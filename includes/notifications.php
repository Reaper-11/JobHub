<?php
// includes/notifications.php

if (!function_exists('notify_create')) {
    function notify_create($recipientType, $recipientId, $title, $message, $link = ''): bool {
        global $conn;

        $recipientType = $recipientType === 'company' ? 'company' : 'user';
        $recipientId = (int)$recipientId;
        $title = trim((string)$title);
        $message = trim((string)$message);
        $link = trim((string)$link);

        if ($recipientId <= 0 || $title === '' || $message === '' || !$conn) {
            return false;
        }

        $stmt = $conn->prepare("INSERT INTO notifications (recipient_type, recipient_id, title, message, link, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param("sisss", $recipientType, $recipientId, $title, $message, $link);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok && defined('JOBHUB_EMAIL_ENABLED') && JOBHUB_EMAIL_ENABLED) {
            $email = notify_recipient_email($recipientType, $recipientId);
            if ($email !== '') {
                $subject = $title;
                notify_send_email($email, $subject, $message, $link);
            }
        }

        return $ok;
    }
}

if (!function_exists('notify_unread_count')) {
    function notify_unread_count($recipientType, $recipientId): int {
        $recipientType = $recipientType === 'company' ? 'company' : 'user';
        $recipientId = (int)$recipientId;
        if ($recipientId <= 0) {
            return 0;
        }
        $count = db_query_value(
            "SELECT COUNT(*) FROM notifications WHERE recipient_type = ? AND recipient_id = ? AND is_read = 0",
            "si",
            [$recipientType, $recipientId],
            0
        );
        return (int)$count;
    }
}

if (!function_exists('notify_fetch')) {
    function notify_fetch($recipientType, $recipientId, $limit = 50): array {
        $recipientType = $recipientType === 'company' ? 'company' : 'user';
        $recipientId = (int)$recipientId;
        $limit = max(1, min(200, (int)$limit));
        if ($recipientId <= 0) {
            return [];
        }
        $sql = "SELECT id, title, message, link, is_read, created_at FROM notifications WHERE recipient_type = ? AND recipient_id = ? ORDER BY created_at DESC LIMIT {$limit}";
        return db_query_all($sql, "si", [$recipientType, $recipientId]);
    }
}

if (!function_exists('notify_mark_all_read')) {
    function notify_mark_all_read($recipientType, $recipientId): void {
        global $conn;
        $recipientType = $recipientType === 'company' ? 'company' : 'user';
        $recipientId = (int)$recipientId;
        if ($recipientId <= 0 || !$conn) {
            return;
        }
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE recipient_type = ? AND recipient_id = ?");
        if (!$stmt) {
            return;
        }
        $stmt->bind_param("si", $recipientType, $recipientId);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('notify_mark_read')) {
    function notify_mark_read($recipientType, $recipientId, $notificationId): void {
        global $conn;
        $recipientType = $recipientType === 'company' ? 'company' : 'user';
        $recipientId = (int)$recipientId;
        $notificationId = (int)$notificationId;
        if ($recipientId <= 0 || $notificationId <= 0 || !$conn) {
            return;
        }
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND recipient_type = ? AND recipient_id = ?");
        if (!$stmt) {
            return;
        }
        $stmt->bind_param("isi", $notificationId, $recipientType, $recipientId);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('notify_recipient_email')) {
    function notify_recipient_email($recipientType, $recipientId): string {
        $recipientType = $recipientType === 'company' ? 'company' : 'user';
        $recipientId = (int)$recipientId;
        if ($recipientId <= 0) {
            return '';
        }
        if ($recipientType === 'company') {
            return (string)db_query_value("SELECT email FROM companies WHERE id = ?", "i", [$recipientId], '');
        }
        return (string)db_query_value("SELECT email FROM users WHERE id = ?", "i", [$recipientId], '');
    }
}

if (!function_exists('notify_send_email')) {
    function notify_send_email($to, $subject, $message, $link = ''): bool {
        if (!defined('JOBHUB_EMAIL_ENABLED') || !JOBHUB_EMAIL_ENABLED) {
            return false;
        }
        $to = trim((string)$to);
        if ($to === '') {
            return false;
        }

        $subject = trim((string)$subject);
        $message = trim((string)$message);
        $link = trim((string)$link);

        $safeTitle = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
        $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));

        $actionUrl = '';
        if ($link !== '') {
            $base = defined('JOBHUB_APP_URL') ? rtrim(JOBHUB_APP_URL, '/') . '/' : '';
            $actionUrl = (strpos($link, 'http://') === 0 || strpos($link, 'https://') === 0) ? $link : $base . ltrim($link, '/');
        }

        $buttonHtml = $actionUrl !== ''
            ? '<p style="margin:16px 0;"><a href="' . htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;padding:10px 16px;background:#0d1b2a;color:#ffffff;text-decoration:none;border-radius:4px;">View</a></p>'
            : '';

        $body = "<div style=\"font-family:Arial,sans-serif;font-size:14px;line-height:1.6;color:#222;\">" .
            "<h2 style=\"margin:0 0 12px;\">{$safeTitle}</h2>" .
            "<p style=\"margin:0 0 12px;\">{$safeMessage}</p>" .
            $buttonHtml .
            "<p style=\"color:#666;font-size:12px;margin-top:24px;\">This is an automated message from JobHub.</p>" .
            "</div>";

        $fromEmail = defined('JOBHUB_EMAIL_FROM') ? JOBHUB_EMAIL_FROM : 'no-reply@jobhub.local';
        $fromName = defined('JOBHUB_EMAIL_FROM_NAME') ? JOBHUB_EMAIL_FROM_NAME : 'JobHub';

        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=UTF-8';
        $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';

        return @mail($to, $subject, $body, implode("\r\n", $headers));
    }
}
