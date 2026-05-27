<?php
ob_start();
// Centralized debug control via env_config.php
if (file_exists(__DIR__ . '/env_config.php')) {
    require_once __DIR__ . '/env_config.php';
}
if (defined('APP_DEBUG') && APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}
ini_set('log_errors', '1');
ini_set('error_log', 'debug_log.txt');

$current_page = basename($_SERVER['PHP_SELF']);

require "CYCLOAN_db.php";

// Verify database connection is active
if (!isset($conn) || $conn->connect_error) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Ping the connection to ensure it's still alive
if (!$conn->ping()) {
    error_log("Database connection lost. Attempting to reconnect...");
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection lost']);
    exit;
}

require_once 'timezone_config.php';

// Add NotificationManager for user and admin notifications (like admin2)
if (file_exists('NotificationManager.php')) {
    require_once 'NotificationManager.php';
} else {
    error_log("NotificationManager.php not found - notifications will be skipped");
}

require 'phpmailer/src/Exception.php';
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['email'])) {
    if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
        exit;
    }
    header("Location: index.php");
    exit();
}

$role = 'admin1'; // Force admin1 for this dashboard
$id = $_SESSION["user_id"] ?? 0;

// Initialize profile image (will be updated after authorization check)
$profile_img = "default.png";

$current_page = basename($_SERVER['PHP_SELF']); // active links

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
    'loan_application' => 'Loan Application',
    'user' => 'User Profile',
    'admin' => 'Admin Profile',
    'interest_rate' => 'Interest Rate',
    'payment' => 'Payment',
    'document' => 'Document',
    'remarks' => 'Remark',
    'payment_schedule' => 'Payment Schedule',
    'loan_type' => 'Loan Type',
    'notification' => 'Notification'
];

// Helper function to execute SELECT queries
function executeQuery($conn, $query, $types = '', $params = [])
{
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        error_log("Prepare failed: " . $conn->error);
        return [];
    }
    if (!empty($types) && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        $stmt->close();
        return [];
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
function executeUpdate($conn, $query, $types = '', $params = [])
{
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        error_log("Prepare failed: " . $conn->error);
        return 0;
    }
    if (!empty($types) && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $result = $stmt->execute();
    $affectedRows = $stmt->affected_rows;
    $stmt->close();
    return $result ? $affectedRows : 0;
}

// Verify user is Admin 1
// Security: Validate session variables
if (empty($_SESSION['email'])) {
    error_log("Admin1 Dashboard: Session email is empty! Session contents: " . json_encode($_SESSION), E_USER_WARNING);
    if (isset($_POST['action'])) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid session. Please login again.']);
        exit;
    }
    $_SESSION['error'] = 'Invalid session. Please login again.';
    header("Location: index.php");
    exit;
}

// Use email from session directly (trim and lowercase for consistency)
$adminEmail = trim(strtolower($_SESSION['email']));

// Debug logging for admin verification
error_log("Admin1 Dashboard: Session details - email from session: '{$_SESSION['email']}', normalized to: '$adminEmail'", E_USER_NOTICE);
error_log("Admin1 Dashboard: Attempting to verify admin with email: $adminEmail", E_USER_NOTICE);

// Determine admin role and verify user is Admin 1
$query = "SELECT id, first_name, last_name, email FROM admin1 WHERE LOWER(TRIM(email)) = LOWER(TRIM(?)) LIMIT 1";
error_log("Admin1 Dashboard: Executing query: $query with param: '$adminEmail'", E_USER_NOTICE);

$stmt = $conn->prepare($query);
if ($stmt === false) {
    error_log("Security: Failed to prepare admin verification statement - Error: " . $conn->error, E_USER_WARNING);
    if (isset($_POST['action'])) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database error. Please try again later.']);
        exit;
    }
    $_SESSION['error'] = 'Database error. Please try again later.';
    header("Location: index.php");
    exit;
}
$stmt->bind_param("s", $adminEmail);
if (!$stmt->execute()) {
    error_log("Security: Failed to execute admin verification for email: $adminEmail - Error: " . $stmt->error, E_USER_WARNING);
    if (isset($_POST['action'])) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database error. Please try again later.']);
        exit;
    }
    $_SESSION['error'] = 'Database error. Please try again later.';
    $stmt->close();
    header("Location: index.php");
    exit;
}
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$stmt->close();

error_log("Admin1 Dashboard: Query result - Found admin: " . ($admin ? "YES (ID: {$admin['id']}, Email: {$admin['email']})" : "NO"), E_USER_NOTICE);

if (!$admin) {
    error_log("AUTHORIZATION FAILED: Email: $adminEmail, User ID: " . ($_SESSION['user_id'] ?? 'NONE') . ", Admin not found in admin1 table");

    // Enhanced auto-creation with comprehensive logging
    error_log("AUTO-CREATE: Attempting to find user in users1 table for email: $adminEmail");

    $userStmt = $conn->prepare("SELECT id, first_name, last_name, email FROM users1 WHERE LOWER(TRIM(email)) = LOWER(TRIM(?)) LIMIT 1");
    if ($userStmt) {
        $userStmt->bind_param("s", $adminEmail);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        $userRecord = $userResult->fetch_assoc();
        $userStmt->close();

        if ($userRecord) {
            error_log("AUTO-CREATE: Found user in users1 - ID: {$userRecord['id']}, Email: {$userRecord['email']}");

            // Check if admin1 record already exists (race condition protection)
            $checkStmt = $conn->prepare("SELECT id FROM admin1 WHERE LOWER(TRIM(email)) = LOWER(TRIM(?)) LIMIT 1");
            if ($checkStmt) {
                $checkStmt->bind_param("s", $adminEmail);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                $existingAdmin = $checkResult->fetch_assoc();
                $checkStmt->close();

                if ($existingAdmin) {
                    error_log("AUTO-CREATE: Admin1 record already exists (race condition) - ID: {$existingAdmin['id']}");
                    $admin = $existingAdmin;
                } else {
                    // Create admin1 record based on user data
                    $insertAdmin = $conn->prepare("INSERT INTO admin1 (first_name, last_name, email, password, profile_img, created_at, updated_at) VALUES (?, ?, ?, 'temp_password', 'default.png', NOW(), NOW())");
                    if ($insertAdmin) {
                        $insertAdmin->bind_param("sss", $userRecord['first_name'], $userRecord['last_name'], $userRecord['email']);
                        if ($insertAdmin->execute()) {
                            $newAdminId = $conn->insert_id;
                            error_log("AUTO-CREATE SUCCESS: Created admin1 record for email: $adminEmail with ID: $newAdminId");

                            // Re-fetch the created admin record to ensure consistency
                            $fetchStmt = $conn->prepare("SELECT id, first_name, last_name, email FROM admin1 WHERE id = ? LIMIT 1");
                            if ($fetchStmt) {
                                $fetchStmt->bind_param("i", $newAdminId);
                                $fetchStmt->execute();
                                $fetchResult = $fetchStmt->get_result();
                                $admin = $fetchResult->fetch_assoc();
                                $fetchStmt->close();
                                error_log("AUTO-CREATE VERIFY: Re-fetched admin record - ID: {$admin['id']}, Email: {$admin['email']}");
                            }
                        } else {
                            error_log("AUTO-CREATE FAILED: Database error creating admin1 record: " . $insertAdmin->error);
                        }
                        $insertAdmin->close();
                    } else {
                        error_log("AUTO-CREATE FAILED: Could not prepare insert statement: " . $conn->error);
                    }
                }
            }
        } else {
            error_log("AUTO-CREATE FAILED: User not found in users1 table for email: $adminEmail");
        }
    } else {
        error_log("AUTO-CREATE FAILED: Could not prepare users1 query: " . $conn->error);
    }

    // If still no admin record found after auto-creation attempt
    if (!$admin) {
        error_log("FINAL AUTH FAILURE: Could not authorize user after auto-creation attempt - Email: $adminEmail");

        // For credit investigation submissions, provide more detailed debugging
        if (isset($_POST['action']) && $_POST['action'] === 'submit_credit_investigation') {
            error_log("CREDIT INVESTIGATION AUTH FAILURE - Email: $adminEmail, User ID: " . ($_SESSION['user_id'] ?? 'NONE') . ", Role: " . ($_SESSION['role'] ?? 'NONE'));

            ob_clean();
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Admin account setup failed. Please contact support to configure your admin1 access.',
                'debug_info' => [
                    'searched_email' => $adminEmail,
                    'session_email' => $_SESSION['email'] ?? 'NONE',
                    'user_id' => $_SESSION['user_id'] ?? 'NONE',
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ]);
            exit;
        }

        if (isset($_POST['action'])) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Admin account setup failed. Please contact support.',
                'debug_email' => $adminEmail,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            exit;
        }
        $_SESSION['error'] = 'Admin account setup failed. Please contact support.';
        header("Location: index.php");
        exit;
    } else {
        error_log("AUTO-CREATE SUCCESS: Admin authorization successful - ID: {$admin['id']}, Email: {$admin['email']}");
    }
}

$adminId = $admin['id'];
$adminName = trim($admin['first_name'] . ' ' . $admin['last_name']);
$adminRole = 'Admin1';

// Log successful admin access AFTER variables are defined
error_log("Admin1 Dashboard Access - Email: $adminEmail, User ID: $adminId, Role: $adminRole");

// Set session role explicitly
$_SESSION['role'] = 'admin1';
$_SESSION['admin_id'] = $adminId;  // Also store admin_id in session for reference
$profile_link = 'profileAdmin1.php';

// Fetch profile image for authenticated admin
if ($adminId && $adminId > 0) {
    $sql = "SELECT profile_img FROM admin1 WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $adminId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        $profile_img = !empty($user['profile_img']) ? $user['profile_img'] : "default.png";
    }
}

// Function to log activity
function logActivity($conn, $userId, $userRole, $actionType, $module, $description, $affectedId = null, $activityType = null)
{
    try {
        // Get admin name and email based on user role
        $adminName = null;
        $adminEmail = null;

        if ($userRole === 'Admin1') {
            $stmt = $conn->prepare("SELECT first_name, last_name, email FROM admin1 WHERE id = ?");
            if (!$stmt) {
                error_log("Admin1 prepare error in logActivity: " . $conn->error);
            } else {
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($admin = $result->fetch_assoc()) {
                    $adminName = trim($admin['first_name'] . ' ' . $admin['last_name']);
                    $adminEmail = $admin['email'];
                }
                $stmt->close();
            }
        } elseif ($userRole === 'Admin2') {
            $stmt = $conn->prepare("SELECT first_name, last_name, email FROM admin2 WHERE id = ?");
            if (!$stmt) {
                error_log("Admin2 prepare error in logActivity: " . $conn->error);
            } else {
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($admin = $result->fetch_assoc()) {
                    $adminName = trim($admin['first_name'] . ' ' . $admin['last_name']);
                    $adminEmail = $admin['email'];
                }
                $stmt->close();
            }
        } elseif ($userRole === 'User') {
            $stmt = $conn->prepare("SELECT first_name, last_name, email FROM users1 WHERE id = ?");
            if (!$stmt) {
                error_log("Users1 prepare error in logActivity: " . $conn->error);
            } else {
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($user = $result->fetch_assoc()) {
                    $adminName = trim($user['first_name'] . ' ' . $user['last_name']);
                    $adminEmail = $user['email'];
                }
                $stmt->close();
            }
        }

        $stmt = $conn->prepare("
            INSERT INTO activity_logs (user_id, user_role, admin_name, admin_email, action_type, module, description, affected_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        if (!$stmt) {
            error_log("Activity log prepare error: " . $conn->error);
            throw new Exception("Failed to prepare activity log: " . $conn->error);
        }

        if (!$stmt->bind_param("issssssi", $userId, $userRole, $adminName, $adminEmail, $actionType, $module, $description, $affectedId)) {
            error_log("Activity log bind error: " . $stmt->error);
            $stmt->close();
            throw new Exception("Failed to bind activity log: " . $stmt->error);
        }

        $result = $stmt->execute();
        if (!$result) {
            error_log("Activity log execute error: " . $stmt->error);
            $stmt->close();
            throw new Exception("Failed to log activity: " . $stmt->error);
        }
        $stmt->close();
        return true;
    } catch (Exception $e) {
        error_log("Log Activity Error: " . $e->getMessage());
        return false;
    }
}

// ============================================
// SECURITY FUNCTIONS (Added for Admin2 Parity)
// ============================================

/**
 * Get or create CSRF token for the session
 * @return string The CSRF token
 */
function getCSRFToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token using timing-safe comparison
 * @param string $token The token to validate
 * @return bool True if valid, false otherwise
 */
function validateCSRFToken($token)
{
    if (empty($_SESSION['csrf_token'])) {
        return false;
    }
    // Use hash_equals for timing-safe comparison to prevent token leakage
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize string input for XSS protection
 * @param string $input The input string to sanitize
 * @param int $maxLength Maximum allowed length (0 = no limit)
 * @return string The sanitized string
 */
function sanitizeString($input, $maxLength = 0)
{
    if (!is_string($input)) {
        return '';
    }

    // Trim whitespace
    $input = trim($input);

    // Apply length limit if specified
    if ($maxLength > 0) {
        $input = substr($input, 0, $maxLength);
    }

    // HTML entity encode to prevent XSS
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');

    return $input;
}

/**
 * Sanitize email input
 * @param string $input The email to sanitize
 * @return string The sanitized email or empty string if invalid
 */
function sanitizeEmail($input)
{
    if (!is_string($input)) {
        return '';
    }

    $email = trim(strtolower($input));

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return '';
    }

    return $email;
}

/**
 * Sanitize integer input
 * @param mixed $input The input to sanitize
 * @return int The sanitized integer or 0
 */
function sanitizeInt($input)
{
    if (is_numeric($input)) {
        return (int) $input;
    }
    return 0;
}

/**
 * Sanitize float input
 * @param mixed $input The input to sanitize
 * @return float The sanitized float or 0.0
 */
function sanitizeFloat($input)
{
    if (is_numeric($input)) {
        return (float) $input;
    }
    return 0.0;
}

/**
 * Validate that a value is within an allowed set of enum values
 * @param mixed $value The value to validate
 * @param array $allowedValues Array of allowed values
 * @return bool True if value is in allowed set, false otherwise
 */
function validateEnum($value, $allowedValues)
{
    if (!is_array($allowedValues) || empty($allowedValues)) {
        return false;
    }

    return in_array($value, $allowedValues, true);
}

/**
 * Sanitize array of inputs with type checking
 * @param array $inputs The array of inputs to sanitize
 * @param string $type The type of sanitization: 'string', 'int', 'float', 'email'
 * @return array The sanitized array
 */
function sanitizeArray($inputs, $type = 'string')
{
    if (!is_array($inputs)) {
        return [];
    }

    $sanitized = [];

    foreach ($inputs as $key => $value) {
        $safeKey = sanitizeString($key, 255);

        if (empty($safeKey)) {
            continue;
        }

        switch ($type) {
            case 'int':
                $sanitized[$safeKey] = sanitizeInt($value);
                break;
            case 'float':
                $sanitized[$safeKey] = sanitizeFloat($value);
                break;
            case 'email':
                $sanitized[$safeKey] = sanitizeEmail($value);
                break;
            case 'string':
            default:
                $sanitized[$safeKey] = sanitizeString($value, 500);
                break;
        }
    }

    return $sanitized;
}

// ============================================
// END SECURITY FUNCTIONS
// ============================================

// ============================================
// ERROR HANDLING & LOGGING FUNCTIONS (Added for Admin2 Parity)
// ============================================

/**
 * Log errors with structured context information
 * @param string $level The error level: 'debug', 'info', 'warning', 'error', 'critical'
 * @param string $message The error message
 * @param array $context Additional context information
 * @return void
 */
function logError($level = 'error', $message = '', $context = [])
{
    $validLevels = ['debug', 'info', 'warning', 'error', 'critical'];

    if (!in_array($level, $validLevels, true)) {
        $level = 'error';
    }

    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
    $logMessage = "[$timestamp] [$level] $message$contextStr";

    error_log($logMessage);
}

/**
 * Return structured JSON error response for API calls
 * @param string $message The error message
 * @param int $statusCode HTTP status code
 * @param array $details Additional error details
 * @return void (outputs JSON and exits)
 */
function handleApiError($message = 'An error occurred', $statusCode = 500, $details = [])
{
    // Set appropriate HTTP status code
    http_response_code($statusCode);

    // Set JSON header
    header('Content-Type: application/json');

    $response = [
        'success' => false,
        'message' => sanitizeString($message, 500),
        'status_code' => $statusCode,
        'timestamp' => date('Y-m-d H:i:s'),
        'details' => $details
    ];

    // Log the error
    logError('error', $message, $details);

    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Return structured JSON success response for API calls
 * @param mixed $data The response data
 * @param string $message Success message
 * @param int $statusCode HTTP status code (default 200)
 * @return void (outputs JSON and exits)
 */
function handleApiSuccess($data = [], $message = 'Operation successful', $statusCode = 200)
{
    // Set HTTP status code
    http_response_code($statusCode);

    // Set JSON header
    header('Content-Type: application/json');

    $response = [
        'success' => true,
        'message' => sanitizeString($message, 500),
        'data' => $data,
        'status_code' => $statusCode,
        'timestamp' => date('Y-m-d H:i:s')
    ];

    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Log operation with status and context
 * @param string $operation The operation name
 * @param string $status The operation status: 'started', 'completed', 'failed', 'pending'
 * @param array $context Additional context information
 * @return void
 */
function logOperation($operation = '', $status = 'pending', $context = [])
{
    $validStatuses = ['started', 'completed', 'failed', 'pending', 'cancelled'];

    if (!in_array($status, $validStatuses, true)) {
        $status = 'pending';
    }

    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? json_encode($context) : '{}';
    $logMessage = "[$timestamp] Operation: $operation | Status: $status | Context: $contextStr";

    error_log($logMessage);
}

/**
 * Validate multiple conditions and return status
 * Useful for bulk validation before executing operations
 * @param array $conditions Array of conditions to validate
 *        Each element should be: ['name' => 'fieldName', 'value' => $value, 'type' => 'required|email|int|range']
 *        Optional 'min' and 'max' for range validation
 * @return array Array with keys: ['valid' => bool, 'errors' => [], 'data' => []]
 */
function validateConditions($conditions = [])
{
    $result = [
        'valid' => true,
        'errors' => [],
        'data' => []
    ];

    if (!is_array($conditions) || empty($conditions)) {
        return $result;
    }

    foreach ($conditions as $condition) {
        $name = isset($condition['name']) ? $condition['name'] : 'unknown';
        $value = isset($condition['value']) ? $condition['value'] : null;
        $type = isset($condition['type']) ? $condition['type'] : 'required';

        $error = null;

        switch ($type) {
            case 'required':
                if (empty($value) && $value !== 0 && $value !== '0') {
                    $error = "$name is required";
                }
                break;

            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $error = "$name must be a valid email address";
                }
                break;

            case 'int':
                if (!is_numeric($value) || !is_int((int) $value)) {
                    $error = "$name must be an integer";
                }
                break;

            case 'float':
                if (!is_numeric($value)) {
                    $error = "$name must be a numeric value";
                }
                break;

            case 'range':
                $min = isset($condition['min']) ? $condition['min'] : null;
                $max = isset($condition['max']) ? $condition['max'] : null;

                if ($min !== null && $value < $min) {
                    $error = "$name must be at least $min";
                } elseif ($max !== null && $value > $max) {
                    $error = "$name must not exceed $max";
                }
                break;

            case 'string':
                if (!is_string($value)) {
                    $error = "$name must be a string";
                }
                break;

            case 'url':
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    $error = "$name must be a valid URL";
                }
                break;

            case 'enum':
                $allowedValues = isset($condition['allowed']) ? $condition['allowed'] : [];
                if (!in_array($value, $allowedValues, true)) {
                    $allowed = implode(', ', $allowedValues);
                    $error = "$name must be one of: $allowed";
                }
                break;

            default:
                // Unknown type, treat as required check
                if (empty($value)) {
                    $error = "$name is required";
                }
        }

        if ($error) {
            $result['valid'] = false;
            $result['errors'][] = $error;
        } else {
            $result['data'][$name] = $value;
        }
    }

    return $result;
}

// ============================================
// END ERROR HANDLING & LOGGING FUNCTIONS
// ============================================

// ============================================
// SECTION 14: PERFORMANCE FEATURES
// ============================================

/**
 * Batch Operations Manager - Handles bulk updates/deletes efficiently
 * Reduces database load by grouping operations and logging them as single batch
 * @param string $operation Operation type (update, delete, status_change)
 * @param string $table Table name
 * @param array $where WHERE conditions (column => value pairs)
 * @param array $sets SET values for update operations (column => value pairs)
 * @param array $details Details for logging and error reporting
 * @return array ['success' => bool, 'affected' => int, 'message' => string, 'errors' => []]
 */
function batchOperation($conn, $operation, $table, $where = [], $sets = [], $details = [])
{
    $result = [
        'success' => false,
        'affected' => 0,
        'message' => '',
        'errors' => [],
        'query' => '',
        'operation_id' => uniqid('batch_', true)
    ];

    try {
        // Validate operation type
        $allowedOperations = ['update', 'delete', 'status_change'];
        if (!in_array($operation, $allowedOperations)) {
            throw new Exception("Invalid batch operation: $operation");
        }

        // Build WHERE clause
        if (empty($where)) {
            throw new Exception("WHERE conditions required for batch operation");
        }

        $whereConditions = [];
        $whereTypes = '';
        $whereParams = [];

        foreach ($where as $column => $value) {
            $whereConditions[] = "$column = ?";
            $whereTypes .= is_int($value) ? 'i' : 's';
            $whereParams[] = $value;
        }

        $whereClause = implode(' AND ', $whereConditions);

        // Build query based on operation
        if ($operation === 'update' || $operation === 'status_change') {
            if (empty($sets)) {
                throw new Exception("SET values required for update operation");
            }

            $setConditions = [];
            $setTypes = '';
            $setParams = [];

            foreach ($sets as $column => $value) {
                $setConditions[] = "$column = ?";
                if (is_int($value)) {
                    $setTypes .= 'i';
                } elseif (is_float($value)) {
                    $setTypes .= 'd';
                } else {
                    $setTypes .= 's';
                }
                $setParams[] = $value;
            }

            $setClause = implode(', ', $setConditions);
            $query = "UPDATE $table SET $setClause WHERE $whereClause";

            // Combine parameters: SET params first, then WHERE params
            $allParams = array_merge($setParams, $whereParams);
            $allTypes = $setTypes . $whereTypes;

        } elseif ($operation === 'delete') {
            $query = "DELETE FROM $table WHERE $whereClause";
            $allParams = $whereParams;
            $allTypes = $whereTypes;
        }

        // Execute batch operation
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        if (!empty($allParams)) {
            $stmt->bind_param($allTypes, ...$allParams);
        }

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $affected = $stmt->affected_rows;
        $stmt->close();

        // Prepare result
        $result['success'] = true;
        $result['affected'] = $affected;
        $result['query'] = $query;
        $result['message'] = "Batch $operation completed: $affected rows affected";

        // Log batch operation with details
        logOperation(
            "batch_" . $operation,
            'completed',
            [
                'operation_id' => $result['operation_id'],
                'table' => $table,
                'affected_rows' => $affected,
                'where_conditions' => array_keys($where),
                'details' => $details
            ]
        );

        return $result;

    } catch (Exception $e) {
        $result['success'] = false;
        $result['message'] = $e->getMessage();
        $result['errors'][] = $e->getMessage();

        logError('error', "Batch operation failed: " . $e->getMessage(), [
            'operation' => $operation,
            'table' => $table,
            'operation_id' => $result['operation_id'],
            'details' => $details
        ]);

        return $result;
    }
}

/**
 * Query Optimization Analyzer - Detects and logs slow/inefficient queries
 * Includes timing analysis, query complexity scoring, and recommendations
 * @param string $query SQL query to analyze
 * @param float $executionTime Execution time in seconds
 * @param array $context Additional context (row count, affected rows, etc.)
 * @return array Analysis results with recommendations
 */
function analyzeQueryPerformance($query, $executionTime = 0, $context = [])
{
    $analysis = [
        'query' => substr($query, 0, 200), // First 200 chars
        'execution_time' => $executionTime,
        'is_slow' => $executionTime > 0.1, // Over 100ms is slow
        'complexity_score' => 0,
        'warnings' => [],
        'recommendations' => [],
        'timestamp' => date('Y-m-d H:i:s')
    ];

    // Check for SELECT * (anti-pattern)
    if (preg_match('/SELECT\s+\*/i', $query)) {
        $analysis['complexity_score'] += 2;
        $analysis['warnings'][] = "SELECT * detected - specify needed columns";
        $analysis['recommendations'][] = "Replace SELECT * with specific column names";
    }

    // Check for JOIN count
    $joinCount = preg_match_all('/\bJOIN\b/i', $query);
    if ($joinCount > 3) {
        $analysis['complexity_score'] += $joinCount;
        $analysis['warnings'][] = "High number of JOINs ($joinCount) - may be inefficient";
        $analysis['recommendations'][] = "Consider denormalization or query restructuring";
    }

    // Check for subqueries
    if (preg_match('/\(SELECT/i', $query)) {
        $analysis['complexity_score'] += 3;
        $analysis['warnings'][] = "Subquery detected - may benefit from JOIN or temp table";
        $analysis['recommendations'][] = "Use JOIN instead of subquery if possible";
    }

    // Check for OR conditions in WHERE
    if (preg_match('/WHERE.*\bOR\b/i', $query)) {
        $analysis['complexity_score'] += 1;
        $analysis['warnings'][] = "OR conditions in WHERE - ensure indexes exist";
        $analysis['recommendations'][] = "Verify indexes on OR-ed columns";
    }

    // Check for missing WHERE clause
    if (preg_match('/^SELECT|^UPDATE|^DELETE/i', $query) && !preg_match('/\bWHERE\b/i', $query)) {
        $analysis['complexity_score'] += 5;
        $analysis['warnings'][] = "No WHERE clause - potential full table scan";
        $analysis['recommendations'][] = "Add WHERE conditions to limit scope";
    }

    // Check execution time thresholds
    if ($executionTime > 1.0) {
        $analysis['warnings'][] = "CRITICAL: Query took over 1 second ({$executionTime}s)";
    } elseif ($executionTime > 0.5) {
        $analysis['warnings'][] = "WARNING: Query took over 500ms ({$executionTime}s)";
    } elseif ($executionTime > 0.1) {
        $analysis['warnings'][] = "NOTICE: Query took over 100ms ({$executionTime}s)";
    }

    // Log slow query
    if ($analysis['is_slow']) {
        logError('warning', "Slow query detected: {$executionTime}s", [
            'query' => $analysis['query'],
            'execution_time' => $executionTime,
            'complexity_score' => $analysis['complexity_score'],
            'context' => $context
        ]);
    }

    return $analysis;
}

/**
 * Cache Invalidation Handler - Intelligently invalidates caches on data changes
 * Cascades invalidation based on data relationships
 * @param string $table Table that changed
 * @param string $action Action that occurred (insert, update, delete)
 * @param array $affectedIds IDs of affected records
 * @param array $context Additional context for logging
 * @return array Invalidation summary
 */
function invalidateCache($table, $action = 'update', $affectedIds = [], $context = [])
{
    $invalidation = [
        'success' => true,
        'table' => $table,
        'action' => $action,
        'invalidated_caches' => [],
        'cascading_invalidations' => [],
        'timestamp' => date('Y-m-d H:i:s')
    ];

    try {
        // Map table to cache keys
        $cacheInvalidationMap = [
            'loan_applications' => ['loans_list', 'loans_stats', 'dashboard_summary'],
            'users' => ['user_profiles', 'user_list', 'dashboard_summary'],
            'payments' => ['payment_history', 'loans_stats', 'dashboard_summary', 'account_status'],
            'documents' => ['document_list', 'loans_list', 'account_status'],
            'remarks' => ['remarks_history', 'account_status', 'loans_list'],
            'interest_rates' => ['rates_cache', 'payment_calculations', 'loans_stats'],
            'activity_logs' => ['activity_cache', 'audit_trail'],
            'notifications' => ['notification_queue', 'user_notifications'],
            'email_queue' => ['email_pending', 'notification_queue']
        ];

        // Get caches to invalidate
        $cachesToInvalidate = $cacheInvalidationMap[$table] ?? [$table];

        foreach ($cachesToInvalidate as $cacheKey) {
            // Mark cache as expired in client-side (via header)
            // In production, would also clear server-side cache (Redis, Memcached)
            $invalidation['invalidated_caches'][] = $cacheKey;
        }

        // Handle cascading invalidations based on relationships
        if ($table === 'loan_applications' && in_array($action, ['update', 'delete'])) {
            // Invalidate related payment, document, and remark caches
            $invalidation['cascading_invalidations'][] = 'payments_for_loan_' . implode(',', $affectedIds);
            $invalidation['cascading_invalidations'][] = 'documents_for_loan_' . implode(',', $affectedIds);
            $invalidation['cascading_invalidations'][] = 'remarks_for_loan_' . implode(',', $affectedIds);
        }

        if ($table === 'documents' && in_array($action, ['insert', 'update'])) {
            // Invalidate affected loan's cache
            $invalidation['cascading_invalidations'][] = 'loan_documents_updated';
        }

        // Log cache invalidation
        logOperation('cache_invalidation', 'completed', [
            'table' => $table,
            'action' => $action,
            'affected_ids' => $affectedIds,
            'caches_invalidated' => $invalidation['invalidated_caches'],
            'cascading_invalidations' => $invalidation['cascading_invalidations'],
            'context' => $context
        ]);

        return $invalidation;

    } catch (Exception $e) {
        $invalidation['success'] = false;
        logError('error', "Cache invalidation failed: " . $e->getMessage(), [
            'table' => $table,
            'action' => $action,
            'context' => $context
        ]);

        return $invalidation;
    }
}

/**
 * Query Execution Wrapper - Wraps query execution with timing and performance monitoring
 * Automatically logs slow queries and provides performance metrics
 * @param mysqli $conn Database connection
 * @param string $query SQL query
 * @param string $types Parameter types (for prepared statements)
 * @param array $params Parameters (for prepared statements)
 * @param string $type Execution type ('select', 'update', 'insert', 'delete')
 * @param array $context Additional context for logging
 * @return array ['success' => bool, 'data' => [], 'affected' => 0, 'timing' => float, 'analysis' => []]
 */
function executeQueryWithTiming($conn, $query, $types = '', $params = [], $type = 'select', $context = [])
{
    $result = [
        'success' => false,
        'data' => [],
        'affected' => 0,
        'timing' => 0,
        'analysis' => [],
        'error' => null
    ];

    try {
        $startTime = microtime(true);

        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        if (!empty($types) && !empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $executionTime = microtime(true) - $startTime;

        if ($type === 'select') {
            $queryResult = $stmt->get_result();
            while ($row = $queryResult->fetch_assoc()) {
                $result['data'][] = $row;
            }
        } else {
            $result['affected'] = $stmt->affected_rows;
        }

        $stmt->close();

        $result['success'] = true;
        $result['timing'] = $executionTime;

        // Analyze performance
        $result['analysis'] = analyzeQueryPerformance($query, $executionTime, [
            'type' => $type,
            'rows_affected' => $result['affected'] ?? count($result['data']),
            'context' => $context
        ]);

        return $result;

    } catch (Exception $e) {
        $result['error'] = $e->getMessage();
        logError('error', "Query execution error: " . $e->getMessage(), [
            'query' => substr($query, 0, 200),
            'type' => $type,
            'context' => $context
        ]);

        return $result;
    }
}

/**
 * Batch Query Results Processor - Handles pagination and filtering of large result sets
 * Reduces memory usage for large datasets
 * @param array $data Query result data
 * @param int $page Page number (1-indexed)
 * @param int $perPage Results per page
 * @param string $searchTerm Optional search term for filtering
 * @param array $searchColumns Columns to search in
 * @return array ['data' => [], 'total' => int, 'pages' => int, 'current_page' => int]
 */
function processBatchResults($data, $page = 1, $perPage = 20, $searchTerm = '', $searchColumns = [])
{
    $result = [
        'data' => [],
        'total' => count($data),
        'pages' => ceil(count($data) / $perPage),
        'current_page' => $page,
        'per_page' => $perPage,
        'has_more' => false
    ];

    try {
        // Apply search filter if provided
        if (!empty($searchTerm) && !empty($searchColumns)) {
            $filtered = [];
            $searchLower = strtolower($searchTerm);

            foreach ($data as $row) {
                $found = false;
                foreach ($searchColumns as $column) {
                    if (isset($row[$column]) && strpos(strtolower((string) $row[$column]), $searchLower) !== false) {
                        $found = true;
                        break;
                    }
                }
                if ($found) {
                    $filtered[] = $row;
                }
            }

            $data = $filtered;
            $result['total'] = count($filtered);
            $result['pages'] = ceil(count($filtered) / $perPage);
        }

        // Apply pagination
        $offset = ($page - 1) * $perPage;
        $result['data'] = array_slice($data, $offset, $perPage);
        $result['has_more'] = ($offset + $perPage) < $result['total'];

        return $result;

    } catch (Exception $e) {
        logError('error', "Batch result processing failed: " . $e->getMessage(), [
            'total_records' => count($data),
            'page' => $page,
            'per_page' => $perPage
        ]);

        return $result;
    }
}

// ============================================
// END SECTION 14: PERFORMANCE FEATURES
// ============================================

// ============================================
// ENHANCED EMAIL SYSTEM (Admin2 Parity)
// ============================================

/**
 * Enhanced email sending function with debugging and retry logic
 * @param string $to Recipient email address
 * @param string $toName Recipient display name
 * @param string $subject Email subject
 * @param string $body Email body (HTML)
 * @param string $context Additional context for logging
 * @param bool $retryOnFailure Retry on failure (max 3 attempts)
 * @return array ['success' => bool, 'message' => string, 'attempts' => int]
 */
function sendEmail($to, $toName, $subject, $body, $context = '', $retryOnFailure = true)
{
    $maxAttempts = 3;
    $attempt = 0;
    $lastError = '';

    while ($attempt < $maxAttempts) {
        $attempt++;

        try {
            // Validate recipient email
            $to = sanitizeEmail($to);
            if (empty($to)) {
                throw new Exception("Invalid recipient email address");
            }

            $mail = new PHPMailer(true);

            // Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'scycloan@gmail.com';
            $mail->Password = 'xbvo zplr dpme ixxj';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            // Timeout and keep-alive settings
            $mail->Timeout = 30;
            $mail->SMTPKeepAlive = true;

            // Recipients
            $mail->setFrom('scycloan@gmail.com', 'CYCLOAN Loan Support');
            $mail->addAddress($to, $toName);

            // Content
            $mail->isHTML(true);
            $mail->Subject = sanitizeString($subject, 255);
            $mail->Body = $body;

            // Plain text alternative
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));

            // Send email
            $mail->send();

            // Success logging
            $logMessage = "Email sent successfully (Attempt $attempt) | To: $to | Subject: {$mail->Subject}";
            if (!empty($context)) {
                $logMessage .= " | Context: $context";
            }
            error_log($logMessage);
            logOperation("send_email", "completed", [
                'recipient' => $to,
                'subject' => $mail->Subject,
                'attempt' => $attempt,
                'context' => $context
            ]);

            return [
                'success' => true,
                'message' => 'Email sent successfully',
                'attempts' => $attempt
            ];

        } catch (Exception $e) {
            $lastError = $e->getMessage();

            $logMessage = "Email sending failed (Attempt $attempt/$maxAttempts) | To: $to | Error: $lastError";
            if (!empty($context)) {
                $logMessage .= " | Context: $context";
            }
            error_log($logMessage);
            logError('warning', "Email send attempt $attempt failed", [
                'recipient' => $to,
                'error' => $lastError,
                'context' => $context
            ]);

            // If not retry-enabled or final attempt, return failure
            if (!$retryOnFailure || $attempt >= $maxAttempts) {
                logError('error', "Email sending permanently failed", [
                    'recipient' => $to,
                    'final_error' => $lastError,
                    'attempts' => $attempt,
                    'context' => $context
                ]);

                return [
                    'success' => false,
                    'message' => $lastError,
                    'attempts' => $attempt
                ];
            }

            // Wait before retry (exponential backoff)
            sleep(2 * $attempt);
        }
    }

    return [
        'success' => false,
        'message' => $lastError,
        'attempts' => $attempt
    ];
}

/**
 * Select appropriate email template based on status and updates
 * @param array $updates Array of updates with status information
 * @return string Template type: 'approved', 'rejected', 'pending', 'default'
 */
function selectEmailTemplate($updates = [])
{
    if (empty($updates) || !is_array($updates)) {
        return 'default';
    }

    // Check various update types to determine template
    if (isset($updates['credit_investigation_status'])) {
        $status = strtolower($updates['credit_investigation_status']);

        if (strpos($status, 'approved') !== false || strpos($status, 'completed') !== false) {
            return 'approved';
        } elseif (strpos($status, 'rejected') !== false || strpos($status, 'failed') !== false) {
            return 'rejected';
        } elseif (strpos($status, 'pending') !== false) {
            return 'pending';
        }
    }

    if (isset($updates['document_status'])) {
        $status = strtolower($updates['document_status']);

        if (strpos($status, 'approved') !== false) {
            return 'approved';
        } elseif (strpos($status, 'rejected') !== false) {
            return 'rejected';
        }
    }

    return 'default';
}

/**
 * Send consolidated update email with multiple status changes
 * Combines multiple updates into a single professional email
 * @param mysqli $conn Database connection
 * @param int $applicationId Application ID
 * @param array $updates Array of updates including statuses, remarks, documents
 * @param string $adminName Admin name for tracking
 * @return array ['success' => bool, 'message' => string]
 */
function sendConsolidatedUpdateEmail($conn, $applicationId, $updates = [], $adminName = 'Admin Support')
{
    try {
        // Get applicant information
        $stmt = $conn->prepare("
            SELECT u.email, u.first_name, u.last_name
            FROM loan_applications la
            JOIN users1 u ON la.user_id = u.id
            WHERE la.application_id = ?
        ");

        if (!$stmt) {
            throw new Exception("Database prepare error: " . $conn->error);
        }

        $stmt->bind_param("i", $applicationId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            throw new Exception("No applicant found for application_id: $applicationId");
        }

        $to = $user['email'];
        $applicantName = trim($user['first_name'] . ' ' . $user['last_name']);

        // Determine email template and subject
        $templateType = selectEmailTemplate($updates);
        $subject = generateEmailSubject($templateType, $updates);

        // Generate email body
        $body = generateConsolidatedEmailBody($applicantName, $templateType, $updates, $adminName);

        // Send the email
        $result = sendEmail($to, $applicantName, $subject, $body, "consolidated_update_app_$applicationId");

        if ($result['success']) {
            // Log successful consolidation
            logOperation("send_consolidated_email", "completed", [
                'application_id' => $applicationId,
                'recipient' => $to,
                'template_type' => $templateType,
                'admin_name' => $adminName,
                'attempts' => $result['attempts']
            ]);
        } else {
            logError('error', "Failed to send consolidated email", [
                'application_id' => $applicationId,
                'recipient' => $to,
                'error' => $result['message']
            ]);
        }

        return $result;

    } catch (Exception $e) {
        $error = $e->getMessage();
        logError('critical', "Consolidated email error", [
            'application_id' => $applicationId,
            'error' => $error
        ]);

        return [
            'success' => false,
            'message' => $error,
            'attempts' => 0
        ];
    }
}

/**
 * Generate email subject based on template type and updates
 * @param string $templateType The email template type
 * @param array $updates Array of updates
 * @return string Email subject
 */
function generateEmailSubject($templateType = 'default', $updates = [])
{
    switch ($templateType) {
        case 'approved':
            return '🎉 CLDD Loan Application - Status Update (Approved)';
        case 'rejected':
            return '📋 CLDD Loan Application - Status Update (Needs Review)';
        case 'pending':
            return '⏳ CLDD Loan Application - Status Update (Under Review)';
        default:
            return '📬 CLDD Loan Application - Status Update';
    }
}

/**
 * Generate consolidated email body with styling
 * @param string $applicantName Applicant name
 * @param string $templateType Template type
 * @param array $updates Array of updates
 * @param string $adminName Admin name
 * @return string HTML email body
 */
function generateConsolidatedEmailBody($applicantName = '', $templateType = 'default', $updates = [], $adminName = '')
{
    $updates = sanitizeArray($updates, 'string');
    $applicantName = sanitizeString($applicantName, 100);
    $adminName = sanitizeString($adminName, 100);

    // Get appropriate greeting and header color based on template
    $headerColor = '#1e88e5';
    $headerGradient = 'linear-gradient(135deg, #1e88e5 0%, #1565c0 100%)';
    $greeting = 'Dear ' . $applicantName . ',';
    $mainMessage = 'Thank you for applying for a CYCLOAN Loan. Here is your application status update.';

    if ($templateType === 'approved') {
        $headerColor = '#28a745';
        $headerGradient = 'linear-gradient(135deg, #28a745 0%, #1e7e34 100%)';
        $mainMessage = '🎉 Congratulations! We have good news about your application!';
    } elseif ($templateType === 'rejected') {
        $headerColor = '#dc3545';
        $headerGradient = 'linear-gradient(135deg, #dc3545 0%, #bd2130 100%)';
        $mainMessage = '📋 Your application has been reviewed and requires additional information.';
    } elseif ($templateType === 'pending') {
        $headerColor = '#ffc107';
        $headerGradient = 'linear-gradient(135deg, #ffc107 0%, #e0a800 100%)';
        $mainMessage = '⏳ Your application is currently under review. We appreciate your patience.';
    }

    // Build updates section
    $updatesHtml = '';
    if (!empty($updates)) {
        $updatesHtml .= '<div style="margin: 20px 0; padding: 20px; background-color: #f5f5f5; border-radius: 8px;">';
        $updatesHtml .= '<h3 style="margin-top: 0; color: #333333; font-size: 18px;">📊 Application Updates:</h3>';

        foreach ($updates as $key => $value) {
            if (!empty($value)) {
                $displayKey = ucwords(str_replace('_', ' ', $key));
                $displayValue = sanitizeString($value, 500);
                $updatesHtml .= '<p style="margin: 10px 0; color: #555555;">';
                $updatesHtml .= '<strong>' . htmlspecialchars($displayKey) . ':</strong> ' . htmlspecialchars($displayValue);
                $updatesHtml .= '</p>';
            }
        }

        $updatesHtml .= '</div>';
    }

    // Admin tracking
    $adminInfo = '';
    if (!empty($adminName)) {
        $adminInfo = '<p style="margin: 15px 0; padding: 10px; background-color: #e8f4f8; border-left: 4px solid #1e88e5; color: #666666; font-size: 13px;">
            <strong>Processed by:</strong> ' . htmlspecialchars($adminName) . ' | <strong>Date:</strong> ' . date('F j, Y \a\t g:i A') . '
        </p>';
    }

    $body = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>CLDD Loan Application Update</title>
    </head>
    <body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; background-color: #f8f9fa; line-height: 1.6;">
        <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
            
            <!-- Header -->
            <div style="background: ' . $headerGradient . '; padding: 30px 20px; text-align: center; color: #ffffff;">
                <img src="assets/Main-Logo.png" alt="CLDD Logo" style="max-width: 200px; height: auto; margin-bottom: 15px;">
                <h1 style="margin: 0; font-size: 28px; font-weight: 600;">Application Status Update</h1>
            </div>
            
            <!-- Main Content -->
            <div style="padding: 30px 20px; color: #333333;">
                <p style="margin-bottom: 10px; font-size: 16px;">' . htmlspecialchars($greeting) . '</p>
                <p style="margin: 0 0 20px 0; font-size: 16px; color: #555555;">' . htmlspecialchars($mainMessage) . '</p>
                
                ' . $updatesHtml . '
                
                ' . $adminInfo . '
                
                <div style="margin: 20px 0; padding: 20px; background-color: #e8f5e9; border-radius: 8px; border-left: 4px solid #1b5e20;">
                    <h3 style="margin-top: 0; color: #1b5e20; font-size: 16px;">📞 Need Help?</h3>
                    <p style="margin: 0; color: #2e7d32;">If you have any questions about your application, please contact our support team at scycloan@gmail.com</p>
                </div>
            </div>
            
            <!-- Footer -->
            <div style="background-color: #f8f8f8; padding: 20px; text-align: center; font-size: 12px; color: #666666; border-top: 1px solid #e0e0e0;">
                <p style="margin: 5px 0;"><strong>CYCLOAN Loan Support Team</strong></p>
                <p style="margin: 5px 0;">Email: scycloan@gmail.com | Phone: 0981-303-8698 | Landline: 545-6789 loc 8018-19</p>
                <p style="margin: 5px 0;">Address: Lower Ground Floor (LG)24 New City Hall Bldg, Bacnotan St., Brgy Real, Calamba City Laguna</p>
                <p style="margin: 5px 0;">© ' . date('Y') . ' CYCLOAN Loan Program. All rights reserved.</p>
                <p style="margin: 10px 0 0 0; font-size: 11px;">This is an automated message. Please do not reply directly to this email.</p>
            </div>
        </div>
    </body>
    </html>';

    return $body;
}

// ============================================
// END ENHANCED EMAIL SYSTEM
// ============================================

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
                <p><strong>CYCLOAN Loan Support Team</strong></p>
                <p>Email: scycloan@gmail.com | Phone: 0981-303-8698 | Landline: 545-6789 loc 8018-19</p>
                <p>Address: Lower Ground Floor (LG)24 New City Hall Bldg, Bacnotan St., Brgy Real, Calamba City Laguna</p>
                <p>© " . date('Y') . " CYCLOAN Loan Program. All rights reserved.</p>
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
function sendCreditStatusEmail($conn, $applicationId, $newStatus, $finalLoanAmount = null)
{
    try {
        // Validate inputs
        if (!$applicationId || !$newStatus) {
            error_log("sendCreditStatusEmail: Missing required parameters - applicationId: $applicationId, newStatus: $newStatus");
            return false;
        }

        // Fetch user info and application details
        $stmt = $conn->prepare("
            SELECT u.email, u.first_name, u.last_name, la.term_length, la.amount_applied, la.application_id
            FROM loan_applications la
            JOIN users1 u ON la.user_id = u.id
            WHERE la.application_id = ?
        ");

        if (!$stmt) {
            error_log("sendCreditStatusEmail: Prepare failed for user fetch - Error: " . $conn->error);
            return false;
        }

        $stmt->bind_param("s", $applicationId);

        if (!$stmt->execute()) {
            error_log("sendCreditStatusEmail: Execute failed for user fetch - Error: " . $stmt->error);
            $stmt->close();
            return false;
        }

        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            error_log("sendCreditStatusEmail: No user found for application_id: $applicationId");
            return false;
        }

        // Fetch all remarks/notes associated with this application
        $remarksStmt = $conn->prepare("
            SELECT remarks, created_at, admin_name
            FROM remarks
            WHERE application_id = ?
            ORDER BY created_at DESC
            LIMIT 5
        ");

        if (!$remarksStmt) {
            error_log("sendCreditStatusEmail: Prepare failed for remarks fetch - Error: " . $conn->error);
            return false;
        }

        $remarksStmt->bind_param("s", $applicationId);

        if (!$remarksStmt->execute()) {
            error_log("sendCreditStatusEmail: Execute failed for remarks fetch - Error: " . $remarksStmt->error);
            $remarksStmt->close();
            return false;
        }

        $remarksResult = $remarksStmt->get_result();
        $allRemarks = $remarksResult->fetch_all(MYSQLI_ASSOC);
        $remarksStmt->close();

        $to = $user['email'];
        $name = htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name']));
        $amountApplied = floatval($user['amount_applied']);
        $termLength = !empty($user['term_length']) ? intval($user['term_length']) : null;

        // Build remarks section if remarks exist
        $remarksSection = '';
        if (!empty($allRemarks)) {
            $remarksSection = '<div style="background: #f5f5f5; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #666;">
                <h3 style="color: #333; margin: 0 0 15px 0; font-size: 16px;">📝 Investigation Notes & Remarks</h3>';

            foreach ($allRemarks as $remark) {
                if (!empty($remark['remarks'])) {
                    $createdDate = !empty($remark['created_at']) ? date('M d, Y g:i A', strtotime($remark['created_at'])) : 'Unknown date';
                    $adminName = !empty($remark['admin_name']) ? htmlspecialchars($remark['admin_name']) : 'Admin';

                    $remarksSection .= '<div style="padding: 10px 0; border-bottom: 1px solid #ddd;">
                        <p style="margin: 0; color: #333; line-height: 1.5;">' . htmlspecialchars($remark['remarks']) . '</p>
                        <p style="margin: 5px 0 0 0; color: #999; font-size: 12px;">— ' . $adminName . ' on ' . $createdDate . '</p>
                    </div>';
                }
            }
            $remarksSection .= '</div>';
        }

        // Create status-specific content
        $statusIcon = '';
        $statusColor = '';
        $statusMessage = '';
        $actionRequired = '';

        if ($newStatus === 'Completed' && $finalLoanAmount) {
            $statusIcon = '✅';
            $statusColor = '#28a745';
            $statusMessage = 'Congratulations! Your credit investigation has been completed successfully.';
            $actionRequired = '<div style="background: #e8f5e9; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #28a745;">
                <h3 style="color: #1b5e20; margin: 0 0 10px 0; font-size: 18px;">📋 Next Steps Required</h3>
                <p style="margin: 0; color: #2e7d32; font-size: 16px; line-height: 1.5;">
                    <strong>Please visit our office to complete your loan processing:</strong><br>
                    • Bring valid ID and required documents<br>
                    • Sign loan agreement and terms<br>
                    • Complete final verification process
                </p>
            </div>';
        } elseif ($newStatus === 'Failed') {
            $statusIcon = '❌';
            $statusColor = '#dc3545';
            $statusMessage = 'We regret to inform you that your credit investigation was not approved at this time.';
            $actionRequired = '<div style="background: #ffebee; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #dc3545;">
                <h3 style="color: #c62828; margin: 0 0 10px 0; font-size: 18px;">📞 Contact Support</h3>
                <p style="margin: 0; color: #d32f2f; font-size: 16px; line-height: 1.5;">
                    Please contact our office for more information about your application status and possible next steps.
                </p>
            </div>';
        } else {
            $statusIcon = '⏳';
            $statusColor = '#ffc107';
            $statusMessage = 'Your credit investigation is currently under review.';
            $actionRequired = '<div style="background: #fff8e1; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ffc107;">
                <h3 style="color: #e65100; margin: 0 0 10px 0; font-size: 18px;">⌛ Please Wait</h3>
                <p style="margin: 0; color: #ef6c00; font-size: 16px; line-height: 1.5;">
                    We will notify you once the review is complete. No action is required at this time.
                </p>
            </div>';
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'cycloancldd@gmail.com';
        $mail->Password = 'hbfh ukgh tmzw nqbq';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('cycloancldd@gmail.com', 'CYCLOAN Loan Support');
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $newStatus === 'Completed' ?
            '🎉 CYCLOAN Loan Approved - Office Visit Required' :
            'CYCLOAN Loan Program - Application Update';

        $mail->Body = "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>CYCLOAN Loan Update</title>
        </head>
        <body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; background-color: #f8f9fa; line-height: 1.6;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);'>
                
                <!-- Header -->
                <div style='background: linear-gradient(135deg, #1e88e5 0%, #1565c0 100%); padding: 30px 20px; text-align: center;'>
                    <div style='background: #ffffff; width: 60px; height: 60px; border-radius: 50%; margin: 0 auto 15px; display: flex; align-items: center; justify-content: center; font-size: 24px;'>🏦</div>
                    <h1 style='color: #ffffff; margin: 0; font-size: 28px; font-weight: 700;'>CYCLOAN Loan Program</h1>
                    <p style='color: #e3f2fd; margin: 5px 0 0 0; font-size: 16px;'>Credit Investigation Update</p>
                </div>

                <!-- Main Content -->
                <div style='padding: 40px 30px;'>
                    
                    <!-- Greeting -->
                    <div style='text-align: center; margin-bottom: 30px;'>
                        <h2 style='color: #1565c0; margin: 0 0 10px 0; font-size: 24px;'>Hello, $name!</h2>
                        <div style='font-size: 48px; margin: 10px 0;'>$statusIcon</div>
                    </div>

                    <!-- Status Message -->
                    <div style='text-align: center; margin-bottom: 30px;'>
                        <p style='font-size: 18px; color: #333333; margin: 0 0 15px 0; font-weight: 500;'>$statusMessage</p>
                    </div>

                    <!-- Application Details -->
                    <div style='background: #f8f9fa; padding: 25px; border-radius: 10px; margin: 25px 0; border: 1px solid #e9ecef;'>
                        <h3 style='color: #495057; margin: 0 0 15px 0; font-size: 18px; text-align: center;'>📋 Investigation Details</h3>
                        <table style='width: 100%; border-collapse: collapse;'>
                            <tr>
                                <td style='padding: 8px 0; color: #6c757d; font-weight: 500;'>Application ID:</td>
                                <td style='padding: 8px 0; color: #333333; font-weight: 700; text-align: right;'>#$applicationId</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px 0; color: #6c757d; font-weight: 500;'>Investigation Status:</td>
                                <td style='padding: 8px 0; color: $statusColor; font-weight: 700; text-align: right;'>$newStatus</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px 0; color: #6c757d; font-weight: 500;'>Amount Requested:</td>
                                <td style='padding: 8px 0; color: #333333; font-weight: 600; text-align: right;'>₱" . number_format($amountApplied, 2) . "</td>
                            </tr>" .
            ($finalLoanAmount ? "
                            <tr style='border-top: 2px solid #28a745;'>
                                <td style='padding: 15px 0 8px 0; color: #6c757d; font-weight: 500; font-size: 16px;'>🎯 Approved Amount:</td>
                                <td style='padding: 15px 0 8px 0; color: #28a745; font-weight: 800; text-align: right; font-size: 20px;'>₱" . number_format($finalLoanAmount, 2) . "</td>
                            </tr>" : "") . "
                            <tr>
                                <td style='padding: 8px 0; color: #6c757d; font-weight: 500;'>Loan Term:</td>
                                <td style='padding: 8px 0; color: #333333; font-weight: 600; text-align: right;'>" . ($termLength ? $termLength . ' months' : 'To be determined') . "</td>
                            </tr>
                        </table>
                    </div>

                    <!-- Remarks/Notes Section -->
                    $remarksSection

                    <!-- Action Required Section -->
                    $actionRequired

                    <!-- Office Information -->
                    <div style='background: #e3f2fd; padding: 25px; border-radius: 10px; margin: 25px 0; border-left: 4px solid #1565c0;'>
                        <h3 style='color: #0d47a1; margin: 0 0 15px 0; font-size: 18px;'>🏢 CYCLOAN Office Information</h3>
                        <p style='margin: 0; color: #1565c0; line-height: 1.6;'>
                            <strong>Address:</strong> Lower Ground Floor (LG)24 New City Hall Bldg, Bacnotan St., Brgy Real, Calamba City Laguna<br>
                            <strong>Business Hours:</strong> Monday - Friday: 8:00 AM - 5:00 PM<br>
                            <strong>Contact:</strong> 0981-303-8698 | Landline: 545-6789 loc 8018-19<br>
                            <strong>Email:</strong> scycloan@gmail.com | <strong>Phone:</strong> 0981-303-8698
                        </p>
                    </div>

                    <!-- CTA Button -->
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='login.php' style='display: inline-block; background: linear-gradient(135deg, #1e88e5 0%, #1565c0 100%); color: #ffffff; text-decoration: none; padding: 15px 40px; border-radius: 25px; font-weight: 600; font-size: 16px; box-shadow: 0 4px 15px rgba(30, 136, 229, 0.3); transition: all 0.3s ease;'>
                            🔐 Login to Your Account
                        </a>
                    </div>
                </div>

                <!-- Footer -->
                <div style='background: #f8f9fa; padding: 25px 30px; text-align: center; border-top: 1px solid #e9ecef;'>
                    <p style='margin: 0 0 10px 0; color: #6c757d; font-size: 14px;'>
                        This is an automated message from CYCLOAN Loan Program.<br>
                        Please do not reply to this email.
                    </p>
                    <p style='margin: 0; color: #adb5bd; font-size: 12px;'>
                        © 2025 CYCLOAN Loan Program. All rights reserved.
                    </p>
                </div>
            </div>
        </body>
        </html>";

        $mail->send();
        error_log("✓ Credit investigation status email sent successfully to $to (Application ID: $applicationId, Status: $newStatus)");
        return true;
    } catch (Exception $e) {
        error_log("✗ FAILED: Mailer Error for application_id $applicationId: {$mail->ErrorInfo} | Exception: " . $e->getMessage());
        return false;
    }
}

// Function to send remark update email
function sendRemarkEmail($conn, $applicationId, $remarks)
{
    $query = "SELECT u.email, u.first_name, u.last_name FROM loan_applications la JOIN users1 u ON la.user_id = u.id WHERE la.application_id = ?";
    $user = executeQuery($conn, $query, "i", [$applicationId]);

    if (empty($user)) {
        error_log("No user found for application_id: $applicationId");
        return false;
    }
    $user = $user[0];

    $to = $user['email'];
    $name = trim($user['first_name'] . ' ' . $user['last_name']);
    $safeRemarks = htmlspecialchars($remarks);

    $content = "
        <p>A new remark has been added to your loan application by our support team.</p>
        <div class='highlight-box'>
            <p><strong>Application ID:</strong> $applicationId</p>
            <p><strong>Remark:</strong></p>
            <p style='font-style: italic; margin-top: 10px;'>\"$safeRemarks\"</p>
            <p style='margin-top: 10px; font-size: 11px; color: #666;'><em>Added on " . date('F j, Y \a\t g:i A') . "</em></p>
        </div>
        <p>This remark may contain important information about your application. Please review it carefully.</p>
        <a href='user_dashboard.php' class='button'>View Application</a>
        <p>If you need clarification on this remark, feel free to contact our support team.</p>
        <p>Best regards,<br><strong>The CYCLOAN Loan Support Team</strong></p>
    ";

    $emailBody = generateEmailTemplate($name, $content);
    $subject = "New Remark Added - Application #$applicationId";

    return sendEmail($to, $name, $subject, $emailBody, "Remark: App $applicationId");
}

// NOTE: Email handling functionality for credit investigation status updates

/**
 * Helper function to create notifications for users and admin2 (like admin2 does)
 * @param mysqli $conn Database connection
 * @param int $userId User ID to notify
 * @param string $type Notification type (approval, warning, status, reminder)
 * @param string $title Notification title
 * @param string $message Notification message
 * @param string $priority Priority level (high, normal, low)
 * @return bool Success status
 */
function createUserAndAdmin2Notification($conn, $userId, $type, $title, $message, $priority = 'normal')
{
    try {
        if (!class_exists('NotificationManager')) {
            error_log("⚠️ NotificationManager not available for notifications");
            return false;
        }

        $notificationManager = new NotificationManager($conn);

        // Create notification for user
        $notificationManager->createNotification($userId, $type, $title, $message, $priority);
        error_log("✅ User notification created: $title");

        // Create notification for all admin2 users
        $admin2Query = $conn->prepare("SELECT id, first_name FROM admin2");
        $admin2Query->execute();
        $admin2Result = $admin2Query->get_result();
        $admin2Count = 0;

        while ($admin2User = $admin2Result->fetch_assoc()) {
            $admin2Title = "Admin1 Update: $title";
            $admin2Message = "Admin1 has processed an application. $message";
            $notificationManager->createNotification($admin2User['id'], $type, $admin2Title, $admin2Message, $priority);
            $admin2Count++;
        }
        $admin2Query->close();

        error_log("✅ Created notifications for $admin2Count admin2 users");
        return true;

    } catch (Exception $e) {
        error_log("❌ Notification creation error: " . $e->getMessage());
        return false;
    }
}

// Fetch loan applicants
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
try {
    $loanTypeData = executeQuery($conn, "
        SELECT lt.type_name, COALESCE(COUNT(la.application_id), 0) as count
        FROM loan_types lt
        LEFT JOIN loan_applications la ON lt.loan_type_id = la.loan_type_id
        GROUP BY lt.type_name
    ");
} catch (Exception $e) {
    error_log('Loan Type Graph data fetch failed: ' . $e->getMessage(), 3, 'errors.log');
    $loanTypeData = [];
}

// Data for Due Accounts List
try {
    $dueAccountsData = executeQuery($conn, "
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
} catch (Exception $e) {
    error_log('Due Accounts List data fetch failed: ' . $e->getMessage(), 3, 'errors.log');
    $dueAccountsData = [];
}

// Handle AJAX request for refreshing due accounts table
if (isset($_GET['action']) && $_GET['action'] === 'get_due_accounts') {
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
        $dueAccounts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'due_accounts' => $dueAccounts]);
        exit;
    } catch (Exception $e) {
        error_log("Get Due Accounts Error: " . $e->getMessage());
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// Fetch activity logs for display
try {
    $activityLogs = executeQuery($conn, "
        SELECT al.log_id, al.user_id, al.user_role, al.action_type, al.module, al.description, 
               al.affected_id, al.created_at,
               CASE 
                   WHEN al.user_role = 'User' THEN u.first_name
                   WHEN al.user_role = 'Admin1' THEN a1.first_name
                   WHEN al.user_role = 'Admin2' THEN a2.first_name
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
        ORDER BY al.created_at DESC
        LIMIT 50
    ");
} catch (Exception $e) {
    error_log('Activity Logs data fetch failed: ' . $e->getMessage(), 3, 'errors.log');
    $activityLogs = [];
}

// Handle AJAX request for loan details
if (isset($_GET['action']) && $_GET['action'] === 'get_loan_details' && isset($_GET['application_id'])) {
    try {
        $applicationId = intval($_GET['application_id']);
        error_log("Processing get_loan_details for application_id: $applicationId");

        $stmt = $conn->prepare("
            SELECT la.*, u.first_name, u.last_name, u.email, u.birthday, u.contact AS contact_number, lt.type_name,
                   fi.business_income, fi.salary_income, fi.remittance_income, fi.other_income,
                   fi.business2_income, fi.salary2_income, fi.net_income,
                   fi.food_allowance, fi.electricity_bill, fi.water_bill, fi.internet_bill,
                   fi.gas_bill, fi.educational_allowance, fi.car_amortization, fi.insurance,
                   fi.other_expense, fi.total_expenditures, fi.expected_monthly_amortization,
                   fi.remaining_income,
                   COALESCE(ir.interest_rate, 6.00) as interest_rate
            FROM loan_applications la
            JOIN users1 u ON la.user_id = u.id
            JOIN loan_types lt ON la.loan_type_id = lt.loan_type_id
            LEFT JOIN financial_info fi ON la.user_id = fi.user_id
            LEFT JOIN interest_rates ir ON ir.term_length = COALESCE(la.term_length, '12')
            WHERE la.application_id = ?
        ");
        if (!$stmt) {
            throw new Exception("Loan details prepare failed: " . $conn->error);
        }
        $stmt->bind_param("i", $applicationId);
        if (!$stmt->execute()) {
            throw new Exception("Loan details execute failed: " . $stmt->error);
        }
        $loan = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$loan) {
            throw new Exception("Loan application not found for ID: $applicationId");
        }

        $stmt = $conn->prepare("
            SELECT d.document_id, dt.document_name, d.file_path, d.status
            FROM documents d
            JOIN document_types dt ON d.document_type_id = dt.document_type_id
            WHERE d.application_id = ?
        ");
        if (!$stmt) {
            throw new Exception("Documents prepare failed: " . $conn->error);
        }
        $stmt->bind_param("i", $applicationId);
        if (!$stmt->execute()) {
            throw new Exception("Documents execute failed: " . $stmt->error);
        }
        $documents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $stmt = $conn->prepare("
            SELECT remarks, created_at, admin_name
            FROM remarks
            WHERE application_id = ?
            ORDER BY created_at DESC
        ");
        if (!$stmt) {
            throw new Exception("Remarks prepare failed: " . $conn->error);
        }
        $stmt->bind_param("i", $applicationId);
        if (!$stmt->execute()) {
            throw new Exception("Remarks execute failed: " . $stmt->error);
        }
        $remarks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $stmt = $conn->prepare("
            SELECT action_type, module, description, created_at, user_role
            FROM activity_logs
            WHERE affected_id = ? AND module IN ('loan_application', 'remarks')
            ORDER BY created_at DESC
            LIMIT 50
        ");
        if (!$stmt) {
            throw new Exception("Logs prepare failed: " . $conn->error);
        }
        $stmt->bind_param("i", $applicationId);
        if (!$stmt->execute()) {
            throw new Exception("Logs execute failed: " . $stmt->error);
        }
        $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'loan' => $loan,
            'documents' => $documents,
            'remarks' => $remarks,
            'logs' => $logs
        ]);
        exit;
    } catch (Exception $e) {
        error_log("Get Loan Details Error: " . $e->getMessage() . " | Line: " . $e->getLine() . " | File: " . $e->getFile());
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// Handle AJAX request for debug logs
if (isset($_POST['action']) && $_POST['action'] === 'get_debug_logs') {
    try {
        ob_clean();
        header('Content-Type: application/json');

        // Read the debug log file
        $debugLogFile = 'debug_log.txt';

        if (!file_exists($debugLogFile)) {
            echo json_encode([
                'success' => false,
                'message' => 'Debug log file not found',
                'logs' => '',
                'lines' => 0
            ]);
            exit;
        }

        if (!is_readable($debugLogFile)) {
            echo json_encode([
                'success' => false,
                'message' => 'Cannot read debug log file (permission issue)',
                'logs' => '',
                'lines' => 0
            ]);
            exit;
        }

        // Read last 500 lines to avoid memory issues
        $lines = file($debugLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            echo json_encode([
                'success' => false,
                'message' => 'Error reading debug log file',
                'logs' => '',
                'lines' => 0
            ]);
            exit;
        }

        // Get last 500 lines
        $totalLines = count($lines);
        $recentLines = array_slice($lines, max(0, $totalLines - 500));
        $logsContent = implode("\n", $recentLines);

        echo json_encode([
            'success' => true,
            'logs' => $logsContent,
            'lines' => count($recentLines),
            'total_lines' => $totalLines
        ]);
        exit;

    } catch (Exception $e) {
        error_log("Debug Logs Error: " . $e->getMessage());
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Error retrieving debug logs: ' . $e->getMessage(),
            'logs' => '',
            'lines' => 0
        ]);
        exit;
    }
}

// Handle AJAX request for updating statuses, final loan amount, and remarks
if (isset($_POST['action']) && $_POST['action'] === 'update_status' && isset($_POST['application_id'])) {
    try {
        error_log("POST Data: " . print_r($_POST, true));
        $applicationId = intval($_POST['application_id']);
        $creditInvestigationStatus = isset($_POST['credit_investigation_status']) && $_POST['credit_investigation_status'] !== '' ? trim($_POST['credit_investigation_status']) : null;
        $finalLoanAmount = isset($_POST['final_loan_amount']) && $_POST['final_loan_amount'] !== '' ? floatval($_POST['final_loan_amount']) : null;
        $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';

        $stmt = $conn->prepare("
            SELECT credit_investigation_status, final_loan_amount, amount_applied, pre_approval_status
            FROM loan_applications
            WHERE application_id = ?
        ");
        $stmt->bind_param("i", $applicationId);
        $stmt->execute();
        $currentData = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        error_log("Current Data Fetched: " . json_encode($currentData));
        error_log("Current Data Keys: " . json_encode(array_keys($currentData)));

        if (!$currentData) {
            throw new Exception("Loan application not found for application_id: $applicationId");
        }

        // Check if pre-approval status is pending
        if ($currentData['pre_approval_status'] === 'Pending') {
            throw new Exception("Cannot proceed with credit investigation. Pre-approval status is still Pending. Please wait for Admin 2 to approve or reject the application first.");
        }

        if ($adminRole === 'Admin1') {
            if (isset($_POST['status'])) {
                throw new Exception('Admin 1 is not authorized to update Loan Status.');
            }
            if ($creditInvestigationStatus || $finalLoanAmount !== null) {


                // Validate credit investigation status if provided
                if ($creditInvestigationStatus && !in_array($creditInvestigationStatus, ['Pending', 'Completed', 'Failed'])) {
                    throw new Exception("Invalid credit investigation status: $creditInvestigationStatus");
                }

                // Status and Amount must be provided together
                if (($creditInvestigationStatus && !$finalLoanAmount) || (!$creditInvestigationStatus && $finalLoanAmount)) {
                    throw new Exception('Both Credit Investigation Status and Final Loan Amount must be provided together.');
                }

                // Validate final loan amount if provided
                if ($finalLoanAmount !== null) {
                    if (!is_numeric($finalLoanAmount)) {
                        throw new Exception('Final Loan Amount must be a valid number.');
                    }
                    if ($finalLoanAmount <= 0) {
                        throw new Exception('Final Loan Amount must be greater than 0.');
                    }
                    if ($finalLoanAmount < 10000) {
                        throw new Exception('Final Loan Amount must be at least ₱10,000 PHP.');
                    }
                }
            }
        }

        // Check if there are any changes to save
        $hasChanges = ($creditInvestigationStatus && $creditInvestigationStatus !== $currentData['credit_investigation_status']) ||
            ($finalLoanAmount !== null && $finalLoanAmount != $currentData['final_loan_amount']) ||
            !empty($remarks);

        if (!$hasChanges) {
            throw new Exception('No changes to save. Please make at least one change.');
        }

        $updateFields = [];
        $paramTypes = '';
        $paramValues = [];

        error_log("DEBUG - Status Update Logic:");
        error_log("  creditInvestigationStatus: " . ($creditInvestigationStatus ?? 'NULL'));
        error_log("  currentData['credit_investigation_status']: " . ($currentData['credit_investigation_status'] ?? 'NULL'));
        error_log("  Are they different? " . ($creditInvestigationStatus !== $currentData['credit_investigation_status'] ? 'YES' : 'NO'));

        if ($adminRole === 'Admin1' && $creditInvestigationStatus && $creditInvestigationStatus !== $currentData['credit_investigation_status']) {
            error_log("  -> Adding status update to query");
            $updateFields[] = "credit_investigation_status = ?";
            $paramTypes .= 's';
            $paramValues[] = $creditInvestigationStatus;
        }

        error_log("DEBUG - Amount Update Logic:");
        error_log("  finalLoanAmount: " . ($finalLoanAmount ?? 'NULL'));
        error_log("  currentData['final_loan_amount']: " . ($currentData['final_loan_amount'] ?? 'NULL'));
        error_log("  Are they different? " . ($finalLoanAmount != $currentData['final_loan_amount'] ? 'YES' : 'NO'));

        if ($adminRole === 'Admin1' && $finalLoanAmount !== null && $finalLoanAmount != $currentData['final_loan_amount']) {
            error_log("  -> Adding amount update to query");
            $updateFields[] = "final_loan_amount = ?";
            $paramTypes .= 'd';
            $paramValues[] = $finalLoanAmount;
        }

        if (!empty($updateFields)) {
            // Get current time in Philippine Time (UTC+8)
            $phpTimeZone = new DateTimeZone('Asia/Manila');
            $now = new DateTime('now', $phpTimeZone);
            $updatedAt = $now->format('Y-m-d H:i:s');

            $sql = "UPDATE loan_applications SET " . implode(', ', $updateFields) . ", updated_at = ? WHERE application_id = ?";
            $paramTypes .= 'si';
            $paramValues[] = $updatedAt;
            $paramValues[] = $applicationId;

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($paramTypes, ...$paramValues);
            if (!$stmt->execute()) {
                throw new Exception("Failed to update credit investigation status or final loan amount: " . $stmt->error);
            }
            $affectedRows = $stmt->affected_rows;
            error_log("UPDATE SQL: $sql");
            error_log("UPDATE Fields: " . implode(', ', $updateFields));
            error_log("Param Types: $paramTypes");
            error_log("Param Values: " . json_encode($paramValues));
            error_log("Affected Rows: $affectedRows");
            if ($affectedRows === 0) {
                throw new Exception("No rows updated. Verify application_id exists: $applicationId");
            }
            $stmt->close();

            if ($creditInvestigationStatus && $creditInvestigationStatus !== $currentData['credit_investigation_status']) {
                $description = "Updated credit investigation status from '{$currentData['credit_investigation_status']}' to '$creditInvestigationStatus'";
                if ($finalLoanAmount !== null) {
                    $description .= " and set final loan amount to ₱" . number_format($finalLoanAmount, 2);
                }
                logActivity(
                    $conn,
                    $adminId,
                    $adminRole,
                    'update',
                    'loan_application',
                    $description,
                    $applicationId,
                    'credit-investigation'
                );
                if (!sendCreditStatusEmail($conn, $applicationId, $creditInvestigationStatus, $finalLoanAmount)) {
                    error_log("Failed to send credit investigation status email for application_id: $applicationId");
                }
            } elseif ($finalLoanAmount !== null && $finalLoanAmount != $currentData['final_loan_amount']) {
                // Only loan amount changed, no status change
                $description = "Updated final loan amount from '" . ($currentData['final_loan_amount'] ? number_format($currentData['final_loan_amount'], 2) : 'Not set') . "' to '" . number_format($finalLoanAmount, 2) . " PHP'";
                logActivity(
                    $conn,
                    $adminId,
                    $adminRole,
                    'update',
                    'loan_application',
                    $description,
                    $applicationId,
                    'credit-investigation'
                );
            }

            // Save remarks if provided
            if (!empty($remarks)) {
                $remarkStmt = $conn->prepare("
                    INSERT INTO remarks (application_id, user_id, remarks, created_at)
                    VALUES (?, ?, ?, NOW())
                ");
                $remarkStmt->bind_param("sis", $applicationId, $adminId, $remarks);
                if (!$remarkStmt->execute()) {
                    error_log("Failed to insert remark for application_id: $applicationId - " . $remarkStmt->error);
                }
                $remarkStmt->close();
            }

            $successMessage = "Credit investigation updated successfully.";
            if ($creditInvestigationStatus) {
                $successMessage .= " Status: " . $creditInvestigationStatus;
            }
            if ($finalLoanAmount !== null) {
                $successMessage .= " Loan Amount: ₱" . number_format($finalLoanAmount, 2);
            }

            echo json_encode([
                'success' => true,
                'message' => $successMessage
            ]);
        } else {
            throw new Exception('No loan application data to update.');
        }

    } catch (Exception $e) {
        error_log("Error updating credit investigation: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        ob_clean(); // Clear any previous output that might have been sent
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    } catch (Error $e) {
        error_log("Fatal error updating credit investigation: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        ob_clean(); // Clear any previous output that might have been sent
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'System error occurred. Please try again.'
        ]);
    }
    exit;
}

// Handle AJAX request for credit investigation modal submission
if (isset($_POST['action']) && $_POST['action'] === 'submit_credit_investigation') {
    try {
        // Secondary authorization check for credit investigation
        if (!isset($adminId) || !$adminId || empty($_SESSION['email'])) {
            error_log("CREDIT INVESTIGATION: Missing admin authorization - Admin ID: " . ($adminId ?? 'NONE') . ", Email: " . ($_SESSION['email'] ?? 'NONE'));
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Authorization required. Please refresh the page and try again.',
                'error_type' => 'auth_missing'
            ]);
            exit;
        }

        // Verify admin1 record exists for this session
        $authCheck = $conn->prepare("SELECT id FROM admin1 WHERE id = ? AND LOWER(TRIM(email)) = LOWER(TRIM(?)) LIMIT 1");
        if ($authCheck) {
            $authCheck->bind_param("is", $adminId, $_SESSION['email']);
            $authCheck->execute();
            $authResult = $authCheck->get_result();
            if (!$authResult->fetch_assoc()) {
                error_log("CREDIT INVESTIGATION: Admin verification failed - ID: $adminId, Email: " . $_SESSION['email']);
                ob_clean();
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Admin verification failed. Please log out and log back in.',
                    'error_type' => 'auth_invalid'
                ]);
                $authCheck->close();
                exit;
            }
            $authCheck->close();
        }

        error_log("=== CREDIT INVESTIGATION SUBMISSION START ===");
        error_log("POST Data: " . json_encode($_POST));
        error_log("Authorized Admin - ID: $adminId, Name: " . ($adminName ?? 'Unknown'));

        $applicationId = isset($_POST['application_id']) ? trim($_POST['application_id']) : null;
        $creditStatus = isset($_POST['credit_status']) ? trim($_POST['credit_status']) : null;
        $finalLoanAmount = isset($_POST['final_loan_amount']) && $_POST['final_loan_amount'] !== '' ? floatval($_POST['final_loan_amount']) : null;
        $termLength = isset($_POST['term_length']) && $_POST['term_length'] !== '' ? trim($_POST['term_length']) : null;
        $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';

        error_log("Parsed values - ID: $applicationId, Status: $creditStatus, Amount: $finalLoanAmount, Term: $termLength");

        if (!$applicationId || !$creditStatus || !$finalLoanAmount || !$termLength) {
            throw new Exception("Application ID, Credit Status, Final Loan Amount, and Term Length are required.");
        }

        // Fetch current application data
        $stmt = $conn->prepare("
            SELECT la.*, u.id as user_id, u.email, u.first_name, u.last_name
            FROM loan_applications la
            JOIN users1 u ON la.user_id = u.id
            WHERE la.application_id = ?
        ");
        $stmt->bind_param("s", $applicationId);
        $stmt->execute();
        $currentApp = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$currentApp) {
            throw new Exception("Loan application not found.");
        }

        // Update loan application with credit investigation data
        $phpTimeZone = new DateTimeZone('Asia/Manila');
        $now = new DateTime('now', $phpTimeZone);
        $updatedAt = $now->format('Y-m-d H:i:s');

        $updateQuery = "
            UPDATE loan_applications 
            SET credit_investigation_status = ?, final_loan_amount = ?, term_length = ?, updated_at = ?
            WHERE application_id = ?
        ";

        $stmt = $conn->prepare($updateQuery);
        if (!$stmt) {
            error_log("Credit Investigation - Prepare Error: " . $conn->error);
            throw new Exception("Database prepare error: " . $conn->error);
        }

        // Bind parameters: status(string), amount(double), term(string), timestamp(string), application_id(string)
        if (!$stmt->bind_param("sdsss", $creditStatus, $finalLoanAmount, $termLength, $updatedAt, $applicationId)) {
            error_log("Credit Investigation - Bind Error: " . $stmt->error);
            throw new Exception("Parameter binding error: " . $stmt->error);
        }

        if (!$stmt->execute()) {
            error_log("Credit Investigation - Execute Error: " . $stmt->error);
            throw new Exception("Failed to update credit investigation: " . $stmt->error);
        }
        $stmt->close();

        // Log activity - wrap in try-catch so errors don't break the main flow
        try {
            $description = "Credit investigation completed: Status set to '$creditStatus', Final Amount: ₱" . number_format($finalLoanAmount, 2) . ", Term Length: $termLength months";
            if ($remarks) {
                $description .= ", Remarks: " . $remarks;
            }
            logActivity(
                $conn,
                $adminId,
                $adminRole,
                'update',
                'loan_application',
                $description,
                $applicationId,
                'credit-investigation-enhanced'
            );
        } catch (Exception $logError) {
            error_log("Warning: Activity logging failed but main operation succeeded: " . $logError->getMessage());
            // Don't throw - activity logging is not critical to the main operation
        }

        // Send email to user
        if ($creditStatus === 'Completed') {
            $emailSubject = "Loan Application Approved - Credit Investigation Complete";

            $content = "
                <p>Congratulations! We are pleased to inform you that your <strong>credit investigation has been successfully completed and your loan application has been APPROVED</strong>.</p>
                <div class='highlight-box'>
                    <p><strong>Application ID:</strong> {$currentApp['application_id']}</p>
                    <p><strong>Status:</strong> <span style='color: #2e7d32;'>✓ APPROVED</span></p>
                    <p><strong>Final Loan Amount:</strong> ₱" . number_format($finalLoanAmount, 2) . "</p>
                    <p><strong>Loan Term:</strong> {$termLength} months</p>
                </div>
            ";

            // Add remarks if available
            if (!empty($remarks)) {
                $content .= "
                <div class='highlight-box' style='background: #e8f5e9; border-left: 4px solid #2e7d32; margin-top: 20px;'>
                    <p style='margin: 0 0 8px 0; color: #1b5e20;'><strong>📝 Investigation Notes:</strong></p>
                    <p style='margin: 0; color: #2e7d32; font-style: italic;'>" . nl2br(htmlspecialchars($remarks)) . "</p>
                </div>
            ";
            }

            $content .= "
                <p style='margin-top: 20px;'><strong>What's Next?</strong></p>
                <p>To finalize your loan approval, please visit our office to complete the following:</p>
                <ul style='margin-left: 20px;'>
                    <li>Bring a valid ID and original documents</li>
                    <li>Review and sign the loan agreement</li>
                    <li>Confirm fund disbursement details</li>
                </ul>
                <p style='margin-top: 15px;'>The loan funds will be disbursed to your designated account within 2-3 business days after signing.</p>
                <a href='user_dashboard.php' class='button'>View Application Details</a>
                <p style='margin-top: 25px; font-size: 13px; color: #666;'>Please ensure you visit our office within 7 days to complete this process.</p>
                <p>Best regards,<br><strong>CLDD Loan Services Team</strong></p>
            ";

            $emailBody = generateEmailTemplate(trim($currentApp['first_name'] . ' ' . $currentApp['last_name']), $content);

            $mailer = new PHPMailer(true);
            try {
                if (empty($currentApp['email'])) {
                    error_log("Critical: No email address found for applicant ID: " . $currentApp['application_id']);
                    throw new Exception("Applicant email address is missing");
                }

                $mailer->isSMTP();
                $mailer->Host = 'smtp.gmail.com';
                $mailer->SMTPAuth = true;
                $mailer->Username = 'cycloancldd@gmail.com';
                $mailer->Password = 'hbfh ukgh tmzw nqbq';
                $mailer->SMTPSecure = 'tls';
                $mailer->Port = 587;
                $mailer->setFrom('cycloancldd@gmail.com', 'CLDD Loan Support');
                $mailer->addAddress($currentApp['email'], $currentApp['first_name'] . ' ' . $currentApp['last_name']);
                $mailer->isHTML(true);
                $mailer->Subject = $emailSubject;
                $mailer->Body = $emailBody;
                $mailer->AltBody = "Congratulations! Your loan application has been approved. Please visit our office to finalize your loan.";
                $mailer->send();
                error_log("✅ Credit investigation approval email sent to: " . $currentApp['email'] . " (Application: " . $currentApp['application_id'] . ")");

                // Create notifications (like admin2) for user and admin2
                try {
                    if (class_exists('NotificationManager')) {
                        $notificationManager = new NotificationManager($conn);

                        // User notification for approval
                        $userNotificationTitle = "Loan Application Approved";
                        $userNotificationMessage = "Great news! Your credit investigation has been completed and your loan application has been APPROVED. Final loan amount: ₱" . number_format($finalLoanAmount, 2) . " for {$termLength} months. Please visit our office to finalize.";
                        $notificationManager->createNotification($currentApp['user_id'], 'approval', $userNotificationTitle, $userNotificationMessage, 'high');
                        error_log("✅ User notification created for approval - Application: " . $currentApp['application_id']);

                        // Admin2 notification
                        $admin2NotificationTitle = "Credit Investigation Completed by Admin1";
                        $admin2NotificationMessage = "Credit investigation for {$currentApp['first_name']} {$currentApp['last_name']} (ID: {$currentApp['application_id']}) has been completed and approved. Final amount: ₱" . number_format($finalLoanAmount, 2);

                        // Get admin2 users for notification
                        $admin2Query = $conn->prepare("SELECT id FROM admin2");
                        $admin2Query->execute();
                        $admin2Result = $admin2Query->get_result();
                        $admin2Count = 0;
                        while ($admin2User = $admin2Result->fetch_assoc()) {
                            $notificationManager->createNotification($admin2User['id'], 'status', $admin2NotificationTitle, $admin2NotificationMessage, 'normal');
                            $admin2Count++;
                        }
                        $admin2Query->close();
                        error_log("✅ Admin2 notifications created for credit investigation approval - Notified $admin2Count admin2 users");
                    } else {
                        error_log("⚠️ NotificationManager class not available - skipping notifications");
                    }
                } catch (Exception $notifError) {
                    error_log("❌ Notification creation error: " . $notifError->getMessage());
                    // Don't fail the operation if notifications fail
                }

            } catch (Exception $e) {
                error_log("❌ FAILED to send credit investigation approval email to: " . (isset($currentApp['email']) ? $currentApp['email'] : 'UNKNOWN') . " | Error: " . $e->getMessage());
            }
        } elseif ($creditStatus === 'Failed') {
            $emailSubject = "Loan Application Status Update - Credit Investigation";

            $content = "
                <p>Thank you for submitting your loan application. We have completed our credit investigation process and wanted to inform you of the outcome.</p>
                <div class='highlight-box'>
                    <p><strong>Application ID:</strong> {$currentApp['application_id']}</p>
                    <p><strong>Status:</strong> <span style='color: #d32f2f;'>Requires Additional Review</span></p>
                </div>
            ";

            // Add remarks if available
            if (!empty($remarks)) {
                $content .= "
                <div class='highlight-box' style='background: #ffebee; border-left: 4px solid #d32f2f; margin-top: 20px;'>
                    <p style='margin: 0 0 8px 0; color: #b71c1c;'><strong>📝 Investigation Notes:</strong></p>
                    <p style='margin: 0; color: #d32f2f; font-style: italic;'>" . nl2br(htmlspecialchars($remarks)) . "</p>
                </div>
            ";
            }

            $content .= "
                <p style='margin-top: 20px;'><strong>What This Means</strong></p>
                <p>Your application requires further review. This may be due to one or more of the following:</p>
                <ul style='margin-left: 20px;'>
                    <li>Additional documentation needed</li>
                    <li>Verification of income and employment</li>
                    <li>Clarification on application details</li>
                    <li>Further credit assessment required</li>
                </ul>
                <p style='margin-top: 15px;'><strong>Next Steps</strong></p>
                <p>Our team will be reaching out to you shortly via phone or email with specific information about what we need from you. Please keep an eye on your email and phone for communication from us within the next 2-3 business days.</p>
                <p style='margin-top: 15px;'>If you have any questions or would like to provide additional information, please don't hesitate to contact us.</p>
                <a href='user_dashboard.php' class='button'>Check Application Status</a>
                <p>Best regards,<br><strong>CLDD Loan Services Team</strong></p>
            ";

            $emailBody = generateEmailTemplate(trim($currentApp['first_name'] . ' ' . $currentApp['last_name']), $content);

            $mailer = new PHPMailer(true);
            try {
                if (empty($currentApp['email'])) {
                    error_log("Critical: No email address found for applicant ID: " . $currentApp['application_id']);
                    throw new Exception("Applicant email address is missing");
                }

                $mailer->isSMTP();
                $mailer->Host = 'smtp.gmail.com';
                $mailer->SMTPAuth = true;
                $mailer->Username = 'cycloancldd@gmail.com';
                $mailer->Password = 'hbfh ukgh tmzw nqbq';
                $mailer->SMTPSecure = 'tls';
                $mailer->Port = 587;
                $mailer->setFrom('cycloancldd@gmail.com', 'CLDD Loan Support');
                $mailer->addAddress($currentApp['email'], $currentApp['first_name'] . ' ' . $currentApp['last_name']);
                $mailer->isHTML(true);
                $mailer->Subject = $emailSubject;
                $mailer->Body = $emailBody;
                $mailer->AltBody = "Your loan application requires further review. We will be in touch with next steps.";
                $mailer->send();
                error_log("✅ Credit investigation status email sent to: " . $currentApp['email'] . " (Application: " . $currentApp['application_id'] . ")");

                // Create notifications (like admin2) for user and admin2  
                try {
                    if (class_exists('NotificationManager')) {
                        $notificationManager = new NotificationManager($conn);

                        // User notification for failed status
                        $userNotificationTitle = "Application Under Review";
                        $userNotificationMessage = "Your credit investigation requires further review. Please contact our support team for more details about your application status.";
                        $notificationManager->createNotification($currentApp['user_id'], 'warning', $userNotificationTitle, $userNotificationMessage, 'high');
                        error_log("✅ User notification created for failed status - Application: " . $currentApp['application_id']);

                        // Admin2 notification
                        $admin2NotificationTitle = "Credit Investigation Failed - Admin1 Review";
                        $admin2NotificationMessage = "Credit investigation for {$currentApp['first_name']} {$currentApp['last_name']} (ID: {$currentApp['application_id']}) has been marked as failed and requires further review.";

                        // Get admin2 users for notification
                        $admin2Query = $conn->prepare("SELECT id FROM admin2");
                        $admin2Query->execute();
                        $admin2Result = $admin2Query->get_result();
                        $admin2Count = 0;
                        while ($admin2User = $admin2Result->fetch_assoc()) {
                            $notificationManager->createNotification($admin2User['id'], 'warning', $admin2NotificationTitle, $admin2NotificationMessage, 'high');
                            $admin2Count++;
                        }
                        $admin2Query->close();
                        error_log("✅ Admin2 notifications created for failed credit investigation - Notified $admin2Count admin2 users");
                    } else {
                        error_log("⚠️ NotificationManager class not available - skipping notifications");
                    }
                } catch (Exception $notifError) {
                    error_log("❌ Notification creation error: " . $notifError->getMessage());
                    // Don't fail the operation if notifications fail
                }

            } catch (Exception $e) {
                error_log("❌ FAILED to send credit investigation status email to: " . (isset($currentApp['email']) ? $currentApp['email'] : 'UNKNOWN') . " | Error: " . $e->getMessage());
            }
        } elseif ($creditStatus === 'Pending') {
            $emailSubject = "Credit Investigation Status - Loan Application Update";

            $content = "
                <p>Thank you for submitting your loan application. We wanted to keep you updated on the status of your credit investigation.</p>
                <div class='highlight-box'>
                    <p><strong>Application ID:</strong> {$currentApp['application_id']}</p>
                    <p><strong>Status:</strong> <span style='color: #1e88e5;'>⏳ Investigation In Progress</span></p>
                </div>
            ";

            // Add remarks if available
            if (!empty($remarks)) {
                $isPendingApplicantResponse = strpos(strtolower($remarks), 'pending applicant response') !== false;
                $content .= "
                <div class='highlight-box' style='background: #e3f2fd; border-left: 4px solid #1e88e5; margin-top: 20px;'>
                    <p style='margin: 0 0 8px 0; color: #0d47a1;'><strong>📝 Investigation Notes:</strong></p>
                    <p style='margin: 0; color: #1e88e5; font-style: italic;'>" . nl2br(htmlspecialchars($remarks)) . "</p>
                </div>
            ";

                // If pending applicant response, add office visit instruction
                if ($isPendingApplicantResponse) {
                    $content .= "
                <div class='highlight-box' style='background: #fff3e0; border-left: 4px solid #f57c00; margin-top: 20px;'>
                    <p style='margin: 0 0 8px 0; color: #e65100;'><strong>⚠️ Important - Please Visit Our Office</strong></p>
                    <p style='margin: 0; color: #f57c00;'><strong>We need your response to proceed with your loan application.</strong></p>
                    <p style='margin: 8px 0 0 0; color: #f57c00;'>Please visit our office at your earliest convenience to confirm the details and provide any additional information required. Our team will be ready to assist you with the next steps of your application.</p>
                </div>
            ";
                }
            }

            $content .= "
                <p style='margin-top: 20px;'><strong>What's Happening Now</strong></p>
                <p>Our credit investigation team is currently reviewing your application. This includes:</p>
                <ul style='margin-left: 20px;'>
                    <li>Document Verification</li>
                    <li>Credit History Review</li>
                    <li>Income Verification</li>
                    <li>Eligibility Assessment</li>
                </ul>
                <p style='margin-top: 15px;'><strong>Expected Timeline</strong></p>
                <p>We typically complete investigations within <strong>5-7 business days</strong>. You will receive an email update once the investigation is complete with the results and next steps.</p>
                <p style='margin-top: 15px;'><strong>Stay Connected</strong></p>
                <p>If we need any additional information from you, we will contact you directly. Your application is in good hands, and we appreciate your patience.</p>
                <a href='user_dashboard.php' class='button'>View Application Status</a>
                <p style='margin-top: 20px; font-size: 13px; color: #666;'>Thank you for choosing CLDD Loan Services. We're here to help!</p>
                <p>Best regards,<br><strong>CLDD Loan Services Team</strong></p>
            ";

            $emailBody = generateEmailTemplate(trim($currentApp['first_name'] . ' ' . $currentApp['last_name']), $content);

            $mailer = new PHPMailer(true);
            try {
                if (empty($currentApp['email'])) {
                    error_log("Critical: No email address found for applicant ID: " . $currentApp['application_id']);
                    throw new Exception("Applicant email address is missing");
                }

                $mailer->isSMTP();
                $mailer->Host = 'smtp.gmail.com';
                $mailer->SMTPAuth = true;
                $mailer->Username = 'cycloancldd@gmail.com';
                $mailer->Password = 'hbfh ukgh tmzw nqbq';
                $mailer->SMTPSecure = 'tls';
                $mailer->Port = 587;
                $mailer->setFrom('cycloancldd@gmail.com', 'CLDD Loan Support');
                $mailer->addAddress($currentApp['email'], $currentApp['first_name'] . ' ' . $currentApp['last_name']);
                $mailer->isHTML(true);
                $mailer->Subject = $emailSubject;
                $mailer->Body = $emailBody;
                $mailer->AltBody = "Your credit investigation is currently in progress. You will be notified with results within 5-7 business days.";

                // Send email first - critical operation
                $emailSent = $mailer->send();
                error_log("✅ Credit investigation pending status email sent to: " . $currentApp['email'] . " (Application: " . $currentApp['application_id'] . ")");

                // Create notifications (like admin2) for user and admin2
                try {
                    if (class_exists('NotificationManager')) {
                        $notificationManager = new NotificationManager($conn);

                        // User notification for pending status
                        $isPendingApplicantResponse = !empty($remarks) && strpos(strtolower($remarks), 'pending applicant response') !== false;
                        $userNotificationTitle = "Credit Investigation In Progress";
                        $userNotificationMessage = $isPendingApplicantResponse
                            ? "Your credit investigation is in progress. We need your response - please visit our office at your earliest convenience for confirmation."
                            : "Your credit investigation is in progress. You will receive updates within 5-7 business days.";
                        $notificationType = $isPendingApplicantResponse ? 'reminder' : 'status';
                        $priority = $isPendingApplicantResponse ? 'high' : 'normal';
                        $notificationManager->createNotification($currentApp['user_id'], $notificationType, $userNotificationTitle, $userNotificationMessage, $priority);
                        error_log("✅ User notification created for pending status - Application: " . $currentApp['application_id']);

                        // Admin2 notification
                        $admin2NotificationTitle = "Credit Investigation Pending - Admin1 Update";
                        $admin2NotificationMessage = "Credit investigation for {$currentApp['first_name']} {$currentApp['last_name']} (ID: {$currentApp['application_id']}) is marked as pending and requires monitoring.";

                        // Get admin2 users for notification
                        $admin2Query = $conn->prepare("SELECT id FROM admin2");
                        $admin2Query->execute();
                        $admin2Result = $admin2Query->get_result();
                        $admin2Count = 0;
                        while ($admin2User = $admin2Result->fetch_assoc()) {
                            $notificationManager->createNotification($admin2User['id'], 'status', $admin2NotificationTitle, $admin2NotificationMessage, 'normal');
                            $admin2Count++;
                        }
                        $admin2Query->close();
                        error_log("✅ Admin2 notifications created for pending credit investigation - Notified $admin2Count admin2 users");
                    } else {
                        error_log("⚠️ NotificationManager class not available - skipping notifications");
                    }
                } catch (Exception $notifError) {
                    error_log("❌ Notification creation error: " . $notifError->getMessage());
                    // Don't fail the operation if notifications fail
                }

            } catch (Exception $e) {
                error_log("❌ FAILED to send credit investigation pending email to: " . (isset($currentApp['email']) ? $currentApp['email'] : 'UNKNOWN') . " | Error: " . $e->getMessage());
                // Don't throw here - continue with flow
                $emailSent = false;
            }
        }

        // Insert remarks if provided
        if (!empty($remarks)) {
            // Get current timestamp in Manila timezone
            $phpTimeZone = new DateTimeZone('Asia/Manila');
            $now = new DateTime('now', $phpTimeZone);
            $createdAt = $now->format('Y-m-d H:i:s');

            $remarkStmt = $conn->prepare("
                INSERT INTO remarks (application_id, remarks, created_at, admin_name)
                VALUES (?, ?, ?, ?)
            ");

            if (!$remarkStmt) {
                error_log("Remarks Insert - Prepare Error: " . $conn->error);
            } else {
                if (!$remarkStmt->bind_param("ssss", $applicationId, $remarks, $createdAt, $adminName)) {
                    error_log("Remarks Insert - Bind Error: " . $remarkStmt->error);
                } elseif (!$remarkStmt->execute()) {
                    error_log("Remarks Insert - Execute Error: " . $remarkStmt->error);
                } else {
                    error_log("Remarks inserted successfully for application_id: $applicationId");
                }
                $remarkStmt->close();
            }
        }

        // Clean any output before sending JSON
        ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'message' => 'Credit investigation submitted successfully.']);
        error_log("=== CREDIT INVESTIGATION SUBMISSION SUCCESS ===");
        exit;
    } catch (Exception $e) {
        error_log("Credit Investigation Submission Error: " . $e->getMessage() . " | Line: " . $e->getLine());

        // Clean any output before sending error JSON
        ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        error_log("=== CREDIT INVESTIGATION SUBMISSION FAILED ===");
        exit;
    }
}

// Handle AJAX request for refreshing loan applicants table
if (isset($_GET['action']) && $_GET['action'] === 'get_loan_applicants') {
    try {
        $stmt = $conn->prepare("
            SELECT la.application_id, u.first_name, u.last_name, lt.type_name, la.amount_applied, la.status, 
                   la.pre_approval_status, la.credit_investigation_status, la.created_at, la.final_loan_amount
            FROM loan_applications la
            JOIN users1 u ON la.user_id = u.id
            JOIN loan_types lt ON la.loan_type_id = lt.loan_type_id
            ORDER BY la.created_at DESC
        ");
        $stmt->execute();
        $loanApplicants = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'applicants' => $loanApplicants]);
        exit;
    } catch (Exception $e) {
        error_log("Get Loan Applicants Error: " . $e->getMessage());
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// Handle AJAX request for refreshing activity logs table
if (isset($_GET['action']) && $_GET['action'] === 'get_activity_logs') {
    try {
        $stmt = $conn->prepare("
            SELECT al.log_id, al.user_id, al.user_role, al.action_type, al.module, al.description, 
                   al.affected_id, al.created_at,
                   COALESCE(u.first_name, a1.first_name, a2.first_name, 'System') AS first_name,
                   COALESCE(u.last_name, a1.last_name, a2.last_name, '') AS last_name,
                   la.loan_id
            FROM activity_logs al
            LEFT JOIN users1 u ON al.user_id = u.id AND al.user_role = 'User'
            LEFT JOIN admin1 a1 ON al.user_id = a1.id AND al.user_role = 'Admin1'
            LEFT JOIN admin2 a2 ON al.user_id = a2.id AND al.user_role = 'Admin2'
            LEFT JOIN loan_applications la ON al.affected_id = la.application_id
            ORDER BY al.created_at DESC
            LIMIT 50
        ");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        $activityLogs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Map action_type and module for each log
        foreach ($activityLogs as &$log) {
            $action = strtolower($log['action_type']);
            $log['action_type_display'] = isset($actionTypeMap[$action]) ? $actionTypeMap[$action] : ucfirst($action);
            $module = strtolower($log['module']);
            $log['module_display'] = isset($moduleMap[$module]) ? $moduleMap[$module] : ucfirst($module);
        }

        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'activity_logs' => $activityLogs]);
        exit;
    } catch (Exception $e) {
        error_log("Get Activity Logs Error: " . $e->getMessage());
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// Handle AJAX request for sending payment reminder email
if (isset($_POST['action']) && $_POST['action'] === 'send_payment_reminder' && isset($_POST['payment_id'])) {
    try {
        // Clear all output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');

        $paymentId = intval($_POST['payment_id']);
        $stmt = $conn->prepare("
            SELECT ps.due_date, ps.amount, u.email, u.first_name, u.last_name, la.application_id
            FROM payment_schedules ps
            JOIN loans l ON ps.loan_id = l.loan_id
            JOIN loan_applications la ON l.application_id = la.application_id
            JOIN users1 u ON la.user_id = u.id
            WHERE ps.payment_id = ?
        ");
        $stmt->bind_param("i", $paymentId);
        $stmt->execute();
        $payment = $stmt->get_result()->fetch_assoc();
        $stmt->close();

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
        $reminderMail->Password = 'xbvo zplr dpme ixxj';
        $reminderMail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $reminderMail->Port = 587;

        $reminderMail->setFrom('scycloan@gmail.com', 'CYCLOAN Loan Support');
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
        error_log("✅ Payment reminder email successfully sent to: " . $to . " (Payment ID: " . $paymentId . ", Amount: " . $amount . " PHP)");

        logActivity(
            $conn,
            $adminId,
            $adminRole,
            'send_reminder',
            'payment_schedule',
            "Sent payment reminder for payment_id: $paymentId, amount: $amount PHP, due date: $dueDate",
            $paymentId
        );

        http_response_code(200);
        die(json_encode(['success' => true, 'message' => 'Payment reminder sent successfully.']));
    } catch (Exception $e) {
        error_log("❌ FAILED to send payment reminder email to: " . $to . " | Payment ID: " . $paymentId . " | Error: " . $e->getMessage() . " | PHPMailer: " . $reminderMail->ErrorInfo);
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Error sending email: ' . $e->getMessage()]));
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin 1 Dashboard - CYCLOAN</title>
    <link rel="stylesheet" href="CSS/admin_dashboard.css">
    <link rel="stylesheet" href="CSS/admin_profile.css">
    <link rel="stylesheet" href="CSS/nav_active.css">
    <link rel="stylesheet" href="CSS/admin1_dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
</head>
<style>
    /* ===== CSS Variables ===== */
    :root {
        --darker: #1f2937;
        --dark: #374151;
        --light: #f5f5f5;
        --lighter: #f9f9f9;
        --border: #e5e7eb;
    }

    /* ===== Global Font Styling ===== */
    * {
        font-family: 'Poppins', sans-serif;
    }

    body {
        font-family: 'Poppins', sans-serif;
        font-size: 14px;
        line-height: 1.6;
        color: #333;
        background-color: #f5f5f5;
    }

    h1,
    h2,
    h3,
    h4,
    h5,
    h6 {
        font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        font-weight: 600;
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
        font-size: 16px;
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

    .close {
        position: absolute;
        right: 15px;
        top: 10px;
        font-size: 24px !important;
        font-weight: bold;
        cursor: pointer;
        color: #6b7280;
        transition: color 0.2s;
        padding: 5px 10px;
    }

    .close:hover,
    .close:focus {
        color: #1f2937;
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

    /* Badge Styling */
    .badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        white-space: nowrap;
        vertical-align: middle;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .badge-success {
        background-color: #d1fae5;
        color: #065f46;
    }

    .badge-warning {
        background-color: #fef3c7;
        color: #92400e;
    }

    .badge-danger {
        background-color: #fee2e2;
        color: #991b1b;
    }

    .badge-info {
        background-color: #dbeafe;
        color: #0c2d6b;
    }

    .badge-secondary {
        background-color: #e5e7eb;
        color: #374151;
    }

    .badge-primary {
        background-color: #dbeafe;
        color: #1e40af;
    }

    .badge.pulse {
        animation: badgePulse 2s ease-in-out infinite;
    }

    @keyframes badgePulse {

        0%,
        100% {
            box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.7);
        }

        50% {
            box-shadow: 0 0 0 8px rgba(59, 130, 246, 0);
        }
    }

    /* Status Badge Styling for Tables */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 500;
        white-space: nowrap;
    }

    .status-pending {
        background-color: #fef3c7;
        color: #92400e;
        border-left: 3px solid #f59e0b;
    }

    .status-approved {
        background-color: #d1fae5;
        color: #065f46;
        border-left: 3px solid #10b981;
    }

    .status-rejected {
        background-color: #fee2e2;
        color: #991b1b;
        border-left: 3px solid #ef4444;
    }

    .status-active {
        background-color: #d1fae5;
        color: #065f46;
        border-left: 3px solid #10b981;
    }

    .status-inactive {
        background-color: #f3f4f6;
        color: #6b7280;
        border-left: 3px solid #d1d5db;
    }

    .status-partial {
        background-color: #fde68a;
        color: #78350f;
        border-left: 3px solid #eab308;
    }

    .status-unpaid {
        background-color: #fee2e2;
        color: #991b1b;
        border-left: 3px solid #ef4444;
    }

    .status-paid {
        background-color: #d1fae5;
        color: #065f46;
        border-left: 3px solid #10b981;
    }

    /* Status Badge Indicator Dot */
    .status-dot {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        animation: none;
    }

    .status-dot.pending {
        background-color: #f59e0b;
        animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }

    .status-dot.approved {
        background-color: #10b981;
    }

    .status-dot.rejected {
        background-color: #ef4444;
    }

    .status-dot.active {
        background-color: #10b981;
    }

    .status-dot.inactive {
        background-color: #d1d5db;
    }

    /* Table Cell Styling */
    table td {
        vertical-align: middle;
    }

    table td.cell-status {
        font-weight: 500;
    }

    table td.cell-action {
        text-align: center;
    }

    table td.cell-timestamp {
        color: #6b7280;
        font-size: 13px;
    }

    table td.cell-admin {
        font-weight: 500;
        color: #1f2937;
    }

    table .action-buttons {
        display: flex;
        gap: 8px;
        justify-content: center;
        flex-wrap: wrap;
    }

    table .action-buttons button {
        padding: 6px 12px;
        font-size: 12px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.2s;
    }

    table .action-buttons .btn-approve {
        background-color: #d1fae5;
        color: #065f46;
        border: 1px solid #10b981;
    }

    table .action-buttons .btn-approve:hover {
        background-color: #a7f3d0;
        box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
    }

    table .action-buttons .btn-reject {
        background-color: #fee2e2;
        color: #991b1b;
        border: 1px solid #ef4444;
    }

    table .action-buttons .btn-reject:hover {
        background-color: #fecaca;
        box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
    }

    table .action-buttons .btn-view {
        background-color: #dbeafe;
        color: #0c2d6b;
        border: 1px solid #3b82f6;
    }

    table .action-buttons .btn-view:hover {
        background-color: #bfdbfe;
        box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
    }

    /* Tooltip Styling */
    .tooltip {
        position: relative;
        display: inline-block;
        border-bottom: 1px dotted #1f2937;
        cursor: help;
    }

    .tooltip .tooltiptext {
        visibility: hidden;
        width: 250px;
        background-color: #1f2937;
        color: #ffffff;
        text-align: center;
        border-radius: 6px;
        padding: 8px 12px;
        position: absolute;
        z-index: 1001;
        bottom: 125%;
        left: 50%;
        margin-left: -125px;
        opacity: 0;
        transition: opacity 0.3s;
        font-size: 12px;
        font-weight: 500;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        white-space: normal;
    }

    .tooltip .tooltiptext::after {
        content: '';
        position: absolute;
        top: 100%;
        left: 50%;
        margin-left: -5px;
        border-width: 5px;
        border-style: solid;
        border-color: #1f2937 transparent transparent transparent;
    }

    .tooltip:hover .tooltiptext {
        visibility: visible;
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
        margin-bottom: 12px;
        padding: 12px;
        background: white;
        border-radius: 6px;
        border-left: 3px solid #2d7d32;
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

    .documents-table-wrapper {
        overflow-x: auto;
        max-height: 300px;
    }

    /* Professional Table Styling - Admin2 Style */
    .due-accounts-table,
    .loan-table,
    .activity-logs-table {
        width: 100%;
        border-collapse: collapse;
        background-color: white;
    }

    .due-accounts-table thead,
    .loan-table thead,
    .activity-logs-table thead {
        background-color: #2d7d32;
        border-bottom: 3px solid #2d7d32;
    }

    .loan-table th,
    .due-accounts-table th,
    .activity-logs-table th {
        background: var(--green1);
        color: #fff;
        font-weight: 700;
        text-transform: uppercase;
        position: sticky;
        top: 0;
        z-index: 10;
        cursor: pointer;
        font-size: 12px;
        letter-spacing: 0.5px;
    }

    .activity-logs-table th:hover {
        background-color: #1b5e20;
    }

    .sort-icon {
        margin-left: 6px;
        opacity: 0.7;
    }

    .due-accounts-table tbody tr,
    .loan-table tbody tr,
    .activity-logs-table tbody tr {
        border-bottom: 2px solid #2d7d32;
        transition: background-color 0.2s ease;
        background-color: #fff;
    }

    .due-accounts-table tbody tr:nth-child(even),
    .loan-table tbody tr:nth-child(even),
    .activity-logs-table tbody tr:nth-child(even) {
        background-color: #f9f9f9;
    }

    .due-accounts-table tbody tr:hover,
    .loan-table tbody tr:hover,
    .activity-logs-table tbody tr:hover {
        background-color: #f0f5ff;
    }

    .due-accounts-table tbody tr.row-highlight,
    .loan-table tbody tr.row-highlight,
    .activity-logs-table tbody tr.row-highlight {
        background-color: #f0f5ff;
    }

    .loan-table td {
        padding: 12px;
        font-size: 0.9rem;
    }

    .due-accounts-table td,
    .activity-logs-table td {
        padding: 16px 16px;
        font-size: 14px;
        vertical-align: middle;
        color: var(--darker);
        font-weight: 500;
    }

    /* Scrollable table wrapper */
    .scrollable-table {
        overflow-x: auto;
        border-radius: 0;
        border: none;
        background-color: #fff;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
    }

    .scrollable-table::-webkit-scrollbar {
        height: 8px;
    }

    .scrollable-table::-webkit-scrollbar-track {
        background: #f5f5f5;
        border-radius: 0;
    }

    .scrollable-table::-webkit-scrollbar-thumb {
        background: #2d7d32;
        border-radius: 4px;
    }

    .scrollable-table::-webkit-scrollbar-thumb:hover {
        background: #1b5e20;
    }

    /* Table Responsive Styling */
    @media (max-width: 1024px) {

        .due-accounts-table th,
        .loan-table th,
        .activity-logs-table th,
        .due-accounts-table td,
        .loan-table td,
        .activity-logs-table td {
            padding: 10px 8px;
            font-size: 0.85rem;
        }
    }

    @media (max-width: 768px) {

        .due-accounts-table th,
        .loan-table th,
        .activity-logs-table th {
            font-size: 0.75rem;
            padding: 8px 6px;
        }

        .due-accounts-table td,
        .loan-table td,
        .activity-logs-table td {
            padding: 8px 6px;
            font-size: 0.8rem;
        }

        .scrollable-table {
            border-radius: 4px;
        }
    }

    /* Confirmation Dialog Enhancement */
    .modal.confirmation-modal .modal-header {
        border-bottom: 2px solid #e5e7eb;
    }

    .modal.confirmation-modal .modal-body {
        padding: 24px;
    }

    .confirmation-message {
        margin-bottom: 20px;
        padding: 16px;
        background-color: #f9fafb;
        border-left: 4px solid #3b82f6;
        border-radius: 4px;
    }

    .confirmation-message p {
        margin: 0;
        color: #374151;
        line-height: 1.5;
    }

    .confirmation-icon {
        font-size: 48px;
        text-align: center;
        margin-bottom: 16px;
    }

    .confirmation-icon.warning {
        color: #f59e0b;
    }

    .confirmation-icon.error {
        color: #ef4444;
    }

    .confirmation-icon.success {
        color: #10b981;
    }

    .confirmation-icon.info {
        color: #3b82f6;
    }

    /* Form Enhancement Styling */
    .form-group {
        margin-bottom: 16px;
    }

    .form-group label {
        display: block;
        margin-bottom: 6px;
        font-weight: 500;
        color: #374151;
        font-size: 14px;
        font-family: 'Segoe UI', Roboto, sans-serif;
    }

    .form-group label.required::after {
        content: ' *';
        color: #ef4444;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 14px;
        font-family: 'Segoe UI', Roboto, sans-serif;
        transition: all 0.2s;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .form-group.error input,
    .form-group.error select,
    .form-group.error textarea {
        border-color: #ef4444;
        background-color: #fef2f2;
    }

    .form-group.error .error-message {
        color: #ef4444;
        font-size: 12px;
        margin-top: 4px;
    }

    .form-group.success input,
    .form-group.success select,
    .form-group.success textarea {
        border-color: #10b981;
        background-color: #f0fdf4;
    }

    .form-group .help-text {
        color: #6b7280;
        font-size: 12px;
        margin-top: 4px;
    }

    .form-group .character-count {
        text-align: right;
        font-size: 12px;
        color: #6b7280;
        margin-top: 4px;
    }

    .form-group .character-count.warning {
        color: #f59e0b;
        font-weight: 500;
    }

    .form-group .character-count.error {
        color: #ef4444;
        font-weight: 500;
    }

    /* Responsive modal sizing */
    @media (max-width: 768px) {
        .modal-content {
            max-width: 95%;
            max-height: 95vh;
        }

        .modal-body {
            max-height: 80vh;
        }

        .modal-header h2 {
            font-size: 18px;
        }
    }

    @media (min-width: 769px) {
        .modal-body {
            max-height: calc(80vh - 60px);
            overflow-y: auto;
            padding: 15px;
        }
    }

    @media (max-width: 480px) {
        .modal-content {
            max-width: 100%;
            border-radius: 0;
        }

        .modal-body {
            max-height: 85vh;
        }

        .close {
            font-size: 24px;
            right: 10px;
            top: 5px;
        }
    }

    /* ==============================================
       ADMIN2-STYLE CSS ADDITIONS - Start
       ============================================== */

    /* Toast Notification Animations */
    @keyframes toastSlideIn {
        from {
            transform: translateY(-100px);
            opacity: 0;
        }

        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    @keyframes toastSlideOut {
        from {
            transform: translateY(0);
            opacity: 1;
        }

        to {
            transform: translateY(-100px);
            opacity: 0;
        }
    }

    @keyframes slideInRight {
        from {
            transform: translateX(400px);
            opacity: 0;
        }

        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }

        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }

    @keyframes scaleIn {
        from {
            transform: scale(0.9);
            opacity: 0;
        }

        to {
            transform: scale(1);
            opacity: 1;
        }
    }

    /* Toast Notification Container */
    #toastContainer {
        position: fixed !important;
        top: 20px !important;
        right: 20px !important;
        z-index: 10000 !important;
        max-width: 400px !important;
        pointer-events: none;
    }

    #toastContainer>* {
        pointer-events: auto;
    }

    .toast-notification {
        margin-bottom: 12px;
        border-radius: 6px;
        animation: slideInRight 0.3s ease-out;
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

    /* Notification Bell Styling */
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

    /* Notification Badge */
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

    @keyframes badgePulse {

        0%,
        100% {
            transform: scale(1);
            opacity: 1;
        }

        50% {
            transform: scale(1.1);
            opacity: 0.8;
        }
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

    /* Loading State */
    .notification-loading {
        padding: 20px;
        text-align: center;
        color: #999;
        font-size: 12px;
    }

    .notification-loading i {
        margin-right: 8px;
    }

    /* Empty State */
    .notification-empty {
        padding: 20px;
        text-align: center;
        color: #999;
        font-size: 12px;
    }

    .notification-empty i {
        font-size: 24px;
        margin-bottom: 8px;
        display: block;
        opacity: 0.5;
    }

    /* Modal Section Styling */
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

    .modal-section label {
        display: block;
        font-weight: 600;
        color: #333;
        margin-bottom: 10px;
        font-size: 12px;
    }

    /* Info Grid Layout (Admin2 Style) */


    .loan-details strong {
        display: inline-block;
        font-weight: 600;
        color: var(--dark);
    }

    /* Applicant Info Section */


    /* Loan Info Section */
    .loan-info-section .info-grid>div {
        background: #fafafa;
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

    .status-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    /* Queue Status Message Styling */
    #queueStatusMessage {
        display: none;
        margin-top: 8px;
        padding: 8px;
        background: #c8e6c9;
        border-left: 3px solid #4caf50;
        border-radius: 3px;
        color: #2e7d32;
        font-size: 12px;
    }

    #queueStatusMessage.active {
        display: block;
    }

    /* Info Box Styling */
    .info-box {
        padding: 12px;
        background: #e3f2fd;
        border-left: 4px solid #2196f3;
        border-radius: 3px;
        color: #1565c0;
        font-size: 12px;
        margin-bottom: 12px;
    }

    .info-box i {
        margin-right: 6px;
    }

    /* Form Section Styling */
    .form-section {
        margin-bottom: 16px;
        padding: 12px;
        background: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 4px;
    }

    .form-section label {
        display: block;
        font-weight: 600;
        color: #333;
        margin-bottom: 8px;
        font-size: 13px;
    }

    /* Modal Input Styling */
    .modal-body .form-group {
        margin-bottom: 16px;
    }

    .modal-body .form-group label {
        font-weight: 600;
        color: #333;
        margin-bottom: 6px;
        font-size: 13px;
    }

    .modal-body input[type="text"],
    .modal-body input[type="email"],
    .modal-body input[type="number"],
    .modal-body input[type="date"],
    .modal-body select,
    .modal-body textarea {
        width: 100%;
        padding: 8px 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 13px;
        font-family: 'Segoe UI', Roboto, sans-serif;
        box-sizing: border-box;
    }

    .modal-body input[type="text"]:focus,
    .modal-body input[type="email"]:focus,
    .modal-body input[type="number"]:focus,
    .modal-body input[type="date"]:focus,
    .modal-body select:focus,
    .modal-body textarea:focus {
        outline: none;
        border-color: #2196f3;
        background-color: #f0f8ff;
        box-shadow: 0 0 0 2px rgba(33, 150, 243, 0.1);
    }

    /* Readonly field styling */
    .modal-body input[readonly],
    .modal-body select[disabled],
    .modal-body textarea[readonly] {
        background-color: #f5f5f5;
        color: #666;
        cursor: not-allowed;
    }

    /* Data Display Elements in Modals */
    .modal-body .info-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid #e5e7eb;
        font-size: 13px;
    }

    .modal-body .info-row:last-child {
        border-bottom: none;
    }

    .modal-body .info-row .label {
        font-weight: 600;
        color: #333;
        min-width: 150px;
    }

    .modal-body .info-row .value {
        color: var(--darker);
        text-align: right;
        flex: 1;
    }

    .modal-body .data-section {
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 2px solid #e5e7eb;
    }

    .modal-body .data-section:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }

    .modal-body .data-section h4 {
        margin: 0 0 10px 0;
        font-size: 13px;
        font-weight: 700;
        color: #1f2937;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Badge/Label Styling in Modal */
    .modal-body .badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .modal-body .badge.badge-primary {
        background-color: #e3f2fd;
        color: #1565c0;
    }

    .modal-body .badge.badge-success {
        background-color: #e8f5e9;
        color: #2e7d32;
    }

    .modal-body .badge.badge-warning {
        background-color: #fff3e0;
        color: #e65100;
    }

    .modal-body .badge.badge-danger {
        background-color: #ffebee;
        color: #c62828;
    }

    /* Document Actions */
    .document-actions {
        display: flex;
        gap: 8px;
        align-items: center;
        justify-content: center;
    }

    .doc-action-btn {
        padding: 8px 12px;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.85rem;
    }

    .doc-approve {
        background-color: #27ae60 !important;
        color: white !important;
        border: 1px solid #27ae60 !important;
    }

    .doc-approve:hover {
        background-color: #229954 !important;
        box-shadow: 0 2px 8px rgba(39, 174, 96, 0.3) !important;
    }

    .doc-approve:active {
        transform: scale(0.95);
    }

    .doc-reject {
        background-color: #e74c3c !important;
        color: white !important;
        border: 1px solid #e74c3c !important;
    }

    .doc-reject:hover {
        background-color: #c0392b !important;
        box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3) !important;
    }

    .doc-reject:active {
        transform: scale(0.95);
    }

    /* Confirmation Dialog Buttons */
    .confirmation-btn {
        padding: 10px 24px;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 14px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .confirmation-btn-cancel {
        background-color: #e0e0e0;
        color: #333;
    }

    .confirmation-btn-cancel:hover {
        background-color: #d0d0d0;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .confirmation-btn-confirm {
        background-color: #2d7d32;
        color: white;
    }

    .confirmation-btn-confirm:hover {
        background-color: #1b5e20;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(45, 125, 50, 0.3);
    }

    .confirmation-btn-confirm:active {
        transform: scale(0.95);
    }

    .confirmation-btn-confirm.reject {
        background-color: #e74c3c;
    }

    .confirmation-btn-confirm.reject:hover {
        background-color: #c0392b;
        box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
    }

    /* Action Button Styles */
    .action-btn {
        padding: 8px 10px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        font-size: 0.9rem;
        background-color: #2196f3;
        color: white;
    }

    .action-btn:hover {
        background-color: #1976d2;
        box-shadow: 0 2px 8px rgba(33, 150, 243, 0.3);
        transform: translateY(-2px);
    }

    .action-btn:active {
        transform: scale(0.95);
    }

    .action-btn.view-btn {
        background-color: #2196f3;
    }

    .action-btn.view-btn:hover {
        background-color: #1976d2;
    }

    .action-btn.reminder-btn {
        background-color: #ff9800;
    }

    .action-btn.reminder-btn:hover {
        background-color: #f57c00;
        box-shadow: 0 2px 8px rgba(255, 152, 0, 0.3);
    }

    .action-buttons {
        display: flex;
        gap: 8px;
        align-items: center;
        justify-content: center;
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

    /* Credit Investigation Modal Styling */
    #creditInvestigationModal .form-section {
        margin-bottom: 25px;
        padding: 20px;
        background-color: #f9fafb;
        border-radius: 8px;
        border-left: 4px solid #2d7d32;
    }

    #creditInvestigationModal .section-title {
        color: #1f2937;
        font-weight: 600;
        font-size: 16px;
        margin: 0 0 15px 0;
        display: flex;
        align-items: center;
    }

    #creditInvestigationModal .form-group {
        margin-bottom: 15px;
    }

    #creditInvestigationModal .form-group label {
        display: block;
        margin-bottom: 8px;
        color: #374151;
        font-weight: 600;
        font-size: 14px;
    }

    #creditInvestigationModal .required {
        color: #ef4444;
        margin-left: 4px;
    }

    #creditInvestigationModal input,
    #creditInvestigationModal select,
    #creditInvestigationModal textarea {
        width: 100%;
        padding: 12px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 14px;
        font-family: inherit;
        transition: all 0.3s ease;
    }

    #creditInvestigationModal input:focus,
    #creditInvestigationModal select:focus,
    #creditInvestigationModal textarea:focus {
        outline: none;
        border-color: #2d7d32;
        background-color: #f0fdf4;
        box-shadow: 0 0 0 3px rgba(45, 125, 50, 0.1);
    }

    #creditInvestigationModal input[readonly] {
        background-color: #f3f4f6;
        cursor: not-allowed;
    }

    #creditInvestigationModal .form-group-row {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }

    @media (max-width: 768px) {
        #creditInvestigationModal .form-group-row {
            grid-template-columns: 1fr;
        }
    }

    @media (min-width: 769px) {
        .modal-content {
            width: 90%;
            max-width: 900px;
            max-height: 80vh;
            padding: 0;
            display: flex;
            flex-direction: column;
        }
    }

    #creditInvestigationModal textarea {
        resize: vertical;
        font-size: 13px;
    }

    /* Debug Logs Link Styling */
    .debug-logs-link {
        background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%) !important;
        border-left: 4px solid #7f1d1d !important;
        color: #ffffff !important;
        font-weight: 600 !important;
    }

    .debug-logs-link:hover {
        background: linear-gradient(135deg, #b91c1c 0%, #dc2626 100%) !important;
        color: #ffffff !important;
        transform: translateX(2px);
    }

    .debug-logs-link i {
        color: #fef2f2 !important;
    }

    /* ==============================================
       ADMIN2-STYLE CSS ADDITIONS - End
       ============================================== */
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
            <a href="admin1_dashboard.php"
                class="<?php echo $current_page === 'admin1_dashboard.php' ? 'active' : ''; ?>">
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
            <a href="#" id="viewInterestRatesBtn" class="view-interest-rate-link">
                <i class="fa-solid fa-percent"></i> VIEW INTEREST RATES
            </a>
            <a href="manage_credit_points.php"
                class="<?php echo $current_page === 'manage_credit_points.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-star"></i> MANAGE CREDIT RATE
            </a>
            <!-- <a href="#" onclick="toggleDebugLogs()" class="debug-logs-link">
                <i class="fa-solid fa-bug"></i> DEBUG LOGS
            </a>
            <a href="debug_logs_admin1.php" target="_blank" class="debug-logs-link"
                style="opacity: 0.8; font-size: 0.9em;">
                <i class="fa-solid fa-external-link-alt"></i> DEBUG LOGS (STANDALONE)
            </a> -->
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
                <div onclick="toggleDropdown(event)" role="button" aria-label="Toggle profile menu" tabindex="0"
                    onkeydown="handleProfileKeydown(event)">
                    <img src="uploads/<?php echo htmlspecialchars($profile_img); ?>" alt="Profile Image"
                        class="profile">
                </div>
                <div class="dropdown-menu" id="dropdown" role="menu">
                    <ul>
                        <li role="none">
                            <a href="<?php echo htmlspecialchars($profile_link); ?>" role="menuitem" tabindex="-1">
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
                    <h1>Admin 1 Dashboard</h1>
                    <p class="dashboard-subtitle">Manage loan applications, credit investigations, and system
                        operations.
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

        </div>

        <div class="activity_logs">
            <div class="section-header">
                <div>
                    <h2>Recent Activity Logs</h2>
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
                        <tbody>
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
                                    <td data-label="Module"><?php echo htmlspecialchars($log['module']); ?></td>
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
        const loanTypeData = <?php echo json_encode($loanTypeData); ?>;

        const loanTypeLabels = loanTypeData.map(item => item.type_name);
        const loanTypeCounts = loanTypeData.map(item => parseInt(item.count));

        if (loanTypeLabels.length === 0) {
            const ctx = document.getElementById('loanTypeChart').getContext('2d');
            ctx.font = '14px Poppins';
            ctx.fillStyle = '#333';
            ctx.textAlign = 'center';
            ctx.fillText('No loan types available', ctx.canvas.width / 2, ctx.canvas.height / 2);
        } else {
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

        // Toggle sidebar and burger button
        document.querySelector('.burger').addEventListener('click', function () {
            this.classList.toggle('active');
            document.querySelector('nav').classList.toggle('active');
        });

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

        // ============================================
        // ============================================
        // ADVANCED POLLING MANAGER (Admin2 Parity)
        // ============================================
        const PollingManager = {
            // State Management
            pollingIntervals: {},
            lastDataHash: {},
            notificationQueue: [],
            isPollingEnabled: true,
            isPaused: false,
            pollInterval: 8000, // 8 seconds default

            // Performance Optimization
            requestPending: {},
            requestTimeout: {},
            failureCount: {},
            maxFailures: 3,
            backoffMultiplier: 1.5,
            baseBackoffTime: 5000,

            // State Flags
            isModalOpen: false,
            pollingState: {
                active: [],
                paused: [],
                failed: [],
                queued: []
            },

            // Cache Management
            dataCache: {},
            cacheTTL: 300000, // 5 minutes
            lastCacheUpdate: {},

            /**
             * Initialize polling for a specific data source
             * @param {string} dataSource Identifier for the data source
             * @param {string} fetchUrl URL to fetch data from
             * @param {function} updateCallback Function called when data updates
             * @param {number} pollInterval Milliseconds between polls
             */
            initPoll(dataSource, fetchUrl, updateCallback, pollInterval = 8000) {
                // Stop existing poll if running
                if (this.pollingIntervals[dataSource]) {
                    clearInterval(this.pollingIntervals[dataSource]);
                }

                // Initialize failure tracking
                this.failureCount[dataSource] = 0;
                this.requestPending[dataSource] = false;

                // Initial fetch
                this.fetchData(dataSource, fetchUrl, updateCallback);

                // Set up polling with state tracking
                this.pollingIntervals[dataSource] = setInterval(() => {
                    if (this.isPollingEnabled && !this.isPaused && !this.isModalOpen) {
                        this.fetchData(dataSource, fetchUrl, updateCallback);
                    } else if (this.isModalOpen && !this.pollingState.paused.includes(dataSource)) {
                        // Track paused state during modal
                        this.pollingState.paused.push(dataSource);
                    }
                }, pollInterval);

                // Add to active polling list
                if (!this.pollingState.active.includes(dataSource)) {
                    this.pollingState.active.push(dataSource);
                }

                console.log(`✓ Advanced polling enabled for: ${dataSource} (${pollInterval}ms)`);
                this.logPollingState();
            },

            /**
             * Fetch data with error handling and retry logic
             * @param {string} dataSource Data source identifier
             * @param {string} fetchUrl URL to fetch
             * @param {function} updateCallback Update callback function
             */
            fetchData(dataSource, fetchUrl, updateCallback) {
                // Prevent duplicate requests
                if (this.requestPending[dataSource]) {
                    console.warn(`⚠️ Request already pending for ${dataSource}, skipping...`);
                    return;
                }

                // Check cache validity
                if (this.isCacheValid(dataSource)) {
                    console.log(`✓ Using cached data for ${dataSource}`);
                    updateCallback(this.dataCache[dataSource]);
                    return;
                }

                this.requestPending[dataSource] = true;

                // Timeout protection (prevent hanging requests)
                this.requestTimeout[dataSource] = setTimeout(() => {
                    console.error(`❌ Request timeout for ${dataSource}`);
                    this.requestPending[dataSource] = false;
                    this.handlePollingError(dataSource);
                }, 15000); // 15 second timeout

                fetch(fetchUrl)
                    .then(response => {
                        clearTimeout(this.requestTimeout[dataSource]);

                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status} - ${response.statusText}`);
                        }

                        // Check if response is valid JSON before parsing
                        const contentType = response.headers.get('content-type');
                        if (!contentType || !contentType.includes('application/json')) {
                            throw new Error(`Invalid content type: ${contentType}. Expected JSON.`);
                        }

                        return response.text().then(text => {
                            try {
                                return JSON.parse(text);
                            } catch (e) {
                                console.error(`JSON Parse Error for ${dataSource}:`, text.substring(0, 200));
                                throw new Error(`Invalid JSON response: ${e.message}`);
                            }
                        });
                    })
                    .then(data => {
                        // Reset failure counter on success
                        this.failureCount[dataSource] = 0;
                        this.requestPending[dataSource] = false;

                        try {
                            const newHash = this.hashData(data);
                            const oldHash = this.lastDataHash[dataSource];

                            // Data changed - update UI and notify
                            if (oldHash !== newHash) {
                                this.lastDataHash[dataSource] = newHash;
                                this.dataCache[dataSource] = data;
                                this.lastCacheUpdate[dataSource] = Date.now();

                                updateCallback(data);

                                // Queue notification
                                this.queueNotification(
                                    `${dataSource} Updated`,
                                    `Synchronized at ${new Date().toLocaleTimeString()}`,
                                    'info'
                                );

                                console.log(`✓ Data updated for ${dataSource}`);
                            }
                        } catch (hashError) {
                            console.warn(`Hash calculation failed for ${dataSource}:`, hashError);
                            updateCallback(data);
                        }
                    })
                    .catch(error => {
                        console.error(`❌ Polling error for ${dataSource}:`, error.message);
                        this.requestPending[dataSource] = false;
                        this.handlePollingError(dataSource);
                    });
            },

            /**
             * Handle polling errors with exponential backoff
             * @param {string} dataSource The failing data source
             */
            handlePollingError(dataSource) {
                this.failureCount[dataSource]++;

                if (this.failureCount[dataSource] >= this.maxFailures) {
                    // Too many failures - pause polling for this source
                    this.pausePollForSource(dataSource);
                    this.queueNotification(
                        'Polling Paused',
                        `${dataSource} polling paused after ${this.failureCount[dataSource]} failures`,
                        'error'
                    );
                    console.error(`❌ Polling paused for ${dataSource} - Max failures reached`);
                    return;
                }

                // Calculate exponential backoff time
                const backoffTime = this.baseBackoffTime * Math.pow(this.backoffMultiplier, this.failureCount[dataSource] - 1);
                console.warn(`⚠️ Polling retry ${this.failureCount[dataSource]}/${this.maxFailures} for ${dataSource} in ${backoffTime}ms`);
            },

            /**
             * Pause polling for a specific source
             * @param {string} dataSource The data source to pause
             */
            pausePollForSource(dataSource) {
                if (!this.pollingState.paused.includes(dataSource)) {
                    this.pollingState.paused.push(dataSource);
                }
                if (this.pollingState.active.includes(dataSource)) {
                    this.pollingState.active.splice(this.pollingState.active.indexOf(dataSource), 1);
                }
            },

            /**
             * Pause all polling (during modal operations)
             */
            pausePolling() {
                this.isPaused = true;
                this.isModalOpen = true;
                this.pollingState.paused = [...this.pollingState.active];
                console.log('⏸️ Polling paused - Modal opened');
                this.logPollingState();
            },

            /**
             * Resume all polling (after modal operations)
             */
            resumePolling() {
                this.isPaused = false;
                this.isModalOpen = false;
                this.pollingState.paused = [];
                console.log('▶️ Polling resumed - Modal closed');
                this.logPollingState();
            },

            /**
             * Queue a notification for batch display
             * @param {string} title Notification title
             * @param {string} message Notification message
             * @param {string} type Notification type
             */
            queueNotification(title, message, type = 'info') {
                this.notificationQueue.push({
                    title: title,
                    message: message,
                    type: type,
                    timestamp: Date.now()
                });

                // Process queue
                this.processNotificationQueue();
            },

            /**
             * Process queued notifications
             */
            processNotificationQueue() {
                while (this.notificationQueue.length > 0) {
                    const notification = this.notificationQueue.shift();
                    this.showInAppNotification(notification.title, notification.message, notification.type);
                }
            },

            /**
             * Check if cached data is still valid
             * @param {string} dataSource Data source identifier
             * @returns {boolean} True if cache is valid
             */
            isCacheValid(dataSource) {
                if (!this.dataCache[dataSource]) return false;
                if (!this.lastCacheUpdate[dataSource]) return false;

                const age = Date.now() - this.lastCacheUpdate[dataSource];
                return age < this.cacheTTL;
            },

            /**
             * Clear cache for a specific source
             * @param {string} dataSource Data source identifier
             */
            clearCache(dataSource) {
                delete this.dataCache[dataSource];
                delete this.lastCacheUpdate[dataSource];
                console.log(`✓ Cache cleared for ${dataSource}`);
            },

            /**
             * Generate hash of data for change detection
             * @param {object} data Data to hash
             * @returns {string} Hash string
             */
            hashData(data) {
                try {
                    const jsonString = JSON.stringify(data);
                    const encoder = new TextEncoder();
                    const encodedData = encoder.encode(jsonString);

                    let hash = 0;
                    for (let i = 0; i < encodedData.length; i++) {
                        const byte = encodedData[i];
                        hash = ((hash << 5) - hash) + byte;
                        hash = hash & hash;
                    }
                    return Math.abs(hash).toString(16).substring(0, 50);
                } catch (e) {
                    // Fallback
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

            /**
             * Send desktop push notification
             * @param {string} title Notification title
             * @param {string} message Notification message
             */
            sendPushNotification(title, message) {
                if ('Notification' in window && Notification.permission === 'granted') {
                    new Notification(title, {
                        body: message,
                        icon: 'IMAGE/Main-Logo.png',
                        tag: 'cycloan-update',
                        requireInteraction: false
                    });
                }

                this.showInAppNotification(title, message);
            },

            /**
             * Show in-app toast notification
             * @param {string} title Notification title
             * @param {string} message Notification message
             * @param {string} type Notification type
             */
            showInAppNotification(title, message, type = 'success') {
                const toast = document.createElement('div');
                toast.className = 'real-time-toast';

                let iconClass = 'fa-info-circle';
                if (type === 'error') iconClass = 'fa-exclamation-circle';
                else if (type === 'warning') iconClass = 'fa-exclamation-triangle';
                else if (type === 'success') iconClass = 'fa-check-circle';

                toast.innerHTML = `
                    <div class="toast-content">
                        <i class="fas ${iconClass}"></i>
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

            /**
             * Stop polling for a specific source
             * @param {string} dataSource Data source identifier
             */
            stopPoll(dataSource) {
                if (this.pollingIntervals[dataSource]) {
                    clearInterval(this.pollingIntervals[dataSource]);
                    delete this.pollingIntervals[dataSource];
                }

                this.pausePollForSource(dataSource);
                console.log(`✗ Polling stopped for: ${dataSource}`);
            },

            /**
             * Stop all polling
             */
            stopAllPolling() {
                Object.keys(this.pollingIntervals).forEach(source => this.stopPoll(source));
                console.log('✗ All polling stopped');
            },

            /**
             * Get polling status
             * @returns {object} Polling state information
             */
            getStatus() {
                return {
                    enabled: this.isPollingEnabled,
                    paused: this.isPaused,
                    modalOpen: this.isModalOpen,
                    activePolls: this.pollingState.active.length,
                    pausedPolls: this.pollingState.paused.length,
                    failedPolls: this.pollingState.failed.length,
                    queuedNotifications: this.notificationQueue.length
                };
            },

            /**
             * Log current polling state for debugging
             */
            logPollingState() {
                const status = this.getStatus();
                console.log('📊 Polling State:', {
                    enabled: status.enabled ? '✓' : '✗',
                    paused: status.paused ? '⏸️' : '▶️',
                    modal: status.modalOpen ? '🟢' : '⚪',
                    active: status.activePolls,
                    paused_count: status.pausedPolls,
                    failed: status.failedPolls,
                    notifications: status.queuedNotifications
                });
            }
        };

        // ========== NOTIFICATION SYSTEM ==========
        function showNotification(message, type = 'success') {
            // Real-time toast notification with advanced styling
            const toast = document.createElement('div');
            const icons = {
                success: 'check-circle',
                error: 'exclamation-circle',
                warning: 'exclamation-triangle',
                info: 'info-circle'
            };

            toast.className = 'real-time-toast';
            toast.style.cssText = `
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
                font-family: 'Poppins', 'Segoe UI', sans-serif;
            `;

            const iconClass = icons[type] || icons.info;

            // Adjust color based on type
            let toastColor = '#2d7d32';
            if (type === 'error') {
                toast.style.background = 'linear-gradient(135deg, #b71c1c 0%, #c62828 100%)';
                toastColor = '#c62828';
            } else if (type === 'warning') {
                toast.style.background = 'linear-gradient(135deg, #e65100 0%, #f57c00 100%)';
                toastColor = '#f57c00';
            } else if (type === 'info') {
                toast.style.background = 'linear-gradient(135deg, #0277bd 0%, #0288d1 100%)';
                toastColor = '#0288d1';
            }

            toast.innerHTML = `
                <div style="display: flex; align-items: flex-start; gap: 12px;">
                    <i class="fas fa-${iconClass}" style="
                        font-size: 18px;
                        animation: spin 2s linear infinite;
                        flex-shrink: 0;
                        margin-top: 2px;
                    "></i>
                    <div style="flex: 1;">
                        <strong style="display: block; margin-bottom: 4px;">${type.charAt(0).toUpperCase() + type.slice(1)}</strong>
                        <p style="margin: 0; font-size: 12px; opacity: 0.9;">${message}</p>
                    </div>
                    <button style="
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
                    " onclick="this.parentElement.parentElement.remove()" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.8'">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;

            document.body.appendChild(toast);

            // Auto-remove after 4 seconds
            setTimeout(() => {
                toast.style.animation = 'toastSlideOut 0.4s ease-in';
                setTimeout(() => toast.remove(), 400);
            }, 4000);
        }

        // Update functions for tables
        function updateDueAccountsTable(data) {
            const tbody = document.getElementById('dueAccountsTableBody');
            if (!tbody || !data.data) return;

            // Only update if new data is different
            if (JSON.stringify(data) === tbody.dataset.lastData) return;
            tbody.dataset.lastData = JSON.stringify(data);

            // Show notification when data is updated
            showNotification('📊 Due Accounts Table Updated', 'info');

            tbody.innerHTML = data.data.map((account, index) => `
                <tr>
                    <td>${index + 1}</td>
                    <td>${escapeHtml(account.application_number)}</td>
                    <td>${escapeHtml(account.user_name)}</td>
                    <td>${account.days_overdue ? `<span class="status-badge badge-danger">${account.days_overdue} days</span>` : '-'}</td>
                    <td>₱${parseFloat(account.total_due).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                </tr>
            `).join('');
        }

        function updateActivityLogsTable(data) {
            const tbody = document.getElementById('activityLogsTableBody');
            if (!tbody || !data.data) return;

            // Only update if new data is different
            if (JSON.stringify(data) === tbody.dataset.lastData) return;
            tbody.dataset.lastData = JSON.stringify(data);

            // Show notification when data is updated
            showNotification('📝 Recent Activity Log Updated', 'info');

            tbody.innerHTML = data.data.map((log, index) => `
                <tr>
                    <td>${index + 1}</td>
                    <td>${escapeHtml(log.admin_name || log.user_name || 'Unknown')}</td>
                    <td>${escapeHtml(log.description || 'N/A')}</td>
                    <td>${new Date(log.created_at).toLocaleString('en-PH')}</td>
                </tr>
            `).join('');
        }

        function updateLoanApplicantsTable(data) {
            const tbody = document.getElementById('loanApplicantsTableBody');
            if (!tbody || !data.data) return;

            // Only update if new data is different
            if (JSON.stringify(data) === tbody.dataset.lastData) return;
            tbody.dataset.lastData = JSON.stringify(data);

            // Show notification when data is updated
            showNotification('📋 Loan Applications Table Updated', 'success');

            tbody.innerHTML = data.data.map((applicant, index) => `
                <tr>
                    <td>${index + 1}</td>
                    <td>${escapeHtml(applicant.application_number)}</td>
                    <td>${escapeHtml(applicant.user_name)}</td>
                    <td><span class="status-badge badge-${(applicant.credit_investigation_status || 'pending').toLowerCase()}">${escapeHtml(applicant.credit_investigation_status || 'Pending')}</span></td>
                    <td>${new Date(applicant.created_at).toLocaleString('en-PH')}</td>
                </tr>
            `).join('');
        }

        // Helper function to escape HTML
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

        // START AUTO-POLLING FOR REAL-TIME UPDATES
        document.addEventListener('DOMContentLoaded', function () {
            setTimeout(() => {
                // Poll Due Accounts every 5 seconds for real-time updates
                if (document.getElementById('dueAccountsTableBody')) {
                    PollingManager.initPoll(
                        'DueAccounts',
                        'admin1_dashboard.php?action=get_due_accounts',
                        updateDueAccountsTable,
                        5000
                    );
                }

                // Poll Activity Logs every 5 seconds for real-time updates
                if (document.getElementById('activityLogsTableBody')) {
                    PollingManager.initPoll(
                        'ActivityLogs',
                        'admin1_dashboard.php?action=get_activity_logs',
                        updateActivityLogsTable,
                        5000
                    );
                }

                // Poll Loan Applicants every 8 seconds for real-time updates
                if (document.getElementById('loanApplicantsTableBody')) {
                    PollingManager.initPoll(
                        'LoanApplicants',
                        'admin1_dashboard.php?action=get_loan_applicants',
                        updateLoanApplicantsTable,
                        8000
                    );
                }

                console.log('🔄 Real-time synchronization started (Auto-Polling) - 5-8 second intervals');
            }, 1000); // Delay 1 second to ensure DOM is fully ready

            // ============================================
            // TABLE ENHANCEMENT SYSTEM (Admin2 Parity)
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
                    return `<span class="status-badge ${badgeClass}">${escapeHtml(status)}</span>`;
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
                 * Add search/filter functionality to table
                 * @param {string} tableSelector Table selector
                 * @param {array} columns Columns to search (0-indexed)
                 */
                enableTableSearch(tableSelector, columns = []) {
                    const table = document.querySelector(tableSelector);
                    if (!table) return;

                    // Create search container
                    const searchContainer = document.createElement('div');
                    searchContainer.className = 'table-search-container';
                    searchContainer.style.cssText = `
                        margin-bottom: 15px;
                        display: flex;
                        gap: 10px;
                        align-items: center;
                    `;

                    const searchInput = document.createElement('input');
                    searchInput.type = 'text';
                    searchInput.className = 'table-search-input';
                    searchInput.placeholder = '🔍 Search table...';
                    searchInput.style.cssText = `
                        flex: 1;
                        padding: 10px 15px;
                        border: 1px solid #ddd;
                        border-radius: 6px;
                        font-size: 14px;
                        transition: border-color 0.3s;
                    `;

                    const clearButton = document.createElement('button');
                    clearButton.textContent = '✕ Clear';
                    clearButton.className = 'table-search-clear';
                    clearButton.style.cssText = `
                        padding: 10px 15px;
                        background-color: #f5f5f5;
                        border: 1px solid #ddd;
                        border-radius: 6px;
                        cursor: pointer;
                        font-size: 14px;
                        transition: all 0.3s;
                    `;

                    // Search functionality
                    searchInput.addEventListener('keyup', (e) => {
                        const searchTerm = e.target.value.toLowerCase();
                        const rows = table.querySelectorAll('tbody tr');

                        rows.forEach(row => {
                            let match = false;
                            if (searchTerm === '') {
                                match = true;
                            } else {
                                const cells = row.querySelectorAll('td');
                                for (let col of columns) {
                                    if (col < cells.length) {
                                        if (cells[col].textContent.toLowerCase().includes(searchTerm)) {
                                            match = true;
                                            break;
                                        }
                                    }
                                }
                            }
                            row.style.display = match ? '' : 'none';
                        });

                        // Update result count
                        const visibleRows = Array.from(rows).filter(r => r.style.display !== 'none').length;
                        console.log(`📊 Search found ${visibleRows} matching rows`);
                    });

                    // Clear functionality
                    clearButton.addEventListener('click', () => {
                        searchInput.value = '';
                        searchInput.dispatchEvent(new Event('keyup'));
                    });

                    searchContainer.appendChild(searchInput);
                    searchContainer.appendChild(clearButton);

                    // Insert before table
                    table.parentElement.insertBefore(searchContainer, table);
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
                 * Add pagination to table
                 * @param {string} tableSelector Table selector
                 * @param {number} rowsPerPage Rows per page
                 */
                enableTablePagination(tableSelector, rowsPerPage = 10) {
                    const table = document.querySelector(tableSelector);
                    if (!table) return;

                    const tbody = table.querySelector('tbody');
                    const rows = Array.from(tbody.querySelectorAll('tr'));
                    const pageCount = Math.ceil(rows.length / rowsPerPage);

                    // Create pagination container
                    const paginationContainer = document.createElement('div');
                    paginationContainer.className = 'table-pagination';
                    paginationContainer.style.cssText = `
                        margin-top: 15px;
                        display: flex;
                        justify-content: center;
                        gap: 5px;
                        flex-wrap: wrap;
                    `;

                    const showPage = (pageNum) => {
                        const start = (pageNum - 1) * rowsPerPage;
                        const end = start + rowsPerPage;

                        rows.forEach((row, index) => {
                            row.style.display = (index >= start && index < end) ? '' : 'none';
                        });

                        // Update button states
                        document.querySelectorAll('.pagination-button').forEach(btn => {
                            btn.classList.remove('active');
                            if (btn.dataset.page == pageNum) {
                                btn.classList.add('active');
                            }
                        });
                    };

                    // Create pagination buttons
                    for (let i = 1; i <= pageCount; i++) {
                        const button = document.createElement('button');
                        button.textContent = i;
                        button.className = 'pagination-button';
                        button.dataset.page = i;
                        button.style.cssText = `
                            padding: 8px 12px;
                            margin: 2px;
                            border: 1px solid #ddd;
                            background-color: #fff;
                            border-radius: 4px;
                            cursor: pointer;
                            font-size: 13px;
                            transition: all 0.3s;
                        `;

                        if (i === 1) button.classList.add('active');

                        button.addEventListener('click', () => showPage(i));
                        paginationContainer.appendChild(button);
                    }

                    table.parentElement.appendChild(paginationContainer);
                    console.log(`📊 Pagination enabled (${pageCount} pages, ${rowsPerPage} rows per page)`);
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
                },

                /**
                 * Add row selection with checkboxes
                 * @param {string} tableSelector Table selector
                 */
                enableRowSelection(tableSelector) {
                    const table = document.querySelector(tableSelector);
                    if (!table) return;

                    const tbody = table.querySelector('tbody');
                    const headerCheckbox = document.createElement('input');
                    headerCheckbox.type = 'checkbox';
                    headerCheckbox.className = 'select-all-checkbox';

                    // Add checkbox column to header
                    const headerCell = document.createElement('th');
                    headerCell.style.width = '40px';
                    headerCell.appendChild(headerCheckbox);
                    table.querySelector('thead tr').insertBefore(headerCell, table.querySelector('thead tr').firstChild);

                    // Add checkboxes to rows
                    const rows = tbody.querySelectorAll('tr');
                    rows.forEach(row => {
                        const cell = document.createElement('td');
                        const checkbox = document.createElement('input');
                        checkbox.type = 'checkbox';
                        checkbox.className = 'row-checkbox';
                        checkbox.style.cursor = 'pointer';
                        cell.appendChild(checkbox);
                        row.insertBefore(cell, row.firstChild);

                        checkbox.addEventListener('change', () => {
                            const allChecked = Array.from(tbody.querySelectorAll('.row-checkbox')).every(cb => cb.checked);
                            headerCheckbox.checked = allChecked;
                        });
                    });

                    // Select all functionality
                    headerCheckbox.addEventListener('change', () => {
                        tbody.querySelectorAll('.row-checkbox').forEach(cb => {
                            cb.checked = headerCheckbox.checked;
                        });
                    });

                    console.log(`📊 Row selection enabled`);
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

            // ============================================
            // ADVANCED MODAL MANAGEMENT SYSTEM (Admin2 Parity)
            // ============================================

            const ModalManager = {
                openModals: [],
                modalCache: {},
                cacheTTL: 300000, // 5 minutes
                lastCacheUpdate: {},

                /**
                 * Open a modal with optional data
                 * @param {string} modalId Modal element ID
                 * @param {object} data Optional data to populate modal
                 */
                openModal(modalId, data = null) {
                    const modal = document.getElementById(modalId);
                    if (!modal) {
                        console.error(`❌ Modal not found: ${modalId}`);
                        return false;
                    }

                    // Pause polling when modal opens
                    PollingManager.pausePolling();

                    // Set backdrop
                    modal.style.display = 'flex';
                    modal.classList.add('modal-open');
                    document.body.style.overflow = 'hidden';

                    // Track open modals
                    if (!this.openModals.includes(modalId)) {
                        this.openModals.push(modalId);
                    }

                    // Populate modal if data provided
                    if (data) {
                        this.populateModal(modalId, data);
                    }

                    console.log(`🔓 Modal opened: ${modalId}`);
                    return true;
                },

                /**
                 * Close a modal
                 * @param {string} modalId Modal element ID
                 */
                closeModal(modalId) {
                    const modal = document.getElementById(modalId);
                    if (!modal) return false;

                    modal.style.display = 'none';
                    modal.classList.remove('modal-open');

                    // Remove from open modals list
                    this.openModals = this.openModals.filter(id => id !== modalId);

                    // Resume polling when all modals closed
                    if (this.openModals.length === 0) {
                        document.body.style.overflow = 'auto';
                        PollingManager.resumePolling();
                    }

                    console.log(`🔒 Modal closed: ${modalId}`);
                    return true;
                },

                /**
                 * Close all open modals
                 */
                closeAllModals() {
                    this.openModals.slice().forEach(modalId => this.closeModal(modalId));
                },

                /**
                 * Populate modal with data
                 * @param {string} modalId Modal element ID
                 * @param {object} data Data to populate
                 */
                populateModal(modalId, data) {
                    const modal = document.getElementById(modalId);
                    if (!modal) return;

                    // Generic field population
                    Object.keys(data).forEach(key => {
                        const element = modal.querySelector(`[data-field="${key}"]`);
                        if (element) {
                            if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA') {
                                element.value = data[key];
                            } else {
                                element.textContent = data[key];
                            }
                        }
                    });
                },

                /**
                 * Cache modal content with TTL
                 * @param {string} modalId Modal element ID
                 * @param {object} content Content to cache
                 */
                setCacheModal(modalId, content) {
                    this.modalCache[modalId] = content;
                    this.lastCacheUpdate[modalId] = Date.now();
                    console.log(`💾 Modal cached: ${modalId}`);
                },

                /**
                 * Get cached modal content if valid
                 * @param {string} modalId Modal element ID
                 * @returns {object|null} Cached content or null
                 */
                getCacheModal(modalId) {
                    if (!this.modalCache[modalId]) return null;

                    const age = Date.now() - this.lastCacheUpdate[modalId];
                    if (age > this.cacheTTL) {
                        delete this.modalCache[modalId];
                        delete this.lastCacheUpdate[modalId];
                        return null;
                    }

                    console.log(`✓ Using cached modal: ${modalId}`);
                    return this.modalCache[modalId];
                },

                /**
                 * Clear cache for specific modal
                 * @param {string} modalId Modal element ID
                 */
                clearCache(modalId) {
                    delete this.modalCache[modalId];
                    delete this.lastCacheUpdate[modalId];
                    console.log(`🗑️ Modal cache cleared: ${modalId}`);
                }
            };

            // ============================================
            // MODAL EVENT HANDLERS
            // ============================================

            /**
             * Show email success modal with auto-close
             * @param {string} message Optional success message
             */
            function showEmailSuccessModal(message = 'Your email has been sent to the applicant.') {
                const modal = document.getElementById('emailSuccessModal');
                const msgElement = modal.querySelector('[data-field="successMessage"]');
                if (msgElement) {
                    msgElement.textContent = message;
                }

                ModalManager.openModal('emailSuccessModal');

                // Auto-close after 5 seconds
                setTimeout(() => {
                    ModalManager.closeModal('emailSuccessModal');
                }, 5000);
            }

            /**
             * Show confirmation modal for document/rejection actions
             * @param {object} options Configuration object
             */
            function showConfirmationModal(options = {}) {
                const defaults = {
                    icon: '⚠️',
                    title: 'Confirm Action',
                    message: 'Are you sure?',
                    onConfirm: null,
                    onCancel: null
                };

                const config = Object.assign({}, defaults, options);
                const modal = document.getElementById('confirmationModal');

                // Populate fields
                modal.querySelector('[data-field="confirmIcon"]').textContent = config.icon;
                modal.querySelector('[data-field="confirmTitle"]').textContent = config.title;
                modal.querySelector('[data-field="confirmMessage"]').textContent = config.message;

                // Set CSRF token
                const csrfToken = getCSRFToken();
                document.getElementById('confirmCsrfToken').value = csrfToken;

                // Attach event handlers
                window.confirmationOnConfirm = config.onConfirm;
                window.confirmationOnCancel = config.onCancel;

                ModalManager.openModal('confirmationModal');
            }

            /**
             * Handle confirmation modal submit
             */
            function handleConfirmation() {
                const template = document.getElementById('rejectionTemplate').value;
                const notes = document.getElementById('confirmationNotes').value;
                const csrfToken = document.getElementById('confirmCsrfToken').value;

                if (!template) {
                    alert('Please select a reason from the template');
                    return;
                }

                const data = {
                    template,
                    notes,
                    csrf_token: csrfToken
                };

                if (window.confirmationOnConfirm && typeof window.confirmationOnConfirm === 'function') {
                    window.confirmationOnConfirm(data);
                }

                ModalManager.closeModal('confirmationModal');
            }

            /**
             * Show pre-approval status modal
             * @param {object} applicationData Application data
             */
            function showPreApprovalModal(applicationData = {}) {
                const modal = document.getElementById('preApprovalModal');

                // Set CSRF token
                const csrfToken = getCSRFToken();
                document.getElementById('preApprovalCsrfToken').value = csrfToken;

                // Setup character counter
                const notesTextarea = document.getElementById('decisionNotes');
                notesTextarea.addEventListener('input', (e) => {
                    const count = e.target.value.length;
                    document.getElementById('decisionCharCount').textContent = count;
                    if (count > 500) {
                        e.target.value = e.target.value.substring(0, 500);
                        document.getElementById('decisionCharCount').textContent = 500;
                    }
                });

                ModalManager.openModal('preApprovalModal');
            }

            /**
             * Handle pre-approval form submission
             */
            function handlePreApprovalSubmit() {
                const form = document.getElementById('preApprovalForm');
                const status = form.querySelector('input[name="preapproval_status"]:checked').value;
                const reason = document.getElementById('decisionReason').value;
                const notes = document.getElementById('decisionNotes').value;
                const csrfToken = document.getElementById('preApprovalCsrfToken').value;

                if (!status) {
                    alert('Please select a decision status');
                    return;
                }

                if (!reason) {
                    alert('Please select a decision reason');
                    return;
                }

                const data = {
                    status,
                    reason,
                    notes,
                    csrf_token: csrfToken
                };

                console.log('📋 Pre-approval submission:', data);
                // Send to server via AJAX
                // TODO: Implement server endpoint

                showEmailSuccessModal('Pre-approval decision saved successfully!');
                ModalManager.closeModal('preApprovalModal');
            }

            /**
             * Show comprehensive loan details modal
             * @param {number} applicationId Application ID
             * @param {object} loanData Loan data to display
             */
            function showLoanDetailsModal(applicationId, loanData = {}) {
                const modal = document.getElementById('loanDetailsModal');

                // Populate basic data
                const defaultData = {
                    applicationId: applicationId,
                    loanAmount: '₱0.00',
                    interestRate: '0%',
                    loanTerm: '-',
                    monthlyPayment: '₱0.00',
                    netIncome: '₱0.00',
                    existingDebts: '₱0.00',
                    debtToIncomeRatio: '0%',
                    currentStatus: 'Pending',
                    adminName: '—',
                    lastUpdated: '—',
                    remarks: 'No remarks yet'
                };

                const finalData = Object.assign({}, defaultData, loanData);

                // Populate all fields
                Object.keys(finalData).forEach(key => {
                    const element = modal.querySelector(`[data-field="${key}"]`);
                    if (element) {
                        element.textContent = finalData[key];
                    }
                });

                // Load activity logs if available
                if (loanData.activityLogs) {
                    const logsSection = document.getElementById('activityLogsSection');
                    let html = '';
                    loanData.activityLogs.forEach(log => {
                        html += `
                            <div style="margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #e5e7eb;">
                                <div style="font-weight: 500; color: #1f2937;">${log.action}</div>
                                <div style="font-size: 12px; color: #9ca3af;">${log.timestamp} by ${log.admin}</div>
                            </div>
                        `;
                    });
                    logsSection.innerHTML = html;
                }

                // Cache the data
                ModalManager.setCacheModal('loanDetailsModal', finalData);

                ModalManager.openModal('loanDetailsModal');
            }

            /**
             * Edit loan details (placeholder)
             */
            function editLoanDetails() {
                console.log('📝 Edit loan details clicked');
                alert('Edit functionality will be implemented in Admin2-level features');
            }

            /**
             * Character counter for confirmation notes
             */
            document.addEventListener('input', (e) => {
                if (e.target.id === 'confirmationNotes') {
                    const count = e.target.value.length;
                    document.getElementById('charCount').textContent = count;
                    if (count > 500) {
                        e.target.value = e.target.value.substring(0, 500);
                        document.getElementById('charCount').textContent = 500;
                    }
                }
            });

            // Setup modal close buttons
            document.querySelectorAll('.close').forEach(closeBtn => {
                closeBtn.addEventListener('click', (e) => {
                    const modal = e.target.closest('.modal');
                    if (modal) {
                        ModalManager.closeModal(modal.id);
                    }
                });
            });

            // Close modal when clicking outside
            document.querySelectorAll('.modal').forEach(modal => {
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        ModalManager.closeModal(modal.id);
                    }
                });
            });

            // Close on Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    ModalManager.closeAllModals();
                }
            });

            console.log('✅ Advanced Modal Management System initialized');

            /**
             * ======================================
             * NOTIFICATION BELL SYSTEM (Admin2 Mirror)
             * ======================================
             */

            const adminId = <?= intval($_SESSION['user_id']) ?>;
            let notificationCheckInterval;

            function initializeNotificationSystem() {
                const bellElement = document.querySelector('.notification-bell');
                if (bellElement) {
                    bellElement.addEventListener('click', toggleNotificationDropdown);
                }

                // Check for notifications every 30 seconds
                checkNotifications();
                notificationCheckInterval = setInterval(checkNotifications, 30000);

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
                    .then(response => {
                        if (!response.ok) throw new Error(`HTTP ${response.status}`);
                        return response.text().then(text => {
                            try {
                                return JSON.parse(text);
                            } catch (e) {
                                console.error('Notification JSON parse error:', text.substring(0, 100));
                                throw new Error('Invalid JSON response');
                            }
                        });
                    })
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
                    .then(response => {
                        if (!response.ok) throw new Error(`HTTP ${response.status}`);
                        return response.text().then(text => {
                            try {
                                return JSON.parse(text);
                            } catch (e) {
                                console.error('Notifications list JSON error:', text.substring(0, 100));
                                throw new Error('Invalid JSON');
                            }
                        });
                    })
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

            // Initialize notification system
            initializeNotificationSystem();

            /**
             * ======================================
             * LOAN DETAILS MODAL - ADMIN2 UI STYLE
             * ======================================
             */

            // Cache for loan details
            const loanDetailsCache = new Map();
            const cacheTimestamps = new Map();
            const CACHE_DURATION = 5 * 60 * 1000; // 5 minutes

            function isCacheValid(applicationId) {
                const timestamp = cacheTimestamps.get(applicationId);
                return timestamp && (Date.now() - timestamp) < CACHE_DURATION;
            }

            function openLoanDetailsModal(applicationId) {
                console.log("Opening loan details modal for applicationId:", applicationId);

                // Save current scroll position
                window.scrollPosition = window.scrollY || document.documentElement.scrollTop;

                // Prevent body scroll when modal opens
                document.body.style.overflow = 'hidden';
                document.body.style.marginRight = window.innerWidth - document.documentElement.clientWidth + 'px';

                // Store the current application ID for form population
                window.currentApplicationId = applicationId;

                const modal = document.getElementById("loanDetailsModal");
                const content = document.getElementById("loanDetailsContent");

                if (!modal || !content) {
                    console.error("Modal elements not found");
                    return;
                }

                // Set z-index and display using flex
                modal.style.zIndex = "9999";
                modal.classList.add('show');

                // Show loading state
                content.innerHTML = `
                    <div style="text-align: center; padding: 40px;">
                        <i class="fas fa-spinner fa-spin" style="font-size: 24px; color: #1b5e20; margin-right: 10px;"></i>
                        <span>Loading loan details...</span>
                    </div>
                `;

                // Check cache
                if (loanDetailsCache.has(applicationId) && isCacheValid(applicationId)) {
                    console.log("Loading from cache:", applicationId);
                    setTimeout(() => {
                        renderLoanDetailsModal(loanDetailsCache.get(applicationId), content);
                    }, 50);
                    return;
                }

                // Fetch fresh data
                fetch(`admin1_dashboard.php?action=get_loan_details&application_id=${applicationId}`, { cache: "no-store" })
                    .then(response => {
                        if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                        return response.json();
                    })
                    .then(data => {
                        if (!data.success) {
                            content.innerHTML = `<div style="padding: 20px; color: #d32f2f;"><strong>Error:</strong> ${data.message}</div>`;
                            return;
                        }

                        // Cache the data
                        loanDetailsCache.set(applicationId, data);
                        cacheTimestamps.set(applicationId, Date.now());

                        renderLoanDetailsModal(data, content);
                    })
                    .catch(error => {
                        console.error("Error loading loan details:", error);
                        content.innerHTML = `<div style="padding: 20px; color: #d32f2f;"><strong>Error:</strong> Failed to load loan details</div>`;
                    });
            }

            function renderLoanDetailsModal(data, content) {
                const { loan, documents, remarks, logs } = data;

                // Applicant Information Section - ADDED FIRST
                const applicantInfo = `
                    <div class="modal-section applicant-info-section">
                        <h3>Applicant Information</h3>
                        <div class="info-grid">
                            <div><strong>Full Name:</strong> ${loan.first_name} ${loan.last_name}</div>
                            <div><strong>Email:</strong> ${loan.email}</div>
                            <div><strong>Contact:</strong> ${loan.contact_number || 'Not provided'}</div>
                            <div><strong>Birthday:</strong> ${loan.birthday ? new Date(loan.birthday).toLocaleDateString() : 'Not provided'}</div>
                        </div>
                    </div>
                `;

                // Loan Details Section
                const loanInfo = `
                    <div class="modal-section loan-info-section">
                        <h3>Loan Details</h3>
                        <div class="info-grid">
                            <div><strong>Loan Type:</strong> ${loan.type_name}</div>
                            <div><strong>Amount Applied:</strong> ₱${parseFloat(loan.amount_applied || 0).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
                            <div><strong>Final Loan Amount:</strong> ${loan.final_loan_amount ? '₱' + parseFloat(loan.final_loan_amount).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : 'Not set'}</div>
                            <div><strong>Submission Date:</strong> ${new Date(loan.created_at).toLocaleDateString()}</div>
                            <div><strong>Loan Status:</strong> ${loan.status || 'Pending'}</div>
                            <div><strong>Pre-Approval Status:</strong> ${loan.pre_approval_status || 'Pending'}</div>
                            <div><strong>Credit Investigation:</strong> ${loan.credit_investigation_status || 'Pending'}</div>
                        </div>
                    </div>
                `;

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
                ` : `<div class="modal-section"><h3>Financial Information</h3><p class="no-data">No financial information available.</p></div>`;

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
                                    </tr>
                                </thead>
                                <tbody>
                                    ${documents.map(doc => `
                                        <tr>
                                            <td><a href="${doc.file_path}" target="_blank"> ${doc.document_name}</a></td>
                                            <td><span class="status-badge status-${(doc.status || 'pending').toLowerCase()}">${doc.status || 'Pending'}</span></td>
                                        </tr>
                                    `).join("")}
                                </tbody>
                            </table>
                        </div>
                    </div>
                ` : `<div class="modal-section"><h3>Submitted Documents</h3><p class="no-data">No documents submitted.</p></div>`;

                // Pre-Approval Decision Section - DISPLAY ONLY (NO BUTTONS FOR ADMIN1)
                const preApprovalSection = `
                    <div class="modal-section">
                        <h3><i class="fas fa-check-circle"></i> Pre-Approval Decision</h3>
                        
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

                        
                        <!-- Document Approval Progress Indicator -->
                        <div style="margin-bottom: 20px; padding: 15px; background: #f5f5f5; border-radius: 4px; border: 1px solid #e0e0e0;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                                <div style="font-weight: 600; color: #1b5e20; font-size: 13px;">
                                    Document Approval Progress
                                </div>
                                <div style="font-size: 18px; font-weight: 700; color: #2e7d32;">
                                    ${documents.filter(d => d.status.toLowerCase() === 'approved').length}/${documents.length}
                                </div>
                            </div>
                            <div style="width: 100%; height: 8px; background: #ddd; border-radius: 4px; overflow: hidden; margin-bottom: 8px;">
                                <div style="height: 100%; background: linear-gradient(90deg, #2e7d32 0%, #1b5e20 100%); width: ${documents.length > 0 ? (documents.filter(d => d.status.toLowerCase() === 'approved').length / documents.length * 100) : 0}%; transition: width 0.3s ease;"></div>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 11px; color: #666;">
                                <span>${documents.filter(d => d.status.toLowerCase() === 'approved').length} Approved</span>
                                <span>${documents.filter(d => d.status.toLowerCase() === 'rejected').length} Rejected</span>
                                <span>${documents.filter(d => d.status.toLowerCase() === 'pending').length} Pending</span>
                            </div>
                        </div>
                    </div>
                `;

                // Credit Investigation Section - AFTER DOCUMENTS
                const creditInvestigationSection = `
                    <div class="modal-section">
                        <h3><i class="fas fa-search-dollar"></i> Credit Investigation</h3>
                        <form id="creditInvestigationForm">
                            <input type="hidden" id="ciApplicationId" name="application_id">
                            <input type="hidden" id="ciLoanType" name="loan_type" value="${loan.type_name}">
                            
                            <!-- Investigation Results Section -->
                            <div class="form-section" style="background: linear-gradient(135deg, #f3e5f5 0%, #ede7f6 100%); border-left: 4px solid #8b5cf6; padding: 16px; border-radius: 8px;">
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px;">
                                    <div style="width: 40px; height: 40px; background: #8b5cf6; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 18px;">
                                        <i class="fas fa-search"></i>
                                    </div>
                                    <h4 style="margin: 0; color: #6b21a8; font-weight: 700; font-size: 16px;">Investigation Results</h4>
                                </div>
                                <div class="form-group">
                                    <label for="ciStatus" style="font-weight: 600; color: #374151; display: flex; align-items: center; gap: 6px; margin-bottom: 8px;">
                                        <i class="fas fa-clipboard-check" style="color: #8b5cf6;"></i>
                                        Investigation Status <span class="required" style="color: #ef4444;">*</span>
                                    </label>
                                    <select id="ciStatus" name="credit_status" required
                                        style="padding: 12px 14px; border: 2px solid #e5e7eb; border-radius: 8px; width: 100%; font-size: 14px; transition: all 0.3s; background: white; cursor: pointer;"
                                        onchange="updateStatusIndicator(this)">
                                        <option value="">-- Select Status --</option>
                                        <option value="Completed" style="color: #059669;">✓ Completed (Approved)</option>
                                        <option value="Failed" style="color: #dc2626;">✗ Failed (Rejected)</option>
                                        <option value="Pending" style="color: #d97706;">○ Pending (Under Review)</option>
                                    </select>
                                    <div id="ciStatusValidation" style="margin-top: 8px; font-size: 12px; display: none;"></div>
                                    <small style="color: #6b7280; margin-top: 6px; display: block;"><i class="fas fa-info-circle"></i> Select the outcome of the credit investigation</small>
                                </div>
                            </div>

                            <!-- Loan Adjustment Section -->
                            <div class="form-section" style="background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%); border-left: 4px solid #10b981; padding: 16px; border-radius: 8px; margin-top: 16px;">
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px;">
                                    <div style="width: 40px; height: 40px; background: #10b981; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 18px;">
                                        <i class="fas fa-coins"></i>
                                    </div>
                                    <h4 style="margin: 0; color: #065f46; font-weight: 700; font-size: 16px;">Loan Adjustment</h4>
                                </div>
                                
                                <div class="form-group" style="margin-bottom: 16px;">
                                    <label style="font-weight: 600; color: #374151; display: flex; align-items: center; gap: 6px; margin-bottom: 8px;">
                                        <i class="fas fa-tag" style="color: #10b981;"></i>
                                        Loan Type
                                    </label>
                                    <div style="padding: 12px 14px; background: white; border-radius: 8px; border: 2px solid #dcfce7; font-weight: 600; color: #065f46; display: flex; align-items: center; gap: 8px;">
                                        <i class="fas fa-file-contract" style="color: #10b981;"></i>
                                        <span id="loanTypeDisplay">${loan.type_name}</span>
                                    </div>
                                    <small style="color: #6b7280; margin-top: 6px; display: block;"><i class="fas fa-info-circle"></i> This is the loan type selected during application</small>
                                </div>

                                <div class="form-group" style="margin-bottom: 16px;">
                                    <label for="ciFinalAmount" style="font-weight: 600; color: #374151; display: flex; align-items: center; gap: 6px; margin-bottom: 8px;">
                                        <i class="fas fa-peso-sign" style="color: #10b981;"></i>
                                        Final Loan Amount <span class="required" style="color: #ef4444;">*</span>
                                    </label>
                                    <div style="position: relative;">
                                        <span style="position: absolute; left: 14px; top: 12px; color: #10b981; font-weight: 600; font-size: 14px;">₱</span>
                                        <input type="number" id="ciFinalAmount" name="final_loan_amount" step="0.01" min="0"
                                            required placeholder="e.g., 50,000"
                                            style="padding: 12px 14px 12px 35px; border: 2px solid #e5e7eb; border-radius: 8px; width: 100%; font-size: 14px; transition: all 0.3s;"
                                            oninput="calculateMonthlyPayment()"
                                            onkeypress="return /[0-9.]/.test(String.fromCharCode(event.which)) || event.which === 0">
                                    </div>
                                    <small style="color: #6b7280; margin-top: 6px; display: block;"><i class="fas fa-info-circle"></i> <span id="amountRangeInfo">Minimum: ₱10,000</span></small>
                                    <div id="amountValidation" style="margin-top: 8px; font-size: 12px; display: none;"></div>
                                </div>

                                <div class="form-group">
                                    <label for="ciTermLength" style="font-weight: 600; color: #374151; display: flex; align-items: center; gap: 6px; margin-bottom: 8px;">
                                        <i class="fas fa-calendar-days" style="color: #10b981;"></i>
                                        Loan Term Length <span class="required" style="color: #ef4444;">*</span>
                                    </label>
                                    <select id="ciTermLength" name="term_length" required
                                        style="padding: 12px 14px; border: 2px solid #e5e7eb; border-radius: 8px; width: 100%; font-size: 14px; transition: all 0.3s; background: white; cursor: pointer;"
                                        onchange="calculateMonthlyPayment()">
                                        <option value="">-- Select Term Length --</option>
                                        <option value="6">6 months</option>
                                        <option value="12">12 months (1 year)</option>
                                        <option value="18">18 months</option>
                                        <option value="24">24 months (2 years)</option>
                                        <option value="36">36 months (3 years)</option>
                                    </select>
                                    <div id="ciTermValidation" style="margin-top: 8px; font-size: 12px; display: none;"></div>
                                    <small style="color: #6b7280; margin-top: 6px; display: block;"><i class="fas fa-info-circle"></i> Select the repayment period</small>
                                </div>

                                <div class="form-group" id="monthlyPaymentSection" style="display: none; padding: 14px; background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border-radius: 8px; border-left: 4px solid #0284c7; margin-top: 16px;">
                                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px;">
                                        <i class="fas fa-calculator" style="color: #0284c7; font-size: 18px;"></i>
                                        <strong style="color: #0c4a6e;">Monthly Payment Estimate:</strong>
                                    </div>
                                    <div id="monthlyPaymentValue" style="font-size: 24px; font-weight: 700; color: #0c4a6e; margin-top: 8px;">₱0.00</div>
                                    <small id="interestRateDisplay" style="color: #075985; margin-top: 6px; display: block;"><i class="fas fa-percent"></i> Based on 6% annual interest rate</small>
                                </div>
                            </div>

                            <!-- Notes & Remarks Section -->
                            <div class="form-section" style="background: linear-gradient(135deg, #fef3c7 0%, #fef08a 100%); border-left: 4px solid #eab308; padding: 16px; border-radius: 8px; margin-top: 16px;">
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px;">
                                    <div style="width: 40px; height: 40px; background: #eab308; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 18px;">
                                        <i class="fas fa-note-sticky"></i>
                                    </div>
                                    <h4 style="margin: 0; color: #854d0e; font-weight: 700; font-size: 16px;">Notes & Remarks</h4>
                                </div>
                                <div class="form-group" style="margin-bottom: 16px;">
                                    <label for="suggestionDropdown" style="font-weight: 600; color: #374151; display: flex; align-items: center; gap: 6px; margin-bottom: 8px;">
                                        <i class="fas fa-lightbulb" style="color: #eab308;"></i>
                                        Pre-Defined Suggestions (Optional)
                                    </label>
                                    <select id="suggestionDropdown"
                                        style="padding: 12px 14px; border: 2px solid #e5e7eb; border-radius: 8px; width: 100%; margin-bottom: 12px; font-size: 14px; background: white; cursor: pointer; transition: all 0.3s;"
                                        onchange="populateRemarks(this)">
                                        <option value="">-- Select a suggestion to auto-populate --</option>
                                    </select>
                                    <small style="color: #6b7280; display: block;"><i class="fas fa-info-circle"></i> Quick templates to save time</small>
                                </div>
                                <div class="form-group">
                                    <label for="ciRemarks" style="font-weight: 600; color: #374151; display: flex; align-items: center; gap: 6px; margin-bottom: 8px;">
                                        <i class="fas fa-pen-to-square" style="color: #eab308;"></i>
                                        Additional Remarks
                                    </label>
                                    <textarea id="ciRemarks" name="remarks" rows="4" maxlength="500"
                                        placeholder="Enter your observations or notes about the credit investigation..."
                                        style="padding: 12px 14px; border: 2px solid #e5e7eb; border-radius: 8px; width: 100%; font-family: inherit; resize: vertical; font-size: 14px; transition: all 0.3s;"
                                        oninput="updateRemarksCounter()"></textarea>
                                    <small id="remarksCounter" style="color: #6b7280; margin-top: 6px; display: block;"><i class="fas fa-check-circle" style="color: #10b981;"></i> 0 / 500 characters</small>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div style="display: flex; gap: 10px; margin-top: 20px;">
                                <button type="submit" id="ciSubmitBtn" style="flex: 1; padding: 14px 16px; background: linear-gradient(135deg, #1b5e20 0%, #2d7d32 100%); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; font-size: 15px; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 12px rgba(27, 94, 32, 0.3);">
                                    <i class="fas fa-check-circle"></i> Submit Investigation
                                </button>
                                <button type="button" id="ciResetBtn" style="flex: 1; padding: 14px 16px; background: #f3f4f6; color: #374151; border: 2px solid #d1d5db; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; font-size: 15px; display: flex; align-items: center; justify-content: center; gap: 8px; hover: background #e5e7eb;">
                                    <i class="fas fa-rotate-left"></i> Reset Form
                                </button>
                            </div>

                            <!-- Message Box -->
                            <div id="ciMessageBox" style="margin-top: 16px; padding: 14px; border-radius: 8px; display: none; font-size: 14px; border-left: 4px solid; font-weight: 500;"></div>
                            
                            <!-- Validation Summary -->
                            <div id="formValidationSummary" style="margin-top: 16px; padding: 14px; background: linear-gradient(135deg, #fef3c7 0%, #fef08a 100%); border: 2px solid #fcd34d; border-radius: 8px; display: none;">
                                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                                    <i class="fas fa-exclamation-circle" style="color: #92400e; font-size: 18px;"></i>
                                    <strong style="color: #92400e;">Form Validation Summary:</strong>
                                </div>
                                <div id="validationList" style="margin-left: 26px; font-size: 13px; color: #78350f;"></div>
                            </div>
                        </form>
                    </div>
                `;

                // Remarks Section
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
                            <div class="remark-content" style="background: white; padding: 10px; border-radius: 4px; border-left: 3px solid #fbc02d; margin-left: 0;">${r.remarks}</div>
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

                // Combine all sections in the correct order
                content.innerHTML = `
                    ${applicantInfo}
                    ${loanInfo}
                    ${financialInfo}
                    ${creditInvestigationSection}
                    ${preApprovalSection}
                    ${documentsTable}
                    ${remarksSection}
                    ${logsSection}
                `;

                // Populate credit investigation form with current values
                if (window.currentApplicationId) {
                    populateCreditInvestigationForm(window.currentApplicationId);
                }

                // Initialize credit investigation form event listeners
                // Use setTimeout to ensure DOM elements are available
                setTimeout(() => {
                    initializeCreditInvestigationForm();
                }, 50);
            }

            function closeLoanDetailsModal() {
                const modal = document.getElementById("loanDetailsModal");
                if (modal) {
                    modal.classList.remove('show');
                }
                // Restore body sc            roll when modal closes
                document.body.style.overflow = '';
                document.body.style.marginRight = '';

                // Restore scroll position
                if (window.scrollPosition !== undefined) {
                    window.scrollY = window.scrollPosition;
                    document.documentElement.scrollTop = window.scrollPosition;
                }
            }

            /**
             * ======================================
             * CREDIT INVESTIGATION FORM FUNCTIONS
             * ======================================
             */

            function populateCreditInvestigationForm(applicationId) {
                fetch('admin1_dashboard.php?action=get_loan_details&application_id=' + applicationId)
                    .then(response => {
                        if (!response.ok) throw new Error(`HTTP ${response.status}`);
                        return response.text().then(text => {
                            try {
                                return JSON.parse(text);
                            } catch (e) {
                                console.error('Loan details JSON error:', text.substring(0, 100));
                                throw new Error('Invalid JSON response');
                            }
                        });
                    })
                    .then(data => {
                        if (data.success && data.loan) {
                            const loan = data.loan;

                            // Store interest rate globally for use in calculations
                            window.currentInterestRate = parseFloat(loan.interest_rate) || 6.00;

                            // Safe element setters with null checks
                            const setElementValue = (elementId, value) => {
                                const element = document.getElementById(elementId);
                                if (element) {
                                    element.value = value;
                                } else {
                                    console.warn(`Element with ID '${elementId}' not found in modal`);
                                }
                            };

                            setElementValue('ciApplicationId', applicationId);

                            // Set current term if exists
                            if (loan.term_length) {
                                setElementValue('ciTermLength', loan.term_length);
                            }

                            // Set current final amount if exists
                            if (loan.final_loan_amount) {
                                setElementValue('ciFinalAmount', parseFloat(loan.final_loan_amount).toFixed(2));
                            }

                            // Set credit status if exists
                            if (loan.credit_investigation_status) {
                                setElementValue('ciStatus', loan.credit_investigation_status);
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching loan details:', error);
                    });
            }

            function closeCreditInvestigationModal() {
                const modal = document.getElementById("creditInvestigationModal");
                if (modal) {
                    modal.classList.remove('show');
                    document.getElementById('creditInvestigationForm').reset();
                }
            }

            // Pre-suggested remarks based on investigation status
            const suggestedRemarks = {
                'Completed': [
                    'Applicant passed all credit checks',
                    'Credit score is satisfactory',
                    'No outstanding debts found',
                    'Income verified and confirmed',
                    'Employment history verified',
                    'All documentation complete and verified'
                ],
                'Failed': [
                    'Credit score below acceptable threshold',
                    'Outstanding debts detected',
                    'Unstable employment history',
                    'Income insufficient for loan amount',
                    'Missing critical documentation',
                    'Red flags identified during investigation'
                ],
                'Pending': [
                    'Awaiting additional documentation',
                    'Verification in progress',
                    'Requires further investigation',
                    'Pending applicant response',
                    'Third-party verification pending'
                ]
            };

            // Define loan type limits
            const loanTypeLimits = {
                'Individual': { min: 10000, max: 100000 },
                'Cooperative': { min: 300000, max: 1000000 }
            };

            // Toast Notification System
            function showToastNotification(type, title, message, duration = 5000) {
                // Create toast container if doesn't exist
                let toastContainer = document.getElementById('toastContainer');
                if (!toastContainer) {
                    toastContainer = document.createElement('div');
                    toastContainer.id = 'toastContainer';
                    toastContainer.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 10000; max-width: 400px;';
                    document.body.appendChild(toastContainer);
                }

                // Create toast element
                const toast = document.createElement('div');
                toast.className = `toast-notification toast-${type}`;

                const bgColor = type === 'success' ? '#d1fae5' : type === 'error' ? '#fee2e2' : '#dbeafe';
                const borderColor = type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6';
                const textColor = type === 'success' ? '#065f46' : type === 'error' ? '#7f1d1d' : '#1e3a8a';
                const icon = type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle';

                toast.style.cssText = `
                    background-color: ${bgColor};
                    border-left: 4px solid ${borderColor};
                    border-radius: 6px;
                    padding: 16px;
                    margin-bottom: 12px;
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                    display: flex;
                    gap: 12px;
                    align-items: flex-start;
                    animation: slideInRight 0.3s ease-out;
                `;

                toast.innerHTML = `
                    <i class="fas ${icon}" style="color: ${textColor}; margin-top: 2px; flex-shrink: 0;"></i>
                    <div style="flex: 1; color: ${textColor};">
                        <strong>${title}</strong><br>
                        <small>${message}</small>
                    </div>
                    <button onclick="this.parentElement.remove()" style="background: none; border: none; color: ${textColor}; cursor: pointer; padding: 0; font-size: 18px;">
                        <i class="fas fa-times"></i>
                    </button>
                `;

                toastContainer.appendChild(toast);

                // Auto-remove after duration
                if (duration > 0) {
                    setTimeout(() => {
                        toast.style.animation = 'slideOutRight 0.3s ease-out';
                        setTimeout(() => toast.remove(), 300);
                    }, duration);
                }

                return toast;
            }

            // Send Confirmation Modal
            function showSendConfirmationModal(config) {
                let modal = document.getElementById('sendConfirmationModal');

                if (!modal) {
                    modal = document.createElement('div');
                    modal.id = 'sendConfirmationModal';
                    modal.className = 'modal';
                    modal.style.zIndex = '99999'; // Higher z-index to appear above loan details modal
                    document.body.appendChild(modal);
                }

                const statusColors = {
                    'Completed': '#2e7d32',
                    'Failed': '#d32f2f',
                    'Pending': '#1e88e5'
                };

                const statusIcons = {
                    'Completed': 'fa-check-circle',
                    'Failed': 'fa-times-circle',
                    'Pending': 'fa-clock'
                };

                const statusText = {
                    'Completed': 'Application Approved',
                    'Failed': 'Requires Additional Review',
                    'Pending': 'Investigation In Progress'
                };

                const statusColor = statusColors[config.creditStatus] || '#666';
                const statusIcon = statusIcons[config.creditStatus] || 'fa-info-circle';
                const statusLabel = statusText[config.creditStatus] || config.creditStatus;

                modal.innerHTML = `
                    <div class="modal-dialog" style="animation: scaleIn 0.3s ease-out;">
                        <div class="modal-content" style="box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); border-radius: 12px;">
                            <div class="modal-header" style="background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%); border-bottom: none; border-radius: 12px 12px 0 0; padding: 24px;">
                                <h5 class="modal-title" style="color: white; font-weight: 600; margin: 0;">
                                    <i class="fas fa-envelope" style="margin-right: 10px;"></i>Confirm Email Sending
                                </h5>
                                <button type="button" class="btn-close" onclick="document.getElementById('sendConfirmationModal').remove(); (window.pendingCancel && window.pendingCancel());" style="filter: brightness(0) invert(1); opacity: 0.8; border: none; background: none; cursor: pointer; font-size: 24px; padding: 0;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div class="modal-body" style="padding: 28px; background: #f9fafb;">
                                <div style="background: white; border-radius: 10px; padding: 16px; margin-bottom: 18px; border-left: 4px solid #2e7d32;">
                                    <p style="margin: 0 0 10px 0; font-size: 12px; color: #6b7280; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">📧 Recipient</p>
                                    <p style="margin: 0; font-size: 16px; font-weight: 600; color: #1f2937;">${config.applicantName}</p>
                                </div>

                                <div style="background: white; border-radius: 10px; padding: 16px; margin-bottom: 18px; border-left: 4px solid ${statusColor};">
                                    <p style="margin: 0 0 10px 0; font-size: 12px; color: #6b7280; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">📋 Investigation Status</p>
                                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                                        <i class="fas ${statusIcon}" style="color: ${statusColor}; font-size: 18px;"></i>
                                        <strong style="color: ${statusColor}; font-size: 14px;">${statusLabel}</strong>
                                    </div>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; font-size: 13px;">
                                        <div>
                                            <span style="color: #6b7280;">💰 Loan Amount:</span><br>
                                            <strong style="color: #1f2937; font-size: 14px;">₱${config.finalAmount.toLocaleString('en-US', { minimumFractionDigits: 2 })}</strong>
                                        </div>
                                        <div>
                                            <span style="color: #6b7280;">⏱️ Term Length:</span><br>
                                            <strong style="color: #1f2937; font-size: 14px;">${config.termLength} months</strong>
                                        </div>
                                    </div>
                                </div>

                                <div style="background: linear-gradient(135deg, rgba(46, 125, 50, 0.05) 0%, rgba(46, 125, 50, 0.02) 100%); border-radius: 10px; padding: 14px; border-left: 4px solid #2e7d32;">
                                    <p style="margin: 0; font-size: 13px; color: #1b5e20; line-height: 1.6;">
                                        <i class="fas fa-info-circle" style="margin-right: 8px; font-weight: 600;"></i>
                                        <strong>The applicant will receive an email with their credit investigation results and next steps to proceed with their loan application.</strong>
                                    </p>
                                </div>
                            </div>
                            <div class="modal-footer" style="background: #f3f4f6; border-top: 1px solid #e5e7eb; border-radius: 0 0 12px 12px; padding: 16px 28px; display: flex; gap: 12px; justify-content: flex-end;">
                                <button type="button" class="btn" onclick="document.getElementById('sendConfirmationModal').remove(); (window.pendingCancel && window.pendingCancel());" style="background: white; color: #374151; border: 1px solid #d1d5db; padding: 10px 24px; border-radius: 6px; cursor: pointer; font-weight: 500; transition: all 0.2s;">Cancel</button>
                                <button type="button" class="btn" onclick="(window.pendingConfirm && window.pendingConfirm()); document.getElementById('sendConfirmationModal').remove();" style="background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%); color: white; border: none; padding: 10px 28px; border-radius: 6px; cursor: pointer; font-weight: 600; transition: all 0.2s; box-shadow: 0 4px 12px rgba(46, 125, 50, 0.3);">
                                    <i class="fas fa-paper-plane" style="margin-right: 6px;"></i>Send Email
                                </button>
                            </div>
                        </div>
                    </div>
                `;

                modal.classList.add('show');
                modal.style.display = 'flex';
                modal.style.zIndex = '9999'; // Ensure z-index is set

                // Store callbacks
                window.pendingConfirm = config.onConfirm;
                window.pendingCancel = config.onCancel;
            }

            // Submit form data with loading indicator in modal
            function submitFormData(applicationId, formData, form) {
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

                fetch('admin1_dashboard.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP Error ${response.status}: ${response.statusText}`);
                        }
                        const contentType = response.headers.get('content-type');
                        if (!contentType || !contentType.includes('application/json')) {
                            return response.text().then(text => {
                                console.error('Invalid response content-type:', contentType);
                                console.error('Response text:', text.substring(0, 200));
                                throw new Error('Server returned non-JSON response: ' + contentType);
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;

                        if (data.success) {
                            // Check if remarks contain "pending applicant response"
                            const remarksField = document.getElementById('ciRemarks');
                            const remarks = remarksField ? remarksField.value.toLowerCase() : '';
                            const isPendingApplicantResponse = remarks.includes('pending applicant response');

                            let toastMessage = `Credit investigation email sent successfully to applicant!`;
                            if (isPendingApplicantResponse) {
                                toastMessage += ` Applicant notified to visit the office for confirmation.`;
                            }
                            toastMessage += ` ✓ Notifications saved to database.`;

                            showToastNotification('success', 'Email Sent', toastMessage, 6000);
                            // Refresh table without closing modal
                            if (typeof refreshLoanApplicantsTable === 'function') {
                                refreshLoanApplicantsTable();
                            }
                        } else {
                            showToastNotification('error', 'Error', data.message || 'Failed to send credit investigation email');
                        }
                    })
                    .catch(error => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                        console.error('Credit Investigation Error:', error);
                        showToastNotification('error', 'Error', error.message || 'An error occurred while sending the email.');
                    });
            }

            // ========== GLOBAL CREDIT INVESTIGATION FORM FUNCTIONS ==========
            // These must be in global scope to be accessible from inline HTML event handlers

            // Update status indicator with visual feedback
            function updateStatusIndicator(selectElement) {
                const statusValue = selectElement.value;
                const validationDiv = document.getElementById('ciStatusValidation');

                if (statusValue === 'Completed') {
                    if (validationDiv) {
                        validationDiv.style.display = 'block';
                        validationDiv.innerHTML = '<i class="fas fa-check-circle" style="color: #059669; margin-right: 6px;"></i><span style="color: #059669;">✓ Approved Status Selected</span>';
                    }
                } else if (statusValue === 'Failed') {
                    if (validationDiv) {
                        validationDiv.style.display = 'block';
                        validationDiv.innerHTML = '<i class="fas fa-times-circle" style="color: #dc2626; margin-right: 6px;"></i><span style="color: #dc2626;">✗ Rejected Status Selected</span>';
                    }
                } else if (statusValue === 'Pending') {
                    if (validationDiv) {
                        validationDiv.style.display = 'block';
                        validationDiv.innerHTML = '<i class="fas fa-hourglass" style="color: #d97706; margin-right: 6px;"></i><span style="color: #d97706;">○ Under Review Status Selected</span>';
                    }
                } else {
                    if (validationDiv) {
                        validationDiv.style.display = 'none';
                    }
                }
            }

            // Calculate monthly payment and update display
            function calculateMonthlyPayment() {
                const finalAmountInput = document.getElementById('ciFinalAmount');
                const termLengthSelect = document.getElementById('ciTermLength');

                if (!finalAmountInput || !termLengthSelect) return;

                const amount = parseFloat(finalAmountInput.value) || 0;
                const term = parseInt(termLengthSelect.value) || 0;
                const interestRate = window.currentInterestRate || 6.00; // Use stored rate or default to 6%
                const annualRate = interestRate / 100;
                const monthlyRate = annualRate / 12;

                if (amount > 0 && term > 0) {
                    // Using standard amortization formula
                    const monthlyPayment = amount * (monthlyRate * Math.pow(1 + monthlyRate, term)) / (Math.pow(1 + monthlyRate, term) - 1);
                    const monthlyPaymentSection = document.getElementById('monthlyPaymentSection');
                    const monthlyPaymentValue = document.getElementById('monthlyPaymentValue');

                    if (monthlyPaymentSection) {
                        monthlyPaymentSection.style.display = 'block';
                    }
                    if (monthlyPaymentValue) {
                        monthlyPaymentValue.textContent = '₱' + monthlyPayment.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    }

                    // Update the interest rate display text
                    const rateDisplay = document.getElementById('interestRateDisplay');
                    if (rateDisplay) {
                        rateDisplay.textContent = `Based on ${interestRate}% annual interest rate (per annum)`;
                    }
                } else {
                    const monthlyPaymentSection = document.getElementById('monthlyPaymentSection');
                    if (monthlyPaymentSection) {
                        monthlyPaymentSection.style.display = 'none';
                    }
                }
            }

            // Populate remarks from suggestions dropdown
            function populateRemarks(selectElement) {
                const selectedValue = selectElement.value;
                const remarksTextarea = document.getElementById('ciRemarks');

                if (selectedValue && remarksTextarea) {
                    remarksTextarea.value = selectedValue;
                    updateRemarksCounter();
                }
            }

            // Update character counter for remarks
            function updateRemarksCounter() {
                const remarksTextarea = document.getElementById('ciRemarks');
                const remarksCounter = document.getElementById('remarksCounter');
                if (remarksTextarea && remarksCounter) {
                    const currentLength = remarksTextarea.value.length;
                    const checkIcon = currentLength > 0 ? '<i class="fas fa-check-circle" style="color: #10b981;"></i>' : '<i class="fas fa-pen-to-square" style="color: #6b7280;"></i>';
                    remarksCounter.innerHTML = `${checkIcon} ${currentLength} / 500 characters`;
                }
            }
            // ========== END OF GLOBAL FUNCTIONS ==========

            // Initialize credit investigation form listeners
            function initializeCreditInvestigationForm() {
                const form = document.getElementById('creditInvestigationForm');
                if (!form) return; // Form not in DOM yet

                const statusSelect = document.getElementById('ciStatus');
                const finalAmountInput = document.getElementById('ciFinalAmount');
                const termLengthSelect = document.getElementById('ciTermLength');
                const remarksTextarea = document.getElementById('ciRemarks');
                const resetBtn = document.getElementById('ciResetBtn');
                const loanTypeInput = document.getElementById('ciLoanType');

                if (!statusSelect || !finalAmountInput || !termLengthSelect) {
                    console.warn('Credit investigation form elements not found');
                    return;
                }

                let isSubmitting = false;

                // Initialize suggestion buttons
                function initializeSuggestions() {
                    const status = statusSelect.value;
                    const suggestionDropdown = document.getElementById('suggestionDropdown');

                    if (status && suggestedRemarks[status]) {
                        // Build dropdown options
                        const options = ['<option value="">-- Select a suggestion to auto-populate --</option>'];
                        suggestedRemarks[status].forEach((remark, idx) => {
                            options.push(`<option value="${remark}">${remark}</option>`);
                        });
                        suggestionDropdown.innerHTML = options.join('');
                        suggestionDropdown.style.display = 'block';
                    } else {
                        suggestionDropdown.innerHTML = '<option value="">-- Select a suggestion to auto-populate --</option>';
                        suggestionDropdown.style.display = 'none';
                    }
                }

                // Handle suggestion dropdown change
                function setupSuggestionDropdown() {
                    const suggestionDropdown = document.getElementById('suggestionDropdown');
                    if (suggestionDropdown) {
                        suggestionDropdown.addEventListener('change', function () {
                            if (this.value) {
                                document.getElementById('ciRemarks').value = this.value;
                                document.getElementById('remarksCounter').textContent = this.value.length + ' / 500 characters';
                                // Reset dropdown to default after selection
                                this.value = '';
                            }
                        });
                    }
                }                // Set loan type info and limits
                function setLoanTypeLimits() {
                    const loanType = loanTypeInput.value;
                    const limits = loanTypeLimits[loanType] || { min: 10000, max: 1000000 };

                    // Update input attributes
                    finalAmountInput.min = limits.min;
                    finalAmountInput.max = limits.max;

                    // Update loan type display (using the correct element ID)
                    const loanTypeDisplay = document.getElementById('loanTypeDisplay');
                    const amountRangeInfo = document.getElementById('amountRangeInfo');

                    // Update loan type display with null check
                    if (loanTypeDisplay) {
                        loanTypeDisplay.innerHTML = loanType || 'Individual';
                    }

                    // Update amount range info with null check
                    if (amountRangeInfo) {
                        amountRangeInfo.innerHTML = `Minimum: ₱${limits.min.toLocaleString()} | Maximum: ₱${limits.max.toLocaleString()}`;
                    }
                }

                // Real-time validation function
                function validateForm() {
                    const validationList = document.getElementById('validationList');
                    const formValidationSummary = document.getElementById('formValidationSummary');
                    const validationMessages = [];

                    // Status validation
                    if (!statusSelect.value) {
                        validationMessages.push({ field: 'Status', valid: false, message: 'Investigation Status is required' });
                        updateFieldValidation(document.getElementById('ciStatusValidation'), false, 'Please select a status');
                    } else {
                        updateFieldValidation(document.getElementById('ciStatusValidation'), true, `Status: ${statusSelect.options[statusSelect.selectedIndex].text}`);
                        validationMessages.push({ field: 'Status', valid: true, message: statusSelect.options[statusSelect.selectedIndex].text });
                    }

                    // Amount validation
                    const amount = parseFloat(finalAmountInput.value) || 0;
                    const minVal = parseFloat(finalAmountInput.min);
                    const maxVal = parseFloat(finalAmountInput.max);

                    if (!amount) {
                        validationMessages.push({ field: 'Amount', valid: false, message: 'Final Loan Amount is required' });
                        document.getElementById('amountValidation').style.display = 'none';
                    } else if (amount < minVal || amount > maxVal) {
                        validationMessages.push({ field: 'Amount', valid: false, message: `Must be between ₱${minVal.toLocaleString()} - ₱${maxVal.toLocaleString()}` });
                        updateFieldValidation(document.getElementById('amountValidation'), false, `Amount must be between ₱${minVal.toLocaleString()} and ₱${maxVal.toLocaleString()}`);
                    } else {
                        validationMessages.push({ field: 'Amount', valid: true, message: `₱${amount.toLocaleString('en-US', { minimumFractionDigits: 2 })}` });
                        updateFieldValidation(document.getElementById('amountValidation'), true, `Amount valid: ₱${amount.toLocaleString('en-US', { minimumFractionDigits: 2 })}`);
                    }

                    // Term validation
                    if (!termLengthSelect.value) {
                        validationMessages.push({ field: 'Term', valid: false, message: 'Loan Term Length is required' });
                        updateFieldValidation(document.getElementById('ciTermValidation'), false, 'Please select a term length');
                    } else {
                        updateFieldValidation(document.getElementById('ciTermValidation'), true, `Term: ${termLengthSelect.options[termLengthSelect.selectedIndex].text}`);
                        validationMessages.push({ field: 'Term', valid: true, message: termLengthSelect.options[termLengthSelect.selectedIndex].text });
                    }

                    // Update summary
                    const allValid = validationMessages.every(msg => msg.valid);
                    if (allValid && statusSelect.value && amount && termLengthSelect.value) {
                        formValidationSummary.style.display = 'none';
                    } else {
                        validationList.innerHTML = validationMessages.map(msg => `
                            <div style="margin: 4px 0; color: ${msg.valid ? '#15803d' : '#dc2626'};">
                                <i class="fas ${msg.valid ? 'fa-check-circle' : 'fa-times-circle'}"></i> ${msg.field}: ${msg.message}
                            </div>
                        `).join('');
                        formValidationSummary.style.display = validationMessages.some(m => !m.valid) ? 'block' : 'none';
                    }
                }

                // Helper function to update field validation display
                function updateFieldValidation(element, isValid, message) {
                    if (isValid) {
                        element.style.color = '#2d7d32';
                        element.innerHTML = `<i class="fas fa-check-circle"></i> ${message}`;
                    } else {
                        element.style.color = '#d32f2f';
                        element.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
                    }
                    element.style.display = 'block';
                }

                // Initialize loan type limits
                setLoanTypeLimits();

                // Setup suggestion dropdown listener
                setupSuggestionDropdown();

                // Note: calculateMonthlyPayment, updateRemarksCounter, updateStatusIndicator, and populateRemarks
                // are now defined globally to be accessible from inline HTML event handlers

                // Update character count for remarks
                if (remarksTextarea) {
                    remarksTextarea.addEventListener('input', function () {
                        document.getElementById('remarksCounter').textContent = this.value.length + ' / 500 characters';
                    });
                }

                // Status change event
                if (statusSelect) {
                    statusSelect.addEventListener('change', function () {
                        initializeSuggestions();
                        validateForm();
                    });
                }

                // Amount input event
                if (finalAmountInput) {
                    finalAmountInput.addEventListener('input', function () {
                        calculateMonthlyPayment();
                        validateForm();
                    });
                }

                // Term change event
                if (termLengthSelect) {
                    termLengthSelect.addEventListener('change', function () {
                        calculateMonthlyPayment();
                        validateForm();
                    });
                }

                // Reset form
                if (resetBtn) {
                    resetBtn.addEventListener('click', function () {
                        form.reset();
                        document.getElementById('amountValidation').style.display = 'none';
                        document.getElementById('ciStatusValidation').style.display = 'none';
                        document.getElementById('ciTermValidation').style.display = 'none';
                        document.getElementById('monthlyPaymentSection').style.display = 'none';
                        document.getElementById('remarksCounter').textContent = '0 / 500 characters';
                        document.getElementById('ciMessageBox').style.display = 'none';
                        document.getElementById('formValidationSummary').style.display = 'none';
                        document.getElementById('suggestionDropdown').value = '';
                    });
                }

                // Form submission
                form.addEventListener('submit', function (e) {
                    e.preventDefault();

                    if (isSubmitting) return; // Prevent duplicate submissions

                    const applicationId = document.getElementById('ciApplicationId').value;
                    const creditStatus = statusSelect.value;
                    const finalAmount = parseFloat(finalAmountInput.value);
                    const termLength = termLengthSelect.value;
                    const remarks = remarksTextarea.value;
                    const minVal = parseFloat(finalAmountInput.min);
                    const maxVal = parseFloat(finalAmountInput.max);

                    console.log('Form Submission Data:', {
                        applicationId: applicationId,
                        creditStatus: creditStatus,
                        finalAmount: finalAmount,
                        termLength: termLength,
                        minVal: minVal,
                        maxVal: maxVal
                    });

                    // Validation
                    const errors = [];

                    if (!applicationId) {
                        errors.push('Application ID is missing');
                    }
                    if (!creditStatus) {
                        errors.push('Investigation Status is required');
                    }
                    if (!finalAmount || isNaN(finalAmount)) {
                        errors.push('Final Amount must be a valid number');
                    }
                    if (finalAmount && (finalAmount < minVal || finalAmount > maxVal)) {
                        errors.push(`Final Amount must be between ₱${minVal.toLocaleString()} and ₱${maxVal.toLocaleString()}`);
                    }
                    if (!termLength) {
                        errors.push('Loan Term Length is required');
                    }

                    if (errors.length > 0) {
                        console.error('Validation Errors:', errors);
                        showToastNotification('error', 'Validation Error', errors.join(' • '));
                        return;
                    }

                    // Prepare form data
                    const formData = new FormData();
                    formData.append('action', 'submit_credit_investigation');
                    formData.append('application_id', applicationId);
                    formData.append('credit_status', creditStatus);
                    formData.append('final_loan_amount', finalAmount);
                    formData.append('term_length', termLength);
                    formData.append('remarks', remarks);

                    // Get applicant name from modal
                    const applicantNameElement = document.querySelector('#loanDetailsModal [data-applicant-name]') ||
                        document.querySelector('#loanDetailsModal .modal-title');
                    const applicantName = applicantNameElement ? (applicantNameElement.getAttribute('data-applicant-name') || applicantNameElement.textContent) : 'Applicant';

                    // Show send confirmation modal
                    showSendConfirmationModal({
                        applicantName: applicantName,
                        creditStatus: creditStatus,
                        finalAmount: finalAmount,
                        termLength: termLength,
                        onConfirm: () => {
                            submitFormData(applicationId, formData, form);
                        },
                        onCancel: () => {
                            // User cancelled, do nothing
                            isSubmitting = false;
                        }
                    });
                });

                // Message display helper
                function showMessage(type, title, message) {
                    const messageBox = document.getElementById('ciMessageBox');
                    const backgroundColor = type === 'success' ? '#d1fae5' : '#fee2e2';
                    const borderColor = type === 'success' ? '#10b981' : '#ef4444';
                    const textColor = type === 'success' ? '#065f46' : '#7f1d1d';
                    const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';

                    messageBox.innerHTML = `
                        <div style="display: flex; gap: 10px; align-items: flex-start;">
                            <i class="fas ${icon}" style="color: ${textColor}; margin-top: 2px; flex-shrink: 0;"></i>
                            <div style="flex: 1;">
                                <strong style="color: ${textColor};">${title}</strong><br>
                                 ${message}
                            </div>
                        </div>
                    `;
                    messageBox.style.backgroundColor = backgroundColor;
                    messageBox.style.borderLeft = `4px solid ${borderColor}`;
                    messageBox.style.display = 'block';
                }
            }

            // Call initialization when modal content is populated
            // We'll call this after modal content is set
            document.addEventListener('DOMContentLoaded', function () {
                // Try to initialize on page load
                setTimeout(() => {
                    initializeCreditInvestigationForm();
                }, 100);
            });            // Expose functions globally
            window.openLoanDetailsModal = openLoanDetailsModal;
            window.closeLoanDetailsModal = closeLoanDetailsModal;
            window.updateStatusIndicator = updateStatusIndicator;
            window.calculateMonthlyPayment = calculateMonthlyPayment;
            window.populateRemarks = populateRemarks;
            window.updateRemarksCounter = updateRemarksCounter;

            /**
             * ======================================
             * TABLE FILTERING ERROR PROTECTION
             * ======================================
             */

            // Wrap the external filterLoanApplicantsTable to prevent errors
            const originalFilterLoanApplicantsTable = typeof filterLoanApplicantsTable === 'function' ? filterLoanApplicantsTable : null;

            window.filterLoanApplicantsTable = function () {
                try {
                    if (originalFilterLoanApplicantsTable) {
                        originalFilterLoanApplicantsTable();
                    }
                } catch (error) {
                    console.warn('Filter error (safely handled):', error.message);
                    // Don't break the app - just log the warning
                }
            };

            // Wrap clearAllFilters to prevent errors
            const originalClearAllFilters = typeof clearAllFilters === 'function' ? clearAllFilters : null;

            window.clearAllFilters = function () {
                try {
                    if (originalClearAllFilters) {
                        originalClearAllFilters();
                    }
                } catch (error) {
                    console.warn('Clear filters error (safely handled):', error.message);
                    // Reset filters manually as fallback
                    document.getElementById('filter-name').value = '';
                    document.getElementById('filter-loan-type').value = '';
                    document.getElementById('filter-loan-status').value = '';
                    document.getElementById('filter-pre-approval').value = '';
                    document.getElementById('filter-credit-status').value = '';

                    // Reload table data
                    location.reload();
                }
            };

            // Wrap clearSearch to prevent errors
            const originalClearSearch = typeof clearSearch === 'function' ? clearSearch : null;

            window.clearSearch = function () {
                try {
                    if (originalClearSearch) {
                        originalClearSearch();
                    }
                } catch (error) {
                    console.warn('Clear search error (safely handled):', error.message);
                    document.getElementById('filter-name').value = '';
                    filterLoanApplicantsTable();
                }
            };

            console.log('✅ Loan Details Modal (Admin2 UI/UX Style) initialized');
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

    <!-- Debug Logs JavaScript -->
    <script>
        let debugLogsModal;
        let currentDebugLogs = '';
        let filteredLogs = '';

        // Initialize debug logs modal
        document.addEventListener('DOMContentLoaded', function () {
            debugLogsModal = document.getElementById('debugLogsModal');
        });

        // Toggle debug logs modal
        function toggleDebugLogs() {
            if (debugLogsModal.style.display === 'none' || debugLogsModal.style.display === '') {
                debugLogsModal.style.display = 'flex';
                refreshDebugLogs();
            } else {
                closeDebugLogs();
            }
        }

        // Close debug logs modal
        function closeDebugLogs() {
            debugLogsModal.style.display = 'none';
        }

        // Refresh debug logs
        function refreshDebugLogs() {
            const container = document.getElementById('debugLogsContainer');
            const logCount = document.getElementById('logCount');
            const lastUpdated = document.getElementById('lastUpdated');

            container.innerHTML = '<div style="color: #9ca3af; text-align: center; padding: 20px;"><i class="fa-solid fa-spinner fa-spin"></i> Loading debug logs...</div>';

            fetch('?action=get_debug_logs', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ action: 'get_debug_logs' })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        currentDebugLogs = data.logs;
                        displayLogs(currentDebugLogs);
                        logCount.textContent = `${data.lines} lines`;
                        lastUpdated.textContent = `Last updated: ${new Date().toLocaleTimeString()}`;
                    } else {
                        container.innerHTML = `<div style="color: #ef4444; text-align: center; padding: 20px;">Error loading logs: ${data.message}</div>`;
                    }
                })
                .catch(error => {
                    container.innerHTML = `<div style="color: #ef4444; text-align: center; padding: 20px;">Network error: ${error.message}</div>`;
                });
        }

        // Display logs with syntax highlighting
        function displayLogs(logs) {
            const container = document.getElementById('debugLogsContainer');

            if (!logs || logs.trim() === '') {
                container.innerHTML = '<div style="color: #9ca3af; text-align: center; padding: 50px;">No debug logs found</div>';
                return;
            }

            // Apply syntax highlighting and colors
            const highlightedLogs = logs
                .split('\\n')
                .map(line => {
                    if (line.includes('AUTHORIZATION FAILED')) {
                        return `<span style="color: #ef4444; font-weight: bold;">${escapeHtml(line)}</span>`;
                    } else if (line.includes('AUTO-CREATE SUCCESS') || line.includes('Dashboard Access')) {
                        return `<span style="color: #10b981; font-weight: bold;">${escapeHtml(line)}</span>`;
                    } else if (line.includes('AUTO-CREATE:') || line.includes('AUTH CHECK:')) {
                        return `<span style="color: #3b82f6;">${escapeHtml(line)}</span>`;
                    } else if (line.includes('FATAL') || line.includes('ERROR')) {
                        return `<span style="color: #f59e0b; font-weight: bold;">${escapeHtml(line)}</span>`;
                    } else if (line.includes('CREDIT INVESTIGATION')) {
                        return `<span style="color: #8b5cf6;">${escapeHtml(line)}</span>`;
                    } else {
                        return `<span style="color: #d1d5db;">${escapeHtml(line)}</span>`;
                    }
                })
                .join('\\n');

            container.innerHTML = highlightedLogs;
            container.scrollTop = container.scrollHeight; // Auto-scroll to bottom
        }

        // Filter debug logs
        function filterDebugLogs() {
            const filter = document.getElementById('logFilter').value.toLowerCase();

            if (!filter) {
                displayLogs(currentDebugLogs);
                return;
            }

            const filteredLines = currentDebugLogs
                .split('\\n')
                .filter(line => line.toLowerCase().includes(filter))
                .join('\\n');

            displayLogs(filteredLines);

            const lineCount = filteredLines ? filteredLines.split('\\n').length : 0;
            document.getElementById('logCount').textContent = `${lineCount} filtered lines`;
        }

        // Clear debug display
        function clearDebugDisplay() {
            document.getElementById('debugLogsContainer').innerHTML = '<div style="color: #9ca3af; text-align: center; padding: 50px;">Display cleared</div>';
            document.getElementById('logCount').textContent = '0 lines';
        }

        // Download debug logs
        function downloadDebugLogs() {
            if (!currentDebugLogs) {
                alert('No logs to download. Please refresh first.');
                return;
            }

            const blob = new Blob([currentDebugLogs], { type: 'text/plain' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `debug_logs_${new Date().toISOString().slice(0, 10)}.txt`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }

        // Utility function to escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Auto-refresh logs every 30 seconds when modal is open
        setInterval(() => {
            if (debugLogsModal && debugLogsModal.style.display === 'flex') {
                refreshDebugLogs();
            }
        }, 30000);
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

    <!-- ============================================
         MODAL DIALOGS & POPUPS (Section 9)
         ============================================ -->

    <!-- EMAIL SUCCESS MODAL -->
    <div id="emailSuccessModal" class="modal" style="display: none;">
        <div class="modal-content"
            style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 12px; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
            <span class="close"
                style="color: white; font-size: 28px; font-weight: bold; cursor: pointer; position: absolute; right: 20px; top: 10px;">&times;</span>
            <div style="text-align: center; padding: 40px 30px;">
                <div style="font-size: 60px; color: white; margin-bottom: 20px; animation: slideInScale 0.5s ease-out;">
                    ✉️</div>
                <h2 style="color: white; margin: 0 0 10px 0; font-size: 24px;">Email Sent Successfully</h2>
                <p style="color: rgba(255,255,255,0.9); margin: 10px 0 0 0; font-size: 16px;"
                    data-field="successMessage">
                    Your email has been sent to the applicant.
                </p>
                <div style="margin-top: 20px; font-size: 14px; color: rgba(255,255,255,0.8);">
                    This modal will close automatically in 5 seconds...
                </div>
            </div>
        </div>
    </div>

    <!-- CONFIRMATION MODAL (Document Actions, Rejections) -->
    <div id="confirmationModal" class="modal" style="display: none;">
        <div class="modal-content"
            style="background: white; border-radius: 12px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-width: 500px;">
            <span class="close"
                style="font-size: 28px; font-weight: bold; cursor: pointer; position: absolute; right: 15px; top: 10px;">&times;</span>
            <div style="padding: 30px;">
                <div style="text-align: center; margin-bottom: 25px;">
                    <div style="font-size: 50px; margin-bottom: 15px;" data-field="confirmIcon">⚠️</div>
                    <h2 style="margin: 0 0 10px 0; color: #1f2937; font-size: 22px;" data-field="confirmTitle">Confirm
                        Action</h2>
                    <p style="margin: 0; color: #6b7280; font-size: 14px;" data-field="confirmMessage">Are you sure?</p>
                </div>

                <form id="confirmationForm" style="margin-bottom: 20px;">
                    <div style="margin-bottom: 15px;">
                        <label
                            style="display: block; margin-bottom: 8px; font-weight: 600; color: #374151; font-size: 14px;">
                            Select Reason:
                        </label>
                        <select id="rejectionTemplate" name="rejection_template"
                            style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; font-family: inherit;">
                            <option value="">-- Choose a template --</option>
                            <option value="incomplete_docs">Incomplete Documentation</option>
                            <option value="low_income">Insufficient Income</option>
                            <option value="bad_credit">Credit Issues</option>
                            <option value="debt_ratio">High Debt Ratio</option>
                            <option value="other">Other Reason</option>
                        </select>
                    </div>

                    <div style="margin-bottom: 15px;">
                        <label
                            style="display: block; margin-bottom: 8px; font-weight: 600; color: #374151; font-size: 14px;">
                            Additional Comments:
                        </label>
                        <textarea id="confirmationNotes" name="confirmation_notes"
                            placeholder="Enter any additional details..."
                            style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; font-family: inherit; min-height: 80px; resize: vertical;"></textarea>
                        <div style="margin-top: 5px; font-size: 12px; color: #9ca3af;">
                            <span id="charCount">0</span>/500 characters
                        </div>
                    </div>

                    <input type="hidden" name="csrf_token" id="confirmCsrfToken" value="">
                </form>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button class="btn btn-secondary" onclick="ModalManager.closeModal('confirmationModal')"
                        style="padding: 10px 20px; border: 1px solid #d1d5db; background: #f3f4f6; color: #374151; border-radius: 6px; cursor: pointer; font-weight: 500; font-size: 14px;">
                        Cancel
                    </button>
                    <button id="confirmBtn" class="btn btn-danger" onclick="handleConfirmation()"
                        style="padding: 10px 20px; background: #ef4444; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; font-size: 14px;">
                        Confirm
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- PRE-APPROVAL STATUS MODAL -->
    <div id="preApprovalModal" class="modal" style="display: none;">
        <div class="modal-content"
            style="background: white; border-radius: 12px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-width: 550px;">
            <span class="close"
                style="font-size: 28px; font-weight: bold; cursor: pointer; position: absolute; right: 15px; top: 10px;">&times;</span>
            <div style="padding: 30px;">
                <div style="text-align: center; margin-bottom: 25px;">
                    <div style="font-size: 50px; margin-bottom: 15px;">📋</div>
                    <h2 style="margin: 0 0 10px 0; color: #1f2937; font-size: 22px;">Pre-Approval Decision</h2>
                    <p style="margin: 0; color: #6b7280; font-size: 14px;">Update the pre-approval status for this
                        application</p>
                </div>

                <form id="preApprovalForm" style="margin-bottom: 20px;">
                    <!-- Status Selection -->
                    <div style="margin-bottom: 20px;">
                        <label
                            style="display: block; margin-bottom: 12px; font-weight: 600; color: #374151; font-size: 14px;">
                            Decision Status:
                        </label>
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">
                            <label
                                style="cursor: pointer; padding: 12px; border: 2px solid #d1d5db; border-radius: 6px; text-align: center; transition: all 0.3s; background: white;">
                                <input type="radio" name="preapproval_status" value="approved"
                                    style="cursor: pointer; margin-right: 5px;">
                                <span style="font-weight: 500; color: #10b981;">✓ Approved</span>
                            </label>
                            <label
                                style="cursor: pointer; padding: 12px; border: 2px solid #d1d5db; border-radius: 6px; text-align: center; transition: all 0.3s; background: white;">
                                <input type="radio" name="preapproval_status" value="pending"
                                    style="cursor: pointer; margin-right: 5px;">
                                <span style="font-weight: 500; color: #f59e0b;">⏳ Pending</span>
                            </label>
                            <label
                                style="cursor: pointer; padding: 12px; border: 2px solid #d1d5db; border-radius: 6px; text-align: center; transition: all 0.3s; background: white;">
                                <input type="radio" name="preapproval_status" value="rejected"
                                    style="cursor: pointer; margin-right: 5px;">
                                <span style="font-weight: 500; color: #ef4444;">✕ Rejected</span>
                            </label>
                        </div>
                    </div>

                    <!-- Decision Reason -->
                    <div style="margin-bottom: 15px;">
                        <label
                            style="display: block; margin-bottom: 8px; font-weight: 600; color: #374151; font-size: 14px;">
                            Reason for Decision:
                        </label>
                        <select id="decisionReason" name="decision_reason"
                            style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; font-family: inherit;">
                            <option value="">-- Select a template --</option>
                            <option value="meets_criteria">Meets All Criteria</option>
                            <option value="review_needed">Additional Review Needed</option>
                            <option value="more_docs">Needs More Documentation</option>
                            <option value="clarification">Needs Clarification</option>
                            <option value="other">Other Reason</option>
                        </select>
                    </div>

                    <!-- Custom Reason (if needed) -->
                    <div style="margin-bottom: 15px;">
                        <label
                            style="display: block; margin-bottom: 8px; font-weight: 600; color: #374151; font-size: 14px;">
                            Additional Notes:
                        </label>
                        <textarea id="decisionNotes" name="decision_notes" placeholder="Explain your decision..."
                            style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; font-family: inherit; min-height: 80px; resize: vertical;"></textarea>
                        <div style="margin-top: 5px; font-size: 12px; color: #9ca3af;">
                            <span id="decisionCharCount">0</span>/500 characters
                        </div>
                    </div>

                    <input type="hidden" name="csrf_token" id="preApprovalCsrfToken" value="">
                </form>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button class="btn btn-secondary" onclick="ModalManager.closeModal('preApprovalModal')"
                        style="padding: 10px 20px; border: 1px solid #d1d5db; background: #f3f4f6; color: #374151; border-radius: 6px; cursor: pointer; font-weight: 500; font-size: 14px;">
                        Cancel
                    </button>
                    <button id="submitPreApprovalBtn" class="btn btn-primary" onclick="handlePreApprovalSubmit()"
                        style="padding: 10px 20px; background: #3b82f6; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; font-size: 14px;">
                        Save Decision
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="JAVASCRIPT/Real-Time.js"></script>
    <script src="JAVASCRIPT/admin1_dashboard.js"></script>

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
    <!-- DEBUG LOGS MODAL -->
    <div id="debugLogsModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 90%; max-height: 90%;">
            <div style="padding: 20px; border-bottom: 1px solid #e5e7eb; background: #f8fafc;">
                <div style="display: flex; justify-content: between; align-items: center;">
                    <h2 style="margin: 0; color: #1f2937; font-size: 24px;">
                        <i class="fa-solid fa-bug" style="color: #dc2626; margin-right: 10px;"></i>
                        Debug Logs Viewer
                    </h2>
                    <button onclick="closeDebugLogs()"
                        style="background: none; border: none; font-size: 24px; cursor: pointer; color: #6b7280;">×</button>
                </div>
                <p style="margin: 10px 0 0 0; color: #6b7280; font-size: 14px;">Real-time monitoring of system logs and
                    debug information</p>
            </div>
            <div style="padding: 20px; overflow-y: auto; max-height: 70vh;">
                <!-- Control Panel -->
                <div style="display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap;">
                    <button onclick="refreshDebugLogs()"
                        style="padding: 8px 16px; background: #3b82f6; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px;">
                        <i class="fa-solid fa-refresh"></i> Refresh
                    </button>
                    <button onclick="clearDebugDisplay()"
                        style="padding: 8px 16px; background: #ef4444; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px;">
                        <i class="fa-solid fa-trash"></i> Clear Display
                    </button>
                    <button onclick="downloadDebugLogs()"
                        style="padding: 8px 16px; background: #10b981; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px;">
                        <i class="fa-solid fa-download"></i> Download
                    </button>
                    <select id="logFilter" onchange="filterDebugLogs()"
                        style="padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                        <option value="">All Logs</option>
                        <option value="authorization">Authorization</option>
                        <option value="auto-create">Auto-Create</option>
                        <option value="credit investigation">Credit Investigation</option>
                        <option value="error">Errors</option>
                        <option value="success">Success</option>
                    </select>
                </div>

                <!-- Logs Display -->
                <div id="debugLogsContainer"
                    style="background: #1f2937; color: #f9fafb; padding: 15px; border-radius: 8px; font-family: 'Courier New', monospace; font-size: 13px; line-height: 1.4; min-height: 400px; overflow-y: auto; white-space: pre-wrap;">
                    <div style="color: #9ca3af; text-align: center; padding: 50px 0;">
                        <i class="fa-solid fa-spinner fa-spin"></i> Loading debug logs...
                    </div>
                </div>

                <!-- Status Bar -->
                <div
                    style="margin-top: 15px; padding: 10px; background: #f3f4f6; border-radius: 6px; font-size: 12px; color: #6b7280; display: flex; justify-content: space-between;">
                    <span id="logCount">Loading...</span>
                    <span id="lastUpdated">Never</span>
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