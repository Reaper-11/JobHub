<?php
require '../db.php';
require_once '../includes/support_helper.php';

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: support-messages.php');
    exit;
}

$messageId = (int)($_POST['message_id'] ?? 0);
$targetState = strtolower(trim((string)($_POST['target_state'] ?? 'read')));
$returnTo = strtolower(trim((string)($_POST['return_to'] ?? 'list')));
$page = max(1, (int)($_POST['page'] ?? 1));

if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    support_set_flash('admin', 'danger', 'Invalid request. Please try again.');
} else {
    $markAsRead = $targetState !== 'unread';
    $ok = support_mark_read_state($conn, $messageId, $markAsRead);

    if ($ok && function_exists('log_activity')) {
        log_activity(
            $conn,
            current_admin_id() ?? 0,
            'admin',
            $markAsRead ? 'support_message_read' : 'support_message_unread',
            'Admin updated support message read state #' . $messageId,
            'support_message',
            $messageId
        );
    }

    support_set_flash(
        'admin',
        $ok ? 'success' : 'danger',
        $ok
            ? ($markAsRead ? 'Support message marked as read.' : 'Support message marked as unread.')
            : 'Could not update read state.'
    );
}

$redirect = $returnTo === 'view'
    ? 'support-view.php?id=' . $messageId
    : 'support-messages.php?page=' . $page;

header('Location: ' . $redirect);
exit;
