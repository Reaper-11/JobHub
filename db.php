<?php
// db.php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "JobHub";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Notification + email configuration
if (!defined('JOBHUB_APP_URL')) {
    define('JOBHUB_APP_URL', 'http://localhost/JobHub/');
}
if (!defined('JOBHUB_EMAIL_ENABLED')) {
    define('JOBHUB_EMAIL_ENABLED', true);
}
if (!defined('JOBHUB_EMAIL_FROM')) {
    define('JOBHUB_EMAIL_FROM', 'no-reply@jobhub.local');
}
if (!defined('JOBHUB_EMAIL_FROM_NAME')) {
    define('JOBHUB_EMAIL_FROM_NAME', 'JobHub');
}

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_once __DIR__ . '/includes/admin_activity_helper.php';
require_once __DIR__ . '/includes/cv_helper.php';

jobhub_auth_bootstrap($conn);

// ================== HELPER FUNCTIONS ==================

/**
 * Execute prepared statement and return all rows as assoc array
 */
function db_query_all($sql, $types = '', $params = []) {
    global $conn;
    $stmt = $conn->prepare($sql);
    if (!$stmt) die("Prepare failed: " . $conn->error);

    if ($types !== '' && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }

    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

/**
 * Execute query and return single value (first column first row)
 */
function db_query_value($sql, $types = '', $params = [], $default = null) {
    global $conn;
    $stmt = $conn->prepare($sql);
    if (!$stmt) return $default;

    if ($types !== '' && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        $stmt->close();
        return $default;
    }

    $result = $stmt->get_result();
    $row = $result ? $result->fetch_row() : null;
    $stmt->close();
    return $row ? $row[0] : $default;
}

function job_deadline_column(mysqli $conn): ?string
{
    static $cachedColumn = null;
    static $loaded = false;

    if ($loaded) {
        return $cachedColumn;
    }

    foreach (['application_deadline', 'deadline'] as $column) {
        if (activity_column_exists($conn, 'jobs', $column)) {
            $cachedColumn = $column;
            break;
        }
    }

    $loaded = true;
    return $cachedColumn;
}

function job_has_post_date_column(mysqli $conn): bool
{
    static $loaded = false;
    static $hasColumn = false;

    if ($loaded) {
        return $hasColumn;
    }

    $hasColumn = activity_column_exists($conn, 'jobs', 'post_date');
    $loaded = true;

    return $hasColumn;
}

function job_deadline_value(array $job): string
{
    foreach (['application_deadline', 'deadline'] as $column) {
        $value = trim((string)($job[$column] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

function job_reference_datetime(array $job): string
{
    foreach (['post_date', 'created_at'] as $column) {
        $value = trim((string)($job[$column] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

/**
 * Resolve the expiration timestamp for a job.
 * Deadline columns take priority over duration-based expiry.
 */
function job_expiration_timestamp($job_or_created_at, $duration = null, $deadline = null, $post_date = null)
{
    if (is_array($job_or_created_at)) {
        $job = $job_or_created_at;
        $created_at = job_reference_datetime($job);
        $duration = $job['application_duration'] ?? $duration;
        $deadline = job_deadline_value($job);
    } else {
        $created_at = trim((string)$job_or_created_at);
        if ($created_at === '') {
            $created_at = trim((string)$post_date);
        }
    }

    $deadline = trim((string)$deadline);
    if ($deadline !== '') {
        $normalizedDeadline = $deadline;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline)) {
            $normalizedDeadline .= ' 23:59:59';
        }

        $deadlineTs = strtotime($normalizedDeadline);
        if ($deadlineTs !== false) {
            return $deadlineTs;
        }
    }

    $duration = trim((string)$duration);
    if ($duration === '' || strtolower($duration) === 'ongoing') {
        return null;
    }

    $createdTs = strtotime($created_at);
    if ($createdTs === false) {
        return null;
    }

    if (preg_match('/^\d+$/', $duration)) {
        return strtotime('+' . $duration . ' days', $createdTs);
    }

    if (preg_match('/^(\d+)\s*(day|days|week|weeks|month|months|year|years)$/i', $duration, $matches)) {
        return strtotime("+{$matches[1]} {$matches[2]}", $createdTs);
    }

    $parsed = strtotime('+' . $duration, $createdTs);
    return $parsed === false ? null : $parsed;
}

function is_job_expired($job): bool
{
    if (strtolower(trim((string)($job['status'] ?? ''))) === 'expired') {
        return true;
    }

    $expires = job_expiration_timestamp($job);
    return $expires !== null && time() > $expires;
}

if (!function_exists('isJobExpired')) {
    function isJobExpired($job): bool
    {
        return is_job_expired($job);
    }
}

function is_job_closed($job): bool
{
    return strtolower(trim((string)($job['status'] ?? ''))) === 'closed';
}

function job_effective_status(array $job): string
{
    $storedStatus = strtolower(trim((string)($job['status'] ?? '')));
    $approvalValue = array_key_exists('is_approved', $job) ? (int)$job['is_approved'] : null;

    if ($storedStatus === 'expired' || is_job_expired($job)) {
        return 'expired';
    }

    if ($storedStatus === 'closed') {
        return 'closed';
    }

    if ($approvalValue === -1) {
        return 'rejected';
    }

    if ($storedStatus === 'draft') {
        return 'draft';
    }

    if ($approvalValue === 0) {
        return 'pending';
    }

    if ($storedStatus === 'active') {
        return 'active';
    }

    if ($approvalValue === 1) {
        return 'approved';
    }

    return $storedStatus !== '' ? $storedStatus : 'pending';
}

function job_status_label($job_or_status): string
{
    $status = is_array($job_or_status)
        ? job_effective_status($job_or_status)
        : strtolower(trim((string)$job_or_status));

    return match ($status) {
        'active' => 'Active',
        'approved' => 'Approved',
        'pending' => 'Pending',
        'expired' => 'Expired',
        'rejected' => 'Rejected',
        'closed' => 'Closed',
        'draft' => 'Draft',
        default => ucfirst($status !== '' ? $status : 'pending'),
    };
}

function job_status_badge_class($job_or_status): string
{
    $status = is_array($job_or_status)
        ? job_effective_status($job_or_status)
        : strtolower(trim((string)$job_or_status));

    return match ($status) {
        'active', 'approved' => 'bg-success',
        'pending' => 'bg-warning text-dark',
        'rejected' => 'bg-danger',
        'expired', 'draft' => 'bg-secondary',
        'closed' => 'bg-dark',
        default => 'bg-secondary',
    };
}

function update_expired_jobs(mysqli $conn, ?int $companyId = null, ?int $jobId = null): int
{
    $selectColumns = ['id', 'status', 'application_duration', 'created_at'];

    $deadlineColumn = job_deadline_column($conn);
    if ($deadlineColumn !== null) {
        $selectColumns[] = $deadlineColumn;
    }

    if (job_has_post_date_column($conn)) {
        $selectColumns[] = 'post_date';
    }

    $whereClauses = ["status = 'active'"];
    $bindTypes = '';
    $bindParams = [];

    if ($companyId !== null) {
        $whereClauses[] = 'company_id = ?';
        $bindTypes .= 'i';
        $bindParams[] = $companyId;
    }

    if ($jobId !== null) {
        $whereClauses[] = 'id = ?';
        $bindTypes .= 'i';
        $bindParams[] = $jobId;
    }

    $sql = "
        SELECT " . implode(', ', array_unique($selectColumns)) . "
        FROM jobs
        WHERE " . implode(' AND ', $whereClauses);

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 0;
    }

    if ($bindTypes !== '') {
        $stmt->bind_param($bindTypes, ...$bindParams);
    }

    if (!$stmt->execute()) {
        $stmt->close();
        return 0;
    }

    $result = $stmt->get_result();
    $jobs = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    if (empty($jobs)) {
        return 0;
    }

    $updateStmt = $conn->prepare("
        UPDATE jobs
        SET status = 'expired', updated_at = NOW()
        WHERE id = ? AND status = 'active'
    ");

    if (!$updateStmt) {
        return 0;
    }

    $updatedCount = 0;
    foreach ($jobs as $job) {
        if (!is_job_expired($job)) {
            continue;
        }

        $expiredJobId = (int)($job['id'] ?? 0);
        if ($expiredJobId <= 0) {
            continue;
        }

        $updateStmt->bind_param("i", $expiredJobId);
        if ($updateStmt->execute()) {
            $updatedCount += $updateStmt->affected_rows > 0 ? 1 : 0;
        }
    }

    $updateStmt->close();

    return $updatedCount;
}

if (!function_exists('updateExpiredJobs')) {
    function updateExpiredJobs(?int $companyId = null, ?int $jobId = null): int
    {
        global $conn;
        return update_expired_jobs($conn, $companyId, $jobId);
    }
}

// CSRF token generation (basic)
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

update_expired_jobs($conn);

// Close connection on shutdown (optional but good practice)
register_shutdown_function(function() use ($conn) {
    if ($conn) $conn->close();
});

require_once __DIR__ . '/includes/notifications.php';
