<?php
require 'db.php';

$msg = '';
$msgType = 'alert-danger';

$jobCategories = require __DIR__ . '/includes/categories.php';
$experienceLevels = require __DIR__ . '/includes/experience_levels.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = 'Invalid request. Please try again.';
    } elseif (!jobhub_table_exists($conn, 'accounts') || !jobhub_column_exists($conn, 'users', 'account_id')) {
        $msg = 'Unified auth schema is not ready. Run the migration first.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $category = trim($_POST['preferred_category'] ?? '');
        $experienceLevel = trim($_POST['experience_level'] ?? '');

        if ($name === '' || $email === '' || $password === '' || $category === '') {
            $msg = 'All required fields must be filled.';
        } elseif ($nameError = jobhub_validate_person_name($name)) {
            $msg = $nameError;
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg = 'Please enter a valid email address.';
        } elseif ($password !== $confirmPassword) {
            $msg = 'Password and confirmation do not match.';
        } elseif ($passwordError = jobhub_validate_password_strength($password)) {
            $msg = $passwordError;
        } elseif (!in_array($category, $jobCategories, true)) {
            $msg = 'Invalid job category selected.';
        } elseif ($experienceLevel !== '' && !in_array($experienceLevel, $experienceLevels, true)) {
            $msg = 'Invalid experience level selected.';
        } else {
            if ($phone !== '') {
                $digits = preg_replace('/\D+/', '', $phone);
                if (strlen($digits) === 13 && substr($digits, 0, 3) === '977') {
                    $digits = substr($digits, 3);
                }

                if (strlen($digits) !== 10) {
                    $msg = 'Phone number must be exactly 10 digits.';
                } else {
                    $phone = $digits;
                }
            }

            if ($msg === '' && jobhub_email_exists($conn, $email)) {
                $msg = 'This email is already registered.';
            }
        }

        if ($msg === '') {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $phoneValue = $phone === '' ? null : $phone;
            $mirrorPassword = jobhub_column_exists($conn, 'users', 'password');
            $hasExperienceColumn = jobhub_column_exists($conn, 'users', 'experience_level');

            $conn->begin_transaction();

            try {
                $accountId = jobhub_create_account($conn, $name, $email, $passwordHash, 'jobseeker', 'active');
                if (!$accountId) {
                    throw new RuntimeException('Could not create account.');
                }

                $columns = ['account_id', 'name', 'email', 'phone', 'preferred_category'];
                $placeholders = ['?', '?', '?', '?', '?'];
                $types = 'issss';
                $params = [$accountId, $name, $email, $phoneValue, $category];

                if ($hasExperienceColumn) {
                    $columns[] = 'experience_level';
                    $placeholders[] = '?';
                    $types .= 's';
                    $params[] = $experienceLevel;
                }

                if ($mirrorPassword) {
                    $columns[] = 'password';
                    $placeholders[] = '?';
                    $types .= 's';
                    $params[] = $passwordHash;
                }

                if (jobhub_column_exists($conn, 'users', 'role')) {
                    $columns[] = 'role';
                    $placeholders[] = '?';
                    $types .= 's';
                    $params[] = 'seeker';
                }

                $columns[] = 'created_at';
                $placeholders[] = 'NOW()';

                $sql = 'INSERT INTO users (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    throw new RuntimeException('Could not prepare profile insert.');
                }

                $stmt->bind_param($types, ...$params);
                if (!$stmt->execute()) {
                    $error = $stmt->error;
                    $stmt->close();
                    throw new RuntimeException($error !== '' ? $error : 'Could not create job seeker profile.');
                }

                $userId = (int) $conn->insert_id;
                $stmt->close();

                log_activity(
                    $conn,
                    $userId,
                    'jobseeker',
                    'user_registration',
                    "New user registered: {$name}",
                    'user',
                    $userId
                );

                $conn->commit();

                try {
                    $mailResult = jobhub_send_account_created_email($email, $name, 'jobseeker');
                    if (empty($mailResult['success'])) {
                        $mailMessage = trim((string) ($mailResult['message'] ?? ''));
                        jobhub_log_mail_error(
                            'account-created',
                            'Job seeker account email failed for ' . $email . ': '
                            . ($mailMessage !== '' ? $mailMessage : 'Unknown mail error.')
                        );
                    }
                } catch (Throwable $mailException) {
                    jobhub_log_mail_error(
                        'account-created',
                        'Job seeker account email threw an exception for ' . $email . ': ' . $mailException->getMessage()
                    );
                }

                jobhub_complete_login('jobseeker', $userId, $name);
                header('Location: index.php?welcome=1');
                exit;
            } catch (Throwable $e) {
                $conn->rollback();
                error_log('Job seeker registration failed: ' . $e->getMessage());
                $msg = 'Registration failed. Please try again later.';
            }
        }
    }
}

$bodyClass = 'user-ui';
require 'header.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4 p-md-5">
                <h1 class="h4 mb-4 text-center">Create Job Seeker Account</h1>

                <?php if ($msg !== ''): ?>
                    <div class="alert <?= htmlspecialchars($msgType) ?>"><?= htmlspecialchars($msg) ?></div>
                <?php endif; ?>

                <form method="post" action="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                    <div class="mb-3">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input
                            type="tel"
                            name="phone"
                            class="form-control"
                            inputmode="numeric"
                            maxlength="10"
                            pattern="[0-9]{10}"
                            oninput="this.value=this.value.replace(/\D/g,'').slice(0,10);"
                            placeholder="98XXXXXXXX"
                            value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                        >
                        <div class="form-text">Must be exactly 10 digits.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Preferred Job Category <span class="text-danger">*</span></label>
                        <select name="preferred_category" class="form-select" required>
                            <option value="">Select category...</option>
                            <?php foreach ($jobCategories as $category): ?>
                                <option value="<?= htmlspecialchars($category) ?>" <?= ($_POST['preferred_category'] ?? '') === $category ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Experience Level</label>
                        <select name="experience_level" class="form-select">
                            <option value="">Select experience level...</option>
                            <?php foreach ($experienceLevels as $level): ?>
                                <option value="<?= htmlspecialchars($level) ?>" <?= ($_POST['experience_level'] ?? '') === $level ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($level) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" required minlength="8">
                        <div class="form-text">Minimum 8 characters with at least one letter and one number.</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="8">
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mb-3">Create Account</button>

                    <div class="text-center small">
                        Already have an account?
                        <a href="login.php" class="text-primary">Sign in</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require 'footer.php'; ?>
