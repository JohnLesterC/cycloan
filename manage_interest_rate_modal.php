<?php
// ========== INTEREST RATE MANAGEMENT MODAL - PHP BACKEND ==========
// Handle AJAX requests for the interest rate modal

// Handle getting current rates for all terms
if (isset($_GET['action']) && $_GET['action'] === 'get_current_rates') {
    header('Content-Type: application/json');

    try {
        if (!isset($conn) || !$conn) {
            throw new Exception('Database connection failed');
        }

        $termLengths = ['6', '12', '18', '24', '36'];
        $rates = [];

        foreach ($termLengths as $term) {
            $sql = "SELECT id, interest_rate, term_length, updated_at, updated_by 
                    FROM interest_rates 
                    WHERE term_length = ? 
                    ORDER BY updated_at DESC LIMIT 1";

            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . mysqli_error($conn));
            }

            mysqli_stmt_bind_param($stmt, "s", $term);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Execute failed: " . mysqli_error($conn));
            }

            $result = mysqli_stmt_get_result($stmt);
            if ($result && $row = mysqli_fetch_assoc($result)) {
                $rates[$term] = $row;
            } else {
                // Default rate if not found
                $rates[$term] = [
                    'id' => null,
                    'interest_rate' => '6.00',
                    'term_length' => $term,
                    'updated_at' => date('Y-m-d H:i:s'),
                    'updated_by' => 'System Default'
                ];
            }
            mysqli_stmt_close($stmt);
        }

        echo json_encode(['success' => true, 'rates' => $rates]);
    } catch (Exception $e) {
        error_log("Get Current Rates Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Handle updating interest rate for a specific term length
if (isset($_POST['action']) && $_POST['action'] === 'update_interest_rate') {
    header('Content-Type: application/json');

    try {
        if (!isset($adminRole) || $adminRole !== 'superadmin') {
            throw new Exception('Unauthorized access. Only superadmins can update interest rates.');
        }

        $newInterestRate = floatval($_POST['interest_rate'] ?? 0);
        $termLength = sanitize_input($_POST['term_length'] ?? '12');
        $updatedBy = $_SESSION['email'] ?? 'Unknown';

        // Validation
        if ($newInterestRate <= 0 || $newInterestRate > 100) {
            throw new Exception('Interest rate must be between 0.01% and 100%.');
        }

        if (!in_array($termLength, ['6', '12', '18', '24', '36'])) {
            throw new Exception('Invalid term length. Must be 6, 12, 18, 24, or 36 months.');
        }

        if (!isset($conn) || !$conn) {
            throw new Exception('Database connection failed');
        }

        // Insert new interest rate into interest_rates table
        $sql = "INSERT INTO interest_rates (interest_rate, term_length, updated_at, updated_by) 
                VALUES (?, ?, NOW(), ?)";

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . mysqli_error($conn));
        }

        $rateStr = (string) $newInterestRate;
        mysqli_stmt_bind_param($stmt, "dss", $newInterestRate, $termLength, $updatedBy);

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Execute failed: " . mysqli_error($conn));
        }

        mysqli_stmt_close($stmt);

        echo json_encode([
            'success' => true,
            'message' => "Interest rate for $termLength months updated successfully to " . number_format($newInterestRate, 2) . '%.'
        ]);
    } catch (Exception $e) {
        error_log("Interest Rate Update Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle getting interest rate history
if (isset($_GET['action']) && $_GET['action'] === 'get_interest_rate_history') {
    header('Content-Type: application/json');

    try {
        if (!isset($conn) || !$conn) {
            throw new Exception('Database connection failed');
        }

        $termLength = sanitize_input($_GET['term_length'] ?? '');

        if ($termLength && !in_array($termLength, ['6', '12', '18', '24', '36'])) {
            throw new Exception('Invalid term length.');
        }

        if ($termLength) {
            // Get history for specific term
            $sql = "SELECT id, interest_rate, term_length, updated_at, updated_by 
                    FROM interest_rates 
                    WHERE term_length = ? 
                    ORDER BY updated_at DESC LIMIT 50";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "s", $termLength);
        } else {
            // Get all history
            $sql = "SELECT id, interest_rate, term_length, updated_at, updated_by 
                    FROM interest_rates 
                    ORDER BY updated_at DESC LIMIT 100";
            $stmt = mysqli_prepare($conn, $sql);
        }

        if (!$stmt) {
            throw new Exception("Prepare failed: " . mysqli_error($conn));
        }

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Execute failed: " . mysqli_error($conn));
        }

        $result = mysqli_stmt_get_result($stmt);
        $history = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);

        echo json_encode(['success' => true, 'history' => $history]);
    } catch (Exception $e) {
        error_log("Interest Rate History Fetch Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Sanitize input function
function sanitize_input($input)
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
?>

<!-- ========== INTEREST RATE MANAGEMENT MODAL - HTML ========== -->
<div id="manageInterestRateModal" class="modal fade-in">
    <div class="modal-content interest-rate-modal-content">
        <div class="modal-header">
            <div class="modal-title-wrapper">
                <div class="modal-title-badge">
                    <i class="fas fa-percentage"></i>
                    <span>Interest Rate Management</span>
                </div>
                <p class="modal-subtitle">View current loan interest rates</p>
            </div>
            <span class="close" onclick="closeManageInterestRateModal()">&times;</span>
        </div>

        <div class="modal-body interest-rate-modal-body">
            <!-- Current Interest Rates Cards -->
            <div class="interest-rates-cards-container" id="interestRatesContainer">
                <div class="loading-spinner">Loading interest rates...</div>
            </div>

            <!-- Update Form -->
            <div class="interest-rate-update-section">
                <h3>Update Interest Rate</h3>
                <form id="interestRateForm" class="interest-rate-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="termLengthSelect">Term Length (Months):</label>
                            <select id="termLengthSelect" required>
                                <option value="">-- Select Term Length --</option>
                                <option value="6">6 Months</option>
                                <option value="12">12 Months</option>
                                <option value="18">18 Months</option>
                                <option value="24">24 Months</option>
                                <option value="36">36 Months</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="newInterestRate">New Interest Rate (% per annum):</label>
                            <input type="number" id="newInterestRate" required placeholder="Enter interest rate"
                                min="0.01" max="100" step="0.01">
                        </div>
                    </div>
                    <button type="button" class="calculate-btn" onclick="updateInterestRate()">Update Interest
                        Rate</button>
                </form>
                <div id="interestRateMessage" class="result"></div>
            </div>

            <!-- Change History -->
            <div class="interest-rate-history-section">
                <div class="history-header">
                    <h3><i class="fas fa-history"></i> Change History</h3>
                    <select id="historyTermFilter" onchange="loadHistoryForTerm(this.value)">
                        <option value="">All Terms</option>
                        <option value="6">6 Months</option>
                        <option value="12">12 Months</option>
                        <option value="18">18 Months</option>
                        <option value="24">24 Months</option>
                        <option value="36">36 Months</option>
                    </select>
                </div>
                <table class="loan-table interest-rate-table">
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
                            <td colspan="5">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ========== INTEREST RATE MODAL - CSS ========== -->
<style>
    .interest-rate-modal-content {
        width: 90%;
        max-width: 1000px;
        max-height: 90vh;
        overflow-y: auto;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: 25px;
        border-bottom: 1px solid #e0e0e0;
        background: linear-gradient(135deg, #1e5c3d 0%, #2a7f56 100%);
        color: white;
        border-radius: 12px 12px 0 0;
    }

    .modal-title-wrapper {
        flex: 1;
    }

    .modal-title-badge {
        display: inline-flex;
        align-items: center;
        gap: 12px;
        background-color: #ffc107;
        color: #333;
        padding: 10px 20px;
        border-radius: 25px;
        font-size: 18px;
        font-weight: 600;
        margin-bottom: 8px;
    }

    .modal-title-badge i {
        font-size: 22px;
    }

    .modal-subtitle {
        margin: 0;
        font-size: 14px;
        opacity: 0.9;
        color: rgba(255, 255, 255, 0.9);
    }

    .interest-rate-modal-body {
        padding: 25px;
        background: #f5f5f5;
    }

    /* Interest Rate Cards */
    .interest-rates-cards-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 15px;
        margin-bottom: 30px;
    }

    .interest-rate-card {
        background: white;
        border-radius: 8px;
        padding: 20px;
        text-align: center;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        border-left: 4px solid #1e5c3d;
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .interest-rate-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
    }

    .interest-rate-card-rate {
        font-size: 28px;
        font-weight: 700;
        color: #1e5c3d;
        margin-bottom: 8px;
    }

    .interest-rate-card-term {
        font-size: 13px;
        color: #666;
        font-weight: 500;
        margin-bottom: 8px;
    }

    .interest-rate-card-date {
        font-size: 11px;
        color: #999;
    }

    /* Update Form */
    .interest-rate-update-section {
        background: white;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 25px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }

    .interest-rate-update-section h3 {
        margin-top: 0;
        margin-bottom: 15px;
        color: #1e5c3d;
        font-size: 16px;
    }

    .interest-rate-form .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        margin-bottom: 15px;
    }

    .interest-rate-form .form-group {
        display: flex;
        flex-direction: column;
    }

    .interest-rate-form label {
        margin-bottom: 6px;
        font-weight: 500;
        color: #333;
        font-size: 13px;
    }

    .interest-rate-form input,
    .interest-rate-form select {
        padding: 10px 12px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 13px;
        font-family: inherit;
    }

    .interest-rate-form input:focus,
    .interest-rate-form select:focus {
        outline: none;
        border-color: #1e5c3d;
        box-shadow: 0 0 0 3px rgba(30, 92, 61, 0.1);
    }

    /* History Section */
    .interest-rate-history-section {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }

    .history-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        gap: 15px;
    }

    .history-header h3 {
        margin: 0;
        color: #1e5c3d;
        font-size: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .history-header select {
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 13px;
        min-width: 150px;
    }

    /* Table Styling */
    .interest-rate-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }

    .interest-rate-table thead {
        background-color: #1e5c3d;
        color: white;
    }

    .interest-rate-table th {
        padding: 12px;
        text-align: left;
        font-weight: 600;
    }

    .interest-rate-table td {
        padding: 12px;
        border-bottom: 1px solid #e0e0e0;
    }

    .interest-rate-table tbody tr:hover {
        background-color: #f9f9f9;
    }

    .interest-rate-table tbody tr:nth-child(even) {
        background-color: #f5f5f5;
    }

    /* Messages */
    .message {
        padding: 12px 15px;
        margin-top: 12px;
        border-radius: 5px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 13px;
    }

    .message.success {
        background-color: #dff0d8;
        color: #3c763d;
        border: 1px solid #d6e9c6;
    }

    .message.error {
        background-color: #f2dede;
        color: #a94442;
        border: 1px solid #ebccd1;
    }

    /* Loading Spinner */
    .loading-spinner {
        text-align: center;
        padding: 30px;
        color: #999;
    }

    .calculate-btn {
        background-color: #1e5c3d;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-weight: 500;
        font-size: 13px;
        transition: background-color 0.2s;
    }

    .calculate-btn:hover {
        background-color: #164d31;
    }

    .calculate-btn:active {
        transform: scale(0.98);
    }

    @media (max-width: 768px) {
        .interest-rates-cards-container {
            grid-template-columns: repeat(2, 1fr);
        }

        .interest-rate-form .form-row {
            grid-template-columns: 1fr;
        }

        .history-header {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>

<!-- ========== INTEREST RATE MODAL - JAVASCRIPT ========== -->
<script>
    function openManageInterestRateModal() {
        const modal = document.getElementById("manageInterestRateModal");
        const container = document.getElementById("interestRatesContainer");
        const historyTable = document.getElementById("interestRateHistoryTable");

        // Clear previous content
        document.getElementById("interestRateMessage").innerHTML = "";
        historyTable.innerHTML = '<tr><td colspan="5">Loading...</td></tr>';

        modal.style.display = "block";
        setTimeout(() => modal.classList.add("show"), 10);

        // Load current rates
        loadCurrentRates();

        // Load history
        loadHistoryForTerm('');
    }

    function closeManageInterestRateModal() {
        const modal = document.getElementById("manageInterestRateModal");
        modal.classList.remove("show");
        setTimeout(() => modal.style.display = "none", 300);
    }

    function loadCurrentRates() {
        const container = document.getElementById("interestRatesContainer");

        fetch('manage_interest_rate_modal.php?action=get_current_rates', { cache: 'no-store' })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const rates = data.rates;
                    const termLabels = {
                        '6': '6 Months',
                        '12': '12 Months',
                        '18': '18 Months',
                        '24': '24 Months',
                        '36': '36 Months'
                    };

                    let html = '';
                    for (const [term, rate] of Object.entries(rates)) {
                        const dateObj = new Date(rate.updated_at);
                        const dateStr = dateObj.toLocaleDateString('en-US', { year: 'numeric', month: '2-digit', day: '2-digit' });

                        html += `
                        <div class="interest-rate-card">
                            <div class="interest-rate-card-rate">${parseFloat(rate.interest_rate).toFixed(2)}%</div>
                            <div class="interest-rate-card-term">${termLabels[term]}</div>
                            <div class="interest-rate-card-date">Updated: ${dateStr}</div>
                        </div>
                    `;
                    }

                    container.innerHTML = html;
                } else {
                    container.innerHTML = `<div style="grid-column: 1/-1; text-align: center; color: #d32f2f;">Error: ${data.message}</div>`;
                }
            })
            .catch(error => {
                container.innerHTML = `<div style="grid-column: 1/-1; text-align: center; color: #d32f2f;">Error loading rates: ${error.message}</div>`;
                console.error('Fetch error:', error);
            });
    }

    function loadHistoryForTerm(term = '') {
        const historyTable = document.getElementById("interestRateHistoryTable");

        let url = 'manage_interest_rate_modal.php?action=get_interest_rate_history';
        if (term) {
            url += '&term_length=' + encodeURIComponent(term);
        }

        fetch(url, { cache: 'no-store' })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const history = data.history;
                    if (history.length > 0) {
                        let html = history.map((item, index) => {
                            const dateObj = new Date(item.updated_at);
                            const dateStr = dateObj.toLocaleString('en-US', {
                                year: 'numeric',
                                month: '2-digit',
                                day: '2-digit',
                                hour: '2-digit',
                                minute: '2-digit',
                                second: '2-digit'
                            });

                            return `
                            <tr>
                                <td>${index + 1}</td>
                                <td>${item.term_length} Months</td>
                                <td>${parseFloat(item.interest_rate).toFixed(2)}%</td>
                                <td>${dateStr}</td>
                                <td>${item.updated_by}</td>
                            </tr>
                        `;
                        }).join('');

                        historyTable.innerHTML = html;
                    } else {
                        historyTable.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px; color: #999;">No history available.</td></tr>';
                    }
                } else {
                    historyTable.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 20px; color: #d32f2f;">Error: ${data.message}</td></tr>`;
                }
            })
            .catch(error => {
                historyTable.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 20px; color: #d32f2f;">Error loading history: ${error.message}</td></tr>`;
                console.error('Fetch error:', error);
            });
    }

    function updateInterestRate() {
        const termLength = document.getElementById('termLengthSelect').value;
        const interestRate = document.getElementById('newInterestRate').value;
        const messageDiv = document.getElementById('interestRateMessage');

        if (!termLength) {
            messageDiv.className = 'message error';
            messageDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Please select a term length.';
            return;
        }

        if (!interestRate || parseFloat(interestRate) <= 0) {
            messageDiv.className = 'message error';
            messageDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Please enter a valid interest rate.';
            return;
        }

        const formData = new FormData();
        formData.append('action', 'update_interest_rate');
        formData.append('interest_rate', interestRate);
        formData.append('term_length', termLength);

        fetch('manage_interest_rate_modal.php', {
            method: 'POST',
            body: formData,
            cache: 'no-store'
        })
            .then(response => response.json())
            .then(data => {
                messageDiv.className = `message ${data.success ? 'success' : 'error'}`;
                messageDiv.innerHTML = `<i class="fas ${data.success ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i> ${data.message}`;

                if (data.success) {
                    document.getElementById('interestRateForm').reset();
                    setTimeout(() => {
                        loadCurrentRates();
                        loadHistoryForTerm('');
                        messageDiv.innerHTML = '';
                    }, 1500);
                }
            })
            .catch(error => {
                messageDiv.className = 'message error';
                messageDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> Error updating interest rate: ${error.message}`;
                console.error('Fetch error:', error);
            });
    }

    // Close modal when clicking outside
    window.addEventListener('click', (event) => {
        const modal = document.getElementById("manageInterestRateModal");
        if (event.target === modal) {
            closeManageInterestRateModal();
        }
    });

    // Close modal on Escape key
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeManageInterestRateModal();
        }
    });
</script>