<?php

namespace App\Services;

use App\Models\SerproConsultaLog;

/**
 * Service para comunicacao com a API de consultas online
 *
 * Centraliza todas as chamadas HTTP para a API SERPRO.
 * Usa bearer token em homologacao e certificado digital mTLS em producao.
 * Rate limit: 15 conexoes/segundo por IP.
 *
 * @see SERPRO_CENTRAL_MULTAS.md para documentacao completa dos endpoints
 */
class SerproService
{
    private string $baseUrl;
    private string $baseUrlTransacional;
    private string $baseUrlCrlv;
    private string $bearerToken;
    private string $ambiente;
    private string $certPath;
    private string $certKeyPath;
    private string $certKeyPassword;
    private string $certPassword;
    private string $certType;
    private ?string $urlConfigError = null;
    private SerproConsultaLog $log;

    public function __construct()
    {
        $this->ambiente = $this->normalizarValorEnv(env('SERPRO_AMBIENTE', 'homologacao'));

        if ($this->ambiente === 'homologacao') {
            $this->baseUrl = $this->envUrl('SERPRO_HOMOLOGACAO_BASE_URL');
            $this->baseUrlTransacional = $this->envUrl('SERPRO_HOMOLOGACAO_BASE_URL_TRANSACIONAL');
            $this->baseUrlCrlv = $this->envUrlCrlv('SERPRO_HOMOLOGACAO_BASE_URL_CRLV');
        } else {
            $this->baseUrl = $this->envUrl('SERPRO_PRODUCAO_BASE_URL');
            $this->baseUrlTransacional = $this->envUrl('SERPRO_PRODUCAO_BASE_URL_TRANSACIONAL');
            $this->baseUrlCrlv = $this->envUrlCrlv('SERPRO_PRODUCAO_BASE_URL_CRLV');
        }

        $this->bearerToken = env('SERPRO_BEARER_TOKEN', '');
        $this->certPath = env('SERPRO_CERT_PATH', '');
        $this->certKeyPath = env('SERPRO_CERT_KEY_PATH', '');
        $this->certKeyPassword = env('SERPRO_CERT_KEY_PASSWORD', '');
        $this->certPassword = env('SERPRO_CERT_PASSWORD', '');
        $this->certType = strtoupper((string) env('SERPRO_CERT_TYPE', 'P12'));
        if ($this->certType === 'PFX') {
            $this->certType = 'P12';
        }
        $this->log = new SerproConsultaLog();
    }

    // =========================================================================
    // VEICULOS
    // =========================================================================

    /**
     * Lista todos os veiculos da frota
     */
    public function consultarVeiculos(string $cnpj, int $pagina = 1, int $quantidade = 20): array
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);
        $endpoint = "/consultas/v1/veiculos?cnpjFilial={$cnpj}&pagina={$pagina}&quantidade={$quantidade}";

        return $this->get($endpoint, 'consulta_veiculos');
    }

    /**
     * Consulta veiculo por placa
     */
    public function consultarVeiculoPorPlaca(string $placa): array
    {
        $placa = strtoupper(trim($placa));
        $endpoint = "/consultas/v1/veiculos/placa/{$placa}";

        return $this->get($endpoint, 'consulta_veiculo', $placa);
    }

    /**
     * Verifica se veiculo pertence a frota
     */
    public function verificarPertenceAFrota(string $placa): array
    {
        $placa = strtoupper(trim($placa));
        $endpoint = "/consultas/v1/veiculos/placa/{$placa}/pertence";

        return $this->get($endpoint, 'verificar_pertence_frota', $placa);
    }

    /**
     * Consulta roubo/furto do veiculo
     */
    public function consultarRouboFurto(string $placa): array
    {
        $placa = strtoupper(trim($placa));
        $endpoint = "/consultas/v1/veiculos/placa/{$placa}/roubo-furto";

        return $this->get($endpoint, 'consulta_roubo_furto', $placa);
    }

    /**
     * Consulta recall pendente do veiculo
     */
    public function consultarRecall(string $placa): array
    {
        $placa = strtoupper(trim($placa));
        $endpoint = "/consultas/v1/veiculos/placa/{$placa}/recall";

        return $this->get($endpoint, 'consulta_recall', $placa);
    }

    /**
     * Consulta restricoes judiciais do veiculo
     */
    public function consultarRestricoesJudiciais(string $placa): array
    {
        $placa = strtoupper(trim($placa));
        $endpoint = "/consultas/v1/veiculos/placa/{$placa}/restricoes-judiciais";

        return $this->get($endpoint, 'consulta_restricoes_judiciais', $placa);
    }

    // =========================================================================
    // INFRACOES
    // =========================================================================

    /**
     * Lista infracoes nao pagas do veiculo
     */
    public function consultarInfracoes(string $placa): array
    {
        $placa = strtoupper(trim($placa));
        $endpoint = "/consultas/v1/infracoes/placa/{$placa}";

        return $this->get($endpoint, 'consulta_infracoes', $placa);
    }

    /**
     * Consulta detalhe de uma infracao especifica
     */
    public function consultarInfracaoDetalhe(string $codigoOrgao, string $numeroAit, string $codigoInfracao): array
    {
        $endpoint = "/consultas/v1/infracoes/codigoOrgao/{$codigoOrgao}/numeroAit/{$numeroAit}/codigoInfracao/{$codigoInfracao}";

        return $this->get($endpoint, 'consulta_infracao_detalhe');
    }

    /**
     * Baixa PDF da Notificacao de Autuacao (NA)
     */
    public function downloadNAPdf(string $placa, string $codigoOrgao, string $numeroAit, string $codigoInfracao): array
    {
        $placa = strtoupper(trim($placa));
        $endpoint = "/consultas/sne/pdf/placa/{$placa}/codigoOrgao/{$codigoOrgao}/numeroAit/{$numeroAit}/codigoInfracao/{$codigoInfracao}/NA";

        return $this->get($endpoint, 'consulta_na_pdf', $placa);
    }

    /**
     * Baixa PDF da Notificacao de Penalidade (NP)
     */
    public function downloadNPPdf(string $placa, string $codigoOrgao, string $numeroAit, string $codigoInfracao): array
    {
        $placa = strtoupper(trim($placa));
        $endpoint = "/consultas/sne/pdf/placa/{$placa}/codigoOrgao/{$codigoOrgao}/numeroAit/{$numeroAit}/codigoInfracao/{$codigoInfracao}/NP";

        return $this->get($endpoint, 'consulta_np_pdf', $placa);
    }

    // =========================================================================
    // DOCUMENTOS
    // =========================================================================

    /**
     * Consulta CRLV do veiculo (PDF + QRCode em base64)
     */
    public function consultarCRLV(string $placa): array
    {
        $placa = strtoupper(trim($placa));
        $endpoint = "/v1/documento/placa/{$placa}";

        return $this->request('GET', $this->baseUrlCrlv . $endpoint, null, 'consulta_crlv', $placa);
    }

    // =========================================================================
    // NOTIFICACOES E EVENTOS
    // =========================================================================

    /**
     * Consulta notificacoes por periodo
     */
    public function consultarNotificacoes(string $dataInicio, string $dataFim): array
    {
        $endpoint = "/notificacoes/v1/dataInicio/{$dataInicio}/dataFim/{$dataFim}";

        return $this->get($endpoint, 'consulta_notificacoes');
    }

    /**
     * Lista eventos ativos/inativos
     */
    public function listarEventos(): array
    {
        $endpoint = '/autorizador/v1/eventos';

        return $this->get($endpoint, 'listar_eventos');
    }

    /**
     * Ativa ou desativa um tipo de evento
     */
    public function ativarEvento(int $tipoEvento, bool $ativo): array
    {
        $endpoint = '/autorizador/v1/eventos';
        $body = [
            'eventosPermitidos' => [
                [
                    'codigo' => $tipoEvento,
                    'ativo' => $ativo,
                ],
            ],
        ];

        return $this->request('PUT', $this->baseUrl . $endpoint, $body, 'ativar_evento');
    }

    /**
     * Consulta URL de webhook registrada
     */
    public function consultarUrlWebhook(): array
    {
        $endpoint = '/autorizador/v1/endpoint';

        return $this->get($endpoint, 'consultar_url_webhook');
    }

    /**
     * Registra URL de webhook para receber eventos
     */
    public function registrarUrlWebhook(string $url, array $headers = []): array
    {
        $endpoint = '/autorizador/v1/endpoint';
        $headerName = '';
        $headerValue = '';

        foreach ($headers as $key => $value) {
            $headerName = (string) $key;
            $headerValue = (string) $value;
            break;
        }

        $body = [
            'url' => $url,
            'header' => $headerName,
            'valor' => $headerValue,
        ];

        return $this->request('PUT', $this->baseUrl . $endpoint, $body, 'registrar_url_webhook');
    }

    /**
     * Remove URL de webhook
     */
    public function removerUrlWebhook(): array
    {
        $endpointAtual = $this->consultarUrlWebhook();
        if (!$endpointAtual['success']) {
            return $endpointAtual;
        }

        $endpointData = $endpointAtual['data'][0] ?? $endpointAtual['data'] ?? [];
        $endpointId = (int) ($endpointData['id'] ?? 0);

        if ($endpointId <= 0) {
            return [
                'success' => true,
                'status' => 200,
                'data' => null,
                'error' => null,
            ];
        }

        $endpoint = '/autorizador/v1/endpoint/' . $endpointId;

        return $this->request('DELETE', $this->baseUrl . $endpoint, null, 'remover_url_webhook');
    }

    // =========================================================================
    // REAL INFRATOR
    // =========================================================================

    /**
     * Insere indicacao de real infrator
     */
    public function indicarRealInfrator(array $dados): array
    {
        $endpoint = '/v1/realinfrator/indicacoes/inserir';
        $body = [
            'codigoOrgao' => $dados['codigo_orgao'],
            'numeroAit' => $dados['numero_ait'],
            'codigoInfracao' => $dados['codigo_infracao'],
            'cnpjIndicante' => preg_replace('/\D/', '', $dados['cnpj_indicante']),
            'cpfIndicado' => preg_replace('/\D/', '', $dados['cpf_indicado']),
        ];

        return $this->request('POST', $this->baseUrlTransacional . $endpoint, $body, 'indicar_real_infrator');
    }

    /**
     * Cancela indicacao de real infrator
     */
    public function cancelarRealInfrator(string $chaveIndicacao, array $dados): array
    {
        $endpoint = "/v1/realinfrator/indicacoes/{$chaveIndicacao}/cancelar";
        $body = [
            'codigoOrgao' => $dados['codigo_orgao'],
            'numeroAit' => $dados['numero_ait'],
            'codigoInfracao' => $dados['codigo_infracao'],
            'cnpjIndicante' => preg_replace('/\D/', '', $dados['cnpj_indicante']),
        ];

        return $this->request('POST', $this->baseUrlTransacional . $endpoint, $body, 'cancelar_real_infrator');
    }

    /**
     * Consulta status de indicacao de real infrator
     */
    public function statusRealInfrator(string $chaveIndicacao): array
    {
        $endpoint = "/v1/realinfrator/indicacoes/{$chaveIndicacao}/status";

        return $this->request('GET', $this->baseUrlTransacional . $endpoint, null, 'status_real_infrator');
    }

    /**
     * Consulta documento assinado de indicacao de real infrator
     */
    public function documentoAssinadoRealInfrator(
        string $chaveIndicacao,
        string $codigoOrgao,
        string $numeroAit,
        string $codigoInfracao
    ): array {
        $endpoint = "/v1/realinfrator/indicacoes/{$chaveIndicacao}/{$codigoOrgao}/{$numeroAit}/{$codigoInfracao}/documentoAssinado";

        return $this->request('GET', $this->baseUrlTransacional . $endpoint, null, 'documento_assinado_real_infrator');
    }

    /**
     * Consulta historico de indicacoes de real infrator por infracao
     */
    public function historicoRealInfrator(string $codigoOrgao, string $numeroAit, string $codigoInfracao): array
    {
        $endpoint = "/v1/realinfrator/indicacoes/historico/{$codigoOrgao}/{$numeroAit}/{$codigoInfracao}";

        return $this->request('GET', $this->baseUrlTransacional . $endpoint, null, 'historico_real_infrator');
    }

    // =========================================================================
    // PRINCIPAL CONDUTOR
    // =========================================================================

    /**
     * Insere indicacao de principal condutor
     */
    public function indicarPrincipalCondutor(array $dados): array
    {
        $endpoint = '/v1/principalcondutor/indicacoes/inserir';
        $body = [
            'identificacaoPossuidor' => preg_replace('/\D/', '', $dados['cnpj_indicante']),
            'placaVeiculo' => strtoupper(trim($dados['placa'])),
            'cpfPrincipalCondutor' => preg_replace('/\D/', '', $dados['cpf_indicado']),
            'numeroRegistroCnhPrincipalCondutor' => $dados['cnh_indicado'] ?? '',
        ];

        return $this->request('POST', $this->baseUrlTransacional . $endpoint, $body, 'indicar_principal_condutor');
    }

    /**
     * Exclui indicacao de principal condutor
     */
    public function excluirPrincipalCondutor(array $dados): array
    {
        $endpoint = '/v1/principalcondutor/indicacoes/excluir';
        $body = [
            'cnpjIndicante' => preg_replace('/\D/', '', $dados['cnpj_indicante']),
            'chaveIndicacao' => $dados['chave_indicacao'],
            'cpfIndicado' => preg_replace('/\D/', '', $dados['cpf_indicado']),
            'placa' => strtoupper(trim($dados['placa'])),
        ];

        return $this->request('POST', $this->baseUrlTransacional . $endpoint, $body, 'excluir_principal_condutor');
    }

    /**
     * Consulta status de indicacao de principal condutor
     */
    public function statusPrincipalCondutor(string $chaveIndicacao): array
    {
        $endpoint = "/v1/principalcondutor/indicacoes/status?chaveIndicacao={$chaveIndicacao}";

        return $this->request('GET', $this->baseUrlTransacional . $endpoint, null, 'status_principal_condutor');
    }

    /**
     * Consulta historico de indicacoes de principal condutor
     */
    public function historicoPrincipalCondutor(array $params): array
    {
        $query = http_build_query(array_filter([
            'placa' => strtoupper(trim($params['placa'] ?? '')),
            'identificacaoPossuidor' => preg_replace('/\D/', '', $params['cnpj'] ?? ''),
            'dataInicio' => $params['data_inicio'] ?? '',
            'dataFim' => $params['data_fim'] ?? '',
            'omitirExcluidas' => $params['omitir_excluidas'] ?? 'false',
        ]));

        $endpoint = "/v1/principalcondutor/indicacoes/historico?{$query}";

        return $this->request('GET', $this->baseUrlTransacional . $endpoint, null, 'historico_principal_condutor');
    }

    // =========================================================================
    // HTTP CLIENT (PRIVADO)
    // =========================================================================

    /**
     * Executa GET na base URL principal
     */
    private function get(string $endpoint, string $tipoOperacao, ?string $placa = null): array
    {
        return $this->request('GET', $this->baseUrl . $endpoint, null, $tipoOperacao, $placa);
    }

    /**
     * Executa requisicao HTTP para a API SERPRO
     *
     * @param string $method GET, POST, PUT, DELETE
     * @param string $url URL completa
     * @param array|null $body Body para POST/PUT
     * @param string $tipoOperacao Tipo para log
     * @param string|null $placa Placa para log
     * @return array ['success' => bool, 'status' => int, 'data' => array|null, 'error' => string|null]
     */
    private function request(
        string $method,
        string $url,
        ?array $body = null,
        string $tipoOperacao = '',
        ?string $placa = null
    ): array {
        $chave = $_SESSION['chave'] ?? 'system';
        $startTime = microtime(true);

        $headers = [
            'Accept: application/json',
        ];

        if ($this->urlConfigError !== null) {
            return $this->configError($chave, $tipoOperacao, $url, $placa, $body, $this->urlConfigError);
        }

        if ($this->ambiente === 'homologacao') {
            if ($this->bearerToken === '') {
                return $this->configError($chave, $tipoOperacao, $url, $placa, $body, 'SERPRO_BEARER_TOKEN nao configurado para homologacao.');
            }

            $headers[] = 'Authorization: Bearer ' . $this->bearerToken;
        }

        if ($this->ambiente === 'producao') {
            $certPath = $this->resolveCertPath();
            $certValidationError = $this->validateProductionCertificate($certPath);

            if ($certValidationError !== null) {
                return $this->configError($chave, $tipoOperacao, $url, $placa, $body, $certValidationError);
            }
        } else {
            $certPath = null;
        }

        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        if ($certPath !== null) {
            curl_setopt($ch, CURLOPT_SSLCERT, $certPath);
            curl_setopt($ch, CURLOPT_SSLCERTTYPE, $this->certType);

            if ($this->certType === 'PEM' && $this->certKeyPath !== '') {
                curl_setopt($ch, CURLOPT_SSLKEY, $this->resolvePath($this->certKeyPath));
                curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');

                if ($this->certKeyPassword !== '') {
                    curl_setopt($ch, CURLOPT_KEYPASSWD, $this->certKeyPassword);
                }
            } elseif ($this->certPassword !== '') {
                curl_setopt($ch, CURLOPT_SSLCERTPASSWD, $this->certPassword);
            }
        }

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $responseRaw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $duracaoMs = (int) ((microtime(true) - $startTime) * 1000);
        $responseHeaders = [];
        $responseBody = false;

        if ($responseRaw !== false) {
            $responseHeaderText = substr($responseRaw, 0, $headerSize);
            $responseBody = substr($responseRaw, $headerSize);
            $responseHeaders = $this->parseResponseHeaders($responseHeaderText);
        }

        // Parse response
        $responseData = null;
        if ($responseBody !== false && $responseBody !== '') {
            $responseData = json_decode($responseBody, true);
            if ($responseData === null && json_last_error() !== JSON_ERROR_NONE) {
                // Response nao eh JSON (pode ser PDF base64)
                $responseData = ['raw' => $responseBody];
            }
        }

        if ($successLocation = $this->extrairChaveIndicacaoLocation($responseHeaders)) {
            if (!is_array($responseData)) {
                $responseData = [];
            }
            $responseData['location'] = $responseHeaders['location'];
            $responseData['chaveIndicacao'] = $successLocation;
        }

        // Determinar sucesso
        $success = $httpCode >= 200 && $httpCode < 300 && $curlError === '';
        $erroMensagem = null;

        if (!$success) {
            $erroApi = $this->extrairMensagemErro($responseData, $responseBody);
            if ($curlError !== '') {
                $erroMensagem = "cURL error: {$curlError}";
            } elseif ($httpCode === 429) {
                $erroMensagem = 'Rate limit excedido (15 req/s). Tente novamente em instantes.';
            } elseif ($httpCode === 401) {
                $erroMensagem = 'Token do sistema de consultas online invalido ou expirado. ' . $erroApi;
            } elseif ($httpCode === 403) {
                $erroMensagem = 'Acesso negado pelo sistema de consultas online. ' . $erroApi;
            } elseif ($httpCode === 404) {
                $erroMensagem = 'Recurso nao encontrado no sistema de consultas online. ' . $erroApi;
            } else {
                $erroMensagem = $erroApi;
            }
        }

        // Log da chamada
        $logStatus = $success ? 'sucesso' : ($curlError !== '' ? 'timeout' : 'erro');

        $this->log->registrar(
            $chave,
            $tipoOperacao,
            $url,
            $placa,
            null, // headers (nao logar bearer token)
            $this->sanitizeRequestPayloadForLog($tipoOperacao, $body),
            $httpCode,
            $responseData,
            $logStatus,
            $erroMensagem,
            null,
            $duracaoMs
        );

        return [
            'success' => $success,
            'status' => $httpCode,
            'data' => $responseData,
            'error' => $erroMensagem,
        ];
    }

    /**
     * Remove segredos do payload antes de gravar log tecnico.
     */
    private function sanitizeRequestPayloadForLog(string $tipoOperacao, ?array $body): ?array
    {
        if ($body === null) {
            return null;
        }

        if ($tipoOperacao === 'registrar_url_webhook' && array_key_exists('valor', $body)) {
            $body['valor'] = '[redacted]';
        }

        if (
            $tipoOperacao === 'registrar_url_webhook'
            && isset($body['headers'])
            && is_array($body['headers'])
            && array_key_exists('Authorization', $body['headers'])
        ) {
            $body['headers']['Authorization'] = '[redacted]';
        }

        return $body;
    }

    /**
     * Normaliza valores vindos do .env para tolerar comentarios inline acidentais.
     */
    private function normalizarValorEnv(mixed $valor): string
    {
        $valor = trim((string) $valor);
        $valor = preg_replace('/\s+#.*$/', '', $valor) ?? $valor;

        return strtolower(trim($valor));
    }

    /**
     * Le URL obrigatoria de ambiente removendo barras finais.
     */
    private function envUrl(string $name): string
    {
        $value = trim((string) env($name, ''));
        $value = preg_replace('/\s+#.*$/', '', $value) ?? $value;

        if ($value === '' && $this->urlConfigError === null) {
            $this->urlConfigError = "{$name} nao configurado para o ambiente SERPRO {$this->ambiente}.";
        }

        return rtrim($value, '/');
    }

    /**
     * Le a URL base do modulo CRLV e tolera ENV antiga sem o sufixo /crlv.
     */
    private function envUrlCrlv(string $name): string
    {
        $value = $this->envUrl($name);
        if ($value === '') {
            return $value;
        }

        return str_ends_with($value, '/crlv') ? $value : $value . '/crlv';
    }

    /**
     * Extrai a mensagem mais util da resposta de erro da API.
     */
    private function extrairMensagemErro(?array $responseData, string|false $responseBody): string
    {
        if (is_array($responseData)) {
            foreach (['mensagemTecnica', 'mensagem', 'message', 'error', 'detail', 'title'] as $campo) {
                if (!empty($responseData[$campo]) && is_scalar($responseData[$campo])) {
                    return (string) $responseData[$campo];
                }
            }

            if (!empty($responseData['raw']) && is_scalar($responseData['raw'])) {
                return (string) $responseData['raw'];
            }
        }

        if (is_string($responseBody) && $responseBody !== '') {
            return $responseBody;
        }

        return 'Erro desconhecido';
    }

    /**
     * Converte headers HTTP em array simples com nomes em lowercase.
     */
    private function parseResponseHeaders(string $headerText): array
    {
        $headers = [];
        $blocks = preg_split("/\r\n\r\n|\n\n|\r\r/", trim($headerText));
        $lastBlock = $blocks ? end($blocks) : '';

        foreach (preg_split("/\r\n|\n|\r/", (string) $lastBlock) as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $line, 2);
            $headers[strtolower(trim($name))] = trim($value);
        }

        return $headers;
    }

    /**
     * Extrai a chave da indicacao quando a API retorna Location: /indicacoes/{chave}.
     */
    private function extrairChaveIndicacaoLocation(array $headers): ?string
    {
        if (empty($headers['location'])) {
            return null;
        }

        $path = trim((string) parse_url($headers['location'], PHP_URL_PATH), '/');
        $parts = explode('/', $path);
        $chave = end($parts);

        return $chave !== false && $chave !== '' ? $chave : null;
    }

    /**
     * Resolve caminho absoluto do certificado SERPRO.
     */
    private function resolveCertPath(): string
    {
        return $this->resolvePath($this->certPath);
    }

    /**
     * Resolve caminho absoluto dentro do projeto quando necessario.
     */
    private function resolvePath(string $path): string
    {
        if ($path === '' || str_starts_with($path, '/')) {
            return $path;
        }

        $appRoot = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2);

        return $appRoot . '/' . ltrim($path, '/');
    }

    /**
     * Valida configuracao mTLS antes de abrir conexao com a SERPRO.
     */
    private function validateProductionCertificate(string $certPath): ?string
    {
        if ($certPath === '') {
            return 'SERPRO_CERT_PATH nao configurado para producao.';
        }

        if (!is_file($certPath) || !is_readable($certPath)) {
            return 'Certificado SERPRO nao encontrado ou sem permissao de leitura: ' . $certPath;
        }

        if ($this->certType === 'PEM') {
            $keyPath = $this->resolvePath($this->certKeyPath);
            if ($keyPath !== '' && (!is_file($keyPath) || !is_readable($keyPath))) {
                return 'Chave privada SERPRO nao encontrada ou sem permissao de leitura: ' . $keyPath;
            }

            return null;
        }

        if (in_array($this->certType, ['P12', 'PFX'], true) && $this->certPassword === '') {
            return 'SERPRO_CERT_PASSWORD nao configurado para o certificado digital SERPRO.';
        }

        return null;
    }

    /**
     * Retorna erro de configuracao e registra tentativa sem expor credenciais.
     */
    private function configError(
        string $chave,
        string $tipoOperacao,
        string $url,
        ?string $placa,
        ?array $body,
        string $message
    ): array {
        $this->log->registrar(
            $chave,
            $tipoOperacao,
            $url,
            $placa,
            null,
            $body,
            0,
            null,
            'erro',
            $message,
            null,
            0
        );

        return [
            'success' => false,
            'status' => 0,
            'data' => null,
            'error' => $message,
        ];
    }

    // =========================================================================
    // UTILITARIOS
    // =========================================================================

    /**
     * Retorna ambiente atual (homologacao/producao)
     */
    public function getAmbiente(): string
    {
        return $this->ambiente;
    }

    /**
     * Verifica se a API esta acessivel
     */
    public function healthCheck(): bool
    {
        $result = $this->listarEventos();
        return $result['success'];
    }
}
