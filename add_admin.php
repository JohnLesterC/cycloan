<?php
session_start();
require "CYCLOAN_db.php";

// Include PHPMailer for email functionality
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/src/Exception.php';
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';

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

// Initialize message variable
$message = '';
$generated_password = '';

// Function to get profile image path with fallback to assets
function getProfileImagePath($profileImg)
{
    $upload_dir = __DIR__ . '/uploads/';
    $assets_dir = __DIR__ . '/assets/';

    // If profile image is provided and exists in uploads folder
    if (!empty($profileImg) && file_exists($upload_dir . $profileImg)) {
        return '/uploads/' . htmlspecialchars($profileImg);
    }

    // Check if default.jpg exists in uploads folder
    if (file_exists($upload_dir . 'default.jpg')) {
        return '/uploads/default.jpg';
    }

    // Fallback to assets/default.jpg
    if (file_exists($assets_dir . 'default.jpg')) {
        return '/assets/default.jpg';
    }

    // Last resort fallback
    return '/assets/default.jpg';
}

// Function to generate secure random password
function generateSecurePassword($length = 12)
{
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
    $password = '';
    $charLength = strlen($characters);

    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[random_int(0, $charLength - 1)];
    }

    return $password;
}

// Function to send welcome email to new admin
function sendAdminWelcomeEmail($adminEmail, $firstName, $lastName, $password, $adminType)
{
    try {
        $mail = new PHPMailer(true);

        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'scycloan@gmail.com';
        $mail->Password = 'xbvo zplr dpme ixxj'; // Use app password, not regular password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients
        $mail->setFrom('scycloan@gmail.com', 'CYCLOAN Admin System');
        $mail->addAddress($adminEmail, $firstName . ' ' . $lastName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Welcome to CYCLOAN Admin System - Account Created';

        // Create beautiful HTML email
        $adminTypeDisplay = $adminType === 'Admin 1' ? 'Admin 1 (Full Access)' : 'Admin 2 (Limited Access)';
        $systemUrl = '';

        $emailBody = "
        <html>
        <head>
            <style>
                body { font-family: 'Poppins', Arial, sans-serif; background-color: #f5f7fa; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); overflow: hidden; }
                .header { background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%); color: white; padding: 40px 20px; text-align: center; }
                .header h1 { margin: 0; font-size: 28px; font-weight: 700; }
                .content { padding: 40px; }
                .welcome-text { font-size: 16px; color: #333; margin-bottom: 30px; line-height: 1.6; }
                .credentials-box { background-color: #f0f8f5; border-left: 5px solid #34c759; padding: 20px; margin: 25px 0; border-radius: 8px; }
                .credentials-box h3 { color: #1b5e20; margin-top: 0; font-size: 18px; }
                .credential-item { margin: 15px 0; font-size: 15px; }
                .credential-label { color: #666; font-weight: 600; display: inline-block; min-width: 120px; }
                .credential-value { color: #333; font-family: 'Courier New', monospace; font-weight: 500; }
                .password-warning { background-color: #fff3cd; border-left: 5px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 8px; font-size: 14px; color: #856404; }
                .instructions-box { background-color: #e3f2fd; border-left: 5px solid #2196F3; padding: 20px; margin: 25px 0; border-radius: 8px; }
                .instructions-box h3 { color: #1565c0; margin-top: 0; font-size: 16px; }
                .instructions-box ol { margin: 10px 0; padding-left: 20px; }
                .instructions-box li { margin: 8px 0; font-size: 14px; color: #333; }
                .footer { background-color: #f5f7fa; padding: 20px; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #e0e0e0; }
                .footer a { color: #34c759; text-decoration: none; }
                .button { display: inline-block; background: linear-gradient(135deg, #34c759 0%, #2e7d32 100%); color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; margin-top: 20px; font-weight: 600; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Welcome to CYCLOAN</h1>
                    <p style='margin: 10px 0 0 0; font-size: 16px; opacity: 0.95;'>Admin System Portal</p>
                </div>
                
                <div class='content'>
                    <div class='welcome-text'>
                        <strong>Dear {$firstName} {$lastName},</strong><br><br>
                        Your administrator account has been successfully created in the CYCLOAN Loan Management System. 
                        Below are your login credentials and important account information.
                    </div>
                    
                    <div class='credentials-box'>
                        <h3><i>📋 Your Account Details</i></h3>
                        <div class='credential-item'>
                            <span class='credential-label'>Email:</span>
                            <span class='credential-value'>{$adminEmail}</span>
                        </div>
                        <div class='credential-item'>
                            <span class='credential-label'>Password:</span>
                            <span class='credential-value'>{$password}</span>
                        </div>
                        <div class='credential-item'>
                            <span class='credential-label'>Admin Role:</span>
                            <span class='credential-value'>{$adminTypeDisplay}</span>
                        </div>
                    </div>
                    
                    <div class='password-warning'>
                        <strong>⚠️ Important Security Notice:</strong><br>
                        This password is displayed only once. Please change your password immediately after your first login. 
                        Do not share your credentials with anyone.
                    </div>
                    
                    <div class='instructions-box'>
                        <h3>📝 First Login Instructions</h3>
                        <ol>
                            <li>Visit: <a href='{$systemUrl}' style='color: #2196F3;'>{$systemUrl}</a></li>
                            <li>Click on the Admin Login section</li>
                            <li>Enter your email and password</li>
                            <li>Upon successful login, change your password immediately</li>
                            <li>Complete your profile with additional information</li>
                        </ol>
                    </div>
                    
                    <div class='instructions-box' style='background-color: #f3e5f5; border-left-color: #9c27b0;'>
                        <h3 style='color: #6a1b9a;'>👤 Your Role Permissions</h3>
                        " . ($adminType === 'Admin 1' ? "
                        <ul style='margin: 10px 0; padding-left: 20px;'>
                            <li><strong>Full Access</strong> to all system features</li>
                            <li>Manage applicants and loan records</li>
                            <li>View reports and analytics</li>
                            <li>Manage other administrators</li>
                            <li>Configure system settings</li>
                            <li>Manage credit points and interest rates</li>
                        </ul>
                        " : "
                        <ul style='margin: 10px 0; padding-left: 20px;'>
                            <li><strong>Limited Access</strong> to core features</li>
                            <li>View and manage applicants</li>
                            <li>Process loan applications</li>
                            <li>View activity history</li>
                            <li>Cannot modify system settings</li>
                            <li>Cannot manage other administrators</li>
                        </ul>
                        ") . "
                    </div>
                    
                    <a href='{$systemUrl}' class='button'>Login to Your Account</a>
                </div>
                
                <div class='footer'>
                    <p><strong>CYCLOAN Loan Management System</strong><br>
                    For support or issues, contact the system administrator.<br>
                    © 2024 CYCLOAN. All rights reserved.<br>
                    <a href='{$systemUrl}'>Visit Our System</a></p>
                </div>
            </div>
        </body>
        </html>";

        $mail->Body = $emailBody;

        // Send email
        if ($mail->send()) {
            return true;
        } else {
            error_log("Admin welcome email failed to send to {$adminEmail}: " . $mail->ErrorInfo, 3, 'errors.log');
            return false;
        }
    } catch (Exception $e) {
        error_log("Admin welcome email exception for {$adminEmail}: " . $e->getMessage(), 3, 'errors.log');
        return false;
    }
}

// Function to reset admin password and send new password via email
function resetAdminPassword($adminEmail, $adminId, $table, $conn)
{
    try {
        $newPassword = generateSecurePassword(12);
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE $table SET password = ? WHERE id = ?");
        if (!$stmt) {
            error_log("Reset password query preparation failed: " . $conn->error, 3, 'errors.log');
            return ['success' => false, 'message' => 'Database error'];
        }

        $stmt->bind_param("si", $hashedPassword, $adminId);
        if ($stmt->execute()) {
            $stmt->close();

            // Send password reset email
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'scycloan@gmail.com';
            $mail->Password = 'xbvo zplr dpme ixxj';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('scycloan@gmail.com', 'CYCLOAN Admin System');
            $mail->addAddress($adminEmail);
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset - CYCLOAN Admin System';

            $emailBody = "
            <html>
            <head>
                <style>
                    body { font-family: 'Poppins', Arial, sans-serif; }
                    .container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
                    .header { background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%); color: white; padding: 30px; text-align: center; }
                    .content { padding: 30px; }
                    .password-box { background-color: #f0f8f5; border-left: 5px solid #34c759; padding: 20px; margin: 20px 0; border-radius: 8px; }
                    .password-value { font-family: 'Courier New', monospace; font-weight: 600; font-size: 18px; color: #1b5e20; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Password Reset</h1>
                    </div>
                    <div class='content'>
                        <p>Your password has been reset. Here is your new temporary password:</p>
                        <div class='password-box'>
                            <p style='margin: 0 0 10px 0; color: #666;'>New Password:</p>
                            <div class='password-value'>$newPassword</div>
                        </div>
                        <p><strong>⚠️ Important:</strong> Please change this password immediately after logging in.</p>
                        <p>Login URL: <a href='index.php'>localhost</a></p>
                    </div>
                </div>
            </body>
            </html>";

            $mail->Body = $emailBody;

            if ($mail->send()) {
                return ['success' => true, 'message' => 'Password reset successfully. New password sent to ' . $adminEmail, 'password' => $newPassword];
            } else {
                return ['success' => true, 'message' => 'Password reset but email could not be sent. New password: ' . $newPassword, 'password' => $newPassword];
            }
        } else {
            error_log("Password reset failed: " . $stmt->error, 3, 'errors.log');
            $stmt->close();
            return ['success' => false, 'message' => 'Failed to reset password'];
        }
    } catch (Exception $e) {
        error_log("Password reset exception: " . $e->getMessage(), 3, 'errors.log');
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// Function to get admin activity statistics
function getAdminStats($table, $conn)
{
    try {
        $query = "SELECT 
                    COUNT(*) as total_admins,
                    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as recent_admins
                  FROM $table";

        $stmt = $conn->prepare($query);
        if (!$stmt) {
            return ['total' => 0, 'recent' => 0];
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();

        return ['total' => $data['total_admins'] ?? 0, 'recent' => $data['recent_admins'] ?? 0];
    } catch (Exception $e) {
        error_log("Admin stats exception: " . $e->getMessage(), 3, 'errors.log');
        return ['total' => 0, 'recent' => 0];
    }
}

// Function to log admin activity
function logAdminActivity($action, $targetAdminEmail, $performedBy, $conn)
{
    try {
        $logMessage = "Admin $performedBy performed action: $action on admin: $targetAdminEmail";
        $logTime = date('Y-m-d H:i:s');

        // Create activity log table if it doesn't exist
        $conn->query("CREATE TABLE IF NOT EXISTS admin_activity_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            action VARCHAR(255) NOT NULL,
            target_admin_email VARCHAR(255),
            performed_by VARCHAR(255),
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(45),
            INDEX idx_timestamp (timestamp),
            INDEX idx_performed_by (performed_by)
        )");

        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $stmt = $conn->prepare("INSERT INTO admin_activity_log (action, target_admin_email, performed_by, ip_address) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("ssss", $action, $targetAdminEmail, $performedBy, $ipAddress);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Activity logging exception: " . $e->getMessage(), 3, 'errors.log');
    }
}

// Handle AJAX requests for admin management
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = htmlspecialchars($_POST['action']);

    switch ($action) {
        case 'reset_password':
            $adminId = intval($_POST['admin_id'] ?? 0);
            $adminEmail = htmlspecialchars($_POST['admin_email'] ?? '');
            $adminType = htmlspecialchars($_POST['admin_type'] ?? '');
            $table = ($adminType === 'Admin 1') ? 'admin1' : 'admin2';

            $result = resetAdminPassword($adminEmail, $adminId, $table, $conn);
            logAdminActivity('Reset Password', $adminEmail, $_SESSION['email'], $conn);
            echo json_encode($result);
            exit;

        case 'get_stats':
            $admin1Stats = getAdminStats('admin1', $conn);
            $admin2Stats = getAdminStats('admin2', $conn);
            echo json_encode([
                'admin1' => $admin1Stats,
                'admin2' => $admin2Stats,
                'total_admins' => $admin1Stats['total'] + $admin2Stats['total']
            ]);
            exit;
    }
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $firstName = htmlspecialchars(trim($_POST['firstName']));
    $middleName = htmlspecialchars(trim($_POST['middleName']));
    $lastName = htmlspecialchars(trim($_POST['lastName']));
    $email = htmlspecialchars(trim($_POST['email']));
    $phone = htmlspecialchars(trim($_POST['phone']));
    $address = htmlspecialchars(trim($_POST['address']));
    $adminType = htmlspecialchars(trim($_POST['admin_type']));

    if (empty($firstName) || empty($lastName) || empty($email) || empty($phone) || empty($address) || empty($adminType)) {
        $message = "<div class='message error'><i class='fas fa-exclamation-circle'></i> All fields are required.</div>";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "<div class='message error'><i class='fas fa-exclamation-circle'></i> Invalid email format.</div>";
    } else {
        // Generate secure password automatically
        $password = generateSecurePassword(12);
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $table = ($adminType === 'Admin 1') ? 'admin1' : 'admin2';

        $checkStmt = $conn->prepare("SELECT email FROM $table WHERE email = ?");
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        if ($checkResult->num_rows > 0) {
            $message = "<div class='message error'><i class='fas fa-exclamation-circle'></i> Email already exists in $table.</div>";
        } else {
            $stmt = $conn->prepare("INSERT INTO $table (first_name, middle_name, last_name, email, phone, address, password, profile_img) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $profile_img = 'default.jpg';
                $stmt->bind_param("ssssssss", $firstName, $middleName, $lastName, $email, $phone, $address, $hashedPassword, $profile_img);
                if ($stmt->execute()) {
                    $generated_password = $password;

                    // Send welcome email to new admin
                    $emailSent = sendAdminWelcomeEmail($email, $firstName, $lastName, $password, $adminType);

                    if ($emailSent) {
                        $message = "<div class='message success'><i class='fas fa-check-circle'></i> New admin added successfully. Welcome email has been sent to {$email}. Password has been generated and is displayed below.</div>";
                    } else {
                        $message = "<div class='message success'><i class='fas fa-check-circle'></i> New admin added successfully. Password has been generated and displayed below. (Note: Welcome email could not be sent.)</div>";
                    }
                } else {
                    $message = "<div class='message error'><i class='fas fa-exclamation-circle'></i> Error: " . htmlspecialchars($stmt->error) . "</div>";
                    error_log("Insert admin failed for $table: " . $stmt->error, 3, 'errors.log');
                }
                $stmt->close();
            } else {
                $message = "<div class='message error'><i class='fas fa-exclamation-circle'></i> Database error: Unable to prepare insert statement.</div>";
                error_log("Insert admin prepare failed for $table: " . $conn->error, 3, 'errors.log');
            }
        }
        $checkStmt->close();
    }
}

// Fetch Admin 1 Accounts
$stmt1 = $conn->prepare("SELECT id, first_name, middle_name, last_name, email, profile_img FROM admin1");
if (!$stmt1) {
    error_log("Admin1 query preparation failed: " . $conn->error, 3, 'errors.log');
    $message .= "<div class='message error'><i class='fas fa-exclamation-circle'></i> Database error: Unable to fetch Admin 1 accounts.</div>";
}
$stmt1->execute();
$admin1_result = $stmt1->get_result();

// Fetch Admin 2 Accounts
$stmt2 = $conn->prepare("SELECT id, first_name, middle_name, last_name, email, profile_img FROM admin2");
if (!$stmt2) {
    error_log("Admin2 query preparation failed: " . $conn->error, 3, 'errors.log');
    $message .= "<div class='message error'><i class='fas fa-exclamation-circle'></i> Database error: Unable to fetch Admin 2 accounts.</div>";
}
$stmt2->execute();
$admin2_result = $stmt2->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Admin - CYCLOAN</title>
    <link rel="stylesheet" href="CSS/admin_dashboard.css">
    <link rel="stylesheet" href="CSS/admin_profile.css">
    <link rel="stylesheet" href="CSS/nav_active.css">
    <link rel="stylesheet" href="CSS/add_admin.css">
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

            </a>
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
        <?php if ($message): ?>
            <?php echo $message; ?>
        <?php endif; ?>
        <?php if (isset($_GET['message'])): ?>
            <div class="message success"><i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($_GET['message']); ?></div>
        <?php endif; ?>

        <div class="page-header">
            <div class="header-content">
                <div class="header-icon">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="header-text">
                    <h1>Admin Management</h1>
                    <p>Create and manage administrator accounts.</p>
                </div>
            </div>
        </div>

        <?php if ($generated_password): ?>
            <div class="password-generated-alert">
                <div class="alert-content">
                    <i class="fas fa-key"></i>
                    <div class="alert-text">
                        <h3>Generated Password</h3>
                        <p>A secure password has been generated for the new admin. Copy it and share it securely:</p>
                        <div class="password-display-box">
                            <code id="generatedPassword"><?php echo htmlspecialchars($generated_password); ?></code>
                            <button type="button" class="copy-btn" onclick="copyPassword()">
                                <i class="fas fa-copy"></i> Copy Password
                            </button>
                        </div>
                        <p class="alert-warning"><i class="fas fa-exclamation-triangle"></i> Keep this password secure. The
                            admin should change it after the first login.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="admin-management">
            <!-- Add Admin Form Section -->
            <div class="form-section">
                <form class="add-admin-form" method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="firstName" class="required"><i class="fas fa-user"></i> First Name</label>
                            <input type="text" id="firstName" name="firstName" placeholder="Enter first name" required>
                        </div>
                        <div class="form-group">
                            <label for="middleName"><i class="fas fa-user"></i> Middle Name</label>
                            <input type="text" id="middleName" name="middleName"
                                placeholder="Enter middle name (optional)">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="lastName" class="required"><i class="fas fa-user"></i> Last Name</label>
                            <input type="text" id="lastName" name="lastName" placeholder="Enter last name" required>
                        </div>
                        <div class="form-group">
                            <label for="email" class="required"><i class="fas fa-envelope"></i> Email Address</label>
                            <input type="email" id="email" name="email" placeholder="admin@cycloan.com" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone" class="required"><i class="fas fa-phone"></i> Phone Number</label>
                            <input type="tel" id="phone" name="phone" placeholder="+63 XXX XXX XXXX" required>
                        </div>
                        <div class="form-group">
                            <label for="address" class="required"><i class="fas fa-map-marker-alt"></i> Address</label>
                            <input type="text" id="address" name="address" placeholder="Enter complete address"
                                required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="admin_type" class="required"><i class="fas fa-user-tag"></i> Admin Type</label>
                            <select id="admin_type" name="admin_type" required>
                                <option value="" disabled selected>Select admin type</option>
                                <option value="Admin 1">Admin 1 - Full Access</option>
                                <option value="Admin 2">Admin 2 - Limited Access</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="reset" class="btn-reset">
                            <i class="fas fa-redo"></i> Reset Form
                        </button>
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-user-plus"></i> Add Administrator
                        </button>
                    </div>
                </form>
            </div>

            <!-- Admin Accounts Section -->
            <div class="accounts-section">
                <!-- Admin 1 Table -->
                <div class="admin-list-card">
                    <div class="card-header admin1-header">
                        <div class="header-left">
                            <h2>Admin 1 Accounts</h2>
                        </div>
                        <span class="count-badge"><?php echo $admin1_result->num_rows; ?></span>
                    </div>
                    <div class="scrollable-table">
                        <?php if ($admin1_result->num_rows > 0): ?>
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th class="fullNameTable"><i class="fas fa-user"></i> Full Name</th>
                                        <th><i class="fas fa-envelope"></i> Email</th>
                                        <th class="actionTable">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Reset pointer for second iteration
                                    $admin1_result->data_seek(0);
                                    while ($row = $admin1_result->fetch_assoc()): ?>
                                        <?php
                                        $fullName = htmlspecialchars($row['first_name']);
                                        if (!empty($row['middle_name'])) {
                                            $fullName .= " " . htmlspecialchars($row['middle_name']);
                                        }
                                        $fullName .= " " . htmlspecialchars($row['last_name']);
                                        ?>
                                        <tr>
                                            <td data-label="Full Name">
                                                <div class="user-info">
                                                    <div class="user-avatar">
                                                        <?php
                                                        $profileImagePath = getProfileImagePath($row['profile_img']);
                                                        ?>
                                                        <img src="<?php echo htmlspecialchars($profileImagePath); ?>"
                                                            alt="<?php echo htmlspecialchars($fullName); ?>"
                                                            class="admin-avatar-img" onerror="this.src='assets/default.jpg'">
                                                    </div>
                                                    <span><?php echo $fullName; ?></span>
                                                </div>
                                            </td>
                                            <td data-label="Email"><?php echo htmlspecialchars($row['email']); ?></td>
                                            <td data-label="Action">
                                                <div class="action-buttons-group">
                                                    <button class="action-btn reset-btn"
                                                        onclick="resetAdminPassword(<?php echo intval($row['id']); ?>, '<?php echo htmlspecialchars($row['email']); ?>', 'Admin 1')"
                                                        aria-label="Reset password for <?php echo htmlspecialchars($row['email']); ?>">
                                                        <i class="fas fa-key"></i> Reset Password
                                                    </button>
                                                    <button class="action-btn remove-btn" type="button"
                                                        onclick="openRemoveModal(<?php echo intval($row['id']); ?>, '<?php echo htmlspecialchars($row['email']); ?>', 'Admin 1'); return false;"
                                                        aria-label="Remove Admin 1 <?php echo htmlspecialchars($row['email']); ?>">
                                                        <i class="fas fa-trash-alt"></i> Remove
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="no-records">
                                <i class="fas fa-user-slash"></i>
                                <p>No Admin 1 accounts found</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Admin 2 Table -->
                <div class="admin-list-card">
                    <div class="card-header admin2-header">
                        <div class="header-left">
                            <h2>Admin 2 Accounts</h2>
                        </div>
                        <span class="count-badge"><?php echo $admin2_result->num_rows; ?></span>
                    </div>
                    <div class="scrollable-table">
                        <?php if ($admin2_result->num_rows > 0): ?>
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-user"></i> Full Name</th>
                                        <th><i class="fas fa-envelope"></i> Email</th>
                                        <th class="actionTable">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Reset pointer for second iteration
                                    $admin2_result->data_seek(0);
                                    while ($row = $admin2_result->fetch_assoc()): ?>
                                        <?php
                                        $fullName = htmlspecialchars($row['first_name']);
                                        if (!empty($row['middle_name'])) {
                                            $fullName .= " " . htmlspecialchars($row['middle_name']);
                                        }
                                        $fullName .= " " . htmlspecialchars($row['last_name']);
                                        ?>
                                        <tr>
                                            <td data-label="Full Name">
                                                <div class="user-info">
                                                    <div class="user-avatar">
                                                        <?php
                                                        $profileImagePath = getProfileImagePath($row['profile_img']);
                                                        ?>
                                                        <img src="<?php echo htmlspecialchars($profileImagePath); ?>"
                                                            alt="<?php echo htmlspecialchars($fullName); ?>"
                                                            class="admin-avatar-img" onerror="this.src='assets/default.jpg'">
                                                    </div>
                                                    <span><?php echo $fullName; ?></span>
                                                </div>
                                            </td>
                                            <td data-label="Email"><?php echo htmlspecialchars($row['email']); ?></td>
                                            <td data-label="Action">
                                                <div class="action-buttons-group">
                                                    <button class="action-btn reset-btn"
                                                        onclick="resetAdminPassword(<?php echo intval($row['id']); ?>, '<?php echo htmlspecialchars($row['email']); ?>', 'Admin 2')"
                                                        aria-label="Reset password for <?php echo htmlspecialchars($row['email']); ?>">
                                                        <i class="fas fa-key"></i> Reset Password
                                                    </button>
                                                    <button class="action-btn remove-btn" type="button"
                                                        onclick="openRemoveModal(<?php echo intval($row['id']); ?>, '<?php echo htmlspecialchars($row['email']); ?>', 'Admin 2'); return false;"
                                                        aria-label="Remove Admin 2 <?php echo htmlspecialchars($row['email']); ?>">
                                                        <i class="fas fa-trash-alt"></i> Remove
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="no-records">
                                <i class="fas fa-user-slash"></i>
                                <p>No Admin 2 accounts found</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
        $stmt1->close();
        $stmt2->close();
        $conn->close();
        ?>
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

        // Copy password to clipboard
        function copyPassword() {
            const passwordElement = document.getElementById('generatedPassword');
            const password = passwordElement.textContent;

            navigator.clipboard.writeText(password).then(() => {
                // Show success feedback
                const copyBtn = event.target.closest('.copy-btn');
                const originalIcon = copyBtn.innerHTML;
                copyBtn.innerHTML = '<i class="fas fa-check"></i> Copied!';
                copyBtn.style.backgroundColor = '#27ae60';

                setTimeout(() => {
                    copyBtn.innerHTML = originalIcon;
                    copyBtn.style.backgroundColor = '';
                }, 2000);

                // Show toast notification
                Toastify({
                    text: "Password copied to clipboard!",
                    duration: 2000,
                    gravity: "top",
                    position: "right",
                    backgroundColor: "#27ae60",
                    className: "success"
                }).showToast();
            }).catch(err => {
                console.error('Failed to copy password:', err);
                Toastify({
                    text: "Failed to copy password",
                    duration: 2000,
                    gravity: "top",
                    position: "right",
                    backgroundColor: "#e74c3c",
                    className: "error"
                }).showToast();
            });
        }

        // Remove Admin Modal Functions
        function openRemoveModal(adminId, adminEmail, adminType) {
            // Prevent event propagation
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }

            document.getElementById('removeAdminId').value = adminId;
            document.getElementById('removeAdminType').value = adminType;
            document.getElementById('adminEmailDisplay').textContent = adminEmail;

            const modal = document.getElementById('removeAdminModal');
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';

            // Add a slight delay to ensure display is set before any other events
            setTimeout(function () {
                modal.classList.add('show');
            }, 10);
        }

        function closeRemoveModal() {
            const modal = document.getElementById('removeAdminModal');
            modal.classList.remove('show');
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }

        function submitRemoveForm() {
            const adminId = document.getElementById('removeAdminId').value;
            const adminType = document.getElementById('removeAdminType').value;

            if (!adminId || !adminType) {
                console.error('Admin ID or Type is missing');
                return;
            }

            // Create a new form and submit it
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'remove_admin.php';

            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'admin_id';
            idInput.value = adminId;

            const typeInput = document.createElement('input');
            typeInput.type = 'hidden';
            typeInput.name = 'admin_type';
            typeInput.value = adminType;

            form.appendChild(idInput);
            form.appendChild(typeInput);
            document.body.appendChild(form);
            form.submit();
        }

        // Close modal when clicking outside of it (on the backdrop)
        document.addEventListener('click', function (event) {
            const modal = document.getElementById('removeAdminModal');
            if (modal && modal.style.display === 'block' && event.target === modal) {
                closeRemoveModal();
            }
        }, false);

        // Close modal when pressing Escape key
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeRemoveModal();
            }
        });

        // Reset admin password function
        function resetAdminPassword(adminId, adminEmail, adminType) {
            if (!confirm('Are you sure you want to reset the password for ' + adminEmail + '? A new password will be generated and sent to their email.')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'reset_password');
            formData.append('admin_id', adminId);
            formData.append('admin_email', adminEmail);
            formData.append('admin_type', adminType);

            fetch('add_admin.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Toastify({
                            text: data.message,
                            duration: 4000,
                            gravity: "top",
                            position: "right",
                            backgroundColor: "#27ae60",
                            className: "success"
                        }).showToast();

                        // Show the new password in an alert
                        if (data.password) {
                            setTimeout(() => {
                                alert('New Password for ' + adminEmail + ':\n\n' + data.password + '\n\nPlease share this securely with the admin.');
                            }, 500);
                        }
                    } else {
                        Toastify({
                            text: "Error: " + data.message,
                            duration: 3000,
                            gravity: "top",
                            position: "right",
                            backgroundColor: "#e74c3c",
                            className: "error"
                        }).showToast();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Toastify({
                        text: "Failed to reset password",
                        duration: 3000,
                        gravity: "top",
                        position: "right",
                        backgroundColor: "#e74c3c",
                        className: "error"
                    }).showToast();
                });
        }

        // Display Toastify notifications
        <?php if ($message): ?>
            Toastify({
                text: "<?php echo strip_tags(str_replace(['<div class="message success">', '<div class="message error">', '<i class="fas fa-check-circle"></i>', '<i class="fas fa-exclamation-circle"></i>'], '', $message)); ?>",
                duration: 3000,
                gravity: "top",
                position: "right",
                backgroundColor: "<?php echo strpos($message, 'success') !== false ? 'var(--primary)' : 'var(--rejected)'; ?>",
                className: "<?php echo strpos($message, 'success') !== false ? 'success' : 'error'; ?>"
            }).showToast();
        <?php endif; ?>
    </script>
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

    <!-- Remove Admin Confirmation Modal -->
    <div id="removeAdminModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Remove Admin</h2>
                <span class="close-modal" onclick="closeRemoveModal(); return false;" role="button" tabindex="0"
                    aria-label="Close modal">&times;</span>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to remove this admin?</p>
                <p id="adminEmailDisplay" style="font-weight: bold; color: #e74c3c;"></p>
                <p style="font-size: 0.9em; color: #7f8c8d; margin-top: 10px;">This action cannot be undone.</p>
                <input type="hidden" id="removeAdminId" value="">
                <input type="hidden" id="removeAdminType" value="">
            </div>
            <div class="modal-footer">
                <button class="modal-btn cancel-btn" type="button"
                    onclick="closeRemoveModal(); return false;">Cancel</button>
                <button type="button" class="modal-btn confirm-btn" onclick="submitRemoveForm(); return false;">
                    <i class="fas fa-trash-alt"></i> Remove Admin
                </button>
            </div>
        </div>
    </div>
</body>

</html>