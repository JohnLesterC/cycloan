-- ============================================
-- CYCLOAN Notification System Database Setup
-- ============================================
-- This script creates all necessary tables and data
-- for the notification system to function properly.
-- 
-- Required Tables:
-- 1. notification_types - Stores notification type definitions
-- 2. user_notifications - Stores user/admin notifications
--
-- Run this script once to initialize the notification system.
-- ============================================

-- ============================================
-- 1. CREATE NOTIFICATION_TYPES TABLE
-- ============================================
-- Stores all available notification types (success, info, warning, error)

CREATE TABLE IF NOT EXISTS `notification_types` (
  `type_id` INT PRIMARY KEY AUTO_INCREMENT,
  `type_name` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Type: success, info, warning, error',
  `description` VARCHAR(255),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_type_name` (`type_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2. INSERT NOTIFICATION TYPES
-- ============================================
-- Insert the 4 core notification types

INSERT IGNORE INTO `notification_types` (`type_name`, `description`) VALUES
('success', 'Success notifications - positive outcomes'),
('info', 'Information notifications - general updates'),
('warning', 'Warning notifications - caution needed'),
('error', 'Error notifications - problems/failures');

-- ============================================
-- 3. CREATE USER_NOTIFICATIONS TABLE
-- ============================================
-- Stores all notifications for users and admins

CREATE TABLE IF NOT EXISTS `user_notifications` (
  `notification_id` BIGINT PRIMARY KEY AUTO_INCREMENT COMMENT 'Unique notification ID',
  `user_id` INT NOT NULL COMMENT 'FK to users1.id - recipient',
  `user_type` VARCHAR(50) DEFAULT 'user' COMMENT 'Type: user, admin1, admin2, superadmin',
  `type_id` INT COMMENT 'FK to notification_types.type_id',
  `title` VARCHAR(255) NOT NULL COMMENT 'Notification title',
  `message` LONGTEXT NOT NULL COMMENT 'Full notification message',
  `short_message` VARCHAR(500) COMMENT 'Brief summary message',
  `event_type` VARCHAR(100) COMMENT 'Category: loan, payment, document, account, system',
  `priority` VARCHAR(50) DEFAULT 'normal' COMMENT 'Priority: low, normal, high, urgent',
  `action_url` VARCHAR(500) COMMENT 'URL to navigate to when clicked',
  `is_read` BOOLEAN DEFAULT FALSE COMMENT 'Has user read this notification?',
  `read_at` DATETIME COMMENT 'When user read the notification',
  `expires_at` DATETIME COMMENT 'When notification expires (null = no expiry)',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'When notification was created',
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME COMMENT 'Soft delete timestamp',
  
  -- Indexes for performance
  KEY `idx_user_id` (`user_id`),
  KEY `idx_type_id` (`type_id`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_priority` (`priority`),
  KEY `idx_user_read` (`user_id`, `is_read`),
  KEY `idx_user_created` (`user_id`, `created_at`),
  KEY `idx_active_notifications` (`user_id`, `deleted_at`, `is_read`),
  
  -- Foreign key constraints
  CONSTRAINT `fk_user_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users1` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_notifications_type` FOREIGN KEY (`type_id`) REFERENCES `notification_types` (`type_id`) ON DELETE SET NULL
  
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. OPTIONAL: CREATE NOTIFICATION_PREFERENCES TABLE
-- ============================================
-- For future use: Allow users to control notification preferences

CREATE TABLE IF NOT EXISTS `notification_preferences` (
  `preference_id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `notification_type` VARCHAR(50) COMMENT 'Type: success, info, warning, error or all',
  `is_enabled` BOOLEAN DEFAULT TRUE,
  `channel` VARCHAR(50) DEFAULT 'in_app' COMMENT 'in_app, email, sms, all',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  UNIQUE KEY `uq_user_type_channel` (`user_id`, `notification_type`, `channel`),
  FOREIGN KEY (`user_id`) REFERENCES `users1` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 5. VERIFICATION QUERIES
-- ============================================
-- Run these to verify the setup is complete

-- Check notification types were created
SELECT 'NOTIFICATION_TYPES' as check_item, COUNT(*) as count FROM notification_types;

-- Check user_notifications table structure
SELECT 'USER_NOTIFICATIONS' as check_item, COUNT(*) as count FROM information_schema.COLUMNS 
WHERE TABLE_NAME = 'user_notifications' AND TABLE_SCHEMA = DATABASE();

-- List all notification types
SELECT * FROM notification_types;

-- ============================================
-- 6. SAMPLE DATA (OPTIONAL - for testing)
-- ============================================
-- Uncomment to insert sample notifications for testing

-- INSERT INTO user_notifications 
-- (user_id, user_type, type_id, title, message, short_message, event_type, priority, action_url, created_at)
-- VALUES (
--   1,
--   'user',
--   (SELECT type_id FROM notification_types WHERE type_name = 'success'),
--   'Test Notification',
--   'This is a test notification for the new notification system',
--   'Test notification',
--   'system',
--   'normal',
--   'user_dashboard.php',
--   NOW()
-- );

-- ============================================
-- END OF DATABASE SETUP
-- ============================================
