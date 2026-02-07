<?php
// company/company-account.php
require '../db.php';

if (!isset($_SESSION['company_id'])) {
    header("Location: company-login.php");
    exit;
}

$cid = (int)$_SESSION['company_id'];
$msg = $msg_type = '';
$pass_msg = $pass_type = '';
$delete_msg = $delete_type = '';

// Fetch current company data
$stmt = $conn->prepare("SELECT name, email, website, location, logo_path FROM companies WHERE id = ?");
$stmt->bind_param("i", $cid);
$stmt->execute();
$company = $stmt->get_result()->fetch_assoc() ?? [];
$stmt->close();

// Update profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'profile' && validate_csrf_token($_POST['csrf_token'] ?? '')) {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $location = trim($_POST['location'] ?? '');

    if (empty($name) || empty($email)) {
        $msg = "Name and email are required.";
        $msg_type = 'danger';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = "Invalid email format.";
        $msg_type = 'danger';
    } else {
        // Check email uniqueness (exclude self)
        $check = $conn->prepare("SELECT id FROM companies WHERE email = ? AND id != ?");
        $check->bind_param("si", $email, $cid);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $msg = "This email is already used by another company.";
            $msg_type = 'danger';
        } else {
            $stmt = $conn->prepare("UPDATE companies SET name = ?, email = ?, website = ?, location = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("ssssi", $name, $email, $website, $location, $cid);
            if ($stmt->execute()) {
                $msg = "Profile updated successfully.";
                $msg_type = 'success';
                $company['name'] = $name;
                $company['email'] = $email;
                $company['website'] = $website;
                $company['location'] = $location;
            } else {
                $msg = "Update failed. Please try again.";
                $msg_type = 'danger';
            }
            $stmt->close();
        }
        $check->close();
    }
}

// Change password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'password' && validate_csrf_token($_POST['csrf_token'] ?? '')) {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (empty($current) || empty($new) || empty($confirm)) {
        $pass_msg = "All password fields are required.";
        $pass_type = 'danger';
    } elseif ($new !== $confirm) {
        $pass_msg = "New passwords do not match.";
        $pass_type = 'danger';
    } elseif (strlen($new) < 8) {
        $pass_msg = "New password must be at least 8 characters.";
        $pass_type = 'danger';
    } else {
        $stmt = $conn->prepare("SELECT password FROM companies WHERE id = ?");
        $stmt->bind_param("i", $cid);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if ($row && password_verify($current, $row['password'])) {
            $newHash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE companies SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $newHash, $cid);
            if ($stmt->execute()) {
                $pass_msg = "Password changed successfully.";
                $pass_type = 'success';
            } else {
                $pass_msg = "Failed to update password.";
                $pass_type = 'danger';
            }
            $stmt->close();
        } else {
            $pass_msg = "Current password is incorrect.";
            $pass_type = 'danger';
        }
    }
}

// Delete account (very dangerous – confirm twice)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && validate_csrf_token($_POST['csrf_token'] ?? '')) {
    $confirm_pass = $_POST['confirm_password'] ?? '';

    $stmt = $conn->prepare("SELECT password FROM companies WHERE id = ?");
    $stmt->bind_param("i", $cid);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if ($row && password_verify($confirm_pass, $row['password'])) {
        // Delete related jobs first
        $conn->prepare("DELETE FROM jobs WHERE company_id = ?")->bind_param("i", $cid)->execute();
        // Delete company
        $stmt = $conn->prepare("DELETE FROM companies WHERE id = ?");
        $stmt->bind_param("i", $cid);
        if ($stmt->execute()) {
            session_destroy();
            header("Location: ../index.php?msg=company_deleted");
            exit;
        } else {
            $delete_msg = "Failed to delete account.";
            $delete_type = 'danger';
        }
        $stmt->close();
    } else {
        $delete_msg = "Incorrect password.";
        $delete_type = 'danger';
    }
}
?>

<?php require 'company-header.php'; ?>

<h1 class="mb-4">Company Account Settings</h1>

<div class="row g-4">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body d-flex flex-wrap align-items-center gap-3">
                <div>
                    <div class="text-muted small">Approval Status</div>
                    <div><?= $approvalBadge ?></div>
                </div>
                <div>
                    <div class="text-muted small">Account State</div>
                    <div><?= $stateBadge ?></div>
                </div>
                <?php if (!empty($restrictionReason)): ?>
                    <div>
                        <div class="text-muted small">Restriction Reason</div>
                        <div><?= htmlspecialchars($restrictionReason) ?></div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($restrictedAt)): ?>
                    <div>
                        <div class="text-muted small">Restricted At</div>
                        <div><?= htmlspecialchars($restrictedAt) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!-- Profile Update -->
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">Company Profile</h5>
            </div>
            <div class="card-body">
                <?php if ($msg): ?>
                    <div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
                <?php endif; ?>

                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="action" value="profile">

                    <div class="mb-3">
                        <label class="form-label">Company Name *</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($company['name'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($company['email'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Website</label>
                        <input type="url" name="website" class="form-control" value="<?= htmlspecialchars($company['website'] ?? '') ?>">
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Location</label>
                        <input type="text" name="location" class="form-control" value="<?= htmlspecialchars($company['location'] ?? '') ?>">
                    </div>

                    <button type="submit" class="btn btn-primary">Update Profile</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Password Change -->
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">Change Password</h5>
            </div>
            <div class="card-body">
                <?php if ($pass_msg): ?>
                    <div class="alert alert-<?= $pass_type ?>"><?= htmlspecialchars($pass_msg) ?></div>
                <?php endif; ?>

                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="action" value="password">

                    <div class="mb-3">
                        <label class="form-label">Current Password *</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">New Password *</label>
                        <input type="password" name="new_password" class="form-control" required minlength="8">
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Confirm New Password *</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>

                    <button type="submit" class="btn btn-primary">Change Password</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Account (last resort) -->
    <div class="col-12">
        <div class="card shadow-sm border-danger">
            <div class="card-header bg-danger-subtle text-danger">
                <h5 class="mb-0">Delete Company Account</h5>
            </div>
            <div class="card-body">
                <?php if ($delete_msg): ?>
                    <div class="alert alert-<?= $delete_type ?>"><?= htmlspecialchars($delete_msg) ?></div>
                <?php endif; ?>

                <p class="text-danger fw-bold">Warning: This action is permanent and cannot be undone.</p>
                <p>All your posted jobs and data will be deleted.</p>

                <form method="post" onsubmit="return confirm('Are you absolutely sure? This will delete your company account FOREVER.');">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="action" value="delete">

                    <div class="mb-3">
                        <label class="form-label">Confirm your password to proceed *</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>

                    <button type="submit" class="btn btn-danger">Delete My Company Account</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require '../footer.php'; ?>
