<?php
require_once __DIR__ . '/env_config.php';
if (defined('APP_DEBUG') && APP_DEBUG) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// SECURITY: Set secure session cookie parameters (only in HTTPS environments)
ini_set('session.cookie_httponly', 1);
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
if ($isHttps) {
    ini_set('session.cookie_secure', 1);
}
ini_set('session.cookie_samesite', 'Lax');
// Always log errors to file even when display is off
ini_set('log_errors', '1');

// SECURITY: Validate CSRF token on POST requests FIRST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Debug: Log CSRF token check
    error_log("CSRF Check - POST Token: " . ($_POST['csrf_token'] ?? 'EMPTY'));
    error_log("CSRF Check - Session Token: " . ($_SESSION['csrf_token'] ?? 'EMPTY'));

    if (empty($_POST['csrf_token'])) {
        http_response_code(403);
        die(json_encode([
            'success' => false,
            'message' => 'Security error: Missing session token. Please refresh and try again.'
        ]));
    }

    if (empty($_SESSION['csrf_token'])) {
        http_response_code(403);
        die(json_encode([
            'success' => false,
            'message' => 'Security error: Session expired. Please refresh the page and try again.'
        ]));
    }

    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        die(json_encode([
            'success' => false,
            'message' => 'Security error: Invalid session token. Please refresh and try again.'
        ]));
    }
}

// SECURITY: Regenerate session ID to prevent fixation attacks (only once)
// Do this AFTER CSRF validation to preserve the token during form submission
if (empty($_SESSION['_session_created'])) {
    // Preserve CSRF token during session regeneration
    $csrf_token = $_SESSION['csrf_token'] ?? null;
    session_regenerate_id(true);
    $_SESSION['_session_created'] = time();
    // Restore CSRF token if it existed
    if ($csrf_token !== null) {
        $_SESSION['csrf_token'] = $csrf_token;
    }
}

// Include the MySQLi database connection
require_once 'CYCLOAN_db.php';

// Include security validation library
require_once 'security_validation.php';

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/src/Exception.php';
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';

// Sanitize input to prevent XSS
function sanitizeInput($data)
{
    return htmlspecialchars(trim($data));
}

// Clean numeric input by removing commas and casting to float
function cleanNumeric($input)
{
    $cleaned = trim(str_replace(',', '', $input ?? '0'));
    return (float) $cleaned;
}

// SECURITY: Enhanced OTP generation with cryptographic randomness
function generateOTP()
{
    // Use random_bytes for cryptographic randomness instead of mt_rand
    $randomBytes = random_bytes(3); // 3 bytes = 24 bits of entropy
    $randomInt = abs((int) bindec(implode('', array_map(fn($b) => sprintf('%08b', ord($b)), str_split($randomBytes)))));
    return str_pad($randomInt % 1000000, 6, '0', STR_PAD_LEFT);
}

// SECURITY: Hash OTP before storing in database
function hashOTP($otp)
{
    return password_hash($otp, PASSWORD_BCRYPT, ['cost' => 12]);
}

// Function to send OTP email with modern, professional template and email header sanitization
function sendOTPEmail($to, $name, $otp)
{
    // SECURITY: Sanitize email headers to prevent email header injection
    $to = filter_var($to, FILTER_SANITIZE_EMAIL);
    $name = str_replace(["\r", "\n", "%0a", "%0d"], '', $name);

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'scycloan@gmail.com';
        $mail->Password = 'xbvo zplr dpme ixxj'; // Use an app password or environment variable for security
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients
        $mail->setFrom('scycloan@gmail.com', 'CYCLOAN Support');
        $mail->addAddress($to);

        // SECURITY: Never include raw OTP in email body - only mention it's in this email
        $mail->isHTML(true);
        $mail->Subject = 'Your CYCLOAN OTP for Account Verification';
        $mail->Body = '
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <style>
                    body { margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; background-color: #f4f4f4; }
                    .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; }
                    .header { background-color: #1b5e20; padding: 20px; text-align: center; }
                    .header img { max-width: 150px; }
                    .content { padding: 30px; color: #333333; }
                    h1 { color: #1b5e20; font-size: 24px; margin-bottom: 20px; }
                    p { font-size: 16px; line-height: 1.6; margin-bottom: 15px; }
                    .otp-box { background-color: #f0f0f0; padding: 15px; border-radius: 5px; text-align: center; font-size: 24px; font-weight: bold; color: #1b5e20; letter-spacing: 5px; }
                    .footer { background-color: #f4f4f4; padding: 20px; text-align: center; font-size: 14px; color: #666666; }
                    @media only screen and (max-width: 600px) {
                        .container { width: 100%; }
                        .content { padding: 20px; }
                        h1 { font-size: 20px; }
                        .otp-box { font-size: 20px; letter-spacing: 3px; }
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <img src="IMAGE/Main-Logo.png" alt="CYCLOAN Logo">
                    </div>
                    <div class="content">
                        <h1>Welcome, ' . htmlspecialchars($name) . '!</h1>
                        <p>Thank you for registering with CYCLOAN. To complete your account setup and ensure the security of your account, please use the following One-Time Password (OTP):</p>
                        <div class="otp-box">' . htmlspecialchars($otp) . '</div>
                        <p>This OTP is valid for the next 10 minutes. If you did not request this, please ignore this email or contact our support team immediately.</p>
                        <p>For your security, never share this OTP with anyone. If you need assistance, please reach out to us at <a href="mailto:support@cycloan-cldd.com">support@cycloan-cldd.com</a>.</p>
                    </div>
                    <div class="footer">
                        <p>&copy; ' . date('Y') . ' CYCLOAN. All rights reserved.<br>
                        <a href="landing_page.php">Visit CYCLOAN</a></p>
                    </div>
                </div>
            </body>
            </html>
        ';
        $mail->AltBody = "Hello $name,\n\nThank you for registering with CYCLOAN. To complete your account setup, please use the following OTP: $otp\n\nThis OTP is valid for 10 minutes. If you did not request this, please ignore this email or contact support at support@cycloan-cldd.com.\n\nBest regards,\nThe CYCLOAN Team\nhttp://localhost/CYCLOAN";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error for email $to: {$mail->ErrorInfo} | Timestamp: " . date('Y-m-d H:i:s') . " | Exception: " . $e->getMessage());
        return false;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $current_step = isset($_GET['step']) ? (int) $_GET['step'] : 1;
    $errors = [];

    // Collect form data from session
    $form_data = isset($_SESSION['form_data']) ? $_SESSION['form_data'] : [];
    $civil_status = isset($form_data['civil_status']) ? $form_data['civil_status'] : '';
    $max_steps = ($civil_status === 'Single' || $civil_status === 'Widowed') ? 5 : 6;

    // Step-specific data collection and validation
    if ($current_step == 1) {
        // Validate and sanitize with security library
        $form_data['first_name'] = validateString($_POST['first_name'] ?? '', 1, 50);
        $form_data['middle_name'] = validateString($_POST['middle_name'] ?? '', 0, 50);
        $form_data['last_name'] = validateString($_POST['last_name'] ?? '', 1, 50);
        $form_data['name_extension'] = validateString($_POST['name_extension'] ?? '', 0, 20);
        $form_data['nick_name'] = validateString($_POST['nick_name'] ?? '', 0, 50);
        $form_data['birthday'] = validateDate($_POST['birthday'] ?? '', 'Y-m-d');
        $form_data['age'] = validateInteger($_POST['age'] ?? 0, 18, 120);
        $form_data['birth_place'] = validateString($_POST['birth_place'] ?? '', 0, 100);
        $form_data['civil_status'] = validateEnum($_POST['civil_status'] ?? '', ['Single', 'Married', 'Widowed', 'Separated', 'Divorced'], false);
        $form_data['contact'] = validatePhoneNumber($_POST['contact'] ?? '');
        $form_data['email'] = validateEmail($_POST['email'] ?? '');
        $form_data['fb_account'] = validateString($_POST['fb_account'] ?? '', 0, 100);
        $form_data['occupation'] = validateString($_POST['occupation'] ?? '', 0, 100);
        $form_data['reg_voter'] = validateEnum($_POST['reg_voter'] ?? 'No', ['Yes', 'No'], false);
        $form_data['year_resident'] = validateInteger($_POST['year_resident'] ?? 0, 0, 150);

        // Recalculate age on server with current date
        if (!empty($form_data['birthday'])) {
            try {
                $birthDate = new DateTime($form_data['birthday']);
                $today = new DateTime('2025-10-16'); // Current date
                $interval = $today->diff($birthDate);
                $form_data['age'] = $interval->y;
            } catch (Exception $e) {
                $errors[] = "Error: Invalid birthday format. Please enter a valid date.";
            }
        }

        // Validate required fields
        if ($form_data['first_name'] === false) {
            $errors[] = "Error: First name is required (1-50 characters).";
        }
        if ($form_data['last_name'] === false) {
            $errors[] = "Error: Last name is required (1-50 characters).";
        }
        if ($form_data['birthday'] === false && !empty($_POST['birthday'] ?? '')) {
            $errors[] = "Error: Invalid birthday format. Please use YYYY-MM-DD format.";
        }
        if ($form_data['civil_status'] === false) {
            $errors[] = "Error: Invalid civil status selected.";
        }
        if (!empty($_POST['contact'] ?? '') && $form_data['contact'] === false) {
            $errors[] = "Error: Contact number must be a valid Philippine phone number (09XXXXXXXXX).";
        }
        if (!empty($_POST['email'] ?? '') && $form_data['email'] === false) {
            $errors[] = "Error: Invalid email format. Please enter a valid email.";
        }

        // Validation
        if ($form_data['age'] !== false && $form_data['age'] < 21) {
            $errors[] = "Please note: You must be at least 21 years old to complete registration. If this is incorrect, double-check your birthday.";
        }
    } elseif ($current_step == 2) {
        $form_data['res_house_no'] = validateString($_POST['res_house_no'] ?? '', 0, 50);
        $form_data['res_street'] = validateString($_POST['res_street'] ?? '', 0, 100);
        $form_data['res_subdivision'] = validateString($_POST['res_subdivision'] ?? '', 0, 100);
        $form_data['res_barangay'] = validateString($_POST['res_barangay'] ?? '', 0, 100);
        $form_data['res_address'] = validateString($_POST['res_address'] ?? '', 0, 255);
        $form_data['house_ownership'] = validateEnum($_POST['house_ownership'] ?? 'Owned', ['Owned', 'Rented', 'Borrowed', 'Other'], false);
    } elseif ($current_step == 3) {
        $form_data['bus_bldg_no'] = validateString($_POST['bus_bldg_no'] ?? '', 0, 50);
        $form_data['bus_street'] = validateString($_POST['bus_street'] ?? '', 0, 100);
        $form_data['bus_subdivision'] = validateString($_POST['bus_subdivision'] ?? '', 0, 100);
        $form_data['bus_barangay'] = validateString($_POST['bus_barangay'] ?? '', 0, 100);
        $form_data['bus_address'] = validateString($_POST['bus_address'] ?? '', 0, 255);
    } elseif ($current_step == 4 && $civil_status !== 'Single' && $civil_status !== 'Widowed') {
        $form_data['spouse_first_name'] = validateString($_POST['spouse_first_name'] ?? '', 1, 50);
        $form_data['spouse_middle_name'] = validateString($_POST['spouse_middle_name'] ?? '', 0, 50);
        $form_data['spouse_last_name'] = validateString($_POST['spouse_last_name'] ?? '', 1, 50);
        $form_data['spouse_name_extension'] = validateString($_POST['spouse_name_extension'] ?? '', 0, 20);
        $form_data['spouse_nick_name'] = validateString($_POST['spouse_nick_name'] ?? '', 0, 50);
        $form_data['spouse_reg_voter'] = validateEnum($_POST['spouse_reg_voter'] ?? 'No', ['Yes', 'No'], false);
        $form_data['spouse_birthday'] = validateDate($_POST['spouse_birthday'] ?? '', 'Y-m-d');
        $form_data['spouse_age'] = validateInteger($_POST['spouse_age'] ?? 0, 18, 120);
        $form_data['spouse_occupation'] = validateString($_POST['spouse_occupation'] ?? '', 0, 100);
        $form_data['spouse_dependents'] = validateInteger($_POST['spouse_dependents'] ?? 0, 0, 20);
        $form_data['spouse_birth_place'] = validateString($_POST['spouse_birth_place'] ?? '', 0, 100);
        $form_data['spouse_contact'] = validatePhoneNumber($_POST['spouse_contact'] ?? '');
        $form_data['spouse_email'] = validateEmail($_POST['spouse_email'] ?? '');
        $form_data['spouse_fb_account'] = validateString($_POST['spouse_fb_account'] ?? '', 0, 100);

        // Recalculate spouse age on server with current date
        if (!empty($form_data['spouse_birthday'])) {
            try {
                $birthDate = new DateTime($form_data['spouse_birthday']);
                $today = new DateTime('2025-10-16'); // Current date
                $interval = $today->diff($birthDate);
                $form_data['spouse_age'] = $interval->y;
            } catch (Exception $e) {
                $errors[] = "Error: Invalid spouse birthday format. Please enter a valid date.";
            }
        }

        // Validate required spouse fields
        if ($form_data['spouse_first_name'] === false) {
            $errors[] = "Error: Spouse first name is required (1-50 characters).";
        }
        if ($form_data['spouse_last_name'] === false) {
            $errors[] = "Error: Spouse last name is required (1-50 characters).";
        }
        if ($form_data['spouse_birthday'] === false && !empty($_POST['spouse_birthday'] ?? '')) {
            $errors[] = "Error: Invalid spouse birthday format. Please use YYYY-MM-DD format.";
        }
        if (!empty($_POST['spouse_contact'] ?? '') && $form_data['spouse_contact'] === false) {
            $errors[] = "Error: Spouse contact must be a valid Philippine phone number.";
        }
        if (!empty($_POST['spouse_email'] ?? '') && $form_data['spouse_email'] === false) {
            $errors[] = "Error: Invalid spouse email format.";
        }

        // Validation
        if ($form_data['spouse_age'] !== false && $form_data['spouse_age'] < 21) {
            $errors[] = "Error: Spouse must be at least 21 years old. Please verify the birthday.";
        }
    } elseif ($current_step == (($civil_status === 'Single' || $civil_status === 'Widowed') ? 4 : 5)) {
        $form_data['income_sources'] = $_POST['income_sources'] ?? [];
        $form_data['business'] = validateDecimal($_POST['business'] ?? '0', 2, 0, 9999999.99);
        $form_data['salary'] = validateDecimal($_POST['salary'] ?? '0', 2, 0, 9999999.99);
        $form_data['remittance'] = validateDecimal($_POST['remittance'] ?? '0', 2, 0, 9999999.99);
        $form_data['other_income'] = validateDecimal($_POST['other_income'] ?? '0', 2, 0, 9999999.99);
        $form_data['business2'] = validateDecimal($_POST['business2'] ?? '0', 2, 0, 9999999.99);
        $form_data['salary2'] = validateDecimal($_POST['salary2'] ?? '0', 2, 0, 9999999.99);
        $form_data['net_income'] = validateDecimal($_POST['net_income'] ?? '0', 2, 0, 9999999.99);
        $form_data['expenditure_types'] = $_POST['expenditure_types'] ?? [];
        $form_data['food_allowance'] = validateDecimal($_POST['food_allowance'] ?? '0', 2, 0, 9999999.99);
        $form_data['electricity_bill'] = validateDecimal($_POST['electricity_bill'] ?? '0', 2, 0, 9999999.99);
        $form_data['water_bill'] = validateDecimal($_POST['water_bill'] ?? '0', 2, 0, 9999999.99);
        $form_data['internet_bill'] = validateDecimal($_POST['internet_bill'] ?? '0', 2, 0, 9999999.99);
        $form_data['gas_bill'] = validateDecimal($_POST['gas_bill'] ?? '0', 2, 0, 9999999.99);
        $form_data['educational_allowance'] = validateDecimal($_POST['educational_allowance'] ?? '0', 2, 0, 9999999.99);
        $form_data['car_amortization'] = validateDecimal($_POST['car_amortization'] ?? '0', 2, 0, 9999999.99);
        $form_data['insurance'] = validateDecimal($_POST['insurance'] ?? '0', 2, 0, 9999999.99);
        $form_data['other_expense'] = validateDecimal($_POST['other_expense'] ?? '0', 2, 0, 9999999.99);
        $form_data['expenditures'] = validateDecimal($_POST['expenditures'] ?? '0', 2, 0, 9999999.99);
        $form_data['expected_monthly_amortization'] = validateDecimal($_POST['expected_monthly_amortization'] ?? '0', 2, 0, 9999999.99);
        $form_data['remaining_income'] = validateDecimal($_POST['remaining_income'] ?? '0', 2, 0, 9999999.99);

        // Validate income sources
        foreach ($form_data['income_sources'] as $source) {
            $sourceName = ucwords(str_replace('_', ' ', $source));
            $validated_source = validateString($source, 1, 50);
            if ($validated_source === false) {
                $errors[] = "Error: Invalid income source selected.";
                logSecurityError("Attempted invalid income source: $source", 'injection_attempt');
                continue;
            }

            $source_value = $form_data[$source] ?? false;
            if ($source_value === false) {
                $errors[] = "Error: $sourceName must have a valid positive value since selected.";
            } elseif ($source_value <= 0) {
                $errors[] = "Error: $sourceName must have a positive value since selected.";
            } elseif ($source_value < 100) {
                $errors[] = "Error: $sourceName must be at least ₱100. Please adjust the value for accuracy.";
            }
        }

        // Validate expenditure types
        foreach ($form_data['expenditure_types'] as $type) {
            $typeName = ucwords(str_replace('_', ' ', $type));
            $validated_type = validateString($type, 1, 50);
            if ($validated_type === false) {
                $errors[] = "Error: Invalid expenditure type selected.";
                logSecurityError("Attempted invalid expenditure type: $type", 'injection_attempt');
                continue;
            }

            $type_value = $form_data[$type] ?? false;
            if ($type_value === false) {
                $errors[] = "Error: $typeName must have a valid positive value since selected.";
            } elseif ($type_value <= 0) {
                $errors[] = "Error: $typeName must have a positive value since selected.";
            } elseif ($type_value < 500) {
                $errors[] = "Error: $typeName must be at least ₱500. Please review and update.";
            }
        }

        // Validate expected monthly amortization
        if ($form_data['expected_monthly_amortization'] === false) {
            $errors[] = "Error: Expected monthly amortization must be a valid positive number.";
        } elseif ($form_data['expected_monthly_amortization'] < 0) {
            $errors[] = "Error: Expected monthly amortization cannot be negative. Please enter 0 or a positive value.";
        }
    } elseif ($current_step == $max_steps) {
        $form_data['password'] = $_POST['password'] ?? '';
        $form_data['confirm_password'] = $_POST['confirm_password'] ?? '';

        if (empty($form_data['password'])) {
            $errors[] = "Error: Password is required. Please create a strong password to secure your account.";
        } else {
            // Validate password strength
            $password_validation = validateString($form_data['password'], 8, 128);
            if ($password_validation === false) {
                $errors[] = "Error: Password must be 8-128 characters long.";
            } elseif (
                !preg_match("/[A-Z]/", $form_data['password']) ||
                !preg_match("/[a-z]/", $form_data['password']) ||
                !preg_match("/\d/", $form_data['password']) ||
                !preg_match("/[!@#$%^&*()_+\-=\[\]{};':\"\\\\|,.<>\/?]/", $form_data['password'])
            ) {
                $errors[] = "Error: Password must include at least one uppercase letter, one lowercase letter, one number, and one special character (e.g., !@#). Please try again.";
            }
        }

        if ($form_data['password'] !== $form_data['confirm_password']) {
            $errors[] = "Error: Passwords do not match. Please double-check and re-enter them.";
        }
    }

    // Store form data in session
    $_SESSION['form_data'] = $form_data;

    if (!empty($errors)) {
        $_SESSION['error_message'] = implode("<br>", $errors);
        $_SESSION['fresh_redirect'] = true;
        header("Location: registration.php?step=$current_step");
        exit();
    }

    // If not final step, redirect to next step
    if ($current_step < $max_steps) {
        $next_step = $current_step + 1;
        $_SESSION['fresh_redirect'] = true;
        header("Location: registration.php?step=$next_step");
        exit();
    }

    // Final step: Save to database
    try {
        // Use global $conn from CYCLOAN_db.php
        global $conn;

        // Check if $conn is a valid MySQLi object
        if (!($conn instanceof mysqli)) {
            throw new Exception("We're sorry, but the database connection could not be established. Please try again later or contact support.");
        }

        // Check if email already exists
        $stmt = $conn->prepare("SELECT COUNT(*) FROM users1 WHERE email = ?");
        if ($stmt === false) {
            throw new Exception("An internal error occurred while preparing the query. Please try again.");
        }
        $stmt->bind_param("s", $form_data['email']);
        $stmt->execute();
        $stmt->bind_result($email_count);
        $stmt->fetch();
        $stmt->close();

        if ($email_count > 0) {
            $_SESSION['error_message'] = "Error: This email is already registered. Please try a different email or log in if it's yours.";
            $_SESSION['fresh_redirect'] = true;
            header("Location: registration.php?step=1");
            exit();
        }

        // Insert into users1 table
        $hashed_password = password_hash($form_data['password'], PASSWORD_DEFAULT);
        $profile_image = '/assets/default.jpg'; // Set default profile picture
        $stmt = $conn->prepare("INSERT INTO users1 (first_name, middle_name, last_name, name_extension, nick_name, birthday, age, birth_place, civil_status, contact, email, fb_account, res_house_no, res_street, res_subdivision, res_barangay, res_address, bus_bldg_no, bus_street, bus_subdivision, bus_barangay, bus_address, house_ownership, occupation, reg_voter, year_resident, password, is_active, profile_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)");
        if ($stmt === false) {
            throw new Exception("An internal error occurred while preparing the user data: " . $conn->error);
        }
        $stmt->bind_param(
            "ssssssissssssssssssssssssiss",
            $form_data['first_name'],
            $form_data['middle_name'],
            $form_data['last_name'],
            $form_data['name_extension'],
            $form_data['nick_name'],
            $form_data['birthday'],
            $form_data['age'],
            $form_data['birth_place'],
            $form_data['civil_status'],
            $form_data['contact'],
            $form_data['email'],
            $form_data['fb_account'],
            $form_data['res_house_no'],
            $form_data['res_street'],
            $form_data['res_subdivision'],
            $form_data['res_barangay'],
            $form_data['res_address'],
            $form_data['bus_bldg_no'],
            $form_data['bus_street'],
            $form_data['bus_subdivision'],
            $form_data['bus_barangay'],
            $form_data['bus_address'],
            $form_data['house_ownership'],
            $form_data['occupation'],
            $form_data['reg_voter'],
            $form_data['year_resident'],
            $hashed_password,
            $profile_image
        );

        if (!$stmt->execute()) {
            throw new Exception("An error occurred while saving your user information. Please try again or contact support.");
        }
        $user_id = $stmt->insert_id;
        $stmt->close();

        // Insert into spouses table if applicable
        if ($civil_status !== 'Single' && $civil_status !== 'Widowed') {
            $stmt = $conn->prepare("INSERT INTO spouses (user_id, first_name, middle_name, last_name, name_extension, nick_name, reg_voter, birthday, age, occupation, dependents, birth_place, contact, email, fb_account) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt === false) {
                throw new Exception("An internal error occurred while preparing spouse data. Please try again.");
            }
            $stmt->bind_param(
                "isssssssisissss",
                $user_id,
                $form_data['spouse_first_name'],
                $form_data['spouse_middle_name'],
                $form_data['spouse_last_name'],
                $form_data['spouse_name_extension'],
                $form_data['spouse_nick_name'],
                $form_data['spouse_reg_voter'],
                $form_data['spouse_birthday'],
                $form_data['spouse_age'],
                $form_data['spouse_occupation'],
                $form_data['spouse_dependents'],
                $form_data['spouse_birth_place'],
                $form_data['spouse_contact'],
                $form_data['spouse_email'],
                $form_data['spouse_fb_account']
            );
            if (!$stmt->execute()) {
                throw new Exception("An error occurred while saving spouse information. Please verify the details and try again.");
            }
            $stmt->close();
        }

        // Insert into financial_info table
        $stmt = $conn->prepare("INSERT INTO financial_info (user_id, business_income, salary_income, remittance_income, other_income, business2_income, salary2_income, net_income, food_allowance, electricity_bill, water_bill, internet_bill, gas_bill, educational_allowance, car_amortization, insurance, other_expense, total_expenditures, expected_monthly_amortization, remaining_income) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt === false) {
            throw new Exception("An internal error occurred while preparing financial data. Please try again.");
        }
        $stmt->bind_param(
            "iddddddddddddddddddd",
            $user_id,
            $form_data['business'],
            $form_data['salary'],
            $form_data['remittance'],
            $form_data['other_income'],
            $form_data['business2'],
            $form_data['salary2'],
            $form_data['net_income'],
            $form_data['food_allowance'],
            $form_data['electricity_bill'],
            $form_data['water_bill'],
            $form_data['internet_bill'],
            $form_data['gas_bill'],
            $form_data['educational_allowance'],
            $form_data['car_amortization'],
            $form_data['insurance'],
            $form_data['other_expense'],
            $form_data['expenditures'],
            $form_data['expected_monthly_amortization'],
            $form_data['remaining_income']
        );
        if (!$stmt->execute()) {
            throw new Exception("An error occurred while saving your financial details. Please review and try again.");
        }
        $stmt->close();

        // Insert into income_sources table
        if (!empty($form_data['income_sources'])) {
            $stmt = $conn->prepare("INSERT INTO income_sources (user_id, source_type) VALUES (?, ?)");
            if ($stmt === false) {
                throw new Exception("An internal error occurred while preparing income sources. Please try again.");
            }
            foreach ($form_data['income_sources'] as $source) {
                $stmt->bind_param("is", $user_id, $source);
                if (!$stmt->execute()) {
                    throw new Exception("An error occurred while saving income sources. Please verify your selections.");
                }
            }
            $stmt->close();
        }

        // Insert into expenditure_types table
        if (!empty($form_data['expenditure_types'])) {
            $stmt = $conn->prepare("INSERT INTO expenditure_types (user_id, expense_type) VALUES (?, ?)");
            if ($stmt === false) {
                throw new Exception("An internal error occurred while preparing expenditure types. Please try again.");
            }
            foreach ($form_data['expenditure_types'] as $type) {
                $stmt->bind_param("is", $user_id, $type);
                if (!$stmt->execute()) {
                    throw new Exception("An error occurred while saving expenditure types. Please verify your selections.");
                }
            }
            $stmt->close();
        }

        // Generate and store OTP
        $otp = generateOTP();
        $otp_hash = hashOTP($otp);
        $created_at = date('Y-m-d H:i:s');
        $expires_at = (new DateTime())->modify('+10 minutes')->format('Y-m-d H:i:s');
        $stmt = $conn->prepare("INSERT INTO otps (user_id, otp_hash, created_at, expires_at, attempt_count) VALUES (?, ?, ?, ?, 0)");
        if ($stmt === false) {
            throw new Exception("An internal error occurred while generating your OTP. Please try again.");
        }
        $stmt->bind_param("isss", $user_id, $otp_hash, $created_at, $expires_at);
        if (!$stmt->execute()) {
            throw new Exception("An error occurred while storing your OTP. Please try again.");
        }
        $stmt->close();

        // Send OTP email
        $full_name = trim("{$form_data['first_name']} " . ($form_data['middle_name'] ? "{$form_data['middle_name']} " : '') . "{$form_data['last_name']}" . ($form_data['name_extension'] ? " {$form_data['name_extension']}" : ''));
        if (!sendOTPEmail($form_data['email'], $full_name, $otp)) {
            error_log("Failed to send OTP email to {$form_data['email']} | Timestamp: " . date('Y-m-d H:i:s'));
            $_SESSION['error_message'] = "We're sorry, but we couldn't send the OTP email right now. Please check your internet connection and try again, or contact support.";
            $_SESSION['fresh_redirect'] = true;
            header("Location: registration.php?step=$max_steps");
            exit();
        }

        // Store email in session for OTP verification
        $_SESSION['otp_email'] = $form_data['email'];

        // Clear form data from session
        unset($_SESSION['form_data']);

        // Redirect to OTP verification page
        $_SESSION['success_message'] = "Great! Your registration is complete. We've sent a secure OTP to your email for verification. Please check your inbox (and spam folder if needed).";
        $_SESSION['fresh_redirect'] = true;
        header("Location: verify_otp.php");
        exit();
    } catch (Exception $e) {
        error_log("Registration error for email {$form_data['email']}: " . $e->getMessage() . " | Timestamp: " . date('Y-m-d H:i:s'));
        $_SESSION['error_message'] = "We're sorry, but registration could not be completed: " . $e->getMessage() . " Please try again or contact our support team for assistance.";
        $_SESSION['fresh_redirect'] = true;
        header("Location: registration.php?step=$current_step");
        exit();
    }
} else {
    $_SESSION['error_message'] = "Error: Invalid request. Please use the registration form to submit your details.";
    $_SESSION['fresh_redirect'] = true;
    header("Location: registration.php?step=$current_step");
    exit();
}
?>