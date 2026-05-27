<?php
session_start(); // Ensure session is started to access user_id
require "CYCLOAN_db.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $applicationId = $_POST["application_id"];
    $oldFileName = $_POST["old_file_name"];

    // Ensure user_id is available in the session
    if (!isset($_SESSION["user_id"])) {
        echo "User  not logged in.";
        exit();
    }

    $userId = $_SESSION["user_id"]; // Get user ID from session

    // Define the upload directory based on user ID and application ID
    $uploadsDir = 'uploads/user_id_' . $userId . '/' . $applicationId . '/';

    // Check if the directory exists, if not create it
    if (!file_exists($uploadsDir)) {
        mkdir($uploadsDir, 0777, true);
    }

    if (isset($_FILES["new_file"]) && $_FILES["new_file"]["error"] === UPLOAD_ERR_OK) {
        $newFileTmp = $_FILES["new_file"]["tmp_name"];
        $newFileName = basename($_FILES["new_file"]["name"]);
        $newFilePath = $uploadsDir . $newFileName; // Use the new directory structure

        // Move uploaded file
        if (move_uploaded_file($newFileTmp, $newFilePath)) {
            // Update database record
            $updateStmt = $conn->prepare("UPDATE uploaded_files SET file_name = ?, file_path = ? WHERE application_id = ? AND file_name = ?");
            $updateStmt->bind_param("ssis", $newFileName, $newFilePath, $applicationId, $oldFileName);
            if ($updateStmt->execute()) {
                // Optionally delete the old file
                $oldFileQuery = $conn->prepare("SELECT file_path FROM uploaded_files WHERE application_id = ? AND file_name = ?");
                $oldFileQuery->bind_param("is", $applicationId, $oldFileName);
                $oldFileQuery->execute();
                $oldFileQuery->bind_result($oldFilePath);
                if ($oldFileQuery->fetch() && file_exists($oldFilePath)) {
                    unlink($oldFilePath); // delete old file
                }
                $oldFileQuery->close();

                header("Location: user_dashboard.php?view_id=$applicationId");
                exit();
            } else {
                echo "Database update failed.";
            }
            $updateStmt->close();
        } else {
            echo "File upload failed.";
        }
    } else {
        echo "Invalid file or upload error.";
    }
}
?>