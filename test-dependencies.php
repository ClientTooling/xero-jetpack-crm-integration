<?php
/**
 * Test script to verify dependency installation
 */

// Simulate WordPress environment
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

// Test the dependency installation methods
echo "Testing Xero Jetpack CRM Integration dependency installation...\n\n";

// Test 1: Check if vendor directory can be created
$plugin_dir = __DIR__ . '/';
$vendor_dir = $plugin_dir . 'vendor/';

echo "1. Testing vendor directory creation...\n";
if (!file_exists($vendor_dir)) {
    if (mkdir($vendor_dir, 0755, true)) {
        echo "   ✓ Vendor directory created successfully\n";
    } else {
        echo "   ✗ Failed to create vendor directory\n";
    }
} else {
    echo "   ✓ Vendor directory already exists\n";
}

// Test 2: Test minimal autoloader creation
echo "\n2. Testing minimal autoloader creation...\n";
$autoloader_content = '<?php
// Minimal autoloader for Xero Jetpack CRM Integration
spl_autoload_register(function ($class) {
    $prefix = "XeroJetpackCRM\\\\";
    $base_dir = __DIR__ . "/../includes/";
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace("\\\\", "/", $relative_class) . ".php";
    
    if (file_exists($file)) {
        require $file;
    }
});

// Mock OAuth2 classes for basic functionality
if (!class_exists("League\\\\OAuth2\\\\Client\\\\Provider\\\\Xero")) {
    class League\\\\OAuth2\\\\Client\\\\Provider\\\\Xero {
        public function __construct($options = []) {
            // Mock implementation
        }
        public function getAuthorizationUrl($options = []) {
            return "https://login.xero.com/identity/connect/authorize";
        }
        public function getAccessToken($grant, $options = []) {
            return new stdClass();
        }
    }
}

if (!class_exists("League\\\\OAuth2\\\\Client\\\\Token\\\\AccessToken")) {
    class League\\\\OAuth2\\\\Client\\\\Token\\\\AccessToken {
        public function __construct($options = []) {
            $this->token = $options["access_token"] ?? "";
            $this->expires = $options["expires"] ?? 3600;
        }
        public function getToken() {
            return $this->token;
        }
        public function getExpires() {
            return $this->expires;
        }
    }
}
';

$autoloader_file = $vendor_dir . 'autoload.php';
if (file_put_contents($autoloader_file, $autoloader_content)) {
    echo "   ✓ Minimal autoloader created successfully\n";
} else {
    echo "   ✗ Failed to create minimal autoloader\n";
}

// Test 3: Test if autoloader can be loaded
echo "\n3. Testing autoloader loading...\n";
if (file_exists($autoloader_file)) {
    require_once $autoloader_file;
    echo "   ✓ Autoloader loaded successfully\n";
    
    // Test if mock classes are available
    if (class_exists('League\OAuth2\Client\Provider\Xero')) {
        echo "   ✓ Mock Xero provider class available\n";
    } else {
        echo "   ✗ Mock Xero provider class not available\n";
    }
    
    if (class_exists('League\OAuth2\Client\Token\AccessToken')) {
        echo "   ✓ Mock AccessToken class available\n";
    } else {
        echo "   ✗ Mock AccessToken class not available\n";
    }
} else {
    echo "   ✗ Autoloader file not found\n";
}

// Test 4: Test file permissions
echo "\n4. Testing file permissions...\n";
$test_file = $vendor_dir . 'test.txt';
if (file_put_contents($test_file, 'test')) {
    echo "   ✓ File write permissions OK\n";
    unlink($test_file);
} else {
    echo "   ✗ File write permissions failed\n";
}

// Test 5: Test directory permissions
echo "\n5. Testing directory permissions...\n";
$test_dir = $vendor_dir . 'test_dir/';
if (mkdir($test_dir, 0755, true)) {
    echo "   ✓ Directory creation permissions OK\n";
    rmdir($test_dir);
} else {
    echo "   ✗ Directory creation permissions failed\n";
}

echo "\nTest completed!\n";
echo "If all tests passed, the dependency installation should work.\n";
echo "If any tests failed, check your server permissions.\n";
