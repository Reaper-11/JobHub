<?php
// company/company-toggle-job.php
require '../db.php';

require_role('company');

$cid = current_company_id() ?? 0;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validate_csrf_token($_POST['csrf_token'] ?? '')) {
    header("Location: company-my-jobs.php");
    exit;
}

$jobId = (int)($_POST['id'] ?? 0);
$requestedStatus = strtolower(trim((string)($_POST['status'] ?? '')));

if ($jobId > 0 && in_array($requestedStatus, ['active', 'closed'], true)) {
    update_expired_jobs($conn, $cid, $jobId);

    $deadlineColumn = job_deadline_column($conn);
    $selectColumns = "id, status, application_duration, created_at";
    if ($deadlineColumn !== null) {
        $selectColumns .= ", {$deadlineColumn}";
    }
    if (job_has_post_date_column($conn)) {
        $selectColumns .= ", post_date";
    }

    $jobStmt = $conn->prepare("
        SELECT {$selectColumns}
        FROM jobs
        WHERE id = ? AND company_id = ?
        LIMIT 1
    ");

    if ($jobStmt) {
        $jobStmt->bind_param("ii", $jobId, $cid);
        $jobStmt->execute();
        $job = $jobStmt->get_result()->fetch_assoc();
        $jobStmt->close();

        if ($job) {
            $nextStatus = $requestedStatus;

            if ($requestedStatus === 'active' && (strtolower((string)($job['status'] ?? '')) === 'expired' || is_job_expired($job))) {
                $nextStatus = 'expired';
            }

            $stmt = $conn->prepare("UPDATE jobs SET status = ?, updated_at = NOW() WHERE id = ? AND company_id = ?");
            if ($stmt) {
                $stmt->bind_param("sii", $nextStatus, $jobId, $cid);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}

header("Location: company-my-jobs.php");
exit;
