<?php
// Saves the in-app message shown by assets/js/notify.js (data/notify.json inside the app folder).
// Times come from the admin form as local Israel time ("2026-12-17T18:00", may be empty = no limit)
// and are stored as ISO 8601 with offset, which every browser (incl. Safari) parses.
date_default_timezone_set('Asia/Jerusalem');

function iso_time($v) {
    $v = trim((string)$v);
    if ($v === '') {
        return '';
    }
    $t = strtotime(str_replace('T', ' ', $v));
    return $t ? date('c', $t) : false;
}

$clear = !empty($_POST['clear']);
$msg = $clear ? '' : trim(isset($_POST['msg']) ? $_POST['msg'] : '');
$msg_he = $clear ? '' : trim(isset($_POST['msg_he']) ? $_POST['msg_he'] : '');
$not_before = $clear ? '' : iso_time(isset($_POST['not_before']) ? $_POST['not_before'] : '');
$not_after = $clear ? '' : iso_time(isset($_POST['not_after']) ? $_POST['not_after'] : '');
if ($not_before === false || $not_after === false) {
    exit('ERROR: cannot read the date/time');
}
// Update needs a message (removing it is "Clear message")
if (!$clear && $msg === '' && $msg_he === '') {
    exit('ERROR: the message is empty (to remove the current message use "Clear message")');
}
// a message always has an end: without "Not after" it would pop up on new devices for the rest of the event
if (!$clear && ($msg !== '' || $msg_he !== '') && $not_after === '') {
    exit('ERROR: "Not after" is required');
}
if (!$clear && $not_after !== '' && strtotime($not_after) <= time()) {
    exit('ERROR: "Not after" is already in the past');
}
if ($not_before !== '' && $not_after !== '' && strtotime($not_after) <= strtotime($not_before)) {
    exit('ERROR: "Not after" must be later than "Not before"');
}

$config = json_decode(file_get_contents('data/config.json'), true);
if (empty($config['app_name'])) {
    exit('ERROR: App Name is empty');
}
$notify = array(
    'timestamp' => date('c'),   // identifies this message: devices show each timestamp once
    'msg' => $msg,
    'msg_he' => $msg_he,
    'not_before' => $not_before,
    'not_after' => $not_after
);
@mkdir($config['app_name'] . '/data');
$file = $config['app_name'] . '/data/notify.json';
if (file_put_contents($file . '.tmp', json_encode($notify, JSON_UNESCAPED_UNICODE)) === false || !rename($file . '.tmp', $file)) {
    exit('ERROR: cannot save the message');
}
print $clear ? 'Message cleared' : ($msg === '' && $msg_he === '' ? 'Saved (empty: nothing is shown)' : 'Message saved');
