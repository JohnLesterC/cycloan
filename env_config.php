<?php
/**
 * CYCLOAN Environment Configuration File
 * 
 * This file loads environment variables for sensitive credentials.
 * NEVER commit this file with actual credentials to version control.
 * 
 * Usage:
 * 1. Copy this file to .env.local
 * 2. Update with your actual credentials
 * 3. Add .env.local to .gitignore
 * 
 * @version 1.0
 * @date November 10, 2025
 */

// Load environment variables from .env file (if exists)
if (file_exists(__DIR__ . '/.env')) {
    $env_file = __DIR__ . '/.env';
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remove quotes if present
            if (
                (strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) ||
                (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)
            ) {
                $value = substr($value, 1, -1);
            }

            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

/**
 * Get environment variable with fallback to $_ENV
 * 
 * @param string $key Environment variable name
 * @param mixed $default Default value if not found
 * @return mixed Environment variable value or default
 */
if (!function_exists('getEnvVar')) {
    function getEnvVar(string $key, mixed $default = null)
    {
        $value = getenv($key);
        if ($value === false) {
            $value = $_ENV[$key] ?? $default;
        }
        return $value;
    }
}

// ========== EMAIL CONFIGURATION ==========
define('MAIL_HOST', getEnvVar('MAIL_HOST', 'smtp.gmail.com'));
define('MAIL_USERNAME', getEnvVar('MAIL_USERNAME', 'scycloan@gmail.com'));
define('MAIL_PASSWORD', getEnvVar('MAIL_PASSWORD', 'xbvo zplr dpme ixxj'));
define('MAIL_FROM_NAME', getEnvVar('MAIL_FROM_NAME', 'CYCLOAN Support'));
define('MAIL_PORT', getEnvVar('MAIL_PORT', 587));
define('MAIL_ENCRYPTION', getEnvVar('MAIL_ENCRYPTION', 'tls'));

// ========== SECURITY CONFIGURATION ==========

// OTP Settings
define('OTP_EXPIRATION_MINUTES', getEnvVar('OTP_EXPIRATION_MINUTES', 10));
define('OTP_MAX_ATTEMPTS', getEnvVar('OTP_MAX_ATTEMPTS', 5));
define('OTP_LENGTH', getEnvVar('OTP_LENGTH', 6));

// Rate Limiting Settings
define('RATE_LIMIT_ENABLED', getEnvVar('RATE_LIMIT_ENABLED', 'true') === 'true');
define('RATE_LIMIT_REGISTRATION_PER_IP', getEnvVar('RATE_LIMIT_REGISTRATION_PER_IP', 5));    // Max 5 registrations per IP
define('RATE_LIMIT_REGISTRATION_WINDOW', getEnvVar('RATE_LIMIT_REGISTRATION_WINDOW', 3600));  // Per 1 hour
define('RATE_LIMIT_OTP_PER_EMAIL', getEnvVar('RATE_LIMIT_OTP_PER_EMAIL', 3));                // Max 3 OTP requests per email
define('RATE_LIMIT_OTP_WINDOW', getEnvVar('RATE_LIMIT_OTP_WINDOW', 3600));                   // Per 1 hour
define('RATE_LIMIT_VERIFICATION_PER_EMAIL', getEnvVar('RATE_LIMIT_VERIFICATION_PER_EMAIL', 5)); // Max 5 verification attempts
define('RATE_LIMIT_VERIFICATION_WINDOW', getEnvVar('RATE_LIMIT_VERIFICATION_WINDOW', 300));  // Per 5 minutes

// Two-Factor Authentication Settings
define('TWO_FACTOR_ENABLED', getEnvVar('TWO_FACTOR_ENABLED', 'true') === 'true');
define('TWO_FACTOR_METHOD', getEnvVar('TWO_FACTOR_METHOD', 'email')); // 'email', 'sms', or 'totp'
define('TWO_FACTOR_GRACE_PERIOD', getEnvVar('TWO_FACTOR_GRACE_PERIOD', 300)); // 5 minutes

// ========== DATABASE CONFIGURATION ==========
define('DB_HOST', getEnvVar('DB_HOST', 'localhost'));
define('DB_USER', getEnvVar('DB_USER', 'root'));
define('DB_PASS', getEnvVar('DB_PASS', ''));
define('DB_NAME', getEnvVar('DB_NAME', 'cycloan_db'));
define('DB_PORT', getEnvVar('DB_PORT', 3306));

// ========== APPLICATION CONFIGURATION ==========
define('APP_NAME', getEnvVar('APP_NAME', 'CYCLOAN'));
define('APP_URL', getEnvVar('APP_URL', 'http://localhost/CYCLOAN'));
define('APP_ENV', getEnvVar('APP_ENV', 'local'));
define('APP_DEBUG', getEnvVar('APP_DEBUG', 'true') === 'true');

// ========== SESSION CONFIGURATION ==========
define('SESSION_LIFETIME', getEnvVar('SESSION_LIFETIME', 1800)); // 30 minutes
define('SESSION_SECURE', getEnvVar('SESSION_SECURE', APP_ENV === 'production'));
define('SESSION_HTTP_ONLY', getEnvVar('SESSION_HTTP_ONLY', 'true') === 'true');

// ========== REDIS CONFIGURATION (For Rate Limiting & Session Storage) ==========
define('REDIS_ENABLED', getEnvVar('REDIS_ENABLED', 'false') === 'true');
define('REDIS_HOST', getEnvVar('REDIS_HOST', 'localhost'));
define('REDIS_PORT', getEnvVar('REDIS_PORT', 6379));
define('REDIS_PASSWORD', getEnvVar('REDIS_PASSWORD', ''));
define('REDIS_DB', getEnvVar('REDIS_DB', 0));

?>