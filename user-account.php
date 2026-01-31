<?php
require 'db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$uid = (int) $_SESSION['user_id'];
$profileMsg = "";
$profileType = "";
$passMsg = "";
$passType = "";
$deleteMsg = "";
$deleteType = "";
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

// Fetch current user details
$userRes = $conn->query("SELECT name, email, phone, preferred_category, cv_path, profile_image FROM users WHERE id = $uid");
$user = $userRes ? $userRes->fetch_assoc() : [
    'name' => '',
    'email' => '',
    'phone' => '',
    'preferred_category' => '',
    'cv_path' => '',
    'profile_image' => ''
];

$preferenceProfile = function_exists('get_user_preferences') ? get_user_preferences($conn, $uid) : [];
if (!empty($preferenceProfile['preferred_category'])) {
    $user['preferred_category'] = $preferenceProfile['preferred_category'];
}

$recentKeyword = isset($_SESSION['last_search_keyword']) ? (string) $_SESSION['last_search_keyword'] : '';
$recommendedJobs = getRecommendedJobs($conn, $uid, $recentKeyword);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $preferred_category = trim($_POST['preferred_category'] ?? '');
        $currentCv = $user['cv_path'] ?? '';
        $uploadError = '';
        $newCvPath = $currentCv;

        if ($name === '' || $email === '' || $preferred_category === '') {
            $profileMsg = "Name, email, and category are required.";
            $profileType = "alert-danger";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $profileMsg = "Please enter a valid email address.";
            $profileType = "alert-danger";
        } elseif ($phone !== '') {
            // Keep only digits
            $digits = preg_replace('/\D+/', '', $phone);

            // If user enters +977XXXXXXXXXX or 977XXXXXXXXXX
            if (strlen($digits) === 13 && substr($digits, 0, 3) === '977') {
                $digits = substr($digits, 3);
            }

            if (strlen($digits) !== 10) {
                $profileMsg = "Phone number must be exactly 10 digits.";
                $profileType = "alert-danger";
            } else {
                $phone = $digits;
            }
        } elseif (!in_array($preferred_category, $jobCategories, true)) {
            $profileMsg = "Invalid job category selected.";
            $profileType = "alert-danger";
        } else {
            if (isset($_FILES['cv_file']) && $_FILES['cv_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES['cv_file']['error'] !== UPLOAD_ERR_OK) {
                    $uploadError = "Could not upload CV. Please try again.";
                } else {
                    $maxBytes = 5 * 1024 * 1024;
                    $fileSize = (int) $_FILES['cv_file']['size'];
                    $ext = strtolower(pathinfo($_FILES['cv_file']['name'], PATHINFO_EXTENSION));
                    $allowed = ['pdf'];
                    if (!in_array($ext, $allowed, true)) {
                        $uploadError = "CV must be a PDF document.";
                    } elseif ($fileSize > $maxBytes) {
                        $uploadError = "CV must be 5MB or smaller.";
                    } else {
                        $uploadDir = __DIR__ . '/uploads/cv';
                        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
                            $uploadError = "Upload folder is not available.";
                        } else {
                            $fileName = 'cv_' . $uid . '_' . time() . '.' . $ext;
                            $destPath = $uploadDir . '/' . $fileName;
                            if (move_uploaded_file($_FILES['cv_file']['tmp_name'], $destPath)) {
                                $newCvPath = 'uploads/cv/' . $fileName;
                            } else {
                                $uploadError = "Could not save CV file.";
                            }
                        }
                    }
                }
            }

            if ($uploadError !== '') {
                $profileMsg = $uploadError;
                $profileType = "alert-danger";
            } else {
                $emailEsc = $conn->real_escape_string($email);
                $check = $conn->query("SELECT id FROM users WHERE email='$emailEsc' AND id <> $uid");
                if ($check && $check->num_rows > 0) {
                    $profileMsg = "That email is already in use.";
                    $profileType = "alert-danger";
                } else {
                    $phoneVal = $phone === '' ? null : $phone;
                    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, preferred_category = ?, cv_path = ? WHERE id = ?");
                    $stmt->bind_param("sssssi", $name, $email, $phoneVal, $preferred_category, $newCvPath, $uid);
                    if ($stmt->execute()) {
                        if (function_exists('db_table_exists') && db_table_exists('user_preferences')) {
                            $prefStmt = $conn->prepare("INSERT INTO user_preferences (user_id, preferred_category) VALUES (?, ?) ON DUPLICATE KEY UPDATE preferred_category = VALUES(preferred_category), updated_at = CURRENT_TIMESTAMP");
                            if ($prefStmt) {
                                $prefStmt->bind_param("is", $uid, $preferred_category);
                                $prefStmt->execute();
                                $prefStmt->close();
                            }
                        }
                        $profileMsg = "Profile updated successfully.";
                        $profileType = "alert-success";
                        $_SESSION['user_name'] = $name;
                        $_SESSION['preferred_category'] = $preferred_category;
                        $user['name'] = $name;
                        $user['email'] = $email;
                        $user['phone'] = $phone;
                        $user['preferred_category'] = $preferred_category;
                        $user['cv_path'] = $newCvPath;
                    } else {
                        $profileMsg = "Could not update profile. Please try again.";
                        $profileType = "alert-danger";
                    }
                    $stmt->close();
                }
            }
        }
    } elseif ($action === 'password') {
        $old = $_POST['old_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($old === '' || $new === '' || $confirm === '') {
            $passMsg = "All fields are required.";
            $passType = "alert-danger";
        } elseif ($new !== $confirm) {
            $passMsg = "New password and confirmation do not match.";
            $passType = "alert-danger";
        } else {
            $res = $conn->query("SELECT password FROM users WHERE id = $uid");
            $row = $res ? $res->fetch_assoc() : null;
            $storedHash = $row['password'] ?? '';
            $legacyMatch = strlen($storedHash) === 32 && ctype_xdigit($storedHash) && hash_equals($storedHash, md5($old));
            $validOld = password_verify($old, $storedHash) || $legacyMatch;

            if (!$row || !$validOld) {
                $passMsg = "Old password is incorrect.";
                $passType = "alert-danger";
            } else {
                $newHash = password_hash($new, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $newHash, $uid);
                if ($stmt->execute()) {
                    $passMsg = "Password updated successfully.";
                    $passType = "alert-success";
                } else {
                    $passMsg = "Could not update password. Please try again.";
                    $passType = "alert-danger";
                }
                $stmt->close();
            }
        }
    } elseif ($action === 'delete') {
        $confirmPassword = $_POST['confirm_password'] ?? '';
        if ($confirmPassword === '') {
            $deleteMsg = "Password is required to delete your account.";
            $deleteType = "alert-danger";
        } else {
            $res = $conn->query("SELECT password FROM users WHERE id = $uid");
            $row = $res ? $res->fetch_assoc() : null;
            $storedHash = $row['password'] ?? '';
            $legacyMatch = strlen($storedHash) === 32 && ctype_xdigit($storedHash) && hash_equals($storedHash, md5($confirmPassword));
            $validConfirm = password_verify($confirmPassword, $storedHash) || $legacyMatch;

            if (!$row || !$validConfirm) {
                $deleteMsg = "Password is incorrect.";
                $deleteType = "alert-danger";
            } else {
                $cvPath = $user['cv_path'] ?? '';
                if ($cvPath !== '' && strpos($cvPath, 'uploads/cv/') === 0) {
                    $fullPath = __DIR__ . '/' . $cvPath;
                    if (is_file($fullPath)) {
                        @unlink($fullPath);
                    }
                }

                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param("i", $uid);
                if ($stmt->execute()) {
                    session_unset();
                    session_destroy();
                    header("Location: index.php");
                    exit;
                } else {
                    $deleteMsg = "Could not delete account. Please try again.";
                    $deleteType = "alert-danger";
                }
                $stmt->close();
            }
        }
    }
}

$bodyClass = 'account-page';
require 'header.php';
?>
<h1 class="mb-3">Account</h1>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5">Recommended for You</h2>
        <?php if (count($recommendedJobs) === 0): ?>
            <p class="text-muted">No recommendations yet. Update your job preference to see more.</p>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($recommendedJobs as $job): ?>
                    <div class="col-12 col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h3 class="h6 mb-2"><?php echo htmlspecialchars($job['title'] ?? ''); ?></h3>
                                <p class="text-muted small mb-3">
                                    <?php echo htmlspecialchars($job['company'] ?? ''); ?> |
                                    <?php echo htmlspecialchars($job['location'] ?? ''); ?>
                                </p>
                                <a class="btn btn-outline-primary btn-sm" href="job-detail.php?id=<?php echo $job['id']; ?>">View Details</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5">Profile</h2>
        <?php if ($profileMsg): ?>
            <div class="alert <?php echo $profileType; ?>"><?php echo htmlspecialchars($profileMsg); ?></div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="profile">
            <div class="mb-3">
                <label class="form-label">Full Name*</label>
                <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Email*</label>
                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Phone (optional)</label>
                <input
                    type="tel"
                    class="form-control"
                    name="phone"
                    value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                    inputmode="numeric"
                    maxlength="10"
                    pattern="[0-9]{10}"
                    oninput="this.value=this.value.replace(/\D/g,'').slice(0,10);"
                    placeholder="98XXXXXXXX"
                >
                <div class="form-text">Must be exactly 10 digits.</div>
            </div>

            <div class="mb-3">
                <label class="form-label">Job Preference*</label>
                <select name="preferred_category" class="form-select" required>
                    <option value="">Prefer Job Category</option>
                    <?php foreach ($jobCategories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>"
                            <?php echo ($user['preferred_category'] === $cat) ? "selected" : ""; ?>>
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">CV (PDF only, max 5MB)</label>
                <input type="file" class="form-control" name="cv_file" accept=".pdf">
                <?php if (!empty($user['cv_path'])): ?>
                    <div class="form-text">
                        Current CV: <a class="link-primary text-decoration-none" href="<?php echo htmlspecialchars($user['cv_path']); ?>" target="_blank">View</a>
                    </div>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary">Save Profile</button>
        </form>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5">Change Password</h2>
        <?php if ($passMsg): ?>
            <div class="alert <?php echo $passType; ?>"><?php echo htmlspecialchars($passMsg); ?></div>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="action" value="password">
            <div class="mb-3">
                <label class="form-label">Old Password*</label>
                <input type="password" class="form-control" name="old_password" placeholder="Old Password" required>
            </div>

            <div class="mb-3">
                <label class="form-label">New Password*</label>
                <input type="password" class="form-control" name="new_password" placeholder="New Password" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Confirm Password*</label>
                <input type="password" class="form-control" name="confirm_password" placeholder="Confirm Password" required>
            </div>

            <button type="submit" class="btn btn-primary">Update Password</button>
        </form>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5">Delete Account</h2>
        <?php if ($deleteMsg): ?>
            <div class="alert <?php echo $deleteType; ?>"><?php echo htmlspecialchars($deleteMsg); ?></div>
        <?php endif; ?>
        <form method="post" onsubmit="return confirm('This will permanently delete your account. Continue?');">
            <input type="hidden" name="action" value="delete">
            <div class="mb-3">
                <label class="form-label">Confirm Password*</label>
                <input type="password" class="form-control" name="confirm_password" required>
            </div>
            <button type="submit" class="btn btn-danger">Delete Account</button>
        </form>
    </div>
</div>
<?php require 'footer.php'; ?>
