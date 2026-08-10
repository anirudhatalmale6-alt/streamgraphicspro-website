<?php
/* Serves the installer. Two ways in:
 *   dl.php?t=<token>   a signed link, handed out after someone gives their e-mail
 *   dl.php?u=1         the in-app update check on already-installed copies — never gated
 * The download folder itself is blocked by download/.htaccess, so this is the only door. */
require_once __DIR__ . '/sgpro-lib.php';
if (SGPRO_QUIET) { @ini_set('display_errors', '0'); }

$update = isset($_GET['u']);
$tok    = isset($_GET['t']) ? (string)$_GET['t'] : '';
$email  = $update ? '' : (sgpro_token_email($tok) ?? '');

if (!$update && $email === '') {
    header('Location: get-started.php?expired=1', true, 302);
    exit;
}
if (!is_readable(SGPRO_FILE)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo "The installer isn't on the server yet. Please try again shortly.";
    exit;
}

// Log who actually pulled the file, so the leads page can show it.
if (!$update) {
    @file_put_contents(dirname(SGPRO_LEADS) . '/downloads.log',
        date('c') . "\t" . $email . "\t" . ($_SERVER['REMOTE_ADDR'] ?? '') . "\n",
        FILE_APPEND | LOCK_EX);
}

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . SGPRO_FILE_NAME . '"');
header('Content-Length: ' . filesize(SGPRO_FILE));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
while (ob_get_level()) { ob_end_clean(); }
readfile(SGPRO_FILE);
