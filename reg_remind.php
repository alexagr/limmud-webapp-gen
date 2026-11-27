<?php
// E-mail reminders 15 minutes before sessions with registration (see reg_send_reminders in reg_lib.php).
// Optional: the app's own requests already trigger them. For cron (every 5 minutes):
//   php /home/limmudf1/public_html/webapp/reg_remind.php
require __DIR__ . '/reg_lib.php';
$config = reg_config();
$app = isset($config['app_name']) ? $config['app_name'] : '';
if (reg_valid_app($app)) {
    echo reg_send_reminders($app, $config) . " reminder(s) sent\n";
}
