<?php
/**
 * Email Queue Manager - Consolidates multiple email events into a single batched email
 * 
 * This system queues email events and sends them as consolidated batches instead of 
 * sending individual emails for each status change. This prevents spam and provides
 * a cleaner user experience.
 * 
 * USAGE:
 *   $queue = new EmailQueueManager($conn);
 *   $queue->addToQueue($applicationId, 'document_rejected', ['document' => 'ID Photo', 'reason' => '...']);
 *   // Later, a cron job or background task processes the queue
 *   $queue->processPendingQueue();
 */

class EmailQueueManager
{
    private $conn;
    private $queueSettings = [];
    private $batchWindowSeconds = 300;  // 5 minutes default
    private $maxBatchSize = 50;
    private $enableQueue = true;
    private $maxRetryAttempts = 3;

    public function __construct($conn)
    {
        $this->conn = $conn;
        $this->loadSettings();
    }

    /**
     * Load queue settings from database
     */
    private function loadSettings()
    {
        $query = "SELECT setting_key, setting_value FROM email_queue_settings";
        $stmt = $this->conn->prepare($query);
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $this->queueSettings[$row['setting_key']] = $row['setting_value'];
            }
            $stmt->close();

            // Apply settings
            $this->batchWindowSeconds = (int)$this->queueSettings['batch_window_seconds'] ?? 300;
            $this->maxBatchSize = (int)$this->queueSettings['max_batch_size'] ?? 50;
            $this->enableQueue = (int)$this->queueSettings['enable_queue'] ?? 1;
            $this->maxRetryAttempts = (int)$this->queueSettings['max_retry_attempts'] ?? 3;
        }
    }

    /**
     * Add an event to the email queue
     * 
     * @param string $applicationId
     * @param string $eventType (e.g., 'document_approved', 'document_rejected', 'remark_added', 'pre_approval_status')
     * @param array $eventData
     * @return bool
     */
    public function addToQueue($applicationId, $eventType, $eventData = [])
    {
        if (!$this->enableQueue) {
            error_log("EMAIL_QUEUE: Queue is disabled, event not added", E_USER_NOTICE);
            return false;
        }

        try {
            // Get user_id from application_id
            $userQuery = "SELECT user_id FROM loan_applications WHERE application_id = ?";
            $userStmt = $this->conn->prepare($userQuery);
            if (!$userStmt) {
                error_log("EMAIL_QUEUE: Failed to prepare user query", E_USER_WARNING);
                return false;
            }

            $userStmt->bind_param("s", $applicationId);
            $userStmt->execute();
            $userResult = $userStmt->get_result();
            $userRow = $userResult->fetch_assoc();
            $userStmt->close();

            if (!$userRow) {
                error_log("EMAIL_QUEUE: Application not found: $applicationId", E_USER_WARNING);
                return false;
            }

            $userId = $userRow['user_id'];
            $eventDataJson = json_encode($eventData);

            // Check if there's an existing open batch for this application
            $batchId = $this->getOrCreateBatch($applicationId, $userId);

            // Insert event into queue
            $query = "INSERT INTO email_queue (application_id, user_id, event_type, event_data, batch_id) 
                      VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                error_log("EMAIL_QUEUE: Failed to prepare insert query", E_USER_WARNING);
                return false;
            }

            $stmt->bind_param("sisss", $applicationId, $userId, $eventType, $eventDataJson, $batchId);
            $result = $stmt->execute();
            $stmt->close();

            if ($result) {
                error_log("EMAIL_QUEUE_ADD: Event added to queue - App: $applicationId, Type: $eventType, Batch: $batchId", E_USER_NOTICE);
                return true;
            } else {
                error_log("EMAIL_QUEUE_ADD_ERROR: Failed to add event to queue", E_USER_WARNING);
                return false;
            }
        } catch (Exception $e) {
            error_log("EMAIL_QUEUE_ADD_EXCEPTION: " . $e->getMessage(), E_USER_WARNING);
            return false;
        }
    }

    /**
     * Get existing batch or create new one if time window expired
     * 
     * @param string $applicationId
     * @param int $userId
     * @return string $batchId
     */
    private function getOrCreateBatch($applicationId, $userId)
    {
        // Check for recent open batch (within batch window)
        $cutoffTime = date('Y-m-d H:i:s', strtotime("-{$this->batchWindowSeconds} seconds"));
        $query = "SELECT batch_id FROM email_batch_log 
                  WHERE application_id = ? AND user_id = ? AND status = 'pending' 
                  AND batch_start_time > ? 
                  LIMIT 1";
        $stmt = $this->conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("sis", $applicationId, $userId, $cutoffTime);
            $stmt->execute();
            $result = $stmt->get_result();
            $batchRow = $result->fetch_assoc();
            $stmt->close();

            if ($batchRow) {
                return $batchRow['batch_id'];
            }
        }

        // Create new batch
        $batchId = 'BATCH_' . $applicationId . '_' . time() . '_' . uniqid();
        $now = date('Y-m-d H:i:s');
        $query = "INSERT INTO email_batch_log 
                  (batch_id, application_id, user_id, batch_start_time, status) 
                  VALUES (?, ?, ?, ?, 'pending')";
        $stmt = $this->conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("ssis", $batchId, $applicationId, $userId, $now);
            $stmt->execute();
            $stmt->close();
            error_log("EMAIL_QUEUE_BATCH_CREATED: New batch created - Batch ID: $batchId", E_USER_NOTICE);
        }

        return $batchId;
    }

    /**
     * Get pending queue for a specific application
     * 
     * @param string $applicationId
     * @return array
     */
    public function getPendingQueue($applicationId)
    {
        $query = "SELECT queue_id, application_id, event_type, event_data, created_at 
                  FROM email_queue 
                  WHERE application_id = ? AND processed = FALSE 
                  ORDER BY created_at ASC";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param("s", $applicationId);
        $stmt->execute();
        $result = $stmt->get_result();
        $queue = [];
        while ($row = $result->fetch_assoc()) {
            $row['event_data'] = json_decode($row['event_data'], true);
            $queue[] = $row;
        }
        $stmt->close();

        return $queue;
    }

    /**
     * Process all pending batches and send consolidated emails
     * Called by a cron job or scheduled task
     * 
     * @return array Statistics about processing
     */
    public function processPendingQueue()
    {
        $stats = [
            'batches_processed' => 0,
            'emails_sent' => 0,
            'emails_failed' => 0,
            'events_processed' => 0
        ];

        try {
            // Get all pending batches
            $query = "SELECT batch_id, application_id, user_id, batch_start_time 
                      FROM email_batch_log 
                      WHERE status = 'pending' 
                      AND TIMESTAMPDIFF(SECOND, batch_start_time, NOW()) > ?
                      LIMIT 100";
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                error_log("EMAIL_QUEUE_PROCESS_ERROR: Failed to prepare pending batches query", E_USER_WARNING);
                return $stats;
            }

            $delaySeconds = ($this->queueSettings['batch_delay_minutes'] ?? 5) * 60;
            $stmt->bind_param("i", $delaySeconds);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($batch = $result->fetch_assoc()) {
                $processed = $this->processBatch($batch['batch_id'], $batch['application_id'], $batch['user_id']);
                if ($processed) {
                    $stats['batches_processed']++;
                    $stats['emails_sent']++;
                } else {
                    $stats['emails_failed']++;
                }
            }
            $stmt->close();

            // Update processed flag for all queued events
            $updateQuery = "UPDATE email_queue SET processed = TRUE, sent_at = NOW() 
                           WHERE processed = FALSE AND batch_id IN 
                           (SELECT batch_id FROM email_batch_log WHERE status = 'sent')";
            $updateStmt = $this->conn->prepare($updateQuery);
            if ($updateStmt) {
                $updateStmt->execute();
                $stats['events_processed'] = $updateStmt->affected_rows;
                $updateStmt->close();
            }

            error_log("EMAIL_QUEUE_PROCESS_COMPLETE: Processed " . $stats['batches_processed'] . " batches, Sent: " . $stats['emails_sent'] . ", Failed: " . $stats['emails_failed'], E_USER_NOTICE);

            return $stats;
        } catch (Exception $e) {
            error_log("EMAIL_QUEUE_PROCESS_EXCEPTION: " . $e->getMessage(), E_USER_WARNING);
            return $stats;
        }
    }

    /**
     * Process a single batch - collect all events and send consolidated email
     * 
     * @param string $batchId
     * @param string $applicationId
     * @param int $userId
     * @return bool
     */
    private function processBatch($batchId, $applicationId, $userId)
    {
        try {
            // Get all events in this batch
            $query = "SELECT queue_id, event_type, event_data FROM email_queue 
                      WHERE batch_id = ? AND processed = FALSE";
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                return false;
            }

            $stmt->bind_param("s", $batchId);
            $stmt->execute();
            $result = $stmt->get_result();

            $events = [];
            while ($row = $result->fetch_assoc()) {
                $row['event_data'] = json_decode($row['event_data'], true);
                $events[] = $row;
            }
            $stmt->close();

            if (empty($events)) {
                error_log("EMAIL_QUEUE_BATCH_EMPTY: Batch has no events - Batch ID: $batchId", E_USER_NOTICE);
                $this->markBatchProcessed($batchId, 'sent');
                return true;
            }

            // Build consolidated updates array from all events
            require_once 'admin2_dashboard.php';

            $consolidatedUpdates = $this->buildConsolidatedUpdates($events, $applicationId);

            // Send consolidated email
            $emailSent = sendConsolidatedUpdateEmail($this->conn, $applicationId, $consolidatedUpdates);

            if ($emailSent) {
                error_log("EMAIL_QUEUE_BATCH_SENT: Consolidated email sent - Batch ID: $batchId, Events: " . count($events), E_USER_NOTICE);
                $this->markBatchProcessed($batchId, 'sent', 'Email successfully sent');
                return true;
            } else {
                error_log("EMAIL_QUEUE_BATCH_FAILED: Failed to send consolidated email - Batch ID: $batchId", E_USER_WARNING);
                $this->markBatchProcessed($batchId, 'failed', 'Email send failed');
                return false;
            }
        } catch (Exception $e) {
            error_log("EMAIL_QUEUE_BATCH_EXCEPTION: " . $e->getMessage(), E_USER_WARNING);
            $this->markBatchProcessed($batchId, 'failed', $e->getMessage());
            return false;
        }
    }

    /**
     * Build consolidated updates array from all queued events
     * 
     * @param array $events
     * @param string $applicationId
     * @return array
     */
    private function buildConsolidatedUpdates($events, $applicationId)
    {
        $updates = [];
        $documentsMap = [];  // Track documents to avoid duplicates
        $remarksCollection = [];
        $preApprovalStatus = null;
        $approvalReason = null;

        foreach ($events as $event) {
            $eventType = $event['event_type'];
            $eventData = $event['event_data'];

            switch ($eventType) {
                case 'document_approved':
                    if (!isset($documentsMap[$eventData['document_id']])) {
                        $documentsMap[$eventData['document_id']] = [
                            'id' => $eventData['document_id'],
                            'name' => $eventData['document_name'] ?? 'Document',
                            'status' => 'Approved'
                        ];
                    }
                    break;

                case 'document_rejected':
                    if (!isset($documentsMap[$eventData['document_id']])) {
                        $documentsMap[$eventData['document_id']] = [
                            'id' => $eventData['document_id'],
                            'name' => $eventData['document_name'] ?? 'Document',
                            'status' => 'Rejected',
                            'rejection_notes' => $eventData['reason'] ?? 'Document rejected'
                        ];
                    } else {
                        // Update existing entry with rejection reason
                        $documentsMap[$eventData['document_id']]['status'] = 'Rejected';
                        $documentsMap[$eventData['document_id']]['rejection_notes'] = $eventData['reason'] ?? 'Document rejected';
                    }
                    break;

                case 'remark_added':
                    $remarksCollection[] = $eventData['remark'] ?? 'Remark added';
                    break;

                case 'pre_approval_status':
                    $preApprovalStatus = $eventData['status'] ?? null;
                    if (isset($eventData['reason'])) {
                        $approvalReason = $eventData['reason'];
                    }
                    break;

                case 'credit_status':
                    $updates['credit_status'] = $eventData['status'] ?? null;
                    break;
            }
        }

        // Add collected data to updates
        if (!empty($documentsMap)) {
            $updates['documents'] = array_values($documentsMap);
        }

        if (!empty($remarksCollection)) {
            $updates['remarks'] = implode(' | ', $remarksCollection);
        }

        if ($preApprovalStatus) {
            $updates['pre_approval_status'] = $preApprovalStatus;
        }

        if ($approvalReason) {
            $updates['approval_reason'] = $approvalReason;
        }

        // Fetch full document list for summary
        require_once 'CYCLOAN_db.php';
        $query = "SELECT d.document_id, d.document_type_id, d.status, dt.document_name 
                  FROM documents d 
                  JOIN document_types dt ON d.document_type_id = dt.document_type_id 
                  WHERE d.application_id = ?";
        $stmt = $this->conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("s", $applicationId);
            $stmt->execute();
            $result = $stmt->get_result();
            $allDocuments = [];
            while ($row = $result->fetch_assoc()) {
                $allDocuments[] = [
                    'id' => $row['document_id'],
                    'name' => $row['document_name'],
                    'status' => $row['status']
                ];
            }
            $stmt->close();
            if (!empty($allDocuments)) {
                $updates['all_documents'] = $allDocuments;
            }
        }

        return $updates;
    }

    /**
     * Mark a batch as processed
     * 
     * @param string $batchId
     * @param string $status ('sent', 'failed')
     * @param string $errorMessage (optional)
     */
    private function markBatchProcessed($batchId, $status = 'sent', $errorMessage = null)
    {
        $now = date('Y-m-d H:i:s');
        $query = "UPDATE email_batch_log SET status = ?, sent_timestamp = ?, error_message = ? WHERE batch_id = ?";
        $stmt = $this->conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("ssss", $status, $now, $errorMessage, $batchId);
            $stmt->execute();
            $stmt->close();
        }
    }

    /**
     * Get queue statistics
     * 
     * @return array
     */
    public function getQueueStats()
    {
        $stats = [
            'pending_events' => 0,
            'pending_batches' => 0,
            'failed_batches' => 0,
            'sent_today' => 0
        ];

        // Pending events
        $query1 = "SELECT COUNT(*) as count FROM email_queue WHERE processed = FALSE";
        $result1 = $this->conn->query($query1);
        if ($result1) {
            $row = $result1->fetch_assoc();
            $stats['pending_events'] = $row['count'];
        }

        // Pending batches
        $query2 = "SELECT COUNT(*) as count FROM email_batch_log WHERE status = 'pending'";
        $result2 = $this->conn->query($query2);
        if ($result2) {
            $row = $result2->fetch_assoc();
            $stats['pending_batches'] = $row['count'];
        }

        // Failed batches
        $query3 = "SELECT COUNT(*) as count FROM email_batch_log WHERE status = 'failed'";
        $result3 = $this->conn->query($query3);
        if ($result3) {
            $row = $result3->fetch_assoc();
            $stats['failed_batches'] = $row['count'];
        }

        // Sent today
        $query4 = "SELECT COUNT(*) as count FROM email_batch_log 
                   WHERE status = 'sent' AND DATE(sent_timestamp) = CURDATE()";
        $result4 = $this->conn->query($query4);
        if ($result4) {
            $row = $result4->fetch_assoc();
            $stats['sent_today'] = $row['count'];
        }

        return $stats;
    }

    /**
     * Clear old processed batches (older than specified days)
     * 
     * @param int $daysOld
     * @return int
     */
    public function clearOldBatches($daysOld = 30)
    {
        $cutoffDate = date('Y-m-d', strtotime("-$daysOld days"));
        $query = "DELETE FROM email_batch_log WHERE status = 'sent' AND sent_timestamp < ?";
        $stmt = $this->conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("s", $cutoffDate);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            error_log("EMAIL_QUEUE_CLEANUP: Removed $affected old batch records", E_USER_NOTICE);
            return $affected;
        }
        return 0;
    }
}
?>
