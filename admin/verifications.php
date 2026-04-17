<?php
// Canonical URL is company-verifications.php — redirect and preserve any query string.
$qs = $_SERVER['QUERY_STRING'] !== '' ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: company-verifications.php' . $qs, true, 302);
exit;
