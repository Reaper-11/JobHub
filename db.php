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

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

/**
 * Simple job expiration check
 */
function job_expiration_timestamp($created_at, $duration) {
    if (empty($duration) || strtolower($duration) === 'ongoing') return null;
    $created_ts = strtotime($created_at);
    if ($created_ts === false) return null;

    if (preg_match('/^(\d+)\s*(day|days|week|weeks|month|months|year|years)$/i', $duration, $m)) {
        return strtotime("+{$m[1]} {$m[2]}", $created_ts);
    }
    return strtotime("+$duration", $created_ts);
}

function is_job_expired($job) {
    $expires = job_expiration_timestamp($job['created_at'] ?? '', $job['application_duration'] ?? '');
    return $expires !== null && time() > $expires;
}

function is_job_closed($job) {
    return strtolower($job['status'] ?? '') === 'closed';
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

// Close connection on shutdown (optional but good practice)
register_shutdown_function(function() use ($conn) {
    if ($conn) $conn->close();
});