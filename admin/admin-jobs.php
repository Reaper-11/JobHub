<?php
require '../db.php';
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit;
}

$msg = "";
// Create new job
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_job'])) {
    $title = trim($_POST['title']);
    $company = trim($_POST['company']);
    $location = trim($_POST['location']);
    $type = trim($_POST['type']);
    $salary = trim($_POST['salary']);
    $desc = trim($_POST['description']);

    if ($title == "" || $company == "" || $location == "" || $type == "" || $desc == "") {
        $msg = "All required fields must be filled.";
    } else {
        $stmt = $conn->prepare("INSERT INTO jobs (title, company, location, type, salary, description, is_approved) VALUES (?, ?, ?, ?, ?, ?, 1)");
        $stmt->bind_param("ssssss", $title, $company, $location, $type, $salary, $desc);
        if ($stmt->execute()) $msg = "Job created successfully.";
        else $msg = "Error creating job.";
    }
}

$jobs = $conn->query("SELECT * FROM jobs ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Jobs - JobHub</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
<main class="container">
    <h1>Manage Jobs</h1>
    <p><a href="admin-dashboard.php">&laquo; Back to Dashboard</a></p>

    <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

    <div class="form-card">
        <h3>Create New Job</h3>
        <form method="post">
            <input type="hidden" name="create_job" value="1">
            <label>Job Title</label>
            <input type="text" name="title">
            <label>Company</label>
            <input type="text" name="company">
            <label>Location</label>
            <input type="text" name="location">
            <label>Job Type</label>
            <select name="type">
                <option>Full-time</option>
                <option>Part-time</option>
                <option>Internship</option>
                <option>Remote</option>
            </select>
            <label>Salary (optional)</label>
            <input type="text" name="salary">
            <label>Description</label>
            <textarea name="description" rows="4"></textarea>
            <button type="submit">Create Job</button>
        </form>
    </div>

    <h3>All Jobs</h3>
    <table>
        <tr>
            <th>ID</th>
            <th>Title</th>
            <th>Company</th>
            <th>Location</th>
            <th>Actions</th>
        </tr>
        <?php while ($j = $jobs->fetch_assoc()): ?>
            <tr>
                <td><?php echo $j['id']; ?></td>
                <td><?php echo htmlspecialchars($j['title']); ?></td>
                <td><?php echo htmlspecialchars($j['company']); ?></td>
                <td><?php echo htmlspecialchars($j['location']); ?></td>
                <td>
                    <a class="btn btn-danger btn-small"
                       href="admin-delete.php?table=jobs&id=<?php echo $j['id']; ?>&return=admin-jobs.php"
                       onclick="return confirm('Delete this job?')">Delete</a>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>
</main>
</body>
</html>
