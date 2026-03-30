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
$previousSupportMessage = support_fetch_message($conn, $messageId);
$previousReply = trim((string)($previousSupportMessage['admin_reply'] ?? ''));

$result = support_reply_message($conn, $messageId, $replyMessage, (int)$_SESSION['admin_id'], $sendEmail);

if (!empty($result['success'])) {
    $supportMessage = support_fetch_message($conn, $messageId);
    $replyChanged = $replyMessage !== '' && $replyMessage !== $previousReply;
    $recipientType = '';
    $recipientId = 0;

    if ($supportMessage && $replyChanged) {
        $senderRole = strtolower(trim((string)($supportMessage['sender_role'] ?? '')));
        if ($senderRole === 'user' && !empty($supportMessage['user_id'])) {
            $recipientType = 'user';
            $recipientId = (int)$supportMessage['user_id'];
        } elseif ($senderRole === 'company' && !empty($supportMessage['company_id'])) {
            $recipientType = 'company';
            $recipientId = (int)$supportMessage['company_id'];
        }
    }

    if ($recipientType !== '' && $recipientId > 0) {
        $subject = trim((string)($supportMessage['subject'] ?? 'Contact Support'));
        $replyPreview = preg_replace('/\s+/u', ' ', $replyMessage) ?? $replyMessage;
        $replyPreview = trim($replyPreview);

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($replyPreview, 'UTF-8') > 180) {
                $replyPreview = mb_substr($replyPreview, 0, 177, 'UTF-8') . '...';
            }
        } elseif (strlen($replyPreview) > 180) {
            $replyPreview = substr($replyPreview, 0, 177) . '...';
        }

        $notificationMessage = 'Admin replied to your support request';
        if ($subject !== '') {
            $notificationMessage .= ': ' . $subject;
        }
        if ($replyPreview !== '') {
            $notificationMessage .= "\n\n" . $replyPreview;
        }

        notify_create(
            $recipientType,
            $recipientId,
            'Contact Support Reply',
            $notificationMessage,
            rtrim(JOBHUB_APP_URL, '/') . '/support-message.php?id=' . $messageId,
            'info',
            'support_reply',
            $messageId
        );
    }
}

if (!$result['success']) {
    support_set_flash('admin', 'danger', $result['message']);
} elseif ($sendEmail && empty($result['email_sent'])) {
    support_set_flash('admin', 'warning', 'Reply saved, but email was not sent: ' . ($result['email_message'] ?? 'Unknown error.'));
} else {
    support_set_flash('admin', 'success', 'Reply saved successfully.');
}

header('Location: support-view.php?id=' . $messageId);
exit;
