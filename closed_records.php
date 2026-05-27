<?php
session_start();
require "CYCLOAN_db.php";

if (!isset($_SESSION['email'])) {
    header("Location: index.php");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);

// Determine admin role and dashboard link
if (!isset($_SESSION['role'])) {
    $stmt = $conn->prepare("
        SELECT 'superadmin' AS role FROM superadmins WHERE email = ? 
        UNION 
        SELECT 'admin1' AS role FROM admin1 WHERE email = ? 
        UNION 
        SELECT 'admin2' AS role FROM admin2 WHERE email = ?
    ");
    if (!$stmt) {
        error_log("Role determination query preparation failed: " . $conn->error, 3, 'errors.log');
        $_SESSION['error'] = "Database error: Unable to determine user role.";
        header("Location: index.php");
        exit();
    }
    $stmt->bind_param("sss", $_SESSION['email'], $_SESSION['email'], $_SESSION['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $_SESSION['role'] = $row ? $row['role'] : 'admin2';
    $stmt->close();
}
$adminRole = $_SESSION['role'];
$dashboard_link = $adminRole === 'superadmin' ? 'Superadmin_dashboard.php' : ($adminRole === 'admin1' ? 'admin1_dashboard.php' : 'admin2_dashboard.php');

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

// Handle AJAX request for loan details
if (isset($_GET['action']) && $_GET['action'] === 'get_loan_details' && isset($_GET['application_id'])) {
    $application_id = (int) $_GET['application_id'];

    $query = "SELECT application_id, user_id AS applicant_name, amount_applied AS loan_amount, 
                     term_length AS duration_months, repayment_frequency, purpose, 
                     project_type, project_description, final_loan_amount, 
                     updated_at AS closed_date
              FROM loan_applications 
              WHERE application_id = ? AND status = 'closed' AND is_archived = 0";

    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['error' => 'Database error: ' . mysqli_error($conn)]);
        exit();
    }

    mysqli_stmt_bind_param($stmt, 'i', $application_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {
        $loan_details = [
            'application_id' => $row['application_id'],
            'applicant_name' => htmlspecialchars($row['applicant_name']),
            'loan_amount' => number_format($row['loan_amount'], 2) . ' PHP',
            'duration_months' => htmlspecialchars($row['duration_months']),
            'repayment_frequency' => htmlspecialchars($row['repayment_frequency'] ?: 'N/A'),
            'purpose' => htmlspecialchars($row['purpose'] ?: 'N/A'),
            'project_type' => htmlspecialchars($row['project_type'] ?: 'N/A'),
            'project_description' => htmlspecialchars($row['project_description'] ?: 'N/A'),
            'final_loan_amount' => number_format($row['final_loan_amount'] ?: 0, 2) . ' PHP',
            'closed_date' => $row['closed_date'] ? date('Y-m-d', strtotime($row['closed_date'])) : 'N/A'
        ];
        echo json_encode($loan_details);
    } else {
        header('HTTP/1.1 404 Not Found');
        echo json_encode(['error' => 'Loan application not found or not closed']);
    }

    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    exit();
}

// Handle AJAX request for archiving a loan
if (isset($_GET['action']) && $_GET['action'] === 'archive_loan' && isset($_GET['application_id'])) {
    $application_id = (int) $_GET['application_id'];

    $query = "UPDATE loan_applications SET is_archived = 1 WHERE application_id = ? AND status = 'closed' AND is_archived = 0";
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['error' => 'Database error: ' . mysqli_error($conn)]);
        exit();
    }

    mysqli_stmt_bind_param($stmt, 'i', $application_id);
    mysqli_stmt_execute($stmt);

    if (mysqli_stmt_affected_rows($stmt) > 0) {
        echo json_encode(['success' => 'Loan application archived successfully']);
    } else {
        header('HTTP/1.1 404 Not Found');
        echo json_encode(['error' => 'Loan application not found, not closed, or already archived']);
    }

    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    exit();
}

// Fetch count of closed applications
$query_closed = "SELECT COUNT(*) as count FROM loan_applications WHERE status = 'closed' AND is_archived = 0";
$result_closed = mysqli_query($conn, $query_closed);
$closed_count = 0;
if ($result_closed) {
    $row = mysqli_fetch_assoc($result_closed);
    $closed_count = $row['count'];
    mysqli_free_result($result_closed);
} else {
    $_SESSION['error'] = "Error fetching closed applications count: " . mysqli_error($conn);
    error_log("Closed count error: " . mysqli_error($conn), 3, 'errors.log');
}

// Fetch counts for all status values
$query_all_status = "SELECT status, COUNT(*) as count FROM loan_applications WHERE is_archived = 0 GROUP BY status";
$result_all_status = mysqli_query($conn, $query_all_status);
$status_counts = [];
if ($result_all_status) {
    while ($row = mysqli_fetch_assoc($result_all_status)) {
        $status_counts[$row['status']] = $row['count'];
    }
    mysqli_free_result($result_all_status);
} else {
    $_SESSION['error'] = "Error fetching status counts: " . mysqli_error($conn);
    error_log("Status counts error: " . mysqli_error($conn), 3, 'errors.log');
}

// Fetch counts for loan_type_id
$query_loan_types = "SELECT loan_type_id, COUNT(*) as count FROM loan_applications WHERE is_archived = 0 GROUP BY loan_type_id";
$result_loan_types = mysqli_query($conn, $query_loan_types);
$loan_type_counts = [];
if ($result_loan_types) {
    while ($row = mysqli_fetch_assoc($result_loan_types)) {
        $loan_type_counts[$row['loan_type_id']] = $row['count'];
    }
    mysqli_free_result($result_loan_types);
} else {
    $_SESSION['error'] = "Error fetching loan type counts: " . mysqli_error($conn);
    error_log("Loan type counts error: " . mysqli_error($conn), 3, 'errors.log');
}

// Fetch counts for repayment_frequency
$query_repayment = "SELECT repayment_frequency, COUNT(*) as count FROM loan_applications WHERE is_archived = 0 GROUP BY repayment_frequency";
$result_repayment = mysqli_query($conn, $query_repayment);
$repayment_counts = [];
if ($result_repayment) {
    while ($row = mysqli_fetch_assoc($result_repayment)) {
        $repayment_counts[$row['repayment_frequency']] = $row['count'];
    }
    mysqli_free_result($result_repayment);
} else {
    $_SESSION['error'] = "Error fetching repayment frequency counts: " . mysqli_error($conn);
    error_log("Repayment frequency counts error: " . mysqli_error($conn), 3, 'errors.log');
}

// Fetch closed applications data
$query_closed_apps = "SELECT application_id, user_id AS applicant_name, 
                      amount_applied AS loan_amount, term_length AS duration_months, 
                      repayment_frequency, purpose, project_type, project_description, 
                      final_loan_amount, updated_at AS closed_date
                      FROM loan_applications
                      WHERE status = 'closed' AND is_archived = 0";
$result_closed_apps = mysqli_query($conn, $query_closed_apps);
$closed_applications = [];
if ($result_closed_apps) {
    while ($row = mysqli_fetch_assoc($result_closed_apps)) {
        $closed_applications[] = $row;
    }
    mysqli_free_result($result_closed_apps);
} else {
    $_SESSION['error'] = "Error fetching closed applications: " . mysqli_error($conn);
    error_log("Closed applications error: " . mysqli_error($conn), 3, 'errors.log');
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Closed Loan Applications</title>
    <link rel="stylesheet" href="CSS/admin_dashboard.css">
    <link rel="stylesheet" href="CSS/admin_profile.css">
    <link rel="stylesheet" href="CSS/nav_active.css">
    <link rel="stylesheet" href="CSS/closed_records.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
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
            <a href="#" onclick="openManageInterestRateModal()" class="manage-interest-rate-link">
                <i class="fa-solid fa-percent"></i> VIEW INTEREST RATES
            </a>
            <?php if ($adminRole === 'admin1' || $adminRole === 'superadmin'): ?>
                <a href="manage_credit_points.php"
                    class="<?php echo $current_page === 'manage_credit_points.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-star"></i> MANAGE CREDIT RATE
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
                <div onclick="toggleDropdown(event)">
                    <img src="uploads/<?php echo htmlspecialchars($profile_img); ?>" alt="Profile Image" class="profile"
                        onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22%231b5e20%22%3E%3Cpath d=%22M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z%22/%3E%3C/svg%3E';">
                </div>
                <div class="dropdown-menu" id="dropdown">
                    <ul>
                        <li>
                            <a href="profileAdmin1.php">
                                <img src="uploads/<?php echo htmlspecialchars($profile_img); ?>" alt="Profile Image"
                                    class="profile-icon"
                                    onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22%231b5e20%22%3E%3Cpath d=%22M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z%22/%3E%3C/svg%3E';">
                                Profile
                            </a>
                        </li>
                        <li>
                            <a class="logout" href="index.php">
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
        <?php
        if (isset($_SESSION['success'])) {
            echo '<div class="message success"><i class="fas fa-check-circle"></i>' . htmlspecialchars($_SESSION['success']) . '</div>';
            unset($_SESSION['success']);
        }
        if (isset($_SESSION['error'])) {
            echo '<div class="message error"><i class="fas fa-exclamation-circle"></i>' . htmlspecialchars($_SESSION['error']) . '</div>';
            unset($_SESSION['error']);
        }
        ?>

        <!-- Modern Page Header -->
        <div class="page-header">
            <div class="header-content">
                <div class="header-icon">
                    <i class="fa-solid fa-folder"></i>
                </div>
                <div class="header-text">
                    <h1>Closed Loan Records</h1>
                    <p>Access details of completed loans and their payment histories.</p>
                </div>
            </div>

            <div class="header-stats">
                <div class="stat-item">
                    <div class="stat-icon">
                        <i class="fa-solid fa-wallet"></i>
                    </div>
                    <span class="stat-number" id="total-closed"><?php echo count($closed_applications); ?></span>
                    <span class="stat-label">Total Closed</span>
                </div>
                <div class="stat-item success">
                    <div class="stat-icon">
                        <i class="fa-solid fa-coins"></i>
                    </div>
                    <span class="stat-number" id="total-amount">
                        <?php
                        $totalAmount = array_sum(array_column($closed_applications, 'loan_amount'));
                        echo '₱' . number_format($totalAmount, 2);
                        ?>
                    </span>
                    <span class="stat-label">Total Amount</span>
                </div>
                <div class="stat-item pending">
                    <div class="stat-icon">
                        <i class="fa-solid fa-money-bill"></i>
                    </div>
                    <span class="stat-number" id="final-amount">
                        <?php
                        $totalFinal = array_sum(array_column($closed_applications, 'final_loan_amount'));
                        echo '₱' . number_format($totalFinal, 2);
                        ?>
                    </span>
                    <span class="stat-label">Total Final Amount</span>
                </div>
                <div class="stat-item">
                    <div class="stat-icon">
                        <i class="fa-solid fa-calendar"></i>
                    </div>
                    <span class="stat-number" id="month-count">
                        <?php
                        $thisMonth = count(array_filter($closed_applications, function ($app) {
                            return date('Y-m', strtotime($app['closed_date'])) === date('Y-m');
                        }));
                        echo $thisMonth;
                        ?>
                    </span>
                    <span class="stat-label">This Month</span>
                </div>
            </div>
        </div>

        <div class="loan_applicants">
            <div class="table-header">
                <div class="table-controls">
                    <div class="search-box">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" id="searchInput" placeholder="Search by ID...">
                    </div>
                    <button class="export-btn" onclick="exportToCSV()">
                        <i class="fa-solid fa-file-csv"></i> Export CSV
                    </button>
                </div>
            </div>

            <?php if (empty($closed_applications)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fa-solid fa-inbox"></i>
                    </div>
                    <h3>No Closed Applications</h3>
                    <p>There are currently no closed loan applications to display.</p>
                </div>
            <?php else: ?>
                <div class="scrollable-table">
                    <table id="closed-records-table" class="loan-table">
                        <thead>
                            <tr>
                                <th class="sortable" data-sort="app-id">
                                    Application ID <i class="fa-solid fa-sort"></i>
                                </th>
                                <th class="sortable" data-sort="applicant-id">
                                    Applicant ID <i class="fa-solid fa-sort"></i>
                                </th>
                                <th class="sortable" data-sort="amount">
                                    Loan Amount <i class="fa-solid fa-sort"></i>
                                </th>
                                <th class="sortable" data-sort="duration">
                                    Duration <i class="fa-solid fa-sort"></i>
                                </th>
                                <th>Interest Rate (%)</th>
                                <th>Total Paid</th>
                                <th class="sortable" data-sort="date">
                                    Closed Date <i class="fa-solid fa-sort"></i>
                                </th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($closed_applications as $app): ?>
                                <tr id="row-<?php echo (int) $app['application_id']; ?>"
                                    data-app-id="<?php echo htmlspecialchars($app['application_id']); ?>"
                                    data-applicant-id="<?php echo htmlspecialchars($app['applicant_name']); ?>"
                                    data-amount="<?php echo $app['loan_amount']; ?>"
                                    data-duration="<?php echo $app['duration_months']; ?>"
                                    data-date="<?php echo strtotime($app['closed_date']); ?>">
                                    <td data-label="Application ID">
                                        <span class="app-id-badge">
                                            <i class="fa-solid fa-hashtag"></i>
                                            <?php echo htmlspecialchars($app['application_id']); ?>
                                        </span>
                                    </td>
                                    <td data-label="Applicant ID">
                                        <span class="applicant-id-text">
                                            <?php echo htmlspecialchars($app['applicant_name']); ?>
                                        </span>
                                    </td>
                                    <td data-label="Loan Amount">
                                        <span class="amount-text">₱<?php echo number_format($app['loan_amount'], 2); ?></span>
                                    </td>
                                    <td data-label="Duration (months)">
                                        <span class="duration-badge">
                                            <i class="fa-solid fa-calendar-days"></i>
                                            <?php echo htmlspecialchars($app['duration_months']); ?> months
                                        </span>
                                    </td>
                                    <td data-label="Interest Rate (%)">
                                        <span class="rate-text"><?php echo htmlspecialchars('N/A'); ?>%</span>
                                    </td>
                                    <td data-label="Total Paid">
                                        <span class="paid-text">₱<?php echo number_format(0, 2); ?></span>
                                    </td>
                                    <td data-label="Closed Date">
                                        <span class="date-text">
                                            <i class="fa-solid fa-calendar-check"></i>
                                            <?php echo htmlspecialchars($app['closed_date'] ? date('M d, Y', strtotime($app['closed_date'])) : 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td data-label="Actions">
                                        <button class="actions-btn views-btn"
                                            onclick="openViewLoanModal(<?php echo (int) $app['application_id']; ?>, event)"
                                            aria-label="View loan details for Applicant ID <?php echo htmlspecialchars($app['applicant_name']); ?>">
                                            <i class="fa-solid fa-eye"></i> View
                                        </button>
                                        <button class="actions-btn archive-btn"
                                            onclick="openArchiveLoanModal(<?php echo (int) $app['application_id']; ?>, event)"
                                            aria-label="Archive loan application for Applicant ID <?php echo htmlspecialchars($app['applicant_name']); ?>">
                                            <i class="fa-solid fa-archive"></i> Archive
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
                    <p>Try adjusting your search criteria.</p>
                </div>
            <?php endif; ?>

            <!-- View Loan Modal -->
            <div id="viewLoanModal" class="modal" role="dialog" aria-labelledby="viewLoanModalLabel">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 id="viewLoanModalLabel">Loan Details</h2>
                        <span class="close" onclick="closeViewLoanModal()" role="button"
                            aria-label="Close modal">×</span>
                    </div>
                    <div class="modal-body">
                        <div id="loanDetailsContent" class="loan-details">
                            <p>Loading...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Archive Loan Confirmation Modal -->
            <div id="archiveLoanModal" class="modal" role="dialog" aria-labelledby="archiveLoanModalLabel">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 id="archiveLoanModalLabel">Confirm Archive</h2>
                        <span class="close" onclick="closeArchiveLoanModal()" role="button"
                            aria-label="Close modal">×</span>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to archive this loan application?</p>
                    </div>
                    <div class="modal-footer">
                        <button class="actions-btn confirm-btn" onclick="confirmArchiveLoan()">Confirm</button>
                        <button class="actions-btn cancel-btn" onclick="closeArchiveLoanModal()">Cancel</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            // ========== INITIALIZE TABLE FEATURES ==========
            document.addEventListener('DOMContentLoaded', function () {
                initializeTableFeatures();
            });

            function initializeTableFeatures() {
                const searchInput = document.getElementById('searchInput');
                const sortableHeaders = document.querySelectorAll('.sortable');

                // Search functionality with debouncing
                let searchTimeout;
                if (searchInput) {
                    searchInput.addEventListener('input', function () {
                        clearTimeout(searchTimeout);
                        searchTimeout = setTimeout(() => {
                            filterTable();
                        }, 300);
                    });
                }

                // Sortable headers
                sortableHeaders.forEach(header => {
                    header.addEventListener('click', function () {
                        const sortKey = this.dataset.sort;
                        sortTable(sortKey, this);
                    });
                });
            }

            function filterTable() {
                const searchInput = document.getElementById('searchInput');
                const table = document.getElementById('closed-records-table');
                const noResults = document.getElementById('noResults');

                if (!table) return;

                const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
                const rows = table.querySelectorAll('tbody tr');
                let visibleCount = 0;

                rows.forEach(row => {
                    const appId = row.dataset.appId ? row.dataset.appId.toLowerCase() : '';
                    const applicantId = row.dataset.applicantId ? row.dataset.applicantId.toLowerCase() : '';

                    const matchesSearch = appId.includes(searchTerm) || applicantId.includes(searchTerm);

                    if (matchesSearch) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                // Show/hide no results message
                if (noResults) {
                    if (visibleCount === 0 && rows.length > 0) {
                        noResults.style.display = 'block';
                        table.parentElement.style.display = 'none';
                    } else {
                        noResults.style.display = 'none';
                        table.parentElement.style.display = 'block';
                    }
                }

                // Update stats
                updateStats(Array.from(rows).filter(r => r.style.display !== 'none'));
            }

            function sortTable(sortKey, headerElement) {
                const table = document.getElementById('closed-records-table');
                if (!table) return;

                const tbody = table.querySelector('tbody');
                const rows = Array.from(tbody.querySelectorAll('tr'));

                // Determine sort direction
                const isAscending = !headerElement.classList.contains('asc');

                // Remove sort classes from all headers
                document.querySelectorAll('.sortable').forEach(h => {
                    h.classList.remove('asc', 'desc');
                });

                // Add appropriate class to clicked header
                headerElement.classList.add(isAscending ? 'asc' : 'desc');

                // Sort rows
                rows.sort((a, b) => {
                    let aValue, bValue;

                    switch (sortKey) {
                        case 'app-id':
                            aValue = a.dataset.appId || '';
                            bValue = b.dataset.appId || '';
                            break;
                        case 'applicant-id':
                            aValue = a.dataset.applicantId || '';
                            bValue = b.dataset.applicantId || '';
                            break;
                        case 'amount':
                            aValue = parseFloat(a.dataset.amount) || 0;
                            bValue = parseFloat(b.dataset.amount) || 0;
                            break;
                        case 'duration':
                            aValue = parseInt(a.dataset.duration) || 0;
                            bValue = parseInt(b.dataset.duration) || 0;
                            break;
                        case 'date':
                            aValue = parseInt(a.dataset.date) || 0;
                            bValue = parseInt(b.dataset.date) || 0;
                            break;
                        default:
                            return 0;
                    }

                    if (typeof aValue === 'string') {
                        return isAscending ? aValue.localeCompare(bValue) : bValue.localeCompare(aValue);
                    } else {
                        return isAscending ? aValue - bValue : bValue - aValue;
                    }
                });

                // Re-append sorted rows
                rows.forEach(row => tbody.appendChild(row));
            }

            function updateStats(visibleRows) {
                const totalClosed = document.getElementById('total-closed');
                const totalAmount = document.getElementById('total-amount');
                const finalAmount = document.getElementById('final-amount');

                if (!visibleRows.length) return;

                const total = visibleRows.length;
                const amount = visibleRows.reduce((sum, row) => sum + parseFloat(row.dataset.amount || 0), 0);

                if (totalClosed) totalClosed.textContent = total;
                if (totalAmount) totalAmount.textContent = '₱' + amount.toFixed(2);
            }

            function exportToCSV() {
                const table = document.getElementById('closed-records-table');
                if (!table) return;

                const rows = Array.from(table.querySelectorAll('tbody tr')).filter(row => row.style.display !== 'none');

                if (rows.length === 0) {
                    alert('No data to export!');
                    return;
                }

                let csv = 'Application ID,Applicant ID,Loan Amount,Duration (months),Interest Rate (%),Total Paid,Closed Date\n';

                rows.forEach(row => {
                    const cells = [
                        row.dataset.appId || '',
                        row.dataset.applicantId || '',
                        row.dataset.amount || '',
                        row.dataset.duration || '',
                        'N/A',
                        '0.00',
                        new Date(parseInt(row.dataset.date) * 1000).toLocaleDateString() || ''
                    ];
                    csv += cells.map(cell => `"${cell}"`).join(',') + '\n';
                });

                const blob = new Blob([csv], { type: 'text/csv' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `closed_applications_${new Date().toISOString().split('T')[0]}.csv`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
            }

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

            let currentApplicationId = null;

            function toggleDropdown(event) {
                event.stopPropagation();
                const dropdown = document.getElementById("dropdown");
                dropdown.classList.toggle("show");
            }

            function openViewLoanModal(applicationId, event) {
                event.preventDefault();
                const modal = document.getElementById('viewLoanModal');
                const loanDetailsContent = document.getElementById('loanDetailsContent');
                modal.style.display = 'flex';
                setTimeout(() => modal.classList.add('show'), 10);
                loanDetailsContent.innerHTML = '<p>Loading...</p>';

                fetch(`closed_records.php?action=get_loan_details&application_id=${applicationId}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! Status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.error) {
                            loanDetailsContent.innerHTML = `<p class="error"><i class="fas fa-exclamation-circle"></i> ${data.error}</p>`;
                        } else {
                            loanDetailsContent.innerHTML = `
                            <p><strong>Application ID:</strong> ${data.application_id}</p>
                            <p><strong>Applicant ID:</strong> ${data.applicant_name}</p>
                            <p><strong>Loan Amount:</strong> ${data.loan_amount}</p>
                            <p><strong>Duration (Months):</strong> ${data.duration_months}</p>
                            <p><strong>Repayment Frequency:</strong> ${data.repayment_frequency}</p>
                            <p><strong>Purpose:</strong> ${data.purpose}</p>
                            <p><strong>Project Type:</strong> ${data.project_type}</p>
                            <p><strong>Project Description:</strong> ${data.project_description}</p>
                            <p><strong>Final Loan Amount:</strong> ${data.final_loan_amount}</p>
                            <p><strong>Closed Date:</strong> ${data.closed_date}</p>
                        `;
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching loan details:', error);
                        loanDetailsContent.innerHTML = '<p class="error"><i class="fas fa-exclamation-circle"></i> Failed to load loan details. Please try again.</p>';
                    });
            }

            function openArchiveLoanModal(applicationId, event) {
                event.preventDefault();
                currentApplicationId = applicationId;
                const modal = document.getElementById('archiveLoanModal');
                modal.style.display = 'flex';
                setTimeout(() => modal.classList.add('show'), 10);
            }

            function confirmArchiveLoan() {
                if (!currentApplicationId) return;

                fetch(`closed_records.php?action=archive_loan&application_id=${currentApplicationId}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! Status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            const row = document.getElementById(`row-${currentApplicationId}`);
                            if (row) {
                                row.remove();
                            }
                            const tableBody = document.querySelector('#closed-records-table tbody');
                            if (tableBody.children.length === 0) {
                                tableBody.innerHTML = '<tr><td colspan="8" class="no-applicants">No closed loan applications found.</td></tr>';
                            }
                            const messageDiv = document.createElement('div');
                            messageDiv.className = 'message success';
                            messageDiv.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.success;
                            document.querySelector('.main-content').prepend(messageDiv);
                            setTimeout(() => messageDiv.remove(), 3000);
                        } else {
                            const messageDiv = document.createElement('div');
                            messageDiv.className = 'message error';
                            messageDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + data.error;
                            document.querySelector('.main-content').prepend(messageDiv);
                            setTimeout(() => messageDiv.remove(), 3000);
                        }
                        closeArchiveLoanModal();
                    })
                    .catch(error => {
                        console.error('Error archiving loan:', error);
                        const messageDiv = document.createElement('div');
                        messageDiv.className = 'message error';
                        messageDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Failed to archive loan. Please try again.';
                        document.querySelector('.main-content').prepend(messageDiv);
                        setTimeout(() => messageDiv.remove(), 3000);
                        closeArchiveLoanModal();
                    });
            }

            function closeViewLoanModal() {
                const modal = document.getElementById('viewLoanModal');
                modal.classList.remove('show');
                setTimeout(() => { modal.style.display = 'none'; }, 300);
            }

            function closeArchiveLoanModal() {
                const modal = document.getElementById('archiveLoanModal');
                modal.classList.remove('show');
                setTimeout(() => { modal.style.display = 'none'; currentApplicationId = null; }, 300);
            }

            window.onclick = function (event) {
                const dropdown = document.getElementById("dropdown");
                if (!event.target.closest(".profile-container") && dropdown.classList.contains("show")) {
                    dropdown.classList.remove("show");
                }
                const viewModal = document.getElementById('viewLoanModal');
                const archiveModal = document.getElementById('archiveLoanModal');
                if (event.target === viewModal) {
                    closeViewLoanModal();
                }
                if (event.target === archiveModal) {
                    closeArchiveLoanModal();
                }
                const interestRateModal = document.getElementById("manageInterestRateModal");
                if (event.target === interestRateModal) {
                    closeManageInterestRateModal();
                }
            };

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

        </script>

        <script src="JAVASCRIPT/Real-Time.js"></script>

        <script>
            // ========== SIDEBAR TOGGLE (ENHANCED) ==========
            document.addEventListener('DOMContentLoaded', function () {
                const burger = document.querySelector('.burger');
                const nav = document.querySelector('nav');
                const navContainer = document.querySelector('.nav-container');
                const body = document.body;

                // Toggle burger menu
                if (burger && nav) {
                    burger.addEventListener('click', function (e) {
                        e.stopPropagation();
                        this.classList.toggle('active');
                        nav.classList.toggle('active');
                        nav.classList.toggle('open');

                        if (navContainer) {
                            navContainer.classList.toggle('expanded');
                        }

                        // Prevent body scroll when menu is open
                        if (nav.classList.contains('active')) {
                            body.style.overflow = 'hidden';
                        } else {
                            body.style.overflow = '';
                        }
                    });
                }

                // Close menu when clicking outside
                document.addEventListener('click', function (e) {
                    if (nav && burger && !nav.contains(e.target) && !burger.contains(e.target)) {
                        nav.classList.remove('active', 'open');
                        burger.classList.remove('active');
                        if (navContainer) {
                            navContainer.classList.remove('expanded');
                        }
                        body.style.overflow = '';
                    }
                });

                // Close menu when clicking nav links (mobile)
                if (nav) {
                    const navLinks = nav.querySelectorAll('a');
                    navLinks.forEach(link => {
                        link.addEventListener('click', function () {
                            if (window.innerWidth <= 768) {
                                nav.classList.remove('active', 'open');
                                if (burger) burger.classList.remove('active');
                                if (navContainer) navContainer.classList.remove('expanded');
                                body.style.overflow = '';
                            }
                        });
                    });
                }
            });
        </script>
    </div>

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
</body>

</html>