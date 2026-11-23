<?php
if (file_exists('lock.txt')) {
    exit('ERROR: Configuration is locked');
}
if (!empty($_POST['sheet_id']) && !empty($_POST['app_name'])) {
    $config = array(
        'sheet_id' => $_POST['sheet_id'],
        'app_name' => $_POST['app_name']
    );
    file_put_contents('data/config.json', json_encode($config));
    print 'Configuration saved';
} else {
    print 'ERROR: Invalid configuration';
}
?>