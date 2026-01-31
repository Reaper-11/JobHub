<?php
// admin/admin-dashboard.php
require '../db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit;
}

$stats = [
    'jobs'       => db_query_value("SELECT COUNT(*) FROM jobs"),
    'users'      => db_query_value("SELECT COUNT(*) FROM users"),
    'applications' => db_query_value("SELECT COUNT(*) FROM applications"),
    'companies'  => db_query_value("SELECT COUNT(*) FROM companies"),
    'pending'    => db_query_value("SELECT COUNT(*) FROM companies WHERE is_approved = 0"),
    'approved'   => db_query_value("SELECT COUNT(*) FROM companies WHERE is_approved = 1"),
    'rejected'   => db_query_value("SELECT COUNT(*) FROM companies WHERE is_approved = -1"),
];
?>

<?php require 'admin-header.php'; ?>

<h1 class="mb-4">Dashboard</h1>

<div class="row g-4">
    <div class="col-md-3 col-sm-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body text-center">
                <h6 class="text-muted mb-1">Total Jobs</h6>
                <h3 class="mb-0"><?= number_format($stats['jobs']) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body text-center">
                <h6 class="text-muted mb-1">Registered Users</h6>
                <h3 class="mb-0"><?= number_format($stats['users']) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body text-center">
                <h6 class="text-muted mb-1">Applications</h6>
                <h3 class="mb-0"><?= number_format($stats['applications']) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body text-center">
                <h6 class="text-muted mb-1">Companies</h6>
                <h3 class="mb-0"><?= number_format($stats['companies']) ?></h3>
                <div class="small text-muted mt-1">
                    Pending: <?= $stats['pending'] ?> • Approved: <?= $stats['approved'] ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mt-4">
    <div class="col-md-4">
        <a href="admin-jobs.php" class="text-decoration-none">
            <div class="card shadow-sm h-100 border-0 hover-lift">
                <div class="card-body">
                    <h5>Manage Jobs</h5>
                    <p class="text-muted small">Review, edit and moderate job listings</p>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="admin-companies.php" class="text-decoration-none">
            <div class="card shadow-sm h-100 border-0 hover-lift">
                <div class="card-body">
                    <h5>Manage Companies</h5>
                    <p class="text-muted small">Approve / reject company registrations</p>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="admin-users.php" class="text-decoration-none">
            <div class="card shadow-sm h-100 border-0 hover-lift">
                <div class="card-body">
                    <h5>Manage Users</h5>
                    <p class="text-muted small">View and manage job seekers</p>
                </div>
            </div>
        </a>
    </div>
</div>

<?php require '../footer.php'; ?>