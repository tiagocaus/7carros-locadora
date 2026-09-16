<?php

/**
 * Message Templates Translations - English (US)
 *
 * Contains names and descriptions of available template types.
 */

return [
    'installment' => [
        'with_total' => 'Installment :parcela of :total',
        'without_total' => 'Installment :parcela',
    ],
    // Template Types
    'types' => [
        'confirmacao_reserva' => 'Reservation Confirmation',
        'confirmacao_reserva_description' => 'Sent to the customer when the rental company confirms the request in the dashboard',
        'confirmacao_reserva_desc' => 'Sent to the customer when the rental company confirms the request in the dashboard',
        'pedido_reserva' => 'Reservation Request',
        'pedido_reserva_description' => 'Sent to the customer when they submit a reservation request on the website',
        'pedido_reserva_desc' => 'Sent to the customer when they submit a reservation request on the website',
        'signature_request' => 'Signature Request',
        'signature_request_description' => 'Sent to the customer with the digital signature link',
        'signature_request_desc' => 'Sent to the customer with the digital signature link',
        'reserva_nao_confirmada' => 'Reservation not confirmed',
        'reserva_nao_confirmada_desc' => 'Optional notification when deleting a pending reservation request',
        'reserva_nao_confirmada_description' => 'Optional notification when deleting a pending reservation request',
        // Onboarding
        'welcome' => 'Welcome',
        'welcome_description' => 'Message sent when a new customer is registered',
        'welcome_desc' => 'Message sent when a new customer is registered',

        'cliente_nova_senha' => 'Customer password reset',
        'cliente_nova_senha_desc' => 'Sent to the customer with a new access password',
        'cliente_nova_senha_link_desc' => 'Sent to the customer with a secure password reset link',

        'funcionario_nova_senha' => 'Employee password reset',
        'funcionario_nova_senha_desc' => 'Sent to the employee with a new secure panel access password',
        'funcionario_nova_senha_link_desc' => 'Sent to the employee with a secure password reset link',

        // Rental
        'rental_confirmation' => 'Rental Confirmation',
        'rental_confirmation_description' => 'Sent when a rental is confirmed',
        'rental_confirmation_desc' => 'Sent when a rental is confirmed',

        'contract_confirmation' => 'Contract Confirmation',
        'contract_confirmation_description' => 'Sent when a contract is signed',
        'contract_confirmation_desc' => 'Sent when a contract is signed',

        // Reminders
        'return_reminder' => 'Return Reminder',
        'return_reminder_description' => 'Notice before the scheduled return date',
        'return_reminder_desc' => 'Notice before the scheduled return date',

        'cnh_expiring' => 'License Expiring',
        'cnh_expiring_description' => 'Notice when the customer\'s driver\'s license is about to expire',
        'cnh_expiring_desc' => 'Notice when the customer\'s driver\'s license is about to expire',

        // Billing
        'payment_reminder' => 'Payment Reminder',
        'payment_reminder_description' => 'Notice of invoice due soon',
        'payment_reminder_desc' => 'Notice of invoice due soon',

        'invoice_generated' => 'Invoice Generated',
        'invoice_generated_description' => 'Sent when a new invoice is generated',
        'invoice_generated_desc' => 'Sent when a new invoice is generated',

        'overdue_notice' => 'Overdue Notice',
        'overdue_notice_description' => 'Notification of overdue invoice',
        'overdue_notice_desc' => 'Notification of overdue invoice',

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
