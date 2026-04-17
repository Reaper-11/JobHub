<?php
// admin/activity-monitor.php
require '../db.php';
require_role('admin');

// --- Filters ---
$allowedRoles = ['all', 'admin', 'company', 'jobseeker'];
$roleFilter   = strtolower(trim((string)($_GET['role'] ?? 'all')));
if (!in_array($roleFilter, $allowedRoles, true)) { $roleFilter = 'all'; }

$allowedDates  = ['all', 'today', 'week', 'month'];
$dateFilter    = strtolower(trim((string)($_GET['date'] ?? 'all')));
if (!in_array($dateFilter, $allowedDates, true)) { $dateFilter = 'all'; }

$search = trim((string)($_GET['search'] ?? ''));

// --- Pagination ---
$perPage = 20;
$page    = max(1, (int)($_GET['page'] ?? 1));

// --- Build WHERE ---
$whereClauses = [];
$whereTypes   = '';
$whereParams  = [];

if ($roleFilter === 'jobseeker') {
    $whereClauses[] = "a.actor_role IN ('seeker', 'jobseeker')";
} elseif ($roleFilter !== 'all') {
    $whereClauses[] = 'a.actor_role = ?';
    $whereTypes    .= 's';
    $whereParams[]  = $roleFilter;
}

if ($dateFilter === 'today') {
    $whereClauses[] = 'DATE(a.created_at) = CURDATE()';
} elseif ($dateFilter === 'week') {
    $whereClauses[] = 'a.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
} elseif ($dateFilter === 'month') {
    $whereClauses[] = 'a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
}

if ($search !== '') {
    $whereClauses[] = 'a.description LIKE ?';
    $whereTypes    .= 's';
    $whereParams[]  = '%' . $search . '%';
}

$where = empty($whereClauses) ? '' : 'WHERE ' . implode(' AND ', $whereClauses);

$totalRecords = (int)db_query_value(
    "SELECT COUNT(*) FROM activity_logs a {$where}",
    $whereTypes,
    $whereParams,
    0
);
$totalPages = $totalRecords > 0 ? (int)ceil($totalRecords / $perPage) : 1;
if ($page > $totalPages) { $page = $totalPages; }
$offset = ($page - 1) * $perPage;

$queryTypes  = $whereTypes . 'ii';
$queryParams = array_merge($whereParams, [$perPage, $offset]);

$activities = db_query_all("
    SELECT a.*,
           u.name       AS user_name,
           c.name       AS company_name,
           ad.username  AS admin_name
    FROM activity_logs a
    LEFT JOIN users    u  ON a.actor_role IN ('seeker', 'jobseeker') AND a.user_id = u.id
    LEFT JOIN companies c ON a.actor_role = 'company'               AND a.user_id = c.id
    LEFT JOIN admins   ad ON a.actor_role = 'admin'                 AND a.user_id = ad.id
    {$where}
    ORDER BY a.created_at DESC
    LIMIT ? OFFSET ?
", $queryTypes, $queryParams);

// Human-readable activity type labels.
$activityTypeLabels = [
    'job_approved'                    => 'Job Approved',
    'job_rejected'                    => 'Job Rejected',
    'user_blocked'                    => 'User Blocked',
    'user_unblocked'                  => 'User Unblocked',
    'user_removed'                    => 'User Removed',
    'user_restored'                   => 'User Restored',
    'company_verification_approved'   => 'Verification Approved',
    'company_verification_rejected'   => 'Verification Rejected',
    'jobseeker_self_deleted'          => 'Jobseeker Deleted Account',
    'company_self_deleted'            => 'Company Deleted Account',
    'support_reply'                   => 'Support Reply Sent',
    'company_approved'                => 'Company Approved',
    'company_rejected'                => 'Company Rejected',
    'company_suspended'               => 'Company Suspended',
    'company_hold'                    => 'Company Put On Hold',
    'company_activated'               => 'Company Activated',
];

// URL helper that keeps all filters.
$filterUrl = static function (array $overrides) use ($roleFilter, $dateFilter, $search): string {
    $params = array_filter([
        'role'   => $overrides['role']   ?? ($roleFilter !== 'all' ? $roleFilter : ''),
        'date'   => $overrides['date']   ?? ($dateFilter !== 'all' ? $dateFilter : ''),
        'search' => $overrides['search'] ?? ($search !== '' ? $search : ''),
        'page'   => $overrides['page']   ?? 1,
    ], fn($v) => $v !== '' && $v !== null);
    return 'activity-monitor.php' . (!empty($params) ? '?' . http_build_query($params) : '');
};

$pageUrl = static fn(int $p): string => $filterUrl(['page' => $p]);

$paginationStart = max(1, $page - 2);
$paginationEnd   = min($totalPages, $page + 2);
?>

<?php require 'admin-header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0">Activity Monitor</h1>
    <span class="badge bg-secondary fs-6"><?= number_format($totalRecords) ?> record<?= $totalRecords !== 1 ? 's' : '' ?></span>
</div>

<!-- Filters -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="get" action="activity-monitor.php" class="row g-2 align-items-end">

            <!-- Role filter -->
            <div class="col-md-3">
                <label class="form-label small mb-1">Actor Role</label>
                <select name="role" class="form-select form-select-sm">
                    <option value="all"       <?= $roleFilter === 'all'       ? 'selected' : '' ?>>All Roles</option>
                    <option value="admin"     <?= $roleFilter === 'admin'     ? 'selected' : '' ?>>Admin</option>
                    <option value="company"   <?= $roleFilter === 'company'   ? 'selected' : '' ?>>Company</option>
                    <option value="jobseeker" <?= $roleFilter === 'jobseeker' ? 'selected' : '' ?>>Job Seeker</option>
                </select>
            </div>

            <!-- Date range filter -->
            <div class="col-md-3">
                <label class="form-label small mb-1">Date Range</label>
                <select name="date" class="form-select form-select-sm">
                    <option value="all"   <?= $dateFilter === 'all'   ? 'selected' : '' ?>>All Time</option>
                    <option value="today" <?= $dateFilter === 'today' ? 'selected' : '' ?>>Today</option>
                    <option value="week"  <?= $dateFilter === 'week'  ? 'selected' : '' ?>>Last 7 Days</option>
                    <option value="month" <?= $dateFilter === 'month' ? 'selected' : '' ?>>Last 30 Days</option>
                </select>
            </div>

            <!-- Description search -->
            <div class="col-md-4">
                <label class="form-label small mb-1">Search Description</label>
                <input type="text" name="search" class="form-control form-control-sm"
                       placeholder="Search activity description…"
                       value="<?= htmlspecialchars($search) ?>">
            </div>

            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
                <a href="activity-monitor.php" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Activity table -->
<div class="table-responsive">
    <table class="table table-hover table-striped align-middle">
        <thead class="table-light">
            <tr>
                <th>Time</th>
                <th>Actor</th>
                <th>Role</th>
                <th>Action</th>
                <th>Description</th>
                <th>Target</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($activities)): ?>
            <tr><td colspan="6" class="text-center py-4">No activity logs found.</td></tr>
        <?php else: ?>
            <?php foreach ($activities as $activity):
                $actorName = trim((string)($activity['user_name'] ?: ($activity['company_name'] ?: ($activity['admin_name'] ?: ''))));
                if ($actorName === '' && in_array((string)($activity['activity_type'] ?? ''), ['jobseeker_self_deleted', 'company_self_deleted'], true)) {
                    $parts = explode(':', (string)($activity['description'] ?? ''), 2);
                    $actorName = trim((string)($parts[1] ?? ''));
                }
                if ($actorName === '') { $actorName = 'System'; }

                $targetText = trim((string)($activity['target_type'] ?? '')) !== ''
                    ? ucfirst((string)$activity['target_type']) . ' #' . (int)$activity['target_id']
                    : '—';

                $actType  = (string)($activity['activity_type'] ?? '');
                $typeLabel = $activityTypeLabels[$actType] ?? ucwords(str_replace('_', ' ', $actType));

                $actorRole = strtolower((string)($activity['actor_role'] ?? ''));
                $roleBadge = match(true) {
                    $actorRole === 'admin'                         => 'bg-primary',
                    $actorRole === 'company'                       => 'bg-warning text-dark',
                    in_array($actorRole, ['seeker','jobseeker'],true) => 'bg-success',
                    default                                        => 'bg-secondary',
                };
            ?>
                <tr>
                    <td style="white-space:nowrap;"><?= htmlspecialchars(date('Y-m-d H:i', strtotime($activity['created_at']))) ?></td>
                    <td><?= htmlspecialchars($actorName) ?></td>
                    <td><span class="badge <?= $roleBadge ?>"><?= htmlspecialchars(ucfirst($actorRole ?: 'system')) ?></span></td>
                    <td><span class="fw-semibold"><?= htmlspecialchars($typeLabel) ?></span></td>
                    <td class="text-muted small"><?= htmlspecialchars($activity['description']) ?></td>
                    <td><?= htmlspecialchars($targetText) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
    <nav class="mt-4" aria-label="Activity pagination">
        <ul class="pagination justify-content-end mb-0">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= $page > 1 ? htmlspecialchars($pageUrl($page - 1)) : '#' ?>">Previous</a>
            </li>
            <?php for ($pn = $paginationStart; $pn <= $paginationEnd; $pn++): ?>
                <li class="page-item <?= $pn === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= htmlspecialchars($pageUrl($pn)) ?>"><?= (int)$pn ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= $page < $totalPages ? htmlspecialchars($pageUrl($page + 1)) : '#' ?>">Next</a>
            </li>
        </ul>
    </nav>
<?php endif; ?>

<?php require '../footer.php'; ?>
