<?php
require '../db.php';
require '../includes/flash.php';
if (!isset($_SESSION['company_id'])) {
    header("Location: company-login.php");
    exit;
}
$cid = (int) $_SESSION['company_id'];
$statusRes = $conn->query("SELECT is_approved, rejection_reason FROM companies WHERE id=$cid");
$statusRow = $statusRes ? $statusRes->fetch_assoc() : null;
$isApproved = $statusRow ? (int) $statusRow['is_approved'] : 0;
$rejectionReason = $statusRow ? trim((string) ($statusRow['rejection_reason'] ?? '')) : '';
if ($isApproved === 1) {
    $statusLabel = 'Approved';
} elseif ($isApproved === -1) {
    $statusLabel = 'Rejected';
} else {
    $statusLabel = 'Pending Approval';
}

$countJobs = $conn->query(
    "SELECT COUNT(*) c FROM jobs WHERE company_id=$cid"
)->fetch_assoc()['c'];

$jobs = $conn->query(
    "SELECT * FROM jobs WHERE company_id=$cid ORDER BY created_at DESC"
);
$flash = get_flash('jobs');
$basePath = '../';
require '../header.php';
?>
<h1>Company Dashboard</h1>
<?php if ($flash): ?>
    <div class="alert <?php echo htmlspecialchars($flash['type']); ?>">
        <?php echo htmlspecialchars($flash['message']); ?>
    </div>
<?php endif; ?>
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

<h3>Your Jobs</h3>
<table>
    <tr>
        <th>ID</th>
        <th>Title</th>
        <th>Location</th>
        <th>Created</th>
        <th>Action</th>
    </tr>
    <?php while ($j = $jobs->fetch_assoc()): ?>
        <tr>
            <td><?php echo $j['id']; ?></td>
            <td><?php echo htmlspecialchars($j['title']); ?></td>
            <td><?php echo htmlspecialchars($j['location']); ?></td>
            <td><?php echo htmlspecialchars($j['created_at']); ?></td>
            <td>
                <a class="btn btn-small" href="company-edit-job.php?id=<?php echo $j['id']; ?>">Edit</a>
                <form class="inline-form" method="post" action="company-delete-job.php" onsubmit="return confirm('Delete this job? This cannot be undone.');">
                    <input type="hidden" name="job_id" value="<?php echo $j['id']; ?>">
                    <button class="btn btn-small btn-danger" type="submit">Delete</button>
                </form>
            </td>
        </tr>
    <?php endwhile; ?>
</table>
<?php require '../footer.php'; ?>
