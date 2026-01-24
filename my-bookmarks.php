<?php
require 'db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$uid = (int) $_SESSION['user_id'];
$msg = "";
$msgType = "alert-success";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_bookmark'])) {
    $bookmarkId = (int) ($_POST['bookmark_id'] ?? 0);
    if ($bookmarkId > 0) {
        $stmt = $conn->prepare("DELETE FROM bookmarks WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $bookmarkId, $uid);
        if ($stmt->execute()) {
            $msg = "Bookmark removed.";
            $msgType = "alert-success";
        } else {
            $msg = "Could not remove bookmark. Please try again.";
            $msgType = "alert-error";
        }
        $stmt->close();
    } else {
        $msg = "Invalid bookmark selection.";
        $msgType = "alert-error";
    }
}

$sql = "SELECT j.*, b.id AS bookmark_id FROM bookmarks b
        JOIN jobs j ON j.id = b.job_id
        WHERE b.user_id = $uid AND j.is_approved=1
        ORDER BY b.created_at DESC";
$res = $conn->query($sql);

require 'header.php';
?>
<h1>My Bookmarked Jobs</h1>
<?php if ($msg): ?>
    <div class="alert <?php echo $msgType; ?>"><?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>
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
            <form method="post" class="inline-form">
                <input type="hidden" name="bookmark_id" value="<?php echo (int) $row['bookmark_id']; ?>">
                <button type="submit" name="remove_bookmark" class="btn btn-danger btn-small"
                        onclick="return confirm('Remove this bookmark?');">Remove</button>
            </form>
        </div>
    <?php endwhile; ?>
</div>
<?php require 'footer.php'; ?>
