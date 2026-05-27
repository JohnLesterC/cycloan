<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Successful - CYCLOAN</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .success-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .success-header {
            background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%);
            padding: 40px 20px;
            text-align: center;
        }

        .success-icon {
            font-size: 60px;
            color: #4caf50;
            margin-bottom: 20px;
            animation: scaleIn 0.6s ease-out;
        }

        @keyframes scaleIn {
            from {
                transform: scale(0);
            }

            to {
                transform: scale(1);
            }
        }

        .success-header h1 {
            color: white;
            font-size: 28px;
            font-weight: 600;
            margin: 0;
            margin-top: 10px;
        }

        .success-body {
            padding: 40px 30px;
        }

        .success-message {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .success-message p {
            margin: 0 0 15px 0;
            font-size: 15px;
        }

        .success-email {
            background: linear-gradient(135deg, #f5f5f5 0%, #efefef 100%);
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            word-break: break-all;
            color: #1b5e20;
            font-weight: 500;
            text-align: center;
        }

        .checklist {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 10px;
            margin: 25px 0;
            border-left: 4px solid #1b5e20;
        }

        .checklist-item {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
            font-size: 14px;
        }

        .checklist-item:last-child {
            margin-bottom: 0;
        }

        .checklist-icon {
            width: 24px;
            height: 24px;
            background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            flex-shrink: 0;
            color: white;
            font-size: 12px;
        }

        .checklist-text {
            color: #333;
        }

        .action-buttons {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
            margin-top: 30px;
        }

        .btn-action {
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-login {
            background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%);
            color: white;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(27, 94, 32, 0.3);
            color: white;
            text-decoration: none;
        }

        .btn-home {
            background: white;
            color: #1b5e20;
            border: 2px solid #1b5e20;
        }

        .btn-home:hover {
            background: #f5f5f5;
            color: #1b5e20;
            text-decoration: none;
        }

        .security-notice {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 13px;
        }

        .security-notice-title {
            color: #856404;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .security-notice-text {
            color: #856404;
            margin: 0;
            line-height: 1.5;
        }

        .countdown-info {
            color: #666;
            font-size: 12px;
            text-align: center;
            margin-top: 15px;
        }

        .redirect-timer {
            font-weight: 600;
            color: #1b5e20;
        }
    </style>
</head>

<body>
    <div class="success-container">
        <div class="success-header">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1>Password Reset Successful!</h1>
        </div>

        <div class="success-body">
            <div class="success-message">
                <p>Your password has been successfully reset. Your account is now secured with your new password.</p>
                <?php if (isset($_GET['email'])): ?>
                    <p>Account email:</p>
                    <div class="success-email">
                        <?php echo htmlspecialchars($_GET['email']); ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="checklist">
                <div class="checklist-item">
                    <div class="checklist-icon"><i class="fas fa-check" style="font-size: 10px;"></i></div>
                    <div class="checklist-text">Email verified with OTP</div>
                </div>
                <div class="checklist-item">
                    <div class="checklist-icon"><i class="fas fa-check" style="font-size: 10px;"></i></div>
                    <div class="checklist-text">Strong password created</div>
                </div>
                <div class="checklist-item">
                    <div class="checklist-icon"><i class="fas fa-check" style="font-size: 10px;"></i></div>
                    <div class="checklist-text">Account security enhanced</div>
                </div>
            </div>

            <div class="security-notice">
                <div class="security-notice-title">
                    <i class="fas fa-lock"></i> Security Reminder
                </div>
                <p class="security-notice-text">
                    For your account security, <strong>never share your password</strong> with anyone. CYCLOAN staff
                    will never ask for your password.
                </p>
            </div>

            <div class="action-buttons">
                <a href="index.php" class="btn-action btn-login">
                    <i class="fas fa-sign-in-alt"></i> Login to Your Account
                </a>
                <a href="index.php" class="btn-action btn-home">
                    <i class="fas fa-home"></i> Return to Home
                </a>
            </div>

            <div class="countdown-info">
                Redirecting to login in <span class="redirect-timer">5</span> seconds...
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-redirect to login after 5 seconds
        let countdown = 5;
        const countdownElement = document.querySelector('.redirect-timer');

        const redirectInterval = setInterval(() => {
            countdown--;
            countdownElement.textContent = countdown;

            if (countdown <= 0) {
                clearInterval(redirectInterval);
                window.location.href = 'index.php';
            }
        }, 1000);

        // Allow immediate redirect on button click
        document.addEventListener('click', function (e) {
            if (e.target.classList.contains('btn-action')) {
                clearInterval(redirectInterval);
            }
        });
    </script>
</body>

</html>