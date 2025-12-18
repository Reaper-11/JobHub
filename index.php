<?php
require 'db.php';
require 'header.php';

$keyword = isset($_GET['q']) ? trim($_GET['q']) : '';

$sql = "SELECT * FROM jobs WHERE is_approved = 1";
if ($keyword !== '') {
    $k = "%" . $conn->real_escape_string($keyword) . "%";
    $sql .= " AND (title LIKE '$k' OR company LIKE '$k' OR location LIKE '$k')";
}
$sql .= " ORDER BY created_at DESC";
$result = $conn->query($sql);
?>
<h1>Latest Jobs</h1>

<form method="get" class="form-card">
    <label>Search jobs (title, company, location)</label>
    <input type="text" name="q" value="<?php echo htmlspecialchars($keyword); ?>">
    <button type="submit">Search</button>
</form>

<div class="jobs-grid">
    <?php while ($row = $result->fetch_assoc()): ?>
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
    <?php endwhile; ?>
</div>
<?php require 'footer.php'; ?>
