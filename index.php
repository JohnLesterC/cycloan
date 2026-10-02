<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}
require 'CYCLOAN_db.php';
require_once 'timezone_config.php';

$error_message = "";
$success_message = "";

// ========== FUNCTION TO LOG LOGIN ATTEMPTS ==========
function logLoginAttempt($conn, $email, $success = false)
{
  try {
    $query = "INSERT INTO logattempts (email, success, attempt) VALUES (?, ?, NOW())";
    $stmt = $conn->prepare($query);
    if (!$stmt) { error_log("Failed to prepare login attempt statement: " . $conn->error); return false; }
    $success_flag = $success ? 1 : 0;
    $stmt->bind_param("si", $email, $success_flag);
    if (!$stmt->execute()) { error_log("Failed to execute login attempt insert: " . $stmt->error); $stmt->close(); return false; }
    $stmt->close();
    return true;
  } catch (Exception $e) {
    error_log("Error logging login attempt: " . $e->getMessage());
    return false;
  }
}

// ========== CHECK FOR REMEMBERED LOGIN ==========
$remembered_email = '';
$remembered_password = '';
$remember_me_checked = '';

if (isset($_COOKIE['cycloan_remembered_email']) && isset($_COOKIE['cycloan_remembered_password'])) {
  $remembered_email = htmlspecialchars($_COOKIE['cycloan_remembered_email']);
  $remembered_password = htmlspecialchars($_COOKIE['cycloan_remembered_password']);
  $remember_me_checked = 'checked';
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $email = isset($_POST['email']) ? trim($_POST['email']) : '';
  $password = isset($_POST['password']) ? $_POST['password'] : '';
  $remember_me = isset($_POST['remember_me']) ? true : false;

  if (empty($email) || empty($password)) {
    $error_message = "Please provide both email and password.";
    logLoginAttempt($conn, $email, false);
  } else {
    $login_success = false;

    // Check in users1 table first
    $stmt = $conn->prepare("SELECT id, password, is_active FROM users1 WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
      $user = $result->fetch_assoc();
      $stmt->close();

      if (!$user['is_active']) {
        $error_message = "Account not verified. Please check your email for the OTP.";
        logLoginAttempt($conn, $email, false);
      } elseif (password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $email;
        $_SESSION['role'] = 'user';

        if ($remember_me) {
          setcookie('cycloan_remembered_email', $email, time() + (30 * 24 * 60 * 60), "/");
          setcookie('cycloan_remembered_password', $password, time() + (30 * 24 * 60 * 60), "/");
        } else {
          setcookie('cycloan_remembered_email', '', time() - 3600, "/");
          setcookie('cycloan_remembered_password', '', time() - 3600, "/");
        }

        logLoginAttempt($conn, $email, true);
        header("Location: user_dashboard.php");
        exit();
      } else {
        $error_message = "Invalid email or password.";
        logLoginAttempt($conn, $email, false);
      }
    } else {
      $stmt->close();

      // Check in admin1 table
      $stmt = $conn->prepare("SELECT id, password FROM admin1 WHERE email = ?");
      $stmt->bind_param("s", $email);
      $stmt->execute();
      $result = $stmt->get_result();

      if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $stmt->close();

        if (password_verify($password, $user['password'])) {
          $_SESSION['user_id'] = $user['id'];
          $_SESSION['email'] = $email;
          $_SESSION['role'] = 'admin1';

          if ($remember_me) {
            setcookie('cycloan_remembered_email', $email, time() + (30 * 24 * 60 * 60), "/");
            setcookie('cycloan_remembered_password', $password, time() + (30 * 24 * 60 * 60), "/");
          } else {
            setcookie('cycloan_remembered_email', '', time() - 3600, "/");
            setcookie('cycloan_remembered_password', '', time() - 3600, "/");
          }

          logLoginAttempt($conn, $email, true);
          header("Location: admin1_dashboard.php");
          exit();
        } else {
          $error_message = "Invalid email or password.";
          logLoginAttempt($conn, $email, false);
        }
      } else {
        $stmt->close();

        // Check in admin2 table
        $stmt = $conn->prepare("SELECT id, password FROM admin2 WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
          $user = $result->fetch_assoc();
          $stmt->close();

          if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $email;
            $_SESSION['role'] = 'admin2';

            logLoginAttempt($conn, $email, true);
            header("Location: admin2_dashboard.php");
            exit();
          } else {
            $error_message = "Invalid email or password.";
            logLoginAttempt($conn, $email, false);
          }
        } else {
          $stmt->close();

          // Check in superadmins table
          $stmt = $conn->prepare("SELECT id, password FROM superadmins WHERE email = ?");
          $stmt->bind_param("s", $email);
          $stmt->execute();
          $result = $stmt->get_result();

          if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $stmt->close();

            if (password_verify($password, $user['password'])) {
              $_SESSION['user_id'] = $user['id'];
              $_SESSION['email'] = $email;
              $_SESSION['role'] = 'superadmin';

              logLoginAttempt($conn, $email, true);
              header("Location: Superadmin_dashboard.php");
              exit();
            } else {
              $error_message = "Invalid email or password.";
              logLoginAttempt($conn, $email, false);
            }
          } else {
            $stmt->close();
            $error_message = "Invalid email or password.";
            logLoginAttempt($conn, $email, false);
          }
        }
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CYCLOAN — Login</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
  <link rel="stylesheet" href="CSS/cycloan-system.css" />
</head>
<body>
  <!-- Loading Screen -->
  <div class="cl-loading" id="loadingScreen">
    <img src="IMAGE/Main-Logo.png" alt="CYCLOAN Logo" class="cl-loading-logo">
  </div>

  <div class="cl-login-page">
    <div class="cl-login-container">
      <!-- Hero Image Side -->
      <div class="cl-login-hero">
        <img src="https://media.base44.com/images/public/6abf5bc571bd58df10d06e82/c41a5432c_generated_258fabb2.jpg" alt="CYCLOAN Loan Management" />
      </div>

      <!-- Login Form Side -->
      <div class="cl-login-form-side">
        <img src="IMAGE/Main-Logo.png" alt="CYCLOAN Logo" class="cl-login-logo" />

        <?php if (!empty($error_message)): ?>
          <div class="cl-login-error"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <form action="index.php" method="post" class="cl-login-form">
          <div class="cl-login-field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= $remembered_email ?>" placeholder="you@example.com" required />
          </div>
          <div class="cl-login-field">
            <label for="password">Password</label>
            <div class="cl-input-group">
              <input type="password" id="password" name="password" placeholder="Enter your password" required>
              <button type="button" class="cl-input-icon" onclick="togglePassword()">
                <i class="fas fa-eye-slash" id="toggleIcon"></i>
              </button>
            </div>
          </div>
          <label class="cl-login-remember">
            <input type="checkbox" id="remember_me" name="remember_me" <?= $remember_me_checked ?>>
            Remember me
          </label>
          <button class="cl-login-btn" type="submit">Log in</button>
        </form>

        <div class="cl-login-links">
          <a href="forget_pass.php">Forgot Password</a>
          <span>|</span>
          <a href="registration.php">Register</a>
        </div>
      </div>
    </div>

    <!-- FAQ Section -->
    <section class="cl-faq-section" style="max-width: 960px; width: 100%; margin-top: 32px;">
      <div class="cl-faq-grid">
        <details class="cl-faq-item" open>
          <summary>How do I create an account?</summary>
          <p>To create an account, click on the "Register" link on the login page. You'll need to provide your personal information, residential address, and financial details. Make sure you have all required documents ready before starting the registration process.</p>
        </details>
        <details class="cl-faq-item" open>
          <summary>What should I do if I forget my password?</summary>
          <p>Click on "Forgot Password" on the login page. Enter your email address and you'll receive a password reset link. Follow the instructions in the email to create a new password. Make sure to check your spam folder if you don't see the email.</p>
        </details>
        <details class="cl-faq-item" open>
          <summary>What documents do I need to submit?</summary>
          <p>Required documents typically include: valid ID, proof of residence, income documentation, and employment verification. The specific documents may vary based on your loan type. You'll see the exact requirements during the registration process.</p>
        </details>
        <details class="cl-faq-item" open>
          <summary>How long does the application process take?</summary>
          <p>The typical application process takes 5-7 business days. However, this may vary depending on document verification and credit investigation. You can track your application status in your dashboard.</p>
        </details>
        <details class="cl-faq-item" open>
          <summary>What loan amounts are available?</summary>
          <p>Loan amounts vary based on your income and creditworthiness. Our loans range from ₱10,000 to ₱500,000. The maximum amount you can borrow will be determined during the pre-approval process based on your financial profile.</p>
        </details>
        <details class="cl-faq-item" open>
          <summary>What are the interest rates?</summary>
          <p>Interest rates are competitive and depend on loan type, amount, and term. You can view current interest rates by clicking "View Interest Rates" in the admin dashboard. Rates are applied based on your credit profile and risk assessment.</p>
        </details>
        <details class="cl-faq-item" open>
          <summary>Is my personal information secure?</summary>
          <p>Yes, we use industry-standard encryption and security measures to protect your personal information. All data is stored securely and we comply with data privacy regulations. Your information is never shared with third parties without your consent.</p>
        </details>
        <details class="cl-faq-item" open>
          <summary>Can I pay off my loan early?</summary>
          <p>Yes, early repayment is allowed without penalties. You can make additional payments at any time to reduce your loan balance and save on interest. Contact our support team for details on early payment procedures.</p>
        </details>
        <details class="cl-faq-item" open>
          <summary>What if my application is rejected?</summary>
          <p>If your application is rejected, you'll receive a notification explaining the reason. You can review the feedback and address any issues, then reapply after 30 days. Our support team is available to help you understand the decision and improve your application.</p>
        </details>
        <details class="cl-faq-item" open>
          <summary>How do I contact customer support?</summary>
          <p>You can contact our support team via email at cycloancldd@gmail.com or by phone at 0981-303-8698. Our business hours are Monday to Friday, 9:00 AM to 5:00 PM. For urgent concerns, please use the phone number provided.</p>
        </details>
      </div>
    </section>
  </div>

  <script>
    function togglePassword() {
      const p = document.getElementById('password');
      const i = document.getElementById('toggleIcon');
      if (p.type === 'password') { p.type = 'text'; i.classList.replace('fa-eye-slash', 'fa-eye'); }
      else { p.type = 'password'; i.classList.replace('fa-eye', 'fa-eye-slash'); }
    }
    window.onload = () => setTimeout(() => document.getElementById('loadingScreen').classList.add('fade-out'), 1200);
  </script>
</body>
</html>
