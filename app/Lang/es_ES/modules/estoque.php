<?php

/**
 * Traducciones del módulo Inventario - Español (España)
 */

return [
    'title' => 'Inventario',
    'title_singular' => 'Producto',
    'new_title' => 'Nuevo Producto',
    'edit_title' => 'Editar Producto',

    // Secciones
    'sections' => [
        'product_data' => 'Datos del Producto',
        'stock' => 'Inventario',
        'values' => 'Valores',
    ],

    // Campos
    'fields' => [
        'code' => 'Código',
        'name' => 'Nombre',
        'brand' => 'Marca',
        'model' => 'Modelo',
        'unit' => 'Unidad',
        'storage_location' => 'Ubicación de Almacenamiento',
        'branch' => 'Sede/Sucursal',
        'supplier' => 'Proveedor',
        'current_stock' => 'Stock Actual',
        'minimum_stock' => 'Stock Mínimo',
        'purchase_value' => 'Valor de Compra',
        'sale_value' => 'Valor de Venta',
        'auto_deduct' => 'Baja automática',
        'auto_deduct_enable' => 'Activar',
        'allow_negative_stock' => 'Permitir stock negativo',
        'allow_negative_stock_enable' => 'Activar',
    ],

    // Opciones de unidad
    'unit_options' => [
        'UN' => 'UN - Unidad',
        'PC' => 'PC - Pieza',
        'CX' => 'CX - Caja',
        'KG' => 'KG - Kilogramo',
        'L' => 'L - Litro',
        'M' => 'M - Metro',
        'M2' => 'M2 - Metro Cuadrado',
        'M3' => 'M3 - Metro Cúbico',
        'JG' => 'JG - Juego',
        'KIT' => 'KIT - Kit',
        'PAR' => 'PAR - Par',
    ],

    // Marcadores de posición
    'placeholders' => [
        'search' => 'Buscar...',
        'select' => 'Seleccione...',
        'storage_location' => 'Ej: Estante A3',
        'search_branch' => 'Escriba para buscar...',
        'search_supplier' => 'Escriba para buscar...',
        'none' => 'Ninguno',
    ],

    // Estado
    'status' => [
        'label' => 'Estado',
        'active' => 'Activo',
        'inactive' => 'Inactivo',
    ],

    // Filtros
    'filters' => [
        'all_branches' => 'Todas las sucursales',
        'all_status' => 'Todos los estados',
    ],

    // Avisos (tooltips)
    'tooltips' => [
        'minimum_stock' => 'Alerta cuando se alcance este valor. 0 = desactivado.',
        'auto_deduct' => 'Cuando está activado, el stock se decrementará automáticamente al usar este producto en una orden de mantenimiento.',
        'allow_negative_stock' => 'Cuando está activado, permite usar el producto aunque no haya stock disponible. Cuando está desactivado, impide la selección con stock cero y limita la cantidad al stock disponible.',
    ],

    // Tabla
    'table' => [
        'code' => 'Código',
        'product' => 'Producto',
        'brand_model' => 'Marca/Modelo',
        'unit' => 'Unidad',
        'stock' => 'Stock',
        'purchase_value' => 'Valor Compra',
        'branch' => 'Sucursal',
        'status' => 'Estado',
        'actions' => 'Acciones',
    ],

    // Mensajes
    'messages' => [
        'no_records' => 'No se encontraron registros',
        'no_name' => 'Sin nombre',
        'load_error' => 'Error al cargar',
        'server_error' => 'Error al conectar con el servidor',
        'delete_error' => 'Error al eliminar',
        'inactivated' => 'Producto inactivado. Tiene vinculo con mantenimiento y no puede ser eliminado.',
        'reactivated' => 'Producto reactivado con exito!',
        'already_inactive' => 'El producto ya está inactivo',
        'reactivate_error' => 'Error al reactivar',
        'this_record' => 'este registro',
        'load_data_error' => 'Error al cargar los datos',
        'load_product_error' => 'Error al cargar los datos del producto',
        'saving' => 'Guardando...',
        'save_error' => 'Error al guardar',
        'save_product_error' => 'Error al guardar el producto',
        'created' => '¡Producto creado con éxito!',
        'updated' => '¡Producto actualizado con éxito!',
    ],

    // Paginación
    'pagination' => [
        'rows_per_page' => 'Registros por página:',
        'showing' => 'Mostrando :start-:end de :total registros',
    ],

    // Tipo de registro
    'record_type' => 'inventario',
];
