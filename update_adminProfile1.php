<?php
session_start();
require "CYCLOAN_db.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id = intval($_POST["id"]);
    $role = $_POST["role"];

    $first = $_POST["first_name"];
    $middle = $_POST["middle_name"];
    $last = $_POST["last_name"];
    $email = $_POST["email"];
    $phone = $_POST["phone"];
    $address = $_POST["address"];
    $password = $_POST["password"];

    // Default profile image
    $profile_img = null;

    // Check if file uploaded
    if (isset($_FILES["profile_img"]) && $_FILES["profile_img"]["error"] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES["profile_img"]["name"], PATHINFO_EXTENSION);
        $newName = uniqid("profile_") . "." . strtolower($ext);
        $uploadDir = "uploads/";

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        if (move_uploaded_file($_FILES["profile_img"]["tmp_name"], $uploadDir . $newName)) {
            $profile_img = $newName;
        }
    }

    // Build SQL dynamically
    $fields = "first_name=?, middle_name=?, last_name=?, email=?, phone=?, address=?";
    $params = [$first, $middle, $last, $email, $phone, $address];
    $types = "ssssss";

    if (!empty($password)) {
        $fields .= ", password=?";
        $params[] = password_hash($password, PASSWORD_BCRYPT);
        $types .= "s";
    }

    if ($profile_img) {
        $fields .= ", profile_img=?";
        $params[] = $profile_img;
        $types .= "s";
    }

    $params[] = $id;
    $types .= "i";

    $sql = "UPDATE $role SET $fields WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        header("Location: profileAdmin1.php?success=1");
        exit();
    } else {
        echo "Error updating profile: " . $stmt->error;
    }

    $stmt->close();
}