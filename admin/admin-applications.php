<?php
// admin/admin-applications.php
require '../db.php';
require_role('admin');

// Basic stats
$totalApps   = db_query_value("SELECT COUNT(*) FROM applications");
$pending     = db_query_value("SELECT COUNT(*) FROM applications WHERE status = 'pending'");
$shortlisted = db_query_value("SELECT COUNT(*) FROM applications WHERE status = 'shortlisted'");
$interview   = db_query_value("SELECT COUNT(*) FROM applications WHERE status = 'interview'");
$rejected    = db_query_value("SELECT COUNT(*) FROM applications WHERE status = 'rejected'");
$approved    = db_query_value("SELECT COUNT(*) FROM applications WHERE status = 'approved'");

// Fetch recent applications (limit 50 for performance)
$applications = db_query_all("
    SELECT a.id, a.status, a.applied_at, a.cover_letter, a.cv_path,
           u.name AS user_name, u.email AS user_email, u.cv_path AS user_cv_path,
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
                <h6 class="text-muted mb-1">Shortlisted / Interview</h6>
                <h3 class="mb-0 text-primary"><?= number_format($shortlisted + $interview) ?></h3>
                <small class="text-muted">(Shortlisted: <?= $shortlisted ?> • Interview: <?= $interview ?>)</small>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body text-center">
                <h6 class="text-muted mb-1">Rejected / Approved</h6>
                <h3 class="mb-0"><?= number_format($rejected + $approved) ?></h3>
                <small class="text-muted">(Rejected: <?= $rejected ?> • Approved: <?= $approved ?>)</small>
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
                        <th>CV</th>
                        <th>Status</th>
                        <th>Applied</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($applications)): ?>
                    <tr><td colspan="8" class="text-center py-5 text-muted">No applications found.</td></tr>
                <?php else: ?>
                    <?php foreach ($applications as $app): ?>
                        <tr>
                            <td><?= $app['id'] ?></td>
                            <td><?= htmlspecialchars($app['job_title']) ?></td>
                            <td><?= htmlspecialchars($app['user_name']) ?></td>
                            <td><?= htmlspecialchars($app['user_email']) ?></td>
                            <td>
                                <?php $cvPath = $app['cv_path'] ?: ($app['user_cv_path'] ?? ''); ?>
                                <?php if (!empty($cvPath) && jobhub_cv_is_stored_path($cvPath)): ?>
                                    <a href="../cv-download.php?scope=application&id=<?= (int) $app['id'] ?>" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">View CV</a>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $status = strtolower($app['status'] ?? 'pending');
                                $badge = match($status) {
                                    'pending'     => 'bg-warning',
                                    'shortlisted' => 'bg-primary',
                                    'interview'   => 'bg-info',
                                    'approved'    => 'bg-success',
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
