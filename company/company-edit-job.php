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
    $salary   = trim($_POST['salary'] ?? '');
    $desc     = trim($_POST['description'] ?? '');

    if ($title === '' || $location === '' || $type === '' || $desc === '') {
        $msg = 'All required fields must be filled.';
        $msgType = 'alert-error';
    } else {
        $companyName = $_SESSION['company_name'];
        $update = $conn->prepare(
            "UPDATE jobs SET title = ?, company = ?, location = ?, type = ?, salary = ?, description = ?
             WHERE id = ? AND company_id = ?"
        );
        $update->bind_param("ssssssii", $title, $companyName, $location, $type, $salary, $desc, $jobId, $cid);
        if ($update->execute()) {
            $msg = 'Job updated successfully.';
            $msgType = 'alert-success';
            $job['title'] = $title;
            $job['location'] = $location;
            $job['type'] = $type;
            $job['salary'] = $salary;
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

        <label>Salary (optional)</label>
        <input type="text" name="salary" value="<?php echo htmlspecialchars($job['salary']); ?>">

        <label>Description *</label>
        <textarea name="description" rows="4"><?php echo htmlspecialchars($job['description']); ?></textarea>

        <button type="submit">Update Job</button>
    </form>
</div>
<?php require '../footer.php'; ?>
