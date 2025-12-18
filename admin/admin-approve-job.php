<?php
require 'db.php';
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit;
}
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$conn->query("UPDATE jobs SET is_approved=1 WHERE id=$id");
header("Location: admin-jobs.php");
exit;
