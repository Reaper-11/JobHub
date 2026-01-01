<?php
require '../db.php';
$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $pass  = $_POST['password'];
    $emailEsc = $conn->real_escape_string($email);
    $res = $conn->query(
        "SELECT id, name, is_approved, rejection_reason, password FROM companies WHERE email='$emailEsc' LIMIT 1"
    );
    if ($res && $res->num_rows == 1) {
        $row = $res->fetch_assoc();
        $storedHash = $row['password'] ?? '';
        $legacyMatch = strlen($storedHash) === 32 && ctype_xdigit($storedHash) && hash_equals($storedHash, md5($pass));
        $valid = password_verify($pass, $storedHash) || $legacyMatch;

        if ($valid) {
            if ($legacyMatch) {
                $newHash = password_hash($pass, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE companies SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $newHash, $row['id']);
                $stmt->execute();
                $stmt->close();
            }
            if ((int) $row['is_approved'] === -1) {
                $reason = trim((string) ($row['rejection_reason'] ?? ''));
                $msg = $reason !== '' ? "Company account rejected: $reason" : "Company account rejected. Contact admin.";
            } else {
                $_SESSION['company_id']   = $row['id'];
                $_SESSION['company_name'] = $row['name'];
                $_SESSION['company_approved'] = (int) $row['is_approved'];
                header("Location: company-dashboard.php");
                exit;
            }
        } else {
            $msg = "Invalid company email or password.";
        }
    } else {
        $msg = "Invalid company email or password.";
    }
}
$basePath = '../';
require '../header.php';
?>
<h1>Company Login</h1>
<div class="form-card">
    <?php if ($msg): ?><div class="alert alert-error"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <form method="post">
        <label>Company Email</label>
        <input type="email" name="email" placeholder="company@example.com" required>
        <label>Password</label>
        <input type="password" name="password" placeholder="••••••••" required>
        <button type="submit" class="btn-login">Login</button>
    </form>
        <div class="register-cta">
            Don’t have a company account? <a href="company-register.php">Register here</a>
        </div>
    </div>
<style>

.btn-login {
    width: 100%;
    margin-top: 8px;
    padding: 10px 14px;
    background: var(--primary);
    color: #fff;
    border: none;
    border-radius: var(--radius-sm);
    cursor: pointer;
}

.btn-login:hover {
    background: #162c7a;
}

.register-cta {
    margin-top: 12px;
    text-align: center;
    font-size: 14px;
    color: #374151;
}

.register-cta a {
    color: var(--primary);
    text-decoration: none;
}

.register-cta a:hover {
    text-decoration: underline;
}
</style>
<?php require '../footer.php'; ?>
