<?php
require 'db.php';

$msg = "";
$jobCategories = [
    "Administration / Management",
    "Public Relations / Advertising",
    "Agriculture & Livestock",
    "Engineering / Architecture",
    "Automotive / Automobiles",
    "Communications / Broadcasting",
    "Computer / Technology Management",
    "Computer / Consulting",
    "Computer / System Programming",
    "Construction Services",
    "Contractors",
    "Education",
    "Electronics / Electrical",
    "Entertainment",
    "Engineering",
    "Finance / Accounting",
    "Healthcare / Medical",
    "Hospitality / Tourism",
    "Information Technology (IT)",
    "Manufacturing",
    "Marketing / Sales",
    "Media / Journalism",
    "Retail / Wholesale",
    "Security Services",
    "Transportation / Logistics",
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name  = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $pass  = $_POST["password"] ?? "";
    $preferred_category = trim($_POST["preferred_category"] ?? "");

    if ($name === "" || $email === "" || $pass === "" || $preferred_category === "") {
        $msg = "Please fill all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = "Please enter a valid email address.";
    } elseif (!in_array($preferred_category, $jobCategories, true)) {
        $msg = "Invalid job category selected.";
    } else {

        $emailEsc = $conn->real_escape_string($email);
        $check = $conn->query("SELECT id FROM users WHERE email='$emailEsc' LIMIT 1");

        if ($check && $check->num_rows > 0) {
            $msg = "Email already registered. Please login.";
        } else {

            $nameEsc  = $conn->real_escape_string($name);
            $phoneEsc = $conn->real_escape_string($phone);
            $prefEsc  = $conn->real_escape_string($preferred_category);

            $hash = password_hash($pass, PASSWORD_DEFAULT);

            $sql = "INSERT INTO users (name, email, phone, password, preferred_category)
                    VALUES ('$nameEsc', '$emailEsc', '$phoneEsc', '$hash', '$prefEsc')";

            if ($conn->query($sql)) {
                // Optional: auto-login after registration
                $_SESSION["user_id"] = $conn->insert_id;
                $_SESSION["user_name"] = $name;
                $_SESSION["preferred_category"] = $preferred_category;

                header("Location: index.php");
                exit;
            } else {
                $msg = "Registration failed. Please try again.";
            }
        }
    }
}
?>
<?php require 'header.php'; ?>
<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <h1 class="h3 mb-3">Job Seeker Registration</h1>

        <?php if ($msg): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Full Name<span class="text-danger ms-1">*</span></label>
                        <input class="form-control" type="text" name="name" placeholder="e.g. Ram Khadka" required
                               value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email<span class="text-danger ms-1">*</span></label>
                        <input class="form-control" type="email" name="email" placeholder="e.g. ramesh@gmail.com" required
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input class="form-control" type="tel" name="phone" placeholder="98XXXXXXXX"
                               value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Location</label>
                        <input class="form-control" type="text" name="location" id="location" placeholder="Kathmandu, Nepal" autocomplete="address-level2">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password<span class="text-danger ms-1">*</span></label>
                        <input class="form-control" type="password" name="password" placeholder="Remember your password" required minlength="8">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Prefer Job Category</label>
                        <select name="preferred_category" id="preferred_category" class="form-select" required>
                            <option value="">Select a job category</option>
                            <?php $selectedCategory = $_POST['preferred_category'] ?? ''; ?>
                            <?php foreach ($jobCategories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>"
                                    <?php echo $selectedCategory === $cat ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button class="btn btn-primary w-100" type="submit">Create Account</button>

                    <div class="mt-2 text-muted small">
                        Already have an account? <a class="link-primary text-decoration-none" href="login.php">Login</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require 'footer.php'; ?>
