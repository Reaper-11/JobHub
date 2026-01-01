<?php
require '../db.php';
$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name']);
    $email = trim($_POST['email']);
    $pass  = $_POST['password'];
    $website  = trim($_POST['website']);
    $location = trim($_POST['location']);

    if ($name=="" || $email=="" || $pass=="") {
        $msg = "Name, email and password are required.";
    } else {
        $emailEsc = $conn->real_escape_string($email);
        $check = $conn->query("SELECT id FROM companies WHERE email='$emailEsc'");
        if ($check->num_rows > 0) {
            $msg = "Company email already registered.";
        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare(
                "INSERT INTO companies (name,email,password,website,location) VALUES (?,?,?,?,?)"
            );
            $stmt->bind_param("sssss", $name, $email, $hash, $website, $location);
            if ($stmt->execute()) {
                $_SESSION['company_id'] = $conn->insert_id;
                $_SESSION['company_name'] = $name;
                $_SESSION['company_approved'] = 0;
                header("Location: company-dashboard.php");
                exit;
            } else {
                $msg = "Error: ".$conn->error;
            }
        }
    }
}
$basePath = '../';
require '../header.php';
?>
<h1>Company Registration</h1>
<div class="form-card">
    <?php if ($msg): ?>
        <div class="alert <?php echo strpos($msg,'registered')!==false?'alert-success':'alert-error'; ?>">
            <?php echo htmlspecialchars($msg); ?>
        </div>
    <?php endif; ?>
    <form method="post">
        <label>Company Name *</label>
        <input type="text" name="name">

        <label>Company Email *</label>
        <input type="email" name="email">

        <label>Password *</label>
        <input type="password" name="password">

        <label>Website</label>
        <input type="text" name="website">

        <label>Location</label>
        <input type="text" name="location">

        <button type="submit">Register Company</button>
    </form>
    <p class="meta">Already have an account? <a href="company-login.php">Login</a></p>
</div>
<?php require '../footer.php'; ?>

