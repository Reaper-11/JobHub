<?php
require '../db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit;
}

$activities = db_query_all("
    SELECT a.*,
           u.name AS user_name,
           c.name AS company_name,
           ad.username AS admin_name
    FROM activity_logs a
    LEFT JOIN users u ON a.actor_role = 'seeker' AND a.user_id = u.id
    LEFT JOIN companies c ON a.actor_role = 'company' AND a.user_id = c.id
    LEFT JOIN admins ad ON a.actor_role = 'admin' AND a.user_id = ad.id
    ORDER BY a.created_at DESC
    LIMIT 100
");
?>

<?php require 'admin-header.php'; ?>

<h1 class="mb-4">Activity Monitor</h1>

<div class="table-responsive">
    <table class="table table-hover table-striped align-middle">
        <thead class="table-light">
            <tr>
                <th>Time</th>
                <th>Actor</th>
                <th>Role</th>
                <th>Activity Type</th>
                <th>Description</th>
                <th>Target</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($activities)): ?>
            <tr><td colspan="6" class="text-center py-4">No activity logs found.</td></tr>
        <?php else: ?>
            <?php foreach ($activities as $activity): ?>
                <?php
                $actorName = $activity['user_name'] ?: ($activity['company_name'] ?: ($activity['admin_name'] ?: 'System'));
                $targetText = trim((string)($activity['target_type'] ?? '')) !== '' ? ucfirst((string)$activity['target_type']) . ' #' . (int)$activity['target_id'] : 'â€”';
                ?>
                <tr>
                    <td><?= htmlspecialchars($activity['created_at']) ?></td>
                    <td><?= htmlspecialchars($actorName) ?></td>
                    <td><?= htmlspecialchars(ucfirst((string)($activity['actor_role'] ?? 'system'))) ?></td>
                    <td><?= htmlspecialchars($activity['activity_type']) ?></td>
                    <td><?= htmlspecialchars($activity['description']) ?></td>
                    <td><?= htmlspecialchars($targetText) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require '../footer.php'; ?>
