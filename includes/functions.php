<?php
// functions.php
require_once 'config.php';

/**
 * Generate a cryptographically safe token
 */
function generateToken(): string {
    return bin2hex(random_bytes(32));
}

/**
 * Save a new login token (kills any previous token for the same browser)
 */
function saveLoginToken(int $userId, string $userType): string {
    global $mysqli;
    
    $token     = generateToken();
    $ip        = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua        = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // 1. Delete any old token that belongs to the SAME browser (same IP+UA)
    $stmt = $mysqli->prepare(
        "DELETE FROM login_sessions 
         WHERE ip_address = ? AND user_agent = ?"
    );
    $stmt->bind_param('ss', $ip, $ua);
    $stmt->execute();
    $stmt->close();
    
    // 2. Insert new token
    $stmt = $mysqli->prepare(
        "INSERT INTO login_sessions (user_id, user_type, token, ip_address, user_agent)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('issss', $userId, $userType, $token, $ip, $ua);
    $stmt->execute();
    $stmt->close();
    
    return $token;
}

/**
 * Verify token & return user data (or FALSE)
 */
function verifyLoginToken(string $token): ?array {
    global $mysqli;
    
    $stmt = $mysqli->prepare(
        "SELECT user_id, user_type 
         FROM login_sessions 
         WHERE token = ? 
         LIMIT 1"
    );
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    
    return $row ?: null;
}

/**
 * Delete token (logout)
 */
function destroyLoginToken(string $token): void {
    global $mysqli;
    $stmt = $mysqli->prepare("DELETE FROM login_sessions WHERE token = ?");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $stmt->close();
}
?>