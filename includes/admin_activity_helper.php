<?php

function activity_table_exists(mysqli $conn, string $table): bool
{
    $safeTable = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '{$safeTable}'");
    $exists = $result && $result->num_rows > 0;

    if ($result instanceof mysqli_result) {
        $result->close();
    }

    return $exists;
}

function activity_column_exists(mysqli $conn, string $table, string $column): bool
{
    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    $exists = $result && $result->num_rows > 0;

    if ($result instanceof mysqli_result) {
        $result->close();
    }

    return $exists;
}

function log_activity(
    mysqli $conn,
    ?int $userId,
    ?string $actorRole,
    string $activityType,
    string $description,
    ?string $targetType = null,
    ?int $targetId = null
): bool {
    if (!activity_table_exists($conn, 'activity_logs')) {
        return false;
    }

    $stmt = $conn->prepare("
        INSERT INTO activity_logs (user_id, actor_role, activity_type, description, target_type, target_id, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("issssi", $userId, $actorRole, $activityType, $description, $targetType, $targetId);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

function get_user_account_status(mysqli $conn, int $userId): string
{
    if ($userId <= 0 || !activity_column_exists($conn, 'users', 'account_status')) {
        return 'active';
    }

    $stmt = $conn->prepare("SELECT account_status, is_active FROM users WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return 'active';
    }

    $stmt->bind_param("i", $userId);
    if (!$stmt->execute()) {
        $stmt->close();
        return 'active';
    }

    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    if (!$row) {
        return 'removed';
    }

    $status = strtolower((string)($row['account_status'] ?? 'active'));
    if ($status === 'blocked' || $status === 'removed') {
        return $status;
    }

    return ((int)($row['is_active'] ?? 1) === 1) ? 'active' : 'blocked';
}

function enforce_user_session_status(mysqli $conn): void
{
    if (empty($_SESSION['user_id'])) {
        return;
    }

    $status = get_user_account_status($conn, (int)$_SESSION['user_id']);
    if ($status === 'active') {
        return;
    }

    unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['role']);

    if ($status === 'blocked') {
        $_SESSION['auth_error'] = 'Your account has been blocked by admin.';
        header('Location: ' . JOBHUB_APP_URL . 'login.php?blocked=1');
        exit;
    }

    $_SESSION['auth_error'] = 'Your account is no longer available.';
    header('Location: ' . JOBHUB_APP_URL . 'login.php?removed=1');
    exit;
}

function user_status_badge_class(string $status): string
{
    return match ($status) {
        'blocked' => 'bg-warning text-dark',
        'removed' => 'bg-danger',
        default => 'bg-success',
    };
}

function user_status_label(string $status): string
{
    return match ($status) {
        'blocked' => 'Blocked',
        'removed' => 'Removed',
        default => 'Active',
    };
}

function job_approval_badge_class(int $approvalValue): string
{
    return match ($approvalValue) {
        1 => 'bg-success',
        -1 => 'bg-danger',
        default => 'bg-warning text-dark',
    };
}

function job_approval_label(int $approvalValue): string
{
    return match ($approvalValue) {
        1 => 'Approved',
        -1 => 'Rejected',
        default => 'Pending',
    };
}
?>
