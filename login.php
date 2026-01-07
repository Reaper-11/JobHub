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
<style>
    .user-login-page {
        margin-bottom: 20px;
    }

    .user-login-page .login-card {
        width: 100%;
        max-width: 600px;
        background: #ffffff;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 0 5px rgba(0, 0, 0, 0.05);
        border: 1px solid #e6e6e6;
        margin: 0 auto 20px;
    }

    .user-login-page .required-star {
        color: #ff3b3b;
        margin-left: 4px;
    }

    .user-login-page .login-form label {
        display: block;
        margin: 6px 0 4px;
        font-weight: 700;
        color: #374151;
    }

    .user-login-page .input-group {
        position: relative;
        margin: 6px 0 12px;
    }

    .user-login-page .input-group input {
        width: 100%;
        padding-left: 12px;
        padding-right: 12px;
    }

    .user-login-page .btn.btn-primary {
        transition: background-color 0.25s ease, opacity 0.25s ease, filter 0.25s ease;
    }

    .user-login-page .btn.btn-primary:hover:not(:disabled) {
        filter: brightness(0.92);
    }

    .user-login-page .btn.btn-primary:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        box-shadow: none;
        transform: none;
        filter: none;
    }

    .user-login-page .login-card__footer {
        margin-top: 16px;
    }
</style>
<div class="login-wrapper user-login-page">
    <div class="login-card">
        <div class="login-card__header">
            <h2>Login to your account</h2>
        </div>
        <?php if ($msg): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($msg); ?></div>
        <?php endif; ?>
        <form method="post" class="login-form">
            <label>Email<span class="required-star">*</span></label>
            <div class="input-group">
                <input type="email" name="email" placeholder="e.g. ramesh@gmail.com" required autocomplete="email">
            </div>

            <label>Password<span class="required-star">*</span></label>
            <div class="input-group">
                <input type="password" name="password" placeholder="&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;" required autocomplete="current-password">
            </div>

            <button type="submit" class="btn btn-primary btn-full">Sign in &#10132;</button>
        </form>
        <div class="login-card__footer">
            <a href="register-choice.php" class="link">Don't have an account? Create one</a>
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
