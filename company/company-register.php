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
<h1 class="mb-3">Company Registration</h1>
<div class="card shadow-sm">
    <div class="card-body">
        <?php if ($msg): ?>
            <div class="alert <?php echo strpos($msg,'registered')!==false?'alert-success':'alert-danger'; ?>">
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>
        <form method="post">
            <div class="mb-3">
                <label class="form-label">Company Name *</label>
                <input type="text" class="form-control" name="name" placeholder="e.g. JobHub Pvt. Ltd." required>
            </div>

            <div class="mb-3">
                <label class="form-label">Company Email *</label>
                <input type="email" class="form-control" name="email" placeholder="company@example.com" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Password *</label>
                <input type="password" class="form-control" name="password" placeholder="********" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Website</label>
                <input type="url" class="form-control" name="website" placeholder="https://company.com">
            </div>

            <div class="mb-3">
                <label class="form-label">Location</label>
                <input type="text" class="form-control" name="location" placeholder="Kathmandu, Nepal">
            </div>

            <button type="submit" class="btn btn-primary w-100">Register Company</button>
        </form>
        <p class="text-muted small mt-3">Already have an account? <a class="link-primary text-decoration-none" href="company-login.php">Login</a></p>
    </div>
</div>
<?php require '../footer.php'; ?>


