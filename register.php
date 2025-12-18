<?php
require 'db.php';
$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name']);
    $email = trim($_POST['email']);
    $pass  = $_POST['password'];

    if ($name == "" || $email == "" || $pass == "") {
        $msg = "All fields are required.";
    } else {
        $emailEsc = $conn->real_escape_string($email);
        $check = $conn->query("SELECT id FROM users WHERE email='$emailEsc'");
        if ($check->num_rows > 0) {
            $msg = "Email already registered.";
        } else {
            $hash = md5($pass);
            $stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $name, $email, $hash);
            if ($stmt->execute()) {
                $msg = "Registration successful. You can login now.";
            } else {
                $msg = "Error: " . $conn->error;
            }
        }
    }
}
require 'header.php';
?>
<h1>User Registration</h1>
<div class="form-card">
    <?php if ($msg): ?>
        <div class="alert <?php echo strpos($msg, 'successful') !== false ? 'alert-success' : 'alert-error'; ?>">
            <?php echo htmlspecialchars($msg); ?>
        </div>
    <?php endif; ?>
    <form method="post">
        <label>Full Name</label>
        <input type="text" name="name">
        <label>Email</label>
        <input type="text" name="email">
        <label>Password</label>
        <input type="password" name="password">
        <button type="submit">Register</button>
    </form>
</div>
<?php require 'footer.php'; ?>
