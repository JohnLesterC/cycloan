<?php
session_start();

// Set the activation_success flag to test the page
$_SESSION['activation_success'] = true;

// Redirect to activation_success.php
header("Location: activation_success.php");
exit();
?>