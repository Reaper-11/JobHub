<?php
// company/company-toggle-job.php
require '../db.php';

if (!isset($_SESSION['company_id'])) {
    header("Location: company-login.php");
    exit;
}

$cid = (int)$_SESSION['company_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validate_csrf_token($_POST['csrf_token'] ?? '')) {
    header("Location: company-my-jobs.php");
    exit;
}

$job_id = (int)($_POST['id'] ?? 0);
$status = strtolower(trim($_POST['status'] ?? ''));

if ($job_id > 0 && in_array($status, ['active', 'closed'])) {
    $stmt = $conn->prepare("UPDATE jobs SET status = ? WHERE id = ? AND company_id = ?");
    $stmt->bind_param("sii", $status, $job_id, $cid);
    $stmt->execute();
    $stmt->close();
}

header("Location: company-my-jobs.php");
exit;