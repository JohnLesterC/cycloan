<?php
session_start();
header('Content-Type: application/json');
require_once 'CYCLOAN_db.php';

// Check if user is logged in (check for email session variable used by admins)
if (!isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

// Get all current interest rates
if ($action === 'get_all_interest_rates') {
    $query = "SELECT ir.term_length, ir.interest_rate, ir.updated_at 
              FROM interest_rates ir
              INNER JOIN (
                  SELECT term_length, MAX(updated_at) as max_date
                  FROM interest_rates
                  GROUP BY term_length
              ) latest ON ir.term_length = latest.term_length AND ir.updated_at = latest.max_date
              ORDER BY ir.term_length ASC";

    $result = mysqli_query($conn, $query);

    if ($result) {
        $rates = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $rates[] = [
                'term_length' => $row['term_length'],
                'interest_rate' => $row['interest_rate'],
                'updated_at' => $row['updated_at']
            ];
        }
        echo json_encode(['success' => true, 'rates' => $rates]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch rates: ' . mysqli_error($conn)]);
    }
    mysqli_close($conn);
    exit;
}

// Get interest rate history
if ($action === 'get_interest_rate_history') {
    $query = "SELECT ir.id, ir.term_length, ir.interest_rate, ir.updated_at, sa.email as updated_by
              FROM interest_rates ir
              LEFT JOIN superadmins sa ON ir.updated_by = sa.id
              ORDER BY ir.term_length ASC, ir.updated_at DESC
              LIMIT 50";

    $result = mysqli_query($conn, $query);

    if ($result) {
        $history = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $history[] = [
                'id' => $row['id'],
                'term_length' => $row['term_length'],
                'interest_rate' => $row['interest_rate'],
                'updated_at' => $row['updated_at'],
                'updated_by' => $row['updated_by'] ?? 'System'
            ];
        }
        echo json_encode(['success' => true, 'history' => $history]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch history: ' . mysqli_error($conn)]);
    }
    mysqli_close($conn);
    exit;
}

// Get single rate by term length
if ($action === 'get_rate') {
    $termLength = isset($_GET['term_length']) ? $_GET['term_length'] : '12';

    // Validate term length
    if (!in_array($termLength, ['6', '12', '18', '24', '36'])) {
        $termLength = '12';
    }

    $query = "SELECT interest_rate FROM interest_rates WHERE term_length = ? ORDER BY updated_at DESC LIMIT 1";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 's', $termLength);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {
        echo json_encode([
            'success' => true,
            'interest_rate' => floatval($row['interest_rate']),
            'term_length' => $termLength
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'interest_rate' => 6.00,
            'term_length' => $termLength,
            'message' => 'Using default interest rate'
        ]);
    }

    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
mysqli_close($conn);
?>