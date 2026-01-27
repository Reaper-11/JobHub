<?php
require '../db.php';
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit;
}

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
$jobRes = $conn->query("SELECT * FROM jobs WHERE id = $jobId");
$job = $jobRes ? $jobRes->fetch_assoc() : null;
if (!$job) {
    header("Location: admin-jobs.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_job'])) {
    $title = trim($_POST['title'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $salary = trim($_POST['salary'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $isApproved = isset($_POST['is_approved']) ? 1 : 0;

    if ($title === '' || $company === '' || $location === '' || $type === '' || $desc === '') {
        $msg = "All required fields must be filled.";
    } else {
        $salaryVal = $salary === '' ? null : $salary;
        $stmt = $conn->prepare(
            "UPDATE jobs SET title = ?, company = ?, location = ?, type = ?, salary = ?, description = ?, is_approved = ? WHERE id = ?"
        );
        $stmt->bind_param("ssssssii", $title, $company, $location, $type, $salaryVal, $desc, $isApproved, $jobId);
        if ($stmt->execute()) {
            $msg = "Job updated successfully.";
            $jobRes = $conn->query("SELECT * FROM jobs WHERE id = $jobId");
            $job = $jobRes ? $jobRes->fetch_assoc() : $job;
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
                <input type="text" class="form-control" name="salary" value="<?php echo htmlspecialchars($job['salary']); ?>">
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
