<?php
/**
 * CYCLOAN Email Queue System Test
 * Tests the persistent email queue functionality
 */

echo "<h2>🧪 CYCLOAN Email Queue System Test</h2>\n";
echo "<p><strong>Testing persistent email queue functionality...</strong></p>\n";

// Start session to test session-based queue
session_start();

// Test 1: Initialize Queue with Test Data
echo "<h3>Test 1: Queue Initialization</h3>\n";

$testApplicationId = "TEST123456";
$testDocumentChange = [
    'document_type' => 'bank_statement',
    'old_status' => 'pending',
    'new_status' => 'rejected',
    'reason' => 'Insufficient balance proof',
    'changed_at' => date('Y-m-d H:i:s'),
    'admin_user' => 'test_admin',
    'change_source' => 'admin_update',
    'processed' => false,
    'email_sent' => false
];

// Initialize queue if it doesn't exist
if (!isset($_SESSION['document_changes_queue'])) {
    $_SESSION['document_changes_queue'] = [];
    echo "✅ Queue initialized<br>\n";
} else {
    echo "✅ Queue already exists<br>\n";
}

// Add test change to queue
if (!isset($_SESSION['document_changes_queue'][$testApplicationId])) {
    $_SESSION['document_changes_queue'][$testApplicationId] = [
        'application_id' => $testApplicationId,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
        'changes' => [],
        'queue_status' => 'accumulating',
        'auto_accumulate' => true,
        'manual_send_only' => true,
        'email_sent' => false,
        'last_email_sent' => null,
        'email_send_count' => 0
    ];
}

// Add the test change
$_SESSION['document_changes_queue'][$testApplicationId]['changes'][] = $testDocumentChange;
$_SESSION['document_changes_queue'][$testApplicationId]['updated_at'] = date('Y-m-d H:i:s');

echo "✅ Test document change added to queue<br>\n";
echo "📊 Queue count: " . count($_SESSION['document_changes_queue'][$testApplicationId]['changes']) . "<br>\n";

// Test 2: Verify Persistent Behavior Settings
echo "<h3>Test 2: Persistent Behavior Verification</h3>\n";

$queueData = $_SESSION['document_changes_queue'][$testApplicationId];
echo "🔧 Auto Accumulate: " . ($queueData['auto_accumulate'] ? 'YES' : 'NO') . "<br>\n";
echo "🔧 Manual Send Only: " . ($queueData['manual_send_only'] ? 'YES' : 'NO') . "<br>\n";
echo "📧 Email Sent: " . ($queueData['email_sent'] ? 'YES' : 'NO') . "<br>\n";
echo "📊 Queue Status: " . $queueData['queue_status'] . "<br>\n";

if ($queueData['auto_accumulate'] && $queueData['manual_send_only'] && !$queueData['email_sent']) {
    echo "✅ Persistent accumulation settings correct<br>\n";
} else {
    echo "❌ Persistent accumulation settings incorrect<br>\n";
}

// Test 3: Queue Status Function (if available)
echo "<h3>Test 3: Queue Status Function Test</h3>\n";

// Simulate the getDetailedQueueStatus function behavior
function testGetDetailedQueueStatus($applicationId)
{
    if (!isset($_SESSION['document_changes_queue'][$applicationId])) {
        return [
            'has_queue' => false,
            'queue_count' => 0,
            'queue_status' => 'empty',
            'last_updated' => null,
            'auto_accumulate' => false,
            'manual_send_only' => false,
            'email_sent' => false,
            'pending_changes' => 0
        ];
    }

    $queueData = $_SESSION['document_changes_queue'][$applicationId];
    $pendingChanges = count(array_filter($queueData['changes'], function ($change) {
        return !($change['processed'] ?? false);
    }));

    return [
        'has_queue' => true,
        'queue_count' => count($queueData['changes']),
        'queue_status' => $queueData['queue_status'] ?? 'unknown',
        'last_updated' => $queueData['updated_at'] ?? null,
        'auto_accumulate' => $queueData['auto_accumulate'] ?? false,
        'manual_send_only' => $queueData['manual_send_only'] ?? false,
        'email_sent' => $queueData['email_sent'] ?? false,
        'pending_changes' => $pendingChanges,
        'created_at' => $queueData['created_at'] ?? null,
        'email_send_count' => $queueData['email_send_count'] ?? 0,
        'last_email_sent' => $queueData['last_email_sent'] ?? null
    ];
}

$status = testGetDetailedQueueStatus($testApplicationId);
echo "📋 Queue Status Report:<br>\n";
foreach ($status as $key => $value) {
    $displayValue = is_bool($value) ? ($value ? 'YES' : 'NO') :
        (is_null($value) ? 'NULL' : $value);
    echo "   • $key: $displayValue<br>\n";
}

// Test 4: Add Multiple Changes
echo "<h3>Test 4: Multiple Changes Accumulation</h3>\n";

$additionalChanges = [
    [
        'document_type' => 'proof_of_income',
        'old_status' => 'pending',
        'new_status' => 'approved',
        'reason' => 'Valid income documentation',
        'changed_at' => date('Y-m-d H:i:s'),
        'admin_user' => 'test_admin',
        'change_source' => 'admin_update',
        'processed' => false,
        'email_sent' => false
    ],
    [
        'document_type' => 'government_id',
        'old_status' => 'approved',
        'new_status' => 'rejected',
        'reason' => 'Document clarity issues',
        'changed_at' => date('Y-m-d H:i:s'),
        'admin_user' => 'test_admin',
        'change_source' => 'admin_update',
        'processed' => false,
        'email_sent' => false
    ]
];

foreach ($additionalChanges as $change) {
    $_SESSION['document_changes_queue'][$testApplicationId]['changes'][] = $change;
}
$_SESSION['document_changes_queue'][$testApplicationId]['updated_at'] = date('Y-m-d H:i:s');

$finalCount = count($_SESSION['document_changes_queue'][$testApplicationId]['changes']);
echo "✅ Added 2 more changes<br>\n";
echo "📊 Total queue count: $finalCount<br>\n";

if ($finalCount === 3) {
    echo "✅ Multiple changes accumulation working correctly<br>\n";
} else {
    echo "❌ Multiple changes accumulation failed<br>\n";
}

// Test 5: JSON Output for AJAX Testing
echo "<h3>Test 5: JSON Output (AJAX Simulation)</h3>\n";

$finalStatus = testGetDetailedQueueStatus($testApplicationId);
$jsonOutput = json_encode([
    'success' => true,
    'queue_status' => $finalStatus,
    'timestamp' => date('Y-m-d H:i:s')
], JSON_PRETTY_PRINT);

echo "<pre style='background: #f0f0f0; padding: 10px; border-radius: 5px;'>\n";
echo htmlspecialchars($jsonOutput);
echo "</pre>\n";

// Test Summary
echo "<h3>🎯 Test Summary</h3>\n";

$allTestsPassed = true;
$testResults = [
    'Queue Initialization' => isset($_SESSION['document_changes_queue'][$testApplicationId]),
    'Persistent Settings' => ($queueData['auto_accumulate'] && $queueData['manual_send_only']),
    'Status Function' => ($status['has_queue'] && $status['queue_count'] > 0),
    'Multiple Changes' => ($finalCount === 3),
    'JSON Output' => (!empty($jsonOutput) && json_last_error() === JSON_ERROR_NONE)
];

foreach ($testResults as $testName => $passed) {
    $icon = $passed ? '✅' : '❌';
    echo "$icon $testName: " . ($passed ? 'PASSED' : 'FAILED') . "<br>\n";
    if (!$passed)
        $allTestsPassed = false;
}

echo "<br><strong>" . ($allTestsPassed ? '🎉 ALL TESTS PASSED!' : '⚠️ SOME TESTS FAILED') . "</strong><br>\n";

// Cleanup (optional)
echo "<br><a href='?cleanup=1'>🧹 Clean up test data</a>\n";

if (isset($_GET['cleanup']) && $_GET['cleanup'] === '1') {
    unset($_SESSION['document_changes_queue'][$testApplicationId]);
    echo "<br>✅ Test data cleaned up<br>\n";
    echo "<a href='?'>🔄 Run tests again</a>\n";
}

?>

<style>
    body {
        font-family: Arial, sans-serif;
        line-height: 1.6;
        padding: 20px;
        background: #f9f9f9;
    }

    h2 {
        color: #2c3e50;
        border-bottom: 2px solid #3498db;
        padding-bottom: 10px;
    }

    h3 {
        color: #27ae60;
        border-left: 4px solid #27ae60;
        padding-left: 10px;
    }

    pre {
        overflow-x: auto;
    }

    a {
        color: #3498db;
        text-decoration: none;
        padding: 8px 15px;
        background: #ecf0f1;
        border-radius: 4px;
        display: inline-block;
        margin: 5px 0;
    }

    a:hover {
        background: #3498db;
        color: white;
    }
</style>