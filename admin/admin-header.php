<?php
// admin/admin-header.php
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - JobHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../custom.css?v=<?= time() ?>">
    <style>
        .sidebar { min-height: 100vh; background: #1a1f36; color: white; }
        .sidebar .nav-link { color: rgba(255,255,255,0.8); }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: white; background: rgba(255,255,255,0.1); }
        .main-content { background: #f5f7ff; }
    </style>
</head>
<body>

<div class="d-flex">
    <!-- Sidebar -->
    <div class="sidebar col-auto p-3">
        <h4 class="text-white mb-4">JobHub Admin</h4>
        <hr class="text-white-50">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'admin-dashboard.php' ? 'active' : '' ?>" 
                   href="admin-dashboard.php">Dashboard</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'admin-jobs.php' ? 'active' : '' ?>" 
                   href="admin-jobs.php">Jobs</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'admin-companies.php' ? 'active' : '' ?>" 
                   href="admin-companies.php">Companies</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'admin-users.php' ? 'active' : '' ?>" 
                   href="admin-users.php">Users</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'admin-applications.php' ? 'active' : '' ?>" 
                   href="admin-applications.php">Applications</a>
            </li>
            <li class="nav-item mt-4">
                <a class="nav-link text-danger" href="../logout.php">Logout</a>
            </li>
        </ul>
    </div>

    <!-- Main content -->
    <div class="main-content flex-grow-1">
        <main class="container-fluid py-4">