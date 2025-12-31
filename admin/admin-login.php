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
</head>
<body>
<main class="container">
    <h1>Admin Login</h1>
    <div class="form-card">
        <?php if ($msg): ?><div class="alert alert-error"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
        <form method="post">
            <label>Username</label>
            <input type="text" name="username">
            <label>Password</label>
            <input type="password" name="password">
            <button type="submit">Login</button>
        </form>
    </div>
</main>
</body>
</html>
