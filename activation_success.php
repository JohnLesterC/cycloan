<?php
session_start();

// Check if user came from successful OTP verification
if (!isset($_SESSION['activation_success']) || $_SESSION['activation_success'] !== true) {
    header("Location: index.php");
    exit();
}

// Clear the flag after checking
unset($_SESSION['activation_success']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CYCLOAN - Account Activated Successfully</title>
    <link rel="stylesheet" href="CSS/activation_success.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>

<body>
    <div class="success-wrapper">
        <!-- Animated Background Elements -->
        <div class="bg-elements">
            <div class="element element-1"></div>
            <div class="element element-2"></div>
            <div class="element element-3"></div>
        </div>

        <!-- Success Modal -->
        <div class="success-modal">
            <!-- Confetti Animation Container -->
            <div class="confetti-container" id="confettiContainer"></div>

            <!-- Success Icon with Animation -->
            <div class="success-icon-wrapper">
                <div class="success-icon">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div class="success-glow"></div>
            </div>

            <!-- Main Content -->
            <h1 class="success-title">Account Activated Successfully!</h1>
            <p class="success-subtitle">Your CYCLOAN account is now fully active and ready to use</p>

            <!-- Status Information -->
            <div class="status-info">
                <div class="info-item">
                    <div class="info-icon">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <div class="info-content">
                        <h3>Email Verified</h3>
                        <p>Your email address has been successfully verified</p>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-icon">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <div class="info-content">
                        <h3>Account Secured</h3>
                        <p>Your account is protected with enterprise-grade security</p>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-icon">
                        <i class="bi bi-rocket-fill"></i>
                    </div>
                    <div class="info-content">
                        <h3>Ready to Start</h3>
                        <p>You can now apply for loans and access all features</p>
                    </div>
                </div>
            </div>

            <!-- Features Available -->
            <div class="features-section">
                <h3 class="features-title">What You Can Do Now:</h3>
                <div class="features-grid">
                    <div class="feature-card">
                        <div class="feature-icon">📝</div>
                        <h4>Apply for Loans</h4>
                        <p>Submit loan applications with just a few clicks</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">📊</div>
                        <h4>Track Status</h4>
                        <p>Monitor your application status in real-time</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">💳</div>
                        <h4>Manage Payments</h4>
                        <p>View and manage your loan payments easily</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">📞</div>
                        <h4>24/7 Support</h4>
                        <p>Our team is always here to help you</p>
                    </div>
                </div>
            </div>

            <!-- Next Steps -->
            <div class="next-steps-section">
                <h3 class="section-title">Next Steps:</h3>
                <div class="steps-list">
                    <div class="step-item">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <h4>Log in to Your Account</h4>
                            <p>Use your email and password to access your dashboard</p>
                        </div>
                    </div>
                    <div class="step-item">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <h4>Complete Your Profile</h4>
                            <p>Add any additional information if needed</p>
                        </div>
                    </div>
                    <div class="step-item">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <h4>Start Your Loan Application</h4>
                            <p>Begin the process to apply for a loan</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="button-container">
                <a href="index.php" class="btn btn-primary btn-lg">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Go to Login
                </a>
                <a href="index.php" class="btn btn-secondary btn-lg">
                    <i class="bi bi-arrow-left me-2"></i>Back to Home
                </a>
            </div>

            <!-- Additional Info -->
            <div class="info-box">
                <p>
                    <i class="bi bi-info-circle me-2"></i>
                    A confirmation email has been sent to your registered email address
                </p>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Confetti animation
        function createConfetti() {
            const container = document.getElementById('confettiContainer');
            const colors = ['#1b5e20', '#2e7d32', '#4caf50', '#81c784', '#ffeb3b', '#fbc02d'];

            for (let i = 0; i < 50; i++) {
                const confetti = document.createElement('div');
                confetti.classList.add('confetti-piece');
                confetti.style.left = Math.random() * 100 + '%';
                confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                confetti.style.delay = (Math.random() * 0.3) + 's';
                container.appendChild(confetti);
            }
        }

        // Initialize animations on page load
        document.addEventListener('DOMContentLoaded', function () {
            createConfetti();

            // Trigger animations
            const modal = document.querySelector('.success-modal');
            setTimeout(() => {
                modal.classList.add('show-animation');
            }, 100);
        });

        // Auto redirect after 10 seconds (optional)
        setTimeout(() => {
            // Uncomment the line below to auto-redirect after 10 seconds
            // window.location.href = 'index.php';
        }, 10000);
    </script>
</body>

</html>