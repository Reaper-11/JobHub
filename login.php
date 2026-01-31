<?php
// login.php
require 'db.php';
require 'header.php';

$msg = '';
$msg_type = 'alert-danger';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = "Invalid request. Please try again.";
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $msg = "Email and password are required.";
        } else {
            $stmt = $conn->prepare("SELECT id, name, password, role 
                                    FROM users 
                                    WHERE email = ? AND is_active = 1 
                                    LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if ($user && password_verify($password, $user['password'])) {
                // Login successful
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['role']      = $user['role'];

                // Optional: regenerate session ID to prevent fixation
                session_regenerate_id(true);

                header("Location: index.php");
                exit;
            } else {
                $msg = "Invalid email or password.";
            }
        }
    }
}
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
                               placeholder="name@example.com" required autofocus>
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
                        <a href="#" class="text-muted">Forgot password?</a><br>
                        Don't have an account? 
                        <a href="register.php" class="text-primary">Create one</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require 'footer.php'; ?>