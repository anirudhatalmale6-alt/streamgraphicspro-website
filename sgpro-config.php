<?php
/* StreamGraphics Pro — settings for the gated download.
 * This is the ONLY file you normally need to edit.
 * Keep it in the same folder as get-started.php / dl.php / leads.php. */

// 1) Make up a long random string and paste it here. Anything, 30+ characters.
//    It signs the download links so people can't share or guess them.
//    If you ever change it, existing e-mailed links stop working. That's fine.
const SGPRO_SECRET = 'CHANGE-ME-to-a-long-random-string-abc123xyz789';

// 2) Password for the leads page (yoursite.com/leads.php).
const SGPRO_LEADS_PASSWORD = 'CHANGE-ME-too';

// 3) Where the installer actually sits on the server, relative to this file.
const SGPRO_FILE = __DIR__ . '/download/StreamGraphicsProSetup.exe';
const SGPRO_FILE_NAME = 'StreamGraphicsProSetup.exe';

// 4) Where the leads are stored. Kept out of the browser's reach by .htaccess.
const SGPRO_LEADS = __DIR__ . '/sgpro-leads/leads.csv';

// 5) The "from" address on the e-mail that carries the download link.
//    Use an address on your own domain or it may land in spam.
const SGPRO_FROM = 'mark@streamgraphicspro.com';
const SGPRO_FROM_NAME = 'Mark at StreamGraphics Pro';

// 6) How long an e-mailed download link stays valid (seconds). 7 days by default.
const SGPRO_LINK_TTL = 604800;

// 7) Set to true once you're happy, to stop showing PHP errors on screen.
const SGPRO_QUIET = true;
