<?php
// my-applications.php
require 'db.php';
$bodyClass = 'user-ui';
require 'header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

$sql = "SELECT a.id, a.job_id, a.status, a.cover_letter, a.applied_at,
               j.title, j.company, j.location, j.type
        FROM applications a
        JOIN jobs j ON a.job_id = j.id
        WHERE a.user_id = ?
        ORDER BY a.applied_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<h1 class="mb-4">My Applications</h1>

<?php if (empty($applications)): ?>
    <div class="alert alert-info">
        You haven't applied to any jobs yet.
        <a href="index.php" class="alert-link">Browse jobs</a>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Job Title</th>
                    <th>Company</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Applied On</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($applications as $app): ?>
                <tr>
                    <td>
                        <a href="job-detail.php?id=<?= $app['job_id'] ?>" class="text-decoration-none">
                            <?= htmlspecialchars($app['title']) ?>
                        </a>
                    </td>
                    <td><?= htmlspecialchars($app['company']) ?></td>
                    <td><?= htmlspecialchars($app['location']) ?></td>
                    <td>
                        <?php
                        $status = strtolower($app['status'] ?? 'pending');
                        $badge = match($status) {
                            'pending'     => 'bg-warning',
                            'shortlisted' => 'bg-primary',
                            'approved'    => 'bg-success',
                            'rejected'    => 'bg-danger',
                            default       => 'bg-secondary'
                        };
                        ?>
                        <span class="badge <?= $badge ?>"><?= ucfirst($status) ?></span>
                    </td>
                    <td><?= date('M d, Y', strtotime($app['applied_at'])) ?></td>
                    <td>
                        <a href="my-application-edit.php?id=<?= $app['id'] ?>" 
                           class="btn btn-sm btn-outline-primary">Edit Cover Letter</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require 'footer.php'; ?>
