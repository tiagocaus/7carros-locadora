<?php

/**
 * Feature Requests module translations - English (US)
 */

return [
    'title' => 'Feature Requests',
    'new_title' => 'New Feature Request',
    'details_title' => 'Request Details',
    'edit_title' => 'Edit Request',
    'new_request' => 'New Request',

    // Fields
    'fields' => [
        'title' => 'Request Title',
        'module' => 'Module/Area',
        'description' => 'Detailed Description',
        'phone' => 'Phone/WhatsApp (optional)',
        'follow_auto' => 'Notify me when this request is completed',
    ],

    // Filters
    'filters' => [
        'status' => 'Status',
        'module' => 'Module',
        'sort' => 'Sort',
        'all' => 'All',
        'my_requests' => 'My requests',
        'sort_recent' => 'Most Recent',
        'sort_votes' => 'Most Voted',
        'sort_oldest' => 'Oldest',
    ],

    // Status
    'status' => [
        'pending' => 'Pending',
        'in_review' => 'Under Review',
        'in_development' => 'In Development',
        'completed' => 'Completed',
        'rejected' => 'Rejected',
        'awaiting_info' => 'Awaiting Info',
        'awaiting_info_full' => 'Awaiting Information',
    ],

    // Priorities
    'priorities' => [
        'low' => 'Low',
        'normal' => 'Normal',
        'high' => 'High',
        'critical' => 'Critical',
    ],

    // Table
    'table' => [
        'title' => 'Title',
        'module' => 'Module',
        'status' => 'Status',
        'votes' => 'Votes',
        'actions' => 'Actions',
    ],

    // Placeholders
    'placeholders' => [
        'search' => 'Search request...',
        'title_input' => 'Briefly describe what you need...',
        'description_input' => 'Explain in detail what you need, what problem you want to solve, and how you envision the solution...',
        'phone_input' => '(555) 999-9999',
        'select_module' => 'Select...',
        'admin_response' => 'Add a response or feedback about the request...',
    ],

    // Hints
    'hints' => [
        'title' => 'Be clear and concise in the title',
        'module' => 'Which part of the system does this refer to?',
        'description' => 'The more details you provide, the better we will understand your needs',
        'phone' => 'To receive notifications via WhatsApp',
    ],

    // Buttons and actions
    'actions' => [
        'vote' => 'Vote for this request',
        'remove_vote' => 'Remove vote',
        'follow' => 'Follow',
        'unfollow' => 'Unfollow',
        'view_details' => 'View details',
        'view' => 'View',
        'submit' => 'Submit Request',
        'sending' => 'Sending...',
        'save_changes' => 'Save Changes',
    ],

    // Information
    'info' => [
        'voted' => 'You voted for this request',
        'following' => 'You will be notified when completed',
        'vote_priority' => 'Voting increases the request priority',
        'follow_updates' => 'Follow to receive notifications when there are updates',
        'requested_by' => 'Requested by',
        'not_categorized' => 'Not categorized',
        'votes_label' => 'votes',
        'followers_label' => 'followers',
        'responded_at' => 'Responded on',
    ],

    // Similar
    'similar' => [
        'found' => 'We found similar requests:',
        'follow_existing' => 'You can follow an existing request and be notified when it is completed.',
        'follow_btn' => 'Follow',
    ],

    // Details
    'details' => [
        'description' => 'Description',
        'admin_response' => '7Carros Team Response',
        'additional_info' => 'Additional Information',
        'id' => 'ID:',
        'priority' => 'Priority:',
        'updated' => 'Updated:',
        'email' => 'Email:',
    ],

    // Admin
    'admin' => [
        'panel_title' => 'Admin Panel',
        'change_status' => 'Change Status',
        'priority' => 'Priority',
        'response' => 'Response/Feedback',
        'notify_creator' => 'Notify the creator about this change',
        'notify_followers' => 'Notify followers',
        'followers_title' => 'Followers',
        'no_followers' => 'No followers yet',
        'notify_email' => 'Notify by email',
        'notify_whatsapp' => 'Notify via WhatsApp',
    ],

    // Edit modal
    'edit' => [
        'title_label' => 'Title',
        'description_label' => 'Description',
    ],

    // Messages
    'messages' => [
        'no_records' => 'No requests found',
        'no_title' => 'No title',
        'other_module' => 'Other',
        'load_error' => 'Error loading requests',
        'server_error' => 'Error connecting to the server',
        'vote_error' => 'Error processing vote',
        'follow_error' => 'Error following request',
        'process_error' => 'Error processing',
        'follow_success' => 'You are now following this request and will be notified when it is completed!',
        'now_following' => 'You are now following this request!',
        'unfollowed' => 'You have unfollowed this request',
        'vote_added' => 'Vote registered!',
        'vote_removed' => 'Vote removed',
        'title_required' => 'Please enter the request title',
        'module_required' => 'Please select the module/area',
        'description_required' => 'Please enter the detailed description',
        'title_required_edit' => 'Please enter the title',
        'description_required_edit' => 'Please enter the description',
        'submit_success' => 'Request submitted successfully!',
        'submit_error' => 'Error submitting request',
        'update_success' => 'Request updated successfully!',
        'update_error' => 'Error updating',
        'update_request_error' => 'Error updating request',
        'not_found' => 'Request not found',
        'id_not_found' => 'Request ID not provided',
        'load_request_error' => 'Error loading request',
        'admin_save_success' => 'Changes saved successfully!',
        'admin_save_error' => 'Error saving',
        'admin_save_changes_error' => 'Error saving changes',
        'saving' => 'Saving...',
        'back_to_list' => 'Back to list',
    ],

    // Pagination
    'pagination' => [
        'rows_per_page' => 'Records per page:',
        'showing' => 'Showing :start-:end of :total records',
    ],

    // System modules (categories)
    'sistema_inicial' => 'System - Dashboard',
    'sistema_locacoes' => 'System - Rentals',
    'sistema_contratos' => 'System - Contracts',
    'sistema_matriz_filiais' => 'System - Headquarters and branches',
    'sistema_funcionarios' => 'System - Employees',
    'sistema_taxas_servicos' => 'System - Fees and services',
    'sistema_oficinas' => 'System - Workshops',
    'sistema_promocoes' => 'System - Promotions',
    'sistema_multas' => 'System - Fines',
    'sistema_contas_caixa' => 'System - Bank accounts/cash',
    'sistema_formas_pagamento' => 'System - Payment methods',
    'sistema_fornecedores' => 'System - Suppliers',
    'sistema_veiculos' => 'System - Vehicles',
    'sistema_grupos' => 'System - Groups',
    'sistema_acessorios_itens' => 'System - Accessories and items',
    'sistema_manutencoes' => 'System - Maintenance',
    'sistema_plano_manutencoes' => 'System - Maintenance plans',
    'sistema_checklist' => 'System - Checklist',
    'sistema_checklist_modelos' => 'System - Checklist templates',
    'sistema_relatorios' => 'System - Reports',
    'sistema_financeiro' => 'System - Financial',
    'sistema_site' => 'System - Website',
    'sistema_clientes' => 'System - Customers',
    'sistema_whatsapp' => 'System - WhatsApp',
    'sistema_documentos' => 'System - Documents',
    'sistema_estoque' => 'System - Inventory',
    'sistema_agenda' => 'System - Calendar',

    // Website and App
    'website_site' => 'Website - Site',
    'aplicativo_checklist' => 'App - Checklist',

    // Other
    'outros' => 'Other',
];
