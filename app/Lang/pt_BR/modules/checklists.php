<?php

/**
 * Traduções do módulo Checklists - Português (Brasil)
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
        'status' => 'Status',
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
        'plate' => 'Placa',
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
        'search' => 'Buscar...',
    ],

    // Mensagens
    'messages' => [
        'no_records' => 'Nenhum checklist encontrado',
        'load_error' => 'Erro ao carregar dados',
        'server_error' => 'Erro ao conectar com o servidor',
        'delete_error' => 'Erro ao excluir registro',
        'this_record' => 'este checklist',
        'mobile_only' => 'Esta funcionalidade está disponível apenas em celulares e tablets.',
    ],

    // Paginação
    'pagination' => [
        'rows_per_page' => 'Registros por página:',
        'showing' => 'Mostrando :start-:end de :total registros',
    ],

    // Tipo de registro
    'record_type' => 'checklist',

    // Checklist Digital (tela mobile)
    'digital' => [
        'title' => 'Checklist digital',
        'tab_info' => 'Infor',
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
        'odometer' => 'Odômetro atual',
        'observations' => 'Observações',
        'observations_placeholder' => 'Digite as observações...',
        'advance' => 'Avançar',
        'save' => 'Salvar',
        'clear' => 'Limpar',
        'close' => 'Fechar',
        'back' => 'Voltar',
        'list' => 'Lista',
        'new' => 'Novo checklist',
        'next_vehicle' => 'Fazer checklist do próximo veículo',
        'saved_success' => 'Checklist Salvo!',
        'saved_message' => 'O checklist foi finalizado com sucesso.',
        'auto_saved' => 'Salvo automaticamente',
        'questionnaire' => 'Questionário',
        'information' => 'Informações',
        'select' => 'Selecione...',
        'select_vehicle' => 'Selecione o veículo...',
        'select_link_first' => 'Selecione o vínculo primeiro...',
        'search_code_client' => 'Buscar por código ou cliente...',
        'search_plate_model' => 'Buscar por placa ou modelo...',
        'select_model' => 'Selecione o modelo...',
        'departure_done' => 'Saída feita',
        'arrival_done' => 'Chegada feita',
        'status_pending' => 'Pendente',
        'status_done' => 'Finalizado',
        'legend_linked' => 'Vinculado',
        'legend_standalone' => 'Avulso',
        'continue' => 'Continuar',
        'loading' => 'Carregando...',
        'processing' => 'Processando...',
        'creating' => 'Criando checklist...',
        'saving_questions' => 'Salvando questionário...',
        'saving_checklist' => 'Salvando checklist...',
        'sending_photo' => 'Enviando foto...',
        'deleting_photo' => 'Excluindo foto...',
        'no_records' => 'Nenhum checklist encontrado',
        'err_select_type' => 'Selecione o tipo',
        'err_select_moment' => 'Selecione o momento',
        'err_select_link' => 'Selecione uma locação ou contrato',
        'err_select_vehicle' => 'Selecione um veículo',
        'err_select_model' => 'Selecione um modelo de checklist',
        'err_select_tank' => 'Selecione o nível do tanque',
        'err_fill_odometer' => 'Informe o odômetro atual',
        'err_answer_all' => 'Responda todas as questões (:count pendente(s))',
        'err_sign' => 'Desenhe a assinatura antes de salvar',
        'err_min_photo' => 'Tire pelo menos uma foto da vistoria',
    ],
];
