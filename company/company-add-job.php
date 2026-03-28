<?php
// company/company-add-job.php
require '../db.php';
require_once '../includes/company_verification_helper.php';
require_once '../includes/recommendation.php';

if (!isset($_SESSION['company_id'])) {
    header("Location: company-login.php");
    exit;
}

$cid = (int)$_SESSION['company_id'];
$msg = $msg_type = '';
$categoryError = '';
$experienceError = '';
$jobTypeError = '';
$categories = require __DIR__ . '/../includes/categories.php';
$jobTypes = require __DIR__ . '/../includes/job_types.php';
$category = '';
$experienceLevel = '';
$title = '';
$location = '';
$type = 'Full-time';
$salary = '';
$duration = '';
$description = '';
$skillsRequired = '';
$experienceLevels = require __DIR__ . '/../includes/experience_levels.php';
$hasSkillsRequiredColumn = false;

$checkSkillsRequired = $conn->query("SHOW COLUMNS FROM jobs LIKE 'skills_required'");
if ($checkSkillsRequired) {
    $hasSkillsRequiredColumn = $checkSkillsRequired->num_rows > 0;
    $checkSkillsRequired->close();
}

$statusStmt = $conn->prepare("
    SELECT is_approved, operational_state, restriction_reason, verification_status
    FROM companies
    WHERE id = ?
");
$statusStmt->bind_param("i", $cid);
$statusStmt->execute();
$companyStatus = $statusStmt->get_result()->fetch_assoc() ?? [
    'is_approved' => 0,
    'operational_state' => 'active',
    'restriction_reason' => null,
    'verification_status' => null,
];
$statusStmt->close();

$isApproved = (int)($companyStatus['is_approved'] ?? 0) === 1;
$operationalState = $companyStatus['operational_state'] ?? 'active';
$restrictionReason = $companyStatus['restriction_reason'] ?? '';
$verificationStatus = get_company_verification_status($companyStatus);
$isVerified = is_company_verified($companyStatus);
$canPostJobs = $isApproved && $operationalState === 'active' && $isVerified;

$blockMsg = '';
if (!$isApproved) {
    $blockMsg = "Your company is not approved yet. You cannot post jobs until approval.";
} elseif (!$isVerified) {
    $blockMsg = "Your company account must be verified by admin before posting jobs.";
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
    $skillsRequired = recommend_normalize_skill_string($_POST['skills_required'] ?? '');

    if (!$canPostJobs) {
        $msg = $blockMsg !== '' ? $blockMsg : "Your company is not allowed to post jobs at this time.";
        $msg_type = 'danger';
    } elseif (empty($title) || empty($location) || empty($category) || empty($experienceLevel) || empty($description)) {
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
                INSERT INTO jobs (company_id, title, location, type, category, salary, application_duration, experience_level, skills_required, description, status, is_approved, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', 0, NOW())
            ");
            $stmt->bind_param("isssssssss", $cid, $title, $location, $type, $category, $salary, $duration, $experienceLevel, $skillsRequired, $description);
        } else {
            $stmt = $conn->prepare("
                INSERT INTO jobs (company_id, title, location, type, category, salary, application_duration, experience_level, description, status, is_approved, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', 0, NOW())
            ");
            $stmt->bind_param("issssssss", $cid, $title, $location, $type, $category, $salary, $duration, $experienceLevel, $description);
        }

        if ($stmt->execute()) {
            $jobId = (int)$conn->insert_id;
            $msg = "Job submitted successfully and is awaiting admin approval.";
            $msg_type = 'success';
            $skillsRequired = '';
            log_activity(
                $conn,
                $cid,
                'company',
                'job_posted',
                "Company posted a new job: {$title}",
                'job',
                $jobId
            );
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
    <div class="alert alert-danger">
        <?= htmlspecialchars($blockMsg) ?>
        <?php if (!$isVerified): ?>
            <br><a href="company-verification.php" class="alert-link">Submit company verification</a>
        <?php endif; ?>
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
                <input type="text" name="title" class="form-control" required value="<?= htmlspecialchars($title) ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Location *</label>
                <input type="text" name="location" class="form-control" required placeholder="Kathmandu, Nepal" value="<?= htmlspecialchars($location) ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Job Type</label>
                <select name="type" class="form-select">
                    <?php foreach ($jobTypes as $jobType): ?>
                        <option value="<?= htmlspecialchars($jobType) ?>" <?= $type === $jobType ? 'selected' : '' ?>>
                            <?= htmlspecialchars($jobType) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($jobTypeError): ?>
                    <div class="text-danger small mt-1"><?= htmlspecialchars($jobTypeError) ?></div>
                <?php endif; ?>
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
                <input type="text" name="salary" class="form-control" placeholder="e.g. 30,000 - 50,000 NPR" value="<?= htmlspecialchars($salary) ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Application Duration (optional)</label>
                <input type="text" name="application_duration" class="form-control" placeholder="e.g. 30 days" value="<?= htmlspecialchars($duration) ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Experience Required *</label>
                <select name="experience_level" class="form-select" required>
                    <option value="" disabled <?= $experienceLevel === '' ? 'selected' : '' ?>>Select experience level...</option>
                    <?php foreach ($experienceLevels as $level): ?>
                        <option value="<?= htmlspecialchars($level) ?>" <?= ($experienceLevel ?? '') === $level ? 'selected' : '' ?>>
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
                <textarea name="skills_required" class="form-control" rows="3" placeholder="Laravel, PHP, MySQL, REST API"><?= htmlspecialchars($skillsRequired) ?></textarea>
                <div class="form-text">Enter comma-separated skills to improve job recommendations.</div>
            </div>

            <div class="mb-4">
                <label class="form-label">Description *</label>
                <textarea name="description" class="form-control" rows="6" required><?= htmlspecialchars($description) ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary" <?= $canPostJobs ? '' : 'disabled' ?>>Publish Job</button>
            <a href="company-dashboard.php" class="btn btn-outline-secondary ms-2">Cancel</a>
        </form>
    </div>
</div>

<?php require '../footer.php'; ?>
