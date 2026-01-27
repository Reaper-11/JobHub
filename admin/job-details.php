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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Details - Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../custom.css?v=<?php echo filemtime(__DIR__ . '/../custom.css'); ?>">
</head>
<body>
<main class="container py-4">
    <h1 class="mb-2">Job Details</h1>
    <p><a class="link-primary text-decoration-none" href="admin-jobs.php">&laquo; Back to Jobs</a></p>

    <?php if (!$job): ?>
        <div class="alert alert-danger">Job not found.</div>
    <?php else: ?>
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h4"><?php echo htmlspecialchars($job['title']); ?></h2>
                <p class="text-muted">
                    <?php echo htmlspecialchars($job['company']); ?> |
                    <?php echo htmlspecialchars($job['location']); ?>
                    <?php if (!empty($job['type'])): ?>
                        <span class="badge text-bg-warning ms-2"><?php echo htmlspecialchars($job['type']); ?></span>
                    <?php endif; ?>
                </p>
                <?php if (!empty($job['created_at'])): ?>
                    <p class="text-muted"><strong>Posted Date:</strong> <?php echo htmlspecialchars($job['created_at']); ?></p>
                <?php endif; ?>
                <?php if (!empty($job['deadline'])): ?>
                    <p class="text-muted"><strong>Deadline:</strong> <?php echo htmlspecialchars($job['deadline']); ?></p>
                <?php endif; ?>
                <?php if (!empty($job['application_duration'])): ?>
                    <p class="text-muted"><strong>Application Duration:</strong> <?php echo htmlspecialchars($job['application_duration']); ?></p>
                <?php endif; ?>
                <?php if (!empty($job['description'])): ?>
                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($job['description'])); ?></p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
