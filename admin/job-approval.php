<?php
// Canonical URL is admin-jobs.php — redirect and preserve any query string.
$qs = $_SERVER['QUERY_STRING'] !== '' ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: admin-jobs.php' . $qs, true, 302);
exit;
