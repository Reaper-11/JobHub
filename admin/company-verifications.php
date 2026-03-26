<?php
require '../db.php';
require_once '../includes/company_verification_helper.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit;
}

$status = strtolower($_GET['status'] ?? 'pending');
if (!in_array($status, ['pending', 'approved', 'rejected', 'all'], true)) {
    $status = 'pending';
}

$where = "verification_status IS NOT NULL";
if ($status !== 'all') {
    $where .= " AND verification_status = ?";
    $requests = db_query_all("
        SELECT id, name, email, verification_company_name, verification_registration_number,
               verification_status, verification_submitted_at, verification_verified_at
        FROM companies
        WHERE $where
        ORDER BY verification_submitted_at DESC, created_at DESC
    ", "s", [$status]);
} else {
    $requests = db_query_all("
        SELECT id, name, email, verification_company_name, verification_registration_number,
               verification_status, verification_submitted_at, verification_verified_at
        FROM companies
        WHERE $where
        ORDER BY verification_submitted_at DESC, created_at DESC
    ");
}

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
    <li class="nav-item"><a class="nav-link <?= $status === 'pending' ? 'active' : '' ?>" href="?status=pending">Pending</a></li>
    <li class="nav-item"><a class="nav-link <?= $status === 'approved' ? 'active' : '' ?>" href="?status=approved">Approved</a></li>
    <li class="nav-item"><a class="nav-link <?= $status === 'rejected' ? 'active' : '' ?>" href="?status=rejected">Rejected</a></li>
    <li class="nav-item"><a class="nav-link <?= $status === 'all' ? 'active' : '' ?>" href="?status=all">All</a></li>
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

<?php require '../footer.php'; ?>
