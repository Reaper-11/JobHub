<?php
// company/company-delete-job.php
require '../db.php';

require_role('company');

$cid = current_company_id() ?? 0;

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
