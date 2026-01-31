<?php
// admin/admin-applications.php
require '../db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit;
}

// Basic stats
$totalApps   = db_query_value("SELECT COUNT(*) FROM applications");
$pending     = db_query_value("SELECT COUNT(*) FROM applications WHERE status = 'pending'");
$shortlisted = db_query_value("SELECT COUNT(*) FROM applications WHERE status = 'shortlisted'");
$rejected    = db_query_value("SELECT COUNT(*) FROM applications WHERE status = 'rejected'");
$offered     = db_query_value("SELECT COUNT(*) FROM applications WHERE status = 'offered'");

// Fetch recent applications (limit 50 for performance)
$applications = db_query_all("
    SELECT a.id, a.status, a.applied_at, a.cover_letter,
           u.name AS user_name, u.email AS user_email,
           j.title AS job_title, j.company AS job_company,
           j.location AS job_location
    FROM applications a
    JOIN users u ON u.id = a.user_id
    JOIN jobs j ON j.id = a.job_id
    ORDER BY a.applied_at DESC
    LIMIT 50
");
?>

<?php require 'admin-header.php'; ?>

<h1 class="mb-4">Job Applications</h1>

<div class="row g-4 mb-5">
    <div class="col-md-3 col-sm-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body text-center">
                <h6 class="text-muted mb-1">Total Applications</h6>
                <h3 class="mb-0"><?= number_format($totalApps) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body text-center">
                <h6 class="text-muted mb-1">Pending</h6>
                <h3 class="mb-0 text-warning"><?= number_format($pending) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body text-center">
                <h6 class="text-muted mb-1">Shortlisted</h6>
                <h3 class="mb-0 text-primary"><?= number_format($shortlisted) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body text-center">
                <h6 class="text-muted mb-1">Rejected / Offered</h6>
                <h3 class="mb-0"><?= number_format($rejected + $offered) ?></h3>
                <small class="text-muted">(Rejected: <?= $rejected ?> • Offered: <?= $offered ?>)</small>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-light">
        <h5 class="mb-0">Recent Applications (Latest 50)</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Job Title</th>
                        <th>Applicant</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Applied</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($applications)): ?>
                    <tr><td colspan="7" class="text-center py-5 text-muted">No applications found.</td></tr>
                <?php else: ?>
                    <?php foreach ($applications as $app): ?>
                        <tr>
                            <td><?= $app['id'] ?></td>
                            <td><?= htmlspecialchars($app['job_title']) ?></td>
                            <td><?= htmlspecialchars($app['user_name']) ?></td>
                            <td><?= htmlspecialchars($app['user_email']) ?></td>
                            <td>
                                <?php
                                $status = strtolower($app['status'] ?? 'pending');
                                $badge = match($status) {
                                    'pending'     => 'bg-warning',
                                    'reviewed'    => 'bg-info',
                                    'shortlisted' => 'bg-primary',
                                    'offered'     => 'bg-success',
                                    'rejected'    => 'bg-danger',
                                    default       => 'bg-secondary'
                                };
                                ?>
                                <span class="badge <?= $badge ?>"><?= ucfirst($status) ?></span>
                            </td>
                            <td><?= date('Y-m-d H:i', strtotime($app['applied_at'])) ?></td>
                            <td>
                                <a href="application-details.php?id=<?= $app['id'] ?>" 
                                   class="btn btn-sm btn-outline-primary">
                                    View Details
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="alert alert-info mt-4 small">
    <i class="bi bi-info-circle me-2"></i>
    This view shows the most recent 50 applications. Use filters or pagination in future versions for full access.
</div>

<?php require '../footer.php'; ?>