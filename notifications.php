<?php
// In-app message (popup in the web app, assets/js/notify.js) - for the admin and the HelpDesk user.
// Saved by notify.php into <app>/data/notify.json.
$notify = array('msg' => '', 'msg_he' => '', 'timestamp' => '', 'not_before' => '', 'not_after' => '');
$config = json_decode(@file_get_contents('data/config.json'), true);
if (!empty($config['app_name'])) {
    $notifyPath = $config['app_name'] . '/data/notify.json';
    if (file_exists($notifyPath)) {
        $notify = array_merge($notify, (array)json_decode(file_get_contents($notifyPath), true));
    }
}
// stored as ISO with offset; the form shows local Israel time for <input type="datetime-local">
function notify_local($v)
{
    if (empty($v)) return '';
    $d = new DateTime($v);
    $d->setTimezone(new DateTimeZone('Asia/Jerusalem'));
    return $d->format('Y-m-d\TH:i');
}
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Notification - Limmud FSU</title>
  <style>
    body { margin: 0; font: 15px/1.4 -apple-system, "Segoe UI", Roboto, Arial, sans-serif; color: #1d1d1f; }
    .wrap { max-width: 640px; margin: 0 auto; padding: 16px; }
    h1 { font-size: 22px; margin: 0 0 12px; }
    .box { border-top: #F0F0F0 2px solid; background: #FAF8F8; padding: 12px; }
    label { display: block; font-weight: 600; margin: 12px 0 4px; }
    input[type=text], input[type=datetime-local] { font: inherit; padding: 7px 9px; border: 1px solid #cfcfd6; border-radius: 6px; }
    input[type=text] { width: 100%; box-sizing: border-box; }
    small { color: #6e6e73; }
    .btn { font: inherit; padding: 9px 22px; border: 0; border-radius: 6px; background: #2FC332; color: #fff; cursor: pointer; margin: 14px 8px 0 0; }
    .mini { font: inherit; padding: 5px 10px; border: 1px solid #cfcfd6; border-radius: 6px; background: #fff; cursor: pointer; }
    .error { background: #FF6600; color: #fff; padding: 6px 10px; border-radius: 4px; }
    .success { background: #12CC1A; color: #fff; padding: 6px 10px; border-radius: 4px; }
    .back { font-size: 14px; }
  </style>
</head>
<body>
<div class="wrap">
  <p class="back"><a href="javascript:history.back()">&larr; Back</a></p>
  <h1>Notification</h1>
  <div class="box">
    <div id="notify-status"></div>
    <label for="notify-msg">Message: <span style="color:#b3261e">*</span> <small style="font-weight:400">(Russian, Ukrainian, English pages)</small></label>
    <input type="text" id="notify-msg" value="<?php echo h($notify['msg']) ?>">
    <label for="notify-msg-he">Message (he): <span style="color:#b3261e">*</span> <small style="font-weight:400">(Hebrew pages) &mdash; at least one of the two</small></label>
    <input type="text" dir="rtl" id="notify-msg-he" value="<?php echo h($notify['msg_he']) ?>">
    <label for="notify-not-before">Not before:</label>
    <input type="datetime-local" id="notify-not-before" value="<?php echo notify_local($notify['not_before']) ?>">
    <button type="button" class="mini" onclick="notifyNow('notify-not-before')">Now</button>
    <button type="button" class="mini" title="Clear (no limit)" onclick="document.getElementById('notify-not-before').value=''">&times;</button>
    <small>Israel time; empty = no limit</small>
    <label for="notify-not-after">Not after: <span style="color:#b3261e">*</span></label>
    <input type="datetime-local" id="notify-not-after" value="<?php echo notify_local($notify['not_after']) ?>">
    <button type="button" class="mini" onclick="notifyIn(15)">15 min</button>
    <button type="button" class="mini" onclick="notifyIn(30)">30 min</button>
    <button type="button" class="mini" onclick="notifyIn(60)">1 hour</button>
    <button type="button" class="mini" title="Clear (no limit)" onclick="document.getElementById('notify-not-after').value=''">&times;</button>
    <small>Israel time; required</small>
    <div>
      <button class="btn" onclick="updateNotify(false)">Update</button>
      <button class="btn" onclick="updateNotify(true)">Clear message</button>
    </div>
    <p><small>Shown once per device as a popup while the app is open (checked every 3 minutes and when the app is reopened).
    Saving again (Update) shows the message again on every device.</small></p>
  </div>
</div>
<script>
  // this browser belongs to the team: the app's usage stats ignore it (assets/js/stats_report.js)
  try { localStorage.setItem('stats-team', '1'); } catch (e) {}

  // Israel time of a moment, in the format of <input type="datetime-local">
  function israel(date) {
    var p = {};
    new Intl.DateTimeFormat('en-GB', {timeZone: 'Asia/Jerusalem', year: 'numeric', month: '2-digit', day: '2-digit',
      hour: '2-digit', minute: '2-digit', hourCycle: 'h23'}).formatToParts(date).forEach(function (x) { p[x.type] = x.value; });
    return p.year + '-' + p.month + '-' + p.day + 'T' + p.hour + ':' + p.minute;
  }
  function notifyNow(id) {
    document.getElementById(id).value = israel(new Date());
  }
  // "Not after" = "Not before" (or now, if empty / already past) + the given minutes
  function notifyIn(min) {
    var from = Date.now();
    var nb = document.getElementById('notify-not-before').value;
    if (nb) {
      // the field is Israel wall time: shift by the difference between that and this browser's reading of it
      var asLocal = new Date(nb).getTime();
      var drift = new Date(israel(new Date(asLocal))).getTime() - asLocal;
      from = Math.max(from, asLocal - drift);
    }
    document.getElementById('notify-not-after').value = israel(new Date(from + min * 60000));
  }

  function updateNotify(clear) {
    var st = document.getElementById('notify-status');
    st.innerHTML = '';
    var ids = ['notify-msg', 'notify-msg-he', 'notify-not-before', 'notify-not-after'];
    var v = ids.map(function (i) { return document.getElementById(i).value; });
    if (!clear && !v[0].trim() && !v[1].trim()) {
      st.innerHTML = '<p class="error">ERROR: write the message (Russian and/or Hebrew); to remove the current one use "Clear message"</p>';
      document.getElementById('notify-msg').focus();
      return;
    }
    if (!clear && (v[0].trim() || v[1].trim()) && !v[3]) {
      st.innerHTML = '<p class="error">ERROR: "Not after" is required - set when the message stops being shown</p>';
      document.getElementById('notify-not-after').focus();
      return;
    }
    var body = new URLSearchParams({msg: v[0], msg_he: v[1], not_before: v[2], not_after: v[3], clear: clear ? '1' : '0'});
    fetch('notify.php', {method: 'POST', body: body}).then(function (r) { return r.text(); }).then(function (data) {
      var p = document.createElement('p');
      p.className = data.indexOf('ERROR') >= 0 ? 'error' : 'success';
      p.textContent = data;
      st.appendChild(p);
      if (clear && p.className === 'success') ids.forEach(function (i) { document.getElementById(i).value = ''; });
    }).catch(function () {
      st.innerHTML = '<p class="error">ERROR: Cannot execute notify.php</p>';
    });
  }
</script>
</body>
</html>
