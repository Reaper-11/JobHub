<?php
require '../db.php';
require_once '../includes/company_verification_helper.php';

require_role('company');

$cid = current_company_id() ?? 0;
$record = get_company_verification_record($conn, $cid);
$status = get_company_verification_status($record);
$msg = '';
$msg_type = 'danger';
$errors = [];
$maxFileSize = 5 * 1024 * 1024;

$form = [
    'company_name' => $record['verification_company_name'] ?? ($record['name'] ?? ''),
    'registration_number' => $record['verification_registration_number'] ?? '',
    'address' => $record['verification_address'] ?? ($record['location'] ?? ''),
    'phone' => $record['verification_phone'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !validate_csrf_token($_POST['csrf_token'] ?? '')) {
    $msg = "Invalid request. Please try again.";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['company_name'] = trim($_POST['company_name'] ?? '');
    $form['registration_number'] = trim($_POST['registration_number'] ?? '');
    $form['address'] = trim($_POST['address'] ?? '');
    $form['phone'] = trim($_POST['phone'] ?? '');

    if ($status === 'approved') {
        $msg = "Your company is already verified.";
        $msg_type = 'success';
    } elseif ($status === 'pending') {
        $msg = "Your verification request is already pending admin review.";
        $msg_type = 'warning';
    } else {
        if ($form['company_name'] === '') {
            $errors[] = "Company name is required.";
        }
        if ($form['registration_number'] === '') {
            $errors[] = "Registration or license number is required.";
        }
        if ($form['address'] === '') {
            $errors[] = "Address is required.";
        }
        if ($form['phone'] === '') {
            $errors[] = "Contact phone is required.";
        }

        if (!isset($_FILES['verification_document']) || $_FILES['verification_document']['error'] === UPLOAD_ERR_NO_FILE) {
            $errors[] = "Verification document is required.";
        } elseif ((int)$_FILES['verification_document']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Could not upload the verification document.";
        } else {
            $file = $_FILES['verification_document'];
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
            $allowedMimeTypes = [
                'pdf' => ['application/pdf'],
                'jpg' => ['image/jpeg'],
                'jpeg' => ['image/jpeg'],
                'png' => ['image/png'],
            ];

            if (!in_array($extension, $allowedExtensions, true)) {
                $errors[] = "Document must be a PDF, JPG, JPEG, or PNG file.";
            } elseif ((int)$file['size'] > $maxFileSize) {
                $errors[] = "Document must be 5MB or smaller.";
            } else {
                $mimeType = function_exists('mime_content_type') ? mime_content_type($file['tmp_name']) : '';
                if ($mimeType !== '' && !in_array($mimeType, $allowedMimeTypes[$extension], true)) {
                    $errors[] = "Uploaded file type does not match the selected document format.";
                }
            }
        }

        if (empty($errors)) {
            $uploadDir = __DIR__ . '/../uploads/company_verification';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
                $errors[] = "Upload folder is not available.";
            } else {
                $safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '', pathinfo($_FILES['verification_document']['name'], PATHINFO_FILENAME));
                $safeBase = $safeBase !== '' ? substr($safeBase, 0, 40) : 'document';
                $newFileName = 'verification_' . $cid . '_' . time() . '_' . $safeBase . '.' . $extension;
                $destination = $uploadDir . '/' . $newFileName;

                if (!move_uploaded_file($_FILES['verification_document']['tmp_name'], $destination)) {
                    $errors[] = "Could not save the uploaded document.";
                } else {
                    $documentPath = 'uploads/company_verification/' . $newFileName;
                    $stmt = $conn->prepare("
                        UPDATE companies
                        SET verification_company_name = ?,
                            verification_registration_number = ?,
                            verification_phone = ?,
                            verification_address = ?,
                            verification_document_path = ?,
                            verification_status = 'pending',
                            verification_admin_remarks = NULL,
                            verification_submitted_at = NOW(),
                            verification_verified_at = NULL,
                            verification_verified_by_admin_id = NULL,
                            updated_at = NOW()
                        WHERE id = ?
                    ");

                    if ($stmt) {
                        $stmt->bind_param(
                            "sssssi",
                            $form['company_name'],
                            $form['registration_number'],
                            $form['phone'],
                            $form['address'],
                            $documentPath,
                            $cid
                        );

                        if ($stmt->execute()) {
                            $msg = "Verification request submitted successfully.";
                            $msg_type = 'success';
                            log_activity(
                                $conn,
                                $cid,
                                'company',
                                'company_verification_submitted',
                                "Company submitted verification request: {$form['company_name']}",
                                'company',
                                $cid
                            );
                            $record = get_company_verification_record($conn, $cid);
                            $status = get_company_verification_status($record);
                        } else {
                            $errors[] = "Could not save verification details.";
                        }
                        $stmt->close();
                    } else {
                        $errors[] = "Could not prepare the verification request.";
                    }
                }
            }
        }

        if (!empty($errors)) {
            $msg = implode(' ', $errors);
        }
    }
}
?>

<?php require 'company-header.php'; ?>

<h1 class="mb-4">Company Verification</h1>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <div class="d-flex flex-wrap gap-4 align-items-center">
            <div>
                <div class="text-muted small">Verification Status</div>
                <div><span class="badge <?= company_verification_badge_class($status) ?>"><?= company_verification_label($status) ?></span></div>
            </div>
            <?php if (!empty($record['verification_submitted_at'])): ?>
                <div>
                    <div class="text-muted small">Submitted At</div>
                    <div><?= htmlspecialchars($record['verification_submitted_at']) ?></div>
                </div>
            <?php endif; ?>
            <?php if (!empty($record['verification_verified_at'])): ?>
                <div>
                    <div class="text-muted small">Reviewed At</div>
                    <div><?= htmlspecialchars($record['verification_verified_at']) ?></div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($msg !== ''): ?>
    <div class="alert alert-<?= htmlspecialchars($msg_type) ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php if ($status === 'approved'): ?>
    <div class="alert alert-success mb-4">
        <i class="fas fa-check-circle me-2"></i>
        <strong>Verified!</strong> Your company verification is approved. You can now post new jobs.
        <?php if (!empty($record['verification_document_path'])): ?>
            <br><br>
            <strong>Submitted Document:</strong>
            <div class="mt-2">
                <a href="../<?= htmlspecialchars($record['verification_document_path']) ?>" class="btn btn-sm btn-outline-success" target="_blank" rel="noopener">
                    <i class="fas fa-download me-1"></i><?= htmlspecialchars(basename($record['verification_document_path'])) ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
<?php elseif ($status === 'pending'): ?>
    <div class="alert alert-warning mb-4">
        <i class="fas fa-hourglass-half me-2"></i>
        <strong>Pending Review</strong> Your verification request is currently under review by the admin team. You will be notified once it's processed.
    </div>
<?php elseif ($status === 'rejected'): ?>
    <div class="alert alert-danger mb-4">
        <i class="fas fa-exclamation-circle me-2"></i>
        <strong>Verification Rejected</strong>
        <?php if (!empty($record['verification_admin_remarks'])): ?>
            <br><strong>Admin Feedback:</strong> <?= htmlspecialchars($record['verification_admin_remarks']) ?>
        <?php endif; ?>
        <br><br>
        <strong>What's Next?</strong> Please review the admin feedback above and resubmit your verification with the necessary corrections.
    </div>
<?php else: ?>
    <div class="alert alert-secondary mb-4">
        <i class="fas fa-info-circle me-2"></i>
        Submit your company verification details before posting new jobs. This helps us maintain a trustworthy platform.
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-file-upload me-2"></i>
            <?= $status === 'rejected' ? 'Resubmit Verification Details' : 'Submit Verification Details' ?>
        </h5>
    </div>
    <div class="card-body">
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

            <div class="mb-3">
                <label class="form-label">Company Legal Name <span class="text-danger">*</span></label>
                <input type="text" name="company_name" class="form-control <?= ($status === 'approved' || $status === 'pending') ? 'bg-light' : '' ?>" required
                       value="<?= htmlspecialchars($form['company_name']) ?>"
                       <?= ($status === 'approved' || $status === 'pending') ? 'readonly' : '' ?>
                       placeholder="Enter your company legal name"
                       style="<?= ($status === 'approved' || $status === 'pending') ? 'color: #495057; background-color: #e9ecef;' : '' ?>">
                <?php if ($status === 'approved' || $status === 'pending'): ?>
                    <small class="text-muted d-block mt-1"><i class="fas fa-lock me-1"></i>This field is locked</small>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label class="form-label">Registration / License Number <span class="text-danger">*</span></label>
                <input type="text" name="registration_number" class="form-control <?= ($status === 'approved' || $status === 'pending') ? 'bg-light' : '' ?>" required
                       value="<?= htmlspecialchars($form['registration_number']) ?>"
                       <?= ($status === 'approved' || $status === 'pending') ? 'readonly' : '' ?>
                       placeholder="Enter your registration or license number"
                       style="<?= ($status === 'approved' || $status === 'pending') ? 'color: #495057; background-color: #e9ecef;' : '' ?>">
                <?php if ($status === 'approved' || $status === 'pending'): ?>
                    <small class="text-muted d-block mt-1"><i class="fas fa-lock me-1"></i>This field is locked</small>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label class="form-label">Company Address <span class="text-danger">*</span></label>
                <textarea name="address" class="form-control <?= ($status === 'approved' || $status === 'pending') ? 'bg-light' : '' ?>" rows="3" required 
                          <?= ($status === 'approved' || $status === 'pending') ? 'readonly' : '' ?>
                          placeholder="Enter your complete company address"
                          style="<?= ($status === 'approved' || $status === 'pending') ? 'color: #495057; background-color: #e9ecef;' : '' ?>"><?= htmlspecialchars($form['address']) ?></textarea>
                <?php if ($status === 'approved' || $status === 'pending'): ?>
                    <small class="text-muted d-block mt-1"><i class="fas fa-lock me-1"></i>This field is locked</small>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label class="form-label">Contact Phone <span class="text-danger">*</span></label>
                <input type="text" name="phone" class="form-control <?= ($status === 'approved' || $status === 'pending') ? 'bg-light' : '' ?>" required
                       value="<?= htmlspecialchars($form['phone']) ?>"
                       <?= ($status === 'approved' || $status === 'pending') ? 'readonly' : '' ?>
                       placeholder="Enter your contact phone number"
                       style="<?= ($status === 'approved' || $status === 'pending') ? 'color: #495057; background-color: #e9ecef;' : '' ?>">
                <?php if ($status === 'approved' || $status === 'pending'): ?>
                    <small class="text-muted d-block mt-1"><i class="fas fa-lock me-1"></i>This field is locked</small>
                <?php endif; ?>
            </div>

            <div class="mb-4">
                <label class="form-label">Proof Document <span class="text-danger">*</span></label>
                
                <?php if (!empty($record['verification_document_path'])): ?>
                    <!-- Submitted Document Card -->
                    <div class="card card-sm mb-3 border" style="background-color: #f8f9fa;">
                        <div class="card-body p-3">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <i class="fas fa-file-pdf me-2" style="color: #dc3545; font-size: 1.5rem;"></i>
                                </div>
                                <div class="col">
                                    <div class="small" style="color: #6c757d;">Your Uploaded Document</div>
                                    <div class="text-break" style="color: #212529; font-weight: 500;">
                                        <?= htmlspecialchars(basename($record['verification_document_path'])) ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <a href="../<?= htmlspecialchars($record['verification_document_path']) ?>" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" title="Open in new tab">
                                        <i class="fas fa-eye me-1"></i>View
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Status-Based Messages and Upload Section -->
                <?php if ($status === 'pending'): ?>
                    <div class="alert alert-warning mb-3" style="background-color: #fff3cd; border: 1px solid #ffc107;">
                        <i class="fas fa-hourglass-half me-2"></i>
                        <strong>Under Review</strong> Your document is currently under review. You cannot upload a new file right now.
                    </div>
                <?php elseif ($status === 'approved'): ?>
                    <div class="alert alert-success mb-3" style="background-color: #d4edda; border: 1px solid #28a745;">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Document Approved</strong> Your document has been approved. No further upload is required.
                    </div>
                <?php elseif ($status === 'rejected'): ?>
                    <div class="alert alert-danger mb-3" style="background-color: #f8d7da; border: 1px solid #dc3545;">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <strong>Document Rejected</strong> Your document was rejected. Please upload a new valid document below.
                    </div>
                    
                    <input type="file" name="verification_document" class="form-control" required
                           accept=".pdf,.jpg,.jpeg,.png">
                    <div class="form-text mt-2">
                        <i class="fas fa-info-circle me-1"></i>
                        Allowed formats: PDF, JPG, JPEG, PNG. Maximum file size: 5MB.
                    </div>
                <?php else: ?>
                    <!-- No status or initial submission -->
                    <input type="file" name="verification_document" class="form-control" required
                           accept=".pdf,.jpg,.jpeg,.png">
                    <div class="form-text mt-2">
                        <i class="fas fa-info-circle me-1"></i>
                        Allowed formats: PDF, JPG, JPEG, PNG. Maximum file size: 5MB.
                    </div>
                <?php endif; ?>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary" <?= ($status === 'approved' || $status === 'pending') ? 'disabled' : '' ?>>
                    <i class="fas fa-paper-plane me-2"></i>
                    <?= $status === 'rejected' ? 'Resubmit Verification' : 'Submit Verification' ?>
                </button>
                <?php if ($status === 'approved'): ?>
                    <span class="badge bg-success align-self-center">
                        <i class="fas fa-check me-1"></i>Verified
                    </span>
                <?php elseif ($status === 'pending'): ?>
                    <span class="badge bg-warning text-dark align-self-center">
                        <i class="fas fa-hourglass-half me-1"></i>Pending Review
                    </span>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php require '../footer.php'; ?>
