<?php

/**
 * Traducciones del módulo Grupos - Español (España)
 */

return [
    'title' => 'Grupos de Vehículos',
    'title_singular' => 'Grupo',
    'new_title' => 'Nuevo Grupo',
    'edit_title' => 'Editar Grupo',

    // Pestañas
    'tabs' => [
        'group_data' => 'Datos del Grupo',
        'prices_by_days' => 'Precios por Días',
    ],

    // Secciones
    'sections' => [
        'basic_data' => 'Datos Básicos',
        'rental_plans' => 'Planes de Alquiler',
        'insurance' => 'Seguros',
        'tolerance_extras' => 'Tolerancia y Extras',
        'investor_commission' => 'Comisión Inversor',
        'progressive_prices' => 'Precios Progresivos por Días',
    ],

    // Campos
    'fields' => [
        'name' => 'Nombre',
        'description' => 'Descripción',
        'visible_on_site' => 'Visible en el sitio',
        'km_paid_value' => 'Tarifa Km de Pago',
        'km_controlled_value' => 'Tarifa Km Controlado',
        'km_free_value' => 'Tarifa Km Libre',
        'km_excess_value' => 'Tarifa Km Excedente',
        'km_franchise' => 'Franquicia de Km',
        'car_insurance_value' => 'Tarifa Seguro de Vehículo (por día)',
        'third_party_insurance_value' => 'Tarifa Seguro de Terceros (por día)',
        'car_coverage' => 'Cobertura del Vehículo',
        'third_party_coverage' => 'Cobertura de Terceros',
        'tolerance_minutes' => 'Minutos de Tolerancia',
        'tolerance_value' => 'Cargo por Tolerancia',
        'return_km_value' => 'Tarifa Km de Retorno',
        'additional_driver_value' => 'Cargo por Conductor Adicional',
        'commission_type' => 'Tipo de Comisión',
        'commission_value' => 'Valor',
    ],

    // Opciones de comisión
    'commission_options' => [
        'none' => 'Ninguno (sin comisión)',
        'percentage_rental' => 'Porcentaje para la Arrendadora',
        'fixed_rental_invoice' => 'Valor Fijo para la Arrendadora (por factura)',
        'fixed_rental_monthly' => 'Valor Fijo Mensual para la Arrendadora',
        'fixed_investor_monthly' => 'Valor Fijo Mensual para el Inversor',
    ],

    // Etiquetas dinámicas de comisión
    'commission_labels' => [
        'rental_percentage' => 'Porcentaje de la Arrendadora',
        'fixed_per_invoice' => 'Valor Fijo por Factura',
        'monthly_rental' => 'Valor Mensual (Arrendadora)',
        'monthly_investor' => 'Valor Mensual (Inversor)',
    ],

    // Sugerencias de comisión
    'commission_hints' => [
        'percentage_rental' => 'Ej.: 20% significa que la arrendadora se queda con el 20% del valor y el inversor recibe el 80%.',
        'fixed_rental_invoice' => 'Ej.: 50 € por factura significa que la arrendadora se queda con 50 € fijos de cada pago.',
        'fixed_rental_monthly' => 'Ej.: 300 €/mes por vehículo. La arrendadora recibe este valor fijo mensual por cada vehículo del inversor.',
        'fixed_investor_monthly' => 'Ej.: 2.000 €/mes por vehículo. El inversor recibe este valor fijo mensual, independientemente de los alquileres.',
    ],

    // Descripciones
    'descriptions' => [
        'investor_commission' => 'Configure cómo se calculará la comisión para los vehículos de inversores en este grupo.',
        'progressive_prices' => 'Configure tarifas diferenciadas según la cantidad de días del alquiler. Si no se configura ningún tramo, se utilizará la tarifa base.',
    ],

    // Sub-pestañas de precio
    'price_tabs' => [
        'km_paid' => 'Km de Pago',
        'km_controlled' => 'Km Controlado',
        'km_free' => 'Km Libre',
    ],

    // Tramos de precio
    'ranges' => [
        'from' => 'De',
        'to' => 'a',
        'days_equals' => 'días =',
        'add_range' => 'Añadir Tramo',
        'no_ranges' => 'Ningún tramo configurado. Se utilizará la tarifa base.',
        'infinity' => '(infinito)',
    ],

    // Imagen
    'image' => [
        'alt' => 'Imagen del Grupo',
        'change' => 'Cambiar Imagen',
    ],

    // Marcadores de posición
    'placeholders' => [
        'search' => 'Buscar grupo...',
    ],

    // Tabla
    'table' => [
        'image' => 'Imagen',
        'name' => 'Nombre',
        'description' => 'Descripción',
        'site' => 'Sitio',
        'actions' => 'Acciones',
    ],

    // Mensajes
    'messages' => [
        'no_records' => 'No se encontraron grupos',
        'no_name' => 'Sin nombre',
        'load_error' => 'Error al cargar grupos',
        'server_error' => 'Error al conectar con el servidor',
        'delete_error' => 'Error al eliminar grupo',
        'this_record' => 'este grupo',
        'load_group_error' => 'Error al cargar grupo',
        'invalid_image_format' => 'Seleccione una imagen válida (JPG, PNG o WebP)',
        'image_too_large' => 'La imagen no debe superar los 5 MB',
        'name_required' => 'El nombre es obligatorio',
        'saving' => 'Guardando...',
        'save_error' => 'Error al guardar',
        'save_server_error' => 'Error al guardar grupo',
        'created' => '¡Grupo creado correctamente!',
        'updated' => '¡Grupo actualizado correctamente!',
    ],

    // Paginación
    'pagination' => [
        'rows_per_page' => 'Registros por página:',
        'showing' => 'Mostrando :start-:end de :total registros',
    ],

    // Tipo de registro
    'record_type' => 'grupo',
];
