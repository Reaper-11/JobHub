<?php
require '../db.php';
if (!isset($_SESSION['company_id'])) {
    header("Location: company-login.php");
    exit;
}
$cid = (int) $_SESSION['company_id'];
$jobId = (int) ($_GET['id'] ?? 0);
$msg = '';
$msgType = 'alert-success';
$jobCategories = [
    "IT & Software",
    "Marketing",
    "Sales",
    "Finance",
    "Design",
    "Education",
    "Healthcare",
    "Engineering",
    "Part-Time",
    "Internship",
];

$stmt = $conn->prepare("SELECT * FROM jobs WHERE id = ? AND company_id = ? LIMIT 1");
$stmt->bind_param("ii", $jobId, $cid);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$job) {
    header("Location: company-dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title    = trim($_POST['title'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $type     = trim($_POST['type'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $salary   = trim($_POST['salary'] ?? '');
    $duration = trim($_POST['application_duration'] ?? '');
    $desc     = trim($_POST['description'] ?? '');

    if ($title === '' || $location === '' || $type === '' || $category === '' || $desc === '') {
        $msg = 'All required fields must be filled.';
        $msgType = 'alert-error';
    } elseif (!in_array($category, $jobCategories, true)) {
        $msg = 'Invalid job category selected.';
        $msgType = 'alert-error';
    } else {
        $companyName = $_SESSION['company_name'];
        $update = $conn->prepare(
            "UPDATE jobs SET title = ?, company = ?, location = ?, type = ?, category = ?, salary = ?, application_duration = ?, description = ?
             WHERE id = ? AND company_id = ?"
        );
        $update->bind_param("ssssssssii", $title, $companyName, $location, $type, $category, $salary, $duration, $desc, $jobId, $cid);
        if ($update->execute()) {
            $msg = 'Job updated successfully.';
            $msgType = 'alert-success';
            $job['title'] = $title;
            $job['location'] = $location;
            $job['type'] = $type;
            $job['category'] = $category;
            $job['salary'] = $salary;
            $job['application_duration'] = $duration;
            $job['description'] = $desc;
        } else {
            $msg = 'Could not update job. Please try again.';
            $msgType = 'alert-error';
        }
        $update->close();
    }
}

$basePath = '../';
require '../header.php';
?>
<h1>Edit Job</h1>
<p><a href="company-dashboard.php">&laquo; Back to Dashboard</a></p>
<?php if ($msg): ?>
    <div class="alert <?php echo $msgType; ?>"><?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>
<div class="form-card">
    <form method="post">
        <label>Job Title *</label>
        <input type="text" name="title" value="<?php echo htmlspecialchars($job['title']); ?>">

        <label>Location *</label>
        <input type="text" name="location" value="<?php echo htmlspecialchars($job['location']); ?>">

        <label>Job Type *</label>
        <select name="type">
            <?php
            $types = ['Full-time', 'Part-time', 'Internship', 'Remote'];
            foreach ($types as $t):
            ?>
                <option value="<?php echo $t; ?>" <?php echo $job['type'] === $t ? 'selected' : ''; ?>>
                    <?php echo $t; ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Job Category *</label>
        <select name="category" required>
            <option value="">Select category</option>
            <?php foreach ($jobCategories as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat); ?>"
                    <?php echo ($job['category'] ?? '') === $cat ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cat); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Salary (optional)</label>
        <input type="text" name="salary" value="<?php echo htmlspecialchars($job['salary']); ?>">

        <label>Application Duration (optional)</label>
        <input type="text" name="application_duration" value="<?php echo htmlspecialchars($job['application_duration'] ?? ''); ?>">

        <label>Description *</label>
        <textarea name="description" rows="4"><?php echo htmlspecialchars($job['description']); ?></textarea>

        <button type="submit">Update Job</button>
    </form>
</div>
<?php require '../footer.php'; ?>




