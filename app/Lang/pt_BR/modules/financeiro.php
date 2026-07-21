<?php

/**
 * Traduções do módulo Financeiro - Português (Brasil)
 */

return [
    // Títulos
    'title' => 'Lançamentos Financeiros',
    'title_singular' => 'Lançamento Financeiro',
    'new_title' => 'Novo Lançamento',
    'edit_title' => 'Editar Lançamento',

    // Campos
    'fields' => [
        'type' => 'Tipo',
        'type_expense' => 'Despesa (Pagar)',
        'type_revenue' => 'Receita (Receber)',
        'bank_account' => 'Conta bancária',
        'payment_method' => 'Forma de Pagamento',
        'chart_of_accounts' => 'Plano de Contas',
        'description' => 'Descrição',
        'document' => 'Documento',
        'creation_date' => 'Data Criação',
        'due_date' => 'Data Vencimento',
        'is_paid' => 'Lançamento Pago',
        'payment_date' => 'Data do Pagamento',
        'branch' => 'Matriz/Filial',
        'client' => 'Cliente',
        'supplier' => 'Fornecedor',
        'employee' => 'Funcionário',
        'vehicle' => 'Veículo',
        'subtotal' => 'Subtotal',
        'interest' => 'Juros',
        'penalty' => 'Multa',
        'discount' => 'Desconto',
        'total_value' => 'Valor Total',
        'installment_count' => 'Número de Parcelas',
        'first_installment_date' => 'Data 1ª Parcela',
        'interval' => 'Intervalo',
        'interval_type' => 'Tipo de Intervalo',
        'original_invoice_value' => 'Valor original da fatura',
        'amount_received' => 'Valor recebido',
        'difference_to_create' => 'Diferença a criar',
        'difference_due_date' => 'Vencimento da diferença',
    ],

    // Seções
    'sections' => [
        'basic_data' => 'Dados Básicos',
        'links' => 'Vínculo(s)',
        'links_hint' => 'preencha pelo menos um: Cliente, Fornecedor, Funcionário ou Veículo',
        'values' => 'Valores',
        'items' => 'Itens do Lançamento',
        'items_hint' => 'opcional - se informado, o Subtotal será calculado automaticamente',
        'generate_installments' => 'Gerar Parcelas',
        'installments_preview' => 'Preview das Parcelas',
        'installments_list' => 'Parcelas do Lançamento',
        'partial_payment' => 'Pagamento parcial',
    ],

    // Abas
    'tabs' => [
        'main_data' => 'Dados Principais',
        'installments' => 'Parcelamento',
    ],

    // Filtros
    'filters' => [
        'branch' => 'Filial',
        'all_branches' => 'Todas',
        'year' => 'Ano',
        'all_years' => 'Todos',
        'month' => 'Mês',
        'all_months' => 'Todos',
        'status' => 'Status',
        'all_statuses' => 'Todos',
        'status_paid' => 'Pago',
        'status_due_today' => 'Vence hoje',
        'status_open' => 'Em aberto',
        'status_overdue' => 'Vencido',
        'clear_title' => 'Limpar filtros',
        'search_placeholder' => 'Buscar lançamento...',
    ],

    // Tabela
    'table' => [
        'seq' => 'Seq.',
        'description' => 'Descrição',
        'client_supplier_employee' => 'Cliente/Fornec/Func',
        'client_supplier_employee_full' => 'Cliente/Fornecedor/Funcionário',
        'due_date' => 'Vencimento',
        'value' => 'Valor',
        'vehicle_plates_label' => 'Placa(s)',
        'installment' => 'Parcela',
    ],

    // Status
    'status' => [
        'paid' => 'Pago',
        'partial_paid' => 'Pago parcial',
        'pending' => 'Pendente',
        'due_in' => 'Vence em :days',
        'due_today' => 'Vence hoje',
        'overdue' => 'Venceu',
        'day_singular' => '1 dia',
        'days_plural' => ':count dias',
    ],

    // Tipos de intervalo
    'interval_types' => [
        'days' => 'Dias',
        'weeks' => 'Semanas',
        'months' => 'Meses',
        'years' => 'Anos',
    ],

    // Botões específicos do módulo
    'buttons' => [
        'add_item' => 'Adicionar Item',
        'generate_preview' => 'Gerar Preview',
        'edit_selected' => 'Editar Selecionados',
        'delete_selected' => 'Excluir Selecionados',
        'delete_selected_count' => 'Excluir selecionados (:count)',
        'select_all_visible' => 'Selecionar todos os registros visíveis',
        'payment_link' => 'Link de Pagamento',
        'print_send' => 'Imprimir / Enviar Fatura',
        'remove_item' => 'Remover item',
        'create_difference' => 'Criar diferença',
    ],

    // Impressão e envio de fatura
    'print' => [
        'title' => 'Imprimir Fatura',
        'entry_label' => 'Lançamento',
        'value_label' => 'Valor',
        'due_label' => 'Vencimento',
        'print_type' => 'Tipo de Impressão',
        'invoice' => 'Fatura',
        'generate_pdf' => 'Gerar PDF',
        'send_via' => 'Enviar por',
        'no_channels_available' => 'Cliente sem e-mail nem telefone cadastrado, ou canais de envio não habilitados no seu plano.',
        'expense_send_unavailable' => 'Despesas podem ser impressas em PDF, mas não são enviadas como cobrança ao fornecedor.',
        'sending' => 'Enviando...',
        'send_success' => 'Fatura enviada com sucesso',
        'send_error' => 'Erro ao enviar fatura',
        'send_connection_error' => 'Erro de conexão ao enviar',
    ],

    'print_pdf' => [
        'title' => 'Fatura :number',
        'invoice' => 'FATURA',
        'default_company' => 'Locadora',
        'company_tax_id' => 'CNPJ',
        'zip' => 'CEP',
        'phone_short' => 'Tel',
        'number' => 'Número',
        'issue_date' => 'Emissão',
        'due_date' => 'Vencimento',
        'paid_at' => 'Pago em',
        'customer' => 'Cliente',
        'supplier' => 'Fornecedor',
        'name' => 'Nome',
        'tax_id' => 'CPF/CNPJ',
        'address' => 'Endereço',
        'city_state' => 'Cidade/UF',
        'email' => 'E-mail',
        'phone' => 'Telefone',
        'description' => 'Descrição',
        'vehicles' => 'Veículo(s)',
        'items' => 'Itens',
        'value' => 'Valor',
        'subtotal' => 'Subtotal',
        'interest' => 'Juros',
        'penalty' => 'Multa',
        'discount' => 'Desconto',
        'total' => 'TOTAL',
        'observations' => 'Observações',
        'online_payment_link' => 'Link para pagamento online',
        'generated_at' => 'Gerado em :date',
        'status_paid' => 'PAGO',
        'status_overdue' => 'VENCIDO',
        'status_open' => 'EM ABERTO',
    ],

    // Mensagens
    'messages' => [
        'no_records' => 'Nenhum lançamento encontrado',
        'no_description' => 'Sem descrição',
        'load_error' => 'Erro ao carregar lançamentos: :message',
        'connection_error' => 'Erro ao conectar com o servidor',
        'delete_confirm' => 'Deseja excluir o lançamento ":name"?',
        'delete_error' => 'Erro ao excluir lançamento',
        'selected_entries' => ':count lançamento(s) selecionado(s)',
        'batch_delete_error' => 'Erro ao excluir lançamentos selecionados',
        'batch_delete_partial_title' => 'Exclusão concluída parcialmente',
        'save_error' => 'Erro ao salvar lançamento',
        'not_found' => 'Lançamento não encontrado',
        'load_single_error' => 'Erro ao carregar lançamento',
        'this_entry' => 'este lançamento',
        'no_items' => 'Nenhum item adicionado',
        'item_description_placeholder' => 'Descrição do item...',
        'subtotal_converted' => 'Subtotal (convertido)',
        'no_installments' => 'Este lançamento não possui parcelas vinculadas',
        'inform_first_date' => 'Informe a data da primeira parcela',
        'value_must_be_positive' => 'O valor total deve ser maior que zero',
        'installment_count_range' => 'O número de parcelas deve estar entre :min e :max',
        'select_installment' => 'Selecione pelo menos uma parcela',
        'inform_field_update' => 'Informe pelo menos um campo para atualizar',
        'installments_updated' => ':count parcela(s) atualizada(s)',
        'installments_update_error' => 'Erro ao atualizar parcelas',
        'installments_deleted' => ':count parcela(s) excluída(s)',
        'installments_delete_error' => 'Erro ao excluir parcelas',
        'payment_link_error' => 'Erro ao gerar link de pagamento',
        'partial_difference_hint' => 'A diferença será criada como uma nova fatura pendente.',
        'save_before_partial' => 'Salve o lançamento antes de registrar pagamento parcial',
        'partial_value_invalid' => 'Informe um valor recebido maior que zero e menor que o valor total',
        'partial_payment_date_required' => 'Informe a data do pagamento',
        'partial_difference_due_required' => 'Informe o vencimento da diferença',
        'partial_success' => 'Baixa parcial registrada com sucesso',
        'partial_error' => 'Erro ao registrar baixa parcial',
        'partial_use_button' => 'Use o botão Criar diferença para registrar pagamento parcial',
        // Validação
        'required_field' => 'Campo obrigatório: :field',
        'fill_at_least_one_link' => 'Preencha pelo menos um: Cliente, Fornecedor, Funcionário ou Veículo',
        'vehicle_link_item_mismatch' => 'O veículo do vínculo é diferente do veículo informado em um item. Remova o veículo do vínculo ou use o mesmo veículo nos itens.',
        'inform_value_or_item' => 'Informe o Subtotal ou adicione pelo menos um item',
        'payment_date_required' => 'Data do Pagamento é obrigatória quando o lançamento está marcado como pago',
    ],

    // Modal de edição em lote de parcelas
    'installment_modal' => [
        'edit_title' => 'Editar :count Parcela(s)',
        'new_due_date' => 'Nova Data de Vencimento',
        'due_date_hint' => 'Deixe em branco para manter as datas atuais',
        'payment_status' => 'Status de Pagamento',
        'keep_current' => 'Manter atual',
    ],

    // Informações de parcelamento
    'installment_info' => [
        'title' => 'Como usar o parcelamento:',
        'step_1' => 'Preencha os dados do lançamento na aba "Dados Principais"',
        'step_2' => 'Informe o Subtotal ou adicione itens',
        'step_3' => 'Configure o número de parcelas e a data da primeira parcela',
        'step_4' => 'Defina o intervalo (ex: 1 mês, 15 dias, 2 semanas)',
        'step_5' => 'Clique em "Gerar Preview" para visualizar as parcelas',
        'step_6' => 'Salve o lançamento - todas as parcelas serão criadas automaticamente',
        'tip' => 'O valor será dividido igualmente entre as parcelas. Diferenças de centavos serão ajustadas na última parcela.',
    ],

    // Paginação
    'pagination' => [
        'rows_per_page' => 'Registros por página:',
        'showing' => 'Mostrando :start-:end de :total registros',
    ],

    // Hints (instruções de campos)
    'hints' => [
        'valor_subtotal' => 'Se houver itens, será calculado automaticamente pela soma dos valores. Caso contrário, informe manualmente. Após salvar, não pode ser alterado.',
        'valor_total' => 'Soma automática: Subtotal + Juros + Multa - Desconto.',
    ],

    // Itens - cabeçalhos
    'items_header' => [
        'description' => 'Descrição',
        'vehicle' => 'Veículo',
        'chart_of_accounts' => 'Plano de Contas',
        'value' => 'Valor',
    ],

    // Parcelas - tipos de registro
    'record_types' => [
        'entry' => 'lançamento',
        'entries' => 'lançamentos',
        'installments' => 'parcelas',
    ],
];
