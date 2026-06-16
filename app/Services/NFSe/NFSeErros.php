<?php

namespace App\Services\NFSe;

/**
 * Mapeamento de erros NFS-e
 *
 * Classe estatica com 70+ codigos de erro mapeados para mensagens amigaveis,
 * instrucoes de correcao e categorias.
 */
class NFSeErros
{
    private static array $erros = [
        // ==========================================
        // CERTIFICADO
        // ==========================================
        'CERT_EXPIRADO' => [
            'mensagem' => 'Seu certificado digital está vencido.',
            'instrucao' => 'Renove o certificado digital A1 e atualize nas configurações.',
            'categoria' => 'certificado',
        ],
        'CERT_INVALIDO' => [
            'mensagem' => 'Certificado digital inválido.',
            'instrucao' => 'Verifique se o arquivo .pfx está correto e não está corrompido.',
            'categoria' => 'certificado',
        ],
        'CERT_SENHA' => [
            'mensagem' => 'Senha do certificado incorreta.',
            'instrucao' => 'Verifique a senha do certificado nas configurações.',
            'categoria' => 'certificado',
        ],
        'CERT_NAO_ENCONTRADO' => [
            'mensagem' => 'Certificado digital não configurado.',
            'instrucao' => 'Acesse Configurações > NFS-e para enviar o certificado.',
            'categoria' => 'certificado',
        ],
        'CERT_LEITURA' => [
            'mensagem' => 'Erro ao ler o certificado digital.',
            'instrucao' => 'O arquivo do certificado pode estar corrompido. Faça o upload novamente.',
            'categoria' => 'certificado',
        ],

        // ==========================================
        // CONEXAO
        // ==========================================
        'CONN_TIMEOUT' => [
            'mensagem' => 'Servidor da SEFIN não respondeu a tempo.',
            'instrucao' => 'Tente novamente em alguns minutos.',
            'categoria' => 'conexao',
            'recuperavel' => true,
        ],
        'CONN_SSL' => [
            'mensagem' => 'Erro de conexão segura (SSL/TLS).',
            'instrucao' => 'Verifique o certificado digital e tente novamente.',
            'categoria' => 'conexao',
        ],
        'CONN_REFUSED' => [
            'mensagem' => 'Conexão recusada pelo servidor.',
            'instrucao' => 'O serviço pode estar temporariamente indisponível. Tente novamente.',
            'categoria' => 'conexao',
            'recuperavel' => true,
        ],
        'SERVICO_INDISPONIVEL' => [
            'mensagem' => 'Serviço da SEFIN temporariamente indisponível.',
            'instrucao' => 'Tente novamente em alguns minutos.',
            'categoria' => 'conexao',
            'recuperavel' => true,
        ],
        'CONN_CURL' => [
            'mensagem' => 'Erro de comunicação com o servidor.',
            'instrucao' => 'Tente novamente. Se persistir, contate o suporte.',
            'categoria' => 'conexao',
            'recuperavel' => true,
        ],

        // ==========================================
        // PRESTADOR
        // ==========================================
        'CNPJ_INVALIDO' => [
            'mensagem' => 'CNPJ do prestador inválido.',
            'instrucao' => 'Verifique os dados da empresa nas configurações.',
            'categoria' => 'prestador',
        ],
        'CNPJ_NAO_CADASTRADO' => [
            'mensagem' => 'CNPJ não cadastrado no Portal Nacional da NFS-e.',
            'instrucao' => 'Cadastre sua empresa no portal antes de emitir.',
            'categoria' => 'prestador',
            'explicacao' => 'Para emitir NFS-e pelo Sistema Nacional, o CNPJ precisa estar cadastrado em sefin.nfse.gov.br. Acesse o portal, faça login com certificado digital e complete o cadastro.',
        ],
        'SERIE_INVALIDA' => [
            'mensagem' => 'Série da DPS não configurada ou inválida.',
            'instrucao' => 'Configure a série nas configurações da empresa.',
            'categoria' => 'prestador',
            'explicacao' => 'A série deve estar cadastrada no Portal Nacional. Acesse sefin.nfse.gov.br > Configurações > Séries e cadastre a série que está utilizando.',
        ],
        'IM_INVALIDA' => [
            'mensagem' => 'Inscrição Municipal inválida ou não encontrada.',
            'instrucao' => 'Verifique a Inscrição Municipal nas configurações da empresa.',
            'categoria' => 'prestador',
        ],
        'IM_SUSPENSA' => [
            'mensagem' => 'Inscrição Municipal suspensa.',
            'instrucao' => 'Regularize sua situação junto à prefeitura.',
            'categoria' => 'prestador',
        ],

        // ==========================================
        // TOMADOR
        // ==========================================
        'TOMADOR_NAO_INFORMADO' => [
            'mensagem' => 'Dados do cliente (tomador) não informados.',
            'instrucao' => 'Preencha os dados do cliente antes de emitir a nota.',
            'categoria' => 'tomador',
        ],
        'TOMADOR_CPF_INVALIDO' => [
            'mensagem' => 'CPF do cliente inválido.',
            'instrucao' => 'Verifique o cadastro do cliente.',
            'categoria' => 'tomador',
        ],
        'TOMADOR_CNPJ_INVALIDO' => [
            'mensagem' => 'CNPJ do cliente inválido.',
            'instrucao' => 'Verifique o cadastro do cliente.',
            'categoria' => 'tomador',
        ],
        'TOMADOR_ENDERECO' => [
            'mensagem' => 'Endereço do cliente incompleto.',
            'instrucao' => 'Verifique CEP, cidade e estado no cadastro do cliente.',
            'categoria' => 'tomador',
        ],
        'TOMADOR_EMAIL' => [
            'mensagem' => 'Email do cliente inválido.',
            'instrucao' => 'Corrija o email no cadastro do cliente.',
            'categoria' => 'tomador',
        ],

        // ==========================================
        // SERVICO
        // ==========================================
        'SERVICO_NAO_INFORMADO' => [
            'mensagem' => 'Descrição do serviço não informada.',
            'instrucao' => 'Informe a descrição do serviço prestado.',
            'categoria' => 'servico',
        ],
        'SERVICO_CODIGO_INVALIDO' => [
            'mensagem' => 'Código do serviço (NBS) inválido.',
            'instrucao' => 'Verifique o código do serviço nas configurações de NFS-e.',
            'categoria' => 'servico',
        ],
        'VALOR_INVALIDO' => [
            'mensagem' => 'Valor do serviço inválido.',
            'instrucao' => 'O valor deve ser maior que zero.',
            'categoria' => 'servico',
        ],
        'VALOR_ZERADO' => [
            'mensagem' => 'Não é possível emitir nota fiscal com valor zerado.',
            'instrucao' => 'Informe um valor maior que zero.',
            'categoria' => 'servico',
        ],
        'ALIQUOTA_INVALIDA' => [
            'mensagem' => 'Alíquota de ISS inválida para este município.',
            'instrucao' => 'Verifique a alíquota de ISS nas configurações.',
            'categoria' => 'servico',
        ],

        // ==========================================
        // XML
        // ==========================================
        'XML_INVALIDO' => [
            'mensagem' => 'Erro na geração do documento fiscal.',
            'instrucao' => 'Contate o suporte técnico.',
            'categoria' => 'xml',
        ],
        'XML_ASSINATURA' => [
            'mensagem' => 'Erro ao assinar o documento fiscal.',
            'instrucao' => 'Verifique o certificado digital e tente novamente.',
            'categoria' => 'xml',
        ],
        'XML_SCHEMA' => [
            'mensagem' => 'Documento fora do padrão da SEFIN.',
            'instrucao' => 'Contate o suporte técnico.',
            'categoria' => 'xml',
        ],

        // ==========================================
        // DUPLICIDADE
        // ==========================================
        'NOTA_DUPLICADA' => [
            'mensagem' => 'Já existe uma NFS-e emitida para este lançamento.',
            'instrucao' => 'Verifique as notas já emitidas.',
            'categoria' => 'duplicidade',
        ],
        'RPS_DUPLICADO' => [
            'mensagem' => 'Número de RPS já utilizado.',
            'instrucao' => 'Tente novamente, o sistema gerará um novo número.',
            'categoria' => 'duplicidade',
        ],

        // ==========================================
        // CANCELAMENTO
        // ==========================================
        'CANCEL_PRAZO' => [
            'mensagem' => 'Prazo para cancelamento expirado.',
            'instrucao' => 'Contate a prefeitura para cancelamento fora do prazo.',
            'categoria' => 'cancelamento',
        ],
        'CANCEL_JA_CANCELADA' => [
            'mensagem' => 'Esta nota fiscal já foi cancelada anteriormente.',
            'instrucao' => 'Verifique o status da nota no sistema.',
            'categoria' => 'cancelamento',
        ],
        'CANCEL_SUBSTITUIDA' => [
            'mensagem' => 'Esta nota foi substituída e não pode ser cancelada.',
            'instrucao' => 'Cancele a nota substituta em seu lugar.',
            'categoria' => 'cancelamento',
        ],
        'CANCEL_MOTIVO' => [
            'mensagem' => 'Motivo do cancelamento não informado.',
            'instrucao' => 'Informe o motivo do cancelamento.',
            'categoria' => 'cancelamento',
        ],

        // ==========================================
        // CONSULTA
        // ==========================================
        'NOTA_NAO_ENCONTRADA' => [
            'mensagem' => 'Nota fiscal não encontrada na base da SEFIN.',
            'instrucao' => 'Verifique se a nota foi emitida corretamente.',
            'categoria' => 'consulta',
        ],
        'CHAVE_INVALIDA' => [
            'mensagem' => 'Chave de acesso da NFS-e inválida.',
            'instrucao' => 'Verifique a chave de acesso e tente novamente.',
            'categoria' => 'consulta',
        ],

        // ==========================================
        // GENERICO / CONFIGURACAO
        // ==========================================
        'ERRO_DESCONHECIDO' => [
            'mensagem' => 'Ocorreu um erro inesperado.',
            'instrucao' => 'Tente novamente ou contate o suporte.',
            'categoria' => 'generico',
        ],
        'MANUTENCAO' => [
            'mensagem' => 'Sistema em manutenção.',
            'instrucao' => 'Tente novamente mais tarde.',
            'categoria' => 'generico',
            'recuperavel' => true,
        ],
        'CONFIGURACAO_INCOMPLETA' => [
            'mensagem' => 'Configurações de NFS-e incompletas.',
            'instrucao' => 'Complete as configurações de NFS-e da empresa.',
            'categoria' => 'configuracao',
        ],
        'NFSE_DESATIVADA' => [
            'mensagem' => 'Emissão de NFS-e desativada para esta empresa.',
            'instrucao' => 'Ative a emissão de NFS-e nas configurações.',
            'categoria' => 'configuracao',
        ],
        'MUNICIPIO_NAO_SUPORTADO' => [
            'mensagem' => 'Município não suportado pelo sistema de emissão configurado.',
            'instrucao' => 'Verifique o tipo de emissão nas configurações.',
            'categoria' => 'configuracao',
        ],

        // ==========================================
        // ERROS SEFIN NACIONAL (codigos E0xxx, E1xxx)
        // ==========================================
        'E0039' => [
            'mensagem' => 'Município não habilitado no Sistema Nacional NFS-e.',
            'instrucao' => 'Verifique se a empresa deve emitir por Betha Cloud ou aguarde a adesão do município ao padrão nacional.',
            'categoria' => 'prestador',
            'explicacao' => 'O município do prestador ainda não aderiu ao Sistema Nacional de NFS-e. Use apenas emissores oficialmente suportados no sistema.',
        ],
        'E0120' => [
            'mensagem' => 'Inscrição Municipal não deve ser informada.',
            'instrucao' => 'Deixe a IM em branco nas configurações.',
            'categoria' => 'prestador',
        ],
        'E0611' => [
            'mensagem' => 'Não é permitido informar alíquota de ISS.',
            'instrucao' => 'Configure a alíquota como 0,00.',
            'categoria' => 'servico',
        ],
        'E0615' => [
            'mensagem' => 'Obrigatório informar alíquota de ISS.',
            'instrucao' => 'Informe a alíquota nas configurações (retenção ME/EPP).',
            'categoria' => 'servico',
        ],
        'E0625' => [
            'mensagem' => 'Alíquota de ISS deve ser 0,00 para ME/EPP sem retenção.',
            'instrucao' => 'Configure alíquota ISS como 0,00.',
            'categoria' => 'servico',
        ],
        'E0634' => [
            'mensagem' => 'Alíquota de ISS deve ser 0,00 quando não há retenção.',
            'instrucao' => 'Configure a alíquota de ISS como 0,00.',
            'categoria' => 'servico',
        ],
        'E0688' => [
            'mensagem' => 'Alíquota de ISS deve ser 0,00 para ISS não retido.',
            'instrucao' => 'Configure a alíquota de ISS como 0,00.',
            'categoria' => 'servico',
        ],
        'E0690' => [
            'mensagem' => 'Retenção de PIS/COFINS requer situação tributária.',
            'instrucao' => 'Informe a situação tributária federal ou alíquota de COFINS.',
            'categoria' => 'servico',
        ],
        'E0712' => [
            'mensagem' => 'Regime Especial de Tributação incorreto.',
            'instrucao' => 'Verifique o regime tributário nas configurações.',
            'categoria' => 'prestador',
        ],
        'E0714' => [
            'mensagem' => 'DPS rejeitada - Erro na estrutura do documento.',
            'instrucao' => 'Verifique campos obrigatórios.',
            'categoria' => 'xml',
            'explicacao' => 'A DPS foi rejeitada por erro na estrutura. Verifique: código do serviço (NBS), série, código do município (IBGE) e regime tributário.',
        ],
        'E1010' => [
            'mensagem' => 'Sociedade de Profissionais obrigada ao Padrão Nacional.',
            'instrucao' => 'Obrigatório a partir de 01/10/2025.',
            'categoria' => 'prestador',
        ],
        'E1011' => [
            'mensagem' => 'Simples Nacional obrigado ao Padrão Nacional.',
            'instrucao' => 'Obrigatório a partir de 01/11/2025.',
            'categoria' => 'prestador',
        ],

        // ==========================================
        // ERROS RNG (Validacao Schema XML)
        // ==========================================
        'RNG6110' => [
            'mensagem' => 'Falha na validação do XML contra o Schema (XSD).',
            'instrucao' => 'Verifique dados da empresa e serviço.',
            'categoria' => 'xml',
            'explicacao' => 'O XML gerado não passou na validação contra o schema XSD da SEFIN. Verifique: CNPJ (14 dígitos), código do município (7 dígitos), NBS e série.',
        ],
        'RNG6111' => [
            'mensagem' => 'Elemento inesperado ou fora de ordem no XML.',
            'instrucao' => 'Contate o suporte técnico.',
            'categoria' => 'xml',
        ],
        'RNG6112' => [
            'mensagem' => 'Campo obrigatório ausente no XML.',
            'instrucao' => 'Verifique dados obrigatórios nas configurações.',
            'categoria' => 'xml',
        ],
        'RNG6113' => [
            'mensagem' => 'Formato de campo inválido no XML.',
            'instrucao' => 'Verifique formatos numéricos e datas.',
            'categoria' => 'xml',
        ],
        'RNG9997' => [
            'mensagem' => 'Dados incompatíveis com o Padrão Nacional.',
            'instrucao' => 'Verifique natureza da operação, série, NBS e regime.',
            'categoria' => 'xml',
            'explicacao' => 'Combinação de campos incompatível. Verifique: tribISSQN + alíquota, regime tributário + opção Simples Nacional, série cadastrada no portal.',
        ],

        'E090' => [
            'mensagem' => 'Número do RPS inválido.',
            'instrucao' => 'Verifique "Número RPS Atual" nas configurações e AIDF no ISSNet.',
            'categoria' => 'prestador',
            'explicacao' => 'O número do RPS precisa estar dentro da faixa autorizada pela AIDF (Autorização de Impressão de Documentos Fiscais). Acesse o ISSNet e verifique a faixa autorizada.',
        ],
        'E093' => [
            'mensagem' => 'Série do RPS inválida.',
            'instrucao' => 'Verifique "Série" e AIDF no portal ISSNet.',
            'categoria' => 'prestador',
            'explicacao' => 'A série informada não está autorizada na AIDF. Acesse ISSNet > Autorização > AIDF e verifique a série cadastrada.',
        ],
        'E160' => [
            'mensagem' => 'XML em desacordo com o XML Schema do webservice.',
            'instrucao' => 'Verifique campos obrigatórios, formatos e códigos.',
            'categoria' => 'xml',
            'explicacao' => 'O XML não passou na validação do webservice. Verifique: CNPJ, IM, códigos de serviço (CNAE, item lista, tributação), formato de datas e valores.',
        ],
        'E183' => [
            'mensagem' => 'Cabeçalho XML fora do padrão do webservice.',
            'instrucao' => 'Contate o suporte técnico.',
            'categoria' => 'xml',
            'explicacao' => 'O cabeçalho SOAP está incorreto. Pode ser versão do schema, namespace ou encoding.',
        ],
        'E232' => [
            'mensagem' => 'Erro no processamento do arquivo pelo webservice.',
            'instrucao' => 'Verifique AIDF e acesso ao webservice no ISSNet.',
            'categoria' => 'xml',
            'explicacao' => 'O webservice não conseguiu processar o RPS. Verifique: se a AIDF está ativa, se o acesso ao webservice está liberado no ISSNet, e se o certificado está cadastrado.',
        ],
    ];

    /**
     * Mapeamento de codigos da API SEFIN para codigos internos
     */
    private static array $mapeamentoAPI = [
        'E01' => 'CNPJ_INVALIDO',
        'E02' => 'IM_INVALIDA',
        'E03' => 'SERVICO_CODIGO_INVALIDO',
        'E04' => 'VALOR_INVALIDO',
        'E05' => 'TOMADOR_NAO_INFORMADO',
        'E06' => 'TOMADOR_CPF_INVALIDO',
        'E07' => 'TOMADOR_CNPJ_INVALIDO',
        'E08' => 'TOMADOR_ENDERECO',
        'E09' => 'SERVICO_NAO_INFORMADO',
        'E10' => 'XML_SCHEMA',
        'E11' => 'XML_ASSINATURA',
        'E20' => 'NOTA_DUPLICADA',
        'E21' => 'RPS_DUPLICADO',
        'E30' => 'CERT_EXPIRADO',
        'E31' => 'CERT_INVALIDO',
        'E40' => 'CANCEL_PRAZO',
        'E41' => 'CANCEL_JA_CANCELADA',
        'E42' => 'CANCEL_SUBSTITUIDA',
        'E50' => 'NOTA_NAO_ENCONTRADA',
        'E51' => 'CHAVE_INVALIDA',
        'E90' => 'SERVICO_INDISPONIVEL',
        'E91' => 'MANUTENCAO',
        'E99' => 'ERRO_DESCONHECIDO',
    ];

    public static function getMensagem(string $codigo): string
    {
        return self::$erros[$codigo]['mensagem'] ?? self::$erros['ERRO_DESCONHECIDO']['mensagem'];
    }

    public static function getInstrucao(string $codigo): string
    {
        return self::$erros[$codigo]['instrucao'] ?? self::$erros['ERRO_DESCONHECIDO']['instrucao'];
    }

    public static function getCategoria(string $codigo): string
    {
        return self::$erros[$codigo]['categoria'] ?? 'generico';
    }

    public static function getErro(string $codigo): array
    {
        return self::$erros[$codigo] ?? self::$erros['ERRO_DESCONHECIDO'];
    }

    /**
     * Mapeia codigo da API SEFIN para codigo interno
     */
    public static function mapearErroAPI(string $codigoSEFIN): string
    {
        // Codigos com entrada propria (E0xxx, E1xxx, RNGxxxx, E090, E093, etc.)
        if (isset(self::$erros[$codigoSEFIN])) {
            return $codigoSEFIN;
        }

        // Mapeamento direto
        if (isset(self::$mapeamentoAPI[$codigoSEFIN])) {
            return self::$mapeamentoAPI[$codigoSEFIN];
        }

        // Fallback por prefixo
        if (str_starts_with($codigoSEFIN, 'RNG')) {
            return 'XML_SCHEMA';
        }
        if (str_starts_with($codigoSEFIN, 'E0')) {
            return 'XML_INVALIDO';
        }
        if (preg_match('/^E\d+$/', $codigoSEFIN)) {
            return 'XML_INVALIDO';
        }

        return 'ERRO_DESCONHECIDO';
    }

    /**
     * Formata erro para exibicao ao usuario
     */
    public static function formatarParaUsuario(string $codigo): array
    {
        $erro = self::getErro($codigo);

        return [
            'codigo' => $codigo,
            'mensagem' => $erro['mensagem'],
            'instrucao' => $erro['instrucao'],
            'categoria' => $erro['categoria'],
            'explicacao' => $erro['explicacao'] ?? null,
        ];
    }

    /**
     * Verifica se o erro permite reenvio automatico
     */
    public static function isRecuperavel(string $codigo): bool
    {
        return self::$erros[$codigo]['recuperavel'] ?? false;
    }

    /**
     * Retorna lista de codigos de erro recuperaveis
     */
    public static function getCodigosRecuperaveis(): array
    {
        return array_keys(array_filter(self::$erros, fn($e) => $e['recuperavel'] ?? false));
    }
}
