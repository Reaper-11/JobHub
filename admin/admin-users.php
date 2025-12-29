<?php
require '../db.php';
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit;
}
$users = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users - JobHub</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
<main class="container">
    <h1>Manage Users</h1>
    <p><a href="admin-dashboard.php">&laquo; Back to Dashboard</a></p>
    <table>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Registered At</th>
            <th>Action</th>
        </tr>
        <?php while ($u = $users->fetch_assoc()): ?>
            <tr>
                <td><?php echo $u['id']; ?></td>
                <td><?php echo htmlspecialchars($u['name']); ?></td>
                <td><?php echo htmlspecialchars($u['email']); ?></td>
                <td><?php echo htmlspecialchars($u['created_at']); ?></td>
                <td>
                    <form class="inline-form" method="post" action="admin-delete.php" onsubmit="return confirm('Delete this user?');">
                        <input type="hidden" name="table" value="users">
                        <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                        <input type="hidden" name="return" value="admin-users.php">
                        <input class="inline-input" type="text" name="reason" placeholder="Reason for deletion" required>
                        <button class="btn btn-danger btn-small" type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>
</main>
</body>
</html>
