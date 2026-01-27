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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - JobHub</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../custom.css?v=<?php echo filemtime(__DIR__ . '/../custom.css'); ?>">
</head>
<body>
<header class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand fw-bold" href="../index.php">JobHub</a>
        <div class="ms-auto">
            <a class="btn btn-outline-light btn-sm" href="../index.php">Home</a>
        </div>
    </div>
</header>
<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="text-center mb-3">
                <h1 class="h3 mb-1">Admin Login</h1>
                <p class="text-danger small mb-0">Restricted Access - Admin Only</p>
            </div>
            <div class="card shadow-sm">
                <div class="card-body">
                    <?php if ($msg): ?><div class="alert alert-danger"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="username" placeholder="e.g. admin" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="password" placeholder="********" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 admin-login-btn">Login</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>
<footer class="bg-primary text-white py-3 text-center">
    <div class="container small">© 2026 JobHub Admin Panel - Authorized Access Only</div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


