<?php
require '../db.php';
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit;
}
$q = trim($_GET['q'] ?? '');
$companyFilter = trim($_GET['company'] ?? '');
$locationFilter = trim($_GET['location'] ?? '');

$companyRows = [];
$companyLookup = [];
$companiesRes = $conn->query("SELECT id, name FROM companies WHERE is_approved = 1 ORDER BY name ASC");
if ($companiesRes) {
    while ($row = $companiesRes->fetch_assoc()) {
        $companyRows[] = $row;
        $companyLookup[(int) $row['id']] = $row['name'];
    }
}

$locationRows = [];
$locationsRes = $conn->query("SELECT DISTINCT location FROM jobs WHERE location IS NOT NULL AND location <> '' ORDER BY location ASC");
if ($locationsRes) {
    while ($row = $locationsRes->fetch_assoc()) {
        $locationRows[] = $row['location'];
    }
}

$sql = "SELECT * FROM jobs";
$conditions = [];
$params = [];
$types = "";

if ($q !== '') {
    $conditions[] = "title LIKE ?";
    $params[] = "%" . $q . "%";
    $types .= "s";
}

if ($companyFilter !== '' && $companyFilter !== 'all') {
    $companyId = (int) $companyFilter;
    if ($companyId > 0) {
        $companyName = $companyLookup[$companyId] ?? '';
        $companyClause = "company_id = ?";
        $params[] = $companyId;
        $types .= "i";
        if ($companyName !== '') {
            $companyClause .= " OR company = ?";
            $params[] = $companyName;
            $types .= "s";
        }
        $conditions[] = "(" . $companyClause . ")";
    }
}

if ($locationFilter !== '' && $locationFilter !== 'all') {
    $conditions[] = "location = ?";
    $params[] = $locationFilter;
    $types .= "s";
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}
$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
if ($stmt && $types !== '') {
    $stmt->bind_param($types, ...$params);
}
if ($stmt) {
    $stmt->execute();
    $jobs = $stmt->get_result();
    $stmt->close();
} else {
    $jobs = $conn->query("SELECT * FROM jobs ORDER BY created_at DESC");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Details - JobHub</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../custom.css?v=<?php echo filemtime(__DIR__ . '/../custom.css'); ?>">
</head>
<body>
<main class="container py-4">
    <h1 class="mb-2">Job Details</h1>
    <p><a class="link-primary text-decoration-none" href="admin-dashboard.php">&laquo; Back to Dashboard</a></p>

    <form class="card shadow-sm mb-4" method="get" action="admin-jobs.php">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-lg-4">
                    <label class="form-label" for="filter-q">Search</label>
                    <input type="text" class="form-control" id="filter-q" name="q" placeholder="Search job title..." value="<?php echo htmlspecialchars($q); ?>">
                </div>
                <div class="col-12 col-lg-4">
                    <label class="form-label" for="filter-company">Company</label>
                    <select id="filter-company" name="company" class="form-select">
                        <option value="all">All Companies</option>
                        <?php foreach ($companyRows as $company): ?>
                            <?php $companyId = (int) $company['id']; ?>
                            <option value="<?php echo $companyId; ?>" <?php echo ($companyFilter !== '' && (int) $companyFilter === $companyId) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($company['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-lg-4">
                    <label class="form-label" for="filter-location">Location</label>
                    <select id="filter-location" name="location" class="form-select">
                        <option value="all">All Locations</option>
                        <?php foreach ($locationRows as $location): ?>
                            <option value="<?php echo htmlspecialchars($location); ?>" <?php echo $locationFilter === $location ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($location); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-3">
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <a class="btn btn-outline-secondary btn-sm" href="admin-jobs.php">Reset</a>
            </div>
        </div>
    </form>

    <h3 class="h5 mb-3">All Jobs</h3>
    <div class="table-responsive">
    <table class="table table-striped table-hover align-middle">
        <thead>
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Company</th>
                <th>Location</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($j = $jobs->fetch_assoc()): ?>
            <tr>
                <td><?php echo $j['id']; ?></td>
                <td><?php echo htmlspecialchars($j['title']); ?></td>
                <td><?php echo htmlspecialchars($j['company']); ?></td>
                <td><?php echo htmlspecialchars($j['location']); ?></td>
                <td>
                    <a class="btn btn-outline-secondary btn-sm"
                       href="job-details.php?id=<?php echo $j['id']; ?>">View Details</a>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
