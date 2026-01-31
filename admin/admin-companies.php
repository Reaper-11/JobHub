<?php
// admin/admin-companies.php
require '../db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit;
}

$status = strtolower($_GET['status'] ?? 'pending');
if (!in_array($status, ['pending','approved','rejected','all'])) $status = 'pending';

$where = $status === 'all' ? "1=1" : "is_approved = " . ($status === 'pending' ? 0 : ($status === 'approved' ? 1 : -1));

$companies = db_query_all("SELECT * FROM companies WHERE $where ORDER BY created_at DESC");

$msg = $msg_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validate_csrf_token($_POST['csrf_token'] ?? '')) {
    $id = (int)($_POST['company_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($id > 0 && in_array($action, ['approve','unapprove','reject'])) {
        if ($action === 'reject') {
            $reason = trim($_POST['reason'] ?? '');
            if ($reason === '') {
                $msg = "Rejection reason is required.";
                $msg_type = 'danger';
            } else {
                $stmt = $conn->prepare("UPDATE companies SET is_approved = -1, rejection_reason = ? WHERE id = ?");
                $stmt->bind_param("si", $reason, $id);
            }
        } else {
            $approved = $action === 'approve' ? 1 : 0;
            $stmt = $conn->prepare("UPDATE companies SET is_approved = ?, rejection_reason = NULL WHERE id = ?");
            $stmt->bind_param("ii", $approved, $id);
        }

        if (isset($stmt) && $stmt->execute()) {
            $msg = "Company status updated successfully.";
            $msg_type = 'success';
            header("Location: admin-companies.php?status=$status");
            exit;
        } else {
            $msg = "Operation failed.";
            $msg_type = 'danger';
        }
    }
}
?>

<?php require 'admin-header.php'; ?>

<h1 class="mb-4">Manage Companies</h1>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<ul class="nav nav-tabs mb-4">
    <li class="nav-item"><a class="nav-link <?= $status==='pending'?'active':'' ?>" href="?status=pending">Pending</a></li>
    <li class="nav-item"><a class="nav-link <?= $status==='approved'?'active':'' ?>" href="?status=approved">Approved</a></li>
    <li class="nav-item"><a class="nav-link <?= $status==='rejected'?'active':'' ?>" href="?status=rejected">Rejected</a></li>
    <li class="nav-item"><a class="nav-link <?= $status==='all'?'active':'' ?>" href="?status=all">All</a></li>
</ul>

<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead class="table-light">
            <tr>
                <th>ID</th>
                <th>Company Name</th>
                <th>Email</th>
                <th>Registered</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($companies as $c): ?>
            <tr>
                <td><?= $c['id'] ?></td>
                <td><?= htmlspecialchars($c['name']) ?></td>
                <td><?= htmlspecialchars($c['email']) ?></td>
                <td><?= date('Y-m-d', strtotime($c['created_at'])) ?></td>
                <td>
                    <?php
                    $statusText = match($c['is_approved']) {
                        1 => '<span class="badge bg-success">Approved</span>',
                        -1 => '<span class="badge bg-danger">Rejected</span>',
                        default => '<span class="badge bg-warning">Pending</span>'
                    };
                    echo $statusText;
                    ?>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <?php if ($c['is_approved'] != 1): ?>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                <input type="hidden" name="company_id" value="<?= $c['id'] ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn btn-outline-success btn-sm">Approve</button>
                            </form>
                        <?php endif; ?>

                        <?php if ($c['is_approved'] != 0): ?>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                <input type="hidden" name="company_id" value="<?= $c['id'] ?>">
                                <input type="hidden" name="action" value="unapprove">
                                <button type="submit" class="btn btn-outline-warning btn-sm">Unapprove</button>
                            </form>
                        <?php endif; ?>

                        <?php if ($c['is_approved'] != -1): ?>
                            <button type="button" class="btn btn-outline-danger btn-sm" 
                                    data-bs-toggle="modal" data-bs-target="#rejectModal<?= $c['id'] ?>">
                                Reject
                            </button>
                        <?php endif; ?>
                    </div>

                    <!-- Reject Modal -->
                    <div class="modal fade" id="rejectModal<?= $c['id'] ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <form method="post" class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Reject Company</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                    <input type="hidden" name="company_id" value="<?= $c['id'] ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <div class="mb-3">
                                        <label class="form-label">Rejection reason <span class="text-danger">*</span></label>
                                        <textarea name="reason" class="form-control" rows="3" required></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-danger">Confirm Reject</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($companies)): ?>
            <tr><td colspan="6" class="text-center py-4">No companies found in this status.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require '../footer.php'; ?>