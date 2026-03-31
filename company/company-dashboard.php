<?php
// company/company-dashboard.php
require '../db.php';
require_role('company');
$cid = current_company_id() ?? 0;

update_expired_jobs($conn, $cid);

$deadlineColumn = job_deadline_column($conn);
$recentJobSelect = "id, title, location, status, is_approved, created_at, application_duration";
if ($deadlineColumn !== null) {
    $recentJobSelect .= ", {$deadlineColumn}";
}
if (job_has_post_date_column($conn)) {
    $recentJobSelect .= ", post_date";
}

$jobsCount = db_query_value("SELECT COUNT(*) FROM jobs WHERE company_id = ?", "i", [$cid]);
$activeJobs = db_query_value(
    "SELECT COUNT(*) FROM jobs WHERE company_id = ? AND status = 'active' AND is_approved = 1",
    "i",
    [$cid]
);
$applicationsCount = db_query_value("
    SELECT COUNT(*)
    FROM applications a
    JOIN jobs j ON a.job_id = j.id
    WHERE j.company_id = ?
", "i", [$cid]);

$recentJobs = db_query_all("
    SELECT {$recentJobSelect}
    FROM jobs
    WHERE company_id = ?
    ORDER BY created_at DESC
    LIMIT 5
", "i", [$cid]);
?>

<?php require 'company-header.php'; ?>

<?php if (!$isApproved): ?>
    <div class="alert alert-warning pending-banner">
        <strong>Your account is pending approval.</strong><br>
        The admin will review your company registration before you can fully use posting features.
        <?php if (!empty($rejectionReason)): ?>
            <br><strong>Previous rejection reason:</strong> <?= htmlspecialchars($rejectionReason) ?>
        <?php endif; ?>
    </div>
<?php elseif (!$isVerified): ?>
    <div class="alert alert-warning pending-banner">
        <strong>Your company is not verified for job posting.</strong><br>
        Submit your verification details before posting a new job.
        <?php if ($verificationStatus === 'rejected' && !empty($company['verification_admin_remarks'])): ?>
            <br><strong>Admin remarks:</strong> <?= htmlspecialchars($company['verification_admin_remarks']) ?>
        <?php endif; ?>
        <br><a href="company-verification.php" class="alert-link">Open company verification</a>
    </div>
<?php elseif ($operationalState === 'on_hold'): ?>
    <div class="alert alert-warning pending-banner">
        <strong>Your company is currently on hold.</strong><br>
        You cannot post jobs until the hold is lifted.
        <?php if (!empty($restrictionReason)): ?>
            <br><strong>Reason:</strong> <?= htmlspecialchars($restrictionReason) ?>
        <?php endif; ?>
    </div>
<?php elseif ($operationalState === 'suspended'): ?>
    <div class="alert alert-danger pending-banner">
        <strong>Your company account is suspended.</strong><br>
        You cannot post jobs due to policy violations.
        <?php if (!empty($restrictionReason)): ?>
            <br><strong>Reason:</strong> <?= htmlspecialchars($restrictionReason) ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<h1 class="mb-4">Company Dashboard</h1>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body text-center">
                <h6 class="text-muted mb-1">Total Jobs Posted</h6>
                <h2 class="display-6"><?= number_format($jobsCount) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body text-center">
                <h6 class="text-muted mb-1">Active Jobs</h6>
                <h2 class="display-6 text-success"><?= number_format($activeJobs) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body text-center">
                <h6 class="text-muted mb-1">Received Applications</h6>
                <h2 class="display-6"><?= number_format($applicationsCount) ?></h2>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mt-5">
    <div class="card-header bg-light">
        <h5 class="mb-0">Recent Jobs</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0 table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Title</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Approval</th>
                        <th>Posted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($recentJobs)): ?>
                    <tr><td colspan="6" class="text-center py-4">No jobs posted yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($recentJobs as $job): ?>
                        <?php $effectiveStatus = job_effective_status($job); ?>
                        <tr>
                            <td><?= htmlspecialchars($job['title']) ?></td>
                            <td><?= htmlspecialchars($job['location']) ?></td>
                            <td>
                                <span class="badge <?= job_status_badge_class($job) ?>">
                                    <?= htmlspecialchars(job_status_label($job)) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= job_approval_badge_class((int)($job['is_approved'] ?? 0)) ?>">
                                    <?= job_approval_label((int)($job['is_approved'] ?? 0)) ?>
                                </span>
                            </td>
                            <td><?= date('M d, Y', strtotime($job['created_at'])) ?></td>
                            <td>
                                <a href="company-edit-job.php?id=<?= (int)$job['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                <a href="company-applications.php?job_id=<?= (int)$job['id'] ?>" class="btn btn-sm btn-outline-info">Applications</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="text-center mt-4">
    <a href="company-add-job.php" class="btn btn-lg btn-primary <?= $canPostJobs ? '' : 'disabled' ?>">
        Post a New Job
    </a>
    <?php if (!$canPostJobs): ?>
        <div class="small text-muted mt-2">Job posting is disabled until your company account is approved, verified, and active.</div>
    <?php endif; ?>
</div>

<?php require '../footer.php'; ?>
