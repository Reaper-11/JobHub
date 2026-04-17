<?php
require '../db.php';
require_once '../includes/company_verification_helper.php';

require_role('admin');

$verificationAdminBaseUrl = 'company-verifications.php';
$status = strtolower(trim((string)($_GET['status'] ?? 'all')));
if (!in_array($status, ['all', 'pending', 'approved', 'rejected'], true)) {
    $status = 'all';
}

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = $status === 'all' ? 30 : 20;
$offset = ($page - 1) * $limit;

$whereClauses = ['verification_status IS NOT NULL'];
$whereTypes = '';
$whereParams = [];

if ($status !== 'all') {
    $whereClauses[] = 'verification_status = ?';
    $whereTypes .= 's';
    $whereParams[] = $status;
}

$where = implode(' AND ', $whereClauses);
$totalRequests = (int)db_query_value(
    "SELECT COUNT(*) FROM companies WHERE {$where}",
    $whereTypes,
    $whereParams,
    0
);
$totalPages = $totalRequests > 0 ? (int)ceil($totalRequests / $limit) : 1;

if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $limit;
}

$requestQueryTypes = $whereTypes . 'ii';
$requestQueryParams = array_merge($whereParams, [$limit, $offset]);

$requests = db_query_all("
    SELECT id, name, email, verification_company_name, verification_registration_number,
           verification_status, verification_submitted_at, verification_verified_at
    FROM companies
    WHERE {$where}
    ORDER BY verification_submitted_at DESC, created_at DESC
    LIMIT ? OFFSET ?
", $requestQueryTypes, $requestQueryParams);

$tabUrl = static function (string $targetStatus) use ($verificationAdminBaseUrl): string {
    return $verificationAdminBaseUrl . '?status=' . urlencode($targetStatus) . '&page=1';
};

$pageUrl = static function (int $targetPage) use ($verificationAdminBaseUrl, $status): string {
    return $verificationAdminBaseUrl . '?status=' . urlencode($status) . '&page=' . max(1, $targetPage);
};

$paginationStart = max(1, $page - 2);
$paginationEnd = min($totalPages, $page + 2);

$counts = [
    'pending' => db_query_value("SELECT COUNT(*) FROM companies WHERE verification_status = 'pending'"),
    'approved' => db_query_value("SELECT COUNT(*) FROM companies WHERE verification_status = 'approved'"),
    'rejected' => db_query_value("SELECT COUNT(*) FROM companies WHERE verification_status = 'rejected'"),
];
?>

<?php require 'admin-header.php'; ?>

<h1 class="mb-4">Company Verifications</h1>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm border-0">
            <div class="card-body text-center">
                <div class="text-muted small">Pending Requests</div>
                <h3 class="mb-0"><?= number_format((int)$counts['pending']) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0">
            <div class="card-body text-center">
                <div class="text-muted small">Approved Requests</div>
                <h3 class="mb-0 text-success"><?= number_format((int)$counts['approved']) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0">
            <div class="card-body text-center">
                <div class="text-muted small">Rejected Requests</div>
                <h3 class="mb-0 text-danger"><?= number_format((int)$counts['rejected']) ?></h3>
            </div>
        </div>
    </div>
</div>

<ul class="nav nav-tabs mb-4">
    <li class="nav-item"><a class="nav-link <?= $status === 'all' ? 'active' : '' ?>" href="<?= htmlspecialchars($tabUrl('all')) ?>">All</a></li>
    <li class="nav-item"><a class="nav-link <?= $status === 'pending' ? 'active' : '' ?>" href="<?= htmlspecialchars($tabUrl('pending')) ?>">Pending</a></li>
    <li class="nav-item"><a class="nav-link <?= $status === 'approved' ? 'active' : '' ?>" href="<?= htmlspecialchars($tabUrl('approved')) ?>">Approved</a></li>
    <li class="nav-item"><a class="nav-link <?= $status === 'rejected' ? 'active' : '' ?>" href="<?= htmlspecialchars($tabUrl('rejected')) ?>">Rejected</a></li>
</ul>

<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead class="table-light">
            <tr>
                <th>Company</th>
                <th>Email</th>
                <th>Registration No.</th>
                <th>Submitted</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($requests)): ?>
                <tr>
                    <td colspan="6" class="text-center py-4">No verification requests found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($requests as $request): ?>
                    <?php $requestStatus = get_company_verification_status($request); ?>
                    <tr>
                        <td><?= htmlspecialchars($request['verification_company_name'] ?: $request['name']) ?></td>
                        <td><?= htmlspecialchars($request['email']) ?></td>
                        <td><?= htmlspecialchars($request['verification_registration_number'] ?? '') ?></td>
                        <td><?= !empty($request['verification_submitted_at']) ? htmlspecialchars($request['verification_submitted_at']) : '-' ?></td>
                        <td><span class="badge <?= company_verification_badge_class($requestStatus) ?>"><?= company_verification_label($requestStatus) ?></span></td>
                        <td><a href="company-verification-view.php?id=<?= (int)$request['id'] ?>" class="btn btn-sm btn-outline-primary">Review</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
    <nav class="mt-4" aria-label="Verification pagination">
        <ul class="pagination justify-content-end mb-0">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= $page > 1 ? htmlspecialchars($pageUrl($page - 1)) : '#' ?>">Previous</a>
            </li>

            <?php for ($pageNumber = $paginationStart; $pageNumber <= $paginationEnd; $pageNumber++): ?>
                <li class="page-item <?= $pageNumber === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= htmlspecialchars($pageUrl($pageNumber)) ?>"><?= (int)$pageNumber ?></a>
                </li>
            <?php endfor; ?>

            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= $page < $totalPages ? htmlspecialchars($pageUrl($page + 1)) : '#' ?>">Next</a>
            </li>
        </ul>
    </nav>
<?php endif; ?>

<?php require '../footer.php'; ?>
