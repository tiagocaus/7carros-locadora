<?php

/**
 * Settings module translations - English (US)
 */

return [
    // Message Templates (index)
    'templates_title' => 'Message Templates',
    'templates_description' => 'Customize the email, WhatsApp, and SMS templates sent to customers.',

    // Categories
    'categories' => [
        'all' => 'All',
        'onboarding' => 'Onboarding',
        'rental' => 'Rental',
        'reminder' => 'Reminders',
        'billing' => 'Billing',
    ],

    // Category labels (used in badges in JS)
    'category_labels' => [
        'onboarding' => 'Onboarding',
        'rental' => 'Rental',
        'reminder' => 'Reminder',
        'billing' => 'Billing',
    ],

    // Edit template
    'edit_title' => 'Edit Template',
    'edit_title_prefix' => 'Edit template:',

    // Labels
    'labels' => [
        'customized' => 'Customized',
        'using_default' => 'Using system default',
        'email_subject' => 'Email Subject',
        'content' => 'Content',
        'characters' => 'characters',
        'available_variables' => 'Available Variables',
        'click_to_insert' => 'Click to insert in editor',
        'subject' => 'Subject:',
        'no_subject' => '(no subject)',
        'content_label' => 'Content:',
    ],

    // Placeholders
    'placeholders' => [
        'email_subject' => 'E.g.: Rental Confirmation #{{rental.number}}',
        'message_content' => 'Type the message content...',
    ],

    // Warnings
    'warnings' => [
        'sms_split' => 'SMS with more than 160 characters will be split',
    ],

    // Buttons
    'buttons' => [
        'preview' => 'Preview',
        'restore_default' => 'Restore Default',
    ],

    // Modals
    'modals' => [
        'attention' => 'Attention',
        'unsaved_changes' => 'You have unsaved changes. Do you want to continue?',
        'continue' => 'Continue',
        'restore_title' => 'Restore Default Template',
        'restore_confirm' => 'Are you sure you want to restore this template to the system default?',
        'restore_warning' => 'Your customizations will be lost.',
        'restore_btn' => 'Restore',
        'preview_title' => 'Template Preview',
        'close' => 'Close',
    ],

    // Messages
    'messages' => [
        'loading' => 'Loading templates...',
        'loading_page' => 'Loading...',
        'load_error' => 'Error loading templates.',
        'no_templates' => 'No templates found.',
        'no_variables' => 'No variables available',
        'saving' => 'Saving...',
        'save_success' => 'Template saved successfully!',
        'save_error' => 'Error saving template',
        'preview_error' => 'Error generating preview',
        'restoring' => 'Restoring...',
        'restore_success' => 'Template restored to system default',
        'restore_error' => 'Error restoring template',
    ],
];
