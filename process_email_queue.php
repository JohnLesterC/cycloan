<?php
/**
 * Email Queue Processor
 * 
 * This script processes pending email queues and sends consolidated emails
 * Should be run periodically via cron job (every 5 minutes recommended)
 * 
 * Command line usage:
 *   php process_email_queue.php
 * 
 * Cron setup (Linux):
 *   Every 5 minutes: /usr/bin/php /path/to/cycloan/process_email_queue.php
 */

// Start output buffering to prevent accidental output
ob_start();

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);  // Don't display errors to prevent cron output
ini_set('log_errors', 1);

try {
    // Require database connection
    require_once 'CYCLOAN_db.php';

    if (!isset($conn) || !$conn) {
        throw new Exception("Database connection failed");
    }

    error_log("EMAIL_QUEUE_PROCESS_START: Beginning email queue processing", E_USER_NOTICE);

    // ========== EMAIL QUEUE PROCESSOR FUNCTIONS ==========

    /**
     * Load queue settings from database
     */
    function loadQueueSettings($conn)
    {
        $settings = [];
        $query = "SELECT setting_key, setting_value FROM email_queue_settings";
        $stmt = $conn->prepare($query);

        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            $stmt->close();
        }

        return $settings;
    }

    /**
     * Get pending batches that are ready to process
     */
    function getPendingBatches($conn, $delayMinutes = 5)
    {
        $delaySeconds = $delayMinutes * 60;
        $query = "SELECT batch_id, application_id, user_id, batch_start_time 
                  FROM email_batch_log 
                  WHERE status = 'pending' 
                  AND TIMESTAMPDIFF(SECOND, batch_start_time, NOW()) >= ?
                  LIMIT 100";

        $stmt = $conn->prepare($query);
        $batches = [];

        if ($stmt) {
            $stmt->bind_param("i", $delaySeconds);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $batches[] = $row;
            }
            $stmt->close();
        }

        return $batches;
    }

    /**
     * Get all events in a batch
     */
    function getBatchEvents($conn, $batchId)
    {
        $query = "SELECT queue_id, event_type, event_data FROM email_queue 
                  WHERE batch_id = ? AND processed = FALSE
                  ORDER BY created_at ASC";

        $stmt = $conn->prepare($query);
        $events = [];

        if ($stmt) {
            $stmt->bind_param("s", $batchId);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $row['event_data'] = json_decode($row['event_data'], true);
                $events[] = $row;
            }
            $stmt->close();
        }

        return $events;
    }

    /**
     * Build consolidated email data from all events in batch
     */
    function buildConsolidatedEmailData($conn, $events, $applicationId)
    {
        $consolidated = [];
        $documentMap = [];
        $remarks = [];

        foreach ($events as $event) {
            $type = $event['event_type'];
            $data = $event['event_data'] ?: [];

            switch ($type) {
                case 'document_approved':
                    $docId = $data['document_id'] ?? null;
                    if ($docId) {
                        $documentMap[$docId] = [
                            'id' => $docId,
                            'name' => $data['document_name'] ?? 'Document',
                            'status' => 'Approved'
                        ];
                    }
                    break;

                case 'document_rejected':
                    $docId = $data['document_id'] ?? null;
                    if ($docId) {
                        $documentMap[$docId] = [
                            'id' => $docId,
                            'name' => $data['document_name'] ?? 'Document',
                            'status' => 'Rejected',
                            'rejection_notes' => $data['reason'] ?? 'Rejected'
                        ];
                    }
                    break;

                case 'remark_added':
                    if (!empty($data['remark'])) {
                        $remarks[] = $data['remark'];
                    }
                    break;

                case 'pre_approval_status':
                    $consolidated['pre_approval_status'] = $data['status'] ?? null;
                    if (!empty($data['reason'])) {
                        $consolidated['approval_reason'] = $data['reason'];
                    }
                    break;

                case 'credit_status':
                    $consolidated['credit_status'] = $data['status'] ?? null;
                    break;
            }
        }

        // Add documents to consolidated
        if (!empty($documentMap)) {
            $consolidated['documents'] = array_values($documentMap);
        }

        // Add remarks
        if (!empty($remarks)) {
            $consolidated['remarks'] = implode(" | ", $remarks);
        }

        // Fetch all documents for context
        $query = "SELECT d.document_id, dt.document_name, d.status, d.rejection_notes
                  FROM documents d
                  JOIN document_types dt ON d.document_type_id = dt.document_type_id
                  WHERE d.application_id = ?
                  ORDER BY dt.document_name ASC";

        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("s", $applicationId);
            $stmt->execute();
            $result = $stmt->get_result();
            $allDocs = [];

            while ($row = $result->fetch_assoc()) {
                $allDocs[] = [
                    'id' => $row['document_id'],
                    'name' => $row['document_name'],
                    'status' => $row['status'],
                    'rejection_notes' => $row['rejection_notes']
                ];
            }
            $stmt->close();

            if (!empty($allDocs)) {
                $consolidated['all_documents'] = $allDocs;
            }
        }

        return $consolidated;
    }

    /**
     * Mark batch as processed
     */
    function markBatchProcessed($conn, $batchId, $status = 'sent', $errorMsg = null)
    {
        $now = date('Y-m-d H:i:s');
        $query = "UPDATE email_batch_log 
                  SET status = ?, sent_timestamp = NOW(), error_message = ? 
                  WHERE batch_id = ?";

        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("sss", $status, $errorMsg, $batchId);
            $stmt->execute();
            $stmt->close();
        }
    }

    /**
     * Mark queued events as sent
     */
    function markEventsProcessed($conn, $batchId)
    {
        $query = "UPDATE email_queue 
                  SET processed = TRUE, sent_at = NOW() 
                  WHERE batch_id = ?";

        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("s", $batchId);
            $stmt->execute();
            $affectedRows = $stmt->affected_rows;
            $stmt->close();
            return $affectedRows;
        }
        return 0;
    }

    /**
     * Process a single batch
     */
    function processBatch($conn, $batch)
    {
        $batchId = $batch['batch_id'];
        $applicationId = $batch['application_id'];
        $userId = $batch['user_id'];

        error_log("EMAIL_QUEUE_BATCH_PROCESS: Processing batch $batchId for app $applicationId", E_USER_NOTICE);

        try {
            // Get all events in batch
            $events = getBatchEvents($conn, $batchId);

            if (empty($events)) {
                error_log("EMAIL_QUEUE_BATCH_EMPTY: Batch $batchId has no events", E_USER_NOTICE);
                markBatchProcessed($conn, $batchId, 'sent', 'No events in batch');
                return true;
            }

            // Build consolidated email data
            $consolidated = buildConsolidatedEmailData($conn, $events, $applicationId);

            error_log("EMAIL_QUEUE_BATCH_BUILT: Built consolidated email with " . count($events) . " events", E_USER_NOTICE);

            // Require admin dashboard for sendConsolidatedUpdateEmail function
            require_once 'admin2_dashboard.php';

            // Send consolidated email
            $emailSent = sendConsolidatedUpdateEmail($conn, $applicationId, $consolidated);

            if ($emailSent) {
                error_log("EMAIL_QUEUE_BATCH_SENT: Batch $batchId sent successfully", E_USER_NOTICE);
                markBatchProcessed($conn, $batchId, 'sent');

                // Mark events as processed
                $processedCount = markEventsProcessed($conn, $batchId);
                error_log("EMAIL_QUEUE_EVENTS_MARKED: Marked $processedCount events as processed", E_USER_NOTICE);

                return true;
            } else {
                error_log("EMAIL_QUEUE_BATCH_FAILED: Failed to send batch $batchId", E_USER_WARNING);
                markBatchProcessed($conn, $batchId, 'failed', 'Email send failed');
                return false;
            }
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
            error_log("EMAIL_QUEUE_BATCH_EXCEPTION: Batch $batchId exception - $errorMsg", E_USER_WARNING);
            markBatchProcessed($conn, $batchId, 'failed', $errorMsg);
            return false;
        }
    }

    /**
     * Clear old processed batches
     */
    function clearOldBatches($conn, $daysOld = 30)
    {
        $cutoffDate = date('Y-m-d 00:00:00', strtotime("-$daysOld days"));

        // Delete old batch log entries
        $query = "DELETE FROM email_batch_log 
                  WHERE status = 'sent' AND sent_timestamp < ?";

        $stmt = $conn->prepare($query);
        $deletedCount = 0;

        if ($stmt) {
            $stmt->bind_param("s", $cutoffDate);
            $stmt->execute();
            $deletedCount = $stmt->affected_rows;
            $stmt->close();
        }

        // Delete associated queue entries
        if ($deletedCount > 0) {
            $query2 = "DELETE FROM email_queue 
                       WHERE batch_id NOT IN (SELECT batch_id FROM email_batch_log)";
            $stmt2 = $conn->prepare($query2);
            if ($stmt2) {
                $stmt2->execute();
                $deletedQueueEntries = $stmt2->affected_rows;
                $stmt2->close();
                error_log("EMAIL_QUEUE_CLEANUP: Cleaned up $deletedCount batches and $deletedQueueEntries queue entries", E_USER_NOTICE);
            }
        }

        return $deletedCount;
    }

    // ========== MAIN EXECUTION ==========

    // Load settings
    $settings = loadQueueSettings($conn);
    error_log("EMAIL_QUEUE_SETTINGS: " . json_encode($settings), E_USER_NOTICE);

    // Check if queue is enabled
    if ($settings['enable_queue'] == '0') {
        error_log("EMAIL_QUEUE_DISABLED: Queue processing is disabled", E_USER_NOTICE);
        ob_end_clean();
        exit(0);
    }

    $batchDelayMinutes = intval($settings['batch_delay_minutes'] ?? 5);

    // Get pending batches ready to process
    $batches = getPendingBatches($conn, $batchDelayMinutes);
    error_log("EMAIL_QUEUE_FOUND: Found " . count($batches) . " pending batches", E_USER_NOTICE);

    $processedCount = 0;
    $failedCount = 0;

    // Process each batch
    foreach ($batches as $batch) {
        if (processBatch($conn, $batch)) {
            $processedCount++;
        } else {
            $failedCount++;
        }
    }

    // Clean up old batches (older than 30 days)
    $cleanedCount = clearOldBatches($conn, 30);

    // Log summary
    error_log("EMAIL_QUEUE_PROCESS_COMPLETE: Processed $processedCount, Failed: $failedCount, Cleaned: $cleanedCount", E_USER_NOTICE);

    // Get summary stats
    $statsQuery = "SELECT 
                    (SELECT COUNT(*) FROM email_queue WHERE processed = FALSE) as pending_events,
                    (SELECT COUNT(*) FROM email_batch_log WHERE status = 'pending') as pending_batches,
                    (SELECT COUNT(*) FROM email_batch_log WHERE status = 'failed') as failed_batches,
                    (SELECT COUNT(*) FROM email_batch_log WHERE status = 'sent' AND DATE(sent_timestamp) = CURDATE()) as sent_today";

    $statsResult = $conn->query($statsQuery);
    if ($statsResult) {
        $stats = $statsResult->fetch_assoc();
        error_log("EMAIL_QUEUE_STATS: " . json_encode($stats), E_USER_NOTICE);
    }

    // Close database connection
    $conn->close();

    error_log("EMAIL_QUEUE_PROCESS_FINISHED: Queue processing completed successfully", E_USER_NOTICE);

} catch (Exception $e) {
    error_log("EMAIL_QUEUE_PROCESS_FATAL: " . $e->getMessage(), E_USER_WARNING);
} finally {
    ob_end_clean();
    exit(0);
}
?>