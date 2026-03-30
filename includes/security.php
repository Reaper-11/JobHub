<?php

function jobhub_validate_password_strength(string $password): ?string
{
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters long.';
    }

    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'Password must contain at least one letter and one number.';
    }

    return null;
}

function jobhub_validate_person_name(string $name): ?string
{
    if ($name === '') {
        return 'Full name is required.';
    }

    if (strlen($name) < 2 || strlen($name) > 120) {
        return 'Full name must be between 2 and 120 characters.';
    }

    if (!preg_match("/^[A-Za-z][A-Za-z .'-]+$/", $name)) {
        return 'Please enter a valid full name.';
    }

    return null;
}

function jobhub_validate_company_name(string $name): ?string
{
    if ($name === '') {
        return 'Company name is required.';
    }

    if (strlen($name) < 2 || strlen($name) > 150) {
        return 'Company name must be between 2 and 150 characters.';
    }

    if (!preg_match("/^[A-Za-z0-9][A-Za-z0-9 .,&()'\\/\\-]+$/", $name)) {
        return 'Please enter a valid company name.';
    }

    return null;
}

function jobhub_validate_location_value(string $location): ?string
{
    if ($location === '') {
        return 'Location is required.';
    }

    if (strlen($location) < 2 || strlen($location) > 200) {
        return 'Location must be between 2 and 200 characters.';
    }

    return null;
}

function jobhub_auth_rate_limit_key(string $scope): string
{
    return 'login_rate_limit_' . preg_replace('/[^a-z0-9_]/i', '', strtolower($scope));
}

function jobhub_auth_throttle_status(string $scope): array
{
    $key = jobhub_auth_rate_limit_key($scope);
    $state = $_SESSION[$key] ?? [
        'count' => 0,
        'window_started_at' => 0,
        'locked_until' => 0,
    ];

    $now = time();
    if (($state['locked_until'] ?? 0) > $now) {
        return [
            'allowed' => false,
            'retry_after' => (int)$state['locked_until'] - $now,
        ];
    }

    if (($state['locked_until'] ?? 0) !== 0 || (($state['window_started_at'] ?? 0) + 600) < $now) {
        unset($_SESSION[$key]);
    }

    return [
        'allowed' => true,
        'retry_after' => 0,
    ];
}

function jobhub_auth_register_failure(string $scope): void
{
    $key = jobhub_auth_rate_limit_key($scope);
    $state = $_SESSION[$key] ?? [
        'count' => 0,
        'window_started_at' => 0,
        'locked_until' => 0,
    ];

    $now = time();
    if (($state['window_started_at'] ?? 0) === 0 || (($state['window_started_at'] ?? 0) + 600) < $now) {
        $state['count'] = 0;
        $state['window_started_at'] = $now;
        $state['locked_until'] = 0;
    }

    $state['count'] = (int)($state['count'] ?? 0) + 1;

    if ($state['count'] >= 5) {
        $state['count'] = 0;
        $state['window_started_at'] = $now;
        $state['locked_until'] = $now + 300;
    }

    $_SESSION[$key] = $state;
}

function jobhub_auth_clear_failures(string $scope): void
{
    unset($_SESSION[jobhub_auth_rate_limit_key($scope)]);
}

function jobhub_is_supported_password_table(string $table): bool
{
    return in_array($table, ['accounts', 'users', 'companies', 'admins'], true);
}

function jobhub_verify_password_with_upgrade(mysqli $conn, string $table, int $accountId, string $password, string $storedPassword): bool
{
    if ($accountId <= 0 || $password === '' || $storedPassword === '' || !jobhub_is_supported_password_table($table)) {
        return false;
    }

    $passwordInfo = password_get_info($storedPassword);
    $isModernHash = !empty($passwordInfo['algo']);
    $verified = false;
    $needsUpgrade = false;

    if ($isModernHash) {
        // password_verify() safely checks the stored secure password hash.
        $verified = password_verify($password, $storedPassword);
        $needsUpgrade = $verified && password_needs_rehash($storedPassword, PASSWORD_DEFAULT);
    } else {
        $verified = hash_equals($storedPassword, $password);

        if (!$verified && strlen($storedPassword) === 32 && ctype_xdigit($storedPassword)) {
            $verified = hash_equals(strtolower($storedPassword), md5($password));
        }

        $needsUpgrade = $verified;
    }

    if ($verified && $needsUpgrade) {
        // password_hash() stores new and migrated passwords using a secure hash.
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE {$table} SET password = ? WHERE id = ? LIMIT 1");

        if ($stmt) {
            $stmt->bind_param('si', $newHash, $accountId);
            $stmt->execute();
            $stmt->close();
        }
    }

    return $verified;
}

function jobhub_company_session_status(mysqli $conn, int $companyId): string
{
    if ($companyId <= 0) {
        return 'removed';
    }

    $stmt = $conn->prepare("SELECT is_approved, is_active FROM companies WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return 'active';
    }

    $stmt->bind_param('i', $companyId);
    if (!$stmt->execute()) {
        $stmt->close();
        return 'active';
    }

    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    if (!$row) {
        return 'removed';
    }

    if ((int)($row['is_active'] ?? 1) !== 1) {
        return 'inactive';
    }

    if ((int)($row['is_approved'] ?? 0) === -1) {
        return 'rejected';
    }

    return 'active';
}

function enforce_company_session_status(mysqli $conn): void
{
    if (empty($_SESSION['company_id'])) {
        return;
    }

    $status = jobhub_company_session_status($conn, (int)$_SESSION['company_id']);
    if ($status === 'active') {
        return;
    }

    jobhub_clear_auth_session();
    $_SESSION['company_auth_error'] = $status === 'rejected'
        ? 'Your company account has been rejected by admin.'
        : 'Your company account is inactive. Please contact admin.';

    header('Location: ' . JOBHUB_APP_URL . 'login.php');
    exit;
}
