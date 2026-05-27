<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}
require 'CYCLOAN_db.php';
require_once 'timezone_config.php';

$error_message = "";
$success_message = "";

// ========== FUNCTION TO LOG LOGIN ATTEMPTS ==========
/**
 * Log login attempt to logattempts table with Philippine Time
 * 
 * @param mysqli $conn Database connection
 * @param string $email Email address attempting login
 * @param bool $success Whether login was successful
 */
function logLoginAttempt($conn, $email, $success = false)
{
  try {
    // Use NOW() for automatic PHT timestamp (MySQL session timezone is set in CYCLOAN_db.php)
    $query = "INSERT INTO logattempts (email, success, attempt) 
                  VALUES (?, ?, NOW())";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
      error_log("Failed to prepare login attempt statement: " . $conn->error);
      return false;
    }

    $success_flag = $success ? 1 : 0;
    $stmt->bind_param("si", $email, $success_flag);

    if (!$stmt->execute()) {
      error_log("Failed to execute login attempt insert: " . $stmt->error);
      $stmt->close();
      return false;
    }

    $stmt->close();
    return true;
  } catch (Exception $e) {
    error_log("Error logging login attempt: " . $e->getMessage());
    return false;
  }
}

// ========== CHECK FOR REMEMBERED LOGIN ==========
// If cookies exist, auto-fill the login form
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
    // Log failed attempt - missing credentials
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
        // Log failed attempt - account not verified
        logLoginAttempt($conn, $email, false);
      } elseif (password_verify($password, $user['password'])) {
        // Login successful
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $email;
        $_SESSION['role'] = 'user';

        // ========== REMEMBER ME FUNCTIONALITY ==========
        if ($remember_me) {
          // Set cookies to remember login for 30 days
          setcookie('cycloan_remembered_email', $email, time() + (30 * 24 * 60 * 60), "/");
          setcookie('cycloan_remembered_password', $password, time() + (30 * 24 * 60 * 60), "/");
        } else {
          // Clear remembered login cookies
          setcookie('cycloan_remembered_email', '', time() - 3600, "/");
          setcookie('cycloan_remembered_password', '', time() - 3600, "/");
        }

        // Log successful attempt
        logLoginAttempt($conn, $email, true);

        header("Location: user_dashboard.php");
        exit();
      } else {
        $error_message = "Invalid email or password.";
        // Log failed attempt - wrong password
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

          // ========== REMEMBER ME FUNCTIONALITY ==========
          if ($remember_me) {
            // Set cookies to remember login for 30 days
            setcookie('cycloan_remembered_email', $email, time() + (30 * 24 * 60 * 60), "/");
            setcookie('cycloan_remembered_password', $password, time() + (30 * 24 * 60 * 60), "/");
          } else {
            // Clear remembered login cookies
            setcookie('cycloan_remembered_email', '', time() - 3600, "/");
            setcookie('cycloan_remembered_password', '', time() - 3600, "/");
          }

          // Log successful attempt
          logLoginAttempt($conn, $email, true);

          header("Location: admin1_dashboard.php");
          exit();
        } else {
          $error_message = "Invalid email or password.";
          // Log failed attempt - wrong password
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

            // Log successful attempt
            logLoginAttempt($conn, $email, true);

            header("Location: admin2_dashboard.php");
            exit();
          } else {
            $error_message = "Invalid email or password.";
            // Log failed attempt - wrong password
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

              // Log successful attempt
              logLoginAttempt($conn, $email, true);

              header("Location: Superadmin_dashboard.php");
              exit();
            } else {
              $error_message = "Invalid email or password.";
              // Log failed attempt - wrong password
              logLoginAttempt($conn, $email, false);
            }
          } else {
            $stmt->close();
            $error_message = "Invalid email or password.";
            // Log failed attempt - email not found
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
  <title>Login Page</title>
  <link rel="stylesheet" href="CSS/style.css" />
  <link rel="stylesheet" href="CSS/login.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
</head>

<style>
  /* ====== LOADING SCREEN WITH LOGO ====== */
  #loading-screen {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
    transition: opacity 0.6s ease, visibility 0.6s ease;
    flex-direction: column;
  }

  #loading-screen.fade-out {
    opacity: 0;
    visibility: hidden;
  }

  .loading-logo {
    width: 440px;
    height: auto;
    animation: pulse 1.8s infinite ease-in-out;
    filter: drop-shadow(0 0 10px rgba(255, 255, 255, 0.4));
  }

  @keyframes pulse {

    0%,
    100% {
      transform: scale(1);
      opacity: 1;
    }

    50% {
      transform: scale(1.1);
      opacity: 0.8;
    }
  }
</style>

<body>
  <div id="loading-screen">
    <img src="IMAGE/Main-Logo.png" alt="CYCLOAN Logo" class="loading-logo">
  </div>
  <div class="container">
    <div class="welcome-box">
      <div class="slideshow-container">
        <div class="slide fade">
          <img src="IMAGE/pm1.png" alt="Welcome Banner 1">
        </div>
        <div class="slide fade">
          <img src="IMAGE/Logo-grp.png" alt="Welcome Banner 2">
        </div>
        <div class="dots-container">
          <span class="dot" onclick="currentSlide(1)"></span>
          <span class="dot" onclick="currentSlide(2)"></span>
        </div>
      </div>
    </div>
    <div class="login-box">
      <div class="logo-container">
        <img src="IMAGE/Main-Logo.png" alt="Logo" />
      </div>

      <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger notification">
          <?= htmlspecialchars($error_message) ?>
        </div>
      <?php endif; ?>

      <form action="index.php" method="post">
        <div class="input-group">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" value="<?= $remembered_email ?>" required />
        </div>
        <div class="input-group">
          <label for="password">Password</label>
          <div class="password-input-wrapper">
            <input type="password" id="password" name="password" required>
            <button type="button" class="toggle-password" onclick="togglePassword()">
              <i class="fas fa-eye-slash" id="toggleIcon"></i>
            </button>
          </div>
        </div>
        <div class="remember-me">
          <input type="checkbox" id="remember_me" name="remember_me" <?= $remember_me_checked ?>>
          <label for="remember_me">Remember me</label>
        </div>
        <button class="submit_btn" type="submit">Log in</button>
      </form>
      <div class="footer-links">
        <a href="forget_pass.php">Forgot Password</a> |
        <a href="registration.php">Register</a>
      </div>
    </div>
  </div>

  <!-- FAQ Modal -->
  <button class="faq-icon" title="FAQs"><i class="fas fa-question-circle"></i></button>
  <div class="faq-modal" id="faqModal">
    <div class="faq-modal-content">
      <div class="faq-header">
        <h2>Frequently Asked Questions</h2>
        <span class="faq-close">&times;</span>
      </div>
      <div class="faq-items">
        <div class="faq-item">
          <div class="faq-question" onclick="toggleFAQ(this)">
            <i class="fas fa-chevron-right"></i>
            <span>How do I create an account?</span>
          </div>
          <div class="faq-answer">
            <p>To create an account, click on the "Register" link on the login page. You'll need to provide your
              personal information, residential address, and financial details. Make sure you have all required
              documents ready before starting the registration process.</p>
          </div>
        </div>

        <div class="faq-item">
          <div class="faq-question" onclick="toggleFAQ(this)">
            <i class="fas fa-chevron-right"></i>
            <span>What should I do if I forget my password?</span>
          </div>
          <div class="faq-answer">
            <p>Click on "Forgot Password" on the login page. Enter your email address and you'll receive a password
              reset link. Follow the instructions in the email to create a new password. Make sure to check your spam
              folder if you don't see the email.</p>
          </div>
        </div>

        <div class="faq-item">
          <div class="faq-question" onclick="toggleFAQ(this)">
            <i class="fas fa-chevron-right"></i>
            <span>What documents do I need to submit?</span>
          </div>
          <div class="faq-answer">
            <p>Required documents typically include: valid ID, proof of residence, income documentation, and employment
              verification. The specific documents may vary based on your loan type. You'll see the exact requirements
              during the registration process.</p>
          </div>
        </div>

        <div class="faq-item">
          <div class="faq-question" onclick="toggleFAQ(this)">
            <i class="fas fa-chevron-right"></i>
            <span>How long does the application process take?</span>
          </div>
          <div class="faq-answer">
            <p>The typical application process takes 5-7 business days. However, this may vary depending on document
              verification and credit investigation. You can track your application status in your dashboard.</p>
          </div>
        </div>

        <div class="faq-item">
          <div class="faq-question" onclick="toggleFAQ(this)">
            <i class="fas fa-chevron-right"></i>
            <span>What loan amounts are available?</span>
          </div>
          <div class="faq-answer">
            <p>Loan amounts vary based on your income and creditworthiness. Our loans range from ₱10,000 to ₱500,000.
              The maximum amount you can borrow will be determined during the pre-approval process based on your
              financial profile.</p>
          </div>
        </div>

        <div class="faq-item">
          <div class="faq-question" onclick="toggleFAQ(this)">
            <i class="fas fa-chevron-right"></i>
            <span>What are the interest rates?</span>
          </div>
          <div class="faq-answer">
            <p>Interest rates are competitive and depend on loan type, amount, and term. You can view current interest
              rates by clicking "View Interest Rates" in the admin dashboard. Rates are applied based on your credit
              profile and risk assessment.</p>
          </div>
        </div>

        <div class="faq-item">
          <div class="faq-question" onclick="toggleFAQ(this)">
            <i class="fas fa-chevron-right"></i>
            <span>Is my personal information secure?</span>
          </div>
          <div class="faq-answer">
            <p>Yes, we use industry-standard encryption and security measures to protect your personal information. All
              data is stored securely and we comply with data privacy regulations. Your information is never shared with
              third parties without your consent.</p>
          </div>
        </div>

        <div class="faq-item">
          <div class="faq-question" onclick="toggleFAQ(this)">
            <i class="fas fa-chevron-right"></i>
            <span>Can I pay off my loan early?</span>
          </div>
          <div class="faq-answer">
            <p>Yes, early repayment is allowed without penalties. You can make additional payments at any time to reduce
              your loan balance and save on interest. Contact our support team for details on early payment procedures.
            </p>
          </div>
        </div>

        <div class="faq-item">
          <div class="faq-question" onclick="toggleFAQ(this)">
            <i class="fas fa-chevron-right"></i>
            <span>What if my application is rejected?</span>
          </div>
          <div class="faq-answer">
            <p>If your application is rejected, you'll receive a notification explaining the reason. You can review the
              feedback and address any issues, then reapply after 30 days. Our support team is available to help you
              understand the decision and improve your application.</p>
          </div>
        </div>

        <div class="faq-item">
          <div class="faq-question" onclick="toggleFAQ(this)">
            <i class="fas fa-chevron-right"></i>
            <span>How do I contact customer support?</span>
          </div>
          <div class="faq-answer">
            <p>You can contact our support team via email at cycloancldd@gmail.com or by phone at 0981-303-8698. Our
              business hours are Monday to Friday, 9:00 AM to 5:00 PM. For urgent concerns, please use the phone number
              provided.</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    function togglePassword() {
      const p = document.getElementById('password');
      const i = document.getElementById('toggleIcon');
      if (p.type === 'password') {
        p.type = 'text'; i.classList.replace('fa-eye-slash', 'fa-eye');
      } else {
        p.type = 'password'; i.classList.replace('fa-eye', 'fa-eye-slash');
      }
    }

    function toggleFAQ(element) {
      const faqItem = element.parentElement;
      const answer = faqItem.querySelector('.faq-answer');
      const icon = element.querySelector('i');

      // Close other open FAQs
      document.querySelectorAll('.faq-item').forEach(item => {
        if (item !== faqItem && item.classList.contains('active')) {
          item.classList.remove('active');
        }
      });

      faqItem.classList.toggle('active');
    }

    const modal = document.getElementById('faqModal');
    document.querySelector('.faq-icon').onclick = () => modal.classList.add('active');
    document.querySelector('.faq-close').onclick = () => modal.classList.remove('active');
    window.onclick = e => { if (e.target === modal) modal.classList.remove('active'); };
    window.onload = () => setTimeout(() => document.getElementById('loading-screen').classList.add('fade-out'), 3500);
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="JAVASCRIPT/index.js"></script>
</body>

</html>