<?php
// company/company-header.php
require '../db.php';

if (!isset($_SESSION['company_id'])) {
    header("Location: company-login.php");
    exit;
}

$cid = (int)$_SESSION['company_id'];

// Fetch company status once
$stmt = $conn->prepare("SELECT name, is_approved FROM companies WHERE id = ?");
$stmt->bind_param("i", $cid);
$stmt->execute();
$company = $stmt->get_result()->fetch_assoc() ?? ['name' => 'Company', 'is_approved' => 0];
$stmt->close();

$isApproved = (int)$company['is_approved'] === 1;
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Dashboard - JobHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../custom.css?v=<?= time() ?>">
    <style>
        .company-sidebar { min-height: 100vh; background: #0d1b2a; color: white; }
        .company-sidebar .nav-link { color: rgba(255,255,255,0.8); }
        .company-sidebar .nav-link:hover, .company-sidebar .nav-link.active { color: white; background: rgba(255,255,255,0.1); }
        .main-content { background: #f8f9fa; }
        .pending-banner { background: #fff3cd; border-left: 4px solid #ffc107; }
    </style>
</head>
<body>

<div class="d-flex">
    <!-- Company Sidebar -->
    <div class="company-sidebar col-auto p-3">
        <h4 class="text-white mb-4">JobHub Company</h4>
        <div class="mb-3 p-2 bg-dark rounded text-center">
            <?= htmlspecialchars($company['name']) ?><br>
            <small class="badge <?= $isApproved ? 'bg-success' : 'bg-warning' ?>">
                <?= $isApproved ? 'Approved' : 'Pending Approval' ?>
            </small>
        </div>
        <hr class="text-white-50">
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'company-dashboard.php' ? 'active' : '' ?>" href="company-dashboard.php">Dashboard</a></li>
            <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'company-add-job.php' ? 'active' : '' ?>" href="company-add-job.php">Post New Job</a></li>
            <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'company-my-jobs.php' ? 'active' : '' ?>" href="company-my-jobs.php">My Jobs</a></li>
            <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'company-applications.php' ? 'active' : '' ?>" href="company-applications.php">Applications</a></li>
            <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'company-account.php' ? 'active' : '' ?>" href="company-account.php">Account Settings</a></li>
            <li class="nav-item mt-4"><a class="nav-link text-danger" href="../logout.php">Logout</a></li>
        </ul>
    </div>

    <!-- Main content -->
    <div class="main-content flex-grow-1">
        <main class="container-fluid py-4">