<?php
// company/company-applications.php
require '../db.php';
require_once '../includes/application_status_helper.php';
require_role('company');
$cid = current_company_id() ?? 0;
$job_id = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;

$where = $job_id > 0 
    ? "j.company_id = ? AND a.job_id = ?" 
    : "j.company_id = ?";

$params = $job_id > 0 ? [$cid, $job_id] : [$cid];
$types  = $job_id > 0 ? "ii" : "i";

$schemaResult = jobhub_application_ensure_status_columns();
$responseMessageSelect = jobhub_application_has_column('response_message')
    ? 'a.response_message,'
    : "'' AS response_message,";
$msg = $msg_type = '';
if (!empty($schemaResult['success']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = "Invalid request. Please try again.";
        $msg_type = 'danger';
    } else {
        $result = jobhub_company_update_application_status(
            $cid,
            (int)($_POST['application_id'] ?? ($_POST['app_id'] ?? 0)),
            (string)($_POST['status'] ?? ''),
            (string)($_POST['response_message'] ?? '')
        );

        $msg = (string)($result['message'] ?? 'Update failed.');
        $msg_type = (string)($result['type'] ?? ($result['ok'] ? 'success' : 'danger'));
    }
} elseif (empty($schemaResult['success'])) {
    $msg = (string)($schemaResult['message'] ?? 'Application status fields could not be prepared.');
    $msg_type = 'danger';
}

$applications = db_query_all("
    SELECT a.id, a.status, {$responseMessageSelect} a.cover_letter, a.cv_path, a.applied_at,
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
                        <input type="hidden" name="application_id" value="<?= $app['id'] ?>">

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

                        <div class="js-response-box" style="margin-top:10px;">
                            <label class="form-label" for="response_message_<?= (int)$app['id'] ?>">Response Message</label>
                            <textarea
                                name="response_message"
                                id="response_message_<?= (int)$app['id'] ?>"
                                class="form-control"
                                rows="4"
                                placeholder="Write message to applicant (this will also be sent by email)"
                            ><?= htmlspecialchars($app['response_message'] ?? '') ?></textarea>
                            <div class="form-text">This message will be visible to the applicant and sent by email.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Update</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const defaultMessages = {
        pending: "Your application is under review.",
        shortlisted: "You have been shortlisted for the next step.",
        approved: "Your application has been approved.",
        interview: "You are invited for an interview.",
        rejected: "We regret to inform you that your application was not selected."
    };

    document.querySelectorAll(".js-status-form").forEach(function (form) {
        const statusSelect = form.querySelector(".js-status-select");
        const responseBox = form.querySelector(".js-response-box");
        const responseTextarea = form.querySelector("textarea[name='response_message']");

        if (!statusSelect || !responseBox || !responseTextarea) {
            return;
        }

        const toggleResponseBox = function () {
            responseBox.style.display = "block";
            responseTextarea.required = false;
            const currentMessage = responseTextarea.value.trim();
            const isExistingDefaultMessage = Object.values(defaultMessages).includes(currentMessage);

            if (currentMessage === "" || isExistingDefaultMessage) {
                responseTextarea.value = defaultMessages[statusSelect.value] || "";
            }
        };

        statusSelect.addEventListener("change", toggleResponseBox);
        toggleResponseBox();
    });
});
</script>

<?php require '../footer.php'; ?>
