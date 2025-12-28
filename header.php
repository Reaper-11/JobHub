<?php
if (!isset($_SESSION)) session_start();
$isLoggedIn = isset($_SESSION['user_id']);
$basePath = isset($basePath) ? $basePath : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>JobHub - Job Portal</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($basePath); ?>style.css">
</head>
<body class="<?php echo isset($bodyClass) ? htmlspecialchars($bodyClass) : ''; ?>">
<header class="topbar">
    <div class="container flex-between">
        <div class="logo">JobHub</div>
        <nav>
            <?php if (!isset($_SESSION['company_id'])): ?>
                <a href="<?php echo htmlspecialchars($basePath); ?>index.php">Home</a>
            <?php endif; ?>
            <?php if ($isLoggedIn && !isset($_SESSION['company_id'])): ?>
                <a href="<?php echo htmlspecialchars($basePath); ?>user-account.php">Account</a>
                <a href="<?php echo htmlspecialchars($basePath); ?>my-bookmarks.php">My Bookmarks</a>
                <a href="<?php echo htmlspecialchars($basePath); ?>my-applications.php">My Applications</a>
                <a href="<?php echo htmlspecialchars($basePath); ?>logout.php">Logout</a>
            <?php elseif (!$isLoggedIn && !isset($_SESSION['company_id'])): ?>
                <a href="<?php echo htmlspecialchars($basePath); ?>register-choice.php">Register</a>
                <a href="<?php echo htmlspecialchars($basePath); ?>login-choice.php">Login</a>
            <?php endif; ?>
            <?php if (isset($_SESSION['company_id'])): ?>
                <a href="<?php echo htmlspecialchars($basePath); ?>company/company-dashboard.php">Company Dashboard</a>
                <a href="<?php echo htmlspecialchars($basePath); ?>company/company-account.php">Company Account</a>
                <a href="<?php echo htmlspecialchars($basePath); ?>logout.php">Logout</a>
            <?php elseif (!$isLoggedIn): ?>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="container">
