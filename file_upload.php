<?php
define('UPLOAD_DIR', 'uploads/');
define('MAX_PIC_SIZE', 2000000); // 2MB
define('MAX_VOTER_CERT_SIZE', 5000000); // 5MB
define('ALLOWED_PIC_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('ALLOWED_VOTER_CERT_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'pdf']);

function handleFileUpload($fileInputName, $allowedTypes, $maxSize)
{
    $targetFile = UPLOAD_DIR . basename($_FILES[$fileInputName]["name"]);
    $uploadOk = 1;
    $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

    // Check for upload errors
    if ($_FILES[$fileInputName]["error"] !== UPLOAD_ERR_OK) {
        return ["error" => "File upload error for $fileInputName: " . $_FILES[$fileInputName]["error"]];
    }

    // Check file size
    if ($_FILES[$fileInputName]["size"] > $maxSize) {
        return ["error" => "Sorry, your file is too large."];
    }

    // Check file type
    if (!in_array($fileType, $allowedTypes)) {
        return ["error" => "Sorry, only " . implode(", ", $allowedTypes) . " files are allowed."];
    }

    // Move the uploaded file
    if (move_uploaded_file($_FILES[$fileInputName]["tmp_name"], $targetFile)) {
        return ["success" => $targetFile];
    } else {
        return ["error" => "Error uploading $fileInputName."];
    }
}
?>