<?php
require '../db.php';
require_once '../includes/recommendation.php';

require_role('admin');

update_expired_jobs($conn);

$deadlineColumn = job_deadline_column($conn);
$jobListSelect = "j.id, j.title, j.category, j.status, j.is_approved, j.admin_remarks, j.created_at, j.application_duration";
if ($deadlineColumn !== null) {
    $jobListSelect .= ", j.{$deadlineColumn}";
}
if (job_has_post_date_column($conn)) {
    $jobListSelect .= ", j.post_date";
}

$jobApprovalBaseUrl = 'admin-jobs.php';
$statusFilter = strtolower(trim((string)($_GET['status'] ?? ($_GET['approval'] ?? 'all'))));
if (!in_array($statusFilter, ['all', 'pending', 'approved', 'rejected'], true)) {
    $statusFilter = 'all';
}
$companySearch = trim((string)($_GET['company'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = $statusFilter === 'all' ? 30 : 20;
$offset = ($page - 1) * $limit;

$msg = $msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validate_csrf_token($_POST['csrf_token'] ?? '')) {
    $jobId  = (int)($_POST['job_id'] ?? 0);
    $action = trim((string)($_POST['action'] ?? ''));
    $remarks = trim((string)($_POST['remarks'] ?? ''));
    $adminId = current_admin_id() ?? 0;

    // Rejection requires a reason.
    if ($jobId > 0 && $action === 'reject' && $remarks === '') {
        $msg      = 'A rejection reason is required.';
        $msg_type = 'danger';
    } elseif ($jobId > 0 && in_array($action, ['approve', 'reject'], true)) {
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
        $job = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$job) {
            $msg = "Job not found.";
            $msg_type = 'danger';
        } else {
            $previousApproval = (int)($job['is_approved'] ?? 0);
            $approvalValue = $action === 'approve' ? 1 : -1;
            $nextStatus = strtolower(trim((string)($job['status'] ?? 'active')));

            if ($action === 'approve') {
                if ($nextStatus === 'closed') {
                    $nextStatus = 'closed';
                } elseif ($nextStatus === 'expired' || is_job_expired($job)) {
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

                            $message = 'A new job "' . ($job['title'] ?? 'Job') . '" matches your profile.';
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
                            ? "Admin approved job {$job['title']}"
                            : "Admin rejected job {$job['title']}",
                        'job',
                        $jobId
                    );

                    $companyId = (int) ($job['company_id'] ?? 0);
                    if ($companyId > 0) {
                        $notificationTitle = $action === 'approve'
                            ? 'Job Approved'
                            : 'Job Rejected';
                        $notificationMessage = $action === 'approve'
                            ? 'Your job "' . ($job['title'] ?? 'Job') . '" has been approved by admin.'
                            : 'Your job "' . ($job['title'] ?? 'Job') . '" has been rejected by admin.';

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

                    $companyEmail = trim((string) ($job['company_email'] ?? ''));
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
                                (string) ($job['company_name'] ?? ''),
                                (string) ($job['company_name'] ?? ''),
                                (string) ($job['title'] ?? ''),
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

$conditions = [];

if ($statusFilter === 'pending') {
    $conditions[] = "j.is_approved = 0";
} elseif ($statusFilter === 'approved') {
    $conditions[] = "j.is_approved = 1";
} elseif ($statusFilter === 'rejected') {
    $conditions[] = "j.is_approved = -1";
}

$filterTypes = '';
$filterParams = [];
if ($companySearch !== '') {
    $conditions[] = "c.name LIKE ?";
    $filterTypes .= 's';
    $filterParams[] = '%' . $companySearch . '%';
}

$where = empty($conditions) ? '1=1' : implode(' AND ', $conditions);

$counts = [
    'all' => (int)db_query_value("SELECT COUNT(*) FROM jobs", '', [], 0),
    'pending' => (int)db_query_value("SELECT COUNT(*) FROM jobs WHERE is_approved = 0", '', [], 0),
    'approved' => (int)db_query_value("SELECT COUNT(*) FROM jobs WHERE is_approved = 1", '', [], 0),
    'rejected' => (int)db_query_value("SELECT COUNT(*) FROM jobs WHERE is_approved = -1", '', [], 0),
];

$totalJobs = (int)db_query_value("
    SELECT COUNT(*)
    FROM jobs j
    LEFT JOIN companies c ON j.company_id = c.id
    WHERE {$where}
", $filterTypes, $filterParams, 0);
$totalPages = $totalJobs > 0 ? (int)ceil($totalJobs / $limit) : 1;

if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $limit;
}

$jobs = db_query_all("
    SELECT {$jobListSelect}, c.name AS company_name
    FROM jobs j
    LEFT JOIN companies c ON j.company_id = c.id
    WHERE {$where}
    ORDER BY j.created_at DESC
    LIMIT ? OFFSET ?
 ", $filterTypes . "ii", array_merge($filterParams, [$limit, $offset]));

$buildJobApprovalUrl = static function (string $status, int $targetPage, string $companyTerm = '') use ($jobApprovalBaseUrl): string {
    $params = [
        'status' => $status,
        'page' => max(1, $targetPage),
    ];

    if ($companyTerm !== '') {
        $params['company'] = $companyTerm;
    }

    return $jobApprovalBaseUrl . '?' . http_build_query($params);
};

$tabUrl = static function (string $status) use ($buildJobApprovalUrl, $companySearch): string {
    return $buildJobApprovalUrl($status, 1, $companySearch);
};

$pageUrl = static function (int $targetPage) use ($buildJobApprovalUrl, $statusFilter, $companySearch): string {
    return $buildJobApprovalUrl($statusFilter, $targetPage, $companySearch);
};

$clearSearchUrl = $buildJobApprovalUrl($statusFilter, 1);
?>

<?php require 'admin-header.php'; ?>

<h1 class="mb-4">Manage Jobs</h1>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm border-0">
            <div class="card-body text-center">
                <div class="text-muted small">Pending Jobs</div>
                <h3 class="mb-0"><?= (int)$counts['pending'] ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0">
            <div class="card-body text-center">
                <div class="text-muted small">Approved Jobs</div>
                <h3 class="mb-0 text-success"><?= (int)$counts['approved'] ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0">
            <div class="card-body text-center">
                <div class="text-muted small">Rejected Jobs</div>
                <h3 class="mb-0 text-danger"><?= (int)$counts['rejected'] ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
    <ul class="nav nav-tabs mb-0 flex-wrap">
        <li class="nav-item"><a class="nav-link <?= $statusFilter === 'all' ? 'active' : '' ?>" href="<?= htmlspecialchars($tabUrl('all')) ?>">All <span class="badge text-bg-secondary ms-1"><?= (int)$counts['all'] ?></span></a></li>
        <li class="nav-item"><a class="nav-link <?= $statusFilter === 'pending' ? 'active' : '' ?>" href="<?= htmlspecialchars($tabUrl('pending')) ?>">Pending <span class="badge bg-warning text-dark ms-1"><?= (int)$counts['pending'] ?></span></a></li>
        <li class="nav-item"><a class="nav-link <?= $statusFilter === 'approved' ? 'active' : '' ?>" href="<?= htmlspecialchars($tabUrl('approved')) ?>">Approved <span class="badge bg-success ms-1"><?= (int)$counts['approved'] ?></span></a></li>
        <li class="nav-item"><a class="nav-link <?= $statusFilter === 'rejected' ? 'active' : '' ?>" href="<?= htmlspecialchars($tabUrl('rejected')) ?>">Rejected <span class="badge bg-danger ms-1"><?= (int)$counts['rejected'] ?></span></a></li>
    </ul>

    <form method="get" class="d-flex align-items-center gap-2 flex-wrap">
        <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
        <input type="hidden" name="page" value="1">
        <input
            type="search"
            name="company"
            class="form-control form-control-sm"
            placeholder="Search company"
            value="<?= htmlspecialchars($companySearch) ?>"
            style="width: 220px;"
        >
        <button type="submit" class="btn btn-sm btn-outline-primary">Search</button>
        <?php if ($companySearch !== ''): ?>
            <a href="<?= htmlspecialchars($clearSearchUrl) ?>" class="btn btn-sm btn-outline-secondary">Clear</a>
        <?php endif; ?>
    </form>
</div>

<?php if ($companySearch !== ''): ?>
    <div class="small text-muted mb-3">
        Showing <?= (int)$totalJobs ?> matching job<?= $totalJobs === 1 ? '' : 's' ?> for company "<span class="text-light"><?= htmlspecialchars($companySearch) ?></span>".
    </div>
<?php endif; ?>

<div class="table-responsive">
    <table class="table table-hover table-striped align-middle">
        <thead class="table-light">
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Company</th>
                <th>Category</th>
                <th>Job Status</th>
                <th>Approval</th>
                <th>Created</th>
                <?php if ($deadlineColumn !== null): ?><th>Deadline</th><?php endif; ?>
                <th>Remarks</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($jobs)): ?>
            <tr><td colspan="<?= $deadlineColumn !== null ? 10 : 9 ?>" class="text-center py-4">No jobs found.</td></tr>
        <?php else: ?>
            <?php foreach ($jobs as $job): ?>
                <tr>
                    <td><?= (int)$job['id'] ?></td>
                    <td>
                        <div class="fw-semibold"><?= htmlspecialchars($job['title']) ?></div>
                        <a href="job-view.php?id=<?= (int)$job['id'] ?>" class="small text-decoration-none">View details</a>
                    </td>
                    <td><?= htmlspecialchars($job['company_name'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($job['category'] ?: '-') ?></td>
                    <td>
                        <span class="badge <?= job_status_badge_class($job) ?>">
                            <?= htmlspecialchars(job_status_label($job)) ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge <?= job_approval_badge_class((int)$job['is_approved']) ?>">
                            <?= job_approval_label((int)$job['is_approved']) ?>
                        </span>
                    </td>
                    <td><?= date('Y-m-d', strtotime($job['created_at'])) ?></td>
                    <?php if ($deadlineColumn !== null): ?>
                        <td>
                            <?php
                            $deadlineVal = '-';
                            if (!empty($job[$deadlineColumn])) {
                                $deadlineVal = date('Y-m-d', strtotime($job[$deadlineColumn]));
                                $isExpired = strtotime($job[$deadlineColumn]) < time();
                                $deadlineVal = $isExpired
                                    ? '<span class="text-danger">' . htmlspecialchars($deadlineVal) . ' <small>(Expired)</small></span>'
                                    : htmlspecialchars($deadlineVal);
                            }
                            echo $deadlineVal;
                            ?>
                        </td>
                    <?php endif; ?>
                    <td><?= htmlspecialchars($job['admin_remarks'] ?: '-') ?></td>
                    <td style="min-width: 160px;">
                        <a href="job-view.php?id=<?= (int)$job['id'] ?>" class="btn btn-sm btn-primary w-100 mb-2">View Job</a>
                        <?php $isRejected = (int)$job['is_approved'] === -1; ?>
                        <!-- Approve form -->
                        <form method="post" class="d-grid gap-1 mb-1">
                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                            <input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>">
                            <textarea name="remarks" class="form-control form-control-sm" rows="2" placeholder="Admin remarks (optional)"><?= htmlspecialchars($job['admin_remarks'] ?? '') ?></textarea>
                            <button type="submit" name="action" value="approve" class="btn btn-sm btn-success w-100">Approve</button>
                        </form>
                        <!-- Reject (modal trigger) -->
                        <?php if (!$isRejected): ?>
                            <button type="button" class="btn btn-sm btn-danger w-100"
                                    data-bs-toggle="modal" data-bs-target="#rejectJobModal<?= (int)$job['id'] ?>">
                                Reject
                            </button>
                            <div class="modal fade" id="rejectJobModal<?= (int)$job['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <form method="post" class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Reject Job</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                            <input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <p class="mb-3">Reject "<strong><?= htmlspecialchars($job['title']) ?></strong>"?</p>
                                            <div class="mb-0">
                                                <label class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                                                <textarea name="remarks" class="form-control" rows="3" required placeholder="Enter reason for rejection…"></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-danger">Confirm Reject</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
    <nav class="mt-4" aria-label="Job approval pagination">
        <ul class="pagination justify-content-end mb-0">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= $page > 1 ? htmlspecialchars($pageUrl($page - 1)) : '#' ?>">Previous</a>
            </li>
            <li class="page-item disabled">
                <span class="page-link">Page <?= (int)$page ?> of <?= (int)$totalPages ?></span>
            </li>
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= $page < $totalPages ? htmlspecialchars($pageUrl($page + 1)) : '#' ?>">Next</a>
            </li>
        </ul>
    </nav>
<?php endif; ?>

<?php require '../footer.php'; ?>
