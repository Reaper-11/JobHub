<?php
require 'db.php';
$keyword = isset($_GET['q']) ? trim($_GET['q']) : '';
$filter = isset($_GET['filter']) ? trim($_GET['filter']) : '';
$jobSearchOptions = [
    "Administration / Management",
    "Public Relations / Advertising",
    "Agriculture & Livestock",
    "Engineering / Architecture",
    "Automotive / Automobiles",
    "Communications / Broadcasting",
    "Computer / Technology Management",
    "Computer / Consulting",
    "Computer / System Programming",
    "Construction Services",
    "Contractors",
    "Education",
    "Electronics / Electrical",
    "Entertainment",
    "Engineering",
    "Finance / Accounting",
    "Healthcare / Medical",
    "Hospitality / Tourism",
    "Information Technology (IT)",
    "Manufacturing",
    "Marketing / Sales",
    "Media / Journalism",
    "Retail / Wholesale",
    "Security Services",
    "Transportation / Logistics",
    "Kathmandu",
    "Lalitpur",
    "Bhaktapur",
    "Pokhara",
];
$locationOptions = ["Kathmandu", "Lalitpur", "Bhaktapur", "Pokhara"];
$showJobSearch = true;
if ($filter !== '' && !in_array($filter, $jobSearchOptions, true)) {
    $filter = '';
}

if (isset($_SESSION['user_id']) && $keyword !== '') {
    $_SESSION['last_search_keyword'] = $keyword;
}

require 'header.php';

$deadlineColumn = get_jobs_deadline_column();
$hasStatusColumn = db_query_value("SHOW COLUMNS FROM jobs LIKE 'status'", '', [], '') !== '';
$postedColumn = db_query_value("SHOW COLUMNS FROM jobs LIKE 'posted_date'", '', [], '') !== '' ? 'posted_date' : 'created_at';
$jobsSql = "SELECT j.*,
                   COALESCE(j.application_count, 0) AS application_count
            FROM jobs j
            LEFT JOIN companies c ON c.id = j.company_id
            WHERE (j.company_id IS NULL OR c.is_approved = 1)";
$types = '';
$params = [];
if ($hasStatusColumn) {
    $jobsSql .= " AND j.status = 'active'";
}
if ($deadlineColumn !== '') {
    $jobsSql .= " AND (j.$deadlineColumn IS NULL OR j.$deadlineColumn >= ?)";
    $types .= 's';
    $params[] = date('Y-m-d');
}
if ($keyword !== '') {
    $jobsSql .= " AND (j.title LIKE ? OR j.company LIKE ? OR j.location LIKE ?)";
    $types .= 'sss';
    $keywordLike = '%' . $keyword . '%';
    $params[] = $keywordLike;
    $params[] = $keywordLike;
    $params[] = $keywordLike;
}
if ($filter !== '') {
    if (in_array($filter, $locationOptions, true)) {
        $jobsSql .= " AND j.location = ?";
    } else {
        $jobsSql .= " AND j.category = ?";
    }
    $types .= 's';
    $params[] = $filter;
}
$jobsSql .= " ORDER BY j.application_count DESC, j.created_at DESC";
$jobs = db_query_all($jobsSql, $types, $params);
$latestJobs = $jobs;
$popularSubtitle = 'Popular right now';
$popularJobs = array_slice($jobs, 0, 3);
$recommendedJobs = [];
$showRecommendations = isset($_SESSION['user_id']) && !isset($_SESSION['company_id']);
if ($showRecommendations) {
    $recentKeyword = isset($_SESSION['last_search_keyword']) ? (string) $_SESSION['last_search_keyword'] : '';
    $recommendedJobs = getRecommendedJobs($conn, (int) $_SESSION['user_id'], $recentKeyword);
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

<?php if (!empty($showRecommendations)): ?>
    <h2>Recommended Jobs</h2>
    <p style="color: #999; font-size: 0.9em; margin: 0 0 10px;">Based on your profile and recent activity</p>
    <div class="jobs-grid">
        <?php if (count($recommendedJobs) === 0): ?>
            <p style="text-align: center; color: #999; margin: 12px 0;">No recommendations yet. Update your preferences or search for jobs.</p>
        <?php else: ?>
            <?php foreach ($recommendedJobs as $row): ?>
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
<?php endif; ?>

<h2>Popular Jobs</h2>
<p style="color: #999; font-size: 0.9em; margin: 0 0 10px;"><?php echo htmlspecialchars($popularSubtitle); ?></p>
<div class="jobs-grid">
    <?php if (count($popularJobs) === 0): ?>
        <p style="text-align: center; color: #999; margin: 12px 0;">No jobs found. Try changing filters.</p>
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

<h2>All Latest Jobs</h2>
<div class="jobs-grid">
    <?php if (count($latestJobs) === 0): ?>
        <p style="text-align: center; color: #999; margin: 12px 0;">No jobs found. Try changing filters.</p>
    <?php else: ?>
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
    <?php endif; ?>
</div>
<?php require 'footer.php'; ?>
