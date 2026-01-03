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
    <title>Job Details - JobHub</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .filter-bar {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 6px 14px rgba(0, 0, 0, 0.08);
            padding: 16px;
            margin: 12px 0 18px;
        }
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            align-items: end;
        }
        .filter-actions {
            margin-top: 12px;
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        @media (max-width: 900px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<main class="container">
    <h1>Job Details</h1>
    <p><a href="admin-dashboard.php">&laquo; Back to Dashboard</a></p>

    <form class="filter-bar" method="get" action="admin-jobs.php">
        <div class="filter-grid">
            <div>
                <label for="filter-q">Search</label>
                <input type="text" id="filter-q" name="q" placeholder="Search job title..." value="<?php echo htmlspecialchars($q); ?>">
            </div>
            <div>
                <label for="filter-company">Company</label>
                <select id="filter-company" name="company">
                    <option value="all">All Companies</option>
                    <?php foreach ($companyRows as $company): ?>
                        <?php $companyId = (int) $company['id']; ?>
                        <option value="<?php echo $companyId; ?>" <?php echo ($companyFilter !== '' && (int) $companyFilter === $companyId) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($company['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filter-location">Location</label>
                <select id="filter-location" name="location">
                    <option value="all">All Locations</option>
                    <?php foreach ($locationRows as $location): ?>
                        <option value="<?php echo htmlspecialchars($location); ?>" <?php echo $locationFilter === $location ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($location); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-secondary btn-small">Filter</button>
            <a href="admin-jobs.php">Reset</a>
        </div>
    </form>

    <h3>All Jobs</h3>
    <table>
        <tr>
            <th>ID</th>
            <th>Title</th>
            <th>Company</th>
            <th>Location</th>
            <th>Details</th>
        </tr>
        <?php while ($j = $jobs->fetch_assoc()): ?>
            <tr>
                <td><?php echo $j['id']; ?></td>
                <td><?php echo htmlspecialchars($j['title']); ?></td>
                <td><?php echo htmlspecialchars($j['company']); ?></td>
                <td><?php echo htmlspecialchars($j['location']); ?></td>
                <td>
                    <a class="btn btn-secondary btn-small"
                       href="job-details.php?id=<?php echo $j['id']; ?>">View Details</a>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>
</main>
</body>
</html>
