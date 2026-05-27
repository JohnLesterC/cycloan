<?php
session_start();
require "CYCLOAN_db.php";

if (!isset($_SESSION['email'])) {
    header("Location: index.php");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);

// Determine admin role and dashboard link
if (!isset($_SESSION['role'])) {
    $stmt = $conn->prepare("
        SELECT 'superadmin' AS role FROM superadmins WHERE email = ? 
        UNION 
        SELECT 'admin1' AS role FROM admin1 WHERE email = ? 
        UNION 
        SELECT 'admin2' AS role FROM admin2 WHERE email = ?
    ");
    if (!$stmt) {
        error_log("Role determination query preparation failed: " . $conn->error, 3, 'errors.log');
        $_SESSION['error'] = "Database error: Unable to determine user role.";
        header("Location: index.php");
        exit();
    }
    $stmt->bind_param("sss", $_SESSION['email'], $_SESSION['email'], $_SESSION['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $_SESSION['role'] = $row ? $row['role'] : 'admin2';
    $stmt->close();
}
$adminRole = $_SESSION['role'];
$dashboard_link = $adminRole === 'superadmin' ? 'Superadmin_dashboard.php' : ($adminRole === 'admin1' ? 'admin1_dashboard.php' : 'admin2_dashboard.php');
$profile_link = $adminRole === 'superadmin' ? 'profileSuperadmin.php' : ($adminRole === 'admin1' ? 'profileAdmin1.php' : 'profileAdmin2.php');

// Fetch profile image
$email = $_SESSION['email'];
$valid_roles = ['superadmin' => 'superadmins', 'admin1' => 'admin1', 'admin2' => 'admin2'];
$table_name = $valid_roles[$adminRole] ?? 'admin2';
$stmt = $conn->prepare("SELECT profile_img FROM $table_name WHERE email = ?");
if (!$stmt) {
    error_log("Profile image query preparation failed for table $table_name: " . $conn->error, 3, 'errors.log');
    $_SESSION['error'] = "Database error: Unable to fetch profile image.";
    $profile_img = 'default.png';
} else {
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    $upload_dir = __DIR__ . '/uploads/';
    $default_img = 'default.png';
    $profile_img = !empty($user['profile_img']) && file_exists($upload_dir . $user['profile_img'])
        ? $user['profile_img']
        : $default_img;

    if (!file_exists($upload_dir . $profile_img)) {
        error_log("Profile image not found: $upload_dir$profile_img", 3, 'errors.log');
        $profile_img = $default_img;
    }
}


$rows_per_page = 5;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;

$start = ($page - 1) * $rows_per_page;


$total_records_query = $conn->query("SELECT COUNT(*) AS total FROM logattempts");
$total_records = $total_records_query->fetch_assoc()['total'];
$pages = ceil($total_records / $rows_per_page);

$result = $conn->query("SELECT * FROM logattempts ORDER BY attempt DESC LIMIT $start, $rows_per_page");

$login_attempts = $result->fetch_all(MYSQLI_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Trails - CYCLOAN</title>
    <link rel="stylesheet" href="CSS/history.css">
    <link rel="stylesheet" href="CSS/admin_profile.css">
    <link rel="stylesheet" href="CSS/admin_dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
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
            <a href="<?php echo htmlspecialchars($dashboard_link); ?>"
                class="<?php echo $current_page === $dashboard_link ? 'active' : ''; ?>">
                <i class="fa-solid fa-table-columns"></i> DASHBOARD
            </a>
            <a href="applicant.php" class="<?php echo $current_page === 'applicant.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-users"></i> APPLICANTS
            </a>

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

            <a href="reports_record.php" class="<?php echo $current_page === 'reports_record.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-scroll"></i> REPORTS RECORDS
            </a>
            <?php if ($adminRole === 'admin1' || $adminRole === 'superadmin'): ?>
                <a href="archived_records.php"
                    class="<?php echo $current_page === 'archived_records.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-archive"></i> ARCHIVED RECORDS
                </a>
                <a href="add_admin.php" class="<?php echo $current_page === 'add_admin.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-user-shield"></i> ADMIN MANAGEMENT
                </a>
            <?php endif; ?>
            <a href="history_activity.php"
                class="<?php echo $current_page === 'history_activity.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-clipboard"></i> AUDIT TRAILS
            </a>
            <a href="#" id="viewInterestRatesBtn" class="view-interest-rate-link">
                <i class="fa-solid fa-percent"></i> VIEW INTEREST RATES
            </a>
            <?php if ($adminRole === 'admin1' || $adminRole === 'superadmin'): ?>
                <a href="manage_credit_points.php"
                    class="<?php echo $current_page === 'manage_credit_points.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-star"></i> MANAGE CREDIT RATE
                </a>
            <?php endif; ?>
            <?php if ($adminRole === 'superadmin'): ?>
                <a href="#" onclick="openManageInterestRateModal()" class="manage-interest-rate-link">
                    <i class="fa-solid fa-percent"></i> MANAGE INTEREST RATE
                </a>
            <?php endif; ?>
        </nav>
    </div>

    <div class="header">
        <div class="profileXdate">
            <div id="datetime" class="datetime"></div>
            <a href="notifications_enhanced.php" class="notification-bell" title="View Notifications">
                <i class="fa-solid fa-bell"></i>
            </a>
            <div class="profile-container">
                <div onclick="toggleDropdown(event)" role="button" aria-label="Toggle profile menu" tabindex="0"
                    onkeydown="handleProfileKeydown(event)">
                    <img src="uploads/<?php echo htmlspecialchars($profile_img); ?>" alt="Profile Image" class="profile"
                        onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22%231b5e20%22%3E%3Cpath d=%22M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z%22/%3E%3C/svg%3E';">
                </div>
                <div class="dropdown-menu" id="dropdown" role="menu">
                    <ul>
                        <li role="none">
                            <a href="<?php echo htmlspecialchars($profile_link); ?>" role="menuitem" tabindex="-1">
                                <img src="uploads/<?php echo htmlspecialchars($profile_img); ?>" alt="Profile Image"
                                    class="profile-icon"
                                    onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22%231b5e20%22%3E%3Cpath d=%22M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z%22/%3E%3C/svg%3E';">
                                Profile
                            </a>
                        </li>
                        <li role="none">
                            <a class="logout" href="index.php" role="menuitem" tabindex="-1">
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
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($_SESSION['error']); ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="page-header">
            <div class="header-content">
                <div class="header-icon">
                    <i class="fas fa-history"></i>
                </div>
                <div class="header-text">
                    <h1>Audit Trails</h1>
                    <p>Track and monitor all login attempts to your account.</p>
                </div>
            </div>
            <div class="header-stats">
                <div class="stat-item success">
                    <i class="fas fa-check-circle"></i>
                    <span class="stat-number">
                        <?php echo count(array_filter($login_attempts, function ($a) {
                            return $a['success'];
                        })); ?>
                    </span>
                    <span class="stat-label">Successful</span>
                </div>
                <div class="stat-item failed">
                    <i class="fas fa-times-circle"></i>
                    <span class="stat-number">
                        <?php echo count(array_filter($login_attempts, function ($a) {
                            return !$a['success'];
                        })); ?>
                    </span>
                    <span class="stat-label">Failed</span>
                </div>
            </div>
        </div>

        <div class="history-container">
            <?php if (empty($login_attempts)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <h3>No Audit Trails</h3>
                    <p>There are no login attempts recorded for this account yet.</p>
                </div>
            <?php else: ?>
                <div class="history-card">
                    <div class="card-header">
                        <div class="header-actions">
                            <button class="btn-filter" onclick="filterHistory('all')" data-filter="all">
                                <i class="fas fa-list"></i> All
                            </button>
                            <button class="btn-filter" onclick="filterHistory('success')" data-filter="success">
                                <i class="fas fa-check"></i> Success
                            </button>
                            <button class="btn-filter" onclick="filterHistory('failed')" data-filter="failed">
                                <i class="fas fa-times"></i> Failed
                            </button>
                        </div>
                    </div>
                    <div class="table-wrapper">
                        <table class="history-table" role="grid" aria-describedby="login-history-info">
                            <thead>
                                <tr>
                                    <th scope="col"><i class="fas fa-envelope"></i> Email</th>
                                    <th scope="col"><i class="fas fa-info-circle"></i> Status</th>
                                    <th scope="col"><i class="fas fa-clock"></i> Attempt Time</th>
                                    <th scope="col"><i class="fas fa-calendar"></i> Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($login_attempts as $attempt): ?>
                                    <tr class="history-row"
                                        data-status="<?php echo $attempt['success'] ? 'success' : 'failed'; ?>">
                                        <td data-label="Email">
                                            <div class="email-cell">
                                                <i class="fas fa-user-circle"></i>
                                                <?php echo htmlspecialchars($attempt['email']); ?>
                                            </div>
                                        </td>
                                        <td data-label="Status">
                                            <span
                                                class="status-badge badge-<?php echo $attempt['success'] ? 'success' : 'failed'; ?>">
                                                <i
                                                    class="fas fa-<?php echo $attempt['success'] ? 'check-circle' : 'times-circle'; ?>"></i>
                                                <?php echo $attempt['success'] ? 'Successful' : 'Failed'; ?>
                                            </span>
                                        </td>
                                        <td data-label="Attempt Time">
                                            <div class="time-cell">
                                                <i class="fas fa-clock"></i>
                                                <?php echo htmlspecialchars(date('h:i:s A', strtotime($attempt['attempt']))); ?>
                                            </div>
                                        </td>
                                        <td data-label="Date">
                                            <div class="date-cell">
                                                <i class="fas fa-calendar-alt"></i>
                                                <?php echo htmlspecialchars(date('M d, Y', strtotime($attempt['attempt']))); ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <div class="pagination-container">
                            <button class="pagination-btn" <?php if ($page <= 1)
                                echo 'disabled'; ?>
                                onclick="window.location.href='?page=<?php echo $page - 1; ?>'">
                                <i class="fas fa-chevron-left"></i>
                            </button>

                            <span class="page-indicator">Page <?php echo $page; ?> of <?php echo $pages; ?></span>

                            <button class="pagination-btn" <?php if ($page >= $pages)
                                echo 'disabled'; ?>
                                onclick="window.location.href='?page=<?php echo $page + 1; ?>'">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>


                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>

        document.addEventListener("DOMContentLoaded", () => {
            const tableBody = document.querySelector(".history-table tbody");

            // Add fade-in animation when page loads
            tableBody.classList.add("fade-in");

            // Function to animate table before navigating
            function navigateWithAnimation(url) {
                tableBody.classList.remove("fade-in");
                tableBody.classList.add("fade-out");
                setTimeout(() => {
                    window.location.href = url;
                }, 350); // match transition time
            }

            // Override pagination button behavior
            const prevBtn = document.querySelector('.pagination-btn:first-child');
            const nextBtn = document.querySelector('.pagination-btn:last-child');

            if (prevBtn) {
                prevBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const url = prevBtn.getAttribute('onclick').match(/'(.*?)'/)[1];
                    navigateWithAnimation(url);
                });
            }

            if (nextBtn) {
                nextBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const url = nextBtn.getAttribute('onclick').match(/'(.*?)'/)[1];
                    navigateWithAnimation(url);
                });
            }
        });



        // Toggle sidebar and burger button
        document.querySelector('.burger').addEventListener('click', function () {
            this.classList.toggle('active');
            document.querySelector('nav').classList.toggle('active');
        });

        // Close sidebar when clicking a nav link
        document.querySelectorAll('nav a').forEach(link => {
            link.addEventListener('click', function () {
                document.querySelector('nav').classList.remove('active');
                document.querySelector('.burger').classList.remove('active');
            });
        });

        function toggleDropdown(event) {
            event.stopPropagation();
            const dropdown = document.getElementById("dropdown");
            dropdown.classList.toggle("show");
        }

        function handleProfileKeydown(event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                toggleDropdown(event);
            }
        }

        window.onclick = function (event) {
            if (!event.target.closest(".profile-container")) {
                const dropdowns = document.getElementsByClassName("dropdown-menu");
                for (let i = 0; i < dropdowns.length; i++) {
                    if (dropdowns[i].classList.contains("show")) {
                        dropdowns[i].classList.remove("show");
                    }
                }
            }
        };

        // Filter history function
        function filterHistory(status) {
            const rows = document.querySelectorAll('.history-row');
            const buttons = document.querySelectorAll('.btn-filter');

            // Remove active class from all buttons
            buttons.forEach(btn => btn.classList.remove('active'));

            // Add active class to clicked button
            event.target.closest('.btn-filter').classList.add('active');

            rows.forEach(row => {
                if (status === 'all' || row.dataset.status === status) {
                    row.style.display = '';
                    setTimeout(() => {
                        row.style.opacity = '1';
                        row.style.transform = 'translateX(0)';
                    }, 10);
                } else {
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(-20px)';
                    setTimeout(() => {
                        row.style.display = 'none';
                    }, 300);
                }
            });
        }

        // Set default active filter on load
        window.addEventListener('DOMContentLoaded', () => {
            const allButton = document.querySelector('[data-filter="all"]');
            if (allButton) {
                allButton.classList.add('active');
            }
        });
    </script>
    <script src="JAVASCRIPT/Real-Time.js"></script>
    <script>
        // ========== SIDEBAR TOGGLE (ENHANCED) ==========
        document.addEventListener('DOMContentLoaded', function () {
            const burger = document.querySelector('.burger');
            const nav = document.querySelector('nav');
            const navContainer = document.querySelector('.nav-container');
            const body = document.body;

            // Toggle burger menu
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
                <span class="close" onclick="closeManageInterestRateModal()" role="button"
                    aria-label="Close modal">×</span>
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