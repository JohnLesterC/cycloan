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
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: #111615;
      color: #e8eceb;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
      -webkit-font-smoothing: antialiased;
      position: relative;
    }
    body::before {
      content: "";
      position: absolute;
      inset: 0;
      background: radial-gradient(ellipse at 84% 2%, #183225 0%, transparent 24%);
      pointer-events: none;
    }

    /* ===== Split layout ===== */
    .login-wrap {
      display: grid;
      grid-template-columns: 1.1fr 1fr;
      max-width: 920px;
      width: 100%;
      border-radius: 8px;
      overflow: hidden;
      border: 1px solid #303734;
      box-shadow: 0 24px 80px rgba(0, 0, 0, 0.4);
      animation: rise 0.55s ease both;
      position: relative;
      z-index: 1;
    }

    @keyframes rise {
      from { opacity: 0; transform: translateY(12px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* ===== Left brand panel ===== */
    .brand-panel {
      background: #171c1b;
      padding: 48px 40px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      position: relative;
      overflow: hidden;
      border-right: 1px solid #303734;
    }
    .brand-panel::before {
      content: "";
      position: absolute;
      top: -40%;
      right: -30%;
      width: 420px;
      height: 420px;
      border-radius: 50%;
      background: rgba(70, 213, 140, 0.04);
    }
    .brand-panel::after {
      content: "";
      position: absolute;
      bottom: -20%;
      left: -10%;
      width: 300px;
      height: 300px;
      border-radius: 50%;
      background: rgba(70, 213, 140, 0.03);
    }

    .brand-logo {
      width: 110px;
      height: auto;
      filter: brightness(0) invert(1);
      opacity: 0.9;
      position: relative;
      z-index: 1;
    }

    .brand-content { position: relative; z-index: 1; }
    .brand-content h1 {
      color: #f1f5f2;
      font-size: 22px;
      font-weight: 650;
      letter-spacing: -0.03em;
      line-height: 1.3;
      margin-bottom: 12px;
    }
    .brand-content p {
      color: #929e97;
      font-size: 13px;
      line-height: 1.6;
      max-width: 280px;
    }

    .brand-features {
      display: flex;
      flex-direction: column;
      gap: 12px;
      position: relative;
      z-index: 1;
    }
    .brand-feature {
      display: flex;
      align-items: center;
      gap: 10px;
      color: #bdc7c1;
      font-size: 12px;
      font-weight: 500;
    }
    .brand-feature i {
      width: 28px;
      height: 28px;
      border-radius: 5px;
      background: rgba(70, 213, 140, 0.1);
      border: 1px solid #3e5a4a;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #46d58c;
      font-size: 12px;
      flex-shrink: 0;
    }

    /* ===== Right form panel ===== */
    .form-panel {
      background: #1a201e;
      padding: 48px 40px;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .form-panel h2 {
      font-size: 20px;
      font-weight: 650;
      color: #f1f5f2;
      letter-spacing: -0.03em;
      margin-bottom: 4px;
    }
    .form-panel .subtitle {
      font-size: 12px;
      color: #77837c;
      margin-bottom: 28px;
    }

    .error-banner {
      background: #3a231f;
      color: #f0867a;
      border: 1px solid #4a2a24;
      border-radius: 5px;
      padding: 10px 14px;
      font-size: 12px;
      font-weight: 500;
      margin-bottom: 18px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .field { margin-bottom: 18px; }
    .field label {
      display: block;
      font-size: 11px;
      font-weight: 550;
      color: #829089;
      letter-spacing: 0.04em;
      margin-bottom: 6px;
    }
    .field input {
      width: 100%;
      height: 42px;
      padding: 0 12px;
      border: 1px solid #333d38;
      border-radius: 5px;
      font-family: inherit;
      font-size: 13px;
      color: #e8eceb;
      background: #151b19;
      outline: none;
      transition: border-color 0.18s ease, box-shadow 0.18s ease;
    }
    .field input::placeholder { color: #5a6660; }
    .field input:focus {
      border-color: #46d58c;
      box-shadow: 0 0 0 2px rgba(70, 213, 140, 0.3);
    }

    .remember-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 24px;
    }
    .remember-row label {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 12px;
      color: #929e97;
      cursor: pointer;
    }
    .remember-row input { width: 16px; height: 16px; accent-color: #46d58c; cursor: pointer; }
    .remember-row a {
      font-size: 12px;
      font-weight: 600;
      color: #46d58c;
      text-decoration: none;
      transition: color 0.18s ease;
    }
    .remember-row a:hover { color: #48d48c; }

    .login-btn {
      width: 100%;
      height: 44px;
      border: 0;
      border-radius: 5px;
      background: #46d58c;
      color: #111615;
      font-family: inherit;
      font-size: 13px;
      font-weight: 700;
      cursor: pointer;
      transition: background 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
    }
    .login-btn:hover { background: #48d48c; box-shadow: 0 0 16px rgba(70, 213, 140, 0.3); }
    .login-btn:active { transform: translateY(1px); }
    .login-btn:focus-visible { outline: 2px solid #46d58c; outline-offset: 2px; }

    .divider {
      display: flex;
      align-items: center;
      gap: 12px;
      margin: 24px 0;
      color: #77837c;
      font-size: 11px;
      font-weight: 500;
    }
    .divider::before, .divider::after {
      content: "";
      flex: 1;
      height: 1px;
      background: #303734;
    }

    .register-link {
      text-align: center;
      font-size: 13px;
      color: #77837c;
    }
    .register-link a {
      font-weight: 700;
      color: #46d58c;
      text-decoration: none;
      transition: color 0.18s ease;
    }
    .register-link a:hover { color: #48d48c; }

    /* ===== Responsive ===== */
    @media (max-width: 768px) {
      .login-wrap { grid-template-columns: 1fr; max-width: 400px; }
      .brand-panel { padding: 32px 28px; min-height: 180px; }
      .brand-content h1 { font-size: 18px; }
      .brand-features { display: none; }
      .form-panel { padding: 32px 28px; }
    }
  </style>
  <link rel="stylesheet" href="CSS/cycloan-system.css">
</head>
<body>
  <div class="login-wrap">
    <!-- Brand Panel -->
    <div class="brand-panel">
      <img src="IMAGE/Main-Logo.png" alt="CYCLOAN" class="brand-logo" />

      <div class="brand-content">
        <h1>City Lending &amp; Development Department</h1>
        <p>Secure loan management platform for applications, payments, and document compliance.</p>
      </div>

      <div class="brand-features">
        <div class="brand-feature">
          <i class="fas fa-shield-alt"></i>
          <span>Bank-grade security &amp; encryption</span>
        </div>
        <div class="brand-feature">
          <i class="fas fa-file-alt"></i>
          <span>Track applications in real time</span>
        </div>
        <div class="brand-feature">
          <i class="fas fa-chart-line"></i>
          <span>Manage payments &amp; schedules</span>
        </div>
      </div>
    </div>

    <!-- Form Panel -->
    <div class="form-panel">
      <h2>Welcome back</h2>
      <p class="subtitle">Sign in to access your dashboard</p>

      <?php if (!empty($error_message)): ?>
        <div class="error-banner">
          <i class="fas fa-exclamation-circle"></i>
          <?= htmlspecialchars($error_message) ?>
        </div>
      <?php endif; ?>

      <form action="index.php" method="post">
        <div class="field">
          <label for="email">Email Address</label>
          <input type="email" id="email" name="email" value="<?= $remembered_email ?>" placeholder="you@example.com" required />
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" value="<?= $remembered_password ?>" placeholder="Enter your password" required />
        </div>
        <div class="remember-row">
          <label>
            <input type="checkbox" id="remember_me" name="remember_me" <?= $remember_me_checked ?>>
            Remember me
          </label>
          <a href="forget_pass.php">Forgot password?</a>
        </div>
        <button class="login-btn" type="submit">Sign In</button>
      </form>

      <div class="divider">or</div>

      <p class="register-link">
        Don't have an account? <a href="registration.php">Create one</a>
      </p>
    </div>
  </div>
</body>
</html>
