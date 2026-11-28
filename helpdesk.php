<?php
// Start page of the HelpDesk user (.htaccess: the HelpDesk login may open only this page, registrations.php,
// notifications.php and notify.php; everything else needs the admin login).
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>HelpDesk - Limmud FSU</title>
  <style>
    body { margin: 0; font: 16px/1.4 -apple-system, "Segoe UI", Roboto, Arial, sans-serif; color: #1d1d1f; background: #f6f6f8; }
    .wrap { max-width: 480px; margin: 0 auto; padding: 32px 16px; }
    h1 { font-size: 24px; margin: 0 0 20px; }
    a.big { display: block; padding: 18px 20px; margin: 0 0 12px; border-radius: 12px; background: #fff; border: 1px solid #e3e3e8;
            color: #1d1d1f; text-decoration: none; font-size: 18px; font-weight: 600; }
    a.big span { display: block; font-size: 14px; font-weight: 400; color: #6e6e73; margin-top: 2px; }
    a.big:hover { border-color: #2a5bd7; }
  </style>
</head>
<body>
<div class="wrap">
  <h1>Limmud FSU — HelpDesk</h1>
  <a class="big" href="registrations.php">Registrations<span>Who is registered, add / cancel registrations, waiting list</span></a>
  <a class="big" href="notifications.php">Notification<span>Popup message shown in the app</span></a>
</div>
<script>
  // this browser belongs to the team: the app's usage stats ignore it (assets/js/stats_report.js)
  try { localStorage.setItem('stats-team', '1'); } catch (e) {}
</script>
</body>
</html>
