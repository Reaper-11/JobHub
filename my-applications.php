<?php
require 'db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$uid = (int) $_SESSION['user_id'];

$sql = "SELECT a.*, j.title, j.company, j.location
        FROM applications a
        JOIN jobs j ON j.id = a.job_id
        WHERE a.user_id = $uid
        ORDER BY a.applied_at DESC";
$res = $conn->query($sql);

require 'header.php';
?>
<h1>My Applications</h1>
<table>
    <tr>
        <th>Job Title</th>
        <th>Company</th>
        <th>Location</th>
        <th>Status</th>
        <th>Rejection Reason</th>
        <th>Applied At</th>
        <th>Action</th>
        <th>Cancel</th>
    </tr>
    <?php while ($row = $res->fetch_assoc()): ?>
        <?php $currentStatus = $row['status'] ?? 'pending'; ?>
        <tr>
            <td><a href="job-detail.php?id=<?php echo $row['job_id']; ?>">
                <?php echo htmlspecialchars($row['title']); ?></a></td>
            <td><?php echo htmlspecialchars($row['company']); ?></td>
            <td><?php echo htmlspecialchars($row['location']); ?></td>
            <td><?php echo htmlspecialchars(ucfirst($currentStatus)); ?></td>
            <td>
                <?php
                $reason = $row['rejection_reason'] ?? '';
                echo ($currentStatus === 'rejected' && $reason !== '') ? htmlspecialchars($reason) : '-';
                ?>
            </td>
            <td><?php echo htmlspecialchars($row['applied_at']); ?></td>
            <td><a class="btn btn-small" href="my-application-edit.php?id=<?php echo $row['id']; ?>">Edit</a></td>
            <td>
                <form method="post" action="my-application-cancel.php" class="inline-form">
                    <input type="hidden" name="app_id" value="<?php echo $row['id']; ?>">
                    <button type="submit" class="btn btn-small btn-danger">Cancel</button>
                </form>
            </td>
        </tr>
    <?php endwhile; ?>
</table>
<?php require 'footer.php'; ?>
