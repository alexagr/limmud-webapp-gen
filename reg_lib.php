<?php
// Registration in advance for sessions with limited seats - shared by registration.php (public, the web app)
// and registrations.php (HelpDesk, password protected).
//
// Sessions: <app>/json/sessions.json, written by generate.php; a session with registration has
//   'reg' => {seats, waitlist (30% of seats, rounded up), start (ISO time registration opens), category, limit}
// Registrations: data/<app>/registrations.json (data/ is not readable from the web):
//   {"next": 12, "regs": {"5": {id, session, device, name, phone, email, lang, status, created, by, ...}}}
//   status: confirmed | waiting | cancelled.  The waiting list is ordered by id (= time of registration).
// A person = device + name (a phone may register up to 3 people per session, e.g. parents);
// HelpDesk registrations without a device: phone + name.
// Rules (per person): no two sessions at the same time, at most <limit> sessions of a category;
// waiting-list places count too. The HelpDesk can override every rule.

define('REG_PER_DEVICE', 3);           // people one device may register for the same session
define('REG_CANCEL_BEFORE', 3600);     // the app can cancel until 1 hour before the start, later only the HelpDesk
define('REG_PER_CONTACT', 6);          // active registrations (all sessions) per e-mail address and per phone number
define('REG_MAILS_PER_HOUR', 5);       // e-mails to one address per hour (no flooding anyone's mailbox)
define('REG_WAITLIST_EXTRA', 5);       // form opened while there was room: waiting list at most this much above its size
define('REG_TOKEN_TTL', 3600);         // the app's token (from "status") is valid for 1 hour
define('REG_IP_LIMIT', 1000);          // safety valve only: registrations per IP in 10 minutes (a venue WiFi is one IP)

function reg_config()
{
    $config = json_decode(@file_get_contents(__DIR__ . '/data/config.json'), true);
    if (!is_array($config)) {
        $config = array();
    }
    $tz = !empty($config['timezone']) ? $config['timezone'] : 'Asia/Jerusalem';
    date_default_timezone_set($tz);
    $config['timezone'] = $tz;
    return $config;
}

function reg_valid_app($app)
{
    return preg_match('/^limmud\d{4}[a-z0-9_-]*$/', $app) && is_dir(__DIR__ . '/' . $app);
}

// sessions that have registration, by id, with start/end as unix times
function reg_sessions($app)
{
    $all = json_decode(@file_get_contents(__DIR__ . '/' . $app . '/json/sessions.json'), true);
    $out = array();
    if (!is_array($all)) {
        return $out;
    }
    foreach ($all as $s) {
        if (empty($s['reg']) || empty($s['reg']['seats']) || empty($s['reg']['start']) || !strtotime($s['reg']['start'])) {
            continue;   // no (readable) opening time: no registration - fail closed
        }
        $out[$s['id']] = array(
            'id' => (int)$s['id'],
            'title' => $s['title'],
            'title2' => isset($s['title2']) ? $s['title2'] : '',
            'location' => isset($s['location']['name']) ? $s['location']['name'] : '',
            'start' => strtotime($s['start_time']),
            'end' => strtotime($s['end_time']),
            'seats' => (int)$s['reg']['seats'],
            'waitlist' => (int)$s['reg']['waitlist'],
            'opens' => !empty($s['reg']['start']) ? strtotime($s['reg']['start']) : 0,
            'category' => isset($s['reg']['category']) ? $s['reg']['category'] : '',
            'limit' => isset($s['reg']['limit']) ? (int)$s['reg']['limit'] : 0,
        );
    }
    return $out;
}

// ---- storage ----
// Safety: one writer at a time (lock file); atomic write (temp file + rename); the previous version in .bak;
// an hourly snapshot (last 72 kept) in data/<app>/snapshots/; and an append-only change log
// data/<app>/registrations.log.jsonl - one line per changed registration, never rewritten, so the state can be
// rebuilt from it (the last line of each id wins). An unreadable or missing file is never replaced by an empty list
// while any copy exists.

// everything of one event in its own folder: data/<app>/ (registrations, their copies and log, usage stats)
function reg_data_dir($app)
{
    $dir = __DIR__ . '/data/' . $app;
    if (!is_dir($dir)) {
        @mkdir($dir, 0755);
    }
    return $dir;
}

function reg_file($app)
{
    return reg_data_dir($app) . '/registrations.json';
}

function reg_lock($app)
{
    $lock = fopen(reg_file($app) . '.lock', 'c');
    if (!$lock) {
        return null;
    }
    flock($lock, LOCK_EX);
    return $lock;
}

function reg_unlock($lock)
{
    if ($lock) {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function reg_snapshot_dir($app)
{
    return reg_data_dir($app) . '/snapshots';
}

function reg_load($app)
{
    $file = reg_file($app);
    $data = array('next' => 1, 'regs' => array());
    $d = file_exists($file) ? json_decode(file_get_contents($file), true) : null;
    if (is_array($d)) {
        return $d + $data;
    }
    // main file missing or unreadable: the change log (it has every change), then the previous version,
    // then the newest snapshot
    $d = reg_from_log($app);
    if ($d && $d['regs']) {
        return $d;
    }
    $copies = array($file . '.bak');
    $snaps = glob(reg_snapshot_dir($app) . '/*.json');
    if ($snaps) {
        rsort($snaps);
        $copies = array_merge($copies, $snaps);
    }
    foreach ($copies as $c) {
        $d = file_exists($c) ? json_decode(file_get_contents($c), true) : null;
        if (is_array($d)) {
            return $d + $data;
        }
    }
    // nothing readable: start empty only if there was never anything (no file, no copies, no log)
    if (!file_exists($file) && !file_exists($file . '.bak') && !$snaps && !file_exists(reg_log_file($app))) {
        return $data;
    }
    return null;   // refuse: the caller fails instead of writing an empty list over lost data
}

function reg_log_file($app)
{
    return reg_data_dir($app) . '/registrations.log.jsonl';
}

function reg_save($app, $data)
{
    $file = reg_file($app);
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    if ($json === false || file_put_contents($file . '.tmp', $json) !== strlen($json)) {
        @unlink($file . '.tmp');
        return false;
    }
    $old = file_exists($file) ? json_decode(file_get_contents($file), true) : null;
    if (is_array($old)) {
        @copy($file, $file . '.bak');   // only a readable version may replace the previous good copy
    }
    if (!rename($file . '.tmp', $file)) {
        @unlink($file . '.tmp');
        return false;
    }
    // change log (after the new file is in place, so it never holds a change that was not saved):
    // every registration that is new or different from the previous version
    $oldRegs = is_array($old) && isset($old['regs']) ? $old['regs'] : array();
    $lines = '';
    foreach ($data['regs'] as $k => $r) {
        if (!isset($oldRegs[$k]) || $oldRegs[$k] !== $r) {
            $lines .= json_encode(array('t' => date('c'), 'reg' => $r), JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
    if ($lines !== '') {
        file_put_contents(reg_log_file($app), $lines, FILE_APPEND | LOCK_EX);
    }
    // hourly snapshot, the last 72 kept
    $dir = reg_snapshot_dir($app);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755);
    }
    $snap = $dir . '/' . date('Ymd-H') . '.json';
    if (is_dir($dir) && !file_exists($snap)) {
        @copy($file, $snap);
        $all = glob($dir . '/*.json');
        sort($all);
        foreach (array_slice($all, 0, max(0, count($all) - 72)) as $f) {
            @unlink($f);
        }
    }
    return true;
}

// rebuilds the registrations from the change log (last line per id wins) - for recovery, see registrations.php
function reg_from_log($app)
{
    $data = array('next' => 1, 'regs' => array());
    $f = @fopen(reg_log_file($app), 'r');
    if (!$f) {
        return null;
    }
    while (($line = fgets($f)) !== false) {
        $e = json_decode($line, true);
        if (isset($e['reg']['id'])) {
            $data['regs'][(string)$e['reg']['id']] = $e['reg'];
            $data['next'] = max($data['next'], (int)$e['reg']['id'] + 1);
        }
    }
    fclose($f);
    return $data;
}

// the session this registration was made for no longer matches (the sheet changed: other title or time) -> what it was
function reg_session_changed($r, $sessions)
{
    if (!isset($r['session_title'])) {
        return '';
    }
    $s = isset($sessions[$r['session']]) ? $sessions[$r['session']] : null;
    if ($s && $s['title'] === $r['session_title'] && $s['start'] == $r['session_start']) {
        return '';
    }
    return '«' . $r['session_title'] . '», ' . date('d.m H:i', $r['session_start']);
}

// ---- rules ----

function reg_norm_name($name)
{
    return mb_strtolower(preg_replace('/\s+/u', ' ', trim($name)), 'UTF-8');
}

function reg_norm_phone($phone)
{
    $d = preg_replace('/\D/', '', $phone);
    if (strpos($d, '972') === 0) {
        $d = '0' . substr($d, 3);
    }
    return $d;
}

// the same person: same name and (same device or same phone) - a phone's new device id (cleared browser data,
// Safari vs the installed app) or a HelpDesk registration is still the same person; a family on one phone is not
function reg_same_person($a, $b)
{
    if (reg_norm_name($a['name']) !== reg_norm_name($b['name'])) {
        return false;
    }
    return (!empty($a['device']) && $a['device'] === $b['device'])
        || (reg_norm_phone($a['phone']) !== '' && reg_norm_phone($a['phone']) === reg_norm_phone($b['phone']));
}

// the device sees and may cancel this registration: the one that made it, or one it was linked to later
// (the same person registering again from another browser / the installed app - see registration.php)
function reg_owned($r, $device)
{
    return $r['device'] === $device || (!empty($r['devices']) && in_array($device, $r['devices'], true));
}

function reg_active($r)
{
    return $r['status'] === 'confirmed' || $r['status'] === 'waiting';
}

function reg_counts($data, $sid)
{
    $c = array('confirmed' => 0, 'waiting' => 0);
    foreach ($data['regs'] as $r) {
        if ($r['session'] == $sid && reg_active($r)) {
            $c[$r['status']]++;
        }
    }
    return $c;
}

// open / waitlist / full for a new registration
function reg_state($data, $s)
{
    $c = reg_counts($data, $s['id']);
    // as after the waiting list got the free seats (reg_fill runs before every registration)
    $moved = max(0, min($s['seats'] - $c['confirmed'], $c['waiting']));
    $c['confirmed'] += $moved;
    $c['waiting'] -= $moved;
    if ($c['confirmed'] < $s['seats']) {
        return 'open';
    }
    if ($c['waiting'] < $s['waitlist']) {
        return 'waitlist';
    }
    return 'full';
}

function reg_waiting_pos($data, $reg)
{
    $pos = 0;
    foreach ($data['regs'] as $r) {
        if ($r['session'] == $reg['session'] && $r['status'] === 'waiting' && (int)$r['id'] <= (int)$reg['id']) {
            $pos++;
        }
    }
    return $pos;
}

// rule check for a new registration of $person in session $s: null if fine, otherwise [code, detail]
function reg_rule_error($data, $sessions, $s, $person, $ignoreId = 0)
{
    $inCategory = 0;
    foreach ($data['regs'] as $r) {
        if (!reg_active($r) || $r['id'] == $ignoreId || !reg_same_person($r, $person) || !isset($sessions[$r['session']])) {
            continue;
        }
        $o = $sessions[$r['session']];
        if ($o['id'] == $s['id']) {
            return array('already', '');
        }
        if ($o['start'] < $s['end'] && $s['start'] < $o['end']) {
            return array('overlap', $o['title']);
        }
        if ($s['category'] !== '' && $o['category'] === $s['category']) {
            $inCategory++;
        }
    }
    if ($s['category'] !== '' && $s['limit'] > 0 && $inCategory >= $s['limit']) {
        return array('category', $s['category'] . '|' . $s['limit']);
    }
    return null;
}

// adds a registration; $status: '' = automatic (confirmed while seats are left, then the waiting list)
// returns the new registration, or [error code, detail]
// $late: the form was opened while there was room - if everything was taken meanwhile, the waiting list even above its size
function reg_add(&$data, $sessions, $sid, $fields, $status = '', $override = false, $late = false)
{
    if (!isset($sessions[$sid])) {
        return array('session', '');
    }
    $s = $sessions[$sid];
    $reg = array(
        'id' => (int)$data['next'],
        'session' => (int)$sid,
        'device' => isset($fields['device']) ? $fields['device'] : '',
        'name' => trim($fields['name']),
        'first' => isset($fields['first']) ? trim($fields['first']) : '',
        'last' => isset($fields['last']) ? trim($fields['last']) : '',
        'phone' => trim($fields['phone']),
        'email' => trim($fields['email']),
        'lang' => isset($fields['lang']) ? $fields['lang'] : 'ru',
        'by' => isset($fields['by']) ? $fields['by'] : 'app',
        'created' => time(),
        'session_title' => $s['title'],
        'session_start' => $s['start'],
    );
    $err = reg_rule_error($data, $sessions, $s, $reg);
    if (!$err && !$override) {
        // one e-mail address / phone number cannot hold more than REG_PER_CONTACT registrations
        $email = strtolower($reg['email']);
        $phone = reg_norm_phone($reg['phone']);
        $byMail = 0;
        $byPhone = 0;
        foreach ($data['regs'] as $r) {
            if (reg_active($r)) {
                $byMail += ($email !== '' && strtolower($r['email']) === $email) ? 1 : 0;
                $byPhone += ($phone !== '' && reg_norm_phone($r['phone']) === $phone) ? 1 : 0;
            }
        }
        if ($byMail >= REG_PER_CONTACT || $byPhone >= REG_PER_CONTACT) {
            $err = array('contact', (string)REG_PER_CONTACT);
        }
    }
    if ($err && !($override && $err[0] !== 'already')) {
        return $err;
    }
    if ($status === '') {
        $state = reg_state($data, $s);
        if ($state === 'full') {
            // the form was opened while there was room: the waiting list even a little above its size
            $c = reg_counts($data, $sid);
            if (!$late || $c['waiting'] >= $s['waitlist'] + REG_WAITLIST_EXTRA) {
                return array('full', '');
            }
        }
        $status = $state === 'open' ? 'confirmed' : 'waiting';
    }
    $reg['status'] = $status;
    if ($override && $err) {
        $reg['override'] = $err[0];
    }
    $data['regs'][(string)$reg['id']] = $reg;
    $data['next'] = $reg['id'] + 1;
    return $reg;
}

// cancels a registration; a freed seat goes to the first one on the waiting list. Returns the promoted
// registrations (to be told by e-mail and in the app).
function reg_cancel(&$data, $sessions, $id, $by)
{
    $key = (string)$id;
    if (!isset($data['regs'][$key]) || !reg_active($data['regs'][$key])) {
        return array();
    }
    $data['regs'][$key]['status'] = 'cancelled';
    $data['regs'][$key]['cancelled'] = time();
    $data['regs'][$key]['cancelled_by'] = $by;
    return reg_fill($data, $sessions, $data['regs'][$key]['session']);
}

// moves people from the waiting list to free seats (in order), returns them
function reg_fill(&$data, $sessions, $sid)
{
    $promoted = array();
    if (!isset($sessions[$sid])) {
        return $promoted;
    }
    $c = reg_counts($data, $sid);
    $free = $sessions[$sid]['seats'] - $c['confirmed'];
    if ($free <= 0) {
        return $promoted;
    }
    $waiting = array();
    foreach ($data['regs'] as $k => $r) {
        if ($r['session'] == $sid && $r['status'] === 'waiting') {
            $waiting[(int)$r['id']] = $k;
        }
    }
    ksort($waiting);
    foreach ($waiting as $k) {
        if ($free-- <= 0) {
            break;
        }
        $data['regs'][$k]['status'] = 'confirmed';
        $data['regs'][$k]['promoted'] = time();
        $data['regs'][$k]['promoted_seen'] = false;
        $promoted[] = $data['regs'][$k];
    }
    return $promoted;
}

// ---- texts ----

function reg_month($lang, $m)
{
    $names = array(
        'ru' => array('января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'),
        'ukr' => array('січня', 'лютого', 'березня', 'квітня', 'травня', 'червня', 'липня', 'серпня', 'вересня', 'жовтня', 'листопада', 'грудня'),
        'en' => array('January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'),
        'he' => array('בינואר', 'בפברואר', 'במרץ', 'באפריל', 'במאי', 'ביוני', 'ביולי', 'באוגוסט', 'בספטמבר', 'באוקטובר', 'בנובמבר', 'בדצמבר'),
    );
    return $names[isset($names[$lang]) ? $lang : 'ru'][$m - 1];
}

function reg_when($lang, $t)
{
    return date('j', $t) . ' ' . reg_month($lang, (int)date('n', $t)) . ', ' . date('G:i', $t);
}

function reg_mail_text($kind, $lang, $reg, $s, $pos)
{
    $title = ($lang === 'he' && !empty($s['title2'])) ? $s['title2'] : $s['title'];
    $when = reg_when($lang, $s['start']);
    $T = array(
        'ru' => array(
            'confirmed' => array('Запись подтверждена', "Здравствуйте, %name%!\n\nВаша запись на сессию «%title%» (%when%, %room%) принята.\n\nЕсли вы не сможете прийти, пожалуйста, отмените запись в приложении (не позднее чем за час до начала) - ваше место получит следующий в листе ожидания."),
            'waiting' => array('Лист ожидания', "Здравствуйте, %name%!\n\nВсе места на сессию «%title%» (%when%, %room%) уже заняты. Вы в листе ожидания, номер %pos%.\n\nЕсли кто-то отменит запись или не придёт вовремя, вы сможете участвовать - мы сообщим, когда для вас освободится место."),
            'promoted' => array('Для вас освободилось место', "Здравствуйте, %name%!\n\nДля вас освободилось место на сессии «%title%» (%when%, %room%). Ваша запись подтверждена.\n\nЕсли вы не сможете прийти, пожалуйста, отмените запись в приложении."),
            'cancelled' => array('Запись отменена', "Здравствуйте, %name%!\n\nВаша запись на сессию «%title%» (%when%) отменена."),
            'reminder' => array('Скоро начало', "Здравствуйте, %name%!\n\nНапоминаем: через 15 минут начинается сессия «%title%» (%when%, %room%), на которую вы записаны. Ждём вас!"),
            'sign' => 'Лимуд FSU Израиль',   // also the sender name (reg_sender_name)
        ),
        'ukr' => array(
            'confirmed' => array('Запис підтверджено', "Вітаємо, %name%!\n\nВаш запис на сесію «%title%» (%when%, %room%) прийнято.\n\nЯкщо ви не зможете прийти, будь ласка, скасуйте запис у застосунку (не пізніше ніж за годину до початку) - ваше місце отримає наступний у списку очікування."),
            'waiting' => array('Список очікування', "Вітаємо, %name%!\n\nУсі місця на сесію «%title%» (%when%, %room%) вже зайняті. Ви у списку очікування, номер %pos%.\n\nЯкщо хтось скасує запис або не прийде вчасно, ви зможете взяти участь - ми повідомимо, коли для вас звільниться місце."),
            'promoted' => array('Для вас звільнилося місце', "Вітаємо, %name%!\n\nДля вас звільнилося місце на сесії «%title%» (%when%, %room%). Ваш запис підтверджено.\n\nЯкщо ви не зможете прийти, будь ласка, скасуйте запис у застосунку."),
            'cancelled' => array('Запис скасовано', "Вітаємо, %name%!\n\nВаш запис на сесію «%title%» (%when%) скасовано."),
            'reminder' => array('Скоро початок', "Вітаємо, %name%!\n\nНагадуємо: за 15 хвилин починається сесія «%title%» (%when%, %room%), на яку ви записані. Чекаємо на вас!"),
            'sign' => 'Limmud FSU',   // also the sender name (reg_sender_name)
        ),
        'en' => array(
            'confirmed' => array('Registration confirmed', "Hello %name%,\n\nYour registration for \"%title%\" (%when%, %room%) is confirmed.\n\nIf you can't come, please cancel it in the app (at least an hour before the start) - your seat will go to the next person on the waiting list."),
            'waiting' => array('Waiting list', "Hello %name%,\n\nAll seats for \"%title%\" (%when%, %room%) are taken. You are on the waiting list, number %pos%.\n\nIf someone cancels or doesn't arrive in time, you will be able to take part - we will let you know when a seat becomes free for you."),
            'promoted' => array('A seat is now free for you', "Hello %name%,\n\nA seat at \"%title%\" (%when%, %room%) is now free for you. Your registration is confirmed.\n\nIf you can't come, please cancel it in the app."),
            'cancelled' => array('Registration cancelled', "Hello %name%,\n\nYour registration for \"%title%\" (%when%) has been cancelled."),
            'reminder' => array('Starting soon', "Hello %name%,\n\nA reminder: \"%title%\" (%when%, %room%), which you registered for, starts in 15 minutes. See you there!"),
            'sign' => 'Limmud FSU',   // also the sender name (reg_sender_name)
        ),
        'he' => array(
            'confirmed' => array('ההרשמה אושרה', "שלום %name%,\n\nההרשמה שלך למפגש \"%title%\" (%when%, %room%) התקבלה.\n\nאם לא תוכלו להגיע, אנא בטלו את ההרשמה באפליקציה (לפחות שעה לפני ההתחלה) - המקום יעבור לבא ברשימת ההמתנה."),
            'waiting' => array('רשימת המתנה', "שלום %name%,\n\nכל המקומות במפגש \"%title%\" (%when%, %room%) תפוסים. נרשמתם לרשימת ההמתנה, מספר %pos%.\n\nאם מישהו יבטל או לא יגיע בזמן, תוכלו להשתתף - נודיע לכם כשיתפנה מקום."),
            'promoted' => array('התפנה עבורך מקום', "שלום %name%,\n\nהתפנה עבורך מקום במפגש \"%title%\" (%when%, %room%). ההרשמה שלך אושרה.\n\nאם לא תוכלו להגיע, אנא בטלו את ההרשמה באפליקציה."),
            'cancelled' => array('ההרשמה בוטלה', "שלום %name%,\n\nההרשמה שלך למפגש \"%title%\" (%when%) בוטלה."),
            'reminder' => array('מתחיל בקרוב', "שלום %name%,\n\nתזכורת: המפגש \"%title%\" (%when%, %room%), שנרשמתם אליו, מתחיל בעוד 15 דקות. מחכים לכם!"),
            'sign' => 'לימוד FSU ישראל',   // also the sender name (reg_sender_name)
        ),
    );
    $t = isset($T[$lang]) ? $T[$lang] : $T['ru'];
    $body = strtr($t[$kind][1], array('%name%' => !empty($reg['first']) ? $reg['first'] : $reg['name'], '%title%' => $title, '%when%' => $when,
                                     '%room%' => $s['location'], '%pos%' => $pos)) . "\n\n" . $t['sign'];
    return array($t[$kind][0] . ': ' . $title, $body);
}

// e-mail to the registered person (confirmed / waiting / promoted / cancelled); failures are ignored
function reg_mail($config, $data, $sessions, $reg, $kind)
{
    if (empty($reg['email']) || !reg_valid_email($reg['email']) || !isset($sessions[$reg['session']])
        || empty($config['mail_from']) || !reg_valid_email($config['mail_from'])) {
        return false;
    }
    if (!empty($config['_app']) && !reg_mail_allowed($config['_app'], $reg['email'])) {
        return false;   // this address got enough e-mails this hour
    }
    $from = $config['mail_from'];
    $pos = $reg['status'] === 'waiting' ? reg_waiting_pos($data, $reg) : 0;
    list($subject, $body) = reg_mail_text($kind, $reg['lang'], $reg, $sessions[$reg['session']], $pos);
    $headers = 'From: =?UTF-8?B?' . base64_encode(reg_sender_name($reg['lang'])) . '?= <' . $from . ">\r\n";
    if (!empty($config['mail_reply_to']) && reg_valid_email($config['mail_reply_to'])) {
        $headers .= 'Reply-To: ' . $config['mail_reply_to'] . "\r\n";
    }
    $headers .= "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n";
    return @mail($reg['email'], '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers, '-f' . $from);
}

// plain addresses only (no quoted parts, line breaks or other header tricks)
function reg_valid_email($e)
{
    return (bool)preg_match('/\A[A-Za-z0-9._%+-]+@[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)+\z/', $e) && filter_var($e, FILTER_VALIDATE_EMAIL);
}

// small counters in data/<app>/limits.json (own lock): e-mails per address per hour, registrations per IP per 10 min
function reg_counter($app, $key, $window, $max)
{
    $file = reg_data_dir($app) . '/limits.json';
    $f = fopen($file, 'c+');
    if (!$f) {
        return true;
    }
    flock($f, LOCK_EX);
    $all = json_decode(stream_get_contents($f), true);
    if (!is_array($all)) {
        $all = array();
    }
    $now = time();
    foreach ($all as $k => $v) {
        if ($now - $v[0] >= $v[2]) {
            unset($all[$k]);   // window over
        }
    }
    $ok = !isset($all[$key]) || $all[$key][1] < $max;
    if ($ok) {
        $all[$key] = isset($all[$key]) ? array($all[$key][0], $all[$key][1] + 1, $window) : array($now, 1, $window);
    }
    ftruncate($f, 0);
    rewind($f);
    fwrite($f, json_encode($all));
    flock($f, LOCK_UN);
    fclose($f);
    return $ok;
}

function reg_mail_allowed($app, $email)
{
    return reg_counter($app, 'm:' . strtolower($email), 3600, REG_MAILS_PER_HOUR);
}

// the app's token: issued with "status", required for register / cancel; bound to the device, valid REG_TOKEN_TTL
function reg_secret($app)
{
    $file = reg_data_dir($app) . '/secret.txt';
    $s = @file_get_contents($file);
    if (!$s || strlen($s) < 32) {
        $s = bin2hex(random_bytes(32));
        file_put_contents($file, $s);
    }
    return trim($s);
}

function reg_token($app, $device)
{
    $t = time();
    return $t . '.' . substr(hash_hmac('sha256', $device . '|' . $t, reg_secret($app)), 0, 32);
}

function reg_token_ok($app, $device, $token)
{
    if (!is_string($token) || !preg_match('/\A(\d{10})\.([0-9a-f]{32})\z/', $token, $m)) {
        return false;
    }
    if (time() - (int)$m[1] > REG_TOKEN_TTL || (int)$m[1] > time() + 60) {
        return false;
    }
    return hash_equals(substr(hash_hmac('sha256', $device . '|' . $m[1], reg_secret($app)), 0, 32), $m[2]);
}

// sender name and signature, in the language of the registration
function reg_sender_name($lang)
{
    $names = array('ru' => 'Лимуд FSU Израиль', 'ukr' => 'Limmud FSU', 'en' => 'Limmud FSU', 'he' => 'לימוד FSU ישראל');
    return isset($names[$lang]) ? $names[$lang] : $names['ru'];
}

// first and last name (each 1-40 characters, letters expected) -> error code or null
function reg_validate_names($first, $last)
{
    foreach (array('first' => $first, 'last' => $last) as $k => $v) {
        $len = mb_strlen(trim($v), 'UTF-8');
        if ($len < 1 || $len > 40 || !preg_match('/\p{L}/u', $v)) {
            return $k;
        }
    }
    return null;
}

function reg_full_name($first, $last)
{
    return preg_replace('/\s+/u', ' ', trim($first) . ' ' . trim($last));
}

function reg_validate_fields($name, $phone, $email)
{
    $len = mb_strlen(trim($name), 'UTF-8');
    if ($len < 2 || $len > 80) {
        return 'name';
    }
    $digits = strlen(preg_replace('/\D/', '', $phone));
    if ($digits < 9 || $digits > 15 || preg_match('/[^\d\s+\-().]/', $phone)) {
        return 'phone';
    }
    if (strlen($email) > 120 || !reg_valid_email(trim($email))) {
        return 'email';
    }
    return null;
}

// e-mail reminders 15 minutes before the start, to everyone confirmed. Runs at most once a minute, triggered by
// the app's own requests (registration.php) - and/or by cron (reg_remind.php). Sessions that started more than
// 5 minutes ago are skipped (a late reminder is useless).
function reg_send_reminders($app, $config)
{
    $stamp = reg_file($app) . '.remind';
    if (file_exists($stamp) && time() - filemtime($stamp) < 60) {
        return 0;
    }
    @touch($stamp);
    $sessions = reg_sessions($app);
    $now = time();
    $due = array();
    foreach ($sessions as $id => $s) {
        if ($s['start'] - $now <= 15 * 60 && $now - $s['start'] <= 5 * 60) {
            $due[$id] = true;
        }
    }
    if (!$due) {
        return 0;
    }
    $lock = reg_lock($app);
    $data = $lock ? reg_load($app) : null;
    if ($data === null) {
        reg_unlock($lock);
        return 0;
    }
    $send = array();
    foreach ($data['regs'] as $k => $r) {
        if ($r['status'] === 'confirmed' && isset($due[$r['session']]) && empty($r['reminded'])) {
            $data['regs'][$k]['reminded'] = $now;   // marked first: never twice, even if sending fails
            // registered in the last 15 minutes before the start: they know, no "starts in 15 minutes"
            if ($r['created'] < $sessions[$r['session']]['start'] - 15 * 60 || !empty($r['promoted'])) {
                $send[] = $data['regs'][$k];
            }
        }
    }
    $changed = false;
    foreach ($data['regs'] as $r) {
        if (isset($due[$r['session']]) && !empty($r['reminded']) && $r['reminded'] == $now) {
            $changed = true;
            break;
        }
    }
    if ($changed && !reg_save($app, $data)) {
        $send = array();
    }
    reg_unlock($lock);
    $config['_app'] = $app;
    foreach ($send as $r) {
        reg_mail($config, $data, $sessions, $r, 'reminder');
    }
    return count($send);
}
