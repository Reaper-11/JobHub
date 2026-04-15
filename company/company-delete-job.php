<?php
// company/company-delete-job.php
require '../db.php';

require_role('company');

$cid = current_company_id() ?? 0;
$redirectUrl = 'company-my-jobs.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validate_csrf_token($_POST['csrf_token'] ?? '')) {
    jobhub_set_auth_flash('danger', 'Invalid delete request. Please try again.');
    header("Location: {$redirectUrl}");
    exit;
}

$jobId = (int)($_POST['id'] ?? 0);
if ($jobId <= 0) {
    jobhub_set_auth_flash('warning', 'Job not found.');
    header("Location: {$redirectUrl}");
    exit;
}

$jobStmt = $conn->prepare("SELECT id, title FROM jobs WHERE id = ? AND company_id = ? LIMIT 1");
if (!$jobStmt) {
    jobhub_set_auth_flash('danger', 'Could not prepare the delete request. Please try again.');
    header("Location: {$redirectUrl}");
    exit;
}

$jobStmt->bind_param("ii", $jobId, $cid);
$jobStmt->execute();
$job = $jobStmt->get_result()->fetch_assoc();
$jobStmt->close();

if (!$job) {
    jobhub_set_auth_flash('warning', 'Job not found or you do not have permission to delete it.');
    header("Location: {$redirectUrl}");
    exit;
}

$applicationCount = 0;
if (jobhub_table_exists($conn, 'applications') && jobhub_column_exists($conn, 'applications', 'job_id')) {
    $applicationCount = (int)db_query_value(
        "SELECT COUNT(*) FROM applications WHERE job_id = ?",
        "i",
        [$jobId],
        0
    );
}

if ($applicationCount > 0) {
    jobhub_set_auth_flash(
        'warning',
        'This job cannot be deleted because it already has ' . $applicationCount . ' application'
        . ($applicationCount === 1 ? '' : 's') . '. Close the job instead to stop new applications while keeping application history.'
    );
    header("Location: {$redirectUrl}");
    exit;
}

$transactionStarted = false;

try {
    $conn->begin_transaction();
    $transactionStarted = true;

    $jobLinkedTables = [
        'bookmarks' => 'job_id',
        'saved_jobs' => 'job_id',
        'job_views' => 'job_id',
        'job_view_logs' => 'job_id',
        'job_skills' => 'job_id',
    ];

    foreach ($jobLinkedTables as $table => $column) {
        if (!jobhub_table_exists($conn, $table) || !jobhub_column_exists($conn, $table, $column)) {
            continue;
        }

        $stmt = $conn->prepare("DELETE FROM `{$table}` WHERE `{$column}` = ?");
        if (!$stmt) {
            throw new RuntimeException("Could not prepare cleanup for {$table}.");
        }

        $stmt->bind_param("i", $jobId);
        $stmt->execute();
        $stmt->close();
    }

    if (
        jobhub_table_exists($conn, 'notifications')
        && jobhub_column_exists($conn, 'notifications', 'related_type')
        && jobhub_column_exists($conn, 'notifications', 'related_id')
    ) {
        $relatedType = 'job';
        $stmt = $conn->prepare("DELETE FROM notifications WHERE related_type = ? AND related_id = ?");
        if ($stmt) {
            $stmt->bind_param("si", $relatedType, $jobId);
            $stmt->execute();
            $stmt->close();
        }
    }

    $deleteStmt = $conn->prepare("DELETE FROM jobs WHERE id = ? AND company_id = ?");
    if (!$deleteStmt) {
        throw new RuntimeException('Could not prepare job delete.');
    }

    $deleteStmt->bind_param("ii", $jobId, $cid);
    $deleteStmt->execute();
    $deletedRows = $deleteStmt->affected_rows;
    $deleteStmt->close();

    if ($deletedRows < 1) {
        throw new RuntimeException('The job was not deleted.');
    }

    $conn->commit();
    $transactionStarted = false;

    log_activity(
        $conn,
        $cid,
        'company',
        'job_deleted',
        'Company deleted job: ' . (string)($job['title'] ?? 'Job'),
        'job',
        $jobId
    );

    jobhub_set_auth_flash('success', 'Job deleted successfully.');
} catch (Throwable $e) {
    if ($transactionStarted) {
        $conn->rollback();
    }

    error_log('[JobHub][company-delete-job] Delete failed for job #' . $jobId . ': ' . $e->getMessage());

    $message = 'Could not delete this job. Please try again.';
    if ($e instanceof mysqli_sql_exception && (int)$e->getCode() === 1451) {
        $message = 'This job cannot be deleted because it has related records. Close the job instead to keep existing history intact.';
    }

    jobhub_set_auth_flash('danger', $message);
}

header("Location: {$redirectUrl}");
exit;
