<?php

/**
 * General Settings module translations - English (US)
 */

return [
    'title' => 'General Settings',

    'sections' => [
        'locale' => 'Localization & Formatting',
        'notifications' => 'Notifications',
        'print' => 'Printing',
        'sequences' => 'Numbering Sequences',
        'sequences_desc' => 'Set the next number to be used for each document type. The value cannot be lower than the current one.',
    ],

    'fields' => [
        'locale' => 'Language',
        'currency' => 'Currency',
        'date_format' => 'Date Format',
        'datetime_format' => 'Date/Time Format',
        'notification_title' => 'Notification Title',
        'notification_title_placeholder' => 'E.g.: Your rental company name',
        'next_rental_number' => 'Next Rental',
        'next_contract_number' => 'Next Contract',
        'next_financial_number' => 'Next Financial',
    ],

    'notifications' => [
        'sms_title' => 'SMS',
        'sms_desc' => 'Send notifications via SMS',
        'email_title' => 'Email',
        'email_desc' => 'Send notifications via email',
        'whatsapp_title' => 'WhatsApp',
        'whatsapp_desc' => 'Send notifications via WhatsApp',
        'financial_automation' => 'Financial automation',
        'overdue_billing_title' => 'Automatic collection of overdue invoices',
        'overdue_billing_desc' => 'Send periodic notices while an invoice remains overdue',
    ],

    'print' => [
        'bold_variables' => 'Bold Variables',
        'bold_variables_desc' => 'Highlight variables in printed documents',
        'remove_yellow_stripe' => 'Remove Yellow Stripe',
        'remove_yellow_stripe_desc' => 'Remove yellow highlight from fields',
    ],

    'messages' => [
        'save_success' => 'Settings saved successfully!',
        'save_error' => 'Error saving settings',
        'load_error' => 'Error loading settings',
    ],
];
