<?php
/* Records that a licence was opened on a machine.
 *
 * The app calls this once when it starts. It exists so you can see that a key sold to one
 * school is being opened on forty different computers. It is a DETECTION aid and nothing more:
 * it can never take a licence away, and the app ignores whatever this returns.
 *
 * WHAT IS STORED
 *   the licence fingerprint (a hash, never the key)
 *   a machine id (a hash the app makes; it is salted with the licence, so the same computer
 *                 under two different licences looks like two unrelated machines)
 *   first seen, last seen, and how many launches
 *
 * WHAT IS DELIBERATELY NOT STORED
 *   no customer name, no email, and NO IP ADDRESS. An IP would make this a record of where
 *   your customers are, which is a much bigger thing to hold than a count, and it is not
 *   needed to answer the only question being asked: how many machines is this key on.
 *
 * The file it writes lives outside public_html where the host allows it - see sgpro-config.php.
 */
require_once __DIR__ . '/sgpro-config.php';
if (defined('SGPRO_QUIET') && SGPRO_QUIET) { @ini_set('display_errors', '0'); }

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

$k = isset($_GET['k']) ? (string)$_GET['k'] : '';
$m = isset($_GET['m']) ? (string)$_GET['m'] : '';
$v = isset($_GET['v']) ? (string)$_GET['v'] : '';

// Anything that isn't the shape we expect is dropped without ceremony. This endpoint is
// public by necessity, so it must never write whatever it is handed.
if (!preg_match('/^[0-9a-f]{16,64}$/', $k) || !preg_match('/^[0-9a-f]{8,32}$/', $m)) {
    http_response_code(204);
    exit;
}
$v = preg_replace('/[^0-9A-Za-z.\-]/', '', substr($v, 0, 16));

$file = defined('SGPRO_SEEN_FILE') ? SGPRO_SEEN_FILE : (__DIR__ . '/sgpro-seen.json');
$today = gmdate('Y-m-d');

/* One lock, read-modify-write. Several copies of the app can start at the same moment across
 * different customers, and without the lock two of them can each read the file, each add their
 * own row, and the second write silently discards the first. */
$fh = @fopen($file, 'c+');
if (!$fh) { http_response_code(204); exit; }
@flock($fh, LOCK_EX);

$raw = stream_get_contents($fh);
$data = json_decode($raw ?: '{}', true);
if (!is_array($data)) { $data = array(); }
if (!isset($data['keys']) || !is_array($data['keys'])) { $data['keys'] = array(); }

if (!isset($data['keys'][$k]) || !is_array($data['keys'][$k])) {
    $data['keys'][$k] = array('machines' => array());
}
$entry =& $data['keys'][$k];
if (!isset($entry['machines']) || !is_array($entry['machines'])) { $entry['machines'] = array(); }

if (!isset($entry['machines'][$m])) {
    $entry['machines'][$m] = array('first' => $today, 'last' => $today, 'n' => 1, 'v' => $v);
} else {
    $entry['machines'][$m]['last'] = $today;
    $entry['machines'][$m]['n'] = (int)$entry['machines'][$m]['n'] + 1;
    if ($v !== '') { $entry['machines'][$m]['v'] = $v; }
}

/* A hard ceiling per key. Without it, a key that really has leaked would grow this file
 * without limit — and the runaway case is exactly the one where the server must stay healthy
 * enough to keep telling you about it. Past the cap the count still climbs; only the
 * per-machine detail stops being added.
 */
$CAP = 500;
if (count($entry['machines']) > $CAP) {
    $entry['overflow'] = (int)(isset($entry['overflow']) ? $entry['overflow'] : 0) + 1;
    // Drop the least recently seen so the list stays the most useful 500.
    uasort($entry['machines'], function ($a, $b) { return strcmp($a['last'], $b['last']); });
    array_shift($entry['machines']);
}
unset($entry);

$data['updated'] = gmdate('c');

ftruncate($fh, 0);
rewind($fh);
fwrite($fh, json_encode($data));
fflush($fh);
@flock($fh, LOCK_UN);
fclose($fh);

http_response_code(204);
