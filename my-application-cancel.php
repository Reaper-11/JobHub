<?php
require 'db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: my-applications.php");
    exit;
}
$uid = (int) $_SESSION['user_id'];
$appId = (int) ($_POST['app_id'] ?? 0);
if ($appId <= 0) {
    header("Location: my-applications.php");
    exit;
}

$stmt = $conn->prepare("DELETE FROM applications WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $appId, $uid);
$stmt->execute();
$stmt->close();

header("Location: my-applications.php");
exit;
