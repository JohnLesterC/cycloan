<?php
session_start();
require "CYCLOAN_db.php";

// Check if user is logged in as a superadmin
if (!isset($_SESSION["email"])) {
    header("Location: index.php");
    exit();
}

// Verify user is a superadmin
$sql = "SELECT id, email, profile_img, first_name, middle_name, last_name, phone, address FROM superadmins WHERE email = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $_SESSION["email"]);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$user) {
    $_SESSION['error'] = 'Unauthorized access. Superadmin not found.';
    header("Location: index.php");
    exit;
}

$profile_img = !empty($user['profile_img']) ? $user['profile_img'] : "default.png";

mysqli_close($conn);
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
    <link rel="stylesheet" href="CSS/superadmin_dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <title>Superadmin Profile - CYCLOAN</title>
</head>

<style>
    .dropdown-container {
        display: block;
        width: 100%;
    }

    .dropdown-btn {
        display: flex;
        align-items: center;
        cursor: pointer;
    }

    .dropdown-icon {
        margin-left: 40px;
        transition: transform 0.3s ease;
    }

    .dropdown-icon.rotate {
        transform: rotate(-180deg);
    }

    .dropdown-content {
        display: none;
        padding-left: 20px;
        flex-direction: column;
    }

    .dropdown-content a {
        font-size: 14px;
        padding: 8px 10px;
        margin: 10px;
    }
</style>

<body>
    <div class="nav-container">
        <button class="burger" aria-label="Toggle menu">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <nav>
            <img src="IMAGE/Main-Logo.png" alt="Loan System Logo" class="sidebar-logo">
            <a href="Superadmin_dashboard.php"><i class="fa-solid fa-table-columns"></i>DASHBOARD</a>
            <a href="applicant.php"><i class="fa-solid fa-users"></i>APPLICANTS</a>

            <div class="dropdown-container">
                <a href="#" class="dropdown-btn">
                    <i class="fa-solid fa-folder-open"></i> RECORDS
                    <i class="fa-solid fa-caret-down dropdown-icon"></i>
                </a>
                <div class="dropdown-content">
                    <a href="active_records.php"
                        class="<?php echo $current_page === 'active_records.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-user-check"></i> Active Records
                    </a>
                    <a href="pending_records.php"
                        class="<?php echo $current_page === 'pending_records.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-spinner"></i> Pending Records
                    </a>
                    <a href="closed_records.php"
                        class="<?php echo $current_page === 'closed_records.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-circle-check"></i> Closed Records
                    </a>
                </div>
            </div>

            <a href="reports_record.php"><i class="fa-solid fa-scroll"></i>REPORTS RECORDS</a>
            <a href="archived_records.php"><i class="fa-solid fa-box-archive"></i>ARCHIVED RECORDS</a>
            <a href="add_admin.php"><i class="fa-solid fa-user-plus"></i>ADD ADMIN</a>
            <a href="history_activity.php"><i class="fa-solid fa-clipboard"></i>HISTORY ACTIVITY</a>
            <a href="manage_credit_points.php"><i class="fa-solid fa-star"></i>CREDIT POINTS</a>
            <a href="#" onclick="openManageInterestRateModal()"><i class="fa-solid fa-percent"></i>MANAGE INTEREST
                RATE</a>
            <a href="#" onclick="openCalculatorModal()"><i class="fa-solid fa-calculator"></i>LOAN CALCULATOR</a>
        </nav>
    </div>

    <div class="header">
        <div class="profileXdate">
            <div id="datetime" class="datetime"></div>
            <div class="profile-container">
                <div onclick="toggleDropdown()">
                    <img src="uploads/<?php echo htmlspecialchars($profile_img); ?>" alt="Profile" class="profile">
                </div>
                <div class="dropdown-menu" id="dropdown">
                    <ul>
                        <li>
                            <a href="profileSuperadmin.php">
                                <img src="uploads/<?php echo htmlspecialchars($profile_img); ?>" alt="Profile"
                                    class="profile-icon">
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
                                alt="Profile Picture" class="profile-avatar-img">
                            <div class="avatar-badge">
                                <i class="fa-solid fa-crown"></i>
                            </div>
                        </div>
                        <div class="profile-info">
                            <h1 class="profile-name">
                                <?php
                                echo htmlspecialchars(
                                    trim(($user['first_name'] ?? '') . ' ' . ($user['middle_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))
                                    ?: 'Superadmin User'
                                );
                                ?>
                            </h1>
                            <p class="profile-role">
                                <i class="fa-solid fa-shield-halved"></i>
                                System Administrator
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

                <form action="update_superAdminProfile.php" method="POST" enctype="multipart/form-data"
                    class="profile-form">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($user['email']); ?>">

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
                        <button type="button" class="btn-cancel"
                            onclick="window.location.href='Superadmin_dashboard.php'">
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

<!-- Interest Rate Management Modal -->
<div id="manageInterestRateModal" class="modal" role="dialog" aria-labelledby="manageInterestRateModalLabel">
    <div class="modal-content interest-rate-modal-content">
        <div class="modal-header">
            <div class="header-content">
                <i class="fas fa-percentage header-icon"></i>
                <div>
                    <h2 id="manageInterestRateModalLabel">Interest Rate Management</h2>
                    <p class="header-subtitle">Configure and monitor loan interest rates</p>
                </div>
            </div>
            <span class="close" onclick="closeManageInterestRateModal()" role="button" aria-label="Close modal">×</span>
        </div>
        <div class="modal-body">
            <div class="current-rates-section">
                <h3><i class="fas fa-th-list"></i> Current Rates by Term</h3>
                <div class="rates-grid" id="ratesGrid">
                    <div class="rate-card-skeleton">
                        <div class="spinner"></div>
                        <p>Loading rates...</p>
                    </div>
                </div>
            </div>
            <div class="interest-rate-history">
                <h3><i class="fas fa-history"></i> Change History</h3>
                <div class="scrollable-table">
                    <table class="loan-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Term Length</th>
                                <th>Interest Rate</th>
                                <th>Updated At</th>
                                <th>Updated By</th>
                            </tr>
                        </thead>
                        <tbody id="interestRateHistoryTable">
                            <tr>
                                <td colspan="5">
                                    <div class="spinner"></div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function openManageInterestRateModal() {
        const modal = document.getElementById("manageInterestRateModal");
        const historyTable = document.getElementById("interestRateHistoryTable");
        const ratesGrid = document.getElementById("ratesGrid");

        ratesGrid.innerHTML = '<div class="rate-card-skeleton"><div class="spinner"></div><p>Loading rates...</p></div>';
        historyTable.innerHTML = '<tr><td colspan="5"><div class="spinner"></div></td></tr>';

        modal.style.display = "block";
        setTimeout(() => modal.classList.add("show"), 10);

        fetch("Superadmin_dashboard.php?action=get_all_interest_rates", { cache: "no-store" })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const rates = data.rates;
                    if (rates.length > 0) {
                        ratesGrid.innerHTML = rates.map(item => `
                            <div class="rate-card">
                                <div class="term-badge">${item.term_length} Months</div>
                                <div class="rate-display">${parseFloat(item.interest_rate).toFixed(2)}<span class="percent-sign">%</span></div>
                                <div class="updated-info">Updated: ${new Date(item.updated_at).toLocaleDateString()}</div>
                            </div>
                        `).join('');
                    } else {
                        ratesGrid.innerHTML = '<div class="rate-card-skeleton"><p>No rates configured yet.</p></div>';
                    }
                }
            })
            .catch(error => {
                ratesGrid.innerHTML = `<div class="rate-card-skeleton"><p>Error loading rates: ${error.message}</p></div>`;
            });

        fetch("Superadmin_dashboard.php?action=get_interest_rate_history", { cache: "no-store" })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.history.length > 0) {
                    historyTable.innerHTML = data.history.map(item => `
                        <tr>
                            <td>${item.id}</td>
                            <td>${item.term_length} Months</td>
                            <td>${parseFloat(item.interest_rate).toFixed(2)}%</td>
                            <td>${new Date(item.updated_at).toLocaleString()}</td>
                            <td>${item.updated_by || "System"}</td>
                        </tr>
                    `).join('');
                } else {
                    historyTable.innerHTML = '<tr><td colspan="5">No history available.</td></tr>';
                }
            })
            .catch(error => {
                historyTable.innerHTML = `<tr><td colspan="5">Error: ${error.message}</td></tr>`;
            });
    }

    function closeManageInterestRateModal() {
        const modal = document.getElementById("manageInterestRateModal");
        modal.classList.remove("show");
        setTimeout(() => modal.style.display = "none", 300);
    }

    window.addEventListener("click", (event) => {
        const modal = document.getElementById("manageInterestRateModal");
        if (event.target === modal) {
            closeManageInterestRateModal();
        }
    });

    document.querySelectorAll('.dropdown-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const dropdown = this.nextElementSibling;
            const icon = this.querySelector('.dropdown-icon');

            // Toggle dropdown visibility
            dropdown.style.display = dropdown.style.display === "block" ? "none" : "block";

            // Rotate icon
            icon.classList.toggle('rotate');
        });
    });

</script>

</body>

</html>