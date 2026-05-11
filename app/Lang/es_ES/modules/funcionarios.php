<?php

/**
 * Traduções do módulo Funcionários - Español (España)
 */

return [
    // Títulos
    'title' => 'Empleados',
    'title_singular' => 'Empleado',
    'new_title' => 'Añadir Nuevo Empleado',
    'edit_title' => 'Editar Empleado',
    'view_title' => 'Ver Empleado',
    'list_title' => 'Lista de Empleados',

    // Seções
    'sections' => [
        'employee_data' => 'Datos del Empleado',
        'personal_data' => 'Datos Personales',
        'drivers_license' => 'Carnet de Conducir',
        'employment_data' => 'Datos Laborales',
        'compensation' => 'Remuneración',
        'address' => 'Dirección',
        'contact' => 'Contacto',
    ],

    // Campos do formulário
    'fields' => [
        'branch' => 'Matriz/Sucursal',
        'full_name' => 'Nombre Completo',
        'email' => 'E-mail',
        'username' => 'Usuario',
        'password' => 'Contraseña',
        'new_password' => 'Nueva Contraseña',
        'confirm_password' => 'Confirmar Contraseña',
        'confirm_new_password' => 'Confirmar Nueva Contraseña',
        'password_hint' => '(dejar vacío para mantener)',
        'role' => 'Rol/Función',
        'cpf' => 'CPF',
        'nationality' => 'Nacionalidad',
        'gender' => 'Sexo',
        'marital_status' => 'Estado Civil',
        'cnh_number' => 'Nº de CNH',
        'cnh_registry' => 'Registro CNH',
        'cnh_expiry' => 'Vencimiento de la CNH',
        'work_card' => 'Tarjeta de Trabajo',
        'pis' => 'PIS',
        'salary' => 'Salario',
        'salary_type' => 'Tipo de Salario',
        'payment_day' => 'Día de Pago',
        'zip_code' => 'Código Postal',
        'street' => 'Calle',
        'number' => 'Nº',
        'complement' => 'Complemento',
        'neighborhood' => 'Barrio',
        'city' => 'Ciudad',
        'state' => 'Provincia',
        'country' => 'País',
        'landline' => 'Tel. Fijo',
        'mobile' => 'Tel. Móvil',
    ],

    // Opções de status
    'status_options' => [
        'active' => 'Activo',
        'inactive' => 'Inactivo',
    ],

    // Opções de sexo
    'gender_options' => [
        'male' => 'Masculino',
        'female' => 'Femenino',
    ],

    // Opções de estado civil
    'marital_options' => [
        'single' => 'Soltero(a)',
        'married' => 'Casado(a)',
        'divorced' => 'Divorciado(a)',
        'widowed' => 'Viudo(a)',
    ],

    // Opções de tipo de salário
    'salary_type_options' => [
        'monthly' => 'Mensual',
        'biweekly' => 'Quincenal',
        'weekly' => 'Semanal',
        'daily' => 'Diario',
    ],

    // Foto
    'photo' => [
        'alt' => 'Foto del Empleado',
        'take_photo' => 'Tomar foto',
        'change_photo' => 'Cambiar foto',
        'choose_title' => 'Elegir Foto',
        'choose_method' => '¿Cómo desea añadir la foto?',
        'upload_file' => 'Subir Archivo',
        'use_camera' => 'Usar Cámara',
        'camera_title' => 'Tomar Foto',
        'capture' => 'Capturar',
    ],

    // Tabela de listagem
    'table' => [
        'name' => 'Nombre',
        'username' => 'Usuario',
        'email' => 'Email',
        'role' => 'Función',
        'status' => 'Estado',
        'actions' => 'Acciones',
    ],

    // Ações
    'actions' => [
        'add' => 'Añadir Empleado',
        'view' => 'Ver Empleado',
        'edit' => 'Editar Empleado',
        'delete' => 'Eliminar Empleado',
        'manage_roles' => 'Gestionar Funciones',
        'set_as_main' => 'Definir como principal',
    ],

    // Botões específicos
    'buttons' => [
        'save' => 'Guardar Empleado',
        'save_changes' => 'Guardar Cambios',
    ],

    // Placeholders
    'placeholders' => [
        'search' => 'Buscar empleado...',
        'select_option' => 'Seleccione una opción...',
        'select_role' => 'Seleccione una función...',
        'nationality' => 'Española',
        'payment_day' => 'Ej: 5',
    ],

    // Dropdown de filiais
    'branch_dropdown' => [
        'loading' => 'Cargando...',
        'loading_branches' => 'Cargando sucursales...',
        'load_error' => 'Error al cargar',
        'load_error_detail' => 'Error al cargar sucursales',
        'no_branches' => 'Ninguna sucursal registrada',
        'no_branches_short' => 'Ninguna sucursal',
    ],

    // Mensagens
    'messages' => [
        'no_records' => 'Ningún empleado encontrado',
        'unnamed' => 'Empleado sin nombre',
        'this_employee' => 'este empleado',
        'id_not_found' => 'Error: ID del empleado no encontrado',
        'load_error' => 'Error al cargar empleados',
        'server_error' => 'Error al conectar con el servidor',
        'not_found' => 'Empleado no encontrado',
        'delete_error' => 'Error al eliminar empleado: :message',
        'save_error' => 'Error al guardar empleado: :message',
        'update_error' => 'Error al actualizar empleado: :message',
        'password_required' => 'La contraseña es obligatoria para nuevos empleados.',
        'password_mismatch' => 'Las contraseñas no coinciden. Por favor, verifique.',
        'passwords_dont_match' => 'Las contraseñas no coinciden',
        'name_support_error' => 'El nombre no puede contener el término "soporte".',
        'username_support_error' => 'El nombre de usuario no puede contener el término "soporte".',
        'username_in_use' => 'El usuario ya está en uso',
        'format_not_supported' => 'Formato no compatible. Use solo JPEG, PNG o WebP.',
        'image_too_large' => 'La imagen es demasiado grande. Por favor, seleccione una imagen menor de 5MB.',
        'camera_not_supported' => 'Su navegador no admite acceso a la cámara. Use la opción de subir archivo.',
        'camera_access_denied' => 'Permiso de acceso a la cámara denegado. Por favor, permita el acceso e inténtelo de nuevo.',
        'camera_not_found' => 'Ninguna cámara encontrada. Use la opción de subir archivo.',
        'camera_error' => 'No fue posible acceder a la cámara.',
        'camera_initializing' => 'Espere a que la cámara termine de inicializarse.',
    ],

    // Modal de exclusão (fallback local)
    'delete_modal' => [
        'title' => 'Confirmar Eliminación',
        'confirm_text' => 'ELIMINAR',
        'this_record' => 'este registro',
        'message' => '¿Desea realmente eliminar el :type (:name)?',
        'type_placeholder' => 'Escriba :text para confirmar',
    ],

    // Paginação
    'pagination' => [
        'rows_per_page' => 'Registros por página:',
        'showing' => 'Mostrando :start-:end de :total registros',
    ],

    // Tipo de registro (para modal de exclusão)
    'record_type' => 'empleado',
];
