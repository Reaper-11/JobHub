<?php
if (!isset($_SESSION)) session_start();
$isLoggedIn = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>JobHub - Job Portal</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="topbar">
    <div class="container flex-between">
        <div class="logo">JobHub</div>
        <nav>
            <a href="index.php">Home</a>
            <?php if ($isLoggedIn): ?>
                <a href="user-account.php">Account</a>
                <a href="my-bookmarks.php">My Bookmarks</a>
                <a href="my-applications.php">My Applications</a>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="register-choice.php">Register</a>
                <a href="login.php">Login</a>
            <?php endif; ?>
            <a href="admin/admin-login.php" class="admin-link">Admin</a>
        </nav>
    </div>
</header>
<main class="container">
