<?php
require '../db.php';
require_role('admin');
$allowed = ['jobs', 'users', 'applications', 'bookmarks', 'companies'];
$input = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$table = isset($input['table']) ? $input['table'] : '';
$id = isset($input['id']) ? (int) $input['id'] : 0;
$return = isset($input['return']) ? $input['return'] : 'admin-dashboard.php';

if (in_array($table, $allowed) && $id > 0) {
    if ($table === 'users') {
        $adminId = current_admin_id() ?? 0;
        $user = db_query_all("SELECT account_id FROM users WHERE id = ? LIMIT 1", "i", [$id])[0] ?? null;
        $conn->begin_transaction();

        $stmt = $conn->prepare("UPDATE users SET account_status = 'removed', is_active = 0, updated_at = NOW() WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $ok = $stmt->execute();
            $stmt->close();

            if ($ok && !empty($user['account_id'])) {
                $ok = jobhub_update_account_status($conn, (int) $user['account_id'], 'inactive');
            }

            if ($ok) {
                $conn->commit();
                log_activity($conn, $adminId, 'admin', 'user_removed', "Admin removed user account #{$id}", 'user', $id);
            } else {
                $conn->rollback();
            }
        }
    } else {
        $conn->query("DELETE FROM $table WHERE id=$id");
    }
}
header("Location: " . $return);
exit;
