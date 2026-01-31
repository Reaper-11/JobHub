<?php
// company/company-my-jobs.php
require '../db.php';

if (!isset($_SESSION['company_id'])) {
    header("Location: company-login.php");
    exit;
}

$cid = (int)$_SESSION['company_id'];

// Optional filters (you can expand later)
$statusFilter = $_GET['status'] ?? 'all';
$where = $statusFilter === 'all' ? "1=1" : "status = ?";

$params = $statusFilter === 'all' ? [] : [$statusFilter];
$types  = $statusFilter === 'all' ? "" : "s";

$jobs = db_query_all("
    SELECT id, title, location, type, status, created_at, application_count
    FROM jobs 
    WHERE company_id = ? AND $where
    ORDER BY created_at DESC
", "i" . $types, array_merge([$cid], $params));
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
                <option value="active"   <?= $statusFilter === 'active'   ? 'selected' : '' ?>>Active</option>
                <option value="closed"   <?= $statusFilter === 'closed'   ? 'selected' : '' ?>>Closed</option>
                <option value="draft"    <?= $statusFilter === 'draft'    ? 'selected' : '' ?>>Draft</option>
                <option value="expired"  <?= $statusFilter === 'expired'  ? 'selected' : '' ?>>Expired</option>
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
                        <th>Applications</th>
                        <th>Posted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($jobs)): ?>
                    <tr><td colspan="7" class="text-center py-5 text-muted">No jobs found.</td></tr>
                <?php else: ?>
                    <?php foreach ($jobs as $job): ?>
                        <tr>
                            <td>
                                <a href="company-edit-job.php?id=<?= $job['id'] ?>" class="text-decoration-none">
                                    <?= htmlspecialchars($job['title']) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($job['location'] ?: '—') ?></td>
                            <td><?= htmlspecialchars($job['type'] ?: 'Full-time') ?></td>
                            <td>
                                <span class="badge <?= match(strtolower($job['status'] ?? 'draft')) {
                                    'active'  => 'bg-success',
                                    'closed'  => 'bg-danger',
                                    'expired' => 'bg-secondary',
                                    default   => 'bg-warning'
                                } ?>">
                                    <?= ucfirst($job['status'] ?? 'Draft') ?>
                                </span>
                            </td>
                            <td><?= number_format($job['application_count'] ?? 0) ?></td>
                            <td><?= date('M d, Y', strtotime($job['created_at'])) ?></td>
                            <td class="text-nowrap">
                                <a href="company-edit-job.php?id=<?= $job['id'] ?>" 
                                   class="btn btn-sm btn-outline-primary">Edit</a>

                                <a href="company-applications.php?job_id=<?= $job['id'] ?>" 
                                   class="btn btn-sm btn-outline-info">View Applications</a>

                                <?php if ($job['status'] === 'active'): ?>
                                    <form method="post" action="company-toggle-job.php" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                        <input type="hidden" name="id" value="<?= $job['id'] ?>">
                                        <input type="hidden" name="status" value="closed">
                                        <button type="submit" class="btn btn-sm btn-outline-warning" 
                                                onclick="return confirm('Close this job? No more applications will be accepted.')">
                                            Close
                                        </button>
                                    </form>
                                <?php elseif ($job['status'] === 'closed'): ?>
                                    <form method="post" action="company-toggle-job.php" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                        <input type="hidden" name="id" value="<?= $job['id'] ?>">
                                        <input type="hidden" name="status" value="active">
                                        <button type="submit" class="btn btn-sm btn-outline-success">
                                            Reopen
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <form method="post" action="company-delete-job.php" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                    <input type="hidden" name="id" value="<?= $job['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" 
                                            onclick="return confirm('Delete this job permanently? This cannot be undone.')">
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