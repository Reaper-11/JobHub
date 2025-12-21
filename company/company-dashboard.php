<?php
require '../db.php';
if (!isset($_SESSION['company_id'])) {
    header("Location: company-login.php");
    exit;
}
$cid = (int) $_SESSION['company_id'];
$statusRes = $conn->query("SELECT is_approved FROM companies WHERE id=$cid");
$isApproved = $statusRes ? (int) $statusRes->fetch_assoc()['is_approved'] : 0;

$countJobs = $conn->query(
    "SELECT COUNT(*) c FROM jobs WHERE company_id=$cid"
)->fetch_assoc()['c'];

$jobs = $conn->query(
    "SELECT * FROM jobs WHERE company_id=$cid ORDER BY created_at DESC"
);
$basePath = '../';
require '../header.php';
?>
<h1>Company Dashboard</h1>
<div class="card">
    <p>Welcome, <?php echo htmlspecialchars($_SESSION['company_name']); ?></p>
    <p>Status: <?php echo $isApproved ? 'Approved' : 'Pending Approval'; ?></p>
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
            <td><a class="btn btn-small" href="company-edit-job.php?id=<?php echo $j['id']; ?>">Edit</a></td>
        </tr>
    <?php endwhile; ?>
</table>
<?php require '../footer.php'; ?>
