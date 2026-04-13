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
require_once __DIR__ . '/includes/company_verification_helper.php';
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

function jobhub_job_filter_options(): array
{
    static $options = null;

    if ($options !== null) {
        return $options;
    }

    $options = [
        'categories' => require __DIR__ . '/includes/categories.php',
        'experienceLevels' => require __DIR__ . '/includes/experience_levels.php',
        'jobTypes' => require __DIR__ . '/includes/job_types.php',
    ];

    return $options;
}

function jobhub_collect_job_filters(?array $source = null): array
{
    $source = $source ?? $_GET;
    $options = jobhub_job_filter_options();

    $keyword = trim((string)($source['q'] ?? ''));
    $location = trim((string)($source['location'] ?? ''));
    $salary = trim((string)($source['salary'] ?? ''));
    $jobTypeInput = trim((string)($source['job_type'] ?? ''));
    $experienceInput = trim((string)($source['experience'] ?? ''));
    $categoryInput = trim((string)($source['filter'] ?? ($source['category'] ?? '')));

    $selectedCategory = in_array($categoryInput, $options['categories'], true) ? $categoryInput : '';
    $selectedExperience = in_array($experienceInput, $options['experienceLevels'], true) ? $experienceInput : '';
    $selectedJobType = in_array($jobTypeInput, $options['jobTypes'], true) ? $jobTypeInput : '';
    $activeLocation = $location !== '' ? $location : '';
    $activeSalary = $salary !== '' ? $salary : '';

    return [
        'q' => $keyword,
        'keyword' => $keyword,
        'filter' => $selectedCategory,
        'category' => $selectedCategory,
        'location' => $activeLocation,
        'salary' => $activeSalary,
        'job_type' => $selectedJobType,
        'experience' => $selectedExperience,
        'selectedCategory' => $selectedCategory,
        'selectedExperience' => $selectedExperience,
        'selectedJobType' => $selectedJobType,
        'activeLocation' => $activeLocation,
        'activeSalary' => $activeSalary,
        'isFilterActive' => (
            $keyword !== '' ||
            $selectedCategory !== '' ||
            $selectedExperience !== '' ||
            $activeLocation !== '' ||
            $activeSalary !== '' ||
            $selectedJobType !== ''
        ),
        'categories' => $options['categories'],
        'experienceLevels' => $options['experienceLevels'],
        'jobTypes' => $options['jobTypes'],
    ];
}

function jobhub_log_job_search(mysqli $conn, ?int $userId, array $filters): void
{
    if ($userId === null || empty($filters['isFilterActive'])) {
        return;
    }

    $hasSearchLogsTable = $conn->query("SHOW TABLES LIKE 'job_search_logs'");
    if (!$hasSearchLogsTable || $hasSearchLogsTable->num_rows === 0) {
        if ($hasSearchLogsTable) {
            $hasSearchLogsTable->close();
        }
        return;
    }

    $hasJobTypeColumn = false;
    $logJobTypeColumn = $conn->query("SHOW COLUMNS FROM job_search_logs LIKE 'job_type'");
    if ($logJobTypeColumn) {
        $hasJobTypeColumn = $logJobTypeColumn->num_rows > 0;
        $logJobTypeColumn->close();
    }

    $logColumns = ['user_id', 'keyword', 'category', 'location'];
    $logValues = ['?', '?', '?', '?'];
    $logTypes = 'isss';
    $logParams = [
        $userId,
        ($filters['keyword'] ?? '') !== '' ? $filters['keyword'] : null,
        ($filters['selectedCategory'] ?? '') !== '' ? $filters['selectedCategory'] : null,
        ($filters['activeLocation'] ?? '') !== '' ? $filters['activeLocation'] : null,
    ];

    if ($hasJobTypeColumn) {
        $logColumns[] = 'job_type';
        $logValues[] = '?';
        $logTypes .= 's';
        $logParams[] = ($filters['selectedJobType'] ?? '') !== '' ? $filters['selectedJobType'] : null;
    }

    $logColumns[] = 'created_at';
    $logValues[] = 'NOW()';

    $logStmt = $conn->prepare("
        INSERT INTO job_search_logs (" . implode(', ', $logColumns) . ")
        VALUES (" . implode(', ', $logValues) . ")
    ");

    if ($logStmt) {
        $logStmt->bind_param($logTypes, ...$logParams);
        $logStmt->execute();
        $logStmt->close();
    }

    $hasSearchLogsTable->close();
}

function jobhub_popularity_sources(mysqli $conn): array
{
    static $cache = [];

    $cacheKey = (string) $conn->thread_id;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $joinClauses = [];
    $applicationExpression = jobhub_column_exists($conn, 'jobs', 'application_count')
        ? 'COALESCE(j.application_count, 0)'
        : '0';
    $viewExpression = '0';

    if (jobhub_table_exists($conn, 'applications') && jobhub_column_exists($conn, 'applications', 'job_id')) {
        $joinClauses[] = "
        LEFT JOIN (
            SELECT a.job_id, COUNT(*) AS application_count
            FROM applications a
            GROUP BY a.job_id
        ) AS application_stats ON application_stats.job_id = j.id";
        $applicationExpression = "COALESCE(application_stats.application_count, {$applicationExpression})";
    }

    if (jobhub_column_exists($conn, 'jobs', 'view_count')) {
        $viewExpression = 'COALESCE(j.view_count, 0)';
    } elseif (jobhub_column_exists($conn, 'jobs', 'views')) {
        $viewExpression = 'COALESCE(j.views, 0)';
    } elseif (jobhub_table_exists($conn, 'job_view_logs') && jobhub_column_exists($conn, 'job_view_logs', 'job_id')) {
        $joinClauses[] = "
        LEFT JOIN (
            SELECT v.job_id, COUNT(*) AS view_count
            FROM job_view_logs v
            GROUP BY v.job_id
        ) AS view_stats ON view_stats.job_id = j.id";
        $viewExpression = 'COALESCE(view_stats.view_count, 0)';
    } elseif (jobhub_table_exists($conn, 'job_views') && jobhub_column_exists($conn, 'job_views', 'job_id')) {
        $joinClauses[] = "
        LEFT JOIN (
            SELECT v.job_id, COUNT(*) AS view_count
            FROM job_views v
            GROUP BY v.job_id
        ) AS view_stats ON view_stats.job_id = j.id";
        $viewExpression = 'COALESCE(view_stats.view_count, 0)';
    }

    $cache[$cacheKey] = [
        'joins' => $joinClauses,
        'application_expression' => $applicationExpression,
        'view_expression' => $viewExpression,
    ];

    return $cache[$cacheKey];
}

function jobhub_browse_jobs_query_parts(array $filters = []): array
{
    $normalizedFilters = jobhub_collect_job_filters($filters);

    $jobWhereClauses = [
        "(j.company_id IS NULL OR " . jobhub_company_public_job_clause('c') . ")",
        "j.is_approved = 1",
        "j.status = 'active'",
    ];
    $jobBindTypes = '';
    $jobBindParams = [];

    if ($normalizedFilters['keyword'] !== '') {
        $keywordLike = '%' . $normalizedFilters['keyword'] . '%';
        $jobWhereClauses[] = "(j.title LIKE ? OR j.description LIKE ? OR j.company LIKE ? OR j.category LIKE ? OR j.location LIKE ? OR j.type LIKE ? OR j.experience_level LIKE ? OR j.skills_required LIKE ?)";
        $jobBindTypes .= 'ssssssss';
        array_push(
            $jobBindParams,
            $keywordLike,
            $keywordLike,
            $keywordLike,
            $keywordLike,
            $keywordLike,
            $keywordLike,
            $keywordLike,
            $keywordLike
        );
    }

    if ($normalizedFilters['selectedCategory'] !== '') {
        $jobWhereClauses[] = "j.category = ?";
        $jobBindTypes .= 's';
        $jobBindParams[] = $normalizedFilters['selectedCategory'];
    }

    if ($normalizedFilters['selectedExperience'] !== '') {
        $jobWhereClauses[] = "j.experience_level = ?";
        $jobBindTypes .= 's';
        $jobBindParams[] = $normalizedFilters['selectedExperience'];
    }

    if ($normalizedFilters['activeLocation'] !== '') {
        $jobWhereClauses[] = "j.location LIKE ?";
        $jobBindTypes .= 's';
        $jobBindParams[] = '%' . $normalizedFilters['activeLocation'] . '%';
    }

    if ($normalizedFilters['activeSalary'] !== '') {
        $jobWhereClauses[] = "j.salary LIKE ?";
        $jobBindTypes .= 's';
        $jobBindParams[] = '%' . $normalizedFilters['activeSalary'] . '%';
    }

    if ($normalizedFilters['selectedJobType'] !== '') {
        $jobWhereClauses[] = "j.type = ?";
        $jobBindTypes .= 's';
        $jobBindParams[] = $normalizedFilters['selectedJobType'];
    }

    return [
        'filters' => $normalizedFilters,
        'where_clauses' => $jobWhereClauses,
        'bind_types' => $jobBindTypes,
        'bind_params' => $jobBindParams,
    ];
}

function jobhub_count_browse_jobs(array $filters = []): int
{
    $queryParts = jobhub_browse_jobs_query_parts($filters);
    $countSql = "SELECT COUNT(*)
        FROM jobs j
        LEFT JOIN companies c ON j.company_id = c.id
        WHERE " . implode(' AND ', $queryParts['where_clauses']);

    return (int) db_query_value($countSql, $queryParts['bind_types'], $queryParts['bind_params'], 0);
}

function jobhub_fetch_browse_jobs(array $filters = [], ?int $limit = null, string $sort = 'latest', int $offset = 0): array
{
    global $conn;

    $queryParts = jobhub_browse_jobs_query_parts($filters);
    $popularitySources = jobhub_popularity_sources($conn);
    $jobBindTypes = $queryParts['bind_types'];
    $jobBindParams = $queryParts['bind_params'];

    $jobsSql = "SELECT j.*,
            {$popularitySources['application_expression']} AS application_count,
            {$popularitySources['view_expression']} AS view_count
        FROM jobs j
        LEFT JOIN companies c ON j.company_id = c.id";

    if (!empty($popularitySources['joins'])) {
        $jobsSql .= implode('', $popularitySources['joins']);
    }

    $jobsSql .= "
        WHERE " . implode(' AND ', $queryParts['where_clauses']);

    if ($sort === 'popular') {
        $jobsSql .= " ORDER BY application_count DESC, view_count DESC, j.created_at DESC, j.id DESC";
    } elseif ($sort === 'oldest') {
        $jobsSql .= " ORDER BY j.created_at ASC, j.id ASC";
    } else {
        $jobsSql .= " ORDER BY j.created_at DESC, j.id DESC";
    }

    if ($limit !== null && $limit > 0) {
        $jobsSql .= " LIMIT ? OFFSET ?";
        $jobBindTypes .= 'ii';
        $jobBindParams[] = (int)$limit;
        $jobBindParams[] = max(0, $offset);
    }

    return db_query_all($jobsSql, $jobBindTypes, $jobBindParams);
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
