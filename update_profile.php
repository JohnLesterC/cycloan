<?php
session_start();
require "CYCLOAN_db.php";

if (!isset($_SESSION['user_id'])) {
    die("Access denied. User not logged in.");
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize and assign POST data
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $middle_name = mysqli_real_escape_string($conn, $_POST['middle_name']);
    $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
    $name_extension = mysqli_real_escape_string($conn, $_POST['name_extension']);
    $nick_name = mysqli_real_escape_string($conn, $_POST['nick_name']);
    $birthday = mysqli_real_escape_string($conn, $_POST['birthday']);
    $age = mysqli_real_escape_string($conn, $_POST['age']);
    $birth_place = mysqli_real_escape_string($conn, $_POST['birth_place']);
    $civil_status = mysqli_real_escape_string($conn, $_POST['civil_status']);
    $contact = mysqli_real_escape_string($conn, $_POST['contact']);
    $res_house_no = mysqli_real_escape_string($conn, $_POST['res_house_no']);
    $res_street = mysqli_real_escape_string($conn, $_POST['res_street']);
    $res_subdivision = mysqli_real_escape_string($conn, $_POST['res_subdivision']);
    $res_barangay = mysqli_real_escape_string($conn, $_POST['res_barangay']);
    $res_address = mysqli_real_escape_string($conn, $_POST['res_address']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $fb_account = mysqli_real_escape_string($conn, $_POST['fb_account']);
    $bus_bldg_no = mysqli_real_escape_string($conn, $_POST['bus_bldg_no']);
    $bus_street = mysqli_real_escape_string($conn, $_POST['bus_street']);
    $bus_subdivision = mysqli_real_escape_string($conn, $_POST['bus_subdivision']);
    $bus_barangay = mysqli_real_escape_string($conn, $_POST['bus_barangay']);
    $bus_address = mysqli_real_escape_string($conn, $_POST['bus_address']);
    $house_ownership = mysqli_real_escape_string($conn, $_POST['house_ownership']);
    $occupation = mysqli_real_escape_string($conn, $_POST['occupation']);
    $reg_voter = mysqli_real_escape_string($conn, $_POST['reg_voter']);
    $year_resident = mysqli_real_escape_string($conn, $_POST['year_resident']);

    // ✅ Handle Profile Image Upload
    $profile_image = null;

    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = "uploads/profile_images/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true); // create folder if not exists
        }

        $fileTmpPath = $_FILES['profile_image']['tmp_name'];
        $fileName = basename($_FILES['profile_image']['name']);
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // Allow only JPG, JPEG, PNG
        $allowedExtensions = ['jpg', 'jpeg', 'png'];
        if (in_array($fileExtension, $allowedExtensions)) {
            // Rename file to avoid conflicts
            $newFileName = $user_id . "_" . time() . "." . $fileExtension;
            $destPath = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $destPath)) {
                $profile_image = $destPath;
            }
        }
    }

    // ✅ Update query (with optional profile image)
    if ($profile_image) {
        $sql = "UPDATE users1 SET 
                first_name=?, middle_name=?, last_name=?, name_extension=?, 
                nick_name=?, birthday=?, age=?, birth_place=?, civil_status=?, 
                contact=?, res_house_no=?, res_street=?, res_subdivision=?, 
                res_barangay=?, res_address=?, email=?, fb_account=?, 
                bus_bldg_no=?, bus_street=?, bus_subdivision=?, bus_barangay=?, 
                bus_address=?, house_ownership=?, occupation=?, reg_voter=?, 
                year_resident=?, profile_image=? 
                WHERE id=?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "sssssssssssssssssssssssssssi",
            $first_name,
            $middle_name,
            $last_name,
            $name_extension,
            $nick_name,
            $birthday,
            $age,
            $birth_place,
            $civil_status,
            $contact,
            $res_house_no,
            $res_street,
            $res_subdivision,
            $res_barangay,
            $res_address,
            $email,
            $fb_account,
            $bus_bldg_no,
            $bus_street,
            $bus_subdivision,
            $bus_barangay,
            $bus_address,
            $house_ownership,
            $occupation,
            $reg_voter,
            $year_resident,
            $profile_image,
            $user_id
        );
    } else {
        // If no image uploaded, keep the old one
        $sql = "UPDATE users1 SET 
                first_name=?, middle_name=?, last_name=?, name_extension=?, 
                nick_name=?, birthday=?, age=?, birth_place=?, civil_status=?, 
                contact=?, res_house_no=?, res_street=?, res_subdivision=?, 
                res_barangay=?, res_address=?, email=?, fb_account=?, 
                bus_bldg_no=?, bus_street=?, bus_subdivision=?, bus_barangay=?, 
                bus_address=?, house_ownership=?, occupation=?, reg_voter=?, 
                year_resident=? 
                WHERE id=?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "ssssssssssssssssssssssssssi",
            $first_name,
            $middle_name,
            $last_name,
            $name_extension,
            $nick_name,
            $birthday,
            $age,
            $birth_place,
            $civil_status,
            $contact,
            $res_house_no,
            $res_street,
            $res_subdivision,
            $res_barangay,
            $res_address,
            $email,
            $fb_account,
            $bus_bldg_no,
            $bus_street,
            $bus_subdivision,
            $bus_barangay,
            $bus_address,
            $house_ownership,
            $occupation,
            $reg_voter,
            $year_resident,
            $user_id
        );
    }

    if ($stmt->execute()) {
        $_SESSION['success'] = "Profile updated successfully!";
        header("Location: profile.php");
        exit();
    } else {
        echo "Error updating profile: " . $stmt->error;
    }

    $stmt->close();
}
?>