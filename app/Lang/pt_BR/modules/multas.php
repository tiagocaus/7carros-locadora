<?php

return [
    'title' => 'Multas',
    'title_singular' => 'Multa',
    'new_title' => 'Nova Multa',
    'edit_title' => 'Editar Multa',

    'sections' => [
        'search_responsible' => 'Identificar Responsavel',
        'responsible_data' => 'Dados do Responsavel',
        'fine_data' => 'Dados da Multa',
    ],

    'fields' => [
        'date_time' => 'Data e Hora da Multa',
        'plate' => 'Placa do Veículo',
        'due_date' => 'Data de Vencimento',
        'value' => 'Valor',
        'infraction_number' => 'N. Infração',
        'issuing_body' => 'Órgão Autuador',
        'location' => 'Local',
        'city' => 'Cidade',
        'state' => 'Estado',
        'description' => 'Descrição',
        'type' => 'Tipo',
        'status' => 'Status',
        'branch' => 'Filial',
        'client' => 'Cliente',
        'vehicle' => 'Veículo',
        'contract_code' => 'Código do Contrato',
        'rental_code' => 'Código da Locação',
        'code' => 'Código',
        'photo' => 'Foto da Multa',
    ],

    'table' => [
        'plate' => 'Placa',
        'client' => 'Cliente',
        'type' => 'Tipo',
        'date_time' => 'Data/Hora',
        'value' => 'Valor',
        'status' => 'Status',
        'actions' => 'Ações',
    ],

    'badges' => [
        'type_contract' => 'Contrato',
        'type_rental' => 'Locação',
        'status_paid' => 'Pago',
        'status_pending' => 'Pendente',
        'status_unknown' => 'Sem tipo',
    ],

    'buttons' => [
        'search_responsible' => 'Buscar Responsavel',
        'continue' => 'Continuar com este responsavel',
        'mark_paid' => 'Marcar como Pago',
        'mark_unpaid' => 'Reverter Pagamento',
    ],

    'messages' => [
        'no_records' => 'Nenhuma multa encontrada',
        'load_error' => 'Erro ao carregar dados',
        'server_error' => 'Erro ao conectar com o servidor',
        'save_error' => 'Erro ao salvar',
        'created' => 'Multa registrada com sucesso!',
        'updated' => 'Multa atualizada com sucesso!',
        'deleted' => 'Multa excluída com sucesso!',
        'marked_paid' => 'Multa marcada como paga!',
        'marked_unpaid' => 'Pagamento revertido!',
        'not_found' => 'Multa não encontrada',
        'vehicle_not_found' => 'Veículo não encontrado com esta placa',
        'responsible_found' => 'Responsavel encontrado',
        'responsible_not_found' => 'Nenhum contrato ou locação encontrado para este veículo na data/hora informada.',
        'required_fields' => 'Preencha os campos obrigatorios:',
        'saving' => 'Salvando...',
        'searching' => 'Buscando...',
        'confirm_delete' => 'Deseja realmente excluir esta multa?',
        'confirm_mark_paid' => 'Deseja marcar esta multa como paga?',
        'confirm_mark_unpaid' => 'Deseja reverter o pagamento desta multa?',
        'cannot_delete_paid' => 'Não é possível excluir uma multa já paga.',
        'this_record' => 'esta multa',
        'select_doc_before_pdf' => 'Selecione um documento antes de gerar o PDF',
        'select_doc_before_send' => 'Selecione um documento antes de enviar',
        'sending' => 'Enviando...',
        'send_success' => 'Documento enviado com sucesso',
        'send_error' => 'Erro ao enviar o documento',
        'send_connection_error' => 'Erro de conexão ao enviar',
    ],

    'filters' => [
        'all_types' => 'Todos os tipos',
        'type_contract' => 'Contrato',
        'type_rental' => 'Locação',
        'all_status' => 'Todos os status',
        'paid' => 'Pago',
        'pending' => 'Pendente',
    ],

    'pagination' => [
        'rows_per_page' => 'Registros por página:',
        'showing' => 'Mostrando',
        'of' => 'de',
        'records' => 'registros',
    ],

    'actions' => [
        'new' => 'Nova Multa',
    ],

    'record_type' => 'multa',

    // =========================================================
    // Impressao (offcanvas-impressao.php)
    // =========================================================
    'print' => [
        'title' => 'Imprimir Multa',
        'fine_label' => 'Multa',
        'print_type' => 'Tipo de Documento',
        'notification' => 'Notificação ao Cliente',
        'document' => 'Documento Personalizado',
        'receipt' => 'Comprovante de Pagamento',
        'indication_term' => 'Termo de Indicação de Condutor',
        'select_document' => 'Selecionar Documento',
        'select_document_placeholder' => 'Escolha um modelo',
        'no_documents' => 'Nenhum modelo cadastrado para Multa',
        'generate_pdf' => 'Gerar PDF',
        'send_via' => 'Enviar por',
    ],

    // =========================================================
    // Templates PDF (notificacao, comprovante, termo, documento)
    // =========================================================
    'pdf' => [
        'notification_title' => 'Notificação de Multa',
        'receipt_title' => 'Comprovante de Pagamento de Multa',
        'indication_title' => 'Termo de Indicação de Condutor',
        'document_title' => 'Documento',
        'fine_data_section' => 'Dados da Infração',
        'vehicle_data_section' => 'Dados do Veículo',
        'fine_origin_section' => 'Dados da Multa',
        'client_section' => 'Dados do Cliente',
        'owner_section' => 'Dados do Proprietario',
        'driver_section' => 'Dados do Condutor (preencher)',
        'fine_number_label' => 'Número:',
        'date_label' => 'Data:',
        'ait_label' => 'AIT:',
        'infraction_code_label' => 'Código da Infração:',
        'issuing_body_label' => 'Órgão Autuador:',
        'location_label' => 'Local:',
        'city_state_label' => 'Cidade/UF:',
        'date_time_label' => 'Data/Hora:',
        'description_label' => 'Descrição:',
        'plate_label' => 'Placa:',
        'brand_model_label' => 'Marca/Modelo:',
        'value_label' => 'Valor a Pagar',
        'amount_paid_label' => 'Valor Pago',
        'discount_40_label' => 'Com desconto de 40%',
        'due_date_label' => 'Vencimento',
        'fine_date_label' => 'Data da Multa:',
        'client_name' => 'Nome:',
        'client_document' => 'CPF/CNPJ:',
        'company_name_label' => 'Razao Social:',
        'driver_name' => 'Nome',
        'driver_cpf' => 'CPF',
        'driver_cnh' => 'CNH',
        'driver_address' => 'Endereço',
        'driver_city' => 'Cidade',
        'driver_phone' => 'Telefone',
        'signature_place_label' => 'Local',
        'signature_date_label' => 'Data',
        'owner_signature' => 'Assinatura do Proprietario',
        'driver_signature' => 'Assinatura do Condutor',
        'witness_1' => 'Testemunha 1',
        'witness_2' => 'Testemunha 2',
        'indication_declaration' => 'Declaro, sob as penas da lei, que o condutor identificado acima foi o responsavel pela infração descrita.',
        'indication_footer' => 'Apresentar este termo ao órgão autuador dentro do prazo legal estabelecido pelo CTB.',
        'notification_text' => 'Prezado(a) :client, comunicamos que foi registrada uma multa de transito vinculada ao veículo de placa :plate. O valor a pagar e de :value, com vencimento em :due. Solicitamos a regularização no prazo indicado.',
        'receipt_text' => 'Recebemos de :client, portador(a) do documento :document, a importância de :value, referente a multa n. :fine_number do veículo de placa :plate, ocorrida em :fine_date. Para clareza, firmamos o presente recibo.',
        'receipt_validity' => 'Este recibo tem validade legal e comprova a quitação da multa identificada acima.',
        'generated_at' => 'Gerado em :datetime',
        'page_label' => 'Página :page de :total',
    ],

    // =========================================================
    // Central de Multas (central.php)
    // =========================================================
    'central' => [
        'title' => 'Central de Multas',
        'search_placeholder' => 'Buscar (nome, placa, AIT)',
        'add_fine' => 'Adicionar Multa',
        'check_online' => 'Consultar Multas',
        'check_batch' => 'Consultar Lote',

        'kpi' => [
            'overdue' => 'Vencidas',
            'expiring_30d' => 'Vencendo 30d',
            'on_time' => 'Em dia',
            'pending' => 'Pendentes',
            'paid' => 'Pagas',
            'pending_value' => 'Valor Pendente',
        ],

        'balance' => [
            'title' => 'Saldo Consultas',
            'manage' => 'Gerenciar',
            'query' => 'Consulta',
            'event' => 'Evento',
        ],

        'origin' => [
            'title' => 'Origem',
            'manual' => 'Manual',
            'online_query' => 'Consulta Online',
            'auto_event' => 'Evento Automático',
        ],

        'nominations' => [
            'title' => 'Indicações',
            'view_all' => 'Ver todas',
            'pending_nomination' => 'Pendentes de indicação',
            'new_unprocessed' => 'Novas (não processadas)',
            'sent' => 'Indicações enviadas',
        ],

        'automation' => [
            'title' => 'Automações',
            'auto_query' => 'Auto-consulta',
            'every' => 'a cada',
            'auto_events' => 'Eventos automáticos',
            'last_query' => 'Última consulta: :date',
            'interval_1d' => '1 dia',
            'interval_3d' => '3 dias',
            'interval_7d' => '7 dias',
            'interval_14d' => '14 dias',
            'interval_30d' => '30 dias',
        ],

        'filters' => [
            'type_all' => 'Tipo: Todos',
            'type_contract' => 'Contrato',
            'type_rental' => 'Locação',
            'payment_all' => 'Pgto: Todos',
            'payment_pending' => 'Pendentes',
            'payment_paid' => 'Pagas',
            'due_all' => 'Venc.: Todos',
            'due_overdue' => 'Vencidas',
            'due_expiring' => 'Vencendo 30d',
            'due_on_time' => 'Em dia',
            'origin_all' => 'Origem: Todas',
            'origin_manual' => 'Manual',
            'origin_online' => 'Consulta Online',
            'origin_event' => 'Evento Automático',
            'status_all' => 'Status: Todos',
            'status_new' => 'Novo',
            'status_pending_nomination' => 'Pendente Indicação',
            'status_nomination_sent' => 'Indicação Enviada',
            'status_nominated' => 'Indicado',
            'status_transferred' => 'Transferido',
        ],

        'table' => [
            'plate' => 'Placa',
            'client' => 'Cliente',
            'date' => 'Data',
            'infraction' => 'Infração',
            'value' => 'Valor',
            'due' => 'Venc.',
            'payment' => 'Pgto',
            'origin' => 'Origem',
            'status' => 'Status',
            'actions' => 'Ações',
        ],

        'pagination' => [
            'rows' => 'Linhas:',
            'showing' => 'Mostrando :start-:end de :total',
        ],

        'ranking' => [
            'title' => 'Ranking de Veículos com mais Multas',
            'position' => '#',
            'plate' => 'Placa',
            'model' => 'Modelo',
            'total' => 'Total',
            'pending' => 'Pendentes',
            'pending_value' => 'Valor Pendente',
            'no_data' => 'Nenhum dado disponível',
        ],

        'badges' => [
            'origin_query' => 'Consulta',
            'origin_event' => 'Evento',
            'origin_manual' => 'Manual',
            'paid' => 'Pago',
            'pending' => 'Pendente',
        ],

        'confirm' => [
            'mark_paid_title' => 'Marcar como Pago',
            'mark_paid_message' => 'Confirma marcar esta multa como paga?',
            'revert_title' => 'Reverter Pagamento',
            'revert_message' => 'Confirma reverter o pagamento desta multa?',
            'cannot_delete_paid' => 'Não é possível excluir uma multa já paga',
            'activate_auto_query_title' => 'Ativar Auto-consulta',
            'activate_auto_query_message' => 'A auto-consulta realizara consultas automaticas periodicas para todos os veículos brasileiros. Cada consulta consome saldo. Deseja ativar?',
            'activate_auto_events_title' => 'Ativar Eventos Automaticos',
            'activate_auto_events_message' => 'Eventos automáticos registram notificações em tempo real sobre novas infrações. Cada evento consome saldo. Deseja ativar?',
            'confirm_activate' => 'Sim, ativar',
        ],

        'toast' => [
            'fine_deleted' => 'Multa excluída com sucesso',
            'fine_marked_paid' => 'Multa marcada como paga',
            'payment_reverted' => 'Pagamento revertido',
            'config_error' => 'Erro ao atualizar configuração',
        ],

        'actions' => [
            'edit' => 'Editar',
            'nominate' => 'Indicar Real Infrator',
            'mark_paid' => 'Marcar como Pago',
            'mark_unpaid' => 'Marcar como Não Pago',
            'delete' => 'Excluir',
            'print' => 'Imprimir',
        ],
    ],

    // =========================================================
    // Indicacoes de Condutor (indicacao.php)
    // =========================================================
    'indicacoes' => [
        'title' => 'Indicações de Condutor',
        'new_nomination' => 'Nova Indicação',

        'summary' => [
            'total' => 'Total',
            'sent' => 'Enviadas',
            'pending' => 'Pendentes',
            'accepted' => 'Aceitas',
            'rejected' => 'Rejeitadas',
        ],

        'filters' => [
            'all_types' => 'Todos os tipos',
            'real_offender' => 'Real Infrator',
            'main_driver' => 'Principal Condutor',
            'all_status' => 'Todos os status',
            'sent' => 'Enviado',
            'processing' => 'Processando',
            'accepted' => 'Aceito',
            'rejected' => 'Rejeitado',
            'cancelled' => 'Cancelado',
            'deleted' => 'Excluído',
            'expired' => 'Expirado',
            'plate' => 'Placa',
        ],

        'table' => [
            'date' => 'Data',
            'type' => 'Tipo',
            'plate' => 'Placa',
            'nominee' => 'Indicado',
            'ait' => 'AIT',
            'status' => 'Status',
            'actions' => 'Ações',
        ],

        'pagination' => [
            'rows' => 'Linhas:',
            'showing' => 'Mostrando :start-:end de :total',
        ],

        'badges' => [
            'real_offender' => 'Real Infrator',
            'main_driver' => 'Principal Condutor',
        ],

        'messages' => [
            'loading' => 'Carregando...',
            'no_nominations' => 'Nenhuma indicação encontrada',
        ],

        'confirm' => [
            'cancel_title' => 'Cancelar Indicação',
            'cancel_message' => 'Tem certeza que deseja cancelar esta indicação?',
        ],

        'actions' => [
            'check_status' => 'Consultar status',
            'cancel' => 'Cancelar',
        ],
    ],

    // =========================================================
    // Saldo de Consultas (saldo.php)
    // =========================================================
    'saldo' => [
        'title' => 'Saldo de Consultas',

        'cards' => [
            'current_balance' => 'Saldo Atual',
            'total_spent' => 'Total Gasto',
            'total_recharged' => 'Total Recarregado',
            'prices_title' => 'Preços por Operação',
            'query' => 'Consulta:',
            'event' => 'Evento:',
        ],

        'buttons' => [
            'pix' => 'PIX',
            'card' => 'Cartão',
            'save' => 'Salvar',
        ],

        'auto_recharge' => [
            'title' => 'Auto-recarga',
            'threshold_label' => 'Recarregar quando saldo abaixo de',
            'value_label' => 'Valor da recarga',
            'requires_card' => 'Requer cartão de crédito salvo. A cobrança será feita automaticamente via Stripe.',
            'card_saved' => 'Cartão salvo configurado',
        ],

        'history_title' => 'Histórico de Transações',

        'filters' => [
            'type_all' => 'Tipo: Todos',
            'type_queries' => 'Consultas',
            'type_events' => 'Eventos',
            'type_pix' => 'Recarga PIX',
            'type_card' => 'Recarga Cartão',
            'until' => 'até',
        ],

        'table' => [
            'date' => 'Data',
            'type' => 'Tipo',
            'description' => 'Descrição',
            'value' => 'Valor',
            'balance' => 'Saldo',
            'status' => 'Status',
        ],

        'pagination' => [
            'rows' => 'Linhas:',
            'showing' => 'Mostrando :start-:end de :total registros',
        ],

        'badges' => [
            'query' => 'Consulta',
            'event' => 'Evento',
            'pix' => 'PIX',
            'card' => 'Cartão',
            'confirmed' => 'Confirmado',
            'pending' => 'Pendente',
            'failed' => 'Falha',
        ],

        'messages' => [
            'loading' => 'Carregando...',
            'no_transactions' => 'Nenhuma transação encontrada',
            'auto_recharge_updated' => 'Auto-recarga atualizada',
            'save_error' => 'Erro ao salvar',
        ],
    ],
];
