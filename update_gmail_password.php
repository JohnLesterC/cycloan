#!/usr/bin/env php
<?php
/**
 * Gmail App Password Updater
 * 
 * This script helps you update the Gmail app password across all CYCLOAN files
 * Run from terminal: php update_gmail_password.php
 * 
 * Usage:
 *   php update_gmail_password.php [old_password] [new_password]
 * 
 * Or run interactively if no arguments provided
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║        CYCLOAN - Gmail App Password Updater                   ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// Files that contain the Gmail password
$files_to_update = [
    'admin1_dashboard.php' => [1102, 1557, 2377, 2490],
    'process_registration.php' => null, // Will search all lines
];

$old_password = $argv[1] ?? null;
$new_password = $argv[2] ?? null;

// Interactive mode if no arguments
if (!$old_password || !$new_password) {
    echo "📧 Interactive Mode\n";
    echo "──────────────────────────────────────────────────────────────\n\n";

    echo "Current Gmail app password: hbfh ukgh tmzw nqbq\n\n";

    $old_password = readline("Enter old password to replace: ");
    $new_password = readline("Enter new password: ");

    echo "\nConfirm password replacement:\n";
    echo "  Old: $old_password\n";
    echo "  New: $new_password\n";

    $confirm = readline("\nProceed? (yes/no): ");
    if (strtolower($confirm) !== 'yes') {
        echo "\n❌ Operation cancelled.\n\n";
        exit(0);
    }
}

echo "\n🔍 Searching for files...\n";
echo "──────────────────────────────────────────────────────────────\n\n";

$project_root = __DIR__;
$total_replacements = 0;
$files_modified = [];

foreach ($files_to_update as $filename => $lines) {
    $filepath = "$project_root/$filename";

    if (!file_exists($filepath)) {
        echo "⚠️  $filename - NOT FOUND\n";
        continue;
    }

    // Read the file
    $content = file_get_contents($filepath);
    $original_content = $content;

    // Count and replace
    $occurrences = substr_count($content, $old_password);

    if ($occurrences > 0) {
        $content = str_replace($old_password, $new_password, $content);
        $total_replacements += $occurrences;

        // Write back
        if (file_put_contents($filepath, $content)) {
            echo "✅ $filename - $occurrences replacement(s)\n";
            $files_modified[] = $filename;
        } else {
            echo "❌ $filename - ERROR writing file (permission denied?)\n";
        }
    } else {
        echo "⏭️  $filename - no matches found\n";
    }
}

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                        SUMMARY                                 ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

echo "Total replacements made: $total_replacements\n";
echo "Files modified: " . count($files_modified) . "\n";

if (count($files_modified) > 0) {
    echo "\nModified files:\n";
    foreach ($files_modified as $file) {
        echo "  • $file\n";
    }

    echo "\n";
    echo "✅ SUCCESS! Gmail app password has been updated.\n\n";
    echo "📋 NEXT STEPS:\n";
    echo "   1. Upload modified files to your server\n";
    echo "   2. Run email_debug_test.php to verify\n";
    echo "   3. Test credit investigation email sending\n";
    echo "   4. Check debug_log.txt for confirmations\n";
    echo "\n";
} else {
    echo "\n❌ No files were modified.\n";
    echo "⚠️  Please check that the old password is correct.\n\n";
}

echo "For more information, see: EMAIL_APP_PASSWORD_RENEWAL_GUIDE.md\n\n";
?>