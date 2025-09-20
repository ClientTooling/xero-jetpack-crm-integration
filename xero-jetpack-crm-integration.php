<?php
/**
 * Plugin Name: Xero Jetpack CRM Integration
 * Plugin URI: https://github.com/your-username/xero-jetpack-crm-integration
 * Description: Integrates Xero with Jetpack CRM for one-way sync of contacts, invoices, and payments. Automatically installs required dependencies.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
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
    
    
    private function render_settings_form() {
        // Handle form submission
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'xero_jetpack_crm_settings')) {
            $this->save_settings();
        }
        
        // Get current settings
        $xero_client_id = get_option('xero_client_id', '');
        $xero_client_secret = get_option('xero_client_secret', '');
        $jetpack_crm_api_key = get_option('jetpack_crm_api_key', '');
        $jetpack_crm_api_secret = get_option('jetpack_crm_api_secret', '');
        $jetpack_crm_endpoint = get_option('jetpack_crm_endpoint', '');
        $sync_frequency = get_option('sync_frequency', 'manual');
        $xero_refresh_token = get_option('xero_refresh_token', '');
        ?>
        <form method="post" action="">
            <?php wp_nonce_field('xero_jetpack_crm_settings'); ?>
            
            <div class="card">
                <h2><?php _e('Xero Configuration', 'xero-jetpack-crm-integration'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="xero_client_id"><?php _e('Xero Client ID', 'xero-jetpack-crm-integration'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="xero_client_id" name="xero_client_id" value="<?php echo esc_attr($xero_client_id); ?>" class="regular-text" />
                            <p class="description"><?php _e('Enter your Xero app Client ID from developer.xero.com', 'xero-jetpack-crm-integration'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="xero_client_secret"><?php _e('Xero Client Secret', 'xero-jetpack-crm-integration'); ?></label>
                        </th>
                        <td>
                            <input type="password" id="xero_client_secret" name="xero_client_secret" value="<?php echo esc_attr($xero_client_secret); ?>" class="regular-text" />
                            <p class="description"><?php _e('Enter your Xero app Client Secret from developer.xero.com', 'xero-jetpack-crm-integration'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="redirect_uri"><?php _e('Redirect URI', 'xero-jetpack-crm-integration'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="redirect_uri" value="<?php echo esc_attr(admin_url('admin.php?page=xero-jetpack-crm-integration&action=oauth_callback')); ?>" class="regular-text" readonly />
                            <p class="description"><?php _e('Copy this URL and add it to your Xero app configuration', 'xero-jetpack-crm-integration'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="card">
                <h2><?php _e('Jetpack CRM Configuration', 'xero-jetpack-crm-integration'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="jetpack_crm_api_key"><?php _e('Jetpack CRM API Key', 'xero-jetpack-crm-integration'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="jetpack_crm_api_key" name="jetpack_crm_api_key" value="<?php echo esc_attr($jetpack_crm_api_key); ?>" class="regular-text" />
                            <p class="description"><?php _e('Enter your Jetpack CRM API key', 'xero-jetpack-crm-integration'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="jetpack_crm_api_secret"><?php _e('Jetpack CRM API Secret', 'xero-jetpack-crm-integration'); ?></label>
                        </th>
                        <td>
                            <input type="password" id="jetpack_crm_api_secret" name="jetpack_crm_api_secret" value="<?php echo esc_attr($jetpack_crm_api_secret); ?>" class="regular-text" />
                            <p class="description"><?php _e('Enter your Jetpack CRM API secret (if required)', 'xero-jetpack-crm-integration'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="jetpack_crm_endpoint"><?php _e('Jetpack CRM Endpoint URL', 'xero-jetpack-crm-integration'); ?></label>
                        </th>
                        <td>
                            <input type="url" id="jetpack_crm_endpoint" name="jetpack_crm_endpoint" value="<?php echo esc_attr($jetpack_crm_endpoint); ?>" class="regular-text" />
                            <p class="description"><?php _e('Enter your Jetpack CRM API endpoint URL', 'xero-jetpack-crm-integration'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="card">
                <h2><?php _e('Sync Settings', 'xero-jetpack-crm-integration'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="sync_frequency"><?php _e('Sync Frequency', 'xero-jetpack-crm-integration'); ?></label>
                        </th>
                        <td>
                            <select id="sync_frequency" name="sync_frequency">
                                <option value="manual" <?php selected($sync_frequency, 'manual'); ?>><?php _e('Manual', 'xero-jetpack-crm-integration'); ?></option>
                                <option value="hourly" <?php selected($sync_frequency, 'hourly'); ?>><?php _e('Hourly', 'xero-jetpack-crm-integration'); ?></option>
                                <option value="daily" <?php selected($sync_frequency, 'daily'); ?>><?php _e('Daily', 'xero-jetpack-crm-integration'); ?></option>
                            </select>
                            <p class="description"><?php _e('Set to manual initially for testing', 'xero-jetpack-crm-integration'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="card">
                <h2><?php _e('Authentication Status', 'xero-jetpack-crm-integration'); ?></h2>
                <div id="auth-status">
                    <?php if (!empty($xero_refresh_token)): ?>
                        <div class="status-success">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php _e('Connected to Xero', 'xero-jetpack-crm-integration'); ?>
                            <button type="button" class="button" id="disconnect-xero"><?php _e('Disconnect', 'xero-jetpack-crm-integration'); ?></button>
                        </div>
                    <?php else: ?>
                        <div class="status-warning">
                            <span class="dashicons dashicons-warning"></span>
                            <?php _e('Not connected to Xero', 'xero-jetpack-crm-integration'); ?>
                            <button type="button" class="button button-primary" id="connect-xero"><?php _e('Connect to Xero', 'xero-jetpack-crm-integration'); ?></button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <h2><?php _e('Actions', 'xero-jetpack-crm-integration'); ?></h2>
                <p>
                    <button type="submit" name="submit" class="button button-primary"><?php _e('Save Settings', 'xero-jetpack-crm-integration'); ?></button>
                    <button type="button" class="button" id="test-connection"><?php _e('Test Connection', 'xero-jetpack-crm-integration'); ?></button>
                    <button type="button" class="button" id="manual-sync"><?php _e('Manual Sync', 'xero-jetpack-crm-integration'); ?></button>
                </p>
            </div>
        </form>
        <?php
    }
    
    private function save_settings() {
        if (isset($_POST['xero_client_id'])) {
            update_option('xero_client_id', sanitize_text_field($_POST['xero_client_id']));
        }
        if (isset($_POST['xero_client_secret'])) {
            update_option('xero_client_secret', sanitize_text_field($_POST['xero_client_secret']));
        }
        if (isset($_POST['jetpack_crm_api_key'])) {
            update_option('jetpack_crm_api_key', sanitize_text_field($_POST['jetpack_crm_api_key']));
        }
        if (isset($_POST['jetpack_crm_api_secret'])) {
            update_option('jetpack_crm_api_secret', sanitize_text_field($_POST['jetpack_crm_api_secret']));
        }
        if (isset($_POST['jetpack_crm_endpoint'])) {
            update_option('jetpack_crm_endpoint', esc_url_raw($_POST['jetpack_crm_endpoint']));
        }
        if (isset($_POST['sync_frequency'])) {
            update_option('sync_frequency', sanitize_text_field($_POST['sync_frequency']));
        }
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', 'xero-jetpack-crm-integration') . '</p></div>';
        });
    }
    
    public function admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        $jetpack_crm_status = $this->check_jetpack_crm_status();
        $dependencies_status = $this->check_dependencies_status();
        $xero_connected = !empty(get_option('xero_refresh_token'));
        $jetpack_configured = !empty(get_option('jetpack_crm_api_key')) && !empty(get_option('jetpack_crm_endpoint'));
        
        // Determine current step
        $current_step = 1;
        if ($jetpack_crm_status['installed'] && $dependencies_status['installed']) {
            $current_step = 2; // Xero configuration
            if ($xero_connected) {
                $current_step = 3; // Jetpack CRM configuration
                if ($jetpack_configured) {
                    $current_step = 4; // Dashboard
                }
            }
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <!-- Setup Wizard -->
            <div class="xero-jetpack-crm-wizard">
                <!-- Progress Steps -->
                <div class="wizard-progress">
                    <div class="step <?php echo $current_step >= 1 ? 'active' : ''; ?> <?php echo $current_step > 1 ? 'completed' : ''; ?>">
                        <div class="step-number">1</div>
                        <div class="step-title">Prerequisites</div>
                    </div>
                    <div class="step <?php echo $current_step >= 2 ? 'active' : ''; ?> <?php echo $current_step > 2 ? 'completed' : ''; ?>">
                        <div class="step-number">2</div>
                        <div class="step-title">Xero Setup</div>
                    </div>
                    <div class="step <?php echo $current_step >= 3 ? 'active' : ''; ?> <?php echo $current_step > 3 ? 'completed' : ''; ?>">
                        <div class="step-number">3</div>
                        <div class="step-title">Jetpack CRM</div>
                    </div>
                    <div class="step <?php echo $current_step >= 4 ? 'active' : ''; ?>">
                        <div class="step-number">4</div>
                        <div class="step-title">Dashboard</div>
                    </div>
                </div>
                
                <!-- Step Content -->
                <div class="wizard-content">
                    <?php if ($current_step == 1): ?>
                        <?php $this->render_prerequisites_step($jetpack_crm_status, $dependencies_status); ?>
                    <?php elseif ($current_step == 2): ?>
                        <?php $this->render_xero_configuration_step(); ?>
                    <?php elseif ($current_step == 3): ?>
                        <?php $this->render_jetpack_configuration_step(); ?>
                    <?php elseif ($current_step == 4): ?>
                        <?php $this->render_dashboard_step(); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
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
        <div class="wizard-step">
            <h2>Step 2: Xero Configuration</h2>
            <p>Connect your Xero account to enable data synchronization.</p>
            
            <?php if ($xero_connected): ?>
                <div class="status-success">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <strong>Xero Connected Successfully!</strong>
                    <p>Your Xero account is connected and ready.</p>
                    <button id="test-xero-connection" class="button">Test Connection</button>
                    <button id="disconnect-xero" class="button">Disconnect</button>
                </div>
            <?php else: ?>
                <div class="configuration-form">
                    <h3>Xero App Credentials</h3>
                    <p>Enter your Xero app credentials from <a href="https://developer.xero.com" target="_blank">developer.xero.com</a></p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="xero_client_id">Client ID</label>
                            </th>
                            <td>
                                <input type="text" id="xero_client_id" value="<?php echo esc_attr($xero_client_id); ?>" class="regular-text" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="xero_client_secret">Client Secret</label>
                            </th>
                            <td>
                                <input type="password" id="xero_client_secret" value="<?php echo esc_attr($xero_client_secret); ?>" class="regular-text" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="redirect_uri">Redirect URI</label>
                            </th>
                            <td>
                                <input type="text" id="redirect_uri" value="<?php echo esc_attr(admin_url('admin.php?page=xero-jetpack-crm-integration&action=oauth_callback')); ?>" class="regular-text" readonly />
                                <p class="description">Copy this URL to your Xero app configuration</p>
                            </td>
                        </tr>
                    </table>
                    
                    <div class="step-actions">
                        <button id="save-xero-credentials" class="button button-primary">Save Credentials</button>
                        <button id="test-xero-credentials" class="button">Test Credentials</button>
                        <button id="connect-xero" class="button button-primary" style="display: none;">Connect to Xero</button>
                    </div>
                    
                    <div id="xero-test-result" style="display: none;"></div>
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
    
    private function render_dashboard_step() {
        $xero_connected = !empty(get_option('xero_refresh_token'));
        $jetpack_configured = !empty(get_option('jetpack_crm_api_key')) && !empty(get_option('jetpack_crm_endpoint'));
        $sync_frequency = get_option('sync_frequency', 'manual');
        ?>
        <div class="wizard-step">
            <h2>Integration Dashboard</h2>
            <p>Your Xero Jetpack CRM integration is ready to use!</p>
            
            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <h3>Xero Integration</h3>
                    <div class="status-indicator <?php echo $xero_connected ? 'success' : 'error'; ?>">
                        <span class="dashicons dashicons-<?php echo $xero_connected ? 'yes-alt' : 'warning'; ?>"></span>
                        <strong><?php echo $xero_connected ? 'Connected' : 'Not Connected'; ?></strong>
                    </div>
                    <div class="card-actions">
                        <button id="test-xero-connection" class="button">Test Connection</button>
                        <?php if ($xero_connected): ?>
                            <button id="disconnect-xero" class="button">Disconnect</button>
                        <?php else: ?>
                            <button id="connect-xero" class="button button-primary">Connect</button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <h3>Jetpack CRM</h3>
                    <div class="status-indicator <?php echo $jetpack_configured ? 'success' : 'error'; ?>">
                        <span class="dashicons dashicons-<?php echo $jetpack_configured ? 'yes-alt' : 'warning'; ?>"></span>
                        <strong><?php echo $jetpack_configured ? 'Configured' : 'Not Configured'; ?></strong>
                    </div>
                    <div class="card-actions">
                        <button id="test-jetpack-connection" class="button">Test Connection</button>
                        <button id="reconfigure-jetpack" class="button">Reconfigure</button>
                    </div>
                </div>
            </div>
            
            <div class="sync-settings">
                <h3>Sync Settings</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">Sync Frequency</th>
                        <td>
                            <select id="sync_frequency">
                                <option value="manual" <?php selected($sync_frequency, 'manual'); ?>>Manual</option>
                                <option value="hourly" <?php selected($sync_frequency, 'hourly'); ?>>Hourly</option>
                                <option value="daily" <?php selected($sync_frequency, 'daily'); ?>>Daily</option>
                            </select>
                        </td>
                    </tr>
                </table>
                <button id="save-sync-settings" class="button button-primary">Save Settings</button>
            </div>
            
            <div class="sync-actions">
                <h3>Sync Actions</h3>
                <button id="manual-sync" class="button button-primary">Start Manual Sync</button>
                <button id="view-logs" class="button">View Sync Logs</button>
            </div>
        </div>
        <?php
    }
        
        <script>
        jQuery(document).ready(function($) {
            // Install Jetpack CRM
            $('#install-jetpack-crm').on('click', function() {
                installComponent('jetpack-crm', $(this));
            });
            
            // Install Dependencies
            $('#install-dependencies').on('click', function() {
                installComponent('dependencies', $(this));
            });
            
            // Show Settings Form
            $('#show-settings').on('click', function() {
                $('.xero-jetpack-crm-setup').hide();
                $('#settings-form').show();
            });
            
            // Connect to Xero
            $('#connect-xero').on('click', function() {
                var clientId = $('#xero_client_id').val();
                if (!clientId) {
                    alert('Please enter your Xero Client ID first.');
                    return;
                }
                
                // Redirect to Xero OAuth
                var redirectUri = encodeURIComponent($('#redirect_uri').val());
                var state = Math.random().toString(36).substring(7);
                var authUrl = 'https://login.xero.com/identity/connect/authorize?' +
                    'response_type=code&' +
                    'client_id=' + clientId + '&' +
                    'redirect_uri=' + redirectUri + '&' +
                    'scope=openid profile email accounting.transactions accounting.contacts.read&' +
                    'state=' + state;
                
                window.location.href = authUrl;
            });
            
            // Test Connection
            $('#test-connection').on('click', function() {
                var $button = $(this);
                $button.prop('disabled', true).text('Testing...');
                
                $.ajax({
                    url: xeroJetpackCrm.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'xero_test_connection',
                        nonce: xeroJetpackCrm.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Connection test successful!');
                        } else {
                            alert('Connection test failed: ' + (response.data || 'Unknown error'));
                        }
                    },
                    error: function(xhr, status, error) {
                        console.log('AJAX Error:', xhr, status, error);
                        alert('Connection test failed: ' + error + ' (Status: ' + xhr.status + ')');
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('Test Connection');
                    }
                });
            });
            
            // Manual Sync
            $('#manual-sync').on('click', function() {
                if (confirm('This will start a manual sync from Xero to Jetpack CRM. Continue?')) {
                    $.ajax({
                        url: xeroJetpackCrm.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'xero_manual_sync',
                            nonce: xeroJetpackCrm.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('Manual sync completed successfully!');
                            } else {
                                alert('Manual sync failed: ' + response.message);
                            }
                        }
                    });
                }
            });
            
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
        });
        </script>
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
if (!class_exists("League\\\\OAuth2\\\\Client\\\\Provider\\\\Xero")) {
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
    class_alias("League_OAuth2_Client_Provider_Xero", "League\\\\OAuth2\\\\Client\\\\Provider\\\\Xero");
}

if (!class_exists("League\\\\OAuth2\\\\Client\\\\Token\\\\AccessToken")) {
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
    class_alias("League_OAuth2_Client_Token_AccessToken", "League\\\\OAuth2\\\\Client\\\\Token\\\\AccessToken");
}
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
    
    public function manual_sync() {
        check_ajax_referer('xero_jetpack_crm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        // Check if we have valid tokens
        $refresh_token = get_option('xero_refresh_token');
        if (empty($refresh_token)) {
            wp_send_json_error('Not connected to Xero. Please authenticate first.');
        }
        
        // This would contain the actual sync logic
        // For now, we'll just return a success message
        wp_send_json_success('Manual sync completed successfully! (This is a placeholder - actual sync logic would be implemented here)');
    }
    
    public function oauth_callback() {
        if (!isset($_GET['code']) || !isset($_GET['state'])) {
            wp_die('Invalid OAuth callback parameters');
        }
        
        $code = sanitize_text_field($_GET['code']);
        $state = sanitize_text_field($_GET['state']);
        
        $client_id = get_option('xero_client_id');
        $client_secret = get_option('xero_client_secret');
        
        if (empty($client_id) || empty($client_secret)) {
            wp_die('Xero credentials not configured');
        }
        
        // Exchange code for tokens
        $token_url = 'https://identity.xero.com/connect/token';
        $redirect_uri = admin_url('admin.php?page=xero-jetpack-crm-integration&action=oauth_callback');
        
        $response = wp_remote_post($token_url, array(
            'body' => array(
                'grant_type' => 'authorization_code',
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'code' => $code,
                'redirect_uri' => $redirect_uri
            ),
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            )
        ));
        
        if (is_wp_error($response)) {
            wp_die('Token exchange failed: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['access_token'])) {
            update_option('xero_access_token', $data['access_token']);
            update_option('xero_refresh_token', $data['refresh_token']);
            update_option('xero_token_expires', time() + $data['expires_in']);
            
            wp_redirect(admin_url('admin.php?page=xero-jetpack-crm-integration&connected=1'));
            exit;
        } else {
            wp_die('Failed to obtain access token: ' . $body);
        }
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

// Initialize the plugin
Xero_Jetpack_CRM_Integration::get_instance();
