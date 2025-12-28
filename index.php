<?php
require 'db.php';
$keyword = isset($_GET['q']) ? trim($_GET['q']) : '';
$jobSearchOptions = [
    "Top Jobs",
    "Hot Jobs",
    "Normal Jobs",
    "Instant Jobs",
    "Premium Jobs",
    "IT Jobs",
    "Hospitality Jobs",
    "Administration/Management Jobs",
    "Ngo/Ingo Jobs",
    "Tender Notice, EOI, Bids",
];
$showJobSearch = true;
require 'header.php';

$sql = "SELECT j.* FROM jobs j
        LEFT JOIN companies c ON c.id = j.company_id
        WHERE (j.company_id IS NULL OR c.is_approved = 1)";
if ($keyword !== '') {
    $k = "%" . $conn->real_escape_string($keyword) . "%";
    $sql .= " AND (j.title LIKE '$k' OR j.company LIKE '$k' OR j.location LIKE '$k')";
}
$sql .= " ORDER BY created_at DESC";
$result = $conn->query($sql);
$jobs = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
$popularJobs = array_slice($jobs, 0, 3);
$latestJobs = $jobs;
?>
<style>
body{background:#ddd;}
</style>
<h1>Let's find you a job.</h1>

<form method="get" class="form-card">
    <label>Search jobs (title, company, location)</label>
    <input type="text" name="q" value="<?php echo htmlspecialchars($keyword); ?>">
    <button type="submit">Search</button>
</form>

<h2>Popular Jobs</h2>
<div class="jobs-grid">
    <?php if (count($popularJobs) === 0): ?>
        <p>No jobs available yet.</p>
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
