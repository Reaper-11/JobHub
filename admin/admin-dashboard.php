<?php
require '../db.php';
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit;
}
$jobsCount = $conn->query("SELECT COUNT(*) c FROM jobs")->fetch_assoc()['c'];
$usersCount = $conn->query("SELECT COUNT(*) c FROM users")->fetch_assoc()['c'];
$appCount  = $conn->query("SELECT COUNT(*) c FROM applications")->fetch_assoc()['c'];
$companyCount = $conn->query("SELECT COUNT(*) c FROM companies")->fetch_assoc()['c'];
$pendingCompanies = $conn->query("SELECT COUNT(*) c FROM companies WHERE is_approved = 0")->fetch_assoc()['c'];
$approvedCompanies = $conn->query("SELECT COUNT(*) c FROM companies WHERE is_approved = 1")->fetch_assoc()['c'];
$rejectedCompanies = $conn->query("SELECT COUNT(*) c FROM companies WHERE is_approved = -1")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - JobHub</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../custom.css?v=<?php echo filemtime(__DIR__ . '/../custom.css'); ?>">
</head>
<body>
<main class="container py-4">
    <h1 class="mb-3">Admin Dashboard</h1>
    <div class="card shadow-sm mb-4">
        <div class="card-body d-flex justify-content-between align-items-center">
            <p class="mb-0">Welcome, <?php echo htmlspecialchars($_SESSION['admin_username']); ?></p>
            <a class="btn btn-outline-secondary btn-sm" href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Total Jobs</div>
                    <div class="h4 mb-0"><?php echo $jobsCount; ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Total Users</div>
                    <div class="h4 mb-0"><?php echo $usersCount; ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Total Applications</div>
                    <div class="h4 mb-0"><?php echo $appCount; ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Total Companies</div>
                    <div class="h4 mb-0"><?php echo $companyCount; ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5">Company Approval</h2>
            <ul class="list-group list-group-flush mb-3">
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    Pending Companies
                    <span class="badge text-bg-warning"><?php echo $pendingCompanies; ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    Approved Companies
                    <span class="badge text-bg-success"><?php echo $approvedCompanies; ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    Rejected Companies
                    <span class="badge text-bg-danger"><?php echo $rejectedCompanies; ?></span>
                </li>
            </ul>
            <a class="btn btn-outline-secondary btn-sm" href="admin-companies.php">Review Pending Companies</a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <h2 class="h5">Quick Actions</h2>
            <div class="row g-3">
                <div class="col-12 col-md-6 col-lg-3">
                    <a class="card h-100 text-decoration-none text-body" href="admin-jobs.php">
                        <div class="card-body">
                            <h3 class="h6 mb-1">View Jobs</h3>
                            <p class="text-muted small mb-0">View all job listings posted by approved companies.</p>
                        </div>
                    </a>
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                    <a class="card h-100 text-decoration-none text-body" href="admin-companies.php">
                        <div class="card-body">
                            <h3 class="h6 mb-1">Manage Companies</h3>
                            <p class="text-muted small mb-0">Approve or reject company accounts.</p>
                        </div>
                    </a>
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                    <a class="card h-100 text-decoration-none text-body" href="admin-users.php">
                        <div class="card-body">
                            <h3 class="h6 mb-1">View Users</h3>
                            <p class="text-muted small mb-0">View registered users and basic details.</p>
                        </div>
                    </a>
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                    <a class="card h-100 text-decoration-none text-body" href="admin-applications.php">
                        <div class="card-body">
                            <h3 class="h6 mb-1">View Applications</h3>
                            <p class="text-muted small mb-0">View job applications submitted by users.</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
