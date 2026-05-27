<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require "CYCLOAN_db.php";

// Check if user is logged in as admin1
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin1") {
    header("Location: index.php");
    exit();
}

$role = $_SESSION["role"];
$id = $_SESSION["user_id"];

$allowed_roles = ["admin1", "admin2", "superadmins"];
if (!in_array($role, $allowed_roles)) {
    die("Invalid role.");
}

// Fetch user details
$sql = "SELECT * FROM admin1 WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    die("User not found.");
}

// Set profile image
$upload_dir = __DIR__ . '/uploads/';
$default_img = 'default.png';
$profile_img = !empty($user['profile_img']) && file_exists($upload_dir . $user['profile_img'])
    ? $user['profile_img']
    : $default_img;

if (!file_exists($upload_dir . $profile_img)) {
    error_log("Profile image not found: $upload_dir$profile_img", 3, 'errors.log');
    $profile_img = $default_img;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="CSS/admin_dashboard.css">
    <link rel="stylesheet" href="CSS/admin_profile.css">
    <link rel="stylesheet" href="CSS/nav_active.css">
    <link rel="stylesheet" href="CSS/profile_superadmin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <title>Admin1 Profile - CYCLOAN</title>
</head>

<body>
    <div class="nav-container">
        <button class="burger" aria-label="Toggle menu">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <nav>
            <img src="IMAGE/Main-Logo.png" alt="Loan System Logo" class="sidebar-logo">
            <a href="admin1_dashboard.php"><i class="fa-solid fa-table-columns"></i>DASHBOARD</a>
            <a href="applicant.php"><i class="fa-solid fa-users"></i>APPLICANTS</a>
            <a href="active_records.php"><i class="fa-solid fa-user-check"></i>ACTIVE RECORDS</a>
            <a href="pending_records.php"><i class="fa-solid fa-spinner"></i>PENDING RECORDS</a>
            <a href="closed_records.php"><i class="fa-solid fa-circle-check"></i>CLOSED RECORDS</a>
            <a href="reports_record.php"><i class="fa-solid fa-scroll"></i>REPORTS RECORDS</a>
            <a href="archived_records.php"><i class="fa-solid fa-box-archive"></i>ARCHIVED RECORDS</a>
            <a href="add_admin.php"><i class="fa-solid fa-user-plus"></i>ADD ADMIN</a>
            <a href="history_activity.php"><i class="fa-solid fa-clipboard"></i>HISTORY ACTIVITY</a>
            <a href="manage_credit_points.php"><i class="fa-solid fa-star"></i>CREDIT POINTS</a>
        </nav>
    </div>

    <div class="header">
        <div class="profileXdate">
            <div id="datetime" class="datetime"></div>
            <div class="profile-container">
                <div onclick="toggleDropdown()">
                    <img src="uploads/<?php echo htmlspecialchars($profile_img); ?>" alt="Profile" class="profile"
                        onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22%231b5e20%22%3E%3Cpath d=%22M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z%22/%3E%3C/svg%3E';">
                </div>
                <div class="dropdown-menu" id="dropdown">
                    <ul>
                        <li>
                            <a href="profileAdmin1.php">
                                <img src="uploads/<?php echo htmlspecialchars($profile_img); ?>" alt="Profile"
                                    class="profile-icon"
                                    onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22%231b5e20%22%3E%3Cpath d=%22M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z%22/%3E%3C/svg%3E';">
                                Profile
                            </a>
                        </li>
                        <li>
                            <a class="logout" href="index.php">
                                <i class="fa-solid fa-sign-out"></i>
                                Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="main-content">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($_SESSION['success']);
                unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($_SESSION['error']);
                unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <div class="profile-container-main">
            <!-- Profile Header Card -->
            <div class="profile-header-card">
                <div class="profile-cover">
                    <div class="cover-gradient"></div>
                </div>
                <div class="profile-header-content">
                    <div class="profile-avatar-wrapper">
                        <div class="profile-avatar-container">
                            <img id="profilePreview"
                                src="uploads/<?php echo htmlspecialchars($user['profile_img'] ?? 'default.png'); ?>"
                                alt="Profile Picture" class="profile-avatar-img"
                                onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22%231b5e20%22%3E%3Cpath d=%22M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z%22/%3E%3C/svg%3E';">
                            <div class="avatar-badge">
                                <i class="fa-solid fa-user-tie"></i>
                            </div>
                        </div>
                        <div class="profile-info">
                            <h1 class="profile-name">
                                <?php
                                echo htmlspecialchars(
                                    trim(($user['first_name'] ?? '') . ' ' . ($user['middle_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))
                                    ?: 'Admin 1 User'
                                );
                                ?>
                            </h1>
                            <p class="profile-role">
                                <i class="fa-solid fa-user-gear"></i>
                                Administrator
                            </p>
                            <p class="profile-email">
                                <i class="fa-solid fa-envelope"></i>
                                <?php echo htmlspecialchars($user['email']); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Edit Form Card -->
            <div class="profile-edit-card">
                <div class="card-header">
                    <h2>
                        <i class="fa-solid fa-user-pen"></i>
                        Edit Profile Information
                    </h2>
                    <p class="card-subtitle">Update your personal details and account settings</p>
                </div>

                <form action="update_adminProfile1.php" method="POST" enctype="multipart/form-data"
                    class="profile-form">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
                    <input type="hidden" name="role" value="<?php echo htmlspecialchars($role); ?>">

                    <!-- Profile Image Upload Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fa-solid fa-image"></i>
                            Profile Picture
                        </h3>
                        <div class="upload-container">
                            <label for="profile_img" class="upload-label">
                                <div class="upload-icon">
                                    <i class="fa-solid fa-cloud-arrow-up"></i>
                                </div>
                                <span class="upload-text">Click to upload new profile picture</span>
                                <span class="upload-hint">PNG, JPG or JPEG (max. 5MB)</span>
                            </label>
                            <input type="file" id="profile_img" name="profile_img" accept="image/*"
                                onchange="previewProfile(event)" class="file-input">
                        </div>
                    </div>

                    <!-- Personal Information Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fa-solid fa-user"></i>
                            Personal Information
                        </h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="first_name">
                                    First Name <span class="required">*</span>
                                </label>
                                <div class="input-wrapper">
                                    <i class="fa-solid fa-user"></i>
                                    <input type="text" id="first_name" name="first_name"
                                        value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required
                                        placeholder="Enter first name">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="middle_name">Middle Name</label>
                                <div class="input-wrapper">
                                    <i class="fa-solid fa-user"></i>
                                    <input type="text" id="middle_name" name="middle_name"
                                        value="<?php echo htmlspecialchars($user['middle_name'] ?? ''); ?>"
                                        placeholder="Enter middle name">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="last_name">
                                    Last Name <span class="required">*</span>
                                </label>
                                <div class="input-wrapper">
                                    <i class="fa-solid fa-user"></i>
                                    <input type="text" id="last_name" name="last_name"
                                        value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required
                                        placeholder="Enter last name">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="phone">
                                    Phone Number <span class="required">*</span>
                                </label>
                                <div class="input-wrapper">
                                    <i class="fa-solid fa-phone"></i>
                                    <input type="text" id="phone" name="phone"
                                        value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required
                                        placeholder="Enter phone number">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Information Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fa-solid fa-address-card"></i>
                            Contact Information
                        </h3>
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label for="email_display">Email Address</label>
                                <div class="input-wrapper disabled">
                                    <i class="fa-solid fa-envelope"></i>
                                    <input type="email" id="email_display" name="email_display"
                                        value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                                </div>
                                <span class="field-hint">Email cannot be changed</span>
                            </div>

                            <div class="form-group full-width">
                                <label for="address">
                                    Address <span class="required">*</span>
                                </label>
                                <div class="input-wrapper">
                                    <i class="fa-solid fa-location-dot"></i>
                                    <textarea id="address" name="address" required rows="3"
                                        placeholder="Enter complete address"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Security Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fa-solid fa-lock"></i>
                            Security Settings
                        </h3>
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label for="password">New Password</label>
                                <div class="input-wrapper">
                                    <i class="fa-solid fa-key"></i>
                                    <input type="password" id="password" name="password"
                                        placeholder="Leave blank to keep current password">
                                    <button type="button" class="toggle-password" onclick="togglePasswordVisibility()">
                                        <i class="fa-solid fa-eye" id="toggleIcon"></i>
                                    </button>
                                </div>
                                <span class="field-hint">Leave empty if you don't want to change your password</span>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="button" class="btn-cancel" onclick="window.location.href='admin1_dashboard.php'">
                            <i class="fa-solid fa-xmark"></i>
                            Cancel
                        </button>
                        <button type="submit" class="btn-submit">
                            <i class="fa-solid fa-floppy-disk"></i>
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>

<script>
    function previewProfile(event) {
        const reader = new FileReader();
        reader.onload = function () {
            const output = document.getElementById('profilePreview');
            output.src = reader.result;

            // Add animation effect
            output.style.opacity = '0';
            setTimeout(() => {
                output.style.transition = 'opacity 0.3s ease';
                output.style.opacity = '1';
            }, 100);
        };
        reader.readAsDataURL(event.target.files[0]);
    }

    function togglePasswordVisibility() {
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.getElementById('toggleIcon');

        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.classList.remove('fa-eye');
            toggleIcon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            toggleIcon.classList.remove('fa-eye-slash');
            toggleIcon.classList.add('fa-eye');
        }
    }

    function toggleDropdown() {
        const dropdown = document.getElementById("dropdown");
        dropdown.classList.toggle("show");
    }

    // Close dropdown when clicking outside
    window.onclick = function (event) {
        if (!event.target.matches('.profile') && !event.target.closest('.profile-container')) {
            const dropdowns = document.getElementsByClassName("dropdown-menu");
            for (let i = 0; i < dropdowns.length; i++) {
                if (dropdowns[i].classList.contains('show')) {
                    dropdowns[i].classList.remove('show');
                }
            }
        }
    };

    // Auto-hide success/error messages
    document.addEventListener('DOMContentLoaded', function () {
        const messages = document.querySelectorAll('.message');
        messages.forEach(message => {
            setTimeout(() => {
                message.style.opacity = '0';
                setTimeout(() => message.remove(), 300);
            }, 5000);
        });

        // Burger menu toggle
        const burger = document.querySelector('.burger');
        const nav = document.querySelector('nav');
        const navContainer = document.querySelector('.nav-container');
        const body = document.body;

        if (burger && nav) {
            burger.addEventListener('click', function (e) {
                e.stopPropagation();
                this.classList.toggle('active');
                nav.classList.toggle('active');
                nav.classList.toggle('open');

                if (navContainer) {
                    navContainer.classList.toggle('expanded');
                }

                // Prevent body scroll when menu is open
                if (nav.classList.contains('active')) {
                    body.style.overflow = 'hidden';
                } else {
                    body.style.overflow = '';
                }
            });
        }

        // Close menu when clicking outside
        document.addEventListener('click', function (e) {
            if (nav && burger && !nav.contains(e.target) && !burger.contains(e.target)) {
                nav.classList.remove('active', 'open');
                burger.classList.remove('active');
                if (navContainer) {
                    navContainer.classList.remove('expanded');
                }
                body.style.overflow = '';
            }
        });

        // Close menu when clicking nav links (mobile)
        if (nav) {
            const navLinks = nav.querySelectorAll('a');
            navLinks.forEach(link => {
                link.addEventListener('click', function () {
                    if (window.innerWidth <= 768) {
                        nav.classList.remove('active', 'open');
                        if (burger) burger.classList.remove('active');
                        if (navContainer) navContainer.classList.remove('expanded');
                        body.style.overflow = '';
                    }
                });
            });
        }
    });
</script>

<script src="JAVASCRIPT/Real-Time.js"></script>

</body>

</html>