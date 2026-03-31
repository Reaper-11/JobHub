<?php
// company/company-notifications.php
require '../db.php';

require_role('company');

$cid = current_company_id() ?? 0;
$msg = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['mark_all'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = 'Invalid request.';
        $msg_type = 'danger';
    } else {
        notify_mark_all_read('company', $cid);
        $msg = 'All notifications marked as read.';
        $msg_type = 'success';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['mark_id'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = 'Invalid request.';
        $msg_type = 'danger';
    } else {
        notify_mark_read('company', $cid, (int)$_POST['mark_id']);
        $msg = 'Notification marked as read.';
        $msg_type = 'success';
    }
}

$notifications = notify_fetch('company', $cid, 100);
?>

<?php require 'company-header.php'; ?>

<h1 class="mb-4">Notifications</h1>

<?php if ($msg): ?>
    <div class="alert alert-<?= htmlspecialchars($msg_type) ?>"><?= htmlspecialchars($msg) ?></div>
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
                $relatedType = strtolower(trim((string)($n['related_type'] ?? '')));
                $isVerification = notify_is_verification_notification($n);
                $isSupportReply = $relatedType === 'support_reply';
                $typeBadge = match ($type) {
                    'success' => 'bg-success',
                    'warning' => 'bg-warning text-dark',
                    'danger' => 'bg-danger',
                    'verification' => 'bg-primary',
                    default => 'bg-info text-dark',
                };
            ?>
            <div class="list-group-item d-flex justify-content-between align-items-start<?= $isRead ? '' : ' list-group-item-warning' ?>">
                <div class="me-3 flex-grow-1">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <strong><?= htmlspecialchars($n['title']) ?></strong>
                        <span class="badge <?= $typeBadge ?>"><?= htmlspecialchars(ucfirst($type)) ?></span>
                        <?php if ($isVerification): ?>
                            <span class="badge text-bg-primary">Verification</span>
                        <?php endif; ?>
                        <?php if ($isSupportReply): ?>
                            <span class="badge text-bg-secondary">Support</span>
                        <?php endif; ?>
                        <?php if (!$isRead): ?>
                            <span class="badge bg-warning text-dark">Unread</span>
                        <?php endif; ?>
                    </div>
                    <div class="text-muted small mb-2"><?= date('M d, Y H:i', strtotime((string)$n['created_at'])) ?></div>
                    <div><?= nl2br(htmlspecialchars($n['message'])) ?></div>
                    <?php if ($link !== ''): ?>
                        <div class="mt-2">
                            <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars($link) ?>">
                                <?= $isVerification ? 'Open Verification Details' : ($isSupportReply ? 'View Reply' : 'View') ?>
                            </a>
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

<?php require '../footer.php'; ?>
