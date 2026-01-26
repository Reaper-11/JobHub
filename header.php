<?php
if (!isset($_SESSION)) session_start();
$isLoggedIn = isset($_SESSION['user_id']);
$basePath = isset($basePath) ? $basePath : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JobHub - Job Portal</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($basePath); ?>custom.css?v=<?php echo filemtime(__DIR__ . '/custom.css'); ?>">
</head>
<body class="<?php echo isset($bodyClass) ? htmlspecialchars($bodyClass) : ''; ?>">
<header class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?php echo htmlspecialchars($basePath); ?>index.php">JobHub</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar"
            aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse justify-content-end" id="mainNavbar">
            <nav class="navbar-nav align-items-lg-center gap-1">
            <?php if (!isset($_SESSION['company_id'])): ?>
                <?php if (!empty($showJobSearch) && !empty($jobSearchOptions)): ?>
                    <form method="get" action="<?php echo htmlspecialchars($basePath); ?>index.php" class="d-flex me-lg-3 my-2 my-lg-0">
                        <select name="filter" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">Search Job</option>
                            <option value="Administration / Management" <?php echo ($filter ?? '') === 'Administration / Management' ? 'selected' : ''; ?>>Administration / Management</option>
                            <option value="Public Relations / Advertising" <?php echo ($filter ?? '') === 'Public Relations / Advertising' ? 'selected' : ''; ?>>Public Relations / Advertising</option>
                            <option value="Agriculture &amp; Livestock" <?php echo ($filter ?? '') === 'Agriculture & Livestock' ? 'selected' : ''; ?>>Agriculture &amp; Livestock</option>
                            <option value="Engineering / Architecture" <?php echo ($filter ?? '') === 'Engineering / Architecture' ? 'selected' : ''; ?>>Engineering / Architecture</option>
                            <option value="Automotive / Automobiles" <?php echo ($filter ?? '') === 'Automotive / Automobiles' ? 'selected' : ''; ?>>Automotive / Automobiles</option>
                            <option value="Communications / Broadcasting" <?php echo ($filter ?? '') === 'Communications / Broadcasting' ? 'selected' : ''; ?>>Communications / Broadcasting</option>
                            <option value="Computer / Technology Management" <?php echo ($filter ?? '') === 'Computer / Technology Management' ? 'selected' : ''; ?>>Computer / Technology Management</option>
                            <option value="Computer / Consulting" <?php echo ($filter ?? '') === 'Computer / Consulting' ? 'selected' : ''; ?>>Computer / Consulting</option>
                            <option value="Computer / System Programming" <?php echo ($filter ?? '') === 'Computer / System Programming' ? 'selected' : ''; ?>>Computer / System Programming</option>
                            <option value="Construction Services" <?php echo ($filter ?? '') === 'Construction Services' ? 'selected' : ''; ?>>Construction Services</option>
                            <option value="Contractors" <?php echo ($filter ?? '') === 'Contractors' ? 'selected' : ''; ?>>Contractors</option>
                            <option value="Education" <?php echo ($filter ?? '') === 'Education' ? 'selected' : ''; ?>>Education</option>
                            <option value="Electronics / Electrical" <?php echo ($filter ?? '') === 'Electronics / Electrical' ? 'selected' : ''; ?>>Electronics / Electrical</option>
                            <option value="Entertainment" <?php echo ($filter ?? '') === 'Entertainment' ? 'selected' : ''; ?>>Entertainment</option>
                            <option value="Engineering" <?php echo ($filter ?? '') === 'Engineering' ? 'selected' : ''; ?>>Engineering</option>
                            <option value="Finance / Accounting" <?php echo ($filter ?? '') === 'Finance / Accounting' ? 'selected' : ''; ?>>Finance / Accounting</option>
                            <option value="Healthcare / Medical" <?php echo ($filter ?? '') === 'Healthcare / Medical' ? 'selected' : ''; ?>>Healthcare / Medical</option>
                            <option value="Hospitality / Tourism" <?php echo ($filter ?? '') === 'Hospitality / Tourism' ? 'selected' : ''; ?>>Hospitality / Tourism</option>
                            <option value="Information Technology (IT)" <?php echo ($filter ?? '') === 'Information Technology (IT)' ? 'selected' : ''; ?>>Information Technology (IT)</option>
                            <option value="Manufacturing" <?php echo ($filter ?? '') === 'Manufacturing' ? 'selected' : ''; ?>>Manufacturing</option>
                            <option value="Marketing / Sales" <?php echo ($filter ?? '') === 'Marketing / Sales' ? 'selected' : ''; ?>>Marketing / Sales</option>
                            <option value="Media / Journalism" <?php echo ($filter ?? '') === 'Media / Journalism' ? 'selected' : ''; ?>>Media / Journalism</option>
                            <option value="Retail / Wholesale" <?php echo ($filter ?? '') === 'Retail / Wholesale' ? 'selected' : ''; ?>>Retail / Wholesale</option>
                            <option value="Security Services" <?php echo ($filter ?? '') === 'Security Services' ? 'selected' : ''; ?>>Security Services</option>
                            <option value="Transportation / Logistics" <?php echo ($filter ?? '') === 'Transportation / Logistics' ? 'selected' : ''; ?>>Transportation / Logistics</option>
                            <option value="" disabled>Locations</option>
                            <option value="Kathmandu" <?php echo ($filter ?? '') === 'Kathmandu' ? 'selected' : ''; ?>>Kathmandu</option>
                            <option value="Lalitpur" <?php echo ($filter ?? '') === 'Lalitpur' ? 'selected' : ''; ?>>Lalitpur</option>
                            <option value="Bhaktapur" <?php echo ($filter ?? '') === 'Bhaktapur' ? 'selected' : ''; ?>>Bhaktapur</option>
                            <option value="Pokhara" <?php echo ($filter ?? '') === 'Pokhara' ? 'selected' : ''; ?>>Pokhara</option>
                        </select>
                    </form>
                <?php endif; ?>
                <a class="nav-link text-white" href="<?php echo htmlspecialchars($basePath); ?>index.php">Home</a>
            <?php endif; ?>
            <?php if ($isLoggedIn && !isset($_SESSION['company_id'])): ?>
                <a class="nav-link text-white" href="<?php echo htmlspecialchars($basePath); ?>user-account.php">Account</a>
                <a class="nav-link text-white" href="<?php echo htmlspecialchars($basePath); ?>my-bookmarks.php">My Bookmarks</a>
                <a class="nav-link text-white" href="<?php echo htmlspecialchars($basePath); ?>my-applications.php">My Applications</a>
                <a class="nav-link text-white" href="<?php echo htmlspecialchars($basePath); ?>logout.php">Logout</a>
            <?php elseif (!$isLoggedIn && !isset($_SESSION['company_id'])): ?>
                <a class="nav-link text-white" href="<?php echo htmlspecialchars($basePath); ?>register-choice.php">Register</a>
                <a class="nav-link text-white" href="<?php echo htmlspecialchars($basePath); ?>login-choice.php">Login</a>
            <?php endif; ?>
            <?php if (isset($_SESSION['company_id'])): ?>
                <a class="nav-link text-white" href="<?php echo htmlspecialchars($basePath); ?>company/company-dashboard.php">Company Dashboard</a>
                <a class="nav-link text-white" href="<?php echo htmlspecialchars($basePath); ?>company/company-account.php">Company Account</a>
                <a class="nav-link text-white" href="<?php echo htmlspecialchars($basePath); ?>logout.php">Logout</a>
            <?php elseif (!$isLoggedIn): ?>
            <?php endif; ?>
            </nav>
        </div>
    </div>
</header>
<main class="container py-4">
