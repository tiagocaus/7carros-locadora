<?php

/**
 * Traduções do módulo Checklists - Português (Portugal)
 */

return [
    // Título
    'title' => 'Checklists',

    // Tabela
    'table' => [
        'code' => 'Código',
        'model' => 'Modelo',
        'vehicle' => 'Veículo',
        'date' => 'Data',
        'type' => 'Tipo',
        'actions' => 'Ações',
        'status' => 'Estado',
    ],

    // Tipos
    'types' => [
        'linked' => 'Vinculado',
        'standalone' => 'Avulso',
    ],

    // Impressão
    'print' => [
        'doc_title' => 'CHECKLIST DE VEÍCULO',
        'code' => 'Código',
        'type' => 'Tipo',
        'date' => 'Data',
        'title_prefix' => 'Checklist',
        'landscape' => 'Paisagem',
        'portrait' => 'Retrato',
        'plate' => 'Matrícula',
        'vehicle' => 'Veículo',
        'renavam' => 'Renavam',
        'departure' => 'SAÍDA',
        'arrival' => 'CHEGADA',
        'questionnaire' => 'Questionário',
        'item' => 'Item',
        'answer' => 'Resposta',
        'observations' => 'Observações',
        'inspection_photos' => 'Vistoria (Fotos)',
        'no_arrival_data' => 'Sem dados de chegada',
        'signature_departure' => 'Assinatura Saída',
        'signature_arrival' => 'Assinatura Chegada',
        'signature' => 'Assinatura',
    ],

    // Badges de resposta
    'answers' => [
        'matches' => 'Confere',
        'not_matches' => 'Não confere',
        'damaged' => 'Danificado',
        'na' => 'N/A',
    ],

    // Placeholders
    'placeholders' => [
        'search' => 'Pesquisar...',
    ],

    // Mensagens
    'messages' => [
        'no_records' => 'Nenhum checklist encontrado',
        'load_error' => 'Erro ao carregar dados',
        'server_error' => 'Erro ao ligar ao servidor',
        'delete_error' => 'Erro ao eliminar registo',
        'this_record' => 'este checklist',
        'mobile_only' => 'Para realizar o checklist, aceda a este sistema pelo navegador de um telemóvel ou tablet.',
    ],

    // Paginação
    'pagination' => [
        'rows_per_page' => 'Registos por página:',
        'showing' => 'A mostrar :start-:end de :total registos',
    ],

    // Tipo de registo
    'record_type' => 'checklist',

    // Checklist digital
    'digital' => [
        'title' => 'Checklist digital',
        'tab_info' => 'Info',
        'tab_questions' => 'Questões',
        'tab_inspection' => 'Vistorias',
        'tab_signature' => 'Assinatura',
        'type' => 'Tipo',
        'type_standalone' => 'Avulso',
        'type_linked' => 'Vinculado',
        'moment' => 'Momento',
        'moment_departure' => 'Saída',
        'moment_arrival' => 'Chegada',
        'vehicle' => 'Veículo',
        'contract_rental' => 'Locação / Contrato',
        'checklist_model' => 'Modelo do checklist',
        'tank' => 'Tanque',
        'battery_charge' => 'Carga da Bateria',
        'odometer' => 'Odómetro atual',
        'observations' => 'Observações',
        'observations_placeholder' => 'Escreva as observações...',
        'advance' => 'Avançar',
        'save' => 'Guardar',
        'clear' => 'Limpar',
        'close' => 'Fechar',
        'back' => 'Voltar',
        'list' => 'Lista',
        'new' => 'Novo checklist',
        'next_vehicle' => 'Fazer checklist do próximo veículo',
        'saved_success' => 'Checklist Guardado!',
        'saved_message' => 'O checklist foi finalizado com sucesso.',
        'auto_saved' => 'Guardado automaticamente',
        'questionnaire' => 'Questionário',
        'information' => 'Informações',
        'select' => 'Selecione...',
        'select_vehicle' => 'Selecione o veículo...',
        'select_link_first' => 'Selecione o vínculo primeiro...',
        'search_code_client' => 'Pesquisar por código ou cliente...',
        'search_plate_model' => 'Pesquisar por matrícula ou modelo...',
        'select_model' => 'Selecione o modelo...',
        'departure_done' => 'Saída feita',
        'arrival_done' => 'Chegada feita',
        'status_pending' => 'Pendente',
        'status_done' => 'Finalizado',
        'legend_linked' => 'Vinculado',
        'legend_standalone' => 'Avulso',
        'continue' => 'Continuar',
        'loading' => 'A carregar...',
        'processing' => 'A processar...',
        'creating' => 'A criar checklist...',
        'saving_questions' => 'A guardar questionário...',
        'saving_checklist' => 'A guardar checklist...',
        'sending_photo' => 'A enviar foto...',
        'deleting_photo' => 'A eliminar foto...',
        'no_records' => 'Nenhum checklist encontrado',
        'err_select_type' => 'Selecione o tipo',
        'err_select_moment' => 'Selecione o momento',
        'err_select_link' => 'Selecione uma locação ou contrato',
        'err_select_vehicle' => 'Selecione um veículo',
        'err_select_model' => 'Selecione um modelo de checklist',
        'err_select_tank' => 'Selecione o nível do tanque',
        'err_fill_odometer' => 'Indique o odómetro atual',
        'err_answer_all' => 'Responda a todas as questões (:count pendente(s))',
        'err_sign' => 'Desenhe a assinatura antes de guardar',
        'err_min_photo' => 'Tire pelo menos uma foto da vistoria',
    ],
];
