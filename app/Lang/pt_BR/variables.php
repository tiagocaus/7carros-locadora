<?php

/**
 * Nomes das variáveis de template - Português (Brasil)
 *
 * Contém os nomes amigáveis das variáveis disponíveis para uso em templates.
 * Organizado por entidade para facilitar a exibição no editor de templates.
 */

return [
    // Nomes das entidades
    'entities' => [
        'cliente' => 'Cliente',
        'empresa' => 'Empresa',
        'contrato' => 'Contrato',
        'locacao' => 'Locação',
        'veiculo' => 'Veículo',
        'fatura' => 'Fatura',
        'fornecedor' => 'Fornecedor',
        'multa' => 'Multa',
        'funcionario' => 'Funcionário',
        'outros' => 'Outros',
    ],

    // Variáveis do Cliente
    'cliente' => [
        'nome' => 'Nome do Cliente',
        'cpf_cnpj' => 'Documento (CPF/CNPJ/NIF/SSN)',
        'rg' => 'RG',
        'rg_ie' => 'RG/Inscrição Estadual',
        'email' => 'E-mail',
        'telefone' => 'Telefone',
        'celular' => 'Celular',
        'endereco_completo' => 'Endereço Completo',
        'endereco' => 'Endereço',
        'numero' => 'Número',
        'complemento' => 'Complemento',
        'bairro' => 'Bairro',
        'cidade' => 'Cidade',
        'estado' => 'Estado',
        'cep' => 'CEP',
        'pais' => 'País',
        'cnh_numero' => 'Número da CNH',
        'cnh_categoria' => 'Categoria da CNH',
        'cnh_validade' => 'Validade da CNH',
        'data_nascimento' => 'Data de Nascimento',
        'sexo' => 'Sexo',
        'estado_civil' => 'Estado Civil',
        'profissao' => 'Profissão',
    ],

    // Variáveis da Empresa
    'empresa' => [
        'razao_social' => 'Razão Social',
        'nome_fantasia' => 'Nome Fantasia',
        'cnpj' => 'CNPJ',
        'inscricao_estadual' => 'Inscrição Estadual',
        'inscricao_municipal' => 'Inscrição Municipal',
        'ie' => 'Inscrição Estadual (IE)',
        'im' => 'Inscrição Municipal (IM)',
        'email' => 'E-mail',
        'telefone' => 'Telefone',
        'celular' => 'Celular',
        'whatsapp' => 'WhatsApp',
        'endereco_completo' => 'Endereço Completo',
        'endereco' => 'Endereço',
        'numero' => 'Número',
        'complemento' => 'Complemento',
        'bairro' => 'Bairro',
        'cidade' => 'Cidade',
        'estado' => 'Estado',
        'cep' => 'CEP',
        'pais' => 'País',
        'site' => 'Website',
        'horario_funcionamento' => 'Horário de Funcionamento',
    ],

    // Variáveis do Contrato
    'contrato' => [
        // Dados básicos existentes
        'numero' => 'Número do Contrato',
        'data_inicio' => 'Data de Início',
        'data_fim' => 'Data de Término',
        'data_assinatura' => 'Data de Assinatura',
        'valor_total' => 'Valor Total',
        'valor_diaria' => 'Valor da Diária',
        'valor_semanal' => 'Valor Semanal',
        'valor_mensal' => 'Valor Mensal',
        'valor_caução' => 'Valor da Caução',
        'valor_franquia' => 'Valor da Franquia',
        'quantidade_dias' => 'Quantidade de Dias',
        'quilometragem_limite' => 'Quilometragem Limite',
        'quilometragem_extra' => 'Valor KM Extra',
        'status' => 'Status',
        'local_assinatura' => 'Local de Assinatura',

        // Novas variáveis - Dados Básicos
        'hora_inicio' => 'Hora de Início',
        'hora_fim' => 'Hora de Término',
        'filial_retirada' => 'Filial de Retirada',
        'filial_endereco' => 'Endereço da Filial',
        'observacoes' => 'Observações',
        'desconto' => 'Valor do Desconto',
        'valor_taxas' => 'Total de Taxas',
        'forma_pagamento' => 'Forma de Pagamento',
        'primeiro_pagamento' => 'Valor do Primeiro Pagamento',
        'contagem' => 'Tipo de Contagem',
        'autorenovacao' => 'Autorenovação',
        'data_renovacao' => 'Data de Renovação',

        // Veículos do Contrato
        'veiculos' => 'Lista de Veículos',
        'veiculos_tabela' => 'Tabela de Veículos',

        // Taxas e Serviços
        'taxas' => 'Lista de Taxas e Serviços',
        'taxas_tabela' => 'Tabela de Taxas e Serviços',

        // Financeiro - Parcelas
        'parcelas' => 'Lista de Parcelas',
        'parcelas_tabela' => 'Tabela de Parcelas',
        'total_parcelas' => 'Total de Parcelas',
        'parcelas_pagas' => 'Parcelas Pagas',
        'parcelas_pendentes' => 'Parcelas Pendentes',
        'valor_pago' => 'Valor Pago',
        'valor_pendente' => 'Valor Pendente',
        'valor_atrasado' => 'Valor em Atraso',

        // Condutores Adicionais
        'condutores' => 'Condutores Adicionais',

        // Fiadores
        'fiadores' => 'Lista de Fiadores',
        'fiadores_assinaturas' => 'Assinaturas dos Fiadores',

        // Avalistas
        'avalistas' => 'Lista de Avalistas',
        'avalistas_assinaturas' => 'Assinaturas dos Avalistas',

        // Testemunhas
        'testemunhas' => 'Lista de Testemunhas',
        'testemunhas_assinaturas' => 'Assinaturas das Testemunhas',

        // Assinaturas em Colunas (lado a lado)
        'fiadores_assinaturas_colunas' => 'Assinaturas Fiadores (Colunas)',
        'avalistas_assinaturas_colunas' => 'Assinaturas Avalistas (Colunas)',
        'testemunhas_assinaturas_colunas' => 'Assinaturas Testemunhas (Colunas)',

        // Assinatura do Cliente
        'assinatura_cliente' => 'Assinatura do Cliente',
    ],

    // Variáveis da Locação
    'locacao' => [
        'numero' => 'Número da Locação',
        'data_retirada' => 'Data de Retirada',
        'hora_retirada' => 'Hora de Retirada',
        'data_devolucao' => 'Data de Devolução',
        'hora_devolucao' => 'Hora de Devolução',
        'data_devolucao_real' => 'Data de Devolução Real',
        'local_retirada' => 'Local de Retirada',
        'local_devolucao' => 'Local de Devolução',
        'valor_total' => 'Valor Total',
        'valor_diaria' => 'Valor da Diária',
        'quantidade_dias' => 'Quantidade de Dias',
        'quilometragem_inicial' => 'Quilometragem Inicial',
        'quilometragem_final' => 'Quilometragem Final',
        'quilometragem_percorrida' => 'Quilometragem Percorrida',
        'nivel_combustivel_retirada' => 'Nível Combustível (Retirada)',
        'nivel_combustivel_devolucao' => 'Nível Combustível (Devolução)',
        'status' => 'Status',
        'observacoes' => 'Observações',
        'grupo' => 'Grupo do Veículo',
        'grupo_descricao' => 'Descrição do Grupo',
        'tanque_saida' => 'Tanque na Saída',
        'tanque_chegada' => 'Tanque na Chegada',
        'km_saida' => 'KM na Saída',
        'km_chegada' => 'KM na Chegada',
        'total_fatura' => 'Total da Fatura',
        'bloqueio_valor' => 'Valor do Bloqueio',
        'deposito_valor' => 'Valor do Depósito',
        'cobertura_terceiros' => 'Cobertura de Terceiros',
        'fatura_paga' => 'Fatura Paga',
        'forma_pagamento' => 'Forma de Pagamento',
        'plano' => 'Plano',
        'info_plano' => 'Informações do Plano',
        'condutores_adicionais' => 'Condutores Adicionais',
        'fiadores' => 'Fiadores',
    ],

    // Variáveis do Veículo
    'veiculo' => [
        'placa' => 'Placa',
        'placa_mercosul' => 'Placa Mercosul',
        'modelo' => 'Modelo',
        'marca' => 'Marca',
        'ano' => 'Ano',
        'ano_modelo' => 'Ano/Modelo',
        'cor' => 'Cor',
        'renavam' => 'Renavam',
        'chassi' => 'Chassi',
        'combustivel' => 'Combustível',
        'categoria' => 'Categoria',
        'cambio' => 'Câmbio',
        'portas' => 'Número de Portas',
        'lugares' => 'Número de Lugares',
        'ar_condicionado' => 'Ar Condicionado',
        'direcao' => 'Tipo de Direção',
        'quilometragem' => 'Quilometragem Atual',
        'descricao_completa' => 'Descrição Completa',
        'valor_diaria' => 'Valor da Diária',
        'valor_fipe' => 'Valor FIPE',
        'valor_compra' => 'Valor de Compra',
        'valor_venda' => 'Valor de Venda',
    ],

    // Variáveis da Fatura
    'fatura' => [
        'numero' => 'Número da Fatura',
        'valor' => 'Valor',
        'valor_original' => 'Valor Original',
        'valor_desconto' => 'Valor do Desconto',
        'valor_acrescimo' => 'Valor do Acréscimo',
        'valor_juros' => 'Valor dos Juros',
        'valor_multa' => 'Valor da Multa',
        'valor_pago' => 'Valor Pago',
        'data_emissao' => 'Data de Emissão',
        'data_vencimento' => 'Data de Vencimento',
        'data_pagamento' => 'Data de Pagamento',
        'status' => 'Status',
        'forma_pagamento' => 'Forma de Pagamento',
        'link_boleto' => 'Link do Boleto',
        'codigo_barras' => 'Código de Barras',
        'linha_digitavel' => 'Linha Digitável',
        'codigo_pix' => 'Código PIX',
        'qrcode_pix' => 'QR Code PIX',
        'parcela' => 'Parcela',
        'total_parcelas' => 'Total de Parcelas',
        'observacoes' => 'Observações',
    ],

    // Variáveis do Fornecedor
    'fornecedor' => [
        'nome' => 'Nome do Fornecedor',
        'nome_fantasia' => 'Nome Fantasia',
        'cpf_cnpj' => 'CPF/CNPJ',
        'rg_ie' => 'RG/Inscrição Estadual',
        'endereco' => 'Endereço',
        'numero' => 'Número',
        'bairro' => 'Bairro',
        'cidade' => 'Cidade',
        'estado' => 'Estado',
        'pais' => 'País',
        'email' => 'E-mail',
        'observacoes' => 'Observações',
    ],

    // Variáveis da Multa
    'multa' => [
        'local' => 'Local da Infração',
        'cidade' => 'Cidade',
        'estado' => 'Estado',
        'data_hora' => 'Data e Hora',
        'data_vencimento' => 'Data de Vencimento',
        'valor' => 'Valor',
        'pago' => 'Status de Pagamento',
        'descricao' => 'Descrição',
        'orgao_autuador' => 'Órgão Autuador',
        'numero_infracao' => 'Número da Infração',
    ],

    // Variáveis do Funcionário
    'funcionario' => [
        'nome' => 'Nome do Funcionário',
        'email' => 'E-mail',
        'telefone' => 'Telefone',
        'cargo' => 'Cargo',
    ],

    // Variáveis Gerais/Outros
    'outros' => [
        'data_atual' => 'Data Atual',
        'hora_atual' => 'Hora Atual',
        'data_hora_atual' => 'Data e Hora Atual',
        'link_portal_cliente' => 'Link do Portal do Cliente',
        'link_assinatura' => 'Link de Assinatura',
        'link_contrato' => 'Link do Contrato',
        'link_fatura' => 'Link da Fatura',
        'link_checklist' => 'Link do Checklist',
        'codigo_verificacao' => 'Código de Verificação',
        'token_acesso' => 'Token de Acesso',
        'assinatura_empresa' => 'Assinatura da Empresa',
        'logo_empresa' => 'Logo da Empresa',
        'contagem' => 'Contagem',
    ],

    // Descrições das variáveis (para tooltips no editor)
    'descriptions' => [
        'cliente.endereco_completo' => 'Endereço formatado com rua, número, bairro, cidade, estado e CEP',
        'cliente.rg_ie' => 'RG para pessoa física ou Inscrição Estadual para pessoa jurídica',
        'veiculo.descricao_completa' => 'Descrição completa: Marca Modelo Ano/Modelo Cor',
        'contrato.quantidade_dias' => 'Calculado automaticamente entre data início e fim',
        'locacao.condutores_adicionais' => 'Lista formatada de condutores adicionais da locação',
        'locacao.fiadores' => 'Lista formatada de fiadores da locação',
        'locacao.grupo' => 'Nome do grupo/categoria do veículo',
        'locacao.info_plano' => 'Detalhes do plano de locação selecionado',
        'outros.data_atual' => 'Data do momento do envio da mensagem',
        'outros.hora_atual' => 'Hora do momento do envio da mensagem',
        'outros.contagem' => 'Contador para uso em listas e iterações',
        'fatura.link_boleto' => 'URL para visualização/download do boleto',
        'fatura.codigo_pix' => 'Código PIX copia e cola para pagamento',
        'multa.orgao_autuador' => 'Nome do órgão responsável pela autuação',
        'funcionario.cargo' => 'Cargo ou função do funcionário na empresa',

        // Descrições das novas variáveis de contrato
        'contrato.filial_endereco' => 'Endereço completo da filial de retirada',
        'contrato.veiculos' => 'Lista formatada com detalhes de todos os veículos do contrato',
        'contrato.veiculos_tabela' => 'Tabela HTML com veículos (ideal para impressão)',
        'contrato.taxas' => 'Lista formatada de taxas e serviços adicionais',
        'contrato.taxas_tabela' => 'Tabela HTML com taxas e serviços (ideal para impressão)',
        'contrato.parcelas' => 'Lista formatada de parcelas do financeiro',
        'contrato.parcelas_tabela' => 'Tabela HTML com parcelas (ideal para impressão)',
        'contrato.condutores' => 'Lista formatada de condutores adicionais com CPF e CNH',
        'contrato.fiadores' => 'Lista formatada de fiadores com CPF/CNPJ',
        'contrato.fiadores_assinaturas' => 'Espaços para assinaturas dos fiadores (ideal para impressão)',
        'contrato.avalistas' => 'Lista formatada de avalistas com CPF/CNPJ',
        'contrato.avalistas_assinaturas' => 'Espaços para assinaturas dos avalistas (ideal para impressão)',
        'contrato.testemunhas' => 'Lista formatada de testemunhas com CPF/CNPJ',
        'contrato.testemunhas_assinaturas' => 'Espaços para assinaturas das testemunhas (ideal para impressão)',
        'contrato.fiadores_assinaturas_colunas' => 'Assinaturas dos fiadores em colunas (2 por linha, lado a lado)',
        'contrato.avalistas_assinaturas_colunas' => 'Assinaturas dos avalistas em colunas (2 por linha, lado a lado)',
        'contrato.testemunhas_assinaturas_colunas' => 'Assinaturas das testemunhas em colunas (2 por linha, lado a lado)',
        'contrato.assinatura_cliente' => 'Espaço para assinatura do cliente/locatário',
    ],
];
