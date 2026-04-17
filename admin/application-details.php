<?php
// admin/application-details.php
require '../db.php';

require_role('admin');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: admin-applications.php");
    exit;
}

$app_id = (int)$_GET['id'];

$app = db_query_all("
    SELECT a.*,
           u.name AS user_name, u.email AS user_email, u.phone AS user_phone, u.cv_path AS user_cv_path,
           j.title AS job_title, j.company AS job_company, j.location AS job_location,
           j.type AS job_type, j.salary AS job_salary
    FROM applications a
    JOIN users u ON u.id = a.user_id
    JOIN jobs  j ON j.id = a.job_id
    WHERE a.id = ?
    LIMIT 1
", "i", [$app_id])[0] ?? null;

if (!$app) {
    header("Location: admin-applications.php");
    exit;
}

// Admin can move to any valid status from any non-terminal state.
// Approved and Rejected are terminal — no further changes allowed.
$adminStatusOptions = [
    'pending'     => ['shortlisted', 'interview', 'approved', 'rejected'],
    'shortlisted' => ['interview',   'approved',  'rejected'],
    'interview'   => ['approved',    'rejected',  'shortlisted'],
];

$current_status = strtolower($app['status'] ?? 'pending');
$next_options   = $adminStatusOptions[$current_status] ?? [];

// Labels and badge classes used in both PHP and HTML.
$statusMeta = [
    'pending'     => ['label' => 'Pending',     'badge' => 'bg-warning text-dark'],
    'shortlisted' => ['label' => 'Shortlisted', 'badge' => 'bg-primary'],
    'interview'   => ['label' => 'Interview',   'badge' => 'bg-info text-dark'],
    'approved'    => ['label' => 'Approved',    'badge' => 'bg-success'],
    'rejected'    => ['label' => 'Rejected',    'badge' => 'bg-danger'],
];

$msg = $msg_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validate_csrf_token($_POST['csrf_token'] ?? '')) {
    $new_status      = trim($_POST['new_status'] ?? '');
    $responseMessage = trim($_POST['response_message'] ?? '');

    if (!in_array($new_status, $next_options, true)) {
        $msg      = 'Invalid status transition.';
        $msg_type = 'danger';
    } elseif (strcasecmp((string)$app['status'], $new_status) === 0) {
        $msg      = 'Application already has that status.';
        $msg_type = 'info';
    } else {
        $stmt = $conn->prepare(
            "UPDATE applications SET status = ?, response_message = ?, status_updated_at = NOW(), updated_at = NOW() WHERE id = ?"
        );
        $stmt->bind_param('ssi', $new_status, $responseMessage, $app_id);

        if ($stmt->execute()) {
            $statusLabel = $statusMeta[$new_status]['label'] ?? ucfirst($new_status);
            $msg      = 'Application status updated to <strong>' . htmlspecialchars($statusLabel) . '</strong>.';
            $msg_type = 'success';
            $app['status'] = $new_status;
            $current_status = $new_status;
            $next_options   = $adminStatusOptions[$current_status] ?? [];

            // Notify the applicant.
            $notifyMsg = 'Your application for "' . ($app['job_title'] ?? 'Job') . '" has been updated to "' . $statusLabel . '".';
            if ($responseMessage !== '') {
                $notifyMsg .= ' Message from admin: ' . $responseMessage;
            }
            notify_create(
                'user',
                (int)$app['user_id'],
                'Application Status Updated',
                $notifyMsg,
                'my-applications.php',
                notify_status_type($new_status),
                'application',
                $app_id
            );

            // Log the admin action.
            log_activity(
                $conn,
                current_admin_id(),
                'admin',
                'application_status_updated',
                'Admin changed application #' . $app_id . ' status to ' . $statusLabel . ' for ' . ($app['user_name'] ?? 'user'),
                'application',
                $app_id
            );
        } else {
            $msg      = 'Failed to update status.';
            $msg_type = 'danger';
        }
        $stmt->close();
    }
}
?>

<?php require 'admin-header.php'; ?>

<h1 class="mb-4">Application #<?= $app_id ?></h1>

<a href="admin-applications.php" class="btn btn-outline-secondary mb-4">← Back to Applications</a>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msg_type ?>"><?= $msg ?></div>
<?php endif; ?>

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
                    <?= htmlspecialchars($app['job_company']) ?> • 
                    <?= htmlspecialchars($app['job_location']) ?>
                    <span class="badge bg-secondary ms-2"><?= htmlspecialchars($app['job_type'] ?? 'Full-time') ?></span>
                </p>
                <?php $jobSalaryText = jobhub_salary_display_value($app['job_salary'] ?? '', ''); ?>
                <?php if ($jobSalaryText !== ''): ?>
                    <p><strong>Salary:</strong> <?= htmlspecialchars($jobSalaryText) ?></p>
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
            <p><strong>Applied:</strong> <?= date('Y-m-d H:i', strtotime($app['applied_at'])) ?></p>
            <?php $cvPath = $app['cv_path'] ?: ($app['user_cv_path'] ?? ''); ?>
            <?php if (!empty($cvPath) && jobhub_cv_is_stored_path($cvPath)): ?>
                <p><strong>CV:</strong> <a href="../cv-download.php?scope=application&id=<?= (int) $app['id'] ?>" target="_blank" rel="noopener">View CV</a></p>
            <?php else: ?>
                <p><strong>CV:</strong> <span class="text-muted">Not provided</span></p>
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

    <!-- Right column: Status & Actions -->
    <div class="col-lg-5">
        <div class="card shadow-sm sticky-top" style="top: 20px;">
            <div class="card-header bg-light">
                <h5 class="mb-0">Current Status</h5>
            </div>
            <div class="card-body">
                <!-- Current status badge -->
                <div class="mb-4">
                    <?php
                    $badgeClass  = $statusMeta[$current_status]['badge']  ?? 'bg-secondary';
                    $badgeLabel  = $statusMeta[$current_status]['label']  ?? ucfirst($current_status);
                    ?>
                    <span class="badge fs-6 p-2 <?= $badgeClass ?>"><?= htmlspecialchars($badgeLabel) ?></span>
                </div>

                <!-- Status change form -->
                <?php if (!empty($next_options)): ?>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Change Status to:</label>
                            <select name="new_status" class="form-select" required>
                                <option value="">Select new status…</option>
                                <?php foreach ($next_options as $opt):
                                    $optMeta  = $statusMeta[$opt] ?? ['label' => ucfirst($opt), 'badge' => 'bg-secondary'];
                                ?>
                                    <option value="<?= htmlspecialchars($opt) ?>">
                                        <?= htmlspecialchars($optMeta['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Message to Applicant <span class="text-muted small fw-normal">(optional)</span></label>
                            <textarea name="response_message" class="form-control" rows="3"
                                      placeholder="e.g. Please attend an interview on Monday at 10am…"><?= htmlspecialchars($app['response_message'] ?? '') ?></textarea>
                            <div class="form-text">This message will be included in the notification sent to the applicant.</div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-check-circle me-1"></i> Update Status
                        </button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-info small mb-0">
                        <?php if (in_array($current_status, ['approved', 'rejected'], true)): ?>
                            This application is <strong><?= htmlspecialchars($badgeLabel) ?></strong> — no further status changes are available.
                        <?php else: ?>
                            No further status changes available at this stage.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Previous admin response message -->
                <?php if (!empty($app['response_message'])): ?>
                    <hr>
                    <div class="mt-3">
                        <div class="text-muted small fw-semibold mb-1">Last Admin Message</div>
                        <div class="content-surface rounded p-3 small"><?= nl2br(htmlspecialchars($app['response_message'])) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require '../footer.php'; ?>
