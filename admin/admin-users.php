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
                    <form class="inline-form delete-user-form" method="post" action="admin-delete.php">
                        <input type="hidden" name="table" value="users">
                        <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                        <input type="hidden" name="return" value="admin-users.php">
                        <input class="inline-input reason-input" type="text" name="reason" style="display:none;">
                        <button class="btn btn-danger btn-small delete-toggle" type="button">Remove User (Permanent)</button>
                        <button class="btn btn-danger btn-small confirm-delete" type="submit" style="display:none;">Confirm Remove</button>
                        <button class="btn btn-secondary btn-small cancel-delete" type="button" style="display:none;">Cancel</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>
</main>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.delete-user-form').forEach(function (form) {
            var reasonInput = form.querySelector('.reason-input');
            var toggleBtn = form.querySelector('.delete-toggle');
            var confirmBtn = form.querySelector('.confirm-delete');
            var cancelBtn = form.querySelector('.cancel-delete');

            if (!reasonInput || !toggleBtn || !confirmBtn || !cancelBtn) return;

            toggleBtn.addEventListener('click', function () {
                reasonInput.style.display = 'inline-block';
                reasonInput.placeholder = 'Deletion reason (optional)';
                reasonInput.required = true;
                confirmBtn.style.display = 'inline-block';
                cancelBtn.style.display = 'inline-block';
                toggleBtn.style.display = 'none';
                reasonInput.focus();
            });

            cancelBtn.addEventListener('click', function () {
                reasonInput.value = '';
                reasonInput.required = false;
                reasonInput.style.display = 'none';
                confirmBtn.style.display = 'none';
                cancelBtn.style.display = 'none';
                toggleBtn.style.display = 'inline-block';
            });

            form.addEventListener('submit', function (e) {
                if (confirmBtn.style.display !== 'none') {
                    if (!confirm('Are you sure you want to permanently remove this user?')) {
                        e.preventDefault();
                    }
                }
            });
        });
    });
</script>
</body>
</html>
