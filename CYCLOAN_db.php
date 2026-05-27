<?php
// ========== PHILIPPINES TIMEZONE CONFIGURATION ==========
// Set PHP timezone to Philippine Time (PHT) - UTC+8
date_default_timezone_set('Asia/Manila');

$host = 'localhost';
$database = 'cycloan_db';
$username = 'root';
$password = '';

try {
    $conn = new mysqli($host, $username, $password, $database);

    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // ========== SET MYSQL SESSION TIMEZONE TO PHILIPPINES TIME ==========
    // This ensures all CURRENT_TIMESTAMP and NOW() calls use PHT
    $conn->query("SET time_zone = '+08:00'");

    // ========== CHARACTER SET CONFIGURATION ==========
    // Set character set to UTF-8 for proper handling of special characters
    $conn->set_charset("utf8mb4");

    // ========== SESSION CONFIGURATION ==========
    // Start session with secure settings
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("Database connection failed. Please check XAMPP services.");
}

function escape_string($string)
{
    global $conn;
    return $conn->real_escape_string($string);
}
?>