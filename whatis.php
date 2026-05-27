<?php
session_start();
require "CYCLOAN_db.php";

// Check if the user is logged in
if (!isset($_SESSION['email'])) {

    header("Location: index.php");
    exit();
}

// Fetch user details (user_id, first_name) from loan_application1
$email = $_SESSION['email'];
$stmt = $conn->prepare("SELECT user_id, first_name FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->bind_result($user_id, $first_name);
$stmt->fetch();
$stmt->close();

$email = $_SESSION['email'];
$stmt = $conn->prepare("SELECT user_id, first_name FROM loan_application1 WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->bind_result($user_id, $email);
$stmt->fetch();
$stmt->close();

// Store user_id and first_name in session if not already set
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = $user_id;
}
if (!isset($_SESSION['first_name'])) {
    $_SESSION['first_name'] = $first_name;
}

// Ensure user_id is set before fetching loans
if (!isset($_SESSION['user_id'])) {
    echo "Error: User ID not found.";
    exit();
}

// Fetch loan applications and their statuses for the logged-in user
$stmt_loans = $conn->prepare("
    SELECT la.id AS loan_id, la.loan_type, ls.pre_approval_status, ls.credit_investigation_status 
    FROM loan_application1 la 
    LEFT JOIN loan_status ls ON la.id = ls.loan_id 
    WHERE la.user_id = ?
");
$stmt_loans->bind_param("i", $_SESSION['user_id']);
$stmt_loans->execute();
$loan_results = $stmt_loans->get_result();

// Close the statement
$stmt_loans->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CYCLOAN</title>

    <head>
        <link rel="stylesheet" href="CSS/dashboard.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
        <script src="JAVASCRIPT/Real-Time.js"></script>
    </head>
</head>

<body>
    <div>
        <nav>
            <img src="IMAGE/Main-Logo.png" alt="Loan System Logo" class="sidebar-logo">
            <a href="whatis.php">CYCLOAN</a>
            <a href="user_dashboard.php">DASHBOARD</a>
            <a href="user_active_record.php">ACTIVE RECORDS</a>
            <a href="user_pending_records.php">PENDING RECORDS</a>
            <a href="user_closed_records.php">CLOSE RECORDS</a>
            <a href="user_history_activity.php">HISTORY ACTIVITY</a>
            <a class="logout" href="index.php" style="float: right;">LOGOUT</a>
        </nav>
    </div>

    <div class="lol">
        <div class="topbar">
            <p class="welcome-text">Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</p>
            <div id="datetime" class="datetime"></div>
            <div><i class="fa-solid fa-user-doctor"></i></div>
        </div>

        <div class="main-content">
            <div class="dashboard">
                <br><br><br><br>
                <h1>Welcome to CYCLOAN</h1>
                <p>We are glad to have you here. CYCLOAN is a platform that connects cyclists with loan options tailored
                    to
                    their
                    needs.</p>
                <p>To get started, please log in or create an account.</p>
                <p>Explore our loan options and find the perfect fit for your cycling journey.</p>
                <p>For any assistance, feel free to reach out to our support team.</p>
                <p>Happy cycling!</p>
                <p>Best regards,</p>
                <p>The CYCLOAN Team</p>
            </div>


        </div>


    </div>
    <footer class="footer">
        <div class="footer-content">
            <p>&copy; 2023 Your Company Name. All rights reserved.</p>
            <div class="footer-links">
                <a href="#">Privacy Policy</a>
                <a href="#">Terms of Service</a>
                <a href="#">Contact Us</a>
            </div>
        </div>
    </footer>
</body>

</html>