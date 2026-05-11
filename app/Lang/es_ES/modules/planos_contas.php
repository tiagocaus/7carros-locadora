<?php

/**
 * Traduções do módulo Planos de Contas - Español
 */

return [
    // Títulos
    'title' => 'Plan de Cuentas',
    'title_singular' => 'Cuenta',
    'list_title' => 'Plan de Cuentas',
    'new_title' => 'Nueva Cuenta',
    'edit_title' => 'Editar Cuenta',

    // Campos do formulário
    'fields' => [
        'hierarquia' => 'Código',
        'descricao' => 'Descripción',
        'tipo' => 'Tipo',
        'tipo_ativo' => 'Activo',
        'tipo_passivo' => 'Pasivo',
        'tipo_despesa' => 'Gasto',
        'tipo_receita' => 'Ingreso',
        'conta_pai' => 'Cuenta Padre',
        'descricao_pt_BR' => 'Portugués (Brasil)',
        'descricao_en_US' => 'Inglés (EE.UU.)',
        'descricao_es_ES' => 'Español',
        'descricao_it_IT' => 'Italiano',
        'descricao_pt_PT' => 'Portugués (Portugal)',
    ],

    // Seções do formulário
    'sections' => [
        'basic_info' => 'Información Básica',
        'translations' => 'Descripciones por Idioma',
    ],

    // Placeholders
    'placeholders' => [
        'search' => 'Buscar cuenta...',
        'descricao' => 'Ej.: Caja general',
        'descricao_optional' => 'Opcional - usará pt_BR si está vacío',
        'conta_pai' => 'Seleccione la cuenta padre (opcional para cuenta raíz)',
        'selecione_tipo' => 'Seleccione el tipo primero',
        'hierarquia' => 'Ej.: 1.1.1.01',
    ],

    // Filtros
    'filters' => [
        'all_types' => 'Todos los tipos',
    ],

    // Tooltips
    'tooltips' => [
        'hierarquia' => 'Código jerárquico único. Ej.: 1.1.1.01',
        'tipo' => 'Clasificación contable de la cuenta.',
    ],

    // Mensagens
    'messages' => [
        'created' => '¡Cuenta creada con éxito!',
        'updated' => '¡Cuenta actualizada con éxito!',
        'deleted' => '¡Cuenta eliminada con éxito!',
        'saved' => '¡Cuenta guardada con éxito!',
        'not_found' => 'Cuenta no encontrada.',
        'has_transactions' => 'Esta cuenta tiene transacciones financieras y no se puede eliminar.',
        'hierarquia_required' => 'El código jerárquico es obligatorio.',
        'hierarquia_exists' => 'Ya existe una cuenta con este código.',
        'tipo_invalid' => 'Tipo de cuenta inválido.',
        'descricao_required' => 'La descripción en Portugués (Brasil) es obligatoria.',
        'cannot_edit_system' => 'Las cuentas del sistema no se pueden editar.',
        'cannot_delete_system' => 'Las cuentas del sistema no se pueden eliminar.',
        'system_readonly' => 'Esta es una cuenta del sistema y no se puede modificar.',
        'no_records' => 'No se encontraron cuentas.',
        'translations_help' => 'Complete la descripción en Portugués (Brasil). Los otros idiomas son opcionales y usarán el valor en pt_BR si se dejan en blanco.',
        'error_list' => 'Error al listar cuentas',
        'error_load' => 'Error al cargar cuenta',
        'error_create' => 'Error al crear cuenta',
        'error_update' => 'Error al actualizar cuenta',
        'error_delete' => 'Error al eliminar cuenta',
        'error_save' => 'Error al guardar cuenta',
        'codigo_disponivel' => 'Código disponible',
        'codigo_em_uso' => 'Este código ya está en uso',
        'codigo_sugerido' => 'Código sugerido automáticamente',
        'conta_raiz' => 'Cuenta raíz (nivel principal)',
        'formato_invalido' => 'Formato inválido. Use solo números y puntos (ej: 1.1.01)',
        'this_record' => 'esta cuenta',
    ],
];
