<?php
require '../db.php';
$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $pass  = $_POST['password'];
    $hash  = md5($pass);

    $emailEsc = $conn->real_escape_string($email);
    $res = $conn->query(
        "SELECT id, name, is_approved FROM companies WHERE email='$emailEsc' AND password='$hash'"
    );
    if ($res->num_rows == 1) {
        $row = $res->fetch_assoc();
        $_SESSION['company_id']   = $row['id'];
        $_SESSION['company_name'] = $row['name'];
        $_SESSION['company_approved'] = (int) $row['is_approved'];
        header("Location: company-dashboard.php");
        exit;
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
        <input type="text" name="email">
        <label>Password</label>
        <input type="password" name="password">
        <button type="submit">Login</button>
    </form>
</div>
<?php require '../footer.php'; ?>
