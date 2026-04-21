<?php
require_once '../db.php';

require_role('admin');

$hasSidebarLayout   = true;
$hideSharedFooter   = true;
$authFlash          = jobhub_take_auth_flash();

// Unread support-message badge shown in the sidebar nav.
$adminNavUnreadSupport = 0;
if (function_exists('jobhub_table_exists') && jobhub_table_exists($conn, 'support_messages')) {
    $adminNavUnreadSupport = (int)db_query_value(
        "SELECT COUNT(*) FROM support_messages WHERE is_read = 0 AND is_deleted = 0",
        '', [], 0
    );
}

// Human-readable page title map for the topbar.
$adminPageTitles = [
    'admin-dashboard.php'          => 'Dashboard',
    'admin-users.php'              => 'Manage Users',
    'admin-companies.php'          => 'Manage Companies',
    'admin-jobs.php'               => 'Job Approval',
    'company-verifications.php'    => 'Company Verifications',
    'company-verification-view.php'=> 'Review Verification',
    'admin-applications.php'       => 'Applications',
    'activity-monitor.php'         => 'Activity Monitor',
    'support-messages.php'         => 'Support Messages',
    'support-view.php'             => 'View Support Message',
    'job-view.php'                 => 'Job Review',
    'application-details.php'      => 'Application Details',
];
$_adminCurrentFile = basename($_SERVER['PHP_SELF']);
$adminPageTitle    = $adminPageTitles[$_adminCurrentFile]
    ?? ucwords(str_replace(['-', '.php'], [' ', ''], $_adminCurrentFile));
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - JobHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../custom.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: #020617;
            color: #e2e8f0;
        }
        .sidebar {
            min-height: 100vh;
            width: 240px;
            flex-shrink: 0;
            position: sticky;
            top: 0;
            align-self: flex-start;
            height: 100vh;
            overflow-y: auto;
            background: #0f172a;
            color: white;
            display: flex;
            flex-direction: column;
        }
        .sidebar-brand {
            padding: 20px 20px 16px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .sidebar-brand .brand-logo {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }
        .sidebar-brand .brand-icon {
            width: 36px; height: 36px;
            background: #3b82f6;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px; color: #fff;
        }
        .sidebar-brand .brand-name { font-size: 16px; font-weight: 600; color: #fff; }
        .sidebar-brand .brand-sub  { font-size: 11px; color: rgba(255,255,255,0.45); display: block; }
        .sidebar-nav { padding: 12px 10px; flex: 1; }
        .sidebar-section-label {
            font-size: 10px;
            font-weight: 600;
            color: rgba(255,255,255,0.3);
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 12px 10px 6px;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.65);
            border-radius: 8px;
            padding: 9px 12px;
            font-size: 13.5px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 2px;
            transition: background 0.15s, color 0.15s;
        }
        .sidebar .nav-link i { width: 16px; text-align: center; font-size: 13px; opacity: 0.8; }
        .sidebar .nav-link:hover { color: white; background: rgba(255,255,255,0.08); }
        .sidebar .nav-link.active { color: white; background: #3b82f6; }
        .sidebar .nav-link.active i { opacity: 1; }
        .sidebar .nav-link.logout { color: #f87171; margin-top: 4px; }
        .sidebar .nav-link.logout:hover { background: rgba(248,113,113,0.12); }
        .sidebar-footer {
            padding: 12px 10px;
            border-top: 1px solid rgba(255,255,255,0.08);
        }
        .main-content {
            background: radial-gradient(circle at top, rgba(59, 130, 246, 0.14), transparent 28%), linear-gradient(180deg, #020617 0%, #081120 100%);
            min-height: 100vh;
            color: #e2e8f0;
        }
        .main-content .container-fluid { max-width: 1400px; color: #e2e8f0; }

        .stat-card {
            background: rgba(15, 23, 42, 0.92);
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #243041;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 18px 40px rgba(2, 6, 23, 0.34);
        }
        .stat-card-icon {
            width: 48px; height: 48px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }
        .stat-card-icon.blue   { background: #dbeafe; color: #2563eb; }
        .stat-card-icon.green  { background: #dcfce7; color: #16a34a; }
        .stat-card-icon.amber  { background: #fef3c7; color: #d97706; }
        .stat-card-icon.purple { background: #ede9fe; color: #7c3aed; }
        .stat-card-icon.red    { background: #fee2e2; color: #dc2626; }
        .stat-card-icon.teal   { background: #ccfbf1; color: #0d9488; }
        .stat-card-body .stat-label { font-size: 12px; color: #94a3b8; font-weight: 500; margin-bottom: 2px; }
        .stat-card-body .stat-value { font-size: 24px; font-weight: 700; color: #f8fafc; line-height: 1.2; }
        .stat-card-body .stat-sub   { font-size: 11px; color: #64748b; margin-top: 2px; }

        .page-topbar {
            background: rgba(2, 6, 23, 0.88);
            border-bottom: 1px solid #1e293b;
            padding: 14px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 12px 30px rgba(2, 6, 23, 0.35);
        }
        .page-topbar h1 { font-size: 18px; font-weight: 600; color: #f8fafc; margin: 0; }
        .page-topbar .topbar-meta { font-size: 12px; color: #94a3b8; }

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
            border-bottom: 1px solid #243041;
        }
        .card {
            background: rgba(15, 23, 42, 0.92);
            border: 1px solid #243041;
            border-radius: 12px;
            box-shadow: 0 18px 40px rgba(2, 6, 23, 0.34);
            color: #e2e8f0;
        }
        .card-header {
            background: #111827;
            border-bottom: 1px solid #243041;
            font-weight: 600;
            font-size: 14px;
            color: #f8fafc;
        }
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
        .card .text-muted { color: #94a3b8 !important; }
        .content-surface {
            background: #111827 !important;
            border: 1px solid #243041 !important;
            color: #e2e8f0 !important;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.02);
        }
    </style>
</head>
<body>

<div class="d-flex">
    <div class="sidebar">
        <div class="sidebar-brand">
            <a class="brand-logo" href="admin-dashboard.php">
                <div class="brand-icon"><i class="fas fa-shield-halved"></i></div>
                <div>
                    <span class="brand-name" aria-label="JobHub">
                        <span class="brand-wordmark brand-wordmark--sidebar">
                            <span class="brand-wordmark__job">Job</span><span class="brand-wordmark__hub">Hub</span>
                        </span>
                    </span>
                    <span class="brand-sub">Admin Panel</span>
                </div>
            </a>
        </div>
        <div class="sidebar-nav">
            <div class="sidebar-section-label">Overview</div>
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link <?= $_adminCurrentFile === 'admin-dashboard.php' ? 'active' : '' ?>" href="admin-dashboard.php"><i class="fas fa-gauge-high"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link <?= $_adminCurrentFile === 'activity-monitor.php' ? 'active' : '' ?>" href="activity-monitor.php"><i class="fas fa-chart-line"></i> Activity Monitor</a></li>
            </ul>
            <div class="sidebar-section-label">Manage</div>
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link <?= $_adminCurrentFile === 'admin-jobs.php' ? 'active' : '' ?>" href="admin-jobs.php?status=all&page=1"><i class="fas fa-briefcase"></i> Job Approval</a></li>
                <li class="nav-item"><a class="nav-link <?= $_adminCurrentFile === 'admin-companies.php' ? 'active' : '' ?>" href="admin-companies.php?status=all&page=1"><i class="fas fa-building"></i> Companies</a></li>
                <li class="nav-item"><a class="nav-link <?= in_array($_adminCurrentFile, ['company-verifications.php', 'company-verification-view.php'], true) ? 'active' : '' ?>" href="company-verifications.php?status=all&page=1"><i class="fas fa-circle-check"></i> Verifications</a></li>
                <li class="nav-item"><a class="nav-link <?= $_adminCurrentFile === 'admin-users.php' ? 'active' : '' ?>" href="admin-users.php?status=all&page=1"><i class="fas fa-users"></i> Users</a></li>
                <li class="nav-item"><a class="nav-link <?= $_adminCurrentFile === 'admin-applications.php' ? 'active' : '' ?>" href="admin-applications.php"><i class="fas fa-file-alt"></i> Applications</a></li>
                <li class="nav-item">
                    <a class="nav-link <?= in_array($_adminCurrentFile, ['support-messages.php', 'support-view.php'], true) ? 'active' : '' ?>" href="support-messages.php">
                        <i class="fas fa-headset"></i> Support Messages
                        <?php if ($adminNavUnreadSupport > 0): ?>
                            <span class="badge bg-danger ms-auto" style="font-size:10px;padding:2px 6px;"><?= (int)$adminNavUnreadSupport ?></span>
                        <?php endif; ?>
                    </a>
                </li>
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
            <h1><?= htmlspecialchars($adminPageTitle) ?></h1>
            <span class="topbar-meta"><?= date('D, M d Y') ?></span>
        </div>
        <main class="container-fluid py-4">
            <?php if ($authFlash): ?>
                <div class="alert alert-<?= htmlspecialchars($authFlash['type'] ?? 'info') ?>">
                    <?= htmlspecialchars($authFlash['message'] ?? '') ?>
                </div>
            <?php endif; ?>
