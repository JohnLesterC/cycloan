<?php
/**
 * CYCLOAN Notification Manager
 * 
 * Comprehensive notification system for user dashboard
 * Features: Template-based notifications, real-time updates, privacy compliance
 * 
 * @author CYCLOAN Development Team
 * @version 1.0
 * @created 2025-11-13
 */

class NotificationManager
{
    private $conn;
    private $errors = [];

    public function __construct($database_connection)
    {
        $this->conn = $database_connection;
    }

    /**
     * Create a new notification from template
     */
    public function createFromTemplate($user_id, $user_type, $template_name, $variables = [], $options = [])
    {
        try {
            // Default options
            $defaults = [
                'priority' => 'normal',
                'expires_at' => null,
                'event_type' => null,
                'event_id' => null,
                'related_table' => null,
                'related_id' => null
            ];
            $options = array_merge($defaults, $options);

            // Get template
            $template = $this->getTemplate($template_name);
            if (!$template) {
                $this->errors[] = "Template '$template_name' not found";
                return false;
            }

            // Check user notification settings
            if (!$this->isNotificationEnabled($user_id, $template['type_id'])) {
                return true; // Skip silently if user has disabled this type
            }

            // Process template variables
            $processed = $this->processTemplate($template, $variables);

            // Insert notification
            $stmt = $this->conn->prepare("
                INSERT INTO user_notifications (
                    user_id, user_type, type_id, title, message, short_message,
                    event_type, event_id, related_table, related_id,
                    action_url, action_text, button_class, priority, expires_at, metadata
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $metadata = json_encode($variables);

            $result = $stmt->execute([
                $user_id,
                $user_type,
                $template['type_id'],
                $processed['title'],
                $processed['message'],
                $processed['short_message'],
                $options['event_type'],
                $options['event_id'],
                $options['related_table'],
                $options['related_id'],
                $processed['action_url'],
                $processed['action_text'],
                $template['button_class'] ?? 'btn-primary',
                $options['priority'],
                $options['expires_at'],
                $metadata
            ]);

            if ($result) {
                $notification_id = $this->conn->lastInsertId();

                // Log activity
                $this->logNotificationActivity($user_id, $user_type, 'create', $processed['title'], $notification_id);

                return $notification_id;
            }

            return false;

        } catch (Exception $e) {
            $this->errors[] = "Error creating notification: " . $e->getMessage();
            error_log("NotificationManager Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a custom notification (without template)
     */
    public function createCustom($user_id, $user_type, $type_name, $title, $message, $options = [])
    {
        try {
            // Get notification type
            $stmt = $this->conn->prepare("SELECT type_id FROM notification_types WHERE type_name = ? AND is_active = 1");
            $stmt->execute([$type_name]);
            $type = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$type) {
                $this->errors[] = "Invalid notification type: $type_name";
                return false;
            }

            // Check user settings
            if (!$this->isNotificationEnabled($user_id, $type['type_id'])) {
                return true; // Skip silently
            }

            // Default options
            $defaults = [
                'short_message' => null,
                'action_url' => null,
                'action_text' => null,
                'button_class' => 'btn-primary',
                'priority' => 'normal',
                'expires_at' => null,
                'event_type' => null,
                'event_id' => null,
                'related_table' => null,
                'related_id' => null,
                'metadata' => null
            ];
            $options = array_merge($defaults, $options);

            // Insert notification
            $stmt = $this->conn->prepare("
                INSERT INTO user_notifications (
                    user_id, user_type, type_id, title, message, short_message,
                    event_type, event_id, related_table, related_id,
                    action_url, action_text, button_class, priority, expires_at, metadata
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $metadata = $options['metadata'] ? json_encode($options['metadata']) : null;

            $result = $stmt->execute([
                $user_id,
                $user_type,
                $type['type_id'],
                $title,
                $message,
                $options['short_message'],
                $options['event_type'],
                $options['event_id'],
                $options['related_table'],
                $options['related_id'],
                $options['action_url'],
                $options['action_text'],
                $options['button_class'],
                $options['priority'],
                $options['expires_at'],
                $metadata
            ]);

            if ($result) {
                $notification_id = $this->conn->lastInsertId();
                $this->logNotificationActivity($user_id, $user_type, 'create', $title, $notification_id);
                return $notification_id;
            }

            return false;

        } catch (Exception $e) {
            $this->errors[] = "Error creating custom notification: " . $e->getMessage();
            error_log("NotificationManager Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user notifications with filtering
     */
    public function getUserNotifications($user_id, $filters = [], $limit = 20, $offset = 0)
    {
        try {
            $where_conditions = ["un.user_id = ?", "un.deleted_at IS NULL"];
            $params = [$user_id];

            // Apply filters
            if (isset($filters['is_read']) && $filters['is_read'] !== '') {
                $where_conditions[] = "un.is_read = ?";
                $params[] = (int) $filters['is_read'];
            }

            if (isset($filters['is_archived']) && $filters['is_archived'] !== '') {
                $where_conditions[] = "un.is_archived = ?";
                $params[] = (int) $filters['is_archived'];
            }

            if (isset($filters['type_name']) && !empty($filters['type_name'])) {
                $where_conditions[] = "nt.type_name = ?";
                $params[] = $filters['type_name'];
            }

            if (isset($filters['priority']) && !empty($filters['priority'])) {
                $where_conditions[] = "un.priority = ?";
                $params[] = $filters['priority'];
            }

            if (isset($filters['date_from']) && !empty($filters['date_from'])) {
                $where_conditions[] = "DATE(un.created_at) >= ?";
                $params[] = $filters['date_from'];
            }

            if (isset($filters['date_to']) && !empty($filters['date_to'])) {
                $where_conditions[] = "DATE(un.created_at) <= ?";
                $params[] = $filters['date_to'];
            }

            if (isset($filters['search']) && !empty($filters['search'])) {
                $where_conditions[] = "(un.title LIKE ? OR un.message LIKE ?)";
                $search_term = '%' . $filters['search'] . '%';
                $params[] = $search_term;
                $params[] = $search_term;
            }

            // Exclude expired notifications
            $where_conditions[] = "(un.expires_at IS NULL OR un.expires_at > NOW())";

            $where_clause = implode(' AND ', $where_conditions);

            $sql = "
                SELECT 
                    un.*,
                    nt.type_name,
                    nt.type_display_name,
                    nt.icon_class,
                    nt.color_class,
                    CASE 
                        WHEN un.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 'just_now'
                        WHEN un.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 'today'
                        WHEN un.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 'this_week'
                        ELSE 'older'
                    END as time_category,
                    DATE_FORMAT(un.created_at, '%Y-%m-%d %H:%i') as formatted_date
                FROM user_notifications un
                JOIN notification_types nt ON un.type_id = nt.type_id
                WHERE {$where_clause}
                ORDER BY 
                    un.priority = 'urgent' DESC,
                    un.priority = 'high' DESC,
                    un.is_read ASC,
                    un.created_at DESC
                LIMIT ? OFFSET ?
            ";

            $params[] = $limit;
            $params[] = $offset;

            $stmt = $this->conn->prepare($sql);
            
            // Build parameter types string for MySQLi
            $types = str_repeat('s', count($params) - 2) . 'ii'; // Most are strings, last 2 are integers (limit, offset)
            
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $notifications = [];
            while ($row = $result->fetch_assoc()) {
                $notifications[] = $row;
            }
            
            return $notifications;

        } catch (Exception $e) {
            $this->errors[] = "Error fetching notifications: " . $e->getMessage();
            return [];
        }
    }

    /**
     * Get unread notifications count by type
     */
    public function getUnreadCounts($user_id)
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    nt.type_name,
                    nt.type_display_name,
                    nt.icon_class,
                    nt.color_class,
                    COUNT(*) as count
                FROM user_notifications un
                JOIN notification_types nt ON un.type_id = nt.type_id
                WHERE un.user_id = ? 
                    AND un.is_read = 0 
                    AND un.is_archived = 0 
                    AND un.deleted_at IS NULL
                    AND (un.expires_at IS NULL OR un.expires_at > NOW())
                GROUP BY nt.type_id, nt.type_name, nt.type_display_name, nt.icon_class, nt.color_class
                ORDER BY count DESC
            ");

            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $counts = [];
            while ($row = $result->fetch_assoc()) {
                $counts[] = $row;
            }

            // Get total unread count
            $total_stmt = $this->conn->prepare("
                SELECT COUNT(*) as total
                FROM user_notifications
                WHERE user_id = ? 
                    AND is_read = 0 
                    AND is_archived = 0 
                    AND deleted_at IS NULL
                    AND (expires_at IS NULL OR expires_at > NOW())
            ");

            $total_stmt->bind_param("i", $user_id);
            $total_stmt->execute();
            $total_result = $total_stmt->get_result();
            $total = $total_result->fetch_assoc();

            return [
                'by_type' => $counts,
                'total' => $total['total']
            ];

        } catch (Exception $e) {
            $this->errors[] = "Error fetching unread counts: " . $e->getMessage();
            return ['by_type' => [], 'total' => 0];
        }
    }

    /**
     * Mark notifications as read
     */
    public function markAsRead($user_id, $notification_ids)
    {
        try {
            if (empty($notification_ids)) {
                return false;
            }

            // Ensure $notification_ids is an array
            if (!is_array($notification_ids)) {
                $notification_ids = [$notification_ids];
            }

            $placeholders = implode(',', array_fill(0, count($notification_ids), '?'));
            $params = array_merge([$user_id], $notification_ids);

            $stmt = $this->conn->prepare("
                UPDATE user_notifications 
                SET is_read = 1, read_at = NOW() 
                WHERE user_id = ? AND notification_id IN ($placeholders)
            ");

            return $stmt->execute($params);

        } catch (Exception $e) {
            $this->errors[] = "Error marking notifications as read: " . $e->getMessage();
            return false;
        }
    }

    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead($user_id)
    {
        try {
            $stmt = $this->conn->prepare("
                UPDATE user_notifications 
                SET is_read = 1, read_at = NOW() 
                WHERE user_id = ? AND is_read = 0 AND deleted_at IS NULL
            ");

            return $stmt->execute([$user_id]);

        } catch (Exception $e) {
            $this->errors[] = "Error marking all notifications as read: " . $e->getMessage();
            return false;
        }
    }

    /**
     * Archive notifications
     */
    public function archiveNotifications($user_id, $notification_ids)
    {
        try {
            if (empty($notification_ids)) {
                return false;
            }

            if (!is_array($notification_ids)) {
                $notification_ids = [$notification_ids];
            }

            $placeholders = implode(',', array_fill(0, count($notification_ids), '?'));
            $params = array_merge([$user_id], $notification_ids);

            $stmt = $this->conn->prepare("
                UPDATE user_notifications 
                SET is_archived = 1, archived_at = NOW() 
                WHERE user_id = ? AND notification_id IN ($placeholders)
            ");

            return $stmt->execute($params);

        } catch (Exception $e) {
            $this->errors[] = "Error archiving notifications: " . $e->getMessage();
            return false;
        }
    }

    /**
     * Delete notifications (soft delete)
     */
    public function deleteNotifications($user_id, $notification_ids)
    {
        try {
            if (empty($notification_ids)) {
                return false;
            }

            if (!is_array($notification_ids)) {
                $notification_ids = [$notification_ids];
            }

            $placeholders = implode(',', array_fill(0, count($notification_ids), '?'));
            $params = array_merge([$user_id], $notification_ids);

            $stmt = $this->conn->prepare("
                UPDATE user_notifications 
                SET deleted_at = NOW() 
                WHERE user_id = ? AND notification_id IN ($placeholders)
            ");

            return $stmt->execute($params);

        } catch (Exception $e) {
            $this->errors[] = "Error deleting notifications: " . $e->getMessage();
            return false;
        }
    }

    /**
     * Get notification settings for a user
     */
    public function getUserSettings($user_id)
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    uns.*,
                    nt.type_name,
                    nt.type_display_name,
                    nt.description
                FROM user_notification_settings uns
                JOIN notification_types nt ON uns.type_id = nt.type_id
                WHERE uns.user_id = ?
                ORDER BY nt.type_display_name
            ");

            $stmt->execute([$user_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            $this->errors[] = "Error fetching user settings: " . $e->getMessage();
            return [];
        }
    }

    /**
     * Update notification settings
     */
    public function updateUserSettings($user_id, $settings)
    {
        try {
            foreach ($settings as $type_id => $setting) {
                $stmt = $this->conn->prepare("
                    INSERT INTO user_notification_settings (
                        user_id, type_id, is_enabled, email_enabled, sms_enabled, 
                        push_enabled, sound_enabled, frequency
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        is_enabled = VALUES(is_enabled),
                        email_enabled = VALUES(email_enabled),
                        sms_enabled = VALUES(sms_enabled),
                        push_enabled = VALUES(push_enabled),
                        sound_enabled = VALUES(sound_enabled),
                        frequency = VALUES(frequency),
                        updated_at = NOW()
                ");

                $stmt->execute([
                    $user_id,
                    $type_id,
                    $setting['is_enabled'] ?? 1,
                    $setting['email_enabled'] ?? 0,
                    $setting['sms_enabled'] ?? 0,
                    $setting['push_enabled'] ?? 1,
                    $setting['sound_enabled'] ?? 1,
                    $setting['frequency'] ?? 'instant'
                ]);
            }

            return true;

        } catch (Exception $e) {
            $this->errors[] = "Error updating user settings: " . $e->getMessage();
            return false;
        }
    }

    /**
     * Get recent notifications for dashboard widget
     */
    public function getRecentNotifications($user_id, $limit = 5)
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    un.*,
                    nt.type_name,
                    nt.type_display_name,
                    nt.icon_class,
                    nt.color_class,
                    CASE 
                        WHEN un.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN CONCAT(TIMESTAMPDIFF(MINUTE, un.created_at, NOW()), ' min ago')
                        WHEN un.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN CONCAT(TIMESTAMPDIFF(HOUR, un.created_at, NOW()), ' hr ago')
                        WHEN un.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN CONCAT(TIMESTAMPDIFF(DAY, un.created_at, NOW()), ' days ago')
                        ELSE DATE_FORMAT(un.created_at, '%M %d, %Y')
                    END as time_ago
                FROM user_notifications un
                JOIN notification_types nt ON un.type_id = nt.type_id
                WHERE un.user_id = ? 
                    AND un.deleted_at IS NULL
                    AND (un.expires_at IS NULL OR un.expires_at > NOW())
                ORDER BY 
                    un.priority = 'urgent' DESC,
                    un.priority = 'high' DESC,
                    un.is_read ASC,
                    un.created_at DESC
                LIMIT ?
            ");

            $stmt->execute([$user_id, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            $this->errors[] = "Error fetching recent notifications: " . $e->getMessage();
            return [];
        }
    }

    /**
     * Get notification statistics
     */
    public function getNotificationStats($user_id, $days = 30)
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END) as read_count,
                    SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread_count,
                    SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent_count,
                    SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high_count
                FROM user_notifications
                WHERE user_id = ? 
                    AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                    AND deleted_at IS NULL
            ");

            $stmt->execute([$user_id, $days]);
            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            $this->errors[] = "Error fetching notification stats: " . $e->getMessage();
            return [];
        }
    }

    // ==================== PRIVATE HELPER METHODS ====================

    /**
     * Get notification template
     */
    private function getTemplate($template_name)
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT * FROM notification_templates 
                WHERE template_name = ? AND is_active = 1
            ");
            $stmt->execute([$template_name]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Process template with variables
     */
    private function processTemplate($template, $variables)
    {
        $processed = [
            'title' => $this->replaceVariables($template['title_template'], $variables),
            'message' => $this->replaceVariables($template['message_template'], $variables),
            'short_message' => $template['short_message_template'] ?
                $this->replaceVariables($template['short_message_template'], $variables) : null,
            'action_text' => $template['action_text_template'] ?
                $this->replaceVariables($template['action_text_template'], $variables) : null,
            'action_url' => $template['action_url_template'] ?
                $this->replaceVariables($template['action_url_template'], $variables) : null
        ];

        return $processed;
    }

    /**
     * Replace template variables
     */
    private function replaceVariables($template, $variables)
    {
        if (empty($template) || empty($variables)) {
            return $template;
        }

        foreach ($variables as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }

        return $template;
    }

    /**
     * Check if notification type is enabled for user
     */
    private function isNotificationEnabled($user_id, $type_id)
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT is_enabled FROM user_notification_settings 
                WHERE user_id = ? AND type_id = ?
            ");
            $stmt->execute([$user_id, $type_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            // Default to enabled if no setting found
            return $result ? (bool) $result['is_enabled'] : true;

        } catch (Exception $e) {
            return true; // Default to enabled on error
        }
    }

    /**
     * Log notification activity
     */
    private function logNotificationActivity($user_id, $user_role, $action, $description, $notification_id)
    {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO activity_logs (user_id, user_role, action_type, module, description, affected_id)
                VALUES (?, ?, ?, 'notification', ?, ?)
            ");

            $stmt->execute([
                $user_id,
                strtoupper($user_role),
                $action,
                $description,
                $notification_id
            ]);
        } catch (Exception $e) {
            // Log error but don't fail the main operation
            error_log("Failed to log notification activity: " . $e->getMessage());
        }
    }

    /**
     * Get error messages
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Clear error messages
     */
    public function clearErrors()
    {
        $this->errors = [];
    }
}

/**
 * Notification Helper Functions
 * Global functions for easy notification creation throughout the application
 */

/**
 * Quick notification for payment events
 */
function createPaymentNotification($user_id, $type, $payment_data)
{
    global $conn;

    if (!$conn)
        return false;

    $notificationManager = new NotificationManager($conn);

    $templates = [
        'received' => 'payment_received',
        'overdue' => 'payment_overdue',
        'reminder' => 'payment_reminder'
    ];

    if (!isset($templates[$type]))
        return false;

    return $notificationManager->createFromTemplate(
        $user_id,
        'user',
        $templates[$type],
        $payment_data,
        [
            'priority' => $type === 'overdue' ? 'high' : 'normal',
            'event_type' => 'payment',
            'event_id' => $payment_data['payment_id'] ?? null,
            'related_table' => 'payment_schedules',
            'related_id' => $payment_data['payment_id'] ?? null
        ]
    );
}

/**
 * Quick notification for status updates
 */
function createStatusNotification($user_id, $status_data)
{
    global $conn;

    if (!$conn)
        return false;

    $notificationManager = new NotificationManager($conn);

    $template_map = [
        'Approved' => 'application_approved',
        'Rejected' => 'application_rejected'
    ];

    $template = $template_map[$status_data['new_status']] ?? 'status_update';

    return $notificationManager->createFromTemplate(
        $user_id,
        'user',
        $template,
        $status_data,
        [
            'priority' => $status_data['new_status'] === 'Approved' ? 'high' : 'normal',
            'event_type' => 'status_change',
            'event_id' => $status_data['application_id'] ?? null,
            'related_table' => 'loan_applications',
            'related_id' => $status_data['application_id'] ?? null
        ]
    );
}

/**
 * Quick notification for remarks/comments
 */
function createRemarkNotification($user_id, $remark_data)
{
    global $conn;

    if (!$conn)
        return false;

    $notificationManager = new NotificationManager($conn);

    return $notificationManager->createFromTemplate(
        $user_id,
        'user',
        'new_remark',
        $remark_data,
        [
            'priority' => 'normal',
            'event_type' => 'remark',
            'event_id' => $remark_data['remark_id'] ?? null,
            'related_table' => 'remarks',
            'related_id' => $remark_data['remark_id'] ?? null
        ]
    );
}

/**
 * Quick notification for document updates
 */
function createDocumentNotification($user_id, $document_data)
{
    global $conn;

    if (!$conn)
        return false;

    $notificationManager = new NotificationManager($conn);

    $template = $document_data['status'] === 'Approved' ? 'document_approved' : 'document_rejected';

    return $notificationManager->createFromTemplate(
        $user_id,
        'user',
        $template,
        $document_data,
        [
            'priority' => $document_data['status'] === 'Rejected' ? 'high' : 'normal',
            'event_type' => 'document_update',
            'event_id' => $document_data['document_id'] ?? null,
            'related_table' => 'documents',
            'related_id' => $document_data['document_id'] ?? null
        ]
    );
}

/**
 * Quick security notification
 */
function createSecurityNotification($user_id, $security_data)
{
    global $conn;

    if (!$conn)
        return false;

    $notificationManager = new NotificationManager($conn);

    return $notificationManager->createFromTemplate(
        $user_id,
        'user',
        'login_notification',
        $security_data,
        [
            'priority' => 'high',
            'event_type' => 'security',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days'))
        ]
    );
}

?>