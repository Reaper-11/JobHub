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

// Ensure jobs.company_id exists for company job posts.
$companyCol = $conn->query("SHOW COLUMNS FROM jobs LIKE 'company_id'");
if ($companyCol && $companyCol->num_rows === 0) {
    $conn->query("ALTER TABLE jobs ADD COLUMN company_id INT NULL");
}
?>
