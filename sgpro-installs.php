<?php
/* How many machines each licence has been opened on. Password is the same one as leads.php,
 * in sgpro-config.php — deliberately, so there is no second password to keep track of.
 *
 * This page answers one question: is a key that was sold once being used in forty places.
 * It cannot switch anything off. Revoking is still a decision you make, in the License Maker.
 *
 * There is no name or e-mail here, because the counter never receives one — it only ever sees
 * a licence fingerprint. Paste a fingerprint into the License Maker to see whose it is.
 */
require_once __DIR__ . '/sgpro-lib.php';
if (SGPRO_QUIET) { @ini_set('display_errors', '0'); }
session_start();

if (isset($_GET['logout'])) { $_SESSION = []; session_destroy(); header('Location: sgpro-installs.php'); exit; }
if (($_POST['pw'] ?? '') !== '') {
    // Refused outright while the password is the one from the template — see sgpro-lib.php.
    if (sgpro_default_password()) { $bad = true; }
    elseif (hash_equals(SGPRO_LEADS_PASSWORD, (string)$_POST['pw'])) { $_SESSION['sgpro_leads'] = true; }
    else { $bad = true; }
}
$ok = !empty($_SESSION['sgpro_leads']) && !sgpro_default_password();

$FLAG = defined('SGPRO_INSTALL_FLAG') ? (int)SGPRO_INSTALL_FLAG : 8;
$FILE = sgpro_seen_file();   // shared with sgpro-seen.php - see sgpro-lib.php

$keys = [];
$updated = '';
if ($ok && is_readable($FILE)) {
    $d = json_decode((string)file_get_contents($FILE), true);
    $updated = is_array($d) && isset($d['updated']) ? (string)$d['updated'] : '';
    foreach ((is_array($d) && isset($d['keys']) && is_array($d['keys'])) ? $d['keys'] : [] as $fp => $e) {
        $machines = (isset($e['machines']) && is_array($e['machines'])) ? $e['machines'] : [];
        $last = ''; $first = ''; $launches = 0; $ver = '';
        foreach ($machines as $m) {
            if (($m['last'] ?? '') > $last) { $last = (string)($m['last'] ?? ''); $ver = (string)($m['v'] ?? ''); }
            if ($first === '' || ($m['first'] ?? '') < $first) { $first = (string)($m['first'] ?? ''); }
            $launches += (int)($m['n'] ?? 0);
        }
        $keys[] = [
            'fp' => (string)$fp,
            'machines' => count($machines) + (int)($e['overflow'] ?? 0),
            'capped' => !empty($e['overflow']),
            'launches' => $launches, 'first' => $first, 'last' => $last, 'v' => $ver,
        ];
    }
    // Worst first. The whole point is that the thing needing attention is at the top of the
    // page, not buried in a list sorted by something you don't care about.
    usort($keys, function ($a, $b) { return $b['machines'] <=> $a['machines']; });
}

// A CSV export for the same reason leads.php has one: it is the fastest way to keep a record.
if ($ok && isset($_GET['csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="streamgraphicspro-installs-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Licence fingerprint', 'Machines', 'Launches', 'First seen', 'Last seen', 'Version']);
    foreach ($keys as $k) { fputcsv($out, [$k['fp'], $k['machines'], $k['launches'], $k['first'], $k['last'], $k['v']]); }
    fclose($out);
    exit;
}

$flagged = 0;
foreach ($keys as $k) { if ($k['machines'] >= $FLAG) $flagged++; }
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Licence activity — StreamGraphics Pro</title>
<meta name="robots" content="noindex,nofollow">
<link rel="stylesheet" href="site.css">
<style>
  .tblwrap{overflow-x:auto;border:1px solid var(--line);border-radius:14px;background:var(--panel)}
  table{border-collapse:collapse;width:100%;font-size:14px;min-width:760px}
  th,td{padding:10px 13px;text-align:left;border-bottom:1px solid var(--line);vertical-align:top}
  th{background:var(--panel2);font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);white-space:nowrap}
  tr:last-child td{border-bottom:none}
  .pwbox{max-width:360px;margin:0 auto;text-align:left}
  .pwbox input{width:100%;padding:12px 14px;border:1.5px solid var(--line);border-radius:12px;font:inherit;margin-bottom:12px}
  .stat{display:flex;gap:14px;flex-wrap:wrap;margin-bottom:20px}
  .stat div{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:14px 20px;min-width:130px}
  .stat b{display:block;font-size:26px;line-height:1.2}
  .stat span{color:var(--muted);font-size:13px}
  .fp{font-family:ui-monospace,Consolas,monospace;font-size:12.5px;word-break:break-all}
  tr.hot td{background:rgba(180,25,45,.10)}
  tr.hot td:first-child{box-shadow:inset 3px 0 0 #b4192d}
  .big{font-size:19px;font-weight:800}
  .hotnum{color:#b4192d}
</style>
</head>
<body>
<section><div class="wrap">
<?php if (!$ok): ?>
  <?= sgpro_setup_warning_html() ?>
  <div class="center"><h2>Licence activity</h2><p class="lead">Enter the password — the same one as the downloads page.</p></div>
  <form class="pwbox" method="post">
    <?php if (!empty($bad)): ?><p style="color:#8a2418;font-size:14px">Wrong password.</p><?php endif; ?>
    <input type="password" name="pw" autofocus placeholder="Password">
    <button class="btn" type="submit" style="width:100%">Open</button>
  </form>
<?php else: ?>
  <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:18px">
    <h2 style="margin:0">Licence activity</h2>
    <span style="flex:1"></span>
    <a class="btn alt" href="sgpro-installs.php?csv=1">Export CSV</a>
    <a class="btn alt" href="leads.php">Downloads</a>
    <a class="btn alt" href="sgpro-installs.php?logout=1">Sign out</a>
  </div>

  <?php if (sgpro_seen_is_public()): ?>
    <div style="background:rgba(180,25,45,.10);border:1px solid rgba(180,25,45,.45);border-left:5px solid #b4192d;border-radius:12px;padding:14px 18px;margin-bottom:20px">
      <b>These counts are sitting inside your public web folder.</b><br>
      <code><?= h(sgpro_seen_file()) ?></code><br><br>
      The <code>.htaccess</code> in that folder is supposed to block it, and it does block
      <code>leads.csv</code> — but this host serves <code>.json</code> files straight off disk
      without checking <code>.htaccess</code>, so anyone who guessed the address could download
      it. There are no names or e-mail addresses in it, but it is still your customers' data.<br><br>
      <b>Fix:</b> in <code>public_html/sgpro-config.php</code>, change <code>SGPRO_SEEN_FILE</code> to<br>
      <code>const SGPRO_SEEN_FILE = __DIR__ . '/../sgpro-private/installs.json';</code><br>
      <span style="font-size:13px">(<code>__DIR__ . '/../'</code>, not <code>dirname(__DIR__)</code> — a
      <code>const</code> line may not call a function, and PHP refuses to load the whole file if it does.)</span><br>
      That is one level above <code>public_html</code>, where no web server can reach it. Then
      delete the old <code>installs.json</code>. The count starts again from zero, which costs
      you nothing.
    </div>
  <?php endif; ?>

  <div class="stat">
    <div><b><?= count($keys) ?></b><span>licences seen</span></div>
    <div><b<?= $flagged ? ' class="hotnum"' : '' ?>><?= $flagged ?></b><span>over <?= $FLAG ?> machines</span></div>
    <div><b><?= array_sum(array_column($keys, 'machines')) ?></b><span>machines in total</span></div>
  </div>

  <?php if ($flagged): ?>
    <p style="background:rgba(180,25,45,.10);border:1px solid rgba(180,25,45,.35);border-radius:12px;padding:12px 16px">
      <b><?= $flagged ?></b> licence<?= $flagged === 1 ? ' has' : 's have' ?> been opened on <?= $FLAG ?> or more machines.
      That can be perfectly innocent — a district with a lot of rooms, or somebody who has changed
      computer several times. Copy the fingerprint into the <b>License Maker</b> to see whose it is
      before doing anything — it lists the same code under each customer.
    </p>
  <?php endif; ?>

  <?php if (!$keys): $H = sgpro_seen_health(); ?>
    <p class="lead">Nothing recorded yet. Copies running 1.0.24 or later report in when they start
      and when a key is activated; older versions don't, so this fills up as people update.</p>
    <?php /* 🚨 "Empty" and "broken" look exactly alike from here, and the counter itself cannot
             tell you which - it answers the same way whether it recorded something or silently
             could not, because it must never leak or interrupt the app. So say it here. */ ?>
    <div class="tblwrap" style="padding:16px 18px">
      <b>If you expected something here, this is where to look</b>
      <table style="min-width:0;margin-top:10px">
        <tr><td>Reading from</td><td class="fp"><?= h($H['file']) ?></td></tr>
        <tr><td>Set in sgpro-config.php</td>
            <td><?= $H['configured']
                  ? '<b style="color:#1a7f4b">yes</b>'
                  : '<b class="hotnum">NO — add SGPRO_SEEN_FILE, or the counter and this page can end up using different files</b>' ?></td></tr>
        <tr><td>Folder exists</td><td><?= $H['dir_exists'] ? 'yes' : '<b class="hotnum">no</b>' ?></td></tr>
        <tr><td>Folder writable</td>
            <td><?= $H['dir_writable']
                  ? '<b style="color:#1a7f4b">yes</b>'
                  : '<b class="hotnum">NO — nothing can ever be recorded until this is fixed</b>' ?></td></tr>
        <tr><td>File written yet</td>
            <td><?= $H['file_exists']
                  ? ('yes, ' . number_format($H['file_size']) . ' bytes')
                  : 'not yet — no report has arrived' ?></td></tr>
      </table>
      <p style="margin:12px 0 0;color:var(--muted);font-size:13px">
        If the folder is writable and the file has never been written, nothing has reached the
        counter — check that <code>sgpro-seen.php</code> is in <code>public_html</code>. If the
        file exists but this list is empty, this page is reading a different file from the one
        being written, which means <code>SGPRO_SEEN_FILE</code> is missing from your config.
      </p>
    </div>
  <?php else: ?>
  <div class="tblwrap"><table>
    <tr><th>Licence fingerprint</th><th>Machines</th><th>Launches</th><th>First seen</th><th>Last seen</th><th>Version</th></tr>
    <?php foreach ($keys as $k): $hot = $k['machines'] >= $FLAG; ?>
      <tr<?= $hot ? ' class="hot"' : '' ?>>
        <td class="fp" title="<?= h($k['fp']) ?>"><?= h(substr($k['fp'], 0, 16)) ?></td>
        <td class="big<?= $hot ? ' hotnum' : '' ?>"><?= (int)$k['machines'] ?><?= $k['capped'] ? '+' : '' ?></td>
        <td><?= (int)$k['launches'] ?></td>
        <td style="white-space:nowrap;color:var(--muted)"><?= h($k['first']) ?></td>
        <td style="white-space:nowrap;color:var(--muted)"><?= h($k['last']) ?></td>
        <td style="color:var(--muted)"><?= h($k['v']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
  <p style="color:var(--muted);font-size:13px;margin-top:14px">
    Two machines per licence is normal — a show computer and a backup. There are no names or
    e-mail addresses here because this counter never receives one; it only ever sees a
    fingerprint. The License Maker lists the same code under each customer, so you can match
    one to the other by eye — hover a code here to see all of it, or use Export CSV.
    <?= $updated ? ' Last report: ' . h(date('j M Y, H:i', strtotime($updated))) . ' UTC.' : '' ?>
  </p>
  <?php endif; ?>
<?php endif; ?>
</div></section>
</body>
</html>
