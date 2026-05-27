<?php
session_start();

// Database connection settings
require_once 'CYCLOAN_db.php';

// Check connection
if ($conn->connect_error) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Connection failed: ' . $conn->connect_error]);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

// Check for existing or previous loans
try {
    $userId = (int) $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT COUNT(*) FROM loan_applications WHERE user_id = ?");
    if ($stmt === false) {
        throw new Exception("Failed to prepare query: " . $conn->error);
    }
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($loanCount);
    $stmt->fetch();
    $stmt->close();

    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'hasLoans' => $loanCount > 0
    ]);
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} finally {
    $conn->close();
}
?>