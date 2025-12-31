<?php
require '../db.php';
if (!isset($_SESSION['company_id'])) {
    header("Location: company-login.php");
    exit;
}

$cid = (int) $_SESSION['company_id'];
$profileMsg = "";
$profileType = "";
$passMsg = "";
$passType = "";
$deleteMsg = "";
$deleteType = "";

$companyRes = $conn->query("SELECT name, email FROM companies WHERE id = $cid");
$company = $companyRes ? $companyRes->fetch_assoc() : ['name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($name === '' || $email === '') {
            $profileMsg = "Company name and email are required.";
            $profileType = "alert-error";
        } else {
            $emailEsc = $conn->real_escape_string($email);
            $check = $conn->query("SELECT id FROM companies WHERE email='$emailEsc' AND id <> $cid");
            if ($check && $check->num_rows > 0) {
                $profileMsg = "That email is already in use.";
                $profileType = "alert-error";
            } else {
                $stmt = $conn->prepare("UPDATE companies SET name = ?, email = ? WHERE id = ?");
                $stmt->bind_param("ssi", $name, $email, $cid);
                if ($stmt->execute()) {
                    $profileMsg = "Company profile updated successfully.";
                    $profileType = "alert-success";
                    $_SESSION['company_name'] = $name;
                    $company['name'] = $name;
                    $company['email'] = $email;

                    $jobUpdate = $conn->prepare("UPDATE jobs SET company = ? WHERE company_id = ?");
                    $jobUpdate->bind_param("si", $name, $cid);
                    $jobUpdate->execute();
                    $jobUpdate->close();
                } else {
                    $profileMsg = "Could not update profile. Please try again.";
                    $profileType = "alert-error";
                }
                $stmt->close();
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
            $res = $conn->query("SELECT password FROM companies WHERE id = $cid");
            $row = $res ? $res->fetch_assoc() : null;
            $storedHash = $row['password'] ?? '';
            $legacyMatch = strlen($storedHash) === 32 && ctype_xdigit($storedHash) && hash_equals($storedHash, md5($old));
            $validOld = password_verify($old, $storedHash) || $legacyMatch;

            if (!$row || !$validOld) {
                $passMsg = "Old password is incorrect.";
                $passType = "alert-error";
            } else {
                $newHash = password_hash($new, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE companies SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $newHash, $cid);
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
            $res = $conn->query("SELECT password FROM companies WHERE id = $cid");
            $row = $res ? $res->fetch_assoc() : null;
            $storedHash = $row['password'] ?? '';
            $legacyMatch = strlen($storedHash) === 32 && ctype_xdigit($storedHash) && hash_equals($storedHash, md5($confirmPassword));
            $validConfirm = password_verify($confirmPassword, $storedHash) || $legacyMatch;

            if (!$row || !$validConfirm) {
                $deleteMsg = "Password is incorrect.";
                $deleteType = "alert-error";
            } else {
                $conn->query("DELETE FROM jobs WHERE company_id = $cid");

                $stmt = $conn->prepare("DELETE FROM companies WHERE id = ?");
                $stmt->bind_param("i", $cid);
                if ($stmt->execute()) {
                    session_unset();
                    session_destroy();
                    header("Location: ../index.php");
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

$basePath = '../';
$bodyClass = 'account-page';
require '../header.php';
?>
<h1>Company Account</h1>
<p><a href="company-dashboard.php">&laquo; Back to Dashboard</a></p>

<div class="form-card">
    <h2>Profile</h2>
    <?php if ($profileMsg): ?>
        <div class="alert <?php echo $profileType; ?>"><?php echo htmlspecialchars($profileMsg); ?></div>
    <?php endif; ?>
    <form method="post">
        <input type="hidden" name="action" value="profile">
        <label>Company Name*</label>
        <input type="text" name="name" value="<?php echo htmlspecialchars($company['name']); ?>" required>

        <label>Email*</label>
        <input type="text" name="email" value="<?php echo htmlspecialchars($company['email']); ?>" required>

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
    <form method="post" onsubmit="return confirm('This will permanently delete your company account and jobs. Continue?');">
        <input type="hidden" name="action" value="delete">
        <label>Confirm Password*</label>
        <input type="password" name="confirm_password" required>
        <button type="submit" class="btn btn-danger">Delete Company</button>
    </form>
</div>
<?php require '../footer.php'; ?>
