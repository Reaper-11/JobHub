<?php
require '../db.php';

require_role('admin');

$msg = $msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validate_csrf_token($_POST['csrf_token'] ?? '')) {
    $uid = (int)($_POST['user_id'] ?? 0);
    $action = trim((string)($_POST['action'] ?? ''));
    $reason = trim((string)($_POST['reason'] ?? ''));
    $reasonForLog = trim((string)preg_replace('/\s+/', ' ', $reason));
    if (function_exists('mb_substr')) {
        $reasonForLog = mb_substr($reasonForLog, 0, 160);
    } else {
        $reasonForLog = substr($reasonForLog, 0, 160);
    }

    if ($uid > 0 && in_array($action, ['block', 'unblock', 'remove', 'restore'], true)) {
        $stmt = $conn->prepare("SELECT id, name, email, account_id FROM users WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            $msg = "User not found.";
            $msg_type = 'danger';
        } elseif (in_array($action, ['block', 'remove'], true) && $reason === '') {
            $msg = $action === 'block'
                ? "A reason is required to block a user."
                : "A reason is required to remove a user.";
            $msg_type = 'danger';
        } else {
            if ($action === 'block') {
                $stmt = $conn->prepare("UPDATE users SET account_status = 'blocked', is_active = 0, updated_at = NOW() WHERE id = ?");
                $accountStatus = 'blocked';
                $activityType = 'user_blocked';
                $description = "Admin blocked user {$user['name']}. Reason: {$reasonForLog}";
                $successMessage = "User blocked successfully.";
                $failureMessage = "Failed to block user.";
            } elseif ($action === 'unblock') {
                $stmt = $conn->prepare("UPDATE users SET account_status = 'active', is_active = 1, updated_at = NOW() WHERE id = ?");
                $accountStatus = 'active';
                $activityType = 'user_unblocked';
                $description = "Admin unblocked user {$user['name']}";
                $successMessage = "User unblocked successfully.";
                $failureMessage = "Failed to unblock user.";
            } elseif ($action === 'remove') {
                $stmt = $conn->prepare("UPDATE users SET account_status = 'removed', is_active = 0, updated_at = NOW() WHERE id = ?");
                $accountStatus = 'inactive';
                $activityType = 'user_removed';
                $description = "Admin removed user {$user['name']}. Reason: {$reasonForLog}";
                $successMessage = "User removed safely.";
                $failureMessage = "Failed to remove user.";
            } else {
                $stmt = $conn->prepare("UPDATE users SET account_status = 'active', is_active = 1, updated_at = NOW() WHERE id = ?");
                $accountStatus = 'active';
                $activityType = 'user_restored';
                $description = "Admin restored user {$user['name']}";
                $successMessage = "User restored successfully.";
                $failureMessage = "Failed to restore user.";
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

                    if ($action === 'block') {
                        notify_create(
                            'user',
                            $uid,
                            'Account Blocked',
                            'Your JobHub account has been blocked by admin. Reason: ' . $reason,
                            'notifications.php',
                            'warning',
                            'user',
                            $uid
                        );
                    } elseif ($action === 'remove') {
                        notify_create(
                            'user',
                            $uid,
                            'Account Removed',
                            'Your JobHub account has been removed by admin. Reason: ' . $reason,
                            'notifications.php',
                            'danger',
                            'user',
                            $uid
                        );
                    }

                    $userEmail = trim((string) ($user['email'] ?? ''));
                    if ($action === 'block' && $userEmail !== '') {
                        try {
                            $mailResult = jobhub_send_account_blocked_email(
                                $userEmail,
                                (string) ($user['name'] ?? ''),
                                $reason
                            );

                            if (empty($mailResult['success'])) {
                                $mailMessage = trim((string) ($mailResult['message'] ?? ''));
                                jobhub_log_mail_error(
                                    'account-blocked',
                                    'Job seeker block email failed for ' . $userEmail . ': '
                                    . ($mailMessage !== '' ? $mailMessage : 'Unknown mail error.')
                                );
                            }
                        } catch (Throwable $mailException) {
                            jobhub_log_mail_error(
                                'account-blocked',
                                'Job seeker block email threw an exception for ' . $userEmail . ': ' . $mailException->getMessage()
                            );
                        }
                    }

                    if ($action === 'remove' && $userEmail !== '') {
                        try {
                            $mailResult = jobhub_send_account_removed_email(
                                $userEmail,
                                (string) ($user['name'] ?? ''),
                                'jobseeker',
                                $reason
                            );

                            if (empty($mailResult['success'])) {
                                $mailMessage = trim((string) ($mailResult['message'] ?? ''));
                                jobhub_log_mail_error(
                                    'account-removed',
                                    'Job seeker removal email failed for ' . $userEmail . ': '
                                    . ($mailMessage !== '' ? $mailMessage : 'Unknown mail error.')
                                );
                            }
                        } catch (Throwable $mailException) {
                            jobhub_log_mail_error(
                                'account-removed',
                                'Job seeker removal email threw an exception for ' . $userEmail . ': ' . $mailException->getMessage()
                            );
                        }
                    }
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

$statusFilter = strtolower(trim((string)($_GET['status'] ?? 'all')));
$allowedStatusFilters = ['active', 'blocked', 'removed', 'all'];
if (!in_array($statusFilter, $allowedStatusFilters, true)) {
    $statusFilter = 'all';
}

$statusCounts = [
    'active' => (int)db_query_value("SELECT COUNT(*) FROM users WHERE account_status = 'active'", '', [], 0),
    'blocked' => (int)db_query_value("SELECT COUNT(*) FROM users WHERE account_status = 'blocked'", '', [], 0),
    'removed' => (int)db_query_value("SELECT COUNT(*) FROM users WHERE account_status = 'removed'", '', [], 0),
];
$statusCounts['all'] = $statusCounts['active'] + $statusCounts['blocked'] + $statusCounts['removed'];

$userWhere = '';
$userTypes = '';
$userParams = [];
if ($statusFilter !== 'all') {
    $userWhere = 'WHERE account_status = ?';
    $userTypes = 's';
    $userParams[] = $statusFilter;
}

$statusTabs = [
    'active' => 'Active',
    'blocked' => 'Blocked',
    'removed' => 'Removed',
    'all' => 'All',
];

$users = db_query_all("
    SELECT id, account_id, name, email, phone, role, account_status, is_active, created_at
    FROM users
    {$userWhere}
    ORDER BY created_at DESC
", $userTypes, $userParams);
?>

<?php require 'admin-header.php'; ?>

<h1 class="mb-4">Manage Users</h1>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="alert alert-secondary">
    Removed users are soft-deactivated to keep applications, bookmarks, and history safe. Reasons are required for block and remove actions.
</div>

<ul class="nav nav-tabs mb-4">
    <?php foreach ($statusTabs as $tabStatus => $tabLabel): ?>
        <li class="nav-item">
            <a class="nav-link <?= $statusFilter === $tabStatus ? 'active' : '' ?>" href="?status=<?= htmlspecialchars($tabStatus) ?>">
                <?= htmlspecialchars($tabLabel) ?>
                <span class="badge text-bg-secondary ms-1"><?= (int)($statusCounts[$tabStatus] ?? 0) ?></span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

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
                    <td><?= htmlspecialchars($u['phone'] ?: '-') ?></td>
                    <td><?= date('Y-m-d', strtotime($u['created_at'])) ?></td>
                    <td>
                        <span class="badge <?= user_status_badge_class($status) ?>">
                            <?= user_status_label($status) ?>
                        </span>
                    </td>
                    <td>
                        <div class="d-flex gap-2 flex-wrap">
                            <?php if ($status === 'active'): ?>
                                <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#blockUserModal<?= (int)$u['id'] ?>">
                                    Block
                                </button>
                            <?php elseif ($status === 'blocked'): ?>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                    <input type="hidden" name="action" value="unblock">
                                    <button type="submit" class="btn btn-sm btn-outline-success">Unblock</button>
                                </form>
                            <?php elseif ($status === 'removed'): ?>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                    <input type="hidden" name="action" value="restore">
                                    <button type="submit" class="btn btn-sm btn-outline-success">Restore</button>
                                </form>
                            <?php endif; ?>

                            <?php if ($status !== 'removed'): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#removeUserModal<?= (int)$u['id'] ?>">
                                    Remove
                                </button>
                            <?php endif; ?>
                        </div>

                        <?php if ($status === 'active'): ?>
                            <div class="modal fade" id="blockUserModal<?= (int)$u['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <form method="post" class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Block User</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                            <input type="hidden" name="action" value="block">
                                            <p class="mb-3">Explain why <?= htmlspecialchars($u['name']) ?> is being blocked.</p>
                                            <div class="mb-0">
                                                <label class="form-label">Reason <span class="text-danger">*</span></label>
                                                <textarea name="reason" class="form-control" rows="3" maxlength="500" required></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-warning">Confirm Block</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($status !== 'removed'): ?>
                            <div class="modal fade" id="removeUserModal<?= (int)$u['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <form method="post" class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Remove User</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                            <input type="hidden" name="action" value="remove">
                                            <p class="mb-3">Explain why <?= htmlspecialchars($u['name']) ?> is being removed. Related records will stay preserved.</p>
                                            <div class="mb-0">
                                                <label class="form-label">Reason <span class="text-danger">*</span></label>
                                                <textarea name="reason" class="form-control" rows="3" maxlength="500" required></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-danger">Confirm Remove</button>
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

<?php require '../footer.php'; ?>
