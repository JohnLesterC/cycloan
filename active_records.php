<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require "CYCLOAN_db.php";

$current_page = basename($_SERVER['PHP_SELF']);

// Check if the user is logged in
if (!isset($_SESSION['email'])) {
    header("Location: index.php");
    exit();
}

// Determine admin role and dashboard link
if (!isset($_SESSION['role'])) {
    $stmt = $conn->prepare("
        SELECT 'superadmin' AS role FROM superadmins WHERE email = ? 
        UNION 
        SELECT 'admin1' AS role FROM admin1 WHERE email = ? 
        UNION 
        SELECT 'admin2' AS role FROM admin2 WHERE email = ?
    ");
    $stmt->bind_param("sss", $_SESSION['email'], $_SESSION['email'], $_SESSION['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $_SESSION['role'] = $row ? $row['role'] : 'admin2';
    $stmt->close();
}

$adminRole = $_SESSION['role'];
$dashboard_link = $adminRole === 'superadmin' ? 'Superadmin_dashboard.php' : ($adminRole === 'admin1' ? 'admin1_dashboard.php' : 'admin2_dashboard.php');
$profile_link = $adminRole === 'superadmin' ? 'profileSuperadmin.php' : ($adminRole === 'admin1' ? 'profileAdmin1.php' : 'profileAdmin2.php');

// Ensure $conn is a MySQLi instance
if (!($conn instanceof mysqli)) {
    $_SESSION['error'] = 'Database connection failed';
    error_log("Database connection is not a MySQLi instance");
    header('Location: ' . $dashboard_link);
    exit;
}

// Check if connection is successful
if (mysqli_connect_errno()) {
    $_SESSION['error'] = 'Failed to connect to database: ' . mysqli_connect_error();
    error_log("Database connection error: " . mysqli_connect_error());
    header('Location: ' . $dashboard_link);
    exit;
}

// Fetch profile image
$email = $_SESSION['email'];
$valid_roles = ['superadmin' => 'superadmins', 'admin1' => 'admin1', 'admin2' => 'admin2'];
$table_name = $valid_roles[$adminRole] ?? 'admin2';
$stmt = $conn->prepare("SELECT profile_img FROM $table_name WHERE email = ?");
if (!$stmt) {
    error_log("Profile image query preparation failed for table $table_name: " . $conn->error, 3, 'errors.log');
    $_SESSION['error'] = "Database error: Unable to fetch profile image.";
    $profile_img = 'default.png';
} else {
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    $upload_dir = __DIR__ . '/uploads/';
    $default_img = 'default.png';
    $profile_img = !empty($user['profile_img']) && file_exists($upload_dir . $user['profile_img'])
        ? $user['profile_img']
        : $default_img;

    if (!file_exists($upload_dir . $profile_img)) {
        error_log("Profile image not found: $upload_dir$profile_img", 3, 'errors.log');
        $profile_img = $default_img;
    }
}

// Handle AJAX request for interest rate
if (isset($_GET['action']) && $_GET['action'] === 'get_current_interest_rate') {
    try {
        $stmt = $conn->prepare("SELECT interest_rate FROM interest_rates WHERE term_length = '12' ORDER BY updated_at DESC LIMIT 1");
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $interest_rate_row = $result->fetch_assoc();
        $current_rate = $interest_rate_row ? $interest_rate_row['interest_rate'] : 6.00;
        $stmt->close();
        $response = ['success' => true, 'interest_rate' => $current_rate];
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Fetch active loan applications
$query = "
    SELECT 
        la.application_id, 
        u.first_name, 
        u.last_name, 
        u.contact, 
        lt.type_name, 
        COALESCE(l.total_principal, 0) AS total_principal,
        COALESCE(l.total_interest, 0) AS total_interest,
        COALESCE(l.total_principal + l.total_interest, la.final_loan_amount, 0) AS final_amount,
        COALESCE(l.total_paid, 0) AS total_paid,
        CASE 
            WHEN l.loan_id IS NULL THEN 0
            ELSE GREATEST(0, COALESCE(l.total_principal + l.total_interest, 0) - COALESCE(l.total_paid, 0))
        END AS remaining_balance,
        CASE 
            WHEN l.loan_id IS NULL THEN 'Awaiting Setup'
            WHEN COALESCE(l.total_paid, 0) = 0 THEN 'Active'
            WHEN COALESCE(l.total_paid, 0) >= COALESCE(l.total_principal + l.total_interest, 0) THEN 'Paid'
            WHEN COALESCE(l.total_paid, 0) > 0 THEN 'Partial'
            ELSE 'Active'
        END AS status,
        COALESCE(MIN(ps.due_date), DATE_ADD(la.created_at, INTERVAL 30 DAY)) AS next_due_date,
        COALESCE(COUNT(DISTINCT ps.payment_id), 0) AS payment_count,
        l.created_at AS disbursement_date,
        l.loan_id,
        la.created_at
    FROM loan_applications la
    JOIN users1 u ON la.user_id = u.id
    JOIN loan_types lt ON la.loan_type_id = lt.loan_type_id
    LEFT JOIN loans l ON la.application_id = l.application_id
    LEFT JOIN payment_schedules ps ON l.loan_id = ps.loan_id
    WHERE la.status = 'Active'
    GROUP BY la.application_id
    ORDER BY la.created_at DESC
";

$result = mysqli_query($conn, $query);

if (!$result) {
    $_SESSION['error'] = 'Query failed: ' . mysqli_error($conn);
    error_log("Query failed: " . mysqli_error($conn));
    header('Location: ' . $dashboard_link);
    exit;
}

$activeApplications = [];
while ($row = mysqli_fetch_assoc($result)) {
    $activeApplications[] = $row;
}
mysqli_free_result($result);

// Calculate statistics for dashboard
$totalActive = count($activeApplications);
$totalAmount = 0;
$totalPaid = 0;
$totalRemaining = 0;
$fullyPaid = 0;
$partiallyPaid = 0;
$noPaid = 0;
$awaitingSetup = 0;
$totalPayments = 0;

foreach ($activeApplications as $app) {
    $totalAmount += $app['final_amount'];
    $totalPaid += $app['total_paid'];
    $totalRemaining += $app['remaining_balance'];
    $totalPayments += $app['payment_count'];

    switch (strtolower($app['status'])) {
        case 'paid':
            $fullyPaid++;
            break;
        case 'partial':
            $partiallyPaid++;
            break;
        case 'awaiting setup':
            $awaitingSetup++;
            break;
        case 'active':
            $noPaid++;
            break;
    }
}

$paymentPercentage = $totalAmount > 0 ? round(($totalPaid / $totalAmount) * 100, 2) : 0;

// Fetch current interest rate (default term_length = 12 months)
$interest_rate_query = "SELECT interest_rate FROM interest_rates WHERE term_length = '12' ORDER BY updated_at DESC LIMIT 1";
$interest_rate_result = mysqli_query($conn, $interest_rate_query);
$default_interest_rate = ($interest_rate_result && mysqli_num_rows($interest_rate_result) > 0)
    ? mysqli_fetch_assoc($interest_rate_result)['interest_rate']
    : 6.00;
mysqli_free_result($interest_rate_result);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Active Records - CYCLOAN</title>
    <link rel="stylesheet" href="CSS/admin_dashboard.css">
    <link rel="stylesheet" href="CSS/admin_profile.css">
    <link rel="stylesheet" href="CSS/active_records.css">
    <link rel="stylesheet" href="CSS/nav_active.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <style>
        /* Enhanced Stat Card Styles */
        .summary-statistics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #4caf50;
            transition: all 0.3s ease;
            margin-bottom: 60px;
        }

        .stat-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }

        .stat-card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
            color: #666;
            font-size: 14px;
            font-weight: 500;
        }

        .stat-card-header i {
            font-size: 18px;
            color: #4caf50;
        }

        .stat-card-value {
            font-size: 28px;
            font-weight: 700;
            color: #333;
            margin-bottom: 8px;
        }

        .stat-card-footer {
            font-size: 12px;
            color: #999;
        }

        .progress-bar {
            height: 6px;
            background: #e0e0e0;
            border-radius: 3px;
            margin-top: 5px;
            overflow: hidden;
        }

        .stat-card:nth-child(2) {
            border-left-color: #4caf50;
        }

        .stat-card:nth-child(2) .stat-card-header i {
            color: #4caf50;
        }

        .stat-card:nth-child(3) {
            border-left-color: #fbc02d;
        }

        .stat-card:nth-child(3) .stat-card-header i {
            color: #fbc02d;
        }

        .stat-card:nth-child(4) {
            border-left-color: #fbc02d;
        }

        .stat-card:nth-child(4) .stat-card-header i {
            color: #fbc02d;
        }

        .payment-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .payment-status-badge.no-payment {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffc107;
        }

        .payment-status-badge.partial-payment {
            background: #e7f3ff;
            color: #004085;
            border: 1px solid #b8daff;
        }

        .payment-status-badge.fully-paid {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .stat-item.info {
            border-left-color: #2196f3;
        }

        .stat-item.warning {
            border-left-color: #ff9800;
        }

        .payment-schedule-table th,
        .payment-schedule-table td {
            padding: 10px;
            text-align: left;
        }

        .payment-modal .form-group input[type="text"] {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        .payment-schedule-table th:nth-child(7),
        .payment-schedule-table td:nth-child(7) {
            min-width: 120px;
            /* Invoice Number column */
            text-align: left;
        }

        .payment-schedule-table th:nth-child(8),
        .payment-schedule-table td:nth-child(8) {
            min-width: 150px;
            /* Processed By column */
            text-align: left;
        }

        .form-group input#invoiceNumber {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .payment-schedule-table th:last-child,
        .payment-schedule-table td:last-child {
            min-width: 150px;
            text-align: left;
        }

        .error-message.show {
            display: block;
            color: #dc2626;
            font-size: 12px;
            margin-top: 5px;
        }

        /* ========== ENHANCED REAL-TIME VALIDATION STYLES ========== */
        .form-group input.valid,
        .form-group select.valid,
        .form-group textarea.valid {
            border: 2px solid #4caf50 !important;
            background: linear-gradient(to right, transparent 0%, rgba(76, 175, 80, 0.05) 100%);
        }

        .form-group input.invalid,
        .form-group select.invalid,
        .form-group textarea.invalid {
            border: 2px solid #f44336 !important;
            background: linear-gradient(to right, transparent 0%, rgba(244, 67, 54, 0.05) 100%);
        }

        .form-group input.valid::placeholder,
        .form-group textarea.valid::placeholder {
            color: #4caf50;
        }

        .form-group input.invalid::placeholder,
        .form-group textarea.invalid::placeholder {
            color: #f44336;
        }

        #loanAmountError,
        #durationError,
        #frequencyError {
            font-size: 13px;
            margin-top: 6px;
            padding: 6px 10px;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        #loanAmountError.show,
        #durationError.show,
        #frequencyError.show {
            display: block;
            animation: slideDown 0.3s ease-out;
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

        /* Success feedback styling */
        .error-message:not(.show) {
            color: #4caf50;
            font-weight: 500;
        }

        /* Smooth transitions for form elements */
        .form-group input,
        .form-group select,
        .form-group textarea {
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        /* Loading state for submit button */
        .submit-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .spinner {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .modal-content {
            max-height: 80vh;
            overflow-y: auto;
        }

        .status-badge.created {
            background-color: #28a745;
            color: white;
        }

        .status-badge.pending {
            background-color: #ffc107;
            color: black;
        }

        .status-badge.paid {
            background-color: #17a2b8;
            color: white;
        }

        .status-badge.partial {
            background-color: #007bff;
            color: white;
        }

        .status-badge.active {
            background-color: #6c757d;
            color: white;
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
    </style>
</head>

<body>
    <div class="nav-container">
        <button class="burger" aria-label="Toggle menu">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <nav>
            <img src="IMAGE/Main-Logo.png" alt="Loan System Logo" class="sidebar-logo">
            <a href="<?php echo htmlspecialchars($dashboard_link); ?>"
                class="<?php echo $current_page === $dashboard_link ? 'active' : ''; ?>">
                <i class="fa-solid fa-table-columns"></i> DASHBOARD
            </a>
            <a href="applicant.php" class="<?php echo $current_page === 'applicant.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-users"></i> APPLICANTS
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
                <i class="fa-solid fa-scroll"></i> REPORTS RECORDS
            </a>
            <?php if ($adminRole === 'admin1' || $adminRole === 'superadmin'): ?>
                <a href="archived_records.php"
                    class="<?php echo $current_page === 'archived_records.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-archive"></i> ARCHIVED RECORDS
                </a>
                <a href="add_admin.php" class="<?php echo $current_page === 'add_admin.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-user-shield"></i> ADMIN MANAGEMENT
                </a>
            <?php endif; ?>
            <a href="history_activity.php"
                class="<?php echo $current_page === 'history_activity.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-clipboard"></i> AUDIT TRAILS
            </a>
            <?php if ($adminRole === 'admin1' || $adminRole === 'superadmin'): ?>
                <a href="manage_credit_points.php"
                    class="<?php echo $current_page === 'manage_credit_points.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-star"></i> MANAGE CREDIT RATE
                </a>
            <?php endif; ?>
            <a href="#" onclick="openManageInterestRateModal()" class="manage-interest-rate-link">
                <i class="fa-solid fa-percent"></i> VIEW INTEREST RATES
            </a>
        </nav>
    </div>

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
                        onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22%231b5e20%22%3E%3Cpath d=%22M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z%22/%3E%3C/svg%3E';">
                </div>
                <div class="dropdown-menu" id="dropdown" role="menu">
                    <ul>
                        <li role="none">
                            <a href="<?php echo htmlspecialchars($profile_link); ?>" role="menuitem" tabindex="-1">
                                <img src="uploads/<?php echo htmlspecialchars($profile_img); ?>" alt="Profile Image"
                                    class="profile"
                                    onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22%231b5e20%22%3E%3Cpath d=%22M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z%22/%3E%3C/svg%3E';">
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

    <div class="main-content">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="message success"><i
                    class="fas fa-check-circle"></i><?php echo htmlspecialchars($_SESSION['success']); ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message error"><i
                    class="fas fa-exclamation-circle"></i><?php echo htmlspecialchars($_SESSION['error']); ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>



        <!-- Enhanced Summary Statistics Section -->
        <div class="summary-statistics"
            style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 25px;">
            <div class="stat-card">
                <div class="stat-card-header">
                    <i class="fas fa-briefcase"></i>
                    <span>Active Loans</span>
                </div>
                <div class="stat-card-value"><?php echo $totalActive; ?></div>
                <div class="stat-card-footer">Total active loan applications</div>
            </div>

            <div class="stat-card">
                <div class="stat-card-header">
                    <i class="fas fa-coins"></i>
                    <span>Total Loan Amount</span>
                </div>
                <div class="stat-card-value">₱<?php echo number_format($totalAmount, 2); ?></div>
                <div class="stat-card-footer">Across all active loans</div>
            </div>

            <div class="stat-card">
                <div class="stat-card-header">
                    <i class="fas fa-chart-pie"></i>
                    <span>Total Paid</span>
                </div>
                <div class="stat-card-value">₱<?php echo number_format($totalPaid, 2); ?></div>
                <div class="stat-card-footer">
                    <div class="progress-bar"
                        style="height: 6px; background: #e0e0e0; border-radius: 3px; margin-top: 5px; overflow: hidden;">
                        <div
                            style="height: 100%; background: linear-gradient(90deg, #4caf50, #81c784); width: <?php echo $paymentPercentage; ?>%; transition: width 0.3s ease;">
                        </div>
                    </div>
                    <span
                        style="font-size: 11px; color: #666; margin-top: 5px; display: block;"><?php echo $paymentPercentage; ?>%
                        of total</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-card-header">
                    <i class="fas fa-hourglass-end"></i>
                    <span>Remaining Balance</span>
                </div>
                <div class="stat-card-value">₱<?php echo number_format($totalRemaining, 2); ?></div>
                <div class="stat-card-footer">Amount yet to be collected</div>
            </div>

            <div class="stat-card">
                <div class="stat-card-header">
                    <i class="fas fa-receipt"></i>
                    <span>Total Payment Schedules</span>
                </div>
                <div class="stat-card-value"><?php echo $totalPayments; ?></div>
                <div class="stat-card-footer">Scheduled across all loans</div>
            </div>
        </div>

        <div class="active-loans">
            <?php if (empty($activeApplications)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-folder-open"></i>
                    </div>
                    <h3>No Active Loan Applications</h3>
                    <p>There are currently no active loan applications to display.</p>
                </div>
            <?php else: ?>
                <div class="loans-card">
                    <div class="table-header">
                        <div class="page-controls">

                            <div class="filterers">
                                <div class="search-box">
                                    <i class="fas fa-search"></i>
                                    <input type="text" id="searchInput" placeholder="Search by applicant name or ID..."
                                        aria-label="Search active loans">
                                </div>

                                <!-- Status Filter -->
                                <div class="filter-group">
                                    <select id="statusFilter" onchange="filterTable()">
                                        <option value="">All Status</option>
                                        <option value="active">Active - No Payment</option>
                                        <option value="partial">Partial Payment</option>
                                        <option value="paid">Fully Paid</option>
                                        <option value="awaiting">Awaiting Setup</option>
                                    </select>
                                </div>

                                <!-- Loan Type Filter -->
                                <div class="filter-group">
                                    <select id="typeFilter" onchange="filterTable()">
                                        <option value="">All Types</option>
                                        <option value="individual">Individual</option>
                                        <option value="cooperative">Cooperative</option>
                                    </select>
                                </div>

                                <!-- Amount Range Filter -->
                                <div class="filter-group">
                                    <input type="number" id="minAmount" placeholder="Min Amount" onchange="filterTable()">
                                </div>

                                <div class="filter-group">
                                    <input type="number" id="maxAmount" placeholder="Max Amount" onchange="filterTable()">
                                </div>

                                <!-- Payment Status Filter -->
                                <div class="filter-group">
                                    <select id="paymentStatusFilter" onchange="filterTable()">
                                        <option value="">All Payment Status</option>
                                        <option value="no-payment">No Payment</option>
                                        <option value="partial-payment">Partial Payment</option>
                                        <option value="fully-paid">Fully Paid</option>
                                    </select>
                                </div>
                            </div>

                            <div class="buttons">
                                <!-- Export Button -->
                                <button class="export-btn" onclick="exportTableToCSV()">
                                    <i class="fa-solid fa-file-csv"></i>
                                    <span>Export CSV</span>
                                </button>

                                <!-- Refresh Button -->
                                <button class="refresh-btn" onclick="refreshTable()">
                                    <i class="fas fa-sync-alt" id="refreshIcon"></i>
                                    <span>Refresh</span>
                                </button>

                                <!-- Clear Filters Button -->
                                <button class="clear-filters-btn" onclick="clearAllFilters()">
                                    <i class="fas fa-times"></i>
                                    <span>Clear All</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="table-wrapper">
                        <table class="loan-table" id="activeLoansTable">
                            <thead>
                                <tr>
                                    <th onclick="sortTable('name')" style="cursor: pointer;">
                                        <div style="display: flex; align-items: center; gap: 6px;">
                                            Applicant Name
                                            <i class="fas fa-sort sort-icon"></i>
                                        </div>
                                    </th>
                                    <th onclick="sortTable('type')" style="cursor: pointer;">
                                        <div style="display: flex; align-items: center; gap: 6px;">
                                            Loan Type
                                            <i class="fas fa-sort sort-icon"></i>
                                        </div>
                                    </th>
                                    <th onclick="sortTable('amount')" style="cursor: pointer;" data-sortable>
                                        <div style="display: flex; align-items: center; gap: 6px;">
                                            Final Amount
                                            <i class="fas fa-sort sort-icon"></i>
                                        </div>
                                    </th>
                                    <th onclick="sortTable('date')" style="cursor: pointer;">
                                        <div style="display: flex; align-items: center; gap: 6px;">
                                            Submission Date
                                            <i class="fas fa-sort sort-icon"></i>
                                        </div>
                                    </th>
                                    <th onclick="sortTable('status')" style="cursor: pointer;">
                                        <div style="display: flex; align-items: center; gap: 6px;">
                                            Loan Status
                                            <i class="fas fa-sort sort-icon"></i>
                                        </div>
                                    </th>
                                    <th>Total Paid</th>
                                    <th>Remaining Balance</th>
                                    <th style="text-align: center;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activeApplications as $application): ?>
                                    <tr class="loan-row">
                                        <td data-label="Applicant Name">
                                            <div class="user-cell">
                                                <div class="user-avatar">
                                                    <?php echo strtoupper(substr($application['first_name'], 0, 1) . substr($application['last_name'], 0, 1)); ?>
                                                </div>
                                                <span><?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?></span>
                                            </div>
                                        </td>
                                        <td data-label="Loan Type">
                                            <span class="loan-type-badge">
                                                <?php echo htmlspecialchars($application['type_name']); ?>
                                            </span>
                                        </td>
                                        <td data-label="Final Amount">
                                            <span class="amount">
                                                <?php echo $application['final_amount'] > 0 ? '₱' . number_format($application['final_amount'], 2) : 'N/A'; ?>
                                            </span>
                                        </td>
                                        <td data-label="Submission Date">
                                            <?php echo date('M d, Y', strtotime($application['created_at'])); ?>
                                        </td>
                                        <td data-label="Loan Status">
                                            <?php
                                            $status = $application['status'];
                                            $statusIcon = '';
                                            $statusText = '';
                                            $statusClass = '';

                                            switch (strtolower($status)) {
                                                case 'paid':
                                                    $statusText = 'Fully Paid';
                                                    $statusClass = 'status-paid';
                                                    $statusIcon = 'fas fa-check-circle';
                                                    break;
                                                case 'partial':
                                                    $statusText = 'Partial Payment';
                                                    $statusClass = 'status-partial';
                                                    $statusIcon = 'fas fa-hourglass-half';
                                                    break;
                                                case 'active':
                                                    $statusText = 'Active - No Payment';
                                                    $statusClass = 'status-active';
                                                    $statusIcon = 'fas fa-play-circle';
                                                    break;
                                                case 'awaiting setup':
                                                    $statusText = 'Awaiting Loan Setup';
                                                    $statusClass = 'status-awaiting';
                                                    $statusIcon = 'fas fa-clock';
                                                    break;
                                                default:
                                                    $statusIcon = 'fas fa-info-circle';
                                                    $statusText = htmlspecialchars($status);
                                                    $statusClass = 'status-default';
                                            }
                                            ?>
                                            <span class="status-badge <?php echo $statusClass; ?>"
                                                title="<?php echo $statusText; ?>">
                                                <i class="<?php echo $statusIcon; ?>"></i>
                                                <span><?php echo $statusText; ?></span>
                                            </span>
                                        </td>
                                        <td data-label="Total Paid">
                                            <div style="display: flex; align-items: center; gap: 6px;">
                                                <?php
                                                if ($application['loan_id'] == 0) {
                                                    echo '<span style="background: #f0f0f0; padding: 4px 8px; border-radius: 4px; font-size: 12px; color: #666;">N/A</span>';
                                                } else {
                                                    $paidPercent = ($application['total_paid'] / max($application['final_amount'], 1)) * 100;
                                                    echo '<span class="paid-amount">₱' . number_format($application['total_paid'], 2) . '</span>';
                                                    echo '<small style="color: #666;">(' . round($paidPercent) . '%)</small>';
                                                }
                                                ?>
                                            </div>
                                        </td>
                                        <td data-label="Remaining Balance">
                                            <?php
                                            if ($application['loan_id'] == 0) {
                                                echo '<span class="no-loan-badge" style="background: #fff3cd; padding: 6px 10px; border-radius: 4px; font-size: 12px;" title="Loan not yet created"><i class="fas fa-hourglass-start"></i> Pending Setup</span>';
                                            } elseif ($application['remaining_balance'] <= 0) {
                                                echo '<span class="fully-paid-badge" style="background: #d4edda; padding: 6px 10px; border-radius: 4px; font-size: 12px; color: #155724;"><i class="fas fa-check-circle"></i> Fully Paid</span>';
                                            } else {
                                                $balancePercent = ($application['remaining_balance'] / max($application['final_amount'], 1)) * 100;
                                                $balanceClass = '';
                                                if ($balancePercent > 75) {
                                                    $balanceClass = 'balance-high-risk';
                                                } elseif ($balancePercent > 50) {
                                                    $balanceClass = 'balance-medium-risk';
                                                } else {
                                                    $balanceClass = 'balance-low-risk';
                                                }
                                                echo '<span class="' . $balanceClass . '" style="display: inline-block; padding: 6px 10px; border-radius: 4px; font-weight: 600;">₱' . number_format($application['remaining_balance'], 2) . '</span>';
                                            }
                                            ?>
                                        </td>
                                        <td data-label="Actions">
                                            <div class="action-buttons"
                                                style="display: flex; gap: 6px; justify-content: center;">
                                                <button class="action-btn view-btn"
                                                    onclick="openViewLoanModal(<?php echo (int) $application['loan_id']; ?>, event)"
                                                    <?php echo $application['loan_id'] == 0 ? 'disabled' : ''; ?>
                                                    title="View loan details" style="padding: 6px 10px; font-size: 12px;">
                                                    <i class="fas fa-eye"></i>
                                                    <span>View</span>
                                                </button>

                                                <?php if ($application['loan_id'] == 0): ?>
                                                    <button class="action-btn create-btn"
                                                        onclick="openCreateLoanModal('<?php echo htmlspecialchars($application['application_id']); ?>', <?php echo number_format($application['final_amount'], 2, '.', ''); ?>, event)"
                                                        title="Create loan payment plan"
                                                        data-app-id="<?php echo htmlspecialchars($application['application_id']); ?>"
                                                        data-final-amount="<?php echo number_format($application['final_amount'], 2, '.', ''); ?>"
                                                        style="padding: 6px 10px; font-size: 12px;">
                                                        <i class="fas fa-plus-circle"></i>
                                                        <span>Create</span>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="action-btn download-btn"
                                                    onclick="downloadLoanStatement(<?php echo (int) $application['loan_id']; ?>, '<?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?>')"
                                                    <?php echo $application['loan_id'] == 0 ? 'disabled' : ''; ?>
                                                    title="Download statement" style="padding: 6px 10px; font-size: 12px;">
                                                    <i class="fas fa-file-pdf"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    </div>

    <!-- Create Loan Modal -->
    <div id="createLoanModal" class="modal" role="dialog" aria-labelledby="createLoanModalLabel">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="createLoanModalLabel"><i class="fas fa-file-invoice-dollar"></i> Create Loan Payment Plan
                </h2>
                <span class="close" onclick="closeCreateLoanModal()" role="button" aria-label="Close modal">×</span>
            </div>
            <div>
                <form id="createLoanForm">
                    <input type="hidden" id="loanId" name="application_id">
                    <input type="hidden" id="interestRate" name="interest_rate"
                        value="<?php echo $default_interest_rate; ?>">
                    <div class="form-group">
                        <label for="loanAmount"><i class="fas fa-peso-sign"></i> Final Loan Amount (PHP)</label>
                        <div
                            style="padding: 12px; background: #f0fdf4; border: 2px solid #4caf50; border-radius: 8px; font-size: 16px; font-weight: 700; color: #1b5e20; display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-check-circle" style="color: #4caf50;"></i>
                            <span id="loanAmountDisplay">₱0.00</span>
                        </div>
                        <input type="hidden" id="loanAmount" name="amount">
                        <div id="loanAmountError" class="error-message"></div>
                    </div>
                    <div class="form-group">
                        <label for="duration"><i class="fas fa-calendar-alt"></i> Duration (Months)</label>
                        <input type="number" id="duration" name="duration" min="1" max="60" required
                            placeholder="Enter duration in months (1-60)" aria-describedby="durationError">
                        <div id="durationError" class="error-message"></div>
                    </div>
                    <div class="form-group">
                        <label for="frequency"><i class="fas fa-repeat"></i> Payment Frequency</label>
                        <select id="frequency" name="frequency" required aria-describedby="frequencyError">
                            <option value="" disabled selected>Select payment frequency</option>
                            <option value="monthly">Monthly</option>
                            <option value="quarterly">Quarterly</option>
                            <option value="semi-annually">Semi-Annually</option>
                            <option value="annually">Annually</option>
                        </select>
                        <div id="frequencyError" class="error-message"></div>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-percent"></i> Interest Rate (% per annum)</label>
                        <p
                            style="font-size: 16px; color: var(--green1); font-weight: 700; padding: 12px; background: #f0fdf4; border-radius: 8px; margin: 0; border-left: 4px solid var(--primary);">
                            <?php echo number_format($default_interest_rate, 2); ?>%
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-calculate" onclick="calculatePayment()">
                            <i class="fas fa-calculator"></i> Calculate Payment
                        </button>
                        <button type="submit" class="submit-btn" id="submitBtn">
                            <span><i class="fas fa-check-circle"></i> Create Loan</span>
                            <span class="spinner"></span>
                        </button>
                        <button type="button" class="btn-reset" onclick="resetForm()">
                            <i class="fas fa-redo"></i> Reset Form
                        </button>
                    </div>
                    <div id="paymentResult"></div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Loan Modal -->
    <div id="viewLoanModal" class="modal" role="dialog" aria-labelledby="viewLoanModalLabel">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="viewLoanModalLabel"><i class="fas fa-user-check"></i> Active Loan Details</h2>
                <span class="close" onclick="closeViewLoanModal()" role="button" aria-label="Close modal">×</span>
            </div>
            <div class="modal-body">
                <div id="loanDetailsContent">
                    <p>Loading...</p>
                </div>
            </div>
        </div>
    </div>
    </div>

    <!-- Payment Modal -->
    <div id="paymentModal" class="modal">
        <div class="modal-content payment-modal-enhanced">
            <div class="modal-header">
                <div class="header-title">
                    <i class="fas fa-money-bill-wave"></i>
                    <h2>Make Payment</h2>
                </div>
                <span class="close" onclick="closeModal('paymentModal')" role="button" aria-label="Close">&times;</span>
            </div>

            <form id="paymentForm" class="payment-form">
                <!-- Payment Summary Card -->
                <div class="payment-summary-card">
                    <div class="summary-row">
                        <span class="summary-label">Loan Balance:</span>
                        <span class="summary-value" id="summaryBalance">₱0.00</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Min. Payment:</span>
                        <span class="summary-value" id="summaryMinPayment">₱0.00</span>
                    </div>
                </div>

                <!-- Payment Amount Section -->
                <div class="form-section">
                    <h3><i class="fas fa-calculator"></i> Payment Amount</h3>

                    <div class="form-group">
                        <label for="paymentAmountType">
                            <span class="label-text">Payment Option</span>
                            <span class="required">*</span>
                        </label>
                        <select id="paymentAmountType" required onchange="handlePaymentAmountChange()"
                            aria-describedby="paymentAmountError">
                            <option value="">-- Select Payment Option --</option>
                            <option value="minimum">Minimum Payment (Monthly Due)</option>
                            <option value="full_schedule">Full Schedule Payment</option>
                            <option value="full_loan">Pay Off Entire Loan</option>
                            <option value="custom">Custom Amount</option>
                        </select>
                        <div id="paymentAmountError" class="error-message"></div>
                    </div>

                    <div class="form-group" id="customAmountGroup" style="display: none;">
                        <label for="paymentAmount">
                            <span class="label-text">Custom Amount (₱)</span>
                            <span class="required">*</span>
                        </label>
                        <div class="input-wrapper">
                            <span class="input-prefix">₱</span>
                            <input type="number" id="paymentAmount" step="0.01" min="0" placeholder="0.00"
                                aria-describedby="customAmountError">
                        </div>
                        <div id="customAmountError" class="error-message"></div>
                        <small class="help-text">Enter custom payment amount</small>
                    </div>

                    <div class="payment-amount-display"
                        style="background: #e8f5e9; padding: 16px; border-radius: 8px; margin-top: 12px; border-left: 4px solid #4caf50;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: #2e7d32; font-weight: 600;">Selected Payment Amount:</span>
                            <span id="selectedPaymentAmount"
                                style="font-size: 1.5rem; font-weight: 700; color: #1b5e20;">₱0.00</span>
                        </div>
                    </div>
                </div>

                <!-- Payment Details Section -->
                <div class="form-section">
                    <h3><i class="fas fa-info-circle"></i> Payment Details</h3>

                    <div class="form-group">
                        <label for="paymentDate">
                            <span class="label-text">Payment Date</span>
                            <span class="required">*</span>
                        </label>
                        <div class="input-wrapper">
                            <i class="fas fa-calendar"></i>
                            <input type="date" id="paymentDate" required aria-describedby="paymentDateError">
                        </div>
                        <div id="paymentDateError" class="error-message"></div>
                    </div>

                    <div class="form-group">
                        <label for="paymentType">
                            <span class="label-text">Payment Type</span>
                            <span class="required">*</span>
                        </label>
                        <select id="paymentType" required onchange="updatePaymentTypeDisplay()"
                            aria-describedby="paymentTypeError">
                            <option value="">-- Select Payment Type --</option>
                            <option value="full">Full Payment (Principal + Interest)</option>
                            <option value="principal">Principal Only</option>
                            <option value="interest">Interest Only</option>
                            <option value="custom">Custom Allocation</option>
                        </select>
                        <div id="paymentTypeError" class="error-message"></div>
                    </div>

                    <!-- Custom Payment Allocation (shown only for custom type) -->
                    <div id="customPaymentSection" class="custom-payment-section" style="display: none;">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="customInterest">
                                    <span class="label-text">Interest Paid (₱)</span>
                                </label>
                                <div class="input-wrapper">
                                    <span class="input-prefix">₱</span>
                                    <input type="number" id="customInterest" step="0.01" min="0" placeholder="0.00"
                                        onchange="calculateCustomTotal()">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="customPrincipal">
                                    <span class="label-text">Principal Paid (₱)</span>
                                </label>
                                <div class="input-wrapper">
                                    <span class="input-prefix">₱</span>
                                    <input type="number" id="customPrincipal" step="0.01" min="0" placeholder="0.00"
                                        onchange="calculateCustomTotal()">
                                </div>
                            </div>
                        </div>
                        <div class="custom-total-display">
                            <span>Total:</span>
                            <span id="customTotalAmount" class="total-amount">₱0.00</span>
                        </div>
                    </div>
                </div>

                <!-- OR Number Section -->
                <div class="form-section">
                    <h3><i class="fas fa-receipt"></i> Official Receipt Information</h3>

                    <div class="form-group">
                        <label for="orNumber">
                            <span class="label-text">OR Number</span>
                            <span class="required">*</span>
                        </label>
                        <div class="input-wrapper">
                            <i class="fas fa-hashtag"></i>
                            <input type="text" id="orNumber" name="or_number" maxlength="20" required
                                placeholder="Enter OR number (numbers only)" aria-describedby="orNumberError"
                                oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                        </div>
                        <div id="orNumberError" class="error-message"></div>
                        <small class="help-text">Official Receipt number (numeric only)</small>
                    </div>

                    <div class="form-group">
                        <label for="paymentNotes">
                            <span class="label-text">Notes</span>
                            <span class="optional">(Optional)</span>
                        </label>
                        <textarea id="paymentNotes" name="notes" rows="3" maxlength="500"
                            placeholder="Add any additional notes about this payment..."></textarea>
                        <small class="help-text"><span id="notesCount">0</span>/500 characters</small>
                    </div>
                </div>

                <!-- Error Message -->
                <div id="paymentError" class="error-message error-alert" style="display: none;"></div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="reset" class="btn-secondary" onclick="resetPaymentForm()">
                        <i class="fas fa-redo"></i> Reset
                    </button>
                    <button type="button" class="btn-secondary" onclick="closeModal('paymentModal')">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="submit-btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Submit Payment
                        <span class="spinner"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Enhanced CSS for Payment Modal -->
    <style>
        .payment-modal-enhanced {
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .payment-modal-enhanced .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 25px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px 12px 0 0;
        }

        .payment-modal-enhanced .header-title {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0;
        }

        .payment-modal-enhanced .header-title h2 {
            margin: 0;
            font-size: 22px;
            font-weight: 600;
        }

        .payment-modal-enhanced .header-title i {
            font-size: 24px;
        }

        .payment-modal-enhanced .close {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            font-size: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .payment-modal-enhanced .close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }

        /* Payment Summary Card */
        .payment-summary-card {
            background: linear-gradient(135deg, #f0f4ff 0%, #f9f7ff 100%);
            border-left: 4px solid #667eea;
            border-radius: 8px;
            padding: 20px;
            margin: 0 25px 20px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            font-size: 16px;
        }

        .summary-row:not(:last-child) {
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .summary-label {
            color: #666;
            font-weight: 500;
        }

        .summary-value {
            color: #1b5e20;
            font-weight: 700;
            font-size: 18px;
        }

        /* Form Sections */
        .payment-form {
            padding: 25px;
        }

        .form-section {
            margin-bottom: 30px;
        }

        .form-section h3 {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin: 0 0 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-section h3 i {
            color: #667eea;
        }

        /* Form Groups */
        .payment-form .form-group {
            margin-bottom: 18px;
        }

        .payment-form label {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 14px;
            color: #333;
        }

        .label-text {
            flex: 1;
        }

        .required {
            color: #dc2626;
            font-size: 16px;
        }

        .optional {
            color: #999;
            font-size: 12px;
            font-weight: 400;
            font-style: italic;
        }

        /* Input Wrapper */
        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .input-wrapper:focus-within {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .input-prefix {
            padding: 0 12px;
            color: #999;
            font-weight: 600;
            font-size: 14px;
        }

        .input-wrapper i {
            padding: 0 12px;
            color: #999;
        }

        .payment-form input,
        .payment-form select,
        .payment-form textarea {
            flex: 1;
            padding: 12px;
            border: none;
            background: transparent;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            color: #333;
        }

        .payment-form input:focus,
        .payment-form select:focus,
        .payment-form textarea:focus {
            outline: none;
        }

        .payment-form textarea {
            padding: 12px;
            resize: vertical;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .payment-form textarea:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        /* Help Text */
        .help-text {
            display: block;
            margin-top: 6px;
            font-size: 12px;
            color: #999;
            font-weight: 400;
        }

        /* Custom Payment Section */
        .custom-payment-section {
            background: #f9f9f9;
            border: 1px dashed #ddd;
            border-radius: 8px;
            padding: 16px;
            margin-top: 12px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .custom-total-display {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 12px;
            padding-top: 12px;
            border-top: 1px solid #ddd;
            margin-top: 12px;
            font-weight: 600;
        }

        .total-amount {
            color: #1b5e20;
            font-size: 18px;
        }

        /* Error Message */
        .error-message {
            display: none;
            color: #dc2626;
            font-size: 12px;
            margin-top: 6px;
            padding: 8px;
            background: #fee2e2;
            border-left: 3px solid #dc2626;
            border-radius: 4px;
        }

        .error-message.show {
            display: block;
        }

        .error-alert {
            display: none;
            color: white;
            background: #dc2626;
            padding: 12px 15px;
            border-radius: 8px;
            margin: 15px 0;
            font-weight: 500;
        }

        .error-alert.show {
            display: block;
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }

        .btn-secondary {
            flex: 1;
            padding: 12px 20px;
            background: #f0f0f0;
            color: #333;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
            transform: translateY(-2px);
        }

        .btn-primary {
            flex: 1;
            padding: 12px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* Spinner */
        .spinner {
            display: none;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .btn-primary:disabled .spinner {
            display: inline-block;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .payment-modal-enhanced {
                max-width: 95vw;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }
        }


        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
            white-space: nowrap;
            transition: all 0.3s ease;
        }

        .status-paid {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status-partial {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .status-active {
            background: #cfe9ff;
            color: #084298;
            border: 1px solid #b6d4fe;
        }

        .status-awaiting {
            background: #e2e3e5;
            color: #383d41;
            border: 1px solid #d3d6d8;
        }

        .status-default {
            background: #f0f0f0;
            color: #666;
            border: 1px solid #ddd;
        }

        /* Balance Indicators */
        .balance-high-risk {
            background: #ffebee;
            color: #c62828;
            padding: 6px 10px;
            border-radius: 4px;
            font-weight: 600;
        }

        .balance-medium-risk {
            background: #fff8e1;
            color: #f57f17;
            padding: 6px 10px;
            border-radius: 4px;
            font-weight: 600;
        }

        .balance-low-risk {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 6px 10px;
            border-radius: 4px;
            font-weight: 600;
        }

        .no-loan-badge {
            background: #fff3cd;
            padding: 6px 10px;
            border-radius: 4px;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .fully-paid-badge {
            background: #d4edda;
            padding: 6px 10px;
            border-radius: 4px;
            font-size: 12px;
            color: #155724;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }



        .action-btn.create-btn {
            background: #f3e5f5;
            color: #7b1fa2;
        }

        .action-btn.create-btn:hover:not(:disabled) {
            background: #7b1fa2;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(123, 31, 162, 0.3);
        }

        .action-btn.download-btn {
            background: #fce4ec;
            color: #c2185b;
        }

        .action-btn.download-btn:hover:not(:disabled) {
            background: #c2185b;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(194, 24, 91, 0.3);
        }

        .sort-icon {
            opacity: 0.5;
            transition: all 0.3s ease;
        }

        th:hover .sort-icon {
            opacity: 1;
        }

        .no-results {
            text-align: center;
            padding: 30px !important;
            color: #999;
        }

        .no-results i {
            font-size: 24px;
            margin-bottom: 10px;
            display: block;
        }

        .paid-amount {
            font-weight: 600;
            color: #2e7d32;
        }

        @media (max-width: 1024px) {
            .page-controls {
                justify-content: space-between;
            }

            .search-box {
                min-width: 150px;
                flex: 0 1 auto;
            }

            .filter-group {
                font-size: 12px;
            }

            .action-btn {
                padding: 5px 8px;
                font-size: 11px;
            }
        }

        @media (max-width: 768px) {
            .page-controls {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box {
                min-width: unset;
            }

            .filter-group {
                flex-direction: column;
            }

            .action-btn {
                padding: 4px 6px;
                font-size: 10px;
            }

            .action-btn span {
                display: none;
            }

            .action-buttons {
                gap: 4px;
            }
        }
    </style>

    <script>
        // Payment amount dropdown handler
        let currentLoanData = null;

        function handlePaymentAmountChange() {
            const typeSelect = document.getElementById('paymentAmountType');
            const customGroup = document.getElementById('customAmountGroup');
            const customInput = document.getElementById('paymentAmount');
            const displayAmount = document.getElementById('selectedPaymentAmount');

            const selectedType = typeSelect.value;

            if (selectedType === 'custom') {
                customGroup.style.display = 'block';
                customInput.required = true;
                displayAmount.textContent = '₱' + (parseFloat(customInput.value) || 0).toFixed(2);
            } else {
                customGroup.style.display = 'none';
                customInput.required = false;

                let amount = 0;
                if (currentLoanData) {
                    switch (selectedType) {
                        case 'minimum':
                            // Get minimum monthly payment from summary
                            const minPaymentEl = document.getElementById('summaryMinPayment');
                            amount = parseFloat(minPaymentEl.textContent.replace('₱', '').replace(/,/g, '')) || 0;
                            break;
                        case 'full_schedule':
                            // Get total due for current schedule
                            const totalDueEl = document.getElementById('summaryTotalDue');
                            amount = parseFloat(totalDueEl.textContent.replace('₱', '').replace(/,/g, '')) || 0;
                            break;
                        case 'full_loan':
                            // Get remaining balance
                            const remainingEl = document.getElementById('summaryRemaining');
                            amount = parseFloat(remainingEl.textContent.replace('₱', '').replace(/,/g, '')) || 0;
                            break;
                    }
                }

                displayAmount.textContent = '₱' + amount.toFixed(2).replace(/\\d(?=(\\d{3})+\\.)/g, '$&,');
                customInput.value = amount.toFixed(2);
            }
        }

        // Update display when custom amount changes
        document.addEventListener('DOMContentLoaded', function () {
            const customInput = document.getElementById('paymentAmount');
            if (customInput) {
                customInput.addEventListener('input', function () {
                    const displayAmount = document.getElementById('selectedPaymentAmount');
                    const amount = parseFloat(this.value) || 0;
                    displayAmount.textContent = '₱' + amount.toFixed(2).replace(/\\d(?=(\\d{3})+\\.)/g, '$&,');
                });
            }
        });

        // Burger menu toggle
        const burgerElement = document.querySelector('.burger');
        if (burgerElement) {
            burgerElement.addEventListener('click', function () {
                this.classList.toggle('active');
                document.querySelector('nav').classList.toggle('active');
            });
        }

        // Navigation links
        const navLinks = document.querySelectorAll('nav a');
        if (navLinks.length > 0) {
            navLinks.forEach(link => {
                link.addEventListener('click', function () {
                    document.querySelector('nav').classList.remove('active');
                    const burger = document.querySelector('.burger');
                    if (burger) {
                        burger.classList.remove('active');
                    }
                });
            });
        }

        function toggleDropdown(event) {
            event.stopPropagation();
            const dropdown = document.getElementById("dropdown");
            if (dropdown) {
                dropdown.classList.toggle("show");
            }
        }

        function handleProfileKeydown(event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                toggleDropdown(event);
            }
        }

        function searchTable() {
            filterTable();
        }

        function filterTable() {
            const input = document.getElementById('searchInput');
            const statusFilter = document.getElementById('statusFilter');
            const typeFilter = document.getElementById('typeFilter');
            const minAmount = document.getElementById('minAmount');
            const maxAmount = document.getElementById('maxAmount');
            const paymentStatusFilter = document.getElementById('paymentStatusFilter');
            const table = document.getElementById('activeLoansTable');

            if (!table) return;

            const rows = table.getElementsByTagName('tr');
            const searchValue = (input ? input.value.toLowerCase() : '');
            const statusValue = (statusFilter ? statusFilter.value.toLowerCase() : '');
            const typeValue = (typeFilter ? typeFilter.value.toLowerCase() : '');
            const minVal = parseFloat(minAmount ? minAmount.value : 0) || 0;
            const maxVal = parseFloat(maxAmount ? maxAmount.value : Infinity) || Infinity;
            const paymentStatus = (paymentStatusFilter ? paymentStatusFilter.value.toLowerCase() : '');

            let visibleCount = 0;

            for (let i = 1; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                if (cells.length === 0) continue;

                // Get values from cells
                const applicantName = cells[0] ? cells[0].textContent.toLowerCase() : '';
                const loanType = cells[1] ? cells[1].textContent.toLowerCase() : '';
                const amount = parseFloat(cells[2].textContent.replace(/[^0-9.]/g, '')) || 0;
                const loanStatus = cells[4] ? cells[4].textContent.toLowerCase() : '';

                // Determine payment status
                let paymentStatusText = 'no-payment';
                if (loanStatus.includes('partial')) {
                    paymentStatusText = 'partial-payment';
                } else if (loanStatus.includes('paid')) {
                    paymentStatusText = 'fully-paid';
                }

                // Apply filters
                let matchSearch = searchValue === '' || applicantName.includes(searchValue);
                let matchStatus = statusValue === '' || loanStatus.includes(statusValue);
                let matchType = typeValue === '' || loanType.includes(typeValue);
                let matchAmount = amount >= minVal && amount <= maxVal;
                let matchPaymentStatus = paymentStatus === '' || paymentStatusText === paymentStatus;

                if (matchSearch && matchStatus && matchType && matchAmount && matchPaymentStatus) {
                    rows[i].style.display = '';
                    visibleCount++;
                } else {
                    rows[i].style.display = 'none';
                }
            }

            // Show message if no results
            const tbody = table.querySelector('tbody');
            let noResultsMsg = tbody ? tbody.querySelector('.no-results') : null;

            if (visibleCount === 0 && tbody) {
                if (!noResultsMsg) {
                    noResultsMsg = document.createElement('tr');
                    noResultsMsg.className = 'no-results';
                    noResultsMsg.innerHTML = '<td colspan="8" style="text-align: center; padding: 30px; color: #999;"><i class="fas fa-search" style="font-size: 24px; margin-bottom: 10px; display: block;"></i>No records found matching your criteria</td>';
                    tbody.appendChild(noResultsMsg);
                }
            } else if (noResultsMsg) {
                noResultsMsg.remove();
            }
        }

        function clearAllFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('statusFilter').value = '';
            document.getElementById('typeFilter').value = '';
            document.getElementById('minAmount').value = '';
            document.getElementById('maxAmount').value = '';
            document.getElementById('paymentStatusFilter').value = '';
            filterTable();

            // Show success message
            Toastify({
                text: "All filters cleared",
                duration: 2000,
                gravity: "top",
                position: "right",
                backgroundColor: "#4caf50"
            }).showToast();
        }

        let sortDirection = {};

        function sortTable(column) {
            const table = document.getElementById('activeLoansTable');
            if (!table) return;

            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr:not(.no-results)'));

            // Toggle sort direction
            sortDirection[column] = !sortDirection[column];

            rows.sort((a, b) => {
                let aValue, bValue;

                switch (column) {
                    case 'name':
                        aValue = a.cells[0].textContent.trim();
                        bValue = b.cells[0].textContent.trim();
                        break;
                    case 'type':
                        aValue = a.cells[1].textContent.trim();
                        bValue = b.cells[1].textContent.trim();
                        break;
                    case 'amount':
                        aValue = parseFloat(a.cells[2].textContent.replace(/[^0-9.]/g, ''));
                        bValue = parseFloat(b.cells[2].textContent.replace(/[^0-9.]/g, ''));
                        break;
                    case 'date':
                        aValue = new Date(a.cells[3].textContent);
                        bValue = new Date(b.cells[3].textContent);
                        break;
                    case 'status':
                        aValue = a.cells[4].textContent.trim();
                        bValue = b.cells[4].textContent.trim();
                        break;
                    default:
                        return 0;
                }

                if (sortDirection[column]) {
                    return aValue > bValue ? 1 : -1;
                } else {
                    return aValue < bValue ? 1 : -1;
                }
            });

            rows.forEach(row => tbody.appendChild(row));
        }

        function exportTableToCSV() {
            const table = document.getElementById('activeLoansTable');
            if (!table) return;

            let csv = [];
            const headers = ['Applicant Name', 'Loan Type', 'Final Amount', 'Submission Date', 'Loan Status', 'Total Paid', 'Remaining Balance'];
            csv.push(headers.join(','));

            const rows = table.querySelectorAll('tbody tr:not(.no-results)');
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length > 0) {
                    const rowData = [];
                    for (let i = 0; i < 7; i++) {
                        let text = cells[i] ? cells[i].textContent.trim() : '';
                        // Remove icons and clean up text
                        text = text.replace(/[\u0000-\u001F\u007F-\u009F]/g, '');
                        text = '"' + text.replace(/"/g, '""') + '"';
                        rowData.push(text);
                    }
                    csv.push(rowData.join(','));
                }
            });

            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `active-records-${new Date().toISOString().split('T')[0]}.csv`;
            link.click();
        }

        function refreshTable() {
            const icon = document.getElementById('refreshIcon');
            if (icon) {
                icon.style.animation = 'spin 0.8s linear';
                setTimeout(() => {
                    location.reload();
                }, 800);
            } else {
                location.reload();
            }
        }

        function downloadLoanStatement(loanId, applicantName) {
            if (!loanId) {
                alert('Loan statement not available');
                return;
            }
            alert('Download feature for loan ID: ' + loanId + ' for ' + applicantName);
        }

        // Initialize filters on page load
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('keyup', filterTable);
            }
        });

        // Search input listener
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('keypress', function (e) {
                if (e.key === 'Enter') {
                    searchTable();
                }
            });
        }

        function openManageInterestRateModal() {
            const modal = document.getElementById("manageInterestRateModal");
            const historyTable = document.getElementById("interestRateHistoryTable");
            const ratesGrid = document.getElementById("ratesGrid");

            ratesGrid.innerHTML = '<div class="rate-card-skeleton"><div class="spinner"></div><p>Loading rates...</p></div>';
            historyTable.innerHTML = '<tr><td colspan="5"><div class="spinner"></div></td></tr>';

            modal.style.display = "block";
            setTimeout(() => modal.classList.add("show"), 10);

            fetch("interest_rate_api.php?action=get_all_interest_rates", { cache: "no-store" })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const rates = data.rates;
                        if (rates.length > 0) {
                            ratesGrid.innerHTML = rates.map(item => `
                                <div class="rate-card">
                                    <div class="term-badge">${item.term_length} Months</div>
                                    <div class="rate-display">${parseFloat(item.interest_rate).toFixed(2)}<span class="percent-sign">%</span></div>
                                    <div class="updated-info">Updated: ${new Date(item.updated_at).toLocaleDateString()}</div>
                                </div>
                            `).join('');
                        } else {
                            ratesGrid.innerHTML = '<div class="rate-card-skeleton"><p>No rates configured yet.</p></div>';
                        }
                    }
                })
                .catch(error => {
                    ratesGrid.innerHTML = `<div class="rate-card-skeleton"><p>Error loading rates: ${error.message}</p></div>`;
                });

            fetch("interest_rate_api.php?action=get_interest_rate_history", { cache: "no-store" })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.history.length > 0) {
                        historyTable.innerHTML = data.history.map(item => `
                            <tr>
                                <td>${item.id}</td>
                                <td>${item.term_length} Months</td>
                                <td>${parseFloat(item.interest_rate).toFixed(2)}%</td>
                                <td>${new Date(item.updated_at).toLocaleString()}</td>
                                <td>${item.updated_by || "System"}</td>
                            </tr>
                        `).join('');
                    } else {
                        historyTable.innerHTML = '<tr><td colspan="5">No history available.</td></tr>';
                    }
                })
                .catch(error => {
                    historyTable.innerHTML = `<tr><td colspan="5">Error: ${error.message}</td></tr>`;
                });
        }

        function closeManageInterestRateModal() {
            const modal = document.getElementById("manageInterestRateModal");
            modal.classList.remove("show");
            setTimeout(() => modal.style.display = "none", 300);
        }

        window.addEventListener("click", (event) => {
            const modal = document.getElementById("manageInterestRateModal");
            if (event.target === modal) {
                closeManageInterestRateModal();
            }
        });


        // Toggle dropdown on click
        document.querySelectorAll('.dropdown-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const dropdown = this.nextElementSibling;
                const icon = this.querySelector('.dropdown-icon');

                // Toggle dropdown visibility
                if (dropdown.style.display === "block") {
                    dropdown.style.display = "none";
                    icon.classList.remove('rotate');
                } else {
                    dropdown.style.display = "block";
                    icon.classList.add('rotate');
                }
            });
        });

        // Auto-open dropdown if a child link is active
        document.querySelectorAll('.dropdown-container').forEach(container => {
            const dropdownContent = container.querySelector('.dropdown-content');
            const dropdownBtn = container.querySelector('.dropdown-btn');
            const icon = dropdownBtn.querySelector('.dropdown-icon');

            // Open only if any child link is active
            if (dropdownContent.querySelector('a.active')) {
                dropdownContent.style.display = 'block';
                icon.classList.add('rotate');
            }
        });

        // Payment History Modal Function
        function viewPaymentHistory(loanId, applicantName) {
            if (!loanId || loanId === 0) {
                Toastify({
                    text: "This loan has not been created yet",
                    duration: 3000,
                    gravity: "top",
                    position: "right",
                    backgroundColor: "#ff9800"
                }).showToast();
                return;
            }

            const modal = document.getElementById('paymentHistoryModal');
            if (!modal) {
                alert('Payment history modal not found');
                return;
            }

            // Fetch payment history
            fetch('get_payment_history.php?loan_id=' + encodeURIComponent(loanId))
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let historyHTML = '';
                        if (data.payments && data.payments.length > 0) {
                            historyHTML = data.payments.map((payment, index) => `
                                <tr>
                                    <td>${index + 1}</td>
                                    <td>₱${parseFloat(payment.payment_amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    <td>${payment.principal_paid ? '₱' + parseFloat(payment.principal_paid).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '₱0.00'}</td>
                                    <td>${payment.interest_paid ? '₱' + parseFloat(payment.interest_paid).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '₱0.00'}</td>
                                    <td>${new Date(payment.payment_date).toLocaleDateString()}</td>
                                    <td><span class="payment-status-badge ${payment.payment_status.toLowerCase()}">${payment.payment_status}</span></td>
                                </tr>
                            `).join('');
                        } else {
                            historyHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px; color: #999;">No payment history found</td></tr>';
                        }

                        document.getElementById('paymentHistoryTableBody').innerHTML = historyHTML;
                        document.getElementById('paymentHistoryApplicant').textContent = applicantName;

                        modal.style.display = 'block';
                        setTimeout(() => modal.classList.add('show'), 10);
                    } else {
                        alert('Error loading payment history: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading payment history');
                });
        }

        function closePaymentHistoryModal() {
            const modal = document.getElementById('paymentHistoryModal');
            if (modal) {
                modal.classList.remove('show');
                setTimeout(() => modal.style.display = 'none', 300);
            }
        }

        window.addEventListener('click', function (event) {
            const modal = document.getElementById('paymentHistoryModal');
            if (event.target === modal) {
                closePaymentHistoryModal();
            }
        });


    </script>

    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

    <div id="manageInterestRateModal" class="modal" role="dialog" aria-labelledby="manageInterestRateModalLabel">
        <div class="modal-content interest-rate-modal-content">
            <div class="modal-header">
                <div class="header-content">
                    <i class="fas fa-percentage header-icon"></i>
                    <div>
                        <h2 id="manageInterestRateModalLabel">Interest Rate Management</h2>
                        <p class="header-subtitle">Configure and monitor loan interest rates</p>
                    </div>
                </div>
                <span class="close" onclick="closeManageInterestRateModal()" role="button"
                    aria-label="Close modal">×</span>
            </div>
            <div class="modal-body">
                <div class="current-rates-section">
                    <h3><i class="fas fa-th-list"></i> Current Rates by Term</h3>
                    <div class="rates-grid" id="ratesGrid">
                        <div class="rate-card-skeleton">
                            <div class="spinner"></div>
                            <p>Loading rates...</p>
                        </div>
                    </div>
                </div>
                <div class="interest-rate-history">
                    <h3><i class="fas fa-history"></i> Change History</h3>
                    <div class="scrollable-table">
                        <table class="loan-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Term Length</th>
                                    <th>Interest Rate</th>
                                    <th>Updated At</th>
                                    <th>Updated By</th>
                                </tr>
                            </thead>
                            <tbody id="interestRateHistoryTable">
                                <tr>
                                    <td colspan="5">
                                        <div class="spinner"></div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment History Modal -->
    <div id="paymentHistoryModal" class="modal" role="dialog" aria-labelledby="paymentHistoryModalLabel">
        <div class="modal-content" style="max-width: 900px;">
            <div class="modal-header">
                <div class="header-content">
                    <i class="fas fa-history" style="font-size: 24px;"></i>
                    <div>
                        <h2 id="paymentHistoryModalLabel">Payment History</h2>
                        <p class="header-subtitle">Applicant: <span id="paymentHistoryApplicant"></span></p>
                    </div>
                </div>
                <span class="close" onclick="closePaymentHistoryModal()" role="button" aria-label="Close modal">×</span>
            </div>
            <div class="modal-body">
                <div class="scrollable-table">
                    <table class="loan-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Amount Paid</th>
                                <th>Principal</th>
                                <th>Interest</th>
                                <th>Payment Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="paymentHistoryTableBody">
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 20px;">
                                    <div class="spinner"></div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="JAVASCRIPT/Real-Time.js"></script>
    <script src="JAVASCRIPT/active_records.js?v=<?php echo time(); ?>"></script>

</body>

</html>
<?php
mysqli_close($conn);
?>