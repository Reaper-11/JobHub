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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Page - JobHub</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../custom.css?v=<?php echo filemtime(__DIR__ . '/../custom.css'); ?>">
</head>
<body>
<main class="container py-4">
    <h1 class="mb-2">Admin Page</h1>
    <p>Welcome, <?php echo htmlspecialchars($_SESSION['admin_username']); ?> | <a class="link-primary text-decoration-none" href="../logout.php">Logout</a></p>
    <div class="card shadow-sm">
        <div class="card-body">
            <p>This is your dedicated admin page.</p>
            <ul class="list-unstyled mb-0">
                <li><a class="link-primary text-decoration-none" href="admin-dashboard.php">Dashboard</a></li>
                <li><a class="link-primary text-decoration-none" href="admin-jobs.php">Manage Jobs</a></li>
                <li><a class="link-primary text-decoration-none" href="admin-users.php">Manage Users</a></li>
                <li><a class="link-primary text-decoration-none" href="admin-applications.php">View Applications</a></li>
            </ul>
        </div>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
