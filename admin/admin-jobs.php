<?php
require '../db.php';
require_once '../includes/recommendation.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit;
}

$statusFilter = $_GET['approval'] ?? 'all';
if (!in_array($statusFilter, ['all', 'pending', 'approved', 'rejected'], true)) {
    $statusFilter = 'all';
}

$msg = $msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validate_csrf_token($_POST['csrf_token'] ?? '')) {
    $jobId = (int)($_POST['job_id'] ?? 0);
    $action = trim($_POST['action'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    $adminId = (int)$_SESSION['admin_id'];

    if ($jobId > 0 && in_array($action, ['approve', 'reject'], true)) {
        $stmt = $conn->prepare("
            SELECT j.id, j.title, j.is_approved, c.name AS company_name
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
            $stmt = $conn->prepare("
                UPDATE jobs
                SET is_approved = ?, approved_by = ?, approved_at = NOW(), admin_remarks = ?, updated_at = NOW()
                WHERE id = ?
            ");

            if ($stmt) {
                $stmt->bind_param("iisi", $approvalValue, $adminId, $remarks, $jobId);
                $ok = $stmt->execute();
                $stmt->close();

                if ($ok) {
                    $msg = $action === 'approve' ? "Job approved successfully." : "Job rejected successfully.";
                    $msg_type = 'success';

                    if ($action === 'approve' && $previousApproval !== 1) {
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
$types = '';
$params = [];

if ($statusFilter === 'pending') {
    $conditions[] = "j.is_approved = 0";
} elseif ($statusFilter === 'approved') {
    $conditions[] = "j.is_approved = 1";
} elseif ($statusFilter === 'rejected') {
    $conditions[] = "j.is_approved = -1";
}

$where = empty($conditions) ? '1=1' : implode(' AND ', $conditions);

$jobs = db_query_all("
    SELECT j.id, j.title, j.category, j.status, j.is_approved, j.admin_remarks, j.created_at,
           c.name AS company_name
    FROM jobs j
    LEFT JOIN companies c ON j.company_id = c.id
    WHERE {$where}
    ORDER BY j.created_at DESC
");

$counts = [
    'pending' => db_query_value("SELECT COUNT(*) FROM jobs WHERE is_approved = 0"),
    'approved' => db_query_value("SELECT COUNT(*) FROM jobs WHERE is_approved = 1"),
    'rejected' => db_query_value("SELECT COUNT(*) FROM jobs WHERE is_approved = -1"),
];
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

<ul class="nav nav-tabs mb-4">
    <li class="nav-item"><a class="nav-link <?= $statusFilter === 'all' ? 'active' : '' ?>" href="?approval=all">All</a></li>
    <li class="nav-item"><a class="nav-link <?= $statusFilter === 'pending' ? 'active' : '' ?>" href="?approval=pending">Pending</a></li>
    <li class="nav-item"><a class="nav-link <?= $statusFilter === 'approved' ? 'active' : '' ?>" href="?approval=approved">Approved</a></li>
    <li class="nav-item"><a class="nav-link <?= $statusFilter === 'rejected' ? 'active' : '' ?>" href="?approval=rejected">Rejected</a></li>
</ul>

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
                <th>Remarks</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($jobs)): ?>
            <tr><td colspan="9" class="text-center py-4">No jobs found.</td></tr>
        <?php else: ?>
            <?php foreach ($jobs as $job): ?>
                <tr>
                    <td><?= (int)$job['id'] ?></td>
                    <td>
                        <div class="fw-semibold"><?= htmlspecialchars($job['title']) ?></div>
                        <a href="job-details.php?id=<?= (int)$job['id'] ?>" class="small text-decoration-none">View details</a>
                    </td>
                    <td><?= htmlspecialchars($job['company_name'] ?: 'â€”') ?></td>
                    <td><?= htmlspecialchars($job['category'] ?: 'â€”') ?></td>
                    <td>
                        <span class="badge <?= match(strtolower($job['status'] ?? 'draft')) {
                            'active' => 'bg-success',
                            'closed' => 'bg-danger',
                            'expired' => 'bg-secondary',
                            default => 'bg-warning text-dark'
                        } ?>">
                            <?= ucfirst($job['status'] ?? 'Draft') ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge <?= job_approval_badge_class((int)$job['is_approved']) ?>">
                            <?= job_approval_label((int)$job['is_approved']) ?>
                        </span>
                    </td>
                    <td><?= date('Y-m-d', strtotime($job['created_at'])) ?></td>
                    <td><?= htmlspecialchars($job['admin_remarks'] ?: 'â€”') ?></td>
                    <td style="min-width: 260px;">
                        <form method="post" class="d-grid gap-2">
                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                            <input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>">
                            <textarea name="remarks" class="form-control form-control-sm" rows="2" placeholder="Admin remarks (optional)"><?= htmlspecialchars($job['admin_remarks'] ?? '') ?></textarea>
                            <div class="d-flex gap-2">
                                <button type="submit" name="action" value="approve" class="btn btn-sm btn-success">Approve</button>
                                <button type="submit" name="action" value="reject" class="btn btn-sm btn-danger">Reject</button>
                            </div>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require '../footer.php'; ?>
