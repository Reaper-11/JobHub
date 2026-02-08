<?php
// admin/admin-login.php
require '../db.php';

$msg = '';
$msg_type = 'alert-danger';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = "Invalid request. Please try again.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $msg = "Username and password are required.";
        } else {
            $stmt = $conn->prepare("SELECT id, username, password FROM admins WHERE username = ? LIMIT 1");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            $admin = $result->fetch_assoc();
            $stmt->close();

            if ($admin && password_verify($password, $admin['password'])) {
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];

                // Regenerate session to prevent fixation
                session_regenerate_id(true);

                header("Location: admin-dashboard.php");
                exit;
            } else {
                $msg = "Invalid username or password.";
            }
        }
    }
}

$basePath = '../';
$pageTitle = 'Admin Login - JobHub';
$bodyClass = 'bg-light';
require '../header.php';
?>

<div class="container">
    <div class="card login-card shadow-lg border-0 mx-auto my-5">
        <div class="card-body p-5">
            <h2 class="h4 mb-4 text-center">Admin Login</h2>

            <?php if ($msg): ?>
                <div class="alert <?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" required autofocus>
                </div>

                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>
        </div>
    </div>
</div>

<?php require '../footer.php'; ?>
