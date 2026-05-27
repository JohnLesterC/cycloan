<?php
// ========== GET INTEREST RATE API ==========
// Fetches the current interest rate for a specific term length
// Returns JSON format suitable for AJAX calls

header('Content-Type: application/json');
require 'CYCLOAN_db.php';

try {
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    // Get term length from request, default to 12 months
    $termLength = isset($_GET['term_length']) ? sanitize_input($_GET['term_length']) : '12';

    // Validate term length
    if (!in_array($termLength, ['6', '12', '18', '24', '36'])) {
        $termLength = '12'; // Default to 12 months if invalid
    }

    // Query for the most recent interest rate for this term length
    $sql = "SELECT id, interest_rate, term_length, updated_at, updated_by 
            FROM interest_rates 
            WHERE term_length = ? 
            ORDER BY updated_at DESC 
            LIMIT 1";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, "s", $termLength);
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Execute failed: " . mysqli_error($conn));
    }

    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {
        // Found a rate
        echo json_encode([
            'success' => true,
            'interest_rate' => floatval($row['interest_rate']),
            'term_length' => $row['term_length'],
            'updated_at' => $row['updated_at'],
            'updated_by' => $row['updated_by']
        ]);
    } else {
        // No rate found, return default
        echo json_encode([
            'success' => true,
            'interest_rate' => 6.00, // Default to 6%
            'term_length' => $termLength,
            'message' => 'Using default interest rate',
            'is_default' => true
        ]);
    }

    mysqli_stmt_close($stmt);

} catch (Exception $e) {
    error_log("Get Interest Rate Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'interest_rate' => 6.00
    ]);
}

/**
 * Sanitize user input
 */
function sanitize_input($input)
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
?>