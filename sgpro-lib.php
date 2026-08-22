<?php
/* Shared helpers for the gated download. Nothing here needs editing — see sgpro-config.php. */
require_once __DIR__ . '/sgpro-config.php';

/* ============================================================================
 * Is this site still running on the settings it shipped with?
 *
 * 🚨 This exists because it actually happened. sgpro-config.php is the one file the owner
 * edits, and it was bundled in the same zip as the pages he re-uploads - so refreshing a page
 * silently put the template values back, and the leads password became a string printed in
 * every copy of that zip. Nothing broke, nothing warned, and the customer list sat behind a
 * publicly known password until somebody happened to look.
 *
 * A security hole that fails silently is the worst kind. These make it loud.
 * ==========================================================================*/
function sgpro_default_password(): bool {
    return !defined('SGPRO_LEADS_PASSWORD')
        || SGPRO_LEADS_PASSWORD === '' || stripos(SGPRO_LEADS_PASSWORD, 'CHANGE-ME') === 0;
}
function sgpro_default_secret(): bool {
    return !defined('SGPRO_SECRET')
        || stripos(SGPRO_SECRET, 'CHANGE-ME') === 0 || strlen(SGPRO_SECRET) < 20;
}

/* The block both admin pages show instead of letting anybody in. Deliberately refuses the
 * login rather than merely warning: while the password is the one from the template, "signing
 * in" protects nothing, so allowing it would only make the hole feel closed. */
function sgpro_setup_warning_html(): string {
    $pw = sgpro_default_password();
    $sc = sgpro_default_secret();
    if (!$pw && !$sc) { return ''; }
    $h  = '<div style="max-width:720px;margin:0 auto 24px;background:#fdeaea;border:1px solid #d98b8b;'
        . 'border-left:5px solid #b4192d;border-radius:12px;padding:18px 22px;color:#4a1216;line-height:1.6">';
    $h .= '<b style="font-size:17px">This site is still using the settings it shipped with.</b><br><br>';
    if ($pw) {
        $h .= 'The password for this page is still <code>CHANGE-ME-too</code> — the one printed in '
            . 'the setup files, which means it is not a password at all. <b>Sign-in is switched off '
            . 'until it is changed.</b><br><br>';
    }
    if ($sc) {
        $h .= 'The link-signing secret is still the example one, so e-mailed download links could be '
            . 'forged by anyone who has seen the setup files.<br><br>';
    }
    $h .= '<b>To fix it, edit <code>public_html/sgpro-config.php</code>:</b><ul style="margin:8px 0 0 18px">';
    if ($sc) { $h .= '<li><code>SGPRO_SECRET</code> — any long random string, 30+ characters. '
                   . 'Changing it stops previously e-mailed download links from working, which is fine.</li>'; }
    if ($pw) { $h .= '<li><code>SGPRO_LEADS_PASSWORD</code> — a password only you know.</li>'; }
    $h .= '</ul><p style="margin:12px 0 0">Save the file and reload this page. Nothing else needs changing.</p>';
    $h .= '<p style="margin:10px 0 0;font-size:13px;opacity:.85">If this has appeared out of nowhere, '
        . 'sgpro-config.php was probably overwritten by re-uploading a setup zip over the top of it.</p>';
    return $h . '</div>';
}

function sgpro_site_url(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'streamgraphicspro.com';
    $dir  = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return ($https ? 'https://' : 'http://') . $host . $dir;
}

function sgpro_sign(string $email, int $exp): string {
    return hash_hmac('sha256', strtolower(trim($email)) . '|' . $exp, SGPRO_SECRET);
}

function sgpro_token(string $email, int $ttl = SGPRO_LINK_TTL): string {
    $exp = time() + $ttl;
    $raw = strtolower(trim($email)) . '|' . $exp . '|' . sgpro_sign($email, $exp);
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

function sgpro_token_email(string $tok): ?string {
    $raw = base64_decode(strtr($tok, '-_', '+/'), true);
    if ($raw === false) return null;
    $p = explode('|', $raw);
    if (count($p) !== 3) return null;
    [$email, $exp, $sig] = $p;
    if (!ctype_digit($exp) || (int)$exp < time()) return null;
    if (!hash_equals(sgpro_sign($email, (int)$exp), $sig)) return null;
    return $email;
}

/** Appends one row to the leads CSV, creating the folder and header on first use. */
function sgpro_save_lead(array $row): bool {
    $dir = dirname(SGPRO_LEADS);
    if (!is_dir($dir)) { @mkdir($dir, 0750, true); }
    // Belt and braces: keep the folder out of the browser's reach even if the
    // .htaccess upload was missed.
    if (!file_exists($dir . '/.htaccess')) {
        @file_put_contents($dir . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
    }
    if (!file_exists($dir . '/index.html')) { @file_put_contents($dir . '/index.html', ''); }

    $cols = ['date','name','email','org','role','uses','news','ip','ref'];
    $new  = !file_exists(SGPRO_LEADS);
    $fh = @fopen(SGPRO_LEADS, 'a');
    if (!$fh) return false;
    @flock($fh, LOCK_EX);
    if ($new) { fputcsv($fh, $cols); }
    fputcsv($fh, array_map(fn($c) => (string)($row[$c] ?? ''), $cols));
    @flock($fh, LOCK_UN);
    fclose($fh);
    return true;
}

/** E-mails the download link. Returns false if the host refused to send. */
function sgpro_send_link(string $name, string $email, string $link): bool {
    $first = trim(explode(' ', trim($name))[0]) ?: 'there';
    $subject = 'Your StreamGraphics Pro download';
    $body =
        "Hi $first,\r\n\r\n" .
        "Thanks for giving StreamGraphics Pro a go. Here's your download:\r\n\r\n" .
        "  $link\r\n\r\n" .
        "Run the installer and it adds a StreamGraphics Pro shortcut to your desktop and Start Menu.\r\n" .
        "Launching it opens your control panel in the browser at http://localhost:4000 — everything runs\r\n" .
        "on your own computer, nothing in the cloud.\r\n\r\n" .
        "If you get stuck or you've got an idea for it, just reply to this e-mail. I read every one.\r\n\r\n" .
        "Mark\r\n" .
        "Manhattan Beach Studios\r\n" .
        "www.streamgraphicspro.com\r\n";

    $from = SGPRO_FROM_NAME . ' <' . SGPRO_FROM . '>';
    $headers = "From: $from\r\nReply-To: " . SGPRO_FROM . "\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\nMIME-Version: 1.0\r\n";
    return @mail($email, $subject, $body, $headers, '-f' . SGPRO_FROM);
}
