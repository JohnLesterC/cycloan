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
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
$profile_img = !empty($user['profile_img']) && file_exists(__DIR__ . '/uploads/' . $user['profile_img'])
    ? $user['profile_img']
    : 'default.png';

// ========== PREDICTIVE ANALYTICS ENGINE ==========

// 1. DEFAULT RISK PREDICTION MODEL
$defaultRiskScores = [];
$query = "
    SELECT 
        la.application_id,
        u.first_name,
        u.last_name,
        la.final_loan_amount,
        COALESCE(l.total_paid, 0) AS total_paid,
        COALESCE(l.total_principal + l.total_interest, 0) AS total_due,
        COUNT(CASE WHEN ps.status = 'pending' AND DATEDIFF(CURDATE(), ps.due_date) > 0 THEN 1 END) AS overdue_count,
        MAX(DATEDIFF(CURDATE(), ps.due_date)) AS max_days_overdue,
        AVG(CASE WHEN ps.status = 'Paid' THEN 1 ELSE 0 END) * 100 AS payment_history_score,
        fi.net_income,
        fi.monthly_expenses
    FROM loan_applications la
    JOIN users1 u ON la.user_id = u.id
    LEFT JOIN loans l ON la.application_id = l.application_id
    LEFT JOIN payment_schedules ps ON l.loan_id = ps.loan_id
    LEFT JOIN financial_info fi ON la.user_id = fi.user_id
    WHERE la.status = 'Active'
    GROUP BY la.application_id
";

$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Calculate default risk score (0-100)
        $risk_score = 0;

        // Factor 1: Payment history (0-30 points)
        $payment_score = $row['payment_history_score'] ?? 0;
        $risk_score += (30 - ($payment_score * 0.3));

        // Factor 2: Days overdue (0-35 points)
        $days_overdue = $row['max_days_overdue'] ?? 0;
        if ($days_overdue > 90) {
            $risk_score += 35;
        } elseif ($days_overdue > 60) {
            $risk_score += 25;
        } elseif ($days_overdue > 30) {
            $risk_score += 15;
        } elseif ($days_overdue > 0) {
            $risk_score += 5;
        }

        // Factor 3: Debt-to-income ratio (0-20 points)
        $net_income = $row['net_income'] ?? 1;
        $monthly_expenses = $row['monthly_expenses'] ?? 0;
        $debt_ratio = $monthly_expenses / ($net_income + 1);
        if ($debt_ratio > 0.8) {
            $risk_score += 20;
        } elseif ($debt_ratio > 0.6) {
            $risk_score += 15;
        } elseif ($debt_ratio > 0.4) {
            $risk_score += 10;
        }

        // Factor 4: Repayment ratio (0-15 points)
        $total_due = $row['total_due'] ?? 1;
        $repayment_ratio = $row['total_paid'] / $total_due;
        if ($repayment_ratio < 0.2) {
            $risk_score += 15;
        } elseif ($repayment_ratio < 0.5) {
            $risk_score += 10;
        } elseif ($repayment_ratio < 0.8) {
            $risk_score += 5;
        }

        // Determine risk level
        $risk_level = 'LOW';
        if ($risk_score >= 75) {
            $risk_level = 'CRITICAL';
        } elseif ($risk_score >= 60) {
            $risk_level = 'HIGH';
        } elseif ($risk_score >= 40) {
            $risk_level = 'MEDIUM';
        }

        // Predict probability of default in next 90 days
        $default_probability = min($risk_score / 100, 1.0) * 100;

        $defaultRiskScores[] = [
            'application_id' => $row['application_id'],
            'borrower_name' => $row['first_name'] . ' ' . $row['last_name'],
            'loan_amount' => $row['final_loan_amount'],
            'risk_score' => round($risk_score, 1),
            'risk_level' => $risk_level,
            'default_probability' => round($default_probability, 1),
            'days_overdue' => $days_overdue,
            'payment_score' => round($payment_score, 1),
            'debt_ratio' => round($debt_ratio, 2)
        ];
    }
}

// Sort by risk score (highest first)
usort($defaultRiskScores, function ($a, $b) {
    return $b['risk_score'] <=> $a['risk_score'];
});

// 2. PREPAYMENT LIKELIHOOD PREDICTION
$prepaymentPrediction = [];
$query = "
    SELECT 
        la.application_id,
        u.first_name,
        u.last_name,
        la.final_loan_amount,
        COALESCE(l.total_paid, 0) AS total_paid,
        COALESCE(l.total_principal + l.total_interest, 0) AS total_due,
        AVG(DATEDIFF(ps.paid_at, ps.due_date)) AS avg_days_early,
        COUNT(CASE WHEN ps.paid_at < ps.due_date THEN 1 END) AS early_payments,
        COUNT(*) AS total_payments,
        fi.net_income
    FROM loan_applications la
    JOIN users1 u ON la.user_id = u.id
    LEFT JOIN loans l ON la.application_id = l.application_id
    LEFT JOIN payment_schedules ps ON l.loan_id = ps.loan_id AND ps.paid_at IS NOT NULL
    LEFT JOIN financial_info fi ON la.user_id = fi.user_id
    WHERE la.status = 'Active' AND ps.paid_at IS NOT NULL
    GROUP BY la.application_id
    HAVING early_payments > 0
    LIMIT 20
";

$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $early_payment_ratio = $row['early_payments'] / $row['total_payments'];
        $prepayment_likelihood = min($early_payment_ratio * 100 + 20, 95);

        $prepaymentPrediction[] = [
            'application_id' => $row['application_id'],
            'borrower_name' => $row['first_name'] . ' ' . $row['last_name'],
            'prepayment_likelihood' => round($prepayment_likelihood, 1),
            'early_payment_history' => $row['early_payments'] . '/' . $row['total_payments'],
            'avg_days_early' => round($row['avg_days_early'] ?? 0, 1)
        ];
    }
}

// 3. LOAN PERFORMANCE FORECAST (Next 6 months)
$performanceForecast = [];
$query = "
    SELECT 
        DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 1 MONTH), '%Y-%m') AS forecast_month,
        COUNT(*) AS projected_due,
        SUM(ps.amount) AS projected_amount,
        SUM(ps.interest_amount) AS projected_interest
    FROM payment_schedules ps
    WHERE ps.due_date BETWEEN DATE_ADD(CURDATE(), INTERVAL 1 MONTH) AND DATE_ADD(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY forecast_month
    ORDER BY forecast_month ASC
";

$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $performanceForecast[] = [
            'month' => $row['forecast_month'],
            'projected_payments' => $row['projected_due'],
            'projected_amount' => $row['projected_amount'],
            'projected_interest' => $row['projected_interest']
        ];
    }
}

// 4. CHURN PREDICTION (Borrowers unlikely to return)
$churnRiskLoans = [];
$query = "
    SELECT 
        la.application_id,
        u.first_name,
        u.last_name,
        COUNT(la.user_id) AS loan_count,
        MAX(la.created_at) AS last_loan_date,
        DATEDIFF(CURDATE(), MAX(la.created_at)) AS days_since_last_loan,
        AVG(la.final_loan_amount) AS avg_loan_amount,
        MIN(la.final_loan_amount) AS min_loan_amount,
        l.status AS current_status
    FROM loan_applications la
    JOIN users1 u ON la.user_id = u.id
    LEFT JOIN loans l ON la.application_id = l.application_id
    GROUP BY la.user_id
    HAVING days_since_last_loan > 180 AND current_status = 'closed'
    LIMIT 15
";

$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Calculate churn risk (0-100)
        $churn_risk = 0;
        $days_inactive = $row['days_since_last_loan'];

        if ($days_inactive > 365) {
            $churn_risk = 95;
        } elseif ($days_inactive > 180) {
            $churn_risk = 70 + (($days_inactive - 180) / 185 * 25);
        }

        $churnRiskLoans[] = [
            'borrower_name' => $row['first_name'] . ' ' . $row['last_name'],
            'churn_risk' => round($churn_risk, 1),
            'days_inactive' => $row['days_since_last_loan'],
            'lifetime_loans' => $row['loan_count'],
            'avg_loan_amount' => round($row['avg_loan_amount'], 2)
        ];
    }
}

// 5. PORTFOLIO HEALTH TREND PREDICTION
$portfolioTrend = [];
$query = "
    SELECT 
        DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m') AS month,
        COUNT(DISTINCT la.application_id) AS active_loans,
        SUM(CASE WHEN ps.status = 'pending' AND DATEDIFF(CURDATE(), ps.due_date) > 0 THEN 1 ELSE 0 END) AS overdue_count,
        SUM(ps.amount) AS total_due,
        SUM(ps.amount_paid) AS total_paid
    FROM loan_applications la
    LEFT JOIN loans l ON la.application_id = l.application_id
    LEFT JOIN payment_schedules ps ON l.loan_id = ps.loan_id
    WHERE la.created_at <= DATE_ADD(CURDATE(), INTERVAL -5 MONTH)
    GROUP BY month
    UNION ALL
    SELECT 
        DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 4 MONTH), '%Y-%m') AS month,
        COUNT(DISTINCT la.application_id),
        SUM(CASE WHEN ps.status = 'pending' AND DATEDIFF(CURDATE(), ps.due_date) > 0 THEN 1 ELSE 0 END),
        SUM(ps.amount),
        SUM(ps.amount_paid)
    FROM loan_applications la
    LEFT JOIN loans l ON la.application_id = l.application_id
    LEFT JOIN payment_schedules ps ON l.loan_id = ps.loan_id
    WHERE la.created_at <= DATE_ADD(CURDATE(), INTERVAL -4 MONTH)
    GROUP BY month
    ORDER BY month ASC
";

$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $portfolioTrend[] = [
            'month' => $row['month'],
            'active_loans' => $row['active_loans'],
            'overdue_count' => $row['overdue_count'],
            'collection_rate' => round(($row['total_paid'] / ($row['total_due'] + 1)) * 100, 1)
        ];
    }
}

// 6. REVENUE FORECAST
$revenueForecast = [];
$query = "
    SELECT 
        DATE_FORMAT(ps.due_date, '%Y-%m') AS forecast_month,
        SUM(ps.interest_amount) AS projected_interest,
        COUNT(*) AS projected_payments
    FROM payment_schedules ps
    WHERE ps.due_date >= CURDATE()
    GROUP BY forecast_month
    ORDER BY forecast_month ASC
    LIMIT 12
";

$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $revenueForecast[] = [
            'month' => $row['forecast_month'],
            'projected_interest' => round($row['projected_interest'], 2),
            'projected_payments' => $row['projected_payments']
        ];
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Predictive Analytics - CYCLOAN</title>
    <link rel="stylesheet" href="CSS/admin_dashboard.css">
    <link rel="stylesheet" href="CSS/admin_profile.css">
    <link rel="stylesheet" href="CSS/superadmin_dashboard.css">
    <link rel="stylesheet" href="CSS/nav_active.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f5f5f5;
            color: #333;
        }

        .main-container {
            display: flex;
            min-height: 100vh;
        }

        .content {
            flex: 1;
            padding: 30px;
            background: #f9f9f9;
        }

        .page-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            color: white;
        }

        .page-header h1 {
            font-size: 32px;
            font-weight: 700;
        }

        .page-header p {
            font-size: 14px;
            opacity: 0.9;
        }

        .section {
            margin-bottom: 40px;
        }

        .section-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 20px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: #667eea;
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .prediction-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }

        .prediction-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }

        .prediction-card.critical {
            border-left-color: #ff4757;
            background: rgba(255, 71, 87, 0.05);
        }

        .prediction-card.high {
            border-left-color: #ffa502;
            background: rgba(255, 165, 2, 0.05);
        }

        .prediction-card.medium {
            border-left-color: #ffc107;
            background: rgba(255, 193, 7, 0.05);
        }

        .prediction-card.low {
            border-left-color: #4caf50;
            background: rgba(76, 175, 80, 0.05);
        }

        .card-header {
            font-weight: 600;
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .card-title {
            font-size: 18px;
            font-weight: 700;
            color: #333;
            margin-bottom: 8px;
        }

        .card-value {
            font-size: 28px;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 10px;
        }

        .card-footer {
            font-size: 12px;
            color: #999;
        }

        .risk-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 10px;
        }

        .risk-badge.critical {
            background: #ff4757;
            color: white;
        }

        .risk-badge.high {
            background: #ffa502;
            color: white;
        }

        .risk-badge.medium {
            background: #ffc107;
            color: white;
        }

        .risk-badge.low {
            background: #4caf50;
            color: white;
        }

        .table-responsive {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table thead {
            background: #f5f5f5;
            border-bottom: 2px solid #e0e0e0;
        }

        table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #666;
            font-size: 13px;
        }

        table td {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 14px;
        }

        table tbody tr:hover {
            background: #f9f9f9;
        }

        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .chart-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
            color: #333;
        }

        .action-button {
            padding: 8px 16px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .action-button:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }

        .action-button.danger {
            background: #ff4757;
        }

        .action-button.danger:hover {
            background: #ff3838;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .progress-bar {
            height: 6px;
            background: #e0e0e0;
            border-radius: 3px;
            margin-top: 8px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 3px;
            transition: width 0.3s ease;
        }

        .metric-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 13px;
            border-bottom: 1px solid #e0e0e0;
        }

        .metric-row:last-child {
            border-bottom: none;
        }

        .metric-label {
            color: #666;
        }

        .metric-value {
            font-weight: 600;
            color: #333;
        }

        .top-navbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
        }

        .top-navbar a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .top-navbar a:hover {
            color: #5568d3;
        }

        @media (max-width: 768px) {
            .content {
                padding: 15px;
            }

            .page-header {
                flex-direction: column;
                text-align: center;
            }

            .page-header h1 {
                font-size: 24px;
            }

            .cards-grid {
                grid-template-columns: 1fr;
            }

            table {
                font-size: 12px;
            }

            table th,
            table td {
                padding: 10px;
            }
        }
    </style>
</head>

<body>
    <div class="main-container">
        <div class="content">
            <!-- Navigation -->
            <div class="top-navbar">
                <a href="<?php echo $dashboard_link; ?>">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <a href="reports_analytics.php">
                    <i class="fas fa-chart-bar"></i> View Reports
                </a>
            </div>

            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <i class="fas fa-crystal-ball" style="font-size: 32px;"></i>
                </div>
                <div>
                    <h1>Predictive Analytics</h1>
                    <p>AI-powered forecasting and risk assessment for your loan portfolio</p>
                </div>
            </div>

            <!-- DEFAULT RISK PREDICTION -->
            <div class="section">
                <div class="section-title">
                    <i class="fas fa-exclamation-triangle"></i>
                    Default Risk Prediction (Next 90 Days)
                </div>

                <div class="cards-grid">
                    <?php if (!empty($defaultRiskScores)): ?>
                        <?php foreach (array_slice($defaultRiskScores, 0, 4) as $loan): ?>
                            <div class="prediction-card <?php echo strtolower($loan['risk_level']); ?>">
                                <div class="card-header">
                                    <i class="fas fa-user"></i> <?php echo $loan['borrower_name']; ?>
                                </div>
                                <div class="card-title"><?php echo $loan['application_id']; ?></div>
                                <div class="card-value"><?php echo $loan['risk_score']; ?>/100</div>
                                <span class="risk-badge <?php echo strtolower($loan['risk_level']); ?>">
                                    <?php echo $loan['risk_level']; ?> RISK
                                </span>
                                <div class="progress-bar" style="margin-top: 12px;">
                                    <div class="progress-fill" style="width: <?php echo $loan['risk_score']; ?>%;"></div>
                                </div>
                                <div class="metric-row" style="margin-top: 12px;">
                                    <span class="metric-label">Default Probability:</span>
                                    <span class="metric-value"><?php echo $loan['default_probability']; ?>%</span>
                                </div>
                                <div class="metric-row">
                                    <span class="metric-label">Days Overdue:</span>
                                    <span class="metric-value"><?php echo $loan['days_overdue'] ?? 0; ?></span>
                                </div>
                                <button class="action-button danger"
                                    onclick="alert('Send SMS reminder to: <?php echo $loan['borrower_name']; ?>')">
                                    Send Alert
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle"></i>
                            <h3>All Clear!</h3>
                            <p>No high-risk loans detected in your portfolio</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Default Risk Table -->
                <?php if (!empty($defaultRiskScores)): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Borrower</th>
                                    <th>Application ID</th>
                                    <th>Risk Score</th>
                                    <th>Risk Level</th>
                                    <th>Default Probability</th>
                                    <th>Days Overdue</th>
                                    <th>Debt Ratio</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($defaultRiskScores as $loan): ?>
                                    <tr>
                                        <td><?php echo $loan['borrower_name']; ?></td>
                                        <td><?php echo $loan['application_id']; ?></td>
                                        <td><strong><?php echo $loan['risk_score']; ?>/100</strong></td>
                                        <td>
                                            <span class="risk-badge <?php echo strtolower($loan['risk_level']); ?>">
                                                <?php echo $loan['risk_level']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $loan['default_probability']; ?>%</td>
                                        <td><?php echo $loan['days_overdue'] ?? 0; ?> days</td>
                                        <td><?php echo $loan['debt_ratio']; ?></td>
                                        <td>
                                            <button class="action-button"
                                                onclick="alert('View details for: <?php echo $loan['application_id']; ?>')">
                                                Details
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- PREPAYMENT LIKELIHOOD -->
            <div class="section">
                <div class="section-title">
                    <i class="fas fa-forward"></i>
                    Prepayment Likelihood Analysis
                </div>

                <?php if (!empty($prepaymentPrediction)): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Borrower</th>
                                    <th>Application ID</th>
                                    <th>Prepayment Likelihood</th>
                                    <th>Early Payment History</th>
                                    <th>Avg Days Early</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($prepaymentPrediction as $loan): ?>
                                    <tr>
                                        <td><?php echo $loan['borrower_name']; ?></td>
                                        <td><?php echo $loan['application_id']; ?></td>
                                        <td>
                                            <strong><?php echo $loan['prepayment_likelihood']; ?>%</strong>
                                            <div class="progress-bar" style="width: 100%; margin-top: 5px;">
                                                <div class="progress-fill"
                                                    style="width: <?php echo $loan['prepayment_likelihood']; ?>%; background: #4caf50;">
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo $loan['early_payment_history']; ?></td>
                                        <td><?php echo $loan['avg_days_early']; ?> days</td>
                                        <td>
                                            <button class="action-button" onclick="alert('Excellent borrower!')">
                                                Mark Premium
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-info-circle"></i>
                        <h3>No Data Available</h3>
                        <p>Prepayment data will appear once borrowers start making early payments</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- PERFORMANCE FORECAST -->
            <div class="section">
                <div class="section-title">
                    <i class="fas fa-chart-line"></i>
                    6-Month Performance Forecast
                </div>

                <?php if (!empty($performanceForecast)): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Forecast Month</th>
                                    <th>Projected Payments</th>
                                    <th>Projected Amount (Principal)</th>
                                    <th>Projected Interest Income</th>
                                    <th>Total Expected</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($performanceForecast as $forecast): ?>
                                    <tr>
                                        <td><strong><?php echo $forecast['month']; ?></strong></td>
                                        <td><?php echo $forecast['projected_payments']; ?> payments</td>
                                        <td>₱<?php echo number_format($forecast['projected_amount'], 2); ?></td>
                                        <td>₱<?php echo number_format($forecast['projected_interest'], 2); ?></td>
                                        <td>
                                            <strong>₱<?php echo number_format($forecast['projected_amount'] + $forecast['projected_interest'], 2); ?></strong>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-hourglass"></i>
                        <h3>No Forecast Data</h3>
                        <p>Create payment schedules to see performance forecasts</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- CHURN RISK -->
            <div class="section">
                <div class="section-title">
                    <i class="fas fa-user-slash"></i>
                    Borrower Churn Risk Assessment
                </div>

                <?php if (!empty($churnRiskLoans)): ?>
                    <div class="cards-grid">
                        <?php foreach (array_slice($churnRiskLoans, 0, 3) as $loan): ?>
                            <div class="prediction-card critical">
                                <div class="card-header">Churn Risk</div>
                                <div class="card-title"><?php echo $loan['borrower_name']; ?></div>
                                <div class="card-value"><?php echo $loan['churn_risk']; ?>%</div>
                                <div class="metric-row">
                                    <span class="metric-label">Days Inactive:</span>
                                    <span class="metric-value"><?php echo $loan['days_inactive']; ?> days</span>
                                </div>
                                <div class="metric-row">
                                    <span class="metric-label">Lifetime Loans:</span>
                                    <span class="metric-value"><?php echo $loan['lifetime_loans']; ?></span>
                                </div>
                                <div class="metric-row">
                                    <span class="metric-label">Avg Loan Amount:</span>
                                    <span class="metric-value">₱<?php echo number_format($loan['avg_loan_amount'], 2); ?></span>
                                </div>
                                <button class="action-button"
                                    onclick="alert('Send re-engagement offer to: <?php echo $loan['borrower_name']; ?>')">
                                    Re-engage
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-thumbs-up"></i>
                        <h3>Low Churn Risk</h3>
                        <p>Your borrower base shows good engagement</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- REVENUE FORECAST -->
            <div class="section">
                <div class="section-title">
                    <i class="fas fa-dollar-sign"></i>
                    Revenue Forecast (Interest Income)
                </div>

                <?php if (!empty($revenueForecast)): ?>
                    <div class="chart-container">
                        <canvas id="revenueChart"></canvas>
                    </div>

                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Projected Interest Income</th>
                                    <th>Payment Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $totalInterest = 0;
                                foreach ($revenueForecast as $revenue):
                                    $totalInterest += $revenue['projected_interest'];
                                    ?>
                                    <tr>
                                        <td><?php echo $revenue['month']; ?></td>
                                        <td><strong>₱<?php echo number_format($revenue['projected_interest'], 2); ?></strong>
                                        </td>
                                        <td><?php echo $revenue['projected_payments']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr style="background: #f5f5f5; font-weight: 600;">
                                    <td colspan="2">Total 12-Month Forecast</td>
                                    <td>₱<?php echo number_format($totalInterest, 2); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-chart-bar"></i>
                        <h3>No Revenue Data</h3>
                        <p>Revenue forecasts will be available after processing payments</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script>
        // Revenue Chart
        const revenueData = <?php echo json_encode($revenueForecast); ?>;

        if (revenueData.length > 0 && document.getElementById('revenueChart')) {
            const ctx = document.getElementById('revenueChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: revenueData.map(r => r.month),
                    datasets: [{
                        label: 'Projected Interest Income (₱)',
                        data: revenueData.map(r => r.projected_interest),
                        backgroundColor: '#667eea',
                        borderColor: '#5568d3',
                        borderWidth: 2,
                        borderRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function (value) {
                                    return '₱' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        }

        // Show success notification on page load
        window.addEventListener('load', function () {
            Toastify({
                text: '📊 Predictive Analytics Dashboard Loaded',
                duration: 3000,
                gravity: 'top',
                position: 'right',
                backgroundColor: '#4caf50'
            }).showToast();
        });
    </script>
</body>

</html>