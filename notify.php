<?php
$msg = '';
$msg_he = '';
if (!empty($_POST['msg'])) {
    $msg = $_POST['msg'];
}
if (!empty($_POST['msg_he'])) {
    $msg_he = $_POST['msg_he'];
}

$config = json_decode(file_get_contents('data/config.json'), true);
if (!empty($config['app_name'])) {
    $notify = array(
        'timestamp' => idate('U'),
        'msg' => $msg,
        'msg_he' => $msg_he
    );
    file_put_contents($config['app_name'] . '/data/notify.json', json_encode($notify));
    print 'Notification saved';
} else {
    print 'App Name is empty';
}
?>