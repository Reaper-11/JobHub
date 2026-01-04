<?php
require '../db.php';
if (!isset($_SESSION['company_id'])) {
    header("Location: company-login.php");
    exit;
}
$cid = (int) $_SESSION['company_id'];
$msg = "";
$jobCategories = [
    "IT & Software",
    "Marketing",
    "Sales",
    "Finance",
    "Design",
    "Education",
    "Healthcare",
    "Engineering",
    "Part-Time",
    "Internship",
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
<style>
    .help-text {
        font-size: 12px;
        opacity: 0.8;
        margin-top: 6px;
        margin-bottom: 16px;
    }
    .salary-input::-webkit-outer-spin-button,
    .salary-input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    .salary-input {
        -moz-appearance: textfield;
    }    
    .form-actions {
        display: flex;
        gap: 12px;
        margin-top: 18px;
        flex-wrap: wrap;
    }
    .form-actions .btn-secondary {
        background: #f2f4f8;
        color: #1e2a3a;
        border: 1px solid #cfd6e0;
    }
    .form-actions button {
        flex: 1 1 180px;
    }
</style>
<h1>Post New Job</h1>
<div class="form-card">
    <?php if ($msg): ?>
        <div class="alert <?php echo strpos($msg,'successfully')!==false?'alert-success':'alert-error'; ?>">
            <?php echo htmlspecialchars($msg); ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <label>Job Title *</label>
        <input type="text" name="title">

        <label>Location *</label>
        <input type="text" name="location">

        <label>Job Type *</label>
        <select name="type">
            <option value="Full-time">Full-time</option>
            <option value="Part-time">Part-time</option>
            <option value="Internship">Internship</option>
            <option value="Remote">Remote</option>
        </select>

        <label>Job Category *</label>
        <select name="category" required>
            <option value="">Select category</option>
            <?php $selectedCategory = $_POST['category'] ?? ''; ?>
            <?php foreach ($jobCategories as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat); ?>"
                    <?php echo $selectedCategory === $cat ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cat); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Salary (optional)</label>
        <input type="number" name="salary" id="salary" class="salary-input" placeholder="e.g. 20000">
        <div class="help-text">Enter expected monthly salary (NPR)</div>

        <label>Application Deadline (optional)</label>
        <input type="date" name="application_duration">

        <label>Description *</label>
        <textarea name="description" rows="4"></textarea>

        <div class="form-actions">
            <button type="submit">Publish Job</button>
            <button type="button" class="btn-secondary" onclick="window.location.href='company-dashboard.php'">Back to Dashboard</button>
        </div>
    </form>
</div>
<?php require '../footer.php'; ?>

