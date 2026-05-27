<?php
/**
 * CYCLOAN Notifications Page
 * With header and navigation bar matching user dashboard
 */

session_start();

if (!isset($_SESSION['email']) || !isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userEmail = $_SESSION['email'];

try {
    require_once 'CYCLOAN_db.php';
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection not available");
}

// Get user information including profile image
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

$notifications = [];
$totalCount = 0;
$totalUnread = 0;
$successMessage = '';
$errorMessage = '';
$current_page = basename($_SERVER['PHP_SELF']);

try {
    require_once 'NotificationManager.php';
    $notificationManager = new NotificationManager($conn);

    // Handle GET requests for AJAX/real-time notifications
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
            }
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
            exit;
        }
    }

    $currentPage = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = isset($_GET['per_page']) ? max(5, min(50, (int) $_GET['per_page'])) : 10;

    // Handle POST actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'mark_read') {
            $notificationIds = $_POST['notification_ids'] ?? [];
            if (!empty($notificationIds)) {
                foreach ($notificationIds as $notifId) {
                    $notificationManager->markAsRead($userId, intval($notifId));
                }
                $successMessage = "Notification marked as read.";
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit();
            }
        } elseif ($action === 'mark_all_read') {
            $notificationManager->markAllAsRead($userId);
            $successMessage = "All notifications marked as read.";
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit();
        } elseif ($action === 'delete') {
            $notificationIds = $_POST['notification_ids'] ?? [];
            if (!empty($notificationIds)) {
                foreach ($notificationIds as $notifId) {
                    $notificationManager->deleteNotifications($userId, intval($notifId));
                }
                $successMessage = "Notification deleted.";
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit();
            }
        }
    }

    $notificationsResult = $notificationManager->getUserNotifications($userId, [], $currentPage, $perPage);
    $notifications = $notificationsResult['notifications'] ?? [];
    $totalCount = $notificationsResult['total_count'] ?? 0;

    $unreadCountsResult = $notificationManager->getUnreadCounts($userId);
    $totalUnread = $unreadCountsResult['total'] ?? 0;

} catch (Exception $e) {
    $errorMessage = "Error: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - CYCLOAN</title>
    <link rel="stylesheet" href="CSS/user_dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="CSS/dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <style>
        .notification-bell {
            font-size: 1.3rem;
            color: #1b5e20;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
        }

        .notification-bell:hover {
            background: rgba(27, 94, 32, 0.1);
            color: #2e7d32;
            transform: scale(1.1);
        }

        /* Profile Dropdown Styles */
        .profile-container {
            position: relative;
            cursor: pointer;
        }

        .profile {
            width: 40px;
            height: 40px;
            background-color: #2e7d32;
            display: flex;
            align-items: center;
            justify-content: center;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #2e7d32;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .profile:hover {
            transform: scale(1.1);
            box-shadow: 0 0 0 4px rgba(27, 94, 32, 0.2);
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            top: 50px;
            background: #f8fafc;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1), 0 1px 3px rgba(0, 0, 0, 0.08);
            border-radius: 12px;
            min-width: 200px;
            z-index: 1000;
            opacity: 0;
            transform: translateY(-10px);
            transition: opacity 0.3s ease, transform 0.3s ease;
        }

        .dropdown-menu.show {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }

        .dropdown-menu ul {
            list-style: none;
            padding: 8px 0;
            margin: 0;
        }

        .dropdown-menu li {
            display: flex;
            padding: 12px 20px;
            align-items: center;
            gap: 12px;
            color: #0a1f1b;
            font-size: 0.95rem;
            font-weight: 500;
            transition: background 0.2s ease;
        }

        .dropdown-menu li:hover {
            background: #e2e8f0;
        }

        .dropdown-menu li a img.profile-icon {
            transition: transform 0.3s ease;
        }

        .dropdown-menu li:hover img.profile-icon {
            transform: scale(1.1);
        }

        .dropdown-menu li i {
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 1rem;
            color: #2e7d32;
        }

        .dropdown-menu li:hover i {
            color: #1b5e20;
        }

        .dropdown-menu li a img {
            border-radius: 50%;
        }

        .dropdown-menu ul li a {
            color: black;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 1px 10px;
            text-decoration: none;
        }

        .dropdown-menu ul li a img.profile-icon,
        .dropdown-menu ul li a i {
            width: 22px;
            height: 22px;
            object-fit: cover;
        }

        /* Burger Menu Navigation Styles */
        .nav-container {
            position: relative;
        }

        .burger {
            display: none;
            cursor: pointer;
            width: 30px;
            height: 20px;
            position: fixed;
            top: 20px;
            left: 20px;
            background: none;
            border: none;
            z-index: 1200;
        }

        .burger span {
            background-color: #f5dc00;
            height: 3px;
            width: 100%;
            position: absolute;
            left: 0;
            transition: all 0.3s ease;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.8);
        }

        .burger span:nth-child(1) {
            top: 0;
        }

        .burger span:nth-child(2) {
            top: 8px;
        }

        .burger span:nth-child(3) {
            top: 16px;
        }

        .burger.active span:nth-child(1) {
            transform: rotate(45deg);
            top: 8px;
            background-color: #fbc02d;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.8);
        }

        .burger.active span:nth-child(2) {
            opacity: 0;
        }

        .burger.active span:nth-child(3) {
            transform: rotate(-45deg);
            top: 8px;
            background-color: #fbc02d;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.8);
        }

        /* Navigation Styles */
        .nav-container {
            position: relative;
        }

        nav {
            position: fixed;
            top: 0;
            left: 0;
            width: 260px;
            height: 100vh;
            background-color: #2e7d32;
            background-image: url("IMAGE/db-bg.com.jpg");
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            padding: 20px;
            color: white;
            display: flex;
            flex-direction: column;
            gap: 10px;
            z-index: 1100;
            transition: none;
            transform: none;
        }

        nav .sidebar-logo {
            width: 100%;
            max-width: 180px;
            margin: 20px auto 40px;
            display: block;
            filter: drop-shadow(0 5px 8px rgba(0, 0, 0, 0.8));
        }

        nav a {
            position: relative;
            display: flex;
            align-items: center;
            padding: 12px 10px;
            text-decoration: none;
            color: white;
            border-radius: 8px;
            transition: color 0.3s ease;
            z-index: 1;
        }

        nav a::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 0;
            height: 100%;
            background: linear-gradient(90deg, rgba(251, 192, 45, 0.8), rgba(0, 0, 0, 0));
            transition: width 0.3s ease-in-out;
            border-radius: 8px;
            z-index: -1;
        }

        nav a:hover::before,
        nav a.active::before {
            width: 100%;
        }

        nav a:hover,
        nav a.active {
            color: #f8fafc;
            background: linear-gradient(90deg, rgba(251, 192, 45, 0.8), rgba(0, 0, 0, 0));
        }

        nav a i {
            margin-right: 12px;
        }

        /* Desktop nav should always be visible */
        @media (min-width: 769px) {
            nav {
                transform: translateX(0) !important;
            }

            nav.show,
            nav.active {
                transform: translateX(0) !important;
            }

            .burger {
                display: none !important;
            }
        }

        .main-content {
            padding: 40px;
            background: linear-gradient(135deg, #f5f7fa 0%, #fafbfc 100%);
            min-height: calc(100vh - 80px);
            margin-left: 260px;
            margin-top: 70px;
        }

        .notifications-header {
            margin-bottom: 40px;
            display: flex;
            align-items: center;
            gap: 15px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }

        .notifications-header i {
            font-size: 2rem;
            color: #1b5e20;
        }

        .notifications-header h2 {
            color: #1b5e20;
            font-size: 2rem;
            margin: 0;
            font-weight: 600;
        }

        .notification-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            gap: 15px;
            flex-wrap: wrap;
        }

        .stats {
            display: flex;
            gap: 15px;
            font-size: 0.95rem;
        }

        .stat {
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            border-left: 4px solid #1b5e20;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .stat:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .stat strong {
            color: #1b5e20;
            font-size: 1.4rem;
        }

        .stat span {
            color: #888;
            font-size: 0.85rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(27, 94, 32, 0.2);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(27, 94, 32, 0.3);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .notifications-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .notification-item {
            background: white;
            border-left: 5px solid #ddd;
            padding: 20px;
            border-radius: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
        }

        .notification-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #1b5e20, transparent);
            opacity: 0;
            transition: opacity 0.3s;
        }

        .notification-item:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateX(4px);
        }

        .notification-item.unread {
            background: linear-gradient(135deg, #f0f8ff 0%, #e8f5e9 100%);
            border-left-color: #1b5e20;
            box-shadow: 0 4px 12px rgba(27, 94, 32, 0.15);
        }

        .notification-item.unread::before {
            opacity: 1;
        }

        .notification-item h3 {
            color: #1b5e20;
            margin-bottom: 10px;
            font-size: 1.15rem;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
        }

        .notification-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            background: rgba(27, 94, 32, 0.1);
            color: #1b5e20;
        }

        .badge-new {
            background: linear-gradient(135deg, #d32f2f, #f57c00);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(211, 47, 47, 0.3);
            animation: badgePulse 2s infinite;
        }

        @keyframes badgePulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.1);
            }
        }

        .notification-item p {
            color: #555;
            margin-bottom: 12px;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .notification-meta {
            font-size: 0.85rem;
            color: #888;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .notification-meta i {
            color: #1b5e20;
        }

        .notification-type-badge {
            background: rgba(27, 94, 32, 0.1);
            color: #1b5e20;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .notification-actions {
            display: flex;
            gap: 10px;
            padding-top: 12px;
            border-top: 1px solid #f0f0f0;
            margin-top: 12px;
        }

        .notification-actions form {
            display: inline;
        }

        .notification-actions button {
            background: #f0f0f0;
            color: #333;
            padding: 8px 14px;
            font-size: 0.85rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .notification-actions button:hover {
            background: #e0e0e0;
            border-color: #999;
        }

        .notification-actions button.delete {
            background: #ffebee;
            color: #d32f2f;
            border-color: #ffcdd2;
        }

        .notification-actions button.delete:hover {
            background: #ffcdd2;
            border-color: #d32f2f;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        }

        .empty-state i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 20px;
            opacity: 0.6;
        }

        .empty-state h3 {
            color: #666;
            margin-bottom: 10px;
            font-size: 1.3rem;
            font-weight: 600;
        }

        .empty-state p {
            color: #999;
            font-size: 0.95rem;
        }

        .message {
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            animation: slideDown 0.3s ease;
            border-left: 4px solid;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message.success {
            background: linear-gradient(135deg, #d4edda 0%, #c8e6c9 100%);
            color: #155724;
            border-left-color: #28a745;
        }

        .message.error {
            background: linear-gradient(135deg, #f8d7da 0%, #ffcccc 100%);
            color: #721c24;
            border-left-color: #d32f2f;
        }

        .message i {
            font-size: 1.1rem;
        }

        .pagination-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px solid #e0e0e0;
            flex-wrap: wrap;
        }

        .pagination-info {
            color: #666;
            font-size: 0.9rem;
            font-weight: 500;
            margin-right: 15px;
        }

        .pagination-controls {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .pagination-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            border: 2px solid #ddd;
            background: white;
            color: #333;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            text-decoration: none;
        }

        .pagination-btn:hover:not(:disabled) {
            background: #f0f0f0;
            border-color: #1b5e20;
            color: #1b5e20;
            transform: translateY(-2px);
            box-shadow: 0 2px 6px rgba(27, 94, 32, 0.15);
        }

        .pagination-btn.active {
            background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%);
            color: white;
            border-color: #1b5e20;
            box-shadow: 0 4px 12px rgba(27, 94, 32, 0.3);
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            border-color: #ccc;
        }

        .pagination-btn i {
            font-size: 0.95rem;
        }

        .page-size-selector {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: 20px;
        }

        .page-size-selector label {
            color: #666;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .page-size-selector select {
            padding: 8px 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            background: white;
            color: #333;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s;
        }

        .page-size-selector select:hover {
            border-color: #1b5e20;
        }

        .page-size-selector select:focus {
            outline: none;
            border-color: #1b5e20;
            box-shadow: 0 0 0 3px rgba(27, 94, 32, 0.1);
        }

        @media (max-width: 768px) {
            .burger {
                display: block;
            }

            nav {
                width: 100%;
                height: auto;
                position: fixed;
                top: 70px;
                left: 0;
                right: 0;
                transform: translateX(-100%);
                flex-direction: column;
                padding: 20px 0;
                z-index: 1100;
            }

            nav.show,
            nav.active {
                transform: translateX(0);
            }

            nav .sidebar-logo {
                margin: 10px auto 20px;
                max-width: 120px;
            }

            nav a {
                padding: 15px 20px;
                border-radius: 0;
            }

            .main-content {
                padding: 20px;
                margin-left: 0;
                margin-top: 80px;
            }
        }

        @media (max-width: 768px) {
            .pagination-container {
                flex-direction: column;
                gap: 15px;
            }

            .pagination-info {
                margin-right: 0;
            }

            .page-size-selector {
                margin-left: 0;
            }

            .pagination-btn {
                width: 36px;
                height: 36px;
                font-size: 0.8rem;
            }
        }
    </style>
</head>

<body>
    <!-- Header -->
    <div class="header">
        <div class="profileXdate">
            <div id="datetime" class="datetime"></div>
            <a href="notifications.php" class="notification-bell" title="View Notifications">
                <i class="fa-solid fa-bell"></i>
            </a>
            <div class="profile-container" onclick="toggleDropdown(event)">
                <img src="<?= htmlspecialchars($profile_image) ?>" alt="Profile Image" class="profile">
                <div class="dropdown-menu" id="dropdown">
                    <ul>
                        <li>
                            <a href="profile.php" class="<?= $current_page === 'profile.php' ? 'active' : '' ?>">
                                <img src="<?= htmlspecialchars($profile_image) ?>" alt="Profile Image"
                                    class="profile-icon"> Profile
                            </a>
                        </li>
                        <li><a class="logout" href="index.php"><i class="fa-solid fa-sign-out"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <div class="nav-container">
        <button class="burger" aria-label="Toggle menu">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <nav>
            <img src="IMAGE/Main-Logo.png" alt="CYCLOAN Logo" class="sidebar-logo">
            <a href="user_dashboard.php" class="<?= $current_page === 'user_dashboard.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-table-columns"></i> DASHBOARD
            </a>
            <a href="user_active_record.php" class="<?= $current_page === 'user_active_record.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-user-check"></i> ACTIVE RECORDS
            </a>
            <a href="user_pending_records.php"
                class="<?= $current_page === 'user_pending_records.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-spinner"></i> PENDING RECORDS
            </a>
            <a href="user_closed_records.php"
                class="<?= $current_page === 'user_closed_records.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-circle-check"></i> CLOSED RECORDS
            </a>
            <a href="user_history_activity.php"
                class="<?= $current_page === 'user_history_activity.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-clipboard"></i> HISTORY ACTIVITY
            </a>
            <a href="#" onclick="openCalculatorModal(); return false;"
                class="<?= $current_page === 'loan_calculator' ? 'active' : '' ?>">
                <i class="fa-solid fa-calculator"></i> LOAN CALCULATOR
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
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

        <!-- Notifications Header -->
        <div class="notifications-header">
            <h2><i class="fas fa-bell"></i> Notifications</h2>
        </div>

        <!-- Controls and Stats -->
        <div class="notification-controls">
            <div class="stats">
                <div class="stat">
                    <strong><?php echo number_format($totalCount); ?></strong>
                    <span>Total Notifications</span>
                </div>
                <div class="stat">
                    <strong><?php echo number_format($totalUnread); ?></strong>
                    <span>Unread</span>
                </div>
            </div>
            <?php if ($totalUnread > 0): ?>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="mark_all_read">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-check-double"></i> Mark All Read
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <!-- Notifications List -->
        <div class="notifications-list">
            <?php if (empty($notifications)): ?>
                <div class="empty-state">
                    <div><i class="fas fa-inbox"></i></div>
                    <h3>No Notifications</h3>
                    <p>You're all caught up! Check back later for new updates.</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>">
                        <h3>
                            <div class="notification-icon">
                                <i class="<?php
                                $type = strtolower($notification['type_display_name'] ?? '');
                                if (strpos($type, 'comment') !== false)
                                    echo 'fas fa-comment-dots';
                                elseif (strpos($type, 'status') !== false)
                                    echo 'fas fa-sync-alt';
                                elseif (strpos($type, 'document') !== false)
                                    echo 'fas fa-file-upload';
                                elseif (strpos($type, 'loan') !== false)
                                    echo 'fas fa-money-bill-wave';
                                else
                                    echo 'fas fa-bell';
                                ?>"></i>
                            </div>
                            <?php echo htmlspecialchars($notification['title']); ?>
                            <?php if (!$notification['is_read']): ?>
                                <span class="badge-new">NEW</span>
                            <?php endif; ?>
                        </h3>
                        <p><?php echo htmlspecialchars($notification['short_message'] ?? $notification['message']); ?></p>
                        <div class="notification-meta">
                            <span>
                                <i class="far fa-clock"></i>
                                <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                            </span>
                            <span class="notification-type-badge">
                                <i class="fas fa-tag"></i> <?php echo htmlspecialchars($notification['type_display_name']); ?>
                            </span>
                        </div>
                        <div class="notification-actions">
                            <?php if (!$notification['is_read']): ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="mark_read">
                                    <input type="hidden" name="notification_ids[]"
                                        value="<?php echo $notification['notification_id']; ?>">
                                    <button type="submit"><i class="fas fa-check"></i> Mark as Read</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination Controls -->
        <?php if (!empty($notifications) && $totalCount > $perPage): ?>
            <div class="pagination-container">
                <div class="pagination-info">
                    Showing <strong><?php echo ($currentPage - 1) * $perPage + 1; ?></strong> to
                    <strong><?php echo min($currentPage * $perPage, $totalCount); ?></strong>
                    of <strong><?php echo number_format($totalCount); ?></strong> notifications
                </div>

                <div class="pagination-controls">
                    <?php
                    $totalPages = ceil($totalCount / $perPage);
                    $startPage = max(1, $currentPage - 2);
                    $endPage = min($totalPages, $currentPage + 2);
                    ?>

                    <!-- Previous Button -->
                    <?php if ($currentPage > 1): ?>
                        <a href="?page=1&per_page=<?php echo $perPage; ?>" class="pagination-btn" title="First Page">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        <a href="?page=<?php echo $currentPage - 1; ?>&per_page=<?php echo $perPage; ?>" class="pagination-btn"
                            title="Previous Page">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <button class="pagination-btn" disabled>
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button class="pagination-btn" disabled>
                            <i class="fas fa-chevron-left"></i>
                        </button>
                    <?php endif; ?>

                    <!-- Page Numbers -->
                    <?php if ($startPage > 1): ?>
                        <span style="color: #999;">...</span>
                    <?php endif; ?>

                    <?php for ($page = $startPage; $page <= $endPage; $page++): ?>
                        <?php if ($page == $currentPage): ?>
                            <button class="pagination-btn active"><?php echo $page; ?></button>
                        <?php else: ?>
                            <a href="?page=<?php echo $page; ?>&per_page=<?php echo $perPage; ?>" class="pagination-btn">
                                <?php echo $page; ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($endPage < $totalPages): ?>
                        <span style="color: #999;">...</span>
                    <?php endif; ?>

                    <!-- Next Button -->
                    <?php if ($currentPage < $totalPages): ?>
                        <a href="?page=<?php echo $currentPage + 1; ?>&per_page=<?php echo $perPage; ?>" class="pagination-btn"
                            title="Next Page">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                        <a href="?page=<?php echo $totalPages; ?>&per_page=<?php echo $perPage; ?>" class="pagination-btn"
                            title="Last Page">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <button class="pagination-btn" disabled>
                            <i class="fas fa-chevron-right"></i>
                        </button>
                        <button class="pagination-btn" disabled>
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Items Per Page Selector -->
                <div class="page-size-selector">
                    <label for="perPageSelect">Items per page:</label>
                    <select id="perPageSelect" onchange="changeItemsPerPage(this.value)">
                        <option value="5" <?php echo $perPage == 5 ? 'selected' : ''; ?>>5</option>
                        <option value="10" <?php echo $perPage == 10 ? 'selected' : ''; ?>>10</option>
                        <option value="20" <?php echo $perPage == 20 ? 'selected' : ''; ?>>20</option>
                        <option value="30" <?php echo $perPage == 30 ? 'selected' : ''; ?>>30</option>
                    </select>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Loan Calculator Modal -->
    <div id="loanCalculatorModal" class="calculatorModal">
        <div class="calculatorModal-content">
            <div class="calculatorModal-header">
                <h2><i class="fa-solid fa-calculator"></i> Loan Calculator</h2>
                <span class="close" onclick="closeCalculatorModal()">×</span>
            </div>
            <div class="calculatorModal-body">
                <div class="calculator-layout">
                    <!-- Calculator Form -->
                    <div class="calculator-form">
                        <form id="calculatorForm" onsubmit="event.preventDefault(); calculateLoan();">
                            <div class="form-group">
                                <label for="loanType">Loan Type <span style="color: red;">*</span></label>
                                <select id="loanType" name="loanType" required onchange="updateLoanAmountRange()">
                                    <option value="">Select Loan Type</option>
                                    <option value="Individual">Individual</option>
                                    <option value="Cooperative">Cooperative</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="loanAmount" id="loanAmountLabel">Loan Amount (₱10,000 - ₱100,000) <span
                                        style="color: red;">*</span></label>
                                <input type="number" id="loanAmount" name="loanAmount" min="10000" max="100000"
                                    step="1000" required placeholder="Enter amount">
                            </div>

                            <div class="form-group">
                                <label for="interestRate">Annual Interest Rate <span
                                        style="color: red;">*</span></label>
                                <input type="number" id="interestRate" name="interestRate" step="0.01" readonly>
                                <div class="interest-rate-display" id="interestRateDisplay">Loading...</div>
                            </div>

                            <div class="form-group">
                                <label for="termLength">Term Length (Months) <span style="color: red;">*</span></label>
                                <select id="termLength" name="termLength" required onchange="updateRepaymentOptions()">
                                    <option value="">Select Term Length</option>
                                    <option value="6">6 Months</option>
                                    <option value="12">12 Months</option>
                                    <option value="18">18 Months</option>
                                    <option value="24">24 Months</option>
                                    <option value="36">36 Months</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="repaymentFrequency">Repayment Frequency <span
                                        style="color: red;">*</span></label>
                                <select id="repaymentFrequency" name="repaymentFrequency" required>
                                    <option value="">Select Repayment Frequency</option>
                                    <option value="Monthly">Monthly</option>
                                    <option value="Quarterly">Quarterly</option>
                                    <option value="Annually">Annually</option>
                                </select>
                            </div>

                            <div class="error-message" id="errorMessage">
                                <i class="fa-solid fa-exclamation-circle"></i>
                                <span id="errorText"></span>
                            </div>

                            <button type="submit" class="calculate-btn"><i class="fa-solid fa-calculator"></i>
                                Calculate</button>
                        </form>
                    </div>

                    <!-- Results Display -->
                    <div class="results-container">
                        <h2><i class="fa-solid fa-chart-pie"></i> Results</h2>
                        <div id="resultsDisplay">
                            <div class="no-result">
                                <i class="fa-solid fa-calculator"></i>
                                <p>Enter loan details and click Calculate to see results</p>
                            </div>
                        </div>
                    </div>
                </div>
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

        // Toggle dropdown menu
        function toggleDropdown(event) {
            event.stopPropagation();
            const dropdown = document.getElementById('dropdown');
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function () {
            const dropdown = document.getElementById('dropdown');
            dropdown.style.display = 'none';
        });

        // Toggle mobile navigation
        const burger = document.querySelector('.burger');
        const navContainer = document.querySelector('.nav-container');
        if (burger) {
            burger.addEventListener('click', function () {
                navContainer.classList.toggle('active');
            });
        }

        // Loan Calculator Functions
        function openCalculatorModal() {
            document.getElementById("loanCalculatorModal").style.display = "flex";
            document.getElementById("loanCalculatorModal").classList.add("show");
            document.getElementById("calculatorForm").reset();
            document.getElementById("resultsDisplay").innerHTML = '<div class="no-result"><i class="fa-solid fa-calculator"></i><p>Enter loan details and click Calculate to see results</p></div>';
            updateRepaymentOptions();
            fetch("user_dashboard.php?action=get_current_interest_rate")
                .then((response) => response.json())
                .then((data) => {
                    if (data.success) {
                        document.getElementById("interestRate").value = data.interest_rate;
                        document.getElementById("interestRateDisplay").textContent = `${data.interest_rate}%`;
                    } else {
                        document.getElementById("interestRateDisplay").textContent = "Error loading rate";
                    }
                })
                .catch(() => {
                    document.getElementById("interestRateDisplay").textContent = "Using default rate";
                    document.getElementById("interestRate").value = "12";
                });
        }

        function closeCalculatorModal() {
            document.getElementById("loanCalculatorModal").style.display = "none";
            document.getElementById("loanCalculatorModal").classList.remove("show");
        }

        function updateLoanAmountRange() {
            const loanType = document.getElementById("loanType").value;
            const loanAmount = document.getElementById("loanAmount");
            const loanAmountLabel = document.getElementById("loanAmountLabel");
            if (loanType === "Individual") {
                loanAmount.min = 10000;
                loanAmount.max = 100000;
                loanAmountLabel.textContent = "Loan Amount (₱10,000 - ₱100,000) *";
            } else if (loanType === "Cooperative") {
                loanAmount.min = 50000;
                loanAmount.max = 500000;
                loanAmountLabel.textContent = "Loan Amount (₱50,000 - ₱500,000) *";
            }
        }

        function updateRepaymentOptions() {
            const termLength = parseInt(document.getElementById("termLength").value) || 0;
            const freqSelect = document.getElementById("repaymentFrequency");
            if (!freqSelect) return;
            const options = freqSelect.options;
            for (let i = 1; i < options.length; i++) {
                options[i].disabled = false;
                options[i].style.display = "";
            }
            if (termLength === 6) {
                for (let i = 1; i < options.length; i++) {
                    const val = options[i].value;
                    if (val === "Quarterly" || val === "Annually") {
                        options[i].disabled = true;
                        options[i].style.display = "none";
                    }
                }
                if (freqSelect.value === "Quarterly" || freqSelect.value === "Annually") {
                    freqSelect.value = "";
                }
            }
        }

        function calculateLoan() {
            const loanAmount = parseFloat(document.getElementById("loanAmount").value);
            const interestRate = parseFloat(document.getElementById("interestRate").value) / 100;
            const termLength = parseInt(document.getElementById("termLength").value);
            const repaymentFrequency = document.getElementById("repaymentFrequency").value;

            if (termLength === 6 && repaymentFrequency !== "Monthly") {
                document.getElementById("resultsDisplay").innerHTML = `<p class="error"><i class="fas fa-exclamation-circle"></i> For 6-month term, only Monthly repayment is allowed.</p>`;
                return;
            }

            let paymentsPerYear;
            switch (repaymentFrequency) {
                case "Monthly":
                    paymentsPerYear = 12;
                    break;
                case "Quarterly":
                    paymentsPerYear = 4;
                    break;
                case "Annually":
                    paymentsPerYear = 1;
                    break;
                default:
                    paymentsPerYear = 12;
            }

            const totalPayments = (termLength * paymentsPerYear) / 12;
            const monthlyRate = interestRate / paymentsPerYear;
            const monthlyPayment = (loanAmount * (monthlyRate * Math.pow(1 + monthlyRate, totalPayments))) / (Math.pow(1 + monthlyRate, totalPayments) - 1);
            const totalInterest = monthlyPayment * totalPayments - loanAmount;

            document.getElementById("resultsDisplay").innerHTML = `
                <p><strong>${repaymentFrequency} Payment:</strong> ₱${monthlyPayment.toFixed(2)}</p>
                <p><strong>Total Interest:</strong> ₱${totalInterest.toFixed(2)}</p>
                <p><strong>Total Repayment:</strong> ₱${(loanAmount + totalInterest).toFixed(2)}</p>
            `;
        }

        // Close calculator modal when clicking outside
        window.onclick = function (event) {
            const modal = document.getElementById("loanCalculatorModal");
            if (event.target == modal) {
                closeCalculatorModal();
            }
        }

        // Auto-polling for new notifications
        let notificationPollingInterval;
        const userId = <?= intval($_SESSION['user_id']) ?>;

        function startNotificationPolling() {
            // Check every 30 seconds for new notifications
            notificationPollingInterval = setInterval(checkForNewNotifications, 30000);
        }

        function checkForNewNotifications() {
            fetch('notifications.php?action=get_unread_count&user_id=' + userId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const unreadCount = data.unread_count;
                        const currentPage = new URLSearchParams(window.location.search).get('page') || 1;

                        // If on first page and there are new unread notifications, refresh the page
                        if (currentPage == 1 && unreadCount > 0) {
                            // Auto-refresh page to show new notifications
                            location.reload();
                        }
                    }
                })
                .catch(error => console.error('Error checking notifications:', error));
        }

        // Initialize auto-polling when page loads
        document.addEventListener('DOMContentLoaded', function () {
            startNotificationPolling();
        });

        // Stop polling when user leaves the page
        window.addEventListener('beforeunload', function () {
            if (notificationPollingInterval) {
                clearInterval(notificationPollingInterval);
            }
        });

        // Pagination - Change items per page
        function changeItemsPerPage(perPage) {
            const currentPage = new URLSearchParams(window.location.search).get('page') || 1;
            window.location.href = '?page=1&per_page=' + perPage;
        }

        // Profile Dropdown Toggle
        function toggleDropdown(event) {
            event.preventDefault();
            event.stopPropagation();
            const dropdown = document.getElementById('dropdown');
            if (dropdown) {
                dropdown.classList.toggle('show');
            }
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function (event) {
            const dropdown = document.getElementById('dropdown');
            const profileContainer = document.querySelector('.profile-container');
            if (dropdown && profileContainer && !profileContainer.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });

        // Burger Menu Navigation Toggle
        document.addEventListener('DOMContentLoaded', function () {
            const burger = document.querySelector('.burger');
            const navContainer = document.querySelector('.nav-container');
            const nav = navContainer ? navContainer.querySelector('nav') : null;

            if (burger && nav) {
                burger.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    nav.classList.toggle('show');
                    burger.classList.toggle('active');
                });

                // Close nav when clicking on a link
                const navLinks = nav.querySelectorAll('a');
                navLinks.forEach(link => {
                    link.addEventListener('click', function () {
                        if (window.innerWidth <= 768) {
                            nav.classList.remove('show');
                            burger.classList.remove('active');
                        }
                    });
                });

                // Close nav when clicking outside
                document.addEventListener('click', function (event) {
                    if (nav && burger) {
                        const isClickInsideNav = nav.contains(event.target);
                        const isClickOnBurger = burger.contains(event.target);

                        if (!isClickInsideNav && !isClickOnBurger && window.innerWidth <= 768) {
                            nav.classList.remove('show');
                            burger.classList.remove('active');
                        }
                    }
                });
            }
        });

        // Burger Menu Navigation Toggle
        function initBurgerMenu() {
            const burger = document.querySelector('.burger');
            const nav = document.querySelector('nav');

            if (!burger || !nav) {
                console.error('Burger or nav element not found');
                return;
            }

            // Burger click handler
            burger.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                nav.classList.toggle('show');
                nav.classList.toggle('active');
                burger.classList.toggle('active');
            });

            // Close nav when clicking on a link
            const navLinks = nav.querySelectorAll('a');
            navLinks.forEach(link => {
                link.addEventListener('click', function () {
                    if (window.innerWidth <= 768) {
                        nav.classList.remove('show');
                        nav.classList.remove('active');
                        burger.classList.remove('active');
                    }
                });
            });

            // Close nav when clicking outside
            document.addEventListener('click', function (event) {
                const isClickInsideNav = nav.contains(event.target);
                const isClickOnBurger = burger.contains(event.target);

                if (!isClickInsideNav && !isClickOnBurger && window.innerWidth <= 768) {
                    if (nav.classList.contains('show') || nav.classList.contains('active')) {
                        nav.classList.remove('show');
                        nav.classList.remove('active');
                        burger.classList.remove('active');
                    }
                }
            });
        }

        // Run burger init on load
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () {
                startNotificationPolling();
                initBurgerMenu();
            });
        } else {
            startNotificationPolling();
            initBurgerMenu();
        }

        // Initialize datetime and update every second
        updateDateTime();
        setInterval(updateDateTime, 1000);
    </script>
</body>

</html>