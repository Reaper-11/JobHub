<?php
require 'db.php';

$scope = $_GET['scope'] ?? '';

if ($scope === 'profile') {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        echo 'Access denied';
        exit;
    }

    $userId = (int) $_SESSION['user_id'];
    $cvPath = db_query_value("SELECT cv_path FROM users WHERE id = ?", 'i', [$userId], null);
    if (!jobhub_cv_is_stored_path($cvPath)) {
        http_response_code(404);
        echo 'CV file not found.';
        exit;
    }

    jobhub_cv_output_download($cvPath);
}

if ($scope === 'application') {
    $applicationId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
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
    if (isset($_SESSION['admin_id'])) {
        $isAllowed = true;
    } elseif (isset($_SESSION['company_id']) && (int) $_SESSION['company_id'] === (int) ($application['company_id'] ?? 0)) {
        $isAllowed = true;
    } elseif (isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] === (int) ($application['user_id'] ?? 0)) {
        $isAllowed = true;
    }

    if (!$isAllowed) {
        http_response_code(403);
        echo 'Access denied';
        exit;
    }

    $cvPath = $application['cv_path'] ?: ($application['user_cv_path'] ?? '');
    if (!jobhub_cv_is_stored_path($cvPath)) {
        http_response_code(404);
        echo 'CV file not found.';
        exit;
    }

    jobhub_cv_output_download($cvPath);
}

http_response_code(400);
echo 'Invalid request';
