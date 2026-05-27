<?php
session_start();
require "CYCLOAN_db.php";

// Check if the user is logged in
if (!isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'message' => 'User  not logged in.']);
    exit();
}

// Get the form data
$loan_id = $_POST['loan_id'];
$first_name = $_POST['first_name'];
$middle_name = $_POST['middle_name'];
$last_name = $_POST['last_name'];
$nick_name = $_POST['nick_name'];
$birthday = $_POST['birthday'];
$age = $_POST['age'];
$birth_place = $_POST['birth_place'];
$civil_status = $_POST['civil_status'];
$contact = $_POST['contact'];
$res_address = $_POST['res_address'];
$email = $_POST['email'];
$fb_account = $_POST['fb_account'];
$bus_address = $_POST['bus_address'];
$house_ownership = $_POST['house_ownership'];
$occupation = $_POST['occupation'];
$reg_voter = $_POST['reg_voter'];
$year_resident = $_POST['year_resident'];

// Spouse Details
$spouse_full_name = $_POST['spouse_full_name'];
$spouse_nick_name = $_POST['spouse_nick_name'];
$spouse_reg_voter = $_POST['spouse_reg_voter'];
$spouse_birthday = $_POST['spouse_birthday'];
$spouse_age = $_POST['spouse_age'];
$spouse_occupation = $_POST['spouse_occupation'];
$spouse_dependents = $_POST['spouse_dependents'];
$spouse_birth_place = $_POST['spouse_birth_place'];
$spouse_contact = $_POST['spouse_contact'];
$spouse_email = $_POST['spouse_email'];
$spouse_fb_account = $_POST['spouse_fb_account'];


// Prepare the update statement for loan_application1
$stmt_loan = $conn->prepare("
    UPDATE loan_application1 
    SET first_name = ?, middle_name = ?, last_name = ?, nick_name = ?, birthday = ?, age = ?, 
        birth_place = ?, civil_status = ?, contact = ?, res_address = ?, email = ?, 
        fb_account = ?, bus_address = ?, house_ownership = ?, occupation = ?, 
        reg_voter = ?, year_resident = ? 
    WHERE id = ?
");
$stmt_loan->bind_param(
    "sssssiissssssssssi",
    $first_name,
    $middle_name,
    $last_name,
    $nick_name,
    $birthday,
    $age,
    $birth_place,
    $civil_status,
    $contact,
    $res_address,
    $email,
    $fb_account,
    $bus_address,
    $house_ownership,
    $occupation,
    $reg_voter,
    $year_resident,
    $loan_id
);

// Execute the loan application update
if (!$stmt_loan->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to update loan application.']);
    $stmt_loan->close();
    $conn->close();
    exit();
}

// Prepare the update statement for spouse_details
$stmt_spouse = $conn->prepare("
    UPDATE spouse_details 
    SET full_name = ?, nick_name = ?, reg_voter = ?, birthday = ?, age = ?, 
        occupation = ?, dependents = ?, birth_place = ?, contact = ?, 
        email = ?, fb_account = ? 
    WHERE loan_id = ?
");
$stmt_spouse->bind_param(
    "ssssissssssi",
    $spouse_full_name,
    $spouse_nick_name,
    $spouse_reg_voter,
    $spouse_birthday,
    $spouse_age,
    $spouse_occupation,
    $spouse_dependents,
    $spouse_birth_place,
    $spouse_contact,
    $spouse_email,
    $spouse_fb_account,
    $loan_id
);

// Execute the spouse details update
if ($stmt_spouse->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update spouse details.']);
}

$uploadDir = 'uploads/';

// Handle file uploads
$files = [
    'pic_2x2',
    'voter_certificate',
    'valid_id',
    'residence_certificate',
    'barangay_clearance',
    'business_permit',
    'farm_plan_budget',
    'loan_project_proposal',
    'audited_financial_statement',
    'bank_statement',
    'bir'
];

$uploadedFiles = [];
foreach ($files as $file) {
    if (!empty($_FILES[$file]['name'])) {
        $uploadedFiles[$file] = $uploadDir . basename($_FILES[$file]['name']);
        if (!move_uploaded_file($_FILES[$file]['tmp_name'], $uploadedFiles[$file])) {
            echo json_encode(['success' => false, 'message' => "Failed to upload $file."]);
            exit();
        }
    }
}

// Prepare the SQL query to update the loan_requirements table
$updateQuery = "UPDATE loan_requirements SET ";
$updateParts = [];
$params = [];
$types = "";

foreach ($uploadedFiles as $column => $value) {
    $updateParts[] = "$column = ?";
    $params[] = $value;
    $types .= "s";
}

if (!empty($updateParts)) {
    $updateQuery .= implode(", ", $updateParts) . " WHERE loan_id = ?";
    $params[] = $loan_id;
    $types .= "i";

    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param($types, ...$params);

    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Failed to update loan requirements.']);
        $stmt->close();
        $conn->close();
        exit();
    }
    $stmt->close();
}








// Close the statements
$stmt_loan->close();
$stmt_spouse->close();
$conn->close();
?>