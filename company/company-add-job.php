<?php
// company/company-add-job.php
require '../db.php';

if (!isset($_SESSION['company_id'])) {
    header("Location: company-login.php");
    exit;
}

$cid = (int)$_SESSION['company_id'];
$msg = $msg_type = '';
$categories = [ /* your full list here */ ];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validate_csrf_token($_POST['csrf_token'] ?? '')) {
    $title = trim($_POST['title'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $type = trim($_POST['type'] ?? 'Full-time');
    $category = trim($_POST['category'] ?? '');
    $salary = trim($_POST['salary'] ?? '');
    $duration = trim($_POST['application_duration'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($title) || empty($location) || empty($category) || empty($description)) {
        $msg = "Required fields are missing.";
        $msg_type = 'danger';
    } else {
        $stmt = $conn->prepare("
            INSERT INTO jobs (company_id, title, location, type, category, salary, application_duration, description, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
        ");
        $stmt->bind_param("isssssss", $cid, $title, $location, $type, $category, $salary, $duration, $description);

        if ($stmt->execute()) {
            $msg = "Job posted successfully!";
            $msg_type = 'success';
            // Optional: redirect to my-jobs
        } else {
            $msg = "Failed to post job. Please try again.";
            $msg_type = 'danger';
        }
        $stmt->close();
    }
}
?>

<?php require 'company-header.php'; ?>

<h1 class="mb-4">Post a New Job</h1>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

            <div class="mb-3">
                <label class="form-label">Job Title *</label>
                <input type="text" name="title" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Location *</label>
                <input type="text" name="location" class="form-control" required placeholder="Kathmandu, Nepal">
            </div>

            <div class="mb-3">
                <label class="form-label">Job Type</label>
                <select name="type" class="form-select">
                    <option>Full-time</option>
                    <option>Part-time</option>
                    <option>Contract</option>
                    <option>Remote</option>
                    <option>Internship</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Category *</label>
                <select name="category" class="form-select" required>
                    <option value="">Select category...</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Salary (optional)</label>
                <input type="text" name="salary" class="form-control" placeholder="e.g. 30,000 - 50,000 NPR">
            </div>

            <div class="mb-3">
                <label class="form-label">Application Duration (optional)</label>
                <input type="text" name="application_duration" class="form-control" placeholder="e.g. 30 days">
            </div>

            <div class="mb-4">
                <label class="form-label">Description *</label>
                <textarea name="description" class="form-control" rows="6" required></textarea>
            </div>

            <button type="submit" class="btn btn-primary">Publish Job</button>
            <a href="company-dashboard.php" class="btn btn-outline-secondary ms-2">Cancel</a>
        </form>
    </div>
</div>

<?php require '../footer.php'; ?>