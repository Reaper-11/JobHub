<?php
require 'db.php';

logout_user();

header("Location: index.php");
exit;
