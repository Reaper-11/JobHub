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
        $reason = isset($input['reason']) ? trim($input['reason']) : '';
        if ($reason !== '') {
            $adminId = (int) $_SESSION['admin_id'];
            $stmt = $conn->prepare(
                "INSERT INTO user_deletion_reasons (user_id, admin_id, reason) VALUES (?, ?, ?)"
            );
            $stmt->bind_param("iis", $id, $adminId, $reason);
            $stmt->execute();
            $stmt->close();
            $conn->query("DELETE FROM users WHERE id=$id");
        }
    } else {
        $conn->query("DELETE FROM $table WHERE id=$id");
    }
}
header("Location: " . $return);
exit;
