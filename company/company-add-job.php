<?php
require '../db.php';
if (!isset($_SESSION['company_id'])) {
    header("Location: company-login.php");
    exit;
}
$cid = (int) $_SESSION['company_id'];
$msg = "";
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title    = trim($_POST['title']);
    $location = trim($_POST['location']);
    $type     = trim($_POST['type']);
    $category = trim($_POST['category'] ?? '');
    $salaryValue = trim($_POST['salary'] ?? '');
    $duration = trim($_POST['application_duration']);
    $desc     = trim($_POST['description']);

    $salary = '';
    if ($salaryValue !== '') {
        if (!is_numeric($salaryValue)) {
            $msg = "Salary must be numeric.";
        } else {
            $salary = 'NPR ' . $salaryValue . ' / Monthly';
        }
    }

    if ($msg === "" && ($title=="" || $location=="" || $type=="" || $category=="" || $desc=="")) {
        $msg = "All required fields must be filled.";
    } elseif ($msg === "" && !in_array($category, $jobCategories, true)) {
        $msg = "Invalid job category selected.";
    } elseif ($msg === "") {
        // company posts job immediately
        $stmt = $conn->prepare(
            "INSERT INTO jobs (company_id, title, company, location, type, category, salary, application_duration, description, is_approved)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)"
        );
        $companyName = $_SESSION['company_name'];
        $stmt->bind_param("issssssss", $cid, $title, $companyName, $location, $type, $category, $salary, $duration, $desc);
        if ($stmt->execute()) {
            $msg = "Job posted successfully.";
        } else {
            $msg = "Error posting job.";
        }
    }
}

$basePath = '../';
require '../header.php';
?>
<h1 class="mb-3">Post New Job</h1>
<div class="card shadow-sm">
    <div class="card-body">
    <?php if ($msg): ?>
        <div class="alert <?php echo strpos($msg,'successfully')!==false?'alert-success':'alert-danger'; ?>">
            <?php echo htmlspecialchars($msg); ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <div class="mb-3">
            <label class="form-label">Job Title *</label>
            <input type="text" class="form-control" name="title">
        </div>

        <div class="mb-3">
            <label class="form-label">Location *</label>
            <input type="text" class="form-control" name="location">
        </div>

        <div class="mb-3">
            <label class="form-label">Job Type *</label>
            <select name="type" class="form-select">
            <option value="Full-time">Full-time</option>
            <option value="Part-time">Part-time</option>
            <option value="Internship">Internship</option>
            <option value="Remote">Remote</option>
        </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Job Category *</label>
            <select name="category" class="form-select" required>
            <option value="">Select category</option>
            <?php $selectedCategory = $_POST['category'] ?? ''; ?>
            <option value="Administration / Management" <?php echo $selectedCategory === 'Administration / Management' ? 'selected' : ''; ?>>Administration / Management</option>
            <option value="Public Relations / Advertising" <?php echo $selectedCategory === 'Public Relations / Advertising' ? 'selected' : ''; ?>>Public Relations / Advertising</option>
            <option value="Agriculture & Livestock" <?php echo $selectedCategory === 'Agriculture & Livestock' ? 'selected' : ''; ?>>Agriculture & Livestock</option>
            <option value="Engineering / Architecture" <?php echo $selectedCategory === 'Engineering / Architecture' ? 'selected' : ''; ?>>Engineering / Architecture</option>
            <option value="Automotive / Automobiles" <?php echo $selectedCategory === 'Automotive / Automobiles' ? 'selected' : ''; ?>>Automotive / Automobiles</option>
            <option value="Communications / Broadcasting" <?php echo $selectedCategory === 'Communications / Broadcasting' ? 'selected' : ''; ?>>Communications / Broadcasting</option>
            <option value="Computer / Technology Management" <?php echo $selectedCategory === 'Computer / Technology Management' ? 'selected' : ''; ?>>Computer / Technology Management</option>
            <option value="Computer / Consulting" <?php echo $selectedCategory === 'Computer / Consulting' ? 'selected' : ''; ?>>Computer / Consulting</option>
            <option value="Computer / System Programming" <?php echo $selectedCategory === 'Computer / System Programming' ? 'selected' : ''; ?>>Computer / System Programming</option>
            <option value="Construction Services" <?php echo $selectedCategory === 'Construction Services' ? 'selected' : ''; ?>>Construction Services</option>
            <option value="Contractors" <?php echo $selectedCategory === 'Contractors' ? 'selected' : ''; ?>>Contractors</option>
            <option value="Education" <?php echo $selectedCategory === 'Education' ? 'selected' : ''; ?>>Education</option>
            <option value="Electronics / Electrical" <?php echo $selectedCategory === 'Electronics / Electrical' ? 'selected' : ''; ?>>Electronics / Electrical</option>
            <option value="Entertainment" <?php echo $selectedCategory === 'Entertainment' ? 'selected' : ''; ?>>Entertainment</option>
            <option value="Engineering" <?php echo $selectedCategory === 'Engineering' ? 'selected' : ''; ?>>Engineering</option>
            <option value="Finance / Accounting" <?php echo $selectedCategory === 'Finance / Accounting' ? 'selected' : ''; ?>>Finance / Accounting</option>
            <option value="Healthcare / Medical" <?php echo $selectedCategory === 'Healthcare / Medical' ? 'selected' : ''; ?>>Healthcare / Medical</option>
            <option value="Hospitality / Tourism" <?php echo $selectedCategory === 'Hospitality / Tourism' ? 'selected' : ''; ?>>Hospitality / Tourism</option>
            <option value="Information Technology (IT)" <?php echo $selectedCategory === 'Information Technology (IT)' ? 'selected' : ''; ?>>Information Technology (IT)</option>
            <option value="Manufacturing" <?php echo $selectedCategory === 'Manufacturing' ? 'selected' : ''; ?>>Manufacturing</option>
            <option value="Marketing / Sales" <?php echo $selectedCategory === 'Marketing / Sales' ? 'selected' : ''; ?>>Marketing / Sales</option>
            <option value="Media / Journalism" <?php echo $selectedCategory === 'Media / Journalism' ? 'selected' : ''; ?>>Media / Journalism</option>
            <option value="Retail / Wholesale" <?php echo $selectedCategory === 'Retail / Wholesale' ? 'selected' : ''; ?>>Retail / Wholesale</option>
            <option value="Security Services" <?php echo $selectedCategory === 'Security Services' ? 'selected' : ''; ?>>Security Services</option>
            <option value="Transportation / Logistics" <?php echo $selectedCategory === 'Transportation / Logistics' ? 'selected' : ''; ?>>Transportation / Logistics</option>
        </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Salary (optional)</label>
            <input type="number" class="form-control" name="salary" id="salary" placeholder="e.g. 20000">
            <div class="form-text">Enter expected monthly salary (NPR)</div>
        </div>

        <div class="mb-3">
            <label class="form-label">Application Deadline (optional)</label>
            <input type="date" class="form-control" name="application_duration">
        </div>

        <div class="mb-3">
            <label class="form-label">Description *</label>
            <textarea name="description" class="form-control" rows="4"></textarea>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-primary">Publish Job</button>
            <button type="button" class="btn btn-outline-secondary" onclick="window.location.href='company-dashboard.php'">Back to Dashboard</button>
        </div>
    </form>
    </div>
</div>
<?php require '../footer.php'; ?>
