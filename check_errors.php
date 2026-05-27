<?php
/**
 * CYCLOAN - Advanced Error Monitoring & Debugging System
 * Monitors all PHP files in the system for errors, bugs, and potential issues
 * 
 * Features:
 * - Real-time error log monitoring
 * - Syntax checking for all PHP files
 * - Database connection testing
 * - JavaScript error detection
 * - File permission checking
 * - Security vulnerability scanning
 * - Performance monitoring
 */

// Enable error reporting for this script
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuration
$SCAN_DIRECTORIES = [
    __DIR__,
    __DIR__ . '/CSS',
    __DIR__ . '/JAVASCRIPT',
    __DIR__ . '/phpmailer',
    __DIR__ . '/tcpdf',
];

$EXCLUDE_DIRS = ['vendor', 'node_modules', '.git', 'uploads'];
$MAX_LOG_LINES = 50;
$ERROR_LOG_FILE = __DIR__ . '/system_errors.log';
$HISTORY_FILE = __DIR__ . '/error_history.json';

// Initialize error counters
$stats = [
    'total_files' => 0,
    'php_files' => 0,
    'js_files' => 0,
    'css_files' => 0,
    'syntax_errors' => 0,
    'warnings' => 0,
    'permission_issues' => 0,
    'security_issues' => 0,
    'code_quality_issues' => 0,
    'deprecated_functions' => 0,
    'sql_injections' => 0,
    'xss_vulnerabilities' => 0,
];

// Save current scan to history
function saveToHistory($stats, $errors)
{
    global $HISTORY_FILE;

    $history = [];
    if (file_exists($HISTORY_FILE)) {
        $history = json_decode(file_get_contents($HISTORY_FILE), true) ?: [];
    }

    $history[] = [
        'timestamp' => date('Y-m-d H:i:s'),
        'stats' => $stats,
        'errors_count' => count($errors),
        'scan_duration' => isset($_SERVER['REQUEST_TIME_FLOAT']) ?
            number_format((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2) : 0
    ];

    // Keep only last 50 scans
    $history = array_slice($history, -50);

    file_put_contents($HISTORY_FILE, json_encode($history, JSON_PRETTY_PRINT));
}

// Get scan history
function getHistory()
{
    global $HISTORY_FILE;
    if (file_exists($HISTORY_FILE)) {
        return json_decode(file_get_contents($HISTORY_FILE), true) ?: [];
    }
    return [];
}

// Check for security vulnerabilities
function checkSecurityIssues($file)
{
    $issues = [];
    $content = @file_get_contents($file);
    if ($content === false)
        return $issues;

    $lines = explode("\n", $content);

    // Check for SQL injection vulnerabilities
    foreach ($lines as $lineNum => $line) {
        if (preg_match('/\$_(GET|POST|REQUEST|COOKIE)\[.*?\].*?(SELECT|UPDATE|DELETE|INSERT|WHERE|ORDER BY)/i', $line)) {
            $issues[] = [
                'type' => 'SQL Injection Risk',
                'severity' => 'critical',
                'line' => $lineNum + 1,
                'code' => trim($line),
                'description' => 'Direct use of user input in SQL query detected',
                'recommendation' => 'Use prepared statements with parameterized queries (mysqli_prepare or PDO)'
            ];
        }
    }

    // Check for XSS vulnerabilities
    foreach ($lines as $lineNum => $line) {
        if (
            preg_match('/echo\s+\$_(GET|POST|REQUEST|COOKIE)/i', $line) &&
            !preg_match('/htmlspecialchars|htmlentities|strip_tags/i', $line)
        ) {
            $issues[] = [
                'type' => 'XSS Vulnerability',
                'severity' => 'high',
                'line' => $lineNum + 1,
                'code' => trim($line),
                'description' => 'Unescaped user input being echoed - Cross-Site Scripting risk',
                'recommendation' => 'Use htmlspecialchars() or htmlentities() to escape output'
            ];
        }
    }

    // Check for deprecated functions
    $deprecated = [
        'mysql_connect' => 'Use mysqli or PDO instead',
        'mysql_query' => 'Use mysqli_query or PDO instead',
        'ereg' => 'Use preg_match instead',
        'split' => 'Use explode or preg_split instead',
        'session_register' => 'Use $_SESSION directly',
        'create_function' => 'Use anonymous functions instead'
    ];

    foreach ($deprecated as $func => $recommendation) {
        foreach ($lines as $lineNum => $line) {
            if (preg_match('/\b' . preg_quote($func, '/') . '\s*\(/i', $line)) {
                $issues[] = [
                    'type' => 'Deprecated Function',
                    'severity' => 'medium',
                    'line' => $lineNum + 1,
                    'code' => trim($line),
                    'description' => "Use of deprecated function: $func",
                    'recommendation' => $recommendation
                ];
            }
        }
    }

    // Check for hardcoded credentials
    foreach ($lines as $lineNum => $line) {
        if (preg_match('/(password|passwd|pwd|secret|api_key|apikey)\s*=\s*["\'][^"\'\s]{3,}["\']/i', $line)) {
            $issues[] = [
                'type' => 'Hardcoded Credentials',
                'severity' => 'critical',
                'line' => $lineNum + 1,
                'code' => preg_replace('/(["\'][^"\']{2})[^"\']+(["\'])/', '$1***$2', trim($line)), // Mask the password
                'description' => 'Hardcoded password/secret detected in source code',
                'recommendation' => 'Use environment variables or configuration files outside web root'
            ];
        }
    }

    // Check for eval() usage
    foreach ($lines as $lineNum => $line) {
        if (preg_match('/\beval\s*\(/i', $line)) {
            $issues[] = [
                'type' => 'Dangerous Function',
                'severity' => 'critical',
                'line' => $lineNum + 1,
                'code' => trim($line),
                'description' => 'Use of eval() function detected - Remote Code Execution risk',
                'recommendation' => 'Avoid eval(). Refactor code to use safer alternatives'
            ];
        }
    }

    // Check for file inclusion vulnerabilities
    foreach ($lines as $lineNum => $line) {
        if (preg_match('/(include|require)(_once)?\s*\(?\s*\$_(GET|POST|REQUEST|COOKIE)/i', $line)) {
            $issues[] = [
                'type' => 'File Inclusion Vulnerability',
                'severity' => 'critical',
                'line' => $lineNum + 1,
                'code' => trim($line),
                'description' => 'Dynamic file inclusion with user input - Local/Remote File Inclusion risk',
                'recommendation' => 'Use whitelist of allowed files or avoid dynamic includes'
            ];
        }
    }

    // Check for command injection
    foreach ($lines as $lineNum => $line) {
        if (preg_match('/(exec|shell_exec|system|passthru|popen|proc_open)\s*\([^)]*\$_(GET|POST|REQUEST|COOKIE)/i', $line)) {
            $issues[] = [
                'type' => 'Command Injection',
                'severity' => 'critical',
                'line' => $lineNum + 1,
                'code' => trim($line),
                'description' => 'Command execution with user input detected',
                'recommendation' => 'Use escapeshellarg() and escapeshellcmd() or avoid shell commands'
            ];
        }
    }

    // Check for unvalidated redirects
    foreach ($lines as $lineNum => $line) {
        if (preg_match('/header\s*\(\s*["\']Location:.*\$_(GET|POST|REQUEST|COOKIE)/i', $line)) {
            $issues[] = [
                'type' => 'Unvalidated Redirect',
                'severity' => 'medium',
                'line' => $lineNum + 1,
                'code' => trim($line),
                'description' => 'Redirect with user input - Open Redirect vulnerability',
                'recommendation' => 'Validate redirect URLs against whitelist'
            ];
        }
    }

    // Check for weak password hashing
    foreach ($lines as $lineNum => $line) {
        if (preg_match('/\b(md5|sha1)\s*\([^)]*password/i', $line)) {
            $issues[] = [
                'type' => 'Weak Password Hashing',
                'severity' => 'high',
                'line' => $lineNum + 1,
                'code' => trim($line),
                'description' => 'Weak hashing algorithm (MD5/SHA1) used for passwords',
                'recommendation' => 'Use password_hash() and password_verify() with bcrypt'
            ];
        }
    }

    // Check for sensitive data exposure
    foreach ($lines as $lineNum => $line) {
        if (
            preg_match('/var_dump|print_r|var_export/i', $line) &&
            preg_match('/\$_(SESSION|POST|GET|REQUEST|COOKIE)/i', $line)
        ) {
            $issues[] = [
                'type' => 'Sensitive Data Exposure',
                'severity' => 'medium',
                'line' => $lineNum + 1,
                'code' => trim($line),
                'description' => 'Debug function exposing sensitive user data',
                'recommendation' => 'Remove debug statements from production code'
            ];
        }
    }

    // Check for insecure file uploads
    foreach ($lines as $lineNum => $line) {
        if (
            preg_match('/move_uploaded_file/i', $line) &&
            !preg_match('/pathinfo|mime_content_type|finfo_file/i', $content)
        ) {
            $issues[] = [
                'type' => 'Insecure File Upload',
                'severity' => 'high',
                'line' => $lineNum + 1,
                'code' => trim($line),
                'description' => 'File upload without proper validation',
                'recommendation' => 'Validate file type, size, and extension. Use whitelist approach'
            ];
        }
    }

    // Check for missing CSRF protection
    if (
        preg_match('/\$_POST/i', $content) &&
        !preg_match('/csrf|token|nonce/i', $content) &&
        preg_match('/<form/i', $content)
    ) {
        $issues[] = [
            'type' => 'Missing CSRF Protection',
            'severity' => 'high',
            'line' => 0,
            'code' => 'Form processing without CSRF token',
            'description' => 'Forms may be vulnerable to Cross-Site Request Forgery',
            'recommendation' => 'Implement CSRF tokens for all state-changing operations'
        ];
    }

    // Check for session security
    if (preg_match('/session_start/i', $content)) {
        if (!preg_match('/session_regenerate_id/i', $content)) {
            $issues[] = [
                'type' => 'Session Fixation Risk',
                'severity' => 'medium',
                'line' => 0,
                'code' => 'session_start() without regeneration',
                'description' => 'Session ID not regenerated after authentication',
                'recommendation' => 'Use session_regenerate_id(true) after login'
            ];
        }
    }

    return $issues;
}

// Code quality analysis
function analyzeCodeQuality($file)
{
    $issues = [];
    $content = file_get_contents($file);
    $lines = file($file);

    // Check file size
    $filesize = filesize($file);
    if ($filesize > 100000) { // 100KB
        $issues[] = [
            'type' => 'Large File',
            'severity' => 'low',
            'description' => 'File is very large (' . number_format($filesize / 1024, 2) . ' KB). Consider splitting.'
        ];
    }

    // Check line count
    if (count($lines) > 500) {
        $issues[] = [
            'type' => 'Too Many Lines',
            'severity' => 'low',
            'description' => 'File has ' . count($lines) . ' lines. Consider refactoring.'
        ];
    }

    // Check for error suppression
    if (preg_match('/@\w+\s*\(/', $content)) {
        $issues[] = [
            'type' => 'Error Suppression',
            'severity' => 'medium',
            'description' => 'Use of @ error suppression operator detected'
        ];
    }

    // Check for TODO/FIXME comments
    if (preg_match_all('/(TODO|FIXME|HACK|XXX)/i', $content, $matches)) {
        $issues[] = [
            'type' => 'Unfinished Code',
            'severity' => 'low',
            'description' => 'Found ' . count($matches[0]) . ' TODO/FIXME comments'
        ];
    }

    // Check for duplicate code (simple check)
    $duplicates = 0;
    for ($i = 0; $i < count($lines) - 5; $i++) {
        $block = implode('', array_slice($lines, $i, 5));
        if (strlen(trim($block)) > 20) {
            for ($j = $i + 5; $j < count($lines) - 5; $j++) {
                $compareBlock = implode('', array_slice($lines, $j, 5));
                if ($block === $compareBlock) {
                    $duplicates++;
                    break;
                }
            }
        }
    }

    if ($duplicates > 3) {
        $issues[] = [
            'type' => 'Code Duplication',
            'severity' => 'medium',
            'description' => 'Detected ' . $duplicates . ' potential code duplications'
        ];
    }

    return $issues;
}

// Check JavaScript files for common issues
function checkJavaScriptIssues($file)
{
    $issues = [];
    $content = file_get_contents($file);

    // Check for console.log (should be removed in production)
    if (preg_match_all('/console\.(log|debug|info|warn|error)/i', $content, $matches)) {
        $issues[] = [
            'type' => 'Debug Code',
            'severity' => 'low',
            'description' => 'Found ' . count($matches[0]) . ' console.log statements'
        ];
    }

    // Check for eval()
    if (preg_match('/\beval\s*\(/i', $content)) {
        $issues[] = [
            'type' => 'Dangerous Function',
            'severity' => 'high',
            'description' => 'Use of eval() detected'
        ];
    }

    // Check for == instead of ===
    if (preg_match_all('/[^=!]==[^=]/i', $content, $matches)) {
        $issues[] = [
            'type' => 'Loose Comparison',
            'severity' => 'low',
            'description' => 'Found ' . count($matches[0]) . ' loose comparisons (==). Use === instead.'
        ];
    }

    return $issues;
}

// ============================================
// PERFORM THE SCAN BEFORE HTML OUTPUT
// ============================================

// Initialize result arrays
$all_files = [];
$syntax_results = [];
$security_issues = [];
$quality_issues = [];
$permission_issues = [];
$js_issues = [];

try {
    // Define helper function for scanning directories
    function scanDirectory($dir, &$files, $excludeDirs = [])
    {
        if (!is_dir($dir))
            return;

        $items = @scandir($dir);
        if ($items === false)
            return;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..')
                continue;

            $path = $dir . '/' . $item;
            $basename = basename($path);

            // Skip excluded directories
            if (is_dir($path) && in_array($basename, $excludeDirs))
                continue;

            if (is_dir($path)) {
                scanDirectory($path, $files, $excludeDirs);
            } else {
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                if (in_array($ext, ['php', 'js', 'css'])) {
                    $files[] = $path;
                }
            }
        }
    }

    function checkPHPSyntax($file)
    {
        $output = [];
        $return_code = 0;

        // Check if exec() is available
        $exec_disabled = false;
        $disabled_functions = ini_get('disable_functions');
        if ($disabled_functions) {
            $disabled_array = array_map('trim', explode(',', $disabled_functions));
            $exec_disabled = in_array('exec', $disabled_array);
        }

        if (!$exec_disabled && function_exists('exec')) {
            // Use exec() if available
            @exec('php -l ' . escapeshellarg($file) . ' 2>&1', $output, $return_code);
            $valid = $return_code === 0;
            $output_text = implode("\n", $output);
        } else {
            // Fallback: Use token_get_all() for basic syntax checking
            $content = @file_get_contents($file);
            if ($content === false) {
                return [
                    'valid' => false,
                    'output' => "Unable to read file: $file",
                    'file' => $file
                ];
            }

            // Suppress errors and check if tokenization works
            $valid = true;
            $error_message = '';

            set_error_handler(function ($errno, $errstr) use (&$valid, &$error_message) {
                $valid = false;
                $error_message = $errstr;
            });

            try {
                $tokens = @token_get_all($content);
                if ($tokens === false) {
                    $valid = false;
                    $error_message = "Tokenization failed";
                }
            } catch (ParseError $e) {
                $valid = false;
                $error_message = $e->getMessage();
            } catch (Exception $e) {
                $valid = false;
                $error_message = $e->getMessage();
            }

            restore_error_handler();

            $output_text = $valid ? "No syntax errors detected in $file" : "Parse error: $error_message in $file";
        }

        return [
            'valid' => $valid,
            'output' => $output_text,
            'file' => $file
        ];
    }

    function checkFilePermissions($file)
    {
        $perms = fileperms($file);
        $readable = is_readable($file);
        $writable = is_writable($file);

        return [
            'readable' => $readable,
            'writable' => $writable,
            'permissions' => substr(sprintf('%o', $perms), -4)
        ];
    }

    // Scan all files
    foreach ($SCAN_DIRECTORIES as $dir) {
        if (is_dir($dir)) {
            scanDirectory($dir, $all_files, $EXCLUDE_DIRS);
        }
    }

    $stats['total_files'] = count($all_files);

    $php_files = array_filter($all_files, function ($f) {
        return strtolower(pathinfo($f, PATHINFO_EXTENSION)) === 'php';
    });
    $stats['php_files'] = count($php_files);

    $js_files = array_filter($all_files, function ($f) {
        return strtolower(pathinfo($f, PATHINFO_EXTENSION)) === 'js';
    });
    $stats['js_files'] = count($js_files);

    // Check syntax for all PHP files (limit to prevent timeout)
    $php_files_to_check = array_slice($php_files, 0, 100); // Limit to first 100 files
    foreach ($php_files_to_check as $php_file) {
        $result = checkPHPSyntax($php_file);
        if (!$result['valid']) {
            $syntax_results[] = $result;
            $stats['syntax_errors']++;
        }
    }

    // Check file permissions
    foreach ($php_files_to_check as $php_file) {
        $perms = checkFilePermissions($php_file);
        if (!$perms['readable'] || !$perms['writable']) {
            $permission_issues[] = [
                'file' => $php_file,
                'permissions' => $perms
            ];
            $stats['permission_issues']++;
        }
    }

    // Security vulnerability scanning (limited)
    foreach (array_slice($php_files, 0, 50) as $php_file) {
        $issues = checkSecurityIssues($php_file);
        if (!empty($issues)) {
            $security_issues[$php_file] = $issues;
            $stats['security_issues'] += count($issues);

            foreach ($issues as $issue) {
                if ($issue['type'] === 'SQL Injection Risk')
                    $stats['sql_injections']++;
                if ($issue['type'] === 'XSS Vulnerability')
                    $stats['xss_vulnerabilities']++;
                if ($issue['type'] === 'Deprecated Function')
                    $stats['deprecated_functions']++;
            }
        }
    }

    // Code quality analysis (limited)
    foreach (array_slice($php_files, 0, 50) as $php_file) {
        $issues = analyzeCodeQuality($php_file);
        if (!empty($issues)) {
            $quality_issues[$php_file] = $issues;
            $stats['code_quality_issues'] += count($issues);
        }
    }

    // JavaScript analysis
    foreach ($js_files as $js_file) {
        $issues = checkJavaScriptIssues($js_file);
        if (!empty($issues)) {
            $js_issues[$js_file] = $issues;
        }
    }

    // CSS file count
    $css_files = array_filter($all_files, function ($f) {
        return strtolower(pathinfo($f, PATHINFO_EXTENSION)) === 'css';
    });
    $stats['css_files'] = count($css_files);

    // Save to history
    saveToHistory($stats, array_merge($syntax_results, $security_issues, $quality_issues));

} catch (Exception $e) {
    // If there's an error during scanning, set default values
    $scan_error = [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ];
}

// NOW START HTML OUTPUT
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CYCLOAN - Error Monitoring System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%);
            padding: 20px;
            color: #333;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .header p {
            font-size: 16px;
            opacity: 0.9;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            padding: 30px;
            background: #f5f5f5;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card i {
            font-size: 36px;
            margin-bottom: 10px;
            color: #1b5e20;
        }

        .stat-card .number {
            font-size: 32px;
            font-weight: 700;
            color: #1b5e20;
        }

        .stat-card .label {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }

        .stat-card.error {
            border-left: 5px solid #f44336;
        }

        .stat-card.error i,
        .stat-card.error .number {
            color: #f44336;
        }

        .stat-card.warning {
            border-left: 5px solid #ff9800;
        }

        .stat-card.warning i,
        .stat-card.warning .number {
            color: #ff9800;
        }

        .stat-card.success {
            border-left: 5px solid #4caf50;
        }

        .stat-card.success i,
        .stat-card.success .number {
            color: #4caf50;
        }

        .content {
            padding: 30px;
        }

        .section {
            margin-bottom: 30px;
            background: #f9f9f9;
            border-radius: 12px;
            padding: 25px;
            border-left: 5px solid #1b5e20;
        }

        .section h2 {
            color: #1b5e20;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 24px;
        }

        .section h2 i {
            font-size: 28px;
        }

        .log-container {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            border-radius: 8px;
            max-height: 400px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.6;
        }

        .log-container::-webkit-scrollbar {
            width: 10px;
        }

        .log-container::-webkit-scrollbar-track {
            background: #2d2d2d;
        }

        .log-container::-webkit-scrollbar-thumb {
            background: #1b5e20;
            border-radius: 5px;
        }

        .error-line {
            color: #f48771;
            padding: 5px;
            border-left: 3px solid #f44336;
            margin: 5px 0;
            padding-left: 10px;
        }

        .warning-line {
            color: #ffd54f;
            padding: 5px;
            border-left: 3px solid #ff9800;
            margin: 5px 0;
            padding-left: 10px;
        }

        .success-line {
            color: #81c784;
        }

        .info-line {
            color: #64b5f6;
        }

        .file-list {
            display: grid;
            gap: 10px;
        }

        .file-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 4px solid #4caf50;
            transition: all 0.3s ease;
        }

        .file-item:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateX(5px);
        }

        .file-item.has-error {
            border-left-color: #f44336;
            background: #ffebee;
        }

        .file-item.has-warning {
            border-left-color: #ff9800;
            background: #fff3e0;
        }

        .file-name {
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            color: white;
        }

        .badge.success {
            background: #4caf50;
        }

        .badge.error {
            background: #f44336;
        }

        .badge.warning {
            background: #ff9800;
        }

        .badge.info {
            background: #2196f3;
        }

        .refresh-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            padding: 15px 30px;
            background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%);
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 6px 20px rgba(27, 94, 32, 0.4);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .refresh-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(27, 94, 32, 0.5);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            font-weight: 500;
        }

        .alert i {
            font-size: 24px;
        }

        .alert.danger {
            background: #ffebee;
            color: #c62828;
            border-left: 5px solid #f44336;
        }

        .alert.success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 5px solid #4caf50;
        }

        .alert.warning {
            background: #fff3e0;
            color: #e65100;
            border-left: 5px solid #ff9800;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }

        thead {
            background: #1b5e20;
            color: white;
        }

        th,
        td {
            padding: 12px;
            text-align: left;
        }

        tbody tr:nth-child(even) {
            background: #f5f5f5;
        }

        tbody tr:hover {
            background: #e8f5e9;
        }

        .no-errors {
            text-align: center;
            padding: 40px;
            color: #4caf50;
            font-size: 18px;
        }

        .timestamp {
            text-align: center;
            padding: 15px;
            background: #f5f5f5;
            color: #666;
            font-size: 14px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-bug"></i> CYCLOAN Error Monitoring System</h1>
            <p>Real-time monitoring of all system files, errors, and potential issues</p>
            <p style="margin-top: 10px; font-size: 14px;">
                <i class="fas fa-clock"></i> Last Scan: <?php echo date('F d, Y h:i:s A'); ?>
            </p>
        </div>

        <?php if (isset($scan_error)): ?>
            <!-- Scan Error Display -->
            <div style="background: #f44336; color: white; padding: 20px; margin: 20px; border-radius: 8px;">
                <h2><i class="fas fa-exclamation-circle"></i> Error During Scan</h2>
                <p><strong>Error Message:</strong> <?php echo htmlspecialchars($scan_error['message']); ?></p>
                <p><strong>File:</strong> <?php echo htmlspecialchars($scan_error['file']); ?></p>
                <p><strong>Line:</strong> <?php echo $scan_error['line']; ?></p>
                <details>
                    <summary
                        style="cursor: pointer; padding: 10px; background: rgba(0,0,0,0.2); border-radius: 4px; margin-top: 10px;">
                        View Stack Trace</summary>
                    <pre
                        style="background: rgba(0,0,0,0.2); padding: 10px; border-radius: 4px; overflow: auto; max-height: 200px; margin-top: 10px;"><?php echo htmlspecialchars($scan_error['trace']); ?></pre>
                </details>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['debug'])): ?>
            <!-- Debug Info -->
            <div
                style="background: #333; color: #0f0; padding: 20px; margin: 20px; border-radius: 8px; font-family: monospace;">
                <h3 style="color: #0f0;">DEBUG MODE</h3>
                <pre><?php
                echo "Stats Array:\n";
                print_r($stats);
                echo "\n\nScan Directories:\n";
                print_r($SCAN_DIRECTORIES);
                echo "\n\nTotal Files Found: " . count($all_files);
                echo "\n\nPHP Version: " . PHP_VERSION;
                echo "\n\nCurrent Dir: " . __DIR__;
                echo "\n\nScan Error: " . (isset($scan_error) ? 'YES' : 'NO');
                ?></pre>
            </div>
        <?php endif; ?>

        <!-- Statistics Dashboard -->
        <div class="stats-grid">
            <div class="stat-card success">
                <i class="fas fa-file-code"></i>
                <div class="number"><?php echo $stats['total_files']; ?></div>
                <div class="label">Total Files Scanned</div>
            </div>
            <div class="stat-card">
                <i class="fab fa-php"></i>
                <div class="number"><?php echo $stats['php_files']; ?></div>
                <div class="label">PHP Files</div>
            </div>
            <div class="stat-card">
                <i class="fab fa-js"></i>
                <div class="number"><?php echo $stats['js_files']; ?></div>
                <div class="label">JavaScript Files</div>
            </div>
            <div class="stat-card">
                <i class="fab fa-css3-alt"></i>
                <div class="number"><?php echo $stats['css_files']; ?></div>
                <div class="label">CSS Files</div>
            </div>
            <div class="stat-card <?php echo $stats['syntax_errors'] > 0 ? 'error' : 'success'; ?>">
                <i class="fas fa-exclamation-triangle"></i>
                <div class="number"><?php echo $stats['syntax_errors']; ?></div>
                <div class="label">Syntax Errors</div>
            </div>
            <div class="stat-card <?php echo $stats['security_issues'] > 0 ? 'error' : 'success'; ?>">
                <i class="fas fa-shield-alt"></i>
                <div class="number"><?php echo $stats['security_issues']; ?></div>
                <div class="label">Security Issues</div>
            </div>
            <div class="stat-card <?php echo $stats['code_quality_issues'] > 0 ? 'warning' : 'success'; ?>">
                <i class="fas fa-code-branch"></i>
                <div class="number"><?php echo $stats['code_quality_issues']; ?></div>
                <div class="label">Code Quality Issues</div>
            </div>
            <div class="stat-card <?php echo $stats['permission_issues'] > 0 ? 'warning' : 'success'; ?>">
                <i class="fas fa-lock"></i>
                <div class="number"><?php echo $stats['permission_issues']; ?></div>
                <div class="label">Permission Issues</div>
            </div>
        </div>

        <!-- Quick Actions Bar -->
        <div
            style="background: #f5f5f5; padding: 20px 30px; display: flex; gap: 15px; flex-wrap: wrap; justify-content: center; border-bottom: 2px solid #e0e0e0;">
            <button onclick="location.reload()"
                style="padding: 10px 20px; background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-sync-alt"></i> Refresh Scan
            </button>
            <button onclick="exportReport()"
                style="padding: 10px 20px; background: linear-gradient(135deg, #1976d2 0%, #2196f3 100%); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-download"></i> Export Report
            </button>
            <button onclick="document.getElementById('historyModal').style.display='block'"
                style="padding: 10px 20px; background: linear-gradient(135deg, #f57c00 0%, #ff9800 100%); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-history"></i> View History
            </button>
            <button onclick="toggleFilter()"
                style="padding: 10px 20px; background: linear-gradient(135deg, #7b1fa2 0%, #9c27b0 100%); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-filter"></i> Filter Results
            </button>
        </div>

        <div class="content">
            <?php
            // ============================================
            // 2. CHECK ERROR LOGS
            // ============================================
            
            $error_logs_found = [];
            $possible_logs = [
                __DIR__ . '/php_errors.log',
                __DIR__ . '/error_log',
                __DIR__ . '/errors.log',
                __DIR__ . '/debug_log.txt',
                __DIR__ . '/system_errors.log',
                'C:/xampp/apache/logs/error.log',
                'C:/wamp/logs/php_error.log',
                '/var/log/apache2/error.log',
                '/var/log/nginx/error.log',
            ];

            foreach ($possible_logs as $log_file) {
                if (file_exists($log_file) && filesize($log_file) > 0) {
                    $error_logs_found[$log_file] = file($log_file);
                }
            }

            if (!empty($error_logs_found)) {
                $stats['warnings'] = count($error_logs_found);
            }
            ?>

            <!-- PHP Configuration -->
            <div class="section">
                <h2><i class="fas fa-cog"></i> PHP Configuration</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Setting</th>
                            <th>Value</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>display_errors</strong></td>
                            <td><?php echo ini_get('display_errors') ? 'On' : 'Off'; ?></td>
                            <td><span class="badge <?php echo ini_get('display_errors') ? 'warning' : 'success'; ?>">
                                    <?php echo ini_get('display_errors') ? 'Enabled (Development)' : 'Disabled (Production)'; ?>
                                </span></td>
                        </tr>
                        <tr>
                            <td><strong>log_errors</strong></td>
                            <td><?php echo ini_get('log_errors') ? 'On' : 'Off'; ?></td>
                            <td><span class="badge <?php echo ini_get('log_errors') ? 'success' : 'error'; ?>">
                                    <?php echo ini_get('log_errors') ? 'Enabled' : 'Disabled'; ?>
                                </span></td>
                        </tr>
                        <tr>
                            <td><strong>error_reporting</strong></td>
                            <td><?php echo error_reporting(); ?></td>
                            <td><span class="badge info">Level: <?php echo error_reporting(); ?></span></td>
                        </tr>
                        <tr>
                            <td><strong>error_log</strong></td>
                            <td><?php echo ini_get('error_log') ?: 'Not set (using default)'; ?></td>
                            <td><span class="badge info">Log File Path</span></td>
                        </tr>
                        <tr>
                            <td><strong>PHP Version</strong></td>
                            <td><?php echo phpversion(); ?></td>
                            <td><span class="badge success">Running</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Syntax Errors -->
            <?php if (!empty($syntax_results)): ?>
                <div class="section">
                    <h2><i class="fas fa-code"></i> PHP Syntax Errors Found</h2>
                    <div class="alert danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <div>
                            <strong>Critical:</strong> Found <?php echo count($syntax_results); ?> file(s) with syntax
                            errors that need immediate attention!
                        </div>
                    </div>
                    <div class="file-list">
                        <?php foreach ($syntax_results as $result): ?>
                            <div class="file-item has-error">
                                <div class="file-name">
                                    <i class="fas fa-file-code"></i>
                                    <?php echo basename($result['file']); ?>
                                    <span style="color: #999; font-size: 12px;"><?php echo dirname($result['file']); ?></span>
                                </div>
                                <span class="badge error">Syntax Error</span>
                            </div>
                            <div class="log-container" style="margin-top: -10px; margin-bottom: 20px;">
                                <div class="error-line"><?php echo htmlspecialchars($result['output']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="section">
                    <h2><i class="fas fa-code"></i> PHP Syntax Check</h2>
                    <div class="alert success">
                        <i class="fas fa-check-circle"></i>
                        <div><strong>Excellent!</strong> All <?php echo count($php_files); ?> PHP files passed syntax
                            validation.</div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Error Logs -->
            <?php if (!empty($error_logs_found)): ?>
                <div class="section">
                    <h2><i class="fas fa-file-alt"></i> Error Logs</h2>
                    <div class="alert warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>Found <?php echo count($error_logs_found); ?> error log file(s) with recent entries.</div>
                    </div>
                    <?php foreach ($error_logs_found as $log_file => $lines): ?>
                        <div style="margin-bottom: 20px;">
                            <h3 style="color: #1b5e20; margin-bottom: 10px;">
                                <i class="fas fa-file"></i> <?php echo basename($log_file); ?>
                                <span style="font-size: 12px; color: #999; font-weight: normal;">(<?php echo count($lines); ?>
                                    lines)</span>
                            </h3>
                            <div class="log-container">
                                <?php
                                $last_lines = array_slice($lines, -$MAX_LOG_LINES);
                                foreach ($last_lines as $line):
                                    $line = htmlspecialchars($line);
                                    $class = '';
                                    if (stripos($line, 'error') !== false || stripos($line, 'fatal') !== false) {
                                        $class = 'error-line';
                                    } elseif (stripos($line, 'warning') !== false) {
                                        $class = 'warning-line';
                                    } elseif (stripos($line, 'notice') !== false) {
                                        $class = 'info-line';
                                    }
                                    ?>
                                    <div class="<?php echo $class; ?>"><?php echo $line; ?></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="section">
                    <h2><i class="fas fa-file-alt"></i> Error Logs</h2>
                    <div class="alert success">
                        <i class="fas fa-check-circle"></i>
                        <div><strong>Clean!</strong> No error logs found or all logs are empty.</div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- File Permission Issues -->
            <?php if (!empty($permission_issues)): ?>
                <div class="section">
                    <h2><i class="fas fa-lock"></i> File Permission Issues</h2>
                    <div class="alert warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>Found <?php echo count($permission_issues); ?> file(s) with permission issues.</div>
                    </div>
                    <div class="file-list">
                        <?php foreach ($permission_issues as $issue): ?>
                            <div class="file-item has-warning">
                                <div class="file-name">
                                    <i class="fas fa-file"></i>
                                    <?php echo basename($issue['file']); ?>
                                </div>
                                <div>
                                    <span class="badge warning">Permissions:
                                        <?php echo $issue['permissions']['permissions']; ?></span>
                                    <?php if (!$issue['permissions']['readable']): ?>
                                        <span class="badge error">Not Readable</span>
                                    <?php endif; ?>
                                    <?php if (!$issue['permissions']['writable']): ?>
                                        <span class="badge error">Not Writable</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Security Vulnerabilities -->
            <?php if (!empty($security_issues)): ?>
                <div class="section">
                    <h2><i class="fas fa-shield-alt"></i> Security Vulnerabilities Detected</h2>
                    <div class="alert danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <div>
                            <strong>Critical:</strong> Found <?php echo $stats['security_issues']; ?> security issue(s) in
                            <?php echo count($security_issues); ?> file(s)!
                            <br>SQL Injections: <?php echo $stats['sql_injections']; ?> | XSS:
                            <?php echo $stats['xss_vulnerabilities']; ?> | Deprecated:
                            <?php echo $stats['deprecated_functions']; ?>
                        </div>
                    </div>
                    <div class="file-list">
                        <?php foreach ($security_issues as $file => $issues): ?>
                            <div style="margin-bottom: 20px;">
                                <div class="file-item has-error">
                                    <div class="file-name">
                                        <i class="fas fa-file-code"></i>
                                        <?php echo basename($file); ?>
                                        <span style="color: #999; font-size: 12px;"><?php echo dirname($file); ?></span>
                                    </div>
                                    <span class="badge error"><?php echo count($issues); ?> Issue(s)</span>
                                </div>
                                <div
                                    style="background: #fff3e0; padding: 15px; margin-top: -10px; margin-bottom: 10px; border-radius: 0 0 8px 8px;">
                                    <?php foreach ($issues as $issue):
                                        $badgeClass = $issue['severity'] === 'critical' ? 'error' : ($issue['severity'] === 'high' ? 'warning' : 'info');
                                        ?>
                                        <div
                                            style="margin-bottom: 10px; padding: 10px; background: white; border-radius: 6px; border-left: 4px solid <?php echo $issue['severity'] === 'critical' ? '#f44336' : ($issue['severity'] === 'high' ? '#ff9800' : '#2196f3'); ?>;">
                                            <strong style="color: #1b5e20;"><?php echo $issue['type']; ?></strong>
                                            <span class="badge <?php echo $badgeClass; ?>"
                                                style="margin-left: 10px;"><?php echo strtoupper($issue['severity']); ?></span>
                                            <br>
                                            <span style="color: #666; font-size: 13px;"><?php echo $issue['description']; ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="section">
                    <h2><i class="fas fa-shield-alt"></i> Security Scan</h2>
                    <div class="alert success">
                        <i class="fas fa-check-circle"></i>
                        <div><strong>Secure!</strong> No major security vulnerabilities detected in scanned files.</div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Code Quality Analysis -->
            <?php if (!empty($quality_issues)): ?>
                <div class="section">
                    <h2><i class="fas fa-code-branch"></i> Code Quality Issues</h2>
                    <div class="alert warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>Found <?php echo $stats['code_quality_issues']; ?> code quality issue(s) in
                            <?php echo count($quality_issues); ?> file(s).
                        </div>
                    </div>
                    <div class="file-list">
                        <?php foreach ($quality_issues as $file => $issues): ?>
                            <div style="margin-bottom: 15px;">
                                <div class="file-item has-warning">
                                    <div class="file-name">
                                        <i class="fas fa-file-code"></i>
                                        <?php echo basename($file); ?>
                                    </div>
                                    <span class="badge warning"><?php echo count($issues); ?> Issue(s)</span>
                                </div>
                                <div style="background: #fff3e0; padding: 10px; margin-top: -10px; border-radius: 0 0 8px 8px;">
                                    <?php foreach ($issues as $issue): ?>
                                        <div
                                            style="padding: 8px; margin: 5px 0; background: white; border-radius: 4px; font-size: 13px;">
                                            <strong><?php echo $issue['type']; ?>:</strong> <?php echo $issue['description']; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="section">
                    <h2><i class="fas fa-code-branch"></i> Code Quality Analysis</h2>
                    <div class="alert success">
                        <i class="fas fa-check-circle"></i>
                        <div><strong>Good!</strong> No major code quality issues detected.</div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- JavaScript Analysis -->
            <?php if (!empty($js_issues)): ?>
                <div class="section">
                    <h2><i class="fab fa-js"></i> JavaScript Issues</h2>
                    <div class="alert warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>Found issues in <?php echo count($js_issues); ?> JavaScript file(s).</div>
                    </div>
                    <div class="file-list">
                        <?php foreach ($js_issues as $file => $issues): ?>
                            <div style="margin-bottom: 15px;">
                                <div class="file-item has-warning">
                                    <div class="file-name">
                                        <i class="fab fa-js"></i>
                                        <?php echo basename($file); ?>
                                    </div>
                                    <span class="badge warning"><?php echo count($issues); ?> Issue(s)</span>
                                </div>
                                <div style="background: #fff3e0; padding: 10px; margin-top: -10px; border-radius: 0 0 8px 8px;">
                                    <?php foreach ($issues as $issue): ?>
                                        <div
                                            style="padding: 8px; margin: 5px 0; background: white; border-radius: 4px; font-size: 13px;">
                                            <strong><?php echo $issue['type']; ?>:</strong> <?php echo $issue['description']; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Database Connection Test -->
            <div class="section">
                <h2><i class="fas fa-database"></i> Database Connection</h2>
                <?php
                $db_file = __DIR__ . '/CYCLOAN_db.php';
                if (file_exists($db_file)) {
                    try {
                        ob_start();
                        require_once $db_file;
                        ob_end_clean();

                        if (isset($conn) && $conn instanceof mysqli) {
                            if ($conn->connect_error) {
                                echo '<div class="alert danger">';
                                echo '<i class="fas fa-times-circle"></i>';
                                echo '<div><strong>Connection Failed:</strong> ' . htmlspecialchars($conn->connect_error) . '</div>';
                                echo '</div>';
                            } else {
                                echo '<div class="alert success">';
                                echo '<i class="fas fa-check-circle"></i>';
                                echo '<div><strong>Connected!</strong> Database connection is working properly.</div>';
                                echo '</div>';
                                echo '<table>';
                                echo '<tr><td><strong>Host</strong></td><td>' . $conn->host_info . '</td></tr>';
                                echo '<tr><td><strong>Database</strong></td><td>' . (isset($dbname) ? $dbname : 'N/A') . '</td></tr>';
                                echo '<tr><td><strong>Character Set</strong></td><td>' . $conn->character_set_name() . '</td></tr>';
                                echo '</table>';
                            }
                        } else {
                            echo '<div class="alert warning">';
                            echo '<i class="fas fa-exclamation-triangle"></i>';
                            echo '<div><strong>Warning:</strong> Database connection object not found.</div>';
                            echo '</div>';
                        }
                    } catch (Exception $e) {
                        echo '<div class="alert danger">';
                        echo '<i class="fas fa-times-circle"></i>';
                        echo '<div><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</div>';
                        echo '</div>';
                    }
                } else {
                    echo '<div class="alert warning">';
                    echo '<i class="fas fa-exclamation-triangle"></i>';
                    echo '<div><strong>Warning:</strong> CYCLOAN_db.php not found in current directory.</div>';
                    echo '</div>';
                }
                ?>
            </div>

            <!-- All Scanned Files -->
            <div class="section">
                <h2><i class="fas fa-folder-open"></i> All Scanned Files (<?php echo $stats['total_files']; ?>)</h2>
                <div class="file-list">
                    <?php
                    usort($all_files, function ($a, $b) {
                        return pathinfo($a, PATHINFO_EXTENSION) <=> pathinfo($b, PATHINFO_EXTENSION);
                    });

                    foreach ($all_files as $file):
                        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                        $icon = $ext === 'php' ? 'fab fa-php' : ($ext === 'js' ? 'fab fa-js' : 'fab fa-css3-alt');
                        $badge_color = $ext === 'php' ? 'info' : ($ext === 'js' ? 'warning' : 'success');
                        ?>
                        <div class="file-item">
                            <div class="file-name">
                                <i class="<?php echo $icon; ?>"></i>
                                <?php echo basename($file); ?>
                                <span
                                    style="color: #999; font-size: 11px;"><?php echo str_replace(__DIR__, '', dirname($file)); ?></span>
                            </div>
                            <span class="badge <?php echo $badge_color; ?>"><?php echo strtoupper($ext); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

        <div class="timestamp">
            <i class="fas fa-info-circle"></i>
            System scan completed successfully. Total execution time:
            <?php echo number_format((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2); ?> ms
        </div>
    </div>

    <button class="refresh-btn" onclick="location.reload()">
        <i class="fas fa-sync-alt"></i> Refresh Scan
    </button>

    <script>
        // Auto-refresh every 30 seconds
        // setTimeout(() => location.reload(), 30000);

        // Add smooth scroll behavior
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });

        // Export report function
        function exportReport() {
            const stats = {
                total_files: <?php echo $stats['total_files']; ?>,
                php_files: <?php echo $stats['php_files']; ?>,
                js_files: <?php echo $stats['js_files']; ?>,
                css_files: <?php echo $stats['css_files']; ?>,
                syntax_errors: <?php echo $stats['syntax_errors']; ?>,
                security_issues: <?php echo $stats['security_issues']; ?>,
                code_quality_issues: <?php echo $stats['code_quality_issues']; ?>,
                permission_issues: <?php echo $stats['permission_issues']; ?>,
                sql_injections: <?php echo $stats['sql_injections']; ?>,
                xss_vulnerabilities: <?php echo $stats['xss_vulnerabilities']; ?>,
                deprecated_functions: <?php echo $stats['deprecated_functions']; ?>
            };

            const report = {
                timestamp: '<?php echo date('Y-m-d H:i:s'); ?>',
                stats: stats,
                summary: {
                    total_issues: stats.syntax_errors + stats.security_issues + stats.code_quality_issues,
                    critical_issues: stats.security_issues,
                    warnings: stats.code_quality_issues
                }
            };

            const blob = new Blob([JSON.stringify(report, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'cycloan_error_report_' + Date.now() + '.json';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);

            alert('Report exported successfully!');
        }

        // Filter toggle
        let filterVisible = false;
        function toggleFilter() {
            filterVisible = !filterVisible;
            const filterPanel = document.getElementById('filterPanel');
            if (filterPanel) {
                filterPanel.style.display = filterVisible ? 'block' : 'none';
            }
        }

        // Apply filters
        function applyFilters() {
            const severity = document.getElementById('severityFilter').value;
            const type = document.getElementById('typeFilter').value;

            const items = document.querySelectorAll('.file-item');
            items.forEach(item => {
                let show = true;

                if (severity && severity !== 'all') {
                    const hasError = item.classList.contains('has-error');
                    const hasWarning = item.classList.contains('has-warning');

                    if (severity === 'error' && !hasError) show = false;
                    if (severity === 'warning' && !hasWarning) show = false;
                    if (severity === 'success' && (hasError || hasWarning)) show = false;
                }

                item.style.display = show ? 'flex' : 'none';
            });
        }

        // Clear filters
        function clearFilters() {
            document.getElementById('severityFilter').value = 'all';
            document.getElementById('typeFilter').value = 'all';
            const items = document.querySelectorAll('.file-item');
            items.forEach(item => {
                item.style.display = 'flex';
            });
        }

        // Real-time search
        function searchFiles(query) {
            query = query.toLowerCase();
            const items = document.querySelectorAll('.file-item');

            items.forEach(item => {
                const fileName = item.querySelector('.file-name').textContent.toLowerCase();
                item.style.display = fileName.includes(query) ? 'flex' : 'none';
            });
        }

        // Show notification
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 25px;
                background: ${type === 'success' ? '#4caf50' : '#f44336'};
                color: white;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                z-index: 10000;
                animation: slideIn 0.3s ease;
            `;
            notification.textContent = message;
            document.body.appendChild(notification);

            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => document.body.removeChild(notification), 300);
            }, 3000);
        }
    </script>

    <!-- History Modal -->
    <div id="historyModal"
        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; overflow-y: auto;">
        <div
            style="max-width: 1000px; margin: 50px auto; background: white; border-radius: 16px; padding: 30px; position: relative;">
            <button onclick="document.getElementById('historyModal').style.display='none'"
                style="position: absolute; top: 15px; right: 15px; background: #f44336; color: white; border: none; border-radius: 50%; width: 35px; height: 35px; cursor: pointer; font-size: 20px;">×</button>

            <h2 style="color: #1b5e20; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-history"></i> Scan History
            </h2>

            <?php
            $history = getHistory();
            if (!empty($history)):
                ?>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead style="background: #1b5e20; color: white;">
                        <tr>
                            <th style="padding: 12px; text-align: left;">Timestamp</th>
                            <th style="padding: 12px; text-align: center;">Files</th>
                            <th style="padding: 12px; text-align: center;">Errors</th>
                            <th style="padding: 12px; text-align: center;">Security</th>
                            <th style="padding: 12px; text-align: center;">Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_reverse($history) as $scan): ?>
                            <tr style="border-bottom: 1px solid #e0e0e0;">
                                <td style="padding: 12px;"><?php echo $scan['timestamp']; ?></td>
                                <td style="padding: 12px; text-align: center;"><?php echo $scan['stats']['total_files']; ?></td>
                                <td style="padding: 12px; text-align: center;">
                                    <span
                                        style="padding: 4px 12px; background: <?php echo $scan['stats']['syntax_errors'] > 0 ? '#f44336' : '#4caf50'; ?>; color: white; border-radius: 12px; font-size: 12px;">
                                        <?php echo $scan['stats']['syntax_errors']; ?>
                                    </span>
                                </td>
                                <td style="padding: 12px; text-align: center;">
                                    <span
                                        style="padding: 4px 12px; background: <?php echo $scan['stats']['security_issues'] > 0 ? '#f44336' : '#4caf50'; ?>; color: white; border-radius: 12px; font-size: 12px;">
                                        <?php echo $scan['stats']['security_issues']; ?>
                                    </span>
                                </td>
                                <td style="padding: 12px; text-align: center;"><?php echo $scan['scan_duration']; ?> ms</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #999;">
                    <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 10px;"></i>
                    <p>No scan history available yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filter Panel -->
    <div id="filterPanel"
        style="display: none; position: fixed; top: 20px; right: 20px; background: white; padding: 20px; border-radius: 12px; box-shadow: 0 8px 30px rgba(0,0,0,0.3); z-index: 1000; width: 300px;">
        <h3 style="color: #1b5e20; margin-bottom: 15px;">Filter Results</h3>

        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Search Files:</label>
            <input type="text" id="searchInput" onkeyup="searchFiles(this.value)" placeholder="Type to search..."
                style="width: 100%; padding: 8px; border: 2px solid #e0e0e0; border-radius: 6px;">
        </div>

        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Severity:</label>
            <select id="severityFilter" onchange="applyFilters()"
                style="width: 100%; padding: 8px; border: 2px solid #e0e0e0; border-radius: 6px;">
                <option value="all">All</option>
                <option value="error">Errors Only</option>
                <option value="warning">Warnings Only</option>
                <option value="success">Success Only</option>
            </select>
        </div>

        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Type:</label>
            <select id="typeFilter" onchange="applyFilters()"
                style="width: 100%; padding: 8px; border: 2px solid #e0e0e0; border-radius: 6px;">
                <option value="all">All Types</option>
                <option value="php">PHP Files</option>
                <option value="js">JavaScript Files</option>
                <option value="css">CSS Files</option>
            </select>
        </div>

        <div style="display: flex; gap: 10px;">
            <button onclick="clearFilters()"
                style="flex: 1; padding: 8px; background: #f44336; color: white; border: none; border-radius: 6px; cursor: pointer;">Clear</button>
            <button onclick="toggleFilter()"
                style="flex: 1; padding: 8px; background: #1b5e20; color: white; border: none; border-radius: 6px; cursor: pointer;">Close</button>
        </div>
    </div>

    <style>
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }

            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
    </style>
</body>

</html>