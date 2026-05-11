<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\SerproIndicacao;
use App\Models\SerproConfiguracao;
use App\Models\Multa;
use App\Services\SerproService;

/**
 * Controller de Indicacoes SERPRO eFrotas
 *
 * Gerencia indicacoes de real infrator e principal condutor
 * enviadas via API SERPRO.
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
                    'message' => 'Multa nao possui chaves SERPRO (codigo_orgao, numero_ait, codigo_infracao). Apenas multas importadas da SERPRO podem ter indicacao.',
                ], 422);
                return;
            }

            // Buscar CNPJ do tenant
            $configModel = new SerproConfiguracao();
            $config = $configModel->buscarPorChave();

            if (!$config || empty($config['cnpj_empresa'])) {
                Response::json([
                    'success' => false,
                    'message' => 'CNPJ da empresa nao configurado. Acesse Configuracoes SERPRO para cadastrar.',
                ], 422);
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
                    'message' => $resultado['error'] ?? 'Erro ao enviar indicacao para SERPRO',
                ], 502);
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

            Response::json([
                'success' => true,
                'data' => [
                    'indicacao_id' => $indicacaoId,
                    'chave_indicacao' => $chaveIndicacao,
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
            $placa = strtoupper(trim($request->input('placa', '')));
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

            // Buscar CNPJ do tenant
            $configModel = new SerproConfiguracao();
            $config = $configModel->buscarPorChave();

            if (!$config || empty($config['cnpj_empresa'])) {
                Response::json([
                    'success' => false,
                    'message' => 'CNPJ da empresa nao configurado. Acesse Configuracoes SERPRO para cadastrar.',
                ], 422);
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
                    'message' => $resultado['error'] ?? 'Erro ao enviar indicacao para SERPRO',
                ], 502);
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

            Response::json([
                'success' => true,
                'data' => [
                    'indicacao_id' => $indicacaoId,
                    'chave_indicacao' => $chaveIndicacao,
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
                    'message' => 'Indicacao sem chave SERPRO',
                ], 422);
                return;
            }

            $serpro = new SerproService();

            if ($indicacao['tipo'] === 'real_infrator') {
                $resultado = $serpro->statusRealInfrator($indicacao['chave_indicacao']);
            } else {
                $resultado = $serpro->statusPrincipalCondutor($indicacao['chave_indicacao']);
            }

            if (!$resultado['success']) {
                Response::json([
                    'success' => false,
                    'message' => $resultado['error'] ?? 'Erro ao consultar status na SERPRO',
                ], 502);
                return;
            }

            // Atualizar status local
            $statusSerpro = $resultado['data']['status'] ?? $resultado['data']['situacao'] ?? null;
            $dadosUpdate = [];

            if ($statusSerpro) {
                $statusNormalizado = $this->normalizarStatusSerpro($statusSerpro);
                $dadosUpdate['status_serpro'] = $statusNormalizado;
            }

            if (!empty($resultado['data']['motivoRejeicao'])) {
                $dadosUpdate['motivo_rejeicao'] = $resultado['data']['motivoRejeicao'];
            }

            if (!empty($resultado['data']['dataResposta'])) {
                $dadosUpdate['data_resposta'] = $resultado['data']['dataResposta'];
            }

            if (!empty($dadosUpdate)) {
                $indicacaoModel->atualizarStatus($id, $dadosUpdate);

                // Atualizar status da multa se indicacao de real infrator
                if ($indicacao['tipo'] === 'real_infrator' && !empty($indicacao['id_multa'])) {
                    $multaModel = new Multa();
                    $statusMulta = $this->mapStatusParaMulta($dadosUpdate['status_serpro'] ?? '');
                    if ($statusMulta) {
                        $multaModel->atualizarDadosSerpro((int) $indicacao['id_multa'], [
                            'status_processamento' => $statusMulta,
                        ]);
                    }
                }
            }

            Response::json([
                'success' => true,
                'data' => [
                    'status_serpro' => $resultado['data'],
                    'status_local' => $dadosUpdate['status_serpro'] ?? $indicacao['status_serpro'],
                ],
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
                    'message' => 'Indicacao sem chave SERPRO',
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
                    'message' => $resultado['error'] ?? 'Erro ao cancelar indicacao na SERPRO',
                ], 502);
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
                'message' => $resultado['error'] ?? 'Erro ao excluir indicacao na SERPRO',
            ], 502);
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

    /**
     * Normaliza status retornado pela SERPRO
     */
    private function normalizarStatusSerpro(string $status): string
    {
        $status = strtolower(trim($status));

        $mapa = [
            'aceita' => 'aceito',
            'aceito' => 'aceito',
            'aprovada' => 'aceito',
            'rejeitada' => 'rejeitado',
            'rejeitado' => 'rejeitado',
            'negada' => 'rejeitado',
            'processando' => 'processando',
            'em_processamento' => 'processando',
            'enviada' => 'enviado',
            'enviado' => 'enviado',
            'cancelada' => 'cancelado',
            'cancelado' => 'cancelado',
            'excluida' => 'excluido',
            'excluido' => 'excluido',
            'expirada' => 'expirado',
            'expirado' => 'expirado',
        ];

        return $mapa[$status] ?? $status;
    }

    /**
     * Mapeia status da indicacao para status da multa
     */
    private function mapStatusParaMulta(string $statusIndicacao): ?string
    {
        $mapa = [
            'aceito' => 'indicacao_aceita',
            'rejeitado' => 'indicacao_rejeitada',
            'enviado' => 'indicacao_enviada',
            'processando' => 'indicacao_enviada',
            'cancelado' => 'novo',
        ];

        return $mapa[$statusIndicacao] ?? null;
    }
}
