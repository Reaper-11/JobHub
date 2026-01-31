<?php
// admin/admin-users.php
require '../db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit;
}

$users = db_query_all("SELECT id, name, email, phone, created_at, is_active 
                       FROM users 
                       ORDER BY created_at DESC");

$msg = $msg_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validate_csrf_token($_POST['csrf_token'] ?? '')) {
    if (isset($_POST['delete_user'])) {
        $uid = (int)($_POST['user_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');

        if ($uid > 0) {
            // Log reason
            $adminId = (int)$_SESSION['admin_id'];
            $stmt = $conn->prepare("INSERT INTO user_deletion_reasons (user_id, admin_id, reason, deleted_at) 
                                    VALUES (?, ?, ?, NOW())");
            $stmt->bind_param("iis", $uid, $adminId, $reason);
            $stmt->execute();
            $stmt->close();

            // Delete user
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $uid);
            if ($stmt->execute()) {
                $msg = "User deleted successfully.";
                $msg_type = 'success';
            } else {
                $msg = "Failed to delete user.";
                $msg_type = 'danger';
            }
            $stmt->close();
        }
    }
}
?>

<?php require 'admin-header.php'; ?>

<h1 class="mb-4">Manage Users</h1>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="table-responsive">
    <table class="table table-hover table-striped align-middle">
        <thead class="table-light">
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Registered</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($users)): ?>
            <tr><td colspan="7" class="text-center py-4">No users found.</td></tr>
        <?php else: ?>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= $u['id'] ?></td>
                    <td><?= htmlspecialchars($u['name']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><?= htmlspecialchars($u['phone'] ?: '—') ?></td>
                    <td><?= date('Y-m-d', strtotime($u['created_at'])) ?></td>
                    <td>
                        <span class="badge <?= $u['is_active'] ? 'bg-success' : 'bg-danger' ?>">
                            <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-danger"
                                data-bs-toggle="modal" data-bs-target="#deleteModal<?= $u['id'] ?>">
                            Delete
                        </button>

                        <!-- Delete Modal -->
                        <div class="modal fade" id="deleteModal<?= $u['id'] ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <form method="post" class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Delete User</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <input type="hidden" name="delete_user" value="1">
                                        <div class="mb-3">
                                            <label class="form-label">Reason (optional)</label>
                                            <textarea name="reason" class="form-control" rows="3"></textarea>
                                        </div>
                                        <p class="text-danger small mb-0">This action cannot be undone.</p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-danger">Delete User</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require '../footer.php'; ?>