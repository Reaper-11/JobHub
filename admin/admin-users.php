<?php
require '../db.php';

require_role('admin');

$msg = $msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validate_csrf_token($_POST['csrf_token'] ?? '')) {
    $uid = (int)($_POST['user_id'] ?? 0);
    $action = trim($_POST['action'] ?? '');

    if ($uid > 0 && in_array($action, ['block', 'unblock', 'remove'], true)) {
        $stmt = $conn->prepare("SELECT id, name, account_id FROM users WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            $msg = "User not found.";
            $msg_type = 'danger';
        } else {
            if ($action === 'block') {
                $stmt = $conn->prepare("UPDATE users SET account_status = 'blocked', is_active = 0, updated_at = NOW() WHERE id = ?");
                $accountStatus = 'blocked';
                $activityType = 'user_blocked';
                $description = "Admin blocked user {$user['name']}";
                $successMessage = "User blocked successfully.";
                $failureMessage = "Failed to block user.";
            } elseif ($action === 'unblock') {
                $stmt = $conn->prepare("UPDATE users SET account_status = 'active', is_active = 1, updated_at = NOW() WHERE id = ?");
                $accountStatus = 'active';
                $activityType = 'user_unblocked';
                $description = "Admin unblocked user {$user['name']}";
                $successMessage = "User unblocked successfully.";
                $failureMessage = "Failed to unblock user.";
            } else {
                $stmt = $conn->prepare("UPDATE users SET account_status = 'removed', is_active = 0, updated_at = NOW() WHERE id = ?");
                $accountStatus = 'inactive';
                $activityType = 'user_removed';
                $description = "Admin removed user {$user['name']}";
                $successMessage = "User removed safely.";
                $failureMessage = "Failed to remove user.";
            }

            if ($stmt) {
                $conn->begin_transaction();

                $stmt->bind_param("i", $uid);
                $ok = $stmt->execute();
                $stmt->close();

                if ($ok && !empty($user['account_id'])) {
                    $ok = jobhub_update_account_status($conn, (int) $user['account_id'], $accountStatus);
                }

                if ($ok) {
                    $conn->commit();
                    $msg = $successMessage;
                    $msg_type = 'success';
                    log_activity($conn, current_admin_id(), 'admin', $activityType, $description, 'user', $uid);
                } else {
                    $conn->rollback();
                    $msg = $failureMessage;
                    $msg_type = 'danger';
                }
            } else {
                $msg = "Could not prepare the requested action.";
                $msg_type = 'danger';
            }
        }
    }
}

$users = db_query_all("
    SELECT id, account_id, name, email, phone, role, account_status, is_active, created_at
    FROM users
    ORDER BY created_at DESC
");
?>

<?php require 'admin-header.php'; ?>

<h1 class="mb-4">Manage Users</h1>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="alert alert-secondary">
    Removed users are soft-deactivated to keep applications, bookmarks, and history safe.
</div>

<div class="table-responsive">
    <table class="table table-hover table-striped align-middle">
        <thead class="table-light">
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Phone</th>
                <th>Registered</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($users)): ?>
            <tr><td colspan="8" class="text-center py-4">No users found.</td></tr>
        <?php else: ?>
            <?php foreach ($users as $u): ?>
                <?php
                $status = strtolower((string)($u['account_status'] ?? 'active'));
                if ($status !== 'blocked' && $status !== 'removed') {
                    $status = ((int)($u['is_active'] ?? 1) === 1) ? 'active' : 'blocked';
                }
                ?>
                <tr>
                    <td><?= (int)$u['id'] ?></td>
                    <td><?= htmlspecialchars($u['name']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><?= htmlspecialchars(ucfirst($u['role'] ?? 'seeker')) ?></td>
                    <td><?= htmlspecialchars($u['phone'] ?: 'â€”') ?></td>
                    <td><?= date('Y-m-d', strtotime($u['created_at'])) ?></td>
                    <td>
                        <span class="badge <?= user_status_badge_class($status) ?>">
                            <?= user_status_label($status) ?>
                        </span>
                    </td>
                    <td>
                        <div class="d-flex gap-2 flex-wrap">
                            <?php if ($status === 'active'): ?>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                    <input type="hidden" name="action" value="block">
                                    <button type="submit" class="btn btn-sm btn-outline-warning" onclick="return confirm('Block this user?')">Block</button>
                                </form>
                            <?php elseif ($status === 'blocked'): ?>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                    <input type="hidden" name="action" value="unblock">
                                    <button type="submit" class="btn btn-sm btn-outline-success">Unblock</button>
                                </form>
                            <?php endif; ?>

                            <?php if ($status !== 'removed'): ?>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                    <input type="hidden" name="action" value="remove">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this user account? Related records will be kept safely.')">Remove</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require '../footer.php'; ?>
