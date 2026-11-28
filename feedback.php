<?php
// Reports sent from the web app ("Report a problem", feedback_report.php) - admin page, password protected.
// Reports: data/<app>/feedback.jsonl (append-only). Status and note per report: data/<app>/feedback_status.json.

require __DIR__ . '/reg_lib.php';
$config = reg_config();
$app = isset($_GET['app']) ? $_GET['app'] : (isset($config['app_name']) ? $config['app_name'] : '');
if (!reg_valid_app($app)) {
    exit('Unknown app');
}
$self = 'feedback.php?app=' . urlencode($app);
$reportsFile = reg_data_dir($app) . '/feedback.jsonl';
$statusFile = reg_data_dir($app) . '/feedback_status.json';
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$STATUS = array('new' => 'new', 'progress' => 'in progress', 'done' => 'done');
$TYPES = array('info' => 'Programme info', 'app' => 'App problem', 'registration' => 'Registration', 'other' => 'Other');

// status / note (only from this page: CSRF check like registrations.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '');
    $site = isset($_SERVER['HTTP_SEC_FETCH_SITE']) ? $_SERVER['HTTP_SEC_FETCH_SITE'] : '';
    if (parse_url($origin, PHP_URL_HOST) !== $_SERVER['HTTP_HOST'] || ($site !== '' && $site !== 'same-origin')) {
        http_response_code(403);
        exit('Forbidden');
    }
    $id = isset($_POST['id']) ? preg_replace('/[^0-9a-f]/', '', $_POST['id']) : '';
    $f = fopen($statusFile, 'c+');
    flock($f, LOCK_EX);
    $all = json_decode(stream_get_contents($f), true);
    if (!is_array($all)) {
        $all = array();
    }
    if ($id !== '') {
        $all[$id] = array(
            'status' => isset($_POST['status']) && isset($STATUS[$_POST['status']]) ? $_POST['status'] : 'new',
            'note' => mb_substr(isset($_POST['note']) ? trim($_POST['note']) : '', 0, 1000, 'UTF-8'),
            't' => time(),
        );
    }
    ftruncate($f, 0);
    rewind($f);
    fwrite($f, json_encode($all, JSON_UNESCAPED_UNICODE));
    flock($f, LOCK_UN);
    fclose($f);
    header('Location: ' . $self . (isset($_POST['filter']) ? '&show=' . urlencode($_POST['filter']) : '') . '#r' . $id);
    exit;
}

$reports = array();
if (file_exists($reportsFile)) {
    foreach (file($reportsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $r = json_decode($line, true);
        if (isset($r['id'])) {
            $reports[] = $r;
        }
    }
}
$reports = array_reverse($reports);   // newest first
$states = json_decode(@file_get_contents($statusFile), true);
if (!is_array($states)) {
    $states = array();
}
$st = function ($r) use ($states) { return isset($states[$r['id']]) ? $states[$r['id']]['status'] : 'new'; };

if (isset($_GET['export'])) {
    $csv = function ($v) { $v = (string)$v; return preg_match('/\A[=+\-@\t\r]/', $v) ? "'" . $v : $v; };
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="feedback-' . $app . '-' . date('Ymd-Hi') . '.csv"');
    echo "\xEF\xBB\xBF";
    $f = fopen('php://output', 'w');
    fputcsv($f, array('Number', 'Time', 'Type', 'Status', 'Note', 'Text', 'E-mail', 'Phone', 'Language', 'Page', 'Session', 'Platform', 'Browser', 'App / browser', 'Version'));
    foreach ($reports as $r) {
        $i = isset($r['info']) ? $r['info'] : array();
        fputcsv($f, array_map($csv, array($r['id'], date('Y-m-d H:i', $r['t']), $TYPES[$r['type']], $STATUS[$st($r)],
            isset($states[$r['id']]) ? $states[$r['id']]['note'] : '', $r['text'], $r['email'], $r['phone'], $r['lang'],
            $r['page'], $r['session'], isset($i['os']) ? $i['os'] : '', isset($i['br']) ? $i['br'] : '',
            isset($i['mode']) ? $i['mode'] : '', $r['version'])));
    }
    exit;
}

$show = isset($_GET['show']) && ($_GET['show'] === 'all' || isset($STATUS[$_GET['show']])) ? $_GET['show'] : 'open';
$counts = array('new' => 0, 'progress' => 0, 'done' => 0);
foreach ($reports as $r) {
    $counts[$st($r)]++;
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Feedback - <?php echo h($app) ?></title>
<style>
  body { margin: 0; font: 15px/1.4 -apple-system, "Segoe UI", Roboto, Arial, sans-serif; color: #1d1d1f; background: #fff; }
  .wrap { max-width: 1000px; margin: 0 auto; padding: 16px; }
  h1 { font-size: 22px; margin: 0 0 12px; }
  a { color: #2a5bd7; }
  .muted { color: #6e6e73; font-size: 13px; }
  .filters a { margin-right: 12px; }
  .filters a.on { font-weight: bold; color: #1d1d1f; text-decoration: none; }
  .report { border: 1px solid #e3e3e8; border-radius: 10px; padding: 12px 14px; margin: 12px 0; }
  .report.st-new { border-left: 4px solid #b3261e; }
  .report.st-progress { border-left: 4px solid #a15c00; }
  .report.st-done { opacity: 0.7; }
  .head { display: flex; flex-wrap: wrap; gap: 8px; align-items: baseline; justify-content: space-between; }
  .type { font-weight: 600; }
  .text { white-space: pre-wrap; margin: 8px 0; font-size: 16px; }
  .meta { font-size: 13px; color: #555; }
  .meta span { margin-right: 14px; display: inline-block; }
  form { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; align-items: center; }
  input[type=text], select { font: inherit; padding: 6px 8px; border: 1px solid #cfcfd6; border-radius: 6px; }
  input[type=text] { flex: 1 1 240px; }
  button { font: inherit; padding: 6px 12px; border-radius: 6px; border: 1px solid #2a5bd7; background: #2a5bd7; color: #fff; cursor: pointer; }
</style>
</head>
<body>
<div class="wrap">
<h1>Feedback <span class="muted"><?php echo h($app) ?> · <?php echo count($reports) ?> reports</span></h1>
<p class="filters">
  <?php foreach (array('open' => 'Open (' . ($counts['new'] + $counts['progress']) . ')', 'new' => 'New (' . $counts['new'] . ')',
                       'progress' => 'In progress (' . $counts['progress'] . ')', 'done' => 'Done (' . $counts['done'] . ')', 'all' => 'All') as $k => $label): ?>
    <a class="<?php echo $show === $k ? 'on' : '' ?>" href="<?php echo h($self . '&show=' . $k) ?>"><?php echo h($label) ?></a>
  <?php endforeach; ?>
  · <a href="<?php echo h($self . '&export=1') ?>">Export CSV</a>
</p>
<?php $n = 0; foreach ($reports as $r):
    $s = $st($r);
    if ($show === 'open' ? $s === 'done' : ($show !== 'all' && $s !== $show)) continue;
    $n++;
    $i = isset($r['info']) ? $r['info'] : array();
    $note = isset($states[$r['id']]) ? $states[$r['id']]['note'] : '';
?>
  <div class="report st-<?php echo $s ?>" id="r<?php echo h($r['id']) ?>">
    <div class="head">
      <span class="type"><?php echo h($TYPES[$r['type']]) ?></span>
      <span class="muted">#<?php echo h($r['id']) ?> · <?php echo h(date('d.m.Y H:i', $r['t'])) ?></span>
    </div>
    <div class="text" dir="auto"><?php echo h($r['text']) ?></div>
    <div class="meta">
      <span>E-mail: <?php echo $r['email'] !== '' ? '<a href="mailto:' . h($r['email']) . '">' . h($r['email']) . '</a>' : '-' ?></span>
      <span>Phone: <?php echo $r['phone'] !== '' ? '<a href="tel:' . h(preg_replace('/[^\d+]/', '', $r['phone'])) . '">' . h($r['phone']) . '</a>' : '-' ?></span>
      <span>Language: <?php echo h(strtoupper($r['lang'])) ?></span>
      <span>Device: <?php echo h(trim((isset($i['os']) ? $i['os'] : '') . ' ' . (isset($i['br']) ? $i['br'] : '') . ' ' . (isset($i['mode']) ? $i['mode'] : ''))) ?: '-' ?></span>
      <span>Version: <?php echo h($r['version'] !== '' ? $r['version'] : '-') ?></span>
      <span>Page: <?php echo h($r['page'] !== '' ? $r['page'] : '-') ?></span>
      <?php if ($r['session'] !== ''): ?><span>Session: <?php echo h($r['session']) ?></span><?php endif; ?>
    </div>
    <form method="post">
      <input type="hidden" name="id" value="<?php echo h($r['id']) ?>">
      <input type="hidden" name="filter" value="<?php echo h($show) ?>">
      <select name="status"><?php foreach ($STATUS as $k => $label): ?><option value="<?php echo $k ?>"<?php echo $k === $s ? ' selected' : '' ?>><?php echo h($label) ?></option><?php endforeach; ?></select>
      <input type="text" name="note" value="<?php echo h($note) ?>" placeholder="Note (only here)">
      <button>Save</button>
    </form>
  </div>
<?php endforeach; ?>
<?php if (!$n): ?><p class="muted">No reports here.</p><?php endif; ?>
</div>
</body>
</html>
