<?php
// company/company-edit-job.php
require '../db.php';
require_once '../includes/company_verification_helper.php';
require_once '../includes/recommendation.php';

if (!isset($_SESSION['company_id'])) {
    header("Location: company-login.php");
    exit;
}

$cid = (int)$_SESSION['company_id'];
$jobId = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM jobs WHERE id = ? AND company_id = ? LIMIT 1");
$stmt->bind_param("ii", $jobId, $cid);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$job) {
    header("Location: company-dashboard.php");
    exit;
}

$msg = $msg_type = '';
$categories = require __DIR__ . '/../includes/categories.php';
$jobTypes = require __DIR__ . '/../includes/job_types.php';
$categoryError = '';
$experienceError = '';
$jobTypeError = '';
$experienceLevels = require __DIR__ . '/../includes/experience_levels.php';
$hasSkillsRequiredColumn = false;
$isVerified = true;

$statusStmt = $conn->prepare("
    SELECT is_approved, operational_state, restriction_reason, verification_status
    FROM companies
    WHERE id = ?
");
if ($statusStmt) {
    $statusStmt->bind_param("i", $cid);
    $statusStmt->execute();
    $companyStatus = $statusStmt->get_result()->fetch_assoc() ?? [
        'is_approved' => 0,
        'operational_state' => 'active',
        'restriction_reason' => null,
        'verification_status' => null,
    ];
    $statusStmt->close();
    $isVerified = is_company_verified($companyStatus);
}

$checkSkillsRequired = $conn->query("SHOW COLUMNS FROM jobs LIKE 'skills_required'");
if ($checkSkillsRequired) {
    $hasSkillsRequiredColumn = $checkSkillsRequired->num_rows > 0;
    $checkSkillsRequired->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validate_csrf_token($_POST['csrf_token'] ?? '')) {
    $title = trim($_POST['title'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $salary = trim($_POST['salary'] ?? '');
    $duration = trim($_POST['application_duration'] ?? '');
    $experienceLevel = trim($_POST['experience_level'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $skillsRequired = recommend_normalize_skill_string($_POST['skills_required'] ?? '');

    if (empty($title) || empty($location) || empty($category) || empty($experienceLevel) || empty($description)) {
        $msg = "Required fields are missing.";
        $msg_type = 'danger';
        if (empty($category)) {
            $categoryError = "Please select a category.";
        }
        if (empty($experienceLevel)) {
            $experienceError = "Please select an experience level.";
        }
    } elseif (!in_array($experienceLevel, $experienceLevels, true)) {
        $msg = "Please select a valid experience level.";
        $msg_type = 'danger';
        $experienceError = "Invalid experience level selected.";
    } elseif (!in_array($type, $jobTypes, true)) {
        $msg = "Please select a valid job type.";
        $msg_type = 'danger';
        $jobTypeError = "Invalid job type selected.";
    } elseif (!in_array($category, $categories, true)) {
        $msg = "Please correct the errors below.";
        $msg_type = 'danger';
        $categoryError = "Invalid category selected.";
    } else {
        if ($hasSkillsRequiredColumn) {
            $stmt = $conn->prepare("
                UPDATE jobs SET 
                    title = ?, location = ?, type = ?, category = ?, 
                    salary = ?, application_duration = ?, experience_level = ?, skills_required = ?, description = ?, is_approved = 0, approved_by = NULL, approved_at = NULL, admin_remarks = NULL, updated_at = NOW()
                WHERE id = ? AND company_id = ?
            ");
            $stmt->bind_param("sssssssssii", $title, $location, $type, $category, $salary, $duration, $experienceLevel, $skillsRequired, $description, $jobId, $cid);
        } else {
            $stmt = $conn->prepare("
                UPDATE jobs SET 
                    title = ?, location = ?, type = ?, category = ?, 
                    salary = ?, application_duration = ?, experience_level = ?, description = ?, is_approved = 0, approved_by = NULL, approved_at = NULL, admin_remarks = NULL, updated_at = NOW()
                WHERE id = ? AND company_id = ?
            ");
            $stmt->bind_param("ssssssssii", $title, $location, $type, $category, $salary, $duration, $experienceLevel, $description, $jobId, $cid);
        }

        if ($stmt->execute()) {
            $msg = "Job updated successfully and resubmitted for admin approval.";
            $msg_type = 'success';
            $job = array_merge($job, [
                'title' => $title,
                'location' => $location,
                'type' => $type,
                'category' => $category,
                'salary' => $salary,
                'application_duration' => $duration,
                'experience_level' => $experienceLevel,
                'skills_required' => $skillsRequired,
                'description' => $description,
                'is_approved' => 0,
                'admin_remarks' => null,
            ]);
            log_activity(
                $conn,
                $cid,
                'company',
                'job_updated',
                "Company updated job: {$title}",
                'job',
                $jobId
            );
        } else {
            $msg = "Update failed.";
            $msg_type = 'danger';
        }
        $stmt->close();
    }
}
?>

<?php require 'company-header.php'; ?>

<h1 class="mb-4">Edit Job: <?= htmlspecialchars($job['title']) ?></h1>

<?php if (!$isVerified): ?>
    <div class="alert alert-warning">
        Your company is not yet verification-approved. Editing existing jobs is still allowed, but you cannot post new jobs until admin approves your company verification.
        <a href="company-verification.php" class="alert-link">Open verification page</a>
    </div>
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
                <input type="text" name="title" class="form-control" required value="<?= htmlspecialchars($job['title'] ?? '') ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Location *</label>
                <input type="text" name="location" class="form-control" required placeholder="Kathmandu, Nepal" value="<?= htmlspecialchars($job['location'] ?? '') ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Job Type</label>
                <select name="type" class="form-select">
                    <?php $currentType = $job['type'] ?? ''; ?>
                    <?php foreach ($jobTypes as $t): ?>
                        <option value="<?= htmlspecialchars($t) ?>" <?= $currentType === $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($jobTypeError): ?>
                    <div class="text-danger small mt-1"><?= htmlspecialchars($jobTypeError) ?></div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label class="form-label">Category *</label>
                <select name="category" class="form-select" required>
                    <option value="" disabled <?= empty($job['category']) ? 'selected' : '' ?>>Select category...</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>" <?= ($job['category'] ?? '') === $cat ? 'selected' : '' ?>>
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
                <input type="text" name="salary" class="form-control" placeholder="e.g. 30,000 - 50,000 NPR" value="<?= htmlspecialchars($job['salary'] ?? '') ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Application Duration (optional)</label>
                <input type="text" name="application_duration" class="form-control" placeholder="e.g. 30 days" value="<?= htmlspecialchars($job['application_duration'] ?? '') ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Experience Required *</label>
                <select name="experience_level" class="form-select" required>
                    <option value="" disabled <?= empty($job['experience_level']) ? 'selected' : '' ?>>Select experience level...</option>
                    <?php foreach ($experienceLevels as $level): ?>
                        <option value="<?= htmlspecialchars($level) ?>" <?= ($job['experience_level'] ?? '') === $level ? 'selected' : '' ?>>
                            <?= htmlspecialchars($level) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($experienceError): ?>
                    <div class="text-danger small mt-1"><?= htmlspecialchars($experienceError) ?></div>
                <?php endif; ?>
            </div>

            <div class="mb-4">
                <label class="form-label">Required Skills (optional)</label>
                <textarea name="skills_required" class="form-control" rows="3" placeholder="Laravel, PHP, MySQL, REST API"><?= htmlspecialchars($job['skills_required'] ?? '') ?></textarea>
                <div class="form-text">Enter comma-separated skills to improve job recommendations.</div>
            </div>

            <div class="mb-4">
                <label class="form-label">Description *</label>
                <textarea name="description" class="form-control" rows="6" required><?= htmlspecialchars($job['description'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="company-dashboard.php" class="btn btn-outline-secondary ms-2">Cancel</a>
        </form>
    </div>
</div>

<?php require '../footer.php'; ?>
