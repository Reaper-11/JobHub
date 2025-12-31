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
<div class="login-wrapper">
    <div class="login-card">
        <div class="login-card__header">
            <h2>Login to your account</h2>
        </div>
        <?php if ($msg): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($msg); ?></div>
        <?php endif; ?>
        <form method="post" class="login-form">
            <label>Email</label>
            <div class="input-icon">
                <span class="icon">&#128100;</span>
                <input type="text" name="email" placeholder="Email" required>
            </div>

            <label>Password</label>
            <div class="input-icon">
                <span class="icon">&#128274;</span>
                <input type="password" name="password" placeholder="Password" required>
            </div>

            <button type="submit" class="btn btn-primary btn-full">Sign in &#10132;</button>
        </form>
        <div class="login-card__footer">
            <a href="#" class="link">Forgot password?</a>
            <a href="register-choice.php" class="link">Don't have an account? Sign up</a>
        </div>
    </div>
</div>
<?php require 'footer.php'; ?>
