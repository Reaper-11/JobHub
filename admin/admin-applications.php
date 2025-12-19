<?php
require '../db.php';
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit;
}
$msg = "";
$msgType = "alert-success";
$statusOptions = [
    'pending' => 'Pending',
    'approved' => 'Approved',
    'rejected' => 'Rejected',
    'interview' => 'Interview',
    'hold' => 'Hold'
];
$statusColumnExists = false;
$colCheck = $conn->query("SHOW COLUMNS FROM applications LIKE 'status'");
if ($colCheck && $colCheck->num_rows > 0) {
    $statusColumnExists = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appId = (int) ($_POST['app_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if (!$statusColumnExists) {
        $msg = "Status column is missing. Please run the database migration first.";
        $msgType = "alert-error";
    } elseif ($appId <= 0 || !isset($statusOptions[$status])) {
        $msg = "Invalid status update.";
        $msgType = "alert-error";
    } else {
        $stmt = $conn->prepare("UPDATE applications SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $appId);
        if ($stmt->execute()) {
            $msg = "Application status updated.";
            $msgType = "alert-success";
        } else {
            $msg = "Could not update status. Please try again or confirm the status column exists.";
            $msgType = "alert-error";
        }
        $stmt->close();
    }
}
$sql = "SELECT a.*, u.name, u.email, j.title
        FROM applications a
        JOIN users u ON u.id = a.user_id
        JOIN jobs j ON j.id = a.job_id
        ORDER BY a.applied_at DESC";
$res = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Applications - JobHub</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
<main class="container">
    <h1>Job Applications</h1>
    <p><a href="admin-dashboard.php">&laquo; Back to Dashboard</a></p>
    <?php if ($msg): ?>
        <div class="alert <?php echo $msgType; ?>"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>
    <table>
        <tr>
            <th>ID</th>
            <th>Job</th>
            <th>User</th>
            <th>Email</th>
            <th>Status</th>
            <th>Applied At</th>
            <th>Action</th>
        </tr>
        <?php while ($a = $res->fetch_assoc()): ?>
            <?php $currentStatus = $a['status'] ?? 'pending'; ?>
            <tr>
                <td><?php echo $a['id']; ?></td>
                <td><?php echo htmlspecialchars($a['title']); ?></td>
                <td><?php echo htmlspecialchars($a['name']); ?></td>
                <td><?php echo htmlspecialchars($a['email']); ?></td>
                <td><?php echo htmlspecialchars($statusOptions[$currentStatus] ?? ucfirst($currentStatus)); ?></td>
                <td><?php echo htmlspecialchars($a['applied_at']); ?></td>
                <td>
                    <form method="post" class="inline-form">
                        <input type="hidden" name="app_id" value="<?php echo $a['id']; ?>">
                        <select name="status">
                            <?php foreach ($statusOptions as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo $currentStatus === $value ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-small btn-secondary">Update</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>
</main>
</body>
</html>
