<?php
/**
 * Simple OTP Database Test
 * Tests if OTP can be stored in database (bypasses email)
 */

require 'CYCLOAN_db.php';

$test_user_id = 1; // Change this to a valid user ID
$test_otp = '123456';
$test_email = 'john@example.com'; // Change this to test email
$expireTime = date('Y-m-d H:i:s', strtotime('+15 minutes'));

echo "<pre>";
echo "===== OTP DATABASE INSERT TEST =====\n\n";

// Check if user exists
echo "Step 1: Check if user exists (ID: $test_user_id)\n";
$checkStmt = $conn->prepare("SELECT id FROM users1 WHERE id = ?");
$checkStmt->bind_param("i", $test_user_id);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();
if ($checkResult->num_rows > 0) {
    echo "[OK] User found\n\n";
} else {
    echo "[FAIL] User not found\n";
    echo "Try a different user ID. Here are available users:\n";
    $result = $conn->query("SELECT id, email FROM users1 LIMIT 5");
    while ($row = $result->fetch_assoc()) {
        echo "  ID: " . $row['id'] . ", Email: " . $row['email'] . "\n";
    }
    echo "\n";
}
$checkStmt->close();

// Check if OTP record already exists
echo "Step 2: Check if OTP record exists for user $test_user_id\n";
$checkOtpStmt = $conn->prepare("SELECT id FROM otps WHERE user_id = ?");
$checkOtpStmt->bind_param("i", $test_user_id);
$checkOtpStmt->execute();
$checkOtpResult = $checkOtpStmt->get_result();

if ($checkOtpResult->num_rows > 0) {
    echo "[OK] OTP record exists - will UPDATE\n\n";

    // Update existing
    echo "Step 3: Updating OTP record...\n";
    $updateStmt = $conn->prepare("UPDATE otps SET otp_code = ?, expires_at = ?, created_at = NOW() WHERE user_id = ?");
    if (!$updateStmt) {
        echo "[FAIL] Prepare failed: " . $conn->error . "\n";
    } else {
        $updateStmt->bind_param("ssi", $test_otp, $expireTime, $test_user_id);
        if ($updateStmt->execute()) {
            echo "[OK] UPDATE successful\n";
            echo "Rows affected: " . $updateStmt->affected_rows . "\n";
        } else {
            echo "[FAIL] UPDATE failed: " . $updateStmt->error . "\n";
        }
        $updateStmt->close();
    }
} else {
    echo "[FAIL] No existing OTP record - will INSERT\n\n";

    // Insert new
    echo "Step 3: Inserting new OTP record...\n";
    $insertStmt = $conn->prepare("INSERT INTO otps (user_id, otp_code, expires_at, created_at) VALUES (?, ?, ?, NOW())");
    if (!$insertStmt) {
        echo "[FAIL] Prepare failed: " . $conn->error . "\n";
    } else {
        $insertStmt->bind_param("iss", $test_user_id, $test_otp, $expireTime);
        if ($insertStmt->execute()) {
            echo "[OK] INSERT successful\n";
            echo "Inserted OTP ID: " . $insertStmt->insert_id . "\n";
        } else {
            echo "[FAIL] INSERT failed: " . $insertStmt->error . "\n";
        }
        $insertStmt->close();
    }
}
$checkOtpStmt->close();

// Verify the data was stored
echo "\nStep 4: Verify OTP stored in database\n";
$verifyStmt = $conn->prepare("SELECT otp_code, expires_at, created_at FROM otps WHERE user_id = ?");
$verifyStmt->bind_param("i", $test_user_id);
$verifyStmt->execute();
$verifyResult = $verifyStmt->get_result();
if ($verifyResult->num_rows > 0) {
    $row = $verifyResult->fetch_assoc();
    echo "[OK] OTP found in database:\n";
    echo "   Code: " . $row['otp_code'] . "\n";
    echo "   Expires: " . $row['expires_at'] . "\n";
    echo "   Created: " . $row['created_at'] . "\n";
} else {
    echo "[FAIL] OTP not found in database after insert/update\n";
}
$verifyStmt->close();

echo "\n===== END DATABASE TEST =====\n";
echo "</pre>";
?>