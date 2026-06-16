<?php

/**
 * Traduções do módulo Veículos - Português (Portugal)
 */

return [
    // Títulos
    'title' => 'Veículos',
    'title_singular' => 'Veículo',
    'new_title' => 'Novo Veículo',
    'edit_title' => 'Editar Veículo',

    // Campos do formulário
    'fields' => [
        'branch' => 'Sede/Filial',
        'supplier' => 'Fornecedor',
        'group' => 'Grupo',
        'plate' => 'Matrícula',
        'renavam' => 'Registo (Renavam)',
        'chassis' => 'Chassis',
        'odometer' => 'Odómetro (km)',
        'availability' => 'Disponibilidade',
        'brand' => 'Marca',
        'model' => 'Modelo',
        'year' => 'Ano',
        'color' => 'Cor',
        'transmission' => 'Transmissão',
        'engine' => 'Motor',
        'max_weight' => 'Peso Máx (kg)',
        'current_location' => 'Localização Atual',
        'fuel_type' => 'Tipo Combustível',
        'tank_liters' => 'Depósito (L)',
        'tank_fraction' => 'Fração Depósito',
        'fraction_value' => 'Valor por Fração',
        'battery_kwh' => 'Bateria (kWh)',
        'battery_charge' => 'Carga Bateria',
        'purchase_date' => 'Data Compra',
        'purchase_value' => 'Valor Compra',
        'for_sale' => 'Para Venda',
        'sale_date' => 'Data Venda',
        'sale_value' => 'Valor Venda',
        'charge_name' => 'Nome',
        'charge_description' => 'Descrição',
        'charge_value' => 'Valor',
        'charge_due_date' => 'Vencimento',
        'charge_recurrence' => 'Recorrência',
        'charge_days_advance' => 'Antecedência',
        'add_charge' => 'Adicionar Encargo',
        'no_charges' => 'Nenhum encargo registado',
        'recurrence_none' => 'Nenhuma',
        'recurrence_monthly' => 'Mensal',
        'recurrence_quarterly' => 'Trimestral',
        'recurrence_semiannual' => 'Semestral',
        'recurrence_annual' => 'Anual',
        'save_vehicle_first' => 'Guarde o veículo antes de adicionar encargos',
        'charge_name_required' => 'O nome do encargo é obrigatório',
        'description' => 'Descrição',
        'accessories' => 'Acessórios do Veículo',
        'photo' => 'Foto do Veículo',
        'change_photo' => 'Alterar Foto',
        'brand_model' => 'Marca/Modelo',
        'branch_short' => 'Filial',
    ],

    // Secções do formulário
    'sections' => [
        'basic_data' => 'Dados Básicos',
        'characteristics' => 'Características',
        'fuel' => 'Combustível',
        'purchase_sale' => 'Compra e Venda',
        'vehicle_charges' => 'Encargos do Veículo',
        'description' => 'Descrição',
        'accessories' => 'Acessórios',
        'select_plan' => 'Selecionar Plano',
    ],

    // Separadores
    'tabs' => [
        'vehicle_data' => 'Dados do Veículo',
        'maintenance_plan' => 'Plano de Manutenção',
        'maintenances' => 'Manutenções',
    ],

    // Separador Manutenções
    'maintenances' => [
        'no_records' => 'Nenhuma manutenção encontrada para este veículo.',
        'load_error' => 'Erro ao carregar manutenções',
        'table_os' => 'OS',
        'table_workshop' => 'Oficina',
        'table_send_date' => 'Data Envio',
        'table_return_date' => 'Data Retorno',
        'table_total' => 'Total',
        'table_status' => 'Estado',
        'status_created' => 'Criada',
        'status_open' => 'Aberta',
        'status_closed' => 'Fechada',
        'action_print' => 'Imprimir OS',
    ],

    // Disponibilidade
    'availability' => [
        'available' => 'Disponível',
        'rented' => 'Alugado',
        'reserved' => 'Reservado',
        'in_shop' => 'Na oficina',
        'sold' => 'Vendido',
        'for_sale' => 'À venda',
        'internal_use' => 'Uso interno',
        'stolen' => 'Roubado',
        'excluded' => 'Excluído',
        'maintenance' => 'Manutenção',
        'unavailable' => 'Indisponível',
    ],

    // Transmissão
    'transmission' => [
        'automatic' => 'Automática',
        'manual' => 'Manual',
    ],

    // Combustível
    'fuel' => [
        'gasoline_ethanol' => 'Gasolina/Etanol',
        'gasoline' => 'Gasolina',
        'ethanol' => 'Etanol',
        'diesel' => 'Gasóleo',
        'gas' => 'Gás',
        'electric' => 'Elétrico',
        'hybrid' => 'Híbrido',
    ],

    // Fração do depósito
    'tank_fraction' => [
        'full' => 'Cheio',
        'reserve' => 'Reserva',
    ],

    // Manutenção
    'maintenance' => [
        'plan' => 'Plano de Manutenção',
        'recalculate' => 'Recalcular com Odómetro Atual',
        'recalculate_hint' => 'Recalcula: odómetro + intervalo do plano',
        'engine_section' => 'Motor',
        'engine_hint' => 'Próximo km para cada item de manutenção do motor',
        'wheels_section' => 'Rodagem',
        'wheels_hint' => 'Próximo km para cada item de manutenção de rodagem',
        'accessories_section' => 'Acessórios',
        'accessories_hint' => 'Próximo km para cada item de manutenção de acessórios',
        // Itens motor
        'engine_oil' => 'Óleo Motor',
        'oil_filter' => 'Filtro de Óleo',
        'timing_belt' => 'Correia de Distribuição',
        'alternator_belt' => 'Correia Alternador',
        'ac_belt' => 'Correia Ar Condicionado',
        'water_pump_belt' => "Correia Bomba d'Água",
        'air_filter' => 'Filtro de Ar',
        'cabin_filter' => 'Filtro de Habitáculo',
        'fuel_filter' => 'Filtro de Combustível',
        'brake_fluid' => 'Fluido de Travão',
        'clutch_fluid' => 'Fluido de Embraiagem',
        'clutch_disc' => 'Disco de Embraiagem',
        'gearbox_fluid' => 'Fluido Caixa de Velocidades',
        'cooling_flush' => 'Limpeza Arrefecimento',
        'spark_plugs' => 'Velas',
        'battery' => 'Bateria',
        // Itens rodagem
        'tires' => 'Pneus',
        'alignment' => 'Alinhamento',
        'brake_pads' => 'Pastilhas de Travão',
        'brake_discs' => 'Discos de Travão',
        'tire_rotation' => 'Rodízio de Pneus',
        // Itens acessórios
        'wiper_blades' => 'Escovas Limpa-vidros',
    ],

    // Placeholders
    'placeholders' => [
        'search' => 'Pesquisar por matrícula, marca ou modelo...',
        'search_select' => 'Escreva para pesquisar...',
        'select' => 'Selecione...',
        'select_option' => 'Selecione',
        'select_plan' => 'Selecione um plano...',
        'plate' => 'ABC-1234',
        'year' => '2024/2025',
        'engine' => '1.0',
        'description' => 'Informações adicionais sobre o veículo...',
        'select_accessories' => 'Selecione os acessórios...',
        'same_as_branch' => 'Mesma da filial',
    ],

    // Mensagens
    'messages' => [
        'created' => 'Veículo criado com sucesso!',
        'updated' => 'Veículo atualizado com sucesso!',
        'deleted' => 'Veículo eliminado com sucesso!',
        'delete_confirm' => 'Deseja eliminar o veículo ":name"?',
        'delete_error' => 'Erro ao eliminar veículo',
        'delete_has_links_title' => 'Veículo com vínculos',
        'delete_has_links_confirm' => "Não é possível eliminar este veículo porque existem vínculos:\n\n:links\n\nDeseja desativá-lo?",
        'deactivate_button' => 'Desativar veículo',
        'deactivated' => 'Veículo desativado com sucesso!',
        'deactivate_error' => 'Erro ao desativar veículo',
        'load_error' => 'Erro ao carregar veículos: ',
        'load_data_error' => 'Erro ao carregar dados do veículo',
        'save_error' => 'Erro ao guardar veículo',
        'save_generic_error' => 'Erro ao guardar',
        'connection_error' => 'Erro ao ligar ao servidor',
        'no_vehicles' => 'Nenhum veículo encontrado',
        'no_plate' => 'Sem matrícula',
        'this_vehicle' => 'este veículo',
        'select_plan_first' => 'Selecione um plano de manutenção primeiro',
        'invalid_image' => 'Selecione uma imagem válida (JPG, PNG ou WebP)',
        'image_too_large' => 'A imagem deve ter no máximo 5MB',
        'accessories_load_error' => 'Erro ao carregar acessórios',
        'accessories_load_error_short' => 'Erro ao carregar',
        'no_accessories' => 'Nenhum acessório registado',
        'no_accessories_short' => 'Nenhum acessório',
        'plan_load_error' => 'Erro ao carregar planos de manutenção:',
        'plan_fetch_error' => 'Erro ao procurar plano:',
        'recalculate_title' => 'Recalcular Plano',
        'recalculate_confirm' => 'Deseja recalcular os valores do plano com base no odómetro atual?',
        'recalculate_btn' => 'Recalcular',
        'for_sale_tooltip' => 'Ao ativar para venda, o veículo vai aparecer no site como disponível para venda e não vai ficar mais disponível para aluguer ou contrato.',
        'loading_accessories' => 'A carregar acessórios...',
        'plan_limit_reached' => 'Limite de veículos atingido. O seu plano (:plano) permite no máximo :limite veículos ativos. Para reativar este veículo, remova outro ou faça upgrade do plano.',
    ],

    // Paginação
    'pagination' => [
        'rows_per_page' => 'Registos por página:',
        'showing' => 'A mostrar :start-:end de :total registos',
        'showing_empty' => 'A mostrar 0-0 de 0 registos',
    ],
];
