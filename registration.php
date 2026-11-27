<?php
// Registration in advance - public endpoint of the web app (assets/js/registration.js); see reg_lib.php.
// Public on purpose (see .htaccess). It never returns other people's data: a device only sees its own registrations.
//   POST {"action":"status",   "app":..., "device":...}                         -> also a token for the next calls
//   POST {"action":"register", "app":..., "device":..., "token":..., "session":12, "first":..., "last":..., "phone":..., "email":...,
//         "lang":"ru", "formOpen":true}   (formOpen: the form was opened while there were free seats)
//   POST {"action":"cancel",   "app":..., "device":..., "token":..., "id":5}
//   POST {"action":"seen",     "app":..., "device":..., "ids":[5]}     (the app told the user about a promotion)
// E-mails are sent after the reply (the app does not wait for the mail server).

require __DIR__ . '/reg_lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$after = array();   // work done after the reply was sent: e-mails, reminders

function out($a, $code = 200)
{
    global $after;
    http_response_code($code);
    $body = json_encode($a, JSON_UNESCAPED_UNICODE);
    if (!$after) {
        exit($body);
    }
    // send the reply, then do the slow part
    ignore_user_abort(true);
    header('Content-Length: ' . strlen($body));
    header('Connection: close');
    echo $body;
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        @ob_end_flush();
        flush();
    }
    foreach ($after as $job) {
        call_user_func($job);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    out(array('ok' => false), 405);
}
$in = json_decode(file_get_contents('php://input', false, null, 0, 5000), true);
$action = isset($in['action']) ? $in['action'] : '';
$app = isset($in['app']) ? $in['app'] : '';
$device = isset($in['device']) ? $in['device'] : '';
if (!is_array($in) || !reg_valid_app($app) || !is_string($device) || !preg_match('/\A[a-z0-9]{8,40}\z/', $device)) {
    out(array('ok' => false), 400);
}

$config = reg_config();
$config['_app'] = $app;
$sessions = reg_sessions($app);
$now = time();

function status_reply($data, $sessions, $device, $now, $config, $app)
{
    $states = array();
    foreach ($sessions as $id => $s) {
        if ($now >= $s['start']) {
            $state = 'closed';
        } elseif ($s['opens'] && $now < $s['opens']) {
            $state = 'soon';
        } else {
            $state = reg_state($data, $s);
        }
        $states[$id] = array('state' => $state, 'opens' => $s['opens'] ? date('c', $s['opens']) : '',
                             'start' => date('c', $s['start']), 'title' => $s['title'], 'title2' => $s['title2'], 'room' => $s['location'],
                             'cancelUntil' => date('c', $s['start'] - REG_CANCEL_BEFORE));
    }
    $mine = array();
    foreach ($data['regs'] as $r) {
        if (!reg_owned($r, $device) || !reg_active($r) || !isset($sessions[$r['session']])) {
            continue;
        }
        $mine[] = array('id' => $r['id'], 'session' => $r['session'], 'name' => $r['name'], 'status' => $r['status'],
                        'pos' => $r['status'] === 'waiting' ? reg_waiting_pos($data, $r) : 0,
                        'canCancel' => $now < $sessions[$r['session']]['start'] - REG_CANCEL_BEFORE,
                        'promoted' => !empty($r['promoted']) && empty($r['promoted_seen']));
    }
    return array('ok' => true, 'now' => date('c', $now), 'tz' => $config['timezone'], 'sessions' => $states, 'mine' => $mine,
                 'token' => reg_token($app, $device));
}

if ($action === 'status') {
    $data = reg_load($app);
    if ($data === null) {
        out(array('ok' => false), 500);
    }
    // e-mail reminders 15 min before the start (at most once a minute), after the reply
    $after[] = function () use ($app, $config) { reg_send_reminders($app, $config); };
    out(status_reply($data, $sessions, $device, $now, $config, $app));
}

if (!in_array($action, array('register', 'cancel', 'seen'))) {
    out(array('ok' => false), 400);
}
// register / cancel need the token the app got with "status" (stops scripts that just fire requests)
if ($action !== 'seen' && !reg_token_ok($app, $device, isset($in['token']) ? $in['token'] : '')) {
    out(array('ok' => false, 'error' => 'token'));
}
// safety valve only: a venue WiFi is one IP for everybody, so the limit is far above any real crowd
if ($action === 'register') {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    if (!reg_counter($app, 'ip:' . $ip, 600, REG_IP_LIMIT)) {
        out(array('ok' => false, 'error' => 'busy'));
    }
}

$lock = reg_lock($app);
$data = $lock ? reg_load($app) : null;
if ($data === null) {
    reg_unlock($lock);
    out(array('ok' => false), 500);
}
$mails = array();   // sent after the data is saved and the reply is out

function fail($lock, $code, $detail = '')
{
    reg_unlock($lock);
    out(array('ok' => false, 'error' => $code, 'detail' => $detail));
}

if ($action === 'register') {
    $sid = isset($in['session']) ? (int)$in['session'] : 0;
    $first = isset($in['first']) && is_string($in['first']) ? trim($in['first']) : '';
    $last = isset($in['last']) && is_string($in['last']) ? trim($in['last']) : '';
    $name = reg_full_name($first, $last);
    $phone = isset($in['phone']) && is_string($in['phone']) ? $in['phone'] : '';
    $email = isset($in['email']) && is_string($in['email']) ? $in['email'] : '';
    $lang = isset($in['lang']) && in_array($in['lang'], array('ru', 'ukr', 'en', 'he'), true) ? $in['lang'] : 'ru';
    if (!isset($sessions[$sid])) {
        fail($lock, 'session');
    }
    $s = $sessions[$sid];
    // registration from phones only (the app shows no button on computers; this stops the obvious other cases)
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    if (preg_match('/Windows NT|CrOS/', $ua) || (preg_match('/X11|Linux/', $ua) && !preg_match('/Android/', $ua))) {
        fail($lock, 'desktop');
    }
    if ($s['opens'] && $now < $s['opens']) {
        fail($lock, 'soon', date('c', $s['opens']));
    }
    if ($now >= $s['start']) {
        fail($lock, 'closed');
    }
    $bad = reg_validate_names($first, $last) ?: reg_validate_fields($name, $phone, $email);
    if ($bad) {
        fail($lock, 'invalid', $bad);
    }
    $mineHere = 0;
    $mineAll = 0;
    foreach ($data['regs'] as $r) {
        if (reg_owned($r, $device) && reg_active($r)) {
            $mineAll++;
            if ($r['session'] == $sid) {
                $mineHere++;
            }
        }
    }
    if ($mineHere >= REG_PER_DEVICE || $mineAll >= 40) {
        fail($lock, 'device', (string)REG_PER_DEVICE);
    }
    // free seats go to the waiting list first (e.g. after seats were added in the sheet)
    foreach (reg_fill($data, $sessions, $sid) as $p) {
        $mails[] = array($p, 'promoted');
    }
    $reg = reg_add($data, $sessions, $sid, array('device' => $device, 'name' => $name, 'first' => $first, 'last' => $last, 'phone' => $phone, 'email' => $email,
                                                  'lang' => $lang, 'by' => 'app'), '', false, !empty($in['formOpen']));
    $linked = null;
    if (!isset($reg['id']) && $reg[0] === 'already') {
        // the same person (name + phone) is already registered here, e.g. from the website and now from the app:
        // show that registration on this device too (and let it be cancelled from here)
        foreach ($data['regs'] as $k => $r) {
            if ($r['session'] == $sid && reg_active($r) && reg_same_person($r, array('name' => $name, 'phone' => $phone, 'device' => $device))) {
                if (!reg_owned($r, $device)) {
                    $data['regs'][$k]['devices'] = array_values(array_unique(array_merge(isset($r['devices']) ? $r['devices'] : array(), array($device))));
                }
                $linked = $data['regs'][$k];
                break;
            }
        }
    }
    if ($linked) {
        $result = array('ok' => true, 'linked' => true, 'id' => $linked['id'], 'status' => $linked['status'],
                        'pos' => $linked['status'] === 'waiting' ? reg_waiting_pos($data, $linked) : 0);
    } else {
        if (!isset($reg['id'])) {
            if ($mails) {   // promotions above still count
                reg_save($app, $data);
            }
            fail($lock, $reg[0], $reg[1]);
        }
        $mails[] = array($reg, $reg['status']);
        $result = array('ok' => true, 'id' => $reg['id'], 'status' => $reg['status'],
                        'pos' => $reg['status'] === 'waiting' ? reg_waiting_pos($data, $reg) : 0);
    }
} elseif ($action === 'cancel') {
    $id = isset($in['id']) ? (string)(int)$in['id'] : '';
    if (!isset($data['regs'][$id]) || !reg_owned($data['regs'][$id], $device) || !reg_active($data['regs'][$id])) {
        fail($lock, 'notfound');
    }
    $sid = $data['regs'][$id]['session'];
    if (isset($sessions[$sid]) && $now >= $sessions[$sid]['start'] - REG_CANCEL_BEFORE) {
        fail($lock, 'late');
    }
    foreach (reg_cancel($data, $sessions, $id, 'app') as $p) {
        $mails[] = array($p, 'promoted');
    }
    $mails[] = array($data['regs'][$id], 'cancelled');
    $result = array('ok' => true);
} else {   // seen
    $ids = isset($in['ids']) && is_array($in['ids']) ? array_slice($in['ids'], 0, 50) : array();
    foreach ($ids as $id) {
        $k = (string)(int)$id;
        if (isset($data['regs'][$k]) && reg_owned($data['regs'][$k], $device)) {
            $data['regs'][$k]['promoted_seen'] = true;
        }
    }
    $result = array('ok' => true);
}

if (!reg_save($app, $data)) {
    fail($lock, 'server');
}
reg_unlock($lock);
if ($mails) {
    $after[] = function () use ($config, $data, $sessions, $mails) {
        foreach ($mails as $m) {
            reg_mail($config, $data, $sessions, $m[0], $m[1]);
        }
    };
}
out($result + array('state' => status_reply($data, $sessions, $device, $now, $config, $app)));
