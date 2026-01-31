<?php
// my-application-edit.php
require 'db.php';
require 'header.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: my-applications.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$app_id  = (int)$_GET['id'];

$stmt = $conn->prepare("
    SELECT a.*, j.title, j.company, j.location
    FROM applications a
    JOIN jobs j ON a.job_id = j.id
    WHERE a.id = ? AND a.user_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $app_id, $user_id);
$stmt->execute();
$application = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$application) {
    header("Location: my-applications.php");
    exit;
}

$msg = '';
$msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = "Invalid request.";
        $msg_type = 'danger';
    } else {
        $cover = trim($_POST['cover_letter'] ?? '');
        $stmt = $conn->prepare("UPDATE applications SET cover_letter = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param("sii", $cover, $app_id, $user_id);
        if ($stmt->execute()) {
            $msg = "Cover letter updated successfully.";
            $application['cover_letter'] = $cover;
        } else {
            $msg = "Failed to update cover letter.";
            $msg_type = 'danger';
        }
        $stmt->close();
    }
}
?>

<h1 class="mb-4">Edit Application</h1>

<a href="my-applications.php" class="btn btn-outline-secondary mb-4">← Back to My Applications</a>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h5 class="mb-3">Job Information</h5>
        <p><strong>Title:</strong> <?= htmlspecialchars($application['title']) ?></p>
        <p><strong>Company:</strong> <?= htmlspecialchars($application['company']) ?></p>
        <p><strong>Location:</strong> <?= htmlspecialchars($application['location']) ?></p>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

            <div class="mb-3">
                <label class="form-label">Cover Letter</label>
                <textarea name="cover_letter" class="form-control" rows="8"><?= htmlspecialchars($application['cover_letter'] ?? '') ?></textarea>
                <div class="form-text">Maximum 2000 characters recommended</div>
            </div>

            <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
    </div>
</div>

<?php require 'footer.php'; ?>