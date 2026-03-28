<?php
require '../db.php';
require_once '../includes/company_verification_helper.php';

if (!isset($_SESSION['company_id'])) {
    header("Location: company-login.php");
    exit;
}

$cid = (int)$_SESSION['company_id'];
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
    <div class="alert alert-success">
        Your company verification is approved. You can now post new jobs.
        <?php if (!empty($record['verification_document_path'])): ?>
            <br>Submitted document:
            <a href="../<?= htmlspecialchars($record['verification_document_path']) ?>" target="_blank">
                <?= htmlspecialchars(basename($record['verification_document_path'])) ?>
            </a>
        <?php endif; ?>
    </div>
<?php elseif ($status === 'pending'): ?>
    <div class="alert alert-warning">
        Your verification request is pending admin review. You cannot submit another request until this one is reviewed.
    </div>
<?php elseif ($status === 'rejected'): ?>
    <div class="alert alert-danger">
        Your verification request was rejected.
        <?php if (!empty($record['verification_admin_remarks'])): ?>
            <br><strong>Admin remarks:</strong> <?= htmlspecialchars($record['verification_admin_remarks']) ?>
        <?php endif; ?>
        <br>You can submit a new verification request below.
    </div>
<?php else: ?>
    <div class="alert alert-secondary">
        Submit your company verification details before posting new jobs.
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header bg-light">
        <h5 class="mb-0">Submit Verification Details</h5>
    </div>
    <div class="card-body">
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

            <div class="mb-3">
                <label class="form-label">Company Legal Name *</label>
                <input type="text" name="company_name" class="form-control" required
                       value="<?= htmlspecialchars($form['company_name']) ?>"
                       <?= $status === 'approved' || $status === 'pending' ? 'readonly' : '' ?>>
            </div>

            <div class="mb-3">
                <label class="form-label">Registration / License Number *</label>
                <input type="text" name="registration_number" class="form-control" required
                       value="<?= htmlspecialchars($form['registration_number']) ?>"
                       <?= $status === 'approved' || $status === 'pending' ? 'readonly' : '' ?>>
            </div>

            <div class="mb-3">
                <label class="form-label">Company Address *</label>
                <textarea name="address" class="form-control" rows="3" required <?= $status === 'approved' || $status === 'pending' ? 'readonly' : '' ?>><?= htmlspecialchars($form['address']) ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Contact Phone *</label>
                <input type="text" name="phone" class="form-control" required
                       value="<?= htmlspecialchars($form['phone']) ?>"
                       <?= $status === 'approved' || $status === 'pending' ? 'readonly' : '' ?>>
            </div>

            <div class="mb-4">
                <label class="form-label">Proof Document *</label>
                <?php if (!empty($record['verification_document_path'])): ?>
                    <div class="small mb-2">
                        Current file:
                        <a href="../<?= htmlspecialchars($record['verification_document_path']) ?>" target="_blank">
                            <?= htmlspecialchars(basename($record['verification_document_path'])) ?>
                        </a>
                    </div>
                <?php endif; ?>
                <input type="file" name="verification_document" class="form-control"
                       accept=".pdf,.jpg,.jpeg,.png"
                       <?= $status === 'approved' || $status === 'pending' ? 'disabled' : 'required' ?>>
                <div class="form-text">Allowed: PDF, JPG, JPEG, PNG. Maximum 5MB.</div>
            </div>

            <button type="submit" class="btn btn-primary" <?= $status === 'approved' || $status === 'pending' ? 'disabled' : '' ?>>
                Submit Verification
            </button>
        </form>
    </div>
</div>

<?php require '../footer.php'; ?>
