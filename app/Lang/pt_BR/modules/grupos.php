<?php

/**
 * Traduções do módulo Grupos - Português (Brasil)
 */

return [
    'title' => 'Grupos de Veículos',
    'title_singular' => 'Grupo',
    'new_title' => 'Novo Grupo',
    'edit_title' => 'Editar Grupo',

    // Abas
    'tabs' => [
        'group_data' => 'Dados do Grupo',
        'values_by_branch' => 'Valores por filial',
        'prices_by_days' => 'Preços por dias',
    ],

    // Botões
    'buttons' => [
        'save_branch_values' => 'Salvar valores desta filial',
        'save_branch_prices' => 'Salvar preços desta filial',
    ],

    // Seções
    'sections' => [
        'basic_data' => 'Dados Basicos',
        'rental_plans' => 'Planos de Locação',
        'insurance' => 'Seguros',
        'tolerance_extras' => 'Tolerância e Extras',
        'investor_commission' => 'Comissão Investidor',
        'progressive_prices' => 'Preços Progressivos por Dias',
    ],

    // Campos
    'fields' => [
        'name' => 'Nome',
        'description' => 'Descrição',
        'visible_on_site' => 'Visivel no site',
        'km_paid_value' => 'Valor Km Pago',
        'km_controlled_value' => 'Valor Km Controlado',
        'km_free_value' => 'Valor Km Livre',
        'km_excess_value' => 'Valor Km Excedente',
        'km_franchise' => 'Km Franquia',
        'car_insurance_value' => 'Valor Seguro Carro (por dia)',
        'third_party_insurance_value' => 'Valor Seguro Terceiros (por dia)',
        'car_coverage' => 'Cobertura Carro',
        'third_party_coverage' => 'Cobertura Terceiros',
        'tolerance_minutes' => 'Minutos Tolerância',
        'tolerance_value' => 'Valor Tolerância',
        'return_km_value' => 'Valor Km Retorno',
        'additional_driver_value' => 'Valor Condutor Adicional',
        'commission_type' => 'Tipo de Comissão',
        'commission_value' => 'Valor',
    ],

    // Opções de comissão
    'commission_options' => [
        'none' => 'Nenhum (sem comissão)',
        'percentage_rental' => 'Percentual para Locadora',
        'fixed_rental_invoice' => 'Valor Fixo para Locadora (por fatura)',
        'fixed_rental_monthly' => 'Valor Fixo Mensal para Locadora',
        'fixed_investor_monthly' => 'Valor Fixo Mensal para Investidor',
    ],

    // Labels dinâmicos de comissão
    'commission_labels' => [
        'rental_percentage' => 'Percentual da Locadora',
        'fixed_per_invoice' => 'Valor Fixo por Fatura',
        'monthly_rental' => 'Valor Mensal (Locadora)',
        'monthly_investor' => 'Valor Mensal (Investidor)',
    ],

    // Hints de comissão
    'commission_hints' => [
        'percentage_rental' => 'Ex: 20% significa que a locadora fica com 20% do valor e o investidor recebe 80%.',
        'fixed_rental_invoice' => 'Ex: R$ 50 por fatura significa que a locadora fica com R$ 50 fixos de cada pagamento.',
        'fixed_rental_monthly' => 'Ex: R$ 300/mes por veículo. A locadora recebe esse valor fixo mensal por cada veículo do investidor.',
        'fixed_investor_monthly' => 'Ex: R$ 2.000/mes por veículo. O investidor recebe esse valor fixo mensal, independente de locações.',
    ],

    // Descrições
    'descriptions' => [
        'investor_commission' => 'Configure como a comissão será calculada para veículos de investidores neste grupo.',
        'progressive_prices' => 'Configure valores diferenciados baseados na quantidade de dias da locação. Se nenhuma faixa for configurada, será usado o valor base.',
    ],

    // Sub-abas de preço
    'price_tabs' => [
        'km_paid' => 'Km Pago',
        'km_controlled' => 'Km Controlado',
        'km_free' => 'Km Livre',
    ],

    // Faixas de preço
    'ranges' => [
        'from' => 'De',
        'to' => 'a',
        'days_equals' => 'dias =',
        'add_range' => 'Adicionar Faixa',
        'no_ranges' => 'Nenhuma faixa configurada. Será usado o valor base.',
        'infinity' => '(infinito)',
    ],

    // Imagem
    'image' => [
        'alt' => 'Imagem do Grupo',
        'change' => 'Alterar Imagem',
    ],

    // Placeholders
    'placeholders' => [
        'search' => 'Buscar grupo...',
    ],

    // Tabela
    'table' => [
        'image' => 'Imagem',
        'name' => 'Nome',
        'description' => 'Descrição',
        'site' => 'Site',
        'actions' => 'Ações',
    ],

    // Mensagens
    'messages' => [
        'no_records' => 'Nenhum grupo encontrado',
        'no_name' => 'Sem nome',
        'load_error' => 'Erro ao carregar grupos',
        'server_error' => 'Erro ao conectar com o servidor',
        'delete_error' => 'Erro ao excluir grupo',
        'this_record' => 'este grupo',
        'load_group_error' => 'Erro ao carregar grupo',
        'invalid_image_format' => 'Selecione uma imagem valida (JPG, PNG ou WebP)',
        'image_too_large' => 'A imagem deve ter no máximo 5MB',
        'name_required' => 'Nome e obrigatório',
        'saving' => 'Salvando...',
        'save_error' => 'Erro ao salvar',
        'save_server_error' => 'Erro ao salvar grupo',
        'created' => 'Grupo criado com sucesso!',
        'updated' => 'Grupo atualizado com sucesso!',
    ],

    // Paginação
    'pagination' => [
        'rows_per_page' => 'Registros por página:',
        'showing' => 'Mostrando :start-:end de :total registros',
    ],

    // Tipo de registro
    'record_type' => 'grupo',
];
