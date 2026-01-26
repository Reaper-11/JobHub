<?php
require 'db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$uid = (int) $_SESSION['user_id'];

$sql = "SELECT a.*, j.title, j.company, j.location
        FROM applications a
        JOIN jobs j ON j.id = a.job_id
        WHERE a.user_id = $uid
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

require 'header.php';
?>
<?php
function render_application_table(array $rows, $showMessage, $showReason)
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
                <th>Job Title</th>
                <th>Company</th>
                <th>Location</th>
                <th>Status</th>
                <?php if ($showMessage): ?>
                    <th>Message</th>
                <?php endif; ?>
                <?php if ($showReason): ?>
                    <th>Rejection Reason</th>
                <?php endif; ?>
                <th>Applied At</th>
                <th>Action</th>
                <th>Cancel</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <?php $currentStatus = $row['status'] ?? 'pending'; ?>
            <tr>
                <td><a href="job-detail.php?id=<?php echo $row['job_id']; ?>">
                    <?php echo htmlspecialchars($row['title']); ?></a></td>
                <td><?php echo htmlspecialchars($row['company']); ?></td>
                <td><?php echo htmlspecialchars($row['location']); ?></td>
                <td><?php echo htmlspecialchars(ucfirst($currentStatus)); ?></td>
                <?php if ($showMessage): ?>
                    <td>
                        <?php echo $currentStatus === 'approved' ? 'Check your mail for further details.' : ''; ?>
                    </td>
                <?php endif; ?>
                <?php if ($showReason): ?>
                    <td>
                        <?php
                        $reason = $row['rejection_reason'] ?? '';
                        echo ($currentStatus === 'rejected' && $reason !== '') ? htmlspecialchars($reason) : '';
                        ?>
                    </td>
                <?php endif; ?>
                <td><?php echo htmlspecialchars($row['applied_at']); ?></td>
                <td><a class="btn btn-sm btn-outline-primary" href="my-application-edit.php?id=<?php echo $row['id']; ?>">Edit</a></td>
                <td>
                    <form method="post" action="my-application-cancel.php" class="d-inline">
                        <input type="hidden" name="app_id" value="<?php echo $row['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-danger">Cancel</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php
}
?>
<h1 class="mb-3">My Applications</h1>
<?php
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
<?php render_application_table($approvedRows, true, false); ?>

<h2 class="h5 mt-4">Rejected</h2>
<?php render_application_table($rejectedRows, false, true); ?>

<h2 class="h5 mt-4">Pending</h2>
<?php render_application_table($pendingRows, false, false); ?>
<?php require 'footer.php'; ?>
