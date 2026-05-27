<?php
/**
 * CYCLOAN Admin Notification Integration Module
 * Handles notification functionality across all admin pages
 * Integrates with notification center
 * 
 * @package CYCLOAN
 * @version 1.0
 */

class AdminNotificationIntegration
{
    private $conn;
    private $adminId;
    private $adminRole;
    private $userId;

    public function __construct($database_connection, $admin_id, $admin_role)
    {
        $this->conn = $database_connection;
        $this->adminId = $admin_id;
        $this->adminRole = $admin_role;
    }

    /**
     * Send notification to specific admin or user
     */
    public function sendNotification($recipient_id, $recipient_role, $type, $title, $message, $action_url = null, $action_text = null, $priority = 'normal')
    {
        try {
            // Get or create notification type
            $stmt = $this->conn->prepare("SELECT type_id FROM notification_types WHERE type_name = ?");
            $stmt->bind_param("s", $type);
            $stmt->execute();
            $result = $stmt->get_result();
            $type_row = $result->fetch_assoc();
            $stmt->close();

            if (!$type_row) {
                // Default icon and color based on type
                $icon_map = [
                    'applicant_update' => 'fas fa-user-check',
                    'loan_status' => 'fas fa-file-contract',
                    'payment_reminder' => 'fas fa-money-bill',
                    'credit_investigation' => 'fas fa-search',
                    'admin_alert' => 'fas fa-exclamation-triangle',
                    'system_message' => 'fas fa-bell',
                    'audit_trail' => 'fas fa-clipboard-list',
                    'credit_rate' => 'fas fa-star'
                ];

                $color_map = [
                    'applicant_update' => 'color-success',
                    'loan_status' => 'color-info',
                    'payment_reminder' => 'color-warning',
                    'credit_investigation' => 'color-info',
                    'admin_alert' => 'color-error',
                    'system_message' => 'color-info',
                    'audit_trail' => 'color-info',
                    'credit_rate' => 'color-success'
                ];

                $icon = $icon_map[$type] ?? 'fas fa-bell';
                $color = $color_map[$type] ?? 'color-info';

                $stmt = $this->conn->prepare("
                    INSERT INTO notification_types (type_name, type_display_name, icon_class, color_class)
                    VALUES (?, ?, ?, ?)
                ");
                $display_name = ucwords(str_replace('_', ' ', $type));
                $stmt->bind_param("ssss", $type, $display_name, $icon, $color);
                $stmt->execute();
                $type_id = $this->conn->insert_id;
                $stmt->close();
            } else {
                $type_id = $type_row['type_id'];
            }

            // Insert notification
            $stmt = $this->conn->prepare("
                INSERT INTO user_notifications (user_id, type_id, title, message, short_message, action_url, action_text, priority, is_read, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())
            ");

            $short_message = substr($message, 0, 100);
            $stmt->bind_param("sissssss", $recipient_id, $type_id, $title, $message, $short_message, $action_url, $action_text, $priority);

            if ($stmt->execute()) {
                $stmt->close();
                return true;
            }
            $stmt->close();
            return false;

        } catch (Exception $e) {
            error_log("Notification Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Broadcast notification to all admins of a specific role
     */
    public function broadcastToAdminRole($role, $type, $title, $message, $action_url = null, $action_text = null, $priority = 'normal')
    {
        try {
            $table = $role === 'admin1' ? 'admin1' : ($role === 'admin2' ? 'admin2' : 'superadmins');

            $stmt = $this->conn->prepare("SELECT id FROM $table");
            $stmt->execute();
            $result = $stmt->get_result();

            $success_count = 0;
            while ($admin = $result->fetch_assoc()) {
                if ($this->sendNotification($admin['id'], $role, $type, $title, $message, $action_url, $action_text, $priority)) {
                    $success_count++;
                }
            }
            $stmt->close();

            return $success_count > 0;
        } catch (Exception $e) {
            error_log("Broadcast Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send applicant update notification
     */
    public function notifyApplicantUpdate($user_id, $applicant_name, $application_id, $status_change)
    {
        $title = "Applicant Status Update: $applicant_name";
        $message = "Applicant $applicant_name has been updated. Status: $status_change";
        $action_url = "applicant.php?id=$application_id";
        $action_text = "View Applicant";

        return $this->broadcastToAdminRole($this->adminRole, 'applicant_update', $title, $message, $action_url, $action_text, 'normal');
    }

    /**
     * Send loan status update notification
     */
    public function notifyLoanStatusUpdate($application_id, $loan_id, $old_status, $new_status, $user_name)
    {
        $title = "Loan Status Updated: $user_name";
        $message = "Loan status changed from '$old_status' to '$new_status'";
        $action_url = "active_records.php?loan_id=$loan_id";
        $action_text = "View Loan Details";

        return $this->broadcastToAdminRole($this->adminRole, 'loan_status', $title, $message, $action_url, $action_text, 'high');
    }

    /**
     * Send payment reminder notification
     */
    public function notifyPaymentReminder($payment_id, $user_name, $amount, $due_date)
    {
        $title = "Payment Reminder: $user_name";
        $message = "Payment of ₱" . number_format($amount, 2) . " is due on $due_date";
        $action_url = "active_records.php?view=payments";
        $action_text = "View Payment Schedule";

        return $this->broadcastToAdminRole($this->adminRole, 'payment_reminder', $title, $message, $action_url, $action_text, 'high');
    }

    /**
     * Send credit investigation update notification
     */
    public function notifyCreditInvestigation($application_id, $user_name, $investigation_status)
    {
        $title = "Credit Investigation: $user_name";
        $message = "Credit investigation status: $investigation_status";
        $action_url = "applicant.php?id=$application_id&tab=credit";
        $action_text = "View Credit Investigation";

        return $this->broadcastToAdminRole($this->adminRole, 'credit_investigation', $title, $message, $action_url, $action_text, 'normal');
    }

    /**
     * Send admin action alert
     */
    public function notifyAdminAction($action_type, $affected_resource, $details, $priority = 'normal')
    {
        $title = "Admin Action: " . ucfirst($action_type);
        $message = "$affected_resource - $details";
        $action_url = null;
        $action_text = null;

        if ($action_type === 'credit_rate_update') {
            $action_url = "manage_credit_points.php";
            $action_text = "View Credit Rates";
        } elseif ($action_type === 'admin_management') {
            $action_url = "add_admin.php";
            $action_text = "View Admins";
        }

        return $this->broadcastToAdminRole($this->adminRole, 'admin_alert', $title, $message, $action_url, $action_text, $priority);
    }

    /**
     * Send audit trail notification
     */
    public function notifyAuditTrail($user_name, $action, $module, $affected_id)
    {
        $title = "Audit Log: $action on $module";
        $message = "$user_name performed $action on $module (ID: $affected_id)";
        $action_url = "history_activity.php?filter_action=$action&filter_module=$module";
        $action_text = "View Audit Logs";

        return $this->sendNotification($this->adminId, $this->adminRole, 'audit_trail', $title, $message, $action_url, $action_text, 'normal');
    }

    /**
     * Get unread notification count for admin
     */
    public function getUnreadCount()
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as unread FROM user_notifications 
                WHERE user_id = ? AND is_read = 0
            ");
            $stmt->bind_param("i", $this->adminId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();

            return $row['unread'] ?? 0;
        } catch (Exception $e) {
            error_log("Unread Count Error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get recent notifications for widget
     */
    public function getRecentNotifications($limit = 5)
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    un.notification_id,
                    un.title,
                    un.message,
                    un.is_read,
                    un.priority,
                    un.action_url,
                    un.created_at,
                    nt.icon_class,
                    nt.color_class
                FROM user_notifications un
                JOIN notification_types nt ON un.type_id = nt.type_id
                WHERE un.user_id = ?
                ORDER BY un.created_at DESC
                LIMIT ?
            ");
            $stmt->bind_param("ii", $this->adminId, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            $notifications = [];

            while ($row = $result->fetch_assoc()) {
                $notifications[] = $row;
            }
            $stmt->close();

            return $notifications;
        } catch (Exception $e) {
            error_log("Recent Notifications Error: " . $e->getMessage());
            return [];
        }
    }
}

/**
 * Helper function to create notifications in admin pages
 */
function createAdminNotification($conn, $admin_id, $admin_role, $type, $title, $message, $action_url = null, $action_text = null, $priority = 'normal')
{
    $notificationHandler = new AdminNotificationIntegration($conn, $admin_id, $admin_role);
    return $notificationHandler->sendNotification($admin_id, $admin_role, $type, $title, $message, $action_url, $action_text, $priority);
}

/**
 * Render notification widget HTML
 */
function renderNotificationWidget($conn, $admin_id, $admin_role)
{
    $notificationHandler = new AdminNotificationIntegration($conn, $admin_id, $admin_role);
    $unreadCount = $notificationHandler->getUnreadCount();
    $recentNotifications = $notificationHandler->getRecentNotifications(5);

    $html = '
    <div class="notification-widget">
        <a href="notifications_enhanced.php" class="notification-icon-link" title="View all notifications">
            <i class="fas fa-bell"></i>
            ' . ($unreadCount > 0 ? '<span class="notification-badge">' . $unreadCount . '</span>' : '') . '
        </a>
        
        <div class="notification-dropdown" id="notificationDropdown">
            <div class="notification-dropdown-header">
                <h3>Notifications</h3>
                <a href="notifications_enhanced.php" class="view-all">View All</a>
            </div>
            
            <div class="notification-dropdown-list">';

    if (empty($recentNotifications)) {
        $html .= '<p class="empty-notification">No new notifications</p>';
    } else {
        foreach ($recentNotifications as $notif) {
            $time = timeAgo($notif['created_at']);
            $html .= '
            <div class="notification-item">
                <div class="notification-icon ' . ($notif['color_class'] ?? '') . '">
                    <i class="' . ($notif['icon_class'] ?? 'fas fa-bell') . '"></i>
                </div>
                <div class="notification-content">
                    <p class="notification-title">' . htmlspecialchars($notif['title']) . '</p>
                    <p class="notification-time">' . $time . '</p>
                </div>
            </div>';
        }
    }

    $html .= '
            </div>
        </div>
    </div>';

    return $html;
}

/**
 * Helper function for time ago
 */
function timeAgo($timestamp)
{
    $time = strtotime($timestamp);
    $time_diff = time() - $time;

    if ($time_diff < 60)
        return 'Just now';
    elseif ($time_diff < 3600)
        return round($time_diff / 60) . 'm ago';
    elseif ($time_diff < 86400)
        return round($time_diff / 3600) . 'h ago';
    elseif ($time_diff < 604800)
        return round($time_diff / 86400) . 'd ago';
    else
        return date('M j, Y', $time);
}

?>