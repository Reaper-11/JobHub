<?php
// company/company-applications.php
require '../db.php';
require_role('company');
$cid = current_company_id() ?? 0;
$job_id = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;

$where = $job_id > 0 
    ? "j.company_id = ? AND a.job_id = ?" 
    : "j.company_id = ?";

$params = $job_id > 0 ? [$cid, $job_id] : [$cid];
$types  = $job_id > 0 ? "ii" : "i";

$msg = $msg_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validate_csrf_token($_POST['csrf_token'] ?? '')) {
    $app_id = (int)($_POST['app_id'] ?? 0);
    $new_status = trim($_POST['status'] ?? '');
    $allowed_statuses = ['pending', 'shortlisted', 'interview', 'approved', 'rejected'];
    $response_statuses = ['shortlisted', 'interview', 'approved', 'rejected'];
    $response_message = trim($_POST['response_message'] ?? '');
    $response_message = in_array($new_status, $response_statuses, true) && $response_message !== '' ? $response_message : null;

    if ($app_id > 0 && in_array($new_status, $allowed_statuses, true)) {
        $currentApplication = db_query_all("
            SELECT a.id, a.user_id, a.status, a.response_message, j.title AS job_title,
                   COALESCE(c.name, j.company, 'Company') AS company_name
            FROM applications a
            JOIN jobs j ON j.id = a.job_id
            LEFT JOIN companies c ON c.id = j.company_id
            WHERE a.id = ? AND j.company_id = ?
            LIMIT 1
        ", "ii", [$app_id, $cid])[0] ?? null;

        if (!$currentApplication) {
            $msg = "Application not found.";
            $msg_type = 'danger';
        } else {
            $currentResponse = trim((string)($currentApplication['response_message'] ?? ''));
            $currentResponse = $currentResponse !== '' ? $currentResponse : null;

            if (
                strcasecmp((string)$currentApplication['status'], $new_status) === 0
                && $currentResponse === $response_message
            ) {
                $msg = "Application already has that status.";
                $msg_type = 'info';
            } else {
                $stmt = $conn->prepare("
                    UPDATE applications
                    SET status = ?, response_message = NULLIF(?, ''), updated_at = NOW()
                    WHERE id = ? AND job_id IN (SELECT id FROM jobs WHERE company_id = ?)
                ");
                $response_value = $response_message ?? '';
                $stmt->bind_param("ssii", $new_status, $response_value, $app_id, $cid);

                if ($stmt->execute()) {
                    $msg = "Application status updated.";
                    $msg_type = 'success';

                    $jobTitle = $currentApplication['job_title'] ?? 'your application';
                    $companyName = $currentApplication['company_name'] ?? 'the company';
                    $statusLabel = notify_status_label($new_status);
                    $notificationMessage = 'Your application for "' . $jobTitle . '" at ' . $companyName . ' has been updated to "' . $statusLabel . '".';
                    if ($response_message !== null) {
                        $notificationMessage .= ' Message from company: ' . $response_message;
                    }

                    notify_create(
                        'user',
                        (int)$currentApplication['user_id'],
                        'Application Status Updated',
                        $notificationMessage,
                        'my-applications.php',
                        notify_status_type($new_status),
                        'application',
                        $app_id
                    );
                } else {
                    $msg = "Update failed.";
                    $msg_type = 'danger';
                }
                $stmt->close();
            }
        }
    } else {
        $msg = "Invalid status selection.";
        $msg_type = 'danger';
    }
}

$applications = db_query_all("
    SELECT a.id, a.status, a.response_message, a.cover_letter, a.cv_path, a.applied_at,
           u.name AS user_name, u.email AS user_email, u.cv_path AS user_cv_path,
           j.title AS job_title
    FROM applications a
    JOIN users u ON u.id = a.user_id
    JOIN jobs j ON j.id = a.job_id
    WHERE $where
    ORDER BY a.applied_at DESC
", $types, $params);
?>

<?php require 'company-header.php'; ?>

<h1 class="mb-4">
    <?= $job_id > 0 ? 'Applications for Job #' . $job_id : 'All Received Applications' ?>
</h1>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php if (empty($applications)): ?>
    <div class="alert alert-info">No applications received yet.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Applicant</th>
                    <th>Email</th>
                    <th>CV</th>
                    <th>Job Title</th>
                    <th>Status</th>
                    <th>Applied</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($applications as $app): ?>
                <tr>
                    <td><?= htmlspecialchars($app['user_name']) ?></td>
                    <td><?= htmlspecialchars($app['user_email']) ?></td>
                    <td>
                        <?php $cvPath = $app['cv_path'] ?: ($app['user_cv_path'] ?? ''); ?>
                        <?php if (!empty($cvPath) && jobhub_cv_is_stored_path($cvPath)): ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle me-2">Attached</span>
                            <a class="btn btn-sm btn-outline-secondary" href="../cv-download.php?scope=application&id=<?= (int) $app['id'] ?>" target="_blank" rel="noopener">View CV</a>
                        <?php else: ?>
                            <span class="text-muted">N/A</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($app['job_title']) ?></td>
                    <td>
                        <span class="badge <?= match(strtolower($app['status'] ?? 'pending')) {
                            'shortlisted' => 'bg-primary',
                            'interview'   => 'bg-info',
                            'approved'    => 'bg-success',
                            'rejected'    => 'bg-danger',
                            default       => 'bg-warning'
                        } ?>">
                            <?= ucfirst($app['status'] ?? 'Pending') ?>
                        </span>
                    </td>
                    <td><?= date('M d, Y', strtotime($app['applied_at'])) ?></td>
                    <td>
                        <a class="btn btn-sm btn-outline-secondary me-2" href="company-application-details.php?id=<?= $app['id'] ?>">
                            View Details
                        </a>
                        <button class="btn btn-sm btn-outline-primary"
                                data-bs-toggle="modal" 
                                data-bs-target="#statusModal<?= $app['id'] ?>">
                            Update Status
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php foreach ($applications as $app): ?>
        <!-- Status Update Modal -->
        <div class="modal fade" id="statusModal<?= $app['id'] ?>" tabindex="-1">
            <div class="modal-dialog">
                <form method="post" class="modal-content js-status-form">
                    <div class="modal-header">
                        <h5 class="modal-title">Update Application Status</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="app_id" value="<?= $app['id'] ?>">

                        <div class="mb-3">
                            <label class="form-label">New Status</label>
                            <select name="status" class="form-select js-status-select" required>
                                <option value="pending" <?= strtolower($app['status'] ?? 'pending') === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="shortlisted" <?= strtolower($app['status'] ?? '') === 'shortlisted' ? 'selected' : '' ?>>Shortlisted</option>
                                <option value="interview" <?= strtolower($app['status'] ?? '') === 'interview' ? 'selected' : '' ?>>Interview</option>
                                <option value="approved" <?= strtolower($app['status'] ?? '') === 'approved' ? 'selected' : '' ?>>Approved</option>
                                <option value="rejected" <?= strtolower($app['status'] ?? '') === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                            </select>
                        </div>

                        <div class="js-response-box" style="display:none; margin-top:10px;">
                            <label class="form-label" for="response_message_<?= (int)$app['id'] ?>">Response Message</label>
                            <textarea
                                name="response_message"
                                id="response_message_<?= (int)$app['id'] ?>"
                                class="form-control"
                                rows="4"
                                placeholder="Write message for applicant..."
                            ><?= htmlspecialchars($app['response_message'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const statusesWithResponse = new Set(["shortlisted", "interview", "approved", "rejected"]);

    document.querySelectorAll(".js-status-form").forEach(function (form) {
        const statusSelect = form.querySelector(".js-status-select");
        const responseBox = form.querySelector(".js-response-box");

        if (!statusSelect || !responseBox) {
            return;
        }

        const toggleResponseBox = function () {
            responseBox.style.display = statusesWithResponse.has(statusSelect.value) ? "block" : "none";
        };

        statusSelect.addEventListener("change", toggleResponseBox);
        toggleResponseBox();
    });
});
</script>

<?php require '../footer.php'; ?>
