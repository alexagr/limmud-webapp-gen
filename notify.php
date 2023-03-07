<?php
$msg = '';
$msg_he = '';
$not_before = date('2020-12-23 18:00');
$not_after = date('2020-12-23 22:00');
if (!empty($_POST['msg'])) {
    $msg = $_POST['msg'];
}
if (!empty($_POST['msg_he'])) {
    $msg_he = $_POST['msg_he'];
}
if (!empty($_POST['not_before'])) {
    $not_after = $_POST['not_before'];
}
if (!empty($_POST['not_after'])) {
    $not_after = $_POST['not_after'];
}

$config = json_decode(file_get_contents('data/config.json'), true);
if (!empty($config['app_name'])) {
    $notify = array(
        'timestamp' => date('Y-m-d H:i:s'),
        'msg' => $msg,
        'msg_he' => $msg_he,
        'not_before' => $not_before,
        'not_after' => $not_after
    );
    @mkdir($config['app_name'] . '/data');
    file_put_contents($config['app_name'] . '/data/notify.json', json_encode($notify));
    print 'Notification saved';
} else {
    print 'App Name is empty';
}
?>