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
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $points = isset($_POST['points']) ? intval($_POST['points']) : 0;
        $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

        if (empty($reason)) {
            echo json_encode(['success' => false, 'message' => 'Reason is required']);
            exit();
        }

        $success = $creditManager->setPoints($user_id, $points, $reason, $admin_id, $adminRole);
        echo json_encode(['success' => $success, 'message' => $success ? 'Credit points updated successfully!' : 'Failed to update credit points']);
        exit();
    }

    if ($_POST['action'] === 'add_points') {
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $points = isset($_POST['points']) ? intval($_POST['points']) : 0;
        $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

        if ($points <= 0) {
            echo json_encode(['success' => false, 'message' => 'Points must be greater than 0']);
            exit();
        }

        if (empty($reason)) {
            echo json_encode(['success' => false, 'message' => 'Reason is required']);
            exit();
        }

        $success = $creditManager->addPoints($user_id, $points, $reason, null, $admin_id, $adminRole);
        echo json_encode(['success' => $success, 'message' => $success ? "Added $points points successfully!" : 'Failed to add points']);
        exit();
    }

    if ($_POST['action'] === 'deduct_points') {
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $points = isset($_POST['points']) ? intval($_POST['points']) : 0;
        $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

        if ($points <= 0) {
            echo json_encode(['success' => false, 'message' => 'Points must be greater than 0']);
            exit();
        }

        if (empty($reason)) {
            echo json_encode(['success' => false, 'message' => 'Reason is required']);
            exit();
        }

        $success = $creditManager->deductPoints($user_id, $points, $reason, null, $admin_id, $adminRole);
        echo json_encode(['success' => $success, 'message' => $success ? "Deducted $points points successfully!" : 'Failed to deduct points']);
        exit();
    }

    if ($_POST['action'] === 'get_user_history') {
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
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
    <title>Manage Credit Points - CYCLOAN</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            box-sizing: border-box;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-primary {
            flex: 1;
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
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
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            background: #f5f5f5;
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

        .points-input-group {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }

        .points-input-group input {
            flex: 1;
        }

        .points-input-group button {
            padding: 12px 20px;
            white-space: nowrap;
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
            <a href="active_records.php"><i class="fa-solid fa-user-check"></i> ACTIVE RECORDS</a>
            <a href="pending_records.php"><i class="fa-solid fa-spinner"></i> PENDING RECORDS</a>
            <a href="closed_records.php"><i class="fa-solid fa-circle-check"></i> CLOSED RECORDS</a>
            <a href="reports_record.php"><i class="fa-solid fa-scroll"></i> REPORTS RECORDS</a>
            <?php if ($adminRole === 'admin1' || $adminRole === 'superadmin'): ?>
                <a href="archived_records.php"><i class="fa-solid fa-archive"></i> ARCHIVED RECORDS</a>
                <a href="add_admin.php"><i class="fa-solid fa-user-plus"></i> ADD ADMIN</a>
                <a href="manage_credit_points_new.php" class="active"><i class="fa-solid fa-star"></i> CREDIT POINTS</a>
            <?php endif; ?>
            <a href="history_activity.php"><i class="fa-solid fa-clipboard"></i> HISTORY ACTIVITY</a>
        </nav>
    </div>

    <div class="header">
        <div class="profileXdate">
            <div id="datetime" class="datetime"></div>
            <div class="profile-container">
                <div onclick="toggleDropdown(event)">
                    <img src="uploads/<?php echo htmlspecialchars($profile_img); ?>" alt="Profile" class="profile">
                </div>
                <div class="dropdown-menu" id="dropdown">
                    <ul>
                        <li>
                            <a href="<?php echo htmlspecialchars($profile_link); ?>">
                                <img src="uploads/<?php echo htmlspecialchars($profile_img); ?>" alt="Profile"
                                    class="profile-icon">
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
            <h1><i class="fas fa-star"></i> Credit Points Management</h1>
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
                                <button class="action-btn" type="button"
                                    onclick="openManageModal(<?php echo json_encode($user); ?>)">
                                    <i class="fas fa-cog"></i> Manage
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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

            <!-- Set Exact Points Section -->
            <div class="form-group">
                <label>Set Exact Points</label>
                <input type="number" id="exactPoints" placeholder="Enter exact points value">
                <button class="btn-primary" onclick="setExactPoints()" style="margin-top: 10px; width: 100%;">Set Exact
                    Points</button>
            </div>

            <hr style="margin: 30px 0;">

            <!-- Add/Deduct Points Section -->
            <div class="form-group">
                <label>Add or Deduct Points</label>
                <div class="points-input-group">
                    <input type="number" id="pointsChange" placeholder="Enter points amount">
                    <button class="action-btn" onclick="addPoints()" title="Add Points">
                        <i class="fas fa-plus"></i> Add
                    </button>
                    <button class="action-btn" style="background: #d32f2f;" onclick="deductPoints()"
                        title="Deduct Points">
                        <i class="fas fa-minus"></i> Deduct
                    </button>
                </div>
            </div>

            <!-- Reason Field -->
            <div class="form-group">
                <label>Reason (Required)</label>
                <textarea id="reason" rows="3" placeholder="Explain why you are changing the points"></textarea>
            </div>

            <div class="button-group">
                <button class="btn-secondary" onclick="closeModal()">Cancel</button>
            </div>

            <hr style="margin: 30px 0;">

            <!-- History Section -->
            <h3>Point History</h3>
            <div id="historyContainer" style="max-height: 300px; overflow-y: auto;">Loading...</div>
        </div>
    </div>

    <script src="JAVASCRIPT/Real-Time.js"></script>
    <script>
        let currentUserId = null;

        function openManageModal(userObject) {
            try {
                // Parse user object
                const userId = userObject.id;
                const userName = userObject.full_name;
                const currentPoints = userObject.credit_points;

                currentUserId = userId;

                // Update modal header
                document.getElementById('modalUserInfo').innerHTML = `
                    <strong>${escapeHtml(userName)}</strong><br>
                    Current Points: <span class="points-badge">${parseInt(currentPoints).toLocaleString()} pts</span>
                `;

                // Reset form fields
                document.getElementById('exactPoints').value = currentPoints;
                document.getElementById('pointsChange').value = '';
                document.getElementById('reason').value = '';

                // Show modal
                document.getElementById('manageModal').style.display = 'block';

                // Load history
                loadHistory(userId);

                console.log('Modal opened for user:', userId);
            } catch (error) {
                console.error('Error opening modal:', error);
                alert('Error opening modal. Please try again.');
            }
        }

        function closeModal() {
            document.getElementById('manageModal').style.display = 'none';
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function showMessage(message, type) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${type}`;
            const iconType = type === 'success' ? 'check-circle' : 'exclamation-circle';
            messageDiv.innerHTML = `<i class="fas fa-${iconType}"></i><span>${escapeHtml(message)}</span>`;
            document.getElementById('messageContainer').appendChild(messageDiv);
            setTimeout(() => messageDiv.remove(), 5000);
        }

        function setExactPoints() {
            const points = document.getElementById('exactPoints').value.trim();
            const reason = document.getElementById('reason').value.trim();

            if (!points) {
                alert('Please enter a points value');
                return;
            }

            if (!reason) {
                alert('Please provide a reason');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'update_points');
            formData.append('user_id', currentUserId);
            formData.append('points', points);
            formData.append('reason', reason);

            fetch('manage_credit_points_new.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showMessage(data.message, 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showMessage(data.message || 'Failed to update points', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showMessage('An error occurred. Please try again.', 'error');
                });
        }

        function addPoints() {
            const points = document.getElementById('pointsChange').value.trim();
            const reason = document.getElementById('reason').value.trim();

            if (!points || isNaN(points) || parseInt(points) <= 0) {
                alert('Please enter a valid points amount (greater than 0)');
                return;
            }

            if (!reason) {
                alert('Please provide a reason');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'add_points');
            formData.append('user_id', currentUserId);
            formData.append('points', points);
            formData.append('reason', reason);

            fetch('manage_credit_points_new.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showMessage(data.message, 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showMessage(data.message || 'Failed to add points', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showMessage('An error occurred. Please try again.', 'error');
                });
        }

        function deductPoints() {
            const points = document.getElementById('pointsChange').value.trim();
            const reason = document.getElementById('reason').value.trim();

            if (!points || isNaN(points) || parseInt(points) <= 0) {
                alert('Please enter a valid points amount (greater than 0)');
                return;
            }

            if (!reason) {
                alert('Please provide a reason');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'deduct_points');
            formData.append('user_id', currentUserId);
            formData.append('points', points);
            formData.append('reason', reason);

            fetch('manage_credit_points_new.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showMessage(data.message, 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showMessage(data.message || 'Failed to deduct points', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showMessage('An error occurred. Please try again.', 'error');
                });
        }

        function loadHistory(userId) {
            const formData = new FormData();
            formData.append('action', 'get_user_history');
            formData.append('user_id', userId);

            fetch('manage_credit_points_new.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('historyContainer');

                    if (!data.success || !data.history || data.history.length === 0) {
                        container.innerHTML = '<p style="text-align: center; color: #999;">No history found</p>';
                        return;
                    }

                    container.innerHTML = data.history.map(h => {
                        const isPositive = h.points_change > 0;
                        const adminName = h.admin1_name || h.admin2_name || h.superadmin_name || 'System';
                        return `
                            <div class="history-item ${isPositive ? 'positive' : 'negative'}">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                                    <strong>${isPositive ? '+' : ''}${h.points_change} points</strong>
                                    <span style="font-size: 12px; color: #666;">${new Date(h.created_at).toLocaleDateString()}</span>
                                </div>
                                <div style="margin-bottom: 5px;">${escapeHtml(h.reason)}</div>
                                <div style="font-size: 12px; color: #666;">By: ${escapeHtml(adminName)}</div>
                            </div>
                        `;
                    }).join('');
                })
                .catch(error => {
                    console.error('Error loading history:', error);
                    document.getElementById('historyContainer').innerHTML = '<p style="color: red;">Error loading history</p>';
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
            if (event.target.id === 'manageModal') {
                event.target.style.display = 'none';
            }
        };
    </script>
</body>

</html>