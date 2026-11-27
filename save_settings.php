<?php
if (file_exists('lock.txt')) {
    exit('ERROR: Configuration is locked');
}
if (!empty($_POST['sheet_id']) && !empty($_POST['app_name'])) {
    $config = file_exists('data/config.json') ? json_decode(file_get_contents('data/config.json'), true) : array();
    if (!is_array($config)) {
        $config = array();
    }
    $config['sheet_id'] = $_POST['sheet_id'];
    $config['app_name'] = $_POST['app_name'];
    // event time zone (session and registration times in the sheet are local) and the sender of registration e-mails
    foreach (array('timezone', 'mail_from', 'mail_reply_to') as $k) {
        if (isset($_POST[$k])) {
            $config[$k] = trim($_POST[$k]);
        }
    }
    unset($config['mail_from_name']);   // not used: the sender name is per language (reg_lib.php)
    if (!empty($config['timezone']) && !in_array($config['timezone'], timezone_identifiers_list())) {
        exit('ERROR: Unknown time zone ' . htmlspecialchars($config['timezone']));
    }
    file_put_contents('data/config.json', json_encode($config));
    print 'Configuration saved';
} else {
    print 'ERROR: Invalid configuration';
}
?>