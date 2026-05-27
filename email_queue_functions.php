/**
 * EMAIL QUEUE FUNCTIONS
 * 
 * Add these functions to admin2_dashboard.php (after the sendConsolidatedUpdateEmail function, around line 1083)
 * 
 * These functions implement the email queue system to consolidate multiple updates into single emails
 */

// ===== EMAIL QUEUE CONFIGURATION =====

// Email queue wait time (seconds) - adjust based on your needs
define('EMAIL_QUEUE_WAIT_SECONDS', 30);          // Wait 30 seconds before sending
define('EMAIL_QUEUE_MAX_BATCH_ITEMS', 50);       // Max 50 items per batch
define('EMAIL_QUEUE_RETRY_ATTEMPTS', 3);         // Retry failed emails 3 times
define('EMAIL_QUEUE_ENABLE', true);              // Enable/disable email queueing

// ===== PRIMARY QUEUE FUNCTIONS =====

/**
 * Add email to queue instead of sending immediately
 * This allows batching of multiple updates into one email
 */
function addToEmailQueue($conn, $applicationId, $queueType, $updateData = [])
{
    if (!EMAIL_QUEUE_ENABLE) {
        // Queueing disabled, send immediately instead
        return sendConsolidatedUpdateEmail($conn, $applicationId, $updateData);
    }
    
    try {
        $query = "
            INSERT INTO email_queue 
            (application_id, user_id, queue_type, update_data, scheduled_send_at, status)
            SELECT ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), 'pending'
            FROM loan_applications la
            WHERE la.application_id = ?
            LIMIT 1
        ";
        
        $updateDataJson = json_encode($updateData);
        $waitSeconds = EMAIL_QUEUE_WAIT_SECONDS;
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            error_log("EMAIL_QUEUE_ERROR: Failed to prepare query - " . $conn->error, E_USER_WARNING);
            return false;
        }
        
        $stmt->bind_param("ssssis", $applicationId, "", $queueType, $updateDataJson, $waitSeconds, $applicationId);
        
        if ($stmt->execute()) {
            error_log("EMAIL_QUEUE_ADDED: Added to queue - App: $applicationId, Type: $queueType, Scheduled: +" . EMAIL_QUEUE_WAIT_SECONDS . "s", E_USER_NOTICE);
            $stmt->close();
            return true;
        } else {
            error_log("EMAIL_QUEUE_ERROR: Failed to insert - " . $stmt->error, E_USER_WARNING);
            $stmt->close();
            return false;
        }
        
    } catch (Exception $e) {
        error_log("EMAIL_QUEUE_EXCEPTION: " . $e->getMessage(), E_USER_WARNING);
        return false;
    }
}

/**
 * Get user_id from application_id
 */
function getUserIdFromApplication($conn, $applicationId)
{
    try {
        $query = "SELECT user_id FROM loan_applications WHERE application_id = ? LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $applicationId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            return $row['user_id'];
        }
        
        $stmt->close();
        return null;
    } catch (Exception $e) {
        error_log("ERROR getting user_id: " . $e->getMessage(), E_USER_WARNING);
        return null;
    }
}

/**
 * Process queued emails and send consolidated batches
 * Should be called via cron job or on-demand
 */
function processEmailQueue($conn, $timeLimitSeconds = 300)
{
    try {
        $startTime = microtime(true);
        $processed = 0;
        
        error_log("EMAIL_QUEUE_PROCESSOR: Starting queue processing", E_USER_NOTICE);
        
        while ((microtime(true) - $startTime) < $timeLimitSeconds) {
            // Get applications with queued emails
            $query = "
                SELECT DISTINCT 
                    eq.application_id,
                    COUNT(eq.id) as queue_count
                FROM email_queue eq
                WHERE eq.status IN ('pending', 'queued')
                AND eq.scheduled_send_at <= NOW()
                AND (eq.retry_after IS NULL OR eq.retry_after <= NOW())
                GROUP BY eq.application_id
                ORDER BY COUNT(eq.id) DESC
                LIMIT 10
            ";
            
            $result = $conn->query($query);
            
            if (!$result || $result->num_rows === 0) {
                error_log("EMAIL_QUEUE_PROCESSOR: No more items to process", E_USER_NOTICE);
                break; // No more items
            }
            
            while ($row = $result->fetch_assoc()) {
                $applicationId = $row['application_id'];
                $queueCount = $row['queue_count'];
                
                error_log("EMAIL_QUEUE_PROCESSOR: Processing App $applicationId ($queueCount items)", E_USER_NOTICE);
                
                $success = processApplicationEmailQueue($conn, $applicationId);
                
                if ($success) {
                    $processed++;
                }
                
                if ((microtime(true) - $startTime) > $timeLimitSeconds) {
                    break; // Time limit reached
                }
            }
        }
        
        $elapsed = round(microtime(true) - $startTime, 2);
        error_log("EMAIL_QUEUE_PROCESSOR: Complete - Processed $processed applications in {$elapsed}s", E_USER_NOTICE);
        
        return $processed;
        
    } catch (Exception $e) {
        error_log("EMAIL_QUEUE_PROCESSOR_ERROR: " . $e->getMessage(), E_USER_WARNING);
        return 0;
    }
}

/**
 * Process all queued emails for a specific application
 */
function processApplicationEmailQueue($conn, $applicationId)
{
    try {
        // Fetch all queued updates for this application
        $query = "
            SELECT id, queue_type, update_data, created_at, send_attempts
            FROM email_queue
            WHERE application_id = ?
            AND status IN ('pending', 'queued')
            ORDER BY created_at ASC
            LIMIT " . EMAIL_QUEUE_MAX_BATCH_ITEMS . "
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $applicationId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $queuedUpdates = [];
        while ($row = $result->fetch_assoc()) {
            $queuedUpdates[] = $row;
        }
        $stmt->close();
        
        if (empty($queuedUpdates)) {
            return false;
        }
        
        error_log("EMAIL_QUEUE_BATCH: Found " . count($queuedUpdates) . " items to consolidate", E_USER_NOTICE);
        
        // Consolidate all updates
        $consolidatedData = consolidateQueuedUpdates($queuedUpdates);
        
        // Add batch info
        $batchId = 'BATCH-' . $applicationId . '-' . date('YmdHis') . '-' . substr(md5(microtime()), 0, 8);
        $consolidatedData['batch_id'] = $batchId;
        
        // Send consolidated email
        error_log("EMAIL_QUEUE_BATCH: Sending consolidated email (Batch: $batchId) with " . count($queuedUpdates) . " items", E_USER_NOTICE);
        
        if (sendConsolidatedUpdateEmail($conn, $applicationId, $consolidatedData)) {
            // Mark all as sent
            foreach ($queuedUpdates as $update) {
                $queueId = $update['id'];
                $query = "UPDATE email_queue SET status = 'sent', sent_at = NOW() WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("i", $queueId);
                $stmt->execute();
                $stmt->close();
            }
            
            // Log batch in audit table
            logEmailBatch($conn, $batchId, $applicationId, count($queuedUpdates), $consolidatedData);
            
            error_log("EMAIL_QUEUE_BATCH_SUCCESS: Batch $batchId sent successfully", E_USER_NOTICE);
            return true;
            
        } else {
            // Retry logic - reschedule with exponential backoff
            foreach ($queuedUpdates as $update) {
                $queueId = $update['id'];
                $attempts = (int)$update['send_attempts'];
                $maxAttempts = EMAIL_QUEUE_RETRY_ATTEMPTS;
                
                if ($attempts >= $maxAttempts) {
                    // Mark as failed
                    $query = "
                        UPDATE email_queue
                        SET status = 'failed', last_error = 'Max retry attempts reached'
                        WHERE id = ?
                    ";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("i", $queueId);
                    $stmt->execute();
                    $stmt->close();
                    
                    error_log("EMAIL_QUEUE_FAILED: Queue ID $queueId marked as failed", E_USER_WARNING);
                } else {
                    // Schedule retry with exponential backoff
                    $backoffSeconds = 60 * pow(2, $attempts); // 60s, 120s, 240s
                    $query = "
                        UPDATE email_queue
                        SET 
                            send_attempts = send_attempts + 1,
                            status = 'queued',
                            scheduled_send_at = DATE_ADD(NOW(), INTERVAL ? SECOND),
                            retry_after = DATE_ADD(NOW(), INTERVAL ? SECOND)
                        WHERE id = ?
                    ";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("iii", $backoffSeconds, $backoffSeconds, $queueId);
                    $stmt->execute();
                    $stmt->close();
                    
                    error_log("EMAIL_QUEUE_RETRY: Queue ID $queueId scheduled for retry in {$backoffSeconds}s", E_USER_NOTICE);
                }
            }
            
            return false;
        }
        
    } catch (Exception $e) {
        error_log("EMAIL_QUEUE_ERROR: Error processing application queue - " . $e->getMessage(), E_USER_WARNING);
        return false;
    }
}

/**
 * Consolidate multiple queued updates into single email data structure
 */
function consolidateQueuedUpdates($queuedUpdates)
{
    $consolidated = [
        'documents' => [],
        'all_documents' => [],
        'pre_approval_status' => null,
        'remarks' => [],
        'admin_name' => null,
        'admin_updated_at' => date('F j, Y \a\t g:i A'),
        'queue_items_count' => count($queuedUpdates),
        'queue_types' => []
    ];
    
    foreach ($queuedUpdates as $update) {
        try {
            $data = json_decode($update['update_data'], true);
            
            if (!is_array($data)) {
                continue;
            }
            
            // Track types
            if (!in_array($update['queue_type'], $consolidated['queue_types'])) {
                $consolidated['queue_types'][] = $update['queue_type'];
            }
            
            // Consolidate by type
            if ($update['queue_type'] === 'document_update') {
                if (isset($data['documents']) && is_array($data['documents'])) {
                    $consolidated['documents'] = array_merge($consolidated['documents'], $data['documents']);
                }
                if (isset($data['all_documents']) && is_array($data['all_documents'])) {
                    $consolidated['all_documents'] = $data['all_documents'];
                }
                
            } elseif ($update['queue_type'] === 'pre_approval') {
                if (isset($data['pre_approval_status'])) {
                    $consolidated['pre_approval_status'] = $data['pre_approval_status'];
                }
                
            } elseif ($update['queue_type'] === 'remark') {
                if (isset($data['remarks'])) {
                    $consolidated['remarks'][] = $data['remarks'];
                }
            }
            
            // Get latest admin info
            if (isset($data['admin_name']) && !empty($data['admin_name'])) {
                $consolidated['admin_name'] = $data['admin_name'];
            }
            
        } catch (Exception $e) {
            error_log("Error consolidating update: " . $e->getMessage(), E_USER_WARNING);
        }
    }
    
    return $consolidated;
}

/**
 * Log batch in audit table
 */
function logEmailBatch($conn, $batchId, $applicationId, $itemCount, $consolidatedData)
{
    try {
        $query = "
            INSERT INTO email_batch_logs 
            (batch_id, application_id, user_id, queue_count, consolidated_email_data, recipient_email, subject, send_status)
            SELECT ?, ?, la.user_id, ?, ?, u.email, ?, 'success'
            FROM loan_applications la
            JOIN users1 u ON la.user_id = u.id
            WHERE la.application_id = ?
        ";
        
        $subject = "Application Update - Application #$applicationId";
        $dataJson = json_encode($consolidatedData);
        
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("ssisss", $batchId, $applicationId, $itemCount, $dataJson, $subject, $applicationId);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Error logging batch: " . $e->getMessage(), E_USER_WARNING);
    }
}

/**
 * Get queue statistics
 */
function getEmailQueueStats($conn)
{
    try {
        $query = "
            SELECT 
                status,
                COUNT(*) as count,
                AVG(TIMESTAMPDIFF(SECOND, created_at, NOW())) as avg_age_seconds,
                MAX(TIMESTAMPDIFF(SECOND, created_at, NOW())) as max_age_seconds,
                MIN(TIMESTAMPDIFF(SECOND, created_at, NOW())) as min_age_seconds
            FROM email_queue
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY status
        ";
        
        $result = $conn->query($query);
        if ($result) {
            return $result->fetch_all(MYSQLI_ASSOC);
        }
        return [];
    } catch (Exception $e) {
        error_log("Error getting queue stats: " . $e->getMessage(), E_USER_WARNING);
        return [];
    }
}

/**
 * Get failed emails from queue
 */
function getFailedQueuedEmails($conn, $limit = 100)
{
    try {
        $query = "
            SELECT id, application_id, queue_type, send_attempts, last_error, created_at
            FROM email_queue
            WHERE status = 'failed'
            ORDER BY created_at DESC
            LIMIT ?
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $failed = [];
        while ($row = $result->fetch_assoc()) {
            $failed[] = $row;
        }
        $stmt->close();
        
        return $failed;
    } catch (Exception $e) {
        error_log("Error getting failed emails: " . $e->getMessage(), E_USER_WARNING);
        return [];
    }
}

/**
 * Manually retry a failed queued email
 */
function retryFailedQueuedEmail($conn, $queueId)
{
    try {
        $query = "
            UPDATE email_queue
            SET status = 'queued', send_attempts = 0, scheduled_send_at = NOW(), last_error = NULL
            WHERE id = ?
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $queueId);
        
        if ($stmt->execute()) {
            error_log("EMAIL_QUEUE_RETRY: Manually retried queue ID $queueId", E_USER_NOTICE);
            $stmt->close();
            return true;
        }
        
        $stmt->close();
        return false;
    } catch (Exception $e) {
        error_log("Error retrying queued email: " . $e->getMessage(), E_USER_WARNING);
        return false;
    }
}

/**
 * Clear old queued emails (cleanup)
 */
function cleanupOldQueuedEmails($conn, $daysOld = 7)
{
    try {
        $query = "
            DELETE FROM email_queue
            WHERE status IN ('sent', 'failed')
            AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $daysOld);
        $result = $stmt->execute();
        
        $deletedRows = $stmt->affected_rows;
        $stmt->close();
        
        error_log("EMAIL_QUEUE_CLEANUP: Deleted $deletedRows old queue entries", E_USER_NOTICE);
        
        return $deletedRows;
    } catch (Exception $e) {
        error_log("Error cleaning up queue: " . $e->getMessage(), E_USER_WARNING);
        return 0;
    }
}
