<?php
require 'db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$uid = (int) $_SESSION['user_id'];

$sql = "SELECT j.* FROM bookmarks b
        JOIN jobs j ON j.id = b.job_id
        WHERE b.user_id = $uid AND j.is_approved=1
        ORDER BY b.created_at DESC";
$res = $conn->query($sql);

require 'header.php';
?>
<h1>My Bookmarked Jobs</h1>
<div class="jobs-grid">
    <?php while ($row = $res->fetch_assoc()): ?>
        <div class="card">
            <h3><?php echo htmlspecialchars($row['title']); ?></h3>
            <p class="meta">
                <?php echo htmlspecialchars($row['company']); ?> |
                <?php echo htmlspecialchars($row['location']); ?>
                <span class="badge"><?php echo htmlspecialchars($row['type']); ?></span>
            </p>
            <a class="btn btn-small" href="job-detail.php?id=<?php echo $row['id']; ?>">View Job</a>
        </div>
    <?php endwhile; ?>
</div>
<?php require 'footer.php'; ?>
