<?php
/* The gated free download. Form -> lead saved -> link shown on screen AND e-mailed. */
require_once __DIR__ . '/sgpro-lib.php';
if (SGPRO_QUIET) { @ini_set('display_errors', '0'); }

$done = false; $err = ''; $link = ''; $mailed = false;
$name = $email = $org = ''; $role = ''; $uses = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $org   = trim((string)($_POST['org'] ?? ''));
    $role  = trim((string)($_POST['role'] ?? ''));
    $uses  = array_map('strval', (array)($_POST['uses'] ?? []));
    $hp    = trim((string)($_POST['website'] ?? ''));   // honeypot — bots fill this, people never see it

    if ($hp !== '') { $err = 'Something went wrong. Please try again.'; }
    elseif ($name === '') { $err = 'Please add your name.'; }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $err = 'That e-mail address does not look right.'; }
    else {
        sgpro_save_lead([
            'date'  => date('c'),
            'name'  => $name,
            'email' => $email,
            'org'   => $org,
            'role'  => $role,
            'uses'  => implode('; ', $uses),
            'news'  => isset($_POST['news']) ? 'yes' : 'no',
            'ip'    => $_SERVER['REMOTE_ADDR'] ?? '',
            'ref'   => $_SERVER['HTTP_REFERER'] ?? '',
        ]);
        $link = sgpro_site_url() . '/dl.php?t=' . sgpro_token($email);
        $mailed = sgpro_send_link($name, $email, $link);
        $done = true;
    }
}
$expired = isset($_GET['expired']);
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $done ? 'Your download is ready' : 'Get StreamGraphics Pro — free' ?> — StreamGraphics Pro</title>
<meta name="description" content="Download StreamGraphics Pro free. Lower thirds, scoreboards, timers and slides for OBS, vMix and any browser source.">
<meta name="robots" content="noindex">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Ccircle cx='32' cy='32' r='30' fill='%23ef6a4c'/%3E%3C/svg%3E">
<link rel="stylesheet" href="site.css">
<style>
  .gform{max-width:560px;margin:0 auto;text-align:left}
  .gform label.fl{display:block;font-weight:700;font-size:14.5px;margin:16px 0 6px}
  .gform input[type=text],.gform input[type=email],.gform select{
    width:100%;padding:12px 14px;border:1.5px solid var(--line);border-radius:12px;font:inherit;background:#fff;color:var(--txt)}
  .gform input:focus,.gform select:focus{outline:none;border-color:var(--accent)}
  .gform .checks{display:flex;flex-wrap:wrap;gap:8px;margin-top:6px}
  .gform .checks label{display:inline-flex;align-items:center;gap:7px;border:1.5px solid var(--line);border-radius:999px;
    padding:8px 14px;font-size:14px;cursor:pointer;background:#fff}
  .gform .checks label:hover{border-color:var(--accent)}
  .gform .checks input{accent-color:var(--accent)}
  .gform .tiny{color:var(--muted);font-size:13px;line-height:1.55}
  .gform .btn{margin-top:22px;width:100%;text-align:center;font-size:17px}
  .hp{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}
  .msg{border-radius:12px;padding:12px 16px;font-size:14.5px;margin-bottom:6px}
  .msg.bad{background:#fdecea;border:1px solid #f5c2bd;color:#8a2418}
  .msg.warn{background:#fff6e5;border:1px solid #f2d9a7;color:#7a5510}
  .dlbox{max-width:620px;margin:0 auto;text-align:left}
  .dlbox code{background:var(--panel2);border:1px solid var(--line);padding:3px 8px;border-radius:7px;font-size:13.5px;word-break:break-all}
</style>
</head>
<body>
<header class="nav"><div class="wrap">
  <a class="brand" href="index.html"><svg class="logomark" width="30" height="30" viewBox="0 0 32 32" aria-hidden="true" style="flex:none"><defs><linearGradient id="sgm" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#f4a63c"/><stop offset="1" stop-color="#ef6a4c"/></linearGradient></defs><rect width="32" height="32" rx="9" fill="url(#sgm)"/><rect x="6" y="11.5" width="13.5" height="9" rx="2.2" fill="#fff"/><path d="M20.5 13.8 L26 10.5 L26 21.5 L20.5 18.2 Z" fill="#fff"/></svg> StreamGraphics Pro</a>
  <button class="menubtn" onclick="document.getElementById('nv').classList.toggle('open')">☰</button>
  <nav id="nv">
    <a href="index.html#features">Features</a><a href="pricing.html">Pricing</a>
    <a href="tutorials.html">Videos</a><a href="docs.html">Guide</a><a href="companion.html">Companion</a><a href="faq.html">FAQ</a>
    <a href="contact.html">Contact</a><a class="navcta" href="get-started.php">Download</a>
  </nav>
</div></header>

<?php if ($done): ?>
<section><div class="wrap center">
  <h2>You're all set, <?= h(explode(' ', $name)[0]) ?> 👋</h2>
  <p class="lead">Here's your download. I've also sent the link to <b><?= h($email) ?></b> so you can grab it on another machine.</p>
  <p><a class="btn" href="<?= h($link) ?>">Download for Windows</a></p>
  <div class="dlbox">
    <?php if (!$mailed): ?>
      <div class="msg warn">The e-mail didn't go out just now — no problem, the button above works. If you want it on another computer, copy this link: <code><?= h($link) ?></code></div>
    <?php endif; ?>
    <p style="color:var(--muted);font-size:14.5px;line-height:1.65;margin-top:18px">
      <b>What happens next:</b> run the installer, and it puts a <b>StreamGraphics Pro</b> shortcut on your desktop and in the Start Menu.
      Launching it opens your control panel in the browser at <code>http://localhost:4000</code>. Nothing runs in the cloud —
      it's all on your own computer.
    </p>
    <p style="color:var(--muted);font-size:14.5px;line-height:1.65">
      <b>If Windows shows a blue warning.</b> The installer is signed by <b>Manhattan Beach Studios LLC</b> — that name is what you
      should see on the prompt. Windows still puts a "Windows protected your PC" box in front of newly released software until
      enough people have downloaded it. Click <b>More info</b>, then <b>Run anyway</b>. If the publisher says anything other than
      Manhattan Beach Studios LLC, stop and <a href="contact.html">tell me</a>.
    </p>
    <p style="color:var(--muted);font-size:14.5px;line-height:1.65">
      New to it? The <a href="docs.html">quick guide</a> gets you a lower third on air in about two minutes, and there are
      <a href="tutorials.html">short videos</a> too. Questions, ideas, gripes — <a href="contact.html">tell me</a>. I read everything.
    </p>
    <p style="color:var(--muted);font-size:14.5px;line-height:1.65">
      Running a <b>Stream Deck</b>? There's a free <a href="companion.html"><b>Bitfocus Companion module</b></a> — drag ready-made
      buttons straight out of the Presets tab, with the live score and your bullet position printed on the keys.
    </p>
    <p style="color:var(--muted);font-size:13px">The link stays good for 7 days. Need it again later? Just come back to this page.</p>
  </div>
</div></section>

<?php else: ?>
<section><div class="wrap center">
  <h2>Get StreamGraphics Pro — free</h2>
  <p class="lead">The full app, on your own computer. No card, no subscription, no time limit — just a small watermark on the output until you decide to buy.</p>

  <form class="gform" method="post" action="get-started.php">
    <?php if ($err): ?><div class="msg bad"><?= h($err) ?></div><?php endif; ?>
    <?php if ($expired): ?><div class="msg warn">That download link has expired. Pop your e-mail in again and I'll send a fresh one.</div><?php endif; ?>

    <label class="fl" for="f-name">Your name</label>
    <input type="text" id="f-name" name="name" required autocomplete="name" value="<?= h($name) ?>" placeholder="Mark Nicholas">

    <label class="fl" for="f-email">E-mail</label>
    <input type="email" id="f-email" name="email" required autocomplete="email" value="<?= h($email) ?>" placeholder="you@example.com">

    <label class="fl" for="f-org">Church, school, club or company <span style="font-weight:500;color:var(--muted)">(optional)</span></label>
    <input type="text" id="f-org" name="org" autocomplete="organization" value="<?= h($org) ?>" placeholder="Manhattan Beach Studios">

    <label class="fl" for="f-role">What best describes you? <span style="font-weight:500;color:var(--muted)">(optional)</span></label>
    <select id="f-role" name="role">
      <option value="">Choose one…</option>
      <?php foreach (['Volunteer / hobbyist','Church or school media team','Freelance video pro','Production company','Broadcaster','Streamer / creator','Something else'] as $r): ?>
        <option value="<?= h($r) ?>"<?= $role === $r ? ' selected' : '' ?>><?= h($r) ?></option>
      <?php endforeach; ?>
    </select>

    <label class="fl">What will you use it for? <span style="font-weight:500;color:var(--muted)">(optional — pick any)</span></label>
    <div class="checks">
      <?php foreach (['Sports','Church services','School events','Live shows','Corporate / conference','Esports','Podcast / talk show'] as $u): ?>
        <label><input type="checkbox" name="uses[]" value="<?= h($u) ?>"<?= in_array($u, $uses, true) ? ' checked' : '' ?>> <?= h($u) ?></label>
      <?php endforeach; ?>
    </div>

    <div style="margin-top:18px">
      <label style="display:flex;gap:10px;align-items:flex-start;font-size:14.5px;cursor:pointer">
        <input type="checkbox" name="news" value="1" checked style="margin-top:3px;accent-color:var(--accent)">
        <span>Send me the occasional note about new features and template packs. No spam, and one click gets you off the list.</span>
      </label>
    </div>

    <div class="hp"><label>Leave this empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

    <button class="btn" type="submit">Send me the download</button>
    <p class="tiny" style="margin-top:14px">I'll e-mail you the link so you can install it on any machine. Your address stays with me —
      it's never sold or shared. See the <a href="privacy.html">privacy note</a>.</p>
  </form>
</div></section>
<?php endif; ?>

<footer><div class="wrap"><div>© StreamGraphics Pro · made with care by Mark</div><div><a href="docs.html">Guide</a> · <a href="faq.html">FAQ</a> · <a href="companion.html">Companion</a> · <a href="tutorials.html">Videos</a> · <a href="contact.html">Contact</a> · <a href="eula.html">License</a> · <a href="privacy.html">Privacy</a> · <a href="terms.html">Terms</a></div></div></footer>
</body>
</html>
