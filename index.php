<?php
require 'db.php';

$keyword = trim($_GET['q'] ?? '');
$filter  = trim($_GET['filter'] ?? '');

$categories = require __DIR__ . '/includes/categories.php';

$sql = "SELECT j.*, COALESCE(j.application_count, 0) AS application_count
        FROM jobs j
        LEFT JOIN companies c ON j.company_id = c.id
        WHERE (j.company_id IS NULL OR c.is_approved = 1)
          AND j.status = 'active'";

$types = '';
$params = [];

if ($keyword) {
    $sql .= " AND (j.title LIKE ? OR j.description LIKE ? OR j.company LIKE ?)";
    $like = "%$keyword%";
    $types .= 'sss';
    $params = [$like, $like, $like];
}

if ($filter && in_array($filter, $categories, true)) {
    $sql .= " AND j.category = ?";
    $types .= 's';
    $params[] = $filter;
}

$sql .= " ORDER BY application_count DESC, j.created_at DESC LIMIT 50";

$jobs = db_query_all($sql, $types, $params);

$topJobs = db_query_all(
    "SELECT j.*, COALESCE(j.application_count, 0) AS application_count
     FROM jobs j
     LEFT JOIN companies c ON j.company_id = c.id
     WHERE (j.company_id IS NULL OR c.is_approved = 1)
       AND j.status = 'active'
     ORDER BY application_count DESC, j.created_at DESC
     LIMIT 6"
);

$isJobSeeker = isset($_SESSION['user_id']);
$isCompany   = isset($_SESSION['company_id']);
$isAdmin     = isset($_SESSION['admin_id']);
$isLoggedIn  = $isJobSeeker || $isCompany || $isAdmin;

$recommendedJobs = [];
if ($isJobSeeker) {
    require_once __DIR__ . '/includes/recommendation.php';
    $recommendedJobs = recommendJobs($conn, (int) $_SESSION['user_id'], 6);
}

$basePath = '';
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JobHub - Find Your Next Opportunity</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .glass-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(26, 35, 126, 0.1);
        }

        .gradient-bg {
            background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 50%, #f8fafc 100%);
        }

        .hover-lift {
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .hover-lift:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(26, 35, 126, 0.15);
        }

        .dot-pattern {
            background-image: url("data:image/svg+xml,%3Csvg width='80' height='80' viewBox='0 0 80 80' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%231a237e' fill-opacity='0.04'%3E%3Ccircle cx='12' cy='12' r='3'/%3E%3Ccircle cx='52' cy='28' r='3'/%3E%3Ccircle cx='32' cy='64' r='3'/%3E%3Ccircle cx='68' cy='60' r='3'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0px) rotate(0deg);
            }

            50% {
                transform: translateY(-20px) rotate(5deg);
            }
        }

        @keyframes pulse-glow {

            0%,
            100% {
                opacity: 0.6;
                transform: scale(1);
            }

            50% {
                opacity: 0.8;
                transform: scale(1.05);
            }
        }

        @keyframes bounce {

            0%,
            20%,
            50%,
            80%,
            100% {
                transform: translateY(0);
            }

            40% {
                transform: translateY(-10px);
            }

            60% {
                transform: translateY(-5px);
            }
        }

        .animate-float {
            animation: float 8s ease-in-out infinite;
        }

        .animate-pulse-glow {
            animation: pulse-glow 3s ease-in-out infinite;
        }

        .animate-bounce {
            animation: bounce 2s infinite;
        }

        .text-shadow-lg {
            text-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .hover-glow {
            transition: all 0.3s ease;
        }

        .hover-glow:hover {
            filter: drop-shadow(0 0 8px rgba(255, 152, 0, 0.4));
        }

        .staircase-line {
            background: linear-gradient(to right, transparent 0%, #1a237e 50%, transparent 100%);
            height: 2px;
        }

        .timeline-connector {
            position: relative;
        }

        .timeline-connector::after {
            content: '';
            position: absolute;
            top: 40%;
            right: -25%;
            width: 50%;
            height: 2px;
            background: linear-gradient(to right, #1a237e, #283593);
            opacity: 0.3;
        }

        @media (max-width: 768px) {
            .timeline-connector::after {
                display: none;
            }
        }
    </style>
</head>

<body class="bg-gradient-to-br from-gray-50 to-blue-50 min-h-screen antialiased text-gray-900">
    <header class="bg-black sticky top-0 z-50 border-b border-black/80 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex justify-between items-center">
                <a href="<?= $basePath ?>index.php" class="flex items-center gap-3 group">
                    <div class="p-3 bg-gradient-to-r from-[#1a237e] to-[#283593] rounded-xl shadow-lg group-hover:scale-105 transition-transform duration-300">
                        <i class="fas fa-briefcase text-xl text-white"></i>
                    </div>
                    <h1 class="text-2xl md:text-3xl font-bold text-white tracking-tight">
                        Job<span class="text-white">Hub</span>
                    </h1>
                </a>

                <div class="hidden md:flex items-center gap-6">
                    <?php if ($isLoggedIn && $isJobSeeker): ?>
                        <a href="<?= $basePath ?>my-bookmarks.php"
                            class="relative text-white hover:text-white font-medium transition px-4 py-2.5 rounded-lg hover:bg-white/10 flex items-center gap-2 group">
                            <i class="fas fa-bookmark"></i>
                            <span>Bookmarks</span>
                            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-[#1a237e] group-hover:w-full transition-all duration-300"></span>
                        </a>
                        <a href="<?= $basePath ?>my-applications.php"
                            class="relative text-white hover:text-white font-medium transition px-4 py-2.5 rounded-lg hover:bg-white/10 flex items-center gap-2 group">
                            <i class="fas fa-clipboard-list"></i>
                            <span>Applications</span>
                            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-[#1a237e] group-hover:w-full transition-all duration-300"></span>
                        </a>
                        <a href="<?= $basePath ?>user-account.php"
                            class="relative text-white hover:text-white font-medium transition px-4 py-2.5 rounded-lg hover:bg-white/10 flex items-center gap-2 group">
                            <i class="fas fa-user"></i>
                            <span>Account</span>
                            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-[#1a237e] group-hover:w-full transition-all duration-300"></span>
                        </a>
                    <?php elseif ($isCompany): ?>
                        <a href="<?= $basePath ?>company/company-dashboard.php"
                            class="relative text-white hover:text-white font-medium transition px-4 py-2.5 rounded-lg hover:bg-white/10 flex items-center gap-2 group">
                            <i class="fas fa-columns"></i>
                            <span>Dashboard</span>
                            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-[#1a237e] group-hover:w-full transition-all duration-300"></span>
                        </a>
                        <a href="<?= $basePath ?>company/my-jobs.php"
                            class="relative text-gray-700 hover:text-[#1a237e] font-medium transition px-4 py-2.5 rounded-lg hover:bg-[#1a237e]/5 flex items-center gap-2 group">
                            <i class="fas fa-briefcase"></i>
                            <span>My Jobs</span>
                            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-[#1a237e] group-hover:w-full transition-all duration-300"></span>
                        </a>
                    <?php elseif ($isAdmin): ?>
                        <a href="<?= $basePath ?>admin/dashboard.php"
                            class="relative text-gray-700 hover:text-[#1a237e] font-medium transition px-4 py-2.5 rounded-lg hover:bg-[#1a237e]/5 flex items-center gap-2 group">
                            <i class="fas fa-user-shield"></i>
                            <span>Admin Panel</span>
                            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-[#1a237e] group-hover:w-full transition-all duration-300"></span>
                        </a>
                    <?php endif; ?>

                    <?php if ($isLoggedIn): ?>
                        <a href="<?= $basePath ?>logout.php"
                            class="relative px-6 py-3 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-xl hover:opacity-90 font-medium transition shadow-md hover:shadow-lg flex items-center gap-2 overflow-hidden group">
                            <span class="relative z-10 flex items-center gap-2">
                                <i class="fas fa-sign-out-alt"></i>
                                Log Out
                            </span>
                            <div class="absolute inset-0 bg-white/20 transform -translate-x-full group-hover:translate-x-0 transition-transform duration-500"></div>
                        </a>
                    <?php else: ?>
                        <a href="<?= $basePath ?>login-choice.php"
                            class="px-4 py-2.5 rounded-xl border border-white text-white bg-transparent font-semibold shadow-sm hover:bg-white/10 transition flex items-center gap-2">
                            <i class="fas fa-sign-in-alt"></i>
                            <span>Log In</span>
                        </a>
                        <a href="<?= $basePath ?>register-choice.php"
                            class="relative px-6 py-3 bg-gradient-to-r from-[#ff9800] to-[#ffb74d] text-white rounded-xl hover:opacity-90 font-medium transition shadow-md hover:shadow-lg flex items-center gap-2 overflow-hidden group">
                            <span class="relative z-10 flex items-center gap-2">
                                <i class="fas fa-user-plus"></i>
                                Register
                            </span>
                            <div class="absolute inset-0 bg-white/20 transform -translate-x-full group-hover:translate-x-0 transition-transform duration-500"></div>
                        </a>
                    <?php endif; ?>
                </div>

                <button id="mobile-menu-button" class="md:hidden p-2 rounded-lg hover:bg-white/10 transition">
                    <i class="fas fa-bars text-xl text-white"></i>
                </button>
            </div>

            <div id="mobile-menu" class="hidden md:hidden mt-4 pt-4 border-t border-white/10">
                <div class="space-y-3">
                    <?php if ($isLoggedIn && $isJobSeeker): ?>
                        <a href="<?= $basePath ?>my-bookmarks.php"
                            class="block w-full text-left px-4 py-3 rounded-lg bg-white/5 text-white font-medium hover:bg-white/10 transition flex items-center gap-3">
                            <i class="fas fa-bookmark"></i>
                            Bookmarks
                        </a>
                        <a href="<?= $basePath ?>my-applications.php"
                            class="block w-full text-left px-4 py-3 rounded-lg bg-white/5 text-white font-medium hover:bg-white/10 transition flex items-center gap-3">
                            <i class="fas fa-clipboard-list"></i>
                            Applications
                        </a>
                        <a href="<?= $basePath ?>user-account.php"
                            class="block w-full text-left px-4 py-3 rounded-lg bg-white/5 text-white font-medium hover:bg-white/10 transition flex items-center gap-3">
                            <i class="fas fa-user"></i>
                            Account
                        </a>
                    <?php elseif ($isCompany): ?>
                        <a href="<?= $basePath ?>company/company-dashboard.php"
                            class="block w-full text-left px-4 py-3 rounded-lg bg-white/5 text-white font-medium hover:bg-white/10 transition flex items-center gap-3">
                            <i class="fas fa-columns"></i>
                            Dashboard
                        </a>
                        <a href="<?= $basePath ?>company/my-jobs.php"
                            class="block w-full text-left px-4 py-3 rounded-lg bg-gradient-to-r from-[#1a237e]/5 to-[#283593]/5 text-gray-700 font-medium hover:from-[#1a237e]/10 hover:to-[#283593]/10 transition flex items-center gap-3">
                            <i class="fas fa-briefcase"></i>
                            My Jobs
                        </a>
                    <?php elseif ($isAdmin): ?>
                        <a href="<?= $basePath ?>admin/dashboard.php"
                            class="block w-full text-left px-4 py-3 rounded-lg bg-gradient-to-r from-[#1a237e]/5 to-[#283593]/5 text-gray-700 font-medium hover:from-[#1a237e]/10 hover:to-[#283593]/10 transition flex items-center gap-3">
                            <i class="fas fa-user-shield"></i>
                            Admin Panel
                        </a>
                    <?php endif; ?>

                    <?php if ($isLoggedIn): ?>
                        <a href="<?= $basePath ?>logout.php"
                            class="block w-full text-left px-4 py-3 rounded-lg bg-gradient-to-r from-red-600 to-red-700 text-white font-medium hover:opacity-90 transition flex items-center gap-3">
                            <i class="fas fa-sign-out-alt"></i>
                            Log Out
                        </a>
                    <?php else: ?>
                        <a href="<?= $basePath ?>login-choice.php"
                            class="block w-full text-left px-4 py-3 rounded-lg border border-white text-white bg-transparent font-semibold hover:bg-white/10 transition flex items-center gap-3">
                            <i class="fas fa-sign-in-alt"></i>
                            Log In
                        </a>
                        <a href="<?= $basePath ?>register-choice.php"
                            class="block w-full text-left px-4 py-3 rounded-lg bg-gradient-to-r from-[#ff9800] to-[#ffb74d] text-white font-medium hover:opacity-90 transition flex items-center gap-3">
                            <i class="fas fa-user-plus"></i>
                            Register
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <div id="scroll-hint" class="fixed bottom-8 left-1/2 transform -translate-x-1/2 z-40 animate-bounce">
        <div class="flex flex-col items-center">
            <span class="text-xs text-gray-600 mb-2 font-medium">View Featured Jobs</span>
            <a href="#featured" class="w-12 h-12 rounded-full bg-gradient-to-r from-[#1a237e] to-[#283593] flex items-center justify-center shadow-lg hover:shadow-xl transition">
                <i class="fas fa-chevron-down text-white"></i>
            </a>
        </div>
    </div>

    <section class="gradient-bg py-16 md:py-24 relative overflow-hidden dot-pattern">
        <div class="absolute inset-0 overflow-hidden">
            <div class="absolute top-20 left-10 w-72 h-72 bg-gradient-to-r from-[#1a237e]/10 to-[#283593]/10 rounded-full animate-float"></div>
            <div class="absolute bottom-20 right-10 w-96 h-96 bg-gradient-to-r from-[#ff9800]/10 to-[#ffb74d]/10 rounded-full animate-float" style="animation-delay: 1s;"></div>
            <div class="absolute top-1/2 left-1/3 w-64 h-64 bg-gradient-to-r from-[#388e3c]/5 to-[#4caf50]/5 rounded-full animate-pulse-glow"></div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
            <?php if (isset($_GET['welcome']) && $_GET['welcome'] === '1'): ?>
                <div class="inline-flex items-center gap-3 bg-gradient-to-r from-[#388e3c]/10 to-[#4caf50]/10 px-6 py-3 rounded-full mb-8 border border-[#388e3c]/20 backdrop-blur-sm">
                    <i class="fas fa-check-circle text-[#388e3c] animate-pulse-glow"></i>
                    <span class="font-medium text-gray-900">Account created successfully. You are now signed in.</span>
                </div>
            <?php endif; ?>

            <h1 class="text-4xl md:text-6xl lg:text-7xl font-black text-gray-900 mb-8 leading-tight tracking-tight">
                Find Your <span class="text-gray-900">Next</span><br class="hidden md:block"> Career Opportunity
            </h1>

            <p class="text-xl md:text-2xl text-gray-700 mb-12 max-w-4xl mx-auto leading-relaxed font-light">
                Discover verified openings from trusted employers across Nepal with a
                <span class="font-bold text-[#1a237e]">transparent hiring process</span> and fast application workflow.
            </p>

            <div class="max-w-4xl mx-auto bg-white/90 backdrop-blur-sm rounded-2xl shadow-xl border border-gray-200/60 p-4 md:p-6">
                <form method="get" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-center">
                    <div class="md:col-span-6">
                        <div class="relative">
                            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <input type="text" name="q" value="<?= htmlspecialchars($keyword) ?>"
                                placeholder="Job title, company, keywords..."
                                class="w-full rounded-xl border border-gray-200 py-3.5 pl-11 pr-4 text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#1a237e]/30">
                        </div>
                    </div>
                    <div class="md:col-span-4">
                        <div class="relative">
                            <i class="fas fa-layer-group absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <select name="filter"
                                class="w-full rounded-xl border border-gray-200 py-3.5 pl-11 pr-4 text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#1a237e]/30">
                                <option value="" disabled <?= $filter === '' ? 'selected' : '' ?>>Select category...</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>" <?= $filter === $cat ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit"
                            class="w-full px-6 py-3.5 bg-gradient-to-r from-[#1a237e] to-[#283593] text-white rounded-xl font-bold hover:opacity-90 transition shadow-md hover:shadow-lg">
                            Search
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <section id="featured" class="py-16 md:py-24 bg-gradient-to-b from-white to-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12 md:mb-20">
                <div class="inline-flex items-center gap-4 mb-8">
                    <div class="h-px w-12 md:w-20 bg-gradient-to-r from-transparent via-[#1a237e] to-transparent"></div>
                    <h2 class="text-3xl md:text-4xl lg:text-5xl font-bold text-gray-900">
                        Featured <span class="text-[#1a237e]">Jobs</span>
                    </h2>
                    <div class="h-px w-12 md:w-20 bg-gradient-to-r from-transparent via-[#1a237e] to-transparent"></div>
                </div>
                <p class="text-xl text-gray-700 max-w-3xl mx-auto leading-relaxed">
                    Highly engaged roles with strong employer activity and fast response times
                </p>
            </div>

            <?php if (!empty($topJobs)): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 md:gap-6">
                    <?php foreach ($topJobs as $job): ?>
                        <div class="bg-white rounded-xl overflow-hidden shadow-lg hover-lift border border-gray-200/50 group">
                            <div class="relative h-44 bg-gradient-to-br from-gray-100 to-blue-50 flex items-center justify-center overflow-hidden">
                                <div class="w-full h-full flex items-center justify-center">
                                    <i class="fas fa-building text-6xl text-[#1a237e]/20"></i>
                                </div>
                                <div class="absolute inset-0 bg-gradient-to-t from-black/30 via-transparent to-transparent"></div>
                                <span class="absolute top-3 left-3 px-3 py-1 bg-gradient-to-r from-[#ff9800] to-[#ffb74d] text-white font-bold rounded-full text-xs shadow-md flex items-center gap-1">
                                    <i class="fas fa-bolt text-xs"></i>Trending
                                </span>
                                <div class="absolute top-3 right-3 px-2 py-1 bg-white/95 backdrop-blur-sm rounded-full text-xs font-medium text-gray-900 shadow">
                                    <?= htmlspecialchars($job['category'] ?? 'General') ?>
                                </div>
                            </div>

                            <div class="p-4">
                                <div class="flex justify-between items-start mb-3">
                                    <h3 class="text-lg font-bold text-gray-900 group-hover:text-[#1a237e] transition-colors duration-300">
                                        <?= htmlspecialchars($job['title']) ?>
                                    </h3>
                                    <span class="px-2 py-0.5 bg-gradient-to-r from-indigo-50 to-blue-50 text-indigo-700 rounded-full text-xs font-medium border border-indigo-100">
                                        <?= htmlspecialchars($job['type'] ?? 'Full-time') ?>
                                    </span>
                                </div>

                                <div class="flex items-center gap-2 mb-3">
                                    <div class="p-1.5 bg-[#1a237e]/10 rounded-lg">
                                        <i class="fas fa-map-marker-alt text-xs text-[#1a237e]"></i>
                                    </div>
                                    <span class="text-gray-700 text-sm">
                                        <?= htmlspecialchars($job['company']) ?> - <?= htmlspecialchars($job['location']) ?>
                                    </span>
                                </div>

                                <p class="text-gray-600 mb-4 line-clamp-2 text-xs leading-relaxed">
                                    <?= nl2br(htmlspecialchars(substr($job['description'], 0, 120))) ?>...
                                </p>

                                <div class="flex justify-between items-center pt-4 border-t border-gray-100">
                                    <div class="flex items-center gap-2">
                                        <div class="p-1 bg-[#388e3c]/10 rounded-md">
                                            <i class="fas fa-clock text-xs text-[#388e3c]"></i>
                                        </div>
                                        <span class="text-xs text-gray-600">
                                            Posted <?= date('M d, Y', strtotime($job['created_at'])) ?>
                                        </span>
                                    </div>
                                    <div class="flex gap-2">
                                        <a href="job-detail.php?id=<?= $job['id'] ?>"
                                            class="px-3 py-1.5 bg-gradient-to-r from-[#1a237e] to-[#283593] text-white rounded-lg text-xs font-medium hover:opacity-90 transition-all shadow hover:shadow-md">
                                            View Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-12 md:py-20 bg-gradient-to-br from-white to-blue-50 rounded-2xl border-2 border-dashed border-[#1a237e]/20 shadow-lg">
                    <div class="w-32 h-32 md:w-40 md:h-40 bg-gradient-to-br from-[#1a237e]/10 to-blue-100 rounded-full flex items-center justify-center mx-auto mb-8 shadow-inner">
                        <i class="fas fa-briefcase text-6xl md:text-8xl text-[#1a237e]/30"></i>
                    </div>
                    <h3 class="text-2xl md:text-3xl font-bold text-gray-900 mb-4">
                        No Featured Jobs Yet
                    </h3>
                    <p class="text-lg md:text-xl text-gray-700 mb-8 max-w-2xl mx-auto leading-relaxed">
                        Check back soon or explore the full list of active openings below.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="#jobs"
                            class="inline-flex items-center gap-3 px-8 py-4 bg-gradient-to-r from-[#1a237e] to-[#283593] text-white rounded-xl text-lg font-medium hover:opacity-90 transition shadow-lg hover:shadow-xl hover-lift">
                            <i class="fas fa-search"></i>
                            Browse Jobs
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($isJobSeeker): ?>
        <section id="recommended" class="py-16 md:py-24 bg-gradient-to-b from-gray-50 to-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12 md:mb-20">
                    <div class="inline-flex items-center gap-4 mb-8">
                        <div class="h-px w-12 md:w-20 bg-gradient-to-r from-transparent via-[#ff9800] to-transparent"></div>
                        <h2 class="text-3xl md:text-4xl lg:text-5xl font-bold text-gray-900">
                            Recommended <span class="text-[#ff9800]">For You</span>
                        </h2>
                        <div class="h-px w-12 md:w-20 bg-gradient-to-r from-transparent via-[#ff9800] to-transparent"></div>
                    </div>
                    <p class="text-xl text-gray-700 max-w-3xl mx-auto leading-relaxed">
                        Personalized openings based on your preferences and recent activity
                    </p>
                </div>

                <?php if (!empty($recommendedJobs)): ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 md:gap-6">
                        <?php foreach ($recommendedJobs as $job): ?>
                            <div class="bg-white rounded-xl overflow-hidden shadow-lg hover-lift border border-gray-200/50 group">
                                <div class="relative h-44 bg-gradient-to-br from-gray-100 to-orange-50 flex items-center justify-center overflow-hidden">
                                    <div class="w-full h-full flex items-center justify-center">
                                        <i class="fas fa-star text-6xl text-[#ff9800]/25"></i>
                                    </div>
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/25 via-transparent to-transparent"></div>
                                    <span class="absolute top-3 left-3 px-3 py-1 bg-gradient-to-r from-[#ff9800] to-[#ffb74d] text-white font-bold rounded-full text-xs shadow-md flex items-center gap-1">
                                        <i class="fas fa-thumbs-up text-xs"></i>Recommended
                                    </span>
                                    <div class="absolute top-3 right-3 px-2 py-1 bg-white/95 backdrop-blur-sm rounded-full text-xs font-medium text-gray-900 shadow">
                                        <?= htmlspecialchars($job['category'] ?? 'General') ?>
                                    </div>
                                </div>

                                <div class="p-4">
                                    <div class="flex justify-between items-start mb-3">
                                        <h3 class="text-lg font-bold text-gray-900 group-hover:text-[#ff9800] transition-colors duration-300">
                                            <?= htmlspecialchars($job['title']) ?>
                                        </h3>
                                        <span class="px-2 py-0.5 bg-gradient-to-r from-orange-50 to-amber-50 text-orange-700 rounded-full text-xs font-medium border border-orange-100">
                                            <?= htmlspecialchars($job['type'] ?? 'Full-time') ?>
                                        </span>
                                    </div>

                                    <div class="flex items-center gap-2 mb-3">
                                        <div class="p-1.5 bg-[#ff9800]/10 rounded-lg">
                                            <i class="fas fa-building text-xs text-[#ff9800]"></i>
                                        </div>
                                        <span class="text-gray-700 text-sm">
                                            <?= htmlspecialchars($job['company']) ?>
                                        </span>
                                    </div>

                                    <div class="flex items-center gap-2 mb-3">
                                        <div class="p-1.5 bg-[#ff9800]/10 rounded-lg">
                                            <i class="fas fa-map-marker-alt text-xs text-[#ff9800]"></i>
                                        </div>
                                        <span class="text-gray-700 text-sm">
                                            <?= htmlspecialchars($job['location']) ?>
                                        </span>
                                    </div>

                                    <p class="text-gray-600 mb-4 line-clamp-2 text-xs leading-relaxed">
                                        <?= nl2br(htmlspecialchars(substr($job['description'], 0, 120))) ?>...
                                    </p>

                                    <div class="flex justify-between items-center pt-4 border-t border-gray-100">
                                        <div class="flex items-center gap-2">
                                            <div class="p-1 bg-[#388e3c]/10 rounded-md">
                                                <i class="fas fa-clock text-xs text-[#388e3c]"></i>
                                            </div>
                                            <span class="text-xs text-gray-600">
                                                Posted <?= date('M d, Y', strtotime($job['created_at'])) ?>
                                            </span>
                                        </div>
                                        <div class="flex gap-2">
                                            <a href="job-detail.php?id=<?= $job['id'] ?>"
                                                class="px-3 py-1.5 bg-gradient-to-r from-[#1a237e] to-[#283593] text-white rounded-lg text-xs font-medium hover:opacity-90 transition-all shadow hover:shadow-md">
                                                View Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12 md:py-20 bg-gradient-to-br from-white to-orange-50 rounded-2xl border-2 border-dashed border-[#ff9800]/20 shadow-lg">
                        <div class="w-32 h-32 md:w-40 md:h-40 bg-gradient-to-br from-[#ff9800]/10 to-orange-100 rounded-full flex items-center justify-center mx-auto mb-8 shadow-inner">
                            <i class="fas fa-user-check text-6xl md:text-8xl text-[#ff9800]/30"></i>
                        </div>
                        <h3 class="text-2xl md:text-3xl font-bold text-gray-900 mb-4">
                            Recommendations Are Warming Up
                        </h3>
                        <p class="text-lg md:text-xl text-gray-700 mb-8 max-w-2xl mx-auto leading-relaxed">
                            Update your profile and preferences to unlock personalized job matches.
                        </p>
                        <div class="flex flex-col sm:flex-row gap-4 justify-center">
                            <a href="<?= $basePath ?>user-account.php"
                                class="inline-flex items-center gap-3 px-8 py-4 bg-gradient-to-r from-[#ff9800] to-[#ffb74d] text-white rounded-xl text-lg font-medium hover:opacity-90 transition shadow-lg hover:shadow-xl hover-lift">
                                <i class="fas fa-user-edit"></i>
                                Update Profile
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <section id="jobs" class="py-16 md:py-24 bg-gradient-to-b from-gray-50 to-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl lg:text-5xl font-bold text-gray-900 mb-6">
                    Latest <span class="text-[#1a237e]">Openings</span>
                </h2>
                <p class="text-xl text-gray-700 max-w-3xl mx-auto leading-relaxed">
                    Browse recently posted opportunities from approved employers
                </p>
            </div>

            <?php if (empty($jobs)): ?>
                <div class="text-center py-12 md:py-20 bg-gradient-to-br from-white to-blue-50 rounded-2xl border-2 border-dashed border-[#1a237e]/20 shadow-lg">
                    <div class="w-32 h-32 md:w-40 md:h-40 bg-gradient-to-br from-[#1a237e]/10 to-blue-100 rounded-full flex items-center justify-center mx-auto mb-8 shadow-inner">
                        <i class="fas fa-search text-6xl md:text-8xl text-[#1a237e]/30"></i>
                    </div>
                    <h3 class="text-2xl md:text-3xl font-bold text-gray-900 mb-4">
                        No Jobs Found
                    </h3>
                    <p class="text-lg md:text-xl text-gray-700 mb-8 max-w-2xl mx-auto leading-relaxed">
                        Try adjusting your search or check back later for new postings.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="<?= $basePath ?>index.php"
                            class="inline-flex items-center gap-3 px-8 py-4 bg-gradient-to-r from-[#1a237e] to-[#283593] text-white rounded-xl text-lg font-medium hover:opacity-90 transition shadow-lg hover:shadow-xl hover-lift">
                            <i class="fas fa-redo"></i>
                            Reset Search
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5 md:gap-6">
                    <?php foreach ($jobs as $job): ?>
                        <div class="bg-white rounded-xl overflow-hidden shadow-lg hover-lift border border-gray-200/50 group">
                            <div class="relative h-44 bg-gradient-to-br from-gray-100 to-blue-50 flex items-center justify-center overflow-hidden">
                                <div class="w-full h-full flex items-center justify-center">
                                    <i class="fas fa-briefcase text-6xl text-[#1a237e]/20"></i>
                                </div>
                                <div class="absolute inset-0 bg-gradient-to-t from-black/30 via-transparent to-transparent"></div>
                                <div class="absolute top-3 right-3 px-2 py-1 bg-white/95 backdrop-blur-sm rounded-full text-xs font-medium text-gray-900 shadow">
                                    <?= htmlspecialchars($job['category'] ?? 'General') ?>
                                </div>
                            </div>

                            <div class="p-4">
                                <div class="flex justify-between items-start mb-3">
                                    <h3 class="text-lg font-bold text-gray-900 group-hover:text-[#1a237e] transition-colors duration-300">
                                        <?= htmlspecialchars($job['title']) ?>
                                    </h3>
                                    <span class="px-2 py-0.5 bg-gradient-to-r from-indigo-50 to-blue-50 text-indigo-700 rounded-full text-xs font-medium border border-indigo-100">
                                        <?= htmlspecialchars($job['type'] ?? 'Full-time') ?>
                                    </span>
                                </div>

                                <div class="flex items-center gap-2 mb-3">
                                    <div class="p-1.5 bg-[#1a237e]/10 rounded-lg">
                                        <i class="fas fa-building text-xs text-[#1a237e]"></i>
                                    </div>
                                    <span class="text-gray-700 text-sm">
                                        <?= htmlspecialchars($job['company']) ?>
                                    </span>
                                </div>

                                <div class="flex items-center gap-2 mb-3">
                                    <div class="p-1.5 bg-[#1a237e]/10 rounded-lg">
                                        <i class="fas fa-map-marker-alt text-xs text-[#1a237e]"></i>
                                    </div>
                                    <span class="text-gray-700 text-sm">
                                        <?= htmlspecialchars($job['location']) ?>
                                    </span>
                                </div>

                                <p class="text-gray-600 mb-4 line-clamp-2 text-xs leading-relaxed">
                                    <?= nl2br(htmlspecialchars(substr($job['description'], 0, 120))) ?>...
                                </p>

                                <div class="flex justify-between items-center pt-4 border-t border-gray-100">
                                    <div class="flex items-center gap-2">
                                        <div class="p-1 bg-[#388e3c]/10 rounded-md">
                                            <i class="fas fa-clock text-xs text-[#388e3c]"></i>
                                        </div>
                                        <span class="text-xs text-gray-600">
                                            Posted <?= date('M d, Y', strtotime($job['created_at'])) ?>
                                        </span>
                                    </div>
                                    <div class="flex gap-2">
                                        <a href="job-detail.php?id=<?= $job['id'] ?>"
                                            class="px-3 py-1.5 bg-gradient-to-r from-[#1a237e] to-[#283593] text-white rounded-lg text-xs font-medium hover:opacity-90 transition-all shadow hover:shadow-md">
                                            View Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
    <section class="py-16 md:py-24 bg-gradient-to-b from-white to-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12 md:mb-20">
                <h2 class="text-3xl md:text-4xl lg:text-5xl font-bold text-gray-900 mb-4">
                    Our <span class="text-[#1a237e]">Hiring</span> Process
                </h2>
                <p class="text-xl text-gray-700 max-w-2xl mx-auto leading-relaxed">
                    Simple and transparent steps from discovery to application
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 md:gap-12 relative">
                <div class="hidden md:block staircase-line absolute top-24 left-1/4 right-1/4"></div>

                <div class="timeline-connector text-center p-6 md:p-8 rounded-2xl bg-white hover-lift border-2 border-transparent hover:border-[#1a237e]/20 shadow-lg hover:shadow-xl group">
                    <div class="relative inline-block mb-6">
                        <div class="w-16 h-16 bg-gradient-to-r from-[#1a237e] to-[#283593] text-white rounded-full flex items-center justify-center text-2xl font-bold mx-auto shadow-lg group-hover:scale-110 transition-transform duration-300">
                            1
                        </div>
                    </div>
                    <div class="w-20 h-20 bg-gradient-to-br from-[#1a237e]/10 to-blue-100 rounded-full flex items-center justify-center mx-auto mb-6 group-hover:scale-110 transition-transform duration-300">
                        <i class="fas fa-user-plus text-3xl md:text-4xl text-[#1a237e]"></i>
                    </div>
                    <h3 class="text-xl md:text-2xl font-bold text-gray-900 mb-4">Create Profile</h3>
                    <p class="text-gray-700 leading-relaxed">Register and build a strong profile to match with top employers</p>
                </div>

                <div class="timeline-connector text-center p-6 md:p-8 rounded-2xl bg-white hover-lift border-2 border-transparent hover:border-[#ff9800]/20 shadow-lg hover:shadow-xl group">
                    <div class="relative inline-block mb-6">
                        <div class="w-16 h-16 bg-gradient-to-r from-[#ff9800] to-[#ffb74d] text-white rounded-full flex items-center justify-center text-2xl font-bold mx-auto shadow-lg group-hover:scale-110 transition-transform duration-300">
                            2
                        </div>
                    </div>
                    <div class="w-20 h-20 bg-gradient-to-br from-[#ff9800]/10 to-orange-100 rounded-full flex items-center justify-center mx-auto mb-6 group-hover:scale-110 transition-transform duration-300">
                        <i class="fas fa-search text-3xl md:text-4xl text-[#ff9800]"></i>
                    </div>
                    <h3 class="text-xl md:text-2xl font-bold text-gray-900 mb-4">Find Your Role</h3>
                    <p class="text-gray-700 leading-relaxed">Filter by category, location, and company to discover the right fit</p>
                </div>

                <div class="text-center p-6 md:p-8 rounded-2xl bg-white hover-lift border-2 border-transparent hover:border-[#388e3c]/20 shadow-lg hover:shadow-xl group">
                    <div class="relative inline-block mb-6">
                        <div class="w-16 h-16 bg-gradient-to-r from-[#388e3c] to-[#4caf50] text-white rounded-full flex items-center justify-center text-2xl font-bold mx-auto shadow-lg group-hover:scale-110 transition-transform duration-300">
                            3
                        </div>
                    </div>
                    <div class="w-20 h-20 bg-gradient-to-br from-[#388e3c]/10 to-green-100 rounded-full flex items-center justify-center mx-auto mb-6 group-hover:scale-110 transition-transform duration-300">
                        <i class="fas fa-paper-plane text-3xl md:text-4xl text-[#388e3c]"></i>
                    </div>
                    <h3 class="text-xl md:text-2xl font-bold text-gray-900 mb-4">Apply Fast</h3>
                    <p class="text-gray-700 leading-relaxed">Send your application and track progress in your dashboard</p>
                </div>
            </div>
        </div>
    </section>

    <section class="py-16 md:py-24 bg-gradient-to-b from-gray-50 to-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl lg:text-5xl font-bold text-gray-900 mb-6">
                    Why Choose <span class="text-[#1a237e]">JobHub</span>
                </h2>
                <p class="text-xl text-gray-700 max-w-3xl mx-auto leading-relaxed">
                    Trusted by job seekers and employers across Nepal
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8 justify-items-center">
                <div class="bg-white p-8 rounded-2xl shadow-lg hover-lift border border-gray-200/50 group text-center">
                    <div class="w-16 h-16 bg-gradient-to-r from-[#1a237e]/10 to-[#283593]/10 rounded-2xl flex items-center justify-center mb-6 mx-auto group-hover:scale-110 transition-transform duration-300">
                        <i class="fas fa-shield-alt text-2xl text-[#1a237e]"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-4">Verified Employers</h3>
                    <p class="text-gray-700 leading-relaxed">Companies are approved to ensure authentic job postings</p>
                </div>

                <div class="bg-white p-8 rounded-2xl shadow-lg hover-lift border border-gray-200/50 group text-center">
                    <div class="w-16 h-16 bg-gradient-to-r from-[#ff9800]/10 to-[#ffb74d]/10 rounded-2xl flex items-center justify-center mb-6 mx-auto group-hover:scale-110 transition-transform duration-300">
                        <i class="fas fa-bolt text-2xl text-[#ff9800]"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-4">Fast Applications</h3>
                    <p class="text-gray-700 leading-relaxed">Apply in minutes with a streamlined workflow</p>
                </div>

                <div class="bg-white p-8 rounded-2xl shadow-lg hover-lift border border-gray-200/50 group text-center">
                    <div class="w-16 h-16 bg-gradient-to-r from-[#388e3c]/10 to-[#4caf50]/10 rounded-2xl flex items-center justify-center mb-6 mx-auto group-hover:scale-110 transition-transform duration-300">
                        <i class="fas fa-headset text-2xl text-[#388e3c]"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-4">Local Support</h3>
                    <p class="text-gray-700 leading-relaxed">Get help from a Nepal-based team that understands your goals</p>
                </div>
            </div>
        </div>
    </section>

    <section class="py-16 md:py-24 bg-gradient-to-r from-[#1a237e] to-[#283593] relative overflow-hidden">
        <div class="absolute inset-0 opacity-10">
            <div class="absolute top-0 left-0 w-full h-full bg-[url('data:image/svg+xml,%3Csvg width=" 60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"%3E%3Cg fill="none" fill-rule="evenodd"%3E%3Cg fill="%23ffffff" fill-opacity="0.2"%3E%3Cpath d="M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z"/%3E%3C/g%3E%3C/g%3E%3C/svg%3E')]"></div>
        </div>
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-white relative z-10">
            <div class="w-24 h-24 bg-white/10 backdrop-blur-sm rounded-full flex items-center justify-center mx-auto mb-8">
                <i class="fas fa-rocket text-4xl text-white"></i>
            </div>
            <h2 class="text-3xl md:text-4xl font-bold mb-6">
                Ready to Make Your Next Move?
            </h2>
            <p class="text-xl mb-10 opacity-90 max-w-2xl mx-auto leading-relaxed">
                Join JobHub to connect with top employers and accelerate your career today.
            </p>
            <?php if ($isLoggedIn): ?>
                <div class="flex flex-col sm:flex-row gap-6 justify-center">
                    <a href="<?= $basePath ?>my-applications.php"
                        class="group relative px-8 py-4 bg-white text-[#1a237e] rounded-xl text-lg md:text-xl font-bold hover:bg-gray-50 transition shadow-xl hover:shadow-2xl hover-lift flex items-center justify-center gap-3 overflow-hidden">
                        <span class="relative z-10 flex items-center gap-3">
                            <i class="fas fa-clipboard-list"></i>
                            View Applications
                        </span>
                        <div class="absolute inset-0 bg-gradient-to-r from-[#1a237e]/0 via-[#1a237e]/5 to-[#1a237e]/0 transform -translate-x-full group-hover:translate-x-full transition-transform duration-1000"></div>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <footer class="bg-gradient-to-b from-gray-900 to-gray-950 text-white py-16">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <div class="flex justify-center mb-8">
                <div class="flex items-center gap-4 bg-gradient-to-r from-[#1a237e]/20 to-[#283593]/20 px-8 py-4 rounded-2xl border border-[#1a237e]/30 backdrop-blur-sm">
                    <div class="p-3 bg-gradient-to-r from-[#1a237e] to-[#283593] rounded-xl shadow-lg">
                        <i class="fas fa-briefcase text-2xl text-white"></i>
                    </div>
                    <div class="text-left">
                        <h3 class="text-2xl font-bold">JobHub</h3>
                        <p class="text-gray-400 text-sm">Nepal's Career Marketplace</p>
                    </div>
                </div>
            </div>

            <p class="text-lg text-gray-300 mb-8 max-w-2xl mx-auto leading-relaxed">
                JobHub connects talented professionals with verified employers across Nepal.
                Dedicated to transparent hiring and long-term career success.
            </p>

            <div class="flex flex-wrap justify-center gap-6 md:gap-8 mb-12">
                <a href="#" class="group text-gray-300 hover:text-white transition flex items-center gap-3">
                    <div class="w-10 h-10 bg-gradient-to-r from-[#1a237e]/20 to-[#283593]/20 rounded-lg flex items-center justify-center group-hover:scale-110 transition-transform">
                        <i class="fas fa-shield-alt text-sm"></i>
                    </div>
                    <span>Safety Guidelines</span>
                </a>
                <a href="#" class="group text-gray-300 hover:text-white transition flex items-center gap-3">
                    <div class="w-10 h-10 bg-gradient-to-r from-[#ff9800]/20 to-[#ffb74d]/20 rounded-lg flex items-center justify-center group-hover:scale-110 transition-transform">
                        <i class="fas fa-question-circle text-sm"></i>
                    </div>
                    <span>FAQ</span>
                </a>
                <a href="#" class="group text-gray-300 hover:text-white transition flex items-center gap-3">
                    <div class="w-10 h-10 bg-gradient-to-r from-[#388e3c]/20 to-[#4caf50]/20 rounded-lg flex items-center justify-center group-hover:scale-110 transition-transform">
                        <i class="fas fa-envelope text-sm"></i>
                    </div>
                    <span>Contact Support</span>
                </a>
                <a href="#" class="group text-gray-300 hover:text-white transition flex items-center gap-3">
                    <div class="w-10 h-10 bg-gradient-to-r from-gray-600/20 to-gray-700/20 rounded-lg flex items-center justify-center group-hover:scale-110 transition-transform">
                        <i class="fas fa-file-alt text-sm"></i>
                    </div>
                    <span>Terms and Policies</span>
                </a>
            </div>

            <div class="border-t border-gray-800 pt-8 mt-8">
                <p class="text-gray-500 text-sm">
                    &copy; <?= date('Y') ?> JobHub. All rights reserved.
                </p>
            </div>
        </div>
    </footer>

    <script>
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');

        mobileMenuButton.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
            const icon = mobileMenuButton.querySelector('i');
            if (mobileMenu.classList.contains('hidden')) {
                icon.className = 'fas fa-bars text-xl text-gray-700';
            } else {
                icon.className = 'fas fa-times text-xl text-gray-700';
            }
        });

        const scrollHint = document.getElementById('scroll-hint');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 300) {
                scrollHint.classList.add('opacity-0', 'invisible');
            } else {
                scrollHint.classList.remove('opacity-0', 'invisible');
            }
        });

        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;

                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 80,
                        behavior: 'smooth'
                    });
                }
            });
        });

        if ('ontouchstart' in window) {
            document.querySelectorAll('.hover-lift').forEach(card => {
                card.addEventListener('touchstart', function() {
                    this.classList.add('scale-95');
                });

                card.addEventListener('touchend', function() {
                    this.classList.remove('scale-95');
                });
            });
        }
    </script>
</body>

</html>
