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

    :root {
      --green-darkest: #163e31;
      --green-dark: #1b4937;
      --green-primary: #388653;
      --green-light: #69a96f;
      --green-accent: #b4e28f;
      --green-pale: #e5f0e2;
      --green-mist: #f4f8f3;
      --text: #183b31;
      --text-muted: #61776a;
      --border: #dfebdf;
      --danger: #c0392b;
      --danger-bg: #fdeae7;
    }

    body {
      font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: var(--green-mist);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
      -webkit-font-smoothing: antialiased;
    }

    /* ===== Split layout ===== */
    .login-wrap {
      display: grid;
      grid-template-columns: 1.1fr 1fr;
      max-width: 920px;
      width: 100%;
      border-radius: 24px;
      overflow: hidden;
      box-shadow: 0 22px 55px rgba(35, 76, 51, 0.16);
      animation: rise 0.6s cubic-bezier(0.2, 0.8, 0.2, 1) both;
    }

    @keyframes rise {
      from { opacity: 0; transform: translateY(16px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* ===== Left brand panel ===== */
    .brand-panel {
      background: linear-gradient(155deg, var(--green-darkest), var(--green-dark) 60%, var(--green-primary));
      padding: 56px 48px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      position: relative;
      overflow: hidden;
    }
    .brand-panel::before {
      content: "";
      position: absolute;
      top: -40%;
      right: -30%;
      width: 420px;
      height: 420px;
      border-radius: 50%;
      background: rgba(180, 226, 143, 0.08);
    }
    .brand-panel::after {
      content: "";
      position: absolute;
      bottom: -20%;
      left: -10%;
      width: 300px;
      height: 300px;
      border-radius: 50%;
      background: rgba(105, 169, 111, 0.07);
    }

    .brand-logo {
      width: 130px;
      height: auto;
      filter: brightness(0) invert(1);
      opacity: 0.95;
      position: relative;
      z-index: 1;
    }

    .brand-content { position: relative; z-index: 1; }
    .brand-content h1 {
      color: #fff;
      font-size: 26px;
      font-weight: 700;
      letter-spacing: -0.02em;
      line-height: 1.3;
      margin-bottom: 14px;
    }
    .brand-content p {
      color: rgba(228, 239, 230, 0.75);
      font-size: 14px;
      line-height: 1.6;
      max-width: 280px;
    }

    .brand-features {
      display: flex;
      flex-direction: column;
      gap: 14px;
      position: relative;
      z-index: 1;
    }
    .brand-feature {
      display: flex;
      align-items: center;
      gap: 12px;
      color: rgba(228, 239, 230, 0.85);
      font-size: 13px;
      font-weight: 500;
    }
    .brand-feature i {
      width: 32px;
      height: 32px;
      border-radius: 8px;
      background: rgba(180, 226, 143, 0.15);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--green-accent);
      font-size: 14px;
      flex-shrink: 0;
    }

    /* ===== Right form panel ===== */
    .form-panel {
      background: #fff;
      padding: 56px 48px;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .form-panel h2 {
      font-size: 24px;
      font-weight: 700;
      color: var(--green-darkest);
      letter-spacing: -0.02em;
      margin-bottom: 6px;
    }
    .form-panel .subtitle {
      font-size: 14px;
      color: var(--text-muted);
      margin-bottom: 32px;
    }

    .error-banner {
      background: var(--danger-bg);
      color: var(--danger);
      border: 1px solid rgba(192, 57, 43, 0.2);
      border-radius: 10px;
      padding: 12px 16px;
      font-size: 13px;
      font-weight: 500;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .field { margin-bottom: 20px; }
    .field label {
      display: block;
      font-size: 13px;
      font-weight: 600;
      color: var(--green-darkest);
      margin-bottom: 8px;
    }
    .field input {
      width: 100%;
      height: 48px;
      padding: 0 16px;
      border: 1.5px solid var(--border);
      border-radius: 12px;
      font-family: inherit;
      font-size: 14px;
      color: var(--text);
      background: var(--green-mist);
      outline: none;
      transition: border-color 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
    }
    .field input::placeholder { color: #a8b8ac; }
    .field input:focus {
      border-color: var(--green-light);
      background: #fff;
      box-shadow: 0 0 0 3px rgba(93, 163, 110, 0.12);
    }

    .remember-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 28px;
    }
    .remember-row label {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 13px;
      color: var(--text-muted);
      cursor: pointer;
    }
    .remember-row input { width: 18px; height: 18px; accent-color: var(--green-primary); cursor: pointer; }
    .remember-row a {
      font-size: 13px;
      font-weight: 600;
      color: var(--green-primary);
      text-decoration: none;
      transition: color 0.18s ease;
    }
    .remember-row a:hover { color: var(--green-dark); }

    .login-btn {
      width: 100%;
      height: 50px;
      border: 0;
      border-radius: 12px;
      background: linear-gradient(135deg, var(--green-primary), var(--green-light));
      color: #fff;
      font-family: inherit;
      font-size: 15px;
      font-weight: 700;
      cursor: pointer;
      transition: filter 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
      box-shadow: 0 4px 14px rgba(56, 134, 83, 0.25);
    }
    .login-btn:hover { filter: brightness(1.08); box-shadow: 0 6px 20px rgba(56, 134, 83, 0.35); }
    .login-btn:active { transform: translateY(1px); }

    .divider {
      display: flex;
      align-items: center;
      gap: 12px;
      margin: 28px 0;
      color: var(--text-muted);
      font-size: 12px;
      font-weight: 500;
    }
    .divider::before, .divider::after {
      content: "";
      flex: 1;
      height: 1px;
      background: var(--border);
    }

    .register-link {
      text-align: center;
      font-size: 14px;
      color: var(--text-muted);
    }
    .register-link a {
      font-weight: 700;
      color: var(--green-primary);
      text-decoration: none;
      transition: color 0.18s ease;
    }
    .register-link a:hover { color: var(--green-dark); }

    /* ===== Responsive ===== */
    @media (max-width: 768px) {
      .login-wrap { grid-template-columns: 1fr; max-width: 420px; }
      .brand-panel { padding: 40px 32px; min-height: 200px; }
      .brand-content h1 { font-size: 22px; }
      .brand-features { display: none; }
      .form-panel { padding: 40px 32px; }
    }
  </style>
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
