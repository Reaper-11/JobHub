<?php
require '../db.php';

if (current_role() === 'admin') {
    jobhub_redirect('admin/admin-dashboard.php');
}

jobhub_redirect('login.php');
