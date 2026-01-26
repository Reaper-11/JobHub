<?php
require '../db.php';
$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username']);
    $pass = $_POST['password'];
    $u = $conn->real_escape_string($user);
    $res = $conn->query("SELECT id, password FROM admins WHERE username='$u' LIMIT 1");
    if ($res && $res->num_rows == 1) {
        $row = $res->fetch_assoc();
        $storedHash = $row['password'] ?? '';
        $legacyMatch = strlen($storedHash) === 32 && ctype_xdigit($storedHash) && hash_equals($storedHash, md5($pass));
        $valid = password_verify($pass, $storedHash) || $legacyMatch;

        if ($valid) {
            if ($legacyMatch) {
                $newHash = password_hash($pass, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE admins SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $newHash, $row['id']);
                $stmt->execute();
                $stmt->close();
            }
            $_SESSION['admin_id'] = $row['id'];
            $_SESSION['admin_username'] = $user;
            $adminPage = 'admin-page-' . $row['id'] . '.php';
            if (file_exists(__DIR__ . DIRECTORY_SEPARATOR . $adminPage)) {
                header("Location: " . $adminPage);
            } else {
                header("Location: admin-dashboard.php");
            }
            exit;
        }
    }
    $msg = "Invalid admin credentials.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login - JobHub</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        body.admin-login-page {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        body.admin-login-page .admin-login-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 8px 0 90px;
        }
        body.admin-login-page .admin-login-block {
            width: 100%;
            max-width: 560px;
            margin: 0 auto;
        }
        body.admin-login-page .admin-login-header {
            text-align: center;
            margin-bottom: 12px;
        }
        body.admin-login-page .admin-login-header h1 {
            margin: 0 0 4px;
        }
        body.admin-login-page .form-card {
            padding: 28px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }
        .admin-login-subtitle {
            margin: 0;
            font-size: 0.95rem;
            color: #b24a4a;
            text-align: center;
        }
        .admin-login-required {
            color: #c23b3b;
        }
        .admin-login-btn {
            transition: background-color 0.2s ease, opacity 0.2s ease;
        }
        .admin-login-btn:hover:not(:disabled) {
            filter: brightness(0.92);
        }
        .admin-login-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            filter: none;
        }
        .admin-login-page-footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            padding: 15px 0;
            text-align: center;
            background: linear-gradient(135deg, #1e3799, #273c75);
            color: #fff;
            font-size: 14px;
        }
    </style>
</head>
<body class="admin-login-page">
<header class="topbar">
    <div class="container flex-between">
        <div class="logo">JobHub</div>
        <nav>
            <a href="../index.php">Home</a>
        </nav>
    </div>
</header>
<main class="container admin-login-container">
    <div class="admin-login-block">
        <div class="admin-login-header">
            <h1>Admin Login</h1>
            <p class="admin-login-subtitle">Restricted Access - Admin Only</p>
        </div>
        <div class="form-card">
            <?php if ($msg): ?><div class="alert alert-danger"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
            <form method="post">
                <label>Username <span class="admin-login-required">*</span></label>
                <input type="text" name="username" placeholder="e.g. admin" required>
                <label>Password <span class="admin-login-required">*</span></label>
                <input type="password" name="password" placeholder="&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;" required>
                <button type="submit" class="admin-login-btn">Login</button>
            </form>
        </div>
    </div>
</main>
<div class="admin-login-page-footer">© 2026 JobHub Admin Panel – Authorized Access Only</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var form = document.querySelector('form');
        if (!form) return;

        var username = form.querySelector('input[name="username"]');
        var password = form.querySelector('input[name="password"]');
        var submitBtn = form.querySelector('button[type="submit"]');
        if (!username || !password || !submitBtn) return;

        function updateButtonState() {
            var ready = username.value.trim() !== "" && password.value.trim() !== "";
            submitBtn.disabled = !ready || submitBtn.dataset.loading === "true";
        }

        username.addEventListener('input', updateButtonState);
        password.addEventListener('input', updateButtonState);

        form.addEventListener('submit', function () {
            submitBtn.dataset.loading = "true";
            submitBtn.textContent = "Logging in...";
            submitBtn.disabled = true;
        });

        updateButtonState();
        setTimeout(updateButtonState, 0);
    });
</script>
</body>
</html>

