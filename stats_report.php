<?php
// Receives anonymous bookmark reports from the web app (assets/js/stats_report.js).
// Public on purpose (see .htaccess): it only accepts data, never shows any.
// Keeps the latest list of starred sessions per anonymous device id in data/stats_<app>.json.

header('Content-Type: application/json');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('{"ok":false}');
}

$raw = file_get_contents('php://input', false, null, 0, 20000);
$in = json_decode($raw, true);
$app = isset($in['app']) ? $in['app'] : '';
$device = isset($in['device']) ? $in['device'] : '';
$sessions = isset($in['sessions']) && is_array($in['sessions']) ? $in['sessions'] : null;

// only known apps, sane ids, at most 300 sessions per device
if (!preg_match('/^limmud\d{4}[a-z0-9_-]*$/', $app) || !is_dir(__DIR__ . '/' . $app)
    || !preg_match('/^[a-z0-9]{8,40}$/', $device) || $sessions === null || count($sessions) > 300) {
    http_response_code(400);
    exit('{"ok":false}');
}
$ids = array();
foreach ($sessions as $s) {
    if (is_int($s) || (is_string($s) && ctype_digit($s))) {
        $ids[] = (int)$s;
    }
}
$ids = array_values(array_unique($ids));
sort($ids);

$file = __DIR__ . '/data/stats_' . $app . '.json';
$fp = fopen($file, 'c+');
if (!$fp) {
    http_response_code(500);
    exit('{"ok":false}');
}
flock($fp, LOCK_EX);
$data = json_decode(stream_get_contents($fp), true);
if (!is_array($data)) {
    $data = array();
}
if (!isset($data[$device]) && count($data) >= 20000) {   // guard against flooding with fake devices
    flock($fp, LOCK_UN);
    fclose($fp);
    http_response_code(429);
    exit('{"ok":false}');
}
$data[$device] = array('s' => $ids, 't' => time());
ftruncate($fp, 0);
rewind($fp);
fwrite($fp, json_encode($data));
fflush($fp);
flock($fp, LOCK_UN);
fclose($fp);

echo '{"ok":true}';
