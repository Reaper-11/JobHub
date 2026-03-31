<?php
require_once '../db.php';
require_once '../includes/company_verification_helper.php';

require_role('company');

$hasSidebarLayout = true;
$cid = current_company_id();
$currentCompanyPage = basename($_SERVER['PHP_SELF']);
$popupFeedback = null;

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['popup_mark_verification_read'])
    && !empty($_POST['popup_notification_id'])
) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $popupFeedback = ['type' => 'danger', 'message' => 'Invalid notification request. Please try again.'];
    } else {
        notify_mark_read('company', $cid ?? 0, (int)$_POST['popup_notification_id']);
        $popupFeedback = ['type' => 'success', 'message' => 'Verification notification marked as read.'];
    }
}

$notificationCount = notify_unread_count('company', $cid ?? 0);
$shouldAutoShowVerificationPopup = in_array($currentCompanyPage, ['company-dashboard.php', 'company-notifications.php'], true);
$verificationPopupNotification = $shouldAutoShowVerificationPopup
    ? notify_latest_unread_verification('company', $cid ?? 0)
    : null;

$stmt = $conn->prepare("
    SELECT name, email, is_approved, rejection_reason, operational_state, restriction_reason, restricted_at,
           verification_company_name, verification_registration_number, verification_phone,
           verification_address, verification_document_path, verification_status,
           verification_admin_remarks, verification_submitted_at, verification_verified_at
    FROM companies
    WHERE id = ?
");
$stmt->bind_param("i", $cid);
$stmt->execute();
$company = $stmt->get_result()->fetch_assoc() ?? [
    'name' => 'Company',
    'email' => '',
    'is_approved' => 0,
    'rejection_reason' => null,
    'operational_state' => 'active',
    'restriction_reason' => null,
    'restricted_at' => null,
    'verification_company_name' => null,
    'verification_registration_number' => null,
    'verification_phone' => null,
    'verification_address' => null,
    'verification_document_path' => null,
    'verification_status' => null,
    'verification_admin_remarks' => null,
    'verification_submitted_at' => null,
    'verification_verified_at' => null,
];
$stmt->close();

$isApproved = (int) $company['is_approved'] === 1;
$isRejected = (int) $company['is_approved'] === -1;
$operationalState = $company['operational_state'] ?? 'active';
$restrictionReason = $company['restriction_reason'] ?? '';
$restrictedAt = $company['restricted_at'] ?? null;
$rejectionReason = $company['rejection_reason'] ?? '';
$verificationStatus = get_company_verification_status($company);
$isVerified = is_company_verified($company);
$canPostJobs = $isApproved && $operationalState === 'active' && $isVerified;
$authFlash = jobhub_take_auth_flash();

$approvalBadge = $isApproved
    ? '<span class="badge bg-success">Approved</span>'
    : ($isRejected ? '<span class="badge bg-danger">Rejected</span>' : '<span class="badge bg-warning text-dark">Pending</span>');

$stateBadge = match ($operationalState) {
    'on_hold' => '<span class="badge bg-warning text-dark">On Hold</span>',
    'suspended' => '<span class="badge bg-danger">Suspended</span>',
    default => '<span class="badge bg-success">Active</span>',
};

$verificationBadge = '<span class="badge ' . company_verification_badge_class($verificationStatus) . '">' .
    company_verification_label($verificationStatus) .
    '</span>';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Dashboard - JobHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../custom.css?v=<?= time() ?>">
    <style>
        .company-sidebar { min-height: 100vh; background: #0d1b2a; color: white; }
        .company-sidebar .nav-link { color: rgba(255,255,255,0.8); }
        .company-sidebar .nav-link:hover, .company-sidebar .nav-link.active { color: white; background: rgba(255,255,255,0.1); }
        .main-content { background: #f8f9fa; }
        .pending-banner { background: #fff3cd; border-left: 4px solid #ffc107; }
        .verification-popup-backdrop {
            position: fixed;
            inset: 0;
            z-index: 1080;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: rgba(15, 23, 42, 0.72);
        }
        .verification-popup-backdrop[hidden] { display: none !important; }
        .verification-popup-dialog {
            position: relative;
            width: min(100%, 620px);
            max-height: calc(100vh - 48px);
            overflow-y: auto;
            padding: 24px;
            border-radius: 18px;
            background: #ffffff;
            box-shadow: 0 24px 80px rgba(15, 23, 42, 0.3);
        }
        .verification-popup-message {
            margin-top: 12px;
            padding: 14px 16px;
            border: 1px solid #e9ecef;
            border-radius: 12px;
            background: #f8f9fa;
            white-space: pre-wrap;
            word-break: break-word;
        }
        @media (max-width: 576px) {
            .verification-popup-backdrop { padding: 12px; }
            .verification-popup-dialog { padding: 20px; border-radius: 14px; }
        }
    </style>
</head>
<body>

<div class="d-flex">
    <div class="company-sidebar col-auto p-3">
        <h4 class="text-white mb-4">Company Dashboard</h4>
        <div class="mb-3 p-2 bg-dark rounded text-center">
            <?= htmlspecialchars($company['name']) ?><br>
            <div class="d-flex flex-column gap-1 align-items-center">
                <small><?= $approvalBadge ?></small>
                <small><?= $verificationBadge ?></small>
                <small><?= $stateBadge ?></small>
            </div>
        </div>
        <hr class="text-white-50">
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'company-dashboard.php' ? 'active' : '' ?>" href="company-dashboard.php">Dashboard</a></li>
            <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'company-add-job.php' ? 'active' : '' ?>" href="company-add-job.php">Post New Job</a></li>
            <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'company-my-jobs.php' ? 'active' : '' ?>" href="company-my-jobs.php">My Jobs</a></li>
            <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'company-applications.php' ? 'active' : '' ?>" href="company-applications.php">Applications</a></li>
            <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'company-verification.php' ? 'active' : '' ?>" href="company-verification.php">Company Verification</a></li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'company-notifications.php' ? 'active' : '' ?>" href="company-notifications.php">
                    Notifications
                    <?php if ($notificationCount > 0): ?>
                        <span class="badge bg-warning text-dark ms-1"><?= (int) $notificationCount ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item"><a class="nav-link" href="../contact-support.php">Contact Support</a></li>
            <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'company-account.php' ? 'active' : '' ?>" href="company-account.php">Account Settings</a></li>
            <li class="nav-item mt-4"><a class="nav-link text-danger" href="../logout.php">Logout</a></li>
        </ul>
    </div>

    <div class="main-content flex-grow-1">
        <main class="container-fluid py-4">
            <?php if ($authFlash): ?>
                <div class="alert alert-<?= htmlspecialchars($authFlash['type'] ?? 'info') ?>">
                    <?= htmlspecialchars($authFlash['message'] ?? '') ?>
                </div>
            <?php endif; ?>
            <?php if ($popupFeedback): ?>
                <div class="alert alert-<?= htmlspecialchars($popupFeedback['type']) ?>">
                    <?= htmlspecialchars($popupFeedback['message']) ?>
                </div>
            <?php endif; ?>
            <?php if ($verificationPopupNotification): ?>
                <div id="verificationReviewPopup" class="verification-popup-backdrop" role="dialog" aria-modal="true" aria-labelledby="verificationReviewPopupTitle">
                    <div class="verification-popup-dialog">
                        <button type="button" class="btn-close" aria-label="Close popup" data-verification-popup-close="1"></button>
                        <div class="text-uppercase text-muted small fw-semibold mb-2">Verification Review</div>
                        <h2 id="verificationReviewPopupTitle" class="h4 mb-2">
                            <?= htmlspecialchars($verificationPopupNotification['title']) ?>
                        </h2>
                        <div class="text-muted small mb-3">
                            <?= date('M d, Y h:i A', strtotime((string)($verificationPopupNotification['created_at'] ?? 'now'))) ?>
                        </div>
                        <div class="verification-popup-message"><?= nl2br(htmlspecialchars($verificationPopupNotification['message'])) ?></div>
                        <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
                            <a href="company-notifications.php" class="btn btn-primary">View Notifications</a>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                <input type="hidden" name="popup_notification_id" value="<?= (int)$verificationPopupNotification['id'] ?>">
                                <button type="submit" name="popup_mark_verification_read" value="1" class="btn btn-outline-success">Mark as Read</button>
                            </form>
                            <button type="button" class="btn btn-outline-secondary" data-verification-popup-close="1">Close</button>
                        </div>
                    </div>
                </div>
                <script>
                document.addEventListener("DOMContentLoaded", function () {
                    const popup = document.getElementById("verificationReviewPopup");
                    if (!popup) {
                        return;
                    }

                    const closePopup = function () {
                        popup.setAttribute("hidden", "");
                    };

                    popup.querySelectorAll("[data-verification-popup-close='1']").forEach(function (button) {
                        button.addEventListener("click", closePopup);
                    });

                    popup.addEventListener("click", function (event) {
                        if (event.target === popup) {
                            closePopup();
                        }
                    });

                    document.addEventListener("keydown", function (event) {
                        if (event.key === "Escape" && !popup.hasAttribute("hidden")) {
                            closePopup();
                        }
                    });
                });
                </script>
            <?php endif; ?>
