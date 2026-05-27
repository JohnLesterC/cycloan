<?php
require_once 'CYCLOAN_db.php';

echo "Step 1: Checking current foreign keys...\n";

$fk_check = $conn->query("
    SELECT CONSTRAINT_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_NAME = 'user_notifications' 
    AND CONSTRAINT_NAME LIKE '%user%'
    AND REFERENCED_TABLE_NAME IS NOT NULL
");

if ($fk_check && $fk_check->num_rows > 0) {
    while ($row = $fk_check->fetch_assoc()) {
        $constraint = $row['CONSTRAINT_NAME'];
        echo "Found constraint: $constraint\n";

        echo "Step 2: Dropping foreign key constraint...\n";
        $drop_sql = "ALTER TABLE user_notifications DROP FOREIGN KEY $constraint";

        if ($conn->query($drop_sql)) {
            echo "✓ Successfully dropped constraint: $constraint\n";
        } else {
            echo "✗ Error dropping constraint: " . $conn->error . "\n";
        }
    }
} else {
    echo "No user foreign key constraints found.\n";
}

echo "\nStep 3: Verifying the constraint is gone...\n";

$verify = $conn->query("
    SELECT CONSTRAINT_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_NAME = 'user_notifications' 
    AND CONSTRAINT_NAME LIKE '%fk_user_notifications_user%'
");

if ($verify && $verify->num_rows === 0) {
    echo "✓ Foreign key constraint successfully removed!\n";
} else {
    echo "✗ Constraint still exists.\n";
}

echo "\n\nNow testing notification creation for admin2...\n";

// Test creating a notification for admin2
require_once 'NotificationManager.php';
$nm = new NotificationManager($conn);

$result = $nm->createNotification(
    9,
    'document',
    'Test Notification After FK Removal',
    'This should work now',
    'high'
);

echo $result ? "✓ Notification created successfully!\n" : "✗ Failed to create notification\n";

// Verify
if ($result) {
    $check = $conn->query("SELECT * FROM user_notifications WHERE user_id = 9 ORDER BY created_at DESC LIMIT 1");
    if ($check && $check->num_rows > 0) {
        $row = $check->fetch_assoc();
        echo "\n✓ Verified in database:\n";
        echo "  Notification ID: " . $row['notification_id'] . "\n";
        echo "  User ID: " . $row['user_id'] . "\n";
        echo "  User Type: " . $row['user_type'] . "\n";
        echo "  Title: " . $row['title'] . "\n";
    }
}
?>