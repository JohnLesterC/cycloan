-- ========================================
-- CYCLOAN OTP TABLE MIGRATION
-- Security Enhancement: OTP Hashing & Rate Limiting
-- ========================================
-- This migration script updates the otps table to:
-- 1. Replace otp_code (plain text) with otp_hash (bcrypt hash)
-- 2. Add created_at timestamp for attempt tracking
-- 3. Add attempt_count column for rate limiting
-- 4. Add indexes for performance
-- ========================================

-- Step 1: Backup existing OTP data (optional - for recovery if needed)
-- CREATE TABLE otps_backup AS SELECT * FROM otps;

-- Step 2: Drop the existing otps table
-- WARNING: This will delete all existing OTP records
-- Only run this if you're sure you want to delete existing OTPs
-- (Users can request new OTPs via resend_otp.php)
DROP TABLE IF EXISTS otps;

-- Step 3: Create new otps table with enhanced security
CREATE TABLE otps (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  otp_hash VARCHAR(255) NOT NULL COMMENT 'bcrypt hashed OTP - never store plain text',
  attempt_count INT DEFAULT 0 COMMENT 'Track verification attempts for rate limiting',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When OTP was generated',
  expires_at TIMESTAMP NOT NULL COMMENT 'When OTP expires (typically 10 minutes)',
  verified_at TIMESTAMP NULL COMMENT 'When OTP was successfully verified',
  FOREIGN KEY (user_id) REFERENCES users1(id) ON DELETE CASCADE,
  INDEX idx_user_id (user_id),
  INDEX idx_expires_at (expires_at),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='One-Time Passwords for account verification with enhanced security';

-- Step 4: Verify the new structure
DESCRIBE otps;

-- ========================================
-- SECURITY NOTES:
-- ========================================
-- 1. otp_hash stores bcrypt hashes of OTPs, NOT plain text
--    - Even if database is breached, OTPs cannot be used
--    - Verification uses password_verify() for comparison
--
-- 2. attempt_count tracks failed verification attempts
--    - Combined with created_at for 15-minute rate limiting window
--    - Prevents brute force attacks
--
-- 3. created_at and expires_at enable precise timing
--    - Tracks when OTP was generated
--    - Tracks when OTP expires (10 minutes)
--    - Allows cleanup of old OTP records
--
-- 4. verified_at (optional) tracks successful verification
--    - Can be used for audit logging
--    - Helps identify verification patterns
--
-- ========================================
-- ROLLBACK INSTRUCTIONS (if needed):
-- ========================================
-- If you need to rollback this migration:
-- 1. Drop the new otps table: DROP TABLE otps;
-- 2. Rename backup: RENAME TABLE otps_backup TO otps;
-- 3. Or manually restore from your database backup
--
-- ========================================
-- RELATED CODE CHANGES:
-- ========================================
-- These PHP files have been updated:
-- 1. process_registration.php
--    - generateOTP() now uses random_bytes() for crypto randomness
--    - hashOTP() function added - uses password_hash()
--    - INSERT stores otp_hash instead of otp_code
--
-- 2. verify_otp.php
--    - SELECT queries changed to use otp_hash instead of otp_code
--    - password_verify() used for comparison instead of ===
--    - Rate limiting implemented with attempt tracking
--    - 5 attempts per 15-minute window enforced
--
-- ========================================
