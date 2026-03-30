<?php
// company/company-add-job.php
require '../db.php';
require_once '../includes/company_verification_helper.php';
require_once '../includes/recommendation.php';

if (!isset($_SESSION['company_id'])) {
    header("Location: company-login.php");
    exit;
}

if (!function_exists('company_add_job_string_length')) {
    function company_add_job_string_length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }
}

if (!function_exists('company_add_job_input_class')) {
    function company_add_job_input_class(array $errors, string $field, string $baseClass): string
    {
        return $baseClass . (isset($errors[$field]) ? ' is-invalid' : '');
    }
}

$cid = (int) $_SESSION['company_id'];
$msg = '';
$msg_type = '';
$submitState = '';
$errors = [];
$categories = require __DIR__ . '/../includes/categories.php';
$jobTypes = require __DIR__ . '/../includes/job_types.php';
$experienceLevels = require __DIR__ . '/../includes/experience_levels.php';

$defaultFormData = [
    'job_title' => '',
    'location' => '',
    'job_type' => 'Full-time',
    'category' => '',
    'salary' => '',
    'application_duration' => '',
    'experience_required' => '',
    'description' => '',
    'skills_required' => '',
];
$formData = $defaultFormData;

$hasSkillsRequiredColumn = false;
$checkSkillsRequired = $conn->query("SHOW COLUMNS FROM jobs LIKE 'skills_required'");
if ($checkSkillsRequired) {
    $hasSkillsRequiredColumn = $checkSkillsRequired->num_rows > 0;
    $checkSkillsRequired->close();
}

$hasDeadlineColumn = false;
$checkDeadline = $conn->query("SHOW COLUMNS FROM jobs LIKE 'deadline'");
if ($checkDeadline) {
    $hasDeadlineColumn = $checkDeadline->num_rows > 0;
    $checkDeadline->close();
}

$statusStmt = $conn->prepare("
    SELECT name, is_approved, operational_state, restriction_reason, verification_status
    FROM companies
    WHERE id = ?
    LIMIT 1
");
$companyStatus = [
    'name' => $_SESSION['company_name'] ?? 'Company',
    'is_approved' => 0,
    'operational_state' => 'active',
    'restriction_reason' => null,
    'verification_status' => null,
];
if ($statusStmt) {
    $statusStmt->bind_param("i", $cid);
    $statusStmt->execute();
    $companyStatus = $statusStmt->get_result()->fetch_assoc() ?: $companyStatus;
    $statusStmt->close();
}

$companyName = trim((string) ($companyStatus['name'] ?? ($_SESSION['company_name'] ?? 'Company')));
if ($companyName === '') {
    $companyName = 'Company';
}

$isApproved = (int) ($companyStatus['is_approved'] ?? 0) === 1;
$operationalState = $companyStatus['operational_state'] ?? 'active';
$restrictionReason = trim((string) ($companyStatus['restriction_reason'] ?? ''));
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
if ($blockMsg !== '' && $restrictionReason !== '') {
    $blockMsg .= " Reason: " . $restrictionReason;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_job'])) {
    $formData = [
        'job_title' => trim((string) ($_POST['job_title'] ?? '')),
        'location' => trim((string) ($_POST['location'] ?? '')),
        'job_type' => trim((string) ($_POST['job_type'] ?? '')),
        'category' => trim((string) ($_POST['category'] ?? '')),
        'salary' => trim((string) ($_POST['salary'] ?? '')),
        'application_duration' => trim((string) ($_POST['application_duration'] ?? '')),
        'experience_required' => trim((string) ($_POST['experience_required'] ?? '')),
        'description' => trim((string) ($_POST['description'] ?? '')),
        'skills_required' => recommend_normalize_skill_string($_POST['skills_required'] ?? ''),
    ];

    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors['form'] = "Invalid request. Please refresh the page and try again.";
    }

    if (!$canPostJobs && $blockMsg === '') {
        $errors['form'] = "Your company is not allowed to post jobs at this time.";
    }

    $titleLength = company_add_job_string_length($formData['job_title']);
    if ($formData['job_title'] === '') {
        $errors['job_title'] = "Job title is required.";
    } elseif ($titleLength < 3 || $titleLength > 150) {
        $errors['job_title'] = "Job title must be between 3 and 150 characters.";
    }

    if ($formData['location'] === '') {
        $errors['location'] = "Location is required.";
    } elseif (
        !preg_match("/^(?=.*[\p{L}])[\p{L}\s,.'-]+$/u", $formData['location']) ||
        preg_match('/^\d+$/', $formData['location'])
    ) {
        $errors['location'] = "Location must contain letters and cannot be numeric only.";
    } elseif (company_add_job_string_length($formData['location']) > 200) {
        $errors['location'] = "Location must be 200 characters or fewer.";
    }

    if ($formData['job_type'] === '') {
        $errors['job_type'] = "Job type is required.";
    } elseif (!in_array($formData['job_type'], $jobTypes, true)) {
        $errors['job_type'] = "Please select a valid job type.";
    }

    if ($formData['category'] === '') {
        $errors['category'] = "Category is required.";
    } elseif (!in_array($formData['category'], $categories, true)) {
        $errors['category'] = "Please select a valid category.";
    }

    if ($formData['salary'] !== '') {
        if (!is_numeric($formData['salary'])) {
            $errors['salary'] = "Salary must be a numeric value.";
        } elseif ((float) $formData['salary'] < 0) {
            $errors['salary'] = "Salary cannot be negative.";
        }
    }

    if ($formData['application_duration'] !== '') {
        $durationValue = filter_var($formData['application_duration'], FILTER_VALIDATE_INT);
        if ($durationValue === false) {
            $errors['application_duration'] = "Application duration must be a whole number.";
        } elseif ($durationValue < 1 || $durationValue > 365) {
            $errors['application_duration'] = "Application duration must be between 1 and 365 days.";
        }
    }

    if ($formData['experience_required'] === '') {
        $errors['experience_required'] = "Experience required is required.";
    } elseif (!in_array($formData['experience_required'], $experienceLevels, true)) {
        $errors['experience_required'] = "Please select a valid experience level.";
    }

    $descriptionLength = company_add_job_string_length($formData['description']);
    if ($formData['description'] === '') {
        $errors['description'] = "Description is required.";
    } elseif ($descriptionLength < 20) {
        $errors['description'] = "Description must be at least 20 characters.";
    }

    if (!empty($errors)) {
        $msg = "Validation failed. Please fix the errors below.";
        $msg_type = 'danger';
        $submitState = 'validation_failed';
    } else {
        $salaryValue = null;
        if ($formData['salary'] !== '') {
            $salaryValue = rtrim(rtrim(number_format((float) $formData['salary'], 2, '.', ''), '0'), '.');
            if ($salaryValue === '') {
                $salaryValue = '0';
            }
        }

        $applicationDurationValue = null;
        $deadlineValue = null;
        if ($formData['application_duration'] !== '') {
            $durationDays = (int) $formData['application_duration'];
            $applicationDurationValue = $durationDays . ' days';
            if ($hasDeadlineColumn) {
                $deadlineValue = date('Y-m-d', strtotime('+' . $durationDays . ' days'));
            }
        }

        $insertColumns = [
            'company_id',
            'company',
            'title',
            'location',
            'type',
            'category',
            'salary',
            'application_duration',
            'experience_level',
            'description',
            'status',
            'is_approved',
            'created_at',
        ];
        $insertValues = [
            '?',
            '?',
            '?',
            '?',
            '?',
            '?',
            '?',
            '?',
            '?',
            '?',
            "'active'",
            '0',
            'NOW()',
        ];
        $insertTypes = 'isssssssss';
        $insertParams = [
            $cid,
            $companyName,
            $formData['job_title'],
            $formData['location'],
            $formData['job_type'],
            $formData['category'],
            $salaryValue,
            $applicationDurationValue,
            $formData['experience_required'],
            $formData['description'],
        ];

        if ($hasSkillsRequiredColumn) {
            $skillsRequiredValue = $formData['skills_required'] !== '' ? $formData['skills_required'] : null;
            $insertColumns[] = 'skills_required';
            $insertValues[] = '?';
            $insertTypes .= 's';
            $insertParams[] = $skillsRequiredValue;
        }

        if ($hasDeadlineColumn) {
            $insertColumns[] = 'deadline';
            $insertValues[] = '?';
            $insertTypes .= 's';
            $insertParams[] = $deadlineValue;
        }

        $insertSql = "
            INSERT INTO jobs (" . implode(', ', $insertColumns) . ")
            VALUES (" . implode(', ', $insertValues) . ")
        ";

        $stmt = $conn->prepare($insertSql);
        if (!$stmt) {
            error_log("company/company-add-job.php prepare failed for company {$cid}: " . $conn->error);
            $msg = "The job could not be saved because of a database error. Please try again.";
            $msg_type = 'danger';
            $submitState = 'sql_failed';
        } else {
            $stmt->bind_param($insertTypes, ...$insertParams);
            if ($stmt->execute()) {
                $jobId = (int) $conn->insert_id;
                $msg = "Job submitted successfully and is awaiting admin approval.";
                $msg_type = 'success';
                $submitState = 'success';
                $postedTitle = $formData['job_title'];
                $formData = $defaultFormData;

                log_activity(
                    $conn,
                    $cid,
                    'company',
                    'job_posted',
                    "Company posted a new job: {$postedTitle}",
                    'job',
                    $jobId
                );
            } else {
                error_log("company/company-add-job.php insert failed for company {$cid}: " . $stmt->error);
                $msg = "The job could not be saved because of a database error. Please try again.";
                $msg_type = 'danger';
                $submitState = 'sql_failed';
            }
            $stmt->close();
        }
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
    <div class="alert alert-<?= $msg_type ?>">
        <?= htmlspecialchars($msg) ?>
        <?php if ($submitState === 'validation_failed' && !empty($errors)): ?>
            <ul class="mb-0 mt-2 ps-3">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" action="company-add-job.php">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

            <div class="mb-3">
                <label class="form-label">Job Title *</label>
                <input
                    type="text"
                    name="job_title"
                    class="<?= htmlspecialchars(company_add_job_input_class($errors, 'job_title', 'form-control')) ?>"
                    required
                    minlength="3"
                    maxlength="150"
                    value="<?= htmlspecialchars($formData['job_title']) ?>"
                >
                <?php if (isset($errors['job_title'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['job_title']) ?></div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label class="form-label">Location *</label>
                <input
                    type="text"
                    name="location"
                    class="<?= htmlspecialchars(company_add_job_input_class($errors, 'location', 'form-control')) ?>"
                    required
                    placeholder="Kathmandu, Nepal"
                    value="<?= htmlspecialchars($formData['location']) ?>"
                >
                <?php if (isset($errors['location'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['location']) ?></div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label class="form-label">Job Type *</label>
                <select
                    name="job_type"
                    class="<?= htmlspecialchars(company_add_job_input_class($errors, 'job_type', 'form-select')) ?>"
                    required
                >
                    <?php foreach ($jobTypes as $jobType): ?>
                        <option value="<?= htmlspecialchars($jobType) ?>" <?= $formData['job_type'] === $jobType ? 'selected' : '' ?>>
                            <?= htmlspecialchars($jobType) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['job_type'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['job_type']) ?></div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label class="form-label">Category *</label>
                <select
                    name="category"
                    class="<?= htmlspecialchars(company_add_job_input_class($errors, 'category', 'form-select')) ?>"
                    required
                >
                    <option value="" disabled <?= $formData['category'] === '' ? 'selected' : '' ?>>Select category...</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>" <?= $formData['category'] === $cat ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['category'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['category']) ?></div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label class="form-label">Salary (optional)</label>
                <input
                    type="number"
                    name="salary"
                    class="<?= htmlspecialchars(company_add_job_input_class($errors, 'salary', 'form-control')) ?>"
                    min="0"
                    step="0.01"
                    value="<?= htmlspecialchars($formData['salary']) ?>"
                >
                <?php if (isset($errors['salary'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['salary']) ?></div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label class="form-label">Application Duration (optional)</label>
                <input
                    type="number"
                    name="application_duration"
                    class="<?= htmlspecialchars(company_add_job_input_class($errors, 'application_duration', 'form-control')) ?>"
                    min="1"
                    max="365"
                    step="1"
                    value="<?= htmlspecialchars($formData['application_duration']) ?>"
                >
                <?php if (isset($errors['application_duration'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['application_duration']) ?></div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label class="form-label">Experience Required *</label>
                <select
                    name="experience_required"
                    class="<?= htmlspecialchars(company_add_job_input_class($errors, 'experience_required', 'form-select')) ?>"
                    required
                >
                    <option value="" disabled <?= $formData['experience_required'] === '' ? 'selected' : '' ?>>Select experience level...</option>
                    <?php foreach ($experienceLevels as $level): ?>
                        <option value="<?= htmlspecialchars($level) ?>" <?= $formData['experience_required'] === $level ? 'selected' : '' ?>>
                            <?= htmlspecialchars($level) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['experience_required'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['experience_required']) ?></div>
                <?php endif; ?>
            </div>

            <div class="mb-4">
                <label class="form-label">Required Skills (optional)</label>
                <textarea name="skills_required" class="form-control" rows="3" placeholder="Laravel, PHP, MySQL, REST API"><?= htmlspecialchars($formData['skills_required']) ?></textarea>
                <div class="form-text">Enter comma-separated skills to improve job recommendations.</div>
            </div>

            <div class="mb-4">
                <label class="form-label">Description *</label>
                <textarea
                    name="description"
                    class="<?= htmlspecialchars(company_add_job_input_class($errors, 'description', 'form-control')) ?>"
                    rows="6"
                    required
                    minlength="20"
                ><?= htmlspecialchars($formData['description']) ?></textarea>
                <?php if (isset($errors['description'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['description']) ?></div>
                <?php endif; ?>
            </div>

            <button type="submit" name="submit_job" class="btn btn-primary" <?= $canPostJobs ? '' : 'disabled' ?>>Publish Job</button>
            <a href="company-dashboard.php" class="btn btn-outline-secondary ms-2">Cancel</a>
        </form>
    </div>
</div>

<?php require '../footer.php'; ?>
