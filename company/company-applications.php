<?php
require '../db.php';
if (!isset($_SESSION['company_id'])) {
    header("Location: company-login.php");
    exit;
}
$cid = (int) $_SESSION['company_id'];
$msg = "";
$msgType = "alert-success";
$statusOptions = [
    'pending' => 'Pending',
    'approved' => 'Approved',
    'rejected' => 'Rejected'
];
$statusColumnExists = false;
$reasonColumnExists = false;
$colCheck = $conn->query("SHOW COLUMNS FROM applications LIKE 'status'");
if ($colCheck && $colCheck->num_rows > 0) {
    $statusColumnExists = true;
}
$reasonColCheck = $conn->query("SHOW COLUMNS FROM applications LIKE 'rejection_reason'");
if ($reasonColCheck && $reasonColCheck->num_rows > 0) {
    $reasonColumnExists = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appId = (int) ($_POST['app_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $rejectionReason = trim($_POST['rejection_reason'] ?? '');
    if (!$statusColumnExists) {
        $msg = "Status column is missing. Please run the database migration first.";
        $msgType = "alert-danger";
    } elseif (!$reasonColumnExists) {
        $msg = "Rejection reason column is missing. Please run the database migration first.";
        $msgType = "alert-danger";
    } elseif ($appId <= 0 || !isset($statusOptions[$status])) {
        $msg = "Invalid status update.";
        $msgType = "alert-danger";
    } elseif ($status === 'rejected' && $rejectionReason === '') {
        $msg = "Please provide a reason for rejection.";
        $msgType = "alert-danger";
    } else {
        if ($status !== 'rejected') {
            $rejectionReason = null;
        }
        $stmt = $conn->prepare(
            "UPDATE applications a
             JOIN jobs j ON j.id = a.job_id
             SET a.status = ?, a.rejection_reason = ?
             WHERE a.id = ? AND (a.company_id = ? OR j.company_id = ?)"
        );
        $stmt->bind_param("ssiii", $status, $rejectionReason, $appId, $cid, $cid);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $msg = "Application status updated.";
            $msgType = "alert-success";
        } else {
            $msg = "Could not update status. Please try again.";
            $msgType = "alert-danger";
        }
        $stmt->close();
    }
}

$sql = "SELECT a.*, u.name, u.email, u.cv_path, j.title
        FROM applications a
        JOIN users u ON u.id = a.user_id
        JOIN jobs j ON j.id = a.job_id
        WHERE (a.company_id = $cid OR j.company_id = $cid)
        ORDER BY a.applied_at DESC";
$res = $conn->query($sql);
$rows = [];
$approvedRows = [];
$rejectedRows = [];
$pendingRows = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
}
$basePath = '../';
require '../header.php';
?>
<h1 class="mb-2">Applications</h1>
<p><a class="link-primary text-decoration-none" href="company-dashboard.php">&laquo; Back to Dashboard</a></p>
<?php if ($msg): ?>
    <div class="alert <?php echo $msgType; ?>"><?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>
<?php
function render_company_applications_table(array $rows, array $statusOptions, $basePath)
{
    if (empty($rows)) {
        echo '<p class="text-muted">No applications to show.</p>';
        return;
    }
    ?>
    <div class="table-responsive mb-4">
    <table class="table table-striped table-hover align-middle">
        <thead>
            <tr>
                <th>ID</th>
                <th>Job</th>
                <th>User</th>
                <th>Email</th>
                <th>CV</th>
                <th>Status</th>
                <th>Applied At</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $a): ?>
            <?php $currentStatus = $a['status'] ?? 'pending'; ?>
            <tr>
                <td><?php echo $a['id']; ?></td>
                <td><?php echo htmlspecialchars($a['title']); ?></td>
                <td><?php echo htmlspecialchars($a['name']); ?></td>
                <td><?php echo htmlspecialchars($a['email']); ?></td>
                <td>
                    <?php if (!empty($a['cv_path'])): ?>
                        <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars($basePath . $a['cv_path']); ?>" target="_blank">View CV</a>
                    <?php else: ?>
                        <span class="text-muted small">No CV</span>
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($statusOptions[$currentStatus] ?? ucfirst($currentStatus)); ?></td>
                <td><?php echo htmlspecialchars($a['applied_at']); ?></td>
                <td>
                    <form method="post" class="d-grid gap-2">
                        <input type="hidden" name="app_id" value="<?php echo $a['id']; ?>">
                        <select name="status" class="form-select form-select-sm">
                            <?php foreach ($statusOptions as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo $currentStatus === $value ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <textarea name="rejection_reason" class="form-control form-control-sm" rows="2" placeholder="Reason (required if rejected)"><?php echo htmlspecialchars($a['rejection_reason'] ?? ''); ?></textarea>
                        <button type="submit" class="btn btn-sm btn-outline-secondary">Update</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php
}

foreach ($rows as $row) {
    $status = strtolower($row['status'] ?? 'pending');
    if ($status === 'approved') {
        $approvedRows[] = $row;
    } elseif ($status === 'rejected') {
        $rejectedRows[] = $row;
    } else {
        $pendingRows[] = $row;
    }
}
?>

<h2 class="h5 mt-4">Approved</h2>
<?php render_company_applications_table($approvedRows, $statusOptions, $basePath); ?>

<h2 class="h5 mt-4">Rejected</h2>
<?php render_company_applications_table($rejectedRows, $statusOptions, $basePath); ?>

<h2 class="h5 mt-4">Pending</h2>
<?php render_company_applications_table($pendingRows, $statusOptions, $basePath); ?>
<?php require '../footer.php'; ?>
