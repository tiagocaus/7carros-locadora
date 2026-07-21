<?php

/**
 * Traduções do módulo Financeiro - Español (España)
 */

return [
    // Títulos
    'title' => 'Movimientos Financieros',
    'title_singular' => 'Movimiento Financiero',
    'new_title' => 'Nuevo Movimiento',
    'edit_title' => 'Editar Movimiento',

    // Campos
    'fields' => [
        'type' => 'Tipo',
        'type_expense' => 'Gasto (Pagar)',
        'type_revenue' => 'Ingreso (Cobrar)',
        'bank_account' => 'Cuenta bancaria',
        'payment_method' => 'Forma de Pago',
        'chart_of_accounts' => 'Plan de Cuentas',
        'description' => 'Descripción',
        'document' => 'Documento',
        'creation_date' => 'Fecha de Creación',
        'due_date' => 'Fecha de Vencimiento',
        'is_paid' => 'Movimiento Pagado',
        'payment_date' => 'Fecha de Pago',
        'branch' => 'Sucursal',
        'client' => 'Cliente',
        'supplier' => 'Proveedor',
        'employee' => 'Empleado',
        'vehicle' => 'Vehículo',
        'subtotal' => 'Subtotal',
        'interest' => 'Intereses',
        'penalty' => 'Multa',
        'discount' => 'Descuento',
        'total_value' => 'Valor Total',
        'installment_count' => 'Número de Cuotas',
        'first_installment_date' => 'Fecha 1ª Cuota',
        'interval' => 'Intervalo',
        'interval_type' => 'Tipo de Intervalo',
        'original_invoice_value' => 'Valor original de la factura',
        'amount_received' => 'Valor recibido',
        'difference_to_create' => 'Diferencia a crear',
        'difference_due_date' => 'Vencimiento de la diferencia',
    ],

    // Seções
    'sections' => [
        'basic_data' => 'Datos Básicos',
        'links' => 'Vínculo(s)',
        'links_hint' => 'rellene al menos uno: Cliente, Proveedor, Empleado o Vehículo',
        'values' => 'Valores',
        'items' => 'Elementos del Movimiento',
        'items_hint' => 'opcional - si se informa, el Subtotal se calculará automáticamente',
        'generate_installments' => 'Generar Cuotas',
        'installments_preview' => 'Vista Previa de las Cuotas',
        'installments_list' => 'Cuotas del Movimiento',
        'partial_payment' => 'Pago parcial',
    ],

    // Abas
    'tabs' => [
        'main_data' => 'Datos Principales',
        'installments' => 'Fraccionamiento',
    ],

    // Filtros
    'filters' => [
        'branch' => 'Sucursal',
        'all_branches' => 'Todas',
        'year' => 'Año',
        'all_years' => 'Todos',
        'month' => 'Mes',
        'all_months' => 'Todos',
        'clear_title' => 'Limpiar filtros',
        'search_placeholder' => 'Buscar movimiento...',
    ],

    // Tabela
    'table' => [
        'seq' => 'Seq.',
        'description' => 'Descripción',
        'client_supplier_employee' => 'Cliente/Prov/Empl',
        'client_supplier_employee_full' => 'Cliente/Proveedor/Empleado',
        'due_date' => 'Vencimiento',
        'value' => 'Valor',
        'vehicle_plates_label' => 'Matrícula(s)',
        'installment' => 'Cuota',
    ],

    // Status
    'status' => [
        'paid' => 'Pagado',
        'partial_paid' => 'Pago parcial',
        'pending' => 'Pendiente',
        'due_in' => 'Vence en :days',
        'due_today' => 'Vence hoy',
        'overdue' => 'Vencido',
        'day_singular' => '1 día',
        'days_plural' => ':count días',
    ],

    // Tipos de intervalo
    'interval_types' => [
        'days' => 'Días',
        'weeks' => 'Semanas',
        'months' => 'Meses',
        'years' => 'Años',
    ],

    // Botões específicos do módulo
    'buttons' => [
        'add_item' => 'Añadir Elemento',
        'generate_preview' => 'Generar Vista Previa',
        'edit_selected' => 'Editar Seleccionados',
        'delete_selected' => 'Eliminar Seleccionados',
        'delete_selected_count' => 'Eliminar seleccionados (:count)',
        'select_all_visible' => 'Seleccionar todos los movimientos visibles',
        'payment_link' => 'Enlace de Pago',
        'print_send' => 'Imprimir / Enviar Factura',
        'remove_item' => 'Eliminar elemento',
        'create_difference' => 'Crear diferencia',
    ],

    'print' => [
        'title' => 'Imprimir Factura',
        'entry_label' => 'Movimiento',
        'value_label' => 'Valor',
        'due_label' => 'Vencimiento',
        'print_type' => 'Tipo de Impresión',
        'invoice' => 'Factura',
        'generate_pdf' => 'Generar PDF',
        'send_via' => 'Enviar por',
        'no_channels_available' => 'Cliente sin correo ni teléfono registrado, o canales de envío no habilitados en su plan.',
        'expense_send_unavailable' => 'Los gastos se pueden imprimir en PDF, pero no se envían como cobro al proveedor.',
        'sending' => 'Enviando...',
        'send_success' => 'Factura enviada con éxito',
        'send_error' => 'Error al enviar factura',
        'send_connection_error' => 'Error de conexión al enviar',
    ],

    'print_pdf' => [
        'title' => 'Factura :number',
        'invoice' => 'FACTURA',
        'default_company' => 'Alquiler',
        'company_tax_id' => 'Identificación fiscal',
        'zip' => 'Código postal',
        'phone_short' => 'Tel',
        'number' => 'Número',
        'issue_date' => 'Emisión',
        'due_date' => 'Vencimiento',
        'paid_at' => 'Pagado el',
        'customer' => 'Cliente',
        'supplier' => 'Proveedor',
        'name' => 'Nombre',
        'tax_id' => 'CPF/CNPJ',
        'address' => 'Dirección',
        'city_state' => 'Ciudad/Estado',
        'email' => 'Email',
        'phone' => 'Teléfono',
        'description' => 'Descripción',
        'vehicles' => 'Vehículo(s)',
        'items' => 'Elementos',
        'value' => 'Valor',
        'subtotal' => 'Subtotal',
        'interest' => 'Intereses',
        'penalty' => 'Multa',
        'discount' => 'Descuento',
        'total' => 'TOTAL',
        'observations' => 'Observaciones',
        'online_payment_link' => 'Enlace para pago online',
        'generated_at' => 'Generado el :date',
        'status_paid' => 'PAGADO',
        'status_overdue' => 'VENCIDO',
        'status_open' => 'ABIERTO',
    ],

    // Mensagens
    'messages' => [
        'no_records' => 'No se encontraron movimientos',
        'no_description' => 'Sin descripción',
        'load_error' => 'Error al cargar movimientos: :message',
        'connection_error' => 'Error al conectar con el servidor',
        'delete_confirm' => '¿Desea eliminar el movimiento ":name"?',
        'delete_error' => 'Error al eliminar movimiento',
        'selected_entries' => ':count movimiento(s) seleccionado(s)',
        'batch_delete_error' => 'Error al eliminar los movimientos seleccionados',
        'batch_delete_partial_title' => 'Eliminación completada parcialmente',
        'save_error' => 'Error al guardar movimiento',
        'not_found' => 'Movimiento no encontrado',
        'load_single_error' => 'Error al cargar movimiento',
        'this_entry' => 'este movimiento',
        'no_items' => 'Ningún elemento añadido',
        'item_description_placeholder' => 'Descripción del elemento...',
        'subtotal_converted' => 'Subtotal (convertido)',
        'no_installments' => 'Este movimiento no tiene cuotas vinculadas',
        'inform_first_date' => 'Indique la fecha de la primera cuota',
        'value_must_be_positive' => 'El valor total debe ser mayor que cero',
        'installment_count_range' => 'El número de cuotas debe estar entre :min y :max',
        'select_installment' => 'Seleccione al menos una cuota',
        'inform_field_update' => 'Indique al menos un campo para actualizar',
        'installments_updated' => ':count cuota(s) actualizada(s)',
        'installments_update_error' => 'Error al actualizar cuotas',
        'installments_deleted' => ':count cuota(s) eliminada(s)',
        'installments_delete_error' => 'Error al eliminar cuotas',
        'payment_link_error' => 'Error al generar enlace de pago',
        'partial_difference_hint' => 'La diferencia se creará como una nueva factura pendiente.',
        'save_before_partial' => 'Guarde el movimiento antes de registrar un pago parcial',
        'partial_value_invalid' => 'Informe un valor recibido mayor que cero y menor que el valor total',
        'partial_payment_date_required' => 'Informe la fecha de pago',
        'partial_difference_due_required' => 'Informe el vencimiento de la diferencia',
        'partial_success' => 'Pago parcial registrado con éxito',
        'partial_error' => 'Error al registrar pago parcial',
        'partial_use_button' => 'Use el botón Crear diferencia para registrar un pago parcial',
        // Validação
        'required_field' => 'Campo obligatorio: :field',
        'fill_at_least_one_link' => 'Rellene al menos uno: Cliente, Proveedor, Empleado o Vehículo',
        'vehicle_link_item_mismatch' => 'El vehículo del vínculo es diferente del vehículo informado en un elemento. Quite el vehículo del vínculo o use el mismo vehículo en los elementos.',
        'inform_value_or_item' => 'Indique el Subtotal o añada al menos un elemento',
        'payment_date_required' => 'La Fecha de Pago es obligatoria cuando el movimiento está marcado como pagado',
    ],

    // Modal de edição em lote de parcelas
    'installment_modal' => [
        'edit_title' => 'Editar :count Cuota(s)',
        'new_due_date' => 'Nueva Fecha de Vencimiento',
        'due_date_hint' => 'Deje en blanco para mantener las fechas actuales',
        'payment_status' => 'Estado de Pago',
        'keep_current' => 'Mantener actual',
    ],

    // Informações de parcelamento
    'installment_info' => [
        'title' => 'Cómo usar el fraccionamiento:',
        'step_1' => 'Rellene los datos del movimiento en la pestaña "Datos Principales"',
        'step_2' => 'Indique el Subtotal o añada elementos',
        'step_3' => 'Configure el número de cuotas y la fecha de la primera cuota',
        'step_4' => 'Defina el intervalo (ej.: 1 mes, 15 días, 2 semanas)',
        'step_5' => 'Haga clic en "Generar Vista Previa" para visualizar las cuotas',
        'step_6' => 'Guarde el movimiento - todas las cuotas se crearán automáticamente',
        'tip' => 'El valor se dividirá a partes iguales entre las cuotas. Las diferencias de céntimos se ajustarán en la última cuota.',
    ],

    // Paginação
    'pagination' => [
        'rows_per_page' => 'Registros por página:',
        'showing' => 'Mostrando :start-:end de :total registros',
    ],

    // Hints (instruções de campos)
    'hints' => [
        'valor_subtotal' => 'Si hay elementos, se calculará automáticamente por la suma de los valores. En caso contrario, indíquelo manualmente. Una vez guardado, no puede modificarse.',
        'valor_total' => 'Suma automática: Subtotal + Intereses + Multa - Descuento.',
    ],

    // Itens - cabeçalhos
    'items_header' => [
        'description' => 'Descripción',
        'vehicle' => 'Vehículo',
        'chart_of_accounts' => 'Plan de Cuentas',
        'value' => 'Valor',
    ],

    // Parcelas - tipos de registro
    'record_types' => [
        'entry' => 'movimiento',
        'entries' => 'movimientos',
        'installments' => 'cuotas',
    ],
];
