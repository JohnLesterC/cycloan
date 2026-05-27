<?php
session_start();
require "CYCLOAN_db.php";

if (!isset($_SESSION['email']) || !isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);

// Fetch profile image
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
    $profile_image = 'assets/default.jpg';
}

// Handle AJAX request for loan details
if (isset($_GET['action']) && $_GET['action'] === 'get_loan_details' && isset($_GET['application_id'])) {
    header('Content-Type: application/json');

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
    mysqli_stmt_close($stmt);

    if ($loan) {
        // Fetch documents
        $query = "SELECT dt.document_name, d.file_path FROM documents d JOIN document_types dt ON d.document_type_id = dt.document_type_id WHERE d.application_id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "s", $applicationId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $documents = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);

        // Fetch remarks
        $query = "SELECT remarks, created_at FROM remarks WHERE application_id = ? ORDER BY created_at DESC";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "s", $applicationId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $remarks = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);

        echo json_encode([
            'success' => true,
            'loan' => $loan,
            'documents' => $documents,
            'remarks' => $remarks
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Loan application not found.']);
    }
    mysqli_close($conn);
    exit;
}

// Handle AJAX request for current interest rate
if (isset($_GET['action']) && $_GET['action'] === 'get_current_interest_rate') {
    header('Content-Type: application/json');
    try {
        $stmt = $conn->prepare("SELECT interest_rate FROM interest_rates WHERE term_length = '12' ORDER BY updated_at DESC LIMIT 1");
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $current_rate = $row ? floatval($row['interest_rate']) : 6.00;
        $stmt->close();

        echo json_encode(['success' => true, 'interest_rate' => $current_rate]);
    } catch (Exception $e) {
        error_log("Interest rate fetch error: " . $e->getMessage(), 3, 'errors.log');
        echo json_encode(['success' => false, 'message' => 'Error fetching interest rate']);
    }
    mysqli_close($conn);
    exit;
}

// Fetch CLOSED loan applications ONLY (Fixed query)
$query = "
    SELECT la.application_id, la.loan_id, u.first_name, u.last_name, lt.type_name, 
           la.amount_applied, la.status, la.pre_approval_status, 
           la.credit_investigation_status, la.created_at, la.term_length, la.repayment_frequency
    FROM loan_applications la
    JOIN users1 u ON la.user_id = u.id
    JOIN loan_types lt ON la.loan_type_id = lt.loan_type_id
    WHERE la.user_id = ? AND la.status = 'Closed'
    ORDER BY la.created_at DESC
";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$closedApplications = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);
mysqli_close($conn);

// Get current page for active navigation
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CYCLOAN - Closed Records</title>
    <link rel="stylesheet" href="CSS/admin_dashboard.css">
    <link rel="stylesheet" href="CSS/user_dashboard.css">
    <link rel="stylesheet" href="CSS/user_closed_records.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
</head>

<body>
    <!-- HEADER -->
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
                            <a href="profile.php">
                                <img src="<?php echo htmlspecialchars($profile_image); ?>" alt="Profile"
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

    <!-- NAVIGATION -->
    <div class="nav-container">
        <button class="burger" aria-label="Toggle menu">
            <span></span><span></span><span></span>
        </button>
        <nav>
            <img src="IMAGE/Main-Logo.png" alt="Logo" class="sidebar-logo">
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
            <a href="#" onclick="openCalculatorModal(); return false;">
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

        <h2>Closed Loan Applications</h2>

        <?php if (empty($closedApplications)): ?>
            <p class="no-applications">
                <i class="fa-solid fa-circle-exclamation"></i>No closed loan applications found.
            </p>
        <?php else: ?>
            <div class="scrollable-table">
                <table class="loan-table">
                    <thead>
                        <tr>
                            <th>Applicant Name</th>
                            <th>Loan Type</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Pre-Approval</th>
                            <th>Credit Check</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($closedApplications as $application): ?>
                            <tr>
                                <td data-label="Applicant Name">
                                    <?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?>
                                </td>
                                <td data-label="Loan Type"><?php echo htmlspecialchars($application['type_name']); ?></td>
                                <td data-label="Amount">₱<?php echo number_format($application['amount_applied'], 2); ?></td>
                                <td data-label="Status"><span
                                        class="status-badge completed"><?php echo htmlspecialchars($application['status']); ?></span>
                                </td>
                                <td data-label="Pre-Approval"><span
                                        class="status-badge <?php echo strtolower($application['pre_approval_status']); ?>"><?php echo htmlspecialchars($application['pre_approval_status']); ?></span>
                                </td>
                                <td data-label="Credit Check"><span
                                        class="status-badge <?php echo strtolower($application['credit_investigation_status']); ?>"><?php echo htmlspecialchars($application['credit_investigation_status']); ?></span>
                                </td>
                                <td data-label="Date"><?php echo date('M j, Y', strtotime($application['created_at'])); ?></td>
                                <td data-label="Action">
                                    <button class="action-btn view-btn"
                                        onclick="openLoanDetailsModal('<?php echo htmlspecialchars($application['loan_id'] ?: $application['application_id'], ENT_QUOTES); ?>')">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- LOAN DETAILS MODAL -->
    <div id="loanDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Loan Application Details</h2>
                <span class="close" onclick="closeLoanDetailsModal()">×</span>
            </div>
            <div class="modal-body">
                <div id="loanDetailsContent" class="application-details">Loading...</div>
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

    <script src="JAVASCRIPT/Real-Time.js"></script>
    <script src="JAVASCRIPT/user_closed_records.js"></script>

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