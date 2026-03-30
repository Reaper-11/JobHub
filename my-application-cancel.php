<?php
require 'db.php';
require_role('jobseeker');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: my-applications.php");
    exit;
}
$uid = current_user_id() ?? 0;
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
