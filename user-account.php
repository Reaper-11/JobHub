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

// Fetch current user details
$userRes = $conn->query("SELECT name, email FROM users WHERE id = $uid");
$user = $userRes ? $userRes->fetch_assoc() : ['name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($name === '' || $email === '') {
            $profileMsg = "Name and email are required.";
            $profileType = "alert-error";
        } else {
            $emailEsc = $conn->real_escape_string($email);
            $check = $conn->query("SELECT id FROM users WHERE email='$emailEsc' AND id <> $uid");
            if ($check && $check->num_rows > 0) {
                $profileMsg = "That email is already in use.";
                $profileType = "alert-error";
            } else {
                $stmt = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
                $stmt->bind_param("ssi", $name, $email, $uid);
                if ($stmt->execute()) {
                    $profileMsg = "Profile updated successfully.";
                    $profileType = "alert-success";
                    $_SESSION['user_name'] = $name;
                    $user['name'] = $name;
                    $user['email'] = $email;
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
            $oldHash = md5($old);
            $newHash = md5($new);

            $res = $conn->query("SELECT password FROM users WHERE id = $uid");
            $row = $res ? $res->fetch_assoc() : null;

            if (!$row || $row['password'] !== $oldHash) {
                $passMsg = "Old password is incorrect.";
                $passType = "alert-error";
            } else {
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
    }
}

require 'header.php';
?>
<h1>Account</h1>

<div class="form-card">
    <h2>Profile</h2>
    <?php if ($profileMsg): ?>
        <div class="alert <?php echo $profileType; ?>"><?php echo htmlspecialchars($profileMsg); ?></div>
    <?php endif; ?>
    <form method="post">
        <input type="hidden" name="action" value="profile">
        <label>Full Name*</label>
        <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>

        <label>Email*</label>
        <input type="text" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>

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
<?php require 'footer.php'; ?>
