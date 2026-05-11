<?php

/**
 * Translations for the Employees module - English (US)
 */

return [
    // Titles
    'title' => 'Employees',
    'title_singular' => 'Employee',
    'new_title' => 'Add New Employee',
    'edit_title' => 'Edit Employee',
    'view_title' => 'View Employee',
    'list_title' => 'Employee List',

    // Sections
    'sections' => [
        'employee_data' => 'Employee Data',
        'personal_data' => 'Personal Data',
        'drivers_license' => 'Driver\'s License',
        'employment_data' => 'Employment Data',
        'compensation' => 'Compensation',
        'address' => 'Address',
        'contact' => 'Contact',
    ],

    // Form fields
    'fields' => [
        'branch' => 'Headquarters/Branch',
        'full_name' => 'Full Name',
        'email' => 'E-mail',
        'username' => 'Username',
        'password' => 'Password',
        'new_password' => 'New Password',
        'confirm_password' => 'Confirm Password',
        'confirm_new_password' => 'Confirm New Password',
        'password_hint' => '(leave blank to keep)',
        'role' => 'Role',
        'cpf' => 'CPF',
        'nationality' => 'Nationality',
        'gender' => 'Gender',
        'marital_status' => 'Marital Status',
        'cnh_number' => 'CNH Number',
        'cnh_registry' => 'CNH Registry',
        'cnh_expiry' => 'CNH Expiry',
        'work_card' => 'Work Card',
        'pis' => 'PIS',
        'salary' => 'Salary',
        'salary_type' => 'Salary Type',
        'payment_day' => 'Payment Day',
        'zip_code' => 'ZIP Code',
        'street' => 'Street',
        'number' => 'No.',
        'complement' => 'Complement',
        'neighborhood' => 'Neighborhood',
        'city' => 'City',
        'state' => 'State',
        'country' => 'Country',
        'landline' => 'Landline',
        'mobile' => 'Mobile',
    ],

    // Status options
    'status_options' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
    ],

    // Gender options
    'gender_options' => [
        'male' => 'Male',
        'female' => 'Female',
    ],

    // Marital status options
    'marital_options' => [
        'single' => 'Single',
        'married' => 'Married',
        'divorced' => 'Divorced',
        'widowed' => 'Widowed',
    ],

    // Salary type options
    'salary_type_options' => [
        'monthly' => 'Monthly',
        'biweekly' => 'Biweekly',
        'weekly' => 'Weekly',
        'daily' => 'Daily',
    ],

    // Photo
    'photo' => [
        'alt' => 'Employee Photo',
        'take_photo' => 'Take photo',
        'change_photo' => 'Change photo',
        'choose_title' => 'Choose Photo',
        'choose_method' => 'How would you like to add the photo?',
        'upload_file' => 'Upload File',
        'use_camera' => 'Use Camera',
        'camera_title' => 'Take Photo',
        'capture' => 'Capture',
    ],

    // Listing table
    'table' => [
        'name' => 'Name',
        'username' => 'Username',
        'email' => 'Email',
        'role' => 'Role',
        'status' => 'Status',
        'actions' => 'Actions',
    ],

    // Actions
    'actions' => [
        'add' => 'Add Employee',
        'view' => 'View Employee',
        'edit' => 'Edit Employee',
        'delete' => 'Delete Employee',
        'manage_roles' => 'Manage Roles',
        'set_as_main' => 'Set as main',
    ],

    // Specific buttons
    'buttons' => [
        'save' => 'Save Employee',
        'save_changes' => 'Save Changes',
    ],

    // Placeholders
    'placeholders' => [
        'search' => 'Search employee...',
        'select_option' => 'Select an option...',
        'select_role' => 'Select a role...',
        'nationality' => 'American',
        'payment_day' => 'e.g.: 5',
    ],

    // Branch dropdown
    'branch_dropdown' => [
        'loading' => 'Loading...',
        'loading_branches' => 'Loading branches...',
        'load_error' => 'Error loading',
        'load_error_detail' => 'Error loading branches',
        'no_branches' => 'No branches registered',
        'no_branches_short' => 'No branches',
    ],

    // Messages
    'messages' => [
        'no_records' => 'No employees found',
        'unnamed' => 'Unnamed employee',
        'this_employee' => 'this employee',
        'id_not_found' => 'Error: Employee ID not found',
        'load_error' => 'Error loading employees',
        'server_error' => 'Error connecting to the server',
        'not_found' => 'Employee not found',
        'delete_error' => 'Error deleting employee: :message',
        'save_error' => 'Error saving employee: :message',
        'update_error' => 'Error updating employee: :message',
        'password_required' => 'Password is required for new employees.',
        'password_mismatch' => 'Passwords do not match. Please check.',
        'passwords_dont_match' => 'Passwords do not match',
        'name_support_error' => 'The name cannot contain the term "support".',
        'username_support_error' => 'Username cannot contain the term "support".',
        'username_in_use' => 'Username is already in use',
        'format_not_supported' => 'Format not supported. Use JPEG, PNG or WebP only.',
        'image_too_large' => 'The image is too large. Please select an image smaller than 5MB.',
        'camera_not_supported' => 'Your browser does not support camera access. Use the file upload option.',
        'camera_access_denied' => 'Camera access permission denied. Please allow access and try again.',
        'camera_not_found' => 'No camera found. Use the file upload option.',
        'camera_error' => 'Could not access the camera.',
        'camera_initializing' => 'Please wait for the camera to fully initialize.',
    ],

    // Delete modal (local fallback)
    'delete_modal' => [
        'title' => 'Confirm Deletion',
        'confirm_text' => 'DELETE',
        'this_record' => 'this record',
        'message' => 'Do you really want to delete the :type (:name)?',
        'type_placeholder' => 'Type :text to confirm',
    ],

    // Pagination
    'pagination' => [
        'rows_per_page' => 'Rows per page:',
        'showing' => 'Showing :start-:end of :total records',
    ],

    // Record type (for delete modal)
    'record_type' => 'employee',
];
