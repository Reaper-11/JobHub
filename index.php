<?php
require 'db.php';
require 'header.php';

$keyword = trim($_GET['q'] ?? '');
$filter  = trim($_GET['filter'] ?? '');

$categories = require __DIR__ . '/includes/categories.php';

$sql = "SELECT j.*, COALESCE(j.application_count, 0) AS application_count
        FROM jobs j
        LEFT JOIN companies c ON j.company_id = c.id
        WHERE (j.company_id IS NULL OR c.is_approved = 1)
          AND j.status = 'active'";

$types = '';
$params = [];

if ($keyword) {
    $sql .= " AND (j.title LIKE ? OR j.description LIKE ? OR j.company LIKE ?)";
    $like = "%$keyword%";
    $types .= 'sss';
    $params = [$like, $like, $like];
}

if ($filter && in_array($filter, $categories, true)) {
    $sql .= " AND j.category = ?";
    $types .= 's';
    $params[] = $filter;
}

$sql .= " ORDER BY application_count DESC, j.created_at DESC LIMIT 50";

$jobs = db_query_all($sql, $types, $params);

$topJobs = db_query_all(
    "SELECT j.*, COALESCE(j.application_count, 0) AS application_count
     FROM jobs j
     LEFT JOIN companies c ON j.company_id = c.id
     WHERE (j.company_id IS NULL OR c.is_approved = 1)
       AND j.status = 'active'
     ORDER BY application_count DESC, j.created_at DESC
     LIMIT 6"
);
?>

<?php if (isset($_GET['welcome']) && $_GET['welcome'] === '1'): ?>
    <div class="alert alert-success">Account created successfully. You are now signed in.</div>
<?php endif; ?>

<h1 class="mb-4">Let's Find You A Job</h1>

<form method="get" class="mb-4">
    <div class="row g-3">
        <div class="col-md-6">
            <input type="text" name="q" class="form-control" placeholder="Job title, company, keywords..." value="<?= htmlspecialchars($keyword) ?>">
        </div>
        <div class="col-md-4">
            <select name="filter" class="form-select" onchange="this.form.submit()">
                <option value="" disabled <?= $filter === '' ? 'selected' : '' ?>>Select category...</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>" <?= $filter === $cat ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">Search</button>
        </div>
    </div>
</form>

<?php if (!empty($topJobs)): ?>
    <h2 class="h4 mb-3">Top Jobs</h2>
    <div class="row g-4 mb-4">
        <?php foreach ($topJobs as $job): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title"><?= htmlspecialchars($job['title']) ?></h5>
                        <p class="text-muted mb-1">
                            <?= htmlspecialchars($job['company']) ?> â€¢ <?= htmlspecialchars($job['location']) ?>
                            <span class="badge bg-warning ms-2"><?= htmlspecialchars($job['type'] ?? 'Full-time') ?></span>
                        </p>
                        <p class="small text-muted">
                            Posted <?= date('M d, Y', strtotime($job['created_at'])) ?>
                        </p>
                        <p class="card-text flex-grow-1"><?= nl2br(htmlspecialchars(substr($job['description'], 0, 120))) ?>...</p>
                        <div class="mt-auto">
                            <a href="job-detail.php?id=<?= $job['id'] ?>" class="btn btn-outline-primary btn-sm">View Details</a>
                            <a href="job-detail.php?id=<?= $job['id'] ?>#apply" class="btn btn-primary btn-sm">Apply Now</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<h2 class="h4 mb-3">Jobs</h2>
<?php if (empty($jobs)): ?>
    <div class="alert alert-info">No jobs found matching your criteria.</div>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($jobs as $job): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title"><?= htmlspecialchars($job['title']) ?></h5>
                        <p class="text-muted mb-1">
                            <?= htmlspecialchars($job['company']) ?> • <?= htmlspecialchars($job['location']) ?>
                            <span class="badge bg-warning ms-2"><?= htmlspecialchars($job['type'] ?? 'Full-time') ?></span>
                        </p>
                        <p class="small text-muted">
                            Posted <?= date('M d, Y', strtotime($job['created_at'])) ?>
                        </p>
                        <p class="card-text flex-grow-1"><?= nl2br(htmlspecialchars(substr($job['description'], 0, 150))) ?>...</p>
                        <div class="mt-auto">
                            <a href="job-detail.php?id=<?= $job['id'] ?>" class="btn btn-outline-primary btn-sm">View Details</a>
                            <a href="job-detail.php?id=<?= $job['id'] ?>#apply" class="btn btn-primary btn-sm">Apply Now</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require 'footer.php'; ?>
