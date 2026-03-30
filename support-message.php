<?php
require 'db.php';
require_once __DIR__ . '/includes/support_helper.php';

$context = support_require_contact_access($conn);
$messageId = (int)($_GET['id'] ?? 0);
$recipientType = ($context['sender_role'] ?? '') === 'company' ? 'company' : 'user';
$recipientId = $recipientType === 'company'
    ? (int)($context['company_id'] ?? 0)
    : (int)($context['user_id'] ?? 0);
$supportMessage = support_fetch_message($conn, $messageId);
$canViewMessage = false;

if ($supportMessage) {
    $senderRole = strtolower(trim((string)($supportMessage['sender_role'] ?? '')));
    $canViewMessage = ($recipientType === 'user'
            && $senderRole === 'user'
            && (int)($supportMessage['user_id'] ?? 0) === $recipientId)
        || ($recipientType === 'company'
            && $senderRole === 'company'
            && (int)($supportMessage['company_id'] ?? 0) === $recipientId);
}

if ($canViewMessage && trim((string)($supportMessage['admin_reply'] ?? '')) !== '') {
    $stmt = $conn->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE recipient_type = ?
          AND recipient_id = ?
          AND related_type = 'support_reply'
          AND related_id = ?
    ");

    if ($stmt) {
        $stmt->bind_param("sii", $recipientType, $recipientId, $messageId);
        $stmt->execute();
        $stmt->close();
    }
}

$pageTitle = 'Support Reply - JobHub';
$bodyClass = 'user-ui';
$backLink = $recipientType === 'company' ? 'company/company-notifications.php' : 'notifications.php';

require 'header.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-9">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h1 class="h3 mb-1">Support Reply</h1>
                <p class="text-muted mb-0">Read the full admin response to your contact support message.</p>
            </div>
            <a href="<?= htmlspecialchars($backLink) ?>" class="btn btn-outline-secondary">Back to Notifications</a>
        </div>

        <?php if (!$canViewMessage || trim((string)($supportMessage['admin_reply'] ?? '')) === ''): ?>
            <?php http_response_code(404); ?>
            <div class="alert alert-warning">
                Support reply not found or you do not have permission to view it.
            </div>
        <?php else: ?>
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-md-5">
                    <div class="d-flex flex-wrap gap-2 mb-4">
                        <span class="badge text-bg-info">Contact Support</span>
                        <span class="badge <?= support_status_badge_class($supportMessage['status'] ?? 'replied') ?>">
                            <?= htmlspecialchars(support_status_label($supportMessage['status'] ?? 'replied')) ?>
                        </span>
                    </div>

                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="text-muted small">Subject</div>
                            <div class="fw-semibold"><?= htmlspecialchars($supportMessage['subject']) ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Reply Date</div>
                            <div><?= htmlspecialchars($supportMessage['replied_at'] ?: '-') ?></div>
                        </div>
                        <div class="col-12">
                            <div class="text-muted small">Your Message</div>
                            <div class="border rounded p-3 bg-light"><?= nl2br(htmlspecialchars($supportMessage['message'])) ?></div>
                        </div>
                        <div class="col-12">
                            <div class="text-muted small">Admin Reply</div>
                            <div class="border rounded p-3 bg-white"><?= nl2br(htmlspecialchars($supportMessage['admin_reply'])) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require 'footer.php'; ?>
