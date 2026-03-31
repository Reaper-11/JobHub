<?php
// includes/notifications.php

if (!function_exists('notify_table_columns')) {
    function notify_table_columns(): array {
        global $conn;

        static $columns = null;
        if ($columns !== null) {
            return $columns;
        }

        $columns = [];
        if (!$conn) {
            return $columns;
        }

        $result = $conn->query("SHOW COLUMNS FROM notifications");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $columns[] = $row['Field'];
            }
            $result->close();
        }

        return $columns;
    }
}

if (!function_exists('notify_has_column')) {
    function notify_has_column($column): bool {
        return in_array($column, notify_table_columns(), true);
    }
}

if (!function_exists('notify_allowed_types')) {
    function notify_allowed_types(): array {
        global $conn;

        static $types = null;
        if ($types !== null) {
            return $types;
        }

        $types = ['info', 'success', 'warning', 'danger'];
        if (!$conn || !notify_has_column('type')) {
            return $types;
        }

        $result = $conn->query("SHOW COLUMNS FROM notifications LIKE 'type'");
        if ($result) {
            $row = $result->fetch_assoc() ?: null;
            $result->close();

            if ($row && preg_match_all("/'([^']+)'/", (string)($row['Type'] ?? ''), $matches)) {
                $parsedTypes = array_values(array_filter(array_map('trim', $matches[1] ?? [])));
                if (!empty($parsedTypes)) {
                    $types = $parsedTypes;
                }
            }
        }

        return $types;
    }
}

if (!function_exists('notify_normalize_recipient_type')) {
    function notify_normalize_recipient_type($recipientType): string {
        $recipientType = strtolower(trim((string)$recipientType));

        return match ($recipientType) {
            'company' => 'company',
            'admin' => 'admin',
            default => 'user',
        };
    }
}

if (!function_exists('notify_status_type')) {
    function notify_status_type($status): string {
        $status = strtolower(trim((string)$status));

        return match ($status) {
            'approved', 'selected', 'hired' => 'success',
            'rejected' => 'danger',
            'shortlisted', 'interview' => 'info',
            default => 'warning',
        };
    }
}

if (!function_exists('notify_status_label')) {
    function notify_status_label($status): string {
        return ucwords(str_replace('_', ' ', strtolower(trim((string)$status))));
    }
}

if (!function_exists('notify_normalize_link')) {
    function notify_normalize_link($recipientType, $link): string {
        $recipientType = notify_normalize_recipient_type($recipientType);
        $link = trim((string)$link);

        if ($link === '') {
            return '';
        }

        $link = str_replace('\\', '/', $link);

        if (preg_match('~^(?:[a-z][a-z0-9+.-]*:|//|/|#|\?)~i', $link)) {
            return $link;
        }

        $link = preg_replace('#^\./+#', '', $link);

        if ($recipientType === 'company' && stripos($link, 'company/') === 0) {
            $link = substr($link, strlen('company/'));
        }

        return $link;
    }
}

if (!function_exists('notify_create')) {
    function notify_create($recipientType, $recipientId, $title, $message, $link = '', $type = 'info', $relatedType = null, $relatedId = null): bool {
        global $conn;

        $recipientType = notify_normalize_recipient_type($recipientType);
        $recipientId = (int)$recipientId;
        $title = trim((string)$title);
        $message = trim((string)$message);
        $link = notify_normalize_link($recipientType, $link);
        $requestedType = strtolower(trim((string)$type));
        $allowedTypes = notify_allowed_types();
        $type = in_array($requestedType, $allowedTypes, true)
            ? $requestedType
            : (in_array('info', $allowedTypes, true) ? 'info' : ($allowedTypes[0] ?? 'info'));
        $relatedType = $relatedType !== null ? trim((string)$relatedType) : null;
        $relatedId = $relatedId !== null ? (int)$relatedId : null;

        if ($recipientId <= 0 || $title === '' || $message === '' || !$conn) {
            return false;
        }

        $columns = ['recipient_type', 'recipient_id', 'title', 'message'];
        $placeholders = ['?', '?', '?', '?'];
        $types = 'siss';
        $params = [$recipientType, $recipientId, $title, $message];

        if (notify_has_column('type')) {
            $columns[] = 'type';
            $placeholders[] = '?';
            $types .= 's';
            $params[] = $type;
        }

        if (notify_has_column('related_type')) {
            $columns[] = 'related_type';
            $placeholders[] = '?';
            $types .= 's';
            $params[] = $relatedType;
        }

        if (notify_has_column('related_id')) {
            $columns[] = 'related_id';
            $placeholders[] = '?';
            $types .= 'i';
            $params[] = $relatedId;
        }

        if (notify_has_column('link')) {
            $columns[] = 'link';
            $placeholders[] = '?';
            $types .= 's';
            $params[] = $link;
        }

        $columns[] = 'is_read';
        $placeholders[] = '0';
        $columns[] = 'created_at';
        $placeholders[] = 'NOW()';

        $sql = "INSERT INTO notifications (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param($types, ...$params);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }
}

if (!function_exists('notify_create_company_verification_review')) {
    function notify_create_company_verification_review(int $companyId, string $title, string $message, string $link = 'company-notifications.php'): bool {
        $companyId = (int)$companyId;
        $title = trim($title);
        $message = trim($message);

        if ($companyId <= 0 || $title === '' || $message === '') {
            return false;
        }

        $type = in_array('verification', notify_allowed_types(), true) ? 'verification' : 'info';

        return notify_create(
            'company',
            $companyId,
            $title,
            $message,
            $link,
            $type,
            'verification',
            $companyId
        );
    }
}

if (!function_exists('notify_unread_count')) {
    function notify_unread_count($recipientType, $recipientId): int {
        $recipientType = notify_normalize_recipient_type($recipientType);
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

if (!function_exists('notify_is_verification_notification')) {
    function notify_is_verification_notification(array $notification): bool {
        $type = strtolower(trim((string)($notification['type'] ?? '')));
        $relatedType = strtolower(trim((string)($notification['related_type'] ?? '')));
        $title = strtolower(trim((string)($notification['title'] ?? '')));

        if ($type === 'verification' || $relatedType === 'verification') {
            return true;
        }

        return $title === 'company verification approved' || $title === 'company verification rejected';
    }
}

if (!function_exists('notify_fetch')) {
    function notify_fetch($recipientType, $recipientId, $limit = 50): array {
        $recipientType = notify_normalize_recipient_type($recipientType);
        $recipientId = (int)$recipientId;
        $limit = max(1, min(200, (int)$limit));
        if ($recipientId <= 0) {
            return [];
        }
        $select = ['id', 'title', 'message', 'is_read', 'created_at'];
        $select[] = notify_has_column('type') ? 'type' : "'info' AS type";
        $select[] = notify_has_column('related_type') ? 'related_type' : "NULL AS related_type";
        $select[] = notify_has_column('related_id') ? 'related_id' : "NULL AS related_id";
        $select[] = notify_has_column('link') ? 'link' : "'' AS link";

        $sql = "SELECT " . implode(', ', $select) . " FROM notifications WHERE recipient_type = ? AND recipient_id = ? ORDER BY created_at DESC, id DESC LIMIT {$limit}";
        $rows = db_query_all($sql, "si", [$recipientType, $recipientId]);

        foreach ($rows as &$row) {
            $row['link'] = notify_normalize_link($recipientType, $row['link'] ?? '');
        }
        unset($row);

        return array_values(array_filter($rows, static function (array $row): bool {
            return trim((string)($row['title'] ?? '')) !== '' && trim((string)($row['message'] ?? '')) !== '';
        }));
    }
}

if (!function_exists('notify_fetch_related')) {
    function notify_fetch_related($recipientType, $recipientId, string $relatedType, int $limit = 50, bool $onlyUnread = false): array {
        $recipientType = notify_normalize_recipient_type($recipientType);
        $recipientId = (int)$recipientId;
        $relatedType = trim($relatedType);
        $limit = max(1, min(200, $limit));

        if ($recipientId <= 0 || $relatedType === '') {
            return [];
        }

        if (notify_has_column('related_type')) {
            $select = ['id', 'title', 'message', 'is_read', 'created_at'];
            $select[] = notify_has_column('type') ? 'type' : "'info' AS type";
            $select[] = 'related_type';
            $select[] = notify_has_column('related_id') ? 'related_id' : "NULL AS related_id";
            $select[] = notify_has_column('link') ? 'link' : "'' AS link";

            $sql = "SELECT " . implode(', ', $select) . "
                    FROM notifications
                    WHERE recipient_type = ? AND recipient_id = ? AND related_type = ?";
            $types = "sis";
            $params = [$recipientType, $recipientId, $relatedType];

            if ($onlyUnread) {
                $sql .= " AND is_read = 0";
            }

            $sql .= " ORDER BY created_at DESC, id DESC LIMIT {$limit}";
            $rows = db_query_all($sql, $types, $params);
        } else {
            $rows = array_values(array_filter(
                notify_fetch($recipientType, $recipientId, $limit * 3),
                static function (array $row) use ($onlyUnread): bool {
                    if (!notify_is_verification_notification($row)) {
                        return false;
                    }

                    return !$onlyUnread || (int)($row['is_read'] ?? 0) === 0;
                }
            ));
        }

        foreach ($rows as &$row) {
            $row['link'] = notify_normalize_link($recipientType, $row['link'] ?? '');
        }
        unset($row);

        return array_slice(array_values(array_filter($rows, static function (array $row): bool {
            return trim((string)($row['title'] ?? '')) !== '' && trim((string)($row['message'] ?? '')) !== '';
        })), 0, $limit);
    }
}

if (!function_exists('notify_latest_unread_verification')) {
    function notify_latest_unread_verification($recipientType, $recipientId): ?array {
        $rows = notify_fetch_related($recipientType, $recipientId, 'verification', 1, true);
        return $rows[0] ?? null;
    }
}

if (!function_exists('notify_mark_all_read')) {
    function notify_mark_all_read($recipientType, $recipientId): void {
        global $conn;
        $recipientType = notify_normalize_recipient_type($recipientType);
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
        $recipientType = notify_normalize_recipient_type($recipientType);
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

if (!function_exists('notify_exists')) {
    function notify_exists($recipientType, $recipientId, $title, $relatedType = null, $relatedId = null): bool {
        $recipientType = notify_normalize_recipient_type($recipientType);
        $recipientId = (int)$recipientId;
        $title = trim((string)$title);
        $relatedType = $relatedType !== null ? trim((string)$relatedType) : null;
        $relatedId = $relatedId !== null ? (int)$relatedId : null;

        if ($recipientId <= 0 || $title === '') {
            return false;
        }

        $sql = "SELECT id FROM notifications WHERE recipient_type = ? AND recipient_id = ? AND title = ?";
        $types = 'sis';
        $params = [$recipientType, $recipientId, $title];

        if (notify_has_column('related_type')) {
            if ($relatedType === null) {
                $sql .= " AND related_type IS NULL";
            } else {
                $sql .= " AND related_type = ?";
                $types .= 's';
                $params[] = $relatedType;
            }
        }

        if (notify_has_column('related_id')) {
            if ($relatedId === null) {
                $sql .= " AND related_id IS NULL";
            } else {
                $sql .= " AND related_id = ?";
                $types .= 'i';
                $params[] = $relatedId;
            }
        }

        $sql .= " LIMIT 1";

        return !empty(db_query_all($sql, $types, $params));
    }
}

if (!function_exists('notify_create_unique')) {
    function notify_create_unique($recipientType, $recipientId, $title, $message, $link = '', $type = 'info', $relatedType = null, $relatedId = null): bool {
        if (notify_exists($recipientType, $recipientId, $title, $relatedType, $relatedId)) {
            return false;
        }

        return notify_create($recipientType, $recipientId, $title, $message, $link, $type, $relatedType, $relatedId);
    }
}
