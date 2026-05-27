<?php
// Centralized debug control via env_config.php
if (file_exists(__DIR__ . '/env_config.php')) {
    require_once __DIR__ . '/env_config.php';
}
if (defined('APP_DEBUG') && APP_DEBUG) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(0);
}
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Database connection
require_once 'CYCLOAN_db.php';
require_once 'email_sender.php';
$user_ip = $_SERVER['REMOTE_ADDR'];

// Email sending is handled by the centralized EmailSender (email_sender.php).
// Local PHPMailer-based email functions were removed to avoid duplication and
// to ensure consistent configuration across the system.
// Process OTP if POST
// Process OTP if POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // SECURITY: Validate CSRF token if present
    if (isset($_POST['csrf_token']) && !empty($_POST['csrf_token'])) {
        if ($_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            $_SESSION['fresh_redirect'] = true;
            $_SESSION['error_message'] = "Security error: Invalid session token. Please try again.";
            header("Location: verify_otp.php");
            exit();
        }
    }

    // Sanitize and validate OTP
    $otp = trim($_POST['otp'] ?? '');
    if (empty($otp) || !preg_match("/^\d{6}$/", $otp)) {
        $_SESSION['fresh_redirect'] = true;
        $_SESSION['error_message'] = "Please enter a valid 6-digit OTP.";
        header("Location: verify_otp.php");
        exit();
    }

    // Validate session email
    $email = isset($_SESSION['otp_email']) ? filter_var($_SESSION['otp_email'], FILTER_SANITIZE_EMAIL) : '';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['fresh_redirect'] = true;
        $_SESSION['error_message'] = "Session expired or invalid email. Please register again.";
        header("Location: registration.php?step=1");
        exit();
    }

    // SECURITY: Rate limiting with timestamp tracking (fallback)
    $attempt_key = "otp_attempts_" . md5($email);
    $last_attempt_key = "otp_last_attempt_" . md5($email);

    if (!isset($_SESSION[$attempt_key])) {
        $_SESSION[$attempt_key] = [];
    }

    // Clean old attempts (older than 15 minutes)
    $current_time = time();
    $_SESSION[$attempt_key] = array_filter(
        $_SESSION[$attempt_key],
        function ($timestamp) use ($current_time) {
            return ($current_time - $timestamp) < 900; // 15 minutes = 900 seconds
        }
    );

    // Check if maximum attempts reached
    if (count($_SESSION[$attempt_key]) >= 5) {
        $oldest_attempt = min($_SESSION[$attempt_key]);
        $wait_time = 900 - ($current_time - $oldest_attempt);
        $wait_minutes = ceil($wait_time / 60);

        error_log("Rate limited: Too many OTP attempts for email $email from IP {$_SERVER['REMOTE_ADDR']} | Timestamp: " . date('Y-m-d H:i:s'));
        $_SESSION['fresh_redirect'] = true;
        $_SESSION['error_message'] = "Too many incorrect attempts. Please try again in $wait_minutes minute(s).";
        header("Location: verify_otp.php");
        exit();
    }

    // Record this attempt
    $_SESSION[$attempt_key][] = $current_time;

    // Find user by email
    $user_query = "SELECT id, first_name, middle_name, last_name, name_extension FROM users1 WHERE email = ? AND is_active = 0";
    $stmt = $conn->prepare($user_query);
    if ($stmt === false) {
        error_log("Prepare failed for user query: " . $conn->error . " | Timestamp: " . date('Y-m-d H:i:s'));
        $_SESSION['fresh_redirect'] = true;
        $_SESSION['error_message'] = "An internal error occurred. Please try again later.";
        header("Location: verify_otp.php");
        exit();
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        error_log("No pending user found for email $email | Timestamp: " . date('Y-m-d H:i:s'));
        $_SESSION['fresh_redirect'] = true;
        $_SESSION['error_message'] = "No pending registration found for this email or account already verified.";
        $stmt->close();
        header("Location: verify_otp.php");
        exit();
    }

    $user = $result->fetch_assoc();
    $user_id = $user['id'];
    $stmt->close();

    // SECURITY: Verify OTP using hashed comparison (password_verify)
    $otp_query = "SELECT otp_hash, expires_at, attempt_count FROM otps WHERE user_id = ? ORDER BY created_at DESC LIMIT 1";
    $stmt = $conn->prepare($otp_query);
    if ($stmt === false) {
        error_log("Prepare failed for OTP query: " . $conn->error . " | Timestamp: " . date('Y-m-d H:i:s'));
        $_SESSION['fresh_redirect'] = true;
        $_SESSION['error_message'] = "An internal error occurred. Please try again later.";
        header("Location: verify_otp.php");
        exit();
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        error_log("No OTP found for user_id $user_id | Timestamp: " . date('Y-m-d H:i:s'));
        $_SESSION['fresh_redirect'] = true;
        $_SESSION['error_message'] = "No OTP found. Please request a new verification code.";
        $stmt->close();
        header("Location: verify_otp.php");
        exit();
    }

    $otp_data = $result->fetch_assoc();
    $stmt->close();

    // Check if OTP is expired
    $current_time_dt = new DateTime();
    $expires_at = new DateTime($otp_data['expires_at']);
    if ($current_time_dt > $expires_at) {
        error_log("OTP expired for user_id $user_id | Timestamp: " . date('Y-m-d H:i:s'));
        $_SESSION['fresh_redirect'] = true;
        $_SESSION['error_message'] = "OTP has expired. Please request a new verification code.";
        unset($_SESSION[$attempt_key]);
        header("Location: verify_otp.php");
        exit();
    }

    // SECURITY: Verify OTP using password_verify (compare against hash)
    if (!password_verify($otp, $otp_data['otp_hash'])) {
        $remaining_attempts = 5 - count($_SESSION[$attempt_key]);
        error_log("Invalid OTP attempt for user_id $user_id | Attempt: " . count($_SESSION[$attempt_key]) . " | IP: {$_SERVER['REMOTE_ADDR']} | Timestamp: " . date('Y-m-d H:i:s'));
        $_SESSION['fresh_redirect'] = true;
        $_SESSION['error_message'] = "Invalid verification code. " . $remaining_attempts . " attempt" . ($remaining_attempts != 1 ? 's' : '') . " remaining.";
        header("Location: verify_otp.php");
        exit();
    }

    // SECURITY: Clear attempt counter on successful verification
    unset($_SESSION[$attempt_key]);

    // Activate user account
    $update_query = "UPDATE users1 SET is_active = 1 WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    if ($stmt === false) {
        error_log("Prepare failed for update query: " . $conn->error . " | Timestamp: " . date('Y-m-d H:i:s'));
        $_SESSION['fresh_redirect'] = true;
        $_SESSION['error_message'] = "An internal error occurred. Please try again later.";
        header("Location: verify_otp.php");
        exit();
    }
    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        error_log("Failed to activate user_id $user_id: " . $conn->error . " | Timestamp: " . date('Y-m-d H:i:s'));
        $_SESSION['fresh_redirect'] = true;
        $_SESSION['error_message'] = "Failed to activate account. Please try again.";
        $stmt->close();
        header("Location: verify_otp.php");
        exit();
    }
    $stmt->close();

    // Delete OTP after successful verification
    $delete_otp_query = "DELETE FROM otps WHERE user_id = ?";
    $stmt = $conn->prepare($delete_otp_query);
    if ($stmt === false) {
        error_log("Prepare failed for OTP deletion: " . $conn->error . " | Timestamp: " . date('Y-m-d H:i:s'));
    } else {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
    }

    // Prepare registration details for confirmation email
    $registrationDetails = [
        'Account Email' => $email,
        'Status' => '✓ Verified & Active',
        'Member Since' => date('F j, Y')
    ];

    // Send welcome / activation email (use centralized EmailSender)
    $full_name = trim("{$user['first_name']} " . ($user['middle_name'] ? "{$user['middle_name']} " : '') . "{$user['last_name']}" . ($user['name_extension'] ? " {$user['name_extension']}" : ''));
    try {
        // Send activation confirmation email with detailed information
        $activationDate = date('F j, Y \a\t g:i A');
        $emailSent = EmailSender::sendAccountActivationConfirmation($email, $full_name, $activationDate, $registrationDetails);
        if (!$emailSent) {
            error_log("Account activation confirmation email failed to send to {$email} for user_id {$user_id} | Timestamp: " . date('Y-m-d H:i:s'));
        } else {
            error_log("Account activation confirmation sent successfully to {$email} for user_id {$user_id} | Timestamp: " . date('Y-m-d H:i:s'));
        }
    } catch (Exception $e) {
        error_log("Exception when sending account activation confirmation to {$email}: " . $e->getMessage());
    }

    // Clear session
    unset($_SESSION['otp_email']);
    unset($_SESSION[$last_attempt_key]);
    $_SESSION['fresh_redirect'] = true;
    $_SESSION['activation_success'] = true;

    $conn->close();
    header("Location: activation_success.php");
    exit();
}

// If not POST, show the form as normal
if (!isset($_SESSION['otp_email'])) {
    $_SESSION['error_message'] = "No OTP session found. Please register first.";
    header("Location: registration.php?step=1");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CYCLOAN - OTP Verification</title>
    <link rel="stylesheet" href="CSS/otp.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>

<body>
    <div class="otp-container">
        <!-- Logo/Icon Section -->
        <div class="otp-icon">
            <i class="bi bi-shield-check"></i>
        </div>

        <h2>Verify Your Email</h2>

        <p class="otp-instruction">
            We've sent a 6-digit verification code to<br>
            <strong class="email-display"><?php echo htmlspecialchars($_SESSION['otp_email']); ?></strong>
        </p>

        <form id="otpForm" action="verify_otp.php" method="POST">
            <!-- OTP Input Boxes -->
            <div class="otp-input-container">
                <input type="text" class="otp-box" maxlength="1" pattern="\d" inputmode="numeric" autocomplete="off"
                    data-index="0">
                <input type="text" class="otp-box" maxlength="1" pattern="\d" inputmode="numeric" autocomplete="off"
                    data-index="1">
                <input type="text" class="otp-box" maxlength="1" pattern="\d" inputmode="numeric" autocomplete="off"
                    data-index="2">
                <input type="text" class="otp-box" maxlength="1" pattern="\d" inputmode="numeric" autocomplete="off"
                    data-index="3">
                <input type="text" class="otp-box" maxlength="1" pattern="\d" inputmode="numeric" autocomplete="off"
                    data-index="4">
                <input type="text" class="otp-box" maxlength="1" pattern="\d" inputmode="numeric" autocomplete="off"
                    data-index="5">
            </div>

            <!-- Hidden input to store combined OTP -->
            <input type="hidden" name="otp" id="otp" required>

            <!-- Error message display -->
            <div id="otpError" class="error-message"></div>

            <!-- Timer Display -->
            <div class="timer-container">
                <p id="timerText" class="timer-text">
                    Code expires in: <span id="timer" class="timer-countdown">10:00</span>
                </p>
            </div>

            <!-- Buttons -->
            <div class="button-container">
                <button type="submit" class="btn btn-verify" id="verifyBtn">
                    <i class="bi bi-check-circle me-2"></i>Verify Code
                </button>

                <button type="button" class="btn btn-resend" id="resendBtn" disabled>
                    <i class="bi bi-arrow-clockwise me-2"></i>Resend Code (<span id="resendTimer">60</span>s)
                </button>
            </div>

            <!-- Additional Links -->
            <div class="additional-links">
                <a href="registration.php?step=1" class="link-back">
                    <i class="bi bi-arrow-left me-1"></i>Back to Registration
                </a>
                <span class="link-separator">|</span>
                <a href="index.php" class="link-login">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Go to Login
                </a>
            </div>
        </form>

        <!-- Help Text -->
        <div class="help-section">
            <p class="help-text">
                <i class="bi bi-info-circle me-1"></i>
                Didn't receive the code? Check your spam folder or click Resend Code.
            </p>
        </div>
    </div>

    <!-- Bootstrap Modal for Messages -->
    <div class="modal fade" id="messageModal" tabindex="-1" aria-labelledby="messageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" id="modalHeader">
                    <h5 class="modal-title" id="messageModalLabel">
                        <i class="modal-icon"></i>
                        <span id="modalTitleText"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body" id="messageModalBody"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-modal-close" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="JAVASCRIPT/otp.js"></script>
    <script>
        // Handle message display on page load
        document.addEventListener('DOMContentLoaded', function () {
            <?php if (isset($_SESSION['success_message'])): ?>
                showMessageModal('Success', '<?php echo addslashes(htmlspecialchars($_SESSION['success_message'])); ?>', 'success');
                <?php unset($_SESSION['success_message']); ?>
            <?php elseif (isset($_SESSION['error_message'])): ?>
                showMessageModal('Error', '<?php echo addslashes(htmlspecialchars($_SESSION['error_message'])); ?>', 'error');
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>
        });
    </script>
</body>

</html>