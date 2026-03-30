<?php
require '../db.php';

$msg = '';
$msgType = 'danger';
$name = trim($_POST['name'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$website = trim($_POST['website'] ?? '');
$location = trim($_POST['location'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = 'Invalid request.';
    } elseif (!jobhub_table_exists($conn, 'accounts') || !jobhub_column_exists($conn, 'companies', 'account_id')) {
        $msg = 'Unified auth schema is not ready. Run the migration first.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($name === '' || $email === '' || $password === '' || $location === '') {
            $msg = 'Company name, email, password, and location are required.';
        } elseif ($nameError = jobhub_validate_company_name($name)) {
            $msg = $nameError;
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg = 'Invalid email format.';
        } elseif ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL)) {
            $msg = 'Please enter a valid website URL.';
        } elseif ($locationError = jobhub_validate_location_value($location)) {
            $msg = $locationError;
        } elseif ($password !== $confirmPassword) {
            $msg = 'Password and confirmation do not match.';
        } elseif ($passwordError = jobhub_validate_password_strength($password)) {
            $msg = $passwordError;
        } elseif (jobhub_email_exists($conn, $email)) {
            $msg = 'This email is already registered.';
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $mirrorPassword = jobhub_column_exists($conn, 'companies', 'password');

            $conn->begin_transaction();

            try {
                $accountId = jobhub_create_account($conn, $name, $email, $passwordHash, 'company', 'active');
                if (!$accountId) {
                    throw new RuntimeException('Could not create company account.');
                }

                $columns = ['account_id', 'name', 'email', 'website', 'location'];
                $placeholders = ['?', '?', '?', '?', '?'];
                $types = 'issss';
                $params = [$accountId, $name, $email, $website, $location];

                if ($mirrorPassword) {
                    $columns[] = 'password';
                    $placeholders[] = '?';
                    $types .= 's';
                    $params[] = $passwordHash;
                }

                if (jobhub_column_exists($conn, 'companies', 'is_approved')) {
                    $columns[] = 'is_approved';
                    $placeholders[] = '0';
                }

                $columns[] = 'created_at';
                $placeholders[] = 'NOW()';

                $sql = 'INSERT INTO companies (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    throw new RuntimeException('Could not prepare company profile insert.');
                }

                $stmt->bind_param($types, ...$params);
                if (!$stmt->execute()) {
                    $error = $stmt->error;
                    $stmt->close();
                    throw new RuntimeException($error !== '' ? $error : 'Could not create company profile.');
                }

                $companyId = (int) $conn->insert_id;
                $stmt->close();

                log_activity(
                    $conn,
                    $companyId,
                    'company',
                    'company_registration',
                    "New company registered: {$name}",
                    'company',
                    $companyId
                );

                $conn->commit();
                jobhub_complete_login('company', $companyId, $name);
                header('Location: company-dashboard.php');
                exit;
            } catch (Throwable $e) {
                $conn->rollback();
                error_log('Company registration failed: ' . $e->getMessage());
                $msg = 'Registration failed. Try again later.';
            }
        }
    }
}

require '../header.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body p-5">
                <h1 class="h4 mb-4 text-center">Register Your Company</h1>

                <?php if ($msg !== ''): ?>
                    <div class="alert alert-<?= htmlspecialchars($msgType) ?>"><?= htmlspecialchars($msg) ?></div>
                <?php endif; ?>

                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                    <div class="mb-3">
                        <label class="form-label">Company Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($name) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Company Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($email) ?>">
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
                        <label class="form-label">Website</label>
                        <input type="url" name="website" class="form-control" value="<?= htmlspecialchars($website) ?>">
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Location <span class="text-danger">*</span></label>
                        <input type="text" name="location" class="form-control" placeholder="e.g. Kathmandu, Nepal" required value="<?= htmlspecialchars($location) ?>">
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Register Company</button>
                </form>

                <div class="text-center mt-3 small">
                    Already have an account?
                    <a href="../login.php" class="text-primary">Login here</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require '../footer.php'; ?>
