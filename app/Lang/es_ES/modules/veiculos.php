<?php

/**
 * Traducciones del módulo Vehículos - Español (España)
 */

return [
    // Títulos
    'title' => 'Vehículos',
    'title_singular' => 'Vehículo',
    'new_title' => 'Nuevo Vehículo',
    'edit_title' => 'Editar Vehículo',

    // Campos del formulario
    'fields' => [
        'branch' => 'Sucursal',
        'supplier' => 'Proveedor',
        'group' => 'Grupo',
        'plate' => 'Matrícula',
        'renavam' => 'Registro (Renavam)',
        'chassis' => 'Chasis',
        'odometer' => 'Odómetro (km)',
        'availability' => 'Disponibilidad',
        'brand' => 'Marca',
        'model' => 'Modelo',
        'year' => 'Año',
        'color' => 'Color',
        'transmission' => 'Transmisión',
        'engine' => 'Motor',
        'max_weight' => 'Peso Máx (kg)',
        'current_location' => 'Ubicación Actual',
        'fuel_type' => 'Tipo Combustible',
        'tank_liters' => 'Tanque (L)',
        'tank_fraction' => 'Fracción Tanque',
        'fraction_value' => 'Valor por Fracción',
        'battery_kwh' => 'Batería (kWh)',
        'battery_charge' => 'Carga Batería',
        'purchase_date' => 'Fecha Compra',
        'purchase_value' => 'Valor Compra',
        'for_sale' => 'En Venta',
        'sale_date' => 'Fecha Venta',
        'sale_value' => 'Valor Venta',
        'charge_name' => 'Nombre',
        'charge_description' => 'Descripción',
        'charge_value' => 'Valor',
        'charge_due_date' => 'Vencimiento',
        'charge_recurrence' => 'Recurrencia',
        'charge_days_advance' => 'Anticipación',
        'add_charge' => 'Agregar Cargo',
        'no_charges' => 'Ningún cargo registrado',
        'recurrence_none' => 'Ninguna',
        'recurrence_monthly' => 'Mensual',
        'recurrence_quarterly' => 'Trimestral',
        'recurrence_semiannual' => 'Semestral',
        'recurrence_annual' => 'Anual',
        'save_vehicle_first' => 'Guarde el vehículo antes de agregar cargos',
        'charge_name_required' => 'El nombre del cargo es obligatorio',
        'description' => 'Descripción',
        'accessories' => 'Accesorios del Vehículo',
        'photo' => 'Foto del Vehículo',
        'change_photo' => 'Cambiar Foto',
        'brand_model' => 'Marca/Modelo',
        'branch_short' => 'Sucursal',
    ],

    // Secciones del formulario
    'sections' => [
        'basic_data' => 'Datos Básicos',
        'characteristics' => 'Características',
        'fuel' => 'Combustible',
        'purchase_sale' => 'Compra y Venta',
        'vehicle_charges' => 'Cargos del Vehículo',
        'description' => 'Descripción',
        'accessories' => 'Accesorios',
        'select_plan' => 'Seleccionar Plan',
    ],

    // Pestañas
    'tabs' => [
        'vehicle_data' => 'Datos del Vehículo',
        'maintenance_plan' => 'Plan de Mantenimiento',
        'maintenances' => 'Mantenimientos',
    ],

    // Pestaña Mantenimientos
    'maintenances' => [
        'no_records' => 'No se encontraron mantenimientos para este vehículo.',
        'load_error' => 'Error al cargar mantenimientos',
        'table_os' => 'OS',
        'table_workshop' => 'Taller',
        'table_send_date' => 'Fecha Envío',
        'table_return_date' => 'Fecha Retorno',
        'table_total' => 'Total',
        'table_status' => 'Estado',
        'status_created' => 'Creada',
        'status_open' => 'Abierta',
        'status_closed' => 'Cerrada',
        'action_print' => 'Imprimir OT',
    ],

    // Disponibilidad
    'availability' => [
        'available' => 'Disponible',
        'rented' => 'Alquilado',
        'reserved' => 'Reservado',
        'in_shop' => 'En taller',
        'sold' => 'Vendido',
        'for_sale' => 'En venta',
        'internal_use' => 'Uso interno',
        'stolen' => 'Robado',
        'excluded' => 'Excluido',
        'maintenance' => 'Mantenimiento',
        'unavailable' => 'No disponible',
    ],

    // Transmisión
    'transmission' => [
        'automatic' => 'Automática',
        'manual' => 'Manual',
    ],

    // Combustible
    'fuel' => [
        'gasoline_ethanol' => 'Gasolina/Etanol',
        'gasoline' => 'Gasolina',
        'ethanol' => 'Etanol',
        'diesel' => 'Diésel',
        'gas' => 'Gas',
        'electric' => 'Eléctrico',
        'hybrid' => 'Híbrido',
    ],

    // Fracción del tanque
    'tank_fraction' => [
        'full' => 'Lleno',
        'reserve' => 'Reserva',
    ],

    // Mantenimiento
    'maintenance' => [
        'plan' => 'Plan de Mantenimiento',
        'recalculate' => 'Recalcular con Odómetro Actual',
        'recalculate_hint' => 'Recalcula: odómetro + intervalo del plan',
        'engine_section' => 'Motor',
        'engine_hint' => 'Próximo km para cada elemento de mantenimiento del motor',
        'wheels_section' => 'Rodaje',
        'wheels_hint' => 'Próximo km para cada elemento de mantenimiento de rodaje',
        'accessories_section' => 'Accesorios',
        'accessories_hint' => 'Próximo km para cada elemento de mantenimiento de accesorios',
        // Elementos motor
        'engine_oil' => 'Aceite Motor',
        'oil_filter' => 'Filtro de Aceite',
        'timing_belt' => 'Correa de Distribución',
        'alternator_belt' => 'Correa Alternador',
        'ac_belt' => 'Correa Aire Acondicionado',
        'water_pump_belt' => 'Correa Bomba de Agua',
        'air_filter' => 'Filtro de Aire',
        'cabin_filter' => 'Filtro de Cabina',
        'fuel_filter' => 'Filtro de Combustible',
        'brake_fluid' => 'Líquido de Frenos',
        'clutch_fluid' => 'Líquido de Embrague',
        'clutch_disc' => 'Disco de Embrague',
        'gearbox_fluid' => 'Líquido Caja de Cambios',
        'cooling_flush' => 'Limpieza Refrigeración',
        'spark_plugs' => 'Bujías',
        'battery' => 'Batería',
        // Elementos rodaje
        'tires' => 'Neumáticos',
        'alignment' => 'Alineación',
        'brake_pads' => 'Pastillas de Freno',
        'brake_discs' => 'Discos de Freno',
        'tire_rotation' => 'Rotación de Neumáticos',
        // Elementos accesorios
        'wiper_blades' => 'Escobillas Parabrisas',
    ],

    // Placeholders
    'placeholders' => [
        'search' => 'Buscar por matrícula, marca o modelo...',
        'search_select' => 'Escriba para buscar...',
        'select' => 'Seleccione...',
        'select_option' => 'Seleccione',
        'select_plan' => 'Seleccione un plan...',
        'plate' => 'ABC-1234',
        'year' => '2024/2025',
        'engine' => '1.0',
        'description' => 'Información adicional sobre el vehículo...',
        'select_accessories' => 'Seleccione los accesorios...',
        'same_as_branch' => 'Misma que la sucursal',
    ],

    // Mensajes
    'messages' => [
        'created' => '¡Vehículo creado con éxito!',
        'updated' => '¡Vehículo actualizado con éxito!',
        'deleted' => '¡Vehículo eliminado con éxito!',
        'delete_confirm' => '¿Desea eliminar el vehículo ":name"?',
        'delete_error' => 'Error al eliminar vehículo',
        'load_error' => 'Error al cargar vehículos: ',
        'load_data_error' => 'Error al cargar datos del vehículo',
        'save_error' => 'Error al guardar vehículo',
        'save_generic_error' => 'Error al guardar',
        'connection_error' => 'Error al conectar con el servidor',
        'no_vehicles' => 'Ningún vehículo encontrado',
        'no_plate' => 'Sin matrícula',
        'this_vehicle' => 'este vehículo',
        'select_plan_first' => 'Seleccione un plan de mantenimiento primero',
        'invalid_image' => 'Seleccione una imagen válida (JPG, PNG o WebP)',
        'image_too_large' => 'La imagen debe tener como máximo 5MB',
        'accessories_load_error' => 'Error al cargar accesorios',
        'accessories_load_error_short' => 'Error al cargar',
        'no_accessories' => 'Ningún accesorio registrado',
        'no_accessories_short' => 'Sin accesorios',
        'plan_load_error' => 'Error al cargar planes de mantenimiento:',
        'plan_fetch_error' => 'Error al buscar plan:',
        'recalculate_title' => 'Recalcular Plan',
        'recalculate_confirm' => '¿Desea recalcular los valores del plan con base en el odómetro actual?',
        'recalculate_btn' => 'Recalcular',
        'for_sale_tooltip' => 'Al activar para venta, el vehículo aparecerá en el sitio como disponible para venta y ya no estará disponible para alquiler o contrato.',
        'loading_accessories' => 'Cargando accesorios...',
        'plan_limit_reached' => 'Límite de vehículos alcanzado. Su plan (:plano) permite un máximo de :límite vehículos activos. Para reactivar este vehículo, elimine otro o actualice su plan.',
    ],

    // Paginación
    'pagination' => [
        'rows_per_page' => 'Registros por página:',
        'showing' => 'Mostrando :start-:end de :total registros',
        'showing_empty' => 'Mostrando 0-0 de 0 registros',
    ],
];
