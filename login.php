<?php
// login.php
require 'db.php';

$msg = '';
$msg_type = 'alert-danger';
$email = '';

if (!empty($_SESSION['auth_error'])) {
    $msg = $_SESSION['auth_error'];
    unset($_SESSION['auth_error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = "Invalid request. Please try again.";
    } else {
        $throttle = jobhub_auth_throttle_status('user_login');
        if (!$throttle['allowed']) {
            $msg = "Too many failed login attempts. Please try again later.";
        } else {
            $email    = strtolower(trim($_POST['email'] ?? ''));
            $password = $_POST['password'] ?? '';

            if (empty($email) || empty($password)) {
                $msg = "Email and password are required.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $msg = "Please enter a valid email address.";
            } else {
                $stmt = $conn->prepare("SELECT id, name, password, role, account_status, is_active
                                        FROM users 
                                        WHERE email = ?
                                        LIMIT 1");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
                $stmt->close();

                $passwordOk = $user
                    ? jobhub_verify_password_with_upgrade($conn, 'users', (int)$user['id'], $password, (string)$user['password'])
                    : false;

                if (!$passwordOk) {
                    jobhub_auth_register_failure('user_login');
                    $msg = "Invalid login credentials.";
                } else {
                    $status = strtolower((string)($user['account_status'] ?? 'active'));
                    if ($status !== 'blocked' && $status !== 'removed' && (int)($user['is_active'] ?? 1) !== 1) {
                        $status = 'blocked';
                    }

                    // Account status checks stop blocked or removed accounts from being used.
                    if ($status === 'blocked') {
                        jobhub_auth_clear_failures('user_login');
                        $msg = "Your account has been blocked by admin.";
                    } elseif ($status === 'removed') {
                        jobhub_auth_clear_failures('user_login');
                        $msg = "Your account is no longer available.";
                    } else {
                        jobhub_auth_clear_failures('user_login');
                        jobhub_complete_login('seeker', (int)$user['id'], (string)$user['name'], (string)$user['role']);

                        header("Location: index.php");
                        exit;
                    }
                }
            }
        }
    }
}

$bodyClass = 'user-ui';
require 'header.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-6 col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4 p-md-5">
                <h2 class="h4 mb-4 text-center">Sign In to JobHub</h2>

                <?php if ($msg): ?>
                    <div class="alert <?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
                <?php endif; ?>

                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                    <div class="mb-3">
                        <label class="form-label">Email address</label>
                        <input type="email" name="email" class="form-control" 
                               placeholder="name@example.com" required autofocus
                               value="<?= htmlspecialchars($email) ?>">
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" 
                               placeholder="••••••••" required>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mb-3">
                        Sign In
                    </button>

                    <div class="text-center small">
                        Don't have an account? 
                        <a href="register.php" class="text-primary">Create one</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require 'footer.php'; ?>
