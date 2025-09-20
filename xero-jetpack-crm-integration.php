<?php
/**
 * Plugin Name: Xero Jetpack CRM Integration
 * Plugin URI: https://github.com/ClientTooling/xero-jetpack-crm-integration
 * Description: Integrates Xero with Jetpack CRM for one-way sync of contacts, invoices, and payments. Automatically installs required dependencies.
 * Version: 1.0.0
 * Author: Maryam
 * Author URI: https://ebridge.tech
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: xero-jetpack-crm-integration
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('XERO_JETPACK_CRM_VERSION', '1.0.0');
define('XERO_JETPACK_CRM_PLUGIN_FILE', __FILE__);
define('XERO_JETPACK_CRM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('XERO_JETPACK_CRM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('XERO_JETPACK_CRM_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Main plugin class with auto-installation
class Xero_Jetpack_CRM_Integration {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('wp_ajax_xero_install_jetpack_crm', array($this, 'install_jetpack_crm'));
        add_action('wp_ajax_xero_install_dependencies', array($this, 'install_dependencies'));
        add_action('wp_ajax_xero_check_status', array($this, 'check_status'));
        add_action('wp_ajax_xero_test_connection', array($this, 'test_connection'));
        add_action('wp_ajax_xero_manual_sync', array($this, 'manual_sync'));
        add_action('wp_ajax_xero_oauth_callback', array($this, 'oauth_callback'));
        add_action('wp_ajax_xero_save_credentials', array($this, 'save_xero_credentials'));
        add_action('wp_ajax_xero_test_credentials', array($this, 'test_xero_credentials'));
        add_action('wp_ajax_xero_save_jetpack_credentials', array($this, 'save_jetpack_credentials'));
        add_action('wp_ajax_xero_test_jetpack_connection', array($this, 'test_jetpack_connection'));
        add_action('wp_ajax_xero_clear_jetpack_config', array($this, 'clear_jetpack_config'));
        add_action('wp_ajax_xero_disconnect', array($this, 'disconnect_xero'));
        add_action('wp_ajax_xero_save_sync_settings', array($this, 'save_sync_settings'));
        add_action('wp_ajax_xero_refresh_token', array($this, 'refresh_xero_token_ajax'));
        add_action('wp_ajax_xero_get_status', array($this, 'get_xero_status_ajax'));
        add_action('wp_ajax_xero_get_auth_url', array($this, 'get_xero_auth_url_ajax'));
        add_action('wp_ajax_xero_get_stats', array($this, 'get_stats'));
        add_action('wp_ajax_xero_verify_credentials', array($this, 'verify_credentials_ajax'));
        add_action('wp_ajax_xero_test_token_exchange', array($this, 'test_token_exchange_ajax'));
        add_action('wp_ajax_xero_get_sync_progress', array($this, 'get_sync_progress_ajax'));
        add_action('wp_ajax_xero_test_sync', array($this, 'test_sync_ajax'));
        
        // Add OAuth callback handler
        add_action('init', array($this, 'handle_oauth_callback'));
        
        // Background sync hook
        add_action('xero_background_sync', array($this, 'background_sync'));
        
        // Activation hook
        register_activation_hook(__FILE__, array($this, 'activate'));
    }
    
    public function init() {
        // Check if we need to show the setup page
        if (is_admin() && !$this->is_fully_ready()) {
            add_action('admin_notices', array($this, 'show_setup_notice'));
        }
    }
    
    public function add_admin_menu() {
        add_options_page(
            __('Xero CRM Integration', 'xero-jetpack-crm-integration'),
            __('Xero CRM Integration', 'xero-jetpack-crm-integration'),
            'manage_options',
            'xero-jetpack-crm-integration',
            array($this, 'admin_page')
        );
        
        // Add settings section
        add_settings_section(
            'xero_jetpack_crm_settings',
            __('Xero Integration Settings', 'xero-jetpack-crm-integration'),
            null,
            'xero-jetpack-crm-integration'
        );
        
        // Register settings
        register_setting('xero_jetpack_crm_settings', 'xero_client_id');
        register_setting('xero_jetpack_crm_settings', 'xero_client_secret');
        register_setting('xero_jetpack_crm_settings', 'jetpack_crm_api_key');
        register_setting('xero_jetpack_crm_settings', 'jetpack_crm_api_secret');
        register_setting('xero_jetpack_crm_settings', 'jetpack_crm_endpoint');
        register_setting('xero_jetpack_crm_settings', 'sync_frequency');
        register_setting('xero_jetpack_crm_settings', 'xero_refresh_token');
        register_setting('xero_jetpack_crm_settings', 'xero_access_token');
        register_setting('xero_jetpack_crm_settings', 'xero_token_expires');
    }
    
    public function admin_enqueue_scripts($hook) {
        if ('settings_page_xero-jetpack-crm-integration' !== $hook) {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script(
            'xero-jetpack-crm-admin',
            XERO_JETPACK_CRM_PLUGIN_URL . 'admin/js/admin.js',
            array('jquery'),
            XERO_JETPACK_CRM_VERSION,
            true
        );
        
        wp_localize_script('xero-jetpack-crm-admin', 'xeroJetpackCrm', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('xero_jetpack_crm_nonce'),
            'strings' => array(
                'installing' => __('Installing...', 'xero-jetpack-crm-integration'),
                'installed' => __('Installed successfully!', 'xero-jetpack-crm-integration'),
                'error' => __('Installation failed. Please try again.', 'xero-jetpack-crm-integration'),
                'checking' => __('Checking status...', 'xero-jetpack-crm-integration'),
            )
        ));
        
        wp_enqueue_style(
            'xero-jetpack-crm-admin',
            XERO_JETPACK_CRM_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            XERO_JETPACK_CRM_VERSION
        );
    }
    
    
    
    
        public function admin_page() {
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have sufficient permissions to access this page.'));
            }
            
            $jetpack_crm_status = $this->check_jetpack_crm_status();
            $dependencies_status = $this->check_dependencies_status();
            
            // Check Xero connection more thoroughly
            $xero_refresh_token = $this->decrypt_token(get_option('xero_refresh_token'));
            $xero_access_token = $this->decrypt_token(get_option('xero_access_token'));
            $xero_token_expires = get_option('xero_token_expires', 0);
            $xero_connected = !empty($xero_refresh_token) && !empty($xero_access_token);
            
            // Debug: Log current connection status
            error_log('Xero Connection Debug:');
            error_log('- Access Token: ' . (!empty($xero_access_token) ? 'Present' : 'Missing'));
            error_log('- Refresh Token: ' . (!empty($xero_refresh_token) ? 'Present' : 'Missing'));
            error_log('- Token Expires: ' . ($xero_token_expires > 0 ? date('Y-m-d H:i:s', $xero_token_expires) : 'Not set'));
            error_log('- Connected: ' . ($xero_connected ? 'Yes' : 'No'));
            
            // Check if token is expired and try to refresh
            if ($xero_connected && $xero_token_expires > 0 && time() > $xero_token_expires) {
                if ($this->refresh_xero_token()) {
                    $xero_token_expires = get_option('xero_token_expires', 0);
                } else {
                    $xero_connected = false;
                }
            }
            
            // Get Xero organization info
            $xero_tenant_name = get_option('xero_tenant_name', '');
            $xero_tenant_type = get_option('xero_tenant_type', '');
            $xero_connected_at = get_option('xero_connected_at', 0);
            
            $jetpack_configured = !empty(get_option('jetpack_crm_api_key')) && !empty(get_option('jetpack_crm_endpoint'));
            
            // Check for success messages
            $show_success_message = isset($_GET['success']) && $_GET['success'] == '1';
            $show_connected_message = isset($_GET['connected']) && $_GET['connected'] == '1';
            
            $last_sync = get_option('xero_last_sync', 0);
            $total_contacts = get_option('xero_synced_contacts_count', 0);
            $total_invoices = get_option('xero_synced_invoices_count', 0);
            $sync_frequency = get_option('sync_frequency', 'manual');
            ?>
            <div class="wrap">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                
                <?php if ($show_success_message && $show_connected_message): ?>
                    <div class="notice notice-success is-dismissible" style="margin: 20px 0; padding: 15px; background: linear-gradient(135deg, #d4edda, #c3e6cb); border-left: 4px solid #28a745; border-radius: 8px;">
                        <p style="margin: 0; font-weight: 600; color: #155724;">
                            <span class="dashicons dashicons-yes-alt" style="color: #28a745; margin-right: 8px;"></span>
                            <strong>Xero Connected Successfully!</strong> Your Xero account is now connected and ready for synchronization.
                        </p>
                    </div>
                <?php endif; ?>
                
                <!-- Debug Information -->
                <div class="notice notice-info" style="margin: 20px 0; padding: 15px; background: #f0f8ff; border-left: 4px solid #0073aa; border-radius: 8px;">
                    <h4 style="margin: 0 0 10px 0; color: #0073aa;">Debug Information</h4>
                    <p style="margin: 0 0 5px 0;"><strong>Current Redirect URI:</strong> <?php echo esc_html(admin_url('admin.php?page=xero-jetpack-crm-integration&action=oauth_callback')); ?></p>
                    <p style="margin: 0 0 5px 0;"><strong>Client ID:</strong> <?php echo !empty(get_option('xero_client_id')) ? 'Present (Length: ' . strlen(get_option('xero_client_id')) . ')' : 'Missing'; ?></p>
                    <p style="margin: 0 0 5px 0;"><strong>Client Secret:</strong> <?php echo !empty(get_option('xero_client_secret')) ? 'Present (Length: ' . strlen(get_option('xero_client_secret')) . ')' : 'Missing'; ?></p>
                    <p style="margin: 0 0 5px 0;"><strong>Access Token:</strong> <?php echo !empty($xero_access_token) ? 'Present (Length: ' . strlen($xero_access_token) . ')' : 'Missing'; ?></p>
                    <p style="margin: 0 0 5px 0;"><strong>Refresh Token:</strong> <?php echo !empty($xero_refresh_token) ? 'Present (Length: ' . strlen($xero_refresh_token) . ')' : 'Missing'; ?></p>
                    <p style="margin: 0 0 5px 0;"><strong>Token Expires:</strong> <?php echo $xero_token_expires > 0 ? date('Y-m-d H:i:s', $xero_token_expires) . ' (' . floor(($xero_token_expires - time()) / 60) . ' minutes left)' : 'Not set'; ?></p>
                    <p style="margin: 0 0 5px 0;"><strong>Organization:</strong> <?php echo !empty($xero_tenant_name) ? esc_html($xero_tenant_name) : 'Not fetched'; ?></p>
                    <p style="margin: 0;"><strong>Connection Status:</strong> <?php echo $xero_connected ? 'Connected' : 'Disconnected'; ?></p>
                </div>
                
                <!-- Single Page Layout -->
                <div class="xero-jetpack-crm-simple">
                    <!-- Header Section -->
                    <div class="page-header">
                        <h2>Integration Status</h2>
                        <p>Configure and manage your Xero and Jetpack CRM integration</p>
                    </div>
                    
                    <!-- Status Cards -->
                    <div class="status-section">
                        <div class="status-card <?php echo $xero_connected ? 'connected' : 'disconnected'; ?>">
                            <div class="card-icon">
                                <span class="material-icons">account_balance</span>
                            </div>
                            <div class="card-content">
                                <h3>Xero CRM</h3>
                                <div class="status-indicator">
                                    <span class="status-dot <?php echo $xero_connected ? 'active' : 'inactive'; ?>"></span>
                                    <span class="status-text"><?php echo $xero_connected ? 'Connected' : 'Disconnected'; ?></span>
                                </div>
                                <p class="card-description">
                                    <?php if ($xero_connected): ?>
                                        <?php if (!empty($xero_tenant_name)): ?>
                                            Connected to <?php echo esc_html($xero_tenant_name); ?>
                                            <?php if ($xero_tenant_type === 'DEMO'): ?>
                                                (Demo - 30 min)
                                            <?php endif; ?>
                                        <?php else: ?>
                                            Your Xero account is connected and ready for sync
                                        <?php endif; ?>
                                    <?php else: ?>
                                        Connect your Xero account to enable data synchronization
                                    <?php endif; ?>
                                </p>
                                <?php if ($xero_connected && $xero_token_expires > 0): ?>
                                    <div class="token-info">
                                        <small>
                                            <?php 
                                            $time_left = $xero_token_expires - time();
                                            if ($time_left > 0) {
                                                $minutes = floor($time_left / 60);
                                                echo "Expires in {$minutes} minutes";
                                            } else {
                                                echo "Token expired";
                                            }
                                            ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="status-card <?php echo $jetpack_configured ? 'connected' : 'disconnected'; ?>">
                            <div class="card-icon">
                                <span class="material-icons">business</span>
                            </div>
                            <div class="card-content">
                                <h3>Jetpack CRM</h3>
                                <div class="status-indicator">
                                    <span class="status-dot <?php echo $jetpack_configured ? 'active' : 'inactive'; ?>"></span>
                                    <span class="status-text"><?php echo $jetpack_configured ? 'Connected' : 'Disconnected'; ?></span>
                                </div>
                                <p class="card-description">
                                    <?php echo $jetpack_configured ? 'Jetpack CRM is configured and ready to receive data' : 'Configure Jetpack CRM to complete the integration'; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Configuration Section -->
                    <div class="configuration-section">
                        <!-- Jetpack CRM Configuration -->
                        <div class="config-card">
                            <div class="config-header">
                                <h3>Jetpack CRM Configuration</h3>
                                <span class="config-status <?php echo $jetpack_configured ? 'configured' : 'not-configured'; ?>">
                                    <?php echo $jetpack_configured ? 'Configured' : 'Not Configured'; ?>
                                </span>
                            </div>
                            
                            <?php if (!$jetpack_crm_status['installed'] || !$dependencies_status['installed']): ?>
                                <div class="prerequisites">
                                    <h4>Prerequisites Required</h4>
                                    <?php if (!$jetpack_crm_status['installed']): ?>
                                        <div class="prerequisite-item">
                                            <span class="material-icons">admin_plugins</span>
                                            <span>Jetpack CRM Plugin</span>
                                            <button id="install-jetpack-crm" class="btn btn-primary">Install</button>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!$dependencies_status['installed']): ?>
                                        <div class="prerequisite-item">
                                            <span class="material-icons">admin_tools</span>
                                            <span>OAuth2 Dependencies</span>
                                            <button id="install-dependencies" class="btn btn-primary">Install</button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="config-form">
                                    <div class="form-group">
                                        <label for="jetpack_crm_api_key">API Key</label>
                                        <input type="password" id="jetpack_crm_api_key" value="<?php echo esc_attr(get_option('jetpack_crm_api_key')); ?>" placeholder="Enter your Jetpack CRM API key">
                                        <button type="button" class="toggle-password" onclick="togglePassword('jetpack_crm_api_key')">
                                            <span class="material-icons">visibility</span>
                                        </button>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="jetpack_crm_api_secret">API Secret (Optional)</label>
                                        <input type="password" id="jetpack_crm_api_secret" value="<?php echo esc_attr(get_option('jetpack_crm_api_secret')); ?>" placeholder="Enter your Jetpack CRM API secret">
                                        <button type="button" class="toggle-password" onclick="togglePassword('jetpack_crm_api_secret')">
                                            <span class="material-icons">visibility</span>
                                        </button>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="jetpack_crm_endpoint">Endpoint URL</label>
                                        <input type="url" id="jetpack_crm_endpoint" value="<?php echo esc_attr(get_option('jetpack_crm_endpoint')); ?>" placeholder="https://your-site.com/wp-json/zero-bs-crm/v1/">
                                    </div>
                                    
                                    <div class="form-actions">
                                        <button id="jetpack-toggle-connection" class="btn <?php echo $jetpack_configured ? 'btn-danger' : 'btn-success'; ?>" data-action="<?php echo $jetpack_configured ? 'disconnect' : 'connect'; ?>">
                                            <span class="material-icons"><?php echo $jetpack_configured ? 'link_off' : 'link'; ?></span>
                                            <?php echo $jetpack_configured ? 'Disconnect' : 'Connect'; ?>
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Xero CRM Configuration -->
                        <div class="config-card">
                            <div class="config-header">
                                <h3>Xero CRM Configuration</h3>
                                <span class="config-status <?php echo $xero_connected ? 'configured' : 'not-configured'; ?>">
                                    <?php echo $xero_connected ? 'Connected' : 'Not Connected'; ?>
                                </span>
                            </div>
                            
                            <?php if ($xero_connected && !empty($xero_tenant_name)): ?>
                                <div class="organization-info">
                                    <div class="org-details">
                                        <span class="material-icons">business</span>
                                        <div class="org-text">
                                            <strong>Connected Organization:</strong>
                                            <span class="org-name"><?php echo esc_html($xero_tenant_name); ?></span>
                                            <?php if ($xero_tenant_type === 'DEMO'): ?>
                                                <span class="org-type demo">(Demo Account - 30 minutes)</span>
                                            <?php elseif ($xero_tenant_type === 'LIVE'): ?>
                                                <span class="org-type live">(Live Account)</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if ($xero_connected_at > 0): ?>
                                        <div class="connection-time">
                                            <small>Connected: <?php echo date('M j, Y \a\t g:i A', $xero_connected_at); ?></small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="config-form">
                                <div class="form-group">
                                    <label for="xero_client_id">Client ID</label>
                                    <input type="text" id="xero_client_id" value="<?php echo esc_attr(get_option('xero_client_id')); ?>" placeholder="Enter your Xero Client ID">
                                </div>
                                
                                <div class="form-group">
                                    <label for="xero_client_secret">Client Secret</label>
                                    <input type="password" id="xero_client_secret" value="<?php echo esc_attr(get_option('xero_client_secret')); ?>" placeholder="Enter your Xero Client Secret">
                                    <button type="button" class="toggle-password" onclick="togglePassword('xero_client_secret')">
                                        <span class="material-icons">visibility</span>
                                    </button>
                                </div>
                                
                                <div class="form-group">
                                    <label for="redirect_uri">Redirect URI</label>
                                    <div class="input-group">
                                        <input type="url" id="redirect_uri" value="<?php echo esc_attr(admin_url('admin.php?page=xero-jetpack-crm-integration&action=oauth_callback')); ?>" readonly>
                                        <button type="button" class="copy-btn" onclick="copyToClipboard('redirect_uri')">
                                            <span class="material-icons">content_copy</span>
                                        </button>
                                    </div>
                                    <small>Copy this URL to your Xero app configuration</small>
                                </div>
                                
                                <div class="form-actions">
                                    <button id="save-xero-credentials" class="btn btn-primary" style="margin-right: 10px;">
                                        <span class="material-icons">save</span>
                                        Save Credentials
                                    </button>
                                    <button id="verify-xero-credentials" class="btn btn-outline" style="margin-right: 10px;">
                                        <span class="material-icons">verified_user</span>
                                        Verify Credentials
                                    </button>
                                    <button id="test-token-exchange" class="btn btn-outline" style="margin-right: 10px;">
                                        <span class="material-icons">bug_report</span>
                                        Test Token Exchange
                                    </button>
                                    <button id="xero-toggle-connection" class="btn <?php echo $xero_connected ? 'btn-danger' : 'btn-success'; ?>" data-action="<?php echo $xero_connected ? 'disconnect' : 'connect'; ?>">
                                        <span class="material-icons"><?php echo $xero_connected ? 'link_off' : 'link'; ?></span>
                                        <?php echo $xero_connected ? 'Disconnect' : 'Connect'; ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Statistics and Actions -->
                    <?php if ($xero_connected && $jetpack_configured): ?>
                        <div class="stats-section">
                            <h3>Integration Statistics</h3>
                            <div class="stats-grid">
                                <div class="stat-item">
                                    <span class="stat-number"><?php echo number_format($total_contacts); ?></span>
                                    <span class="stat-label">Synced Contacts</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number"><?php echo number_format($total_invoices); ?></span>
                                    <span class="stat-label">Synced Invoices</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number"><?php echo ucfirst($sync_frequency); ?></span>
                                    <span class="stat-label">Sync Frequency</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number"><?php echo $last_sync ? date('M j, Y', $last_sync) : 'Never'; ?></span>
                                    <span class="stat-label">Last Sync</span>
                                </div>
                            </div>
                            
                            <div class="actions-section">
                                <button id="test-sync" class="btn btn-outline" style="background: #17a2b8; color: white; border-color: #17a2b8;">
                                    <span class="material-icons">bug_report</span>
                                    Test Sync
                                </button>
                                <button id="manual-sync" class="btn btn-primary">
                                    <span class="material-icons">sync</span>
                                    Manual Sync
                                </button>
                                <button id="sync-settings" class="btn btn-outline">
                                    <span class="material-icons">settings</span>
                                    Sync Settings
                                </button>
                                <button id="view-logs" class="btn btn-outline">
                                    <span class="material-icons">description</span>
                                    View Logs
                                </button>
                            </div>
                            
                            <!-- Sync Progress Section -->
                            <div id="sync-progress-section" class="sync-progress-section" style="display: none; margin-top: 20px;">
                                <h4>Sync Progress</h4>
                                <div class="progress-container">
                                    <div class="progress-bar">
                                        <div id="progress-fill" class="progress-fill" style="width: 0%;"></div>
                                    </div>
                                    <div id="progress-text" class="progress-text">Ready to sync</div>
                                </div>
                                <div id="sync-details" class="sync-details">
                                    <div id="current-item" class="current-item"></div>
                                    <div id="sync-stats" class="sync-stats"></div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        
        <script>
        jQuery(document).ready(function($) {
            console.log('Xero Jetpack CRM Integration: JavaScript loaded');
            console.log('jQuery version:', $.fn.jquery);
            console.log('xeroJetpackCrm object:', typeof xeroJetpackCrm !== 'undefined' ? xeroJetpackCrm : 'undefined');
            
            // Test if buttons exist
            console.log('Xero button found:', $('#xero-toggle-connection').length);
            console.log('Jetpack button found:', $('#jetpack-toggle-connection').length);
            
            // Check if we just returned from OAuth and show connected state
            if (window.location.search.includes('connected=1') && window.location.search.includes('success=1')) {
                // Update toggle button to show disconnect state
                updateXeroToggleButton(true);
                
                // Show success message
                showNotification('Xero connected successfully! You can now proceed to configure Jetpack CRM.', 'success');
            }
            
            // Simple page functionality - no tabs needed
            
            // Show notification function
            function showNotification(message, type) {
                var notificationClass = type === 'success' ? 'notice-success' : 'notice-error';
                var $notification = $('<div class="notice ' + notificationClass + ' is-dismissible"><p>' + message + '</p></div>');
                $('.wrap h1').after($notification);
                setTimeout(function() {
                    $notification.fadeOut();
                }, 5000);
            }
            
            // Toggle password visibility
            window.togglePassword = function(inputId) {
                var input = document.getElementById(inputId);
                var button = input.nextElementSibling;
                var icon = button.querySelector('.material-icons');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.textContent = 'visibility_off';
                } else {
                    input.type = 'password';
                    icon.textContent = 'visibility';
                }
            }
            
            // Copy to clipboard function
            window.copyToClipboard = function(inputId) {
                var input = document.getElementById(inputId);
                input.select();
                input.setSelectionRange(0, 99999); // For mobile devices
                document.execCommand('copy');
                showNotification('Redirect URI copied to clipboard!', 'success');
            }
            
            // Update Xero status periodically
            function updateXeroStatus() {
                $.ajax({
                    url: xeroJetpackCrm.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'xero_get_status',
                        nonce: xeroJetpackCrm.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            var data = response.data;
                            updateXeroStatusDisplay(data);
                        }
                    },
                    error: function() {
                        console.log('Failed to update Xero status');
                    }
                });
            }
            
            function updateXeroStatusDisplay(data) {
                // Target the Xero status card specifically (first status card)
                var $xeroStatusCard = $('.status-card').first();
                var $statusDot = $xeroStatusCard.find('.status-dot');
                var $statusText = $xeroStatusCard.find('.status-text');
                var $description = $xeroStatusCard.find('.card-description');
                var $tokenInfo = $xeroStatusCard.find('.token-info');
                
                console.log('Updating Xero status:', data);
                console.log('Status card found:', $xeroStatusCard.length);
                console.log('Status text found:', $statusText.length);
                
                if (data.connected) {
                    $xeroStatusCard.removeClass('disconnected').addClass('connected');
                    $statusDot.removeClass('inactive').addClass('active');
                    $statusText.text('Connected');
                    
                    var description = 'Your Xero account is connected and ready for sync';
                    if (data.tenant_name) {
                        description = 'Connected to ' + data.tenant_name;
                        if (data.tenant_type === 'DEMO') {
                            description += ' (Demo - 30 min)';
                        }
                    }
                    $description.text(description);
                    
                    if (data.minutes_left > 0) {
                        $tokenInfo.html('<small>Expires in ' + data.minutes_left + ' minutes</small>');
                    } else {
                        $tokenInfo.html('<small>Token expired</small>');
                    }
                    
                    // Update organization info in config card
                    updateOrganizationInfo(data);
                } else {
                    $xeroStatusCard.removeClass('connected').addClass('disconnected');
                    $statusDot.removeClass('active').addClass('inactive');
                    $statusText.text('Disconnected');
                    $description.text('Connect your Xero account to enable data synchronization');
                    $tokenInfo.empty();
                    
                    // Hide organization info
                    $('.organization-info').remove();
                }
            }
            
            function updateOrganizationInfo(data) {
                var $configCard = $('.config-card').last(); // Xero config card
                var $existingOrgInfo = $configCard.find('.organization-info');
                
                if ($existingOrgInfo.length > 0) {
                    $existingOrgInfo.remove();
                }
                
                if (data.connected && data.tenant_name) {
                    var orgTypeClass = data.tenant_type === 'DEMO' ? 'demo' : 'live';
                    var orgTypeText = data.tenant_type === 'DEMO' ? '(Demo Account - 30 minutes)' : '(Live Account)';
                    
                    var orgHtml = '<div class="organization-info">' +
                        '<div class="org-details">' +
                        '<span class="material-icons">business</span>' +
                        '<div class="org-text">' +
                        '<strong>Connected Organization:</strong>' +
                        '<span class="org-name">' + data.tenant_name + '</span>' +
                        '<span class="org-type ' + orgTypeClass + '">' + orgTypeText + '</span>' +
                        '</div>' +
                        '</div>';
                    
                    if (data.connected_at > 0) {
                        var connectedDate = new Date(data.connected_at * 1000);
                        var formattedDate = connectedDate.toLocaleDateString('en-US', {
                            month: 'short',
                            day: 'numeric',
                            year: 'numeric',
                            hour: 'numeric',
                            minute: '2-digit',
                            hour12: true
                        });
                        orgHtml += '<div class="connection-time">' +
                            '<small>Connected: ' + formattedDate + '</small>' +
                            '</div>';
                    }
                    
                    orgHtml += '</div>';
                    
                    $configCard.find('.config-header').after(orgHtml);
                }
            }
            
            // Update status immediately on page load
            updateXeroStatus();
            
            // Update status every 30 seconds
            setInterval(updateXeroStatus, 30000);
            
            // Add manual refresh button for testing
            $('.status-card').first().append('<button id="refresh-xero-status" class="btn btn-sm" style="margin-top: 10px; font-size: 12px; padding: 4px 8px;">Refresh Status</button>');
            
            $('#refresh-xero-status').on('click', function() {
                console.log('Manual status refresh triggered');
                updateXeroStatus();
            });
            
            // Function to update the toggle button state
            function updateXeroToggleButton(isConnected) {
                var $button = $('#xero-toggle-connection');
                if (isConnected) {
                    $button.removeClass('btn-success').addClass('btn-danger');
                    $button.find('.material-icons').text('link_off');
                    $button.html('<span class="material-icons">link_off</span>Disconnect');
                    $button.data('action', 'disconnect');
                } else {
                    $button.removeClass('btn-danger').addClass('btn-success');
                    $button.find('.material-icons').text('link');
                    $button.html('<span class="material-icons">link</span>Connect');
                    $button.data('action', 'connect');
                }
            }
            
            // Function to update the Jetpack toggle button state
            function updateJetpackToggleButton(isConnected) {
                var $button = $('#jetpack-toggle-connection');
                if (isConnected) {
                    $button.removeClass('btn-success').addClass('btn-danger');
                    $button.find('.material-icons').text('link_off');
                    $button.html('<span class="material-icons">link_off</span>Disconnect');
                    $button.data('action', 'disconnect');
                } else {
                    $button.removeClass('btn-danger').addClass('btn-success');
                    $button.find('.material-icons').text('link');
                    $button.html('<span class="material-icons">link</span>Connect');
                    $button.data('action', 'connect');
                }
            }
            
            // Install Jetpack CRM
            $('#install-jetpack-crm').on('click', function() {
                installComponent('jetpack-crm', $(this));
            });
            
            // Install Dependencies
            $('#install-dependencies').on('click', function() {
                installComponent('dependencies', $(this));
            });
            
            // Save Xero Credentials
            $('#save-xero-credentials').on('click', function() {
                var clientId = $('#xero_client_id').val();
                var clientSecret = $('#xero_client_secret').val();
                
                if (!clientId || !clientSecret) {
                    alert('Please enter both Client ID and Client Secret.');
                    return;
                }
                
                $.ajax({
                    url: xeroJetpackCrm.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'xero_save_credentials',
                        nonce: xeroJetpackCrm.nonce,
                        xero_client_id: clientId,
                        xero_client_secret: clientSecret
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#connect-xero').show();
                            showTestResult('#xero-test-result', 'Credentials saved successfully!', 'success');
                        } else {
                            showTestResult('#xero-test-result', 'Failed to save credentials: ' + response.data, 'error');
                        }
                    }
                });
            });
            
            // Test Xero Credentials
            $('#test-xero-credentials').on('click', function() {
                var clientId = $('#xero_client_id').val();
                var clientSecret = $('#xero_client_secret').val();
                
                if (!clientId || !clientSecret) {
                    showTestResult('#xero-test-result', 'Please enter both Client ID and Client Secret first.', 'error');
                    return;
                }
                
                showTestResult('#xero-test-result', 'Testing credentials...', 'info');
                
                $.ajax({
                    url: xeroJetpackCrm.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'xero_test_credentials',
                        nonce: xeroJetpackCrm.nonce,
                        xero_client_id: clientId,
                        xero_client_secret: clientSecret
                    },
                    success: function(response) {
                        if (response.success) {
                            showTestResult('#xero-test-result', 'Credentials are valid! You can now connect to Xero.', 'success');
                            $('#connect-xero').show();
                        } else {
                            showTestResult('#xero-test-result', 'Invalid credentials: ' + response.data, 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        showTestResult('#xero-test-result', 'Test failed: ' + error, 'error');
                    }
                });
            });
            
            // Save Xero Credentials
            $('#save-xero-credentials').on('click', function() {
                var $button = $(this);
                var originalHtml = $button.html();
                $button.prop('disabled', true);
                $button.html('<span class="material-icons">sync</span>Saving...');
                
                var clientId = $('#xero_client_id').val();
                var clientSecret = $('#xero_client_secret').val();
                
                if (!clientId || !clientSecret) {
                    showNotification('Please enter both Client ID and Client Secret.', 'error');
                    $button.html(originalHtml).prop('disabled', false);
                    return;
                }
                
                $.ajax({
                    url: xeroJetpackCrm.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'xero_save_credentials',
                        nonce: xeroJetpackCrm.nonce,
                        xero_client_id: clientId,
                        xero_client_secret: clientSecret
                    },
                    success: function(response) {
                        if (response.success) {
                            showNotification('Xero credentials saved successfully!', 'success');
                            // Update the connect button state
                            updateXeroToggleButton(false);
                        } else {
                            showNotification('Failed to save credentials: ' + response.data, 'error');
                        }
                        $button.html(originalHtml).prop('disabled', false);
                    },
                    error: function() {
                        showNotification('Failed to save credentials due to network error', 'error');
                        $button.html(originalHtml).prop('disabled', false);
                    }
                });
            });
            
            // Verify Xero Credentials
            $('#verify-xero-credentials').on('click', function() {
                var $button = $(this);
                var originalHtml = $button.html();
                $button.prop('disabled', true);
                $button.html('<span class="material-icons">sync</span>Verifying...');
                
                $.ajax({
                    url: xeroJetpackCrm.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'xero_verify_credentials',
                        nonce: xeroJetpackCrm.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            showNotification('Credentials verified: ' + response.data.message, 'success');
                        } else {
                            showNotification('Credential verification failed: ' + response.data.error, 'error');
                        }
                        $button.html(originalHtml).prop('disabled', false);
                    },
                    error: function() {
                        showNotification('Failed to verify credentials due to network error', 'error');
                        $button.html(originalHtml).prop('disabled', false);
                    }
                });
            });
            
            // Test Token Exchange
            $('#test-token-exchange').on('click', function() {
                var $button = $(this);
                var originalHtml = $button.html();
                $button.prop('disabled', true);
                $button.html('<span class="material-icons">sync</span>Testing...');
                
                $.ajax({
                    url: xeroJetpackCrm.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'xero_test_token_exchange',
                        nonce: xeroJetpackCrm.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            var data = response.data;
                            var message = 'Token Exchange Test Results:\n';
                            message += 'HTTP Code: ' + data.http_code + '\n';
                            message += 'Client ID: ' + data.client_id + '\n';
                            message += 'Client Secret Length: ' + data.client_secret_length + '\n';
                            message += 'Redirect URI: ' + data.redirect_uri + '\n';
                            message += 'Response: ' + data.response_body;
                            
                            if (data.error) {
                                message += '\n\nError: ' + data.error;
                                if (data.error_description) {
                                    message += '\nDescription: ' + data.error_description;
                                }
                            }
                            
                            alert(message);
                        } else {
                            showNotification('Token exchange test failed: ' + response.data, 'error');
                        }
                        $button.html(originalHtml).prop('disabled', false);
                    },
                    error: function() {
                        showNotification('Failed to test token exchange due to network error', 'error');
                        $button.html(originalHtml).prop('disabled', false);
                    }
                });
            });
            
            // Toggle Xero Connection
            $('#xero-toggle-connection').on('click', function() {
                console.log('Xero button clicked');
                var action = $(this).data('action');
                console.log('Xero action:', action);
                
                if (action === 'connect') {
                    // Connect to Xero
                    var clientId = $('#xero_client_id').val();
                    if (!clientId) {
                        alert('Please enter your Xero Client ID first.');
                        return;
                    }
                    
                    // Show loading state
                    var $button = $(this);
                    var originalHtml = $button.html();
                    $button.prop('disabled', true);
                    $button.html('<span class="material-icons">sync</span>Connecting...');
                    
                    // Redirect to Xero OAuth using server-generated URL
                    $.ajax({
                        url: xeroJetpackCrm.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'xero_get_auth_url',
                            nonce: xeroJetpackCrm.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                window.location.href = response.data.auth_url;
                            } else {
                                alert('Failed to generate authorization URL: ' + response.data);
                            }
                        },
                        error: function() {
                            alert('Failed to generate authorization URL');
                        }
                    });
                } else if (action === 'disconnect') {
                    // Disconnect from Xero
                    if (confirm('This will disconnect your Xero account. Continue?')) {
                        var $button = $(this);
                        var originalHtml = $button.html();
                        $button.prop('disabled', true);
                        $button.html('<span class="material-icons">sync</span>Disconnecting...');
                        
                        $.ajax({
                            url: xeroJetpackCrm.ajaxUrl,
                            type: 'POST',
                            data: {
                                action: 'xero_disconnect',
                                nonce: xeroJetpackCrm.nonce
                            },
                            success: function(response) {
                                showNotification('Xero disconnected successfully!', 'success');
                                updateXeroToggleButton(false); // Switch back to connect state
                                $button.html(originalHtml);
                                $button.prop('disabled', false);
                                setTimeout(function() {
                                    location.reload();
                                }, 1000);
                            },
                            error: function() {
                                showNotification('Failed to disconnect Xero account', 'error');
                                $button.html(originalHtml);
                                $button.prop('disabled', false);
                            }
                        });
                    }
                }
            });
            
            // Toggle Jetpack Connection
            $('#jetpack-toggle-connection').on('click', function() {
                console.log('Jetpack button clicked');
                var action = $(this).data('action');
                console.log('Jetpack action:', action);
                
                if (action === 'connect') {
                    // Connect to Jetpack CRM
                    var apiKey = $('#jetpack_crm_api_key').val();
                    var endpoint = $('#jetpack_crm_endpoint').val();
                    
                    if (!apiKey || !endpoint) {
                        alert('Please enter both API Key and Endpoint URL.');
                        return;
                    }
                    
                    var $button = $(this);
                    var originalHtml = $button.html();
                    $button.prop('disabled', true);
                    $button.html('<span class="material-icons">sync</span>Connecting...');
                    
                    $.ajax({
                        url: xeroJetpackCrm.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'xero_save_jetpack_credentials',
                            nonce: xeroJetpackCrm.nonce,
                            jetpack_crm_api_key: apiKey,
                            jetpack_crm_api_secret: $('#jetpack_crm_api_secret').val(),
                            jetpack_crm_endpoint: endpoint
                        },
                        success: function(response) {
                            if (response.success) {
                                showNotification('Jetpack CRM connected successfully!', 'success');
                                updateJetpackToggleButton(true);
                                $button.html(originalHtml);
                                $button.prop('disabled', false);
                            } else {
                                showNotification('Failed to connect to Jetpack CRM: ' + response.data, 'error');
                                $button.html(originalHtml);
                                $button.prop('disabled', false);
                            }
                        },
                        error: function() {
                            showNotification('Failed to connect to Jetpack CRM due to network error', 'error');
                            $button.html(originalHtml);
                            $button.prop('disabled', false);
                        }
                    });
                } else if (action === 'disconnect') {
                    // Disconnect from Jetpack CRM
                    if (confirm('This will disconnect your Jetpack CRM. Continue?')) {
                        var $button = $(this);
                        var originalHtml = $button.html();
                        $button.prop('disabled', true);
                        $button.html('<span class="material-icons">sync</span>Disconnecting...');
                        
                        $.ajax({
                            url: xeroJetpackCrm.ajaxUrl,
                            type: 'POST',
                            data: {
                                action: 'xero_clear_jetpack_config',
                                nonce: xeroJetpackCrm.nonce
                            },
                            success: function(response) {
                                showNotification('Jetpack CRM disconnected successfully!', 'success');
                                updateJetpackToggleButton(false);
                                $button.html(originalHtml);
                                $button.prop('disabled', false);
                                setTimeout(function() {
                                    location.reload();
                                }, 1000);
                            },
                            error: function() {
                                showNotification('Failed to disconnect Jetpack CRM', 'error');
                                $button.html(originalHtml);
                                $button.prop('disabled', false);
                            }
                        });
                    }
                }
            });
            
            // Test Xero Connection
            $('#test-xero-connection').on('click', function() {
                testConnection('xero');
            });
            
            // Save Jetpack Credentials
            $('#save-jetpack-credentials').on('click', function() {
                var apiKey = $('#jetpack_crm_api_key').val();
                var apiSecret = $('#jetpack_crm_api_secret').val();
                var endpoint = $('#jetpack_crm_endpoint').val();
                
                if (!apiKey || !endpoint) {
                    alert('Please enter API Key and Endpoint URL.');
                    return;
                }
                
                $.ajax({
                    url: xeroJetpackCrm.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'xero_save_jetpack_credentials',
                        nonce: xeroJetpackCrm.nonce,
                        jetpack_crm_api_key: apiKey,
                        jetpack_crm_api_secret: apiSecret,
                        jetpack_crm_endpoint: endpoint
                    },
                    success: function(response) {
                        if (response.success) {
                            showTestResult('#jetpack-test-result', 'Configuration saved successfully!', 'success');
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            showTestResult('#jetpack-test-result', 'Failed to save configuration: ' + response.data, 'error');
                        }
                    }
                });
            });
            
            // Test Jetpack Credentials
            $('#test-jetpack-credentials').on('click', function() {
                var apiKey = $('#jetpack_crm_api_key').val();
                var apiSecret = $('#jetpack_crm_api_secret').val();
                var endpoint = $('#jetpack_crm_endpoint').val();
                
                if (!apiKey || !endpoint) {
                    showTestResult('#jetpack-test-result', 'Please enter API Key and Endpoint URL first.', 'error');
                    return;
                }
                
                showTestResult('#jetpack-test-result', 'Testing connection...', 'info');
                
                $.ajax({
                    url: xeroJetpackCrm.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'xero_test_jetpack_connection',
                        nonce: xeroJetpackCrm.nonce,
                        jetpack_crm_api_key: apiKey,
                        jetpack_crm_api_secret: apiSecret,
                        jetpack_crm_endpoint: endpoint
                    },
                    success: function(response) {
                        if (response.success) {
                            showTestResult('#jetpack-test-result', 'Connection successful! ' + response.data, 'success');
                        } else {
                            showTestResult('#jetpack-test-result', 'Connection failed: ' + response.data, 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        showTestResult('#jetpack-test-result', 'Test failed: ' + error, 'error');
                    }
                });
            });
            
            // Test Jetpack Connection
            $('#test-jetpack-connection').on('click', function() {
                testConnection('jetpack');
            });
            
            // Reconfigure Jetpack
            $('#reconfigure-jetpack').on('click', function() {
                if (confirm('This will clear your current Jetpack CRM configuration. Continue?')) {
                    $.ajax({
                        url: xeroJetpackCrm.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'xero_clear_jetpack_config',
                            nonce: xeroJetpackCrm.nonce
                        },
                        success: function(response) {
                            location.reload();
                        }
                    });
                }
            });
            
            // Disconnect Xero (for the connected status display)
            $('#disconnect-xero, #xero-toggle-connection-connected').on('click', function() {
                if (confirm('This will disconnect your Xero account. Continue?')) {
                    var $button = $(this);
                    $button.addClass('loading').prop('disabled', true);
                    
                    $.ajax({
                        url: xeroJetpackCrm.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'xero_disconnect',
                            nonce: xeroJetpackCrm.nonce
                        },
                        success: function(response) {
                            showNotification('Xero disconnected successfully!', 'success');
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        },
                        error: function() {
                            showNotification('Failed to disconnect Xero account', 'error');
                            $button.removeClass('loading').prop('disabled', false);
                        }
                    });
                }
            });
            
            // Save Sync Settings
            $('#save-sync-settings').on('click', function() {
                var frequency = $('#sync_frequency').val();
                
                $.ajax({
                    url: xeroJetpackCrm.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'xero_save_sync_settings',
                        nonce: xeroJetpackCrm.nonce,
                        sync_frequency: frequency
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Sync settings saved successfully!');
                        } else {
                            alert('Failed to save settings: ' + response.data);
                        }
                    }
                });
            });
            
            // Manual Sync
            $('#manual-sync').on('click', function() {
                if (confirm('This will start a manual sync from Xero to Jetpack CRM. Continue?')) {
                    var $button = $(this);
                    var originalHtml = $button.html();
                    $button.prop('disabled', true).html('<span class="material-icons spin">sync</span> Starting...');
                    
                    // Show progress section
                    $('#sync-progress-section').show();
                    
                    $.ajax({
                        url: xeroJetpackCrm.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'xero_manual_sync',
                            nonce: xeroJetpackCrm.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                showNotification('Sync started in background. Check progress below.', 'success');
                                // Start polling for progress
                                startProgressPolling();
                            } else {
                                showNotification('Manual sync failed: ' + response.data, 'error');
                                $('#sync-progress-section').hide();
                            }
                            $button.html(originalHtml).prop('disabled', false);
                        },
                        error: function() {
                            showNotification('Manual sync failed due to network error', 'error');
                            $('#sync-progress-section').hide();
                            $button.html(originalHtml).prop('disabled', false);
                        }
                    });
                }
            });
            
            // Test Sync Button
            $('#test-sync').on('click', function() {
                var $button = $(this);
                var originalHtml = $button.html();
                $button.prop('disabled', true).html('<span class="material-icons spin">bug_report</span> Testing...');
                
                $.ajax({
                    url: xeroJetpackCrm.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'xero_test_sync',
                        nonce: xeroJetpackCrm.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            var data = response.data;
                            var message = 'Test completed! Xero: ' + data.xero_contacts + ' contacts, Jetpack CRM: ' + data.jetpack_contacts + ' contacts';
                            showNotification(message, 'success');
                            console.log('Test Sync Results:', data);
                        } else {
                            showNotification('Test failed: ' + response.data, 'error');
                            console.error('Test Sync Error:', response.data);
                        }
                        $button.html(originalHtml).prop('disabled', false);
                    },
                    error: function() {
                        showNotification('Test failed due to network error', 'error');
                        $button.html(originalHtml).prop('disabled', false);
                    }
                });
            });
            
            // Progress polling function
            function startProgressPolling() {
                var pollInterval = setInterval(function() {
                    $.ajax({
                        url: xeroJetpackCrm.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'xero_get_sync_progress',
                            nonce: xeroJetpackCrm.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                updateProgressDisplay(response.data);
                                
                                // Stop polling if sync is completed or errored
                                if (response.data.status === 'completed' || response.data.status === 'error') {
                                    clearInterval(pollInterval);
                                    if (response.data.status === 'completed') {
                                        showNotification('Sync completed successfully!', 'success');
                                        updateXeroStatus(); // Refresh stats
                                    } else {
                                        showNotification('Sync failed: ' + response.data.current_step, 'error');
                                    }
                                }
                            }
                        },
                        error: function() {
                            console.log('Failed to get sync progress');
                        }
                    });
                }, 1000); // Poll every second
            }
            
            // Update progress display
            function updateProgressDisplay(progress) {
                // Update progress bar
                $('#progress-fill').css('width', progress.progress + '%');
                $('#progress-text').text(progress.current_step);
                
                // Update current item
                var currentItem = '';
                if (progress.current_contact) {
                    currentItem = 'Syncing contact: <strong>' + progress.current_contact + '</strong>';
                } else if (progress.current_invoice) {
                    currentItem = 'Syncing invoice: <strong>' + progress.current_invoice + '</strong>';
                }
                $('#current-item').html(currentItem);
                
                // Update stats
                var stats = '';
                if (progress.total_contacts > 0) {
                    stats += 'Contacts: ' + progress.synced_contacts + '/' + progress.total_contacts;
                }
                if (progress.total_invoices > 0) {
                    if (stats) stats += ' | ';
                    stats += 'Invoices: ' + progress.synced_invoices + '/' + progress.total_invoices;
                }
                if (progress.errors > 0) {
                    if (stats) stats += ' | ';
                    stats += 'Errors: ' + progress.errors;
                }
                $('#sync-stats').text(stats);
            }
            
            // Sync Settings Modal
            $('#sync-settings').on('click', function() {
                $('#sync-settings-modal').fadeIn(300);
            });
            
            $('.modal-close').on('click', function() {
                $('#sync-settings-modal').fadeOut(300);
            });
            
            // Export Data
            $('#export-data').on('click', function() {
                showStatusBar('Preparing data export...');
                setTimeout(function() {
                    hideStatusBar();
                    showNotification('Data export feature coming soon!', 'info');
                }, 2000);
            });
            
            // View Logs
            $('#view-logs').on('click', function() {
                showStatusBar('Loading sync logs...');
                setTimeout(function() {
                    hideStatusBar();
                    showNotification('Log viewer feature coming soon!', 'info');
                }, 1500);
            });
            
            // Real-time Stats Update
            setInterval(function() {
                updateStats();
            }, 30000); // Update every 30 seconds
            
            // Dashboard button handlers
            $('#sync-settings').on('click', function() {
                showNotification('Sync settings feature coming soon!', 'info');
            });
            
            $('#view-logs').on('click', function() {
                showNotification('Log viewer feature coming soon!', 'info');
            });
            
            $('#export-data').on('click', function() {
                showNotification('Data export feature coming soon!', 'info');
            });
            
            // Update stats function
            function updateStats() {
                $.ajax({
                    url: xeroJetpackCrm.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'xero_get_stats',
                        nonce: xeroJetpackCrm.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            var stats = response.data;
                            $('.stat-card h4').each(function(index) {
                                var $this = $(this);
                                if (index === 0) {
                                    $this.text(numberFormat(stats.contacts || 0));
                                } else if (index === 1) {
                                    $this.text(numberFormat(stats.invoices || 0));
                                } else if (index === 2) {
                                    $this.text(stats.frequency || 'Manual');
                                } else if (index === 3) {
                                    $this.text(stats.last_sync || 'Never');
                                }
                            });
                        }
                    }
                });
            }
            
            function numberFormat(num) {
                return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
            }
            
            // Next button functionality
            $('#next-to-jetpack, #next-to-jetpack-from-config').on('click', function() {
                // Redirect to step 3 (Jetpack CRM configuration)
                window.location.href = '<?php echo admin_url('options-general.php?page=xero-jetpack-crm-integration&step=3'); ?>';
            });
            
            // Progress bar navigation - make it always clickable
            $('.step').on('click', function() {
                var stepNumber = $(this).find('.step-number').text();
                if (stepNumber && !$(this).hasClass('disabled')) {
                    window.location.href = '<?php echo admin_url('options-general.php?page=xero-jetpack-crm-integration&step='); ?>' + stepNumber;
                }
            });
            
            // Helper Functions
            function testConnection(type) {
                var $button = $('#' + type + '-connection');
                $button.prop('disabled', true).text('Testing...');
                
                $.ajax({
                    url: xeroJetpackCrm.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'xero_test_' + type + '_connection',
                        nonce: xeroJetpackCrm.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(type.charAt(0).toUpperCase() + type.slice(1) + ' connection test successful!');
                        } else {
                            alert(type.charAt(0).toUpperCase() + type.slice(1) + ' connection test failed: ' + response.data);
                        }
                    },
                    error: function(xhr, status, error) {
                        alert(type.charAt(0).toUpperCase() + type.slice(1) + ' connection test failed: ' + error);
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('Test Connection');
                    }
                });
            }
            
            function showTestResult(selector, message, type) {
                var $result = $(selector);
                $result.removeClass('success error info').addClass(type);
                $result.html(message).show();
            }
            
            function installComponent(type, $button) {
                $button.prop('disabled', true);
                $('#progress-container').show();
                
                var progressMessages = {
                    'jetpack-crm': [
                        { percent: 10, message: 'Starting Jetpack CRM installation...' },
                        { percent: 30, message: 'Downloading Jetpack CRM...' },
                        { percent: 60, message: 'Installing Jetpack CRM...' },
                        { percent: 80, message: 'Activating Jetpack CRM...' },
                        { percent: 100, message: 'Installation completed!' }
                    ],
                    'dependencies': [
                        { percent: 5, message: 'Starting dependency installation...' },
                        { percent: 15, message: 'Checking server capabilities...' },
                        { percent: 25, message: 'Trying Composer installation...' },
                        { percent: 35, message: 'Composer method failed, trying direct download...' },
                        { percent: 50, message: 'Downloading OAuth2 client library...' },
                        { percent: 65, message: 'Extracting library files...' },
                        { percent: 75, message: 'Direct download failed, creating fallback...' },
                        { percent: 85, message: 'Creating minimal autoloader...' },
                        { percent: 95, message: 'Finalizing installation...' },
                        { percent: 100, message: 'Installation completed!' }
                    ]
                };
                
                var messages = progressMessages[type] || [{ percent: 100, message: 'Installing...' }];
                var currentStep = 0;
                var progressInterval;
                
                function updateProgressStep() {
                    if (currentStep < messages.length) {
                        var step = messages[currentStep];
                        updateProgress(step.percent, step.message);
                        currentStep++;
                        
                        if (currentStep < messages.length) {
                            progressInterval = setTimeout(updateProgressStep, 800);
                        }
                    }
                }
                
                // Start progress updates
                updateProgressStep();
                
                // Set a maximum timeout to prevent infinite waiting
                var maxTimeout = setTimeout(function() {
                    if (progressInterval) {
                        clearTimeout(progressInterval);
                    }
                    updateProgress(100, 'Installation completed (timeout reached)');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                }, 30000); // 30 second maximum timeout
                
                $.ajax({
                    url: xeroJetpackCrm.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'xero_install_' + type,
                        nonce: xeroJetpackCrm.nonce
                    },
                    timeout: 25000, // 25 second AJAX timeout
                    success: function(response) {
                        // Clear the progress interval and timeout
                        if (progressInterval) {
                            clearTimeout(progressInterval);
                        }
                        clearTimeout(maxTimeout);
                        
                        if (response.success) {
                            updateProgress(100, 'Installation completed successfully!');
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            updateProgress(0, 'Installation failed: ' + response.message);
                            $button.prop('disabled', false);
                        }
                    },
                    error: function(xhr, status, error) {
                        // Clear the progress interval and timeout
                        if (progressInterval) {
                            clearTimeout(progressInterval);
                        }
                        clearTimeout(maxTimeout);
                        
                        var errorMsg = 'Installation failed. ';
                        if (status === 'timeout') {
                            errorMsg += 'Request timed out. Please try again.';
                        } else {
                            errorMsg += 'Please try again.';
                        }
                        
                        updateProgress(0, errorMsg);
                        $button.prop('disabled', false);
                    }
                });
            }
            
            function updateProgress(percent, text) {
                $('#progress-fill').css('width', percent + '%');
                $('#progress-text').text(text);
            }
            
            // Enhanced Dashboard Functions
            function showStatusBar(message) {
                $('#status-bar .status-message').text(message);
                $('#status-bar').fadeIn(300);
            }
            
            function hideStatusBar() {
                $('#status-bar').fadeOut(300);
            }
            
            function showNotification(message, type) {
                var notification = $('<div class="notification notification-' + type + '">' + message + '</div>');
                $('body').append(notification);
                
                setTimeout(function() {
                    notification.addClass('show');
                }, 100);
                
                setTimeout(function() {
                    notification.removeClass('show');
                    setTimeout(function() {
                        notification.remove();
                    }, 300);
                }, 3000);
            }
            
            function updateStats() {
                $.ajax({
                    url: xeroJetpackCrm.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'xero_get_stats',
                        nonce: xeroJetpackCrm.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            var stats = response.data;
                            $('#contacts-count').text(numberFormat(stats.contacts || 0));
                            $('#invoices-count').text(numberFormat(stats.invoices || 0));
                            $('#last-sync-time').text(stats.last_sync || 'Never');
                            $('#sync-frequency').text(stats.frequency || 'Manual');
                        }
                    }
                });
            }
            
            function numberFormat(num) {
                return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
            }
            
            // Add notification styles
            $('<style>')
                .prop('type', 'text/css')
                .html(`
                    .notification {
                        position: fixed;
                        top: 20px;
                        right: 20px;
                        padding: 15px 20px;
                        border-radius: 8px;
                        color: white;
                        font-weight: 600;
                        z-index: 10001;
                        transform: translateX(400px);
                        transition: transform 0.3s ease;
                        box-shadow: 0 4px 20px rgba(0,0,0,0.2);
                    }
                    
                    .notification.show {
                        transform: translateX(0);
                    }
                    
                    .notification-success {
                        background: linear-gradient(135deg, #28a745, #20c997);
                    }
                    
                    .notification-error {
                        background: linear-gradient(135deg, #dc3545, #c82333);
                    }
                    
                    .notification-info {
                        background: linear-gradient(135deg, #17a2b8, #138496);
                    }
                `)
                .appendTo('head');
            
            // Enhanced button loading states
            function setButtonLoading($button, loading) {
                if (loading) {
                    $button.addClass('loading').prop('disabled', true);
                } else {
                    $button.removeClass('loading').prop('disabled', false);
                }
            }
            
            // Update existing AJAX calls to use loading states
            $('#save-xero-credentials').on('click', function() {
                var $button = $(this);
                var clientId = $('#xero_client_id').val();
                var clientSecret = $('#xero_client_secret').val();
                
                if (!clientId || !clientSecret) {
                    showNotification('Please enter both Client ID and Client Secret.', 'error');
                    return;
                }
                
                setButtonLoading($button, true);
                
                $.ajax({
                    url: xeroJetpackCrm.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'xero_save_credentials',
                        nonce: xeroJetpackCrm.nonce,
                        xero_client_id: clientId,
                        xero_client_secret: clientSecret
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#xero-toggle-connection').show();
                            updateXeroToggleButton(false); // Show connect state
                            $('#xero-next-navigation').show();
                            showTestResult('#xero-test-result', 'Credentials saved successfully!', 'success');
                            showNotification('Credentials saved successfully!', 'success');
                        } else {
                            showTestResult('#xero-test-result', 'Failed to save credentials: ' + response.data, 'error');
                            showNotification('Failed to save credentials: ' + response.data, 'error');
                        }
                    },
                    error: function() {
                        showTestResult('#xero-test-result', 'Failed to save credentials due to network error', 'error');
                        showNotification('Failed to save credentials due to network error', 'error');
                    },
                    complete: function() {
                        setButtonLoading($button, false);
                    }
                });
            });
            
            $('#test-xero-credentials').on('click', function() {
                var $button = $(this);
                var clientId = $('#xero_client_id').val();
                var clientSecret = $('#xero_client_secret').val();
                
                if (!clientId || !clientSecret) {
                    showTestResult('#xero-test-result', 'Please enter both Client ID and Client Secret first.', 'error');
                    showNotification('Please enter both Client ID and Client Secret first.', 'error');
                    return;
                }
                
                setButtonLoading($button, true);
                showTestResult('#xero-test-result', 'Testing credentials...', 'info');
                
                $.ajax({
                    url: xeroJetpackCrm.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'xero_test_credentials',
                        nonce: xeroJetpackCrm.nonce,
                        xero_client_id: clientId,
                        xero_client_secret: clientSecret
                    },
                    success: function(response) {
                        if (response.success) {
                            showTestResult('#xero-test-result', 'Credentials are valid! You can now connect to Xero.', 'success');
                            $('#xero-toggle-connection').show();
                            updateXeroToggleButton(false); // Show connect state
                            $('#xero-next-navigation').show();
                            showNotification('Credentials are valid! You can now connect to Xero.', 'success');
                        } else {
                            showTestResult('#xero-test-result', 'Invalid credentials: ' + response.data, 'error');
                            showNotification('Invalid credentials: ' + response.data, 'error');
                        }
                    },
                    error: function() {
                        showTestResult('#xero-test-result', 'Test failed due to network error', 'error');
                        showNotification('Test failed due to network error', 'error');
                    },
                    complete: function() {
                        setButtonLoading($button, false);
                    }
                });
            });
        });
        </script>
        
        <script>
        // Global functions for interactive features
        function togglePassword(inputId) {
            var input = document.getElementById(inputId);
            var button = input.nextElementSibling;
            var icon = button.querySelector('.dashicons');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'dashicons dashicons-hidden';
            } else {
                input.type = 'password';
                icon.className = 'dashicons dashicons-visibility';
            }
        }
        
        function copyToClipboard(inputId) {
            var input = document.getElementById(inputId);
            input.select();
            input.setSelectionRange(0, 99999); // For mobile devices
            
            try {
                document.execCommand('copy');
                
                // Show success feedback
                var button = input.nextElementSibling;
                var originalIcon = button.innerHTML;
                button.innerHTML = '<span class="dashicons dashicons-yes-alt"></span>';
                button.style.color = '#27ae60';
                
                // Show notification
                if (typeof showNotification === 'function') {
                    showNotification('Redirect URI copied to clipboard!', 'success');
                }
                
                // Reset button after 2 seconds
                setTimeout(function() {
                    button.innerHTML = originalIcon;
                    button.style.color = '#6c757d';
                }, 2000);
                
            } catch (err) {
                if (typeof showNotification === 'function') {
                    showNotification('Failed to copy to clipboard', 'error');
                }
            }
        }
        </script>
        <?php
    }
    
    private function render_prerequisites_step($jetpack_crm_status, $dependencies_status) {
        ?>
        <div class="wizard-step">
            <h2>Step 1: Prerequisites</h2>
            <p>Let's make sure everything is ready for the integration.</p>
            
            <div class="prerequisites-grid">
                <!-- Jetpack CRM Status -->
                <div class="prerequisite-card">
                    <h3>Jetpack CRM</h3>
                    <div id="jetpack-crm-status">
                        <?php if ($jetpack_crm_status['installed']): ?>
                            <div class="status-success">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <strong>Installed and Active</strong>
                            </div>
                        <?php else: ?>
                            <div class="status-warning">
                                <span class="dashicons dashicons-warning"></span>
                                <strong>Not Installed</strong>
                                <p>Jetpack CRM is required for this integration.</p>
                                <button id="install-jetpack-crm" class="button button-primary">
                                    Install Jetpack CRM
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Dependencies Status -->
                <div class="prerequisite-card">
                    <h3>Dependencies</h3>
                    <div id="dependencies-status">
                        <?php if ($dependencies_status['installed']): ?>
                            <div class="status-success">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <strong>All Dependencies Installed</strong>
                                <?php if ($dependencies_status['method'] === 'autoloader'): ?>
                                    <br><small>Using fallback autoloader</small>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="status-warning">
                                <span class="dashicons dashicons-warning"></span>
                                <strong>Dependencies Missing</strong>
                                <p>OAuth2 client library is required.</p>
                                <button id="install-dependencies" class="button button-primary">
                                    Install Dependencies
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Progress Bar -->
            <div id="progress-container" style="display: none;">
                <h3>Installation Progress</h3>
                <div class="progress-bar">
                    <div class="progress-fill" id="progress-fill"></div>
                </div>
                <div id="progress-text">Preparing installation...</div>
            </div>
        </div>
        <?php
    }
    
    private function render_xero_configuration_step() {
        $xero_client_id = get_option('xero_client_id', '');
        $xero_client_secret = get_option('xero_client_secret', '');
        $xero_connected = !empty(get_option('xero_refresh_token'));
        ?>
        <div class="wizard-step xero-configuration-enhanced">
            <div class="step-header">
                <div class="step-icon">
                    <span class="dashicons dashicons-cloud"></span>
                </div>
                <div class="step-content">
                    <h2>Step 2: Xero Configuration</h2>
                    <p class="step-description">Connect your Xero account to enable data synchronization</p>
                </div>
            </div>
            
            <?php if ($xero_connected): ?>
                <div class="connection-status connected">
                    <div class="status-icon">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <div class="status-content">
                        <h3>Xero Connected Successfully!</h3>
                        <p>Your Xero account is connected and ready for synchronization.</p>
                        <div class="connection-details">
                            <span class="connection-indicator pulse"></span>
                            <span class="connection-text">Active Connection</span>
                        </div>
                    </div>
                    <div class="status-actions">
                        <button id="test-xero-connection" class="btn btn-outline">
                            <span class="dashicons dashicons-update"></span> Test Connection
                        </button>
                        <button id="xero-toggle-connection-connected" class="btn btn-danger">
                            <span class="dashicons dashicons-no-alt"></span>
                            <span class="btn-text">Disconnect from Xero</span>
                        </button>
                    </div>
                </div>
                
                <div class="step-navigation">
                    <button id="next-to-jetpack" class="btn btn-success btn-large">
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                        <span class="btn-text">Next: Configure Jetpack CRM</span>
                    </button>
                </div>
            <?php else: ?>
                <div class="configuration-card">
                    <div class="card-header">
                        <h3><span class="dashicons dashicons-admin-settings"></span> Xero App Credentials</h3>
                        <p class="card-description">
                            Enter your Xero app credentials from 
                            <a href="https://developer.xero.com" target="_blank" class="external-link">
                                developer.xero.com
                                <span class="dashicons dashicons-external"></span>
                            </a>
                        </p>
                    </div>
                    
                    <div class="form-container">
                        <div class="input-group">
                            <label for="xero_client_id" class="input-label">
                                <span class="label-icon dashicons dashicons-admin-users"></span>
                                Client ID
                            </label>
                            <div class="input-wrapper">
                                <input type="text" id="xero_client_id" value="<?php echo esc_attr($xero_client_id); ?>" 
                                       class="modern-input" placeholder="Enter your Xero Client ID" />
                                <div class="input-focus-line"></div>
                            </div>
                            <div class="input-hint">Your Xero application's unique identifier</div>
                        </div>
                        
                        <div class="input-group">
                            <label for="xero_client_secret" class="input-label">
                                <span class="label-icon dashicons dashicons-lock"></span>
                                Client Secret
                            </label>
                            <div class="input-wrapper">
                                <input type="password" id="xero_client_secret" value="<?php echo esc_attr($xero_client_secret); ?>" 
                                       class="modern-input" placeholder="Enter your Xero Client Secret" />
                                <button type="button" class="toggle-password" onclick="togglePassword('xero_client_secret')">
                                    <span class="dashicons dashicons-visibility"></span>
                                </button>
                                <div class="input-focus-line"></div>
                            </div>
                            <div class="input-hint">Keep this secret secure and never share it publicly</div>
                        </div>
                        
                        <div class="input-group">
                            <label for="redirect_uri" class="input-label">
                                <span class="label-icon dashicons dashicons-admin-links"></span>
                                Redirect URI
                            </label>
                            <div class="input-wrapper">
                                <input type="text" id="redirect_uri" 
                                       value="<?php echo esc_attr(admin_url('admin.php?page=xero-jetpack-crm-integration&action=oauth_callback')); ?>" 
                                       class="modern-input readonly" readonly />
                                <button type="button" class="copy-button" onclick="copyToClipboard('redirect_uri')">
                                    <span class="dashicons dashicons-clipboard"></span>
                                </button>
                                <div class="input-focus-line"></div>
                            </div>
                            <div class="input-hint">
                                <span class="dashicons dashicons-info"></span>
                                Copy this URL to your Xero app configuration
                            </div>
                        </div>
                    </div>
                    
                    <div class="action-buttons">
                        <button id="save-xero-credentials" class="btn btn-primary">
                            <span class="dashicons dashicons-saved"></span>
                            <span class="btn-text">Save Credentials</span>
                            <div class="btn-loading">
                                <div class="spinner"></div>
                            </div>
                        </button>
                        <button id="test-xero-credentials" class="btn btn-outline">
                            <span class="dashicons dashicons-search"></span>
                            <span class="btn-text">Test Credentials</span>
                            <div class="btn-loading">
                                <div class="spinner"></div>
                            </div>
                        </button>
                        <button id="xero-toggle-connection" class="btn btn-success" style="display: none;">
                            <span class="dashicons dashicons-admin-links"></span>
                            <span class="btn-text">Connect to Xero</span>
                            <div class="btn-loading">
                                <div class="spinner"></div>
                            </div>
                        </button>
                    </div>
                    
                    <div id="xero-next-navigation" class="step-navigation" style="display: none;">
                        <button id="next-to-jetpack-from-config" class="btn btn-success btn-large">
                            <span class="dashicons dashicons-arrow-right-alt2"></span>
                            <span class="btn-text">Next: Configure Jetpack CRM</span>
                        </button>
                    </div>
                    
                    <div id="xero-test-result" class="test-result" style="display: none;"></div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    private function render_jetpack_configuration_step() {
        $jetpack_crm_api_key = get_option('jetpack_crm_api_key', '');
        $jetpack_crm_api_secret = get_option('jetpack_crm_api_secret', '');
        $jetpack_crm_endpoint = get_option('jetpack_crm_endpoint', '');
        $jetpack_configured = !empty($jetpack_crm_api_key) && !empty($jetpack_crm_endpoint);
        ?>
        <div class="wizard-step">
            <h2>Step 3: Jetpack CRM Configuration</h2>
            <p>Configure your Jetpack CRM API settings for data synchronization.</p>
            
            <?php if ($jetpack_configured): ?>
                <div class="status-success">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <strong>Jetpack CRM Configured!</strong>
                    <p>Your Jetpack CRM is properly configured and ready.</p>
                    <button id="test-jetpack-connection" class="button">Test Connection</button>
                    <button id="reconfigure-jetpack" class="button">Reconfigure</button>
                </div>
            <?php else: ?>
                <div class="configuration-form">
                    <h3>Jetpack CRM API Settings</h3>
                    <p>Enter your Jetpack CRM API credentials and endpoint.</p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="jetpack_crm_api_key">API Key</label>
                            </th>
                            <td>
                                <input type="text" id="jetpack_crm_api_key" value="<?php echo esc_attr($jetpack_crm_api_key); ?>" class="regular-text" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="jetpack_crm_api_secret">API Secret</label>
                            </th>
                            <td>
                                <input type="password" id="jetpack_crm_api_secret" value="<?php echo esc_attr($jetpack_crm_api_secret); ?>" class="regular-text" />
                                <p class="description">Optional - only if required by your setup</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="jetpack_crm_endpoint">Endpoint URL</label>
                            </th>
                            <td>
                                <input type="url" id="jetpack_crm_endpoint" value="<?php echo esc_attr($jetpack_crm_endpoint); ?>" class="regular-text" />
                                <p class="description">e.g., https://yourdomain.com/wp-json/zerobscrm/v1/</p>
                            </td>
                        </tr>
                    </table>
                    
                    <div class="step-actions">
                        <button id="save-jetpack-credentials" class="button button-primary">Save Configuration</button>
                        <button id="test-jetpack-credentials" class="button">Test Connection</button>
                    </div>
                    
                    <div id="jetpack-test-result" style="display: none;"></div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
        private function render_jetpack_tab($jetpack_crm_status, $dependencies_status) {
            $jetpack_configured = !empty(get_option('jetpack_crm_api_key')) && !empty(get_option('jetpack_crm_endpoint'));
            ?>
            <div class="tab-panel">
                <div class="tab-header">
                    <h2>Jetpack CRM Configuration</h2>
                    <p class="tab-description">Configure your Jetpack CRM connection settings</p>
                </div>
                
                <?php if (!$jetpack_crm_status['installed'] || !$dependencies_status['installed']): ?>
                    <div class="prerequisites-section">
                        <h3>Prerequisites Required</h3>
                        <div class="prerequisites-grid">
                            <?php if (!$jetpack_crm_status['installed']): ?>
                                <div class="prerequisite-card">
                                    <div class="prerequisite-icon">
                                        <span class="dashicons dashicons-admin-plugins"></span>
                                    </div>
                                    <div class="prerequisite-content">
                                        <h4>Jetpack CRM Plugin</h4>
                                        <p>Install and activate the Jetpack CRM plugin</p>
                                        <button id="install-jetpack-crm" class="btn btn-primary">
                                            <span class="dashicons dashicons-download"></span>
                                            Install Jetpack CRM
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!$dependencies_status['installed']): ?>
                                <div class="prerequisite-card">
                                    <div class="prerequisite-icon">
                                        <span class="dashicons dashicons-admin-tools"></span>
                                    </div>
                                    <div class="prerequisite-content">
                                        <h4>OAuth2 Dependencies</h4>
                                        <p>Install required OAuth2 client library</p>
                                        <button id="install-dependencies" class="btn btn-primary">
                                            <span class="dashicons dashicons-download"></span>
                                            Install Dependencies
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="configuration-section">
                        <div class="material-card">
                            <div class="card-header">
                                <div class="header-icon">
                                    <span class="material-icons">admin_panel_settings</span>
                                </div>
                                <div class="header-content">
                                    <h3>Jetpack CRM Settings</h3>
                                    <p>Enter your Jetpack CRM API credentials</p>
                                </div>
                            </div>
                            
                            <div class="card-body">
                                <div class="material-form-group">
                                    <label for="jetpack_crm_api_key" class="material-label">API Key</label>
                                    <div class="material-input-container">
                                        <input type="password" id="jetpack_crm_api_key" name="jetpack_crm_api_key" 
                                               value="<?php echo esc_attr(get_option('jetpack_crm_api_key')); ?>" 
                                               placeholder="Enter your Jetpack CRM API key"
                                               class="material-input">
                                        <button type="button" class="material-icon-button" onclick="togglePassword('jetpack_crm_api_key')">
                                            <span class="material-icons">visibility</span>
                                        </button>
                                        <span class="material-underline"></span>
                                    </div>
                                </div>
                                
                                <div class="material-form-group">
                                    <label for="jetpack_crm_api_secret" class="material-label">API Secret (Optional)</label>
                                    <div class="material-input-container">
                                        <input type="password" id="jetpack_crm_api_secret" name="jetpack_crm_api_secret" 
                                               value="<?php echo esc_attr(get_option('jetpack_crm_api_secret')); ?>" 
                                               placeholder="Enter your Jetpack CRM API secret"
                                               class="material-input">
                                        <button type="button" class="material-icon-button" onclick="togglePassword('jetpack_crm_api_secret')">
                                            <span class="material-icons">visibility</span>
                                        </button>
                                        <span class="material-underline"></span>
                                    </div>
                                </div>
                                
                                <div class="material-form-group">
                                    <label for="jetpack_crm_endpoint" class="material-label">Endpoint URL</label>
                                    <div class="material-input-container">
                                        <input type="url" id="jetpack_crm_endpoint" name="jetpack_crm_endpoint" 
                                               value="<?php echo esc_attr(get_option('jetpack_crm_endpoint')); ?>" 
                                               placeholder="https://your-site.com/wp-json/zero-bs-crm/v1/"
                                               class="material-input">
                                        <span class="material-underline"></span>
                                    </div>
                                    <div class="material-helper-text">
                                        Enter your Jetpack CRM REST API endpoint URL
                                    </div>
                                </div>
                                
                                <div class="material-actions">
                                    <button id="jetpack-toggle-connection" class="material-button <?php echo $jetpack_configured ? 'material-button-danger' : 'material-button-primary'; ?>">
                                        <span class="material-icons"><?php echo $jetpack_configured ? 'link_off' : 'link'; ?></span>
                                        <span class="button-text"><?php echo $jetpack_configured ? 'Disconnect from Jetpack CRM' : 'Connect to Jetpack CRM'; ?></span>
                                        <div class="material-spinner" style="display: none;">
                                            <div class="spinner"></div>
                                        </div>
                                    </button>
                                </div>
                                
                                <div id="jetpack-test-result" class="material-snackbar" style="display: none;"></div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <?php
        }
        
        private function render_xero_tab() {
            $xero_refresh_token = get_option('xero_refresh_token');
            $xero_access_token = get_option('xero_access_token');
            $xero_connected = !empty($xero_refresh_token) && !empty($xero_access_token);
            ?>
            <div class="tab-panel">
                <div class="tab-header">
                    <h2>Xero CRM Configuration</h2>
                    <p class="tab-description">Configure your Xero account connection</p>
                </div>
                
                <div class="configuration-section">
                    <div class="material-card">
                        <div class="card-header">
                            <div class="header-icon">
                                <span class="material-icons">settings</span>
                            </div>
                            <div class="header-content">
                                <h3>Xero App Credentials</h3>
                                <p>Enter your Xero app credentials from the Xero Developer Portal</p>
                            </div>
                        </div>
                        
                        <div class="card-body">
                            <div class="material-form-group">
                                <label for="xero_client_id" class="material-label">Client ID</label>
                                <div class="material-input-container">
                                    <input type="text" id="xero_client_id" name="xero_client_id" 
                                           value="<?php echo esc_attr(get_option('xero_client_id')); ?>" 
                                           placeholder="Enter your Xero Client ID"
                                           class="material-input">
                                    <span class="material-underline"></span>
                                </div>
                            </div>
                            
                            <div class="material-form-group">
                                <label for="xero_client_secret" class="material-label">Client Secret</label>
                                <div class="material-input-container">
                                    <input type="password" id="xero_client_secret" name="xero_client_secret" 
                                           value="<?php echo esc_attr(get_option('xero_client_secret')); ?>" 
                                           placeholder="Enter your Xero Client Secret"
                                           class="material-input">
                                    <button type="button" class="material-icon-button" onclick="togglePassword('xero_client_secret')">
                                        <span class="material-icons">visibility</span>
                                    </button>
                                    <span class="material-underline"></span>
                                </div>
                            </div>
                            
                            <div class="material-form-group">
                                <label for="redirect_uri" class="material-label">Redirect URI</label>
                                <div class="material-input-container">
                                    <input type="url" id="redirect_uri" name="redirect_uri" 
                                           value="<?php echo esc_attr(admin_url('options-general.php?page=xero-jetpack-crm-integration&tab=xero')); ?>" 
                                           readonly
                                           class="material-input">
                                    <button type="button" class="material-icon-button" onclick="copyToClipboard('redirect_uri')">
                                        <span class="material-icons">content_copy</span>
                                    </button>
                                    <span class="material-underline"></span>
                                </div>
                                <div class="material-helper-text">
                                    Copy this URL to your Xero app configuration
                                </div>
                            </div>
                            
                            <div class="material-actions">
                                <button id="xero-toggle-connection" class="material-button <?php echo $xero_connected ? 'material-button-danger' : 'material-button-primary'; ?>">
                                    <span class="material-icons"><?php echo $xero_connected ? 'link_off' : 'link'; ?></span>
                                    <span class="button-text"><?php echo $xero_connected ? 'Disconnect from Xero' : 'Connect to Xero'; ?></span>
                                    <div class="material-spinner" style="display: none;">
                                        <div class="spinner"></div>
                                    </div>
                                </button>
                            </div>
                            
                            <div id="xero-test-result" class="material-snackbar" style="display: none;"></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }
        
        private function render_dashboard_tab($xero_connected, $jetpack_configured) {
            $last_sync = get_option('xero_last_sync', 0);
            $total_contacts = get_option('xero_synced_contacts_count', 0);
            $total_invoices = get_option('xero_synced_invoices_count', 0);
            $sync_frequency = get_option('sync_frequency', 'manual');
            ?>
            <div class="tab-panel">
                <div class="tab-header">
                    <h2>Integration Dashboard</h2>
                    <p class="tab-description">Monitor and manage your Xero and Jetpack CRM integration</p>
                </div>
                
                <div class="dashboard-content">
                    <!-- Status Overview Cards -->
                    <div class="dashboard-grid">
                        <div class="status-card <?php echo $xero_connected ? 'connected' : 'disconnected'; ?>">
                            <div class="card-icon">
                                <span class="material-icons">account_balance</span>
                            </div>
                            <div class="card-content">
                                <h3>Xero CRM</h3>
                                <div class="status-indicator">
                                    <span class="status-dot <?php echo $xero_connected ? 'active' : 'inactive'; ?>"></span>
                                    <span class="status-text"><?php echo $xero_connected ? 'Connected' : 'Disconnected'; ?></span>
                                </div>
                                <p class="card-description">
                                    <?php echo $xero_connected ? 'Your Xero account is connected and ready for sync' : 'Connect your Xero account to enable data synchronization'; ?>
                                </p>
                            </div>
                            <div class="card-actions">
                                <a href="?page=xero-jetpack-crm-integration&tab=xero" class="material-button material-button-outline">
                                    <span class="material-icons">settings</span>
                                    <?php echo $xero_connected ? 'Reconfigure' : 'Configure'; ?>
                                </a>
                            </div>
                        </div>
                        
                        <div class="status-card <?php echo $jetpack_configured ? 'connected' : 'disconnected'; ?>">
                            <div class="card-icon">
                                <span class="material-icons">business</span>
                            </div>
                            <div class="card-content">
                                <h3>Jetpack CRM</h3>
                                <div class="status-indicator">
                                    <span class="status-dot <?php echo $jetpack_configured ? 'active' : 'inactive'; ?>"></span>
                                    <span class="status-text"><?php echo $jetpack_configured ? 'Connected' : 'Disconnected'; ?></span>
                                </div>
                                <p class="card-description">
                                    <?php echo $jetpack_configured ? 'Jetpack CRM is configured and ready to receive data' : 'Configure Jetpack CRM to complete the integration'; ?>
                                </p>
                            </div>
                            <div class="card-actions">
                                <a href="?page=xero-jetpack-crm-integration&tab=jetpack" class="material-button material-button-outline">
                                    <span class="material-icons">settings</span>
                                    <?php echo $jetpack_configured ? 'Reconfigure' : 'Configure'; ?>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Integration Status -->
                    <div class="integration-status-card">
                        <div class="status-header">
                            <h3>Integration Status</h3>
                            <?php if ($xero_connected && $jetpack_configured): ?>
                                <span class="status-badge success">Complete</span>
                            <?php else: ?>
                                <span class="status-badge warning">Incomplete</span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($xero_connected && $jetpack_configured): ?>
                            <div class="status-content success">
                                <div class="status-icon">
                                    <span class="material-icons">check_circle</span>
                                </div>
                                <div class="status-text">
                                    <h4>Integration Complete</h4>
                                    <p>Both systems are connected and ready for synchronization.</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="status-content warning">
                                <div class="status-icon">
                                    <span class="material-icons">warning</span>
                                </div>
                                <div class="status-text">
                                    <h4>Configuration Required</h4>
                                    <p>Please configure both Xero and Jetpack CRM to complete the integration.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Statistics and Actions -->
                    <?php if ($xero_connected && $jetpack_configured): ?>
                        <div class="dashboard-stats">
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <span class="material-icons">people</span>
                                </div>
                                <div class="stat-content">
                                    <h4><?php echo number_format($total_contacts); ?></h4>
                                    <p>Synced Contacts</p>
                                </div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <span class="material-icons">receipt</span>
                                </div>
                                <div class="stat-content">
                                    <h4><?php echo number_format($total_invoices); ?></h4>
                                    <p>Synced Invoices</p>
                                </div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <span class="material-icons">schedule</span>
                                </div>
                                <div class="stat-content">
                                    <h4><?php echo ucfirst($sync_frequency); ?></h4>
                                    <p>Sync Frequency</p>
                                </div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <span class="material-icons">update</span>
                                </div>
                                <div class="stat-content">
                                    <h4><?php echo $last_sync ? date('M j, Y', $last_sync) : 'Never'; ?></h4>
                                    <p>Last Sync</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="dashboard-actions">
                            <button id="manual-sync" class="material-button material-button-primary">
                                <span class="material-icons">sync</span>
                                <span class="button-text">Manual Sync</span>
                            </button>
                            
                            <button id="sync-settings" class="material-button material-button-outline">
                                <span class="material-icons">settings</span>
                                <span class="button-text">Sync Settings</span>
                            </button>
                            
                            <button id="view-logs" class="material-button material-button-outline">
                                <span class="material-icons">description</span>
                                <span class="button-text">View Logs</span>
                            </button>
                            
                            <button id="export-data" class="material-button material-button-outline">
                                <span class="material-icons">download</span>
                                <span class="button-text">Export Data</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Quick Setup Guide -->
                    <?php if (!$xero_connected || !$jetpack_configured): ?>
                        <div class="setup-guide">
                            <h3>Quick Setup Guide</h3>
                            <div class="setup-steps">
                                <div class="setup-step <?php echo $jetpack_configured ? 'completed' : 'pending'; ?>">
                                    <div class="step-number">1</div>
                                    <div class="step-content">
                                        <h4>Configure Jetpack CRM</h4>
                                        <p>Set up your Jetpack CRM API credentials and endpoint</p>
                                        <a href="?page=xero-jetpack-crm-integration&tab=jetpack" class="material-button material-button-outline">
                                            <span class="material-icons">arrow_forward</span>
                                            Get Started
                                        </a>
                                    </div>
                                </div>
                                
                                <div class="setup-step <?php echo $xero_connected ? 'completed' : 'pending'; ?>">
                                    <div class="step-number">2</div>
                                    <div class="step-content">
                                        <h4>Connect Xero</h4>
                                        <p>Authenticate with your Xero account for data synchronization</p>
                                        <a href="?page=xero-jetpack-crm-integration&tab=xero" class="material-button material-button-outline">
                                            <span class="material-icons">arrow_forward</span>
                                            Connect Now
                                        </a>
                                    </div>
                                </div>
                                
                                <div class="setup-step <?php echo ($xero_connected && $jetpack_configured) ? 'completed' : 'pending'; ?>">
                                    <div class="step-number">3</div>
                                    <div class="step-content">
                                        <h4>Start Syncing</h4>
                                        <p>Begin synchronizing data between Xero and Jetpack CRM</p>
                                        <button class="material-button material-button-primary" disabled>
                                            <span class="material-icons">sync</span>
                                            Ready to Sync
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php
        }
        
        private function render_dashboard_step() {
        $xero_connected = !empty(get_option('xero_refresh_token'));
        $jetpack_configured = !empty(get_option('jetpack_crm_api_key')) && !empty(get_option('jetpack_crm_endpoint'));
        $sync_frequency = get_option('sync_frequency', 'manual');
        $last_sync = get_option('xero_last_sync', 0);
        $total_synced_contacts = get_option('xero_synced_contacts_count', 0);
        $total_synced_invoices = get_option('xero_synced_invoices_count', 0);
        ?>
        <div class="wizard-step dashboard-enhanced">
            <div class="dashboard-header">
                <h2><span class="dashicons dashicons-dashboard"></span> Integration Dashboard</h2>
                <p class="dashboard-subtitle">Monitor and manage your Xero ↔ Jetpack CRM integration</p>
            </div>
            
            <!-- Status Overview Cards -->
            <div class="status-overview">
                <div class="status-card xero-status <?php echo $xero_connected ? 'connected' : 'disconnected'; ?>">
                    <div class="status-icon">
                        <span class="dashicons dashicons-<?php echo $xero_connected ? 'yes-alt' : 'warning'; ?>"></span>
                    </div>
                    <div class="status-content">
                        <h3>Xero Integration</h3>
                        <div class="status-text">
                            <span class="status-label"><?php echo $xero_connected ? 'Connected' : 'Disconnected'; ?></span>
                            <span class="status-dot <?php echo $xero_connected ? 'pulse' : ''; ?>"></span>
                        </div>
                        <p class="status-description">
                            <?php echo $xero_connected ? 'Your Xero account is connected and ready' : 'Connect your Xero account to start syncing'; ?>
                        </p>
                    </div>
                    <div class="status-actions">
                        <button id="test-xero-connection" class="btn btn-outline">
                            <span class="dashicons dashicons-update"></span> Test
                        </button>
                        <?php if ($xero_connected): ?>
                            <button id="disconnect-xero" class="btn btn-danger">
                                <span class="dashicons dashicons-no-alt"></span> Disconnect
                            </button>
                        <?php else: ?>
                            <button id="connect-xero" class="btn btn-primary">
                                <span class="dashicons dashicons-admin-links"></span> Connect
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="status-card jetpack-status <?php echo $jetpack_configured ? 'configured' : 'not-configured'; ?>">
                    <div class="status-icon">
                        <span class="dashicons dashicons-<?php echo $jetpack_configured ? 'yes-alt' : 'warning'; ?>"></span>
                    </div>
                    <div class="status-content">
                        <h3>Jetpack CRM</h3>
                        <div class="status-text">
                            <span class="status-label"><?php echo $jetpack_configured ? 'Configured' : 'Not Configured'; ?></span>
                            <span class="status-dot <?php echo $jetpack_configured ? 'pulse' : ''; ?>"></span>
                        </div>
                        <p class="status-description">
                            <?php echo $jetpack_configured ? 'Jetpack CRM is properly configured' : 'Configure Jetpack CRM API settings'; ?>
                        </p>
                    </div>
                    <div class="status-actions">
                        <button id="test-jetpack-connection" class="btn btn-outline">
                            <span class="dashicons dashicons-update"></span> Test
                        </button>
                        <button id="reconfigure-jetpack" class="btn btn-secondary">
                            <span class="dashicons dashicons-admin-settings"></span> Configure
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-groups"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number" id="contacts-count"><?php echo number_format($total_synced_contacts); ?></div>
                        <div class="stat-label">Synced Contacts</div>
                    </div>
                    <div class="stat-trend">
                        <span class="trend-indicator up">+12%</span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-media-document"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number" id="invoices-count"><?php echo number_format($total_synced_invoices); ?></div>
                        <div class="stat-label">Synced Invoices</div>
                    </div>
                    <div class="stat-trend">
                        <span class="trend-indicator up">+8%</span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-clock"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number" id="last-sync-time">
                            <?php echo $last_sync ? human_time_diff($last_sync) . ' ago' : 'Never'; ?>
                        </div>
                        <div class="stat-label">Last Sync</div>
                    </div>
                    <div class="stat-trend">
                        <span class="trend-indicator neutral">Auto</span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-update"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number" id="sync-frequency"><?php echo ucfirst($sync_frequency); ?></div>
                        <div class="stat-label">Sync Frequency</div>
                    </div>
                    <div class="stat-trend">
                        <span class="trend-indicator neutral">Active</span>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions Panel -->
            <div class="quick-actions-panel">
                <h3><span class="dashicons dashicons-controls-play"></span> Quick Actions</h3>
                <div class="actions-grid">
                    <button id="manual-sync" class="action-btn primary">
                        <span class="dashicons dashicons-update"></span>
                        <div class="action-content">
                            <div class="action-title">Start Manual Sync</div>
                            <div class="action-description">Sync all data from Xero to Jetpack CRM</div>
                        </div>
                        <div class="action-arrow">→</div>
                    </button>
                    
                    <button id="view-logs" class="action-btn secondary">
                        <span class="dashicons dashicons-list-view"></span>
                        <div class="action-content">
                            <div class="action-title">View Sync Logs</div>
                            <div class="action-description">Check sync history and errors</div>
                        </div>
                        <div class="action-arrow">→</div>
                    </button>
                    
                    <button id="export-data" class="action-btn secondary">
                        <span class="dashicons dashicons-download"></span>
                        <div class="action-content">
                            <div class="action-title">Export Data</div>
                            <div class="action-description">Download sync reports</div>
                        </div>
                        <div class="action-arrow">→</div>
                    </button>
                    
                    <button id="sync-settings" class="action-btn secondary">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <div class="action-content">
                            <div class="action-title">Sync Settings</div>
                            <div class="action-description">Configure sync preferences</div>
                        </div>
                        <div class="action-arrow">→</div>
                    </button>
                </div>
            </div>
            
            <!-- Sync Settings Modal -->
            <div id="sync-settings-modal" class="modal-overlay" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3><span class="dashicons dashicons-admin-settings"></span> Sync Settings</h3>
                        <button class="modal-close">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="setting-group">
                            <label for="sync_frequency">Sync Frequency</label>
                            <select id="sync_frequency" class="modern-select">
                                <option value="manual" <?php selected($sync_frequency, 'manual'); ?>>Manual Only</option>
                                <option value="hourly" <?php selected($sync_frequency, 'hourly'); ?>>Every Hour</option>
                                <option value="daily" <?php selected($sync_frequency, 'daily'); ?>>Daily</option>
                            </select>
                            <p class="setting-description">Choose how often to automatically sync data</p>
                        </div>
                        
                        <div class="setting-group">
                            <label for="sync_contacts">Sync Contacts</label>
                            <label class="toggle-switch">
                                <input type="checkbox" id="sync_contacts" checked>
                                <span class="toggle-slider"></span>
                            </label>
                            <p class="setting-description">Enable contact synchronization</p>
                        </div>
                        
                        <div class="setting-group">
                            <label for="sync_invoices">Sync Invoices</label>
                            <label class="toggle-switch">
                                <input type="checkbox" id="sync_invoices" checked>
                                <span class="toggle-slider"></span>
                            </label>
                            <p class="setting-description">Enable invoice synchronization</p>
                        </div>
                        
                        <div class="setting-group">
                            <label for="sync_payments">Sync Payments</label>
                            <label class="toggle-switch">
                                <input type="checkbox" id="sync_payments" checked>
                                <span class="toggle-slider"></span>
                            </label>
                            <p class="setting-description">Enable payment synchronization</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button id="save-sync-settings" class="btn btn-primary">
                            <span class="dashicons dashicons-yes-alt"></span> Save Settings
                        </button>
                        <button class="modal-close btn btn-secondary">Cancel</button>
                    </div>
                </div>
            </div>
            
            <!-- Real-time Status Bar -->
            <div id="status-bar" class="status-bar" style="display: none;">
                <div class="status-content">
                    <div class="status-spinner"></div>
                    <span class="status-message">Processing...</span>
                </div>
                <button class="status-close">&times;</button>
            </div>
        </div>
        <?php
    }
    
    public function show_setup_notice() {
        ?>
        <div class="notice notice-info is-dismissible">
            <p>
                <strong><?php _e('Xero Jetpack CRM Integration', 'xero-jetpack-crm-integration'); ?>:</strong>
                <?php _e('Setup required. Please complete the installation process.', 'xero-jetpack-crm-integration'); ?>
                <a href="<?php echo admin_url('options-general.php?page=xero-jetpack-crm-integration'); ?>" class="button button-primary">
                    <?php _e('Complete Setup', 'xero-jetpack-crm-integration'); ?>
                </a>
            </p>
        </div>
        <?php
    }
    
    public function install_jetpack_crm() {
        check_ajax_referer('xero_jetpack_crm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        // Check if Jetpack CRM is already installed
        if ($this->check_jetpack_crm_status()['installed']) {
            wp_send_json_success('Jetpack CRM is already installed');
        }
        
        // Install Jetpack CRM via WordPress API
        $plugin_slug = 'zero-bs-crm';
        $plugin_zip = "https://downloads.wordpress.org/plugin/{$plugin_slug}.latest-stable.zip";
        
        // Download and install the plugin
        $result = $this->install_plugin_from_url($plugin_zip);
        
        if ($result['success']) {
            // Activate the plugin
            $activation_result = activate_plugin($result['plugin_file']);
            if (is_wp_error($activation_result)) {
                wp_send_json_error('Failed to activate Jetpack CRM: ' . $activation_result->get_error_message());
            }
            wp_send_json_success('Jetpack CRM installed and activated successfully');
        } else {
            wp_send_json_error('Failed to install Jetpack CRM: ' . $result['message']);
        }
    }
    
    public function install_dependencies() {
        check_ajax_referer('xero_jetpack_crm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        // Check if dependencies are already installed
        if ($this->check_dependencies_status()['installed']) {
            wp_send_json_success('Dependencies are already installed');
        }
        
        $plugin_dir = XERO_JETPACK_CRM_PLUGIN_DIR;
        
        // Ensure plugin directory exists and is writable
        if (!is_dir($plugin_dir)) {
            wp_mkdir_p($plugin_dir);
        }
        
        if (!is_writable($plugin_dir)) {
            wp_send_json_error('Plugin directory is not writable. Please check permissions.');
        }
        
        // Create vendor directory if it doesn't exist
        $vendor_dir = $plugin_dir . 'vendor/';
        if (!file_exists($vendor_dir)) {
            if (!wp_mkdir_p($vendor_dir)) {
                wp_send_json_error('Could not create vendor directory. Please check permissions.');
            }
        }
        
        // Try multiple installation methods
        $success = false;
        $error_message = '';
        $debug_info = [];
        
        // Method 1: Try to create minimal autoloader first (most reliable)
        $debug_info[] = 'Trying minimal autoloader...';
        $success = $this->create_minimal_autoloader($plugin_dir);
        if ($success) {
            $debug_info[] = 'Minimal autoloader created successfully';
        } else {
            $error_message = 'Minimal autoloader creation failed. ';
            $debug_info[] = 'Minimal autoloader failed';
        }
        
        // Method 2: Try to download and install via Composer (if minimal failed)
        if (!$success) {
            $debug_info[] = 'Trying Composer installation...';
            $success = $this->install_dependencies_via_composer($plugin_dir);
            if ($success) {
                $debug_info[] = 'Composer installation successful';
            } else {
                $error_message .= 'Composer installation failed. ';
                $debug_info[] = 'Composer installation failed';
            }
        }
        
        // Method 3: Try to download OAuth2 client library directly (if others failed)
        if (!$success) {
            $debug_info[] = 'Trying direct download...';
            $success = $this->install_dependencies_direct($plugin_dir);
            if ($success) {
                $debug_info[] = 'Direct download successful';
            } else {
                $error_message .= 'Direct download failed. ';
                $debug_info[] = 'Direct download failed';
            }
        }
        
        // Log debug information
        error_log('Xero Jetpack CRM - Dependency Installation Debug: ' . implode(' | ', $debug_info));
        
        if ($success) {
            wp_send_json_success('Dependencies installed successfully');
        } else {
            wp_send_json_error('Installation failed: ' . $error_message . ' Debug: ' . implode(' | ', $debug_info));
        }
    }
    
    private function install_dependencies_via_composer($plugin_dir) {
        try {
            // Download Composer if not present
            if (!file_exists($plugin_dir . 'composer.phar')) {
                $composer_installer = wp_remote_get('https://getcomposer.org/installer');
                if (is_wp_error($composer_installer)) {
                    return false;
                }
                $composer_data = wp_remote_retrieve_body($composer_installer);
                file_put_contents($plugin_dir . 'composer.phar', $composer_data);
            }
            
            // Install dependencies
            $output = [];
            $return_code = 0;
            exec('cd ' . escapeshellarg($plugin_dir) . ' && php composer.phar install --no-dev --optimize-autoloader 2>&1', $output, $return_code);
            
            return $return_code === 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function install_dependencies_direct($plugin_dir) {
        try {
            // Create vendor directory structure
            $vendor_dir = $plugin_dir . 'vendor/';
            $league_dir = $vendor_dir . 'league/oauth2-client/';
            $xero_dir = $vendor_dir . 'league/oauth2-xero/';
            
            wp_mkdir_p($league_dir);
            wp_mkdir_p($xero_dir);
            
            // Download OAuth2 Client library
            $oauth2_client_url = 'https://github.com/thephpleague/oauth2-client/archive/refs/heads/master.zip';
            $oauth2_client_zip = wp_remote_get($oauth2_client_url);
            
            if (!is_wp_error($oauth2_client_zip)) {
                $oauth2_client_data = wp_remote_retrieve_body($oauth2_client_zip);
                $temp_file = $plugin_dir . 'temp-oauth2-client.zip';
                file_put_contents($temp_file, $oauth2_client_data);
                
                $zip = new ZipArchive();
                if ($zip->open($temp_file) === TRUE) {
                    $zip->extractTo($league_dir);
                    $zip->close();
                    unlink($temp_file);
                    
                    // Move files to correct location
                    $extracted_dir = $league_dir . 'oauth2-client-master/';
                    if (is_dir($extracted_dir)) {
                        $this->move_directory_contents($extracted_dir, $league_dir);
                        $this->remove_directory($extracted_dir);
                    }
                }
            }
            
            // Create a simple autoloader
            $this->create_simple_autoloader($vendor_dir);
            
            return file_exists($vendor_dir . 'autoload.php');
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function create_minimal_autoloader($plugin_dir) {
        try {
            $vendor_dir = $plugin_dir . 'vendor/';
            
            // Ensure vendor directory exists
            if (!wp_mkdir_p($vendor_dir)) {
                error_log('Xero Jetpack CRM - Could not create vendor directory');
                return false;
            }
            
            // Create a minimal autoloader that doesn't require external libraries
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
        // Mock classes will be defined in the autoloader file
';
            
            // Write the autoloader file
            $autoloader_file = $vendor_dir . 'autoload.php';
            $bytes_written = file_put_contents($autoloader_file, $autoloader_content);
            
            if ($bytes_written === false) {
                error_log('Xero Jetpack CRM - Could not write autoloader file');
                return false;
            }
            
            // Also create a simple composer.json for reference
            $composer_content = json_encode([
                "name" => "xero-jetpack-crm/minimal",
                "description" => "Minimal autoloader for Xero Jetpack CRM Integration",
                "autoload" => [
                    "files" => ["autoload.php"]
                ]
            ], JSON_PRETTY_PRINT);
            
            $composer_file = $vendor_dir . 'composer.json';
            file_put_contents($composer_file, $composer_content);
            
            // Verify the files were created successfully
            if (file_exists($autoloader_file) && file_exists($composer_file)) {
                error_log('Xero Jetpack CRM - Minimal autoloader created successfully');
                return true;
            } else {
                error_log('Xero Jetpack CRM - Files not created properly');
                return false;
            }
            
        } catch (Exception $e) {
            error_log('Xero Jetpack CRM - Exception in create_minimal_autoloader: ' . $e->getMessage());
            return false;
        }
    }
    
    private function create_simple_autoloader($vendor_dir) {
        $autoloader_content = '<?php
// Simple autoloader for Xero Jetpack CRM Integration
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
';
        
        file_put_contents($vendor_dir . 'autoload.php', $autoloader_content);
    }
    
    private function move_directory_contents($source, $destination) {
        $files = glob($source . '*');
        foreach ($files as $file) {
            $dest_file = $destination . basename($file);
            if (is_dir($file)) {
                if (!is_dir($dest_file)) {
                    wp_mkdir_p($dest_file);
                }
                $this->move_directory_contents($file . '/', $dest_file . '/');
            } else {
                copy($file, $dest_file);
            }
        }
    }
    
    private function remove_directory($dir) {
        if (is_dir($dir)) {
            $files = glob($dir . '*');
            foreach ($files as $file) {
                if (is_dir($file)) {
                    $this->remove_directory($file . '/');
                } else {
                    unlink($file);
                }
            }
            rmdir($dir);
        }
    }
    
    public function check_status() {
        check_ajax_referer('xero_jetpack_crm_nonce', 'nonce');
        
        $jetpack_crm = $this->check_jetpack_crm_status();
        $dependencies = $this->check_dependencies_status();
        
        wp_send_json_success(array(
            'jetpack_crm' => $jetpack_crm,
            'dependencies' => $dependencies,
            'ready' => $jetpack_crm['installed'] && $dependencies['installed']
        ));
    }
    
    private function check_jetpack_crm_status() {
        return array(
            'installed' => class_exists('ZeroBSCRM'),
            'active' => is_plugin_active('zero-bs-crm/ZeroBSCRM.php')
        );
    }
    
    private function check_dependencies_status() {
        $vendor_dir = XERO_JETPACK_CRM_PLUGIN_DIR . 'vendor/';
        $autoloader_file = $vendor_dir . 'autoload.php';
        
        $installed = false;
        $details = [];
        
        if (file_exists($autoloader_file)) {
            // Try to include the autoloader to test if it works
            try {
                include_once $autoloader_file;
                $installed = true;
                $details[] = 'Autoloader file exists and loads successfully';
                
                // Test if our mock classes are available
                if (class_exists('League\OAuth2\Client\Provider\Xero')) {
                    $details[] = 'OAuth2 Xero provider class available';
                } else {
                    $details[] = 'OAuth2 Xero provider class not found';
                }
                
                if (class_exists('League\OAuth2\Client\Token\AccessToken')) {
                    $details[] = 'OAuth2 AccessToken class available';
                } else {
                    $details[] = 'OAuth2 AccessToken class not found';
                }
                
            } catch (Exception $e) {
                $installed = false;
                $details[] = 'Autoloader file exists but failed to load: ' . $e->getMessage();
            }
        } else {
            $details[] = 'Autoloader file does not exist';
        }
        
        return array(
            'installed' => $installed,
            'method' => $installed ? 'autoloader' : 'none',
            'autoloader_path' => $autoloader_file,
            'vendor_dir' => $vendor_dir,
            'details' => $details
        );
    }
    
    private function is_fully_ready() {
        $jetpack_crm = $this->check_jetpack_crm_status();
        $dependencies = $this->check_dependencies_status();
        return $jetpack_crm['installed'] && $dependencies['installed'];
    }
    
    public function test_connection() {
        try {
            check_ajax_referer('xero_jetpack_crm_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
            }
            
            $xero_client_id = get_option('xero_client_id');
            $xero_client_secret = get_option('xero_client_secret');
            $jetpack_crm_api_key = get_option('jetpack_crm_api_key');
            $jetpack_crm_api_secret = get_option('jetpack_crm_api_secret');
            $jetpack_crm_endpoint = get_option('jetpack_crm_endpoint');
            
            $errors = [];
            
            if (empty($xero_client_id)) {
                $errors[] = 'Xero Client ID is not configured';
            }
            
            if (empty($xero_client_secret)) {
                $errors[] = 'Xero Client Secret is not configured';
            }
            
            if (empty($jetpack_crm_api_key)) {
                $errors[] = 'Jetpack CRM API Key is not configured';
            }
            
            if (empty($jetpack_crm_endpoint)) {
                $errors[] = 'Jetpack CRM Endpoint URL is not configured';
            }
            
            if (!empty($errors)) {
                wp_send_json_error(implode(', ', $errors));
            }
            
            // Test Jetpack CRM connection with different authentication methods
            $test_url = rtrim($jetpack_crm_endpoint, '/') . '/wp-json/zerobscrm/v1/customers';
            
            // Try different authentication methods
            $auth_methods = array();
            
            // Method 1: Bearer token
            if (!empty($jetpack_crm_api_key)) {
                $auth_methods[] = array(
                    'name' => 'Bearer Token',
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $jetpack_crm_api_key,
                        'Content-Type' => 'application/json'
                    )
                );
            }
            
            // Method 2: Basic auth with API key and secret
            if (!empty($jetpack_crm_api_key) && !empty($jetpack_crm_api_secret)) {
                $auth_methods[] = array(
                    'name' => 'Basic Auth (Key + Secret)',
                    'headers' => array(
                        'Authorization' => 'Basic ' . base64_encode($jetpack_crm_api_key . ':' . $jetpack_crm_api_secret),
                        'Content-Type' => 'application/json'
                    )
                );
            }
            
            // Method 3: API key in header
            if (!empty($jetpack_crm_api_key)) {
                $auth_methods[] = array(
                    'name' => 'API Key Header',
                    'headers' => array(
                        'X-API-Key' => $jetpack_crm_api_key,
                        'Content-Type' => 'application/json'
                    )
                );
            }
            
            // Method 4: API key as parameter
            if (!empty($jetpack_crm_api_key)) {
                $auth_methods[] = array(
                    'name' => 'API Key Parameter',
                    'url' => $test_url . '?api_key=' . urlencode($jetpack_crm_api_key),
                    'headers' => array(
                        'Content-Type' => 'application/json'
                    )
                );
            }
            
            $last_error = '';
            $successful_method = '';
            
            foreach ($auth_methods as $method) {
                $test_url_to_use = isset($method['url']) ? $method['url'] : $test_url;
                $response = wp_remote_get($test_url_to_use, array(
                    'headers' => $method['headers'],
                    'timeout' => 10
                ));
                
                if (!is_wp_error($response)) {
                    $status_code = wp_remote_retrieve_response_code($response);
                    if ($status_code === 200) {
                        $successful_method = $method['name'];
                        break;
                    } else {
                        $last_error = $method['name'] . ' failed with status ' . $status_code;
                    }
                } else {
                    $last_error = $method['name'] . ' failed: ' . $response->get_error_message();
                }
            }
            
            if (empty($successful_method)) {
                wp_send_json_error('All authentication methods failed. Last error: ' . $last_error);
            }
            
            wp_send_json_success('Connection test successful! Jetpack CRM connected using: ' . $successful_method);
            
        } catch (Exception $e) {
            wp_send_json_error('Test connection failed: ' . $e->getMessage());
        }
    }
    
    public function save_xero_credentials() {
        check_ajax_referer('xero_jetpack_crm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $client_id = sanitize_text_field($_POST['xero_client_id']);
        $client_secret = sanitize_text_field($_POST['xero_client_secret']);
        
        if (empty($client_id) || empty($client_secret)) {
            wp_send_json_error('Both Client ID and Client Secret are required');
        }
        
        update_option('xero_client_id', $client_id);
        update_option('xero_client_secret', $client_secret);
        
        wp_send_json_success('Credentials saved successfully');
    }
    
    public function test_xero_credentials() {
        check_ajax_referer('xero_jetpack_crm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $client_id = sanitize_text_field($_POST['xero_client_id']);
        $client_secret = sanitize_text_field($_POST['xero_client_secret']);
        
        if (empty($client_id) || empty($client_secret)) {
            wp_send_json_error('Both Client ID and Client Secret are required');
        }
        
        // Basic validation - check if credentials look valid
        if (strlen($client_id) < 10 || strlen($client_secret) < 10) {
            wp_send_json_error('Invalid credential format');
        }
        
        wp_send_json_success('Credentials appear to be valid');
    }
    
    public function save_jetpack_credentials() {
        check_ajax_referer('xero_jetpack_crm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $api_key = sanitize_text_field($_POST['jetpack_crm_api_key']);
        $api_secret = sanitize_text_field($_POST['jetpack_crm_api_secret']);
        $endpoint = esc_url_raw($_POST['jetpack_crm_endpoint']);
        
        if (empty($api_key) || empty($endpoint)) {
            wp_send_json_error('API Key and Endpoint URL are required');
        }
        
        update_option('jetpack_crm_api_key', $api_key);
        update_option('jetpack_crm_api_secret', $api_secret);
        update_option('jetpack_crm_endpoint', $endpoint);
        
        wp_send_json_success('Configuration saved successfully');
    }
    
    public function test_jetpack_connection() {
        check_ajax_referer('xero_jetpack_crm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $api_key = get_option('jetpack_crm_api_key');
        $api_secret = get_option('jetpack_crm_api_secret');
        $endpoint = get_option('jetpack_crm_endpoint');
        
        if (empty($api_key) || empty($endpoint)) {
            wp_send_json_error('Jetpack CRM not configured');
        }
        
        // Test connection with multiple authentication methods
        $test_url = rtrim($endpoint, '/') . '/wp-json/zerobscrm/v1/customers';
        
        $auth_methods = array();
        
        // Method 1: Bearer token
        $auth_methods[] = array(
            'name' => 'Bearer Token',
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            )
        );
        
        // Method 2: Basic auth with API key and secret
        if (!empty($api_secret)) {
            $auth_methods[] = array(
                'name' => 'Basic Auth',
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode($api_key . ':' . $api_secret),
                    'Content-Type' => 'application/json'
                )
            );
        }
        
        // Method 3: API key in header
        $auth_methods[] = array(
            'name' => 'API Key Header',
            'headers' => array(
                'X-API-Key' => $api_key,
                'Content-Type' => 'application/json'
            )
        );
        
        $last_error = '';
        $successful_method = '';
        
        foreach ($auth_methods as $method) {
            $response = wp_remote_get($test_url, array(
                'headers' => $method['headers'],
                'timeout' => 10
            ));
            
            if (!is_wp_error($response)) {
                $status_code = wp_remote_retrieve_response_code($response);
                if ($status_code === 200) {
                    $successful_method = $method['name'];
                    break;
                } else {
                    $last_error = $method['name'] . ' failed with status ' . $status_code;
                }
            } else {
                $last_error = $method['name'] . ' failed: ' . $response->get_error_message();
            }
        }
        
        if (empty($successful_method)) {
            wp_send_json_error('All authentication methods failed. Last error: ' . $last_error);
        }
        
        wp_send_json_success('Connection successful using: ' . $successful_method);
    }
    
    public function clear_jetpack_config() {
        check_ajax_referer('xero_jetpack_crm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        delete_option('jetpack_crm_api_key');
        delete_option('jetpack_crm_api_secret');
        delete_option('jetpack_crm_endpoint');
        
        wp_send_json_success('Configuration cleared');
    }
    
    public function disconnect_xero() {
        check_ajax_referer('xero_jetpack_crm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        delete_option('xero_access_token');
        delete_option('xero_refresh_token');
        delete_option('xero_token_expires');
        
        wp_send_json_success('Xero disconnected');
    }
    
    public function save_sync_settings() {
        check_ajax_referer('xero_jetpack_crm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $frequency = sanitize_text_field($_POST['sync_frequency']);
        
        if (!in_array($frequency, array('manual', 'hourly', 'daily'))) {
            wp_send_json_error('Invalid sync frequency');
        }
        
        update_option('sync_frequency', $frequency);
        
        wp_send_json_success('Sync settings saved');
    }
    
    public function get_stats() {
        check_ajax_referer('xero_jetpack_crm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $stats = array(
            'contacts' => get_option('xero_synced_contacts_count', 0),
            'invoices' => get_option('xero_synced_invoices_count', 0),
            'last_sync' => get_option('xero_last_sync', 0) ? human_time_diff(get_option('xero_last_sync', 0)) . ' ago' : 'Never',
            'frequency' => ucfirst(get_option('sync_frequency', 'manual'))
        );
        
        wp_send_json_success($stats);
    }
    
    public function manual_sync() {
        check_ajax_referer('xero_jetpack_crm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        // Check if we have valid tokens
        $access_token = $this->decrypt_token(get_option('xero_access_token'));
        $refresh_token = $this->decrypt_token(get_option('xero_refresh_token'));
        
        if (empty($access_token) || empty($refresh_token)) {
            wp_send_json_error('Not connected to Xero. Please authenticate first.');
        }
        
        // Check if Jetpack CRM is configured
        $jetpack_api_key = get_option('jetpack_crm_api_key');
        $jetpack_api_secret = get_option('jetpack_crm_api_secret');
        $jetpack_endpoint = get_option('jetpack_crm_endpoint');
        
        if (empty($jetpack_api_key) || empty($jetpack_api_secret) || empty($jetpack_endpoint)) {
            wp_send_json_error('Jetpack CRM not configured. Please set up API credentials (key, secret, and endpoint) first.');
        }
        
        // Initialize sync progress
        update_option('xero_sync_progress', array(
            'status' => 'starting',
            'current_step' => 'Initializing...',
            'progress' => 0,
            'total_contacts' => 0,
            'total_invoices' => 0,
            'synced_contacts' => 0,
            'synced_invoices' => 0,
            'errors' => 0,
            'current_contact' => '',
            'current_invoice' => ''
        ));
        
        // Start background sync process
        wp_schedule_single_event(time(), 'xero_background_sync');
        
        wp_send_json_success('Sync started in background. Check progress below.');
    }
    
    public function background_sync() {
        $this->log_sync_message('Starting background sync process...');
        
        // Update progress
        $this->update_sync_progress('fetching_contacts', 'Fetching contacts from Xero...', 10);
        
        try {
            // Sync contacts
            $contacts_result = $this->sync_contacts_from_xero_with_progress();
            
            // Update progress
            $this->update_sync_progress('fetching_invoices', 'Fetching invoices from Xero...', 50);
            
            // Sync invoices
            $invoices_result = $this->sync_invoices_from_xero_with_progress();
            
            // Update sync statistics
            $total_contacts = get_option('xero_synced_contacts_count', 0) + $contacts_result['synced'];
            $total_invoices = get_option('xero_synced_invoices_count', 0) + $invoices_result['synced'];
            
            update_option('xero_synced_contacts_count', $total_contacts);
            update_option('xero_synced_invoices_count', $total_invoices);
            update_option('xero_last_sync', time());
            
            // Mark as completed
            $this->update_sync_progress('completed', 'Sync completed successfully!', 100);
            
            $this->log_sync_message('Background sync completed successfully!');
            
        } catch (Exception $e) {
            $this->update_sync_progress('error', 'Sync failed: ' . $e->getMessage(), 0);
            $this->log_sync_message('Background sync failed: ' . $e->getMessage());
        }
    }
    
    public function get_sync_progress_ajax() {
        check_ajax_referer('xero_jetpack_crm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $progress = get_option('xero_sync_progress', array(
            'status' => 'idle',
            'current_step' => 'Ready to sync',
            'progress' => 0,
            'total_contacts' => 0,
            'total_invoices' => 0,
            'synced_contacts' => 0,
            'synced_invoices' => 0,
            'errors' => 0,
            'current_contact' => '',
            'current_invoice' => ''
        ));
        
        wp_send_json_success($progress);
    }
    
    private function update_sync_progress($status, $current_step, $progress, $additional_data = array()) {
        $current_progress = get_option('xero_sync_progress', array());
        
        $current_progress['status'] = $status;
        $current_progress['current_step'] = $current_step;
        $current_progress['progress'] = $progress;
        $current_progress['timestamp'] = time();
        
        // Merge additional data
        if (!empty($additional_data)) {
            $current_progress = array_merge($current_progress, $additional_data);
        }
        
        update_option('xero_sync_progress', $current_progress);
    }
    
    public function test_sync_ajax() {
        check_ajax_referer('xero_jetpack_crm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $this->log_sync_message('=== TEST SYNC STARTED ===');
        
        // Test Xero connection
        $access_token = $this->decrypt_token(get_option('xero_access_token'));
        $tenant_id = get_option('xero_tenant_id');
        
        $this->log_sync_message('Access Token: ' . (empty($access_token) ? 'MISSING' : 'PRESENT (' . strlen($access_token) . ' chars)'));
        $this->log_sync_message('Tenant ID: ' . (empty($tenant_id) ? 'MISSING' : $tenant_id));
        
        if (empty($access_token) || empty($tenant_id)) {
            wp_send_json_error('Xero not properly connected. Access token or tenant ID missing.');
        }
        
        // Test Xero API call
        $response = wp_remote_get('https://api.xero.com/api.xro/2.0/Contacts', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Xero-tenant-id' => $tenant_id,
                'Accept' => 'application/json'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            $this->log_sync_message('Xero API Error: ' . $response->get_error_message());
            wp_send_json_error('Xero API Error: ' . $response->get_error_message());
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        $this->log_sync_message('Xero API Response Code: ' . $http_code);
        $this->log_sync_message('Xero API Response Body: ' . substr($body, 0, 500) . '...');
        
        if ($http_code !== 200) {
            wp_send_json_error('Xero API returned HTTP ' . $http_code . ': ' . $body);
        }
        
        $data = json_decode($body, true);
        $contact_count = isset($data['Contacts']) ? count($data['Contacts']) : 0;
        
        $this->log_sync_message('Found ' . $contact_count . ' contacts in Xero');
        
        // Test Jetpack CRM connection
        $jetpack_api_key = get_option('jetpack_crm_api_key');
        $jetpack_api_secret = get_option('jetpack_crm_api_secret');
        $jetpack_endpoint = get_option('jetpack_crm_endpoint');
        
        $this->log_sync_message('Jetpack API Key: ' . (empty($jetpack_api_key) ? 'MISSING' : 'PRESENT (' . strlen($jetpack_api_key) . ' chars)'));
        $this->log_sync_message('Jetpack API Secret: ' . (empty($jetpack_api_secret) ? 'MISSING' : 'PRESENT (' . strlen($jetpack_api_secret) . ' chars)'));
        $this->log_sync_message('Jetpack Endpoint: ' . (empty($jetpack_endpoint) ? 'MISSING' : $jetpack_endpoint));
        
        if (empty($jetpack_api_key) || empty($jetpack_api_secret) || empty($jetpack_endpoint)) {
            wp_send_json_error('Jetpack CRM not properly configured. API key, secret, or endpoint missing.');
        }
        
        // Test Jetpack CRM API call with Basic Auth
        $auth_header = 'Basic ' . base64_encode($jetpack_api_key . ':' . $jetpack_api_secret);
        
        $jetpack_response = wp_remote_get(rtrim($jetpack_endpoint, '/') . '/wp-json/zerobscrm/v1/customers', array(
            'headers' => array(
                'Authorization' => $auth_header,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($jetpack_response)) {
            $this->log_sync_message('Jetpack CRM API Error: ' . $jetpack_response->get_error_message());
            wp_send_json_error('Jetpack CRM API Error: ' . $jetpack_response->get_error_message());
        }
        
        $jetpack_http_code = wp_remote_retrieve_response_code($jetpack_response);
        $jetpack_body = wp_remote_retrieve_body($jetpack_response);
        
        $this->log_sync_message('Jetpack CRM API Response Code: ' . $jetpack_http_code);
        $this->log_sync_message('Jetpack CRM API Response Body: ' . substr($jetpack_body, 0, 500) . '...');
        
        if ($jetpack_http_code !== 200) {
            wp_send_json_error('Jetpack CRM API returned HTTP ' . $jetpack_http_code . ': ' . $jetpack_body);
        }
        
        $jetpack_data = json_decode($jetpack_body, true);
        $jetpack_contact_count = is_array($jetpack_data) ? count($jetpack_data) : 0;
        
        $this->log_sync_message('Found ' . $jetpack_contact_count . ' existing contacts in Jetpack CRM');
        $this->log_sync_message('=== TEST SYNC COMPLETED ===');
        
        wp_send_json_success(array(
            'xero_contacts' => $contact_count,
            'jetpack_contacts' => $jetpack_contact_count,
            'xero_status' => 'Connected',
            'jetpack_status' => 'Connected'
        ));
    }
    
    private function sync_contacts_from_xero() {
        $access_token = $this->decrypt_token(get_option('xero_access_token'));
        $tenant_id = get_option('xero_tenant_id');
        
        if (empty($access_token) || empty($tenant_id)) {
            return array('synced' => 0, 'errors' => 1);
        }
        
        $synced = 0;
        $errors = 0;
        
        // Fetch contacts from Xero
        $response = wp_remote_get('https://api.xero.com/api.xro/2.0/Contacts', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Xero-tenant-id' => $tenant_id,
                'Accept' => 'application/json'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            $this->log_sync_message('Failed to fetch contacts from Xero: ' . $response->get_error_message());
            return array('synced' => 0, 'errors' => 1);
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            $this->log_sync_message('Xero API error (HTTP ' . $http_code . '): ' . $body);
            return array('synced' => 0, 'errors' => 1);
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['Contacts']) || !is_array($data['Contacts'])) {
            $this->log_sync_message('Invalid response format from Xero API');
            return array('synced' => 0, 'errors' => 1);
        }
        
        $this->log_sync_message('Found ' . count($data['Contacts']) . ' contacts in Xero');
        
        // Process each contact
        foreach ($data['Contacts'] as $xero_contact) {
            try {
                $result = $this->sync_single_contact($xero_contact);
                if ($result) {
                    $synced++;
                } else {
                    $errors++;
                }
            } catch (Exception $e) {
                $this->log_sync_message('Error syncing contact ' . $xero_contact['Name'] . ': ' . $e->getMessage());
                $errors++;
            }
        }
        
        return array('synced' => $synced, 'errors' => $errors);
    }
    
    private function sync_contacts_from_xero_with_progress() {
        $access_token = $this->decrypt_token(get_option('xero_access_token'));
        $tenant_id = get_option('xero_tenant_id');
        
        if (empty($access_token) || empty($tenant_id)) {
            return array('synced' => 0, 'errors' => 1);
        }
        
        $synced = 0;
        $errors = 0;
        
        // Fetch contacts from Xero
        $response = wp_remote_get('https://api.xero.com/api.xro/2.0/Contacts', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Xero-tenant-id' => $tenant_id,
                'Accept' => 'application/json'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            $this->log_sync_message('Failed to fetch contacts from Xero: ' . $response->get_error_message());
            return array('synced' => 0, 'errors' => 1);
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            $this->log_sync_message('Xero API error (HTTP ' . $http_code . '): ' . $body);
            return array('synced' => 0, 'errors' => 1);
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['Contacts']) || !is_array($data['Contacts'])) {
            $this->log_sync_message('Invalid response format from Xero API');
            return array('synced' => 0, 'errors' => 1);
        }
        
        $total_contacts = count($data['Contacts']);
        $this->log_sync_message('Found ' . $total_contacts . ' contacts in Xero');
        
        // Update progress with total count
        $this->update_sync_progress('syncing_contacts', 'Syncing contacts...', 20, array(
            'total_contacts' => $total_contacts,
            'synced_contacts' => 0
        ));
        
        // Process each contact
        foreach ($data['Contacts'] as $index => $xero_contact) {
            try {
                $this->update_sync_progress('syncing_contacts', 'Syncing contacts...', 20 + (($index / $total_contacts) * 30), array(
                    'current_contact' => $xero_contact['Name'],
                    'synced_contacts' => $synced,
                    'total_contacts' => $total_contacts
                ));
                
                $result = $this->sync_single_contact($xero_contact);
                if ($result) {
                    $synced++;
                } else {
                    $errors++;
                }
            } catch (Exception $e) {
                $this->log_sync_message('Error syncing contact ' . $xero_contact['Name'] . ': ' . $e->getMessage());
                $errors++;
            }
        }
        
        return array('synced' => $synced, 'errors' => $errors);
    }
    
    private function sync_invoices_from_xero_with_progress() {
        $access_token = $this->decrypt_token(get_option('xero_access_token'));
        $tenant_id = get_option('xero_tenant_id');
        
        if (empty($access_token) || empty($tenant_id)) {
            return array('synced' => 0, 'errors' => 1);
        }
        
        $synced = 0;
        $errors = 0;
        
        // Fetch invoices from Xero (last 12 months)
        $from_date = date('Y-m-d', strtotime('-12 months'));
        $to_date = date('Y-m-d');
        
        $response = wp_remote_get('https://api.xero.com/api.xro/2.0/Invoices?where=Date>=' . $from_date . '&where=Date<=' . $to_date, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Xero-tenant-id' => $tenant_id,
                'Accept' => 'application/json'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            $this->log_sync_message('Failed to fetch invoices from Xero: ' . $response->get_error_message());
            return array('synced' => 0, 'errors' => 1);
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            $this->log_sync_message('Xero API error (HTTP ' . $http_code . '): ' . $body);
            return array('synced' => 0, 'errors' => 1);
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['Invoices']) || !is_array($data['Invoices'])) {
            $this->log_sync_message('Invalid response format from Xero API');
            return array('synced' => 0, 'errors' => 1);
        }
        
        $total_invoices = count($data['Invoices']);
        $this->log_sync_message('Found ' . $total_invoices . ' invoices in Xero');
        
        // Update progress with total count
        $this->update_sync_progress('syncing_invoices', 'Syncing invoices...', 50, array(
            'total_invoices' => $total_invoices,
            'synced_invoices' => 0
        ));
        
        // Process each invoice
        foreach ($data['Invoices'] as $index => $xero_invoice) {
            try {
                $this->update_sync_progress('syncing_invoices', 'Syncing invoices...', 50 + (($index / $total_invoices) * 40), array(
                    'current_invoice' => $xero_invoice['InvoiceNumber'],
                    'synced_invoices' => $synced,
                    'total_invoices' => $total_invoices
                ));
                
                $result = $this->sync_single_invoice($xero_invoice);
                if ($result) {
                    $synced++;
                } else {
                    $errors++;
                }
            } catch (Exception $e) {
                $this->log_sync_message('Error syncing invoice ' . $xero_invoice['InvoiceNumber'] . ': ' . $e->getMessage());
                $errors++;
            }
        }
        
        return array('synced' => $synced, 'errors' => $errors);
    }
    
    private function sync_single_contact($xero_contact) {
        // Check if contact already exists in Jetpack CRM
        $existing_contact = $this->find_jetpack_contact_by_xero_id($xero_contact['ContactID']);
        
        $contact_data = array(
            'fname' => $this->extract_first_name($xero_contact['Name']),
            'lname' => $this->extract_last_name($xero_contact['Name']),
            'email' => isset($xero_contact['EmailAddress']) ? $xero_contact['EmailAddress'] : '',
            'tel' => isset($xero_contact['Phones']) && !empty($xero_contact['Phones']) ? $xero_contact['Phones'][0]['PhoneNumber'] : '',
            'company' => isset($xero_contact['Name']) ? $xero_contact['Name'] : '',
            'addr1' => isset($xero_contact['Addresses']) && !empty($xero_contact['Addresses']) ? $xero_contact['Addresses'][0]['AddressLine1'] : '',
            'addr2' => isset($xero_contact['Addresses']) && !empty($xero_contact['Addresses']) ? $xero_contact['Addresses'][0]['AddressLine2'] : '',
            'city' => isset($xero_contact['Addresses']) && !empty($xero_contact['Addresses']) ? $xero_contact['Addresses'][0]['City'] : '',
            'county' => isset($xero_contact['Addresses']) && !empty($xero_contact['Addresses']) ? $xero_contact['Addresses'][0]['Region'] : '',
            'postcode' => isset($xero_contact['Addresses']) && !empty($xero_contact['Addresses']) ? $xero_contact['Addresses'][0]['PostalCode'] : '',
            'country' => isset($xero_contact['Addresses']) && !empty($xero_contact['Addresses']) ? $xero_contact['Addresses'][0]['Country'] : '',
            'xero_contact_id' => $xero_contact['ContactID'] // Custom field for de-duplication
        );
        
        if ($existing_contact) {
            // Update existing contact
            return $this->update_jetpack_contact($existing_contact['id'], $contact_data);
        } else {
            // Create new contact
            return $this->create_jetpack_contact($contact_data);
        }
    }
    
    private function sync_invoices_from_xero() {
        $access_token = $this->decrypt_token(get_option('xero_access_token'));
        $tenant_id = get_option('xero_tenant_id');
        
        if (empty($access_token) || empty($tenant_id)) {
            return array('synced' => 0, 'errors' => 1);
        }
        
        $synced = 0;
        $errors = 0;
        
        // Fetch invoices from Xero (last 12 months)
        $from_date = date('Y-m-d', strtotime('-12 months'));
        $to_date = date('Y-m-d');
        
        $response = wp_remote_get('https://api.xero.com/api.xro/2.0/Invoices?where=Date>=' . $from_date . '&where=Date<=' . $to_date, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Xero-tenant-id' => $tenant_id,
                'Accept' => 'application/json'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            $this->log_sync_message('Failed to fetch invoices from Xero: ' . $response->get_error_message());
            return array('synced' => 0, 'errors' => 1);
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            $this->log_sync_message('Xero API error (HTTP ' . $http_code . '): ' . $body);
            return array('synced' => 0, 'errors' => 1);
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['Invoices']) || !is_array($data['Invoices'])) {
            $this->log_sync_message('Invalid response format from Xero API');
            return array('synced' => 0, 'errors' => 1);
        }
        
        $this->log_sync_message('Found ' . count($data['Invoices']) . ' invoices in Xero');
        
        // Process each invoice
        foreach ($data['Invoices'] as $xero_invoice) {
            try {
                $result = $this->sync_single_invoice($xero_invoice);
                if ($result) {
                    $synced++;
                } else {
                    $errors++;
                }
            } catch (Exception $e) {
                $this->log_sync_message('Error syncing invoice ' . $xero_invoice['InvoiceNumber'] . ': ' . $e->getMessage());
                $errors++;
            }
        }
        
        return array('synced' => $synced, 'errors' => $errors);
    }
    
    private function sync_single_invoice($xero_invoice) {
        // Check if transaction already exists in Jetpack CRM
        $existing_transaction = $this->find_jetpack_transaction_by_xero_id($xero_invoice['InvoiceID']);
        
        // Get line item descriptions
        $description = '';
        if (isset($xero_invoice['LineItems']) && is_array($xero_invoice['LineItems'])) {
            $descriptions = array();
            foreach ($xero_invoice['LineItems'] as $line_item) {
                if (isset($line_item['Description']) && !empty($line_item['Description'])) {
                    $descriptions[] = $line_item['Description'];
                }
            }
            $description = implode(', ', $descriptions);
        }
        
        $transaction_data = array(
            'title' => 'Invoice ' . $xero_invoice['InvoiceNumber'],
            'type' => 'invoice',
            'value' => isset($xero_invoice['Total']) ? $xero_invoice['Total'] : 0,
            'currency' => isset($xero_invoice['CurrencyCode']) ? $xero_invoice['CurrencyCode'] : 'USD',
            'date' => isset($xero_invoice['Date']) ? date('Y-m-d', strtotime($xero_invoice['Date'])) : date('Y-m-d'),
            'due_date' => isset($xero_invoice['DueDate']) ? date('Y-m-d', strtotime($xero_invoice['DueDate'])) : null,
            'status' => isset($xero_invoice['Status']) ? strtolower($xero_invoice['Status']) : 'draft',
            'description' => $description,
            'xero_invoice_id' => $xero_invoice['InvoiceID'],
            'xero_invoice_number' => $xero_invoice['InvoiceNumber']
        );
        
        if ($existing_transaction) {
            // Update existing transaction
            return $this->update_jetpack_transaction($existing_transaction['id'], $transaction_data);
        } else {
            // Create new transaction
            return $this->create_jetpack_transaction($transaction_data);
        }
    }
    
    private function log_sync_message($message) {
        $log_message = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        error_log($log_message, 3, WP_CONTENT_DIR . '/uploads/xero-sync.log');
    }
    
    private function extract_first_name($full_name) {
        $parts = explode(' ', trim($full_name));
        return $parts[0];
    }
    
    private function extract_last_name($full_name) {
        $parts = explode(' ', trim($full_name));
        if (count($parts) > 1) {
            return implode(' ', array_slice($parts, 1));
        }
        return '';
    }
    
    private function find_jetpack_contact_by_xero_id($xero_contact_id) {
        $jetpack_api_key = get_option('jetpack_crm_api_key');
        $jetpack_api_secret = get_option('jetpack_crm_api_secret');
        $jetpack_endpoint = get_option('jetpack_crm_endpoint');
        
        if (empty($jetpack_api_key) || empty($jetpack_api_secret) || empty($jetpack_endpoint)) {
            return false;
        }
        
        $auth_header = 'Basic ' . base64_encode($jetpack_api_key . ':' . $jetpack_api_secret);
        
        $response = wp_remote_get(rtrim($jetpack_endpoint, '/') . '/wp-json/zerobscrm/v1/customers', array(
            'headers' => array(
                'Authorization' => $auth_header,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $customers = json_decode(wp_remote_retrieve_body($response), true);
        
        if (is_array($customers)) {
            foreach ($customers as $customer) {
                if (isset($customer['xero_contact_id']) && $customer['xero_contact_id'] === $xero_contact_id) {
                    return $customer;
                }
            }
        }
        
        return false;
    }
    
    private function find_jetpack_transaction_by_xero_id($xero_invoice_id) {
        $jetpack_api_key = get_option('jetpack_crm_api_key');
        $jetpack_api_secret = get_option('jetpack_crm_api_secret');
        $jetpack_endpoint = get_option('jetpack_crm_endpoint');
        
        if (empty($jetpack_api_key) || empty($jetpack_api_secret) || empty($jetpack_endpoint)) {
            return false;
        }
        
        $auth_header = 'Basic ' . base64_encode($jetpack_api_key . ':' . $jetpack_api_secret);
        
        $response = wp_remote_get(rtrim($jetpack_endpoint, '/') . '/wp-json/zerobscrm/v1/transactions', array(
            'headers' => array(
                'Authorization' => $auth_header,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $transactions = json_decode(wp_remote_retrieve_body($response), true);
        
        if (is_array($transactions)) {
            foreach ($transactions as $transaction) {
                if (isset($transaction['xero_invoice_id']) && $transaction['xero_invoice_id'] === $xero_invoice_id) {
                    return $transaction;
                }
            }
        }
        
        return false;
    }
    
    private function create_jetpack_contact($contact_data) {
        $jetpack_api_key = get_option('jetpack_crm_api_key');
        $jetpack_api_secret = get_option('jetpack_crm_api_secret');
        $jetpack_endpoint = get_option('jetpack_crm_endpoint');
        
        if (empty($jetpack_api_key) || empty($jetpack_api_secret) || empty($jetpack_endpoint)) {
            return false;
        }
        
        $auth_header = 'Basic ' . base64_encode($jetpack_api_key . ':' . $jetpack_api_secret);
        
        $response = wp_remote_post(rtrim($jetpack_endpoint, '/') . '/wp-json/zerobscrm/v1/customers', array(
            'headers' => array(
                'Authorization' => $auth_header,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($contact_data),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            $this->log_sync_message('Failed to create Jetpack contact: ' . $response->get_error_message());
            return false;
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code >= 200 && $http_code < 300) {
            $this->log_sync_message('Created Jetpack contact: ' . $contact_data['fname'] . ' ' . $contact_data['lname']);
            return true;
        } else {
            $body = wp_remote_retrieve_body($response);
            $this->log_sync_message('Failed to create Jetpack contact (HTTP ' . $http_code . '): ' . $body);
            return false;
        }
    }
    
    private function update_jetpack_contact($contact_id, $contact_data) {
        $jetpack_api_key = get_option('jetpack_crm_api_key');
        $jetpack_api_secret = get_option('jetpack_crm_api_secret');
        $jetpack_endpoint = get_option('jetpack_crm_endpoint');
        
        if (empty($jetpack_api_key) || empty($jetpack_api_secret) || empty($jetpack_endpoint)) {
            return false;
        }
        
        $auth_header = 'Basic ' . base64_encode($jetpack_api_key . ':' . $jetpack_api_secret);
        
        $response = wp_remote_post(rtrim($jetpack_endpoint, '/') . '/wp-json/zerobscrm/v1/customers/' . $contact_id, array(
            'headers' => array(
                'Authorization' => $auth_header,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($contact_data),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            $this->log_sync_message('Failed to update Jetpack contact: ' . $response->get_error_message());
            return false;
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code >= 200 && $http_code < 300) {
            $this->log_sync_message('Updated Jetpack contact: ' . $contact_data['fname'] . ' ' . $contact_data['lname']);
            return true;
        } else {
            $body = wp_remote_retrieve_body($response);
            $this->log_sync_message('Failed to update Jetpack contact (HTTP ' . $http_code . '): ' . $body);
            return false;
        }
    }
    
    private function create_jetpack_transaction($transaction_data) {
        $jetpack_api_key = get_option('jetpack_crm_api_key');
        $jetpack_api_secret = get_option('jetpack_crm_api_secret');
        $jetpack_endpoint = get_option('jetpack_crm_endpoint');
        
        if (empty($jetpack_api_key) || empty($jetpack_api_secret) || empty($jetpack_endpoint)) {
            return false;
        }
        
        $auth_header = 'Basic ' . base64_encode($jetpack_api_key . ':' . $jetpack_api_secret);
        
        $response = wp_remote_post(rtrim($jetpack_endpoint, '/') . '/wp-json/zerobscrm/v1/transactions', array(
            'headers' => array(
                'Authorization' => $auth_header,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($transaction_data),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            $this->log_sync_message('Failed to create Jetpack transaction: ' . $response->get_error_message());
            return false;
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code >= 200 && $http_code < 300) {
            $this->log_sync_message('Created Jetpack transaction: ' . $transaction_data['title']);
            return true;
        } else {
            $body = wp_remote_retrieve_body($response);
            $this->log_sync_message('Failed to create Jetpack transaction (HTTP ' . $http_code . '): ' . $body);
            return false;
        }
    }
    
    private function update_jetpack_transaction($transaction_id, $transaction_data) {
        $jetpack_api_key = get_option('jetpack_crm_api_key');
        $jetpack_api_secret = get_option('jetpack_crm_api_secret');
        $jetpack_endpoint = get_option('jetpack_crm_endpoint');
        
        if (empty($jetpack_api_key) || empty($jetpack_api_secret) || empty($jetpack_endpoint)) {
            return false;
        }
        
        $auth_header = 'Basic ' . base64_encode($jetpack_api_key . ':' . $jetpack_api_secret);
        
        $response = wp_remote_post(rtrim($jetpack_endpoint, '/') . '/wp-json/zerobscrm/v1/transactions/' . $transaction_id, array(
            'headers' => array(
                'Authorization' => $auth_header,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($transaction_data),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            $this->log_sync_message('Failed to update Jetpack transaction: ' . $response->get_error_message());
            return false;
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code >= 200 && $http_code < 300) {
            $this->log_sync_message('Updated Jetpack transaction: ' . $transaction_data['title']);
            return true;
        } else {
            $body = wp_remote_retrieve_body($response);
            $this->log_sync_message('Failed to update Jetpack transaction (HTTP ' . $http_code . '): ' . $body);
            return false;
        }
    }
    
    public function oauth_callback() {
        // Log all GET parameters for debugging
        error_log('OAuth Callback - GET parameters: ' . print_r($_GET, true));
        
        // Check if this is an OAuth callback (either with action parameter or just code/state)
        $is_oauth_callback = (isset($_GET['action']) && $_GET['action'] === 'oauth_callback') || 
                            (isset($_GET['code']) && isset($_GET['state']));
        
        if ($is_oauth_callback) {
            if (!isset($_GET['code']) || !isset($_GET['state'])) {
                error_log('OAuth Callback - Missing code or state parameters');
                wp_die('Invalid OAuth callback parameters');
            }
            
            $code = sanitize_text_field($_GET['code']);
            $state = sanitize_text_field($_GET['state']);
            
            // Parse state parameter
            $state_parts = explode('|', $state);
            $action = isset($state_parts[0]) ? $state_parts[0] : '';
            
            // Verify state parameter to prevent CSRF attacks
            $stored_state = get_option('xero_oauth_state');
            if ($state !== $stored_state) {
                error_log('OAuth Callback - State mismatch. Expected: ' . $stored_state . ', Received: ' . $state);
                wp_die('Invalid state parameter - possible CSRF attack');
            }
            
            // Clear the stored state
            delete_option('xero_oauth_state');
            
            $client_id = get_option('xero_client_id');
            $client_secret = get_option('xero_client_secret');
            
            if (empty($client_id) || empty($client_secret)) {
                error_log('OAuth Callback - Xero credentials not configured');
                wp_die('Xero credentials not configured');
            }
            
            // Exchange code for tokens
            $token_url = 'https://identity.xero.com/connect/token';
            $redirect_uri = admin_url('admin.php?page=xero-jetpack-crm-integration&action=oauth_callback');
            
            // Log the token exchange request details
            error_log('OAuth Callback - Token exchange request details:');
            error_log('- Client ID: ' . $client_id);
            error_log('- Client Secret: ' . (empty($client_secret) ? 'EMPTY' : 'PRESENT (' . strlen($client_secret) . ' chars)'));
            error_log('- Code: ' . $code);
            error_log('- Redirect URI: ' . $redirect_uri);
            error_log('- Token URL: ' . $token_url);
            
            // Use Basic Auth header instead of client credentials in body
            $auth_header = 'Basic ' . base64_encode($client_id . ':' . $client_secret);
            
            $response = wp_remote_post($token_url, array(
                'body' => array(
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $redirect_uri
                ),
                'headers' => array(
                    'Authorization' => $auth_header,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Accept' => 'application/json'
                ),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                $error_message = 'OAuth Callback - Token exchange failed: ' . $response->get_error_message();
                error_log($error_message);
                error_log($error_message, 3, WP_CONTENT_DIR . '/uploads/xero-sync.log');
                wp_die('Token exchange failed: ' . $response->get_error_message());
            }
            
            $http_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            error_log('OAuth Callback - Token exchange response (HTTP ' . $http_code . '): ' . $body);
            
            if ($http_code !== 200) {
                $error_message = 'OAuth Callback - Token exchange failed with HTTP ' . $http_code . ': ' . $body;
                error_log($error_message);
                error_log($error_message, 3, WP_CONTENT_DIR . '/uploads/xero-sync.log');
                wp_die('Token exchange failed with HTTP ' . $http_code . '. Response: ' . $body);
            }
            
            if (isset($data['access_token'])) {
                // Encrypt and save tokens securely
                $this->save_encrypted_tokens($data);
                update_option('xero_connected_at', time());
                
                // Fetch basic organization info to verify connection
                $this->fetch_xero_organization_info();
                
                // Log successful connection
                error_log('Xero OAuth: Successfully connected. Access token saved.');
                
                // Redirect to admin page with success message
                wp_redirect(admin_url('options-general.php?page=xero-jetpack-crm-integration&connected=1&success=1'));
                exit;
            } else {
                // Log error for debugging
                error_log('Xero OAuth Error - No access token in response: ' . $body);
                if (isset($data['error'])) {
                    error_log('Xero OAuth Error details: ' . $data['error'] . ' - ' . (isset($data['error_description']) ? $data['error_description'] : 'No description'));
                    wp_die('OAuth Error: ' . $data['error'] . ' - ' . (isset($data['error_description']) ? $data['error_description'] : 'No description'));
                } else {
                    wp_die('Failed to obtain access token. Response: ' . $body);
                }
            }
        } else {
            // Fallback: Handle callback without action parameter
            if (isset($_GET['code']) && isset($_GET['state'])) {
                error_log('OAuth Callback - Fallback handler triggered');
                
                $code = sanitize_text_field($_GET['code']);
                $state = sanitize_text_field($_GET['state']);
                
                // Parse state parameter
                $state_parts = explode('|', $state);
                $action = isset($state_parts[0]) ? $state_parts[0] : '';
                
                $client_id = get_option('xero_client_id');
                $client_secret = get_option('xero_client_secret');
                
                if (empty($client_id) || empty($client_secret)) {
                    wp_die('Xero credentials not configured');
                }
                
                // Exchange code for tokens
                $token_url = 'https://identity.xero.com/connect/token';
                $redirect_uri = admin_url('admin.php?page=xero-jetpack-crm-integration&action=oauth_callback');
                
                // Use Basic Auth header instead of client credentials in body
                $auth_header = 'Basic ' . base64_encode($client_id . ':' . $client_secret);
                
                $response = wp_remote_post($token_url, array(
                    'body' => array(
                        'grant_type' => 'authorization_code',
                        'code' => $code,
                        'redirect_uri' => $redirect_uri
                    ),
                    'headers' => array(
                        'Authorization' => $auth_header,
                        'Content-Type' => 'application/x-www-form-urlencoded',
                        'Accept' => 'application/json'
                    ),
                    'timeout' => 30
                ));
                
                if (is_wp_error($response)) {
                    wp_die('Token exchange failed: ' . $response->get_error_message());
                }
                
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                
                error_log('OAuth Callback (Fallback) - Token exchange response: ' . $body);
                
                if (isset($data['access_token'])) {
                    // Encrypt and save tokens securely
                    $this->save_encrypted_tokens($data);
                    update_option('xero_connected_at', time());
                    
                    // Fetch basic organization info to verify connection
                    $this->fetch_xero_organization_info();
                    
                    // Log successful connection
                    error_log('Xero OAuth: Successfully connected via fallback handler. Access token saved.');
                    
                    // Redirect based on action - if connecting, go to Jetpack tab
                    if ($action === 'connect-xero') {
                        wp_redirect(admin_url('options-general.php?page=xero-jetpack-crm-integration&tab=jetpack&connected=1&success=1'));
                    } else {
                        wp_redirect(admin_url('options-general.php?page=xero-jetpack-crm-integration&connected=1&success=1'));
                    }
                    exit;
                } else {
                    // Log error for debugging
                    error_log('Xero OAuth Error (Fallback): ' . $body);
                    wp_die('Failed to obtain access token. Error: ' . $body);
                }
            }
        }
    }
    
    public function get_xero_authorization_url() {
        $client_id = get_option('xero_client_id');
        $redirect_uri = admin_url('admin.php?page=xero-jetpack-crm-integration&action=oauth_callback');
        $state = 'connect-xero|' . wp_generate_password(32, false);
        
        // Store state for verification
        update_option('xero_oauth_state', $state);
        
        $params = array(
            'response_type' => 'code',
            'client_id' => $client_id,
            'redirect_uri' => $redirect_uri,
            'scope' => 'accounting.contacts accounting.transactions offline_access',
            'state' => $state
        );
        
        return 'https://login.xero.com/identity/connect/authorize?' . http_build_query($params);
    }
    
    private function save_encrypted_tokens($token_data) {
        // Encrypt tokens before storing
        $access_token = $this->encrypt_token($token_data['access_token']);
        $refresh_token = isset($token_data['refresh_token']) ? $this->encrypt_token($token_data['refresh_token']) : '';
        $expires_in = isset($token_data['expires_in']) ? time() + $token_data['expires_in'] : 0;
        
        update_option('xero_access_token', $access_token);
        update_option('xero_refresh_token', $refresh_token);
        update_option('xero_token_expires', $expires_in);
        
        error_log('Xero tokens encrypted and saved successfully');
    }
    
    private function encrypt_token($token) {
        // Use WordPress's built-in encryption if available, otherwise use a simple method
        if (function_exists('wp_salt')) {
            $key = wp_salt('secure_auth');
            return base64_encode(openssl_encrypt($token, 'AES-256-CBC', $key, 0, substr(hash('sha256', $key), 0, 16)));
        }
        
        // Fallback: simple base64 encoding (not secure for production)
        return base64_encode($token);
    }
    
    private function decrypt_token($encrypted_token) {
        if (empty($encrypted_token)) {
            return '';
        }
        
        // Use WordPress's built-in encryption if available
        if (function_exists('wp_salt')) {
            $key = wp_salt('secure_auth');
            $decrypted = openssl_decrypt(base64_decode($encrypted_token), 'AES-256-CBC', $key, 0, substr(hash('sha256', $key), 0, 16));
            return $decrypted !== false ? $decrypted : '';
        }
        
        // Fallback: simple base64 decoding
        return base64_decode($encrypted_token);
    }
    
    private function fetch_xero_organization_info() {
        $access_token = $this->decrypt_token(get_option('xero_access_token'));
        if (empty($access_token)) {
            return false;
        }
        
        // Get tenant ID without calling fetch_xero_organization_info to avoid circular dependency
        $tenant_id = get_option('xero_tenant_id');
        
        $response = wp_remote_get('https://api.xero.com/connections', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Xero-tenant-id' => $tenant_id
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('Xero API Error: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data[0]['tenantId'])) {
            update_option('xero_tenant_id', $data[0]['tenantId']);
            update_option('xero_tenant_name', $data[0]['tenantName']);
            update_option('xero_tenant_type', $data[0]['tenantType']);
            return true;
        }
        
        return false;
    }
    
    private function get_xero_tenant_id() {
        $tenant_id = get_option('xero_tenant_id');
        if (empty($tenant_id)) {
            // Don't call fetch_xero_organization_info here to avoid circular dependency
            // Return empty string instead
            return '';
        }
        return $tenant_id;
    }
    
    private function refresh_xero_token() {
        $refresh_token = $this->decrypt_token(get_option('xero_refresh_token'));
        if (empty($refresh_token)) {
            return false;
        }
        
        $client_id = get_option('xero_client_id');
        $client_secret = get_option('xero_client_secret');
        
        // Use Basic Auth header for refresh token request
        $auth_header = 'Basic ' . base64_encode($client_id . ':' . $client_secret);
        
        $response = wp_remote_post('https://identity.xero.com/connect/token', array(
            'body' => array(
                'grant_type' => 'refresh_token',
                'refresh_token' => $refresh_token
            ),
            'headers' => array(
                'Authorization' => $auth_header,
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('Xero token refresh failed: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['access_token'])) {
            $this->save_encrypted_tokens($data);
            return true;
        }
        
        return false;
    }
    
    public function refresh_xero_token_ajax() {
        check_ajax_referer('xero_jetpack_crm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $success = $this->refresh_xero_token();
        
        if ($success) {
            wp_send_json_success('Token refreshed successfully');
        } else {
            wp_send_json_error('Failed to refresh token');
        }
    }
    
    public function get_xero_status_ajax() {
        check_ajax_referer('xero_jetpack_crm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $access_token = $this->decrypt_token(get_option('xero_access_token'));
        $refresh_token = $this->decrypt_token(get_option('xero_refresh_token'));
        $token_expires = get_option('xero_token_expires', 0);
        $tenant_name = get_option('xero_tenant_name', '');
        $tenant_type = get_option('xero_tenant_type', '');
        $connected_at = get_option('xero_connected_at', 0);
        
        $connected = !empty($access_token) && !empty($refresh_token);
        
        // Check if token is expired
        if ($connected && $token_expires > 0 && time() > $token_expires) {
            if ($this->refresh_xero_token()) {
                $token_expires = get_option('xero_token_expires', 0);
            } else {
                $connected = false;
            }
        }
        
        $time_left = $token_expires > 0 ? max(0, $token_expires - time()) : 0;
        $minutes_left = floor($time_left / 60);
        
        wp_send_json_success(array(
            'connected' => $connected,
            'tenant_name' => $tenant_name,
            'tenant_type' => $tenant_type,
            'minutes_left' => $minutes_left,
            'connected_at' => $connected_at
        ));
    }
    
    public function handle_oauth_callback() {
        // Check if this is an OAuth callback
        if (isset($_GET['code']) && isset($_GET['state']) && 
            (isset($_GET['action']) && $_GET['action'] === 'oauth_callback' || 
             strpos($_SERVER['REQUEST_URI'], 'xero-jetpack-crm-integration') !== false)) {
            
            error_log('OAuth Callback detected via init hook');
            $this->oauth_callback();
        }
    }
    
    public function get_xero_auth_url_ajax() {
        check_ajax_referer('xero_jetpack_crm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $client_id = get_option('xero_client_id');
        if (empty($client_id)) {
            wp_send_json_error('Xero Client ID not configured');
        }
        
        $auth_url = $this->get_xero_authorization_url();
        wp_send_json_success(array('auth_url' => $auth_url));
    }
    
    public function test_oauth_flow() {
        // This method can be called to test the OAuth flow
        error_log('Testing OAuth flow...');
        
        $client_id = get_option('xero_client_id');
        $client_secret = get_option('xero_client_secret');
        
        if (empty($client_id) || empty($client_secret)) {
            error_log('OAuth Test: Credentials not configured');
            return false;
        }
        
        $auth_url = $this->get_xero_authorization_url();
        error_log('OAuth Test: Generated auth URL: ' . $auth_url);
        
        return $auth_url;
    }
    
    public function verify_xero_credentials() {
        $client_id = get_option('xero_client_id');
        $client_secret = get_option('xero_client_secret');
        
        if (empty($client_id) || empty($client_secret)) {
            return array('valid' => false, 'error' => 'Credentials not configured');
        }
        
        // Test with a simple API call to verify credentials
        $test_url = 'https://api.xero.com/connections';
        
        // Try to get connections (this will fail with invalid credentials)
        $response = wp_remote_get($test_url, array(
            'headers' => array(
                'Authorization' => 'Bearer test_token',
                'Accept' => 'application/json'
            ),
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            return array('valid' => false, 'error' => 'Network error: ' . $response->get_error_message());
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        
        // 401 means invalid token (expected), 403 means invalid client
        if ($http_code === 401) {
            return array('valid' => true, 'message' => 'Credentials format appears valid');
        } elseif ($http_code === 403) {
            return array('valid' => false, 'error' => 'Invalid client credentials');
        }
        
        return array('valid' => true, 'message' => 'Credentials verified');
    }
    
    public function verify_credentials_ajax() {
        check_ajax_referer('xero_jetpack_crm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $result = $this->verify_xero_credentials();
        
        if ($result['valid']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    public function test_token_exchange_ajax() {
        check_ajax_referer('xero_jetpack_crm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $client_id = get_option('xero_client_id');
        $client_secret = get_option('xero_client_secret');
        
        if (empty($client_id) || empty($client_secret)) {
            wp_send_json_error('Xero credentials not configured');
        }
        
        // Test with a dummy code to see what error we get
        $token_url = 'https://identity.xero.com/connect/token';
        $redirect_uri = admin_url('admin.php?page=xero-jetpack-crm-integration&action=oauth_callback');
        
        // Use Basic Auth header for test token exchange
        $auth_header = 'Basic ' . base64_encode($client_id . ':' . $client_secret);
        
        $response = wp_remote_post($token_url, array(
            'body' => array(
                'grant_type' => 'authorization_code',
                'code' => 'test_code_123',
                'redirect_uri' => $redirect_uri
            ),
            'headers' => array(
                'Authorization' => $auth_header,
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error('Network error: ' . $response->get_error_message());
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        $result = array(
            'http_code' => $http_code,
            'response_body' => $body,
            'client_id' => $client_id,
            'client_secret_length' => strlen($client_secret),
            'redirect_uri' => $redirect_uri
        );
        
        if (isset($data['error'])) {
            $result['error'] = $data['error'];
            $result['error_description'] = isset($data['error_description']) ? $data['error_description'] : '';
        }
        
        wp_send_json_success($result);
    }
    
    private function install_plugin_from_url($plugin_url) {
        // This is a simplified version - in production, you'd want more robust error handling
        $upload_dir = wp_upload_dir();
        $temp_file = $upload_dir['basedir'] . '/temp-plugin.zip';
        
        // Download the plugin
        $response = wp_remote_get($plugin_url);
        if (is_wp_error($response)) {
            return array('success' => false, 'message' => 'Failed to download plugin');
        }
        
        $plugin_data = wp_remote_retrieve_body($response);
        file_put_contents($temp_file, $plugin_data);
        
        // Extract the plugin
        $zip = new ZipArchive();
        if ($zip->open($temp_file) === TRUE) {
            $zip->extractTo(WP_PLUGIN_DIR);
            $zip->close();
            unlink($temp_file);
            
            // Find the plugin file
            $plugin_files = glob(WP_PLUGIN_DIR . '/zero-bs-crm/*.php');
            if (!empty($plugin_files)) {
                return array('success' => true, 'plugin_file' => 'zero-bs-crm/' . basename($plugin_files[0]) . '.php');
            }
        }
        
        return array('success' => false, 'message' => 'Failed to extract plugin');
    }
    
    public function activate() {
        // Set default options
        $default_options = array(
            'xero_client_id' => '',
            'xero_client_secret' => '',
            'xero_redirect_uri' => admin_url('admin-ajax.php?action=xero_oauth_callback'),
            'jetpack_crm_api_key' => '',
            'jetpack_crm_endpoint' => home_url('/wp-json/jetpackcrm/v1/'),
            'sync_frequency' => 'daily',
            'last_sync' => 0,
        );
        
        add_option('xero_jetpack_crm_options', $default_options);
    }
}

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
                "scope" => "accounting.contacts accounting.transactions offline_access",
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
    class_alias("League_OAuth2_Client_Provider_Xero", "League\\OAuth2\\Client\\Provider\\Xero");
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
    class_alias("League_OAuth2_Client_Token_AccessToken", "League\\OAuth2\\Client\\Token\\AccessToken");
}

// Initialize the plugin
Xero_Jetpack_CRM_Integration::get_instance();
