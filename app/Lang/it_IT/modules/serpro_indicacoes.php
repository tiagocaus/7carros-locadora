<?php

return [
    'instructions' => [
        'button' => 'Istruzioni',
        'title' => 'Come Funziona la Nomina del Conducente',
        'what_is_title' => 'Cos\'è la Nomina del Conducente?',
        'what_is_text' => 'Quando un veicolo della flotta riceve una multa, la sanzione viene emessa a nome dell\'azienda (proprietaria). La nomina del conducente è il processo legale per trasferire la responsabilità dell\'infrazione alla persona che stava effettivamente guidando in quel momento.',

        'real_infrator_title' => 'Tipo 1: Trasgressore Reale',
        'real_infrator_desc' => 'Utilizzato per trasferire i punti e la responsabilità di una multa specifica a chi ha realmente commesso l\'infrazione.',
        'real_infrator_when' => 'Quando si riceve una multa e si identifica chi stava guidando',
        'real_infrator_prereq' => 'La multa deve essere stata importata tramite SERPRO (con codice ente, AIT e codice infrazione)',
        'real_infrator_fields' => 'Selezionare la multa + CPF del conducente',
        'real_infrator_result' => 'I punti dell\'infrazione vengono trasferiti alla patente del nominato',

        'principal_title' => 'Tipo 2: Conducente Principale',
        'principal_desc' => 'Utilizzato per registrare il conducente abituale di un veicolo. Non è collegato a una multa specifica.',
        'principal_when' => 'Quando si noleggia un veicolo a un cliente, registrarlo come conducente principale',
        'principal_advantage' => 'Le future multe per questo veicolo saranno automaticamente indirizzate al conducente registrato',
        'principal_fields' => 'Targa del veicolo + CPF + numero patente',
        'principal_important' => 'Può essere rimosso al termine del noleggio',

        'status_title' => 'Stato della Nomina',
        'status_enviado' => 'Nomina inviata al SERPRO',
        'status_processando' => 'In analisi dall\'ente',
        'status_aceito' => 'Nomina accettata con successo',
        'status_rejeitado' => 'Nomina rifiutata dall\'ente',
        'status_cancelado' => 'Annullata dall\'azienda di noleggio',
        'status_expirado' => 'Termine della nomina scaduto',

        'important_title' => 'Informazioni Importanti',
        'important_1' => 'La nomina del Trasgressore Reale funziona solo per le multe importate dal SERPRO (non per le multe registrate manualmente)',
        'important_2' => 'Il CNPJ dell\'azienda deve essere configurato nelle Impostazioni SERPRO prima di effettuare nomine',
        'important_3' => 'Dopo l\'invio, usare il pulsante di sincronizzazione per verificare lo stato aggiornato su SERPRO',
        'important_4' => 'Le nomine con stato "Inviato" o "In elaborazione" possono essere annullate',
        'important_5' => 'Rispettare le scadenze legali: la nomina deve essere effettuata entro il termine stabilito nella notifica',

        'steps_title' => 'Passo dopo Passo',
        'step_1' => 'Cliccare su "Nuova Nomina"',
        'step_2' => 'Scegliere il tipo: Trasgressore Reale o Conducente Principale',
        'step_3' => 'Per Trasgressore Reale: selezionare la multa e inserire il CPF del conducente',
        'step_4' => 'Per Conducente Principale: inserire la targa, CPF e numero patente',
        'step_5' => 'Cliccare su "Invia Nomina"',
        'step_6' => 'Seguire lo stato nella tabella e usare il pulsante di sincronizzazione per aggiornare',

        'label_when' => 'Quando usare:',
        'label_prereq' => 'Prerequisito:',
        'label_fields' => 'Campi necessari:',
        'label_result' => 'Risultato:',
        'label_advantage' => 'Vantaggio:',
        'label_important' => 'Importante:',
    ],
];
