<?php
require '../db.php';
if (!isset($_SESSION['admin_id'])) {
    die("Access denied");
}
$allowed = ['jobs', 'users', 'applications', 'bookmarks', 'companies'];
$input = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$table = isset($input['table']) ? $input['table'] : '';
$id = isset($input['id']) ? (int) $input['id'] : 0;
$return = isset($input['return']) ? $input['return'] : 'admin-dashboard.php';

if (in_array($table, $allowed) && $id > 0) {
    if ($table === 'users') {
        $adminId = (int) $_SESSION['admin_id'];
        $stmt = $conn->prepare("UPDATE users SET account_status = 'removed', is_active = 0, updated_at = NOW() WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
            log_activity($conn, $adminId, 'admin', 'user_removed', "Admin removed user account #{$id}", 'user', $id);
        }
    } else {
        $conn->query("DELETE FROM $table WHERE id=$id");
    }
}
header("Location: " . $return);
exit;
