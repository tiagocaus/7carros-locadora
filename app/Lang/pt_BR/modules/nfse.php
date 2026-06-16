<?php

/**
 * Traduções do módulo NFS-e - Português (Brasil)
 */

return [
    // Titulos
    'title' => 'NFS-e - Notas Fiscais de Serviço',
    'title_singular' => 'NFS-e',
    'emit_title' => 'Emitir NFS-e',
    'view_title' => 'Visualizar NFS-e',
    'cancel_title' => 'Cancelar NFS-e',
    'config_title' => 'Configurações NFS-e',

    // Status
    'status' => [
        'pendente' => 'Pendente',
        'processando' => 'Processando',
        'autorizada' => 'Autorizada',
        'rejeitada' => 'Rejeitada',
        'cancelada' => 'Cancelada',
    ],

    // Campos
    'fields' => [
        'numero' => 'Número',
        'serie' => 'Série',
        'data_emissao' => 'Data de Emissão',
        'data_competencia' => 'Competência',
        'prestador' => 'Prestador',
        'tomador' => 'Tomador',
        'tomador_nome' => 'Nome / Razão Social',
        'tomador_cpf_cnpj' => 'CPF/CNPJ',
        'tomador_email' => 'E-mail do Tomador',
        'valor_servicos' => 'Valor dos Serviços',
        'valor_deducoes' => 'Deduções',
        'base_calculo' => 'Base de Cálculo',
        'aliquota_iss' => 'Alíquota ISS',
        'valor_iss' => 'Valor ISS',
        'valor_ibs' => 'Valor IBS',
        'valor_cbs' => 'Valor CBS',
        'iss_retido' => 'ISS Retido',
        'chave_acesso' => 'Chave de Acesso',
        'codigo_verificacao' => 'Código de Verificação',
        'tipo_emissao' => 'Tipo de Emissão',
        'ambiente' => 'Ambiente',
        'motivo_cancelamento' => 'Motivo do Cancelamento',
        'descricao_servico' => 'Descrição do Serviço',
        'codigo_servico' => 'Código NBS do Serviço',
        'filial' => 'Matriz/Filial',
    ],

    // Ambiente
    'ambiente' => [
        'producao' => 'Produção',
        'homologacao' => 'Homologação',
    ],

    // Tipo emissao
    'tipo_emissao' => [
        'nacional' => 'Nacional (SEFIN)',
        'betha' => 'Betha Cloud',
    ],

    // Secoes
    'sections' => [
        'identification' => 'Identificação da NFS-e',
        'prestador' => 'Prestador de Serviços',
        'tomador' => 'Tomador de Serviços',
        'servico' => 'Serviço',
        'valores' => 'Valores e Tributos',
        'cancelamento' => 'Cancelamento',
        'eventos' => 'Histórico de Eventos',
        'xml' => 'XML',
    ],

    // Filtros
    'filters' => [
        'search_placeholder' => 'Buscar por número, tomador...',
        'all_branches' => 'Todas as filiais',
        'all_status' => 'Todos os status',
        'date_from' => 'Data início',
        'date_to' => 'Data fim',
        'clear_title' => 'Limpar filtros',
    ],

    // Tabela
    'table' => [
        'numero' => 'Nº',
        'tomador' => 'Tomador',
        'valor' => 'Valor',
        'data' => 'Data',
        'status' => 'Status',
        'actions' => 'Ações',
        'tipo' => 'Tipo',
    ],

    // Botoes
    'buttons' => [
        'emit' => 'Emitir NFS-e',
        'cancel_nfse' => 'Cancelar NFS-e',
        'consult' => 'Consultar Status',
        'resend' => 'Reenviar',
        'send_email' => 'Enviar por e-mail',
        'download_pdf' => 'Download PDF',
        'download_xml' => 'Download XML',
        'config' => 'Configurações',
        'test_connection' => 'Testar Conexão',
        'upload_cert' => 'Enviar Certificado',
        'remove_cert' => 'Remover Certificado',
    ],

    // Mensagens
    'messages' => [
        'no_records' => 'Nenhuma NFS-e encontrada.',
        'load_error' => 'Erro ao carregar NFS-e.',
        'emit_success' => 'NFS-e emitida com sucesso!',
        'emit_error' => 'Erro ao emitir NFS-e.',
        'cancel_success' => 'NFS-e cancelada com sucesso!',
        'cancel_error' => 'Erro ao cancelar NFS-e.',
        'cancel_confirm' => 'Tem certeza que deseja cancelar esta NFS-e? Esta ação não pode ser desfeita.',
        'cancel_motivo_min' => 'O motivo deve ter no mínimo 15 caracteres.',
        'resend_success' => 'NFS-e reenviada com sucesso!',
        'resend_error' => 'Erro ao reenviar NFS-e.',
        'email_success' => 'E-mail enviado com sucesso!',
        'email_error' => 'Erro ao enviar e-mail.',
        'consult_success' => 'Consulta realizada com sucesso.',
        'config_saved' => 'Configurações salvas com sucesso!',
        'config_error' => 'Erro ao salvar configurações.',
        'cert_uploaded' => 'Certificado enviado com sucesso!',
        'cert_removed' => 'Certificado removido.',
        'cert_error' => 'Erro ao processar certificado.',
        'cert_required' => 'Configure o certificado digital antes de emitir NFS-e.',
        'connection_ok' => 'Conexão estabelecida com sucesso!',
        'connection_error' => 'Falha na conexão.',
        'financeiro_required' => 'Selecione um lançamento financeiro.',
        'no_events' => 'Nenhum evento registrado.',
        'homologacao_aviso' => 'Ambiente de HOMOLOGAÇÃO - Notas sem valor fiscal.',
        'select_branch_config' => 'Selecione uma filial no filtro para configurar.',
    ],

    // Estatisticas
    'stats' => [
        'total' => 'Total',
        'autorizadas' => 'Autorizadas',
        'pendentes' => 'Pendentes',
        'rejeitadas' => 'Rejeitadas',
        'canceladas' => 'Canceladas',
    ],

    // Pagination
    'pagination' => [
        'rows_per_page' => 'Linhas por página:',
        'showing' => 'Exibindo {from} a {to} de {total}',
    ],

    // Configuracoes
    'config' => [
        'section_cert' => 'Certificado Digital',
        'section_general' => 'Configurações Gerais',
        'section_fiscal' => 'Dados Fiscais',
        'ativo' => 'Emissão de NFS-e ativa',
        'ambiente' => 'Ambiente',
        'tipo_emissao' => 'Tipo de Emissão',
        'serie' => 'Série',
        'emissao_auto' => 'Emissão automática',
        'emissao_auto_hint' => 'Emitir NFS-e automaticamente quando pagamento for confirmado',
        'enviar_email' => 'Enviar e-mail ao tomador',
        'enviar_email_hint' => 'Enviar PDF da NFS-e por e-mail automaticamente',
        'codigo_municipio' => 'Código IBGE do Município',
        'codigo_servico' => 'Código NBS do Serviço',
        'descricao_servico' => 'Descrição padrão do Serviço',
        'regime_tributario' => 'Regime Tributário',
        'regime_simples' => 'Simples Nacional',
        'regime_mei' => 'MEI',
        'regime_presumido' => 'Lucro Presumido',
        'regime_real' => 'Lucro Real',
        'reg_apuracao_sn' => 'Regime de apuração SN',
        'reg_apuracao_sn_1' => 'Competência',
        'reg_apuracao_sn_2' => 'Caixa',
        'trib_issqn' => 'Tributação ISSQN',
        'trib_normal' => 'Tributável',
        'trib_imunidade' => 'Imunidade',
        'trib_exportacao' => 'Exportação de serviço',
        'trib_nao_incide' => 'Não incidência',
        'aliquota_iss' => 'Alíquota ISS (%)',
        'exigibilidade_iss' => 'Exigibilidade ISS',
        'incentivo_fiscal' => 'Incentivo Fiscal',
        'enviar_im' => 'Enviar IM no DPS',
        'enviar_im_hint' => 'Use somente quando o cadastro do CNPJ no município exigir Inscrição Municipal no DPS.',
        'numero_atual' => 'Número Atual NFS-e',
        'cert_arquivo' => 'Arquivo .pfx/.p12',
        'cert_senha' => 'Senha do Certificado',
        'cert_validade' => 'Válido até',
        'cert_dias_expirar' => 'dias para expirar',
        'cert_expirado' => 'Certificado vencido!',
        'cert_nao_configurado' => 'Nenhum certificado configurado.',
    ],
];
