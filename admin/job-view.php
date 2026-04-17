<?php
require '../db.php';
require_once '../includes/recommendation.php';

require_role('admin');

$jobId = (int)($_GET['id'] ?? 0);
if ($jobId <= 0) {
    header("Location: admin-jobs.php?status=all&page=1");
    exit;
}

update_expired_jobs($conn, null, $jobId);

$deadlineColumn = job_deadline_column($conn);
$msg = $msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validate_csrf_token($_POST['csrf_token'] ?? '')) {
    $action = trim((string)($_POST['action'] ?? ''));
    $remarks = trim((string)($_POST['remarks'] ?? ''));
    $adminId = current_admin_id() ?? 0;

    // Rejection requires a reason.
    if ($action === 'reject' && $remarks === '') {
        $msg      = 'A rejection reason is required.';
        $msg_type = 'danger';
    } elseif (in_array($action, ['approve', 'reject'], true)) {
        // Validation of existing job
        $reviewSelect = "j.id, j.company_id, j.title, j.status, j.is_approved, j.created_at, j.application_duration";
        if ($deadlineColumn !== null) {
            $reviewSelect .= ", j.{$deadlineColumn}";
        }
        if (job_has_post_date_column($conn)) {
            $reviewSelect .= ", j.post_date";
        }

        $stmt = $conn->prepare("
            SELECT {$reviewSelect}, c.name AS company_name, c.email AS company_email
            FROM jobs j
            LEFT JOIN companies c ON j.company_id = c.id
            WHERE j.id = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $jobId);
        $stmt->execute();
        $jobForAction = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$jobForAction) {
            $msg = "Job not found.";
            $msg_type = 'danger';
        } else {
            $previousApproval = (int)($jobForAction['is_approved'] ?? 0);
            $approvalValue = $action === 'approve' ? 1 : -1;
            $nextStatus = strtolower(trim((string)($jobForAction['status'] ?? 'active')));

            if ($action === 'approve') {
                if ($nextStatus === 'closed') {
                    $nextStatus = 'closed';
                } elseif ($nextStatus === 'expired' || is_job_expired($jobForAction)) {
                    $nextStatus = 'expired';
                } else {
                    $nextStatus = 'active';
                }
            }

            $stmt = $action === 'approve'
                ? $conn->prepare("
                    UPDATE jobs
                    SET is_approved = ?, status = ?, approved_by = ?, approved_at = NOW(), admin_remarks = ?, updated_at = NOW()
                    WHERE id = ?
                ")
                : $conn->prepare("
                    UPDATE jobs
                    SET is_approved = ?, approved_by = ?, approved_at = NOW(), admin_remarks = ?, updated_at = NOW()
                    WHERE id = ?
                ");

            if ($stmt) {
                if ($action === 'approve') {
                    $stmt->bind_param("isisi", $approvalValue, $nextStatus, $adminId, $remarks, $jobId);
                } else {
                    $stmt->bind_param("iisi", $approvalValue, $adminId, $remarks, $jobId);
                }

                $ok = $stmt->execute();
                $stmt->close();

                if ($ok) {
                    if ($action === 'approve' && $nextStatus === 'expired') {
                        $msg = "Job approved, but it is already expired and was not activated.";
                    } else {
                        $msg = $action === 'approve' ? "Job approved successfully." : "Job rejected successfully.";
                    }
                    $msg_type = 'success';

                    if ($action === 'approve' && $previousApproval !== 1 && $nextStatus === 'active') {
                        $matchedSeekers = recommend_matching_seekers_for_job($conn, $jobId);
                        foreach ($matchedSeekers as $match) {
                            $userId = (int)($match['user_id'] ?? 0);
                            if ($userId <= 0) {
                                continue;
                            }

                            $message = 'A new job "' . ($jobForAction['title'] ?? 'Job') . '" matches your profile.';
                            $reasons = $match['reasons'] ?? [];
                            if (!empty($reasons)) {
                                $message .= ' Match reason: ' . ucfirst($reasons[0]) . '.';
                            }

                            notify_create_unique(
                                'user',
                                $userId,
                                'New Job Match Found',
                                $message,
                                'job-detail.php?id=' . $jobId,
                                'info',
                                'job',
                                $jobId
                            );
                        }
                    }

                    log_activity(
                        $conn,
                        $adminId,
                        'admin',
                        $action === 'approve' ? 'job_approved' : 'job_rejected',
                        $action === 'approve'
                            ? "Admin approved job {$jobForAction['title']}"
                            : "Admin rejected job {$jobForAction['title']}",
                        'job',
                        $jobId
                    );

                    $companyId = (int) ($jobForAction['company_id'] ?? 0);
                    if ($companyId > 0) {
                        $notificationTitle = $action === 'approve'
                            ? 'Job Approved'
                            : 'Job Rejected';
                        $notificationMessage = $action === 'approve'
                            ? 'Your job "' . ($jobForAction['title'] ?? 'Job') . '" has been approved by admin.'
                            : 'Your job "' . ($jobForAction['title'] ?? 'Job') . '" has been rejected by admin.';

                        if ($action === 'approve' && $nextStatus !== 'active') {
                            $publishNote = $nextStatus === 'expired'
                                ? ' The job is approved, but it is already expired and is not active.'
                                : ' The job is approved, but its current status is ' . job_status_label($nextStatus) . '.';
                            $notificationMessage .= $publishNote;
                        }

                        if ($remarks !== '') {
                            $notificationMessage .= "\n\nAdmin remarks: " . $remarks;
                        }

                        notify_create(
                            'company',
                            $companyId,
                            $notificationTitle,
                            $notificationMessage,
                            'company-my-jobs.php',
                            $action === 'approve' ? 'success' : 'danger',
                            'job',
                            $jobId
                        );
                    }

                    $companyEmail = trim((string) ($jobForAction['company_email'] ?? ''));
                    if ($companyEmail !== '') {
                        $jobMailAction = $action === 'approve' ? 'approved' : 'rejected';
                        $jobMailRemarks = $remarks;

                        if ($action === 'approve' && $nextStatus !== 'active') {
                            $publishNote = $nextStatus === 'expired'
                                ? 'This job has already passed its application window, so it is not active on JobHub.'
                                : 'This job is approved, but its current status is ' . job_status_label($nextStatus) . '.';
                            $jobMailRemarks = $jobMailRemarks !== ''
                                ? $jobMailRemarks . "\n\n" . $publishNote
                                : $publishNote;
                        }

                        try {
                            $mailResult = jobhub_send_job_review_email(
                                $companyEmail,
                                (string) ($jobForAction['company_name'] ?? ''),
                                (string) ($jobForAction['company_name'] ?? ''),
                                (string) ($jobForAction['title'] ?? ''),
                                $jobMailAction,
                                $jobMailRemarks
                            );

                            if (empty($mailResult['success'])) {
                                $mailMessage = trim((string) ($mailResult['message'] ?? ''));
                                jobhub_log_mail_error(
                                    'job-review',
                                    'Job #' . $jobId . ' review email (' . $jobMailAction . ') failed for ' . $companyEmail . ': '
                                    . ($mailMessage !== '' ? $mailMessage : 'Unknown mail error.')
                                );
                            }
                        } catch (Throwable $mailException) {
                            jobhub_log_mail_error(
                                'job-review',
                                'Job #' . $jobId . ' review email (' . $jobMailAction . ') threw an exception for '
                                . $companyEmail . ': ' . $mailException->getMessage()
                            );
                        }
                    }
                } else {
                    $msg = "Could not update the job approval status.";
                    $msg_type = 'danger';
                }
            } else {
                $msg = "Could not prepare the job review action.";
                $msg_type = 'danger';
            }
        }
    }
}

// Fetch complete job details for display
$stmt = $conn->prepare("
    SELECT j.*, c.name AS company_name
    FROM jobs j
    LEFT JOIN companies c ON c.id = j.company_id
    WHERE j.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $jobId);
$stmt->execute();
$result = $stmt->get_result();
$job = $result ? $result->fetch_assoc() : null;
$stmt->close();

require 'admin-header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="mb-0">Job Review</h1>
    <a href="admin-jobs.php?status=all&page=1" class="btn btn-outline-secondary">&laquo; Back to Jobs</a>
</div>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show">
        <?= htmlspecialchars($msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (!$job): ?>
    <div class="alert alert-danger">Job not found.</div>
<?php else: ?>
    <?php $isRejected = (int)$job['is_approved'] === -1; ?>
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header pt-3 pb-2">
                    <h2 class="h5 mb-0">Job Information</h2>
                </div>
                <div class="card-body">
                    <h3 class="h4 text-primary mb-3"><?= htmlspecialchars($job['title']) ?></h3>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <ul class="list-unstyled mb-0">
                                <li class="mb-2"><strong>Company:</strong> <?= htmlspecialchars($job['company_name'] ?: ($job['company'] ?: '-')) ?></li>
                                <li class="mb-2"><strong>Category:</strong> <?= htmlspecialchars($job['category'] ?: '-') ?></li>
                                <li class="mb-2"><strong>Location:</strong> <?= htmlspecialchars($job['location'] ?: '-') ?></li>
                                <li class="mb-2"><strong>Job Type:</strong> <?= htmlspecialchars($job['type'] ?: '-') ?></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="list-unstyled mb-0">
                                <li class="mb-2"><strong>Status:</strong> <span class="badge <?= job_status_badge_class($job) ?>"><?= htmlspecialchars(job_status_label($job)) ?></span></li>
                                <li class="mb-2"><strong>Approval:</strong> <span class="badge <?= job_approval_badge_class((int)$job['is_approved']) ?>"><?= job_approval_label((int)$job['is_approved']) ?></span></li>
                                <li class="mb-2"><strong>Created:</strong> <?= date('Y-m-d H:i', strtotime($job['created_at'])) ?></li>
                            </ul>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <ul class="list-unstyled mb-0">
                                <li class="mb-2"><strong>Salary:</strong> <?= htmlspecialchars($job['salary'] ?: '-') ?></li>
                                <?php if (!empty($job['salary_min']) || !empty($job['salary_max'])): ?>
                                    <li class="mb-2"><strong>Salary Range:</strong> <?= htmlspecialchars($job['salary_min'] ?? '0') ?> - <?= htmlspecialchars($job['salary_max'] ?? '0') ?> <?= htmlspecialchars($job['salary_currency'] ?? '') ?> (<?= htmlspecialchars($job['salary_period'] ?? '') ?>)</li>
                                <?php endif; ?>
                                <li class="mb-2"><strong>Experience Level:</strong> <?= htmlspecialchars($job['experience_level'] ?: '-') ?></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="list-unstyled mb-0">
                                <li class="mb-2"><strong>Application Duration:</strong> <?= htmlspecialchars($job['application_duration'] ?: '-') ?></li>
                                <?php if ($deadlineColumn !== null && !empty($job[$deadlineColumn])): ?>
                                    <li class="mb-2"><strong>Deadline:</strong> <?= date('Y-m-d', strtotime($job[$deadlineColumn])) ?></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                    
                    <?php if (!empty($job['skills_required'])): ?>
                        <div class="mb-4">
                            <h4 class="h6 fw-bold">Skills Required</h4>
                            <div class="p-3 content-surface rounded">
                                <?= nl2br(htmlspecialchars($job['skills_required'])) ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="mb-4">
                        <h4 class="h6 fw-bold">Description</h4>
                        <div class="p-3 content-surface rounded" style="white-space: pre-wrap;"><?= htmlspecialchars($job['description']) ?></div>
                    </div>

                    <?php if (!empty($job['admin_remarks'])): ?>
                        <div class="mb-0">
                            <h4 class="h6 fw-bold text-muted">Previous Admin Remarks</h4>
                            <div class="p-3 content-surface rounded text-muted">
                                <?= nl2br(htmlspecialchars($job['admin_remarks'])) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 sticky-top" style="top: 20px;">
                <div class="card-header pt-4 pb-0 border-bottom-0">
                    <h4 class="h5 mb-0">Take Action</h4>
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>">
                        
                        <div class="mb-4">
                            <label class="form-label fw-medium">Admin Remarks</label>
                            <textarea name="remarks" class="form-control" rows="5" placeholder="Enter remarks (required for rejection)"><?= htmlspecialchars($job['admin_remarks'] ?? '') ?></textarea>
                            <div class="form-text">These remarks are visible to the company.</div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" name="action" value="approve" class="btn btn-success py-2">
                                Approve Job
                            </button>
                            <?php if (!$isRejected): ?>
                                <button type="submit" name="action" value="reject" class="btn btn-danger py-2">
                                    Reject Job
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require '../footer.php'; ?>
