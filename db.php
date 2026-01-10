<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "JobHub";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('job_expiration_timestamp')) {
    function job_expiration_timestamp($createdAt, $duration)
    {
        $duration = trim((string) $duration);
        if ($duration === '') {
            return null;
        }
        $durationLower = strtolower($duration);
        if ($durationLower === 'ongoing') {
            return null;
        }
        $createdTs = strtotime($createdAt);
        if ($createdTs === false) {
            return null;
        }
        if (preg_match('/^(\d+)\s*(day|days|week|weeks|month|months|year|years)$/i', $duration, $matches)) {
            $value = (int) $matches[1];
            $unit = $matches[2];
            $expires = strtotime("+$value $unit", $createdTs);
            return $expires === false ? null : $expires;
        }
        $expires = strtotime("+$duration", $createdTs);
        return $expires === false ? null : $expires;
    }
}

if (!function_exists('is_job_expired')) {
    function is_job_expired(array $job)
    {
        if (!isset($job['created_at'])) {
            return false;
        }
        $expiresAt = job_expiration_timestamp($job['created_at'], $job['application_duration'] ?? '');
        return $expiresAt !== null && time() > $expiresAt;
    }
}

if (!function_exists('is_job_closed')) {
    function is_job_closed(array $job)
    {
        if (!isset($job['status'])) {
            return false;
        }
        return strtolower((string) $job['status']) === 'closed';
    }
}

if (!function_exists('db_query_all')) {
    function db_query_all($sql, $types = '', array $params = [])
    {
        global $conn;
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
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
}

if (!function_exists('db_query_value')) {
    function db_query_value($sql, $types = '', array $params = [], $default = 0)
    {
        global $conn;
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return $default;
        }
        if ($types !== '' && !empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        if (!$stmt->execute()) {
            $stmt->close();
            return $default;
        }
        $result = $stmt->get_result();
        if (!$result) {
            $stmt->close();
            return $default;
        }
        $row = $result->fetch_row();
        $stmt->close();
        if (!$row || !array_key_exists(0, $row)) {
            return $default;
        }
        return $row[0] === null ? $default : $row[0];
    }
}

if (!function_exists('get_jobs_deadline_column')) {
    function get_jobs_deadline_column()
    {
        foreach (['application_deadline', 'apply_before', 'deadline'] as $column) {
            if (db_query_value("SHOW COLUMNS FROM jobs LIKE '$column'", '', [], '') !== '') {
                return $column;
            }
        }
        return '';
    }
}

if (!function_exists('incrementJobView')) {
    function incrementJobView($conn, $jobId)
    {
        $jobId = (int) $jobId;
        if ($jobId <= 0) {
            return false;
        }
        $sessionKey = 'viewed_job_' . $jobId;
        if (isset($_SESSION[$sessionKey])) {
            return false;
        }
        $stmt = $conn->prepare("UPDATE jobs SET views = views + 1 WHERE id = ?");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param("i", $jobId);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok) {
            $_SESSION[$sessionKey] = true;
        }
        return $ok;
    }
}

if (!function_exists('getPopularJobs')) {
    function getPopularJobs($conn, $limit = 20, $category = null)
    {
        $deadlineColumn = get_jobs_deadline_column();
        $hasStatusColumn = db_query_value("SHOW COLUMNS FROM jobs LIKE 'status'", '', [], '') !== '';

        $sql = "SELECT j.*,
                       COALESCE(app.app_count, 0) AS applications_count,
                       COALESCE(bm.bm_count, 0) AS bookmarks_count,
                       COALESCE(j.views, 0) AS views_count,
                       (COALESCE(app.app_count, 0) * 5
                        + COALESCE(bm.bm_count, 0) * 3
                        + COALESCE(j.views, 0) * 1) AS popularity_score
                FROM jobs j
                LEFT JOIN companies c ON c.id = j.company_id
                LEFT JOIN (
                    SELECT job_id, COUNT(*) AS app_count
                    FROM applications
                    GROUP BY job_id
                ) app ON app.job_id = j.id
                LEFT JOIN (
                    SELECT job_id, COUNT(*) AS bm_count
                    FROM bookmarks
                    GROUP BY job_id
                ) bm ON bm.job_id = j.id
                WHERE (j.company_id IS NULL OR c.is_approved = 1)";

        if ($hasStatusColumn) {
            $sql .= " AND j.status = 'active'";
        }
        if ($deadlineColumn !== '') {
            $sql .= " AND (j.$deadlineColumn IS NULL OR j.$deadlineColumn >= ?)";
        }
        if ($category !== null && $category !== '') {
            $sql .= " AND j.category = ?";
        }

        $sql .= " ORDER BY popularity_score DESC, j.created_at DESC LIMIT ?";

        $types = '';
        $params = [];
        if ($deadlineColumn !== '') {
            $types .= 's';
            $params[] = date('Y-m-d');
        }
        if ($category !== null && $category !== '') {
            $types .= 's';
            $params[] = $category;
        }
        $types .= 'i';
        $params[] = (int) $limit;

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            $stmt->close();
            return [];
        }
        $result = $stmt->get_result();
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('getRecommendedJobs')) {
    function getRecommendedJobs($conn, $userId)
    {
        $preferredCategory = '';
        $stmt = $conn->prepare("SELECT preferred_category FROM users WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $row = $result ? $result->fetch_assoc() : null;
                $preferredCategory = isset($row['preferred_category']) ? trim((string) $row['preferred_category']) : '';
            }
            $stmt->close();
        }

        if ($preferredCategory !== '') {
            return getPopularJobs($conn, 10, $preferredCategory);
        }
        return getPopularJobs($conn, 10, null);
    }
}

?>
