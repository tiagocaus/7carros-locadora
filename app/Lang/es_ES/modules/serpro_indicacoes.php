<?php

return [
    'instructions' => [
        'button' => 'Instrucciones',
        'title' => 'Como Funciona la Indicación de Conductor',
        'what_is_title' => 'Qué es la Indicación de Conductor?',
        'what_is_text' => 'Cuando un vehículo de la flota recibe una multa, la sancion se emite a nombre de la empresa (propietaria). La indicación de conductor es el proceso legal de transferir la responsabilidad de la infracción a la persona que realmente estaba conduciendo en ese momento.',

        'real_infrator_title' => 'Tipo 1: Infractor Real',
        'real_infrator_desc' => 'Se utiliza para transferir los puntos y la responsabilidad de una multa específica a quien realmente cometió la infracción.',
        'real_infrator_when' => 'Al recibir una multa e identificar quien estaba conduciendo',
        'real_infrator_prereq' => 'La multa debe haber sido importada via SERPRO (con código del órgano, AIT y código de infracción)',
        'real_infrator_fields' => 'Seleccionar la multa + CPF del conductor',
        'real_infrator_result' => 'Los puntos de la infracción se transfieren a la licencia del indicado',

        'principal_title' => 'Tipo 2: Conductor Principal',
        'principal_desc' => 'Se utiliza para registrar el conductor habitual de un vehículo. No está vinculado a una multa específica.',
        'principal_when' => 'Al alquilar un vehículo a un cliente, registrarlo como conductor principal',
        'principal_advantage' => 'Las futuras multas de este vehículo serán automáticamente dirigidas al conductor registrado',
        'principal_fields' => 'Placa del vehículo + CPF + licencia de conducir',
        'principal_important' => 'Puede ser eliminado cuando termine el alquiler',

        'status_title' => 'Estado de la Indicación',
        'status_enviado' => 'Indicación enviada al SERPRO',
        'status_processando' => 'En analisis por el órgano',
        'status_aceito' => 'Indicación aceptada con exito',
        'status_rejeitado' => 'Indicación rechazada por el órgano',
        'status_cancelado' => 'Cancelada por la empresa de alquiler',
        'status_expirado' => 'Plazo de indicación vencido',

        'important_title' => 'Información Importante',
        'important_1' => 'La indicación de Infractor Real solo funciona para multas importadas del SERPRO (no para multas registradas manualmente)',
        'important_2' => 'El CNPJ de la empresa debe estar configurado en Configuración SERPRO antes de realizar indicaciones',
        'important_3' => 'Después del envío, use el botón de sincronización para consultar el estado actualizado en SERPRO',
        'important_4' => 'Las indicaciones con estado "Enviado" o "Procesando" pueden ser canceladas',
        'important_5' => 'Respete los plazos legales: la indicación debe realizarse dentro del plazo estipulado en la notificación',

        'steps_title' => 'Paso a Paso',
        'step_1' => 'Haga clic en "Nueva Indicación"',
        'step_2' => 'Elija el tipo: Infractor Real o Conductor Principal',
        'step_3' => 'Para Infractor Real: seleccione la multa e ingrese el CPF del conductor',
        'step_4' => 'Para Conductor Principal: ingrese la placa, CPF y licencia de conducir',
        'step_5' => 'Haga clic en "Enviar Indicación"',
        'step_6' => 'Siga el estado en la tabla y use el botón de sincronización para actualizar',

        'label_when' => 'Cuando usar:',
        'label_prereq' => 'Prerrequisito:',
        'label_fields' => 'Campos necesarios:',
        'label_result' => 'Resultado:',
        'label_advantage' => 'Ventaja:',
        'label_important' => 'Importante:',
    ],
];
