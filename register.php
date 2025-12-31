<?php
require 'db.php';

$msg = "";
$jobCategories = [
    "IT & Software",
    "Marketing",
    "Sales",
    "Finance",
    "Design",
    "Education",
    "Healthcare",
    "Engineering",
    "Part-Time",
    "Internship",
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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Job Seeker Registration - JobHub</title>

    <!-- If you already use Bootstrap, keep this link; otherwise you can remove it -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

    <style>
        :root{
            --primary:#1f3aa9;
            --bg:#f3f5f9;
            --card:#ffffff;
            --muted:#6b7280;
            --border:#e5e7eb;
        }

        body{
            background:var(--bg);
            min-height:100vh;
            display:flex;
            flex-direction:column;
        }

        /* Top Navbar style (matches screenshot vibe) */
        .topbar{
            background:var(--primary);
            color:#fff;
            padding:12px 0;
        }
        .topbar .brand{
            font-weight:700;
            font-size:22px;
            letter-spacing:.3px;
        }
        .topbar a{
            color:#fff;
            text-decoration:none;
            font-size:14px;
            margin-left:16px;
            opacity:.95;
        }
        .topbar a:hover{ opacity:1; text-decoration:underline; }
        .admin-pill{
            background:#ff4d4d;
            padding:6px 10px;
            border-radius:6px;
            margin-left:12px;
            display:inline-block;
        }

        /* Page Container */
        .page-wrap{
            max-width:1100px;
            margin:0 auto;
            padding:28px 18px 40px;
            width:100%;
            flex:1 0 auto;
        }
        .title{
            font-size:28px;
            font-weight:700;
            margin-bottom:18px;
            color:#111827;
        }

        /* Form Card */
        .form-card{
            background:var(--card);
            border:1px solid var(--border);
            border-radius:10px;
            padding:22px;
            box-shadow:0 10px 22px rgba(0,0,0,.04);
        }

        .form-label{
            font-weight:600;
            color:#111827;
            margin-top:12px;
        }

        /* IMPORTANT FIX: force ALL inputs full width */
        .form-control, .form-select{
            width:100% !important;
            height:44px;
            border-radius:8px;
            border:1px solid var(--border);
        }
        .form-control:focus, .form-select:focus{
            border-color:rgba(31,58,169,.55);
            box-shadow:0 0 0 .2rem rgba(31,58,169,.15);
        }

        .btn-primary{
            background:var(--primary);
            border-color:var(--primary);
            height:46px;
            border-radius:8px;
            font-weight:600;
        }
        .btn-primary:hover{
            background:#193190;
            border-color:#193190;
        }

        .helper{
            margin-top:12px;
            color:var(--muted);
            font-size:14px;
        }

        .alert-custom{
            border-radius:10px;
            padding:12px 14px;
        }

        /* Footer bar like screenshot bottom strip */
        .footerbar{
            background:var(--primary);
            height:60px;
            display:flex;
            align-items:center;
            justify-content:center;
            color:#fff;
            font-size:14px;
            font-weight:500;
            letter-spacing:.2px;
            flex:0 0 auto;
        }
    </style>
</head>
<body>

    <!-- Top Nav (adjust links to your actual pages if needed) -->
    <div class="topbar">
        <div class="container d-flex align-items-center justify-content-between">
            <div class="brand">JobHub</div>
            <div>
                <a href="index.php">Home</a>
                <a href="register-choice.php">Register</a>
                <a href="login.php">Login</a>
            </div>
        </div>
    </div>

    <div class="page-wrap">
        <div class="title">Job Seeker Registration</div>

        <?php if ($msg): ?>
            <div class="alert alert-danger alert-custom">
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <div class="form-card">
            <form method="POST">
                <div class="mb-2">
                    <label class="form-label">Full Name</label>
                    <input class="form-control" type="text" name="name" placeholder="Enter your name" required
                           value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                </div>

                <div class="mb-2">
                    <label class="form-label">Email</label>
                    <input class="form-control" type="email" name="email" placeholder="Enter email" required
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>

                <div class="mb-2">
                    <label class="form-label">Phone</label>
                    <input class="form-control" type="text" name="phone" placeholder="Enter phone (optional)"
                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                </div>

                <div class="mb-2">
                    <label class="form-label">Password</label>
                    <input class="form-control" type="password" name="password" placeholder="Create password" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Job Preference</label>
                    <select name="preferred_category" class="form-select" required>
                        <option value="">Prefer Job Category</option>
                        <?php
                            $selected = $_POST['preferred_category'] ?? "";
                            foreach ($jobCategories as $cat):
                        ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>"
                                <?php echo ($selected === $cat) ? "selected" : ""; ?>>
                                <?php echo htmlspecialchars($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button class="btn btn-primary w-100" type="submit">Create Account</button>

                <div class="helper">
                    Already have an account? <a href="login.php">Login</a>
                </div>
            </form>
        </div>

    </div>

    <div class="footerbar">&copy; 2025 JobHub - Simple Job Portal</div>

</body>
</html>

