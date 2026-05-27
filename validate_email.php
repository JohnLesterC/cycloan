<?php
/**
 * EMAIL VALIDATION - Check if email is already registered
 * This endpoint performs real-time validation for registration form
 */

session_start();
require_once 'CYCLOAN_db.php';

header('Content-Type: application/json');

// Security: Check if request is POST and AJAX
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['email'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
    exit();
}

// Sanitize and validate email
$email = sanitizeEmail($_POST['email']);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false,
        'valid' => false,
        'message' => 'Invalid email format'
    ]);
    exit();
}

// Function to sanitize email
function sanitizeEmail($email)
{
    return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
}

// Check if email exists in database
try {
    // First check in users1 table
    $stmt = $conn->prepare("SELECT id FROM users1 WHERE email = ? LIMIT 1");
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Email already registered
        echo json_encode([
            'success' => true,
            'exists' => true,
            'message' => 'This email is already registered. Please use a different email or login to your account.',
            'valid' => false
        ]);
        $stmt->close();
        exit();
    }

    $stmt->close();

    // Email is not registered and is valid
    echo json_encode([
        'success' => true,
        'exists' => false,
        'message' => 'Email is available',
        'valid' => true
    ]);

} catch (Exception $e) {
    error_log("Email validation error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while validating the email',
        'valid' => false
    ]);
}

$conn->close();
?>