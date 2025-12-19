<?php
require '../db.php';
$msg = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (!$username || !$password || !$confirm) {
        $msg = 'All fields are required.';
    } elseif ($password !== $confirm) {
        $msg = 'Passwords do not match.';
    } else {
        // Check if username already exists
        $check = $conn->prepare('SELECT id FROM admins WHERE username = ? LIMIT 1');
        $check->bind_param('s', $username);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $msg = 'Username already exists.';
        } else {
            $hash = md5($password);
            $insert = $conn->prepare('INSERT INTO admins (username, password) VALUES (?, ?)');
            $insert->bind_param('ss', $username, $hash);

            if ($insert->execute()) {
                $msg = 'Admin registration successful. You can login now.';
            } else {
                $msg = 'Registration failed. Please try again.';
            }
            $insert->close();
        }
        $check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Register - JobHub</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
<main class="container">
    <h1>Admin Registration</h1>
    <div class="form-card">
        <?php if ($msg): ?>
            <div class="alert <?php echo strpos($msg, 'successful') !== false ? 'alert-success' : 'alert-error'; ?>">
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>
        <form method="post">
            <label>Username</label>
            <input type="text" name="username" value="<?php echo htmlspecialchars($username); ?>" required>

            <label>Password</label>
            <input type="password" name="password" required>

            <label>Confirm Password</label>
            <input type="password" name="confirm_password" required>

            <button type="submit">Register</button>
        </form>
        <p style="margin-top:10px;">
            Already an admin? <a href="admin-login.php">Login here</a>
        </p>
    </div>
</main>
</body>
</html>
