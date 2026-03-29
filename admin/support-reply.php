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

if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    support_set_flash('admin', 'danger', 'Invalid request. Please try again.');
    header('Location: support-view.php?id=' . $messageId);
    exit;
}

$replyMessage = trim((string)($_POST['reply_message'] ?? ''));
$sendEmail = !empty($_POST['send_email']);

$result = support_reply_message($conn, $messageId, $replyMessage, (int)$_SESSION['admin_id'], $sendEmail);

if (!$result['success']) {
    support_set_flash('admin', 'danger', $result['message']);
} elseif ($sendEmail && empty($result['email_sent'])) {
    support_set_flash('admin', 'warning', 'Reply saved, but email was not sent: ' . ($result['email_message'] ?? 'Unknown error.'));
} else {
    support_set_flash('admin', 'success', 'Reply saved successfully.');
}

header('Location: support-view.php?id=' . $messageId);
exit;
