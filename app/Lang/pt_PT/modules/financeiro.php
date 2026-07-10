<?php

/**
 * Traduções do módulo Financeiro - Português (Portugal)
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
        'installment_count' => 'Número de Prestações',
        'first_installment_date' => 'Data 1ª Prestação',
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
        'items_hint' => 'opcional - se indicado, o Subtotal será calculado automaticamente',
        'generate_installments' => 'Gerar Prestações',
        'installments_preview' => 'Pré-visualização das Prestações',
        'installments_list' => 'Prestações do Lançamento',
        'partial_payment' => 'Pagamento parcial',
    ],

    // Abas
    'tabs' => [
        'main_data' => 'Dados Principais',
        'installments' => 'Prestações',
    ],

    // Filtros
    'filters' => [
        'branch' => 'Filial',
        'all_branches' => 'Todas',
        'year' => 'Ano',
        'all_years' => 'Todos',
        'month' => 'Mês',
        'all_months' => 'Todos',
        'clear_title' => 'Limpar filtros',
        'search_placeholder' => 'Procurar lançamento...',
    ],

    // Tabela
    'table' => [
        'seq' => 'Seq.',
        'description' => 'Descrição',
        'client_supplier_employee' => 'Cliente/Fornec/Func',
        'client_supplier_employee_full' => 'Cliente/Fornecedor/Funcionário',
        'due_date' => 'Vencimento',
        'value' => 'Valor',
        'vehicle_plates_label' => 'Matrícula(s)',
        'installment' => 'Prestação',
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
        'generate_preview' => 'Gerar Pré-visualização',
        'edit_selected' => 'Editar Selecionados',
        'delete_selected' => 'Eliminar Selecionados',
        'payment_link' => 'Link de Pagamento',
        'print_send' => 'Imprimir / Enviar Fatura',
        'remove_item' => 'Remover item',
        'create_difference' => 'Criar diferença',
    ],

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
        'sending' => 'A enviar...',
        'send_success' => 'Fatura enviada com sucesso',
        'send_error' => 'Erro ao enviar fatura',
        'send_connection_error' => 'Erro de ligação ao enviar',
    ],

    'print_pdf' => [
        'title' => 'Fatura :number',
        'invoice' => 'FATURA',
        'default_company' => 'Locadora',
        'company_tax_id' => 'NIF/NIPC',
        'zip' => 'Código postal',
        'phone_short' => 'Tel',
        'number' => 'Número',
        'issue_date' => 'Emissão',
        'due_date' => 'Vencimento',
        'paid_at' => 'Pago em',
        'customer' => 'Cliente',
        'supplier' => 'Fornecedor',
        'name' => 'Nome',
        'tax_id' => 'NIF/NIPC',
        'address' => 'Endereço',
        'city_state' => 'Cidade/Distrito',
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
        'connection_error' => 'Erro ao ligar ao servidor',
        'delete_confirm' => 'Deseja eliminar o lançamento ":name"?',
        'delete_error' => 'Erro ao eliminar lançamento',
        'save_error' => 'Erro ao guardar lançamento',
        'not_found' => 'Lançamento não encontrado',
        'load_single_error' => 'Erro ao carregar lançamento',
        'this_entry' => 'este lançamento',
        'no_items' => 'Nenhum item adicionado',
        'item_description_placeholder' => 'Descrição do item...',
        'subtotal_converted' => 'Subtotal (convertido)',
        'no_installments' => 'Este lançamento não possui prestações vinculadas',
        'inform_first_date' => 'Indique a data da primeira prestação',
        'value_must_be_positive' => 'O valor total deve ser maior que zero',
        'select_installment' => 'Selecione pelo menos uma prestação',
        'inform_field_update' => 'Indique pelo menos um campo para atualizar',
        'installments_updated' => ':count prestação(ões) atualizada(s)',
        'installments_update_error' => 'Erro ao atualizar prestações',
        'installments_deleted' => ':count prestação(ões) eliminada(s)',
        'installments_delete_error' => 'Erro ao eliminar prestações',
        'payment_link_error' => 'Erro ao gerar link de pagamento',
        'partial_difference_hint' => 'A diferença será criada como uma nova fatura pendente.',
        'save_before_partial' => 'Guarde o lançamento antes de registar pagamento parcial',
        'partial_value_invalid' => 'Indique um valor recebido maior que zero e menor que o valor total',
        'partial_payment_date_required' => 'Indique a data do pagamento',
        'partial_difference_due_required' => 'Indique o vencimento da diferença',
        'partial_success' => 'Baixa parcial registada com sucesso',
        'partial_error' => 'Erro ao registar baixa parcial',
        'partial_use_button' => 'Use o botão Criar diferença para registar pagamento parcial',
        // Validação
        'required_field' => 'Campo obrigatório: :field',
        'fill_at_least_one_link' => 'Preencha pelo menos um: Cliente, Fornecedor, Funcionário ou Veículo',
        'vehicle_link_item_mismatch' => 'O veículo do vínculo é diferente do veículo indicado num item. Remova o veículo do vínculo ou use o mesmo veículo nos itens.',
        'inform_value_or_item' => 'Indique o Subtotal ou adicione pelo menos um item',
        'payment_date_required' => 'Data do Pagamento é obrigatória quando o lançamento está marcado como pago',
    ],

    // Modal de edição em lote de parcelas
    'installment_modal' => [
        'edit_title' => 'Editar :count Prestação(ões)',
        'new_due_date' => 'Nova Data de Vencimento',
        'due_date_hint' => 'Deixe em branco para manter as datas atuais',
        'payment_status' => 'Estado de Pagamento',
        'keep_current' => 'Manter atual',
    ],

    // Informações de parcelamento
    'installment_info' => [
        'title' => 'Como usar o parcelamento:',
        'step_1' => 'Preencha os dados do lançamento no separador "Dados Principais"',
        'step_2' => 'Indique o Subtotal ou adicione itens',
        'step_3' => 'Configure o número de prestações e a data da primeira prestação',
        'step_4' => 'Defina o intervalo (ex: 1 mês, 15 dias, 2 semanas)',
        'step_5' => 'Clique em "Gerar Pré-visualização" para visualizar as prestações',
        'step_6' => 'Guarde o lançamento - todas as prestações serão criadas automaticamente',
        'tip' => 'O valor será dividido igualmente entre as prestações. Diferenças de cêntimos serão ajustadas na última prestação.',
    ],

    // Paginação
    'pagination' => [
        'rows_per_page' => 'Registos por página:',
        'showing' => 'A mostrar :start-:end de :total registos',
    ],

    // Hints (instruções de campos)
    'hints' => [
        'valor_subtotal' => 'Se houver itens, será calculado automaticamente pela soma dos valores. Caso contrário, indique manualmente. Após guardar, não pode ser alterado.',
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
        'installments' => 'prestações',
    ],
];
