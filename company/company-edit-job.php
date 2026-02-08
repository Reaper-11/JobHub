<?php
// company/company-edit-job.php
require '../db.php';

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
$categoryError = '';
$experienceLevels = [
    'Entry Level (0–1 years)',
    'Junior (1–3 years)',
    'Mid Level (3–5 years)',
    'Senior (5–8 years)',
    'Lead (8–10 years)',
    'Manager (10+ years)',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validate_csrf_token($_POST['csrf_token'] ?? '')) {
    $title = trim($_POST['title'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $salary = trim($_POST['salary'] ?? '');
    $duration = trim($_POST['application_duration'] ?? '');
    $experienceLevel = trim($_POST['experience_level'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($title) || empty($location) || empty($category) || empty($experienceLevel) || empty($description)) {
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
            UPDATE jobs SET 
                title = ?, location = ?, type = ?, category = ?, 
                salary = ?, application_duration = ?, experience_level = ?, description = ?, updated_at = NOW()
            WHERE id = ? AND company_id = ?
        ");
        $stmt->bind_param("ssssssssii", $title, $location, $type, $category, $salary, $duration, $experienceLevel, $description, $jobId, $cid);

        if ($stmt->execute()) {
            $msg = "Job updated successfully!";
            $msg_type = 'success';
            $job = array_merge($job, [
                'title' => $title,
                'location' => $location,
                'type' => $type,
                'category' => $category,
                'salary' => $salary,
                'application_duration' => $duration,
                'experience_level' => $experienceLevel,
                'description' => $description,
            ]);
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
                    <?php
                        $types = ['Full-time', 'Part-time', 'Contract', 'Remote', 'Internship'];
                        $currentType = $job['type'] ?? '';
                    ?>
                    <?php foreach ($types as $t): ?>
                        <option value="<?= htmlspecialchars($t) ?>" <?= $currentType === $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                    <?php endforeach; ?>
                </select>
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
