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
$profile_img = (!empty($user['profile_img']) && file_exists(__DIR__ . '/uploads/' . $user['profile_img']))
    ? $user['profile_img']
    : 'default.png';

// ========== ENHANCED OPERATIONAL ANALYTICS ==========

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

// 3. EFFICIENCY METRICS
$efficiencyMetrics = [
    'approval_rate' => 0,
    'disbursement_rate' => 0,
    'collection_rate' => 0,
    'avg_loan_size' => 0,
    'avg_term_length' => 0
];

$query = "
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) AS approved,
        AVG(CASE WHEN status = 'Approved' THEN final_loan_amount END) AS avg_amount,
        AVG(term_length) AS avg_term
    FROM loan_applications
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
";

$result = $conn->query($query);
if ($row = $result->fetch_assoc()) {
    $total = $row['total'] ?? 1;
    $efficiencyMetrics['approval_rate'] = round(($row['approved'] ?? 0) / $total * 100, 1);
    $efficiencyMetrics['avg_loan_size'] = round($row['avg_amount'] ?? 0, 2);
    $efficiencyMetrics['avg_term_length'] = round($row['avg_term'] ?? 0, 1);
}

// Collection rate
$query = "
    SELECT 
        SUM(ps.amount) AS total_due,
        SUM(ps.amount_paid) AS total_paid,
        SUM(CASE WHEN ps.status = 'Paid' THEN 1 ELSE 0 END) AS completed
    FROM payment_schedules ps
    WHERE ps.due_date <= CURDATE()
";

$result = $conn->query($query);
if ($row = $result->fetch_assoc()) {
    $total_due = $row['total_due'] ?? 1;
    $efficiencyMetrics['collection_rate'] = round(($row['total_paid'] ?? 0) / $total_due * 100, 1);
    $efficiencyMetrics['disbursement_rate'] = round(($row['completed'] ?? 0) / max(($total_due / 4303.32), 1) * 100, 1); // Rough estimate
}

// 4. DAILY ACTIVITY LOG
$dailyActivityLog = [];
$query = "
    SELECT 
        DATE_FORMAT(created_at, '%H:%i') AS time,
        COUNT(*) AS applications,
        SUM(CASE WHEN status = 'Approved' THEN final_loan_amount ELSE 0 END) AS approved_amount,
        MAX(final_loan_amount) AS highest_amount
    FROM loan_applications
    WHERE DATE(created_at) = CURDATE()
    GROUP BY DATE_FORMAT(created_at, '%H:00')
    ORDER BY time DESC
    LIMIT 12
";

$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $dailyActivityLog[] = $row;
    }
}

// 5. BORROWER SEGMENTATION
$borrowerSegmentation = [];
$query = "
    SELECT 
        CASE 
            WHEN la.final_loan_amount < 25000 THEN 'Small (< 25K)'
            WHEN la.final_loan_amount < 50000 THEN 'Medium (25K-50K)'
            WHEN la.final_loan_amount < 100000 THEN 'Large (50K-100K)'
            ELSE 'Premium (100K+)'
        END AS segment,
        COUNT(*) AS count,
        AVG(la.final_loan_amount) AS avg_amount,
        SUM(CASE WHEN la.status = 'Approved' THEN la.final_loan_amount ELSE 0 END) AS total_disbursed,
        AVG(CASE WHEN ps.status = 'Paid' THEN 1 ELSE 0 END) * 100 AS payment_completion_rate
    FROM loan_applications la
    LEFT JOIN loans l ON la.application_id = l.application_id
    LEFT JOIN payment_schedules ps ON l.loan_id = ps.loan_id
    GROUP BY segment
    ORDER BY avg_amount ASC
";

$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $borrowerSegmentation[] = $row;
    }
}

// 6. PROCESSING PIPELINE
$processingPipeline = [
    'new' => 0,
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'closed' => 0
];

$query = "
    SELECT 
        status,
        COUNT(*) AS count
    FROM loan_applications
    GROUP BY status
";

$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $status = strtolower($row['status']);
        if (isset($processingPipeline[$status])) {
            $processingPipeline[$status] = $row['count'];
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Operational Analytics - CYCLOAN</title>
    <link rel="stylesheet" href="CSS/admin_dashboard.css">
    <link rel="stylesheet" href="CSS/admin_profile.css">
    <link rel="stylesheet" href="CSS/superadmin_dashboard.css">
    <link rel="stylesheet" href="CSS/nav_active.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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

        .kpi-change {
            font-size: 12px;
            color: #4caf50;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .kpi-change.negative {
            color: #ff4757;
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

        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .table-responsive {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
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

        .status-badge.success {
            background: #d4edda;
            color: #155724;
        }

        .status-badge.warning {
            background: #fff3cd;
            color: #856404;
        }

        .status-badge.danger {
            background: #f8d7da;
            color: #721c24;
        }

        .metric-box {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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

            .kpi-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            table {
                font-size: 12px;
            }

            table th, table td {
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
                    <a href="predictive_analytics.php">
                        <i class="fas fa-crystal-ball"></i> Predictive Analytics
                    </a>
                    &nbsp;|&nbsp;
                    <a href="reports_analytics.php">
                        <i class="fas fa-chart-bar"></i> Advanced Reports
                    </a>
                </div>
            </div>

            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <i class="fas fa-tachometer-alt" style="font-size: 32px;"></i>
                </div>
                <div>
                    <h1>Operational Analytics</h1>
                    <p>Real-time performance monitoring and daily operations dashboard</p>
                </div>
            </div>

            <!-- REAL-TIME KPI DASHBOARD -->
            <div class="section">
                <div class="section-title">
                    <i class="fas fa-bolt"></i>
                    Real-Time KPIs - Today & This Month
                </div>

                <div class="kpi-grid">
                    <div class="kpi-card">
                        <div class="kpi-label">Applications Today</div>
                        <div class="kpi-value"><?php echo $realTimeKPIs['applications_today']; ?></div>
                        <div class="kpi-label" style="margin-top: 10px;">This Week: <?php echo $realTimeKPIs['applications_this_week']; ?></div>
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-label">Approvals Today</div>
                        <div class="kpi-value"><?php echo $realTimeKPIs['approvals_today']; ?></div>
                        <div class="kpi-label" style="margin-top: 10px;">This Month: <?php echo $realTimeKPIs['approvals_this_month']; ?></div>
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-label">Disbursements Today</div>
                        <div class="kpi-value">₱<?php echo number_format($realTimeKPIs['disbursements_today'], 0); ?></div>
                        <div class="kpi-label" style="margin-top: 10px;">Month: ₱<?php echo number_format($realTimeKPIs['disbursements_this_month'], 0); ?></div>
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-label">Collections Today</div>
                        <div class="kpi-value">₱<?php echo number_format($realTimeKPIs['collections_today'], 0); ?></div>
                        <div class="kpi-label" style="margin-top: 10px;">Month: ₱<?php echo number_format($realTimeKPIs['collections_this_month'], 0); ?></div>
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-label">Overdue Payments</div>
                        <div class="kpi-value" style="color: #ff4757;"><?php echo $realTimeKPIs['overdue_count']; ?></div>
                        <div class="kpi-change negative">
                            <i class="fas fa-arrow-up"></i> Attention Required
                        </div>
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-label">Pending Approvals</div>
                        <div class="kpi-value"><?php echo $realTimeKPIs['pending_approvals']; ?></div>
                        <div class="kpi-label" style="margin-top: 10px; color: #667eea;">Requires Action</div>
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-label">Avg Processing Days</div>
                        <div class="kpi-value"><?php echo $realTimeKPIs['average_processing_days']; ?></div>
                        <div class="kpi-label" style="margin-top: 10px;">From Apply to Approval</div>
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-label">Today's Update</div>
                        <div class="kpi-value" style="font-size: 16px;"><?php echo date('M d, Y'); ?></div>
                        <div class="kpi-label" style="margin-top: 10px; color: #4caf50;"><i class="fas fa-check-circle"></i> Live Data</div>
                    </div>
                </div>
            </div>

            <!-- PORTFOLIO HEALTH -->
            <div class="section">
                <div class="section-title">
                    <i class="fas fa-heart-pulse"></i>
                    Portfolio Health Snapshot
                </div>

                <div class="metric-box">
                    <div class="metric">
                        <div class="metric-number"><?php echo $portfolioHealth['total_active_loans']; ?></div>
                        <div class="metric-label">Total Active Loans</div>
                    </div>

                    <div class="metric">
                        <div class="metric-number" style="color: #4caf50;"><?php echo $portfolioHealth['current_status']; ?></div>
                        <div class="metric-label">Current (0 DPD)</div>
                    </div>

                    <div class="metric">
                        <div class="metric-number" style="color: #ffc107;"><?php echo $portfolioHealth['days_1_30']; ?></div>
                        <div class="metric-label">1-30 Days Overdue</div>
                    </div>

                    <div class="metric">
                        <div class="metric-number" style="color: #ff9800;"><?php echo $portfolioHealth['days_31_60']; ?></div>
                        <div class="metric-label">31-60 Days Overdue</div>
                    </div>

                    <div class="metric">
                        <div class="metric-number" style="color: #ff4757;"><?php echo $portfolioHealth['days_60_plus']; ?></div>
                        <div class="metric-label">60+ Days Overdue</div>
                    </div>

                    <div class="metric">
                        <div class="metric-number"><?php echo $portfolioHealth['health_percentage']; ?>%</div>
                        <div class="metric-label">Portfolio Health Score</div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $portfolioHealth['health_percentage']; ?>%;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- EFFICIENCY METRICS -->
            <div class="section">
                <div class="section-title">
                    <i class="fas fa-chart-line"></i>
                    Operational Efficiency Metrics (Last 30 Days)
                </div>

                <div class="metric-box">
                    <div class="metric">
                        <div class="metric-number"><?php echo $efficiencyMetrics['approval_rate']; ?>%</div>
                        <div class="metric-label">Approval Rate</div>
                    </div>

                    <div class="metric">
                        <div class="metric-number"><?php echo $efficiencyMetrics['collection_rate']; ?>%</div>
                        <div class="metric-label">Collection Rate</div>
                    </div>

                    <div class="metric">
                        <div class="metric-number">₱<?php echo number_format($efficiencyMetrics['avg_loan_size'], 0); ?></div>
                        <div class="metric-label">Average Loan Size</div>
                    </div>

                    <div class="metric">
                        <div class="metric-number"><?php echo $efficiencyMetrics['avg_term_length']; ?> mo</div>
                        <div class="metric-label">Average Term Length</div>
                    </div>
                </div>
            </div>

            <!-- PROCESSING PIPELINE -->
            <div class="section">
                <div class="section-title">
                    <i class="fas fa-stream"></i>
                    Application Processing Pipeline
                </div>

                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Count</th>
                                <th>Percentage</th>
                                <th>Visual</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total = array_sum($processingPipeline);
                            foreach ($processingPipeline as $status => $count): 
                                $percentage = $total > 0 ? ($count / $total) * 100 : 0;
                            ?>
                                <tr>
                                    <td>
                                        <span class="status-badge <?php echo $status === 'approved' ? 'success' : ($status === 'pending' ? 'warning' : 'danger'); ?>">
                                            <?php echo ucfirst($status); ?>
                                        </span>
                                    </td>
                                    <td><strong><?php echo $count; ?></strong></td>
                                    <td><?php echo round($percentage, 1); ?>%</td>
                                    <td>
                                        <div class="progress-bar" style="width: 100%;">
                                            <div class="progress-fill" style="width: <?php echo $percentage; ?>%;"></div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- BORROWER SEGMENTATION -->
            <div class="section">
                <div class="section-title">
                    <i class="fas fa-users"></i>
                    Borrower Segmentation Analysis
                </div>

                <?php if (!empty($borrowerSegmentation)): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Segment</th>
                                    <th>Count</th>
                                    <th>Avg Loan Amount</th>
                                    <th>Total Disbursed</th>
                                    <th>Payment Completion Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($borrowerSegmentation as $segment): ?>
                                    <tr>
                                        <td><strong><?php echo $segment['segment']; ?></strong></td>
                                        <td><?php echo $segment['count']; ?></td>
                                        <td>₱<?php echo number_format($segment['avg_amount'], 2); ?></td>
                                        <td>₱<?php echo number_format($segment['total_disbursed'], 2); ?></td>
                                        <td>
                                            <?php echo round($segment['payment_completion_rate'] ?? 0, 1); ?>%
                                            <div class="progress-bar">
                                                <div class="progress-fill" style="width: <?php echo $segment['payment_completion_rate'] ?? 0; ?>%;"></div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- DAILY ACTIVITY LOG -->
            <div class="section">
                <div class="section-title">
                    <i class="fas fa-history"></i>
                    Today's Activity Log (Hourly Breakdown)
                </div>

                <?php if (!empty($dailyActivityLog)): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Applications</th>
                                    <th>Approved Amount</th>
                                    <th>Highest Single Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dailyActivityLog as $log): ?>
                                    <tr>
                                        <td><strong><?php echo $log['time']; ?></strong></td>
                                        <td><?php echo $log['applications']; ?></td>
                                        <td>₱<?php echo number_format($log['approved_amount'], 2); ?></td>
                                        <td>₱<?php echo number_format($log['highest_amount'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; background: white; border-radius: 10px;">
                        <i class="fas fa-inbox" style="font-size: 48px; color: #ccc; margin-bottom: 15px;"></i>
                        <p style="color: #999;">No applications received today yet</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script>
        // Show success notification on page load
        window.addEventListener('load', function() {
            Toastify({
                text: '📊 Operational Analytics Dashboard Loaded',
                duration: 3000,
                gravity: 'top',
                position: 'right',
                backgroundColor: '#667eea'
            }).showToast();
        });

        // Auto-refresh KPIs every 5 minutes
        setInterval(function() {
            location.reload();
        }, 300000); // 5 minutes
    </script>
</body>

</html>
