<?php
// company/company-delete-job.php
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

if ($job_id > 0) {
    $stmt = $conn->prepare("DELETE FROM jobs WHERE id = ? AND company_id = ?");
    $stmt->bind_param("ii", $job_id, $cid);
    $stmt->execute();
    $stmt->close();
}

header("Location: company-my-jobs.php");
exit;