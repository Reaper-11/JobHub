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

// Ensure applications.status exists for admin updates.
$colCheck = $conn->query("SHOW COLUMNS FROM applications LIKE 'status'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE applications ADD COLUMN status VARCHAR(20) DEFAULT 'pending'");
}
?>
