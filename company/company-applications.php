<?php
// company/company-applications.php
require '../db.php';

if (!isset($_SESSION['company_id'])) {
    header("Location: company-login.php");
    exit;
}

$cid = (int)$_SESSION['company_id'];
$job_id = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;

$where = $job_id > 0 
    ? "j.company_id = ? AND a.job_id = ?" 
    : "j.company_id = ?";

$params = $job_id > 0 ? [$cid, $job_id] : [$cid];
$types  = $job_id > 0 ? "ii" : "i";

$applications = db_query_all("
    SELECT a.id, a.status, a.cover_letter, a.cv_path, a.applied_at,
           u.name AS user_name, u.email AS user_email, u.cv_path AS user_cv_path,
           j.title AS job_title
    FROM applications a
    JOIN users u ON u.id = a.user_id
    JOIN jobs j ON j.id = a.job_id
    WHERE $where
    ORDER BY a.applied_at DESC
", $types, $params);

$msg = $msg_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validate_csrf_token($_POST['csrf_token'] ?? '')) {
    $app_id = (int)($_POST['app_id'] ?? 0);
    $new_status = trim($_POST['status'] ?? '');
    $allowed_statuses = ['pending', 'shortlisted', 'approved', 'rejected'];

    if ($app_id > 0 && in_array($new_status, $allowed_statuses, true)) {
        $stmt = $conn->prepare("
            UPDATE applications
            SET status = ?, updated_at = NOW()
            WHERE id = ? AND job_id IN (SELECT id FROM jobs WHERE company_id = ?)
        ");
        $stmt->bind_param("sii", $new_status, $app_id, $cid);

        if ($stmt->execute()) {
            $msg = "Application status updated.";
            $msg_type = 'success';
        } else {
            $msg = "Update failed.";
            $msg_type = 'danger';
        }
        $stmt->close();
    } else {
        $msg = "Invalid status selection.";
        $msg_type = 'danger';
    }
}
?>

<?php require 'company-header.php'; ?>

<h1 class="mb-4">
    <?= $job_id > 0 ? 'Applications for Job #' . $job_id : 'All Received Applications' ?>
</h1>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php if (empty($applications)): ?>
    <div class="alert alert-info">No applications received yet.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Applicant</th>
                    <th>Email</th>
                    <th>CV</th>
                    <th>Job Title</th>
                    <th>Status</th>
                    <th>Applied</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($applications as $app): ?>
                <tr>
                    <td><?= htmlspecialchars($app['user_name']) ?></td>
                    <td><?= htmlspecialchars($app['user_email']) ?></td>
                    <td>
                        <?php $cvPath = $app['cv_path'] ?: ($app['user_cv_path'] ?? ''); ?>
                        <?php if (!empty($cvPath)): ?>
                            <a class="btn btn-sm btn-outline-secondary" href="../<?= htmlspecialchars($cvPath) ?>" target="_blank" rel="noopener">View CV</a>
                        <?php else: ?>
                            <span class="text-muted">N/A</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($app['job_title']) ?></td>
                    <td>
                        <span class="badge <?= match(strtolower($app['status'] ?? 'pending')) {
                            'shortlisted' => 'bg-primary',
                            'approved'    => 'bg-success',
                            'rejected'    => 'bg-danger',
                            default       => 'bg-warning'
                        } ?>">
                            <?= ucfirst($app['status'] ?? 'Pending') ?>
                        </span>
                    </td>
                    <td><?= date('M d, Y', strtotime($app['applied_at'])) ?></td>
                    <td>
                        <a class="btn btn-sm btn-outline-secondary me-2" href="company-application-details.php?id=<?= $app['id'] ?>">
                            View Details
                        </a>
                        <button class="btn btn-sm btn-outline-primary"
                                data-bs-toggle="modal" 
                                data-bs-target="#statusModal<?= $app['id'] ?>">
                            Update Status
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php foreach ($applications as $app): ?>
        <!-- Status Update Modal -->
        <div class="modal fade" id="statusModal<?= $app['id'] ?>" tabindex="-1">
            <div class="modal-dialog">
                <form method="post" class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Update Application Status</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="app_id" value="<?= $app['id'] ?>">

                        <div class="mb-3">
                            <label class="form-label">New Status</label>
                            <select name="status" class="form-select" required>
                                <option value="pending">Pending</option>
                                <option value="shortlisted">Shortlisted</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require '../footer.php'; ?>
