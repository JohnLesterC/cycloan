<?php
session_start();
require "CYCLOAN_db.php";

if (!isset($_SESSION['email']) || !isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Set charset to match HTML
mysqli_set_charset($conn, "utf8mb4");

// Fetch profile image
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

// Fetch active loans for the logged-in user - exactly like user_dashboard.php
$stmt = mysqli_prepare($conn, "
    SELECT la.*, lt.type_name, la.application_id as formatted_application_id,
           la.loan_id as application_loan_id, l.loan_id as loans_loan_id, 
           l.total_principal, l.total_interest, l.total_paid, l.remaining_balance, 
           l.status as loan_status
    FROM loan_applications la
    JOIN loan_types lt ON la.loan_type_id = lt.loan_type_id
    LEFT JOIN loans l ON la.application_id = l.application_id
    WHERE la.user_id = ? AND la.status = 'Active'
    ORDER BY la.created_at DESC
");

$activeApplications = [];
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        // Fetch payment schedule for this loan if loan_id exists
        $row['payment_schedule'] = [];
        if (!empty($row['loans_loan_id'])) {
            $scheduleStmt = mysqli_prepare($conn, "
                SELECT payment_id, due_date, 
                       CAST(amount AS DECIMAL(12,2)) AS amount,
                       CAST(interest_amount AS DECIMAL(12,2)) AS interest_amount,
                       CAST(principal_amount AS DECIMAL(12,2)) AS principal_amount,
                       CAST(amount_paid AS DECIMAL(12,2)) AS amount_paid,
                       CAST(interest_paid AS DECIMAL(12,2)) AS interest_paid,
                       CAST(principal_paid AS DECIMAL(12,2)) AS principal_paid,
                       status
                FROM payment_schedules
                WHERE loan_id = ?
                ORDER BY due_date ASC
            ");
            if ($scheduleStmt) {
                mysqli_stmt_bind_param($scheduleStmt, "i", $row['loans_loan_id']);
                mysqli_stmt_execute($scheduleStmt);
                $scheduleResult = mysqli_stmt_get_result($scheduleStmt);
                while ($scheduleRow = mysqli_fetch_assoc($scheduleResult)) {
                    $row['payment_schedule'][] = $scheduleRow;
                }
                mysqli_stmt_close($scheduleStmt);
            } else {
                error_log('Payment schedule query error for loan_id ' . $row['loans_loan_id'] . ': ' . mysqli_error($conn), 3, 'errors.log');
            }
        }
        $activeApplications[] = $row;
    }
    mysqli_stmt_close($stmt);
} else {
    error_log('Active loans fetch error: ' . mysqli_error($conn), 3, 'errors.log');
}

// Get current page for active navigation
$current_page = basename($_SERVER['PHP_SELF']);

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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CYCLOAN - Active Records</title>
    <link rel="stylesheet" href="CSS/admin_dashboard.css">
    <link rel="stylesheet" href="CSS/user_dashboard.css">
    <link rel="stylesheet" href="CSS/user_active_record.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
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
                    <img src="<?= htmlspecialchars($profile_image) ?>" alt="Profile Image" class="profile">
                </div>
                <div class="dropdown-menu" id="dropdown">
                    <ul>
                        <li>
                            <a href="profile.php" class="<?= $current_page === 'profile.php' ? 'active' : '' ?>">
                                <img src="<?= htmlspecialchars($profile_image) ?>" alt="Profile Image"
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
            <a href="#" onclick="openCalculatorModal()"
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

        <h2><i class="fa-solid fa-user-check"></i> Active Loan Applications</h2>
        <p class="page-subtitle">View and manage your currently active loan accounts</p>

        <?php if (empty($activeApplications)): ?>
            <div class="no-applications">
                <i class="fa-solid fa-folder-open"></i>
                <div class="message-title">No Active Loans</div>
                <p class="message-text">You don't have any active loan applications at the moment.</p>
            </div>
        <?php else: ?>
            <?php foreach ($activeApplications as $activeLoan):
                $progress = 0;
                if ($activeLoan['total_principal'] > 0) {
                    $progress = ($activeLoan['total_paid'] / ($activeLoan['total_principal'] + $activeLoan['total_interest'])) * 100;
                }
                ?>
                <div class="active-loan-banner">
                    <div class="banner-header">
                        <div class="banner-icon">
                            <i class="fas fa-file-contract"></i>
                        </div>
                        <div class="banner-info">
                            <?php if ($activeLoan['status'] === 'Active'): ?>
                                <h2>Active Loan - <?= htmlspecialchars($activeLoan['type_name']) ?></h2>
                                <p>Loan ID:
                                    <?= htmlspecialchars($activeLoan['application_loan_id'] ?: 'N/A') ?>
                                </p>
                            <?php elseif ($activeLoan['status'] === 'Closed'): ?>
                                <h2>Closed Loan - <?= htmlspecialchars($activeLoan['type_name']) ?></h2>
                                <p>Loan ID:
                                    <?= htmlspecialchars($activeLoan['application_loan_id'] ?: 'N/A') ?>
                                </p>
                            <?php else: ?>
                                <h2>Loan Application - <?= htmlspecialchars($activeLoan['type_name']) ?></h2>
                                <p>Application ID:
                                    <?= htmlspecialchars($activeLoan['formatted_application_id'] ?: 'N/A') ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="banner-status">
                            <span class="status-badge badge-<?= strtolower($activeLoan['status']) ?>">
                                <?= htmlspecialchars($activeLoan['status']) ?>
                            </span>
                        </div>
                    </div>

                    <div class="loan-metrics-grid">
                        <div class="metric-card">
                            <div class="metric-icon"
                                style="background: linear-gradient(135deg, #1b5e20 0%, #fbc02d 50%, #2e7d32 100%);">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <div class="metric-content">
                                <span class="metric-label">Loan Amount</span>
                                <span
                                    class="metric-value">₱<?= number_format($activeLoan['final_loan_amount'] ?: $activeLoan['amount_applied'], 2) ?></span>
                            </div>
                        </div>

                        <?php if ($activeLoan['loan_id']): ?>
                            <div class="metric-card">
                                <div class="metric-icon"
                                    style="background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 70%, #fbc02d 100%);">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <div class="metric-content">
                                    <span class="metric-label">Total Paid</span>
                                    <span class="metric-value success">₱<?= number_format($activeLoan['total_paid'], 2) ?></span>
                                </div>
                            </div>

                            <div class="metric-card">
                                <div class="metric-icon"
                                    style="background: linear-gradient(135deg, #1b5e20 0%, #fbc02d 40%, #2e7d32 100%);">
                                    <i class="fas fa-wallet"></i>
                                </div>
                                <div class="metric-content">
                                    <span class="metric-label">Remaining Balance</span>
                                    <span
                                        class="metric-value warning">₱<?= number_format($activeLoan['remaining_balance'], 2) ?></span>
                                </div>
                            </div>

                            <div class="metric-card">
                                <div class="metric-icon"
                                    style="background: linear-gradient(135deg, #1b5e20 0%, #fbc02d 40%, #2e7d32 100%);">
                                    <i class="fas fa-percentage"></i>
                                </div>
                                <div class="metric-content">
                                    <span class="metric-label">Progress</span>
                                    <span class="metric-value"><?= number_format($progress, 1) ?>%</span>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="metric-card pending-setup">
                                <div class="metric-icon" style="background: linear-gradient(135deg, #ffeaa7 0%, #fdcb6e 100%);">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="metric-content">
                                    <span class="metric-label">Status</span>
                                    <span class="metric-value pending">Awaiting Setup</span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($activeLoan['loan_id']): ?>
                        <!-- Payment Progress Bar -->
                        <div class="payment-progress">
                            <div class="progress-header">
                                <span>Payment Progress</span>
                                <span class="progress-percent"><?= number_format($progress, 1) ?>%</span>
                            </div>
                            <div class="progress-bar-container">
                                <div class="progress-bar-fill" style="width: <?= min($progress, 100) ?>%"></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="banner-actions">
                        <a href="user_history_activity.php" class="action-btn secondary">
                            <i class="fas fa-history"></i> View History
                        </a>
                    </div>
                </div>

                <!-- Payment Schedule Table -->
                <?php if ($activeLoan['loan_id']): ?>
                    <?php
                    // Debug: Check what we have
                    echo "<!-- DEBUG: loan_id = " . $activeLoan['loan_id'] . " -->";
                    echo "<!-- DEBUG: payment_schedule count = " . count($activeLoan['payment_schedule']) . " -->";
                    echo "<!-- DEBUG: payment_schedule empty? " . (empty($activeLoan['payment_schedule']) ? 'YES' : 'NO') . " -->";
                    ?>
                    <?php if (empty($activeLoan['payment_schedule'])): ?>
                        <div class="payment-schedule-notice">
                            <div class="notice-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="notice-content">
                                <h4>Payment Schedule Pending</h4>
                                <p>Your payment schedule is being prepared by our admin team. It will be available here once
                                    the loan setup is complete.</p>
                                <small style="color: #999; display: block; margin-top: 8px;">
                                    DEBUG: Loan ID = <?= $activeLoan['loan_id'] ?>,
                                    Schedule Count = <?= count($activeLoan['payment_schedule']) ?>
                                </small>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="payment-schedule-section">
                            <h3><i class="fas fa-calendar-alt"></i> Payment Schedule</h3>
                            <div class="scrollable-table">
                                <table class="payment-schedule-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Due Date</th>
                                            <th>Amount Due</th>
                                            <th>Interest Due</th>
                                            <th>Principal Due</th>
                                            <th>Amount Paid</th>
                                            <th>Interest Paid</th>
                                            <th>Principal Paid</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $paymentNum = 1;
                                        foreach ($activeLoan['payment_schedule'] as $payment):
                                            ?>
                                            <tr>
                                                <td><?= $paymentNum++ ?></td>
                                                <td><?= date('M d, Y', strtotime($payment['due_date'])) ?></td>
                                                <td>₱<?= number_format($payment['amount'], 2) ?></td>
                                                <td>₱<?= number_format($payment['interest_amount'], 2) ?></td>
                                                <td>₱<?= number_format($payment['principal_amount'], 2) ?></td>
                                                <td>₱<?= number_format($payment['amount_paid'], 2) ?></td>
                                                <td>₱<?= number_format($payment['interest_paid'], 2) ?></td>
                                                <td>₱<?= number_format($payment['principal_paid'], 2) ?></td>
                                                <td><span
                                                        class="status-badge badge-<?= strtolower($payment['status']) ?>"><?= ucfirst($payment['status']) ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>

        <div id="viewLoanModal" class="modal" role="dialog" aria-labelledby="viewLoanModalLabel">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="viewLoanModalLabel">Loan Details</h2>
                    <span class="close" onclick="closeViewLoanModal()" role="button" aria-label="Close modal">×</span>
                </div>
                <div class="modal-body">
                    <div id="loanDetailsContent">
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

    <script src="JAVASCRIPT/Real-Time.js"></script>
    <script src="JAVASCRIPT/user_active_record.js"></script>

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
<?php
mysqli_close($conn);
?>