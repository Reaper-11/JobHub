<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "JobHub";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
session_start();

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

?>
