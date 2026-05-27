<?php
/**
 * Notification Manager
 * Handles all notification creation and retrieval for users and admins
 */

require_once 'CYCLOAN_db.php';

class NotificationManagerAPI
{
    private $conn;

    public function __construct($mysqli_connection)
    {
        $this->conn = $mysqli_connection;
    }

    /**
     * Create a notification for a user or admin
     * 
     * @param int $user_id - User ID (can be user, admin1, admin2, or superadmin)
     * @param string $title - Notification title
     * @param string $message - Notification message
     * @param string $type - Type: 'info', 'warning', 'success', 'error', 'reminder'
     * @param string $category - Category: 'loan', 'payment', 'document', 'account', 'system'
     * @param string $priority - Priority: 'low', 'normal', 'high', 'urgent'
     * @param string $action_url - Optional URL for action
     * @param datetime $expires_at - Optional expiration time
     * 
     * @return bool - Success or failure
     */
    public function createNotification($user_id, $title, $message, $type = 'info', $category = 'system', $priority = 'normal', $action_url = null, $expires_at = null)
    {
        try {
            // Insert into user_notifications table (not notifications)
            $stmt = $this->conn->prepare("
                INSERT INTO user_notifications 
                (user_id, user_type, type_id, title, message, short_message, event_type, priority, action_url, expires_at, created_at)
                VALUES (?, ?, 
                    (SELECT type_id FROM notification_types WHERE type_name = ? LIMIT 1),
                    ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            if ($stmt === false) {
                error_log("Notification insert prepare failed: " . $this->conn->error);
                return false;
            }

            // Determine user_type (default: 'user')
            $user_type = 'user';

            $stmt->bind_param(
                "isssssssss",
                $user_id,           // user_id (i)
                $user_type,         // user_type (s) - 'user'
                $type,              // type_name for lookup (s)
                $title,             // title (s)
                $message,           // message (s)
                $message,           // short_message (s)
                $category,          // event_type (s)
                $priority,          // priority (s)
                $action_url,        // action_url (s)
                $expires_at         // expires_at (s)
            );

            $result = $stmt->execute();
            $stmt->close();

            if ($result) {
                error_log("Notification created for user $user_id: $title (type=$type, priority=$priority)");
                return true;
            } else {
                error_log("Notification insert failed: " . $this->conn->error);
                return false;
            }
        } catch (Exception $e) {
            error_log("Notification creation error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create notification for loan calculator action
     */
    public function notifyLoanCalculation($user_id, $loan_type, $amount, $term_length, $repayment_frequency)
    {
        $title = "Loan Calculation Completed";
        $message = "You calculated a $loan_type loan for ₱" . number_format($amount, 2) . " with $term_length-month term ($repayment_frequency repayment).";
        $action_url = "user_dashboard.php";

        return $this->createNotification(
            $user_id,
            $title,
            $message,
            'success',
            'loan',
            'normal',
            $action_url
        );
    }

    /**
     * Create notification for loan application submitted
     */
    public function notifyLoanApplicationSubmitted($user_id, $application_id, $loan_type, $amount, $admin_ids = array())
    {
        // Notify user
        $title = "Loan Application Submitted";
        $message = "Your $loan_type loan application for ₱" . number_format($amount, 2) . " (Application ID: $application_id) has been successfully submitted. Our team will review it shortly.";
        $action_url = "user_dashboard.php?app_id=$application_id";

        $this->createNotification(
            $user_id,
            $title,
            $message,
            'success',
            'loan',
            'high',
            $action_url
        );

        // Notify admins
        if (!empty($admin_ids)) {
            $admin_title = "New Loan Application Received";
            $admin_message = "A new $loan_type loan application (ID: $application_id) for ₱" . number_format($amount, 2) . " has been submitted and requires review.";
            $admin_action_url = "admin1_dashboard.php?pending=true";

            foreach ($admin_ids as $admin_id) {
                $this->createNotification(
                    $admin_id,
                    $admin_title,
                    $admin_message,
                    'info',
                    'loan',
                    'high',
                    $admin_action_url
                );
            }
        }

        return true;
    }

    /**
     * Create notification for loan status change
     */
    public function notifyLoanStatusChange($user_id, $application_id, $new_status, $admin_ids = array())
    {
        $status_messages = array(
            'Active' => 'Your loan has been approved and is now active!',
            'Pending' => 'Your loan application is under review.',
            'Closed' => 'Your loan has been closed successfully.',
            'Rejected' => 'Unfortunately, your loan application has been rejected.'
        );

        $message = isset($status_messages[$new_status]) ? $status_messages[$new_status] : "Your loan status has been updated to: $new_status";
        $type = ($new_status === 'Active') ? 'success' : (($new_status === 'Rejected') ? 'error' : 'info');
        $priority = ($new_status === 'Rejected') ? 'high' : 'normal';

        // Notify user
        $this->createNotification(
            $user_id,
            "Loan Status Updated",
            $message,
            $type,
            'loan',
            $priority,
            "user_dashboard.php?app_id=$application_id"
        );

        return true;
    }

    /**
     * Create notification for payment due
     */
    public function notifyPaymentDue($user_id, $loan_id, $due_amount, $due_date, $admin_ids = array())
    {
        $title = "Payment Due Reminder";
        $message = "Payment of ₱" . number_format($due_amount, 2) . " is due on " . date('M d, Y', strtotime($due_date)) . " for Loan ID: $loan_id";
        $action_url = "pay_balance.php?loan_id=$loan_id";

        // Notify user
        $this->createNotification(
            $user_id,
            $title,
            $message,
            'warning',
            'payment',
            'high',
            $action_url,
            $due_date
        );

        // Notify admins
        if (!empty($admin_ids)) {
            $admin_message = "User payment of ₱" . number_format($due_amount, 2) . " is due on " . date('M d, Y', strtotime($due_date)) . " for Loan ID: $loan_id";
            foreach ($admin_ids as $admin_id) {
                $this->createNotification(
                    $admin_id,
                    "User Payment Due",
                    $admin_message,
                    'info',
                    'payment',
                    'normal',
                    "admin1_dashboard.php?loan_id=$loan_id"
                );
            }
        }

        return true;
    }

    /**
     * Create notification for document upload
     */
    public function notifyDocumentUploaded($user_id, $document_type, $application_id, $admin_ids = array())
    {
        // Notify user
        $this->createNotification(
            $user_id,
            "Document Uploaded",
            "Your $document_type has been successfully uploaded for Application ID: $application_id",
            'success',
            'document',
            'normal',
            "user_dashboard.php?app_id=$application_id"
        );

        // Notify admins
        if (!empty($admin_ids)) {
            foreach ($admin_ids as $admin_id) {
                $this->createNotification(
                    $admin_id,
                    "Document Uploaded for Review",
                    "A new $document_type has been uploaded for Application ID: $application_id and requires verification.",
                    'info',
                    'document',
                    'normal',
                    "admin1_dashboard.php?app_id=$application_id"
                );
            }
        }

        return true;
    }

    /**
     * Create notification for credit investigation update
     */
    public function notifyCreditInvestigationUpdate($user_id, $application_id, $investigation_status, $admin_ids = array())
    {
        $status_messages = array(
            'Pending' => 'Your credit investigation is in progress.',
            'Completed' => 'Your credit investigation has been completed.',
            'Failed' => 'Your credit investigation could not be completed. Please contact support.'
        );

        $message = isset($status_messages[$investigation_status]) ? $status_messages[$investigation_status] : "Credit investigation status updated to: $investigation_status";
        $type = ($investigation_status === 'Completed') ? 'success' : 'info';

        // Notify user
        $this->createNotification(
            $user_id,
            "Credit Investigation Update",
            $message,
            $type,
            'loan',
            'normal',
            "user_dashboard.php?app_id=$application_id"
        );

        return true;
    }

    /**
     * Create notification for final loan amount approval
     */
    public function notifyFinalAmountApproved($user_id, $application_id, $final_amount, $admin_ids = array())
    {
        // Notify user
        $user_title = "Final Loan Amount Approved";
        $user_message = "Your final loan amount of ₱" . number_format($final_amount, 2) . " has been approved for Application ID: $application_id. You will receive your funds shortly.";

        $this->createNotification(
            $user_id,
            $user_title,
            $user_message,
            'success',
            'loan',
            'high',
            "user_dashboard.php?app_id=$application_id"
        );

        // Notify admins
        if (!empty($admin_ids)) {
            $admin_title = "Final Loan Amount Approved";
            $admin_message = "Final loan amount of ₱" . number_format($final_amount, 2) . " has been approved for Application ID: $application_id.";

            foreach ($admin_ids as $admin_id) {
                $this->createNotification(
                    $admin_id,
                    $admin_title,
                    $admin_message,
                    'info',
                    'loan',
                    'normal',
                    "admin1_dashboard.php?app_id=$application_id"
                );
            }
        }

        return true;
    }

    /**
     * Create notification for term length assignment
     */
    public function notifyTermLengthAssigned($user_id, $application_id, $term_length, $admin_ids = array())
    {
        // Notify user
        $user_title = "Recommended Loan Term Assigned";
        $user_message = "A loan term of " . intval($term_length) . " months has been recommended for your Application ID: $application_id based on your credit investigation.";

        $this->createNotification(
            $user_id,
            $user_title,
            $user_message,
            'success',
            'loan',
            'normal',
            "user_dashboard.php?app_id=$application_id"
        );

        // Notify admins
        if (!empty($admin_ids)) {
            $admin_title = "Term Length Assigned";
            $admin_message = "Term length of " . intval($term_length) . " months has been assigned for Application ID: $application_id.";

            foreach ($admin_ids as $admin_id) {
                $this->createNotification(
                    $admin_id,
                    $admin_title,
                    $admin_message,
                    'info',
                    'loan',
                    'normal',
                    "admin1_dashboard.php?app_id=$application_id"
                );
            }
        }

        return true;
    }

    /**
     * Get unread notifications for a user
     */
    public function getUnreadNotifications($user_id, $limit = 10)
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    notification_id as id, 
                    title, 
                    message, 
                    short_message,
                    (SELECT type_name FROM notification_types WHERE type_id = user_notifications.type_id) as type,
                    event_type as category, 
                    priority, 
                    action_url, 
                    created_at
                FROM user_notifications
                WHERE user_id = ? AND is_read = 0 AND (expires_at IS NULL OR expires_at > NOW()) AND deleted_at IS NULL
                ORDER BY priority DESC, created_at DESC
                LIMIT ?
            ");

            if ($stmt === false) {
                error_log("User notifications fetch prepare failed: " . $this->conn->error);
                return array();
            }

            $stmt->bind_param("ii", $user_id, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            $notifications = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            return $notifications;
        } catch (Exception $e) {
            error_log("Notification fetch error: " . $e->getMessage());
            return array();
        }
    }

    /**
     * Get all notifications for a user (including read)
     */
    public function getAllNotifications($user_id, $limit = 50, $offset = 0)
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    notification_id as id, 
                    title, 
                    message, 
                    short_message,
                    (SELECT type_name FROM notification_types WHERE type_id = user_notifications.type_id) as type,
                    event_type as category,
                    is_read, 
                    priority, 
                    action_url, 
                    created_at
                FROM user_notifications
                WHERE user_id = ? AND (expires_at IS NULL OR expires_at > NOW()) AND deleted_at IS NULL
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ");

            if ($stmt === false) {
                error_log("User notifications fetch prepare failed: " . $this->conn->error);
                return array();
            }

            $stmt->bind_param("iii", $user_id, $limit, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            $notifications = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            return $notifications;
        } catch (Exception $e) {
            error_log("Notification fetch error: " . $e->getMessage());
            return array();
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead($notification_id, $user_id)
    {
        try {
            $stmt = $this->conn->prepare("
                UPDATE user_notifications
                SET is_read = 1, read_at = NOW()
                WHERE notification_id = ? AND user_id = ?
            ");

            if ($stmt === false) {
                error_log("Notification update prepare failed: " . $this->conn->error);
                return false;
            }

            $stmt->bind_param("ii", $notification_id, $user_id);
            $result = $stmt->execute();
            $stmt->close();

            return $result;
        } catch (Exception $e) {
            error_log("Notification update error: " . $e->getMessage());
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

            if ($stmt === false) {
                error_log("Notification batch update prepare failed: " . $this->conn->error);
                return false;
            }

            $stmt->bind_param("i", $user_id);
            $result = $stmt->execute();
            $stmt->close();

            return $result;
        } catch (Exception $e) {
            error_log("Notification batch update error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get unread notification count for a user
     */
    public function getUnreadCount($user_id)
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as unread_count
                FROM user_notifications
                WHERE user_id = ? AND is_read = 0 AND (expires_at IS NULL OR expires_at > NOW()) AND deleted_at IS NULL
            ");

            if ($stmt === false) {
                error_log("Unread count fetch prepare failed: " . $this->conn->error);
                return 0;
            }

            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();

            return $row['unread_count'] ?? 0;
        } catch (Exception $e) {
            error_log("Unread count fetch error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Delete a notification
     */
    public function deleteNotification($notification_id, $user_id)
    {
        try {
            $stmt = $this->conn->prepare("
                DELETE FROM notifications
                WHERE id = ? AND user_id = ?
            ");

            if ($stmt === false) {
                error_log("Notification delete prepare failed: " . $this->conn->error);
                return false;
            }

            $stmt->bind_param("ii", $notification_id, $user_id);
            $result = $stmt->execute();
            $stmt->close();

            return $result;
        } catch (Exception $e) {
            error_log("Notification delete error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get admin users for notification
     */
    public function getAdminUserIds()
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT id FROM admins 
                UNION 
                SELECT id FROM superadmins
            ");

            if ($stmt === false) {
                error_log("Admin IDs fetch prepare failed: " . $this->conn->error);
                return array();
            }

            $stmt->execute();
            $result = $stmt->get_result();
            $admins = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            return array_column($admins, 'id');
        } catch (Exception $e) {
            error_log("Admin IDs fetch error: " . $e->getMessage());
            return array();
        }
    }
}

// Initialize if accessed directly via AJAX
if (isset($_GET['action'])) {
    $notificationManager = new NotificationManagerAPI($conn);

    if ($_GET['action'] === 'get_unread') {
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        $notifications = $notificationManager->getUnreadNotifications($_SESSION['user_id']);
        echo json_encode(['success' => true, 'notifications' => $notifications]);
        exit;
    }

    if ($_GET['action'] === 'mark_as_read') {
        if (!isset($_SESSION['user_id']) || !isset($_GET['notification_id'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid request']);
            exit;
        }

        $success = $notificationManager->markAsRead($_GET['notification_id'], $_SESSION['user_id']);
        echo json_encode(['success' => $success]);
        exit;
    }

    if ($_GET['action'] === 'get_unread_count') {
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'count' => 0]);
            exit;
        }

        $count = $notificationManager->getUnreadCount($_SESSION['user_id']);
        echo json_encode(['success' => true, 'count' => $count]);
        exit;
    }

    if ($_GET['action'] === 'mark_all_as_read') {
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        $success = $notificationManager->markAllAsRead($_SESSION['user_id']);
        echo json_encode(['success' => $success]);
        exit;
    }
}

// Handle POST requests for creating notifications
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $notificationManager = new NotificationManagerAPI($conn);

    if (isset($input['action']) && $input['action'] === 'notify_loan_calculation') {
        $loanType = $input['data']['loan_type'] ?? 'Unknown';
        $amount = $input['data']['amount'] ?? 0;
        $termLength = $input['data']['term_length'] ?? 0;
        $repaymentFrequency = $input['data']['repayment_frequency'] ?? 'Unknown';

        $success = $notificationManager->notifyLoanCalculation(
            $_SESSION['user_id'],
            $loanType,
            $amount,
            $termLength,
            $repaymentFrequency
        );

        echo json_encode(['success' => $success]);
        exit;
    }

    if (isset($input['action']) && $input['action'] === 'notify_loan_application') {
        $applicationId = $input['data']['application_id'] ?? null;
        $loanType = $input['data']['loan_type'] ?? 'Unknown';
        $amount = $input['data']['amount'] ?? 0;

        // Get admin IDs
        $adminIds = $notificationManager->getAdminUserIds();

        $success = $notificationManager->notifyLoanApplicationSubmitted(
            $_SESSION['user_id'],
            $applicationId,
            $loanType,
            $amount,
            $adminIds
        );

        echo json_encode(['success' => $success]);
        exit;
    }

    if (isset($input['action']) && $input['action'] === 'notify_status_change') {
        $applicationId = $input['data']['application_id'] ?? null;
        $newStatus = $input['data']['new_status'] ?? 'Unknown';

        // Get admin IDs
        $adminIds = $notificationManager->getAdminUserIds();

        $success = $notificationManager->notifyLoanStatusChange(
            $_SESSION['user_id'],
            $applicationId,
            $newStatus,
            $adminIds
        );

        echo json_encode(['success' => $success]);
        exit;
    }

    if (isset($input['action']) && $input['action'] === 'notify_payment_due') {
        $loanId = $input['data']['loan_id'] ?? null;
        $dueAmount = $input['data']['due_amount'] ?? 0;
        $dueDate = $input['data']['due_date'] ?? date('Y-m-d');

        // Get admin IDs
        $adminIds = $notificationManager->getAdminUserIds();

        $success = $notificationManager->notifyPaymentDue(
            $_SESSION['user_id'],
            $loanId,
            $dueAmount,
            $dueDate,
            $adminIds
        );

        echo json_encode(['success' => $success]);
        exit;
    }

    if (isset($input['action']) && $input['action'] === 'notify_document_uploaded') {
        $documentType = $input['data']['document_type'] ?? 'Document';
        $applicationId = $input['data']['application_id'] ?? null;

        // Get admin IDs
        $adminIds = $notificationManager->getAdminUserIds();

        $success = $notificationManager->notifyDocumentUploaded(
            $_SESSION['user_id'],
            $documentType,
            $applicationId,
            $adminIds
        );

        echo json_encode(['success' => $success]);
        exit;
    }

    if (isset($input['action']) && $input['action'] === 'notify_credit_investigation') {
        $applicationId = $input['data']['application_id'] ?? null;
        $investigationStatus = $input['data']['investigation_status'] ?? 'Unknown';

        $success = $notificationManager->notifyCreditInvestigationUpdate(
            $_SESSION['user_id'],
            $applicationId,
            $investigationStatus
        );

        echo json_encode(['success' => $success]);
        exit;
    }

    if (isset($input['action']) && $input['action'] === 'notify_final_amount_approved') {
        $applicationId = $input['data']['application_id'] ?? null;
        $finalAmount = $input['data']['final_amount'] ?? 0;
        $userId = $input['data']['user_id'] ?? $_SESSION['user_id'];

        // Get admin IDs
        $adminIds = $notificationManager->getAdminUserIds();

        $success = $notificationManager->notifyFinalAmountApproved(
            $userId,
            $applicationId,
            $finalAmount,
            $adminIds
        );

        echo json_encode(['success' => $success]);
        exit;
    }

    if (isset($input['action']) && $input['action'] === 'notify_term_length_assigned') {
        $applicationId = $input['data']['application_id'] ?? null;
        $termLength = $input['data']['term_length'] ?? 0;
        $userId = $input['data']['user_id'] ?? $_SESSION['user_id'];

        // Get admin IDs
        $adminIds = $notificationManager->getAdminUserIds();

        $success = $notificationManager->notifyTermLengthAssigned(
            $userId,
            $applicationId,
            $termLength,
            $adminIds
        );

        echo json_encode(['success' => $success]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}
?>