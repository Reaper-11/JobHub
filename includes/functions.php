<?php
// includes/functions.php
require_once __DIR__ . '/auth.php';

function is_job_seeker() {
    return current_role() === 'jobseeker';
}

if (!function_exists('get_category_card_icon')) {
    function get_category_card_icon($category): string
    {
        $categoryKey = strtolower(trim((string) $category));

        return match ($categoryKey) {
            'information technology',
            'it' => "\u{1F4BB}",
            'finance' => "\u{1F4B0}",
            'marketing' => "\u{1F4E2}",
            'human resources' => "\u{1F465}",
            'sales' => "\u{1F6D2}",
            'design' => "\u{1F3A8}",
            'customer support' => "\u{1F3A7}",
            'internship' => "\u{1F393}",
            'freelance' => "\u{1F9D1}\u{200D}\u{1F4BB}",
            default => "\u{1F4BC}",
        };
    }
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
