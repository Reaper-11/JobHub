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

if (!function_exists('db_table_exists')) {
    function db_table_exists($table)
    {
        $table = trim((string) $table);
        if ($table === '') {
            return false;
        }
        $safeTable = str_replace(['`', '"', "'"], '', $table);
        return db_query_value("SHOW TABLES LIKE '$safeTable'", '', [], '') !== '';
    }
}

if (!function_exists('db_column_exists')) {
    function db_column_exists($table, $column)
    {
        $table = trim((string) $table);
        $column = trim((string) $column);
        if ($table === '' || $column === '') {
            return false;
        }
        $safeTable = str_replace(['`', '"', "'"], '', $table);
        $safeColumn = str_replace(['`', '"', "'"], '', $column);
        return db_query_value("SHOW COLUMNS FROM $safeTable LIKE '$safeColumn'", '', [], '') !== '';
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

if (!function_exists('normalize_keywords')) {
    function normalize_keywords($text)
    {
        $text = strtolower(trim((string) $text));
        if ($text === '') {
            return [];
        }
        $parts = preg_split('/[,\s\/\|]+/', $text);
        $keywords = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '' || strlen($part) < 2) {
                continue;
            }
            $keywords[$part] = true;
        }
        return array_keys($keywords);
    }
}

if (!function_exists('get_user_preferences')) {
    function get_user_preferences($conn, $userId, $recentKeyword = '')
    {
        $preferences = [
            'preferred_category' => '',
            'preferred_skills' => '',
            'preferred_location' => '',
            'recent_keyword' => trim((string) $recentKeyword),
        ];

        if (db_table_exists('user_preferences')) {
            $stmt = $conn->prepare("SELECT preferred_category, preferred_skills, preferred_location FROM user_preferences WHERE user_id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("i", $userId);
                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    $row = $result ? $result->fetch_assoc() : null;
                    if ($row) {
                        $preferences['preferred_category'] = trim((string) ($row['preferred_category'] ?? ''));
                        $preferences['preferred_skills'] = trim((string) ($row['preferred_skills'] ?? ''));
                        $preferences['preferred_location'] = trim((string) ($row['preferred_location'] ?? ''));
                    }
                }
                $stmt->close();
            }
        }

        $needUserFallback = $preferences['preferred_category'] === ''
            && $preferences['preferred_skills'] === ''
            && $preferences['preferred_location'] === '';

        if ($needUserFallback && db_table_exists('users')) {
            $columns = [];
            if (db_column_exists('users', 'preferred_category')) {
                $columns[] = 'preferred_category';
            }
            if (db_column_exists('users', 'preferred_skills')) {
                $columns[] = 'preferred_skills';
            }
            if (db_column_exists('users', 'preferred_location')) {
                $columns[] = 'preferred_location';
            }
            if (!empty($columns)) {
                $columnList = implode(', ', $columns);
                $stmt = $conn->prepare("SELECT $columnList FROM users WHERE id = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param("i", $userId);
                    if ($stmt->execute()) {
                        $result = $stmt->get_result();
                        $row = $result ? $result->fetch_assoc() : null;
                        if ($row) {
                            foreach ($columns as $column) {
                                $preferences[$column] = trim((string) ($row[$column] ?? ''));
                            }
                        }
                    }
                    $stmt->close();
                }
            }
        }

        return $preferences;
    }
}

if (!function_exists('compute_job_score')) {
    function compute_job_score(array $job, $preferredCategory, array $keywords, $preferredLocation, $hasSkillsColumn)
    {
        $score = 0;

        $jobCategory = strtolower(trim((string) ($job['category'] ?? '')));
        if ($preferredCategory !== '' && $jobCategory === strtolower($preferredCategory)) {
            $score += 5;
        }

        $jobLocation = strtolower(trim((string) ($job['location'] ?? '')));
        if ($preferredLocation !== '' && $jobLocation === strtolower($preferredLocation)) {
            $score += 1;
        }

        if (!empty($keywords)) {
            $titleText = strtolower((string) ($job['title'] ?? ''));
            $skillsText = '';
            if ($hasSkillsColumn) {
                $skillsText = strtolower((string) ($job['skills'] ?? ''));
            } elseif (isset($job['description'])) {
                $skillsText = strtolower((string) $job['description']);
            }
            $hits = 0;
            foreach ($keywords as $keyword) {
                if ($keyword === '') {
                    continue;
                }
                if (strpos($titleText, $keyword) !== false || ($skillsText !== '' && strpos($skillsText, $keyword) !== false)) {
                    $hits++;
                }
            }
            if ($hits > 0) {
                $score += $hits * 2;
            }
        }

        return $score;
    }
}

if (!function_exists('get_fallback_jobs')) {
    function get_fallback_jobs($conn, $userId, array $excludeIds, $limit)
    {
        $deadlineColumn = get_jobs_deadline_column();
        $hasStatusColumn = db_query_value("SHOW COLUMNS FROM jobs LIKE 'status'", '', [], '') !== '';

        $sql = "SELECT j.*,
                       COALESCE(j.application_count, 0) AS application_count
                FROM jobs j
                LEFT JOIN companies c ON c.id = j.company_id
                WHERE (j.company_id IS NULL OR c.is_approved = 1)
                  AND j.id NOT IN (SELECT job_id FROM applications WHERE user_id = ?)";

        $types = 'i';
        $params = [$userId];

        if ($hasStatusColumn) {
            $sql .= " AND j.status = 'active'";
        }
        if ($deadlineColumn !== '') {
            $sql .= " AND (j.$deadlineColumn IS NULL OR j.$deadlineColumn >= ?)";
            $types .= 's';
            $params[] = date('Y-m-d');
        }
        if (!empty($excludeIds)) {
            $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
            $sql .= " AND j.id NOT IN ($placeholders)";
            $types .= str_repeat('i', count($excludeIds));
            foreach ($excludeIds as $jobId) {
                $params[] = (int) $jobId;
            }
        }

        $sql .= " ORDER BY j.application_count DESC, j.created_at DESC LIMIT ?";
        $types .= 'i';
        $params[] = (int) $limit;

        $rows = db_query_all($sql, $types, $params);
        $filtered = [];
        foreach ($rows as $row) {
            if (is_job_expired($row) || is_job_closed($row)) {
                continue;
            }
            $filtered[] = $row;
        }
        return $filtered;
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
                       COALESCE(j.application_count, 0) AS application_count
                FROM jobs j
                LEFT JOIN companies c ON c.id = j.company_id
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

        $sql .= " ORDER BY j.application_count DESC, j.created_at DESC LIMIT ?";

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
    function getRecommendedJobs($conn, $userId, $recentKeyword = '')
    {
        $preferences = get_user_preferences($conn, $userId, $recentKeyword);
        $preferredCategory = $preferences['preferred_category'];
        $preferredSkills = $preferences['preferred_skills'];
        $preferredLocation = $preferences['preferred_location'];
        $recentKeyword = $preferences['recent_keyword'];

        $keywords = normalize_keywords($preferredSkills);
        if ($recentKeyword !== '') {
            $keywords = array_values(array_unique(array_merge($keywords, normalize_keywords($recentKeyword))));
        }

        $deadlineColumn = get_jobs_deadline_column();
        $hasStatusColumn = db_query_value("SHOW COLUMNS FROM jobs LIKE 'status'", '', [], '') !== '';
        $hasSkillsColumn = db_column_exists('jobs', 'skills');

        $sql = "SELECT j.*,
                       COALESCE(j.application_count, 0) AS application_count
                FROM jobs j
                LEFT JOIN companies c ON c.id = j.company_id
                WHERE (j.company_id IS NULL OR c.is_approved = 1)
                  AND j.id NOT IN (SELECT job_id FROM applications WHERE user_id = ?)";

        $types = 'i';
        $params = [$userId];

        if ($hasStatusColumn) {
            $sql .= " AND j.status = 'active'";
        }
        if ($deadlineColumn !== '') {
            $sql .= " AND (j.$deadlineColumn IS NULL OR j.$deadlineColumn >= ?)";
            $types .= 's';
            $params[] = date('Y-m-d');
        }

        $relevanceParts = [];
        if ($preferredCategory !== '') {
            $relevanceParts[] = "j.category = ?";
            $types .= 's';
            $params[] = $preferredCategory;
        }
        if ($preferredLocation !== '') {
            $relevanceParts[] = "j.location = ?";
            $types .= 's';
            $params[] = $preferredLocation;
        }
        if (!empty($keywords)) {
            foreach ($keywords as $keyword) {
                $like = '%' . $keyword . '%';
                $relevanceParts[] = "j.title LIKE ?";
                $types .= 's';
                $params[] = $like;
                if ($hasSkillsColumn) {
                    $relevanceParts[] = "j.skills LIKE ?";
                    $types .= 's';
                    $params[] = $like;
                } else {
                    $relevanceParts[] = "j.description LIKE ?";
                    $types .= 's';
                    $params[] = $like;
                }
            }
        }
        if (!empty($relevanceParts)) {
            $sql .= " AND (" . implode(' OR ', $relevanceParts) . ")";
        }

        $sql .= " ORDER BY j.created_at DESC LIMIT 80";
        $candidateJobs = db_query_all($sql, $types, $params);

        $scored = [];
        foreach ($candidateJobs as $job) {
            if (is_job_expired($job) || is_job_closed($job)) {
                continue;
            }
            $score = compute_job_score($job, $preferredCategory, $keywords, $preferredLocation, $hasSkillsColumn);
            if ($score <= 0) {
                continue;
            }
            $job['_score'] = $score;
            $scored[] = $job;
        }

        usort($scored, function ($a, $b) {
            if ($a['_score'] === $b['_score']) {
                return strtotime((string) ($b['created_at'] ?? '')) <=> strtotime((string) ($a['created_at'] ?? ''));
            }
            return $b['_score'] <=> $a['_score'];
        });

        $recommended = array_slice($scored, 0, 10);
        if (count($recommended) < 5) {
            $excludeIds = array_column($recommended, 'id');
            $fill = get_fallback_jobs($conn, $userId, $excludeIds, 10 - count($recommended));
            $recommended = array_merge($recommended, $fill);
        }

        return $recommended;
    }
}

?>
