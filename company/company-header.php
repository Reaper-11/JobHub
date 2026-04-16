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
    SELECT name, email, is_approved, is_active, rejection_reason, operational_state, restriction_reason, restricted_at,
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
    'is_active' => 1,
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
$finalCompanyStatus = jobhub_company_final_status($company);
$operationalState = $company['operational_state'] ?? 'active';
$restrictionReason = $company['restriction_reason'] ?? '';
$restrictedAt = $company['restricted_at'] ?? null;
$rejectionReason = $company['rejection_reason'] ?? '';
$verificationStatus = get_company_verification_status($company);
$isVerified = is_company_verified($company);
$canPostJobs = jobhub_company_can_post_jobs($company);
$authFlash = jobhub_take_auth_flash();

// Build status badges for the info box (clean display)
$infoBadges = '';

// Show "Active" if company is active
if ($finalCompanyStatus === 'active') {
    $infoBadges .= '<span class="badge bg-success" title="Company Status">Active</span>';
}

// Show "Verified" if verification is complete
if ($isVerified) {
    $infoBadges .= '<span class="badge bg-primary" title="Verification Status">Verified</span>';
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Dashboard - JobHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../custom.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: #020617;
            color: #e2e8f0;
        }
        .company-sidebar {
            min-height: 100vh;
            width: 240px;
            flex-shrink: 0;
            background: #0d1b2a;
            color: white;
            display: flex;
            flex-direction: column;
        }
        .sidebar-brand {
            padding: 20px 20px 16px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .sidebar-brand .brand-logo {
            display: inline-flex; align-items: center; gap: 10px; text-decoration: none;
        }
        .sidebar-brand .brand-icon {
            width: 36px; height: 36px; background: #0ea5e9;
            border-radius: 8px; display: flex; align-items: center; justify-content: center;
            font-size: 16px; color: #fff;
        }
        .sidebar-brand .brand-name { font-size: 16px; font-weight: 600; color: #fff; }
        .sidebar-brand .brand-sub  { font-size: 11px; color: rgba(255,255,255,0.45); display: block; }
        .company-info-box {
            margin: 12px 10px;
            background: transparent;
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 10px;
            padding: 10px 12px;
        }
        .company-info-box .co-name {
            font-size: 14px; font-weight: 700; color: #fff; margin-bottom: 8px;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .company-info-box .badge { font-size: 11px; padding: 4px 8px; border-radius: 5px; margin-right: 4px; }
        .sidebar-section-label {
            font-size: 10px; font-weight: 600; color: rgba(255,255,255,0.3);
            letter-spacing: 0.08em; text-transform: uppercase; padding: 10px 10px 5px;
        }
        .sidebar-nav { padding: 4px 10px; flex: 1; }
        .company-sidebar .nav-link {
            color: rgba(255,255,255,0.65);
            border-radius: 8px;
            padding: 9px 12px;
            font-size: 13.5px;
            display: flex; align-items: center; gap: 10px;
            margin-bottom: 2px;
            transition: background 0.15s, color 0.15s;
        }
        .company-sidebar .nav-link i { width: 16px; text-align: center; font-size: 13px; opacity: 0.8; }
        .company-sidebar .nav-link:hover { color: white; background: rgba(255,255,255,0.08); }
        .company-sidebar .nav-link.active { color: white; background: #0ea5e9; }
        .company-sidebar .nav-link.active i { opacity: 1; }
        .company-sidebar .nav-link.logout { color: #f87171; }
        .company-sidebar .nav-link.logout:hover { background: rgba(248,113,113,0.12); }
        .sidebar-footer { padding: 12px 10px; border-top: 1px solid rgba(255,255,255,0.08); }
        .main-content {
            background: radial-gradient(circle at top, rgba(14, 165, 233, 0.14), transparent 28%), linear-gradient(180deg, #020617 0%, #081120 100%);
            min-height: 100vh;
            color: #e2e8f0;
            display: flex;
            flex-direction: column;
        }
        .main-content > main { flex: 1 0 auto; }
        .page-topbar {
            background: rgba(2, 6, 23, 0.88); border-bottom: 1px solid #1e293b;
            padding: 14px 24px; display: flex; align-items: center; justify-content: space-between;
            box-shadow: 0 12px 30px rgba(2, 6, 23, 0.35);
        }
        .page-topbar h1 { font-size: 18px; font-weight: 600; color: #f8fafc; margin: 0; }
        .page-topbar .topbar-meta { font-size: 12px; color: #94a3b8; }
        .pending-banner { background: rgba(245, 158, 11, 0.14); border-left: 4px solid #f59e0b; border-radius: 0 8px 8px 0; color: #fde68a; }
        .card {
            background: rgba(15, 23, 42, 0.92);
            border: 1px solid #243041;
            border-radius: 12px;
            box-shadow: 0 18px 40px rgba(2, 6, 23, 0.34);
            color: #e2e8f0;
        }
        .card-header { background: #111827; border-bottom: 1px solid #243041; font-weight: 600; font-size: 14px; color: #f8fafc; }
        .card-header.bg-light {
            background: #111827 !important;
            color: #f8fafc !important;
        }
        .card-header.bg-light h1,
        .card-header.bg-light h2,
        .card-header.bg-light h3,
        .card-header.bg-light h4,
        .card-header.bg-light h5,
        .card-header.bg-light h6,
        .card-header.bg-light span,
        .card-header.bg-light small {
            color: inherit !important;
        }
        .table {
            font-size: 13.5px;
            --bs-table-bg: transparent;
            --bs-table-color: #cbd5e1;
            --bs-table-border-color: #243041;
            --bs-table-hover-bg: rgba(148, 163, 184, 0.08);
            --bs-table-hover-color: #f8fafc;
            --bs-table-striped-bg: rgba(148, 163, 184, 0.04);
            --bs-table-striped-color: #e2e8f0;
        }
        .table thead th {
            background: #111827; color: #cbd5e1; font-weight: 600;
            font-size: 12px; letter-spacing: 0.04em; text-transform: uppercase;
        }
        .stat-card {
            background: rgba(15, 23, 42, 0.92); border-radius: 12px; padding: 20px;
            border: 1px solid #243041; display: flex; align-items: center; gap: 16px;
            box-shadow: 0 18px 40px rgba(2, 6, 23, 0.34);
        }
        .stat-card-icon { width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
        .stat-card-icon.blue   { background: #dbeafe; color: #2563eb; }
        .stat-card-icon.green  { background: #dcfce7; color: #16a34a; }
        .stat-card-icon.amber  { background: #fef3c7; color: #d97706; }
        .stat-card-icon.purple { background: #ede9fe; color: #7c3aed; }
        .stat-card-body .stat-label { font-size: 12px; color: #94a3b8; font-weight: 500; margin-bottom: 2px; }
        .stat-card-body .stat-value { font-size: 24px; font-weight: 700; color: #f8fafc; line-height: 1.2; }
        .stat-card-body .stat-sub   { font-size: 11px; color: #64748b; margin-top: 2px; }
        .verification-popup-backdrop {
            position: fixed; inset: 0; z-index: 1080;
            display: flex; align-items: center; justify-content: center; padding: 24px;
            background: rgba(15, 23, 42, 0.72);
        }
        .verification-popup-backdrop[hidden] { display: none !important; }
        .verification-popup-dialog {
            position: relative; width: min(100%, 620px);
            max-height: calc(100vh - 48px); overflow-y: auto; padding: 24px;
            border-radius: 18px; background: #0f172a; border: 1px solid #243041;
            box-shadow: 0 24px 80px rgba(15, 23, 42, 0.3);
        }
        .verification-popup-message {
            margin-top: 12px; padding: 14px 16px; border: 1px solid #e9ecef;
            border-radius: 12px; background: #111827; white-space: pre-wrap; word-break: break-word;
        }
        .verification-popup-dialog .btn-close { filter: invert(1) grayscale(1); }
        .card .text-muted { color: #94a3b8 !important; }
    </style>
</head>
<body>

<div class="d-flex">
    <div class="company-sidebar">
        <div class="sidebar-brand">
            <a class="brand-logo" href="company-dashboard.php">
                <div class="brand-icon"><i class="fas fa-building"></i></div>
                <div>
                    <span class="brand-name" aria-label="JobHub">
                        <span class="brand-wordmark brand-wordmark--sidebar">
                            <span class="brand-wordmark__job">Job</span><span class="brand-wordmark__hub">Hub</span>
                        </span>
                    </span>
                    <span class="brand-sub">Company Portal</span>
                </div>
            </a>
        </div>
        <div class="company-info-box">
            <div class="co-name"><?= htmlspecialchars($company['name']) ?></div>
            <div class="d-flex flex-wrap gap-0">
                <?= $infoBadges ?>
            </div>
        </div>
        <div class="sidebar-nav">
            <div class="sidebar-section-label">Menu</div>
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'company-dashboard.php' ? 'active' : '' ?>" href="company-dashboard.php"><i class="fas fa-gauge-high"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'company-add-job.php' ? 'active' : '' ?>" href="company-add-job.php"><i class="fas fa-plus-circle"></i> Post New Job</a></li>
                <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'company-my-jobs.php' ? 'active' : '' ?>" href="company-my-jobs.php"><i class="fas fa-briefcase"></i> My Jobs</a></li>
                <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'company-applications.php' ? 'active' : '' ?>" href="company-applications.php"><i class="fas fa-file-alt"></i> Applications</a></li>
                <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'company-verification.php' ? 'active' : '' ?>" href="company-verification.php"><i class="fas fa-circle-check"></i> Verification</a></li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'company-notifications.php' ? 'active' : '' ?>" href="company-notifications.php">
                        <i class="fas fa-bell"></i> Notifications
                        <?php if ($notificationCount > 0): ?>
                            <span class="badge bg-warning text-dark ms-auto"><?= (int) $notificationCount ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'company-contact-support.php' ? 'active' : '' ?>" href="company-contact-support.php"><i class="fas fa-headset"></i> Contact Support</a></li>
                <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'company-account.php' ? 'active' : '' ?>" href="company-account.php"><i class="fas fa-gear"></i> Account Settings</a></li>
            </ul>
        </div>
        <div class="sidebar-footer">
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link logout" href="../logout.php"><i class="fas fa-right-from-bracket"></i> Logout</a></li>
            </ul>
        </div>
    </div>

    <div class="main-content flex-grow-1">
        <div class="page-topbar">
            <h1><?= htmlspecialchars($company['name']) ?></h1>
            <span class="topbar-meta"><?= date('D, M d Y') ?></span>
        </div>
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
