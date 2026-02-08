<?php
// my-bookmarks.php
require 'db.php';
$bodyClass = 'user-ui';
require 'header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_bookmark'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $alert = "Invalid request.";
        $alert_type = 'danger';
    } else {
        $bookmark_id = (int)($_POST['bookmark_id'] ?? 0);
        if ($bookmark_id > 0) {
            $stmt = $conn->prepare("DELETE FROM bookmarks WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $bookmark_id, $user_id);
            if ($stmt->execute()) {
                $alert = "Bookmark removed successfully.";
                $alert_type = 'success';
            } else {
                $alert = "Could not remove bookmark.";
                $alert_type = 'danger';
            }
            $stmt->close();
        }
    }
}

// Fetch bookmarked jobs
$sql = "SELECT b.id AS bookmark_id, b.created_at AS bookmarked_at,
               j.id, j.title, j.company, j.location, j.type, j.category,
               j.salary, j.created_at AS posted_at
        FROM bookmarks b
        JOIN jobs j ON b.job_id = j.id
        LEFT JOIN companies c ON j.company_id = c.id
        WHERE b.user_id = ?
          AND j.status = 'active'
          AND (j.company_id IS NULL OR c.is_approved = 1)
        ORDER BY b.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$bookmarks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<h1 class="mb-4">My Bookmarked Jobs</h1>

<?php if (isset($alert)): ?>
    <div class="alert alert-<?= htmlspecialchars($alert_type) ?>">
        <?= htmlspecialchars($alert) ?>
    </div>
<?php endif; ?>

<?php if (empty($bookmarks)): ?>
    <div class="alert alert-info">
        You haven't bookmarked any jobs yet.<br>
        <a href="index.php" class="alert-link">Browse open positions</a>
    </div>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($bookmarks as $job): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm position-relative">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title mb-2">
                            <a href="job-detail.php?id=<?= $job['id'] ?>" class="text-decoration-none text-dark">
                                <?= htmlspecialchars($job['title']) ?>
                            </a>
                        </h5>

                        <p class="text-muted mb-2 small">
                            <?= htmlspecialchars($job['company']) ?> • 
                            <?= htmlspecialchars($job['location']) ?>
                            <span class="badge bg-light text-dark ms-2 border">
                                <?= htmlspecialchars($job['type'] ?? 'Full-time') ?>
                            </span>
                        </p>

                        <?php if (!empty($job['salary'])): ?>
                            <p class="small mb-2">
                                <strong>Salary:</strong> <?= htmlspecialchars($job['salary']) ?>
                            </p>
                        <?php endif; ?>

                        <p class="small text-muted mb-3">
                            Bookmarked <?= date('M d, Y', strtotime($job['bookmarked_at'])) ?>
                        </p>

                        <div class="mt-auto d-flex gap-2">
                            <a href="job-detail.php?id=<?= $job['id'] ?>" 
                               class="btn btn-outline-primary btn-sm flex-grow-1">
                                View Details
                            </a>

                            <form method="post" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                <input type="hidden" name="bookmark_id" value="<?= $job['bookmark_id'] ?>">
                                <input type="hidden" name="remove_bookmark" value="1">
                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                        onclick="return confirm('Remove this bookmark?');">
                                    Remove
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require 'footer.php'; ?>
