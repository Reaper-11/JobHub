<?php
// logout.php
require 'db.php';

jobhub_destroy_session();

header("Location: index.php");
exit;
