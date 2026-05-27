<?php
/**
 * Credit Investigation Database Verification Script
 * Purpose: Verify that database schema is correct for credit investigation feature
 * 
 * Run this to check:
 * 1. loan_applications table has required columns
 * 2. remarks table has correct structure
 * 3. Indexes are properly created
 */

require_once 'CYCLOAN_db.php';

echo "=== CREDIT INVESTIGATION DATABASE VERIFICATION ===\n\n";

// Check 1: loan_applications table structure
echo "1️⃣  Checking loan_applications table...\n";
$query = "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE 
          FROM INFORMATION_SCHEMA.COLUMNS 
          WHERE TABLE_NAME = 'loan_applications' 
          AND TABLE_SCHEMA = DATABASE()
          AND COLUMN_NAME IN ('term_length', 'final_loan_amount', 'credit_investigation_status')
          ORDER BY COLUMN_NAME";

$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();

    $requiredColumns = ['credit_investigation_status', 'final_loan_amount', 'term_length'];
    $foundColumns = [];

    while ($row = $result->fetch_assoc()) {
        $foundColumns[$row['COLUMN_NAME']] = $row['COLUMN_TYPE'];
        echo "   ✓ {$row['COLUMN_NAME']}: {$row['COLUMN_TYPE']} (Nullable: {$row['IS_NULLABLE']})\n";
    }

    foreach ($requiredColumns as $col) {
        if (!isset($foundColumns[$col])) {
            echo "   ❌ MISSING: $col\n";
        }
    }
    $stmt->close();
} else {
    echo "   ❌ Query error: " . $conn->error . "\n";
}

// Check 2: remarks table structure
echo "\n2️⃣  Checking remarks table...\n";
$query = "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE 
          FROM INFORMATION_SCHEMA.COLUMNS 
          WHERE TABLE_NAME = 'remarks' 
          AND TABLE_SCHEMA = DATABASE()
          ORDER BY COLUMN_NAME";

$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();

    $requiredRemarkColumns = ['remarks', 'admin_name', 'application_id', 'created_at'];
    $foundRemark = [];

    echo "   Remarks table columns:\n";
    while ($row = $result->fetch_assoc()) {
        $foundRemark[$row['COLUMN_NAME']] = $row['COLUMN_TYPE'];
        echo "      • {$row['COLUMN_NAME']}: {$row['COLUMN_TYPE']}\n";
    }

    echo "\n   Checking required columns:\n";
    foreach ($requiredRemarkColumns as $col) {
        if (isset($foundRemark[$col])) {
            echo "      ✓ $col found\n";
        } else {
            echo "      ❌ $col MISSING\n";
        }
    }

    $stmt->close();
} else {
    echo "   ❌ Query error: " . $conn->error . "\n";
}

// Check 3: Sample data - recent credit investigations
echo "\n3️⃣  Recent credit investigations:\n";
$query = "SELECT 
            la.application_id,
            u.first_name,
            u.last_name,
            la.final_loan_amount,
            la.term_length,
            la.credit_investigation_status,
            COUNT(r.remark_id) as remark_count
          FROM loan_applications la
          LEFT JOIN users1 u ON la.user_id = u.id
          LEFT JOIN remarks r ON la.application_id = r.application_id
          WHERE la.credit_investigation_status != 'Pending'
          GROUP BY la.application_id
          ORDER BY la.updated_at DESC
          LIMIT 5";

$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo "   ℹ️  No completed credit investigations found\n";
    } else {
        while ($row = $result->fetch_assoc()) {
            echo "   Application: {$row['application_id']}\n";
            echo "      Applicant: {$row['first_name']} {$row['last_name']}\n";
            echo "      Amount: ₱" . number_format($row['final_loan_amount'] ?? 0, 2) . "\n";
            echo "      Term: {$row['term_length']} months\n";
            echo "      Status: {$row['credit_investigation_status']}\n";
            echo "      Remarks: {$row['remark_count']} entries\n";
            echo "\n";
        }
    }
    $stmt->close();
} else {
    echo "   ❌ Query error: " . $conn->error . "\n";
}

// Check 4: Verify indexes
echo "4️⃣  Checking database indexes...\n";
$query = "SELECT INDEX_NAME, COLUMN_NAME 
          FROM INFORMATION_SCHEMA.STATISTICS 
          WHERE TABLE_NAME IN ('loan_applications', 'remarks')
          AND TABLE_SCHEMA = DATABASE()
          AND INDEX_NAME != 'PRIMARY'
          ORDER BY TABLE_NAME, INDEX_NAME";

$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo "   ⚠️  No additional indexes found\n";
        echo "   💡 Run CREDIT_INVESTIGATION_SCHEMA_FIX.sql to add performance indexes\n";
    } else {
        while ($row = $result->fetch_assoc()) {
            echo "   • {$row['INDEX_NAME']} on {$row['COLUMN_NAME']}\n";
        }
    }
    $stmt->close();
} else {
    echo "   ❌ Query error: " . $conn->error . "\n";
}

echo "\n=== VERIFICATION COMPLETE ===\n";
echo "\nSummary:\n";
echo "• Term Length: Stored in loan_applications.term_length\n";
echo "• Final Amount: Stored in loan_applications.final_loan_amount\n";
echo "• Remarks: Stored in remarks.remarks (TEXT column)\n";
echo "• Admin Name: Tracked in remarks.admin_name\n";
echo "• Timestamp: Recorded in remarks.created_at\n";

$conn->close();
?>