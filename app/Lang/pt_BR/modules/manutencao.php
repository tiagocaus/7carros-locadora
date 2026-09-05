<?php

/**
 * Traduções do módulo Manutenção - Português (Brasil)
 *
 * Contém labels de itens compartilhados entre as telas:
 * - Plano de Manutenções (CRUD)
 * - Manutenções (OS)
 * - CRON de verificação
 */

return [
    // Títulos gerais
    'title' => 'Manutenção',
    'preventive_title' => 'Manutenção Preventiva',

    // Labels dos itens de manutenção (compartilhados)
    'items' => [
        'motor_oleo' => 'Óleo do motor',
        'motor_filtrooleo' => 'Filtro de óleo',
        'motor_correiadentada' => 'Correia dentada',
        'motor_correiaalternador' => 'Correia do alternador',
        'motor_correiaarcondicionado' => 'Correia do ar condicionado',
        'motor_correiabombadagua' => "Correia da bomba d'água",
        'motor_filtrodear' => 'Filtro de ar do motor',
        'motor_filtrodecabine' => 'Filtro de ar da cabine',
        'motor_filtrodecombustivel' => 'Filtro de combustível',
        'motor_fluidodofreio' => 'Fluido de freio',
        'motor_fluidoembreagem' => 'Fluido da embreagem',
        'motor_discodeembreagem' => 'Disco de embreagem',
        'motor_fluidocaixademarcha' => 'Fluido da caixa de marcha',
        'motor_limpesaarrefecimento' => 'Limpeza do arrefecimento',
        'motor_vejas' => 'Velas de ignição',
        'motor_bateria' => 'Bateria',
        'rodagem_pneus' => 'Pneus',
        'rodagem_alinhamento' => 'Alinhamento',
        'rodagem_pastilhasdefreio' => 'Pastilhas de freio',
        'rodagem_discodefreios' => 'Discos de freio',
        'rodagem_rodiziodepneus' => 'Rodízio de pneus',
        'acessorio_paletasparabrisa' => 'Palhetas do parabrisa',
        'moto_corrente' => 'Corrente de transmissão',
        'moto_kitrelacao' => 'Kit relação (coroa/pinhão)',
        'moto_oleosuspensao' => 'Óleo de suspensão/garfo',
        'moto_caboembreagem' => 'Cabo de embreagem',
        'moto_caboacelerador' => 'Cabo de acelerador',
    ],

    // Categorias (agrupamento na UI)
    'categories' => [
        'motor' => 'Motor',
        'rodagem' => 'Rodagem',
        'acessorio' => 'Acessórios',
        'moto' => 'Moto',
    ],

    // Mensagens do CRON
    'cron' => [
        'processing_tenant' => 'Processando tenant: :chave',
        'os_generated' => 'OS :código gerada para veículo :placa',
        'finished' => 'Finalizado: :tenants tenants | :veiculos veículos | :os OS geradas',
        'result' => 'Processados :tenants tenants, :veiculos veículos, :os OS geradas',
    ],

    // Logs de auditoria
    'audit' => [
        'os_created' => 'Sistema gerou manutenção preventiva para veículo [:placa] - OS [:código]',
    ],

    // Campos da OS gerada
    'os' => [
        'reason' => 'Manutenção preventiva gerada pelo sistema.',
        'status_created' => 'Criada pelo sistema',
    ],

    // Notificações (por veículo - detalhadas)
    'notifications' => [
        'email_subject' => 'Manutenção Preventiva - Placa :placa',
        'email_body' => "Veículo: :placa\nOdômetro Atual: :odômetro km\n\nItens próximos de manutenção:\n:itens\n\nUma Ordem de Serviço foi criada automaticamente.",
        'whatsapp_title' => '*Manutenção Preventiva*',
        'whatsapp_body' => "Veículo: :placa\nItens: :itens\n\nOS criada automaticamente no sistema.",
    ],

    // Notificações do CRON (consolidadas por tenant)
    'cron_notifications' => [
        'email_subject' => 'Manutenções Preventivas Criadas',
        'email_body' => 'Foram criadas manutenções preventivas, acesse o menu veículos > manutenções.',
        'sms_body' => 'Foram criadas manutenções preventivas, acesse o menu veículos > manutenções.',
        'whatsapp_body' => '*[7Carros]* Foram criadas manutenções preventivas, acesse o menu veículos > manutenções.',
    ],

    // ===== Views (index.php + adicionar.php) =====

    // Títulos das views
    'title_list' => 'Manutenções',
    'new_title' => 'Nova Manutenção',
    'edit_title' => 'Editar Manutenção',

    // Abas
    'tabs' => [
        'data' => 'Dados',
        'items' => 'Itens',
        'financial' => 'Financeiro',
    ],

    // Seções
    'sections' => [
        'maintenance_data' => 'Dados da Manutenção',
        'send_to_workshop' => 'Envio para oficina',
        'return_from_workshop' => 'Retorno da oficina',
        'services_performed' => 'Serviços Realizados',
        'services_performed_note' => 'Essas informações são apenas para registro e poderão ser usadas em cálculos futuros.',
        'maintenance_items' => 'Itens da Manutenção',
        'financial_entries' => 'Lançamentos Financeiros',
        'entry_config' => 'Configuração do Lançamento',
        'generated_installments' => 'Parcelas geradas',
    ],

    // Campos
    'fields' => [
        'os' => 'OS',
        'status' => 'Status',
        'branch' => 'Matriz/Filial',
        'vehicle' => 'Veículo',
        'workshop' => 'Oficina',
        'client' => 'Cliente responsável pelo pagamento',
        'send_date' => 'Data Envio',
        'send_odometer' => 'Odômetro Envio',
        'send_tank' => 'Tanque Envio',
        'return_date' => 'Data Retorno',
        'return_odometer' => 'Odômetro Retorno',
        'return_tank' => 'Tanque Retorno',
        'odometer' => 'Odômetro',
        'tank' => 'Tanque',
        'send_reason' => 'Motivo do envio à oficina',
        'workshop_notes' => 'Observações da Oficina',
        'changed_oil' => 'Trocou Óleo',
        'changed_tires' => 'Trocou Pneus',
        'product' => 'Produto',
        'qty' => 'Qtd',
        'unit_value' => 'Valor Unit.',
        'discount' => 'Desconto',
        'total_value' => 'Valor Total',
        'action' => 'Ação',
        'description' => 'Descrição',
        'value' => 'Valor',
        'payment_method' => 'Forma de Pagamento',
        'bank_account' => 'Conta bancária',
        'chart_account' => 'Plano de contas',
        'installments' => 'Parcelas',
        'first_due_date' => '1º Vencimento',
        'due_date' => 'Vencimento',
        'interval_days' => 'Intervalo (dias)',
        'paid' => 'Pago?',
    ],

    'helpers' => [
        'client_payer' => 'Ao gerar o financeiro: com cliente, será uma conta a receber; sem cliente, será uma despesa da empresa.',
    ],

    // Opções de status
    'status_options' => [
        'created' => 'Criada',
        'created_by_system' => 'Criada pelo sistema',
        'open' => 'Aberta',
        'closed' => 'Fechada',
    ],

    // Níveis do tanque
    'tank_levels' => [
        'full' => 'Cheio',
        'reserve' => 'Reserva',
    ],

    // Badges
    'badges' => [
        'paid' => 'Pago',
        'pending' => 'Pendente',
        'new' => 'Novo',
        'editing' => 'Editando',
    ],

    // Ações
    'actions' => [
        'new' => 'Nova',
        'add_item' => 'Adicionar Item',
        'create_full_entry' => 'Criar Lançamento Completo',
        'close_selected' => 'Fechar Itens Selecionados',
        'go_to_list' => 'Ir para Listagem',
    ],

    // Tabela
    'table' => [
        'os' => 'OS',
        'vehicle' => 'Veículo',
        'workshop' => 'Oficina',
        'creation_date' => 'Data Criação',
        'send_date' => 'Data Envio',
        'total' => 'Total',
        'status' => 'Status',
        'actions' => 'Ações',
        'totals' => 'Totais:',
        'total_paid' => 'Total Pago:',
        'total_pending' => 'Total Pendente:',
        'total_selected' => 'Total Selecionado:',
    ],

    // Placeholders
    'placeholders' => [
        'search' => 'Buscar OS, veículo...',
        'select' => 'Selecione...',
        'search_type' => 'Digite para buscar...',
        'search_product' => 'Buscar produto...',
        'search_product_service' => 'Buscar produto/serviço...',
        'item_description' => 'Descrição do item',
        'manual_description' => 'Digitar descrição manual',
        'no_client_company_expense' => 'Nenhum cliente — despesa da empresa',
    ],

    // Mensagens
    'messages' => [
        'no_records' => 'Nenhuma manutenção encontrada',
        'load_error' => 'Erro ao carregar',
        'server_error' => 'Erro ao conectar',
        'delete_error' => 'Erro ao excluir',
        'save_error' => 'Erro ao salvar',
        'save_success' => 'Manutenção salva com sucesso',
        'no_items' => 'Nenhum item adicionado',
        'no_pending_items' => 'Nenhum item pendente',
        'select_product' => 'Selecione um produto',
        'cannot_remove_paid' => 'Não é possível remover itens pagos',
        'cannot_edit_paid' => 'Não é possível editar itens pagos',
        'provide_description' => 'Informe a descrição ou selecione um produto',
        'product_out_of_stock' => 'Produto sem estoque disponível.',
        'stock_insufficient' => 'So há :qty disponível(is). Quantidade ajustada.',
        'discount_exceeds_subtotal' => 'Desconto não pode ser maior que o subtotal do item.',
        'select_at_least_one' => 'Selecione pelo menos um item',
        'entry_created' => 'Lançamento criado com sucesso',
        'generic_error' => 'Erro',
        'odometer_required' => 'Informe o odômetro de retorno',
        'saved_title' => 'Manutenção Salva',
        'saved_go_to_list' => 'Deseja voltar para a listagem?',
        'financial_desc' => 'Selecione os itens pendentes para criar um lançamento financeiro parcial ou clique em "Criar Lançamento Completo" para lançar todos.',
        'installments_total_diff' => 'A soma das parcelas deve ser igual ao total selecionado.',
        'complete_financial_config' => 'Preencha a configuração do lançamento para gerar as parcelas.',
        'payer_confirm_title' => 'Confirmar cliente responsável pelo pagamento',
        'payer_confirm_selected' => 'Atenção! O cliente :client será responsável pelo pagamento dos novos lançamentos financeiros desta manutenção. Deseja confirmar?',
        'payer_confirm_removed' => 'Atenção! Nenhum cliente ficará responsável pelos novos lançamentos financeiros desta manutenção; eles serão registrados como despesa da empresa. Deseja confirmar?',
        'payer_existing_financial_warning' => 'Os lançamentos financeiros já criados não serão alterados.',
        'payer_confirm_action' => 'Confirmar e salvar',
        'payer_confirmation_required' => 'Confirme o cliente responsável pelo pagamento antes de salvar.',
    ],

    // Paginação
    'pagination' => [
        'rows_per_page' => 'Registros por página:',
        'showing' => 'Mostrando :start-:end de :total registros',
        'page_navigation' => 'Navegação de páginas',
    ],

    // Impressão
    'print' => [
        'title' => 'Ordem de Serviço',
        'action' => 'Imprimir',
        'cpf_cnpj_label' => 'CPF/CNPJ:',
    ],

    // Tipo de registro (para modal de exclusão)
    'record_type' => 'manutenção',

    // Opções do modal de exclusão
    'delete_options' => [
        'financial_linked' => 'Excluir financeiro vinculado',
        'restore_stock' => 'Repor estoque utilizado',
    ],

    // Auditoria do financeiro
    'audit_financial' => [
        'section' => 'Lançamento Financeiro',
        'type' => 'Tipo',
        'complete' => 'Completo',
        'partial' => 'Parcial',
        'payment_method' => 'Forma de Pagamento',
        'bank_account' => 'Conta bancária',
        'chart_account' => 'Plano de contas',
        'paid' => 'Pago',
        'generated_installments' => 'Parcelas Geradas',
        'installments' => 'Parcelas',
        'first_due_date' => '1º Vencimento',
        'interval' => 'Intervalo',
        'days' => 'dias',
        'total_value' => 'Valor Total',
        'selected_items' => 'Itens Selecionados',
        'item' => 'Item',
        'value' => 'Valor',
    ],

    'audit_payer' => [
        'confirmation_label' => 'Confirmação do cliente pagador',
        'confirmed' => 'Usuário informado e confirmou a definição ou alteração do cliente pagador.',
        'confirmed_existing_financial' => 'Usuário informado e confirmou a alteração do cliente pagador; lançamentos financeiros existentes não serão alterados.',
    ],
];
