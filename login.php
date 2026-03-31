<?php
require 'db.php';

if (is_logged_in()) {
    jobhub_redirect(jobhub_role_home());
}

$msg = '';
$msgType = 'alert-danger';
$email = '';

if (!empty($_SESSION['auth_error'])) {
    $msg = (string) $_SESSION['auth_error'];
    unset($_SESSION['auth_error']);
} elseif (!empty($_SESSION['company_auth_error'])) {
    $msg = (string) $_SESSION['company_auth_error'];
    unset($_SESSION['company_auth_error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = 'Invalid request. Please try again.';
    } else {
        $throttle = jobhub_auth_throttle_status('account_login');
        if (!$throttle['allowed']) {
            $msg = 'Too many failed login attempts. Please try again later.';
        } else {
            $email = strtolower(trim($_POST['email'] ?? ''));
            $password = $_POST['password'] ?? '';

            if ($email === '' || $password === '') {
                $msg = 'Email and password are required.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $msg = 'Please enter a valid email address.';
            } else {
                $loginResult = jobhub_attempt_login($conn, $email, $password);
                if (!($loginResult['success'] ?? false)) {
                    jobhub_auth_register_failure('account_login');
                    $msg = (string) ($loginResult['message'] ?? 'Invalid login credentials.');
                } else {
                    jobhub_auth_clear_failures('account_login');
                    jobhub_redirect((string) ($loginResult['redirect'] ?? 'index.php'));
                }
            }
        }
    }
}

$bodyClass = 'user-ui';
$pageTitle = 'Login to JobHub';
require 'header.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-6 col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4 p-md-5">
                <h1 class="h4 mb-2 text-center">Login to JobHub</h1>
                <p class="text-muted text-center mb-4">Use your email and password. Your dashboard is chosen automatically from your account role.</p>

                <?php if ($msg !== ''): ?>
                    <div class="alert <?= htmlspecialchars($msgType) ?>"><?= htmlspecialchars($msg) ?></div>
                <?php endif; ?>

                <form method="post" action="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                    <div class="mb-3">
                        <label class="form-label">Email address</label>
                        <input
                            type="email"
                            name="email"
                            class="form-control"
                            placeholder="name@example.com"
                            value="<?= htmlspecialchars($email) ?>"
                            required
                            autofocus
                        >
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Password</label>
                        <input
                            type="password"
                            name="password"
                            class="form-control"
                            placeholder="Enter your password"
                            required
                        >
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mb-3">Login</button>
                </form>

                <p style="text-align:center; margin-top:10px; font-size:14px;">
                    Don't have an account?
                    <a href="register.php" style="color:#2563eb; font-weight:600; text-decoration:none;">
                        Register Now
                    </a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require 'footer.php'; ?>
