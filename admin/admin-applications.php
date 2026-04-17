<?php
// admin/admin-applications.php
require '../db.php';
require_role('admin');

$allowedStatuses = ['all', 'pending', 'shortlisted', 'interview', 'rejected', 'approved'];
$statusFilter    = strtolower(trim((string)($_GET['status'] ?? 'all')));
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'all';
}

$perPage = 20;
$page    = max(1, (int)($_GET['page'] ?? 1));

// Count by status for tab badges.
$counts = [
    'all'         => (int)db_query_value("SELECT COUNT(*) FROM applications", '', [], 0),
    'pending'     => (int)db_query_value("SELECT COUNT(*) FROM applications WHERE status = 'pending'",     '', [], 0),
    'shortlisted' => (int)db_query_value("SELECT COUNT(*) FROM applications WHERE status = 'shortlisted'", '', [], 0),
    'interview'   => (int)db_query_value("SELECT COUNT(*) FROM applications WHERE status = 'interview'",   '', [], 0),
    'rejected'    => (int)db_query_value("SELECT COUNT(*) FROM applications WHERE status = 'rejected'",    '', [], 0),
    'approved'    => (int)db_query_value("SELECT COUNT(*) FROM applications WHERE status = 'approved'",    '', [], 0),
];

// Build WHERE clause.
$where      = '';
$whereTypes = '';
$whereParams = [];
if ($statusFilter !== 'all') {
    $where       = 'WHERE a.status = ?';
    $whereTypes  = 's';
    $whereParams = [$statusFilter];
}

$totalRecords = (int)db_query_value(
    "SELECT COUNT(*) FROM applications a {$where}",
    $whereTypes,
    $whereParams,
    0
);
$totalPages = $totalRecords > 0 ? (int)ceil($totalRecords / $perPage) : 1;
if ($page > $totalPages) { $page = $totalPages; }
$offset = ($page - 1) * $perPage;

$queryTypes  = $whereTypes . 'ii';
$queryParams = array_merge($whereParams, [$perPage, $offset]);

$applications = db_query_all("
    SELECT a.id, a.status, a.applied_at, a.cover_letter, a.cv_path,
           u.name AS user_name, u.email AS user_email, u.cv_path AS user_cv_path,
           j.title AS job_title, j.company AS job_company, j.location AS job_location
    FROM applications a
    JOIN users u ON u.id = a.user_id
    JOIN jobs  j ON j.id = a.job_id
    {$where}
    ORDER BY a.applied_at DESC
    LIMIT ? OFFSET ?
", $queryTypes, $queryParams);

$tabUrl  = static fn(string $s): string => 'admin-applications.php?status=' . urlencode($s) . '&page=1';
$pageUrl = static fn(int $p): string    => 'admin-applications.php?status=' . urlencode($statusFilter) . '&page=' . max(1, $p);

$paginationStart = max(1, $page - 2);
$paginationEnd   = min($totalPages, $page + 2);
?>

<?php require 'admin-header.php'; ?>

<h1 class="mb-4">Job Applications</h1>

<!-- Summary stat cards -->
<div class="row g-3 mb-4">
    <div class="col-md-2 col-sm-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body text-center">
                <h6 class="text-muted mb-1 small">Total</h6>
                <h3 class="mb-0"><?= number_format($counts['all']) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body text-center">
                <h6 class="text-muted mb-1 small">Pending</h6>
                <h3 class="mb-0 text-warning"><?= number_format($counts['pending']) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body text-center">
                <h6 class="text-muted mb-1 small">Shortlisted</h6>
                <h3 class="mb-0 text-primary"><?= number_format($counts['shortlisted']) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body text-center">
                <h6 class="text-muted mb-1 small">Interview</h6>
                <h3 class="mb-0 text-info"><?= number_format($counts['interview']) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body text-center">
                <h6 class="text-muted mb-1 small">Approved</h6>
                <h3 class="mb-0 text-success"><?= number_format($counts['approved']) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body text-center">
                <h6 class="text-muted mb-1 small">Rejected</h6>
                <h3 class="mb-0 text-danger"><?= number_format($counts['rejected']) ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Status filter tabs -->
<ul class="nav nav-tabs mb-4">
    <?php
    $tabDefs = [
        'all'         => ['All',         'text-bg-secondary'],
        'pending'     => ['Pending',      'bg-warning text-dark'],
        'shortlisted' => ['Shortlisted',  'bg-primary'],
        'interview'   => ['Interview',    'bg-info text-dark'],
        'approved'    => ['Approved',     'bg-success'],
        'rejected'    => ['Rejected',     'bg-danger'],
    ];
    foreach ($tabDefs as $tabKey => [$tabLabel, $badgeClass]):
    ?>
        <li class="nav-item">
            <a class="nav-link <?= $statusFilter === $tabKey ? 'active' : '' ?>"
               href="<?= htmlspecialchars($tabUrl($tabKey)) ?>">
                <?= htmlspecialchars($tabLabel) ?>
                <span class="badge <?= $badgeClass ?> ms-1"><?= (int)$counts[$tabKey] ?></span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Job Title</th>
                        <th>Applicant</th>
                        <th>Email</th>
                        <th>CV</th>
                        <th>Status</th>
                        <th>Applied</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($applications)): ?>
                    <tr><td colspan="8" class="text-center py-5 text-muted">No applications found.</td></tr>
                <?php else: ?>
                    <?php foreach ($applications as $app): ?>
                        <?php
                        $appStatus = strtolower($app['status'] ?? 'pending');
                        $badge = match($appStatus) {
                            'pending'     => 'bg-warning text-dark',
                            'shortlisted' => 'bg-primary',
                            'interview'   => 'bg-info text-dark',
                            'approved'    => 'bg-success',
                            'rejected'    => 'bg-danger',
                            default       => 'bg-secondary',
                        };
                        ?>
                        <tr>
                            <td><?= (int)$app['id'] ?></td>
                            <td><?= htmlspecialchars($app['job_title']) ?></td>
                            <td><?= htmlspecialchars($app['user_name']) ?></td>
                            <td><?= htmlspecialchars($app['user_email']) ?></td>
                            <td>
                                <?php $cvPath = $app['cv_path'] ?: ($app['user_cv_path'] ?? ''); ?>
                                <?php if (!empty($cvPath) && jobhub_cv_is_stored_path($cvPath)): ?>
                                    <a href="../cv-download.php?scope=application&id=<?= (int)$app['id'] ?>" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">View CV</a>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge <?= $badge ?>"><?= ucfirst($appStatus) ?></span></td>
                            <td><?= date('Y-m-d H:i', strtotime($app['applied_at'])) ?></td>
                            <td>
                                <a href="application-details.php?id=<?= (int)$app['id'] ?>"
                                   class="btn btn-sm btn-outline-primary">View Details</a>
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
    <nav class="mt-4" aria-label="Applications pagination">
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
