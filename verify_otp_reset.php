<?php
session_start();

// Check if user came from forget_pass.php
if (!isset($_SESSION['reset_email']) || !isset($_SESSION['reset_otp_id'])) {
    header("Location: forget_pass.php");
    exit();
}

$email = $_SESSION['reset_email'];
$message = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : '';
$message_type = isset($_GET['type']) ? htmlspecialchars($_GET['type']) : '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CYCLOAN - Verify OTP</title>
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

        .otp-container {
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

        .otp-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .icon-wrapper {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 36px;
            color: white;
        }

        .otp-header h2 {
            color: #1b5e20;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .otp-header p {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }

        .email-info {
            background: linear-gradient(135deg, #e8f5e9 0%, #f1f8e9 100%);
            border: 2px solid #2e7d32;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
        }

        .email-info p {
            margin: 0;
            color: #1b5e20;
            font-size: 14px;
            font-weight: 500;
        }

        .email-info .email {
            display: block;
            font-weight: 700;
            font-size: 16px;
            margin-top: 5px;
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

        .otp-input-group {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 20px 0;
        }

        .otp-input {
            width: 50px;
            height: 50px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            color: #1b5e20;
            transition: all 0.3s ease;
        }

        .otp-input:focus {
            outline: none;
            border-color: #1b5e20;
            box-shadow: 0 0 0 3px rgba(27, 94, 32, 0.1);
        }

        .otp-input.filled {
            background: #e8f5e9;
            border-color: #2e7d32;
        }

        .timer {
            text-align: center;
            margin: 15px 0;
            font-size: 14px;
            color: #666;
        }

        .timer-text {
            font-weight: 600;
            color: #1b5e20;
        }

        .timer.warning {
            color: #f57c00;
        }

        .timer.warning .timer-text {
            color: #f57c00;
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

        .submit-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(27, 94, 32, 0.4);
        }

        .submit-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
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
            margin-top: 15px;
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

        .security-info {
            background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
            border-left: 5px solid #f57c00;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            font-size: 12px;
            color: #e65100;
            line-height: 1.6;
        }

        .security-info strong {
            display: block;
            margin-bottom: 8px;
        }

        @media (max-width: 600px) {
            .otp-container {
                padding: 25px;
            }

            .otp-header h2 {
                font-size: 24px;
            }

            .button-container {
                flex-direction: column;
            }

            .otp-input {
                width: 45px;
                height: 45px;
                font-size: 20px;
            }

            .otp-input-group {
                gap: 8px;
            }
        }
    </style>
</head>

<body>
    <div class="otp-container">
        <div class="otp-header">
            <div class="icon-wrapper">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h2>Verify Your Identity</h2>
            <p>Enter the 6-digit OTP code sent to your email</p>
        </div>

        <div class="email-info">
            <p>Verification code sent to:</p>
            <span
                class="email"><?php echo substr($email, 0, 3) . '****' . substr($email, strrpos($email, '@')); ?></span>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type ?: 'info'; ?>" role="alert">
                <i
                    class="fas fa-<?php echo $message_type === 'error' ? 'exclamation-circle' : ($message_type === 'success' ? 'check-circle' : 'info-circle'); ?>"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form action="process_otp_reset.php" method="POST" id="otpForm">
            <div class="form-group">
                <label>Enter 6-Digit Code</label>
                <div class="otp-input-group" id="otpGroup">
                    <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" autocomplete="off" required>
                    <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" autocomplete="off" required>
                    <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" autocomplete="off" required>
                    <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" autocomplete="off" required>
                    <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" autocomplete="off" required>
                    <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" autocomplete="off" required>
                </div>
                <input type="hidden" id="otpCode" name="otp_code">
            </div>

            <div class="timer" id="timerDisplay">
                <span class="timer-text" id="timerText">Expires in: 15:00</span>
            </div>

            <div class="button-container">
                <button type="submit" class="submit-btn" id="submitBtn">
                    <i class="fas fa-check-circle" style="margin-right: 8px;"></i> Verify OTP
                </button>
                <a href="forget_pass.php" class="back-button">
                    <i class="fas fa-arrow-left" style="margin-right: 5px;"></i> Back
                </a>
            </div>

            <div class="help-text">
                <p>Didn't receive the code? <a href="#" onclick="resendOTP(event)">Resend OTP</a></p>
                <p style="margin-top: 10px;"><a href="forget_pass.php">Try a different email</a></p>
            </div>

            <div class="security-info">
                <strong>🔒 Security Reminder:</strong>
                Never share this code with anyone. CYCLOAN staff will never ask for your OTP code.
            </div>
        </form>
    </div>

    <script>
        const otpInputs = document.querySelectorAll('.otp-input');
        const otpCodeInput = document.getElementById('otpCode');
        const timerDisplay = document.getElementById('timerDisplay');
        const timerText = document.getElementById('timerText');
        const form = document.getElementById('otpForm');

        // OTP input auto-focus and validation
        otpInputs.forEach((input, index) => {
            input.addEventListener('input', function (e) {
                if (!/[0-9]/.test(this.value)) {
                    this.value = '';
                    return;
                }

                if (this.value.length > 0) {
                    this.classList.add('filled');
                    if (index < otpInputs.length - 1) {
                        otpInputs[index + 1].focus();
                    }
                } else {
                    this.classList.remove('filled');
                }

                updateOTPCode();
                checkAllFilled();
            });

            input.addEventListener('keydown', function (e) {
                if (e.key === 'Backspace' && this.value === '') {
                    if (index > 0) {
                        otpInputs[index - 1].focus();
                    }
                }
            });

            input.addEventListener('paste', function (e) {
                e.preventDefault();
                const paste = (e.clipboardData || window.clipboardData).getData('text');
                if (/^\d{6}$/.test(paste)) {
                    paste.split('').forEach((char, i) => {
                        if (i < otpInputs.length) {
                            otpInputs[i].value = char;
                            otpInputs[i].classList.add('filled');
                        }
                    });
                    updateOTPCode();
                    checkAllFilled();
                }
            });
        });

        function updateOTPCode() {
            const code = Array.from(otpInputs).map(input => input.value).join('');
            otpCodeInput.value = code;
        }

        function checkAllFilled() {
            const allFilled = Array.from(otpInputs).every(input => input.value !== '');
            document.getElementById('submitBtn').disabled = !allFilled;
        }

        // Timer countdown
        let timeLeft = 900; // 15 minutes in seconds
        const countdown = setInterval(() => {
            timeLeft--;

            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            const timeString = `${minutes}:${seconds.toString().padStart(2, '0')}`;

            timerText.textContent = `Expires in: ${timeString}`;

            if (timeLeft <= 300) {
                timerDisplay.classList.add('warning');
            }

            if (timeLeft <= 0) {
                clearInterval(countdown);
                timerText.textContent = 'Code Expired!';
                timerDisplay.classList.add('warning');
                document.getElementById('submitBtn').disabled = true;
            }
        }, 1000);

        function resendOTP(e) {
            e.preventDefault();
            window.location.href = 'forget_pass.php?message=' + encodeURIComponent('OTP resent. Check your email.') + '&type=success';
        }

        // Form submission
        form.addEventListener('submit', function (e) {
            if (otpCodeInput.value.length !== 6) {
                e.preventDefault();
                alert('Please enter a valid 6-digit OTP code');
            }
        });

        // Auto-focus first input on load
        otpInputs[0].focus();
    </script>
</body>

</html>