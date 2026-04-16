<?php
// company/company-my-jobs.php
require '../db.php';
require_role('company');
$cid = current_company_id() ?? 0;

update_expired_jobs($conn, $cid);

$deadlineColumn = job_deadline_column($conn);
$jobSelect = "j.id, j.title, j.location, j.type, j.status, j.is_approved, j.admin_remarks, j.created_at,
              COALESCE(application_stats.application_count, 0) AS application_count, j.application_duration";
if ($deadlineColumn !== null) {
    $jobSelect .= ", j.{$deadlineColumn}";
}
if (job_has_post_date_column($conn)) {
    $jobSelect .= ", j.post_date";
}

$statusFilter = strtolower(trim($_GET['status'] ?? 'all'));
$allowedFilters = ['all', 'active', 'pending', 'rejected', 'closed', 'expired'];
if (!in_array($statusFilter, $allowedFilters, true)) {
    $statusFilter = 'all';
}

$whereClauses = ['j.company_id = ?'];
$params = [$cid];
$types = 'i';

switch ($statusFilter) {
    case 'active':
        $whereClauses[] = "j.status = 'active'";
        $whereClauses[] = "j.is_approved = 1";
        break;
    case 'pending':
        $whereClauses[] = 'j.is_approved = 0';
        $whereClauses[] = "j.status <> 'draft'";
        break;
    case 'rejected':
        $whereClauses[] = 'j.is_approved = -1';
        break;
    case 'closed':
        $whereClauses[] = "j.status = 'closed'";
        break;
    case 'expired':
        $whereClauses[] = "j.status = 'expired'";
        break;
}

$jobs = db_query_all("
    SELECT {$jobSelect}
    FROM jobs j
    LEFT JOIN (
        SELECT job_id, COUNT(*) AS application_count
        FROM applications
        GROUP BY job_id
    ) AS application_stats ON application_stats.job_id = j.id
    WHERE " . implode(' AND ', $whereClauses) . "
    ORDER BY j.created_at DESC
", $types, $params);
?>

<?php require 'company-header.php'; ?>

<h1 class="mb-4">My Posted Jobs</h1>

<?php if (!$isApproved): ?>
    <div class="alert alert-warning">
        Your account is not yet approved. Jobs are not visible to the public.
    </div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="get" class="d-flex gap-3 align-items-center">
            <label class="form-label mb-0">Filter by status:</label>
            <select name="status" class="form-select w-auto" onchange="this.form.submit()">
                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All</option>
                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                <option value="closed" <?= $statusFilter === 'closed' ? 'selected' : '' ?>>Closed</option>
                <option value="expired" <?= $statusFilter === 'expired' ? 'selected' : '' ?>>Expired</option>
            </select>
            <a href="company-my-jobs.php" class="btn btn-outline-secondary btn-sm">Reset</a>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Title</th>
                        <th>Location</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Approval</th>
                        <th>Applications</th>
                        <th>Posted</th>
                        <th>Expiry</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($jobs)): ?>
                    <tr><td colspan="9" class="text-center py-5 text-muted">No jobs found.</td></tr>
                <?php else: ?>
                    <?php foreach ($jobs as $job): ?>
                        <?php
                        $effectiveStatus = job_effective_status($job);
                        $approvalStatus = (int)$job['is_approved'];
                        $canReopen = $effectiveStatus === 'closed' && !is_job_expired($job);
                        $expirationTimestamp = job_expiration_timestamp($job);
                        
                        // Expiry styling - Green for active, Red for expired
                        $expiryHtml = '<span class="text-muted small">Ongoing</span>';
                        $expiryClass = 'text-muted small';

                        if ($expirationTimestamp !== null) {
                            $expiryDate = date('M d, Y', $expirationTimestamp);
                            $secondsLeft = $expirationTimestamp - time();
                            $daysLeft = ceil($secondsLeft / 86400);
                            
                            if ($secondsLeft < 0) {
                                // Expired - RED
                                $expiryHtml = '<div><span class="fw-bold text-danger">Expired</span></div>';
                                $expiryHtml .= '<small class="text-muted">' . $expiryDate . '</small>';
                                $expiryClass = 'text-danger small';
                            } else {
                                // Active - GREEN
                                $expiryHtml = '<div><span class="fw-bold text-success">Expires in ' . $daysLeft . 'd</span></div>';
                                $expiryHtml .= '<small class="text-muted">' . $expiryDate . '</small>';
                                $expiryClass = 'text-success small';
                            }
                        } elseif ($effectiveStatus === 'expired') {
                            // Expired - RED
                            $expiryHtml = '<span class="fw-bold text-danger">Expired</span>';
                            $expiryClass = 'text-danger small';
                        }
                        ?>
                        <tr style="border-bottom: 1px solid #e9ecef;">
                            <td style="padding: 1rem 0.75rem;">
                                <a href="company-edit-job.php?id=<?= (int)$job['id'] ?>" class="text-decoration-none" style="font-weight: 600; color: #ffffff;">
                                    <?= htmlspecialchars($job['title']) ?>
                                </a>
                            </td>
                            <td style="padding: 1rem 0.75rem; color: #9ca3af; font-size: 0.9rem;">
                                <?= htmlspecialchars($job['location'] ?: '-') ?>
                            </td>
                            <td style="padding: 1rem 0.75rem; color: #9ca3af; font-size: 0.9rem;">
                                <?= htmlspecialchars($job['type'] ?: 'Full-time') ?>
                            </td>
                            <td style="padding: 1rem 0.75rem;">
                                <span class="badge <?= job_status_badge_class($job) ?>">
                                    <?= htmlspecialchars(job_status_label($job)) ?>
                                </span>
                            </td>
                            <td style="padding: 1rem 0.75rem;">
                                <span class="badge <?= job_approval_badge_class($approvalStatus) ?>">
                                    <?= job_approval_label($approvalStatus) ?>
                                </span>
                            </td>
                            <td style="padding: 1rem 0.75rem; text-align: center;">
                                <span style="color: #ffffff; font-weight: 500;">
                                    <?= number_format($job['application_count'] ?? 0) ?>
                                </span>
                            </td>
                            <td style="padding: 1rem 0.75rem; color: #9ca3af; font-size: 0.9rem;">
                                <?= date('M d, Y', strtotime($job['created_at'])) ?>
                            </td>
                            <td style="padding: 1rem 0.75rem;" class="<?= htmlspecialchars($expiryClass) ?>">
                                <?= $expiryHtml ?>
                            </td>
                            <td style="padding: 1rem 0.75rem; vertical-align: middle;">
                                <div class="d-flex flex-wrap gap-2">
                                    <!-- Edit Button (always shown) -->
                                    <a href="company-edit-job.php?id=<?= (int)$job['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit job">
                                        <i class="fas fa-edit me-1"></i>Edit
                                    </a>

                                    <!-- View Applications (shown for active, closed, expired with applications) -->
                                    <?php if (in_array($effectiveStatus, ['active', 'closed', 'expired'])): ?>
                                        <a href="company-applications.php?job_id=<?= (int)$job['id'] ?>" class="btn btn-sm btn-outline-info" title="View applications">
                                            <i class="fas fa-users me-1"></i>Applicants
                                        </a>
                                    <?php endif; ?>

                                    <!-- Close Button (only for active) -->
                                    <?php if ($effectiveStatus === 'active'): ?>
                                        <form method="post" action="company-toggle-job.php" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                            <input type="hidden" name="id" value="<?= (int)$job['id'] ?>">
                                            <input type="hidden" name="status" value="closed">
                                            <button
                                                type="submit"
                                                class="btn btn-sm btn-outline-warning"
                                                title="Close job"
                                                onclick="return confirm('Close this job? No more applications will be accepted.')"
                                            >
                                                <i class="fas fa-pause me-1"></i>Close
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <!-- Reopen Button (only for closed) -->
                                    <?php if ($canReopen): ?>
                                        <form method="post" action="company-toggle-job.php" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                            <input type="hidden" name="id" value="<?= (int)$job['id'] ?>">
                                            <input type="hidden" name="status" value="active">
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Reopen job">
                                                <i class="fas fa-play me-1"></i>Reopen
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <!-- Delete Button (always shown) -->
                                    <form method="post" action="company-delete-job.php" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                        <input type="hidden" name="id" value="<?= (int)$job['id'] ?>">
                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-outline-danger"
                                            title="Delete job"
                                            onclick="return confirm('Delete this job permanently? This cannot be undone.')"
                                        >
                                            <i class="fas fa-trash me-1"></i>Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require '../footer.php'; ?>
