<?php
require_once '../includes/bootstrap.php';  // ← ADD THIS
session_start();
// ... rest of file
session_destroy();
header("Location: login.php");
exit;
