<?php
// Registration in advance for sessions with limited seats - shared by registration.php (public, the web app)
// and registrations.php (HelpDesk, password protected).
//
// Sessions: <app>/json/sessions.json, written by generate.php; a session with registration has
//   'reg' => {seats, waitlist (30% of seats, rounded up), start (ISO time registration opens), category, limit}
// Registrations: data/registrations_<app>.json (data/ is not readable from the web):
//   {"next": 12, "regs": {"5": {id, session, device, name, phone, email, lang, status, created, by, ...}}}
//   status: confirmed | waiting | cancelled.  The waiting list is ordered by id (= time of registration).
// A person = device + name (a phone may register up to 3 people per session, e.g. parents);
// HelpDesk registrations without a device: phone + name.
// Rules (per person): no two sessions at the same time, at most <limit> sessions of a category;
// waiting-list places count too. The HelpDesk can override every rule.

define('REG_PER_DEVICE', 3);           // people one device may register for the same session
define('REG_CANCEL_BEFORE', 3600);     // the app can cancel until 1 hour before the start, later only the HelpDesk

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
        if (empty($s['reg']) || empty($s['reg']['seats'])) {
            continue;
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

// ---- storage: lock file + atomic write + backup (same as stats_report.php) ----

function reg_file($app)
{
    return __DIR__ . '/data/registrations_' . $app . '.json';
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

function reg_load($app)
{
    $file = reg_file($app);
    $data = array('next' => 1, 'regs' => array());
    if (!file_exists($file)) {
        return $data;
    }
    $d = json_decode(file_get_contents($file), true);
    if (!is_array($d)) {
        $d = json_decode(@file_get_contents($file . '.bak'), true);   // never lose data we cannot read
        if (!is_array($d)) {
            return null;
        }
    }
    return $d + $data;
}

function reg_save($app, $data)
{
    $file = reg_file($app);
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    if (file_put_contents($file . '.tmp', $json) !== strlen($json)) {
        @unlink($file . '.tmp');
        return false;
    }
    if (file_exists($file)) {
        @copy($file, $file . '.bak');
    }
    return rename($file . '.tmp', $file);
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

function reg_person($r)
{
    return (!empty($r['device']) ? 'd:' . $r['device'] : 'p:' . reg_norm_phone($r['phone'])) . '|' . reg_norm_name($r['name']);
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
        if (!reg_active($r) || $r['id'] == $ignoreId || reg_person($r) !== $person || !isset($sessions[$r['session']])) {
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
        'phone' => trim($fields['phone']),
        'email' => trim($fields['email']),
        'lang' => isset($fields['lang']) ? $fields['lang'] : 'ru',
        'by' => isset($fields['by']) ? $fields['by'] : 'app',
        'created' => time(),
    );
    $err = reg_rule_error($data, $sessions, $s, reg_person($reg));
    if ($err && !($override && $err[0] !== 'already')) {
        return $err;
    }
    if ($status === '') {
        $state = reg_state($data, $s);
        if ($state === 'full' && !$late) {
            return array('full', '');
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
    $body = strtr($t[$kind][1], array('%name%' => $reg['name'], '%title%' => $title, '%when%' => $when,
                                     '%room%' => $s['location'], '%pos%' => $pos)) . "\n\n" . $t['sign'];
    return array($t[$kind][0] . ': ' . $title, $body);
}

// e-mail to the registered person (confirmed / waiting / promoted / cancelled); failures are ignored
function reg_mail($config, $data, $sessions, $reg, $kind)
{
    if (empty($reg['email']) || !filter_var($reg['email'], FILTER_VALIDATE_EMAIL) || !isset($sessions[$reg['session']])) {
        return false;
    }
    $from = !empty($config['mail_from']) ? $config['mail_from'] : 'noreply@' . preg_replace('/^www\./', '', $_SERVER['HTTP_HOST']);
    $pos = $reg['status'] === 'waiting' ? reg_waiting_pos($data, $reg) : 0;
    list($subject, $body) = reg_mail_text($kind, $reg['lang'], $reg, $sessions[$reg['session']], $pos);
    $headers = 'From: =?UTF-8?B?' . base64_encode(reg_sender_name($reg['lang'])) . '?= <' . $from . ">\r\n";
    if (!empty($config['mail_reply_to'])) {
        $headers .= 'Reply-To: ' . $config['mail_reply_to'] . "\r\n";
    }
    $headers .= "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n";
    return @mail($reg['email'], '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers, '-f' . $from);
}

// sender name and signature, in the language of the registration
function reg_sender_name($lang)
{
    $names = array('ru' => 'Лимуд FSU Израиль', 'ukr' => 'Limmud FSU', 'en' => 'Limmud FSU', 'he' => 'לימוד FSU ישראל');
    return isset($names[$lang]) ? $names[$lang] : $names['ru'];
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
    if (strlen($email) > 120 || !filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
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
            $send[] = $data['regs'][$k];
        }
    }
    if ($send && !reg_save($app, $data)) {
        $send = array();
    }
    reg_unlock($lock);
    foreach ($send as $r) {
        reg_mail($config, $data, $sessions, $r, 'reminder');
    }
    return count($send);
}
