-- CYCLOAN Two-Factor Authentication Database Schema
-- Run these SQL statements to create the necessary tables
-- These tables are auto-created by TwoFactorAuth class, but you can run manually too

-- ============================================================================
-- TABLE: two_factor_methods
-- Purpose: Store 2FA settings for each user
-- ============================================================================

CREATE TABLE IF NOT EXISTS `two_factor_methods` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL UNIQUE,
    `method` ENUM('email', 'sms', 'totp') NOT NULL DEFAULT 'email',
    `phone_number` VARCHAR(20),
    `totp_secret` VARCHAR(32),
    `is_enabled` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users1(id) ON DELETE CASCADE,
    INDEX idx_enabled (is_enabled),
    INDEX idx_method (method)
);

-- ============================================================================
-- TABLE: two_factor_challenges
-- Purpose: Store OTP/2FA codes for verification
-- ============================================================================

CREATE TABLE IF NOT EXISTS `two_factor_challenges` (
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
    INDEX idx_user_expires (user_id, expires_at),
    INDEX idx_verified (verified)
);

-- ============================================================================
-- TABLE: two_factor_backup_codes
-- Purpose: Store backup codes for account recovery
-- ============================================================================

CREATE TABLE IF NOT EXISTS `two_factor_backup_codes` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `code_hash` VARCHAR(255) NOT NULL,
    `used` BOOLEAN DEFAULT FALSE,
    `used_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users1(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_used (used)
);

-- ============================================================================
-- SAMPLE QUERIES FOR TESTING
-- ============================================================================

-- View 2FA settings for a user
-- SELECT * FROM two_factor_methods WHERE user_id = 1;

-- View active challenges for a user
-- SELECT * FROM two_factor_challenges WHERE user_id = 1 AND verified = FALSE;

-- View backup codes (unused only)
-- SELECT * FROM two_factor_backup_codes WHERE user_id = 1 AND used = FALSE;

-- Count users with 2FA enabled
-- SELECT COUNT(*) as users_with_2fa FROM two_factor_methods WHERE is_enabled = TRUE;

-- Find expired challenges
-- SELECT * FROM two_factor_challenges WHERE expires_at < NOW();

-- ============================================================================
-- CLEANUP QUERIES (Use Carefully!)
-- ============================================================================

-- Delete expired challenges
-- DELETE FROM two_factor_challenges WHERE expires_at < NOW();

-- Delete all used backup codes
-- DELETE FROM two_factor_backup_codes WHERE used = TRUE AND used_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- Reset 2FA for specific user (admin action)
-- UPDATE two_factor_methods SET is_enabled = FALSE WHERE user_id = 1;

-- Delete all 2FA data for user (user deletion)
-- DELETE FROM two_factor_methods WHERE user_id = 1;
-- DELETE FROM two_factor_challenges WHERE user_id = 1;
-- DELETE FROM two_factor_backup_codes WHERE user_id = 1;
