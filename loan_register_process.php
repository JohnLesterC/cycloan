<?php
session_start();
require 'CYCLOAN_db.php';
require_once 'timezone_config.php';
require_once 'email_sender.php';

if (!$conn) {
    $_SESSION['error'] = 'Connection failed: ' . mysqli_connect_error();
    header('Location: /user_dashboard.php');
    exit;
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
    $_SESSION['error'] = 'You must be logged in to submit an application.';
    header('Location: /user_dashboard.php');
    exit;
}

$allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
$maxFileSize = 5 * 1024 * 1024;
$uploadDir = 'Uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

function sanitizeInput($data)
{
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function uploadFile($file, $uploadDir, $allowedTypes, $maxFileSize)
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload error: ' . $file['error']];
    }
    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type. Only JPEG, PNG, or PDF allowed.'];
    }
    if ($file['size'] > $maxFileSize) {
        return ['success' => false, 'message' => 'File size exceeds 5MB limit.'];
    }
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $ext;
    $destination = $uploadDir . $filename;
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => true, 'path' => $destination, 'type' => $file['type'], 'size' => $file['size']];
    }
    return ['success' => false, 'message' => 'Failed to move uploaded file.'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loanType = isset($_POST['loanType']) ? sanitizeInput($_POST['loanType']) : '';
    $loanStatus = isset($_POST['loanStatus']) ? sanitizeInput($_POST['loanStatus']) : '';
    $amountApplied = isset($_POST['amountApplied']) ? floatval($_POST['amountApplied']) : 0;
    $termLength = isset($_POST['termLength']) ? sanitizeInput($_POST['termLength']) : '';
    $repaymentFrequency = isset($_POST['repaymentFrequency']) ? sanitizeInput($_POST['repaymentFrequency']) : '';
    $purpose = isset($_POST['purpose']) ? sanitizeInput($_POST['purpose']) : '';
    $othersText = isset($_POST['othersText']) ? sanitizeInput($_POST['othersText']) : '';
    $projectType = isset($_POST['projectType']) ? sanitizeInput($_POST['projectType']) : '';
    $projectDescription = isset($_POST['projectDescription']) ? sanitizeInput($_POST['projectDescription']) : '';

    error_log("Received POST data: " . print_r($_POST, true), 3, 'debug.log');
    error_log("termLength received: $termLength", 3, 'debug.log');

    $errors = [];
    if (!in_array($loanType, ['Individual', 'Cooperative'])) {
        $errors[] = 'Invalid loan type.';
    }
    if (!in_array($loanStatus, ['new', 'renewal'])) {
        $errors[] = 'Invalid loan status.';
    }
    if ($amountApplied < 10000 || $amountApplied > 1000000) {
        $errors[] = 'Amount must be between 10,000 and 1,000,000 PHP.';
    }

    $validTermsIndividual = ['6', '12', '18'];
    $validTermsCooperative = ['6', '12', '18', '24', '36'];
    $validTerms = $loanType === 'Cooperative' ? $validTermsCooperative : $validTermsIndividual;
    if (!in_array($termLength, $validTerms)) {
        $errors[] = 'Invalid term length: ' . $termLength . ' for ' . $loanType . ' loan.';
    }
    if (!in_array($repaymentFrequency, ['Monthly', 'Quarterly', 'Semi-Annually', 'Annually'])) {
        $errors[] = 'Invalid repayment frequency.';
    }
    if (empty($purpose)) {
        $errors[] = 'Purpose is required.';
    }
    if ($purpose === 'Others' && empty($othersText)) {
        $errors[] = 'Please specify other purpose.';
    }
    if (empty($projectType)) {
        $errors[] = 'Project type is required.';
    }
    if (empty($projectDescription) || strlen($projectDescription) < 20 || strlen($projectDescription) > 500) {
        $errors[] = 'Project description must be between 20 and 500 characters.';
    }

    $documentFieldMap = [
        '2x2pic' => '2x2 Picture',
        'votersCertificate' => 'Voter\'s Certificate',
        'residenceCertificate' => 'Residence Certificate',
        'barangayClearance' => 'Barangay Clearance',
        'businessPermit' => 'Business Permit',
        'farmPlanBudget' => 'Farm Plan and Budget',
        'loanProjectProposal' => 'Loan Project Proposal',
        'auditedFS' => 'Audited Financial Statement',
        'bankStatement' => 'Bank Statement',
        'BIR' => 'BIR'
    ];

    $uploadedFiles = [];
    foreach ($documentFieldMap as $field => $documentName) {
        if (isset($_FILES[$field]) && $_FILES[$field]['size'] > 0) {
            $result = uploadFile($_FILES[$field], $uploadDir, $allowedTypes, $maxFileSize);
            if ($result['success']) {
                $uploadedFiles[$field] = [
                    'document_name' => $documentName,
                    'path' => $result['path'],
                    'type' => $result['type'],
                    'size' => $result['size']
                ];
            } else {
                $errors[] = "Error uploading $documentName: " . $result['message'];
            }
        }
    }

    if ($loanType === 'Individual') {
        $individualDocs = ['2x2 Picture', 'Voter\'s Certificate', 'Residence Certificate', 'Barangay Clearance', 'Business Permit'];
        $hasIndividualDoc = false;
        foreach ($individualDocs as $doc) {
            if (isset($uploadedFiles[array_search($doc, $documentFieldMap)])) {
                $hasIndividualDoc = true;
                break;
            }
        }
        if (!$hasIndividualDoc) {
            $errors[] = 'At least one individual requirement file is required.';
        }
        if ($projectType === 'Agricultural-based' && !isset($uploadedFiles['farmPlanBudget'])) {
            $errors[] = 'Farm Plan and Budget is required for agricultural projects.';
        }
    } elseif ($loanType === 'Cooperative') {
        $cooperativeDocs = ['Loan Project Proposal', 'Audited Financial Statement', 'Bank Statement', 'BIR'];
        $hasCooperativeDoc = false;
        foreach ($cooperativeDocs as $doc) {
            if (isset($uploadedFiles[array_search($doc, $documentFieldMap)])) {
                $hasCooperativeDoc = true;
                break;
            }
        }
        if (!$hasCooperativeDoc) {
            $errors[] = 'At least one cooperative requirement file is required.';
        }
    }

    if (empty($errors)) {
        mysqli_begin_transaction($conn);
        try {
            $loanType = mysqli_real_escape_string($conn, $loanType);
            $query = "SELECT loan_type_id FROM loan_types WHERE type_name = '$loanType'";
            $result = mysqli_query($conn, $query);
            if (!$result || mysqli_num_rows($result) == 0) {
                throw new Exception('Invalid loan type ID.');
            }
            $row = mysqli_fetch_assoc($result);
            $loanTypeId = $row['loan_type_id'];

            // Generate a unique application number for today
            $today = date('Ymd');
            $query = "SELECT COUNT(*) as count FROM loan_applications WHERE DATE(created_at) = CURDATE()";
            $result = mysqli_query($conn, $query);
            $row = mysqli_fetch_assoc($result);
            $dailyCount = $row['count'] + 1;

            // Generate unique Application ID in the format APP-YYYYMMDD-XXXX
            $applicationId = 'APP-' . $today . '-' . str_pad($dailyCount, 4, '0', STR_PAD_LEFT);

            // Generate unique Loan ID in the format LOAN-YYYYMMDD-XXXX (will be the same sequence)
            $loanId = 'LOAN-' . $today . '-' . str_pad($dailyCount, 4, '0', STR_PAD_LEFT);

            error_log("Generated application_id: $applicationId and loan_id: $loanId", 3, 'debug.log');

            // Calculate total payable amount for logging
            $termYears = floatval($termLength) / 12;
            $interestRate = 6; // From interest_rates table
            $interest = $amountApplied * ($interestRate / 100) * $termYears;
            $totalPayable = $amountApplied + $interest;
            error_log("Calculated total payable: $totalPayable for amount: $amountApplied, term: $termLength months", 3, 'debug.log');

            $userId = intval($_SESSION['user_id']);
            $loanStatus = mysqli_real_escape_string($conn, $loanStatus);
            $amountApplied = floatval($amountApplied);
            $termLength = mysqli_real_escape_string($conn, $termLength);
            $repaymentFrequency = mysqli_real_escape_string($conn, $repaymentFrequency);
            $purpose = mysqli_real_escape_string($conn, $purpose);
            $othersText = mysqli_real_escape_string($conn, $othersText);
            $projectType = mysqli_real_escape_string($conn, $projectType);
            $projectDescription = mysqli_real_escape_string($conn, $projectDescription);

            // Get current time in Philippine Time (UTC+8)
            $phpTimeZone = new DateTimeZone('Asia/Manila');
            $now = new DateTime('now', $phpTimeZone);
            $createdAt = $now->format('Y-m-d H:i:s');

            error_log("Inserting loan application with termLength: '$termLength'", 3, 'debug.log');
            error_log("INSERT Query: INSERT INTO loan_applications (application_id, loan_id, user_id, loan_type_id, loan_status, amount_applied, term_length, repayment_frequency, purpose, others_text, project_type, project_description) VALUES ('$applicationId', '$loanId', $userId, $loanTypeId, '$loanStatus', $amountApplied, '$termLength', '$repaymentFrequency', '$purpose', '$othersText', '$projectType', '$projectDescription')", 3, 'debug.log');

            $query = "INSERT INTO loan_applications (
                application_id, loan_id, user_id, loan_type_id, loan_status, amount_applied, term_length, repayment_frequency,
                purpose, others_text, project_type, project_description, created_at
            ) VALUES (
                '$applicationId', '$loanId', $userId, $loanTypeId, '$loanStatus', $amountApplied, '$termLength', '$repaymentFrequency',
                '$purpose', '$othersText', '$projectType', '$projectDescription', '$createdAt'
            )";
            if (!mysqli_query($conn, $query)) {
                error_log("Insert failed: termLength='$termLength', error=" . mysqli_error($conn), 3, 'debug.log');
                throw new Exception('Failed to insert loan application: ' . mysqli_error($conn));
            }
            error_log("Successfully inserted loan application with application_id: $applicationId", 3, 'debug.log');

            $verifyQuery = "SELECT term_length, loan_id FROM loan_applications WHERE application_id = '$applicationId'";
            $verifyResult = mysqli_query($conn, $verifyQuery);
            if ($verifyResult && mysqli_num_rows($verifyResult) > 0) {
                $row = mysqli_fetch_assoc($verifyResult);
                $insertedTermLength = $row['term_length'];
                $insertedLoanId = $row['loan_id'];
                error_log("Verified inserted term_length: '$insertedTermLength', loan_id: '$insertedLoanId' for application_id: $applicationId", 3, 'debug.log');
                if ($insertedTermLength !== $termLength) {
                    throw new Exception("Term length mismatch: Expected '$termLength', got '$insertedTermLength'");
                }
                if ($insertedLoanId !== $loanId) {
                    throw new Exception("Loan ID mismatch: Expected '$loanId', got '$insertedLoanId'");
                }
            } else {
                throw new Exception("Failed to verify inserted term_length or loan_id: " . mysqli_error($conn));
            }

            foreach ($uploadedFiles as $file) {
                $documentName = mysqli_real_escape_string($conn, $file['document_name']);
                $query = "SELECT document_type_id FROM document_types WHERE document_name = '$documentName'";
                $result = mysqli_query($conn, $query);
                if ($result && mysqli_num_rows($result) > 0) {
                    $row = mysqli_fetch_assoc($result);
                    $documentTypeId = $row['document_type_id'];
                    $filePath = mysqli_real_escape_string($conn, $file['path']);
                    $fileType = mysqli_real_escape_string($conn, $file['type']);
                    $fileSize = intval($file['size']);
                    $query = "INSERT INTO documents (application_id, document_type_id, file_path, file_type, file_size)
                              VALUES ('$applicationId', $documentTypeId, '$filePath', '$fileType', $fileSize)";
                    if (!mysqli_query($conn, $query)) {
                        throw new Exception('Failed to insert document: ' . mysqli_error($conn));
                    }
                }
            }

            mysqli_commit($conn);
            error_log("Transaction committed successfully for application_id: $applicationId, loan_id: $loanId", 3, 'debug.log');

            // Create notification for admins about the new loan application
            require_once 'NotificationManager.php';
            $notificationManager = new NotificationManager($conn);

            // Get all admin2 IDs to notify them
            $adminQuery = "SELECT id FROM admin2";
            $adminResult = mysqli_query($conn, $adminQuery);
            if ($adminResult) {
                while ($admin = mysqli_fetch_assoc($adminResult)) {
                    $notificationManager->createNotification(
                        $admin['id'],
                        'application',
                        'New Loan Application Submitted - ' . $loanType,
                        "New $loanType loan application - ID: $applicationId | Amount: ₱" . number_format($amountApplied, 2),
                        'high'
                    );
                }
            }

            // Send loan application confirmation email to user
            $userId = intval($_SESSION['user_id']);
            $emailQuery = "SELECT email, CONCAT(first_name, ' ', last_name) as full_name FROM users1 WHERE id = $userId LIMIT 1";
            $emailResult = mysqli_query($conn, $emailQuery);

            if ($emailResult && mysqli_num_rows($emailResult) > 0) {
                $userData = mysqli_fetch_assoc($emailResult);
                $userEmail = $userData['email'];
                $userName = $userData['full_name'];

                // Send email confirmation
                $emailSent = EmailSender::sendLoanApplicationSubmitted($userEmail, $userName, $loanId, $applicationId, $loanType, $amountApplied, $termLength);

                if ($emailSent) {
                    error_log("Loan application confirmation email sent successfully to: $userEmail for application: $applicationId", 3, 'debug.log');
                } else {
                    error_log("Failed to send loan application confirmation email to: $userEmail for application: $applicationId", 3, 'debug.log');
                }
            } else {
                error_log("Failed to fetch user email for user_id: $userId", 3, 'debug.log');
            }

            $_SESSION['success'] = "Loan application submitted successfully!";
            header('Location: /user_dashboard.php');
            exit;
        } catch (Exception $e) {
            mysqli_rollback($conn);
            error_log("Database error: " . $e->getMessage(), 3, 'debug.log');
            $_SESSION['error'] = 'Database error: ' . $e->getMessage();
            header('Location: /user_dashboard.php');
            exit;
        }
    } else {
        error_log("Validation errors: " . implode('; ', $errors), 3, 'debug.log');
        $_SESSION['error'] = implode('<br>', $errors);
        header('Location: /user_dashboard.php');
        exit;
    }
} else {
    $_SESSION['error'] = 'Invalid request method.';
    header('Location: /user_dashboard.php');
    exit;
}


?>