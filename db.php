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

        $baseSql = "SELECT j.* FROM jobs j
                    LEFT JOIN companies c ON c.id = j.company_id
                    WHERE (j.company_id IS NULL OR c.is_approved = 1)";
        $matching = [];
        $remaining = [];

        if ($preferredCategory !== '') {
            $sql = $baseSql . " AND j.category = ? ORDER BY j.created_at DESC";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("s", $preferredCategory);
                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    $matching = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
                }
                $stmt->close();
            }

            $sql = $baseSql . " AND j.category <> ? ORDER BY j.created_at DESC";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("s", $preferredCategory);
                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    $remaining = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
                }
                $stmt->close();
            }
        } else {
            $sql = $baseSql . " ORDER BY j.created_at DESC";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    $matching = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
                }
                $stmt->close();
            }
        }

        $recommended = array_merge($matching, $remaining);
        return array_slice($recommended, 0, 10);
    }
}

?>
