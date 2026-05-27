<?php
/**
 * INTEGRATION SNIPPETS FOR CYCLOAN ADMIN PAGES
 * 
 * Copy and paste these snippets into your existing admin pages
 * Follow the specific page type: Dashboard, Records, or Other
 * 
 * IMPORTANT: All code examples below should be copied into your actual files
 * This file contains ONLY documentation and examples
 * 
 * @author CYCLOAN Development Team
 * @version 1.0
 */

// ============================================
// SNIPPET 1: ADMIN DASHBOARD INTEGRATION
// ============================================

/*
LOCATION: At the top of admin1_dashboard.php, admin2_dashboard.php, Superadmin_dashboard.php
AFTER: session_start() and require statements

CODE TO ADD:
    require_once 'notification_widget.php';

OPTIONAL - Send notification on page load for demo:
    if ($_SESSION['role'] === 'admin1') {
        $notificationManager = new NotificationManager($conn);
        $notificationManager->createNotification(
            $_SESSION['user_id'],
            'system_message',
            'Welcome Back',
            'You have logged in to your dashboard',
            'normal'
        );
    }

IN THE HTML HEADER: Add the widget in profile section
    <div class="profileXdate">
        <div id="datetime" class="datetime"></div>

        <!-- ADD NOTIFICATION WIDGET -->
        <div style="display: inline-flex; align-items: center; gap: 20px;">
            <?php echo renderNotificationWidget($conn, $_SESSION['user_id'], $_SESSION['role'] ?? 'admin1'); ?>

            <!-- Existing Profile Dropdown -->
            <div class="profile-container"><!-- ... --></div>
        </div>
    </div>
*/

// ============================================
// SNIPPET 2: RECORDS PAGE INTEGRATION
// ============================================

/*
LOCATION: In the <header> or <nav> section of records pages
(applicant.php, active_records.php, closed_records.php, pending_records.php, reports_record.php)

CODE TO ADD IN HTML:
    <div style="position: absolute; top: 20px; right: 80px;">
        <?php 
            require_once 'notification_widget.php';
            echo renderNotificationWidget(
                $conn, 
                $_SESSION['user_id'], 
                $_SESSION['role'] ?? 'user'
            ); 
        ?>
    </div>
*/

// ============================================
// SNIPPET 3: SEND NOTIFICATION ON APPLICANT UPDATE
// ============================================

/*
LOCATION: applicant.php - when approving or updating an applicant

CODE TO ADD:
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve_applicant') {
        // ... your existing approval logic ...

        require_once 'NotificationManager.php';
        $notificationManager = new NotificationManager($conn);

        $applicant_name = $_POST['applicant_name'] ?? 'Applicant';

        $notificationManager->createNotification(
            $_SESSION['user_id'],
            'applicant_update',
            'Applicant Approved',
            "$applicant_name's application has been approved",
            'high'
        );
    }
*/

// ============================================
// SNIPPET 4: SEND NOTIFICATION ON LOAN STATUS CHANGE
// ============================================

/*
LOCATION: Any page handling loan status updates

CODE TO ADD:
    if ($old_status !== $new_status) {
        require_once 'NotificationManager.php';
        $notificationManager = new NotificationManager($conn);

        $notificationManager->createNotification(
            $_SESSION['user_id'],
            'loan_status',
            'Loan Status Changed',
            "Loan $loan_id status changed from $old_status to $new_status",
            'normal'
        );
    }
*/

// ============================================
// SNIPPET 5: SEND NOTIFICATION ON PAYMENT RECEIVED
// ============================================

/*
LOCATION: Payment processing page

CODE TO ADD:
    if ($payment_processed) {
        require_once 'NotificationManager.php';
        $notificationManager = new NotificationManager($conn);

        $notificationManager->createNotification(
            $_SESSION['user_id'],
            'payment_reminder',
            'Payment Received',
            "Payment of " . number_format($payment_amount, 2) . " received",
            'normal'
        );
    }
*/

// ============================================
// SNIPPET 6: SEND NOTIFICATION FOR AUDIT LOG
// ============================================

/*
LOCATION: When important admin actions occur

CODE TO ADD:
    require_once 'NotificationManager.php';
    $notificationManager = new NotificationManager($conn);

    $notificationManager->createNotification(
        $_SESSION['user_id'],
        'audit_trail',
        'Admin Action',
        "Action: " . $action_description,
        'normal'
    );
*/

// ============================================
// SNIPPET 7: SEND NOTIFICATION FOR CREDIT RATE CHANGE
// ============================================

/*
LOCATION: manage_credit_points.php - when rates change

CODE TO ADD:
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_rate'])) {
        $old_rate = 5.5;
        $new_rate = $_POST['new_rate'];

        if ($old_rate !== $new_rate) {
            require_once 'NotificationManager.php';
            $notificationManager = new NotificationManager($conn);

            $notificationManager->createNotification(
                $_SESSION['user_id'],
                'credit_rate',
                'Interest Rate Updated',
                "Rate changed from $old_rate% to $new_rate%",
                'high'
            );
        }
    }
*/

// ============================================
// SNIPPET 8: ADD NOTIFICATION LINK TO NAVIGATION
// ============================================

/*
LOCATION: In your navigation menu HTML

CODE TO ADD:
    <a href="notifications_enhanced.php" class="nav-link" title="View all notifications">
        <i class="fas fa-bell"></i> 
        <span>Notifications</span>
    </a>
*/

// ============================================
// SNIPPET 9: COMPLETE HEADER TEMPLATE REFERENCE
// ============================================

/*
COMPLETE EXAMPLE HTML STRUCTURE:

    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Dashboard</title>

        <link rel="stylesheet" href="CSS/dashboard.css">
        <link rel="stylesheet" href="CSS/notification_system.css?v=<?php echo time(); ?>">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    </head>
    <body>
        <!-- Navigation -->
        <nav class="navbar">
            <div class="logo">CYCLOAN</div>
            <ul class="nav-menu">
                <li><a href="admin1_dashboard.php">Dashboard</a></li>
                <li><a href="notifications_enhanced.php"><i class="fas fa-bell"></i> Notifications</a></li>
            </ul>
        </nav>

        <!-- Header with Widget -->
        <div class="header">
            <div class="header-right">
                <?php 
                    require_once 'notification_widget.php';
                    echo renderNotificationWidget($conn, $_SESSION['user_id'], $_SESSION['role'] ?? 'admin1'); 
                ?>

                <div class="profile-container">
                    <!-- Profile dropdown -->
                </div>
            </div>
        </div>

        <!-- Scripts -->
        <script src="JAVASCRIPT/dashboard.js"></script>
        <script src="JAVASCRIPT/Real-Time.js"></script>
    </body>
    </html>
*/

// ============================================
// SNIPPET 10: CSS CUSTOMIZATION (OPTIONAL)
// ============================================

/*
ADD TO CSS/notification_system.css TO CUSTOMIZE COLORS:

    :root {
        --primary-color: #1b5e20;
        --danger-color: #ef5350;
    }

    .notification-bell-btn:hover {
        background-color: rgba(27, 94, 32, 0.1);
        color: #1b5e20;
    }

    .notification-badge {
        background-color: #ef5350;
    }

    .notification-dropdown-header {
        background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%);
    }
*/

// ============================================
// SNIPPET 11: TESTING THE SYSTEM
// ============================================

/*
TESTING INSTRUCTIONS:

1. Browser Console Testing (F12):
   updateNotificationBadge('notification_widget_YOUR_ID');
   loadRecentNotifications('notification_dropdown_YOUR_ID');

2. Send Test Notification - Add to any page temporarily:
    if (isset($_GET['test_notification'])) {
        require_once 'NotificationManager.php';
        $nm = new NotificationManager($conn);
        $nm->createNotification(
            $_SESSION['user_id'],
            'system_message',
            'Test Notification',
            'This is a test',
            'normal'
        );
        echo "<script>alert('Sent!'); window.location.href = '" . 
             str_replace('?test_notification=1', '', $_SERVER['REQUEST_URI']) . "';</script>";
    }

   Then visit: yourpage.php?test_notification=1

3. Database Check:
   SELECT * FROM user_notifications WHERE user_id = YOUR_USER_ID;
*/

// ============================================
// END OF INTEGRATION SNIPPETS
// ============================================

?>