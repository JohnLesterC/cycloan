<?php
require_once 'session_config.php'; // starts session if not already

// Clear all session variables
$_SESSION = [];

// Destroy the session
session_unset();
session_destroy();

// Clear remember-me cookies
setcookie('cycloan_remembered_email', '', time() - 3600, '/');
setcookie('cycloan_remembered_password', '', time() - 3600, '/');

// Redirect to login page
header("Location: index.php");
exit();
