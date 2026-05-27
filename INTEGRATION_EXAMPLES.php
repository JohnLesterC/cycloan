<?php
/**
 * CYCLOAN Integration Examples
 * 
 * This file demonstrates how to integrate:
 * 1. Environment Variables
 * 2. Rate Limiting
 * 3. Two-Factor Authentication
 * 
 * Into your existing registration flow.
 * 
 * @version 1.0
 * @date November 10, 2025
 */

// ============================================================================
// EXAMPLE 1: Using Environment Variables in process_registration.php
// ============================================================================

/*
// At the TOP of process_registration.php, add:

require_once 'env_config.php';              // Load environment variables
require_once 'rate_limiter.php';            // Load rate limiter
require_once 'two_factor_auth.php';         // Load 2FA
require_once 'security_validation.php';     // Your existing validation
require_once 'CYCLOAN_db.php';              // Your DB connection

// Initialize services
$rate_limiter = new RateLimiter();
$twofa = new TwoFactorAuth($conn);

// Get user's IP address
$user_ip = $_SERVER['REMOTE_ADDR'];

// OLD WAY (DON'T USE):
// $mail->Username = 'cycloancldd@gmail.com';
// $mail->Password = 'hbfh ukgh tmzw nqbq';

// NEW WAY (USE THIS):
$mail->Username = MAIL_USERNAME;            // From .env file
$mail->Password = MAIL_PASSWORD;            // From .env file
$mail->Host = MAIL_HOST;                    // From .env file
$mail->Port = MAIL_PORT;                    // From .env file
*/

// ============================================================================
// EXAMPLE 2: Rate Limiting in Registration
// ============================================================================

/*
// In process_registration.php, add after POST validation:

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_ip = $_SERVER['REMOTE_ADDR'];

    // Check if registration limit is exceeded
    if (!$rate_limiter->checkLimit('registration', $user_ip)) {
        $remaining_time = $rate_limiter->getTimeUntilReset('registration', $user_ip);

        http_response_code(429);  // Too Many Requests
        die(json_encode([
            'success' => false,
            'message' => "Too many registration attempts. Please try again in $remaining_time seconds.",
            'retry_after' => $remaining_time,
            'status' => 'rate_limited'
        ]));
    }

    // ... rest of registration code ...

    // Only record attempt if registration was actually submitted
    if ($current_step == $max_steps) {
        $rate_limiter->recordAttempt('registration', $user_ip);
    }
}
*/

// ============================================================================
// EXAMPLE 3: Rate Limiting for OTP Generation
// ============================================================================

/*
// In process_registration.php, before sending OTP:

// Check OTP request rate limit
if (!$rate_limiter->checkLimit('otp_request', $form_data['email'])) {
    $remaining_time = $rate_limiter->getTimeUntilReset('otp_request', $form_data['email']);

    error_log("OTP rate limit exceeded for email: {$form_data['email']}");
    $_SESSION['error_message'] = 
        "Too many OTP requests from this email. Please try again in $remaining_time seconds.";
    $_SESSION['fresh_redirect'] = true;
    header("Location: registration.php?step=$max_steps");
    exit();
}

// Generate and send OTP
$otp = generateOTP();
$otp_hash = hashOTP($otp);
// ... send OTP email ...
if (sendOTPEmail($form_data['email'], $full_name, $otp)) {
    // Record the OTP request attempt
    $rate_limiter->recordAttempt('otp_request', $form_data['email']);
    $_SESSION['otp_email'] = $form_data['email'];
}
*/

// ============================================================================
// EXAMPLE 4: Rate Limiting for OTP Verification
// ============================================================================

/*
// In verify_otp.php, when verifying OTP code:

require_once 'env_config.php';
require_once 'rate_limiter.php';
require_once 'CYCLOAN_db.php';

$rate_limiter = new RateLimiter();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $otp_code = $_POST['otp_code'] ?? '';
    $email = $_SESSION['otp_email'] ?? '';

    // Check verification rate limit
    if (!$rate_limiter->checkLimit('otp_verification', $email)) {
        $remaining_time = $rate_limiter->getTimeUntilReset('otp_verification', $email);

        $_SESSION['error_message'] = 
            "Too many verification attempts. Please try again in $remaining_time seconds.";
        header("Location: verify_otp.php");
        exit();
    }

    // Verify OTP...
    if (verifyOTP($email, $otp_code)) {
        // Success - reset rate limit
        $rate_limiter->reset('otp_verification', $email);
        // ... activate account ...
    } else {
        // Failed - record attempt
        $rate_limiter->recordAttempt('otp_verification', $email);

        $remaining = $rate_limiter->getRemainingAttempts('otp_verification', $email);
        $_SESSION['error_message'] = 
            "Invalid OTP. You have $remaining attempts remaining.";
    }
}
*/

// ============================================================================
// EXAMPLE 5: Enabling 2FA After Registration
// ============================================================================

/*
// In process_registration.php, after user creation:

try {
    // ... existing user creation code ...

    $user_id = $stmt->insert_id;  // Get the new user's ID

    // Enable 2FA (email method) for the new user
    $twofa->enableTwoFactorAuth($user_id, 'email');

    // ... continue with existing code ...

} catch (Exception $e) {
    error_log("Registration error: " . $e->getMessage());
}
*/

// ============================================================================
// EXAMPLE 6: Sending 2FA Code After Registration
// ============================================================================

/*
// In process_registration.php, when redirecting to OTP verification:

// Generate 2FA challenge for the new user
$challenge_id = $twofa->generateChallenge($user_id, $form_data['email']);

if (!$challenge_id) {
    error_log("Failed to generate 2FA challenge for user $user_id");
    $_SESSION['error_message'] = "Could not send verification code. Please try again.";
    header("Location: registration.php?step=$max_steps");
    exit();
}

// Store challenge ID in session for verification
$_SESSION['2fa_challenge_id'] = $challenge_id;
$_SESSION['user_id_for_2fa'] = $user_id;

// Store email in session for OTP verification
$_SESSION['otp_email'] = $form_data['email'];

// Redirect to OTP verification page
$_SESSION['success_message'] = "Registration complete! We've sent a verification code to your email.";
$_SESSION['fresh_redirect'] = true;
header("Location: verify_otp.php");
exit();
*/

// ============================================================================
// EXAMPLE 7: Verifying 2FA Code
// ============================================================================

/*
// In verify_otp.php, when verifying the code:

require_once 'env_config.php';
require_once 'two_factor_auth.php';
require_once 'rate_limiter.php';
require_once 'CYCLOAN_db.php';

$twofa = new TwoFactorAuth($conn);
$rate_limiter = new RateLimiter();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $code = $_POST['otp_code'] ?? '';
    $challenge_id = $_SESSION['2fa_challenge_id'] ?? '';
    $user_id = $_SESSION['user_id_for_2fa'] ?? '';
    $email = $_SESSION['otp_email'] ?? '';

    // Verify the 2FA code
    if ($twofa->verifyChallenge($user_id, $code, $challenge_id)) {
        // Verification successful

        // Activate user account
        $stmt = $conn->prepare("UPDATE users1 SET is_active = 1 WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        // Clear session data
        unset($_SESSION['2fa_challenge_id']);
        unset($_SESSION['user_id_for_2fa']);
        unset($_SESSION['otp_email']);

        $_SESSION['success_message'] = "Email verified! Your account is now active. Please log in.";
        header("Location: index.php");
        exit();
    } else {
        // Verification failed - show remaining attempts
        $remaining = $rate_limiter->getRemainingAttempts('otp_verification', $email);
        $_SESSION['error_message'] = "Invalid code. You have $remaining attempts remaining.";
        header("Location: verify_otp.php");
        exit();
    }
}
*/

// ============================================================================
// EXAMPLE 8: Using Environment Variables in Email Configuration
// ============================================================================

/*
// OLD WAY (in process_registration.php):
function sendOTPEmail($to, $name, $otp) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'cycloancldd@gmail.com';              // ❌ Hardcoded
        $mail->Password = 'hbfh ukgh tmzw nqbq';             // ❌ Hardcoded
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        // ...
    }
}

// NEW WAY (using environment variables):
function sendOTPEmail($to, $name, $otp) {
    // Credentials loaded from .env via env_config.php
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;                             // ✅ From .env
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;                     // ✅ From .env
        $mail->Password = MAIL_PASSWORD;                     // ✅ From .env
        $mail->SMTPSecure = MAIL_ENCRYPTION === 'tls' 
            ? PHPMailer::ENCRYPTION_STARTTLS 
            : PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = MAIL_PORT;                             // ✅ From .env
        // ...
    }
}
*/

// ============================================================================
// EXAMPLE 9: Admin Panel - View Rate Limit Status
// ============================================================================

/*
// In admin dashboard:

require_once 'rate_limiter.php';

$rate_limiter = new RateLimiter();

// Get statistics for monitoring
$user_ip = '192.168.1.100';  // Example IP
$email = 'user@example.com';  // Example email

$registration_remaining = $rate_limiter->getRemainingAttempts('registration', $user_ip);
$registration_reset = $rate_limiter->getTimeUntilReset('registration', $user_ip);

$otp_remaining = $rate_limiter->getRemainingAttempts('otp_request', $email);
$otp_reset = $rate_limiter->getTimeUntilReset('otp_request', $email);

echo "IP: $user_ip";
echo "- Registration attempts remaining: $registration_remaining";
echo "- Reset in: $registration_reset seconds";
echo "";
echo "Email: $email";
echo "- OTP requests remaining: $otp_remaining";
echo "- Reset in: $otp_reset seconds";
*/

// ============================================================================
// EXAMPLE 10: Admin Panel - Reset Rate Limits
// ============================================================================

/*
// In admin dashboard (require admin authentication):

require_once 'rate_limiter.php';

$rate_limiter = new RateLimiter();

if ($_POST['action'] === 'reset_rate_limit') {
    $identifier = $_POST['identifier'];  // IP or email
    $action_type = $_POST['action_type'];  // registration, otp_request, otp_verification

    // Only admins can reset
    if ($_SESSION['role'] === 'superadmin') {
        $rate_limiter->reset($action_type, $identifier);
        $_SESSION['message'] = "Rate limit reset for $identifier";
    }
}
*/

// ============================================================================
// EXAMPLE 11: Check 2FA Status
// ============================================================================

/*
// In user profile/settings:

require_once 'two_factor_auth.php';
require_once 'CYCLOAN_db.php';

$twofa = new TwoFactorAuth($conn);
$user_id = $_SESSION['user_id'];

if ($twofa->isTwoFactorEnabled($user_id)) {
    echo "Two-Factor Authentication: ENABLED ✓";
} else {
    echo "Two-Factor Authentication: DISABLED";
    echo "Enable it for added security";
}
*/

// ============================================================================
// EXAMPLE 12: Generate Backup Codes
// ============================================================================

/*
// In settings page - allow users to generate backup codes:

require_once 'two_factor_auth.php';
require_once 'CYCLOAN_db.php';

$twofa = new TwoFactorAuth($conn);
$user_id = $_SESSION['user_id'];

if ($_POST['action'] === 'generate_backup_codes') {
    $codes = $twofa->generateBackupCodes($user_id, 10);

    // Display codes to user - they must download/print them
    foreach ($codes as $code) {
        echo "<li>$code</li>";
    }

    echo "Save these codes in a safe place. You can use them to regain access if you lose your 2FA device.";
}
*/

// ============================================================================
// COMPLETE REGISTRATION FLOW WITH ALL FEATURES
// ============================================================================

/*
// Complete example in process_registration.php:

<?php
session_start();

// 1. LOAD ENVIRONMENT & SECURITY LIBRARIES
require_once 'env_config.php';
require_once 'rate_limiter.php';
require_once 'two_factor_auth.php';
require_once 'security_validation.php';
require_once 'CYCLOAN_db.php';

use PHPMailer\PHPMailer\PHPMailer;

// 2. INITIALIZE SERVICES
$rate_limiter = new RateLimiter();
$twofa = new TwoFactorAuth($conn);
$user_ip = $_SERVER['REMOTE_ADDR'];

// 3. POST REQUEST HANDLING
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 4. CHECK RATE LIMIT
    if (!$rate_limiter->checkLimit('registration', $user_ip)) {
        $remaining_time = $rate_limiter->getTimeUntilReset('registration', $user_ip);
        http_response_code(429);
        die(json_encode(['success' => false, 'message' => "Rate limited. Try again in $remaining_time seconds."]));
    }

    // 5. VALIDATE FORM DATA
    // ... existing validation code ...

    // 6. CREATE USER ACCOUNT
    try {
        $hashed_password = password_hash($form_data['password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users1 (...) VALUES (...)");
        $stmt->bind_param("...", ...);
        $stmt->execute();
        $user_id = $stmt->insert_id;
        $stmt->close();

        // 7. ENABLE 2FA
        $twofa->enableTwoFactorAuth($user_id, 'email');

        // 8. GENERATE 2FA CHALLENGE
        $challenge_id = $twofa->generateChallenge($user_id, $form_data['email']);

        // 9. STORE IN SESSION
        $_SESSION['2fa_challenge_id'] = $challenge_id;
        $_SESSION['user_id_for_2fa'] = $user_id;
        $_SESSION['otp_email'] = $form_data['email'];

        // 10. RECORD RATE LIMIT ATTEMPT
        $rate_limiter->recordAttempt('registration', $user_ip);

        // 11. REDIRECT TO VERIFICATION
        $_SESSION['success_message'] = "Registration complete! Check your email for verification code.";
        header("Location: verify_otp.php");

    } catch (Exception $e) {
        error_log("Registration error: " . $e->getMessage());
        $_SESSION['error_message'] = "Registration failed. Please try again.";
        header("Location: registration.php?step=1");
    }
}
?>
*/

?>