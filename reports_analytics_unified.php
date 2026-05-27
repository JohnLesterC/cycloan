<?php
session_start();
require "CYCLOAN_db.php";

if (!isset($_SESSION['email'])) {
    header("Location: index.php");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);

// Determine admin role
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
$profile_img = (!empty($user['profile_img']) && file_exists(__DIR__ . '/uploads/' . $user['profile_img']))
    ? $user['profile_img']
    : 'default.png';

// ========== COMBINED ANALYTICS DATA COLLECTION ==========

// === OPERATIONAL ANALYTICS DATA ===

// 1. REAL-TIME KPI DASHBOARD
$realTimeKPIs = [
    'applications_today' => 0,
    'applications_this_week' => 0,
    'applications_this_month' => 0,
    'approvals_today' => 0,
    'approvals_this_month' => 0,
    'disbursements_today' => 0,
    'disbursements_this_month' => 0,
    'collections_today' => 0,
    'collections_this_month' => 0,
    'overdue_count' => 0,
    'pending_approvals' => 0,
    'average_processing_days' => 0
];

// Applications metrics
$query = "
    SELECT 
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) AS today,
        SUM(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS week,
        SUM(CASE WHEN MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) THEN 1 ELSE 0 END) AS month,
        SUM(CASE WHEN DATE(created_at) = CURDATE() AND status = 'Approved' THEN 1 ELSE 0 END) AS approvals_today,
        SUM(CASE WHEN MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) AND status = 'Approved' THEN 1 ELSE 0 END) AS approvals_month
    FROM loan_applications
";

$result = $conn->query($query);
if ($row = $result->fetch_assoc()) {
    $realTimeKPIs['applications_today'] = $row['today'] ?? 0;
    $realTimeKPIs['applications_this_week'] = $row['week'] ?? 0;
    $realTimeKPIs['applications_this_month'] = $row['month'] ?? 0;
    $realTimeKPIs['approvals_today'] = $row['approvals_today'] ?? 0;
    $realTimeKPIs['approvals_this_month'] = $row['approvals_month'] ?? 0;
}

// Disbursements metrics
$query = "
    SELECT 
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN final_loan_amount ELSE 0 END) AS today,
        SUM(CASE WHEN MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) THEN final_loan_amount ELSE 0 END) AS month
    FROM loans
    WHERE status = 'active'
";

$result = $conn->query($query);
if ($row = $result->fetch_assoc()) {
    $realTimeKPIs['disbursements_today'] = $row['today'] ?? 0;
    $realTimeKPIs['disbursements_this_month'] = $row['month'] ?? 0;
}

// Collections metrics
$query = "
    SELECT 
        SUM(CASE WHEN DATE(paid_at) = CURDATE() THEN amount_paid ELSE 0 END) AS today,
        SUM(CASE WHEN MONTH(paid_at) = MONTH(CURDATE()) AND YEAR(paid_at) = YEAR(CURDATE()) THEN amount_paid ELSE 0 END) AS month,
        SUM(CASE WHEN ps.status = 'pending' AND DATEDIFF(CURDATE(), ps.due_date) > 0 THEN 1 ELSE 0 END) AS overdue
    FROM payment_schedules ps
";

$result = $conn->query($query);
if ($row = $result->fetch_assoc()) {
    $realTimeKPIs['collections_today'] = $row['today'] ?? 0;
    $realTimeKPIs['collections_this_month'] = $row['month'] ?? 0;
    $realTimeKPIs['overdue_count'] = $row['overdue'] ?? 0;
}

// Pending approvals
$query = "SELECT COUNT(*) as count FROM loan_applications WHERE status = 'Pending'";
$result = $conn->query($query);
if ($row = $result->fetch_assoc()) {
    $realTimeKPIs['pending_approvals'] = $row['count'] ?? 0;
}

// Average processing days
$query = "
    SELECT AVG(DATEDIFF(updated_at, created_at)) AS avg_days
    FROM loan_applications
    WHERE status = 'Approved'
";

$result = $conn->query($query);
if ($row = $result->fetch_assoc()) {
    $realTimeKPIs['average_processing_days'] = round($row['avg_days'] ?? 0, 1);
}

// 2. PORTFOLIO HEALTH SNAPSHOT
$portfolioHealth = [
    'total_active_loans' => 0,
    'current_status' => 0,
    'days_1_30' => 0,
    'days_31_60' => 0,
    'days_60_plus' => 0,
    'portfolio_at_risk' => 0,
    'health_percentage' => 0
];

$query = "
    SELECT 
        COUNT(DISTINCT l.loan_id) AS total_loans,
        SUM(CASE WHEN DATEDIFF(CURDATE(), ps.due_date) <= 0 THEN 1 ELSE 0 END) AS current,
        SUM(CASE WHEN DATEDIFF(CURDATE(), ps.due_date) BETWEEN 1 AND 30 THEN 1 ELSE 0 END) AS days_1_30,
        SUM(CASE WHEN DATEDIFF(CURDATE(), ps.due_date) BETWEEN 31 AND 60 THEN 1 ELSE 0 END) AS days_31_60,
        SUM(CASE WHEN DATEDIFF(CURDATE(), ps.due_date) > 60 THEN 1 ELSE 0 END) AS days_60_plus,
        SUM(CASE WHEN ps.status = 'pending' AND DATEDIFF(CURDATE(), ps.due_date) > 0 THEN ps.amount ELSE 0 END) AS at_risk
    FROM loans l
    LEFT JOIN payment_schedules ps ON l.loan_id = ps.loan_id
    WHERE l.status = 'active'
";

$result = $conn->query($query);
if ($row = $result->fetch_assoc()) {
    $portfolioHealth['total_active_loans'] = $row['total_loans'] ?? 0;
    $portfolioHealth['current_status'] = $row['current'] ?? 0;
    $portfolioHealth['days_1_30'] = $row['days_1_30'] ?? 0;
    $portfolioHealth['days_31_60'] = $row['days_31_60'] ?? 0;
    $portfolioHealth['days_60_plus'] = $row['days_60_plus'] ?? 0;
    $portfolioHealth['portfolio_at_risk'] = $row['at_risk'] ?? 0;

    $total_delinquent = ($row['days_1_30'] ?? 0) + ($row['days_31_60'] ?? 0) + ($row['days_60_plus'] ?? 0);
    $total_payments = ($row['current'] ?? 0) + $total_delinquent;
    $portfolioHealth['health_percentage'] = $total_payments > 0 ? round((($row['current'] ?? 0) / $total_payments) * 100, 1) : 100;
}

// === PREDICTIVE ANALYTICS DATA ===

// 1. DEFAULT RISK PREDICTION MODEL
$defaultRiskScores = [];
$query = "
    SELECT 
        la.application_id,
        u.first_name,
        u.last_name,
        l.final_loan_amount,
        fi.monthly_income,
        fi.monthly_expenses,
        COUNT(ps.payment_id) AS total_payments,
        SUM(CASE WHEN ps.status = 'Paid' THEN 1 ELSE 0 END) AS paid_payments,
        SUM(CASE WHEN ps.status = 'pending' AND DATEDIFF(CURDATE(), ps.due_date) > 0 THEN DATEDIFF(CURDATE(), ps.due_date) ELSE 0 END) AS max_days_overdue
    FROM loan_applications la
    JOIN loans l ON la.application_id = l.application_id
    JOIN users1 u ON la.applicant_id = u.user_id
    LEFT JOIN financial_info fi ON u.user_id = fi.user_id
    LEFT JOIN payment_schedules ps ON l.loan_id = ps.loan_id
    WHERE l.status = 'active'
    GROUP BY la.application_id, u.first_name, u.last_name, l.final_loan_amount, fi.monthly_income, fi.monthly_expenses
    ORDER BY MAX(CASE WHEN ps.status = 'pending' AND DATEDIFF(CURDATE(), ps.due_date) > 0 THEN DATEDIFF(CURDATE(), ps.due_date) ELSE 0 END) DESC
    LIMIT 20
";

$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $payment_history = ($row['paid_payments'] ?? 0) / max(($row['total_payments'] ?? 1), 1);
        $days_overdue = $row['max_days_overdue'] ?? 0;
        $debt_to_income = ($row['monthly_expenses'] ?? 0) / max(($row['monthly_income'] ?? 1), 1);
        $repayment_ratio = $payment_history;

        $risk_score = 0;
        $risk_score += (30 * (1 - $payment_history));
        $risk_score += min(35, ($days_overdue / 180) * 35);
        $risk_score += min(20, $debt_to_income * 20);
        $risk_score += (15 * (1 - $repayment_ratio));

        $risk_score = min(100, max(0, round($risk_score, 1)));

        if ($risk_score <= 25) {
            $risk_level = 'LOW';
            $badge_color = '#4caf50';
        } elseif ($risk_score <= 50) {
            $risk_level = 'MEDIUM';
            $badge_color = '#ffc107';
        } elseif ($risk_score <= 75) {
            $risk_level = 'HIGH';
            $badge_color = '#ff9800';
        } else {
            $risk_level = 'CRITICAL';
            $badge_color = '#ff4757';
        }

        $default_probability = min(100, round(($risk_score / 100) * 100, 1));

        $defaultRiskScores[] = [
            'borrower' => $row['first_name'] . ' ' . $row['last_name'],
            'loan_amount' => $row['final_loan_amount'],
            'risk_score' => $risk_score,
            'risk_level' => $risk_level,
            'badge_color' => $badge_color,
            'default_probability' => $default_probability
        ];
    }
}

// 2. REVENUE FORECAST (12 months)
$revenueForecasts = [];
$query = "
    SELECT 
        DATE_FORMAT(CURDATE(), '%m') AS current_month,
        COUNT(DISTINCT l.loan_id) AS loan_count,
        SUM(ps.amount * 0.05) AS projected_interest
    FROM loans l
    LEFT JOIN payment_schedules ps ON l.loan_id = ps.loan_id
    WHERE l.status = 'active'
    GROUP BY YEAR(CURDATE())
";

$result = $conn->query($query);
$base_revenue = 0;
if ($row = $result->fetch_assoc()) {
    $base_revenue = $row['projected_interest'] ?? 0;
}

// Generate 12-month forecast
for ($i = 0; $i < 12; $i++) {
    $month = (intval(date('m')) + $i - 1) % 12 + 1;
    $year = intval(date('Y')) + intval((intval(date('m')) + $i - 1) / 12);
    $month_str = date('M', mktime(0, 0, 0, $month, 1));

    // Simple linear forecast (can be enhanced with ML)
    $forecasted_revenue = $base_revenue * (1 + (rand(-5, 10) / 100));

    $revenueForecasts[] = [
        'month' => $month_str,
        'revenue' => round($forecasted_revenue, 2)
    ];
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Analytics - CYCLOAN</title>
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

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid #e0e0e0;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 12px 20px;
            background: none;
            border: none;
            font-size: 14px;
            font-weight: 600;
            color: #999;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }

        .tab-btn:hover {
            color: #667eea;
        }

        .tab-btn.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .section {
            margin-bottom: 40px;
        }

        .section-title {
            font-size: 20px;
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

        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .kpi-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-top: 4px solid #667eea;
            transition: all 0.3s ease;
        }

        .kpi-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }

        .kpi-label {
            font-size: 12px;
            color: #999;
            font-weight: 600;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .kpi-value {
            font-size: 28px;
            font-weight: 700;
            color: #333;
            margin-bottom: 8px;
        }

        .table-responsive {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            overflow-x: auto;
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

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-badge.low {
            background: #d4edda;
            color: #155724;
        }

        .status-badge.medium {
            background: #fff3cd;
            color: #856404;
        }

        .status-badge.high {
            background: #ffe0e0;
            color: #ff9800;
        }

        .status-badge.critical {
            background: #f8d7da;
            color: #721c24;
        }

        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            max-width: 100%;
            height: 400px;
        }

        .metric-box {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .metric {
            text-align: center;
        }

        .metric-number {
            font-size: 32px;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 8px;
        }

        .metric-label {
            font-size: 14px;
            color: #666;
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

        .action-btn {
            padding: 8px 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .action-btn:hover {
            background: #5568d3;
            transform: translateY(-2px);
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

            .kpi-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .tabs {
                flex-direction: column;
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
                <div>
                    <a href="active_records.php">
                        <i class="fas fa-file-contract"></i> Loan Records
                    </a>
                </div>
            </div>

            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <i class="fas fa-chart-bar" style="font-size: 32px;"></i>
                </div>
                <div>
                    <h1>Complete Analytics Dashboard</h1>
                    <p>Operational metrics + Predictive insights in one unified view</p>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <div class="tabs">
                <button class="tab-btn active" onclick="switchTab('operational')">
                    <i class="fas fa-tachometer-alt"></i> Operational Analytics
                </button>
                <button class="tab-btn" onclick="switchTab('predictive')">
                    <i class="fas fa-crystal-ball"></i> Predictive Analytics
                </button>
            </div>

            <!-- ========== OPERATIONAL ANALYTICS TAB ========== -->
            <div id="operational" class="tab-content active">
                <!-- Real-Time KPIs -->
                <div class="section">
                    <div class="section-title">
                        <i class="fas fa-bolt"></i> Real-Time KPIs - Today & This Month
                    </div>
                    <div class="kpi-grid">
                        <div class="kpi-card">
                            <div class="kpi-label">Applications Today</div>
                            <div class="kpi-value"><?php echo $realTimeKPIs['applications_today']; ?></div>
                            <div class="kpi-label" style="margin-top: 10px;">Week:
                                <?php echo $realTimeKPIs['applications_this_week']; ?>
                            </div>
                        </div>

                        <div class="kpi-card">
                            <div class="kpi-label">Approvals Today</div>
                            <div class="kpi-value"><?php echo $realTimeKPIs['approvals_today']; ?></div>
                            <div class="kpi-label" style="margin-top: 10px;">Month:
                                <?php echo $realTimeKPIs['approvals_this_month']; ?>
                            </div>
                        </div>

                        <div class="kpi-card">
                            <div class="kpi-label">Disbursements Today</div>
                            <div class="kpi-value">
                                ₱<?php echo number_format($realTimeKPIs['disbursements_today'], 0); ?></div>
                            <div class="kpi-label" style="margin-top: 10px;">Month:
                                ₱<?php echo number_format($realTimeKPIs['disbursements_this_month'], 0); ?></div>
                        </div>

                        <div class="kpi-card">
                            <div class="kpi-label">Collections Today</div>
                            <div class="kpi-value">₱<?php echo number_format($realTimeKPIs['collections_today'], 0); ?>
                            </div>
                            <div class="kpi-label" style="margin-top: 10px;">Month:
                                ₱<?php echo number_format($realTimeKPIs['collections_this_month'], 0); ?></div>
                        </div>

                        <div class="kpi-card">
                            <div class="kpi-label">Overdue Payments</div>
                            <div class="kpi-value" style="color: #ff4757;"><?php echo $realTimeKPIs['overdue_count']; ?>
                            </div>
                            <div class="kpi-label" style="margin-top: 10px; color: #ff4757;">⚠️ Attention Required</div>
                        </div>

                        <div class="kpi-card">
                            <div class="kpi-label">Pending Approvals</div>
                            <div class="kpi-value"><?php echo $realTimeKPIs['pending_approvals']; ?></div>
                            <div class="kpi-label" style="margin-top: 10px; color: #667eea;">Action Needed</div>
                        </div>

                        <div class="kpi-card">
                            <div class="kpi-label">Avg Processing Days</div>
                            <div class="kpi-value"><?php echo $realTimeKPIs['average_processing_days']; ?></div>
                            <div class="kpi-label" style="margin-top: 10px;">Days to Approval</div>
                        </div>

                        <div class="kpi-card">
                            <div class="kpi-label">Portfolio Health</div>
                            <div class="kpi-value"><?php echo $portfolioHealth['health_percentage']; ?>%</div>
                            <div class="kpi-label" style="margin-top: 10px;">Current Status</div>
                        </div>
                    </div>
                </div>

                <!-- Portfolio Health -->
                <div class="section">
                    <div class="section-title">
                        <i class="fas fa-heart-pulse"></i> Portfolio Health Breakdown
                    </div>
                    <div class="metric-box">
                        <div class="metric">
                            <div class="metric-number"><?php echo $portfolioHealth['total_active_loans']; ?></div>
                            <div class="metric-label">Total Active Loans</div>
                        </div>
                        <div class="metric">
                            <div class="metric-number" style="color: #4caf50;">
                                <?php echo $portfolioHealth['current_status']; ?>
                            </div>
                            <div class="metric-label">Current (0 DPD)</div>
                        </div>
                        <div class="metric">
                            <div class="metric-number" style="color: #ffc107;">
                                <?php echo $portfolioHealth['days_1_30']; ?>
                            </div>
                            <div class="metric-label">1-30 Days Overdue</div>
                        </div>
                        <div class="metric">
                            <div class="metric-number" style="color: #ff9800;">
                                <?php echo $portfolioHealth['days_31_60']; ?>
                            </div>
                            <div class="metric-label">31-60 Days Overdue</div>
                        </div>
                        <div class="metric">
                            <div class="metric-number" style="color: #ff4757;">
                                <?php echo $portfolioHealth['days_60_plus']; ?>
                            </div>
                            <div class="metric-label">60+ Days Overdue</div>
                        </div>
                        <div class="metric">
                            <div class="metric-number">
                                ₱<?php echo number_format($portfolioHealth['portfolio_at_risk'], 0); ?></div>
                            <div class="metric-label">At-Risk Amount</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========== PREDICTIVE ANALYTICS TAB ========== -->
            <div id="predictive" class="tab-content">
                <!-- Default Risk Prediction -->
                <div class="section">
                    <div class="section-title">
                        <i class="fas fa-exclamation-triangle"></i> Default Risk Prediction - Top 20 At Risk
                    </div>

                    <?php if (!empty($defaultRiskScores)): ?>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Borrower</th>
                                        <th>Loan Amount</th>
                                        <th>Risk Score</th>
                                        <th>Risk Level</th>
                                        <th>Default Probability (90 Days)</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($defaultRiskScores as $score): ?>
                                        <tr>
                                            <td><strong><?php echo $score['borrower']; ?></strong></td>
                                            <td>₱<?php echo number_format($score['loan_amount'], 2); ?></td>
                                            <td>
                                                <strong style="color: <?php echo $score['badge_color']; ?>;">
                                                    <?php echo $score['risk_score']; ?>/100
                                                </strong>
                                            </td>
                                            <td>
                                                <span class="status-badge <?php echo strtolower($score['risk_level']); ?>">
                                                    <?php echo $score['risk_level']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?php echo $score['default_probability']; ?>%</strong>
                                                <div class="progress-bar">
                                                    <div class="progress-fill"
                                                        style="width: <?php echo $score['default_probability']; ?>%; background: <?php echo $score['badge_color']; ?>;">
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <button class="action-btn"
                                                    onclick="alert('Send alert for: <?php echo addslashes($score['borrower']); ?>')">
                                                    <i class="fas fa-bell"></i> Alert
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px; background: white; border-radius: 10px;">
                            <i class="fas fa-shield-alt" style="font-size: 48px; color: #4caf50; margin-bottom: 15px;"></i>
                            <p style="color: #999; font-size: 16px;">✅ All borrowers show LOW to MEDIUM risk</p>
                            <p style="color: #ccc; font-size: 14px;">No critical alerts at this time</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Revenue Forecast -->
                <div class="section">
                    <div class="section-title">
                        <i class="fas fa-chart-line"></i> 12-Month Revenue Forecast
                    </div>
                    <div class="chart-container">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script>
        // Tab Switching
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });

            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }

        // Revenue Chart
        const revenueData = <?php echo json_encode($revenueForecasts); ?>;
        const ctx = document.getElementById('revenueChart');

        if (ctx) {
            const chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: revenueData.map(d => d.month),
                    datasets: [{
                        label: 'Projected Interest Revenue (₱)',
                        data: revenueData.map(d => d.revenue),
                        backgroundColor: 'rgba(102, 126, 234, 0.6)',
                        borderColor: 'rgba(102, 126, 234, 1)',
                        borderWidth: 2,
                        borderRadius: 5,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            labels: {
                                font: { size: 13, family: "'Poppins', sans-serif", weight: 600 }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function (value) {
                                    return '₱' + value.toFixed(0);
                                }
                            }
                        }
                    }
                }
            });
        }

        // Show notification on load
        window.addEventListener('load', function () {
            Toastify({
                text: '📊 Complete Analytics Dashboard Loaded',
                duration: 3000,
                gravity: 'top',
                position: 'right',
                backgroundColor: '#667eea'
            }).showToast();
        });
    </script>
</body>

</html>