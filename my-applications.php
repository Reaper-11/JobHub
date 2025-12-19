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
        <th>Applied At</th>
    </tr>
    <?php while ($row = $res->fetch_assoc()): ?>
        <tr>
            <td><a href="job-detail.php?id=<?php echo $row['job_id']; ?>">
                <?php echo htmlspecialchars($row['title']); ?></a></td>
            <td><?php echo htmlspecialchars($row['company']); ?></td>
            <td><?php echo htmlspecialchars($row['location']); ?></td>
            <td><?php echo htmlspecialchars(ucfirst($row['status'] ?? 'pending')); ?></td>
            <td><?php echo htmlspecialchars($row['applied_at']); ?></td>
        </tr>
    <?php endwhile; ?>
</table>
<?php require 'footer.php'; ?>
