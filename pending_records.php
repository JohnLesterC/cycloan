<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require "CYCLOAN_db.php";
$current_page = basename($_SERVER['PHP_SELF']);

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

// Determine profile link based on role
$profile_link = $adminRole === 'superadmin' ? 'profileSuperadmin.php' : ($adminRole === 'admin1' ? 'profileAdmin1.php' : 'profileAdmin2.php');

$profile_img = !empty($user['profile_img']) ? $user['profile_img'] : "default.png";

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

// Fetch pending loan applicants (status = 'pending' or 'new')
$stmt = $conn->prepare("
    SELECT la.application_id, u.first_name, u.last_name, lt.type_name, la.amount_applied, la.status, 
           la.pre_approval_status, la.credit_investigation_status, la.created_at
    FROM loan_applications la
    JOIN users1 u ON la.user_id = u.id
    JOIN loan_types lt ON la.loan_type_id = lt.loan_type_id
    WHERE la.status IN ('pending', 'new')
    ORDER BY la.created_at DESC
");
if (!$stmt) {
    $_SESSION['error'] = 'Query preparation failed: ' . $conn->error;
    error_log("Query preparation failed: " . $conn->error);
    header('Location: ' . $dashboard_link);
    exit;
}
$stmt->execute();
$result = $stmt->get_result();
$pendingApplicants = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle AJAX request for loan details (for modal)
if (isset($_GET['action']) && $_GET['action'] === 'get_loan_details' && isset($_GET['application_id'])) {
    $applicationId = intval($_GET['application_id']);
    try {
        $stmt = $conn->prepare("
            SELECT la.*, lt.type_name, 
                   u.first_name, u.last_name, u.email, u.birthday, u.contact, u.res_address, u.civil_status, u.occupation, u.year_resident,
                   s.first_name AS spouse_first_name, s.last_name AS spouse_last_name, s.birthday AS spouse_birthday, s.occupation AS spouse_occupation, s.contact AS spouse_contact,
                   fi.business_income, fi.salary_income, fi.remittance_income, fi.other_income,
                   fi.business2_income, fi.salary2_income, fi.net_income,
                   fi.food_allowance, fi.electricity_bill, fi.water_bill, fi.internet_bill, fi.gas_bill,
                   fi.educational_allowance, fi.car_amortization, fi.insurance, fi.other_expense,
                   fi.total_expenditures, fi.expected_monthly_amortization, fi.remaining_income
            FROM loan_applications la
            JOIN users1 u ON la.user_id = u.id
            JOIN loan_types lt ON la.loan_type_id = lt.loan_type_id
            LEFT JOIN spouses s ON la.user_id = s.user_id
            LEFT JOIN financial_info fi ON la.user_id = fi.user_id
            WHERE la.application_id = ?
        ");
        if (!$stmt) {
            throw new Exception("Loan details prepare failed: " . $conn->error);
        }
        $stmt->bind_param("i", $applicationId);
        if (!$stmt->execute()) {
            throw new Exception("Loan details execute failed: " . $stmt->error);
        }
        $result = $stmt->get_result();
        $loan = $result->fetch_assoc();
        $stmt->close();

        if ($loan) {
            $stmt = $conn->prepare("
                SELECT dt.document_name, d.file_path, d.document_id, d.status, d.status_updated_at
                FROM documents d
                JOIN document_types dt ON d.document_type_id = dt.document_type_id
                WHERE d.application_id = ?
            ");
            if (!$stmt) {
                throw new Exception("Documents prepare failed: " . $conn->error);
            }
            $stmt->bind_param("i", $applicationId);
            if (!$stmt->execute()) {
                throw new Exception("Documents execute failed: " . $stmt->error);
            }
            $result = $stmt->get_result();
            $documents = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $stmt = $conn->prepare("
                SELECT remark_id, remarks, created_at, admin_name
                FROM remarks
                WHERE application_id = ?
                ORDER BY created_at DESC
            ");
            if (!$stmt) {
                throw new Exception("Remarks prepare failed: " . $conn->error);
            }
            $stmt->bind_param("i", $applicationId);
            if (!$stmt->execute()) {
                throw new Exception("Remarks execute failed: " . $stmt->error);
            }
            $result = $stmt->get_result();
            $remarks = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $response = [
                'success' => true,
                'loan' => $loan,
                'documents' => $documents,
                'remarks' => $remarks
            ];
        } else {
            $response = ['success' => false, 'message' => 'Loan application not found.'];
        }
    } catch (Exception $e) {
        error_log("Get Loan Details Error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Records - CYCLOAN</title>
    <link rel="stylesheet" href="CSS/admin_dashboard.css">
    <link rel="stylesheet" href="CSS/admin_profile.css">
    <link rel="stylesheet" href="CSS/pending_records.css">
    <link rel="stylesheet" href="CSS/nav_active.css">
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

    .modal-section-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }

    .detail-item {
        padding: 12px;
        background: #f9f9f9;
        border-radius: 6px;
        border-left: 3px solid #1b5e20;
    }

    .detail-label {
        font-size: 11px;
        color: #666;
        font-weight: 600;
        text-transform: uppercase;
        margin-bottom: 6px;
        display: block;
    }

    .detail-value {
        font-size: 14px;
        color: #333;
        font-weight: 500;
    }

    .detail-value.status {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }

    .detail-value.status.pending {
        background: #fff3cd;
        color: #856404;
    }

    .detail-value.status.approved {
        background: #d4edda;
        color: #155724;
    }

    .detail-value.status.rejected {
        background: #f8d7da;
        color: #721c24;
    }

    .financial-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }

    .financial-card {
        background: linear-gradient(135deg, #1b5e20 0%, #2d7d32 100%);
        color: white;
        padding: 15px;
        border-radius: 8px;
        text-align: center;
    }

    .financial-card h4 {
        margin-top: 0;
        margin-bottom: 10px;
        font-size: 14px;
        text-align: left;
        color: white;
        border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        padding-bottom: 8px;
    }

    .financial-card .card-label {
        font-size: 11px;
        opacity: 0.9;
        margin-bottom: 8px;
    }

    .financial-card .card-value {
        font-size: 18px;
        font-weight: 700;
    }

    .financial-items {
        display: flex;
        flex-direction: column;
        gap: 10px;
        text-align: left;
    }

    .financial-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px;
        background: rgba(255, 255, 255, 0.9);
        color: #333;
        border-radius: 4px;
        font-size: 13px;
    }

    .financial-row span {
        font-weight: 500;
    }

    .financial-row strong {
        color: #1b5e20;
        font-weight: 700;
    }

    .documents-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }

    .documents-table-wrapper {
        max-height: 250px;
        overflow-y: auto;
    }

    .documents-table thead {
        background: linear-gradient(135deg, #1b5e20 0%, #2d7d32 100%);
        color: white;
        font-weight: 600;
    }

    .documents-table th,
    .documents-table td {
        padding: 12px 15px;
        text-align: left;
        border-bottom: 1px solid #e5e7eb;
    }

    .documents-table tbody tr:hover {
        background: #f9fafb;
    }

    .remark-item {
        padding: 15px;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        margin-bottom: 10px;
    }
</style>

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
            <a href="#" id="viewInterestRatesBtn" class="view-interest-rate-link">
                <i class="fa-solid fa-percent"></i> VIEW INTEREST RATES
            </a>
            <?php if ($adminRole === 'admin1' || $adminRole === 'superadmin'): ?>
                <a href="manage_credit_points.php"
                    class="<?php echo $current_page === 'manage_credit_points.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-star"></i> MANAGE CREDIT RATE
                </a>
            <?php endif; ?>
            <?php if ($adminRole === 'superadmin'): ?>
                <a href="#" onclick="openManageInterestRateModal()" class="manage-interest-rate-link">
                    <i class="fa-solid fa-percent"></i> MANAGE INTEREST RATE
                </a>
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
                                    class="profile-icon"
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

        <!-- Modern Page Header -->
        <div class="page-header">
            <div class="header-content">
                <div class="header-icon">
                    <i class="fa-solid fa-spinner"></i>
                </div>
                <div class="header-text">
                    <h1>Pending Loan Records</h1>
                    <p>Monitor the status and progress of all pending loan applications.</p>
                </div>
            </div>
            <div class="header-stats">
                <div class="stat-item">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <span class="stat-number" id="total-pending"><?php echo count($pendingApplicants); ?></span>
                    <span class="stat-label">Total Pending</span>
                </div>
                <div class="stat-item success">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <span class="stat-number" id="pre-approved-count">
                        <?php
                        $preApprovedCount = count(array_filter($pendingApplicants, function ($app) {
                            return strtolower($app['pre_approval_status']) === 'approved';
                        }));
                        echo $preApprovedCount;
                        ?>
                    </span>
                    <span class="stat-label">Pre-Approved</span>
                </div>
                <div class="stat-item pending">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <span class="stat-number" id="investigation-count">
                        <?php
                        $investigationCount = count(array_filter($pendingApplicants, function ($app) {
                            return in_array(strtolower($app['credit_investigation_status']), ['pending', 'in_progress']);
                        }));
                        echo $investigationCount;
                        ?>
                    </span>
                    <span class="stat-label">Under Investigation</span>
                </div>
            </div>
        </div>

        <div class="loan_applicants">
            <div class="table-header">
                <div class="table-controls">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Search by name...">
                    </div>
                    <div class="filter-group">
                        <select id="statusFilter">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="new">New</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <select id="loanTypeFilter">
                            <option value="">All Loan Types</option>
                            <?php
                            $loanTypes = array_unique(array_column($pendingApplicants, 'type_name'));
                            foreach ($loanTypes as $type) {
                                echo '<option value="' . htmlspecialchars($type) . '">' . htmlspecialchars($type) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <button class="export-btn" onclick="exportToCSV()">
                        <i class="fa-solid fa-file-csv"></i> Export CSV
                    </button>
                </div>
            </div>

            <?php if (empty($pendingApplicants)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fa-solid fa-inbox"></i>
                    </div>
                    <h3>No Pending Applications</h3>
                    <p>There are currently no pending loan applications to review.</p>
                </div>
            <?php else: ?>
                <div class="scrollable-table">
                    <table class="loan-table" id="pendingTable">
                        <thead>
                            <tr>
                                <th class="sortable" data-sort="name">
                                    Applicant Name <i class="fa-solid fa-sort"></i>
                                </th>
                                <th class="sortable" data-sort="loan-type">
                                    Loan Type <i class="fa-solid fa-sort"></i>
                                </th>
                                <th class="sortable" data-sort="amount">
                                    Amount Applied <i class="fa-solid fa-sort"></i>
                                </th>
                                <th class="sortable" data-sort="status">
                                    Loan Status <i class="fa-solid fa-sort"></i>
                                </th>
                                <th class="sortable" data-sort="pre-approval">
                                    Pre-Approval <i class="fa-solid fa-sort"></i>
                                </th>
                                <th class="sortable" data-sort="credit">
                                    Credit Status <i class="fa-solid fa-sort"></i>
                                </th>
                                <th class="sortable" data-sort="date">
                                    Submission Date <i class="fa-solid fa-sort"></i>
                                </th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingApplicants as $applicant): ?>
                                <tr data-name="<?php echo htmlspecialchars($applicant['first_name'] . ' ' . $applicant['last_name']); ?>"
                                    data-loan-type="<?php echo htmlspecialchars($applicant['type_name']); ?>"
                                    data-amount="<?php echo $applicant['amount_applied']; ?>"
                                    data-status="<?php echo strtolower($applicant['status']); ?>"
                                    data-pre-approval="<?php echo strtolower($applicant['pre_approval_status']); ?>"
                                    data-credit="<?php echo strtolower($applicant['credit_investigation_status']); ?>"
                                    data-date="<?php echo strtotime($applicant['created_at']); ?>">
                                    <td data-label="Applicant Name">
                                        <div class="applicant-cell">
                                            <div class="applicant-avatar">
                                                <?php
                                                $nameParts = explode(' ', $applicant['first_name'] . ' ' . $applicant['last_name']);
                                                $initials = '';
                                                foreach ($nameParts as $part) {
                                                    $initials .= strtoupper(substr($part, 0, 1));
                                                }
                                                echo htmlspecialchars(substr($initials, 0, 2));
                                                ?>
                                            </div>
                                            <span><?php echo htmlspecialchars($applicant['first_name'] . ' ' . $applicant['last_name']); ?></span>
                                        </div>
                                    </td>
                                    <td data-label="Loan Type">
                                        <span class="loan-type-badge">
                                            <i class="fa-solid fa-tag"></i>
                                            <?php echo htmlspecialchars($applicant['type_name']); ?>
                                        </span>
                                    </td>
                                    <td data-label="Amount Applied">
                                        <span
                                            class="amount-text">₱<?php echo number_format($applicant['amount_applied'], 2); ?></span>
                                    </td>
                                    <td data-label="Loan Status">
                                        <span class="status-badge status-<?php echo strtolower($applicant['status']); ?>">
                                            <?php echo htmlspecialchars($applicant['status']); ?>
                                        </span>
                                    </td>
                                    <td data-label="Pre-Approval Status">
                                        <span
                                            class="status-badge status-<?php echo strtolower($applicant['pre_approval_status']); ?>">
                                            <?php echo htmlspecialchars($applicant['pre_approval_status']); ?>
                                        </span>
                                    </td>
                                    <td data-label="Credit Investigation Status">
                                        <span
                                            class="status-badge status-<?php echo strtolower($applicant['credit_investigation_status']); ?>">
                                            <?php echo htmlspecialchars($applicant['credit_investigation_status']); ?>
                                        </span>
                                    </td>
                                    <td data-label="Submission Date">
                                        <span class="date-text">
                                            <i class="fa-solid fa-calendar"></i>
                                            <?php echo date('M d, Y', strtotime($applicant['created_at'])); ?>
                                        </span>
                                    </td>
                                    <td data-label="Action">
                                        <button class="action-btn view-btn"
                                            onclick="openLoanDetailsModal('<?php echo $applicant['application_id']; ?>')"
                                            aria-label="View details for <?php echo htmlspecialchars($applicant['first_name'] . ' ' . $applicant['last_name']); ?>">
                                            <i class="fa-solid fa-eye"></i> View
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="no-results" id="noResults" style="display: none;">
                    <div class="no-results-icon">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </div>
                    <h3>No Results Found</h3>
                    <p>Try adjusting your search or filter criteria.</p>
                </div>
            <?php endif; ?>
        </div>

        <div id="loanDetailsModal" class="modal" role="dialog" aria-labelledby="loanDetailsModalLabel">
            <div class="modal-content"
                style="width: 95% !important; max-width: 100% !important; max-height: 85vh !important; padding: 0 !important; border-radius: 8px; display: flex; flex-direction: column;">
                <!-- Modal Header with Gradient -->
                <div class="modal-header"
                    style="background: linear-gradient(135deg, #1b5e20 0%, #2d7d32 100%); color: white; padding: 20px; border-radius: 8px 8px 0 0; flex-shrink: 0;">
                    <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <i class="fas fa-file-alt" style="font-size: 28px; color: #fbc02d;"></i>
                            <div>
                                <h2 id="loanDetailsModalLabel"
                                    style="margin: 0; font-size: 24px; font-weight: 700; color: white;">Loan Application
                                    Details</h2>
                                <p style="margin: 4px 0 0 0; font-size: 13px; opacity: 0.9;">Complete applicant
                                    information and loan details</p>
                            </div>
                        </div>
                        <span class="close" onclick="closeLoanDetailsModal()" role="button" aria-label="Close modal"
                            style="font-size: 32px; cursor: pointer; color: white; opacity: 0.8; transition: opacity 0.2s;"
                            onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.8'">×</span>
                    </div>
                </div>

                <!-- Modal Body with Scrolling -->
                <div class="modal-body" style="flex: 1; overflow-y: auto; padding: 20px; background: #f9f9f9;">
                    <div id="loanDetailsContent" class="loan-details">
                        <!-- Applicant Personal Information Section -->
                        <div class="modal-section">
                            <h3><i class="fas fa-user"></i> Personal Information</h3>
                            <div class="modal-section-grid">
                                <div class="detail-item">
                                    <span class="detail-label">Full Name</span>
                                    <span class="detail-value" id="applicantName">-</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Email Address</span>
                                    <span class="detail-value" id="applicantEmail">-</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Contact Number</span>
                                    <span class="detail-value" id="applicantContact">-</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Birthday</span>
                                    <span class="detail-value" id="applicantBirthday">-</span>
                                </div>
                            </div>
                        </div>

                        <!-- Applicant Details Section -->
                        <div class="modal-section">
                            <h3><i class="fas fa-id-card"></i> Applicant Details</h3>
                            <div class="modal-section-grid">
                                <div class="detail-item">
                                    <span class="detail-label">Address</span>
                                    <span class="detail-value" id="applicantAddress">-</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Civil Status</span>
                                    <span class="detail-value" id="applicantCivilStatus">-</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Occupation</span>
                                    <span class="detail-value" id="applicantOccupation">-</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Years Resident</span>
                                    <span class="detail-value" id="applicantYearsResident">-</span>
                                </div>
                            </div>
                        </div>

                        <!-- Spouse Information Section -->
                        <div class="modal-section" id="spouseSection" style="display: none;">
                            <h3><i class="fas fa-ring"></i> Spouse Information</h3>
                            <div class="modal-section-grid">
                                <div class="detail-item">
                                    <span class="detail-label">Spouse Name</span>
                                    <span class="detail-value" id="spouseName">-</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Spouse Birthday</span>
                                    <span class="detail-value" id="spouseBirthday">-</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Spouse Contact</span>
                                    <span class="detail-value" id="spouseContact">-</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Spouse Occupation</span>
                                    <span class="detail-value" id="spouseOccupation">-</span>
                                </div>
                            </div>
                        </div>

                        <!-- Loan Information Section -->
                        <div class="modal-section">
                            <h3><i class="fas fa-file-contract"></i> Loan Information</h3>
                            <div class="modal-section-grid">
                                <div class="detail-item">
                                    <span class="detail-label">Loan Type</span>
                                    <span class="detail-value" id="loanType">-</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Amount Applied</span>
                                    <span class="detail-value" id="loanAmount">-</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Status</span>
                                    <span class="detail-value status" id="loanStatus">-</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Pre-Approval Status</span>
                                    <span class="detail-value status" id="preApprovalStatus">-</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Term Length</span>
                                    <span class="detail-value" id="termLength">-</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Repayment Frequency</span>
                                    <span class="detail-value" id="repaymentFrequency">-</span>
                                </div>
                            </div>
                        </div>

                        <!-- Financial Information Section -->
                        <div class="modal-section">
                            <h3><i class="fas fa-coins"></i> Financial Information</h3>
                            <div class="financial-grid">
                                <div class="financial-card">
                                    <h4>Income Sources</h4>
                                    <div class="financial-items" id="incomeSourcesContainer">
                                        <p style="color: #999;">Loading...</p>
                                    </div>
                                </div>
                                <div class="financial-card">
                                    <h4>Monthly Expenses</h4>
                                    <div class="financial-items" id="expensesContainer">
                                        <p style="color: #999;">Loading...</p>
                                    </div>
                                </div>
                                <div class="financial-card financial-summary">
                                    <h4>Financial Summary</h4>
                                    <div class="financial-items" id="financialSummaryContainer">
                                        <p style="color: #999;">Loading...</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Documents Section -->
                        <div class="modal-section">
                            <h3><i class="fas fa-file-upload"></i> Submitted Documents</h3>
                            <div class="documents-table-wrapper">
                                <table class="documents-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Document Type</th>
                                            <th>Status</th>
                                            <th>Uploaded Date</th>
                                        </tr>
                                    </thead>
                                    <tbody id="documentsTableBody">
                                        <tr>
                                            <td colspan="4" style="text-align: center; padding: 20px; color: #999;">
                                                <i class="fas fa-spinner fa-spin"></i> Loading documents...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Remarks Section -->
                        <div class="modal-section remarks-section">
                            <h3><i class="fas fa-comment"></i> Review & Remarks History</h3>
                            <div class="remarks-timeline" id="remarksTimeline">
                                <div style="text-align: center; padding: 20px; color: #999;">
                                    <i class="fas fa-spinner fa-spin"></i> Loading remarks...
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle sidebar and burger button
        document.querySelector('.burger').addEventListener('click', function () {
            this.classList.toggle('active');
            document.querySelector('nav').classList.toggle('active');
        });

        // Close sidebar when clicking a nav link
        document.querySelectorAll('nav a').forEach(link => {
            link.addEventListener('click', function () {
                document.querySelector('nav').classList.remove('active');
                document.querySelector('.burger').classList.remove('active');
            });
        });

        function toggleDropdown(event) {
            event.stopPropagation();
            const dropdown = document.getElementById("dropdown");
            dropdown.classList.toggle("show");
        }

        function handleProfileKeydown(event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                toggleDropdown(event);
            }
        }

        function openLoanDetailsModal(applicationId) {
            const modal = document.getElementById("loanDetailsModal");
            modal.style.display = "block";
            setTimeout(() => modal.classList.add("show"), 10);

            // Fetch loan details
            fetch("pending_records.php?action=get_loan_details&application_id=" + applicationId, { cache: "no-store" })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const applicant = data.loan;

                        // Populate Personal Information
                        document.getElementById('applicantName').textContent = (applicant.first_name || '') + ' ' + (applicant.last_name || '');
                        document.getElementById('applicantEmail').textContent = applicant.email || '-';
                        document.getElementById('applicantContact').textContent = applicant.contact || '-';
                        document.getElementById('applicantBirthday').textContent = applicant.birthday ? new Date(applicant.birthday).toLocaleDateString() : '-';

                        // Populate Applicant Details
                        document.getElementById('applicantAddress').textContent = applicant.res_address || '-';
                        document.getElementById('applicantCivilStatus').textContent = applicant.civil_status || '-';
                        document.getElementById('applicantOccupation').textContent = applicant.occupation || '-';
                        document.getElementById('applicantYearsResident').textContent = applicant.year_resident || '-';

                        // Populate Spouse Information (if spouse exists)
                        const spouseSection = document.getElementById('spouseSection');
                        if (applicant.spouse_first_name) {
                            spouseSection.style.display = 'block';
                            document.getElementById('spouseName').textContent = (applicant.spouse_first_name || '') + ' ' + (applicant.spouse_last_name || '');
                            document.getElementById('spouseBirthday').textContent = applicant.spouse_birthday ? new Date(applicant.spouse_birthday).toLocaleDateString() : '-';
                            document.getElementById('spouseContact').textContent = applicant.spouse_contact || '-';
                            document.getElementById('spouseOccupation').textContent = applicant.spouse_occupation || '-';
                        } else {
                            spouseSection.style.display = 'none';
                        }

                        // Populate Loan Information
                        document.getElementById('loanType').textContent = applicant.type_name || '-';
                        document.getElementById('loanAmount').textContent = applicant.amount_applied ? '₱' + parseFloat(applicant.amount_applied).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '-';
                        document.getElementById('loanStatus').innerHTML = `<span class="status-badge status-${(applicant.status || '').toLowerCase()}">${applicant.status || '-'}</span>`;
                        document.getElementById('preApprovalStatus').innerHTML = `<span class="status-badge status-${(applicant.pre_approval_status || '').toLowerCase()}">${applicant.pre_approval_status || '-'}</span>`;
                        document.getElementById('termLength').textContent = applicant.term_months ? applicant.term_months + ' months' : '-';
                        document.getElementById('repaymentFrequency').textContent = applicant.payment_frequency || '-';

                        // Populate Financial Information
                        populateFinancialInfo(applicant);

                        // Populate Documents
                        populateDocuments(data.documents || []);

                        // Populate Remarks
                        populateRemarks(data.remarks || []);
                    } else {
                        document.getElementById('loanDetailsContent').innerHTML = '<p style="color: red;">Error loading loan details: ' + (data.message || 'Unknown error') + '</p>';
                    }
                })
                .catch(error => {
                    document.getElementById('loanDetailsContent').innerHTML = '<p style="color: red;">Error: ' + error.message + '</p>';
                });
        }

        function populateFinancialInfo(applicant) {
            // Income Sources
            const incomeFields = [
                { key: 'business_income', label: 'Business Income' },
                { key: 'salary_income', label: 'Salary Income' },
                { key: 'remittance_income', label: 'Remittance Income' },
                { key: 'other_income', label: 'Other Income' },
                { key: 'business2_income', label: 'Additional Business Income' },
                { key: 'salary2_income', label: 'Additional Salary Income' },
                { key: 'net_income', label: 'Net Income', bold: true }
            ];

            let incomeHTML = '';
            incomeFields.forEach(field => {
                const value = applicant[field.key];
                if (value) {
                    incomeHTML += `<div class="financial-row">
                        <span>${field.label}</span>
                        <strong>₱${parseFloat(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</strong>
                    </div>`;
                }
            });
            document.getElementById('incomeSourcesContainer').innerHTML = incomeHTML || '<p style="color: #999;">No income data</p>';

            // Monthly Expenses
            const expenseFields = [
                { key: 'food_allowance', label: 'Food Allowance' },
                { key: 'electricity_bill', label: 'Electricity Bill' },
                { key: 'water_bill', label: 'Water Bill' },
                { key: 'internet_bill', label: 'Internet Bill' },
                { key: 'gas_bill', label: 'Gas Bill' },
                { key: 'educational_allowance', label: 'Educational Allowance' },
                { key: 'car_amortization', label: 'Car Amortization' },
                { key: 'insurance', label: 'Insurance' },
                { key: 'other_expense', label: 'Other Expenses' },
                { key: 'total_expenditures', label: 'Total Expenditures', bold: true }
            ];

            let expenseHTML = '';
            expenseFields.forEach(field => {
                const value = applicant[field.key];
                if (value) {
                    expenseHTML += `<div class="financial-row">
                        <span>${field.label}</span>
                        <strong>₱${parseFloat(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</strong>
                    </div>`;
                }
            });
            document.getElementById('expensesContainer').innerHTML = expenseHTML || '<p style="color: #999;">No expense data</p>';

            // Financial Summary
            const expectedAmortization = applicant.expected_monthly_amortization || 0;
            const remainingIncome = (applicant.net_income || 0) - expectedAmortization;

            let summaryHTML = `
                <div class="financial-row">
                    <span>Expected Monthly Amortization</span>
                    <strong>₱${parseFloat(expectedAmortization).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</strong>
                </div>
                <div class="financial-row">
                    <span>Remaining Income</span>
                    <strong>₱${parseFloat(remainingIncome).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</strong>
                </div>
            `;
            document.getElementById('financialSummaryContainer').innerHTML = summaryHTML;
        }

        function populateDocuments(documents) {
            const tbody = document.getElementById('documentsTableBody');
            if (documents.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; color: #999;">No documents uploaded</td></tr>';
                return;
            }

            tbody.innerHTML = documents.map((doc, index) => `
                <tr>
                    <td>${index + 1}</td>
                    <td>${doc.document_name || '-'}</td>
                    <td><span class="status-badge status-${(doc.status || 'pending').toLowerCase()}">${doc.status || 'Pending'}</span></td>
                    <td>${doc.status_updated_at ? new Date(doc.status_updated_at).toLocaleDateString() : '-'}</td>
                </tr>
            `).join('');
        }

        function populateRemarks(remarks) {
            const timeline = document.getElementById('remarksTimeline');
            if (remarks.length === 0) {
                timeline.innerHTML = '<p style="text-align: center; color: #999; padding: 20px;">No remarks yet</p>';
                return;
            }

            timeline.innerHTML = remarks.map((remark, index) => `
                <div class="remark-item" style="${index === 0 ? 'background: #f0f8f5;' : ''}">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px solid #e0e0e0;">
                        <div style="flex: 1;">
                            <div style="font-weight: 600; color: #1b5e20; display: flex; align-items: center; gap: 6px;">
                                <i class="fas fa-user" style="color: #1b5e20;"></i>
                                ${remark.admin_name || 'Admin'}
                                ${index === 0 ? '<span style="background: #1b5e20; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: 600;">LATEST</span>' : ''}
                            </div>
                            <div style="font-size: 12px; color: #666; margin-top: 3px;">
                                <i class="fas fa-calendar-alt"></i> ${remark.created_at ? new Date(remark.created_at).toLocaleString() : ''}
                            </div>
                        </div>
                    </div>
                    <div style="background: white; padding: 10px; border-radius: 4px; border-left: 3px solid #fbc02d; margin-left: 0;">${remark.remarks || '-'}</div>
                </div>
            `).join('');
        }

        function closeLoanDetailsModal() {
            const modal = document.getElementById("loanDetailsModal");
            modal.classList.remove("show");
            setTimeout(() => modal.style.display = "none", 300);
        }

        window.onclick = function (event) {
            if (!event.target.closest(".profile-container")) {
                const dropdowns = document.getElementsByClassName("dropdown-menu");
                for (let i = 0; i < dropdowns.length; i++) {
                    if (dropdowns[i].classList.contains("show")) {
                        dropdowns[i].classList.remove("show");
                    }
                }
            }
            const loanDetailsModal = document.getElementById("loanDetailsModal");
            if (event.target === loanDetailsModal) {
                closeLoanDetailsModal();
            }
        };


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
        });;

    </script>

    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="JAVASCRIPT/Real-Time.js"></script>
    <script src="JAVASCRIPT/pending_records.js"></script>

    <!-- Interest Rate Management Modal -->
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

    <script>
        function openManageInterestRateModal() {
            const modal = document.getElementById("manageInterestRateModal");
            const historyTable = document.getElementById("interestRateHistoryTable");
            const ratesGrid = document.getElementById("ratesGrid");

            ratesGrid.innerHTML = '<div class="rate-card-skeleton"><div class="spinner"></div><p>Loading rates...</p></div>';
            historyTable.innerHTML = '<tr><td colspan="5"><div class="spinner"></div></td></tr>';

            modal.style.display = "block";
            setTimeout(() => modal.classList.add("show"), 10);

            fetch("Superadmin_dashboard.php?action=get_all_interest_rates", { cache: "no-store" })
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

            fetch("Superadmin_dashboard.php?action=get_interest_rate_history", { cache: "no-store" })
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
    </script>
</body>

</html>
<?php
mysqli_close($conn);
?>