<?php
// HelpDesk: registrations for sessions with limited seats (see reg_lib.php).
// Protected by the same password as admin.php (.htaccess: every *.php except the public endpoints).
// Shows every session with registration and who is registered / on the waiting list; adds registrations
// for people whose phone doesn't cooperate, cancels invalid ones, moves people from the waiting list.

require __DIR__ . '/reg_lib.php';
$config = reg_config();
$app = isset($_GET['app']) ? $_GET['app'] : (isset($config['app_name']) ? $config['app_name'] : '');
if (!reg_valid_app($app)) {
    exit('Unknown app');
}
$config['_app'] = $app;
$sessions = reg_sessions($app);
uasort($sessions, function ($a, $b) { return $a['start'] - $b['start'] ?: strcmp($a['title'], $b['title']); });
$self = 'registrations.php?app=' . urlencode($app);
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
// CSV cells: a value starting with = + - @ would run as a formula in Excel
function csv_safe($row)
{
    return array_map(function ($v) { $v = (string)$v; return preg_match('/\A[=+\-@\t\r]/', $v) ? "'" . $v : $v; }, $row);
}

// ---- actions (POST, then back to the page) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '');
    $site = isset($_SERVER['HTTP_SEC_FETCH_SITE']) ? $_SERVER['HTTP_SEC_FETCH_SITE'] : '';
    if (parse_url($origin, PHP_URL_HOST) !== $_SERVER['HTTP_HOST'] || ($site !== '' && $site !== 'same-origin')) {
        http_response_code(403);
        exit('Forbidden: this action can only be sent from the Registrations page');
    }
    $do = isset($_POST['do']) ? $_POST['do'] : '';
    $sid = isset($_POST['session']) ? (int)$_POST['session'] : 0;
    $notify = !empty($_POST['notify']);
    $msg = '';
    $mails = array();
    $lock = reg_lock($app);
    $data = $lock ? reg_load($app) : null;
    if ($data === null) {
        reg_unlock($lock);
        exit('ERROR: cannot read the registrations file');
    }
    if ($do === 'add') {
        $first = isset($_POST['first']) ? trim($_POST['first']) : '';
        $last = isset($_POST['last']) ? trim($_POST['last']) : '';
        $name = reg_full_name($first, $last);
        $phone = isset($_POST['phone']) ? $_POST['phone'] : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $status = isset($_POST['status']) && in_array($_POST['status'], array('confirmed', 'waiting')) ? $_POST['status'] : '';
        $override = !empty($_POST['override']);
        $bad = reg_validate_names($first, $last) ?: reg_validate_fields($name, $phone, $email === '' ? 'nobody@example.com' : $email);   // e-mail optional here
        if ($bad) {
            $msg = 'err:' . array('first' => 'Укажите имя', 'last' => 'Укажите фамилию', 'name' => 'Проверьте имя',
                                  'phone' => 'Проверьте телефон', 'email' => 'Проверьте e-mail')[$bad];
        } else {
            $lang = isset($_POST['lang']) && in_array($_POST['lang'], array('ru', 'ukr', 'en', 'he')) ? $_POST['lang'] : 'ru';
            foreach (reg_fill($data, $sessions, $sid) as $p) {   // free seats go to the waiting list first
                $mails[] = array($p, 'promoted');
            }
            $reg = reg_add($data, $sessions, $sid, array('name' => $name, 'first' => $first, 'last' => $last, 'phone' => $phone, 'email' => $email,
                                                       'lang' => $lang, 'by' => 'helpdesk'), $status, $override);
            if (isset($reg['id'])) {
                $msg = 'ok:' . $reg['name'] . ' - ' . ($reg['status'] === 'confirmed' ? 'записан(а)' : 'в листе ожидания');
                if ($notify) {
                    $mails[] = array($reg, $reg['status']);
                }
            } else {
                $why = array(
                    'already' => 'этот человек уже записан на эту сессию',
                    'overlap' => 'пересекается с другой записью: «' . $reg[1] . '»',
                    'category' => 'превышен лимит категории «' . explode('|', $reg[1])[0] . '» (' . (explode('|', $reg[1] . '|')[1]) . ')',
                    'full' => 'мест нет, и лист ожидания заполнен',
                    'contact' => 'на этот e-mail или телефон уже ' . $reg[1] . ' записей',
                    'session' => 'нет такой сессии');
                $msg = 'err:Не записан(а): ' . (isset($why[$reg[0]]) ? $why[$reg[0]] : $reg[0])
                     . ($reg[0] !== 'already' && $reg[0] !== 'session' ? '. Можно отметить «игнорировать ограничения».' : '');
            }
        }
    } elseif ($do === 'cancel' || $do === 'confirm') {
        $id = isset($_POST['id']) ? (string)(int)$_POST['id'] : '';
        if (isset($data['regs'][$id]) && reg_active($data['regs'][$id])
            && ($do === 'cancel' || $data['regs'][$id]['status'] === 'waiting')) {
            $sid = $data['regs'][$id]['session'];
            if ($do === 'cancel') {
                foreach (reg_cancel($data, $sessions, $id, 'helpdesk') as $p) {
                    $mails[] = array($p, 'promoted');
                }
                if ($notify) {
                    $mails[] = array($data['regs'][$id], 'cancelled');
                }
                $msg = 'ok:Запись отменена: ' . $data['regs'][$id]['name'];
            } else {   // waiting list -> confirmed, even above the number of seats
                $data['regs'][$id]['status'] = 'confirmed';
                $data['regs'][$id]['promoted'] = time();
                $data['regs'][$id]['promoted_seen'] = false;
                $data['regs'][$id]['override'] = 'helpdesk';
                if ($notify) {
                    $mails[] = array($data['regs'][$id], 'promoted');
                }
                $msg = 'ok:Подтверждено: ' . $data['regs'][$id]['name'];
            }
        }
    }
    if ($do === 'cancel_many') {
        $n = 0;
        foreach (isset($_POST['ids']) && is_array($_POST['ids']) ? $_POST['ids'] : array() as $id) {
            $id = (string)(int)$id;
            if (isset($data['regs'][$id]) && reg_active($data['regs'][$id])) {
                foreach (reg_cancel($data, $sessions, $id, 'helpdesk') as $p) {
                    $mails[] = array($p, 'promoted');
                }
                $n++;
            }
        }
        $msg = 'ok:Отменено записей: ' . $n;
    }
    if ($msg === '' || strpos($msg, 'ok:') === 0) {
        if (!reg_save($app, $data)) {
            $msg = 'err:Ошибка записи файла';
        }
    }
    reg_unlock($lock);
    foreach ($mails as $m) {
        reg_mail($config, $data, $sessions, $m[0], $m[1]);
    }
    $back = $do === 'cancel_many' && isset($_POST['q']) ? '&q=' . urlencode($_POST['q']) : '';
    header('Location: ' . $self . ($sid ? '&session=' . $sid : '') . $back . '&msg=' . urlencode($msg) . ($sid ? '#s' : ''));
    exit;
}

$data = reg_load($app);
if ($data === null) {
    exit('ERROR: cannot read the registrations file');
}
$sel = isset($_GET['session']) ? (int)$_GET['session'] : 0;
$q = isset($_GET['q']) ? trim($_GET['q']) : '';

// full copy for safekeeping: everything as JSON (can be put back as data/<app>/registrations.json) or as one CSV;
// ?download=log - the same rebuilt from the change log (if the main file and its copies were ever lost)
if (isset($_GET['download'])) {
    $what = $_GET['download'];
    $stamp = date('Ymd-Hi');
    if ($what === 'json' || $what === 'log') {
        $out = $what === 'log' ? reg_from_log($app) : $data;
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="registrations_' . $app . ($what === 'log' ? '-from-log' : '') . '-' . $stamp . '.json"');
        echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="registrations_' . $app . '-' . $stamp . '.csv"');
    echo "\xEF\xBB\xBF";
    $f = fopen('php://output', 'w');
    fputcsv($f, array('id', 'Сессия', 'Начало', 'Статус', 'Имя', 'Телефон', 'E-mail', 'Записан', 'Кто', 'Отменён', 'Язык'));
    foreach ($data['regs'] as $r) {
        $s = isset($sessions[$r['session']]) ? $sessions[$r['session']] : null;
        fputcsv($f, csv_safe(array($r['id'], $s ? $s['title'] : (isset($r['session_title']) ? $r['session_title'] : $r['session']),
                          $s ? date('d.m H:i', $s['start']) : '', $r['status'], $r['name'], $r['phone'], $r['email'],
                          date('d.m H:i', $r['created']), $r['by'], !empty($r['cancelled']) ? date('d.m H:i', $r['cancelled']) : '',
                          $r['lang'])));
    }
    exit;
}

// registrations whose session changed in the sheet (other title or time under the same id)
$changed = array();
foreach ($data['regs'] as $r) {
    if (reg_active($r) && reg_session_changed($r, $sessions) !== '') {
        $changed[] = $r;
    }
}

// CSV of one session (list for the door)
if (isset($_GET['csv']) && isset($sessions[$sel])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="registrations-' . $sel . '.csv"');
    echo "\xEF\xBB\xBF";
    $f = fopen('php://output', 'w');
    fputcsv($f, array('#', 'Статус', 'Имя', 'Телефон', 'E-mail', 'Когда', 'Кто'));
    foreach (session_regs($data, $sel) as $r) {
        fputcsv($f, csv_safe(array($r['status'] === 'waiting' ? 'W' . reg_waiting_pos($data, $r) : '', $r['status'] === 'confirmed' ? 'записан' : 'ожидание',
                          $r['name'], $r['phone'], $r['email'], date('d.m H:i', $r['created']), $r['by'])));
    }
    exit;
}

function session_regs($data, $sid, $withCancelled = false)
{
    $out = array();
    foreach ($data['regs'] as $r) {
        if ($r['session'] == $sid && ($withCancelled || reg_active($r))) {
            $out[] = $r;
        }
    }
    usort($out, function ($a, $b) {
        $o = array('confirmed' => 0, 'waiting' => 1, 'cancelled' => 2);
        return $o[$a['status']] - $o[$b['status']] ?: $a['id'] - $b['id'];
    });
    return $out;
}

$days = array('вс', 'пн', 'вт', 'ср', 'чт', 'пт', 'сб');
function when($t) { global $days; return $days[(int)date('w', $t)] . ' ' . date('d.m H:i', $t); }
$now = time();
?><!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Registrations - <?php echo h($app) ?></title>
<style>
  :root { --ink: #1d1d1f; --muted: #6e6e73; --line: #e3e3e8; --bg: #f6f6f8; --accent: #2a5bd7; --ok: #1f7a3a; --warn: #a15c00; --bad: #b3261e; }
  * { box-sizing: border-box; }
  body { margin: 0; font: 15px/1.4 -apple-system, "Segoe UI", Roboto, Arial, sans-serif; color: var(--ink); background: #fff; }
  .wrap { max-width: 1100px; margin: 0 auto; padding: 16px; }
  h1 { font-size: 22px; margin: 0 0 12px; }
  h2 { font-size: 18px; margin: 24px 0 8px; }
  a { color: var(--accent); }
  .muted { color: var(--muted); font-size: 13px; }
  .msg { padding: 10px 12px; border-radius: 6px; margin: 0 0 12px; }
  .msg.ok { background: #e7f5ea; color: var(--ok); }
  .msg.err { background: #fdecea; color: var(--bad); }
  table { width: 100%; border-collapse: collapse; }
  th, td { text-align: left; padding: 8px 6px; border-bottom: 1px solid var(--line); vertical-align: top; }
  th { font-size: 12px; color: var(--muted); font-weight: 600; text-transform: uppercase; }
  tr.sel td { background: #eef3ff; }
  tr.cancelled td { color: var(--muted); text-decoration: line-through; }
  .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
  .pill { display: inline-block; padding: 1px 8px; border-radius: 10px; font-size: 12px; white-space: nowrap; }
  .p-open { background: #e7f5ea; color: var(--ok); }
  .p-waitlist { background: #fff4e0; color: var(--warn); }
  .p-full, .p-closed { background: #fdecea; color: var(--bad); }
  .p-soon { background: var(--bg); color: var(--muted); }
  form.inline { display: inline; margin: 0; }
  button, .btn { font: inherit; padding: 6px 12px; border-radius: 6px; border: 1px solid var(--line); background: #fff; cursor: pointer; color: var(--ink); text-decoration: none; display: inline-block; }
  button.primary { background: var(--accent); border-color: var(--accent); color: #fff; }
  button.danger { color: var(--bad); }
  button.danger.sure { background: var(--bad); border-color: var(--bad); color: #fff; }
  .add { background: var(--bg); padding: 12px; border-radius: 8px; margin: 12px 0; }
  .add .row { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 8px; }
  .add input[type=text], .add input[type=tel], .add input[type=email], .add select, .search input { font: inherit; padding: 7px 9px; border: 1px solid #cfcfd6; border-radius: 6px; min-width: 0; }
  .add input[type=text] { flex: 2 1 180px; }
  .add input[type=tel], .add input[type=email] { flex: 1 1 150px; }
  .search { display: flex; gap: 8px; margin: 8px 0 4px; }
  .search input { flex: 1; }
  .tag { font-size: 11px; color: var(--muted); border: 1px solid var(--line); border-radius: 4px; padding: 0 4px; margin-left: 4px; }
  .dup td { background: #fff8e1; }
  @media (max-width: 640px) { .hide-sm { display: none; } th, td { padding: 6px 4px; } }
</style>
</head>
<body>
<div class="wrap">
<h1>Registrations <span class="muted"><?php echo h($app) ?> · <?php echo h(date('d.m H:i', $now)) ?> (<?php echo h($config['timezone']) ?>)</span></h1>
<?php
if (isset($_GET['msg']) && $_GET['msg'] !== '') {
    $m = $_GET['msg'];
    $ok = strpos($m, 'ok:') === 0;
    echo '<div class="msg ' . ($ok ? 'ok' : 'err') . '">' . h(substr($m, $ok ? 3 : 4)) . '</div>';
}
?>
<form class="search" method="get">
  <input type="hidden" name="app" value="<?php echo h($app) ?>">
  <input type="search" name="q" value="<?php echo h($q) ?>" placeholder="Поиск человека: имя, телефон или e-mail - все его записи">
  <button>Найти</button>
</form>

<?php if ($q !== ''):
    $qn = mb_strtolower($q, 'UTF-8');
    $qp = reg_norm_phone($q);
    $found = array();
    foreach ($data['regs'] as $r) {
        if (!isset($sessions[$r['session']])) continue;
        if (mb_strpos(mb_strtolower($r['name'] . ' ' . $r['email'], 'UTF-8'), $qn) !== false
            || (strlen($qp) >= 4 && strpos(reg_norm_phone($r['phone']), $qp) !== false)) {
            $found[] = $r;
        }
    }
    usort($found, function ($a, $b) use ($sessions) { return $sessions[$a['session']]['start'] - $sessions[$b['session']]['start']; });
?>
<h2>Найдено: <?php echo count($found) ?> <a class="muted" href="<?php echo h($self) ?>">очистить</a></h2>
<?php $activeFound = array_filter($found, 'reg_active'); if ($activeFound): ?>
<form method="post" style="margin:0 0 8px">
  <input type="hidden" name="do" value="cancel_many"><input type="hidden" name="q" value="<?php echo h($q) ?>">
  <?php foreach ($activeFound as $r): ?><input type="hidden" name="ids[]" value="<?php echo (int)$r['id'] ?>"><?php endforeach; ?>
  <button class="danger" type="button" onclick="sure(this, 'Отменить все найденные (<?php echo count($activeFound) ?>)')">Отменить все найденные (<?php echo count($activeFound) ?>)</button>
  <span class="muted">без писем; освободившиеся места получат люди из листа ожидания</span>
</form>
<?php endif; ?>
<table>
  <tr><th>Сессия</th><th>Имя</th><th class="hide-sm">Телефон</th><th>Статус</th></tr>
  <?php foreach ($found as $r): $s = $sessions[$r['session']]; ?>
  <tr class="<?php echo $r['status'] === 'cancelled' ? 'cancelled' : '' ?>">
    <td><a href="<?php echo h($self . '&session=' . $s['id']) ?>#s"><?php echo h($s['title']) ?></a><div class="muted"><?php echo h(when($s['start'])) ?></div></td>
    <td><?php echo h($r['name']) ?></td>
    <td class="hide-sm"><?php echo h($r['phone']) ?></td>
    <td><?php echo $r['status'] === 'confirmed' ? 'записан(а)' : ($r['status'] === 'waiting' ? 'ожидание №' . reg_waiting_pos($data, $r) : 'отменено') ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<?php if ($changed): ?>
<div class="msg err"><b>Внимание:</b> <?php echo count($changed) ?> активных записей сделаны на сессию, которая с тех пор изменилась
  в таблице (другое название или время). Проверьте их:
  <ul><?php foreach ($changed as $r): ?>
    <li><?php echo h($r['name']) ?> — записан(а) на <?php echo h(reg_session_changed($r, $sessions)) ?>,
      сейчас это <?php echo isset($sessions[$r['session']]) ? '«' . h($sessions[$r['session']]['title']) . '», ' . h(when($sessions[$r['session']]['start'])) : 'сессия без записи' ?></li>
  <?php endforeach; ?></ul></div>
<?php endif; ?>
<p class="muted">Скачать всё: <a href="<?php echo h($self) ?>&download=csv">CSV</a> ·
  <a href="<?php echo h($self) ?>&download=json">JSON</a> (полная копия) ·
  <a href="<?php echo h($self) ?>&download=log">восстановить из журнала</a></p>

<h2>Сессии с записью</h2>
<?php if (!$sessions): ?>
<p class="muted">Нет сессий с записью (колонка reg-seats в таблице). После изменения таблицы нажмите Generate.</p>
<?php else: ?>
<table>
  <tr><th>Сессия</th><th class="hide-sm">Запись с</th><th class="num">Записаны</th><th class="num">Ожидание</th><th>Сейчас</th></tr>
  <?php foreach ($sessions as $s):
      $c = reg_counts($data, $s['id']);
      $state = $now >= $s['start'] ? 'closed' : ($s['opens'] && $now < $s['opens'] ? 'soon' : reg_state($data, $s));
      $label = array('open' => 'есть места', 'waitlist' => 'лист ожидания', 'full' => 'мест нет', 'soon' => 'ещё не открыта', 'closed' => 'началась')[$state];
  ?>
  <tr class="<?php echo $s['id'] == $sel ? 'sel' : '' ?>">
    <td><a href="<?php echo h($self . '&session=' . $s['id']) ?>#s"><?php echo h($s['title']) ?></a>
      <div class="muted"><?php echo h(when($s['start']) . ' · ' . $s['location'] . ($s['category'] !== '' ? ' · ' . $s['category'] . ' (≤' . $s['limit'] . ')' : '')) ?></div></td>
    <td class="hide-sm muted"><?php echo $s['opens'] ? h(when($s['opens'])) : '-' ?></td>
    <td class="num"><?php echo $c['confirmed'] ?> / <?php echo $s['seats'] ?></td>
    <td class="num"><?php echo $c['waiting'] ?> / <?php echo $s['waitlist'] ?></td>
    <td><span class="pill p-<?php echo $state ?>"><?php echo $label ?></span></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<?php if (isset($sessions[$sel])):
    $s = $sessions[$sel];
    $c = reg_counts($data, $sel);
    $regs = session_regs($data, $sel, true);
    // the same name or phone twice in one session (e.g. the app and Safari on one iPhone)
    $seen = array(); $dups = array();
    foreach ($regs as $r) {
        if (!reg_active($r)) continue;
        foreach (array('n:' . reg_norm_name($r['name']), 'p:' . reg_norm_phone($r['phone'])) as $k) {
            if (isset($seen[$k]) && $k !== 'p:') { $dups[$r['id']] = 1; $dups[$seen[$k]] = 1; }
            $seen[$k] = $r['id'];
        }
    }
?>
<h2 id="s"><?php echo h($s['title']) ?></h2>
<p class="muted"><?php echo h(when($s['start']) . ' · ' . $s['location']) ?> ·
  записаны <?php echo $c['confirmed'] ?> из <?php echo $s['seats'] ?>, ожидание <?php echo $c['waiting'] ?> из <?php echo $s['waitlist'] ?>
  · <a href="<?php echo h($self . '&session=' . $sel . '&csv=1') ?>">CSV</a>
  · <a href="#" onclick="window.print();return false">печать</a></p>

<form class="add" method="post">
  <input type="hidden" name="do" value="add">
  <input type="hidden" name="session" value="<?php echo $sel ?>">
  <div class="row">
    <input type="text" name="first" placeholder="Имя" required>
    <input type="text" name="last" placeholder="Фамилия" required>
    <input type="tel" name="phone" placeholder="Телефон" required>
    <input type="email" name="email" placeholder="E-mail (не обязательно)">
  </div>
  <div class="row" style="align-items:center">
    <select name="status">
      <option value="">автоматически</option>
      <option value="confirmed">записать (место)</option>
      <option value="waiting">в лист ожидания</option>
    </select>
    <select name="lang"><option value="ru">письмо: рус</option><option value="he">עב</option><option value="en">eng</option><option value="ukr">укр</option></select>
    <label><input type="checkbox" name="override" value="1"> игнорировать ограничения</label>
    <label><input type="checkbox" name="notify" value="1" checked> отправить письмо</label>
    <button class="primary">Записать</button>
  </div>
</form>

<table>
  <tr><th>#</th><th>Имя</th><th>Телефон</th><th class="hide-sm">E-mail</th><th class="hide-sm">Когда</th><th></th></tr>
  <?php $n = 0; foreach ($regs as $r): $active = reg_active($r); ?>
  <tr class="<?php echo $active ? '' : 'cancelled' ?> <?php echo isset($dups[$r['id']]) ? 'dup' : '' ?>">
    <td class="num"><?php echo $r['status'] === 'confirmed' ? ++$n : ($r['status'] === 'waiting' ? 'W' . reg_waiting_pos($data, $r) : '') ?></td>
    <td><?php echo h($r['name']) ?>
      <?php if ($r['by'] === 'helpdesk'): ?><span class="tag">HelpDesk</span><?php endif; ?>
      <?php if (!empty($r['override'])): ?><span class="tag">вне правил</span><?php endif; ?>
      <?php if (!empty($r['promoted'])): ?><span class="tag">из ожидания</span><?php endif; ?>
      <?php if (isset($dups[$r['id']])): ?><span class="tag">повтор?</span><?php endif; ?>
      <?php if (reg_active($r) && ($was = reg_session_changed($r, $sessions)) !== ''): ?><span class="tag" style="color:#b3261e">сессия изменилась: <?php echo h($was) ?></span><?php endif; ?>
      <?php if (!$active): ?><div class="muted">отменено <?php echo !empty($r['cancelled']) ? h(date('d.m H:i', $r['cancelled'])) : '' ?> (<?php echo h(isset($r['cancelled_by']) ? $r['cancelled_by'] : '') ?>)</div><?php endif; ?>
    </td>
    <td><a href="tel:<?php echo h(preg_replace('/[^\d+]/', '', $r['phone'])) ?>"><?php echo h($r['phone']) ?></a></td>
    <td class="hide-sm"><?php echo h($r['email']) ?></td>
    <td class="hide-sm muted"><?php echo h(date('d.m H:i', $r['created'])) ?></td>
    <td style="white-space:nowrap">
      <?php if ($active): ?>
        <?php if ($r['status'] === 'waiting'): ?>
        <form class="inline" method="post"><input type="hidden" name="do" value="confirm"><input type="hidden" name="id" value="<?php echo $r['id'] ?>"><input type="hidden" name="session" value="<?php echo $sel ?>"><input type="hidden" name="notify" value="1"><button>Дать место</button></form>
        <?php endif; ?>
        <form class="inline" method="post"><input type="hidden" name="do" value="cancel"><input type="hidden" name="id" value="<?php echo $r['id'] ?>"><input type="hidden" name="session" value="<?php echo $sel ?>"><input type="hidden" name="notify" value="1"><button class="danger" type="button" onclick="sure(this)">Отменить</button></form>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$regs): ?><tr><td colspan="6" class="muted">Пока никто не записан</td></tr><?php endif; ?>
</table>
<?php endif; ?>
</div>
<script>
  // two taps to cancel (no browser dialog): the first one asks, the second one cancels
  function sure(b, label) {
    if (b.classList.contains('sure')) { b.form.submit(); return; }
    b.classList.add('sure');
    b.textContent = 'Точно отменить?';
    setTimeout(function () { b.classList.remove('sure'); b.textContent = label || 'Отменить'; }, 4000);
  }
</script>
</body>
</html>
