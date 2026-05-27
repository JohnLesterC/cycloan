<?php
session_start();
require 'CYCLOAN_db.php';

// Validate session
if (!isset($_SESSION['reset_email']) || !isset($_SESSION['otp_verified']) || !$_SESSION['otp_verified']) {
    header("Location: forget_pass.php?message=" . urlencode('Session expired. Please start over.') . "&type=error");
    exit();
}

// Validate POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['password']) || !isset($_POST['confirm_password'])) {
    header("Location: confirm_password_reset.php?message=" . urlencode('Invalid request.') . "&type=error");
    exit();
}

try {
    $email = $_SESSION['reset_email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate passwords match
    if ($password !== $confirm_password) {
        header("Location: confirm_password_reset.php?message=" . urlencode('Passwords do not match.') . "&type=error");
        exit();
    }

    // Validate password strength
    $strength_check = (
        strlen($password) >= 8 &&
        preg_match('/[A-Z]/', $password) &&
        preg_match('/[a-z]/', $password) &&
        preg_match('/\d/', $password) &&
        preg_match('/[!@#$%^&*]/', $password)
    );

    if (!$strength_check) {
        header("Location: confirm_password_reset.php?message=" . urlencode('Password does not meet requirements.') . "&type=error");
        exit();
    }

    // Find user and update password
    $userTable = null;
    $userId = null;
    $userName = null;
    $userRole = null;

    // Search in all user tables
    $tables = ['users1', 'admin1', 'admin2', 'superadmins'];
    foreach ($tables as $table) {
        $stmt = $conn->prepare("SELECT id, first_name, last_name FROM $table WHERE email = ?");
        if (!$stmt) {
            error_log("Prepare error for $table: " . $conn->error);
            continue;
        }
        $stmt->bind_param("s", $email);
        if (!$stmt->execute()) {
            error_log("Execute error for $table: " . $stmt->error);
            $stmt->close();
            continue;
        }
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $userTable = $table;
            $userId = $user['id'];
            $userName = trim($user['first_name'] . ' ' . $user['last_name']);
            $userRole = ($table === 'users1') ? 'user' : $table;
            $stmt->close();
            break;
        }
        $stmt->close();
    }

    // User not found
    if (!$userTable) {
        error_log("User not found for email: $email");
        session_destroy();
        header("Location: forget_pass.php?message=" . urlencode('User account not found.') . "&type=error");
        exit();
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Update password in database
    $updateStmt = $conn->prepare("UPDATE $userTable SET password = ? WHERE id = ? AND email = ?");
    if (!$updateStmt) {
        error_log("Prepare error updating password: " . $conn->error);
        throw new Exception("Database error while updating password");
    }
    $updateStmt->bind_param("sis", $hashedPassword, $userId, $email);

    if (!$updateStmt->execute()) {
        error_log("Failed to update password for email: $email. Error: " . $updateStmt->error);
        $updateStmt->close();
        header("Location: confirm_password_reset.php?message=" . urlencode('Failed to update password. Please try again.') . "&type=error");
        exit();
    }
    $updateStmt->close();

    // Log the password reset if activity_logs table exists
    $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, user_role, admin_name, admin_email, action_type, module, description, affected_id) VALUES (?, ?, 'System', 'system@cycloan.com', 'password_reset_completed', 'authentication', 'Password reset completed via OTP verification', ?)");
    if ($logStmt) {
        $logStmt->bind_param("iss", $userId, $userRole, $userId);
        $logStmt->execute();
        $logStmt->close();
    }

    // Clear session
    session_destroy();

    // Redirect to success page
    header("Location: password_reset_success.php?email=" . urlencode($email));
    exit();
} catch (Exception $e) {
    error_log("Error in complete_password_reset.php: " . $e->getMessage());
    header("Location: confirm_password_reset.php?message=" . urlencode('An error occurred. Please try again.') . "&type=error");
    exit();
}
?>