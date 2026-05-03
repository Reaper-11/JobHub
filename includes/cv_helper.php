<?php

if (!defined('JOBHUB_CV_DIR')) {
    define('JOBHUB_CV_DIR', __DIR__ . '/../uploads/cv');
}

if (!defined('JOBHUB_CV_RELATIVE_DIR')) {
    define('JOBHUB_CV_RELATIVE_DIR', 'uploads/cv/');
}

if (!defined('JOBHUB_CV_MAX_SIZE')) {
    define('JOBHUB_CV_MAX_SIZE', 5 * 1024 * 1024);
}

if (!function_exists('jobhub_cv_allowed_types')) {
    function jobhub_cv_allowed_types(): array
    {
        return [
            'pdf' => ['application/pdf', 'application/x-pdf'],
            'doc' => ['application/msword', 'application/vnd.ms-word', 'application/octet-stream'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        ];
    }
}

if (!function_exists('jobhub_cv_is_stored_path')) {
    function jobhub_cv_is_stored_path(?string $path): bool
    {
        if (!is_string($path) || $path === '') {
            return false;
        }

        if (strpos($path, JOBHUB_CV_RELATIVE_DIR) !== 0) {
            return false;
        }

        $fileName = basename($path);
        return $fileName !== '' && $fileName === substr($path, strlen(JOBHUB_CV_RELATIVE_DIR));
    }
}

if (!function_exists('jobhub_cv_absolute_path')) {
    function jobhub_cv_absolute_path(string $path): ?string
    {
        if (!jobhub_cv_is_stored_path($path)) {
            return null;
        }

        return JOBHUB_CV_DIR . '/' . basename($path);
    }
}

if (!function_exists('jobhub_cv_file_name')) {
    function jobhub_cv_file_name(?string $path): string
    {
        return $path ? basename($path) : '';
    }
}

if (!function_exists('jobhub_cv_clean_original_name')) {
    function jobhub_cv_clean_original_name(?string $name, ?string $path = null): string
    {
        $name = trim((string) $name);
        $name = basename($name);
        $name = preg_replace('/[^\w.\-\s()]+/u', '_', $name) ?? '';
        $name = preg_replace('/\s+/', ' ', $name) ?? '';
        $name = trim((string) $name, ". \t\n\r\0\x0B");

        if ($name !== '') {
            return $name;
        }

        $fallback = $path !== null && $path !== ''
            ? basename($path)
            : 'resume';

        return trim((string) $fallback) !== '' ? basename((string) $fallback) : 'resume';
    }
}

if (!function_exists('jobhub_cv_display_name')) {
    function jobhub_cv_display_name(?string $originalName, ?string $path = null): string
    {
        return jobhub_cv_clean_original_name($originalName, $path);
    }
}

if (!function_exists('jobhub_cv_schema_table_exists')) {
    function jobhub_cv_schema_table_exists(mysqli $conn, string $table): bool
    {
        $safeTable = $conn->real_escape_string($table);
        $result = $conn->query("SHOW TABLES LIKE '{$safeTable}'");
        $exists = $result instanceof mysqli_result && $result->num_rows > 0;

        if ($result instanceof mysqli_result) {
            $result->close();
        }

        return $exists;
    }
}

if (!function_exists('jobhub_cv_schema_column_exists')) {
    function jobhub_cv_schema_column_exists(mysqli $conn, string $table, string $column): bool
    {
        if (!jobhub_cv_schema_table_exists($conn, $table)) {
            return false;
        }

        $safeTable = $conn->real_escape_string($table);
        $safeColumn = $conn->real_escape_string($column);
        $result = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
        $exists = $result instanceof mysqli_result && $result->num_rows > 0;

        if ($result instanceof mysqli_result) {
            $result->close();
        }

        return $exists;
    }
}

if (!function_exists('jobhub_cv_ensure_library_schema')) {
    function jobhub_cv_ensure_library_schema(mysqli $conn): array
    {
        static $schemaReady = null;
        if ($schemaReady !== null) {
            return $schemaReady;
        }

        if (!jobhub_table_exists($conn, 'users') || !jobhub_table_exists($conn, 'applications')) {
            $schemaReady = [
                'success' => false,
                'message' => 'Core CV tables are not available.',
            ];
            return $schemaReady;
        }

        $queries = [];

        if (!jobhub_cv_schema_table_exists($conn, 'user_cvs')) {
            $queries[] = "
                CREATE TABLE `user_cvs` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `user_id` INT NOT NULL,
                    `cv_path` VARCHAR(255) NOT NULL,
                    `original_name` VARCHAR(255) NOT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY `uniq_user_cvs_user_path` (`user_id`, `cv_path`),
                    KEY `idx_user_cvs_user_created` (`user_id`, `created_at`),
                    CONSTRAINT `fk_user_cvs_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
        }

        if (!jobhub_cv_schema_table_exists($conn, 'application_cvs')) {
            $queries[] = "
                CREATE TABLE `application_cvs` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `application_id` INT NOT NULL,
                    `cv_path` VARCHAR(255) NOT NULL,
                    `original_name` VARCHAR(255) NOT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY `uniq_application_cvs_application_path` (`application_id`, `cv_path`),
                    KEY `idx_application_cvs_application` (`application_id`, `created_at`),
                    CONSTRAINT `fk_application_cvs_application` FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
        }

        foreach ($queries as $query) {
            if ($conn->query($query) !== true) {
                error_log('[JobHub CV] Failed to prepare CV schema: ' . $conn->error);
                $schemaReady = [
                    'success' => false,
                    'message' => 'CV storage could not be prepared. Please check the database schema.',
                ];
                return $schemaReady;
            }
        }

        if (jobhub_cv_schema_table_exists($conn, 'user_cvs') && !jobhub_cv_schema_column_exists($conn, 'user_cvs', 'original_name')) {
            if ($conn->query("ALTER TABLE `user_cvs` ADD COLUMN `original_name` VARCHAR(255) NOT NULL AFTER `cv_path`") !== true) {
                error_log('[JobHub CV] Failed to add user_cvs.original_name: ' . $conn->error);
                $schemaReady = [
                    'success' => false,
                    'message' => 'CV storage could not be prepared. Please check the database schema.',
                ];
                return $schemaReady;
            }
        }

        if (jobhub_cv_schema_table_exists($conn, 'application_cvs') && !jobhub_cv_schema_column_exists($conn, 'application_cvs', 'original_name')) {
            if ($conn->query("ALTER TABLE `application_cvs` ADD COLUMN `original_name` VARCHAR(255) NOT NULL AFTER `cv_path`") !== true) {
                error_log('[JobHub CV] Failed to add application_cvs.original_name: ' . $conn->error);
                $schemaReady = [
                    'success' => false,
                    'message' => 'CV storage could not be prepared. Please check the database schema.',
                ];
                return $schemaReady;
            }
        }

        $schemaReady = [
            'success' => true,
            'message' => '',
        ];

        return $schemaReady;
    }
}

if (!function_exists('jobhub_cv_sync_user_library')) {
    function jobhub_cv_sync_user_library(mysqli $conn, int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $schema = jobhub_cv_ensure_library_schema($conn);
        if (empty($schema['success'])) {
            return;
        }

        $defaultPath = db_query_value("SELECT cv_path FROM users WHERE id = ?", 'i', [$userId], null);
        $defaultPath = is_string($defaultPath) ? trim($defaultPath) : '';

        if ($defaultPath !== '' && jobhub_cv_is_stored_path($defaultPath)) {
            $exists = (int) db_query_value(
                "SELECT COUNT(*) FROM user_cvs WHERE user_id = ? AND cv_path = ?",
                'is',
                [$userId, $defaultPath],
                0
            );

            if ($exists === 0) {
                $originalName = jobhub_cv_file_name($defaultPath);
                $stmt = $conn->prepare("
                    INSERT INTO user_cvs (user_id, cv_path, original_name, created_at, updated_at)
                    VALUES (?, ?, ?, NOW(), NOW())
                ");
                if ($stmt) {
                    $stmt->bind_param('iss', $userId, $defaultPath, $originalName);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }

        if ($defaultPath === '') {
            $latestCvPath = db_query_value(
                "SELECT cv_path FROM user_cvs WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 1",
                'i',
                [$userId],
                null
            );

            if (is_string($latestCvPath) && $latestCvPath !== '') {
                $stmt = $conn->prepare("UPDATE users SET cv_path = ?, updated_at = NOW() WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param('si', $latestCvPath, $userId);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
    }
}

if (!function_exists('jobhub_user_cv_list')) {
    function jobhub_user_cv_list(mysqli $conn, int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $schema = jobhub_cv_ensure_library_schema($conn);
        if (empty($schema['success'])) {
            return [];
        }

        jobhub_cv_sync_user_library($conn, $userId);

        $defaultPath = db_query_value("SELECT cv_path FROM users WHERE id = ?", 'i', [$userId], null);
        $defaultPath = is_string($defaultPath) ? trim($defaultPath) : '';

        $rows = db_query_all("
            SELECT id, user_id, cv_path, original_name, created_at, updated_at
            FROM user_cvs
            WHERE user_id = ?
            ORDER BY
                CASE WHEN cv_path = ? THEN 0 ELSE 1 END,
                created_at DESC,
                id DESC
        ", 'is', [$userId, $defaultPath]);

        foreach ($rows as &$row) {
            $row['id'] = (int) ($row['id'] ?? 0);
            $row['user_id'] = (int) ($row['user_id'] ?? 0);
            $row['is_default'] = $defaultPath !== '' && (string) ($row['cv_path'] ?? '') === $defaultPath;
            $row['display_name'] = jobhub_cv_display_name(
                (string) ($row['original_name'] ?? ''),
                (string) ($row['cv_path'] ?? '')
            );
        }
        unset($row);

        if ($defaultPath === '' && !empty($rows)) {
            $rows[0]['is_default'] = true;
        }

        return $rows;
    }
}

if (!function_exists('jobhub_user_default_cv')) {
    function jobhub_user_default_cv(mysqli $conn, int $userId): ?array
    {
        $cvs = jobhub_user_cv_list($conn, $userId);
        foreach ($cvs as $cv) {
            if (!empty($cv['is_default'])) {
                return $cv;
            }
        }

        return $cvs[0] ?? null;
    }
}

if (!function_exists('jobhub_user_cv_find_many')) {
    function jobhub_user_cv_find_many(mysqli $conn, int $userId, array $cvIds): array
    {
        if ($userId <= 0) {
            return [];
        }

        $schema = jobhub_cv_ensure_library_schema($conn);
        if (empty($schema['success'])) {
            return [];
        }

        jobhub_cv_sync_user_library($conn, $userId);

        $filteredIds = [];
        foreach ($cvIds as $cvId) {
            $cvId = (int) $cvId;
            if ($cvId > 0 && !in_array($cvId, $filteredIds, true)) {
                $filteredIds[] = $cvId;
            }
        }

        if (empty($filteredIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($filteredIds), '?'));
        $types = 'i' . str_repeat('i', count($filteredIds));
        $params = array_merge([$userId], $filteredIds);

        $rows = db_query_all("
            SELECT id, user_id, cv_path, original_name, created_at, updated_at
            FROM user_cvs
            WHERE user_id = ? AND id IN ({$placeholders})
        ", $types, $params);

        $rowsById = [];
        foreach ($rows as $row) {
            $row['id'] = (int) ($row['id'] ?? 0);
            $row['user_id'] = (int) ($row['user_id'] ?? 0);
            $row['display_name'] = jobhub_cv_display_name(
                (string) ($row['original_name'] ?? ''),
                (string) ($row['cv_path'] ?? '')
            );
            $rowsById[$row['id']] = $row;
        }

        $orderedRows = [];
        foreach ($filteredIds as $filteredId) {
            if (isset($rowsById[$filteredId])) {
                $orderedRows[] = $rowsById[$filteredId];
            }
        }

        return $orderedRows;
    }
}

if (!function_exists('jobhub_collect_user_cv_paths')) {
    function jobhub_collect_user_cv_paths(mysqli $conn, int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $paths = [];

        $defaultPath = db_query_value("SELECT cv_path FROM users WHERE id = ?", 'i', [$userId], null);
        if (is_string($defaultPath) && jobhub_cv_is_stored_path($defaultPath)) {
            $paths[] = $defaultPath;
        }

        $schema = jobhub_cv_ensure_library_schema($conn);
        if (!empty($schema['success'])) {
            foreach (db_query_all("SELECT cv_path FROM user_cvs WHERE user_id = ?", 'i', [$userId]) as $row) {
                $path = trim((string) ($row['cv_path'] ?? ''));
                if (jobhub_cv_is_stored_path($path)) {
                    $paths[] = $path;
                }
            }

            foreach (db_query_all("
                SELECT ac.cv_path
                FROM application_cvs ac
                INNER JOIN applications a ON a.id = ac.application_id
                WHERE a.user_id = ?
            ", 'i', [$userId]) as $row) {
                $path = trim((string) ($row['cv_path'] ?? ''));
                if (jobhub_cv_is_stored_path($path)) {
                    $paths[] = $path;
                }
            }
        }

        foreach (db_query_all("SELECT cv_path FROM applications WHERE user_id = ?", 'i', [$userId]) as $row) {
            $path = trim((string) ($row['cv_path'] ?? ''));
            if (jobhub_cv_is_stored_path($path)) {
                $paths[] = $path;
            }
        }

        return array_values(array_unique($paths));
    }
}

if (!function_exists('jobhub_cv_path_is_referenced')) {
    function jobhub_cv_path_is_referenced(mysqli $conn, string $path): bool
    {
        if (!jobhub_cv_is_stored_path($path)) {
            return false;
        }

        if ((int) db_query_value("SELECT COUNT(*) FROM users WHERE cv_path = ?", 's', [$path], 0) > 0) {
            return true;
        }

        if ((int) db_query_value("SELECT COUNT(*) FROM applications WHERE cv_path = ?", 's', [$path], 0) > 0) {
            return true;
        }

        $schema = jobhub_cv_ensure_library_schema($conn);
        if (!empty($schema['success'])) {
            if ((int) db_query_value("SELECT COUNT(*) FROM user_cvs WHERE cv_path = ?", 's', [$path], 0) > 0) {
                return true;
            }

            if ((int) db_query_value("SELECT COUNT(*) FROM application_cvs WHERE cv_path = ?", 's', [$path], 0) > 0) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('jobhub_cv_delete_file_if_unused')) {
    function jobhub_cv_delete_file_if_unused(mysqli $conn, string $path): bool
    {
        if (!jobhub_cv_is_stored_path($path)) {
            return false;
        }

        if (jobhub_cv_path_is_referenced($conn, $path)) {
            return false;
        }

        $absolutePath = jobhub_cv_absolute_path($path);
        if ($absolutePath === null || !is_file($absolutePath)) {
            return false;
        }

        return @unlink($absolutePath);
    }
}

if (!function_exists('jobhub_cleanup_cv_paths')) {
    function jobhub_cleanup_cv_paths(mysqli $conn, array $paths): void
    {
        foreach (array_values(array_unique($paths)) as $path) {
            $path = trim((string) $path);
            if ($path !== '') {
                jobhub_cv_delete_file_if_unused($conn, $path);
            }
        }
    }
}

if (!function_exists('jobhub_user_cv_remove')) {
    function jobhub_user_cv_remove(mysqli $conn, int $userId, int $cvId, ?string &$error = null): bool
    {
        $error = '';
        if ($userId <= 0 || $cvId <= 0) {
            $error = 'Invalid CV selection.';
            return false;
        }

        $schema = jobhub_cv_ensure_library_schema($conn);
        if (empty($schema['success'])) {
            $error = (string) ($schema['message'] ?? 'CV storage could not be prepared.');
            return false;
        }

        jobhub_cv_sync_user_library($conn, $userId);
        $cv = db_query_all("
            SELECT id, cv_path
            FROM user_cvs
            WHERE id = ? AND user_id = ?
            LIMIT 1
        ", 'ii', [$cvId, $userId])[0] ?? null;

        if (!$cv) {
            $error = 'Selected CV was not found.';
            return false;
        }

        $path = trim((string) ($cv['cv_path'] ?? ''));
        $defaultPath = db_query_value("SELECT cv_path FROM users WHERE id = ?", 'i', [$userId], null);
        $defaultPath = is_string($defaultPath) ? trim($defaultPath) : '';

        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare("DELETE FROM user_cvs WHERE id = ? AND user_id = ? LIMIT 1");
            if (!$stmt) {
                throw new RuntimeException('Could not prepare CV deletion.');
            }

            $stmt->bind_param('ii', $cvId, $userId);
            $deleted = $stmt->execute();
            $stmt->close();

            if (!$deleted) {
                throw new RuntimeException('Could not delete the selected CV.');
            }

            if ($path !== '' && $path === $defaultPath) {
                $replacementPath = db_query_value(
                    "SELECT cv_path FROM user_cvs WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 1",
                    'i',
                    [$userId],
                    null
                );
                $replacementPath = is_string($replacementPath) ? trim($replacementPath) : '';

                $stmt = $conn->prepare("UPDATE users SET cv_path = ?, updated_at = NOW() WHERE id = ?");
                if (!$stmt) {
                    throw new RuntimeException('Could not update the default CV.');
                }

                $stmt->bind_param('si', $replacementPath, $userId);
                if (!$stmt->execute()) {
                    $stmt->close();
                    throw new RuntimeException('Could not update the default CV.');
                }
                $stmt->close();
            }

            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            $error = $e->getMessage();
            return false;
        }

        if ($path !== '') {
            jobhub_cv_delete_file_if_unused($conn, $path);
        }

        return true;
    }
}

if (!function_exists('jobhub_cv_normalize_uploads')) {
    function jobhub_cv_normalize_uploads(array $files): array
    {
        if (!isset($files['name']) || !is_array($files['name'])) {
            return [$files];
        }

        $normalized = [];
        $count = count($files['name']);
        for ($index = 0; $index < $count; $index++) {
            $normalized[] = [
                'name' => $files['name'][$index] ?? '',
                'type' => $files['type'][$index] ?? '',
                'tmp_name' => $files['tmp_name'][$index] ?? '',
                'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$index] ?? 0,
            ];
        }

        return $normalized;
    }
}

if (!function_exists('jobhub_cv_upload_details')) {
    function jobhub_cv_upload_details(array $file, int $userId, ?string &$error = null): ?array
    {
        $error = '';

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $uploadErrorMap = [
                UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the server limit.',
                UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the form limit.',
                UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Temporary upload folder is missing.',
                UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded file.',
                UPLOAD_ERR_EXTENSION => 'A server extension stopped the upload.',
                UPLOAD_ERR_NO_FILE => 'Please choose at least one CV file to upload.',
            ];
            $error = $uploadErrorMap[(int) ($file['error'] ?? UPLOAD_ERR_NO_FILE)] ?? 'Could not upload the selected CV.';
            return null;
        }

        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $error = 'Invalid upload request.';
            return null;
        }

        $fileSize = (int) ($file['size'] ?? 0);
        if ($fileSize <= 0 || $fileSize > JOBHUB_CV_MAX_SIZE) {
            $error = 'Each CV must be 5MB or smaller.';
            return null;
        }

        $originalName = jobhub_cv_clean_original_name((string) ($file['name'] ?? ''));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedTypes = jobhub_cv_allowed_types();
        if (!isset($allowedTypes[$extension])) {
            $error = 'Only PDF, DOC, and DOCX files are allowed.';
            return null;
        }

        $mimeType = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mimeType = (string) finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
            }
        }

        if ($mimeType !== '' && !in_array($mimeType, $allowedTypes[$extension], true)) {
            $error = 'The uploaded file type is not allowed.';
            return null;
        }

        if (!is_dir(JOBHUB_CV_DIR) && !mkdir(JOBHUB_CV_DIR, 0755, true)) {
            $error = 'Upload folder is not available.';
            return null;
        }

        try {
            $randomPart = bin2hex(random_bytes(6));
        } catch (Throwable $e) {
            $randomPart = (string) mt_rand(100000, 999999);
        }

        $fileName = 'cv_' . $userId . '_' . time() . '_' . $randomPart . '.' . $extension;
        $destination = JOBHUB_CV_DIR . '/' . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $error = 'Could not save CV file.';
            return null;
        }

        return [
            'path' => JOBHUB_CV_RELATIVE_DIR . $fileName,
            'original_name' => $originalName,
        ];
    }
}

if (!function_exists('jobhub_cv_upload')) {
    function jobhub_cv_upload(array $file, int $userId, ?string &$error = null): ?string
    {
        $upload = jobhub_cv_upload_details($file, $userId, $error);
        return $upload['path'] ?? null;
    }
}

if (!function_exists('jobhub_application_attach_cvs')) {
    function jobhub_application_attach_cvs(mysqli $conn, int $applicationId, array $selectedCvs): bool
    {
        if ($applicationId <= 0) {
            return false;
        }

        $schema = jobhub_cv_ensure_library_schema($conn);
        if (empty($schema['success'])) {
            return false;
        }

        if (empty($selectedCvs)) {
            return false;
        }

        $stmt = $conn->prepare("
            INSERT INTO application_cvs (application_id, cv_path, original_name, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        if (!$stmt) {
            return false;
        }

        foreach ($selectedCvs as $selectedCv) {
            $path = trim((string) ($selectedCv['cv_path'] ?? ''));
            $originalName = jobhub_cv_display_name(
                (string) ($selectedCv['original_name'] ?? ''),
                $path
            );
            if (!jobhub_cv_is_stored_path($path)) {
                $stmt->close();
                return false;
            }

            $stmt->bind_param('iss', $applicationId, $path, $originalName);
            if (!$stmt->execute()) {
                $stmt->close();
                return false;
            }
        }

        $stmt->close();
        return true;
    }
}

if (!function_exists('jobhub_application_cv_list')) {
    function jobhub_application_cv_list(mysqli $conn, int $applicationId, ?string $legacyPath = null, ?string $legacyName = null): array
    {
        if ($applicationId <= 0) {
            return [];
        }

        $attachments = [];
        $schema = jobhub_cv_ensure_library_schema($conn);
        if (!empty($schema['success'])) {
            $attachments = db_query_all("
                SELECT id, application_id, cv_path, original_name, created_at
                FROM application_cvs
                WHERE application_id = ?
                ORDER BY created_at ASC, id ASC
            ", 'i', [$applicationId]);
        }

        foreach ($attachments as &$attachment) {
            $attachment['id'] = (int) ($attachment['id'] ?? 0);
            $attachment['application_id'] = (int) ($attachment['application_id'] ?? 0);
            $attachment['display_name'] = jobhub_cv_display_name(
                (string) ($attachment['original_name'] ?? ''),
                (string) ($attachment['cv_path'] ?? '')
            );
        }
        unset($attachment);

        if (!empty($attachments)) {
            return $attachments;
        }

        $legacyPath = trim((string) $legacyPath);
        if (!jobhub_cv_is_stored_path($legacyPath)) {
            return [];
        }

        return [[
            'id' => 0,
            'application_id' => $applicationId,
            'cv_path' => $legacyPath,
            'original_name' => jobhub_cv_display_name($legacyName, $legacyPath),
            'created_at' => null,
            'display_name' => jobhub_cv_display_name($legacyName, $legacyPath),
        ]];
    }
}

if (!function_exists('jobhub_application_cv_find')) {
    function jobhub_application_cv_find(mysqli $conn, int $applicationId, int $attachmentId): ?array
    {
        if ($applicationId <= 0 || $attachmentId <= 0) {
            return null;
        }

        $schema = jobhub_cv_ensure_library_schema($conn);
        if (empty($schema['success'])) {
            return null;
        }

        $attachment = db_query_all("
            SELECT id, application_id, cv_path, original_name, created_at
            FROM application_cvs
            WHERE application_id = ? AND id = ?
            LIMIT 1
        ", 'ii', [$applicationId, $attachmentId])[0] ?? null;

        if (!$attachment) {
            return null;
        }

        $attachment['id'] = (int) ($attachment['id'] ?? 0);
        $attachment['application_id'] = (int) ($attachment['application_id'] ?? 0);
        $attachment['display_name'] = jobhub_cv_display_name(
            (string) ($attachment['original_name'] ?? ''),
            (string) ($attachment['cv_path'] ?? '')
        );

        return $attachment;
    }
}

if (!function_exists('jobhub_cv_output_download')) {
    function jobhub_cv_output_download(string $path, ?string $downloadName = null): void
    {
        $absolutePath = jobhub_cv_absolute_path($path);
        if ($absolutePath === null || !is_file($absolutePath)) {
            http_response_code(404);
            echo 'CV file not found.';
            exit;
        }

        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        $contentTypes = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
        $contentType = $contentTypes[$extension] ?? 'application/octet-stream';
        $disposition = $extension === 'pdf' ? 'inline' : 'attachment';
        $downloadName = jobhub_cv_display_name($downloadName, $path);

        header('Content-Description: File Transfer');
        header('Content-Type: ' . $contentType);
        header('Content-Length: ' . filesize($absolutePath));
        header('Content-Disposition: ' . $disposition . '; filename="' . rawurlencode($downloadName) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=0, must-revalidate');

        readfile($absolutePath);
        exit;
    }
}
