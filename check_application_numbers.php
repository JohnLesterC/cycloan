<?php
require_once 'CYCLOAN_db.php';

echo "Checking application numbers...\n\n";

// Get recent applications
$result = $conn->query("
    SELECT 
        a.id,
        a.application_number,
        a.user_id,
        u.email,
        a.created_at
    FROM applications a
    LEFT JOIN users1 u ON a.user_id = u.id
    ORDER BY a.created_at DESC
    LIMIT 20
");

echo "RECENT APPLICATIONS:\n";
echo "====================\n";
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "App ID: " . $row['id'] . " - App Number: " . ($row['application_number'] ?: 'NULL/0') . " - User: " . $row['email'] . " - Created: " . $row['created_at'] . "\n";
    }
} else {
    echo "No applications found.\n";
}

echo "\n\nCHECKING APPLICATION NUMBER GENERATION:\n";
echo "========================================\n";

// Check the documents/pending_records that reference applications
$docs = $conn->query("
    SELECT 
        d.id,
        d.application_id,
        a.application_number,
        d.document_name,
        d.status,
        d.status_updated_at
    FROM documents d
    LEFT JOIN applications a ON d.application_id = a.id
    ORDER BY d.status_updated_at DESC
    LIMIT 10
");

if ($docs && $docs->num_rows > 0) {
    echo "RECENT DOCUMENTS:\n";
    while ($row = $docs->fetch_assoc()) {
        echo "Doc ID: " . $row['id'] . " - App ID: " . $row['application_id'] . " - App Number: " . ($row['application_number'] ?: '0/NULL') . " - Doc: " . $row['document_name'] . " - Status: " . $row['status'] . "\n";
    }
}

echo "\n\nNOTIFICATION MESSAGE CHECK:\n";
echo "=============================\n";

// Check what's in the notifications
$notifs = $conn->query("
    SELECT 
        un.notification_id,
        un.user_id,
        un.user_type,
        un.title,
        un.message,
        un.created_at
    FROM user_notifications
    WHERE un.title LIKE '%Document Resubmitted%'
    ORDER BY un.created_at DESC
    LIMIT 5
");

if ($notifs && $notifs->num_rows > 0) {
    while ($row = $notifs->fetch_assoc()) {
        echo "\nNotification ID: " . $row['notification_id'] . "\n";
        echo "User ID: " . $row['user_id'] . " (Type: " . $row['user_type'] . ")\n";
        echo "Title: " . $row['title'] . "\n";
        echo "Message: " . $row['message'] . "\n";
        echo "Created: " . $row['created_at'] . "\n";
    }
}
?>