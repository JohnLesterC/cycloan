-- ============================================================
-- SSE Real-Time Notification System - Database Schema
-- ============================================================
-- This file ensures the notifications table has all required
-- columns for the SSE real-time notification system.

-- Create or verify notifications table structure
CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    notification_type VARCHAR(50) NOT NULL COMMENT 'loan_created, loan_updated, payment_received, etc.',
    module VARCHAR(100) COMMENT 'Module/section the notification relates to',
    action VARCHAR(50) COMMENT 'Action type: create, update, delete, approve, etc.',
    priority ENUM('low', 'normal', 'high', 'critical') DEFAULT 'normal',
    recipient_id INT COMMENT 'Specific admin user ID if applicable',
    recipient_role VARCHAR(50) COMMENT 'Role if notification is for all users in a role',
    sender_id INT COMMENT 'User ID who triggered the notification',
    related_id INT COMMENT 'Related entity ID (loan application, user, etc.)',
    is_read BOOLEAN DEFAULT FALSE,
    read_at DATETIME NULL,
    sent_at DATETIME NULL COMMENT 'When the notification was sent to the client',
    status ENUM('active', 'archived', 'deleted') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_recipient_id (recipient_id),
    INDEX idx_recipient_role (recipient_role),
    INDEX idx_created_at (created_at),
    INDEX idx_notification_type (notification_type),
    INDEX idx_is_read (is_read),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add missing columns if they don't exist
ALTER TABLE notifications 
ADD COLUMN IF NOT EXISTS notification_type VARCHAR(50) NOT NULL DEFAULT 'system_alert' AFTER message,
ADD COLUMN IF NOT EXISTS module VARCHAR(100) AFTER notification_type,
ADD COLUMN IF NOT EXISTS action VARCHAR(50) AFTER module,
ADD COLUMN IF NOT EXISTS priority ENUM('low', 'normal', 'high', 'critical') DEFAULT 'normal' AFTER action,
ADD COLUMN IF NOT EXISTS recipient_id INT AFTER priority,
ADD COLUMN IF NOT EXISTS recipient_role VARCHAR(50) AFTER recipient_id,
ADD COLUMN IF NOT EXISTS sender_id INT AFTER recipient_role,
ADD COLUMN IF NOT EXISTS related_id INT AFTER sender_id,
ADD COLUMN IF NOT EXISTS is_read BOOLEAN DEFAULT FALSE AFTER related_id,
ADD COLUMN IF NOT EXISTS read_at DATETIME NULL AFTER is_read,
ADD COLUMN IF NOT EXISTS sent_at DATETIME NULL AFTER read_at,
ADD COLUMN IF NOT EXISTS status ENUM('active', 'archived', 'deleted') DEFAULT 'active' AFTER sent_at;

-- Create indexes for better query performance
ALTER TABLE notifications
ADD INDEX IF NOT EXISTS idx_recipient_id (recipient_id),
ADD INDEX IF NOT EXISTS idx_recipient_role (recipient_role),
ADD INDEX IF NOT EXISTS idx_created_at (created_at),
ADD INDEX IF NOT EXISTS idx_notification_type (notification_type),
ADD INDEX IF NOT EXISTS idx_is_read (is_read),
ADD INDEX IF NOT EXISTS idx_status (status);

-- Set timezone for timestamps
SET time_zone = '+08:00';

-- Verify table structure
DESCRIBE notifications;
