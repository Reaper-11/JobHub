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
    <link rel="stylesheet" href="../style.css">
    <style>
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
            margin-top: 12px;
        }
        .quick-action-card {
            display: block;
            padding: 18px;
            border-radius: 12px;
            border: 1px solid #e2e6ef;
            background: #fff;
            box-shadow: 0 6px 14px rgba(0, 0, 0, 0.08);
            color: inherit;
            text-decoration: none;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
        }
        .quick-action-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 22px rgba(0, 0, 0, 0.12);
            background-color: #f7f9fc;
        }
        .quick-action-title {
            margin: 0 0 6px;
            font-weight: 700;
        }
        .quick-action-text {
            margin: 0;
            color: #5a6575;
            font-size: 0.92rem;
        }
        .approval-list {
            list-style: none;
            padding: 0;
            margin: 12px 0 16px;
        }
        .approval-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #edf0f5;
        }
        .approval-item:last-child {
            border-bottom: none;
        }
        .approval-value {
            font-weight: 700;
            color: #2d5d8a;
        }
        @media (max-width: 980px) {
            .quick-actions-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (max-width: 640px) {
            .quick-actions-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<main class="container">
    <h1>Admin Dashboard</h1>
    <div class="card flex-between">
        <p>Welcome, <?php echo htmlspecialchars($_SESSION['admin_username']); ?></p>
        <a class="btn btn-secondary btn-small" href="../logout.php">Logout</a>
    </div>

    <div class="jobs-grid">
        <div class="card"><h3>Total Jobs</h3><p><?php echo $jobsCount; ?></p></div>
        <div class="card"><h3>Total Users</h3><p><?php echo $usersCount; ?></p></div>
        <div class="card"><h3>Total Applications</h3><p><?php echo $appCount; ?></p></div>
        <div class="card"><h3>Total Companies</h3><p><?php echo $companyCount; ?></p></div>
    </div>

    <div class="card">
        <h3>Company Approval</h3>
        <ul class="approval-list">
            <li class="approval-item">
                <span>Pending Companies</span>
                <span class="approval-value"><?php echo $pendingCompanies; ?></span>
            </li>
            <li class="approval-item">
                <span>Approved Companies</span>
                <span class="approval-value"><?php echo $approvedCompanies; ?></span>
            </li>
            <li class="approval-item">
                <span>Rejected Companies</span>
                <span class="approval-value"><?php echo $rejectedCompanies; ?></span>
            </li>
        </ul>
        <a class="btn btn-secondary btn-small" href="admin-companies.php">Review Pending Companies</a>
    </div>

    <div class="card">
        <h3>Quick Actions</h3>
        <div class="quick-actions-grid">
            <a class="quick-action-card" href="admin-jobs.php">
                <h4 class="quick-action-title">View Jobs</h4>
                <p class="quick-action-text">View all job listings posted by approved companies.</p>
            </a>
            <a class="quick-action-card" href="admin-companies.php">
                <h4 class="quick-action-title">Manage Companies</h4>
                <p class="quick-action-text">Approve or reject company accounts.</p>
            </a>
            <a class="quick-action-card" href="admin-users.php">
                <h4 class="quick-action-title">View Users</h4>
                <p class="quick-action-text">View registered users and basic details.</p>
            </a>
            <a class="quick-action-card" href="admin-applications.php">
                <h4 class="quick-action-title">View Applications</h4>
                <p class="quick-action-text">View job applications submitted by users.</p>
            </a>
        </div>
    </div>
</main>
</body>
</html>
