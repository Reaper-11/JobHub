<?php
// Canonical URL is admin-dashboard.php — redirect and preserve any query string.
$qs = $_SERVER['QUERY_STRING'] !== '' ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: admin-dashboard.php' . $qs, true, 302);
exit;
