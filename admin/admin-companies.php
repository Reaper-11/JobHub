<?php
require '../db.php';
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit;
}

$statusFilter = strtolower(trim($_GET['status'] ?? 'pending'));
if (!in_array($statusFilter, ['pending', 'approved', 'rejected', 'all'], true)) {
    $statusFilter = 'pending';
}

$msg = "";
$msgType = "alert-success";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['company_id'], $_POST['action'])) {
    $companyId = (int) $_POST['company_id'];
    $action = $_POST['action'];
    if ($companyId > 0 && in_array($action, ['approve', 'unapprove', 'reject'], true)) {
        if ($action === 'reject') {
            $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
            if ($reason === '') {
                $msg = "Rejection reason is required.";
                $msgType = "alert-error";
            } else {
                $isApproved = -1;
                $stmt = $conn->prepare("UPDATE companies SET is_approved=?, rejection_reason=? WHERE id=?");
                $stmt->bind_param("isi", $isApproved, $reason, $companyId);
            }
        } elseif ($action === 'approve') {
            $isApproved = 1;
            $stmt = $conn->prepare("UPDATE companies SET is_approved=?, rejection_reason=NULL WHERE id=?");
            $stmt->bind_param("ii", $isApproved, $companyId);
        } else {
            $isApproved = 0;
            $stmt = $conn->prepare("UPDATE companies SET is_approved=?, rejection_reason=NULL WHERE id=?");
            $stmt->bind_param("ii", $isApproved, $companyId);
        }
        if (!empty($stmt)) {
            if ($stmt->execute()) {
                if ($isApproved === 1) {
                    $msg = "Company approved.";
                } elseif ($isApproved === -1) {
                    $msg = "Company rejected.";
                } else {
                    $msg = "Company set to pending.";
                }
            } else {
                $msg = "Error updating company status.";
                $msgType = "alert-error";
            }
        }
    }
}

$companiesSql = "SELECT * FROM companies";
if ($statusFilter !== 'all') {
    $statusMap = [
        'pending' => 0,
        'approved' => 1,
        'rejected' => -1
    ];
    $statusValue = $statusMap[$statusFilter] ?? 0;
    $companiesSql .= " WHERE is_approved = " . (int) $statusValue;
}
$companiesSql .= " ORDER BY created_at DESC";
$companies = $conn->query($companiesSql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Companies - JobHub</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .status-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 12px 0 18px;
        }
        .status-tab {
            display: inline-flex;
            align-items: center;
            padding: 8px 14px;
            border-radius: 999px;
            border: 1px solid #dfe3eb;
            background: #f7f8fb;
            color: #2c2f36;
            text-decoration: none;
            font-weight: 600;
        }
        .status-tab.active {
            background: #e0e7f1;
            border-color: #c7d2e3;
        }
    </style>
</head>
<body>
<main class="container">
    <h1>Manage Companies</h1>
    <p><a href="admin-dashboard.php">&laquo; Back to Dashboard</a></p>

    <?php if ($msg): ?><div class="alert <?php echo $msgType; ?>"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

    <div class="status-tabs">
        <a class="status-tab <?php echo $statusFilter === 'pending' ? 'active' : ''; ?>" href="admin-companies.php?status=pending">Pending</a>
        <a class="status-tab <?php echo $statusFilter === 'approved' ? 'active' : ''; ?>" href="admin-companies.php?status=approved">Approved</a>
        <a class="status-tab <?php echo $statusFilter === 'rejected' ? 'active' : ''; ?>" href="admin-companies.php?status=rejected">Rejected</a>
        <a class="status-tab <?php echo $statusFilter === 'all' ? 'active' : ''; ?>" href="admin-companies.php?status=all">All</a>
    </div>

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
                <?php
                $statusValue = (int) $c['is_approved'];
                if ($statusValue === 1) {
                    $statusLabel = 'Approved';
                } elseif ($statusValue === -1) {
                    $statusLabel = 'Rejected';
                } else {
                    $statusLabel = 'Pending';
                }
                ?>
                <td>
                    <?php echo $statusLabel; ?>
                    <?php if ($statusValue === -1 && !empty($c['rejection_reason'])): ?>
                        (Reason: <?php echo htmlspecialchars($c['rejection_reason']); ?>)
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($c['created_at']); ?></td>
                <td>
                    <?php if ($statusValue === 1): ?>
                        <form method="post" class="inline-form">
                            <input type="hidden" name="company_id" value="<?php echo $c['id']; ?>">
                            <input type="hidden" name="action" value="unapprove">
                            <button type="submit" class="btn btn-small btn-secondary">Set Pending</button>
                        </form>
                    <?php elseif ($statusValue === -1): ?>
                        <form method="post" class="inline-form">
                            <input type="hidden" name="company_id" value="<?php echo $c['id']; ?>">
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="btn btn-small">Approve</button>
                        </form>
                    <?php else: ?>
                        <form method="post" class="inline-form">
                            <input type="hidden" name="company_id" value="<?php echo $c['id']; ?>">
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="btn btn-small">Approve</button>
                        </form>
                        <form method="post" class="inline-form reject-form">
                            <input type="hidden" name="company_id" value="<?php echo $c['id']; ?>">
                            <input type="hidden" name="action" value="reject">
                            <input type="text" name="reason" class="reason-input" style="display:none;">
                            <button type="submit" class="btn btn-small btn-danger">Reject</button>
                        </form>
                    <?php endif; ?>
                    <form method="get" action="admin-delete.php" class="inline-form delete-form">
                        <input type="hidden" name="table" value="companies">
                        <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                        <input type="hidden" name="return" value="admin-companies.php">
                        <input type="text" name="reason" class="reason-input" style="display:none;">
                        <button type="submit" class="btn btn-danger btn-small">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>
</main>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.reject-form').forEach(function (form) {
            var input = form.querySelector('.reason-input');
            form.addEventListener('submit', function (e) {
                if (!input) return;
                if (input.style.display === 'none') {
                    e.preventDefault();
                    input.style.display = 'inline-block';
                    input.placeholder = 'Rejection reason (required)';
                    input.required = true;
                    input.focus();
                }
            });
        });

        document.querySelectorAll('.delete-form').forEach(function (form) {
            var input = form.querySelector('.reason-input');
            form.addEventListener('submit', function (e) {
                if (!input) return;
                if (input.style.display === 'none') {
                    e.preventDefault();
                    input.style.display = 'inline-block';
                    input.placeholder = 'Delete reason (optional)';
                    input.required = false;
                    input.focus();
                    return;
                }
                if (!confirm('Delete this company?')) {
                    e.preventDefault();
                }
            });
        });
    });
</script>
</body>
</html>
