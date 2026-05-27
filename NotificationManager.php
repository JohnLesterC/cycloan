<?php
/**
 * CYCLOAN Notification Manager - MySQLi Version
 * 
 * Simplified notification system for user dashboard
 * Compatible with MySQLi database connections
 * 
 * @author CYCLOAN Development Team
 * @version 2.0 (MySQLi Compatible)
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
     * Get user notifications with filtering and pagination
     */
    public function getUserNotifications($user_id, $filters = [], $page = 1, $per_page = 20)
    {
        try {
            // Debug log
            error_log("GET_NOTIFICATIONS: Fetching notifications for user_id=$user_id, page=$page, per_page=$per_page");

            // Check if tables exist first
            $tableCheck = $this->conn->query("SHOW TABLES LIKE 'user_notifications'");
            if (!$tableCheck || $tableCheck->num_rows === 0) {
                error_log("GET_NOTIFICATIONS ERROR: user_notifications table not found");
                return [
                    'notifications' => [],
                    'total_count' => 0,
                    'total_pages' => 0,
                    'current_page' => 1,
                    'per_page' => $per_page,
                    'error' => 'Notifications table not found'
                ];
            }

            $where_conditions = ["un.user_id = ?"];
            $params = [$user_id];
            $param_types = "i";

            // Apply filters
            if (isset($filters['is_read']) && $filters['is_read'] !== '') {
                $where_conditions[] = "un.is_read = ?";
                $params[] = (int) $filters['is_read'];
                $param_types .= "i";
            }

            if (isset($filters['type']) && !empty($filters['type'])) {
                $where_conditions[] = "nt.type_name = ?";
                $params[] = $filters['type'];
                $param_types .= "s";
            }

            if (isset($filters['search']) && !empty($filters['search'])) {
                $where_conditions[] = "(un.title LIKE ? OR un.message LIKE ?)";
                $search_term = '%' . $filters['search'] . '%';
                $params[] = $search_term;
                $params[] = $search_term;
                $param_types .= "ss";
            }

            $where_clause = implode(' AND ', $where_conditions);

            // First, get total count
            $count_sql = "
                SELECT COUNT(*) as total
                FROM user_notifications un
                JOIN notification_types nt ON un.type_id = nt.type_id
                WHERE {$where_clause}
            ";

            $count_stmt = $this->conn->prepare($count_sql);
            if (!$count_stmt) {
                error_log("GET_NOTIFICATIONS ERROR: Failed to prepare count query - " . $this->conn->error);
                return [
                    'notifications' => [],
                    'total_count' => 0,
                    'total_pages' => 0,
                    'current_page' => 1,
                    'per_page' => $per_page,
                    'error' => $this->conn->error
                ];
            }

            $count_stmt->bind_param($param_types, ...$params);
            $count_stmt->execute();
            $count_result = $count_stmt->get_result();
            $total_count = $count_result->fetch_assoc()['total'];
            error_log("GET_NOTIFICATIONS: Found $total_count total notifications for user_id=$user_id");

            // Calculate pagination
            $total_pages = ceil($total_count / $per_page);
            $offset = ($page - 1) * $per_page;

            // Get notifications
            $sql = "
                SELECT 
                    un.notification_id,
                    un.user_id,
                    un.title,
                    un.message,
                    un.short_message,
                    un.is_read,
                    un.priority,
                    un.action_url,
                    un.action_text,
                    un.created_at,
                    nt.type_name,
                    nt.type_display_name,
                    nt.icon_class,
                    nt.color_class
                FROM user_notifications un
                JOIN notification_types nt ON un.type_id = nt.type_id
                WHERE {$where_clause}
                ORDER BY un.created_at DESC
                LIMIT ? OFFSET ?
            ";

            // Add limit and offset parameters
            $params[] = $per_page;
            $params[] = $offset;
            $param_types .= "ii";

            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                return [
                    'notifications' => [],
                    'total_count' => 0,
                    'total_pages' => 0,
                    'current_page' => 1,
                    'per_page' => $per_page,
                    'error' => $this->conn->error
                ];
            }

            $stmt->bind_param($param_types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();

            $notifications = [];
            while ($row = $result->fetch_assoc()) {
                $notifications[] = $row;
            }

            error_log("GET_NOTIFICATIONS: Retrieved " . count($notifications) . " notifications for user_id=$user_id on page=$page");

            return [
                'notifications' => $notifications,
                'total_count' => $total_count,
                'total_pages' => $total_pages,
                'current_page' => $page,
                'per_page' => $per_page
            ];

        } catch (Exception $e) {
            error_log("GET_NOTIFICATIONS EXCEPTION: " . $e->getMessage());
            $this->errors[] = "Error fetching notifications: " . $e->getMessage();
            return [
                'notifications' => [],
                'total_count' => 0,
                'total_pages' => 0,
                'current_page' => 1,
                'per_page' => $per_page
            ];
        }
    }

    /**
     * Get unread notifications count by type
     */
    public function getUnreadCounts($user_id)
    {
        try {
            // Check if tables exist
            $tableCheck = $this->conn->query("SHOW TABLES LIKE 'user_notifications'");
            if (!$tableCheck || $tableCheck->num_rows === 0) {
                return ['total' => 0];
            }

            // Get counts by type
            $stmt = $this->conn->prepare("
                SELECT 
                    nt.type_name,
                    COUNT(*) as count
                FROM user_notifications un
                JOIN notification_types nt ON un.type_id = nt.type_id
                WHERE un.user_id = ? AND un.is_read = 0
                GROUP BY nt.type_id, nt.type_name
                ORDER BY count DESC
            ");

            if (!$stmt) {
                return ['total' => 0];
            }

            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $counts = [];
            while ($row = $result->fetch_assoc()) {
                $counts[$row['type_name']] = $row['count'];
            }

            // Get total unread count
            $total_stmt = $this->conn->prepare("
                SELECT COUNT(*) as total
                FROM user_notifications
                WHERE user_id = ? AND is_read = 0
            ");

            if (!$total_stmt) {
                return ['total' => 0];
            }

            $total_stmt->bind_param("i", $user_id);
            $total_stmt->execute();
            $total_result = $total_stmt->get_result();
            $total = $total_result->fetch_assoc();

            $result_counts = [
                'total' => $total['total'],
                'payment' => $counts['payment'] ?? 0,
                'status' => $counts['status'] ?? 0,
                'remark' => $counts['remark'] ?? 0,
                'security' => $counts['security'] ?? 0,
                'system' => $counts['system'] ?? 0,
                'reminder' => $counts['reminder'] ?? 0,
                'approval' => $counts['approval'] ?? 0,
                'document' => $counts['document'] ?? 0
            ];

            return $result_counts;

        } catch (Exception $e) {
            $this->errors[] = "Error fetching unread counts: " . $e->getMessage();
            return ['total' => 0];
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

            $ids = is_array($notification_ids) ? $notification_ids : [$notification_ids];
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';

            $stmt = $this->conn->prepare("
                UPDATE user_notifications 
                SET is_read = 1, updated_at = NOW() 
                WHERE user_id = ? AND notification_id IN ({$placeholders})
            ");

            $params = array_merge([$user_id], $ids);
            $types = 'i' . str_repeat('i', count($ids));
            $stmt->bind_param($types, ...$params);

            return $stmt->execute();

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
                SET is_read = 1, updated_at = NOW() 
                WHERE user_id = ? AND is_read = 0
            ");

            $stmt->bind_param("i", $user_id);
            return $stmt->execute();

        } catch (Exception $e) {
            $this->errors[] = "Error marking all notifications as read: " . $e->getMessage();
            return false;
        }
    }

    /**
     * Delete notifications
     */
    public function deleteNotifications($user_id, $notification_ids)
    {
        try {
            if (empty($notification_ids)) {
                return false;
            }

            $ids = is_array($notification_ids) ? $notification_ids : [$notification_ids];
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';

            $stmt = $this->conn->prepare("
                DELETE FROM user_notifications 
                WHERE user_id = ? AND notification_id IN ({$placeholders})
            ");

            $params = array_merge([$user_id], $ids);
            $types = 'i' . str_repeat('i', count($ids));
            $stmt->bind_param($types, ...$params);

            return $stmt->execute();

        } catch (Exception $e) {
            $this->errors[] = "Error deleting notifications: " . $e->getMessage();
            return false;
        }
    }

    /**
     * Get user notification settings
     */
    public function getUserSettings($user_id)
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT uns.type_id, uns.is_enabled, nt.type_name
                FROM user_notification_settings uns
                JOIN notification_types nt ON uns.type_id = nt.type_id
                WHERE uns.user_id = ?
            ");

            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();

            $settings = [];
            while ($row = $result->fetch_assoc()) {
                $settings[$row['type_name']] = $row['is_enabled'];
            }

            return $settings;

        } catch (Exception $e) {
            $this->errors[] = "Error fetching user settings: " . $e->getMessage();
            return [];
        }
    }

    /**
     * Create a simple notification
     */
    public function createNotification($user_id, $type_name, $title, $message, $priority = 'normal')
    {
        try {
            // Debug log
            error_log("NOTIFICATION: Creating notification for user_id=$user_id, type=$type_name, title=$title");

            // Get type_id
            $type_stmt = $this->conn->prepare("SELECT type_id FROM notification_types WHERE type_name = ?");
            if (!$type_stmt) {
                error_log("NOTIFICATION ERROR: Failed to prepare type lookup - " . $this->conn->error);
                return false;
            }

            $type_stmt->bind_param("s", $type_name);
            $type_stmt->execute();
            $type_result = $type_stmt->get_result();

            if ($type_result->num_rows === 0) {
                error_log("NOTIFICATION ERROR: Type not found: $type_name");
                return false;
            }

            $type_id = $type_result->fetch_assoc()['type_id'];
            error_log("NOTIFICATION: Found type_id=$type_id for type_name=$type_name");

            // Determine user type
            $user_type = 'user';  // Default
            $admin_check = $this->conn->query("SELECT 'admin2' as type FROM admin2 WHERE id = $user_id");
            if ($admin_check && $admin_check->num_rows > 0) {
                $user_type = 'admin2';
            } else {
                $admin1_check = $this->conn->query("SELECT 'admin1' as type FROM admin1 WHERE id = $user_id");
                if ($admin1_check && $admin1_check->num_rows > 0) {
                    $user_type = 'admin1';
                } else {
                    $sa_check = $this->conn->query("SELECT 'superadmin' as type FROM superadmins WHERE id = $user_id");
                    if ($sa_check && $sa_check->num_rows > 0) {
                        $user_type = 'superadmin';
                    }
                }
            }
            error_log("NOTIFICATION: Detected user_type=$user_type for user_id=$user_id");

            // Create notification - include user_type column
            $stmt = $this->conn->prepare("
                INSERT INTO user_notifications (user_id, user_type, type_id, title, message, short_message, priority, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            if (!$stmt) {
                error_log("NOTIFICATION ERROR: Failed to prepare insert - " . $this->conn->error);
                return false;
            }

            $short_message = strlen($message) > 100 ? substr($message, 0, 100) . '...' : $message;
            $stmt->bind_param("isissss", $user_id, $user_type, $type_id, $title, $message, $short_message, $priority);

            $result = $stmt->execute();
            if ($result) {
                error_log("NOTIFICATION SUCCESS: Created notification_id=" . $this->conn->insert_id . " for user_id=$user_id, user_type=$user_type");
            } else {
                error_log("NOTIFICATION ERROR: Failed to execute insert - " . $stmt->error);
            }

            return $result;

        } catch (Exception $e) {
            error_log("NOTIFICATION EXCEPTION: " . $e->getMessage());
            $this->errors[] = "Error creating notification: " . $e->getMessage();
            return false;
        }
    }

    /**
     * Get recent notifications
     */
    public function getRecentNotifications($user_id, $limit = 5)
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    un.notification_id,
                    un.title,
                    un.short_message,
                    un.is_read,
                    un.created_at,
                    nt.type_name,
                    nt.icon_class,
                    nt.color_class
                FROM user_notifications un
                JOIN notification_types nt ON un.type_id = nt.type_id
                WHERE un.user_id = ?
                ORDER BY un.created_at DESC
                LIMIT ?
            ");

            $stmt->bind_param("ii", $user_id, $limit);
            $stmt->execute();
            $result = $stmt->get_result();

            $notifications = [];
            while ($row = $result->fetch_assoc()) {
                $notifications[] = $row;
            }

            return $notifications;

        } catch (Exception $e) {
            $this->errors[] = "Error fetching recent notifications: " . $e->getMessage();
            return [];
        }
    }

    /**
     * Archive notifications (soft delete alternative)
     */
    public function archiveNotifications($user_id, $notification_ids)
    {
        try {
            if (empty($notification_ids)) {
                return false;
            }

            $ids = is_array($notification_ids) ? $notification_ids : [$notification_ids];
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';

            $stmt = $this->conn->prepare("
                UPDATE user_notifications 
                SET is_archived = 1, updated_at = NOW() 
                WHERE user_id = ? AND notification_id IN ({$placeholders})
            ");

            $params = array_merge([$user_id], $ids);
            $types = 'i' . str_repeat('i', count($ids));
            $stmt->bind_param($types, ...$params);

            return $stmt->execute();

        } catch (Exception $e) {
            $this->errors[] = "Error archiving notifications: " . $e->getMessage();
            return false;
        }
    }

    /**
     * Update user notification settings
     */
    public function updateUserSettings($user_id, $settings)
    {
        try {
            foreach ($settings as $type_name => $enabled) {
                // Get type_id
                $type_stmt = $this->conn->prepare("SELECT type_id FROM notification_types WHERE type_name = ?");
                $type_stmt->bind_param("s", $type_name);
                $type_stmt->execute();
                $type_result = $type_stmt->get_result();

                if ($type_result->num_rows === 0) {
                    continue;
                }

                $type_id = $type_result->fetch_assoc()['type_id'];

                // Update or insert setting
                $stmt = $this->conn->prepare("
                    INSERT INTO user_notification_settings (user_id, type_id, is_enabled) 
                    VALUES (?, ?, ?) 
                    ON DUPLICATE KEY UPDATE is_enabled = ?
                ");

                $enabled_int = $enabled ? 1 : 0;
                $stmt->bind_param("iiii", $user_id, $type_id, $enabled_int, $enabled_int);
                $stmt->execute();
            }

            return true;

        } catch (Exception $e) {
            $this->errors[] = "Error updating user settings: " . $e->getMessage();
            return false;
        }
    }

    /**
     * Get activity notifications from audit logs for admins
     * Fetches pre-approval, credit investigation, and loan status changes
     */
    public function getActivityNotifications($admin_id, $filters = [], $page = 1, $per_page = 20)
    {
        try {
            $where_conditions = ["1=1"];
            $params = [];
            $param_types = "";

            // Filter by search
            if (isset($filters['search']) && !empty($filters['search'])) {
                $where_conditions[] = "(al.description LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
                $search_term = '%' . $filters['search'] . '%';
                $params[] = $search_term;
                $params[] = $search_term;
                $params[] = $search_term;
                $param_types .= "sss";
            }

            // Filter by type
            if (isset($filters['activity_type']) && !empty($filters['activity_type'])) {
                $where_conditions[] = "al.activity_type LIKE ?";
                $params[] = '%' . $filters['activity_type'] . '%';
                $param_types .= "s";
            }

            $where_clause = implode(' AND ', $where_conditions);

            // Get total count
            $count_sql = "
                SELECT COUNT(*) as total
                FROM activity_logs al
                LEFT JOIN users1 u ON al.user_id = u.id
                WHERE {$where_clause} AND al.activity_type IN ('pre-approval', 'credit-investigation', 'loan-status')
            ";

            $count_stmt = $this->conn->prepare($count_sql);
            if (!$count_stmt) {
                return ['notifications' => [], 'total_count' => 0, 'total_pages' => 0];
            }

            if (!empty($params)) {
                $count_stmt->bind_param($param_types, ...$params);
            }
            $count_stmt->execute();
            $count_result = $count_stmt->get_result();
            $total_count = $count_result->fetch_assoc()['total'] ?? 0;

            // Calculate pagination
            $total_pages = ceil($total_count / $per_page);
            $offset = ($page - 1) * $per_page;

            // Get activities
            $sql = "
                SELECT 
                    al.id as activity_id,
                    al.activity_type,
                    al.description,
                    al.created_at,
                    CONCAT(u.first_name, ' ', u.last_name) as user_name,
                    la.application_id,
                    CASE 
                        WHEN al.activity_type = 'pre-approval' THEN 'fas fa-check-circle'
                        WHEN al.activity_type = 'credit-investigation' THEN 'fas fa-search'
                        WHEN al.activity_type = 'loan-status' THEN 'fas fa-file-invoice-dollar'
                        ELSE 'fas fa-info-circle'
                    END as icon_class,
                    CASE 
                        WHEN al.activity_type = 'pre-approval' THEN 'primary'
                        WHEN al.activity_type = 'credit-investigation' THEN 'warning'
                        WHEN al.activity_type = 'loan-status' THEN 'success'
                        ELSE 'info'
                    END as badge_color
                FROM activity_logs al
                LEFT JOIN users1 u ON al.user_id = u.id
                LEFT JOIN loan_applications la ON FIND_IN_SET(la.application_id, al.reference_id)
                WHERE {$where_clause} AND al.activity_type IN ('pre-approval', 'credit-investigation', 'loan-status')
                ORDER BY al.created_at DESC
                LIMIT ? OFFSET ?
            ";

            $sql_params = $params;
            $sql_param_types = $param_types . "ii";
            $sql_params[] = $per_page;
            $sql_params[] = $offset;

            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                return ['notifications' => [], 'total_count' => 0, 'total_pages' => 0];
            }

            $stmt->bind_param($sql_param_types, ...$sql_params);
            $stmt->execute();
            $result = $stmt->get_result();

            $activities = [];
            while ($row = $result->fetch_assoc()) {
                $activities[] = [
                    'activity_id' => $row['activity_id'],
                    'title' => ucfirst(str_replace('-', ' ', $row['activity_type'])) . ' Update',
                    'message' => $row['description'],
                    'icon_class' => $row['icon_class'],
                    'badge_color' => $row['badge_color'],
                    'user_name' => $row['user_name'],
                    'created_at' => $row['created_at'],
                    'application_id' => $row['application_id'],
                    'activity_type' => $row['activity_type']
                ];
            }

            return [
                'notifications' => $activities,
                'total_count' => $total_count,
                'total_pages' => $total_pages
            ];

        } catch (Exception $e) {
            $this->errors[] = "Error fetching activity notifications: " . $e->getMessage();
            return ['notifications' => [], 'total_count' => 0, 'total_pages' => 0];
        }
    }

    /**
     * Get errors
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Clear errors
     */
    public function clearErrors()
    {
        $this->errors = [];
    }
}

// Helper functions for common notification types
function createPaymentNotification($conn, $user_id, $payment_amount, $payment_id = null)
{
    $manager = new NotificationManager($conn);
    return $manager->createNotification(
        $user_id,
        'payment',
        'Payment Processed Successfully',
        "Your payment of ₱" . number_format($payment_amount, 2) . " has been processed successfully.",
        'normal'
    );
}

function createStatusNotification($conn, $user_id, $status_message, $priority = 'normal')
{
    $manager = new NotificationManager($conn);
    return $manager->createNotification(
        $user_id,
        'status',
        'Status Update',
        $status_message,
        $priority
    );
}

function createRemarkNotification($conn, $user_id, $remark_message)
{
    $manager = new NotificationManager($conn);
    return $manager->createNotification(
        $user_id,
        'remark',
        'New Comment',
        $remark_message,
        'normal'
    );
}

function createDocumentNotification($conn, $user_id, $document_message)
{
    $manager = new NotificationManager($conn);
    return $manager->createNotification(
        $user_id,
        'document',
        'Document Update',
        $document_message,
        'normal'
    );
}
?>