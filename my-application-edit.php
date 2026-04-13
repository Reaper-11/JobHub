<?php
// my-application-edit.php
require 'db.php';
require_role('jobseeker');

jobhub_set_auth_flash('warning', 'Submitted applications cannot be edited.');
jobhub_redirect('my-applications.php');
