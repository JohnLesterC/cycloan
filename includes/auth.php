<?php
// includes/auth.php — Centralized session guard
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ROLE_DASHBOARD = [
    'user'       => 'user_dashboard.php',
    'admin1'     => 'admin1_dashboard.php',
    'admin2'     => 'admin2_dashboard.php',
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

// 2. If on ANY OTHER PAGE and NOT LOGGED IN → go to login
if (empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// 3. If on a dashboard page and WRONG ROLE → redirect to correct dashboard
$current_page = basename($_SERVER['PHP_SELF']);
$allowed = $ROLE_DASHBOARD[$_SESSION['role']] ?? null;

if ($allowed && $current_page !== $allowed && $current_page !== 'logout.php') {
    // Only redirect if the current page is one of the role-specific dashboards
    // (don't block access to shared pages like notifications, profile, records, etc.)
    if (in_array($current_page, $ROLE_DASHBOARD)) {
        header("Location: $allowed");
        exit;
    }
}
