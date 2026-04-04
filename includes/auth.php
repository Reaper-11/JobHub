<?php

require_once __DIR__ . '/session.php';

if (!function_exists('jobhub_table_exists')) {
    function jobhub_table_exists(mysqli $conn, string $table): bool
    {
        static $cache = [];

        $key = $conn->thread_id . ':' . strtolower($table);
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $safeTable = $conn->real_escape_string($table);
        $result = $conn->query("SHOW TABLES LIKE '{$safeTable}'");
        $exists = $result instanceof mysqli_result && $result->num_rows > 0;

        if ($result instanceof mysqli_result) {
            $result->close();
        }

        $cache[$key] = $exists;
        return $exists;
    }
}

if (!function_exists('jobhub_column_exists')) {
    function jobhub_column_exists(mysqli $conn, string $table, string $column): bool
    {
        static $cache = [];

        $key = $conn->thread_id . ':' . strtolower($table) . ':' . strtolower($column);
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        if (!jobhub_table_exists($conn, $table)) {
            $cache[$key] = false;
            return false;
        }

        $safeTable = $conn->real_escape_string($table);
        $safeColumn = $conn->real_escape_string($column);
        $result = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
        $exists = $result instanceof mysqli_result && $result->num_rows > 0;

        if ($result instanceof mysqli_result) {
            $result->close();
        }

        $cache[$key] = $exists;
        return $exists;
    }
}

if (!function_exists('jobhub_role_alias')) {
    function jobhub_role_alias(?string $role): ?string
    {
        $role = strtolower(trim((string) $role));

        return match ($role) {
            'jobseeker', 'seeker', 'user' => 'jobseeker',
            'company', 'employer' => 'company',
            'admin', 'administrator' => 'admin',
            default => null,
        };
    }
}

if (!function_exists('jobhub_profile_table_for_role')) {
    function jobhub_profile_table_for_role(string $role): ?string
    {
        return match (jobhub_role_alias($role)) {
            'jobseeker' => 'users',
            'company' => 'companies',
            'admin' => 'admins',
            default => null,
        };
    }
}

if (!function_exists('jobhub_role_home')) {
    function jobhub_role_home(?string $role = null): string
    {
        return match (jobhub_role_alias($role ?? current_role())) {
            'company' => 'company/company-dashboard.php',
            'admin' => 'admin/admin-dashboard.php',
            default => 'index.php',
        };
    }
}

if (!function_exists('jobhub_auth_url')) {
    function jobhub_auth_url(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return JOBHUB_APP_URL;
        }

        if (preg_match('~^https?://~i', $path)) {
            return $path;
        }

        if (str_starts_with($path, '/')) {
            return rtrim(JOBHUB_APP_URL, '/') . $path;
        }

        return JOBHUB_APP_URL . ltrim($path, '/');
    }
}

if (!function_exists('jobhub_redirect')) {
    function jobhub_redirect(string $path): void
    {
        header('Location: ' . jobhub_auth_url($path));
        exit;
    }
}

if (!function_exists('jobhub_set_auth_flash')) {
    function jobhub_set_auth_flash(string $type, string $message): void
    {
        $_SESSION['auth_flash'] = [
            'type' => $type,
            'message' => $message,
        ];
    }
}

if (!function_exists('jobhub_take_auth_flash')) {
    function jobhub_take_auth_flash(): ?array
    {
        $flash = $_SESSION['auth_flash'] ?? null;
        unset($_SESSION['auth_flash']);

        return is_array($flash) ? $flash : null;
    }
}

if (!function_exists('jobhub_clear_auth_session')) {
    function jobhub_clear_auth_session(): void
    {
        unset(
            $_SESSION['account_id'],
            $_SESSION['role'],
            $_SESSION['email'],
            $_SESSION['name'],
            $_SESSION['profile_id'],
            $_SESSION['profile_role'],
            $_SESSION['user_id'],
            $_SESSION['user_name'],
            $_SESSION['company_id'],
            $_SESSION['company_name'],
            $_SESSION['admin_id'],
            $_SESSION['admin_username'],
            $_SESSION['authenticated_role'],
            $_SESSION['preferred_category']
        );
    }
}

if (!function_exists('logout_user')) {
    function logout_user(): void
    {
        jobhub_clear_auth_session();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}

if (!function_exists('jobhub_destroy_session')) {
    function jobhub_destroy_session(): void
    {
        logout_user();
    }
}

if (!function_exists('jobhub_sync_session_from_account')) {
    function jobhub_sync_session_from_account(array $account): void
    {
        $role = jobhub_role_alias($account['role'] ?? null);
        if ($role === null) {
            return;
        }

        $displayName = trim((string) ($account['full_name'] ?? ''));
        if ($role === 'admin' && $displayName === '') {
            $displayName = trim((string) ($account['admin_username'] ?? 'Administrator'));
        }

        jobhub_clear_auth_session();

        $_SESSION['account_id'] = (int) ($account['id'] ?? 0);
        $_SESSION['role'] = $role;
        $_SESSION['email'] = (string) ($account['email'] ?? '');
        $_SESSION['name'] = $displayName;
        $_SESSION['authenticated_role'] = $role;

        if ($role === 'jobseeker') {
            $profileId = (int) ($account['user_profile_id'] ?? 0);
            if ($profileId > 0) {
                $_SESSION['profile_id'] = $profileId;
                $_SESSION['profile_role'] = $role;
                $_SESSION['user_id'] = $profileId;
                $_SESSION['user_name'] = $displayName;
            }
        } elseif ($role === 'company') {
            $profileId = (int) ($account['company_profile_id'] ?? 0);
            if ($profileId > 0) {
                $_SESSION['profile_id'] = $profileId;
                $_SESSION['profile_role'] = $role;
                $_SESSION['company_id'] = $profileId;
                $_SESSION['company_name'] = $displayName;
            }
        } elseif ($role === 'admin') {
            $profileId = (int) ($account['admin_profile_id'] ?? 0);
            if ($profileId > 0) {
                $_SESSION['profile_id'] = $profileId;
                $_SESSION['profile_role'] = $role;
                $_SESSION['admin_id'] = $profileId;
                $_SESSION['admin_username'] = trim((string) ($account['admin_username'] ?? $displayName));
            }
        }
    }
}

if (!function_exists('jobhub_login_account')) {
    function jobhub_login_account(array $account): void
    {
        jobhub_sync_session_from_account($account);
        session_regenerate_id(true);
    }
}

if (!function_exists('jobhub_complete_login')) {
    function jobhub_complete_login(string $accountType, int $profileId, string $displayName, ?string $userRole = null): void
    {
        global $conn;

        $role = jobhub_role_alias($accountType === 'seeker' ? ($userRole ?? 'jobseeker') : $accountType);
        if ($role === null) {
            return;
        }

        if ($conn instanceof mysqli) {
            $account = jobhub_find_account_by_profile($conn, $role, $profileId);
            if ($account) {
                jobhub_login_account($account);
                return;
            }
        }

        jobhub_clear_auth_session();

        if ($role === 'jobseeker') {
            $_SESSION['user_id'] = $profileId;
            $_SESSION['user_name'] = $displayName;
        } elseif ($role === 'company') {
            $_SESSION['company_id'] = $profileId;
            $_SESSION['company_name'] = $displayName;
        } elseif ($role === 'admin') {
            $_SESSION['admin_id'] = $profileId;
            $_SESSION['admin_username'] = $displayName;
        }

        $_SESSION['role'] = $role;
        $_SESSION['name'] = $displayName;
        $_SESSION['authenticated_role'] = $role;
        session_regenerate_id(true);
    }
}

if (!function_exists('current_role')) {
    function current_role(): ?string
    {
        if (!empty($_SESSION['role'])) {
            return jobhub_role_alias((string) $_SESSION['role']);
        }

        if (!empty($_SESSION['admin_id'])) {
            return 'admin';
        }

        if (!empty($_SESSION['company_id'])) {
            return 'company';
        }

        if (!empty($_SESSION['user_id'])) {
            return 'jobseeker';
        }

        return null;
    }
}

if (!function_exists('is_logged_in')) {
    function is_logged_in(): bool
    {
        return current_role() !== null;
    }
}

if (!function_exists('current_account_id')) {
    function current_account_id(): ?int
    {
        $accountId = (int) ($_SESSION['account_id'] ?? 0);
        return $accountId > 0 ? $accountId : null;
    }
}

if (!function_exists('current_profile_id')) {
    function current_profile_id(?string $role = null): ?int
    {
        $role = jobhub_role_alias($role ?? current_role());
        if ($role === null) {
            return null;
        }

        return match ($role) {
            'jobseeker' => (($id = (int) ($_SESSION['user_id'] ?? 0)) > 0 ? $id : null),
            'company' => (($id = (int) ($_SESSION['company_id'] ?? 0)) > 0 ? $id : null),
            'admin' => (($id = (int) ($_SESSION['admin_id'] ?? 0)) > 0 ? $id : null),
            default => null,
        };
    }
}

if (!function_exists('current_user_id')) {
    function current_user_id(): ?int
    {
        return current_profile_id('jobseeker');
    }
}

if (!function_exists('current_company_id')) {
    function current_company_id(): ?int
    {
        return current_profile_id('company');
    }
}

if (!function_exists('current_admin_id')) {
    function current_admin_id(): ?int
    {
        return current_profile_id('admin');
    }
}

if (!function_exists('jobhub_fetch_account_record')) {
    function jobhub_fetch_account_record(mysqli $conn, string $field, mixed $value): ?array
    {
        if (!jobhub_table_exists($conn, 'accounts')) {
            return null;
        }

        $field = $field === 'email' ? 'email' : 'id';

        $userJoin = '';
        $userSelect = "NULL AS user_profile_id, NULL AS user_account_status, 1 AS user_is_active";
        if (jobhub_column_exists($conn, 'users', 'account_id')) {
            $userJoin = "LEFT JOIN users u ON u.account_id = a.id";
            $userSelect = "u.id AS user_profile_id, u.account_status AS user_account_status, u.is_active AS user_is_active";
        }

        $companyJoin = '';
        $companySelect = "NULL AS company_profile_id, 1 AS company_is_active, 0 AS company_is_approved, NULL AS company_rejection_reason";
        if (jobhub_column_exists($conn, 'companies', 'account_id')) {
            $companyJoin = "LEFT JOIN companies c ON c.account_id = a.id";
            $companySelect = "c.id AS company_profile_id, c.is_active AS company_is_active, c.is_approved AS company_is_approved, c.rejection_reason AS company_rejection_reason";
        }

        $adminJoin = '';
        $adminSelect = "NULL AS admin_profile_id, '' AS admin_username";
        if (jobhub_column_exists($conn, 'admins', 'account_id')) {
            $adminJoin = "LEFT JOIN admins ad ON ad.account_id = a.id";
            $adminSelect = "ad.id AS admin_profile_id, ad.username AS admin_username";
        }

        $sql = "
            SELECT
                a.id,
                a.full_name,
                a.email,
                a.password,
                a.role,
                a.status,
                a.created_at,
                a.updated_at,
                {$userSelect},
                {$companySelect},
                {$adminSelect}
            FROM accounts a
            {$userJoin}
            {$companyJoin}
            {$adminJoin}
            WHERE a.{$field} = ?
            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return null;
        }

        if ($field === 'id') {
            $intValue = (int) $value;
            $stmt->bind_param('i', $intValue);
        } else {
            $stringValue = strtolower(trim((string) $value));
            $stmt->bind_param('s', $stringValue);
        }

        $stmt->execute();
        $account = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();

        return $account;
    }
}

if (!function_exists('jobhub_fetch_account_by_id')) {
    function jobhub_fetch_account_by_id(mysqli $conn, int $accountId): ?array
    {
        if ($accountId <= 0) {
            return null;
        }

        return jobhub_fetch_account_record($conn, 'id', $accountId);
    }
}

if (!function_exists('jobhub_fetch_account_by_email')) {
    function jobhub_fetch_account_by_email(mysqli $conn, string $email): ?array
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }

        return jobhub_fetch_account_record($conn, 'email', $email);
    }
}

if (!function_exists('jobhub_find_account_by_profile')) {
    function jobhub_find_account_by_profile(mysqli $conn, string $role, int $profileId): ?array
    {
        $role = jobhub_role_alias($role);
        $table = $role ? jobhub_profile_table_for_role($role) : null;
        if ($table === null || $profileId <= 0 || !jobhub_column_exists($conn, $table, 'account_id')) {
            return null;
        }

        $sql = "SELECT account_id FROM {$table} WHERE id = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $profileId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();

        if (!$row || empty($row['account_id'])) {
            return null;
        }

        return jobhub_fetch_account_by_id($conn, (int) $row['account_id']);
    }
}

if (!function_exists('jobhub_account_status_message')) {
    function jobhub_account_status_message(array $account): ?string
    {
        $role = jobhub_role_alias($account['role'] ?? null);
        $status = strtolower(trim((string) ($account['status'] ?? 'active')));

        if ($status === 'pending') {
            return 'Your account is pending approval.';
        }

        if ($status === 'blocked') {
            return 'Your account has been blocked by admin.';
        }

        if ($role === 'jobseeker') {
            $profileId = (int) ($account['user_profile_id'] ?? 0);
            if ($profileId <= 0) {
                return 'Your job seeker profile is unavailable.';
            }

            $userStatus = strtolower(trim((string) ($account['user_account_status'] ?? 'active')));
            if ($userStatus === 'blocked' || (int) ($account['user_is_active'] ?? 1) !== 1) {
                return 'Your account has been blocked by admin.';
            }

            if ($userStatus === 'removed') {
                return 'Your account is no longer available.';
            }
        } elseif ($role === 'company') {
            $profileId = (int) ($account['company_profile_id'] ?? 0);
            if ($profileId <= 0) {
                return 'Your company profile is unavailable.';
            }

            if ((int) ($account['company_is_active'] ?? 1) !== 1) {
                return 'Your company account is inactive. Please contact admin.';
            }

            if ((int) ($account['company_is_approved'] ?? 0) === -1) {
                $message = 'Your company account has been rejected by admin.';
                $reason = trim((string) ($account['company_rejection_reason'] ?? ''));
                if ($reason !== '') {
                    $message .= ' Reason: ' . $reason;
                }

                return $message;
            }
        } elseif ($role === 'admin') {
            if ((int) ($account['admin_profile_id'] ?? 0) <= 0) {
                return 'Your admin profile is unavailable.';
            }
        }

        if ($status === 'inactive') {
            return 'Your account is inactive. Please contact admin.';
        }

        return null;
    }
}

if (!function_exists('jobhub_account_password_matches')) {
    function jobhub_account_password_matches(mysqli $conn, array $account, string $password): bool
    {
        $storedPassword = (string) ($account['password'] ?? '');
        if ($storedPassword === '' || $password === '') {
            return false;
        }

        if (function_exists('jobhub_verify_password_with_upgrade')) {
            return jobhub_verify_password_with_upgrade($conn, 'accounts', (int) ($account['id'] ?? 0), $password, $storedPassword);
        }

        $passwordInfo = password_get_info($storedPassword);
        if (!empty($passwordInfo['algo'])) {
            return password_verify($password, $storedPassword);
        }

        return hash_equals($storedPassword, $password);
    }
}

if (!function_exists('jobhub_attempt_login')) {
    function jobhub_attempt_login(mysqli $conn, string $email, string $password): array
    {
        if (!jobhub_table_exists($conn, 'accounts')) {
            return [
                'success' => false,
                'message' => 'Unified accounts table is missing. Run the auth migration first.',
            ];
        }

        $account = jobhub_fetch_account_by_email($conn, $email);
        if (!$account) {
            return [
                'success' => false,
                'message' => "You don't have an account. Please register first.",
            ];
        }

        if (!jobhub_account_password_matches($conn, $account, $password)) {
            return [
                'success' => false,
                'message' => 'Invalid password.',
            ];
        }

        $statusMessage = jobhub_account_status_message($account);
        if ($statusMessage !== null) {
            return [
                'success' => false,
                'message' => $statusMessage,
            ];
        }

        jobhub_login_account($account);

        return [
            'success' => true,
            'account' => $account,
            'redirect' => jobhub_role_home($account['role'] ?? null),
        ];
    }
}

if (!function_exists('current_account')) {
    function current_account(bool $forceRefresh = false): ?array
    {
        global $conn;

        static $cachedAccountId = null;
        static $cachedAccount = null;

        $accountId = current_account_id();
        if ($accountId === null || !($conn instanceof mysqli)) {
            $cachedAccountId = null;
            $cachedAccount = null;
            return null;
        }

        if (!$forceRefresh && $cachedAccountId === $accountId && is_array($cachedAccount)) {
            return $cachedAccount;
        }

        $cachedAccountId = $accountId;
        $cachedAccount = jobhub_fetch_account_by_id($conn, $accountId);

        return $cachedAccount;
    }
}

if (!function_exists('jobhub_restore_legacy_session')) {
    function jobhub_restore_legacy_session(mysqli $conn): void
    {
        if (!empty($_SESSION['account_id']) || !jobhub_table_exists($conn, 'accounts')) {
            return;
        }

        $role = null;
        $profileId = 0;

        if (!empty($_SESSION['user_id'])) {
            $role = 'jobseeker';
            $profileId = (int) $_SESSION['user_id'];
        } elseif (!empty($_SESSION['company_id'])) {
            $role = 'company';
            $profileId = (int) $_SESSION['company_id'];
        } elseif (!empty($_SESSION['admin_id'])) {
            $role = 'admin';
            $profileId = (int) $_SESSION['admin_id'];
        }

        if ($role === null || $profileId <= 0) {
            return;
        }

        $account = jobhub_find_account_by_profile($conn, $role, $profileId);
        if ($account) {
            jobhub_sync_session_from_account($account);
        }
    }
}

if (!function_exists('jobhub_auth_bootstrap')) {
    function jobhub_auth_bootstrap(mysqli $conn): void
    {
        if (!jobhub_table_exists($conn, 'accounts')) {
            return;
        }

        jobhub_restore_legacy_session($conn);

        $accountId = current_account_id();
        if ($accountId === null) {
            return;
        }

        $account = jobhub_fetch_account_by_id($conn, $accountId);
        if (!$account) {
            jobhub_clear_auth_session();
            $_SESSION['auth_error'] = 'Please log in to continue.';
            return;
        }

        $statusMessage = jobhub_account_status_message($account);
        if ($statusMessage !== null) {
            jobhub_clear_auth_session();
            $_SESSION['auth_error'] = $statusMessage;
            return;
        }

        jobhub_sync_session_from_account($account);
    }
}

if (!function_exists('require_login')) {
    function require_login(): void
    {
        if (!is_logged_in()) {
            $_SESSION['auth_error'] = 'Please log in to continue.';
            jobhub_redirect('login.php');
        }
    }
}

if (!function_exists('require_role')) {
    function require_role(string $role): void
    {
        $requiredRole = jobhub_role_alias($role);
        require_login();

        if ($requiredRole === null) {
            jobhub_set_auth_flash('danger', 'Invalid authorization rule.');
            jobhub_redirect(jobhub_role_home());
        }

        if (current_role() !== $requiredRole) {
            jobhub_set_auth_flash('warning', 'Unauthorized access.');
            jobhub_redirect(jobhub_role_home());
        }
    }
}

if (!function_exists('require_roles')) {
    function require_roles(array $roles): void
    {
        require_login();

        $normalizedRoles = array_values(array_filter(array_map('jobhub_role_alias', $roles)));
        if (empty($normalizedRoles) || !in_array(current_role(), $normalizedRoles, true)) {
            jobhub_set_auth_flash('warning', 'Unauthorized access.');
            jobhub_redirect(jobhub_role_home());
        }
    }
}

if (!function_exists('require_admin')) {
    function require_admin(): void
    {
        require_role('admin');
    }
}

if (!function_exists('jobhub_email_exists')) {
    function jobhub_email_exists(mysqli $conn, string $email, ?int $exceptAccountId = null): bool
    {
        if (!jobhub_table_exists($conn, 'accounts')) {
            return false;
        }

        $email = strtolower(trim($email));
        if ($email === '') {
            return false;
        }

        $sql = "SELECT id FROM accounts WHERE email = ?";
        if (($exceptAccountId ?? 0) > 0) {
            $sql .= " AND id <> ?";
        }
        $sql .= " LIMIT 1";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return false;
        }

        if (($exceptAccountId ?? 0) > 0) {
            $exceptId = (int) $exceptAccountId;
            $stmt->bind_param('si', $email, $exceptId);
        } else {
            $stmt->bind_param('s', $email);
        }

        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        return $exists;
    }
}

if (!function_exists('jobhub_create_account')) {
    function jobhub_create_account(
        mysqli $conn,
        string $fullName,
        string $email,
        string $passwordHash,
        string $role,
        string $status = 'active'
    ): ?int {
        $role = jobhub_role_alias($role);
        $status = strtolower(trim($status));
        if ($role === null || !in_array($status, ['active', 'inactive', 'blocked', 'pending'], true)) {
            return null;
        }

        $stmt = $conn->prepare("
            INSERT INTO accounts (full_name, email, password, role, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");
        if (!$stmt) {
            return null;
        }

        $email = strtolower(trim($email));
        $stmt->bind_param('sssss', $fullName, $email, $passwordHash, $role, $status);
        $ok = $stmt->execute();
        $accountId = $ok ? (int) $conn->insert_id : null;
        $stmt->close();

        return $accountId;
    }
}

if (!function_exists('jobhub_update_account_identity')) {
    function jobhub_update_account_identity(mysqli $conn, int $accountId, string $fullName, string $email): bool
    {
        if ($accountId <= 0) {
            return false;
        }

        $email = strtolower(trim($email));
        $stmt = $conn->prepare("
            UPDATE accounts
            SET full_name = ?, email = ?, updated_at = NOW()
            WHERE id = ?
            LIMIT 1
        ");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('ssi', $fullName, $email, $accountId);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok && current_account_id() === $accountId) {
            $account = jobhub_fetch_account_by_id($conn, $accountId);
            if ($account) {
                jobhub_sync_session_from_account($account);
            }
        }

        return $ok;
    }
}

if (!function_exists('jobhub_update_account_password')) {
    function jobhub_update_account_password(mysqli $conn, int $accountId, string $passwordHash): bool
    {
        if ($accountId <= 0 || $passwordHash === '') {
            return false;
        }

        $stmt = $conn->prepare("
            UPDATE accounts
            SET password = ?, updated_at = NOW()
            WHERE id = ?
            LIMIT 1
        ");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('si', $passwordHash, $accountId);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }
}

if (!function_exists('jobhub_update_account_status')) {
    function jobhub_update_account_status(mysqli $conn, int $accountId, string $status): bool
    {
        if ($accountId <= 0) {
            return false;
        }

        $status = strtolower(trim($status));
        if (!in_array($status, ['active', 'inactive', 'blocked', 'pending'], true)) {
            return false;
        }

        $stmt = $conn->prepare("
            UPDATE accounts
            SET status = ?, updated_at = NOW()
            WHERE id = ?
            LIMIT 1
        ");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('si', $status, $accountId);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }
}

if (!function_exists('jobhub_safe_identifier')) {
    function jobhub_safe_identifier(string $value): ?string
    {
        $value = trim($value);
        return preg_match('/^[a-zA-Z0-9_]+$/', $value) ? $value : null;
    }
}

if (!function_exists('jobhub_delete_rows_by_column')) {
    function jobhub_delete_rows_by_column(mysqli $conn, string $table, string $column, int $id): bool
    {
        if ($id <= 0) {
            return true;
        }

        $safeTable = jobhub_safe_identifier($table);
        $safeColumn = jobhub_safe_identifier($column);
        if ($safeTable === null || $safeColumn === null) {
            return false;
        }

        if (!jobhub_table_exists($conn, $safeTable) || !jobhub_column_exists($conn, $safeTable, $safeColumn)) {
            return true;
        }

        $stmt = $conn->prepare("DELETE FROM `{$safeTable}` WHERE `{$safeColumn}` = ?");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }
}

if (!function_exists('jobhub_find_profile_id_by_account')) {
    function jobhub_find_profile_id_by_account(mysqli $conn, string $table, int $accountId): ?int
    {
        if ($accountId <= 0) {
            return null;
        }

        $safeTable = jobhub_safe_identifier($table);
        if ($safeTable === null) {
            return null;
        }

        if (!jobhub_table_exists($conn, $safeTable) || !jobhub_column_exists($conn, $safeTable, 'account_id')) {
            return null;
        }

        $stmt = $conn->prepare("SELECT id FROM `{$safeTable}` WHERE account_id = ? LIMIT 1");
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $accountId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        return $row ? (int) ($row['id'] ?? 0) : null;
    }
}

if (!function_exists('jobhub_delete_notifications_for_recipient')) {
    function jobhub_delete_notifications_for_recipient(mysqli $conn, string $recipientType, int $recipientId): bool
    {
        if ($recipientId <= 0 || !jobhub_table_exists($conn, 'notifications')) {
            return true;
        }

        if (!jobhub_column_exists($conn, 'notifications', 'recipient_type') || !jobhub_column_exists($conn, 'notifications', 'recipient_id')) {
            return true;
        }

        $recipientType = match (strtolower(trim($recipientType))) {
            'company' => 'company',
            'admin' => 'admin',
            default => 'user',
        };
        $stmt = $conn->prepare("DELETE FROM notifications WHERE recipient_type = ? AND recipient_id = ?");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('si', $recipientType, $recipientId);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }
}

if (!function_exists('jobhub_delete_company_job_dependencies')) {
    function jobhub_delete_company_job_dependencies(mysqli $conn, int $companyId): bool
    {
        if ($companyId <= 0 || !jobhub_table_exists($conn, 'jobs') || !jobhub_column_exists($conn, 'jobs', 'company_id')) {
            return true;
        }

        $jobLinkedTables = [
            'applications' => 'job_id',
            'bookmarks' => 'job_id',
            'saved_jobs' => 'job_id',
            'job_view_logs' => 'job_id',
            'job_skills' => 'job_id',
        ];

        foreach ($jobLinkedTables as $table => $column) {
            if (!jobhub_table_exists($conn, $table) || !jobhub_column_exists($conn, $table, $column)) {
                continue;
            }

            $stmt = $conn->prepare("
                DELETE linked
                FROM `{$table}` AS linked
                INNER JOIN jobs ON jobs.id = linked.`{$column}`
                WHERE jobs.company_id = ?
            ");
            if (!$stmt) {
                return false;
            }

            $stmt->bind_param('i', $companyId);
            $ok = $stmt->execute();
            $stmt->close();

            if (!$ok) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('jobhub_delete_jobseeker_account_data')) {
    function jobhub_delete_jobseeker_account_data(mysqli $conn, int $accountId): bool
    {
        $userId = jobhub_find_profile_id_by_account($conn, 'users', $accountId);
        if ($userId === null || $userId <= 0) {
            return true;
        }

        $userLinkedTables = [
            'applications' => 'user_id',
            'bookmarks' => 'user_id',
            'saved_jobs' => 'user_id',
            'job_search_logs' => 'user_id',
            'job_view_logs' => 'user_id',
            'user_skills' => 'user_id',
        ];

        foreach ($userLinkedTables as $table => $column) {
            if (!jobhub_delete_rows_by_column($conn, $table, $column, $userId)) {
                return false;
            }
        }

        if (!jobhub_delete_notifications_for_recipient($conn, 'user', $userId)) {
            return false;
        }

        return jobhub_delete_rows_by_column($conn, 'users', 'id', $userId);
    }
}

if (!function_exists('jobhub_delete_company_account_data')) {
    function jobhub_delete_company_account_data(mysqli $conn, int $accountId): bool
    {
        $companyId = jobhub_find_profile_id_by_account($conn, 'companies', $accountId);
        if ($companyId === null || $companyId <= 0) {
            return true;
        }

        if (!jobhub_delete_company_job_dependencies($conn, $companyId)) {
            return false;
        }

        if (!jobhub_delete_notifications_for_recipient($conn, 'company', $companyId)) {
            return false;
        }

        if (!jobhub_delete_rows_by_column($conn, 'jobs', 'company_id', $companyId)) {
            return false;
        }

        return jobhub_delete_rows_by_column($conn, 'companies', 'id', $companyId);
    }
}

if (!function_exists('jobhub_delete_admin_account_data')) {
    function jobhub_delete_admin_account_data(mysqli $conn, int $accountId): bool
    {
        $adminId = jobhub_find_profile_id_by_account($conn, 'admins', $accountId);
        if ($adminId !== null && $adminId > 0) {
            if (!jobhub_delete_notifications_for_recipient($conn, 'admin', $adminId)) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('jobhub_delete_account')) {
    function jobhub_delete_account(mysqli $conn, int $accountId): bool
    {
        if ($accountId <= 0) {
            return false;
        }

        $stmt = $conn->prepare("SELECT role FROM accounts WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('i', $accountId);
        $stmt->execute();
        $result = $stmt->get_result();
        $account = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if (!$account) {
            return false;
        }

        $role = jobhub_role_alias((string) ($account['role'] ?? ''));
        if ($role === 'jobseeker' && !jobhub_delete_jobseeker_account_data($conn, $accountId)) {
            return false;
        }

        if ($role === 'company' && !jobhub_delete_company_account_data($conn, $accountId)) {
            return false;
        }

        if ($role === 'admin' && !jobhub_delete_admin_account_data($conn, $accountId)) {
            return false;
        }

        $stmt = $conn->prepare("DELETE FROM accounts WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('i', $accountId);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }
}
