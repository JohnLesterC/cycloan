<?php
session_start();
require "CYCLOAN_db.php";

if (!isset($_SESSION['email'])) {
    header("Location: index.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $admin_id = $_POST['admin_id'];
    $admin_type = $_POST['admin_type']; // This will be passed from the form

    // Determine the table to delete from based on admin type
    if ($admin_type === 'Admin 1') {
        $table = 'admin1';
    } else {
        $table = 'admin2';
    }

    // Prepare the delete statement
    $stmt = $conn->prepare("DELETE FROM $table WHERE id = ?");
    $stmt->bind_param("i", $admin_id);

    if ($stmt->execute()) {
        // Redirect back to the admin management page with a success message
        header("Location: add_admin.php?message=Admin removed successfully.");
    } else {
        // Redirect back with an error message
        header("Location: add_admin.php?message=Error removing admin: " . $stmt->error);
    }

    // Close the statement
    $stmt->close();
    $conn->close();
}
?>