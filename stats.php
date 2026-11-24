<?php
// Admin view of anonymous bookmark statistics (password-protected like every other *.php here).
// Counts, per session, how many devices currently have it starred (data/stats_<app>.json,
// written by stats_report.php). Session titles/times/rooms come from <app>/json/sessions.json.

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
  <title>Bookmark stats - <?php echo h($app) ?></title>
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
  </style>
</head>
<body>
  <h1>Bookmark stats &mdash; <?php echo h($app) ?></h1>
  <div class="summary">
    Devices that reported: <b><?php echo count($devices) ?></b>
    &nbsp;|&nbsp; with at least one ★: <b><?php echo $withStars ?></b>
    &nbsp;|&nbsp; stars in total: <b><?php echo array_sum($counts) ?></b>
    &nbsp;|&nbsp; last report: <b><?php echo $lastReport ? date('d.m.Y H:i', $lastReport) : '&mdash;' ?></b>
    <div class="muted">Counts devices (not people), anonymously. Click a column title to sort.</div>
  </div>
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
