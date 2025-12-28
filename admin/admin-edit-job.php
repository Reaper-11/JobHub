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
    <title>Edit Job - JobHub</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
<main class="container">
    <h1>Edit Job</h1>
    <p><a href="admin-jobs.php">&laquo; Back to Jobs</a></p>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

    <div class="form-card">
        <form method="post">
            <input type="hidden" name="update_job" value="1">
            <label>Job Title</label>
            <input type="text" name="title" value="<?php echo htmlspecialchars($job['title']); ?>" required>
            <label>Company</label>
            <input type="text" name="company" value="<?php echo htmlspecialchars($job['company']); ?>" required>
            <label>Location</label>
            <input type="text" name="location" value="<?php echo htmlspecialchars($job['location']); ?>" required>
            <label>Job Type</label>
            <select name="type" required>
                <?php
                $types = ['Full-time', 'Part-time', 'Internship', 'Remote'];
                foreach ($types as $t):
                ?>
                    <option value="<?php echo $t; ?>" <?php echo $job['type'] === $t ? 'selected' : ''; ?>>
                        <?php echo $t; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <label>Salary (optional)</label>
            <input type="text" name="salary" value="<?php echo htmlspecialchars($job['salary']); ?>">
            <label>Description</label>
            <textarea name="description" rows="4" required><?php echo htmlspecialchars($job['description']); ?></textarea>
            <label>
                <input type="checkbox" name="is_approved" value="1" <?php echo $job['is_approved'] ? 'checked' : ''; ?>>
                Approved
            </label>
            <button type="submit">Save Changes</button>
        </form>
    </div>
</main>
</body>
</html>
