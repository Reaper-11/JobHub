<?php
require 'db.php';
$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $pass  = $_POST['password'];
    $emailEsc = $conn->real_escape_string($email);
    $res = $conn->query("SELECT id, name, password FROM users WHERE email='$emailEsc' LIMIT 1");
    if ($res && $res->num_rows == 1) {
        $row = $res->fetch_assoc();
        $storedHash = $row['password'] ?? '';
        $legacyMatch = strlen($storedHash) === 32 && ctype_xdigit($storedHash) && hash_equals($storedHash, md5($pass));
        $valid = password_verify($pass, $storedHash) || $legacyMatch;

        if ($valid) {
            if ($legacyMatch) {
                $newHash = password_hash($pass, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $newHash, $row['id']);
                $stmt->execute();
                $stmt->close();
            }
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['user_name'] = $row['name'];
            header("Location: index.php");
            exit;
        }
    }
    $msg = "Invalid email or password.";
}
require 'header.php';
?>
<div class="user-login-page">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h4 mb-3">Login to your account</h2>
                    <?php if ($msg): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($msg); ?></div>
                    <?php endif; ?>
                    <form method="post" class="login-form">
                        <label class="form-label">Email<span class="text-danger ms-1">*</span></label>
                        <div class="mb-3">
                            <input type="email" name="email" class="form-control" placeholder="e.g. ramesh@gmail.com" required autocomplete="email">
                        </div>

                        <label class="form-label">Password<span class="text-danger ms-1">*</span></label>
                        <div class="mb-3">
                            <input type="password" name="password" class="form-control" placeholder="&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;" required autocomplete="current-password">
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Sign in &#10132;</button>
                    </form>
                    <div class="mt-3">
                        <a href="register-choice.php" class="link-primary text-decoration-none">Don't have an account? Create one</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var page = document.querySelector('.user-login-page');
        if (!page) return;

        var form = page.querySelector('.login-form');
        var email = form ? form.querySelector('input[name="email"]') : null;
        var password = form ? form.querySelector('input[name="password"]') : null;
        var submitBtn = form ? form.querySelector('button[type="submit"]') : null;
        if (!form || !email || !password || !submitBtn) return;

        function updateButtonState() {
            var ready = email.value.trim() !== "" && password.value.trim() !== "";
            submitBtn.disabled = !ready || submitBtn.dataset.loading === "true";
        }

        email.focus();

        email.addEventListener('input', updateButtonState);
        password.addEventListener('input', updateButtonState);

        form.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && e.target && e.target.tagName === 'INPUT') {
                e.preventDefault();
                form.requestSubmit();
            }
        });

        form.addEventListener('submit', function () {
            submitBtn.dataset.loading = "true";
            submitBtn.textContent = "Signing in...";
            submitBtn.disabled = true;
        });

        updateButtonState();
        setTimeout(updateButtonState, 0);
    });
</script>
<?php require 'footer.php'; ?>
