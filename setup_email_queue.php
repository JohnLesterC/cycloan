<?php
/**
 * EMAIL QUEUE SYSTEM - Database Setup
 * Run this script once to create the required tables for email queueing and consolidation
 * 
 * Purpose: Enable batching of emails so users receive consolidated updates instead of spam
 */

require_once 'CYCLOAN_db.php';

try {
    echo "Starting email queue system setup...\n\n";
    
    // Create email_queue table
    $createQueueTable = "
    CREATE TABLE IF NOT EXISTS email_queue (
        id INT AUTO_INCREMENT PRIMARY KEY,
        application_id VARCHAR(50) NOT NULL,
        user_id INT NOT NULL,
        queue_type ENUM('document_update', 'pre_approval', 'remark', 'payment_reminder') NOT NULL DEFAULT 'document_update',
        update_data LONGTEXT NOT NULL COMMENT 'JSON encoded update data',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        scheduled_send_at TIMESTAMP NULL COMMENT 'When this email should be processed',
        sent_at TIMESTAMP NULL COMMENT 'When email was successfully sent',
        send_attempts INT DEFAULT 0 COMMENT 'Number of send attempts',
        max_attempts INT DEFAULT 3 COMMENT 'Maximum retry attempts',
        last_error VARCHAR(500) COMMENT 'Error message from last failed attempt',
        status ENUM('pending', 'queued', 'sent', 'failed') DEFAULT 'pending' COMMENT 'Current status of queued email',
        retry_after TIMESTAMP NULL COMMENT 'Do not retry before this time',
        batch_id VARCHAR(100) COMMENT 'Groups related emails together',
        
        INDEX idx_application_id (application_id),
        INDEX idx_user_id (user_id),
        INDEX idx_status (status),
        INDEX idx_scheduled_send_at (scheduled_send_at),
        INDEX idx_batch_id (batch_id),
        CONSTRAINT fk_queue_app FOREIGN KEY (application_id) REFERENCES loan_applications(application_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Email queue for batching and consolidating application updates';
    ";
    
    if ($conn->query($createQueueTable)) {
        echo "✓ Created email_queue table\n";
    } else {
        echo "✗ Error creating email_queue table: " . $conn->error . "\n";
        exit(1);
    }
    
    // Create email_batch_logs table
    $createBatchLogsTable = "
    CREATE TABLE IF NOT EXISTS email_batch_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        batch_id VARCHAR(100) UNIQUE NOT NULL COMMENT 'Unique batch identifier',
        application_id VARCHAR(50) NOT NULL,
        user_id INT NOT NULL,
        queue_count INT NOT NULL COMMENT 'Number of emails consolidated in this batch',
        consolidated_email_data LONGTEXT COMMENT 'JSON of consolidated data sent in email',
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When batch email was sent',
        recipient_email VARCHAR(100) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        email_body_summary VARCHAR(500) COMMENT 'First 500 chars of email body for reference',
        send_status ENUM('success', 'failed', 'bounced') DEFAULT 'success',
        send_error VARCHAR(500),
        
        INDEX idx_application_id (application_id),
        INDEX idx_batch_id (batch_id),
        INDEX idx_sent_at (sent_at),
        CONSTRAINT fk_batch_app FOREIGN KEY (application_id) REFERENCES loan_applications(application_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log of all consolidated batch emails sent';
    ";
    
    if ($conn->query($createBatchLogsTable)) {
        echo "✓ Created email_batch_logs table\n";
    } else {
        echo "✗ Error creating email_batch_logs table: " . $conn->error . "\n";
        exit(1);
    }
    
    // Create email_queue_stats table for monitoring
    $createStatsTable = "
    CREATE TABLE IF NOT EXISTS email_queue_stats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        stat_date DATE NOT NULL,
        total_queued INT DEFAULT 0,
        total_sent INT DEFAULT 0,
        total_failed INT DEFAULT 0,
        avg_wait_seconds INT DEFAULT 0,
        max_batch_size INT DEFAULT 0,
        min_batch_size INT DEFAULT 0,
        total_batches_sent INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        UNIQUE INDEX idx_stat_date (stat_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Daily statistics for email queue monitoring';
    ";
    
    if ($conn->query($createStatsTable)) {
        echo "✓ Created email_queue_stats table\n";
    } else {
        echo "✗ Error creating email_queue_stats table: " . $conn->error . "\n";
        exit(1);
    }
    
    echo "\n✓ Email queue system database setup complete!\n";
    echo "Tables created:\n";
    echo "  - email_queue (stores pending/queued emails)\n";
    echo "  - email_batch_logs (audit trail of sent batches)\n";
    echo "  - email_queue_stats (daily statistics)\n\n";
    
    echo "Next steps:\n";
    echo "  1. Add queue functions to admin2_dashboard.php\n";
    echo "  2. Modify document update handler to use addToEmailQueue()\n";
    echo "  3. Set up cron job to run process_email_queue.php every 5 minutes\n";
    echo "  4. Monitor email_queue table: SELECT COUNT(*), status FROM email_queue GROUP BY status\n\n";
    
} catch (Exception $e) {
    echo "Error during setup: " . $e->getMessage() . "\n";
    exit(1);
}
?>
