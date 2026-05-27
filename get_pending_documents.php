<?php
session_start();
require_once 'CYCLOAN_db.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in and is Admin 2
if (!isset($_SESSION['admin2_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access.'
    ]);
    exit;
}

try {
    // Fetch pending documents with applicant details
    $query = "SELECT 
                d.document_id,
                d.file_path,
                dt.document_name,
                la.application_id,
                u.first_name,
                u.last_name,
                d.created_at
            FROM documents d
            JOIN document_types dt ON d.document_type_id = dt.document_type_id
            JOIN loan_applications la ON d.application_id = la.application_id
            JOIN users1 u ON la.user_id = u.id
            WHERE d.status = 'Pending'
            ORDER BY d.created_at DESC";

    $stmt = $conn->prepare($query);

    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $documents = [];
    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }

    $stmt->close();

    if (count($documents) > 0) {
        echo json_encode([
            'success' => true,
            'documents' => $documents,
            'count' => count($documents)
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No pending documents found.',
            'documents' => []
        ]);
    }

} catch (Exception $e) {
    error_log("Error in get_pending_documents.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching documents.',
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>