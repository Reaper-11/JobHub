<?php
require '../db.php';
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit;
}

$applicationId = (int) ($_GET['id'] ?? 0);
$invalidId = $applicationId <= 0;

$stmt = $conn->prepare(
    "SELECT a.*, u.name AS user_name, u.email AS user_email, j.title AS job_title,
            j.company AS job_company, j.location AS job_location, j.type AS job_type,
            j.created_at AS job_posted_at, u.cv_path AS user_cv_path
     FROM applications a
     JOIN users u ON u.id = a.user_id
     JOIN jobs j ON j.id = a.job_id
     WHERE a.id = ?
     LIMIT 1"
);
$application = null;
if (!$invalidId) {
    $stmt->bind_param("i", $applicationId);
    $stmt->execute();
    $result = $stmt->get_result();
    $application = $result ? $result->fetch_assoc() : null;
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Application Details - Admin</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
<main class="container">
    <h1>Application Details</h1>
    <p><a href="admin-applications.php">&laquo; Back to Applications</a></p>

    <?php if ($invalidId): ?>
        <div class="alert alert-danger">Invalid application ID.</div>
    <?php elseif (!$application): ?>
        <div class="alert alert-danger">Application not found.</div>
    <?php else: ?>
        <div class="card">
            <h3>Job Details</h3>
            <p><strong>Job Title:</strong> <?php echo htmlspecialchars($application['job_title']); ?></p>
            <p><strong>Company Name:</strong> <?php echo htmlspecialchars($application['job_company']); ?></p>
            <p><strong>Location:</strong> <?php echo htmlspecialchars($application['job_location']); ?></p>
            <?php if (!empty($application['job_type'])): ?>
                <p><strong>Job Type:</strong> <?php echo htmlspecialchars($application['job_type']); ?></p>
            <?php endif; ?>
            <?php if (!empty($application['job_posted_at'])): ?>
                <p><strong>Posted Date:</strong> <?php echo htmlspecialchars($application['job_posted_at']); ?></p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>User Details</h3>
            <p><strong>User Name:</strong> <?php echo htmlspecialchars($application['user_name']); ?></p>
            <p><strong>User Email:</strong> <?php echo htmlspecialchars($application['user_email']); ?></p>
            <p><strong>Applied Date:</strong> <?php echo htmlspecialchars($application['applied_at']); ?></p>
            <p><strong>Application Status:</strong> <?php echo htmlspecialchars(ucfirst($application['status'] ?? 'pending')); ?></p>
        </div>

        <div class="card">
            <h3>Message / Cover Letter</h3>
            <?php if (!empty($application['cover_letter'])): ?>
                <p><?php echo nl2br(htmlspecialchars($application['cover_letter'])); ?></p>
            <?php else: ?>
                <p>Not provided.</p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>CV</h3>
            <?php
            $cvPath = $application['user_cv_path'] ?? '';
            $safeCv = '';
            if ($cvPath !== '' && strpos($cvPath, 'uploads/cv/') === 0) {
                $cvFile = basename($cvPath);
                if (strtolower(pathinfo($cvFile, PATHINFO_EXTENSION)) === 'pdf') {
                    $safeCv = '../uploads/cv/' . $cvFile;
                }
            }
            ?>
            <?php if ($safeCv !== ''): ?>
                <a class="btn btn-secondary btn-small" href="<?php echo htmlspecialchars($safeCv); ?>" target="_blank" rel="noopener">
                    Open CV
                </a>
            <?php else: ?>
                <p>CV not uploaded.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
