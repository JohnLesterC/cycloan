<?php
/**
 * Test Script: Rejection Notes Debug
 * 
 * This script helps verify the rejection_notes storage issue.
 * Run this after attempting a pre-approval rejection in the admin dashboard.
 * 
 * Instructions:
 * 1. Perform a pre-approval rejection in admin2_dashboard.php (reject an application)
 * 2. Run this script: php test_rejection_debug.php
 * 3. Share the output with diagnostic information
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'CYCLOAN_db.php';

echo "=== REJECTION NOTES DEBUG TEST ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Check error_log for recent messages
echo "1. CHECKING ERROR LOG...\n";
echo "   Looking for BACKEND_DEBUG messages (new logging added)...\n\n";

$errorLogFile = ini_get('error_log');
echo "   Error log location: $errorLogFile\n";

if (file_exists($errorLogFile)) {
    $lines = file($errorLogFile);
    $recentLines = array_slice($lines, -200); // Last 200 lines

    $debugFound = false;
    echo "\n   BACKEND DEBUG MESSAGES:\n";
    foreach ($recentLines as $line) {
        if (strpos($line, 'BACKEND_DEBUG') !== false || strpos($line, 'EXECUTE_UPDATE_DEBUG') !== false) {
            echo "   > " . trim($line) . "\n";
            $debugFound = true;
        }
    }

    // Also check for old debug messages
    $oldDebugFound = false;
    echo "\n   OLD DEBUG MESSAGES (pre-approval path):\n";
    foreach ($recentLines as $line) {
        if (
            strpos($line, 'DEBUG_REJECTION_REASON') !== false ||
            strpos($line, 'VERIFY_SAVED') !== false ||
            strpos($line, 'PREAPPROVAL_UPDATE_DOC') !== false
        ) {
            echo "   > " . trim($line) . "\n";
            $oldDebugFound = true;
        }
    }

    if (!$debugFound && !$oldDebugFound) {
        echo "   ⚠️  No debug messages found!\n";
        echo "   This means: Recent rejection action not yet performed.\n";
        echo "   ACTION: Reject a document in admin2_dashboard.php, then run this script again.\n";
    }
} else {
    echo "   ⚠️  Error log file not found at: $errorLogFile\n";
}

echo "\n";

// 2. Check database for rejected documents
echo "2. CHECKING DATABASE FOR REJECTED DOCUMENTS...\n";

$query = "
    SELECT 
        d.document_id, 
        d.application_id,
        dt.document_name,
        d.status,
        d.rejection_notes,
        d.updated_at
    FROM documents d
    JOIN document_types dt ON d.document_type_id = dt.document_type_id
    WHERE d.status = 'Rejected'
    ORDER BY d.updated_at DESC
    LIMIT 10
";

$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    echo "   Found " . $result->num_rows . " rejected documents:\n\n";

    while ($row = $result->fetch_assoc()) {
        echo "   Document ID: {$row['document_id']}\n";
        echo "   Application: {$row['application_id']}\n";
        echo "   Type: {$row['document_name']}\n";
        echo "   Status: {$row['status']}\n";
        echo "   Rejection Notes: '" . $row['rejection_notes'] . "'\n";
        echo "   Updated: {$row['updated_at']}\n";

        // Check if rejection_notes is the problem
        if ($row['rejection_notes'] === '0' || $row['rejection_notes'] === 0 || empty($row['rejection_notes'])) {
            echo "   ❌ ISSUE: rejection_notes is empty or 0!\n";
        } else {
            echo "   ✅ OK: rejection_notes has text value\n";
        }
        echo "\n";
    }
} else {
    echo "   No rejected documents found in database.\n";
}

echo "\n";

// 3. Check loan_applications for approval_reason
echo "3. CHECKING LOAN_APPLICATIONS FOR APPROVAL_REASON...\n";

$query = "
    SELECT 
        application_id,
        loan_amount,
        approval_status,
        approval_reason,
        updated_at
    FROM loan_applications
    WHERE approval_status = 'Rejected' OR (approval_status = 'Pre-Approved' AND approval_reason IS NOT NULL)
    ORDER BY updated_at DESC
    LIMIT 5
";

$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    echo "   Found " . $result->num_rows . " applications with approval_reason:\n\n";

    while ($row = $result->fetch_assoc()) {
        echo "   Application ID: {$row['application_id']}\n";
        echo "   Status: {$row['approval_status']}\n";
        echo "   Approval Reason: '" . $row['approval_reason'] . "'\n";
        echo "   Updated: {$row['updated_at']}\n";
        echo "\n";
    }
} else {
    echo "   No applications with rejection reasons found.\n";
}

echo "\n";

// 4. Test the UPDATE directly
echo "4. TESTING DIRECT UPDATE...\n";

$testDocId = 1; // You may need to adjust this
$testReason = "TEST REJECTION REASON - " . date('Y-m-d H:i:s');

echo "   Attempting test UPDATE on document_id=$testDocId\n";
echo "   Reason: '$testReason'\n\n";

// First check current value
$checkStmt = $conn->prepare("SELECT rejection_notes FROM documents WHERE document_id = ?");
$checkStmt->bind_param("i", $testDocId);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows > 0) {
    $row = $checkResult->fetch_assoc();
    echo "   Before UPDATE: rejection_notes = '" . $row['rejection_notes'] . "'\n";

    // Perform test UPDATE
    $updateStmt = $conn->prepare("UPDATE documents SET rejection_notes = ? WHERE document_id = ?");
    $updateStmt->bind_param("si", $testReason, $testDocId);

    if ($updateStmt->execute()) {
        $affectedRows = $updateStmt->affected_rows;
        echo "   UPDATE executed, rows affected: $affectedRows\n";

        // Verify
        $verifyStmt = $conn->prepare("SELECT rejection_notes FROM documents WHERE document_id = ?");
        $verifyStmt->bind_param("i", $testDocId);
        $verifyStmt->execute();
        $verifyResult = $verifyStmt->get_result();

        if ($verifyResult->num_rows > 0) {
            $row = $verifyResult->fetch_assoc();
            echo "   After UPDATE: rejection_notes = '" . $row['rejection_notes'] . "'\n";

            if ($row['rejection_notes'] === $testReason) {
                echo "   ✅ UPDATE WORKS: Value saved correctly!\n";
            } else {
                echo "   ❌ UPDATE FAILED: Value not saved!\n";
            }
        }
    } else {
        echo "   ❌ UPDATE FAILED: " . $updateStmt->error . "\n";
    }
} else {
    echo "   Document with ID=$testDocId not found\n";
}

echo "\n";

// 5. Database connection info
echo "5. DATABASE CONNECTION INFO...\n";
echo "   Host: " . ($conn ? "Connected" : "Failed") . "\n";
echo "   Character Set: " . $conn->character_set_name() . "\n";

echo "\n=== END DEBUG TEST ===\n";

$conn->close();
?>