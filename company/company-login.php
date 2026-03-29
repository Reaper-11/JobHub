<?php
// company/company-login.php
require '../db.php';

$msg = $msg_type = '';
$email = '';

if (!empty($_SESSION['company_auth_error'])) {
    $msg = $_SESSION['company_auth_error'];
    $msg_type = 'danger';
    unset($_SESSION['company_auth_error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = "Invalid request.";
        $msg_type = 'danger';
    } else {
        $throttle = jobhub_auth_throttle_status('company_login');
        if (!$throttle['allowed']) {
            $msg = "Too many failed login attempts. Please try again later.";
            $msg_type = 'danger';
        } else {
            $email = strtolower(trim($_POST['email'] ?? ''));
            $password = $_POST['password'] ?? '';

            if (empty($email) || empty($password)) {
                $msg = "Email and password are required.";
                $msg_type = 'danger';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $msg = "Please enter a valid email address.";
                $msg_type = 'danger';
            } else {
                $stmt = $conn->prepare("
                    SELECT id, name, password, is_approved, rejection_reason, is_active
                    FROM companies 
                    WHERE email = ? 
                    LIMIT 1
                ");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                $company = $result->fetch_assoc();
                $stmt->close();

                $passwordOk = $company
                    ? jobhub_verify_password_with_upgrade($conn, 'companies', (int)$company['id'], $password, (string)$company['password'])
                    : false;

                if (!$passwordOk) {
                    jobhub_auth_register_failure('company_login');
                    $msg = "Invalid login credentials.";
                    $msg_type = 'danger';
                } else {
                    if ((int)($company['is_active'] ?? 1) !== 1) {
                        jobhub_auth_clear_failures('company_login');
                        $msg = "Your company account is inactive. Please contact admin.";
                        $msg_type = 'danger';
                    } elseif ((int)($company['is_approved'] ?? 0) === -1) {
                        jobhub_auth_clear_failures('company_login');
                        $msg = "Your company account has been rejected by admin.";
                        if (!empty($company['rejection_reason'])) {
                            $msg .= " Reason: " . $company['rejection_reason'];
                        }
                        $msg_type = 'danger';
                    } else {
                        jobhub_auth_clear_failures('company_login');
                        jobhub_complete_login('company', (int)$company['id'], (string)$company['name']);

                        header("Location: company-dashboard.php");
                        exit;
                    }
                }
            }
        }
    }
}

require '../header.php';
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
                        <input type="email" name="email" class="form-control" required autofocus
                               value="<?= htmlspecialchars($email) ?>">
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
