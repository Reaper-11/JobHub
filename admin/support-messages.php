<?php
require '../db.php';
require_once '../includes/support_helper.php';

require_role('admin');

$flash = support_get_flash('admin');
$counts = support_fetch_counts($conn);
$filterOptions = support_filter_options();
$activeFilter = support_normalize_filter($_GET['filter'] ?? 'all');

$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$activeTotal = support_count_messages($conn, $activeFilter);
$totalPages = max(1, (int)ceil(($activeTotal ?: 0) / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}

$messages = support_fetch_messages_page($conn, $page, $perPage, $activeFilter);
?>

<?php require 'admin-header.php'; ?>

<style>
    .support-filter-bar {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        padding: 1rem;
        border-bottom: 1px solid #243041;
        background: rgba(15, 23, 42, 0.88);
    }
    .support-filter-tab {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        min-height: 36px;
        padding: 0.45rem 0.75rem;
        border: 1px solid #334155;
        border-radius: 8px;
        background: #111827;
        color: #cbd5e1;
        font-size: 0.85rem;
        font-weight: 600;
        text-decoration: none;
        transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
    }
    .support-filter-tab:hover {
        border-color: #3b82f6;
        color: #f8fafc;
        background: rgba(59, 130, 246, 0.16);
    }
    .support-filter-tab.active {
        background: #3b82f6;
        border-color: #3b82f6;
        color: #fff;
    }
    .support-filter-count {
        min-width: 1.5rem;
        padding: 0.12rem 0.4rem;
        border-radius: 999px;
        background: rgba(148, 163, 184, 0.18);
        color: inherit;
        font-size: 0.72rem;
        line-height: 1.35;
        text-align: center;
    }
    .support-filter-tab.active .support-filter-count {
        background: rgba(255, 255, 255, 0.22);
    }
</style>

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
    <div class="support-filter-bar" aria-label="Support message filters">
        <?php foreach ($filterOptions as $filterKey => $filterLabel): ?>
            <?php
                $filterKey = (string)$filterKey;
                $isActiveFilter = $activeFilter === $filterKey;
                $filterCount = (int)($counts[$filterKey] ?? 0);
            ?>
            <a
                href="<?= htmlspecialchars(support_messages_url($filterKey, 1)) ?>"
                class="support-filter-tab <?= $isActiveFilter ? 'active' : '' ?>"
                aria-current="<?= $isActiveFilter ? 'page' : 'false' ?>"
            >
                <span><?= htmlspecialchars($filterLabel) ?></span>
                <span class="support-filter-count"><?= $filterCount ?></span>
            </a>
        <?php endforeach; ?>
    </div>
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
                        <td colspan="9" class="text-center py-4">
                            No <?= htmlspecialchars(strtolower($filterOptions[$activeFilter] ?? '')) ?> support messages found.
                        </td>
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
                                    <a href="<?= htmlspecialchars(support_view_url((int)$message['id'], $activeFilter, $page)) ?>" class="btn btn-sm btn-outline-primary">View</a>

                                    <?php if (empty($message['is_deleted'])): ?>
                                        <form method="post" action="support-toggle-read.php" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                            <input type="hidden" name="message_id" value="<?= (int)$message['id'] ?>">
                                            <input type="hidden" name="target_state" value="<?= !empty($message['is_read']) ? 'unread' : 'read' ?>">
                                            <input type="hidden" name="filter" value="<?= htmlspecialchars($activeFilter) ?>">
                                            <input type="hidden" name="page" value="<?= (int)$page ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                <?= !empty($message['is_read']) ? 'Mark Unread' : 'Mark Read' ?>
                                            </button>
                                        </form>

                                        <?php if (($message['status'] ?? 'new') !== 'resolved'): ?>
                                            <form method="post" action="support-resolve.php" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                                <input type="hidden" name="message_id" value="<?= (int)$message['id'] ?>">
                                                <input type="hidden" name="filter" value="<?= htmlspecialchars($activeFilter) ?>">
                                                <input type="hidden" name="page" value="<?= (int)$page ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-success">Resolve</button>
                                            </form>
                                        <?php endif; ?>

                                        <form method="post" action="support-delete.php" class="d-inline" onsubmit="return confirm('Delete this support message?');">
                                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                            <input type="hidden" name="message_id" value="<?= (int)$message['id'] ?>">
                                            <input type="hidden" name="filter" value="<?= htmlspecialchars($activeFilter) ?>">
                                            <input type="hidden" name="page" value="<?= (int)$page ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="badge bg-secondary align-self-center">Deleted</span>
                                    <?php endif; ?>
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
                    <a class="page-link" href="<?= htmlspecialchars(support_messages_url($activeFilter, $i)) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
<?php endif; ?>

<?php require '../footer.php'; ?>
