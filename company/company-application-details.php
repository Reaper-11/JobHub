<?php
// company/company-application-details.php
require '../db.php';

if (!isset($_SESSION['company_id']) || !isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: company-applications.php");
    exit;
}

$cid = (int)$_SESSION['company_id'];
$app_id = (int)$_GET['id'];

$app = db_query_all("
    SELECT a.id, a.status, a.cover_letter, a.cv_path, a.applied_at, a.updated_at,
           u.name AS user_name, u.email AS user_email, u.phone AS user_phone,
           u.cv_path AS user_cv_path, u.profile_image AS user_profile_image,
           j.id AS job_id, j.title AS job_title, j.company AS job_company,
           j.location AS job_location, j.type AS job_type, j.salary AS job_salary
    FROM applications a
    JOIN users u ON u.id = a.user_id
    JOIN jobs j ON j.id = a.job_id
    WHERE a.id = ? AND j.company_id = ?
    LIMIT 1
", "ii", [$app_id, $cid])[0] ?? null;

if (!$app) {
    header("Location: company-applications.php");
    exit;
}

$backUrl = $app['job_id'] ? "company-applications.php?job_id=" . (int)$app['job_id'] : "company-applications.php";
?>

<?php require 'company-header.php'; ?>

<h1 class="mb-4">Application #<?= $app_id ?></h1>

<a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-outline-secondary mb-4">Back to Applications</a>

<div class="row g-4">
    <!-- Left column: Job & Applicant Info -->
    <div class="col-lg-7">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">Job Information</h5>
            </div>
            <div class="card-body">
                <h5><?= htmlspecialchars($app['job_title']) ?></h5>
                <p class="text-muted mb-1">
                    <?= htmlspecialchars($app['job_company'] ?: ($_SESSION['company_name'] ?? 'Company')) ?>
                    &bull;
                    <?= htmlspecialchars($app['job_location'] ?: 'N/A') ?>
                    <span class="badge bg-secondary ms-2"><?= htmlspecialchars($app['job_type'] ?? 'Full-time') ?></span>
                </p>
                <?php if (!empty($app['job_salary'])): ?>
                    <p><strong>Salary:</strong> <?= htmlspecialchars($app['job_salary']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">Applicant Information</h5>
            </div>
            <div class="card-body">
                <p><strong>Name:</strong> <?= htmlspecialchars($app['user_name']) ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($app['user_email']) ?></p>
                <p><strong>Phone:</strong> <?= htmlspecialchars($app['user_phone'] ?: 'Not provided') ?></p>
                <p><strong>Applied:</strong> <?= date('M d, Y H:i', strtotime($app['applied_at'])) ?></p>
                <?php $cvPath = $app['cv_path'] ?: ($app['user_cv_path'] ?? ''); ?>
                <?php if (!empty($cvPath)): ?>
                    <p><strong>CV:</strong> <a href="../<?= htmlspecialchars($cvPath) ?>" target="_blank" rel="noopener">View CV</a></p>
                <?php else: ?>
                    <p><strong>CV:</strong> <span class="text-muted">Not provided</span></p>
                <?php endif; ?>
                <?php if (!empty($app['updated_at'])): ?>
                    <p><strong>Last Updated:</strong> <?= date('M d, Y H:i', strtotime($app['updated_at'])) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Cover Letter</h5>
                <span class="badge bg-<?= $app['cover_letter'] ? 'success' : 'secondary' ?>">
                    <?= $app['cover_letter'] ? 'Provided' : 'Not provided' ?>
                </span>
            </div>
            <div class="card-body">
                <?php if ($app['cover_letter']): ?>
                    <div style="white-space: pre-wrap;"><?= nl2br(htmlspecialchars($app['cover_letter'])) ?></div>
                <?php else: ?>
                    <p class="text-muted mb-0">No cover letter was submitted with this application.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right column: Status -->
    <div class="col-lg-5">
        <div class="card shadow-sm sticky-top" style="top: 20px;">
            <div class="card-header bg-light">
                <h5 class="mb-0">Current Status</h5>
            </div>
            <div class="card-body">
                <span class="badge fs-6 p-2 <?= match(strtolower($app['status'] ?? 'pending')) {
                    'pending'     => 'bg-warning text-dark',
                    'shortlisted' => 'bg-primary',
                    'approved'    => 'bg-success',
                    'rejected'    => 'bg-danger',
                    default       => 'bg-secondary'
                } ?>">
                    <?= ucfirst($app['status'] ?? 'Pending') ?>
                </span>
                <div class="text-muted small mt-3">
                    Update status from the Applications list.
                </div>
            </div>
        </div>
    </div>
</div>

<?php require '../footer.php'; ?>
