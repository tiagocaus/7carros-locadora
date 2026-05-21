<?php

/**
 * Message Templates Translations - English (US)
 *
 * Contains names and descriptions of available template types.
 */

return [
    // Template Types
    'types' => [
        // Onboarding
        'welcome' => 'Welcome',
        'welcome_description' => 'Message sent when a new customer is registered',
        'welcome_desc' => 'Message sent when a new customer is registered',

        'cliente_nova_senha' => 'Customer password reset',
        'cliente_nova_senha_desc' => 'Sent to the customer with a new access password',
        'cliente_nova_senha_link_desc' => 'Sent to the customer with a secure password reset link',

        'funcionario_nova_senha' => 'Employee password reset',
        'funcionario_nova_senha_desc' => 'Sent to the employee with a new secure panel access password',

        // Rental
        'rental_confirmation' => 'Rental Confirmation',
        'rental_confirmation_description' => 'Sent when a rental is confirmed',

        'contract_confirmation' => 'Contract Confirmation',
        'contract_confirmation_description' => 'Sent when a contract is signed',

        // Reminders
        'return_reminder' => 'Return Reminder',
        'return_reminder_description' => 'Notice before the scheduled return date',

        'cnh_expiring' => 'License Expiring',
        'cnh_expiring_description' => 'Notice when the customer\'s driver\'s license is about to expire',

        // Billing
        'payment_reminder' => 'Payment Reminder',
        'payment_reminder_description' => 'Notice of invoice due soon',

        'invoice_generated' => 'Invoice Generated',
        'invoice_generated_description' => 'Sent when a new invoice is generated',

        'overdue_notice' => 'Overdue Notice',
        'overdue_notice_description' => 'Notification of overdue invoice',

        'payment_received' => 'Payment Received',
        'payment_received_description' => 'Confirmation of payment receipt',

        // Other
        'general_notification' => 'General Notification',
        'general_notification_description' => 'Template for miscellaneous notifications',
    ],

    // Categories
    'categories' => [
        'onboarding' => 'Onboarding',
        'rental' => 'Rental',
        'reminder' => 'Reminders',
        'billing' => 'Billing',
        'notification' => 'Notifications',
    ],

    // Channels
    'channels' => [
        'email' => 'Email',
        'sms' => 'SMS',
        'whatsapp' => 'WhatsApp',
    ],

    // UI Messages
    'ui' => [
        'title' => 'Message Templates',
        'subtitle' => 'Customize the messages sent to customers',
        'search_placeholder' => 'Search templates...',
        'select_template' => 'Select a template to edit',
        'available_variables' => 'Available Variables',
        'preview' => 'Preview',
        'editor' => 'Editor',
        'restore_default' => 'Restore Default',
        'save_changes' => 'Save Changes',
        'unsaved_changes' => 'You have unsaved changes. Do you want to leave?',
        'template_saved' => 'Template saved successfully!',
        'template_restored' => 'Template restored to default',
        'no_templates' => 'No templates available',
        'custom_template' => 'Custom',
        'default_template' => 'Default',
        'subject' => 'Subject',
        'content' => 'Content',
        'content_plain' => 'Content (plain text)',
        'locale' => 'Language',
        'channel' => 'Channel',
        'insert_variable' => 'Click to insert',
    ],

    // Validation
    'validation' => [
        'entity_not_allowed' => 'The entity ":entity" is not allowed in this template',
        'variable_not_found' => 'The variable ":variable" does not exist',
        'content_required' => 'Template content is required',
        'subject_required_email' => 'Subject is required for email templates',
    ],
];
