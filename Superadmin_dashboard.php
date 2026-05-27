<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require "CYCLOAN_db.php";
require_once 'timezone_config.php';

$current_page = basename($_SERVER['PHP_SELF']);

// Add NotificationManager for comprehensive notification system
if (file_exists('NotificationManager.php')) {
    require_once 'NotificationManager.php';
} else {
    error_log("NotificationManager.php not found - notifications will be skipped");
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/src/Exception.php';
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';

if (!isset($_SESSION['email'])) {
    header("Location: index.php");
    exit();
}

// Verify user is a superadmin
$sql = "SELECT id, email, profile_img, first_name, middle_name, last_name, phone, address FROM superadmins WHERE email = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $_SESSION["email"]);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$user) {
    $_SESSION['error'] = 'Unauthorized access. Superadmin not found.';
    header("Location: index.php");
    exit;
}

$profile_img = !empty($user['profile_img']) ? $user['profile_img'] : "default.png";

// Set role to 'superadmin'
$adminRole = 'superadmin';

// Fetch the current interest rate (default term_length = 12 months)
$sql = "SELECT interest_rate FROM interest_rates WHERE term_length = '12' ORDER BY updated_at DESC LIMIT 1";
$result = mysqli_query($conn, $sql);
if ($result && mysqli_num_rows($result) > 0) {
    $interest_rate_row = mysqli_fetch_assoc($result);
    $default_interest_rate = $interest_rate_row['interest_rate'];
} else {
    error_log('Interest rate fetch failed: ' . mysqli_error($conn), 3, 'errors.log');
    $default_interest_rate = 6.00;
}

// Fetch data for Loan Type Graph
$sql = "
    SELECT lt.type_name, COALESCE(COUNT(la.application_id), 0) AS count
    FROM (
        SELECT 'Individual' AS type_name, loan_type_id FROM loan_types WHERE type_name = 'Individual'
        UNION
        SELECT 'Cooperative' AS type_name, loan_type_id FROM loan_types WHERE type_name = 'Cooperative'
    ) lt
    LEFT JOIN loan_applications la ON lt.loan_type_id = la.loan_type_id
    GROUP BY lt.type_name
";
$result = mysqli_query($conn, $sql);
if ($result) {
    $loanTypeData = mysqli_fetch_all($result, MYSQLI_ASSOC);
} else {
    error_log("Loan Type Graph data fetch failed: " . mysqli_error($conn));
    $loanTypeData = [
        ['type_name' => 'Individual', 'count' => 0],
        ['type_name' => 'Cooperative', 'count' => 0]
    ];
}

// Fetch data for Due Accounts - Payments due within next 30 days
try {
    $stmt = $conn->prepare("
        SELECT ps.due_date, u.first_name, u.last_name, ps.amount, ps.payment_id, l.loan_id, la.application_id
        FROM payment_schedules ps
        JOIN loans l ON ps.loan_id = l.loan_id
        JOIN loan_applications la ON l.application_id = la.application_id
        JOIN users1 u ON la.user_id = u.id
        WHERE ps.status IN ('pending', 'partial', 'Unpaid')
        AND l.status IN ('active', 'Active')
        AND la.status = 'Active'
        AND ps.due_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        AND ps.due_date >= CURDATE()
        ORDER BY ps.due_date ASC
    ");
    if ($stmt === false) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $dueAccountsData = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    error_log("Due Accounts data fetch failed: " . $e->getMessage());
    $dueAccountsData = [];
}

// Fetch activity logs for display
$sql = "
    SELECT al.log_id, al.user_id, al.user_role, al.action_type, al.module, al.description, 
           al.created_at,
           CASE 
               WHEN al.user_role = 'User' THEN u.first_name
               WHEN al.user_role = 'Admin1' THEN a1.first_name
               WHEN al.user_role = 'Admin2' THEN a2.first_name
               WHEN al.user_role = 'superadmin' THEN sa.email
               ELSE NULL
           END AS first_name,
           CASE 
               WHEN al.user_role = 'User' THEN u.last_name
               WHEN al.user_role = 'Admin1' THEN a1.last_name
               WHEN al.user_role = 'Admin2' THEN a2.last_name
               ELSE NULL
           END AS last_name
    FROM activity_logs al
    LEFT JOIN users1 u ON al.user_id = u.id AND al.user_role = 'User'
    LEFT JOIN admin1 a1 ON al.user_id = a1.id AND al.user_role = 'Admin1'
    LEFT JOIN admin2 a2 ON al.user_id = a2.id AND al.user_role = 'Admin2'
    LEFT JOIN superadmins sa ON al.user_id = sa.id AND al.user_role = 'superadmin'
    ORDER BY al.created_at DESC
    LIMIT 50
";
$result = mysqli_query($conn, $sql);
if ($result) {
    $activityLogs = mysqli_fetch_all($result, MYSQLI_ASSOC);
} else {
    error_log('Activity Logs data fetch failed: ' . mysqli_error($conn), 3, 'errors.log');
    $activityLogs = [];
}

// Define mappings for action_type and module
$actionTypeMap = [
    'create' => 'Create',
    'update' => 'Update',
    'delete' => 'Delete',
    'login' => 'Login',
    'logout' => 'Logout',
    'view' => 'View',
    'approve' => 'Approve',
    'reject' => 'Reject',
    'send_reminder' => 'Send Reminder',
    'password_reset_requested' => 'Password Reset Request',
    'password_reset_completed' => 'Password Reset Completed'
];

$moduleMap = [
    'loan_application' => 'Loan Application',
    'user' => 'User Account',
    'admin' => 'Admin Account',
    'interest_rate' => 'Interest Rate',
    'payment' => 'Payment',
    'document' => 'Document',
    'remarks' => 'Remark',
    'payment_schedule' => 'Payment Schedule'
];

// Centralized email configuration and sending function
function sendEmail($to, $toName, $subject, $body, $context = '')
{
    try {
        $mail = new PHPMailer(true);

        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'scycloan@gmail.com';
        $mail->Password = 'hbfh ukgh tmzw nqbq'; // Use an app password or environment variable for security
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Timeout settings
        $mail->Timeout = 30;
        $mail->SMTPKeepAlive = true;

        // Recipients
        $mail->setFrom('scycloan@gmail.com', 'CYCLOAN Support');
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;

        // Plain text alternative
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));

        $mail->send();
        error_log("Email sent successfully to $to | Subject: $subject | Context: $context");
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed to $to | Subject: $subject | Context: $context | Error: {$mail->ErrorInfo}");
        return false;
    }
}

// Enhanced email template generator
function generateEmailTemplate($name, $content, $footer = true)
{
    $template = "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <style>
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
            .email-container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
            .email-header { background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%); color: #ffffff; padding: 30px 20px; text-align: center; }
            .email-header h1 { margin: 0; font-size: 24px; font-weight: 600; }
            .email-body { padding: 30px 20px; color: #333333; line-height: 1.6; }
            .email-body p { margin: 15px 0; }
            .email-body strong { color: #1b5e20; }
            .highlight-box { background-color: #e8f5e9; border-left: 4px solid #2e7d32; padding: 15px; margin: 20px 0; border-radius: 4px; }
            .button { display: inline-block; padding: 12px 30px; background-color: #2e7d32; color: #ffffff; text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: 600; }
            .button:hover { background-color: #1b5e20; }
            .email-footer { background-color: #f8f8f8; padding: 20px; text-align: center; font-size: 12px; color: #666666; border-top: 1px solid #e0e0e0; }
            .email-footer p { margin: 5px 0; }
        </style>
    </head>
    <body>
        <div class='email-container'>
            <div class='email-header'>
                <img src='IMAGE/Main-Logo.png' alt='CLDD Logo' style='max-width: 200px; height: auto; display: block; margin: 0 auto;'>
            </div>
            <div class='email-body'>
                <p>Hello <strong>$name</strong>,</p>
                $content
            </div>";

    if ($footer) {
        $template .= "
            <div class='email-footer'>
                <p><strong>CLDD Loan Support Team</strong></p>
                <p>Email: scycloan@gmail.com | Phone: 0981-303-8698 | Landline: 545-6789 loc 8018-19</p>
                <p>Address: Lower Ground Floor (LG)24 New City Hall Bldg, Bacnotan St., Brgy Real, Calamba City Laguna</p>
                <p>© " . date('Y') . " CLDD Loan Program. All rights reserved.</p>
                <p style='margin-top: 10px; font-size: 11px;'>This is an automated message. Please do not reply directly to this email.</p>
            </div>";
    }

    $template .= "
        </div>
    </body>
    </html>";

    return $template;
}

// Function to send loan status update email
function sendLoanStatusEmail($conn, $applicationId, $newStatus)
{
    $sql = "
        SELECT u.email, u.first_name, u.last_name
        FROM loan_applications la
        JOIN users1 u ON la.user_id = u.id
        WHERE la.application_id = ?
    ";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $applicationId); // 's' for string application_id
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$user) {
        error_log("No user found for application_id: $applicationId");
        return false;
    }

    $to = $user['email'];
    $name = trim($user['first_name'] . ' ' . $user['last_name']);
    $safeStatus = htmlspecialchars($newStatus);

    // Determine status color and icon
    $statusColor = '#2e7d32';
    $statusIcon = '✅';

    switch (strtolower($newStatus)) {
        case 'approved':
            $statusColor = '#2e7d32';
            $statusIcon = '✅';
            break;
        case 'rejected':
        case 'cancelled':
            $statusColor = '#d32f2f';
            $statusIcon = '❌';
            break;
        case 'pending':
        case 'new':
            $statusColor = '#fbc02d';
            $statusIcon = '⏳';
            break;
        case 'active':
            $statusColor = '#1976d2';
            $statusIcon = '🔵';
            break;
        case 'closed':
            $statusColor = '#757575';
            $statusIcon = '⚫';
            break;
    }

    $content = "
        <p>We have an important update regarding your loan application.</p>
        <div class='highlight-box'>
            <p style='font-size: 18px;'>$statusIcon <strong>Loan Status:</strong> <span style='color: $statusColor;'>$safeStatus</span></p>
            <p><strong>Application ID:</strong> $applicationId</p>
        </div>
        <p>Your application status has been updated. Please log in to your account for complete details.</p>
        <p>If you have any questions or concerns, please don't hesitate to contact our support team.</p>
        <a href='user_dashboard.php' class='button'>View Application Details</a>
        <p>Best regards,<br><strong>The CLDD Loan Support Team</strong></p>
    ";

    $emailBody = generateEmailTemplate($name, $content);
    $subject = "Loan Status Update - Application #$applicationId";

    try {
        $result = sendEmail($to, $name, $subject, $emailBody, "Loan Status Update: App $applicationId, Status: $newStatus");

        if ($result) {
            error_log("Loan status email sent to $to for application_id: $applicationId, status: $newStatus");
        }

        return $result;
    } catch (Exception $e) {
        error_log("Error sending loan status email for application_id $applicationId: " . $e->getMessage());
        return false;
    }
}

/**
 * Create comprehensive notifications for all stakeholders (User, Admin1, Admin2, Superadmin)
 * 
 * @param mysqli $conn Database connection
 * @param int $userId User ID to notify
 * @param int $applicationId Application ID
 * @param string $type Notification type (approval, status, warning, reminder, document)
 * @param string $title Notification title
 * @param string $message Notification message
 * @param string $priority Priority level (normal, high, urgent)
 * @param array $adminTypes Which admins to notify ['admin1', 'admin2', 'superadmin']
 * @return bool Success status
 */
function createComprehensiveNotification($conn, $userId, $applicationId, $type, $title, $message, $priority = 'normal', $adminTypes = ['admin1', 'admin2', 'superadmin'])
{
    try {
        if (!class_exists('NotificationManager')) {
            error_log("⚠️ NotificationManager not available for notifications");
            return false;
        }

        $notificationManager = new NotificationManager($conn);

        // Create notification for the user
        if ($userId > 0) {
            $notificationManager->createNotification($userId, $type, $title, $message, $priority);
            error_log("✅ User notification created: $title (User ID: $userId)");
        }

        $totalAdminsNotified = 0;

        // Create notifications for Admin1 users
        if (in_array('admin1', $adminTypes)) {
            $admin1Query = $conn->prepare("SELECT id, first_name, last_name FROM admin1");
            if ($admin1Query) {
                $admin1Query->execute();
                $admin1Result = $admin1Query->get_result();

                while ($admin1User = $admin1Result->fetch_assoc()) {
                    $admin1Title = "[Admin1 Alert] $title";
                    $admin1Message = "Application #$applicationId requires attention. $message";
                    $notificationManager->createNotification($admin1User['id'], $type, $admin1Title, $admin1Message, $priority);
                    $totalAdminsNotified++;
                }
                $admin1Query->close();
                error_log("✅ Admin1 users notified for application #$applicationId");
            }
        }

        // Create notifications for Admin2 users
        if (in_array('admin2', $adminTypes)) {
            $admin2Query = $conn->prepare("SELECT id, first_name, last_name FROM admin2");
            if ($admin2Query) {
                $admin2Query->execute();
                $admin2Result = $admin2Query->get_result();

                while ($admin2User = $admin2Result->fetch_assoc()) {
                    $admin2Title = "[Admin2 Alert] $title";
                    $admin2Message = "Application #$applicationId has been updated. $message";
                    $notificationManager->createNotification($admin2User['id'], $type, $admin2Title, $admin2Message, $priority);
                    $totalAdminsNotified++;
                }
                $admin2Query->close();
                error_log("✅ Admin2 users notified for application #$applicationId");
            }
        }

        // Create notifications for Superadmin users
        if (in_array('superadmin', $adminTypes)) {
            $superadminQuery = $conn->prepare("SELECT id, first_name, last_name FROM superadmins");
            if ($superadminQuery) {
                $superadminQuery->execute();
                $superadminResult = $superadminQuery->get_result();

                while ($superadminUser = $superadminResult->fetch_assoc()) {
                    $superadminTitle = "[System Alert] $title";
                    $superadminMessage = "Application #$applicationId status update. $message";
                    $notificationManager->createNotification($superadminUser['id'], $type, $superadminTitle, $superadminMessage, $priority);
                    $totalAdminsNotified++;
                }
                $superadminQuery->close();
                error_log("✅ Superadmin users notified for application #$applicationId");
            }
        }

        error_log("📢 Comprehensive notification sent: $totalAdminsNotified admins + 1 user for App #$applicationId");
        return true;

    } catch (Exception $e) {
        error_log("❌ Comprehensive notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all system notifications for Superadmin dashboard
 * 
 * @param mysqli $conn Database connection
 * @param int $adminId Superadmin ID
 * @param int $limit Number of notifications to fetch
 * @return array Notifications array
 */
function getSuperadminNotifications($conn, $adminId, $limit = 50)
{
    try {
        if (!class_exists('NotificationManager')) {
            return [];
        }

        $notificationManager = new NotificationManager($conn);

        // Get notifications for this superadmin
        $notifications = $notificationManager->getUserNotifications($adminId, [], 1, $limit);

        return $notifications['notifications'] ?? [];

    } catch (Exception $e) {
        error_log("❌ Error fetching superadmin notifications: " . $e->getMessage());
        return [];
    }
}

// Fetch loan applicants
$sql = "
    SELECT la.application_id, u.first_name, u.last_name, lt.type_name, la.amount_applied, 
           la.final_loan_amount, la.status, 
           la.pre_approval_status, la.credit_investigation_status, la.created_at
    FROM loan_applications la
    JOIN users1 u ON la.user_id = u.id
    JOIN loan_types lt ON la.loan_type_id = lt.loan_type_id
    ORDER BY la.created_at DESC
";
$result = mysqli_query($conn, $sql);
$loanApplicants = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];

// Fetch status counts
$sql = "
    SELECT 
        SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) AS active_count,
        SUM(CASE WHEN status IN ('Pending', 'New') THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN status = 'Closed' THEN 1 ELSE 0 END) AS closed_count
    FROM loan_applications
";
$result = mysqli_query($conn, $sql);
$statusCounts = $result ? mysqli_fetch_assoc($result) : ['active_count' => 0, 'pending_count' => 0, 'closed_count' => 0];
$activeCount = $statusCounts['active_count'] ?? 0;
$pendingCount = $statusCounts['pending_count'] ?? 0;
$closedCount = $statusCounts['closed_count'] ?? 0;

// Fetch notifications for superadmin dashboard
$notifications = getSuperadminNotifications($conn, $user['id'], 20);

// Handle AJAX request for loan details
if (isset($_GET['action']) && $_GET['action'] === 'get_loan_details' && isset($_GET['application_id'])) {
    $applicationId = trim($_GET['application_id']);
    $sql = "
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
        WHERE la.application_id = ?
    ";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $applicationId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $loan = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($loan) {
        $sql = "
            SELECT dt.document_name, d.file_path, d.document_id, d.status, d.status_updated_at
            FROM documents d
            JOIN document_types dt ON d.document_type_id = dt.document_type_id
            WHERE d.application_id = ?
        ";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $applicationId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $documents = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);

        $sql = "
            SELECT remark_id, remarks, created_at, admin_name
            FROM remarks
            WHERE application_id = ?
            ORDER BY created_at DESC
        ";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $applicationId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $remarks = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);

        // Build dynamic query to find logs by searching descriptions
        $documentNames = array_column($documents, 'document_name');

        $whereClauses = [];
        $whereClauses[] = "description COLLATE utf8mb4_general_ci LIKE CONCAT('%', ?, '%')"; // Search for application_id

        // Add search for loan_id if available
        if (!empty($loan['loan_id'])) {
            $whereClauses[] = "description COLLATE utf8mb4_general_ci LIKE CONCAT('%', ?, '%')";
        }

        // Add search for each document name
        foreach ($documentNames as $docName) {
            $whereClauses[] = "description COLLATE utf8mb4_general_ci LIKE CONCAT('%', ?, '%')";
        }

        $whereClause = implode(' OR ', $whereClauses);

        $sql = "
            SELECT action_type, module, description, created_at, user_role, admin_name, admin_email
            FROM activity_logs
            WHERE ($whereClause) AND module IN ('loan_application', 'remarks', 'document')
            ORDER BY created_at DESC
            LIMIT 50
        ";

        $stmt = mysqli_prepare($conn, $sql);

        // Build bind parameters
        $bindTypes = 's'; // application_id
        $bindParams = [$applicationId];

        if (!empty($loan['loan_id'])) {
            $bindTypes .= 's';
            $bindParams[] = $loan['loan_id'];
        }

        foreach ($documentNames as $docName) {
            $bindTypes .= 's';
            $bindParams[] = $docName;
        }

        mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindParams);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $logs = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);

        $response = [
            'success' => true,
            'loan' => $loan,
            'documents' => $documents,
            'remarks' => $remarks,
            'logs' => $logs
        ];
    } else {
        $response = ['success' => false, 'message' => 'Loan application not found.'];
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Handle AJAX request for updating status and remarks
if (isset($_POST['action']) && $_POST['action'] === 'update_status' && isset($_POST['application_id'])) {
    // Debug logging for POST data
    error_log("POST data received: " . json_encode($_POST));

    $applicationId = isset($_POST['application_id']) ? trim($_POST['application_id']) : null;
    $loanStatus = isset($_POST['status']) ? trim($_POST['status']) : null;
    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';

    // Additional validation for application_id
    $rawAppId = $_POST['application_id'] ?? null;

    // Log debug information
    error_log("Raw application_id: " . var_export($rawAppId, true));
    error_log("Cleaned application_id: " . var_export($applicationId, true));

    // Check for various invalid states - application_id should be a string like APP-YYYYMMDD-NNNN
    if (empty($rawAppId) || $rawAppId === 'null' || $rawAppId === 'undefined' || empty($applicationId)) {
        error_log("Invalid application_id detected - raw=" . ($rawAppId ?? 'NULL') . ", cleaned=" . ($applicationId ?? 'NULL'));
        error_log("Full POST data: " . print_r($_POST, true));
        $response = ['success' => false, 'message' => 'Invalid application ID received. Please refresh the page and try again.'];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    // Validate application_id format (should be like APP-YYYYMMDD-NNNN or LOAN-YYYYMMDD-NNNN)
    if (!preg_match('/^(APP|LOAN)-\d{8}-\d{4}$/', $applicationId)) {
        error_log("Application ID has invalid format: " . $applicationId);
        $response = ['success' => false, 'message' => 'Invalid application ID format. Please refresh the page and try again.'];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    if ($adminRole !== 'superadmin') {
        $response = ['success' => false, 'message' => 'Unauthorized access.'];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    // Check if application_id exists
    $sql = "SELECT application_id, status, pre_approval_status, credit_investigation_status FROM loan_applications WHERE application_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        error_log("Database prepare failed: " . mysqli_error($conn));
        $response = ['success' => false, 'message' => 'Database error occurred. Please try again.'];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    mysqli_stmt_bind_param($stmt, "s", $applicationId); // 's' for string application_id
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $existingApp = mysqli_fetch_assoc($result);

    if (!$existingApp) {
        error_log("Application ID does not exist in database: $applicationId");
        mysqli_stmt_close($stmt);
        $response = ['success' => false, 'message' => "Loan application not found for ID: $applicationId. Please refresh the page and try again."];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    mysqli_stmt_close($stmt);

    error_log("Found application: ID={$existingApp['application_id']}, status={$existingApp['status']}, pre_approval={$existingApp['pre_approval_status']}, credit_investigation={$existingApp['credit_investigation_status']}");

    if ($loanStatus) {
        // Use the data we already fetched instead of making another query
        $currentStatuses = $existingApp;

        if (
            strcasecmp($currentStatuses['pre_approval_status'], 'Approved') !== 0 ||
            strcasecmp($currentStatuses['credit_investigation_status'], 'Completed') !== 0
        ) {
            $response = [
                'success' => false,
                'message' => 'Loan status cannot be changed unless Pre-Approval Status is Approved and Credit Investigation Status is Completed. Current: ' .
                    $currentStatuses['pre_approval_status'] . ', ' . $currentStatuses['credit_investigation_status']
            ];
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
    }

    $updateFields = [];
    $types = "";
    $values = [];

    if ($loanStatus && in_array($loanStatus, ['Pending', 'Active', 'Completed', 'New', 'Renewal'])) {
        $updateFields[] = "status = ?";
        $types .= "s";
        $values[] = $loanStatus;
    }

    // Always append the WHERE clause parameter last
    $types .= "s"; // 's' for string application_id
    $values[] = $applicationId;

    error_log("Received: application_id=$applicationId, loan_status=$loanStatus, role=$adminRole");

    if (!empty($updateFields)) {
        $sql = "UPDATE loan_applications SET " . implode(', ', $updateFields) . " WHERE application_id = ?";
        error_log("Executing query: $sql with types: $types and values: " . json_encode($values));

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            error_log("Update prepare failed: " . mysqli_error($conn));
            $response = ['success' => false, 'message' => 'Database error during update. Please try again.'];
        } else {
            mysqli_stmt_bind_param($stmt, $types, ...$values);
            $executeResult = mysqli_stmt_execute($stmt);

            if (!$executeResult) {
                error_log("Update execution failed: " . mysqli_stmt_error($stmt));
                $response = ['success' => false, 'message' => 'Database error during update execution. Please try again.'];
            } else {
                $affectedRows = mysqli_stmt_affected_rows($stmt);
                error_log("Update completed: affected_rows=$affectedRows");

                if ($affectedRows === 0) {
                    error_log("No rows updated. This could mean the new value is the same as the current value, or the application_id doesn't exist: $applicationId");
                    // Check if this is because the value is the same
                    if ($loanStatus && $existingApp['status'] === $loanStatus) {
                        $response = ['success' => true, 'message' => 'Status is already set to the selected value.'];
                    } else {
                        $response = ['success' => false, 'message' => "No rows updated. Please verify the application exists and try again."];
                    }
                } else {
                    // Send email notification
                    if ($loanStatus && !sendLoanStatusEmail($conn, $applicationId, $loanStatus)) {
                        error_log("Failed to send loan status email for application_id: $applicationId");
                    }

                    // Create comprehensive notifications for all stakeholders
                    if ($loanStatus) {
                        // Get user ID for the application
                        $userQuery = $conn->prepare("SELECT user_id FROM loan_applications WHERE application_id = ?");
                        if ($userQuery) {
                            $userQuery->bind_param("s", $applicationId); // 's' for string application_id
                            $userQuery->execute();
                            $userResult = $userQuery->get_result();
                            $userRow = $userResult->fetch_assoc();
                            $userQuery->close();

                            if ($userRow) {
                                $userId = $userRow['user_id'];
                                $notificationTitle = "Loan Status Updated";
                                $notificationMessage = "Your loan application status has been changed to: $loanStatus";

                                // Determine priority based on status
                                $priority = 'normal';
                                if (in_array(strtolower($loanStatus), ['approved', 'rejected'])) {
                                    $priority = 'high';
                                }

                                // Create notifications for user and all admin types
                                createComprehensiveNotification(
                                    $conn,
                                    $userId,
                                    $applicationId,
                                    'status',
                                    $notificationTitle,
                                    $notificationMessage,
                                    $priority,
                                    ['admin1', 'admin2'] // Don't notify other superadmins
                                );
                            }
                        }
                    }

                    $response = ['success' => true, 'message' => 'Status updated successfully.'];
                }
            }
            mysqli_stmt_close($stmt);
        }
    } else {
        error_log("No valid status updates provided.");
        if ($loanStatus) {
            $response = ['success' => false, 'message' => "Invalid loan status: $loanStatus"];
        } else {
            $response = ['success' => true, 'message' => 'No status updates provided.'];
        }
    }

    if (!empty($remarks)) {
        $adminName = isset($_SESSION['first_name']) && isset($_SESSION['last_name'])
            ? $_SESSION['first_name'] . ' ' . $_SESSION['last_name']
            : 'Superadmin';

        $sql = "INSERT INTO remarks (application_id, remarks, created_at, admin_name) VALUES (?, ?, NOW(), ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sss", $applicationId, $remarks, $adminName); // 's' for string application_id
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $response['message'] = 'Status and/or remarks updated successfully.';
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Handle AJAX request for updating interest rate
if (isset($_POST['action']) && $_POST['action'] === 'update_interest_rate' && isset($_POST['interest_rate']) && isset($_POST['term_length'])) {
    $newInterestRate = floatval($_POST['interest_rate']);
    $termLength = $_POST['term_length'];
    $updatedBy = $user['id']; // Use admin ID from session

    if ($adminRole !== 'superadmin') {
        $response = ['success' => false, 'message' => 'Unauthorized access.'];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    if ($newInterestRate <= 0 || $newInterestRate > 100) {
        $response = ['success' => false, 'message' => 'Interest rate must be between 0.01% and 100%.'];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    if (!in_array($termLength, ['6', '12', '18', '24', '36'])) {
        $response = ['success' => false, 'message' => 'Invalid term length.'];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    // Check if rate exists for this term length
    $checkSql = "SELECT id FROM interest_rates WHERE term_length = ?";
    $checkStmt = mysqli_prepare($conn, $checkSql);
    mysqli_stmt_bind_param($checkStmt, "s", $termLength);
    mysqli_stmt_execute($checkStmt);
    $checkResult = mysqli_stmt_get_result($checkStmt);

    // Get current time in Philippine Time (UTC+8)
    $phpTimeZone = new DateTimeZone('Asia/Manila');
    $now = new DateTime('now', $phpTimeZone);
    $updatedAtTime = $now->format('Y-m-d H:i:s');

    if (mysqli_num_rows($checkResult) > 0) {
        // Update existing rate
        $sql = "UPDATE interest_rates SET interest_rate = ?, updated_at = ?, updated_by = ? WHERE term_length = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "diss", $newInterestRate, $updatedAtTime, $updatedBy, $termLength);
    } else {
        // Insert new rate
        $sql = "INSERT INTO interest_rates (interest_rate, term_length, updated_at, updated_by) VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "dsis", $newInterestRate, $termLength, $updatedAtTime, $updatedBy);
    }

    mysqli_stmt_close($checkStmt);

    if (mysqli_stmt_execute($stmt)) {
        $response = ['success' => true, 'message' => 'Interest rate updated successfully for ' . $termLength . ' months term.', 'new_rate' => $newInterestRate];
    } else {
        $response = ['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)];
    }

    mysqli_stmt_close($stmt);
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Handle AJAX request for fetching interest rate history
if (isset($_GET['action']) && $_GET['action'] === 'get_interest_rate_history') {
    $sql = "SELECT ir.id, ir.interest_rate, ir.term_length, ir.updated_at, u.email as updated_by 
            FROM interest_rates ir 
            LEFT JOIN users1 u ON ir.updated_by = u.id 
            ORDER BY ir.term_length ASC, ir.updated_at DESC";
    $result = mysqli_query($conn, $sql);
    if ($result) {
        $history = mysqli_fetch_all($result, MYSQLI_ASSOC);
        $response = ['success' => true, 'history' => $history];
    } else {
        error_log("Interest Rate History Fetch Error: " . mysqli_error($conn));
        $response = ['success' => false, 'message' => 'Error: ' . mysqli_error($conn)];
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Handle AJAX request for fetching all current interest rates
if (isset($_GET['action']) && $_GET['action'] === 'get_all_interest_rates') {
    $sql = "SELECT term_length, interest_rate, updated_at FROM interest_rates ORDER BY term_length ASC";
    $result = mysqli_query($conn, $sql);
    if ($result) {
        $rates = mysqli_fetch_all($result, MYSQLI_ASSOC);
        $response = ['success' => true, 'rates' => $rates];
    } else {
        error_log("Interest Rates Fetch Error: " . mysqli_error($conn));
        $response = ['success' => false, 'message' => 'Error: ' . mysqli_error($conn)];
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Handle AJAX request for fetching current interest rate
if (isset($_GET['action']) && $_GET['action'] === 'get_current_interest_rate') {
    $sql = "SELECT interest_rate FROM interest_rates WHERE term_length = '12' ORDER BY updated_at DESC LIMIT 1";
    $result = mysqli_query($conn, $sql);
    if ($result && mysqli_num_rows($result) > 0) {
        $interest_rate_row = mysqli_fetch_assoc($result);
        $current_rate = $interest_rate_row['interest_rate'];
    } else {
        error_log("Current Interest Rate Fetch Error: " . mysqli_error($conn));
        $current_rate = 6.00;
    }

    $response = ['success' => true, 'interest_rate' => $current_rate];
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Handle AJAX request for refreshing loan applicants table
if (isset($_GET['action']) && $_GET['action'] === 'get_loan_applicants') {
    $sql = "
        SELECT la.application_id, u.first_name, u.last_name, lt.type_name, la.amount_applied, la.status, 
               la.pre_approval_status, la.credit_investigation_status, la.created_at, la.final_loan_amount
        FROM loan_applications la
        JOIN users1 u ON la.user_id = u.id
        JOIN loan_types lt ON la.loan_type_id = lt.loan_type_id
        ORDER BY la.created_at DESC
    ";
    $result = mysqli_query($conn, $sql);

    if ($result) {
        $loanApplicants = mysqli_fetch_all($result, MYSQLI_ASSOC);
        $response = ['success' => true, 'applicants' => $loanApplicants];
    } else {
        error_log("Loan Applicants Fetch Error: " . mysqli_error($conn));
        $response = ['success' => false, 'message' => 'Error: ' . mysqli_error($conn)];
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Handle payment reminder sending
if (isset($_POST['action']) && $_POST['action'] === 'send_payment_reminder' && isset($_POST['payment_id'])) {
    try {
        // Clear all output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');

        $paymentId = intval($_POST['payment_id']);
        $stmt = mysqli_prepare($conn, "
            SELECT ps.due_date, ps.amount, u.email, u.first_name, u.last_name, la.application_id
            FROM payment_schedules ps
            JOIN loans l ON ps.loan_id = l.loan_id
            JOIN loan_applications la ON l.application_id = la.application_id
            JOIN users1 u ON la.user_id = u.id
            WHERE ps.payment_id = ?
        ");
        mysqli_stmt_bind_param($stmt, "i", $paymentId);
        mysqli_stmt_execute($stmt);
        $payment = mysqli_stmt_get_result($stmt)->fetch_assoc();
        mysqli_stmt_close($stmt);

        if (!$payment) {
            http_response_code(404);
            die(json_encode(['success' => false, 'message' => "Payment not found for payment_id: $paymentId"]));
        }

        $to = $payment['email'];
        $name = trim($payment['first_name'] . ' ' . $payment['last_name']);
        $dueDate = date('Y-m-d', strtotime($payment['due_date']));
        $amount = number_format($payment['amount'], 2);

        // Create fresh PHPMailer instance
        $reminderMail = new PHPMailer(true);
        $reminderMail->isSMTP();
        $reminderMail->Host = 'smtp.gmail.com';
        $reminderMail->SMTPAuth = true;
        $reminderMail->Username = 'scycloan@gmail.com';
        $reminderMail->Password = 'hbfh ukgh tmzw nqbq';
        $reminderMail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $reminderMail->Port = 587;

        $reminderMail->setFrom('scycloan@gmail.com', 'CLDD Loan Support');
        $reminderMail->addAddress($to);

        $reminderMail->isHTML(true);
        $reminderMail->Subject = 'CLDD Loan Program - Payment Reminder';
        $reminderMail->Body = "
            Hello <strong>$name</strong>,<br><br>
            This is a reminder that your payment of <strong>$amount PHP</strong> for loan application (ID: {$payment['application_id']}) is due on <strong>$dueDate</strong>.<br>
            Please ensure timely payment to avoid penalties. Log in to your account for more details or contact our support team.<br><br>
            Best regards,<br>
            The CLDD Team
        ";

        $reminderMail->send();

        http_response_code(200);
        die(json_encode(['success' => true, 'message' => 'Payment reminder sent successfully.']));
    } catch (Exception $e) {
        error_log("Send Payment Reminder Error: " . $e->getMessage());
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]));
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard</title>
    <link rel="stylesheet" href="CSS/admin_dashboard.css">
    <link rel="stylesheet" href="CSS/admin_profile.css">
    <link rel="stylesheet" href="CSS/nav_active.css">
    <link rel="stylesheet" href="CSS/superadmin_dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>

    <!-- Toast Notification Styles -->
    <style>
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
        }

        .toast {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            margin-bottom: 10px;
            overflow: hidden;
            transform: translateX(400px);
            transition: all 0.3s ease;
            opacity: 0;
        }

        .toast.show {
            transform: translateX(0);
            opacity: 1;
        }

        .toast.success {
            border-left: 4px solid #10b981;
        }

        .toast.error {
            border-left: 4px solid #ef4444;
        }

        .toast.info {
            border-left: 4px solid #3b82f6;
        }

        .toast-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px 8px;
            font-weight: 600;
            font-size: 14px;
        }

        .toast-body {
            padding: 0 16px 12px;
            font-size: 13px;
            color: #6b7280;
            line-height: 1.4;
        }

        .toast-close {
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: #6b7280;
            padding: 0;
            line-height: 1;
        }

        .toast-close:hover {
            color: #374151;
        }

        .toast-icon {
            margin-right: 8px;
            font-size: 16px;
        }

        .toast.success .toast-icon {
            color: #10b981;
        }

        .toast.error .toast-icon {
            color: #ef4444;
        }

        .toast.info .toast-icon {
            color: #3b82f6;
        }

        /* Confirmation Modal Styles */
        .confirmation-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 10000;
        }

        .confirmation-modal.show {
            display: flex;
        }

        .confirmation-content {
            background: white;
            border-radius: 12px;
            padding: 24px;
            max-width: 400px;
            width: 90%;
            text-align: center;
            transform: scale(0.7);
            transition: transform 0.2s ease;
        }

        .confirmation-modal.show .confirmation-content {
            transform: scale(1);
        }

        .confirmation-icon {
            font-size: 48px;
            margin-bottom: 16px;
            color: #f59e0b;
        }

        .confirmation-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #1f2937;
        }

        .confirmation-message {
            color: #6b7280;
            margin-bottom: 24px;
            line-height: 1.5;
        }

        .confirmation-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        .btn-confirm {
            background: #ef4444;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.2s;
        }

        .btn-confirm:hover {
            background: #dc2626;
        }

        .btn-cancel {
            background: #f3f4f6;
            color: #374151;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.2s;
        }

        .btn-cancel:hover {
            background: #e5e7eb;
        }

        /* Loading state for submit button */
        .submit-btn.loading {
            opacity: 0.7;
            cursor: not-allowed;
            position: relative;
        }

        .submit-btn.loading::after {
            content: '';
            position: absolute;
            left: 50%;
            top: 50%;
            width: 16px;
            height: 16px;
            margin: -8px 0 0 -8px;
            border: 2px solid transparent;
            border-top: 2px solid currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .submit-btn.loading .button-text {
            opacity: 0;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }
    </style>
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

    /* Profile Dropdown CSS (Admin1 parity) */
    .profile-container {
        position: relative;
        display: inline-block;
    }

    .profile-container .profile {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        cursor: pointer;
        border: 2px solid #fff;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
    }

    .profile-container .profile:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
    }

    .profile-container .dropdown-menu {
        position: absolute;
        top: 50px;
        right: 0;
        background: white;
        border-radius: 8px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        border: 1px solid #e0e0e0;
        min-width: 200px;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: all 0.3s ease;
        z-index: 1000;
    }

    .profile-container .dropdown-menu.show {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .profile-container .dropdown-menu ul {
        list-style: none;
        padding: 8px 0;
        margin: 0;
    }

    .profile-container .dropdown-menu li {
        margin: 0;
    }

    .profile-container .dropdown-menu a {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 20px;
        color: #333;
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.2s ease;
        border: none;
        margin: 0;
    }

    .profile-container .dropdown-menu a:hover {
        background: #f8f9fa;
        color: #1b5e20;
        transform: translateX(5px);
    }

    .profile-container .dropdown-menu .profile-icon {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        border: 1px solid #e0e0e0;
    }

    .profile-container .dropdown-menu .fa-solid {
        font-size: 16px;
        width: 24px;
        text-align: center;
    }

    .profile-container .dropdown-menu .logout {
        color: #dc3545;
    }

    .profile-container .dropdown-menu .logout:hover {
        background: #fff5f5;
        color: #dc3545;
    }

    /* Focus and keyboard navigation */
    .profile-container [role="button"]:focus {
        outline: 2px solid #1b5e20;
        outline-offset: 2px;
    }

    .profile-container .dropdown-menu [role="menuitem"]:focus {
        background: #f8f9fa;
        color: #1b5e20;
        outline: none;
    }

    .close-modal-btn:hover {
        background: rgba(255, 255, 255, 0.2) !important;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }

    /* Timeline Components Styling */
    .logs-timeline {
        position: relative;
        padding-left: 10px;
        max-height: 350px;
        overflow-y: auto;
        background: #f8f9fa;
        border-radius: 8px;
        padding: 15px 10px 15px 10px;
        margin-top: 10px;
        border: 1px solid #e9ecef;
        box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .logs-timeline::-webkit-scrollbar {
        width: 6px;
    }

    .logs-timeline::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 3px;
    }

    .logs-timeline::-webkit-scrollbar-thumb {
        background: #fbc02d;
        border-radius: 3px;
        transition: background 0.3s ease;
    }

    .logs-timeline::-webkit-scrollbar-thumb:hover {
        background: #f57c00;
    }

    .logs-timeline::before {
        content: '';
        position: absolute;
        left: 7px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: linear-gradient(to bottom, #1b5e20, #fbc02d);
    }

    .log-item {
        position: relative;
        margin-bottom: 18px;
        padding-left: 25px;
        background: #ffffff;
        border-radius: 8px;
        padding: 14px 14px 14px 25px;
        border: 1px solid #e5e7eb;
        transition: all 0.2s ease;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    .log-item:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        transform: translateY(-1px);
        border-color: #fbc02d;
    }

    .log-indicator {
        position: absolute;
        left: -8px;
        top: 12px;
        width: 16px;
        height: 16px;
        background: #fbc02d;
        border: 3px solid #fff;
        border-radius: 50%;
        box-shadow: 0 0 0 2px #1b5e20, 0 2px 4px rgba(0, 0, 0, 0.1);
        z-index: 2;
        transition: all 0.2s ease;
    }

    .log-item:hover .log-indicator {
        background: #f57c00;
        transform: scale(1.1);
    }

    .log-content {
        background: transparent;
        padding: 0;
        border: none;
        border-radius: 0;
        transition: all 0.2s ease;
    }

    .log-content:hover {
        background: transparent;
        box-shadow: none;
    }

    .log-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
        padding-bottom: 6px;
        border-bottom: 1px solid #e9ecef;
    }

    .log-time {
        font-size: 12px;
        color: #6b7280;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .log-time i {
        color: #1b5e20;
        font-size: 11px;
    }

    .log-description {
        font-size: 14px;
        line-height: 1.6;
        color: #374151;
        word-wrap: break-word;
        overflow-wrap: break-word;
    }

    .log-description strong {
        color: #1b5e20;
        font-weight: 700;
        background: linear-gradient(135deg, #e8f5e9 0%, #f0f9ff 100%);
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 13px;
    }

    /* Remarks Timeline Styling */
    .remarks-timeline {
        position: relative;
        padding-left: 10px;
        max-height: 400px;
        overflow-y: auto;
        background: #f8f9fa;
        border-radius: 8px;
        padding: 15px 10px 15px 10px;
        margin-top: 10px;
        border: 1px solid #e9ecef;
        box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .remarks-timeline::-webkit-scrollbar {
        width: 6px;
    }

    .remarks-timeline::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 3px;
    }

    .remarks-timeline::-webkit-scrollbar-thumb {
        background: #1b5e20;
        border-radius: 3px;
        transition: background 0.3s ease;
    }

    .remarks-timeline::-webkit-scrollbar-thumb:hover {
        background: #2e7d32;
    }

    .remarks-timeline::before {
        content: '';
        position: absolute;
        left: 7px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: linear-gradient(to bottom, #1b5e20, #fbc02d);
    }

    .remark-item {
        position: relative;
        margin-bottom: 20px;
        padding-left: 25px;
        background: #ffffff;
        border-radius: 8px;
        padding: 15px 15px 15px 25px;
        border: 1px solid #e5e7eb;
        transition: all 0.2s ease;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    .remark-item:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        transform: translateY(-1px);
    }

    .remark-item::before {
        content: '';
        position: absolute;
        left: -8px;
        top: 15px;
        width: 16px;
        height: 16px;
        background: #4caf50;
        border: 3px solid #fff;
        border-radius: 50%;
        box-shadow: 0 0 0 2px #1b5e20, 0 2px 4px rgba(0, 0, 0, 0.1);
        z-index: 2;
    }

    .remark-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }

    .remark-author {
        font-size: 13px;
        color: #1b5e20;
        font-weight: 600;
    }

    .remark-author i {
        margin-right: 4px;
        font-size: 12px;
    }

    .remark-date {
        font-size: 12px;
        color: #6b7280;
    }

    .remark-date i {
        margin-right: 4px;
        font-size: 11px;
    }

    .remark-content {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 6px;
        padding: 14px;
        font-size: 14px;
        line-height: 1.6;
        color: #374151;
        margin-top: 8px;
        position: relative;
        word-wrap: break-word;
        overflow-wrap: break-word;
    }

    .remark-content::before {
        content: '\201C';
        position: absolute;
        top: 5px;
        left: 8px;
        font-size: 20px;
        color: #1b5e20;
        opacity: 0.3;
        font-family: serif;
    }

    /* Modal Section Styling */
    .modal-section {
        margin-bottom: 20px;
        background: #ffffff;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        overflow: hidden;
    }

    .modal-section:last-child {
        margin-bottom: 0;
    }

    .modal-section h3 {
        font-size: 18px;
        font-weight: 700;
        color: #1f2937;
        margin: 0 0 15px 0;
        padding-bottom: 10px;
        border-bottom: 3px solid #2d7d32;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .modal-section h3 i {
        font-size: 14px;
        color: #1b5e20;
        width: 16px;
        text-align: center;
        flex-shrink: 0;
    }

    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 12px;
    }

    .info-grid>div {
        padding: 8px 0;
        border-bottom: 1px solid #f3f4f6;
    }

    .info-grid strong {
        color: #1b5e20;
        font-weight: 600;
    }

    /* Loading Content Styling */
    .loading-content {
        text-align: center;
        padding: 40px 20px;
        color: #666;
    }

    .loading-content .spinner {
        display: inline-block;
        width: 30px;
        height: 30px;
        border: 3px solid #f3f3f3;
        border-top: 3px solid #4caf50;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-bottom: 10px;
    }

    .loading-content p {
        margin: 10px 0 0 0;
        font-size: 14px;
    }

    /* Priority badge for update form */
    .priority-badge {
        margin-left: auto;
        background: #f59e0b;
        color: white;
        font-size: 11px;
        font-weight: 600;
        padding: 3px 8px;
        border-radius: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 2px 4px rgba(245, 158, 11, 0.3);
    }

    /* Form icon sizing */
    .form-group label i,
    .section-label i,
    .log-time i,
    .remark-author i,
    .remark-date i {
        font-size: 12px !important;
        width: 14px;
        text-align: center;
    }

    /* Action button icons */
    .submit-btn i,
    .update-btn i,
    .action-btn i {
        font-size: 13px !important;
        margin-right: 6px;
    }

    /* Empty States Styling */
    .empty-remarks-state,
    .empty-logs-state {
        text-align: center;
        padding: 40px 20px;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: 8px;
        border: 2px dashed #cbd5e0;
        margin-top: 15px;
    }

    .empty-remarks-state i,
    .empty-logs-state i {
        display: block;
        margin-bottom: 12px;
        opacity: 0.6;
    }

    .empty-logs-state {
        border-color: #fbc02d;
        background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
    }

    /* Remarks Section Header Enhancement */
    .modal-section h3 {
        font-size: 16px;
        font-weight: 700;
        color: #1f2937;
        margin: 0 0 15px 0;
        padding: 12px 16px;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-left: 4px solid #2d7d32;
        border-radius: 6px;
        display: flex;
        align-items: center;
        gap: 10px;
        position: relative;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    /* Special styling for update form section */
    .modal-section.update-section h3 {
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        border-left-color: #f59e0b;
        color: #92400e;
    }

    .modal-section.update-section h3 i {
        color: #f59e0b;
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
            <a href="Superadmin_dashboard.php"
                class="<?php echo $current_page === 'Superadmin_dashboard.php' ? 'active' : ''; ?>">
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
            <a href="archived_records.php"
                class="<?php echo $current_page === 'archived_records.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-archive"></i> ARCHIVED RECORDS
            </a>
            <a href="add_admin.php" class="<?php echo $current_page === 'add_admin.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-user-shield"></i> ADMIN MANAGEMENT
            </a>
            <a href="history_activity.php"
                class="<?php echo $current_page === 'history_activity.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-clipboard"></i> AUDIT TRAILS
            </a>
            <a href="manage_credit_points.php"
                class="<?php echo $current_page === 'manage_credit_points.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-star"></i> MANAGE CREDIT RATE
            </a>
            <a href="#" onclick="openManageInterestRateModal(); event.stopPropagation(); return false;"
                class="manage-interest-rate-link">
                <i class="fa-solid fa-percent"></i> MANAGE INTEREST RATE
            </a>
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
                    <img src="uploads/<?php echo htmlspecialchars($profile_img); ?>" alt="Profile Image"
                        class="profile">
                </div>
                <div class="dropdown-menu" id="dropdown" role="menu">
                    <ul>
                        <li role="none">
                            <a href="profileSuperadmin.php" role="menuitem" tabindex="-1">
                                <img src="uploads/<?php echo htmlspecialchars($profile_img); ?>" alt="Profile Image"
                                    class="profile-icon">
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
        <?php
        if (isset($_SESSION['success'])) {
            echo '<div class="message success"><i class="fas fa-check-circle"></i>' . htmlspecialchars($_SESSION['success']) . '</div>';
            unset($_SESSION['success']);
        }
        if (isset($_SESSION['error'])) {
            echo '<div class="message error"><i class="fas fa-exclamation-circle"></i>' . htmlspecialchars($_SESSION['error']) . '</div>';
            unset($_SESSION['error']);
        }
        ?>

        <!-- Dashboard Header -->
        <div class="dashboard-header">

            <div class="dashboard-left">

                <div class="dashboard-admin">
                    <h1>Superadmin Dashboard</h1>
                    <p class="dashboard-subtitle">Manage loan applications, interest rates, and system administration.
                    </p>
                </div>

                <div class="kpi-grid">

                    <div class="kpi-card">
                        <div class="kpi-icon kpi-approved">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="kpi-content">
                            <p class="kpi-value"><?php echo htmlspecialchars($activeCount); ?></p>
                            <h4>Active Accounts</h4>
                        </div>
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-icon kpi-outstanding">
                            <i class="fas fa-spinner"></i>
                        </div>
                        <div class="kpi-content">
                            <p class="kpi-value"><?php echo htmlspecialchars($pendingCount); ?></p>
                            <h4>Pending Accounts</h4>
                        </div>
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-icon kpi-risk">
                            <i class="fas fa-archive"></i>
                        </div>
                        <div class="kpi-content">
                            <p class="kpi-value"><?php echo htmlspecialchars($closedCount); ?></p>
                            <h4>Closed Accounts</h4>
                        </div>
                    </div>
                </div>

            </div>

            <div class="dashboard-right">
                <div class="graph-box">
                    <h3>Loan Applications by Type</h3>
                    <div class="chart-container">
                        <canvas id="loanTypeChart"></canvas>
                    </div>
                </div>

                <div class="loan_applicants">
                    <div class="section-header">
                        <div class="due-accounts-header">
                            <h2>Due Accounts</h2>
                            <p class="section-subtitle">Upcoming payments within 30 days</p>
                        </div>
                    </div>

                    <div id="dueAccountsContainer">
                        <?php if (empty($dueAccountsData)): ?>
                            <div class="empty-state">
                                <i class="fa-solid fa-circle-check"></i>
                                <p>No due accounts found. All payments are on track!</p>
                            </div>
                        <?php else: ?>
                            <div class="scrollable-table">
                                <table class="due-accounts-table">
                                    <thead>
                                        <tr>
                                            <th>Due Date</th>
                                            <th>Account Holder</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($dueAccountsData as $due):
                                            $dueDate = new DateTime($due['due_date']);
                                            $today = new DateTime();
                                            $today->setTime(0, 0, 0);
                                            $dueDate->setTime(0, 0, 0);
                                            $interval = $today->diff($dueDate);
                                            $daysUntilDue = (int) $interval->format('%r%a');

                                            $urgencyClass = 'normal';
                                            $urgencyBadge = '';

                                            if ($daysUntilDue <= 0) {
                                                $urgencyClass = 'urgent';
                                                $urgencyBadge = '<span class="urgency-badge urgent"><i class="fas fa-exclamation-circle"></i> Overdue</span>';
                                            } elseif ($daysUntilDue <= 7) {
                                                $urgencyClass = 'urgent';
                                                $urgencyBadge = '<span class="urgency-badge urgent"><i class="fas fa-exclamation"></i> Urgent</span>';
                                            } elseif ($daysUntilDue <= 15) {
                                                $urgencyClass = 'warning';
                                                $urgencyBadge = '<span class="urgency-badge warning"><i class="fas fa-clock"></i> Due Soon</span>';
                                            }
                                            ?>
                                            <tr class="due-row <?php echo $urgencyClass; ?>">
                                                <td data-label="Due Date">
                                                    <span
                                                        class="date-badge"><?php echo date('M j, Y', strtotime($due['due_date'])); ?></span>
                                                </td>
                                                <td data-label="Account Holder">
                                                    <div class="account-holder-info">
                                                        <strong><?php echo htmlspecialchars($due['first_name'] . ' ' . $due['last_name']); ?></strong>
                                                        <span class="account-id">ID:
                                                            <?php echo htmlspecialchars($due['application_id']); ?></span>
                                                    </div>
                                                </td>
                                                <td data-label="Amount">
                                                    <span
                                                        class="amount-badge">₱<?php echo number_format($due['amount'], 2); ?></span>
                                                </td>
                                                <td data-label="Status">
                                                    <?php echo $urgencyBadge; ?>
                                                </td>
                                                <td data-label="Actions">
                                                    <div class="action-buttons">
                                                        <button class="action-btn view-btn"
                                                            onclick="openLoanDetailsModal(<?php echo $due['application_id']; ?>); event.stopPropagation(); return false;"
                                                            title="View Loan Details">
                                                            <i class="fas fa-eye"></i> View
                                                        </button>
                                                        <button class="action-btn reminder-btn"
                                                            onclick="sendPaymentReminder(<?php echo $due['payment_id']; ?>)"
                                                            title="Send Payment Reminder">
                                                            <i class="fas fa-bell"></i> Remind
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>


        <div class="loan_applicants">
            <div class="section-header">
                <div>
                    <h2>Loan Applicants</h2>
                    <p class="section-subtitle">Review and manage loan applications</p>
                </div>
                <div class="section-actions">
                    <button class="export-btn" onclick="exportToCSV()" title="Export to CSV">
                        <i class="fas fa-download"></i> Export
                    </button>
                </div>
            </div>
            <div class="filter-container">
                <div class="filter-group search-group">
                    <label for="filter-name">Search Applicant:</label>
                    <div class="search-wrapper">
                        <input type="text" id="filter-name" placeholder="🔍︎ Search by name..."
                            oninput="filterLoanApplicantsTable()">
                        <i class="fas fa-times-circle clear-search" onclick="clearSearch()" style="display: none;"></i>
                    </div>
                </div>
                <div class="filter-group">
                    <label for="filter-loan-type">Loan Type:</label>
                    <select id="filter-loan-type" onchange="filterLoanApplicantsTable()">
                        <option value="">All Types</option>
                        <option value="Individual">Individual</option>
                        <option value="Cooperative">Cooperative</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter-loan-status">Loan Status:</label>
                    <select id="filter-loan-status" onchange="filterLoanApplicantsTable()">
                        <option value="">All Statuses</option>
                        <option value="Active">Active</option>
                        <option value="Pending">Pending</option>
                        <option value="New">New</option>
                        <option value="Completed">Completed</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="filter-pre-approval">Pre-Approval Status:</label>
                    <select id="filter-pre-approval" onchange="filterLoanApplicantsTable()">
                        <option value="">All</option>
                        <option value="Pending">Pending</option>
                        <option value="Approved">Approved</option>
                        <option value="Rejected">Rejected</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="filter-credit-status">Credit Status:</label>
                    <select id="filter-credit-status" onchange="filterLoanApplicantsTable()">
                        <option value="">All</option>
                        <option value="Pending">Pending</option>
                        <option value="Completed">Completed</option>
                        <option value="Failed">Failed</option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button class="clear-filters-btn" onclick="clearAllFilters()">
                        <i class="fas fa-times"></i> Clear All Filters
                    </button>
                </div>
            </div>
            <div class="scrollable-table">
                <?php if (empty($loanApplicants)): ?>
                    <p class="no-records">No loan applicants found.</p>
                <?php else: ?>
                    <table class="loan-table" role="grid" aria-describedby="loan-applicants-info">
                        <thead>
                            <tr>
                                <th scope="col" data-sort="name" onclick="sortLoanApplicantsTable('name')">Applicant Name
                                    <span class="sort-icon"></span>
                                </th>
                                <th scope="col" data-sort="loan_type" onclick="sortLoanApplicantsTable('loan_type')">Loan
                                    Type <span class="sort-icon"></span></th>
                                <th scope="col" data-sort="amount_applied"
                                    onclick="sortLoanApplicantsTable('amount_applied')">Amount Applied <span
                                        class="sort-icon"></span></th>
                                <th scope="col" data-sort="final_loan_amount"
                                    onclick="sortLoanApplicantsTable('final_loan_amount')">Final Loan Amount <span
                                        class="sort-icon"></span></th>
                                <th scope="col" data-sort="status" onclick="sortLoanApplicantsTable('status')">Loan Status
                                    <span class="sort-icon"></span>
                                </th>
                                <th scope="col" data-sort="pre_approval_status"
                                    onclick="sortLoanApplicantsTable('pre_approval_status')">Pre-Approval Status <span
                                        class="sort-icon"></span></th>
                                <th scope="col" data-sort="credit_investigation_status"
                                    onclick="sortLoanApplicantsTable('credit_investigation_status')">Credit Investigation
                                    Status <span class="sort-icon"></span></th>
                                <th scope="col" data-sort="created_at" onclick="sortLoanApplicantsTable('created_at')">
                                    Submission Date <span class="sort-icon"></span></th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody id="loanApplicantsTableBody">
                            <?php foreach ($loanApplicants as $applicant): ?>
                                <tr>
                                    <td data-label="Applicant Name">
                                        <?php echo htmlspecialchars($applicant['first_name'] . ' ' . $applicant['last_name']); ?>
                                    </td>
                                    <td data-label="Loan Type"><?php echo htmlspecialchars($applicant['type_name']); ?></td>
                                    <td data-label="Amount Applied" class="amount-cell">
                                        <span
                                            class="amount-value">₱<?php echo number_format($applicant['amount_applied'], 2); ?></span>
                                    </td>
                                    <td data-label="Final Loan Amount" class="amount-cell">
                                        <?php if ($applicant['final_loan_amount']): ?>
                                            <span
                                                class="amount-value">₱<?php echo number_format($applicant['final_loan_amount'], 2); ?></span>
                                        <?php else: ?>
                                            <span class="amount-not-set">Not set</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Loan Status">
                                        <span
                                            class="status-badge badge-<?php echo strtolower($applicant['status']); ?>"><?php echo htmlspecialchars($applicant['status']); ?></span>
                                    </td>
                                    <td data-label="Pre-Approval Status">
                                        <span
                                            class="status-badge badge-<?php echo strtolower($applicant['pre_approval_status']); ?>"><?php echo htmlspecialchars($applicant['pre_approval_status']); ?></span>
                                    </td>
                                    <td data-label="Credit Investigation Status">
                                        <span
                                            class="status-badge badge-<?php echo strtolower($applicant['credit_investigation_status']); ?>"><?php echo htmlspecialchars($applicant['credit_investigation_status']); ?></span>
                                    </td>
                                    <td data-label="Submission Date">
                                        <?php echo date('Y-m-d', strtotime($applicant['created_at'])); ?>
                                    </td>
                                    <td data-label="Action">
                                        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                            <a href="#" class="view-btn"
                                                onclick="openLoanDetailsModal('<?php echo htmlspecialchars($applicant['application_id']); ?>'); return false;"
                                                style="display: inline-block; padding: 6px 12px; background-color: #3b82f6; color: white; border-radius: 4px; text-decoration: none; font-size: 12px;">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="activity_logs">
            <div class="section-header">
                <div>
                    <h2>Recent Audith Logs</h2>
                    <p class="section-subtitle">Track system activities and changes</p>
                </div>
            </div>
            <div class="scrollable-table">
                <?php if (empty($activityLogs)): ?>
                    <p class="no-records">No activity logs found.</p>
                <?php else: ?>
                    <table class="activity-logs-table" role="grid" aria-describedby="activity-logs-info">
                        <thead>
                            <tr>
                                <th scope="col">Date & Time</th>
                                <th scope="col">User</th>
                                <th scope="col">Role</th>
                                <th scope="col">Action</th>
                                <th scope="col">Module</th>
                                <th scope="col">Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activityLogs as $log): ?>
                                <tr>
                                    <td data-label="Date & Time">
                                        <?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?>
                                    </td>
                                    <td data-label="User">
                                        <?php echo htmlspecialchars(($log['first_name'] && $log['last_name']) ? $log['first_name'] . ' ' . $log['last_name'] : ($log['first_name'] ?: 'Unknown')); ?>
                                    </td>
                                    <td data-label="Role">
                                        <span class="status-badge badge-<?php echo strtolower($log['user_role']); ?>">
                                            <?php echo htmlspecialchars($log['user_role']); ?>
                                        </span>
                                    </td>
                                    <td data-label="Action">
                                        <span class="status-badge badge-<?php echo strtolower($log['action_type']); ?>">
                                            <?php echo htmlspecialchars(isset($actionTypeMap[$log['action_type']]) ? $actionTypeMap[$log['action_type']] : $log['action_type']); ?>
                                        </span>
                                    </td>
                                    <td data-label="Module">
                                        <?php echo htmlspecialchars(isset($moduleMap[$log['module']]) ? $moduleMap[$log['module']] : $log['module']); ?>
                                    </td>
                                    <td data-label="Description"><?php echo htmlspecialchars($log['description']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Toast Notification Container -->
        <div class="toast-container" id="toastContainer"></div>

        <!-- Confirmation Modal -->
        <div id="confirmationModal" class="confirmation-modal">
            <div class="confirmation-content">
                <i class="fas fa-exclamation-triangle confirmation-icon"></i>
                <div class="confirmation-title" id="confirmationTitle">Confirm Action</div>
                <div class="confirmation-message" id="confirmationMessage">Are you sure you want to proceed?</div>
                <div class="confirmation-buttons">
                    <button class="btn-cancel" id="confirmationCancel">Cancel</button>
                    <button class="btn-confirm" id="confirmationConfirm">Confirm</button>
                </div>
            </div>
        </div>

        <div id="loanDetailsModal" class="modal" role="dialog" aria-labelledby="loanDetailsModalLabel">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="header-content">
                        <div class="header-text">
                            <h2 id="loanDetailsModalLabel">Loan Application Details</h2>
                        </div>
                    </div>
                    <span class="close" onclick="closeLoanDetailsModal()" role="button"
                        aria-label="Close modal">×</span>
                </div>

                <div class="modal-body">
                    <div id="loanDetailsContent" class="loan-details">
                        <div class="loading-content">
                            <div class="spinner"></div>
                            <p>Loading loan details...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>



        <div id="manageInterestRateModal" class="modal" role="dialog" aria-labelledby="manageInterestRateModalLabel">
            <div class="modal-content" style="max-height: 90vh; display: flex; flex-direction: column;">
                <!-- Modal Header -->
                <div class="modal-header"
                    style="background: linear-gradient(135deg, #1b5e20 0%, #2d7d32 100%); color: white; padding: 20px; border-radius: 8px 8px 0 0; flex-shrink: 0;">
                    <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <i class="fas fa-percentage" style="font-size: 28px; color: #fbc02d;"></i>
                            <div>
                                <h2 id="manageInterestRateModalLabel"
                                    style="margin: 0; font-size: 24px; font-weight: 700; color: white;">Interest Rate
                                    Management</h2>
                                <p style="margin: 4px 0 0 0; font-size: 13px; opacity: 0.9;">View current loan interest
                                    rates</p>
                            </div>
                        </div>
                        <span class="close" id="closeInterestRateModal" role="button" aria-label="Close modal"
                            onclick="closeManageInterestRateModal()"
                            style="font-size: 32px; cursor: pointer; color: white; opacity: 0.8; transition: opacity 0.2s;"
                            onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.8'">×</span>
                    </div>
                </div>

                <!-- Modal Body with Scrolling -->
                <div class="modal-body" style="flex: 1; overflow-y: auto; padding: 20px; background: #f9f9f9;">
                    <!-- Quick Stats Cards -->
                    <div
                        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 25px;">
                        <!-- Configured Terms Card -->
                        <div
                            style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.08); border-left: 4px solid #2d7d32; display: flex; align-items: center; gap: 15px;">
                            <div
                                style="background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%); color: white; width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div>
                                <p
                                    style="margin: 0; font-size: 12px; color: #666; font-weight: 600; text-transform: uppercase;">
                                    Configured Terms</p>
                                <p style="margin: 5px 0 0 0; font-size: 24px; font-weight: 700; color: #1b5e20;"
                                    id="configuredTermsCount">-</p>
                            </div>
                        </div>

                        <!-- Average Rate Card -->
                        <div
                            style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.08); border-left: 4px solid #fbc02d; display: flex; align-items: center; gap: 15px;">
                            <div
                                style="background: linear-gradient(135deg, #fbc02d 0%, #f57c00 100%); color: white; width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div>
                                <p
                                    style="margin: 0; font-size: 12px; color: #666; font-weight: 600; text-transform: uppercase;">
                                    Average Rate</p>
                                <p style="margin: 5px 0 0 0; font-size: 24px; font-weight: 700; color: #f57c00;"
                                    id="averageRateValue">-</p>
                            </div>
                        </div>

                        <!-- Last Updated Card -->
                        <div
                            style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.08); border-left: 4px solid #2196F3; display: flex; align-items: center; gap: 15px;">
                            <div
                                style="background: linear-gradient(135deg, #1976D2 0%, #2196F3 100%); color: white; width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                                <i class="fas fa-history"></i>
                            </div>
                            <div>
                                <p
                                    style="margin: 0; font-size: 12px; color: #666; font-weight: 600; text-transform: uppercase;">
                                    Last Updated</p>
                                <p style="margin: 5px 0 0 0; font-size: 18px; font-weight: 700; color: #1976D2;"
                                    id="lastUpdatedValue">-</p>
                            </div>
                        </div>
                    </div>

                    <!-- Current Interest Rates Section -->
                    <div
                        style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.08); margin-bottom: 25px;">
                        <h3
                            style="margin: 0 0 15px 0; color: #1b5e20; font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-table"></i> Current Rates by Term
                        </h3>
                        <div id="ratesGrid"
                            style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px;">
                            <div style="padding: 20px; text-align: center; color: #999;">
                                <i class="fas fa-spinner fa-spin"
                                    style="font-size: 20px; margin-bottom: 8px; display: block;"></i>
                                <p style="margin: 0; font-size: 11px;">Loading rates...</p>
                            </div>
                        </div>
                    </div>

                    <!-- Update Form (Initially Hidden) -->
                    <div class="interest-rate-card" id="updateFormSection" style="display: none;">
                        <div class="card-header-flex">
                            <h3><i class="fas fa-edit"></i> Update Interest Rate</h3>
                            <button class="close-form-btn" onclick="hideUpdateForm()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <form id="interestRateForm">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="termLength">
                                        <i class="fas fa-calendar-alt"></i> Term Length
                                        <span style="color: red;">*</span>
                                    </label>
                                    <select id="termLength" name="termLength" required>
                                        <option value="">Select Term Length</option>
                                        <option value="6">6 Months</option>
                                        <option value="12">12 Months</option>
                                        <option value="18">18 Months</option>
                                        <option value="24">24 Months</option>
                                        <option value="36">36 Months</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="newInterestRate">
                                        <i class="fas fa-percent"></i> Interest Rate (% per annum)
                                        <span style="color: red;">*</span>
                                    </label>
                                    <input type="number" id="newInterestRate" required placeholder="e.g., 6.50"
                                        min="0.01" max="100" step="0.01">
                                </div>
                            </div>
                            <div class="form-actions">
                                <button type="button" class="btn-cancel" onclick="hideUpdateForm()">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                                <button type="button" class="btn-primary" onclick="updateInterestRate()">
                                    <i class="fas fa-save"></i> Update Interest Rate
                                </button>
                            </div>
                        </form>
                        <div id="interestRateMessage" class="result"></div>
                    </div>

                    <!-- Interest Rate History -->
                    <div style="margin-top: 30px;">
                        <h3
                            style="color: #1b5e20; font-size: 18px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-history"></i> Change History
                        </h3>
                        <div id="historyLoading" style="display: none; text-align: center; padding: 20px;">
                            <div
                                style="display: inline-block; width: 30px; height: 30px; border: 3px solid #f3f3f3; border-top: 3px solid #4caf50; border-radius: 50%; animation: spin 1s linear infinite;">
                            </div>
                        </div>
                        <div
                            style="background: white; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); overflow: hidden;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr
                                        style="background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%); color: white;">
                                        <th
                                            style="padding: 15px; text-align: left; font-weight: 600; border-right: 1px solid rgba(255,255,255,0.1);">
                                            #</th>
                                        <th
                                            style="padding: 15px; text-align: left; font-weight: 600; border-right: 1px solid rgba(255,255,255,0.1);">
                                            Term Length</th>
                                        <th
                                            style="padding: 15px; text-align: left; font-weight: 600; border-right: 1px solid rgba(255,255,255,0.1);">
                                            Interest Rate</th>
                                        <th
                                            style="padding: 15px; text-align: left; font-weight: 600; border-right: 1px solid rgba(255,255,255,0.1);">
                                            Updated At</th>
                                        <th style="padding: 15px; text-align: left; font-weight: 600;">Updated By</th>
                                    </tr>
                                </thead>
                                <tbody id="interestRateHistoryTable">
                                    <tr>
                                        <td colspan="5" style="padding: 40px; text-align: center;">
                                            <div
                                                style="display: inline-block; width: 30px; height: 30px; border: 3px solid #f3f3f3; border-top: 3px solid #4caf50; border-radius: 50%; animation: spin 1s linear infinite;">
                                            </div>
                                            <p style="margin-top: 10px; color: #666;">Loading history...</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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

        // 🧠 Helper to show "no data" image
        function showNoDataImage(containerId, imagePath, altText) {
            const container = document.getElementById(containerId).parentElement;
            container.innerHTML = `
                <div class="no-data-image">
                    <img src="${imagePath}" alt="${altText}">
                    <p>${altText}</p>
                </div>
            `;
        }

        // 🎨 Loan Type Chart
        const loanTypeData = <?php echo json_encode($loanTypeData); ?>;

        if (!loanTypeData || loanTypeData.length === 0) {
            // No data → show fallback SVG
            showNoDataImage('loanTypeChart', 'IMAGE/empty svg.svg', 'No Loan Data Available');
        } else {
            // Render chart with fixed order: Individual first, Cooperative second
            const loanTypeLabels = ['Individual', 'Cooperative'];
            const loanTypeCounts = loanTypeLabels.map(label => {
                const dataPoint = loanTypeData.find(item => item.type_name === label);
                return dataPoint ? parseInt(dataPoint.count) : 0;
            });

            const loanTypeCtx = document.getElementById('loanTypeChart').getContext('2d');

            // Create gradients for bars
            const gradient1 = loanTypeCtx.createLinearGradient(0, 0, 0, 400);
            gradient1.addColorStop(0, '#1b5e20');
            gradient1.addColorStop(1, '#4caf50');

            const gradient2 = loanTypeCtx.createLinearGradient(0, 0, 0, 400);
            gradient2.addColorStop(0, '#f57c00');
            gradient2.addColorStop(1, '#ffd54f');

            new Chart(loanTypeCtx, {
                type: 'bar',
                data: {
                    labels: loanTypeLabels,
                    datasets: [{
                        label: 'Number of Applications',
                        data: loanTypeCounts,
                        backgroundColor: [gradient1, gradient2],
                        borderColor: ['#1b5e20', '#f57c00'],
                        borderWidth: 2,
                        borderRadius: 8,
                        hoverBackgroundColor: ['#2e7d32', '#fb8c00'],
                        hoverBorderColor: ['#1b5e20', '#f57c00'],
                        hoverBorderWidth: 3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    aspectRatio: 2,
                    animation: {
                        duration: 1500,
                        easing: 'easeInOutQuart',
                        onComplete: function () {
                            // Add pulse animation class when done
                            document.querySelector('.chart-container').classList.add('loaded');
                        }
                    },
                    plugins: {
                        legend: {
                            display: false,
                            position: 'top',
                            labels: {
                                font: {
                                    size: 13,
                                    family: 'Poppins, sans-serif',
                                    weight: '500'
                                },
                                padding: 15,
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        title: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.85)',
                            titleFont: {
                                size: 14,
                                family: 'Poppins, sans-serif',
                                weight: '600'
                            },
                            bodyFont: {
                                size: 13,
                                family: 'Poppins, sans-serif'
                            },
                            padding: 12,
                            cornerRadius: 8,
                            displayColors: true,
                            callbacks: {
                                label: function (context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    label += context.parsed.y;
                                    label += context.parsed.y === 1 ? ' application' : ' applications';
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Applications',
                                font: {
                                    size: 13,
                                    family: 'Poppins, sans-serif',
                                    weight: '600'
                                },
                                padding: { top: 10, bottom: 10 }
                            },
                            ticks: {
                                stepSize: 1,
                                font: {
                                    size: 12,
                                    family: 'Poppins, sans-serif'
                                }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)',
                                drawBorder: false
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Loan Type',
                                font: {
                                    size: 13,
                                    family: 'Poppins, sans-serif',
                                    weight: '600'
                                },
                                padding: { top: 10, bottom: 10 }
                            },
                            ticks: {
                                font: {
                                    size: 12,
                                    family: 'Poppins, sans-serif'
                                }
                            },
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }


        // Handle modal-based active state
        document.addEventListener('DOMContentLoaded', function () {
            const manageInterestLink = document.querySelector('.manage-interest-rate-link');
            const modal = document.getElementById('manageInterestRateModal');

            manageInterestLink.addEventListener('click', function () {
                document.querySelectorAll('nav a').forEach(link => link.classList.remove('active'));
                this.classList.add('active');
            });

            modal.querySelector('.close').addEventListener('click', function () {
                manageInterestLink.classList.remove('active');
                document.querySelector(`nav a[href="Superadmin_dashboard.php"]`).classList.add('active');
            });
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

        // ====== AUTO-POLLING FOR REAL-TIME UPDATES ======
        const PollingManager = {
            pollingIntervals: {},
            lastDataHash: {},
            notificationQueue: [],
            isPollingEnabled: true,
            isPaused: false,
            pollInterval: 30000,

            initPoll: function (dataSource, fetchUrl, updateCallback, pollInterval) {
                if (!this.isPollingEnabled) return;
                if (!pollInterval) pollInterval = this.pollInterval;

                this.fetchData(dataSource, fetchUrl, updateCallback);

                this.pollingIntervals[dataSource] = setInterval(() => {
                    if (!this.isPaused) {
                        this.fetchData(dataSource, fetchUrl, updateCallback);
                    }
                }, pollInterval);

                console.log(`✓ Auto-polling enabled for: ${dataSource} (${pollInterval}ms)`);
            },

            fetchData: function (dataSource, fetchUrl, updateCallback) {
                fetch(fetchUrl)
                    .then(response => {
                        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                        return response.json();
                    })
                    .then(data => {
                        if (data.data && Array.isArray(data.data)) {
                            const currentHash = this.hashData(data.data);
                            if (this.lastDataHash[dataSource] !== currentHash) {
                                this.lastDataHash[dataSource] = currentHash;
                                updateCallback(data.data);
                                this.sendPushNotification(`${dataSource} Updated`,
                                    `Real-time data synchronized at ${new Date().toLocaleTimeString()}`);
                            }
                        }
                    })
                    .catch(error => console.error(`Polling error for ${dataSource}:`, error));
            },

            pausePolling: function () {
                this.isPaused = true;
                console.log('⏸️ Polling paused during modal load');
            },

            resumePolling: function () {
                this.isPaused = false;
                console.log('▶️ Polling resumed');
            },

            hashData: function (data) {
                if (!data) return '';
                try {
                    const encoder = new TextEncoder();
                    let hash = 5381;
                    const dataStr = JSON.stringify(data);
                    for (let i = 0; i < dataStr.length; i++) {
                        hash = ((hash << 5) + hash) + dataStr.charCodeAt(i);
                        hash = hash & 0xFFFFFFFF;
                    }
                    return Math.abs(hash).toString(36);
                } catch (e) {
                    return JSON.stringify(data).length.toString();
                }
            },

            sendPushNotification: function (title, message) {
                if ('Notification' in window && Notification.permission === 'granted') {
                    new Notification(title, {
                        body: message,
                        icon: 'IMAGE/Main-Logo.png'
                    });
                }
                this.showInAppNotification(title, message);
            },

            showInAppNotification: function (title, message) {
                const toastContainer = document.getElementById('toastContainer');
                if (!toastContainer) return;

                const toast = document.createElement('div');
                toast.className = 'real-time-toast';
                toast.innerHTML = `
                    <div class="toast-content">
                        <strong class="toast-title">${title}</strong>
                        <p class="toast-text">${message}</p>
                        <button class="toast-close" aria-label="Close notification">&times;</button>
                    </div>
                `;

                toastContainer.appendChild(toast);

                const closeBtn = toast.querySelector('.toast-close');
                const dismissToast = () => {
                    toast.style.opacity = '0';
                    setTimeout(() => toast.remove(), 300);
                };
                closeBtn.onclick = dismissToast;

                setTimeout(dismissToast, 5000);
            },

            stopPoll: function (dataSource) {
                if (this.pollingIntervals[dataSource]) {
                    clearInterval(this.pollingIntervals[dataSource]);
                    delete this.pollingIntervals[dataSource];
                    console.log(`Stopped polling for: ${dataSource}`);
                }
            },

            stopAllPolling: function () {
                Object.keys(this.pollingIntervals).forEach(dataSource => {
                    this.stopPoll(dataSource);
                });
                this.isPollingEnabled = false;
                console.log('All polling stopped');
            }
        };

        // Update Functions for Real-Time Table Synchronization
        function updateLoanApplicantsTable(data) {
            const tbody = document.getElementById('loanApplicantsTableBody');
            if (!tbody) return;

            let html = '';
            data.forEach(app => {
                const statusBadge = app.status === 'approved' ? 'bg-success' :
                    app.status === 'rejected' ? 'bg-danger' :
                        app.status === 'pending' ? 'bg-warning' : 'bg-secondary';

                const loanAmount = parseFloat(app.loan_amount || 0);

                html += `
                    <tr>
                        <td>${app.app_id || '-'}</td>
                        <td>${app.borrower_name || 'N/A'}</td>
                        <td>₱${loanAmount.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</td>
                        <td>
                            <span class="badge ${statusBadge}">${app.status || 'Unknown'}</span>
                        </td>
                        <td><small>${new Date(app.created_at).toLocaleDateString('en-PH')}</small></td>
                        <td><small>${app.description || 'Pending'}</small></td>
                    </tr>
                `;
            });

            tbody.innerHTML = html || '<tr><td colspan="6" class="text-center">No applications</td></tr>';
        }

        // Initialize Auto-Polling on Page Load
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof PollingManager === 'undefined') {
                console.error('PollingManager not found!');
                return;
            }

            // Request notification permission
            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission();
            }

            // Create toast container if it doesn't exist
            if (!document.getElementById('toastContainer')) {
                const container = document.createElement('div');
                container.id = 'toastContainer';
                container.style.cssText = 'position: fixed; bottom: 20px; right: 20px; z-index: 9999; max-width: 400px;';
                document.body.appendChild(container);
            }

            // Wait briefly to ensure DOM is ready
            setTimeout(() => {
                try {
                    // Initialize auto-polling for Loan Applicants
                    if (document.getElementById('loanApplicantsTableBody')) {
                        PollingManager.initPoll(
                            'LoanApplicants',
                            'Superadmin_dashboard.php?action=get_loan_applicants',
                            updateLoanApplicantsTable,
                            8000
                        );
                    }

                    console.log('🔄 Real-time synchronization started (Auto-Polling) - 8 second intervals');

                } catch (error) {
                    console.error('Error initializing auto-polling:', error);
                }
            }, 1000);
        });

        let downloadLink = null;

        // ─────────────────── MAIN EXPORT FUNCTION ───────────────────
        function exportToCSV() {
            const table = document.querySelector(".loan-table");
            if (!table || table.querySelectorAll("tbody tr").length === 0) {
                alert("No data available to export.");
                return;
            }

            showExportModal('preparing'); // Show "Preparing Export..." modal

            setTimeout(() => {
                try {
                    // Build CSV (your original code - unchanged)
                    const headers = Array.from(table.querySelectorAll("thead th"))
                        .map(th => th.innerText.trim())
                        .filter(h => h !== "");

                    const rows = table.querySelectorAll("tbody tr");
                    const data = Array.from(rows).map(row =>
                        Array.from(row.cells)
                            .map(cell => cell.innerText.trim())
                            .slice(0, headers.length)
                    );

                    const csvLines = [headers, ...data].map(row =>
                        row.map(cell => `"${(cell + '').replace(/"/g, '""')}"`).join(',')
                    ).join('\r\n');

                    const blob = new Blob(['\uFEFF' + csvLines], { type: 'text/csv;charset=utf-8;' });
                    const url = URL.createObjectURL(blob);
                    downloadLink = url;

                    const filename = `Loan_Records_${new Date().toISOString().slice(0, 10)}.csv`;

                    // Trigger download
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = filename;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);

                    // SUCCESS: Close modal + show beautiful toast
                    closeExportModal();
                    showSuccessToast(filename);

                    // Clean up blob after 1 minute
                    setTimeout(() => URL.revokeObjectURL(url), 60000);

                } catch (err) {
                    console.error(err);
                    showExportModal('failed'); // Show failed modal
                }
            }, 800);
        }

        // ─────────────────── MODAL CONTROL (unchanged) ───────────────────
        function showExportModal(step) {
            const modal = document.getElementById("exportModal");
            const preparing = document.getElementById("preparingStep");
            const success = document.getElementById("successStep");
            const failed = document.getElementById("failedStep");

            preparing.style.display = 'none';
            success.style.display = 'none';
            failed.style.display = 'none';

            if (step === 'preparing') preparing.style.display = 'block';
            if (step === 'success') success.style.display = 'block';
            if (step === 'failed') failed.style.display = 'block';

            if (step === 'preparing') {
                const bar = document.querySelector(".progress-fill");
                bar.style.width = "0%";
                setTimeout(() => bar.style.width = "90%", 100);
            }

            modal.style.display = "flex";
        }

        function closeExportModal() {
            document.getElementById("exportModal").style.display = "none";
            setTimeout(() => {
                document.getElementById("preparingStep").style.display = "block";
                document.getElementById("successStep").style.display = "none";
                document.getElementById("failedStep").style.display = "none";
            }, 300);
        }

        function retryExport() {
            closeExportModal();
            setTimeout(exportToCSV, 200);
        }

        function openDownloadedFile() {
            if (downloadLink) window.open(downloadLink, '_blank');
            hideSuccessToast(); // Close toast when opening file
        }

        // ─────────────────── TOAST NOTIFICATION (NEW & BEAUTIFUL) ───────────────────
        function showSuccessToast(filename) {
            const toast = document.getElementById("successToast");
            document.getElementById("toastFilename").textContent = filename + " is ready.";

            toast.style.display = "flex";
            toast.style.opacity = "0";
            toast.style.transform = "translateX(100%)";

            // Trigger reflow + animate in
            toast.offsetHeight;
            toast.style.transition = "all 0.4s ease-out";
            toast.style.opacity = "1";
            toast.style.transform = "translateX(0)";

            // Auto hide after 5 seconds
            setTimeout(hideSuccessToast, 5000);
        }

        function hideSuccessToast() {
            const toast = document.getElementById("successToast");
            toast.style.opacity = "0";
            toast.style.transform = "translateX(120%)";

            setTimeout(() => {
                toast.style.display = "none";
            }, 400);
        }

        // ========== MISSING MODAL FUNCTIONS ==========

        // Interest Rate Modal Functions
        function openManageInterestRateModal() {
            const modal = document.getElementById('manageInterestRateModal');
            if (modal) {
                modal.style.display = 'flex';
                setTimeout(() => modal.classList.add('show'), 10);
                loadInterestRateData();
                console.log('Interest Rate Modal opened');
            }
        }

        function openInterestRateModal() {
            openManageInterestRateModal();
        }

        function closeManageInterestRateModal() {
            const modal = document.getElementById('manageInterestRateModal');
            if (modal) {
                modal.classList.remove('show');
                setTimeout(() => (modal.style.display = "none"), 300);
                console.log('Interest Rate Modal closed');
            }
        }

        function closeInterestRateModal() {
            closeManageInterestRateModal();
        }

        // Load Interest Rate Data
        function loadInterestRateData() {
            console.log('Loading interest rate data...');
            fetch('interest_rate_api.php?action=get_all_interest_rates')
                .then(response => {
                    console.log('API Response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('API Response data:', data);
                    if (data.success && data.rates) {
                        displayInterestRateCards(data.rates);
                        updateInterestRateStats(data.rates);
                        loadInterestRateHistory();
                    } else {
                        console.error('Failed to load interest rate data:', data.message || 'Unknown error');
                    }
                })
                .catch(error => {
                    console.error('Error loading interest rate data:', error);
                });
        }

        // Display Interest Rate Cards
        function displayInterestRateCards(rates) {
            const container = document.getElementById('ratesGrid');
            if (!container) return;

            if (!rates || rates.length === 0) {
                container.innerHTML = '<p style="text-align: center; color: #666;">No interest rates configured.</p>';
                return;
            }

            container.innerHTML = rates.map(rate => `
        <div class="rate-card">
            <div class="rate-term">${rate.term_length} Months</div>
            <div class="rate-value">${parseFloat(rate.interest_rate).toFixed(2)}%</div>
            <div class="rate-updated">Updated: ${new Date(rate.updated_at).toLocaleDateString()}</div>
        </div>
    `).join('');
        }

        // Update Interest Rate Statistics
        function updateInterestRateStats(rates) {
            // Calculate stats
            const configuredTerms = rates.length;
            const totalTerms = 5; // 6, 12, 18, 24, 36 months

            let avgRate = 0;
            if (rates.length > 0) {
                const sum = rates.reduce(
                    (acc, item) => acc + parseFloat(item.interest_rate),
                    0
                );
                avgRate = (sum / rates.length).toFixed(2);
            }

            let lastUpdated = "Never";
            if (rates.length > 0) {
                const dates = rates.map((item) => new Date(item.updated_at));
                const mostRecent = new Date(Math.max(...dates));
                lastUpdated = mostRecent.toLocaleDateString("en-US", {
                    month: "short",
                    day: "numeric",
                    year: "numeric",
                });
            }

            // Update stat displays
            const configElement = document.getElementById("configuredTermsCount");
            const avgRateElement = document.getElementById("averageRateValue");
            const lastUpdatedElement = document.getElementById("lastUpdatedValue");

            if (configElement)
                configElement.textContent = `${configuredTerms}/${totalTerms}`;
            if (avgRateElement) avgRateElement.textContent = avgRate + "%";
            if (lastUpdatedElement) lastUpdatedElement.textContent = lastUpdated;
        }

        // Load Interest Rate History
        function loadInterestRateHistory() {
            const historyTable = document.getElementById('interestRateHistoryTable');
            if (!historyTable) return;

            const historyLoading = document.getElementById('historyLoading');
            if (historyLoading) historyLoading.style.display = "block";

            fetch('interest_rate_api.php?action=get_interest_rate_history')
                .then(response => response.json())
                .then(data => {
                    if (historyLoading) historyLoading.style.display = "none";
                    if (data.success) {
                        if (data.history.length > 0) {
                            historyTable.innerHTML = data.history.map((item) => `
                        <tr>
                            <td>${item.id}</td>
                            <td>${item.term_length} Months</td>
                            <td>${parseFloat(item.interest_rate).toFixed(2)}%</td>
                            <td>${new Date(item.updated_at).toLocaleString()}</td>
                            <td>${item.updated_by || "System"}</td>
                        </tr>
                    `).join("");
                        } else {
                            historyTable.innerHTML = '<tr><td colspan="5">No history available.</td></tr>';
                        }
                    } else {
                        historyTable.innerHTML = `<tr><td colspan="5">Error: ${data.message}</td></tr>`;
                    }
                }).catch((error) => {
                    if (historyLoading) historyLoading.style.display = "none";
                    historyTable.innerHTML = `<tr><td colspan="5">Error loading history: ${error.message}</td></tr>`;
                    console.error("Fetch error:", error);
                });
        }

        // Loan Details Modal Functions
        function openLoanDetailsModal(applicationId) {
            console.log('Opening modal for application ID:', applicationId, 'type:', typeof applicationId);

            if (!applicationId || applicationId === '' || applicationId === null || applicationId === undefined) {
                console.error('Application ID is required but missing or invalid:', applicationId);
                showToast('error', 'Error', 'Invalid application ID. Please refresh the page and try again.');
                return;
            }

            // Ensure it's a valid application ID format
            const appIdPattern = /^(APP|LOAN)-\d{8}-\d{4}$/;
            if (!appIdPattern.test(applicationId)) {
                console.error('Application ID has invalid format:', applicationId);
                showToast('error', 'Error', 'Invalid application ID format. Please refresh the page and try again.');
                return;
            }

            const modal = document.getElementById('loanDetailsModal');
            if (modal) {
                modal.style.display = 'flex';
                setTimeout(() => modal.classList.add('show'), 10);
                loadLoanDetails(applicationId); // Pass as string
                console.log('Loan Details Modal opened for application:', applicationId);
            }
        }

        function closeLoanDetailsModal() {
            const modal = document.getElementById('loanDetailsModal');
            if (modal) {
                modal.classList.remove('show');
                setTimeout(() => (modal.style.display = "none"), 300);
                console.log('Loan Details Modal closed');
            }
        }

        // Load Loan Details
        function loadLoanDetails(applicationId) {
            console.log('Loading loan details for application:', applicationId);

            const content = document.getElementById('loanDetailsContent');
            if (!content) {
                console.error('Modal content element not found');
                return;
            }

            content.innerHTML = '<div class="loading-content"><div class="spinner"></div><p>Loading loan details...</p></div>';

            fetch(`Superadmin_dashboard.php?action=get_loan_details&application_id=${applicationId}`, { cache: "no-store" })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        const loan = data.loan;
                        const documents = data.documents || [];
                        const remarks = data.remarks || [];
                        const logs = data.logs || [];

                        // Applicant Information Section - MATCHING ADMIN1
                        const applicantInfo = `
                            <div class="modal-section applicant-info-section">
                                <h3><i class="fas fa-user"></i> Applicant Information</h3>
                                <div class="info-grid">
                                    <div><strong>Full Name:</strong> ${loan.first_name} ${loan.last_name}</div>
                                    <div><strong>Email:</strong> ${loan.email}</div>
                                    <div><strong>Contact:</strong> ${loan.contact || 'Not provided'}</div>
                                    <div><strong>Birthday:</strong> ${loan.birthday ? new Date(loan.birthday).toLocaleDateString() : 'Not provided'}</div>
                                </div>
                            </div>
                        `;

                        // Loan Details Section - MATCHING ADMIN1
                        const loanInfo = `
                            <div class="modal-section loan-info-section">
                                <h3><i class="fas fa-file-invoice-dollar"></i> Loan Details</h3>
                                <div class="info-grid">
                                    <div><strong>Loan Type:</strong> ${loan.type_name}</div>
                                    <div><strong>Amount Applied:</strong> ₱${parseFloat(loan.amount_applied || 0).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
                                    <div><strong>Final Loan Amount:</strong> ${loan.final_loan_amount ? '₱' + parseFloat(loan.final_loan_amount).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : 'Not set'}</div>
                                    <div><strong>Submission Date:</strong> ${new Date(loan.created_at).toLocaleDateString()}</div>
                                    <div><strong>Loan Status:</strong> <span class="status-badge badge-${loan.status.toLowerCase()}">${loan.status || 'Pending'}</span></div>
                                    <div><strong>Pre-Approval Status:</strong> <span class="status-badge badge-${loan.pre_approval_status.toLowerCase()}">${loan.pre_approval_status || 'Pending'}</span></div>
                                    <div><strong>Credit Investigation:</strong> <span class="status-badge badge-${loan.credit_investigation_status.toLowerCase()}">${loan.credit_investigation_status || 'Pending'}</span></div>
                                </div>
                            </div>
                        `;

                        // Financial Info Section - MATCHING ADMIN1
                        const financialInfo = loan.net_income ? `
                            <div class="modal-section">
                                <h3><i class="fas fa-chart-line"></i> Financial Information</h3>
                                <div class="financial-grid">
                                    <div class="financial-card">
                                        <h4>Income Sources</h4>
                                        <div class="financial-items">
                                            ${["business_income", "salary_income", "remittance_income", "other_income", "business2_income", "salary2_income"].map(field => `
                                                <div class="financial-row">
                                                    <span>${field.replace(/_/g, " ").replace(/\b\w/g, l => l.toUpperCase())}:</span>
                                                    <strong>₱${parseFloat(loan[field] || 0).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</strong>
                                                </div>
                                            `).join("")}
                                            <div class="financial-row total-row">
                                                <span>Net Income:</span>
                                                <strong class="highlight-green">₱${parseFloat(loan.net_income || 0).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</strong>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="financial-card">
                                        <h4>Monthly Expenses</h4>
                                        <div class="financial-items">
                                            ${["food_allowance", "electricity_bill", "water_bill", "internet_bill", "gas_bill", "educational_allowance", "car_amortization", "insurance", "other_expense"].map(field => `
                                                <div class="financial-row">
                                                    <span>${field.replace(/_/g, " ").replace(/\b\w/g, l => l.toUpperCase())}:</span>
                                                    <strong>₱${parseFloat(loan[field] || 0).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</strong>
                                                </div>
                                            `).join("")}
                                            <div class="financial-row total-row">
                                                <span>Total Expenditures:</span>
                                                <strong class="highlight-red">₱${parseFloat(loan.total_expenditures || 0).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</strong>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="financial-card financial-summary">
                                        <h4>Financial Summary</h4>
                                        <div class="financial-items">
                                            <div class="financial-row">
                                                <span>Expected Monthly Amortization:</span>
                                                <strong>₱${parseFloat(loan.expected_monthly_amortization || 0).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</strong>
                                            </div>
                                            <div class="financial-row total-row">
                                                <span>Remaining Income:</span>
                                                <strong class="highlight-${parseFloat(loan.remaining_income || 0) >= 0 ? "green" : "red"}">₱${parseFloat(loan.remaining_income || 0).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        ` : `<div class="modal-section"><h3><i class="fas fa-chart-line"></i> Financial Information</h3><p class="no-data">No financial information available.</p></div>`;

                        // Documents Section - MATCHING ADMIN1
                        const documentsTable = documents.length ? `
                            <div class="modal-section">
                                <h3><i class="fas fa-file-alt"></i> Submitted Documents</h3>
                                <div class="documents-table-wrapper">
                                    <table class="modal-documents-table">
                                        <thead>
                                            <tr>
                                                <th>Document</th>
                                                <th> Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${documents.map(doc => `
                                                <tr>
                                                    <td class="doc-name">
                                                        <a href="${doc.file_path}" target="_blank" class="doc-link">
                                                             ${doc.document_name}
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <span class="status-badge status-${(doc.status || 'pending').toLowerCase()}">${doc.status || 'Pending'}</span>
                                                    </td>
                                                </tr>
                                            `).join("")}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        ` : `<div class="modal-section"><h3><i class="fas fa-file-alt"></i> Submitted Documents</h3><p class="no-data">No documents submitted.</p></div>`;

                        // Remarks Section - MATCHING ADMIN1
                        const remarksSection = remarks.length ? `
                            <div class="modal-section">
                                <h3><i class="fas fa-comment-dots"></i> Remarks History</h3>
                                <div class="remarks-timeline">
                                    ${remarks.map(remark => `
                                        <div class="remark-item">
                                            <div class="remark-header">
                                                <span class="remark-author"><i class="fas fa-user-circle"></i> ${remark.admin_name || 'Admin'}</span>
                                                <span class="remark-date"><i class="far fa-clock"></i> ${new Date(remark.created_at).toLocaleString("en-US", { month: "short", day: "numeric", year: "numeric", hour: "2-digit", minute: "2-digit" })}</span>
                                            </div>
                                            <div class="remark-content">${remark.remarks}</div>
                                        </div>
                                    `).join("")}
                                </div>
                            </div>
                        ` : `<div class="modal-section"><h3><i class="fas fa-comment-dots"></i> Remarks History</h3><div class="empty-remarks-state"><i class="fas fa-comment-slash" style="font-size: 24px; color: #cbd5e0; margin-bottom: 12px;"></i><p class="no-data" style="color: #6b7280; font-size: 14px; margin: 0;">No remarks available for this application.</p><p style="color: #9ca3af; font-size: 12px; margin: 8px 0 0 0;">Remarks will appear here when admins add comments.</p></div></div>`;

                        // Activity Logs Section - MATCHING ADMIN1
                        const logsSection = logs.length ? `
                            <div class="modal-section">
                                <h3><i class="fas fa-history"></i> Activity Logs</h3>
                                <div class="logs-timeline">
                                    ${logs.map(log => `
                                        <div class="log-item">
                                            <div class="log-indicator"></div>
                                            <div class="log-content">
                                                <div class="log-header">
                                                    <span class="log-time"><i class="far fa-clock"></i> ${new Date(log.created_at).toLocaleString("en-US", { month: "short", day: "numeric", year: "numeric", hour: "2-digit", minute: "2-digit" })}</span>
                                                    <span class="log-role badge-${log.user_role.toLowerCase()}">${log.user_role}</span>
                                                </div>
                                                <div class="log-description">
                                                    <strong>${log.action_type}:</strong> ${log.description}
                                                </div>
                                            </div>
                                        </div>
                                    `).join("")}
                                </div>
                            </div>
                        ` : `<div class="modal-section"><h3><i class="fas fa-history"></i> Activity Logs</h3><div class="empty-logs-state"><i class="fas fa-history" style="font-size: 24px; color: #cbd5e0; margin-bottom: 12px;"></i><p class="no-data" style="color: #6b7280; font-size: 14px; margin: 0;">No activity logs found for this application.</p><p style="color: #9ca3af; font-size: 12px; margin: 8px 0 0 0;">Activity history will appear here as actions are performed.</p></div></div>`;

                        // Check if loan status is editable - MATCHING ADMIN1
                        const isLoanStatusEditable = loan.pre_approval_status.toLowerCase() === "approved" && loan.credit_investigation_status.toLowerCase() === "completed";

                        // Validate application_id before creating form
                        if (!loan.application_id || loan.application_id === '' || loan.application_id === null || loan.application_id === undefined) {
                            console.error('Invalid loan application_id:', loan.application_id);
                            content.innerHTML = `<p class="error"><i class="fas fa-exclamation-circle"></i> Error: Invalid application ID. Please close and reopen this modal.</p>`;
                            return;
                        }

                        // Ensure application_id is a valid string format (APP-YYYYMMDD-NNNN or LOAN-YYYYMMDD-NNNN)
                        const appIdPattern = /^(APP|LOAN)-\d{8}-\d{4}$/;
                        if (!appIdPattern.test(loan.application_id)) {
                            console.error('Application ID has invalid format:', loan.application_id);
                            content.innerHTML = `<p class="error"><i class="fas fa-exclamation-circle"></i> Error: Invalid application ID format. Please close and reopen this modal.</p>`;
                            return;
                        }

                        const validApplicationId = loan.application_id; // Keep as string

                        // Update Form Section - MOVED TO TOP FOR BETTER WORKFLOW
                        const updateForm = `
                            <div class="modal-section update-section">
                                <h3>
                                    <i class="fas fa-edit"></i> 
                                    Update Status & Add Remarks
                                    <span class="priority-badge">Action Required</span>
                                </h3>
                                <form id="statusUpdateForm" class="status-update-form">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="application_id" value="${validApplicationId}">
                                    <!-- Debug info (hidden) -->
                                    <input type="hidden" name="debug_original_id" value="${loan.application_id}">
                                    <input type="hidden" name="debug_parsed_id" value="${validApplicationId}">
                                    
                                    <div class="form-group">
                                        <label for="loanStatus"><i class="fas fa-info-circle"></i> Loan Status</label>
                                        <select name="status" id="loanStatus" class="form-select" ${!isLoanStatusEditable ? "disabled" : ""}>
                                            <option value="">-- Select Status --</option>
                                            <option value="Pending" ${loan.status === "Pending" ? "selected" : ""}>Pending</option>
                                            <option value="Active" ${loan.status === "Active" ? "selected" : ""}>Active</option>
                                        </select>
                                        ${!isLoanStatusEditable ? '<p class="form-note"><i class="fas fa-exclamation-triangle"></i> Loan status can only be changed when Pre-Approval is Approved and Credit Investigation is Completed.</p>' : ''}
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="remarksText"><i class="fas fa-comment"></i> Add Remarks (Optional)</label>
                                        <textarea name="remarks" id="remarksText" class="form-textarea" rows="4" placeholder="Enter your remarks here..."></textarea>
                                    </div>
                                    
                                    <button type="submit" class="submit-btn">
                                        <span class="button-text"><i class="fas fa-save"></i> Update Application</span>
                                    </button>
                                    </button>
                                </form>
                                <div id="updateMessage" class="update-message"></div>
                            </div>
                        `;

                        // Combine all sections - OPTIMIZED ORDER FOR ADMIN WORKFLOW
                        content.innerHTML = `
                            ${applicantInfo}
                            ${loanInfo}
                            ${financialInfo}
                            ${documentsTable}
                            ${updateForm}
                            ${remarksSection}
                            ${logsSection}
                        `;

                        // Handle form submission with confirmation and toast notifications
                        const statusForm = document.getElementById('statusUpdateForm');
                        if (statusForm) {
                            statusForm.addEventListener('submit', function (e) {
                                e.preventDefault();
                                const formData = new FormData(this);
                                const messageDiv = document.getElementById('updateMessage');
                                const submitBtn = this.querySelector('.submit-btn');

                                // Debug form data before sending
                                console.log('Form data being sent:');
                                for (let [key, value] of formData.entries()) {
                                    console.log(`${key}: ${value}`);
                                }

                                // Validate application_id is present and valid
                                const appId = formData.get('application_id');
                                console.log('Application ID from form:', appId, 'type:', typeof appId);

                                if (!appId || appId === '' || appId === 'null' || appId === 'undefined') {
                                    console.error('Missing or invalid application_id in form data');
                                    showToast('error', 'Validation Error', 'Missing application ID. Please refresh the page and try again.');
                                    return;
                                }

                                // Validate application_id format
                                const appIdPattern = /^(APP|LOAN)-\d{8}-\d{4}$/;
                                if (!appIdPattern.test(appId)) {
                                    console.error('Application ID has invalid format:', appId);
                                    showToast('error', 'Validation Error', 'Invalid application ID format. Please refresh the page and try again.');
                                    return;
                                }

                                const status = formData.get('status');
                                const remarks = formData.get('remarks');

                                // Validate that at least one field is being updated
                                if (!status && !remarks.trim()) {
                                    showToast('error', 'Validation Error', 'Please select a status or add remarks before submitting.');
                                    return;
                                }

                                // Create confirmation message
                                let confirmationMessage = 'Are you sure you want to update this application?';
                                if (status) {
                                    confirmationMessage = `Are you sure you want to change the loan status to "${status}"?`;
                                    if (remarks.trim()) {
                                        confirmationMessage += '\n\nRemarks will also be added.';
                                    }
                                } else if (remarks.trim()) {
                                    confirmationMessage = 'Are you sure you want to add these remarks to the application?';
                                }

                                // Show confirmation dialog
                                showConfirmation(
                                    'Confirm Update',
                                    confirmationMessage,
                                    () => {
                                        // User confirmed, proceed with update
                                        performStatusUpdate(formData, submitBtn, messageDiv, status, remarks);
                                    },
                                    () => {
                                        // User cancelled
                                        showToast('info', 'Update Cancelled', 'The status update was cancelled.');
                                    }
                                );
                            });
                        }

                        function performStatusUpdate(formData, submitBtn, messageDiv, status, remarks) {
                            // Show loading state
                            submitBtn.classList.add('loading');
                            submitBtn.disabled = true;

                            // Show info toast
                            showToast('info', 'Processing Update', 'Updating loan application status...');

                            fetch('Superadmin_dashboard.php', {
                                method: 'POST',
                                body: formData
                            })
                                .then(response => response.json())
                                .then(data => {
                                    // Remove loading state
                                    submitBtn.classList.remove('loading');
                                    submitBtn.disabled = false;

                                    if (data.success) {
                                        // Show success toast
                                        let successMessage = data.message;
                                        if (status && remarks.trim()) {
                                            successMessage = `Status updated to "${status}" and remarks added successfully.`;
                                        } else if (status) {
                                            successMessage = `Loan status successfully updated to "${status}".`;
                                        } else if (remarks.trim()) {
                                            successMessage = 'Remarks added successfully to the application.';
                                        }

                                        showToast('success', 'Update Successful', successMessage);

                                        // Clear form
                                        statusForm.reset();

                                        // Reload page after a delay to show updated data
                                        setTimeout(() => {
                                            location.reload();
                                        }, 2000);
                                    } else {
                                        // Show error toast
                                        showToast('error', 'Update Failed', data.message || 'Failed to update application status.');

                                        // Also show in message div for fallback
                                        messageDiv.innerHTML = `<p class="error"><i class="fas fa-exclamation-circle"></i> ${data.message}</p>`;
                                    }
                                })
                                .catch(error => {
                                    // Remove loading state
                                    submitBtn.classList.remove('loading');
                                    submitBtn.disabled = false;

                                    console.error('Update error:', error);

                                    // Show error toast
                                    showToast('error', 'Network Error', 'Failed to connect to server. Please check your connection and try again.');

                                    // Also show in message div for fallback
                                    messageDiv.innerHTML = `<p class="error"><i class="fas fa-exclamation-circle"></i> Error: ${error.message}</p>`;
                                });
                        }

                    } else {
                        content.innerHTML = `<p class="error"><i class="fas fa-exclamation-circle"></i> ${data.message || "Failed to load loan details."}</p>`;
                    }
                })
                .catch(error => {
                    content.innerHTML = `<p class="error"><i class="fas fa-exclamation-circle"></i> Error: ${error.message}</p>`;
                    console.error('Fetch error:', error);
                });
        }

        // ============================================
        // TOAST NOTIFICATION SYSTEM
        // ============================================

        function showToast(type, title, message, duration = 5000) {
            const toastContainer = document.getElementById('toastContainer');
            if (!toastContainer) return;

            const toast = document.createElement('div');
            toast.className = `toast ${type}`;

            const iconMap = {
                success: 'fas fa-check-circle',
                error: 'fas fa-exclamation-circle',
                info: 'fas fa-info-circle'
            };

            toast.innerHTML = `
                <div class="toast-header">
                    <div>
                        <i class="${iconMap[type]} toast-icon"></i>
                        ${title}
                    </div>
                    <button class="toast-close" onclick="removeToast(this.closest('.toast'))">&times;</button>
                </div>
                <div class="toast-body">${message}</div>
            `;

            toastContainer.appendChild(toast);

            // Show toast
            setTimeout(() => toast.classList.add('show'), 10);

            // Auto remove
            setTimeout(() => removeToast(toast), duration);
        }

        function removeToast(toast) {
            if (!toast) return;
            toast.classList.remove('show');
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }

        // ============================================
        // CONFIRMATION DIALOG SYSTEM
        // ============================================

        function showConfirmation(title, message, onConfirm, onCancel = null) {
            const modal = document.getElementById('confirmationModal');
            const titleEl = document.getElementById('confirmationTitle');
            const messageEl = document.getElementById('confirmationMessage');
            const confirmBtn = document.getElementById('confirmationConfirm');
            const cancelBtn = document.getElementById('confirmationCancel');

            if (!modal || !titleEl || !messageEl || !confirmBtn || !cancelBtn) return;

            titleEl.textContent = title;
            messageEl.textContent = message;

            // Remove existing event listeners
            const newConfirmBtn = confirmBtn.cloneNode(true);
            const newCancelBtn = cancelBtn.cloneNode(true);
            confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
            cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);

            // Add new event listeners
            newConfirmBtn.addEventListener('click', () => {
                hideConfirmation();
                if (onConfirm) onConfirm();
            });

            newCancelBtn.addEventListener('click', () => {
                hideConfirmation();
                if (onCancel) onCancel();
            });

            modal.classList.add('show');
        }

        function hideConfirmation() {
            const modal = document.getElementById('confirmationModal');
            if (modal) {
                modal.classList.remove('show');
            }
        }

        // Close confirmation modal when clicking outside
        document.addEventListener('click', function (e) {
            const modal = document.getElementById('confirmationModal');
            if (e.target === modal) {
                hideConfirmation();
            }
        });

        // Dropdown Toggle Function
        function toggleDropdown(event) {
            event.stopPropagation();
            const dropdown = document.getElementById("dropdown");
            dropdown.classList.toggle("show");
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

        // ============================================
        // TABLE ENHANCEMENT SYSTEM (Admin1 Parity)
        // ============================================

        const TableEnhancementSystem = {
            /**
             * Add status badge to table cells
             * @param {string} status Status text
             * @param {string} type Badge type: success, warning, danger, info
             * @returns {string} HTML badge
             */
            getStatusBadge(status, type = 'info') {
                const badgeClasses = {
                    success: 'badge-success',
                    warning: 'badge-warning',
                    danger: 'badge-danger',
                    info: 'badge-info'
                };
                const badgeClass = badgeClasses[type] || 'badge-info';
                return `<span class="status-badge ${badgeClass}">${status}</span>`;
            },

            /**
             * Format timestamp to readable date/time
             * @param {string} timestamp ISO timestamp
             * @returns {string} Formatted date and time
             */
            formatTimestamp(timestamp) {
                if (!timestamp) return '-';
                try {
                    const date = new Date(timestamp);
                    return date.toLocaleString('en-PH', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit',
                        hour12: true
                    });
                } catch (e) {
                    return timestamp;
                }
            },

            /**
             * Format currency value with locale
             * @param {number} amount Amount to format
             * @param {string} currency Currency code
             * @returns {string} Formatted currency
             */
            formatCurrency(amount, currency = 'PHP') {
                if (!amount && amount !== 0) return '-';
                try {
                    const formatted = parseFloat(amount).toLocaleString('en-PH', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                    return `₱${formatted}`;
                } catch (e) {
                    return `₱${amount}`;
                }
            },

            /**
             * Add sorting functionality to table headers
             * @param {string} tableSelector Table selector
             */
            enableTableSort(tableSelector) {
                const table = document.querySelector(tableSelector);
                if (!table) return;

                const headers = table.querySelectorAll('thead th');
                headers.forEach((header, columnIndex) => {
                    header.style.cursor = 'pointer';
                    header.style.userSelect = 'none';
                    header.title = 'Click to sort';

                    header.addEventListener('click', () => {
                        const tbody = table.querySelector('tbody');
                        const rows = Array.from(tbody.querySelectorAll('tr'));

                        const isAscending = header.dataset.sort !== 'asc';

                        rows.sort((a, b) => {
                            const aVal = a.querySelectorAll('td')[columnIndex]?.textContent.trim() || '';
                            const bVal = b.querySelectorAll('td')[columnIndex]?.textContent.trim() || '';

                            // Try numeric comparison
                            const aNum = parseFloat(aVal.replace(/[^\d.-]/g, ''));
                            const bNum = parseFloat(bVal.replace(/[^\d.-]/g, ''));

                            if (!isNaN(aNum) && !isNaN(bNum)) {
                                return isAscending ? aNum - bNum : bNum - aNum;
                            }

                            // String comparison
                            return isAscending ?
                                aVal.localeCompare(bVal) :
                                bVal.localeCompare(aVal);
                        });

                        // Clear previous sort indicators
                        headers.forEach(h => h.textContent = h.textContent.replace(/\s+[↑↓]/g, ''));

                        // Add sort indicator
                        header.textContent += isAscending ? ' ↑' : ' ↓';
                        header.dataset.sort = isAscending ? 'asc' : 'desc';

                        // Reorder rows
                        rows.forEach(row => tbody.appendChild(row));

                        console.log(`📊 Table sorted by column ${columnIndex} (${isAscending ? 'ascending' : 'descending'})`);
                    });
                });
            },

            /**
             * Add row highlighting on hover
             * @param {string} tableSelector Table selector
             */
            enableRowHighlight(tableSelector) {
                const table = document.querySelector(tableSelector);
                if (!table) return;

                const rows = table.querySelectorAll('tbody tr');
                rows.forEach(row => {
                    row.addEventListener('mouseenter', () => {
                        row.classList.add('row-highlight');
                    });

                    row.addEventListener('mouseleave', () => {
                        row.classList.remove('row-highlight');
                    });
                });
            }
        };

        // Apply enhancements to Due Accounts Table
        if (document.querySelector('.due-accounts-table')) {
            TableEnhancementSystem.enableTableSort('.due-accounts-table');
            TableEnhancementSystem.enableRowHighlight('.due-accounts-table');
        }

        // Apply enhancements to Loan Applicants Table
        if (document.querySelector('.loan-table')) {
            TableEnhancementSystem.enableTableSort('.loan-table');
            TableEnhancementSystem.enableRowHighlight('.loan-table');
        }

        // Apply enhancements to Activity Logs Table
        if (document.querySelector('.activity-logs-table')) {
            TableEnhancementSystem.enableTableSort('.activity-logs-table');
            TableEnhancementSystem.enableRowHighlight('.activity-logs-table');
        }

        console.log('✅ Table Enhancement System initialized');

        // Export functions to global scope for backward compatibility
        window.openManageInterestRateModal = openManageInterestRateModal;
        window.openInterestRateModal = openInterestRateModal;
        window.closeManageInterestRateModal = closeManageInterestRateModal;
        window.closeInterestRateModal = closeInterestRateModal;
        window.updateInterestRateStats = updateInterestRateStats;
        window.openLoanDetailsModal = openLoanDetailsModal;
        window.closeLoanDetailsModal = closeLoanDetailsModal;
        window.toggleDropdown = toggleDropdown;

        // Add modal click-outside-to-close functionality
        document.addEventListener('click', function (event) {
            // Close modals when clicking outside
            const modals = ['manageInterestRateModal', 'loanDetailsModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (modal && (modal.style.display === 'block' || modal.classList.contains('show'))) {
                    const modalContent = modal.querySelector('.modal-content');
                    if (modalContent && !modalContent.contains(event.target)) {
                        modal.classList.remove('show');
                        setTimeout(() => (modal.style.display = "none"), 300);
                    }
                }
            });
        });

        // Add escape key to close modals
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                const modals = ['manageInterestRateModal', 'loanDetailsModal'];
                modals.forEach(modalId => {
                    const modal = document.getElementById(modalId);
                    if (modal && (modal.style.display === 'block' || modal.classList.contains('show'))) {
                        modal.classList.remove('show');
                        setTimeout(() => (modal.style.display = "none"), 300);
                    }
                });
            }
        });

    </script>
    <script src="JAVASCRIPT/Real-Time.js"></script>
    <script src="JAVASCRIPT/superadmin_dashboard.js"></script>

    <div id="exportModal" class="export-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-body">

                <!-- Preparing -->
                <div id="preparingStep">
                    <i class="fas fa-sync-alt fa-spin"></i>
                    <h3>Preparing Export...</h3>
                    <p>Your file is being generated. Please wait.</p>
                    <div class="progress-bar">
                        <div class="progress-fill"></div>
                    </div>
                </div>

                <!-- Success -->
                <div id="successStep" style="display:none;">
                    <i class="fas fa-check-circle success-icon"></i>
                    <h3>Download Successful!</h3>
                    <p id="filenameDisplay">Loan_Records.csv is ready.</p>
                    <button onclick="closeExportModal()" class="modal-btn close-btn">Close</button>
                    <button onclick="openDownloadedFile()" class="modal-btn open-btn">Open File</button>
                </div>

                <!-- Failed -->
                <div id="failedStep" style="display:none;">
                    <i class="fas fa-times-circle failed-icon"></i>
                    <h3>Export Failed</h3>
                    <p>Could not generate the file. Please try again.</p>
                    <button onclick="retryExport()" class="modal-btn retry-btn">Try Again</button>
                    <button onclick="closeExportModal()" class="modal-btn close-btn">Close</button>
                </div>

            </div>
        </div>
    </div>
    <!-- SUCCESS TOAST - LOOKS EXACTLY LIKE YOUR IMAGE -->
    <div id="successToast" class="success-toast" style="display: none;">
        <div class="toast-inner">
            <div class="toast-check">
                <i class="fas fa-check"></i>
            </div>
            <div class="toast-text">
                <div class="toast-title">Download Successful!</div>
                <div class="toast-filename" id="toastFilename">Loan_Records_2025-11-19.csv is ready.</div>
            </div>
            <div class="toast-buttons">
                <button onclick="openDownloadedFile()" class="toast-btn green">Open File</button>
                <button onclick="hideSuccessToast()" class="toast-btn gray">Close</button>
            </div>
        </div>
    </div>
</body>

</html>
<?php
mysqli_close($conn);
?>