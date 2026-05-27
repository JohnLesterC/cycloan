<?php
// Centralized debug control via env_config.php
if (file_exists(__DIR__ . '/env_config.php')) {
    require_once __DIR__ . '/env_config.php';
}
if (defined('APP_DEBUG') && APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}
ini_set('log_errors', '1');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

try {
    require "CYCLOAN_db.php"; // Include MySQLi connection
    require_once 'timezone_config.php';
    require "credit_points_manager.php"; // Include credit points manager
} catch (Exception $e) {
    error_log("Dashboard include error: " . $e->getMessage());
    http_response_code(500);
    die("Unable to load dashboard components");
}

// Centralized debug logging function
function debugLog($message, $userId = null)
{
    $timestamp = date('Y-m-d H:i:s');
    $userContext = $userId ? "User ID: $userId | " : "";
    $logMessage = "[$timestamp] $userContext$message\n";
    error_log($logMessage, 3, 'debug.log');
}

// Check if the user is logged in
if (!isset($_SESSION['email']) || !isset($_SESSION['user_id'])) {
    debugLog("Unauthorized access attempt to dashboard", null);
    $_SESSION['error'] = "Please log in to access the dashboard.";
    header("Location: index.php");
    exit();
}

$userId = $_SESSION['user_id'];
debugLog("Dashboard accessed", $userId);

// Ensure global $conn is a valid MySQLi object
global $conn;
if (!($conn instanceof mysqli)) {
    debugLog("Connection failed: MySQLi connection not initialized", $userId);
    $_SESSION['error'] = 'Connection failed. Please try again later.';
    header('Location: user_dashboard.php');
    exit;
}

// Fetch profile picture
try {
    $stmt = $conn->prepare("SELECT profile_image FROM users1 WHERE id = ?");
    if ($stmt === false) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $profile_image = !empty($row['profile_image']) ? $row['profile_image'] : 'assets/default.jpg';
    $stmt->close();
    debugLog("Profile picture fetched successfully", $userId);
} catch (Exception $e) {
    debugLog("Profile picture fetch error: " . $e->getMessage(), $userId);
    $profile_image = 'assets/default.jpg';
}

// Fetch user's credit points
try {
    $creditManager = getCreditPointsManager();
    $userCreditPoints = $creditManager->getUserPoints($userId);
    $creditHistory = $creditManager->getUserHistory($userId, 5); // Get last 5 transactions
    debugLog("Credit points fetched: $userCreditPoints", $userId);
} catch (Exception $e) {
    debugLog("Credit points fetch error: " . $e->getMessage(), $userId);
    $userCreditPoints = 0;
    $creditHistory = [];
}

// Fetch payment history for timeline
$paymentHistory = [];
try {
    // Check if payments table exists first
    $tableCheck = $conn->query("SHOW TABLES LIKE 'payments'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        $paymentHistoryStmt = $conn->prepare("
            SELECT 
                p.payment_id,
                p.amount_paid,
                p.payment_date,
                p.payment_method,
                ps.due_date,
                ps.amount as scheduled_amount,
                ps.status as schedule_status,
                l.loan_id,
                la.application_id,
                lt.type_name as loan_type
            FROM payments p
            JOIN payment_schedules ps ON p.schedule_id = ps.schedule_id
            JOIN loans l ON ps.loan_id = l.loan_id
            JOIN loan_applications la ON l.application_id = la.application_id
            JOIN loan_types lt ON la.loan_type_id = lt.loan_type_id
            WHERE la.user_id = ?
            ORDER BY p.payment_date DESC
            LIMIT 10
        ");

        if ($paymentHistoryStmt) {
            $paymentHistoryStmt->bind_param("i", $userId);
            $paymentHistoryStmt->execute();
            $paymentHistoryResult = $paymentHistoryStmt->get_result();
            $paymentHistory = $paymentHistoryResult->fetch_all(MYSQLI_ASSOC);
            $paymentHistoryStmt->close();
            debugLog("Payment history fetched: " . count($paymentHistory) . " payments", $userId);
        }
    } else {
        debugLog("Payments table does not exist - timeline will be hidden", $userId);
    }
} catch (Exception $e) {
    debugLog("Payment history fetch error: " . $e->getMessage(), $userId);
    $paymentHistory = [];
}

// Get current page for active navigation highlighting
$current_page = basename($_SERVER['PHP_SELF']);

// Handle AJAX request for current interest rate
if (isset($_GET['action']) && $_GET['action'] === 'get_current_interest_rate') {
    try {
        $stmt = $conn->prepare("SELECT interest_rate FROM interest_rates WHERE term_length = '12' ORDER BY updated_at DESC LIMIT 1");
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $interest_rate_row = $result->fetch_assoc();
        $current_rate = $interest_rate_row ? $interest_rate_row['interest_rate'] : 6.00;
        $stmt->close();
        debugLog("Interest rate fetched: $current_rate", $userId);
        $response = ['success' => true, 'interest_rate' => $current_rate];
    } catch (Exception $e) {
        debugLog("Interest rate fetch error: " . $e->getMessage(), $userId);
        $response = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Fetch user's loan applications
$stmt = $conn->prepare("
    SELECT la.application_id as formatted_application_id, la.loan_id as application_loan_id, 
           lt.type_name, la.amount_applied, la.status, la.pre_approval_status, 
           la.credit_investigation_status, la.created_at, la.final_loan_amount,
           la.term_length, l.loan_id as loans_loan_id
    FROM loan_applications la
    JOIN loan_types lt ON la.loan_type_id = lt.loan_type_id
    LEFT JOIN loans l ON la.application_id = l.application_id
    WHERE la.user_id = ?
    ORDER BY la.created_at DESC
");
if ($stmt === false) {
    debugLog("Loan applications prepare error: " . $conn->error, $userId);
    $_SESSION['error'] = 'Failed to fetch loan applications.';
    header('Location: user_dashboard.php');
    exit;
}
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$loanApplications = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
debugLog("Fetched " . count($loanApplications) . " loan applications", $userId);

// Check for active or pending loan applications
$stmt = $conn->prepare("
    SELECT COUNT(*) as count, application_id
    FROM loan_applications
    WHERE user_id = ? AND status IN ('Active', 'Pending')
");
if ($stmt === false) {
    debugLog("Active/Pending loan check error: " . $conn->error, $userId);
    $_SESSION['error'] = 'Failed to check loan status.';
    header('Location: user_dashboard.php');
    exit;
}
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$hasActiveOrPendingLoan = $row['count'] > 0;
$activePendingLoanId = $row['application_id'] ?? null;
$stmt->close();
debugLog("Active/Pending loan check: " . ($hasActiveOrPendingLoan ? "Yes" : "No"), $userId);

// Handle AJAX request for loan details
if (isset($_GET['action']) && $_GET['action'] === 'get_loan_details' && isset($_GET['application_id'])) {
    $applicationId = mysqli_real_escape_string($conn, $_GET['application_id']);
    debugLog("Fetching loan details for application ID: $applicationId", $userId);
    try {
        $stmt = $conn->prepare("
            SELECT la.application_id, la.user_id, la.loan_type_id, la.amount_applied, la.status, 
                   la.pre_approval_status, la.credit_investigation_status, la.created_at,
                   la.term_length, la.repayment_frequency, la.purpose, la.others_text, 
                   la.project_type, la.project_description, lt.type_name, la.final_loan_amount
            FROM loan_applications la
            JOIN loan_types lt ON la.loan_type_id = lt.loan_type_id
            WHERE la.application_id = ? AND la.user_id = ?
        ");
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("si", $applicationId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $loan = $result->fetch_assoc();
        $stmt->close();

        if ($loan) {
            $stmt = $conn->prepare("
                SELECT dt.document_name, d.file_path, d.document_id, d.status, d.status_updated_at
                FROM documents d
                JOIN document_types dt ON d.document_type_id = dt.document_type_id
                WHERE d.application_id = ?
            ");
            if ($stmt === false) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("s", $applicationId);
            $stmt->execute();
            $result = $stmt->get_result();
            $documents = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $stmt = $conn->prepare("
                SELECT remark_id, remarks, created_at
                FROM remarks
                WHERE application_id = ?
                ORDER BY created_at DESC
            ");
            if ($stmt === false) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("s", $applicationId);
            $stmt->execute();
            $result = $stmt->get_result();
            $remarks = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            // Fetch payment schedule if loan is active or closed
            $schedule = [];
            if ($loan['status'] === 'Active' || $loan['status'] === 'Closed') {
                // First get the loan_id from the loans table
                $stmt = $conn->prepare("SELECT loan_id FROM loans WHERE application_id = ?");
                if ($stmt === false) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                $stmt->bind_param("i", $applicationId);
                $stmt->execute();
                $result = $stmt->get_result();
                $loanRow = $result->fetch_assoc();
                $stmt->close();

                if ($loanRow && $loanRow['loan_id']) {
                    $loanId = $loanRow['loan_id'];

                    // Now fetch the payment schedule using loan_id
                    $stmt = $conn->prepare("
                        SELECT payment_id, due_date, 
                               CAST(amount AS DECIMAL(12,2)) AS amount,
                               CAST(interest_amount AS DECIMAL(12,2)) AS interest_amount,
                               CAST(principal_amount AS DECIMAL(12,2)) AS principal_amount,
                               CAST(amount_paid AS DECIMAL(12,2)) AS amount_paid,
                               CAST(interest_paid AS DECIMAL(12,2)) AS interest_paid,
                               CAST(principal_paid AS DECIMAL(12,2)) AS principal_paid,
                               status
                        FROM payment_schedules
                        WHERE loan_id = ?
                        ORDER BY due_date ASC
                    ");
                    if ($stmt === false) {
                        throw new Exception("Prepare failed: " . $conn->error);
                    }
                    $stmt->bind_param("i", $loanId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $schedule = $result->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                }
            }

            debugLog("Loan details fetched successfully for application ID: $applicationId", $userId);
            $response = [
                'success' => true,
                'loan' => $loan,
                'documents' => $documents,
                'remarks' => $remarks,
                'schedule' => $schedule
            ];
        } else {
            debugLog("Loan application not found or unauthorized: $applicationId", $userId);
            $response = ['success' => false, 'message' => 'Loan application not found or unauthorized.'];
        }
    } catch (Exception $e) {
        debugLog("Loan details error: " . $e->getMessage(), $userId);
        $response = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Handle AJAX request for getting document status with rejection notes
if (isset($_GET['action']) && $_GET['action'] === 'get_document_status' && isset($_GET['application_id'])) {
    $applicationId = mysqli_real_escape_string($conn, $_GET['application_id']);

    $query = "
        SELECT d.document_id, dt.document_name, d.status, d.status_updated_at, d.rejection_notes,
               d.file_path, d.file_type, d.file_size
        FROM documents d
        JOIN document_types dt ON d.document_type_id = dt.document_type_id
        WHERE d.application_id = ?
        ORDER BY dt.document_name ASC
    ";

    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $applicationId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $documents = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $documents[] = $row;
    }
    mysqli_stmt_close($stmt);

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'documents' => $documents]);
    exit;
}

// Handle AJAX request for getting document rejection history
if (isset($_GET['action']) && $_GET['action'] === 'get_rejection_history' && isset($_GET['document_id'])) {
    $documentId = intval($_GET['document_id']);

    $query = "
        SELECT 
            d.document_id,
            d.status,
            d.rejection_notes,
            d.status_updated_at,
            d.file_type,
            COUNT(*) as rejection_count
        FROM documents d
        WHERE d.document_id = ?
    ";

    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $documentId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $document = $result->fetch_assoc();
    mysqli_stmt_close($stmt);

    // Get all statuses from activity log or create a simple history
    // For now, we'll use a simplified approach with the current rejection notes
    $history = [];
    if ($document && $document['status'] === 'Rejected' && $document['rejection_notes']) {
        $history[] = [
            'date' => $document['status_updated_at'],
            'reason' => $document['rejection_notes'],
            'status' => 'Rejected'
        ];
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'history' => $history, 'document' => $document]);
    exit;
}

// Handle AJAX request for updating document
if (isset($_POST['action']) && $_POST['action'] === 'update_document' && isset($_POST['document_id']) && isset($_FILES['new_file'])) {
    $documentId = intval($_POST['document_id']);
    $applicationId = $_POST['application_id']; // VARCHAR - keep as string
    debugLog("Updating document ID: $documentId for application ID: $applicationId", $userId);

    $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
    $maxFileSize = 5 * 1024 * 1024; // 5MB in bytes
    $uploadDir = 'Uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
        debugLog("Created upload directory: $uploadDir", $userId);
    }

    try {
        $stmt = $conn->prepare("
            SELECT d.file_path, dt.document_name
            FROM documents d
            JOIN document_types dt ON d.document_type_id = dt.document_type_id
            JOIN loan_applications la ON d.application_id = la.application_id
            WHERE d.document_id = ? AND d.application_id = ? AND la.user_id = ?
        ");
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("iii", $documentId, $applicationId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $currentDoc = $result->fetch_assoc();
        $stmt->close();

        if (!$currentDoc) {
            debugLog("Document not found or unauthorized: document_id=$documentId, application_id=$applicationId", $userId);
            $response = ['success' => false, 'message' => 'Document not found or unauthorized.'];
        } else {
            $file = $_FILES['new_file'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                debugLog("File upload error: " . $file['error'], $userId);
                $response = ['success' => false, 'message' => 'File upload error: ' . $file['error']];
            } elseif (!in_array($file['type'], $allowedTypes)) {
                debugLog("Invalid file type: " . $file['type'], $userId);
                $response = ['success' => false, 'message' => 'Invalid file type. Only JPEG, PNG, or PDF allowed.'];
            } elseif ($file['size'] > $maxFileSize) {
                debugLog("File size exceeds 5MB limit: " . $file['size'], $userId);
                $response = ['success' => false, 'message' => 'File size exceeds 5MB limit.'];
            } else {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = uniqid() . '.' . $ext;
                $destination = $uploadDir . $filename;

                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    if (file_exists($currentDoc['file_path'])) {
                        unlink($currentDoc['file_path']);
                        debugLog("Deleted old file: " . $currentDoc['file_path'], $userId);
                    }

                    // Get current time in Philippine Time (UTC+8)
                    $phpTimeZone = new DateTimeZone('Asia/Manila');
                    $now = new DateTime('now', $phpTimeZone);
                    $statusUpdatedAt = $now->format('Y-m-d H:i:s');

                    $stmt = $conn->prepare("
                        UPDATE documents
                        SET file_path = ?, file_type = ?, file_size = ?, status = 'pending', status_updated_at = ?
                        WHERE document_id = ?
                    ");
                    if ($stmt === false) {
                        throw new Exception("Prepare failed: " . $conn->error);
                    }
                    $fileSize = (int) $file['size'];
                    $stmt->bind_param("ssisi", $destination, $file['type'], $fileSize, $statusUpdatedAt, $documentId);
                    $stmt->execute();
                    $stmt->close();
                    debugLog("Document updated successfully: document_id=$documentId", $userId);

                    // Create notification for admins about the document update
                    require_once 'NotificationManager.php';
                    $notificationManager = new NotificationManager($conn);

                    // Get the application number from the loan_applications table
                    $appNumber = "Unknown";
                    if (!empty($applicationId)) {
                        $appNumberQuery = "SELECT application_id FROM loan_applications WHERE application_id = ? LIMIT 1";
                        $appStmt = $conn->prepare($appNumberQuery);
                        if ($appStmt) {
                            $appStmt->bind_param("s", $applicationId);
                            if ($appStmt->execute()) {
                                $appResult = $appStmt->get_result();
                                if ($appResult && $appResult->num_rows > 0) {
                                    $appRow = $appResult->fetch_assoc();
                                    $appNumber = !empty($appRow['application_id']) ? $appRow['application_id'] : "Unknown";
                                }
                            }
                            $appStmt->close();
                        }
                    }

                    // Get all admin2 users to notify them
                    $adminQuery = "SELECT id FROM admin2";
                    $adminResult = $conn->query($adminQuery);
                    if ($adminResult) {
                        while ($admin = $adminResult->fetch_assoc()) {
                            $adminId = $admin['id'];
                            // Notify admin: user_id, type_name, title, message, priority
                            $notificationManager->createNotification(
                                $adminId,
                                'document',
                                "Document Resubmitted: {$currentDoc['document_name']}",
                                "User has resubmitted the {$currentDoc['document_name']} for application $appNumber. Status set to Pending for re-review.",
                                'high'
                            );
                        }
                    }

                    $response = ['success' => true, 'message' => 'Document updated successfully.'];
                } else {
                    debugLog("Failed to move uploaded file: $destination", $userId);
                    $response = ['success' => false, 'message' => 'Failed to move uploaded file.'];
                }
            }
        }
    } catch (Exception $e) {
        debugLog("Document update error: " . $e->getMessage(), $userId);
        $response = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Prepare data for pie chart
$statusCounts = ['Pending' => 0, 'Active' => 0, 'Approved' => 0, 'Rejected' => 0, 'Completed' => 0];
foreach ($loanApplications as $app) {
    if (isset($statusCounts[$app['status']])) {
        $statusCounts[$app['status']]++;
    }
}

// ===== AUTO-POLLING ENDPOINTS =====

// Handle credit points polling request
if (isset($_GET['action']) && $_GET['action'] === 'get_credit_points') {
    debugLog("Polling: Fetching credit points", $userId);
    try {
        $creditManager = getCreditPointsManager();
        $userCreditPoints = $creditManager->getUserPoints($userId);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'credit_points' => $userCreditPoints
        ]);
    } catch (Exception $e) {
        debugLog("Error fetching credit points: " . $e->getMessage(), $userId);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching credit points'
        ]);
    }
    exit;
}

// Handle document statuses polling request
if (isset($_GET['action']) && $_GET['action'] === 'get_document_statuses') {
    debugLog("Polling: Fetching document statuses", $userId);
    try {
        // Check what document tables exist in the database
        $tables = [];
        $result = $conn->query("SHOW TABLES LIKE '%document%'");
        if ($result) {
            while ($row = $result->fetch_array()) {
                $tables[] = $row[0];
            }
        }
        debugLog("Available document tables: " . implode(', ', $tables), $userId);

        // Try different possible table structures
        $documentQuery = null;
        $documentTable = null;

        // First try: documents with document_types join
        if (in_array('documents', $tables) && in_array('document_types', $tables)) {
            $documentTable = 'documents';
            $documentQuery = "
                SELECT d.document_id, d.application_id, 
                       COALESCE(dt.document_name, CONCAT('Document Type ', COALESCE(d.document_type_id, 'Unknown'))) as document_name, 
                       d.status, d.rejection_notes, IFNULL(d.status_updated_at, d.updated_at) as status_updated_at, 
                       COALESCE(dt.document_name, 'Unknown Type') as type_name
                FROM documents d
                LEFT JOIN loan_applications la ON d.application_id = la.application_id
                LEFT JOIN document_types dt ON d.document_type_id = dt.document_type_id
                WHERE la.user_id = ?
                ORDER BY d.status_updated_at DESC
            ";
        }
        // Second try: loan_application_documents
        elseif (in_array('loan_application_documents', $tables)) {
            $documentTable = 'loan_application_documents';
            $documentQuery = "
                SELECT lad.document_id, lad.application_id, 
                       COALESCE(lad.name, CONCAT('Document ', lad.document_id)) as document_name, 
                       lad.status, lad.rejection_notes, IFNULL(lad.status_updated_at, lad.updated_at) as status_updated_at,
                       'Document' as type_name
                FROM loan_application_documents lad
                LEFT JOIN loan_applications la ON lad.application_id = la.application_id
                WHERE la.user_id = ?
                ORDER BY lad.status_updated_at DESC
            ";
        }
        // Third try: documents table without types
        elseif (in_array('documents', $tables)) {
            $documentTable = 'documents';
            $documentQuery = "
                SELECT d.document_id, d.application_id, 
                       CONCAT('Document Type ', COALESCE(d.document_type_id, d.document_id)) as document_name, 
                       d.status, d.rejection_notes, IFNULL(d.status_updated_at, d.updated_at) as status_updated_at,
                       'Document' as type_name
                FROM documents d
                LEFT JOIN loan_applications la ON d.application_id = la.application_id
                WHERE la.user_id = ?
                ORDER BY d.status_updated_at DESC
            ";
        }

        if (!$documentQuery) {
            throw new Exception("No suitable document table found. Available tables: " . implode(', ', $tables));
        }

        debugLog("Using document table: $documentTable", $userId);
        debugLog("Document query: $documentQuery", $userId);

        $stmt = $conn->prepare($documentQuery);
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("i", $userId);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $result = $stmt->get_result();
        $documents = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        debugLog("Polling: Retrieved " . count($documents) . " documents for user from $documentTable", $userId);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'documents' => $documents,
            'count' => count($documents),
            'table_used' => $documentTable
        ]);
    } catch (Exception $e) {
        debugLog("Error fetching document statuses: " . $e->getMessage(), $userId);
        debugLog("Error details: " . $e->getFile() . ":" . $e->getLine(), $userId);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching document statuses: ' . $e->getMessage(),
            'error_details' => DEBUG_MODE ? $e->getTrace() : null
        ]);
    }
    exit;
}

// Handle loan statuses polling request
if (isset($_GET['action']) && $_GET['action'] === 'get_loan_statuses') {
    debugLog("Polling: Fetching loan statuses", $userId);
    try {
        $stmt = $conn->prepare("
            SELECT la.application_id, la.status, la.pre_approval_status,
            la.credit_investigation_status, la.created_at, lt.type_name,
            la.amount_applied, la.final_loan_amount
            FROM loan_applications la
            JOIN loan_types lt ON la.loan_type_id = lt.loan_type_id
            WHERE la.user_id = ?
            ORDER BY la.created_at DESC
        ");

        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $loans = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        debugLog("Polling: Retrieved " . count($loans) . " loans for user", $userId);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'loans' => $loans,
            'count' => count($loans)
        ]);
    } catch (Exception $e) {
        debugLog("Error fetching loan statuses: " . $e->getMessage(), $userId);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching loan statuses'
        ]);
    }
    exit;
}

// Get active loan details for polling
if (isset($_GET['action']) && $_GET['action'] === 'get_active_loan') {
    debugLog("Polling: Fetching active loan details", $userId);
    try {
        $stmt = $conn->prepare("
            SELECT la.application_id, la.status, la.pre_approval_status,
            la.credit_investigation_status, la.created_at, lt.type_name,
            la.amount_applied, la.final_loan_amount, la.term_length, la.application_id as formatted_application_id, 
            la.loan_id as application_loan_id, l.loan_id as loans_loan_id, 
            l.total_principal, l.total_interest, l.total_paid, l.remaining_balance, 
            l.status as loan_status
            FROM loan_applications la
            JOIN loan_types lt ON la.loan_type_id = lt.loan_type_id
            LEFT JOIN loans l ON la.application_id = l.application_id
            WHERE la.user_id = ? AND la.status IN ('Active', 'Pending')
            ORDER BY la.created_at DESC LIMIT 1
        ");

        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $activeLoan = $result->fetch_assoc();
        $stmt->close();

        if ($activeLoan) {
            // Calculate progress
            $progress = 0;
            if ($activeLoan['total_principal'] > 0) {
                $progress = ($activeLoan['total_paid'] / ($activeLoan['total_principal'] + $activeLoan['total_interest'])) * 100;
            }
            $activeLoan['progress'] = round($progress, 1);
        }

        debugLog("Polling: Retrieved active loan", $userId);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'activeLoan' => $activeLoan
        ]);
    } catch (Exception $e) {
        debugLog("Error fetching active loan: " . $e->getMessage(), $userId);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching active loan'
        ]);
    }
    exit;
}

// ===== END AUTO-POLLING ENDPOINTS =====

?>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard</title>
    <link rel="stylesheet" href="CSS/admin_dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="CSS/user_dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="CSS/dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

    <style>
        .notification-wrapper {
            position: relative;
            display: inline-block;
        }

        .notification-bell {
            font-size: 1.3rem;
            color: #1b5e20;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            position: relative;
        }

        .notification-bell:hover {
            background: rgba(27, 94, 32, 0.1);
            color: #2e7d32;
            transform: scale(1.1);
        }

        .notification-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            background-color: #d32f2f;
            color: white;
            border-radius: 50%;
            min-width: 20px;
            width: auto;
            height: 20px;
            padding: 0 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: bold;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            animation: badgePulse 2s infinite;
        }

        .notification-dropdown {
            position: absolute;
            top: 50px;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            min-width: 350px;
            max-width: 400px;
            max-height: 400px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            overflow-y: auto;
        }

        .notification-dropdown-header {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f5f5f5;
            border-radius: 8px 8px 0 0;
        }

        .notification-dropdown-header h3 {
            margin: 0;
            font-size: 14px;
            font-weight: 600;
            color: #1b5e20;
        }

        .view-all-link {
            color: #1b5e20;
            text-decoration: none;
            font-size: 12px;
            font-weight: 500;
        }

        .view-all-link:hover {
            text-decoration: underline;
        }

        .notification-list {
            max-height: 300px;
            overflow-y: auto;
        }

        .notification-item {
            padding: 12px 15px;
            border-bottom: 1px solid #e0e0e0;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .notification-item:hover {
            background-color: #f9f9f9;
        }

        .notification-item.high {
            background-color: #fff3cd;
        }

        .notification-item-title {
            font-weight: 600;
            color: #1b5e20;
            font-size: 13px;
            margin-bottom: 4px;
        }

        .notification-item-message {
            color: #666;
            font-size: 12px;
            line-height: 1.4;
            margin-bottom: 4px;
        }

        .notification-item-time {
            color: #999;
            font-size: 11px;
        }

        .notification-loading {
            padding: 20px;
            text-align: center;
            color: #666;
        }

        .notification-empty {
            padding: 20px;
            text-align: center;
            color: #999;
        }

        @keyframes badgePulse {

            0%,
            100% {
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            }

            50% {
                box-shadow: 0 2px 8px rgba(211, 47, 47, 0.4);
            }
        }

        .empty-state {
            text-align: center;
            margin: 40px 0;
        }

        .empty-state img {
            margin-bottom: 18px;
        }

        /* All Applications Section */
        .all-applications-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .all-applications-section .section-header {
            margin-bottom: 25px;
        }

        .all-applications-section h2 {
            margin: 0 0 8px 0;
            color: #1b5e20;
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .all-applications-section h2 i {
            font-size: 28px;
        }

        .section-subtitle {
            color: #666;
            font-size: 14px;
            margin: 0;
        }

        .applications-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 20px;
        }

        .application-card {
            background: #f9f9f9;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #e0e0e0;
            transition: all 0.3s ease;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
        }

        .application-card:hover {
            box-shadow: 0 4px 12px rgba(27, 94, 32, 0.15);
            transform: translateY(-2px);
        }

        .application-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 18px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }

        .application-info h3 {
            margin: 0 0 6px 0;
            color: #1b5e20;
            font-size: 16px;
            font-weight: 600;
        }

        .app-id {
            color: #666;
            font-size: 13px;
            margin: 4px 0;
        }

        .app-date {
            color: #999;
            font-size: 12px;
            margin: 4px 0 0 0;
        }

        .application-status-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .application-details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 15px;
        }

        .detail-item {
            display: flex;
            gap: 12px;
            padding: 12px;
            background: white;
            border-radius: 6px;
            border: 1px solid #e0e0e0;
        }

        .detail-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 6px;
            flex-shrink: 0;
            font-size: 18px;
        }

        .detail-content {
            display: flex;
            flex-direction: column;
            justify-content: center;
            flex: 1;
        }

        .detail-label {
            color: #666;
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .detail-value {
            color: #1b5e20;
            font-size: 14px;
            font-weight: 600;
        }

        .detail-value.approved {
            color: #2e7d32;
        }

        .application-card-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }

        .action-link {
            background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .action-link:hover {
            background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(27, 94, 32, 0.3);
        }

        /* Status Badges */
        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-active {
            background: linear-gradient(135deg, #2e7d32 0%, #43a047 100%);
            color: white;
        }

        .badge-pending {
            background: linear-gradient(135deg, #1e88e5 0%, #42a5f5 100%);
            color: white;
        }

        .badge-approved {
            background: linear-gradient(135deg, #2e7d32 0%, #43a047 100%);
            color: white;
        }

        .badge-rejected {
            background: linear-gradient(135deg, #d32f2f 0%, #ef5350 100%);
            color: white;
        }

        .badge-completed {
            background: linear-gradient(135deg, #2e7d32 0%, #43a047 100%);
            color: white;
        }

        .badge-failed {
            background: linear-gradient(135deg, #d32f2f 0%, #ef5350 100%);
            color: white;
        }

        /* Remarks Section */
        .remarks-section {
            background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
            border-left: 4px solid #6a1b9a;
            border-radius: 6px;
            padding: 15px;
            margin: 15px 0;
        }

        .remarks-header {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #6a1b9a;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }

        .remarks-header i {
            font-size: 16px;
        }

        .remarks-content {
            color: #1b5e20;
            font-size: 13px;
            line-height: 1.6;
            word-wrap: break-word;
        }

        .remarks-content p {
            margin: 0;
        }
    </style>

</head>

<body>
    <div class="header">
        <div class="profileXdate">
            <div id="datetime" class="datetime"></div>
            <div class="notification-wrapper">
                <a href="notifications.php" class="notification-bell" title="View Notifications">
                    <i class="fa-solid fa-bell"></i>
                    <span class="notification-badge" id="notificationBadge" style="display: none;">0</span>
                </a>
                <div class="notification-dropdown" id="notificationDropdown" style="display: none;">
                    <div class="notification-dropdown-header">
                        <h3>Recent Notifications</h3>
                        <a href="notifications.php" class="view-all-link">View All</a>
                    </div>
                    <div class="notification-list" id="notificationList">
                        <div class="notification-loading">
                            <i class="fas fa-spinner fa-spin"></i> Loading...
                        </div>
                    </div>
                </div>
            </div>
            <div class="profile-container" onclick="toggleDropdown(event)">
                <img src="<?= htmlspecialchars($profile_image) ?>" alt="Profile Image" class="profile">
                <div class="dropdown-menu" id="dropdown">
                    <ul>
                        <li>
                            <a href="profile.php" class="<?= $current_page === 'profile.php' ? 'active' : '' ?>">
                                <img src="<?= htmlspecialchars($profile_image) ?>" alt="Profile Image"
                                    class="profile-icon"> Profile
                            </a>
                        </li>
                        <li><a class="logout" href="index.php"><i class="fa-solid fa-sign-out"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="nav-container">
        <button class="burger" aria-label="Toggle menu">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <nav>
            <img src="IMAGE/Main-Logo.png" alt="CYCLOAN Logo" class="sidebar-logo">
            <a href="user_dashboard.php" class="<?= $current_page === 'user_dashboard.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-table-columns"></i> DASHBOARD
            </a>
            <a href="user_active_record.php" class="<?= $current_page === 'user_active_record.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-user-check"></i> ACTIVE RECORDS
            </a>
            <a href="user_pending_records.php"
                class="<?= $current_page === 'user_pending_records.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-spinner"></i> PENDING RECORDS
            </a>
            <a href="user_closed_records.php"
                class="<?= $current_page === 'user_closed_records.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-circle-check"></i> CLOSED RECORDS
            </a>
            <a href="user_history_activity.php"
                class="<?= $current_page === 'user_history_activity.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-clipboard"></i> HISTORY ACTIVITY
            </a>
            <a href="#" onclick="openCalculatorModal(); return false;"
                class="<?= $current_page === 'loan_calculator' ? 'active' : '' ?>">
                <i class="fa-solid fa-calculator"></i> LOAN CALCULATOR
            </a>
        </nav>
    </div>

    <div class="main-content">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="message success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($_SESSION['success']) ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_SESSION['error']) ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div class="welcome-section">
                <h1>Welcome</h1>
                <p class="subtitle">Manage your loan applications and track your progress</p>
            </div>
        </div>

        <!-- Loan Information Section - Only show if no active loan -->
        <?php if (!$hasActiveOrPendingLoan): ?>
            <div class="loan-info-section">
                <div class="loan-info-container">
                    <!-- Loan Types -->
                    <div class="loan-types-section">
                        <h2><i class="fas fa-list"></i> Available Loan Types</h2>
                        <div class="loan-types-grid">

                            <div class="loan-type-card clickable-card"
                                onclick="scrollToRequirements('individualBusinessReq')">
                                <div class="type-header">
                                    <i class="fas fa-briefcase"></i>
                                    <h3>Business Loan</h3>
                                </div>
                                <div class="type-details">
                                    <p class="type-description">Grow your business with confidence</p>
                                    <div class="amount-badge">₱10,000 - ₱100,000</div>
                                    <ul class="type-features">
                                        <li><i class="fas fa-check"></i> Flexible term length</li>
                                        <li><i class="fas fa-check"></i> Choose repayment frequency</li>
                                    </ul>
                                    <div class="card-action">
                                        <span class="learn-more">View Requirements <i class="fas fa-arrow-right"></i></span>
                                    </div>
                                </div>
                            </div>

                            <div class="loan-type-card clickable-card" onclick="scrollToRequirements('agriculturalReq')">
                                <div class="type-header">
                                    <i class="fas fa-leaf"></i>
                                    <h3>Agricultural Loan</h3>
                                </div>
                                <div class="type-details">
                                    <p class="type-description">Invest in your farm and harvest success</p>
                                    <div class="amount-badge">₱10,000 - ₱100,000</div>
                                    <ul class="type-features">
                                        <li><i class="fas fa-check"></i> Flexible term length</li>
                                        <li><i class="fas fa-check"></i> Choose repayment frequency</li>
                                    </ul>
                                    <div class="card-action">
                                        <span class="learn-more">View Requirements <i class="fas fa-arrow-right"></i></span>
                                    </div>
                                </div>
                            </div>

                            <div class="loan-type-card clickable-card" onclick="scrollToRequirements('cooperativeReq')">

                                <div class="type-header">
                                    <i class="fas fa-handshake"></i>
                                    <h3>Cooperative Loan</h3>
                                </div>
                                <div class="type-details">
                                    <p class="type-description">Empower your cooperative organization</p>
                                    <div class="amount-badge">₱300,000 - ₱1,000,000</div>
                                    <ul class="type-features">
                                        <li><i class="fas fa-check"></i> Flexible term length</li>
                                        <li><i class="fas fa-check"></i> Choose repayment frequency</li>
                                    </ul>
                                    <div class="card-action">
                                        <span class="learn-more">View Requirements <i class="fas fa-arrow-right"></i></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Credit Investigation Notice -->
                        <div class="loan-notice">
                            <i class="fas fa-exclamation-circle"></i>
                            <div class="notice-content">
                                <strong>Important Notice:</strong> The final loan amount may vary based on the results of
                                our credit investigation. The approved amount will be determined after reviewing your
                                financial profile.
                            </div>
                        </div>
                    </div>

                    <!-- Application Requirements -->
                    <div class="requirements-section">
                        <h2><i class="fas fa-clipboard-list"></i> What You Need to Apply</h2>

                        <!-- Individual & Business Requirements -->
                        <div class="loan-type-requirements" id="individualBusinessReq">
                            <h3><i class="fas fa-user-tie"></i> Individual & Business Loan Requirements</h3>
                            <div class="requirements-grid">
                                <div class="requirement-item">
                                    <div class="requirement-icon">
                                        <i class="fas fa-image"></i>
                                    </div>
                                    <h3>2x2 Picture</h3>
                                    <p>Recent passport-sized photograph (JPEG, PNG, or PDF)</p>
                                </div>
                                <div class="requirement-item">
                                    <div class="requirement-icon">
                                        <i class="fa-solid fa-certificate"></i>
                                    </div>
                                    <h3>Voter's Certificate</h3>
                                    <p>Official voter's identification document</p>
                                </div>
                                <div class="requirement-item">
                                    <div class="requirement-icon">
                                        <i class="fas fa-home"></i>
                                    </div>
                                    <h3>Residence Certificate</h3>
                                    <p>Proof of current residential address</p>
                                </div>
                                <div class="requirement-item">
                                    <div class="requirement-icon">
                                        <i class="fas fa-file-alt"></i>
                                    </div>
                                    <h3>Barangay Clearance</h3>
                                    <p>Barangay community clearance certificate</p>
                                </div>
                                <div class="requirement-item">
                                    <div class="requirement-icon">
                                        <i class="fas fa-briefcase"></i>
                                    </div>
                                    <h3>Business Permit</h3>
                                    <p>DTI, BIR Certificate of Registration, or Barangay Business Permit</p>
                                </div>
                            </div>
                        </div>

                        <!-- Agricultural Requirements -->
                        <div class="loan-type-requirements" id="agriculturalReq" style="display: none;">
                            <h3><i class="fas fa-leaf"></i> Agricultural Loan Requirements</h3>
                            <div class="requirements-grid">
                                <div class="requirement-item">
                                    <div class="requirement-icon">
                                        <i class="fas fa-image"></i>
                                    </div>
                                    <h3>2x2 Picture</h3>
                                    <p>Recent passport-sized photograph (JPEG, PNG, or PDF)</p>
                                </div>
                                <div class="requirement-item">
                                    <div class="requirement-icon">
                                        <i class="fa-solid fa-certificate"></i>
                                    </div>
                                    <h3>Voter's Certificate</h3>
                                    <p>Official voter's identification document</p>
                                </div>
                                <div class="requirement-item">
                                    <div class="requirement-icon">
                                        <i class="fas fa-home"></i>
                                    </div>
                                    <h3>Residence Certificate</h3>
                                    <p>Proof of current residential address</p>
                                </div>
                                <div class="requirement-item">
                                    <div class="requirement-icon">
                                        <i class="fas fa-file-alt"></i>
                                    </div>
                                    <h3>Barangay Clearance</h3>
                                    <p>Barangay community clearance certificate</p>
                                </div>
                                <div class="requirement-item">
                                    <div class="requirement-icon">
                                        <i class="fas fa-chart-bar"></i>
                                    </div>
                                    <h3>Farm Plan & Budget</h3>
                                    <p>Detailed farm plan with budget breakdown for your agricultural project</p>
                                </div>
                            </div>
                        </div>

                        <!-- Cooperative Requirements -->
                        <div class="loan-type-requirements" id="cooperativeReq">
                            <h3><i class="fas fa-handshake"></i> Cooperative Loan Requirements</h3>
                            <div class="requirements-grid">
                                <div class="requirement-item">
                                    <div class="requirement-icon">
                                        <i class="fas fa-file-contract"></i>
                                    </div>
                                    <h3>Loan Project Proposal</h3>
                                    <p>Comprehensive project proposal with objectives and implementation plan</p>
                                </div>
                                <div class="requirement-item">
                                    <div class="requirement-icon">
                                        <i class="fas fa-file-invoice"></i>
                                    </div>
                                    <h3>Audited Financial Statement</h3>
                                    <p>Latest year audited financial statements of the cooperative</p>
                                </div>
                                <div class="requirement-item">
                                    <div class="requirement-icon">
                                        <i class="fas fa-university"></i>
                                    </div>
                                    <h3>Bank Statement</h3>
                                    <p>Last 6 months of bank statements showing cooperative accounts</p>
                                </div>
                                <div class="requirement-item">
                                    <div class="requirement-icon">
                                        <i class="fas fa-certificate"></i>
                                    </div>
                                    <h3>BIR Registration</h3>
                                    <p>Bureau of Internal Revenue registration certificate</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Call to Action -->
                    <div class="application-cta">
                        <div class="cta-content">
                            <h3>Ready to Get Your Loan?</h3>
                            <p>Start your application today and get approved faster than ever before!</p>
                        </div>
                        <a href="loan_register.php" class="cta-button">
                            <i class="fas fa-arrow-right"></i> Start Your Application Now
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($hasActiveOrPendingLoan): ?>
            <!-- Active Loan Overview -->
            <?php
            // Fetch active loan details
            $stmt = $conn->prepare("
                SELECT la.*, lt.type_name, la.application_id as formatted_application_id, 
                       la.loan_id as application_loan_id, l.loan_id as loans_loan_id, 
                       l.total_principal, l.total_interest, l.total_paid, l.remaining_balance, 
                       l.status as loan_status, la.final_loan_amount, la.term_length
                FROM loan_applications la
                JOIN loan_types lt ON la.loan_type_id = lt.loan_type_id
                LEFT JOIN loans l ON la.application_id = l.application_id
                WHERE la.user_id = ? AND la.status IN ('Active', 'Pending')
                ORDER BY la.created_at DESC LIMIT 1
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $activeLoan = $result->fetch_assoc();
            $stmt->close();

            if ($activeLoan):
                $progress = 0;
                if ($activeLoan['total_principal'] > 0) {
                    $progress = ($activeLoan['total_paid'] / ($activeLoan['total_principal'] + $activeLoan['total_interest'])) * 100;
                }
                ?>
                <div class="active-loan-banner" data-active-loan-id="<?= $activeLoan['application_id'] ?>">
                    <div class="banner-header">
                        <div class="banner-icon">
                            <i class="fas fa-file-contract"></i>
                        </div>
                        <div class="banner-info">
                            <?php if ($activeLoan['status'] === 'Active'): ?>
                                <h2>Active Loan - <?= htmlspecialchars($activeLoan['type_name']) ?></h2>
                                <p>Loan ID:
                                    <?= htmlspecialchars($activeLoan['application_loan_id'] ?: 'N/A') ?>
                                </p>
                            <?php elseif ($activeLoan['status'] === 'Closed'): ?>
                                <h2>Closed Loan - <?= htmlspecialchars($activeLoan['type_name']) ?></h2>
                                <p>Loan ID:
                                    <?= htmlspecialchars($activeLoan['application_loan_id'] ?: 'N/A') ?>
                                </p>
                            <?php else: ?>
                                <h2>Loan Application - <?= htmlspecialchars($activeLoan['type_name']) ?></h2>
                                <p>Application ID:
                                    <?= htmlspecialchars($activeLoan['formatted_application_id'] ?: 'N/A') ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="banner-status">
                            <span class="status-badge badge-<?= strtolower($activeLoan['status']) ?>"
                                id="active-loan-status-badge">
                                <?= htmlspecialchars($activeLoan['status']) ?>
                            </span>
                        </div>
                    </div>

                    <div class="loan-metrics-grid">
                        <div class="metric-card">
                            <div class="metric-icon"
                                style="background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 50%, #fbc02d 100%);">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <div class="metric-content">
                                <span class="metric-label">Loan Amount</span>
                                <span class="metric-value"
                                    id="active-loan-amount">₱<?= number_format($activeLoan['final_loan_amount'] ?: $activeLoan['amount_applied'], 2) ?></span>
                            </div>
                        </div>

                        <div class="metric-card">
                            <div class="metric-icon" style="background: linear-gradient(135deg, #d4af37 0%, #f9d71c 100%);">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="metric-content">
                                <span class="metric-label">Pre-Approval Status</span>
                                <span class="metric-value"
                                    id="active-loan-pre-approval"><?= htmlspecialchars($activeLoan['pre_approval_status'] ?: 'Pending') ?></span>
                            </div>
                        </div>

                        <div class="metric-card">
                            <div class="metric-icon" style="background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%);">
                                <i class="fas fa-search"></i>
                            </div>
                            <div class="metric-content">
                                <span class="metric-label">Credit Investigation</span>
                                <span class="metric-value"
                                    id="active-loan-credit-investigation"><?= htmlspecialchars($activeLoan['credit_investigation_status'] ?: 'Pending') ?></span>
                            </div>
                        </div>

                        <div class="metric-card">
                            <div class="metric-icon" style="background: linear-gradient(135deg, #1565c0 0%, #1976d2 100%);">
                                <i class="fas fa-coins"></i>
                            </div>
                            <div class="metric-content">
                                <span class="metric-label">Final Loan Amount</span>
                                <span class="metric-value"
                                    id="active-loan-final-amount">₱<?= number_format($activeLoan['final_loan_amount'] ?: 0, 2) ?></span>
                            </div>
                        </div>

                        <div class="metric-card">
                            <div class="metric-icon" style="background: linear-gradient(135deg, #6a1b9a 0%, #7b1fa2 100%);">
                                <i class="fas fa-calendar-days"></i>
                            </div>
                            <div class="metric-content">
                                <span class="metric-label">Term Length</span>
                                <span class="metric-value"
                                    id="active-loan-term-length"><?= htmlspecialchars($activeLoan['term_length'] ?: 'N/A') ?>
                                    months</span>
                            </div>
                        </div>

                        <?php if ($activeLoan['loans_loan_id']): ?>
                            <div class="metric-card">
                                <div class="metric-icon"
                                    style="background: linear-gradient(135deg, #1b5e20 0%, #fbc02d 50%, #2e7d32 100%);">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <div class="metric-content">
                                    <span class="metric-label">Total Paid</span>
                                    <span class="metric-value success"
                                        id="active-loan-total-paid">₱<?= number_format($activeLoan['total_paid'], 2) ?></span>
                                </div>
                            </div>

                            <div class="metric-card">
                                <div class="metric-icon"
                                    style="background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 70%, #fbc02d 100%);">
                                    <i class="fas fa-wallet"></i>
                                </div>
                                <div class="metric-content">
                                    <span class="metric-label">Remaining Balance</span>
                                    <span class="metric-value warning"
                                        id="active-loan-remaining-balance">₱<?= number_format($activeLoan['remaining_balance'], 2) ?></span>
                                </div>
                            </div>

                            <div class="metric-card">
                                <div class="metric-icon"
                                    style="background: linear-gradient(135deg, #1b5e20 0%, #fbc02d 40%, #2e7d32 100%);">
                                    <i class="fas fa-percentage"></i>
                                </div>
                                <div class="metric-content">
                                    <span class="metric-label">Progress</span>
                                    <span class="metric-value"
                                        id="active-loan-progress-percent"><?= number_format($progress, 1) ?>%</span>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="metric-card pending-setup">
                                <div class="metric-icon"
                                    style="background: linear-gradient(135deg, #1b5e20 0%, #fbc02d 40%, #2e7d32 100%);">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="metric-content">
                                    <span class="metric-label">Status</span>
                                    <span class="metric-value pending">Awaiting Approval</span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Loan Progress Tracker -->
                    <div class="loan-progress-tracker">
                        <?php
                        $steps = ['Pending', 'Pre-Approval', 'Credit Investigation', 'Active', 'Closed'];
                        $currentStep = 0;

                        // Determine the current step based on status
                        if ($activeLoan['status'] === 'Closed') {
                            $currentStep = 4;
                        } elseif ($activeLoan['status'] === 'Active') {
                            $currentStep = 3;
                        } elseif ($activeLoan['credit_investigation_status'] === 'Completed' || $activeLoan['status'] === 'Approved') {
                            $currentStep = 2;
                        } elseif ($activeLoan['pre_approval_status'] === 'Approved') {
                            $currentStep = 1;
                        } else {
                            $currentStep = 0;
                        }
                        ?>
                        <div class="progress-bar">
                            <?php foreach ($steps as $i => $step): ?>
                                <span
                                    class="progress-step <?= $i < $currentStep ? 'done' : '' ?> <?= $i == $currentStep ? 'current' : '' ?> <?= $i == $currentStep && $step == 'Active' ? 'active-ongoing' : '' ?>">
                                    <?= $step ?>
                                    <?php if ($i < count($steps) - 1): ?><span class="progress-arrow">→</span><?php endif; ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php if ($activeLoan['loans_loan_id']): ?>
                        <!-- Payment Progress Bar -->
                        <div class="payment-progress">
                            <div class="progress-header">
                                <span>Payment Progress</span>
                                <span class="progress-percent"><?= number_format($progress, 1) ?>%</span>
                            </div>
                            <div class="progress-bar-container">
                                <div class="progress-bar-fill" id="active-loan-progress-bar"
                                    style="width: <?= min($progress, 100) ?>%"></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="banner-actions">
                        <button class="action-btn primary"
                            onclick="openLoanDetailsModal('<?= htmlspecialchars($activeLoan['application_id'], ENT_QUOTES) ?>')">
                            <i class="fas fa-eye"></i> View Full Details
                        </button>
                        <a href="user_history_activity.php" class="action-btn secondary">
                            <i class="fas fa-history"></i> View History
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Info Alert -->
            <div class="info-alert">
                <i class="fas fa-info-circle"></i>
                <div class="alert-content">
                    <strong>Note:</strong> You cannot apply for a new loan while you have an active or pending loan
                    application.
                    Complete your current loan or wait for a decision on pending applications.
                </div>
            </div>

            <!-- Document Status Section CSS -->
            <style>
                .document-status-section {
                    margin-top: 30px;
                    padding: 20px;
                    background: #f8f9fa;
                    border-radius: 8px;
                }

                .document-status-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
                    gap: 20px;
                    margin-top: 15px;
                }

                .document-card {
                    background: white;
                    border-radius: 10px;
                    padding: 18px;
                    border-left: 5px solid #1b5e20;
                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                    transition: all 0.3s ease;
                }

                .document-card:hover {
                    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
                    transform: translateY(-4px);
                }

                .document-card-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                    margin-bottom: 12px;
                    padding-bottom: 12px;
                    border-bottom: 2px solid #f0f0f0;
                    gap: 10px;
                }

                .document-name {
                    font-weight: 700;
                    color: #2c5f2d;
                    flex: 1;
                    font-size: 15px;
                }

                .status-badge {
                    padding: 6px 12px;
                    border-radius: 6px;
                    font-size: 12px;
                    font-weight: 700;
                    white-space: nowrap;
                    border: 1px solid transparent;
                }

                .status-badge.status-approved {
                    background-color: #d4edda;
                    color: #155724;
                    border-color: #c3e6cb;
                }

                .status-badge.status-rejected {
                    background-color: #f8d7da;
                    color: #721c24;
                    border-color: #f5c6cb;
                }

                .status-badge.status-pending {
                    background-color: #fff3cd;
                    color: #856404;
                    border-color: #ffeaa7;
                }

                .document-card-body {
                    font-size: 14px;
                    color: #555;
                }

                .document-card-actions {
                    display: flex;
                    gap: 10px;
                    margin-top: 12px;
                    padding-top: 12px;
                    border-top: 1px solid #f0f0f0;
                    min-height: 36px;
                }

                .btn-resubmit {
                    flex: 1;
                    padding: 8px 12px;
                    background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
                    color: white;
                    border: none;
                    border-radius: 6px;
                    font-size: 13px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    display: flex !important;
                    align-items: center;
                    justify-content: center;
                    gap: 6px;
                    width: 100%;
                    min-width: 100px;
                }

                .btn-resubmit:hover {
                    background: linear-gradient(135deg, #1976D2 0%, #1565C0 100%);
                    box-shadow: 0 4px 12px rgba(33, 150, 243, 0.3);
                    transform: translateY(-2px);
                }

                .btn-resubmit:active {
                    transform: translateY(0);
                }

                .document-info {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 8px;
                }

                .info-label {
                    font-weight: 600;
                    color: #666;
                }

                .info-value {
                    color: #333;
                }

                .info-value.approved {
                    color: #155724;
                    font-weight: 600;
                }

                .info-value.rejected {
                    color: #721c24;
                    font-weight: 600;
                }

                .loading-status {
                    text-align: center;
                    padding: 40px 20px;
                    color: #999;
                }

                .loading-status i {
                    animation: spin 1s linear infinite;
                }

                @keyframes spin {
                    0% {
                        transform: rotate(0deg);
                    }

                    100% {
                        transform: rotate(360deg);
                    }
                }
            </style>

            <!-- Document Status Section -->
            <div class="document-status-section">
                <div class="section-header">
                    <h3><i class="fas fa-file-check"></i> Document Status</h3>
                    <button class="refresh-btn" id="refreshDocStatusBtn" title="Refresh document status">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
                <div class="document-status-grid" id="documentStatusGrid">
                    <div class="loading-status">
                        <i class="fas fa-spinner"></i> Loading documents...
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- All Loan Applications Section with Credit Investigation Details -->
        <?php if (!empty($loanApplications) && count($loanApplications) > 1): ?>
            <div class="all-applications-section">
                <div class="section-header">
                    <h2><i class="fas fa-list"></i> All Loan Applications</h2>
                    <p class="section-subtitle">Complete overview of your loan applications and their status</p>
                </div>

                <div class="applications-grid">
                    <?php foreach ($loanApplications as $app):
                        // Determine status colors
                        $statusColorClass = strtolower($app['status']);
                        $preApprovalClass = $app['pre_approval_status'] ? strtolower($app['pre_approval_status']) : 'pending';
                        $creditInvClass = $app['credit_investigation_status'] ? strtolower($app['credit_investigation_status']) : 'pending';

                        // Determine icons for credit investigation status
                        $creditInvIcon = '';
                        $creditInvColor = '';
                        switch ($app['credit_investigation_status']) {
                            case 'Completed':
                                $creditInvIcon = 'fa-check-circle';
                                $creditInvColor = '#2e7d32'; // Green
                                break;
                            case 'Pending':
                                $creditInvIcon = 'fa-hourglass-half';
                                $creditInvColor = '#1e88e5'; // Blue
                                break;
                            case 'Failed':
                                $creditInvIcon = 'fa-times-circle';
                                $creditInvColor = '#d32f2f'; // Red
                                break;
                            default:
                                $creditInvIcon = 'fa-question-circle';
                                $creditInvColor = '#757575'; // Gray
                        }
                        ?>
                        <div class="application-card" style="border-top: 4px solid <?php echo $creditInvColor; ?>;">
                            <div class="application-card-header">
                                <div class="application-info">
                                    <h3><?php echo htmlspecialchars($app['type_name']); ?></h3>
                                    <p class="app-id">ID: <?php echo htmlspecialchars($app['formatted_application_id']); ?></p>
                                    <p class="app-date" style="color: #999; font-size: 12px;">
                                        <?php echo date('M d, Y', strtotime($app['created_at'])); ?>
                                    </p>
                                </div>
                                <div class="application-status-badges">
                                    <span class="status-badge badge-<?php echo $statusColorClass; ?>">
                                        <?php echo htmlspecialchars($app['status']); ?>
                                    </span>
                                </div>
                            </div>

                            <div class="application-details-grid">
                                <!-- Applied Amount -->
                                <div class="detail-item">
                                    <div class="detail-icon"
                                        style="background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%); color: white;">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </div>
                                    <div class="detail-content">
                                        <span class="detail-label">Applied Amount</span>
                                        <span
                                            class="detail-value">₱<?php echo number_format($app['amount_applied'], 2); ?></span>
                                    </div>
                                </div>

                                <!-- Final Loan Amount (if credit investigation completed) -->
                                <?php if ($app['credit_investigation_status'] === 'Completed' && $app['final_loan_amount']): ?>
                                    <div class="detail-item">
                                        <div class="detail-icon"
                                            style="background: linear-gradient(135deg, #fbc02d 0%, #f9d71c 100%); color: #1b5e20;">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                        <div class="detail-content">
                                            <span class="detail-label">Approved Amount</span>
                                            <span
                                                class="detail-value approved">₱<?php echo number_format($app['final_loan_amount'], 2); ?></span>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Pre-Approval Status -->
                                <div class="detail-item">
                                    <div class="detail-icon"
                                        style="background: linear-gradient(135deg, #d4af37 0%, #f9d71c 100%); color: #1b5e20;">
                                        <i class="fas fa-clipboard-check"></i>
                                    </div>
                                    <div class="detail-content">
                                        <span class="detail-label">Pre-Approval</span>
                                        <span
                                            class="detail-value"><?php echo htmlspecialchars($app['pre_approval_status'] ?: 'Pending'); ?></span>
                                    </div>
                                </div>

                                <!-- Credit Investigation Status with Icon -->
                                <div class="detail-item">
                                    <div class="detail-icon"
                                        style="background: linear-gradient(135deg, <?php echo $creditInvColor; ?> 0%, <?php echo $creditInvColor; ?> 100%); color: white;">
                                        <i class="fas <?php echo $creditInvIcon; ?>"></i>
                                    </div>
                                    <div class="detail-content">
                                        <span class="detail-label">Credit Investigation</span>
                                        <span
                                            class="detail-value"><?php echo htmlspecialchars($app['credit_investigation_status'] ?: 'Pending'); ?></span>
                                    </div>
                                </div>

                                <!-- Term Length (if credit investigation completed) -->
                                <?php if ($app['credit_investigation_status'] === 'Completed' && $app['term_length']): ?>
                                    <div class="detail-item">
                                        <div class="detail-icon"
                                            style="background: linear-gradient(135deg, #6a1b9a 0%, #9c27b0 100%); color: white;">
                                            <i class="fas fa-calendar-alt"></i>
                                        </div>
                                        <div class="detail-content">
                                            <span class="detail-label">Recommended Term</span>
                                            <span class="detail-value"><?php echo htmlspecialchars($app['term_length']); ?>
                                                Months</span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Admin Remarks Section (if credit investigation completed and remarks exist) -->
                            <?php
                            // Fetch remarks for this application
                            $remarkStmt = $conn->prepare("SELECT remarks FROM remarks WHERE application_id = ? ORDER BY created_at DESC LIMIT 1");
                            $remarkStmt->bind_param("s", $app['formatted_application_id']);
                            $remarkStmt->execute();
                            $remarkResult = $remarkStmt->get_result();
                            $remarkData = $remarkResult->fetch_assoc();
                            $remarkStmt->close();
                            ?>
                            <?php if ($app['credit_investigation_status'] === 'Completed' && $remarkData): ?>
                                <div class="remarks-section">
                                    <div class="remarks-header">
                                        <i class="fas fa-sticky-note"></i>
                                        <span>Investigation Notes</span>
                                    </div>
                                    <div class="remarks-content">
                                        <?php echo nl2br(htmlspecialchars($remarkData['remarks'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="application-card-actions">
                                <button class="action-link"
                                    onclick="openLoanDetailsModal('<?php echo htmlspecialchars($app['formatted_application_id'], ENT_QUOTES); ?>')">
                                    <i class="fas fa-eye"></i> View Details
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Credit Points Card - Only show if user has loan applications -->
        <?php if (!empty($loanApplications)): ?>
            <div class="credit-points-card">
                <div class="credit-card-header">
                    <div class="credit-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="credit-info">
                        <h3>Your Credit Score</h3>
                        <div class="credit-points-display">
                            <span class="points-value"><?php echo number_format($userCreditPoints); ?></span>
                            <span class="points-label">Points</span>
                        </div>
                    </div>
                </div>
                <div class="credit-card-body">
                    <p class="credit-description">
                        <i class="fas fa-info-circle"></i>
                        Earn points by completing loans on time. Higher scores improve loan approval chances!
                    </p>
                    <?php if (!empty($creditHistory)): ?>
                        <div class="recent-activity">
                            <h4><i class="fas fa-history"></i> Recent Activity</h4>
                            <ul class="activity-list">
                                <?php foreach (array_slice($creditHistory, 0, 3) as $activity): ?>
                                    <li
                                        class="activity-item <?php echo $activity['points_change'] > 0 ? 'positive' : 'negative'; ?>">
                                        <div class="activity-icon">
                                            <i
                                                class="fas <?php echo $activity['points_change'] > 0 ? 'fa-arrow-up' : 'fa-arrow-down'; ?>"></i>
                                        </div>
                                        <div class="activity-details">
                                            <span
                                                class="activity-reason"><?php echo htmlspecialchars($activity['reason']); ?></span>
                                            <span
                                                class="activity-date"><?php echo date('M d, Y', strtotime($activity['created_at'])); ?></span>
                                        </div>
                                        <div
                                            class="activity-points <?php echo $activity['points_change'] > 0 ? 'positive' : 'negative'; ?>">
                                            <?php echo $activity['points_change'] > 0 ? '+' : ''; ?>
                                            <?php echo number_format($activity['points_change']); ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Payment History Timeline -->
        <?php if (!empty($paymentHistory)): ?>
            <div class="payment-timeline-section">
                <div class="timeline-header">
                    <div class="timeline-title">
                        <i class="fas fa-history"></i>
                        <h2>Payment History Timeline</h2>
                    </div>
                    <div class="timeline-stats">
                        <span class="stat-item">
                            <i class="fas fa-receipt"></i>
                            <strong><?php echo count($paymentHistory); ?></strong> Recent Payments
                        </span>
                    </div>
                </div>

                <div class="timeline-container">
                    <?php foreach ($paymentHistory as $index => $payment):
                        $isOnTime = strtotime($payment['payment_date']) <= strtotime($payment['due_date']);
                        $isEarly = strtotime($payment['payment_date']) < strtotime($payment['due_date']);
                        $daysLate = max(0, (strtotime($payment['payment_date']) - strtotime($payment['due_date'])) / 86400);

                        $statusClass = $isOnTime ? 'on-time' : 'late';
                        $statusIcon = $isOnTime ? 'fa-check-circle' : 'fa-exclamation-circle';
                        $statusText = $isEarly ? 'Early Payment' : ($isOnTime ? 'On Time' : 'Late Payment');
                        ?>
                        <div class="timeline-item <?php echo $statusClass; ?>">
                            <div class="timeline-marker">
                                <i class="fas <?php echo $statusIcon; ?>"></i>
                            </div>
                            <div class="timeline-content">
                                <div class="timeline-card">
                                    <div class="timeline-card-header">
                                        <div class="payment-status-badge <?php echo $statusClass; ?>">
                                            <i class="fas <?php echo $statusIcon; ?>"></i>
                                            <?php echo $statusText; ?>
                                        </div>
                                        <div class="payment-date">
                                            <i class="fas fa-calendar"></i>
                                            <?php echo date('M d, Y', strtotime($payment['payment_date'])); ?>
                                        </div>
                                    </div>
                                    <div class="timeline-card-body">
                                        <div class="payment-amount">
                                            <span class="amount-label">Amount Paid</span>
                                            <span
                                                class="amount-value">₱<?php echo number_format($payment['amount_paid'], 2); ?></span>
                                        </div>
                                        <div class="payment-details">
                                            <div class="detail-row">
                                                <span class="detail-label"><i class="fas fa-file-invoice"></i> Loan Type:</span>
                                                <span
                                                    class="detail-value"><?php echo htmlspecialchars($payment['loan_type']); ?></span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label"><i class="fas fa-credit-card"></i> Payment
                                                    Method:</span>
                                                <span
                                                    class="detail-value"><?php echo htmlspecialchars($payment['payment_method']); ?></span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label"><i class="fas fa-clock"></i> Due Date:</span>
                                                <span
                                                    class="detail-value"><?php echo date('M d, Y', strtotime($payment['due_date'])); ?></span>
                                            </div>
                                            <?php if (!$isOnTime): ?>
                                                <div class="detail-row late-notice">
                                                    <span class="detail-label"><i class="fas fa-exclamation-triangle"></i> Days
                                                        Late:</span>
                                                    <span class="detail-value"><?php echo ceil($daysLate); ?> day(s)</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="timeline-card-footer">
                                        <span class="payment-id">Payment #<?php echo $payment['payment_id']; ?></span>
                                        <a href="#" class="view-receipt-btn"
                                            onclick="viewReceipt(<?php echo $payment['payment_id']; ?>); return false;">
                                            <i class="fas fa-file-download"></i> View Receipt
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (count($paymentHistory) >= 10): ?>
                    <div class="timeline-footer">
                        <a href="user_history_activity.php" class="view-all-btn">
                            <i class="fas fa-list"></i> View All Payment History
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Loan Details Modal -->
        <div id="loanDetailsModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2><i class="fa-solid fa-file-invoice-dollar"></i> Loan Details</h2>
                    <button class="close" onclick="closeLoanDetailsModal()" title="Close" aria-label="Close modal">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="loanDetailsContent" class="loan-details">
                        <div class="modal-loader">
                            <i class="fas fa-spinner fa-spin"></i>
                            <span>Loading your loan details...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Document Status Modal -->
        <div id="documentStatusModal" class="modal">
            <div class="modal-content" style="max-width: 700px;">
                <div class="modal-header">
                    <h2><i class="fas fa-file-check"></i> Document Details & Rejection Notes</h2>
                    <button class="close" onclick="closeDocumentStatusModal()" title="Close" aria-label="Close modal">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="documentStatusContent" class="document-details">
                        <div class="modal-loader">
                            <i class="fas fa-spinner fa-spin"></i>
                            <span>Loading document details...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Loan Calculator Modal -->
        <div id="loanCalculatorModal" class="calculatorModal">
            <div class="calculatorModal-content">
                <div class="calculatorModal-header">
                    <h2><i class="fa-solid fa-calculator"></i> Loan Calculator</h2>
                    <button class="close" onclick="closeCalculatorModal()" title="Close" aria-label="Close modal">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="calculatorModal-body">
                    <div class="calculator-layout">
                        <!-- Calculator Form -->
                        <div class="calculator-form">
                            <h3
                                style="margin: 0 0 15px 0; color: var(--primary); display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-pen-to-square"></i> Loan Details
                            </h3>
                            <form id="calculatorForm" onsubmit="event.preventDefault(); calculateLoan();">
                                <div class="form-group">
                                    <label for="loanType">Loan Type <span
                                            style="color: var(--rejected);">*</span></label>
                                    <select id="loanType" name="loanType" required onchange="updateLoanAmountRange()">
                                        <option value="">Select Loan Type</option>
                                        <option value="Individual">Individual</option>
                                        <option value="Cooperative">Cooperative</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="loanAmount" id="loanAmountLabel">Loan Amount (₱10,000 - ₱100,000) <span
                                            style="color: var(--rejected);">*</span></label>
                                    <input type="number" id="loanAmount" name="loanAmount" min="10000" max="100000"
                                        step="1000" required placeholder="Enter amount (₱)">
                                </div>

                                <div class="form-group">
                                    <label for="interestRate">Annual Interest Rate <span
                                            style="color: var(--rejected);">*</span></label>
                                    <input type="number" id="interestRate" name="interestRate" step="0.01" readonly
                                        placeholder="Auto-calculated">
                                    <div class="interest-rate-display" id="interestRateDisplay"
                                        style="font-size: 12px; color: var(--primary); margin-top: 4px;">Interest rate
                                        will be calculated based on loan type</div>
                                </div>

                                <div class="form-group">
                                    <label for="termLength">Term Length (Months) <span
                                            style="color: var(--rejected);">*</span></label>
                                    <select id="termLength" name="termLength" required
                                        onchange="updateRepaymentOptions()">
                                        <option value="">Select Term Length</option>
                                        <option value="6">6 Months</option>
                                        <option value="12">12 Months</option>
                                        <option value="18">18 Months</option>
                                        <option value="24">24 Months</option>
                                        <option value="36">36 Months</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="repaymentFrequency">Repayment Frequency <span
                                            style="color: var(--rejected);">*</span></label>
                                    <select id="repaymentFrequency" name="repaymentFrequency" required>
                                        <option value="">Select Repayment Frequency</option>
                                        <option value="Monthly">Monthly</option>
                                        <option value="Quarterly">Quarterly</option>
                                        <option value="Annually">Annually</option>
                                    </select>
                                </div>

                                <div class="error-message" id="errorMessage" style="display: none;">
                                    <i class="fa-solid fa-exclamation-circle"></i>
                                    <span id="errorText"></span>
                                </div>

                                <button type="submit" class="calculate-btn">
                                    <i class="fa-solid fa-calculator"></i> Calculate
                                </button>
                            </form>
                        </div>

                        <!-- Results Display -->
                        <div class="results-container">
                            <h3
                                style="margin: 0 0 15px 0; color: var(--primary); display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-chart-pie"></i> Approximately
                            </h3>
                            <div id="resultsDisplay">
                                <div class="no-result">
                                    <i class="fa-solid fa-calculator"></i>
                                    <p>Enter loan details and click Calculate to see results</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Amortization Schedule -->
                    <div id="amortizationContainer" style="display: none; margin-top: 30px;">
                        <div
                            style="background: white; border-radius: 12px; padding: 30px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);">
                            <h2
                                style="margin-bottom: 20px; color: var(--primary); display: flex; align-items: center; gap: 10px;">
                                <i class="fa-solid fa-table"></i> Amortization Schedule
                            </h2>
                            <div class="amortization-schedule">
                                <table class="amortization-table" id="amortizationTable">
                                    <thead>
                                        <tr>
                                            <th>Payment #</th>
                                            <th>Payment Amount</th>
                                            <th>Principal</th>
                                            <th>Interest</th>
                                            <th>Balance</th>
                                        </tr>
                                    </thead>
                                    <tbody id="amortizationBody"></tbody>
                                </table>
                            </div>
                            <button class="print-btn" onclick="window.print()"
                                style="margin-top: 20px; padding: 10px 20px; background: var(--primary); color: white; border: none; border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                                <i class="fa-solid fa-print"></i> Print Schedule
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        // View Receipt Function
        function viewReceipt(paymentId) {
            // Create a simple modal or redirect to receipt page
            // For now, we'll show an alert - you can enhance this later
            alert('Receipt download functionality will be implemented. Payment ID: ' + paymentId);

            // TODO: Implement actual receipt generation/download
            // window.open('download_receipt.php?payment_id=' + paymentId, '_blank');
        }

        // Handle requirements display based on loan type (for user reference)
        document.addEventListener('DOMContentLoaded', function () {
            // Show Individual/Business requirements by default
            updateRequirementsDisplay();

            // If there's a loan calculator, update requirements when loan type changes
            const loanTypeSelect = document.getElementById('loanType');
            if (loanTypeSelect) {
                loanTypeSelect.addEventListener('change', updateRequirementsDisplay);
            }

            // Initialize real-time validation for loan calculator
            initializeCalculatorValidation();
        });

        function updateRequirementsDisplay() {
            const loanTypeInput = document.querySelector('input[name="loanType"], select#loanType');
            const loanType = loanTypeInput ? (loanTypeInput.value || 'Individual') : 'Individual';

            // Show all requirement sections by default (only if they exist)
            const individualBusinessReq = document.getElementById('individualBusinessReq');
            const agriculturalReq = document.getElementById('agriculturalReq');
            const cooperativeReq = document.getElementById('cooperativeReq');

            if (individualBusinessReq) individualBusinessReq.style.display = 'block';
            if (agriculturalReq) agriculturalReq.style.display = 'block';
            if (cooperativeReq) cooperativeReq.style.display = 'block';
        }

        // Scroll to specific requirement section
        function scrollToRequirements(sectionId) {
            const element = document.getElementById(sectionId);
            if (element) {
                element.scrollIntoView({ behavior: 'smooth', block: 'start' });
                // Add highlight animation
                element.style.animation = 'highlightSection 1s ease';
                setTimeout(() => {
                    element.style.animation = '';
                }, 1000);
            }
        }

        // Enhanced Real-Time Validation for Loan Calculator
        function initializeCalculatorValidation() {
            const loanTypeSelect = document.getElementById('loanType');
            const loanAmountInput = document.getElementById('loanAmount');
            const termLengthSelect = document.getElementById('termLength');
            const repaymentFrequencySelect = document.getElementById('repaymentFrequency');
            const errorMessage = document.getElementById('errorMessage');
            const errorText = document.getElementById('errorText');

            if (!loanTypeSelect || !loanAmountInput) return;

            // Real-time validation on loan type change
            loanTypeSelect.addEventListener('change', function () {
                validateLoanType();
                validateLoanAmount();
                clearErrors();
            });

            // Real-time validation on loan amount input
            loanAmountInput.addEventListener('input', function () {
                validateLoanAmount();
                clearErrors();
            });

            loanAmountInput.addEventListener('blur', function () {
                validateLoanAmount();
            });

            // Real-time validation on term length change
            if (termLengthSelect) {
                termLengthSelect.addEventListener('change', function () {
                    validateTermLength();
                    validateRepaymentFrequency();
                    clearErrors();
                });
            }

            // Real-time validation on repayment frequency change
            if (repaymentFrequencySelect) {
                repaymentFrequencySelect.addEventListener('change', function () {
                    validateRepaymentFrequency();
                    clearErrors();
                });
            }
        }

        // Validation functions
        function validateLoanType() {
            const loanType = document.getElementById('loanType').value;
            const loanTypeField = document.getElementById('loanType').parentElement;

            if (!loanType) {
                addValidationError(loanTypeField, 'Please select a loan type');
                return false;
            } else {
                removeValidationError(loanTypeField);
                return true;
            }
        }

        function validateLoanAmount() {
            const loanType = document.getElementById('loanType').value;
            const loanAmount = parseFloat(document.getElementById('loanAmount').value);
            const loanAmountField = document.getElementById('loanAmount').parentElement;

            if (!loanType) {
                addValidationError(loanAmountField, 'Select loan type first');
                return false;
            }

            let min, max;
            if (loanType === 'Individual' || loanType === 'Business' || loanType === 'Agricultural') {
                min = 10000;
                max = 100000;
            } else if (loanType === 'Cooperative') {
                min = 300000;
                max = 1000000;
            }

            if (isNaN(loanAmount)) {
                addValidationError(loanAmountField, `Enter loan amount (₱${min.toLocaleString()} - ₱${max.toLocaleString()})`);
                return false;
            } else if (loanAmount < min) {
                addValidationError(loanAmountField, `Minimum amount is ₱${min.toLocaleString()}`);
                return false;
            } else if (loanAmount > max) {
                addValidationError(loanAmountField, `Maximum amount is ₱${max.toLocaleString()}`);
                return false;
            } else {
                removeValidationError(loanAmountField);
                return true;
            }
        }

        function validateTermLength() {
            const termLength = document.getElementById('termLength').value;
            const termField = document.getElementById('termLength').parentElement;

            if (!termLength) {
                addValidationError(termField, 'Please select term length');
                return false;
            } else {
                removeValidationError(termField);
                return true;
            }
        }

        function validateRepaymentFrequency() {
            const termLength = parseInt(document.getElementById('termLength').value) || 0;
            const repaymentFrequency = document.getElementById('repaymentFrequency').value;
            const frequencyField = document.getElementById('repaymentFrequency').parentElement;

            if (!repaymentFrequency) {
                addValidationError(frequencyField, 'Please select repayment frequency');
                return false;
            }

            if (termLength === 6 && repaymentFrequency !== 'Monthly') {
                addValidationError(frequencyField, '6-month term only allows Monthly repayment');
                return false;
            } else {
                removeValidationError(frequencyField);
                return true;
            }
        }

        function addValidationError(field, message) {
            removeValidationError(field);
            const errorDiv = document.createElement('span');
            errorDiv.className = 'validation-error';
            errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
            field.appendChild(errorDiv);
            field.style.borderColor = '#ff6b6b';
        }

        function removeValidationError(field) {
            const errorDiv = field.querySelector('.validation-error');
            if (errorDiv) {
                errorDiv.remove();
            }
            field.style.borderColor = '';
        }

        function clearErrors() {
            const errorMessage = document.getElementById('errorMessage');
            if (errorMessage) {
                errorMessage.style.display = 'none';
            }
        }

        // Add highlight animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes highlightSection {
                0% { background-color: transparent; }
                50% { background-color: rgba(27, 94, 32, 0.1); }
                100% { background-color: transparent; }
            }
            
            .validation-error {
                display: block;
                color: #ff6b6b;
                font-size: 0.85rem;
                margin-top: 5px;
                font-weight: 500;
                animation: slideInError 0.3s ease;
            }
            
            @keyframes slideInError {
                from {
                    opacity: 0;
                    transform: translateY(-5px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        `;
        document.head.appendChild(style);

        // ===== USER NOTIFICATION SYSTEM =====
        let userNotificationCheckInterval;
        const userId = <?= intval($_SESSION['user_id']) ?>;

        // Initialize notification system
        document.addEventListener('DOMContentLoaded', function () {
            initializeUserNotificationSystem();
        });

        function initializeUserNotificationSystem() {
            const bellElement = document.querySelector('.notification-bell');
            if (bellElement) {
                bellElement.addEventListener('click', toggleUserNotificationDropdown);
            }

            // Check for notifications every 4 seconds
            checkUserNotifications();
            userNotificationCheckInterval = setInterval(checkUserNotifications, 4000);

            // Initialize auto-polling system
            initializeUserPolling();

            // Close dropdown when clicking outside
            document.addEventListener('click', function (event) {
                const wrapper = document.querySelector('.notification-wrapper');
                if (wrapper && !wrapper.contains(event.target)) {
                    closeUserNotificationDropdown();
                }
            });
        }

        function toggleUserNotificationDropdown(e) {
            e.preventDefault();
            const dropdown = document.getElementById('notificationDropdown');
            if (dropdown) {
                if (dropdown.style.display === 'none' || !dropdown.style.display) {
                    dropdown.style.display = 'block';
                    loadUserNotifications();
                } else {
                    dropdown.style.display = 'none';
                }
            }
        }

        function closeUserNotificationDropdown() {
            const dropdown = document.getElementById('notificationDropdown');
            if (dropdown) {
                dropdown.style.display = 'none';
            }
        }

        function checkUserNotifications() {
            fetch('notifications.php?action=get_unread_count&user_id=' + userId)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.unread_count > 0) {
                        const badge = document.getElementById('notificationBadge');
                        if (badge) {
                            badge.textContent = data.unread_count;
                            badge.style.display = 'flex';
                        }

                        // When new notifications arrive, refresh document statuses
                        if (typeof pollDocumentStatuses === 'function') {
                            console.log("New notifications detected - refreshing document statuses");
                            pollDocumentStatuses();
                        }
                    } else {
                        const badge = document.getElementById('notificationBadge');
                        if (badge) {
                            badge.style.display = 'none';
                        }
                    }
                })
                .catch(error => console.error('Error checking notifications:', error));
        }

        function loadUserNotifications() {
            const listElement = document.getElementById('notificationList');
            if (!listElement) return;

            fetch('notifications.php?action=get_recent_notifications&user_id=' + userId + '&limit=5')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.notifications.length > 0) {
                        let html = '';
                        let hasDocumentUpdate = false;

                        data.notifications.forEach(notif => {
                            const date = new Date(notif.created_at);
                            const timeAgo = getUserTimeAgo(date);
                            const priorityClass = notif.priority === 'high' ? 'high' : '';

                            html += `
                                <div class="notification-item ${priorityClass}">
                                    <div class="notification-item-title">${escapeUserHtml(notif.title)}</div>
                                    <div class="notification-item-message">${escapeUserHtml(notif.message)}</div>
                                    <div class="notification-item-time">${timeAgo}</div>
                                </div>
                            `;

                            // Check if this is a document-related notification
                            if (notif.type === 'approval' || notif.type === 'alert' || notif.title.includes('Document')) {
                                hasDocumentUpdate = true;
                            }
                        });
                        listElement.innerHTML = html;

                        // If a document notification was received, refresh the document statuses
                        if (hasDocumentUpdate) {
                            console.log("Document notification received - refreshing document statuses");
                            // Trigger document status refresh
                            if (typeof pollDocumentStatuses === 'function') {
                                pollDocumentStatuses();
                            }
                        }
                    } else {
                        listElement.innerHTML = '<div class="notification-empty">No new notifications</div>';
                    }
                })
                .catch(error => {
                    console.error('Error loading notifications:', error);
                    listElement.innerHTML = '<div class="notification-empty">Error loading notifications</div>';
                });
        }

        function getUserTimeAgo(date) {
            const now = new Date();
            const diff = Math.floor((now - date) / 1000);

            if (diff < 60) return 'just now';
            if (diff < 3600) return Math.floor(diff / 60) + ' minutes ago';
            if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
            return Math.floor(diff / 86400) + ' days ago';
        }

        function escapeUserHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

        // Cleanup on page unload
        window.addEventListener('beforeunload', function () {
            if (userNotificationCheckInterval) {
                clearInterval(userNotificationCheckInterval);
            }
        });

        // ===== USER DASHBOARD POLLING MANAGER =====
        const UserPollingManager = {
            pollingIntervals: {},
            lastDataHash: {},
            isPollingEnabled: true,
            isPaused: false,

            // Initialize polling for a specific data source
            initPoll(dataSource, fetchUrl, updateCallback, pollInterval = 4000) {
                if (this.pollingIntervals[dataSource]) {
                    clearInterval(this.pollingIntervals[dataSource]);
                }

                // Initial fetch
                this.fetchData(dataSource, fetchUrl, updateCallback);

                // Set up polling
                this.pollingIntervals[dataSource] = setInterval(() => {
                    if (this.isPollingEnabled && !this.isPaused) {
                        this.fetchData(dataSource, fetchUrl, updateCallback);
                    }
                }, pollInterval);

                console.log(`✓ User auto-polling enabled for: ${dataSource} (${pollInterval}ms)`);
            },

            // Fetch data and detect changes with better error handling
            fetchData(dataSource, fetchUrl, updateCallback) {
                // Add timeout and better error handling to prevent tracking issues
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout

                fetch(fetchUrl, {
                    signal: controller.signal,
                    credentials: 'same-origin',  // Prevent cross-origin tracking issues
                    cache: 'no-store'            // Prevent caching issues
                })
                    .then(response => {
                        clearTimeout(timeoutId);
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        try {
                            const newHash = this.hashData(data);
                            const oldHash = this.lastDataHash[dataSource];

                            // Data changed - update UI and show notification
                            if (oldHash !== newHash) {
                                this.lastDataHash[dataSource] = newHash;
                                updateCallback(data);

                                // Show subtle notification for data updates
                                if (oldHash !== undefined) { // Don't show on first load
                                    this.showUpdateNotification(dataSource);
                                }
                            } else {
                                // Data hasn't changed, but still call callback for initial load
                                if (oldHash === undefined) {
                                    this.lastDataHash[dataSource] = newHash;
                                    updateCallback(data);
                                }
                            }
                        } catch (callbackError) {
                            console.warn(`⚠️ Error in callback for ${dataSource}:`, callbackError);
                            // Don't stop polling due to callback errors
                        }
                    })
                    .catch(error => {
                        clearTimeout(timeoutId);
                        console.warn(`⚠️ Polling error for ${dataSource}:`, error.message);

                        // Handle specific error types
                        if (error.name === 'AbortError') {
                            console.warn(`⏱️ ${dataSource} request timed out`);
                        } else if (error.message.includes('Failed to fetch')) {
                            console.warn(`🌐 Network error for ${dataSource}`);
                        }
                    });
            },

            // Simple hash function for data comparison
            hashData(data) {
                return JSON.stringify(data).split('').reduce((hash, char) => {
                    hash = ((hash << 5) - hash) + char.charCodeAt(0);
                    return hash & hash;
                }, 0);
            },

            // Show subtle update notification
            showUpdateNotification(dataSource) {
                const notification = document.createElement('div');
                notification.className = 'update-notification';
                notification.innerHTML = `
                    <i class="fas fa-sync-alt"></i>
                    ${dataSource} updated
                `;
                notification.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: linear-gradient(135deg, #4CAF50, #45a049);
                    color: white;
                    padding: 12px 20px;
                    border-radius: 25px;
                    box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
                    z-index: 10000;
                    font-size: 14px;
                    font-weight: 500;
                    transform: translateX(400px);
                    transition: transform 0.3s ease;
                    font-family: 'Poppins', sans-serif;
                `;

                document.body.appendChild(notification);

                // Animate in
                setTimeout(() => {
                    notification.style.transform = 'translateX(0)';
                }, 100);

                // Animate out and remove
                setTimeout(() => {
                    notification.style.transform = 'translateX(400px)';
                    setTimeout(() => {
                        if (document.body.contains(notification)) {
                            document.body.removeChild(notification);
                        }
                    }, 300);
                }, 3000);
            },

            // Pause polling during user interactions
            pausePolling() {
                this.isPaused = true;
                console.log('⏸️ User polling paused');
            },

            // Resume polling
            resumePolling() {
                this.isPaused = false;
                console.log('▶️ User polling resumed');
            },

            // Stop all polling
            stopAllPolling() {
                Object.keys(this.pollingIntervals).forEach(dataSource => {
                    clearInterval(this.pollingIntervals[dataSource]);
                    delete this.pollingIntervals[dataSource];
                });
                this.isPollingEnabled = false;
                console.log('🛑 All user polling stopped');
            }
        };

        // Data update callback functions
        function updateCreditPointsDisplay(data) {
            console.log('🔄 Credit points update:', data);
            if (data.success && data.credit_points !== undefined) {
                const creditElements = document.querySelectorAll('.credit-points-value, .points-value, .metric-value');
                creditElements.forEach(element => {
                    if (element.textContent.includes('Points') || element.closest('.credit-points-card')) {
                        const oldValue = parseInt(element.textContent) || 0;
                        const newValue = data.credit_points;

                        if (oldValue !== newValue) {
                            element.textContent = newValue;
                            element.style.animation = 'pulse 0.5s ease-in-out';
                            setTimeout(() => {
                                element.style.animation = '';
                            }, 500);
                        }
                    }
                });
            }
        }

        function updateLoanStatusesDisplay(data) {
            console.log('🔄 Loan statuses update:', data);
            if (data.success && data.activeLoan) {
                const loan = data.activeLoan;

                // Update active loan banner status badge
                const statusBadge = document.getElementById('active-loan-status-badge');
                if (statusBadge && statusBadge.textContent.trim() !== loan.status) {
                    statusBadge.textContent = loan.status;
                    statusBadge.className = `status-badge badge-${loan.status.toLowerCase()}`;
                    statusBadge.style.animation = 'statusUpdate 0.6s ease-in-out';
                    setTimeout(() => {
                        statusBadge.style.animation = '';
                    }, 600);
                    console.log(`✅ Updated active loan status to: ${loan.status}`);
                }

                // Update pre-approval status
                const preApprovalElem = document.getElementById('active-loan-pre-approval');
                if (preApprovalElem && preApprovalElem.textContent.trim() !== (loan.pre_approval_status || 'Pending')) {
                    preApprovalElem.textContent = loan.pre_approval_status || 'Pending';
                    preApprovalElem.style.animation = 'statusUpdate 0.6s ease-in-out';
                    setTimeout(() => {
                        preApprovalElem.style.animation = '';
                    }, 600);
                    console.log(`✅ Updated pre-approval status to: ${loan.pre_approval_status}`);
                }

                // Update credit investigation status
                const creditElem = document.getElementById('active-loan-credit-investigation');
                if (creditElem && creditElem.textContent.trim() !== (loan.credit_investigation_status || 'Pending')) {
                    creditElem.textContent = loan.credit_investigation_status || 'Pending';
                    creditElem.style.animation = 'statusUpdate 0.6s ease-in-out';
                    setTimeout(() => {
                        creditElem.style.animation = '';
                    }, 600);
                    console.log(`✅ Updated credit investigation status to: ${loan.credit_investigation_status}`);
                }

                // Update loan progress tracker steps
                const progressBar = document.querySelector('.loan-progress-tracker .progress-bar');
                if (progressBar) {
                    // Determine the current step based on status
                    let currentStep = 0;
                    if (loan.status === 'Closed') {
                        currentStep = 4;
                    } else if (loan.status === 'Active') {
                        currentStep = 3;
                    } else if (loan.credit_investigation_status === 'Completed' || loan.status === 'Approved') {
                        currentStep = 2;
                    } else if (loan.pre_approval_status === 'Approved') {
                        currentStep = 1;
                    } else {
                        currentStep = 0;
                    }

                    // Update progress steps
                    const steps = progressBar.querySelectorAll('.progress-step');
                    steps.forEach((step, index) => {
                        const shouldBeDone = index < currentStep;
                        const shouldBeCurrent = index === currentStep;

                        if (shouldBeDone && !step.classList.contains('done')) {
                            step.classList.add('done');
                            step.style.animation = 'stepComplete 0.5s ease-in-out';
                            console.log(`✅ Marked step ${index} as done`);
                        } else if (!shouldBeDone && step.classList.contains('done')) {
                            step.classList.remove('done');
                            console.log(`↩️ Unmarked step ${index}`);
                        }

                        if (shouldBeCurrent && !step.classList.contains('current')) {
                            step.classList.add('current');
                            if (step.textContent.includes('Active')) {
                                step.classList.add('active-ongoing');
                            }
                            console.log(`✅ Marked step ${index} as current`);
                        } else if (!shouldBeCurrent && step.classList.contains('current')) {
                            step.classList.remove('current');
                            step.classList.remove('active-ongoing');
                            console.log(`↩️ Unmarked step ${index} as current`);
                        }
                    });
                }

                // Update final loan amount
                const finalAmountElem = document.getElementById('active-loan-final-amount');
                if (finalAmountElem && loan.final_loan_amount) {
                    const formattedFinalAmount = '₱' + parseFloat(loan.final_loan_amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    if (finalAmountElem.textContent.trim() !== formattedFinalAmount) {
                        finalAmountElem.textContent = formattedFinalAmount;
                        finalAmountElem.style.animation = 'statusUpdate 0.6s ease-in-out';
                        setTimeout(() => {
                            finalAmountElem.style.animation = '';
                        }, 600);
                        console.log(`✅ Updated final loan amount to: ${formattedFinalAmount}`);
                    }
                }

                // Update term length
                const termLengthElem = document.getElementById('active-loan-term-length');
                if (termLengthElem && loan.term_length) {
                    const termText = loan.term_length + ' months';
                    if (termLengthElem.textContent.trim() !== termText) {
                        termLengthElem.textContent = termText;
                        termLengthElem.style.animation = 'statusUpdate 0.6s ease-in-out';
                        setTimeout(() => {
                            termLengthElem.style.animation = '';
                        }, 600);
                        console.log(`✅ Updated term length to: ${termText}`);
                    }
                }

                // Update payment metrics if available
                if (loan.loans_loan_id) {
                    const totalPaidElem = document.getElementById('active-loan-total-paid');
                    if (totalPaidElem) {
                        const formattedAmount = '₱' + parseFloat(loan.total_paid).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        if (totalPaidElem.textContent.trim() !== formattedAmount) {
                            totalPaidElem.textContent = formattedAmount;
                            totalPaidElem.style.animation = 'statusUpdate 0.6s ease-in-out';
                            setTimeout(() => {
                                totalPaidElem.style.animation = '';
                            }, 600);
                            console.log(`✅ Updated total paid to: ${formattedAmount}`);
                        }
                    }

                    const remainingElem = document.getElementById('active-loan-remaining-balance');
                    if (remainingElem) {
                        const formattedRemaining = '₱' + parseFloat(loan.remaining_balance).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        if (remainingElem.textContent.trim() !== formattedRemaining) {
                            remainingElem.textContent = formattedRemaining;
                            remainingElem.style.animation = 'statusUpdate 0.6s ease-in-out';
                            setTimeout(() => {
                                remainingElem.style.animation = '';
                            }, 600);
                            console.log(`✅ Updated remaining balance to: ${formattedRemaining}`);
                        }
                    }

                    const progressPercentElem = document.getElementById('active-loan-progress-percent');
                    if (progressPercentElem) {
                        const progressText = loan.progress + '%';
                        if (progressPercentElem.textContent.trim() !== progressText) {
                            progressPercentElem.textContent = progressText;
                            progressPercentElem.style.animation = 'statusUpdate 0.6s ease-in-out';
                            setTimeout(() => {
                                progressPercentElem.style.animation = '';
                            }, 600);
                            console.log(`✅ Updated progress to: ${progressText}`);
                        }
                    }

                    const progressBarFill = document.getElementById('active-loan-progress-bar');
                    if (progressBarFill) {
                        const newWidth = Math.min(loan.progress, 100);
                        const currentWidth = parseFloat(progressBarFill.style.width);
                        if (currentWidth !== newWidth) {
                            progressBarFill.style.width = newWidth + '%';
                            progressBarFill.style.transition = 'width 0.6s ease-in-out';
                            console.log(`✅ Updated progress bar to: ${newWidth}%`);
                        }
                    }
                }
            }
        }

        function updateDocumentStatusesDisplay(data) {
            console.log('🔄 Document statuses update:', data);
            if (data.success && data.documents) {
                // Store in global cache for use by renderDocuments
                documentsCache = data.documents;

                // Update the document status grid with card layout
                const statusGrid = document.getElementById('documentStatusGrid');
                if (statusGrid) {
                    statusGrid.innerHTML = '';

                    data.documents.forEach(doc => {
                        const card = document.createElement('div');
                        card.className = 'document-card';
                        card.setAttribute('data-doc-id', doc.document_id);

                        const statusClass = doc.status.toLowerCase();
                        console.log(`📄 Polling - Document: ${doc.document_name}, Status: ${doc.status}, StatusClass: ${statusClass}`);
                        const statusIcon = statusClass === 'approved' ? '✅' :
                            statusClass === 'rejected' ? '❌' : '⏳';

                        card.innerHTML = `
                            <div class="document-card-header">
                                <div class="document-name">${doc.document_name}</div>
                                <span class="status-badge status-${statusClass}">${doc.status}</span>
                            </div>
                            <div class="document-card-body">
                                <div class="document-info">
                                    <span class="info-label">Status:</span>
                                    <span class="info-value ${statusClass}">${statusIcon} ${doc.status}</span>
                                </div>
                                <div class="document-info">
                                    <span class="info-label">Last Updated:</span>
                                    <span class="info-value">${doc.status_updated_at ? new Date(doc.status_updated_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'N/A'}</span>
                                </div>
                                ${doc.rejection_notes ? `<div class="document-info"><span class="info-label">Reason:</span><span class="info-value rejection">${doc.rejection_notes}</span></div>` : ''}
                            </div>
                            <div class="document-card-actions">
                                <button class="btn-resubmit" data-doc-id="${doc.document_id}" data-doc-name="${doc.document_name}"><i class="fas fa-upload"></i> Resubmit</button>
                            </div>
                        `;

                        statusGrid.appendChild(card);

                        // Set actions visibility based on status
                        const actionsDiv = card.querySelector('.document-card-actions');
                        if (statusClass !== 'rejected') {
                            actionsDiv.style.display = 'none';
                        }
                    });

                    // Attach event listeners to resubmit buttons
                    const resubmitButtons = statusGrid.querySelectorAll('.btn-resubmit');
                    resubmitButtons.forEach(btn => {
                        btn.addEventListener('click', function () {
                            const docId = this.getAttribute('data-doc-id');
                            const docName = this.getAttribute('data-doc-name');
                            showResubmitModal(docId, docName);
                        });
                    });
                }
            }
        }

        // Initialize user dashboard auto-polling
        function initializeUserPolling() {
            console.log('🚀 Initializing User Dashboard Auto-Polling...');

            // Poll credit points every 5 seconds
            UserPollingManager.initPoll(
                'Credit Points',
                'user_dashboard.php?action=get_credit_points',
                updateCreditPointsDisplay,
                5000
            );

            // Poll active loan details every 4 seconds
            UserPollingManager.initPoll(
                'Active Loan',
                'user_dashboard.php?action=get_active_loan',
                updateLoanStatusesDisplay,
                4000
            );

            // Poll loan statuses every 4 seconds
            UserPollingManager.initPoll(
                'Loan Status',
                'user_dashboard.php?action=get_loan_statuses',
                updateLoanStatusesDisplay,
                4000
            );

            // Poll document statuses every 3 seconds
            UserPollingManager.initPoll(
                'Documents',
                'user_dashboard.php?action=get_document_statuses',
                updateDocumentStatusesDisplay,
                3000
            );

            console.log('✅ User Dashboard Real-time Polling Started - 3-5 second intervals');

            // Add CSS animations for status updates
            const style = document.createElement('style');
            style.textContent = `
                @keyframes pulse {
                    0% { transform: scale(1); }
                    50% { transform: scale(1.1); }
                    100% { transform: scale(1); }
                }
                @keyframes statusUpdate {
                    0% { background-color: #4CAF50; transform: scale(1); }
                    50% { background-color: #66BB6A; transform: scale(1.05); }
                    100% { background-color: initial; transform: scale(1); }
                }
            `;
            document.head.appendChild(style);
        }

        // Pause polling when user is actively interacting
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                console.log('📱 Page hidden - reducing polling frequency');
                // Don't stop completely, just slow down
            } else {
                console.log('📱 Page visible - resuming normal polling');
                if (typeof UserPollingManager !== 'undefined') {
                    UserPollingManager.resumePolling();
                }
            }
        });

        // Pause polling during modal interactions or form submissions
        document.addEventListener('click', function (e) {
            if (e.target.closest('.modal, form[method="post"]')) {
                if (typeof UserPollingManager !== 'undefined') {
                    UserPollingManager.pausePolling();
                    // Resume after 3 seconds
                    setTimeout(() => {
                        UserPollingManager.resumePolling();
                    }, 3000);
                }
            }
        });
    </script>
    <script src="JAVASCRIPT/user_dashboard.js"></script>
    <script src="JAVASCRIPT/user_history_activity.js"></script>
    <script src="JAVASCRIPT/Real-Time.js"></script>
</body>

</html>