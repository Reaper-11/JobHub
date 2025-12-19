<?php
require '../db.php';
if (!isset($_SESSION['company_id'])) {
    header("Location: company-login.php");
    exit;
}
$cid = (int) $_SESSION['company_id'];
$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title    = trim($_POST['title']);
    $location = trim($_POST['location']);
    $type     = trim($_POST['type']);
    $salary   = trim($_POST['salary']);
    $desc     = trim($_POST['description']);

    if ($title=="" || $location=="" || $type=="" || $desc=="") {
        $msg = "All required fields must be filled.";
    } else {
        // company posts job immediately
        $stmt = $conn->prepare(
            "INSERT INTO jobs (company_id, title, company, location, type, salary, description, is_approved)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1)"
        );
        $companyName = $_SESSION['company_name'];
        $stmt->bind_param("issssss", $cid, $title, $companyName, $location, $type, $salary, $desc);
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

        <label>Salary (optional)</label>
        <input type="text" name="salary">

        <label>Description *</label>
        <textarea name="description" rows="4"></textarea>

        <button type="submit">Submit Job</button>
    </form>
</div>
<?php require '../footer.php'; ?>
