<?php
// Registration in advance - public endpoint of the web app (assets/js/registration.js); see reg_lib.php.
// Public on purpose (see .htaccess). It never returns other people's data: a device only sees its own registrations.
//   POST {"action":"status",   "app":..., "device":...}
//   POST {"action":"register", "app":..., "device":..., "session":12, "name":..., "phone":..., "email":..., "lang":"ru"}
//   POST {"action":"cancel",   "app":..., "device":..., "id":5}
//   POST {"action":"seen",     "app":..., "device":..., "ids":[5]}     (the app told the user about a promotion)

require __DIR__ . '/reg_lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out($a, $code = 200)
{
    http_response_code($code);
    exit(json_encode($a, JSON_UNESCAPED_UNICODE));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    out(array('ok' => false), 405);
}
$in = json_decode(file_get_contents('php://input', false, null, 0, 5000), true);
$action = isset($in['action']) ? $in['action'] : '';
$app = isset($in['app']) ? $in['app'] : '';
$device = isset($in['device']) ? $in['device'] : '';
if (!is_array($in) || !reg_valid_app($app) || !preg_match('/^[a-z0-9]{8,40}$/', $device)) {
    out(array('ok' => false), 400);
}

$config = reg_config();
$sessions = reg_sessions($app);
$now = time();

function status_reply($data, $sessions, $device, $now, $config)
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
        if ($r['device'] !== $device || !reg_active($r) || !isset($sessions[$r['session']])) {
            continue;
        }
        $mine[] = array('id' => $r['id'], 'session' => $r['session'], 'name' => $r['name'], 'status' => $r['status'],
                        'pos' => $r['status'] === 'waiting' ? reg_waiting_pos($data, $r) : 0,
                        'canCancel' => $now < $sessions[$r['session']]['start'] - REG_CANCEL_BEFORE,
                        'promoted' => !empty($r['promoted']) && empty($r['promoted_seen']));
    }
    return array('ok' => true, 'now' => date('c', $now), 'tz' => $config['timezone'], 'sessions' => $states, 'mine' => $mine);
}

if ($action === 'status') {
    reg_send_reminders($app, $config);   // e-mail reminders 15 min before the start (at most once a minute)
    $data = reg_load($app);
    if ($data === null) {
        out(array('ok' => false), 500);
    }
    out(status_reply($data, $sessions, $device, $now, $config));
}

if (!in_array($action, array('register', 'cancel', 'seen'))) {
    out(array('ok' => false), 400);
}

$lock = reg_lock($app);
$data = $lock ? reg_load($app) : null;
if ($data === null) {
    reg_unlock($lock);
    out(array('ok' => false), 500);
}
$mails = array();   // sent after the data is saved and unlocked

function fail($lock, $code, $detail = '')
{
    reg_unlock($lock);
    out(array('ok' => false, 'error' => $code, 'detail' => $detail));
}

if ($action === 'register') {
    $sid = isset($in['session']) ? (int)$in['session'] : 0;
    $name = isset($in['name']) ? (string)$in['name'] : '';
    $phone = isset($in['phone']) ? (string)$in['phone'] : '';
    $email = isset($in['email']) ? (string)$in['email'] : '';
    $lang = isset($in['lang']) && in_array($in['lang'], array('ru', 'ukr', 'en', 'he')) ? $in['lang'] : 'ru';
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
    $bad = reg_validate_fields($name, $phone, $email);
    if ($bad) {
        fail($lock, 'invalid', $bad);
    }
    $mineHere = 0;
    $mineAll = 0;
    foreach ($data['regs'] as $r) {
        if ($r['device'] === $device && reg_active($r)) {
            $mineAll++;
            if ($r['session'] == $sid) {
                $mineHere++;
            }
        }
    }
    if ($mineHere >= REG_PER_DEVICE) {
        fail($lock, 'device', (string)REG_PER_DEVICE);
    }
    if ($mineAll >= 40) {   // guard against flooding
        fail($lock, 'device', (string)REG_PER_DEVICE);
    }
    $reg = reg_add($data, $sessions, $sid, array('device' => $device, 'name' => $name, 'phone' => $phone, 'email' => $email,
                                                  'lang' => $lang, 'by' => 'app'), '', false, !empty($in['formOpen']));
    if (!isset($reg['id'])) {
        fail($lock, $reg[0], $reg[1]);
    }
    $mails[] = array($reg, $reg['status']);
    $result = array('ok' => true, 'id' => $reg['id'], 'status' => $reg['status'],
                    'pos' => $reg['status'] === 'waiting' ? reg_waiting_pos($data, $reg) : 0);
} elseif ($action === 'cancel') {
    $id = isset($in['id']) ? (string)(int)$in['id'] : '';
    if (!isset($data['regs'][$id]) || $data['regs'][$id]['device'] !== $device || !reg_active($data['regs'][$id])) {
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
    $ids = isset($in['ids']) && is_array($in['ids']) ? $in['ids'] : array();
    foreach ($ids as $id) {
        $k = (string)(int)$id;
        if (isset($data['regs'][$k]) && $data['regs'][$k]['device'] === $device) {
            $data['regs'][$k]['promoted_seen'] = true;
        }
    }
    $result = array('ok' => true);
}

if (!reg_save($app, $data)) {
    fail($lock, 'server');
}
reg_unlock($lock);
foreach ($mails as $m) {
    reg_mail($config, $data, $sessions, $m[0], $m[1]);
}
$st = status_reply($data, $sessions, $device, $now, $config);
out($result + array('state' => $st));
