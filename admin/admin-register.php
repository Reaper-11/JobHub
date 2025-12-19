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
                $adminId = $conn->insert_id;
                $adminPage = __DIR__ . DIRECTORY_SEPARATOR . 'admin-page-' . $adminId . '.php';
                if (!file_exists($adminPage)) {
                    $pageContent = <<<'PHP'
<?php
require '../db.php';
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit;
}
if ((int)$_SESSION['admin_id'] !== __ADMIN_ID__) {
    header('Location: admin-dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Page - JobHub</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
<main class="container">
    <h1>Admin Page</h1>
    <p>Welcome, <?php echo htmlspecialchars($_SESSION['admin_username']); ?> | <a href="../logout.php">Logout</a></p>
    <div class="card">
        <p>This is your dedicated admin page.</p>
        <ul>
            <li><a href="admin-dashboard.php">Dashboard</a></li>
            <li><a href="admin-jobs.php">Manage Jobs</a></li>
            <li><a href="admin-users.php">Manage Users</a></li>
            <li><a href="admin-applications.php">View Applications</a></li>
        </ul>
    </div>
</main>
</body>
</html>
PHP;
                    $pageContent = str_replace('__ADMIN_ID__', (string)(int)$adminId, $pageContent);
                    file_put_contents($adminPage, $pageContent);
                }
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
