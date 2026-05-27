<?php
require_once __DIR__ . '/env_config.php';
if (defined('APP_DEBUG') && APP_DEBUG) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require 'CYCLOAN_db.php';
ini_set('log_errors', '1');

// Validate session
if (!isset($_SESSION['reset_email']) || !isset($_SESSION['reset_otp_id'])) {
    header("Location: forget_pass.php?message=" . urlencode('Session expired. Please try again.') . "&type=error");
    exit();
}

// Validate POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['otp_code'])) {
    header("Location: verify_otp_reset.php?message=" . urlencode('Invalid request.') . "&type=error");
    exit();
}

try {
    $email = $_SESSION['reset_email'];
    $otp_id = $_SESSION['reset_otp_id'];
    $submitted_otp = trim($_POST['otp_code']);

    // Validate OTP format
    if (!preg_match('/^\d{6}$/', $submitted_otp)) {
        header("Location: verify_otp_reset.php?message=" . urlencode('Invalid OTP format. Please enter 6 digits.') . "&type=error");
        exit();
    }

    // Fetch OTP from database
    $stmt = $conn->prepare("SELECT otp_hash, expires_at, attempt_count FROM otps WHERE user_id = ?");
    if (!$stmt) {
        error_log("Prepare error in process_otp_reset: " . $conn->error);
        throw new Exception("Database error: Unable to verify OTP");
    }

    $stmt->bind_param("i", $otp_id);
    if (!$stmt->execute()) {
        error_log("Execute error in process_otp_reset: " . $stmt->error);
        throw new Exception("Database error: Unable to verify OTP");
    }

    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $stmt->close();
        error_log("OTP not found for user_id: $otp_id");
        header("Location: verify_otp_reset.php?message=" . urlencode('Invalid OTP code. Please try again.') . "&type=error");
        exit();
    }

    $otp_record = $result->fetch_assoc();
    $stmt->close();

    // Check if OTP has expired
    $current_time = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $expiry_time = new DateTime($otp_record['expires_at'], new DateTimeZone('Asia/Manila'));

    if ($current_time > $expiry_time) {
        error_log("OTP expired for user_id: $otp_id");
        header("Location: verify_otp_reset.php?message=" . urlencode('OTP has expired. Please request a new one.') . "&type=error");
        exit();
    }

    // Verify OTP matches using bcrypt
    if (!password_verify($submitted_otp, $otp_record['otp_hash'])) {
        // Increment attempt count
        $attemptCount = $otp_record['attempt_count'] + 1;
        $updateAttempt = $conn->prepare("UPDATE otps SET attempt_count = ? WHERE user_id = ?");
        if ($updateAttempt) {
            $updateAttempt->bind_param("ii", $attemptCount, $otp_id);
            $updateAttempt->execute();
            $updateAttempt->close();
        }
        error_log("Invalid OTP for user_id: $otp_id (attempt: $attemptCount)");
        header("Location: verify_otp_reset.php?message=" . urlencode('Invalid OTP code. Please try again.') . "&type=error");
        exit();
    }

    // OTP is valid - mark as verified
    $updateStmt = $conn->prepare("UPDATE otps SET verified_at = NOW() WHERE user_id = ?");
    if ($updateStmt) {
        $updateStmt->bind_param("i", $otp_id);
        $updateStmt->execute();
        $updateStmt->close();
    }

    // Set session flag for next step
    $_SESSION['otp_verified'] = true;

    // Redirect to confirmation page
    header("Location: confirm_password_reset.php?message=" . urlencode('OTP verified successfully. Now set your new password.') . "&type=success");
    exit();
} catch (Exception $e) {
    error_log("Error in process_otp_reset.php: " . $e->getMessage());
    header("Location: verify_otp_reset.php?message=" . urlencode('An error occurred. Please try again.') . "&type=error");
    exit();
}
?>