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

if (!function_exists('format_posted_time')) {
    function format_posted_time($dateValue)
    {
        $timestamp = strtotime((string) $dateValue);
        if ($timestamp === false) {
            return '';
        }
        $days = (int) floor((time() - $timestamp) / 86400);
        if ($days <= 0) {
            return 'Posted today';
        }
        if ($days === 1) {
            return 'Posted 1 day ago';
        }
        return "Posted {$days} days ago";
    }
}

if (!function_exists('user_has_applied_job')) {
    function user_has_applied_job($conn, $userId, $jobId)
    {
        if ($userId === null || $jobId === null) {
            return false;
        }
        $stmt = $conn->prepare("SELECT 1 FROM applications WHERE user_id = ? AND job_id = ? LIMIT 1");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param("ii", $userId, $jobId);
        $stmt->execute();
        $stmt->store_result();
        $hasApplied = $stmt->num_rows > 0;
        $stmt->close();
        return $hasApplied;
    }
}

$currentUserId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
$alreadyApplied = false;
$formAlert = '';
$formAlertType = '';
if ($currentUserId !== null && user_has_applied_job($conn, $currentUserId, $job_id)) {
    $alreadyApplied = true;
    $formAlert = "You have already applied to this job";
    $formAlertType = 'error';
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
            if (user_has_applied_job($conn, $uid, $job_id)) {
                $alreadyApplied = true;
                $formAlert = "You have already applied to this job";
                $formAlertType = 'error';
            } else {
                $cover = trim($_POST['cover_letter']);
                $companyId = isset($job['company_id']) ? (int) $job['company_id'] : null;
                $stmt = $conn->prepare(
                    "INSERT INTO applications (user_id, job_id, company_id, cover_letter) VALUES (?, ?, ?, ?)"
                );
                $stmt->bind_param("iiis", $uid, $job_id, $companyId, $cover);
                if ($stmt->execute()) {
                    $formAlert = "Application submitted successfully";
                    $formAlertType = 'success';
                    $alreadyApplied = true;
                } else {
                    $formAlert = "Unable to submit your application. Please try again.";
                    $formAlertType = 'error';
                }
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

$postedText = '';
$postedColumns = ['created_at', 'posted_at', 'posted_date'];
foreach ($postedColumns as $column) {
    if (!empty(trim((string) ($job[$column] ?? '')))) {
        $postedText = format_posted_time($job[$column]);
        if ($postedText !== '') {
            break;
        }
    }
}
$deadlineFormatted = '';
$deadlineColumns = ['application_deadline', 'deadline', 'apply_before'];
foreach ($deadlineColumns as $column) {
    if (!empty(trim((string) ($job[$column] ?? '')))) {
        $deadlineTs = strtotime($job[$column]);
        if ($deadlineTs !== false) {
            $deadlineFormatted = date('M d', $deadlineTs);
            break;
        }
    }
}
?>
<h1><?php echo htmlspecialchars($job['title']); ?></h1>
<?php if ($postedText !== ''): ?>
    <p style="color: #6b7280; font-size: 0.9em; margin: 6px 0 0;"><?php echo htmlspecialchars($postedText); ?></p>
<?php endif; ?>
<?php if ($deadlineFormatted !== ''): ?>
    <p style="color: #6b7280; font-size: 0.9em; margin: 2px 0 10px;">Apply before: <?php echo htmlspecialchars($deadlineFormatted); ?></p>
<?php endif; ?>
<div class="card" style="margin-bottom: 30px;">
    <div class="job-meta">
        <?php if (!empty($job['location'])): ?>
            <div class="meta-row" style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
                <span class="meta-label" style="font-weight:600; color:#2c2c2c;">Location</span>
                <span class="meta-value" style="font-weight:400; color:#424242;"><?php echo htmlspecialchars($job['location']); ?></span>
            </div>
        <?php endif; ?>
        <?php if (!empty($job['company'])): ?>
            <div class="meta-row" style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
                <span class="meta-label" style="font-weight:600; color:#2c2c2c;">Company</span>
                <span class="meta-value" style="font-weight:400; color:#424242;"><?php echo htmlspecialchars($job['company']); ?></span>
            </div>
        <?php endif; ?>
        <?php if (!empty($job['type'])): ?>
            <div class="meta-row" style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
                <span class="meta-label" style="font-weight:600; color:#2c2c2c;">Job Type</span>
                <span class="meta-value"><span class="badge"><?php echo htmlspecialchars($job['type']); ?></span></span>
            </div>
        <?php endif; ?>
        <div class="meta-row" style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
            <span class="meta-label" style="font-weight:600; color:#2c2c2c;">Salary</span>
            <span class="meta-value" style="font-weight:400; color:#424242;">
                <?php echo !empty(trim($job['salary'] ?? '')) ? htmlspecialchars($job['salary']) : 'Negotiable'; ?>
            </span>
        </div>
        <?php if (!empty($job['category'])): ?>
            <div class="meta-row" style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
                <span class="meta-label" style="font-weight:600; color:#2c2c2c;">Category</span>
                <span class="meta-value" style="font-weight:400; color:#424242;"><?php echo htmlspecialchars($job['category']); ?></span>
            </div>
        <?php endif; ?>
        <?php if (!empty($job['application_duration'])): ?>
            <div class="meta-row" style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
                <span class="meta-label" style="font-weight:600; color:#2c2c2c;">Application Duration</span>
                <span class="meta-value" style="font-weight:400; color:#424242;"><?php echo htmlspecialchars($job['application_duration']); ?></span>
            </div>
        <?php endif; ?>
    </div>
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
    <?php if ($formAlert !== ''): ?>
        <?php $alertStyle = $formAlertType === 'success'
            ? 'background:#e6f4ea;color:#1b6630;border:1px solid #9cd5a9;font-size:0.9em;padding:10px;border-radius:6px;margin-bottom:12px;'
            : 'background:#ffecec;color:#a30000;border:1px solid #f5c6c6;font-size:0.9em;padding:10px;border-radius:6px;margin-bottom:12px;'; ?>
        <div style="<?php echo $alertStyle; ?>"><?php echo htmlspecialchars($formAlert); ?></div>
    <?php endif; ?>
    <form method="post">
        <label>Cover Letter (optional)</label>
        <textarea name="cover_letter" rows="4"<?php echo $alreadyApplied ? ' disabled' : ''; ?>></textarea>
        <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:18px;">
            <?php if ($alreadyApplied): ?>
                <button type="button" class="btn btn-primary" disabled>Already Applied</button>
            <?php else: ?>
                <button type="submit" name="apply" class="btn btn-primary">Apply Now</button>
            <?php endif; ?>
            <button type="submit" name="bookmark" class="btn btn-secondary">Bookmark</button>
        </div>
    </form>
</div>
<?php else: ?>
<div class="alert alert-error">
    Please <a href="login.php">login</a> to apply or bookmark this job.
</div>
<?php endif; ?>

<?php require 'footer.php'; ?>
