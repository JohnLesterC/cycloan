<?php
ob_start(); // Start output buffering
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Configure error logging for environment (safe defaults for localhost)
ini_set('log_errors', 1);
if (defined('APP_ENV') && APP_ENV === 'local') {
    // Local: log to project logs directory
    ini_set('error_log', __DIR__ . '/logs/error_log_local.txt');
} else {
    // Production: use system default or configured path
    // ini_set('error_log', '/home/u455107563/.logs/error_log_cycloan-cldd_com');
}

// CRITICAL DEBUG: Log that this file is being executed
error_log("===== ADMIN2 DASHBOARD LOADED (VSCODE VERSION) ===== " . date('Y-m-d H:i:s'), E_USER_NOTICE);

/**
 * @var mysqli $conn
 */
require "CYCLOAN_db.php";
// Check if NotificationManager exists before requiring it
if (file_exists('NotificationManager.php')) {
    require_once 'NotificationManager.php';
} else {
    error_log("NotificationManager.php not found - creating stub", E_USER_WARNING);
    // Create a stub class if the file doesn't exist
    if (!class_exists('NotificationManager')) {
        class NotificationManager
        {
            public static function send($message)
            {
                error_log("NotificationManager stub: $message", E_USER_NOTICE);
                return true;
            }
        }
    }
}
// require_once 'notification_manager.php';

// Define DEBUG_MODE constant - set to false in production
if (!defined('DEBUG_MODE')) {
    define('DEBUG_MODE', false);
}

// Log all POST requests for debugging
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("Admin2 Dashboard POST Request - Action: " . ($_POST['action'] ?? 'NONE') . ", Session ID: " . session_id() . ", Email: " . ($_SESSION['email'] ?? 'NONE'), E_USER_NOTICE);
    error_log("Admin2 Dashboard POST Data: " . json_encode([
        'action' => $_POST['action'] ?? null,
        'application_id' => $_POST['application_id'] ?? null,
        'has_csrf_token' => isset($_POST['csrf_token']),
        'post_keys' => array_keys($_POST)
    ]), E_USER_NOTICE);

    // CRITICAL DEBUG: Log exactly where we are in execution
    error_log("TRACE: POST request received, about to process handlers", E_USER_NOTICE);
}

// ============================================
// SECURITY: CSRF TOKEN MANAGEMENT
// ============================================

/**
 * Initialize CSRF token in session if not exists
 * Uses cryptographically secure random bytes for token generation
 * 
 * @return void
 */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Validate CSRF token against session token
 * Uses hash_equals() for timing-safe comparison
 * 
 * @param string $token The token to validate
 * @return bool True if token is valid, false otherwise
 */
function validateCSRFToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Retrieve CSRF token from session
 * Returns empty string if token doesn't exist
 * 
 * @return string The CSRF token or empty string
 */
function getCSRFToken()
{
    return $_SESSION['csrf_token'] ?? '';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/src/Exception.php';
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';

// ============================================
// SECURITY: INPUT SANITIZATION FUNCTIONS
// ============================================

/**
 * Sanitize string input - removes dangerous characters and limits length
 * Protects against XSS attacks by converting special characters to HTML entities
 * 
 * @param string $input The input string to sanitize
 * @param int|null $maxLength Maximum allowed length (optional)
 * @return string Sanitized string
 */
function sanitizeString($input, $maxLength = null)
{
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    if ($maxLength && strlen($input) > $maxLength) {
        $input = substr($input, 0, $maxLength);
    }
    return $input;
}

/**
 * Sanitize and validate email input
 * Uses PHP's built-in filter functions for proper validation
 * 
 * @param string $input The email address to sanitize
 * @return string Valid email or empty string if invalid
 */
function sanitizeEmail($input)
{
    $email = filter_var(trim($input), FILTER_SANITIZE_EMAIL);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return '';
    }
    return $email;
}

/**
 * Sanitize numeric input (integer)
 * Converts input to integer after filtering
 * 
 * @param mixed $input The input to sanitize
 * @return int Sanitized integer value
 */
function sanitizeInt($input)
{
    return (int) filter_var($input, FILTER_SANITIZE_NUMBER_INT);
}

/**
 * Sanitize numeric input (float)
 * Allows decimal points and minus signs
 * 
 * @param mixed $input The input to sanitize
 * @return float Sanitized float value
 */
function sanitizeFloat($input)
{
    return (float) filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
}

/**
 * Validate enum values using whitelist
 * Uses strict type comparison to prevent type juggling vulnerabilities
 * 
 * @param string $value The value to validate
 * @param array $allowedValues Array of allowed values
 * @return bool True if value is in whitelist, false otherwise
 */
function validateEnum($value, $allowedValues)
{
    return in_array($value, $allowedValues, true);
}

// ============================================
// ERROR HANDLING & LOGGING
// ============================================

/**
 * Enhanced error handler with structured logging
 * Provides consistent error logging across the application
 * 
 * @param string $level Error level (ERROR, WARNING, NOTICE, DEBUG)
 * @param string $message Error message
 * @param array $context Additional context (optional)
 * @return void
 */
function logError($level, $message, $context = [])
{
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' | ' . json_encode($context) : '';
    $logEntry = "[$timestamp] [$level] $message$contextStr";

    // Log to file
    error_log($logEntry, 3, 'logs/admin2_errors.log');

    // Also log to system log if level is WARNING or ERROR
    if (in_array($level, ['ERROR', 'WARNING'])) {
        error_log($logEntry);
    }
}

// ============================================
// SESSION & USER INITIALIZATION
// ============================================

$role = $_SESSION["role"] ?? null;
$id = $_SESSION["user_id"] ?? null;

// Validate session variables
if (empty($role) || empty($id)) {
    logError('ERROR', 'Invalid session state', ['role' => $role, 'id' => $id]);
    http_response_code(401);
    die('Unauthorized access');
}

// Fetch user profile image
$sql = "SELECT profile_img FROM $role WHERE id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    logError('ERROR', 'Failed to prepare profile image query', ['error' => $conn->error]);
    die('Database error');
}

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$profile_img = !empty($user['profile_img']) ? $user['profile_img'] : "default.png";
$current_page = basename($_SERVER['PHP_SELF']);

// Define mappings for action_type and module
$actionTypeMap = [
    'create' => 'Created',
    'update' => 'Updated',
    'delete' => 'Deleted',
    'login' => 'Logged In',
    'logout' => 'Logged Out',
    'view' => 'Viewed',
    'approve' => 'Approved',
    'reject' => 'Rejected',
    'send_reminder' => 'Send Reminder',
    'upload' => 'Uploaded',
    'status_update' => 'Status Updated',
    'comment' => 'Commented',
    'assign' => 'Assigned',
    'password_reset_requested' => 'Password Reset Request',
    'password_reset_completed' => 'Password Reset Completed'
];

$moduleMap = [
    'loan_application' => 'Loan Application Management',
    'user' => 'User Profile Management',
    'admin' => 'Admin Profile Management',
    'interest_rate' => 'Interest Rate Configuration',
    'payment' => 'Payment Processing',
    'document' => 'Document Management',
    'remarks' => 'Decision Notes',
    'payment_schedule' => 'Payment Schedule & Reminders',
    'loan_type' => 'Loan Type Configuration',
    'notification' => 'Notification System'
];

// Helper function to execute SELECT queries
/**
 * Execute SELECT database query with prepared statements
 * @param mysqli $conn Database connection
 * @param string $query SQL query with placeholders
 * @param string $types Parameter types (s=string, i=integer, d=double)
 * @param array $params Parameter values to bind
 * @return array Query results as associative array
 * @throws Exception On database error
 */
function executeQuery($conn, $query, $types = '', $params = [])
{
    if (!$conn) {
        logError('ERROR', 'Database connection is null');
        throw new Exception("Database connection failed");
    }

    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        logError('ERROR', 'Query preparation failed', [
            'query' => substr($query, 0, 150),
            'error' => $conn->error
        ]);
        throw new Exception("Query preparation failed: " . $conn->error);
    }

    if (!empty($types) && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        logError('ERROR', 'Query execution failed', [
            'query' => substr($query, 0, 150),
            'error' => $stmt->error
        ]);
        $stmt->close();
        throw new Exception("Query execution failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

// Helper function to execute INSERT/UPDATE/DELETE queries
/**
 * Execute INSERT/UPDATE/DELETE database query with prepared statements
 * @param mysqli $conn Database connection
 * @param string $query SQL query with placeholders
 * @param string $types Parameter types
 * @param array $params Parameter values to bind
 * @return int Number of affected rows, or 0 on error
 */
function executeUpdate($conn, $query, $types = '', $params = [])
{
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        logError('WARNING', 'Update query preparation failed', [
            'error' => $conn->error
        ]);
        error_log("EXECUTE_UPDATE_DEBUG: Prepare failed - " . $conn->error, E_USER_WARNING);
        return 0;
    }

    if (!empty($types) && !empty($params)) {
        error_log("EXECUTE_UPDATE_DEBUG: Binding params - types='$types', param_count=" . count($params), E_USER_NOTICE);
        for ($i = 0; $i < count($params); $i++) {
            $param = $params[$i];
            $type = $types[$i] ?? 'unknown';
            $displayValue = is_string($param) ? substr($param, 0, 50) : $param;
            error_log("EXECUTE_UPDATE_DEBUG: Param $i: type='$type', value='" . $displayValue . "'", E_USER_NOTICE);
        }
        $stmt->bind_param($types, ...$params);
    }

    $result = $stmt->execute();
    $affectedRows = $stmt->affected_rows;
    error_log("EXECUTE_UPDATE_DEBUG: Execute result=$result, affected_rows=$affectedRows", E_USER_NOTICE);
    $stmt->close();

    if (!$result) {
        logError('WARNING', 'Update query execution failed');
        error_log("EXECUTE_UPDATE_DEBUG: Execute failed", E_USER_WARNING);
    }

    return $result ? $affectedRows : 0;
}

// ============================================
// ENHANCED ERROR HANDLING & VALIDATION
// ============================================

/**
 * Send standardized API error response
 * @param string $message Error message to display
 * @param int $statusCode HTTP status code
 * @param array|null $details Additional debug details (shown only in DEBUG_MODE)
 * @return never Terminates execution with JSON response
 */
function handleApiError($message, $statusCode = 400, $details = null)
{
    $response = [
        'success' => false,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s'),
        'status_code' => $statusCode
    ];

    if ($details && defined('DEBUG_MODE') && DEBUG_MODE) {
        // $response['details'] = $details;
    }

    logError('API_ERROR', $message, ['status_code' => $statusCode]);
    ob_clean();
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response);
    exit;
}

/**
 * Send standardized API success response
 * @param mixed $data Data to include in response (optional)
 * @param string $message Success message
 * @return never Terminates execution with JSON response
 */
function handleApiSuccess($data = null, $message = 'Operation successful')
{
    ob_clean();
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');

    $response = [
        'success' => true,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];

    if ($data !== null) {
        $response['data'] = $data;
    }

    die(json_encode($response));
}

/**
 * Validate multiple conditions
 */
function validateConditions(array $conditions)
{
    foreach ($conditions as $condition => $errorMessage) {
        if (!$condition) {
            return ['valid' => false, 'message' => $errorMessage];
        }
    }
    return ['valid' => true];
}

/**
 * Sanitize array of inputs
 */
function sanitizeArray(array $inputs, $type = 'string')
{
    $sanitized = [];
    foreach ($inputs as $key => $value) {
        switch ($type) {
            case 'int':
                $sanitized[$key] = sanitizeInt($value);
                break;
            case 'float':
                $sanitized[$key] = sanitizeFloat($value);
                break;
            case 'email':
                $sanitized[$key] = sanitizeEmail($value);
                break;
            default:
                $sanitized[$key] = sanitizeString($value);
        }
    }
    return $sanitized;
}

/**
 * Log operation with context
 */
function logOperation($operation, $status, $context = '')
{
    $logEntry = date('Y-m-d H:i:s') . " | $operation | $status | $context";
    error_log($logEntry, 3, 'operations.log');
}

// Check session for AJAX requests

if (!isset($_SESSION['email']) || !isset($_SESSION['user_id'])) {
    if (isset($_GET['action']) && $_GET['action'] === 'get_loan_details') {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
        exit;
    }
    $_SESSION['error'] = 'Session expired. Please login again.';
    header("Location: index.php");
    exit;
}

// Security: Validate session variables
if (empty($_SESSION['email'])) {
    error_log("Admin2 Dashboard: Session email is empty! Session contents: " . json_encode($_SESSION), E_USER_WARNING);
    $_SESSION['error'] = 'Invalid session. Please login again.';
    header("Location: index.php");
    exit;
}

// Use email from session directly (trim and lowercase for consistency)
$adminEmail = trim(strtolower($_SESSION['email']));

// Debug logging for admin verification
error_log("Admin2 Dashboard: Session details - email from session: '{$_SESSION['email']}', normalized to: '$adminEmail'", E_USER_NOTICE);
error_log("Admin2 Dashboard: Attempting to verify admin with email: $adminEmail", E_USER_NOTICE);

// Determine admin role and verify user is Admin 2
$query = "SELECT id, first_name, last_name, email FROM admin2 WHERE LOWER(TRIM(email)) = LOWER(TRIM(?)) LIMIT 1";
error_log("Admin2 Dashboard: Executing query: $query with param: '$adminEmail'", E_USER_NOTICE);

$stmt = $conn->prepare($query);
if ($stmt === false) {
    error_log("Security: Failed to prepare admin verification statement - Error: " . $conn->error, E_USER_WARNING);
    $_SESSION['error'] = 'Database error. Please try again later.';
    header("Location: index.php");
    exit;
}
$stmt->bind_param("s", $adminEmail);
if (!$stmt->execute()) {
    error_log("Security: Failed to execute admin verification for email: $adminEmail - Error: " . $stmt->error, E_USER_WARNING);
    $_SESSION['error'] = 'Database error. Please try again later.';
    $stmt->close();
    header("Location: index.php");
    exit;
}
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$stmt->close();

error_log("Admin2 Dashboard: Query result - Found admin: " . ($admin ? "YES (ID: {$admin['id']}, Email: {$admin['email']})" : "NO"), E_USER_NOTICE);

if (!$admin) {
    error_log("Admin2 Dashboard: Admin not found in admin2 table for email: $adminEmail. Checking if this is a POST request with action.", E_USER_WARNING);
    if (isset($_POST['action']) && isset($_POST['csrf_token'])) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized access. Admin 2 role required.',
            'debug_email' => $adminEmail,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
    $_SESSION['error'] = 'Unauthorized access. Admin 2 role required.';
    header("Location: index.php");
    exit;
}

error_log("Admin2 Dashboard: Admin verified successfully - ID: {$admin['id']}, Email: {$admin['email']}", E_USER_NOTICE);

$adminId = $admin['id'];
$adminName = trim($admin['first_name'] . ' ' . $admin['last_name']);
$adminRole = 'Admin2';

// Global flag to track whether a POST action was handled
// This prevents the fallback 'Invalid action' block from firing after a successful handler
if (!isset($ACTION_HANDLED)) {
    $ACTION_HANDLED = false;
}

// Set session role explicitly
$_SESSION['role'] = 'admin2';
$_SESSION['admin_id'] = $adminId;  // Also store admin_id in session for reference

/**
 * Get a user-friendly description for a document based on its name or type
 */
function getDocumentDescription($documentName, $documentTypeId = null)
{
    $descriptions = [
        'valid_id' => 'Government issued identification document',
        'business_permit' => 'Business permit or license document',
        'income_certificate' => 'Income verification document',
        'bank_statement' => 'Bank account statement for financial verification',
        'employment_certificate' => 'Employment verification document',
        'tax_return' => 'Income tax return document',
        'property_documents' => 'Property ownership verification documents',
        'credit_history' => 'Credit history and score verification',
        'identification' => 'Valid government-issued identification',
        'financial' => 'Financial documentation and statements',
        'employment' => 'Employment and income verification',
        'business' => 'Business registration and permit documents',
        'collateral' => 'Collateral and asset documentation',
        'signature' => 'Signature card and authorization forms'
    ];

    $docKey = strtolower(str_replace([' ', '-', '_'], '_', $documentName));
    foreach ($descriptions as $key => $description) {
        if (strpos($docKey, $key) !== false) {
            return $description;
        }
    }

    // Default description based on document name
    return ucfirst(strtolower($documentName)) . ' document for loan application';
}

// ============================================
// HANDLE POST ACTIONS - PRIORITY HANDLERS
// ============================================

// CRITICAL DEBUG: Log before checking update_status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    error_log("TRACE: About to check update_status handler. Action = " . $_POST['action'] . ", Has app_id = " . (isset($_POST['application_id']) ? 'YES' : 'NO') . ", ACTION_HANDLED = " . ($ACTION_HANDLED ? 'TRUE' : 'FALSE'), E_USER_NOTICE);

    // ADDITIONAL DEBUG: Log specific checks for queue handler
    if ($_POST['action'] === 'check_queue_status') {
        error_log("🔥 QUEUE DEBUG: Action matches check_queue_status, checking application_id...", E_USER_NOTICE);
        error_log("🔥 QUEUE DEBUG: Application ID isset? " . (isset($_POST['application_id']) ? 'YES' : 'NO'), E_USER_NOTICE);
        if (isset($_POST['application_id'])) {
            error_log("🔥 QUEUE DEBUG: Application ID value: " . $_POST['application_id'], E_USER_NOTICE);
        }
    }
}

// Handle update_status POST action first (priority)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status' && isset($_POST['application_id'])) {
    // IMMEDIATELY mark as handled to prevent secondary handler
    $ACTION_HANDLED = true;

    error_log("🔥🔥🔥 VSCODE VERSION - Admin2: update_status handler PRIORITY - Processing submission 🔥🔥🔥", E_USER_NOTICE);

    try {
        ob_clean();
        header('Content-Type: application/json');

        // Security: CSRF Token Validation
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            error_log("Admin2 PRIORITY: CSRF validation FAILED. Token in POST: " . ($_POST['csrf_token'] ?? 'NONE') . ", Session token exists: " . (isset($_SESSION['csrf_token']) ? 'YES' : 'NO'), E_USER_WARNING);
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Security validation failed. Please refresh and try again.']);
            exit;
        }

        error_log("Admin2 PRIORITY: CSRF validation PASSED", E_USER_NOTICE);

        // Get and sanitize inputs
        $applicationId = isset($_POST['application_id']) ? trim($_POST['application_id']) : '';
        $preApprovalStatus = isset($_POST['pre_approval_status']) ? trim($_POST['pre_approval_status']) : '';
        $approvalReason = isset($_POST['approval_reason']) ? trim($_POST['approval_reason']) : '';

        // Validate inputs
        if (empty($applicationId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Application ID is required']);
            exit;
        }

        // Validate pre-approval status
        $validStatuses = ['Pending', 'Approved', 'Rejected'];
        if (!in_array($preApprovalStatus, $validStatuses)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid pre-approval status']);
            exit;
        }

        // Update loan_applications table
        $updateQuery = "UPDATE loan_applications SET pre_approval_status = ? WHERE application_id = ? LIMIT 1";
        $stmt = $conn->prepare($updateQuery);
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }

        $stmt->bind_param("ss", $preApprovalStatus, $applicationId);
        if (!$stmt->execute()) {
            throw new Exception("Update failed: " . $stmt->error);
        }

        $stmt->close();

        // AUTO-APPROVE: If pre-approval is "Approved", auto-approve all pending documents
        $autoApprovedDocs = 0;
        if ($preApprovalStatus === 'Approved') {
            error_log("🎯 Pre-approval is 'Approved' - Auto-approving all pending documents for application $applicationId", E_USER_NOTICE);

            // Get all pending documents for this application
            $getPendingDocsQuery = "SELECT document_id, document_type_id FROM documents WHERE application_id = ? AND status = 'Pending' LIMIT 100";
            $pendingStmt = $conn->prepare($getPendingDocsQuery);
            if ($pendingStmt) {
                $pendingStmt->bind_param("s", $applicationId);
                $pendingStmt->execute();
                $pendingResult = $pendingStmt->get_result();

                // Auto-approve each pending document
                while ($doc = $pendingResult->fetch_assoc()) {
                    $docId = $doc['document_id'];
                    $docTypeId = $doc['document_type_id'];

                    // Update document status to Approved
                    $approveDocQuery = "UPDATE documents SET status = 'Approved', status_updated_at = NOW() WHERE document_id = ? AND application_id = ? LIMIT 1";
                    $approveStmt = $conn->prepare($approveDocQuery);
                    if ($approveStmt) {
                        $approveStmt->bind_param("is", $docId, $applicationId);
                        if ($approveStmt->execute()) {
                            // Create audit trail for auto-approval
                            $auditQuery = "INSERT INTO document_audit 
                                          (document_id, application_id, document_type_id, old_status, new_status, admin_id, admin_name, admin_role, notes) 
                                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                            $auditStmt = $conn->prepare($auditQuery);
                            if ($auditStmt) {
                                $oldStatus = 'Pending';
                                $newStatus = 'Approved';
                                $auditNotes = 'Auto-approved due to pre-approval status change to Approved';
                                $auditStmt->bind_param(
                                    "isississs",
                                    $docId,
                                    $applicationId,
                                    $docTypeId,
                                    $oldStatus,
                                    $newStatus,
                                    $adminId,
                                    $adminName,
                                    $adminRole,
                                    $auditNotes
                                );
                                $auditStmt->execute();
                                $auditStmt->close();
                            }
                            $autoApprovedDocs++;
                            error_log("✅ Auto-approved document $docId for application $applicationId", E_USER_NOTICE);
                        }
                        $approveStmt->close();
                    }
                }
                $pendingStmt->close();
            }

            error_log("✅ Successfully auto-approved $autoApprovedDocs documents for application $applicationId", E_USER_NOTICE);
        }

        // Add remark if provided
        if (!empty($approvalReason)) {
            $remarkQuery = "INSERT INTO remarks (application_id, remark, created_by, created_at) VALUES (?, ?, ?, NOW())";
            $remarkStmt = $conn->prepare($remarkQuery);
            if ($remarkStmt) {
                $remarkStmt->bind_param("sss", $applicationId, $approvalReason, $adminName);
                $remarkStmt->execute();
                $remarkStmt->close();
            }
        }

        // Log the activity
        logActivity(
            $conn,
            $adminId,
            'Admin2',
            'update',
            'loan_application',
            "Updated pre-approval status to '$preApprovalStatus' for application $applicationId" . ($autoApprovedDocs > 0 ? " and auto-approved $autoApprovedDocs documents" : ""),
            $applicationId
        );

        // CREATE NOTIFICATION FOR PRE-APPROVAL STATUS CHANGE
        try {
            // Get user ID for this application
            $userQuery = "SELECT user_id FROM loan_applications WHERE application_id = ? LIMIT 1";
            $userStmt = $conn->prepare($userQuery);
            if ($userStmt) {
                $userStmt->bind_param("s", $applicationId);
                $userStmt->execute();
                $userResult = $userStmt->get_result();
                $userData = $userResult->fetch_assoc();
                $userId = $userData['user_id'] ?? null;
                $userStmt->close();

                if ($userId) {
                    // Prepare notification based on pre-approval status
                    $notificationTitle = '';
                    $notificationMessage = '';
                    $notificationType = 'status';
                    $notificationPriority = 'high';

                    if ($preApprovalStatus === 'Approved') {
                        $notificationTitle = '✅ Pre-Approval Approved';
                        $notificationMessage = 'Congratulations! Your loan application has been pre-approved. ' . ($autoApprovedDocs > 0 ? "All $autoApprovedDocs submitted documents have been approved. " : '') . 'You can proceed with the next steps. ' . (!empty($approvalReason) ? "Remark: $approvalReason" : '');
                        $notificationPriority = 'high';
                    } elseif ($preApprovalStatus === 'Rejected') {
                        $notificationTitle = '❌ Pre-Approval Rejected';
                        $notificationMessage = 'Unfortunately, your pre-approval application has been rejected. ' . (!empty($approvalReason) ? "Reason: $approvalReason. " : '') . 'Please contact support for more information.';
                        $notificationPriority = 'high';
                    } elseif ($preApprovalStatus === 'Pending') {
                        $notificationTitle = '⏳ Pre-Approval Status: Pending';
                        $notificationMessage = 'Your pre-approval application is pending review. ' . (!empty($approvalReason) ? "Note: $approvalReason. " : '') . 'We will update you shortly.';
                        $notificationPriority = 'normal';
                    }

                    // Create the notification using NotificationManager
                    if ($notificationTitle && isset($notificationManager)) {
                        $notificationManager->createNotification(
                            $userId,
                            $notificationType,
                            $notificationTitle,
                            $notificationMessage,
                            $notificationPriority
                        );
                        error_log("✅ PRE-APPROVAL NOTIFICATION: Created notification for user $userId - Pre-approval status changed to $preApprovalStatus", E_USER_NOTICE);
                    }
                } else {
                    error_log("⚠️ PRE-APPROVAL NOTIFICATION: No user_id found for application $applicationId", E_USER_WARNING);
                }

                // CREATE ADMIN1 NOTIFICATION (Bidirectional Communication)
                try {
                    // Get admin1 user from admin_accounts table
                    $admin1Query = "SELECT id, admin_name FROM admin_accounts WHERE admin_type = 'Admin 1' LIMIT 1";
                    $admin1Result = $conn->query($admin1Query);

                    if ($admin1Result && $admin1User = $admin1Result->fetch_assoc()) {
                        // Prepare admin1-specific notification
                        $admin1NotificationTitle = '';
                        $admin1NotificationMessage = '';
                        $admin1NotificationType = 'status';
                        $admin1Priority = 'normal';

                        if ($preApprovalStatus === 'Approved') {
                            $admin1NotificationTitle = '✅ Pre-Approval Decision: Approved';
                            $admin1NotificationMessage = "Application #$applicationId has been pre-approved. " . ($autoApprovedDocs > 0 ? "$autoApprovedDocs documents were auto-approved. " : '') . 'Ready for credit investigation. ' . (!empty($approvalReason) ? "Basis: $approvalReason" : '');
                            $admin1Priority = 'normal';
                        } elseif ($preApprovalStatus === 'Rejected') {
                            $admin1NotificationTitle = '❌ Pre-Approval Decision: Rejected';
                            $admin1NotificationMessage = "Application #$applicationId has been rejected during pre-approval. " . (!empty($approvalReason) ? "Reason: $approvalReason. " : '') . 'No further action required.';
                            $admin1Priority = 'low';
                        } elseif ($preApprovalStatus === 'Pending') {
                            $admin1NotificationTitle = '⏳ Pre-Approval Status Updated';
                            $admin1NotificationMessage = "Application #$applicationId pre-approval status set to pending review. " . (!empty($approvalReason) ? "Note: $approvalReason. " : '') . 'Awaiting final decision.';
                            $admin1Priority = 'low';
                        }

                        // Create notification for admin1
                        if ($admin1NotificationTitle && isset($notificationManager)) {
                            $notificationManager->createNotification(
                                $admin1User['id'],
                                $admin1NotificationType,
                                $admin1NotificationTitle,
                                $admin1NotificationMessage,
                                $admin1Priority
                            );
                            error_log("✅ ADMIN1 NOTIFICATION: Created notification for admin1 (ID: {$admin1User['id']}) - Pre-approval status changed to $preApprovalStatus for app $applicationId", E_USER_NOTICE);
                        }
                    } else {
                        error_log("⚠️ ADMIN1 NOTIFICATION: No Admin 1 user found in admin_accounts table", E_USER_WARNING);
                    }
                } catch (Exception $admin1NotifEx) {
                    error_log("❌ ADMIN1 NOTIFICATION ERROR: Failed to create admin1 notification for app $applicationId: " . $admin1NotifEx->getMessage(), E_USER_WARNING);
                    // Don't fail the entire operation if admin1 notification creation fails
                }
            }
        } catch (Exception $notifEx) {
            error_log("❌ PRE-APPROVAL NOTIFICATION ERROR: Failed to create pre-approval notification for app $applicationId: " . $notifEx->getMessage(), E_USER_WARNING);
            // Don't fail the entire operation if notification creation fails
        }

        // Send email for all preapproval status updates
        $sendEmail = true;

        if ($sendEmail) {
            // Send consolidated email with status update and reason
            $emailUpdates = [
                'pre_approval_status' => $preApprovalStatus,
                'admin_name' => $adminName,
                'admin_updated_at' => date('Y-m-d H:i:s'),
                'approval_reason' => $approvalReason
            ];

            try {
                $emailSent = sendConsolidatedUpdateEmail($conn, $applicationId, $emailUpdates);
                error_log("Email notification " . ($emailSent ? "sent successfully" : "failed") . " for application $applicationId", E_USER_NOTICE);
            } catch (Exception $emailEx) {
                error_log("Email sending failed for application $applicationId: " . $emailEx->getMessage(), E_USER_WARNING);
            }
        } else {
            error_log("📧 Email notification skipped (this shouldn't happen as sendEmail is always true now)", E_USER_NOTICE);
        }

        // Send success response
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => "Application status updated to $preApprovalStatus successfully!" . ($autoApprovedDocs > 0 ? " Auto-approved $autoApprovedDocs documents." : ""),
            'data' => [
                'application_id' => $applicationId,
                'pre_approval_status' => $preApprovalStatus,
                'auto_approved_documents' => $autoApprovedDocs
            ]
        ]);
        exit;

    } catch (Exception $e) {
        error_log("Admin2 update_status error: " . $e->getMessage(), E_USER_WARNING);
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error updating status: ' . $e->getMessage()
        ]);
        exit;
    }
}

// Handle check_queue_status POST action (priority)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_queue_status' && isset($_POST['application_id'])) {
    // IMMEDIATELY mark as handled to prevent secondary handler
    $ACTION_HANDLED = true;

    error_log("🚀🚀🚀 VSCODE VERSION - Admin2: check_queue_status handler PRIORITY - Processing request 🚀🚀🚀", E_USER_NOTICE);

    try {
        ob_clean();
        header('Content-Type: application/json');

        // Security: CSRF Token Validation
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            error_log("Admin2 check_queue_status PRIORITY: CSRF validation FAILED. Token in POST: " . ($_POST['csrf_token'] ?? 'NONE') . ", Session token exists: " . (isset($_SESSION['csrf_token']) ? 'YES' : 'NO'), E_USER_WARNING);
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Security validation failed. Please refresh and try again.']);
            exit;
        }

        error_log("Admin2 check_queue_status PRIORITY: CSRF validation PASSED", E_USER_NOTICE);

        // Input validation
        $applicationId = sanitizeString($_POST['application_id'], 50);

        if (empty($applicationId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'queue_count' => 0, 'message' => 'Invalid application ID']);
            exit;
        }

        // Check if there are queued changes in session
        $queueCount = 0;
        $queueDetails = [];
        if (
            isset($_SESSION['document_changes_queue'][$applicationId]) &&
            isset($_SESSION['document_changes_queue'][$applicationId]['changes']) &&
            is_array($_SESSION['document_changes_queue'][$applicationId]['changes'])
        ) {
            $queueData = $_SESSION['document_changes_queue'][$applicationId];
            $queueCount = count($queueData['changes']);

            // Prepare queue preview for display
            $queueDetails = [
                'total_changes' => $queueCount,
                'first_change_time' => $queueData['first_change_time'] ?? 'Unknown',
                'last_update_time' => $queueData['last_update_time'] ?? 'Unknown',
                'changes_preview' => $queueData['changes'] // Show ALL changes, not limited to 3
            ];
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'queue_count' => $queueCount,
            'has_queue' => $queueCount > 0,
            'queue_details' => $queueDetails,
            'message' => $queueCount > 0 ? "$queueCount document change" . ($queueCount === 1 ? '' : 's') . " queued" : "No queued changes"
        ]);
        exit;

    } catch (Exception $e) {
        error_log("Admin2 check_queue_status error: " . $e->getMessage(), E_USER_WARNING);
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'queue_count' => 0,
            'message' => 'Error checking queue status: ' . $e->getMessage()
        ]);
        exit;
    }
}

// Handle test_queue_creation POST action (for testing purposes)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'test_queue_creation' && isset($_POST['application_id'])) {
    try {
        $ACTION_HANDLED = true;
        $applicationId = trim($_POST['application_id']);

        error_log("Admin2 test_queue_creation PRIORITY: Processing for application ID: " . $applicationId, E_USER_NOTICE);

        // Get user ID for the application
        $userQuery = "SELECT user_id FROM loan_applications WHERE application_id = ? LIMIT 1";
        $userResult = executeQuery($conn, $userQuery, "s", [$applicationId]);
        $userId = !empty($userResult) ? $userResult[0]['user_id'] : null;

        // Initialize queue if not exists
        if (!isset($_SESSION['document_changes_queue'][$applicationId])) {
            $_SESSION['document_changes_queue'][$applicationId] = [
                'changes' => [],
                'user_id' => $userId,
                'first_change_time' => date('F j, Y \a\t g:i A'),
                'last_update_time' => date('F j, Y \a\t g:i A'),
                'all_documents' => []
            ];
            error_log("Admin2 test_queue_creation: Initialized new queue for app $applicationId", E_USER_NOTICE);
        }

        // Create enhanced realistic test data for document changes
        $testDocuments = [
            [
                'document_id' => 999901,
                'document_name' => 'Valid ID',
                'document_type_id' => 1,
                'previous_status' => 'Pending',
                'old_status' => 'Pending',
                'new_status' => 'Approved',
                'status' => 'Approved',
                'rejection_reason' => null,
                'rejection_notes' => null,
                'has_rejection_reason' => false,
                'admin_name' => $adminName,
                'admin_id' => $adminId,
                'admin_role' => 'Admin2',
                'status_updated_at' => date('Y-m-d H:i:s'),
                'formatted_time' => date('F j, Y \a\t g:i A'),
                'unix_timestamp' => time(),
                'updated_at' => date('Y-m-d H:i:s'),
                'description' => 'Government issued identification document',
                'status_icon' => '✅',
                'status_color' => '#ffffff',
                'status_bg_color' => 'linear-gradient(135deg, #4caf50, #66bb6a)',
                'border_color' => '#4caf50',
                'change_type' => 'test_data',
                'is_new_change' => true,
                'processed' => false
            ],
            [
                'document_id' => 999902,
                'document_name' => 'Business Permit',
                'document_type_id' => 2,
                'previous_status' => 'Pending',
                'old_status' => 'Pending',
                'new_status' => 'Rejected',
                'status' => 'Rejected',
                'rejection_reason' => 'The document image is unclear. Please provide a clearer copy with all text readable.',
                'rejection_notes' => 'The document image is unclear. Please provide a clearer copy with all text readable.',
                'has_rejection_reason' => true,
                'admin_name' => $adminName,
                'admin_id' => $adminId,
                'admin_role' => 'Admin2',
                'status_updated_at' => date('Y-m-d H:i:s'),
                'formatted_time' => date('F j, Y \a\t g:i A'),
                'unix_timestamp' => time(),
                'updated_at' => date('Y-m-d H:i:s'),
                'description' => 'Business permit or license document',
                'status_icon' => '❌',
                'status_color' => '#ffffff',
                'status_bg_color' => 'linear-gradient(135deg, #f44336, #ef5350)',
                'border_color' => '#f44336',
                'change_type' => 'test_data',
                'is_new_change' => true,
                'processed' => false
            ],
            [
                'document_id' => 999903,
                'document_name' => 'Income Certificate',
                'document_type_id' => 3,
                'previous_status' => 'Pending',
                'old_status' => 'Pending',
                'new_status' => 'Approved',
                'status' => 'Approved',
                'rejection_reason' => null,
                'rejection_notes' => null,
                'has_rejection_reason' => false,
                'admin_name' => $adminName,
                'admin_id' => $adminId,
                'admin_role' => 'Admin2',
                'status_updated_at' => date('Y-m-d H:i:s'),
                'formatted_time' => date('F j, Y \a\t g:i A'),
                'unix_timestamp' => time(),
                'updated_at' => date('Y-m-d H:i:s'),
                'description' => 'Income verification document',
                'status_icon' => '✅',
                'status_color' => '#ffffff',
                'status_bg_color' => 'linear-gradient(135deg, #4caf50, #66bb6a)',
                'border_color' => '#4caf50',
                'change_type' => 'test_data',
                'is_new_change' => true,
                'processed' => false
            ],
            [
                'document_id' => 999904,
                'document_name' => 'Bank Statement',
                'document_type_id' => 4,
                'previous_status' => 'Pending',
                'old_status' => 'Pending',
                'new_status' => 'Rejected',
                'status' => 'Rejected',
                'rejection_reason' => 'Document has expired. Please submit a valid and current document.',
                'rejection_notes' => 'Document has expired. Please submit a valid and current document.',
                'has_rejection_reason' => true,
                'admin_name' => $adminName,
                'admin_id' => $adminId,
                'admin_role' => 'Admin2',
                'status_updated_at' => date('Y-m-d H:i:s'),
                'formatted_time' => date('F j, Y \a\t g:i A'),
                'unix_timestamp' => time(),
                'updated_at' => date('Y-m-d H:i:s'),
                'description' => 'Bank account statement for financial verification',
                'status_icon' => '❌',
                'status_color' => '#ffffff',
                'status_bg_color' => 'linear-gradient(135deg, #f44336, #ef5350)',
                'border_color' => '#f44336',
                'change_type' => 'test_data',
                'is_new_change' => true,
                'processed' => false
            ],
            [
                'document_id' => 999905,
                'document_name' => 'Employment Certificate',
                'document_type_id' => 5,
                'previous_status' => 'Pending',
                'old_status' => 'Pending',
                'new_status' => 'Approved',
                'status' => 'Approved',
                'rejection_reason' => null,
                'rejection_notes' => null,
                'has_rejection_reason' => false,
                'admin_name' => $adminName,
                'admin_id' => $adminId,
                'admin_role' => 'Admin2',
                'status_updated_at' => date('Y-m-d H:i:s'),
                'formatted_time' => date('F j, Y \a\t g:i A'),
                'unix_timestamp' => time(),
                'updated_at' => date('Y-m-d H:i:s'),
                'description' => 'Employment verification document',
                'status_icon' => '✅',
                'status_color' => '#ffffff',
                'status_bg_color' => 'linear-gradient(135deg, #4caf50, #66bb6a)',
                'border_color' => '#4caf50',
                'change_type' => 'test_data',
                'is_new_change' => true,
                'processed' => false
            ],
            [
                'document_id' => 999906,
                'document_name' => 'Tax Return',
                'document_type_id' => 6,
                'previous_status' => 'Pending',
                'old_status' => 'Pending',
                'new_status' => 'Rejected',
                'status' => 'Rejected',
                'rejection_reason' => 'Tax return is missing required signatures. Please submit a complete document.',
                'rejection_notes' => 'Tax return is missing required signatures. Please submit a complete document.',
                'has_rejection_reason' => true,
                'admin_name' => $adminName,
                'admin_id' => $adminId,
                'admin_role' => 'Admin2',
                'status_updated_at' => date('Y-m-d H:i:s'),
                'formatted_time' => date('F j, Y \a\t g:i A'),
                'unix_timestamp' => time(),
                'updated_at' => date('Y-m-d H:i:s'),
                'description' => 'Income tax return document',
                'status_icon' => '❌',
                'status_color' => '#ffffff',
                'status_bg_color' => 'linear-gradient(135deg, #f44336, #ef5350)',
                'border_color' => '#f44336',
                'change_type' => 'test_data',
                'is_new_change' => true,
                'processed' => false
            ],
            [
                'document_id' => 999907,
                'document_name' => 'Property Documents',
                'document_type_id' => 7,
                'previous_status' => 'Pending',
                'old_status' => 'Pending',
                'new_status' => 'Approved',
                'status' => 'Approved',
                'rejection_reason' => null,
                'rejection_notes' => null,
                'has_rejection_reason' => false,
                'admin_name' => $adminName,
                'admin_id' => $adminId,
                'admin_role' => 'Admin2',
                'status_updated_at' => date('Y-m-d H:i:s'),
                'formatted_time' => date('F j, Y \a\t g:i A'),
                'unix_timestamp' => time(),
                'updated_at' => date('Y-m-d H:i:s'),
                'description' => 'Property ownership verification documents',
                'status_icon' => '✅',
                'status_color' => '#ffffff',
                'status_bg_color' => 'linear-gradient(135deg, #4caf50, #66bb6a)',
                'border_color' => '#4caf50',
                'change_type' => 'test_data',
                'is_new_change' => true,
                'processed' => false
            ],
            [
                'document_id' => 999908,
                'document_name' => 'Credit History',
                'document_type_id' => 8,
                'previous_status' => 'Pending',
                'old_status' => 'Pending',
                'new_status' => 'Rejected',
                'status' => 'Rejected',
                'rejection_reason' => 'Credit report is outdated. Please provide a current report within 30 days.',
                'rejection_notes' => 'Credit report is outdated. Please provide a current report within 30 days.',
                'has_rejection_reason' => true,
                'admin_name' => $adminName,
                'admin_id' => $adminId,
                'admin_role' => 'Admin2',
                'status_updated_at' => date('Y-m-d H:i:s'),
                'formatted_time' => date('F j, Y \a\t g:i A'),
                'unix_timestamp' => time(),
                'updated_at' => date('Y-m-d H:i:s'),
                'description' => 'Credit history and score verification',
                'status_icon' => '❌',
                'status_color' => '#ffffff',
                'status_bg_color' => 'linear-gradient(135deg, #f44336, #ef5350)',
                'border_color' => '#f44336',
                'change_type' => 'test_data',
                'is_new_change' => true,
                'processed' => false
            ]
        ];

        // Add multiple random test documents to queue (2-4 documents per test)
        $numDocsToAdd = rand(2, 4); // Random between 2-4 documents
        $addedDocs = [];

        for ($i = 0; $i < $numDocsToAdd; $i++) {
            $testDoc = $testDocuments[array_rand($testDocuments)];

            // Make sure we don't add the same document twice in one test
            $attempts = 0;
            while (in_array($testDoc['document_id'], $addedDocs) && $attempts < 10) {
                $testDoc = $testDocuments[array_rand($testDocuments)];
                $attempts++;
            }

            $addedDocs[] = $testDoc['document_id'];

            // Add admin and timestamp info
            $testDoc['admin_name'] = $_SESSION['admin_name'] ?? 'Test Admin';
            $testDoc['admin_id'] = $_SESSION['admin_id'] ?? 1;
            $testDoc['status_updated_at'] = date('Y-m-d H:i:s');
            $testDoc['formatted_time'] = date('F j, Y \a\t g:i A');
            $testDoc['test_entry'] = true;

            // Check if document already exists in queue, update it
            $existingIndex = null;
            foreach ($_SESSION['document_changes_queue'][$applicationId]['changes'] as $index => $change) {
                if ($change['document_id'] == $testDoc['document_id']) {
                    $existingIndex = $index;
                    break;
                }
            }

            if ($existingIndex !== null) {
                $_SESSION['document_changes_queue'][$applicationId]['changes'][$existingIndex] = $testDoc;
                error_log("Admin2 test_queue_creation: Updated existing test document {$testDoc['document_id']}", E_USER_NOTICE);
            } else {
                $_SESSION['document_changes_queue'][$applicationId]['changes'][] = $testDoc;
                error_log("Admin2 test_queue_creation: Added new test document {$testDoc['document_id']}", E_USER_NOTICE);
            }
        }

        // Update comprehensive queue metadata and statistics
        $_SESSION['document_changes_queue'][$applicationId]['last_update_time'] = date('F j, Y \a\t g:i A');
        $_SESSION['document_changes_queue'][$applicationId]['formatted_last_time'] = date('F j, Y \a\t g:i A');
        $_SESSION['document_changes_queue'][$applicationId]['last_timestamp'] = time();
        $_SESSION['document_changes_queue'][$applicationId]['admin_name'] = $_SESSION['admin_name'] ?? 'Test Admin';

        // Recalculate comprehensive queue statistics
        $queueChanges = $_SESSION['document_changes_queue'][$applicationId]['changes'];
        $queueCount = count($queueChanges);
        $approvedCount = 0;
        $rejectedCount = 0;
        $pendingCount = 0;
        $hasRejections = false;
        $hasApprovals = false;

        foreach ($queueChanges as $change) {
            $status = $change['new_status'] ?? $change['status'];
            if ($status === 'Approved') {
                $approvedCount++;
                $hasApprovals = true;
            } elseif ($status === 'Rejected') {
                $rejectedCount++;
                $hasRejections = true;
            } elseif ($status === 'Pending') {
                $pendingCount++;
            }
        }

        // Update enhanced statistics
        $_SESSION['document_changes_queue'][$applicationId]['total_changes'] = $queueCount;
        $_SESSION['document_changes_queue'][$applicationId]['approved_count'] = $approvedCount;
        $_SESSION['document_changes_queue'][$applicationId]['rejected_count'] = $rejectedCount;
        $_SESSION['document_changes_queue'][$applicationId]['pending_count'] = $pendingCount;
        $_SESSION['document_changes_queue'][$applicationId]['has_rejections'] = $hasRejections;
        $_SESSION['document_changes_queue'][$applicationId]['has_approvals'] = $hasApprovals;
        $_SESSION['document_changes_queue'][$applicationId]['queue_status'] = 'active';

        $queueCount = count($_SESSION['document_changes_queue'][$applicationId]['changes']);

        error_log("ENHANCED_TEST_QUEUE: Created test entry for app $applicationId - Total: $queueCount, Approved: $approvedCount, Rejected: $rejectedCount, Pending: $pendingCount", E_USER_NOTICE);

        echo json_encode([
            'success' => true,
            'message' => 'Test document change added to queue',
            'queue_count' => $queueCount,
            'queue_stats' => [
                'total_changes' => $queueCount,
                'approved_count' => $approvedCount,
                'rejected_count' => $rejectedCount,
                'pending_count' => $pendingCount,
                'has_rejections' => $hasRejections,
                'has_approvals' => $hasApprovals,
                'last_update' => $_SESSION['document_changes_queue'][$applicationId]['formatted_last_time']
            ],
            'test_data' => [
                'document_id' => $testDoc['document_id'],
                'document_name' => $testDoc['document_name'],
                'previous_status' => $testDoc['previous_status'],
                'new_status' => $testDoc['new_status'],
                'status' => $testDoc['new_status'],
                'rejection_reason' => $testDoc['rejection_reason'],
                'has_rejection' => $testDoc['has_rejection_reason'],
                'admin_name' => $testDoc['admin_name'],
                'formatted_time' => $testDoc['formatted_time']
            ]
        ]);
        exit;

    } catch (Exception $e) {
        error_log("Admin2 test_queue_creation error: " . $e->getMessage(), E_USER_WARNING);
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error creating test queue: ' . $e->getMessage()
        ]);
        exit;
    }
}

// Handle update_document_status POST action (priority)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_document_status' && isset($_POST['application_id']) && isset($_POST['document_id']) && isset($_POST['status'])) {
    error_log("🔥🔥🔥 Admin2: update_document_status handler PRIORITY - Processing request 🔥🔥🔥", E_USER_NOTICE);
    error_log("DEBUG: Initial POST data received: " . json_encode($_POST), E_USER_NOTICE);

    try {
        $ACTION_HANDLED = true;
        error_log("DEBUG: ACTION_HANDLED set to true", E_USER_NOTICE);

        ob_clean();
        header('Content-Type: application/json');
        error_log("DEBUG: Headers set and output buffer cleaned", E_USER_NOTICE);

        // Security: CSRF Token Validation
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            error_log("Admin2 update_document_status PRIORITY: CSRF validation FAILED", E_USER_WARNING);
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Security validation failed. Please refresh and try again.']);
            exit;
        }
        error_log("DEBUG: CSRF validation PASSED", E_USER_NOTICE);

        // Input Sanitization
        error_log("DEBUG: Starting input sanitization", E_USER_NOTICE);
        $applicationId = sanitizeString($_POST['application_id'], 50);
        $documentId = sanitizeInt($_POST['document_id']);
        $newStatus = sanitizeString($_POST['status'], 20);
        $rejectionReason = isset($_POST['rejection_reason']) ? sanitizeString($_POST['rejection_reason'], 1000) : null;
        error_log("DEBUG: Input sanitization completed", E_USER_NOTICE);

        // Debug logging
        error_log("DEBUG: update_document_status - appId: $applicationId, docId: $documentId, status: $newStatus, reason: $rejectionReason", E_USER_NOTICE);

        // Validate inputs
        if (empty($applicationId) || empty($documentId) || empty($newStatus)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid input parameters']);
            exit;
        }

        // Validate status
        $validStatuses = ['Pending', 'Approved', 'Rejected'];
        if (!in_array($newStatus, $validStatuses)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid document status']);
            exit;
        }

        // Validate rejection reason is required when rejecting
        if ($newStatus === 'Rejected' && empty($rejectionReason)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Rejection reason is required']);
            exit;
        }

        // Get current document status for audit trail
        error_log("DEBUG: Getting current document status for audit", E_USER_NOTICE);
        $currentDocumentQuery = "SELECT status, document_type_id, updated_at, created_at FROM documents WHERE document_id = ? AND application_id = ? LIMIT 1";
        $currentStmt = $conn->prepare($currentDocumentQuery);
        $oldStatus = null;
        $documentTypeId = null;
        $lastUpdated = null;
        $createdAt = null;

        if ($currentStmt) {
            $currentStmt->bind_param("is", $documentId, $applicationId);
            if ($currentStmt->execute()) {
                $result = $currentStmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $oldStatus = $row['status'];
                    $documentTypeId = $row['document_type_id'];
                    $lastUpdated = $row['updated_at'] ?? null;
                    $createdAt = $row['created_at'] ?? null;
                    error_log("DEBUG: Found current status: $oldStatus, document_type_id: $documentTypeId, last_updated: $lastUpdated", E_USER_NOTICE);
                }
            }
            $currentStmt->close();
        }

        // VALIDATION: Prevent approval if document was rejected and user hasn't resubmitted
        if ($newStatus === 'Approved' && $oldStatus === 'Rejected') {
            // Check if there's been any document resubmission after the last rejection
            try {
                // Look for any recent document audit trail or status changes indicating resubmission
                $resubmissionCheck = "SELECT COUNT(*) as resubmission_count FROM document_audit 
                                    WHERE document_id = ? AND application_id = ? 
                                    AND action_type IN ('resubmitted', 'uploaded', 'replaced') 
                                    AND created_at > (
                                        SELECT MAX(created_at) FROM document_audit 
                                        WHERE document_id = ? AND application_id = ? 
                                        AND new_status = 'Rejected'
                                    )";

                $resubmissionStmt = $conn->prepare($resubmissionCheck);
                if ($resubmissionStmt) {
                    $resubmissionStmt->bind_param("isis", $documentId, $applicationId, $documentId, $applicationId);
                    $resubmissionStmt->execute();
                    $resubResult = $resubmissionStmt->get_result();
                    $resubData = $resubResult->fetch_assoc();
                    $resubmissionStmt->close();

                    if ($resubData['resubmission_count'] == 0) {
                        // Alternative check: Look at document file timestamps or modification dates
                        $fileUpdateCheck = "SELECT file_updated_at, last_modified, upload_timestamp 
                                          FROM documents 
                                          WHERE document_id = ? AND application_id = ? 
                                          LIMIT 1";

                        $fileStmt = $conn->prepare($fileUpdateCheck);
                        $hasResubmission = false;

                        if ($fileStmt) {
                            $fileStmt->bind_param("is", $documentId, $applicationId);
                            $fileStmt->execute();
                            $fileResult = $fileStmt->get_result();
                            if ($fileData = $fileResult->fetch_assoc()) {
                                // Check if any file timestamp is newer than the last rejection
                                $fileTimestamps = array_filter([
                                    $fileData['file_updated_at'],
                                    $fileData['last_modified'],
                                    $fileData['upload_timestamp']
                                ]);

                                if (!empty($fileTimestamps)) {
                                    $latestFileTime = max($fileTimestamps);
                                    // If we have a file timestamp newer than last update, consider it resubmitted
                                    if ($lastUpdated && strtotime($latestFileTime) > strtotime($lastUpdated)) {
                                        $hasResubmission = true;
                                        error_log("DEBUG: Document appears to have been resubmitted based on file timestamps", E_USER_NOTICE);
                                    }
                                }
                            }
                            $fileStmt->close();
                        }

                        if (!$hasResubmission) {
                            http_response_code(400);
                            echo json_encode([
                                'success' => false,
                                'message' => 'Cannot approve document that was previously rejected. User must resubmit the document before it can be approved.',
                                'code' => 'RESUBMISSION_REQUIRED'
                            ]);
                            exit;
                        }
                    }
                }
            } catch (Exception $validationEx) {
                error_log("DEBUG: Resubmission validation check failed: " . $validationEx->getMessage(), E_USER_WARNING);
                // If validation check fails, err on the side of caution
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Unable to verify document resubmission status. Please ensure the document has been resubmitted after rejection.',
                    'code' => 'VALIDATION_ERROR'
                ]);
                exit;
            }
        }

        // Update the document status
        error_log("DEBUG: Starting database update", E_USER_NOTICE);

        // CRITICAL FIX: Check what tables exist and find the correct one
        $tablesQuery = "SHOW TABLES";
        $tables = $conn->query($tablesQuery);
        $tablesList = [];
        if ($tables) {
            while ($row = $tables->fetch_array()) {
                $tablesList[] = $row[0];
            }
        }
        error_log("DEBUG: Available tables in database: " . implode(', ', $tablesList), E_USER_NOTICE);

        // Try different possible table names for documents
        $possibleTables = [
            'loan_application_documents',
            'documents',
            'loan_documents',
            'application_documents',
            'user_documents'
        ];

        $documentTable = null;
        foreach ($possibleTables as $table) {
            if (in_array($table, $tablesList)) {
                $documentTable = $table;
                error_log("DEBUG: Found document table: $table", E_USER_NOTICE);
                break;
            }
        }

        if (!$documentTable) {
            error_log("DEBUG: No document table found. Available tables: " . implode(', ', $tablesList), E_USER_WARNING);
            throw new Exception("Document table not found. Please check database schema.");
        }

        $updateQuery = "UPDATE $documentTable SET status = ? WHERE document_id = ? AND application_id = ? LIMIT 1";
        error_log("DEBUG: Preparing query: $updateQuery", E_USER_NOTICE);

        $stmt = $conn->prepare($updateQuery);
        if (!$stmt) {
            error_log("DEBUG: Database prepare failed: " . $conn->error, E_USER_WARNING);
            throw new Exception("Database error: " . $conn->error);
        }
        error_log("DEBUG: Query prepared successfully", E_USER_NOTICE);

        error_log("DEBUG: Binding parameters - status: $newStatus, docId: $documentId, appId: $applicationId", E_USER_NOTICE);
        $stmt->bind_param("sis", $newStatus, $documentId, $applicationId);

        if (!$stmt->execute()) {
            error_log("DEBUG: Database execute failed: " . $stmt->error, E_USER_WARNING);
            throw new Exception("Update failed: " . $stmt->error);
        }
        error_log("DEBUG: Database update executed successfully, affected rows: " . $stmt->affected_rows, E_USER_NOTICE);

        $stmt->close();
        error_log("DEBUG: Database connection closed", E_USER_NOTICE);

        // Store rejection reason if provided
        if ($newStatus === 'Rejected' && !empty($rejectionReason)) {
            // Check if document_remarks table exists, if not use general remarks table
            $checkTable = $conn->query("SHOW TABLES LIKE 'document_remarks'");

            if ($checkTable && $checkTable->num_rows > 0) {
                // Use document_remarks table
                $remarkQuery = "INSERT INTO document_remarks (document_id, application_id, remark, created_by, created_at) VALUES (?, ?, ?, ?, NOW())";
                $remarkStmt = $conn->prepare($remarkQuery);
                if ($remarkStmt) {
                    $remarkStmt->bind_param("isss", $documentId, $applicationId, $rejectionReason, $adminName);
                    if (!$remarkStmt->execute()) {
                        error_log("Failed to insert document remark: " . $remarkStmt->error, E_USER_WARNING);
                    }
                    $remarkStmt->close();
                }
            } else {
                // Fall back to general remarks table
                $remarkQuery = "INSERT INTO remarks (application_id, remark, created_by, created_at) VALUES (?, ?, ?, NOW())";
                $remarkStmt = $conn->prepare($remarkQuery);
                if ($remarkStmt) {
                    $remarkStmt->bind_param("sss", $applicationId, $rejectionReason, $adminName);
                    if (!$remarkStmt->execute()) {
                        error_log("Failed to insert general remark: " . $remarkStmt->error, E_USER_WARNING);
                    }
                    $remarkStmt->close();
                }
            }
        }

        // Create document audit trail
        if ($oldStatus !== null) {
            error_log("DEBUG: Creating document audit trail - old: $oldStatus, new: $newStatus", E_USER_NOTICE);

            $auditQuery = "
                INSERT INTO document_audit 
                (document_id, application_id, document_type_id, old_status, new_status, admin_id, admin_name, admin_role, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $auditNotes = ($newStatus === 'Rejected' && !empty($rejectionReason)) ? $rejectionReason : "Document status updated via Admin2 Dashboard";

            $auditStmt = $conn->prepare($auditQuery);
            if ($auditStmt) {
                $auditStmt->bind_param(
                    "isississs",
                    $documentId,
                    $applicationId,
                    $documentTypeId,
                    $oldStatus,
                    $newStatus,
                    $adminId,
                    $adminName,
                    $adminRole,
                    $auditNotes
                );

                if ($auditStmt->execute()) {
                    error_log("DEBUG: Document audit trail created successfully", E_USER_NOTICE);
                } else {
                    error_log("WARNING: Failed to create document audit trail: " . $auditStmt->error, E_USER_WARNING);
                }
                $auditStmt->close();
            } else {
                error_log("WARNING: Failed to prepare document audit query: " . $conn->error, E_USER_WARNING);
            }
        } else {
            error_log("WARNING: Could not create audit trail - old status not found", E_USER_WARNING);
        }

        // Log activity
        logActivity(
            $conn,
            $adminId,
            'Admin2',
            'update',
            'document',
            "Updated document status to '$newStatus' for application $applicationId",
            $applicationId
        );

        // Initialize default response variables
        $emailStatus = 'queued_for_manual_send';
        $emailMessage = 'Document change added to email queue.';
        $queueCount = 0;
        $approvedCount = 0;
        $rejectedCount = 0;
        $pendingCount = 0;
        $hasRejections = false;
        $hasApprovals = false;
        $documentName = 'Document';

        // ENHANCED: Queue document change instead of sending immediate email
        try {
            // Get user ID for the application (with error handling)
            $userId = null;
            $documentName = 'Document';
            $documentTypeId = null;

            try {
                $userQuery = "SELECT user_id FROM loan_applications WHERE application_id = ? LIMIT 1";
                $userResult = executeQuery($conn, $userQuery, "s", [$applicationId]);
                $userId = !empty($userResult) ? $userResult[0]['user_id'] : null;
                error_log("QUEUE_DEBUG: Retrieved user_id=$userId for app $applicationId", E_USER_NOTICE);
            } catch (Exception $userEx) {
                error_log("QUEUE_WARNING: Failed to get user_id for app $applicationId: " . $userEx->getMessage(), E_USER_WARNING);
                // Continue with null user_id
            }

            // Get document name from the database - try to get actual uploaded filename
            try {
                // Query all document fields to get complete information
                $docQuery = "SELECT * FROM $documentTable WHERE document_id = ? AND application_id = ? LIMIT 1";
                error_log("QUEUE_DEBUG: Getting document info: $docQuery with docId=$documentId, appId=$applicationId", E_USER_NOTICE);
                $docResult = executeQuery($conn, $docQuery, "is", [$documentId, $applicationId]);

                if (!empty($docResult)) {
                    $docData = $docResult[0];
                    error_log("QUEUE_DEBUG: Found document data: " . json_encode($docData), E_USER_NOTICE);

                    // Priority order: look for actual uploaded filenames first, then fallback to type names
                    $possibleFields = [
                        'original_filename',
                        'uploaded_filename',
                        'user_filename',
                        'file_original_name',
                        'document_filename',
                        'actual_filename',
                        'real_filename',
                        'filename',
                        'file_name',
                        'name',
                        'document_name',
                        'title',
                        'document_title'
                    ];

                    foreach ($possibleFields as $field) {
                        if (isset($docData[$field]) && !empty(trim($docData[$field]))) {
                            $rawName = trim($docData[$field]);

                            // Extract filename from path if needed
                            if (strpos($rawName, '/') !== false || strpos($rawName, '\\') !== false) {
                                $documentName = basename($rawName);
                            } else {
                                $documentName = $rawName;
                            }

                            // Skip if it's just a generic ID or hash
                            if (!preg_match('/^[a-f0-9]{10,}$/', $documentName) && $documentName !== 'Document') {
                                error_log("QUEUE_DEBUG: Using document name '$documentName' from field '$field'", E_USER_NOTICE);
                                break;
                            }
                        }
                    }

                    // Get document type ID for fallback lookup
                    $typeFields = ['document_type_id', 'type_id', 'document_type', 'category_id'];
                    foreach ($typeFields as $field) {
                        if (isset($docData[$field]) && !empty($docData[$field])) {
                            $documentTypeId = $docData[$field];
                            break;
                        }
                    }
                } else {
                    error_log("QUEUE_WARNING: No document found with ID $documentId in app $applicationId", E_USER_WARNING);
                }

                error_log("QUEUE_DEBUG: Final document_name='$documentName' for doc $documentId", E_USER_NOTICE);


                // If still no proper name, try document type tables
                if ($documentName === 'Document' && $documentTypeId) {
                    // First try the predefined document type mapping
                    $documentTypeMap = [
                        1 => '2x2 Picture',
                        2 => 'Voter\'s Certificate',
                        3 => 'Residence Certificate',
                        4 => 'Barangay Clearance',
                        5 => 'Business Permit',
                        6 => 'Farm Plan and Budget',
                        7 => 'Loan Project Proposal',
                        8 => 'Audited Financial Statement',
                        9 => 'Bank Statement',
                        10 => 'BIR Document'
                    ];

                    if (isset($documentTypeMap[$documentTypeId])) {
                        $documentName = $documentTypeMap[$documentTypeId];
                        error_log("QUEUE_DEBUG: Using predefined document name '$documentName' for type ID $documentTypeId", E_USER_NOTICE);
                    } else {
                        // Fallback to database lookup for document types not in the map
                        try {
                            // Try common document type table names
                            $typeTables = ['document_types', 'doc_types', 'document_categories', 'loan_document_types'];

                            foreach ($typeTables as $typeTable) {
                                $checkQuery = "SHOW TABLES LIKE '$typeTable'";
                                if ($conn->query($checkQuery) && $conn->query($checkQuery)->num_rows > 0) {
                                    // Try to get type name
                                    $typeQuery = "SELECT name, type_name, document_name FROM $typeTable WHERE id = ? OR document_type_id = ? LIMIT 1";
                                    $typeResult = executeQuery($conn, $typeQuery, "ii", [$documentTypeId, $documentTypeId]);

                                    if (!empty($typeResult)) {
                                        $typeData = $typeResult[0];
                                        $typeName = $typeData['name'] ?? $typeData['type_name'] ?? $typeData['document_name'] ?? null;

                                        if (!empty($typeName) && !in_array(strtolower($typeName), ['document', 'file', 'upload'])) {
                                            $documentName = $typeName;
                                            error_log("QUEUE_DEBUG: Got type name '$documentName' from $typeTable", E_USER_NOTICE);
                                            break;
                                        }
                                    }
                                }
                            }
                        } catch (Exception $typeEx) {
                            error_log("QUEUE_DEBUG: Type table lookup failed: " . $typeEx->getMessage(), E_USER_NOTICE);
                        }
                    }
                }

                // Ultimate fallback: Generate a meaningful name from document ID or type
                if ($documentName === 'Document') {
                    if ($documentTypeId) {
                        $documentName = "Document Type $documentTypeId";
                    } else {
                        $documentName = "Document #$documentId";
                    }
                    error_log("QUEUE_DEBUG: Using fallback document name: '$documentName'", E_USER_NOTICE);
                }

            } catch (Exception $docEx) {
                error_log("QUEUE_WARNING: Failed to get document details for doc $documentId: " . $docEx->getMessage(), E_USER_WARNING);
                // Use fallback name based on document ID
                $documentName = "Document #$documentId";
            }

            // Initialize PERSISTENT queue for this application if not exists
            error_log("QUEUE_DEBUG: Initializing queue for app $applicationId with user_id=$userId", E_USER_NOTICE);

            if (!isset($_SESSION['document_changes_queue'][$applicationId])) {
                $_SESSION['document_changes_queue'][$applicationId] = [
                    'changes' => [],
                    'user_id' => $userId,
                    'application_id' => $applicationId,
                    'first_change_time' => date('F j, Y \a\t g:i A'),
                    'last_update_time' => date('F j, Y \a\t g:i A'),
                    'created_timestamp' => time(),
                    'last_timestamp' => time(),
                    'all_documents' => [],
                    'admin_name' => $adminName ?? 'Admin',
                    'admin_id' => $adminId ?? 0,
                    'total_changes' => 0,
                    'approved_count' => 0,
                    'rejected_count' => 0,
                    'pending_count' => 0,
                    'has_rejections' => false,
                    'has_approvals' => false,
                    'queue_status' => 'accumulating', // Status: accumulating, ready, sending, sent
                    'auto_accumulate' => true, // Always accumulate changes
                    'manual_send_only' => true, // Only send when admin clicks send button
                    'auto_send_disabled' => true, // Explicitly disable auto-sending
                    'last_email_sent' => null,
                    'email_send_count' => 0,
                    'session_id' => session_id()
                ];
                error_log("PERSISTENT_QUEUE_INIT: Initialized persistent accumulating queue for app $applicationId", E_USER_NOTICE);
            }

            error_log("QUEUE_DEBUG: Preparing change data for doc $documentId", E_USER_NOTICE);

            // Add comprehensive document change to PERSISTENT queue
            $changeData = [
                // Document identifiers
                'document_id' => $documentId,
                'document_name' => $documentName,
                'document_type_id' => $documentTypeId,
                'application_id' => $applicationId,

                // Status changes
                'previous_status' => $oldStatus,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'status' => $newStatus,
                'status_changed' => ($oldStatus !== $newStatus),

                // Rejection information
                'rejection_reason' => $rejectionReason,
                'rejection_notes' => $rejectionReason,
                'has_rejection_reason' => !empty($rejectionReason),

                // Admin information
                'admin_name' => $adminName ?? 'Admin',
                'admin_id' => $adminId ?? 0,
                'admin_role' => 'Admin2',

                // Timestamps
                'status_updated_at' => date('Y-m-d H:i:s'),
                'formatted_time' => date('F j, Y \a\t g:i A'),
                'unix_timestamp' => time(),
                'updated_at' => date('Y-m-d H:i:s'),

                // Display information for email
                'description' => getDocumentDescription($documentName, $documentTypeId),
                'status_icon' => $newStatus === 'Approved' ? '✅' : ($newStatus === 'Rejected' ? '❌' : '⏳'),
                'status_color' => $newStatus === 'Approved' ? '#ffffff' : ($newStatus === 'Rejected' ? '#ffffff' : '#ffffff'),
                'status_bg_color' => $newStatus === 'Approved' ? 'linear-gradient(135deg, #4caf50, #66bb6a)' : ($newStatus === 'Rejected' ? 'linear-gradient(135deg, #f44336, #ef5350)' : 'linear-gradient(135deg, #ff9800, #ffb74d)'),
                'border_color' => $newStatus === 'Approved' ? '#4caf50' : ($newStatus === 'Rejected' ? '#f44336' : '#ff9800'),
                'card_bg_color' => $newStatus === 'Approved' ? '#f8fdf8' : ($newStatus === 'Rejected' ? '#fff8f8' : '#fffaf0'),

                // Queue tracking
                'change_type' => 'status_update',
                'is_new_change' => true,
                'processed' => false,
                'queued_at' => date('Y-m-d H:i:s'),
                'email_sent' => false,
                'change_source' => 'admin_update'
            ];            // Check if this document is already in queue, update it instead of adding duplicate
            $existingIndex = null;
            foreach ($_SESSION['document_changes_queue'][$applicationId]['changes'] as $index => $change) {
                if ($change['document_id'] == $documentId) {
                    $existingIndex = $index;
                    break;
                }
            }

            if ($existingIndex !== null) {
                $_SESSION['document_changes_queue'][$applicationId]['changes'][$existingIndex] = $changeData;
                error_log("QUEUE_UPDATE: Updated existing document $documentId in queue for app $applicationId", E_USER_NOTICE);
            } else {
                $_SESSION['document_changes_queue'][$applicationId]['changes'][] = $changeData;
                error_log("QUEUE_ADD: Added new document $documentId to queue for app $applicationId", E_USER_NOTICE);
            }

            // Update comprehensive queue metadata and statistics
            $_SESSION['document_changes_queue'][$applicationId]['last_update_time'] = date('F j, Y \a\t g:i A');
            $_SESSION['document_changes_queue'][$applicationId]['last_timestamp'] = time();
            $_SESSION['document_changes_queue'][$applicationId]['user_id'] = $userId;
            $_SESSION['document_changes_queue'][$applicationId]['admin_name'] = $adminName;

            // Calculate queue statistics
            $queueChanges = $_SESSION['document_changes_queue'][$applicationId]['changes'];
            $queueCount = count($queueChanges);
            $approvedCount = 0;
            $rejectedCount = 0;
            $pendingCount = 0;
            $hasRejections = false;
            $hasApprovals = false;

            foreach ($queueChanges as $change) {
                $status = $change['new_status'];
                if ($status === 'Approved') {
                    $approvedCount++;
                    $hasApprovals = true;
                } elseif ($status === 'Rejected') {
                    $rejectedCount++;
                    $hasRejections = true;
                } elseif ($status === 'Pending') {
                    $pendingCount++;
                }
            }

            // Update statistics
            $_SESSION['document_changes_queue'][$applicationId]['total_changes'] = $queueCount;
            $_SESSION['document_changes_queue'][$applicationId]['approved_count'] = $approvedCount;
            $_SESSION['document_changes_queue'][$applicationId]['rejected_count'] = $rejectedCount;
            $_SESSION['document_changes_queue'][$applicationId]['pending_count'] = $pendingCount;
            $_SESSION['document_changes_queue'][$applicationId]['has_rejections'] = $hasRejections;
            $_SESSION['document_changes_queue'][$applicationId]['has_approvals'] = $hasApprovals;

            error_log("ENHANCED_QUEUE_STATUS: App $applicationId - Total: $queueCount, Approved: $approvedCount, Rejected: $rejectedCount, Pending: $pendingCount", E_USER_NOTICE);

            // AUTOMATIC EMAIL SENDING: Send immediate notification for document changes
            try {
                error_log("AUTO_EMAIL: Attempting to send automatic notification for document change", E_USER_NOTICE);

                // Create immediate email update data
                $documentChangeUpdate = [
                    'admin_name' => $adminName,
                    'admin_updated_at' => date('Y-m-d H:i:s'),
                    'document_changes' => [
                        [
                            'document_name' => $documentName,
                            'new_status' => $newStatus,
                            'previous_status' => $oldStatus,
                            'rejection_reason' => $rejectionReason ?? '',
                            'rejection_notes' => $rejectionReason ?? '',
                            'status_updated_at' => date('Y-m-d H:i:s'),
                            'formatted_time' => date('F j, Y \a\t g:i A'),
                            'description' => getDocumentDescription($documentName, $documentTypeId)
                        ]
                    ],
                    'batch_count' => 1,
                    'has_rejections' => ($newStatus === 'Rejected'),
                    'has_approvals' => ($newStatus === 'Approved'),
                    'total_changes' => 1
                ];

                // Send automatic email notification
                $autoEmailSent = sendConsolidatedUpdateEmail($conn, $applicationId, $documentChangeUpdate);

                if ($autoEmailSent) {
                    $emailStatus = 'sent_automatically';
                    $emailMessage = "Document status updated and notification sent automatically to user.";
                    error_log("AUTO_EMAIL_SUCCESS: Automatic notification sent for doc $documentId in app $applicationId", E_USER_NOTICE);

                    // Mark the queue change as processed since email was sent
                    if (isset($_SESSION['document_changes_queue'][$applicationId]['changes'])) {
                        foreach ($_SESSION['document_changes_queue'][$applicationId]['changes'] as &$queuedChange) {
                            if ($queuedChange['document_id'] == $documentId) {
                                $queuedChange['email_sent'] = true;
                                $queuedChange['email_sent_at'] = date('Y-m-d H:i:s');
                                $queuedChange['processed'] = true;
                                break;
                            }
                        }
                    }
                } else {
                    $emailStatus = 'auto_send_failed_queued';
                    $emailMessage = "Document updated but automatic email failed. Change added to manual queue.";
                    error_log("AUTO_EMAIL_FAILED: Automatic notification failed for doc $documentId in app $applicationId, falling back to queue", E_USER_WARNING);
                }

            } catch (Exception $autoEmailEx) {
                $emailStatus = 'auto_send_error_queued';
                $emailMessage = "Document updated but automatic email error occurred. Change added to manual queue.";
                error_log("AUTO_EMAIL_ERROR: Automatic notification error for doc $documentId: " . $autoEmailEx->getMessage(), E_USER_WARNING);
            }

        } catch (Exception $queueEx) {
            error_log("QUEUE_ERROR: Failed to queue document change for app $applicationId: " . $queueEx->getMessage(), E_USER_WARNING);
            error_log("QUEUE_ERROR_TRACE: " . $queueEx->getTraceAsString(), E_USER_WARNING);

            // Set status but don't fail the entire operation - document was still updated successfully
            $emailStatus = 'queue_failed';
            $emailMessage = 'Document updated but failed to add to email queue: ' . $queueEx->getMessage();

            // Initialize default values if they weren't set due to the exception
            $queueCount = 0;
            $approvedCount = 0;
            $rejectedCount = 0;
            $pendingCount = 0;
            $hasRejections = false;
            $hasApprovals = false;
        }

        // Create notification for the user about document status update
        try {
            if (!empty($userId)) {
                require_once 'NotificationManager.php';
                $notificationManager = new NotificationManager($conn);

                // Determine notification title and message based on status
                if ($newStatus === 'Approved') {
                    $notificationTitle = "Document Approved: {$documentName}";
                    $notificationMessage = "Your {$documentName} document has been approved. You can proceed with the next steps of your application.";
                    $notificationType = 'approval';
                    $notificationPriority = 'high';
                } elseif ($newStatus === 'Rejected') {
                    $notificationTitle = "Document Rejected: {$documentName}";
                    $notificationMessage = "Your {$documentName} document was rejected. Reason: {$rejectionReason}. Please resubmit the corrected document.";
                    $notificationType = 'warning';
                    $notificationPriority = 'high';
                } else { // Pending
                    $notificationTitle = "Document Under Review: {$documentName}";
                    $notificationMessage = "Your {$documentName} document has been set to pending for re-review.";
                    $notificationType = 'status';
                    $notificationPriority = 'normal';
                }

                // Create the notification
                $notificationManager->createNotification(
                    $userId,
                    $notificationType,
                    $notificationTitle,
                    $notificationMessage,
                    $notificationPriority
                );
                error_log("USER_NOTIFICATION: Created notification for user $userId - Document $documentId status changed to $newStatus", E_USER_NOTICE);
            } else {
                error_log("USER_NOTIFICATION_SKIPPED: No user_id found for application $applicationId", E_USER_WARNING);
            }
        } catch (Exception $notifEx) {
            error_log("USER_NOTIFICATION_ERROR: Failed to create user notification for app $applicationId: " . $notifEx->getMessage(), E_USER_WARNING);
            // Don't fail the entire operation if notification creation fails
        }

        // CREATE ADMIN1 NOTIFICATION for document status updates (Bidirectional Communication)
        try {
            // Get admin1 user from admin_accounts table
            $admin1Query = "SELECT id, admin_name FROM admin_accounts WHERE admin_type = 'Admin 1' LIMIT 1";
            $admin1Result = $conn->query($admin1Query);

            if ($admin1Result && $admin1User = $admin1Result->fetch_assoc()) {
                // Create admin1-specific notification for document updates
                $admin1NotificationTitle = '';
                $admin1NotificationMessage = '';
                $admin1NotificationType = 'status';
                $admin1Priority = 'low';

                if ($newStatus === 'Approved') {
                    $admin1NotificationTitle = '📋 Document Approved';
                    $admin1NotificationMessage = "Document '{$documentName}' for application #$applicationId has been approved by Admin2.";
                    $admin1Priority = 'low';
                } elseif ($newStatus === 'Rejected') {
                    $admin1NotificationTitle = '📋 Document Rejected';
                    $admin1NotificationMessage = "Document '{$documentName}' for application #$applicationId was rejected. Reason: " . ($rejectionReason ?? 'Not specified') . ".";
                    $admin1Priority = 'low';
                } else { // Pending
                    $admin1NotificationTitle = '📋 Document Status Updated';
                    $admin1NotificationMessage = "Document '{$documentName}' for application #$applicationId has been set to pending for re-review.";
                    $admin1Priority = 'low';
                }

                // Create notification for admin1
                if ($admin1NotificationTitle) {
                    $notificationManager->createNotification(
                        $admin1User['id'],
                        $admin1NotificationType,
                        $admin1NotificationTitle,
                        $admin1NotificationMessage,
                        $admin1Priority
                    );
                    error_log("✅ ADMIN1_DOC_NOTIFICATION: Created notification for admin1 (ID: {$admin1User['id']}) - Document $documentId status changed to $newStatus for app $applicationId", E_USER_NOTICE);
                }
            } else {
                error_log("⚠️ ADMIN1_DOC_NOTIFICATION: No Admin 1 user found in admin_accounts table", E_USER_WARNING);
            }
        } catch (Exception $admin1NotifEx) {
            error_log("❌ ADMIN1_DOC_NOTIFICATION_ERROR: Failed to create admin1 notification for app $applicationId: " . $admin1NotifEx->getMessage(), E_USER_WARNING);
            // Don't fail the entire operation if admin1 notification creation fails
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => "Document status updated to $newStatus successfully! " . ($emailMessage ?? 'Notification sent automatically.'),
            'queue_behavior' => 'automatic_notification', // Changed from accumulation_mode
            'email_status' => $emailStatus ?? 'sent_automatically',
            'data' => [
                'application_id' => $applicationId,
                'document_id' => $documentId,
                'document_name' => $documentName ?? 'Document',
                'previous_status' => $oldStatus,
                'new_status' => $newStatus,
                'status' => $newStatus,
                'rejection_reason' => $rejectionReason,
                'has_rejection' => !empty($rejectionReason),
                'admin_name' => $adminName,
                'formatted_time' => date('F j, Y \a\t g:i A'),
                'queue_count' => $queueCount ?? 0,
                'queue_stats' => [
                    'total_changes' => $queueCount ?? 0,
                    'approved_count' => $approvedCount ?? 0,
                    'rejected_count' => $rejectedCount ?? 0,
                    'pending_count' => $pendingCount ?? 0,
                    'has_rejections' => $hasRejections ?? false,
                    'has_approvals' => $hasApprovals ?? false,
                    'queue_status' => 'accumulating', // Always accumulating until manual send
                    'last_update' => $_SESSION['document_changes_queue'][$applicationId]['last_update_time'] ?? date('F j, Y \a\t g:i A')
                ],
                'queued' => true,
                'auto_send' => false, // No automatic sending
                'email_sent' => false, // Never auto-sent
                'manual_send_required' => true // Always requires manual send
            ]
        ]);
        exit;

    } catch (Exception $e) {
        error_log("Admin2 update_document_status error: " . $e->getMessage(), E_USER_WARNING);
        error_log("Admin2 update_document_status FULL TRACE: " . $e->getTraceAsString(), E_USER_WARNING);
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error updating document status: ' . $e->getMessage(),
            'error_code' => 'UPDATE_DOCUMENT_FAILED',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
}

// Handle check_queue_status POST action - get current queue status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_queue_status' && isset($_POST['application_id'])) {
    error_log("Admin2: check_queue_status handler - Processing request", E_USER_NOTICE);

    try {
        $ACTION_HANDLED = true;
        ob_clean();
        header('Content-Type: application/json');

        // Security: CSRF Token Validation
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            error_log("Admin2 check_queue_status: CSRF validation FAILED", E_USER_WARNING);
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Security validation failed. Please refresh and try again.']);
            exit;
        }

        // Input Sanitization
        $applicationId = sanitizeString($_POST['application_id'], 50);
        if (empty($applicationId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid application ID']);
            exit;
        }

        // Get detailed queue status
        $queueStatus = getDetailedQueueStatus($applicationId);

        error_log("QUEUE_STATUS_CHECK: App $applicationId - Queue count: {$queueStatus['queue_count']}, Status: {$queueStatus['queue_status']}", E_USER_NOTICE);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'queue_status' => $queueStatus,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;

    } catch (Exception $e) {
        error_log("Admin2 check_queue_status error: " . $e->getMessage(), E_USER_WARNING);
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error checking queue status: ' . $e->getMessage()
        ]);
        exit;
    }
}

// Handle send_queued_document_email POST action (priority)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_queued_document_email' && isset($_POST['application_id'])) {
    error_log("Admin2: send_queued_document_email handler PRIORITY - Processing request", E_USER_NOTICE);

    try {
        $ACTION_HANDLED = true;
        ob_clean();
        header('Content-Type: application/json');

        // Security: CSRF Token Validation
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            error_log("Admin2 send_queued_document_email PRIORITY: CSRF validation FAILED", E_USER_WARNING);
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Security validation failed. Please refresh and try again.']);
            exit;
        }

        // Input Sanitization
        $applicationId = sanitizeString($_POST['application_id'], 50);
        if (empty($applicationId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid application ID']);
            exit;
        }

        // Get the queue data from session
        if (!isset($_SESSION['document_changes_queue'][$applicationId])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No queued changes found']);
            exit;
        }

        $queueData = $_SESSION['document_changes_queue'][$applicationId];

        // Send email with queued changes
        $emailSent = sendQueuedDocumentChangesEmail($conn, $applicationId);

        if (!$emailSent) {
            error_log("Failed to send queued document email for application $applicationId", E_USER_WARNING);
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error sending queued changes email'
            ]);
            exit;
        }

        error_log("Queued document email sent successfully for application $applicationId", E_USER_NOTICE);

        // Log activity
        logActivity(
            $conn,
            $adminId,
            'Admin2',
            'send_email',
            'document',
            "Sent queued document changes email for application $applicationId",
            $applicationId
        );

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Queued changes email sent successfully!'
        ]);
        exit;

    } catch (Exception $e) {
        error_log("Admin2 send_queued_document_email error: " . $e->getMessage(), E_USER_WARNING);
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error sending queued email: ' . $e->getMessage()
        ]);
        exit;
    }
}

// Handle clear_queue POST action (clear email queue without sending)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_queue' && isset($_POST['application_id'])) {

    if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'Admin2') {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Admin 2 role required']);
        http_response_code(403);
        exit;
    }

    // CSRF Token Validation
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Security validation failed. Please refresh and try again.']);
        http_response_code(403);
        exit;
    }

    $applicationId = sanitizeString($_POST['application_id'], 50);
    $adminId = $_SESSION['admin_id'];
    $adminName = $_SESSION['admin_name'] ?? 'Admin';

    error_log("🗑️ QUEUE_CLEAR_DEBUG: Queue clear requested for app_id='$applicationId' by admin='$adminName'", E_USER_NOTICE);

    try {
        // Check if queue exists
        if (!isset($_SESSION['document_changes_queue'][$applicationId]) || empty($_SESSION['document_changes_queue'][$applicationId]['changes'])) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'No queue found to clear'
            ]);
            exit;
        }

        $queueData = $_SESSION['document_changes_queue'][$applicationId];
        $changeCount = count($queueData['changes']);

        // Clear the queue
        unset($_SESSION['document_changes_queue'][$applicationId]);

        // Log activity
        logActivity(
            $conn,
            $adminId,
            'Admin2',
            'clear_queue',
            'document',
            "Cleared email queue for application $applicationId ($changeCount changes discarded)",
            $applicationId
        );

        error_log("QUEUE_CLEARED: Successfully cleared queue for App $applicationId with $changeCount changes", E_USER_NOTICE);

        ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => "Queue cleared successfully. $changeCount document changes discarded.",
            'changes_cleared' => $changeCount
        ]);

    } catch (Exception $e) {
        error_log("QUEUE_CLEAR_ERROR: " . $e->getMessage(), E_USER_WARNING);

        ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Error clearing queue: ' . $e->getMessage()
        ]);
    }

    exit;
}

// Handle send_payment_reminder POST action (priority)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_payment_reminder' && isset($_POST['payment_id'])) {
    error_log("Admin2: send_payment_reminder handler PRIORITY - Processing request", E_USER_NOTICE);

    try {
        $ACTION_HANDLED = true;
        ob_clean();
        header('Content-Type: application/json');

        // Security: CSRF Token Validation
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            error_log("Admin2 send_payment_reminder PRIORITY: CSRF validation FAILED", E_USER_WARNING);
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Security validation failed. Please refresh and try again.']);
            exit;
        }

        // Input Sanitization
        $paymentId = sanitizeInt($_POST['payment_id']);
        if (empty($paymentId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid payment ID']);
            exit;
        }

        error_log("Sending payment reminder for payment ID $paymentId", E_USER_NOTICE);

        // Log activity
        logActivity(
            $conn,
            $adminId,
            'Admin2',
            'send_reminder',
            'payment',
            "Sent payment reminder for payment ID $paymentId"
        );

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Payment reminder sent successfully!'
        ]);
        exit;

    } catch (Exception $e) {
        error_log("Admin2 send_payment_reminder error: " . $e->getMessage(), E_USER_WARNING);
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error sending payment reminder: ' . $e->getMessage()
        ]);
        exit;
    }
}

// Handle search_applications POST action (priority)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'search_applications') {
    error_log("Admin2: search_applications handler PRIORITY - Processing request", E_USER_NOTICE);

    try {
        $ACTION_HANDLED = true;
        ob_clean();
        header('Content-Type: application/json');

        // Get search parameters
        $searchQuery = isset($_POST['search_query']) ? sanitizeString($_POST['search_query'], 255) : '';
        $status = isset($_POST['status']) ? sanitizeString($_POST['status'], 50) : '';
        $page = isset($_POST['page']) ? sanitizeInt($_POST['page']) : 1;
        $limit = isset($_POST['limit']) ? sanitizeInt($_POST['limit']) : 10;

        // Validate pagination
        $page = max(1, $page);
        $limit = min($limit, 100); // Max 100 results per page
        $offset = ($page - 1) * $limit;

        // Build search query
        $query = "SELECT * FROM loan_applications WHERE 1=1";
        $params = [];
        $types = "";

        if (!empty($searchQuery)) {
            $query .= " AND (application_id LIKE ? OR user_id LIKE ?)";
            $searchParam = "%$searchQuery%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $types .= "ss";
        }

        if (!empty($status)) {
            $query .= " AND status = ?";
            $params[] = $status;
            $types .= "s";
        }

        $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";

        // Execute search
        $results = executeQuery($conn, $query, $types, $params);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $results,
            'page' => $page,
            'limit' => $limit,
            'count' => count($results)
        ]);
        exit;

    } catch (Exception $e) {
        error_log("Admin2 search_applications error: " . $e->getMessage(), E_USER_WARNING);
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error searching applications: ' . $e->getMessage()
        ]);
        exit;
    }
}

// CHECK QUEUE STATUS HANDLER - Get current queue count for an application
if (isset($_GET['action']) && $_GET['action'] === 'check_queue_status' && isset($_GET['application_id'])) {

    if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'Admin2') {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Admin 2 role required']);
        http_response_code(403);
        exit;
    }

    $applicationId = sanitizeString($_GET['application_id'], 50);

    // Check current queue status
    $queueCount = 0;
    if (isset($_SESSION['document_changes_queue'][$applicationId]['changes'])) {
        $queueCount = count($_SESSION['document_changes_queue'][$applicationId]['changes']);
    }

    error_log("🔍 QUEUE_STATUS_CHECK: App '$applicationId' has $queueCount queued changes", E_USER_NOTICE);

    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'queue_count' => $queueCount,
        'has_queue' => $queueCount > 0
    ]);
    exit;
}

// MANUAL EMAIL QUEUE HANDLER - Send consolidated email on button click
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_queued_email' && isset($_POST['application_id'])) {

    if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'Admin2') {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Admin 2 role required']);
        http_response_code(403);
        exit;
    }

    // CSRF Token Validation
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Security validation failed']);
        http_response_code(403);
        exit;
    }

    $applicationId = sanitizeString($_POST['application_id'], 50);
    $adminId = $_SESSION['admin_id'];
    $adminName = $_SESSION['admin_name'];

    error_log("🔍 QUEUE_EMAIL_DEBUG: Manual email send requested for app_id='$applicationId' by admin='$adminName'", E_USER_NOTICE);

    try {
        // Check if there are queued changes
        if (empty($_SESSION['document_changes_queue'][$applicationId])) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'No queued document changes found']);
            exit;
        }

        $queueData = $_SESSION['document_changes_queue'][$applicationId];
        $changeCount = count($queueData['changes']);

        error_log("🔍 QUEUE_EMAIL_DEBUG: Found $changeCount queued changes for app_id='$applicationId'", E_USER_NOTICE);

        // Prepare consolidated email data
        $consolidatedData = [
            'template' => 'batch_documents',
            'document_changes' => $queueData['changes'],
            'all_documents' => $queueData['all_documents'] ?? [],
            'admin_name' => $adminName,
            'admin_id' => $adminId,
            'user_id' => $queueData['user_id'],
            'first_change_time' => $queueData['first_change_time'],
            'last_update_time' => $queueData['last_update_time'] ?? date('F j, Y \a\t g:i A'),
            'total_changes' => $changeCount,
            'batch_count' => $changeCount,
            'has_rejections' => count(array_filter($queueData['changes'], function ($c) {
                return strtolower($c['new_status']) === 'rejected';
            })) > 0,
            'has_approvals' => count(array_filter($queueData['changes'], function ($c) {
                return strtolower($c['new_status']) === 'approved';
            })) > 0,
            'admin_updated_at' => date('Y-m-d H:i:s')
        ];

        // Send consolidated email
        $emailSent = sendConsolidatedUpdateEmail($conn, $applicationId, $consolidatedData);

        if ($emailSent) {
            // Clear the queue after successful email
            unset($_SESSION['document_changes_queue'][$applicationId]);

            error_log("EMAIL_QUEUE_SUCCESS: Consolidated email sent for App $applicationId with $changeCount changes", E_USER_NOTICE);

            ob_clean();
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => "Consolidated email sent successfully with $changeCount document updates",
                'changes_sent' => $changeCount
            ]);
        } else {
            error_log("EMAIL_QUEUE_FAILED: Failed to send consolidated email for App $applicationId", E_USER_WARNING);

            ob_clean();
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Failed to send consolidated email. Please try again.'
            ]);
        }

    } catch (Exception $e) {
        error_log("EMAIL_QUEUE_ERROR: " . $e->getMessage(), E_USER_WARNING);

        ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Error sending email: ' . $e->getMessage()
        ]);
    }

    exit;
}

// Function to log activity
function logActivity($conn, $userId, $userRole, $actionType, $module, $description, $affectedId = null, $activityType = null)
{
    // Get admin name and email based on user role
    $adminName = null;
    $adminEmail = null;

    if ($userRole === 'Admin2') {
        $admin = executeQuery($conn, "SELECT first_name, last_name, email FROM admin2 WHERE id = ?", "i", [$userId]);
        if (!empty($admin)) {
            $adminName = trim($admin[0]['first_name'] . ' ' . $admin[0]['last_name']);
            $adminEmail = $admin[0]['email'];
        }
    } elseif ($userRole === 'Admin1') {
        $admin = executeQuery($conn, "SELECT first_name, last_name, email FROM admin1 WHERE id = ?", "i", [$userId]);
        if (!empty($admin)) {
            $adminName = trim($admin[0]['first_name'] . ' ' . $admin[0]['last_name']);
            $adminEmail = $admin[0]['email'];
        }
    } elseif ($userRole === 'User') {
        $user = executeQuery($conn, "SELECT first_name, last_name, email FROM users1 WHERE id = ?", "i", [$userId]);
        if (!empty($user)) {
            $adminName = trim($user[0]['first_name'] . ' ' . $user[0]['last_name']);
            $adminEmail = $user[0]['email'];
        }
    }

    $query = "INSERT INTO activity_logs (user_id, user_role, admin_name, admin_email, action_type, module, activity_type, description, affected_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    $types = "issssssss";
    $params = [$userId, $userRole, $adminName, $adminEmail, $actionType, $module, $activityType, $description, $affectedId];

    // DEBUG: Log activity insertion attempt
    error_log("ACTIVITY_LOG_ATTEMPT: User: $userId ($userRole), Action: $actionType, Module: $module, Description: $description", E_USER_NOTICE);

    $result = executeUpdate($conn, $query, $types, $params);
    if ($result === 0) {
        error_log("ACTIVITY_LOG_FAILED: Failed to insert activity log for user $userId", E_USER_WARNING);
        return false;
    }

    error_log("ACTIVITY_LOG_SUCCESS: Activity logged successfully for user $userId ($userRole)", E_USER_NOTICE);
    return true;
}

// Centralized email configuration and sending function
function sendEmail($to, $toName, $subject, $body, $context = '')
{
    try {
        // Validate recipient email
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            error_log("EMAIL_VALIDATION_FAILED: Invalid recipient email '$to' | Context: $context", E_USER_WARNING);
            return false;
        }

        error_log("EMAIL_INIT: Starting email send to $to | Subject: $subject | Context: $context", E_USER_NOTICE);

        $mail = new PHPMailer(true);

        // Enable SMTP debug for troubleshooting (Set to 2 for detailed logging)
        // 0 = off, 1 = client messages, 2 = client and server messages, 3 = verbose
        $mail->SMTPDebug = 2; // Set to 2 to capture SMTP conversation in logs

        // Server settings
        error_log("EMAIL_SMTP_INIT: Initializing SMTP connection to smtp.gmail.com:587", E_USER_NOTICE);

        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'scycloan@gmail.com';
        $mail->Password = getenv('GMAIL_APP_PASSWORD') ?: 'xbvo zplr dpme ixxj'; // Use environment variable or current app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->Timeout = 30;
        $mail->SMTPKeepAlive = true;

        error_log("EMAIL_CONFIG: SMTP settings configured", E_USER_NOTICE);

        // Set up custom error/debug handler to capture SMTP logs
        $mail->Debugoutput = function ($str, $level) {
            error_log("SMTP_DEBUG_$level: $str", E_USER_NOTICE);
        };

        // Recipients
        $mail->setFrom('scycloan@gmail.com', 'CYCLOAN Support');
        $mail->addAddress($to, $toName);

        error_log("EMAIL_RECIPIENT: Set recipient $to ($toName)", E_USER_NOTICE);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;

        // Plain text alternative
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));

        error_log("EMAIL_CONTENT: Email body prepared (Length: " . strlen($body) . " chars)", E_USER_NOTICE);

        // Try to send the email
        error_log("EMAIL_ATTEMPTING_SEND: About to call PHPMailer->send() for $to with subject: $subject", E_USER_NOTICE);

        try {
            $mailSendResult = $mail->send();
            error_log("EMAIL_SEND_RESULT: PHPMailer->send() returned: " . ($mailSendResult ? 'TRUE' : 'FALSE'), E_USER_WARNING);
        } catch (Exception $sendEx) {
            error_log("EMAIL_SEND_EXCEPTION: Exception during mail send for $to | Error: " . $sendEx->getMessage(), E_USER_WARNING);
            return false;
        }

        if (!$mailSendResult) {
            error_log("EMAIL_SEND_FAILED: PHPMailer error for To: $to | Error: " . $mail->ErrorInfo . " | Context: $context", E_USER_WARNING);
            return false;
        }

        error_log("EMAIL_SUCCESS: Email sent successfully to $to | Subject: $subject", E_USER_NOTICE);
        return true;
    } catch (Exception $e) {
        error_log("EMAIL_EXCEPTION: Exception caught for To: $to | Error: " . $e->getMessage() . " | Code: " . $e->getCode() . " | Context: $context", E_USER_WARNING);
        return false;
    }
}

// ============================================
// POLLING ENDPOINTS (GET REQUESTS)
// Must be placed before HTML output starts
// ============================================

// Handle AJAX request for refreshing due accounts table
if (isset($_GET['action']) && $_GET['action'] === 'get_due_accounts') {
    try {
        $dueAccounts = executeQuery($conn, "
            SELECT ps.due_date, u.first_name, u.last_name, ps.amount, ps.payment_id, la.loan_id, la.application_id
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
            LIMIT 50
        ");

        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'due_accounts' => $dueAccounts]);
        exit;
    } catch (Exception $e) {
        error_log("get_due_accounts error: " . $e->getMessage(), E_USER_WARNING);
        ob_clean();
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to fetch due accounts']);
        exit;
    }
}

// Handle AJAX request for refreshing activity logs table
if (isset($_GET['action']) && $_GET['action'] === 'get_activity_logs') {
    try {
        error_log("ACTIVITY_LOGS_REQUEST: Fetching activity logs via AJAX", E_USER_NOTICE);

        $activityLogs = executeQuery($conn, "
            SELECT al.log_id, al.user_id, al.user_role, al.action_type, al.module, al.description, 
                   al.created_at,
                   COALESCE(u.first_name, a.first_name, a1.first_name, 'System') AS first_name,
                   COALESCE(u.last_name, a.last_name, a1.last_name, '') AS last_name,
                   la.loan_id
            FROM activity_logs al
            LEFT JOIN users1 u ON al.user_id = u.id AND al.user_role = 'User'
            LEFT JOIN admin2 a ON al.user_id = a.id AND al.user_role = 'Admin2'
            LEFT JOIN admin1 a1 ON al.user_id = a1.id AND al.user_role = 'Admin1'
            LEFT JOIN loan_applications la ON al.user_id = la.user_id
            ORDER BY al.created_at DESC
            LIMIT 30
        ");

        error_log("ACTIVITY_LOGS_QUERY: Found " . count($activityLogs) . " activity logs", E_USER_NOTICE);

        // Map action_type and module for each log
        foreach ($activityLogs as &$log) {
            $action = strtolower($log['action_type']);
            $log['action_type_display'] = isset($actionTypeMap[$action]) ? $actionTypeMap[$action] : ucfirst($action);
            $module = strtolower($log['module']);
            $log['module_display'] = isset($moduleMap[$module]) ? $moduleMap[$module] : ucfirst($module);
        }

        ob_clean();
        header('Content-Type: application/json');

        $response = ['success' => true, 'activity_logs' => $activityLogs];
        error_log("ACTIVITY_LOGS_RESPONSE: Sending " . count($activityLogs) . " logs to frontend", E_USER_NOTICE);

        echo json_encode($response);
        exit;
    } catch (Exception $e) {
        error_log("Activity logs fetch failed: " . $e->getMessage(), E_USER_WARNING);
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Failed to fetch activity logs']);
        exit;
    }
}

// Handle AJAX request for refreshing loan applicants table
if (isset($_GET['action']) && $_GET['action'] === 'get_loan_applicants') {
    try {
        // Use LEFT JOIN to handle missing loan_type records gracefully
        $loanApplicants = executeQuery($conn, "
            SELECT la.application_id, u.first_name, u.last_name, 
                   COALESCE(lt.type_name, 'Unknown') AS type_name, 
                   la.amount_applied, la.status, 
                   la.pre_approval_status, la.credit_investigation_status, 
                   la.created_at, la.final_loan_amount
            FROM loan_applications la
            JOIN users1 u ON la.user_id = u.id
            LEFT JOIN loan_types lt ON la.loan_type_id = lt.loan_type_id
            WHERE la.is_archived = 0
            ORDER BY la.created_at DESC
            LIMIT 50
        ");

        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'loan_applicants' => $loanApplicants]);
        exit;
    } catch (Exception $e) {
        error_log("get_loan_applicants error: " . $e->getMessage(), E_USER_WARNING);
        ob_clean();
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to fetch loan applicants']);
        exit;
    }
}

// Handle AJAX request for document rejection reason templates
if (isset($_GET['action']) && $_GET['action'] === 'get_rejection_templates') {
    try {
        // Predefined rejection reason templates for documents
        $rejectionTemplates = [
            [
                'id' => 1,
                'name' => 'Poor Image Quality',
                'reason' => 'The document image is unclear or of poor quality. Please resubmit with a clear, high-resolution image.'
            ],
            [
                'id' => 2,
                'name' => 'Expired Document',
                'reason' => 'The document has expired. Please submit a valid and current document.'
            ],
            [
                'id' => 3,
                'name' => 'Incomplete Information',
                'reason' => 'The document is incomplete or missing required information. Please resubmit with complete details.'
            ],
            [
                'id' => 4,
                'name' => 'Name Mismatch',
                'reason' => 'The name on the document does not match the application. Please verify and resubmit if necessary.'
            ],
            [
                'id' => 5,
                'name' => 'Invalid Document Type',
                'reason' => 'The submitted document is not the required type. Please submit the correct document as specified.'
            ],
            [
                'id' => 6,
                'name' => 'Document Not Legible',
                'reason' => 'The text or details in the document are not clearly readable. Please resubmit with better visibility.'
            ]
        ];

        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'templates' => $rejectionTemplates]);
        exit;
    } catch (Exception $e) {
        error_log("get_rejection_templates error: " . $e->getMessage(), E_USER_WARNING);
        ob_clean();
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to fetch rejection templates']);
        exit;
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
                <img src='assets/Main-Logo.png' alt='CLDD Logo' style='max-width: 200px; height: auto; display: block; margin: 0 auto;'>
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

// Function to send credit investigation status update email
function sendCreditStatusEmail($conn, $applicationId, $newStatus)
{
    $query = "SELECT u.email, u.first_name, u.last_name FROM loan_applications la JOIN users1 u ON la.user_id = u.id WHERE la.application_id = ?";
    $user = executeQuery($conn, $query, "i", [$applicationId]);

    if (empty($user)) {
        error_log("Application data retrieval failed", E_USER_WARNING);
        return false;
    }
    $user = $user[0];

    $to = $user['email'];
    $name = trim($user['first_name'] . ' ' . $user['last_name']);
    $safeStatus = htmlspecialchars($newStatus);

    $statusColor = $newStatus === 'Completed' ? '#2e7d32' : ($newStatus === 'Failed' ? '#d32f2f' : '#fbc02d');

    $content = "
        <p>We have an important update regarding your loan application.</p>
        <div class='highlight-box'>
            <p><strong>Application ID:</strong> $applicationId</p>
            <p><strong>Credit Investigation Status:</strong> <span style='color: $statusColor;'>$safeStatus</span></p>
        </div>
        <p>This update reflects the current status of your credit investigation process.</p>
        <p>If you have any questions or concerns, please don't hesitate to contact our support team.</p>
        <a href='user_dashboard.php' class='button'>View Application Details</a>
        <p>Best regards,<br><strong>The CLDD Loan Support Team</strong></p>
    ";

    $emailBody = generateEmailTemplate($name, $content);
    $subject = "Credit Investigation Status Update - Application #$applicationId";

    return sendEmail($to, $name, $subject, $emailBody, "Credit Investigation: App $applicationId, Status: $newStatus");
}

// Function to determine which email template to use based on pre-approval status
/**
 * TEMPLATE SELECTION FUNCTION - Routes to appropriate email template
 * Determines email content based on pre-approval decision
 */
function selectEmailTemplate($updates = [])
{
    // Check if this is a batch email with document changes
    if (!empty($updates['is_batch']) && $updates['is_batch'] === true) {
        return 'batch_documents';
    }

    if (!empty($updates['pre_approval_status'])) {
        $status = $updates['pre_approval_status'];
        if ($status === 'Approved') {
            return 'approved';
        } elseif ($status === 'Rejected') {
            return 'rejected';
        } else {
            return 'pending';
        }
    }
    return 'default';
}

/**
 * ENHANCED: Queue document changes for batch email sending
 * Stores document status changes in session for consolidated email
 * Integrates with database schema for proper document information
 * 
 * @param string $applicationId Application ID
 * @param int $documentId Document ID
 * @param string $newStatus New document status
 * @param string $rejectionNotes Rejection notes if applicable
 * @param string $adminName Name of admin making the change
 * @return bool True if queued successfully
 */
function queueDocumentChange($applicationId, $documentId, $newStatus, $rejectionNotes = '', $adminName = 'Administrator')
{
    try {
        // Initialize queue if it doesn't exist
        if (!isset($_SESSION['document_changes_queue'])) {
            $_SESSION['document_changes_queue'] = [];
        }

        if (!isset($_SESSION['document_changes_queue'][$applicationId])) {
            $_SESSION['document_changes_queue'][$applicationId] = [
                'changes' => [],
                'admin_name' => $adminName,
                'first_change_time' => date('Y-m-d H:i:s'),
                'last_change_time' => date('Y-m-d H:i:s')
            ];
        }

        // Add or update the document change
        $_SESSION['document_changes_queue'][$applicationId]['changes'][$documentId] = [
            'document_id' => $documentId,
            'status' => $newStatus,
            'rejection_notes' => $rejectionNotes,
            'changed_at' => date('Y-m-d H:i:s'),
            'admin_name' => $adminName
        ];

        // Update last change time
        $_SESSION['document_changes_queue'][$applicationId]['last_change_time'] = date('Y-m-d H:i:s');

        $changeCount = count($_SESSION['document_changes_queue'][$applicationId]['changes']);
        error_log("DOCUMENT_QUEUE: Queued document change - App: $applicationId, Doc: $documentId, Status: $newStatus, Queue size: $changeCount", E_USER_NOTICE);

        return true;

    } catch (Exception $e) {
        error_log("DOCUMENT_QUEUE_ERROR: Failed to queue document change - " . $e->getMessage(), E_USER_WARNING);
        return false;
    }
}

/**
 * ENHANCED: Get comprehensive queue information for an application
 * Returns detailed queue data including statistics and metadata
 * 
 * @param string $applicationId Application ID
 * @return array|null Queue data or null if no queue exists
 */
function getQueueInfo($applicationId)
{
    if (!isset($_SESSION['document_changes_queue'][$applicationId])) {
        return null;
    }

    return $_SESSION['document_changes_queue'][$applicationId];
}

/**
 * ENHANCED: Get detailed queue status for admin dashboard
 * Provides comprehensive queue information for display purposes
 * 
 * @param string $applicationId Application ID
 * @return array Detailed queue status
 */
function getDetailedQueueStatus($applicationId)
{
    if (!isset($_SESSION['document_changes_queue'][$applicationId])) {
        return [
            'has_queue' => false,
            'queue_count' => 0,
            'message' => 'No queued changes for this application'
        ];
    }

    $queueData = $_SESSION['document_changes_queue'][$applicationId];
    $changes = $queueData['changes'] ?? [];

    // Generate descriptive message
    $totalChanges = $queueData['total_changes'] ?? count($changes);
    $rejectedCount = $queueData['rejected_count'] ?? 0;
    $approvedCount = $queueData['approved_count'] ?? 0;
    $pendingCount = $queueData['pending_count'] ?? 0;

    $messages = [];
    if ($rejectedCount > 0)
        $messages[] = "$rejectedCount rejected";
    if ($approvedCount > 0)
        $messages[] = "$approvedCount approved";
    if ($pendingCount > 0)
        $messages[] = "$pendingCount pending";

    $statusText = implode(', ', $messages);
    $message = $totalChanges > 0 ? "$totalChanges document(s) queued ($statusText). Ready to send consolidated email." : "No document changes queued.";

    return [
        'has_queue' => true,
        'queue_count' => count($changes),
        'total_changes' => $totalChanges,
        'approved_count' => $approvedCount,
        'rejected_count' => $rejectedCount,
        'pending_count' => $pendingCount,
        'has_rejections' => $queueData['has_rejections'] ?? false,
        'has_approvals' => $queueData['has_approvals'] ?? false,
        'queue_status' => $queueData['queue_status'] ?? 'accumulating',
        'first_change_time' => $queueData['first_change_time'] ?? null,
        'last_update_time' => $queueData['last_update_time'] ?? null,
        'admin_name' => $queueData['admin_name'] ?? 'Unknown',
        'auto_accumulate' => $queueData['auto_accumulate'] ?? true,
        'manual_send_only' => $queueData['manual_send_only'] ?? true,
        'changes_summary' => array_map(function ($change) {
            return [
                'document_id' => $change['document_id'],
                'document_name' => $change['document_name'],
                'previous_status' => $change['previous_status'] ?? $change['old_status'],
                'new_status' => $change['new_status'],
                'has_rejection' => $change['has_rejection_reason'] ?? false,
                'rejection_reason' => $change['rejection_reason'] ?? null,
                'formatted_time' => $change['formatted_time'],
                'status_icon' => $change['status_icon']
            ];
        }, $changes),
        'message' => $message
    ];
}

/**
 * ENHANCED: Clear queue for a specific application
 * Removes all queued changes for an application after successful email send
 * 
 * @param string $applicationId Application ID
 * @return bool True if cleared successfully
 */
function clearQueue($applicationId)
{
    if (isset($_SESSION['document_changes_queue'][$applicationId])) {
        unset($_SESSION['document_changes_queue'][$applicationId]);
        error_log("ENHANCED_QUEUE_CLEAR: Cleared queue for application $applicationId", E_USER_NOTICE);
        return true;
    }
    return false;
}

/**
 * ENHANCED: Get all applications with queued changes
 * Returns array of application IDs that have pending changes
 * 
 * @return array Array of application IDs with queued changes
 */
function getApplicationsWithQueuedChanges()
{
    if (!isset($_SESSION['document_changes_queue'])) {
        return [];
    }

    return array_keys($_SESSION['document_changes_queue']);
}

/**
 * ENHANCED: Get queue statistics across all applications
 * Returns comprehensive statistics about all queued changes
 * 
 * @return array Statistics array
 */
function getGlobalQueueStats()
{
    if (!isset($_SESSION['document_changes_queue'])) {
        return [
            'total_applications' => 0,
            'total_changes' => 0,
            'total_approved' => 0,
            'total_rejected' => 0,
            'total_pending' => 0
        ];
    }

    $stats = [
        'total_applications' => 0,
        'total_changes' => 0,
        'total_approved' => 0,
        'total_rejected' => 0,
        'total_pending' => 0,
        'applications' => []
    ];

    foreach ($_SESSION['document_changes_queue'] as $appId => $queueData) {
        $stats['total_applications']++;
        $stats['total_changes'] += $queueData['total_changes'] ?? count($queueData['changes'] ?? []);
        $stats['total_approved'] += $queueData['approved_count'] ?? 0;
        $stats['total_rejected'] += $queueData['rejected_count'] ?? 0;
        $stats['total_pending'] += $queueData['pending_count'] ?? 0;

        $stats['applications'][$appId] = [
            'changes' => $queueData['total_changes'] ?? count($queueData['changes'] ?? []),
            'approved' => $queueData['approved_count'] ?? 0,
            'rejected' => $queueData['rejected_count'] ?? 0,
            'pending' => $queueData['pending_count'] ?? 0,
            'last_update' => $queueData['formatted_last_time'] ?? $queueData['last_update_time'] ?? 'Unknown'
        ];
    }

    return $stats;
}

/**
 * ENHANCED: Get queued document changes count
 * Returns the number of queued document changes for an application
 * 
 * @param string $applicationId Application ID
 * @return int Number of queued changes
 */
function getQueuedChangesCount($applicationId)
{
    if (isset($_SESSION['document_changes_queue'][$applicationId]['changes'])) {
        return count($_SESSION['document_changes_queue'][$applicationId]['changes']);
    }
    return 0;
}

// Function to send pre-approval status update email
/**
 * ENHANCED CONSOLIDATED EMAIL FUNCTION - Sends comprehensive email with all updates
 * Combines: Pre-Approval Status + Document Updates + Remarks + Admin Info into ONE email
 * Now includes admin name, timestamp, complete document review summary, and decision reasoning
 * ENHANCED: Different templates based on pre-approval decision with enhanced database integration
 */
function sendConsolidatedUpdateEmail($conn, $applicationId, $updates = [])
{
    try {
        error_log("CONSOLIDATED_EMAIL_START: Processing email for App ID: $applicationId", E_USER_NOTICE);

        $query = "SELECT u.email, u.first_name, u.last_name FROM loan_applications la JOIN users1 u ON la.user_id = u.id WHERE la.application_id = ?";
        $user = executeQuery($conn, $query, "s", [$applicationId]);

        if (empty($user)) {
            error_log("CONSOLIDATED_EMAIL_ERROR: Application data retrieval failed for App ID: $applicationId", E_USER_WARNING);
            return false;
        }
        $user = $user[0];

        $to = $user['email'];
        $name = trim($user['first_name'] . ' ' . $user['last_name']);

        error_log("CONSOLIDATED_EMAIL_USER: Retrieved user - Name: $name, Email: $to", E_USER_NOTICE);

        // Validate email address
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            error_log("CONSOLIDATED_EMAIL_VALIDATION_ERROR: Invalid email address '$to' for App ID: $applicationId", E_USER_WARNING);
            return false;
        }

        // DETERMINE WHICH TEMPLATE TO USE
        $template = 'batch_documents';
        if (!empty($updates['template'])) {
            $template = $updates['template'];
        } elseif (!empty($updates['pre_approval_status'])) {
            $template = strtolower($updates['pre_approval_status']);
        } elseif (!empty($updates['document_status'])) {
            $template = 'document_update';
        } elseif (!empty($updates['document_changes']) && is_array($updates['document_changes'])) {
            $template = 'batch_documents';
        }
        error_log("CONSOLIDATED_EMAIL_TEMPLATE: Selected template: $template", E_USER_NOTICE);

        // Start building the email with professional header
        $content = "
        <div style='background: linear-gradient(135deg, #1b5e20 0%, #2d7d32 100%); color: white; padding: 24px; border-radius: 8px 8px 0 0; text-align: center;'>
            <h1 style='margin: 0; font-size: 24px; font-weight: 700;'>CYCLOAN</h1>
            <p style='margin: 4px 0 0 0; font-size: 14px; opacity: 0.95;'>Loan Application Update</p>
        </div>
        
        <div style='padding: 24px; background: white; font-family: \"Segoe UI\", Roboto, Arial, sans-serif;'>";

        if ($template === 'batch_documents') {
            // ENHANCED BATCH DOCUMENTS EMAIL - Multiple document status changes with detailed information
            $batchCount = isset($updates['batch_count']) ? (int) $updates['batch_count'] : 0;
            $totalChanges = isset($updates['total_changes']) ? (int) $updates['total_changes'] : $batchCount;
            $hasRejections = isset($updates['has_rejections']) ? $updates['has_rejections'] : false;
            $hasApprovals = isset($updates['has_approvals']) ? $updates['has_approvals'] : false;

            $content .= "
        <p style='color: #333; font-size: 16px; font-weight: 600; margin: 0 0 8px 0;'>Hi $name! 👋</p>
        <p style='color: #666; font-size: 14px; line-height: 1.8; margin: 0 0 16px 0;'>We're writing to inform you about updates to your loan application. We've reviewed <strong>$batchCount document(s)</strong> and updated their <strong>status</strong> accordingly.</p>
        
        <p style='color: #666; font-size: 14px; line-height: 1.8; margin: 0 0 16px 0;'>Please check the details below to see what has changed.</p>";

            // Add reviewer information section (like in the screenshot)
            $reviewerName = $updates['admin_name'] ?? 'Ken Solloza';
            $reviewDate = isset($updates['admin_updated_at']) ? date('F j, Y \a\t g:i A', strtotime($updates['admin_updated_at'])) : date('F j, Y \a\t g:i A');

            $content .= "
        <div style='margin: 20px 0; padding: 16px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #17a2b8;'>
            <p style='margin: 0; color: #17a2b8; font-size: 14px; font-weight: 600;'>👤 Review Information</p>
            <p style='margin: 8px 0 0 0; color: #333; font-size: 13px;'><strong>Reviewed by:</strong> $reviewerName</p>
            <p style='margin: 4px 0 0 0; color: #333; font-size: 13px;'><strong>Review Date:</strong> $reviewDate</p>
        </div>";

            // Add summary box with enhanced styling
            if ($hasRejections && $hasApprovals) {
                $content .= "<div style='padding: 16px; background: linear-gradient(135deg, #fff3e0, #ffe8cc); border-left: 5px solid #ff9800; margin-bottom: 16px; border-radius: 6px;'><p style='color: #e65100; font-size: 14px; line-height: 1.8; margin: 0; font-weight: 600;'><strong>🔄 Mixed Results:</strong> Some documents have been approved while others require attention. Please see the detailed review below.</p></div>";
            } elseif ($hasRejections) {
                $content .= "<div style='padding: 16px; background: linear-gradient(135deg, #ffebee, #fce4ec); border-left: 5px solid #f44336; margin-bottom: 16px; border-radius: 6px;'><p style='color: #d32f2f; font-size: 14px; line-height: 1.8; margin: 0; font-weight: 600;'><strong>⚠️ Action Required:</strong> Some documents need to be resubmitted. Please review the feedback below carefully.</p></div>";
            } elseif ($hasApprovals) {
                $content .= "<div style='padding: 16px; background: linear-gradient(135deg, #e8f5e9, #f1f8e9); border-left: 5px solid #4caf50; margin-bottom: 16px; border-radius: 6px;'><p style='color: #2e7d32; font-size: 14px; line-height: 1.8; margin: 0; font-weight: 600;'><strong>✅ Good News:</strong> All reviewed documents have been approved! Your application is moving forward.</p></div>";
            }
        } elseif ($template === 'approved') {
            $content .= "
        <p style='color: #2e7d32; font-size: 16px; font-weight: 700; margin: 0 0 8px 0;'>✅ Great News! Your Application Has Been Approved</p>
        <p style='color: #333; font-size: 14px; line-height: 1.8; margin: 0 0 8px 0;'>Hi $name,</p>
        <p style='color: #666; font-size: 14px; line-height: 1.8; margin: 0;'>Congratulations! We're pleased to inform you that your loan application has been <strong style='color: #2e7d32;'>APPROVED</strong> for pre-approval. All required documents have been verified and your application meets our lending criteria. This is an important milestone in your loan journey!</p>";
        } elseif ($template === 'rejected') {
            $content .= "
        <p style='color: #d32f2f; font-size: 16px; font-weight: 700; margin: 0 0 8px 0;'>📋 Application Review Decision</p>
        <p style='color: #333; font-size: 14px; line-height: 1.8; margin: 0 0 8px 0;'>Hi $name,</p>
        <p style='color: #666; font-size: 14px; line-height: 1.8; margin: 0;'>Thank you for submitting your loan application. After a comprehensive review of your documents and financial information, we're unable to approve your application at this time. Please review the details below to understand the reasons for this decision.</p>";
        } elseif ($template === 'pending') {
            $content .= "
        <p style='color: #f57f17; font-size: 16px; font-weight: 700; margin: 0 0 8px 0;'>⏳ Your Application is Under Review</p>
        <p style='color: #333; font-size: 14px; line-height: 1.8; margin: 0 0 8px 0;'>Hi $name,</p>
        <p style='color: #666; font-size: 14px; line-height: 1.8; margin: 0;'>Your loan application is currently undergoing review. Our team is carefully evaluating your documents and financial information. We'll send you a final decision notification soon.</p>";
        } elseif ($template === 'document_update') {
            $docStatus = $updates['document_status'] ?? 'Updated';
            $statusEmoji = $docStatus === 'Approved' ? '✅' : ($docStatus === 'Rejected' ? '❌' : '📋');
            $content .= "
        <p style='color: #333; font-size: 16px; font-weight: 700; margin: 0 0 8px 0;'>{$statusEmoji} Document Status Update</p>
        <p style='color: #333; font-size: 14px; line-height: 1.8; margin: 0 0 8px 0;'>Hi $name,</p>
        <p style='color: #666; font-size: 14px; line-height: 1.8; margin: 0;'>We have reviewed one of your submitted documents and updated its status. Please see the details below.</p>";
        } else {
            $content .= "
        <p style='color: #333; font-size: 16px; font-weight: 600; margin: 0 0 8px 0;'>Hi $name!</p>
        <p style='color: #666; font-size: 14px; line-height: 1.8; margin: 0;'>Your loan application has been reviewed and updated. Please check the summary below for details.</p>";
        }

        // Add UPDATE INFORMATION
        if (!empty($updates['admin_name']) || !empty($updates['admin_updated_at'])) {
            $adminName = !empty($updates['admin_name']) ? htmlspecialchars($updates['admin_name']) : 'System';
            $timestamp = !empty($updates['admin_updated_at']) ? $updates['admin_updated_at'] : date('F j, Y \a\t g:i A');

            $content .= "
        <div style='margin: 24px 0; padding: 16px; background: #f5f5f5; border-left: 4px solid #999; border-radius: 4px;'>
            <p style='margin: 0; font-size: 12px; color: #666; line-height: 1.8;'>
                <strong style='color: #333;'>👤 Review Information</strong><br>
                <strong>Reviewed by:</strong> $adminName<br>
                <strong>Review Date:</strong> $timestamp
            </p>
        </div>";
        }

        // ADD TEMPLATE-SPECIFIC INFORMATION
        if ($template === 'approved') {
            $content .= "
        <div style='margin: 24px 0; padding: 16px; background: linear-gradient(135deg, #e8f5e9 0%, #f1f8f6 100%); border-left: 6px solid #2e7d32; border-radius: 4px;'>
            <h3 style='margin: 0 0 12px 0; color: #1b5e20; font-size: 16px; font-weight: 700;'>✅ Pre-Approval Status</h3>
            <p style='margin: 8px 0; font-size: 14px;'><strong>Decision:</strong> <span style='color: #2e7d32; font-weight: 700; font-size: 15px;'>APPROVED</span></p>
            <p style='margin: 8px 0; font-size: 12px; color: #666;'><strong>Application ID:</strong> $applicationId</p>
            <p style='margin: 8px 0; color: #555; line-height: 1.6; font-size: 13px;'>Your application has successfully passed our pre-approval review process. All required documentation has been verified and your financial profile meets our lending standards.</p>
            <p style='margin: 10px 0 0 0; font-size: 13px; color: #2e7d32; font-weight: 600;'>⬜ Next Step: Loan Activation & Disbursement</p>
        </div>";
        } elseif ($template === 'rejected') {
            $content .= "
        <div style='margin: 24px 0; padding: 16px; background: linear-gradient(135deg, #ffebee 0%, #fff5f7 100%); border-left: 6px solid #d32f2f; border-radius: 4px;'>
            <h3 style='margin: 0 0 12px 0; color: #b71c1c; font-size: 16px; font-weight: 700;'>❌ Application Decision</h3>
            <p style='margin: 8px 0; font-size: 14px;'><strong>Decision:</strong> <span style='color: #d32f2f; font-weight: 700; font-size: 15px;'>NOT APPROVED</span></p>
            <p style='margin: 8px 0; font-size: 12px; color: #666;'><strong>Application ID:</strong> $applicationId</p>
            <p style='margin: 8px 0; color: #555; line-height: 1.6; font-size: 13px;'>Your application could not be approved based on our review criteria. Please review the details below to understand the reasons.</p>
            <p style='margin: 12px 0 0 0; font-size: 13px;'><strong style='color: #d32f2f;'>📌 What You Can Do:</strong></p>
            <ul style='margin: 6px 0 0 16px; color: #555; line-height: 1.8; font-size: 12px;'>
                <li>Contact our support team to discuss options</li>
                <li>Reapply if your situation has improved</li>
                <li>Request a formal appeal review</li>
            </ul>
        </div>";
        } elseif ($template === 'pending') {
            $content .= "
        <div style='margin: 24px 0; padding: 16px; background: linear-gradient(135deg, #fff8e1 0%, #fffbf0 100%); border-left: 6px solid #f57f17; border-radius: 4px;'>
            <h3 style='margin: 0 0 12px 0; color: #e65100; font-size: 16px; font-weight: 700;'>⏳ Review Status Update</h3>
            <p style='margin: 8px 0; font-size: 14px;'><strong>Current Status:</strong> <span style='color: #f57f17; font-weight: 700; font-size: 15px;'>UNDER REVIEW</span></p>
            <p style='margin: 8px 0; font-size: 12px; color: #666;'><strong>Application ID:</strong> $applicationId</p>
            <p style='margin: 8px 0; color: #555; line-height: 1.6; font-size: 13px;'>Our review team is carefully evaluating your documents and financial information. A final decision will be sent to you shortly.</p>
            <p style='margin: 8px 0; font-size: 12px;'><strong>⏱️ Expected Timeline:</strong> 3-5 business days</p>
        </div>";
        }

        // ENHANCED: Add BATCH DOCUMENT CHANGES with proper database integration
        if ($template === 'batch_documents' && !empty($updates['document_changes']) && is_array($updates['document_changes'])) {
            error_log("ENHANCED_BATCH_DOCUMENTS_EMAIL: Building document changes section. Document count: " . count($updates['document_changes']), E_USER_NOTICE);

            $content .= "
        <div style='margin: 24px 0;'>
            <div style='background: linear-gradient(135deg, #1b5e20, #2e7d32); color: white; padding: 16px; border-radius: 8px 8px 0 0; margin-bottom: 0;'>
                <h3 style='color: white; margin: 0; font-size: 18px; font-weight: 700; display: flex; align-items: center;'>
                    📋 Document Status Changes
                </h3>
                <p style='margin: 8px 0 0 0; color: #e8f5e9; font-size: 13px; opacity: 0.9;'>
                    <strong>Total Changes:</strong> $totalChanges document(s)
                </p>
            </div>";

            foreach ($updates['document_changes'] as $doc) {
                $docName = htmlspecialchars($doc['document_name'] ?? 'Unknown Document');
                $docStatus = htmlspecialchars($doc['new_status'] ?? $doc['status'] ?? 'Unknown');
                $previousStatus = htmlspecialchars($doc['previous_status'] ?? 'Pending');
                $docNotes = htmlspecialchars($doc['rejection_reason'] ?? $doc['rejection_notes'] ?? '');
                $docUpdated = $doc['status_updated_at'] ?? $doc['formatted_time'] ?? date('Y-m-d H:i:s');
                $docDescription = htmlspecialchars($doc['description'] ?? '');

                error_log("ENHANCED_BATCH_DOCUMENTS_EMAIL: Processing document - Name: $docName, Status: $docStatus, Previous: $previousStatus", E_USER_NOTICE);

                // Enhanced status styling with icons - improved colors to match attachment design
                $statusIcon = $doc['status_icon'] ?? ($docStatus === 'Approved' ? '✅' : ($docStatus === 'Rejected' ? '❌' : '⏳'));
                $statusColor = $doc['status_color'] ?? ($docStatus === 'Approved' ? '#ffffff' : ($docStatus === 'Rejected' ? '#ffffff' : '#ffffff'));
                $statusBg = $docStatus === 'Approved' ? 'linear-gradient(135deg, #4caf50, #66bb6a)' : ($docStatus === 'Rejected' ? 'linear-gradient(135deg, #f44336, #ef5350)' : 'linear-gradient(135deg, #ff9800, #ffb74d)');
                $borderColor = $docStatus === 'Approved' ? '#4caf50' : ($docStatus === 'Rejected' ? '#f44336' : '#ff9800');
                $cardBgColor = $docStatus === 'Approved' ? '#f8fdf8' : ($docStatus === 'Rejected' ? '#fff8f8' : '#fffaf0');

                // Enhanced mobile-friendly card layout with improved styling
                $content .= "
            <div style='margin: 16px 0; padding: 18px; background: $cardBgColor; border-radius: 10px; border-left: 5px solid $borderColor; box-shadow: 0 3px 8px rgba(0,0,0,0.12); transition: all 0.2s ease;'>
                <div style='display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;'>
                    <div style='flex: 1;'>
                        <h4 style='margin: 0 0 6px 0; font-weight: 700; color: #1b5e20; font-size: 15px;'>$docName</h4>
                        <p style='margin: 0 0 4px 0; font-size: 12px; color: #666; line-height: 1.4;'>$docDescription</p>
                        <p style='margin: 0; font-size: 11px; color: #888;'><strong>Previous:</strong> <span style='color: #999; text-decoration: line-through;'>$previousStatus</span></p>
                        <p style='margin: 2px 0 0 0; font-size: 11px; color: #555;'><strong>Updated:</strong> $docUpdated</p>
                    </div>
                    <span style='padding: 10px 16px; background: $statusBg; color: $statusColor; border-radius: 25px; font-weight: 700; font-size: 13px; display: inline-block; white-space: nowrap; margin-left: 12px; box-shadow: 0 2px 6px rgba(0,0,0,0.15); text-shadow: 0 1px 2px rgba(0,0,0,0.1);'>$statusIcon $docStatus</span>
                </div>";

                // Add rejection notes or approval message with improved styling
                if ($docStatus === 'Rejected' && $docNotes) {
                    // Limit notes display and add read more if needed
                    $displayNotes = strlen($docNotes) > 120 ? substr($docNotes, 0, 120) . '...' : $docNotes;
                    $content .= "
                <div style='margin: 14px 0 0 0; padding: 12px; background: linear-gradient(135deg, #ffebee, #fce4ec); border-radius: 6px; border-left: 4px solid #f44336; box-shadow: inset 0 1px 3px rgba(244,67,54,0.1);'>
                    <p style='margin: 0 0 6px 0; color: #d32f2f; font-size: 13px; font-weight: 700;'>📝 Note:</p>
                    <p style='margin: 0; color: #666; font-size: 12px; line-height: 1.6; font-style: italic;'>$displayNotes</p>
                </div>";
                } elseif ($docStatus === 'Approved') {
                    $content .= "
                <div style='margin: 14px 0 0 0; padding: 12px; background: linear-gradient(135deg, #e8f5e9, #f1f8e9); border-radius: 6px; border-left: 4px solid #4caf50; box-shadow: inset 0 1px 3px rgba(76,175,80,0.1);'>
                    <p style='margin: 0; color: #2e7d32; font-size: 13px; font-weight: 700;'>✨ This document has been verified and approved!</p>
                </div>";
                }

                $content .= "</div>";
            }

            $content .= "</div>";
        } else {
            error_log("ENHANCED_BATCH_DOCUMENTS_EMAIL: Document section NOT rendered. Template: $template, Has document_changes: " . (!empty($updates['document_changes']) ? 'yes' : 'no') . ", Is array: " . (is_array($updates['document_changes']) ? 'yes' : 'no'), E_USER_WARNING);
        }

        // Add DOCUMENT REVIEW SUMMARY (list all documents with their status)
        if (!empty($updates['all_documents']) && is_array($updates['all_documents'])) {
            $approved = array_filter($updates['all_documents'], function ($d) {
                return strtolower($d['status']) === 'approved';
            });
            $rejected = array_filter($updates['all_documents'], function ($d) {
                return strtolower($d['status']) === 'rejected';
            });
            $pending = array_filter($updates['all_documents'], function ($d) {
                return strtolower($d['status']) === 'pending';
            });

            $content .= "
        <div style='margin: 24px 0;'>
            <h3 style='color: #1b5e20; border-bottom: 3px solid #2e7d32; padding-bottom: 10px; font-size: 16px; font-weight: 700;'>📁 Document Status Overview</h3>
            
            <div style='margin-top: 16px; display: grid; gap: 12px;'>";

            // Approved documents
            if (!empty($approved)) {
                $content .= "
            <div style='padding: 12px 14px; background: linear-gradient(135deg, #e8f5e9 0%, #f1f8f6 100%); border-left: 4px solid #2e7d32; border-radius: 4px;'>
                <p style='margin: 0 0 6px 0; color: #1b5e20; font-weight: 700; font-size: 13px;'>✅ Approved Documents (" . count($approved) . ")</p>";
                foreach ($approved as $doc) {
                    $docName = htmlspecialchars($doc['name']);
                    $content .= "<p style='margin: 4px 0 0 0; font-size: 12px; color: #555;'>• $docName</p>";
                }
                $content .= "</div>";
            }

            // Rejected documents
            if (!empty($rejected)) {
                $content .= "
            <div style='padding: 12px 14px; background: linear-gradient(135deg, #ffebee 0%, #fff5f7 100%); border-left: 4px solid #d32f2f; border-radius: 4px;'>
                <p style='margin: 0 0 6px 0; color: #b71c1c; font-weight: 700; font-size: 13px;'>❌ Rejected Documents (" . count($rejected) . ")</p>";
                foreach ($rejected as $doc) {
                    $docName = htmlspecialchars($doc['name']);
                    $remark = !empty($doc['remark']) ? ' - ' . htmlspecialchars($doc['remark']) : '';
                    $content .= "<p style='margin: 4px 0 0 0; font-size: 12px; color: #555;'>• $docName$remark</p>";
                }
                $content .= "</div>";
            }

            // Pending documents
            if (!empty($pending)) {
                $content .= "
            <div style='padding: 12px 14px; background: linear-gradient(135deg, #fff8e1 0%, #fffbf0 100%); border-left: 4px solid #f57f17; border-radius: 4px;'>
                <p style='margin: 0 0 6px 0; color: #e65100; font-weight: 700; font-size: 13px;'>⏳ Pending Documents (" . count($pending) . ")</p>";
                foreach ($pending as $doc) {
                    $docName = htmlspecialchars($doc['name']);
                    $content .= "<p style='margin: 4px 0 0 0; font-size: 12px; color: #555;'>• $docName</p>";
                }
                $content .= "</div>";
            }

            $content .= "</div></div>";
        }

        // Add PRE-APPROVAL STATUS if present
        if (!empty($updates['pre_approval_status'])) {
            $status = $updates['pre_approval_status'];
            $statusColor = $status === 'Approved' ? '#2e7d32' : ($status === 'Rejected' ? '#d32f2f' : '#f57f17');
            $statusIcon = $status === 'Approved' ? '✅' : ($status === 'Rejected' ? '❌' : '⏳');
            $statusBg = $status === 'Approved' ? '#e8f5e9' : ($status === 'Rejected' ? '#ffebee' : '#fff8e1');

            $content .= "
        <div style='margin: 24px 0; padding: 16px; border-left: 6px solid $statusColor; background: $statusBg; border-radius: 4px;'>
            <h3 style='margin: 0 0 12px 0; color: $statusColor; font-size: 16px; font-weight: 700;'>$statusIcon Pre-Approval Status</h3>
            <p style='margin: 8px 0;'><strong>Status:</strong> <span style='color: $statusColor; font-weight: 700; font-size: 15px;'>$status</span></p>
            <p style='margin: 8px 0; font-size: 13px;'><strong>Application ID:</strong> $applicationId</p>";

            // Show summary message based on template (additional context)
            if ($status === 'Approved') {
                $content .= "<p style='margin: 8px 0; color: #1b5e20; font-size: 13px;'>All documents have been verified and your pre-approval decision is complete. You will receive further updates regarding the next phase of your loan process.</p>";
            } elseif ($status === 'Rejected') {
                $content .= "<p style='margin: 8px 0; color: #d32f2f; font-size: 13px;'>Please review the decision notes below for details about why your application could not be approved.</p>";
            } else {
                $content .= "<p style='margin: 8px 0; color: #666; font-size: 13px;'>Our team continues to review your application. You will be notified once a decision has been made.</p>";
            }

            $content .= "</div>";
        }

        // Add APPROVAL REASON if present (custom reason for approval/rejection)
        if (!empty($updates['approval_reason'])) {
            $reason = htmlspecialchars($updates['approval_reason']);
            $reasonBg = isset($updates['pre_approval_status']) && $updates['pre_approval_status'] === 'Rejected' ? '#ffebee' : '#e8f5e9';
            $reasonBorder = isset($updates['pre_approval_status']) && $updates['pre_approval_status'] === 'Rejected' ? '#d32f2f' : '#2e7d32';
            $reasonIcon = isset($updates['pre_approval_status']) && $updates['pre_approval_status'] === 'Rejected' ? '❌' : '✅';
            $reasonTitle = isset($updates['pre_approval_status']) && $updates['pre_approval_status'] === 'Rejected' ? 'Rejection Reason' : 'Approval Basis';

            $content .= "
        <div style='margin: 15px 0; padding: 12px; background: $reasonBg; border-left: 4px solid $reasonBorder; border-radius: 4px;'>
            <h4 style='margin: 0 0 8px 0; color: $reasonBorder; font-size: 14px; font-weight: 600;'>$reasonIcon $reasonTitle</h4>
            <p style='margin: 5px 0; color: #333; line-height: 1.6; font-size: 13px;'>$reason</p>
        </div>";
        }

        // ENHANCED: Add COMPREHENSIVE DECISION REASONING section
        if (!empty($updates['decision_reasoning']) || !empty($updates['approval_criteria']) || !empty($updates['risk_assessment'])) {
            error_log("CONSOLIDATED_EMAIL: Adding decision reasoning section", E_USER_NOTICE);

            $decisionStatus = $updates['pre_approval_status'] ?? 'Review';
            $isApproved = $decisionStatus === 'Approved';
            $isRejected = $decisionStatus === 'Rejected';

            $bgColor = $isApproved ? '#f1f8e9' : ($isRejected ? '#fff5f5' : '#fff8e1');
            $borderColor = $isApproved ? '#4caf50' : ($isRejected ? '#f44336' : '#ff9800');
            $headerColor = $isApproved ? '#2e7d32' : ($isRejected ? '#d32f2f' : '#f57f17');
            $icon = $isApproved ? '✅' : ($isRejected ? '⚠️' : '📋');
            $sectionTitle = $isApproved ? 'Approval Decision Summary' : ($isRejected ? 'Decision Analysis & Review Notes' : 'Review Progress Summary');

            $content .= "
        <div style='margin: 24px 0; padding: 18px; background: $bgColor; border-left: 6px solid $borderColor; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
            <h3 style='margin: 0 0 16px 0; color: $headerColor; font-size: 16px; font-weight: 700; border-bottom: 2px solid $borderColor; padding-bottom: 8px;'>$icon $sectionTitle</h3>";

            // Main decision reasoning
            if (!empty($updates['decision_reasoning'])) {
                $reasoning = htmlspecialchars($updates['decision_reasoning']);
                $content .= "
            <div style='margin: 12px 0; padding: 12px; background: #ffffff; border-radius: 4px; border-left: 3px solid $borderColor;'>
                <h4 style='margin: 0 0 8px 0; color: $headerColor; font-size: 13px; font-weight: 600;'>📝 Decision Rationale</h4>
                <p style='margin: 0; color: #333; line-height: 1.6; font-size: 13px;'>$reasoning</p>
            </div>";
            }

            // Approval criteria (for approved applications)
            if (!empty($updates['approval_criteria'])) {
                $criteria = htmlspecialchars($updates['approval_criteria']);
                $content .= "
            <div style='margin: 12px 0; padding: 12px; background: #ffffff; border-radius: 4px; border-left: 3px solid #4caf50;'>
                <h4 style='margin: 0 0 8px 0; color: #2e7d32; font-size: 13px; font-weight: 600;'>✅ Meeting Approval Standards</h4>
                <p style='margin: 0; color: #333; line-height: 1.6; font-size: 13px;'>$criteria</p>
            </div>";
            }

            // Risk assessment
            if (!empty($updates['risk_assessment'])) {
                $riskAssessment = htmlspecialchars($updates['risk_assessment']);
                $riskColor = $isApproved ? '#2e7d32' : ($isRejected ? '#d32f2f' : '#f57f17');
                $riskTitle = $isApproved ? 'Risk Profile Assessment' : ($isRejected ? 'Risk Considerations' : 'Ongoing Risk Review');
                $riskIcon = $isApproved ? '🛡️' : ($isRejected ? '⚠️' : '📊');

                $content .= "
            <div style='margin: 12px 0; padding: 12px; background: #ffffff; border-radius: 4px; border-left: 3px solid $riskColor;'>
                <h4 style='margin: 0 0 8px 0; color: $riskColor; font-size: 13px; font-weight: 600;'>$riskIcon $riskTitle</h4>
                <p style='margin: 0; color: #333; line-height: 1.6; font-size: 13px;'>$riskAssessment</p>
            </div>";
            }

            // Financial evaluation summary
            if (!empty($updates['financial_summary'])) {
                $financialSummary = htmlspecialchars($updates['financial_summary']);
                $content .= "
            <div style='margin: 12px 0; padding: 12px; background: #ffffff; border-radius: 4px; border-left: 3px solid #1976d2;'>
                <h4 style='margin: 0 0 8px 0; color: #1976d2; font-size: 13px; font-weight: 600;'>💰 Financial Assessment</h4>
                <p style='margin: 0; color: #333; line-height: 1.6; font-size: 13px;'>$financialSummary</p>
            </div>";
            }

            // Credit evaluation
            if (!empty($updates['credit_evaluation'])) {
                $creditEval = htmlspecialchars($updates['credit_evaluation']);
                $content .= "
            <div style='margin: 12px 0; padding: 12px; background: #ffffff; border-radius: 4px; border-left: 3px solid #7b1fa2;'>
                <h4 style='margin: 0 0 8px 0; color: #7b1fa2; font-size: 13px; font-weight: 600;'>📈 Credit Profile Review</h4>
                <p style='margin: 0; color: #333; line-height: 1.6; font-size: 13px;'>$creditEval</p>
            </div>";
            }

            // Next steps or recommendations
            if (!empty($updates['next_steps'])) {
                $nextSteps = htmlspecialchars($updates['next_steps']);
                $stepIcon = $isApproved ? '🚀' : ($isRejected ? '🔄' : '⏳');
                $stepTitle = $isApproved ? 'Next Steps' : ($isRejected ? 'Recommended Actions' : 'What Happens Next');

                $content .= "
            <div style='margin: 12px 0; padding: 12px; background: #ffffff; border-radius: 4px; border-left: 3px solid $borderColor;'>
                <h4 style='margin: 0 0 8px 0; color: $headerColor; font-size: 13px; font-weight: 600;'>$stepIcon $stepTitle</h4>
                <p style='margin: 0; color: #333; line-height: 1.6; font-size: 13px;'>$nextSteps</p>
            </div>";
            }

            $content .= "</div>";
        }

        // Add SPECIAL REJECTION SUMMARY if this is a rejection (enhanced display for rejected applications)
        if (isset($updates['pre_approval_status']) && $updates['pre_approval_status'] === 'Rejected') {
            // Count rejected documents
            $rejectedDocs = [];
            if (!empty($updates['all_documents']) && is_array($updates['all_documents'])) {
                $rejectedDocs = array_filter($updates['all_documents'], function ($d) {
                    return strtolower($d['status']) === 'rejected';
                });
            }

            if (!empty($rejectedDocs)) {
                $content .= "
            <div style='margin: 20px 0; padding: 15px; background: #ffebee; border: 2px solid #d32f2f; border-radius: 6px;'>
                <h3 style='margin: 0 0 12px 0; color: #b71c1c; font-size: 14px; font-weight: 600;'>⚠️ Rejected Documents Requiring Attention</h3>
                <p style='margin: 10px 0; color: #333; font-size: 12px; line-height: 1.6;'>The following documents did not meet our verification requirements:</p>";

                foreach ($rejectedDocs as $doc) {
                    $docName = htmlspecialchars($doc['name']);
                    $remark = !empty($doc['rejection_notes']) ? htmlspecialchars($doc['rejection_notes']) : 'Verification criteria not met';

                    $content .= "
                <div style='margin: 10px 0; padding: 10px; background: #fff5f5; border-radius: 4px; border-left: 3px solid #d32f2f;'>
                    <p style='margin: 0 0 5px 0; color: #b71c1c; font-weight: 600; font-size: 12px;'>📄 $docName</p>
                    <p style='margin: 0; color: #666; font-size: 12px;'>$remark</p>
                </div>";
                }

                $content .= "
                <p style='margin: 12px 0 0 0; color: #666; font-size: 12px; line-height: 1.5;'>
                    <strong>Next Steps:</strong> You may contact our team at <strong>scycloan@gmail.com</strong> to discuss resubmission options or to request clarification on the requirements.
                </p>
            </div>";
            }
        }

        // Add DOCUMENT STATUS UPDATES if present (individual updates)
        if (!empty($updates['documents']) && is_array($updates['documents'])) {
            // Check if this is a batch email with multiple document changes
            $isBatchEmail = isset($updates['is_batch']) && $updates['is_batch'] === true;
            $batchCount = isset($updates['batch_count']) ? (int) $updates['batch_count'] : count($updates['documents']);

            if (!$isBatchEmail || $batchCount === 1) {
                // SINGLE EMAIL: Display individual document update (original behavior)
                $content .= "
        <div style='margin: 20px 0;'>
            <h3 style='color: #1b5e20; border-bottom: 2px solid #2e7d32; padding-bottom: 8px; font-size: 15px;'>Document Status Updates</h3>";

                foreach ($updates['documents'] as $doc) {
                    $docName = htmlspecialchars($doc['name']);
                    $docStatus = $doc['status'];
                    $statusColor = $docStatus === 'Approved' ? '#2e7d32' : ($docStatus === 'Rejected' ? '#d32f2f' : '#fbc02d');
                    $statusLabel = $docStatus;

                    $content .= "
            <div style='margin: 12px 0; padding: 10px; background: #f9f9f9; border-radius: 4px; border-left: 3px solid $statusColor;'>
                <p style='margin: 5px 0;'><strong>$docName</strong></p>
                <p style='margin: 5px 0; color: $statusColor;'>Status: <strong>$statusLabel</strong></p>";

                    if ($docStatus === 'Rejected' && !empty($doc['rejection_notes'])) {
                        $remarkText = htmlspecialchars($doc['rejection_notes']);
                        $content .= "<p style='margin: 5px 0; font-size: 12px; color: #666;'><em>Reason:</em> $remarkText</p>";
                    }

                    // Add rejection reason if present (from rejection template)
                    if ($docStatus === 'Rejected' && !empty($doc['rejection_reason'])) {
                        $rejectionText = htmlspecialchars($doc['rejection_reason']);
                        $content .= "
                <div style='margin: 8px 0; padding: 8px; background: #ffebee; border-left: 3px solid #d32f2f; border-radius: 3px;'>
                    <p style='margin: 0; font-size: 12px; color: #b71c1c;'><strong>📋 Decision Notes:</strong></p>
                    <p style='margin: 5px 0 0 0; font-size: 12px; color: #333; line-height: 1.4;'>$rejectionText</p>
                </div>";
                    }

                    $content .= "</div>";
                }

                $content .= "</div>";
            }
        }

        // Add REMARKS if present (single remark for immediate notice)
        if (!empty($updates['remarks'])) {
            $safeRemarks = htmlspecialchars($updates['remarks']);
            $content .= "
        <div style='margin: 20px 0; padding: 15px; background: #fff3cd; border-left: 4px solid #fbc02d; border-radius: 4px;'>
            <h3 style='margin: 0 0 10px 0; color: #856404; font-size: 15px;'>Review Notes</h3>
            <p style='font-style: italic; margin: 10px 0; color: #333;'>\"$safeRemarks\"</p>
            <p style='font-size: 11px; color: #666; margin: 0;'><em>Added on " . date('F j, Y \a\t g:i A') . "</em></p>
        </div>";
        }

        // Add ALL REMARKS HISTORY if present (when decision is submitted)
        if (!empty($updates['all_remarks']) && is_array($updates['all_remarks']) && count($updates['all_remarks']) > 0) {
            $content .= "
        <div style='margin: 24px 0;'>
            <h3 style='color: #1b5e20; border-bottom: 3px solid #2e7d32; padding-bottom: 10px; font-size: 16px; font-weight: 700;'>📝 Review History</h3>";

            foreach ($updates['all_remarks'] as $remark) {
                $safeRemark = htmlspecialchars($remark['remarks']);
                $adminName = !empty($remark['admin_name']) ? htmlspecialchars($remark['admin_name']) : 'Review Officer';
                $createdAt = !empty($remark['created_at']) ? date('F j, Y \a\t g:i A', strtotime($remark['created_at'])) : 'Unknown Date';

                $content .= "
            <div style='margin: 12px 0; padding: 12px 14px; background: linear-gradient(135deg, #f0f8f5 0%, #f5fcf9 100%); border-radius: 4px; border-left: 4px solid #2d7d32;'>
                <p style='margin: 0 0 4px 0; font-weight: 700; color: #1b5e20; font-size: 13px;'>👤 $adminName</p>
                <p style='margin: 4px 0 8px 0; font-size: 11px; color: #666;'>📅 $createdAt</p>
                <p style='margin: 0; color: #333; line-height: 1.6; font-size: 13px;'>$safeRemark</p>
            </div>";
            }

            $content .= "</div>";
        }

        // Add summary section for batch documents template (like in the screenshot)
        if ($template === 'batch_documents' && !empty($updates['document_changes']) && is_array($updates['document_changes'])) {
            $totalDocs = count($updates['document_changes']);
            $content .= "
        <div style='margin: 24px 0; padding: 16px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #17a2b8;'>
            <p style='margin: 0 0 8px 0; color: #17a2b8; font-size: 14px; font-weight: 600;'>📊 Summary:</p>
            <p style='margin: 0; color: #666; font-size: 14px; line-height: 1.6;'>All document changes have been recorded. Please review carefully and contact support if you have questions.</p>
        </div>";
        }

        // Add summary and action button with professional footer
        $content .= "
        <div style='margin-top: 28px; padding-top: 20px; border-top: 2px solid #e0e0e0;'>
            <div style='text-align: center; margin-bottom: 20px;'>
                <a href='user_dashboard.php' style='display: inline-block; padding: 14px 32px; background: linear-gradient(135deg, #2d7d32 0%, #1b5e20 100%); color: white; text-decoration: none; border-radius: 6px; font-weight: 700; font-size: 14px; box-shadow: 0 4px 12px rgba(45, 125, 50, 0.3);'>Access Your Application</a>
            </div>
            
            <div style='background: #f9f9f9; padding: 16px; border-radius: 6px; font-size: 12px; color: #666; line-height: 1.8;'>
                <p style='margin: 0 0 8px 0;'><strong>📧 Need Help?</strong> Contact our customer service team at <strong style='color: #2d7d32;'>scycloan@gmail.com</strong> or call <strong>0981-303-8698</strong></p>
                <p style='margin: 0;'><strong>ⓘ Important:</strong> This is an automated message from CYCLOAN. Please do not reply to this email.</p>
            </div>
            
            <p style='margin: 16px 0 0 0; font-size: 12px; color: #999; text-align: center;'>
                Sincerely,<br><strong style='color: #1b5e20;'>CLDD Loan Services</strong><br><em>Loan Application Management Team</em>
            </p>
        </div>";

        $content .= "</div>";

        $emailBody = generateEmailTemplate($name, $content);

        // Build subject based on template and what was updated
        $subjectParts = [];

        // PRIMARY SUBJECT BASED ON TEMPLATE
        if ($template === 'batch_documents') {
            $docCount = isset($updates['total_changes']) ? $updates['total_changes'] : count($updates['document_changes'] ?? []);
            $subject = "$docCount Document(s) Reviewed - Application #$applicationId";
        } elseif ($template === 'approved') {
            $subject = "✅ Your Loan Application Has Been Pre-Approved - Application #$applicationId";
        } elseif ($template === 'rejected') {
            $subject = "📋 Loan Application Review Decision - Application #$applicationId";
        } elseif ($template === 'pending') {
            $subject = "⏳ Your Loan Application is Under Review - Application #$applicationId";
        } else {
            // Fallback for non-pre-approval updates
            $subjectParts = [];
            if (!empty($updates['pre_approval_status']))
                $subjectParts[] = "Pre-Approval: " . $updates['pre_approval_status'];
            if (!empty($updates['documents']))
                $subjectParts[] = count($updates['documents']) . " Document(s) Reviewed";
            if (!empty($updates['remarks']) || !empty($updates['all_remarks']))
                $subjectParts[] = "Decision Notes";

            $subject = !empty($subjectParts) ? implode(" | ", $subjectParts) . " - Application #$applicationId" : "Application Update - Application #$applicationId";
        }

        $logMessage = "Email Template: $template | ";
        if (!empty($updates['pre_approval_status']))
            $logMessage .= "PreApproval=" . $updates['pre_approval_status'] . " | ";
        if (!empty($updates['documents']))
            $logMessage .= "Docs=" . count($updates['documents']) . " | ";
        if (!empty($updates['admin_name']))
            $logMessage .= "Admin=" . $updates['admin_name'] . " | ";
        $logMessage .= "App=$applicationId";

        error_log("CONSOLIDATED_EMAIL_SENDING: About to send email - Recipient: $to, Subject: $subject", E_USER_NOTICE);
        error_log("CONSOLIDATED_EMAIL_LOG_MESSAGE: $logMessage", E_USER_NOTICE);

        error_log("BEFORE_SEND_EMAIL: Calling sendEmail with TO: $to | NAME: $name | SUBJECT: $subject | BODY_LENGTH: " . strlen($emailBody), E_USER_NOTICE);
        $sendResult = sendEmail($to, $name, $subject, $emailBody, $logMessage);
        error_log("AFTER_SEND_EMAIL: sendEmail returned: " . ($sendResult ? 'TRUE' : 'FALSE') . " for App: $applicationId", E_USER_WARNING);

        if ($sendResult) {
            error_log("CONSOLIDATED_EMAIL_COMPLETE: Email successfully sent to $to for App ID: $applicationId", E_USER_NOTICE);
        } else {
            error_log("CONSOLIDATED_EMAIL_FAILED: Email send returned false for App ID: $applicationId, Recipient: $to", E_USER_WARNING);
        }

        return $sendResult;
    } catch (Exception $e) {
        error_log("Email Send Exception for App ID: $applicationId - " . $e->getMessage(), E_USER_WARNING);
        return false;
    }
}

/**
 * LEGACY FUNCTIONS - Deprecated but kept for backward compatibility
 * New code should use sendConsolidatedUpdateEmail() instead
 */
function sendPreApprovalStatusEmail($conn, $applicationId, $newStatus)
{
    return sendConsolidatedUpdateEmail($conn, $applicationId, ['pre_approval_status' => $newStatus]);
}

function sendDocumentStatusEmail($conn, $applicationId, $documentId, $documentName, $newStatus)
{
    return sendConsolidatedUpdateEmail($conn, $applicationId, [
        'documents' => [['name' => $documentName, 'status' => $newStatus]]
    ]);
}

function sendRemarkEmail($conn, $applicationId, $remarks)
{
    return sendConsolidatedUpdateEmail($conn, $applicationId, ['remarks' => $remarks]);
}

/**
 * Send consolidated email with all queued document changes from current session
 * Accumulates all document status changes and sends ONE email containing all changes
 * Clears the queue after sending
 * 
 * @param mysqli $conn Database connection
 * @param string $applicationId Application ID to send email for
 * @return bool True if email sent successfully, false otherwise
 */
function sendQueuedDocumentChangesEmail($conn, $applicationId)
{
    // Check if there are queued changes for this application
    if (empty($_SESSION['document_changes_queue'][$applicationId])) {
        error_log("QUEUED_EMAIL: No queued changes for App ID: $applicationId", E_USER_NOTICE);
        return false;
    }

    $queueData = $_SESSION['document_changes_queue'][$applicationId];
    $changes = $queueData['changes'];
    $adminName = $queueData['admin_name'];
    $userId = $queueData['user_id'];
    $allDocuments = $queueData['all_documents'];

    error_log("QUEUED_EMAIL_SEND: Sending consolidated email for App ID: $applicationId with " . count($changes) . " document change(s)", E_USER_NOTICE);

    // Build document changes array for the email template
    $documentChanges = [];
    $hasRejections = false;
    $hasApprovals = false;

    foreach ($changes as $change) {
        $docStatus = $change['new_status'];
        if ($docStatus === 'Rejected')
            $hasRejections = true;
        if ($docStatus === 'Approved')
            $hasApprovals = true;

        $documentChanges[] = [
            'document_name' => $change['document_name'],
            'new_status' => $docStatus,
            'previous_status' => $change['previous_status'] ?? 'Pending',
            'rejection_reason' => $change['rejection_reason'] ?? '',
            'rejection_notes' => $change['rejection_notes'] ?? '',
            'status_updated_at' => $change['updated_at'],
            'formatted_time' => date('F j, Y \a\t g:i A', strtotime($change['updated_at'])),
            'description' => $change['description'] ?? ''
        ];

        // Enhanced debugging to track what document names are being processed
        error_log("QUEUED_EMAIL_DEBUG: Processing document - Name: '" . ($change['document_name'] ?? 'NOT_SET') . "', Status: '$docStatus', Description: '" . ($change['description'] ?? 'NOT_SET') . "'", E_USER_NOTICE);
    }

    // Build consolidated updates for email template matching the screenshot format
    $consolidatedUpdates = [
        'admin_name' => $adminName,
        'admin_updated_at' => $queueData['first_change_time'],
        'document_changes' => $documentChanges,  // Key change: use document_changes instead of documents
        'batch_count' => count($changes),
        'has_rejections' => $hasRejections,
        'has_approvals' => $hasApprovals,
        'total_changes' => count($changes)
    ];

    // Debug the final consolidated updates structure
    error_log("QUEUED_EMAIL_DEBUG: Final consolidatedUpdates - document_changes count: " . count($documentChanges) . ", first doc name: '" . ($documentChanges[0]['document_name'] ?? 'NONE') . "'", E_USER_NOTICE);

    // Send the consolidated email using the batch_documents template
    $emailSent = sendConsolidatedUpdateEmail($conn, $applicationId, $consolidatedUpdates);

    if ($emailSent) {
        // Mark queue as sent and update metadata before clearing
        $_SESSION['document_changes_queue'][$applicationId]['queue_status'] = 'sent';
        $_SESSION['document_changes_queue'][$applicationId]['email_sent'] = true;
        $_SESSION['document_changes_queue'][$applicationId]['last_email_sent'] = date('Y-m-d H:i:s');
        $_SESSION['document_changes_queue'][$applicationId]['email_send_count'] = ($_SESSION['document_changes_queue'][$applicationId]['email_send_count'] ?? 0) + 1;

        // Mark all changes as processed
        foreach ($_SESSION['document_changes_queue'][$applicationId]['changes'] as &$change) {
            $change['processed'] = true;
            $change['email_sent'] = true;
            $change['email_sent_at'] = date('Y-m-d H:i:s');
        }

        error_log("PERSISTENT_QUEUE_SUCCESS: Email sent successfully for App ID: $applicationId with " . count($changes) . " changes", E_USER_NOTICE);

        // Clear the queue after successful send (this prevents accumulation)
        unset($_SESSION['document_changes_queue'][$applicationId]);
        error_log("QUEUE_CLEARED: Removed processed queue for App ID: $applicationId", E_USER_NOTICE);
        return true;
    } else {
        error_log("PERSISTENT_QUEUE_FAILED: Failed to send email for App ID: $applicationId", E_USER_WARNING);
        return false;
    }
}

// Fetch loan applications
$loanApplicants = executeQuery($conn, "
    SELECT la.application_id, u.first_name, u.last_name, lt.type_name, la.amount_applied, la.status, 
           la.pre_approval_status, la.credit_investigation_status, la.created_at, la.final_loan_amount
    FROM loan_applications la
    JOIN users1 u ON la.user_id = u.id
    JOIN loan_types lt ON la.loan_type_id = lt.loan_type_id
    ORDER BY la.created_at DESC
");

// Fetch status counts
$statusCounts = executeQuery($conn, "
    SELECT 
        SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) AS active_count,
        SUM(CASE WHEN status IN ('Pending', 'New') THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN status = 'Closed' THEN 1 ELSE 0 END) AS closed_count
    FROM loan_applications
");
$activeCount = $statusCounts[0]['active_count'] ?? 0;
$pendingCount = $statusCounts[0]['pending_count'] ?? 0;
$closedCount = $statusCounts[0]['closed_count'] ?? 0;

// Fetch data for Loan Type Graph
$loanTypeData = executeQuery($conn, "
    SELECT lt.type_name, COALESCE(COUNT(la.application_id), 0) AS count
    FROM (
        SELECT 'Individual' AS type_name, loan_type_id FROM loan_types WHERE type_name = 'Individual'
        UNION
        SELECT 'Cooperative' AS type_name, loan_type_id FROM loan_types WHERE type_name = 'Cooperative'
    ) lt
    LEFT JOIN loan_applications la ON lt.loan_type_id = la.loan_type_id
    GROUP BY lt.type_name
");
if (empty($loanTypeData)) {
    error_log('Loan Type Graph data fetch failed', 3, 'errors.log');
    $loanTypeData = [
        ['type_name' => 'Individual', 'count' => 0],
        ['type_name' => 'Cooperative', 'count' => 0]
    ];
}

// Fetch data for Due Accounts List
try {
    $dueAccountsData = executeQuery($conn, "
        SELECT ps.due_date, u.first_name, u.last_name, ps.amount, ps.payment_id, la.loan_id, la.application_id
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
    if (empty($dueAccountsData)) {
        error_log('Due Accounts List data fetch: No records found', 3, 'errors.log');
    }
} catch (Exception $e) {
    error_log('Data fetch operation failed', E_USER_WARNING);
    $dueAccountsData = [];
}

// Fetch activity logs for display
$activityLogs = executeQuery($conn, "
    SELECT al.log_id, al.user_id, al.user_role, al.action_type, al.module, al.description, 
           al.created_at,
           COALESCE(u.first_name, a.first_name, a1.first_name, 'System') AS first_name,
           COALESCE(u.last_name, a.last_name, a1.last_name, '') AS last_name,
           la.loan_id
    FROM activity_logs al
    LEFT JOIN users1 u ON al.user_id = u.id AND al.user_role = 'User'
    LEFT JOIN admin2 a ON al.user_id = a.id AND al.user_role = 'Admin2'
    LEFT JOIN admin1 a1 ON al.user_id = a1.id AND al.user_role = 'Admin1'
    LEFT JOIN loan_applications la ON al.user_id = la.user_id
    ORDER BY al.created_at DESC
    LIMIT 50
");
if (empty($activityLogs)) {
    error_log('Activity Logs data fetch failed', 3, 'errors.log');
} else {
    // Apply module mapping to initial logs
    foreach ($activityLogs as &$log) {
        $module = strtolower($log['module']);
        $log['module_display'] = isset($moduleMap[$module]) ? $moduleMap[$module] : ucfirst($log['module']);
    }
    // Log cases where user name is not found
    foreach ($activityLogs as $log) {
        if ($log['first_name'] === 'System' && $log['user_role'] !== 'System') {
            error_log("Activity log data processing skipped", E_USER_NOTICE);
        }
    }
}

// Handle AJAX request for interest rates data
if (isset($_GET['action']) && $_GET['action'] === 'get_interest_rates') {
    try {
        ob_clean();
        header('Content-Type: application/json');

        // Fetch current interest rates
        $query = "
            SELECT id, term_length, interest_rate as rate, updated_at, updated_by 
            FROM interest_rates 
            ORDER BY 
                CASE 
                    WHEN term_length = '6' THEN 1
                    WHEN term_length = '12' THEN 2
                    WHEN term_length = '18' THEN 3
                    WHEN term_length = '24' THEN 4
                    WHEN term_length = '36' THEN 5
                    ELSE 6
                END ASC
        ";

        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $result = $stmt->get_result();
        $rates = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Calculate statistics
        $termCount = count($rates);
        $averageRate = 0;
        $lastUpdated = 'N/A';

        if ($termCount > 0) {
            $sum = 0;
            foreach ($rates as $rate) {
                $sum += floatval($rate['rate']);
            }
            $averageRate = round($sum / $termCount, 2);
        }

        if (!empty($rates)) {
            $lastUpdated = $rates[0]['updated_at'];
        }

        echo json_encode([
            'success' => true,
            'rates' => $rates,
            'stats' => [
                'term_count' => $termCount,
                'average_rate' => $averageRate,
                'last_updated' => $lastUpdated
            ],
            'history' => $rates
        ]);
        exit;
    } catch (Exception $e) {
        error_log("Interest rates fetch failed: " . $e->getMessage(), E_USER_WARNING);
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        http_response_code(500);
        exit;
    }
}

// Handle AJAX request for payment details
if (isset($_GET['action']) && $_GET['action'] === 'get_payment_details' && isset($_GET['payment_id'])) {
    try {
        $paymentId = sanitizeInt($_GET['payment_id']);

        if (empty($paymentId)) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid payment ID']);
            exit;
        }

        // Fetch payment details
        $payment = executeQuery($conn, "
            SELECT ps.payment_id, ps.due_date, ps.amount, ps.status, ps.loan_id, 
                   la.loan_id as full_loan_id, la.application_id, u.first_name, u.last_name
            FROM payment_schedules ps
            JOIN loans l ON ps.loan_id = l.loan_id
            JOIN loan_applications la ON l.application_id = la.application_id
            JOIN users1 u ON la.user_id = u.id
            WHERE ps.payment_id = ?
        ", "i", [$paymentId]);

        if (empty($payment)) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Payment not found']);
            exit;
        }

        $payment = $payment[0];

        ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'payment_id' => $payment['payment_id'],
            'loan_id' => $payment['full_loan_id'],
            'application_id' => $payment['application_id'],
            'amount' => $payment['amount'],
            'due_date' => $payment['due_date'],
            'status' => $payment['status'],
            'account_holder' => trim($payment['first_name'] . ' ' . $payment['last_name'])
        ]);
        exit;
    } catch (Exception $e) {
        ob_clean();
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error fetching payment details']);
        exit;
    }
}

// Handle AJAX request for loan details
if (isset($_GET['action']) && $_GET['action'] === 'get_loan_details' && isset($_GET['application_id'])) {
    $applicationId = trim($_GET['application_id']); // Keep as string since application_id is VARCHAR
    $startTime = microtime(true);

    try {
        // OPTIMIZATION: Fetch loan details with financial information
        // CRITICAL: Only fetch columns we actually need to reduce data transfer
        $queryStart = microtime(true);
        $loan = executeQuery($conn, "
            SELECT la.application_id, la.user_id, la.loan_type_id, la.status, la.pre_approval_status, 
                   la.credit_investigation_status, la.amount_applied, la.final_loan_amount, 
                   la.created_at, la.updated_at, la.is_archived, la.approval_reason,
                   u.first_name, u.last_name, u.email, u.birthday, u.contact, 
                   COALESCE(lt.type_name, 'Unknown') AS type_name,
                   fi.business_income, fi.salary_income, fi.remittance_income, fi.other_income,
                   fi.business2_income, fi.salary2_income, fi.net_income,
                   fi.food_allowance, fi.electricity_bill, fi.water_bill, fi.internet_bill,
                   fi.gas_bill, fi.educational_allowance, fi.car_amortization, fi.insurance,
                   fi.other_expense, fi.total_expenditures, fi.expected_monthly_amortization,
                   fi.remaining_income
            FROM loan_applications la
            JOIN users1 u ON la.user_id = u.id
            LEFT JOIN loan_types lt ON la.loan_type_id = lt.loan_type_id
            LEFT JOIN financial_info fi ON la.user_id = fi.user_id
            WHERE la.application_id = ?
        ", "s", [$applicationId]);
        $loanQueryTime = microtime(true) - $queryStart;

        if (empty($loan)) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => "Loan application not found for ID: $applicationId"]);
            exit;
        }
        $loan = $loan[0];

        // OPTIMIZATION: Fetch documents
        $queryStart = microtime(true);
        $documents = executeQuery($conn, "
            SELECT d.document_id, dt.document_name, d.file_path, d.status
            FROM documents d
            JOIN document_types dt ON d.document_type_id = dt.document_type_id
            WHERE d.application_id = ?
            ORDER BY d.updated_at DESC
        ", "s", [$applicationId]);
        $docsQueryTime = microtime(true) - $queryStart;

        // OPTIMIZATION: Fetch remarks
        $queryStart = microtime(true);
        $remarks = executeQuery($conn, "
            SELECT remarks, created_at, admin_name
            FROM remarks
            WHERE application_id = ?
            ORDER BY created_at DESC
        ", "s", [$applicationId]);
        $remarksQueryTime = microtime(true) - $queryStart;

        // OPTIMIZATION: Ultra-fast activity logs query
        // Gets the 50 most recent activities for THIS APPLICATION ONLY
        // Filter by application_id in description to get relevant logs
        // With proper indexes, this should take <100ms
        $queryStart = microtime(true);
        $logs = executeQuery($conn, "
            SELECT al.log_id, al.user_id, al.user_role, al.action_type, al.module, al.description, 
                   al.created_at,
                   COALESCE(al.admin_name, 'System') AS first_name,
                   '' AS last_name
            FROM activity_logs al
            WHERE al.description LIKE ? OR al.module IN ('loan_applications', 'documents', 'remarks')
            ORDER BY al.created_at DESC
            LIMIT 50
        ", "s", ["%$applicationId%"]);
        $logsQueryTime = microtime(true) - $queryStart;

        $totalTime = microtime(true) - $startTime;

        // Debug logging removed for security

        ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'loan' => $loan,
            'documents' => $documents,
            'remarks' => $remarks,
            'logs' => $logs,
            '_debug' => [
                'loan_query_ms' => round($loanQueryTime * 1000, 2),
                'documents_query_ms' => round($docsQueryTime * 1000, 2),
                'remarks_query_ms' => round($remarksQueryTime * 1000, 2),
                'logs_query_ms' => round($logsQueryTime * 1000, 2),
                'total_ms' => round($totalTime * 1000, 2)
            ]
        ]);
        exit;
    } catch (Exception $e) {
        error_log("get_loan_details error: " . $e->getMessage(), E_USER_WARNING);
        ob_clean();
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to fetch loan details']);
        exit;
    }
}

// Log all POST requests before checking specific handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    error_log("Admin2 Dashboard: Before handler check - Action: " . $_POST['action'] . ", Has app_id: " . (isset($_POST['application_id']) ? 'YES' : 'NO'), E_USER_NOTICE);
}

// Handle AJAX request for updating statuses, final loan amount, and remarks (only if not already handled)
if (!$ACTION_HANDLED && isset($_POST['action']) && $_POST['action'] === 'update_status' && isset($_POST['application_id'])) {
    error_log("Admin2: update_status handler (SECONDARY) reached! Checking CSRF token...", E_USER_NOTICE);

    // Security: CSRF Token Validation
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        error_log("Admin2: CSRF validation FAILED. Token in POST: " . ($_POST['csrf_token'] ?? 'NONE') . ", Session token exists: " . (isset($_SESSION['csrf_token']) ? 'YES' : 'NO'), E_USER_WARNING);
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Security validation failed. Please refresh and try again.']);
        http_response_code(403);
        exit;
    }

    error_log("Admin2: CSRF validation PASSED", E_USER_NOTICE);

    // Mark as handled to prevent fallback
    $ACTION_HANDLED = true;

    // Security: Input Sanitization
    $applicationId = sanitizeString($_POST['application_id'], 50);
    $preApprovalStatus = isset($_POST['pre_approval_status']) ? sanitizeString($_POST['pre_approval_status'], 20) : null;
    $loanStatus = isset($_POST['status']) ? sanitizeString($_POST['status'], 20) : null;
    $approvalReason = isset($_POST['approval_reason']) ? sanitizeString($_POST['approval_reason'], 1000) : null;

    // DEBUG: Log incoming data
    error_log("PRE-APPROVAL HANDLER: Received data - Status: $preApprovalStatus, Reason: $approvalReason", E_USER_NOTICE);

    // Security: Validate enum values
    $validPreApprovalStatuses = ['Pending', 'Approved', 'Rejected'];
    $validLoanStatuses = ['Pending', 'Active', 'Completed', 'New', 'Renewal'];

    if ($preApprovalStatus && !validateEnum($preApprovalStatus, $validPreApprovalStatuses)) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid pre-approval status.']);
        http_response_code(400);
        exit;
    }

    if ($loanStatus && !validateEnum($loanStatus, $validLoanStatuses)) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid loan status.']);
        http_response_code(400);
        exit;
    }

    // Security: Validate approval reason is required for final decisions (Approved/Rejected)
    if ($preApprovalStatus && ($preApprovalStatus === 'Approved' || $preApprovalStatus === 'Rejected')) {
        if (empty($approvalReason)) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Approval reason is required for final decisions (Approved/Rejected)',
                'field' => 'approval_reason',
                'required_for_status' => $preApprovalStatus
            ]);
            http_response_code(400);
            exit;
        }
        // Ensure approval reason is not just whitespace
        if (trim($approvalReason) === '') {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Approval reason cannot be empty or whitespace',
                'field' => 'approval_reason'
            ]);
            http_response_code(400);
            exit;
        }
    }

    // Fetch current data for comparison including user_id and loan_id
    $currentData = executeQuery($conn, "
        SELECT pre_approval_status, credit_investigation_status, status, user_id, loan_id
        FROM loan_applications
        WHERE application_id = ?
    ", "s", [$applicationId]);

    if (empty($currentData)) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => "Loan application not found for application_id: $applicationId"]);
        exit;
    }
    $currentData = $currentData[0];
    $userId = $currentData['user_id'];
    $loanId = $currentData['loan_id'];

    // Validate Admin 2 permissions
    if ($adminRole === 'Admin2') {
        if ($preApprovalStatus && !in_array($preApprovalStatus, ['Pending', 'Approved', 'Rejected'])) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => "Invalid pre-approval status: $preApprovalStatus"]);
            exit;
        }
        if ($loanStatus && !in_array($loanStatus, ['Pending', 'Active', 'Completed', 'New', 'Renewal'])) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => "Invalid loan status: $loanStatus"]);
            exit;
        }
        if ($loanStatus && ($currentData['pre_approval_status'] !== 'Approved' || $currentData['credit_investigation_status'] !== 'Completed')) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Loan Status can only be updated when Pre-Approval is Approved and Credit Investigation is Completed.']);
            exit;
        }
    }

    // Prepare update query
    $updateFields = [];
    $paramTypes = "";
    $paramValues = [];

    if ($preApprovalStatus && $preApprovalStatus !== $currentData['pre_approval_status']) {
        $updateFields[] = "pre_approval_status = ?";
        $paramTypes .= "s";
        $paramValues[] = $preApprovalStatus;
    }
    if ($loanStatus && $loanStatus !== $currentData['status']) {
        $updateFields[] = "status = ?";
        $paramTypes .= "s";
        $paramValues[] = $loanStatus;
    }
    // Add approval_reason if provided with pre-approval status change (Approved or Rejected)
    if ($preApprovalStatus && ($preApprovalStatus === 'Approved' || $preApprovalStatus === 'Rejected') && !empty($approvalReason)) {
        $updateFields[] = "approval_reason = ?";
        $paramTypes .= "s";
        $paramValues[] = $approvalReason;
    }

    // Update loan_applications if there are changes
    if (!empty($updateFields)) {
        $query = "UPDATE loan_applications SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE application_id = ?";
        $paramTypes .= "s"; // application_id is VARCHAR
        $paramValues[] = $applicationId;

        $affectedRows = executeUpdate($conn, $query, $paramTypes, $paramValues);
        error_log("Status update operation completed", E_USER_NOTICE);
        if ($affectedRows === 0) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => "No rows updated. Verify application_id exists: $applicationId"]);
            exit;
        }

        // Collect all updates for consolidated email
        $consolidatedUpdates = [];
        $emailShouldBeSent = false;

        // Add admin information to consolidated updates
        $consolidatedUpdates['admin_name'] = $adminName;
        $consolidatedUpdates['admin_updated_at'] = date('F j, Y \a\t g:i A');

        // Fetch ALL documents for this application to show in email summary
        $allDocuments = executeQuery($conn, "
            SELECT dt.document_name as name, d.status, d.document_id
            FROM documents d
            JOIN document_types dt ON d.document_type_id = dt.document_type_id
            WHERE d.application_id = ?
            ORDER BY dt.document_name
        ", "s", [$applicationId]);

        if (!empty($allDocuments)) {
            $consolidatedUpdates['all_documents'] = $allDocuments;
        }

        // Log pre-approval status update
        if ($preApprovalStatus && $preApprovalStatus !== $currentData['pre_approval_status']) {
            // Determine reference based on current loan status
            $referenceId = ($currentData['status'] === 'Active') ? "loan $loanId" : "application $applicationId";
            $reasonText = !empty($approvalReason) ? " | Reason: $approvalReason" : "";
            $description = "Pre-Approval Status Updated | From: {$currentData['pre_approval_status']} → To: $preApprovalStatus | Reference: $referenceId | Updated by: $adminName$reasonText";
            logActivity($conn, $adminId, $adminRole, 'update', 'loan_application', $description, $userId, 'pre-approval');

            $consolidatedUpdates['pre_approval_status'] = $preApprovalStatus;
            if (!empty($approvalReason)) {
                $consolidatedUpdates['approval_reason'] = $approvalReason;
            }
            $emailShouldBeSent = true;

            // Create notification for status update
            try {
                $statusMessage = $preApprovalStatus === 'Approved' ? 'Your application has been approved for the next stage.' :
                    ($preApprovalStatus === 'Rejected' ? 'Please contact support for more information.' :
                        'Your application is currently under review.');

                createStatusNotification($conn, $userId, $statusMessage);

                error_log("Notification operation completed", E_USER_NOTICE);
            } catch (Exception $e) {
                error_log("Notification creation failed", E_USER_WARNING);
            }
        }

        // Log loan status update
        if ($loanStatus && $loanStatus !== $currentData['status']) {
            // If loan is becoming Active, reference the loan_id; otherwise application_id
            $referenceId = ($loanStatus === 'Active') ? "loan $loanId" : "application $applicationId";
            $description = "Loan Status Updated | From: {$currentData['status']} → To: $loanStatus | Reference: $referenceId | Updated by: $adminName";
            logActivity($conn, $adminId, $adminRole, 'update', 'loan_application', $description, $userId);

            // Create notification for loan status update
            try {
                $statusMessage = $loanStatus === 'Active' ? 'Your loan is now active and ready for disbursement.' :
                    ($loanStatus === 'Completed' ? 'Congratulations! Your loan has been completed.' :
                        'Your loan status has been updated.');

                createStatusNotification($conn, $userId, $statusMessage);
            } catch (Exception $e) {
                // Security: Log error without exposing details
                error_log("Notification creation failed", E_USER_WARNING);
            }
        }
    }

    // Handle remarks - store but do NOT send email yet (only send when decision is submitted)
    if (!empty($approvalReason)) {
        $query = "INSERT INTO remarks (application_id, remarks, created_at, admin_name) VALUES (?, ?, NOW(), ?)";
        $affectedRows = executeUpdate($conn, $query, "sss", [$applicationId, $approvalReason, $adminName]);

        // Determine reference based on current loan status
        $referenceId = ($currentData['status'] === 'Active') ? "loan $loanId" : "application $applicationId";
        $description = "Decision Note Added | Remark: \"$approvalReason\" | Reference: $referenceId | Added by: $adminName";
        logActivity($conn, $adminId, $adminRole, 'create', 'remarks', $description, $userId);

        $consolidatedUpdates['remarks'] = $approvalReason;
        // DO NOT set emailShouldBeSent = true here - email only sent when pre-approval decision is submitted

        // Create notification for new remark
        try {
            $notificationMessage = "A new remark has been added: '$approvalReason'";
            createRemarkNotification($conn, $userId, $notificationMessage);
        } catch (Exception $e) {
            // Security: Log error without exposing details
            error_log("Notification creation failed", E_USER_WARNING);
        }
    }

    // Send consolidated email ONLY when pre-approval status changes to Approved or Rejected
    if (($preApprovalStatus === 'Approved' || $preApprovalStatus === 'Rejected') && $preApprovalStatus !== $currentData['pre_approval_status']) {
        // Fetch all remarks for this application to include in email
        $allRemarks = executeQuery($conn, "SELECT remarks, created_at, admin_name FROM remarks WHERE application_id = ? ORDER BY created_at DESC", "s", [$applicationId]);
        if (!empty($allRemarks)) {
            $consolidatedUpdates['all_remarks'] = $allRemarks;
        }

        // IMPORTANT: When rejecting during pre-approval, mark all documents as rejected and store the rejection reason
        if ($preApprovalStatus === 'Rejected' && !empty($approvalReason)) {
            error_log("PREAPPROVAL_REJECT_START: Rejecting application $applicationId with reason: $approvalReason", E_USER_NOTICE);

            // Fetch all documents for this application
            $allDocsToReject = executeQuery($conn, "
                SELECT d.document_id, d.document_type_id, d.status, dt.document_name
                FROM documents d
                JOIN document_types dt ON d.document_type_id = dt.document_type_id
                WHERE d.application_id = ? AND d.status != 'Rejected'
            ", "s", [$applicationId]);

            error_log("PREAPPROVAL_DOCS_FOUND: Found " . count($allDocsToReject) . " documents to reject", E_USER_NOTICE);

            // Reject all documents and create audit trails
            if (!empty($allDocsToReject)) {
                foreach ($allDocsToReject as $docToReject) {
                    error_log("PREAPPROVAL_REJECTING_DOC: Document {$docToReject['document_name']} (ID: {$docToReject['document_id']})", E_USER_NOTICE);

                    // CRITICAL DEBUG: Log the exact rejection reason before UPDATE
                    error_log("DEBUG_REJECTION_REASON: Value='$approvalReason' | Length=" . strlen($approvalReason) . " | Type=" . gettype($approvalReason), E_USER_NOTICE);

                    // Update document status and store rejection notes
                    $updateResult = executeUpdate($conn, "
                        UPDATE documents 
                        SET status = 'Rejected', status_updated_at = NOW(), rejection_notes = ? 
                        WHERE document_id = ?
                    ", "si", [$approvalReason, $docToReject['document_id']]);

                    error_log("PREAPPROVAL_UPDATE_DOC: Update result for doc {$docToReject['document_id']}: $updateResult rows affected", E_USER_NOTICE);

                    // CRITICAL: Verify what was actually saved immediately after UPDATE
                    $verify = executeQuery($conn, "SELECT rejection_notes FROM documents WHERE document_id = ?", "i", [$docToReject['document_id']]);
                    if (!empty($verify)) {
                        $saved = $verify[0]['rejection_notes'];
                        error_log("VERIFY_SAVED: Doc {$docToReject['document_id']} rejection_notes='$saved' (Type: " . gettype($saved) . ")", E_USER_NOTICE);
                    }

                    // Create audit trail for this document rejection
                    $auditQuery = "
                        INSERT INTO document_audit 
                        (document_id, application_id, document_type_id, old_status, new_status, admin_id, admin_name, admin_role, notes) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ";
                    $auditResult = executeUpdate($conn, $auditQuery, "isississs", [
                        $docToReject['document_id'],
                        $applicationId,
                        $docToReject['document_type_id'],
                        $docToReject['status'],
                        'Rejected',
                        $adminId,
                        $adminName,
                        $adminRole,
                        $approvalReason
                    ]);

                    error_log("PREAPPROVAL_AUDIT_CREATED: Audit entry created, result: $auditResult", E_USER_NOTICE);
                }

                error_log("PREAPPROVAL_REJECT_COMPLETE: All documents rejected successfully", E_USER_NOTICE);
            } else {
                error_log("PREAPPROVAL_NO_DOCS: No documents found to reject", E_USER_WARNING);
            }

            // Fetch updated documents with rejection notes for email
            $consolidatedUpdates['all_documents'] = executeQuery($conn, "
                SELECT d.document_id, dt.document_name as name, d.status, d.rejection_notes
                FROM documents d
                JOIN document_types dt ON d.document_type_id = dt.document_type_id
                WHERE d.application_id = ?
                ORDER BY dt.document_name
            ", "s", [$applicationId]);
        }

        // Queue email instead of sending immediately - events will be consolidated and sent in batches
        $eventData = [
            'status' => $preApprovalStatus,
            'reason' => !empty($approvalReason) ? $approvalReason : '',
            'remarks' => !empty($consolidatedUpdates['remarks']) ? $consolidatedUpdates['remarks'] : '',
            'all_remarks' => !empty($consolidatedUpdates['all_remarks']) ? $consolidatedUpdates['all_remarks'] : [],
            'documents' => !empty($consolidatedUpdates['documents']) ? $consolidatedUpdates['documents'] : []
        ];

        error_log("PREAPPROVAL_EMAIL_TRIGGER: About to call sendConsolidatedUpdateEmail for App: $applicationId with status: $preApprovalStatus", E_USER_NOTICE);
        $emailSendResult = sendConsolidatedUpdateEmail($conn, $applicationId, $consolidatedUpdates);
        error_log("PREAPPROVAL_EMAIL_RESULT: sendConsolidatedUpdateEmail returned: " . ($emailSendResult ? 'TRUE (SUCCESS)' : 'FALSE (FAILED)'), E_USER_WARNING);
        if (!$emailSendResult) {
            error_log("PREAPPROVAL_EMAIL_FAILED: Email notification operation failed for App: $applicationId", E_USER_WARNING);
        } else {
            error_log("PREAPPROVAL_EMAIL_SUCCESS: Email notification operation succeeded for App: $applicationId", E_USER_NOTICE);
        }
    }

    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Status and/or remarks updated successfully.']);
    exit;
}

// Handle AJAX request for sending queued document changes email
if (!$ACTION_HANDLED && isset($_POST['action']) && $_POST['action'] === 'send_queued_document_email' && isset($_POST['application_id'])) {

    $ACTION_HANDLED = true;
    // CSRF Token Validation
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Security validation failed']);
        http_response_code(403);
        exit;
    }

    // Input Sanitization (SECURITY: Prevent XSS and injection attacks)
    $applicationId = sanitizeString($_POST['application_id'], 50); // Keep as string since application_id is VARCHAR
    $documentId = sanitizeInt($_POST['document_id']);
    $newStatus = sanitizeString($_POST['status'], 20);
    $rejectionReason = isset($_POST['rejection_reason']) ? sanitizeString($_POST['rejection_reason'], 1000) : null;

    // CRITICAL DEBUG: Log all received data
    error_log("🔍 AUDIT_DEBUG: POST data received - application_id='" . $_POST['application_id'] . "', document_id='" . $_POST['document_id'] . "', status='" . $_POST['status'] . "'", E_USER_NOTICE);
    error_log("🔍 AUDIT_DEBUG: After sanitization - applicationId='$applicationId', documentId='$documentId', newStatus='$newStatus'", E_USER_NOTICE);

    // CRITICAL DEBUG: Log the rejection reason immediately upon receipt
    error_log("BACKEND_DEBUG: Received rejection_reason = '" . (isset($_POST['rejection_reason']) ? $_POST['rejection_reason'] : 'NOT SET') . "'", E_USER_NOTICE);
    error_log("BACKEND_DEBUG: After sanitization, \$rejectionReason = '" . $rejectionReason . "' | Empty? = " . (empty($rejectionReason) ? 'YES' : 'NO'), E_USER_NOTICE);

    // Validate sanitized inputs are not empty
    if (empty($applicationId) || empty($documentId) || empty($newStatus)) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid input parameters']);
        http_response_code(400);
        exit;
    }

    if ($adminRole !== 'Admin2') {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Admin 2 role required']);
        http_response_code(403);
        exit;
    }

    // Enum Validation (SECURITY: Strict whitelist validation for status)
    $validStatuses = ['Pending', 'Approved', 'Rejected'];
    if (!validateEnum($newStatus, $validStatuses)) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid document status']);
        http_response_code(400);
        exit;
    }

    // NEW: Validate rejection reason is required when rejecting
    if ($newStatus === 'Rejected' && empty($rejectionReason)) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Rejection reason is required when rejecting a document',
            'field' => 'rejection_reason'
        ]);
        http_response_code(400);
        exit;
    }

    // Fetch current status and document details and get the user_id from the application
    $document = executeQuery($conn, "
        SELECT d.document_id, d.document_type_id, dt.document_name, d.status, la.user_id, la.status as loan_status, la.loan_id
        FROM documents d
        JOIN document_types dt ON d.document_type_id = dt.document_type_id
        JOIN loan_applications la ON d.application_id = la.application_id
        WHERE d.document_id = ? AND d.application_id = ?
    ", "is", [$documentId, $applicationId]);

    if (empty($document)) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Document not found.']);
        exit;
    }
    $document = $document[0];
    $userId = $document['user_id'];
    $loanStatus = $document['loan_status'];
    $loanId = $document['loan_id'];

    if ($newStatus === $document['status']) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'No change in document status.']);
        exit;
    }

    // Update document status and store rejection notes if rejecting
    if ($newStatus === 'Rejected' && !empty($rejectionReason)) {
        error_log("BACKEND_DEBUG: UPDATE with rejection_reason - executing UPDATE with rejectionReason = '$rejectionReason'", E_USER_NOTICE);
        error_log("BACKEND_DEBUG: Query params - status='$newStatus', rejection_notes='$rejectionReason', doc_id=$documentId, app_id='$applicationId'", E_USER_NOTICE);

        $affectedRows = executeUpdate($conn, "
            UPDATE documents SET status = ?, status_updated_at = NOW(), rejection_notes = ? WHERE document_id = ? AND application_id = ?
        ", "ssis", [$newStatus, $rejectionReason, $documentId, $applicationId]);
        error_log("BACKEND_DEBUG: UPDATE completed. Rows affected: $affectedRows", E_USER_NOTICE);

        // VERIFICATION: Check what was actually saved
        $verifyStmt = $conn->prepare("SELECT rejection_notes, status FROM documents WHERE document_id = ? AND application_id = ?");
        if ($verifyStmt) {
            $verifyStmt->bind_param("is", $documentId, $applicationId);
            $verifyStmt->execute();
            $verifyResult = $verifyStmt->get_result();
            if ($row = $verifyResult->fetch_assoc()) {
                error_log("BACKEND_DEBUG: VERIFY - After UPDATE, document shows: status='" . $row['status'] . "', rejection_notes='" . $row['rejection_notes'] . "'", E_USER_NOTICE);
            }
            $verifyStmt->close();
        }
    } else {
        error_log("BACKEND_DEBUG: UPDATE WITHOUT rejection_reason - rejectionReason is " . (empty($rejectionReason) ? 'EMPTY' : 'HAS VALUE'), E_USER_NOTICE);
        $affectedRows = executeUpdate($conn, "
            UPDATE documents SET status = ?, status_updated_at = NOW() WHERE document_id = ? AND application_id = ?
        ", "sis", [$newStatus, $documentId, $applicationId]);
        error_log("BACKEND_DEBUG: UPDATE (no reason) completed. Rows affected: $affectedRows", E_USER_NOTICE);
    }

    if ($affectedRows > 0) {
        // Determine which ID to include in description based on loan status
        $referenceId = ($loanStatus === 'Active') ? "loan $loanId" : "application $applicationId";

        // Build description with rejection reason if applicable
        $reasonClause = '';
        if ($newStatus === 'Rejected' && !empty($rejectionReason)) {
            $reasonClause = " | Reason: " . htmlspecialchars($rejectionReason, ENT_QUOTES, 'UTF-8');
        }

        $description = "Document Status Updated | Document: {$document['document_name']} | From: {$document['status']} → To: $newStatus$reasonClause | Reference: $referenceId | Updated by: $adminName";

        // Use user_id as affected_id since application_id and loan_id are VARCHAR
        logActivity($conn, $adminId, $adminRole, 'update', 'document', $description, $userId);

        // CREATE DOCUMENT AUDIT TRAIL - Store all status changes with notes
        $auditQuery = "
            INSERT INTO document_audit (document_id, application_id, document_type_id, old_status, new_status, admin_id, admin_name, admin_role, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";

        // DEBUG: Log audit parameters before insertion
        error_log("🔍 AUDIT_DEBUG: About to insert audit record - doc_id=$documentId, app_id='$applicationId', doc_type_id='" . $document['document_type_id'] . "', old_status='" . $document['status'] . "', new_status='$newStatus', admin_id=$adminId", E_USER_NOTICE);

        $auditResult = executeUpdate($conn, $auditQuery, "isississs", [
            $documentId,
            $applicationId,
            $document['document_type_id'],
            $document['status'],
            $newStatus,
            $adminId,
            $adminName,
            $adminRole,
            !empty($rejectionReason) ? $rejectionReason : null
        ]);
        error_log("🔍 AUDIT_DEBUG: Audit insert result=$auditResult for doc_id=$documentId, app_id='$applicationId'", E_USER_NOTICE);

        // AUTOMATION: Auto-check if all documents are approved
        $allDocuments = executeQuery($conn, "
            SELECT d.document_id, dt.document_name as name, d.status, d.rejection_notes
            FROM documents d
            JOIN document_types dt ON d.document_type_id = dt.document_type_id
            WHERE d.application_id = ?
            ORDER BY dt.document_name
        ", "s", [$applicationId]);

        $approvedCount = 0;
        $rejectedCount = 0;
        $pendingCount = 0;

        foreach ($allDocuments as $doc) {
            if ($doc['status'] === 'Approved') {
                $approvedCount++;
            } elseif ($doc['status'] === 'Rejected') {
                $rejectedCount++;
            } else {
                $pendingCount++;
            }
        }

        $totalDocuments = count($allDocuments);
        $allApproved = ($approvedCount === $totalDocuments);
        $anyRejected = ($rejectedCount > 0);

        // CONSOLIDATED APPROACH: Queue document change in session instead of sending individual emails
        // This allows all document changes to be accumulated and sent as ONE consolidated email
        if (!isset($_SESSION['document_changes_queue'])) {
            $_SESSION['document_changes_queue'] = [];
        }

        // Initialize application entry if not exists
        if (!isset($_SESSION['document_changes_queue'][$applicationId])) {
            $_SESSION['document_changes_queue'][$applicationId] = [
                'changes' => [],
                'admin_name' => $adminName,
                'admin_id' => $adminId,
                'user_id' => $userId,
                'all_documents' => $allDocuments,
                'first_change_time' => date('F j, Y \a\t g:i A')
            ];
        }

        // Add this document change to the queue
        $changeEntry = [
            'document_id' => $documentId,
            'document_name' => $document['document_name'],
            'old_status' => $document['status'],
            'new_status' => $newStatus,
            'rejection_reason' => !empty($rejectionReason) ? $rejectionReason : null,
            'timestamp' => date('H:i:s')
        ];

        $_SESSION['document_changes_queue'][$applicationId]['changes'][] = $changeEntry;
        $_SESSION['document_changes_queue'][$applicationId]['all_documents'] = $allDocuments;
        $_SESSION['document_changes_queue'][$applicationId]['last_update_time'] = date('F j, Y \a\t g:i A');

        $queueCount = count($_SESSION['document_changes_queue'][$applicationId]['changes']);

        error_log("DOCUMENT_CHANGE_QUEUED: App ID $applicationId, Document: {$document['document_name']}, Status: $newStatus (queued for consolidated email)", E_USER_NOTICE);
        error_log("QUEUE_STATUS: Total changes in queue for App $applicationId: " . $queueCount, E_USER_NOTICE);

        // Create notification for document status update (immediate, in-app feedback)
        try {
            require_once 'DocumentNotificationHandler.php';
            $docHandler = new DocumentNotificationHandler($conn);

            // Create notification for the user about document update
            $docHandler->notifyDocumentStatusUpdate(
                $userId,
                $applicationId,
                $document['document_name'],
                $document['status'],
                $newStatus,
                !empty($rejectionReason) ? $rejectionReason : null
            );

            error_log("Notification operation completed for document update", E_USER_NOTICE);
        } catch (Exception $e) {
            error_log("Notification creation failed: " . $e->getMessage(), E_USER_WARNING);
        }

        // AUTOMATION: Add automation status to response for frontend feedback
        $automationStatus = [
            'totalDocuments' => $totalDocuments,
            'approved' => $approvedCount,
            'rejected' => $rejectedCount,
            'pending' => $pendingCount,
            'allApproved' => $allApproved,
            'anyRejected' => $anyRejected
        ];

        $response = [
            'success' => true,
            'message' => "Document status updated to $newStatus successfully.",
            'new_status' => $newStatus,
            'document_name' => $document['document_name'],
            'rejection_reason' => $rejectionReason,
            'queue_count' => $queueCount,
            'queue_ready' => true,
            'automation' => $automationStatus
        ];
    } else {
        $response = ['success' => true, 'message' => 'No changes made to document status.'];
    }

    ob_clean();
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Handle AJAX request for sending queued document changes email
if (!$ACTION_HANDLED && isset($_POST['action']) && $_POST['action'] === 'send_queued_document_email' && isset($_POST['application_id'])) {

    $ACTION_HANDLED = true;
    // CSRF Token Validation
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Security validation failed']);
        http_response_code(403);
        exit;
    }

    // Input Sanitization
    $applicationId = sanitizeString($_POST['application_id'], 50);

    // Validate input
    if (empty($applicationId)) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid application ID']);
        http_response_code(400);
        exit;
    }

    // Send the queued email
    $emailSent = sendQueuedDocumentChangesEmail($conn, $applicationId);

    ob_clean();
    header('Content-Type: application/json');

    if ($emailSent) {
        echo json_encode([
            'success' => true,
            'message' => 'Consolidated email sent successfully with all document changes'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No queued changes to send or email failed'
        ]);
    }
    exit;
}

// Handle AJAX request for sending ENHANCED consolidated document changes email
if (!$ACTION_HANDLED && isset($_POST['action']) && $_POST['action'] === 'send_consolidated_document_email' && isset($_POST['application_id'])) {

    $ACTION_HANDLED = true;
    // CSRF Token Validation
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Security validation failed']);
        http_response_code(403);
        exit;
    }

    // Input Sanitization
    $applicationId = sanitizeString($_POST['application_id'], 50);

    // Validate input
    if (empty($applicationId)) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid application ID']);
        http_response_code(400);
        exit;
    }

    try {
        // Get queued changes from session
        $queuedChanges = [];
        if (isset($_SESSION['document_changes_queue'][$applicationId]['changes'])) {
            $queuedChanges = $_SESSION['document_changes_queue'][$applicationId]['changes'];
        }

        if (empty($queuedChanges)) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'No queued changes to send']);
            http_response_code(400);
            exit;
        }

        // Build enhanced consolidated email with all changes and rejection reasons
        $adminName = $_SESSION['admin_name'] ?? 'Admin';

        // Prepare document changes with enhanced details matching attachment format
        $documentChanges = [];
        foreach ($queuedChanges as $change) {
            $documentChanges[] = [
                'document_name' => $change['document_name'] ?? 'Unknown Document',
                'status' => $change['new_status'] ?? 'Unknown',
                'old_status' => $change['old_status'] ?? 'Pending',
                'rejection_notes' => $change['rejection_reason'] ?? '',
                'status_updated_at' => date('Y-m-d H:i:s'),
                'description' => '', // Could be enhanced with document type description
            ];
        }

        // Enhanced updates array for comprehensive email
        $updates = [
            'template' => 'batch_documents',
            'document_changes' => $documentChanges,
            'batch_count' => count($documentChanges),
            'has_approvals' => array_filter($documentChanges, function ($d) {
                return $d['status'] === 'Approved';
            }) ? true : false,
            'has_rejections' => array_filter($documentChanges, function ($d) {
                return $d['status'] === 'Rejected';
            }) ? true : false,
            'admin_name' => $adminName,
            'admin_updated_at' => date('F j, Y \a\t g:i A'),
            'all_documents' => $documentChanges,
            'consolidated_summary' => 'All document status changes have been reviewed and updated.',
            'application_note' => 'This email contains all recent document status changes for your loan application.'
        ];

        // Send the enhanced consolidated email
        $emailSent = sendConsolidatedUpdateEmail($conn, $applicationId, $updates);

        ob_clean();
        header('Content-Type: application/json');

        if ($emailSent) {
            // Clear the queue after successful sending
            unset($_SESSION['document_changes_queue'][$applicationId]);

            error_log("ENHANCED_CONSOLIDATED_EMAIL: Successfully sent for App ID: $applicationId with " . count($documentChanges) . " changes", E_USER_NOTICE);

            echo json_encode([
                'success' => true,
                'message' => 'Enhanced consolidated email sent successfully with all document changes and detailed feedback!'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to send enhanced consolidated email'
            ]);
        }
    } catch (Exception $e) {
        error_log("ENHANCED_CONSOLIDATED_EMAIL_ERROR: " . $e->getMessage(), E_USER_WARNING);
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Email sending failed: ' . $e->getMessage()]);
        http_response_code(500);
    }

    exit;
}

// Handle AJAX request for checking queue status (only if not already handled)
if (!$ACTION_HANDLED && isset($_POST['action']) && $_POST['action'] === 'check_queue_status' && isset($_POST['application_id'])) {

    error_log("🔄 SECONDARY queue handler reached! This should NOT happen if primary works", E_USER_WARNING);
    $ACTION_HANDLED = true;

    // Input validation
    $applicationId = sanitizeString($_POST['application_id'], 50);

    if (empty($applicationId)) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['queue_count' => 0, 'message' => 'Invalid application ID']);
        http_response_code(400);
        exit;
    }

    // Check if there are queued changes in session
    $queueCount = 0;
    $queuedChanges = [];

    if (
        isset($_SESSION['document_changes_queue'][$applicationId]) &&
        isset($_SESSION['document_changes_queue'][$applicationId]['changes']) &&
        is_array($_SESSION['document_changes_queue'][$applicationId]['changes'])
    ) {
        $queuedChanges = $_SESSION['document_changes_queue'][$applicationId]['changes'];
        $queueCount = count($queuedChanges);
    }

    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'queue_count' => $queueCount,
        'changes' => $queuedChanges,
        'admin_name' => $_SESSION['document_changes_queue'][$applicationId]['admin'] ?? ($_SESSION['admin_name'] ?? 'Admin'),
        'has_queue' => $queueCount > 0,
        'message' => $queueCount > 0 ?
            "Found $queueCount queued document change" . ($queueCount > 1 ? 's' : '') . " ready to send" :
            'No queued changes found'
    ]);
    exit;
}



// Handle AJAX request for sending payment reminder email
if (!$ACTION_HANDLED && isset($_POST['action']) && $_POST['action'] === 'send_payment_reminder' && isset($_POST['payment_id'])) {

    $ACTION_HANDLED = true;
    try {
        // CSRF Token Validation (SECURITY: Prevent cross-site request forgery)
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            http_response_code(403);
            die(json_encode(['success' => false, 'message' => 'Security validation failed']));
        }

        // Input Sanitization (SECURITY: Prevent injection attacks)
        $paymentId = sanitizeInt($_POST['payment_id']);

        // Validate sanitized input is not empty
        if (empty($paymentId) || $paymentId <= 0) {
            http_response_code(400);
            die(json_encode(['success' => false, 'message' => 'Invalid payment ID']));
        }

        // Clear all output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');

        $payment = executeQuery($conn, "
            SELECT ps.due_date, ps.amount, ps.loan_id AS payment_loan_id, u.email, u.first_name, u.last_name, la.application_id, la.loan_id
            FROM payment_schedules ps
            JOIN loans l ON ps.loan_id = l.loan_id
            JOIN loan_applications la ON l.application_id = la.application_id
            JOIN users1 u ON la.user_id = u.id
            WHERE ps.payment_id = ?
        ", "i", [$paymentId]);

        if (empty($payment)) {
            http_response_code(404);
            die(json_encode(['success' => false, 'message' => 'Payment record not found']));
        }
        $payment = $payment[0];

        $to = sanitizeEmail($payment['email']);
        if (empty($to)) {
            http_response_code(400);
            die(json_encode(['success' => false, 'message' => 'Invalid recipient email']));
        }

        $name = htmlspecialchars(trim($payment['first_name'] . ' ' . $payment['last_name']), ENT_QUOTES, 'UTF-8');
        $dueDate = date('Y-m-d', strtotime($payment['due_date']));
        $amount = number_format($payment['amount'], 2);

        // Create fresh PHPMailer instance for this reminder
        $reminderMail = new PHPMailer(true);
        $reminderMail->isSMTP();
        $reminderMail->Host = 'smtp.gmail.com';
        $reminderMail->SMTPAuth = true;
        $reminderMail->Username = 'scycloan@gmail.com';
        $reminderMail->Password = 'xbvo zplr dpme ixxj';
        $reminderMail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $reminderMail->Port = 587;

        $reminderMail->setFrom('scycloan@gmail.com', 'CLDD Loan Support');
        $reminderMail->addAddress($to);

        $reminderMail->isHTML(true);
        $reminderMail->Subject = 'CLDD Loan Program - Payment Reminder Notice';

        // Build enhanced HTML email template
        $currentDate = date('F j, Y');
        $daysUntilDue = ceil((strtotime($payment['due_date']) - time()) / (60 * 60 * 24));
        $urgencyLevel = $daysUntilDue <= 7 ? 'URGENT' : ($daysUntilDue <= 15 ? 'NOTICE' : 'REMINDER');
        $urgencyColor = $daysUntilDue <= 7 ? '#d32f2f' : ($daysUntilDue <= 15 ? '#f57c00' : '#2d7d32');

        $reminderMail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Payment Reminder - CLDD Loan Program</title>
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; background-color: #f5f5f5; }
                .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); overflow: hidden; }
                .header { background: linear-gradient(135deg, #2d7d32 0%, #1b5e20 100%); color: white; padding: 30px 20px; text-align: center; }
                .header h1 { margin: 0; font-size: 28px; font-weight: 600; }
                .urgency-badge { display: inline-block; background-color: " . $urgencyColor . "; color: white; padding: 8px 16px; border-radius: 4px; font-size: 12px; font-weight: 700; margin-top: 10px; }
                .content { padding: 30px 20px; }
                .greeting { font-size: 18px; color: #2d7d32; margin-bottom: 20px; font-weight: 600; }
                .alert-box { background-color: #fff3cd; border-left: 4px solid #f57c00; padding: 15px; border-radius: 4px; margin: 20px 0; }
                .alert-box strong { color: #f57c00; }
                .details-box { background-color: #f0f4f8; border-radius: 8px; padding: 20px; margin: 20px 0; }
                .detail-row { display: flex; justify-content: space-between; margin: 12px 0; }
                .detail-label { font-weight: 600; color: #555; min-width: 120px; }
                .detail-value { color: #2d7d32; font-weight: 700; }
                .status-paid { color: #4caf50; }
                .status-pending { color: #f57c00; }
                .button { display: inline-block; background-color: #2d7d32; color: white; padding: 12px 30px; border-radius: 4px; text-decoration: none; margin: 15px 0; font-weight: 600; text-align: center; }
                .button:hover { background-color: #1b5e20; }
                .divider { border-top: 1px solid #e0e0e0; margin: 20px 0; }
                .footer { background-color: #f5f5f5; padding: 20px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #e0e0e0; }
                .footer-links { margin: 10px 0; }
                .footer-links a { color: #2d7d32; text-decoration: none; margin: 0 10px; }
                .warning-text { color: #d32f2f; font-size: 14px; margin-top: 10px; font-weight: 500; }
                .timeline { background-color: #e8f5e9; padding: 15px; border-radius: 4px; margin: 15px 0; border-left: 4px solid #2d7d32; }
                .timeline-item { margin: 8px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <!-- Header -->
                <div class='header'>
                    <h1>Payment Reminder Notice</h1>
                    <div class='urgency-badge'>$urgencyLevel - Due in $daysUntilDue Days</div>
                </div>

                <!-- Main Content -->
                <div class='content'>
                    <p class='greeting'>Hello <strong>$name</strong>,</p>

                    <p>We hope you're doing well. This is a friendly reminder about your upcoming loan payment with the <strong>CLDD Loan Program</strong>.</p>

                    <!-- Alert Box -->
                    <div class='alert-box'>
                        <strong>Action Required:</strong> Your payment is due on <strong>$dueDate</strong>. Please ensure timely payment to avoid penalties and maintain good standing with your loan account.
                    </div>

                    <!-- Payment Details -->
                    <div class='details-box'>
                        <p style='margin-top: 0; font-weight: 600; color: #2d7d32; font-size: 16px;'>📋 Payment Details</p>
                        <div class='detail-row'>
                            <span class='detail-label'>Loan Application:</span>
                            <span class='detail-value'>{$payment['application_id']}</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Amount Due:</span>
                            <span class='detail-value'>₱" . number_format($payment['amount'], 2) . "</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Due Date:</span>
                            <span class='detail-value'>$dueDate</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Days Remaining:</span>
                            <span class='detail-value'><strong>$daysUntilDue Days</strong></span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Payment ID:</span>
                            <span class='detail-value' style='font-size: 12px;'>#$paymentId</span>
                        </div>
                    </div>

                    <!-- Next Steps -->
                    <div class='timeline'>
                        <p style='margin: 0 0 10px 0; font-weight: 600; color: #2d7d32;'>Important Dates & Actions</p>
                        <div class='timeline-item'>✓ <strong>Today:</strong> You received this reminder</div>
                        <div class='timeline-item'><strong>Due Date ($dueDate):</strong> Payment must be received</div>
                        <div class='timeline-item'> <strong>After Due Date:</strong> Late fees may apply</div>
                    </div>

                    <!-- Action Button -->
                    <div style='text-align: center;'>
                        <a href='user_dashboard.php' class='button'>Pay Now →</a>
                    </div>

                    <p class='warning-text'>Late payments may result in additional charges and may affect your credit standing. Please prioritize this payment.</p>

                    <!-- Support Info -->
                    <div class='divider'></div>
                    <p style='color: #666; font-size: 14px;'>
                        <strong>Need Help?</strong><br>
                        If you have questions about this payment or need to discuss payment arrangements, please don't hesitate to contact our support team. We're here to help!
                    </p>
                </div>

                <!-- Footer -->
                <div class='footer'>
                    <p style='margin: 0 0 10px 0;'>
                        <strong>CLDD Loan Program</strong><br>
                        Providing accessible and reliable lending solutions
                    </p>
                    <div class='footer-links'>
                        <a href='landing_page.php'>Visit Website</a>
                    </div>
                    <p style='margin: 10px 0 0 0; color: #999; font-size: 11px;'>
                        This is an automated message sent on $currentDate. Please do not reply directly to this email. For support, use the contact methods above.
                    </p>
                </div>
            </div>
        </body>
        </html>
        ";


        $reminderMail->send();
        logActivity(
            $conn,
            $adminId,
            $adminRole,
            'send_reminder',
            'payment_schedule',
            "Payment Reminder Sent | Amount: $amount PHP | Due: $dueDate | Recipient: {$payment['first_name']} {$payment['last_name']}",
            $payment['loan_id']
        );

        http_response_code(200);
        die(json_encode(['success' => true, 'message' => 'Payment reminder sent successfully.']));
    } catch (Exception $e) {
        error_log("Notification operation failed", E_USER_WARNING);
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Failed to process payment reminder']));
    }
}

// ============================================
// SEARCH & FILTER FUNCTIONALITY
// ============================================

// Handle search and filter for loan applicants
if (isset($_POST['action']) && $_POST['action'] === 'search_applications') {
    try {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            handleApiError('Security validation failed', 403);
        }

        // Sanitize inputs
        $searchTerm = sanitizeString($_POST['search'] ?? '', 100);
        $statusFilter = sanitizeString($_POST['status_filter'] ?? '', 50);
        $preApprovalFilter = sanitizeString($_POST['pre_approval_filter'] ?? '', 50);
        $sortBy = sanitizeString($_POST['sort_by'] ?? 'created_at', 50);
        $sortOrder = sanitizeString($_POST['sort_order'] ?? 'DESC', 4);

        // Validate sort parameters to prevent SQL injection
        $validSortColumns = ['application_id', 'first_name', 'amount_applied', 'status', 'pre_approval_status', 'created_at'];
        $validSortOrders = ['ASC', 'DESC'];

        if (!in_array($sortBy, $validSortColumns, true))
            $sortBy = 'created_at';
        if (!in_array($sortOrder, $validSortOrders, true))
            $sortOrder = 'DESC';

        // Build dynamic query
        $query = "SELECT la.application_id, u.first_name, u.last_name, lt.type_name, la.amount_applied, la.status, 
                         la.pre_approval_status, la.credit_investigation_status, la.created_at, la.final_loan_amount
                  FROM loan_applications la
                  JOIN users1 u ON la.user_id = u.id
                  JOIN loan_types lt ON la.loan_type_id = lt.loan_type_id
                  WHERE 1=1";

        $params = [];
        $types = "";

        // Add search condition
        if (!empty($searchTerm)) {
            $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR la.application_id LIKE ?)";
            $searchPattern = "%$searchTerm%";
            $params = array_merge($params, [$searchPattern, $searchPattern, $searchPattern, $searchPattern]);
            $types .= "ssss";
        }

        // Add status filter
        if (!empty($statusFilter) && validateEnum($statusFilter, ['Pending', 'Active', 'Completed', 'Closed', 'New', 'Renewal'])) {
            $query .= " AND la.status = ?";
            $params[] = $statusFilter;
            $types .= "s";
        }

        // Add pre-approval filter
        if (!empty($preApprovalFilter) && validateEnum($preApprovalFilter, ['Pending', 'Approved', 'Rejected'])) {
            $query .= " AND la.pre_approval_status = ?";
            $params[] = $preApprovalFilter;
            $types .= "s";
        }

        // Add sorting
        $query .= " ORDER BY $sortBy $sortOrder LIMIT 500";

        $results = executeQuery($conn, $query, $types, $params);

        ob_clean();
        header('Content-Type: application/json');
        handleApiSuccess($results ?? [], 'Search completed successfully');
    } catch (Exception $e) {
        error_log("Search operation failed", E_USER_WARNING);
        handleApiError('Search operation failed', 400);
    }
}

// Handle unknown POST actions (only if not already handled)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $ACTION_HANDLED === false) {
    // If we reach here, it means the action was not recognized by any handler above
    $action = sanitizeString($_POST['action'], 50);

    // CRITICAL DEBUG: Log that we reached fallback
    error_log("TRACE: REACHED FALLBACK HANDLER! ACTION_HANDLED = " . ($ACTION_HANDLED ? 'TRUE' : 'FALSE') . ", Action = " . $action, E_USER_WARNING);

    $debugInfo = [
        'action_received' => $action,
        'application_id' => $_POST['application_id'] ?? 'NOT SET',
        'csrf_token_set' => isset($_POST['csrf_token']) ? 'YES' : 'NO',
        'all_post_keys' => array_keys($_POST),
        'timestamp' => date('Y-m-d H:i:s')
    ];

    error_log("Admin2 Dashboard: Unknown POST action received - " . json_encode($debugInfo), E_USER_WARNING);

    ob_clean();
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid action',
        'debug_info' => $debugInfo,
        'server_debug' => 'Fallback handler reached - check error logs',
        'handled_flag' => $ACTION_HANDLED
    ]);
    exit;
}

// ============================================
// EXPORT FUNCTIONALITY
// ============================================

// Handle export to CSV/JSON
if (isset($_GET['action']) && $_GET['action'] === 'export_applications') {
    try {
        // Validate export format
        $exportFormat = sanitizeString($_GET['format'] ?? 'csv', 10);
        if (!validateEnum($exportFormat, ['csv', 'json'])) {
            $exportFormat = 'csv';
        }

        // Fetch data to export
        $query = "SELECT la.application_id, u.first_name, u.last_name, u.email, u.contact, 
                         lt.type_name, la.amount_applied, la.final_loan_amount, la.status, 
                         la.pre_approval_status, la.credit_investigation_status, la.created_at
                  FROM loan_applications la
                  JOIN users1 u ON la.user_id = u.id
                  JOIN loan_types lt ON la.loan_type_id = lt.loan_type_id
                  ORDER BY la.created_at DESC LIMIT 1000";

        $results = executeQuery($conn, $query, "", []);

        if (empty($results)) {
            handleApiError('No data available to export', 400);
        }

        $filename = 'loan_applications_' . date('Y-m-d_His');

        if ($exportFormat === 'csv') {
            // Export to CSV
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '.csv"');

            $output = fopen('php://output', 'w');

            // Write BOM for proper encoding
            fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Write header
            fputcsv($output, [
                'Application ID',
                'First Name',
                'Last Name',
                'Email',
                'Contact',
                'Loan Type',
                'Amount Applied',
                'Final Amount',
                'Status',
                'Pre-Approval Status',
                'Credit Investigation',
                'Created Date'
            ]);

            // Write data rows
            foreach ($results as $row) {
                fputcsv($output, [
                    $row['application_id'],
                    htmlspecialchars_decode($row['first_name']),
                    htmlspecialchars_decode($row['last_name']),
                    $row['email'],
                    $row['contact'],
                    $row['type_name'],
                    number_format($row['amount_applied'], 2),
                    $row['final_loan_amount'] ? number_format($row['final_loan_amount'], 2) : '',
                    $row['status'],
                    $row['pre_approval_status'],
                    $row['credit_investigation_status'],
                    $row['created_at']
                ]);
            }
            fclose($output);

            logOperation('export', 'success', "CSV export of " . count($results) . " records");
        } else {
            // Export to JSON
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '.json"');

            echo json_encode([
                'export_date' => date('Y-m-d H:i:s'),
                'total_records' => count($results),
                'data' => $results
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            logOperation('export', 'success', "JSON export of " . count($results) . " records");
        }
        exit;
    } catch (Exception $e) {
        error_log("Export operation failed", E_USER_WARNING);
        handleApiError('Export failed', 400);
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin 2 Dashboard</title>
    <link rel="stylesheet" href="CSS/admin_dashboard.css">
    <link rel="stylesheet" href="CSS/admin_profile.css">
    <link rel="stylesheet" href="CSS/admin2_dashboard.css">
    <link rel="stylesheet" href="CSS/admin_dashboard.css">
    <link rel="stylesheet" href="CSS/nav_active.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <!-- SheetJS library for Excel export with styling -->
    <script src="https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js"></script>
</head>

<style>
    /* ===== NAVIGATION & BURGER MENU ===== */
    .nav-container {
        display: flex;
        flex-direction: column;
    }

    .burger {
        display: none;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        background: none;
        border: none;
        cursor: pointer;
        padding: 10px;
        gap: 6px;
    }

    .burger span {
        display: block;
        width: 25px;
        height: 3px;
        background-color: #2d7d32;
        border-radius: 2px;
        transition: all 0.3s ease;
    }

    .burger.active span:nth-child(1) {
        transform: rotate(45deg) translateY(11px);
    }

    .burger.active span:nth-child(2) {
        opacity: 0;
    }

    .burger.active span:nth-child(3) {
        transform: rotate(-45deg) translateY(-11px);
    }

    /* Show burger menu on mobile */
    @media (max-width: 768px) {
        .burger {
            display: flex;
        }

        nav {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            flex-direction: column;
            background: white;
            z-index: 999;
            overflow-y: auto;
            padding-top: 20px;
        }

        nav.active {
            display: flex;
        }
    }

    /* CRITICAL: Force loading overlay to ALWAYS be on top */
    #loadingOverlay {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        width: 100% !important;
        height: 100% !important;
        z-index: 2147483647 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        pointer-events: auto !important;
        background: rgba(0, 0, 0, 0.7) !important;
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

    /* ============================================
       MODAL DIALOG STYLES (Section 9)
       ============================================ */

    .modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: none;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        z-index: 2000;
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
    }

    .modal.show {
        display: flex !important;
    }

    .modal-content {
        background: white;
        border-radius: 8px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        width: 95%;
        max-width: 100%;
        max-height: 85vh;
        padding: 0;
        position: relative;
        display: flex;
        flex-direction: column;
        animation: slideUp 0.3s ease-out;
    }

    /* Animation for modal fade in */
    @keyframes fadeIn {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }

    /* Animation for modal slide up */
    @keyframes slideUp {
        from {
            transform: translateY(30px);
            opacity: 0;
        }

        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    /* Animation for icon scale */
    @keyframes slideInScale {
        from {
            transform: scale(0.8);
            opacity: 0;
        }

        to {
            transform: scale(1);
            opacity: 1;
        }
    }


    /* Modal header styling */
    .modal-header {
        padding: 20px 25px !important;
        flex-shrink: 0;
        border-bottom: none;
        background: linear-gradient(135deg, #f4c430 0%, #daa520 100%);
        border-radius: 8px 8px 0 0;
    }

    .header-content {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .header-icon {
        font-size: 32px;
        color: #3b82f6;
    }

    .modal-header h2 {
        margin: 0 !important;
        font-size: 24px !important;
        color: white;
        font-family: 'Poppins', 'Segoe UI', sans-serif !important;
        font-weight: 700 !important;
        text-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .header-subtitle {
        margin: 5px 0 0 0;
        color: #6b7280;
        font-size: 14px;
    }

    /* Modal body styling */
    .modal-body {
        flex: 1;
        overflow-y: auto;
        padding: 20px 25px;
        min-height: 0;
        background: #ffffff;
        font-size: 16px;
    }

    /* Section title styling with green underline */
    .section-title {
        font-size: 18px !important;
        font-weight: 700 !important;
        color: #1f2937 !important;
        margin: 20px 0 15px 0 !important;
        padding-bottom: 10px !important;
        border-bottom: 3px solid #2d7d32 !important;
        display: flex !important;
        align-items: center !important;
        gap: 10px !important;
    }

    /* Information row styling for two-column layout */
    .info-row {
        display: grid !important;
        grid-template-columns: 1fr 1fr !important;
        gap: 20px !important;
        margin-bottom: 15px !important;
    }

    @media (max-width: 768px) {
        .info-row {
            grid-template-columns: 1fr !important;
        }
    }

    /* Info item styling */
    .info-item {
        display: flex !important;
        flex-direction: column !important;
    }

    .info-label {
        font-size: 13px !important;
        font-weight: 600 !important;
        color: #374151 !important;
        margin-bottom: 4px !important;
        text-transform: uppercase !important;
        letter-spacing: 0.5px !important;
    }

    .info-value {
        font-size: 15px !important;
        color: #1f2937 !important;
        font-weight: 500 !important;
    }

    .modal-body::-webkit-scrollbar {
        width: 6px;
    }

    .modal-body::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }

    .modal-body::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 4px;
    }

    .modal-body::-webkit-scrollbar-thumb:hover {
        background: #555;
    }

    /* Form elements in modals */
    .modal select,
    .modal textarea,
    .modal input[type="text"],
    .modal input[type="email"],
    .modal input[type="number"],
    .modal input[type="date"] {
        width: 100%;
        padding: 10px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 14px;
        font-family: 'Segoe UI', Roboto, sans-serif;
        box-sizing: border-box;
        transition: border-color 0.2s;
    }

    .modal select:focus,
    .modal textarea:focus,
    .modal input[type="text"]:focus,
    .modal input[type="email"]:focus,
    .modal input[type="number"]:focus,
    .modal input[type="date"]:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    /* Button styles in modals */
    .modal .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 500;
        font-size: 14px;
        transition: all 0.2s;
    }

    .modal .btn-primary {
        background: #3b82f6;
        color: white;
    }

    .modal .btn-primary:hover {
        background: #2563eb;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
    }

    .modal .btn-secondary {
        background: #f3f4f6;
        color: #374151;
        border: 1px solid #d1d5db;
    }

    .modal .btn-secondary:hover {
        background: #e5e7eb;
    }

    .modal .btn-danger {
        background: #ef4444;
        color: white;
    }

    .modal .btn-danger:hover {
        background: #dc2626;
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.4);
    }

    /* Button State Styling */
    .btn:disabled,
    .btn[disabled] {
        opacity: 0.6;
        cursor: not-allowed;
        background-color: #9ca3af !important;
    }

    .btn.loading {
        position: relative;
        color: transparent;
        pointer-events: none;
    }

    .btn.loading::after {
        content: '';
        position: absolute;
        width: 16px;
        height: 16px;
        top: 50%;
        left: 50%;
        margin-left: -8px;
        margin-top: -8px;
        border: 2px solid #ffffff;
        border-radius: 50%;
        border-top-color: transparent;
        animation: spin 0.6s linear infinite;
    }

    .btn.loading:disabled {
        opacity: 1;
    }

    /* Scrollable Timelines for Modal */
    .remarks-timeline,
    .logs-timeline {
        max-height: 300px;
        overflow-y: auto;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        padding: 10px;
        background-color: #fafbfc;
    }

    /* Scrollbar styling for timelines */
    .remarks-timeline::-webkit-scrollbar,
    .logs-timeline::-webkit-scrollbar {
        width: 6px;
    }

    .remarks-timeline::-webkit-scrollbar-track,
    .logs-timeline::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 10px;
    }

    .remarks-timeline::-webkit-scrollbar-thumb,
    .logs-timeline::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 10px;
    }

    .remarks-timeline::-webkit-scrollbar-thumb:hover,
    .logs-timeline::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }

    /* Remark and Log Item Styling */
    .remark-item,
    .log-item {
        transition: all 0.2s ease;
    }

    .remark-item:last-child,
    .log-item:last-child {
        margin-bottom: 0;
    }

    .remark-item:hover,
    .log-item:hover {
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }

    .remark-content {
        font-size: 14px;
        color: #374151;
        line-height: 1.5;
    }

    .log-content {
        font-size: 13px;
        color: #4b5563;
    }

    .log-header {
        display: flex;
        gap: 8px;
        margin-bottom: 6px;
        font-size: 12px;
    }

    .log-description {
        font-size: 13px;
        color: #1f2937;
        line-height: 1.4;
    }

    /* ===== MOBILE RESPONSIVENESS ===== */
    /* Base styles for all devices */
    * {
        box-sizing: border-box;
    }

    /* Small devices: phones (320px to 480px) */
    @media (max-width: 480px) {
        body {
            font-size: 14px;
        }

        /* Navigation and sidebar */
        nav {
            width: 100% !important;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: auto;
            padding: 10px !important;
            z-index: 1000;
        }

        .main-content {
            margin-bottom: 200px !important;
            padding: 10px !important;
        }

        /* Buttons */
        button,
        .btn {
            padding: 12px 16px !important;
            font-size: 14px !important;
            min-height: 44px !important;
            min-width: 44px !important;
        }

        /* Forms */
        input,
        textarea,
        select {
            padding: 12px !important;
            font-size: 16px !important;
            min-height: 44px !important;
            width: 100% !important;
        }

        /* Tables */
        table {
            font-size: 12px !important;
        }

        .documents-table-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* Financial grid */
        .financial-grid {
            grid-template-columns: 1fr !important;
            gap: 10px !important;
        }

        .financial-card {
            padding: 12px !important;
        }

        /* Status buttons */
        .status-buttons {
            flex-direction: column !important;
            gap: 8px !important;
        }

        .status-btn {
            padding: 10px 12px !important;
            font-size: 12px !important;
            width: 100% !important;
        }

        /* Pre-approval section */
        .pre-approval-section {
            padding: 15px !important;
            margin: 10px 0 !important;
        }

        .requirements-checklist {
            font-size: 13px !important;
        }

        /* Notifications */
        #loadingOverlay>div {
            padding: 20px !important;
            width: 90% !important;
        }

        .notification {
            max-width: 90vw !important;
            top: 10px !important;
            right: 10px !important;
            left: auto !important;
        }

        /* Dropdowns */
        .dropdown-content {
            position: absolute;
            top: 100%;
            left: 0;
            background: white;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            z-index: 500;
        }

        /* Charts */
        .chart-container {
            max-height: 300px !important;
        }
    }

    /* Medium devices: tablets (481px to 768px) */
    @media (min-width: 481px) and (max-width: 768px) {
        body {
            font-size: 15px;
        }

        .main-content {
            margin-left: 150px !important;
            padding: 15px !important;
        }

        .modal-content {
            width: 90% !important;
            max-height: 85vh !important;
            padding: 0 !important;
            display: flex;
            flex-direction: column;
        }

        .modal-body {
            max-height: calc(85vh - 60px) !important;
            overflow-y: auto !important;
            padding: 15px !important;
        }

        .remarks-timeline,
        .logs-timeline {
            max-height: 350px !important;
        }

        .financial-grid {
            grid-template-columns: repeat(2, 1fr) !important;
            gap: 12px !important;
        }

        .status-buttons {
            flex-direction: row;
            gap: 10px;
            flex-wrap: wrap;
        }

        .status-btn {
            flex: 1;
            min-width: 100px;
        }

        table {
            font-size: 13px !important;
        }

        input,
        textarea,
        select {
            padding: 10px !important;
            font-size: 15px !important;
        }

        button,
        .btn {
            padding: 10px 16px !important;
            font-size: 14px !important;
        }
    }

    /* Large devices: desktops (769px and up) */
    @media (min-width: 769px) {
        .main-content {
            margin-left: 280px;
        }

        .modal-content {
            width: 90%;
            max-width: 900px;
            max-height: 80vh;
            padding: 0;
            display: flex;
            flex-direction: column;
        }

        .modal-body {
            max-height: calc(80vh - 60px);
            overflow-y: auto;
            padding: 15px;
        }

        .remarks-timeline,
        .logs-timeline {
            max-height: 400px;
        }

        .financial-grid {
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }
    }

    /* Landscape orientation adjustments */
    @media (max-height: 500px) and (orientation: landscape) {
        nav {
            height: auto;
            padding: 5px !important;
        }

        .main-content {
            margin-bottom: 80px !important;
        }

        .modal-content {
            max-height: 95vh !important;
            padding: 0 !important;
        }

        .modal-body {
            max-height: calc(95vh - 60px) !important;
            overflow-y: auto !important;
            padding: 10px !important;
        }

        .documents-table-wrapper {
            max-height: 250px !important;
        }

        .remarks-timeline,
        .logs-timeline {
            max-height: 300px !important;
        }
    }

    /* Touch device enhancements */
    @media (hover: none) and (pointer: coarse) {

        /* Increase touch target sizes */
        button,
        a,
        input[type="checkbox"],
        input[type="radio"] {
            min-height: 44px;
            min-width: 44px;
        }

        /* Reduce animations on touch devices */
        * {
            -webkit-tap-highlight-color: rgba(45, 125, 50, 0.3);
        }

        /* Improve scrolling performance */
        .documents-table-wrapper,
        .remarks-timeline,
        .logs-timeline {
            -webkit-overflow-scrolling: touch;
        }
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

    /* Enhanced Pre-Approval Section Styles */
    .pre-approval-section {
        background: linear-gradient(135deg, #f0f7f0 0%, #e8f5e9 100%);
        border-radius: 12px;
        padding: 25px;
        margin: 20px 0;
    }

    .pre-approval-section h3 {
        color: #1b5e20;
        margin-top: 0;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 1.3rem;
    }

    /* Status Display Badge */
    .status-display {
        margin-bottom: 20px;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.95rem;
    }

    .status-badge.status-pending {
        background-color: #fff3cd;
        color: #856404;
        border: 1px solid #ffeaa7;
    }

    .status-badge.status-approved {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .status-badge.status-rejected {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    /* Document Requirements Checklist */
    .requirements-checklist {
        background: white;
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 20px;
    }

    .requirements-checklist h4 {
        margin: 0 0 15px 0;
        color: #2d7d32;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .checklist-items {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-bottom: 12px;
    }

    .checklist-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 8px;
        border-radius: 6px;
        background-color: #fafafa;
    }

    .doc-status {
        display: inline-flex;
        align-items: center;
        min-width: 24px;
    }

    .doc-status.approved {
        color: #27ae60;
    }

    .doc-status.pending {
        color: #f39c12;
    }

    .doc-name {
        flex: 1;
        font-weight: 500;
        color: #333;
    }

    .doc-status-label {
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .checklist-item .doc-status-label {
        background-color: #e8f5e9;
        color: #2d7d32;
    }

    .checklist-item:has(.doc-status.pending) .doc-status-label {
        background-color: #fff3cd;
        color: #856404;
    }

    .checklist-item:has(.doc-status.rejected) .doc-status-label {
        background-color: #f8d7da;
        color: #721c24;
    }

    .req-success,
    .req-warning {
        padding: 10px 12px;
        border-radius: 6px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .req-success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .req-warning {
        background-color: #fff3cd;
        color: #856404;
        border: 1px solid #ffeaa7;
    }

    /* Status Selection Buttons */
    .status-selection {
        margin: 20px 0;
    }

    .status-selection label {
        display: block;
        margin-bottom: 12px;
        color: #1b5e20;
        font-weight: 600;
    }

    .status-buttons {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 12px;
    }

    .status-selection input[type="radio"] {
        display: none;
    }

    .status-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 16px;
        border: 2px solid #ddd;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        background-color: white;
        font-weight: 600;
        font-size: 0.9rem;
    }

    .status-btn:hover:not(:disabled) {
        border-color: #2d7d32;
        background-color: #f0f7f0;
    }

    .status-btn i {
        font-size: 1.5rem;
    }

    .status-btn-pending {
        color: #f39c12;
        border-color: #f39c12;
    }

    .status-btn-approved {
        color: #27ae60;
        border-color: #27ae60;
    }

    .status-btn-rejected {
        color: #e74c3c;
        border-color: #e74c3c;
    }

    .status-selection input[type="radio"]:checked+.status-btn {
        background-color: var(--btn-color);
        color: white;
        border-color: var(--btn-color);
    }

    .status-selection #status-pending:checked+.status-btn-pending {
        --btn-color: #f39c12;
        background-color: #f39c12;
        color: white;
    }

    .status-selection #status-approved:checked+.status-btn-approved {
        --btn-color: #27ae60;
        background-color: #27ae60;
        color: white;
    }

    .status-selection #status-rejected:checked+.status-btn-rejected {
        --btn-color: #e74c3c;
        background-color: #e74c3c;
        color: white;
    }

    /* Remarks/Decision Notes */
    .remarks-group {
        margin: 20px 0;
    }

    .remarks-group label {
        display: block;
        margin-bottom: 8px;
        color: #1b5e20;
        font-weight: 600;
    }

    .remarks-textarea {
        width: 100%;
        min-height: 100px;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-family: inherit;
        font-size: 0.95rem;
        resize: vertical;
        box-sizing: border-box;
    }

    .remarks-textarea:focus {
        outline: none;
        border-color: #2d7d32;
        box-shadow: 0 0 0 3px rgba(45, 125, 50, 0.1);
    }

    .char-count {
        margin-top: 5px;
        font-size: 0.85rem;
        color: #999;
    }

    /* Form Actions */
    .form-actions {
        display: flex;
        gap: 12px;
        margin-top: 20px;
    }

    .btn {
        flex: 1;
        padding: 12px 16px;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-size: 0.95rem;
    }

    .btn-primary {
        background: linear-gradient(135deg, #4caf50 0%, #2e7d32 100%);
        color: white;
        box-shadow: 0 2px 8px rgba(76, 175, 80, 0.2);
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
        box-shadow: 0 4px 12px rgba(45, 125, 50, 0.3);
        transform: translateY(-2px);
    }

    .btn-secondary {
        background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
        color: white;
        border: none;
        box-shadow: 0 2px 8px rgba(244, 67, 54, 0.2);
    }

    .btn-secondary:hover {
        background: linear-gradient(135deg, #d32f2f 0%, #b71c1c 100%);
        box-shadow: 0 4px 12px rgba(244, 67, 54, 0.3);
        transform: translateY(-2px);
    }

    .btn-submit-approval {
        background: linear-gradient(135deg, #4caf50 0%, #2e7d32 100%);
        box-shadow: 0 2px 8px rgba(76, 175, 80, 0.2);
    }

    .btn-submit-approval:hover {
        background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
        box-shadow: 0 4px 12px rgba(45, 125, 50, 0.3);
        transform: translateY(-2px);
    }

    .btn-submit-approval:active {
        transform: scale(0.98);
    }

    /* Pre-Approval Form Styling */
    .pre-approval-form {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    /* Modal Section Enhancement */
    .modal-section {
        margin-bottom: 15px;
        padding: 12px;
        background: #fafafa;
        border-radius: 6px;
        border: 1px solid #e0e0e0;
    }

    .modal-section h3 {
        margin: 0 0 10px 0;
        padding-bottom: 8px;
        border-bottom: 2px solid #2d7d32;
        color: #1b5e20;
        font-size: 1rem;
    }

    .modal-section h4 {
        margin: 8px 0 6px 0;
        font-size: 0.95rem;
        color: #2d7d32;
    }

    .financial-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 10px;
        margin-top: 10px;
    }

    .financial-card {
        background: white;
        padding: 10px;
        border-radius: 4px;
        border: 1px solid #e0e0e0;
        font-size: 0.9rem;
    }

    .financial-items {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .financial-row {
        display: flex;
        justify-content: space-between;
        font-size: 0.85rem;
        padding: 4px 0;
    }

    .financial-row.total-row {
        font-weight: 600;
        padding-top: 6px;
        border-top: 1px solid #e0e0e0;
        margin-top: 4px;
    }

    .highlight-green {
        color: #27ae60;
    }

    .highlight-red {
        color: #e74c3c;
    }

    .remarks-timeline,
    .logs-timeline {
        max-height: 400px;
        overflow-y: auto;
        padding-right: 8px;
    }

    .remarks-timeline::-webkit-scrollbar,
    .logs-timeline::-webkit-scrollbar {
        width: 6px;
    }

    .remarks-timeline::-webkit-scrollbar-track,
    .logs-timeline::-webkit-scrollbar-track {
        background: #f1f1f1;
    }

    .remarks-timeline::-webkit-scrollbar-thumb,
    .logs-timeline::-webkit-scrollbar-thumb {
        background: #ccc;
        border-radius: 3px;
    }

    .remarks-timeline::-webkit-scrollbar-thumb:hover,
    .logs-timeline::-webkit-scrollbar-thumb:hover {
        background: #999;
    }

    .remark-item {
        margin-bottom: 8px;
        padding: 8px;
        background: white;
        border-radius: 4px;
        border-left: 3px solid #fbc02d;
        font-size: 0.85rem;
    }

    .remark-header {
        margin-bottom: 6px;
        font-weight: 600;
        color: #1b5e20;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }

    .remark-content {
        background: #fffef0;
        padding: 8px;
        border-radius: 3px;
        font-size: 0.8rem;
        line-height: 1.4;
    }

    .log-item {
        margin-bottom: 6px;
        border-radius: 4px;
        font-size: 0.8rem;
    }

    .log-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 4px;
        color: #666;
        font-size: 0.75rem;
    }

    .log-description {
        color: #333;
        font-size: 0.85rem;
    }



    /* Document Status Badges */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .status-badge.status-approved {
        background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        color: #155724;
        border: 1px solid #b1dfbb;
    }

    .status-badge.status-rejected {
        background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
        color: #721c24;
        border: 1px solid #f1b0b7;
    }

    .status-badge.status-pending {
        background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
        color: #856404;
        border: 1px solid #ffe69c;
    }

    /* Document Actions */
    .document-actions {
        display: flex;
        gap: 10px;
        align-items: center;
        justify-content: flex-end;
    }

    .doc-action-btn {
        border: none;
        border-radius: 4px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 4px;
        font-size: 0.75rem;
        min-width: 80px;
        text-transform: uppercase;
        letter-spacing: 0.2px;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
    }

    .doc-approve {
        background: linear-gradient(135deg, #27ae60 0%, #229954 100%) !important;
        color: white !important;
        border: none !important;
    }

    .doc-approve:hover:not(:disabled) {
        background: linear-gradient(135deg, #229954 0%, #1e8449 100%) !important;
        box-shadow: 0 6px 20px rgba(39, 174, 96, 0.4) !important;
        transform: translateY(-2px) !important;
    }

    .doc-approve:active:not(:disabled) {
        transform: translateY(0) !important;
        box-shadow: 0 2px 8px rgba(39, 174, 96, 0.2) !important;
    }

    .doc-approve:disabled {
        background: linear-gradient(135deg, #d3d3d3 0%, #c0c0c0 100%) !important;
        color: #999 !important;
        cursor: not-allowed !important;
        box-shadow: none !important;
    }

    .doc-reject {
        background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%) !important;
        color: white !important;
        border: none !important;
    }

    .doc-reject:hover:not(:disabled) {
        background: linear-gradient(135deg, #c0392b 0%, #a93226 100%) !important;
        box-shadow: 0 6px 20px rgba(231, 76, 60, 0.4) !important;
        transform: translateY(-2px) !important;
    }

    .doc-reject:active:not(:disabled) {
        transform: translateY(0) !important;
        box-shadow: 0 2px 8px rgba(231, 76, 60, 0.2) !important;
    }

    .doc-reject:disabled {
        background: linear-gradient(135deg, #d3d3d3 0%, #c0c0c0 100%) !important;
        color: #999 !important;
        cursor: not-allowed !important;
        box-shadow: none !important;
    }



    .loan-details {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    /* Confirmation Modal Styling */
    .confirmation-modal {
        display: flex;
        position: fixed;
        z-index: 10100;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0);
        backdrop-filter: blur(0px);
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
    }

    .confirmation-modal.show {
        background-color: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(5px);
        opacity: 1;
        visibility: visible;
        pointer-events: auto;
    }

    .confirmation-modal-content {
        background: white;
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
        width: 90%;
        max-width: 480px;
        overflow: hidden;
        transform: scale(0.9) translateY(20px);
        opacity: 0;
    }

    .confirmation-modal.show .confirmation-modal-content {
        transform: scale(1) translateY(0);
        opacity: 1;
        transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    .confirmation-modal-header {
        background: linear-gradient(135deg, #27ae60 0%, #1b5e20 100%);
        color: white;
        padding: 28px;
        display: flex;
        align-items: center;
        gap: 18px;
        border-bottom: none;
    }

    .confirmation-modal-header i {
        font-size: 40px;
        animation: bounceIn 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    .confirmation-modal-header i.reject {
        color: #ff6b6b;
    }

    .confirmation-modal-header i.approve {
        color: #51cf66;
    }

    .confirmation-modal-header h3 {
        margin: 0;
        font-size: 22px;
        font-weight: 700;
        letter-spacing: 0.3px;
    }

    .confirmation-modal-body {
        padding: 28px;
        color: #333;
        font-size: 15px;
        line-height: 1.7;
        max-height: 60vh;
        overflow-y: auto;
        background: #fafafa;
    }

    .confirmation-modal-body p {
        margin: 0 0 16px 0;
        font-weight: 500;
        color: #555;
    }

    /* Rejection Reason Container Styling */
    #rejectionReasonContainer {
        margin-top: 20px !important;
        padding: 20px;
        border-radius: 12px;
        background: white;
        border: 2px dashed #e0e0e0;
    }

    #rejectionReasonContainer .form-group {
        margin-bottom: 18px;
    }

    #rejectionReasonContainer .form-label {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
        font-size: 14px;
        font-weight: 700;
        color: #2c3e50;
        gap: 10px;
    }

    #rejectionReasonContainer .form-label span:first-child {
        flex: 1;
    }

    #rejectionReasonContainer .badge {
        background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        color: white;
        font-size: 11px;
        font-weight: 700;
        padding: 4px 10px;
        border-radius: 20px;
        white-space: nowrap;
        box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3);
    }

    #rejectionReasonContainer .form-control {
        width: 100%;
        padding: 12px 14px;
        border: 2px solid #d0d7e0;
        border-radius: 8px;
        font-size: 14px;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        transition: all 0.3s ease;
        background-color: #f8f9fa;
        color: #2c3e50;
    }

    #rejectionReasonContainer .form-control::placeholder {
        color: #95a5a6;
    }

    #rejectionReasonContainer .form-control:hover {
        border-color: #bdc3c7;
        background-color: #fff;
    }

    #rejectionReasonContainer .form-control:focus {
        outline: none;
        border-color: #e74c3c;
        background-color: #fff;
        box-shadow: 0 0 0 4px rgba(231, 76, 60, 0.15);
    }

    #rejectionReasonContainer select.form-control {
        cursor: pointer;
        appearance: none;
        background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23e74c3c' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 12px center;
        background-size: 20px;
        padding-right: 36px;
    }

    #rejectionReasonContainer textarea.form-control {
        resize: vertical;
        min-height: 100px;
        max-height: 300px;
        font-size: 14px;
        line-height: 1.5;
    }

    #rejectionReasonContainer .form-text {
        display: block;
        margin-top: 6px;
        font-size: 12px;
        color: #7f8c8d;
        line-height: 1.5;
    }

    .confirmation-modal-footer {
        padding: 20px 28px;
        display: flex;
        gap: 12px;
        justify-content: flex-end;
        background-color: #ffffff;
        border-top: 1px solid #e0e0e0;
    }

    .confirmation-btn {
        padding: 11px 28px;
        border: none;
        border-radius: 8px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .confirmation-btn-cancel {
        background-color: #e0e0e0;
        color: #333;
    }

    .confirmation-btn-cancel:hover {
        background-color: #d0d0d0;
        transform: translateY(-3px);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
    }

    .confirmation-btn-cancel:active {
        transform: translateY(-1px);
    }

    .confirmation-btn-confirm {
        background: linear-gradient(135deg, #27ae60 0%, #1b5e20 100%);
        color: white;
    }

    .confirmation-btn-confirm:hover {
        background: linear-gradient(135deg, #229954 0%, #145a2e 100%);
        transform: translateY(-3px);
        box-shadow: 0 6px 16px rgba(39, 174, 96, 0.4);
    }

    .confirmation-btn-confirm:active {
        transform: translateY(-1px);
    }

    .confirmation-btn-confirm.reject {
        background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
    }

    .confirmation-btn-confirm.reject:hover {
        background: linear-gradient(135deg, #d43d2d 0%, #a93226 100%);
        box-shadow: 0 6px 16px rgba(231, 76, 60, 0.4);
    }

    @keyframes bounceIn {
        0% {
            transform: scale(0);
            opacity: 0;
        }

        50% {
            transform: scale(1.1);
        }

        100% {
            transform: scale(1);
            opacity: 1;
        }
    }

    @keyframes slideUp {
        from {
            transform: translateY(30px);
            opacity: 0;
        }

        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    @keyframes bounce {

        0%,
        100% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.1);
        }
    }

    /* Success Modal Styling */
    .success-modal-content {
        animation: slideUp 0.4s ease;
    }

    .success-header {
        background: linear-gradient(135deg, #2d7d32 0%, #1b5e20 100%) !important;
    }

    .success-header i {
        color: #51cf66 !important;
        font-size: 36px;
        animation: bounce 0.6s ease;
    }

    .success-body {
        background-color: #f0f8f5;
        padding: 32px 24px;
    }

    .success-body p {
        margin: 0 !important;
        color: #1b5e20;
        font-size: 16px;
        font-weight: 500;
        line-height: 1.6;
    }

    /* ============================================
       SEARCH STYLING
       ============================================ */

    /* Enhanced search styling */
    .search-wrapper {
        position: relative;
        display: flex;
        align-items: center;
    }

    .search-wrapper input {
        width: 100%;
        padding: 10px 32px 10px 12px;
        border: 2px solid #ddd;
        border-radius: 4px;
        font-size: 0.95rem;
        transition: border-color 0.3s ease;
    }

    .search-wrapper input:focus {
        outline: none;
        border-color: #2d7d32;
        box-shadow: 0 0 4px rgba(45, 125, 50, 0.2);
    }

    .clear-search {
        position: absolute;
        right: 10px;
        cursor: pointer;
        color: #999;
        transition: color 0.3s ease;
    }

    .clear-search:hover {
        color: #dc3545;
    }

    /* Filter container responsive */
    .filter-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 12px;
        margin-bottom: 16px;
        padding: 12px;
        background-color: #f9f9f9;
        border-radius: 6px;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .filter-group label {
        font-weight: 500;
        color: #333;
        font-size: 0.9rem;
    }

    .filter-group select,
    .filter-group input {
        padding: 8px 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 0.9rem;
        background-color: white;
    }

    .filter-group select:focus,
    .filter-group input:focus {
        outline: none;
        border-color: #2d7d32;
        box-shadow: 0 0 4px rgba(45, 125, 50, 0.2);
    }

    .filter-actions {
        display: flex;
        gap: 8px;
    }

    .clear-filters-btn {
        padding: 8px 16px;
        background-color: #e74c3c;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .clear-filters-btn:hover {
        background-color: #c0392b;
        box-shadow: 0 2px 6px rgba(230, 76, 60, 0.3);
    }

    /* Table responsive improvements */
    .loan-table {
        width: 100%;
        border-collapse: collapse;
        background-color: white;
        border-radius: 6px;
        overflow: hidden;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .loan-table thead {
        background-color: #f5f5f5;
        border-bottom: 2px solid #ddd;
    }

    .loan-table th {
        padding: 12px;
        text-align: left;
        font-weight: 600;
        color: white;
        font-size: 0.8rem;
        cursor: pointer;
        white-space: nowrap;
    }

    .loan-table tbody tr {
        border-bottom: 1px solid #eee;
        transition: background-color 0.2s ease;
    }

    .loan-table tbody tr:hover {
        background-color: #f9f9f9;
    }

    .loan-table td {
        padding: 12px;
        font-size: 0.9rem;
    }

    /* Mobile responsiveness */
    @media (max-width: 1024px) {
        .filter-container {
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        }

        .loan-table {
            font-size: 0.85rem;
        }

        .loan-table th,
        .loan-table td {
            padding: 8px;
        }
    }

    @media (max-width: 768px) {
        .filter-container {
            grid-template-columns: 1fr;
        }
    }
</style>

<body>
    <!-- CSRF Token for security -->
    <meta name="csrf-token" content="<?php echo htmlspecialchars(getCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" id="csrfToken" value="<?php echo htmlspecialchars(getCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">

    <!-- Real-Time SSE Notification Container -->
    <div id="notificationContainer" class="notification-container"></div>

    <div class="nav-container">
        <button class="burger" onclick="toggleSidebar()">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <nav>
            <img src="IMAGE/Main-Logo.png" alt="Loan System Logo" class="sidebar-logo">
            <a href="admin2_dashboard.php"
                class="<?php echo $current_page === 'admin2_dashboard.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-table-columns"></i> DASHBOARD
            </a>
            <a href="applicant.php" class="<?php echo $current_page === 'applicant.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-users"></i>APPLICANTS
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
                <i class="fa-solid fa-scroll"></i>REPORTS RECORDS
            </a>
            <a href="history_activity.php"
                class="<?php echo $current_page === 'history_activity.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-clipboard"></i>AUDIT TRAILS
            </a>
            <a href="#" id="viewInterestRatesBtn" class="view-interest-rate-link">
                <i class="fa-solid fa-percent"></i> VIEW INTEREST RATES
            </a>
        </nav>
    </div>

    <div class="header">
        <div class="profileXdate">
            <div id="datetime" class="datetime"></div>
            <div class="notification-wrapper">
                <a href="notifications_enhanced.php" class="notification-bell" title="View Notifications">
                    <i class="fa-solid fa-bell"></i>
                    <span class="notification-badge" id="notificationBadge" style="display: none;">0</span>
                </a>
                <div class="notification-dropdown" id="notificationDropdown" style="display: none;">
                    <div class="notification-dropdown-header">
                        <h3>Recent Notifications</h3>
                        <a href="notifications_enhanced.php" class="view-all-link">View All</a>
                    </div>
                    <div class="notification-list" id="notificationList">
                        <div class="notification-loading">
                            <i class="fas fa-spinner fa-spin"></i> Loading...
                        </div>
                    </div>
                </div>
            </div>
            <div class="profile-container">
                <div onclick="toggleDropdown()">
                    <img src="uploads/<?= htmlspecialchars($profile_img) ?>" alt="Profile" class="profile">
                </div>
                <div class="dropdown-menu" id="dropdown">
                    <ul>
                        <li>
                            <a href="profileAdmin2.php">
                                <img src="uploads/<?= htmlspecialchars($profile_img) ?>" alt="Profile"
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

    <div class="main-content">
        <style>
            /* ===== NOTIFICATION SYSTEM ===== */
            .notification-wrapper {
                position: relative;
                display: inline-block;
            }

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
                position: relative;
            }

            .notification-bell:hover {
                background: rgba(27, 94, 32, 0.1);
                color: #2e7d32;
                transform: scale(1.1);
            }

            .notification-badge {
                position: absolute;
                top: -8px;
                right: -8px;
                background-color: #d32f2f;
                color: white;
                border-radius: 50%;
                width: 24px;
                height: 24px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 12px;
                font-weight: bold;
                border: 2px solid white;
                animation: badgePulse 2s ease-in-out infinite;
            }

            .notification-dropdown {
                position: absolute;
                top: 50px;
                right: 0;
                background: white;
                border: 1px solid #ddd;
                border-radius: 8px;
                min-width: 350px;
                max-width: 400px;
                max-height: 400px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                z-index: 1000;
                overflow-y: auto;
            }

            .notification-dropdown-header {
                padding: 15px;
                border-bottom: 1px solid #e0e0e0;
                display: flex;
                justify-content: space-between;
                align-items: center;
                background: #f5f5f5;
                border-radius: 8px 8px 0 0;
            }

            .notification-dropdown-header h3 {
                margin: 0;
                font-size: 14px;
                font-weight: 600;
                color: #1b5e20;
            }

            .view-all-link {
                color: #1b5e20;
                text-decoration: none;
                font-size: 12px;
                font-weight: 500;
            }

            .view-all-link:hover {
                text-decoration: underline;
            }

            .notification-list {
                max-height: 300px;
                overflow-y: auto;
            }

            .notification-item {
                padding: 12px 15px;
                border-bottom: 1px solid #e0e0e0;
                cursor: pointer;
                transition: background-color 0.2s ease;
            }

            .notification-item:hover {
                background-color: #f9f9f9;
            }

            .notification-item.high {
                background-color: #fff3cd;
            }

            .notification-item-title {
                font-weight: 600;
                color: #1b5e20;
                font-size: 13px;
                margin-bottom: 4px;
            }

            .notification-item-message {
                color: #666;
                font-size: 12px;
                line-height: 1.4;
                margin-bottom: 4px;
            }

            .notification-item-time {
                color: #999;
                font-size: 11px;
            }

            .notification-loading {
                padding: 20px;
                text-align: center;
                color: #999;
            }

            .notification-loading i {
                margin-right: 8px;
            }

            .notification-empty {
                padding: 20px;
                text-align: center;
                color: #999;
                font-size: 13px;
            }
        </style>
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
                    <h1>Admin 2 Dashboard</h1>
                    <p class="dashboard-subtitle">Manage loan applications, pre-approval, and system operations.</p>
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

                                <!-- Debug Information -->
                                <div class="debug-info"
                                    style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #17a2b8; font-size: 12px; color: #6c757d;">
                                    <h4 style="margin-top: 0; color: #17a2b8;">🔍 Debug Information</h4>
                                    <p><strong>Query executed:</strong> Looking for payment schedules due within 30 days</p>
                                    <p><strong>Criteria:</strong> Status = pending/partial/unpaid, Loan status =
                                        active/Active, Application status = Active</p>
                                    <p><strong>Date range:</strong> <?php echo date('Y-m-d'); ?> to
                                        <?php echo date('Y-m-d', strtotime('+30 days')); ?>
                                    </p>
                                    <p><strong>Result:</strong>
                                        <?php echo is_array($dueAccountsData) ? count($dueAccountsData) : 'Error - not an array'; ?>
                                        records found</p>
                                    <div style="margin-top: 10px;">
                                        <button onclick="refreshDueAccountsTable()"
                                            style="background: #17a2b8; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;">
                                            🔄 Refresh Due Accounts
                                        </button>
                                        <a href="debug_due_accounts.php" target="_blank"
                                            style="margin-left: 10px; color: #17a2b8; text-decoration: none;">
                                            🔧 Run Detailed Debug
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="scrollable-table">
                                <table class="due-accounts-table">
                                    <thead>
                                        <tr>
                                            <th scope="col" data-sort="due_date" onclick="sortDueAccountsTable('due_date')">
                                                Due Date <span class="sort-icon"></span>
                                            </th>
                                            <th scope="col" data-sort="account_holder"
                                                onclick="sortDueAccountsTable('account_holder')">
                                                Account Holder <span class="sort-icon"></span>
                                            </th>
                                            <th scope="col" data-sort="amount" onclick="sortDueAccountsTable('amount')">
                                                Amount <span class="sort-icon"></span>
                                            </th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($dueAccountsData as $due): ?>
                                            <tr>
                                                <td data-label="Due Date">
                                                    <span class="date-badge">
                                                        <?php
                                                        $dueDate = strtotime($due['due_date']);
                                                        $daysUntilDue = floor(($dueDate - time()) / (60 * 60 * 24));
                                                        $urgencyClass = $daysUntilDue <= 7 ? 'urgent' : ($daysUntilDue <= 15 ? 'warning' : 'normal');
                                                        echo date('M d, Y', $dueDate);
                                                        ?>
                                                    </span>
                                                    <?php if ($daysUntilDue <= 7): ?>
                                                        <span class="urgency-badge urgent">
                                                            <i class="fas fa-exclamation-circle"></i>
                                                            <?php echo $daysUntilDue <= 0 ? 'Overdue' : 'Urgent'; ?>
                                                        </span>
                                                    <?php elseif ($daysUntilDue <= 15): ?>
                                                        <span class="urgency-badge warning">
                                                            <i class="fas fa-clock"></i> Due Soon
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td data-label="Account Holder">
                                                    <div class="account-holder-info">
                                                        <strong><?php echo htmlspecialchars($due['first_name'] . ' ' . $due['last_name']); ?></strong>
                                                        <small class="account-id">ID:
                                                            <?php echo htmlspecialchars($due['application_id']); ?></small>
                                                    </div>
                                                </td>
                                                <td data-label="Amount">
                                                    <span
                                                        class="amount-badge">₱<?php echo number_format($due['amount'], 2); ?></span>
                                                </td>
                                                <td data-label="Actions">
                                                    <div class="action-buttons">
                                                        <button class="action-btn view-btn"
                                                            onclick="openLoanDetailsModal('<?php echo htmlspecialchars($due['application_id']); ?>')"
                                                            title="View Loan Details">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button class="action-btn reminder-btn"
                                                            onclick="sendPaymentReminder(<?php echo htmlspecialchars($due['payment_id']); ?>)"
                                                            title="Send Payment Reminder">
                                                            <i class="fas fa-envelope"></i>
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
                    <label for="filter-pre-approval">Pre-Approval:</label>
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
                                <tr data-app-id="<?php echo htmlspecialchars($applicant['application_id']); ?>">
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
                                        <?php echo date('M j, Y', strtotime($applicant['created_at'])); ?>
                                    </td>
                                    <td data-label="Action">
                                        <a href="#" class="view-btn"
                                            onclick="openLoanDetailsModal('<?php echo htmlspecialchars($applicant['application_id']); ?>'); return false;">
                                            <i class="fas fa-eye"></i> View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div id="loanDetailsModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 id="loanDetailsModalLabel">Loan Application Details</h2>
                        <span class="close" onclick="closeLoanDetailsModal()" role="button"
                            aria-label="Close modal">×</span>
                    </div>
                    <div class="modal-body">
                        <div id="loanDetailsContent" class="loan-details">
                            <p>Loading...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Details Modal -->
            <div id="paymentDetailsModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 id="paymentDetailsModalLabel">Payment Details</h2>
                        <span class="close" onclick="closePaymentDetailsModal()" role="button"
                            aria-label="Close modal">×</span>
                    </div>
                    <div class="modal-body">
                        <div id="paymentDetailsContent" class="payment-details">
                            <p>Loading...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Reminder Confirmation Modal -->
            <div id="paymentReminderModal" class="confirmation-modal">
                <div class="confirmation-modal-content">
                    <div class="confirmation-modal-header">
                        <i class="fas fa-envelope" id="reminderIcon" style="color: #2d7d32;"></i>
                        <h3 id="reminderTitle">Send Payment Reminder</h3>
                    </div>
                    <div class="confirmation-modal-body">
                        <p id="reminderMessage">Are you sure you want to send a payment reminder email?</p>
                        <div id="reminderDetails"
                            style="margin-top: 12px; padding: 12px; background-color: #e8f5e9; border-radius: 6px; font-size: 14px; color: #2d7d32; border-left: 4px solid #2d7d32;">
                            <p style="margin: 4px 0;"><strong>Account Holder:</strong> <span
                                    id="reminderAccountHolder">N/A</span></p>
                            <p style="margin: 4px 0;"><strong>Loan ID:</strong> <span id="reminderPaymentId">N/A</span>
                            </p>
                            <p style="margin: 4px 0;"><strong>Status:</strong> Email will be sent to the registered
                                email address</p>
                        </div>
                    </div>
                    <div class="confirmation-modal-footer">
                        <button class="confirmation-btn confirmation-btn-cancel"
                            onclick="closePaymentReminderModal()">Cancel</button>
                        <button class="confirmation-btn confirmation-btn-confirm" id="reminderConfirmBtn"
                            style="background-color: #2d7d32;">Send Email</button>
                    </div>
                </div>
            </div>

            <!-- Confirmation Modal for Document Actions -->
            <div id="confirmationModal" class="confirmation-modal">
                <div class="confirmation-modal-content">
                    <div class="confirmation-modal-header">
                        <i class="fas fa-question-circle" id="confirmationIcon"></i>
                        <h3 id="confirmationTitle">Confirm Action</h3>
                    </div>
                    <div class="confirmation-modal-body">
                        <p id="confirmationMessage">Are you sure you want to perform this action?</p>
                        <!-- Rejection Reason Fields (Hidden by default, shown for rejections) -->
                        <div id="rejectionReasonContainer" style="display: none; margin-top: 20px;">
                            <div class="form-group">
                                <label for="rejectionTemplateSelect" class="form-label">
                                    <span>📋 Select Rejection Reason Template</span>
                                </label>
                                <select id="rejectionTemplateSelect" class="form-control" onchange="onTemplateSelect()">
                                    <option value="">-- Choose a template or enter custom reason --</option>
                                </select>
                                <small class="form-text">
                                    💡 Tip: Select a template to automatically populate the reason field below
                                </small>
                            </div>
                            <div class="form-group">
                                <label for="rejectionReason" class="form-label">
                                    <span>✉️ Rejection Reason</span>
                                    <span class="badge">
                                        <span id="rejectionReasonCharCount">0</span>/1000
                                    </span>
                                </label>
                                <textarea id="rejectionReason" class="form-control"
                                    placeholder="Enter or select a rejection reason for the applicant..."
                                    maxlength="1000" rows="4" oninput="updateRejectionReasonCharCount()"></textarea>
                                <small class="form-text">
                                    📧 This reason will be sent to the applicant in their notification email
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="confirmation-modal-footer">
                        <button class="confirmation-btn confirmation-btn-cancel"
                            onclick="closeConfirmationModal()">Cancel</button>
                        <button class="confirmation-btn confirmation-btn-confirm" id="confirmationConfirmBtn"
                            onclick="confirmDocumentAction()">Confirm</button>
                    </div>
                </div>
            </div>

            <!-- Email Success Modal -->
            <div id="emailSuccessModal" class="confirmation-modal">
                <div class="confirmation-modal-content success-modal-content">
                    <div class="confirmation-modal-header success-header">
                        <i class="fas fa-check-circle"></i>
                        <h3>Email Sent Successfully</h3>
                    </div>
                    <div class="confirmation-modal-body success-body">
                        <p id="emailSuccessMessage"
                            style="text-align: center; font-size: 16px; color: #2d7d32; font-weight: 500;">
                            ✅ Email sent successfully! All queued changes have been sent to the applicant.
                        </p>
                    </div>
                    <div class="confirmation-modal-footer">
                        <button class="confirmation-btn confirmation-btn-confirm" onclick="closeEmailSuccessModal()"
                            style="width: 100%; justify-content: center;"
                            aria-label="Close success message">Close</button>
                    </div>
                </div>
            </div>

            <!-- Pre-Approval Confirmation Modal -->
            <div id="preApprovalConfirmModal" class="confirmation-modal">
                <div class="confirmation-modal-content">
                    <div class="confirmation-modal-header" id="preApprovalHeader">
                        <i class="fas fa-question-circle" id="preApprovalIcon"></i>
                        <h3 id="preApprovalTitle">Confirm Action</h3>
                    </div>
                    <div class="confirmation-modal-body">
                        <p id="preApprovalMessage"
                            style="margin: 0 0 16px 0; font-size: 15px; color: #333; line-height: 1.6;">Are you sure you
                            want to perform this action?</p>
                        <div id="preApprovalDetails"
                            style="margin-top: 12px; padding: 16px; background-color: #f0f4f8; border-radius: 8px; font-size: 14px; color: #555; line-height: 1.6; border-left: 4px solid #2d7d32;">
                        </div>
                    </div>
                    <div class="confirmation-modal-footer">
                        <button class="confirmation-btn confirmation-btn-cancel" onclick="closePreApprovalModal()"
                            aria-label="Cancel pre-approval action">Cancel</button>
                        <button class="confirmation-btn confirmation-btn-confirm" id="preApprovalConfirmBtn"
                            onclick="confirmPreApprovalAction()"
                            aria-label="Confirm pre-approval action">Confirm</button>
                    </div>
                </div>
            </div>

        </div>

        <div class="activity_logs">
            <div class="section-header">
                <div>
                    <h2>Audit trails Logs</h2>
                    <p class="section-subtitle">Track system activities and changes</p>
                </div>

            </div>
            <?php if (empty($activityLogs)): ?>
                <p class="no-activity-logs">
                    <i class="fa-solid fa-circle-exclamation"></i> No activity logs found.
                </p>
            <?php else: ?>
                <div class="scrollable-table">
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
                        <tbody id="activityLogsTableBody">
                            <?php foreach ($activityLogs as $log): ?>
                                <tr>
                                    <td data-label="Date & Time">
                                        <?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?>
                                    </td>
                                    <td data-label="User">
                                        <?php echo htmlspecialchars(($log['first_name'] && $log['last_name']) ? $log['first_name'] . ' ' . $log['last_name'] : 'Unknown'); ?>
                                    </td>
                                    <td data-label="Role" class="status-<?php echo strtolower($log['user_role']); ?>">
                                        <span
                                            class="status-badge badge-<?php echo strtolower($log['user_role']); ?>"><?php echo htmlspecialchars($log['user_role']); ?></span>
                                    </td>
                                    <td data-label="Action" class="status-<?php echo strtolower($log['action_type']); ?>">
                                        <span
                                            class="status-badge badge-<?php echo strtolower($log['action_type']); ?>"><?php echo htmlspecialchars($log['action_type']); ?></span>
                                    </td>
                                    <td data-label="Module">
                                        <?php echo htmlspecialchars($log['module_display'] ?? $log['module']); ?>
                                    </td>
                                    <td data-label="Description"><?php echo htmlspecialchars($log['description']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <script>

        // ============================================
        // CSRF TOKEN MANAGEMENT HELPERS
        // ============================================
        function getCSRFTokenFromDOM() {
            const csrfTokenElement = document.getElementById('csrfToken');
            if (csrfTokenElement && csrfTokenElement.value) {
                return csrfTokenElement.value;
            }

            // Fallback: try to get from meta tag if hidden input is blocked
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            if (csrfMeta && csrfMeta.getAttribute('content')) {
                return csrfMeta.getAttribute('content');
            }

            console.error('❌ CSRF token not found in DOM - this will cause request failures');
            return null;
        }

        function addCSRFTokenToFormData(formData) {
            const token = getCSRFTokenFromDOM();
            if (!token) {
                showNotification('Security error: CSRF token not found. Please refresh the page.', 'error');
                return false;
            }
            formData.set('csrf_token', token); // Use .set() to replace any existing csrf_token field
            return true;
        }

        // ===== Helper Functions =====
        function showLoadingOverlay(message = "Loading...") {
            let overlay = document.getElementById("loadingOverlay");
            if (!overlay) {
                overlay = document.createElement("div");
                overlay.id = "loadingOverlay";
                overlay.style.cssText = `
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.6);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 10200;
                    backdrop-filter: blur(4px);
                `;
                document.body.appendChild(overlay);
            }

            overlay.innerHTML = `
                <div style="
                    background: white;
                    padding: 40px;
                    border-radius: 12px;
                    text-align: center;
                    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
                    animation: scaleIn 0.3s ease-out;
                ">
                    <div style="
                        width: 50px;
                        height: 50px;
                        border: 4px solid #f0f0f0;
                        border-top: 4px solid #27ae60;
                        border-radius: 50%;
                        animation: spin 1s linear infinite;
                        margin: 0 auto 15px;
                    "></div>
                    <p style="margin: 0; color: #333; font-weight: 600; font-size: 16px;">${message}</p>
                </div>
            `;

            overlay.style.display = "flex";
            overlay.style.visibility = "visible";
            overlay.style.opacity = "1";
            overlay.style.pointerEvents = "auto";
        }

        function hideLoadingOverlay() {
            const overlay = document.getElementById("loadingOverlay");
            if (overlay) {
                overlay.style.display = "none";
                overlay.style.visibility = "hidden";
                overlay.style.opacity = "0";
                overlay.style.pointerEvents = "none";
            }
        }

        // Add animation styles for loading overlay if not already present
        if (!document.getElementById('loadingOverlayStyles')) {
            const animStyle = document.createElement('style');
            animStyle.id = 'loadingOverlayStyles';
            animStyle.textContent = `
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
            `;
            document.head.appendChild(animStyle);
        }

        function showNotification(message, type = "info") {
            const notif = document.createElement("div");
            const colors = {
                success: "#27ae60",
                error: "#e74c3c",
                warning: "#f39c12",
                info: "#3498db"
            };
            const icons = {
                success: "check-circle",
                error: "exclamation-circle",
                warning: "exclamation-triangle",
                info: "info-circle"
            };

            notif.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${colors[type] || colors.info};
                color: white;
                padding: 16px 24px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                z-index: 10003;
                animation: slideIn 0.3s ease;
                display: flex;
                align-items: center;
                gap: 12px;
                font-weight: 600;
                font-family: 'Poppins', sans-serif;
                max-width: 400px;
            `;
            notif.innerHTML = `
                <i class="fas fa-${icons[type] || icons.info}" style="font-size: 18px;"></i>
                <span>${message}</span>
            `;
            document.body.appendChild(notif);

            setTimeout(() => {
                notif.style.animation = "slideOut 0.3s ease";
                setTimeout(() => notif.remove(), 300);
            }, 4000);
        }

        // Add animation styles
        const style = document.createElement("style");
        style.textContent = `
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            @keyframes slideIn {
                from { transform: translateX(400px); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(400px); opacity: 0; }
            }
            @keyframes toastSlideIn {
                from { transform: translateY(-100px); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }
            @keyframes toastSlideOut {
                from { transform: translateY(0); opacity: 1; }
                to { transform: translateY(-100px); opacity: 0; }
            }

            /* Real-time Toast Notification Styles */
            .real-time-toast {
                position: fixed;
                top: 20px;
                right: 20px;
                background: linear-gradient(135deg, #1b5e20 0%, #2d7d32 100%);
                color: white;
                padding: 16px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
                z-index: 10000;
                animation: toastSlideIn 0.4s ease-out;
                max-width: 400px;
                font-size: 14px;
            }

            .toast-content {
                display: flex;
                align-items: flex-start;
                gap: 12px;
            }

            .toast-content i {
                font-size: 18px;
                animation: spin 2s linear infinite;
                flex-shrink: 0;
                margin-top: 2px;
            }

            .toast-text {
                flex: 1;
            }

            .toast-text strong {
                display: block;
                margin-bottom: 4px;
            }

            .toast-text p {
                margin: 0;
                font-size: 12px;
                opacity: 0.9;
            }

            .toast-close {
                background: none;
                border: none;
                color: white;
                font-size: 20px;
                cursor: pointer;
                padding: 0;
                line-height: 1;
                opacity: 0.8;
                transition: opacity 0.2s;
                flex-shrink: 0;
            }

            .toast-close:hover {
                opacity: 1;
            }

            @media (max-width: 768px) {
                .real-time-toast {
                    right: 10px;
                    left: 10px;
                    max-width: none;
                }
            }

            /* ====================================
               SSE Real-Time Notification Styles
               ==================================== */
            .notification-container {
                position: fixed;
                top: 80px;
                right: 20px;
                z-index: 9999;
                max-width: 450px;
                display: flex;
                flex-direction: column;
                gap: 12px;
                pointer-events: none;
            }

            .sse-notification {
                background: white;
                border-radius: 8px;
                box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
                overflow: hidden;
                animation: notificationSlideIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
                pointer-events: auto;
                transition: all 0.3s ease;
                max-height: 300px;
                display: flex;
                flex-direction: column;
            }

            .sse-notification:hover {
                box-shadow: 0 6px 24px rgba(0, 0, 0, 0.2);
                transform: translateY(-2px);
            }

            .sse-notification.critical {
                border-top: 3px solid #dc3545;
                background: linear-gradient(135deg, rgba(220, 53, 69, 0.05) 0%, rgba(220, 53, 69, 0.02) 100%);
            }

            .sse-notification.high {
                border-top: 3px solid #fd7e14;
                background: linear-gradient(135deg, rgba(253, 126, 20, 0.05) 0%, rgba(253, 126, 20, 0.02) 100%);
            }

            .sse-notification.normal {
                border-top: 3px solid #0dcaf0;
                background: linear-gradient(135deg, rgba(13, 202, 240, 0.05) 0%, rgba(13, 202, 240, 0.02) 100%);
            }

            .sse-notification.low {
                border-top: 3px solid #6c757d;
                background: linear-gradient(135deg, rgba(108, 117, 125, 0.05) 0%, rgba(108, 117, 125, 0.02) 100%);
            }

            .notification-content {
                padding: 16px;
                display: flex;
                flex-direction: column;
                gap: 10px;
            }

            .notification-header {
                display: flex;
                align-items: center;
                gap: 10px;
                margin-bottom: 4px;
            }

            .notification-header i {
                font-size: 18px;
                flex-shrink: 0;
            }

            .sse-notification.critical .notification-header i {
                color: #dc3545;
                animation: pulse 2s infinite;
            }

            .sse-notification.high .notification-header i {
                color: #fd7e14;
            }

            .sse-notification.normal .notification-header i {
                color: #0dcaf0;
            }

            .sse-notification.low .notification-header i {
                color: #6c757d;
            }

            .notification-title {
                font-weight: 600;
                font-size: 14px;
                color: #212529;
                flex: 1;
                word-break: break-word;
            }

            .notification-time {
                font-size: 11px;
                color: #999;
                white-space: nowrap;
            }

            .notification-message {
                font-size: 13px;
                color: #555;
                line-height: 1.4;
                word-break: break-word;
                padding: 8px 0;
                border-top: 1px solid rgba(0, 0, 0, 0.05);
                border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            }

            .notification-meta {
                display: flex;
                gap: 12px;
                font-size: 11px;
                color: #999;
                flex-wrap: wrap;
            }

            .notification-sender,
            .notification-module {
                padding: 2px 6px;
                background: rgba(0, 0, 0, 0.03);
                border-radius: 3px;
            }

            .notification-actions {
                display: flex;
                gap: 8px;
                margin-top: 8px;
                align-items: center;
                justify-content: space-between;
            }

            .notification-link {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                padding: 6px 12px;
                background: linear-gradient(135deg, #1b5e20 0%, #2d7d32 100%);
                color: white;
                text-decoration: none;
                border-radius: 4px;
                font-size: 12px;
                font-weight: 500;
                transition: all 0.2s;
                border: none;
                cursor: pointer;
            }

            .notification-link:hover {
                background: linear-gradient(135deg, #2d7d32 0%, #388e3c 100%);
                transform: translateX(2px);
            }

            .notification-close {
                padding: 6px 12px;
                background: rgba(0, 0, 0, 0.05);
                color: #666;
                border: none;
                border-radius: 4px;
                font-size: 12px;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.2s;
            }

            .notification-close:hover {
                background: rgba(0, 0, 0, 0.1);
                color: #333;
            }

            .notification-badge {
                display: inline-block;
                background: #dc3545;
                color: white;
                border-radius: 50%;
                min-width: 20px;
                height: 20px;
                line-height: 20px;
                text-align: center;
                font-size: 11px;
                font-weight: bold;
                position: relative;
                top: -2px;
                animation: badgePulse 2s infinite;
            }

            @keyframes notificationSlideIn {
                from {
                    opacity: 0;
                    transform: translateX(420px) rotateZ(5deg);
                }
                to {
                    opacity: 1;
                    transform: translateX(0) rotateZ(0);
                }
            }

            @keyframes pulse {
                0%, 100% {
                    opacity: 1;
                }
                50% {
                    opacity: 0.7;
                }
            }

            @keyframes badgePulse {
                0%, 100% {
                    transform: scale(1);
                    box-shadow: 0 0 0 3px rgba(211, 47, 47, 0.2), 0 2px 8px rgba(0, 0, 0, 0.3);
                }
                50% {
                    transform: scale(1.15);
                    box-shadow: 0 0 0 6px rgba(211, 47, 47, 0.15), 0 4px 12px rgba(0, 0, 0, 0.4);
                }
            }

            @keyframes slideInDown {
                from {
                    opacity: 0;
                    transform: translateY(-20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            /* Enhanced Email Queue Management Panel Animations */
            @keyframes slideInFromTop {
                from {
                    opacity: 0;
                    transform: translateY(-20px) scale(0.95);
                }
                to {
                    opacity: 1;
                    transform: translateY(0) scale(1);
                }
            }

            @keyframes slideDown {
                from {
                    opacity: 0;
                    transform: translateY(-10px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            @keyframes pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.05); }
                100% { transform: scale(1); }
            }

            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(10px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            @keyframes shake {
                0%, 100% { transform: translateX(0); }
                10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
                20%, 40%, 60%, 80% { transform: translateX(5px); }
            }

            @media (max-width: 768px) {
                .notification-container {
                    top: 60px;
                    right: 10px;
                    left: 10px;
                    max-width: none;
                }

                .sse-notification {
                    max-height: 250px;
                }

                .notification-content {
                    padding: 12px;
                }

                .notification-header {
                    gap: 8px;
                }

                .notification-title {
                    font-size: 13px;
                }

                .notification-time {
                    font-size: 10px;
                }

                .notification-message {
                    font-size: 12px;
                }

                .notification-actions {
                    flex-direction: column;
                    gap: 6px;
                }

                .notification-link,
                .notification-close {
                    width: 100%;
                    text-align: center;
                }
            }
        `;
        document.head.appendChild(style);

        // ===== Confirmation Modal Functions =====
        let pendingDocumentAction = null;

        function showConfirmationModal(applicationId, documentId, status) {
            console.log('📋 Showing confirmation modal for:', { applicationId, documentId, status });

            // First, hide any overlays
            hideLoadingOverlay();

            const modal = document.getElementById('confirmationModal');
            if (!modal) {
                console.error('❌ Confirmation modal not found');
                alert('Modal not found. Please refresh the page.');
                return;
            }

            const title = document.getElementById('confirmationTitle');
            const message = document.getElementById('confirmationMessage');
            const icon = document.getElementById('confirmationIcon');
            const confirmBtn = document.getElementById('confirmationConfirmBtn');
            const rejectionReasonContainer = document.getElementById('rejectionReasonContainer');

            // Configure modal based on action
            if (status === 'Approved') {
                if (title) title.textContent = '✅ Approve Document';
                if (message) message.textContent = 'Are you sure you want to APPROVE this document?';
                if (icon) {
                    icon.className = 'fas fa-check-circle approve';
                    icon.style.color = '#27ae60';
                }
                if (confirmBtn) {
                    confirmBtn.textContent = '✓ Yes, Approve';
                    confirmBtn.style.background = '#27ae60';
                    confirmBtn.style.color = 'white';
                }
                if (rejectionReasonContainer) {
                    rejectionReasonContainer.style.display = 'none';
                }
            } else {
                if (title) title.textContent = '❌ Reject Document';
                if (message) message.textContent = 'Are you sure you want to REJECT this document?';
                if (icon) {
                    icon.className = 'fas fa-times-circle reject';
                    icon.style.color = '#e74c3c';
                }
                if (confirmBtn) {
                    confirmBtn.textContent = '✗ Yes, Reject';
                    confirmBtn.style.background = '#e74c3c';
                    confirmBtn.style.color = 'white';
                }
                if (rejectionReasonContainer) {
                    rejectionReasonContainer.style.display = 'block';
                    loadRejectionTemplates();
                    // Clear previous input
                    const reasonField = document.getElementById('rejectionReason');
                    if (reasonField) reasonField.value = '';
                }
            }

            // Store the action for later
            pendingDocumentAction = { applicationId, documentId, status };

            // Force modal to be visible with multiple approaches
            modal.style.display = 'flex';
            modal.style.visibility = 'visible';
            modal.style.opacity = '1';
            modal.style.zIndex = '10200';
            modal.style.position = 'fixed';
            modal.style.top = '0';
            modal.style.left = '0';
            modal.style.width = '100%';
            modal.style.height = '100%';
            modal.style.background = 'rgba(0, 0, 0, 0.5)';
            modal.style.alignItems = 'center';
            modal.style.justifyContent = 'center';

            // Add show class
            modal.classList.add('show');

            console.log('✅ Confirmation modal should now be visible');
        }

        // Load rejection reason templates
        function loadRejectionTemplates() {
            fetch('admin2_dashboard.php?action=get_rejection_templates')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.templates) {
                        const templateSelect = document.getElementById('rejectionTemplateSelect');
                        if (templateSelect) {
                            templateSelect.innerHTML = '<option value="">-- Select a reason template --</option>';
                            data.templates.forEach(template => {
                                const option = document.createElement('option');
                                option.value = template.reason;
                                option.textContent = template.name;
                                option.dataset.templateId = template.id;
                                templateSelect.appendChild(option);
                            });
                        }
                    }
                })
                .catch(error => console.error('Failed to load rejection templates:', error));
        }

        // Handle template selection
        function onTemplateSelect() {
            const templateSelect = document.getElementById('rejectionTemplateSelect');
            const rejectionReasonField = document.getElementById('rejectionReason');

            if (templateSelect && rejectionReasonField && templateSelect.value) {
                rejectionReasonField.value = templateSelect.value;
                const charCount = rejectionReasonField.value.length;
                document.getElementById('rejectionReasonCharCount').textContent = charCount;
            }
        }

        // Update rejection reason character count display
        function updateRejectionReasonCharCount() {
            const rejectionReasonField = document.getElementById('rejectionReason');
            const charCountDisplay = document.getElementById('rejectionReasonCharCount');

            if (rejectionReasonField && charCountDisplay) {
                const charCount = rejectionReasonField.value.length;
                charCountDisplay.textContent = charCount;
            }
        }

        function closeConfirmationModal() {
            const modal = document.getElementById('confirmationModal');
            modal.classList.remove('show');
            pendingDocumentAction = null;
        }

        function confirmDocumentAction() {
            if (!pendingDocumentAction) {
                showNotification('No pending action. Please try again.', 'warning');
                return;
            }

            const { applicationId, documentId, status } = pendingDocumentAction;

            console.log('✓ Confirming document action:', {
                applicationId,
                documentId,
                status
            });

            // For rejection, validate that reason is provided
            if (status === 'Rejected') {
                const rejectionReasonField = document.getElementById('rejectionReason');
                if (!rejectionReasonField || !rejectionReasonField.value.trim()) {
                    showNotification('⚠️ Please provide a rejection reason', 'warning');
                    if (rejectionReasonField) rejectionReasonField.focus();
                    return;
                }
            }

            // Close modal FIRST
            const modal = document.getElementById('confirmationModal');
            if (modal) {
                modal.style.display = 'none';
                modal.classList.remove('show');
            }

            // Then show loading overlay
            setTimeout(() => {
                showLoadingOverlay(`${status === 'Approved' ? '✓ Approving' : '✕ Rejecting'} document...`);
            }, 100);

            // Safety timeout: force close overlay after 8 seconds max
            const overlayTimeout = setTimeout(() => {
                hideLoadingOverlay();
                console.warn('⚠️ Document action took too long; overlay closed.');
                showNotification('Action took too long. Please try again.', 'error');
            }, 8000);

            // Pause polling during action to avoid contention
            if (typeof PollingManager !== 'undefined') {
                PollingManager.pausePolling();
            }

            const formData = new FormData();
            formData.append('action', 'update_document_status');
            formData.append('application_id', applicationId);
            formData.append('document_id', documentId);
            formData.append('status', status);

            console.log('📤 Sending document action');

            // Add rejection reason if rejecting
            if (status === 'Rejected') {
                const rejectionReasonField = document.getElementById('rejectionReason');
                if (rejectionReasonField) {
                    const reasonValue = rejectionReasonField.value.trim();
                    formData.append('rejection_reason', reasonValue);
                    console.log('📝 Including rejection reason');
                }
            }

            // Add CSRF token
            if (!addCSRFTokenToFormData(formData)) {
                hideLoadingOverlay();
                showNotification('Security validation failed. Please refresh and try again.', 'error');
                return;
            }

            fetch('admin2_dashboard.php', {
                method: 'POST',
                body: formData
            })
                .then(response => {
                    console.log('📥 Server response:', response.status);
                    if (!response.ok) throw new Error(`HTTP ${response.status}`);
                    return response.json();
                })
                .then(data => {
                    clearTimeout(overlayTimeout);
                    console.log('✅ Response:', data);

                    if (data.success) {
                        const actionLabel = status === 'Approved' ? 'approved' : 'rejected';
                        let message = `✅ Document ${actionLabel} successfully!`;

                        if (data.data && data.data.queue_count) {
                            message += ` (${data.data.queue_count} in queue)`;
                        }

                        showNotification(message, 'success');

                        // Update queue display
                        if (data.data && data.data.queue_count !== undefined) {
                            updateQueueDisplay(applicationId, data.data.queue_count);
                        }

                        // Refresh modal after 1.5 seconds
                        setTimeout(() => {
                            hideLoadingOverlay();
                            console.log('🔄 Refreshing modal...');
                            loanDetailsCache.delete(applicationId);
                            openLoanDetailsModal(applicationId);
                        }, 1500);
                    } else {
                        hideLoadingOverlay();
                        showNotification('❌ Error: ' + (data.message || 'Unknown error'), 'error');
                    }

                    // Resume polling
                    if (typeof PollingManager !== 'undefined') {
                        PollingManager.resumePolling();
                    }
                })
                .catch(error => {
                    hideLoadingOverlay();
                    clearTimeout(overlayTimeout);
                    console.error('❌ Error:', error);
                    showNotification('❌ Error: ' + error.message, 'error');

                    if (typeof PollingManager !== 'undefined') {
                        PollingManager.resumePolling();
                    }
                });
        }

        // ===== MANUAL EMAIL QUEUE FUNCTIONS =====
        function sendQueuedEmail(applicationId) {
            console.log('📧 Sending queued email for application:', applicationId);

            const button = document.getElementById(`sendQueuedEmailBtn-${applicationId}`);
            if (button) {
                button.disabled = true;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            }

            showLoadingOverlay('Sending consolidated email...');

            const formData = new FormData();
            formData.append('action', 'send_queued_email');
            formData.append('application_id', applicationId);
            formData.append('csrf_token', getCSRFTokenFromDOM());

            fetch('admin2_dashboard.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    hideLoadingOverlay();

                    if (data.success) {
                        showNotification(data.message, 'success');
                        // Hide the email queue banner after successful send
                        const banner = document.getElementById(`emailQueueBanner-${applicationId}`);
                        if (banner) {
                            banner.style.display = 'none';
                        }
                        // Reset queue count
                        updateQueueDisplay(applicationId, 0);
                    } else {
                        showNotification(data.message || 'Failed to send email', 'error');
                        // Re-enable button on error
                        if (button) {
                            button.disabled = false;
                            button.innerHTML = '<i class="fas fa-paper-plane"></i> Send Email Now';
                        }
                    }
                })
                .catch(error => {
                    console.error('Email send error:', error);
                    hideLoadingOverlay();
                    showNotification('Error sending email. Please try again.', 'error');
                    // Re-enable button on error
                    if (button) {
                        button.disabled = false;
                        button.innerHTML = '<i class="fas fa-paper-plane"></i> Send Email Now';
                    }
                });
        }

        function updateQueueDisplay(applicationId, queueCount) {
            console.log('📊 Updating queue display for app:', applicationId, 'count:', queueCount);

            const countBadge = document.getElementById(`queueCount-${applicationId}`);
            const sendBtn = document.getElementById(`sendQueuedEmailBtn-${applicationId}`);

            if (!countBadge) {
                console.warn('⚠️ Queue count badge not found for app:', applicationId);
                return;
            }

            // Update queue count with enhanced styling
            if (queueCount > 0) {
                countBadge.textContent = `${queueCount} item${queueCount === 1 ? '' : 's'}`;
                countBadge.style.background = '#ff9800';
                countBadge.style.color = 'white';
                countBadge.style.animation = 'pulse 1s ease-in-out';
                countBadge.style.fontWeight = '700';

                // Enable send button with enhanced styling
                if (sendBtn) {
                    sendBtn.disabled = false;
                    sendBtn.style.opacity = '1';
                    sendBtn.style.cursor = 'pointer';
                    sendBtn.style.background = '#2196f3';
                    sendBtn.style.transform = 'scale(1.02)';
                    sendBtn.style.boxShadow = '0 2px 8px rgba(33, 150, 243, 0.4)';
                    sendBtn.title = `Send consolidated email with ${queueCount} document change${queueCount === 1 ? '' : 's'}`;
                }

                // Add subtle notification for first queue item
                if (queueCount === 1) {
                    showNotification(`Document change queued for consolidation (${queueCount} item)`, 'info');
                }

            } else {
                countBadge.textContent = '0 items';
                countBadge.style.background = '#4caf50';
                countBadge.style.color = 'white';
                countBadge.style.animation = 'none';
                countBadge.style.fontWeight = '600';

                // Disable send button but keep it visible
                if (sendBtn) {
                    sendBtn.disabled = true;
                    sendBtn.style.opacity = '0.5';
                    sendBtn.style.cursor = 'not-allowed';
                    sendBtn.style.background = '#ccc';
                    sendBtn.style.transform = 'scale(1)';
                    sendBtn.style.boxShadow = 'none';
                    sendBtn.title = 'No document changes to send';
                }
            }
        }

        function initializeQueueDisplay(applicationId) {
            console.log('🔄 Initializing queue display for app:', applicationId);

            // Use the same logic as the working enhanced queue check
            const csrfTokenElement = document.getElementById('csrfToken');
            const csrfToken = csrfTokenElement ? csrfTokenElement.value : '';

            console.log('🔍 Queue Display Init Debug:', {
                csrfTokenElement: csrfTokenElement ? 'FOUND' : 'NOT FOUND',
                csrfToken: csrfToken ? 'HAS VALUE' : 'EMPTY',
                applicationId: applicationId
            });

            const formData = new FormData();
            formData.append('action', 'check_queue_status');
            formData.append('application_id', applicationId);
            formData.append('csrf_token', csrfToken);

            fetch('admin2_dashboard.php', {
                method: 'POST',
                body: formData,
                cache: "no-store"
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('📊 Queue init response:', data);
                    if (data.success && data.queue_count !== undefined) {
                        console.log('📊 Queue status retrieved:', data.queue_count, 'items for app:', applicationId);
                        updateQueueDisplay(applicationId, data.queue_count);
                    } else {
                        console.log('📊 No queue data or empty queue for app:', applicationId, 'Response:', data);
                        updateQueueDisplay(applicationId, 0);
                    }
                })
                .catch(error => {
                    console.error('Queue status check failed:', error);
                    console.error('Error details:', {
                        applicationId: applicationId,
                        csrfToken: csrfToken ? 'Present' : 'Missing',
                        error: error.message
                    });
                    updateQueueDisplay(applicationId, 0);
                });
        }        // ===== Document Update Function =====
        function updateDocumentStatus(applicationId, documentId, status) {
            console.log('🔄 Document action requested:', {
                applicationId: applicationId,
                documentId: documentId,
                status: status
            });

            // Ensure any existing loading overlay is hidden
            hideLoadingOverlay();

            // Show confirmation modal
            showConfirmationModal(applicationId, documentId, status);
        }

        // ===== Pre-Approval Confirmation Modal Functions =====
        let pendingPreApprovalData = null;

        function showPreApprovalConfirmModal(preApprovalStatus, approvalReason) {
            const modal = document.getElementById('preApprovalConfirmModal');
            const title = document.getElementById('preApprovalTitle');
            const message = document.getElementById('preApprovalMessage');
            const icon = document.getElementById('preApprovalIcon');
            const confirmBtn = document.getElementById('preApprovalConfirmBtn');
            const detailsDiv = document.getElementById('preApprovalDetails');
            const header = document.getElementById('preApprovalHeader');

            // Set header styling based on status
            let statusIcon, statusColor;
            if (preApprovalStatus === 'Approved') {
                title.textContent = '✓ Approve Pre-Application';
                message.textContent = 'Are you sure you want to APPROVE this pre-application?';
                icon.className = 'fas fa-check-circle approve';
                icon.style.color = '#51cf66';
                confirmBtn.className = 'confirmation-btn confirmation-btn-confirm';
                confirmBtn.style.backgroundColor = '#51cf66';
                statusColor = '#51cf66';
                detailsDiv.innerHTML = `
                    <div style="color: ${statusColor}; font-weight: 600; margin-bottom: 8px;">
                        <i class="fas fa-arrow-right"></i> Next Stage
                    </div>
                    <div>This action will move the application to the next stage. The applicant will be notified of this decision.</div>
                `;
            } else if (preApprovalStatus === 'Rejected') {
                title.textContent = '✕ Reject Pre-Application';
                message.textContent = 'Are you sure you want to REJECT this pre-application?';
                icon.className = 'fas fa-times-circle reject';
                icon.style.color = '#ff6b6b';
                confirmBtn.className = 'confirmation-btn confirmation-btn-confirm reject';
                confirmBtn.style.backgroundColor = '#ff6b6b';
                statusColor = '#ff6b6b';
                detailsDiv.innerHTML = `
                    <div style="color: ${statusColor}; font-weight: 600; margin-bottom: 8px;">
                        <i class="fas fa-ban"></i> Cannot Be Undone
                    </div>
                    <div>This action cannot be undone. The applicant will be notified and the application will be archived.</div>
                `;
            } else {
                title.textContent = '⏳ Keep Application as Pending';
                message.textContent = 'Are you sure you want to keep this application as PENDING?';
                icon.className = 'fas fa-hourglass-half';
                icon.style.color = '#f39c12';
                confirmBtn.className = 'confirmation-btn confirmation-btn-confirm';
                confirmBtn.style.backgroundColor = '#f39c12';
                statusColor = '#f39c12';
                detailsDiv.innerHTML = `
                    <div style="color: ${statusColor}; font-weight: 600; margin-bottom: 8px;">
                        <i class="fas fa-clock"></i> Under Review
                    </div>
                    <div>The application will remain under review. Further documents or clarifications may be requested.</div>
                `;
            }

            // Store the pending data BEFORE showing modal
            pendingPreApprovalData = { preApprovalStatus, approvalReason };
            console.log('✓ Stored pendingPreApprovalData:', pendingPreApprovalData);

            // Ensure modal is visible
            modal.style.display = 'flex';
            // Ensure pre-approval modal sits above loan details modal
            modal.style.zIndex = '10100';
            setTimeout(() => {
                modal.classList.add('show');
            }, 10);
        }

        function closePreApprovalModal() {
            const modal = document.getElementById('preApprovalConfirmModal');
            modal.classList.remove('show');
            pendingPreApprovalData = null;
        }

        function showEmailSuccessModal(message = null) {
            const modal = document.getElementById('emailSuccessModal');
            if (!modal) return;

            // Update message if provided
            if (message) {
                const messageEl = document.getElementById('emailSuccessMessage');
                if (messageEl) {
                    messageEl.textContent = message;
                }
            }

            // Show modal with animation
            modal.classList.add('show');

            // Optional: Auto-close after 5 seconds
            setTimeout(() => {
                closeEmailSuccessModal();
            }, 5000);
        }

        function closeEmailSuccessModal() {
            const modal = document.getElementById('emailSuccessModal');
            if (!modal) return;
            modal.classList.remove('show');
        }

        // Form submission handler for pre-approval status form
        function handlePreApprovalSubmit(event) {
            if (event) event.preventDefault();
            console.log('📋 handlePreApprovalSubmit called');

            // Clear previous error messages
            const errorMsg = document.getElementById('decisionErrorMessage');
            if (errorMsg) errorMsg.style.display = 'none';

            const statusForm = document.getElementById('statusUpdateForm');
            if (!statusForm) {
                console.error('❌ Form not found');
                showErrorMessage('Form not found. Please refresh and try again.');
                return;
            }

            // Get form values
            const preApprovalStatus = document.querySelector('input[name="pre_approval_status"]:checked');
            const approvalReason = document.querySelector('select[name="approval_reason"]');

            console.log('📋 Status element:', preApprovalStatus);
            console.log('📋 Reason element:', approvalReason);
            console.log('📋 Form values retrieved:', { status: preApprovalStatus?.value, reason: approvalReason?.value });

            // Validate inputs with detailed feedback
            if (!preApprovalStatus || !preApprovalStatus.value) {
                console.error('❌ No pre-approval status selected');
                showErrorMessage('Please select a pre-approval status (Pending, Approve, or Reject)');
                highlightField('status-pending');
                return;
            }

            if (!approvalReason) {
                console.error('❌ Approval reason dropdown not found');
                showErrorMessage('Decision reasoning dropdown not found. Please refresh the page.');
                return;
            }

            const reasonText = approvalReason.value.trim();

            if (reasonText === '') {
                console.error('❌ Approval reason is empty');
                showErrorMessage('Please select a decision remark from the dropdown.');
                approvalReason.focus();
                approvalReason.style.borderColor = '#f44336';
                setTimeout(() => { approvalReason.style.borderColor = '#ddd'; }, 2000);
                return;
            }

            // Special handling for "Others" option
            if (reasonText === 'Others') {
                const customRemark = document.getElementById('customRemark');
                if (!customRemark) {
                    console.error('❌ Custom remark field not found');
                    showErrorMessage('Custom remark field not found. Please refresh the page.');
                    return;
                }

                const customText = customRemark.value.trim();
                if (customText === '') {
                    console.error('❌ Custom remark is empty');
                    showErrorMessage('Please enter a custom decision remark.');
                    customRemark.focus();
                    customRemark.style.borderColor = '#f44336';
                    setTimeout(() => { customRemark.style.borderColor = '#2196F3'; }, 2000);
                    return;
                }

                if (customText.length < 5) {
                    console.error('❌ Custom remark too short:', customText.length);
                    showErrorMessage('Custom remark must be at least 5 characters long.');
                    customRemark.focus();
                    return;
                }

                if (customText.length > 500) {
                    console.error('❌ Custom remark too long:', customText.length);
                    showErrorMessage('Custom remark exceeds maximum length of 500 characters.');
                    return;
                }

                // Use custom remark as the approval reason
                approvalReason.value = customText;
            } else if (reasonText.length < 5) {
                console.error('❌ Approval reason too short:', reasonText.length);
                showErrorMessage('Please select a valid decision remark.');
                approvalReason.focus();
                return;
            } else if (reasonText.length > 1000) {
                console.error('❌ Approval reason too long:', reasonText.length);
                showErrorMessage('Decision remark exceeds maximum length.');
                return;
            }

            // DEBUG: Log what we're about to confirm
            const statusValue = preApprovalStatus.value;
            const reasonValue = reasonText;
            console.log('📋 PRE-APPROVAL FORM SUBMITTED - About to show confirmation modal:', {
                pre_approval_status: statusValue,
                approval_reason: reasonValue
            });

            // Show the confirmation modal (don't submit yet)
            console.log('📋 Calling showPreApprovalConfirmModal with:', statusValue, reasonValue);
            showPreApprovalConfirmModal(statusValue, reasonValue);
            console.log('📋 Confirmation modal should now be visible');
        }

        // Helper function to display error messages
        function showErrorMessage(message) {
            const errorMsg = document.getElementById('decisionErrorMessage');
            const errorText = document.getElementById('errorMessageText');
            if (errorMsg && errorText) {
                errorText.textContent = message;
                errorMsg.style.display = 'block';
                errorMsg.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            showNotification(message, 'error');
        }

        // Helper function to highlight field with animation
        function highlightField(fieldId) {
            const field = document.getElementById(fieldId);
            if (field) {
                const label = document.querySelector(`label[for="${fieldId}"]`);
                if (label) {
                    label.style.animation = 'pulse 0.5s ease-in-out';
                    setTimeout(() => { label.style.animation = ''; }, 500);
                }
            }
        }

        function confirmPreApprovalAction() {
            // Use the pending data stored from the confirmation modal
            console.log('confirmPreApprovalAction called, pendingPreApprovalData:', pendingPreApprovalData);

            if (!pendingPreApprovalData) {
                console.error('❌ CRITICAL: No pending pre-approval data found!');
                showNotification('Error: No decision data stored. Please try again.', 'error');
                return;
            }

            const { preApprovalStatus, approvalReason } = pendingPreApprovalData;

            // Validate the data
            if (!preApprovalStatus || !approvalReason) {
                console.error('❌ Invalid pending data:', pendingPreApprovalData);
                showNotification('Error: Invalid decision data. Please try again.', 'error');
                return;
            }

            console.log('✅ Valid pending data found, proceeding with submission');

            // Close the confirmation modal first
            closePreApprovalModal();

            // Now submit the actual form with the pending data
            handlePreApprovalSubmitWithData(preApprovalStatus, approvalReason);
        }

        // New function to submit with explicit data (used when coming from confirmation modal)
        function handlePreApprovalSubmitWithData(preApprovalStatusValue, approvalReasonValue) {
            const statusForm = document.getElementById('statusUpdateForm');
            if (!statusForm) {
                console.error('Status form not found');
                return;
            }

            // Get the form data
            const formData = new FormData(statusForm);

            // Override with the confirmed data
            formData.set('pre_approval_status', preApprovalStatusValue);
            formData.set('approval_reason', approvalReasonValue);

            // DEBUG: Log what we're sending
            console.log('PRE-APPROVAL SUBMISSION (from confirmation):', {
                pre_approval_status: preApprovalStatusValue,
                approval_reason: approvalReasonValue
            });

            // Add CSRF token
            if (!addCSRFTokenToFormData(formData)) {
                showErrorMessage('Security token error. Please refresh the page.');
                return;
            }

            // DEBUG: Log FormData contents before sending
            console.log('DEBUG: FormData contents before sending:');
            for (let [key, value] of formData.entries()) {
                console.log(`  ${key}: ${value}`);
            }

            // Get submit button and disable it during submission
            const submitBtn = document.getElementById('submitDecisionBtn');
            const originalBtnText = submitBtn ? submitBtn.innerHTML : '';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Processing...</span>';
            }

            // Enhanced loading message that indicates email is being sent for final decisions
            const isFinalDecision = preApprovalStatusValue === 'Approved' || preApprovalStatusValue === 'Rejected';
            const loadingMessage = isFinalDecision
                ? `Submitting decision & sending email to applicant...`
                : `Updating application status...`;

            // Show loading overlay
            showLoadingOverlay(loadingMessage);

            console.log('DEBUG: About to send fetch request to admin2_dashboard.php with:', {
                method: 'POST',
                body: 'FormData',
                status: preApprovalStatusValue,
                reason: approvalReasonValue
            });

            fetch("admin2_dashboard.php", {
                method: "POST",
                body: formData,
            })
                .then((response) => {
                    console.log('DEBUG: Response status:', response.status);
                    return response.json();
                })
                .then((data) => {
                    console.log('DEBUG: Response data:', data);
                    hideLoadingOverlay();
                    if (data.success) {
                        // Enhanced success message that confirms email was sent for final decisions
                        const successMessage = isFinalDecision
                            ? `✅ Decision submitted successfully to ${preApprovalStatusValue}! Email sent to applicant.`
                            : `✅ Application status updated to ${preApprovalStatusValue}!`;

                        // Show notification AFTER loading is hidden
                        setTimeout(() => {
                            showNotification(successMessage, "success");
                        }, 300);

                        // Get applicationId from the hidden input
                        const appIdInput = statusForm.querySelector('input[name="application_id"]');
                        const applicationId = appIdInput ? appIdInput.value : null;
                        setTimeout(() => {
                            if (applicationId) {
                                // Clear cache so updated data is fetched
                                loanDetailsCache.delete(applicationId);
                                openLoanDetailsModal(applicationId); // Refresh modal
                            }
                        }, 1500);
                    } else {
                        showErrorMessage(`Submission failed: ${data.message}`);
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalBtnText;
                        }
                    }
                })
                .catch((error) => {
                    hideLoadingOverlay();
                    console.error("Error updating status:", error);
                    showErrorMessage("Network error submitting decision. Please check your connection and try again.");
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnText;
                    }
                });
        }

        // ===== Modal Functions =====

        // OPTIMIZATION: Client-side cache for loan details with auto-invalidation
        const loanDetailsCache = new Map();
        const CACHE_TTL = 5 * 60 * 1000; // 5 minutes cache TTL
        const cacheTimestamps = new Map();

        function isCacheValid(applicationId) {
            const timestamp = cacheTimestamps.get(applicationId);
            if (!timestamp) return false;
            return (Date.now() - timestamp) < CACHE_TTL;
        }

        function openLoanDetailsModal(applicationId) {
            console.log("Opening modal for applicationId:", applicationId);

            const modal = document.getElementById("loanDetailsModal");
            const content = document.getElementById("loanDetailsContent");

            if (!modal || !content) {
                console.error("Modal elements not found - check HTML IDs");
                return;
            }

            // Show loading skeleton immediately for instant feedback
            modal.style.zIndex = '10000';
            modal.classList.add("show");
            content.innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 24px; color: #2d7d32; margin-right: 10px;"></i> 
                    Loading loan details...
                </div>
            `;

            // OPTIMIZATION: Check cache only if still valid (not expired)
            if (loanDetailsCache.has(applicationId) && isCacheValid(applicationId)) {
                console.log("Loading from valid cache for:", applicationId);
                // Use setTimeout to avoid blocking UI
                setTimeout(() => {
                    renderLoanDetailsModalProgressive(loanDetailsCache.get(applicationId), content, modal);
                    PollingManager.resumePolling();
                }, 50);
                return;
            }

            // Cache expired or doesn't exist - fetch fresh data
            if (loanDetailsCache.has(applicationId)) {
                console.log("Cache expired for:", applicationId, "- fetching fresh data");
                loanDetailsCache.delete(applicationId);
                cacheTimestamps.delete(applicationId);
            }

            // PAUSE POLLING to prevent 503 errors during heavy load
            PollingManager.pausePolling();

            fetch(`admin2_dashboard.php?action=get_loan_details&application_id=${applicationId}`, { cache: "no-store" })
                .then((response) => {
                    if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                    return response.json();
                })
                .then((data) => {
                    if (!data.success) {
                        content.innerHTML = `<p class="error">Error: ${data.message}</p>`;
                        PollingManager.resumePolling();
                        return;
                    }

                    // OPTIMIZATION: Store in cache with timestamp
                    loanDetailsCache.set(applicationId, data);
                    cacheTimestamps.set(applicationId, Date.now());
                    console.log("Cached data for:", applicationId);

                    renderLoanDetailsModalProgressive(data, content, modal);

                    if (window.__modalTimeoutId) {
                        clearTimeout(window.__modalTimeoutId);
                        window.__modalTimeoutId = null;
                    }
                    hideLoadingOverlay();

                    // RESUME POLLING after successful load
                    PollingManager.resumePolling();
                })
                .catch((error) => {
                    console.error("Fetch error:", error);
                    content.innerHTML = `<p class="error">Failed to load loan details: ${error.message}</p>`;
                    PollingManager.resumePolling();
                });
        }

        // Progressive rendering function for faster modal display
        function renderLoanDetailsModalProgressive(data, content, modal) {
            const { loan, documents, remarks, logs } = data;

            // Start with basic structure immediately
            content.innerHTML = `<div id="modalContent" style="opacity: 0.8; transition: opacity 0.3s;">Loading content...</div>`;

            // Render synchronously for faster display
            renderLoanDetailsModal(data, content, modal);
        }

        function renderLoanDetailsModal(data, content, modal) {
            const { loan, documents, remarks, logs } = data;

            // DEBUG: Log the loan data to see application_id value
            console.log('🔍 DEBUG - Loan data in modal:', {
                application_id: loan.application_id,
                user_id: loan.user_id,
                status: loan.status,
                loan_object: loan
            });

            // Financial Info Section
            const financialInfo = loan.net_income ? `
                <div class="modal-section">
                  <h3>Financial Information</h3>
                  <div class="financial-grid">
                    <div class="financial-card">
                      <h4>Income Sources</h4>
                      <div class="financial-items">
                        ${["business_income", "salary_income", "remittance_income", "other_income", "business2_income", "salary2_income"].map(field => `
                          <div class="financial-row">
                            <span>${field.replace("_", " ").replace(/\b\w/g, l => l.toUpperCase())}:</span>
                            <strong>₱${parseFloat(loan[field] || 0).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</strong>
                          </div>`).join("")}
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
                            <span>${field.replace("_", " ").replace(/\b\w/g, l => l.toUpperCase())}:</span>
                            <strong>₱${parseFloat(loan[field] || 0).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</strong>
                          </div>`).join("")}
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
                </div>` : `<div class="modal-section">
                  <h3>Financial Information</h3>
                  <p class="no-data">No financial information available.</p>
                </div>`;

            // Documents Section
            const documentsTable = documents.length ? `
                <div class="modal-section">
                  <h3>Submitted Documents</h3>
                  <div class="documents-table-wrapper">
                    <table class="modal-documents-table">
                      <thead>
                        <tr>
                          <th>Document</th>
                          <th>Status</th>
                          <th>Action</th>
                        </tr>
                      </thead>
                      <tbody>
                        ${documents.map(doc => `
                          <tr>
                            <td><a href="${doc.file_path}" target="_blank">${doc.document_name}</a></td>
                            <td><span class="status-badge status-${doc.status.toLowerCase()}">${doc.status}</span></td>
                            <td>
                              <div class="document-actions">
                                <button class="doc-action-btn doc-approve" 
                                  onclick="updateDocumentStatus('${loan.application_id}', '${doc.document_id}', 'Approved')" 
                                  title="${loan.pre_approval_status === 'Approved' ? 'Pre-approval already approved - cannot change' : (doc.status === 'Approved' ? 'Already approved' : 'Approve Document')}"
                                  ${(doc.status === 'Approved' || loan.pre_approval_status === 'Approved') ? 'disabled' : ''} 
                                  style="${(doc.status === 'Approved' || loan.pre_approval_status === 'Approved') ? 'opacity: 0.5; cursor: not-allowed; background: #ccc !important; color: #666 !important;' : 'cursor: pointer;'}">
                                  <i class="fas fa-check"></i> Approve
                                </button>
                                <button class="doc-action-btn doc-reject" 
                                  onclick="updateDocumentStatus('${loan.application_id}', '${doc.document_id}', 'Rejected')" 
                                  title="${loan.pre_approval_status === 'Approved' ? 'Pre-approval already approved - cannot change' : (doc.status === 'Rejected' ? 'Already rejected' : 'Reject Document')}"
                                  ${(doc.status === 'Rejected' || loan.pre_approval_status === 'Approved') ? 'disabled' : ''} 
                                  style="${(doc.status === 'Rejected' || loan.pre_approval_status === 'Approved') ? 'opacity: 0.5; cursor: not-allowed; background: #ccc !important; color: #666 !important;' : 'cursor: pointer;'}">
                                  <i class="fas fa-times"></i> Reject
                                </button>
                              </div>
                            </td>
                          </tr>`).join("")}
                      </tbody>
                    </table>
                  </div>
                </div>` : `<div class="modal-section"><h3>Submitted Documents</h3><p class="no-data">No documents submitted.</p></div>`;



            // Remarks Section with Admin Tracking
            const remarksSection = remarks.length ? `
                <div class="modal-section">
                  <h3>Review & Remarks History</h3>
                  <div class="remarks-timeline">
                    ${remarks.map((r, idx) => `
                      <div class="remark-item" style="${idx === 0 ? 'background: #f0f8f5;' : ''}">
                        <div class="remark-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px solid #e0e0e0;">
                          <div style="flex: 1;">
                            <div style="font-weight: 600; color: #1b5e20; display: flex; align-items: center; gap: 6px;">
                              <i class="fas fa-user" style="color: #1b5e20;"></i>
                              ${r.admin_name || "Admin"}
                              ${idx === 0 ? '<span style="background: #1b5e20; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: 600; margin-left: 6px;">LATEST</span>' : ''}
                            </div>
                            <div style="font-size: 12px; color: #666; margin-top: 3px;">
                              <i class="fas fa-calendar-alt"></i> ${new Date(r.created_at).toLocaleString()}
                            </div>
                          </div>
                        </div>
                        <div class="remark-content" style="background: white; padding: 10px; border-radius: 4px; margin-left: 0;">${r.remarks}</div>
                      </div>`).join("")}
                  </div>
                </div>` : `<div class="modal-section"><h3>Review & Remarks History</h3><p class="no-data">No remarks or review notes available yet.</p></div>`;

            // Activity Logs Section
            const logsSection = logs.length ? `
                <div class="modal-section">
                  <h3>Activity Logs</h3>
                  <div class="logs-timeline">
                    ${logs.map(log => `
                      <div class="log-item">
                        <div class="log-content">
                          <div class="log-header">
                            <span>
                              ${new Date(log.created_at).toLocaleString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            })
                }
                            </span>
                            <span>${log.user_role}</span>
                          </div>
                          <div class="log-description"><strong>${log.action_type}:</strong> ${log.description}</div>
                        </div>
                      </div>`).join("")}
                  </div>
                </div>` : `<div class="modal-section"><h3>Activity Logs</h3><p class="no-data">No activity logs available.</p></div>`;

            // Admin 2 Update Form
            const updateForm = `                <div class="modal-section update-section pre-approval-section">
                  <h3><i class="fas fa-check-circle"></i> Pre-Approval Decision</h3>
                  <form id="statusUpdateForm" class="pre-approval-form">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="application_id" value="${loan.application_id}">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
                    
                    <!-- Application Status Summary -->
                    <div style="margin-bottom: 20px; padding: 12px; background: linear-gradient(135deg, #e8f5e9 0%, #f0f8f5 100%); border-radius: 4px; border-left: 4px solid #2e7d32;">
                      <div style="display: flex; align-items: center; justify-content: space-between; gap: 15px;">
                        <div style="flex: 1;">
                          <div style="font-weight: 600; color: #1b5e20; font-size: 13px; margin-bottom: 6px;">
                            Current Application Status
                          </div>
                          <div style="font-size: 12px; color: #333; line-height: 1.6;">
                            <div style="margin-bottom: 4px;"><strong>Pre-Approval:</strong> <span style="color: ${loan.pre_approval_status === 'Approved' ? '#2e7d32' : (loan.pre_approval_status === 'Rejected' ? '#d32f2f' : '#f57f17')}; font-weight: 600;">${loan.pre_approval_status || 'Pending'}</span></div>
                            <div><strong>Last Updated:</strong> ${loan.updated_at ? new Date(loan.updated_at).toLocaleString() : 'Never'}</div>
                          </div>
                        </div>
                        <div style="text-align: center;">
                          <div class="status-badge status-${loan.pre_approval_status.toLowerCase()}" style="padding: 8px 12px; border-radius: 6px; min-width: 80px;">
                            <span style="font-size: 11px; font-weight: 600;">${loan.pre_approval_status}</span>
                          </div>
                        </div>
                      </div>
                    </div>

                    <!-- Submitted Documents for Review -->
                    ${documentsTable}

                    <!-- Document Approval Progress Indicator -->
                    <div style="margin-bottom: 20px; padding: 15px; background: #f5f5f5; border-radius: 4px; border: 1px solid #e0e0e0;">
                      <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                        <div style="font-weight: 600; color: #1b5e20; font-size: 13px;">
                          <i class="fas fa-file-check"></i> Document Approval Progress
                        </div>
                        <div style="font-size: 18px; font-weight: 700; color: #2e7d32;">
                          ${documents.filter(d => d.status.toLowerCase() === 'approved').length}/${documents.length}
                        </div>
                      </div>
                      <div style="width: 100%; height: 8px; background: #ddd; border-radius: 4px; overflow: hidden; margin-bottom: 8px;">
                        <div style="height: 100%; background: linear-gradient(90deg, #2e7d32 0%, #1b5e20 100%); width: ${documents.length > 0 ? (documents.filter(d => d.status.toLowerCase() === 'approved').length / documents.length * 100) : 0}%; transition: width 0.3s ease;"></div>
                      </div>
                      <div style="display: flex; justify-content: space-between; font-size: 11px; color: #666; margin-bottom: 15px;">
                        <span>${documents.filter(d => d.status.toLowerCase() === 'approved').length} Approved</span>
                        <span>${documents.filter(d => d.status.toLowerCase() === 'rejected').length} Rejected</span>
                        <span>${documents.filter(d => d.status.toLowerCase() === 'pending').length} Pending</span>
                      </div>
                      
                      <!-- Consolidated Email Queue Management -->
                      <div style="background: linear-gradient(145deg, #e8f5e8, #f1f8e9); border: 1px solid #c8e6c9; border-radius: 8px; padding: 12px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                          <div style="display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-envelope-open-text" style="color: #2e7d32;"></i>
                            <span style="font-weight: 500; color: #1b5e20; font-size: 12px;">Email Queue</span>
                            <span id="queueCount-${loan.application_id}" 
                                  data-queue-app="${loan.application_id}"
                                  style="background: #4caf50; color: white; padding: 1px 6px; border-radius: 10px; font-size: 10px; font-weight: 600;">📧 Checking...</span>
                          </div>
                        </div>
                        <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                          <button type="button" 
                                  onclick="initializeQueueDisplay('${loan.application_id}');"
                                  title="Refresh queue status"
                                  style="background: #2196f3; color: white; border: none; padding: 6px 10px; border-radius: 4px; font-size: 11px; cursor: pointer; transition: all 0.2s;">
                            <i class="fas fa-sync-alt"></i> Refresh
                          </button>
                          <button type="button" 
                                  id="sendQueuedEmailBtn-${loan.application_id}"
                                  data-send-queue="${loan.application_id}"
                                  onclick="sendQueuedEmail('${loan.application_id}')"
                                  title="${loan.pre_approval_status === 'Approved' ? 'Email queue disabled - pre-approval already approved' : 'Send consolidated email with all queued changes'}"
                                  style="background: ${loan.pre_approval_status === 'Approved' ? '#ccc' : '#2196f3'}; color: ${loan.pre_approval_status === 'Approved' ? '#999' : 'white'}; border: none; padding: 6px 12px; border-radius: 4px; font-size: 11px; font-weight: 500; cursor: ${loan.pre_approval_status === 'Approved' ? 'not-allowed' : 'pointer'}; transition: all 0.2s; opacity: ${loan.pre_approval_status === 'Approved' ? '0.5' : '0.5'};"
                                  ${loan.pre_approval_status === 'Approved' ? 'disabled' : ''}>
                            <i class="fas fa-paper-plane"></i> Send Email
                          </button>
                        </div>
                        <div style="font-size: 10px; color: #555; margin-top: 8px; line-height: 1.3;">
                          📧 Document changes are queued and sent together in one consolidated email
                        </div>
                        
                        <!-- Queue Info Container for JavaScript functions -->
                        <div id="queueInfoContainer" style="margin-top: 8px; font-size: 10px; color: #666;">
                          <div id="noQueueMessage" style="display: none;">No items in queue</div>
                        </div>
                      </div>
                    </div>

                    <!-- Decision Reasoning Section - System Style -->
                    <div class="modal-section">
                      <h3>Decision Reasoning</h3>
                      
                      <!-- Pre-Approval Status Selection -->
                      <div style="margin-bottom: 16px; padding: 12px; background: #f5f5f5; border-radius: 4px;">
                        <label style="display: block; font-weight: 600; color: #333; margin-bottom: 10px; font-size: 12px;">
                          Pre-Approval Status:
                        </label>
                        <div class="status-buttons" style="display: flex; gap: 8px; flex-wrap: wrap;">
                          <!-- Keep Pending Button -->
                          <input type="radio" id="status-pending" name="pre_approval_status" value="Pending" 
                            ${loan.pre_approval_status === "Pending" ? "checked" : ""} style="display: none;" onchange="updateStatusButtonStyles();">
                          <label for="status-pending" class="status-btn status-btn-pending" title="Keep application in pending status" style="flex: 1; min-width: 100px; padding: 10px 12px; text-align: center; background: ${loan.pre_approval_status === "Pending" ? '#ffc107' : '#fff9e6'}; color: ${loan.pre_approval_status === "Pending" ? '#333' : '#f57f17'}; border: 2px solid #ffc107; border-radius: 4px; cursor: pointer; font-weight: 600; transition: all 0.3s ease; font-size: 12px;">
                            <i class="fas fa-hourglass-start" style="margin-right: 4px;"></i>Pending
                          </label>

                          <!-- Approve Button - DISABLED IF ALREADY APPROVED -->
                          <input type="radio" id="status-approved" name="pre_approval_status" value="Approved" 
                            ${loan.pre_approval_status === "Approved" ? 'checked' : ''} ${(documents.every(d => d.status.toLowerCase() === 'approved') && loan.pre_approval_status !== 'Approved') ? '' : 'disabled'} style="display: none;" onchange="updateStatusButtonStyles();">
                          <label for="status-approved" class="status-btn status-btn-approved" title="${loan.pre_approval_status === 'Approved' ? 'Application already approved - cannot change' : (documents.every(d => d.status.toLowerCase() === 'approved') ? 'Approve the application' : 'Approve all documents first')}" style="flex: 1; min-width: 100px; padding: 10px 12px; text-align: center; background: ${loan.pre_approval_status === "Approved" ? '#4caf50' : '#f1f8f6'}; color: ${loan.pre_approval_status === "Approved" ? 'white' : '#2e7d32'}; border: 2px solid #4caf50; border-radius: 4px; cursor: ${(documents.every(d => d.status.toLowerCase() === 'approved') && loan.pre_approval_status !== 'Approved') ? 'pointer' : 'not-allowed'}; font-weight: 600; transition: all 0.3s ease; font-size: 12px; opacity: ${(documents.every(d => d.status.toLowerCase() === 'approved') && loan.pre_approval_status !== 'Approved') ? '1' : '0.5'};" ${!documents.every(d => d.status.toLowerCase() === 'approved') || loan.pre_approval_status === 'Approved' ? 'onclick="event.preventDefault()"' : ''} onchange="updateStatusButtonStyles();">
                            <i class="fas fa-check-double" style="margin-right: 4px;"></i>Approve
                          </label>

                          <!-- Reject Button - DISABLED IF ALREADY APPROVED -->
                          <input type="radio" id="status-rejected" name="pre_approval_status" value="Rejected" 
                            ${loan.pre_approval_status === "Rejected" ? "checked" : ""} ${loan.pre_approval_status === "Approved" ? 'disabled' : ''} style="display: none;" onchange="updateStatusButtonStyles();">
                          <label for="status-rejected" class="status-btn status-btn-rejected" title="${loan.pre_approval_status === 'Approved' ? 'Application already approved - cannot change' : 'Reject the application'}" style="flex: 1; min-width: 100px; padding: 10px 12px; text-align: center; background: ${loan.pre_approval_status === "Rejected" ? '#f44336' : '#fff5f5'}; color: ${loan.pre_approval_status === "Rejected" ? 'white' : '#d32f2f'}; border: 2px solid #f44336; border-radius: 4px; cursor: ${loan.pre_approval_status === "Approved" ? 'not-allowed' : 'pointer'}; font-weight: 600; transition: all 0.3s ease; font-size: 12px; opacity: ${loan.pre_approval_status === "Approved" ? '0.5' : '1'};" ${loan.pre_approval_status === "Approved" ? 'onclick="event.preventDefault()"' : ''} onchange="updateStatusButtonStyles();">
                            <i class="fas fa-times-circle" style="margin-right: 4px;"></i>Reject
                          </label>
                        </div>
                        <small style="display: block; margin-top: 8px; color: #666; font-size: 11px;">Tip: All documents must be approved before you can approve the application.</small>
                      </div>

                      <!-- Decision Reason - Remarks Dropdown -->
                      <div style="margin-bottom: 12px; width: 100%;">
                        <label for="decisionReason" style="font-weight: 600; color: #333; margin-bottom: 8px; font-size: 12px; display: block;">
                          Decision Remark <span style="color: #f44336;">*</span>
                        </label>

                        <select name="approval_reason" id="decisionReason" class="form-select" required
                          style="width: 100%; max-width: 100%; padding: 12px 15px; border: 2px solid #ddd; border-radius: 4px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 13px; transition: border-color 0.3s ease; cursor: pointer; min-height: 45px; box-sizing: border-box;"
                          onfocus="this.style.borderColor='#2196F3'; this.style.boxShadow='0 0 0 3px rgba(33, 150, 243, 0.1)';"
                          onblur="this.style.borderColor='#ddd'; this.style.boxShadow='none';"
                          onchange="updateCharCount(); handleDecisionRemarkChange();">
                          <option value="">-- Select a remark to record --</option>
                        </select>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 8px;">
                          <small style="color: #666; font-size: 12px;">Choose a suggested remark that will be recorded to the remarks table</small>
                          <div style="color: #2196F3; font-size: 12px; font-weight: 600;"><span id="decisionReasonCharCount">0</span><span style="color: #ccc;"> chars</span></div>
                        </div>

                        <!-- Custom Remark Input (shown when "Others" is selected) -->
                        <div id="customRemarkContainer" style="display: none; margin-top: 12px; padding: 12px; background: #f0f7ff; border-radius: 4px; border-left: 3px solid #2196F3;">
                          <label for="customRemark" style="font-weight: 600; color: #333; margin-bottom: 8px; font-size: 12px; display: block;">
                            Enter Custom Remark <span style="color: #f44336;">*</span>
                          </label>
                          <textarea id="customRemark" name="custom_remark" placeholder="Enter your custom decision remark here..." 
                            style="width: 100%; padding: 12px 15px; border: 2px solid #2196F3; border-radius: 4px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 13px; resize: vertical; min-height: 120px; transition: border-color 0.3s ease; line-height: 1.5;"
                            onfocus="this.style.borderColor='#1976D2'; this.style.boxShadow='0 0 0 3px rgba(33, 150, 243, 0.1)';"
                            onblur="this.style.borderColor='#2196F3'; this.style.boxShadow='none';"
                            oninput="updateCustomRemarkCount();" maxlength="500"></textarea>
                          <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 8px;">
                            <small style="color: #666; font-size: 12px;">Provide a detailed explanation for your decision</small>
                            <div style="color: #2196F3; font-size: 12px; font-weight: 600;"><span id="customRemarkCharCount">0</span><span style="color: #ccc;">/500</span></div>
                          </div>
                        </div>
                      </div>

                      <!-- Error Message Display (Initially Hidden) -->
                      <div id="decisionErrorMessage" style="display: none; margin-bottom: 12px; padding: 10px; background: #ffcdd2; border-left: 3px solid #f44336; border-radius: 3px; color: #c62828; font-size: 12px;">
                        <i class="fas fa-exclamation-circle" style="margin-right: 6px;"></i><span id="errorMessageText">Please complete all required fields</span>
                      </div>

                      <!-- Submit Button - System Style -->
                      <button type="submit" class="btn btn-primary" id="submitDecisionBtn"
                        style="width: 100%; padding: 12px 20px; background: #1b5e20; color: white; border: none; border-radius: 4px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 14px;"
                        onmouseover="this.style.backgroundColor='#174f1b'; this.style.boxShadow='0 4px 12px rgba(27, 94, 32, 0.4)';"
                        onmouseout="this.style.backgroundColor='#1b5e20'; this.style.boxShadow='none';">
                        <i class="fas fa-paper-plane"></i> <span id="submitBtnText">Submit Decision</span>
                      </button>
                      <small style="display: block; margin-top: 8px; color: #666; font-size: 11px; text-align: center;">Your submission will trigger an email to the applicant</small>
                    </div>
                  </form>
                </div>`;

            // Populate Modal
            content.innerHTML = `
                <div class="loan-details-wrapper">
                  <div class="modal-section applicant-info-section">
                    <h3>Applicant Information</h3>
                    <div class="info-grid">
                      <div><strong>Full Name:</strong> ${loan.first_name} ${loan.last_name}</div>
                      <div><strong>Email:</strong> ${loan.email}</div>
                      <div><strong>Contact:</strong> ${loan.contact || "Not provided"}</div>
                      <div><strong>Birthday:</strong> ${loan.birthday ? new Date(loan.birthday).toLocaleDateString() : "Not provided"}</div>
                    </div>
                  </div>
        
                  <div class="modal-section loan-info-section">
                    <h3>Loan Details</h3>
                    <div class="info-grid">
                      <div><strong>Loan Type:</strong> ${loan.type_name}</div>
                      <div><strong>Amount Applied:</strong> ₱${parseFloat(loan.amount_applied).toLocaleString()}</div>
                      <div><strong>Final Loan Amount:</strong> ${loan.final_loan_amount ? "₱" + parseFloat(loan.final_loan_amount).toLocaleString() : "Not set"}</div>
                      <div><strong>Submission Date:</strong> ${new Date(loan.created_at).toLocaleDateString()}</div>
                      <div><strong>Loan Status:</strong> ${loan.status}</div>
                      <div><strong>Pre-Approval Status:</strong> ${loan.pre_approval_status}</div>
                      <div><strong>Credit Investigation:</strong> ${loan.credit_investigation_status}</div>
                    </div>
                  </div>
        
                  ${financialInfo}
                  ${updateForm}
                  ${remarksSection}
                  ${logsSection}
                </div>
              `;

            // Attach event listeners for document forms or status update
            attachModalEventListeners(loan.application_id, documents, loan);

            // Initialize queue display - check current queue status
            initializeQueueDisplay(loan.application_id);

            // Load suggested remarks for the current pre-approval status and loan type
            const currentStatus = loan.pre_approval_status || 'Pending';
            const loanType = loan.type_name || 'Individual';
            loadRemarksDropdown(currentStatus, loanType);

            // Populate CSRF token in the form
            const csrfTokenElement = document.getElementById('csrfToken');
            const csrfTokenInput = document.querySelector('input[name="csrf_token"]');
            if (csrfTokenElement && csrfTokenInput) {
                csrfTokenInput.value = csrfTokenElement.value;
            }

            // Update queue button visibility based on session data
            updateQueueButtonVisibility(loan.application_id);
        }

        // Function to close the loan details modal
        function closeLoanDetailsModal() {
            const modal = document.getElementById("loanDetailsModal");
            if (modal) {
                modal.classList.remove("show");
            }
        }

        // Function to send queued consolidated email
        function sendQueuedEmail(applicationId) {
            console.log('Sending queued email for application:', applicationId);

            // Get CSRF token from DOM element
            const csrfTokenElement = document.getElementById('csrfToken');
            const csrfToken = csrfTokenElement ? csrfTokenElement.value : '';

            if (!csrfToken) {
                alert('❌ Security token not found. Please refresh the page.');
                return;
            }

            // Get button and disable it
            const btn = document.getElementById(`sendQueuedEmailBtn-${applicationId}`);
            if (!btn) {
                console.error('Send email button not found for application:', applicationId);
                alert('❌ Email button not found. Please refresh the page.');
                return;
            }

            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

            // Prepare form data
            const formData = new FormData();
            formData.append('action', 'send_queued_document_email');
            formData.append('application_id', applicationId);
            formData.append('csrf_token', csrfToken);

            // Send AJAX request
            fetch('admin2_dashboard.php', {
                method: 'POST',
                body: formData
            })
                .then(response => {
                    console.log('Response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Response data:', data);
                    if (data.success) {
                        // Show success modal instead of alert
                        showEmailSuccessModal('✅ Email sent successfully! All queued changes have been sent to the applicant.');

                        // Reset queue display
                        const queueStatus = document.getElementById('queueStatusText');
                        const queueCount = document.getElementById('queueCountText');
                        if (queueStatus) queueStatus.textContent = 'No changes queued';
                        if (queueCount) queueCount.textContent = '';

                        // Hide button
                        btn.style.display = 'none';

                        // Optionally refresh modal to show updated state
                        setTimeout(() => {
                            openLoanDetailsModal(applicationId);
                        }, 1500);
                    } else {
                        alert('❌ Error: ' + (data.message || 'Failed to send email'));
                        if (btn) {
                            btn.disabled = false;
                            btn.innerHTML = originalText;
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('❌ Network error: ' + error.message);
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                    }
                });
        }

        // ENHANCED: Function to send consolidated email with all document changes
        function sendConsolidatedEmail(applicationId) {
            console.log('Sending consolidated email for application:', applicationId);

            // Get CSRF token from DOM element
            const csrfTokenElement = document.getElementById('csrfToken');
            const csrfToken = csrfTokenElement ? csrfTokenElement.value : '';

            if (!csrfToken) {
                alert('❌ Security token not found. Please refresh the page.');
                return;
            }

            // Get button and disable it
            const btn = document.getElementById('sendConsolidatedEmailBtn');
            if (!btn) {
                console.error('Send consolidated email button not found');
                alert('❌ Email button not found. Please refresh the page.');
                return;
            }

            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

            // Prepare form data
            const formData = new FormData();
            formData.append('action', 'send_consolidated_document_email');
            formData.append('application_id', applicationId);
            formData.append('csrf_token', csrfToken);

            // Send AJAX request
            fetch('admin2_dashboard.php', {
                method: 'POST',
                body: formData
            })
                .then(response => {
                    console.log('Response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Response data:', data);
                    if (data.success) {
                        // Show success message
                        const successDiv = document.getElementById('consolidatedEmailSuccess');
                        const successText = document.getElementById('consolidatedEmailSuccessText');

                        if (successDiv && successText) {
                            successText.textContent = data.message || 'Consolidated email sent successfully with all document changes and reasons!';
                            successDiv.style.display = 'block';

                            // Hide success message after 5 seconds
                            setTimeout(() => {
                                successDiv.style.display = 'none';
                            }, 5000);
                        }

                        // Update queue display
                        updateQueueDisplayAfterSend();

                        // Refresh modal to show updated state
                        setTimeout(() => {
                            openLoanDetailsModal(applicationId);
                        }, 2000);
                    } else {
                        alert('❌ Error: ' + (data.message || 'Failed to send consolidated email'));
                        if (btn) {
                            btn.disabled = false;
                            btn.innerHTML = originalText;
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('❌ Network error: ' + error.message);
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                    }
                });
        }

        // Function to toggle queue preview visibility
        function toggleQueuePreview() {
            const preview = document.getElementById('queuePreview');
            const btn = document.getElementById('viewQueueBtn');
            const btnText = document.getElementById('viewQueueBtnText');

            if (preview.style.display === 'none' || preview.style.display === '') {
                preview.style.display = 'block';
                btnText.textContent = 'Hide Changes';
                btn.innerHTML = '<i class="fas fa-eye-slash"></i> ' + btnText.textContent;
            } else {
                preview.style.display = 'none';
                btnText.textContent = 'View Changes';
                btn.innerHTML = '<i class="fas fa-eye"></i> ' + btnText.textContent;
            }
        }

        // Function to update queue display after email is sent
        function updateQueueDisplayAfterSend() {
            const queueInfo = document.getElementById('queueInfoContainer');
            const noQueueMsg = document.getElementById('noQueueMessage');
            const preview = document.getElementById('queuePreview');

            if (queueInfo) queueInfo.style.display = 'none';
            if (noQueueMsg) noQueueMsg.style.display = 'block';
            if (preview) preview.style.display = 'none';
        }

        // Enhanced function to update queue display with preview content
        function updateEnhancedQueueDisplay(applicationId, queueData) {
            const queueInfo = document.getElementById('queueInfoContainer');
            const noQueueMsg = document.getElementById('noQueueMessage');
            const queueCount = document.getElementById('queueCountText');
            const previewContent = document.getElementById('queuePreviewContent');

            if (queueData && queueData.queue_count > 0) {
                // Show queue info
                if (queueInfo) queueInfo.style.display = 'block';
                if (noQueueMsg) noQueueMsg.style.display = 'none';

                // Update count text
                if (queueCount) {
                    const count = queueData.queue_count;
                    queueCount.textContent = `${count} document change${count > 1 ? 's' : ''} ready to send`;
                }

                // Build preview content matching attachment format
                if (previewContent && queueData.changes) {
                    let previewHTML = '';
                    queueData.changes.forEach((change, index) => {
                        // Status styling matching attachment
                        let statusBadge = '';
                        let cardBg = '#f8f9fa';
                        let borderColor = '#dee2e6';

                        if (change.new_status === 'Rejected') {
                            statusBadge = '<span style="display: inline-flex; align-items: center; background: #f8d7da; color: #721c24; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: 600;"><span style="margin-right: 3px; color: #dc3545;">✗</span> Rejected</span>';
                            cardBg = '#fff5f5';
                            borderColor = '#f5c6cb';
                        } else if (change.new_status === 'Approved') {
                            statusBadge = '<span style="display: inline-flex; align-items: center; background: #d4edda; color: #155724; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: 600;"><span style="margin-right: 3px; color: #28a745;">✓</span> Approved</span>';
                            cardBg = '#f8fff9';
                            borderColor = '#c3e6cb';
                        } else {
                            statusBadge = '<span style="display: inline-flex; align-items: center; background: #fff3cd; color: #856404; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: 600;"><span style="margin-right: 3px; color: #ffc107;">⏳</span> Pending</span>';
                            cardBg = '#fffef7';
                            borderColor = '#ffeaa7';
                        }

                        previewHTML += `
                            <div style="margin: 6px 0; padding: 8px; background: ${cardBg}; border: 1px solid ${borderColor}; border-left: 3px solid ${borderColor}; border-radius: 4px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                    <div style="font-weight: 600; color: #495057; font-size: 11px;">${change.document_name}</div>
                                    ${statusBadge}
                                </div>
                                <div style="color: #6c757d; font-size: 9px; margin-bottom: 2px;"><strong>Previous:</strong> ${change.old_status}</div>
                                ${change.rejection_reason ? `<div style="background: #ffffff; padding: 4px 6px; border: 1px solid #e9ecef; border-radius: 2px; margin-top: 4px;"><div style="color: #495057; font-size: 9px; font-weight: 600;">Notes:</div><div style="color: #6c757d; font-size: 9px;">${change.rejection_reason.substring(0, 40)}${change.rejection_reason.length > 40 ? '...' : ''}</div></div>` : ''}
                            </div>`;
                    });
                    previewContent.innerHTML = previewHTML;
                }
            } else {
                // Hide queue info
                if (queueInfo) queueInfo.style.display = 'none';
                if (noQueueMsg) noQueueMsg.style.display = 'block';
            }
        }

        // Function to update queue button visibility (called when modal is loaded)
        // Queue check tracking to prevent multiple simultaneous requests
        let queueCheckInProgress = new Set();

        function updateQueueButtonVisibility(applicationId) {
            console.log('Checking queue status for application:', applicationId);

            // Prevent multiple simultaneous requests for the same application
            if (queueCheckInProgress.has(applicationId)) {
                console.log('⚠️ Queue check already in progress for:', applicationId);
                return;
            }

            queueCheckInProgress.add(applicationId);

            // Updated for enhanced consolidated email interface
            const queueInfoContainer = document.getElementById('queueInfoContainer');
            const noQueueMessage = document.getElementById('noQueueMessage');

            if (!queueInfoContainer) {
                console.warn('Queue info container not found in DOM');
                queueCheckInProgress.delete(applicationId);
                return;
            }

            // Make AJAX request to check queue status
            const csrfTokenElement = document.getElementById('csrfToken');
            const csrfToken = csrfTokenElement ? csrfTokenElement.value : '';

            console.log('🔍 Enhanced Queue Status Debug:', {
                csrfTokenElement: csrfTokenElement ? 'FOUND' : 'NOT FOUND',
                csrfToken: csrfToken ? 'HAS VALUE' : 'EMPTY',
                applicationId: applicationId
            });

            const formData = new FormData();
            formData.append('action', 'check_queue_status');
            formData.append('application_id', applicationId);
            formData.append('csrf_token', csrfToken);

            fetch('admin2_dashboard.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    console.log('Enhanced queue check response:', data);

                    // Remove from progress tracking
                    queueCheckInProgress.delete(applicationId);

                    // Use enhanced display function
                    updateEnhancedQueueDisplay(applicationId, data);
                })
                .catch(error => {
                    console.warn('Could not check queue status:', error);

                    // Remove from progress tracking
                    queueCheckInProgress.delete(applicationId);

                    // If check fails, hide button to be safe
                    if (sendQueueBtn) {
                        sendQueueBtn.style.display = 'none';
                        sendQueueBtn.disabled = true;
                    }
                });
        }

        // Queue Status Checking Functions
        function checkQueueStatus(applicationId) {
            console.log("Checking queue status for application:", applicationId);

            if (!applicationId || typeof applicationId !== 'string' || applicationId.trim() === '') {
                console.error('Invalid application ID provided to checkQueueStatus');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'check_queue_status');
            formData.append('application_id', applicationId.trim());
            formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

            fetch('admin2_dashboard.php', {
                method: 'POST',
                body: formData
            })
                .then(response => {
                    console.log("Queue status check response status:", response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(result => {
                    console.log("Queue status check result:", result);

                    if (result.success && result.queue_status) {
                        updateQueueStatusDisplay(applicationId, result.queue_status);
                        console.log(`Queue status updated for ${applicationId}:`, result.queue_status);
                    } else {
                        console.warn('Queue status check failed:', result.message);
                    }
                })
                .catch(error => {
                    console.error('Error checking queue status:', error);
                });
        }

        // Update Queue Status Display (UI Update)
        function updateQueueStatusDisplay(applicationId, queueStatus) {
            // Update queue indicator if it exists
            const queueIndicator = document.querySelector(`[data-queue-app="${applicationId}"]`);
            if (queueIndicator) {
                const queueCount = queueStatus.queue_count || 0;
                const queueState = queueStatus.queue_status || 'empty';

                if (queueCount > 0) {
                    queueIndicator.innerHTML = `📧 Queue: ${queueCount} changes (${queueState})`;
                    queueIndicator.style.color = '#e74c3c';
                    queueIndicator.style.fontWeight = 'bold';
                } else {
                    queueIndicator.innerHTML = "📧 Queue: Empty";
                    queueIndicator.style.color = '#7f8c8d';
                    queueIndicator.style.fontWeight = 'normal';
                }
            }

            // Update send button state if it exists
            const sendButton = document.querySelector(`[data-send-queue="${applicationId}"]`);
            if (sendButton) {
                if (queueStatus.queue_count > 0) {
                    sendButton.disabled = false;
                    sendButton.style.opacity = '1';
                    sendButton.title = `Send ${queueStatus.queue_count} queued changes`;
                } else {
                    sendButton.disabled = true;
                    sendButton.style.opacity = '0.5';
                    sendButton.title = 'No changes queued';
                }
            }
        }

        // Automatic Queue Refresh (no test button needed)
        function autoRefreshQueueForApp(applicationId) {
            console.log('🔄 Auto-refreshing queue display for app:', applicationId);

            // Check queue status via AJAX
            const formData = new FormData();
            formData.append('action', 'check_queue_status');
            formData.append('application_id', applicationId);
            formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

            fetch('admin2_dashboard.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(result => {
                    if (result.success && result.queue_status) {
                        // Update queue count display
                        const queueElement = document.getElementById(`queueCount-${applicationId}`);
                        if (queueElement) {
                            const count = result.queue_status.queue_count || 0;
                            if (count > 0) {
                                queueElement.innerHTML = `📧 Queue: ${count} changes`;
                                queueElement.style.background = '#e74c3c';
                                queueElement.style.color = 'white';
                                queueElement.style.fontWeight = 'bold';

                                // Enable send button
                                const sendBtn = document.getElementById(`sendQueuedEmailBtn-${applicationId}`);
                                if (sendBtn) {
                                    sendBtn.disabled = false;
                                    sendBtn.style.opacity = '1';
                                }
                            } else {
                                queueElement.innerHTML = '📧 Queue: Empty';
                                queueElement.style.background = '#7f8c8d';
                                queueElement.style.color = 'white';
                                queueElement.style.fontWeight = 'normal';
                            }
                        }

                        console.log('✅ Auto-refresh completed - Queue count:', result.queue_status.queue_count);
                    }
                })
                .catch(error => {
                    console.error('❌ Auto-refresh queue failed:', error);
                });
        }

        // Auto-refresh all visible application queues every 7 seconds
        setInterval(() => {
            const applications = document.querySelectorAll('[data-queue-app]');
            applications.forEach(element => {
                const appId = element.getAttribute('data-queue-app');
                if (appId && document.visibilityState === 'visible') {
                    autoRefreshQueueForApp(appId);
                }
            });
        }, 7000); // Every 7 seconds

        // Auto-check queue status on page load
        document.addEventListener('DOMContentLoaded', function () {
            // Check queue status for all visible applications every 6 seconds
            setInterval(() => {
                const applicationElements = document.querySelectorAll('[data-queue-app]');
                applicationElements.forEach(element => {
                    const appId = element.getAttribute('data-queue-app');
                    if (appId) {
                        checkQueueStatus(appId);
                    }
                });
            }, 30000); // Check every 30 seconds

            // Initial queue status check
            const applicationElements = document.querySelectorAll('[data-queue-app]');
            applicationElements.forEach(element => {
                const appId = element.getAttribute('data-queue-app');
                if (appId) {
                    checkQueueStatus(appId);
                }
            });
        });

        // Ensure functions are globally accessible
        window.openLoanDetailsModal = openLoanDetailsModal;
        window.closeLoanDetailsModal = closeLoanDetailsModal;
        // Make functions globally accessible
        window.sendQueuedEmail = sendQueuedEmail;
        window.checkQueueStatus = checkQueueStatus;
        window.updateQueueStatusDisplay = updateQueueStatusDisplay;
        window.autoRefreshQueueForApp = autoRefreshQueueForApp;

        window.autoRefreshQueueForApp = autoRefreshQueueForApp;
        window.sendConsolidatedEmail = sendConsolidatedEmail;
        window.toggleQueuePreview = toggleQueuePreview;
        window.updateEnhancedQueueDisplay = updateEnhancedQueueDisplay;
        window.updateQueueButtonVisibility = updateQueueButtonVisibility;
        window.showConfirmationModal = showConfirmationModal;
        window.closeConfirmationModal = closeConfirmationModal;
        window.confirmDocumentAction = confirmDocumentAction;
        window.showPreApprovalConfirmModal = showPreApprovalConfirmModal;
        window.closePreApprovalModal = closePreApprovalModal;
        window.confirmPreApprovalAction = confirmPreApprovalAction;

        // Function to update status button styles based on selection
        function updateStatusButtonStyles() {
            const pendingRadio = document.getElementById('status-pending');
            const approvedRadio = document.getElementById('status-approved');
            const rejectedRadio = document.getElementById('status-rejected');

            const pendingLabel = document.querySelector('label[for="status-pending"]');
            const approvedLabel = document.querySelector('label[for="status-approved"]');
            const rejectedLabel = document.querySelector('label[for="status-rejected"]');

            // Reset all to unselected state
            [pendingLabel, approvedLabel, rejectedLabel].forEach(label => {
                if (label) {
                    label.style.transition = 'all 0.3s ease';
                    label.style.boxShadow = 'none';
                }
            });

            // Update selected button
            if (pendingRadio && pendingRadio.checked && pendingLabel) {
                pendingLabel.style.background = '#ffc107';
                pendingLabel.style.color = 'white';
                pendingLabel.style.boxShadow = '0 4px 12px rgba(255, 193, 7, 0.3)';
            } else if (pendingLabel) {
                pendingLabel.style.background = '#fff9e6';
                pendingLabel.style.color = '#f57f17';
            }

            if (approvedRadio && approvedRadio.checked && approvedLabel) {
                approvedLabel.style.background = '#4caf50';
                approvedLabel.style.color = 'white';
                approvedLabel.style.boxShadow = '0 4px 12px rgba(76, 175, 80, 0.3)';
            } else if (approvedLabel) {
                approvedLabel.style.background = '#f1f8f6';
                approvedLabel.style.color = '#2e7d32';
            }

            if (rejectedRadio && rejectedRadio.checked && rejectedLabel) {
                rejectedLabel.style.background = '#f44336';
                rejectedLabel.style.color = 'white';
                rejectedLabel.style.boxShadow = '0 4px 12px rgba(244, 67, 54, 0.3)';
            } else if (rejectedLabel) {
                rejectedLabel.style.background = '#fff5f5';
                rejectedLabel.style.color = '#d32f2f';
            }
        }

        window.updateStatusButtonStyles = updateStatusButtonStyles;

        // ===== Load Suggested Remarks from Dropdown =====
        function loadRemarksDropdown(status, loanType = 'Individual') {
            const select = document.getElementById('decisionReason');
            const customRemarkContainer = document.getElementById('customRemarkContainer');
            const customRemark = document.getElementById('customRemark');
            if (!select) return;

            // Clear any previous selection and custom input
            select.value = '';
            if (customRemarkContainer) {
                customRemarkContainer.style.display = 'none';
            }
            if (customRemark) {
                customRemark.value = '';
            }
            // Reset character counters
            const charCount = document.getElementById('decisionReasonCharCount');
            const customCharCount = document.getElementById('customRemarkCharCount');
            if (charCount) charCount.textContent = '0';
            if (customCharCount) customCharCount.textContent = '0';

            // Show loading state
            select.innerHTML = '<option value="">Loading suggestions...</option>';
            select.disabled = true;

            // Pass both status and loan type to the API
            fetch(`get_loan_remarks.php?status=${encodeURIComponent(status)}&loan_type=${encodeURIComponent(loanType)}`)
                .then(response => response.json())
                .then(data => {
                    select.innerHTML = '<option value="">-- Select a remark to record --</option>';

                    if (data.success && data.remarks && data.remarks.length > 0) {
                        data.remarks.forEach(remark => {
                            const option = document.createElement('option');
                            option.value = remark;
                            // Display: full remark text (truncated in dropdown, full on selection)
                            const shortText = remark.substring(0, 60) + (remark.length > 60 ? '...' : '');
                            option.textContent = shortText;
                            select.appendChild(option);
                        });
                    } else {
                        const option = document.createElement('option');
                        option.disabled = true;
                        option.textContent = 'No suggestions available';
                        select.appendChild(option);
                    }

                    select.disabled = false;
                })
                .catch(error => {
                    console.error('Error loading suggested remarks:', error);
                    select.innerHTML = '<option value="">-- Error loading suggestions --</option>';
                    select.disabled = false;
                });
        }

        function updateCharCount() {
            const decisionReason = document.getElementById('decisionReason');
            const charCountElement = document.getElementById('decisionReasonCharCount');
            if (charCountElement && decisionReason) {
                charCountElement.textContent = decisionReason.value.length;
            }
        }

        function handleDecisionRemarkChange() {
            const decisionReason = document.getElementById('decisionReason');
            const customRemarkContainer = document.getElementById('customRemarkContainer');
            const customRemark = document.getElementById('customRemark');

            if (!decisionReason || !customRemarkContainer) return;

            // Show custom remark input if "Others" is selected
            if (decisionReason.value === 'Others') {
                customRemarkContainer.style.display = 'block';
                if (customRemark) {
                    customRemark.focus();
                    customRemark.removeAttribute('disabled');
                }
            } else {
                customRemarkContainer.style.display = 'none';
                if (customRemark) {
                    customRemark.value = '';
                    customRemark.setAttribute('disabled', 'disabled');
                }
            }
        }

        function updateCustomRemarkCount() {
            const customRemark = document.getElementById('customRemark');
            const charCountElement = document.getElementById('customRemarkCharCount');
            if (charCountElement && customRemark) {
                charCountElement.textContent = customRemark.value.length;
            }
        }

        function showMessageTemplates(status) {
            // Deprecated - remarks are now loaded from database
        }

        function onTemplateSelectChange() {
            // Deprecated - remarks are now loaded from database
            updateCharCount();
        }

        function applyCustomMessage() {
            // Deprecated - remarks are now loaded from database
        }

        function hideMessageTemplates() {
            // Deprecated - remarks are now loaded from database
        }

        // Function to attach event listeners to modal forms (for updates)
        function attachModalEventListeners(applicationId, documents, loan) {
            // Check if all documents are approved
            const allDocumentsApproved =
                documents &&
                documents.length > 0 &&
                documents.every((doc) => doc.status.toLowerCase() === "approved");

            // Handle status radio button selection with live validation
            const statusRadios = document.querySelectorAll('input[name="pre_approval_status"]');
            const loanType = (loan && loan.type_name) ? loan.type_name : 'Individual'; // Store loan type for reuse
            statusRadios.forEach(radio => {
                radio.addEventListener("change", function () {
                    const selectedStatus = this.value;
                    const decisionReasonField = document.getElementById('decisionReason');

                    // Update button styles when selection changes
                    updateStatusButtonStyles();

                    // Load suggested remarks for this status and loan type
                    loadRemarksDropdown(selectedStatus, loanType);

                    // Focus on decision reason when status is selected
                    if ((selectedStatus === "Rejected" || selectedStatus === "Approved") && decisionReasonField) {
                        decisionReasonField.focus();
                    }

                    // Disable Approved if documents not all approved
                    if (selectedStatus === "Approved" && !allDocumentsApproved) {
                        showNotification(
                            "Cannot approve pre-approval status. All documents must be approved first!",
                            "error"
                        );
                        this.checked = false;
                        updateStatusButtonStyles();
                        const documentsSection = document.querySelector(
                            ".modal-section:has(.modal-documents-table)"
                        );
                        if (documentsSection) {
                            documentsSection.style.border = "2px solid #dc2626";
                            setTimeout(() => {
                                documentsSection.style.border = "";
                            }, 3000);
                            documentsSection.scrollIntoView({
                                behavior: "smooth",
                                block: "center",
                            });
                        }
                        return;
                    }

                    // Disable Approved radio if documents not approved
                    const approvedRadio = document.getElementById('status-approved');
                    if (approvedRadio && !allDocumentsApproved) {
                        approvedRadio.disabled = true;
                        const approvedLabel = document.querySelector('label[for="status-approved"]');
                        if (approvedLabel) {
                            approvedLabel.style.opacity = "0.6";
                            approvedLabel.style.cursor = "not-allowed";
                        }
                    }
                });
            });

            // Initialize button styles on modal open
            updateStatusButtonStyles();

            // Character count for decision reasoning (updated - no longer needed as we use oninput event)
            // Removed as we now use oninput="updateCharCount();" directly on textarea

            // Status update form with enhanced validation
            const statusForm = document.getElementById("statusUpdateForm");
            if (statusForm) {
                statusForm.addEventListener("submit", function (e) {
                    e.preventDefault();
                    console.log('📋 Form submit event triggered');

                    // Call the main handler which validates and shows confirmation modal
                    handlePreApprovalSubmit(e);
                });

                // Add global event delegation as backup for form submissions
                document.addEventListener("submit", function (e) {
                    if (e.target.id === "statusUpdateForm") {
                        console.log('📋 Global form submit handler triggered');
                        e.preventDefault();
                        handlePreApprovalSubmit(e);
                    }
                }, true); // Use capture phase to catch before other handlers

                // Document status forms
                const docForms = document.querySelectorAll(".document-status-form");
                docForms.forEach((form) => {
                    form.addEventListener("submit", function (e) {
                        e.preventDefault();
                        const formData = new FormData(this);

                        // Add CSRF token using centralized helper
                        if (!addCSRFTokenToFormData(formData)) {
                            return;
                        }

                        // Show loading overlay
                        showLoadingOverlay("Updating document status...");

                        fetch("admin2_dashboard.php", {
                            method: "POST",
                            body: formData,
                        })
                            .then((response) => {
                                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                                return response.json();
                            })
                            .then((data) => {
                                hideLoadingOverlay();
                                if (data.success) {
                                    showNotification(data.message, "success");
                                    setTimeout(() => {
                                        openLoanDetailsModal(applicationId); // Refresh modal
                                    }, 500);
                                } else {
                                    showNotification("Error: " + data.message, "error");
                                }
                            })
                            .catch((error) => {
                                hideLoadingOverlay();
                                console.error("Error updating document:", error);
                                showNotification(
                                    "Error updating document status. Please try again.",
                                    "error"
                                );
                            });
                    });
                });
            }

        }

        // ============================================
        // SEARCH & FILTER FUNCTIONS
        // ====================================


        function searchApplications() {
            const searchTerm = document.getElementById('filter-name')?.value || '';
            const statusFilter = document.getElementById('filter-loan-status')?.value || '';
            const preApprovalFilter = document.getElementById('filter-pre-approval-status')?.value || '';
            const sortBy = document.querySelector('.loan-table [data-sort]')?.dataset.sort || 'created_at';
            const sortOrder = document.querySelector('.loan-table [data-sort]')?.dataset.sortOrder || 'DESC';

            const formData = new FormData();
            formData.append('action', 'search_applications');

            // Use dynamic CSRF token from DOM instead of static PHP template
            if (!addCSRFTokenToFormData(formData)) {
                showNotification('Security error: Cannot submit request', 'error');
                return;
            }

            formData.append('search', searchTerm);
            formData.append('status_filter', statusFilter);
            formData.append('pre_approval_filter', preApprovalFilter);
            formData.append('sort_by', sortBy);
            formData.append('sort_order', sortOrder);

            fetch('admin2_dashboard.php', {
                method: 'POST',
                body: formData
            })
                .then(response => {
                    if (!response.ok) throw new Error(`HTTP ${response.status}`);
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        updateLoanApplicantsTable(data.data || []);
                        showNotification('Search completed', 'success');
                    } else {
                        showNotification('Search failed: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Search error:', error);
                    showNotification('Search operation failed', 'error');
                });
        }

        function updateLoanApplicantsTable(data) {
            const tbody = document.getElementById('loanApplicantsTableBody');
            if (!tbody) return;

            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" class="no-records">No records found</td></tr>';
                return;
            }

            tbody.innerHTML = data.map(row => `
                <tr data-app-id="${escapeHtml(row.application_id)}">
                    <td data-label="Applicant Name">${escapeHtml(row.first_name + ' ' + row.last_name)}</td>
                    <td data-label="Loan Type">${escapeHtml(row.type_name)}</td>
                    <td data-label="Amount Applied" class="amount-cell">₱${Number(row.amount_applied).toLocaleString('en-PH', { minimumFractionDigits: 2 })}</td>
                    <td data-label="Final Loan Amount" class="amount-cell">
                        ${row.final_loan_amount ? '₱' + Number(row.final_loan_amount).toLocaleString('en-PH', { minimumFractionDigits: 2 }) : 'Not set'}
                    </td>
                    <td data-label="Loan Status"><span class="status-badge badge-${row.status.toLowerCase()}">${escapeHtml(row.status)}</span></td>
                    <td data-label="Pre-Approval Status"><span class="status-badge badge-${row.pre_approval_status.toLowerCase()}">${escapeHtml(row.pre_approval_status)}</span></td>
                    <td data-label="Credit Status"><span class="status-badge badge-${row.credit_investigation_status.toLowerCase()}">${escapeHtml(row.credit_investigation_status)}</span></td>
                    <td data-label="Submission Date">${new Date(row.created_at).toLocaleDateString()}</td>
                    <td>
                        <button class="action-btn view-btn" onclick="openLoanDetailsModal('${escapeHtml(row.application_id)}')">
                            <i class="fas fa-eye"></i> View
                        </button>
                    </td>
                </tr>
            `).join('');
        }

        function filterLoanApplicantsTable() {
            searchApplications();
        }

        function clearAllFilters() {
            document.getElementById('filter-name').value = '';
            document.getElementById('filter-loan-type').value = '';
            document.getElementById('filter-loan-status').value = '';
            document.getElementById('filter-pre-approval-status').value = '';
            document.getElementById('filter-credit-status').value = '';
            searchApplications();
        }

        function clearSearch() {
            document.getElementById('filter-name').value = '';
            searchApplications();
        }

        // ============================================
        // EXPORT FUNCTIONS
        // ============================================

        function exportToCSV() {
            const format = 'csv';
            window.location.href = `admin2_dashboard.php?action=export_applications&format=${format}`;
        }

        function exportToJSON() {
            const format = 'json';
            window.location.href = `admin2_dashboard.php?action=export_applications&format=${format}`;
        }

        // Helper function to escape HTML
        function escapeHtml(text) {
            // Handle non-string values
            if (text === null || text === undefined) return '';
            if (typeof text !== 'string') text = String(text);

            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

        // ============================================
        // AUTO-POLLING & REAL-TIME SYNCHRONIZATION
        // ============================================

        const PollingManager = {
            pollingIntervals: {},
            lastDataHash: {},
            notificationQueue: [],
            isPollingEnabled: true,
            isPaused: false,
            pollInterval: 6000, // 6 seconds default (optimized for 5-8 second range)

            // Initialize polling for a specific data source
            initPoll(dataSource, fetchUrl, updateCallback, pollInterval = 6000) {
                if (this.pollingIntervals[dataSource]) {
                    clearInterval(this.pollingIntervals[dataSource]);
                }

                // Initial fetch
                this.fetchData(dataSource, fetchUrl, updateCallback);

                // Set up polling
                this.pollingIntervals[dataSource] = setInterval(() => {
                    if (this.isPollingEnabled && !this.isPaused) {
                        this.fetchData(dataSource, fetchUrl, updateCallback);
                    }
                }, pollInterval);

                console.log(`✓ Auto-polling enabled for: ${dataSource} (${pollInterval}ms)`);
            },

            // Fetch data and detect changes
            fetchData(dataSource, fetchUrl, updateCallback) {
                fetch(fetchUrl)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        try {
                            const newHash = this.hashData(data);
                            const oldHash = this.lastDataHash[dataSource];

                            // Data changed - update UI and notify
                            if (oldHash !== newHash) {
                                this.lastDataHash[dataSource] = newHash;
                                updateCallback(data);

                                // Show push notification
                                this.sendPushNotification(
                                    `${dataSource} Updated`,
                                    `Real-time data synchronized at ${new Date().toLocaleTimeString()}`
                                );
                            }
                        } catch (hashError) {
                            console.warn(`Hash calculation failed for ${dataSource}, skipping change detection:`, hashError);
                            // Still update the data even if hash fails
                            updateCallback(data);
                        }
                    })
                    .catch(error => console.error(`Polling error for ${dataSource}:`, error));
            },

            // Pause polling (during modal loading)
            pausePolling() {
                this.isPaused = true;
                console.log('⏸️ Polling paused during modal load');
            },

            // Resume polling
            resumePolling() {
                this.isPaused = false;
                console.log('▶️ Polling resumed');
            },

            // Generate hash of data for change detection (handles Unicode characters)
            hashData(data) {
                try {
                    const jsonString = JSON.stringify(data);
                    // Use TextEncoder to handle Unicode characters properly
                    const encoder = new TextEncoder();
                    const encodedData = encoder.encode(jsonString);
                    // Create a simple hash from byte array
                    let hash = 0;
                    for (let i = 0; i < encodedData.length; i++) {
                        const byte = encodedData[i];
                        hash = ((hash << 5) - hash) + byte;
                        hash = hash & hash; // Convert to 32-bit integer
                    }
                    return Math.abs(hash).toString(16).substring(0, 50);
                } catch (e) {
                    // Fallback if TextEncoder fails
                    const jsonString = JSON.stringify(data);
                    let hash = 0;
                    for (let i = 0; i < jsonString.length; i++) {
                        const char = jsonString.charCodeAt(i);
                        hash = ((hash << 5) - hash) + char;
                        hash = hash & hash;
                    }
                    return Math.abs(hash).toString(16).substring(0, 50);
                }
            },

            // Send desktop/browser push notification
            sendPushNotification(title, message) {
                if ('Notification' in window && Notification.permission === 'granted') {
                    new Notification(title, {
                        body: message,
                        icon: 'IMAGE/Main-Logo.png',
                        tag: 'cycloan-update',
                        requireInteraction: false
                    });
                }

                // Also show in-app notification
                this.showInAppNotification(title, message);
            },

            // Show in-app toast notification
            showInAppNotification(title, message) {
                const toast = document.createElement('div');
                toast.className = 'real-time-toast';
                toast.innerHTML = `
                    <div class="toast-content">
                        <i class="fas fa-sync-alt"></i>
                        <div class="toast-text">
                            <strong>${escapeHtml(title)}</strong>
                            <p>${escapeHtml(message)}</p>
                        </div>
                        <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
                    </div>
                `;

                document.body.appendChild(toast);

                // Auto-dismiss after 5 seconds
                setTimeout(() => {
                    if (toast.parentElement) {
                        toast.remove();
                    }
                }, 5000);
            },

            // Stop polling
            stopPoll(dataSource) {
                if (this.pollingIntervals[dataSource]) {
                    clearInterval(this.pollingIntervals[dataSource]);
                    delete this.pollingIntervals[dataSource];
                    console.log(`✗ Auto-polling disabled for: ${dataSource}`);
                }
            },

            // Stop all polling
            stopAllPolls() {
                Object.keys(this.pollingIntervals).forEach(key => this.stopPoll(key));
                this.isPollingEnabled = false;
            },

            // Resume all polling
            resumeAllPolls() {
                this.isPollingEnabled = true;
                console.log('✓ All auto-polling resumed');
            }
        };

        // Callback functions for each data source
        function updateDueAccountsTable(data) {
            try {
                if (!data || !data.success || !data.due_accounts) {
                    console.warn('Invalid due accounts data:', data);
                    return;
                }

                const container = document.getElementById('dueAccountsContainer');
                if (!container) return;

                const accounts = Array.isArray(data.due_accounts) ? data.due_accounts : [];
                if (accounts.length === 0) {
                    container.innerHTML = '<div class="empty-state"><i class="fa-solid fa-circle-check"></i><p>No due accounts found. All payments are on track!</p></div>';
                    return;
                }

                let html = '<div class="scrollable-table"><table class="due-accounts-table"><thead><tr>' +
                    '<th scope="col" data-sort="due_date">Due Date <span class="sort-icon"></span></th>' +
                    '<th scope="col" data-sort="account_holder">Account Holder <span class="sort-icon"></span></th>' +
                    '<th scope="col" data-sort="amount">Amount <span class="sort-icon"></span></th>' +
                    '<th scope="col">Actions</th>' +
                    '</tr></thead><tbody>';

                accounts.forEach(account => {
                    try {
                        // Safely parse date
                        let dueDateStr = '';
                        try {
                            const dueDate = new Date(account.due_date);
                            if (isNaN(dueDate.getTime())) {
                                dueDateStr = account.due_date || 'N/A';
                            } else {
                                dueDateStr = dueDate.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
                            }
                        } catch (e) {
                            dueDateStr = account.due_date || 'N/A';
                        }

                        const dueDate = new Date(account.due_date);
                        const daysUntilDue = isNaN(dueDate.getTime()) ? 0 : Math.floor((dueDate - new Date()) / (60 * 60 * 24 * 1000));
                        const urgencyClass = daysUntilDue <= 7 ? 'urgent' : (daysUntilDue <= 15 ? 'warning' : 'normal');

                        const accountHolder = `${escapeHtml((account.first_name || '') + ' ' + (account.last_name || ''))}`;
                        const applicationId = escapeHtml(account.application_id || '');

                        html += `<tr>
                            <td data-label="Due Date"><span class="date-badge ${urgencyClass}">${escapeHtml(dueDateStr)}</span></td>
                            <td data-label="Account Holder">
                                <div>${accountHolder}</div>
                                <small style="color: #666;">Loan ID: ${escapeHtml(account.loan_id || 'N/A')}</small>
                            </td>
                            <td data-label="Amount" class="amount-cell">
                                <span class="amount-badge">₱${Number(account.amount || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 })}</span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="action-btn view-btn" onclick="viewPaymentDetails('${account.payment_id}', '${account.application_id}')" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="action-btn email-btn" onclick="sendPaymentReminder('${account.payment_id}', '${account.loan_id}', '${accountHolder}')" title="Send Reminder Email">
                                        <i class="fas fa-envelope"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>`;
                    } catch (e) {
                        console.error('Error rendering account row:', e, account);
                    }
                });

                html += '</tbody></table></div>';
                container.innerHTML = html;
            } catch (error) {
                console.error('Error updating due accounts:', error, data);
            }
        }

        function updateActivityLogsTable(data) {
            console.log('🔄 updateActivityLogsTable called with data:', data);

            try {
                if (!data || !data.success || !data.activity_logs) {
                    console.warn('❌ Invalid activity logs data:', data);
                    return;
                }

                const tbody = document.querySelector('#activityLogsTableBody');
                if (!tbody) return;

                const logs = Array.isArray(data.activity_logs) ? data.activity_logs : [];
                if (logs.length === 0) {
                    const container = document.getElementById('activityLogsContainer');
                    if (container) {
                        container.innerHTML = '<p class="no-activity-logs"><i class="fa-solid fa-circle-exclamation"></i> No activity logs found.</p>';
                    }
                    return;
                }

                tbody.innerHTML = logs.map(log => {
                    try {
                        let dateTimeStr = '';
                        try {
                            const createdAt = new Date(log.created_at);
                            if (isNaN(createdAt.getTime())) {
                                dateTimeStr = log.created_at || 'N/A';
                            } else {
                                dateTimeStr = createdAt.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) +
                                    ' ' + createdAt.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                            }
                        } catch (e) {
                            dateTimeStr = log.created_at || 'N/A';
                        }

                        const userRole = log.user_role || 'Unknown';
                        const actionType = log.action_type || 'N/A';
                        const userFullName = escapeHtml((log.first_name || '') + ' ' + (log.last_name || ''));
                        const module = escapeHtml(log.module_display || log.module || 'N/A');
                        const description = escapeHtml(log.description || 'N/A');

                        return `<tr>
                            <td data-label="Date & Time">${escapeHtml(dateTimeStr)}</td>
                            <td data-label="User">${userFullName}</td>
                            <td data-label="Role" class="status-${userRole.toLowerCase()}">
                                <span class="status-badge badge-${userRole.toLowerCase()}">${escapeHtml(userRole)}</span>
                            </td>
                            <td data-label="Action" class="status-${actionType.toLowerCase()}">
                                <span class="status-badge badge-${actionType.toLowerCase()}">${escapeHtml(actionType)}</span>
                            </td>
                            <td data-label="Module">${module}</td>
                            <td data-label="Description">${description}</td>
                        </tr>`;
                    } catch (e) {
                        console.error('Error rendering log row:', e, log);
                        return '';
                    }
                }).join('');
            } catch (error) {
                console.error('Error updating activity logs:', error, data);
            }
        }

        function updateLoanApplicantsTable(data) {
            try {
                if (!data || !data.success || !data.loan_applicants) {
                    console.warn('Invalid loan applicants data:', data);
                    return;
                }

                const tbody = document.getElementById('loanApplicantsTableBody');
                if (!tbody) return;

                const applicants = Array.isArray(data.loan_applicants) ? data.loan_applicants : [];
                if (applicants.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="9" class="no-records">No records found</td></tr>';
                    return;
                }

                tbody.innerHTML = applicants.map(row => {
                    try {
                        let submissionDateStr = '';
                        try {
                            const createdAt = new Date(row.created_at);
                            if (isNaN(createdAt.getTime())) {
                                submissionDateStr = row.created_at || 'N/A';
                            } else {
                                submissionDateStr = createdAt.toLocaleDateString();
                            }
                        } catch (e) {
                            submissionDateStr = row.created_at || 'N/A';
                        }

                        return `<tr data-app-id="${escapeHtml(row.application_id || '')}">
                            <td data-label="Applicant Name">${escapeHtml((row.first_name || '') + ' ' + (row.last_name || ''))}</td>
                            <td data-label="Loan Type">${escapeHtml(row.type_name || 'N/A')}</td>
                            <td data-label="Amount Applied" class="amount-cell">₱${Number(row.amount_applied || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 })}</td>
                            <td data-label="Final Loan Amount" class="amount-cell">
                                ${row.final_loan_amount ? '₱' + Number(row.final_loan_amount).toLocaleString('en-PH', { minimumFractionDigits: 2 }) : 'Not set'}
                            </td>
                            <td data-label="Loan Status"><span class="status-badge badge-${(row.status || 'pending').toLowerCase()}">${escapeHtml(row.status || 'Pending')}</span></td>
                            <td data-label="Pre-Approval Status"><span class="status-badge badge-${(row.pre_approval_status || 'pending').toLowerCase()}">${escapeHtml(row.pre_approval_status || 'Pending')}</span></td>
                            <td data-label="Credit Status"><span class="status-badge badge-${(row.credit_investigation_status || 'pending').toLowerCase()}">${escapeHtml(row.credit_investigation_status || 'Pending')}</span></td>
                            <td data-label="Submission Date">${escapeHtml(submissionDateStr)}</td>
                            <td>
                                <button class="action-btn view-btn" onclick="openLoanDetailsModal('${escapeHtml(row.application_id || '')}')" style="cursor: pointer;">
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </td>
                        </tr>`;
                    } catch (e) {
                        console.error('Error rendering applicant row:', e, row);
                        return '';
                    }
                }).join('');
            } catch (error) {
                console.error('Error updating loan applicants:', error, data);
            }
        }

        // Request notification permission on page load
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }

        // Initialize after DOM is ready

        function initializeDashboard() {
            // Chart initialization code
            if (document.getElementById('loanTypeChart')) {
                const loanTypeData = <?php echo json_encode($loanTypeData); ?>;
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
                                const chartContainer = document.querySelector('.chart-container');
                                if (chartContainer) chartContainer.classList.add('loaded');
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
                                    },
                                    padding: 8
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
                                        family: 'Poppins, sans-serif',
                                        weight: '500'
                                    },
                                    padding: 8
                                },
                                grid: {
                                    display: false
                                }
                            }
                        },
                        interaction: {
                            mode: 'index',
                            intersect: false
                        }
                    }
                });
            }

            // ===== Setup Modal Backdrop Click Handlers =====

            // Loan Details Modal
            const loanDetailsModal = document.getElementById('loanDetailsModal');
            if (loanDetailsModal) {
                loanDetailsModal.addEventListener('click', function (event) {
                    // Only close if clicking on the backdrop (modal itself), not modal-content
                    if (event.target === this) {
                        closeLoanDetailsModal();
                    }
                });
            }

            // Confirmation Modal
            const confirmationModal = document.getElementById('confirmationModal');
            if (confirmationModal) {
                confirmationModal.addEventListener('click', function (event) {
                    if (event.target === this) {
                        closeConfirmationModal();
                    }
                });
            }

            // Pre-Approval Modal
            const preApprovalModal = document.getElementById('preApprovalConfirmModal');
            if (preApprovalModal) {
                preApprovalModal.addEventListener('click', function (event) {
                    if (event.target === this) {
                        closePreApprovalModal();
                    }
                });
            }

            // Payment Reminder Modal
            const paymentReminderModal = document.getElementById('paymentReminderModal');
            if (paymentReminderModal) {
                paymentReminderModal.addEventListener('click', function (event) {
                    if (event.target === this) {
                        closePaymentReminderModal();
                    }
                });
            }

            // ===== Setup Close Button Handlers =====

            // Loan Details close button
            const loanDetailsCloseBtn = loanDetailsModal ? loanDetailsModal.querySelector('.close') : null;
            if (loanDetailsCloseBtn) {
                loanDetailsCloseBtn.addEventListener('click', closeLoanDetailsModal);
            }

            // Confirmation modal close button
            const confirmationCloseBtn = confirmationModal ? confirmationModal.querySelector('.close') : null;
            if (confirmationCloseBtn) {
                confirmationCloseBtn.addEventListener('click', closeConfirmationModal);
            }

            // Pre-Approval modal close button
            const preApprovalCloseBtn = preApprovalModal ? preApprovalModal.querySelector('.close') : null;
            if (preApprovalCloseBtn) {
                preApprovalCloseBtn.addEventListener('click', closePreApprovalModal);
            }

            // ===== Setup Keyboard Close Handler (ESC key) =====

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    if (loanDetailsModal && loanDetailsModal.classList.contains('show')) {
                        closeLoanDetailsModal();
                    }
                    if (confirmationModal && confirmationModal.classList.contains('show')) {
                        closeConfirmationModal();
                    }
                    if (preApprovalModal && preApprovalModal.classList.contains('show')) {
                        closePreApprovalModal();
                    }
                    if (paymentReminderModal && paymentReminderModal.classList.contains('show')) {
                        closePaymentReminderModal();
                    }
                }
            });
        }

        // Run initialization when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializeDashboard);
        } else {
            initializeDashboard();
        }

        // ============================================
        // SERVER-SENT EVENTS (SSE) NOTIFICATION SYSTEM
        // ============================================

        let sseEventSource = null;
        let sseLastNotificationId = 0;
        let notificationBadgeCount = 0;
        let sseConnectionAttempts = 0;
        const maxSSEReconnectAttempts = 5;

        // ============================================
        // START AUTO-POLLING FOR REAL-TIME UPDATES
        // ============================================
        document.addEventListener('DOMContentLoaded', function () {
            console.log('🚀 DOM loaded, initializing polling in 1 second...');

            setTimeout(() => {
                console.log('🔄 Starting PollingManager initialization...');

                // Poll Due Accounts every 5 seconds for real-time updates
                PollingManager.initPoll(
                    'DueAccounts',
                    'admin2_dashboard.php?action=get_due_accounts',
                    updateDueAccountsTable,
                    5000
                );

                // Poll Activity Logs every 5 seconds for real-time updates
                PollingManager.initPoll(
                    'ActivityLogs',
                    'admin2_dashboard.php?action=get_activity_logs',
                    updateActivityLogsTable,
                    5000
                );

                // Poll Loan Applicants every 8 seconds for real-time updates
                PollingManager.initPoll(
                    'LoanApplicants',
                    'admin2_dashboard.php?action=get_loan_applicants',
                    updateLoanApplicantsTable,
                    8000
                );

                console.log('✅ Real-time synchronization started (Auto-Polling + SSE) - 5-8 second intervals');
            }, 1000); // Delay 1 second to ensure DOM is fully ready
        });





        // UI utility functions
        function toggleDropdown() {
            document.getElementById('dropdown').classList.toggle('show');
        }

        function toggleSidebar() {
            document.querySelector('nav').classList.toggle('active');
            document.querySelector('.burger').classList.toggle('active');
        }

        // Close dropdown when clicking outside
        window.onclick = function (event) {
            if (!event.target.closest('.profile-container')) {
                document.getElementById('dropdown').classList.remove('show');
            }
        };

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

        // Note: Using inline JavaScript functions only - external admin2_dashboard.js disabled
        // All functionality is handled by inline functions and auto-polling system

        // ===== Due Accounts Helper Functions =====
        window.viewPaymentDetails = function (paymentId, applicationId) {
            try {
                const modal = document.getElementById('paymentDetailsModal');
                const content = document.getElementById('paymentDetailsContent');

                if (!modal || !content) {
                    console.error('Payment details modal elements not found');
                    return;
                }

                // Show modal with loading state
                modal.style.zIndex = '10000';
                modal.classList.add('show');
                content.innerHTML = `<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin" style="font-size: 24px; color: #2d7d32; margin-right: 10px;"></i> Loading payment details...</div>`;

                fetch(`admin2_dashboard.php?action=get_payment_details&payment_id=${encodeURIComponent(paymentId)}`)
                    .then(response => {
                        if (!response.ok) throw new Error(`HTTP ${response.status}`);
                        return response.json();
                    })
                    .then(data => {
                        if (!data.success) {
                            content.innerHTML = `<p class="error">Error: ${data.message}</p>`;
                            return;
                        }

                        // Format amount and date
                        const formattedAmount = parseFloat(data.amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        const formattedDate = new Date(data.due_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
                        const statusColor = data.status.toLowerCase() === 'pending' ? 'warning' : (data.status.toLowerCase() === 'paid' ? 'success' : 'danger');

                        content.innerHTML = `
                            <div class="modal-section">
                                <h3><i class="fas fa-receipt"></i> Payment Information</h3>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">
                                    <div>
                                        <div style="margin-bottom: 15px;">
                                            <strong style="color: #666; font-size: 12px; display: block; margin-bottom: 4px;">Loan ID</strong>
                                            <div style="font-size: 16px; color: #2d7d32;">${data.loan_id}</div>
                                        </div>
                                        <div style="margin-bottom: 15px;">
                                            <strong style="color: #666; font-size: 12px; display: block; margin-bottom: 4px;">Account Holder</strong>
                                            <div style="font-size: 16px; color: #333;">${data.account_holder}</div>
                                        </div>
                                    </div>
                                    <div>
                                        <div style="margin-bottom: 15px;">
                                            <strong style="color: #666; font-size: 12px; display: block; margin-bottom: 4px;">Amount</strong>
                                            <div style="font-size: 16px; color: #1b5e20; font-weight: 600;">₱${formattedAmount}</div>
                                        </div>
                                        <div style="margin-bottom: 15px;">
                                            <strong style="color: #666; font-size: 12px; display: block; margin-bottom: 4px;">Due Date</strong>
                                            <div style="font-size: 16px; color: #333;">${formattedDate}</div>
                                    </div>
                                        <div style="margin-bott                                om: 15px;">
                                            <strong style="color: #666; font-size: 12px; display: block; margin-bottom: 4px;">Status</strong>
                                            <span class="status-badge status-${statusColor}" style="font-weight: 600;">${data.status}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    })
                    .catch(error => {
                        console.error('Error fetching payment details:', error);
                        content.innerHTML = `<p class="error">Failed to load payment details: ${error.message}</p>`;
                    });
            } catch (error) {
                console.error('Error:', error);
            }
        };

        // Close payment details modal
        window.closePaymentDetailsModal = function () {
            const modal = document.getElementById('paymentDetailsModal');
            if (modal) {
                modal.classList.remove('show');
                modal.style.zIndex = '';
            }
        };

        window.sendPaymentReminder = function (paymentId, loanId, accountHolder) {
            try {
                // Show confirmation modal instead of browser confirm
                const modal = document.getElementById('paymentReminderModal');
                if (!modal) {
                    console.error('Payment reminder modal not found');
                    return;
                }

                // Set modal details
                document.getElementById('reminderAccountHolder').textContent = accountHolder;
                document.getElementById('reminderPaymentId').textContent = loanId;  // Display loan_id instead of payment_id

                // Set up confirm button handler
                const confirmBtn = document.getElementById('reminderConfirmBtn');
                confirmBtn.onclick = function () {
                    closePaymentReminderModal();
                    performSendReminder(paymentId);
                };

                // Show modal
                modal.classList.add('show');
            } catch (error) {
                console.error('Error:', error);
            }
        };

        window.closePaymentReminderModal = function () {
            const modal = document.getElementById('paymentReminderModal');
            if (modal) {
                modal.classList.remove('show');
            }
        };

        function performSendReminder(paymentId) {
            try {
                showLoadingOverlay('Sending reminder email...');
                const formData = new FormData();
                formData.append('action', 'send_payment_reminder');
                formData.append('payment_id', paymentId);

                // Add CSRF token using centralized helper
                if (!addCSRFTokenToFormData(formData)) {
                    hideLoadingOverlay();
                    return;
                }

                fetch('admin2_dashboard.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => {
                        if (!response.ok) throw new Error(`HTTP ${response.status}`);
                        return response.json();
                    })
                    .then(data => {
                        hideLoadingOverlay();
                        if (data.success) {
                            showNotification('Reminder email sent successfully!', 'success');
                        } else {
                            showNotification('Failed to send reminder: ' + data.message, 'error');
                        }
                    })
                    .catch(error => {
                        hideLoadingOverlay();
                        console.error('Error sending reminder:', error);
                        showNotification('Error sending reminder email', 'error');
                    });
            } catch (error) {
                hideLoadingOverlay();
                console.error('Error:', error);
            }
        }


        // Search and filter functions handled by inline JavaScript below

        // ===== Interest Rate Modal Functions =====

        function openInterestRateModal() {
            const modal = document.getElementById('manageInterestRateModal');
            const ratesGrid = document.getElementById("ratesGrid");
            const historyTable = document.getElementById("interestRateHistoryTable");
            const historyLoading = document.getElementById("historyLoading");

            // Check if required elements exist
            if (!modal || !ratesGrid || !historyTable) {
                console.error("Missing required elements for interest rate modal");
                return;
            }

            // Clear and show loading
            ratesGrid.innerHTML = '<div class="rate-card-skeleton"><div class="spinner"></div><p>Loading rates...</p></div>';
            historyTable.innerHTML = '<tr><td colspan="5"><div class="spinner"></div></td></tr>';
            if (historyLoading) historyLoading.style.display = "block";

            modal.style.display = "block";
            setTimeout(() => modal.classList.add("show"), 10);

            // Load current interest rates from API
            fetch("interest_rate_api.php?action=get_all_interest_rates", { cache: "no-store" })
                .then((response) => response.json())
                .then((data) => {
                    if (data.success) {
                        updateInterestRateStats(data.rates);
                        if (data.rates.length > 0) {
                            ratesGrid.innerHTML = data.rates.map((item) => {
                                const updatedDate = new Date(item.updated_at);
                                const formattedDate = updatedDate.toLocaleDateString("en-US", {
                                    month: "short", day: "numeric", year: "numeric"
                                });
                                return `
                                    <div class="rate-card">
                                        <div class="term-badge">${item.term_length} Months</div>
                                        <div class="rate-display">
                                            ${parseFloat(item.interest_rate).toFixed(2)}
                                            <span class="percent-sign">%</span>
                                        </div>
                                        <div class="updated-info">Updated: ${formattedDate}</div>
                                    </div>
                                `;
                            }).join("");
                        } else {
                            ratesGrid.innerHTML = '<div class="rate-card-skeleton"><i class="fas fa-info-circle" style="font-size: 2rem; color: #9ca3af; margin-bottom: 10px;"></i><p>No rates configured yet.</p></div>';
                        }
                    } else {
                        ratesGrid.innerHTML = `<div class="rate-card-skeleton"><i class="fas fa-exclamation-triangle" style="font-size: 2rem; color: #ef4444; margin-bottom: 10px;"></i><p>Error: ${data.message}</p></div>`;
                    }
                }).catch((error) => {
                    ratesGrid.innerHTML = `<div class="rate-card-skeleton"><i class="fas fa-exclamation-triangle" style="font-size: 2rem; color: #ef4444; margin-bottom: 10px;"></i><p>Error loading rates: ${error.message}</p></div>`;
                    console.error("Fetch error:", error);
                });

            // Load interest rate history from API
            fetch("interest_rate_api.php?action=get_interest_rate_history", { cache: "no-store" })
                .then((response) => response.json())
                .then((data) => {
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

        function closeInterestRateModal() {
            const modal = document.getElementById('manageInterestRateModal');
            if (modal) {
                modal.classList.remove('show');
                setTimeout(() => (modal.style.display = "none"), 300);
                console.log('Interest Rate Modal closed');
            }
        }

        // Load Interest Rate Data
        function loadInterestRateData() {
            // Fetch current interest rates
            fetch('admin2_dashboard.php?action=get_interest_rates')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayRates(data.rates);
                        displayRateStats(data.stats);
                        displayRateHistory(data.history);
                    }
                })
                .catch(error => {
                    console.error('Error loading interest rates:', error);
                    document.getElementById('ratesGrid').innerHTML = '<div style="padding: 20px; text-align: center; color: #d32f2f;"><i class="fas fa-exclamation-circle"></i> Error loading rates</div>';
                });
        }

        function displayRates(rates) {
            const ratesGrid = document.getElementById('ratesGrid');
            if (!rates || rates.length === 0) {
                ratesGrid.innerHTML = '<div style="padding: 20px; text-align: center; color: #999;">No rates configured</div>';
                return;
            }

            let html = '';
            rates.forEach(rate => {
                const termLabel = rate.term_length + ' Months';
                html += `
                    <div style="background: linear-gradient(135deg, #1b5e20 0%, #2d7d32 100%); color: white; padding: 16px; border-radius: 8px; text-align: center; box-shadow: 0 2px 6px rgba(0,0,0,0.12);">
                        <p style="margin: 0 0 8px 0; font-size: 12px; opacity: 0.9; font-weight: 600;">Term Length</p>
                        <p style="margin: 0 0 12px 0; font-size: 20px; font-weight: 700;">${termLabel}</p>
                        <div style="border-top: 1px solid rgba(255,255,255,0.3); padding-top: 10px;">
                            <p style="margin: 0 0 4px 0; font-size: 11px; opacity: 0.85;">Interest Rate</p>
                            <p style="margin: 0; font-size: 24px; font-weight: 700; color: #fbc02d;">${rate.rate}%</p>
                        </div>
                    </div>
                `;
            });

            ratesGrid.innerHTML = html;
        }

        function displayRateStats(stats) {
            if (!stats) return;

            const termCount = document.getElementById('configuredTermsCount');
            const avgRate = document.getElementById('averageRateValue');
            const lastUpdated = document.getElementById('lastUpdatedValue');

            if (termCount) termCount.textContent = stats.term_count || 0;
            if (avgRate) avgRate.textContent = (stats.average_rate || 0).toFixed(2) + '%';
            if (lastUpdated) lastUpdated.textContent = stats.last_updated || 'N/A';
        }

        function displayRateHistory(history) {
            const historyTable = document.getElementById('interestRateHistoryTable');
            if (!history || history.length === 0) {
                historyTable.innerHTML = '<tr><td colspan="5" style="padding: 20px; text-align: center; color: #999;">No history available</td></tr>';
                return;
            }

            let html = '';
            history.forEach((item, index) => {
                const updatedByName = item.updated_by || 'Admin';
                html += `
                    <tr>
                        <td style="padding: 12px; border-bottom: 1px solid #e8e8e8;">${index + 1}</td>
                        <td style="padding: 12px; border-bottom: 1px solid #e8e8e8;">${item.term_length} Months</td>
                        <td style="padding: 12px; border-bottom: 1px solid #e8e8e8;"><strong>${item.rate}%</strong></td>
                        <td style="padding: 12px; border-bottom: 1px solid #e8e8e8;">${item.updated_at}</td>
                        <td style="padding: 12px; border-bottom: 1px solid #e8e8e8;">${updatedByName}</td>
                    </tr>
                `;
            });

            historyTable.innerHTML = html;
        }

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

            // Update stat displays - match IDs from HTML
            const configElement = document.getElementById("configuredTermsCount");
            const avgRateElement = document.getElementById("averageRateValue");
            const lastUpdatedElement = document.getElementById("lastUpdatedValue");

            if (configElement)
                configElement.textContent = `${configuredTerms}/${totalTerms}`;
            if (avgRateElement) avgRateElement.textContent = avgRate + "%";
            if (lastUpdatedElement) lastUpdatedElement.textContent = lastUpdated;
        }

        // Export to global scope for backward compatibility
        window.openInterestRateModal = openInterestRateModal;
        window.closeInterestRateModal = closeInterestRateModal;
        window.openManageInterestRateModal = openInterestRateModal;
        window.closeManageInterestRateModal = closeInterestRateModal;
        window.updateInterestRateStats = updateInterestRateStats;

        // Setup Interest Rate Modal Event Listeners (Updated for Admin1 compatibility)
        document.addEventListener('DOMContentLoaded', function () {
            const interestRateModal = document.getElementById('manageInterestRateModal');
            const viewInterestRatesBtn = document.getElementById('viewInterestRatesBtn');
            const closeInterestRateBtn = document.getElementById('closeInterestRateModal');

            // Open modal when button is clicked
            if (viewInterestRatesBtn) {
                viewInterestRatesBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    openInterestRateModal();
                });
            }

            // Close modal when close button is clicked
            if (closeInterestRateBtn) {
                closeInterestRateBtn.addEventListener('click', closeInterestRateModal);
            }

            // Close modal when backdrop is clicked
            if (interestRateModal) {
                interestRateModal.addEventListener('click', function (event) {
                    if (event.target === this) {
                        closeInterestRateModal();
                    }
                });
            }

            // Close modal on ESC key
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && interestRateModal && interestRateModal.classList.contains('show')) {
                    closeInterestRateModal();
                }
            });
        });

    </script>

    <!-- Interest Rate Management Modal -->
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

                <!-- Interest Rate History Section -->
                <div
                    style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.08);">
                    <h3
                        style="margin: 0 0 15px 0; color: #1b5e20; font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-history"></i> Change History
                    </h3>
                    <div style="overflow-x: auto; max-height: 400px; overflow-y: auto;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                            <thead>
                                <tr
                                    style="background: #f5f5f5; border-bottom: 2px solid #e0e0e0; position: sticky; top: 0; z-index: 10;">
                                    <th
                                        style="padding: 12px; text-align: left; font-weight: 700; color: #1b5e20; white-space: nowrap;">
                                        #</th>
                                    <th
                                        style="padding: 12px; text-align: left; font-weight: 700; color: #1b5e20; white-space: nowrap;">
                                        Term Length</th>
                                    <th
                                        style="padding: 12px; text-align: left; font-weight: 700; color: #1b5e20; white-space: nowrap;">
                                        Interest Rate</th>
                                    <th
                                        style="padding: 12px; text-align: left; font-weight: 700; color: #1b5e20; white-space: nowrap;">
                                        Updated At</th>
                                    <th
                                        style="padding: 12px; text-align: left; font-weight: 700; color: #1b5e20; white-space: nowrap;">
                                        Updated By</th>
                                </tr>
                            </thead>
                            <tbody id="interestRateHistoryTable">
                                <tr>
                                    <td colspan="5" style="padding: 30px; text-align: center; color: #999;">
                                        <i class="fas fa-spinner fa-spin"
                                            style="font-size: 20px; margin-right: 8px;"></i> Loading history...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <style>
                        #interestRateHistoryTable tr {
                            border-bottom: 1px solid #e8e8e8;
                            transition: background-color 0.2s ease;
                        }

                        #interestRateHistoryTable tr:hover {
                            background-color: #f9f9f9;
                        }

                        #interestRateHistoryTable td {
                            padding: 12px;
                            color: #333;
                        }

                        #interestRateHistoryTable tr:last-child {
                            border-bottom: none;
                        }
                    </style>
                </div>
            </div>
        </div>
    </div>

    <!-- External scripts disabled - using inline real-time polling and functions instead -->
    <script src="JAVASCRIPT/Real-Time.js?v=<?php echo time(); ?>"></script>
    <!-- admin2_dashboard.js disabled - conflicts with inline confirmation modal functions -->
    <!-- <script src="JAVASCRIPT/admin2_dashboard.js?v=<?php echo time(); ?>"></script> -->

    <!-- External JavaScript disabled to prevent conflicts with inline functions -->
    <script>
        console.log('✅ Using inline JavaScript functions only - external admin2_dashboard.js disabled');
    </script>

    <script>
        // ===== NOTIFICATION SYSTEM =====
        let notificationCheckInterval;
        const adminId = <?= intval($_SESSION['user_id']) ?>;

        // Initialize notification system
        document.addEventListener('DOMContentLoaded', function () {
            initializeNotificationSystem();
        });

        function initializeNotificationSystem() {
            const bellElement = document.querySelector('.notification-bell');
            if (bellElement) {
                bellElement.addEventListener('click', toggleNotificationDropdown);
            }

            // Check for notifications every 6 seconds for better responsiveness
            checkNotifications();
            notificationCheckInterval = setInterval(checkNotifications, 6000);

            // Close dropdown when clicking outside
            document.addEventListener('click', function (event) {
                const wrapper = document.querySelector('.notification-wrapper');
                if (wrapper && !wrapper.contains(event.target)) {
                    closeNotificationDropdown();
                }
            });
        }

        function toggleNotificationDropdown(e) {
            e.preventDefault();
            const dropdown = document.getElementById('notificationDropdown');
            if (dropdown) {
                if (dropdown.style.display === 'none' || !dropdown.style.display) {
                    dropdown.style.display = 'block';
                    loadNotifications();
                } else {
                    dropdown.style.display = 'none';
                }
            }
        }

        function closeNotificationDropdown() {
            const dropdown = document.getElementById('notificationDropdown');
            if (dropdown) {
                dropdown.style.display = 'none';
            }
        }

        function checkNotifications() {
            fetch('notifications_enhanced.php?action=get_unread_count&user_id=' + adminId)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.unread_count > 0) {
                        const badge = document.getElementById('notificationBadge');
                        if (badge) {
                            badge.textContent = data.unread_count;
                            badge.style.display = 'flex';
                        }
                    } else {
                        const badge = document.getElementById('notificationBadge');
                        if (badge) {
                            badge.style.display = 'none';
                        }
                    }
                })
                .catch(error => console.error('Error checking notifications:', error));
        }

        function loadNotifications() {
            const listElement = document.getElementById('notificationList');
            if (!listElement) return;

            fetch('notifications_enhanced.php?action=get_recent_notifications&user_id=' + adminId + '&limit=5')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.notifications.length > 0) {
                        let html = '';
                        data.notifications.forEach(notif => {
                            const date = new Date(notif.created_at);
                            const timeAgo = getTimeAgo(date);
                            const priorityClass = notif.priority === 'high' ? 'high' : '';

                            html += `
                                <div class="notification-item ${priorityClass}">
                                    <div class="notification-item-title">${escapeHtml(notif.title)}</div>
                                    <div class="notification-item-message">${escapeHtml(notif.message)}</div>
                                    <div class="notification-item-time">${timeAgo}</div>
                                </div>
                            `;
                        });
                        listElement.innerHTML = html;
                    } else {
                        listElement.innerHTML = '<div class="notification-empty">No new notifications</div>';
                    }
                })
                .catch(error => {
                    console.error('Error loading notifications:', error);
                    listElement.innerHTML = '<div class="notification-empty">Error loading notifications</div>';
                });
        }

        function getTimeAgo(date) {
            const now = new Date();
            const diff = Math.floor((now - date) / 1000);

            if (diff < 60) return 'just now';
            if (diff < 3600) return Math.floor(diff / 60) + ' minutes ago';
            if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
            return Math.floor(diff / 86400) + ' days ago';
        }

        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

        // Cleanup on page unload
        window.addEventListener('beforeunload', function () {
            if (notificationCheckInterval) {
                clearInterval(notificationCheckInterval);
            }

        });
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
    </script>
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
<?php ob_end_flush(); ?>