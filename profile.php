<?php
session_start();
require "CYCLOAN_db.php";
require "credit_points_manager.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /CYCLOAN/index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Database query with error handling for user data
try {
    $sql = "SELECT * FROM users1 WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare statement failed: " . $conn->error);
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();

    if (!$user_data) {
        throw new Exception("User not found");
    }

    $stmt->close();
} catch (Exception $e) {
    error_log($e->getMessage());
    die("An error occurred while fetching user data.");
}

// Fetch profile image
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT profile_image FROM users1 WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $profile_image = !empty($row['profile_image']) ? $row['profile_image'] : 'assets/default.png';
    } else {
        $profile_image = 'assets/default.png';
    }
    $stmt->close();
} else {
    $profile_image = 'assets/default.png';
}

// Fetch user's credit points
try {
    $creditManager = getCreditPointsManager();
    $userCreditPoints = $creditManager->getUserPoints($user_id);
} catch (Exception $e) {
    error_log("Credit points fetch error: " . $e->getMessage());
    $userCreditPoints = 0;
}

// Sanitize user data
$user_data = array_map('htmlspecialchars', $user_data);

// Get values for select options
$house_ownership = $user_data['house_ownership'];
$name_extension = $user_data['name_extension'];
$civil_status = $user_data['civil_status'];
$res_barangay = $user_data['res_barangay'];
$bus_barangay = $user_data['bus_barangay'];
$reg_voter = $user_data['reg_voter'];
$profile_image = $user_data['profile_image'] ?? 'http://localhost/CYCLOAN/IMAGE/2x2.png';

// Check for pending or active loans
$hasPendingActiveLoan = false;
try {
    $sql = "SELECT status FROM loans WHERE user_id = ? AND status IN ('Pending', 'Active')";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare statement failed: " . $conn->error);
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $hasPendingActiveLoan = true;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log($e->getMessage());
    // Log the error but proceed without blocking (optional fallback)
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - CYCLOAN</title>
    <link rel="stylesheet" href="CSS/profile.css">
    <link rel="stylesheet" href="CSS/user_dashboard.css">
    <link rel="stylesheet" href="CSS/admin1_dashboard.css">
    <link rel="stylesheet" href="CSS/admin_dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
</head>

<body>
    <div class="header">
        <div class="profileXdate">
            <div id="datetime" class="datetime"></div>
            <div class="profile-container">
                <div class="profile" onclick="toggleDropdown(event)">
                    <img src="<?= $profile_image ?>" alt="Profile Image" class="profile">
                </div>
                <div class="dropdown-menu" id="dropdown">
                    <ul>
                        <li>
                            <a href="profile.php" class="<?= $current_page === 'profile.php' ? 'active' : '' ?>">
                                <img src="<?= htmlspecialchars($profile_image) ?>" alt="Profile Image"
                                    class="profile-icon"> Profile
                            </a>
                        </li>
                        <li>
                            <a class="logout" href="index.php"><i class="fa-solid fa-sign-out"></i> Logout</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div>
        <nav>
            <img src="IMAGE/Main-Logo.png" alt="Loan System Logo" class="sidebar-logo">
            <a href="user_dashboard.php"><i class="fa-solid fa-table-columns"></i>DASHBOARD</a>
            <a href="user_active_record.php"><i class="fa-solid fa-user-check"></i>ACTIVE RECORDS</a>
            <a href="user_pending_records.php"><i class="fa-solid fa-spinner"></i>PENDING RECORDS</a>
            <a href="user_closed_records.php"><i class="fa-solid fa-circle-check"></i>CLOSED RECORDS</a>
            <a href="user_history_activity.php"><i class="fa-solid fa-clipboard"></i>HISTORY ACTIVITY</a>
            <a href="#" onclick="openCalculatorModal()"><i class="fa-solid fa-calculator"></i>LOAN CALCULATOR</a>
        </nav>
    </div>

    <div class="main-content">
        <h2 class="edit-profile-text">Edit Profile</h2>

        <!-- Credit Points Badge -->
        <div class="profile-credit-badge">
            <div class="badge-icon">
                <i class="fas fa-star"></i>
            </div>
            <div class="badge-content">
                <span class="badge-label">Your Credit Score</span>
                <span class="badge-value"><?php echo number_format($userCreditPoints); ?> Points</span>
            </div>
        </div>

        <?php
        if (isset($_SESSION['success'])) {
            echo '<div class="message success"><i class="fas fa-check-circle"></i>' . htmlspecialchars($_SESSION['success']) . '</div>';
            unset($_SESSION['success']);
        }
        if (isset($_SESSION['error'])) {
            echo '<div class="message error"><i class="fas fa-exclamation-circle"></i>' . htmlspecialchars($_SESSION['error']) . '</div>';
            unset($_SESSION['error']);
        }
        if ($hasPendingActiveLoan) {
            echo '<div class="message error"><i class="fas fa-exclamation-circle"></i>You cannot update your profile while you have a pending or active loan. Please resolve your loan status first.</div>';
        }
        ?>
        <form action="update_profile.php" method="POST" id="profileForm" enctype="multipart/form-data" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
            <div class="edit-profile-container">
                <div class="profile-image">
                    <img id="profileImagePreview" src="<?= $profile_image ?>" alt="Profile Image">
                    <label for="profile_image" class="upload-label"><i class="fas fa-camera"></i> Upload New
                        Image</label>
                    <input type="file" id="profile_image" name="profile_image" accept="image/*"
                        onchange="previewImage(event)" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                </div>
                <div class="profile-information">
                    <h3>Personal Information</h3>
                    <div class="form-group">
                        <label class="required">First Name</label>
                        <input type="text" name="first_name" id="first_name" required
                            value="<?= $user_data['first_name'] ?>" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                        <div class="error-message" id="first_name-error"></div>
                    </div>
                    <div class="form-group">
                        <label>Middle Name</label>
                        <input type="text" name="middle_name" id="middle_name" value="<?= $user_data['middle_name'] ?>"
                            <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                    </div>
                    <div class="form-group">
                        <label class="required">Last Name</label>
                        <input type="text" name="last_name" id="last_name" required
                            value="<?= $user_data['last_name'] ?>" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                        <div class="error-message" id="last_name-error"></div>
                    </div>
                    <div class="form-group">
                        <label>Name Extension</label>
                        <select name="name_extension" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                            <option value="">None</option>
                            <option value="Jr." <?= $name_extension === 'Jr.' ? 'selected' : '' ?>>Jr.</option>
                            <option value="Sr." <?= $name_extension === 'Sr.' ? 'selected' : '' ?>>Sr.</option>
                            <option value="I" <?= $name_extension === 'I' ? 'selected' : '' ?>>I</option>
                            <option value="II" <?= $name_extension === 'II' ? 'selected' : '' ?>>II</option>
                            <option value="III" <?= $name_extension === 'III' ? 'selected' : '' ?>>III</option>
                            <option value="IV" <?= $name_extension === 'IV' ? 'selected' : '' ?>>IV</option>
                            <option value="V" <?= $name_extension === 'V' ? 'selected' : '' ?>>V</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Nick Name</label>
                        <input type="text" name="nick_name" id="nick_name" value="<?= $user_data['nick_name'] ?>" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                    </div>
                    <div class="form-group">
                        <label class="required">Birthday</label>
                        <input type="date" name="birthday" id="birthday" required onchange="calculateAge()"
                            value="<?= $user_data['birthday'] ?>" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                        <div class="error-message" id="birthday-error"></div>
                    </div>
                    <div class="form-group">
                        <label class="required">Age</label>
                        <input type="number" name="age" id="age" required min="0" readonly
                            value="<?= $user_data['age'] ?>" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                    </div>
                    <div class="form-group">
                        <label class="required">Birth Place</label>
                        <input type="text" name="birth_place" id="birth_place" required
                            value="<?= $user_data['birth_place'] ?>" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                        <div class="error-message" id="birth_place-error"></div>
                    </div>
                    <div class="form-group">
                        <label class="required">Civil Status</label>
                        <select name="civil_status" id="civil_status" required onchange="toggleSpouseSection()" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                            <option value="">Select Status</option>
                            <option value="Single" <?= $civil_status === 'Single' ? 'selected' : '' ?>>Single</option>
                            <option value="Married" <?= $civil_status === 'Married' ? 'selected' : '' ?>>Married</option>
                            <option value="Widowed" <?= $civil_status === 'Widowed' ? 'selected' : '' ?>>Widowed</option>
                            <option value="Separated" <?= $civil_status === 'Separated' ? 'selected' : '' ?>>Separated
                            </option>
                            <option value="Divorced" <?= $civil_status === 'Divorced' ? 'selected' : '' ?>>Divorced
                            </option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="required">Contact</label>
                        <input type="text" name="contact" id="contact" required pattern="\d{11}"
                            title="Please enter a valid 11-digit phone number" value="<?= $user_data['contact'] ?>"
                            <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                        <div class="error-message" id="contact-error"></div>
                    </div>
                    <div class="form-group">
                        <label class="required">Email</label>
                        <input type="email" name="email" id="email" required value="<?= $user_data['email'] ?>" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                        <div class="error-message" id="email-error"></div>
                    </div>
                    <div class="form-group">
                        <label>Facebook Account</label>
                        <input type="text" name="fb_account" id="fb_account" value="<?= $user_data['fb_account'] ?>"
                            <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                    </div>

                    <h3>Residential Address (Calamba City)</h3>
                    <div class="address-container">
                        <div class="form-group">
                            <label>House/Unit No.</label>
                            <input type="text" name="res_house_no" id="res_house_no"
                                value="<?= $user_data['res_house_no'] ?>" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-group">
                            <label>Street/Block/Lot</label>
                            <input type="text" name="res_street" id="res_street" value="<?= $user_data['res_street'] ?>"
                                <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-group">
                            <label>Subdivision/Village</label>
                            <input type="text" name="res_subdivision" id="res_subdivision"
                                value="<?= $user_data['res_subdivision'] ?>" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-group">
                            <label class="required">Barangay</label>
                            <select name="res_barangay" id="res_barangay" required <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                                <option value="">Select Barangay</option>
                                <?php
                                $barangays = [
                                    "Bagong Kalsada",
                                    "Banadero",
                                    "Banlic",
                                    "Barangay 1",
                                    "Barangay 2",
                                    "Barangay 3",
                                    "Batino",
                                    "Bubuyan",
                                    "Bucal",
                                    "Butong",
                                    "Canlubang",
                                    "Halang",
                                    "Hornalan",
                                    "Kay-Anlog",
                                    "La Mesa",
                                    "Laguerta",
                                    "Lingga",
                                    "Looc",
                                    "Majada Out",
                                    "Makiling",
                                    "Mapagong",
                                    "Masili",
                                    "Maunong",
                                    "Mayapa",
                                    "Milagrosa",
                                    "Paciano Rizal",
                                    "Palo-Alto",
                                    "Pansol",
                                    "Parian",
                                    "Puting Lupa",
                                    "Puypuy",
                                    "Real",
                                    "Sainan",
                                    "Sampiruhan",
                                    "San Cristobal",
                                    "San Jose",
                                    "San Juan",
                                    "Sirang Lupa",
                                    "Sucol",
                                    "Tulo",
                                    "Turbina",
                                    "Ulango"
                                ];
                                foreach ($barangays as $barangay) {
                                    $selected = $res_barangay === $barangay ? 'selected' : '';
                                    echo "<option value='$barangay' $selected>$barangay</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group full-width">
                        <label class="required">Complete Address</label>
                        <input type="text" name="res_address" id="res_address" readonly
                            value="<?= $user_data['res_address'] ?>" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                    </div>

                    <h3>Business Address (Calamba City)</h3>
                    <div id="business_address_section">
                        <div class="address-container">
                            <div class="form-group">
                                <label>Building/Unit No.</label>
                                <input type="text" name="bus_bldg_no" id="bus_bldg_no"
                                    value="<?= $user_data['bus_bldg_no'] ?>" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                            </div>
                            <div class="form-group">
                                <label>Street/Block/Lot</label>
                                <input type="text" name="bus_street" id="bus_street"
                                    value="<?= $user_data['bus_street'] ?>" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                            </div>
                            <div class="form-group">
                                <label>Subdivision/Village</label>
                                <input type="text" name="bus_subdivision" id="bus_subdivision"
                                    value="<?= $user_data['bus_subdivision'] ?>" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                            </div>
                            <div class="form-group">
                                <label class="required">Barangay</label>
                                <select name="bus_barangay" id="bus_barangay" required <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                                    <option value="">Select Barangay</option>
                                    <?php
                                    foreach ($barangays as $barangay) {
                                        $selected = $bus_barangay === $barangay ? 'selected' : '';
                                        echo "<option value='$barangay' $selected>$barangay</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group full-width">
                            <label>Complete Business Address</label>
                            <input type="text" name="bus_address" id="bus_address" readonly
                                value="<?= $user_data['bus_address'] ?>" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="required">House Ownership</label>
                        <select name="house_ownership" required <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                            <option value="">Select Ownership</option>
                            <option value="Owned" <?= $house_ownership === 'Owned' ? 'selected' : '' ?>>Owned</option>
                            <option value="Rented" <?= $house_ownership === 'Rented' ? 'selected' : '' ?>>Rented</option>
                            <option value="Living with Parents/Relatives" <?= $house_ownership === 'Living with Parents/Relatives' ? 'selected' : '' ?>>Living with Parents/Relatives</option>
                            <option value="Mortgaged" <?= $house_ownership === 'Mortgaged' ? 'selected' : '' ?>>Mortgaged
                            </option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="required">Occupation</label>
                        <input type="text" name="occupation" id="occupation" required
                            value="<?= $user_data['occupation'] ?>" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                        <div class="error-message" id="occupation-error"></div>
                    </div>
                    <div class="form-group">
                        <label>Registered Voter?</label>
                        <select name="reg_voter" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                            <option value="Yes" <?= $reg_voter === 'Yes' ? 'selected' : '' ?>>Yes</option>
                            <option value="No" <?= $reg_voter === 'No' ? 'selected' : '' ?>>No</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="required">Years as Resident</label>
                        <input type="number" name="year_resident" id="year_resident" min="0" required
                            value="<?= $user_data['year_resident'] ?>" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                        <div class="error-message" id="year_resident-error"></div>
                    </div>

                    <h3>Spouse Information</h3>
                    <div id="spouse_section" style="display: <?= $civil_status === 'Married' ? 'block' : 'none' ?>">
                        <div class="form-group">
                            <label class="spouse-required">First Name</label>
                            <input type="text" name="spouse_first_name" id="spouse_first_name" class="spouse-required"
                                value="<?= htmlspecialchars($user_data['spouse_first_name'] ?? '') ?>" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                            <div class="error-message" id="spouse_first_name-error"></div>
                        </div>
                        <div class="form-group">
                            <label>Middle Name</label>
                            <input type="text" name="spouse_middle_name" class="spouse-required"
                                value="<?= htmlspecialchars($user_data['spouse_middle_name'] ?? '') ?>" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-group">
                            <label class="spouse-required">Last Name</label>
                            <input type="text" name="spouse_last_name" id="spouse_last_name" class="spouse-required"
                                value="<?= htmlspecialchars($user_data['spouse_last_name'] ?? '') ?>" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                            <div class="error-message" id="spouse_last_name-error"></div>
                        </div>
                        <div class="form-group">
                            <label>Name Extension</label>
                            <select name="spouse_name_extension" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                                <option value="">None</option>
                                <option value="Jr." <?= ($user_data['spouse_name_extension'] ?? '') === 'Jr.' ? 'selected' : '' ?>>Jr.</option>
                                <option value="Sr." <?= ($user_data['spouse_name_extension'] ?? '') === 'Sr.' ? 'selected' : '' ?>>Sr.</option>
                                <option value="I" <?= ($user_data['spouse_name_extension'] ?? '') === 'I' ? 'selected' : '' ?>>I</option>
                                <option value="II" <?= ($user_data['spouse_name_extension'] ?? '') === 'II' ? 'selected' : '' ?>>II</option>
                                <option value="III" <?= ($user_data['spouse_name_extension'] ?? '') === 'III' ? 'selected' : '' ?>>III</option>
                                <option value="IV" <?= ($user_data['spouse_name_extension'] ?? '') === 'IV' ? 'selected' : '' ?>>IV</option>
                                <option value="V" <?= ($user_data['spouse_name_extension'] ?? '') === 'V' ? 'selected' : '' ?>>V</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Nick Name</label>
                            <input type="text" name="spouse_nick_name" class="spouse-required"
                                value="<?= htmlspecialchars($user_data['spouse_nick_name'] ?? '') ?>" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-group">
                            <label>Registered Voter?</label>
                            <select name="spouse_reg_voter" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                                <option value="Yes" <?= ($user_data['spouse_reg_voter'] ?? '') === 'Yes' ? 'selected' : '' ?>>Yes</option>
                                <option value="No" <?= ($user_data['spouse_reg_voter'] ?? '') === 'No' ? 'selected' : '' ?>>No</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="spouse-required">Birthday</label>
                            <input type="date" name="spouse_birthday" id="spouse_birthday" class="spouse-required"
                                onchange="calculateSpouseAge()"
                                value="<?= htmlspecialchars($user_data['spouse_birthday'] ?? '') ?>" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                            <div class="error-message" id="spouse_birthday-error"></div>
                        </div>
                        <div class="form-group">
                            <label class="spouse-required">Age</label>
                            <input type="number" name="spouse_age" id="spouse_age" min="0" readonly
                                value="<?= htmlspecialchars($user_data['spouse_age'] ?? '') ?>" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-group">
                            <label class="spouse-required">Occupation</label>
                            <input type="text" name="spouse_occupation" class="spouse-required"
                                value="<?= htmlspecialchars($user_data['spouse_occupation'] ?? '') ?>" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                            <div class="error-message" id="spouse_occupation-error"></div>
                        </div>
                        <div class="form-group">
                            <label>Number of Dependents</label>
                            <input type="number" name="spouse_dependents" min="0" class="spouse-required"
                                value="<?= htmlspecialchars($user_data['spouse_dependents'] ?? '') ?>" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-group">
                            <label>Birth Place</label>
                            <input type="text" name="spouse_birth_place" class="spouse-required"
                                value="<?= htmlspecialchars($user_data['spouse_birth_place'] ?? '') ?>" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-group">
                            <label class="spouse-required">Contact</label>
                            <input type="text" name="spouse_contact" pattern="\d{11}"
                                title="Please enter a valid 11-digit phone number" class="spouse-required"
                                value="<?= htmlspecialchars($user_data['spouse_contact'] ?? '') ?>" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                            <div class="error-message" id="spouse_contact-error"></div>
                        </div>
                        <div class="form-group">
                            <label class="spouse-required">Email</label>
                            <input type="email" name="spouse_email" class="spouse-required"
                                value="<?= htmlspecialchars($user_data['spouse_email'] ?? '') ?>" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                            <div class="error-message" id="spouse_email-error"></div>
                        </div>
                        <div class="form-group">
                            <label>Facebook Account</label>
                            <input type="text" name="spouse_fb_account" class="spouse-required"
                                value="<?= htmlspecialchars($user_data['spouse_fb_account'] ?? '') ?>" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                        </div>
                    </div>

                    <h3>Sources of Income</h3>
                    <div class="checkbox-group">
                        <div class="form-group">
                            <label>Business Income (₱)</label>
                            <input type="number" name="business" min="0" step="0.01" class="income-amount"
                                data-source="business" value="<?= htmlspecialchars($user_data['business'] ?? '') ?>"
                                <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                            <div class="error-message" id="business-error"></div>
                        </div>
                        <div class="form-group">
                            <label>Salary Income (₱)</label>
                            <input type="number" name="salary" min="0" step="0.01" class="income-amount"
                                data-source="salary" value="<?= htmlspecialchars($user_data['salary'] ?? '') ?>" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                            <div class="error-message" id="salary-error"></div>
                        </div>
                        <div class="form-group">
                            <label>Remittance Income (₱)</label>
                            <input type="number" name="remittance" min="0" step="0.01" class="income-amount"
                                data-source="remittance" value="<?= htmlspecialchars($user_data['remittance'] ?? '') ?>"
                                <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                            <div class="error-message" id="remittance-error"></div>
                        </div>
                        <div class="form-group">
                            <label>Other Income (₱)</label>
                            <input type="number" name="other_income" min="0" step="0.01" class="income-amount"
                                data-source="other" value="<?= htmlspecialchars($user_data['other_income'] ?? '') ?>"
                                <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                            <div class="error-message" id="other_income-error"></div>
                        </div>
                        <div class="form-group">
                            <label>Secondary Business Income (₱)</label>
                            <input type="number" name="business2" min="0" step="0.01" class="income-amount"
                                data-source="business2" value="<?= htmlspecialchars($user_data['business2'] ?? '') ?>"
                                <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                            <div class="error-message" id="business2-error"></div>
                        </div>
                        <div class="form-group">
                            <label>Secondary Salary Income (₱)</label>
                            <input type="number" name="salary2" min="0" step="0.01" class="income-amount"
                                data-source="salary2" value="<?= htmlspecialchars($user_data['salary2'] ?? '') ?>" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                            <div class="error-message" id="salary2-error"></div>
                        </div>
                    </div>
                    <div class="total-section">
                        <div class="form-group">
                            <label><strong>Total Monthly Income (₱)</strong></label>
                            <input type="number" name="net_income" min="0" step="0.01" readonly
                                value="<?= htmlspecialchars($user_data['net_income'] ?? '') ?>" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                        </div>
                    </div>

                    <h3>Monthly Expenditures</h3>
                    <div class="checkbox-group">
                        <div class="form-group">
                            <label>Food Allowance (₱)</label>
                            <input type="number" name="food_allowance" min="0" step="0.01" class="expenditure-amount"
                                data-type="food" value="<?= htmlspecialchars($user_data['food_allowance'] ?? '') ?>"
                                <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                            <div class="error-message" id="food_allowance-error"></div>
                        </div>
                        <div class="form-group">
                            <label>Electricity Bill (₱)</label>
                            <input type="number" name="electricity_bill" min="0" step="0.01" class="expenditure-amount"
                                data-type="electricity"
                                value="<?= htmlspecialchars($user_data['electricity_bill'] ?? '') ?>" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                            <div class="error-message" id="electricity_bill-error"></div>
                        </div>
                        <div class="form-group">
                            <label>Water Bill (₱)</label>
                            <input type="number" name="water_bill" min="0" step="0.01" class="expenditure-amount"
                                data-type="water" value="<?= htmlspecialchars($user_data['water_bill'] ?? '') ?>" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                            <div class="error-message" id="water_bill-error"></div>
                        </div>
                        <div class="form-group">
                            <label>Internet Bill (₱)</label>
                            <input type="number" name="internet_bill" min="0" step="0.01" class="expenditure-amount"
                                data-type="internet" value="<?= htmlspecialchars($user_data['internet_bill'] ?? '') ?>"
                                <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                            <div class="error-message" id="internet_bill-error"></div>
                        </div>
                        <div class="form-group">
                            <label>Gas Bill (₱)</label>
                            <input type="number" name="gas_bill" min="0" step="0.01" class="expenditure-amount"
                                data-type="gas" value="<?= htmlspecialchars($user_data['gas_bill'] ?? '') ?>" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                            <div class="error-message" id="gas_bill-error"></div>
                        </div>
                        <div class="form-group">
                            <label>Educational Allowance (₱)</label>
                            <input type="number" name="educational_allowance" min="0" step="0.01"
                                class="expenditure-amount" data-type="education"
                                value="<?= htmlspecialchars($user_data['educational_allowance'] ?? '') ?>" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                            <div class="error-message" id="educational_allowance-error"></div>
                        </div>
                        <div class="form-group">
                            <label>Car Amortization (₱)</label>
                            <input type="number" name="car_amortization" min="0" step="0.01" class="expenditure-amount"
                                data-type="car" value="<?= htmlspecialchars($user_data['car_amortization'] ?? '') ?>"
                                <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                            <div class="error-message" id="car_amortization-error"></div>
                        </div>
                        <div class="form-group">
                            <label>Insurance (₱)</label>
                            <input type="number" name="insurance" min="0" step="0.01" class="expenditure-amount"
                                data-type="insurance" value="<?= htmlspecialchars($user_data['insurance'] ?? '') ?>"
                                <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                            <div class="error-message" id="insurance-error"></div>
                        </div>
                        <div class="form-group">
                            <label>Other Expenses (₱)</label>
                            <input type="number" name="other_expense" min="0" step="0.01" class="expenditure-amount"
                                data-type="other" value="<?= htmlspecialchars($user_data['other_expense'] ?? '') ?>"
                                <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                            <div class="error-message" id="other_expense-error"></div>
                        </div>
                    </div>
                    <div class="total-section">
                        <div class="form-group">
                            <label><strong>Total Monthly Expenditures (₱)</strong></label>
                            <input type="number" name="expenditures" min="0" step="0.01" readonly
                                value="<?= htmlspecialchars($user_data['expenditures'] ?? '') ?>" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                        </div>
                    </div>
                    <div class="total-section">
                        <div class="form-group">
                            <label>Expected Monthly Amortization (₱)</label>
                            <input type="number" name="expected_monthly_amortization" min="0" step="0.01"
                                value="<?= htmlspecialchars($user_data['expected_monthly_amortization'] ?? '0') ?>"
                                <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                        </div>
                    </div>
                    <div class="total-section">
                        <div class="form-group">
                            <label><strong>Remaining Monthly Income (₱)</strong></label>
                            <input type="number" name="remaining_income" min="0" step="0.01" readonly
                                value="<?= htmlspecialchars($user_data['remaining_income'] ?? '') ?>" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>
                        </div>
                    </div>
                </div>
                <button type="button" class="submit-btn" onclick="confirmSubmission()" <?php echo $hasPendingActiveLoan ? 'disabled' : ''; ?>>Update Profile</button>
            </div>
        </form>
    </div>

    <!-- Enhanced Confirmation Modal -->
    <div id="confirmModal" class="modals">
        <div class="modals-content">
            <span class="close" onclick="closeConfirmModal()">×</span>
            <h2>Confirm Profile Update</h2>
            <p>Please review your changes carefully. Updating your profile will overwrite existing information. Are you
                sure you want to proceed?</p>
            <div id="loadingSpinner" class="loading-spinner"></div>
            <div class="modal-buttons">
                <button class="confirm-btn" id="confirmUpdateBtn" onclick="submitForm()">Yes, Update</button>
                <button class="cancel-btn" onclick="closeConfirmModal()">Cancel</button>
            </div>
        </div>
    </div>

    <div id="loanCalculatorModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeCalculatorModal()">×</span>
            <h2>Loan Calculator</h2>
            <form id="calculatorForm" onsubmit="event.preventDefault(); calculateLoan();">
                <div class="form-group">
                    <label for="loanType">Loan Type</label>
                    <select id="loanType" name="loanType" required onchange="updateLoanAmountRange()">
                        <option value="">Select Loan Type</option>
                        <option value="Individual">Individual</option>
                        <option value="Cooperative">Cooperative</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="loanAmount" id="loanAmountLabel">Loan Amount</label>
                    <input type="number" id="loanAmount" name="loanAmount" required
                        placeholder="Select loan type first">
                    <div class="error-message" id="loanAmount-error"></div>
                </div>
                <div class="form-group">
                    <label for="interestRate">Annual Interest Rate</label>
                    <input type="number" id="interestRate" name="interestRate" step="0.01" readonly>
                    <span id="interestRateDisplay">Select a loan type</span>
                </div>
                <div class="form-group">
                    <label for="termLength">Term Length (Months)</label>
                    <select id="termLength" name="termLength" required>
                        <option value="">Select Term Length</option>
                        <option value="6">6 Months</option>
                        <option value="12">12 Months</option>
                        <option value="18">18 Months</option>
                        <option value="24">24 Months</option>
                        <option value="36">36 Months</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="repaymentFrequency">Repayment Frequency</label>
                    <select id="repaymentFrequency" name="repaymentFrequency" required>
                        <option value="">Select Repayment Frequency</option>
                        <option value="Monthly">Monthly</option>
                        <option value="Quarterly">Quarterly</option>
                        <option value="Annually">Annually</option>
                    </select>
                </div>
                <button type="submit" class="calculate-btn">Calculate</button>
            </form>
            <div id="result" class="result"></div>
        </div>
    </div>
    <script src="JAVASCRIPT/Real-Time.js"></script>
    <script src="JAVASCRIPT/profile.js"></script>
</body>

</html>