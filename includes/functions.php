<?php
// includes/functions.php

function is_logged_in() {
    return isset($_SESSION['user_id']) || isset($_SESSION['company_id']) || isset($_SESSION['admin_id']);
}

function is_job_seeker() {
    return isset($_SESSION['user_id']);
}

function is_company() {
    return isset($_SESSION['company_id']);
}

function redirect_if_not_logged_in($role = null) {
    if (!is_logged_in()) {
        header("Location: login-choice.php");
        exit;
    }
    if ($role === 'seeker' && !is_job_seeker()) {
        header("Location: index.php");
        exit;
    }
    // similar for company & admin
}

function safe_file_upload($file, $allowed = ['pdf'], $max_size = 5*1024*1024, $dest_folder = 'uploads/resumes/') {
    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    if ($file['size'] > $max_size) return false;
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) return false;
    
    $new_name = uniqid() . '.' . $ext;
    $dest = $dest_folder . $new_name;
    
    if (move_uploaded_file($file['tmp_name'], $dest)) {
        return $dest;
    }
    return false;
}

function send_email($to, $subject, $body) {
    // Use PHPMailer or mail() for now
    // In production → use PHPMailer + SMTP (Gmail, SendGrid, etc)
    return mail($to, $subject, $body, "From: no-reply@yourjobhub.com");
}