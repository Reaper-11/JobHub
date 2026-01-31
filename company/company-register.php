<?php
// company/company-register.php
require '../db.php';
require '../header.php';

$msg = $msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = "Invalid request.";
        $msg_type = 'danger';
    } else {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $website  = trim($_POST['website'] ?? '');
        $location = trim($_POST['location'] ?? '');

        if (empty($name) || empty($email) || empty($password)) {
            $msg = "Company name, email and password are required.";
            $msg_type = 'danger';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg = "Invalid email format.";
            $msg_type = 'danger';
        } elseif (strlen($password) < 8) {
            $msg = "Password must be at least 8 characters.";
            $msg_type = 'danger';
        } else {
            $check = $conn->prepare("SELECT id FROM companies WHERE email = ? LIMIT 1");
            $check->bind_param("s", $email);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $msg = "This email is already registered.";
                $msg_type = 'danger';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare("
                    INSERT INTO companies (name, email, password, website, location, is_approved, created_at)
                    VALUES (?, ?, ?, ?, ?, 0, NOW())
                ");
                $stmt->bind_param("sssss", $name, $email, $hash, $website, $location);

                if ($stmt->execute()) {
                    $company_id = $conn->insert_id;

                    $_SESSION['company_id'] = $company_id;
                    $_SESSION['company_name'] = $name;

                    $msg = "Company registered successfully! Waiting for admin approval.";
                    $msg_type = 'success';

                    // Optional: redirect to dashboard after registration
                    // header("Location: company-dashboard.php");
                } else {
                    $msg = "Registration failed. Try again later.";
                    $msg_type = 'danger';
                }
                $stmt->close();
            }
            $check->close();
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body p-5">
                <h2 class="h4 mb-4 text-center">Register Your Company</h2>

                <?php if ($msg): ?>
                    <div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
                <?php endif; ?>

                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                    <div class="mb-3">
                        <label class="form-label">Company Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Company Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" required minlength="8">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Website (optional)</label>
                        <input type="url" name="website" class="form-control">
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Location (optional)</label>
                        <input type="text" name="location" class="form-control" placeholder="e.g. Kathmandu, Nepal">
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Register Company</button>
                </form>

                <div class="text-center mt-3 small">
                    Already have an account? 
                    <a href="company-login.php" class="text-primary">Login here</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require '../footer.php'; ?>