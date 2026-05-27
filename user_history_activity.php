<?php
session_start();
require "CYCLOAN_db.php";

if (!isset($_SESSION['email']) || !isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Ensure global $conn is a valid MySQLi object
global $conn;
if (!($conn instanceof mysqli)) {
    error_log('Connection failed: MySQLi connection not initialized.', 3, 'errors.log');
    $_SESSION['error'] = 'Connection failed. Please try again later.';
    header('Location: user_history_activity.php');
    exit;
}

// Fetch user details and profile image
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

// Fetch login attempts
try {
    $stmt = $conn->prepare("SELECT email, success, attempt FROM logattempts WHERE email = ? ORDER BY attempt DESC LIMIT 50");
    if ($stmt === false) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("s", $_SESSION['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    $loginAttempts = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    error_log('Login attempts fetch error: ' . $e->getMessage(), 3, 'errors.log');
    $loginAttempts = [];
}

// Fetch application history
try {
    $query = "
        SELECT la.application_id as formatted_application_id, la.loan_id as application_loan_id, 
               la.created_at, la.status, la.purpose, la.amount_applied,
               la.final_loan_amount, lt.type_name, l.loan_id as loans_loan_id
        FROM loan_applications la
        JOIN loan_types lt ON la.loan_type_id = lt.loan_type_id
        LEFT JOIN loans l ON la.application_id = l.application_id
        WHERE la.user_id = ?
        ORDER BY la.created_at DESC
        LIMIT 50
    ";
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $applicationHistory = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    error_log('Application history fetch error: ' . $e->getMessage(), 3, 'errors.log');
    $applicationHistory = [];
}

// Fetch payment history
try {
    $query = "
        SELECT ph.payment_history_id, ph.payment_date, ph.amount_paid, ph.interest_paid, 
               ph.principal_paid, ph.late_fee_paid, ph.payment_type, ph.invoice_number,
               l.loan_id, la.application_id, lt.type_name
        FROM payment_history ph
        JOIN loans l ON ph.loan_id = l.loan_id
        JOIN loan_applications la ON l.application_id = la.application_id
        JOIN loan_types lt ON la.loan_type_id = lt.loan_type_id
        WHERE la.user_id = ?
        ORDER BY ph.payment_date DESC
        LIMIT 100
    ";
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $paymentHistory = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    error_log('Payment history fetch error: ' . $e->getMessage(), 3, 'errors.log');
    $paymentHistory = [];
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

mysqli_close($conn);

// Get current page for active navigation
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CYCLOAN - Activity History</title>
    <link rel="stylesheet" href="CSS/admin_dashboard.css">
    <link rel="stylesheet" href="CSS/user_dashboard.css">
    <link rel="stylesheet" href="CSS/user_history_activity.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

</head>

<body>
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
                            <a class="logout" href="index.php"><i class="fa-solid fa-sign-out"></i>
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

        <h2><i class="fa-solid fa-clock-rotate-left"></i> Activity History</h2>
        <p class="page-subtitle">Track your account activity, loan applications, and payment history</p>

        <!-- Tab Navigation -->
        <div class="history-tabs">
            <button class="tab-btn active" onclick="switchTab('login')">
                <i class="fas fa-sign-in-alt"></i> Login History
            </button>
            <button class="tab-btn" onclick="switchTab('applications')">
                <i class="fas fa-file-alt"></i> Loan Applications
            </button>
            <button class="tab-btn" onclick="switchTab('payments')">
                <i class="fas fa-credit-card"></i> Payment History
            </button>
        </div>

        <!-- Login Attempts Tab -->
        <div class="history-container tab-content active" id="loginTab">
            <div class="section-header">
                <h3><i class="fas fa-sign-in-alt"></i> Login Attempts</h3>
                <span class="record-count"><?= count($loginAttempts) ?> records</span>
            </div>
            <?php if (empty($loginAttempts)): ?>
                <div class="no-data">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <p>No login attempts found.</p>
                </div>
            <?php else: ?>
                <div class="scrollable-table">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Attempt Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($loginAttempts as $attempt): ?>
                                <tr>
                                    <td data-label="Email"><?php echo htmlspecialchars($attempt['email']); ?></td>
                                    <td data-label="Status">
                                        <span
                                            class="status-badge <?php echo $attempt['success'] ? 'badge-success' : 'badge-failed'; ?>">
                                            <i class="fas fa-<?= $attempt['success'] ? 'check-circle' : 'times-circle' ?>"></i>
                                            <?php echo $attempt['success'] ? 'Successful' : 'Failed'; ?>
                                        </span>
                                    </td>
                                    <td data-label="Attempt Time">
                                        <i class="far fa-clock"></i>
                                        <?php echo date('M d, Y h:i A', strtotime($attempt['attempt'])); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Loan Application History Tab -->
        <div class="history-container tab-content" id="applicationsTab">
            <div class="section-header">
                <h3><i class="fas fa-file-alt"></i> Loan Application History</h3>
                <span class="record-count"><?= count($applicationHistory) ?> records</span>
            </div>
            <?php if (empty($applicationHistory)): ?>
                <div class="no-data">
                    <i class="fa-solid fa-folder-open"></i>
                    <p>No loan applications found.</p>
                </div>
            <?php else: ?>
                <div class="scrollable-table">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Loan Type</th>
                                <th>Amount Applied</th>
                                <th>Final Amount</th>
                                <th>Purpose</th>
                                <th>Status</th>
                                <th>Submitted On</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applicationHistory as $app): ?>
                                <tr>
                                    <td data-label="ID">
                                        <?php if ($app['status'] === 'Active' || $app['status'] === 'Closed'): ?>
                                            <strong>Loan ID:
                                                <?php echo htmlspecialchars($app['application_loan_id'] ?: 'N/A'); ?></strong>
                                        <?php else: ?>
                                            <strong>App ID:
                                                <?php echo htmlspecialchars($app['formatted_application_id'] ?: 'N/A'); ?></strong>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Loan Type"><?php echo htmlspecialchars($app['type_name']); ?></td>
                                    <td data-label="Amount Applied">
                                        ₱<?= number_format($app['amount_applied'], 2) ?>
                                    </td>
                                    <td data-label="Final Amount">
                                        <?= $app['final_loan_amount'] ? '₱' . number_format($app['final_loan_amount'], 2) : '<span class="text-muted">Not set</span>' ?>
                                    </td>
                                    <td data-label="Purpose"><?php echo htmlspecialchars($app['purpose']); ?></td>
                                    <td data-label="Status">
                                        <span class="status-badge badge-<?php echo strtolower($app['status']); ?>">
                                            <?php echo htmlspecialchars($app['status']); ?>
                                        </span>
                                    </td>
                                    <td data-label="Submitted On">
                                        <i class="far fa-calendar"></i>
                                        <?php echo date('M d, Y', strtotime($app['created_at'])); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Payment History Tab -->
        <div class="history-container tab-content" id="paymentsTab">
            <div class="section-header">
                <h3><i class="fas fa-credit-card"></i> Payment History</h3>
                <span class="record-count"><?= count($paymentHistory) ?> records</span>
            </div>
            <?php if (empty($paymentHistory)): ?>
                <div class="no-data">
                    <i class="fa-solid fa-receipt"></i>
                    <p>No payment history found.</p>
                    <small>Payments will appear here once you start making loan payments.</small>
                </div>
            <?php else: ?>
                <?php
                $totalPaid = array_sum(array_column($paymentHistory, 'amount_paid'));
                $totalInterest = array_sum(array_column($paymentHistory, 'interest_paid'));
                $totalPrincipal = array_sum(array_column($paymentHistory, 'principal_paid'));
                ?>

                <!-- Payment Summary Cards -->
                <div class="payment-summary">
                    <div class="summary-card">
                        <div class="summary-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="summary-content">
                            <span class="summary-label">Total Paid</span>
                            <span class="summary-value">₱<?= number_format($totalPaid, 2) ?></span>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="summary-content">
                            <span class="summary-label">Total Interest</span>
                            <span class="summary-value">₱<?= number_format($totalInterest, 2) ?></span>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon" style="background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <div class="summary-content">
                            <span class="summary-label">Total Principal</span>
                            <span class="summary-value">₱<?= number_format($totalPrincipal, 2) ?></span>
                        </div>
                    </div>
                </div>

                <div class="scrollable-table">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Loan ID</th>
                                <th>Loan Type</th>
                                <th>Payment Date</th>
                                <th>Amount Paid</th>
                                <th>Principal</th>
                                <th>Interest</th>
                                <th>Late Fee</th>
                                <th>Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paymentHistory as $payment): ?>
                                <tr>
                                    <td data-label="Invoice #">
                                        <strong><?= $payment['invoice_number'] ?: 'N/A' ?></strong>
                                    </td>
                                    <td data-label="Loan ID">
                                        <span class="loan-id-badge">#<?= $payment['loan_id'] ?></span>
                                    </td>
                                    <td data-label="Loan Type"><?= htmlspecialchars($payment['type_name']) ?></td>
                                    <td data-label="Payment Date">
                                        <i class="far fa-calendar-check"></i>
                                        <?= date('M d, Y', strtotime($payment['payment_date'])) ?>
                                    </td>
                                    <td data-label="Amount Paid">
                                        <strong
                                            class="amount-highlight">₱<?= number_format($payment['amount_paid'], 2) ?></strong>
                                    </td>
                                    <td data-label="Principal">₱<?= number_format($payment['principal_paid'], 2) ?></td>
                                    <td data-label="Interest">₱<?= number_format($payment['interest_paid'], 2) ?></td>
                                    <td data-label="Late Fee">
                                        <?= $payment['late_fee_paid'] > 0 ? '₱' . number_format($payment['late_fee_paid'], 2) : '-' ?>
                                    </td>
                                    <td data-label="Type">
                                        <span class="payment-type-badge badge-<?= strtolower($payment['payment_type']) ?>">
                                            <?= ucfirst($payment['payment_type']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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
                                    <label for="termLength">Term Length (Months) <span
                                            style="color: red;">*</span></label>
                                    <select id="termLength" name="termLength" required
                                        onchange="updateRepaymentOptions()">
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

    <script src="JAVASCRIPT/user_history_activity.js"></script>
    <script src="JAVASCRIPT/Real-Time.js"></script>

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
    </style>
</body>

</html>