<?php
// company/company-edit-job.php
require '../db.php';
require_once '../includes/company_verification_helper.php';
require_once '../includes/recommendation.php';

require_role('company');

$cid = current_company_id() ?? 0;
$jobId = (int)($_GET['id'] ?? 0);

update_expired_jobs($conn, $cid, $jobId > 0 ? $jobId : null);

$jobStmt = $conn->prepare("SELECT * FROM jobs WHERE id = ? AND company_id = ? LIMIT 1");
$jobStmt->bind_param("ii", $jobId, $cid);
$jobStmt->execute();
$job = $jobStmt->get_result()->fetch_assoc();
$jobStmt->close();

if (!$job) {
    header("Location: company-dashboard.php");
    exit;
}

$msg = '';
$msg_type = '';
$categories = require __DIR__ . '/../includes/categories.php';
$jobTypes = require __DIR__ . '/../includes/job_types.php';
$experienceLevels = require __DIR__ . '/../includes/experience_levels.php';
$categoryError = '';
$experienceError = '';
$jobTypeError = '';
$durationError = '';
$hasSkillsRequiredColumn = false;
$isVerified = true;
$deadlineColumn = job_deadline_column($conn);

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = "Invalid request. Please refresh the page and try again.";
        $msg_type = 'danger';
    } else {
        $title = trim((string)($_POST['title'] ?? ''));
        $location = trim((string)($_POST['location'] ?? ''));
        $type = trim((string)($_POST['type'] ?? ''));
        $category = trim((string)($_POST['category'] ?? ''));
        $salary = trim((string)($_POST['salary'] ?? ''));
        $duration = trim((string)($_POST['application_duration'] ?? ''));
        $experienceLevel = trim((string)($_POST['experience_level'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $skillsRequired = recommend_normalize_skill_string($_POST['skills_required'] ?? '');

        if ($title === '' || $location === '' || $category === '' || $experienceLevel === '' || $description === '') {
            $msg = "Required fields are missing.";
            $msg_type = 'danger';
            if ($category === '') {
                $categoryError = "Please select a category.";
            }
            if ($experienceLevel === '') {
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
            $normalizedDuration = strtolower($duration);
            if ($duration !== '') {
                $durationTimestamp = job_expiration_timestamp(job_reference_datetime($job), $duration);
                if ($normalizedDuration !== 'ongoing' && $durationTimestamp === null) {
                    $msg = "Please provide a valid application duration.";
                    $msg_type = 'danger';
                    $durationError = "Use a value like 30, 30 days, 2 weeks, or leave it blank.";
                }
            }
        }

        if ($msg === '') {
            $deadlineValue = null;
            if ($deadlineColumn !== null && $duration !== '' && $normalizedDuration !== 'ongoing') {
                $deadlineTimestamp = job_expiration_timestamp(job_reference_datetime($job), $duration);
                if ($deadlineTimestamp !== null) {
                    $deadlineValue = date('Y-m-d', $deadlineTimestamp);
                }
            }

            $updateSql = "
                UPDATE jobs
                SET title = ?, location = ?, type = ?, category = ?, salary = ?, application_duration = ?,
                    experience_level = ?, description = ?, is_approved = 0, approved_by = NULL, approved_at = NULL,
                    admin_remarks = NULL, updated_at = NOW()
            ";
            $bindTypes = "ssssssss";
            $bindParams = [
                $title,
                $location,
                $type,
                $category,
                $salary,
                $duration,
                $experienceLevel,
                $description,
            ];

            if ($hasSkillsRequiredColumn) {
                $updateSql .= ", skills_required = ?";
                $bindTypes .= "s";
                $bindParams[] = $skillsRequired;
            }

            if ($deadlineColumn !== null) {
                $updateSql .= ", {$deadlineColumn} = ?";
                $bindTypes .= "s";
                $bindParams[] = $deadlineValue;
            }

            $updateSql .= " WHERE id = ? AND company_id = ?";
            $bindTypes .= "ii";
            $bindParams[] = $jobId;
            $bindParams[] = $cid;

            $stmt = $conn->prepare($updateSql);
            if ($stmt) {
                $stmt->bind_param($bindTypes, ...$bindParams);

                if ($stmt->execute()) {
                    update_expired_jobs($conn, $cid, $jobId);

                    $reloadStmt = $conn->prepare("SELECT * FROM jobs WHERE id = ? AND company_id = ? LIMIT 1");
                    $reloadStmt->bind_param("ii", $jobId, $cid);
                    $reloadStmt->execute();
                    $job = $reloadStmt->get_result()->fetch_assoc() ?: $job;
                    $reloadStmt->close();

                    $msg = "Job updated successfully and resubmitted for admin approval.";
                    $msg_type = 'success';

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
            } else {
                $msg = "Update failed.";
                $msg_type = 'danger';
            }
        }
    }
}

$effectiveStatus = job_effective_status($job);
?>

<?php require 'company-header.php'; ?>

<h1 class="mb-4">
    Edit Job: <?= htmlspecialchars($job['title']) ?>
    <span class="badge <?= job_status_badge_class($job) ?> ms-2"><?= htmlspecialchars(job_status_label($job)) ?></span>
</h1>

<?php if (!$isVerified): ?>
    <div class="alert alert-warning">
        Your company is not yet verification-approved. Editing existing jobs is still allowed, but you cannot post new jobs until admin approves your company verification.
        <a href="company-verification.php" class="alert-link">Open verification page</a>
    </div>
<?php endif; ?>

<?php if ($effectiveStatus === 'expired'): ?>
    <div class="alert alert-secondary">
        This job is expired. Editing it will not make it active again automatically.
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
                <?php if ($durationError): ?>
                    <div class="text-danger small mt-1"><?= htmlspecialchars($durationError) ?></div>
                <?php endif; ?>
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
