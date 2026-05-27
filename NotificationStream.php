<?php

/**
 * NotificationStream - Real-Time Notification Manager
 * 
 * Manages real-time notifications for the admin dashboard.
 * Handles notification querying, processing, and metadata generation.
 */

class NotificationStream
{
    private $conn;
    private $userId;
    private $role;

    // Notification type to icon mapping
    private $iconMap = [
        'loan_created' => 'fa-file-contract',
        'loan_updated' => 'fa-edit',
        'loan_approved' => 'fa-check-circle',
        'loan_rejected' => 'fa-times-circle',
        'payment_received' => 'fa-money-bill-wave',
        'payment_overdue' => 'fa-exclamation-triangle',
        'document_uploaded' => 'fa-file-upload',
        'document_verified' => 'fa-file-check',
        'status_changed' => 'fa-sync-alt',
        'assignment_received' => 'fa-inbox',
        'comment_added' => 'fa-comment',
        'reminder_sent' => 'fa-bell',
        'system_alert' => 'fa-exclamation-circle',
        'user_login' => 'fa-sign-in-alt',
        'password_reset' => 'fa-key',
        'profile_updated' => 'fa-user-edit',
        'interest_rate_changed' => 'fa-chart-line'
    ];

    // Priority color scheme
    private $priorityColors = [
        'critical' => '#dc3545',
        'high' => '#fd7e14',
        'normal' => '#0dcaf0',
        'low' => '#6c757d'
    ];

    /**
     * Constructor
     */
    public function __construct($conn, $userId, $role)
    {
        $this->conn = $conn;
        $this->userId = $userId;
        $this->role = $role;
    }

    /**
     * Get new notifications since last ID
     */
    public function getNewNotifications($lastNotificationId = 0)
    {
        // Query for notifications targeted at this user/role
        $query = "
            SELECT 
                n.id,
                n.notification_type as type,
                n.title,
                n.message,
                n.module,
                n.action,
                n.related_id,
                n.priority,
                n.created_at,
                u.firstname as sender_name,
                u.lastname as sender_lastname,
                n.is_read,
                n.sent_at
            FROM notifications n
            LEFT JOIN admin2 u ON n.sender_id = u.id
            WHERE 
                (
                    n.recipient_id = ? OR 
                    n.recipient_role = ? OR
                    n.recipient_id IS NULL
                )
                AND n.id > ?
                AND n.status = 'active'
            ORDER BY n.created_at DESC
            LIMIT 50
        ";

        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            error_log('Notification query preparation failed: ' . $this->conn->error);
            return [];
        }

        $stmt->bind_param("isi", $this->userId, $this->role, $lastNotificationId);
        $stmt->execute();
        $result = $stmt->get_result();

        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }

        $stmt->close();

        return $notifications;
    }

    /**
     * Get total unread notification count
     */
    public function getUnreadCount()
    {
        $query = "
            SELECT COUNT(*) as count
            FROM notifications
            WHERE 
                (recipient_id = ? OR recipient_role = ? OR recipient_id IS NULL)
                AND is_read = 0
                AND status = 'active'
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("is", $this->userId, $this->role);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return $row['count'] ?? 0;
    }

    /**
     * Get notification icon based on type
     */
    public function getNotificationIcon($type)
    {
        return $this->iconMap[$type] ?? 'fa-bell';
    }

    /**
     * Get notification action URL
     */
    public function getNotificationActionUrl($notification)
    {
        $type = $notification['type'];
        $relatedId = $notification['related_id'] ?? null;

        // Map notification types to dashboard sections
        $urlMap = [
            'loan_created' => 'admin2_dashboard.php?tab=loan-applicants&id=' . $relatedId,
            'loan_updated' => 'admin2_dashboard.php?tab=loan-applicants&id=' . $relatedId,
            'loan_approved' => 'admin2_dashboard.php?tab=loan-applicants&id=' . $relatedId,
            'loan_rejected' => 'admin2_dashboard.php?tab=loan-applicants&id=' . $relatedId,
            'payment_received' => 'admin2_dashboard.php?tab=loan-applicants&id=' . $relatedId,
            'payment_overdue' => 'admin2_dashboard.php?tab=due-accounts',
            'document_uploaded' => 'admin2_dashboard.php?tab=loan-applicants&id=' . $relatedId,
            'status_changed' => 'admin2_dashboard.php?tab=loan-applicants&id=' . $relatedId,
            'user_login' => 'admin2_dashboard.php?tab=activity-logs',
        ];

        return $urlMap[$type] ?? 'admin2_dashboard.php';
    }

    /**
     * Mark notification as sent (one-time delivery)
     */
    public function markNotificationAsSent($notificationId)
    {
        $query = "UPDATE notifications SET sent_at = NOW() WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("i", $notificationId);
            $stmt->execute();
            $stmt->close();
        }
    }

    /**
     * Mark notification as read
     */
    public function markNotificationAsRead($notificationId)
    {
        $query = "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("i", $notificationId);
            $stmt->execute();
            $stmt->close();
        }
    }

    /**
     * Create a new notification in the database
     */
    public function createNotification($data)
    {
        // Validate required fields
        $required = ['title', 'message', 'notification_type'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return false;
            }
        }

        $query = "
            INSERT INTO notifications 
            (title, message, notification_type, module, action, priority, 
             recipient_id, recipient_role, sender_id, related_id, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
        ";

        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            error_log('Notification insert preparation failed: ' . $this->conn->error);
            return false;
        }

        $title = $data['title'] ?? '';
        $message = $data['message'] ?? '';
        $type = $data['notification_type'] ?? '';
        $module = $data['module'] ?? 'system';
        $action = $data['action'] ?? 'update';
        $priority = $data['priority'] ?? 'normal';
        $recipientId = $data['recipient_id'] ?? null;
        $recipientRole = $data['recipient_role'] ?? null;
        $senderId = $data['sender_id'] ?? null;
        $relatedId = $data['related_id'] ?? null;

        $stmt->bind_param(
            "sssssssiii",
            $title,
            $message,
            $type,
            $module,
            $action,
            $priority,
            $recipientId,
            $recipientRole,
            $senderId,
            $relatedId
        );

        if ($stmt->execute()) {
            $notificationId = $stmt->insert_id;
            $stmt->close();
            return $notificationId;
        }

        $stmt->close();
        return false;
    }

    /**
     * Get priority color for badge
     */
    public function getPriorityColor($priority)
    {
        return $this->priorityColors[$priority] ?? $this->priorityColors['normal'];
    }

    /**
     * Get notification history for dashboard
     */
    public function getNotificationHistory($limit = 20)
    {
        $query = "
            SELECT 
                n.id,
                n.notification_type as type,
                n.title,
                n.message,
                n.priority,
                n.created_at,
                u.firstname as sender_name,
                n.is_read
            FROM notifications n
            LEFT JOIN admin2 u ON n.sender_id = u.id
            WHERE 
                (n.recipient_id = ? OR n.recipient_role = ? OR n.recipient_id IS NULL)
                AND n.status = 'active'
            ORDER BY n.created_at DESC
            LIMIT ?
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("isi", $this->userId, $this->role, $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        $history = [];
        while ($row = $result->fetch_assoc()) {
            $history[] = $row;
        }

        $stmt->close();
        return $history;
    }

    /**
     * Cleanup old notifications (older than 30 days)
     */
    public function cleanupOldNotifications()
    {
        $query = "
            DELETE FROM notifications 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND is_read = 1
        ";

        $stmt = $this->conn->prepare($query);
        if ($stmt) {
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            return $affected;
        }

        return 0;
    }
}

?>