<?php
// notifications.php
require_once __DIR__ . '/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];
$msg = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validate_csrf_token($_POST['csrf_token'] ?? '')) {
    if (!empty($_POST['mark_all'])) {
        notify_mark_all_read('user', $userId);
        $msg = 'All notifications marked as read.';
        $msg_type = 'success';
    } elseif (!empty($_POST['mark_id'])) {
        notify_mark_read('user', $userId, (int)$_POST['mark_id']);
        $msg = 'Notification marked as read.';
        $msg_type = 'success';
    }
}

$notifications = notify_fetch('user', $userId, 100);
?>

<h1 class="mb-4">Notifications</h1>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php if (empty($notifications)): ?>
    <div class="alert alert-info">No notifications yet.</div>
<?php else: ?>
    <form method="post" class="mb-3">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        <button type="submit" name="mark_all" value="1" class="btn btn-sm btn-outline-secondary">Mark all as read</button>
    </form>

    <div class="list-group">
        <?php foreach ($notifications as $n): ?>
            <?php
                $isRead = (int)($n['is_read'] ?? 0) === 1;
                $link = trim((string)($n['link'] ?? ''));
                $type = strtolower(trim((string)($n['type'] ?? 'info')));
                $accentClass = match ($type) {
                    'success' => 'border-success-subtle',
                    'warning' => 'border-warning-subtle',
                    'danger' => 'border-danger-subtle',
                    default => 'border-info-subtle',
                };
                $typeBadge = match ($type) {
                    'success' => 'bg-success',
                    'warning' => 'bg-warning text-dark',
                    'danger' => 'bg-danger',
                    default => 'bg-info text-dark',
                };
            ?>
            <div class="list-group-item d-flex justify-content-between align-items-start border-start border-4 <?= $isRead ? $accentClass : 'list-group-item-warning ' . $accentClass ?>">
                <div class="me-3">
                    <div class="d-flex align-items-center gap-2">
                        <strong><?= htmlspecialchars($n['title']) ?></strong>
                        <span class="badge <?= $typeBadge ?>"><?= ucfirst($type) ?></span>
                        <?php if (!$isRead): ?>
                            <span class="badge bg-warning text-dark">Unread</span>
                        <?php endif; ?>
                    </div>
                    <div class="text-muted small mb-2"><?= date('M d, Y H:i', strtotime($n['created_at'])) ?></div>
                    <div><?= nl2br(htmlspecialchars($n['message'])) ?></div>
                    <?php if ($link !== ''): ?>
                        <div class="mt-2">
                            <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars($link) ?>">View</a>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if (!$isRead): ?>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="mark_id" value="<?= (int)$n['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-secondary">Mark read</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
