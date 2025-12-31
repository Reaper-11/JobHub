<?php
if (!isset($_SESSION)) {
    session_start();
}

function require_login($loginPath = 'login.php')
{
    if (!isset($_SESSION['user_id'])) {
        header("Location: $loginPath");
        exit;
    }
}

function require_admin($loginPath = 'login.php')
{
    if (!isset($_SESSION['user_id'])) {
        header("Location: $loginPath");
        exit;
    }
    if (($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo 'Access denied';
        exit;
    }
}
?>
