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
        echo '<p>No applications to show.</p>';
        return;
    }
    ?>
    <table>
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
        <?php foreach ($rows as $row): ?>
            <?php $currentStatus = $row['status'] ?? 'pending'; ?>
        <?php if (!in_array($currentStatus, ['pending', 'approved', 'rejected'], true)) { $currentStatus = 'pending'; } ?>
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
                <td><a class="btn btn-small" href="my-application-edit.php?id=<?php echo $row['id']; ?>">Edit</a></td>
                <td>
                    <form method="post" action="my-application-cancel.php" class="inline-form">
                        <input type="hidden" name="app_id" value="<?php echo $row['id']; ?>">
                        <button type="submit" class="btn btn-small btn-danger">Cancel</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
    <?php
}
?>
<h1>My Applications</h1>
<?php
foreach ($rows as $row) {
    $status = strtolower($row['status'] ?? 'pending');
    if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
        $status = 'pending';
    }
    if ($status === 'approved') {
        $approvedRows[] = $row;
    } elseif ($status === 'rejected') {
        $rejectedRows[] = $row;
    } else {
        $pendingRows[] = $row;
    }
}
?>

<h2>Approved</h2>
<?php render_application_table($approvedRows, true, false); ?>

<h2>Rejected</h2>
<?php render_application_table($rejectedRows, false, true); ?>

<h2>Pending</h2>
<?php render_application_table($pendingRows, false, false); ?>
<?php require 'footer.php'; ?>
