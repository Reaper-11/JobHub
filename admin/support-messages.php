<?php
require '../db.php';
require_once '../includes/support_helper.php';

require_role('admin');

$flash = support_get_flash('admin');
$counts = support_fetch_counts($conn);

$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$totalPages = max(1, (int)ceil(($counts['total'] ?: 0) / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}

$messages = support_fetch_messages_page($conn, $page, $perPage);
?>

<?php require 'admin-header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0">Support Messages</h1>
    <span class="badge bg-dark fs-6">Total: <?= (int)$counts['total'] ?></span>
</div>

<?php if (!support_table_exists($conn)): ?>
    <div class="alert alert-warning">
        Support module database table is missing. Run the support SQL first.
    </div>
<?php endif; ?>

<?php if ($flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
        <?= htmlspecialchars($flash['message']) ?>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-sm-6">
        <div class="card shadow-sm border-0">
            <div class="card-body text-center">
                <div class="text-muted small">New</div>
                <h3 class="mb-0"><?= (int)$counts['new'] ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card shadow-sm border-0">
            <div class="card-body text-center">
                <div class="text-muted small">Unread</div>
                <h3 class="mb-0"><?= (int)$counts['unread'] ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card shadow-sm border-0">
            <div class="card-body text-center">
                <div class="text-muted small">Replied</div>
                <h3 class="mb-0"><?= (int)$counts['replied'] ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card shadow-sm border-0">
            <div class="card-body text-center">
                <div class="text-muted small">Resolved</div>
                <h3 class="mb-0"><?= (int)$counts['resolved'] ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Read</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($messages)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-4">No support messages found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($messages as $message): ?>
                        <tr>
                            <td><?= (int)$message['id'] ?></td>
                            <td><?= htmlspecialchars($message['sender_name']) ?></td>
                            <td><?= htmlspecialchars($message['sender_email']) ?></td>
                            <td><?= htmlspecialchars(support_role_label($message['sender_role'] ?? 'guest')) ?></td>
                            <td><?= htmlspecialchars($message['subject']) ?></td>
                            <td>
                                <span class="badge <?= support_status_badge_class($message['status'] ?? 'new') ?>">
                                    <?= htmlspecialchars(support_status_label($message['status'] ?? 'new')) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= !empty($message['is_read']) ? 'bg-success' : 'bg-warning text-dark' ?>">
                                    <?= !empty($message['is_read']) ? 'Read' : 'Unread' ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($message['created_at']))) ?></td>
                            <td>
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="support-view.php?id=<?= (int)$message['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>

                                    <form method="post" action="support-toggle-read.php" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                        <input type="hidden" name="message_id" value="<?= (int)$message['id'] ?>">
                                        <input type="hidden" name="target_state" value="<?= !empty($message['is_read']) ? 'unread' : 'read' ?>">
                                        <input type="hidden" name="page" value="<?= (int)$page ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                                            <?= !empty($message['is_read']) ? 'Mark Unread' : 'Mark Read' ?>
                                        </button>
                                    </form>

                                    <?php if (($message['status'] ?? 'new') !== 'resolved'): ?>
                                        <form method="post" action="support-resolve.php" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                            <input type="hidden" name="message_id" value="<?= (int)$message['id'] ?>">
                                            <input type="hidden" name="page" value="<?= (int)$page ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-success">Resolve</button>
                                        </form>
                                    <?php endif; ?>

                                    <form method="post" action="support-delete.php" class="d-inline" onsubmit="return confirm('Delete this support message?');">
                                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                        <input type="hidden" name="message_id" value="<?= (int)$message['id'] ?>">
                                        <input type="hidden" name="page" value="<?= (int)$page ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($totalPages > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
<?php endif; ?>

<?php require '../footer.php'; ?>
