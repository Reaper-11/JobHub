<?php
require 'db.php';
require 'includes/recommendation.php';
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
$jobCategories = require __DIR__ . '/includes/categories.php';
$legacyCategoryWarning = false;
$preferredValue = '';
$experienceLevels = [
    'Entry Level (0–1 years)',
    'Junior (1–3 years)',
    'Mid Level (3–5 years)',
    'Senior (5–8 years)',
    'Lead (8–10 years)',
    'Manager (10+ years)',
];
$hasExperienceColumn = false;

$checkExperience = $conn->query("SHOW COLUMNS FROM users LIKE 'experience_level'");
if ($checkExperience) {
    $hasExperienceColumn = $checkExperience->num_rows > 0;
    $checkExperience->close();
}

// Fetch current user details
$userSelect = "SELECT name, email, phone, preferred_category, cv_path, profile_image";
if ($hasExperienceColumn) {
    $userSelect .= ", experience_level";
}
$userSelect .= " FROM users WHERE id = $uid";
$userRes = $conn->query($userSelect);
$user = $userRes ? $userRes->fetch_assoc() : [
    'name' => '',
    'email' => '',
    'phone' => '',
    'preferred_category' => '',
    'cv_path' => '',
    'profile_image' => '',
    'experience_level' => '',
];

$preferenceProfile = function_exists('get_user_preferences') ? get_user_preferences($conn, $uid) : [];
if (!empty($preferenceProfile['preferred_category'])) {
    $user['preferred_category'] = $preferenceProfile['preferred_category'];
}
$preferredValue = $user['preferred_category'] ?? '';
if ($preferredValue !== '' && !in_array($preferredValue, $jobCategories, true)) {
    $legacyCategoryWarning = true;
    $preferredValue = 'Other';
}

$recommendedJobs = recommendJobs($conn, $uid, 10);
$debugUpload = isset($_GET['debug_upload']) || isset($_POST['debug_upload']);
$profileDebug = [];
$uploadError = '';
$cvMoved = null;
$dbPrepareOk = null;
$dbExecuteOk = null;
$dbStmtError = '';
$dbAffected = null;
$finalCvPath = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST) && empty($_FILES)) {
        $profileMsg = "Upload failed before processing. The request was likely too large for the server limits.";
        $profileType = "alert-danger";
        $profileMsg .= " (upload_max_filesize=" . ini_get('upload_max_filesize') . ", post_max_size=" . ini_get('post_max_size') . ")";
    }

    $action = $_POST['action'] ?? '';
    if ($action === '' && $profileMsg === '') {
        $profileMsg = "No action received. Please try saving your profile again.";
        $profileType = "alert-danger";
    }

    if ($action === 'profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $preferred_category = trim($_POST['preferred_category'] ?? '');
        $experience_level = trim($_POST['experience_level'] ?? '');
        if ($preferred_category !== '') {
            $preferredValue = $preferred_category;
            $legacyCategoryWarning = false;
        }

        $currentCv = $user['cv_path'] ?? '';
        $newCvPath = $currentCv;

        // Validation
        if ($name === '' || $email === '' || $preferred_category === '') {
            $profileMsg = "Name, email, and category are required.";
            $profileType = "alert-danger";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $profileMsg = "Please enter a valid email address.";
            $profileType = "alert-danger";
        } elseif ($phone !== '') {
            $digits = preg_replace('/\D+/', '', $phone);
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
        } elseif ($hasExperienceColumn && $experience_level !== '' && !in_array($experience_level, $experienceLevels, true)) {
            $profileMsg = "Invalid experience level selected.";
            $profileType = "alert-danger";
        }

        // Upload (only if validation passed)
        if ($profileMsg === '') {
            if (isset($_FILES['cv_file']) && $_FILES['cv_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES['cv_file']['error'] !== UPLOAD_ERR_OK) {
                    $uploadErrorMap = [
                        UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the server limit (upload_max_filesize).',
                        UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the form limit (MAX_FILE_SIZE).',
                        UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
                        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder on the server.',
                        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
                    ];
                    $errCode = (int) $_FILES['cv_file']['error'];
                    $errMsg = $uploadErrorMap[$errCode] ?? 'Unknown upload error.';
                    $uploadError = "Could not upload CV. Error code {$errCode}: {$errMsg}";
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
                                $cvMoved = true;
                            } else {
                                $uploadError = "Could not save CV file.";
                                $cvMoved = false;
                            }
                        }
                    }
                }
            }
        }

        if ($uploadError !== '') {
            $profileMsg = $uploadError;
            $profileType = "alert-danger";
        }

        // DB update (only if validation/upload passed)
        if ($profileMsg === '') {
            $emailEsc = $conn->real_escape_string($email);
            $check = $conn->query("SELECT id FROM users WHERE email='$emailEsc' AND id <> $uid");
            if ($check && $check->num_rows > 0) {
                $profileMsg = "That email is already in use.";
                $profileType = "alert-danger";
            } else {
                $phoneVal = $phone === '' ? null : $phone;
                if ($hasExperienceColumn) {
                    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, preferred_category = ?, experience_level = ?, cv_path = ? WHERE id = ?");
                } else {
                    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, preferred_category = ?, cv_path = ? WHERE id = ?");
                }
        $dbPrepareOk = $stmt ? true : false;
        if (!$stmt) {
            $dbStmtError = $conn->error ?? '';
            $profileMsg = "Could not update profile. Please try again.";
            $profileType = "alert-danger";
        } else {
            if ($hasExperienceColumn) {
                $stmt->bind_param("ssssssi", $name, $email, $phoneVal, $preferred_category, $experience_level, $newCvPath, $uid);
            } else {
                $stmt->bind_param("sssssi", $name, $email, $phoneVal, $preferred_category, $newCvPath, $uid);
            }
            $dbExecuteOk = $stmt->execute();
            $dbAffected = $stmt->affected_rows;
            $finalCvPath = $newCvPath;
            if ($dbExecuteOk) {
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
                        if ($hasExperienceColumn) {
                            $user['experience_level'] = $experience_level;
                        }
                        $user['cv_path'] = $newCvPath;
                    } else {
                        $profileMsg = "Could not update profile. Please try again.";
                        $profileType = "alert-danger";
                    }
                    $stmt->close();
                }
            }
        }

        if ($profileMsg === '') {
            $profileMsg = "Profile save did not complete. Check the debug panel for details.";
            $profileType = "alert-danger";
        }

        if ($debugUpload) {
            $profileDebug = [
                'action' => $action,
                'name' => $name,
                'email' => $email,
                'preferred_category' => $preferred_category,
                'experience_level' => $experience_level,
                'upload_error' => $uploadError,
                'profile_msg' => $profileMsg,
                'profile_type' => $profileType,
                'db_error' => $conn->error ?? '',
                'cv_moved' => $cvMoved === null ? 'n/a' : ($cvMoved ? 'yes' : 'no'),
                'db_prepare_ok' => $dbPrepareOk === null ? 'n/a' : ($dbPrepareOk ? 'yes' : 'no'),
                'db_execute_ok' => $dbExecuteOk === null ? 'n/a' : ($dbExecuteOk ? 'yes' : 'no'),
                'db_affected_rows' => $dbAffected === null ? 'n/a' : (string) $dbAffected,
                'final_cv_path' => $finalCvPath,
                'user_cv_path' => $user['cv_path'] ?? '',
                'conn_errno' => (string) ($conn->errno ?? ''),
            ];
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

$bodyClass = 'account-page user-ui';
require 'header.php';
?>
<h1 class="mb-3">Account</h1>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5">Profile</h2>
        <?php if ($profileMsg): ?>
            <div class="alert <?php echo $profileType; ?>"><?php echo htmlspecialchars($profileMsg); ?></div>
        <?php elseif ($legacyCategoryWarning): ?>
            <div class="alert alert-warning">Your previous job category is no longer available. Please confirm a new category.</div>
        <?php endif; ?>
        <?php if ($debugUpload): ?>
            <?php
                $uploadDir = __DIR__ . '/uploads/cv';
                $tmpDir = ini_get('upload_tmp_dir');
                $tmpDir = $tmpDir !== '' ? $tmpDir : sys_get_temp_dir();
                $fileInfo = $_FILES['cv_file'] ?? null;
                $fileError = $fileInfo['error'] ?? null;
            ?>
            <div class="alert alert-secondary small">
                <div>Debug upload: enabled</div>
                <div>request method: <?php echo htmlspecialchars($_SERVER['REQUEST_METHOD']); ?></div>
                <div>file_uploads: <?php echo ini_get('file_uploads'); ?></div>
                <div>upload_max_filesize: <?php echo ini_get('upload_max_filesize'); ?></div>
                <div>post_max_size: <?php echo ini_get('post_max_size'); ?></div>
                <div>upload_tmp_dir: <?php echo htmlspecialchars($tmpDir); ?></div>
                <div>cv upload dir: <?php echo htmlspecialchars($uploadDir); ?></div>
                <div>cv dir exists: <?php echo is_dir($uploadDir) ? 'yes' : 'no'; ?></div>
                <div>cv dir writable: <?php echo is_writable($uploadDir) ? 'yes' : 'no'; ?></div>
                <div>cv_file present: <?php echo $fileInfo ? 'yes' : 'no'; ?></div>
                <?php if ($fileInfo): ?>
                    <div>cv_file name: <?php echo htmlspecialchars($fileInfo['name'] ?? ''); ?></div>
                    <div>cv_file size: <?php echo (int) ($fileInfo['size'] ?? 0); ?></div>
                    <div>cv_file error: <?php echo $fileError === null ? 'n/a' : (int) $fileError; ?></div>
                <?php endif; ?>
                <?php if (!empty($profileDebug)): ?>
                    <div>action: <?php echo htmlspecialchars($profileDebug['action'] ?? ''); ?></div>
                    <div>name: <?php echo htmlspecialchars($profileDebug['name'] ?? ''); ?></div>
                    <div>email: <?php echo htmlspecialchars($profileDebug['email'] ?? ''); ?></div>
                    <div>preferred_category: <?php echo htmlspecialchars($profileDebug['preferred_category'] ?? ''); ?></div>
                    <div>experience_level: <?php echo htmlspecialchars($profileDebug['experience_level'] ?? ''); ?></div>
                    <div>upload_error: <?php echo htmlspecialchars($profileDebug['upload_error'] ?? ''); ?></div>
                    <div>profile_msg: <?php echo htmlspecialchars($profileDebug['profile_msg'] ?? ''); ?></div>
                    <div>profile_type: <?php echo htmlspecialchars($profileDebug['profile_type'] ?? ''); ?></div>
                    <div>db_error: <?php echo htmlspecialchars($profileDebug['db_error'] ?? ''); ?></div>
                    <div>conn_errno: <?php echo htmlspecialchars($profileDebug['conn_errno'] ?? ''); ?></div>
                    <div>cv_moved: <?php echo htmlspecialchars($profileDebug['cv_moved'] ?? ''); ?></div>
                    <div>db_prepare_ok: <?php echo htmlspecialchars($profileDebug['db_prepare_ok'] ?? ''); ?></div>
                    <div>db_execute_ok: <?php echo htmlspecialchars($profileDebug['db_execute_ok'] ?? ''); ?></div>
                    <div>db_affected_rows: <?php echo htmlspecialchars($profileDebug['db_affected_rows'] ?? ''); ?></div>
                    <div>final_cv_path: <?php echo htmlspecialchars($profileDebug['final_cv_path'] ?? ''); ?></div>
                    <div>user_cv_path: <?php echo htmlspecialchars($profileDebug['user_cv_path'] ?? ''); ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data" action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
            <input type="hidden" name="action" value="profile">
            <?php if ($debugUpload): ?>
                <input type="hidden" name="debug_upload" value="1">
            <?php endif; ?>
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
                            <?php echo ($preferredValue === $cat) ? "selected" : ""; ?>>
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($hasExperienceColumn): ?>
                <div class="mb-3">
                    <label class="form-label">Experience Level (optional)</label>
                    <select name="experience_level" class="form-select">
                        <option value="">Select experience level</option>
                        <?php foreach ($experienceLevels as $level): ?>
                            <option value="<?php echo htmlspecialchars($level); ?>"
                                <?php echo (($user['experience_level'] ?? '') === $level) ? "selected" : ""; ?>>
                                <?php echo htmlspecialchars($level); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

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
