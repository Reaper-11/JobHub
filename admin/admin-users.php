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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - JobHub</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../custom.css?v=<?php echo filemtime(__DIR__ . '/../custom.css'); ?>">
</head>
<body>
<main class="container py-4">
    <h1 class="mb-2">Manage Users</h1>
    <p><a class="link-primary text-decoration-none" href="admin-dashboard.php">&laquo; Back to Dashboard</a></p>
    <div class="table-responsive">
    <table class="table table-striped table-hover align-middle">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Registered At</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($u = $users->fetch_assoc()): ?>
            <tr>
                <td><?php echo $u['id']; ?></td>
                <td><?php echo htmlspecialchars($u['name']); ?></td>
                <td><?php echo htmlspecialchars($u['email']); ?></td>
                <td><?php echo htmlspecialchars($u['created_at']); ?></td>
                <td>
                    <form class="d-inline delete-user-form" method="post" action="admin-delete.php">
                        <input type="hidden" name="table" value="users">
                        <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                        <input type="hidden" name="return" value="admin-users.php">
                        <input class="form-control form-control-sm d-inline-block reason-input" style="display: none;" type="text" name="reason">
                        <button class="btn btn-sm btn-danger delete-toggle" type="button">Remove User (Permanent)</button>
                        <button class="btn btn-sm btn-danger confirm-delete" style="display: none;" type="submit">Confirm Remove</button>
                        <button class="btn btn-sm btn-outline-secondary cancel-delete" style="display: none;" type="button">Cancel</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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
