<?php
require_once __DIR__ . '/env_config.php';
if (defined('APP_DEBUG') && APP_DEBUG) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
ini_set('log_errors', '1');

// SECURITY: Regenerate session ID to prevent session fixation attacks
if (empty($_SESSION['_session_created'])) {
    session_regenerate_id(true);
    $_SESSION['_session_created'] = time();
}

// SECURITY: Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// SECURITY: Set secure session cookie parameters
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Strict');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // SECURITY: Validate CSRF token
    if (isset($_POST['csrf_token']) && !empty($_POST['csrf_token'])) {
        if ($_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Security error: Invalid session token'
            ]);
            exit();
        }
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'accept') {
        // SECURITY: Regenerate session on important decision
        session_regenerate_id(true);
        $_SESSION['data_privacy_consented'] = true;
        // Regenerate CSRF token after consent
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        echo json_encode(['success' => true, 'message' => 'Consent accepted']);
    } elseif ($action === 'decline') {
        $_SESSION['data_privacy_consented'] = false;
        $_SESSION['decline_message'] = 'You must accept the Data Privacy Policy to register. If you change your mind, please visit the registration page again.';
        echo json_encode(['success' => true, 'message' => 'Consent declined']);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>