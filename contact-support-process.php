<?php
require 'db.php';
require_once __DIR__ . '/includes/support_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: contact-support.php');
    exit;
}

$context = support_require_contact_access($conn);

if (!support_table_exists($conn)) {
    support_set_flash('public', 'warning', 'Support module database table is missing. Run the support SQL first.');
    header('Location: contact-support.php');
    exit;
}

if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    support_set_flash('public', 'danger', 'Invalid request. Please try again.');
    header('Location: contact-support.php');
    exit;
}

$validation = support_validate_submission($_POST, $context);
$data = $validation['data'];
$errors = $validation['errors'];

if (!empty($errors)) {
    support_set_old_input('public', [
        'sender_name' => $data['sender_name'],
        'sender_email' => $data['sender_email'],
        'sender_phone' => $data['sender_phone'],
        'subject' => $data['subject'],
        'message' => $data['message'],
    ]);
    support_set_flash('public', 'danger', $errors[0]);
    header('Location: contact-support.php');
    exit;
}

$messageId = support_create_message($conn, $data);
if ($messageId <= 0) {
    support_set_old_input('public', [
        'sender_name' => $data['sender_name'],
        'sender_email' => $data['sender_email'],
        'sender_phone' => $data['sender_phone'],
        'subject' => $data['subject'],
        'message' => $data['message'],
    ]);
    support_set_flash('public', 'danger', 'Could not submit your support message. Please try again.');
    header('Location: contact-support.php');
    exit;
}

support_set_flash('public', 'success', 'Your support message has been submitted successfully.');
header('Location: contact-support.php');
exit;
