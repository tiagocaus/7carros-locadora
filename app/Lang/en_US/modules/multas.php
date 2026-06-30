<?php

return [
    'title' => 'Fines',
    'title_singular' => 'Fine',
    'new_title' => 'New Fine',
    'edit_title' => 'Edit Fine',

    'sections' => [
        'search_responsible' => 'Identify Responsible',
        'responsible_data' => 'Responsible Data',
        'fine_data' => 'Fine Data',
    ],

    'fields' => [
        'date_time' => 'Fine Date and Time',
        'plate' => 'Vehicle Plate',
        'due_date' => 'Due Date',
        'value' => 'Value',
        'infraction_number' => 'Infraction No.',
        'issuing_body' => 'Issuing Authority',
        'location' => 'Location',
        'city' => 'City',
        'state' => 'State',
        'description' => 'Description',
        'type' => 'Type',
        'status' => 'Status',
        'branch' => 'Branch',
        'client' => 'Client',
        'manual_responsible' => 'Manual responsible',
        'vehicle' => 'Vehicle',
        'contract_code' => 'Contract Code',
        'rental_code' => 'Rental Code',
        'code' => 'Code',
        'photo' => 'Fine Photo',
        'payer' => 'Who will pay the fine?',
        'payer_client' => 'Client',
        'payer_company' => 'Company',
    ],

    'table' => [
        'plate' => 'Plate',
        'client' => 'Client',
        'type' => 'Type',
        'date_time' => 'Date/Time',
        'value' => 'Value',
        'status' => 'Status',
        'actions' => 'Actions',
    ],

    'badges' => [
        'type_contract' => 'Contract',
        'type_rental' => 'Rental',
        'status_paid' => 'Paid',
        'status_pending' => 'Pending',
        'status_unknown' => 'No type',
    ],

    'buttons' => [
        'search_responsible' => 'Search Responsible',
        'continue' => 'Continue with this responsible',
        'add_manual_responsible' => 'Add responsible manually',
        'continue_manual' => 'Continue with manual responsible',
        'mark_paid' => 'Mark as Paid',
        'mark_unpaid' => 'Revert Payment',
    ],

    'messages' => [
        'no_records' => 'No fines found',
        'load_error' => 'Error loading data',
        'server_error' => 'Error connecting to server',
        'save_error' => 'Error saving',
        'created' => 'Fine registered successfully!',
        'updated' => 'Fine updated successfully!',
        'invalid_file_type' => 'Upload only an image or PDF.',
        'photo_allowed_types' => 'Image or PDF',
        'pdf_selected' => 'PDF selected',
        'deleted' => 'Fine deleted successfully!',
        'marked_paid' => 'Fine marked as paid!',
        'marked_unpaid' => 'Payment reverted!',
        'not_found' => 'Fine not found',
        'vehicle_not_found' => 'Vehicle not found with this plate',
        'responsible_found' => 'Responsible found',
        'responsible_not_found' => 'No contract or rental found for this vehicle at the specified date/time.',
        'manual_responsible_hint' => 'Select the client who had the vehicle on that date. The fine will be registered without a linked contract or rental.',
        'select_manual_responsible' => 'Select the manual responsible',
        'search_client_placeholder' => 'Type the client name or document...',
        'required_fields' => 'Fill in the required fields:',
        'saving' => 'Saving...',
        'searching' => 'Searching...',
        'confirm_delete' => 'Do you really want to delete this fine?',
        'confirm_mark_paid' => 'Do you want to mark this fine as paid?',
        'confirm_mark_unpaid' => 'Do you want to revert the payment of this fine?',
        'cannot_delete_paid' => 'Cannot delete a fine that has already been paid.',
        'this_record' => 'this fine',
        'select_doc_before_pdf' => 'Select a document before generating the PDF',
        'select_doc_before_send' => 'Select a document before sending',
        'sending' => 'Sending...',
        'send_success' => 'Document sent successfully',
        'send_error' => 'Error sending document',
        'send_connection_error' => 'Connection error while sending',
    ],

    'filters' => [
        'all_types' => 'All types',
        'type_contract' => 'Contract',
        'type_rental' => 'Rental',
        'all_status' => 'All statuses',
        'paid' => 'Paid',
        'pending' => 'Pending',
    ],

    'pagination' => [
        'rows_per_page' => 'Rows per page:',
        'showing' => 'Showing',
        'of' => 'of',
        'records' => 'records',
    ],

    'actions' => [
        'new' => 'New Fine',
    ],

    'record_type' => 'fine',

    // =========================================================
    // Print (offcanvas-impressao.php)
    // =========================================================
    'print' => [
        'title' => 'Print Fine',
        'fine_label' => 'Fine',
        'print_type' => 'Document Type',
        'notification' => 'Notification to Customer',
        'document' => 'Custom Document',
        'receipt' => 'Payment Receipt',
        'indication_term' => 'Driver Indication Form',
        'select_document' => 'Select Document',
        'select_document_placeholder' => 'Choose a template',
        'no_documents' => 'No templates registered for Fines',
        'generate_pdf' => 'Generate PDF',
        'send_via' => 'Send via',
    ],

    // =========================================================
    // PDF Templates (notification, receipt, indication, document)
    // =========================================================
    'pdf' => [
        'notification_title' => 'Fine Notification',
        'receipt_title' => 'Fine Payment Receipt',
        'indication_title' => 'Driver Indication Form',
        'document_title' => 'Document',
        'fine_data_section' => 'Infraction Data',
        'vehicle_data_section' => 'Vehicle Data',
        'fine_origin_section' => 'Fine Data',
        'client_section' => 'Customer Data',
        'owner_section' => 'Owner Data',
        'driver_section' => 'Driver Data (to fill)',
        'fine_number_label' => 'Number:',
        'date_label' => 'Date:',
        'ait_label' => 'AIT:',
        'infraction_code_label' => 'Infraction Code:',
        'issuing_body_label' => 'Issuing Authority:',
        'location_label' => 'Location:',
        'city_state_label' => 'City/State:',
        'date_time_label' => 'Date/Time:',
        'description_label' => 'Description:',
        'plate_label' => 'Plate:',
        'brand_model_label' => 'Brand/Model:',
        'value_label' => 'Amount Due',
        'amount_paid_label' => 'Amount Paid',
        'discount_40_label' => 'With 40% discount',
        'due_date_label' => 'Due Date',
        'fine_date_label' => 'Fine Date:',
        'client_name' => 'Name:',
        'client_document' => 'Tax ID:',
        'company_name_label' => 'Company Name:',
        'driver_name' => 'Name',
        'driver_cpf' => 'Tax ID',
        'driver_cnh' => 'Driver License',
        'driver_address' => 'Address',
        'driver_city' => 'City',
        'driver_phone' => 'Phone',
        'signature_place_label' => 'Place',
        'signature_date_label' => 'Date',
        'owner_signature' => 'Owner Signature',
        'driver_signature' => 'Driver Signature',
        'witness_1' => 'Witness 1',
        'witness_2' => 'Witness 2',
        'indication_declaration' => 'I declare, under penalty of law, that the driver identified above was responsible for the described infraction.',
        'indication_footer' => 'Submit this form to the issuing authority within the legal deadline.',
        'notification_text' => 'Dear :client, we hereby inform that a traffic fine has been registered linked to the vehicle with plate :plate. The amount due is :value, with due date on :due. We request regularization within the indicated period.',
        'receipt_text' => 'We received from :client, holder of document :document, the amount of :value, referring to fine no. :fine_number of the vehicle with plate :plate, occurred on :fine_date. For clarity, we sign this receipt.',
        'receipt_validity' => 'This receipt has legal validity and proves the settlement of the fine identified above.',
        'generated_at' => 'Generated at :datetime',
        'page_label' => 'Page :page of :total',
    ],

    // =========================================================
    // Fines Center (central.php)
    // =========================================================
    'central' => [
        'title' => 'Fines Center',
        'search_placeholder' => 'Search (name, plate, AIT)',
        'add_fine' => 'Add Fine',
        'check_online' => 'Check Fines',
        'check_batch' => 'Batch Check',

        'kpi' => [
            'overdue' => 'Overdue',
            'expiring_30d' => 'Expiring 30d',
            'on_time' => 'On time',
            'pending' => 'Pending',
            'paid' => 'Paid',
            'pending_value' => 'Pending Value',
        ],

        'balance' => [
            'title' => 'Query Balance',
            'manage' => 'Manage',
            'query' => 'Query',
            'event' => 'Event',
            'indication' => 'Nomination',
        ],

        'origin' => [
            'title' => 'Origin',
            'manual' => 'Manual',
            'online_query' => 'Online Query',
            'auto_event' => 'Automatic Event',
        ],

        'nominations' => [
            'title' => 'Nominations',
            'view_all' => 'View all',
            'pending_nomination' => 'Pending nomination',
            'new_unprocessed' => 'New (unprocessed)',
            'sent' => 'Nominations sent',
        ],

        'automation' => [
            'title' => 'Automations',
            'auto_query' => 'Auto-query',
            'auto_query_help' => 'Automatically checks fines for registered vehicles at the selected interval. The charge is per checked plate, not by the number of fines found. Example: if one plate returns several fines, only 1 query is charged for that plate.',
            'every' => 'every',
            'auto_events' => 'Automatic events',
            'auto_events_help' => 'Receives automatic Online Query notifications when new fine events are identified. Each received event consumes balance as an Event, separate from the Query charge per plate.',
            'last_query' => 'Last query: :date',
            'interval_1d' => '1 day',
            'interval_3d' => '3 days',
            'interval_7d' => '7 days',
            'interval_14d' => '14 days',
            'interval_30d' => '30 days',
            'online_query_requires_cnpj' => 'Online Query requires a CNPJ. Register a head office or branch with a valid CNPJ to activate automations.',
            'online_query_multiple_cnpjs' => 'There is more than one registered CNPJ. Configure which CNPJ Online Query should use before activating automations.',
        ],

        'filters' => [
            'type_all' => 'Type: All',
            'type_contract' => 'Contract',
            'type_rental' => 'Rental',
            'payment_all' => 'Payment: All',
            'payment_pending' => 'Pending',
            'payment_paid' => 'Paid',
            'due_all' => 'Due: All',
            'due_overdue' => 'Overdue',
            'due_expiring' => 'Expiring 30d',
            'due_on_time' => 'On time',
            'origin_all' => 'Origin: All',
            'origin_manual' => 'Manual',
            'origin_online' => 'Online Query',
            'origin_event' => 'Automatic Event',
            'status_all' => 'Status: All',
            'status_new' => 'New',
            'status_pending_nomination' => 'Pending Nomination',
            'status_nomination_sent' => 'Nomination Sent',
            'status_nominated' => 'Nominated',
            'status_transferred' => 'Transferred',
        ],

        'table' => [
            'plate' => 'Plate',
            'client' => 'Client',
            'date' => 'Date',
            'infraction' => 'Infraction',
            'value' => 'Value',
            'due' => 'Due',
            'payment' => 'Payment',
            'origin' => 'Origin',
            'status' => 'Status',
            'actions' => 'Actions',
        ],

        'pagination' => [
            'rows' => 'Rows:',
            'showing' => 'Showing :start-:end of :total',
        ],

        'ranking' => [
            'title' => 'Vehicle Ranking by Most Fines',
            'position' => '#',
            'plate' => 'Plate',
            'model' => 'Model',
            'total' => 'Total',
            'pending' => 'Pending',
            'pending_value' => 'Pending Value',
            'no_data' => 'No data available',
        ],

        'badges' => [
            'origin_query' => 'Query',
            'origin_event' => 'Event',
            'origin_manual' => 'Manual',
            'paid' => 'Paid',
            'pending' => 'Pending',
        ],

        'confirm' => [
            'mark_paid_title' => 'Mark as Paid',
            'mark_paid_message' => 'Confirm marking this fine as paid?',
            'revert_title' => 'Revert Payment',
            'revert_message' => 'Confirm reverting the payment of this fine?',
            'cannot_delete_paid' => 'Cannot delete a fine that has already been paid',
            'activate_auto_query_title' => 'Activate Auto-query',
            'activate_auto_query_message' => 'Auto-query will perform periodic automatic queries for all Brazilian vehicles. Each query consumes balance. Do you want to activate?',
            'activate_auto_events_title' => 'Activate Automatic Events',
            'activate_auto_events_message' => 'Automatic events register real-time notifications about new infractions. Each event consumes balance. Do you want to activate?',
            'confirm_activate' => 'Yes, activate',
        ],

        'toast' => [
            'fine_deleted' => 'Fine deleted successfully',
            'fine_marked_paid' => 'Fine marked as paid',
            'payment_reverted' => 'Payment reverted',
            'config_error' => 'Error updating configuration',
        ],

        'actions' => [
            'edit' => 'Edit',
            'nominate' => 'Nominate Real Offender',
            'mark_paid' => 'Mark as Paid',
            'mark_unpaid' => 'Mark as Unpaid',
            'delete' => 'Delete',
            'print' => 'Print',
        ],
    ],

    // =========================================================
    // Driver Nominations (indicacao.php)
    // =========================================================
    'indicacoes' => [
        'title' => 'Driver Nominations',
        'new_nomination' => 'New Nomination',

        'summary' => [
            'total' => 'Total',
            'sent' => 'Sent',
            'pending' => 'Pending',
            'accepted' => 'Accepted',
            'rejected' => 'Rejected',
        ],

        'filters' => [
            'all_types' => 'All types',
            'real_offender' => 'Real Offender',
            'main_driver' => 'Main Driver',
            'all_status' => 'All statuses',
            'sent' => 'Sent',
            'pending' => 'Pending',
            'processing' => 'Processing',
            'accepted' => 'Accepted',
            'rejected' => 'Rejected',
            'cancelled' => 'Cancelled',
            'deleted' => 'Deleted',
            'expired' => 'Expired',
            'plate' => 'Plate',
        ],

        'table' => [
            'date' => 'Date',
            'type' => 'Type',
            'plate' => 'Plate',
            'nominee' => 'Nominee',
            'ait' => 'AIT',
            'status' => 'Status',
            'actions' => 'Actions',
        ],

        'pagination' => [
            'rows' => 'Rows:',
            'showing' => 'Showing :start-:end of :total',
        ],

        'badges' => [
            'real_offender' => 'Real Offender',
            'main_driver' => 'Main Driver',
        ],

        'messages' => [
            'loading' => 'Loading...',
            'no_nominations' => 'No nominations found',
        ],

        'confirm' => [
            'cancel_title' => 'Cancel Nomination',
            'cancel_message' => 'Are you sure you want to cancel this nomination?',
        ],

        'actions' => [
            'check_status' => 'Check status',
            'cancel' => 'Cancel',
        ],
    ],

    // =========================================================
    // Query Balance (saldo.php)
    // =========================================================
    'saldo' => [
        'title' => 'Query Balance',

        'cards' => [
            'current_balance' => 'Current Balance',
            'total_spent' => 'Total Spent',
            'total_recharged' => 'Total Recharged',
            'prices_title' => 'Prices per Operation',
            'query' => 'Query:',
            'event' => 'Event:',
            'indication' => 'Nomination:',
        ],

        'buttons' => [
            'pix' => 'PIX',
            'card' => 'Card',
            'save' => 'Save',
        ],

        'auto_recharge' => [
            'title' => 'Auto-recharge',
            'threshold_label' => 'Recharge when balance below',
            'value_label' => 'Recharge amount',
            'requires_card' => 'Requires a saved credit card. The charge will be made automatically via Stripe.',
            'card_saved' => 'Saved card configured',
        ],

        'history_title' => 'Transaction History',

        'filters' => [
            'type_all' => 'Type: All',
            'type_queries' => 'Queries',
            'type_events' => 'Events',
            'type_indications' => 'Nominations',
            'type_pix' => 'PIX Recharge',
            'type_card' => 'Card Recharge',
            'until' => 'until',
        ],

        'table' => [
            'date' => 'Date',
            'type' => 'Type',
            'description' => 'Description',
            'value' => 'Value',
            'balance' => 'Balance',
            'status' => 'Status',
        ],

        'pagination' => [
            'rows' => 'Rows:',
            'showing' => 'Showing :start-:end of :total records',
        ],

        'badges' => [
            'query' => 'Query',
            'event' => 'Event',
            'indication' => 'Nomination',
            'pix' => 'PIX',
            'card' => 'Card',
            'confirmed' => 'Confirmed',
            'pending' => 'Pending',
            'failed' => 'Failed',
        ],

        'messages' => [
            'loading' => 'Loading...',
            'no_transactions' => 'No transactions found',
            'auto_recharge_updated' => 'Auto-recharge updated',
            'save_error' => 'Error saving',
        ],
    ],
];
