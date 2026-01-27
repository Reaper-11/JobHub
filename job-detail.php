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
$jobStatus = strtolower((string) ($job['status'] ?? ''));
$isClosed = $jobStatus === 'closed';
$isInactive = $jobStatus !== '' && $jobStatus !== 'active';
$deadlineTs = job_expiration_timestamp($job['created_at'] ?? '', $job['application_duration'] ?? '');
$isExpired = $deadlineTs !== null && time() > $deadlineTs;
if ($isExpired) {
    $isInactive = true;
}

$deadlineValue = '';
foreach (['application_deadline', 'deadline', 'apply_before'] as $column) {
    if (!empty(trim((string) ($job[$column] ?? '')))) {
        $deadlineValue = $job[$column];
        break;
    }
}
$deadlineValueTs = $deadlineValue !== '' ? strtotime($deadlineValue) : false;

incrementJobView($conn, $job_id);

$msg = "";
$msgType = "alert-success";
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'already_bookmarked') {
        $msg = "Already bookmarked";
        $msgType = "alert-danger";
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
    if ($isInactive) {
        if ($isExpired) {
            $msg = "Application deadline has expired.";
        } elseif ($isClosed) {
            $msg = "This job is closed and is no longer accepting applications.";
        } else {
            $msg = "This job is no longer accepting applications.";
        }
        $msgType = "alert-danger";
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
                $conn->begin_transaction();
                $stmt = $conn->prepare(
                    "INSERT INTO applications (user_id, job_id, company_id, cover_letter) VALUES (?, ?, ?, ?)"
                );
                $stmt->bind_param("iiis", $uid, $job_id, $companyId, $cover);
                $insertOk = $stmt->execute();
                $stmt->close();

                $updateOk = false;
                if ($insertOk) {
                    $updateStmt = $conn->prepare(
                        "UPDATE jobs SET application_count = application_count + 1 WHERE id = ?"
                    );
                    $updateStmt->bind_param("i", $job_id);
                    $updateOk = $updateStmt->execute();
                    $updateStmt->close();
                }

                if ($insertOk && $updateOk) {
                    $conn->commit();
                    $formAlert = "Application submitted successfully";
                    $formAlertType = 'success';
                    $alreadyApplied = true;
                } else {
                    $conn->rollback();
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
            $msgType = "alert-danger";
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
if ($deadlineTs !== null) {
    $deadlineFormatted = date('M d', $deadlineTs);
} elseif ($deadlineValueTs !== false) {
    $deadlineFormatted = date('M d', $deadlineValueTs);
}
?>
<h1 class="mb-2">
    <?php echo htmlspecialchars($job['title']); ?>
    <span class="badge <?php echo $isExpired ? 'text-bg-danger' : 'text-bg-success'; ?> ms-2">
        <?php echo $isExpired ? 'Expired' : 'Open'; ?>
    </span>
</h1>
<?php if ($postedText !== ''): ?>
    <p class="text-muted mb-1"><?php echo htmlspecialchars($postedText); ?></p>
<?php endif; ?>
<?php if ($deadlineFormatted !== ''): ?>
    <p class="text-muted">Apply before: <?php echo htmlspecialchars($deadlineFormatted); ?></p>
<?php endif; ?>
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <dl class="row mb-3">
            <?php if (!empty($job['location'])): ?>
                <dt class="col-sm-4 text-muted">Location</dt>
                <dd class="col-sm-8"><?php echo htmlspecialchars($job['location']); ?></dd>
            <?php endif; ?>
            <?php if (!empty($job['company'])): ?>
                <dt class="col-sm-4 text-muted">Company</dt>
                <dd class="col-sm-8"><?php echo htmlspecialchars($job['company']); ?></dd>
            <?php endif; ?>
            <?php if (!empty($job['type'])): ?>
                <dt class="col-sm-4 text-muted">Job Type</dt>
                <dd class="col-sm-8"><span class="badge text-bg-warning"><?php echo htmlspecialchars($job['type']); ?></span></dd>
            <?php endif; ?>
            <dt class="col-sm-4 text-muted">Salary</dt>
            <dd class="col-sm-8">
                <?php echo !empty(trim($job['salary'] ?? '')) ? htmlspecialchars($job['salary']) : 'Negotiable'; ?>
            </dd>
            <?php if (!empty($job['category'])): ?>
                <dt class="col-sm-4 text-muted">Category</dt>
                <dd class="col-sm-8"><?php echo htmlspecialchars($job['category']); ?></dd>
            <?php endif; ?>
            <?php if (!empty($job['application_duration'])): ?>
                <dt class="col-sm-4 text-muted">Application Duration</dt>
                <dd class="col-sm-8"><?php echo htmlspecialchars($job['application_duration']); ?></dd>
            <?php endif; ?>
        </dl>
        <p class="mb-0"><?php echo nl2br(htmlspecialchars($job['description'])); ?></p>
    </div>
</div>

<?php if ($msg): ?>
    <div class="alert <?php echo $msgType; ?>"><?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>

<?php if ($isInactive): ?>
<div class="alert alert-warning">
    <?php
    if ($isExpired) {
        echo "Application deadline has expired.";
    } elseif ($isClosed) {
        echo "This job is closed and is no longer accepting applications.";
    } else {
        echo "This job is no longer accepting applications.";
    }
    ?>
</div>
<?php elseif (isset($_SESSION['user_id'])): ?>
<div class="card shadow-sm">
    <div class="card-body">
    <h3 class="h5 mb-3">Apply for this job</h3>
    <?php if ($formAlert !== ''): ?>
        <?php $alertClass = $formAlertType === 'success' ? 'alert-success' : 'alert-danger'; ?>
        <div class="alert <?php echo $alertClass; ?>"><?php echo htmlspecialchars($formAlert); ?></div>
    <?php endif; ?>
    <form method="post">
        <label class="form-label">Cover Letter (optional)</label>
        <textarea name="cover_letter" class="form-control" rows="4" maxlength="500" placeholder="Briefly explain why you are a good fit for this job&hellip;" <?php echo $alreadyApplied ? 'disabled' : ''; ?>></textarea>
        <div class="form-text mb-3">Max 500 characters</div>
        <div class="d-flex flex-wrap gap-2">
            <?php if ($alreadyApplied): ?>
                <button type="button" class="btn btn-primary" disabled>Already Applied</button>
            <?php else: ?>
                <button type="submit" name="apply" class="btn btn-primary">Apply Now</button>
            <?php endif; ?>
            <button type="submit" name="bookmark" class="btn btn-outline-secondary">Bookmark</button>
        </div>
    </form>
    </div>
</div>
<?php else: ?>
<div class="alert alert-warning">
    Please <a class="link-primary text-decoration-none" href="login.php">login</a> to apply or bookmark this job.
</div>
<?php endif; ?>

<?php require 'footer.php'; ?>
