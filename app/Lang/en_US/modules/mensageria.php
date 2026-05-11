<?php

/**
 * Messaging module translations - English (US)
 */

return [
    'title' => 'Messaging WhatsApp, SMS and SMTP',
    'subtitle' => 'Messaging: WhatsApp, SMS and SMTP(Mail)',

    // Connection types
    'types' => [
        'whatsapp' => 'WhatsApp',
        'sms' => 'SMS',
        'smtp' => 'SMTP (Mail)',
    ],

    // Common (shared between sub-views)
    'common' => [
        'connection' => 'Connection',
        'branches_label' => 'Companies/Branches',
        'branches_desc' => 'Select the companies that will use this connection',
        'no_branches' => 'No companies available',
        'already_linked' => 'Already linked',
        'none' => 'None',
        'load_error' => 'Error loading data',
        'load_branches_error' => 'Error loading companies',
        'load_connection_error' => 'Error loading connection',
        'fill_required' => 'Fill in all required fields',
        'select_branch' => 'Select at least one company',
        'connection_id_missing' => 'Connection ID not provided',
    ],

    // Table
    'table' => [
        'type' => 'Type',
        'linked_branches' => 'Linked Companies',
        'identifier' => 'Identifier',
        'status' => 'Status',
        'actions' => 'Actions',
        'no_records' => 'No connections found',
        'load_error_branches' => 'Error loading',
    ],

    // Buttons
    'buttons' => [
        'new_whatsapp' => 'New WhatsApp',
        'new_sms' => 'New SMS',
        'new_smtp' => 'New SMTP',
    ],

    // Search
    'search_placeholder' => 'Search connection...',

    // Pagination
    'pagination' => [
        'rows_per_page' => 'Rows per page:',
        'showing' => 'Showing :start-:end of :total records',
    ],

    // Status badges
    'status' => [
        'connected' => 'Connected',
        'connecting' => 'Connecting',
        'disconnected' => 'Disconnected',
        'validated' => 'Validated',
        'pending' => 'Pending',
        'invalid' => 'Invalid',
        'unknown' => 'Unknown',
    ],

    // Action titles (table buttons)
    'actions' => [
        'test' => 'Test',
        'restart' => 'Restart',
        'disconnect' => 'Disconnect',
        'connect' => 'Connect',
        'recreate' => 'Recreate connection',
        'test_sms' => 'Test SMS',
        'check_balance' => 'Check Balance',
        'validate_credentials' => 'Validate Credentials',
        'test_email' => 'Test Email',
        'validate_connection' => 'Validate Connection',
    ],

    // Offcanvas titles
    'offcanvas' => [
        'new_whatsapp' => 'New WhatsApp Connection',
        'edit_whatsapp' => 'Edit WhatsApp Connection',
        'connect_whatsapp' => 'Connect WhatsApp',
        'test_whatsapp' => 'Test WhatsApp',
        'new_sms' => 'New SMS Connection',
        'edit_sms' => 'Edit SMS Connection',
        'test_sms' => 'Test SMS',
        'new_smtp' => 'New SMTP Connection',
        'edit_smtp' => 'Edit SMTP Connection',
        'test_smtp' => 'Test SMTP',
    ],

    // Confirmations
    'confirms' => [
        'delete' => 'Do you want to delete the connection ":name"?',
        'disconnect' => 'Do you really want to disconnect this connection?',
        'restart' => 'Do you want to restart this connection? The connection will be re-established.',
    ],

    // Messages
    'messages' => [
        // SMTP
        'smtp_created' => 'SMTP connection created successfully!',
        'smtp_updated' => 'Connection updated successfully!',
        'smtp_deleted' => 'SMTP connection deleted successfully',
        'smtp_validated' => 'SMTP connection validated successfully!',
        'smtp_validation_failed' => 'Validation failed',
        'smtp_create_error' => 'Error creating connection',
        'smtp_update_error' => 'Error updating',
        'smtp_delete_error' => 'Error deleting connection',
        'smtp_validate_error' => 'Error validating',

        // WhatsApp
        'whatsapp_created' => 'Connection created! Scan the QR Code to connect.',
        'whatsapp_created_short' => 'Connection created! Scan the QR Code.',
        'whatsapp_updated' => 'Connection updated successfully!',
        'whatsapp_deleted' => 'WhatsApp connection deleted successfully',
        'whatsapp_disconnected' => 'Disconnected successfully',
        'whatsapp_restarted' => 'Connection restarted. Waiting for reconnection...',
        'whatsapp_recreated' => 'Instance recreated! Opening QR Code...',
        'whatsapp_disconnect_error' => 'Error disconnecting',
        'whatsapp_restart_error' => 'Error restarting',
        'whatsapp_recreate_error' => 'Error recreating',
        'whatsapp_create_error' => 'Error creating connection',
        'whatsapp_update_error' => 'Error updating connection',
        'whatsapp_delete_error' => 'Error deleting connection',

        // SMS
        'sms_created' => 'SMS connection created successfully!',
        'sms_updated' => 'SMS connection updated successfully!',
        'sms_deleted' => 'SMS connection deleted successfully',
        'sms_validated' => 'Credentials validated successfully!',
        'sms_validation_failed' => 'Invalid credentials',
        'sms_create_error' => 'Error creating connection',
        'sms_update_error' => 'Error updating connection',
        'sms_delete_error' => 'Error deleting connection',
        'sms_validate_error' => 'Error validating',
        'sms_balance' => 'Balance: :currency :balance',
        'sms_balance_error' => 'Error checking balance',

        // Tests
        'test_sent' => 'Test sent!',
        'test_success' => 'Sent successfully!',
        'test_error' => 'Error sending',
        'email_sent' => 'Email sent!',
        'email_test_success' => 'Test email sent successfully!',
        'email_test_error' => 'Failed to send test email',
        'email_test_send_error' => 'Error sending test email',
        'sms_sent' => 'SMS sent!',
        'sms_test_success' => 'Test SMS sent successfully!',
        'sms_test_error' => 'Failed to send test SMS',
        'sms_test_send_error' => 'Error sending test SMS',
        'provide_email' => 'Enter an email for testing',
        'provide_valid_email' => 'Enter a valid email',
        'provide_phone' => 'Enter a phone number for testing',
        'provide_valid_phone' => 'Enter a valid phone number',
        'sending_email' => 'Sending email...',
        'sending_sms' => 'Sending SMS...',

        // QR Code
        'qr_generating' => 'Generating QR Code...',
        'qr_scan' => 'Scan the QR Code with your WhatsApp',
        'qr_error' => 'Error generating QR Code',
        'qr_connect_error' => 'Error connecting',
        'qr_waiting' => 'Waiting for connection...',
        'qr_connected' => 'Connected!',
        'server_error' => 'Error connecting to the server',
    ],

    // SMTP specific
    'smtp' => [
        'provider' => 'Provider',
        'connection_name' => 'Connection Name',
        'server' => 'SMTP Server',
        'port' => 'Port',
        'encryption' => 'Encryption',
        'encryption_none' => 'None',
        'auth_email' => 'Authentication Email',
        'password' => 'Password / App Password',
        'from_email' => 'Sender Email',
        'from_name' => 'Sender Name',
        'reply_to' => 'Reply-To Email (optional)',
        'daily_limit' => 'Daily Limit (optional)',
        'daily_limit_hint' => 'Leave empty for no limit',
        'password_hint_gmail' => 'For Gmail, use an <a href="https://support.google.com/accounts/answer/185833" target="_blank" class="text-blue-600 hover:underline">app password</a>',
        'password_hint_custom' => 'Consult your SMTP provider documentation',
        'password_hint_default' => 'Use the password or App Password from your provider',
        'password_change_hint' => 'Changing the password will revalidate the connection',
        'keep_blank' => 'Leave blank to keep current',
        'provider_settings' => 'Provider settings:',
        'create_validate' => 'Create and Validate Connection',
        'test_email_label' => 'Email for testing',
        'test_email_hint' => 'A test email will be sent to this address',
        'send_test' => 'Send Test Email',
    ],

    // SMTP placeholders
    'smtp_placeholders' => [
        'name' => 'E.g.: Main Email',
        'server' => 'smtp.yourserver.com',
        'auth_email' => 'your@email.com',
        'password' => 'Password or app password',
        'from_email' => 'noreply@yourcompany.com',
        'from_name' => 'Your Company',
        'reply_to' => 'contact@yourcompany.com',
        'daily_limit' => 'E.g.: 500',
    ],

    // WhatsApp specific
    'whatsapp' => [
        'create_connection' => 'Create WhatsApp Connection',
        'send_text' => 'Send Text',
        'send_image' => 'Send Image',
        'send_document' => 'Send Document',
        'instance_label' => 'Instance',
    ],

    // SMS specific
    'sms' => [
        'provider' => 'Provider',
        'sender_id' => 'Sender ID',
        'sender_id_hint' => 'Max 11 alphanumeric characters',
        'username' => 'ClickSend Username',
        'api_key' => 'API Key',
        'api_credentials_hint' => 'Find at: ClickSend Dashboard > Developers > API Credentials',
        'api_key_change_hint' => 'Changing the API Key will revalidate the credentials',
        'create_validate' => 'Create and Validate',
        'test_phone_label' => 'Phone for testing',
        'test_phone_hint' => 'Format: country code + area code + number',
        'test_phone_placeholder' => '+1 (555) 999-9999',
        'send_test' => 'Send Test SMS',
        'sender_id_short' => 'Sender ID',
    ],
];
