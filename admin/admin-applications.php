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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applications - JobHub</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../custom.css?v=<?php echo filemtime(__DIR__ . '/../custom.css'); ?>">
</head>
<body>
<main class="container py-4">
    <h1 class="mb-2">Job Applications</h1>
    <p><a class="link-primary text-decoration-none" href="admin-dashboard.php">&laquo; Back to Dashboard</a></p>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Total Applications</div>
                    <div class="h4 mb-0"><?php echo $totalApplications; ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Approved</div>
                    <div class="h4 text-success mb-0"><?php echo $approvedCount; ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Rejected</div>
                    <div class="h4 text-danger mb-0"><?php echo $rejectedCount; ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Pending</div>
                    <div class="h4 text-warning mb-0"><?php echo $pendingCount; ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="table-responsive">
    <table class="table table-striped table-hover align-middle">
        <thead>
            <tr>
                <th>ID</th>
                <th>Job</th>
                <th>User</th>
                <th>Email</th>
                <th>Status</th>
                <th>Applied At</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($a = $res->fetch_assoc()): ?>
            <tr>
                <td><?php echo $a['id']; ?></td>
                <td><?php echo htmlspecialchars($a['title']); ?></td>
                <td><?php echo htmlspecialchars($a['name']); ?></td>
                <td><?php echo htmlspecialchars($a['email']); ?></td>
                <td><?php echo htmlspecialchars(ucfirst($a['status'] ?? 'pending')); ?></td>
                <td><?php echo htmlspecialchars($a['applied_at']); ?></td>
                <td>
                    <a class="btn btn-outline-secondary btn-sm" href="application-details.php?id=<?php echo $a['id']; ?>">
                        View Application
                    </a>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
