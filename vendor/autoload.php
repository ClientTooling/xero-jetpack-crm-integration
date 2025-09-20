<?php
// Minimal autoloader for Xero Jetpack CRM Integration
spl_autoload_register(function ($class) {
    $prefix = "XeroJetpackCRM\\";
    $base_dir = __DIR__ . "/../includes/";
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace("\\", "/", $relative_class) . ".php";
    
    if (file_exists($file)) {
        require $file;
    }
});

// Mock OAuth2 classes for basic functionality
if (!class_exists("League\\OAuth2\\Client\\Provider\\Xero")) {
    class League_OAuth2_Client_Provider_Xero {
        private $options;
        
        public function __construct($options = []) {
            $this->options = $options;
        }
        
        public function getAuthorizationUrl($options = []) {
            $params = array_merge([
                "response_type" => "code",
                "client_id" => $this->options["clientId"] ?? "",
                "redirect_uri" => $this->options["redirectUri"] ?? "",
                "scope" => "openid profile email accounting.transactions accounting.contacts.read",
                "state" => wp_generate_password(32, false)
            ], $options);
            
            return "https://login.xero.com/identity/connect/authorize?" . http_build_query($params);
        }
        
        public function getAccessToken($grant, $options = []) {
            // Mock implementation - in real usage, this would make API calls
            return new League_OAuth2_Client_Token_AccessToken([
                "access_token" => "mock_token_" . wp_generate_password(32, false),
                "expires" => 3600,
                "refresh_token" => "mock_refresh_" . wp_generate_password(32, false)
            ]);
        }
    }
    
    // Create an alias for the namespaced class name
    class_alias('League_OAuth2_Client_Provider_Xero', 'League\\OAuth2\\Client\\Provider\\Xero');
}

if (!class_exists("League\\OAuth2\\Client\\Token\\AccessToken")) {
    class League_OAuth2_Client_Token_AccessToken {
        private $token;
        private $expires;
        private $refresh_token;
        
        public function __construct($options = []) {
            $this->token = $options["access_token"] ?? "";
            $this->expires = $options["expires"] ?? 3600;
            $this->refresh_token = $options["refresh_token"] ?? "";
        }
        
        public function getToken() {
            return $this->token;
        }
        
        public function getExpires() {
            return $this->expires;
        }
        
        public function getRefreshToken() {
            return $this->refresh_token;
        }
    }
    
    // Create an alias for the namespaced class name
    class_alias('League_OAuth2_Client_Token_AccessToken', 'League\\OAuth2\\Client\\Token\\AccessToken');
}
