<?php
require '../db.php';
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit;
}
$jobsCount = $conn->query("SELECT COUNT(*) c FROM jobs")->fetch_assoc()['c'];
$usersCount = $conn->query("SELECT COUNT(*) c FROM users")->fetch_assoc()['c'];
$appCount  = $conn->query("SELECT COUNT(*) c FROM applications")->fetch_assoc()['c'];
$companyCount = $conn->query("SELECT COUNT(*) c FROM companies")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - JobHub</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
<main class="container">
    <h1>Admin Dashboard</h1>
    <div class="card flex-between">
        <p>Welcome, <?php echo htmlspecialchars($_SESSION['admin_username']); ?></p>
        <a class="btn btn-secondary btn-small" href="../logout.php">Logout</a>
    </div>

    <div class="jobs-grid">
        <div class="card"><h3>Total Jobs</h3><p><?php echo $jobsCount; ?></p></div>
        <div class="card"><h3>Total Users</h3><p><?php echo $usersCount; ?></p></div>
        <div class="card"><h3>Total Applications</h3><p><?php echo $appCount; ?></p></div>
        <div class="card"><h3>Total Companies</h3><p><?php echo $companyCount; ?></p></div>
    </div>

    <div class="card">
        <h3>Admin Menu</h3>
        <ul class="admin-menu">
            <li><a href="admin-jobs.php">Job Details</a></li>
            <li><a href="admin-users.php">Manage Users</a></li>
            <li><a href="admin-companies.php">Manage Companies</a></li>
            <li><a href="admin-applications.php">View Applications</a></li>
        </ul>
    </div>
</main>
</body>
</html>

