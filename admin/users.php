<?php
// Canonical URL is admin-users.php — redirect and preserve any query string.
$qs = $_SERVER['QUERY_STRING'] !== '' ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: admin-users.php' . $qs, true, 302);
exit;
