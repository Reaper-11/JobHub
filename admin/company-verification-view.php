<?php
require '../db.php';
require_once '../includes/company_verification_helper.php';

require_role('admin');

$companyId = (int)($_GET['id'] ?? $_POST['company_id'] ?? 0);
if ($companyId <= 0) {
    header("Location: company-verifications.php");
    exit;
}

$record = get_company_verification_record($conn, $companyId);
if (!$record) {
    header("Location: company-verifications.php");
    exit;
}

$status = get_company_verification_status($record);
$msg = '';
$msg_type = 'danger';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !validate_csrf_token($_POST['csrf_token'] ?? '')) {
    $msg = "Invalid request. Please try again.";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $remarks = trim($_POST['remarks'] ?? '');
    $adminId = current_admin_id() ?? 0;

    if ($status === 'not_submitted') {
        $msg = "This company has not submitted a verification request.";
    } elseif (!in_array($action, ['approve', 'reject'], true)) {
        $msg = "Invalid action.";
    } elseif ($action === 'reject' && $remarks === '') {
        $msg = "Remarks are required when rejecting a request.";
    } else {
        if ($action === 'approve') {
            $stmt = $conn->prepare("
                UPDATE companies
                SET verification_status = 'approved',
                    verification_admin_remarks = ?,
                    verification_verified_at = NOW(),
                    verification_verified_by_admin_id = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            if ($stmt) {
                $stmt->bind_param("sii", $remarks, $adminId, $companyId);
            }
        } else {
            $stmt = $conn->prepare("
                UPDATE companies
                SET verification_status = 'rejected',
                    verification_admin_remarks = ?,
                    verification_verified_at = NULL,
                    verification_verified_by_admin_id = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            if ($stmt) {
                $stmt->bind_param("sii", $remarks, $adminId, $companyId);
            }
        }

        if (!isset($stmt) || !$stmt) {
            $msg = "Could not prepare verification review.";
        } elseif ($stmt->execute()) {
            $msg = $action === 'approve' ? "Verification approved successfully." : "Verification rejected successfully.";
            $msg_type = 'success';

            log_activity(
                $conn,
                $adminId,
                'admin',
                $action === 'approve' ? 'company_verification_approved' : 'company_verification_rejected',
                $action === 'approve'
                    ? "Admin approved company verification for {$record['name']}"
                    : "Admin rejected company verification for {$record['name']}",
                'company',
                $companyId
            );

            $title = $action === 'approve' ? 'Company Verification Approved' : 'Company Verification Rejected';
            $message = $action === 'approve'
                ? 'Your company verification request has been approved. You can now post new jobs.'
                : 'Your company verification request was rejected. Remarks: ' . $remarks;
            notify_create('company', $companyId, $title, $message, 'company-verification.php');

            $record = get_company_verification_record($conn, $companyId);
            $status = get_company_verification_status($record);
        } else {
            $msg = "Could not save the verification decision.";
        }

        if (isset($stmt) && $stmt) {
            $stmt->close();
        }
    }
}
?>

<?php require 'admin-header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0">Review Company Verification</h1>
    <a href="company-verifications.php" class="btn btn-outline-secondary">Back</a>
</div>

<?php if ($msg !== ''): ?>
    <div class="alert alert-<?= htmlspecialchars($msg_type) ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">Company Details</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-muted small">Company Account Name</div>
                        <div><?= htmlspecialchars($record['name']) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Email</div>
                        <div><?= htmlspecialchars($record['email']) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Legal Name</div>
                        <div><?= htmlspecialchars($record['verification_company_name'] ?? '-') ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Registration Number</div>
                        <div><?= htmlspecialchars($record['verification_registration_number'] ?? '-') ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Contact Phone</div>
                        <div><?= htmlspecialchars($record['verification_phone'] ?? '-') ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Submitted At</div>
                        <div><?= htmlspecialchars($record['verification_submitted_at'] ?? '-') ?></div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted small">Address</div>
                        <div><?= nl2br(htmlspecialchars($record['verification_address'] ?? '-')) ?></div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted small">Current Status</div>
                        <div><span class="badge <?= company_verification_badge_class($status) ?>"><?= company_verification_label($status) ?></span></div>
                    </div>
                    <?php if (!empty($record['verification_admin_remarks'])): ?>
                        <div class="col-12">
                            <div class="text-muted small">Existing Remarks</div>
                            <div><?= nl2br(htmlspecialchars($record['verification_admin_remarks'])) ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">Uploaded Proof</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($record['verification_document_path'])): ?>
                    <p class="mb-2"><?= htmlspecialchars(basename($record['verification_document_path'])) ?></p>
                    <a href="../<?= htmlspecialchars($record['verification_document_path']) ?>" target="_blank" class="btn btn-outline-primary btn-sm">Open Document</a>
                <?php else: ?>
                    <p class="mb-0 text-muted">No document uploaded.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">Review Action</h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="company_id" value="<?= (int)$companyId ?>">

                    <div class="mb-3">
                        <label class="form-label">Admin Remarks</label>
                        <textarea name="remarks" class="form-control" rows="4"><?= htmlspecialchars($record['verification_admin_remarks'] ?? '') ?></textarea>
                        <div class="form-text">Remarks are required when rejecting. Optional when approving.</div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" name="action" value="approve" class="btn btn-success">Approve</button>
                        <button type="submit" name="action" value="reject" class="btn btn-danger">Reject</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require '../footer.php'; ?>
