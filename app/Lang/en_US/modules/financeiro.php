<?php

/**
 * Translations for the Financeiro module - English (United States)
 */

return [
    // Titles
    'title' => 'Financial Entries',
    'title_singular' => 'Financial Entry',
    'new_title' => 'New Entry',
    'edit_title' => 'Edit Entry',

    // Fields
    'fields' => [
        'type' => 'Type',
        'type_expense' => 'Expense (Payable)',
        'type_revenue' => 'Revenue (Receivable)',
        'bank_account' => 'Bank Account',
        'payment_method' => 'Payment Method',
        'chart_of_accounts' => 'Chart of Accounts',
        'description' => 'Description',
        'document' => 'Document',
        'creation_date' => 'Creation Date',
        'due_date' => 'Due Date',
        'is_paid' => 'Entry Paid',
        'payment_date' => 'Payment Date',
        'branch' => 'Branch',
        'client' => 'Client',
        'supplier' => 'Supplier',
        'employee' => 'Employee',
        'vehicle' => 'Vehicle',
        'subtotal' => 'Subtotal',
        'interest' => 'Interest',
        'penalty' => 'Penalty',
        'discount' => 'Discount',
        'total_value' => 'Total Amount',
        'installment_count' => 'Number of Installments',
        'first_installment_date' => '1st Installment Date',
        'interval' => 'Interval',
        'interval_type' => 'Interval Type',
        'original_invoice_value' => 'Original invoice amount',
        'amount_received' => 'Amount received',
        'difference_to_create' => 'Difference to create',
        'difference_due_date' => 'Difference due date',
    ],

    // Sections
    'sections' => [
        'basic_data' => 'Basic Data',
        'links' => 'Association(s)',
        'links_hint' => 'fill in at least one: Client, Supplier, Employee or Vehicle',
        'values' => 'Values',
        'items' => 'Entry Items',
        'items_hint' => 'optional - if provided, the Subtotal will be calculated automatically',
        'generate_installments' => 'Generate Installments',
        'installments_preview' => 'Installments Preview',
        'installments_list' => 'Entry Installments',
        'partial_payment' => 'Partial payment',
    ],

    // Tabs
    'tabs' => [
        'main_data' => 'Main Data',
        'installments' => 'Installments',
    ],

    // Filters
    'filters' => [
        'branch' => 'Branch',
        'all_branches' => 'All',
        'year' => 'Year',
        'all_years' => 'All',
        'month' => 'Month',
        'all_months' => 'All',
        'status' => 'Status',
        'all_statuses' => 'All',
        'status_paid' => 'Paid',
        'status_due_today' => 'Due today',
        'status_open' => 'Open',
        'status_overdue' => 'Overdue',
        'clear_title' => 'Clear filters',
        'search_placeholder' => 'Search entry...',
    ],

    // Table
    'table' => [
        'seq' => 'Seq.',
        'description' => 'Description',
        'client_supplier_employee' => 'Client/Supplier/Emp.',
        'client_supplier_employee_full' => 'Client/Supplier/Employee',
        'due_date' => 'Due Date',
        'value' => 'Amount',
        'vehicle_plates_label' => 'Plate(s)',
        'installment' => 'Installment',
    ],

    // Status
    'status' => [
        'paid' => 'Paid',
        'partial_paid' => 'Partially paid',
        'pending' => 'Pending',
        'due_in' => 'Due in :days',
        'due_today' => 'Due today',
        'overdue' => 'Overdue',
        'day_singular' => '1 day',
        'days_plural' => ':count days',
    ],

    // Interval types
    'interval_types' => [
        'days' => 'Days',
        'weeks' => 'Weeks',
        'months' => 'Months',
        'years' => 'Years',
    ],

    // Module-specific buttons
    'buttons' => [
        'add_item' => 'Add Item',
        'generate_preview' => 'Generate Preview',
        'edit_selected' => 'Edit Selected',
        'delete_selected' => 'Delete Selected',
        'payment_link' => 'Payment Link',
        'print_send' => 'Print / Send Invoice',
        'remove_item' => 'Remove item',
        'create_difference' => 'Create difference',
    ],

    // Invoice print and send
    'print' => [
        'title' => 'Print Invoice',
        'entry_label' => 'Entry',
        'value_label' => 'Amount',
        'due_label' => 'Due date',
        'print_type' => 'Print Type',
        'invoice' => 'Invoice',
        'generate_pdf' => 'Generate PDF',
        'send_via' => 'Send via',
        'no_channels_available' => 'Client has no email or phone, or no channels enabled on your plan.',
        'expense_send_unavailable' => 'Expenses can be printed as PDF, but are not sent as a charge to the supplier.',
        'sending' => 'Sending...',
        'send_success' => 'Invoice sent successfully',
        'send_error' => 'Error sending invoice',
        'send_connection_error' => 'Connection error while sending',
    ],

    'print_pdf' => [
        'title' => 'Invoice :number',
        'invoice' => 'INVOICE',
        'default_company' => 'Rental company',
        'company_tax_id' => 'Tax ID',
        'zip' => 'ZIP',
        'phone_short' => 'Phone',
        'number' => 'Number',
        'issue_date' => 'Issue date',
        'due_date' => 'Due date',
        'paid_at' => 'Paid at',
        'customer' => 'Customer',
        'supplier' => 'Supplier',
        'name' => 'Name',
        'tax_id' => 'Tax ID',
        'address' => 'Address',
        'city_state' => 'City/State',
        'email' => 'Email',
        'phone' => 'Phone',
        'description' => 'Description',
        'vehicles' => 'Vehicle(s)',
        'items' => 'Items',
        'value' => 'Amount',
        'subtotal' => 'Subtotal',
        'interest' => 'Interest',
        'penalty' => 'Penalty',
        'discount' => 'Discount',
        'total' => 'TOTAL',
        'observations' => 'Observations',
        'online_payment_link' => 'Online payment link',
        'generated_at' => 'Generated at :date',
        'status_paid' => 'PAID',
        'status_overdue' => 'OVERDUE',
        'status_open' => 'OPEN',
    ],

    // Messages
    'messages' => [
        'no_records' => 'No entries found',
        'no_description' => 'No description',
        'load_error' => 'Error loading entries: :message',
        'connection_error' => 'Error connecting to the server',
        'delete_confirm' => 'Do you want to delete the entry ":name"?',
        'delete_error' => 'Error deleting entry',
        'save_error' => 'Error saving entry',
        'not_found' => 'Entry not found',
        'load_single_error' => 'Error loading entry',
        'this_entry' => 'this entry',
        'no_items' => 'No items added',
        'item_description_placeholder' => 'Item description...',
        'subtotal_converted' => 'Subtotal (converted)',
        'no_installments' => 'This entry has no linked installments',
        'inform_first_date' => 'Enter the first installment date',
        'value_must_be_positive' => 'The total amount must be greater than zero',
        'select_installment' => 'Select at least one installment',
        'inform_field_update' => 'Enter at least one field to update',
        'installments_updated' => ':count installment(s) updated',
        'installments_update_error' => 'Error updating installments',
        'installments_deleted' => ':count installment(s) deleted',
        'installments_delete_error' => 'Error deleting installments',
        'payment_link_error' => 'Error generating payment link',
        'partial_difference_hint' => 'The difference will be created as a new pending invoice.',
        'save_before_partial' => 'Save the entry before registering a partial payment',
        'partial_value_invalid' => 'Enter an amount received greater than zero and less than the total amount',
        'partial_payment_date_required' => 'Enter the payment date',
        'partial_difference_due_required' => 'Enter the difference due date',
        'partial_success' => 'Partial payment registered successfully',
        'partial_error' => 'Error registering partial payment',
        'partial_use_button' => 'Use the Create difference button to register a partial payment',
        // Validation
        'required_field' => 'Required field: :field',
        'fill_at_least_one_link' => 'Fill in at least one: Client, Supplier, Employee or Vehicle',
        'vehicle_link_item_mismatch' => 'The linked vehicle differs from a vehicle selected in an item. Remove the linked vehicle or use the same vehicle in the items.',
        'inform_value_or_item' => 'Enter the Subtotal or add at least one item',
        'payment_date_required' => 'Payment Date is required when the entry is marked as paid',
    ],

    // Batch installment edit modal
    'installment_modal' => [
        'edit_title' => 'Edit :count Installment(s)',
        'new_due_date' => 'New Due Date',
        'due_date_hint' => 'Leave blank to keep current dates',
        'payment_status' => 'Payment Status',
        'keep_current' => 'Keep current',
    ],

    // Installment information
    'installment_info' => [
        'title' => 'How to use installments:',
        'step_1' => 'Fill in the entry data on the "Main Data" tab',
        'step_2' => 'Enter the Subtotal or add items',
        'step_3' => 'Set the number of installments and the first installment date',
        'step_4' => 'Define the interval (e.g.: 1 month, 15 days, 2 weeks)',
        'step_5' => 'Click "Generate Preview" to view the installments',
        'step_6' => 'Save the entry - all installments will be created automatically',
        'tip' => 'The amount will be divided equally among installments. Cent differences will be adjusted on the last installment.',
    ],

    // Pagination
    'pagination' => [
        'rows_per_page' => 'Records per page:',
        'showing' => 'Showing :start-:end of :total records',
    ],

    // Hints (field instructions)
    'hints' => [
        'valor_subtotal' => 'If items are present, it will be calculated automatically as the sum of their values. Otherwise, enter it manually. Cannot be changed after saving.',
        'valor_total' => 'Automatic sum: Subtotal + Interest + Penalty - Discount.',
    ],

    // Items - headers
    'items_header' => [
        'description' => 'Description',
        'vehicle' => 'Vehicle',
        'chart_of_accounts' => 'Chart of Accounts',
        'value' => 'Amount',
    ],

    // Installments - record types
    'record_types' => [
        'entry' => 'entry',
        'installments' => 'installments',
    ],
];
