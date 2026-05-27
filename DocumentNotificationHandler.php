<?php
/**
 * CYCLOAN Document Notification Handler
 * Handles all document-related notifications for users
 * Automatically creates notifications when documents are updated
 */

class DocumentNotificationHandler
{
    private $conn;
    private $notificationManager;

    public function __construct($database_connection, $notificationManager = null)
    {
        $this->conn = $database_connection;
        $this->notificationManager = $notificationManager;
    }

    /**
     * Create a notification for document status update
     * Tracks document changes and notifies the user
     */
    public function notifyDocumentStatusUpdate($userId, $applicationId, $documentName, $oldStatus, $newStatus, $rejectionReason = null)
    {
        try {
            // Build notification message based on status change
            $statusText = $newStatus === 'Approved' ? '✓ Approved' : ($newStatus === 'Rejected' ? '✗ Rejected' : 'Pending');
            $message = "Your document '{$documentName}' status has been updated to: {$statusText}";
            $shortMessage = "{$documentName} - {$statusText}";

            if ($newStatus === 'Rejected' && !empty($rejectionReason)) {
                $message .= "\nReason: {$rejectionReason}";
                $shortMessage = "{$documentName} - Requires Revision";
            }

            // Determine priority based on status
            $priority = 'normal';
            if ($newStatus === 'Rejected') {
                $priority = 'high';
            }

            // Insert into user_notifications table using correct schema
            $query = "
                INSERT INTO user_notifications 
                (user_id, user_type, type_id, title, message, short_message, event_type, related_table, related_id, priority, is_read, created_at, updated_at)
                VALUES (?, ?, 
                    (SELECT type_id FROM notification_types WHERE type_name = 'document' LIMIT 1),
                    ?, ?, ?, ?, 'loan_applications', ?, ?, 0, NOW(), NOW())
            ";

            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                error_log("DocumentNotificationHandler: Prepare failed - " . $this->conn->error);
                return false;
            }

            $title = "Document Update: {$documentName}";
            $user_type = 'user';
            $event_type = 'document_' . strtolower($newStatus);

            $stmt->bind_param(
                "issssssi",
                $userId,                    // user_id (i)
                $user_type,                 // user_type (s)
                $title,                     // title (s)
                $message,                   // message (s)
                $shortMessage,              // short_message (s)
                $event_type,                // event_type (s)
                $applicationId,             // related_id (s)
                $priority                   // priority (s)
            );

            $result = $stmt->execute();
            $stmt->close();

            if ($result) {
                error_log("DocumentNotificationHandler: Notification created for user {$userId} - {$documentName} updated to {$newStatus}");
                return true;
            } else {
                error_log("DocumentNotificationHandler: Insert failed - " . $this->conn->error);
                return false;
            }
        } catch (Exception $e) {
            error_log("DocumentNotificationHandler: Exception - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all document updates for an application from activity logs
     */
    public function getDocumentUpdatesFromActivityLog($applicationId, $limit = 10)
    {
        try {
            $query = "
                SELECT 
                    log_id,
                    description,
                    created_at,
                    admin_name,
                    admin_email
                FROM activity_logs
                WHERE module = 'document' AND action_type = 'update' AND description LIKE ?
                ORDER BY created_at DESC
                LIMIT ?
            ";

            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                error_log("DocumentNotificationHandler: Prepare failed in getDocumentUpdatesFromActivityLog - " . $this->conn->error);
                return [];
            }

            $searchTerm = "%{$applicationId}%";
            $stmt->bind_param("si", $searchTerm, $limit);
            $stmt->execute();
            $result = $stmt->get_result();

            $updates = [];
            while ($row = $result->fetch_assoc()) {
                // Parse the description to extract document details
                $parsed = $this->parseDocumentUpdateDescription($row['description']);
                $row['parsed'] = $parsed;
                $updates[] = $row;
            }

            $stmt->close();
            return $updates;
        } catch (Exception $e) {
            error_log("DocumentNotificationHandler Exception in getDocumentUpdatesFromActivityLog: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Parse document update description to extract key information
     */
    private function parseDocumentUpdateDescription($description)
    {
        $parsed = [
            'document_name' => '',
            'old_status' => '',
            'new_status' => '',
            'reason' => '',
            'raw' => $description
        ];

        // Pattern: Document Status Updated | Document: XXX | From: YYY → To: ZZZ | Reason: ...
        if (preg_match('/Document:\s*(.+?)\s*\|/', $description, $matches)) {
            $parsed['document_name'] = trim($matches[1]);
        }

        if (preg_match('/From:\s*(.+?)\s*→\s*To:\s*(.+?)\s*(?:\||$)/', $description, $matches)) {
            $parsed['old_status'] = trim($matches[1]);
            $parsed['new_status'] = trim($matches[2]);
        }

        if (preg_match('/Reason:\s*(.+?)(?:\||$)/', $description, $matches)) {
            $parsed['reason'] = trim($matches[1]);
        }

        return $parsed;
    }

    /**
     * Get all document updates for display in notification center
     */
    public function getDocumentNotifications($userId, $limit = 20, $offset = 0)
    {
        try {
            $query = "
                SELECT 
                    notification_id as id,
                    title,
                    message,
                    priority,
                    is_read,
                    created_at,
                    event_type as notification_type,
                    related_id
                FROM user_notifications
                WHERE user_id = ? 
                AND (event_type LIKE 'document_%' OR type_id = (SELECT type_id FROM notification_types WHERE type_name = 'document' LIMIT 1))
                AND deleted_at IS NULL
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ";

            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                error_log("DocumentNotificationHandler: Prepare failed in getDocumentNotifications - " . $this->conn->error);
                return [];
            }

            $stmt->bind_param("iii", $userId, $limit, $offset);
            $stmt->execute();
            $result = $stmt->get_result();

            $notifications = [];
            while ($row = $result->fetch_assoc()) {
                $notifications[] = $row;
            }

            $stmt->close();
            return $notifications;
        } catch (Exception $e) {
            error_log("DocumentNotificationHandler Exception in getDocumentNotifications: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get count of document notifications for a user
     */
    public function getDocumentNotificationCount($userId, $onlyUnread = false)
    {
        try {
            $query = "
                SELECT COUNT(*) as count
                FROM user_notifications
                WHERE user_id = ? 
                AND (event_type LIKE 'document_%' OR type_id = (SELECT type_id FROM notification_types WHERE type_name = 'document' LIMIT 1))
                AND deleted_at IS NULL
            ";

            if ($onlyUnread) {
                $query .= " AND is_read = 0";
            }

            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                error_log("DocumentNotificationHandler: Prepare failed in getDocumentNotificationCount - " . $this->conn->error);
                return 0;
            }

            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();

            return $row['count'] ?? 0;
        } catch (Exception $e) {
            error_log("DocumentNotificationHandler Exception in getDocumentNotificationCount: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Mark document notification as read
     */
    public function markDocumentNotificationAsRead($notificationId, $userId)
    {
        try {
            $query = "
                UPDATE user_notifications
                SET is_read = 1, read_at = NOW()
                WHERE notification_id = ? AND user_id = ? AND (event_type LIKE 'document_%' OR type_id = (SELECT type_id FROM notification_types WHERE type_name = 'document' LIMIT 1))
            ";

            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                error_log("DocumentNotificationHandler: Prepare failed in markDocumentNotificationAsRead - " . $this->conn->error);
                return false;
            }

            $stmt->bind_param("ii", $notificationId, $userId);
            $result = $stmt->execute();
            $stmt->close();

            return $result;
        } catch (Exception $e) {
            error_log("DocumentNotificationHandler Exception in markDocumentNotificationAsRead: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get recent document status changes for a specific application
     */
    public function getApplicationDocumentChanges($applicationId, $limit = 10)
    {
        try {
            $query = "
                SELECT 
                    d.document_id,
                    dt.document_name,
                    d.status,
                    d.status_updated_at,
                    d.rejection_notes,
                    da.old_status,
                    da.new_status,
                    da.admin_name,
                    da.changed_at
                FROM document_audit da
                JOIN documents d ON da.document_id = d.document_id
                JOIN document_types dt ON da.document_type_id = dt.document_type_id
                WHERE da.application_id = ?
                ORDER BY da.changed_at DESC
                LIMIT ?
            ";

            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                error_log("DocumentNotificationHandler: Prepare failed in getApplicationDocumentChanges - " . $this->conn->error);
                return [];
            }

            $stmt->bind_param("si", $applicationId, $limit);
            $stmt->execute();
            $result = $stmt->get_result();

            $changes = [];
            while ($row = $result->fetch_assoc()) {
                $changes[] = $row;
            }

            $stmt->close();
            return $changes;
        } catch (Exception $e) {
            error_log("DocumentNotificationHandler Exception in getApplicationDocumentChanges: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get admin ID from current session
     */
    private function getAdminIdFromSession()
    {
        if (isset($_SESSION['user_id'])) {
            return (int) $_SESSION['user_id'];
        }
        return null;
    }

    /**
     * Get summary of all document updates for an application
     */
    public function getDocumentUpdateSummary($applicationId)
    {
        try {
            $query = "
                SELECT 
                    COUNT(*) as total_updates,
                    SUM(CASE WHEN new_status = 'Approved' THEN 1 ELSE 0 END) as approved_count,
                    SUM(CASE WHEN new_status = 'Rejected' THEN 1 ELSE 0 END) as rejected_count,
                    MAX(changed_at) as last_updated
                FROM document_audit
                WHERE application_id = ?
            ";

            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                error_log("DocumentNotificationHandler: Prepare failed in getDocumentUpdateSummary - " . $this->conn->error);
                return null;
            }

            $stmt->bind_param("s", $applicationId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();

            return $row;
        } catch (Exception $e) {
            error_log("DocumentNotificationHandler Exception in getDocumentUpdateSummary: " . $e->getMessage());
            return null;
        }
    }
}
?>