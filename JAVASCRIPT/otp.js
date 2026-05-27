document.addEventListener('DOMContentLoaded', function () {
    initializeOTPInputs();
    startExpiryTimer();
    startResendTimer();
});

// Initialize OTP input boxes
function initializeOTPInputs() {
    const otpBoxes = document.querySelectorAll('.otp-box');
    const otpInput = document.getElementById('otp');
    const otpError = document.getElementById('otpError');

    otpBoxes.forEach((box, index) => {
        // Handle input
        box.addEventListener('input', function (e) {
            const value = e.target.value;

            // Only allow digits
            if (!/^\d$/.test(value)) {
                e.target.value = '';
                return;
            }

            // Mark as filled
            e.target.classList.add('filled');
            e.target.classList.remove('error');
            otpError.textContent = '';

            // Move to next box
            if (value && index < otpBoxes.length - 1) {
                otpBoxes[index + 1].focus();
            }

            // Update hidden input
            updateOTPValue();
        });

        // Handle keydown for backspace
        box.addEventListener('keydown', function (e) {
            if (e.key === 'Backspace') {
                if (!e.target.value && index > 0) {
                    // Move to previous box if current is empty
                    otpBoxes[index - 1].focus();
                    otpBoxes[index - 1].value = '';
                    otpBoxes[index - 1].classList.remove('filled');
                } else {
                    // Clear current box
                    e.target.value = '';
                    e.target.classList.remove('filled');
                }
                updateOTPValue();
            } else if (e.key === 'ArrowLeft' && index > 0) {
                otpBoxes[index - 1].focus();
            } else if (e.key === 'ArrowRight' && index < otpBoxes.length - 1) {
                otpBoxes[index + 1].focus();
            }
        });

        // Handle paste
        box.addEventListener('paste', function (e) {
            e.preventDefault();
            const pastedData = e.clipboardData.getData('text').trim();
            
            // Only process if it's 6 digits
            if (/^\d{6}$/.test(pastedData)) {
                const digits = pastedData.split('');
                otpBoxes.forEach((box, idx) => {
                    if (digits[idx]) {
                        box.value = digits[idx];
                        box.classList.add('filled');
                    }
                });
                updateOTPValue();
                // Focus last box
                otpBoxes[5].focus();
            }
        });

        // Auto-select on focus
        box.addEventListener('focus', function () {
            this.select();
        });
    });

    // Update hidden OTP input
    function updateOTPValue() {
        let otpValue = '';
        otpBoxes.forEach(box => {
            otpValue += box.value || '';
        });
        otpInput.value = otpValue;

        // Enable/disable verify button
        const verifyBtn = document.getElementById('verifyBtn');
        if (otpValue.length === 6) {
            verifyBtn.disabled = false;
        } else {
            verifyBtn.disabled = true;
        }
    }

    // Focus first box on load
    otpBoxes[0].focus();
}

// Start OTP expiry timer (10 minutes)
function startExpiryTimer() {
    const timerElement = document.getElementById('timer');
    const timerText = document.getElementById('timerText');
    
    if (!timerElement || !timerText) return;
    
    let timeLeft = 600; // 10 minutes in seconds

    const countdown = setInterval(() => {
        timeLeft--;

        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;
        timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;

        // Warning when less than 1 minute
        if (timeLeft <= 60) {
            timerElement.classList.add('expired');
        }

        // Expired
        if (timeLeft <= 0) {
            clearInterval(countdown);
            timerText.innerHTML = '<span style="color: var(--pending);">OTP has expired. Please request a new code.</span>';
            
            // Disable verify button
            document.getElementById('verifyBtn').disabled = true;
            
            // Show expiry message
            showMessageModal('OTP Expired', 'Your verification code has expired. Please click "Resend Code" to receive a new one.', 'error', { closable: true });
        }
    }, 1000);
}

// Start resend timer (60 seconds)
function startResendTimer() {
    const resendBtn = document.getElementById('resendBtn');
    const resendTimerSpan = document.getElementById('resendTimer');
    
    if (!resendBtn || !resendTimerSpan) return;
    
    let timeLeft = 60;

    const countdown = setInterval(() => {
        timeLeft--;
        resendTimerSpan.textContent = timeLeft;

        if (timeLeft <= 0) {
            clearInterval(countdown);
            resendBtn.disabled = false;
            resendBtn.innerHTML = '<i class="bi bi-arrow-clockwise me-2"></i>Resend Code';
        }
    }, 1000);
}

// Handle form submission
document.getElementById('otpForm').addEventListener('submit', function (e) {
    e.preventDefault();

    const otpValue = document.getElementById('otp').value;
    const otpError = document.getElementById('otpError');
    const verifyBtn = document.getElementById('verifyBtn');
    const otpBoxes = document.querySelectorAll('.otp-box');

    // Validate OTP
    if (otpValue.length !== 6) {
        otpError.textContent = 'Please enter all 6 digits';
        otpBoxes.forEach(box => box.classList.add('error'));
        return;
    }

    // Show loading state
    verifyBtn.classList.add('loading');
    verifyBtn.disabled = true;

    // Submit form
    this.submit();
});

// Handle resend button
const resendBtn = document.getElementById('resendBtn');
if (resendBtn) {
    resendBtn.addEventListener('click', function () {
        // Disable button
        resendBtn.disabled = true;
        resendBtn.innerHTML = '<i class="bi bi-arrow-clockwise me-2"></i>Sending...';

        // Send request to resend OTP
        fetch('resend_otp.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessageModal('Success', 'A new verification code has been sent to your email.', 'success', { closable: true });
                
                // Clear OTP boxes
                document.querySelectorAll('.otp-box').forEach(box => {
                    box.value = '';
                    box.classList.remove('filled', 'error');
                });
                document.getElementById('otp').value = '';
                document.getElementById('otpError').textContent = '';
                
                // Restart timers
                setTimeout(() => {
                    location.reload();
                }, 2000);
            } else {
                showMessageModal('Error', data.message || 'Failed to resend OTP. Please try again.', 'error', { closable: true });
                resendBtn.disabled = false;
                resendBtn.innerHTML = '<i class="bi bi-arrow-clockwise me-2"></i>Resend Code';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessageModal('Error', 'An error occurred. Please try again.', 'error', { closable: true });
            resendBtn.disabled = false;
            resendBtn.innerHTML = '<i class="bi bi-arrow-clockwise me-2"></i>Resend Code';
        });
    });
}

// Your existing showMessageModal function (keeping it as is)
function showMessageModal(title, message, type, options = {}) {
    const modal = document.getElementById("messageModal");
    const modalTitle = document.getElementById("messageModalLabel");
    const modalBody = document.getElementById("messageModalBody");
    const modalContent = modal.querySelector(".modal-content");
    const modalHeader = document.getElementById("modalHeader");
    const modalIcon = modalHeader ? modalHeader.querySelector('.modal-icon') : null;
    const modalTitleText = document.getElementById("modalTitleText");

    // Set title and message
    if (modalTitleText) {
        modalTitleText.textContent = title;
    } else {
        modalTitle.textContent = title;
    }
    modalBody.innerHTML = message;

    // Remove previous styling
    modalContent.classList.remove("border-success", "border-danger");
    modalTitle.classList.remove("text-success", "text-danger");
    modalBody.classList.remove("text-success", "text-danger");
    
    if (modalHeader) {
        modalHeader.classList.remove("success", "error");
    }

    // Apply styling based on message type
    if (type === "success") {
        modalContent.classList.add("border-success");
        modalTitle.classList.add("text-success");
        modalBody.classList.add("text-success");
        if (modalHeader) {
            modalHeader.classList.add("success");
        }
        if (modalIcon) {
            modalIcon.className = 'modal-icon bi bi-check-circle-fill';
        }
    } else {
        modalContent.classList.add("border-danger");
        modalTitle.classList.add("text-danger");
        modalBody.classList.add("text-danger");
        if (modalHeader) {
            modalHeader.classList.add("error");
        }
        if (modalIcon) {
            modalIcon.className = 'modal-icon bi bi-exclamation-triangle-fill';
        }
    }

    // Show modal
    const bootstrapModal = new bootstrap.Modal(modal, {
        backdrop: options.closable ? true : "static",
        keyboard: options.closable ? true : false,
    });
    bootstrapModal.show();

    // Handle auto-redirect for OTP verification success
    if (type === "success" && (message.includes("Account verified successfully") || message.includes("verified"))) {
        setTimeout(function () {
            window.location.href = "index.php";
        }, 3000);
    }
}

// Prevent right-click on OTP boxes (security)
document.querySelectorAll('.otp-box').forEach(box => {
    box.addEventListener('contextmenu', e => e.preventDefault());
});