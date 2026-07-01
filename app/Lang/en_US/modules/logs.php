<?php

/**
 * System Logs module translations - English (US)
 */

return [
    'title' => 'System Logs',
    'search_placeholder' => 'Search log...',
    'tabs' => [
        'audit' => 'Audit',
        'messages' => 'Messages',
    ],
    'filters' => [
        'all_channels' => 'All channels',
        'all_statuses' => 'All statuses',
    ],
    'table' => [
        'date' => 'Date',
        'user' => 'User',
        'message' => 'Message',
        'ip' => 'IP',
        'actions' => 'Actions',
        'channel' => 'Channel',
        'recipient' => 'Recipient',
        'status' => 'Status',
        'error' => 'Error',
        'processed_at' => 'Processed at',
    ],
    'channels' => [
        'email' => 'Email',
        'whatsapp' => 'WhatsApp',
        'sms' => 'SMS',
    ],
    'status' => [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'sent' => 'Sent',
        'failed' => 'Failed',
        'skipped' => 'Skipped',
    ],
    'pagination' => [
        'rows_per_page' => 'Rows per page:',
        'showing' => 'Showing :start-:end of :total records',
        'showing_lazy' => 'Showing records :start-:end',
    ],
    'no_records' => 'No logs found',
    'details_title' => 'Change Details',
    'payload_title' => 'Message Details',
    'empty_value' => '(empty)',
    'unrecognized_format' => 'Unrecognized data format.',
    'view_details' => 'View details',
    'no_details' => 'No details',
    'messages' => [
        'load_error' => 'Error loading logs',
        'server_error' => 'Error connecting to server',
        'sent_hint' => 'Sent means the worker processed the message and the provider accepted the request; it does not confirm final delivery or read status.',
    ],
];
