<?php
return [
    'tab'=>'Siniestros','title'=>'Siniestros','register'=>'Registrar siniestro','edit'=>'Editar siniestro','loading'=>'Cargando siniestros...','save_first'=>'Guarde primero el contrato/alquiler para registrar siniestros.','empty'=>'No hay siniestros registrados.','load_error'=>'No se pudieron cargar los siniestros.','save_error'=>'No se pudo guardar el siniestro.','charge_error'=>'No se pudo generar el cobro.','required'=>'Complete fecha, vehículo, tipo y descripción.','charge_required'=>'Complete valor, vencimiento, cuenta y forma de pago.','charge_title'=>'Generar cobro del siniestro','charge_created'=>'Cobro generado correctamente.','view_charge'=>'Ver cobro','edit_action'=>'Editar','generate_charge_action'=>'Generar cobro','not_generated'=>'No generado','saved'=>'Siniestro guardado correctamente.',
    'fields'=>['date'=>'Fecha y hora','vehicle'=>'Vehículo','type'=>'Tipo','estimated_value'=>'Valor estimado','description'=>'Descripción','status'=>'Estado','notes'=>'Observaciones','charge'=>'Cobro','actions'=>'Acciones'],
    'types'=>['collision'=>'Colisión/accidente','theft'=>'Hurto/robo','fire'=>'Incendio','flood'=>'Inundación','third_party'=>'Daños a terceros','total_loss'=>'Pérdida total','other'=>'Otros'],
    'status'=>['open'=>'Abierto','completed'=>'Concluido'],
    'charge'=>['generate'=>'Generar cobro al cliente','value'=>'Valor','due_date'=>'Vencimiento','account'=>'Cuenta bancaria','payment_method'=>'Forma de pago','pending'=>'Pendiente','paid'=>'Pagado'],
    'report'=>['title'=>'Siniestros','description'=>'Siniestros registrados en contratos y alquileres, con valores y estado del cobro.','total'=>'Total de siniestros','open'=>'Abiertos','completed'=>'Concluidos','charged_value'=>'Valor cobrado','client'=>'Cliente','link'=>'Vínculo'],
];
