<?php
// includes/functions.php
require_once __DIR__ . '/auth.php';

function is_job_seeker() {
    return current_role() === 'jobseeker';
}

function is_company() {
    return current_role() === 'company';
}

function redirect_if_not_logged_in($role = null) {
    if (!is_logged_in()) {
        $_SESSION['auth_error'] = 'Please log in to continue.';
        jobhub_redirect('login.php');
    }

    $role = jobhub_role_alias($role);
    if ($role !== null && current_role() !== $role) {
        jobhub_set_auth_flash('warning', 'Unauthorized access.');
        jobhub_redirect(jobhub_role_home());
    }
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
