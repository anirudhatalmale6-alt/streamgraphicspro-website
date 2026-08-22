<?php
/* Everyone who has asked for the free download. Password is in sgpro-config.php. */
require_once __DIR__ . '/sgpro-lib.php';
if (SGPRO_QUIET) { @ini_set('display_errors', '0'); }
session_start();

if (isset($_GET['logout'])) { $_SESSION = []; session_destroy(); header('Location: leads.php'); exit; }
if (($_POST['pw'] ?? '') !== '') {
    // Refused outright while the password is the one from the template — see sgpro-lib.php.
    if (sgpro_default_password()) { $bad = true; }
    elseif (hash_equals(SGPRO_LEADS_PASSWORD, (string)$_POST['pw'])) { $_SESSION['sgpro_leads'] = true; }
    else { $bad = true; }
}
$ok = !empty($_SESSION['sgpro_leads']) && !sgpro_default_password();

if ($ok && isset($_GET['csv'])) {                       // straight download for Excel / Mailchimp
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="streamgraphicspro-leads-' . date('Y-m-d') . '.csv"');
    readfile(SGPRO_LEADS);
    exit;
}

$rows = [];
if ($ok && is_readable(SGPRO_LEADS)) {
    $fh = fopen(SGPRO_LEADS, 'r');
    $head = fgetcsv($fh);
    while (($r = fgetcsv($fh)) !== false) { if (count($r) === count($head)) $rows[] = array_combine($head, $r); }
    fclose($fh);
    $rows = array_reverse($rows);                       // newest first
}
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Downloads — StreamGraphics Pro</title>
<meta name="robots" content="noindex,nofollow">
<link rel="stylesheet" href="site.css">
<style>
  .tblwrap{overflow-x:auto;border:1px solid var(--line);border-radius:14px;background:var(--panel)}
  table{border-collapse:collapse;width:100%;font-size:14px;min-width:820px}
  th,td{padding:10px 13px;text-align:left;border-bottom:1px solid var(--line);vertical-align:top}
  th{background:var(--panel2);font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);white-space:nowrap}
  tr:last-child td{border-bottom:none}
  td.em{font-weight:700}
  .pwbox{max-width:360px;margin:0 auto;text-align:left}
  .pwbox input{width:100%;padding:12px 14px;border:1.5px solid var(--line);border-radius:12px;font:inherit;margin-bottom:12px}
  .stat{display:flex;gap:14px;flex-wrap:wrap;margin-bottom:20px}
  .stat div{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:14px 20px;min-width:130px}
  .stat b{display:block;font-size:26px;line-height:1.2}
  .stat span{color:var(--muted);font-size:13px}
</style>
</head>
<body>
<section><div class="wrap">
<?php if (!$ok): ?>
  <?= sgpro_setup_warning_html() ?>
  <div class="center"><h2>Downloads</h2><p class="lead">Enter the password.</p></div>
  <form class="pwbox" method="post">
    <?php if (!empty($bad)): ?><p style="color:#8a2418;font-size:14px">Wrong password.</p><?php endif; ?>
    <input type="password" name="pw" autofocus placeholder="Password">
    <button class="btn" type="submit" style="width:100%">Open</button>
  </form>
<?php else: ?>
  <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:18px">
    <h2 style="margin:0">Downloads</h2>
    <span style="flex:1"></span>
    <a class="btn alt" href="leads.php?csv=1">Export CSV</a>
    <a class="btn alt" href="leads.php?logout=1">Sign out</a>
  </div>
  <?php
    $today = 0; $week = 0; $news = 0; $t0 = strtotime('today'); $w0 = strtotime('-7 days');
    foreach ($rows as $r) { $t = strtotime($r['date'] ?? ''); if ($t >= $t0) $today++; if ($t >= $w0) $week++; if (($r['news'] ?? '') === 'yes') $news++; }
  ?>
  <div class="stat">
    <div><b><?= count($rows) ?></b><span>total</span></div>
    <div><b><?= $week ?></b><span>last 7 days</span></div>
    <div><b><?= $today ?></b><span>today</span></div>
    <div><b><?= $news ?></b><span>opted in to news</span></div>
  </div>
  <?php if (!$rows): ?>
    <p class="lead">Nobody yet. As soon as someone fills in the form on <a href="get-started.php">get-started.php</a>, they'll show up here.</p>
  <?php else: ?>
  <div class="tblwrap"><table>
    <tr><th>When</th><th>Name</th><th>E-mail</th><th>Organisation</th><th>Who they are</th><th>Using it for</th><th>News</th></tr>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td style="white-space:nowrap;color:var(--muted)"><?= h(date('j M Y, H:i', strtotime($r['date'] ?? ''))) ?></td>
        <td class="em"><?= h($r['name'] ?? '') ?></td>
        <td><a href="mailto:<?= h($r['email'] ?? '') ?>"><?= h($r['email'] ?? '') ?></a></td>
        <td><?= h($r['org'] ?? '') ?></td>
        <td><?= h($r['role'] ?? '') ?></td>
        <td><?= h($r['uses'] ?? '') ?></td>
        <td><?= ($r['news'] ?? '') === 'yes' ? '✓' : '' ?></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
  <p style="color:var(--muted);font-size:13px;margin-top:14px">Export CSV opens straight in Excel, Google Sheets or Mailchimp.</p>
  <?php endif; ?>
<?php endif; ?>
</div></section>
</body>
</html>
