<?php
require 'db.php';
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit;
}
$sql = "SELECT a.*, u.name, u.email, j.title
        FROM applications a
        JOIN users u ON u.id = a.user_id
        JOIN jobs j ON j.id = a.job_id
        ORDER BY a.applied_at DESC";
$res = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Applications - JobHub</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<main class="container">
    <h1>Job Applications</h1>
    <p><a href="admin-dashboard.php">&laquo; Back to Dashboard</a></p>
    <table>
        <tr>
            <th>ID</th>
            <th>Job</th>
            <th>User</th>
            <th>Email</th>
            <th>Applied At</th>
        </tr>
        <?php while ($a = $res->fetch_assoc()): ?>
            <tr>
                <td><?php echo $a['id']; ?></td>
                <td><?php echo htmlspecialchars($a['title']); ?></td>
                <td><?php echo htmlspecialchars($a['name']); ?></td>
                <td><?php echo htmlspecialchars($a['email']); ?></td>
                <td><?php echo htmlspecialchars($a['applied_at']); ?></td>
            </tr>
        <?php endwhile; ?>
    </table>
</main>
</body>
</html>
