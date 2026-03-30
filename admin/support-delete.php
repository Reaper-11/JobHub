<?php
require '../db.php';
require_once '../includes/support_helper.php';

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: support-messages.php');
    exit;
}

$messageId = (int)($_POST['message_id'] ?? 0);
$page = max(1, (int)($_POST['page'] ?? 1));

if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    support_set_flash('admin', 'danger', 'Invalid request. Please try again.');
    header('Location: support-messages.php?page=' . $page);
    exit;
}

$ok = support_delete_message($conn, $messageId);

if ($ok && function_exists('log_activity')) {
    log_activity(
        $conn,
        current_admin_id() ?? 0,
        'admin',
        'support_message_deleted',
        'Admin deleted support message #' . $messageId,
        'support_message',
        $messageId
    );
}

support_set_flash(
    'admin',
    $ok ? 'success' : 'danger',
    $ok ? 'Support message deleted successfully.' : 'Could not delete the support message.'
);

header('Location: support-messages.php?page=' . $page);
exit;
