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
$salaryPeriods = jobhub_salary_period_options();
$salaryStorageColumns = jobhub_salary_storage_columns($conn);
$categoryError = '';
$experienceError = '';
$jobTypeError = '';
$durationError = '';
$salaryMinError = '';
$salaryMaxError = '';
$salaryPeriodError = '';
$hasSkillsRequiredColumn = false;
$isVerified = true;
$deadlineColumn = job_deadline_column($conn);
$salaryFormData = jobhub_salary_form_values_from_job($job);

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
        $duration = trim((string)($_POST['application_duration'] ?? ''));
        $experienceLevel = trim((string)($_POST['experience_level'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $skillsRequired = recommend_normalize_skill_string($_POST['skills_required'] ?? '');
        $legacySalary = trim((string) ($_POST['salary_legacy_original'] ?? ($salaryFormData['legacy_salary'] ?? '')));
        $salaryValidation = jobhub_salary_validate_submission($_POST, $legacySalary);
        $salaryFormData = [
            'salary_min' => (string) ($salaryValidation['salary_min_input'] ?? ''),
            'salary_max' => (string) ($salaryValidation['salary_max_input'] ?? ''),
            'salary_period' => (string) ($salaryValidation['salary_period_input'] ?? jobhub_salary_default_period()),
            'salary_currency' => (string) ($salaryValidation['salary_currency'] ?? jobhub_salary_default_currency()),
            'legacy_salary' => $legacySalary,
        ];
        $salaryMinError = (string) (($salaryValidation['errors']['salary_min'] ?? ''));
        $salaryMaxError = (string) (($salaryValidation['errors']['salary_max'] ?? ''));
        $salaryPeriodError = (string) (($salaryValidation['errors']['salary_period'] ?? ''));

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

        if ($msg === '' && !empty($salaryValidation['errors'])) {
            $msg = "Please correct the salary fields below.";
            $msg_type = 'danger';
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
                    admin_remarks = NULL, status = 'pending', updated_at = NOW()
            ";
            $salaryValue = $salaryValidation['salary_text'] ?? null;
            $bindTypes = "ssssssss";
            $bindParams = [
                $title,
                $location,
                $type,
                $category,
                $salaryValue,
                $duration,
                $experienceLevel,
                $description,
            ];

            if ($hasSkillsRequiredColumn) {
                $updateSql .= ", skills_required = ?";
                $bindTypes .= "s";
                $bindParams[] = $skillsRequired;
            }

            foreach (['salary_min', 'salary_max', 'salary_period', 'salary_currency'] as $salaryColumn) {
                if (!empty($salaryStorageColumns[$salaryColumn])) {
                    $updateSql .= ", {$salaryColumn} = ?";
                    $bindTypes .= "s";
                    $bindParams[] = $salaryValidation[$salaryColumn] ?? null;
                }
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
                <label class="form-label">Salary</label>
                <?php if (($salaryFormData['legacy_salary'] ?? '') !== ''): ?>
                    <div class="form-text mb-2">
                        Current saved salary text: <?= htmlspecialchars($salaryFormData['legacy_salary']) ?>.
                        Leave the fields below blank to keep it, or enter a new salary range to replace it.
                    </div>
                <?php endif; ?>
                <input type="hidden" name="salary_legacy_original" value="<?= htmlspecialchars($salaryFormData['legacy_salary'] ?? '') ?>">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Minimum Salary</label>
                        <input
                            type="number"
                            name="salary_min"
                            class="form-control<?= $salaryMinError !== '' ? ' is-invalid' : '' ?>"
                            min="1"
                            step="1"
                            inputmode="numeric"
                            placeholder="40000"
                            onkeydown="if (['e', 'E', '+', '-', '.'].includes(event.key)) { event.preventDefault(); }"
                            oninput="this.value = this.value.replace(/[^0-9]/g, '');"
                            value="<?= htmlspecialchars($salaryFormData['salary_min'] ?? '') ?>"
                        >
                        <?php if ($salaryMinError !== ''): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($salaryMinError) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Maximum Salary</label>
                        <input
                            type="number"
                            name="salary_max"
                            class="form-control<?= $salaryMaxError !== '' ? ' is-invalid' : '' ?>"
                            min="1"
                            step="1"
                            inputmode="numeric"
                            placeholder="70000"
                            onkeydown="if (['e', 'E', '+', '-', '.'].includes(event.key)) { event.preventDefault(); }"
                            oninput="this.value = this.value.replace(/[^0-9]/g, '');"
                            value="<?= htmlspecialchars($salaryFormData['salary_max'] ?? '') ?>"
                        >
                        <?php if ($salaryMaxError !== ''): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($salaryMaxError) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Salary Period</label>
                        <select name="salary_period" class="form-select<?= $salaryPeriodError !== '' ? ' is-invalid' : '' ?>">
                            <?php foreach ($salaryPeriods as $periodValue => $periodLabel): ?>
                                <option value="<?= htmlspecialchars($periodValue) ?>" <?= ($salaryFormData['salary_period'] ?? jobhub_salary_default_period()) === $periodValue ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($periodLabel) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($salaryPeriodError !== ''): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($salaryPeriodError) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-text">Example display: <?= htmlspecialchars(jobhub_salary_format_text('40000', '70000', 'month', 'NPR')) ?></div>
            </div>

            <div class="mb-3">
                <label class="form-label">Application Duration</label>
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
