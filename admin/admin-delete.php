<?php
require '../db.php';
require '../includes/flash.php';
if (!isset($_SESSION['admin_id'])) {
    die("Access denied");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: admin-dashboard.php");
    exit;
}

$allowed = ['jobs', 'users', 'applications', 'bookmarks', 'companies'];
$table = isset($_POST['table']) ? $_POST['table'] : '';
$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$return = isset($_POST['return']) ? $_POST['return'] : 'admin-dashboard.php';
$setJobFlash = false;

if (in_array($table, $allowed, true) && $id > 0) {
    if ($table === 'users') {
        $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
        if ($reason !== '') {
            $adminId = (int) $_SESSION['admin_id'];
            $stmt = $conn->prepare(
                "INSERT INTO user_deletion_reasons (user_id, admin_id, reason) VALUES (?, ?, ?)"
            );
            $stmt->bind_param("iis", $id, $adminId, $reason);
            $stmt->execute();
            $stmt->close();
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
        }
    } elseif ($table === 'jobs') {
        $stmt = $conn->prepare("DELETE FROM jobs WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            set_flash('jobs', 'Job deleted successfully.', 'alert-success');
            $setJobFlash = true;
        } else {
            set_flash('jobs', 'Unable to delete job. Please try again.', 'alert-error');
            $setJobFlash = true;
        }
        $stmt->close();
    } else {
        $stmt = $conn->prepare("DELETE FROM $table WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }
}

if ($setJobFlash) {
    $separator = strpos($return, '?') === false ? '?' : '&';
    $return .= $separator . 'deleted=1';
}

header("Location: " . $return);
exit;
