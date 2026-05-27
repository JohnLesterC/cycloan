<?php
session_start();

$message = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : '';
$message_type = isset($_GET['type']) ? htmlspecialchars($_GET['type']) : '';
$token = isset($_GET['token']) ? $_GET['token'] : '';
$email = isset($_GET['email']) ? $_GET['email'] : '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CYCLOAN - Reset Password</title>
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

        .reset-container {
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

        .reset-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .reset-header h2 {
            color: #1b5e20;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .reset-header p {
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

        .form-group input[type="password"],
        .form-group input[type="email"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-group input[type="password"]:focus,
        .form-group input[type="email"]:focus {
            outline: none;
            border-color: #1b5e20;
            box-shadow: 0 0 0 3px rgba(27, 94, 32, 0.1);
        }

        .password-strength {
            margin-top: 8px;
            font-size: 12px;
        }

        .strength-bar {
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            overflow: hidden;
            margin-top: 4px;
        }

        .strength-bar-fill {
            height: 100%;
            width: 0%;
            transition: width 0.3s ease;
        }

        .strength-weak {
            background: #dc3545;
        }

        .strength-medium {
            background: #ffc107;
        }

        .strength-strong {
            background: #28a745;
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

        .password-requirements {
            background: #f9f9f9;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            font-size: 12px;
        }

        .requirement {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            color: #666;
        }

        .requirement.met {
            color: #28a745;
        }

        .requirement-icon {
            width: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 8px;
        }

        @media (max-width: 600px) {
            .reset-container {
                padding: 25px;
            }

            .reset-header h2 {
                font-size: 24px;
            }

            .button-container {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
    <div class="reset-container">
        <div class="reset-header">
            <div class="icon-wrapper">
                <i class="fas fa-key"></i>
            </div>
            <h2>Create New Password</h2>
            <p>Please enter your new password below.</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type ?: 'info'; ?>" role="alert">
                <i
                    class="fas fa-<?php echo $message_type === 'error' ? 'exclamation-circle' : ($message_type === 'success' ? 'check-circle' : 'info-circle'); ?>"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form action="process_password_reset.php" method="POST" id="resetForm">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">

            <div class="form-group">
                <label for="password">
                    <i class="fas fa-lock"></i> New Password
                </label>
                <input type="password" id="password" name="password" placeholder="Enter strong password" required
                    minlength="8">
                <div class="password-strength">
                    <div class="strength-bar">
                        <div class="strength-bar-fill" id="strengthBar"></div>
                    </div>
                    <span id="strengthText">Password strength: Weak</span>
                </div>
            </div>

            <div class="form-group">
                <label for="confirm-password">
                    <i class="fas fa-lock"></i> Confirm Password
                </label>
                <input type="password" id="confirm-password" name="confirm_password" placeholder="Confirm password"
                    required minlength="8">
            </div>

            <div class="password-requirements">
                <div style="font-weight: 600; margin-bottom: 10px; color: #333;">Password Requirements:</div>
                <div class="requirement" id="req-length">
                    <span class="requirement-icon"><i class="fas fa-times"></i></span>
                    <span>At least 8 characters</span>
                </div>
                <div class="requirement" id="req-uppercase">
                    <span class="requirement-icon"><i class="fas fa-times"></i></span>
                    <span>At least one uppercase letter</span>
                </div>
                <div class="requirement" id="req-lowercase">
                    <span class="requirement-icon"><i class="fas fa-times"></i></span>
                    <span>At least one lowercase letter</span>
                </div>
                <div class="requirement" id="req-number">
                    <span class="requirement-icon"><i class="fas fa-times"></i></span>
                    <span>At least one number</span>
                </div>
                <div class="requirement" id="req-special">
                    <span class="requirement-icon"><i class="fas fa-times"></i></span>
                    <span>At least one special character (!@#$%^&*)</span>
                </div>
                <div class="requirement" id="req-match">
                    <span class="requirement-icon"><i class="fas fa-times"></i></span>
                    <span>Passwords match</span>
                </div>
            </div>

            <div class="button-container">
                <button type="submit" class="submit-btn" id="submitBtn" disabled>
                    <span id="btnText">Reset Password</span>
                </button>
                <a href="index.php" class="back-button">
                    <i class="fas fa-arrow-left" style="margin-right: 5px;"></i> Back
                </a>
            </div>
        </form>
    </div>

    <script>
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm-password');
        const submitBtn = document.getElementById('submitBtn');

        // Password strength checker
        function checkPasswordStrength(password) {
            let strength = 0;
            const requirements = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /\d/.test(password),
                special: /[!@#$%^&*]/.test(password)
            };

            // Update requirement indicators
            document.getElementById('req-length').classList.toggle('met', requirements.length);
            document.getElementById('req-uppercase').classList.toggle('met', requirements.uppercase);
            document.getElementById('req-lowercase').classList.toggle('met', requirements.lowercase);
            document.getElementById('req-number').classList.toggle('met', requirements.number);
            document.getElementById('req-special').classList.toggle('met', requirements.special);

            // Calculate strength
            Object.values(requirements).forEach(req => {
                if (req) strength++;
            });

            // Check if passwords match
            const passwordsMatch = passwordInput.value === confirmPasswordInput.value && passwordInput.value !== '';
            document.getElementById('req-match').classList.toggle('met', passwordsMatch);

            // Update strength bar
            const bar = document.getElementById('strengthBar');
            const text = document.getElementById('strengthText');
            let strengthLevel = 'Weak';
            let strengthClass = 'strength-weak';

            if (strength >= 4 && passwordsMatch) {
                strengthLevel = 'Strong';
                strengthClass = 'strength-strong';
                bar.style.width = '100%';
            } else if (strength >= 3) {
                strengthLevel = 'Medium';
                strengthClass = 'strength-medium';
                bar.style.width = '66%';
            } else {
                bar.style.width = '33%';
            }

            bar.className = 'strength-bar-fill ' + strengthClass;
            text.textContent = 'Password strength: ' + strengthLevel;

            // Enable submit if all requirements met
            const allRequirementsMet = Object.values(requirements).every(req => req) && passwordsMatch;
            submitBtn.disabled = !allRequirementsMet;
        }

        // Check password on input
        passwordInput.addEventListener('input', () => checkPasswordStrength(passwordInput.value));
        confirmPasswordInput.addEventListener('input', () => checkPasswordStrength(passwordInput.value));

        // Form submission
        document.getElementById('resetForm').addEventListener('submit', function (e) {
            if (!passwordInput.value || !confirmPasswordInput.value) {
                e.preventDefault();
                alert('Please fill in all fields');
                return;
            }

            if (passwordInput.value !== confirmPasswordInput.value) {
                e.preventDefault();
                alert('Passwords do not match');
                return;
            }

            submitBtn.disabled = true;
        });
    </script>
</body>

</html>