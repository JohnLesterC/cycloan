<?php
session_start();
require "CYCLOAN_db.php";
require "credit_points_manager.php";

// Check if admin is logged in
if (!isset($_SESSION['email']) || !isset($_SESSION['role'])) {
    header("Location: index.php");
    exit();
}

$adminRole = $_SESSION['role'];
$current_page = basename($_SERVER['PHP_SELF']);

// Only superadmin and admin1 can access this page
if (!in_array($adminRole, ['superadmin', 'admin1'])) {
    $_SESSION['error'] = 'Access denied. Only administrators can manage credit points.';
    header('Location: admin2_dashboard.php');
    exit();
}

// Get admin info
$email = $_SESSION['email'];
$valid_roles = ['superadmin' => 'superadmins', 'admin1' => 'admin1', 'admin2' => 'admin2'];
$table_name = $valid_roles[$adminRole] ?? 'admin2';
$stmt = $conn->prepare("SELECT id, profile_img FROM $table_name WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$stmt->close();

$admin_id = $admin['id'];
$profile_img = !empty($admin['profile_img']) ? $admin['profile_img'] : "default.png";

$creditManager = getCreditPointsManager();

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'update_points') {
        $user_id = (int) $_POST['user_id'];
        $points = (int) $_POST['points'];
        $reason = trim($_POST['reason']);

        if (empty($reason)) {
            echo json_encode(['success' => false, 'message' => 'Reason is required']);
            exit();
        }

        $success = $creditManager->setPoints($user_id, $points, $reason, $admin_id, $adminRole);
        echo json_encode(['success' => $success, 'message' => $success ? 'Credit points updated successfully!' : 'Failed to update credit points']);
        exit();
    }

    if ($_POST['action'] === 'add_points') {
        $user_id = (int) $_POST['user_id'];
        $points = (int) $_POST['points'];
        $reason = trim($_POST['reason']);

        if ($points <= 0) {
            echo json_encode(['success' => false, 'message' => 'Points must be greater than 0']);
            exit();
        }

        $success = $creditManager->addPoints($user_id, $points, $reason, null, $admin_id, $adminRole);
        echo json_encode(['success' => $success, 'message' => $success ? "Added $points points successfully!" : 'Failed to add points']);
        exit();
    }

    if ($_POST['action'] === 'deduct_points') {
        $user_id = (int) $_POST['user_id'];
        $points = (int) $_POST['points'];
        $reason = trim($_POST['reason']);

        if ($points <= 0) {
            echo json_encode(['success' => false, 'message' => 'Points must be greater than 0']);
            exit();
        }

        $success = $creditManager->deductPoints($user_id, $points, $reason, null, $admin_id, $adminRole);
        echo json_encode(['success' => $success, 'message' => $success ? "Deducted $points points successfully!" : 'Failed to deduct points']);
        exit();
    }

    if ($_POST['action'] === 'get_user_history') {
        $user_id = (int) $_POST['user_id'];
        $history = $creditManager->getUserHistory($user_id, 20);
        echo json_encode(['success' => true, 'history' => $history]);
        exit();
    }
}

// Fetch all users with their credit points
$stmt = $conn->prepare("
    SELECT id, CONCAT(first_name, ' ', last_name) as full_name, email, 
           credit_points, profile_image, credit_points_updated_at
    FROM users1 
    WHERE status = 'active'
    ORDER BY credit_points DESC
");
$stmt->execute();
$result = $stmt->get_result();
$users = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get credit points settings
$settings = $creditManager->getAllSettings();

$dashboard_link = $adminRole === 'superadmin' ? 'Superadmin_dashboard.php' : ($adminRole === 'admin1' ? 'admin1_dashboard.php' : 'admin2_dashboard.php');
$profile_link = $adminRole === 'superadmin' ? 'profileSuperadmin.php' : ($adminRole === 'admin1' ? 'profileAdmin1.php' : 'profileAdmin2.php');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Credit Rate - CYCLOAN</title>
    <link rel="stylesheet" href="CSS/admin_dashboard.css">
    <link rel="stylesheet" href="CSS/admin_profile.css">
    <link rel="stylesheet" href="CSS/nav_active.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <style>
        .main-content {
            margin-left: 260px;
            padding: 100px 30px 30px;
            min-height: 100vh;
        }

        .page-header {
            background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 70%, #fbc02d 100%);
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
            color: white;
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .page-header h1 {
            margin: 0 0 10px 0;
            font-size: 32px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .page-header p {
            margin: 0;
            opacity: 0.9;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .stat-card h3 {
            font-size: 14px;
            color: #666;
            margin: 0 0 10px 0;
            text-transform: uppercase;
        }

        .stat-card .value {
            font-size: 32px;
            font-weight: 700;
            color: #1b5e20;
        }

        .users-table-container {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
        }

        .users-table th,
        .users-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        .users-table th {
            background: #f5f5f5;
            font-weight: 600;
            color: #333;
        }

        .users-table tr:hover {
            background: #f9f9f9;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .points-badge {
            display: inline-block;
            padding: 6px 12px;
            background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 70%, #fbc02d 100%);
            color: white;
            border-radius: 20px;
            font-weight: 600;
        }

        .action-btn {
            padding: 8px 16px;
            background: #1b5e20;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            background: #2e7d32;
            transform: translateY(-2px);
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: white;
            margin: 50px auto;
            padding: 30px;
            border-radius: 16px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h2 {
            margin: 0;
        }

        .close {
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #999;
        }

        .close:hover {
            color: #333;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #46a047;
        }

        .button-group {
            display: flex;
            gap: 10px;
        }

        .btn-primary {
            flex: 1;
            padding: 12px 24px;
            background: linear-gradient(135deg, #46a047 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }

        .btn-secondary {
            flex: 1;
            padding: 12px 24px;
            background: white;
            color: #333;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }

        .history-item {
            padding: 15px;
            background: #f5f5f5;
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .history-item.positive {
            border-left: 4px solid #4caf50;
        }

        .history-item.negative {
            border-left: 4px solid #f44336;
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
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
        }

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

        .notification {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 12px;
            font-weight: 500;
            animation: slideIn 0.3s ease;
        }

        .notification.success {
            background-color: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .notification.error {
            background-color: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        .notification.info {
            background-color: #dbeafe;
            color: #0c2340;
            border-left: 4px solid #0369a1;
        }

        .notification.fade-out {
            animation: slideOut 0.3s ease forwards;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slideOut {
            from {
                opacity: 1;
                transform: translateX(0);
            }

            to {
                opacity: 0;
                transform: translateX(-20px);
            }
        }

        .custom-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            min-width: 320px;
            max-width: 450px;
            padding: 16px 20px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15), 0 2px 8px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 10000;
            opacity: 0;
            transform: translateX(400px);
            transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        .custom-notification.show {
            opacity: 1;
            transform: translateX(0);
        }

        .custom-notification i {
            font-size: 24px;
            flex-shrink: 0;
        }

        .custom-notification.success {
            border-left: 4px solid #16a34a;
        }

        .custom-notification.success i {
            color: #16a34a;
        }

        .custom-notification.error {
            border-left: 4px solid #dc2626;
        }

        .custom-notification.error i {
            color: #dc2626;
        }

        .custom-notification.warning {
            border-left: 4px solid #f59e0b;
        }

        .custom-notification.warning i {
            color: #f59e0b;
        }

        .custom-notification.info {
            border-left: 4px solid #3b82f6;
        }

        .custom-notification.info i {
            color: #3b82f6;
        }

        .notification-message {
            flex: 1;
            font-size: 14px;
            color: var(--darker);
            font-weight: 500;
            line-height: 1.5;
        }

        .notification-close {
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }

        .notification-close:hover {
            background: #f3f4f6;
            color: var(--darker);
        }

        .notification-close i {
            font-size: 16px;
        }
    </style>
</head>

<body>
    <div class="nav-container">
        <button class="burger" aria-label="Toggle menu">
            <span></span><span></span><span></span>
        </button>
        <nav>
            <img src="IMAGE/Main-Logo.png" alt="Loan System Logo" class="sidebar-logo">
            <a href="<?php echo htmlspecialchars($dashboard_link); ?>">
                <i class="fa-solid fa-table-columns"></i> DASHBOARD
            </a>
            <a href="applicant.php"><i class="fa-solid fa-users"></i> APPLICANTS</a>

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

            <a href="reports_record.php"><i class="fa-solid fa-scroll"></i> REPORTS RECORDS</a>
            <?php if ($adminRole === 'admin1' || $adminRole === 'superadmin'): ?>
                <a href="archived_records.php"><i class="fa-solid fa-archive"></i> ARCHIVED RECORDS</a>
                <a href="add_admin.php" class="<?php echo $current_page === 'add_admin.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-user-shield"></i> ADMIN MANAGEMENT
                </a>
                <a href="history_activity.php"><i class="fa-solid fa-clipboard"></i> AUDIT TRAILS</a>
                <a href="manage_credit_points.php" class="active"><i class="fa-solid fa-star"></i> MANAGE CREDIT RATE</a>

            <?php endif; ?>
        </nav>
    </div>

    <div class="header">
        <div class="profileXdate">
            <div id="datetime" class="datetime"></div>
            <a href="notifications_enhanced.php" class="notification-bell" title="View Notifications">
                <i class="fa-solid fa-bell"></i>
            </a>
            <div class="profile-container">
                <div onclick="toggleDropdown(event)">
                    <img src="uploads/<?php echo htmlspecialchars($profile_img); ?>" alt="Profile" class="profile"
                        onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22%231b5e20%22%3E%3Cpath d=%22M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z%22/%3E%3C/svg%3E';">
                </div>
                <div class="dropdown-menu" id="dropdown">
                    <ul>
                        <li>
                            <a href="<?php echo htmlspecialchars($profile_link); ?>">
                                <img src="uploads/<?php echo htmlspecialchars($profile_img); ?>" alt="Profile"
                                    class="profile-icon"
                                    onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22%231b5e20%22%3E%3Cpath d=%22M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z%22/%3E%3C/svg%3E';">
                                Profile
                            </a>
                        </li>
                        <li>
                            <a class="logout" href="index.php">
                                <i class="fa-solid fa-sign-out"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="main-content">
        <div id="messageContainer"></div>

        <div class="page-header">
            <h1><i class="fas fa-star"></i> Manage Credit Rate</h1>
            <p>Manage user credit scores and reward system</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Users</h3>
                <div class="value"><?php echo count($users); ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Points Distributed</h3>
                <div class="value"><?php echo number_format(array_sum(array_column($users, 'credit_points'))); ?></div>
            </div>
            <div class="stat-card">
                <h3>Average Score</h3>
                <div class="value">
                    <?php echo count($users) > 0 ? number_format(array_sum(array_column($users, 'credit_points')) / count($users), 0) : 0; ?>
                </div>
            </div>
            <div class="stat-card">
                <h3>Top Score</h3>
                <div class="value"><?php echo count($users) > 0 ? number_format($users[0]['credit_points']) : 0; ?>
                </div>
            </div>
        </div>

        <div class="users-table-container">
            <h2 style="margin: 0 0 20px 0;">User Credit Scores</h2>
            <table class="users-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Credit Points</th>
                        <th>Last Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <img src="<?php echo htmlspecialchars($user['profile_image'] ?: 'assets/default.png'); ?>"
                                        alt="Avatar" class="user-avatar">
                                    <span><?php echo htmlspecialchars($user['full_name']); ?></span>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><span class="points-badge"><?php echo number_format($user['credit_points']); ?> pts</span>
                            </td>
                            <td><?php echo $user['credit_points_updated_at'] ? date('M d, Y', strtotime($user['credit_points_updated_at'])) : 'Never'; ?>
                            </td>
                            <td>
                                <button class="action-btn manage-btn" data-user-id="<?php echo intval($user['id']); ?>"
                                    data-user-name="<?php echo htmlspecialchars($user['full_name'], ENT_QUOTES); ?>"
                                    data-user-points="<?php echo intval($user['credit_points']); ?>">
                                    <i class="fas fa-cog"></i> Manage
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Credit Rating Matrix Section -->
        <div class="users-table-container" style="margin-top: 30px;">
            <h2 style="margin: 0 0 20px 0;"><i class="fas fa-chart-bar"></i> Credit Rating Matrix</h2>
            <p style="color: #666; margin-bottom: 20px;">This matrix defines how credit points are awarded or deducted
                based on payment behavior and frequency.</p>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
                <!-- Payment Status & Points Table -->
                <div>
                    <h3 style="border-bottom: 3px solid #46a047; padding-bottom: 10px; margin: 0 0 15px 0;">Payment
                        Status & Points</h3>
                    <table class="users-table" style="font-size: 13px;">
                        <thead>
                            <tr style="background: #46a047; color: white;">
                                <th>Payment Status</th>
                                <th>Frequency</th>
                                <th>On-Time</th>
                                <th>Late</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td rowspan="4" style="font-weight: 600; color: #1b5e20;">On-Time</td>
                                <td>Monthly</td>
                                <td><span
                                        style="background: #4caf50; color: white; padding: 4px 8px; border-radius: 4px;">+5</span>
                                </td>
                                <td>0</td>
                            </tr>
                            <tr>
                                <td>Quarterly</td>
                                <td><span
                                        style="background: #4caf50; color: white; padding: 4px 8px; border-radius: 4px;">+12</span>
                                </td>
                                <td>0</td>
                            </tr>
                            <tr>
                                <td>Semi-Annual</td>
                                <td><span
                                        style="background: #4caf50; color: white; padding: 4px 8px; border-radius: 4px;">+20</span>
                                </td>
                                <td>0</td>
                            </tr>
                            <tr>
                                <td>Annually</td>
                                <td><span
                                        style="background: #4caf50; color: white; padding: 4px 8px; border-radius: 4px;">+40</span>
                                </td>
                                <td>0</td>
                            </tr>
                            <tr style="background: #fff3cd;">
                                <td rowspan="4" style="font-weight: 600; color: #d32f2f;">Late 1-7 days</td>
                                <td>Monthly</td>
                                <td>0</td>
                                <td><span
                                        style="background: #f44336; color: white; padding: 4px 8px; border-radius: 4px;">-5</span>
                                </td>
                            </tr>
                            <tr style="background: #fff3cd;">
                                <td>Quarterly</td>
                                <td>0</td>
                                <td><span
                                        style="background: #f44336; color: white; padding: 4px 8px; border-radius: 4px;">-12</span>
                                </td>
                            </tr>
                            <tr style="background: #fff3cd;">
                                <td>Semi-Annual</td>
                                <td>0</td>
                                <td><span
                                        style="background: #f44336; color: white; padding: 4px 8px; border-radius: 4px;">-20</span>
                                </td>
                            </tr>
                            <tr style="background: #fff3cd;">
                                <td>Annually</td>
                                <td>0</td>
                                <td><span
                                        style="background: #f44336; color: white; padding: 4px 8px; border-radius: 4px;">-40</span>
                                </td>
                            </tr>
                            <tr style="background: #ffebee;">
                                <td rowspan="4" style="font-weight: 600; color: #c62828;">Late 8-30 days</td>
                                <td>Monthly</td>
                                <td>0</td>
                                <td><span
                                        style="background: #d32f2f; color: white; padding: 4px 8px; border-radius: 4px;">-10</span>
                                </td>
                            </tr>
                            <tr style="background: #ffebee;">
                                <td>Quarterly</td>
                                <td>0</td>
                                <td><span
                                        style="background: #d32f2f; color: white; padding: 4px 8px; border-radius: 4px;">-25</span>
                                </td>
                            </tr>
                            <tr style="background: #ffebee;">
                                <td>Semi-Annual</td>
                                <td>0</td>
                                <td><span
                                        style="background: #d32f2f; color: white; padding: 4px 8px; border-radius: 4px;">-35</span>
                                </td>
                            </tr>
                            <tr style="background: #ffebee;">
                                <td>Annually</td>
                                <td>0</td>
                                <td><span
                                        style="background: #d32f2f; color: white; padding: 4px 8px; border-radius: 4px;">-70</span>
                                </td>
                            </tr>
                            <tr style="background: #f3e5f5;">
                                <td rowspan="4" style="font-weight: 600; color: #880e4f;">Late >30 days</td>
                                <td>Monthly</td>
                                <td>0</td>
                                <td><span
                                        style="background: #880e4f; color: white; padding: 4px 8px; border-radius: 4px;">-20</span>
                                </td>
                            </tr>
                            <tr style="background: #f3e5f5;">
                                <td>Quarterly</td>
                                <td>0</td>
                                <td><span
                                        style="background: #880e4f; color: white; padding: 4px 8px; border-radius: 4px;">-40</span>
                                </td>
                            </tr>
                            <tr style="background: #f3e5f5;">
                                <td>Semi-Annual</td>
                                <td>0</td>
                                <td><span
                                        style="background: #880e4f; color: white; padding: 4px 8px; border-radius: 4px;">-50</span>
                                </td>
                            </tr>
                            <tr style="background: #f3e5f5;">
                                <td>Annually</td>
                                <td>0</td>
                                <td><span
                                        style="background: #880e4f; color: white; padding: 4px 8px; border-radius: 4px;">-70</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Credit Score Ranges -->
                <div>
                    <h3 style="border-bottom: 3px solid #46a047; padding-bottom: 10px; margin: 0 0 15px 0;">Credit Score
                        Ranges</h3>
                    <table class="users-table" style="font-size: 13px;">
                        <thead>
                            <tr style="background: #46a047; color: white;">
                                <th>Score Range</th>
                                <th>Rating</th>
                                <th>Meaning</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr style="background: #e8f5e9;">
                                <td><strong>90-100</strong></td>
                                <td><span
                                        style="background: #4caf50; color: white; padding: 4px 8px; border-radius: 4px; display: inline-block;">Excellent</span>
                                </td>
                                <td>Very reliable, low risk</td>
                            </tr>
                            <tr style="background: #f1f8e9;">
                                <td><strong>75-89</strong></td>
                                <td><span
                                        style="background: #8bc34a; color: white; padding: 4px 8px; border-radius: 4px; display: inline-block;">Good</span>
                                </td>
                                <td>Reliable, moderate risk</td>
                            </tr>
                            <tr style="background: #fffde7;">
                                <td><strong>50-74</strong></td>
                                <td><span
                                        style="background: #fbc02d; color: white; padding: 4px 8px; border-radius: 4px; display: inline-block;">Fair</span>
                                </td>
                                <td>Some risk, needs monitoring</td>
                            </tr>
                            <tr style="background: #ffe0b2;">
                                <td><strong>25-49</strong></td>
                                <td><span
                                        style="background: #ff9800; color: white; padding: 4px 8px; border-radius: 4px; display: inline-block;">Poor</span>
                                </td>
                                <td>High risk, needs improvement</td>
                            </tr>
                            <tr style="background: #ffcdd2;">
                                <td><strong>1-24</strong></td>
                                <td><span
                                        style="background: #f44336; color: white; padding: 4px 8px; border-radius: 4px; display: inline-block;">Very
                                        Poor</span></td>
                                <td>Very high risk, likely default</td>
                            </tr>
                        </tbody>
                    </table>

                    <div
                        style="background: #e3f2fd; padding: 15px; border-radius: 8px; margin-top: 15px; border-left: 4px solid #46a047;">
                        <h4 style="margin: 0 0 10px 0;">Formula</h4>
                        <p
                            style="margin: 0; font-family: monospace; background: white; padding: 10px; border-radius: 4px;">
                            Credit Score = Previous Score + Points Added - Points Deducted
                        </p>
                        <small style="display: block; margin-top: 10px; color: #555;">
                            <strong>Note:</strong> Maximum score = 100, Minimum score = 0
                        </small>
                    </div>
                </div>
            </div>

            <!-- Key Guidelines -->
            <div style="background: #f5f5f5; padding: 20px; border-radius: 8px;">
                <h3 style="margin: 0 0 15px 0;"><i class="fas fa-lightbulb"></i> Key Guidelines</h3>
                <ul style="margin: 0; padding-left: 20px; line-height: 1.8;">
                    <li><strong>More Frequent Payments:</strong> Monthly payments result in smaller point adjustments,
                        promoting consistency and steady improvement.</li>
                    <li><strong>Less Frequent Payments:</strong> Quarterly, semi-annual, and annual payments have larger
                        adjustments, reflecting higher financial risk.</li>
                    <li><strong>Immediate Application:</strong> All adjustments are applied immediately after each
                        payment due date.</li>
                    <li><strong>Behavioral Incentive:</strong> This system encourages applicants to maintain consistent
                        and timely payments to improve their credit standing.</li>
                    <li><strong>Transparent Evaluation:</strong> The matrix provides a quantitative and transparent
                        basis for credit scoring.</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Manage Points Modal -->
    <div id="manageModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Manage Credit Points</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div id="modalUserInfo" style="margin-bottom: 20px;"></div>

            <div class="form-group">
                <label>Set Exact Points</label>
                <input type="number" id="exactPoints" placeholder="Enter exact points value">
            </div>

            <div class="form-group">
                <label>Or Add/Deduct Points</label>
                <div style="display: flex; gap: 10px;">
                    <input type="number" id="pointsChange" placeholder="Enter points" style="flex: 1;">
                    <button class="action-btn" onclick="addPoints()">
                        <i class="fas fa-plus"></i> Add
                    </button>
                    <button class="action-btn" style="background: #d32f2f;" onclick="deductPoints()">
                        <i class="fas fa-minus"></i> Deduct
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label>Reason (Required)</label>
                <textarea id="reason" rows="3" placeholder="Enter reason for points change"></textarea>
            </div>

            <div class="button-group">
                <button class="btn-secondary" onclick="closeModal()">Cancel</button>
                <button class="btn-primary" onclick="setExactPoints()">Set Exact Points</button>
            </div>

            <hr style="margin: 30px 0;">

            <h3>Point History</h3>
            <div id="historyContainer">Loading...</div>
        </div>
    </div>

    <script src="JAVASCRIPT/Real-Time.js"></script>
    <script>
        let currentUserId = null;

        // Add event listeners to manage buttons
        document.addEventListener('DOMContentLoaded', function () {
            const manageButtons = document.querySelectorAll('.manage-btn');
            manageButtons.forEach(button => {
                button.addEventListener('click', function () {
                    const userId = parseInt(this.getAttribute('data-user-id'));
                    const userName = this.getAttribute('data-user-name');
                    const userPoints = parseInt(this.getAttribute('data-user-points'));
                    openManageModal(userId, userName, userPoints);
                });
            });
        });

        function openManageModal(userId, userName, currentPoints) {
            currentUserId = userId;
            document.getElementById('modalUserInfo').innerHTML = `
                <strong>${userName}</strong><br>
                Current Points: <span class="points-badge">${currentPoints.toLocaleString()} pts</span>
            `;
            document.getElementById('exactPoints').value = currentPoints;
            document.getElementById('pointsChange').value = '';
            document.getElementById('reason').value = '';
            document.getElementById('manageModal').style.display = 'block';
            loadHistory(userId);
        }

        function closeModal() {
            document.getElementById('manageModal').style.display = 'none';
        }

        function showMessage(message, type) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${type}`;
            messageDiv.innerHTML = `<i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}-circle"></i>${message}`;
            document.getElementById('messageContainer').appendChild(messageDiv);
            setTimeout(() => messageDiv.remove(), 5000);
        }

        function setExactPoints() {
            const points = parseInt(document.getElementById('exactPoints').value);
            const reason = document.getElementById('reason').value.trim();

            if (!reason) {
                alert('Please provide a reason');
                return;
            }

            fetch('manage_credit_points.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=update_points&user_id=${currentUserId}&points=${points}&reason=${encodeURIComponent(reason)}`
            })
                .then(r => r.json())
                .then(data => {
                    showMessage(data.message, data.success ? 'success' : 'error');
                    if (data.success) {
                        setTimeout(() => location.reload(), 1500);
                    }
                });
        }

        function addPoints() {
            const points = parseInt(document.getElementById('pointsChange').value);
            const reason = document.getElementById('reason').value.trim();

            if (!points || points <= 0) {
                alert('Please enter valid points');
                return;
            }

            if (!reason) {
                alert('Please provide a reason');
                return;
            }

            fetch('manage_credit_points.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=add_points&user_id=${currentUserId}&points=${points}&reason=${encodeURIComponent(reason)}`
            })
                .then(r => r.json())
                .then(data => {
                    showMessage(data.message, data.success ? 'success' : 'error');
                    if (data.success) {
                        setTimeout(() => location.reload(), 1500);
                    }
                });
        }

        function deductPoints() {
            const points = parseInt(document.getElementById('pointsChange').value);
            const reason = document.getElementById('reason').value.trim();

            if (!points || points <= 0) {
                alert('Please enter valid points');
                return;
            }

            if (!reason) {
                alert('Please provide a reason');
                return;
            }

            fetch('manage_credit_points.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=deduct_points&user_id=${currentUserId}&points=${points}&reason=${encodeURIComponent(reason)}`
            })
                .then(r => r.json())
                .then(data => {
                    showMessage(data.message, data.success ? 'success' : 'error');
                    if (data.success) {
                        setTimeout(() => location.reload(), 1500);
                    }
                });
        }

        function loadHistory(userId) {
            fetch('manage_credit_points.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=get_user_history&user_id=${userId}`
            })
                .then(r => r.json())
                .then(data => {
                    const container = document.getElementById('historyContainer');
                    if (data.history.length === 0) {
                        container.innerHTML = '<p>No history found</p>';
                        return;
                    }

                    container.innerHTML = data.history.map(h => `
                    <div class="history-item ${h.points_change > 0 ? 'positive' : 'negative'}">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <strong>${h.points_change > 0 ? '+' : ''}${h.points_change} points</strong>
                            <span>${new Date(h.created_at).toLocaleDateString()}</span>
                        </div>
                        <div>${h.reason}</div>
                        ${h.admin1_name || h.admin2_name || h.superadmin_name ?
                            `<div style="font-size: 12px; color: #666; margin-top: 5px;">By: ${h.admin1_name || h.admin2_name || h.superadmin_name}</div>`
                            : ''}
                    </div>
                `).join('');
                });
        }

        function toggleDropdown(event) {
            event.stopPropagation();
            document.getElementById("dropdown").classList.toggle("show");
        }

        document.querySelector('.burger').addEventListener('click', function () {
            this.classList.toggle('active');
            document.querySelector('nav').classList.toggle('active');
        });

        window.onclick = function (event) {
            if (!event.target.matches('.profile')) {
                document.getElementById("dropdown").classList.remove("show");
            }
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        };

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

    </script>

</body>

</html>