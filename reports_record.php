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

// Fetch data for Loan Applications by Type Chart
$chartData = [];
$query = "SELECT lt.type_name, COALESCE(COUNT(la.application_id), 0) as count
          FROM loan_types lt
          LEFT JOIN loan_applications la ON lt.loan_type_id = la.loan_type_id
          GROUP BY lt.type_name";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $chartData[] = $row;
    }
    $result->free();
} else {
    error_log('Chart data fetch failed: ' . $conn->error, 3, 'errors.log');
    $chartData = [
        ['type_name' => 'Individual', 'count' => 0],
        ['type_name' => 'Cooperative', 'count' => 0]
    ];
}

// Fetch data for Loan Status Distribution Chart
$statusChartData = [];
$query = "SELECT status, COUNT(application_id) as count
          FROM loan_applications
          GROUP BY status";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $statusChartData[] = $row;
    }
    $result->free();
} else {
    error_log('Status chart data fetch failed: ' . $conn->error, 3, 'errors.log');
    $statusChartData = [];
}

// Fetch data for Applications Over Time (monthly)
$timeChartData = [];
$query = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(application_id) as count
          FROM loan_applications
          GROUP BY month
          ORDER BY month ASC
          LIMIT 12";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $timeChartData[] = $row;
    }
    $result->free();
} else {
    error_log('Time chart data fetch failed: ' . $conn->error, 3, 'errors.log');
    $timeChartData = [];
}

// Fetch summary stats
$summaryData = ['total_applications' => 0, 'total_disbursed' => 0, 'total_repaid' => 0, 'total_overdue' => 0];
$query = "SELECT 
            COUNT(DISTINCT la.application_id) as total_applications,
            SUM(la.final_loan_amount) as total_disbursed,
            (SELECT SUM(ps.amount_paid) FROM payment_schedules ps JOIN loans l ON ps.loan_id = l.loan_id) as total_repaid,
            (SELECT SUM(ps.amount - ps.amount_paid) FROM payment_schedules ps WHERE ps.status != 'Paid' AND ps.due_date < CURDATE()) as total_overdue
          FROM loan_applications la
          WHERE la.status IN ('Approved', 'Active')";
$result = $conn->query($query);
if ($result) {
    $summaryData = $result->fetch_assoc();
    $result->free();
} else {
    error_log('Summary data fetch failed: ' . $conn->error, 3, 'errors.log');
}

// ========== ADVANCED ANALYTICS DATA ==========

// Advanced KPI Data
$kpiData = [
    'total_applications' => 0,
    'approved_applications' => 0,
    'approval_rate' => 0,
    'total_disbursed' => 0,
    'total_repaid' => 0,
    'repayment_rate' => 0,
    'total_outstanding' => 0,
    'average_loan_amount' => 0,
    'default_rate' => 0
];

$query = "SELECT 
            COUNT(DISTINCT la.application_id) as total_applications,
            SUM(CASE WHEN la.status = 'Approved' THEN 1 ELSE 0 END) as approved_applications,
            SUM(CASE WHEN la.status = 'Approved' THEN la.final_loan_amount ELSE 0 END) as total_disbursed,
            AVG(CASE WHEN la.status = 'Approved' THEN la.final_loan_amount END) as average_loan_amount
          FROM loan_applications la";
$result = $conn->query($query);
if ($result && $row = $result->fetch_assoc()) {
    $kpiData['total_applications'] = $row['total_applications'] ?? 0;
    $kpiData['approved_applications'] = $row['approved_applications'] ?? 0;
    $kpiData['total_disbursed'] = $row['total_disbursed'] ?? 0;
    $kpiData['average_loan_amount'] = $row['average_loan_amount'] ?? 0;
    $kpiData['approval_rate'] = $row['total_applications'] > 0
        ? ($row['approved_applications'] / $row['total_applications']) * 100
        : 0;
}

// Calculate repayment metrics
$query = "SELECT 
            SUM(ps.amount_paid) as total_repaid,
            SUM(ps.amount) as total_expected,
            SUM(CASE WHEN ps.status = 'pending' THEN ps.amount - ps.amount_paid ELSE 0 END) as total_pending
          FROM payment_schedules ps 
          JOIN loans l ON ps.loan_id = l.loan_id";
$result = @$conn->query($query);
if ($result && $row = $result->fetch_assoc()) {
    $kpiData['total_repaid'] = $row['total_repaid'] ?? 0;
    $kpiData['total_outstanding'] = ($row['total_expected'] ?? 0) - ($row['total_repaid'] ?? 0);
    $kpiData['repayment_rate'] = ($row['total_expected'] ?? 0) > 0
        ? (($row['total_repaid'] ?? 0) / $row['total_expected']) * 100
        : 0;
    $kpiData['default_rate'] = ($row['total_expected'] ?? 0) > 0
        ? (($row['total_pending'] ?? 0) / $row['total_expected']) * 100
        : 0;
}

// Monthly Trend Analysis (Last 12 months)
$monthlyTrendsAdvanced = [];
$query = "SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as applications,
            SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'Approved' THEN final_loan_amount ELSE 0 END) as disbursed
          FROM loan_applications
          WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
          GROUP BY month
          ORDER BY month ASC";
$result = @$conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $monthlyTrendsAdvanced[] = $row;
    }
}

// Loan Performance by Type
$loanTypePerformance = [];
$query = "SELECT 
            lt.type_name,
            COUNT(la.application_id) as total_count,
            SUM(CASE WHEN la.status = 'Approved' THEN 1 ELSE 0 END) as approved_count,
            AVG(CASE WHEN la.status = 'Approved' THEN la.final_loan_amount END) as avg_amount,
            SUM(CASE WHEN la.status = 'Approved' THEN la.final_loan_amount ELSE 0 END) as total_disbursed
          FROM loan_types lt
          LEFT JOIN loan_applications la ON lt.loan_type_id = la.loan_type_id
          GROUP BY lt.type_name";
$result = @$conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $loanTypePerformance[] = $row;
    }
}

// Risk Analysis - Loans by Credit Status
$creditRiskAnalysis = [];
$query = "SELECT 
            la.credit_investigation_status as risk_category,
            COUNT(*) as count,
            AVG(la.final_loan_amount) as avg_amount,
            SUM(CASE WHEN la.status = 'Approved' THEN la.final_loan_amount ELSE 0 END) as total_disbursed
          FROM loan_applications la
          GROUP BY la.credit_investigation_status";
$result = @$conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $creditRiskAnalysis[] = $row;
    }
}

// Repayment Performance Analysis
$repaymentPerformance = [];
$query = "SELECT 
            ps.status,
            COUNT(*) as count,
            SUM(ps.amount) as total_amount,
            SUM(ps.amount_paid) as amount_paid
          FROM payment_schedules ps
          GROUP BY ps.status";
$result = @$conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $repaymentPerformance[] = $row;
    }
}

// Top Borrowers Analysis
$topBorrowers = [];
$query = "SELECT 
            CONCAT(u.first_name, ' ', u.last_name) as borrower_name,
            COUNT(la.application_id) as loan_count,
            SUM(CASE WHEN la.status = 'Approved' THEN la.final_loan_amount ELSE 0 END) as total_borrowed,
            AVG(CASE WHEN la.status = 'Approved' THEN la.final_loan_amount END) as avg_loan_amount
          FROM users1 u
          JOIN loan_applications la ON u.id = la.user_id
          WHERE la.status = 'Approved'
          GROUP BY u.id
          ORDER BY total_borrowed DESC
          LIMIT 10";
$result = @$conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $topBorrowers[] = $row;
    }
}

// Loan Purpose Analysis
$purposeAnalysis = [];
$query = "SELECT 
            purpose,
            COUNT(*) as count,
            SUM(CASE WHEN status = 'Approved' THEN final_loan_amount ELSE 0 END) as total_amount,
            AVG(CASE WHEN status = 'Approved' THEN term_length END) as avg_term
          FROM loan_applications
          WHERE purpose IS NOT NULL AND purpose != ''
          GROUP BY purpose
          ORDER BY count DESC";
$result = @$conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $purposeAnalysis[] = $row;
    }
}

// Project Type Distribution
$projectTypeDistribution = [];
$query = "SELECT 
            project_type,
            COUNT(*) as count,
            SUM(CASE WHEN status = 'Approved' THEN final_loan_amount ELSE 0 END) as total_amount
          FROM loan_applications
          WHERE project_type IS NOT NULL AND project_type != ''
          GROUP BY project_type";
$result = @$conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $projectTypeDistribution[] = $row;
    }
}

// Comparative Analysis - Current vs Previous Period
$currentMonth = date('Y-m');
$previousMonth = date('Y-m', strtotime('-1 month'));

$comparativeData = [
    'current' => ['applications' => 0, 'approved' => 0, 'disbursed' => 0],
    'previous' => ['applications' => 0, 'approved' => 0, 'disbursed' => 0]
];

$query = "SELECT 
            COUNT(*) as applications,
            SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'Approved' THEN final_loan_amount ELSE 0 END) as disbursed
          FROM loan_applications
          WHERE DATE_FORMAT(created_at, '%Y-%m') = ?";

$stmt = $conn->prepare($query);
if ($stmt) {
    // Get current month data
    $stmt->bind_param("s", $currentMonth);
    $stmt->execute();
    $stmt->bind_result($curr_apps, $curr_approved, $curr_disbursed);
    if ($stmt->fetch()) {
        $comparativeData['current'] = [
            'applications' => $curr_apps ?? 0,
            'approved' => $curr_approved ?? 0,
            'disbursed' => $curr_disbursed ?? 0
        ];
    }
    $stmt->close();

    // Get previous month data
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $previousMonth);
    $stmt->execute();
    $stmt->bind_result($prev_apps, $prev_approved, $prev_disbursed);
    if ($stmt->fetch()) {
        $comparativeData['previous'] = [
            'applications' => $prev_apps ?? 0,
            'approved' => $prev_approved ?? 0,
            'disbursed' => $prev_disbursed ?? 0
        ];
    }
    $stmt->close();
}

// Calculate percentage changes
$comparativeData['change'] = [
    'applications' => ($comparativeData['previous']['applications'] ?? 0) > 0
        ? ((($comparativeData['current']['applications'] ?? 0) - ($comparativeData['previous']['applications'] ?? 0)) / ($comparativeData['previous']['applications'] ?? 1)) * 100
        : 0,
    'approved' => ($comparativeData['previous']['approved'] ?? 0) > 0
        ? ((($comparativeData['current']['approved'] ?? 0) - ($comparativeData['previous']['approved'] ?? 0)) / ($comparativeData['previous']['approved'] ?? 1)) * 100
        : 0,
    'disbursed' => ($comparativeData['previous']['disbursed'] ?? 0) > 0
        ? ((($comparativeData['current']['disbursed'] ?? 0) - ($comparativeData['previous']['disbursed'] ?? 0)) / ($comparativeData['previous']['disbursed'] ?? 1)) * 100
        : 0,
];

// ========== REAL-TIME OPERATIONAL DASHBOARD ==========

// Real-Time KPIs (Today, This Week, This Month)
$today = date('Y-m-d');
$weekStart = date('Y-m-d', strtotime('monday this week'));
$monthStart = date('Y-m-01');

$realTimeKPIs = [
    'today' => ['applications' => 0, 'approvals' => 0, 'disbursements' => 0, 'collections' => 0],
    'week' => ['applications' => 0, 'approvals' => 0, 'disbursements' => 0, 'collections' => 0],
    'month' => ['applications' => 0, 'approvals' => 0, 'disbursements' => 0, 'collections' => 0]
];

// Today's metrics
$query = "SELECT 
            COUNT(*) as applications,
            SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approvals,
            SUM(CASE WHEN status = 'Active' THEN final_loan_amount ELSE 0 END) as disbursements
          FROM loan_applications
          WHERE DATE(created_at) = ?";
$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $stmt->bind_result($today_apps, $today_approvals, $today_disbursements);
    if ($stmt->fetch()) {
        $realTimeKPIs['today']['applications'] = $today_apps ?? 0;
        $realTimeKPIs['today']['approvals'] = $today_approvals ?? 0;
        $realTimeKPIs['today']['disbursements'] = $today_disbursements ?? 0;
    }
    $stmt->close();
}

// This week's metrics
$query = "SELECT 
            COUNT(*) as applications,
            SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approvals,
            SUM(CASE WHEN status = 'Active' THEN final_loan_amount ELSE 0 END) as disbursements
          FROM loan_applications
          WHERE DATE(created_at) >= ? AND DATE(created_at) <= ?";
$stmt = $conn->prepare($query);
if ($stmt) {
    $weekEnd = date('Y-m-d');
    $stmt->bind_param("ss", $weekStart, $weekEnd);
    $stmt->execute();
    $stmt->bind_result($week_apps, $week_approvals, $week_disbursements);
    if ($stmt->fetch()) {
        $realTimeKPIs['week']['applications'] = $week_apps ?? 0;
        $realTimeKPIs['week']['approvals'] = $week_approvals ?? 0;
        $realTimeKPIs['week']['disbursements'] = $week_disbursements ?? 0;
    }
    $stmt->close();
}

// This month's metrics
$monthEnd = date('Y-m-t');
$query = "SELECT 
            COUNT(*) as applications,
            SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approvals,
            SUM(CASE WHEN status = 'Active' THEN final_loan_amount ELSE 0 END) as disbursements
          FROM loan_applications
          WHERE DATE(created_at) >= ? AND DATE(created_at) <= ?";
$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param("ss", $monthStart, $monthEnd);
    $stmt->execute();
    $stmt->bind_result($month_apps, $month_approvals, $month_disbursements);
    if ($stmt->fetch()) {
        $realTimeKPIs['month']['applications'] = $month_apps ?? 0;
        $realTimeKPIs['month']['approvals'] = $month_approvals ?? 0;
        $realTimeKPIs['month']['disbursements'] = $month_disbursements ?? 0;
    }
    $stmt->close();
}

// Collection metrics (this month)
$query = "SELECT SUM(ps.amount_paid) as collections
          FROM payment_schedules ps
          WHERE DATE(ps.updated_at) >= ? AND DATE(ps.updated_at) <= ?";
$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param("ss", $monthStart, $monthEnd);
    $stmt->execute();
    $stmt->bind_result($collections);
    if ($stmt->fetch()) {
        $realTimeKPIs['month']['collections'] = $collections ?? 0;
    }
    $stmt->close();
}

// Processing Pipeline Metrics
$processingPipeline = [
    'pending' => 0,
    'under_review' => 0,
    'approved_pending_disbursement' => 0,
    'rejected' => 0
];

$query = "SELECT 
            SUM(CASE WHEN la.status = 'Pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN la.status = 'Under Review' THEN 1 ELSE 0 END) as under_review,
            SUM(CASE WHEN la.status = 'Approved' AND l.loan_id IS NULL THEN 1 ELSE 0 END) as approved_pending,
            SUM(CASE WHEN la.status = 'Rejected' THEN 1 ELSE 0 END) as rejected
          FROM loan_applications la
          LEFT JOIN loans l ON la.application_id = l.application_id";
$result = $conn->query($query);
if ($result && $row = $result->fetch_assoc()) {
    $processingPipeline['pending'] = $row['pending'] ?? 0;
    $processingPipeline['under_review'] = $row['under_review'] ?? 0;
    $processingPipeline['approved_pending_disbursement'] = $row['approved_pending'] ?? 0;
    $processingPipeline['rejected'] = $row['rejected'] ?? 0;
}

// Portfolio Health Snapshot
$portfolioHealth = [
    'total_active_loans' => 0,
    'on_track' => 0,
    'at_risk' => 0,
    'overdue' => 0,
    'in_default' => 0,
    'health_percentage' => 0
];

$query = "SELECT 
            COUNT(DISTINCT l.loan_id) as total_active,
            SUM(CASE WHEN ps.status = 'pending' AND ps.due_date >= CURDATE() THEN 1 ELSE 0 END) as on_track,
            SUM(CASE WHEN ps.status = 'pending' AND ps.due_date < CURDATE() AND DATEDIFF(CURDATE(), ps.due_date) <= 30 THEN 1 ELSE 0 END) as at_risk,
            SUM(CASE WHEN ps.status = 'pending' AND DATEDIFF(CURDATE(), ps.due_date) > 30 AND DATEDIFF(CURDATE(), ps.due_date) <= 90 THEN 1 ELSE 0 END) as overdue,
            SUM(CASE WHEN ps.status = 'pending' AND DATEDIFF(CURDATE(), ps.due_date) > 90 THEN 1 ELSE 0 END) as in_default
          FROM loans l
          JOIN payment_schedules ps ON l.loan_id = ps.loan_id
          WHERE l.status = 'Active'";
$result = $conn->query($query);
if ($result && $row = $result->fetch_assoc()) {
    $portfolioHealth['total_active_loans'] = $row['total_active'] ?? 0;
    $portfolioHealth['on_track'] = $row['on_track'] ?? 0;
    $portfolioHealth['at_risk'] = $row['at_risk'] ?? 0;
    $portfolioHealth['overdue'] = $row['overdue'] ?? 0;
    $portfolioHealth['in_default'] = $row['in_default'] ?? 0;

    $totalPayments = ($row['on_track'] ?? 0) + ($row['at_risk'] ?? 0) + ($row['overdue'] ?? 0) + ($row['in_default'] ?? 0);
    $portfolioHealth['health_percentage'] = $totalPayments > 0
        ? round((($row['on_track'] ?? 0) / $totalPayments) * 100, 1)
        : 0;
}

// ========== PREDICTIVE RISK MODELS ==========

// Default Risk Prediction Model (4-factor scoring)
$defaultRiskScores = [];
$query = "SELECT 
            la.application_id,
            CONCAT(u.first_name, ' ', u.last_name) as borrower_name,
            la.final_loan_amount,
            la.term_length,
            la.credit_investigation_status,
            fi.net_income,
            COUNT(DISTINCT ps.payment_id) as payment_schedules_count,
            SUM(CASE WHEN ps.status = 'Paid' THEN 1 ELSE 0 END) as paid_schedules,
            ROUND(
                (CASE WHEN la.credit_investigation_status = 'Approved' THEN 20 ELSE 80 END) +
                (CASE WHEN fi.net_income > 100000 THEN 10 ELSE 50 END) +
                (CASE WHEN la.term_length <= 12 THEN 15 ELSE 30 END) +
                (CASE WHEN COUNT(DISTINCT ps.payment_id) > 0 
                      THEN (SUM(CASE WHEN ps.status = 'Paid' THEN 1 ELSE 0 END) / COUNT(DISTINCT ps.payment_id)) * 25
                      ELSE 0 END), 1
            ) as risk_score
          FROM loan_applications la
          JOIN users1 u ON la.user_id = u.id
          LEFT JOIN financial_info fi ON u.id = fi.user_id
          LEFT JOIN loans l ON la.application_id = l.application_id
          LEFT JOIN payment_schedules ps ON l.loan_id = ps.loan_id
          WHERE la.status IN ('Approved', 'Active')
          GROUP BY la.application_id
          ORDER BY risk_score ASC
          LIMIT 20";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Classify risk level
        $risk_level = $row['risk_score'] < 40 ? 'HIGH RISK' : ($row['risk_score'] < 70 ? 'MEDIUM RISK' : 'LOW RISK');
        $row['risk_level'] = $risk_level;
        $defaultRiskScores[] = $row;
    }
}

// Prepayment Likelihood Model
$prepaymentPrediction = [];
$query = "SELECT 
            u.id as user_id,
            CONCAT(u.first_name, ' ', u.last_name) as borrower_name,
            COUNT(DISTINCT la.application_id) as total_applications,
            SUM(CASE WHEN la.status = 'Active' THEN la.final_loan_amount ELSE 0 END) as active_loans,
            SUM(CASE WHEN l.status = 'closed' THEN 1 ELSE 0 END) as prepaid_count,
            ROUND(
                (CASE WHEN SUM(CASE WHEN l.status = 'closed' THEN 1 ELSE 0 END) > 0 THEN 40 ELSE 10 END) +
                (CASE WHEN fi.net_income > 150000 THEN 30 ELSE 15 END) +
                (CASE WHEN COUNT(DISTINCT la.application_id) > 1 THEN 20 ELSE 5 END) +
                (CASE WHEN DATEDIFF(CURDATE(), MAX(la.created_at)) < 180 THEN 15 ELSE 5 END), 1
            ) as prepayment_likelihood
          FROM users1 u
          LEFT JOIN loan_applications la ON u.id = la.user_id
          LEFT JOIN loans l ON la.application_id = l.application_id
          LEFT JOIN financial_info fi ON u.id = fi.user_id
          WHERE la.status IN ('Approved', 'Active')
          GROUP BY u.id
          ORDER BY prepayment_likelihood DESC
          LIMIT 15";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $prepayment_probability = min($row['prepayment_likelihood'], 100);
        $row['prepayment_probability'] = $prepayment_probability;
        $prepaymentPrediction[] = $row;
    }
}

// Portfolio Risk Trend (Last 6 months)
$portfolioRiskTrend = [];
$query = "SELECT 
            DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL (6 - MONTH(CURDATE())) MONTH), '%Y-%m') as month,
            COUNT(*) as total_schedules,
            SUM(CASE WHEN status = 'Paid' THEN 1 ELSE 0 END) as paid_schedules,
            SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_schedules,
            ROUND((SUM(CASE WHEN status = 'Paid' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as health_percentage
          FROM payment_schedules
          WHERE due_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
          GROUP BY YEAR(due_date), MONTH(due_date)
          ORDER BY month ASC";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $portfolioRiskTrend[] = $row;
    }
}

// Revenue Forecast (12-month projection)
$revenueForecasts = [];
$currentMonthRevenue = 0;
$query = "SELECT SUM(ps.amount_paid) as current_collections
          FROM payment_schedules ps
          WHERE DATE_FORMAT(ps.updated_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
$result = $conn->query($query);
if ($result && $row = $result->fetch_assoc()) {
    $currentMonthRevenue = $row['current_collections'] ?? 0;
}

// Generate 12-month forecast based on current trends
for ($i = 1; $i <= 12; $i++) {
    $forecastDate = date('Y-m', strtotime("+$i months"));
    $growthFactor = 1 + (0.02 * $i); // Assuming 2% monthly growth
    $forecastAmount = $currentMonthRevenue * $growthFactor * (0.95 + (rand(0, 10) / 100)); // Add some variance

    $revenueForecasts[] = [
        'month' => $forecastDate,
        'forecasted_revenue' => round($forecastAmount, 2),
        'confidence' => 95 - ($i * 1.5) // Confidence decreases over time
    ];
}

// Churn Risk Model
$churnRiskAnalysis = [];
$query = "SELECT 
            u.id as user_id,
            CONCAT(u.first_name, ' ', u.last_name) as borrower_name,
            MAX(la.created_at) as last_application_date,
            COUNT(DISTINCT la.application_id) as total_applications,
            DATEDIFF(CURDATE(), MAX(la.created_at)) as days_inactive,
            ROUND(
                (CASE WHEN DATEDIFF(CURDATE(), MAX(la.created_at)) > 365 THEN 50 ELSE 10 END) +
                (CASE WHEN COUNT(DISTINCT la.application_id) = 1 THEN 30 ELSE 0 END) +
                (CASE WHEN MAX(la.status) != 'Active' THEN 20 ELSE 0 END), 1
            ) as churn_risk_score
          FROM users1 u
          LEFT JOIN loan_applications la ON u.id = la.user_id
          GROUP BY u.id
          HAVING churn_risk_score > 0
          ORDER BY churn_risk_score DESC
          LIMIT 15";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $risk_level = $row['churn_risk_score'] > 70 ? 'HIGH' : ($row['churn_risk_score'] > 40 ? 'MEDIUM' : 'LOW');
        $row['churn_risk_level'] = $risk_level;
        $churnRiskAnalysis[] = $row;
    }
}

// ========== END PREDICTIVE RISK MODELS ==========

// ========== PORTFOLIO FORECASTING ==========

// Loan Maturity Schedule (Next 12 months)
$loanMaturitySchedule = [];
$query = "SELECT 
            DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL (MONTH(CURDATE()) - MONTH(l.created_at) + (YEAR(l.created_at) - YEAR(CURDATE())) * 12 + la.term_length) MONTH), '%Y-%m') as maturity_month,
            COUNT(*) as maturing_loans,
            SUM(l.total_principal) as total_principal,
            SUM(CASE WHEN ps.status = 'Paid' THEN ps.amount_paid ELSE 0 END) as already_collected
          FROM loans l
          LEFT JOIN loan_applications la ON l.application_id = la.application_id
          LEFT JOIN payment_schedules ps ON l.loan_id = ps.loan_id
          WHERE l.status = 'active'
          GROUP BY maturity_month
          ORDER BY maturity_month ASC
          LIMIT 12";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $loanMaturitySchedule[] = $row;
    }
}

// Portfolio Growth Projection (Next 6 months)
$portfolioGrowthProjection = [];
$currentTotalLoans = 0;
$query = "SELECT COUNT(*) as total_active FROM loans WHERE status = 'Active'";
$result = $conn->query($query);
if ($result && $row = $result->fetch_assoc()) {
    $currentTotalLoans = $row['total_active'] ?? 0;
}

for ($i = 1; $i <= 6; $i++) {
    $projectionMonth = date('Y-m', strtotime("+$i months"));
    // Assuming 5% monthly growth in loan portfolio
    $projectedLoans = round($currentTotalLoans * pow(1.05, $i));
    $projectionAmount = $kpiData['total_disbursed'] * pow(1.05, $i);

    $portfolioGrowthProjection[] = [
        'month' => $projectionMonth,
        'projected_loans' => $projectedLoans,
        'projected_amount' => round($projectionAmount, 2),
        'growth_rate' => 5.0
    ];
}

// Interest Income Forecast (Next 12 months)
$interestIncomeForecasts = [];
$currentMonthInterest = 0;
$query = "SELECT SUM((ps.amount - ps.amount_paid) * 0.02) as estimated_interest
          FROM payment_schedules ps
          WHERE ps.status = 'pending'";
$result = $conn->query($query);
if ($result && $row = $result->fetch_assoc()) {
    $currentMonthInterest = $row['estimated_interest'] ?? 0;
}

for ($i = 1; $i <= 12; $i++) {
    $forecastMonth = date('Y-m', strtotime("+$i months"));
    // Interest income grows as collections increase
    $forecastedInterest = $currentMonthInterest * (1 + (0.015 * $i));

    $interestIncomeForecasts[] = [
        'month' => $forecastMonth,
        'forecasted_interest' => round($forecastedInterest, 2),
        'projected_collections' => round($currentMonthInterest * 50, 2) // Collection ratio
    ];
}

// ========== CUSTOMER ANALYTICS ==========

// Customer Segmentation by Loan Amount
$customerSegmentation = [];
$query = "SELECT 
            CASE 
                WHEN la.final_loan_amount < 50000 THEN 'Micro Loans (<₱50K)'
                WHEN la.final_loan_amount >= 50000 AND la.final_loan_amount < 200000 THEN 'Small Loans (₱50K-200K)'
                WHEN la.final_loan_amount >= 200000 AND la.final_loan_amount < 500000 THEN 'Medium Loans (₱200K-500K)'
                ELSE 'Large Loans (>₱500K)'
            END as segment,
            COUNT(*) as customer_count,
            COUNT(DISTINCT u.id) as unique_customers,
            AVG(la.final_loan_amount) as avg_loan_amount,
            SUM(la.final_loan_amount) as total_amount,
            ROUND(AVG(CASE WHEN l.status = 'active' THEN 1 ELSE 0 END) * 100, 1) as active_percentage
          FROM loan_applications la
          LEFT JOIN loans l ON la.application_id = l.application_id
          LEFT JOIN users1 u ON la.user_id = u.id
          WHERE la.status IN ('Approved', 'Active')
          GROUP BY segment
          ORDER BY CAST(SUBSTRING_INDEX(segment, '₱', -1) AS DECIMAL)";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $customerSegmentation[] = $row;
    }
}

// Customer Loyalty Analysis
$customerLoyalty = [];
$query = "SELECT 
            u.id as user_id,
            CONCAT(u.first_name, ' ', u.last_name) as customer_name,
            COUNT(DISTINCT la.application_id) as repeat_applications,
            SUM(CASE WHEN la.status = 'Approved' THEN 1 ELSE 0 END) as successful_applications,
            SUM(CASE WHEN la.status = 'Approved' THEN la.final_loan_amount ELSE 0 END) as total_borrowed,
            SUM(CASE WHEN ps.status = 'Paid' THEN ps.amount_paid ELSE 0 END) as total_repaid,
            DATEDIFF(MAX(la.created_at), MIN(la.created_at)) as customer_tenure_days,
            CASE 
                WHEN COUNT(DISTINCT la.application_id) >= 3 AND SUM(CASE WHEN ps.status = 'Paid' THEN 1 ELSE 0 END) >= COUNT(*) * 0.8 THEN 'Platinum'
                WHEN COUNT(DISTINCT la.application_id) >= 2 AND SUM(CASE WHEN ps.status = 'Paid' THEN 1 ELSE 0 END) >= COUNT(*) * 0.6 THEN 'Gold'
                WHEN COUNT(DISTINCT la.application_id) >= 1 THEN 'Silver'
                ELSE 'Bronze'
            END as loyalty_tier
          FROM users1 u
          LEFT JOIN loan_applications la ON u.id = la.user_id
          LEFT JOIN loans l ON la.application_id = l.application_id
          LEFT JOIN payment_schedules ps ON l.loan_id = ps.loan_id
          WHERE la.status IN ('Approved', 'Active')
          GROUP BY u.id
          HAVING repeat_applications > 0
          ORDER BY total_borrowed DESC
          LIMIT 20";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $customerLoyalty[] = $row;
    }
}

// Geographic Distribution (if location data available)
$geographicDistribution = [];
$query = "SELECT 
            COALESCE(u.res_barangay, 'Unknown') as location,
            COUNT(DISTINCT u.id) as customer_count,
            COUNT(DISTINCT la.application_id) as loan_count,
            SUM(CASE WHEN la.status = 'Approved' THEN la.final_loan_amount ELSE 0 END) as total_amount,
            AVG(la.final_loan_amount) as avg_loan_size
          FROM users1 u
          LEFT JOIN loan_applications la ON u.id = la.user_id
          WHERE la.status IN ('Approved', 'Active')
          GROUP BY u.res_barangay
          ORDER BY total_amount DESC
          LIMIT 10";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $geographicDistribution[] = $row;
    }
}

// ========== ADVANCED KPI CARDS DATA ==========

// Calculate additional KPIs
$advancedKPIs = [
    'average_processing_time' => 0,
    'first_time_approval_rate' => 0,
    'borrower_retention_rate' => 0,
    'collection_effectiveness' => 0,
    'portfolio_yield' => 0,
    'average_monthly_growth' => 0,
    'disbursement_efficiency' => 0,
    'customer_acquisition_rate' => 0
];

// Average Processing Time (days)
$query = "SELECT AVG(DATEDIFF(la.updated_at, la.created_at)) as avg_processing_days
          FROM loan_applications la
          WHERE la.status IN ('Approved', 'Active')
          AND la.updated_at IS NOT NULL";
$result = $conn->query($query);
if ($result && $row = $result->fetch_assoc()) {
    $advancedKPIs['average_processing_time'] = round($row['avg_processing_days'] ?? 0, 1);
}

// First-Time Approval Rate
$query = "SELECT 
            COUNT(DISTINCT CASE WHEN total_apps = 1 AND status = 'Approved' THEN user_id END) as first_time_approved,
            COUNT(DISTINCT CASE WHEN total_apps = 1 THEN user_id END) as first_time_applicants
          FROM (
            SELECT u.id as user_id, la.status, COUNT(*) as total_apps
            FROM users1 u
            JOIN loan_applications la ON u.id = la.user_id
            GROUP BY u.id
          ) subquery";
$result = $conn->query($query);
if ($result && $row = $result->fetch_assoc()) {
    $firstTimeApproved = $row['first_time_approved'] ?? 0;
    $firstTimeApplicants = $row['first_time_applicants'] ?? 0;
    $advancedKPIs['first_time_approval_rate'] = $firstTimeApplicants > 0 ? round(($firstTimeApproved / $firstTimeApplicants) * 100, 1) : 0;
}

// Borrower Retention Rate
$query = "SELECT 
            COUNT(DISTINCT CASE WHEN repeat_applications > 1 THEN user_id END) as repeat_borrowers,
            COUNT(DISTINCT user_id) as total_borrowers
          FROM (
            SELECT u.id as user_id, COUNT(*) as repeat_applications
            FROM users1 u
            JOIN loan_applications la ON u.id = la.user_id
            WHERE la.status IN ('Approved', 'Active')
            GROUP BY u.id
          ) subquery";
$result = $conn->query($query);
if ($result && $row = $result->fetch_assoc()) {
    $repeatBorrowers = $row['repeat_borrowers'] ?? 0;
    $totalBorrowers = $row['total_borrowers'] ?? 0;
    $advancedKPIs['borrower_retention_rate'] = $totalBorrowers > 0 ? round(($repeatBorrowers / $totalBorrowers) * 100, 1) : 0;
}

// Collection Effectiveness (collected vs expected)
$query = "SELECT 
            SUM(ps.amount_paid) as total_collected,
            SUM(ps.amount) as total_expected,
            COUNT(DISTINCT CASE WHEN ps.status = 'Paid' THEN ps.payment_id END) as completed_payments
          FROM payment_schedules ps
          JOIN loans l ON ps.loan_id = l.loan_id
          WHERE l.status = 'active'";
$result = $conn->query($query);
if ($result && $row = $result->fetch_assoc()) {
    $collected = $row['total_collected'] ?? 0;
    $expected = $row['total_expected'] ?? 0;
    $advancedKPIs['collection_effectiveness'] = $expected > 0 ? round(($collected / $expected) * 100, 1) : 0;
}

// Portfolio Yield (annualized return)
$query = "SELECT 
            SUM(CASE WHEN l.interest_rate > 0 THEN (l.total_principal * (l.interest_rate / 100)) ELSE 0 END) as annual_interest
          FROM loans l
          WHERE l.status = 'active'";
$result = $conn->query($query);
if ($result && $row = $result->fetch_assoc()) {
    $annualInterest = $row['annual_interest'] ?? 0;
    $advancedKPIs['portfolio_yield'] = $kpiData['total_disbursed'] > 0 ? round(($annualInterest / $kpiData['total_disbursed']) * 100, 1) : 0;
}

// Average Monthly Growth Rate
$query = "SELECT 
            AVG(monthly_growth) as avg_growth
          FROM (
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as applications,
                LAG(COUNT(*)) OVER (ORDER BY DATE_FORMAT(created_at, '%Y-%m')) as prev_month,
                ((COUNT(*) - LAG(COUNT(*)) OVER (ORDER BY DATE_FORMAT(created_at, '%Y-%m'))) / LAG(COUNT(*)) OVER (ORDER BY DATE_FORMAT(created_at, '%Y-%m')) * 100) as monthly_growth
            FROM loan_applications
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY month
          ) growth_data
          WHERE monthly_growth IS NOT NULL";
$result = $conn->query($query);
if ($result && $row = $result->fetch_assoc()) {
    $advancedKPIs['average_monthly_growth'] = round($row['avg_growth'] ?? 0, 1);
}

// Disbursement Efficiency (approved vs actually disbursed)
$query = "SELECT 
            COUNT(DISTINCT CASE WHEN la.status = 'Approved' THEN la.application_id END) as approved_count,
            COUNT(DISTINCT l.loan_id) as disbursed_count
          FROM loan_applications la
          LEFT JOIN loans l ON la.application_id = l.application_id";
$result = $conn->query($query);
if ($result && $row = $result->fetch_assoc()) {
    $approved = $row['approved_count'] ?? 0;
    $disbursed = $row['disbursed_count'] ?? 0;
    $advancedKPIs['disbursement_efficiency'] = $approved > 0 ? round(($disbursed / $approved) * 100, 1) : 0;
}

// Customer Acquisition Rate (this month vs last month)
$thisMonthAcquisitions = 0;
$lastMonthAcquisitions = 0;
$query = "SELECT COUNT(DISTINCT user_id) as acquisitions
          FROM loan_applications
          WHERE DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
          AND user_id NOT IN (
            SELECT DISTINCT user_id FROM loan_applications
            WHERE DATE_FORMAT(created_at, '%Y-%m') < DATE_FORMAT(CURDATE(), '%Y-%m')
          )";
$result = $conn->query($query);
if ($result && $row = $result->fetch_assoc()) {
    $thisMonthAcquisitions = $row['acquisitions'] ?? 0;
}

$advancedKPIs['customer_acquisition_rate'] = $thisMonthAcquisitions;

// ========== END ADVANCED ANALYTICS ==========


// Fetch data for Due Accounts
$dueFilters = [];
$dueQuery = "SELECT ps.due_date, CONCAT(u.first_name, ' ', u.last_name) as account_holder, ps.amount - ps.amount_paid as due_amount, lt.type_name
             FROM payment_schedules ps
             JOIN loans l ON ps.loan_id = l.loan_id
             JOIN loan_applications la ON l.application_id = la.application_id
             JOIN users1 u ON la.user_id = u.id
             JOIN loan_types lt ON la.loan_type_id = lt.loan_type_id
             WHERE ps.status != 'Paid'
             AND la.status = 'Active'
             AND ps.due_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
$dueParams = [];
$dueTypes = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dueLoanType = isset($_POST['dueLoanType']) ? htmlspecialchars(trim($_POST['dueLoanType'])) : '';
    $dueStartDate = isset($_POST['dueStartDate']) ? htmlspecialchars(trim($_POST['dueStartDate'])) : '';
    $dueEndDate = isset($_POST['dueEndDate']) ? htmlspecialchars(trim($_POST['dueEndDate'])) : '';

    if (!empty($dueLoanType)) {
        $dueQuery .= " AND lt.type_name = ?";
        $dueParams[] = $dueLoanType;
        $dueFilters['Loan Type'] = $dueLoanType;
        $dueTypes[] = 's';
    }
    if (!empty($dueStartDate)) {
        $dueQuery .= " AND ps.due_date >= ?";
        $dueParams[] = $dueStartDate;
        $dueFilters['Start Date'] = $dueStartDate;
        $dueTypes[] = 's';
    }
    if (!empty($dueEndDate)) {
        $dueQuery .= " AND ps.due_date <= ?";
        $dueParams[] = $dueEndDate;
        $dueFilters['End Date'] = $dueEndDate;
        $dueTypes[] = 's';
    }
}
$dueQuery .= " ORDER BY ps.due_date ASC";
$dueAccountsData = [];
if (empty($dueParams)) {
    $result = $conn->query($dueQuery);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $dueAccountsData[] = $row;
        }
        $result->free();
    } else {
        error_log('Due Accounts data fetch failed: ' . $conn->error, 3, 'errors.log');
    }
} else {
    $stmt = $conn->prepare($dueQuery);
    if ($stmt) {
        $stmt->bind_param(implode('', $dueTypes), ...$dueParams);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $dueAccountsData[] = $row;
        }
        $stmt->close();
    } else {
        error_log('Due Accounts prepare failed: ' . $conn->error, 3, 'errors.log');
    }
}

// Handle Custom Report Generation
$reportData = [];
$filters = [];
$reportSummary = ['total_applications' => 0, 'total_amount' => 0, 'average_term' => 0];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loanType = isset($_POST['loanType']) ? htmlspecialchars(trim($_POST['loanType'])) : '';
    $loanStatus = isset($_POST['loanStatus']) ? htmlspecialchars(trim($_POST['loanStatus'])) : '';
    $repaymentFrequency = isset($_POST['repaymentFrequency']) ? htmlspecialchars(trim($_POST['repaymentFrequency'])) : '';
    $purpose = isset($_POST['purpose']) ? htmlspecialchars(trim($_POST['purpose'])) : '';
    $projectType = isset($_POST['projectType']) ? htmlspecialchars(trim($_POST['projectType'])) : '';
    $startDate = isset($_POST['startDate']) ? htmlspecialchars(trim($_POST['startDate'])) : '';
    $endDate = isset($_POST['endDate']) ? htmlspecialchars(trim($_POST['endDate'])) : '';

    $query = "SELECT la.application_id, la.user_id, CONCAT(u.first_name, ' ', u.last_name) as user_name, la.final_loan_amount, la.term_length, la.repayment_frequency, 
                     la.purpose, la.project_type, lt.type_name, la.loan_status, la.status as application_status
              FROM loan_applications la 
              JOIN loan_types lt ON la.loan_type_id = lt.loan_type_id 
              JOIN users1 u ON la.user_id = u.id
              WHERE 1=1";
    $params = [];
    $types = [];
    if (!empty($loanType)) {
        $query .= " AND lt.type_name = ?";
        $params[] = $loanType;
        $filters['Loan Type'] = $loanType;
        $types[] = 's';
    }
    if (!empty($loanStatus)) {
        $query .= " AND la.loan_status = ?";
        $params[] = ucfirst(strtolower($loanStatus));
        $filters['Loan Status'] = $loanStatus;
        $types[] = 's';
    }
    if (!empty($repaymentFrequency)) {
        $query .= " AND la.repayment_frequency = ?";
        $params[] = $repaymentFrequency;
        $filters['Repayment Frequency'] = $repaymentFrequency;
        $types[] = 's';
    }
    if (!empty($purpose)) {
        $query .= " AND la.purpose = ?";
        $params[] = $purpose;
        $filters['Purpose'] = $purpose;
        $types[] = 's';
    }
    if (!empty($projectType)) {
        $query .= " AND la.project_type = ?";
        $params[] = $projectType;
        $filters['Project Type'] = $projectType;
        $types[] = 's';
    }
    if (!empty($startDate)) {
        $query .= " AND la.created_at >= ?";
        $params[] = $startDate . ' 00:00:00';
        $filters['Start Date'] = $startDate;
        $types[] = 's';
    }
    if (!empty($endDate)) {
        $query .= " AND la.created_at <= ?";
        $params[] = $endDate . ' 23:59:59';
        $filters['End Date'] = $endDate;
        $types[] = 's';
    }

    if (empty($params)) {
        $result = $conn->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $reportData[] = $row;
            }
            $result->free();
        } else {
            $_SESSION['error'] = 'Error generating report: ' . $conn->error;
            error_log('Report generation failed: ' . $conn->error, 3, 'errors.log');
        }
    } else {
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param(implode('', $types), ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $reportData[] = $row;
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = 'Error generating report: ' . $conn->error;
            error_log('Report generation failed: ' . $conn->error, 3, 'errors.log');
        }
    }

    // Calculate report summary
    if (!empty($reportData)) {
        $reportSummary['total_applications'] = count($reportData);
        $reportSummary['total_amount'] = array_sum(array_column($reportData, 'final_loan_amount'));
        $reportSummary['average_term'] = round(array_sum(array_column($reportData, 'term_length')) / count($reportData), 1);
    }

    // Handle PDF export for Custom Reports
    if (isset($_POST['exportPDF'])) {
        require('tcpdf/tcpdf.php');
        $pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false); // Landscape orientation
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('CYCLOAN System');
        $pdf->SetTitle('Custom Loan Report');
        $pdf->SetSubject('Loan Application Report');
        $pdf->SetKeywords('CYCLOAN, Loan, Report');

        // Set margins
        $pdf->SetMargins(15, 20, 15);
        $pdf->SetHeaderMargin(10);
        $pdf->SetFooterMargin(10);
        $pdf->SetAutoPageBreak(TRUE, 15);

        // Add page
        $pdf->AddPage();

        // Title with green background
        $pdf->SetFillColor(46, 125, 50); // Green
        $pdf->SetTextColor(255, 255, 255); // White text
        $pdf->SetFont('helvetica', 'B', 20);
        $pdf->Cell(0, 15, 'CYCLOAN - Custom Loan Report', 0, 1, 'C', 1);
        $pdf->Ln(3);

        // Reset text color
        $pdf->SetTextColor(0, 0, 0);

        // Generation info box
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 8, 'Generated on: ' . date('F d, Y H:i:s'), 0, 1, 'R', 1);
        $pdf->Ln(5);

        // Filters section with styled box
        if (!empty($filters)) {
            $pdf->SetFillColor(225, 245, 254); // Light blue
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 8, 'FILTERS APPLIED', 0, 1, 'L', 1);
            $pdf->SetFont('helvetica', '', 10);
            foreach ($filters as $key => $value) {
                $pdf->Cell(60, 6, $key . ':', 0, 0, 'L');
                $pdf->SetFont('helvetica', 'B', 10);
                $pdf->Cell(0, 6, $value, 0, 1, 'L');
                $pdf->SetFont('helvetica', '', 10);
            }
            $pdf->Ln(3);
        }

        // Summary section with green background
        $pdf->SetFillColor(209, 250, 229); // Light green
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'SUMMARY', 0, 1, 'L', 1);

        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetFillColor(255, 255, 255);

        // Summary cards
        $pdf->Cell(90, 10, 'Total Applications:', 1, 0, 'L', 1);
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(90, 10, $reportSummary['total_applications'], 1, 1, 'L', 1);

        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(90, 10, 'Total Loan Amount:', 1, 0, 'L', 1);
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(90, 10, '₱' . number_format($reportSummary['total_amount'], 2), 1, 1, 'L', 1);

        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(90, 10, 'Average Term Length:', 1, 0, 'L', 1);
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(90, 10, $reportSummary['average_term'] . ' months', 1, 1, 'L', 1);
        $pdf->Ln(5);

        // Table header with green background
        $pdf->SetFillColor(46, 125, 50);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 9);

        $pdf->Cell(15, 10, 'ID', 1, 0, 'C', 1);
        $pdf->Cell(40, 10, 'User Name', 1, 0, 'C', 1);
        $pdf->Cell(30, 10, 'Loan Type', 1, 0, 'C', 1);
        $pdf->Cell(30, 10, 'Amount', 1, 0, 'C', 1);
        $pdf->Cell(18, 10, 'Term', 1, 0, 'C', 1);
        $pdf->Cell(30, 10, 'Frequency', 1, 0, 'C', 1);
        $pdf->Cell(35, 10, 'Purpose', 1, 0, 'C', 1);
        $pdf->Cell(35, 10, 'Project Type', 1, 0, 'C', 1);
        $pdf->Cell(27, 10, 'Status', 1, 1, 'C', 1);

        // Table data with alternating row colors
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 8);
        $fill = false;

        foreach ($reportData as $row) {
            $pdf->SetFillColor($fill ? 245 : 255, $fill ? 245 : 255, $fill ? 245 : 255);

            $pdf->Cell(15, 8, $row['application_id'], 1, 0, 'C', 1);
            $pdf->Cell(40, 8, substr($row['user_name'], 0, 25), 1, 0, 'L', 1);
            $pdf->Cell(30, 8, $row['type_name'], 1, 0, 'C', 1);
            $pdf->Cell(30, 8, '₱' . number_format($row['final_loan_amount'], 2), 1, 0, 'R', 1);
            $pdf->Cell(18, 8, $row['term_length'], 1, 0, 'C', 1);
            $pdf->Cell(30, 8, $row['repayment_frequency'], 1, 0, 'C', 1);
            $pdf->Cell(35, 8, substr($row['purpose'], 0, 20), 1, 0, 'L', 1);
            $pdf->Cell(35, 8, substr($row['project_type'], 0, 20), 1, 0, 'L', 1);
            $pdf->Cell(27, 8, $row['application_status'], 1, 1, 'C', 1);

            $fill = !$fill;
        }

        $pdf->Output('custom_loan_report_' . date('YmdHis') . '.pdf', 'D');
        exit;
    }

    // Handle CSV export for Custom Reports
    if (isset($_POST['exportCSV'])) {
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=CYCLOAN_Custom_Loan_Report_' . date('YmdHis') . '.csv');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Create file pointer connected to output stream
        $output = fopen('php://output', 'w');

        // Add BOM to fix UTF-8 in Excel
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Add styled report header
        fputcsv($output, array('╔═══════════════════════════════════════════════════════════════╗'));
        fputcsv($output, array('║          CYCLOAN - CUSTOM LOAN REPORT                        ║'));
        fputcsv($output, array('╚═══════════════════════════════════════════════════════════════╝'));
        fputcsv($output, array(''));
        fputcsv($output, array('Generated on:', date('F d, Y H:i:s')));
        fputcsv($output, array('Report Type:', 'Custom Loan Applications'));
        fputcsv($output, array(''));
        fputcsv($output, array('═══════════════════════════════════════════════════════════════'));

        // Add filters if any
        if (!empty($filters)) {
            fputcsv($output, array(''));
            fputcsv($output, array('FILTERS APPLIED'));
            fputcsv($output, array('───────────────────────────────────────────────────────────────'));
            foreach ($filters as $key => $value) {
                fputcsv($output, array('  ' . $key . ':', $value));
            }
            fputcsv($output, array(''));
        }

        // Add summary with box formatting
        fputcsv($output, array(''));
        fputcsv($output, array('SUMMARY STATISTICS'));
        fputcsv($output, array('───────────────────────────────────────────────────────────────'));
        fputcsv($output, array('Metric', 'Value'));
        fputcsv($output, array('Total Applications', $reportSummary['total_applications']));
        fputcsv($output, array('Total Loan Amount', '₱' . number_format($reportSummary['total_amount'], 2)));
        fputcsv($output, array('Average Term Length', $reportSummary['average_term'] . ' months'));
        fputcsv($output, array(''));
        fputcsv($output, array('═══════════════════════════════════════════════════════════════'));

        // Add data table header
        fputcsv($output, array(''));
        fputcsv($output, array('LOAN APPLICATION DETAILS'));
        fputcsv($output, array('───────────────────────────────────────────────────────────────'));
        fputcsv($output, array(
            'ID',
            'User Name',
            'Loan Type',
            'Amount (₱)',
            'Term (Months)',
            'Repayment Frequency',
            'Purpose',
            'Project Type',
            'Application Status'
        ));

        // Add data rows
        foreach ($reportData as $row) {
            fputcsv($output, array(
                $row['application_id'],
                $row['user_name'],
                $row['type_name'],
                number_format($row['final_loan_amount'], 2),
                $row['term_length'],
                $row['repayment_frequency'],
                $row['purpose'],
                $row['project_type'],
                $row['application_status']
            ));
        }

        // Add footer
        fputcsv($output, array(''));
        fputcsv($output, array('───────────────────────────────────────────────────────────────'));
        fputcsv($output, array('Total Records:', count($reportData)));
        fputcsv($output, array(''));
        fputcsv($output, array('End of Report - Generated by CYCLOAN System'));

        fclose($output);
        exit;
    }

    // Handle PDF export for Due Accounts
    if (isset($_POST['exportDueAccountsPDF'])) {
        require('tcpdf/tcpdf.php');
        $pdf = new TCPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('CYCLOAN System');
        $pdf->SetTitle('Due Accounts Report');
        $pdf->SetSubject('Payment Due Report');

        // Set margins
        $pdf->SetMargins(15, 20, 15);
        $pdf->SetHeaderMargin(10);
        $pdf->SetFooterMargin(10);
        $pdf->SetAutoPageBreak(TRUE, 15);

        $pdf->AddPage();

        // Title with red background (urgent)
        $pdf->SetFillColor(220, 53, 69); // Red for due accounts
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->Cell(0, 15, 'CYCLOAN - Due Accounts Report', 0, 1, 'C', 1);
        $pdf->Ln(3);

        // Reset text color
        $pdf->SetTextColor(0, 0, 0);

        // Generation info
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 8, 'Generated on: ' . date('F d, Y H:i:s'), 0, 1, 'R', 1);
        $pdf->Ln(5);

        // Filters section
        if (!empty($dueFilters)) {
            $pdf->SetFillColor(255, 243, 224); // Light orange
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 8, 'FILTERS APPLIED', 0, 1, 'L', 1);
            $pdf->SetFont('helvetica', '', 10);
            foreach ($dueFilters as $key => $value) {
                $pdf->Cell(60, 6, $key . ':', 0, 0, 'L');
                $pdf->SetFont('helvetica', 'B', 10);
                $pdf->Cell(0, 6, $value, 0, 1, 'L');
                $pdf->SetFont('helvetica', '', 10);
            }
            $pdf->Ln(3);
        }

        // Calculate total due amount
        $totalDue = 0;
        foreach ($dueAccountsData as $due) {
            $totalDue += $due['due_amount'];
        }

        // Summary box
        $pdf->SetFillColor(254, 226, 226); // Light red
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'SUMMARY', 0, 1, 'L', 1);

        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Cell(95, 10, 'Total Due Accounts:', 1, 0, 'L', 1);
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(95, 10, count($dueAccountsData), 1, 1, 'L', 1);

        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(95, 10, 'Total Amount Due:', 1, 0, 'L', 1);
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(95, 10, '₱' . number_format($totalDue, 2), 1, 1, 'L', 1);
        $pdf->Ln(5);

        // Table header with red background
        $pdf->SetFillColor(220, 53, 69);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 10);

        $pdf->Cell(45, 10, 'Due Date', 1, 0, 'C', 1);
        $pdf->Cell(60, 10, 'Account Holder', 1, 0, 'C', 1);
        $pdf->Cell(40, 10, 'Due Amount', 1, 0, 'C', 1);
        $pdf->Cell(35, 10, 'Loan Type', 1, 1, 'C', 1);

        // Table data with alternating colors
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 9);
        $fill = false;

        foreach ($dueAccountsData as $due) {
            $pdf->SetFillColor($fill ? 245 : 255, $fill ? 245 : 255, $fill ? 245 : 255);

            $pdf->Cell(45, 8, date('M d, Y', strtotime($due['due_date'])), 1, 0, 'C', 1);
            $pdf->Cell(60, 8, substr($due['account_holder'], 0, 30), 1, 0, 'L', 1);
            $pdf->Cell(40, 8, '₱' . number_format($due['due_amount'], 2), 1, 0, 'R', 1);
            $pdf->Cell(35, 8, $due['type_name'], 1, 1, 'C', 1);

            $fill = !$fill;
        }

        $pdf->Output('CYCLOAN_Due_Accounts_' . date('YmdHis') . '.pdf', 'D');
        exit;
    }

    // Handle CSV export for Due Accounts
    if (isset($_POST['exportDueAccountsCSV'])) {
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=CYCLOAN_Due_Accounts_' . date('YmdHis') . '.csv');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Create file pointer connected to output stream
        $output = fopen('php://output', 'w');

        // Add BOM to fix UTF-8 in Excel
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Report header with box border
        fputcsv($output, array('╔═══════════════════════════════════════════════════════════════════╗'));
        fputcsv($output, array('║              CYCLOAN - DUE ACCOUNTS REPORT                       ║'));
        fputcsv($output, array('╚═══════════════════════════════════════════════════════════════════╝'));
        fputcsv($output, array(''));
        fputcsv($output, array('Generated on: ' . date('F d, Y H:i:s')));
        fputcsv($output, array(''));

        // Filters section
        if (!empty($dueFilters)) {
            fputcsv($output, array('FILTERS APPLIED'));
            fputcsv($output, array('───────────────────────────────────────────────────────────────────'));
            foreach ($dueFilters as $key => $value) {
                fputcsv($output, array($key . ':', $value));
            }
            fputcsv($output, array(''));
        }

        // Calculate summary statistics
        $totalDue = 0;
        foreach ($dueAccountsData as $due) {
            $totalDue += $due['due_amount'];
        }

        // Summary section
        fputcsv($output, array('SUMMARY STATISTICS'));
        fputcsv($output, array('───────────────────────────────────────────────────────────────────'));
        fputcsv($output, array('Metric', 'Value'));
        fputcsv($output, array('═══════════════════════════════════', '═══════════════════════════'));
        fputcsv($output, array('Total Due Accounts', count($dueAccountsData)));
        fputcsv($output, array('Total Amount Due', '₱' . number_format($totalDue, 2)));
        fputcsv($output, array(''));

        // Data section header
        fputcsv($output, array('DUE ACCOUNTS DETAILS'));
        fputcsv($output, array('───────────────────────────────────────────────────────────────────'));

        // Column headers
        fputcsv($output, array(
            'Due Date',
            'Account Holder Name',
            'Amount Due (₱)',
            'Loan Type'
        ));
        fputcsv($output, array('═══════════════', '═══════════════════════════════', '═══════════════', '═══════════════'));

        // Data rows
        foreach ($dueAccountsData as $due) {
            fputcsv($output, array(
                date('M d, Y', strtotime($due['due_date'])),
                $due['account_holder'],
                '₱' . number_format($due['due_amount'], 2),
                $due['type_name']
            ));
        }

        // Footer
        fputcsv($output, array(''));
        fputcsv($output, array('───────────────────────────────────────────────────────────────────'));
        fputcsv($output, array('Total Records: ' . count($dueAccountsData)));
        fputcsv($output, array('End of Report - Generated by CYCLOAN System'));

        fclose($output);
        exit;
    }
}

// ========== MONTHLY COLLECTION REPORT GENERATION ==========
if (isset($_POST['generate_monthly_collection'])) {
    $report_month = $_POST['report_month'] ?? date('Y-m');
    list($year, $month) = explode('-', $report_month);

    // Query to get all payments for the selected month
    $query = "SELECT 
                ps.paid_at as payment_date,
                ps.payment_id as receipt_no,
                CONCAT(u.first_name, ' ', u.last_name) as borrower_name,
                ps.principal_paid,
                ps.interest_paid,
                ps.amount_paid as total_amount,
                l.status as loan_status,
                la.status as application_status,
                '' as check_bank,
                '' as check_no,
                '' as check_date,
                '' as check_amount
              FROM payment_schedules ps
              JOIN loans l ON ps.loan_id = l.loan_id
              JOIN loan_applications la ON l.application_id = la.application_id
              JOIN users1 u ON la.user_id = u.id
              WHERE ps.status = 'Paid'
              AND YEAR(ps.paid_at) = ?
              AND MONTH(ps.paid_at) = ?
              ORDER BY ps.paid_at ASC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $year, $month);
    $stmt->execute();
    $result = $stmt->get_result();
    $payments = $result->fetch_all(MYSQLI_ASSOC);

    // Calculate summary data
    $total_principal = 0;
    $total_interest = 0;
    $total_collection = 0;
    $num_receipts = count($payments);
    $num_checks = 0;
    $total_check_amount = 0;
    $active_account_payments = 0;
    $delinquent_account_payments = 0;
    $closed_account_payments = 0;

    foreach ($payments as $payment) {
        $total_principal += $payment['principal_paid'];
        $total_interest += $payment['interest_paid'];
        $total_collection += $payment['total_amount'];

        if ($payment['loan_status'] == 'active') {
            $active_account_payments++;
        } elseif ($payment['loan_status'] == 'closed') {
            $closed_account_payments++;
        }
    }

    // Generate PDF Report
    require('tcpdf/tcpdf.php');
    $pdf = new TCPDF('L', PDF_UNIT, 'LEGAL', true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('CYCLOAN System');
    $pdf->SetTitle('Report of Collection - ' . date('F Y', strtotime($report_month . '-01')));

    $pdf->SetMargins(10, 15, 10);
    $pdf->SetAutoPageBreak(TRUE, 15);
    $pdf->AddPage();

    // Header
    $pdf->SetFont('helvetica', 'B', 18);
    $pdf->Cell(0, 10, 'REPORT OF COLLECTION', 0, 1, 'C');
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Period: CY ' . $year . ' : ' . strtoupper(date('F', strtotime($report_month . '-01'))), 0, 1, 'C');
    $pdf->Ln(5);

    // Table Header
    $pdf->SetFillColor(46, 125, 50);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 8);

    $pdf->Cell(22, 8, 'DATE REMIT', 1, 0, 'C', 1);
    $pdf->Cell(20, 8, 'OR No.', 1, 0, 'C', 1);
    $pdf->Cell(50, 8, 'PAYOR / BORROWER', 1, 0, 'C', 1);
    $pdf->Cell(25, 8, 'Principal', 1, 0, 'C', 1);
    $pdf->Cell(25, 8, 'Interest', 1, 0, 'C', 1);
    $pdf->Cell(28, 8, 'TOTAL', 1, 0, 'C', 1);
    $pdf->Cell(35, 8, 'CK Bank', 1, 0, 'C', 1);
    $pdf->Cell(25, 8, 'CK No.', 1, 0, 'C', 1);
    $pdf->Cell(22, 8, 'CK Date', 1, 0, 'C', 1);
    $pdf->Cell(25, 8, 'CK Amt', 1, 1, 'C', 1);

    // Table Data
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 7);
    $fill = false;

    foreach ($payments as $payment) {
        $pdf->SetFillColor($fill ? 245 : 255, $fill ? 245 : 255, $fill ? 245 : 255);

        $pdf->Cell(22, 6, date('d-M', strtotime($payment['payment_date'])), 1, 0, 'C', $fill);
        $pdf->Cell(20, 6, 'OR ' . $payment['receipt_no'], 1, 0, 'C', $fill);
        $pdf->Cell(50, 6, substr($payment['borrower_name'], 0, 30), 1, 0, 'L', $fill);
        $pdf->Cell(25, 6, number_format($payment['principal_paid'], 2), 1, 0, 'R', $fill);
        $pdf->Cell(25, 6, number_format($payment['interest_paid'], 2), 1, 0, 'R', $fill);
        $pdf->Cell(28, 6, number_format($payment['total_amount'], 2), 1, 0, 'R', $fill);
        $pdf->Cell(35, 6, $payment['check_bank'], 1, 0, 'C', $fill);
        $pdf->Cell(25, 6, $payment['check_no'], 1, 0, 'C', $fill);
        $pdf->Cell(22, 6, $payment['check_date'], 1, 0, 'C', $fill);
        $pdf->Cell(25, 6, $payment['check_amount'], 1, 1, 'R', $fill);

        $fill = !$fill;
    }

    // Summary Section
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, 'SUMMARY', 0, 1, 'L');

    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetFont('helvetica', 'B', 9);

    $pdf->Cell(100, 7, 'Total Principal Collection:', 1, 0, 'L', 1);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(60, 7, '₱ ' . number_format($total_principal, 2), 1, 1, 'R', 1);

    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(100, 7, 'Total Interest Collection:', 1, 0, 'L', 1);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(60, 7, '₱ ' . number_format($total_interest, 2), 1, 1, 'R', 1);

    $pdf->SetFillColor(46, 125, 50);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(100, 8, 'TOTAL COLLECTION:', 1, 0, 'L', 1);
    $pdf->Cell(60, 8, '₱ ' . number_format($total_collection, 2), 1, 1, 'R', 1);

    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', 'B', 9);

    $pdf->Cell(100, 7, '# of Receipts (individual):', 1, 0, 'L', 1);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(60, 7, $num_receipts, 1, 1, 'R', 1);

    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(100, 7, '# of Checks:', 1, 0, 'L', 1);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(60, 7, $num_checks, 1, 1, 'R', 1);

    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(100, 7, 'Total Amount of Checks:', 1, 0, 'L', 1);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(60, 7, '₱ ' . number_format($total_check_amount, 2), 1, 1, 'R', 1);

    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(100, 7, 'Number of Payments from Active Accounts:', 1, 0, 'L', 1);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(60, 7, $active_account_payments, 1, 1, 'R', 1);

    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(100, 7, 'Number of Payments from Delinquent Accounts:', 1, 0, 'L', 1);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(60, 7, $delinquent_account_payments, 1, 1, 'R', 1);

    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(100, 7, 'Number of Closed Accounts:', 1, 0, 'L', 1);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(60, 7, $closed_account_payments, 1, 1, 'R', 1);

    // Certification
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'I', 9);
    $pdf->Cell(0, 6, 'Certified Correct by: ______________________________', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(0, 5, 'Name & Position', 0, 1, 'L');

    // Output PDF
    $filename = 'Collection_Report_' . date('F_Y', strtotime($report_month . '-01')) . '.pdf';
    $pdf->Output($filename, 'D');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports Records - CYCLOAN</title>
    <link rel="stylesheet" href="CSS/admin_dashboard.css">
    <link rel="stylesheet" href="CSS/admin_profile.css">
    <link rel="stylesheet" href="CSS/nav_active.css">
    <link rel="stylesheet" href="CSS/report.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script src="JAVASCRIPT/Real-Time.js"></script>
    <script>
        // Tab functionality - defined early to prevent "openTab is not defined" errors
        function openTab(evt, tabName) {
            // Hide all tab contents
            var tabContents = document.getElementsByClassName("tab-content");
            for (var i = 0; i < tabContents.length; i++) {
                tabContents[i].classList.remove("active");
            }

            // Remove active class from all tab buttons
            var tabBtns = document.getElementsByClassName("tab-btn");
            for (var i = 0; i < tabBtns.length; i++) {
                tabBtns[i].classList.remove("active");
            }

            // Show the selected tab and mark button as active
            var targetTab = document.getElementById(tabName);
            if (targetTab) {
                targetTab.classList.add("active");
            } else {
                console.error('Tab not found:', tabName);
                return;
            }

            if (evt && evt.currentTarget) {
                evt.currentTarget.classList.add("active");
            }

            // Update tab counter with animation
            var activeIndex = Array.from(tabBtns).findIndex(btn => btn.classList.contains('active')) + 1;
            var totalTabs = tabBtns.length;
            var counterElement = document.getElementById('tab-counter');
            if (counterElement) {
                counterElement.style.animation = 'none';
                setTimeout(() => {
                    counterElement.innerHTML = `Tab <strong>${activeIndex}</strong> of <strong>${totalTabs}</strong>`;
                    counterElement.style.animation = 'counterPulse 0.5s ease-out';
                }, 10);
            }

            // Scroll tab into view smoothly
            if (evt && evt.currentTarget) {
                evt.currentTarget.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
            }
        }
    </script>
    <style>
        /* ========== PREMIUM TAB SYSTEM ========== */
        .tabs-container {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafb 100%);
            border-radius: 14px;
            padding: 12px;
            margin-bottom: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08), inset 0 1px 0 rgba(255, 255, 255, 0.6);
            border: 1px solid #e2e8f0;
            overflow-x: auto;
            overflow-y: hidden;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .tabs {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            width: 100%;
        }

        .tab-btn {
            padding: 11px 18px;
            background: linear-gradient(135deg, #ffffff 0%, #f9fafb 100%);
            border: 2px solid #e2e8f0;
            border-radius: 9px;
            cursor: pointer;
            font-size: 13.5px;
            font-weight: 600;
            color: #64748b;
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(10px);
        }

        .tab-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.6s ease;
        }

        .tab-btn::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, transparent, #2d7d32, transparent);
            transform: scaleX(0);
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 2px;
        }

        .tab-btn:hover::before {
            left: 100%;
        }

        .tab-btn:hover {
            border-color: #cbd5e1;
            color: #334155;
            background: linear-gradient(135deg, #f1f5fe 0%, #f0fdf4 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(45, 125, 50, 0.12);
        }

        .tab-btn:active {
            transform: translateY(0);
        }

        .tab-btn i {
            font-size: 16px;
            transition: transform 0.3s ease;
        }

        .tab-btn:hover i {
            transform: scale(1.1);
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #2d7d32 0%, #1b5e20 100%);
            color: #ffffff;
            border-color: #1b5e20;
            box-shadow: 0 6px 24px rgba(45, 125, 50, 0.35), inset 0 1px 0 rgba(255, 255, 255, 0.15);
            transform: translateY(-3px);
            animation: tabActivePulse 0.6s ease-out;
        }

        .tab-btn.active::after {
            transform: scaleX(1);
        }

        .tab-btn.active i {
            animation: tabIconBounce 0.5s ease-out;
        }

        @keyframes tabActivePulse {
            0% {
                transform: translateY(-3px) scale(0.98);
                opacity: 0.8;
            }

            50% {
                transform: translateY(-4px) scale(1.02);
            }

            100% {
                transform: translateY(-3px) scale(1);
                opacity: 1;
            }
        }

        @keyframes tabIconBounce {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-2px);
            }
        }

        /* Tab badge styling - enhanced */
        .tab-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.4) 0%, rgba(255, 255, 255, 0.2) 100%);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            font-size: 10px;
            font-weight: 800;
            margin-left: 6px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
        }

        .tab-btn.active .tab-badge {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.6) 0%, rgba(255, 255, 255, 0.4) 100%);
            border-color: rgba(255, 255, 255, 0.6);
            transform: scale(1.15);
            box-shadow: 0 3px 10px rgba(255, 255, 255, 0.3);
            animation: badgePulse 0.6s ease-out;
        }

        .tab-btn:hover .tab-badge {
            background: rgba(45, 125, 50, 0.15);
            border-color: rgba(45, 125, 50, 0.3);
            transform: scale(1.1);
        }

        @keyframes badgePulse {
            0% {
                transform: scale(0.9);
                opacity: 0.7;
            }

            50% {
                transform: scale(1.25);
            }

            100% {
                transform: scale(1.15);
                opacity: 1;
            }
        }

        .tab-info {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            color: #64748b;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
            white-space: nowrap;
        }

        #tab-counter {
            background: linear-gradient(135deg, rgba(45, 125, 50, 0.12) 0%, rgba(45, 125, 50, 0.06) 100%);
            padding: 4px 10px;
            border-radius: 6px;
            color: #2d7d32;
            font-weight: 800;
            letter-spacing: 0.3px;
            box-shadow: inset 0 1px 3px rgba(45, 125, 50, 0.1);
        }

        #tab-counter strong {
            font-size: 13px;
        }

        @media (max-width: 768px) {
            .tabs-container {
                margin-bottom: 16px;
                padding: 8px;
                gap: 8px;
            }

            .tab-btn {
                padding: 9px 13px;
                font-size: 11px;
                gap: 6px;
            }

            .tab-btn i {
                font-size: 14px;
            }

            .tab-badge {
                display: none;
            }

            .tab-info {
                display: none;
            }

            .due-accounts-form .form-group,
            .custom-report-form .form-group {
                flex: 1 1 100%;
            }

            .report-table th,
            .report-table td,
            .due-accounts-table th,
            .due-accounts-table td {
                font-size: 0.8em;
                padding: 8px;
            }
        }

        /* Tab description with premium styling */
        .tab-descriptions {
            animation: slideInDown 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            background: linear-gradient(135deg, rgba(240, 253, 244, 0.95) 0%, rgba(241, 245, 254, 0.95) 100%);
            backdrop-filter: blur(15px);
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(45, 125, 50, 0.1), inset 0 1px 0 rgba(255, 255, 255, 0.8);
        }

        .tab-descriptions p {
            animation: fadeInText 0.6s ease-out 0.1s both;
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-15px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInText {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        /* Premium tab content animations */
        .tab-content {
            display: none;
            animation: fadeInScaleEnhanced 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeInScaleEnhanced {
            from {
                opacity: 0;
                transform: scale(0.95) translateY(10px);
            }

            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .summary-stats {
            display: flex;
            justify-content: space-around;
            margin-bottom: 30px;
            flex-wrap: wrap;
            animation: staggerFadeIn 0.8s ease-out;
        }

        .graph-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .report-table tr:nth-child(even),
        .due-accounts-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .report-table tr:hover,
        .due-accounts-table tr:hover {
            background-color: #f1f1f1;
        }


        .error {
            color: #dc3545;
            margin: 10px 0;
            font-size: 0.9em;
        }

        .report-summary {
            margin-top: 20px;
            padding: 15px;
            background-color: #e9f7ef;
            border-radius: 8px;
            border: 1px solid #28a745;
        }

        .report-summary h4 {
            margin-bottom: 10px;
            color: #28a745;
        }

        .report-summary p {
            margin: 5px 0;
            font-size: 0.9em;
        }

        @media (max-width: 768px) {
            .summary-stats {
                flex-direction: column;
            }

            .stat-box {
                margin: 10px 0;
            }

            .due-accounts-form .form-group,
            .custom-report-form .form-group {
                flex: 1 1 100%;
            }

            .report-table th,
            .report-table td,
            .due-accounts-table th,
            .due-accounts-table td {
                font-size: 0.8em;
                padding: 8px;
            }
        }

        /* Professional print styles */
        @media print {
            @page {
                size: A4;
                margin: 0.75in;
            }

            /* Hide app UI elements */
            .nav-container,
            .burger,
            nav,
            .header,
            .profile-container,
            .header-actions,
            .btn-action,
            .btn-export,
            .btn-print,
            .dropdown-menu,
            .chart-actions {
                display: none !important;
            }

            /* Print header/info inserted by JS */
            .print-header-info {
                display: block !important;
                margin-bottom: 18px;
            }

            .print-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                border-bottom: 3px solid #2e7d32;
                padding-bottom: 12px;
                margin-bottom: 12px;
            }

            .print-title h1 {
                color: #1b5e20;
                font-size: 20px;
                margin: 0 0 6px 0;
            }

            .print-title p {
                margin: 0;
                color: #444;
                font-size: 12px;
            }

            /* Summary and layout */
            .print-summary {
                background: #f8f9fa;
                padding: 12px;
                border-left: 4px solid #2e7d32;
                margin-bottom: 14px;
            }

            .print-summary h2 {
                font-size: 14px;
                margin: 0 0 8px 0;
                color: #1b5e20;
            }

            /* Make charts and KPI friendly for print */
            .kpi-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 12px;
            }

            .chart-container {
                height: 260px !important;
            }

            /* Tables */
            .report-table,
            .due-accounts-table {
                font-size: 11px;
            }

            .report-table th,
            .report-table td,
            .due-accounts-table th,
            .due-accounts-table td {
                border: 1px solid #ddd;
                padding: 6px;
            }

            /* Footer inserted by JS */
            .print-footer {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                text-align: center;
                font-size: 11px;
                color: #666;
                border-top: 1px solid #ddd;
                padding: 8px;
                background: white;
            }

            /* Avoid breaking critical sections */
            .no-break {
                page-break-inside: avoid;
            }

            .page-break {
                page-break-before: always;
            }


        }

        /* Screen-only: hide print header/footer placeholders */
        @media screen {

            .print-header-info,
            .print-footer {
                display: none;
            }
        }

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
</head>

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
            <a href="#" onclick="openManageInterestRateModal()" class="manage-interest-rate-link">
                <i class="fa-solid fa-percent"></i> VIEW INTEREST RATES
            </a>
            <?php if ($adminRole === 'superadmin'): ?>
                <a href="#" onclick="openCalculatorModal()">
                    <i class="fa-solid fa-calculator"></i> LOAN CALCULATOR
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
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message error"><i
                    class="fas fa-exclamation-circle"></i><?php echo htmlspecialchars($_SESSION['error']); ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Tab Navigation -->
        <div class="tabs-container">
            <div class="tabs">
                <button class="tab-btn active" onclick="openTab(event, 'overview')" data-tab-name="overview"
                    title="View system overview and key statistics">
                    <i class="fa-solid fa-square-poll-vertical"></i> Overview
                    <span class="tab-badge">1</span>
                </button>
                <button class="tab-btn" onclick="openTab(event, 'analytics')" data-tab-name="analytics"
                    title="Explore advanced metrics and detailed analytics">
                    <i class="fa-solid fa-chart-pie"></i> Advanced Analytics
                    <span class="tab-badge">2</span>
                </button>
                <button class="tab-btn" onclick="openTab(event, 'due-accounts')" data-tab-name="due-accounts"
                    title="Monitor accounts with upcoming payment deadlines">
                    <i class="fas fa-calendar-alt"></i> Due Accounts
                    <span class="tab-badge">3</span>
                </button>
                <button class="tab-btn" onclick="openTab(event, 'custom-reports')" data-tab-name="custom-reports"
                    title="Generate customized reports with specific filters">
                    <i class="fas fa-file-alt"></i> Custom Reports
                    <span class="tab-badge">4</span>
                </button>
                <button class="tab-btn" onclick="openTab(event, 'operational-dashboard')"
                    data-tab-name="operational-dashboard" title="Real-time operational metrics and pipeline status">
                    <i class="fas fa-gauge"></i> Operational Dashboard
                    <span class="tab-badge">5</span>
                </button>
                <button class="tab-btn" onclick="openTab(event, 'predictive-risk')" data-tab-name="predictive-risk"
                    title="AI-powered risk prediction and portfolio forecasting">
                    <i class="fas fa-brain"></i> Predictive Risk Models
                    <span class="tab-badge">6</span>
                </button>
                <button class="tab-btn" onclick="openTab(event, 'portfolio-forecast')"
                    data-tab-name="portfolio-forecast"
                    title="Portfolio maturity, growth projections, and income forecasts">
                    <i class="fas fa-chart-line"></i> Portfolio Forecasting
                    <span class="tab-badge">7</span>
                </button>
                <button class="tab-btn" onclick="openTab(event, 'customer-analytics')"
                    data-tab-name="customer-analytics"
                    title="Customer segmentation, loyalty analysis, and geographic distribution">
                    <i class="fas fa-users"></i> Customer Analytics
                    <span class="tab-badge">8</span>
                </button>
                <button class="tab-btn" onclick="openTab(event, 'kpi-cards')" data-tab-name="kpi-cards"
                    title="Advanced KPI metrics and performance indicators">
                    <i class="fas fa-chart-bar"></i> Advanced KPIs
                    <span class="tab-badge">9</span>
                </button>
            </div>
        </div>
        <div class="tab-info">
            <span id="tab-counter">Tab <strong>1</strong> of <strong>9</strong></span>
        </div>

        <!-- Tab Description Labels -->
        <div class="tab-descriptions"
            style="padding: 16px 20px; background: linear-gradient(135deg, #f0fdf4 0%, #f1f5fe 100%); border-radius: 10px; margin-bottom: 20px;">
            <p id="tab-description" style="margin: 0; color: #1e293b; font-size: 0.95rem; line-height: 1.5;">
                <span style="font-weight: 600; color: #2d7d32;">Overview:</span> Welcome to the Reports Dashboard! Here
                you'll find a comprehensive overview of our lending activities, including key statistics on total
                applications, disbursed amounts, successful repayments, and overdue accounts. This section provides a
                quick snapshot of the system's performance.
            </p>
        </div>

        <!-- Overview Tab -->
        <div id="overview" class="tab-content active">
            <div class="summary-stats">
                <div class="stat-box">
                    <h3><i class="fas fa-file-alt"></i> Total Applications</h3>
                    <p><?php echo $summaryData['total_applications']; ?></p>
                    <span class="stat-subtext">All time applications</span>
                </div>
                <div class="stat-box">
                    <h3><i class="fas fa-money-bill-wave"></i> Total Disbursed</h3>
                    <p>₱<?php echo number_format($summaryData['total_disbursed'], 2); ?></p>
                    <span class="stat-subtext">Total funds released</span>
                </div>
                <div class="stat-box">
                    <h3><i class="fa-solid fa-money-check"></i> Total Repaid</h3>
                    <p>₱<?php echo number_format($summaryData['total_repaid'], 2); ?></p>
                    <span class="stat-subtext">Successful collections</span>
                </div>
                <div class="stat-box">
                    <h3><i class="fas fa-exclamation-triangle"></i> Total Overdue</h3>
                    <p>₱<?php echo number_format($summaryData['total_overdue'], 2); ?></p>
                    <span class="stat-subtext">Pending payments</span>
                </div>
            </div>
            <div class="report-container1">
                <h2 class="section-title">Analytics Overview</h2>
                <div class="graph-section">
                    <div class="graph-box">
                        <h3><i class="fa-solid fa-chart-column graph-icon"></i> Loan Applications by Type</h3>
                        <div class="chart-container">
                            <canvas id="loanChart"></canvas>
                        </div>
                    </div>
                    <div class="graph-box">
                        <h3><i class="fas fa-chart-pie"></i> Loan Status Distribution</h3>
                        <div class="chart-container">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                    <div class="graph-box">
                        <h3><i class="fas fa-chart-line"></i> Applications Over Time</h3>
                        <div class="chart-container">
                            <canvas id="timeChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- End Overview Tab -->

        <!-- Advanced Analytics Tab -->
        <div id="analytics" class="tab-content">
            <div class="analytics-dashboard">
                <div class="analytics-header-section">
                    <div class="header-actions">
                        <button onclick="printAdvancedReport()" class="btn-action btn-print">
                            <i class="fas fa-print"></i> Print Report
                        </button>
                        <button onclick="exportAnalyticsData()" class="btn-action btn-export">
                            <i class="fas fa-file-excel"></i> Export
                        </button>
                    </div>
                </div>

                <!-- Key Performance Indicators -->
                <div class="kpi-section">
                    <h3 class="section-subtitle">Key Performance Indicators</h3>
                    <div class="kpi-grid">
                        <div class="kpi-card">
                            <div class="kpi-icon kpi-applications">
                                <i class="fas fa-file-contract"></i>
                            </div>
                            <div class="kpi-content">
                                <h4>Total Applications</h4>
                                <p class="kpi-value"><?php echo number_format($kpiData['total_applications']); ?></p>
                                <span class="kpi-label">All time</span>
                            </div>
                        </div>

                        <div class="kpi-card">
                            <div class="kpi-icon kpi-approved">
                                <i class="fas fa-thumbs-up"></i>
                            </div>
                            <div class="kpi-content">
                                <h4>Approval Rate</h4>
                                <p class="kpi-value"><?php echo number_format($kpiData['approval_rate'], 1); ?>%</p>
                                <span class="kpi-label"><?php echo number_format($kpiData['approved_applications']); ?>
                                    approved</span>
                            </div>
                        </div>

                        <div class="kpi-card">
                            <div class="kpi-icon kpi-disbursed">
                                <i class="fas fa-coins"></i>
                            </div>
                            <div class="kpi-content">
                                <h4>Total Disbursed</h4>
                                <p class="kpi-value">₱<?php echo number_format($kpiData['total_disbursed'], 2); ?></p>
                                <span class="kpi-label">Avg:
                                    ₱<?php echo number_format($kpiData['average_loan_amount'], 2); ?></span>
                            </div>
                        </div>

                        <div class="kpi-card">
                            <div class="kpi-icon kpi-repayment">
                                <i class="fas fa-credit-card"></i>
                            </div>
                            <div class="kpi-content">
                                <h4>Repayment Rate</h4>
                                <p class="kpi-value"><?php echo number_format($kpiData['repayment_rate'], 1); ?>%</p>
                                <span class="kpi-label">₱<?php echo number_format($kpiData['total_repaid'], 2); ?>
                                    repaid</span>
                            </div>
                        </div>

                        <div class="kpi-card">
                            <div class="kpi-icon kpi-outstanding">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="kpi-content">
                                <h4>Outstanding</h4>
                                <p class="kpi-value">₱<?php echo number_format($kpiData['total_outstanding'], 2); ?></p>
                                <span class="kpi-label">Pending payments</span>
                            </div>
                        </div>

                        <div class="kpi-card">
                            <div class="kpi-icon kpi-risk">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <div class="kpi-content">
                                <h4>Default Rate</h4>
                                <p class="kpi-value"><?php echo number_format($kpiData['default_rate'], 1); ?>%</p>
                                <span class="kpi-label">Risk indicator</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Comparative Analysis -->
                <div class="comparative-section">
                    <h3 class="section-subtitle">Month-over-Month Comparison</h3>
                    <div class="comparison-grid">
                        <div class="comparison-card">
                            <div class="comparison-header">
                                <h4>Applications</h4>
                                <span
                                    class="comparison-trend <?php echo ($comparativeData['change']['applications'] ?? 0) >= 0 ? 'positive' : 'negative'; ?>">
                                    <i
                                        class="fas fa-arrow-<?php echo ($comparativeData['change']['applications'] ?? 0) >= 0 ? 'up' : 'down'; ?>"></i>
                                    <?php echo abs(number_format($comparativeData['change']['applications'] ?? 0, 1)); ?>%
                                </span>
                            </div>
                            <div class="comparison-values">
                                <div class="current-value">
                                    <span class="label">This Month</span>
                                    <span
                                        class="value"><?php echo number_format($comparativeData['current']['applications'] ?? 0); ?></span>
                                </div>
                                <div class="previous-value">
                                    <span class="label">Last Month</span>
                                    <span
                                        class="value"><?php echo number_format($comparativeData['previous']['applications'] ?? 0); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="comparison-card">
                            <div class="comparison-header">
                                <h4>Approvals</h4>
                                <span
                                    class="comparison-trend <?php echo ($comparativeData['change']['approved'] ?? 0) >= 0 ? 'positive' : 'negative'; ?>">
                                    <i
                                        class="fas fa-arrow-<?php echo ($comparativeData['change']['approved'] ?? 0) >= 0 ? 'up' : 'down'; ?>"></i>
                                    <?php echo abs(number_format($comparativeData['change']['approved'] ?? 0, 1)); ?>%
                                </span>
                            </div>
                            <div class="comparison-values">
                                <div class="current-value">
                                    <span class="label">This Month</span>
                                    <span
                                        class="value"><?php echo number_format($comparativeData['current']['approved'] ?? 0); ?></span>
                                </div>
                                <div class="previous-value">
                                    <span class="label">Last Month</span>
                                    <span
                                        class="value"><?php echo number_format($comparativeData['previous']['approved'] ?? 0); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="comparison-card">
                            <div class="comparison-header">
                                <h4>Amount Disbursed</h4>
                                <span
                                    class="comparison-trend <?php echo ($comparativeData['change']['disbursed'] ?? 0) >= 0 ? 'positive' : 'negative'; ?>">
                                    <i
                                        class="fas fa-arrow-<?php echo ($comparativeData['change']['disbursed'] ?? 0) >= 0 ? 'up' : 'down'; ?>"></i>
                                    <?php echo abs(number_format($comparativeData['change']['disbursed'] ?? 0, 1)); ?>%
                                </span>
                            </div>
                            <div class="comparison-values">
                                <div class="current-value">
                                    <span class="label">This Month</span>
                                    <span
                                        class="value">₱<?php echo number_format($comparativeData['current']['disbursed'] ?? 0, 2); ?></span>
                                </div>
                                <div class="previous-value">
                                    <span class="label">Last Month</span>
                                    <span
                                        class="value">₱<?php echo number_format($comparativeData['previous']['disbursed'] ?? 0, 2); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="analytics-charts-grid">
                    <!-- Monthly Trends Chart -->
                    <div class="chart-card">
                        <h3><i class="fas fa-chart-line"></i> Monthly Trends (Last 12 Months)</h3>
                        <div class="chart-container">
                            <canvas id="monthlyTrendsChart"></canvas>
                        </div>
                    </div>

                    <!-- Loan Type Performance Chart -->
                    <div class="chart-card">
                        <h3><i class="fas fa-chart-bar"></i> Loan Type Performance</h3>
                        <div class="chart-container">
                            <canvas id="loanTypeChart"></canvas>
                        </div>
                    </div>

                    <!-- Credit Risk Analysis Chart -->
                    <div class="chart-card">
                        <h3><i class="fas fa-shield-alt"></i> Credit Risk Analysis</h3>
                        <div class="chart-container">
                            <canvas id="creditRiskChart"></canvas>
                        </div>
                    </div>

                    <!-- Repayment Performance Chart -->
                    <div class="chart-card">
                        <h3><i class="fas fa-hand-holding-usd"></i> Repayment Performance</h3>
                        <div class="chart-container">
                            <canvas id="repaymentPerformanceChart"></canvas>
                        </div>
                    </div>

                    <!-- Loan Purpose Distribution Chart -->
                    <div class="chart-card">
                        <h3><i class="fas fa-tasks"></i> Loan Purpose Distribution</h3>
                        <div class="chart-container">
                            <canvas id="purposeChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Top Borrowers Table -->
                <div class="table-section">
                    <h3 class="section-subtitle">Top 10 Borrowers</h3>
                    <div class="table-wrapper">
                        <table class="analytics-table">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Borrower Name</th>
                                    <th>Loan Count</th>
                                    <th>Total Borrowed</th>
                                    <th>Average Loan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($topBorrowers)): ?>
                                    <?php $rank = 1;
                                    foreach ($topBorrowers as $borrower): ?>
                                        <tr>
                                            <td><?php echo $rank++; ?></td>
                                            <td><?php echo htmlspecialchars($borrower['borrower_name']); ?></td>
                                            <td><?php echo number_format($borrower['loan_count']); ?></td>
                                            <td>₱<?php echo number_format($borrower['total_borrowed'], 2); ?></td>
                                            <td>₱<?php echo number_format($borrower['avg_loan_amount'] ?? 0, 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align:center;">No data available</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <!-- End Advanced Analytics Tab -->

        <!-- Due Accounts Tab -->
        <div id="due-accounts" class="tab-content">
            <div class="report-container">
                <h2>Due Accounts</h2>
                <form method="POST" action="reports_record.php" class="due-accounts-form">
                    <div class="filter-group1">
                        <div class="form-group">
                            <label for="dueLoanType">Loan Type</label>
                            <select id="dueLoanType" name="dueLoanType">
                                <option value="">All</option>
                                <option value="Individual" <?php echo isset($_POST['dueLoanType']) && $_POST['dueLoanType'] === 'Individual' ? 'selected' : ''; ?>>Individual</option>
                                <option value="Cooperative" <?php echo isset($_POST['dueLoanType']) && $_POST['dueLoanType'] === 'Cooperative' ? 'selected' : ''; ?>>Cooperative</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="dueStartDate">Start Date</label>
                            <input type="date" id="dueStartDate" name="dueStartDate"
                                value="<?php echo isset($_POST['dueStartDate']) ? htmlspecialchars($_POST['dueStartDate']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="dueEndDate">End Date</label>
                            <input type="date" id="dueEndDate" name="dueEndDate"
                                value="<?php echo isset($_POST['dueEndDate']) ? htmlspecialchars($_POST['dueEndDate']) : ''; ?>">
                        </div>
                    </div>

                    <div class="filter-btn1">
                        <button type="submit" class="action-btn filter-btn">
                            <i class="fa-solid fa-filter"></i> Filter
                        </button>
                        <button type="submit" name="exportDueAccountsPDF" class="action-btn">
                            <i class="fa-solid fa-file-pdf"></i> Export PDF
                        </button>
                        <button type="submit" name="exportDueAccountsCSV" class="action-btn btn-csv">
                            <i class="fa-solid fa-file-excel"></i> Export CSV
                        </button>
                    </div>
                </form>
                <div class="scrollable-table">
                    <table class="due-accounts-table">
                        <thead>
                            <tr>
                                <th>Due Date</th>
                                <th>Account Holder</th>
                                <th>Due Amount</th>
                                <th>Loan Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($dueAccountsData)): ?>
                                <tr>
                                    <td colspan="4" class="no-records">
                                        <i class="fa-solid fa-circle-exclamation"></i> No due accounts found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($dueAccountsData as $due): ?>
                                    <tr>
                                        <td data-label="Due Date"><?php echo date('Y-m-d', strtotime($due['due_date'])); ?></td>
                                        <td data-label="Account Holder"><?php echo htmlspecialchars($due['account_holder']); ?>
                                        </td>
                                        <td data-label="Due Amount">₱<?php echo number_format($due['due_amount'], 2); ?></td>
                                        <td data-label="Loan Type"><?php echo htmlspecialchars($due['type_name']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!-- End Due Accounts Tab -->

        <!-- Custom Reports Tab -->
        <div id="custom-reports" class="tab-content">
            <div class="report-container">
                <h2 class="section-title"><i class="fas fa-file-chart-line"></i> Custom Loan Reports</h2>
                <p style="text-align: center; color: #64748b; margin-bottom: 32px; font-size: 0.95rem;">Generate
                    comprehensive loan reports with custom filters and date ranges</p>

                <form method="POST" action="reports_record.php" class="custom-report-form">
                    <div class="filter-group">
                        <h3
                            style="font-size: 1.125rem; color: var(--dark); font-weight: 600; margin: 0 0 16px 0; display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-filter" style="color: var(--primary);"></i> Filter Options
                        </h3>
                        <div class="filter-form-grp">
                            <div class="form-group">
                                <label for="loanType">Loan Type</label>
                                <select id="loanType" name="loanType">
                                    <option value="">All Types</option>
                                    <option value="Individual" <?php echo isset($_POST['loanType']) && $_POST['loanType'] === 'Individual' ? 'selected' : ''; ?>>Individual</option>
                                    <option value="Cooperative" <?php echo isset($_POST['loanType']) && $_POST['loanType'] === 'Cooperative' ? 'selected' : ''; ?>>Cooperative</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="loanStatus">Loan Status</label>
                                <select id="loanStatus" name="loanStatus">
                                    <option value="">All Status</option>
                                    <option value="New" <?php echo isset($_POST['loanStatus']) && $_POST['loanStatus'] === 'New' ? 'selected' : ''; ?>>New</option>
                                    <option value="Renewal" <?php echo isset($_POST['loanStatus']) && $_POST['loanStatus'] === 'Renewal' ? 'selected' : ''; ?>>Renewal</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="repaymentFrequency">Repayment
                                    Frequency</label>
                                <select id="repaymentFrequency" name="repaymentFrequency">
                                    <option value="">All Frequencies</option>
                                    <option value="Monthly" <?php echo isset($_POST['repaymentFrequency']) && $_POST['repaymentFrequency'] === 'Monthly' ? 'selected' : ''; ?>>Monthly</option>
                                    <option value="Quarterly" <?php echo isset($_POST['repaymentFrequency']) && $_POST['repaymentFrequency'] === 'Quarterly' ? 'selected' : ''; ?>>Quarterly
                                    </option>
                                    <option value="Annually" <?php echo isset($_POST['repaymentFrequency']) && $_POST['repaymentFrequency'] === 'Annually' ? 'selected' : ''; ?>>Annually
                                    </option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="purpose">Purpose</label>
                                <select id="purpose" name="purpose">
                                    <option value="">All Purposes</option>
                                    <option value="Start-up Capital" <?php echo isset($_POST['purpose']) && $_POST['purpose'] === 'Start-up Capital' ? 'selected' : ''; ?>>Start-up Capital
                                    </option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="projectType">Project Type</label>
                                <select id="projectType" name="projectType">
                                    <option value="">All Projects</option>
                                    <option value="Non-Agricultural" <?php echo isset($_POST['projectType']) && $_POST['projectType'] === 'Non-Agricultural' ? 'selected' : ''; ?>>
                                        Non-Agricultural</option>
                                    <option value="Agricultural-based" <?php echo isset($_POST['projectType']) && $_POST['projectType'] === 'Agricultural-based' ? 'selected' : ''; ?>>
                                        Agricultural-based</option>
                                </select>
                            </div>
                        </div>

                        <h3
                            style="font-size: 1.125rem; color: var(--dark); font-weight: 600; margin: 16px 0 16px 0; display: flex; align-items: center; gap: 10px;">
                            <i class="fa-solid fa-calendar-days" style="color: var(--primary);"></i> Date Range
                        </h3>
                        <div class="calendar-form-grp">
                            <div class="form-group1">
                                <label for="startDate">Start Date</label>
                                <input type="date" id="startDate" name="startDate"
                                    value="<?php echo isset($_POST['startDate']) ? htmlspecialchars($_POST['startDate']) : ''; ?>">
                            </div>
                            <div class="form-group1">
                                <label for="endDate">End Date</label>
                                <input type="date" id="endDate" name="endDate"
                                    value="<?php echo isset($_POST['endDate']) ? htmlspecialchars($_POST['endDate']) : ''; ?>">
                            </div>
                        </div>

                        <div class="filter-group-btn">
                            <button type="submit" class="action-btn">
                                <i class="fa-solid fa-chart-line"></i> Generate Report
                            </button>
                            <button type="submit" name="exportPDF" class="action-btn">
                                <i class="fa-solid fa-file-pdf"></i> Export PDF
                            </button>
                            <button type="submit" name="exportCSV" class="action-btn btn-csv">
                                <i class="fa-solid fa-file-excel"></i> Export CSV
                            </button>
                        </div>
                    </div>
                </form>

                <?php if (!empty($reportData)): ?>
                    <div class="report-summary">
                        <h4>Summary</h4>
                        <div class="report-summary-grid">
                            <p>
                                <span
                                    style="font-size: 0.85rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Total
                                    Applications</span>
                                <strong><?php echo $reportSummary['total_applications']; ?></strong>
                            </p>
                            <p>
                                <span
                                    style="font-size: 0.85rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Total
                                    Loan Amount</span>
                                <strong>₱<?php echo number_format($reportSummary['total_amount'], 2); ?></strong>
                            </p>
                            <p>
                                <span
                                    style="font-size: 0.85rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Average
                                    Term</span>
                                <strong><?php echo $reportSummary['average_term']; ?> months</strong>
                            </p>
                        </div>
                    </div>
                    <div class="scrollable-table">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User Name</th>
                                    <th>Loan Type</th>
                                    <th>Amount</th>
                                    <th>Term (Months)</th>
                                    <th>Frequency</th>
                                    <th>Purpose</th>
                                    <th>Project Type</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData as $row): ?>
                                    <tr>
                                        <td data-label="ID"><?php echo htmlspecialchars($row['application_id']); ?></td>
                                        <td data-label="User Name"><?php echo htmlspecialchars($row['user_name']); ?></td>
                                        <td data-label="Loan Type"><?php echo htmlspecialchars($row['type_name']); ?></td>
                                        <td data-label="Amount">₱<?php echo number_format($row['final_loan_amount'], 2); ?></td>
                                        <td data-label="Term (Months)"><?php echo htmlspecialchars($row['term_length']); ?></td>
                                        <td data-label="Frequency"><?php echo htmlspecialchars($row['repayment_frequency']); ?>
                                        </td>
                                        <td data-label="Purpose"><?php echo htmlspecialchars($row['purpose']); ?></td>
                                        <td data-label="Project Type"><?php echo htmlspecialchars($row['project_type']); ?></td>
                                        <td data-label="Status"><?php echo htmlspecialchars($row['application_status']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                    <div
                        style="text-align: center; padding: 60px 20px; background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%); border-radius: 16px; margin-top: 24px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);">
                        <i class="fa-solid fa-inbox" style="font-size: 4rem; color: #cbd5e1; margin-bottom: 20px;"></i>
                        <p style="font-size: 1.125rem; color: #64748b; font-weight: 600; margin: 0;">No records found</p>
                        <p style="font-size: 0.9rem; color: #94a3b8; margin-top: 8px;">Try adjusting your filter criteria
                        </p>
                    </div>
                <?php else: ?>
                    <div
                        style="text-align: center; padding: 60px 20px; background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border-radius: 16px; margin-top: 24px; box-shadow: 0 4px 15px rgba(52, 199, 89, 0.1); border: 2px dashed var(--primary);">
                        <i class="fa-solid fa-folder-open"
                            style="font-size: 4rem; color: var(--primary); margin-bottom: 20px;"></i>
                        <p style="font-size: 1.125rem; color: var(--dark); font-weight: 600; margin: 0;">Ready to Generate
                            Report</p>
                        <p style="font-size: 0.9rem; color: #64748b; margin-top: 8px;">Select your filters above and click
                            "Generate Report" to view loan data</p>
                    </div>
                <?php endif; ?>

                <!-- Monthly Collection Report Section -->
                <div style="margin-top: 50px; padding-top: 30px; border-top: 2px solid #e2e8f0;">
                    <h3
                        style="font-size: 1.25rem; color: var(--dark); font-weight: 600; margin-bottom: 20px; display: flex; align-items: center; gap: 12px;">
                        <i class="fas fa-file-invoice-dollar" style="color: var(--primary);"></i> Monthly Collection
                        Report
                    </h3>
                    <p style="text-align: center; color: #64748b; margin-bottom: 24px; font-size: 0.95rem;">
                        Generate a comprehensive monthly collection report with payment details, receipts, and summary
                        statistics
                    </p>

                    <form method="POST" action="reports_record.php" style="max-width: 600px; margin: 0 auto;">
                        <div
                            style="background: #f8fafb; padding: 24px; border-radius: 12px; border: 1px solid #e2e8f0;">
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label for="report_month"
                                    style="display: block; margin-bottom: 8px; color: #334155; font-weight: 500;">
                                    <i class="fas fa-calendar-alt"></i> Select Report Month
                                </label>
                                <input type="month" id="report_month" name="report_month"
                                    value="<?php echo date('Y-m'); ?>" max="<?php echo date('Y-m'); ?>" required
                                    style="width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 1rem;">
                            </div>

                            <div
                                style="background: #e0f2f1; padding: 16px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #2d7d32;">
                                <p style="margin: 0; font-size: 0.9rem; color: #1b5e20;">
                                    <i class="fas fa-info-circle"></i> <strong>Report Includes:</strong>
                                </p>
                                <ul style="margin: 8px 0 0 20px; font-size: 0.85rem; color: #334155;">
                                    <li>All payments received during the selected month</li>
                                    <li>Detailed breakdown of principal and interest</li>
                                    <li>Official Receipt (OR) numbers and borrower information</li>
                                    <li>Complete summary statistics and account status</li>
                                </ul>
                            </div>

                            <button type="submit" name="generate_monthly_collection" class="action-btn"
                                style="width: 100%; padding: 14px; font-size: 1rem; background: linear-gradient(135deg, #2d7d32 0%, #1b5e20 100%); color: white; border: none; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; box-shadow: 0 4px 12px rgba(45, 125, 50, 0.3); transition: all 0.3s ease;">
                                <i class="fas fa-download"></i> Generate Monthly Collection Report (PDF)
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- End Custom Reports Tab -->

        <!-- Real-Time Operational Dashboard Tab -->
        <div id="operational-dashboard" class="tab-content">
            <div class="operational-dashboard-container">
                <h3 class="section-subtitle" style="margin-top: 30px;">Real-Time Metrics</h3>

                <!-- Real-Time KPIs Section -->
                <div style="margin-bottom: 40px;">

                    <!-- Today's Metrics -->
                    <div style="margin-bottom: 30px;">
                        <h4 style="color: #2d7d32; margin-bottom: 15px;">Today</h4>
                        <div
                            style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                            <div
                                style="background: linear-gradient(135deg, #2d7d32 0%, #1b5e20 100%); padding: 20px; border-radius: 12px; color: white; box-shadow: 0 4px 15px rgba(45, 125, 50, 0.4);">
                                <div style="font-size: 12px; opacity: 0.9; margin-bottom: 8px;">Applications Received
                                </div>
                                <div style="font-size: 28px; font-weight: 700;">
                                    <?php echo $realTimeKPIs['today']['applications']; ?>
                                </div>
                            </div>
                            <div
                                style="background: linear-gradient(135deg, #2d7d32 0%, #1b5e20 100%); padding: 20px; border-radius: 12px; color: white; box-shadow: 0 4px 15px rgba(45, 125, 50, 0.4);">
                                <div style="font-size: 12px; opacity: 0.9; margin-bottom: 8px;">Approvals</div>
                                <div style="font-size: 28px; font-weight: 700;">
                                    <?php echo $realTimeKPIs['today']['approvals']; ?>
                                </div>
                            </div>
                            <div
                                style="background: linear-gradient(135deg, #2d7d32 0%, #1b5e20 100%); padding: 20px; border-radius: 12px; color: white; box-shadow: 0 4px 15px rgba(45, 125, 50, 0.4);">
                                <div style="font-size: 12px; opacity: 0.9; margin-bottom: 8px;">Disbursements</div>
                                <div style="font-size: 28px; font-weight: 700;">
                                    ₱<?php echo number_format($realTimeKPIs['today']['disbursements'], 0); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- This Week Metrics -->
                    <div style="margin-bottom: 30px;">
                        <h4 style="color: #2d7d32; margin-bottom: 15px;">This Week</h4>
                        <div
                            style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                            <div
                                style="background: #f8f9ff; padding: 20px; border-radius: 12px; border-left: 4px solid #2d7d32;">
                                <div
                                    style="font-size: 12px; color: #999; margin-bottom: 8px; text-transform: uppercase;">
                                    Applications</div>
                                <div style="font-size: 24px; font-weight: 700; color: #2d7d32;">
                                    <?php echo $realTimeKPIs['week']['applications']; ?>
                                </div>
                            </div>
                            <div
                                style="background: #f8f9ff; padding: 20px; border-radius: 12px; border-left: 4px solid #2d7d32;">
                                <div
                                    style="font-size: 12px; color: #999; margin-bottom: 8px; text-transform: uppercase;">
                                    Approvals</div>
                                <div style="font-size: 24px; font-weight: 700; color: #2d7d32;">
                                    <?php echo $realTimeKPIs['week']['approvals']; ?>
                                </div>
                            </div>
                            <div
                                style="background: #f8f9ff; padding: 20px; border-radius: 12px; border-left: 4px solid #2d7d32;">
                                <div
                                    style="font-size: 12px; color: #999; margin-bottom: 8px; text-transform: uppercase;">
                                    Disbursements</div>
                                <div style="font-size: 24px; font-weight: 700; color: #2d7d32;">
                                    ₱<?php echo number_format($realTimeKPIs['week']['disbursements'], 0); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- This Month Metrics -->
                    <div>
                        <h4 style="color: #2d7d32; margin-bottom: 15px;">This Month</h4>
                        <div
                            style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                            <div
                                style="background: #f8f9ff; padding: 20px; border-radius: 12px; border-left: 4px solid #2d7d32;">
                                <div
                                    style="font-size: 12px; color: #999; margin-bottom: 8px; text-transform: uppercase;">
                                    Applications</div>
                                <div style="font-size: 24px; font-weight: 700; color: #2d7d32;">
                                    <?php echo $realTimeKPIs['month']['applications']; ?>
                                </div>
                            </div>
                            <div
                                style="background: #f8f9ff; padding: 20px; border-radius: 12px; border-left: 4px solid #2d7d32;">
                                <div
                                    style="font-size: 12px; color: #999; margin-bottom: 8px; text-transform: uppercase;">
                                    Approvals</div>
                                <div style="font-size: 24px; font-weight: 700; color: #2d7d32;">
                                    <?php echo $realTimeKPIs['month']['approvals']; ?>
                                </div>
                            </div>
                            <div
                                style="background: #f8f9ff; padding: 20px; border-radius: 12px; border-left: 4px solid #2d7d32;">
                                <div
                                    style="font-size: 12px; color: #999; margin-bottom: 8px; text-transform: uppercase;">
                                    Disbursements</div>
                                <div style="font-size: 24px; font-weight: 700; color: #2d7d32;">
                                    ₱<?php echo number_format($realTimeKPIs['month']['disbursements'], 0); ?></div>
                            </div>
                            <div
                                style="background: #f8f9ff; padding: 20px; border-radius: 12px; border-left: 4px solid #2d7d32;">
                                <div
                                    style="font-size: 12px; color: #999; margin-bottom: 8px; text-transform: uppercase;">
                                    Collections</div>
                                <div style="font-size: 24px; font-weight: 700; color: #2196f3;">
                                    ₱<?php echo number_format($realTimeKPIs['month']['collections'], 0); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Processing Pipeline -->
                <div style="margin-bottom: 40px;">
                    <h3 style="color: #2d7d32; margin-bottom: 20px; font-size: 1.3rem;"><i
                            class="fas fa-project-diagram"></i> Processing Pipeline</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px;">
                        <div
                            style="background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); padding: 20px; border-radius: 12px; text-align: center;">
                            <div style="font-size: 48px; font-weight: 700; color: #ff6b35; margin-bottom: 8px;">
                                <?php echo $processingPipeline['pending']; ?>
                            </div>
                            <div style="color: #d84315; font-weight: 600;">Pending Review</div>
                        </div>
                        <div
                            style="background: linear-gradient(135deg, #fff5e6 0%, #ffe0b2 100%); padding: 20px; border-radius: 12px; text-align: center;">
                            <div style="font-size: 48px; font-weight: 700; color: #ff9800; margin-bottom: 8px;">
                                <?php echo $processingPipeline['under_review']; ?>
                            </div>
                            <div style="color: #e65100; font-weight: 600;">Under Review</div>
                        </div>
                        <div
                            style="background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); padding: 20px; border-radius: 12px; text-align: center;">
                            <div style="font-size: 48px; font-weight: 700; color: #4caf50; margin-bottom: 8px;">
                                <?php echo $processingPipeline['approved_pending_disbursement']; ?>
                            </div>
                            <div style="color: #1b5e20; font-weight: 600;">Approved (Pending Disburse)</div>
                        </div>
                        <div
                            style="background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%); padding: 20px; border-radius: 12px; text-align: center;">
                            <div style="font-size: 48px; font-weight: 700; color: #f44336; margin-bottom: 8px;">
                                <?php echo $processingPipeline['rejected']; ?>
                            </div>
                            <div style="color: #b71c1c; font-weight: 600;">Rejected</div>
                        </div>
                    </div>
                </div>

                <!-- Portfolio Health -->
                <div>
                    <h3 style="color: #2d7d32; margin-bottom: 20px; font-size: 1.3rem;"><i class="fas fa-heartbeat"></i>
                        Portfolio Health</h3>
                    <div
                        style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <div
                            style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px;">
                            <div style="text-align: center;">
                                <div style="font-size: 32px; font-weight: 700; color: #2196f3;">
                                    <?php echo $portfolioHealth['total_active_loans']; ?>
                                </div>
                                <div style="color: #666; margin-top: 5px;">Total Active Loans</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 32px; font-weight: 700; color: #4caf50;">
                                    <?php echo $portfolioHealth['on_track']; ?>
                                </div>
                                <div style="color: #666; margin-top: 5px;">On Track</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 32px; font-weight: 700; color: #ff9800;">
                                    <?php echo $portfolioHealth['at_risk']; ?>
                                </div>
                                <div style="color: #666; margin-top: 5px;">At Risk</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 32px; font-weight: 700; color: #ff5722;">
                                    <?php echo $portfolioHealth['overdue']; ?>
                                </div>
                                <div style="color: #666; margin-top: 5px;">Overdue</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 32px; font-weight: 700; color: #f44336;">
                                    <?php echo $portfolioHealth['in_default']; ?>
                                </div>
                                <div style="color: #666; margin-top: 5px;">In Default</div>
                            </div>
                        </div>
                        <div
                            style="background: linear-gradient(to right, #4caf50 0%, #4caf50 <?php echo $portfolioHealth['health_percentage']; ?>%, #f0f0f0 <?php echo $portfolioHealth['health_percentage']; ?>%, #f0f0f0 100%); height: 30px; border-radius: 15px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700;">
                            <?php echo $portfolioHealth['health_percentage']; ?>% Healthy
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- End Operational Dashboard Tab -->

        <!-- Predictive Risk Models Tab -->
        <div id="predictive-risk" class="tab-content">
            <div class="predictive-risk-container">
                <h3 class="section-subtitle" style="margin-top: 30px;">Predictive Risk Models & Forecasting</h3>

                <!-- Default Risk Scores Table -->
                <div style="margin-bottom: 40px;">
                    <h4 style="color: #2d7d32; margin-bottom: 20px; font-size: 1rem;"><i
                            class="fas fa-exclamation-triangle"></i> Default Risk Analysis (Top 20 At-Risk Borrowers)
                    </h4>
                    <div class="table-wrapper" style="overflow-x: auto;">
                        <table
                            style="width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                            <thead style="background: #2d7d32; color: white;">
                                <tr>
                                    <th style="padding: 15px; text-align: left;">ID</th>
                                    <th style="padding: 15px; text-align: left;">Borrower Name</th>
                                    <th style="padding: 15px; text-align: right;">Loan Amount</th>
                                    <th style="padding: 15px; text-align: center;">Credit Status</th>
                                    <th style="padding: 15px; text-align: center;">Risk Score</th>
                                    <th style="padding: 15px; text-align: center;">Risk Level</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($defaultRiskScores)): ?>
                                    <?php foreach ($defaultRiskScores as $risk): ?>
                                        <tr style="border-bottom: 1px solid #eee; transition: background 0.3s;">
                                            <td style="padding: 12px 15px;">
                                                <?php echo htmlspecialchars($risk['application_id']); ?>
                                            </td>
                                            <td style="padding: 12px 15px;">
                                                <?php echo htmlspecialchars($risk['borrower_name']); ?>
                                            </td>
                                            <td style="padding: 12px 15px; text-align: right;">
                                                ₱<?php echo number_format($risk['final_loan_amount'], 2); ?></td>
                                            <td style="padding: 12px 15px; text-align: center;">
                                                <span
                                                    style="background: <?php echo $risk['credit_investigation_status'] === 'Approved' ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo $risk['credit_investigation_status'] === 'Approved' ? '#155724' : '#721c24'; ?>; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;">
                                                    <?php echo htmlspecialchars($risk['credit_investigation_status']); ?>
                                                </span>
                                            </td>
                                            <td style="padding: 12px 15px; text-align: center; font-weight: 700;">
                                                <?php echo $risk['risk_score']; ?>
                                            </td>
                                            <td style="padding: 12px 15px; text-align: center;">
                                                <span
                                                    style="background: <?php echo $risk['risk_level'] === 'HIGH RISK' ? '#ffebee' : ($risk['risk_level'] === 'MEDIUM RISK' ? '#fff3e0' : '#e8f5e9'); ?>; color: <?php echo $risk['risk_level'] === 'HIGH RISK' ? '#c62828' : ($risk['risk_level'] === 'MEDIUM RISK' ? '#e65100' : '#1b5e20'); ?>; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">
                                                    <?php echo htmlspecialchars($risk['risk_level']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="padding: 20px; text-align: center; color: #999;">No risk data
                                            available</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Churn Risk Analysis -->
                <div style="margin-bottom: 40px;">
                    <h4 style="color: #2d7d32; margin-bottom: 20px; font-size: 1rem;">
                        <i class="fas fa-users-slash"></i> Churn Risk Analysis (Borrower Retention Risk)
                    </h4>
                    <div class="table-wrapper" style="overflow-x: auto;">
                        <table
                            style="width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                            <thead style="background: #d32f2f; color: white;">
                                <tr>
                                    <th style="padding: 15px; text-align: left;">Borrower Name</th>
                                    <th style="padding: 15px; text-align: center;">Total Applications</th>
                                    <th style="padding: 15px; text-align: center;">Days Inactive</th>
                                    <th style="padding: 15px; text-align: center;">Risk Score</th>
                                    <th style="padding: 15px; text-align: center;">Churn Risk Level</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($churnRiskAnalysis)): ?>
                                    <?php foreach ($churnRiskAnalysis as $churn): ?>
                                        <tr style="border-bottom: 1px solid #eee;">
                                            <td style="padding: 12px 15px;">
                                                <?php echo htmlspecialchars($churn['borrower_name']); ?>
                                            </td>
                                            <td style="padding: 12px 15px; text-align: center;">
                                                <?php echo $churn['total_applications']; ?>
                                            </td>
                                            <td style="padding: 12px 15px; text-align: center;">
                                                <?php echo $churn['days_inactive']; ?>
                                            </td>
                                            <td style="padding: 12px 15px; text-align: center; font-weight: 700;">
                                                <?php echo $churn['churn_risk_score']; ?>
                                            </td>
                                            <td style="padding: 12px 15px; text-align: center;">
                                                <span
                                                    style="background: <?php echo $churn['churn_risk_level'] === 'HIGH' ? '#ffebee' : ($churn['churn_risk_level'] === 'MEDIUM' ? '#fff3e0' : '#e8f5e9'); ?>; color: <?php echo $churn['churn_risk_level'] === 'HIGH' ? '#c62828' : ($churn['churn_risk_level'] === 'MEDIUM' ? '#e65100' : '#1b5e20'); ?>; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">
                                                    <?php echo htmlspecialchars($churn['churn_risk_level']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="padding: 20px; text-align: center; color: #999;">No churn
                                            risk data available</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Revenue Forecast -->
                <div style="margin-bottom: 40px;">
                    <h4 class="section-subtitle" style="color: #2d7d32; margin-bottom: 20px; font-size: 1rem;">
                        <i class="fas fa-chart-line"></i> 12-Month Revenue Forecast
                    </h4>
                    <div class="table-wrapper" style="overflow-x: auto;">
                        <table
                            style="width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                            <thead style="background: #2196f3; color: white;">
                                <tr>
                                    <th style="padding: 15px; text-align: center;">Month</th>
                                    <th style="padding: 15px; text-align: right;">Forecasted Revenue</th>
                                    <th style="padding: 15px; text-align: center;">Confidence Level</th>
                                    <th style="padding: 15px; text-align: center;">Trend</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($revenueForecasts)): ?>
                                    <?php foreach ($revenueForecasts as $index => $forecast): ?>
                                        <tr style="border-bottom: 1px solid #eee;">
                                            <td style="padding: 12px 15px; text-align: center; font-weight: 600;">
                                                <?php echo $forecast['month']; ?>
                                            </td>
                                            <td
                                                style="padding: 12px 15px; text-align: right; font-weight: 700; color: #2196f3;">
                                                ₱<?php echo number_format($forecast['forecasted_revenue'], 2); ?></td>
                                            <td style="padding: 12px 15px; text-align: center;">
                                                <div
                                                    style="background: #e3f2fd; border-radius: 4px; padding: 4px 8px; font-size: 12px; font-weight: 600; color: #1565c0;">
                                                    <?php echo number_format($forecast['confidence'], 1); ?>%
                                                </div>
                                            </td>
                                            <td style="padding: 12px 15px; text-align: center;">
                                                <?php if ($index > 0): ?>
                                                    <?php $prevRevenue = $revenueForecasts[$index - 1]['forecasted_revenue']; ?>
                                                    <?php $trend = $forecast['forecasted_revenue'] >= $prevRevenue ? 'up' : 'down'; ?>
                                                    <?php $trendPercent = $prevRevenue != 0 ? (($forecast['forecasted_revenue'] - $prevRevenue) / $prevRevenue * 100) : 0; ?>
                                                    <span
                                                        style="color: <?php echo $trend === 'up' ? '#4caf50' : '#f44336'; ?>; font-weight: 600;">
                                                        <i class="fas fa-arrow-<?php echo $trend; ?>"></i>
                                                        <?php echo number_format(abs($trendPercent), 1); ?>%
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: #999;">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" style="padding: 20px; text-align: center; color: #999;">No forecast
                                            data available</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Portfolio Risk Trend -->
                <div>
                    <h4 class="section-subtitle" style="color: #2d7d32; margin-bottom: 20px; font-size: 1rem;">
                        <i class="fas fa-chart-area"></i> Portfolio Health Trend (Last 6 Months)
                    </h4>
                    <div class="table-wrapper" style="overflow-x: auto;">
                        <table
                            style="width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                            <thead style="background: #9c27b0; color: white;">
                                <tr>
                                    <th style="padding: 15px; text-align: center;">Month</th>
                                    <th style="padding: 15px; text-align: center;">Total Schedules</th>
                                    <th style="padding: 15px; text-align: center;">Paid</th>
                                    <th style="padding: 15px; text-align: center;">Pending</th>
                                    <th style="padding: 15px; text-align: center;">Health %</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($portfolioRiskTrend)): ?>
                                    <?php foreach ($portfolioRiskTrend as $trend): ?>
                                        <tr style="border-bottom: 1px solid #eee;">
                                            <td style="padding: 12px 15px; text-align: center; font-weight: 600;">
                                                <?php echo $trend['month']; ?>
                                            </td>
                                            <td style="padding: 12px 15px; text-align: center;">
                                                <?php echo $trend['total_schedules']; ?>
                                            </td>
                                            <td
                                                style="padding: 12px 15px; text-align: center; color: #4caf50; font-weight: 600;">
                                                <?php echo $trend['paid_schedules']; ?>
                                            </td>
                                            <td
                                                style="padding: 12px 15px; text-align: center; color: #ff9800; font-weight: 600;">
                                                <?php echo $trend['pending_schedules']; ?>
                                            </td>
                                            <td style="padding: 12px 15px; text-align: center;">
                                                <div
                                                    style="background: linear-gradient(to right, #4caf50 0%, #4caf50 <?php echo $trend['health_percentage']; ?>%, #f0f0f0 <?php echo $trend['health_percentage']; ?>%, #f0f0f0 100%); height: 24px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 12px; color: white; font-weight: 700;">
                                                    <?php echo $trend['health_percentage']; ?>%
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="padding: 20px; text-align: center; color: #999;">No trend
                                            data available</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <!-- End Predictive Risk Models Tab -->

        <!-- Portfolio Forecasting Tab -->
        <div id="portfolio-forecast" class="tab-content">
            <div class="portfolio-forecast-container">
                <h3 class="section-subtitle">Portfolio Forecasting & Projections</h3>

                <!-- Portfolio Growth Projection -->
                <div style="margin-bottom: 40px;">
                    <h4 class="section-subtitle" style="color: #2d7d32; margin-bottom: 20px; font-size: 1rem;">
                        <i class="fas fa-rocket"></i> 6-Month Portfolio Growth Projection
                    </h4>
                    <div class="table-wrapper" style="overflow-x: auto;">
                        <table
                            style="width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                            <thead style="background: linear-gradient(135deg, #2d7d32 0%, #1b5e20 100%); color: white;">
                                <tr>
                                    <th style="padding: 15px; text-align: center;">Projection Month</th>
                                    <th style="padding: 15px; text-align: center;">Projected Loans</th>
                                    <th style="padding: 15px; text-align: right;">Projected Amount</th>
                                    <th style="padding: 15px; text-align: center;">Growth Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($portfolioGrowthProjection)): ?>
                                    <?php foreach ($portfolioGrowthProjection as $proj): ?>
                                        <tr style="border-bottom: 1px solid #eee;">
                                            <td style="padding: 12px 15px; text-align: center; font-weight: 600;">
                                                <?php echo $proj['month']; ?>
                                            </td>
                                            <td
                                                style="padding: 12px 15px; text-align: center; color: #2d7d32; font-weight: 700;">
                                                <?php echo number_format($proj['projected_loans']); ?>
                                            </td>
                                            <td
                                                style="padding: 12px 15px; text-align: right; color: #1b5e20; font-weight: 700;">
                                                ₱<?php echo number_format($proj['projected_amount'], 2); ?></td>
                                            <td style="padding: 12px 15px; text-align: center;">
                                                <span
                                                    style="background: #e8f5e9; color: #2d7d32; padding: 4px 8px; border-radius: 4px; font-weight: 600; font-size: 12px;">
                                                    +<?php echo $proj['growth_rate']; ?>%
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" style="padding: 20px; text-align: center; color: #999;">No
                                            projection data available</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Loan Maturity Schedule -->
                <div style="margin-bottom: 40px;">
                    <h4 class="section-subtitle" style="color: #2d7d32; margin-bottom: 20px; font-size: 1rem;">
                        <i class="fas fa-calendar-check"></i> Loan Maturity Schedule (Next 12 Months)
                    </h4>
                    <div class="table-wrapper" style="overflow-x: auto;">
                        <table
                            style="width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                            <thead style="background: linear-gradient(135deg, #2d7d32 0%, #1b5e20 100%); color: white;">
                                <tr>
                                    <th style="padding: 15px; text-align: center;">Maturity Month</th>
                                    <th style="padding: 15px; text-align: center;">Maturing Loans</th>
                                    <th style="padding: 15px; text-align: right;">Total Principal</th>
                                    <th style="padding: 15px; text-align: right;">Already Collected</th>
                                    <th style="padding: 15px; text-align: right;">Remaining</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($loanMaturitySchedule)): ?>
                                    <?php foreach ($loanMaturitySchedule as $maturity): ?>
                                        <tr style="border-bottom: 1px solid #eee;">
                                            <td style="padding: 12px 15px; text-align: center; font-weight: 600;">
                                                <?php echo $maturity['maturity_month']; ?>
                                            </td>
                                            <td
                                                style="padding: 12px 15px; text-align: center; color: #2d7d32; font-weight: 700;">
                                                <?php echo $maturity['maturing_loans']; ?>
                                            </td>
                                            <td style="padding: 12px 15px; text-align: right;">
                                                ₱<?php echo number_format($maturity['total_principal'], 2); ?></td>
                                            <td
                                                style="padding: 12px 15px; text-align: right; color: #4caf50; font-weight: 600;">
                                                ₱<?php echo number_format($maturity['already_collected'] ?? 0, 2); ?></td>
                                            <td
                                                style="padding: 12px 15px; text-align: right; color: #ff9800; font-weight: 600;">
                                                ₱<?php echo number_format(($maturity['total_principal'] - ($maturity['already_collected'] ?? 0)), 2); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="padding: 20px; text-align: center; color: #999;">No maturity
                                            schedule data available</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Interest Income Forecast -->
                <div>
                    <h4 class="section-subtitle" style="color: #2d7d32; margin-bottom: 20px; font-size: 1rem;">
                        <i class="fa-solid fa-coins"></i> 12-Month Interest Income Forecast
                    </h4>
                    <div class="table-wrapper" style="overflow-x: auto;">
                        <table
                            style="width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                            <thead style="background: linear-gradient(135deg, #2d7d32 0%, #1b5e20 100%); color: white;">
                                <tr>
                                    <th style="padding: 15px; text-align: center;">Forecast Month</th>
                                    <th style="padding: 15px; text-align: right;">Forecasted Interest</th>
                                    <th style="padding: 15px; text-align: right;">Projected Collections</th>
                                    <th style="padding: 15px; text-align: center;">Trend</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($interestIncomeForecasts)): ?>
                                    <?php foreach ($interestIncomeForecasts as $index => $forecast): ?>
                                        <tr style="border-bottom: 1px solid #eee;">
                                            <td style="padding: 12px 15px; text-align: center; font-weight: 600;">
                                                <?php echo $forecast['month']; ?>
                                            </td>
                                            <td
                                                style="padding: 12px 15px; text-align: right; color: #1b5e20; font-weight: 700;">
                                                ₱<?php echo number_format($forecast['forecasted_interest'], 2); ?></td>
                                            <td
                                                style="padding: 12px 15px; text-align: right; color: #2d7d32; font-weight: 600;">
                                                ₱<?php echo number_format($forecast['projected_collections'], 2); ?></td>
                                            <td style="padding: 12px 15px; text-align: center;">
                                                <?php if ($index > 0): ?>
                                                    <?php $prevInterest = $interestIncomeForecasts[$index - 1]['forecasted_interest']; ?>
                                                    <?php $trend = $forecast['forecasted_interest'] >= $prevInterest ? 'up' : 'down'; ?>
                                                    <span
                                                        style="color: <?php echo $trend === 'up' ? '#4caf50' : '#f44336'; ?>; font-weight: 600;">
                                                        <i class="fas fa-arrow-<?php echo $trend; ?>"></i>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: #999;">—</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" style="padding: 20px; text-align: center; color: #999;">No forecast
                                            data available</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <!-- End Portfolio Forecasting Tab -->

        <!-- Customer Analytics Tab -->
        <div id="customer-analytics" class="tab-content">
            <div class="customer-analytics-container">
                <h3 class="section-subtitle">Customer Analytics & Insights</h3>

                <!-- Customer Segmentation -->
                <div style="margin-bottom: 40px;">
                    <h4 class="section-subtitle" style="color: #2d7d32; margin-bottom: 20px; font-size: 1rem;">
                        <i class="fas fa-chart-pie"></i> Customer Segmentation by Loan Amount
                    </h4>
                    <div
                        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px;">
                        <?php if (!empty($customerSegmentation)): ?>
                            <?php foreach ($customerSegmentation as $segment): ?>
                                <div
                                    style="background: linear-gradient(135deg, #2d7d32 0%, #1b5e20 100%); padding: 20px; border-radius: 12px; color: white; box-shadow: 0 4px 15px rgba(45, 125, 50, 0.4);">
                                    <div style="font-size: 12px; opacity: 0.9; margin-bottom: 8px;">
                                        <?php echo htmlspecialchars($segment['segment']); ?>
                                    </div>
                                    <div style="font-size: 24px; font-weight: 700; margin-bottom: 8px;">
                                        <?php echo $segment['customer_count']; ?>
                                    </div>
                                    <div style="font-size: 13px; opacity: 0.8;">
                                        ₱<?php echo number_format($segment['avg_loan_amount'], 2); ?> avg<br>
                                        ₱<?php echo number_format($segment['total_amount'], 0); ?> total
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Customer Loyalty Tiers -->
                <div style="margin-bottom: 40px;">
                    <h4 class="section-subtitle" style="color: #2d7d32; margin-bottom: 20px; font-size: 1rem;">
                        <i class="fas fa-crown"></i> Top Customers by Loyalty Tier
                    </h4>
                    <div class="table-wrapper" style="overflow-x: auto;">
                        <table
                            style="width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                            <thead style="background: #2d7d32; color: white;">
                                <tr>
                                    <th style="padding: 15px; text-align: left;">Customer Name</th>
                                    <th style="padding: 15px; text-align: center;">Applications</th>
                                    <th style="padding: 15px; text-align: right;">Total Borrowed</th>
                                    <th style="padding: 15px; text-align: right;">Total Repaid</th>
                                    <th style="padding: 15px; text-align: center;">Tenure (Days)</th>
                                    <th style="padding: 15px; text-align: center;">Loyalty Tier</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($customerLoyalty)): ?>
                                    <?php foreach ($customerLoyalty as $customer): ?>
                                        <tr style="border-bottom: 1px solid #eee;">
                                            <td style="padding: 12px 15px;">
                                                <?php echo htmlspecialchars($customer['customer_name']); ?>
                                            </td>
                                            <td style="padding: 12px 15px; text-align: center; font-weight: 600;">
                                                <?php echo $customer['repeat_applications']; ?>
                                            </td>
                                            <td
                                                style="padding: 12px 15px; text-align: right; color: #2d7d32; font-weight: 600;">
                                                ₱<?php echo number_format($customer['total_borrowed'], 2); ?></td>
                                            <td
                                                style="padding: 12px 15px; text-align: right; color: #1b5e20; font-weight: 600;">
                                                ₱<?php echo number_format($customer['total_repaid'] ?? 0, 2); ?></td>
                                            <td style="padding: 12px 15px; text-align: center; font-size: 13px;">
                                                <?php echo $customer['customer_tenure_days']; ?>
                                            </td>
                                            <td style="padding: 12px 15px; text-align: center;">
                                                <span
                                                    style="background: <?php
                                                    if ($customer['loyalty_tier'] === 'Platinum')
                                                        echo '#ffeb3b';
                                                    elseif ($customer['loyalty_tier'] === 'Gold')
                                                        echo '#ffc107';
                                                    elseif ($customer['loyalty_tier'] === 'Silver')
                                                        echo '#c0c0c0';
                                                    else
                                                        echo '#cd7f32';
                                                    ?>; color: <?php
                                                    if ($customer['loyalty_tier'] === 'Platinum')
                                                        echo '#f57f17';
                                                    elseif ($customer['loyalty_tier'] === 'Gold')
                                                        echo '#e65100';
                                                    else
                                                        echo 'white';
                                                    ?>; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">
                                                    <?php echo $customer['loyalty_tier']; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="padding: 20px; text-align: center; color: #999;">No loyalty
                                            data available</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Geographic Distribution -->
                <div>
                    <h4 class="section-subtitle" style="color: #2d7d32; margin-bottom: 20px; font-size: 1rem;">
                        <i class="fas fa-map-location-dot"></i> Geographic Distribution (Top 10)
                    </h4>
                    <div class="table-wrapper" style="overflow-x: auto;">
                        <table
                            style="width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                            <thead style="background: linear-gradient(135deg, #2d7d32 0%, #1b5e20 100%); color: white;">
                                <tr>
                                    <th style="padding: 15px; text-align: left;">Location</th>
                                    <th style="padding: 15px; text-align: center;">Customers</th>
                                    <th style="padding: 15px; text-align: center;">Loans</th>
                                    <th style="padding: 15px; text-align: right;">Total Amount</th>
                                    <th style="padding: 15px; text-align: right;">Avg Loan Size</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($geographicDistribution)): ?>
                                    <?php foreach ($geographicDistribution as $geo): ?>
                                        <tr style="border-bottom: 1px solid #eee;">
                                            <td style="padding: 12px 15px; font-weight: 600;">
                                                <?php echo htmlspecialchars($geo['location']); ?>
                                            </td>
                                            <td
                                                style="padding: 12px 15px; text-align: center; color: #667eea; font-weight: 700;">
                                                <?php echo $geo['customer_count']; ?>
                                            </td>
                                            <td
                                                style="padding: 12px 15px; text-align: center; color: #764ba2; font-weight: 700;">
                                                <?php echo $geo['loan_count']; ?>
                                            </td>
                                            <td style="padding: 12px 15px; text-align: right; font-weight: 600;">
                                                ₱<?php echo number_format($geo['total_amount'], 2); ?></td>
                                            <td
                                                style="padding: 12px 15px; text-align: right; color: #ff9800; font-weight: 600;">
                                                ₱<?php echo number_format($geo['avg_loan_size'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="padding: 20px; text-align: center; color: #999;">No
                                            geographic data available</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <!-- End Customer Analytics Tab -->

        <!-- Advanced KPI Cards Tab -->
        <div id="kpi-cards" class="tab-content">
            <div class="advanced-kpi-container">
                <h3 class="section-subtitle" style="margin-bottom: 30px;">Advanced KPI Dashboard</h3>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
                    <!-- Average Processing Time KPI -->
                    <div
                        style="background: linear-gradient(135deg, #2d7d32 0%, #1b5e20 100%); padding: 25px; border-radius: 12px; color: white; box-shadow: 0 4px 15px rgba(45, 125, 50, 0.4);">
                        <div
                            style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                            <h4 style="margin: 0; font-size: 14px; opacity: 0.9;">Avg Processing Time</h4>
                            <i class="fas fa-hourglass-end" style="font-size: 24px; opacity: 0.7;"></i>
                        </div>
                        <div style="font-size: 42px; font-weight: 700; margin-bottom: 8px;">
                            <?php echo $advancedKPIs['average_processing_time']; ?>
                        </div>
                        <div style="font-size: 13px; opacity: 0.9;">days from application to approval</div>
                    </div>

                    <!-- First-Time Approval Rate KPI -->
                    <div
                        style="background: linear-gradient(135deg, #2d7d32 0%, #1b5e20 100%); padding: 25px; border-radius: 12px; color: white; box-shadow: 0 4px 15px rgba(45, 125, 50, 0.4);">
                        <div
                            style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                            <h4 style="margin: 0; font-size: 14px; opacity: 0.9;">First-Time Approval Rate</h4>
                            <i class="fas fa-check-double" style="font-size: 24px; opacity: 0.7;"></i>
                        </div>
                        <div style="font-size: 42px; font-weight: 700; margin-bottom: 8px;">
                            <?php echo $advancedKPIs['first_time_approval_rate']; ?>%
                        </div>
                        <div style="font-size: 13px; opacity: 0.9;">of first-time applicants approved</div>
                    </div>

                    <!-- Borrower Retention Rate KPI -->
                    <div
                        style="background: linear-gradient(135deg, #2d7d32 0%, #1b5e20 100%); padding: 25px; border-radius: 12px; color: white; box-shadow: 0 4px 15px rgba(45, 125, 50, 0.4);">
                        <div
                            style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                            <h4 style="margin: 0; font-size: 14px; opacity: 0.9;">Borrower Retention Rate</h4>
                            <i class="fas fa-users" style="font-size: 24px; opacity: 0.7;"></i>
                        </div>
                        <div style="font-size: 42px; font-weight: 700; margin-bottom: 8px;">
                            <?php echo $advancedKPIs['borrower_retention_rate']; ?>%
                        </div>
                        <div style="font-size: 13px; opacity: 0.9;">repeat borrowers from total base</div>
                    </div>

                    <!-- Collection Effectiveness KPI -->
                    <div
                        style="background: linear-gradient(135deg, #2d7d32 0%, #1b5e20 100%); padding: 25px; border-radius: 12px; color: white; box-shadow: 0 4px 15px rgba(45, 125, 50, 0.4);">
                        <div
                            style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                            <h4 style="margin: 0; font-size: 14px; opacity: 0.9;">Collection Effectiveness</h4>
                            <i class="fas fa-money-bill-wave" style="font-size: 24px; opacity: 0.7;"></i>
                        </div>
                        <div style="font-size: 42px; font-weight: 700; margin-bottom: 8px;">
                            <?php echo $advancedKPIs['collection_effectiveness']; ?>%
                        </div>
                        <div style="font-size: 13px; opacity: 0.9;">collected vs expected amount</div>
                    </div>

                    <!-- Portfolio Yield KPI -->
                    <div
                        style="background: linear-gradient(135deg, #2d7d32 0%, #1b5e20 100%); padding: 25px; border-radius: 12px; color: white; box-shadow: 0 4px 15px rgba(45, 125, 50, 0.4);">
                        <div
                            style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                            <h4 style="margin: 0; font-size: 14px; opacity: 0.9;">Portfolio Yield</h4>
                            <i class="fas fa-percent" style="font-size: 24px; opacity: 0.7;"></i>
                        </div>
                        <div style="font-size: 42px; font-weight: 700; margin-bottom: 8px;">
                            <?php echo $advancedKPIs['portfolio_yield']; ?>%
                        </div>
                        <div style="font-size: 13px; opacity: 0.9;">annualized return on portfolio</div>
                    </div>

                    <!-- Average Monthly Growth KPI -->
                    <div
                        style="background: linear-gradient(135deg, #2d7d32 0%, #1b5e20 100%); padding: 25px; border-radius: 12px; color: white; box-shadow: 0 4px 15px rgba(45, 125, 50, 0.4);">
                        <div
                            style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                            <h4 style="margin: 0; font-size: 14px; opacity: 0.9;">Avg Monthly Growth</h4>
                            <i class="fas fa-chart-line" style="font-size: 24px; opacity: 0.7;"></i>
                        </div>
                        <div style="font-size: 42px; font-weight: 700; margin-bottom: 8px;">
                            <?php echo $advancedKPIs['average_monthly_growth']; ?>%
                        </div>
                        <div style="font-size: 13px; opacity: 0.9;">month-over-month portfolio growth</div>
                    </div>

                    <!-- Disbursement Efficiency KPI -->
                    <div
                        style="background: linear-gradient(135deg, #2d7d32 0%, #1b5e20 100%); padding: 25px; border-radius: 12px; color: white; box-shadow: 0 4px 15px rgba(45, 125, 50, 0.4);">
                        <div
                            style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                            <h4 style="margin: 0; font-size: 14px; opacity: 0.9;">Disbursement Efficiency</h4>
                            <i class="fas fa-tachometer-alt" style="font-size: 24px; opacity: 0.7;"></i>
                        </div>
                        <div style="font-size: 42px; font-weight: 700; margin-bottom: 8px;">
                            <?php echo $advancedKPIs['disbursement_efficiency']; ?>%
                        </div>
                        <div style="font-size: 13px; opacity: 0.9;">approved loans actually disbursed</div>
                    </div>

                    <!-- Customer Acquisition Rate KPI -->
                    <div
                        style="background: linear-gradient(135deg, #2d7d32 0%, #1b5e20 100%); padding: 25px; border-radius: 12px; color: white; box-shadow: 0 4px 15px rgba(45, 125, 50, 0.4);">
                        <div
                            style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                            <h4 style="margin: 0; font-size: 14px; opacity: 0.9;">This Month's New Customers</h4>
                            <i class="fas fa-user-plus" style="font-size: 24px; opacity: 0.7;"></i>
                        </div>
                        <div style="font-size: 42px; font-weight: 700; margin-bottom: 8px;">
                            <?php echo $advancedKPIs['customer_acquisition_rate']; ?>
                        </div>
                        <div style="font-size: 13px; opacity: 0.9;">new first-time borrowers</div>
                    </div>
                </div>
            </div>
        </div>
        <!-- End Advanced KPI Cards Tab -->
    </div>
    <!-- End main-content -->

    <script>
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
        };

        // 🧠 Helper to show "no data" image
        function showNoDataImage(containerId, imagePath, altText) {
            const element = document.getElementById(containerId);
            if (!element || !element.parentElement) {
                console.warn('Container not found:', containerId);
                return;
            }
            const container = element.parentElement;
            container.innerHTML = `
                <div class="no-data-image">
                    <img src="${imagePath}" alt="${altText}" style="width: 220px; opacity: 0.9;">
                    <p>${altText}</p>
                </div>
            `;
        }

        // ========== INITIALIZE ALL CHARTS AFTER DOM IS READY ==========
        document.addEventListener('DOMContentLoaded', function () {
            // Check if Chart.js is loaded
            if (typeof Chart === 'undefined') {
                console.error('Chart.js library not loaded!');
                return;
            }

            console.log('Chart.js version:', Chart.version);
            console.log('Initializing charts...');

            // Small delay to ensure all DOM elements are ready
            setTimeout(function () {
                // 🎨 Loan Type Chart
                const chartData = <?php echo json_encode($chartData); ?>;
                console.log('Chart Data:', chartData);

                const loanChartCanvas = document.getElementById('loanChart');
                console.log('Loan Chart Canvas:', loanChartCanvas);

                if (!chartData || chartData.length === 0) {
                    showNoDataImage('loanChart', 'IMAGE/bar graph svg.svg', 'No Loan Data Available');
                } else if (!loanChartCanvas) {
                    console.error('loanChart canvas element not found');
                } else {
                    const loanLabels = chartData.map(item => item.type_name);
                    const loanCounts = chartData.map(item => item.count);
                    const loanCtx = loanChartCanvas.getContext('2d');
                    new Chart(loanCtx, {
                        type: 'bar',
                        data: {
                            labels: loanLabels,
                            datasets: [{
                                label: 'Applications',
                                data: loanCounts,
                                backgroundColor: [
                                    'rgba(46, 125, 50, 0.8)',
                                    'rgba(234, 210, 0, 0.8)',
                                    'rgba(59, 130, 246, 0.8)',
                                    'rgba(139, 92, 246, 0.8)'
                                ],
                                borderColor: [
                                    'rgba(46, 125, 50, 1)',
                                    'rgba(234, 210, 0, 1)',
                                    'rgba(59, 130, 246, 1)',
                                    'rgba(139, 92, 246, 1)'
                                ],
                                borderWidth: 2,
                                borderRadius: 8,
                                borderSkipped: false
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        stepSize: 1,
                                        font: { size: 12, weight: '500' },
                                        color: '#64748b'
                                    },
                                    grid: {
                                        color: 'rgba(0, 0, 0, 0.05)',
                                        drawBorder: false
                                    }
                                },
                                x: {
                                    ticks: {
                                        font: { size: 12, weight: '600' },
                                        color: '#334155'
                                    },
                                    grid: {
                                        display: false
                                    }
                                }
                            },
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    backgroundColor: 'rgba(30, 41, 59, 0.95)',
                                    titleFont: { size: 14, weight: '700' },
                                    bodyFont: { size: 13 },
                                    padding: 12,
                                    borderColor: 'rgba(46, 125, 50, 0.3)',
                                    borderWidth: 1,
                                    cornerRadius: 8,
                                    displayColors: true,
                                    callbacks: {
                                        label: function (context) {
                                            return ' Applications: ' + context.parsed.y;
                                        }
                                    }
                                }
                            },
                            animation: {
                                duration: 1500,
                                easing: 'easeInOutQuart'
                            }
                        }
                    });
                }

                // 🟡 Status Chart
                const statusData = <?php echo json_encode($statusChartData); ?>;
                if (!statusData || statusData.length === 0) {
                    showNoDataImage('statusChart', 'IMAGE/doughnut svg.svg', 'No Status Data Available');
                } else {
                    const statusLabels = statusData.map(item => item.status);
                    const statusCounts = statusData.map(item => item.count);
                    const statusCtx = document.getElementById('statusChart').getContext('2d');
                    new Chart(statusCtx, {
                        type: 'doughnut',
                        data: {
                            labels: statusLabels,
                            datasets: [{
                                data: statusCounts,
                                backgroundColor: [
                                    'rgba(234, 210, 0, 0.85)',
                                    'rgba(46, 125, 50, 0.85)',
                                    'rgba(211, 47, 47, 0.85)',
                                    'rgba(59, 130, 246, 0.85)'
                                ],
                                borderColor: '#ffffff',
                                borderWidth: 3,
                                hoverOffset: 15,
                                hoverBorderWidth: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        padding: 20,
                                        font: { size: 12, weight: '600' },
                                        color: '#334155',
                                        usePointStyle: true,
                                        pointStyle: 'circle',
                                        boxWidth: 10,
                                        boxHeight: 10
                                    }
                                },
                                tooltip: {
                                    backgroundColor: 'rgba(30, 41, 59, 0.95)',
                                    titleFont: { size: 14, weight: '700' },
                                    bodyFont: { size: 13 },
                                    padding: 12,
                                    borderColor: 'rgba(46, 125, 50, 0.3)',
                                    borderWidth: 1,
                                    cornerRadius: 8,
                                    callbacks: {
                                        label: function (context) {
                                            const label = context.label || '';
                                            const value = context.parsed || 0;
                                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                            const percentage = ((value / total) * 100).toFixed(1);
                                            return ` ${label}: ${value} (${percentage}%)`;
                                        }
                                    }
                                }
                            },
                            animation: {
                                duration: 1800,
                                easing: 'easeInOutQuart',
                                animateRotate: true,
                                animateScale: true
                            }
                        }
                    });
                }

                // 🕓 Time Chart
                const timeData = <?php echo json_encode($timeChartData); ?>;
                if (!timeData || timeData.length === 0) {
                    showNoDataImage('timeChart', 'IMAGE/line graph svg.svg', 'No Time Data Available');
                } else {
                    const timeLabels = timeData.map(item => item.month);
                    const timeCounts = timeData.map(item => item.count);
                    const timeCtx = document.getElementById('timeChart').getContext('2d');
                    new Chart(timeCtx, {
                        type: 'line',
                        data: {
                            labels: timeLabels,
                            datasets: [{
                                label: 'Applications',
                                data: timeCounts,
                                borderColor: 'rgba(46, 125, 50, 1)',
                                backgroundColor: function (context) {
                                    const chart = context.chart;
                                    const { ctx, chartArea } = chart;
                                    if (!chartArea) return null;
                                    const gradient = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
                                    gradient.addColorStop(0, 'rgba(46, 125, 50, 0)');
                                    gradient.addColorStop(0.5, 'rgba(46, 125, 50, 0.15)');
                                    gradient.addColorStop(1, 'rgba(46, 125, 50, 0.3)');
                                    return gradient;
                                },
                                fill: true,
                                tension: 0.4,
                                borderWidth: 3,
                                pointRadius: 5,
                                pointHoverRadius: 8,
                                pointBackgroundColor: '#ffffff',
                                pointBorderColor: 'rgba(46, 125, 50, 1)',
                                pointBorderWidth: 2,
                                pointHoverBackgroundColor: 'rgba(46, 125, 50, 1)',
                                pointHoverBorderColor: '#ffffff',
                                pointHoverBorderWidth: 3
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: {
                                mode: 'index',
                                intersect: false
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        stepSize: 1,
                                        font: { size: 12, weight: '500' },
                                        color: '#64748b'
                                    },
                                    grid: {
                                        color: 'rgba(0, 0, 0, 0.05)',
                                        drawBorder: false
                                    }
                                },
                                x: {
                                    ticks: {
                                        font: { size: 11, weight: '600' },
                                        color: '#334155'
                                    },
                                    grid: {
                                        display: false
                                    }
                                }
                            },
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    backgroundColor: 'rgba(30, 41, 59, 0.95)',
                                    titleFont: { size: 14, weight: '700' },
                                    bodyFont: { size: 13 },
                                    padding: 12,
                                    borderColor: 'rgba(46, 125, 50, 0.3)',
                                    borderWidth: 1,
                                    cornerRadius: 8,
                                    displayColors: true,
                                    callbacks: {
                                        label: function (context) {
                                            return ' Applications: ' + context.parsed.y;
                                        }
                                    }
                                }
                            },
                            animation: {
                                duration: 2000,
                                easing: 'easeInOutQuart'
                            }
                        }
                    });
                }

                // Advanced Analytics Charts
                // Monthly Trends Chart
                const monthlyTrendsData = <?php echo json_encode($monthlyTrendsAdvanced); ?>;
                if (monthlyTrendsData.length > 0 && document.getElementById('monthlyTrendsChart')) {
                    const monthlyLabels = monthlyTrendsData.map(item => item.month);
                    const monthlyApplications = monthlyTrendsData.map(item => parseInt(item.applications));
                    const monthlyApproved = monthlyTrendsData.map(item => parseInt(item.approved));
                    const monthlyDisbursed = monthlyTrendsData.map(item => parseFloat(item.disbursed));

                    new Chart(document.getElementById('monthlyTrendsChart'), {
                        type: 'line',
                        data: {
                            labels: monthlyLabels,
                            datasets: [
                                {
                                    label: 'Applications',
                                    data: monthlyApplications,
                                    borderColor: '#2e7d32',
                                    backgroundColor: 'rgba(46, 125, 50, 0.1)',
                                    tension: 0.4
                                },
                                {
                                    label: 'Approved',
                                    data: monthlyApproved,
                                    borderColor: '#34c759',
                                    backgroundColor: 'rgba(52, 199, 89, 0.1)',
                                    tension: 0.4
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: true, position: 'top' }
                            },
                            scales: {
                                y: { beginAtZero: true }
                            }
                        }
                    });
                }

                // Loan Type Performance Chart
                const loanTypeData = <?php echo json_encode($loanTypePerformance); ?>;
                if (loanTypeData.length > 0 && document.getElementById('loanTypeChart')) {
                    const typeLabels = loanTypeData.map(item => item.type_name);
                    const typeCounts = loanTypeData.map(item => parseInt(item.total_count));
                    const typeApproved = loanTypeData.map(item => parseInt(item.approved_count));

                    new Chart(document.getElementById('loanTypeChart'), {
                        type: 'bar',
                        data: {
                            labels: typeLabels,
                            datasets: [
                                {
                                    label: 'Total Applications',
                                    data: typeCounts,
                                    backgroundColor: 'rgba(46, 125, 50, 0.7)'
                                },
                                {
                                    label: 'Approved',
                                    data: typeApproved,
                                    backgroundColor: 'rgba(52, 199, 89, 0.7)'
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: true, position: 'top' }
                            },
                            scales: {
                                y: { beginAtZero: true }
                            }
                        }
                    });
                }

                // Credit Risk Analysis Chart
                const creditRiskData = <?php echo json_encode($creditRiskAnalysis); ?>;
                if (creditRiskData.length > 0 && document.getElementById('creditRiskChart')) {
                    const riskLabels = creditRiskData.map(item => item.risk_category || 'Unknown');
                    const riskCounts = creditRiskData.map(item => parseInt(item.count));
                    const colors = ['#2e7d32', '#34c759', '#ef5350', '#ffa726', '#66bb6a'];

                    new Chart(document.getElementById('creditRiskChart'), {
                        type: 'doughnut',
                        data: {
                            labels: riskLabels,
                            datasets: [{
                                data: riskCounts,
                                backgroundColor: colors.slice(0, riskLabels.length)
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: true, position: 'right' }
                            }
                        }
                    });
                }

                // Repayment Performance Chart
                const repaymentData = <?php echo json_encode($repaymentPerformance); ?>;
                if (repaymentData.length > 0 && document.getElementById('repaymentPerformanceChart')) {
                    const repaymentLabels = repaymentData.map(item => item.status);
                    const repaymentCounts = repaymentData.map(item => parseInt(item.count));
                    const statusColors = {
                        'Paid': '#2e7d32',
                        'pending': '#ffa726',
                        'partial': '#ef5350'
                    };
                    const repaymentColors = repaymentLabels.map(label => statusColors[label] || '#999');

                    new Chart(document.getElementById('repaymentPerformanceChart'), {
                        type: 'pie',
                        data: {
                            labels: repaymentLabels,
                            datasets: [{
                                data: repaymentCounts,
                                backgroundColor: repaymentColors
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: true, position: 'right' }
                            }
                        }
                    });
                }

                // Loan Purpose Distribution Chart
                const purposeData = <?php echo json_encode($purposeAnalysis); ?>;
                if (purposeData.length > 0 && document.getElementById('purposeChart')) {
                    const purposeLabels = purposeData.map(item => item.purpose);
                    const purposeCounts = purposeData.map(item => parseInt(item.count));

                    new Chart(document.getElementById('purposeChart'), {
                        type: 'bar',
                        data: {
                            labels: purposeLabels,
                            datasets: [{
                                label: 'Number of Loans',
                                data: purposeCounts,
                                backgroundColor: 'rgba(52, 199, 89, 0.7)',
                                borderColor: '#34c759',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false }
                            },
                            scales: {
                                x: { beginAtZero: true }
                            }
                        }
                    });
                }

                // Export Analytics Data Function
                function exportAnalyticsData() {
                    alert('Export functionality - Would integrate with XLSX.js library');
                    // Implementation would use SheetJS to export data to Excel
                }

                // Enhanced Professional Print Report Function
                function printAdvancedReport() {
                    // Add print timestamp and prepare document
                    const printDate = new Date().toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });

                    const analyticsTab = document.getElementById('analytics');
                    if (!analyticsTab) {
                        alert('Analytics section not found.');
                        return;
                    }

                    // Collect KPI and table/chart elements
                    const kpiSection = analyticsTab.querySelector('.kpi-section');
                    const chartCards = analyticsTab.querySelectorAll('.chart-card');
                    const topBorrowersTable = analyticsTab.querySelector('.analytics-table');

                    // Create print header
                    const printInfo = document.createElement('div');
                    printInfo.className = 'print-header-info';
                    printInfo.innerHTML = `
                <div class="print-header">
                    <div class="print-logo">
                        <img src="IMAGE/Main-Logo.png" alt="CYCLOAN Logo" style="height: 60px;">
                    </div>
                    <div class="print-title">
                        <h1>CYCLOAN Advanced Analytics Report</h1>
                        <p class="print-date">Generated on: ${printDate}</p>
                        <p class="print-admin">Administrator: <?php echo htmlspecialchars($_SESSION['email']); ?></p>
                        <p class="print-type">Report Type: Comprehensive Loan Performance Analysis</p>
                    </div>
                </div>
            `;

                    // Create printable body container
                    const printBody = document.createElement('div');
                    printBody.className = 'print-body';

                    // Insert KPI section snapshot
                    if (kpiSection) {
                        const kpiClone = kpiSection.cloneNode(true);
                        kpiClone.classList.add('print-kpi-section');
                        printBody.appendChild(kpiClone);
                    }

                    // Convert each chart canvas to image and append
                    if (chartCards && chartCards.length) {
                        const chartsGrid = document.createElement('div');
                        chartsGrid.className = 'print-charts-grid';

                        chartCards.forEach(card => {
                            const canvas = card.querySelector('canvas');
                            const titleEl = card.querySelector('h3');
                            const title = titleEl ? titleEl.textContent.trim() : '';
                            const cardWrap = document.createElement('div');
                            cardWrap.className = 'print-chart-card';
                            const caption = document.createElement('h4');
                            caption.textContent = title;
                            cardWrap.appendChild(caption);

                            if (canvas) {
                                try {
                                    const img = new Image();
                                    img.src = canvas.toDataURL('image/png');
                                    img.style.maxWidth = '100%';
                                    img.style.height = 'auto';
                                    cardWrap.appendChild(img);
                                } catch (e) {
                                    // Fallback: leave a placeholder
                                    const placeholder = document.createElement('div');
                                    placeholder.textContent = 'Chart preview unavailable';
                                    cardWrap.appendChild(placeholder);
                                }
                            }

                            chartsGrid.appendChild(cardWrap);
                        });

                        printBody.appendChild(chartsGrid);
                    }

                    // Top borrowers table
                    if (topBorrowersTable) {
                        const tableSection = document.createElement('div');
                        tableSection.className = 'print-table-section';
                        const heading = document.createElement('h3');
                        heading.textContent = 'Top 10 Borrowers';
                        tableSection.appendChild(heading);
                        const tableClone = topBorrowersTable.cloneNode(true);
                        tableClone.classList.add('print-table');
                        tableSection.appendChild(tableClone);
                        printBody.appendChild(tableSection);
                    }

                    // Analysis notes placeholder (will be printed in footer area)
                    const analysisNotes = document.createElement('div');
                    analysisNotes.className = 'print-analysis-notes-placeholder';
                    printBody.appendChild(analysisNotes);

                    // Footer
                    const printFooter = document.createElement('div');
                    printFooter.className = 'print-footer';
                    printFooter.innerHTML = `
                <div class="footer-content">
                    <div class="footer-left">
                        <p><strong>© ${new Date().getFullYear()} CYCLOAN Loan Management System</strong></p>
                        <p>Advanced Analytics Division | Report ID: RPT-${Date.now().toString().slice(-8)}</p>
                    </div>
                    <div class="footer-right">
                        <p>Generated: ${printDate}</p>
                        <p>Authorized Personnel Only</p>
                    </div>
                </div>
            `;

                    // Insert header, body, footer into analytics tab
                    analyticsTab.insertBefore(printInfo, analyticsTab.firstChild);
                    analyticsTab.insertBefore(printBody, printInfo.nextSibling);
                    analyticsTab.appendChild(printFooter);

                    // Ensure analytics tab active
                    const analyticsTabButton = document.querySelector('[data-tab="analytics"]');
                    if (analyticsTabButton && !analyticsTabButton.classList.contains('active')) {
                        analyticsTabButton.click();
                    }

                    // Print and cleanup
                    setTimeout(() => {
                        window.print();
                        setTimeout(() => {
                            if (printInfo && printInfo.parentNode) printInfo.parentNode.removeChild(printInfo);
                            if (printBody && printBody.parentNode) printBody.parentNode.removeChild(printBody);
                            if (printFooter && printFooter.parentNode) printFooter.parentNode.removeChild(printFooter);
                        }, 700);
                    }, 700);
                }

                // Note: print header/footer and all related elements are created and cleaned up inside
                // the printAdvancedReport() function; no additional global insertion is needed here.

            }, 100); // End setTimeout
        }); // END DOMContentLoaded for charts

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
    <!-- Chart.js already loaded in <head> section at line 1692 -->
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
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
    </script>



    <script>
        function openManageInterestRateModal() {
            const modal = document.getElementById("manageInterestRateModal");
            const historyTable = document.getElementById("interestRateHistoryTable");
            const ratesGrid = document.getElementById("ratesGrid");

            if (!modal || !historyTable || !ratesGrid) {
                console.error('Modal elements not found');
                return;
            }

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
            if (!modal) {
                console.error('Modal not found');
                return;
            }
            modal.classList.remove("show");
            setTimeout(() => modal.style.display = "none", 300);
        }

        window.addEventListener("click", (event) => {
            const modal = document.getElementById("manageInterestRateModal");
            if (event.target === modal) {
                closeManageInterestRateModal();
            }
        });
    </script>
</body>

</html>
<?php
$conn->close();
?>