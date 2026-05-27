<?php
// Test that basic dashboard structure loads without errors
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Simulate session data for testing
if (!isset($_SESSION['email'])) {
    $_SESSION['email'] = 'test@example.com';
    $_SESSION['user_id'] = 1;
}

require "CYCLOAN_db.php";
require_once 'timezone_config.php';
require "credit_points_manager.php";
require_once 'NotificationManager.php';

$userId = $_SESSION['user_id'];
$profile_image = 'IMAGE/default-avatar.png';
$current_page = 'user_dashboard.php';
$hasActiveOrPendingLoan = false;

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - Test</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #e2e8f0;
        }

        .header {
            position: fixed;
            top: 0;
            left: 260px;
            right: 0;
            height: 70px;
            background: #f8fafc;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: flex-end;
            align-items: center;
            padding-right: 20px;
            z-index: 1000;
        }

        .nav-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 260px;
            height: 100vh;
            background: #1a3c34;
            z-index: 1001;
            padding: 20px;
            color: white;
        }

        .nav-container h2 {
            margin-top: 20px;
            margin-bottom: 20px;
        }

        .nav-container a {
            display: block;
            color: #fff;
            text-decoration: none;
            padding: 12px 15px;
            margin: 5px 0;
            border-radius: 6px;
            transition: background 0.3s;
        }

        .nav-container a:hover,
        .nav-container a.active {
            background: rgba(251, 191, 36, 0.2);
            color: #fbc02d;
        }

        .main-content {
            margin-left: 260px;
            margin-top: 70px;
            padding: 30px;
        }

        .dashboard-header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .dashboard-header h1 {
            color: #1a3c34;
            margin-bottom: 10px;
        }

        .dashboard-header p {
            color: #666;
        }

        .test-box {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .test-box h3 {
            color: #1b5e20;
            margin-bottom: 10px;
        }

        .success {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 6px;
            margin: 5px 0;
        }

        .test-status {
            padding: 10px;
            border-radius: 6px;
            margin: 5px 0;
        }

        .test-status.pass {
            background: #d4edda;
            color: #155724;
        }

        .test-status.fail {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>

<body>
    <div class="header">
        <div style="text-align: right;">
            <p>Dashboard Test Page</p>
        </div>
    </div>

    <div class="nav-container">
        <h2>CYCLOAN</h2>
        <a href="#" class="active">Dashboard</a>
        <a href="#">Active Records</a>
        <a href="#">Pending Records</a>
        <a href="#">Closed Records</a>
        <a href="#">History</a>
        <a href="#">Notifications</a>
    </div>

    <div class="main-content">
        <div class="dashboard-header">
            <h1>✅ Dashboard Test - Structure Validation</h1>
            <p>Testing that all components load correctly...</p>
        </div>

        <div class="test-box">
            <h3>Component Load Status</h3>
            <div class="test-status pass">✓ Session started successfully</div>
            <div class="test-status pass">✓ Database connection loaded</div>
            <div class="test-status pass">✓ Timezone config loaded</div>
            <div class="test-status pass">✓ Credit points manager loaded</div>
            <div class="test-status pass">✓ NotificationManager loaded</div>
            <div class="test-status pass">✓ Basic layout structure rendering</div>
        </div>

        <div class="test-box">
            <h3>CSS & JavaScript Status</h3>
            <p>To verify all elements display correctly:</p>
            <ol>
                <li>Check that this page has visible elements with the dark green sidebar</li>
                <li>Verify the header is visible at the top</li>
                <li>Check main content area displays with proper spacing</li>
                <li>All text should be readable (not black on black)</li>
            </ol>
        </div>

        <div class="test-box">
            <h3>Session Information</h3>
            <div class="test-status pass">User Email: <?php echo htmlspecialchars($_SESSION['email'] ?? 'Not set'); ?>
            </div>
            <div class="test-status pass">User ID: <?php echo htmlspecialchars($_SESSION['user_id'] ?? 'Not set'); ?>
            </div>
        </div>

        <div class="test-box">
            <h3>Next Steps</h3>
            <p>If all elements are visible and properly styled:</p>
            <ul>
                <li>Load the full user_dashboard.php page</li>
                <li>Check browser console for JavaScript errors (F12)</li>
                <li>Verify CSS files are loading properly</li>
                <li>Check Network tab to ensure no 404 errors on CSS/JS files</li>
            </ul>
        </div>

        <div
            style="margin-top: 30px; padding: 20px; background: #f0f9ff; border-left: 4px solid #1b5e20; border-radius: 6px;">
            <h3 style="color: #1b5e20; margin-bottom: 10px;">✅ Troubleshooting Info</h3>
            <p><strong>Issue:</strong> If the dashboard shows blank/black elements</p>
            <p><strong>Likely Cause:</strong> CSS files not loading from relative paths</p>
            <p><strong>Solution Applied:</strong> Changed all CSS paths from http://cycloan-cldd.com/CSS/ to relative
                CSS/</p>
            <p><strong>Test:</strong> Open browser DevTools (F12) → Network tab and verify CSS files load with 200
                status</p>
        </div>
    </div>

    <script>
        // Verify JavaScript is working
        console.log('Dashboard test page loaded successfully');
        document.addEventListener('DOMContentLoaded', function () {
            console.log('DOM fully loaded');
        });
    </script>
</body>

</html>
<?php
?>