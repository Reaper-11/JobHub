<?php
require '../db.php';
require '../includes/flash.php';
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit;
}
$jobs = $conn->query("SELECT * FROM jobs ORDER BY created_at DESC");
$flash = get_flash('jobs');
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
    <?php if ($flash): ?>
        <div class="alert <?php echo htmlspecialchars($flash['type']); ?>">
            <?php echo htmlspecialchars($flash['message']); ?>
        </div>
    <?php endif; ?>

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
                    <form class="inline-form" method="post" action="admin-delete.php" onsubmit="return confirm('Delete this job?');">
                        <input type="hidden" name="table" value="jobs">
                        <input type="hidden" name="id" value="<?php echo $j['id']; ?>">
                        <input type="hidden" name="return" value="admin-jobs.php">
                        <button class="btn btn-danger btn-small" type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>
</main>
</body>
</html>
