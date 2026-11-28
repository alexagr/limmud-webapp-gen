<?php
// "Report a problem" from the web app (assets/js/feedback.js) - PUBLIC endpoint (see .htaccess).
//   POST {"action":"token", "app":..., "device":...}                           -> {"token":...}
//   POST {"action":"send",  "app":..., "device":..., "token":..., "type":"info|app|registration|other",
//         "text":..., "email":..., "phone":..., "page":..., "session":..., "info":{os,br,mode,lang}, "version":..., "lang":...}
// Each report is appended to data/<app>/feedback.jsonl (never rewritten) and e-mailed to the event's address
// (Event sheet, "email") with the sender's e-mail as Reply-To. The admin page is feedback.php.

require __DIR__ . '/reg_lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function fb_out($a, $code = 200)
{
    http_response_code($code);
    exit(json_encode($a, JSON_UNESCAPED_UNICODE));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fb_out(array('ok' => false), 405);
}
$in = json_decode(file_get_contents('php://input', false, null, 0, 20000), true);
$action = isset($in['action']) ? $in['action'] : '';
$app = isset($in['app']) ? $in['app'] : '';
$device = isset($in['device']) ? $in['device'] : '';
if (!is_array($in) || !reg_valid_app($app) || !is_string($device) || !preg_match('/\A[a-z0-9]{8,40}\z/', $device)) {
    fb_out(array('ok' => false), 400);
}
$config = reg_config();

if ($action === 'token') {
    fb_out(array('ok' => true, 'token' => reg_token($app, $device)));
}
if ($action !== 'send') {
    fb_out(array('ok' => false), 400);
}
if (!reg_token_ok($app, $device, isset($in['token']) ? $in['token'] : '')) {
    fb_out(array('ok' => false, 'error' => 'token'));
}

function fb_str($in, $k, $max)
{
    $v = isset($in[$k]) && is_string($in[$k]) ? trim($in[$k]) : '';
    return mb_substr(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $v), 0, $max, 'UTF-8');
}
$text = fb_str($in, 'text', 3000);
$email = fb_str($in, 'email', 120);
$phone = fb_str($in, 'phone', 25);
$type = isset($in['type']) && in_array($in['type'], array('info', 'app', 'registration', 'other'), true) ? $in['type'] : 'other';
$lang = isset($in['lang']) && in_array($in['lang'], array('ru', 'ukr', 'en', 'he'), true) ? $in['lang'] : 'ru';
if (mb_strlen($text, 'UTF-8') < 3) {
    fb_out(array('ok' => false, 'error' => 'text'));
}
if ($email !== '' && !reg_valid_email($email)) {
    fb_out(array('ok' => false, 'error' => 'email'));
}
if ($phone !== '' && (preg_match('/[^\d\s+\-().]/', $phone) || strlen(preg_replace('/\D/', '', $phone)) < 9)) {
    fb_out(array('ok' => false, 'error' => 'phone'));
}
// limits: 5 reports per device per hour; per IP only a safety valve (a venue WiFi is one IP)
$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
if (!reg_counter($app, 'fb:' . $device, 3600, 5) || !reg_counter($app, 'fbip:' . $ip, 3600, 300)) {
    fb_out(array('ok' => false, 'error' => 'busy'));
}

$info = array();
if (isset($in['info']) && is_array($in['info'])) {
    foreach (array('os', 'br', 'mode', 'lang') as $k) {
        if (isset($in['info'][$k]) && is_string($in['info'][$k]) && preg_match('/\A[a-z]{1,12}\z/', $in['info'][$k])) {
            $info[$k] = $in['info'][$k];
        }
    }
}
$report = array(
    'id' => date('ymdHis') . substr(bin2hex(random_bytes(3)), 0, 4),
    't' => time(),
    'type' => $type,
    'text' => $text,
    'email' => $email,
    'phone' => $phone,
    'lang' => $lang,
    'page' => fb_str($in, 'page', 200),
    'session' => fb_str($in, 'session', 300),
    'version' => fb_str($in, 'version', 20),
    'info' => $info,
    'device' => $device,
);
$line = json_encode($report, JSON_UNESCAPED_UNICODE) . "\n";
if (file_put_contents(reg_data_dir($app) . '/feedback.jsonl', $line, FILE_APPEND | LOCK_EX) === false) {
    fb_out(array('ok' => false, 'error' => 'server'), 500);
}

// reply first, then the e-mail to the organisers
$body = json_encode(array('ok' => true), JSON_UNESCAPED_UNICODE);
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

$event = json_decode(@file_get_contents(__DIR__ . '/' . $app . '/json/event.json'), true);
$to = isset($event['email']) ? $event['email'] : '';
$from = isset($config['mail_from']) ? $config['mail_from'] : '';
if (reg_valid_email($to) && reg_valid_email($from)) {
    $types = array('info' => 'Ошибка в программе', 'app' => 'Проблема с приложением', 'registration' => 'Запись на сессию', 'other' => 'Другое');
    $os = array('iphone' => 'iPhone', 'ipad' => 'iPad', 'android' => 'Android', 'windows' => 'Windows', 'mac' => 'Mac', 'linux' => 'Linux');
    $device_line = trim((isset($info['os']) ? (isset($os[$info['os']]) ? $os[$info['os']] : $info['os']) : '') . ', '
        . (isset($info['br']) ? $info['br'] : '') . ', ' . (isset($info['mode']) && $info['mode'] === 'app' ? 'installed app' : 'browser'), ', ');
    $subject = 'Сообщение из приложения: ' . $types[$type] . ' (' . mb_substr(preg_replace('/\s+/u', ' ', $text), 0, 50, 'UTF-8') . ')';
    $mail = $types[$type] . "\n\n" . $text . "\n\n"
        . "E-mail: " . ($email !== '' ? $email : '-') . "\n"
        . "Телефон: " . ($phone !== '' ? $phone : '-') . "\n"
        . "Язык: " . strtoupper($lang) . "\n"
        . "Страница: " . ($report['page'] !== '' ? $report['page'] : '-') . "\n"
        . ($report['session'] !== '' ? "Сессия: " . $report['session'] . "\n" : '')
        . "Устройство: " . $device_line . "\n"
        . "Версия: " . ($report['version'] !== '' ? $report['version'] : '-') . "\n"
        . "Время: " . date('d.m.Y H:i', $report['t']) . "\n"
        . "Номер: " . $report['id'] . "\n";
    $headers = 'From: =?UTF-8?B?' . base64_encode(reg_sender_name('ru')) . '?= <' . $from . ">\r\n";
    if ($email !== '') {
        $headers .= 'Reply-To: ' . $email . "\r\n";   // answer the sender straight from the mailbox
    }
    $headers .= "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n";
    @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $mail, $headers, '-f' . $from);
}
