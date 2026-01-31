<?php
// header.php
require_once 'db.php';

$isJobSeeker = isset($_SESSION['user_id']);
$isCompany   = isset($_SESSION['company_id']);
$isAdmin     = isset($_SESSION['admin_id']);
$isLoggedIn  = $isJobSeeker || $isCompany || $isAdmin;

$basePath = ''; // change if in subdirectory
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JobHub - Nepal's Job Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>custom.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100">

<header class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= $basePath ?>index.php">JobHub</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <?php if ($isLoggedIn && $isJobSeeker): ?>
                    <li class="nav-item"><a class="nav-link text-white" href="<?= $basePath ?>my-bookmarks.php">Bookmarks</a></li>
                    <li class="nav-item"><a class="nav-link text-white" href="<?= $basePath ?>my-applications.php">Applications</a></li>
                    <li class="nav-item"><a class="nav-link text-white" href="<?= $basePath ?>user-account.php">Account</a></li>
                <?php elseif ($isCompany): ?>
                    <li class="nav-item"><a class="nav-link text-white" href="<?= $basePath ?>company/company-dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link text-white" href="<?= $basePath ?>company/my-jobs.php">My Jobs</a></li>
                <?php elseif ($isAdmin): ?>
                    <li class="nav-item"><a class="nav-link text-white" href="<?= $basePath ?>admin/dashboard.php">Admin Panel</a></li>
                <?php endif; ?>

                <?php if ($isLoggedIn): ?>
                    <li class="nav-item"><a class="nav-link text-white" href="<?= $basePath ?>logout.php">Logout</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link text-white" href="<?= $basePath ?>login-choice.php">Login</a></li>
                    <li class="nav-item"><a class="nav-link text-white" href="<?= $basePath ?>register-choice.php">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</header>

<main class="container py-4 flex-grow-1">