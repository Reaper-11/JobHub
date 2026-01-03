<?php
require '../db.php';
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit;
}
$totalApplications = $conn->query("SELECT COUNT(*) c FROM applications")->fetch_assoc()['c'];
$approvedCount = $conn->query("SELECT COUNT(*) c FROM applications WHERE status = 'approved'")->fetch_assoc()['c'];
$rejectedCount = $conn->query("SELECT COUNT(*) c FROM applications WHERE status = 'rejected'")->fetch_assoc()['c'];
$pendingCount = $conn->query("SELECT COUNT(*) c FROM applications WHERE status = 'pending'")->fetch_assoc()['c'];
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
    <style>
        .application-chips {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin: 12px 0 18px;
        }
        .application-chip {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e2e6ef;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.06);
            padding: 12px 16px;
        }
        .chip-label {
            margin: 0 0 6px;
            color: #5a6575;
            font-size: 0.9rem;
        }
        .chip-value {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 700;
        }
        .chip-approved .chip-value {
            color: #1f7a3d;
        }
        .chip-rejected .chip-value {
            color: #b23b3b;
        }
        .chip-pending .chip-value {
            color: #b26a00;
        }
        @media (max-width: 900px) {
            .application-chips {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (max-width: 600px) {
            .application-chips {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<main class="container">
    <h1>Job Applications</h1>
    <p><a href="admin-dashboard.php">&laquo; Back to Dashboard</a></p>

    <div class="application-chips">
        <div class="application-chip">
            <p class="chip-label">Total Applications</p>
            <p class="chip-value"><?php echo $totalApplications; ?></p>
        </div>
        <div class="application-chip chip-approved">
            <p class="chip-label">Approved</p>
            <p class="chip-value"><?php echo $approvedCount; ?></p>
        </div>
        <div class="application-chip chip-rejected">
            <p class="chip-label">Rejected</p>
            <p class="chip-value"><?php echo $rejectedCount; ?></p>
        </div>
        <div class="application-chip chip-pending">
            <p class="chip-label">Pending</p>
            <p class="chip-value"><?php echo $pendingCount; ?></p>
        </div>
    </div>

    <table>
        <tr>
            <th>ID</th>
            <th>Job</th>
            <th>User</th>
            <th>Email</th>
            <th>Status</th>
            <th>Applied At</th>
            <th>Details</th>
        </tr>
        <?php while ($a = $res->fetch_assoc()): ?>
            <tr>
                <td><?php echo $a['id']; ?></td>
                <td><?php echo htmlspecialchars($a['title']); ?></td>
                <td><?php echo htmlspecialchars($a['name']); ?></td>
                <td><?php echo htmlspecialchars($a['email']); ?></td>
                <td><?php echo htmlspecialchars(ucfirst($a['status'] ?? 'pending')); ?></td>
                <td><?php echo htmlspecialchars($a['applied_at']); ?></td>
                <td>
                    <a class="btn btn-secondary btn-small" href="application-details.php?id=<?php echo $a['id']; ?>">
                        View Application
                    </a>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>
</main>
</body>
</html>
