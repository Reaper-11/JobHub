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
$postedColumn = db_query_value("SHOW COLUMNS FROM jobs LIKE 'posted_date'", '', [], '') !== '' ? 'posted_date' : 'created_at';
$deadlineColumn = '';

foreach (['application_deadline', 'deadline'] as $column) {
    if (db_query_value("SHOW COLUMNS FROM jobs LIKE '$column'", '', [], '') !== '') {
        $deadlineColumn = $column;
        break;
    }
}

if (!function_exists('format_posted_time')) {
    function format_posted_time($dateValue)
    {
        $timestamp = strtotime((string) $dateValue);
        if ($timestamp === false) {
            return '';
        }
        $days = (int) floor((time() - $timestamp) / 86400);
        if ($days <= 0) {
            return 'Posted today';
        }
        if ($days === 1) {
            return 'Posted 1 day ago';
        }
        return "Posted {$days} days ago";
    }
}

if (!function_exists('format_salary_display')) {
    function format_salary_display($salary)
    {
        $salary = trim((string) $salary);
        if ($salary === '' || stripos($salary, 'negotiable') !== false) {
            return 'Salary: Negotiable';
        }
        $label = $salary;
        if (stripos($label, 'npr') === false) {
            $label = 'NPR ' . $label;
        }
        if (stripos($label, 'month') === false && stripos($label, 'per') === false && strpos($label, '/') === false) {
            $label .= ' / month';
        }
        return 'Salary: ' . $label;
    }
}

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
<style>
.job-description {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
}
.job-description a {
    color: #1a73e8;
    font-size: 0.9em;
    text-decoration: none;
    margin-left: 6px;
}
</style>
<h1>Let's find you a job.</h1>

<form method="get" class="form-card">
    <label>Search jobs (title, company, location)</label>
    <input type="text" name="q" value="<?php echo htmlspecialchars($keyword); ?>">
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
                <?php $postedText = format_posted_time($row[$postedColumn] ?? ''); ?>
                <?php if ($postedText !== ''): ?>
                    <p class="meta" style="color: #999; font-size: 0.85em; margin: 4px 0 0;"><?php echo htmlspecialchars($postedText); ?></p>
                <?php endif; ?>
                <?php if ($deadlineColumn !== '' && !empty($row[$deadlineColumn])): ?>
                    <?php $deadlineTs = strtotime($row[$deadlineColumn]); ?>
                    <?php if ($deadlineTs !== false): ?>
                        <p class="meta" style="color: #999; font-size: 0.85em; margin: 2px 0 0;">Apply before: <?php echo htmlspecialchars(date('M d', $deadlineTs)); ?></p>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if (array_key_exists('salary', $row)): ?>
                    <p class="meta" style="color: #777; font-size: 0.9em; margin: 6px 0 0;"><?php echo htmlspecialchars(format_salary_display($row['salary'])); ?></p>
                <?php endif; ?>
                <p class="job-description">
                    <?php echo nl2br(htmlspecialchars($row['description'])); ?>
                    <a href="job-detail.php?id=<?php echo $row['id']; ?>">Read more</a>
                </p>
                <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px;">
                    <a class="btn btn-small" href="job-detail.php?id=<?php echo $row['id']; ?>" style="background: transparent; color: #1a73e8; border: 1px solid #1a73e8;">View Details</a>
                    <a class="btn btn-small" href="job-detail.php?id=<?php echo $row['id']; ?>#apply">Apply Now</a>
                </div>
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
                <?php $postedText = format_posted_time($row[$postedColumn] ?? ''); ?>
                <?php if ($postedText !== ''): ?>
                    <p class="meta" style="color: #999; font-size: 0.85em; margin: 4px 0 0;"><?php echo htmlspecialchars($postedText); ?></p>
                <?php endif; ?>
                <?php if ($deadlineColumn !== '' && !empty($row[$deadlineColumn])): ?>
                    <?php $deadlineTs = strtotime($row[$deadlineColumn]); ?>
                    <?php if ($deadlineTs !== false): ?>
                        <p class="meta" style="color: #999; font-size: 0.85em; margin: 2px 0 0;">Apply before: <?php echo htmlspecialchars(date('M d', $deadlineTs)); ?></p>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if (array_key_exists('salary', $row)): ?>
                    <p class="meta" style="color: #777; font-size: 0.9em; margin: 6px 0 0;"><?php echo htmlspecialchars(format_salary_display($row['salary'])); ?></p>
                <?php endif; ?>
                <p class="job-description">
                    <?php echo nl2br(htmlspecialchars($row['description'])); ?>
                    <a href="job-detail.php?id=<?php echo $row['id']; ?>">Read more</a>
                </p>
                <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px;">
                    <a class="btn btn-small" href="job-detail.php?id=<?php echo $row['id']; ?>" style="background: transparent; color: #1a73e8; border: 1px solid #1a73e8;">View Details</a>
                    <a class="btn btn-small" href="job-detail.php?id=<?php echo $row['id']; ?>#apply">Apply Now</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <p>No jobs available yet.</p>
<?php endif; ?>
<?php require 'footer.php'; ?>


