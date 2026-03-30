<?php
require __DIR__ . '/db.php';

if (PHP_SAPI !== 'cli' && (string) ($_GET['run'] ?? '') !== '1') {
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Unified auth migration is ready.\n";
    echo "Run from CLI: php migrate_unified_auth.php\n";
    echo "Or from the browser: /JobHub/migrate_unified_auth.php?run=1\n";
    exit;
}

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=UTF-8');
}

set_time_limit(0);

function migrate_output(string $message): void
{
    echo $message . PHP_EOL;
}

function migrate_query(mysqli $conn, string $sql): void
{
    if (!$conn->query($sql)) {
        throw new RuntimeException($conn->error ?: 'SQL execution failed.');
    }
}

function migrate_ensure_table(mysqli $conn): void
{
    $sql = "
        CREATE TABLE IF NOT EXISTS accounts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(150) NOT NULL,
            email VARCHAR(150) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('jobseeker', 'company', 'admin') NOT NULL,
            status ENUM('active', 'inactive', 'blocked', 'pending') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ";

    migrate_query($conn, $sql);
}

function migrate_ensure_column(mysqli $conn, string $table, string $column, string $definition): void
{
    if (!jobhub_column_exists($conn, $table, $column)) {
        migrate_query($conn, "ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
    }
}

function migrate_ensure_index(mysqli $conn, string $table, string $indexName, string $definition): void
{
    $safeTable = $conn->real_escape_string($table);
    $safeIndex = $conn->real_escape_string($indexName);
    $result = $conn->query("SHOW INDEX FROM `{$safeTable}` WHERE Key_name = '{$safeIndex}'");
    $exists = $result instanceof mysqli_result && $result->num_rows > 0;

    if ($result instanceof mysqli_result) {
        $result->close();
    }

    if (!$exists) {
        migrate_query($conn, "ALTER TABLE {$table} ADD {$definition}");
    }
}

function migrate_ensure_foreign_key(mysqli $conn, string $table, string $constraintName, string $definition): void
{
    $sql = "
        SELECT 1
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND CONSTRAINT_NAME = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException($conn->error ?: 'Could not prepare foreign key lookup.');
    }

    $stmt->bind_param('ss', $table, $constraintName);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if (!$exists) {
        migrate_query($conn, "ALTER TABLE {$table} ADD CONSTRAINT {$constraintName} {$definition}");
    }
}

function migrate_fetch_rows(mysqli $conn, string $table, array $preferredColumns): array
{
    if (!jobhub_table_exists($conn, $table)) {
        return [];
    }

    $select = [];
    foreach ($preferredColumns as $alias => $choices) {
        $chosen = null;
        foreach ($choices as $candidate) {
            if (jobhub_column_exists($conn, $table, $candidate)) {
                $chosen = $candidate;
                break;
            }
        }

        if ($chosen !== null) {
            $select[] = "{$chosen} AS {$alias}";
        } else {
            $select[] = "NULL AS {$alias}";
        }
    }

    $sql = 'SELECT ' . implode(', ', $select) . " FROM {$table} ORDER BY id ASC";
    $result = $conn->query($sql);
    if (!$result) {
        throw new RuntimeException($conn->error ?: "Could not read {$table}.");
    }

    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $result->close();

    return $rows;
}

function migrate_normalize_password(?string $password): ?string
{
    $password = (string) $password;
    if ($password === '') {
        return null;
    }

    $info = password_get_info($password);
    if (!empty($info['algo'])) {
        return $password;
    }

    if (preg_match('/^[a-f0-9]{32}$/i', $password)) {
        return strtolower($password);
    }

    return password_hash($password, PASSWORD_DEFAULT);
}

function migrate_upsert_account(
    mysqli $conn,
    ?int $existingAccountId,
    string $fullName,
    string $email,
    string $password,
    string $role,
    string $status
): array {
    $email = strtolower(trim($email));
    $fullName = trim($fullName) !== '' ? trim($fullName) : $email;
    $role = jobhub_role_alias($role) ?? '';
    $status = in_array($status, ['active', 'inactive', 'blocked', 'pending'], true) ? $status : 'active';

    if ($role === '' || $email === '') {
        throw new RuntimeException('Missing role or email.');
    }

    $account = null;
    if (($existingAccountId ?? 0) > 0) {
        $account = jobhub_fetch_account_by_id($conn, (int) $existingAccountId);
    }

    if (!$account) {
        $account = jobhub_fetch_account_by_email($conn, $email);
    }

    if ($account && jobhub_role_alias($account['role'] ?? null) !== $role) {
        throw new RuntimeException("Email conflict with existing {$account['role']} account.");
    }

    if ($account) {
        $stmt = $conn->prepare("
            UPDATE accounts
            SET full_name = ?, email = ?, password = ?, role = ?, status = ?, updated_at = NOW()
            WHERE id = ?
            LIMIT 1
        ");
        if (!$stmt) {
            throw new RuntimeException($conn->error ?: 'Could not prepare account update.');
        }

        $accountId = (int) $account['id'];
        $stmt->bind_param('sssssi', $fullName, $email, $password, $role, $status, $accountId);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new RuntimeException($error !== '' ? $error : 'Could not update account.');
        }
        $stmt->close();

        return ['id' => $accountId, 'mode' => 'updated'];
    }

    $stmt = $conn->prepare("
        INSERT INTO accounts (full_name, email, password, role, status, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, NOW(), NOW())
    ");
    if (!$stmt) {
        throw new RuntimeException($conn->error ?: 'Could not prepare account insert.');
    }

    $stmt->bind_param('sssss', $fullName, $email, $password, $role, $status);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException($error !== '' ? $error : 'Could not create account.');
    }

    $accountId = (int) $conn->insert_id;
    $stmt->close();

    return ['id' => $accountId, 'mode' => 'inserted'];
}

function migrate_link_profile(mysqli $conn, string $table, int $profileId, int $accountId): void
{
    $stmt = $conn->prepare("UPDATE {$table} SET account_id = ? WHERE id = ? LIMIT 1");
    if (!$stmt) {
        throw new RuntimeException($conn->error ?: "Could not prepare {$table} account link.");
    }

    $stmt->bind_param('ii', $accountId, $profileId);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException($error !== '' ? $error : "Could not link {$table} profile.");
    }
    $stmt->close();
}

try {
    migrate_output('Starting unified auth migration...');
    migrate_ensure_table($conn);

    migrate_ensure_column($conn, 'users', 'account_id', 'INT NULL AFTER id');
    migrate_ensure_column($conn, 'companies', 'account_id', 'INT NULL AFTER id');
    migrate_ensure_column($conn, 'admins', 'account_id', 'INT NULL AFTER id');
    migrate_ensure_column($conn, 'admins', 'email', 'VARCHAR(150) NULL AFTER username');
    migrate_ensure_column($conn, 'admins', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER password');
    migrate_ensure_column($conn, 'admins', 'updated_at', 'DATETIME NULL AFTER created_at');

    if (jobhub_table_exists($conn, 'notifications')) {
        migrate_query($conn, "ALTER TABLE notifications MODIFY recipient_type ENUM('user', 'company', 'admin') NOT NULL");
    }

    $summary = [
        'accounts_inserted' => 0,
        'accounts_updated' => 0,
        'users_linked' => 0,
        'companies_linked' => 0,
        'admins_linked' => 0,
        'conflicts' => 0,
        'warnings' => [],
    ];

    $conn->begin_transaction();

    $userRows = migrate_fetch_rows($conn, 'users', [
        'id' => ['id'],
        'account_id' => ['account_id'],
        'name' => ['name', 'full_name', 'username'],
        'email' => ['email', 'user_email'],
        'password' => ['password', 'user_password', 'pass'],
        'account_status' => ['account_status'],
        'is_active' => ['is_active'],
    ]);

    foreach ($userRows as $row) {
        $email = strtolower(trim((string) ($row['email'] ?? '')));
        $password = migrate_normalize_password($row['password'] ?? null);
        if ($email === '' || $password === null) {
            $summary['warnings'][] = "Skipped user #{$row['id']}: missing email or password.";
            continue;
        }

        $status = 'active';
        $legacyStatus = strtolower(trim((string) ($row['account_status'] ?? 'active')));
        if ($legacyStatus === 'blocked' || (int) ($row['is_active'] ?? 1) !== 1) {
            $status = 'blocked';
        } elseif ($legacyStatus === 'removed') {
            $status = 'inactive';
        }

        try {
            $result = migrate_upsert_account(
                $conn,
                !empty($row['account_id']) ? (int) $row['account_id'] : null,
                (string) ($row['name'] ?? $email),
                $email,
                $password,
                'jobseeker',
                $status
            );
            $summary[$result['mode'] === 'inserted' ? 'accounts_inserted' : 'accounts_updated']++;
            migrate_link_profile($conn, 'users', (int) $row['id'], (int) $result['id']);
            $summary['users_linked']++;
        } catch (Throwable $e) {
            $summary['conflicts']++;
            $summary['warnings'][] = "User #{$row['id']} skipped: " . $e->getMessage();
        }
    }

    $companyRows = migrate_fetch_rows($conn, 'companies', [
        'id' => ['id'],
        'account_id' => ['account_id'],
        'name' => ['name', 'company_name'],
        'email' => ['email', 'company_email'],
        'password' => ['password', 'company_password', 'pass'],
        'is_approved' => ['is_approved'],
        'is_active' => ['is_active'],
    ]);

    foreach ($companyRows as $row) {
        $email = strtolower(trim((string) ($row['email'] ?? '')));
        $password = migrate_normalize_password($row['password'] ?? null);
        if ($email === '' || $password === null) {
            $summary['warnings'][] = "Skipped company #{$row['id']}: missing email or password.";
            continue;
        }

        $status = ((int) ($row['is_approved'] ?? 0) === -1 || (int) ($row['is_active'] ?? 1) !== 1)
            ? 'inactive'
            : 'active';

        try {
            $result = migrate_upsert_account(
                $conn,
                !empty($row['account_id']) ? (int) $row['account_id'] : null,
                (string) ($row['name'] ?? $email),
                $email,
                $password,
                'company',
                $status
            );
            $summary[$result['mode'] === 'inserted' ? 'accounts_inserted' : 'accounts_updated']++;
            migrate_link_profile($conn, 'companies', (int) $row['id'], (int) $result['id']);
            $summary['companies_linked']++;
        } catch (Throwable $e) {
            $summary['conflicts']++;
            $summary['warnings'][] = "Company #{$row['id']} skipped: " . $e->getMessage();
        }
    }

    $adminRows = migrate_fetch_rows($conn, 'admins', [
        'id' => ['id'],
        'account_id' => ['account_id'],
        'username' => ['username', 'name', 'full_name'],
        'email' => ['email', 'admin_email'],
        'password' => ['password', 'admin_password', 'pass'],
    ]);

    foreach ($adminRows as $row) {
        $username = trim((string) ($row['username'] ?? 'admin'));
        $email = strtolower(trim((string) ($row['email'] ?? '')));
        if ($email === '') {
            $email = 'admin+' . (int) $row['id'] . '@jobhub.local';

            $stmt = $conn->prepare("UPDATE admins SET email = ? WHERE id = ? LIMIT 1");
            if ($stmt) {
                $adminId = (int) $row['id'];
                $stmt->bind_param('si', $email, $adminId);
                $stmt->execute();
                $stmt->close();
            }
        }

        $password = migrate_normalize_password($row['password'] ?? null);
        if ($password === null) {
            $summary['warnings'][] = "Skipped admin #{$row['id']}: missing password.";
            continue;
        }

        try {
            $result = migrate_upsert_account(
                $conn,
                !empty($row['account_id']) ? (int) $row['account_id'] : null,
                $username !== '' ? $username : $email,
                $email,
                $password,
                'admin',
                'active'
            );
            $summary[$result['mode'] === 'inserted' ? 'accounts_inserted' : 'accounts_updated']++;
            migrate_link_profile($conn, 'admins', (int) $row['id'], (int) $result['id']);

            $stmt = $conn->prepare("UPDATE admins SET email = ?, password = ?, updated_at = NOW() WHERE id = ? LIMIT 1");
            if ($stmt) {
                $adminId = (int) $row['id'];
                $stmt->bind_param('ssi', $email, $password, $adminId);
                $stmt->execute();
                $stmt->close();
            }

            $summary['admins_linked']++;
        } catch (Throwable $e) {
            $summary['conflicts']++;
            $summary['warnings'][] = "Admin #{$row['id']} skipped: " . $e->getMessage();
        }
    }

    $conn->commit();

    migrate_ensure_index($conn, 'users', 'uq_users_account_id', 'UNIQUE KEY uq_users_account_id (account_id)');
    migrate_ensure_index($conn, 'companies', 'uq_companies_account_id', 'UNIQUE KEY uq_companies_account_id (account_id)');
    migrate_ensure_index($conn, 'admins', 'uq_admins_account_id', 'UNIQUE KEY uq_admins_account_id (account_id)');

    migrate_ensure_foreign_key($conn, 'users', 'fk_users_account', 'FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE');
    migrate_ensure_foreign_key($conn, 'companies', 'fk_companies_account', 'FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE');
    migrate_ensure_foreign_key($conn, 'admins', 'fk_admins_account', 'FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE');

    migrate_output('Migration completed.');
    migrate_output('Accounts inserted: ' . $summary['accounts_inserted']);
    migrate_output('Accounts updated: ' . $summary['accounts_updated']);
    migrate_output('Users linked: ' . $summary['users_linked']);
    migrate_output('Companies linked: ' . $summary['companies_linked']);
    migrate_output('Admins linked: ' . $summary['admins_linked']);
    migrate_output('Conflicts skipped: ' . $summary['conflicts']);

    if (!empty($summary['warnings'])) {
        migrate_output('');
        migrate_output('Warnings:');
        foreach ($summary['warnings'] as $warning) {
            migrate_output('- ' . $warning);
        }
    }
} catch (Throwable $e) {
    if ($conn->errno === 0) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
        }
    }

    migrate_output('Migration failed: ' . $e->getMessage());
    exit(1);
}
