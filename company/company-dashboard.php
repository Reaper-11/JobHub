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

<?php if ($finalCompanyStatus === 'rejected'): ?>
    <div class="alert alert-danger pending-banner">
        <strong>Your company account is rejected.</strong><br>
        Rejected companies cannot post jobs and remain non-active until admin re-approves them.
        <?php if (!empty($rejectionReason)): ?>
            <br><strong>Reason:</strong> <?= htmlspecialchars($rejectionReason) ?>
        <?php endif; ?>
    </div>
<?php elseif ($finalCompanyStatus === 'pending'): ?>
    <div class="alert alert-warning pending-banner">
        <strong>Your company account is awaiting approval.</strong><br>
        In the meantime, please submit your verification details in the <a href="company-verification.php">Company Verification</a> section to speed up the process and access all features.
    </div>
<?php elseif ($finalCompanyStatus === 'approved_incomplete'): ?>
    <div class="alert alert-warning pending-banner">
        <strong>Your company is approved, but verification is incomplete.</strong><br>
        Submit or complete company verification before posting a new job.
        <?php if ($verificationStatus === 'rejected' && !empty($company['verification_admin_remarks'])): ?>
            <br><strong>Admin remarks:</strong> <?= htmlspecialchars($company['verification_admin_remarks']) ?>
        <?php endif; ?>
        <br><a href="company-verification.php" class="alert-link">Open company verification</a>
    </div>
<?php elseif ((int)($company['is_active'] ?? 1) !== 1): ?>
    <div class="alert alert-danger pending-banner">
        <strong>Your company account is inactive.</strong><br>
        Please contact admin for access to posting features.
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

<div class="row g-3 mb-4" style="max-width: 1000px;">
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-card-icon blue"><i class="fas fa-briefcase"></i></div>
            <div class="stat-card-body">
                <div class="stat-label">Total Jobs Posted</div>
                <div class="stat-value"><?= number_format($jobsCount) ?></div>
                <div class="stat-sub" style="color: #cbd5e1; font-size: 12px; opacity: 0.8;">All time</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-card-icon green"><i class="fas fa-circle-check"></i></div>
            <div class="stat-card-body">
                <div class="stat-label">Active Jobs</div>
                <div class="stat-value"><?= number_format($activeJobs) ?></div>
                <div class="stat-sub" style="color: #cbd5e1; font-size: 12px; opacity: 0.8;">Currently live</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-card-icon purple"><i class="fas fa-file-alt"></i></div>
            <div class="stat-card-body">
                <div class="stat-label">Total Applications Received</div>
                <div class="stat-value"><?= number_format($applicationsCount) ?></div>
                <div class="stat-sub" style="color: #cbd5e1; font-size: 12px; opacity: 0.8;">All jobs</div>
            </div>
        </div>
    </div>
</div>

<div class="card mt-2">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-clock me-2 text-muted"></i>Recent Jobs</h5>
        <a href="company-add-job.php" class="btn btn-primary btn-sm <?= $canPostJobs ? '' : 'disabled' ?>">
            <i class="fas fa-plus me-1"></i>Post a New Job
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0 align-middle company-dashboard-table">
                <style>
                    .company-dashboard-table thead th {
                        background-color: rgba(17, 24, 39, 0.6);
                        border-bottom: 2px solid #243041;
                        font-weight: 600;
                        color: #cbd5e1;
                        text-transform: uppercase;
                        font-size: 12px;
                        letter-spacing: 0.04em;
                        padding: 14px 12px;
                    }
                    .company-dashboard-table tbody tr {
                        border-bottom: 1px solid #243041;
                        transition: background-color 0.15s ease;
                    }
                    .company-dashboard-table tbody tr:hover {
                        background-color: rgba(255, 255, 255, 0.04);
                        cursor: pointer;
                    }
                </style>
                <thead>
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
                                <a href="company-applications.php?job_id=<?= (int)$job['id'] ?>" class="btn btn-sm btn-outline-info">View Applications</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (!$canPostJobs): ?>
    <div class="text-center mt-4">
        <div class="small text-muted">Job posting is disabled until your company account is approved, verified, and active.</div>
    </div>
<?php endif; ?>

<?php require '../footer.php'; ?>
