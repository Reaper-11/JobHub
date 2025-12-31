<?php
require '../db.php';
require '../includes/flash.php';
if (!isset($_SESSION['company_id'])) {
    header("Location: company-login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: company-dashboard.php");
    exit;
}

$cid = (int) $_SESSION['company_id'];
$jobId = isset($_POST['job_id']) ? (int) $_POST['job_id'] : 0;

if ($jobId <= 0) {
    set_flash('jobs', 'Invalid job request.', 'alert-error');
    header("Location: company-dashboard.php");
    exit;
}

$stmt = $conn->prepare("SELECT company_id FROM jobs WHERE id = ?");
$stmt->bind_param("i", $jobId);
$stmt->execute();
$stmt->bind_result($jobCompanyId);
$found = $stmt->fetch();
$stmt->close();

if (!$found) {
    set_flash('jobs', 'Job not found or already deleted.', 'alert-error');
    header("Location: company-dashboard.php");
    exit;
}

if ((int) $jobCompanyId !== $cid) {
    set_flash('jobs', 'You do not have permission to delete this job.', 'alert-error');
    header("Location: company-dashboard.php");
    exit;
}

$stmt = $conn->prepare("DELETE FROM jobs WHERE id = ?");
$stmt->bind_param("i", $jobId);
if ($stmt->execute()) {
    set_flash('jobs', 'Job deleted successfully.', 'alert-success');
} else {
    set_flash('jobs', 'Unable to delete job. Please try again.', 'alert-error');
}
$stmt->close();

header("Location: company-dashboard.php?deleted=1");
exit;
