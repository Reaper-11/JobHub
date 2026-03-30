<?php
// admin/admin-login.php
require '../db.php';

$msg = '';
$msg_type = 'alert-danger';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = "Invalid request. Please try again.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $throttle = jobhub_auth_throttle_status('admin_login');
        if (!$throttle['allowed']) {
            $msg = "Too many failed login attempts. Please try again later.";
        } elseif (empty($username) || empty($password)) {
            $msg = "Username and password are required.";
        } elseif (strlen($username) > 80) {
            $msg = "Invalid login credentials.";
        } else {
            $stmt = $conn->prepare("SELECT id, username, password FROM admins WHERE username = ? LIMIT 1");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            $admin = $result->fetch_assoc();
            $stmt->close();

            $passwordOk = $admin
                ? jobhub_verify_password_with_upgrade($conn, 'admins', (int)$admin['id'], $password, (string)$admin['password'])
                : false;

            if ($passwordOk) {
                jobhub_auth_clear_failures('admin_login');
                jobhub_complete_login('admin', (int)$admin['id'], (string)$admin['username']);
                header("Location: admin-dashboard.php");
                exit;
            } else {
                jobhub_auth_register_failure('admin_login');
                $msg = "Invalid login credentials.";
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

            <div style="color: red; background: #ffe6e6; padding: 10px; border-radius: 5px; margin-bottom: 15px; font-weight: bold; text-align: center;">
                Restricted area &ndash; only admin is allowed to login.
            </div>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" required autofocus
                           value="<?= htmlspecialchars($username) ?>">
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
