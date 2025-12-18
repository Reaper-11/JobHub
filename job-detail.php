<?php
require 'db.php';

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}
$job_id = (int) $_GET['id'];
$jobRes = $conn->query("SELECT * FROM jobs WHERE id=$job_id AND is_approved=1");
if ($jobRes->num_rows == 0) {
    die("Job not found or not approved.");
}
$job = $jobRes->fetch_assoc();

$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $uid = (int) $_SESSION['user_id'];

    if (isset($_POST['apply'])) {
        $cover = trim($_POST['cover_letter']);
        $stmt = $conn->prepare("INSERT INTO applications (user_id, job_id, cover_letter) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $uid, $job_id, $cover);
        if ($stmt->execute()) {
            $msg = "Application submitted successfully.";
        } else {
            $msg = "You may have already applied or error occurred.";
        }
    }

    if (isset($_POST['bookmark'])) {
        $stmt = $conn->prepare("INSERT IGNORE INTO bookmarks (user_id, job_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $uid, $job_id);
        if ($stmt->execute()) {
            $msg = "Job bookmarked.";
        } else {
            $msg = "Error bookmarking job.";
        }
    }
}

require 'header.php';
?>
<h1><?php echo htmlspecialchars($job['title']); ?></h1>
<div class="card">
    <p class="meta">
        <?php echo htmlspecialchars($job['company']); ?> |
        <?php echo htmlspecialchars($job['location']); ?>
        <span class="badge"><?php echo htmlspecialchars($job['type']); ?></span>
    </p>
    <?php if (!empty($job['salary'])): ?>
        <p class="meta"><strong>Salary:</strong> <?php echo htmlspecialchars($job['salary']); ?></p>
    <?php endif; ?>
    <p><?php echo nl2br(htmlspecialchars($job['description'])); ?></p>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>

<?php if (isset($_SESSION['user_id'])): ?>
<div class="form-card">
    <h3>Apply for this job</h3>
    <form method="post">
        <label>Cover Letter (optional)</label>
        <textarea name="cover_letter" rows="4"></textarea>
        <button type="submit" name="apply">Apply Now</button>
        <button type="submit" name="bookmark" class="btn-secondary">Bookmark</button>
    </form>
</div>
<?php else: ?>
<div class="alert alert-error">
    Please <a href="login.php">login</a> to apply or bookmark this job.
</div>
<?php endif; ?>

<?php require 'footer.php'; ?>
