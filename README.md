# Xero Jetpack CRM Integration

A WordPress plugin that integrates Xero with Jetpack CRM for one-way synchronization of contacts, invoices, and payments. This plugin is designed to be completely user-friendly with automatic dependency installation.

## Features

- **One-way sync** from Xero to Jetpack CRM
- **Contact synchronization** with automatic deduplication
- **Invoice and payment sync** with detailed descriptions
- **OAuth 2.0 authentication** with Xero
- **Configurable sync frequency** (hourly, daily, or manual)
- **Secure credential storage** with encryption
- **Comprehensive logging** for debugging
- **User-friendly interface** with automatic installation
- **Progress bars** for visual feedback
- **Zero technical knowledge required**

## Requirements

- WordPress 6.0 or higher
- PHP 7.4 or higher
- Jetpack CRM plugin (installed automatically)
- Xero Developer account
- SSL certificate (required for OAuth)

## Installation

### Method 1: WordPress Admin (Recommended)
1. **Go to WordPress Admin → Plugins → Add New**
2. **Click "Upload Plugin"**
3. **Choose the plugin zip file**
4. **Click "Install Now"**
5. **Activate the plugin**

### Method 2: Manual Upload
1. **Extract the zip file**
2. **Upload the folder** to `/wp-content/plugins/`
3. **Activate the plugin** in WordPress admin

## Setup Guide

### Step 1: Complete Setup
1. **Activate the plugin**
2. **You'll see a setup notice** at the top of your admin
3. **Click "Complete Setup"**
4. **The plugin will automatically**:
   - Check if Jetpack CRM is installed
   - Install Jetpack CRM if needed
   - Install required dependencies
   - Show progress bars during installation

### Step 2: Configure Credentials
1. **Go to Settings → Xero CRM Integration**
2. **Enter your Xero credentials**:
   - Client ID
   - Client Secret
   - Redirect URI (auto-filled)
3. **Enter your Jetpack CRM credentials**:
   - API Key
   - Endpoint URL
4. **Set your preferred sync frequency**
5. **Click "Save Settings"**

### Step 3: Authenticate with Xero
1. **Click "Authenticate with Xero"**
2. **Complete OAuth flow** in Xero
3. **You'll be redirected back** to WordPress

### Step 4: Run First Sync
1. **Click "Run Manual Sync"**
2. **Monitor the progress** and status
3. **Check logs** if needed

## User Interface

### Setup Page
The plugin provides a beautiful, user-friendly setup page with:

- **Status Cards**: Show the status of each component
- **Progress Bars**: Visual feedback during installation
- **One-Click Buttons**: Install everything with a single click
- **Clear Messages**: Easy-to-understand status messages

### Status Indicators
- ✅ **Green**: Component is ready
- ⚠️ **Yellow**: Component needs attention
- ❌ **Red**: Component has an error

## Data Mapping

### Contacts → Customers
- **Name**: First Name + Last Name from Xero
- **Email**: Primary email address
- **Phone**: Mobile or default phone number
- **Company**: Company name
- **Address**: Street address details
- **Custom Field**: Xero Contact ID for deduplication

### Invoices → Transactions
- **Reference**: Invoice Number
- **Amount**: Total amount
- **Date**: Invoice date
- **Status**: Mapped from Xero status
- **Description**: Concatenated line item descriptions
- **Custom Field**: Xero Invoice ID

### Payments → Transactions
- **Reference**: Payment reference
- **Amount**: Payment amount
- **Date**: Payment date
- **Status**: Always "completed"
- **Description**: Payment details or reference
- **Custom Field**: Xero Payment ID

## Sync Rules

- **Initial sync**: Last 12 months of invoices + all active contacts
- **Recurring sync**: Based on configured frequency
- **Deduplication**: By Xero Contact ID or email address
- **Conflict resolution**: Update existing records if data has changed
- **Error handling**: Logs errors and continues processing

## Configuration Options

### Sync Frequency
- **Manual**: Only sync when manually triggered
- **Hourly**: Automatic sync every hour
- **Daily**: Automatic sync once per day

### Data Sources
- **Contacts**: All active contacts from Xero
- **Invoices**: Last 12 months (configurable)
- **Payments**: Last 12 months (configurable)

## Troubleshooting

### Common Issues

1. **"Jetpack CRM is not installed" error**
   - Click "Install Jetpack CRM" button
   - Wait for installation to complete

2. **"Dependencies are not installed" error**
   - Click "Install Dependencies" button
   - Wait for installation to complete

3. **Sync fails with timeout**
   - Check server timeout settings
   - Reduce sync frequency
   - Check logs for specific errors

4. **No data synced**
   - Verify Xero has data in the specified date range
   - Check API permissions in Xero app
   - Review sync logs for errors

### Logs

- **Location**: `/wp-content/uploads/xero-sync-logs/xero-sync.log`
- **View**: Use "View Logs" button in admin interface
- **Clear**: Use "Clear Logs" button to reset
- **Rotation**: Automatic rotation when log exceeds 10MB

## Security

- All credentials are encrypted before storage
- OAuth 2.0 for secure Xero authentication
- Nonce verification for all AJAX requests
- Capability checks for admin functions
- No credentials stored in source code

## Support

For support and bug reports, please:
1. Check the troubleshooting section above
2. Review the sync logs
3. Enable debug mode and check WordPress debug log
4. Create an issue with detailed information

## Changelog

### Version 1.0.0
- Initial release
- One-way sync from Xero to Jetpack CRM
- OAuth 2.0 authentication
- Contact, invoice, and payment synchronization
- User-friendly interface with automatic installation
- Progress bars and visual feedback
- Comprehensive logging system
- Secure credential storage

## License

This plugin is licensed under the GPL v2 or later.

## Credits

- Built for WordPress
- Uses League OAuth2 Client for Xero authentication
- Integrates with Jetpack CRM
- Designed for non-technical users
