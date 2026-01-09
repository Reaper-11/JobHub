<?php
require 'db.php';

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}
$job_id = (int) $_GET['id'];
$jobRes = $conn->query(
    "SELECT j.* FROM jobs j
     LEFT JOIN companies c ON c.id = j.company_id
     WHERE j.id=$job_id AND (j.company_id IS NULL OR c.is_approved = 1)"
);
if ($jobRes->num_rows == 0) {
    die("Job not found.");
}
$job = $jobRes->fetch_assoc();
$isExpired = is_job_expired($job);
$isClosed = is_job_closed($job);
if ($viewStmt = $conn->prepare("UPDATE jobs SET views = views + 1 WHERE id = ?")) {
    $viewStmt->bind_param("i", $job_id);
    $viewStmt->execute();
    $viewStmt->close();
}

$msg = "";
$msgType = "alert-success";
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'already_bookmarked') {
        $msg = "Already bookmarked";
        $msgType = "alert-error";
    } elseif ($_GET['msg'] === 'bookmarked') {
        $msg = "Job bookmarked successfully";
        $msgType = "alert-success";
    }
}

function add_msg_to_url($url, $msg)
{
    $fragment = '';
    $hashPos = strpos($url, '#');
    if ($hashPos !== false) {
        $fragment = substr($url, $hashPos);
        $url = substr($url, 0, $hashPos);
    }
    $separator = (strpos($url, '?') === false) ? '?' : '&';
    return $url . $separator . 'msg=' . rawurlencode($msg) . $fragment;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    if ($isExpired || $isClosed) {
        $msg = $isClosed
            ? "This job is closed and is no longer accepting applications."
            : "This job has expired and is no longer accepting applications.";
        $msgType = "alert-error";
    } else {
        $uid = (int) $_SESSION['user_id'];

        if (isset($_POST['apply'])) {
            $cover = trim($_POST['cover_letter']);
            $companyId = isset($job['company_id']) ? (int) $job['company_id'] : null;
            $stmt = $conn->prepare(
                "INSERT INTO applications (user_id, job_id, company_id, cover_letter) VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param("iiis", $uid, $job_id, $companyId, $cover);
            if ($stmt->execute()) {
                $msg = "Application submitted successfully.";
            } else {
                $msg = "You may have already applied or error occurred.";
            }
        }

        if (isset($_POST['bookmark'])) {
            $returnUrl = $_SERVER['HTTP_REFERER'] ?? 'index.php';
            $checkStmt = $conn->prepare("SELECT 1 FROM bookmarks WHERE user_id = ? AND job_id = ? LIMIT 1");
            $checkStmt->bind_param("ii", $uid, $job_id);
            $checkStmt->execute();
            $checkStmt->store_result();
            if ($checkStmt->num_rows > 0) {
                $checkStmt->close();
                header("Location: " . add_msg_to_url($returnUrl, "already_bookmarked"));
                exit;
            }
            $checkStmt->close();

            $stmt = $conn->prepare("INSERT INTO bookmarks (user_id, job_id, created_at) VALUES (?, ?, NOW())");
            $stmt->bind_param("ii", $uid, $job_id);
            if ($stmt->execute()) {
                header("Location: " . add_msg_to_url($returnUrl, "bookmarked"));
                exit;
            }
            $msg = "Error bookmarking job.";
            $msgType = "alert-error";
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
    <?php if (!empty($job['category'])): ?>
        <p class="meta"><strong>Category:</strong> <?php echo htmlspecialchars($job['category']); ?></p>
    <?php endif; ?>
    <?php if (!empty($job['application_duration'])): ?>
        <p class="meta"><strong>Application Duration:</strong> <?php echo htmlspecialchars($job['application_duration']); ?></p>
    <?php endif; ?>
    <p><?php echo nl2br(htmlspecialchars($job['description'])); ?></p>
</div>

<?php if ($msg): ?>
    <div class="alert <?php echo $msgType; ?>"><?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>

<?php if ($isExpired || $isClosed): ?>
<div class="alert alert-error">
    <?php echo $isClosed
        ? "This job is closed and is no longer accepting applications."
        : "This job has expired and is no longer accepting applications."; ?>
</div>
<?php elseif (isset($_SESSION['user_id'])): ?>
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
