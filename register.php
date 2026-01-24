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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Job Seeker Registration - JobHub</title>

    <!-- If you already use Bootstrap, keep this link; otherwise you can remove it -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

    <style>
        .user-register-page{
            --bg:#e5e7eb;
            --text:#2f3640;
            --muted:#6b7280;
            --primary:#1e3799;
            --primary-dark:#162c7a;
            --card:#ffffff;
            --border:#e6e6e6;
            --radius-sm:4px;
            --radius-md:8px;
            --shadow-sm:0 0 5px rgba(0,0,0,0.05);
            --shadow-md:0 6px 18px rgba(0,0,0,0.10);
            background:var(--bg);
            color:var(--text);
            min-height:100vh;
            display:flex;
            flex-direction:column;
        }

        .user-register-page *{
            box-sizing:border-box;
            margin:0;
            padding:0;
            font-family:"Inter","Segoe UI",Roboto,Arial,sans-serif;
        }

        .user-register-page .container{
            width:90%;
            max-width:1100px;
            margin:0 auto;
        }

        .user-register-page .topbar{
            background:linear-gradient(135deg, #1e3799, #273c75);
            color:#fff;
            padding:10px 0;
            margin-bottom:20px;
            box-shadow:0 6px 18px rgba(0,0,0,0.08);
        }
        .user-register-page .topbar .brand{
            font-weight:800;
            letter-spacing:0.2px;
            font-size:22px;
        }
        .user-register-page .topbar a{
            color:#fff;
            text-decoration:none;
            font-size:14px;
            margin-left:15px;
            opacity:0.95;
        }
        .user-register-page .topbar a:hover{
            text-decoration:underline;
            opacity:1;
        }

        .user-register-page .page-wrap{
            width:90%;
            max-width:1100px;
            margin:0 auto;
            padding:0 0 20px;
            flex:1 0 auto;
        }
        .user-register-page .title{
            margin-bottom:15px;
            color:#1f2937;
            font-size:28px;
            font-weight:700;
        }

        .user-register-page .form-card{
            background:var(--card);
            border-radius:var(--radius-md);
            padding:20px;
            margin:0 auto 20px;
            box-shadow:var(--shadow-sm);
            border:1px solid var(--border);
            max-width:600px;
        }

        .user-register-page .form-label{
            display:block;
            margin:6px 0 4px;
            font-weight:700;
            color:#374151;
        }
        .user-register-page .required-star{
            color:#374151;
            margin-left:4px;
        }

        .user-register-page .form-control,
        .user-register-page .form-select{
            width:100%;
            padding:10px 12px;
            margin:6px 0 12px;
            border:1px solid #d1d5db;
            border-radius:var(--radius-sm);
            background:#fff;
            color:#111827;
            transition:border-color 0.15s ease, box-shadow 0.15s ease;
        }
        .user-register-page .form-control:focus,
        .user-register-page .form-select:focus{
            outline:none;
            border-color:var(--primary);
            box-shadow:0 0 0 3px rgba(30,55,153,0.15);
        }

        .user-register-page .user-register-btn{
            width:100%;
            margin-top:10px;
            padding:10px 14px;
            background:var(--primary);
            color:#fff;
            border:none;
            border-radius:var(--radius-sm);
            cursor:pointer;
            transition:background-color 0.2s ease;
        }
        .user-register-page .user-register-btn:hover{
            background:var(--primary-dark);
        }

        .user-register-page .helper{
            font-size:13px;
            color:var(--muted);
            margin:6px 0;
        }

        .user-register-page .alert-custom{
            border-radius:var(--radius-sm);
            padding:10px 12px;
            margin-bottom:10px;
            font-size:13px;
            border:1px solid #ffd0d0;
            background:#ffe5e5;
            color:#c0392b;
        }

        .user-register-page .footerbar{
            margin-top:30px;
            padding:15px 0;
            text-align:center;
            background:linear-gradient(135deg, #1e3799, #273c75);
            color:#fff;
            font-size:14px;
        }
    </style>
</head>
<body>
    <div class="user-register-page">

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
                    <label class="form-label">Full Name<span class="required-star">*</span></label>
                    <input class="form-control" type="text" name="name" placeholder="e.g. Ram Khadka" required
                           value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                </div>

                <div class="mb-2">
                    <label class="form-label">Email<span class="required-star">*</span></label>
                    <input class="form-control" type="email" name="email" placeholder="e.g. ramesh@gmail.com" required
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>

                <div class="mb-2">
                    <label class="form-label">Phone</label>
                    <input class="form-control" type="tel" name="phone" placeholder="98XXXXXXXX"
                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                </div>

                <div class="mb-2">
                    <label class="form-label">Location</label>
                    <input class="form-control" type="text" name="location" id="location" placeholder="Kathmandu, Nepal" autocomplete="address-level2">
                </div>

                <div class="mb-2">
                    <label class="form-label">Password<span class="required-star">*</span></label>
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

                <button class="btn btn-primary w-100 user-register-btn" type="submit">Create Account</button>

                <div class="helper">
                    Already have an account? <a href="login.php">Login</a>
                </div>
            </form>
        </div>

    </div>

    <div class="footerbar">&copy; 2025 JobHub - Simple Job Portal</div>

    </div>
</body>
</html>
