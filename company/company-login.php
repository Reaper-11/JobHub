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
<h1 class="mb-3">Company Login</h1>
<div class="card shadow-sm">
    <div class="card-body">
        <?php if ($msg): ?><div class="alert alert-danger"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
        <form method="post">
            <div class="mb-3">
                <label class="form-label">Company Email</label>
                <input type="email" class="form-control" name="email" placeholder="company@example.com" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" class="form-control" name="password" placeholder="********" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>
        <div class="mt-3 text-center text-muted small">
            Don't have a company account? <a class="link-primary text-decoration-none" href="company-register.php">Register here</a>
        </div>
    </div>
</div>
<?php require '../footer.php'; ?>

