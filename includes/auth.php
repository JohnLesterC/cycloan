<?php
// includes/auth.php  ← ONLY FILE YOU NEED
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ROLE_DASHBOARD = [
    'User Application' => 'applicant.php',
    'User Admin 1' => 'admin1_dashboard.php',
    'User Admin 2' => 'admin2_dashboard.php',
    'superadmin' => 'Superadmin_dashboard.php',
];

// 1. If on LOGIN PAGE (index.php) and LOGGED IN → go to dashboard
if (basename($_SERVER['PHP_SELF']) === 'index.php') {
    if (!empty($_SESSION['user_id']) && !empty($_SESSION['role'])) {
        $dash = $ROLE_DASHBOARD[$_SESSION['role']] ?? 'index.php';
        header("Location: $dash");
        exit;
    }
    return; // Stop here — show login form
}

// 2. If on ANY OTHER PAGE (dashboard) and NOT LOGGED IN → go to login
if (empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// 3. If on dashboard and WRONG ROLE → optional (block access)
// Example: Admin 1 tries to access Superadmin page
$current_page = basename($_SERVER['PHP_SELF']);
$allowed = $ROLE_DASHBOARD[$_SESSION['role']] ?? null;

if ($allowed && $current_page !== $allowed && $current_page !== 'logout.php') {
    // Optional: redirect to correct dashboard
    header("Location: $allowed");
    exit;
}
?>