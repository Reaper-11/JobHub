<?php
require '../db.php';
if (!isset($_SESSION['company_id'])) {
    header("Location: company-login.php");
    exit;
}

$cid = (int) $_SESSION['company_id'];
$jobId = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($jobId > 0) {
    $conn->query("DELETE FROM jobs WHERE id=$jobId AND company_id=$cid");
}

header("Location: company-dashboard.php");
exit;
