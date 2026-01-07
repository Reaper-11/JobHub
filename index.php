<?php
require 'db.php';
$keyword = isset($_GET['q']) ? trim($_GET['q']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$jobSearchOptions = [
    "IT & Software",
    "Marketing",
    "Sales",
    "Finance",
    "Design",
    "Education",
    "Healthcare",
    "Engineering",
    "Kathmandu",
    "Lalitpur",
    "Bhaktapur",
    "Pokhara",
];
$showJobSearch = true;
if ($category !== '' && !in_array($category, $jobSearchOptions, true)) {
    $category = '';
}
require 'header.php';

$keywordLike = '';
$categoryValue = '';
$sql = "SELECT j.* FROM jobs j
        LEFT JOIN companies c ON c.id = j.company_id
        WHERE (j.company_id IS NULL OR c.is_approved = 1)";
if ($keyword !== '') {
    $keywordLike = "%" . $conn->real_escape_string($keyword) . "%";
    $sql .= " AND (j.title LIKE '$keywordLike' OR j.company LIKE '$keywordLike' OR j.location LIKE '$keywordLike')";
}
if ($category !== '') {
    $categoryValue = $conn->real_escape_string($category);
    $sql .= " AND j.category = '$categoryValue'";
}
$sql .= " ORDER BY created_at DESC";
$result = $conn->query($sql);
$jobs = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
$jobs = array_values(array_filter($jobs, function ($job) {
    return !is_job_expired($job) && !is_job_closed($job);
}));
$latestJobs = $jobs;

$popularSubtitle = 'Most applied jobs this week';
$popularJobs = [];
$hasApplications = db_query_value("SHOW TABLES LIKE 'applications'", '', [], '') !== '';
$viewsColumn = '';
$featuredColumn = '';

if (!$hasApplications) {
    foreach (['view_count', 'views', 'visits'] as $column) {
        if (db_query_value("SHOW COLUMNS FROM jobs LIKE '$column'", '', [], '') !== '') {
            $viewsColumn = $column;
            break;
        }
    }
}

if (!$hasApplications && $viewsColumn === '') {
    foreach (['is_featured', 'featured'] as $column) {
        if (db_query_value("SHOW COLUMNS FROM jobs LIKE '$column'", '', [], '') !== '') {
            $featuredColumn = $column;
            break;
        }
    }
}

if ($hasApplications) {
    $popularSql = "SELECT j.*, COUNT(a.id) AS application_count
                   FROM jobs j
                   LEFT JOIN companies c ON c.id = j.company_id
                   LEFT JOIN applications a ON a.job_id = j.id
                   WHERE (j.company_id IS NULL OR c.is_approved = 1)";
    if ($keywordLike !== '') {
        $popularSql .= " AND (j.title LIKE '$keywordLike' OR j.company LIKE '$keywordLike' OR j.location LIKE '$keywordLike')";
    }
    if ($categoryValue !== '') {
        $popularSql .= " AND j.category = '$categoryValue'";
    }
    $popularSql .= " GROUP BY j.id ORDER BY application_count DESC, j.created_at DESC LIMIT 10";
    $popularRows = db_query_all($popularSql);
    $popularJobs = array_values(array_filter($popularRows, function ($job) {
        return !is_job_expired($job) && !is_job_closed($job);
    }));
    $popularJobs = array_slice($popularJobs, 0, 3);
} elseif ($viewsColumn !== '') {
    $popularSql = "SELECT j.* FROM jobs j
                   LEFT JOIN companies c ON c.id = j.company_id
                   WHERE (j.company_id IS NULL OR c.is_approved = 1)";
    if ($keywordLike !== '') {
        $popularSql .= " AND (j.title LIKE '$keywordLike' OR j.company LIKE '$keywordLike' OR j.location LIKE '$keywordLike')";
    }
    if ($categoryValue !== '') {
        $popularSql .= " AND j.category = '$categoryValue'";
    }
    $popularSql .= " ORDER BY j.$viewsColumn DESC, j.created_at DESC LIMIT 10";
    $popularRows = db_query_all($popularSql);
    $popularJobs = array_values(array_filter($popularRows, function ($job) {
        return !is_job_expired($job) && !is_job_closed($job);
    }));
    $popularJobs = array_slice($popularJobs, 0, 3);
} elseif ($featuredColumn !== '') {
    $popularSql = "SELECT j.* FROM jobs j
                   LEFT JOIN companies c ON c.id = j.company_id
                   WHERE (j.company_id IS NULL OR c.is_approved = 1)
                   AND j.$featuredColumn = 1";
    if ($keywordLike !== '') {
        $popularSql .= " AND (j.title LIKE '$keywordLike' OR j.company LIKE '$keywordLike' OR j.location LIKE '$keywordLike')";
    }
    if ($categoryValue !== '') {
        $popularSql .= " AND j.category = '$categoryValue'";
    }
    $popularSql .= " ORDER BY j.created_at DESC LIMIT 10";
    $popularRows = db_query_all($popularSql);
    $popularJobs = array_values(array_filter($popularRows, function ($job) {
        return !is_job_expired($job) && !is_job_closed($job);
    }));
    $popularJobs = array_slice($popularJobs, 0, 3);
} else {
    $popularSubtitle = 'Featured picks';
    $popularJobs = array_slice($jobs, 0, 3);
}
?>
<h1>Let's find you a job.</h1>

<form method="get" class="form-card">
    <label>Search jobs (title, company, location)</label>
    <input type="text" name="q" value="<?php echo htmlspecialchars($keyword); ?>">
    <select name="location" id="location">
        <option value="" disabled selected>Select Location</option>
        <option value="Kathmandu">Kathmandu</option>
        <option value="Lalitpur">Lalitpur</option>
        <option value="Bhaktapur">Bhaktapur</option>
        <option value="Pokhara">Pokhara</option>
    </select>
    <button type="submit">Search</button>
</form>

<h2>Popular Jobs</h2>
<p style="color: #999; font-size: 0.9em; margin: 0 0 10px;"><?php echo htmlspecialchars($popularSubtitle); ?></p>
<div class="jobs-grid">
    <?php if (count($popularJobs) === 0): ?>
        <p>No popular jobs available right now.</p>
    <?php else: ?>
        <?php foreach ($popularJobs as $row): ?>
            <div class="card">
                <h3><?php echo htmlspecialchars($row['title']); ?></h3>
                <p class="meta">
                    <?php echo htmlspecialchars($row['company']); ?> |
                    <?php echo htmlspecialchars($row['location']); ?>
                    <span class="badge"><?php echo htmlspecialchars($row['type']); ?></span>
                </p>
                <?php if (!empty($row['salary'])): ?>
                    <p class="meta"><strong>Salary:</strong> <?php echo htmlspecialchars($row['salary']); ?></p>
                <?php endif; ?>
                <p><?php echo nl2br(htmlspecialchars(substr($row['description'], 0, 120))); ?>...</p>
                <a class="btn btn-small" href="job-detail.php?id=<?php echo $row['id']; ?>">View & Apply</a>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if (count($latestJobs) > 0): ?>
    <h2>All Latest Jobs</h2>
    <div class="jobs-grid">
        <?php foreach ($latestJobs as $row): ?>
            <div class="card">
                <h3><?php echo htmlspecialchars($row['title']); ?></h3>
                <p class="meta">
                    <?php echo htmlspecialchars($row['company']); ?> |
                    <?php echo htmlspecialchars($row['location']); ?>
                    <span class="badge"><?php echo htmlspecialchars($row['type']); ?></span>
                </p>
                <?php if (!empty($row['salary'])): ?>
                    <p class="meta"><strong>Salary:</strong> <?php echo htmlspecialchars($row['salary']); ?></p>
                <?php endif; ?>
                <p><?php echo nl2br(htmlspecialchars(substr($row['description'], 0, 120))); ?>...</p>
                <a class="btn btn-small" href="job-detail.php?id=<?php echo $row['id']; ?>">View & Apply</a>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <p>No jobs available yet.</p>
<?php endif; ?>
<?php require 'footer.php'; ?>


