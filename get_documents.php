<?php
session_start();
require_once 'CYCLOAN_db.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access.'
    ]);
    exit;
}

try {
    $userId = $_SESSION['user_id'];

    // Fetch documents for the current user
    $query = "SELECT 
                d.document_id,
                d.file_path,
                dt.document_name,
                d.status,
                d.status_updated_at,
                d.created_at
            FROM documents d
            JOIN document_types dt ON d.document_type_id = dt.document_type_id
            JOIN loan_applications la ON d.application_id = la.application_id
            WHERE la.user_id = ?
            ORDER BY d.created_at DESC";

    $stmt = $conn->prepare($query);

    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }

    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $documents = [];
    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }

    $stmt->close();

    echo json_encode([
        'success' => true,
        'documents' => $documents,
        'count' => count($documents)
    ]);

} catch (Exception $e) {
    error_log("Error in get_documents.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching documents.',
        'error' => $e->getMessage()
    ]);
}

if ($conn) {
    $conn->close();
}
?>