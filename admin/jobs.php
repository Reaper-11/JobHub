<?php
$qs = $_SERVER['QUERY_STRING'] !== '' ? '?' . $_SERVER['QUERY_STRING'] : '?status=all&page=1';
header('Location: admin-jobs.php' . $qs, true, 302);
exit;
