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

// Group notifications by date
$groupedNotifications = [];
$today = date('Y-m-d');
foreach ($notifications as $n) {
    $notifDate = date('Y-m-d', strtotime((string)$n['created_at']));
    $groupKey = $notifDate === $today ? 'Today' : 'Earlier';
    if (!isset($groupedNotifications[$groupKey])) {
        $groupedNotifications[$groupKey] = [];
    }
    $groupedNotifications[$groupKey][] = $n;
}
?>

<?php require 'company-header.php'; ?>

<h1 class="mb-4">Notifications</h1>

<?php if ($msg): ?>
    <div class="alert alert-<?= htmlspecialchars($msg_type) ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php if (empty($notifications)): ?>
    <div class="alert alert-secondary" style="text-align: center; padding: 2rem;">
        <i class="fas fa-bell-slash me-2" style="font-size: 2rem; opacity: 0.5;"></i>
        <div class="text-muted">No notifications yet.</div>
    </div>
<?php else: ?>
    <div class="mb-3">
        <form method="post" class="d-inline-block">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            <button type="submit" name="mark_all" value="1" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-check-double me-1"></i>Mark all as read
            </button>
        </form>
    </div>

    <?php foreach ($groupedNotifications as $groupLabel => $groupNotifs): ?>
        <div class="mb-4">
            <h5 class="text-muted mb-3" style="font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em;">
                <?= htmlspecialchars($groupLabel) ?>
            </h5>

            <div class="list-group">
                <?php foreach ($groupNotifs as $n): ?>
                    <?php
                        $isRead = (int)($n['is_read'] ?? 0) === 1;
                        $link = trim((string)($n['link'] ?? ''));
                        $type = strtolower(trim((string)($n['type'] ?? 'info')));
                        $relatedType = strtolower(trim((string)($n['related_type'] ?? '')));
                        $isVerification = notify_is_verification_notification($n);
                        $isSupportReply = $relatedType === 'support_reply';
                        
                        if ($isVerification && ($link === '' || basename($link) === 'company-notifications.php')) {
                            $link = 'company-verification.php';
                        }
                        
                        // Determine border color based on type
                        $borderColor = match ($type) {
                            'success' => '#28a745',
                            'danger' => '#dc3545',
                            'warning' => '#ffc107',
                            'verification' => '#0d6efd',
                            default => '#17a2b8',
                        };
                        
                        // Map notification types to user-friendly badge labels
                        $badgeClass = match ($type) {
                            'success' => 'bg-success',
                            'warning' => 'bg-warning text-dark',
                            'danger' => 'bg-danger',
                            'verification' => 'bg-primary',
                            default => 'bg-info text-dark',
                        };
                        
                        // User-friendly badge label
                        $badgeLabel = match ($type) {
                            'success' => 'Approved',
                            'danger' => 'Rejected',
                            'warning' => 'Warning',
                            'verification' => 'Verified',
                            default => 'Info',
                        };
                    ?>
                    <div class="list-group-item" style="border-left: 4px solid <?= $borderColor ?>; <?= $isRead ? '' : 'background-color: #f8f9fa;' ?> padding-left: 1rem;">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                    <strong style="font-size: 1.05rem; color: #ffffff; font-weight: 600;">
                                        <?= htmlspecialchars($n['title']) ?>
                                    </strong>
                                    <span class="badge <?= $badgeClass ?>" style="font-size: 0.75rem;">
                                        <?= htmlspecialchars($badgeLabel) ?>
                                    </span>
                                    <?php if ($isSupportReply && !$isVerification): ?>
                                        <span class="badge text-bg-secondary" style="font-size: 0.75rem;">
                                            <i class="fas fa-comment me-1"></i>Support
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!$isRead): ?>
                                        <span class="badge bg-warning text-dark" style="font-size: 0.75rem;">
                                            <i class="fas fa-circle-fill" style="font-size: 0.5rem;"></i> Unread
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="text-muted small mb-2" style="font-size: 0.85rem;">
                                    <i class="fas fa-clock me-1"></i><?= date('M d, Y H:i', strtotime((string)$n['created_at'])) ?>
                                </div>
                                
                                <div style="color: #495057; line-height: 1.5;">
                                    <?= nl2br(htmlspecialchars($n['message'])) ?>
                                </div>
                                
                                <?php if ($link !== ''): ?>
                                    <div class="mt-2">
                                        <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars($link) ?>">
                                            <i class="fas fa-arrow-right me-1"></i>
                                            <?= $isVerification ? 'View Verification' : ($isSupportReply ? 'View Reply' : 'View Details') ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!$isRead): ?>
                                <form method="post" class="ms-3" style="white-space: nowrap;">
                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                    <input type="hidden" name="mark_id" value="<?= (int)$n['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Mark as read">
                                        <i class="fas fa-check me-1"></i>Mark read
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require '../footer.php'; ?>
