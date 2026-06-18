<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\SerproIndicacao;
use App\Models\SerproConfiguracao;
use App\Models\Multa;
use App\Models\Veiculo;
use App\Models\MatrizFilial;
use App\Services\SerproService;
use App\Services\SerproIndicacaoStatusService;
use App\Services\SerproSaldoService;

/**
 * Controller de indicacoes por consultas online
 *
 * Gerencia indicacoes de real infrator e principal condutor
 * enviadas via API de consultas online.
 */
class SerproIndicacaoController
{
    /**
     * Renderiza pagina de indicacoes
     *
     * GET /pages/multas-online/indicacoes
     */
    public function view(Request $request): void
    {
        $html = Template::render('pages.multas.indicacao');
        Response::html($html);
    }

    /**
     * Lista indicacoes com paginacao e filtros
     *
     * GET /api/multas-online/indicacoes
     */
    public function index(Request $request): void
    {
        try {
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('perPage', 15)));
            $filtroTipo = $request->query('tipo', '');
            $filtroStatus = $request->query('status', '') ?: null;
            $filtroPlaca = $request->query('placa', '') ?: null;

            $model = new SerproIndicacao();
            $indicacoes = $model->listarPaginado($page, $perPage, $filtroTipo, $filtroStatus, $filtroPlaca);
            $total = $model->contar($filtroTipo, $filtroStatus, $filtroPlaca);

            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

            Response::json([
                'success' => true,
                'data' => $indicacoes,
                'pagination' => [
                    'page' => $page,
                    'perPage' => $perPage,
                    'total' => $total,
                    'totalPages' => $totalPages,
                    'hasPrev' => $page > 1,
                    'hasNext' => $page < $totalPages,
                ],
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao listar indicacoes: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Busca indicacao por ID com dados completos
     *
     * GET /api/multas-online/indicacoes/{id}
     */
    public function show(Request $request, int $id): void
    {
        try {
            $model = new SerproIndicacao();
            $indicacao = $model->buscarPorId($id);

            if (!$indicacao) {
                Response::json(['success' => false, 'message' => 'Indicacao nao encontrada'], 404);
                return;
            }

            Response::json(['success' => true, 'data' => $indicacao]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar indicacao: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resumo de indicacoes (para dashboard)
     *
     * GET /api/multas-online/indicacoes/resumo
     */
    public function resumo(Request $request): void
    {
        try {
            $model = new SerproIndicacao();
            $resumo = $model->resumo();

            Response::json(['success' => true, 'data' => $resumo]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao carregar resumo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Indica real infrator para uma infracao
     *
     * POST /multas-online/indicacoes/real-infrator
     * Body: id_multa, cpf_indicado, nome_indicado (opcional)
     */
    public function indicarRealInfrator(Request $request): void
    {
        try {
            $idMulta = (int) $request->input('id_multa', 0);
            $cpfIndicado = $request->input('cpf_indicado', '');
            $nomeIndicado = $request->input('nome_indicado', '');

            if (empty($idMulta) || empty($cpfIndicado)) {
                Response::json([
                    'success' => false,
                    'message' => 'Multa e CPF do indicado sao obrigatorios',
                ], 422);
                return;
            }

            // Buscar multa
            $multaModel = new Multa();
            $multa = $multaModel->buscarPorId($idMulta);

            if (!$multa) {
                Response::json(['success' => false, 'message' => 'Multa nao encontrada'], 404);
                return;
            }

            if (empty($multa['codigo_orgao']) || empty($multa['numero_ait']) || empty($multa['codigo_infracao'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Multa nao possui identificadores da consulta online (codigo_orgao, numero_ait, codigo_infracao). Apenas multas importadas via consulta online podem ter indicacao.',
                ], 422);
                return;
            }

            if (!ctype_digit((string) $multa['codigo_orgao']) || !ctype_digit((string) $multa['codigo_infracao'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Multa nao possui identificadores oficiais validos da consulta online. Consulte a placa novamente antes de indicar real infrator.',
                ], 422);
                return;
            }

            $config = $this->obterConfiguracaoComCnpj();

            if (!$config || empty($config['cnpj_empresa'])) {
                Response::json([
                    'success' => false,
                    'message' => 'CNPJ da empresa nao configurado. Acesse as configuracoes de consulta online para cadastrar.',
                ], 422);
                return;
            }

            $saldoService = new SerproSaldoService();
            if (!$saldoService->temSaldoParaIndicacoes(1)) {
                Response::json([
                    'success' => false,
                    'message' => 'Saldo insuficiente para indicacao. Saldo atual: R$ ' . number_format($saldoService->getSaldo(), 2, ',', '.'),
                    'saldo_insuficiente' => true,
                ], 402);
                return;
            }

            // Enviar para SERPRO
            $serpro = new SerproService();
            $resultado = $serpro->indicarRealInfrator([
                'codigo_orgao' => $multa['codigo_orgao'],
                'numero_ait' => $multa['numero_ait'],
                'codigo_infracao' => $multa['codigo_infracao'],
                'cnpj_indicante' => $config['cnpj_empresa'],
                'cpf_indicado' => $cpfIndicado,
            ]);

            if (!$resultado['success']) {
                Response::json([
                    'success' => false,
                    'message' => $resultado['error'] ?? 'Erro ao enviar indicacao para o sistema de consultas online',
                ], 422);
                return;
            }

            // Registrar indicacao no BD
            $indicacaoModel = new SerproIndicacao();
            $chaveIndicacao = $resultado['data']['chaveIndicacao'] ?? $resultado['data']['chave'] ?? null;

            $indicacaoId = $indicacaoModel->criar([
                'tipo' => 'real_infrator',
                'id_multa' => $idMulta,
                'id_veiculo' => $multa['id_veiculo'] ?? null,
                'id_cliente' => $multa['id_cliente'] ?? null,
                'id_contrato' => $multa['id_contrato'] ?? null,
                'id_locacao' => $multa['id_locacao'] ?? null,
                'placa' => $multa['veiculo_placa'] ?? '',
                'codigo_orgao' => $multa['codigo_orgao'],
                'numero_ait' => $multa['numero_ait'],
                'codigo_infracao' => $multa['codigo_infracao'],
                'cpf_indicado' => $cpfIndicado,
                'nome_indicado' => $nomeIndicado,
                'chave_indicacao' => $chaveIndicacao,
                'status_serpro' => 'enviado',
            ]);

            // Atualizar status da multa
            $multaModel->atualizarDadosSerpro($idMulta, [
                'status_processamento' => 'indicacao_enviada',
            ]);

            $placaMulta = $multa['veiculo_placa'] ?? '';
            $debito = $saldoService->debitarIndicacao(
                "Indicacao de real infrator placa {$placaMulta}",
                $chaveIndicacao ?: ($placaMulta ?: null)
            );

            Response::json([
                'success' => true,
                'data' => [
                    'indicacao_id' => $indicacaoId,
                    'chave_indicacao' => $chaveIndicacao,
                    'saldo_posterior' => $debito['saldo_posterior'],
                ],
                'message' => 'Indicacao de real infrator enviada com sucesso.',
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro na indicacao: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Indica principal condutor para um veiculo
     *
     * POST /multas-online/indicacoes/principal-condutor
     * Body: placa, cpf_indicado, cnh_indicado, nome_indicado (opcional)
     */
    public function indicarPrincipalCondutor(Request $request): void
    {
        try {
            $placa = strtoupper(str_replace(['-', ' '], '', trim($request->input('placa', ''))));
            $cpfIndicado = $request->input('cpf_indicado', '');
            $cnhIndicado = $request->input('cnh_indicado', '');
            $nomeIndicado = $request->input('nome_indicado', '');
            $idVeiculo = (int) $request->input('id_veiculo', 0);
            $idCliente = (int) $request->input('id_cliente', 0);

            if (empty($placa) || empty($cpfIndicado) || empty($cnhIndicado)) {
                Response::json([
                    'success' => false,
                    'message' => 'Placa, CPF e CNH do condutor sao obrigatorios',
                ], 422);
                return;
            }

            if (empty($idVeiculo)) {
                $veiculoModel = new Veiculo();
                $veiculo = $veiculoModel->buscarPorPlaca($placa);
                if ($veiculo) {
                    $idVeiculo = (int) $veiculo['id'];
                }
            }

            $config = $this->obterConfiguracaoComCnpj();

            if (!$config || empty($config['cnpj_empresa'])) {
                Response::json([
                    'success' => false,
                    'message' => 'CNPJ da empresa nao configurado. Acesse as configuracoes de consulta online para cadastrar.',
                ], 422);
                return;
            }

            $saldoService = new SerproSaldoService();
            if (!$saldoService->temSaldoParaIndicacoes(1)) {
                Response::json([
                    'success' => false,
                    'message' => 'Saldo insuficiente para indicacao. Saldo atual: R$ ' . number_format($saldoService->getSaldo(), 2, ',', '.'),
                    'saldo_insuficiente' => true,
                ], 402);
                return;
            }

            // Enviar para SERPRO
            $serpro = new SerproService();
            $resultado = $serpro->indicarPrincipalCondutor([
                'cnpj_indicante' => $config['cnpj_empresa'],
                'placa' => $placa,
                'cpf_indicado' => $cpfIndicado,
                'cnh_indicado' => $cnhIndicado,
            ]);

            if (!$resultado['success']) {
                Response::json([
                    'success' => false,
                    'message' => $resultado['error'] ?? 'Erro ao enviar indicacao para o sistema de consultas online',
                ], 422);
                return;
            }

            // Registrar indicacao no BD
            $indicacaoModel = new SerproIndicacao();
            $chaveIndicacao = $resultado['data']['chaveIndicacao'] ?? $resultado['data']['chave'] ?? null;

            $indicacaoId = $indicacaoModel->criar([
                'tipo' => 'principal_condutor',
                'placa' => $placa,
                'id_veiculo' => $idVeiculo ?: null,
                'id_cliente' => $idCliente ?: null,
                'cpf_indicado' => $cpfIndicado,
                'nome_indicado' => $nomeIndicado,
                'cnh_indicado' => $cnhIndicado,
                'chave_indicacao' => $chaveIndicacao,
                'status_serpro' => 'enviado',
            ]);

            $debito = $saldoService->debitarIndicacao(
                "Indicacao de principal condutor placa {$placa}",
                $chaveIndicacao ?: $placa
            );

            Response::json([
                'success' => true,
                'data' => [
                    'indicacao_id' => $indicacaoId,
                    'chave_indicacao' => $chaveIndicacao,
                    'saldo_posterior' => $debito['saldo_posterior'],
                ],
                'message' => 'Indicacao de principal condutor enviada com sucesso.',
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro na indicacao: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Consulta status de uma indicacao na SERPRO
     *
     * GET /api/multas-online/indicacoes/{id}/status
     */
    public function consultarStatus(Request $request, int $id): void
    {
        try {
            $indicacaoModel = new SerproIndicacao();
            $indicacao = $indicacaoModel->buscarPorId($id);

            if (!$indicacao) {
                Response::json(['success' => false, 'message' => 'Indicacao nao encontrada'], 404);
                return;
            }

            if (empty($indicacao['chave_indicacao'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Indicacao sem identificador da consulta online',
                ], 422);
                return;
            }

            $statusService = new SerproIndicacaoStatusService();
            $resultado = $statusService->sincronizar($indicacao);

            if (!$resultado['success']) {
                Response::json([
                    'success' => false,
                    'message' => $resultado['message'] ?? 'Erro ao consultar status no sistema de consultas online',
                ], 422);
                return;
            }

            Response::json([
                'success' => true,
                'data' => $resultado['data'],
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao consultar status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancela indicacao de real infrator
     *
     * POST /multas-online/indicacoes/{id}/cancelar
     */
    public function cancelar(Request $request, int $id): void
    {
        try {
            $indicacaoModel = new SerproIndicacao();
            $indicacao = $indicacaoModel->buscarPorId($id);

            if (!$indicacao) {
                Response::json(['success' => false, 'message' => 'Indicacao nao encontrada'], 404);
                return;
            }

            if ($indicacao['tipo'] !== 'real_infrator') {
                // Principal condutor usa excluir, nao cancelar
                $this->excluirPrincipalCondutor($indicacao);
                return;
            }

            if (empty($indicacao['chave_indicacao'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Indicacao sem identificador da consulta online',
                ], 422);
                return;
            }

            $configModel = new SerproConfiguracao();
            $config = $configModel->buscarPorChave();

            $serpro = new SerproService();
            $resultado = $serpro->cancelarRealInfrator($indicacao['chave_indicacao'], [
                'codigo_orgao' => $indicacao['codigo_orgao'],
                'numero_ait' => $indicacao['numero_ait'],
                'codigo_infracao' => $indicacao['codigo_infracao'],
                'cnpj_indicante' => $config['cnpj_empresa'] ?? '',
            ]);

            if (!$resultado['success']) {
                Response::json([
                    'success' => false,
                    'message' => $resultado['error'] ?? 'Erro ao cancelar indicacao no sistema de consultas online',
                ], 422);
                return;
            }

            $indicacaoModel->atualizarStatus($id, [
                'status_serpro' => 'cancelado',
                'data_resposta' => date('Y-m-d H:i:s'),
            ]);

            // Reverter status da multa
            if (!empty($indicacao['id_multa'])) {
                $multaModel = new Multa();
                $multaModel->atualizarDadosSerpro((int) $indicacao['id_multa'], [
                    'status_processamento' => 'novo',
                ]);
            }

            Response::json([
                'success' => true,
                'message' => 'Indicacao cancelada com sucesso.',
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao cancelar indicacao: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================================
    // METODOS PRIVADOS
    // =========================================================================

    /**
     * Busca configuracao SERPRO e tenta preencher CNPJ pelo cadastro da empresa.
     */
    private function obterConfiguracaoComCnpj(): ?array
    {
        $configModel = new SerproConfiguracao();
        $config = $configModel->buscarPorChave();

        if ($config && !empty($config['cnpj_empresa'])) {
            return $config;
        }

        $resultado = $this->resolverCnpjConsultaOnline();
        if (!$resultado['success']) {
            return $config;
        }

        $configModel->salvar(['cnpj_empresa' => $resultado['cnpj']]);

        return $configModel->buscarPorChave();
    }

    /**
     * Retorna o CNPJ que deve ser usado pela Consulta Online.
     */
    private function resolverCnpjConsultaOnline(): array
    {
        $model = new MatrizFilial();
        $matriz = $model->buscarMatriz();
        $cnpjMatriz = preg_replace('/\D/', '', (string) ($matriz['cpf_cnpj'] ?? ''));

        if ($this->cnpjSerproValido($cnpjMatriz)) {
            return ['success' => true, 'cnpj' => $cnpjMatriz];
        }

        $empresas = $model->listar(null, [], 'tipo DESC, razao_social ASC');
        $cnpjsValidos = [];

        foreach ($empresas as $empresa) {
            $cnpj = preg_replace('/\D/', '', (string) ($empresa['cpf_cnpj'] ?? ''));
            if ($this->cnpjSerproValido($cnpj)) {
                $cnpjsValidos[$cnpj] = $empresa;
            }
        }

        if (count($cnpjsValidos) === 1) {
            return ['success' => true, 'cnpj' => array_key_first($cnpjsValidos)];
        }

        return ['success' => false];
    }

    /**
     * Valida formato minimo de CNPJ para envio a SERPRO.
     */
    private function cnpjSerproValido(string $cnpj): bool
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);

        return strlen($cnpj) === 14 && !preg_match('/^(\d)\1{13}$/', $cnpj);
    }

    /**
     * Exclui indicacao de principal condutor na SERPRO
     */
    private function excluirPrincipalCondutor(array $indicacao): void
    {
        $configModel = new SerproConfiguracao();
        $config = $configModel->buscarPorChave();

        $serpro = new SerproService();
        $resultado = $serpro->excluirPrincipalCondutor([
            'cnpj_indicante' => $config['cnpj_empresa'] ?? '',
            'chave_indicacao' => $indicacao['chave_indicacao'],
            'cpf_indicado' => $indicacao['cpf_indicado'],
            'placa' => $indicacao['placa'],
        ]);

        if (!$resultado['success']) {
            Response::json([
                'success' => false,
                'message' => $resultado['error'] ?? 'Erro ao excluir indicacao no sistema de consultas online',
            ], 422);
            return;
        }

        $indicacaoModel = new SerproIndicacao();
        $indicacaoModel->atualizarStatus((int) $indicacao['id'], [
            'status_serpro' => 'excluido',
            'data_resposta' => date('Y-m-d H:i:s'),
        ]);

        Response::json([
            'success' => true,
            'message' => 'Indicacao de principal condutor excluida com sucesso.',
        ]);
    }

}
