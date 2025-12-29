<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "JobHub";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
session_start();

if (!function_exists('job_expiration_timestamp')) {
    function job_expiration_timestamp($createdAt, $duration)
    {
        $duration = trim((string) $duration);
        if ($duration === '') {
            return null;
        }
        $durationLower = strtolower($duration);
        if ($durationLower === 'ongoing') {
            return null;
        }
        $createdTs = strtotime($createdAt);
        if ($createdTs === false) {
            return null;
        }
        if (preg_match('/^(\d+)\s*(day|days|week|weeks|month|months|year|years)$/i', $duration, $matches)) {
            $value = (int) $matches[1];
            $unit = $matches[2];
            $expires = strtotime("+$value $unit", $createdTs);
            return $expires === false ? null : $expires;
        }
        $expires = strtotime("+$duration", $createdTs);
        return $expires === false ? null : $expires;
    }
}

if (!function_exists('is_job_expired')) {
    function is_job_expired(array $job)
    {
        if (!isset($job['created_at'])) {
            return false;
        }
        $expiresAt = job_expiration_timestamp($job['created_at'], $job['application_duration'] ?? '');
        return $expiresAt !== null && time() > $expiresAt;
    }
}

// Ensure users.preferred_category exists for job preference feature.
$prefColCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'preferred_category'");
if ($prefColCheck && $prefColCheck->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN preferred_category VARCHAR(100) NULL AFTER phone");
}

// Ensure users.cv_path exists for CV uploads.
$cvColCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'cv_path'");
if ($cvColCheck && $cvColCheck->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN cv_path VARCHAR(255) NULL AFTER preferred_category");
}

// Ensure users.profile_image exists for profile photos.
$profileImageColCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_image'");
if ($profileImageColCheck && $profileImageColCheck->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN profile_image VARCHAR(255) NULL AFTER cv_path");
}

// Ensure applications.status exists for admin updates.
$colCheck = $conn->query("SHOW COLUMNS FROM applications LIKE 'status'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE applications ADD COLUMN status VARCHAR(20) DEFAULT 'pending'");
}

// Ensure applications.rejection_reason exists for rejection feedback.
$reasonColCheck = $conn->query("SHOW COLUMNS FROM applications LIKE 'rejection_reason'");
if ($reasonColCheck && $reasonColCheck->num_rows === 0) {
    $conn->query("ALTER TABLE applications ADD COLUMN rejection_reason TEXT NULL");
}

// Ensure applications.company_id exists for company views.
$companyColCheck = $conn->query("SHOW COLUMNS FROM applications LIKE 'company_id'");
if ($companyColCheck && $companyColCheck->num_rows === 0) {
    $conn->query("ALTER TABLE applications ADD COLUMN company_id INT NULL");
}

// Ensure companies table exists for company panel.
$companiesCheck = $conn->query("SHOW TABLES LIKE 'companies'");
if ($companiesCheck && $companiesCheck->num_rows === 0) {
    $conn->query(
        "CREATE TABLE companies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            email VARCHAR(150) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            website VARCHAR(200),
            location VARCHAR(150),
            is_approved TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )"
    );
}

// Ensure companies.is_approved exists for approval workflow.
$companyApprovedCol = $conn->query("SHOW COLUMNS FROM companies LIKE 'is_approved'");
if ($companyApprovedCol && $companyApprovedCol->num_rows === 0) {
    $conn->query("ALTER TABLE companies ADD COLUMN is_approved TINYINT(1) DEFAULT 0");
}

// Ensure companies.rejection_reason exists for rejection feedback.
$companyRejectCol = $conn->query("SHOW COLUMNS FROM companies LIKE 'rejection_reason'");
if ($companyRejectCol && $companyRejectCol->num_rows === 0) {
    $conn->query("ALTER TABLE companies ADD COLUMN rejection_reason TEXT NULL");
}

// Ensure jobs.company_id exists for company job posts.
$companyCol = $conn->query("SHOW COLUMNS FROM jobs LIKE 'company_id'");
if ($companyCol && $companyCol->num_rows === 0) {
    $conn->query("ALTER TABLE jobs ADD COLUMN company_id INT NULL");
}

// Ensure jobs.application_duration exists for job application duration.
$jobDurationCol = $conn->query("SHOW COLUMNS FROM jobs LIKE 'application_duration'");
if ($jobDurationCol && $jobDurationCol->num_rows === 0) {
    $conn->query("ALTER TABLE jobs ADD COLUMN application_duration VARCHAR(100) NULL");
}

// Ensure jobs.category exists for job categories.
$jobCategoryCol = $conn->query("SHOW COLUMNS FROM jobs LIKE 'category'");
if ($jobCategoryCol && $jobCategoryCol->num_rows === 0) {
    $conn->query("ALTER TABLE jobs ADD COLUMN category VARCHAR(100) NULL");
}

// Ensure user_deletion_reasons table exists for admin deletes.
$userDeletionCheck = $conn->query("SHOW TABLES LIKE 'user_deletion_reasons'");
if ($userDeletionCheck && $userDeletionCheck->num_rows === 0) {
    $conn->query(
        "CREATE TABLE user_deletion_reasons (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            admin_id INT NOT NULL,
            reason TEXT NOT NULL,
            deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )"
    );
}

?>
