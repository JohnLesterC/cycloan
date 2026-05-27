-- Email Queue System Tables
-- This creates tables to store email notifications and batch them for consolidated delivery

-- 1. Email Queue Table - stores pending email notifications
CREATE TABLE IF NOT EXISTS email_queue (
    queue_id INT AUTO_INCREMENT PRIMARY KEY,
    application_id VARCHAR(50) NOT NULL,
    user_id INT,
    event_type VARCHAR(50) NOT NULL,
    event_data LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    batch_id VARCHAR(100),
    processed BOOLEAN DEFAULT FALSE,
    sent_at TIMESTAMP NULL,
    retry_count INT DEFAULT 0,
    last_error TEXT,
    INDEX idx_application (application_id),
    INDEX idx_user (user_id),
    INDEX idx_processed (processed),
    INDEX idx_batch (batch_id),
    INDEX idx_created (created_at)
);

-- 2. Email Batch Log Table - tracks consolidated emails sent
CREATE TABLE IF NOT EXISTS email_batch_log (
    batch_id VARCHAR(100) PRIMARY KEY,
    application_id VARCHAR(50) NOT NULL,
    user_id INT,
    recipient_email VARCHAR(255),
    batch_start_time TIMESTAMP,
    batch_end_time TIMESTAMP,
    event_count INT DEFAULT 0,
    email_subject VARCHAR(255),
    email_body_preview LONGTEXT,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    sent_timestamp TIMESTAMP NULL,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_application (application_id),
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
);

-- 3. Email Queue Settings Table - configurable batching parameters
CREATE TABLE IF NOT EXISTS email_queue_settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value VARCHAR(255),
    description TEXT,
    data_type VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default settings
INSERT IGNORE INTO email_queue_settings (setting_key, setting_value, description, data_type) VALUES
('batch_window_seconds', '300', 'Time window to collect events before sending (in seconds)', 'integer'),
('max_batch_size', '50', 'Maximum number of events to include in one batch', 'integer'),
('enable_queue', '1', 'Enable/disable the email queue system (1=enabled, 0=disabled)', 'boolean'),
('batch_delay_minutes', '5', 'Minutes to wait before processing queue', 'integer'),
('max_retry_attempts', '3', 'Maximum number of retry attempts for failed emails', 'integer');
