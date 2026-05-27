<?php
session_start();
require "CYCLOAN_db.php";

// Set JSON content type
header('Content-Type: application/json');

// Ensure user is authorized
if (!isset($_SESSION['email']) || !in_array($_SESSION['role'], ['admin1', 'superadmin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Ensure $conn is a MySQLi instance
if (!($conn instanceof mysqli)) {
    error_log("Database connection is not a MySQLi instance", 3, 'errors.log');
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit;
}

// Check if connection is successful
if (mysqli_connect_errno()) {
    error_log("Database connection error: " . mysqli_connect_error(), 3, 'errors.log');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Parse JSON input
$data = json_decode(file_get_contents('php://input'), true);
$action_type = filter_var($data['action_type'] ?? '', FILTER_SANITIZE_STRING);
$module = filter_var($data['module'] ?? '', FILTER_SANITIZE_STRING);
$description = filter_var($data['description'] ?? '', FILTER_SANITIZE_STRING);
$affected_id = filter_var($data['affected_id'] ?? 0, FILTER_VALIDATE_INT);

// Validate inputs
if (!$action_type || !$module || !$description || !$affected_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit;
}

// Get user_id based on role
$email = $_SESSION['email'];
$role = $_SESSION['role'];
$valid_roles = ['superadmin' => 'superadmins', 'admin1' => 'admin1', 'admin2' => 'admin2'];
$table_name = $valid_roles[$role] ?? 'admin2';

$stmt = $conn->prepare("SELECT id FROM $table_name WHERE email = ?");
if (!$stmt) {
    error_log("User ID query preparation failed for table $table_name: " . $conn->error, 3, 'errors.log');
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

$user_id = $user['id'];

// Insert into activity_logs
$stmt = $conn->prepare("
    INSERT INTO activity_logs (user_id, user_role, action_type, module, description, affected_id, created_at)
    VALUES (?, ?, ?, ?, ?, ?, NOW())
");
if (!$stmt) {
    error_log("Activity log insert preparation failed: " . $conn->error, 3, 'errors.log');
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}
$stmt->bind_param("issssi", $user_id, $role, $action_type, $module, $description, $affected_id);
$success = $stmt->execute();
$stmt->close();

if ($success) {
    echo json_encode(['success' => true, 'message' => 'Activity logged successfully']);
} else {
    error_log("Failed to insert activity log: " . $conn->error, 3, 'errors.log');
    echo json_encode(['success' => false, 'message' => 'Failed to log activity']);
}

$conn->close();
?>