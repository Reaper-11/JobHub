<?php
// job-detail.php
require 'db.php';

update_expired_jobs($conn, null, isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$job_id = (int)$_GET['id'];

$sql = "SELECT j.*, COALESCE(j.application_count, 0) AS application_count,
               c.name AS company_name, c.logo_path, c.is_approved,
               c.website AS company_website, c.location AS company_location
        FROM jobs j
        LEFT JOIN companies c ON j.company_id = c.id
        WHERE j.id = ? 
          AND j.status = 'active'
          AND j.is_approved = 1
          AND (j.company_id IS NULL OR " . jobhub_company_public_job_clause('c') . ")";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $job_id);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$job) {
    $bodyClass = 'user-ui';
    require 'header.php';
    echo '<div class="alert alert-danger">Job not found or no longer available.</div>';
    require 'footer.php';
    exit;
}

$is_expired = is_job_expired($job);
$is_closed  = is_job_closed($job);
$is_inactive = $is_expired || $is_closed;

$already_applied = false;
$already_bookmarked = false;
$user_id = current_user_id();
$user_cv_path = null;
$available_user_cvs = [];
$selected_apply_cv_ids = [];
$cover_letter_draft = '';
$cv_schema_result = jobhub_cv_ensure_library_schema($conn);

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

    if (!empty($cv_schema_result['success'])) {
        $available_user_cvs = jobhub_user_cv_list($conn, $user_id);
        $default_user_cv = jobhub_user_default_cv($conn, $user_id);
        $user_cv_path = $default_user_cv['cv_path'] ?? null;
    } else {
        $user_cv_path = db_query_value("SELECT cv_path FROM users WHERE id = ?", "i", [$user_id], null);
    }

    // Log job view (if tracking table exists)
    $check = $conn->query("SHOW TABLES LIKE 'job_view_logs'");
    if ($check && $check->num_rows > 0) {
        $viewStmt = $conn->prepare("INSERT INTO job_view_logs (user_id, job_id, created_at) VALUES (?, ?, NOW())");
        if ($viewStmt) {
            $viewStmt->bind_param("ii", $user_id, $job_id);
            $viewStmt->execute();
            $viewStmt->close();
        }
    }
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
            $cover_letter_draft = trim($_POST['cover_letter'] ?? '');
            $selected_apply_cv_ids = array_values(array_unique(array_filter(
                array_map(static fn($cvId): int => (int) $cvId, (array) ($_POST['cv_ids'] ?? [])),
                static fn(int $cvId): bool => $cvId > 0
            )));

            if ($already_applied) {
                $alert = "You have already applied for this job.";
                $alert_type = 'warning';
            } elseif ($is_inactive) {
                $alert = "This job is no longer accepting applications.";
                $alert_type = 'warning';
            } elseif (empty($cv_schema_result['success'])) {
                $alert = (string) ($cv_schema_result['message'] ?? 'CV storage could not be prepared.');
                $alert_type = 'warning';
            } elseif (empty($available_user_cvs)) {
                $alert = "Please upload at least one CV in your account before applying.";
                $alert_type = 'warning';
            } elseif (empty($selected_apply_cv_ids)) {
                $alert = "Please select at least one CV for this application.";
                $alert_type = 'warning';
            } else {
                $selected_user_cvs = jobhub_user_cv_find_many($conn, (int) $user_id, $selected_apply_cv_ids);
                if (count($selected_user_cvs) !== count($selected_apply_cv_ids)) {
                    $alert = "Please select valid CVs from your account.";
                    $alert_type = 'warning';
                } else {
                    $primary_cv_path = (string) ($selected_user_cvs[0]['cv_path'] ?? '');
                    $cover = $cover_letter_draft;
                    $conn->begin_transaction();

                    try {
                        $stmt = $conn->prepare("
                            INSERT INTO applications (user_id, job_id, cover_letter, cv_path, applied_at)
                            VALUES (?, ?, ?, ?, NOW())
                        ");
                        if (!$stmt) {
                            throw new RuntimeException('Could not prepare the application.');
                        }

                        $stmt->bind_param("iiss", $user_id, $job_id, $cover, $primary_cv_path);
                        if (!$stmt->execute()) {
                            $error = $stmt->error;
                            $stmt->close();
                            throw new RuntimeException($error !== '' ? $error : 'Could not save the application.');
                        }

                        $application_id = (int) $conn->insert_id;
                        $stmt->close();

                        if (!jobhub_application_attach_cvs($conn, $application_id, $selected_user_cvs)) {
                            throw new RuntimeException('Could not attach the selected CVs.');
                        }

                        $conn->commit();
                        $alert = count($selected_user_cvs) === 1
                            ? "Application submitted successfully with 1 CV attached."
                            : "Application submitted successfully with " . count($selected_user_cvs) . " CVs attached.";
                        $already_applied = true;
                        $cover_letter_draft = '';
                        $selected_apply_cv_ids = [];
                        log_activity(
                            $conn,
                            $user_id,
                            'seeker',
                            'job_application_submitted',
                            "User applied for job: {$job['title']}",
                            'application',
                            $application_id
                        );
                    } catch (Throwable $e) {
                        $conn->rollback();
                        $alert = "Failed to submit application. Please try again.";
                        $alert_type = 'danger';
                    }
                }
            }
        }

        if (isset($_POST['bookmark'])) {
            if ($already_bookmarked) {
                $alert = "This job is already bookmarked.";
                $alert_type = 'info';
            } elseif ($is_inactive) {
                $alert = "This job is no longer active and cannot be bookmarked.";
                $alert_type = 'warning';
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

$company_name = trim((string)($job['company_name'] ?: $job['company'] ?: 'Company'));
$location_display = trim((string)($job['location'] ?? '')) !== '' ? trim((string)$job['location']) : 'Not specified';
$type_display = trim((string)($job['type'] ?? '')) !== '' ? trim((string)$job['type']) : 'Not specified';
$category_display = trim((string)($job['category'] ?? '')) !== '' ? trim((string)$job['category']) : 'Not specified';
$experience_display = trim((string)($job['experience_level'] ?? '')) !== '' ? trim((string)$job['experience_level']) : 'Not specified';
$salary_display = jobhub_salary_display_value($job, 'Not specified');
$posted_timestamp = !empty($job['created_at']) ? strtotime((string)$job['created_at']) : false;
$date_posted_display = $posted_timestamp ? date('M d, Y', $posted_timestamp) : 'Not specified';
$apply_by_timestamp = job_expiration_timestamp($job);
$apply_by_display = $apply_by_timestamp ? date('M d, Y', $apply_by_timestamp) : 'Not specified';
$company_location_display = trim((string)($job['company_location'] ?? '')) !== '' ? trim((string)$job['company_location']) : $location_display;
$company_website = trim((string)($job['company_website'] ?? ''));
$company_focus = $category_display !== 'Not specified'
    ? $category_display
    : ($type_display !== 'Not specified' ? $type_display : 'Hiring company');

$status_label = 'Open';
$status_note = 'Currently accepting applications.';
if ($is_expired) {
    $status_label = 'Expired';
    $status_note = 'This job has passed its application window.';
} elseif ($is_closed) {
    $status_label = 'Closed';
    $status_note = 'This role has been closed by the employer.';
}

$skill_tags = [];
$skills_required_raw = trim((string)($job['skills_required'] ?? ''));
if ($skills_required_raw !== '') {
    $normalized_skills = str_replace(["\r\n", "\r", "\n", ";", "|"], ',', $skills_required_raw);
    $normalized_skills = preg_replace('/\s*\/\s*/', ',', $normalized_skills);
    $skill_parts = preg_split('/,/', (string)$normalized_skills);
    $seen_skills = [];

    foreach ($skill_parts as $skill_part) {
        $skill = trim((string)preg_replace('/\s+/', ' ', (string)$skill_part));
        if ($skill === '') {
            continue;
        }

        $skill_key = strtolower($skill);
        if (isset($seen_skills[$skill_key])) {
            continue;
        }

        $seen_skills[$skill_key] = true;
        $skill_tags[] = $skill;
    }
}

$company_initials = '';
$initial_chunks = preg_split('/[\s\-&]+/', $company_name, -1, PREG_SPLIT_NO_EMPTY);
if (is_array($initial_chunks)) {
    foreach ($initial_chunks as $chunk) {
        $company_initials .= strtoupper(substr($chunk, 0, 1));
        if (strlen($company_initials) >= 2) {
            break;
        }
    }
}
if ($company_initials === '') {
    $company_initials = strtoupper(substr($company_name !== '' ? $company_name : 'CO', 0, 2));
}

$pageTitle = trim((string)($job['title'] ?? '')) !== ''
    ? trim((string)$job['title']) . ' | JobHub'
    : 'Job Details | JobHub';
$bodyClass = 'user-ui';
require 'header.php';
?>

<div class="job-detail-page">
    <div class="job-detail-layout">
        <div class="job-detail-main">
            <section class="job-section job-hero">
                <a href="index.php" class="job-back-link">Back to Listings</a>

                <div class="job-hero-content">
                    <div class="job-hero-copy">
                        <h1><?= htmlspecialchars($job['title']) ?></h1>
                        <div class="job-meta-row">
                            <span class="job-company-name"><?= htmlspecialchars($company_name) ?></span>
                            <span class="job-meta-separator" aria-hidden="true"></span>
                            <span><?= htmlspecialchars($location_display) ?></span>
                        </div>
                    </div>

                    <div class="job-hero-actions">
                        <span class="job-type-pill"><?= htmlspecialchars($type_display) ?></span>

                        <?php if ($already_bookmarked): ?>
                            <span class="job-bookmark-state">Bookmarked</span>
                        <?php elseif ($is_inactive): ?>
                            <span class="job-bookmark-state"><?= htmlspecialchars($status_label) ?></span>
                        <?php elseif ($user_id): ?>
                            <form method="post" class="job-bookmark-form">
                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                <input type="hidden" name="bookmark" value="1">
                                <button type="submit" class="job-bookmark-btn">Bookmark Job</button>
                            </form>
                        <?php elseif (!$user_id): ?>
                            <a href="login.php" class="job-bookmark-btn job-bookmark-link">Sign In to Bookmark</a>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <?php if ($is_inactive): ?>
                <div class="alert alert-warning job-detail-alert">
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
                <div class="alert alert-<?= $alert_type ?> job-detail-alert"><?= htmlspecialchars($alert) ?></div>
            <?php endif; ?>

            <section class="job-stat-grid" aria-label="Job summary">
                <article class="job-stat-card">
                    <span class="job-stat-label">Salary</span>
                    <strong class="job-stat-value"><?= htmlspecialchars($salary_display) ?></strong>
                </article>
                <article class="job-stat-card">
                    <span class="job-stat-label">Category</span>
                    <strong class="job-stat-value"><?= htmlspecialchars($category_display) ?></strong>
                </article>
                <article class="job-stat-card">
                    <span class="job-stat-label">Date Posted</span>
                    <strong class="job-stat-value"><?= htmlspecialchars($date_posted_display) ?></strong>
                </article>
            </section>

            <section class="job-section">
                <div class="job-section-header">
                    <div>
                        <h2>Job Description</h2>
                        <p>Review the scope, expectations, and responsibilities for this role.</p>
                    </div>
                </div>
                <div class="job-description-content"><?= nl2br(htmlspecialchars($job['description'])) ?></div>
            </section>

            <section class="job-section">
                <div class="job-section-header">
                    <div>
                        <h2>Role Snapshot</h2>
                        <p>Concise information to help you evaluate the opportunity.</p>
                    </div>
                </div>

                <div class="role-detail-grid">
                    <article class="role-detail-card">
                        <span class="role-detail-label">Experience</span>
                        <strong class="role-detail-value"><?= htmlspecialchars($experience_display) ?></strong>
                    </article>

                    <article class="role-detail-card">
                        <span class="role-detail-label">Apply By</span>
                        <strong class="role-detail-value"><?= htmlspecialchars($apply_by_display) ?></strong>
                    </article>

                    <article class="role-detail-card">
                        <span class="role-detail-label">Status</span>
                        <strong class="role-detail-value"><?= htmlspecialchars($status_label) ?></strong>
                        <p class="role-detail-note"><?= htmlspecialchars($status_note) ?></p>
                    </article>

                    <article class="role-detail-card role-detail-card--skills">
                        <span class="role-detail-label">Skills</span>
                        <?php if ($skill_tags): ?>
                            <div class="skill-tags">
                                <?php foreach ($skill_tags as $skill_tag): ?>
                                    <span class="skill-tag"><?= htmlspecialchars($skill_tag) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <strong class="role-detail-value">Not specified</strong>
                        <?php endif; ?>
                    </article>
                </div>
            </section>
        </div>

        <aside class="job-sidebar">
            <section class="job-sidebar-card">
                <div class="job-sidebar-header">
                    <h2>Apply Now</h2>
                    <p>Use your saved profile and CV to submit a polished application.</p>
                </div>

                <?php if ($already_applied): ?>
                    <div class="alert alert-success mb-0">
                        You have already applied for this job.
                    </div>
                <?php elseif ($is_inactive): ?>
                    <div class="alert alert-warning mb-0">
                        Applications closed
                    </div>
                <?php elseif (!$user_id): ?>
                    <p class="job-sidebar-copy">
                        Please sign in or create an account to apply and bookmark this opportunity.
                    </p>
                    <div class="job-auth-actions">
                        <a href="login.php" class="btn btn-primary job-apply-btn">Sign In</a>
                        <a href="register.php" class="job-secondary-btn">Create Account</a>
                    </div>
                <?php elseif (empty($available_user_cvs)): ?>
                    <div class="alert alert-warning mb-3">
                        Please upload your CV in <a href="user-account.php" class="alert-link">your account</a> before applying.
                    </div>
                    <a href="user-account.php" class="job-secondary-btn w-100 text-center">Manage CV</a>
                <?php else: ?>
                    <form method="post" class="job-apply-form">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="apply" value="1">

                        <div class="job-cv-note">
                            Select one or more saved CVs to attach to this application.
                        </div>

                        <div class="mb-3">
                            <label class="job-form-label d-block">Choose CV(s)</label>
                            <div class="job-cv-picker">
                                <?php foreach ($available_user_cvs as $saved_cv): ?>
                                    <?php
                                    $savedCvId = (int) ($saved_cv['id'] ?? 0);
                                    $isChecked = in_array($savedCvId, $selected_apply_cv_ids, true)
                                        || (empty($selected_apply_cv_ids) && !empty($saved_cv['is_default']));
                                    $savedCvInputId = 'apply_cv_' . $savedCvId;
                                    ?>
                                    <div class="job-cv-option">
                                        <input
                                            type="checkbox"
                                            name="cv_ids[]"
                                            value="<?= $savedCvId ?>"
                                            id="<?= htmlspecialchars($savedCvInputId) ?>"
                                            class="job-cv-checkbox"
                                            <?= $isChecked ? 'checked' : '' ?>
                                        >
                                        <div class="job-cv-option-shell">
                                            <label for="<?= htmlspecialchars($savedCvInputId) ?>" class="job-cv-card">
                                                <span class="job-cv-card-top">
                                                    <span class="job-cv-checkmark" aria-hidden="true"></span>
                                                    <span class="job-cv-file-block">
                                                        <span class="job-cv-file-name"><?= htmlspecialchars($saved_cv['display_name'] ?? jobhub_cv_file_name($saved_cv['cv_path'] ?? '')) ?></span>
                                                        <span class="job-cv-badges">
                                                            <?php if (!empty($saved_cv['is_default'])): ?>
                                                                <span class="job-cv-badge job-cv-badge--default">Default</span>
                                                            <?php endif; ?>
                                                        </span>
                                                    </span>
                                                </span>
                                                <span class="job-cv-card-meta">
                                                    Uploaded <?= !empty($saved_cv['created_at']) ? htmlspecialchars(date('M d, Y H:i', strtotime((string) $saved_cv['created_at']))) : 'recently' ?>
                                                </span>
                                            </label>
                                            <a
                                                href="cv-download.php?scope=profile&cv_id=<?= $savedCvId ?>"
                                                class="job-cv-view-link"
                                                target="_blank"
                                                rel="noopener"
                                            >View</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="job-cv-helper">
                                Choose the CV version that best fits this role. You can attach multiple CVs if the employer needs more than one version.
                            </div>
                        </div>

                        <label for="cover_letter" class="job-form-label">Cover Letter</label>
                        <textarea
                            id="cover_letter"
                            name="cover_letter"
                            class="form-control job-cover-letter"
                            rows="5"
                            placeholder="Highlight your relevant experience and interest in this role."><?= htmlspecialchars($cover_letter_draft) ?></textarea>

                        <button type="submit" class="btn btn-primary job-apply-btn">
                            Submit Application
                        </button>
                    </form>
                <?php endif; ?>
            </section>

            <section class="company-overview-card">
                <div class="job-section-header company-overview-title">
                    <div>
                        <h2>Company Overview</h2>
                    </div>
                </div>

                <div class="company-overview-header">
                    <?php if (!empty($job['logo_path'])): ?>
                        <img
                            src="<?= htmlspecialchars($job['logo_path']) ?>"
                            alt="<?= htmlspecialchars($company_name) ?> logo"
                            class="company-overview-logo">
                    <?php else: ?>
                        <div class="company-overview-placeholder"><?= htmlspecialchars($company_initials) ?></div>
                    <?php endif; ?>

                    <div class="company-overview-copy">
                        <h3><?= htmlspecialchars($company_name) ?></h3>
                        <p><?= htmlspecialchars($company_focus) ?></p>
                    </div>
                </div>

                <div class="company-overview-meta">
                    <div class="company-overview-item">
                        <span>Location</span>
                        <strong><?= htmlspecialchars($company_location_display) ?></strong>
                    </div>
                    <div class="company-overview-item">
                        <span>Hiring For</span>
                        <strong><?= htmlspecialchars($type_display) ?></strong>
                    </div>
                    <?php if ($company_website !== ''): ?>
                        <div class="company-overview-item">
                            <span>Website</span>
                            <strong><a href="<?= htmlspecialchars($company_website) ?>" target="_blank" rel="noopener noreferrer">Visit website</a></strong>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </aside>
    </div>
</div>

<?php require 'footer.php'; ?>
