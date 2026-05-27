<?php
/**
 * CYCLOAN Security - Input Validation Helper Functions
 * 
 * This file provides reusable, tested security functions for input validation
 * and data sanitization across the entire CYCLOAN application.
 * 
 * Usage: require_once 'security_validation.php';
 * 
 * @version 1.0
 * @date November 4, 2025
 */

// ========== STRING VALIDATION ==========

/**
 * Validate and sanitize string input
 * 
 * @param mixed $input The input to validate
 * @param int $min_length Minimum string length
 * @param int $max_length Maximum string length
 * @return string|false Sanitized string or false if invalid
 */
function validateString($input, $min_length = 1, $max_length = 255)
{
    if (!isset($input)) {
        return false;
    }

    $value = trim((string) $input);
    $length = strlen($value);

    if ($length < $min_length || $length > $max_length) {
        return false;
    }

    return $value;
}

// ========== INTEGER VALIDATION ==========

/**
 * Validate and convert to integer
 * 
 * @param mixed $input The input to validate
 * @param int|null $min Minimum value (inclusive)
 * @param int|null $max Maximum value (inclusive)
 * @return int|false Valid integer or false if invalid
 */
function validateInteger($input, $min = null, $max = null)
{
    if (!isset($input) || $input === '') {
        return false;
    }

    $value = intval($input);

    // Check if it's actually numeric
    if (!is_numeric($input) || $value != $input) {
        return false;
    }

    if ($min !== null && $value < $min) {
        return false;
    }

    if ($max !== null && $value > $max) {
        return false;
    }

    return $value;
}

// ========== FLOAT/DECIMAL VALIDATION ==========

/**
 * Validate and convert to float with decimal places
 * 
 * @param mixed $input The input to validate
 * @param int $decimal_places Number of decimal places to allow
 * @param float|null $min Minimum value
 * @param float|null $max Maximum value
 * @return float|false Valid float or false if invalid
 */
function validateDecimal($input, $decimal_places = 2, $min = null, $max = null)
{
    if (!isset($input) || $input === '') {
        return false;
    }

    // Normalize common formatted numbers: remove thousand separators, currency symbols and whitespace
    // Accept inputs like "1,000", "₱1,000.00", "$1 000", etc.
    $raw = (string) $input;

    // Remove common currency symbols and non-numeric characters except minus and dot
    $clean = preg_replace('/[^0-9\.\-]/', '', $raw);

    if ($clean === '' || !is_numeric($clean)) {
        return false;
    }

    $value = floatval($clean);

    // Round to specified decimal places
    $value = round($value, $decimal_places);

    if ($min !== null && $value < $min) {
        return false;
    }

    if ($max !== null && $value > $max) {
        return false;
    }

    return $value;
}

// ========== EMAIL VALIDATION ==========

/**
 * Validate email address format
 * 
 * @param mixed $input The email to validate
 * @return string|false Valid email or false if invalid
 */
function validateEmail($input)
{
    if (!isset($input)) {
        return false;
    }

    $email = trim((string) $input);

    // Validate format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    // Check length
    if (strlen($email) > 254) {
        return false;
    }

    return strtolower($email);
}

// ========== ENUM/CHOICE VALIDATION ==========

/**
 * Validate input against a list of allowed values (enum)
 * 
 * @param mixed $input The input to validate
 * @param array $allowed_values Array of allowed values
 * @param bool $case_sensitive Whether to do case-sensitive comparison
 * @return mixed The validated value, or false if invalid
 */
function validateEnum($input, $allowed_values, $case_sensitive = true)
{
    if (!isset($input) || !is_array($allowed_values)) {
        return false;
    }

    $value = trim((string) $input);

    if ($case_sensitive) {
        if (!in_array($value, $allowed_values, true)) {
            return false;
        }
    } else {
        $value_lower = strtolower($value);
        $found = false;

        foreach ($allowed_values as $allowed) {
            if (strtolower($allowed) === $value_lower) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            return false;
        }
    }

    return $value;
}

// ========== DATE VALIDATION ==========

/**
 * Validate date format
 * 
 * @param mixed $input The date to validate
 * @param string $format Expected date format (default: Y-m-d)
 * @return string|false Valid date string or false if invalid
 */
function validateDate($input, $format = 'Y-m-d')
{
    if (!isset($input)) {
        return false;
    }

    $date = trim((string) $input);
    $d = \DateTime::createFromFormat($format, $date);

    if (!$d || $d->format($format) !== $date) {
        return false;
    }

    return $date;
}

// ========== PHONE VALIDATION ==========

/**
 * Validate Philippine phone number
 * Accepts formats: 09XXXXXXXXX, +639XXXXXXXXX, 639XXXXXXXXX
 * 
 * @param mixed $input The phone number to validate
 * @return string|false Valid phone number or false if invalid
 */
function validatePhoneNumber($input)
{
    if (!isset($input)) {
        return false;
    }

    $phone = preg_replace('/[^0-9+]/', '', (string) $input);

    // Check if it's a valid PH number
    if (preg_match('/^(\+63|63|09)\d{9}$/', $phone)) {
        // Standardize to 09 format
        if (strpos($phone, '+63') === 0) {
            $phone = '0' . substr($phone, 3);
        } elseif (strpos($phone, '63') === 0) {
            $phone = '0' . substr($phone, 2);
        }

        return $phone;
    }

    return false;
}

// ========== JSON VALIDATION ==========

/**
 * Validate and parse JSON
 * 
 * @param mixed $input The JSON string to validate
 * @return array|false Parsed JSON as array, or false if invalid
 */
function validateJSON($input)
{
    if (!isset($input) || empty($input)) {
        return false;
    }

    $decoded = json_decode((string) $input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return false;
    }

    return $decoded;
}

// ========== FILE UPLOAD VALIDATION ==========

/**
 * Validate file upload
 * 
 * @param array $file $_FILES['field'] array
 * @param array $allowed_types Allowed MIME types
 * @param int $max_size Maximum file size in bytes
 * @return array|false File info or false if invalid
 */
function validateFileUpload($file, $allowed_types = [], $max_size = 5242880)
{
    if (!isset($file) || !is_array($file)) {
        return false;
    }

    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    // Check file size
    if ($file['size'] > $max_size) {
        return false;
    }

    // Check MIME type if restricted
    if (!empty($allowed_types)) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime_type, $allowed_types, true)) {
            return false;
        }
    }

    return [
        'name' => basename($file['name']),
        'size' => $file['size'],
        'tmp_name' => $file['tmp_name'],
        'mime_type' => finfo_file(finfo_open(FILEINFO_MIME_TYPE), $file['tmp_name'])
    ];
}

// ========== ARRAY VALIDATION ==========

/**
 * Validate array structure and content
 * 
 * @param array $input The array to validate
 * @param array $required_keys Array of required keys
 * @param array $validation_rules Rules for each key
 * @return array|false Validated array or false if invalid
 */
function validateArray($input, $required_keys = [], $validation_rules = [])
{
    if (!is_array($input)) {
        return false;
    }

    // Check required keys
    foreach ($required_keys as $key) {
        if (!isset($input[$key]) || $input[$key] === '') {
            return false;
        }
    }

    // Apply validation rules
    $result = [];
    foreach ($input as $key => $value) {
        if (isset($validation_rules[$key])) {
            $validator = $validation_rules[$key];
            $validated = $validator($value);

            if ($validated === false) {
                return false;
            }

            $result[$key] = $validated;
        } else {
            $result[$key] = $value;
        }
    }

    return $result;
}

// ========== SQL INJECTION PREVENTION ==========

/**
 * Validate table name against whitelist
 * NEVER use user input directly for table names
 * 
 * @param string $table_name The table name to validate
 * @param array $allowed_tables Whitelist of allowed tables
 * @return string|false Valid table name or false if not in whitelist
 */
function validateTableName($table_name, $allowed_tables = [])
{
    if (empty($table_name) || empty($allowed_tables)) {
        return false;
    }

    // Check if table exists in whitelist
    if (in_array($table_name, $allowed_tables, true)) {
        return $table_name;
    }

    return false;
}

/**
 * Validate column name against whitelist
 * NEVER use user input directly for column names
 * 
 * @param string $column_name The column name to validate
 * @param array $allowed_columns Whitelist of allowed columns
 * @return string|false Valid column name or false if not in whitelist
 */
function validateColumnName($column_name, $allowed_columns = [])
{
    if (empty($column_name) || empty($allowed_columns)) {
        return false;
    }

    if (in_array($column_name, $allowed_columns, true)) {
        return $column_name;
    }

    return false;
}

// ========== SAFE OUTPUT ESCAPING ==========

/**
 * Escape output for safe HTML display
 * 
 * @param mixed $data The data to escape
 * @param string $flags Additional flags for htmlspecialchars
 * @return string Escaped string safe for HTML
 */
function escapeOutput($data, $flags = ENT_QUOTES)
{
    return htmlspecialchars((string) $data, $flags, 'UTF-8');
}

/**
 * Escape JSON for safe output
 * 
 * @param mixed $data The data to convert to JSON
 * @return string Safe JSON string
 */
function escapeJSON($data)
{
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

// ========== BATCH VALIDATION ==========

/**
 * Validate multiple POST/GET parameters at once
 * 
 * @param array $input The input array (usually $_POST or $_GET)
 * @param array $rules Validation rules: ['field_name' => 'validation_function']
 * @return array|false Validated data or false if any validation fails
 */
function validateBatch($input, $rules = [])
{
    if (!is_array($input) || !is_array($rules)) {
        return false;
    }

    $validated = [];

    foreach ($rules as $field => $validator) {
        if (!isset($input[$field])) {
            return false;
        }

        if (is_callable($validator)) {
            $result = $validator($input[$field]);
        } elseif (function_exists($validator)) {
            $result = $validator($input[$field]);
        } else {
            return false;
        }

        if ($result === false) {
            return false;
        }

        $validated[$field] = $result;
    }

    return $validated;
}

// ========== ERROR LOGGING ==========

/**
 * Log security validation errors
 * 
 * @param string $message Error message
 * @param string $type Type of error (validation, injection_attempt, etc)
 * @return void
 */
function logSecurityError($message, $type = 'validation')
{
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'anonymous';

    $log_message = "[$timestamp] Type: $type | User: $user | IP: $ip | Message: $message\n";

    error_log($log_message, 3, 'security_errors.log');
}

// ========== USAGE EXAMPLES ==========

/*

// Example 1: Simple validation
$email = validateEmail($_POST['email']);
if (!$email) {
    logSecurityError('Invalid email format', 'validation');
    die('Invalid email');
}

// Example 2: Enum validation
$status = validateEnum($_POST['status'], ['pending', 'approved', 'rejected']);
if (!$status) {
    logSecurityError('Invalid status value', 'injection_attempt');
    die('Invalid status');
}

// Example 3: Batch validation
$validated = validateBatch($_POST, [
    'email' => 'validateEmail',
    'amount' => fn($v) => validateDecimal($v, 2, 0, 999999.99),
    'status' => fn($v) => validateEnum($v, ['active', 'inactive']),
]);

if (!$validated) {
    logSecurityError('Batch validation failed', 'validation');
    die('Invalid input');
}

// Example 4: Table name validation
$allowed_tables = ['users1', 'admin1', 'admin2', 'superadmins'];
$table = validateTableName($_POST['table'], $allowed_tables);
if (!$table) {
    logSecurityError('Invalid table name', 'injection_attempt');
    die('Invalid table');
}

// Example 5: Combined with prepared statements
$email = validateEmail($_POST['email']);
$amount = validateDecimal($_POST['amount'], 2, 0, 999999.99);

if (!$email || !$amount) {
    die('Invalid input');
}

$stmt = $conn->prepare("INSERT INTO loans (email, amount) VALUES (?, ?)");
$stmt->bind_param("sd", $email, $amount);
$stmt->execute();

*/

?>