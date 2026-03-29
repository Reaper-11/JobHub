<?php
require_once __DIR__ . '/mailer.php';

function support_table_exists(mysqli $conn): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    $result = $conn->query("SHOW TABLES LIKE 'support_messages'");
    $exists = $result instanceof mysqli_result && $result->num_rows > 0;

    if ($result instanceof mysqli_result) {
        $result->close();
    }

    return $exists;
}

function support_notifications_table_exists(mysqli $conn): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    $result = $conn->query("SHOW TABLES LIKE 'notifications'");
    $exists = $result instanceof mysqli_result && $result->num_rows > 0;

    if ($result instanceof mysqli_result) {
        $result->close();
    }

    return $exists;
}

function support_admin_notifications_supported(mysqli $conn): bool
{
    static $supported = null;
    if ($supported !== null) {
        return $supported;
    }

    $supported = false;
    if (!support_notifications_table_exists($conn)) {
        return false;
    }

    $result = $conn->query("SHOW COLUMNS FROM notifications LIKE 'recipient_type'");
    if ($result instanceof mysqli_result) {
        $row = $result->fetch_assoc();
        $supported = stripos((string)($row['Type'] ?? ''), "'admin'") !== false;
        $result->close();
    }

    return $supported;
}

function support_flash_key(string $channel): string
{
    return 'support_flash_' . preg_replace('/[^a-z0-9_]/i', '', strtolower($channel));
}

function support_old_input_key(string $channel): string
{
    return 'support_old_input_' . preg_replace('/[^a-z0-9_]/i', '', strtolower($channel));
}

function support_set_flash(string $channel, string $type, string $message): void
{
    $_SESSION[support_flash_key($channel)] = [
        'type' => $type,
        'message' => $message,
    ];
}

function support_get_flash(string $channel): ?array
{
    $key = support_flash_key($channel);
    if (empty($_SESSION[$key]) || !is_array($_SESSION[$key])) {
        return null;
    }

    $flash = $_SESSION[$key];
    unset($_SESSION[$key]);

    return $flash;
}

function support_set_old_input(string $channel, array $data): void
{
    $_SESSION[support_old_input_key($channel)] = $data;
}

function support_get_old_input(string $channel): array
{
    $key = support_old_input_key($channel);
    if (empty($_SESSION[$key]) || !is_array($_SESSION[$key])) {
        return [];
    }

    $oldInput = $_SESSION[$key];
    unset($_SESSION[$key]);

    return $oldInput;
}

function support_role_label(?string $role): string
{
    return match (strtolower((string)$role)) {
        'user' => 'User',
        'company' => 'Company',
        default => 'Guest',
    };
}

function support_status_label(string $status): string
{
    return match (strtolower($status)) {
        'read' => 'Read',
        'replied' => 'Replied',
        'resolved' => 'Resolved',
        default => 'New',
    };
}

function support_status_badge_class(string $status): string
{
    return match (strtolower($status)) {
        'read' => 'bg-info',
        'replied' => 'bg-primary',
        'resolved' => 'bg-success',
        default => 'bg-warning text-dark',
    };
}

function support_sender_context(mysqli $conn): array
{
    $context = [
        'sender_name' => '',
        'sender_email' => '',
        'sender_phone' => '',
        'sender_role' => '',
        'user_id' => null,
        'company_id' => null,
    ];

    if (!empty($_SESSION['user_id'])) {
        $userId = (int)$_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT name, email, phone FROM users WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($user) {
                $context['sender_name'] = (string)($user['name'] ?? '');
                $context['sender_email'] = (string)($user['email'] ?? '');
                $context['sender_phone'] = (string)($user['phone'] ?? '');
                $context['sender_role'] = 'user';
                $context['user_id'] = $userId;
            }
        }
    } elseif (!empty($_SESSION['company_id'])) {
        $companyId = (int)$_SESSION['company_id'];
        $stmt = $conn->prepare("SELECT name, email, verification_phone FROM companies WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $companyId);
            $stmt->execute();
            $company = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($company) {
                $context['sender_name'] = (string)($company['name'] ?? '');
                $context['sender_email'] = (string)($company['email'] ?? '');
                $context['sender_phone'] = (string)($company['verification_phone'] ?? '');
                $context['sender_role'] = 'company';
                $context['company_id'] = $companyId;
            }
        }
    }

    return $context;
}

function support_sender_is_authenticated(array $context): bool
{
    if (($context['sender_role'] ?? '') === 'user' && !empty($context['user_id'])) {
        return true;
    }

    if (($context['sender_role'] ?? '') === 'company' && !empty($context['company_id'])) {
        return true;
    }

    return false;
}

function support_require_contact_access(mysqli $conn): array
{
    $context = support_sender_context($conn);
    if (support_sender_is_authenticated($context)) {
        return $context;
    }

    if (!empty($_SESSION['admin_id'])) {
        support_set_flash('admin', 'info', 'Admins can manage support messages from the admin panel.');
        header('Location: admin/support-messages.php');
        exit;
    }

    $_SESSION['auth_error'] = 'Please log in to contact support.';
    header('Location: login.php');
    exit;
}

function support_validate_submission(array $source, array $context): array
{
    $data = [
        'sender_name' => trim((string)($source['sender_name'] ?? $context['sender_name'] ?? '')),
        'sender_email' => strtolower(trim((string)($source['sender_email'] ?? $context['sender_email'] ?? ''))),
        'sender_phone' => trim((string)($source['sender_phone'] ?? $context['sender_phone'] ?? '')),
        'sender_role' => $context['sender_role'] ?? '',
        'user_id' => $context['user_id'] ?? null,
        'company_id' => $context['company_id'] ?? null,
        'subject' => trim((string)($source['subject'] ?? '')),
        'message' => trim((string)($source['message'] ?? '')),
    ];

    $errors = [];

    if (!support_sender_is_authenticated($context)) {
        $errors[] = 'Please log in to contact support.';
    }

    if (!in_array($data['sender_role'], ['user', 'company'], true)) {
        $errors[] = 'Invalid sender role.';
    }

    if ($data['sender_name'] === '') {
        $errors[] = 'Full name is required.';
    } elseif (strlen($data['sender_name']) < 2 || strlen($data['sender_name']) > 120) {
        $errors[] = 'Full name must be between 2 and 120 characters.';
    }

    if ($data['sender_email'] === '' || !filter_var($data['sender_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }

    if ($data['sender_phone'] !== '' && !preg_match('/^[0-9+\-\s()]{7,30}$/', $data['sender_phone'])) {
        $errors[] = 'Phone number format is invalid.';
    }

    if ($data['subject'] === '') {
        $errors[] = 'Subject is required.';
    } elseif (strlen($data['subject']) > 200) {
        $errors[] = 'Subject must be 200 characters or less.';
    }

    if ($data['message'] === '') {
        $errors[] = 'Message is required.';
    } elseif (strlen($data['message']) > 5000) {
        $errors[] = 'Message must be 5000 characters or less.';
    }

    return [
        'data' => $data,
        'errors' => $errors,
    ];
}

function support_create_admin_notifications(mysqli $conn, int $messageId, array $data): void
{
    if ($messageId <= 0 || !support_admin_notifications_supported($conn)) {
        return;
    }

    $adminIds = [];
    $adminResult = $conn->query("SELECT id FROM admins");
    if ($adminResult instanceof mysqli_result) {
        while ($row = $adminResult->fetch_assoc()) {
            $adminIds[] = (int)$row['id'];
        }
        $adminResult->close();
    }

    if (empty($adminIds)) {
        return;
    }

    $stmt = $conn->prepare("
        INSERT INTO notifications (
            recipient_type, recipient_id, title, message, type, related_type, related_id, link, is_read, created_at
        ) VALUES ('admin', ?, ?, ?, 'info', 'support_message', ?, ?, 0, NOW())
    ");

    if (!$stmt) {
        return;
    }

    $title = 'New support message';
    $message = support_role_label($data['sender_role']) . ' sent a support request: ' . $data['subject'];
    $link = 'admin/support-view.php?id=' . $messageId;

    foreach ($adminIds as $adminId) {
        $relatedId = $messageId;
        $stmt->bind_param("issis", $adminId, $title, $message, $relatedId, $link);
        $stmt->execute();
    }

    $stmt->close();
}

function support_create_message(mysqli $conn, array $data): int
{
    if (!support_table_exists($conn)) {
        return 0;
    }

    if (!in_array($data['sender_role'] ?? '', ['user', 'company'], true)) {
        return 0;
    }

    $senderPhone = $data['sender_phone'] !== '' ? $data['sender_phone'] : null;
    $userId = !empty($data['user_id']) ? (int)$data['user_id'] : null;
    $companyId = !empty($data['company_id']) ? (int)$data['company_id'] : null;

    $stmt = $conn->prepare("
        INSERT INTO support_messages (
            sender_name, sender_email, sender_phone, sender_role, user_id, company_id,
            subject, message, status, is_read, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'new', 0, NOW(), NOW())
    ");

    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param(
        "ssssiiss",
        $data['sender_name'],
        $data['sender_email'],
        $senderPhone,
        $data['sender_role'],
        $userId,
        $companyId,
        $data['subject'],
        $data['message']
    );

    $ok = $stmt->execute();
    $newId = $ok ? (int)$conn->insert_id : 0;
    $stmt->close();

    if ($newId > 0) {
        support_create_admin_notifications($conn, $newId, $data);
    }

    if ($newId > 0 && function_exists('log_activity')) {
        $actorId = $userId ?: $companyId;
        log_activity(
            $conn,
            $actorId ?: null,
            $data['sender_role'],
            'support_message_submitted',
            'Support request submitted: ' . $data['subject'],
            'support_message',
            $newId
        );
    }

    return $newId;
}

function support_fetch_counts(mysqli $conn): array
{
    if (!support_table_exists($conn)) {
        return [
            'total' => 0,
            'new' => 0,
            'unread' => 0,
            'replied' => 0,
            'resolved' => 0,
        ];
    }

    return [
        'total' => (int)db_query_value("SELECT COUNT(*) FROM support_messages", '', [], 0),
        'new' => (int)db_query_value("SELECT COUNT(*) FROM support_messages WHERE status = 'new'", '', [], 0),
        'unread' => (int)db_query_value("SELECT COUNT(*) FROM support_messages WHERE is_read = 0", '', [], 0),
        'replied' => (int)db_query_value("SELECT COUNT(*) FROM support_messages WHERE status = 'replied'", '', [], 0),
        'resolved' => (int)db_query_value("SELECT COUNT(*) FROM support_messages WHERE status = 'resolved'", '', [], 0),
    ];
}

function support_fetch_messages_page(mysqli $conn, int $page, int $perPage): array
{
    if (!support_table_exists($conn)) {
        return [];
    }

    $page = max(1, $page);
    $perPage = max(1, min(50, $perPage));
    $offset = ($page - 1) * $perPage;

    $stmt = $conn->prepare("
        SELECT id, sender_name, sender_email, sender_phone, sender_role, subject, status, is_read, created_at
        FROM support_messages
        ORDER BY created_at DESC, id DESC
        LIMIT ?, ?
    ");

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param("ii", $offset, $perPage);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}

function support_fetch_message(mysqli $conn, int $messageId): ?array
{
    if ($messageId <= 0 || !support_table_exists($conn)) {
        return null;
    }

    $stmt = $conn->prepare("
        SELECT id, sender_name, sender_email, sender_phone, sender_role, user_id, company_id,
               subject, message, admin_reply, status, is_read, reply_email_sent,
               reply_email_error, replied_by_admin_id, replied_at, created_at, updated_at
        FROM support_messages
        WHERE id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("i", $messageId);
    $stmt->execute();
    $message = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    return $message;
}

function support_mark_read_state(mysqli $conn, int $messageId, bool $markAsRead): bool
{
    if ($messageId <= 0 || !support_table_exists($conn) || !support_fetch_message($conn, $messageId)) {
        return false;
    }

    if ($markAsRead) {
        $stmt = $conn->prepare("
            UPDATE support_messages
            SET is_read = 1,
                status = CASE WHEN status = 'new' THEN 'read' ELSE status END,
                updated_at = NOW()
            WHERE id = ?
        ");
    } else {
        $stmt = $conn->prepare("
            UPDATE support_messages
            SET is_read = 0,
                status = CASE WHEN status = 'read' THEN 'new' ELSE status END,
                updated_at = NOW()
            WHERE id = ?
        ");
    }

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("i", $messageId);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

function support_mark_resolved(mysqli $conn, int $messageId): bool
{
    if ($messageId <= 0 || !support_table_exists($conn) || !support_fetch_message($conn, $messageId)) {
        return false;
    }

    $stmt = $conn->prepare("
        UPDATE support_messages
        SET status = 'resolved',
            is_read = 1,
            updated_at = NOW()
        WHERE id = ?
    ");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("i", $messageId);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

function support_delete_message(mysqli $conn, int $messageId): bool
{
    if ($messageId <= 0 || !support_table_exists($conn) || !support_fetch_message($conn, $messageId)) {
        return false;
    }

    $stmt = $conn->prepare("DELETE FROM support_messages WHERE id = ?");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("i", $messageId);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

function support_reply_message(mysqli $conn, int $messageId, string $replyMessage, int $adminId, bool $sendEmail): array
{
    $replyMessage = trim($replyMessage);
    if ($replyMessage === '') {
        return [
            'success' => false,
            'message' => 'Reply message is required.',
            'email_sent' => false,
        ];
    }

    $supportMessage = support_fetch_message($conn, $messageId);
    if (!$supportMessage) {
        return [
            'success' => false,
            'message' => 'Support message not found.',
            'email_sent' => false,
        ];
    }

    $stmt = $conn->prepare("
        UPDATE support_messages
        SET admin_reply = ?,
            status = 'replied',
            is_read = 1,
            replied_by_admin_id = ?,
            replied_at = NOW(),
            reply_email_sent = 0,
            reply_email_error = NULL,
            updated_at = NOW()
        WHERE id = ?
    ");

    if (!$stmt) {
        return [
            'success' => false,
            'message' => 'Could not save admin reply.',
            'email_sent' => false,
        ];
    }

    $stmt->bind_param("sii", $replyMessage, $adminId, $messageId);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        return [
            'success' => false,
            'message' => 'Could not save admin reply.',
            'email_sent' => false,
        ];
    }

    $emailSent = false;
    $emailMessage = 'Reply saved successfully.';

    if ($sendEmail) {
        $emailResult = jobhub_send_support_reply_email(
            (string)$supportMessage['sender_email'],
            (string)$supportMessage['sender_name'],
            'JobHub Support Reply: ' . (string)$supportMessage['subject'],
            $replyMessage
        );

        $emailSent = $emailResult['success'];
        $emailMessage = $emailResult['message'];

        $emailSentValue = $emailSent ? 1 : 0;
        $emailError = $emailSent ? null : substr($emailMessage, 0, 255);
        $updateEmailStmt = $conn->prepare("
            UPDATE support_messages
            SET reply_email_sent = ?,
                reply_email_error = ?
            WHERE id = ?
        ");

        if ($updateEmailStmt) {
            $updateEmailStmt->bind_param("isi", $emailSentValue, $emailError, $messageId);
            $updateEmailStmt->execute();
            $updateEmailStmt->close();
        }
    }

    if (function_exists('log_activity')) {
        log_activity(
            $conn,
            $adminId,
            'admin',
            'support_message_replied',
            'Admin replied to support message #' . $messageId,
            'support_message',
            $messageId
        );
    }

    return [
        'success' => true,
        'message' => 'Reply saved successfully.',
        'email_sent' => $emailSent,
        'email_message' => $emailMessage,
    ];
}
