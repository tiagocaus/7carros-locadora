<?php

namespace App\Services;

use App\Models\SerproConsultaLog;

/**
 * Service para comunicacao com a API de consultas online
 *
 * Centraliza todas as chamadas HTTP para a API SERPRO.
 * Usa bearer token unico da 7Carros (configurado via ENV).
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
    private SerproConsultaLog $log;

    public function __construct()
    {
        $this->ambiente = env('SERPRO_AMBIENTE', 'homologacao');

        if ($this->ambiente === 'homologacao') {
            $this->baseUrl = 'https://hom-efrotas.estaleiro.serpro.gov.br/efrotas/api';
            $this->baseUrlTransacional = 'https://hom-efrotas.estaleiro.serpro.gov.br/efrotas/api/transacional';
            $this->baseUrlCrlv = 'https://hom-efrotas.estaleiro.serpro.gov.br/efrotas/api';
        } else {
            $this->baseUrl = env('SERPRO_BASE_URL', 'https://efrotas.estaleiro.serpro.gov.br/efrotas/api');
            $this->baseUrlTransacional = env('SERPRO_BASE_URL_TRANSACIONAL', 'https://efrotas.estaleiro.serpro.gov.br/efrotas/api/transacional');
            $this->baseUrlCrlv = env('SERPRO_BASE_URL_CRLV', 'https://efrotas.estaleiro.serpro.gov.br/efrotas/api');
        }

        $this->bearerToken = env('SERPRO_BEARER_TOKEN', '');
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
        $endpoint = '/gerenciamento/v1/eventos';

        return $this->get($endpoint, 'listar_eventos');
    }

    /**
     * Ativa ou desativa um tipo de evento
     */
    public function ativarEvento(int $tipoEvento, bool $ativo): array
    {
        $endpoint = '/gerenciamento/v1/eventos';
        $body = [
            'tipoEvento' => $tipoEvento,
            'ativo' => $ativo,
        ];

        return $this->request('PUT', $this->baseUrl . $endpoint, $body, 'ativar_evento');
    }

    /**
     * Consulta URL de webhook registrada
     */
    public function consultarUrlWebhook(): array
    {
        $endpoint = '/gerenciamento/v1/url-eventos';

        return $this->get($endpoint, 'consultar_url_webhook');
    }

    /**
     * Registra URL de webhook para receber eventos
     */
    public function registrarUrlWebhook(string $url, array $headers = []): array
    {
        $endpoint = '/gerenciamento/v1/url-eventos';
        $body = [
            'url' => $url,
            'headers' => $headers,
        ];

        return $this->request('POST', $this->baseUrl . $endpoint, $body, 'registrar_url_webhook');
    }

    /**
     * Remove URL de webhook
     */
    public function removerUrlWebhook(): array
    {
        $endpoint = '/gerenciamento/v1/url-eventos';

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
            'Authorization: Bearer ' . $this->bearerToken,
        ];

        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $responseBody = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $duracaoMs = (int) ((microtime(true) - $startTime) * 1000);

        // Parse response
        $responseData = null;
        if ($responseBody !== false && $responseBody !== '') {
            $responseData = json_decode($responseBody, true);
            if ($responseData === null && json_last_error() !== JSON_ERROR_NONE) {
                // Response nao eh JSON (pode ser PDF base64)
                $responseData = ['raw' => $responseBody];
            }
        }

        // Determinar sucesso
        $success = $httpCode >= 200 && $httpCode < 300 && $curlError === '';
        $erroMensagem = null;

        if (!$success) {
            if ($curlError !== '') {
                $erroMensagem = "cURL error: {$curlError}";
            } elseif ($httpCode === 429) {
                $erroMensagem = 'Rate limit excedido (15 req/s). Tente novamente em instantes.';
            } elseif ($httpCode === 401) {
                $erroMensagem = 'Token do sistema de consultas online invalido ou expirado.';
            } elseif ($httpCode === 403) {
                $erroMensagem = 'Acesso negado pelo sistema de consultas online.';
            } elseif ($httpCode === 404) {
                $erroMensagem = 'Recurso nao encontrado no sistema de consultas online.';
            } else {
                $erroMensagem = "HTTP {$httpCode}: " . ($responseData['message'] ?? $responseBody ?? 'Erro desconhecido');
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
            $body,
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
