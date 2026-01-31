<?php
// admin/admin-jobs.php
require '../db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit;
}

$q             = trim($_GET['q'] ?? '');
$companyFilter = trim($_GET['company'] ?? '');
$locationFilter = trim($_GET['location'] ?? '');
$page          = max(1, (int)($_GET['page'] ?? 1));
$perPage       = 20;
$offset        = ($page - 1) * $perPage;

$conditions = [];
$params     = [];
$types      = "";

if ($q !== '') {
    $conditions[] = "(title LIKE ? OR description LIKE ? OR company LIKE ?)";
    $like = "%$q%";
    $params = array_merge($params, [$like, $like, $like]);
    $types .= 'sss';
}

if ($companyFilter !== '' && $companyFilter !== 'all') {
    $cid = (int)$companyFilter;
    $conditions[] = "company_id = ?";
    $params[] = $cid;
    $types .= 'i';
}

if ($locationFilter !== '') {
    $conditions[] = "location = ?";
    $params[] = $locationFilter;
    $types .= 's';
}

$where = empty($conditions) ? "1=1" : implode(" AND ", $conditions);

$total = db_query_value("SELECT COUNT(*) FROM jobs WHERE $where", $types, $params);

$sql = "SELECT j.*, c.name AS company_name 
        FROM jobs j 
        LEFT JOIN companies c ON j.company_id = c.id 
        WHERE $where 
        ORDER BY j.created_at DESC 
        LIMIT ? OFFSET ?";

$params[] = $perPage;
$params[] = $offset;
$types .= 'ii';

$jobs = db_query_all($sql, $types, $params);

// For dropdowns
$companies = db_query_all("SELECT id, name FROM companies WHERE is_approved = 1 ORDER BY name");
$locations = db_query_all("SELECT DISTINCT location FROM jobs WHERE location IS NOT NULL AND location != '' ORDER BY location");
?>

<?php require 'admin-header.php'; ?>

<h1 class="mb-4">Manage Jobs</h1>

<form method="get" class="card shadow-sm p-3 mb-4">
    <div class="row g-3">
        <div class="col-md-4">
            <input type="text" name="q" class="form-control" placeholder="Search title, company, description..." 
                   value="<?= htmlspecialchars($q) ?>">
        </div>
        <div class="col-md-3">
            <select name="company" class="form-select">
                <option value="all">All Companies</option>
                <?php foreach ($companies as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $companyFilter == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select name="location" class="form-select">
                <option value="">All Locations</option>
                <?php foreach ($locations as $loc): ?>
                    <option value="<?= htmlspecialchars($loc['location']) ?>" 
                            <?= $locationFilter === $loc['location'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($loc['location']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1">Filter</button>
            <a href="admin-jobs.php" class="btn btn-outline-secondary">Reset</a>
        </div>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-hover table-striped align-middle">
        <thead class="table-light">
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Company</th>
                <th>Location</th>
                <th>Status</th>
                <th>Posted</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($jobs)): ?>
            <tr><td colspan="7" class="text-center py-4">No jobs found.</td></tr>
        <?php else: ?>
            <?php foreach ($jobs as $j): ?>
                <tr>
                    <td><?= $j['id'] ?></td>
                    <td><?= htmlspecialchars($j['title']) ?></td>
                    <td>
                        <?= htmlspecialchars($j['company_name'] ?: $j['company'] ?: '—') ?>
                    </td>
                    <td><?= htmlspecialchars($j['location'] ?: '—') ?></td>
                    <td>
                        <?php
                        $status = $j['status'] ?? 'draft';
                        $badge = match(strtolower($status)) {
                            'active'  => 'bg-success',
                            'closed'  => 'bg-danger',
                            'expired' => 'bg-secondary',
                            default   => 'bg-warning'
                        };
                        ?>
                        <span class="badge <?= $badge ?>"><?= ucfirst($status) ?></span>
                    </td>
                    <td><?= date('Y-m-d', strtotime($j['created_at'])) ?></td>
                    <td>
                        <a href="job-details.php?id=<?= $j['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                        <!-- Uncomment when edit is ready -->
                        <!-- <a href="admin-edit-job.php?id=<?= $j['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a> -->
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Simple pagination (expand later with LIMIT/OFFSET logic already in place) -->
<nav aria-label="Job pagination">
    <ul class="pagination justify-content-center mt-4">
        <?php if ($page > 1): ?>
            <li class="page-item"><a class="page-link" href="?page=<?= $page-1 ?>&q=<?= urlencode($q) ?>&company=<?= $companyFilter ?>&location=<?= urlencode($locationFilter) ?>">Previous</a></li>
        <?php endif; ?>
        <li class="page-item disabled"><span class="page-link">Page <?= $page ?> of <?= max(1, ceil($total / $perPage)) ?></span></li>
        <?php if ($page * $perPage < $total): ?>
            <li class="page-item"><a class="page-link" href="?page=<?= $page+1 ?>&q=<?= urlencode($q) ?>&company=<?= $companyFilter ?>&location=<?= urlencode($locationFilter) ?>">Next</a></li>
        <?php endif; ?>
    </ul>
</nav>

<?php require '../footer.php'; ?>