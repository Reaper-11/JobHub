<?php
// company/company-application-details.php
require '../db.php';
require_once '../includes/application_status_helper.php';

require_role('company');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: company-applications.php");
    exit;
}

$cid = current_company_id() ?? 0;
$app_id = (int)$_GET['id'];
$msg = '';
$msg_type = '';
$schemaResult = jobhub_application_ensure_status_columns();

$fetchApplication = static function (int $applicationId, int $companyId): ?array {
    $responseMessageSelect = jobhub_application_has_column('response_message')
        ? 'a.response_message,'
        : "'' AS response_message,";
    $statusUpdatedSelect = jobhub_application_has_column('status_updated_at')
        ? 'a.status_updated_at,'
        : 'NULL AS status_updated_at,';

    return db_query_all("
        SELECT a.id, a.status, {$responseMessageSelect} {$statusUpdatedSelect}
               a.cover_letter, a.cv_path, a.applied_at, a.updated_at,
               u.name AS user_name, u.email AS user_email, u.phone AS user_phone,
               u.cv_path AS user_cv_path, u.profile_image AS user_profile_image,
               j.id AS job_id, j.title AS job_title,
               COALESCE(NULLIF(c.name, ''), NULLIF(j.company, ''), 'Company') AS job_company,
               j.location AS job_location, j.type AS job_type, j.salary AS job_salary
        FROM applications a
        JOIN users u ON u.id = a.user_id
        JOIN jobs j ON j.id = a.job_id
        LEFT JOIN companies c ON c.id = j.company_id
        WHERE a.id = ? AND j.company_id = ?
        LIMIT 1
    ", "ii", [$applicationId, $companyId])[0] ?? null;
};

$app = $fetchApplication($app_id, $cid);
if (!$app) {
    header("Location: company-applications.php");
    exit;
}

if (empty($schemaResult['success'])) {
    $msg = (string)($schemaResult['message'] ?? 'Application status fields could not be prepared.');
    $msg_type = 'danger';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = 'Invalid request. Please try again.';
        $msg_type = 'danger';
    } elseif ((int)($_POST['application_id'] ?? 0) !== $app_id) {
        $msg = 'Invalid application selection.';
        $msg_type = 'danger';
    } else {
        $result = jobhub_company_update_application_status(
            $cid,
            (int)($_POST['application_id'] ?? 0),
            (string)($_POST['status'] ?? ''),
            (string)($_POST['response_message'] ?? '')
        );

        $msg = (string)($result['message'] ?? 'Update failed.');
        $msg_type = (string)($result['type'] ?? ($result['ok'] ? 'success' : 'danger'));

        if (!empty($result['ok'])) {
            $refreshedApplication = $fetchApplication($app_id, $cid);
            if ($refreshedApplication) {
                $app = $refreshedApplication;
            }
        }
    }
}

$backUrl = $app['job_id'] ? "company-applications.php?job_id=" . (int)$app['job_id'] : "company-applications.php";
$currentStatus = strtolower((string)($app['status'] ?? 'pending'));
$savedResponseMessage = trim((string)($app['response_message'] ?? ''));
$prefilledResponseMessage = $savedResponseMessage !== ''
    ? $savedResponseMessage
    : jobhub_application_default_response_message($currentStatus);
$statusUpdatedAt = trim((string)($app['status_updated_at'] ?? ''));
if ($statusUpdatedAt === '') {
    $statusUpdatedAt = trim((string)($app['updated_at'] ?? ''));
}
?>

<?php require 'company-header.php'; ?>

<h1 class="mb-4">Application #<?= $app_id ?></h1>

<a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-outline-secondary mb-4">Back to Applications</a>

<?php if ($msg): ?>
    <div class="alert alert-<?= htmlspecialchars($msg_type) ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">Job Information</h5>
            </div>
            <div class="card-body">
                <h5><?= htmlspecialchars($app['job_title']) ?></h5>
                <p class="text-muted mb-1">
                    <?= htmlspecialchars($app['job_company']) ?>
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
                <?php if (!empty($cvPath) && jobhub_cv_is_stored_path($cvPath)): ?>
                    <p><strong>CV:</strong> <span class="badge bg-success-subtle text-success border border-success-subtle">Attached</span></p>
                    <p><a href="../cv-download.php?scope=application&id=<?= (int)$app['id'] ?>" target="_blank" rel="noopener">View or download CV</a></p>
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

    <div class="col-lg-5">
        <div class="position-sticky" style="top: 20px;">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Current Status</h5>
                </div>
                <div class="card-body">
                    <span class="badge fs-6 p-2 <?= match($currentStatus) {
                        'pending' => 'bg-warning text-dark',
                        'shortlisted' => 'bg-primary',
                        'interview' => 'bg-info',
                        'approved' => 'bg-success',
                        'rejected' => 'bg-danger',
                        default => 'bg-secondary'
                    } ?>">
                        <?= htmlspecialchars(ucfirst($app['status'] ?? 'Pending')) ?>
                    </span>

                    <?php if ($statusUpdatedAt !== ''): ?>
                        <div class="text-muted small mt-3">
                            Updated on <?= date('M d, Y H:i', strtotime($statusUpdatedAt)) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Update Application Status</h5>
                </div>
                <div class="card-body">
                    <form method="post" class="js-status-update-form">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="application_id" value="<?= (int)$app['id'] ?>">

                        <div class="mb-3">
                            <label class="form-label" for="status">Status</label>
                            <select name="status" id="status" class="form-select js-status-select" required>
                                <option value="pending" <?= $currentStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="shortlisted" <?= $currentStatus === 'shortlisted' ? 'selected' : '' ?>>Shortlisted</option>
                                <option value="approved" <?= $currentStatus === 'approved' ? 'selected' : '' ?>>Approved</option>
                                <option value="interview" <?= $currentStatus === 'interview' ? 'selected' : '' ?>>Interview</option>
                                <option value="rejected" <?= $currentStatus === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="response_message">Response Message</label>
                            <textarea
                                name="response_message"
                                id="response_message"
                                class="form-control js-response-message"
                                rows="5"
                                placeholder="Write message to applicant (this will also be sent by email)"
                            ><?= htmlspecialchars($prefilledResponseMessage) ?></textarea>
                            <div class="form-text">This message will be visible to the applicant and sent by email.</div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Save Update</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const form = document.querySelector(".js-status-update-form");
    if (!form) {
        return;
    }

    const statusSelect = form.querySelector(".js-status-select");
    const responseTextarea = form.querySelector(".js-response-message");
    if (!statusSelect || !responseTextarea) {
        return;
    }

    const defaultMessages = {
        pending: "Your application is under review.",
        shortlisted: "You have been shortlisted for the next step.",
        approved: "Your application has been approved.",
        interview: "You are invited for an interview.",
        rejected: "We regret to inform you that your application was not selected."
    };

    const syncDefaultMessage = function () {
        const currentMessage = responseTextarea.value.trim();
        const isExistingDefaultMessage = Object.values(defaultMessages).includes(currentMessage);

        if (currentMessage === "" || isExistingDefaultMessage) {
            responseTextarea.value = defaultMessages[statusSelect.value] || "";
        }
    };

    statusSelect.addEventListener("change", syncDefaultMessage);
});
</script>

<?php require '../footer.php'; ?>
