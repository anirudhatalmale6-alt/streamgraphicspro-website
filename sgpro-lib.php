<?php
/* Shared helpers for the gated download. Nothing here needs editing — see sgpro-config.php. */
require_once __DIR__ . '/sgpro-config.php';

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
