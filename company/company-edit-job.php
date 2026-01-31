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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validate_csrf_token($_POST['csrf_token'] ?? '')) {
    $title = trim($_POST['title'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $salary = trim($_POST['salary'] ?? '');
    $duration = trim($_POST['application_duration'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($title) || empty($description)) {
        $msg = "Title and description are required.";
        $msg_type = 'danger';
    } else {
        $stmt = $conn->prepare("
            UPDATE jobs SET 
                title = ?, location = ?, type = ?, category = ?, 
                salary = ?, application_duration = ?, description = ?, updated_at = NOW()
            WHERE id = ? AND company_id = ?
        ");
        $stmt->bind_param("ssssssiii", $title, $location, $type, $category, $salary, $duration, $description, $jobId, $cid);

        if ($stmt->execute()) {
            $msg = "Job updated successfully!";
            $msg_type = 'success';
            $job = array_merge($job, $_POST); // refresh view
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

            <!-- Same form fields as add-job.php, pre-filled with $job values -->
            <!-- ... copy fields from company-add-job.php and use value="<?= htmlspecialchars($job['field']) ?>" ... -->

            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="company-dashboard.php" class="btn btn-outline-secondary ms-2">Cancel</a>
        </form>
    </div>
</div>

<?php require '../footer.php'; ?>