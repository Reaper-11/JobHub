<?php
require_once __DIR__ . '/db.php';

$role = current_role();
$isJobSeeker = $role === 'jobseeker';
$isCompany = $role === 'company';
$isAdmin = $role === 'admin';
$isLoggedIn = $role !== null;
$notificationCount = 0;

if ($isJobSeeker && current_user_id() !== null) {
    $notificationCount = notify_unread_count('user', current_user_id());
}

$basePath = isset($basePath) ? trim($basePath) : '';
if ($basePath === '') {
    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $projectRoot = __DIR__;
    if ($docRoot !== '') {
        $docRootNorm = str_replace('\\', '/', $docRoot);
        $projectNorm = str_replace('\\', '/', $projectRoot);
        if (stripos($projectNorm, $docRootNorm) === 0) {
            $relative = substr($projectNorm, strlen($docRootNorm));
            $relative = $relative === '' ? '/' : $relative;
            $basePath = rtrim($relative, '/') . '/';
        }
    }
}

$pageTitle = isset($pageTitle) ? trim($pageTitle) : '';
$bodyClass = isset($bodyClass) ? trim($bodyClass) : '';
$authFlash = jobhub_take_auth_flash();
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle !== '' ? $pageTitle : "JobHub - Nepal's Job Portal") ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>custom.css?v=<?= time() ?>">
    <style>
        .simple-navbar {
            background: linear-gradient(180deg, #020617 0%, #0b1120 100%);
            border-bottom: 1px solid rgba(148, 163, 184, 0.16);
            box-shadow: 0 12px 32px rgba(2, 6, 23, 0.45);
        }
        .simple-navbar-inner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            padding: 14px 0;
        }
        .simple-navbar-links {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            align-items: center;
            gap: 12px;
            padding: 9px 12px;
            border: 1px solid rgba(148, 163, 184, 0.34);
            border-radius: 16px;
            background: rgba(7, 12, 24, 0.82);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.03), 0 10px 22px rgba(2, 6, 23, 0.24);
        }
        .simple-navbar-brand {
            display: inline-flex;
            align-items: center;
            color: #f8fafc;
            padding: 2px 0;
            font-weight: 800;
            font-size: 1.55rem;
            text-decoration: none;
            white-space: nowrap;
            line-height: 1;
            letter-spacing: -0.04em;
            transition: color 0.2s ease, opacity 0.2s ease;
        }
        .simple-navbar-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #cbd5e1;
            text-decoration: none;
            white-space: nowrap;
            font-size: 13.5px;
            font-weight: 600;
            min-height: 42px;
            padding: 8px 10px;
            border-radius: 10px;
            border: 1px solid transparent;
            background: transparent;
            box-shadow: none;
            transition: background 0.15s, color 0.15s, border-color 0.15s, transform 0.15s;
        }
        .simple-navbar-link:hover,
        .simple-navbar-link:focus {
            color: #fff;
            background: rgba(15, 23, 42, 0.94);
            border-color: rgba(96, 165, 250, 0.28);
            transform: translateY(-1px);
        }
        .simple-navbar-logout {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #e53935;
            color: #ffffff;
            min-height: 42px;
            padding: 9px 20px;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            text-decoration: none;
            font-weight: 700;
            font-size: 13px;
            white-space: nowrap;
            box-shadow: 0 10px 24px rgba(229, 57, 53, 0.18);
            transition: opacity 0.2s ease, transform 0.15s ease;
        }
        .simple-navbar-login {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #0369a1;
            color: #fff;
            min-height: 42px;
            padding: 9px 20px;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            text-decoration: none;
            font-weight: 700;
            font-size: 13px;
            white-space: nowrap;
            transition: opacity 0.2s ease, transform 0.15s ease;
        }
        .simple-navbar-register {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #166534;
            color: #fff;
            min-height: 42px;
            padding: 9px 20px;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            text-decoration: none;
            font-weight: 700;
            font-size: 13px;
            white-space: nowrap;
            transition: opacity 0.2s ease, transform 0.15s ease;
        }
        .simple-navbar-notif-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            background: #ef4444;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            border-radius: 99px;
            margin-left: 5px;
            vertical-align: middle;
            line-height: 1;
        }
        .simple-navbar-brand:hover,
        .simple-navbar-logout:hover,
        .simple-navbar-login:hover,
        .simple-navbar-register:hover {
            opacity: 0.85;
            transform: translateY(-1px);
        }
        @media (max-width: 768px) {
            .simple-navbar-inner {
                flex-direction: column;
                align-items: flex-start;
            }
            .simple-navbar-links {
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100<?= $bodyClass !== '' ? ' ' . htmlspecialchars($bodyClass) : '' ?>">

<?php
$navLinks = [];

if ($isLoggedIn && $isJobSeeker) {
    $notificationLabel = 'Notifications';
    if ($notificationCount > 0) {
        $notificationLabel .= ' (' . (int) $notificationCount . ')';
    }

    $navLinks = [
        ['href' => $basePath . 'jobs.php', 'label' => 'Jobs'],
        ['href' => $basePath . 'my-bookmarks.php', 'label' => 'Bookmarks'],
        ['href' => $basePath . 'my-applications.php', 'label' => 'Applications'],
        ['href' => $basePath . 'notifications.php', 'label' => $notificationLabel],
        ['href' => $basePath . 'user-account.php', 'label' => 'Account'],
        ['href' => $basePath . 'contact-support.php', 'label' => 'Contact Support'],
        ['href' => $basePath . 'logout.php', 'label' => 'Logout'],
    ];
} elseif ($isCompany) {
    $navLinks = [
        ['href' => $basePath . 'company/company-dashboard.php', 'label' => 'Dashboard'],
        ['href' => $basePath . 'company/company-my-jobs.php', 'label' => 'My Jobs'],
        ['href' => $basePath . 'contact-support.php', 'label' => 'Contact Support'],
        ['href' => $basePath . 'logout.php', 'label' => 'Logout'],
    ];
} elseif ($isAdmin) {
    $navLinks = [
        ['href' => $basePath . 'admin/admin-dashboard.php', 'label' => 'Admin Panel'],
        ['href' => $basePath . 'logout.php', 'label' => 'Logout'],
    ];
} else {
    $navLinks = [
        ['href' => $basePath . 'index.php', 'label' => 'Home'],
        ['href' => $basePath . 'jobs.php', 'label' => 'Jobs'],
        ['href' => $basePath . 'login.php', 'label' => 'Login'],
        ['href' => $basePath . 'register-choice.php', 'label' => 'Register'],
    ];
}
?>
<header class="simple-navbar">
    <nav class="container simple-navbar-inner" aria-label="Main navigation">
        <a class="simple-navbar-brand" href="<?= htmlspecialchars($basePath) ?>index.php" aria-label="JobHub">
            <span class="brand-wordmark brand-wordmark--nav">
                <span class="brand-wordmark__job">Job</span><span class="brand-wordmark__hub">Hub</span>
            </span>
        </a>
        <div class="simple-navbar-links">
            <?php foreach ($navLinks as $link): ?>
                <?php
                $isLogoutLink = $link['href'] === $basePath . 'logout.php';
                $isLoginLink  = $link['href'] === $basePath . 'login.php';
                $isRegLink    = $link['href'] === $basePath . 'register-choice.php';
                $isNotifLink  = str_contains($link['label'], 'Notification');
                $linkClass = 'simple-navbar-link';
                if ($isLogoutLink) $linkClass = 'simple-navbar-logout';
                elseif ($isLoginLink) $linkClass = 'simple-navbar-login';
                elseif ($isRegLink)   $linkClass = 'simple-navbar-register';
                ?>
                <a class="<?= $linkClass ?>" href="<?= htmlspecialchars($link['href']) ?>">
                    <?php if ($isNotifLink): ?>
                        Notifications
                        <?php if ($notificationCount > 0): ?>
                            <span class="simple-navbar-notif-badge"><?= (int)$notificationCount ?></span>
                        <?php endif; ?>
                    <?php else: ?>
                        <?= htmlspecialchars($link['label']) ?>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>
</header>

<main class="container py-4 flex-grow-1">
    <?php if ($authFlash): ?>
        <div class="alert alert-<?= htmlspecialchars($authFlash['type'] ?? 'info') ?>">
            <?= htmlspecialchars($authFlash['message'] ?? '') ?>
        </div>
    <?php endif; ?>
