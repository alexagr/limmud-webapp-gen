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
$remove = !empty($in['remove']);   // team device (opened admin.php): forget it
if ($remove) {
    $sessions = array();
}

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

// rough device profile: only known values are kept
$allowed = array(
    'os' => array('android', 'iphone', 'ipad', 'windows', 'mac', 'linux', 'other'),
    'br' => array('chrome', 'safari', 'samsung', 'firefox', 'edge', 'yandex', 'opera', 'other'),
    'mode' => array('app', 'browser'),
    'lang' => array('ru', 'he', 'en', 'ukr'),
);
$info = array();
if (isset($in['info']) && is_array($in['info'])) {
    foreach ($allowed as $k => $values) {
        if (isset($in['info'][$k]) && in_array($in['info'][$k], $values, true)) {
            $info[$k] = $in['info'][$k];
        }
    }
}
sort($ids);

$file = __DIR__ . '/data/stats_' . $app . '.json';
$lock = fopen($file . '.lock', 'c');   // separate lock file: the data file itself is replaced by rename
if (!$lock) {
    http_response_code(500);
    exit('{"ok":false}');
}
flock($lock, LOCK_EX);
function fail_unlock($lock, $code) {
    flock($lock, LOCK_UN);
    fclose($lock);
    http_response_code($code);
    exit('{"ok":false}');
}
$data = array();
$raw = file_exists($file) ? file_get_contents($file) : '';
if ($raw !== '' && $raw !== false) {
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        // never overwrite data we cannot read: try the last good copy, otherwise refuse
        $bak = file_exists($file . '.bak') ? json_decode(file_get_contents($file . '.bak'), true) : null;
        if (!is_array($bak)) {
            fail_unlock($lock, 500);
        }
        $data = $bak;
    }
}
if (!$remove && !isset($data[$device]) && count($data) >= 20000) {   // guard against flooding with fake devices
    fail_unlock($lock, 429);
}
if ($remove) {
    unset($data[$device]);
} else {
$prevInfo = isset($data[$device]['i']) ? $data[$device]['i'] : null;   // an old app version sends no profile: keep the known one
$first = isset($data[$device]['f']) ? $data[$device]['f'] : (isset($data[$device]['t']) ? $data[$device]['t'] : time());
$data[$device] = array('s' => $ids, 't' => time(), 'f' => $first);
if ($info) {
    $data[$device]['i'] = $info;
} elseif ($prevInfo) {
    $data[$device]['i'] = $prevInfo;
}
}
// atomic write: temp file + rename, so the file is always either the old or the new version
$tmp = $file . '.tmp';
$json = json_encode($data);
if (file_put_contents($tmp, $json) !== strlen($json)) {
    @unlink($tmp);
    fail_unlock($lock, 500);
}
if (is_array(json_decode($raw, true))) {
    @copy($file, $file . '.bak');   // keep the previous good version
}
if (!rename($tmp, $file)) {
    @unlink($tmp);
    fail_unlock($lock, 500);
}
flock($lock, LOCK_UN);
fclose($lock);

echo '{"ok":true}';
