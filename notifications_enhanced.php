<?php
/**
 * CYCLOAN Enhanced Notifications Center
 * Unified notification hub for all admin roles and users
 * Matches admin dashboard design and styling
 * 
 * Supported Roles: Admin 1, Admin 2, Super Admin, Users
 * Supported Pages: All admin pages and user dashboard
 */

session_start();

if (!isset($_SESSION['email']) || !isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Helper function to sanitize strings
function sanitizeString($input, $maxLength = null)
{
    $sanitized = htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    if ($maxLength && strlen($sanitized) > $maxLength) {
        $sanitized = substr($sanitized, 0, $maxLength);
    }
    return $sanitized;
}

$userId = $_SESSION['user_id'];
$userEmail = $_SESSION['email'];
$userRole = strtolower($_SESSION['role'] ?? 'user'); // Convert to lowercase for consistency

require_once 'CYCLOAN_db.php';
require_once 'NotificationManager.php';

// Check if connection exists
if (!$conn) {
    die("Database connection failed");
}

// Get user information based on role
$profile_img = 'default.png';
$userName = 'User';
$adminName = '';
$profile_link = 'profile.php';

if ($userRole === 'admin1') {
    $stmt = $conn->prepare("SELECT id, first_name, last_name, profile_img FROM admin1 WHERE email = ?");
    $profile_link = 'profileAdmin1.php';
} elseif ($userRole === 'admin2') {
    $stmt = $conn->prepare("SELECT id, first_name, last_name, profile_img FROM admin2 WHERE email = ?");
    $profile_link = 'profileAdmin2.php';
} elseif ($userRole === 'superadmin') {
    $stmt = $conn->prepare("SELECT id, first_name, last_name, profile_img FROM superadmins WHERE email = ?");
    $profile_link = 'profileSuperadmin.php';
} else {
    $stmt = $conn->prepare("SELECT id, first_name, last_name, profile_image FROM users1 WHERE email = ?");
    $profile_link = 'profile.php';
}

if (!$stmt) {
    die("Prepare statement failed: " . $conn->error);
}

$stmt->bind_param("s", $userEmail);
if (!$stmt->execute()) {
    die("Execute failed: " . $stmt->error);
}

$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    error_log("User not found in database: email=$userEmail, role=$userRole");
    header("Location: index.php");
    exit();
}

$userId = $user['id'];
$userName = trim($user['first_name'] . ' ' . $user['last_name']);
$profile_img = !empty($user['profile_img'] ?? $user['profile_image']) ? ($user['profile_img'] ?? $user['profile_image']) : 'default.png';

// Ensure profile_img is not empty
if (empty($profile_img) || $profile_img === '' || $profile_img === NULL) {
    $profile_img = 'default.png';
}

// Initialize notification manager
$notificationManager = new NotificationManager($conn);
$current_page = basename($_SERVER['PHP_SELF']);

// Get filter parameters
$current_page_num = max(1, (int) ($_GET['page'] ?? 1));
$filter_type = $_GET['filter_type'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';
$search_query = $_GET['search'] ?? '';
$sort_by = $_GET['sort'] ?? 'newest';

$perPage = 15;
$filters = [];

// Build filters
if ($filter_type && $filter_type !== 'all') {
    $filters['type'] = $filter_type;
}

if ($filter_status === 'unread') {
    $filters['is_read'] = 0;
} elseif ($filter_status === 'read') {
    $filters['is_read'] = 1;
}

if (!empty($search_query)) {
    $filters['search'] = $search_query;
}

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $action = $_POST['action'];

    try {
        if ($action === 'mark_read') {
            $notificationIds = isset($_POST['notification_ids']) ? (array) $_POST['notification_ids'] : [];
            $success_count = 0;

            foreach ($notificationIds as $notifId) {
                if ($notificationManager->markAsRead($userId, (int) $notifId)) {
                    $success_count++;
                }
            }

            echo json_encode([
                'success' => true,
                'message' => "$success_count notification(s) marked as read",
                'count' => $success_count
            ]);
            exit;
        } elseif ($action === 'mark_all_read') {
            if ($notificationManager->markAllAsRead($userId)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'All notifications marked as read'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to mark all as read'
                ]);
            }
            exit;

        } elseif ($action === 'get_counts') {
            $counts = $notificationManager->getUnreadCounts($userId);
            echo json_encode([
                'success' => true,
                'counts' => $counts
            ]);
            exit;
        }

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
        exit;
    }
}

// Handle GET requests for real-time notifications
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $requestUserId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : $userId;

    try {
        if ($action === 'get_unread_count') {
            $counts = $notificationManager->getUnreadCounts($requestUserId);
            echo json_encode([
                'success' => true,
                'unread_count' => $counts['total'] ?? 0
            ]);
            exit;
        } elseif ($action === 'get_recent_notifications') {
            $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 5;
            $result = $notificationManager->getUserNotifications($requestUserId, [], 1, $limit);
            echo json_encode([
                'success' => true,
                'notifications' => $result['notifications'] ?? []
            ]);
            exit;
        } elseif ($action === 'get_document_updates') {
            // Get document update notifications
            require_once 'DocumentNotificationHandler.php';
            $docHandler = new DocumentNotificationHandler($conn);

            $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
            $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;

            $documentNotifications = $docHandler->getDocumentNotifications($requestUserId, $limit, $offset);
            $totalCount = $docHandler->getDocumentNotificationCount($requestUserId);
            $unreadCount = $docHandler->getDocumentNotificationCount($requestUserId, true);

            echo json_encode([
                'success' => true,
                'notifications' => $documentNotifications,
                'total_count' => $totalCount,
                'unread_count' => $unreadCount,
                'limit' => $limit,
                'offset' => $offset
            ]);
            exit;
        } elseif ($action === 'get_application_document_updates') {
            // Get document updates for a specific application
            require_once 'DocumentNotificationHandler.php';
            $docHandler = new DocumentNotificationHandler($conn);

            $applicationId = isset($_GET['application_id']) ? sanitizeString($_GET['application_id'], 50) : '';
            if (empty($applicationId)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Application ID required'
                ]);
                exit;
            }

            $updates = $docHandler->getApplicationDocumentChanges($applicationId, 20);
            $summary = $docHandler->getDocumentUpdateSummary($applicationId);

            echo json_encode([
                'success' => true,
                'updates' => $updates,
                'summary' => $summary
            ]);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
        exit;
    }
}

// Determine notification view type
$notif_view = 'standard'; // Only standard notifications

// Get paginated notifications with error handling
$notificationsResult = $notificationManager->getUserNotifications($userId, $filters, $current_page_num, $perPage);
$notifications = $notificationsResult['notifications'] ?? [];
$totalCount = $notificationsResult['total_count'] ?? 0;
$totalPages = $notificationsResult['total_pages'] ?? 0;
$totalUnread = $notificationManager->getUnreadCounts($userId)['total'] ?? 0;

// Activity notifications disabled - only standard notifications shown

// Get notification types for filtering with safe query
$notificationTypes = [];
$typeQuery = "SHOW TABLES LIKE 'notification_types'";
$tableExists = $conn->query($typeQuery);

if ($tableExists && $tableExists->num_rows > 0) {
    $typeQuery = "SELECT DISTINCT type_name, type_display_name FROM notification_types WHERE is_active = 1 ORDER BY type_display_name";
    $typeResult = $conn->query($typeQuery);

    if ($typeResult) {
        while ($row = $typeResult->fetch_assoc()) {
            $notificationTypes[] = $row;
        }
    }
} else {
    // Fallback notification types if table doesn't exist
    $notificationTypes = [
        ['type_name' => 'payment', 'type_display_name' => 'Payment Updates'],
        ['type_name' => 'status', 'type_display_name' => 'Status Changes'],
        ['type_name' => 'remark', 'type_display_name' => 'Comments & Remarks'],
        ['type_name' => 'security', 'type_display_name' => 'Security Alerts'],
        ['type_name' => 'system', 'type_display_name' => 'System Updates'],
        ['type_name' => 'reminder', 'type_display_name' => 'Reminders'],
        ['type_name' => 'approval', 'type_display_name' => 'Approvals'],
        ['type_name' => 'document', 'type_display_name' => 'Document Updates']
    ];
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications Center - CYCLOAN</title>
    <link rel="stylesheet" href="CSS/admin_dashboard.css">
    <link rel="stylesheet" href="CSS/admin_profile.css">
    <link rel="stylesheet" href="CSS/nav_active.css">
    <link rel="stylesheet" href="CSS/notifications_enhanced.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
</head>

<style>
    .dropdown-container {
        display: block;
        width: 100%;
    }

    .dropdown-btn {
        display: flex;
        align-items: center;
        cursor: pointer;
    }

    .dropdown-icon {
        margin-left: 40px;
        transition: transform 0.3s ease;
    }

    .dropdown-icon.rotate {
        transform: rotate(-180deg);
    }

    .dropdown-content {
        display: none;
        padding-left: 20px;
        flex-direction: column;
    }

    .dropdown-content a {
        font-size: 14px;
        padding: 8px 10px;
        margin: 10px;
    }

    /* Notifications Container Scrollbar */
    .notifications-container {
        max-height: 70vh;
        overflow-y: auto;
        overflow-x: hidden;
    }

    /* Custom Scrollbar Styling */
    .notifications-container::-webkit-scrollbar {
        width: 8px;
    }

    .notifications-container::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }

    .notifications-container::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 10px;
    }

    .notifications-container::-webkit-scrollbar-thumb:hover {
        background: #555;
    }

    /* Mark All Read Modal Styles */
    .mark-all-read-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        flex-direction: column;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
    }

    .mark-all-read-modal-content {
        background: white;
        border-radius: 8px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        width: 90%;
        max-width: 450px;
        padding: 0;
        position: relative;
        animation: slideUp 0.3s ease-out;
    }

    .mark-all-read-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px;
        border-bottom: 1px solid #e0e0e0;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 8px 8px 0 0;
        color: white;
    }

    .mark-all-read-modal-header h2 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
    }

    .mark-all-read-modal-header .close-btn {
        background: none;
        border: none;
        color: white;
        font-size: 24px;
        cursor: pointer;
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
        transition: background-color 0.2s;
    }

    .mark-all-read-modal-header .close-btn:hover {
        background-color: rgba(255, 255, 255, 0.1);
    }

    .mark-all-read-modal-body {
        padding: 25px 20px;
        text-align: center;
    }

    .mark-all-read-modal-body p {
        margin: 0;
        color: #333;
        font-size: 16px;
        line-height: 1.5;
    }

    .mark-all-read-modal-footer {
        display: flex;
        gap: 10px;
        padding: 20px;
        border-top: 1px solid #e0e0e0;
        justify-content: flex-end;
    }

    .mark-all-read-modal-footer .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .mark-all-read-modal-footer .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .mark-all-read-modal-footer .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }

    .mark-all-read-modal-footer .btn-secondary {
        background: #f0f0f0;
        color: #333;
    }

    .mark-all-read-modal-footer .btn-secondary:hover {
        background: #e0e0e0;
    }

    @keyframes slideUp {
        from {
            transform: translateY(30px);
            opacity: 0;
        }

        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
</style>

<body>
    <div class="nav-container">
        <button class="burger" onclick="toggleSidebar()">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <nav>
            <img src="IMAGE/Main-Logo.png" alt="Loan System Logo" class="sidebar-logo">
            <?php if ($userRole === 'admin1'): ?>
                <a href="admin1_dashboard.php"
                    class="<?php echo $current_page === 'admin1_dashboard.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-table-columns"></i> DASHBOARD
                </a>
            <?php elseif ($userRole === 'admin2'): ?>
                <a href="admin2_dashboard.php"
                    class="<?php echo $current_page === 'admin2_dashboard.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-table-columns"></i> DASHBOARD
                </a>
            <?php elseif ($userRole === 'superadmin'): ?>
                <a href="Superadmin_dashboard.php"
                    class="<?php echo $current_page === 'Superadmin_dashboard.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-table-columns"></i> DASHBOARD
                </a>
            <?php else: ?>
                <a href="user_dashboard.php" class="<?php echo $current_page === 'user_dashboard.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-home"></i> DASHBOARD
                </a>
            <?php endif; ?>

            <?php if ($userRole === 'admin1' || $userRole === 'admin2' || $userRole === 'superadmin'): ?>
                <!-- Admin Navigation -->
                <a href="applicant.php" class="<?php echo $current_page === 'applicant.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-users"></i>APPLICANTS
                </a>

                <div class="dropdown-container">
                    <a href="#" class="dropdown-btn">
                        <i class="fa-solid fa-folder-open"></i> RECORDS
                        <i class="fa-solid fa-caret-down dropdown-icon"></i>
                    </a>
                    <div class="dropdown-content">
                        <a href="active_records.php"
                            class="<?php echo $current_page === 'active_records.php' ? 'active' : ''; ?>">
                            <i class="fa-solid fa-user-check"></i> Active Records
                        </a>
                        <a href="pending_records.php"
                            class="<?php echo $current_page === 'pending_records.php' ? 'active' : ''; ?>">
                            <i class="fa-solid fa-spinner"></i> Pending Records
                        </a>
                        <a href="closed_records.php"
                            class="<?php echo $current_page === 'closed_records.php' ? 'active' : ''; ?>">
                            <i class="fa-solid fa-circle-check"></i> Closed Records
                        </a>
                    </div>
                </div>

                <a href="reports_record.php" class="<?php echo $current_page === 'reports_record.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-scroll"></i>REPORTS RECORDS
                </a>
                <a href="history_activity.php"
                    class="<?php echo $current_page === 'history_activity.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-clipboard"></i>AUDIT TRAILS
                </a>
                <a href="#" id="viewInterestRatesBtn" class="view-interest-rate-link">
                    <i class="fa-solid fa-percent"></i> VIEW INTEREST RATES
                </a>
            <?php else: ?>
                <!-- User Navigation -->
                <a href="profile.php" class="<?php echo $current_page === 'profile.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-user"></i> PROFILE
                </a>
                <a href="active_records.php" class="<?php echo $current_page === 'active_records.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-file-invoice-dollar"></i> ACTIVE LOANS
                </a>
                <a href="pending_records.php"
                    class="<?php echo $current_page === 'pending_records.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-spinner"></i> PENDING
                </a>
                <a href="closed_records.php" class="<?php echo $current_page === 'closed_records.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-check"></i> COMPLETED
                </a>
            <?php endif; ?>
        </nav>
    </div>

    <!-- Header with Profile -->
    <div class="header">
        <div class="profileXdate">
            <div id="datetime" class="datetime"></div>
            <a href="notifications_enhanced.php" class="notification-bell" title="View Notifications">
                <i class="fa-solid fa-bell"></i>
            </a>
            <div class="profile-container">
                <div onclick="toggleDropdown(event)" role="button" aria-label="Toggle profile menu" tabindex="0"
                    onkeydown="handleProfileKeydown(event)">
                    <img src="uploads/<?php echo htmlspecialchars($profile_img); ?>" alt="Profile Image" class="profile"
                        onerror="this.src='IMAGE/default-profile.png';">
                </div>
                <div class="dropdown-menu" id="dropdown" role="menu">
                    <ul>
                        <li role="none">
                            <a href="<?php echo htmlspecialchars($profile_link); ?>" role="menuitem" tabindex="-1">
                                <img src="uploads/<?php echo htmlspecialchars($profile_img); ?>" alt="Profile Image"
                                    class="profile-icon" onerror="this.src='IMAGE/default-profile.png';">
                                Profile
                            </a>
                        </li>
                        <li role="none">
                            <a class="logout" href="index.php" role="menuitem" tabindex="-1">
                                <i class="fa-solid fa-sign-out"></i>
                                Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">

        <!-- Notifications Header -->
        <div class="notifications-header">
            <div class="header-content">
                <div class="header-text">
                    <h1>Notifications Center</h1>
                    <p class="subtitle">Stay updated with real-time alerts and notifications.</p>
                </div>
            </div>
            <div class="header-stats">
                <div class="stat-badge total-badge">
                    <span class="stat-label">Total</span>
                    <span class="stat-value" id="total-count">
                        <?php echo $notif_view === 'activities' ? $activityTotalCount : $totalCount; ?>
                    </span>
                </div>
                <div class="stat-badge unread-badge">
                    <span class="stat-label">Unread</span>
                    <span class="stat-value" id="unread-count"><?php echo $totalUnread; ?></span>
                </div>
            </div>
        </div>

        <!-- Notification View Tabs (for admins) -->
        <?php if (in_array($userRole, ['admin1', 'admin2', 'superadmin'])): ?>
            <div class="notification-tabs">
                <button class="tab-btn active" onclick="switchView('standard')">
                    <i class="fas fa-bell"></i> Notifications
                    <span class="tab-badge"><?php echo $totalCount; ?></span>
                </button>
            </div>
            <style>

            </style>
        <?php endif; ?>

        <!-- Controls Bar -->
        <div class="notifications-controls">
            <div class="controls-left">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="search-input" placeholder="Search notifications..."
                        value="<?php echo htmlspecialchars($search_query); ?>" onkeyup="performSearch()">
                </div>
            </div>

            <div class="controls-right">
                <div class="filter-group">
                    <select id="filter-type" class="filter-select" onchange="applyFilters()">
                        <option value="">All Types</option>
                        <?php foreach ($notificationTypes as $type): ?>
                            <option value="<?php echo $type['type_name']; ?>" <?php echo $filter_type === $type['type_name'] ? 'selected' : ''; ?>>
                                <?php echo $type['type_display_name']; ?>
                            </option>
                        <?php endforeach; ?>
                        <?php if (in_array($userRole, ['admin1', 'admin2', 'superadmin'])): ?>
                            <!-- Activity types separator -->
                            <optgroup label="─── Activity Types ───">
                                <option value="pre-approval" <?php echo $filter_type === 'pre-approval' ? 'selected' : ''; ?>>
                                    Pre-Approval Updates</option>
                                <option value="credit-investigation" <?php echo $filter_type === 'credit-investigation' ? 'selected' : ''; ?>>Credit Investigation</option>
                                <option value="loan-status" <?php echo $filter_type === 'loan-status' ? 'selected' : ''; ?>>
                                    Loan Status Changes</option>
                            </optgroup>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <select id="filter-status" class="filter-select" onchange="applyFilters()">
                        <option value="">All Status</option>
                        <option value="unread" <?php echo $filter_status === 'unread' ? 'selected' : ''; ?>>Unread
                        </option>
                        <option value="read" <?php echo $filter_status === 'read' ? 'selected' : ''; ?>>Read</option>
                    </select>
                </div>

                <div class="action-buttons">
                    <button class="btn btn-outline" onclick="openMarkAllReadModal()" title="Mark all as read">
                        <i class="fas fa-envelope-open"></i> Mark All Read
                    </button>
                </div>
            </div>
        </div>

        <!-- Notifications List -->
        <div class="notifications-container">
            <?php
            // Only display standard notifications
            $displayNotifications = $notifications;
            $displayTotalPages = $totalPages;
            $displayEmpty = 'No notifications';
            ?>

            <?php if (empty($displayNotifications)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3><?php echo $displayEmpty; ?></h3>
                    <p>You're all caught up! Check back later for new updates.</p>
                </div>
            <?php else: ?>
                <div class="notifications-list">
                    <?php foreach ($displayNotifications as $notif): ?>
                        <div class="notification-item <?php echo isset($notif['is_read']) ? ($notif['is_read'] ? 'read' : 'unread') : ''; ?>"
                            data-notification-id="<?php echo $notif['notification_id'] ?? '0'; ?>" data-type="standard">

                            <div class="notification-content">
                                <div class="notification-header">
                                    <h4 class="notification-title"><?php echo htmlspecialchars($notif['title']); ?></h4>
                                    <span class="notification-type"><?php echo $notif['type_display_name']; ?></span>
                                </div>
                                <p class="notification-message"><?php echo htmlspecialchars($notif['message']); ?></p>

                                <div class="notification-meta">
                                    <span class="notification-time">
                                        <i class="fas fa-clock"></i>
                                        <?php echo timeAgo($notif['created_at']); ?>
                                    </span>
                                    <?php if (!empty($notif['action_url'])): ?>
                                        <a href="<?php echo htmlspecialchars($notif['action_url']); ?>" class="notification-action">
                                            <?php echo $notif['action_text'] ?? 'View Details'; ?>
                                            <i class="fas fa-arrow-right"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="notification-actions">
                                <?php if (!$notif['is_read']): ?>
                                    <button class="action-btn" onclick="markAsRead(<?php echo $notif['notification_id']; ?>)"
                                        title="Mark as read">
                                        <i class="fas fa-check"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($displayTotalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($current_page_num > 1): ?>
                            <a href="?page=1<?php echo buildQueryString(['page' => 1], $filter_type, $filter_status, $search_query); ?>"
                                class="pag-btn">
                                <i class="fas fa-chevron-left"></i> First
                            </a>
                            <a href="?page=<?php echo $current_page_num - 1; ?><?php echo buildQueryString(['page' => $current_page_num - 1], $filter_type, $filter_status, $search_query); ?>"
                                class="pag-btn">
                                <i class="fas fa-chevron-left"></i> Prev
                            </a>
                        <?php endif; ?>

                        <div class="page-numbers">
                            <?php for ($i = max(1, $current_page_num - 2); $i <= min($displayTotalPages, $current_page_num + 2); $i++): ?>
                                <a href="?page=<?php echo $i; ?><?php echo buildQueryString(['page' => $i], $filter_type, $filter_status, $search_query); ?>"
                                    class="pag-btn <?php echo $i === $current_page_num ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                        </div>

                        <?php if ($current_page_num < $displayTotalPages): ?>
                            <a href="?page=<?php echo $current_page_num + 1; ?><?php echo buildQueryString(['page' => $current_page_num + 1], $filter_type, $filter_status, $search_query); ?>"
                                class="pag-btn">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                            <a href="?view=<?php echo $notif_view; ?>&page=<?php echo $displayTotalPages; ?><?php echo buildQueryString(['page' => $displayTotalPages], $filter_type, $filter_status, $search_query); ?>"
                                class="pag-btn">
                                Last <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Mark All Read Confirmation Modal -->
    <div id="markAllReadModal" class="mark-all-read-modal">
        <div class="mark-all-read-modal-content">
            <div class="mark-all-read-modal-header">
                <h2>Mark All as Read</h2>
                <button class="close-btn" onclick="closeMarkAllReadModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="mark-all-read-modal-body">
                <p>Are you sure you want to mark all notifications as read?</p>
                <p style="margin-top: 10px; font-size: 14px; color: #666;">This action will mark all unread
                    notifications as read.</p>
            </div>
            <div class="mark-all-read-modal-footer">
                <button class="btn btn-secondary" onclick="closeMarkAllReadModal()">
                    Cancel
                </button>
                <button class="btn btn-primary" onclick="confirmMarkAllRead()">
                    <i class="fas fa-check"></i> Mark All as Read
                </button>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        // Update datetime
        function updateDateTime() {
            const now = new Date();
            document.getElementById('datetime').textContent = now.toLocaleString('en-US', {
                weekday: 'short',
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                second: '2-digit'
            });
        }
        updateDateTime();
        setInterval(updateDateTime, 1000);

        // Toggle navigation
        function toggleNav() {
            const navbar = document.getElementById('navbar');
            const burger = document.querySelector('.burger');
            navbar.classList.toggle('active');
            burger.classList.toggle('active');
        }

        // Toggle profile dropdown
        function toggleDropdown(event) {
            event.stopPropagation();
            const dropdown = document.getElementById('dropdown');
            dropdown.classList.toggle('show');
        }

        function handleProfileKeydown(event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                toggleDropdown(event);
            }
        }

        document.addEventListener('click', function () {
            const dropdown = document.getElementById('dropdown');
            dropdown.classList.remove('show');
        });

        // Mark as read
        function markAsRead(notificationId) {
            const formData = new FormData();
            formData.append('action', 'mark_read');
            formData.append('notification_ids[]', notificationId);

            fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        // Open Mark All Read Modal
        function openMarkAllReadModal() {
            const modal = document.getElementById('markAllReadModal');
            if (modal) {
                modal.style.display = 'flex';
            }
        }

        // Close Mark All Read Modal
        function closeMarkAllReadModal() {
            const modal = document.getElementById('markAllReadModal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        // Confirm Mark All as Read
        function confirmMarkAllRead() {
            const formData = new FormData();
            formData.append('action', 'mark_all_read');

            fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        closeMarkAllReadModal();
                        location.reload();
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        // Mark all as read (old function - kept for backward compatibility)
        function markAllRead() {
            openMarkAllReadModal();
        }



        // Apply filters
        function applyFilters() {
            const filterType = document.getElementById('filter-type').value;
            const filterStatus = document.getElementById('filter-status').value;
            const search = document.getElementById('search-input').value;

            let url = '?page=1';
            if (filterType) url += '&filter_type=' + encodeURIComponent(filterType);
            if (filterStatus) url += '&filter_status=' + encodeURIComponent(filterStatus);
            if (search) url += '&search=' + encodeURIComponent(search);

            window.location.href = url;
        }

        // Search with debounce
        let searchTimeout;
        function performSearch() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(applyFilters, 500);
        }

        // Refresh counts periodically
        function refreshCounts() {
            const formData = new FormData();
            formData.append('action', 'get_counts');

            fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('unread-count').textContent = data.counts.total || 0;
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        // Switch between standard and activity notifications
        function switchView(view) {
            const url = new URL(window.location);
            url.searchParams.set('view', view);
            window.location = url.toString();
        }

        // View activity details
        function viewActivityDetails(applicationId) {
            if (!applicationId || applicationId === 0) {
                alert('Unable to load activity details');
                return;
            }
            // Redirect to the application/loan details page
            window.location = 'get_loan_details.php?id=' + applicationId;
        }

        // Refresh counts every 30 seconds
        setInterval(refreshCounts, 30000);

        document.querySelectorAll('.dropdown-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const dropdown = this.nextElementSibling;
                const icon = this.querySelector('.dropdown-icon');

                // Toggle dropdown visibility
                dropdown.style.display = dropdown.style.display === "block" ? "none" : "block";

                // Rotate icon
                icon.classList.toggle('rotate');
            });
        });

        // Toggle Sidebar for mobile
        function toggleSidebar() {
            document.querySelector('nav').classList.toggle('active');
        }

    </script>
</body>

</html>

<?php
/**
 * Helper Functions
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

function buildQueryString($additions = [], $filter_type = '', $filter_status = '', $search_query = '')
{
    $params = [];
    if ($filter_type)
        $params['filter_type'] = $filter_type;
    if ($filter_status)
        $params['filter_status'] = $filter_status;
    if ($search_query)
        $params['search'] = $search_query;

    $params = array_merge($params, $additions);

    if (empty($params))
        return '';
    return '&' . http_build_query($params);
}
?>