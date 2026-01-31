<?php
// admin/application-details.php
require '../db.php';

if (!isset($_SESSION['admin_id']) || !isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: admin-applications.php");
    exit;
}

$app_id = (int)$_GET['id'];

$app = db_query_all("
    SELECT a.*, 
           u.name AS user_name, u.email AS user_email, u.phone AS user_phone,
           j.title AS job_title, j.company AS job_company, j.location AS job_location,
           j.type AS job_type, j.salary AS job_salary
    FROM applications a
    JOIN users u ON u.id = a.user_id
    JOIN jobs j ON j.id = a.job_id
    WHERE a.id = ?
    LIMIT 1
", "i", [$app_id])[0] ?? null;

if (!$app) {
    header("Location: admin-applications.php");
    exit;
}

// Possible next statuses (you can expand logic later)
$possible_statuses = [
    'pending'      => ['reviewed', 'shortlisted', 'rejected'],
    'reviewed'     => ['shortlisted', 'rejected'],
    'shortlisted'  => ['interview_scheduled', 'rejected', 'offered'],
    'interview_scheduled' => ['offered', 'rejected'],
    'offered'      => ['hired', 'rejected'],
];

$current_status = strtolower($app['status'] ?? 'pending');
$next_options = $possible_statuses[$current_status] ?? [];

$msg = $msg_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validate_csrf_token($_POST['csrf_token'] ?? '')) {
    $new_status = trim($_POST['new_status'] ?? '');
    if (in_array($new_status, $next_options)) {
        $stmt = $conn->prepare("UPDATE applications SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $new_status, $app_id);
        if ($stmt->execute()) {
            $msg = "Application status updated to <strong>" . ucfirst($new_status) . "</strong>.";
            $msg_type = 'success';
            $app['status'] = $new_status; // refresh view
        } else {
            $msg = "Failed to update status.";
            $msg_type = 'danger';
        }
        $stmt->close();
    } else {
        $msg = "Invalid status transition.";
        $msg_type = 'danger';
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
                <?php if ($app['job_salary']): ?>
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
                <p><strong>Applied:</strong> <?= date('Y-m-d H:i', strtotime($app['applied_at'])) ?></p>
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
                <div class="mb-4">
                    <span class="badge fs-6 p-2 <?= match(strtolower($app['status'] ?? 'pending')) {
                        'pending'     => 'bg-warning text-dark',
                        'shortlisted' => 'bg-primary',
                        'offered'     => 'bg-success',
                        'rejected'    => 'bg-danger',
                        default       => 'bg-secondary'
                    } ?>">
                        <?= ucfirst($app['status'] ?? 'Pending') ?>
                    </span>
                </div>

                <?php if (!empty($next_options)): ?>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                        <div class="mb-3">
                            <label class="form-label">Change Status to:</label>
                            <select name="new_status" class="form-select">
                                <option value="">Select new status...</option>
                                <?php foreach ($next_options as $opt): ?>
                                    <option value="<?= $opt ?>"><?= ucfirst(str_replace('_', ' ', $opt)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            Update Status
                        </button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-info small mb-0">
                        No further status changes available at this stage.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require '../footer.php'; ?>