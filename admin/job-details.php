<?php
require '../db.php';
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit;
}

$jobId = (int) ($_GET['id'] ?? 0);
if ($jobId <= 0) {
    header("Location: admin-jobs.php");
    exit;
}

$stmt = $conn->prepare("SELECT * FROM jobs WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $jobId);
$stmt->execute();
$result = $stmt->get_result();
$job = $result ? $result->fetch_assoc() : null;
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Job Details - Admin</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
<main class="container">
    <h1>Job Details</h1>
    <p><a href="admin-jobs.php">&laquo; Back to Jobs</a></p>

    <?php if (!$job): ?>
        <div class="alert alert-error">Job not found.</div>
    <?php else: ?>
        <div class="card">
            <h2><?php echo htmlspecialchars($job['title']); ?></h2>
            <p class="meta">
                <?php echo htmlspecialchars($job['company']); ?> |
                <?php echo htmlspecialchars($job['location']); ?>
                <?php if (!empty($job['type'])): ?>
                    <span class="badge"><?php echo htmlspecialchars($job['type']); ?></span>
                <?php endif; ?>
            </p>
            <?php if (!empty($job['created_at'])): ?>
                <p class="meta"><strong>Posted Date:</strong> <?php echo htmlspecialchars($job['created_at']); ?></p>
            <?php endif; ?>
            <?php if (!empty($job['deadline'])): ?>
                <p class="meta"><strong>Deadline:</strong> <?php echo htmlspecialchars($job['deadline']); ?></p>
            <?php endif; ?>
            <?php if (!empty($job['application_duration'])): ?>
                <p class="meta"><strong>Application Duration:</strong> <?php echo htmlspecialchars($job['application_duration']); ?></p>
            <?php endif; ?>
            <?php if (!empty($job['description'])): ?>
                <p><?php echo nl2br(htmlspecialchars($job['description'])); ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
