<?php
// register.php
require 'db.php';
require 'header.php';

$msg = '';
$msg_type = 'alert-danger';

$job_categories = [
    "Administration / Management", "Public Relations / Advertising", "Agriculture & Livestock",
    "Engineering / Architecture", "Information Technology (IT)", "Marketing / Sales",
    "Finance / Accounting", "Healthcare / Medical", "Education", "Hospitality / Tourism",
    // ... add the rest as needed
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = "Invalid request. Please try again.";
    } else {
        $name      = trim($_POST['name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');
        $password  = $_POST['password'] ?? '';
        $category  = trim($_POST['preferred_category'] ?? '');

        // Basic validation
        if (empty($name) || empty($email) || empty($password) || empty($category)) {
            $msg = "All required fields must be filled.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg = "Please enter a valid email address.";
        } elseif ($phone !== '') {
            // Keep only digits
            $digits = preg_replace('/\D+/', '', $phone);

            // If user enters +977XXXXXXXXXX or 977XXXXXXXXXX
            if (strlen($digits) === 13 && substr($digits, 0, 3) === '977') {
                $digits = substr($digits, 3);
            }

            if (strlen($digits) !== 10) {
                $msg = "Phone number must be exactly 10 digits.";
            } else {
                $phone = $digits;
            }
        } elseif (strlen($password) < 8) {
            $msg = "Password must be at least 8 characters long.";
        } elseif (!in_array($category, $job_categories)) {
            $msg = "Invalid job category selected.";
        } else {
            // Check if email exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $msg = "This email is already registered.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare("
                    INSERT INTO users (name, email, phone, password, preferred_category, role, created_at)
                    VALUES (?, ?, ?, ?, ?, 'seeker', NOW())
                ");
                $stmt->bind_param("sssss", $name, $email, $phone, $hash, $category);

                if ($stmt->execute()) {
                    $user_id = $conn->insert_id;

                    $_SESSION['user_id']   = $user_id;
                    $_SESSION['user_name'] = $name;
                    $_SESSION['role']      = 'seeker';

                    header("Location: index.php?welcome=1");
                    exit;
                } else {
                    $msg = "Registration failed. Please try again later.";
                }
            }
            $stmt->close();
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4 p-md-5">
                <h2 class="h4 mb-4 text-center">Create Job Seeker Account</h2>

                <?php if ($msg): ?>
                    <div class="alert <?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
                <?php endif; ?>

                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                    <div class="mb-3">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required
                               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" required
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
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
                            <?php foreach ($job_categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>"
                                    <?= ($_POST['preferred_category'] ?? '') === $cat ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" required minlength="8">
                        <div class="form-text">Minimum 8 characters</div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mb-3">
                        Create Account
                    </button>

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
