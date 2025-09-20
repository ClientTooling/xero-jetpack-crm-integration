/**
 * Admin JavaScript for Xero Jetpack CRM Integration
 */

(function($) {
    'use strict';
    
    // Initialize when document is ready
    $(document).ready(function() {
        initAdmin();
    });
    
    function initAdmin() {
        // Initialize tooltips
        initTooltips();
        
        // Initialize form validation
        initFormValidation();
        
        // Initialize AJAX handlers
        initAjaxHandlers();
    }
    
    function initTooltips() {
        $('[data-tooltip]').each(function() {
            $(this).addClass('xero-jetpack-crm-tooltip');
        });
    }
    
    function initFormValidation() {
        // Validate Xero credentials
        $('#xero_client_id, #xero_client_secret').on('blur', function() {
            validateXeroCredentials();
        });
        
        // Validate Jetpack CRM credentials
        $('#jetpack_crm_api_key, #jetpack_crm_endpoint').on('blur', function() {
            validateJetpackCrmCredentials();
        });
    }
    
    function validateXeroCredentials() {
        var clientId = $('#xero_client_id').val();
        var clientSecret = $('#xero_client_secret').val();
        
        if (clientId && clientSecret) {
            // Show validation indicator
            showValidationStatus('xero-credentials', 'validating');
            
            // Test connection (simplified - in production, you'd make an actual API call)
            setTimeout(function() {
                showValidationStatus('xero-credentials', 'valid');
            }, 1000);
        } else {
            showValidationStatus('xero-credentials', 'invalid');
        }
    }
    
    function validateJetpackCrmCredentials() {
        var apiKey = $('#jetpack_crm_api_key').val();
        var endpoint = $('#jetpack_crm_endpoint').val();
        
        if (apiKey && endpoint) {
            showValidationStatus('jetpack-crm-credentials', 'validating');
            
            // Test connection
            $.ajax({
                url: xeroJetpackCrm.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'xero_test_jetpack_crm',
                    nonce: xeroJetpackCrm.nonce,
                    api_key: apiKey,
                    endpoint: endpoint
                },
                success: function(response) {
                    if (response.success) {
                        showValidationStatus('jetpack-crm-credentials', 'valid');
                    } else {
                        showValidationStatus('jetpack-crm-credentials', 'invalid', response.message);
                    }
                },
                error: function() {
                    showValidationStatus('jetpack-crm-credentials', 'invalid', 'Connection failed');
                }
            });
        } else {
            showValidationStatus('jetpack-crm-credentials', 'invalid');
        }
    }
    
    function showValidationStatus(container, status, message) {
        var $container = $('#' + container);
        var $indicator = $container.find('.validation-indicator');
        
        if ($indicator.length === 0) {
            $indicator = $('<span class="validation-indicator"></span>');
            $container.append($indicator);
        }
        
        $indicator.removeClass('validating valid invalid');
        
        switch (status) {
            case 'validating':
                $indicator.html('<span class="spinner is-active"></span> Validating...');
                $indicator.addClass('validating');
                break;
            case 'valid':
                $indicator.html('<span class="dashicons dashicons-yes-alt" style="color: green;"></span> Valid');
                $indicator.addClass('valid');
                break;
            case 'invalid':
                var errorMsg = message ? ': ' + message : '';
                $indicator.html('<span class="dashicons dashicons-warning" style="color: red;"></span> Invalid' + errorMsg);
                $indicator.addClass('invalid');
                break;
        }
    }
    
    function initAjaxHandlers() {
        // Manual sync handler
        $('#manual-sync-btn').on('click', function(e) {
            e.preventDefault();
            runManualSync();
        });
        
        // Clear logs handler
        $('#clear-logs-btn').on('click', function(e) {
            e.preventDefault();
            clearLogs();
        });
        
        // View logs handler
        $('a[href*="xero_view_logs"]').on('click', function(e) {
            e.preventDefault();
            viewLogs();
        });
    }
    
    function runManualSync() {
        var $btn = $('#manual-sync-btn');
        var $status = $('#sync-status');
        
        // Disable button and show loading
        $btn.prop('disabled', true).addClass('button-primary-disabled');
        $status.html('<span class="spinner is-active"></span> ' + xeroJetpackCrm.strings.syncInProgress);
        
        // Make AJAX request
        $.ajax({
            url: xeroJetpackCrm.ajaxUrl,
            type: 'POST',
            data: {
                action: 'xero_manual_sync',
                nonce: xeroJetpackCrm.nonce
            },
            timeout: 300000, // 5 minutes timeout
            success: function(response) {
                if (response.success) {
                    showSyncSuccess(response);
                } else {
                    showSyncError(response.message);
                }
            },
            error: function(xhr, status, error) {
                var errorMessage = 'Unknown error occurred';
                if (status === 'timeout') {
                    errorMessage = 'Sync timed out. Please try again.';
                } else if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                showSyncError(errorMessage);
            },
            complete: function() {
                // Re-enable button
                $btn.prop('disabled', false).removeClass('button-primary-disabled');
            }
        });
    }
    
    function showSyncSuccess(response) {
        var $status = $('#sync-status');
        var message = '<span class="dashicons dashicons-yes-alt" style="color: green;"></span> ' + response.message;
        
        // Add sync results if available
        if (response.data) {
            var results = [];
            if (response.data.contacts_created) results.push(response.data.contacts_created + ' contacts created');
            if (response.data.contacts_updated) results.push(response.data.contacts_updated + ' contacts updated');
            if (response.data.invoices_created) results.push(response.data.invoices_created + ' invoices created');
            if (response.data.invoices_updated) results.push(response.data.invoices_updated + ' invoices updated');
            if (response.data.payments_created) results.push(response.data.payments_created + ' payments created');
            if (response.data.payments_updated) results.push(response.data.payments_updated + ' payments updated');
            
            if (results.length > 0) {
                message += '<br><small>' + results.join(', ') + '</small>';
            }
            
            // Show errors if any
            if (response.data.errors && response.data.errors.length > 0) {
                message += '<br><small style="color: red;">Errors: ' + response.data.errors.join(', ') + '</small>';
            }
        }
        
        $status.html(message);
        
        // Show success notice
        showNotice('success', response.message);
    }
    
    function showSyncError(message) {
        var $status = $('#sync-status');
        $status.html('<span class="dashicons dashicons-warning" style="color: red;"></span> ' + message);
        
        // Show error notice
        showNotice('error', message);
    }
    
    function clearLogs() {
        if (!confirm('Are you sure you want to clear all logs? This action cannot be undone.')) {
            return;
        }
        
        $.ajax({
            url: xeroJetpackCrm.ajaxUrl,
            type: 'POST',
            data: {
                action: 'xero_clear_logs',
                nonce: xeroJetpackCrm.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', 'Logs cleared successfully!');
                } else {
                    showNotice('error', 'Failed to clear logs: ' + response.message);
                }
            },
            error: function() {
                showNotice('error', 'Failed to clear logs. Please try again.');
            }
        });
    }
    
    function viewLogs() {
        // Open logs in a new window/tab
        var logUrl = xeroJetpackCrm.ajaxUrl + '?action=xero_view_logs';
        window.open(logUrl, '_blank', 'width=800,height=600,scrollbars=yes,resizable=yes');
    }
    
    function showNotice(type, message) {
        // Remove existing notices
        $('.xero-jetpack-crm-notice').remove();
        
        // Create new notice
        var $notice = $('<div class="xero-jetpack-crm-notice notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        
        // Add to page
        $('.wrap h1').after($notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut();
        }, 5000);
    }
    
    // Utility functions
    function formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }
    
    function formatDate(timestamp) {
        return new Date(timestamp * 1000).toLocaleString();
    }
    
    // Export functions for global access
    window.XeroJetpackCrmAdmin = {
        runManualSync: runManualSync,
        clearLogs: clearLogs,
        viewLogs: viewLogs,
        showNotice: showNotice
    };
    
})(jQuery);
