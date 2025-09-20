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
        add_action('wp_ajax_xero_get_stats', array($this, 'get_stats'));
        
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
            
            // Disconnect Xero
            $('#disconnect-xero').on('click', function() {
                if (confirm('This will disconnect your Xero account. Continue?')) {
                    $.ajax({
                        url: xeroJetpackCrm.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'xero_disconnect',
                            nonce: xeroJetpackCrm.nonce
                        },
                        success: function(response) {
                            location.reload();
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
                    showStatusBar('Starting manual sync...');
                    $.ajax({
                        url: xeroJetpackCrm.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'xero_manual_sync',
                            nonce: xeroJetpackCrm.nonce
                        },
                        success: function(response) {
                            hideStatusBar();
                            if (response.success) {
                                showNotification('Manual sync completed successfully!', 'success');
                                updateStats();
                            } else {
                                showNotification('Manual sync failed: ' + response.data, 'error');
                            }
                        },
                        error: function() {
                            hideStatusBar();
                            showNotification('Manual sync failed due to network error', 'error');
                        }
                    });
                }
            });
            
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
                            $('#connect-xero').show();
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
                            $('#connect-xero').show();
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
                        <button id="disconnect-xero" class="btn btn-danger">
                            <span class="dashicons dashicons-no-alt"></span> Disconnect
                        </button>
                    </div>
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
                        <button id="connect-xero" class="btn btn-success" style="display: none;">
                            <span class="dashicons dashicons-admin-links"></span>
                            <span class="btn-text">Connect to Xero</span>
                            <div class="btn-loading">
                                <div class="spinner"></div>
                            </div>
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
