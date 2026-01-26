<?php
require '../db.php';
if (!isset($_SESSION['company_id'])) {
    header("Location: company-login.php");
    exit;
}
$cid = (int) $_SESSION['company_id'];
$jobId = (int) ($_GET['id'] ?? 0);
$msg = '';
$msgType = 'alert-success';
$jobCategories = [
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
];

$stmt = $conn->prepare("SELECT * FROM jobs WHERE id = ? AND company_id = ? LIMIT 1");
$stmt->bind_param("ii", $jobId, $cid);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$job) {
    header("Location: company-dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title    = trim($_POST['title'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $type     = trim($_POST['type'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $salary   = trim($_POST['salary'] ?? '');
    $duration = trim($_POST['application_duration'] ?? '');
    $desc     = trim($_POST['description'] ?? '');

    if ($title === '' || $location === '' || $type === '' || $category === '' || $desc === '') {
        $msg = 'All required fields must be filled.';
        $msgType = 'alert-danger';
    } elseif (!in_array($category, $jobCategories, true)) {
        $msg = 'Invalid job category selected.';
        $msgType = 'alert-danger';
    } else {
        $companyName = $_SESSION['company_name'];
        $update = $conn->prepare(
            "UPDATE jobs SET title = ?, company = ?, location = ?, type = ?, category = ?, salary = ?, application_duration = ?, description = ?
             WHERE id = ? AND company_id = ?"
        );
        $update->bind_param("ssssssssii", $title, $companyName, $location, $type, $category, $salary, $duration, $desc, $jobId, $cid);
        if ($update->execute()) {
            $msg = 'Job updated successfully.';
            $msgType = 'alert-success';
            $job['title'] = $title;
            $job['location'] = $location;
            $job['type'] = $type;
            $job['category'] = $category;
            $job['salary'] = $salary;
            $job['application_duration'] = $duration;
            $job['description'] = $desc;
        } else {
            $msg = 'Could not update job. Please try again.';
            $msgType = 'alert-danger';
        }
        $update->close();
    }
}

$basePath = '../';
require '../header.php';
?>
<h1>Edit Job</h1>
<p><a href="company-dashboard.php">&laquo; Back to Dashboard</a></p>
<?php if ($msg): ?>
    <div class="alert <?php echo $msgType; ?>"><?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>
<div class="form-card">
    <form method="post">
        <label>Job Title *</label>
        <input type="text" name="title" value="<?php echo htmlspecialchars($job['title']); ?>">

        <label>Location *</label>
        <input type="text" name="location" value="<?php echo htmlspecialchars($job['location']); ?>">

        <label>Job Type *</label>
        <select name="type">
            <?php
            $types = ['Full-time', 'Part-time', 'Internship', 'Remote'];
            foreach ($types as $t):
            ?>
                <option value="<?php echo $t; ?>" <?php echo $job['type'] === $t ? 'selected' : ''; ?>>
                    <?php echo $t; ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Job Category *</label>
        <select name="category" required>
            <option value="">Select category</option>
            <option value="Administration / Management" <?php echo ($job['category'] ?? '') === 'Administration / Management' ? 'selected' : ''; ?>>Administration / Management</option>
            <option value="Public Relations / Advertising" <?php echo ($job['category'] ?? '') === 'Public Relations / Advertising' ? 'selected' : ''; ?>>Public Relations / Advertising</option>
            <option value="Agriculture & Livestock" <?php echo ($job['category'] ?? '') === 'Agriculture & Livestock' ? 'selected' : ''; ?>>Agriculture & Livestock</option>
            <option value="Engineering / Architecture" <?php echo ($job['category'] ?? '') === 'Engineering / Architecture' ? 'selected' : ''; ?>>Engineering / Architecture</option>
            <option value="Automotive / Automobiles" <?php echo ($job['category'] ?? '') === 'Automotive / Automobiles' ? 'selected' : ''; ?>>Automotive / Automobiles</option>
            <option value="Communications / Broadcasting" <?php echo ($job['category'] ?? '') === 'Communications / Broadcasting' ? 'selected' : ''; ?>>Communications / Broadcasting</option>
            <option value="Computer / Technology Management" <?php echo ($job['category'] ?? '') === 'Computer / Technology Management' ? 'selected' : ''; ?>>Computer / Technology Management</option>
            <option value="Computer / Consulting" <?php echo ($job['category'] ?? '') === 'Computer / Consulting' ? 'selected' : ''; ?>>Computer / Consulting</option>
            <option value="Computer / System Programming" <?php echo ($job['category'] ?? '') === 'Computer / System Programming' ? 'selected' : ''; ?>>Computer / System Programming</option>
            <option value="Construction Services" <?php echo ($job['category'] ?? '') === 'Construction Services' ? 'selected' : ''; ?>>Construction Services</option>
            <option value="Contractors" <?php echo ($job['category'] ?? '') === 'Contractors' ? 'selected' : ''; ?>>Contractors</option>
            <option value="Education" <?php echo ($job['category'] ?? '') === 'Education' ? 'selected' : ''; ?>>Education</option>
            <option value="Electronics / Electrical" <?php echo ($job['category'] ?? '') === 'Electronics / Electrical' ? 'selected' : ''; ?>>Electronics / Electrical</option>
            <option value="Entertainment" <?php echo ($job['category'] ?? '') === 'Entertainment' ? 'selected' : ''; ?>>Entertainment</option>
            <option value="Engineering" <?php echo ($job['category'] ?? '') === 'Engineering' ? 'selected' : ''; ?>>Engineering</option>
            <option value="Finance / Accounting" <?php echo ($job['category'] ?? '') === 'Finance / Accounting' ? 'selected' : ''; ?>>Finance / Accounting</option>
            <option value="Healthcare / Medical" <?php echo ($job['category'] ?? '') === 'Healthcare / Medical' ? 'selected' : ''; ?>>Healthcare / Medical</option>
            <option value="Hospitality / Tourism" <?php echo ($job['category'] ?? '') === 'Hospitality / Tourism' ? 'selected' : ''; ?>>Hospitality / Tourism</option>
            <option value="Information Technology (IT)" <?php echo ($job['category'] ?? '') === 'Information Technology (IT)' ? 'selected' : ''; ?>>Information Technology (IT)</option>
            <option value="Manufacturing" <?php echo ($job['category'] ?? '') === 'Manufacturing' ? 'selected' : ''; ?>>Manufacturing</option>
            <option value="Marketing / Sales" <?php echo ($job['category'] ?? '') === 'Marketing / Sales' ? 'selected' : ''; ?>>Marketing / Sales</option>
            <option value="Media / Journalism" <?php echo ($job['category'] ?? '') === 'Media / Journalism' ? 'selected' : ''; ?>>Media / Journalism</option>
            <option value="Retail / Wholesale" <?php echo ($job['category'] ?? '') === 'Retail / Wholesale' ? 'selected' : ''; ?>>Retail / Wholesale</option>
            <option value="Security Services" <?php echo ($job['category'] ?? '') === 'Security Services' ? 'selected' : ''; ?>>Security Services</option>
            <option value="Transportation / Logistics" <?php echo ($job['category'] ?? '') === 'Transportation / Logistics' ? 'selected' : ''; ?>>Transportation / Logistics</option>
        </select>

        <label>Salary (optional)</label>
        <input type="text" name="salary" value="<?php echo htmlspecialchars($job['salary']); ?>">

        <label>Application Duration (optional)</label>
        <input type="text" name="application_duration" value="<?php echo htmlspecialchars($job['application_duration'] ?? ''); ?>">

        <label>Description *</label>
        <textarea name="description" rows="4"><?php echo htmlspecialchars($job['description']); ?></textarea>

        <button type="submit">Update Job</button>
    </form>
</div>
<?php require '../footer.php'; ?>


