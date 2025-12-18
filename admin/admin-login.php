<?php
require 'db.php';
$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username']);
    $pass = $_POST['password'];
    $hash = md5($pass);

    $u = $conn->real_escape_string($user);
    $res = $conn->query("SELECT id FROM admins WHERE username='$u' AND password='$hash'");
    if ($res->num_rows == 1) {
        $row = $res->fetch_assoc();
        $_SESSION['admin_id'] = $row['id'];
        $_SESSION['admin_username'] = $user;
        header("Location: admin-dashboard.php");
        exit;
    } else {
        $msg = "Invalid admin credentials.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login - JobHub</title>
    <link rel="stylesheet" href="style.css">
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
