<?php
require_once 'CYCLOAN_db.php';

// Debug: Try direct INSERT to see the exact error
$user_id = 9;
$user_type = 'admin2';
$type_id = 8; // document type
$title = 'Test Direct Insert';
$message = 'Testing direct insert';
$short_message = 'Test';
$priority = 'high';

echo "Attempting direct INSERT...\n\n";

$sql = "
    INSERT INTO user_notifications (user_id, user_type, type_id, title, message, short_message, priority, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
";

echo "SQL: $sql\n\n";
echo "Parameters:\n";
echo "  user_id: $user_id (int)\n";
echo "  user_type: $user_type (string)\n";
echo "  type_id: $type_id (int)\n";
echo "  title: $title (string)\n";
echo "  message: $message (string)\n";
echo "  short_message: $short_message (string)\n";
echo "  priority: $priority (string)\n\n";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo "ERROR preparing statement: " . $conn->error . "\n";
    exit;
}

echo "Statement prepared successfully.\n";

$result = $stmt->bind_param("isissss", $user_id, $user_type, $type_id, $title, $message, $short_message, $priority);
if (!$result) {
    echo "ERROR in bind_param: " . $stmt->error . "\n";
    exit;
}

echo "Parameters bound successfully.\n";

$result = $stmt->execute();
if (!$result) {
    echo "ERROR executing statement: " . $stmt->error . "\n";
    echo "Connection error: " . $conn->error . "\n";
    exit;
}

echo "✓ Insert successful! Notification ID: " . $conn->insert_id . "\n";

// Verify
$verify = $conn->query("SELECT * FROM user_notifications WHERE notification_id = " . $conn->insert_id);
if ($verify && $verify->num_rows > 0) {
    $row = $verify->fetch_assoc();
    echo "\n✓ Record verified in database:\n";
    echo json_encode($row, JSON_PRETTY_PRINT);
}
?>