<?php
// job-detail.php
require 'db.php';
require 'header.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$job_id = (int)$_GET['id'];

$sql = "SELECT j.*, COALESCE(j.application_count, 0) AS application_count,
               c.name AS company_name, c.logo_path, c.is_approved
        FROM jobs j
        LEFT JOIN companies c ON j.company_id = c.id
        WHERE j.id = ? 
          AND j.status = 'active'
          AND (j.company_id IS NULL OR c.is_approved = 1)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $job_id);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$job) {
    echo '<div class="alert alert-danger">Job not found or no longer available.</div>';
    require 'footer.php';
    exit;
}

$is_expired = is_job_expired($job);
$is_closed  = is_job_closed($job);
$is_inactive = $is_expired || $is_closed;

$already_applied = false;
$already_bookmarked = false;
$user_id = $_SESSION['user_id'] ?? null;

if ($user_id) {
    // Check if already applied
    $stmt = $conn->prepare("SELECT id FROM applications WHERE user_id = ? AND job_id = ? LIMIT 1");
    $stmt->bind_param("ii", $user_id, $job_id);
    $stmt->execute();
    $already_applied = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    // Check if bookmarked
    $stmt = $conn->prepare("SELECT id FROM bookmarks WHERE user_id = ? AND job_id = ? LIMIT 1");
    $stmt->bind_param("ii", $user_id, $job_id);
    $stmt->execute();
    $already_bookmarked = $stmt->get_result()->num_rows > 0;
    $stmt->close();
}

// Handle POST actions (apply / bookmark)
$alert = '';
$alert_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_id) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $alert = "Invalid request. Please try again.";
        $alert_type = 'danger';
    } else {
        if (isset($_POST['apply'])) {
            if ($already_applied) {
                $alert = "You have already applied for this job.";
                $alert_type = 'warning';
            } elseif ($is_inactive) {
                $alert = "This job is no longer accepting applications.";
                $alert_type = 'warning';
            } else {
                $cover = trim($_POST['cover_letter'] ?? '');
                $stmt = $conn->prepare("
                    INSERT INTO applications (user_id, job_id, cover_letter, applied_at)
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->bind_param("iis", $user_id, $job_id, $cover);
                if ($stmt->execute()) {
                    $alert = "Application submitted successfully!";
                    $already_applied = true;
                    // Optional: increment application_count
                } else {
                    $alert = "Failed to submit application. Please try again.";
                    $alert_type = 'danger';
                }
                $stmt->close();
            }
        }

        if (isset($_POST['bookmark'])) {
            if ($already_bookmarked) {
                $alert = "This job is already bookmarked.";
                $alert_type = 'info';
            } else {
                $stmt = $conn->prepare("INSERT INTO bookmarks (user_id, job_id, created_at) VALUES (?, ?, NOW())");
                $stmt->bind_param("ii", $user_id, $job_id);
                if ($stmt->execute()) {
                    $alert = "Job bookmarked successfully!";
                    $already_bookmarked = true;
                } else {
                    $alert = "Failed to bookmark job.";
                    $alert_type = 'danger';
                }
                $stmt->close();
            }
        }
    }
}
?>

<div class="row">
    <div class="col-lg-8">
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h1 class="h3 mb-3"><?= htmlspecialchars($job['title']) ?></h1>
                
                <div class="d-flex align-items-center mb-4">
                    <?php if (!empty($job['logo_path'])): ?>
                        <img src="<?= htmlspecialchars($job['logo_path']) ?>" alt="Company logo" 
                             class="rounded me-3" style="width:60px; height:60px; object-fit:contain; border:1px solid #dee2e6;">
                    <?php endif; ?>
                    <div>
                        <h5 class="mb-1"><?= htmlspecialchars($job['company_name'] ?: $job['company']) ?></h5>
                        <div class="text-muted">
                            <?= htmlspecialchars($job['location']) ?> 
                            <span class="badge bg-warning ms-2"><?= htmlspecialchars($job['type'] ?? 'Full-time') ?></span>
                        </div>
                    </div>
                </div>

                <?php if ($is_inactive): ?>
                    <div class="alert alert-warning">
                        <?php if ($is_expired): ?>
                            This job has expired.
                        <?php elseif ($is_closed): ?>
                            This position has been closed by the employer.
                        <?php else: ?>
                            Applications are no longer being accepted.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($alert): ?>
                    <div class="alert alert-<?= $alert_type ?>"><?= htmlspecialchars($alert) ?></div>
                <?php endif; ?>

                <h5 class="mt-4 mb-3">Job Description</h5>
                <div class="mb-4"><?= nl2br(htmlspecialchars($job['description'])) ?></div>

                <h5 class="mb-3">Details</h5>
                <dl class="row">
                    <dt class="col-sm-4 text-muted">Salary</dt>
                    <dd class="col-sm-8"><?= htmlspecialchars($job['salary'] ?: 'Negotiable / Not specified') ?></dd>

                    <dt class="col-sm-4 text-muted">Category</dt>
                    <dd class="col-sm-8"><?= htmlspecialchars($job['category'] ?: '-') ?></dd>

                    <?php if (!empty($job['application_duration'])): ?>
                    <dt class="col-sm-4 text-muted">Apply by</dt>
                    <dd class="col-sm-8">
                        <?= htmlspecialchars($job['application_duration']) ?>
                        <?php if ($expires = job_expiration_timestamp($job['created_at'], $job['application_duration'])): ?>
                            <small class="text-muted">(<?= date('M d, Y', $expires) ?>)</small>
                        <?php endif; ?>
                    </dd>
                    <?php endif; ?>

                    <dt class="col-sm-4 text-muted">Posted</dt>
                    <dd class="col-sm-8"><?= date('M d, Y', strtotime($job['created_at'])) ?></dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm sticky-top" style="top:20px;">
            <div class="card-body text-center">
                <h5 class="mb-4">Apply for this position</h5>

                <?php if (!$user_id): ?>
                    <p class="text-muted mb-4">
                        Please <a href="login.php" class="text-primary">sign in</a> or 
                        <a href="register.php" class="text-primary">create an account</a> 
                        to apply or bookmark this job.
                    </p>
                <?php elseif ($is_inactive): ?>
                    <div class="alert alert-warning small mb-0">
                        Applications closed
                    </div>
                <?php elseif ($already_applied): ?>
                    <div class="alert alert-success small mb-4">
                        You have already applied for this job
                    </div>
                <?php else: ?>
                    <form method="post" class="mb-4">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="apply" value="1">

                        <div class="mb-3">
                            <textarea name="cover_letter" class="form-control" rows="5" 
                                      placeholder="Why are you a good fit? (optional)"></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 mb-2">
                            Submit Application
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($user_id && !$already_bookmarked && !$is_inactive): ?>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="bookmark" value="1">
                        <button type="submit" class="btn btn-outline-secondary w-100">
                            <?= $already_bookmarked ? 'Bookmarked' : 'Bookmark this job' ?>
                        </button>
                    </form>
                <?php elseif ($already_bookmarked): ?>
                    <div class="text-success small">Already bookmarked</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require 'footer.php'; ?>