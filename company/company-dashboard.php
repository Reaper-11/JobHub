<?php
require '../db.php';
if (!isset($_SESSION['company_id'])) {
    header("Location: company-login.php");
    exit;
}
$cid = (int) $_SESSION['company_id'];
$isApproved = 0;
$rejectionReason = '';
if ($statusStmt = $conn->prepare("SELECT is_approved, rejection_reason FROM companies WHERE id = ?")) {
    $statusStmt->bind_param("i", $cid);
    if ($statusStmt->execute()) {
        $statusRes = $statusStmt->get_result();
        $statusRow = $statusRes ? $statusRes->fetch_assoc() : null;
        $isApproved = $statusRow ? (int) $statusRow['is_approved'] : 0;
        $rejectionReason = $statusRow ? trim((string) ($statusRow['rejection_reason'] ?? '')) : '';
    }
    $statusStmt->close();
}
if ($isApproved === 1) {
    $statusLabel = 'Approved';
} elseif ($isApproved === -1) {
    $statusLabel = 'Rejected';
} else {
    $statusLabel = 'Pending Approval';
}

$totalJobs = 0;
$activeJobs = 0;
$closedJobs = 0;
$jobsSql = "SELECT j.id,
                   j.title,
                   j.location,
                   j.created_at,
                   j.application_duration,
                   j.status,
                   COALESCE(j.views, 0) AS views,
                   COALESCE(apps.total_apps, 0) AS total_apps,
                   COALESCE(apps.shortlisted_apps, 0) AS shortlisted_apps
            FROM jobs j
            LEFT JOIN (
                SELECT a.job_id,
                       COUNT(*) AS total_apps,
                       SUM(CASE WHEN a.status = 'shortlisted' THEN 1 ELSE 0 END) AS shortlisted_apps
                FROM applications a
                GROUP BY a.job_id
            ) apps ON apps.job_id = j.id
            WHERE j.company_id = ?
            ORDER BY j.created_at DESC";
$jobs = db_query_all($jobsSql, "i", [$cid]);
foreach ($jobs as $row) {
    $isClosed = is_job_closed($row);
    if ($isClosed) {
        $closedJobs++;
    } else {
        $activeJobs++;
    }
}
$totalJobs = count($jobs);

$totalApps = 0;
$newAppsToday = 0;
$newAppsWeek = 0;
$appsSql = "SELECT COUNT(*) AS total_apps,
                   SUM(CASE WHEN DATE(a.applied_at) = CURDATE() THEN 1 ELSE 0 END) AS today_apps,
                   SUM(CASE WHEN a.applied_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) THEN 1 ELSE 0 END) AS week_apps
            FROM applications a
            JOIN jobs j ON a.job_id = j.id
            WHERE j.company_id = ?";
if ($appsStmt = $conn->prepare($appsSql)) {
    $appsStmt->bind_param("i", $cid);
    if ($appsStmt->execute()) {
        $appsRes = $appsStmt->get_result();
        $appsRow = $appsRes ? $appsRes->fetch_assoc() : null;
        if ($appsRow) {
            $totalApps = (int) ($appsRow['total_apps'] ?? 0);
            $newAppsToday = (int) ($appsRow['today_apps'] ?? 0);
            $newAppsWeek = (int) ($appsRow['week_apps'] ?? 0);
        }
    }
    $appsStmt->close();
}

$countJobs = $totalJobs;
$basePath = '../';
require '../header.php';
?>
<h1 class="mb-3">Company Dashboard</h1>
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <p class="mb-1">Welcome, <?php echo htmlspecialchars($_SESSION['company_name']); ?></p>
        <p class="mb-1">Status: <?php echo $statusLabel; ?></p>
        <?php if ($isApproved === -1 && $rejectionReason !== ''): ?>
            <p class="text-danger mb-1">Reason: <?php echo htmlspecialchars($rejectionReason); ?></p>
        <?php endif; ?>
        <p class="mb-3">Total jobs posted: <?php echo $countJobs; ?></p>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-primary" href="company-add-job.php">Post New Job</a>
            <a class="btn btn-outline-secondary" href="company-applications.php">View Applications</a>
            <a class="btn btn-outline-secondary" href="company-account.php">Company Account</a>
            <a class="btn btn-outline-secondary" href="../logout.php">Logout</a>
        </div>
    </div>
</div>

<h3 class="h5 mb-3">Quick Stats</h3>
<div class="row g-3 mb-4">
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">Total Jobs Posted</div>
                <div class="h4 mb-0"><?php echo $totalJobs; ?></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">Total Applications Received</div>
                <div class="h4 mb-0"><?php echo $totalApps; ?></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">New Applications</div>
                <div class="h4 mb-1"><?php echo $newAppsToday; ?></div>
                <div class="text-muted small">Today: <?php echo $newAppsToday; ?> | This Week: <?php echo $newAppsWeek; ?></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">Active / Closed Jobs</div>
                <div class="h5 mb-0"><?php echo $activeJobs; ?> Active / <?php echo $closedJobs; ?> Closed</div>
            </div>
        </div>
    </div>
</div>

<h3 class="h5 mb-3">Your Jobs</h3>
<div class="table-responsive">
<table class="table table-striped table-hover align-middle">
    <thead>
        <tr>
            <th>ID</th>
            <th>Title</th>
            <th>Location</th>
            <th>Views</th>
            <th>Applications</th>
            <th>Shortlisted</th>
            <th>Created</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($jobs as $j): ?>
        <?php
        $jobIsClosed = is_job_closed($j);
        ?>
        <tr>
            <td><?php echo $j['id']; ?></td>
            <td><?php echo htmlspecialchars($j['title']); ?></td>
            <td><?php echo htmlspecialchars($j['location']); ?></td>
            <td><?php echo (int) ($j['views'] ?? 0); ?></td>
            <td><?php echo (int) ($j['total_apps'] ?? 0); ?></td>
            <td><?php echo (int) ($j['shortlisted_apps'] ?? 0); ?></td>
            <td><?php echo htmlspecialchars($j['created_at']); ?></td>
            <td>
                <a class="btn btn-sm btn-outline-primary" href="company-edit-job.php?id=<?php echo $j['id']; ?>">Edit</a>
                <?php if ($jobIsClosed): ?>
                    <form class="d-inline" method="post" action="company-toggle-job.php" onsubmit="return confirm('Reopen this job?');">
                        <input type="hidden" name="id" value="<?php echo $j['id']; ?>">
                        <input type="hidden" name="status" value="active">
                        <button class="btn btn-sm btn-outline-secondary" type="submit">Reopen</button>
                    </form>
                <?php else: ?>
                    <form class="d-inline" method="post" action="company-toggle-job.php" onsubmit="return confirm('Close this job? Applicants will no longer be able to apply.');">
                        <input type="hidden" name="id" value="<?php echo $j['id']; ?>">
                        <input type="hidden" name="status" value="closed">
                        <button class="btn btn-sm btn-outline-secondary" type="submit">Close</button>
                    </form>
                <?php endif; ?>
                <form class="d-inline" method="post" action="company-delete-job.php" onsubmit="return confirm('Delete this job? This cannot be undone.');">
                    <input type="hidden" name="id" value="<?php echo $j['id']; ?>">
                    <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php require '../footer.php'; ?>
