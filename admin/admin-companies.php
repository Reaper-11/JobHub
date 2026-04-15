<?php
// admin/admin-companies.php
require '../db.php';

require_role('admin');

$companyAdminBaseUrl = 'companies.php';
$status = strtolower(trim((string)($_GET['status'] ?? 'all')));
if (!in_array($status, ['all', 'pending', 'approved', 'rejected'], true)) {
    $status = 'all';
}

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = $status === 'all' ? 30 : 20;
$offset = ($page - 1) * $limit;

$state = strtolower($_GET['state'] ?? 'all');
if (!in_array($state, ['all','active','on_hold','suspended'])) {
    $state = 'all';
}
if ($status !== 'approved') {
    $state = 'all';
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && basename($_SERVER['PHP_SELF']) === 'admin-companies.php') {
    $redirectQuery = 'status=' . urlencode($status) . '&page=' . $page;
    if ($status === 'approved' && $state !== 'all') {
        $redirectQuery .= '&state=' . urlencode($state);
    }

    header('Location: ' . $companyAdminBaseUrl . '?' . $redirectQuery);
    exit;
}

$whereClauses = [];
$whereTypes = '';
$whereParams = [];

if ($status === 'pending') {
    $whereClauses[] = 'c.is_approved = 0';
} elseif ($status === 'approved') {
    $whereClauses[] = 'c.is_approved = 1';
} elseif ($status === 'rejected') {
    $whereClauses[] = 'c.is_approved = -1';
}

if ($status === 'approved' && $state !== 'all') {
    $whereClauses[] = 'c.operational_state = ?';
    $whereTypes .= 's';
    $whereParams[] = $state;
}

$where = empty($whereClauses) ? '1=1' : implode(' AND ', $whereClauses);
$totalCompanies = (int)db_query_value(
    "SELECT COUNT(*) FROM companies c WHERE {$where}",
    $whereTypes,
    $whereParams,
    0
);
$totalPages = $totalCompanies > 0 ? (int)ceil($totalCompanies / $limit) : 1;

if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $limit;
}

$companyQueryTypes = $whereTypes . 'ii';
$companyQueryParams = array_merge($whereParams, [$limit, $offset]);

$companies = db_query_all("
    SELECT c.*
    FROM companies c
    WHERE {$where}
    ORDER BY c.created_at DESC
    LIMIT ? OFFSET ?
", $companyQueryTypes, $companyQueryParams);

$tabUrl = static function (string $targetStatus) use ($companyAdminBaseUrl): string {
    return $companyAdminBaseUrl . '?status=' . urlencode($targetStatus) . '&page=1';
};

$stateUrl = static function (string $targetState) use ($companyAdminBaseUrl): string {
    return $companyAdminBaseUrl . '?status=approved&page=1&state=' . urlencode($targetState);
};

$pageUrl = static function (int $targetPage) use ($companyAdminBaseUrl, $status, $state): string {
    $query = 'status=' . urlencode($status) . '&page=' . max(1, $targetPage);
    if ($status === 'approved' && $state !== 'all') {
        $query .= '&state=' . urlencode($state);
    }

    return $companyAdminBaseUrl . '?' . $query;
};

$paginationStart = max(1, $page - 2);
$paginationEnd = min($totalPages, $page + 2);

$msg = $msg_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validate_csrf_token($_POST['csrf_token'] ?? '')) {
    $id = (int)($_POST['company_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($id > 0 && in_array($action, ['approve','unapprove','reject','hold','suspend','activate'])) {
        $companyRow = null;
        $companyInfo = db_query_all("SELECT id, account_id, name, email FROM companies WHERE id = ? LIMIT 1", "i", [$id])[0] ?? null;
        $checkStmt = $conn->prepare("SELECT is_approved, is_active, operational_state, verification_status FROM companies WHERE id = ? LIMIT 1");
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
            $adminId = current_admin_id() ?? 0;
            $reason = '';
            $accountStatus = 'active';
            $stmt = null;
            $mailAction = '';

            if ($action === 'reject') {
                $reason = trim($_POST['reason'] ?? '');
                if ($reason === '') {
                    $msg = "Rejection reason is required.";
                    $msg_type = 'danger';
                } else {
                    $stmt = $conn->prepare("
                        UPDATE companies
                        SET is_approved = -1,
                            is_active = 0,
                            rejection_reason = ?,
                            operational_state = 'active',
                            restriction_reason = NULL,
                            restricted_at = NULL,
                            restricted_by_admin_id = NULL,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $accountStatus = 'inactive';
                    $mailAction = 'rejected';
                    if ($stmt) {
                        $stmt->bind_param("si", $reason, $id);
                    }
                }
            } elseif ($action === 'approve') {
                $stmt = $conn->prepare("
                    UPDATE companies
                    SET is_approved = 1,
                        is_active = 1,
                        rejection_reason = NULL,
                        operational_state = 'active',
                        restriction_reason = NULL,
                        restricted_at = NULL,
                        restricted_by_admin_id = NULL,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $accountStatus = 'active';
                $mailAction = 'approved';
                if ($stmt) {
                    $stmt->bind_param("i", $id);
                }
            } elseif ($action === 'unapprove') {
                $stmt = $conn->prepare("
                    UPDATE companies
                    SET is_approved = 0,
                        is_active = 1,
                        rejection_reason = NULL,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $accountStatus = 'active';
                $mailAction = 'unapproved';
                if ($stmt) {
                    $stmt->bind_param("i", $id);
                }
            } elseif (in_array($action, ['hold','suspend','activate'], true)) {
                if (!$isApproved) {
                    $msg = "Only approved companies can be restricted.";
                    $msg_type = 'danger';
                } elseif ($action === 'activate') {
                    $stmt = $conn->prepare("UPDATE companies SET operational_state = 'active', restriction_reason = NULL, restricted_at = NULL, restricted_by_admin_id = NULL WHERE id = ?");
                    $accountStatus = 'active';
                    $mailAction = 'activated';
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
                        $accountStatus = 'active';
                        $mailAction = $action === 'hold' ? 'hold' : 'suspended';
                        if ($stmt) {
                            $stmt->bind_param("ssii", $newState, $reason, $adminId, $id);
                        }
                    }
                }
            }

            if (!isset($msg) || $msg === '') {
                $conn->begin_transaction();

                $updated = $stmt && $stmt->execute();
                if ($stmt) {
                    $stmt->close();
                }

                if ($updated) {
                    $ok = true;
                    if (!empty($companyInfo['account_id'])) {
                        $ok = jobhub_update_account_status($conn, (int) $companyInfo['account_id'], $accountStatus);
                    }

                    if (!$ok) {
                        $conn->rollback();
                        $msg = "Operation failed.";
                        $msg_type = 'danger';
                    } else {
                        $conn->commit();
                        $msg = "Company status updated successfully.";
                        $msg_type = 'success';

                        if ($companyInfo) {
                            $companyName = $companyInfo['name'] ?? 'your company';
                            $updatedCompany = db_query_all("
                                SELECT is_approved, is_active, rejection_reason, operational_state, restriction_reason,
                                       verification_status, verification_admin_remarks
                                FROM companies
                                WHERE id = ?
                                LIMIT 1
                            ", "i", [$id])[0] ?? null;
                            $finalCompanyStatus = jobhub_company_final_status($updatedCompany);
                            $canPostJobsNow = jobhub_company_can_post_jobs($updatedCompany);
                            $title = 'Company Status Update';
                            $message = "Your company status was updated.";
                            if ($action === 'approve') {
                                $title = 'Company Approved';
                                $message = $canPostJobsNow
                                    ? "{$companyName} has been approved and is now Active."
                                    : "{$companyName} has been approved, but the account is " . company_final_status_label($finalCompanyStatus) . ". Job posting stays disabled until verification is completed.";
                            } elseif ($action === 'reject') {
                                $title = 'Company Rejected';
                                $message = "{$companyName} was rejected. Reason: {$reason}";
                            } elseif ($action === 'unapprove') {
                                $title = 'Approval Removed';
                                $message = "{$companyName} approval has been removed. The account is now Pending and job posting is disabled until re-approved and verified.";
                            } elseif ($action === 'hold') {
                                $title = 'Account On Hold';
                                $message = "{$companyName} has been put on hold. Reason: {$reason}";
                            } elseif ($action === 'suspend') {
                                $title = 'Account Suspended';
                                $message = "{$companyName} has been suspended. Reason: {$reason}";
                            } elseif ($action === 'activate') {
                                $title = 'Account Activated';
                                $message = $canPostJobsNow
                                    ? "{$companyName} has been reactivated and is now Active."
                                    : "{$companyName} has been reactivated, but the account remains " . company_final_status_label($finalCompanyStatus) . " until verification is completed.";
                            }

                            notify_create('company', $id, $title, $message, 'company-dashboard.php');

                            $companyEmail = trim((string) ($companyInfo['email'] ?? ''));
                            if ($companyEmail !== '' && $mailAction !== '') {
                                try {
                                    $mailResult = jobhub_send_company_approval_email(
                                        $companyEmail,
                                        (string) ($companyInfo['name'] ?? ''),
                                        $companyName,
                                        $mailAction,
                                        $reason
                                    );

                                    if (empty($mailResult['success'])) {
                                        $mailMessage = trim((string) ($mailResult['message'] ?? ''));
                                        jobhub_log_mail_error(
                                            'company-approval',
                                            'Company #' . $id . ' review email (' . $mailAction . ') failed for ' . $companyEmail . ': '
                                            . ($mailMessage !== '' ? $mailMessage : 'Unknown mail error.')
                                        );
                                    }
                                } catch (Throwable $mailException) {
                                    jobhub_log_mail_error(
                                        'company-approval',
                                        'Company #' . $id . ' review email (' . $mailAction . ') threw an exception for '
                                        . $companyEmail . ': ' . $mailException->getMessage()
                                    );
                                }
                            }
                        }

                        $query = 'status=' . urlencode($status) . '&page=' . $page;
                        if ($status === 'approved' && $state !== 'all') {
                            $query .= "&state=" . urlencode($state);
                        }
                        header("Location: {$companyAdminBaseUrl}?{$query}");
                        exit;
                    }
                } elseif (!isset($msg) || $msg === '') {
                    $conn->rollback();
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
    <li class="nav-item"><a class="nav-link <?= $status==='all'?'active':'' ?>" href="<?= htmlspecialchars($tabUrl('all')) ?>">All</a></li>
    <li class="nav-item"><a class="nav-link <?= $status==='pending'?'active':'' ?>" href="<?= htmlspecialchars($tabUrl('pending')) ?>">Pending</a></li>
    <li class="nav-item"><a class="nav-link <?= $status==='approved'?'active':'' ?>" href="<?= htmlspecialchars($tabUrl('approved')) ?>">Approved</a></li>
    <li class="nav-item"><a class="nav-link <?= $status==='rejected'?'active':'' ?>" href="<?= htmlspecialchars($tabUrl('rejected')) ?>">Rejected</a></li>
</ul>

<?php if ($status === 'approved'): ?>
    <ul class="nav nav-pills mb-3">
        <li class="nav-item"><a class="nav-link <?= $state==='all'?'active':'' ?>" href="<?= htmlspecialchars($stateUrl('all')) ?>">All Approved</a></li>
        <li class="nav-item"><a class="nav-link <?= $state==='active'?'active':'' ?>" href="<?= htmlspecialchars($stateUrl('active')) ?>">Active</a></li>
        <li class="nav-item"><a class="nav-link <?= $state==='on_hold'?'active':'' ?>" href="<?= htmlspecialchars($stateUrl('on_hold')) ?>">On Hold</a></li>
        <li class="nav-item"><a class="nav-link <?= $state==='suspended'?'active':'' ?>" href="<?= htmlspecialchars($stateUrl('suspended')) ?>">Suspended</a></li>
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
                <th>Verification</th>
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
                    $finalCompanyStatus = jobhub_company_final_status($c);
                    echo '<span class="badge ' . company_final_status_badge_class($finalCompanyStatus) . '">' . company_final_status_label($finalCompanyStatus) . '</span>';
                    ?>
                </td>
                <td>
                    <?php
                    $opState = strtolower(trim((string)($c['operational_state'] ?? 'active')));
                    if ((int)($c['is_active'] ?? 1) !== 1 && $finalCompanyStatus !== 'rejected') {
                        $stateBadge = '<span class="badge bg-secondary">Inactive</span>';
                    } elseif ($finalCompanyStatus !== 'active') {
                        $stateBadge = '<span class="badge ' . company_final_status_badge_class($finalCompanyStatus) . '">' . company_final_status_label($finalCompanyStatus) . '</span>';
                    } else {
                        $stateBadge = match($opState) {
                            'on_hold' => '<span class="badge bg-warning text-dark" data-bs-toggle="tooltip" data-bs-title="Approval remains valid, but job posting is temporarily disabled.">On Hold</span>',
                            'suspended' => '<span class="badge bg-danger" data-bs-toggle="tooltip" data-bs-title="Company is restricted due to violations or misuse.">Suspended</span>',
                            default => '<span class="badge bg-success" data-bs-toggle="tooltip" data-bs-title="Company is approved, verified, and allowed to post jobs.">Active</span>',
                        };
                    }
                    echo $stateBadge;
                    ?>
                </td>
                <td>
                    <?php
                    $verificationStatus = get_company_verification_status($c);
                    $hasVerificationDocument = trim((string)($c['verification_document_path'] ?? '')) !== '';
                    ?>
                    <span class="badge <?= company_verification_badge_class($verificationStatus) ?>">
                        <?= company_verification_label($verificationStatus) ?>
                    </span>
                    <?php if ($hasVerificationDocument): ?>
                        <div class="mt-2">
                            <a href="company-verification-view.php?id=<?= (int)$c['id'] ?>" class="btn btn-outline-primary btn-sm">View Docs</a>
                        </div>
                    <?php endif; ?>
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
                                <button type="submit" class="btn btn-outline-warning btn-sm">Pending</button>
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
            <tr><td colspan="8" class="text-center py-4">No companies found in this status.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
    <nav class="mt-4" aria-label="Company pagination">
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function (el) {
        new bootstrap.Tooltip(el);
    });
});
</script>
