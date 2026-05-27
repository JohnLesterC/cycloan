<?php
session_start();
require_once 'CYCLOAN_db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

$email = isset($_POST['email']) ? trim($_POST['email']) : '';

if (empty($email)) {
    echo json_encode(['error' => 'Email is required']);
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['error' => 'Invalid email format']);
    exit;
}

try {
    // Check if email exists in users1 table
    $stmt = $conn->prepare("SELECT id FROM users1 WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode([
            'exists' => true,
            'message' => 'This email is already registered. Please use a different email or log in to your existing account.'
        ]);
    } else {
        echo json_encode([
            'exists' => false,
            'message' => 'Email is available'
        ]);
    }
    
    $stmt->close();
} catch (Exception $e) {
    error_log("Email check error: " . $e->getMessage());
    echo json_encode(['error' => 'An error occurred while checking the email']);
}

$conn->close();
?>
