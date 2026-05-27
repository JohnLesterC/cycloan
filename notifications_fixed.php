<?php
/**
 * CYCLOAN Notifications Dashboard Page - Fixed Version
 * Enhanced notification management interface matching user dashboard design
 * 
 * @author CYCLOAN Development Team
 * @version 3.1 - Debug Fixed
 * @created 2025-11-13
 */

// Turn on error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Basic session check first
if (!isset($_SESSION['email']) || !isset($_SESSION['user_id'])) {
    // Redirect to login
    header("Location: index.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userEmail = $_SESSION['email'];

// Try to include database connection
try {
    require_once 'CYCLOAN_db.php';
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Check database connection
if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection not available");
}

// Get user information
try {
    $stmt = $conn->prepare("SELECT id as user_id, first_name, last_name, profile_image FROM users1 WHERE email = ?");
    if (!$stmt) {
        throw new Exception("Database prepare failed: " . $conn->error);
    }

    $stmt->bind_param("s", $userEmail);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        header("Location: index.php");
        exit();
    }

    $user = $result->fetch_assoc();
    $userId = $user['user_id'];
    $userName = trim($user['first_name'] . ' ' . $user['last_name']);
    $profile_image = !empty($user['profile_image']) ? $user['profile_image'] : 'IMAGE/default-avatar.png';
    $stmt->close();

} catch (Exception $e) {
    die("Failed to fetch user information: " . $e->getMessage());
}

// Try to include NotificationManager
$notifications = [];
$totalCount = 0;
$totalUnread = 0;
$totalPages = 0;
$successMessage = '';
$errorMessage = '';

try {
    require_once 'NotificationManager.php';
    $notificationManager = new NotificationManager($conn);

    // Get filters from request
    $currentPage = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = min(max(1, (int) ($_GET['per_page'] ?? 20)), 50);
    $typeFilter = $_GET['type'] ?? '';
    $statusFilter = $_GET['status'] ?? 'all';
    $searchQuery = $_GET['search'] ?? '';

    // Build filters array
    $filters = [];
    if (!empty($typeFilter)) {
        $filters['type'] = $typeFilter;
    }
    if ($statusFilter === 'unread') {
        $filters['is_read'] = false;
    } elseif ($statusFilter === 'read') {
        $filters['is_read'] = true;
    }
    if (!empty($searchQuery)) {
        $filters['search'] = $searchQuery;
    }

    // Handle POST actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        try {
            switch ($action) {
                case 'mark_read':
                    $notificationIds = $_POST['notification_ids'] ?? [];
                    if (!empty($notificationIds)) {
                        $result = $notificationManager->markAsRead($userId, $notificationIds);
                        $successMessage = $result ? "Notifications marked as read." : "Failed to mark notifications as read.";
                    }
                    break;

                case 'delete':
                    $notificationIds = $_POST['notification_ids'] ?? [];
                    if (!empty($notificationIds)) {
                        $result = $notificationManager->deleteNotifications($userId, $notificationIds);
                        $successMessage = $result ? "Notifications deleted." : "Failed to delete notifications.";
                    }
                    break;

                case 'mark_all_read':
                    $result = $notificationManager->markAllAsRead($userId);
                    $successMessage = $result ? "All notifications marked as read." : "Failed to mark all notifications as read.";
                    break;
            }
        } catch (Exception $e) {
            $errorMessage = "Error processing action: " . $e->getMessage();
        }

        // Redirect to prevent form resubmission
        if ($successMessage || $errorMessage) {
            $_SESSION['notification_message'] = $successMessage ?: $errorMessage;
            $_SESSION['notification_type'] = $successMessage ? 'success' : 'error';
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit();
        }
    }

    // Check for session messages
    if (isset($_SESSION['notification_message'])) {
        if ($_SESSION['notification_type'] === 'success') {
            $successMessage = $_SESSION['notification_message'];
        } else {
            $errorMessage = $_SESSION['notification_message'];
        }
        unset($_SESSION['notification_message']);
        unset($_SESSION['notification_type']);
    }

    // Get notifications
    $notificationsResult = $notificationManager->getUserNotifications($userId, $filters, $currentPage, $perPage);
    $notifications = $notificationsResult['notifications'] ?? [];
    $totalCount = $notificationsResult['total_count'] ?? 0;
    $totalPages = ceil($totalCount / $perPage);

    // Get statistics
    $unreadCountsResult = $notificationManager->getUnreadCounts($userId);
    $totalUnread = $unreadCountsResult['total'] ?? 0;

} catch (Exception $e) {
    $errorMessage = "Notification system error: " . $e->getMessage();
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - CYCLOAN</title>
    <link rel="stylesheet" href="CSS/admin_dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="CSS/user_dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="CSS/dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">

    <style>
        :root {
            --primary: #1b5e20;
            --secondary: #2e7d32;
            --dark: #1a3c34;
            --darker: #0a1f1b;
            --light: #f8fafc;
            --pending: #ef5350;
            --active: #1b5e20;
            --completed: #2e7d32;
            --textbased: #161718;
            --bg: #e2e8f0;
            --side: #1e293b;
            --bgside: #1a3c34;
            --green1: #1b5e20;
            --green2: #2e7d32;
            --yellow: #fbc02d;
            --rejected: #d32f2f;
            --approved: #1b5e20;
            --failed: #f4511e;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1), 0 1px 3px rgba(0, 0, 0, 0.08);
        }

        body {
            font-family: "Poppins", sans-serif;
            background: var(--bg);
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        .header {
            position: fixed;
            top: 0;
            left: 260px;
            right: 0;
            height: 70px;
            padding: 0 24px;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            background: var(--light);
            box-shadow: var(--shadow);
            z-index: 1000;
        }

        .profileXdate {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .datetime {
            font-size: 1rem;
            font-weight: 500;
            color: var(--darker);
        }

        .profile-container {
            position: relative;
            cursor: pointer;
        }

        .profile {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .nav-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 260px;
            height: 100vh;
            background: var(--bgside);
            z-index: 1001;
        }

        nav {
            padding: 20px 0;
        }

        .sidebar-logo {
            width: 180px;
            height: auto;
            margin: 0 auto 30px;
            display: block;
        }

        nav a {
            display: flex;
            align-items: center;
            padding: 15px 25px;
            color: var(--light);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            gap: 12px;
        }

        nav a:hover,
        nav a.active {
            background: rgba(255, 255, 255, 0.1);
            color: var(--yellow);
        }

        .main-content {
            margin-left: 260px;
            margin-top: 70px;
            padding: 30px;
            min-height: calc(100vh - 70px);
        }

        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .notifications-header {
            background: var(--light);
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
            border-left: 5px solid var(--primary);
        }

        .notifications-header h1 {
            color: var(--dark);
            font-size: 2.2rem;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .subtitle {
            color: var(--textbased);
            font-size: 1.1rem;
            opacity: 0.8;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--light);
            border-radius: 12px;
            padding: 25px;
            box-shadow: var(--shadow);
            transition: transform 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: var(--light);
            background: linear-gradient(135deg, var(--primary), var(--secondary));
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark);
        }

        .stat-label {
            color: var(--textbased);
            font-size: 0.95rem;
            font-weight: 500;
            margin-top: 5px;
        }

        .notifications-section {
            background: var(--light);
            border-radius: 12px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .section-header {
            padding: 25px 30px;
            border-bottom: 1px solid #e5e7eb;
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .section-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 30px;
            color: #6b7280;
        }

        .empty-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #d1d5db;
        }

        .notification-item {
            padding: 25px 30px;
            border-bottom: 1px solid #f1f5f9;
            transition: background-color 0.3s ease;
        }

        .notification-item:hover {
            background-color: #f8fafc;
        }

        .notification-item.unread {
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            border-left: 4px solid var(--primary);
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: var(--light);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(27, 94, 32, 0.3);
        }

        @media (max-width: 768px) {
            .header {
                left: 0;
            }

            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .nav-container {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .nav-container.active {
                transform: translateX(0);
            }
        }
    </style>
</head>

<body>
    <!-- Header -->
    <div class="header">
        <div class="profileXdate">
            <div class="datetime" id="datetime"></div>
            <div class="profile-container">
                <img src="<?php echo htmlspecialchars($profile_image); ?>" alt="Profile" class="profile">
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <div class="nav-container">
        <nav>
            <img src="IMAGE/Main-Logo.png" alt="CYCLOAN Logo" class="sidebar-logo">
            <a href="user_dashboard.php" class="<?php echo $current_page === 'user_dashboard.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-table-columns"></i> DASHBOARD
            </a>
            <a href="user_active_record.php">
                <i class="fa-solid fa-user-check"></i> ACTIVE RECORDS
            </a>
            <a href="user_pending_records.php">
                <i class="fa-solid fa-spinner"></i> PENDING RECORDS
            </a>
            <a href="user_closed_records.php">
                <i class="fa-solid fa-circle-check"></i> CLOSED RECORDS
            </a>
            <a href="user_history_activity.php">
                <i class="fa-solid fa-clipboard"></i> HISTORY ACTIVITY
            </a>
            <a href="notifications.php" class="<?php echo $current_page === 'notifications.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-bell"></i> NOTIFICATIONS
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Success/Error Messages -->
        <?php if ($successMessage): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMessage); ?>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>

        <!-- Header Section -->
        <div class="notifications-header">
            <h1><i class="fas fa-bell"></i> Notifications Center</h1>
            <p class="subtitle">Stay updated with your loan applications and account activities</p>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($totalCount); ?></div>
                </div>
                <div class="stat-label">Total Notifications</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-envelope-open"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($totalUnread); ?></div>
                </div>
                <div class="stat-label">Unread Messages</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-number">
                        <?php echo $totalCount > 0 ? round(($totalCount - $totalUnread) / $totalCount * 100) : 0; ?>%
                    </div>
                </div>
                <div class="stat-label">Read Rate</div>
            </div>
        </div>

        <!-- Notifications Section -->
        <div class="notifications-section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-list"></i>
                    Your Notifications
                </h2>

                <?php if ($totalUnread > 0): ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="mark_all_read">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check-double"></i> Mark All Read
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="notifications-list">
                <?php if (empty($notifications)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-inbox"></i>
                        </div>
                        <h3>No Notifications Found</h3>
                        <p>You're all caught up! Check back later for new updates.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>">
                            <h3><?php echo htmlspecialchars($notification['title']); ?>
                                <?php if (!$notification['is_read']): ?>
                                    <span
                                        style="background: var(--primary); color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; margin-left: 10px;">NEW</span>
                                <?php endif; ?>
                            </h3>
                            <p><?php echo htmlspecialchars($notification['short_message'] ?? $notification['message']); ?></p>
                            <small style="color: #666;">
                                <i class="far fa-clock"></i>
                                <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                | <i class="fas fa-tag"></i> <?php echo htmlspecialchars($notification['type_display_name']); ?>
                            </small>

                            <div style="margin-top: 10px;">
                                <?php if (!$notification['is_read']): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="mark_read">
                                        <input type="hidden" name="notification_ids[]"
                                            value="<?php echo $notification['notification_id']; ?>">
                                        <button type="submit"
                                            style="background: #28a745; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;">
                                            Mark as Read
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <form method="POST" style="display: inline; margin-left: 10px;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="notification_ids[]"
                                        value="<?php echo $notification['notification_id']; ?>">
                                    <button type="submit"
                                        style="background: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;"
                                        onclick="return confirm('Delete this notification?')">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Update date and time
        function updateDateTime() {
            const now = new Date();
            const options = {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            };
            document.getElementById('datetime').textContent = now.toLocaleDateString('en-US', options);
        }

        // Initialize
        updateDateTime();
        setInterval(updateDateTime, 1000);
    </script>
</body>

</html>