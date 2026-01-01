<?php
require '../db.php';
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit;
}
$jobs = $conn->query("SELECT * FROM jobs ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Job Details - JobHub</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
<main class="container">
    <h1>Job Details</h1>
    <p><a href="admin-dashboard.php">&laquo; Back to Dashboard</a></p>

    <h3>All Jobs</h3>
    <table>
        <tr>
            <th>ID</th>
            <th>Title</th>
            <th>Company</th>
            <th>Location</th>
            <th>Actions</th>
        </tr>
        <?php while ($j = $jobs->fetch_assoc()): ?>
            <tr>
                <td><?php echo $j['id']; ?></td>
                <td><?php echo htmlspecialchars($j['title']); ?></td>
                <td><?php echo htmlspecialchars($j['company']); ?></td>
                <td><?php echo htmlspecialchars($j['location']); ?></td>
                <td>
                    <a class="btn btn-danger btn-small"
                       href="admin-delete.php?table=jobs&id=<?php echo $j['id']; ?>&return=admin-jobs.php"
                       onclick="return confirm('Delete this job?')">Delete</a>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>
</main>
</body>
</html>
