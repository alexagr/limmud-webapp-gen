<?php

function recursive_copy($src, $dst)
{
	$dir = opendir($src);
	@mkdir($dst);
	while ($file = readdir($dir)) {
		if (( $file != '.' ) && ( $file != '..' )) {
			if (is_dir($src . '/' . $file)) {
				recursive_copy($src . '/' . $file, $dst . '/' . $file);
			}
			else {
				copy($src . '/' . $file, $dst . '/' . $file);
			}
		}
	}
	closedir($dir);
}

$configPath = 'data/config.json';
if (file_exists($configPath)) {
    $config = json_decode(file_get_contents($configPath), true);
} else {
    exit('ERROR: cannot find config.json');
}

if (empty($config['app_name'])) {
    exit('ERROR: app_name is empty');
}

@mkdir(__DIR__ . '/' . $config['app_name']);
recursive_copy(__DIR__ . '/assets', __DIR__ . '/' . $config['app_name'] . '/assets');
if (!file_exists(__DIR__ . '/' . $config['app_name'] . '/data/notify.json')) {
	@mkdir(__DIR__ . '/' . $config['app_name'] . '/data');
	file_put_contents(__DIR__ . '/' . $config['app_name'] . '/data/notify.json', '{"timestamp": "2020-12-23 18:00:00", "msg": "", "msg_he": "", "not_before": "2022-12-23 18:00", "not_after": "2022-12-23 22:00"}');
}
print 'Assets sucessfuly copied';
