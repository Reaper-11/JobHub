<?php
// company/company-add-job.php
require '../db.php';

if (!isset($_SESSION['company_id'])) {
    header("Location: company-login.php");
    exit;
}

$cid = (int)$_SESSION['company_id'];
$msg = $msg_type = '';
$categoryError = '';
$categories = require __DIR__ . '/../includes/categories.php';
$category = '';
$experienceLevel = '';
$experienceLevels = [
    'Entry Level (0–1 years)',
    'Junior (1–3 years)',
    'Mid Level (3–5 years)',
    'Senior (5–8 years)',
    'Lead (8–10 years)',
    'Manager (10+ years)',
];

$statusStmt = $conn->prepare("SELECT is_approved, operational_state, restriction_reason FROM companies WHERE id = ?");
$statusStmt->bind_param("i", $cid);
$statusStmt->execute();
$companyStatus = $statusStmt->get_result()->fetch_assoc() ?? ['is_approved' => 0, 'operational_state' => 'active', 'restriction_reason' => null];
$statusStmt->close();

$isApproved = (int)($companyStatus['is_approved'] ?? 0) === 1;
$operationalState = $companyStatus['operational_state'] ?? 'active';
$restrictionReason = $companyStatus['restriction_reason'] ?? '';
$canPostJobs = $isApproved && $operationalState === 'active';

$blockMsg = '';
if (!$isApproved) {
    $blockMsg = "Your company is not approved yet. You cannot post jobs until approval.";
} elseif ($operationalState === 'on_hold') {
    $blockMsg = "Your company is currently on hold. Please contact support or wait for admin review.";
} elseif ($operationalState === 'suspended') {
    $blockMsg = "Your company account is suspended due to policy violations.";
}
if ($blockMsg !== '' && $restrictionReason) {
    $blockMsg .= " Reason: " . $restrictionReason;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validate_csrf_token($_POST['csrf_token'] ?? '')) {
    $title = trim($_POST['title'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $type = trim($_POST['type'] ?? 'Full-time');
    $category = trim($_POST['category'] ?? '');
    $salary = trim($_POST['salary'] ?? '');
    $duration = trim($_POST['application_duration'] ?? '');
    $experienceLevel = trim($_POST['experience_level'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (!$canPostJobs) {
        $msg = $blockMsg !== '' ? $blockMsg : "Your company is not allowed to post jobs at this time.";
        $msg_type = 'danger';
    } elseif (empty($title) || empty($location) || empty($category) || empty($experienceLevel) || empty($description)) {
        $msg = "Required fields are missing.";
        $msg_type = 'danger';
        if (empty($category)) {
            $categoryError = "Please select a category.";
        }
    } elseif (!in_array($experienceLevel, $experienceLevels, true)) {
        $msg = "Please select a valid experience level.";
        $msg_type = 'danger';
    } elseif (!in_array($category, $categories, true)) {
        $msg = "Please correct the errors below.";
        $msg_type = 'danger';
        $categoryError = "Invalid category selected.";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO jobs (company_id, title, location, type, category, salary, application_duration, experience_level, description, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
        ");
        $stmt->bind_param("issssssss", $cid, $title, $location, $type, $category, $salary, $duration, $experienceLevel, $description);

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

<?php if ($blockMsg): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($blockMsg) ?></div>
<?php endif; ?>

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
                    <option value="" disabled <?= $category === '' ? 'selected' : '' ?>>Select category...</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>" <?= ($category ?? '') === $cat ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($categoryError): ?>
                    <div class="text-danger small mt-1"><?= htmlspecialchars($categoryError) ?></div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label class="form-label">Salary (optional)</label>
                <input type="text" name="salary" class="form-control" placeholder="e.g. 30,000 - 50,000 NPR">
            </div>

            <div class="mb-3">
                <label class="form-label">Application Duration (optional)</label>
                <input type="text" name="application_duration" class="form-control" placeholder="e.g. 30 days">
            </div>

            <div class="mb-3">
                <label class="form-label">Experience Required *</label>
                <select name="experience_level" class="form-select" required>
                    <option value="" disabled selected>Select experience level...</option>
                    <?php foreach ($experienceLevels as $level): ?>
                        <option value="<?= htmlspecialchars($level) ?>" <?= ($experienceLevel ?? '') === $level ? 'selected' : '' ?>>
                            <?= htmlspecialchars($level) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-4">
                <label class="form-label">Description *</label>
                <textarea name="description" class="form-control" rows="6" required></textarea>
            </div>

            <button type="submit" class="btn btn-primary" <?= $canPostJobs ? '' : 'disabled' ?>>Publish Job</button>
            <a href="company-dashboard.php" class="btn btn-outline-secondary ms-2">Cancel</a>
        </form>
    </div>
</div>

<?php require '../footer.php'; ?>
