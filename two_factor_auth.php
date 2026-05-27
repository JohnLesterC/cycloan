<?php
/**
 * CYCLOAN Two-Factor Authentication (2FA) System
 * 
 * Provides 2FA support with multiple methods:
 * - Email (OTP via email)
 * - SMS (OTP via SMS)
 * - TOTP (Time-based One-Time Password with authenticator apps)
 * 
 * Usage:
 * require_once 'two_factor_auth.php';
 * $twofa = new TwoFactorAuth();
 * 
 * // Generate 2FA challenge
 * $challenge_id = $twofa->generateChallenge($user_id, 'email');
 * 
 * // Verify 2FA response
 * if ($twofa->verifyChallenge($user_id, $code, $challenge_id)) {
 *     // 2FA passed
 * }
 * 
 * @version 1.0
 * @date November 10, 2025
 */

require_once 'env_config.php';
require_once 'rate_limiter.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class TwoFactorAuth
{
    private $conn;
    private $rate_limiter;

    /**
     * Initialize 2FA System
     */
    public function __construct(mysqli $conn = null)
    {
        global $conn;
        $this->conn = $conn;
        $this->rate_limiter = new RateLimiter();

        // Create 2FA tables if they don't exist
        $this->createTables();
    }

    /**
     * Create necessary database tables for 2FA
     */
    private function createTables()
    {
        $tables = [
            // Two-factor authentication methods
            "CREATE TABLE IF NOT EXISTS `two_factor_methods` (
                `id` INT PRIMARY KEY AUTO_INCREMENT,
                `user_id` INT NOT NULL UNIQUE,
                `method` ENUM('email', 'sms', 'totp') NOT NULL DEFAULT 'email',
                `phone_number` VARCHAR(20),
                `totp_secret` VARCHAR(32),
                `is_enabled` BOOLEAN DEFAULT TRUE,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users1(id) ON DELETE CASCADE
            )",

            // 2FA challenges (OTP records)
            "CREATE TABLE IF NOT EXISTS `two_factor_challenges` (
                `id` INT PRIMARY KEY AUTO_INCREMENT,
                `user_id` INT NOT NULL,
                `challenge_id` VARCHAR(64) NOT NULL UNIQUE,
                `method` ENUM('email', 'sms', 'totp') NOT NULL,
                `code_hash` VARCHAR(255),
                `verified` BOOLEAN DEFAULT FALSE,
                `attempt_count` INT DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `expires_at` TIMESTAMP,
                `verified_at` TIMESTAMP NULL,
                FOREIGN KEY (user_id) REFERENCES users1(id) ON DELETE CASCADE,
                INDEX idx_challenge_id (challenge_id),
                INDEX idx_user_expires (user_id, expires_at)
            )",

            // 2FA backup codes
            "CREATE TABLE IF NOT EXISTS `two_factor_backup_codes` (
                `id` INT PRIMARY KEY AUTO_INCREMENT,
                `user_id` INT NOT NULL,
                `code_hash` VARCHAR(255) NOT NULL,
                `used` BOOLEAN DEFAULT FALSE,
                `used_at` TIMESTAMP NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users1(id) ON DELETE CASCADE,
                INDEX idx_user_id (user_id)
            )",
        ];

        foreach ($tables as $table_sql) {
            if ($this->conn->query($table_sql) === false) {
                error_log("Error creating table: " . $this->conn->error);
            }
        }
    }

    /**
     * Enable 2FA for a user
     * 
     * @param int $user_id User ID
     * @param string $method 2FA method (email, sms, totp)
     * @param string|null $phone_number Phone number for SMS method
     * @return bool Success status
     */
    public function enableTwoFactorAuth(int $user_id, string $method, string $phone_number = null): bool
    {
        // Validate method
        $allowed_methods = ['email', 'sms', 'totp'];
        if (!in_array($method, $allowed_methods)) {
            return false;
        }

        // Generate TOTP secret if needed
        $totp_secret = $method === 'totp' ? $this->generateTOTPSecret() : null;

        $stmt = $this->conn->prepare(
            "INSERT INTO two_factor_methods (user_id, method, phone_number, totp_secret, is_enabled)
             VALUES (?, ?, ?, ?, TRUE)
             ON DUPLICATE KEY UPDATE 
             method = VALUES(method), 
             phone_number = VALUES(phone_number),
             totp_secret = VALUES(totp_secret),
             is_enabled = TRUE"
        );

        $stmt->bind_param("isss", $user_id, $method, $phone_number, $totp_secret);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }

    /**
     * Disable 2FA for a user
     * 
     * @param int $user_id User ID
     * @return bool Success status
     */
    public function disableTwoFactorAuth(int $user_id): bool
    {
        $stmt = $this->conn->prepare(
            "UPDATE two_factor_methods SET is_enabled = FALSE WHERE user_id = ?"
        );
        $stmt->bind_param("i", $user_id);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }

    /**
     * Check if user has 2FA enabled
     * 
     * @param int $user_id User ID
     * @return bool Whether 2FA is enabled
     */
    public function isTwoFactorEnabled(int $user_id): bool
    {
        $stmt = $this->conn->prepare(
            "SELECT is_enabled FROM two_factor_methods WHERE user_id = ? AND is_enabled = TRUE LIMIT 1"
        );
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        return $result->num_rows > 0;
    }

    /**
     * Generate 2FA challenge (send OTP/code)
     * 
     * @param int $user_id User ID
     * @param string|null $email User email
     * @param string|null $method Override method (email, sms, totp)
     * @return string|null Challenge ID or null on failure
     */
    public function generateChallenge(int $user_id, string $email = null, string $method = null): ?string
    {
        // Check rate limit
        $identifier = $email ?? (string) $user_id;
        if (!$this->rate_limiter->checkLimit('otp_request', $identifier)) {
            error_log("Rate limit exceeded for user $user_id (2FA challenge)");
            return null;
        }

        // Get user's 2FA method
        if (!$method) {
            $stmt = $this->conn->prepare(
                "SELECT method FROM two_factor_methods WHERE user_id = ? AND is_enabled = TRUE LIMIT 1"
            );
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();

            if ($result->num_rows === 0) {
                return null; // 2FA not enabled
            }

            $row = $result->fetch_assoc();
            $method = $row['method'];
        }

        // Generate challenge
        $challenge_id = bin2hex(random_bytes(32));
        $code = $this->generateOTP();
        $code_hash = password_hash($code, PASSWORD_BCRYPT, ['cost' => 12]);
        $expires_at = date('Y-m-d H:i:s', time() + (OTP_EXPIRATION_MINUTES * 60));

        // Store challenge
        $stmt = $this->conn->prepare(
            "INSERT INTO two_factor_challenges 
             (user_id, challenge_id, method, code_hash, expires_at)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("isss", $user_id, $challenge_id, $method, $code_hash, $expires_at);

        if (!$stmt->execute()) {
            error_log("Failed to store 2FA challenge: " . $this->conn->error);
            $stmt->close();
            return null;
        }
        $stmt->close();

        // Send code based on method
        switch ($method) {
            case 'email':
                if ($email) {
                    $this->sendEmailChallenge($email, $code);
                }
                break;
            case 'sms':
                $this->sendSmsChallenge($user_id, $code);
                break;
            case 'totp':
                // TOTP doesn't require sending, user generates from app
                break;
        }

        // Record attempt
        $this->rate_limiter->recordAttempt('otp_request', $identifier);

        return $challenge_id;
    }

    /**
     * Verify 2FA challenge
     * 
     * @param int $user_id User ID
     * @param string $code OTP/2FA code
     * @param string $challenge_id Challenge ID
     * @return bool Whether verification passed
     */
    public function verifyChallenge(int $user_id, string $code, string $challenge_id): bool
    {
        // Check rate limit
        if (!$this->rate_limiter->checkLimit('otp_verification', (string) $user_id)) {
            error_log("Rate limit exceeded for user $user_id (2FA verification)");
            return false;
        }

        // Fetch challenge
        $stmt = $this->conn->prepare(
            "SELECT code_hash, expires_at, verified, attempt_count 
             FROM two_factor_challenges 
             WHERE user_id = ? AND challenge_id = ? AND verified = FALSE"
        );
        $stmt->bind_param("is", $user_id, $challenge_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        if ($result->num_rows === 0) {
            return false; // Challenge not found or already verified
        }

        $challenge = $result->fetch_assoc();

        // Check expiration
        if (strtotime($challenge['expires_at']) < time()) {
            return false; // Challenge expired
        }

        // Check attempts
        if ($challenge['attempt_count'] >= OTP_MAX_ATTEMPTS) {
            return false; // Too many attempts
        }

        // Verify code
        if (!password_verify($code, $challenge['code_hash'])) {
            // Increment attempts
            $stmt = $this->conn->prepare(
                "UPDATE two_factor_challenges SET attempt_count = attempt_count + 1 WHERE challenge_id = ?"
            );
            $stmt->bind_param("s", $challenge_id);
            $stmt->execute();
            $stmt->close();

            $this->rate_limiter->recordAttempt('otp_verification', (string) $user_id);
            return false; // Code mismatch
        }

        // Mark as verified
        $verified_at = date('Y-m-d H:i:s');
        $stmt = $this->conn->prepare(
            "UPDATE two_factor_challenges SET verified = TRUE, verified_at = ? WHERE challenge_id = ?"
        );
        $stmt->bind_param("ss", $verified_at, $challenge_id);
        $result = $stmt->execute();
        $stmt->close();

        // Reset rate limit on success
        $this->rate_limiter->reset('otp_verification', (string) $user_id);

        return $result;
    }

    /**
     * Generate backup codes for user
     * 
     * @param int $user_id User ID
     * @param int $count Number of codes to generate (default: 10)
     * @return array Generated codes
     */
    public function generateBackupCodes(int $user_id, int $count = 10): array
    {
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $code = strtoupper(bin2hex(random_bytes(4))); // 8-character code
            $code_hash = password_hash($code, PASSWORD_BCRYPT, ['cost' => 12]);

            $stmt = $this->conn->prepare(
                "INSERT INTO two_factor_backup_codes (user_id, code_hash) VALUES (?, ?)"
            );
            $stmt->bind_param("is", $user_id, $code_hash);
            $stmt->execute();
            $stmt->close();

            $codes[] = $code;
        }

        return $codes;
    }

    /**
     * Verify backup code
     * 
     * @param int $user_id User ID
     * @param string $code Backup code
     * @return bool Whether backup code is valid
     */
    public function verifyBackupCode(int $user_id, string $code): bool
    {
        // Fetch unused backup codes
        $stmt = $this->conn->prepare(
            "SELECT id, code_hash FROM two_factor_backup_codes WHERE user_id = ? AND used = FALSE LIMIT 10"
        );
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        while ($row = $result->fetch_assoc()) {
            if (password_verify($code, $row['code_hash'])) {
                // Mark as used
                $stmt = $this->conn->prepare(
                    "UPDATE two_factor_backup_codes SET used = TRUE, used_at = NOW() WHERE id = ?"
                );
                $stmt->bind_param("i", $row['id']);
                $stmt->execute();
                $stmt->close();

                return true;
            }
        }

        return false;
    }

    /**
     * Send email challenge
     * 
     * @param string $email User email
     * @param string $code OTP code
     */
    private function sendEmailChallenge(string $email, string $code): void
    {
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = MAIL_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = MAIL_USERNAME;
            $mail->Password = MAIL_PASSWORD;
            $mail->SMTPSecure = MAIL_ENCRYPTION === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = MAIL_PORT;

            $mail->setFrom(MAIL_USERNAME, MAIL_FROM_NAME);
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = 'Your CYCLOAN Two-Factor Authentication Code';
            $mail->Body = $this->getEmailTemplate('2fa_challenge', ['code' => $code]);

            $mail->send();
        } catch (Exception $e) {
            error_log("Failed to send 2FA email: {$mail->ErrorInfo}");
        }
    }

    /**
     * Send SMS challenge
     * 
     * @param int $user_id User ID
     * @param string $code OTP code
     */
    private function sendSmsChallenge(int $user_id, string $code): void
    {
        // Get user's phone number
        $stmt = $this->conn->prepare(
            "SELECT phone_number FROM two_factor_methods WHERE user_id = ?"
        );
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        if ($result->num_rows === 0) {
            return;
        }

        $row = $result->fetch_assoc();
        $phone = $row['phone_number'];

        // TODO: Integrate with SMS service provider (Twilio, AWS SNS, etc.)
        error_log("SMS Challenge sent to $phone: $code");
    }

    /**
     * Generate OTP
     * 
     * @return string Generated OTP
     */
    private function generateOTP(): string
    {
        $randomBytes = random_bytes(3);
        $randomInt = abs((int) bindec(implode('', array_map(fn($b) => sprintf('%08b', ord($b)), str_split($randomBytes)))));
        return str_pad($randomInt % 1000000, OTP_LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * Generate TOTP secret
     * 
     * @return string Base32 encoded secret
     */
    private function generateTOTPSecret(): string
    {
        // Generate 16 random bytes
        $secret = random_bytes(16);

        // Base32 encode
        return $this->base32Encode($secret);
    }

    /**
     * Base32 encode (for TOTP QR codes)
     * 
     * @param string $input Input string
     * @return string Base32 encoded string
     */
    private function base32Encode(string $input): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $encoded = '';
        $bit_buffer = 0;
        $bit_count = 0;

        for ($i = 0; $i < strlen($input); $i++) {
            $bit_buffer = ($bit_buffer << 8) | ord($input[$i]);
            $bit_count += 8;

            while ($bit_count >= 5) {
                $bit_count -= 5;
                $encoded .= $alphabet[($bit_buffer >> $bit_count) & 31];
            }
        }

        if ($bit_count > 0) {
            $encoded .= $alphabet[($bit_buffer << (5 - $bit_count)) & 31];
        }

        // Pad with = signs
        while (strlen($encoded) % 8 !== 0) {
            $encoded .= '=';
        }

        return $encoded;
    }

    /**
     * Get email template
     * 
     * @param string $template Template name
     * @param array $data Template data
     * @return string HTML email body
     */
    private function getEmailTemplate(string $template, array $data = []): string
    {
        if ($template === '2fa_challenge') {
            return "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <style>
                        body { font-family: Arial, sans-serif; background-color: #f4f4f4; }
                        .container { max-width: 600px; margin: 0 auto; background-color: #fff; padding: 20px; border-radius: 8px; }
                        .header { color: #2d7d32; font-size: 24px; margin-bottom: 20px; }
                        .code-box { background-color: #f0f0f0; padding: 15px; border-radius: 5px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #2d7d32; }
                        .message { color: #555; line-height: 1.6; margin-top: 20px; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>🔐 Two-Factor Authentication</div>
                        <p class='message'>Your CYCLOAN verification code is:</p>
                        <div class='code-box'>" . htmlspecialchars($data['code'] ?? '') . "</div>
                        <p class='message'>This code expires in " . OTP_EXPIRATION_MINUTES . " minutes.</p>
                        <p class='message'><strong>Do not share this code with anyone.</strong></p>
                    </div>
                </body>
                </html>
            ";
        }

        return '';
    }
}

?>