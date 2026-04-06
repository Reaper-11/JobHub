<?php
require '../db.php';
require_role('admin');

$editingDisabled = true;
if ($editingDisabled) {
    header("Location: admin-jobs.php");
    exit;
}

$jobId = (int) ($_GET['id'] ?? 0);
if ($jobId <= 0) {
    header("Location: admin-jobs.php");
    exit;
}

$msg = "";
$salaryPeriods = jobhub_salary_period_options();
$salaryStorageColumns = jobhub_salary_storage_columns($conn);
$salaryMinError = '';
$salaryMaxError = '';
$salaryPeriodError = '';
$jobRes = $conn->query("SELECT * FROM jobs WHERE id = $jobId");
$job = $jobRes ? $jobRes->fetch_assoc() : null;
if (!$job) {
    header("Location: admin-jobs.php");
    exit;
}
$salaryFormData = jobhub_salary_form_values_from_job($job);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_job'])) {
    $title = trim($_POST['title'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $isApproved = isset($_POST['is_approved']) ? 1 : 0;
    $legacySalary = trim((string) ($_POST['salary_legacy_original'] ?? ($salaryFormData['legacy_salary'] ?? '')));
    $salaryValidation = jobhub_salary_validate_submission($_POST, $legacySalary);
    $salaryFormData = [
        'salary_min' => (string) ($salaryValidation['salary_min_input'] ?? ''),
        'salary_max' => (string) ($salaryValidation['salary_max_input'] ?? ''),
        'salary_period' => (string) ($salaryValidation['salary_period_input'] ?? jobhub_salary_default_period()),
        'salary_currency' => (string) ($salaryValidation['salary_currency'] ?? jobhub_salary_default_currency()),
        'legacy_salary' => $legacySalary,
    ];
    $salaryMinError = (string) ($salaryValidation['errors']['salary_min'] ?? '');
    $salaryMaxError = (string) ($salaryValidation['errors']['salary_max'] ?? '');
    $salaryPeriodError = (string) ($salaryValidation['errors']['salary_period'] ?? '');

    if ($title === '' || $company === '' || $location === '' || $type === '' || $desc === '') {
        $msg = "All required fields must be filled.";
    } elseif (!empty($salaryValidation['errors'])) {
        $msg = "Please correct the salary fields below.";
    } else {
        $salaryVal = $salaryValidation['salary_text'] ?? null;
        $sql = "UPDATE jobs SET title = ?, company = ?, location = ?, type = ?, salary = ?, description = ?, is_approved = ?";
        $types = "ssssssi";
        $params = [$title, $company, $location, $type, $salaryVal, $desc, $isApproved];

        foreach (['salary_min', 'salary_max', 'salary_period', 'salary_currency'] as $salaryColumn) {
            if (!empty($salaryStorageColumns[$salaryColumn])) {
                $sql .= ", {$salaryColumn} = ?";
                $types .= 's';
                $params[] = $salaryValidation[$salaryColumn] ?? null;
            }
        }

        $sql .= " WHERE id = ?";
        $types .= 'i';
        $params[] = $jobId;

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) {
            $msg = "Job updated successfully.";
            $jobRes = $conn->query("SELECT * FROM jobs WHERE id = $jobId");
            $job = $jobRes ? $jobRes->fetch_assoc() : $job;
            $salaryFormData = jobhub_salary_form_values_from_job($job);
        } else {
            $msg = "Error updating job.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Job - JobHub</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../custom.css?v=<?php echo filemtime(__DIR__ . '/../custom.css'); ?>">
</head>
<body>
<main class="container py-4">
    <h1 class="mb-2">Edit Job</h1>
    <p><a class="link-primary text-decoration-none" href="admin-jobs.php">&laquo; Back to Jobs</a></p>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
        <form method="post">
            <input type="hidden" name="update_job" value="1">
            <div class="mb-3">
                <label class="form-label">Job Title</label>
                <input type="text" class="form-control" name="title" value="<?php echo htmlspecialchars($job['title']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Company</label>
                <input type="text" class="form-control" name="company" value="<?php echo htmlspecialchars($job['company']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Location</label>
                <input type="text" class="form-control" name="location" value="<?php echo htmlspecialchars($job['location']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Job Type</label>
                <select name="type" class="form-select" required>
                <?php
                $types = ['Full-time', 'Part-time', 'Internship', 'Remote'];
                foreach ($types as $t):
                ?>
                    <option value="<?php echo $t; ?>" <?php echo $job['type'] === $t ? 'selected' : ''; ?>>
                        <?php echo $t; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Salary (optional)</label>
                <?php if (($salaryFormData['legacy_salary'] ?? '') !== ''): ?>
                    <div class="form-text mb-2">
                        Current saved salary text: <?php echo htmlspecialchars($salaryFormData['legacy_salary']); ?>.
                        Leave the fields below blank to keep it, or enter a new salary range to replace it.
                    </div>
                <?php endif; ?>
                <input type="hidden" name="salary_legacy_original" value="<?php echo htmlspecialchars($salaryFormData['legacy_salary'] ?? ''); ?>">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Minimum Salary</label>
                        <input type="number" class="form-control<?php echo $salaryMinError !== '' ? ' is-invalid' : ''; ?>" name="salary_min" min="1" step="0.01" inputmode="decimal" value="<?php echo htmlspecialchars($salaryFormData['salary_min'] ?? ''); ?>">
                        <?php if ($salaryMinError !== ''): ?><div class="invalid-feedback"><?php echo htmlspecialchars($salaryMinError); ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Maximum Salary</label>
                        <input type="number" class="form-control<?php echo $salaryMaxError !== '' ? ' is-invalid' : ''; ?>" name="salary_max" min="1" step="0.01" inputmode="decimal" value="<?php echo htmlspecialchars($salaryFormData['salary_max'] ?? ''); ?>">
                        <?php if ($salaryMaxError !== ''): ?><div class="invalid-feedback"><?php echo htmlspecialchars($salaryMaxError); ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Salary Period</label>
                        <select name="salary_period" class="form-select<?php echo $salaryPeriodError !== '' ? ' is-invalid' : ''; ?>">
                            <?php foreach ($salaryPeriods as $periodValue => $periodLabel): ?>
                                <option value="<?php echo htmlspecialchars($periodValue); ?>" <?php echo (($salaryFormData['salary_period'] ?? jobhub_salary_default_period()) === $periodValue) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($periodLabel); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($salaryPeriodError !== ''): ?><div class="invalid-feedback"><?php echo htmlspecialchars($salaryPeriodError); ?></div><?php endif; ?>
                    </div>
                </div>
                <div class="form-text">Example display: <?php echo htmlspecialchars(jobhub_salary_format_text('40000', '70000', 'month', 'NPR')); ?></div>
            </div>
            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="4" required><?php echo htmlspecialchars($job['description']); ?></textarea>
            </div>
            <div class="form-check mb-3">
                <input type="checkbox" class="form-check-input" name="is_approved" value="1" id="job-approved" <?php echo $job['is_approved'] ? 'checked' : ''; ?>>
                <label class="form-check-label" for="job-approved">Approved</label>
            </div>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
        </div>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
