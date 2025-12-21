<?php
require '../db.php';
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit;
}

$msg = "";
$msgType = "alert-success";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['company_id'], $_POST['action'])) {
    $companyId = (int) $_POST['company_id'];
    $action = $_POST['action'];
    if ($companyId > 0 && ($action === 'approve' || $action === 'unapprove')) {
        $isApproved = $action === 'approve' ? 1 : 0;
        $stmt = $conn->prepare("UPDATE companies SET is_approved=? WHERE id=?");
        $stmt->bind_param("ii", $isApproved, $companyId);
        if ($stmt->execute()) {
            $msg = $isApproved ? "Company approved." : "Company set to pending.";
        } else {
            $msg = "Error updating company status.";
            $msgType = "alert-error";
        }
    }
}

$companies = $conn->query("SELECT * FROM companies ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Companies - JobHub</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
<main class="container">
    <h1>Manage Companies</h1>
    <p><a href="admin-dashboard.php">&laquo; Back to Dashboard</a></p>

    <?php if ($msg): ?><div class="alert <?php echo $msgType; ?>"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

    <table>
        <tr>
            <th>ID</th>
            <th>Company</th>
            <th>Email</th>
            <th>Website</th>
            <th>Location</th>
            <th>Status</th>
            <th>Registered At</th>
            <th>Actions</th>
        </tr>
        <?php while ($c = $companies->fetch_assoc()): ?>
            <tr>
                <td><?php echo $c['id']; ?></td>
                <td><?php echo htmlspecialchars($c['name']); ?></td>
                <td><?php echo htmlspecialchars($c['email']); ?></td>
                <td><?php echo htmlspecialchars($c['website']); ?></td>
                <td><?php echo htmlspecialchars($c['location']); ?></td>
                <td><?php echo $c['is_approved'] ? 'Approved' : 'Pending'; ?></td>
                <td><?php echo htmlspecialchars($c['created_at']); ?></td>
                <td>
                    <?php if ((int) $c['is_approved'] === 1): ?>
                        <form method="post" class="inline-form">
                            <input type="hidden" name="company_id" value="<?php echo $c['id']; ?>">
                            <input type="hidden" name="action" value="unapprove">
                            <button type="submit" class="btn btn-small btn-secondary">Set Pending</button>
                        </form>
                    <?php else: ?>
                        <form method="post" class="inline-form">
                            <input type="hidden" name="company_id" value="<?php echo $c['id']; ?>">
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="btn btn-small">Approve</button>
                        </form>
                    <?php endif; ?>
                    <a class="btn btn-danger btn-small"
                       href="admin-delete.php?table=companies&id=<?php echo $c['id']; ?>&return=admin-companies.php"
                       onclick="return confirm('Delete this company?')">Delete</a>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>
</main>
</body>
</html>
