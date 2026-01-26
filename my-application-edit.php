<?php
require 'db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$uid = (int) $_SESSION['user_id'];
$appId = (int) ($_GET['id'] ?? 0);
$msg = '';
$msgType = 'alert-success';

$stmt = $conn->prepare(
    "SELECT a.*, j.title, j.company, j.location
     FROM applications a
     JOIN jobs j ON j.id = a.job_id
     WHERE a.id = ? AND a.user_id = ?
     LIMIT 1"
);
$stmt->bind_param("ii", $appId, $uid);
$stmt->execute();
$application = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$application) {
    header("Location: my-applications.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cover = trim($_POST['cover_letter'] ?? '');
    $update = $conn->prepare("UPDATE applications SET cover_letter = ? WHERE id = ? AND user_id = ?");
    $update->bind_param("sii", $cover, $appId, $uid);
    if ($update->execute()) {
        $msg = "Application updated.";
        $msgType = "alert-success";
        $application['cover_letter'] = $cover;
    } else {
        $msg = "Could not update application.";
        $msgType = "alert-danger";
    }
    $update->close();
}

require 'header.php';
?>
<h1 class="mb-2">Edit Application</h1>
<p><a class="link-primary text-decoration-none" href="my-applications.php">&laquo; Back to My Applications</a></p>
<?php if ($msg): ?>
    <div class="alert <?php echo $msgType; ?>"><?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>
<div class="card shadow-sm mb-3">
    <div class="card-body">
        <p class="mb-1"><strong>Job:</strong> <?php echo htmlspecialchars($application['title']); ?></p>
        <p class="mb-1"><strong>Company:</strong> <?php echo htmlspecialchars($application['company']); ?></p>
        <p class="mb-0"><strong>Location:</strong> <?php echo htmlspecialchars($application['location']); ?></p>
    </div>
</div>
<div class="card shadow-sm">
    <div class="card-body">
        <form method="post">
            <label class="form-label">Cover Letter</label>
            <textarea name="cover_letter" class="form-control mb-3" rows="6"><?php echo htmlspecialchars($application['cover_letter'] ?? ''); ?></textarea>
            <button type="submit" class="btn btn-primary">Update Application</button>
        </form>
    </div>
</div>
<?php require 'footer.php'; ?>
