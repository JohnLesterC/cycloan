<?php
// SECURITY: Set session cookie parameters BEFORE session_start()
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
// Only set secure flag if running on HTTPS (production)
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
if ($isHttps) {
    ini_set('session.cookie_secure', 1);
}

// Now start the session (after all ini_set calls)
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Load language support
require_once 'languages.php';

// SECURITY: Set HTTP security headers (must be before any output)
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; img-src \'self\' data: https:; font-src \'self\' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://fonts.gstatic.com; connect-src \'self\' https://api.github.com https://fonts.googleapis.com; frame-ancestors \'none\'; base-uri \'self\'; form-action \'self\'');

// SECURITY: Regenerate session ID ONLY on first visit (on login/registration start)
// Don't regenerate on every page load to prevent CSRF token invalidation
if (empty($_SESSION['_session_initialized'])) {
    // Only regenerate if this is a brand new session
    if (empty($_SESSION['_session_created'])) {
        session_regenerate_id(true);
        $_SESSION['_session_created'] = time();
    }
    $_SESSION['_session_initialized'] = true;
}

// SECURITY: Generate CSRF token if not exists - only once per session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Clear session messages for fresh page loads unless explicitly redirected
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_SESSION['fresh_redirect'])) {
    unset($_SESSION['success_message']);
    unset($_SESSION['error_message']);
    // Don't clear form_data here to preserve it across steps
}

// Initialize current step
$current_step = isset($_GET['step']) ? (int) $_GET['step'] : 1;
$form_data = isset($_SESSION['form_data']) ? $_SESSION['form_data'] : [];
$civil_status = $form_data['civil_status'] ?? '';
$max_steps = ($civil_status === 'Single' || $civil_status === 'Widowed') ? 5 : 6;

if ($current_step < 1 || $current_step > $max_steps) {
    $current_step = 1;
}

// Check if user has given data privacy consent
$show_form = isset($_SESSION['data_privacy_consented']) && $_SESSION['data_privacy_consented'] === true;

// SECURITY: Generate CSRF token for this page
$csrf_token = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('page_title'); ?></title>

    <!-- SECURITY: Additional security headers (via meta tags where HTTP headers not available) -->
    <meta http-equiv="X-UA-Compatible" content="ie=edge">

    <link rel="stylesheet" href="./CSS/registration.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        /* Enhanced Responsive & Accessibility Styles */

        /* Improved focus management */
        *:focus {
            outline: 2px solid var(--secondary);
            outline-offset: 2px;
        }

        /* Skip link for accessibility */
        .skip-link {
            position: absolute;
            top: -40px;
            left: 6px;
            background: var(--primary);
            color: white;
            padding: 8px;
            text-decoration: none;
            z-index: 1000;
            border-radius: 4px;
        }

        .skip-link:focus {
            top: 6px;
        }

        /* Language Selector Styles */
        .language-selector {
            position: fixed;
            top: 15px;
            right: 15px;
            z-index: 1000;
            background: white;
            padding: 8px 12px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .language-selector select {
            border: none;
            background: transparent;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            cursor: pointer;
            color: var(--dark);
            padding: 4px 8px;
            font-weight: 500;
        }

        .language-selector select:focus {
            outline: none;
        }

        .language-selector select option {
            padding: 10px;
        }

        @media (max-width: 768px) {
            .language-selector {
                top: 10px;
                right: 10px;
                padding: 6px 10px;
            }

            .language-selector select {
                font-size: 12px;
            }
        }

        /* Enhanced button accessibility */
        .btn:focus-visible {
            box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.3);
        }

        /* Touch device base improvements */
        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="number"],
        input[type="date"],
        select,
        textarea {
            -webkit-appearance: none;
            appearance: none;
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        input[type="checkbox"],
        input[type="radio"] {
            cursor: pointer;
            min-width: 18px;
            min-height: 18px;
        }

        /* Prevent zoom on mobile input focus */
        @media (max-width: 768px) {

            input[type="text"],
            input[type="email"],
            input[type="password"],
            input[type="number"],
            input[type="date"],
            select,
            textarea {
                font-size: 16px;
                min-height: 44px;
                padding: 12px;
            }
        }

        /* Improved mobile responsiveness */
        @media (max-width: 768px) {
            body {
                padding: 5px;
            }

            .container_registration {
                width: 100%;
                margin: 10px auto;
                padding: 15px;
                border-radius: 8px;
            }

            h2 {
                font-size: 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .progress-container {
                flex-direction: row;
                flex-wrap: wrap;
                gap: 4px;
                justify-content: center;
                align-items: center;
            }

            .progress-step {
                width: auto;
                flex-direction: column;
                justify-content: center;
                padding: 0;
                background: transparent;
                border-radius: 0;
            }

            .progress-circle {
                margin-right: 0;
                margin-bottom: 4px;
            }

            .modal-dialog {
                margin: 10px;
            }

            label {
                font-size: 14px;
                display: block;
                margin-bottom: 8px;
                font-weight: 500;
            }

            .field-help-text {
                font-size: 12px;
                margin-top: 4px;
                color: #666;
            }

            .btn {
                min-height: 44px;
                padding: 12px 16px;
                font-size: 14px;
                width: 100%;
            }

            .button-container {
                display: flex;
                flex-direction: column;
                gap: 10px;
                margin-top: 20px;
            }

            .button-container .btn {
                width: 100%;
            }

            .button-container.button-row {
                flex-direction: column;
            }
        }

        /* Tablet optimization */
        @media (min-width: 481px) and (max-width: 768px) {
            .button-container {
                flex-direction: row;
                gap: 10px;
            }

            .button-container .btn {
                flex: 1;
                width: auto;
            }

            .form-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
        }

        /* Additional Modal Styling */
        .modal-content {
            border-radius: 8px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            background: linear-gradient(135deg, #2e7d32, #1b5e20);
            color: white;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }

        .modal-header .modal-title {
            font-weight: 600;
        }

        .modal-body {
            padding: 30px;
            line-height: 1.8;
        }

        .modal-body p {
            margin-bottom: 15px;
            color: #333;
        }

        .modal-body a {
            color: #1b5e20;
            font-weight: 500;
            text-decoration: underline;
        }

        .modal-body a:hover {
            color: #2e7d32;
        }

        .modal-footer {
            padding: 20px 30px;
            border-top: 1px solid #e0e0e0;
        }

        .modal-footer .btn {
            padding: 10px 30px;
            font-weight: 500;
            border-radius: 4px;
        }

        .modal-footer .btn-secondary {
            background-color: #6c757d;
            border: none;
        }

        .modal-footer .btn-secondary:hover {
            background-color: #5a6268;
        }

        .modal-footer .btn-primary {
            background: linear-gradient(135deg, #2e7d32, #1b5e20);
            border: none;
        }

        .modal-footer .btn-primary:hover {
            background: linear-gradient(135deg, #1b5e20, #2e7d32);
            box-shadow: 0 4px 8px rgba(27, 94, 32, 0.3);
        }

        /* Loading States & Animations */
        .form-field-loading {
            position: relative;
        }

        .form-field-loading::after {
            content: '';
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 16px;
            height: 16px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid var(--secondary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: translateY(-50%) rotate(0deg);
            }

            100% {
                transform: translateY(-50%) rotate(360deg);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .form-step {
            animation: fadeInUp 0.6s ease-out;
        }

        .validation-success {
            animation: slideInRight 0.4s ease-out;
        }

        .progress-step.active {
            animation: pulse 0.6s ease-in-out;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }

            100% {
                transform: scale(1);
            }
        }

        /* Enhanced Loading Spinner */
        .enhanced-loading {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.95);
            z-index: 9999;
            backdrop-filter: blur(5px);
        }

        .spinner-container {
            position: relative;
            width: 60px;
            height: 60px;
            margin-bottom: 20px;
        }

        .spinner-ring {
            position: absolute;
            width: 100%;
            height: 100%;
            border: 3px solid transparent;
            border-top: 3px solid var(--secondary);
            border-radius: 50%;
            animation: spin 1.2s cubic-bezier(0.68, -0.55, 0.265, 1.55) infinite;
        }

        .spinner-ring:nth-child(2) {
            animation-delay: 0.1s;
            border-top-color: var(--primary);
        }

        .spinner-ring:nth-child(3) {
            animation-delay: 0.2s;
            border-top-color: #4caf50;
        }

        .loading-text {
            font-size: 16px;
            color: var(--dark);
            text-align: center;
            font-weight: 500;
        }

        .loading-progress {
            width: 200px;
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            margin-top: 10px;
            overflow: hidden;
        }

        .loading-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--secondary), var(--primary));
            border-radius: 2px;
            animation: loadingProgress 3s ease-in-out infinite;
        }

        @keyframes loadingProgress {
            0% {
                width: 0%;
            }

            50% {
                width: 70%;
            }

            100% {
                width: 100%;
            }
        }

        /* Overlay for declined state */
        .form-disabled-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            display: none;
        }

        .form-disabled-overlay.active {
            display: block;
        }

        /* Form transition animations */
        .form-grid {
            transition: all 0.3s ease;
        }

        .form-grid>div {
            transition: all 0.2s ease;
        }

        .form-grid>div:hover {
            transform: translateY(-1px);
        }

        input,
        select,
        textarea {
            transition: border-color 0.3s ease, box-shadow 0.3s ease, transform 0.2s ease;
        }

        input:focus,
        select:focus,
        textarea:focus {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(46, 125, 50, 0.15);
        }

        /* Occupation dropdown styling */
        #customOccupationField,
        #customSpouseOccupationField {
            transition: all 0.3s ease;
        }

        #occupation_custom,
        #spouse_occupation_custom {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
            font-size: 14px;
            width: 100%;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        #occupation_custom:focus,
        #spouse_occupation_custom:focus {
            border-color: var(--secondary);
            box-shadow: 0 0 0 2px rgba(46, 125, 50, 0.1);
            outline: none;
        }

        #occupation_custom.error,
        #spouse_occupation_custom.error {
            border-color: #dc3545;
            box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.1);
        }

        #occupation_custom.is-valid,
        #spouse_occupation_custom.is-valid {
            border-color: #28a745;
            box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.1);
        }

        /* Residency dropdown styling - matching occupation field */
        #customResidencyField {
            transition: all 0.3s ease;
        }

        #year_resident_custom {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
            font-size: 14px;
            width: 100%;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        #year_resident_custom:focus {
            border-color: var(--secondary);
            box-shadow: 0 0 0 2px rgba(46, 125, 50, 0.1);
            outline: none;
        }

        #year_resident_custom.error {
            border-color: #dc3545;
            box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.1);
        }

        #year_resident_custom.is-valid {
            border-color: #28a745;
            box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.1);
        }

        .field-help-text {
            font-size: 12px;
            color: #6c757d;
            font-style: italic;
        }
    </style>
</head>

<body>
    <!-- Language Selector -->
    <div class="language-selector">
        <select onchange="changeLanguage(this.value)">
            <option value="en" <?php echo $current_language === 'en' ? 'selected' : ''; ?>>English</option>
            <option value="tl" <?php echo $current_language === 'tl' ? 'selected' : ''; ?>>Tagalog</option>
        </select>
    </div>

    <!-- Accessibility: Skip to main content -->
    <a href="#registrationContainer" class="skip-link">Skip to main content</a>

    <!-- Data Privacy Consent Modal -->
    <div class="modal fade" id="dataPrivacyModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
        aria-labelledby="dataPrivacyModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="dataPrivacyModalLabel">
                        Data Privacy Consent
                    </h5>
                </div>
                <div class="modal-body">
                    <p><strong>Your Privacy Matters to Us</strong></p>
                    <p>We value your privacy and are committed to protecting your personal information in accordance
                        with the <strong>Republic Act No. 10173 (Data Privacy Act of 2012)</strong>.</p>
                    <p>By registering with CYCLOAN, your data will be collected, processed, and stored securely to:</p>
                    <ul>
                        <li>Process your loan applications</li>
                        <li>Verify your identity and financial information</li>
                        <li>Communicate with you regarding your account</li>
                        <li>Comply with legal and regulatory requirements</li>
                    </ul>
                    <p>Please review our <a href="privacy_policy.php" target="_blank">Data Privacy Policy</a> for
                        complete details on how we handle your information, your rights, and how to contact us.</p>
                    <p><strong>By clicking "Accept," you consent to the collection and use of your personal data as
                            described.</strong> You can withdraw consent at any time by contacting us at <a
                            href="mailto:cycloancldd@gmail.com">cycloancldd@gmail.com</a>.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="declineConsent()">
                        <?php echo t('btn_decline'); ?>
                    </button>
                    <button type="button" class="btn btn-primary" onclick="acceptConsent()">
                        <?php echo t('btn_accept'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Voter Registration Requirement Modal -->
    <div class="modal fade" id="voterRequirementModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
        aria-labelledby="voterRequirementModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #dc3545, #c82333); color: white;">
                    <h5 class="modal-title" id="voterRequirementModalLabel">
                        Registration Requirement
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger" role="alert">
                        <h4 class="alert-heading mb-3">
                            Voter Registration is MANDATORY
                        </h4>
                    </div>

                    <p class="lead">To proceed with your <strong>CYCLOAN registration</strong>, you must be a registered
                        voter with the <strong>Commission on Elections (COMELEC)</strong>.</p>

                    <div class="mb-4">
                        <h6 class="mb-3">What You Need:</h6>
                        <ul class="list-unstyled ms-4">
                            <li class="mb-2">Valid Voter's ID or Certificate of Registration</li>
                            <li class="mb-2">Current registration with COMELEC</li>
                        </ul>
                    </div>

                    <div class="mb-4">
                        <h6 class="mb-3">Next Steps:</h6>
                        <ol class="ms-4">
                            <li class="mb-2">Register as a voter with COMELEC if you haven't already</li>
                            <li class="mb-2">Wait for your voter registration to be processed</li>
                            <li class="mb-2">Return to complete this registration</li>
                        </ol>
                    </div>

                    <div class="mb-4 p-3"
                        style="background-color: #f8f9fa; border-left: 4px solid #1b5e20; border-radius: 4px;">
                        <h6 class="mb-3">For More Information:</h6>
                        <ul class="list-unstyled">
                            <li class="mb-2"><strong>Official Website:</strong> <a href="https://www.comelec.gov.ph"
                                    target="_blank">www.comelec.gov.ph</a></li>
                            <li class="mb-2"><strong>Voter Registration:</strong> Visit your nearest COMELEC office or
                                registration center</li>
                            <li><strong>Questions?</strong> Contact your local COMELEC office or call their hotline</li>
                        </ul>
                    </div>

                    <div class="alert alert-info" role="alert">
                        <strong>Once you become a registered voter</strong>, please return and select "Yes" to continue
                        your CYCLOAN registration.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Close
                    </button>
                    <a href="https://www.comelec.gov.ph" target="_blank" class="btn btn-primary">
                        Visit COMELEC Website
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Decline Message Display -->
    <?php if (!$show_form && isset($_SESSION['decline_message'])): ?>
        <div class="container_registration mt-5">
            <div class="alert alert-warning text-center" role="alert">
                <span class="material-icons me-2" style="font-size: 20px; vertical-align: middle;">warning</span>
                <h4 class="alert-heading">Registration Access Denied</h4>
                <p><?php echo htmlspecialchars($_SESSION['decline_message']); ?></p>
                <hr>
                <p class="mb-0">
                    <a href="index.php" class="btn btn-primary">
                        <span class="material-icons me-1" style="font-size: 18px; vertical-align: middle;">home</span>Return
                        to Home
                    </a>
                </p>
            </div>
        </div>
        <?php unset($_SESSION['decline_message']); ?>
    <?php endif; ?>

    <!-- Registration Form Container -->
    <div class="container_registration" id="registrationContainer"
        style="<?php echo !$show_form ? 'display: none;' : ''; ?>">
        <h2><?php echo t('form_title'); ?></h2>

        <!-- Circular Progress Bar -->
        <div class="progress-container">
            <?php
            $steps = [
                t('step_personal_info'),
                t('step_residential'),
                t('step_business')
            ];
            if ($civil_status !== 'Single' && $civil_status !== 'Widowed') {
                $steps[] = t('step_spouse');
            }
            $steps[] = t('step_financial');
            $steps[] = t('step_security');
            foreach ($steps as $index => $step_name):
                $step_number = $index + 1;
                ?>
                <div class="progress-step <?php echo $current_step >= $step_number ? 'active' : ''; ?>">
                    <div class="progress-circle"><?php echo $step_number; ?></div>
                    <span class="progress-label"><?php echo $step_name; ?></span>
                </div>
                <?php if ($index < count($steps) - 1): ?>
                    <div class="progress-line <?php echo $current_step > $step_number ? 'completed' : ''; ?>"></div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <?php
        if (isset($_SESSION['success_message'])) {
            echo '<div class="success-message alert alert-success d-flex align-items-center"><span class="material-icons me-2" style="font-size: 20px; vertical-align: middle;">check_circle</span>' . htmlspecialchars($_SESSION['success_message']) . '</div>';
            unset($_SESSION['success_message']);
        } elseif (isset($_SESSION['error_message'])) {
            echo '<div class="error-message alert alert-danger d-flex align-items-center"><span class="material-icons me-2" style="font-size: 20px; vertical-align: middle;">error_outline</span>' . htmlspecialchars($_SESSION['error_message']) . '</div>';
            unset($_SESSION['error_message']);
        }
        unset($_SESSION['fresh_redirect']);
        ?>

        <!-- Loading Indicator -->
        <div id="loadingIndicator" class="loading-container" style="display: none;">
            <div class="loading-overlay"></div>
            <div class="loading-content">
                <div class="spinner"></div>
                <p>Processing your registration...</p>
            </div>
        </div>

        <form id="registrationForm" action="process_registration.php?step=<?php echo $current_step; ?>" method="POST"
            onsubmit="return validateStep(<?php echo $current_step; ?>)">

            <!-- SECURITY: CSRF Token for form submission protection -->
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <!-- Step 1: Personal Information -->
            <?php if ($current_step == 1): ?>
                <h3><?php echo t('step_personal_info'); ?></h3>
                <div class="form-grid">
                    <div>
                        <label class="required"><?php echo t('label_first_name'); ?></label>
                        <input type="text" name="first_name" id="first_name"
                            value="<?php echo isset($form_data['first_name']) ? htmlspecialchars($form_data['first_name']) : ''; ?>"
                            required placeholder="<?php echo t('placeholder_first_name'); ?>">
                        <div id="firstNameValidationMessage" class="error-message"></div>
                    </div>
                    <div>
                        <label><?php echo t('label_middle_name'); ?></label>
                        <input type="text" name="middle_name"
                            value="<?php echo isset($form_data['middle_name']) ? htmlspecialchars($form_data['middle_name']) : ''; ?>"
                            required placeholder="<?php echo t('placeholder_middle_name'); ?>">
                    </div>
                    <div>
                        <label class="required"><?php echo t('label_last_name'); ?></label>
                        <input type="text" name="last_name" id="last_name"
                            value="<?php echo isset($form_data['last_name']) ? htmlspecialchars($form_data['last_name']) : ''; ?>"
                            required placeholder="<?php echo t('placeholder_last_name'); ?>">
                        <div id="lastNameValidationMessage" class="error-message"></div>
                    </div>
                    <div>
                        <label><?php echo t('label_name_extension'); ?></label>
                        <select name="name_extension">
                            <option value="" <?php echo !isset($form_data['name_extension']) || $form_data['name_extension'] == '' ? 'selected' : ''; ?>><?php echo t('option_none'); ?></option>
                            <option value="Jr." <?php echo isset($form_data['name_extension']) && $form_data['name_extension'] == 'Jr.' ? 'selected' : ''; ?>>Jr.</option>
                            <option value="Sr." <?php echo isset($form_data['name_extension']) && $form_data['name_extension'] == 'Sr.' ? 'selected' : ''; ?>>Sr.</option>
                            <option value="I" <?php echo isset($form_data['name_extension']) && $form_data['name_extension'] == 'I' ? 'selected' : ''; ?>>I</option>
                            <option value="II" <?php echo isset($form_data['name_extension']) && $form_data['name_extension'] == 'II' ? 'selected' : ''; ?>>II</option>
                            <option value="III" <?php echo isset($form_data['name_extension']) && $form_data['name_extension'] == 'III' ? 'selected' : ''; ?>>III</option>
                            <option value="IV" <?php echo isset($form_data['name_extension']) && $form_data['name_extension'] == 'IV' ? 'selected' : ''; ?>>IV</option>
                            <option value="V" <?php echo isset($form_data['name_extension']) && $form_data['name_extension'] == 'V' ? 'selected' : ''; ?>>V</option>
                        </select>
                    </div>
                    <div>
                        <label><?php echo t('label_nickname'); ?></label>
                        <input type="text" name="nick_name"
                            value="<?php echo isset($form_data['nick_name']) ? htmlspecialchars($form_data['nick_name']) : ''; ?>"
                            placeholder="<?php echo t('placeholder_nickname'); ?>">
                    </div>
                    <div>
                        <label class="required"><?php echo t('label_birthday'); ?></label>
                        <input type="date" name="birthday" id="birthday"
                            value="<?php echo isset($form_data['birthday']) ? htmlspecialchars($form_data['birthday']) : ''; ?>"
                            required onchange="calculateAge()">
                        <div id="birthdayValidationMessage" class="error-message"></div>
                    </div>
                    <div>
                        <label class="required"><?php echo t('label_age'); ?></label>
                        <input type="number" name="age" id="age"
                            value="<?php echo isset($form_data['age']) ? htmlspecialchars($form_data['age']) : ''; ?>"
                            required min="0" readonly>
                    </div>
                    <div>
                        <label class="required"><?php echo t('label_birth_place'); ?></label>
                        <input type="text" name="birth_place" id="birth_place"
                            value="<?php echo isset($form_data['birth_place']) ? htmlspecialchars($form_data['birth_place']) : ''; ?>"
                            required placeholder="<?php echo t('placeholder_birth_place'); ?>">
                        <div id="birthPlaceValidationMessage" class="error-message"></div>
                    </div>
                    <div>
                        <label class="required"><?php echo t('label_civil_status'); ?></label>
                        <select name="civil_status" id="civil_status" required>
                            <option value=""><?php echo t('option_select_status'); ?></option>
                            <option value="Single" <?php echo isset($form_data['civil_status']) && $form_data['civil_status'] == 'Single' ? 'selected' : ''; ?>><?php echo t('option_single'); ?></option>
                            <option value="Married" <?php echo isset($form_data['civil_status']) && $form_data['civil_status'] == 'Married' ? 'selected' : ''; ?>><?php echo t('option_married'); ?></option>
                            <option value="Widowed" <?php echo isset($form_data['civil_status']) && $form_data['civil_status'] == 'Widowed' ? 'selected' : ''; ?>><?php echo t('option_widowed'); ?></option>
                            <option value="Separated" <?php echo isset($form_data['civil_status']) && $form_data['civil_status'] == 'Separated' ? 'selected' : ''; ?>><?php echo t('option_separated'); ?></option>
                            <option value="Live In" <?php echo isset($form_data['civil_status']) && $form_data['civil_status'] == 'Live In' ? 'selected' : ''; ?>><?php echo t('option_live_in'); ?></option>
                        </select>
                    </div>
                    <div>
                        <label class="required"><?php echo t('label_contact'); ?></label>
                        <input type="text" name="contact"
                            value="<?php echo isset($form_data['contact']) ? htmlspecialchars($form_data['contact']) : ''; ?>"
                            required pattern="\d{11}" title="<?php echo t('title_phone_validation'); ?>"
                            placeholder="<?php echo t('placeholder_contact'); ?>">
                        <div id="contactValidationMessage" class="error-message"></div>
                    </div>
                    <div>
                        <label class="required"><?php echo t('label_email'); ?></label>
                        <input type="email" name="email" id="email"
                            value="<?php echo isset($form_data['email']) ? htmlspecialchars($form_data['email']) : ''; ?>"
                            required placeholder="<?php echo t('placeholder_email'); ?>" autocomplete="off">
                        <div id="emailValidationMessage" class="error-message"></div>
                    </div>
                    <div>
                        <label class="required"><?php echo t('label_confirm_email'); ?></label>
                        <input type="email" name="confirm_email" id="confirm_email"
                            value="<?php echo isset($form_data['confirm_email']) ? htmlspecialchars($form_data['confirm_email']) : ''; ?>"
                            required placeholder="<?php echo t('placeholder_confirm_email'); ?>" autocomplete="off">
                        <div id="confirmEmailValidationMessage" class="error-message"></div>
                    </div>
                    <div>
                        <label><?php echo t('label_facebook'); ?></label>
                        <input type="text" name="fb_account" id="fb_account"
                            value="<?php echo isset($form_data['fb_account']) ? htmlspecialchars($form_data['fb_account']) : ''; ?>"
                            pattern="^(https?:\/\/(www\.)?facebook\.com\/[a-zA-Z0-9][a-zA-Z0-9\.\-]{3,}[a-zA-Z0-9]\/?(\?.*)?)?$"
                            placeholder="<?php echo t('placeholder_facebook'); ?>">
                        <div id="fbAccountValidationMessage" class="error-message"></div>
                    </div>
                    <div>
                        <label class="required"><?php echo t('label_occupation'); ?></label>
                        <select name="occupation" id="occupation" required>
                            <option value=""><?php echo t('placeholder_occupation'); ?></option>
                        <option value="Teacher" <?php echo isset($form_data['occupation']) && $form_data['occupation'] == 'Teacher' ? 'selected' : ''; ?>>Teacher</option>
                        <option value="Engineer" <?php echo isset($form_data['occupation']) && $form_data['occupation'] == 'Engineer' ? 'selected' : ''; ?>>Engineer</option>
                        <option value="Doctor" <?php echo isset($form_data['occupation']) && $form_data['occupation'] == 'Doctor' ? 'selected' : ''; ?>>Doctor</option>
                        <option value="Nurse" <?php echo isset($form_data['occupation']) && $form_data['occupation'] == 'Nurse' ? 'selected' : ''; ?>>Nurse</option>
                        <option value="Driver" <?php echo isset($form_data['occupation']) && $form_data['occupation'] == 'Driver' ? 'selected' : ''; ?>>Driver</option>
                        <option value="Manager" <?php echo isset($form_data['occupation']) && $form_data['occupation'] == 'Manager' ? 'selected' : ''; ?>>Manager</option>
                        <option value="Supervisor" <?php echo isset($form_data['occupation']) && $form_data['occupation'] == 'Supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                        <option value="Clerk" <?php echo isset($form_data['occupation']) && $form_data['occupation'] == 'Clerk' ? 'selected' : ''; ?>>Clerk</option>
                        <option value="Sales Representative" <?php echo isset($form_data['occupation']) && $form_data['occupation'] == 'Sales Representative' ? 'selected' : ''; ?>>Sales Representative
                        </option>
                        <option value="Mechanic" <?php echo isset($form_data['occupation']) && $form_data['occupation'] == 'Mechanic' ? 'selected' : ''; ?>>Mechanic</option>
                        <option value="Electrician" <?php echo isset($form_data['occupation']) && $form_data['occupation'] == 'Electrician' ? 'selected' : ''; ?>>Electrician</option>
                        <option value="Carpenter" <?php echo isset($form_data['occupation']) && $form_data['occupation'] == 'Carpenter' ? 'selected' : ''; ?>>Carpenter</option>
                        <option value="Cook" <?php echo isset($form_data['occupation']) && $form_data['occupation'] == 'Cook' ? 'selected' : ''; ?>>Cook</option>
                        <option value="Security Guard" <?php echo isset($form_data['occupation']) && $form_data['occupation'] == 'Security Guard' ? 'selected' : ''; ?>>Security Guard</option>
                        <option value="Business Owner" <?php echo isset($form_data['occupation']) && $form_data['occupation'] == 'Business Owner' ? 'selected' : ''; ?>>Business Owner</option>
                        <option value="Farmer" <?php echo isset($form_data['occupation']) && $form_data['occupation'] == 'Farmer' ? 'selected' : ''; ?>>Farmer</option>
                        <option value="Construction Worker" <?php echo isset($form_data['occupation']) && $form_data['occupation'] == 'Construction Worker' ? 'selected' : ''; ?>>Construction Worker
                        </option>
                        <option value="Accountant" <?php echo isset($form_data['occupation']) && $form_data['occupation'] == 'Accountant' ? 'selected' : ''; ?>>Accountant</option>
                        <option value="Cashier" <?php echo isset($form_data['occupation']) && $form_data['occupation'] == 'Cashier' ? 'selected' : ''; ?>>Cashier</option>
                        <option value="Student" <?php echo isset($form_data['occupation']) && $form_data['occupation'] == 'Student' ? 'selected' : ''; ?>>Student</option>
                        <option value="Housewife/Househusband" <?php echo isset($form_data['occupation']) && $form_data['occupation'] == 'Housewife/Househusband' ? 'selected' : ''; ?>>
                            Housewife/Househusband</option>
                        <option value="Retired" <?php echo isset($form_data['occupation']) && $form_data['occupation'] == 'Retired' ? 'selected' : ''; ?>>Retired</option>
                        <option value="Unemployed" <?php echo isset($form_data['occupation']) && $form_data['occupation'] == 'Unemployed' ? 'selected' : ''; ?>>Unemployed</option>
                        <option value="custom" <?php echo isset($form_data['occupation']) && !in_array($form_data['occupation'], ['Teacher', 'Engineer', 'Doctor', 'Nurse', 'Driver', 'Manager', 'Supervisor', 'Clerk', 'Sales Representative', 'Mechanic', 'Electrician', 'Carpenter', 'Cook', 'Security Guard', 'Business Owner', 'Farmer', 'Construction Worker', 'Accountant', 'Cashier', 'Student', 'Housewife/Househusband', 'Retired', 'Unemployed']) && $form_data['occupation'] != '' ? 'selected' : ''; ?>><?php echo t('option_other_manual'); ?></option>
                        </select>
                        <div id="occupationValidationMessage" class="error-message"></div>
                        <div id="customOccupationField" style="display: none; margin-top: 10px;">
                            <input type="text" id="occupation_custom" placeholder="<?php echo t('placeholder_specify_occupation'); ?>"
                                value="<?php echo isset($form_data['occupation']) && !in_array($form_data['occupation'], ['Teacher', 'Engineer', 'Doctor', 'Nurse', 'Driver', 'Manager', 'Supervisor', 'Clerk', 'Sales Representative', 'Mechanic', 'Electrician', 'Carpenter', 'Cook', 'Security Guard', 'Business Owner', 'Farmer', 'Construction Worker', 'Accountant', 'Cashier', 'Student', 'Housewife/Househusband', 'Retired', 'Unemployed']) && $form_data['occupation'] != '' ? htmlspecialchars($form_data['occupation']) : ''; ?>">
                            <div class="field-help-text" style="margin-top: 5px;">
                                <?php echo t('help_enter_specific_occupation'); ?>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label><?php echo t('label_reg_voter'); ?></label>
                        <select name="reg_voter">
                            <option value=""><?php echo t('option_select_option'); ?></option>
                            <option value="yes_calamba" <?php echo isset($form_data['reg_voter']) && $form_data['reg_voter'] == 'yes_calamba' ? 'selected' : ''; ?>><?php echo t('option_yes_calamba'); ?></option>
                            <option value="yes_not_calamba" <?php echo isset($form_data['reg_voter']) && $form_data['reg_voter'] == 'yes_not_calamba' ? 'selected' : ''; ?>><?php echo t('option_yes_not_calamba'); ?></option>
                            <option value="no" <?php echo isset($form_data['reg_voter']) && $form_data['reg_voter'] == 'no' ? 'selected' : ''; ?>><?php echo t('option_no'); ?></option>
                        </select>
                    </div>
                    <div>
                        <label class="required"><?php echo t('label_residency_length'); ?></label>
                        <select name="year_resident" id="year_resident" required>
                            <option value=""><?php echo t('option_select_duration'); ?></option>
                            <option value="6m" <?php echo isset($form_data['year_resident']) && $form_data['year_resident'] == '6m' ? 'selected' : ''; ?>><?php echo t('option_6_months'); ?></option>
                            <option value="1y" <?php echo isset($form_data['year_resident']) && $form_data['year_resident'] == '1y' ? 'selected' : ''; ?>><?php echo t('option_1_year'); ?></option>
                            <option value="1y6m" <?php echo isset($form_data['year_resident']) && $form_data['year_resident'] == '1y6m' ? 'selected' : ''; ?>><?php echo t('option_1y6m'); ?></option>
                            <option value="2y" <?php echo isset($form_data['year_resident']) && $form_data['year_resident'] == '2y' ? 'selected' : ''; ?>><?php echo t('option_2_years'); ?></option>
                            <option value="3y" <?php echo isset($form_data['year_resident']) && $form_data['year_resident'] == '3y' ? 'selected' : ''; ?>><?php echo t('option_3_years'); ?></option>
                            <option value="4y" <?php echo isset($form_data['year_resident']) && $form_data['year_resident'] == '4y' ? 'selected' : ''; ?>><?php echo t('option_4_years'); ?></option>
                            <option value="5y" <?php echo isset($form_data['year_resident']) && $form_data['year_resident'] == '5y' ? 'selected' : ''; ?>><?php echo t('option_5_years'); ?></option>
                            <option value="10y" <?php echo isset($form_data['year_resident']) && $form_data['year_resident'] == '10y' ? 'selected' : ''; ?>><?php echo t('option_10_years'); ?></option>
                            <option value="custom" <?php echo isset($form_data['year_resident']) && !in_array($form_data['year_resident'], ['6m', '1y', '1y6m', '2y', '3y', '4y', '5y', '10y']) && $form_data['year_resident'] != '' ? 'selected' : ''; ?>><?php echo t('option_other_manual'); ?></option>
                        </select>
                        <div id="yearResidentValidationMessage" class="error-message"></div>
                        <div id="customResidencyField" style="display: none; margin-top: 10px;">
                            <input type="text" id="year_resident_custom" placeholder="<?php echo t('placeholder_residency_format'); ?>"
                                value="<?php echo isset($form_data['year_resident']) && !in_array($form_data['year_resident'], ['6m', '1y', '1y6m', '2y', '3y', '4y', '5y', '10y']) && $form_data['year_resident'] != '' ? htmlspecialchars($form_data['year_resident']) : ''; ?>">
                            <div id="customResidencyMessage" class="field-help-text" style="margin-top: 5px;">
                                <?php echo t('help_residency_format'); ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Step 2: Residential Address -->
            <?php if ($current_step == 2): ?>
                <h3>Residential Address (Calamba City)</h3>
                <div class="form-grid address-grid">
                    <div>
                        <label><?php echo t('label_house_unit'); ?></label>
                        <input type="text" name="res_house_no"
                            value="<?php echo isset($form_data['res_house_no']) ? htmlspecialchars($form_data['res_house_no']) : ''; ?>"
                            placeholder="<?php echo t('placeholder_house_unit'); ?>" <?php echo !$show_form ? 'disabled' : ''; ?>>
                    </div>
                    <div>
                        <label><?php echo t('label_street_block'); ?></label>
                        <input type="text" name="res_street"
                            value="<?php echo isset($form_data['res_street']) ? htmlspecialchars($form_data['res_street']) : ''; ?>"
                            placeholder="<?php echo t('placeholder_street_block'); ?>" <?php echo !$show_form ? 'disabled' : ''; ?>>
                    </div>
                    <div>
                        <label><?php echo t('label_subdivision'); ?></label>
                        <input type="text" name="res_subdivision"
                            value="<?php echo isset($form_data['res_subdivision']) ? htmlspecialchars($form_data['res_subdivision']) : ''; ?>"
                            placeholder="<?php echo t('placeholder_subdivision'); ?>" <?php echo !$show_form ? 'disabled' : ''; ?>>
                    </div>
                    <div>
                        <label class="required"><?php echo t('label_barangay'); ?></label>
                        <select id="res_barangay_select" name="res_barangay" required
                            data-selected="<?php echo isset($form_data['res_barangay']) ? htmlspecialchars($form_data['res_barangay']) : ''; ?>"
                            <?php echo !$show_form ? 'disabled' : ''; ?>>
                            <option value="">Loading barangays...</option>
                        </select>
                    </div>
                    <div>
                        <label class="required"><?php echo t('label_house_ownership'); ?></label>
                        <select name="house_ownership" required <?php echo !$show_form ? 'disabled' : ''; ?>>
                            <option value=""><?php echo t('option_select_ownership'); ?></option>
                            <option value="Owned" <?php echo isset($form_data['house_ownership']) && $form_data['house_ownership'] == 'Owned' ? 'selected' : ''; ?>><?php echo t('option_owned'); ?></option>
                            <option value="Rented" <?php echo isset($form_data['house_ownership']) && $form_data['house_ownership'] == 'Rented' ? 'selected' : ''; ?>><?php echo t('option_rented'); ?></option>
                            <option value="Living with Parents/Relatives" <?php echo isset($form_data['house_ownership']) && $form_data['house_ownership'] == 'Living with Parents/Relatives' ? 'selected' : ''; ?>><?php echo t('option_living_with_parents'); ?></option>
                            <option value="Mortgaged" <?php echo isset($form_data['house_ownership']) && $form_data['house_ownership'] == 'Mortgaged' ? 'selected' : ''; ?>><?php echo t('option_mortgaged'); ?></option>
                        </select>
                    </div>
                    <div class="full-width">
                        <label class="required"><?php echo t('label_complete_address'); ?></label>
                        <input type="text" name="res_address"
                            value="<?php echo isset($form_data['res_address']) ? htmlspecialchars($form_data['res_address']) : ''; ?>"
                            required readonly <?php echo !$show_form ? 'disabled' : ''; ?>>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Step 3: Business Address -->
            <?php if ($current_step == 3): ?>
                <h3>Business Address (Calamba City)</h3>
                <div class="form-grid address-grid">
                    <div>
                        <label><?php echo t('label_building_unit'); ?></label>
                        <input type="text" name="bus_bldg_no"
                            value="<?php echo isset($form_data['bus_bldg_no']) ? htmlspecialchars($form_data['bus_bldg_no']) : ''; ?>"
                            placeholder="<?php echo t('placeholder_building_unit'); ?>" <?php echo !$show_form ? 'disabled' : ''; ?>>
                    </div>
                    <div>
                        <label><?php echo t('label_street_block'); ?></label>
                        <input type="text" name="bus_street"
                            value="<?php echo isset($form_data['bus_street']) ? htmlspecialchars($form_data['bus_street']) : ''; ?>"
                            placeholder="<?php echo t('placeholder_street_block'); ?>" <?php echo !$show_form ? 'disabled' : ''; ?>>
                    </div>
                    <div>
                        <label><?php echo t('label_subdivision'); ?></label>
                        <input type="text" name="bus_subdivision"
                            value="<?php echo isset($form_data['bus_subdivision']) ? htmlspecialchars($form_data['bus_subdivision']) : ''; ?>"
                            placeholder="<?php echo t('placeholder_subdivision'); ?>" <?php echo !$show_form ? 'disabled' : ''; ?>>
                    </div>
                    <div>
                        <label class="required"><?php echo t('label_barangay'); ?></label>
                        <select id="bus_barangay_select" name="bus_barangay" required
                            data-selected="<?php echo isset($form_data['bus_barangay']) ? htmlspecialchars($form_data['bus_barangay']) : ''; ?>"
                            <?php echo !$show_form ? 'disabled' : ''; ?>>
                            <option value="">Loading barangays...</option>
                        </select>
                    </div>
                    <div class="full-width">
                        <label><?php echo t('label_business_address'); ?></label>
                        <input type="text" name="bus_address"
                            value="<?php echo isset($form_data['bus_address']) ? htmlspecialchars($form_data['bus_address']) : ''; ?>"
                            readonly <?php echo !$show_form ? 'disabled' : ''; ?>>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($current_step == 4 && $civil_status !== 'Single' && $civil_status !== 'Widowed'): ?>
                <h3><?php echo t('step_spouse'); ?></h3>
                <div class="form-grid" id="spouse_section">
                    <div>
                        <label class="spouse-required"><?php echo t('label_first_name'); ?></label>
                        <input type="text" name="spouse_first_name" id="spouse_first_name" class="spouse-required"
                            value="<?php echo isset($form_data['spouse_first_name']) ? htmlspecialchars($form_data['spouse_first_name']) : ''; ?>"
                            placeholder="<?php echo t('placeholder_spouse_first_name'); ?>" <?php echo !$show_form ? 'disabled' : ''; ?>>
                        <div id="spouseFirstNameValidationMessage" class="error-message"></div>
                    </div>
                    <div>
                        <label><?php echo t('label_middle_name'); ?></label>
                        <input type="text" name="spouse_middle_name"
                            value="<?php echo isset($form_data['spouse_middle_name']) ? htmlspecialchars($form_data['spouse_middle_name']) : ''; ?>"
                            placeholder="<?php echo t('placeholder_spouse_middle_name'); ?>" <?php echo !$show_form ? 'disabled' : ''; ?>>
                    </div>
                    <div>
                        <label class="spouse-required"><?php echo t('label_last_name'); ?></label>
                        <input type="text" name="spouse_last_name" id="spouse_last_name" class="spouse-required"
                            value="<?php echo isset($form_data['spouse_last_name']) ? htmlspecialchars($form_data['spouse_last_name']) : ''; ?>"
                            placeholder="<?php echo t('placeholder_spouse_last_name'); ?>" <?php echo !$show_form ? 'disabled' : ''; ?>>
                        <div id="spouseLastNameValidationMessage" class="error-message"></div>

                    </div>
                    <div>
                        <label><?php echo t('label_name_extension'); ?></label>
                        <select name="spouse_name_extension" <?php echo !$show_form ? 'disabled' : ''; ?>>
                            <option value=""><?php echo t('placeholder_extension'); ?></option>
                            <option value="Jr." <?php echo isset($form_data['spouse_name_extension']) && $form_data['spouse_name_extension'] == 'Jr.' ? 'selected' : ''; ?>>Jr.</option>
                            <option value="Sr." <?php echo isset($form_data['spouse_name_extension']) && $form_data['spouse_name_extension'] == 'Sr.' ? 'selected' : ''; ?>>Sr.</option>
                            <option value="I" <?php echo isset($form_data['spouse_name_extension']) && $form_data['spouse_name_extension'] == 'I' ? 'selected' : ''; ?>>I</option>
                            <option value="II" <?php echo isset($form_data['spouse_name_extension']) && $form_data['spouse_name_extension'] == 'II' ? 'selected' : ''; ?>>II</option>
                            <option value="III" <?php echo isset($form_data['spouse_name_extension']) && $form_data['spouse_name_extension'] == 'III' ? 'selected' : ''; ?>>III</option>
                            <option value="IV" <?php echo isset($form_data['spouse_name_extension']) && $form_data['spouse_name_extension'] == 'IV' ? 'selected' : ''; ?>>IV</option>
                            <option value="V" <?php echo isset($form_data['spouse_name_extension']) && $form_data['spouse_name_extension'] == 'V' ? 'selected' : ''; ?>>V</option>
                        </select>
                    </div>
                    <div>
                        <label><?php echo t('label_nick_name'); ?></label>
                        <input type="text" name="spouse_nick_name"
                            value="<?php echo isset($form_data['spouse_nick_name']) ? htmlspecialchars($form_data['spouse_nick_name']) : ''; ?>"
                            placeholder="<?php echo t('placeholder_spouse_nickname'); ?>" <?php echo !$show_form ? 'disabled' : ''; ?>>
                    </div>
                    <div>
                        <label><?php echo t('label_registered_voter'); ?></label>
                        <select name="spouse_reg_voter" <?php echo !$show_form ? 'disabled' : ''; ?>>
                            <option value="Yes" <?php echo isset($form_data['spouse_reg_voter']) && $form_data['spouse_reg_voter'] == 'Yes' ? 'selected' : ''; ?>><?php echo t('option_yes'); ?></option>
                            <option value="No" <?php echo isset($form_data['spouse_reg_voter']) && $form_data['spouse_reg_voter'] == 'No' ? 'selected' : ''; ?>><?php echo t('option_no'); ?></option>
                        </select>
                    </div>
                    <div>
                        <label class="spouse-required"><?php echo t('label_birthday'); ?></label>
                        <input type="date" name="spouse_birthday" id="spouse_birthday"
                            value="<?php echo isset($form_data['spouse_birthday']) ? htmlspecialchars($form_data['spouse_birthday']) : ''; ?>"
                            class="spouse-required" <?php echo !$show_form ? 'disabled' : ''; ?>>
                        <div id="spouseBirthdayValidationMessage" class="error-message"></div>
                        <div class="field-help-text">Enter a valid date (DD/MM/YYYY format)</div>
                    </div>
                    <div>
                        <label class="spouse-required"><?php echo t('label_age'); ?></label>
                        <input type="number" name="spouse_age" id="spouse_age" min="0" readonly class="spouse-required"
                            value="<?php echo isset($form_data['spouse_age']) ? htmlspecialchars($form_data['spouse_age']) : ''; ?>"
                            <?php echo !$show_form ? 'disabled' : ''; ?>>
                    </div>
                    <div>
                        <label class="spouse-required"><?php echo t('label_occupation'); ?></label>
                        <select name="spouse_occupation" id="spouse_occupation" class="spouse-required" <?php echo !$show_form ? 'disabled' : ''; ?>>
                            <option value="">Select spouse's occupation</option>
                            <option value="Teacher" <?php echo isset($form_data['spouse_occupation']) && $form_data['spouse_occupation'] == 'Teacher' ? 'selected' : ''; ?>>Teacher</option>
                            <option value="Engineer" <?php echo isset($form_data['spouse_occupation']) && $form_data['spouse_occupation'] == 'Engineer' ? 'selected' : ''; ?>>Engineer</option>
                            <option value="Doctor" <?php echo isset($form_data['spouse_occupation']) && $form_data['spouse_occupation'] == 'Doctor' ? 'selected' : ''; ?>>Doctor</option>
                            <option value="Nurse" <?php echo isset($form_data['spouse_occupation']) && $form_data['spouse_occupation'] == 'Nurse' ? 'selected' : ''; ?>>Nurse</option>
                            <option value="Driver" <?php echo isset($form_data['spouse_occupation']) && $form_data['spouse_occupation'] == 'Driver' ? 'selected' : ''; ?>>Driver</option>
                            <option value="Manager" <?php echo isset($form_data['spouse_occupation']) && $form_data['spouse_occupation'] == 'Manager' ? 'selected' : ''; ?>>Manager</option>
                            <option value="Supervisor" <?php echo isset($form_data['spouse_occupation']) && $form_data['spouse_occupation'] == 'Supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                            <option value="Clerk" <?php echo isset($form_data['spouse_occupation']) && $form_data['spouse_occupation'] == 'Clerk' ? 'selected' : ''; ?>>Clerk</option>
                            <option value="Sales Representative" <?php echo isset($form_data['spouse_occupation']) && $form_data['spouse_occupation'] == 'Sales Representative' ? 'selected' : ''; ?>>Sales
                                Representative</option>
                            <option value="Mechanic" <?php echo isset($form_data['spouse_occupation']) && $form_data['spouse_occupation'] == 'Mechanic' ? 'selected' : ''; ?>>Mechanic</option>
                            <option value="Electrician" <?php echo isset($form_data['spouse_occupation']) && $form_data['spouse_occupation'] == 'Electrician' ? 'selected' : ''; ?>>Electrician</option>
                            <option value="Carpenter" <?php echo isset($form_data['spouse_occupation']) && $form_data['spouse_occupation'] == 'Carpenter' ? 'selected' : ''; ?>>Carpenter</option>
                            <option value="Cook" <?php echo isset($form_data['spouse_occupation']) && $form_data['spouse_occupation'] == 'Cook' ? 'selected' : ''; ?>>Cook</option>
                            <option value="Security Guard" <?php echo isset($form_data['spouse_occupation']) && $form_data['spouse_occupation'] == 'Security Guard' ? 'selected' : ''; ?>>Security Guard
                            </option>
                            <option value="Business Owner" <?php echo isset($form_data['spouse_occupation']) && $form_data['spouse_occupation'] == 'Business Owner' ? 'selected' : ''; ?>>Business Owner
                            </option>
                            <option value="Farmer" <?php echo isset($form_data['spouse_occupation']) && $form_data['spouse_occupation'] == 'Farmer' ? 'selected' : ''; ?>>Farmer</option>
                            <option value="Construction Worker" <?php echo isset($form_data['spouse_occupation']) && $form_data['spouse_occupation'] == 'Construction Worker' ? 'selected' : ''; ?>>Construction
                                Worker</option>
                            <option value="Accountant" <?php echo isset($form_data['spouse_occupation']) && $form_data['spouse_occupation'] == 'Accountant' ? 'selected' : ''; ?>>Accountant</option>
                            <option value="Cashier" <?php echo isset($form_data['spouse_occupation']) && $form_data['spouse_occupation'] == 'Cashier' ? 'selected' : ''; ?>>Cashier</option>
                            <option value="Student" <?php echo isset($form_data['spouse_occupation']) && $form_data['spouse_occupation'] == 'Student' ? 'selected' : ''; ?>>Student</option>
                            <option value="Housewife/Househusband" <?php echo isset($form_data['spouse_occupation']) && $form_data['spouse_occupation'] == 'Housewife/Househusband' ? 'selected' : ''; ?>>
                                Housewife/Househusband</option>
                            <option value="Retired" <?php echo isset($form_data['spouse_occupation']) && $form_data['spouse_occupation'] == 'Retired' ? 'selected' : ''; ?>>Retired</option>
                            <option value="Unemployed" <?php echo isset($form_data['spouse_occupation']) && $form_data['spouse_occupation'] == 'Unemployed' ? 'selected' : ''; ?>>Unemployed</option>
                            <option value="custom" <?php echo isset($form_data['spouse_occupation']) && !in_array($form_data['spouse_occupation'], ['Teacher', 'Engineer', 'Doctor', 'Nurse', 'Driver', 'Manager', 'Supervisor', 'Clerk', 'Sales Representative', 'Mechanic', 'Electrician', 'Carpenter', 'Cook', 'Security Guard', 'Business Owner', 'Farmer', 'Construction Worker', 'Accountant', 'Cashier', 'Student', 'Housewife/Househusband', 'Retired', 'Unemployed']) && $form_data['spouse_occupation'] != '' ? 'selected' : ''; ?>>
                                Other (enter manually)</option>
                        </select>
                        <div id="spouseOccupationValidationMessage" class="error-message"></div>
                        <div id="customSpouseOccupationField" style="display: none; margin-top: 10px;">
                            <input type="text" id="spouse_occupation_custom"
                                placeholder="Please specify spouse's occupation"
                                value="<?php echo isset($form_data['spouse_occupation']) && !in_array($form_data['spouse_occupation'], ['Teacher', 'Engineer', 'Doctor', 'Nurse', 'Driver', 'Manager', 'Supervisor', 'Clerk', 'Sales Representative', 'Mechanic', 'Electrician', 'Carpenter', 'Cook', 'Security Guard', 'Business Owner', 'Farmer', 'Construction Worker', 'Accountant', 'Cashier', 'Student', 'Housewife/Househusband', 'Retired', 'Unemployed']) && $form_data['spouse_occupation'] != '' ? htmlspecialchars($form_data['spouse_occupation']) : ''; ?>"
                                <?php echo !$show_form ? 'disabled' : ''; ?>>
                            <div class="field-help-text" style="margin-top: 5px;">
                                Please enter spouse's specific occupation
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="spouse-required"><?php echo t('label_num_dependents'); ?></label>
                        <input type="number" name="spouse_dependents" min="0" id="spouse_dependents" class="spouse-required"
                            value="<?php echo isset($form_data['spouse_dependents']) ? htmlspecialchars($form_data['spouse_dependents']) : ''; ?>"
                            placeholder="Enter number of dependents" <?php echo !$show_form ? 'disabled' : ''; ?>>
                        <div id="spouseDependentsValidationMessage" class="error-message"></div>
                        <div class="field-help-text">0 or more (e.g., 0, 1, 2, 3)</div>
                    </div>
                    <div>
                        <label class="spouse-required"><?php echo t('label_birth_place'); ?></label>
                        <input type="text" name="spouse_birth_place" id="spouse_birth_place" class="spouse-required"
                            value="<?php echo isset($form_data['spouse_birth_place']) ? htmlspecialchars($form_data['spouse_birth_place']) : ''; ?>"
                            placeholder="Enter spouse's birth place" <?php echo !$show_form ? 'disabled' : ''; ?>>
                        <div id="spouseBirthPlaceValidationMessage" class="error-message"></div>
                        <div class="field-help-text">City, Province (e.g., Manila, NCR)</div>
                    </div>
                    <div>
                        <label><?php echo t('label_contact'); ?></label>
                        <input type="text" name="spouse_contact" id="spouse_contact"
                            value="<?php echo isset($form_data['spouse_contact']) ? htmlspecialchars($form_data['spouse_contact']) : ''; ?>"
                            placeholder="Enter 11-digit phone number" <?php echo !$show_form ? 'disabled' : ''; ?>>
                        <div id="spouseContactValidationMessage" class="error-message"></div>
                    </div>
                    <div>
                        <label><?php echo t('label_email'); ?></label>
                        <input type="email" name="spouse_email" id="spouse_email"
                            value="<?php echo isset($form_data['spouse_email']) ? htmlspecialchars($form_data['spouse_email']) : ''; ?>"
                            placeholder="spouse@example.com" <?php echo !$show_form ? 'disabled' : ''; ?>>
                        <div id="spouseEmailValidationMessage" class="error-message"></div>
                        <div class="field-help-text">Valid email address (optional)</div>
                    </div>
                    <div>
                        <label><?php echo t('label_facebook'); ?></label>
                        <input type="text" name="spouse_fb_account" id="spouse_fb_account"
                            value="<?php echo isset($form_data['spouse_fb_account']) ? htmlspecialchars($form_data['spouse_fb_account']) : ''; ?>"
                            placeholder="https://www.facebook.com/username or username" <?php echo !$show_form ? 'disabled' : ''; ?>>
                        <div id="spouseFbAccountValidationMessage" class="error-message"></div>
                        <div class="field-help-text">Facebook URL or username (optional)</div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Step 5 (or 4 if no spouse): Financial Information -->
            <?php if ($current_step == (($civil_status === 'Single' || $civil_status === 'Widowed') ? 4 : 5)): ?>
                <h3><?php echo t('step_financial'); ?></h3>
                <div class="financial-section">
                    <h4><?php echo t('label_monthly_income'); ?></h4>
                    <div class="financial-table income-table">
                        <?php
                        $income_sources = [
                            ['id' => 'income_business', 'name' => 'business', 'label' => 'Business Income (₱)', 'placeholder' => 'Enter business income'],
                            ['id' => 'income_salary', 'name' => 'salary', 'label' => 'Salary Income (₱)', 'placeholder' => 'Enter salary income'],
                            ['id' => 'income_remittance', 'name' => 'remittance', 'label' => 'Remittance (₱)', 'placeholder' => 'Enter remittance amount'],
                            ['id' => 'income_other', 'name' => 'other_income', 'label' => 'Other Income (₱)', 'placeholder' => 'Enter other income'],
                            ['id' => 'income_business2', 'name' => 'business2', 'label' => 'Secondary Business Income (₱)', 'placeholder' => 'Enter secondary business income'],
                            ['id' => 'income_salary2', 'name' => 'salary2', 'label' => 'Secondary Salary Income (₱)', 'placeholder' => 'Enter secondary salary income'],
                        ];
                        foreach ($income_sources as $source): ?>
                            <div class="financial-row">
                                <div class="checkbox-item">
                                    <input type="checkbox" id="<?php echo $source['id']; ?>" name="income_sources[]"
                                        value="<?php echo $source['name']; ?>" class="income-source" <?php echo isset($form_data['income_sources']) && in_array($source['name'], $form_data['income_sources']) ? 'checked' : ''; ?>         <?php echo !$show_form ? 'disabled' : ''; ?>>
                                    <label for="<?php echo $source['id']; ?>"><?php echo $source['label']; ?></label>
                                </div>
                                <div id="<?php echo $source['id']; ?>_field"
                                    class="financial-input <?php echo isset($form_data['income_sources']) && in_array($source['name'], $form_data['income_sources']) ? '' : 'hidden'; ?>">
                                    <input type="text" name="<?php echo $source['name']; ?>" min="100" step="0.01"
                                        class="income-amount" data-source="<?php echo $source['name']; ?>"
                                        value="<?php echo isset($form_data[$source['name']]) ? htmlspecialchars(number_format($form_data[$source['name']], 2)) : ''; ?>"
                                        placeholder="<?php echo $source['placeholder']; ?>" <?php echo !$show_form ? 'disabled' : ''; ?>>
                                </div>
                                <div class="error-message" id="<?php echo $source['name']; ?>-error"></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="total-section">
                        <label><strong>Total Monthly Income (₱)</strong></label>
                        <input type="text" name="net_income" readonly
                            value="<?php echo isset($form_data['net_income']) ? htmlspecialchars(number_format($form_data['net_income'], 2)) : ''; ?>"
                            <?php echo !$show_form ? 'disabled' : ''; ?>>
                    </div>
                </div>

                <div class="financial-section">
                    <h4><?php echo t('label_monthly_expenses'); ?></h4>
                    <div class="financial-table expenditure-table">
                        <?php
                        $expenditures = [
                            ['id' => 'exp_food', 'name' => 'food_allowance', 'label' => 'Food Allowance (₱)', 'placeholder' => 'Enter food allowance'],
                            ['id' => 'exp_electricity', 'name' => 'electricity_bill', 'label' => 'Electricity Bill (₱)', 'placeholder' => 'Enter electricity bill'],
                            ['id' => 'exp_water', 'name' => 'water_bill', 'label' => 'Water Bill (₱)', 'placeholder' => 'Enter water bill'],
                            ['id' => 'exp_internet', 'name' => 'internet_bill', 'label' => 'Internet Bill (₱)', 'placeholder' => 'Enter internet bill'],
                            ['id' => 'exp_gas', 'name' => 'gas_bill', 'label' => 'Gas Bill (₱)', 'placeholder' => 'Enter gas bill'],
                            ['id' => 'exp_education', 'name' => 'educational_allowance', 'label' => 'Educational Allowance (₱)', 'placeholder' => 'Enter educational allowance'],
                            ['id' => 'exp_car', 'name' => 'car_amortization', 'label' => 'Car Amortization (₱)', 'placeholder' => 'Enter car amortization'],
                            ['id' => 'exp_insurance', 'name' => 'insurance', 'label' => 'Insurance (₱)', 'placeholder' => 'Enter insurance amount'],
                            ['id' => 'exp_other', 'name' => 'other_expense', 'label' => 'Other Expenses (₱)', 'placeholder' => 'Enter other expenses'],
                        ];
                        foreach ($expenditures as $exp): ?>
                            <div class="financial-row">
                                <div class="checkbox-item">
                                    <input type="checkbox" id="<?php echo $exp['id']; ?>" name="expenditure_types[]"
                                        value="<?php echo $exp['name']; ?>" class="expenditure-type" <?php echo isset($form_data['expenditure_types']) && in_array($exp['name'], $form_data['expenditure_types']) ? 'checked' : ''; ?>         <?php echo !$show_form ? 'disabled' : ''; ?>>
                                    <label for="<?php echo $exp['id']; ?>"><?php echo $exp['label']; ?></label>
                                </div>
                                <div id="<?php echo $exp['id']; ?>_field"
                                    class="financial-input <?php echo isset($form_data['expenditure_types']) && in_array($exp['name'], $form_data['expenditure_types']) ? '' : 'hidden'; ?>">
                                    <input type="text" name="<?php echo $exp['name']; ?>" min="500" step="0.01"
                                        class="expenditure-amount" data-type="<?php echo $exp['name']; ?>"
                                        value="<?php echo isset($form_data[$exp['name']]) ? htmlspecialchars(number_format($form_data[$exp['name']], 2)) : ''; ?>"
                                        placeholder="<?php echo $exp['placeholder']; ?>" <?php echo !$show_form ? 'disabled' : ''; ?>>
                                </div>
                                <div class="error-message" id="<?php echo $exp['name']; ?>-error"></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="total-section">
                        <label><strong>Total Monthly Expenditures (₱)</strong></label>
                        <input type="text" name="expenditures" readonly
                            value="<?php echo isset($form_data['expenditures']) ? htmlspecialchars(number_format($form_data['expenditures'], 2)) : ''; ?>"
                            <?php echo !$show_form ? 'disabled' : ''; ?>>
                    </div>
                </div>

                <div class="financial-summary">
                    <div class="form-grid">
                        <div>
                            <label><strong>Expected Monthly Amortization (₱)</strong></label>
                            <input type="text" name="expected_monthly_amortization" min="0" step="0.01"
                                value="<?php echo isset($form_data['expected_monthly_amortization']) ? htmlspecialchars(number_format($form_data['expected_monthly_amortization'], 2)) : '0.00'; ?>"
                                placeholder="Enter expected amortization" <?php echo !$show_form ? 'disabled' : ''; ?>>
                        </div>
                        <div>
                            <label><strong>Remaining Monthly Income (₱)</strong></label>
                            <input type="text" name="remaining_income" readonly
                                value="<?php echo isset($form_data['remaining_income']) ? htmlspecialchars(number_format($form_data['remaining_income'], 2)) : ''; ?>"
                                <?php echo !$show_form ? 'disabled' : ''; ?>>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Step 6 (or 5 if no spouse): Account Security -->
            <?php if ($current_step == (($civil_status === 'Single' || $civil_status === 'Widowed') ? 5 : 6)): ?>
                <h3><?php echo t('step_security'); ?></h3>
                <div class="form-grid">
                    <div>
                        <label class="required">
                            Password
                        </label>
                        <div class="password-input-wrapper">
                            <input type="password" name="password" id="password" required
                                oninput="validatePassword(this.value)" placeholder="Enter your password" <?php echo !$show_form ? 'disabled' : ''; ?>>
                            <button type="button" class="toggle-password-btn" onclick="togglePassword('password')"
                                style="display:none;">
                                <i class="fas fa-eye" id="toggleIcon1"></i>
                            </button>
                        </div>
                        <div class="password-strength">
                            <div class="strength-bar">
                                <div class="strength-bar-fill" id="strengthBar"></div>
                            </div>
                            <span class="strength-text" id="strengthText">Password strength: Weak</span>
                        </div>
                    </div>
                    <div>
                        <label class="required">
                            Confirm Password
                        </label>
                        <div class="password-input-wrapper">
                            <input type="password" name="confirm_password" id="confirm_password" required
                                oninput="validatePasswordMatch()" placeholder="Confirm your password" <?php echo !$show_form ? 'disabled' : ''; ?>>
                            <button type="button" class="toggle-password-btn" onclick="togglePassword('confirm_password')"
                                style="display:none;">
                                <i class="fas fa-eye" id="toggleIcon2"></i>
                            </button>
                        </div>
                        <div id="passwordMatchMessage" class="error-message"></div>
                    </div>
                </div>
                <!-- Password Requirements Checklist -->
                <div class="requirement-checklist">
                    <div style="font-weight: 600; margin-bottom: 10px; color: #333; font-size: 13px;">Password Requirements:
                    </div>
                    <div class="requirement" id="req-length">
                        <span class="requirement-icon"><i class="fas fa-times"></i></span>
                        <span>At least 8 characters</span>
                    </div>
                    <div class="requirement" id="req-uppercase">
                        <span class="requirement-icon"><i class="fas fa-times"></i></span>
                        <span>At least one uppercase letter (A-Z)</span>
                    </div>
                    <div class="requirement" id="req-lowercase">
                        <span class="requirement-icon"><i class="fas fa-times"></i></span>
                        <span>At least one lowercase letter (a-z)</span>
                    </div>
                    <div class="requirement" id="req-number">
                        <span class="requirement-icon"><i class="fas fa-times"></i></span>
                        <span>At least one number (0-9)</span>
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
                <!-- Data Privacy Consent -->
                <div class="form-group data-privacy">
                    <label class="required">
                        <input type="checkbox" name="data_privacy_consent" id="data_privacy_consent" required <?php echo !$show_form ? 'disabled' : ''; ?>>
                        I agree to the <a href="privacy_policy.php" target="_blank">Data Privacy Policy</a> and consent to
                        the collection, processing, and storage of my personal data as described therein.
                    </label>
                    <div id="dataPrivacyValidationMessage" class="error-message"></div>
                </div>
            <?php endif; ?>
            <!-- Navigation Buttons -->
            <div class="button-container">
                <?php if ($current_step > 1): ?>
                    <a class="btn btn-secondary"
                        href="registration.php?step=<?php echo $current_step - 1; ?>"><?php echo t('btn_previous'); ?></a>
                <?php else: ?>
                    <a class="btn btn-secondary" href="index.php"><?php echo t('btn_cancel'); ?></a>
                <?php endif; ?>
                <?php if ($current_step < $max_steps): ?>
                    <button type="submit" name="next" class="btn btn-primary"><?php echo t('btn_next'); ?></button>
                <?php else: ?>
                    <button type="submit" name="submit" class="btn btn-primary"><?php echo t('btn_submit'); ?></button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="./JAVASCRIPT/registration.js"></script>

    <script>
        // Initialize modal on page load
        document.addEventListener("DOMContentLoaded", function () {
            const showForm = <?php echo $show_form ? 'true' : 'false'; ?>;

            if (!showForm) {
                const modalElement = document.getElementById("dataPrivacyModal");
                if (modalElement) {
                    const modal = new bootstrap.Modal(modalElement);
                    modal.show();
                }
            }
        });
    </script>
</body>

</html>