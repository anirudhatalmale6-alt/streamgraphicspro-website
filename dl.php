<?php
/* Serves a download. Two ways in:
 *   dl.php?t=<token>   a signed link, handed out after someone gives their e-mail
 *   dl.php?u=1         the in-app update check on already-installed copies — never gated
 *
 * &b=win | mac-arm | mac-intel picks WHICH build. Leave it off and you get Windows, so every
 * link e-mailed before the Mac version existed still works exactly as it did.
 *
 * The ?b= value is a key into the fixed list in sgpro_builds(), never part of a path, so there
 * is no ?b= a stranger can invent that reaches a file which is not on that list.
 *
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

$key   = isset($_GET['b']) ? (string)$_GET['b'] : 'win';
$build = sgpro_build($key);

if (!$build['ready']) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo "That download isn't on the server yet. Please try again shortly.";
    exit;
}

// Log who actually pulled the file, and which one, so the leads page can show it.
if (!$update) {
    @file_put_contents(dirname(SGPRO_LEADS) . '/downloads.log',
        date('c') . "\t" . $email . "\t" . ($_SERVER['REMOTE_ADDR'] ?? '') . "\t" . $build['label'] . "\n",
        FILE_APPEND | LOCK_EX);
}

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $build['name'] . '"');
header('Content-Length: ' . filesize($build['file']));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
while (ob_get_level()) { ob_end_clean(); }
readfile($build['file']);
