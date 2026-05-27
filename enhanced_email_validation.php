<?php
/**
 * Enhanced Email Validation API
 * Provides real-time email validation with advanced checks
 */

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Include database connection
require_once 'CYCLOAN_db.php';

// SECURITY: Check for valid request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// SECURITY: Rate limiting
session_start();
$rate_limit_key = 'email_validation_' . $_SERVER['REMOTE_ADDR'];
$current_time = time();
$rate_limit_window = 60; // 1 minute
$max_requests = 20; // Maximum requests per window

if (!isset($_SESSION[$rate_limit_key])) {
    $_SESSION[$rate_limit_key] = [];
}

// Clean old requests
$cleaned_requests = [];
foreach ($_SESSION[$rate_limit_key] as $timestamp) {
    if (($current_time - $timestamp) < $rate_limit_window) {
        $cleaned_requests[] = $timestamp;
    }
}
$_SESSION[$rate_limit_key] = $cleaned_requests;

if (count($_SESSION[$rate_limit_key]) >= $max_requests) {
    http_response_code(429);
    echo json_encode([
        'error' => 'Rate limit exceeded',
        'message' => 'Too many requests. Please wait before trying again.'
    ]);
    exit;
}

$_SESSION[$rate_limit_key][] = $current_time;

// Get and validate input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['email']) || empty($input['email'])) {
    echo json_encode(['error' => 'Email is required']);
    exit;
}

$email = filter_var(trim($input['email']), FILTER_SANITIZE_EMAIL);

if (!$email) {
    echo json_encode([
        'valid' => false,
        'error' => 'Invalid email format',
        'checks' => ['format' => false]
    ]);
    exit;
}

// Validation checks
$checks = [
    'format' => false,
    'domain' => false,
    'disposable' => true, // true means NOT disposable
    'available' => false,
    'mx_record' => false
];

$suggestions = [];
$warnings = [];

// 1. Basic format validation
if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $checks['format'] = true;
}

// 2. Domain validation
$domain = substr(strrchr($email, "@"), 1);
if ($domain) {
    // Check for common domain typos
    $common_domains = [
        'gmail.co' => 'gmail.com',
        'gmail.cm' => 'gmail.com',
        'gmai.com' => 'gmail.com',
        'yahoo.co' => 'yahoo.com',
        'hotmail.co' => 'hotmail.com',
        'outlook.co' => 'outlook.com'
    ];

    if (isset($common_domains[$domain])) {
        $suggested_email = str_replace($domain, $common_domains[$domain], $email);
        $suggestions[] = [
            'type' => 'domain_typo',
            'suggestion' => $suggested_email,
            'message' => "Did you mean {$suggested_email}?"
        ];
    }

    // Check if domain has MX record
    if (function_exists('checkdnsrr')) {
        $checks['mx_record'] = checkdnsrr($domain, 'MX');
        if (!$checks['mx_record']) {
            $warnings[] = 'Domain may not accept emails';
        }
    }

    $checks['domain'] = strlen($domain) > 3 && strpos($domain, '.') !== false;
}

// 3. Check for disposable email domains
$disposable_domains = [
    '10minutemail.com',
    'tempmail.org',
    'guerrillamail.com',
    'mailinator.com',
    'yopmail.com',
    'throwaway.email',
    'temp-mail.org',
    'getnada.com'
];

$checks['disposable'] = !in_array($domain, $disposable_domains);
if (!$checks['disposable']) {
    $warnings[] = 'Disposable email addresses are not recommended';
}

// 4. Check availability in database
if ($checks['format'] && $conn) {
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users1 WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        $checks['available'] = ($row['count'] == 0);

        if (!$checks['available']) {
            $warnings[] = 'This email is already registered';
        }

        $stmt->close();
    } catch (Exception $e) {
        error_log("Email validation database error: " . $e->getMessage());
        // Don't fail validation due to database issues
        $checks['available'] = true;
    }
}

// 5. Overall validation result
$is_valid = $checks['format'] && $checks['domain'] && $checks['available'];

// 6. Generate response
$response = [
    'valid' => $is_valid,
    'email' => $email,
    'checks' => $checks,
    'warnings' => $warnings,
    'suggestions' => $suggestions,
    'timestamp' => date('Y-m-d H:i:s')
];

// Add detailed messages
if (!$checks['format']) {
    $response['message'] = 'Please enter a valid email address';
} elseif (!$checks['domain']) {
    $response['message'] = 'Please check the email domain';
} elseif (!$checks['available']) {
    $response['message'] = 'This email is already registered';
} elseif (!empty($warnings)) {
    $response['message'] = implode('. ', $warnings);
} elseif ($is_valid) {
    $response['message'] = 'Email looks good!';
}

// SECURITY: Log validation attempts
if (!$is_valid) {
    error_log("Invalid email validation attempt: {$email} from IP: " . $_SERVER['REMOTE_ADDR']);
}

echo json_encode($response);
?>