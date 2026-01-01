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
$jobs = [];
if ($jobsStmt = $conn->prepare("SELECT id, title, location, created_at, application_duration, status FROM jobs WHERE company_id = ? ORDER BY created_at DESC")) {
    $jobsStmt->bind_param("i", $cid);
    if ($jobsStmt->execute()) {
        $jobsRes = $jobsStmt->get_result();
        if ($jobsRes) {
            while ($row = $jobsRes->fetch_assoc()) {
                $jobs[] = $row;
                $isClosed = is_job_closed($row) || is_job_expired($row);
                if ($isClosed) {
                    $closedJobs++;
                } else {
                    $activeJobs++;
                }
            }
        }
    }
    $jobsStmt->close();
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
<h1>Company Dashboard</h1>
<div class="card">
    <p>Welcome, <?php echo htmlspecialchars($_SESSION['company_name']); ?></p>
    <p>Status: <?php echo $statusLabel; ?></p>
    <?php if ($isApproved === -1 && $rejectionReason !== ''): ?>
        <p>Reason: <?php echo htmlspecialchars($rejectionReason); ?></p>
    <?php endif; ?>
    <p>Total jobs posted: <?php echo $countJobs; ?></p>
    <p>
        <a class="btn" href="company-add-job.php">Post New Job</a>
        <a class="btn btn-secondary" href="company-applications.php">View Applications</a>
        <a class="btn btn-secondary" href="company-account.php">Company Account</a>
        <a class="btn btn-secondary" href="../logout.php">Logout</a>
    </p>
</div>

<h3>Quick Stats</h3>
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Total Jobs Posted</div>
        <div class="stat-value"><?php echo $totalJobs; ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total Applications Received</div>
        <div class="stat-value"><?php echo $totalApps; ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">New Applications</div>
        <div class="stat-value"><?php echo $newAppsToday; ?></div>
        <div class="stat-sub">Today • <?php echo $newAppsWeek; ?> This Week</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Active / Closed Jobs</div>
        <div class="stat-value"><?php echo $activeJobs; ?> Active / <?php echo $closedJobs; ?> Closed</div>
    </div>
</div>

<h3>Your Jobs</h3>
<table>
    <tr>
        <th>ID</th>
        <th>Title</th>
        <th>Location</th>
        <th>Created</th>
        <th>Status</th>
        <th>Action</th>
    </tr>
    <?php foreach ($jobs as $j): ?>
        <?php
        $jobIsClosed = is_job_closed($j);
        $jobIsExpired = is_job_expired($j);
        $jobStatusLabel = $jobIsClosed ? 'Closed' : ($jobIsExpired ? 'Expired' : 'Active');
        ?>
        <tr>
            <td><?php echo $j['id']; ?></td>
            <td><?php echo htmlspecialchars($j['title']); ?></td>
            <td><?php echo htmlspecialchars($j['location']); ?></td>
            <td><?php echo htmlspecialchars($j['created_at']); ?></td>
            <td><?php echo $jobStatusLabel; ?></td>
            <td>
                <a class="btn btn-small" href="company-edit-job.php?id=<?php echo $j['id']; ?>">Edit</a>
                <?php if ($jobIsClosed): ?>
                    <form class="inline-form" method="post" action="company-toggle-job.php" onsubmit="return confirm('Reopen this job?');">
                        <input type="hidden" name="id" value="<?php echo $j['id']; ?>">
                        <input type="hidden" name="status" value="active">
                        <button class="btn btn-small btn-secondary" type="submit">Reopen</button>
                    </form>
                <?php elseif (!$jobIsExpired): ?>
                    <form class="inline-form" method="post" action="company-toggle-job.php" onsubmit="return confirm('Close this job? Applicants will no longer be able to apply.');">
                        <input type="hidden" name="id" value="<?php echo $j['id']; ?>">
                        <input type="hidden" name="status" value="closed">
                        <button class="btn btn-small btn-secondary" type="submit">Close</button>
                    </form>
                <?php endif; ?>
                <form class="inline-form" method="post" action="company-delete-job.php" onsubmit="return confirm('Delete this job? This cannot be undone.');">
                    <input type="hidden" name="id" value="<?php echo $j['id']; ?>">
                    <button class="btn btn-small btn-danger" type="submit">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
<?php require '../footer.php'; ?>
