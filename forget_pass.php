<?php
session_start();

// Get any messages from query parameters
$message = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : '';
$message_type = isset($_GET['type']) ? htmlspecialchars($_GET['type']) : ''; // 'success', 'error', 'info'
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CYCLOAN - Forgot Password</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .forget-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 450px;
            padding: 40px;
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .forget-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .forget-header img {
            max-width: 120px;
            margin-bottom: 20px;
        }

        .forget-header h2 {
            color: #1b5e20;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .forget-header p {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }

        .alert {
            border: none;
            border-radius: 10px;
            margin-bottom: 20px;
            padding: 12px 16px;
            font-size: 14px;
            animation: alertSlideIn 0.4s ease-out;
        }

        @keyframes alertSlideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background-color: #d4edda;
            border-left: 4px solid #28a745;
            color: #155724;
        }

        .alert-danger {
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
        }

        .alert-info {
            background-color: #d1ecf1;
            border-left: 4px solid #17a2b8;
            color: #0c5460;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }

        .form-group input[type="email"],
        .form-group input[type="text"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-group input[type="email"]:focus,
        .form-group input[type="text"]:focus {
            outline: none;
            border-color: #1b5e20;
            box-shadow: 0 0 0 3px rgba(27, 94, 32, 0.1);
        }

        .button-container {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }

        .submit-btn {
            flex: 1;
            padding: 12px;
            background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(27, 94, 32, 0.4);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .back-button {
            flex: 1;
            padding: 12px;
            background: #f5f5f5;
            color: #333;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .back-button:hover {
            background: #e8e8e8;
            border-color: #d0d0d0;
            color: #1b5e20;
        }

        .help-text {
            text-align: center;
            color: #999;
            font-size: 13px;
            margin-top: 20px;
            line-height: 1.6;
        }

        .help-text a {
            color: #1b5e20;
            text-decoration: none;
            font-weight: 500;
        }

        .help-text a:hover {
            text-decoration: underline;
        }

        .spinner {
            display: none;
            margin-right: 10px;
        }

        .submit-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .icon-wrapper {
            width: 60px;
            height: 60px;
            background: #f0f0f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 28px;
            color: #1b5e20;
        }

        @media (max-width: 600px) {
            .forget-container {
                padding: 25px;
            }

            .forget-header h2 {
                font-size: 24px;
            }

            .button-container {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
    <div class="forget-container">
        <div class="forget-header">
            <div class="icon-wrapper">
                <i class="fas fa-lock"></i>
            </div>
            <h2>Reset Password</h2>
            <p>Don't worry! We'll help you reset your password securely.</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type ?: 'info'; ?>" role="alert">
                <i
                    class="fas fa-<?php echo $message_type === 'error' ? 'exclamation-circle' : ($message_type === 'success' ? 'check-circle' : 'info-circle'); ?>"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form action="submit_forget_pass.php" method="POST" id="forgetForm">
            <div class="form-group">
                <label for="email">
                    <i class="fas fa-envelope"></i> Email Address
                </label>
                <input type="email" id="email" name="email" placeholder="Enter your registered email" required
                    autocomplete="email">
            </div>

            <div
                style="background-color: #e8f5e9; border-left: 4px solid #2e7d32; padding: 15px; border-radius: 8px; margin: 20px 0; font-size: 13px; color: #1b5e20;">
                <i class="fas fa-info-circle"></i> <strong>Security Info:</strong> We'll send a 6-digit OTP code to your
                email. Enter it in the next step to verify your identity and reset your password.
            </div>

            <div class="button-container">
                <button type="submit" class="submit-btn" id="submitBtn">
                    <span class="spinner" id="spinner">
                        <i class="fas fa-spinner fa-spin"></i>
                    </span>
                    <span id="btnText">Send OTP Code</span>
                </button>
                <a href="index.php" class="back-button">
                    <i class="fas fa-arrow-left" style="margin-right: 5px;"></i> Back
                </a>
            </div>
        </form>

        <div class="help-text">
            <p><i class="fas fa-shield-alt"></i> Your account is secure. We take your privacy seriously.</p>
            <p style="margin-top: 15px;">
                Can't find the reset email? <a href="#" onclick="resendEmail(event)">Resend</a> or
                <a href="index.php">try logging in again</a>
            </p>
        </div>
    </div>

    <script>
        // Handle form submission
        document.getElementById('forgetForm').addEventListener('submit', function (e) {
            const email = document.getElementById('email').value.trim();

            if (!email) {
                e.preventDefault();
                alert('Please enter your email address');
                return;
            }

            // Validate email format
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address');
                return;
            }

            // Show loading state
            const submitBtn = document.getElementById('submitBtn');
            const spinner = document.getElementById('spinner');
            submitBtn.disabled = true;
            spinner.style.display = 'inline-block';
        });

        function resendEmail(e) {
            e.preventDefault();
            const email = prompt('Enter your email address:');
            if (email) {
                const form = document.getElementById('forgetForm');
                document.getElementById('email').value = email;
                form.submit();
            }
        }
    </script>
</body>

</html>