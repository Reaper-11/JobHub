<?php
require 'db.php';
if (!isset($_SESSION['admin_id'])) {
    die("Access denied");
}
$allowed = ['jobs', 'users', 'applications', 'bookmarks'];
$table = isset($_GET['table']) ? $_GET['table'] : '';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$return = isset($_GET['return']) ? $_GET['return'] : 'admin-dashboard.php';

if (in_array($table, $allowed) && $id > 0) {
    $conn->query("DELETE FROM $table WHERE id=$id");
}
header("Location: " . $return);
exit;
