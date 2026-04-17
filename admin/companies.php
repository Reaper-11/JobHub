<?php
// Canonical URL is admin-companies.php — redirect and preserve any query string.
$qs = $_SERVER['QUERY_STRING'] !== '' ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: admin-companies.php' . $qs, true, 302);
exit;

