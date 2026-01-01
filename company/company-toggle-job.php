<?php
require '../db.php';

if (!isset($_SESSION['company_id'])) {
    header("Location: company-login.php");
    exit;
}

$cid = (int) $_SESSION['company_id'];
$jobId = (int) ($_POST['id'] ?? 0);
$status = strtolower(trim((string) ($_POST['status'] ?? '')));

if ($jobId > 0 && in_array($status, ['active', 'closed'], true)) {
    if ($stmt = $conn->prepare("UPDATE jobs SET status = ? WHERE id = ? AND company_id = ?")) {
        $stmt->bind_param("sii", $status, $jobId, $cid);
        $stmt->execute();
        $stmt->close();
    }
}

header("Location: company-dashboard.php");
exit;
