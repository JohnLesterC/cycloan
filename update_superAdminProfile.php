<?php
session_start();
require "CYCLOAN_db.php";

// Check if logged in
if (!isset($_SESSION["email"])) {
    header("Location: index.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = $_POST['email'];
    $first_name = $_POST['first_name'];
    $middle_name = $_POST['middle_name'];
    $last_name = $_POST['last_name'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $password = $_POST['password']; // may be blank

    // Handle image upload
    $profile_img = null;
    if (isset($_FILES['profile_img']) && $_FILES['profile_img']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . "/uploads/";  // make sure uploads folder exists and is writable
        $ext = pathinfo($_FILES['profile_img']['name'], PATHINFO_EXTENSION);
        $newFileName = uniqid("profile_", true) . "." . strtolower($ext);

        $targetPath = $uploadDir . $newFileName;

        if (move_uploaded_file($_FILES['profile_img']['tmp_name'], $targetPath)) {
            $profile_img = $newFileName;
        }
    }

    // Prepare SQL update
    $sql = "UPDATE superadmins 
            SET first_name = ?, middle_name = ?, last_name = ?, phone = ?, address = ?";

    $params = [$first_name, $middle_name, $last_name, $phone, $address];
    $types = "sssss";

    // Update password only if provided
    if (!empty($password)) {
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $sql .= ", password = ?";
        $params[] = $hashedPassword;
        $types .= "s";
    }

    // Update profile image if uploaded
    if (!empty($profile_img)) {
        $sql .= ", profile_img = ?";
        $params[] = $profile_img;
        $types .= "s";
    }

    $sql .= " WHERE email = ?";
    $params[] = $email;
    $types .= "s";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Profile updated successfully.";
    } else {
        $_SESSION['error'] = "Error updating profile: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();

    header("Location: profileSuperadmin.php");
    exit();
}
?>