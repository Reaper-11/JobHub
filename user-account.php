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
            $profileType = "alert-error";
        } elseif (!in_array($preferred_category, $jobCategories, true)) {
            $profileMsg = "Invalid job category selected.";
            $profileType = "alert-error";
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
                $profileType = "alert-error";
            } else {
            $emailEsc = $conn->real_escape_string($email);
            $check = $conn->query("SELECT id FROM users WHERE email='$emailEsc' AND id <> $uid");
            if ($check && $check->num_rows > 0) {
                $profileMsg = "That email is already in use.";
                $profileType = "alert-error";
            } else {
                $phoneVal = $phone === '' ? null : $phone;
                $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, preferred_category = ?, cv_path = ? WHERE id = ?");
                $stmt->bind_param("sssssi", $name, $email, $phoneVal, $preferred_category, $newCvPath, $uid);
                if ($stmt->execute()) {
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
                    $profileType = "alert-error";
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
            $passType = "alert-error";
        } elseif ($new !== $confirm) {
            $passMsg = "New password and confirmation do not match.";
            $passType = "alert-error";
        } else {
            $res = $conn->query("SELECT password FROM users WHERE id = $uid");
            $row = $res ? $res->fetch_assoc() : null;
            $storedHash = $row['password'] ?? '';
            $legacyMatch = strlen($storedHash) === 32 && ctype_xdigit($storedHash) && hash_equals($storedHash, md5($old));
            $validOld = password_verify($old, $storedHash) || $legacyMatch;

            if (!$row || !$validOld) {
                $passMsg = "Old password is incorrect.";
                $passType = "alert-error";
            } else {
                $newHash = password_hash($new, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $newHash, $uid);
                if ($stmt->execute()) {
                    $passMsg = "Password updated successfully.";
                    $passType = "alert-success";
                } else {
                    $passMsg = "Could not update password. Please try again.";
                    $passType = "alert-error";
                }
                $stmt->close();
            }
        }
    } elseif ($action === 'delete') {
        $confirmPassword = $_POST['confirm_password'] ?? '';
        if ($confirmPassword === '') {
            $deleteMsg = "Password is required to delete your account.";
            $deleteType = "alert-error";
        } else {
            $res = $conn->query("SELECT password FROM users WHERE id = $uid");
            $row = $res ? $res->fetch_assoc() : null;
            $storedHash = $row['password'] ?? '';
            $legacyMatch = strlen($storedHash) === 32 && ctype_xdigit($storedHash) && hash_equals($storedHash, md5($confirmPassword));
            $validConfirm = password_verify($confirmPassword, $storedHash) || $legacyMatch;

            if (!$row || !$validConfirm) {
                $deleteMsg = "Password is incorrect.";
                $deleteType = "alert-error";
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
                    $deleteType = "alert-error";
                }
                $stmt->close();
            }
        }
    }
}

$bodyClass = 'account-page';
require 'header.php';
?>
<h1>Account</h1>

<div class="form-card">
    <h2>Profile</h2>
    <?php if ($profileMsg): ?>
        <div class="alert <?php echo $profileType; ?>"><?php echo htmlspecialchars($profileMsg); ?></div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="profile">
        <label>Full Name*</label>
        <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>

        <label>Email*</label>
        <input type="text" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>

        <label>Phone (optional)</label>
        <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="e.g. +977-9800000000">

        <label>Job Preference*</label>
        <select name="preferred_category" required>
            <option value="">Prefer Job Category</option>
            <?php foreach ($jobCategories as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat); ?>"
                    <?php echo ($user['preferred_category'] === $cat) ? "selected" : ""; ?>>
                    <?php echo htmlspecialchars($cat); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>CV (PDF only, max 5MB)</label>
        <input type="file" name="cv_file" accept=".pdf">
        <?php if (!empty($user['cv_path'])): ?>
            <p class="meta">
                Current CV: <a href="<?php echo htmlspecialchars($user['cv_path']); ?>" target="_blank">View</a>
            </p>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary">Save Profile</button>
    </form>
</div>

<div class="form-card">
    <h2>Change Password</h2>
    <?php if ($passMsg): ?>
        <div class="alert <?php echo $passType; ?>"><?php echo htmlspecialchars($passMsg); ?></div>
    <?php endif; ?>
    <form method="post">
        <input type="hidden" name="action" value="password">
        <label>Old Password*</label>
        <input type="password" name="old_password" placeholder="Old Password" required>

        <label>New Password*</label>
        <input type="password" name="new_password" placeholder="New Password" required>

        <label>Confirm Password*</label>
        <input type="password" name="confirm_password" placeholder="Confirm Password" required>

        <button type="submit" class="btn btn-primary">Update Password</button>
    </form>
</div>

<div class="form-card">
    <h2>Delete Account</h2>
    <?php if ($deleteMsg): ?>
        <div class="alert <?php echo $deleteType; ?>"><?php echo htmlspecialchars($deleteMsg); ?></div>
    <?php endif; ?>
    <form method="post" onsubmit="return confirm('This will permanently delete your account. Continue?');">
        <input type="hidden" name="action" value="delete">
        <label>Confirm Password*</label>
        <input type="password" name="confirm_password" required>
        <button type="submit" class="btn btn-danger">Delete Account</button>
    </form>
</div>
<?php require 'footer.php'; ?>
