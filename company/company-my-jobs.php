<?php
// company/company-my-jobs.php
require '../db.php';
require_role('company');
$cid = current_company_id() ?? 0;

update_expired_jobs($conn, $cid);

$deadlineColumn = job_deadline_column($conn);
$jobSelect = "id, title, location, type, status, is_approved, admin_remarks, created_at, application_count, application_duration";
if ($deadlineColumn !== null) {
    $jobSelect .= ", {$deadlineColumn}";
}
if (job_has_post_date_column($conn)) {
    $jobSelect .= ", post_date";
}

$statusFilter = strtolower(trim($_GET['status'] ?? 'all'));
$allowedFilters = ['all', 'active', 'pending', 'rejected', 'closed', 'draft', 'expired'];
if (!in_array($statusFilter, $allowedFilters, true)) {
    $statusFilter = 'all';
}

$whereClauses = ['company_id = ?'];
$params = [$cid];
$types = 'i';

switch ($statusFilter) {
    case 'active':
        $whereClauses[] = "status = 'active'";
        $whereClauses[] = "is_approved = 1";
        break;
    case 'pending':
        $whereClauses[] = 'is_approved = 0';
        $whereClauses[] = "status <> 'draft'";
        break;
    case 'rejected':
        $whereClauses[] = 'is_approved = -1';
        break;
    case 'closed':
        $whereClauses[] = "status = 'closed'";
        break;
    case 'draft':
        $whereClauses[] = "status = 'draft'";
        break;
    case 'expired':
        $whereClauses[] = "status = 'expired'";
        break;
}

$jobs = db_query_all("
    SELECT {$jobSelect}
    FROM jobs
    WHERE " . implode(' AND ', $whereClauses) . "
    ORDER BY created_at DESC
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
                <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Draft</option>
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
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($jobs)): ?>
                    <tr><td colspan="8" class="text-center py-5 text-muted">No jobs found.</td></tr>
                <?php else: ?>
                    <?php foreach ($jobs as $job): ?>
                        <?php
                        $effectiveStatus = job_effective_status($job);
                        $canReopen = $effectiveStatus === 'closed' && !is_job_expired($job);
                        ?>
                        <tr>
                            <td>
                                <a href="company-edit-job.php?id=<?= (int)$job['id'] ?>" class="text-decoration-none">
                                    <?= htmlspecialchars($job['title']) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($job['location'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($job['type'] ?: 'Full-time') ?></td>
                            <td>
                                <span class="badge <?= job_status_badge_class($job) ?>">
                                    <?= htmlspecialchars(job_status_label($job)) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= job_approval_badge_class((int)$job['is_approved']) ?>">
                                    <?= job_approval_label((int)$job['is_approved']) ?>
                                </span>
                                <?php if (!empty($job['admin_remarks'])): ?>
                                    <div class="small text-muted mt-1"><?= htmlspecialchars($job['admin_remarks']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= number_format($job['application_count'] ?? 0) ?></td>
                            <td><?= date('M d, Y', strtotime($job['created_at'])) ?></td>
                            <td class="text-nowrap">
                                <a href="company-edit-job.php?id=<?= (int)$job['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>

                                <a href="company-applications.php?job_id=<?= (int)$job['id'] ?>" class="btn btn-sm btn-outline-info">View Applications</a>

                                <?php if ($effectiveStatus === 'active'): ?>
                                    <form method="post" action="company-toggle-job.php" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                        <input type="hidden" name="id" value="<?= (int)$job['id'] ?>">
                                        <input type="hidden" name="status" value="closed">
                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-outline-warning"
                                            onclick="return confirm('Close this job? No more applications will be accepted.')"
                                        >
                                            Close
                                        </button>
                                    </form>
                                <?php elseif ($canReopen): ?>
                                    <form method="post" action="company-toggle-job.php" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                        <input type="hidden" name="id" value="<?= (int)$job['id'] ?>">
                                        <input type="hidden" name="status" value="active">
                                        <button type="submit" class="btn btn-sm btn-outline-success">
                                            Reopen
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <form method="post" action="company-delete-job.php" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                    <input type="hidden" name="id" value="<?= (int)$job['id'] ?>">
                                    <button
                                        type="submit"
                                        class="btn btn-sm btn-danger"
                                        onclick="return confirm('Delete this job permanently? This cannot be undone.')"
                                    >
                                        Delete
                                    </button>
                                </form>
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
