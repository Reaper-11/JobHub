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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle !== '' ? $pageTitle : "JobHub - Nepal's Job Portal") ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>custom.css?v=<?= time() ?>">
    <style>
        .simple-navbar {
            background: #000000;
        }

        .simple-navbar-inner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            padding: 15px 0;
        }

        .simple-navbar-links {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 12px 25px;
        }

        .simple-navbar-brand {
            display: inline-block;
            background: #ffffff;
            color: #000000;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 18px;
            text-decoration: none;
            white-space: nowrap;
            transition: opacity 0.2s ease;
        }

        .simple-navbar-link {
            color: #ffffff;
            text-decoration: none;
            white-space: nowrap;
            transition: opacity 0.2s ease;
        }

        .simple-navbar-logout {
            display: inline-block;
            background: #e53935;
            color: #ffffff;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 700;
            white-space: nowrap;
            transition: opacity 0.2s ease;
        }

        .simple-navbar-brand:hover,
        .simple-navbar-brand:focus,
        .simple-navbar-link:hover,
        .simple-navbar-link:focus,
        .simple-navbar-logout:hover,
        .simple-navbar-logout:focus {
            opacity: 0.8;
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
        ['href' => $basePath . 'login.php', 'label' => 'Login'],
        ['href' => $basePath . 'register-choice.php', 'label' => 'Register'],
    ];
}
?>
<header class="simple-navbar">
    <nav class="container simple-navbar-inner" aria-label="Main navigation">
        <a class="simple-navbar-brand" href="<?= htmlspecialchars($basePath) ?>index.php">JobHub</a>
        <div class="simple-navbar-links">
            <?php foreach ($navLinks as $link): ?>
                <?php $isLogoutLink = $link['href'] === $basePath . 'logout.php'; ?>
                <a class="<?= $isLogoutLink ? 'simple-navbar-logout' : 'simple-navbar-link' ?>" href="<?= htmlspecialchars($link['href']) ?>"><?= htmlspecialchars($link['label']) ?></a>
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
