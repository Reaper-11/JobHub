<?php

if (!function_exists('jobhub_application_allowed_statuses')) {
    function jobhub_application_allowed_statuses(): array
    {
        return ['pending', 'shortlisted', 'approved', 'interview', 'rejected'];
    }
}

if (!function_exists('jobhub_application_default_response_message')) {
    function jobhub_application_default_response_message(string $status): string
    {
        return match (strtolower(trim($status))) {
            'pending' => 'Your application is under review.',
            'shortlisted' => 'You have been shortlisted for the next step.',
            'approved' => 'Your application has been approved.',
            'interview' => 'You are invited for an interview.',
            'rejected' => 'We regret to inform you that your application was not selected.',
            default => 'Your application status has been updated.',
        };
    }
}

if (!function_exists('jobhub_application_table_columns')) {
    function jobhub_application_table_columns(bool $refresh = false): array
    {
        global $conn;

        static $columns = null;
        if (!$refresh && is_array($columns)) {
            return $columns;
        }

        $columns = [];
        if (!($conn instanceof mysqli)) {
            return $columns;
        }

        $result = $conn->query("SHOW COLUMNS FROM applications");
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $field = strtolower(trim((string)($row['Field'] ?? '')));
                if ($field !== '') {
                    $columns[] = $field;
                }
            }
            $result->close();
        }

        return $columns;
    }
}

if (!function_exists('jobhub_application_has_column')) {
    function jobhub_application_has_column(string $column, bool $refresh = false): bool
    {
        return in_array(strtolower(trim($column)), jobhub_application_table_columns($refresh), true);
    }
}

if (!function_exists('jobhub_application_ensure_status_columns')) {
    function jobhub_application_ensure_status_columns(): array
    {
        global $conn;

        if (!($conn instanceof mysqli)) {
            return [
                'success' => false,
                'message' => 'Database connection is unavailable.',
            ];
        }

        if (!jobhub_application_has_column('response_message')) {
            if ($conn->query("ALTER TABLE `applications` ADD COLUMN `response_message` TEXT NULL AFTER `status`") !== true) {
                error_log('[JobHub Application Status] Failed to add applications.response_message: ' . $conn->error);

                return [
                    'success' => false,
                    'message' => 'Application status fields could not be prepared. Please check the database schema.',
                ];
            }

            jobhub_application_table_columns(true);
        }

        if (!jobhub_application_has_column('status_updated_at')) {
            if ($conn->query("ALTER TABLE `applications` ADD COLUMN `status_updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `response_message`") !== true) {
                error_log('[JobHub Application Status] Failed to add applications.status_updated_at: ' . $conn->error);

                return [
                    'success' => false,
                    'message' => 'Application status fields could not be prepared. Please check the database schema.',
                ];
            }

            jobhub_application_table_columns(true);
        }

        return [
            'success' => true,
            'message' => '',
        ];
    }
}

if (!function_exists('jobhub_company_update_application_status')) {
    function jobhub_company_update_application_status(
        int $companyId,
        int $applicationId,
        string $status,
        ?string $submittedResponseMessage = ''
    ): array {
        global $conn;

        $companyId = (int)$companyId;
        $applicationId = (int)$applicationId;
        if ($companyId <= 0 || $applicationId <= 0) {
            return [
                'ok' => false,
                'type' => 'danger',
                'message' => 'Invalid application selection.',
            ];
        }

        $schemaResult = jobhub_application_ensure_status_columns();
        if (empty($schemaResult['success'])) {
            return [
                'ok' => false,
                'type' => 'danger',
                'message' => (string)($schemaResult['message'] ?? 'Application status fields could not be prepared.'),
            ];
        }

        $normalizedStatus = strtolower(trim($status));
        if (!in_array($normalizedStatus, jobhub_application_allowed_statuses(), true)) {
            return [
                'ok' => false,
                'type' => 'danger',
                'message' => 'Invalid status selection.',
            ];
        }

        $submittedResponseMessage = trim((string)$submittedResponseMessage);
        $submittedResponseMessage = preg_replace("/\r\n?/", "\n", $submittedResponseMessage);
        $responseMessage = $submittedResponseMessage !== ''
            ? $submittedResponseMessage
            : jobhub_application_default_response_message($normalizedStatus);

        $application = db_query_all("
            SELECT a.id, a.user_id, a.status, a.response_message,
                   COALESCE(NULLIF(u.name, ''), 'Applicant') AS user_name,
                   u.email AS user_email,
                   j.title AS job_title,
                   COALESCE(NULLIF(c.name, ''), NULLIF(j.company, ''), 'Company') AS company_name
            FROM applications a
            JOIN users u ON u.id = a.user_id
            JOIN jobs j ON j.id = a.job_id
            LEFT JOIN companies c ON c.id = j.company_id
            WHERE a.id = ? AND j.company_id = ?
            LIMIT 1
        ", "ii", [$applicationId, $companyId])[0] ?? null;

        if (!$application) {
            return [
                'ok' => false,
                'type' => 'danger',
                'message' => 'Application not found or you are not authorized to update it.',
            ];
        }

        $currentStatus = strtolower(trim((string)($application['status'] ?? 'pending')));
        $currentResponse = trim((string)($application['response_message'] ?? ''));
        if ($currentStatus === $normalizedStatus && $currentResponse === $responseMessage) {
            return [
                'ok' => true,
                'type' => 'info',
                'message' => 'Application already has that status and message.',
                'email_sent' => false,
                'notification_sent' => false,
                'updated' => false,
            ];
        }

        $setClauses = [
            'status = ?',
            'response_message = ?',
        ];
        if (jobhub_application_has_column('status_updated_at')) {
            $setClauses[] = 'status_updated_at = NOW()';
        }
        if (jobhub_application_has_column('updated_at')) {
            $setClauses[] = 'updated_at = NOW()';
        }

        $sql = "
            UPDATE applications
            SET " . implode(', ', $setClauses) . "
            WHERE id = ? AND job_id IN (
                SELECT id FROM jobs WHERE company_id = ?
            )
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [
                'ok' => false,
                'type' => 'danger',
                'message' => 'Update failed.',
            ];
        }

        $stmt->bind_param("ssii", $normalizedStatus, $responseMessage, $applicationId, $companyId);
        $updated = $stmt->execute();
        $stmt->close();

        if (!$updated) {
            return [
                'ok' => false,
                'type' => 'danger',
                'message' => 'Update failed.',
            ];
        }

        $jobTitle = trim((string)($application['job_title'] ?? 'Job Application'));
        $companyName = trim((string)($application['company_name'] ?? 'Company'));
        $statusLabel = notify_status_label($normalizedStatus);
        $notificationMessage = 'Your application for "' . $jobTitle . '" at ' . $companyName . ' was updated to ' . $statusLabel . '.';
        if ($responseMessage !== '') {
            $notificationMessage .= ' Message from company: ' . $responseMessage;
        }

        $notificationSent = notify_create(
            'user',
            (int)$application['user_id'],
            'Application Status Updated',
            $notificationMessage,
            'my-applications.php',
            notify_status_type($normalizedStatus),
            'application',
            $applicationId
        );

        $emailSent = false;
        $emailMessage = '';
        try {
            $emailResult = jobhub_send_application_status_update_email(
                (string)($application['user_email'] ?? ''),
                (string)($application['user_name'] ?? 'Applicant'),
                $jobTitle,
                $normalizedStatus,
                $responseMessage
            );
            $emailSent = !empty($emailResult['success']);
            $emailMessage = trim((string)($emailResult['message'] ?? ''));

            if (!$emailSent) {
                jobhub_log_mail_error(
                    'application-status',
                    'Application #' . $applicationId . ' status update email failed for '
                    . (string)($application['user_email'] ?? 'unknown-recipient')
                    . ': '
                    . ($emailMessage !== '' ? $emailMessage : 'Unknown mail error.')
                );
            }
        } catch (\Throwable $e) {
            $emailMessage = $e->getMessage();
            jobhub_log_mail_error(
                'application-status',
                'Application #' . $applicationId . ' status update email threw an exception: ' . $emailMessage
            );
        }

        return [
            'ok' => true,
            'type' => $emailSent ? 'success' : 'warning',
            'message' => $emailSent
                ? 'Application status updated and email sent successfully.'
                : 'Application status updated, but email could not be sent.',
            'email_sent' => $emailSent,
            'email_message' => $emailMessage,
            'notification_sent' => $notificationSent,
            'updated' => true,
            'status' => $normalizedStatus,
            'response_message' => $responseMessage,
            'job_title' => $jobTitle,
            'company_name' => $companyName,
        ];
    }
}
