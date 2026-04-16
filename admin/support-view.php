<?php
require '../db.php';
require_once '../includes/support_helper.php';

require_role('admin');

$messageId = (int)($_GET['id'] ?? 0);
$activeFilter = support_normalize_filter($_GET['filter'] ?? 'all');
$page = max(1, (int)($_GET['page'] ?? 1));
$includeDeleted = $activeFilter === 'deleted';
$supportMessage = support_fetch_message($conn, $messageId, $includeDeleted);

if (!$supportMessage) {
    support_set_flash('admin', 'danger', 'Support message not found.');
    header('Location: ' . support_messages_url($activeFilter, $page));
    exit;
}

if (empty($supportMessage['is_deleted']) && empty($supportMessage['is_read'])) {
    support_mark_read_state($conn, $messageId, true);
    $supportMessage = support_fetch_message($conn, $messageId, $includeDeleted);
}

$flash = support_get_flash('admin');
$mailStatus = jobhub_support_mail_status();
$senderEmail = trim((string)($supportMessage['sender_email'] ?? ''));
$messageIsDeleted = !empty($supportMessage['is_deleted']);
$canSendReplyEmail = !$messageIsDeleted && !empty($mailStatus['can_send']) && filter_var($senderEmail, FILTER_VALIDATE_EMAIL);
$replyEmailHelp = $canSendReplyEmail
    ? 'The reply will be emailed after it is saved.'
    : (!filter_var($senderEmail, FILTER_VALIDATE_EMAIL)
        ? 'The sender email address is missing or invalid.'
        : $mailStatus['message']);
if ($messageIsDeleted) {
    $replyEmailHelp = 'Deleted messages cannot be replied to.';
}
?>

<?php require 'admin-header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0">Support Message #<?= (int)$supportMessage['id'] ?></h1>
    <a href="<?= htmlspecialchars(support_messages_url($activeFilter, $page)) ?>" class="btn btn-outline-secondary">Back to List</a>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
        <?= htmlspecialchars($flash['message']) ?>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light">
                <h2 class="h5 mb-0">Message Details</h2>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="text-muted small">Sender Name</div>
                        <div><?= htmlspecialchars($supportMessage['sender_name']) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Email</div>
                        <div><?= htmlspecialchars($supportMessage['sender_email']) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Phone</div>
                        <div><?= htmlspecialchars($supportMessage['sender_phone'] ?: '-') ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Role</div>
                        <div><?= htmlspecialchars(support_role_label($supportMessage['sender_role'] ?? 'guest')) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Status</div>
                        <div><span class="badge <?= support_status_badge_class($supportMessage['status'] ?? 'new') ?>"><?= htmlspecialchars(support_status_label($supportMessage['status'] ?? 'new')) ?></span></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Read State</div>
                        <div><span class="badge <?= !empty($supportMessage['is_read']) ? 'bg-success' : 'bg-warning text-dark' ?>"><?= !empty($supportMessage['is_read']) ? 'Read' : 'Unread' ?></span></div>
                    </div>
                    <?php if ($messageIsDeleted): ?>
                        <div class="col-md-6">
                            <div class="text-muted small">Deleted</div>
                            <div><span class="badge bg-secondary">Deleted</span></div>
                        </div>
                    <?php endif; ?>
                    <div class="col-md-6">
                        <div class="text-muted small">Created At</div>
                        <div><?= htmlspecialchars($supportMessage['created_at']) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Replied At</div>
                        <div><?= htmlspecialchars($supportMessage['replied_at'] ?: '-') ?></div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted small">Subject</div>
                        <div class="fw-semibold"><?= htmlspecialchars($supportMessage['subject']) ?></div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted small">Message</div>
                        <div class="content-surface rounded p-3"><?= nl2br(htmlspecialchars($supportMessage['message'])) ?></div>
                    </div>
                    <?php if (!empty($supportMessage['admin_reply'])): ?>
                        <div class="col-12">
                            <div class="text-muted small">Latest Admin Reply</div>
                            <div class="content-surface rounded p-3"><?= nl2br(htmlspecialchars($supportMessage['admin_reply'])) ?></div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <?php if (!$messageIsDeleted): ?>
                        <form method="post" action="support-toggle-read.php">
                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                            <input type="hidden" name="message_id" value="<?= (int)$supportMessage['id'] ?>">
                            <input type="hidden" name="target_state" value="<?= !empty($supportMessage['is_read']) ? 'unread' : 'read' ?>">
                            <input type="hidden" name="return_to" value="view">
                            <input type="hidden" name="filter" value="<?= htmlspecialchars($activeFilter) ?>">
                            <input type="hidden" name="page" value="<?= (int)$page ?>">
                            <button type="submit" class="btn btn-outline-secondary">
                                <?= !empty($supportMessage['is_read']) ? 'Mark Unread' : 'Mark Read' ?>
                            </button>
                        </form>

                        <?php if (($supportMessage['status'] ?? 'new') !== 'resolved'): ?>
                            <form method="post" action="support-resolve.php">
                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                <input type="hidden" name="message_id" value="<?= (int)$supportMessage['id'] ?>">
                                <input type="hidden" name="return_to" value="view">
                                <input type="hidden" name="filter" value="<?= htmlspecialchars($activeFilter) ?>">
                                <input type="hidden" name="page" value="<?= (int)$page ?>">
                                <button type="submit" class="btn btn-outline-success">Mark Resolved</button>
                            </form>
                        <?php endif; ?>

                        <form method="post" action="support-delete.php" onsubmit="return confirm('Delete this support message?');">
                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                            <input type="hidden" name="message_id" value="<?= (int)$supportMessage['id'] ?>">
                            <input type="hidden" name="filter" value="<?= htmlspecialchars($activeFilter) ?>">
                            <input type="hidden" name="page" value="<?= (int)$page ?>">
                            <button type="submit" class="btn btn-outline-danger">Delete</button>
                        </form>
                    <?php else: ?>
                        <span class="badge bg-secondary align-self-center">Deleted message</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-light">
                <h2 class="h5 mb-0">Admin Reply</h2>
            </div>
            <div class="card-body">
                <form method="post" action="support-reply.php">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="message_id" value="<?= (int)$supportMessage['id'] ?>">
                    <input type="hidden" name="filter" value="<?= htmlspecialchars($activeFilter) ?>">
                    <input type="hidden" name="page" value="<?= (int)$page ?>">

                    <div class="mb-3">
                        <label class="form-label">Reply Message <span class="text-danger">*</span></label>
                        <textarea name="reply_message" class="form-control" rows="8" <?= $messageIsDeleted ? 'disabled' : 'required' ?>><?= htmlspecialchars($supportMessage['admin_reply'] ?? '') ?></textarea>
                    </div>

                    <div class="form-check mb-3">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="send_email"
                            id="send_email"
                            value="1"
                            <?= $canSendReplyEmail ? 'checked' : 'disabled' ?>
                        >
                        <label class="form-check-label" for="send_email">
                            Send reply email to <?= htmlspecialchars($senderEmail !== '' ? $senderEmail : 'the sender') ?>
                        </label>
                    </div>

                    <div class="form-text mb-3">
                        <?= htmlspecialchars($replyEmailHelp) ?>
                    </div>

                    <button type="submit" class="btn btn-primary w-100" <?= $messageIsDeleted ? 'disabled' : '' ?>>Save Reply</button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-light">
                <h2 class="h5 mb-0">Reply Email Status</h2>
            </div>
            <div class="card-body">
                <p class="mb-2">
                    <strong>SMTP Status:</strong>
                    <?= htmlspecialchars($mailStatus['message']) ?>
                </p>
                <p class="mb-2">
                    <strong>Sent:</strong>
                    <?= !empty($supportMessage['reply_email_sent']) ? 'Yes' : 'No' ?>
                </p>
                <p class="mb-0">
                    <strong>Error:</strong>
                    <?= htmlspecialchars($supportMessage['reply_email_error'] ?: '-') ?>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require '../footer.php'; ?>
