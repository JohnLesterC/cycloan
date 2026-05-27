<?php
date_default_timezone_set('Asia/Manila');

ini_set('session.use_only_cookies', 1);
ini_set('session.use_strict_mode', 1);

$domain = $_SERVER['HTTP_HOST'] ?? 'localhost';

session_set_cookie_params([
  'lifetime' => 3600,
  'domain' => $domain,
  'path' => '/',
  'secure' => true,
  'httponly' => true,
  'samesite' => 'Strict'
]);

session_start();

$directory = "index.php";

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 2400)) {
  session_unset();
  session_destroy();
  $_SESSION['error'] = "Your session has expired. Please login again.";
  header("Location: $directory");
  exit();
}

$_SESSION['last_activity'] = time();

if (!isset($_SESSION['last_regeneration'])) {
  regenerate_session_id();
} else {
  $interval = 60 * 60;
  if (time() - $_SESSION['last_regeneration'] >= $interval) {
    if (isset($_SESSION['email'])) {
      regenerate_session_id_loggedin();
    } else {
      regenerate_session_id();
    }
  }
}

function regenerate_session_id_loggedin()
{
  session_regenerate_id(true);

  $userEmail = $_SESSION['email'];
  $newSessionId = session_create_id();
  $sessionId = $newSessionId . '_' . $userEmail;
  session_id($sessionId);

  $_SESSION['last_regeneration'] = time();
}

function regenerate_session_id()
{
  session_regenerate_id(true);
  $_SESSION['last_regeneration'] = time();
}