<?php
require '../db.php';
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit;
}
if ((int)$_SESSION['admin_id'] !== 5) {
    header('Location: admin-dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Page - JobHub</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
<main class="container">
    <h1>Admin Page</h1>
    <p>Welcome, <?php echo htmlspecialchars($_SESSION['admin_username']); ?> | <a href="../logout.php">Logout</a></p>
    <div class="card">
        <p>This is your dedicated admin page.</p>
        <ul>
            <li><a href="admin-dashboard.php">Dashboard</a></li>
            <li><a href="admin-jobs.php">Manage Jobs</a></li>
            <li><a href="admin-users.php">Manage Users</a></li>
            <li><a href="admin-applications.php">View Applications</a></li>
        </ul>
    </div>
</main>
</body>
</html>