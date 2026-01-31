<?php
// company/company-login.php
require '../db.php';
require '../header.php';

$msg = $msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = "Invalid request.";
        $msg_type = 'danger';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $msg = "Email and password are required.";
            $msg_type = 'danger';
        } else {
            $stmt = $conn->prepare("
                SELECT id, name, password, is_approved, rejection_reason 
                FROM companies 
                WHERE email = ? 
                LIMIT 1
            ");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $company = $result->fetch_assoc();
            $stmt->close();

            if ($company && password_verify($password, $company['password'])) {
                if ($company['is_approved'] == -1) {
                    $reason = $company['rejection_reason'] ? "Reason: " . htmlspecialchars($company['rejection_reason']) : "";
                    $msg = "Your account was rejected. $reason";
                    $msg_type = 'danger';
                } elseif ($company['is_approved'] == 0) {
                    $msg = "Your account is still pending approval.";
                    $msg_type = 'warning';
                } else {
                    $_SESSION['company_id'] = $company['id'];
                    $_SESSION['company_name'] = $company['name'];

                    session_regenerate_id(true);

                    header("Location: company-dashboard.php");
                    exit;
                }
            } else {
                $msg = "Invalid email or password.";
                $msg_type = 'danger';
            }
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body p-5">
                <h2 class="h4 mb-4 text-center">Company Login</h2>

                <?php if ($msg): ?>
                    <div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
                <?php endif; ?>

                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                    <div class="mb-3">
                        <label class="form-label">Company Email</label>
                        <input type="email" name="email" class="form-control" required autofocus>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Login</button>
                </form>

                <div class="text-center mt-3 small">
                    Don't have a company account? 
                    <a href="company-register.php" class="text-primary">Register here</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require '../footer.php'; ?>