<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require "CYCLOAN_db.php";

if (!isset($_SESSION['email'])) {
    header("Location: index.php");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);

// Handle AJAX request for loan details
if (isset($_GET['action']) && $_GET['action'] === 'get_loan_details' && isset($_GET['application_id'])) {
    $application_id = (int) $_GET['application_id'];

    $query = "SELECT application_id, user_id AS applicant_name, amount_applied AS loan_amount, 
                     term_length AS duration_months, repayment_frequency, purpose, 
                     project_type, project_description, final_loan_amount, 
                     updated_at AS closed_date
              FROM loan_applications 
              WHERE application_id = ? AND status = 'closed' AND is_archived = 1";

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
        echo json_encode(['error' => 'Loan application not found or not archived']);
    }

    mysqli_stmt_close($stmt);
    mysqli_close($conn);
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
$profile_link = $adminRole === 'superadmin' ? 'profileSuperadmin.php' : ($adminRole === 'admin1' ? 'profileAdmin1.php' : 'profileAdmin2.php');

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

// Handle AJAX request for unarchiving a loan
if (isset($_GET['action']) && $_GET['action'] === 'unarchive_loan' && isset($_GET['application_id'])) {
    $application_id = (int) $_GET['application_id'];

    $query = "UPDATE loan_applications SET is_archived = 0 WHERE application_id = ? AND status = 'closed' AND is_archived = 1";
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['error' => 'Database error: ' . mysqli_error($conn)]);
        exit();
    }

    mysqli_stmt_bind_param($stmt, 'i', $application_id);
    mysqli_stmt_execute($stmt);

    if (mysqli_stmt_affected_rows($stmt) > 0) {
        echo json_encode(['success' => 'Loan application unarchived successfully']);
    } else {
        header('HTTP/1.1 404 Not Found');
        echo json_encode(['error' => 'Loan application not found, not closed, or not archived']);
    }

    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    exit();
}

// Fetch archived applications data
$query_archived_apps = "SELECT application_id, user_id AS applicant_name, 
                        amount_applied AS loan_amount, term_length AS duration_months, 
                        repayment_frequency, purpose, project_type, project_description, 
                        final_loan_amount, updated_at AS closed_date
                        FROM loan_applications
                        WHERE status = 'closed' AND is_archived = 1";
$result_archived_apps = mysqli_query($conn, $query_archived_apps);
$archived_applications = [];
if ($result_archived_apps) {
    while ($row = mysqli_fetch_assoc($result_archived_apps)) {
        $archived_applications[] = $row;
    }
    mysqli_free_result($result_archived_apps);
} else {
    $_SESSION['error'] = "Error fetching archived applications: " . mysqli_error($conn);
    error_log("Archived applications error: " . mysqli_error($conn), 3, 'errors.log');
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archived Loan Applications - CYCLOAN</title>
    <link rel="stylesheet" href="CSS/admin_dashboard.css">
    <link rel="stylesheet" href="CSS/admin_profile.css">
    <link rel="stylesheet" href="CSS/nav_active.css">
    <link rel="stylesheet" href="CSS/archived_records.css">
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
                    <i class="fa-solid fa-box-archive"></i>
                </div>
                <div class="header-text">
                    <h1>Archived Loan Records</h1>
                    <p>View and manage all archived loan applications and their payment histories.</p>
                </div>
            </div>
        </div>

        <div class="loan_applicants">
            <div class="scrollable-table">
                <table id="archived-records-table" class="loan-table">
                    <thead>
                        <tr>
                            <th>Application ID</th>
                            <th>Applicant ID</th>
                            <th>Loan Amount</th>
                            <th>Duration (Months)</th>
                            <th>Interest Rate (%)</th>
                            <th>Total Paid</th>
                            <th>Closed Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($archived_applications)): ?>
                            <tr>
                                <td colspan="8" class="no-records">
                                    <i class="fa-solid fa-circle-exclamation"></i> No archived loan applications found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($archived_applications as $app): ?>
                                <tr id="row-<?php echo (int) $app['application_id']; ?>">
                                    <td data-label="Application ID"><?php echo htmlspecialchars($app['application_id']); ?></td>
                                    <td data-label="Applicant ID"><?php echo htmlspecialchars($app['applicant_name']); ?></td>
                                    <td data-label="Loan Amount"><?php echo number_format($app['loan_amount'], 2); ?> PHP</td>
                                    <td data-label="Duration (Months)"><?php echo htmlspecialchars($app['duration_months']); ?>
                                    </td>
                                    <td data-label="Interest Rate (%)"><?php echo htmlspecialchars('N/A'); ?>%</td>
                                    <td data-label="Total Paid"><?php echo number_format(0, 2); ?> PHP</td>
                                    <td data-label="Closed Date">
                                        <?php echo htmlspecialchars($app['closed_date'] ? date('Y-m-d', strtotime($app['closed_date'])) : 'N/A'); ?>
                                    </td>
                                    <td data-label="Actions">
                                        <button class="action-btn view-btn"
                                            onclick="openViewLoanModal(<?php echo (int) $app['application_id']; ?>, event)"
                                            aria-label="View loan details for Applicant ID <?php echo htmlspecialchars($app['applicant_name']); ?>">
                                            View
                                        </button>
                                        <button class="action-btn unarchive-btn"
                                            onclick="openUnarchiveLoanModal(<?php echo (int) $app['application_id']; ?>, event)"
                                            aria-label="Unarchive loan application for Applicant ID <?php echo htmlspecialchars($app['applicant_name']); ?>">
                                            Unarchive
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- View Loan Modal -->
        <div id="viewLoanModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Loan Details</h2>
                    <span class="close" onclick="closeViewLoanModal()" aria-label="Close modal">&times;</span>
                </div>
                <div class="modal-body">
                    <div id="loanDetailsContent">
                        <p>Loading...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Unarchive Loan Confirmation Modal -->
        <div id="unarchiveLoanModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Confirm Unarchive</h2>
                    <span class="close" onclick="closeUnarchiveLoanModal()" aria-label="Close modal">&times;</span>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to unarchive this loan application?</p>
                </div>
                <div class="modal-footer">
                    <button class="action-btn confirm-btn" onclick="confirmUnarchiveLoan()">Confirm</button>
                    <button class="action-btn cancel-btn" onclick="closeUnarchiveLoanModal()">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentApplicationId = null;

        // Toggle sidebar and burger button
        const burgerElement = document.querySelector('.burger');
        if (burgerElement) {
            burgerElement.addEventListener('click', function () {
                this.classList.toggle('active');
                const nav = document.querySelector('nav');
                if (nav) {
                    nav.classList.toggle('active');
                }
            });
        }

        // Close sidebar when clicking a nav link
        const navLinks = document.querySelectorAll('nav a');
        if (navLinks.length > 0) {
            navLinks.forEach(link => {
                link.addEventListener('click', function () {
                    const nav = document.querySelector('nav');
                    if (nav) {
                        nav.classList.remove('active');
                    }
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

        window.onclick = function (event) {
            if (!event.target.closest(".profile-container")) {
                const dropdowns = document.getElementsByClassName("dropdown-menu");
                for (let i = 0; i < dropdowns.length; i++) {
                    if (dropdowns[i].classList.contains("show")) {
                        dropdowns[i].classList.remove("show");
                    }
                }
            }
            const viewModal = document.getElementById('viewLoanModal');
            const unarchiveModal = document.getElementById('unarchiveLoanModal');
            if (viewModal && event.target === viewModal) {
                viewModal.style.display = 'none';
            }
            if (unarchiveModal && event.target === unarchiveModal) {
                unarchiveModal.style.display = 'none';
                currentApplicationId = null;
            }
        };

        // Function to open the view loan modal
        function openViewLoanModal(applicationId, event) {
            event.preventDefault();
            const modal = document.getElementById('viewLoanModal');
            const loanDetailsContent = document.getElementById('loanDetailsContent');
            modal.style.display = 'block';
            loanDetailsContent.innerHTML = '<p>Loading...</p>';

            fetch(`archived_records.php?action=get_loan_details&application_id=${applicationId}`, {
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
                        loanDetailsContent.innerHTML = `<p class="error">${data.error}</p>`;
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
                    loanDetailsContent.innerHTML = '<p class="error">Failed to load loan details. Please try again.</p>';
                });
        }

        // Function to open the unarchive loan modal
        function openUnarchiveLoanModal(applicationId, event) {
            event.preventDefault();
            currentApplicationId = applicationId;
            const modal = document.getElementById('unarchiveLoanModal');
            modal.style.display = 'block';
        }

        // Function to confirm unarchiving a loan
        function confirmUnarchiveLoan() {
            if (!currentApplicationId) return;

            fetch(`archived_records.php?action=unarchive_loan&application_id=${currentApplicationId}`, {
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
                        const tableBody = document.querySelector('#archived-records-table tbody');
                        if (tableBody.children.length === 0) {
                            tableBody.innerHTML = '<tr><td colspan="8" class="no-records">No archived loan applications found.</td></tr>';
                        }
                        Toastify({
                            text: data.success,
                            duration: 3000,
                            gravity: "top",
                            position: "right",
                            backgroundColor: "var(--primary)",
                            className: "success"
                        }).showToast();
                    } else {
                        Toastify({
                            text: data.error,
                            duration: 3000,
                            gravity: "top",
                            position: "right",
                            backgroundColor: "var(--rejected)",
                            className: "error"
                        }).showToast();
                    }
                    closeUnarchiveLoanModal();
                })
                .catch(error => {
                    console.error('Error unarchiving loan:', error);
                    Toastify({
                        text: "Failed to unarchive loan. Please try again.",
                        duration: 3000,
                        gravity: "top",
                        position: "right",
                        backgroundColor: "var(--rejected)",
                        className: "error"
                    }).showToast();
                    closeUnarchiveLoanModal();
                });
        }

        // Function to close the view loan modal
        function closeViewLoanModal() {
            const modal = document.getElementById('viewLoanModal');
            modal.style.display = 'none';
        }

        // Function to close the unarchive loan modal
        function closeUnarchiveLoanModal() {
            const modal = document.getElementById('unarchiveLoanModal');
            modal.style.display = 'none';
            currentApplicationId = null;
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="JAVASCRIPT/Real-Time.js"></script>

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