<?php
session_start();
require "CYCLOAN_db.php";

if (!isset($_SESSION['email']) || !isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
try {
    $stmt = $conn->prepare("SELECT profile_image FROM users1 WHERE id = ?");
    if ($stmt === false) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $profile_image = !empty($row['profile_image']) ? $row['profile_image'] : 'assets/default.jpg';
    $stmt->close();
} catch (Exception $e) {
    error_log('Profile image fetch error: ' . $e->getMessage(), 3, 'errors.log');
    $profile_image = 'assets/default.png';
}

// Handle AJAX request for loan details
if (isset($_GET['action']) && $_GET['action'] === 'get_loan_details' && isset($_GET['application_id'])) {
    $applicationId = mysqli_real_escape_string($conn, $_GET['application_id']);
    $query = "
        SELECT la.*, lt.type_name, 
               u.first_name, u.last_name, u.email, u.birthday, u.contact,
               fi.business_income, fi.salary_income, fi.remittance_income, fi.other_income,
               fi.business2_income, fi.salary2_income, fi.net_income,
               fi.food_allowance, fi.electricity_bill, fi.water_bill, fi.internet_bill, fi.gas_bill,
               fi.educational_allowance, fi.car_amortization, fi.insurance, fi.other_expense,
               fi.total_expenditures, fi.expected_monthly_amortization, fi.remaining_income
        FROM loan_applications la
        JOIN users1 u ON la.user_id = u.id
        JOIN loan_types lt ON la.loan_type_id = lt.loan_type_id
        LEFT JOIN financial_info fi ON la.user_id = fi.user_id
        WHERE la.application_id = ? AND la.user_id = ?
    ";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "si", $applicationId, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $loan = mysqli_fetch_assoc($result);

    if ($loan) {
        $query = "
            SELECT dt.document_name, d.file_path, d.document_id, d.status, d.status_updated_at
            FROM documents d
            JOIN document_types dt ON d.document_type_id = dt.document_type_id
            WHERE d.application_id = ?
        ";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "s", $applicationId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $documents = mysqli_fetch_all($result, MYSQLI_ASSOC);

        $query = "
            SELECT remark_id, remarks, created_at
            FROM remarks
            WHERE application_id = ?
            ORDER BY created_at DESC
        ";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "s", $applicationId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $remarks = mysqli_fetch_all($result, MYSQLI_ASSOC);

        $response = [
            'success' => true,
            'loan' => $loan,
            'documents' => $documents,
            'remarks' => $remarks
        ];
    } else {
        $response = ['success' => false, 'message' => 'Loan application not found or access denied.'];
    }
    mysqli_stmt_close($stmt);
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Handle AJAX request for current interest rate
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
        error_log("Interest rate fetch error: " . $e->getMessage(), 3, 'errors.log');
        $response = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Handle AJAX request for getting document status with rejection notes
if (isset($_GET['action']) && $_GET['action'] === 'get_document_status' && isset($_GET['application_id'])) {
    $applicationId = mysqli_real_escape_string($conn, $_GET['application_id']);

    $query = "
        SELECT d.document_id, dt.document_name, d.status, d.status_updated_at, d.rejection_notes,
               d.file_path, d.file_type, d.file_size
        FROM documents d
        JOIN document_types dt ON d.document_type_id = dt.document_type_id
        WHERE d.application_id = ?
        ORDER BY dt.document_name ASC
    ";

    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $applicationId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $documents = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $documents[] = $row;
    }
    mysqli_stmt_close($stmt);

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'documents' => $documents]);
    exit;
}

// Handle AJAX request for required actions
if (isset($_GET['action']) && $_GET['action'] === 'get_required_actions' && isset($_GET['application_id'])) {
    $applicationId = mysqli_real_escape_string($conn, $_GET['application_id']);

    $actions = [];

    // Check pre-approval status
    $query = "SELECT pre_approval_status FROM loan_applications WHERE application_id = ? AND user_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "si", $applicationId, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $appData = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($appData && $appData['pre_approval_status'] === 'Pending') {
        $actions[] = [
            'id' => 'preapproval',
            'title' => 'Complete Pre-Approval Review',
            'description' => 'Your application is awaiting pre-approval assessment',
            'priority' => 'high',
            'icon' => 'fa-check-circle'
        ];
    }

    // Check for missing documents
    $docQuery = "
        SELECT COUNT(*) as missing_count 
        FROM document_types dt 
        LEFT JOIN documents d ON dt.document_type_id = d.document_type_id 
            AND d.application_id = ? 
            AND d.status = 'Approved'
        WHERE d.document_id IS NULL
    ";
    $docStmt = mysqli_prepare($conn, $docQuery);
    mysqli_stmt_bind_param($docStmt, "s", $applicationId);
    mysqli_stmt_execute($docStmt);
    $docResult = mysqli_stmt_get_result($docStmt);
    $docData = mysqli_fetch_assoc($docResult);
    mysqli_stmt_close($docStmt);

    if ($docData['missing_count'] > 0) {
        $actions[] = [
            'id' => 'documents',
            'title' => 'Submit Required Documents',
            'description' => $docData['missing_count'] . ' document(s) need approval',
            'priority' => 'high',
            'icon' => 'fa-file-upload'
        ];
    }

    // Check credit investigation
    $creditQuery = "SELECT credit_investigation_status FROM loan_applications WHERE application_id = ? AND user_id = ?";
    $creditStmt = mysqli_prepare($conn, $creditQuery);
    mysqli_stmt_bind_param($creditStmt, "si", $applicationId, $user_id);
    mysqli_stmt_execute($creditStmt);
    $creditResult = mysqli_stmt_get_result($creditStmt);
    $creditData = mysqli_fetch_assoc($creditResult);
    mysqli_stmt_close($creditStmt);

    if ($creditData && $creditData['credit_investigation_status'] === 'In Progress') {
        $actions[] = [
            'id' => 'credit',
            'title' => 'Await Credit Investigation Results',
            'description' => 'Credit investigation is currently in progress',
            'priority' => 'medium',
            'icon' => 'fa-search'
        ];
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'actions' => $actions]);
    exit;
}

// Handle AJAX request for activity log
if (isset($_GET['action']) && $_GET['action'] === 'get_activity_log' && isset($_GET['application_id'])) {
    $applicationId = mysqli_real_escape_string($conn, $_GET['application_id']);

    $activities = [];

    // Get main status updates from loan_applications
    $query = "
        SELECT created_at as 'timestamp', 'Application Submitted' as title, 
               'Your loan application has been submitted successfully' as description,
               'fas fa-file-alt' as icon, 'submitted' as type
        FROM loan_applications 
        WHERE application_id = ? AND user_id = ?
        
        UNION ALL
        
        SELECT updated_at, 'Pre-Approval Status Updated', 
               CONCAT('Pre-approval status: ', pre_approval_status),
               'fas fa-check-circle', 'preapproval'
        FROM loan_applications 
        WHERE application_id = ? AND user_id = ? 
        AND pre_approval_status != 'Pending'
        
        UNION ALL
        
        SELECT updated_at, 'Credit Investigation Updated',
               CONCAT('Credit investigation status: ', credit_investigation_status),
               'fas fa-search', 'credit'
        FROM loan_applications 
        WHERE application_id = ? AND user_id = ? 
        AND credit_investigation_status != 'Pending'
        
        ORDER BY timestamp DESC
        LIMIT 10
    ";

    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "sisisisi", $applicationId, $user_id, $applicationId, $user_id, $applicationId, $user_id, $applicationId, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $activities[] = $row;
    }
    mysqli_stmt_close($stmt);

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'activities' => $activities]);
    exit;
}

// Handle refresh banner AJAX request
if (isset($_GET['action']) && $_GET['action'] === 'refresh_banner') {
    $bannerQuery = "
        SELECT la.application_id, la.loan_id, u.first_name, u.last_name, lt.type_name, 
               la.amount_applied, la.status, la.pre_approval_status, la.credit_investigation_status, 
               la.created_at, la.final_loan_amount, l.total_paid, l.remaining_balance
        FROM loan_applications la
        JOIN users1 u ON la.user_id = u.id
        JOIN loan_types lt ON la.loan_type_id = lt.loan_type_id
        LEFT JOIN loans l ON la.application_id = l.application_id
        WHERE la.user_id = ? AND la.status = 'Pending'
        ORDER BY la.created_at DESC
        LIMIT 1
    ";
    $bannerStmt = mysqli_prepare($conn, $bannerQuery);
    mysqli_stmt_bind_param($bannerStmt, "i", $user_id);
    mysqli_stmt_execute($bannerStmt);
    $bannerResult = mysqli_stmt_get_result($bannerStmt);
    $activePendingLoan = mysqli_fetch_assoc($bannerResult);
    mysqli_stmt_close($bannerStmt);

    if ($activePendingLoan) {
        ?>
        <div class="active-loan-banner">
            <div class="banner-header">
                <div class="banner-icon">
                    <i class="fas fa-file-contract"></i>
                </div>
                <div class="banner-info">
                    <h2>Pending Application - <?= htmlspecialchars($activePendingLoan['type_name']) ?></h2>
                    <p>Application ID: <?= htmlspecialchars($activePendingLoan['application_id']) ?></p>
                </div>
                <div class="banner-status">
                    <span class="status-badge badge-pending">Pending</span>
                </div>
            </div>

            <div class="loan-metrics-grid">
                <div class="metric-card">
                    <div class="metric-icon"
                        style="background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 50%, #fbc02d 100%);"><i
                            class="fas fa-money-bill-wave"></i></div>
                    <div class="metric-content">
                        <span class="metric-label">Amount Applied</span>
                        <span class="metric-value">₱<?= number_format($activePendingLoan['amount_applied'], 2) ?></span>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-icon"
                        style="background: linear-gradient(135deg, #1b5e20 0%, #fbc02d 50%, #2e7d32 100%);"><i
                            class="fas fa-check-circle"></i></div>
                    <div class="metric-content">
                        <span class="metric-label">Pre-Approval Status</span>
                        <span
                            class="metric-value"><?= htmlspecialchars($activePendingLoan['pre_approval_status'] ?: 'Pending') ?></span>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-icon"
                        style="background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 70%, #fbc02d 100%);"><i
                            class="fas fa-search"></i></div>
                    <div class="metric-content">
                        <span class="metric-label">Credit Investigation</span>
                        <span
                            class="metric-value"><?= htmlspecialchars($activePendingLoan['credit_investigation_status'] ?: 'Pending') ?></span>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-icon"
                        style="background: linear-gradient(135deg, #1b5e20 0%, #fbc02d 40%, #2e7d32 100%);"><i
                            class="fas fa-calendar-alt"></i></div>
                    <div class="metric-content">
                        <span class="metric-label">Submitted Date</span>
                        <span class="metric-value"><?= date('M d, Y', strtotime($activePendingLoan['created_at'])) ?></span>
                    </div>
                </div>
            </div>

            <div class="banner-actions">
                <button class="action-btn primary"
                    onclick="if(typeof openLoanDetailsModal === 'function') { openLoanDetailsModal('<?= htmlspecialchars($activePendingLoan['application_id'], ENT_QUOTES) ?>'); } else { alert('Function not loaded yet. Please try again.'); }">
                    <i class="fas fa-eye"></i> View Full Details
                </button>
            </div>
        </div>
        <?php
    }
    exit;
}

// Handle refresh table AJAX request
if (isset($_GET['action']) && $_GET['action'] === 'refresh_table') {
    $query = "
        SELECT la.application_id, la.loan_id, u.first_name, u.last_name, lt.type_name, la.amount_applied, la.status, 
               la.pre_approval_status, la.credit_investigation_status, la.created_at
        FROM loan_applications la
        JOIN users1 u ON la.user_id = u.id
        JOIN loan_types lt ON la.loan_type_id = lt.loan_type_id
        WHERE la.user_id = ? AND la.status = 'Pending'
        ORDER BY la.created_at DESC
    ";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $pendingApplications = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    if (empty($pendingApplications)) {
        ?>
        <p class="no-applications">
            <i class="fa-solid fa-circle-exclamation"></i>No pending loan applications found.
        </p>
        <?php
    } else {
        ?>
        <div class="scrollable-table">
            <table class="loan-table">
                <thead>
                    <tr>
                        <th>Applicant Name</th>
                        <th>Loan Type</th>
                        <th>Amount Applied</th>
                        <th>Loan Status</th>
                        <th>Pre-Approval Status</th>
                        <th>Credit Investigation Status</th>
                        <th>Submission Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingApplications as $application): ?>
                        <tr data-application-id="<?php echo htmlspecialchars($application['application_id']); ?>">
                            <td data-label="Applicant Name">
                                <?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?>
                            </td>
                            <td data-label="Loan Type"><?php echo htmlspecialchars($application['type_name']); ?></td>
                            <td data-label="Amount Applied">₱<?php echo number_format($application['amount_applied'], 2); ?>
                            </td>
                            <td data-label="Loan Status"><span
                                    class="status-badge <?php echo strtolower($application['status']); ?>"><?php echo htmlspecialchars($application['status']); ?></span>
                            </td>
                            <td data-label="Pre-Approval Status"><span
                                    class="status-badge <?php echo strtolower($application['pre_approval_status']); ?>"><?php echo htmlspecialchars($application['pre_approval_status']); ?></span>
                            </td>
                            <td data-label="Credit Investigation Status"><span
                                    class="status-badge <?php echo strtolower($application['credit_investigation_status']); ?>"><?php echo htmlspecialchars($application['credit_investigation_status']); ?></span>
                            </td>
                            <td data-label="Submission Date">
                                <?php echo date('Y-m-d', strtotime($application['created_at'])); ?>
                            </td>
                            <td data-label="Action">
                                <button class="action-btn view-btn"
                                    onclick="if(typeof openLoanDetailsModal === 'function') { openLoanDetailsModal('<?php echo htmlspecialchars($application['loan_id'] ?: $application['application_id'], ENT_QUOTES); ?>'); } else { alert('Function not loaded. Refresh page.'); }"
                                    aria-label="View loan details for <?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?>">
                                    View
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    exit;
}

// Fetch pending loan applications for the user
$query = "
    SELECT la.application_id, la.loan_id, u.first_name, u.last_name, lt.type_name, la.amount_applied, la.status, 
           la.pre_approval_status, la.credit_investigation_status, la.created_at
    FROM loan_applications la
    JOIN users1 u ON la.user_id = u.id
    JOIN loan_types lt ON la.loan_type_id = lt.loan_type_id
    WHERE la.user_id = ? AND la.status = 'Pending'
    ORDER BY la.created_at DESC
";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$pendingApplications = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Fetch the first pending application for banner display
$bannerQuery = "
    SELECT la.application_id, la.loan_id, u.first_name, u.last_name, lt.type_name, 
           la.amount_applied, la.status, la.pre_approval_status, la.credit_investigation_status, 
           la.created_at, la.final_loan_amount, l.total_paid, l.remaining_balance
    FROM loan_applications la
    JOIN users1 u ON la.user_id = u.id
    JOIN loan_types lt ON la.loan_type_id = lt.loan_type_id
    LEFT JOIN loans l ON la.application_id = l.application_id
    WHERE la.user_id = ? AND la.status = 'Pending'
    ORDER BY la.created_at DESC
    LIMIT 1
";
$bannerStmt = mysqli_prepare($conn, $bannerQuery);
mysqli_stmt_bind_param($bannerStmt, "i", $user_id);
mysqli_stmt_execute($bannerStmt);
$bannerResult = mysqli_stmt_get_result($bannerStmt);
$activePendingLoan = mysqli_fetch_assoc($bannerResult);
mysqli_stmt_close($bannerStmt);

mysqli_close($conn);

// Get current page for active navigation
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CYCLOAN - Pending Records</title>
    <link rel="stylesheet" href="CSS/admin_dashboard.css">
    <link rel="stylesheet" href="CSS/user_dashboard.css">
    <link rel="stylesheet" href="CSS/user_pending_records.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
</head>

<body>
    <!-- Load JavaScript early so functions are available for onclick handlers -->
    <script src="JAVASCRIPT/Real-Time.js"></script>
    <script src="JAVASCRIPT/user_pending_records.js"></script>

    <div class="header">
        <div class="profileXdate">
            <div id="datetime" class="datetime"></div>
            <a href="notifications.php" class="notification-bell" title="View Notifications">
                <i class="fa-solid fa-bell"></i>
            </a>
            <div class="profile-container">
                <div onclick="toggleDropdown(event)">
                    <img src="<?php echo htmlspecialchars($profile_image); ?>" alt="Profile Image" class="profile">
                </div>
                <div class="dropdown-menu" id="dropdown">
                    <ul>
                        <li>
                            <a href="profile.php"
                                class="<?php echo $current_page === 'profile.php' ? 'active' : ''; ?>">
                                <img src="<?php echo htmlspecialchars($profile_image); ?>" alt="Profile Image"
                                    class="profile-icon">
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

    <div class="nav-container">
        <button class="burger" aria-label="Toggle menu">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <nav>
            <img src="IMAGE/Main-Logo.png" alt="Loan System Logo" class="sidebar-logo">
            <a href="user_dashboard.php" class="<?php echo $current_page === 'user_dashboard.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-table-columns"></i> DASHBOARD
            </a>
            <a href="user_active_record.php"
                class="<?php echo $current_page === 'user_active_record.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-user-check"></i> ACTIVE RECORDS
            </a>
            <a href="user_pending_records.php"
                class="<?php echo $current_page === 'user_pending_records.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-spinner"></i> PENDING RECORDS
            </a>
            <a href="user_closed_records.php"
                class="<?php echo $current_page === 'user_closed_records.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-circle-check"></i> CLOSED RECORDS
            </a>
            <a href="user_history_activity.php"
                class="<?php echo $current_page === 'user_history_activity.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-clipboard"></i> HISTORY ACTIVITY
            </a>
            <a href="#" onclick="openCalculatorModal(); return false;"
                class="<?php echo $current_page === 'loan_calculator' ? 'active' : ''; ?>">
                <i class="fa-solid fa-calculator"></i> LOAN CALCULATOR
            </a>
        </nav>
    </div>

    <div class="main-content">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="message success"><i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($_SESSION['success']); ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message error"><i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($_SESSION['error']); ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <h2>Pending Loan Applications</h2>

        <?php if ($activePendingLoan): ?>
            <div class="active-loan-banner">
                <div class="banner-header">
                    <div class="banner-icon">
                        <i class="fas fa-file-contract"></i>
                    </div>
                    <div class="banner-info">
                        <h2>Pending Application - <?= htmlspecialchars($activePendingLoan['type_name']) ?></h2>
                        <p>Application ID: <?= htmlspecialchars($activePendingLoan['application_id']) ?></p>
                    </div>
                    <div class="banner-status">
                        <span class="status-badge badge-pending">Pending</span>
                    </div>
                </div>

                <div class="loan-metrics-grid">
                    <div class="metric-card">
                        <div class="metric-icon"
                            style="background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 50%, #fbc02d 100%);"><i
                                class="fas fa-money-bill-wave"></i></div>
                        <div class="metric-content">
                            <span class="metric-label">Amount Applied</span>
                            <span class="metric-value">₱<?= number_format($activePendingLoan['amount_applied'], 2) ?></span>
                        </div>
                    </div>

                    <div class="metric-card">
                        <div class="metric-icon"
                            style="background: linear-gradient(135deg, #1b5e20 0%, #fbc02d 50%, #2e7d32 100%);"><i
                                class="fas fa-check-circle"></i></div>
                        <div class="metric-content">
                            <span class="metric-label">Pre-Approval Status</span>
                            <span
                                class="metric-value"><?= htmlspecialchars($activePendingLoan['pre_approval_status'] ?: 'Pending') ?></span>
                        </div>
                    </div>

                    <div class="metric-card">
                        <div class="metric-icon"
                            style="background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 70%, #fbc02d 100%);"><i
                                class="fas fa-search"></i></div>
                        <div class="metric-content">
                            <span class="metric-label">Credit Investigation</span>
                            <span
                                class="metric-value"><?= htmlspecialchars($activePendingLoan['credit_investigation_status'] ?: 'Pending') ?></span>
                        </div>
                    </div>

                    <div class="metric-card">
                        <div class="metric-icon"
                            style="background: linear-gradient(135deg, #1b5e20 0%, #fbc02d 40%, #2e7d32 100%);"><i
                                class="fas fa-calendar-alt"></i></div>
                        <div class="metric-content">
                            <span class="metric-label">Submitted Date</span>
                            <span
                                class="metric-value"><?= date('M d, Y', strtotime($activePendingLoan['created_at'])) ?></span>
                        </div>
                    </div>
                </div>

                <div class="banner-actions">
                    <button class="action-btn primary"
                        onclick="if(typeof openLoanDetailsModal === 'function') { openLoanDetailsModal('<?= htmlspecialchars($activePendingLoan['application_id'], ENT_QUOTES) ?>'); } else { alert('Function not loaded. Refresh page.'); }">
                        <i class="fas fa-eye"></i> View Full Details
                    </button>
                </div>
            </div>

            <!-- Document Status Section -->
            <div class="document-status-section">
                <div class="section-header">
                    <h3><i class="fas fa-file-check"></i> Document Status</h3>
                    <button class="refresh-btn" id="refreshDocStatusBtn" title="Refresh document status">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
                <div class="document-status-grid" id="documentStatusGrid">
                    <div class="loading-status">
                        <i class="fas fa-spinner"></i> Loading documents...
                    </div>
                </div>
            </div>

            <!-- Activity Log -->
            <div class="activity-log-section">
                <div class="section-header">
                    <h3><i class="fas fa-history"></i> Activity Log</h3>
                    <button class="refresh-btn" id="refreshActivityBtn" title="Refresh activity log">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
                <div class="activity-timeline" id="activityTimeline">
                    <div class="loading-activity">
                        <i class="fas fa-spinner"></i> Loading activity...
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (empty($pendingApplications)): ?>
        <p class="no-applications">
            <i class="fa-solid fa-circle-exclamation"></i>No pending loan applications found.
        </p>
    <?php endif; ?>
    </div>

    <!-- Loan Details Modal (Outside main-content for proper z-index) -->
    <div id="loanDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="loanDetailsModalLabel">Loan Application Details</h2>
                <span class="close" onclick="closeLoanDetailsModal()" role="button" aria-label="Close modal">×</span>
            </div>
            <div class="modal-body">
                <div id="loanDetailsContent" class="application-details">
                    <p>Loading...</p>
                </div>
            </div>
        </div>
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
                        <h2><i class="fa-solid fa-chart-pie"></i> Approximately</h2>
                        <div id="resultsDisplay">
                            <div class="no-result">
                                <i class="fa-solid fa-calculator"></i>
                                <p>Enter loan details and click Calculate to see results</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Amortization Schedule -->
                <div id="amortizationContainer" style="display: none; margin-top: 30px;">
                    <div
                        style="background: var(--light); border-radius: 10px; padding: 30px; box-shadow: var(--shadow);">
                        <h2 style="margin-bottom: 20px; background: var(--primary);">
                            <i class="fa-solid fa-table"></i> Amortization Schedule
                        </h2>
                        <div class="amortization-schedule">
                            <table class="amortization-table" id="amortizationTable">
                                <thead>
                                    <tr>
                                        <th>Payment #</th>
                                        <th>Payment Amount</th>
                                        <th>Principal</th>
                                        <th>Interest</th>
                                        <th>Balance</th>
                                    </tr>
                                </thead>
                                <tbody id="amortizationBody"></tbody>
                            </table>
                        </div>
                        <button class="print-btn" onclick="window.print()">
                            <i class="fa-solid fa-print"></i> Print Schedule
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>

    <!-- Enhanced Real-Time Validation for Loan Calculator -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            initializeCalculatorValidation();
        });

        function initializeCalculatorValidation() {
            const loanTypeSelect = document.getElementById('loanType');
            const loanAmountInput = document.getElementById('loanAmount');
            const termLengthSelect = document.getElementById('termLength');
            const repaymentFrequencySelect = document.getElementById('repaymentFrequency');

            if (!loanTypeSelect) return; // Exit if calculator not on page

            // Event listeners for real-time validation
            loanTypeSelect.addEventListener('change', function () {
                validateLoanType();
                validateLoanAmount();
                clearErrors();
            });

            loanAmountInput.addEventListener('input', function () {
                validateLoanAmount();
                clearErrors();
            });

            loanAmountInput.addEventListener('blur', function () {
                validateLoanAmount();
            });

            termLengthSelect.addEventListener('change', function () {
                validateTermLength();
                validateRepaymentFrequency();
                clearErrors();
            });

            repaymentFrequencySelect.addEventListener('change', function () {
                validateRepaymentFrequency();
                clearErrors();
            });

            repaymentFrequencySelect.addEventListener('blur', function () {
                validateRepaymentFrequency();
            });
        }

        function validateLoanType() {
            const loanType = document.getElementById('loanType').value;
            const loanTypeField = document.getElementById('loanType').parentElement;

            if (!loanType) {
                addValidationError(loanTypeField, 'Please select a loan type');
                return false;
            } else {
                removeValidationError(loanTypeField);
                return true;
            }
        }

        function validateLoanAmount() {
            const loanType = document.getElementById('loanType').value;
            const loanAmount = parseFloat(document.getElementById('loanAmount').value);
            const loanAmountField = document.getElementById('loanAmount').parentElement;

            if (!loanType) {
                addValidationError(loanAmountField, 'Select loan type first');
                return false;
            }

            let min, max;
            if (loanType === 'Individual' || loanType === 'Business' || loanType === 'Agricultural') {
                min = 10000;
                max = 100000;
            } else if (loanType === 'Cooperative') {
                min = 300000;
                max = 1000000;
            }

            if (isNaN(loanAmount)) {
                addValidationError(loanAmountField, `Enter loan amount (₱${min.toLocaleString()} - ₱${max.toLocaleString()})`);
                return false;
            } else if (loanAmount < min) {
                addValidationError(loanAmountField, `Minimum amount is ₱${min.toLocaleString()}`);
                return false;
            } else if (loanAmount > max) {
                addValidationError(loanAmountField, `Maximum amount is ₱${max.toLocaleString()}`);
                return false;
            } else {
                removeValidationError(loanAmountField);
                return true;
            }
        }

        function validateTermLength() {
            const termLength = document.getElementById('termLength').value;
            const termField = document.getElementById('termLength').parentElement;

            if (!termLength) {
                addValidationError(termField, 'Please select a term length');
                return false;
            } else {
                removeValidationError(termField);
                return true;
            }
        }

        function validateRepaymentFrequency() {
            const termLength = parseInt(document.getElementById('termLength').value) || 0;
            const repaymentFrequency = document.getElementById('repaymentFrequency').value;
            const frequencyField = document.getElementById('repaymentFrequency').parentElement;

            if (!repaymentFrequency) {
                addValidationError(frequencyField, 'Please select repayment frequency');
                return false;
            }

            if (termLength === 6 && repaymentFrequency !== 'Monthly') {
                addValidationError(frequencyField, '6-month term only allows Monthly repayment');
                return false;
            } else {
                removeValidationError(frequencyField);
                return true;
            }
        }

        function addValidationError(field, message) {
            removeValidationError(field);
            const errorDiv = document.createElement('span');
            errorDiv.className = 'validation-error';
            errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
            field.appendChild(errorDiv);
            field.style.borderColor = '#ff6b6b';
        }

        function removeValidationError(field) {
            const errorDiv = field.querySelector('.validation-error');
            if (errorDiv) {
                errorDiv.remove();
            }
            field.style.borderColor = '';
        }

        function clearErrors() {
            const errorMessage = document.getElementById('errorMessage');
            if (errorMessage) {
                errorMessage.style.display = 'none';
            }
        }
    </script>

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

        .validation-error {
            display: block;
            color: #ff6b6b;
            font-size: 12px;
            margin-top: 5px;
            animation: slideIn 0.3s ease-in;
        }

        .validation-error i {
            margin-right: 5px;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-5px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Document Status Styles */
        .document-status-section {
            background: white;
            padding: 24px;
            border-radius: 8px;
            border-left: 4px solid #2e7d32;
            margin-top: 24px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .document-status-section .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid #e2e8f0;
        }

        .document-status-section .section-header h3 {
            font-size: 16px;
            font-weight: 700;
            color: #1b5e20;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .document-status-section .section-header h3 i {
            font-size: 18px;
        }

        .refresh-btn {
            background: none;
            border: none;
            color: #1b5e20;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 6px 10px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .refresh-btn:hover {
            background: rgba(27, 94, 32, 0.1);
            color: #2e7d32;
            transform: rotate(180deg);
        }

        .refresh-btn:active {
            transform: scale(0.95) rotate(180deg);
        }

        .document-status-grid {
            width: 100%;
            overflow-x: auto;
        }

        .loading-status {
            text-align: center;
            padding: 40px 20px;
            color: #999;
            font-size: 14px;
        }

        .loading-status i {
            animation: spin 2s linear infinite;
            margin-right: 10px;
            color: #1b5e20;
            font-size: 18px;
        }

        @keyframes spin {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        /* Document Status Table Styling */
        .document-status-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }

        .document-status-table thead {
            background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%);
            color: white;
        }

        .document-status-table th {
            padding: 14px 16px;
            text-align: left;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .document-status-table tbody tr {
            border-bottom: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .document-status-table tbody tr:hover {
            background: #f8f9fa;
        }

        .document-status-table td {
            padding: 14px 16px;
            font-size: 13px;
            color: #1a1a1a;
        }

        .document-status-table td:first-child {
            font-weight: 600;
            color: #1b5e20;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .status-badge.approved {
            background: #c8e6c9;
            color: #1b5e20;
        }

        .status-badge.pending {
            background: #fff9c4;
            color: #f57f17;
        }

        .status-badge.rejected {
            background: #ffcdd2;
            color: #d32f2f;
        }

        .rejection-notes-inline {
            background: #ffebee;
            border-left: 3px solid #d32f2f;
            padding: 8px 10px;
            border-radius: 4px;
            font-size: 12px;
            color: #c62828;
        }

        .doc-action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            margin-right: 6px;
            text-decoration: none;
        }

        .doc-action-btn.view {
            background: #1b5e20;
            color: white;
        }

        .doc-action-btn.view:hover {
            background: #2e7d32;
            transform: translateY(-1px);
        }

        .doc-action-btn i {
            font-size: 11px;
        }
    </style>
</body>

</html>