<?php
// admin/admin-companies.php
require '../db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit;
}

$status = strtolower($_GET['status'] ?? 'pending');
if (!in_array($status, ['pending','approved','rejected','all'])) $status = 'pending';

$state = strtolower($_GET['state'] ?? 'all');
if (!in_array($state, ['all','active','on_hold','suspended'])) {
    $state = 'all';
}
if ($status !== 'approved') {
    $state = 'all';
}

$where = $status === 'all' ? "1=1" : "is_approved = " . ($status === 'pending' ? 0 : ($status === 'approved' ? 1 : -1));
if ($status === 'approved' && $state !== 'all') {
    $where .= " AND operational_state = '" . $conn->real_escape_string($state) . "'";
}

$companies = db_query_all("SELECT * FROM companies WHERE $where ORDER BY created_at DESC");

$msg = $msg_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validate_csrf_token($_POST['csrf_token'] ?? '')) {
    $id = (int)($_POST['company_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($id > 0 && in_array($action, ['approve','unapprove','reject','hold','suspend','activate'])) {
        $companyRow = null;
        $checkStmt = $conn->prepare("SELECT is_approved, operational_state FROM companies WHERE id = ? LIMIT 1");
        if ($checkStmt) {
            $checkStmt->bind_param("i", $id);
            $checkStmt->execute();
            $companyRow = $checkStmt->get_result()->fetch_assoc();
            $checkStmt->close();
        }

        if (!$companyRow) {
            $msg = "Company not found.";
            $msg_type = 'danger';
        } else {
            $isApproved = (int)$companyRow['is_approved'] === 1;
            $adminId = (int)($_SESSION['admin_id'] ?? 0);

            if ($action === 'reject') {
                $reason = trim($_POST['reason'] ?? '');
                if ($reason === '') {
                    $msg = "Rejection reason is required.";
                    $msg_type = 'danger';
                } else {
                    $stmt = $conn->prepare("UPDATE companies SET is_approved = -1, rejection_reason = ? WHERE id = ?");
                    if ($stmt) {
                        $stmt->bind_param("si", $reason, $id);
                    }
                }
            } elseif ($action === 'approve') {
                $stmt = $conn->prepare("UPDATE companies SET is_approved = 1, rejection_reason = NULL, operational_state = 'active', restriction_reason = NULL, restricted_at = NULL, restricted_by_admin_id = NULL WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $id);
                }
            } elseif ($action === 'unapprove') {
                $stmt = $conn->prepare("UPDATE companies SET is_approved = 0, rejection_reason = NULL WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $id);
                }
            } elseif (in_array($action, ['hold','suspend','activate'], true)) {
                if (!$isApproved) {
                    $msg = "Only approved companies can be restricted.";
                    $msg_type = 'danger';
                } elseif ($action === 'activate') {
                    $stmt = $conn->prepare("UPDATE companies SET operational_state = 'active', restriction_reason = NULL, restricted_at = NULL, restricted_by_admin_id = NULL WHERE id = ?");
                    if ($stmt) {
                        $stmt->bind_param("i", $id);
                    }
                } else {
                    $reason = trim($_POST['reason'] ?? '');
                    if ($reason === '') {
                        $msg = "Restriction reason is required.";
                        $msg_type = 'danger';
                    } else {
                        $newState = $action === 'hold' ? 'on_hold' : 'suspended';
                        $stmt = $conn->prepare("UPDATE companies SET operational_state = ?, restriction_reason = ?, restricted_at = NOW(), restricted_by_admin_id = ? WHERE id = ?");
                        if ($stmt) {
                            $stmt->bind_param("ssii", $newState, $reason, $adminId, $id);
                        }
                    }
                }
            }

            if (!isset($msg) || $msg === '') {
                if (isset($stmt) && $stmt && $stmt->execute()) {
                    $msg = "Company status updated successfully.";
                    $msg_type = 'success';
                    $query = "status=$status";
                    if ($status === 'approved' && $state !== 'all') {
                        $query .= "&state=" . urlencode($state);
                    }
                    header("Location: admin-companies.php?$query");
                    exit;
                } elseif (!isset($msg) || $msg === '') {
                    $msg = "Operation failed.";
                    $msg_type = 'danger';
                }
            }
        }
    }
}
?>

<?php require 'admin-header.php'; ?>

<h1 class="mb-4">Manage Companies</h1>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<ul class="nav nav-tabs mb-4">
    <li class="nav-item"><a class="nav-link <?= $status==='pending'?'active':'' ?>" href="?status=pending">Pending</a></li>
    <li class="nav-item"><a class="nav-link <?= $status==='approved'?'active':'' ?>" href="?status=approved">Approved</a></li>
    <li class="nav-item"><a class="nav-link <?= $status==='rejected'?'active':'' ?>" href="?status=rejected">Rejected</a></li>
    <li class="nav-item"><a class="nav-link <?= $status==='all'?'active':'' ?>" href="?status=all">All</a></li>
</ul>

<?php if ($status === 'approved'): ?>
    <ul class="nav nav-pills mb-3">
        <li class="nav-item"><a class="nav-link <?= $state==='all'?'active':'' ?>" href="?status=approved&state=all">All Approved</a></li>
        <li class="nav-item"><a class="nav-link <?= $state==='active'?'active':'' ?>" href="?status=approved&state=active">Active</a></li>
        <li class="nav-item"><a class="nav-link <?= $state==='on_hold'?'active':'' ?>" href="?status=approved&state=on_hold">On Hold</a></li>
        <li class="nav-item"><a class="nav-link <?= $state==='suspended'?'active':'' ?>" href="?status=approved&state=suspended">Suspended</a></li>
    </ul>
<?php endif; ?>

<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead class="table-light">
            <tr>
                <th>ID</th>
                <th>Company Name</th>
                <th>Email</th>
                <th>Registered</th>
                <th>Status</th>
                <th>Account State</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($companies as $c): ?>
            <tr>
                <td><?= $c['id'] ?></td>
                <td><?= htmlspecialchars($c['name']) ?></td>
                <td><?= htmlspecialchars($c['email']) ?></td>
                <td><?= date('Y-m-d', strtotime($c['created_at'])) ?></td>
                <td>
                    <?php
                    $statusText = match($c['is_approved']) {
                        1 => '<span class="badge bg-success">Approved</span>',
                        -1 => '<span class="badge bg-danger">Rejected</span>',
                        default => '<span class="badge bg-warning">Pending</span>'
                    };
                    echo $statusText;
                    ?>
                </td>
                <td>
                    <?php
                    $opState = $c['operational_state'] ?? 'active';
                    $isApprovedRow = (int)$c['is_approved'] === 1;
                    if ($isApprovedRow) {
                        $stateBadge = match($opState) {
                            'on_hold' => '<span class="badge bg-warning text-dark" data-bs-toggle="tooltip" data-bs-title="Approval remains valid, but job posting is temporarily disabled.">On Hold</span>',
                            'suspended' => '<span class="badge bg-danger" data-bs-toggle="tooltip" data-bs-title="Company is restricted due to violations or misuse.">Suspended</span>',
                            default => '<span class="badge bg-success" data-bs-toggle="tooltip" data-bs-title="Company is approved and allowed to post jobs.">Active</span>',
                        };
                    } else {
                        $stateBadge = match($opState) {
                            'on_hold' => '<span class="badge bg-warning text-dark" data-bs-toggle="tooltip" data-bs-title="Company is not approved, so job posting is not allowed.">On Hold</span>',
                            'suspended' => '<span class="badge bg-danger" data-bs-toggle="tooltip" data-bs-title="Company is not approved, so job posting is not allowed.">Suspended</span>',
                            default => '<span class="badge bg-success" data-bs-toggle="tooltip" data-bs-title="Company is not approved, so job posting is not allowed.">Active</span>',
                        };
                    }
                    echo $stateBadge;
                    ?>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <?php if ($c['is_approved'] != 1): ?>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                <input type="hidden" name="company_id" value="<?= $c['id'] ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn btn-outline-success btn-sm">Approve</button>
                            </form>
                        <?php endif; ?>

                        <?php if ($c['is_approved'] != 0): ?>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                <input type="hidden" name="company_id" value="<?= $c['id'] ?>">
                                <input type="hidden" name="action" value="unapprove">
                                <button type="submit" class="btn btn-outline-warning btn-sm">Unapprove</button>
                            </form>
                        <?php endif; ?>

                        <?php if ($c['is_approved'] != -1): ?>
                            <button type="button" class="btn btn-outline-danger btn-sm" 
                                    data-bs-toggle="modal" data-bs-target="#rejectModal<?= $c['id'] ?>">
                                Reject
                            </button>
                        <?php endif; ?>

                        <?php if ((int)$c['is_approved'] === 1): ?>
                            <?php $opState = $c['operational_state'] ?? 'active'; ?>
                            <?php if ($opState === 'active'): ?>
                                <button type="button" class="btn btn-outline-warning btn-sm"
                                        data-bs-toggle="modal" data-bs-target="#holdModal<?= $c['id'] ?>">Hold</button>
                                <button type="button" class="btn btn-outline-danger btn-sm"
                                        data-bs-toggle="modal" data-bs-target="#suspendModal<?= $c['id'] ?>">Suspend</button>
                            <?php elseif ($opState === 'on_hold'): ?>
                                <button type="button" class="btn btn-outline-success btn-sm"
                                        data-bs-toggle="modal" data-bs-target="#activateModal<?= $c['id'] ?>">Activate</button>
                                <button type="button" class="btn btn-outline-danger btn-sm"
                                        data-bs-toggle="modal" data-bs-target="#suspendModal<?= $c['id'] ?>">Suspend</button>
                            <?php elseif ($opState === 'suspended'): ?>
                                <button type="button" class="btn btn-outline-success btn-sm"
                                        data-bs-toggle="modal" data-bs-target="#activateModal<?= $c['id'] ?>">Activate</button>
                                <button type="button" class="btn btn-outline-warning btn-sm"
                                        data-bs-toggle="modal" data-bs-target="#holdModal<?= $c['id'] ?>">Hold</button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Reject Modal -->
                    <div class="modal fade" id="rejectModal<?= $c['id'] ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <form method="post" class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Reject Company</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                    <input type="hidden" name="company_id" value="<?= $c['id'] ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <div class="mb-3">
                                        <label class="form-label">Rejection reason <span class="text-danger">*</span></label>
                                        <textarea name="reason" class="form-control" rows="3" required></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-danger">Confirm Reject</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Hold Modal -->
                    <div class="modal fade" id="holdModal<?= $c['id'] ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <form method="post" class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Put Company On Hold</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                    <input type="hidden" name="company_id" value="<?= $c['id'] ?>">
                                    <input type="hidden" name="action" value="hold">
                                    <div class="mb-3">
                                        <label class="form-label">Reason <span class="text-danger">*</span></label>
                                        <textarea name="reason" class="form-control" rows="3" required></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-warning">Confirm Hold</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Suspend Modal -->
                    <div class="modal fade" id="suspendModal<?= $c['id'] ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <form method="post" class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Suspend Company</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                    <input type="hidden" name="company_id" value="<?= $c['id'] ?>">
                                    <input type="hidden" name="action" value="suspend">
                                    <div class="mb-3">
                                        <label class="form-label">Reason <span class="text-danger">*</span></label>
                                        <textarea name="reason" class="form-control" rows="3" required></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-danger">Confirm Suspend</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Activate Modal -->
                    <div class="modal fade" id="activateModal<?= $c['id'] ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <form method="post" class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Activate Company</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                    <input type="hidden" name="company_id" value="<?= $c['id'] ?>">
                                    <input type="hidden" name="action" value="activate">
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-success">Confirm Activate</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($companies)): ?>
            <tr><td colspan="7" class="text-center py-4">No companies found in this status.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require '../footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function (el) {
        new bootstrap.Tooltip(el);
    });
});
</script>
