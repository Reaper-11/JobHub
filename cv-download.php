<?php
require 'db.php';

$scope = $_GET['scope'] ?? '';

if ($scope === 'profile') {
    require_role('jobseeker');
    $userId = current_user_id() ?? 0;
    $cvId = isset($_GET['cv_id']) ? (int) $_GET['cv_id'] : 0;

    if ($cvId > 0) {
        $selectedCv = jobhub_user_cv_find_many($conn, $userId, [$cvId])[0] ?? null;
        if (!$selectedCv || !jobhub_cv_is_stored_path((string) ($selectedCv['cv_path'] ?? ''))) {
            http_response_code(404);
            echo 'CV file not found.';
            exit;
        }

        jobhub_cv_output_download(
            (string) $selectedCv['cv_path'],
            (string) ($selectedCv['original_name'] ?? '')
        );
    }

    $defaultCv = jobhub_user_default_cv($conn, $userId);
    if ($defaultCv && jobhub_cv_is_stored_path((string) ($defaultCv['cv_path'] ?? ''))) {
        jobhub_cv_output_download(
            (string) $defaultCv['cv_path'],
            (string) ($defaultCv['original_name'] ?? '')
        );
    }

    $cvPath = db_query_value("SELECT cv_path FROM users WHERE id = ?", 'i', [$userId], null);
    if (!jobhub_cv_is_stored_path($cvPath)) {
        http_response_code(404);
        echo 'CV file not found.';
        exit;
    }

    jobhub_cv_output_download((string) $cvPath);
}

if ($scope === 'application') {
    $applicationId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $attachmentId = isset($_GET['attachment_id']) ? (int) $_GET['attachment_id'] : 0;
    if ($applicationId <= 0) {
        http_response_code(400);
        echo 'Invalid request';
        exit;
    }

    $application = db_query_all("
        SELECT a.id, a.user_id, a.cv_path, u.cv_path AS user_cv_path, j.company_id
        FROM applications a
        JOIN jobs j ON j.id = a.job_id
        JOIN users u ON u.id = a.user_id
        WHERE a.id = ?
        LIMIT 1
    ", 'i', [$applicationId])[0] ?? null;

    if (!$application) {
        http_response_code(404);
        echo 'Application not found.';
        exit;
    }

    $isAllowed = false;
    if (current_admin_id() !== null) {
        $isAllowed = true;
    } elseif ((current_company_id() ?? 0) === (int) ($application['company_id'] ?? 0)) {
        $isAllowed = true;
    } elseif ((current_user_id() ?? 0) === (int) ($application['user_id'] ?? 0)) {
        $isAllowed = true;
    }

    if (!$isAllowed) {
        http_response_code(403);
        echo 'Access denied';
        exit;
    }

    if ($attachmentId > 0) {
        $attachment = jobhub_application_cv_find($conn, $applicationId, $attachmentId);
        if (!$attachment || !jobhub_cv_is_stored_path((string) ($attachment['cv_path'] ?? ''))) {
            http_response_code(404);
            echo 'CV file not found.';
            exit;
        }

        jobhub_cv_output_download(
            (string) $attachment['cv_path'],
            (string) ($attachment['original_name'] ?? '')
        );
    }

    $applicationCvs = jobhub_application_cv_list(
        $conn,
        $applicationId,
        (string) ($application['cv_path'] ?: ($application['user_cv_path'] ?? '')),
        null
    );

    if (empty($applicationCvs) || !jobhub_cv_is_stored_path((string) ($applicationCvs[0]['cv_path'] ?? ''))) {
        http_response_code(404);
        echo 'CV file not found.';
        exit;
    }

    jobhub_cv_output_download(
        (string) $applicationCvs[0]['cv_path'],
        (string) ($applicationCvs[0]['original_name'] ?? '')
    );
}

http_response_code(400);
echo 'Invalid request';
