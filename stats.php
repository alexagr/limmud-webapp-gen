<?php
// Admin view of anonymous usage statistics (bookmarks, devices, last seen) (password-protected like every other *.php here).
// Counts, per session, how many devices currently have it starred (data/stats_<app>.json,
// written by stats_report.php). Session titles/times/rooms come from <app>/json/sessions.json.

// new devices are grouped by day in Israel time (the server clock is UTC)
date_default_timezone_set('Asia/Jerusalem');
$config = json_decode(file_get_contents('data/config.json'), true);
$app = $config['app_name'];

$devices = array();
$statsFile = 'data/stats_' . $app . '.json';
if (file_exists($statsFile)) {
    $devices = json_decode(file_get_contents($statsFile), true);
    if (!is_array($devices)) {
        $devices = array();
    }
}

$sessions = array();
$sessionsFile = $app . '/json/sessions.json';
if (file_exists($sessionsFile)) {
    foreach (json_decode(file_get_contents($sessionsFile), true) as $s) {
        $sessions[$s['id']] = $s;
    }
}

$counts = array();
$withStars = 0;
// device profiles (sent since Nov 2026; older reports have none until the device reports again)
$profile = array('os' => array(), 'mode' => array(), 'br' => array(), 'lang' => array());
$profiled = 0;
$firstSeen = array();   // new devices per day
foreach ($devices as $d) {
    if (!empty($d['i'])) {
        $profiled++;
        foreach ($profile as $k => $v) {
            $val = isset($d['i'][$k]) ? $d['i'][$k] : '?';
            $profile[$k][$val] = (isset($profile[$k][$val]) ? $profile[$k][$val] : 0) + 1;
        }
    }
    $day = date('Y-m-d', isset($d['f']) ? $d['f'] : (isset($d['t']) ? $d['t'] : 0));
    $firstSeen[$day] = (isset($firstSeen[$day]) ? $firstSeen[$day] : 0) + 1;
}
foreach ($profile as $k => $v) {
    arsort($profile[$k]);
}
ksort($firstSeen);
// active devices: last report (t) - the app reports at least every 6 hours while it is used
$now = time();
$active = array('last hour' => 3600, 'last 24 hours' => 86400, 'last 7 days' => 7 * 86400);
foreach ($active as $label => $sec) {
    $n = 0;
    foreach ($devices as $d) {
        if (isset($d['t']) && $now - $d['t'] <= $sec) $n++;
    }
    $active[$label] = $n;
}
$labels = array(
    'os' => array('android' => 'Android', 'iphone' => 'iPhone', 'ipad' => 'iPad', 'windows' => 'Windows', 'mac' => 'Mac', 'linux' => 'Linux', 'other' => 'other'),
    'mode' => array('app' => 'installed app', 'browser' => 'browser only'),
    'br' => array('chrome' => 'Chrome', 'safari' => 'Safari', 'samsung' => 'Samsung', 'firefox' => 'Firefox', 'edge' => 'Edge', 'yandex' => 'Yandex', 'opera' => 'Opera', 'other' => 'other'),
    'lang' => array('ru' => 'RU', 'he' => 'HE', 'en' => 'EN', 'ukr' => 'UKR'),
);
$titles = array('os' => 'Platform', 'mode' => 'App', 'br' => 'Browser', 'lang' => 'Language');
$lastReport = 0;
foreach ($devices as $d) {
    if (!empty($d['s'])) {
        $withStars++;
    }
    $lastReport = max($lastReport, isset($d['t']) ? $d['t'] : 0);
    foreach ($d['s'] as $id) {
        $counts[$id] = (isset($counts[$id]) ? $counts[$id] : 0) + 1;
    }
}

$rows = array();
foreach ($sessions as $id => $s) {
    $rows[] = array(
        'count' => isset($counts[$id]) ? $counts[$id] : 0,
        'start' => $s['start_time'],
        'title' => $s['title'],
        'room' => isset($s['location']['name']) ? $s['location']['name'] : '',
        'speakers' => !empty($s['alt_presenter_summary']) ? $s['alt_presenter_summary']
            : implode(', ', array_map(function ($p) { return is_array($p) ? (isset($p['name_first_last']) ? $p['name_first_last'] : (isset($p['name']) ? $p['name'] : '')) : $p; }, isset($s['speakers']) ? $s['speakers'] : array())),
    );
}
usort($rows, function ($a, $b) { return $b['count'] - $a['count'] ?: strcmp($a['start'], $b['start']); });

$days = array('Mon' => 'пн', 'Tue' => 'вт', 'Wed' => 'ср', 'Thu' => 'чт', 'Fri' => 'пт', 'Sat' => 'сб', 'Sun' => 'вс');
function h($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Usage stats - <?php echo h($app) ?></title>
  <style>
    body { font-family: Arial, sans-serif; margin: 16px; color: #222; }
    .summary { background: #FAF8F8; border-top: #F0F0F0 2px solid; padding: 10px; margin-bottom: 14px; }
    table { border-collapse: collapse; width: 100%; max-width: 1100px; }
    th, td { border-bottom: 1px solid #eee; padding: 6px 8px; text-align: left; vertical-align: top; font-size: 14px; }
    th { background: #f4f4f4; cursor: pointer; user-select: none; white-space: nowrap; }
    td.num, th.num { text-align: right; white-space: nowrap; }
    td:nth-child(2) { white-space: nowrap; }
    .bar { display: inline-block; height: 10px; background: #2FC332; border-radius: 2px; vertical-align: middle; margin-right: 6px; }
    .muted { color: #888; }
    .tiles { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 14px; }
    .tile { background: #FAF8F8; border-top: #F0F0F0 2px solid; padding: 10px 16px; min-width: 150px; }
    .tile-num { font-size: 28px; font-weight: bold; }
    .tile-label { font-size: 13px; color: #666; }
    .charts { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 14px; max-width: 1100px; }
    .chart { background: #FAF8F8; border-top: #F0F0F0 2px solid; padding: 10px; }
    .chart-wide { grid-column: 1 / -1; }
    .chart h3 { margin: 0 0 8px; font-size: 14px; }
    .hrow { display: flex; align-items: center; gap: 8px; margin: 4px 0; font-size: 13px; cursor: default; }
    .hrow:hover { background: #f0eeee; }
    .hlabel { flex: 0 0 90px; }
    .htrack { flex: 1; height: 12px; }
    .hbar { display: block; height: 12px; background: #2FC332; border-radius: 0 4px 4px 0; }
    .hval { flex: 0 0 64px; text-align: right; white-space: nowrap; }
    .cols { display: flex; align-items: flex-end; gap: 2px; overflow-x: auto; padding-top: 4px; }
    .col { display: flex; flex-direction: column; align-items: center; justify-content: flex-end; min-width: 34px; font-size: 12px; cursor: default; }
    .col:hover .cbar { background: #25a028; }
    .cbar { display: block; width: 22px; background: #2FC332; border-radius: 4px 4px 0 0; }
    .cval { margin-bottom: 2px; }
    .clabel { color: #666; margin-top: 3px; border-top: 1px solid #ccc; padding-top: 2px; width: 100%; text-align: center; }
    .note { margin: 8px 0 14px; font-size: 13px; }
  </style>
</head>
<body>
  <h1>Usage stats &mdash; <?php echo h($app) ?></h1>
  <div class="summary">
    Devices that reported: <b><?php echo count($devices) ?></b>
    &nbsp;|&nbsp; with at least one ★: <b><?php echo $withStars ?></b>
    &nbsp;|&nbsp; stars in total: <b><?php echo array_sum($counts) ?></b>
    &nbsp;|&nbsp; last report: <b><?php echo $lastReport ? '<span class="local-time" data-t="' . $lastReport . '">' . gmdate('d.m.Y H:i', $lastReport) . ' UTC</span>' : '&mdash;' ?></b>
    <div class="muted">Counts devices (not people), anonymously. Click a column title to sort.</div>
  </div>
  <!-- charts: one hue (magnitude), the number and % written next to every bar; hover a bar for details -->
  <div class="tiles">
    <?php foreach ($active as $label => $n): ?>
      <div class="tile"><div class="tile-num"><?php echo $n ?></div><div class="tile-label">active devices, <?php echo h($label) ?></div></div>
    <?php endforeach; ?>
  </div>
  <div class="charts">
    <?php foreach ($profile as $k => $vals): $top = max(1, $vals ? max($vals) : 1); ?>
      <div class="chart">
        <h3><?php echo $titles[$k] ?></h3>
        <?php if (!$profiled): ?><div class="muted">no data yet</div><?php endif; ?>
        <?php foreach ($vals as $v => $n): $label = isset($labels[$k][$v]) ? $labels[$k][$v] : $v; $pct = round(100 * $n / $profiled); ?>
          <div class="hrow" title="<?php echo h($label) . ': ' . $n . ' devices (' . $pct . '%)' ?>">
            <span class="hlabel"><?php echo h($label) ?></span>
            <span class="htrack"><span class="hbar" style="width:<?php echo max(1, round(100 * $n / $top)) ?>%"></span></span>
            <span class="hval"><b><?php echo $n ?></b> <span class="muted"><?php echo $pct ?>%</span></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
    <div class="chart chart-wide">
      <h3>New devices by day</h3>
      <?php $top = max(1, $firstSeen ? max($firstSeen) : 1); ?>
      <div class="cols">
        <?php foreach ($firstSeen as $day => $n): ?>
          <div class="col" title="<?php echo date('d.m.Y', strtotime($day)) . ': ' . $n . ' new devices' ?>">
            <span class="cval"><?php echo $n ?></span>
            <span class="cbar" style="height:<?php echo max(2, round(100 * $n / $top)) ?>px"></span>
            <span class="clabel"><?php echo date('d.m', strtotime($day)) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <div class="muted note">&ldquo;Installed app&rdquo; = used the installed app at least once.</div>
  <?php $max = max(1, $counts ? max($counts) : 1); ?>
  <table id="stats">
    <thead><tr>
      <th class="num" data-type="num">★</th>
      <th data-type="str">When</th>
      <th data-type="str">Room</th>
      <th data-type="str">Session</th>
      <th data-type="str">Presenters</th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r):
      $t = strtotime($r['start']); ?>
      <tr>
        <td class="num" data-v="<?php echo $r['count'] ?>"><span class="bar" style="width:<?php echo round(60 * $r['count'] / $max) ?>px"></span><?php echo $r['count'] ?></td>
        <td data-v="<?php echo h($r['start']) ?>"><?php echo $days[date('D', $t)] . ' ' . date('H:i', $t) ?></td>
        <td><?php echo h($r['room']) ?></td>
        <td><?php echo h($r['title']) ?></td>
        <td class="muted"><?php echo h($r['speakers']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <script>
  // times are sent as UTC timestamps and shown in this browser's local time
  document.querySelectorAll('.local-time').forEach(function (el) {
    var d = new Date(Number(el.getAttribute('data-t')) * 1000);
    var p = function (n) { return (n < 10 ? '0' : '') + n; };
    el.textContent = p(d.getDate()) + '.' + p(d.getMonth() + 1) + '.' + d.getFullYear() + ' ' + p(d.getHours()) + ':' + p(d.getMinutes());
  });
  // click a column title to sort (click again to reverse)
  document.querySelectorAll('#stats th').forEach(function (th, col) {
    var asc = false;
    th.addEventListener('click', function () {
      asc = !asc;
      var tbody = document.querySelector('#stats tbody');
      var rows = Array.prototype.slice.call(tbody.rows);
      var num = th.getAttribute('data-type') === 'num';
      rows.sort(function (a, b) {
        var x = a.cells[col].getAttribute('data-v') || a.cells[col].textContent;
        var y = b.cells[col].getAttribute('data-v') || b.cells[col].textContent;
        var c = num ? (Number(x) - Number(y)) : x.localeCompare(y, 'ru');
        return asc ? c : -c;
      });
      rows.forEach(function (r) { tbody.appendChild(r); });
    });
  });
  </script>
</body>
</html>
