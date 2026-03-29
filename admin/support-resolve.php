<?php
require '../db.php';
require_once '../includes/support_helper.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: support-messages.php');
    exit;
}

$messageId = (int)($_POST['message_id'] ?? 0);
$returnTo = strtolower(trim((string)($_POST['return_to'] ?? 'list')));
$page = max(1, (int)($_POST['page'] ?? 1));

if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    support_set_flash('admin', 'danger', 'Invalid request. Please try again.');
} else {
    $ok = support_mark_resolved($conn, $messageId);

    if ($ok && function_exists('log_activity')) {
        log_activity(
            $conn,
            (int)$_SESSION['admin_id'],
            'admin',
            'support_message_resolved',
            'Admin resolved support message #' . $messageId,
            'support_message',
            $messageId
        );
    }

    support_set_flash(
        'admin',
        $ok ? 'success' : 'danger',
        $ok ? 'Support message marked as resolved.' : 'Could not resolve the support message.'
    );
}

$redirect = $returnTo === 'view'
    ? 'support-view.php?id=' . $messageId
    : 'support-messages.php?page=' . $page;

header('Location: ' . $redirect);
exit;
