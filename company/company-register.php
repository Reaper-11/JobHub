<?php
// company/company-register.php
require '../db.php';

$msg = $msg_type = '';
$name = '';
$email = '';
$website = '';
$location = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = "Invalid request.";
        $msg_type = 'danger';
    } else {
        $name     = trim($_POST['name'] ?? '');
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $website  = trim($_POST['website'] ?? '');
        $location = trim($_POST['location'] ?? '');

        if (empty($name) || empty($email) || empty($password) || empty($location)) {
            $msg = "Company name, email, password, and location are required.";
            $msg_type = 'danger';
        } elseif ($nameError = jobhub_validate_company_name($name)) {
            $msg = $nameError;
            $msg_type = 'danger';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg = "Invalid email format.";
            $msg_type = 'danger';
        } elseif ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL)) {
            $msg = "Please enter a valid website URL.";
            $msg_type = 'danger';
        } elseif ($locationError = jobhub_validate_location_value($location)) {
            $msg = $locationError;
            $msg_type = 'danger';
        } elseif ($password !== $confirm_password) {
            $msg = "Password and confirmation do not match.";
            $msg_type = 'danger';
        } elseif ($passwordError = jobhub_validate_password_strength($password)) {
            $msg = $passwordError;
            $msg_type = 'danger';
        } else {
            $check = $conn->prepare("SELECT id FROM companies WHERE email = ? LIMIT 1");
            $check->bind_param("s", $email);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $msg = "This email is already registered.";
                $msg_type = 'danger';
            } else {
                // password_hash() stores new company passwords securely.
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare("
                    INSERT INTO companies (name, email, password, website, location, is_approved, created_at)
                    VALUES (?, ?, ?, ?, ?, 0, NOW())
                ");
                $stmt->bind_param("sssss", $name, $email, $hash, $website, $location);

                if ($stmt->execute()) {
                    $company_id = $conn->insert_id;

                    log_activity(
                        $conn,
                        $company_id,
                        'company',
                        'company_registration',
                        "New company registered: {$name}",
                        'company',
                        $company_id
                    );

                    jobhub_complete_login('company', $company_id, $name);

                    header("Location: company-dashboard.php");
                    exit;
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

require '../header.php';
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
                        <input type="text" name="name" class="form-control" required
                               value="<?= htmlspecialchars($name) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Company Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" required
                               value="<?= htmlspecialchars($email) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" required minlength="8">
                        <div class="form-text">Minimum 8 characters with at least one letter and one number.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="8">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Website (optional)</label>
                        <input type="url" name="website" class="form-control"
                               value="<?= htmlspecialchars($website) ?>">
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Location <span class="text-danger">*</span></label>
                        <input type="text" name="location" class="form-control" placeholder="e.g. Kathmandu, Nepal" required
                               value="<?= htmlspecialchars($location) ?>">
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
