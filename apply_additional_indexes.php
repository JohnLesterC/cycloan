<?php
require_once 'CYCLOAN_db.php';

echo "🔧 Applying additional performance indexes...\n\n";

$queries = [
    "ALTER TABLE `document_types` ADD INDEX IF NOT EXISTS `idx_document_type_id` (`document_type_id`)",
    "ALTER TABLE `users1` ADD INDEX IF NOT EXISTS `idx_user_id` (`id`)",
    "ALTER TABLE `loan_types` ADD INDEX IF NOT EXISTS `idx_loan_type_id` (`loan_type_id`)",
    "ALTER TABLE `financial_info` ADD INDEX IF NOT EXISTS `idx_user_id_fi` (`user_id`)",
];

foreach ($queries as $idx => $query) {
    echo ($idx + 1) . ". Executing: " . substr($query, 0, 50) . "... ";
    if ($conn->query($query)) {
        echo "✅\n";
    } else {
        if (stripos($conn->error, 'duplicate') !== false) {
            echo "ℹ️ Already exists\n";
        } else {
            echo "❌ Error: " . $conn->error . "\n";
        }
    }
}

echo "\n✨ Additional indexes applied!\n";
$conn->close();
?>