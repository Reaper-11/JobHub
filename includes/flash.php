<?php
if (!isset($_SESSION)) {
    session_start();
}

function set_flash($key, $message, $type = 'alert-success')
{
    $_SESSION['flash'][$key] = [
        'message' => $message,
        'type' => $type,
    ];
}

function get_flash($key)
{
    if (empty($_SESSION['flash'][$key])) {
        return null;
    }
    $flash = $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);
    return $flash;
}
?>
