<?php

/**
 * Traduções do módulo Grupos - Português (Portugal)
 */

return [
    'title' => 'Grupos de Veículos',
    'title_singular' => 'Grupo',
    'new_title' => 'Novo Grupo',
    'edit_title' => 'Editar Grupo',

    // Separadores
    'tabs' => [
        'group_data' => 'Dados do Grupo',
        'prices_by_days' => 'Preços por Dias',
    ],

    // Secções
    'sections' => [
        'basic_data' => 'Dados Básicos',
        'rental_plans' => 'Planos de Aluguer',
        'insurance' => 'Seguros',
        'tolerance_extras' => 'Tolerância e Extras',
        'investor_commission' => 'Comissão Investidor',
        'progressive_prices' => 'Preços Progressivos por Dias',
    ],

    // Campos
    'fields' => [
        'name' => 'Nome',
        'description' => 'Descrição',
        'visible_on_site' => 'Visível no site',
        'km_paid_value' => 'Valor Km Pago',
        'km_controlled_value' => 'Valor Km Controlado',
        'km_free_value' => 'Valor Km Livre',
        'km_excess_value' => 'Valor Km Excedente',
        'km_franchise' => 'Franquia de Km',
        'car_insurance_value' => 'Valor Seguro Automóvel (por dia)',
        'third_party_insurance_value' => 'Valor Seguro de Terceiros (por dia)',
        'car_coverage' => 'Cobertura Automóvel',
        'third_party_coverage' => 'Cobertura de Terceiros',
        'tolerance_minutes' => 'Minutos de Tolerância',
        'tolerance_value' => 'Valor de Tolerância',
        'return_km_value' => 'Valor Km de Retorno',
        'additional_driver_value' => 'Valor Condutor Adicional',
        'commission_type' => 'Tipo de Comissão',
        'commission_value' => 'Valor',
    ],

    // Opções de comissão
    'commission_options' => [
        'none' => 'Nenhum (sem comissão)',
        'percentage_rental' => 'Percentagem para a Locadora',
        'fixed_rental_invoice' => 'Valor Fixo para a Locadora (por fatura)',
        'fixed_rental_monthly' => 'Valor Fixo Mensal para a Locadora',
        'fixed_investor_monthly' => 'Valor Fixo Mensal para o Investidor',
    ],

    // Etiquetas dinâmicas de comissão
    'commission_labels' => [
        'rental_percentage' => 'Percentagem da Locadora',
        'fixed_per_invoice' => 'Valor Fixo por Fatura',
        'monthly_rental' => 'Valor Mensal (Locadora)',
        'monthly_investor' => 'Valor Mensal (Investidor)',
    ],

    // Sugestões de comissão
    'commission_hints' => [
        'percentage_rental' => 'Ex.: 20% significa que a locadora fica com 20% do valor e o investidor recebe 80%.',
        'fixed_rental_invoice' => 'Ex.: 50 € por fatura significa que a locadora fica com 50 € fixos de cada pagamento.',
        'fixed_rental_monthly' => 'Ex.: 300 €/mês por veículo. A locadora recebe este valor fixo mensal por cada veículo do investidor.',
        'fixed_investor_monthly' => 'Ex.: 2.000 €/mês por veículo. O investidor recebe este valor fixo mensal, independentemente dos alugueres.',
    ],

    // Descrições
    'descriptions' => [
        'investor_commission' => 'Configure como a comissão será calculada para veículos de investidores neste grupo.',
        'progressive_prices' => 'Configure valores diferenciados com base na quantidade de dias do aluguer. Se nenhuma faixa for configurada, será utilizado o valor base.',
    ],

    // Sub-separadores de preço
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
        'no_ranges' => 'Nenhuma faixa configurada. Será utilizado o valor base.',
        'infinity' => '(infinito)',
    ],

    // Imagem
    'image' => [
        'alt' => 'Imagem do Grupo',
        'change' => 'Alterar Imagem',
    ],

    // Marcadores
    'placeholders' => [
        'search' => 'Pesquisar grupo...',
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
        'server_error' => 'Erro ao ligar ao servidor',
        'delete_error' => 'Erro ao eliminar grupo',
        'this_record' => 'este grupo',
        'load_group_error' => 'Erro ao carregar grupo',
        'invalid_image_format' => 'Selecione uma imagem válida (JPG, PNG ou WebP)',
        'image_too_large' => 'A imagem deve ter no máximo 5 MB',
        'name_required' => 'O nome é obrigatório',
        'saving' => 'A guardar...',
        'save_error' => 'Erro ao guardar',
        'save_server_error' => 'Erro ao guardar grupo',
        'created' => 'Grupo criado com sucesso!',
        'updated' => 'Grupo atualizado com sucesso!',
    ],

    // Paginação
    'pagination' => [
        'rows_per_page' => 'Registos por página:',
        'showing' => 'A mostrar :start-:end de :total registos',
    ],

    // Tipo de registo
    'record_type' => 'grupo',
];
