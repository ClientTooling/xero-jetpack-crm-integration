<?php
/**
 * Script to create a zip file of the Xero Jetpack CRM Integration plugin
 */

// Check if we're in the right directory
if (!file_exists('xero-jetpack-crm-integration.php')) {
    die("Error: Please run this script from the plugin directory.\n");
}

echo "Creating Xero Jetpack CRM Integration plugin zip file...\n";

// Create zip file
$zip = new ZipArchive();
$zip_filename = 'xero-jetpack-crm-integration.zip';

if ($zip->open($zip_filename, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    die("Cannot create zip file: $zip_filename\n");
}

// Files to include in the zip
$files_to_include = [
    'xero-jetpack-crm-integration.php',
    'composer.json',
    'README.md',
    'admin/css/admin.css',
    'admin/js/admin.js',
];

// Add files to zip
foreach ($files_to_include as $file) {
    if (file_exists($file)) {
        $zip->addFile($file, $file);
        echo "Added: $file\n";
    } else {
        echo "Warning: $file not found\n";
    }
}

// Close zip
$zip->close();

echo "\nZip file created successfully: $zip_filename\n";
echo "File size: " . formatBytes(filesize($zip_filename)) . "\n";
echo "\nYou can now upload this zip file to WordPress!\n";

function formatBytes($bytes, $decimals = 2) {
    $size = array('B','KB','MB','GB','TB','PB','EB','ZB','YB');
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$size[$factor];
}
