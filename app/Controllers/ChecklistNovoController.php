<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Models\Checklist;
use App\Models\Veiculo;
use App\Helpers\FileHelper;
use App\Helpers\FilialHelper;
use App\Helpers\ImageHelper;
use App\Services\AuditLogService;

/**
 * Controller para criacao de checklists digitais via dispositivo movel
 *
 * Gerencia a pagina standalone mobile e os endpoints da API
 * para criacao, preenchimento e finalizacao de checklists.
 */
class ChecklistNovoController
{
    /**
     * Planos que permitem checklist digital
     */
    private const PLANOS_PERMITIDOS = ['P3', 'P4'];

    /**
     * Verifica se o plano do usuario permite checklist digital
     */
    private function planoPermitido(): bool
    {
        $plano = Auth::user()['plano'] ?? 'G';
        return in_array($plano, self::PLANOS_PERMITIDOS, true);
    }

    /**
     * Verifica se o checklist pertence ao tenant e esta pendente
     *
     * @return array|null Dados do checklist ou null se invalido
     */
    private function validarChecklist(int $id, bool $exigirPendente = true, ?string $etapa = null): ?array
    {
        $chave = Auth::chave();
        $model = new Checklist();
        $checklist = $model->buscarPorIdCompleto($id, $chave);

        if (!$checklist) {
            Response::json([
                'success' => false,
                'message' => 'Checklist nao encontrado'
            ], 404);
            return null;
        }

        $etapaAtual = $model->etapaAtual($checklist, $etapa);
        if ($exigirPendente && $model->etapaFinalizada($checklist, $etapaAtual)) {
            Response::json([
                'success' => false,
                'message' => 'Esta etapa do checklist ja foi finalizada'
            ], 422);
            return null;
        }

        return $checklist;
    }

    /**
     * Renderiza a listagem mobile standalone de checklists
     *
     * GET /checklists/digital
     */
    public function viewDigital(Request $request): void
    {
        if (!Auth::can('checklists.criar')) {
            Response::html('<h1>Acesso negado</h1>', 403);
            return;
        }

        $user = Auth::user();
        $csrfToken = $_SESSION['csrf_token'] ?? '';
        $temDashboard = Auth::can('dashboard.visualizar');

        ob_start();
        $viewPath = __DIR__ . '/../Views/pages/checklists/digital.php';
        extract([
            'csrf_token' => $csrfToken,
            'user_name' => $user['name'] ?? '',
            'tem_dashboard' => $temDashboard,
        ]);
        include $viewPath;
        $html = ob_get_clean();

        Response::html($html);
    }

    /**
     * Renderiza a lista mobile de checklists vinculados pendentes.
     */
    public function viewVinculados(Request $request): void
    {
        if (!Auth::can('checklists.criar')) {
            Response::html('<h1>Acesso negado</h1>', 403);
            return;
        }

        $csrfToken = $_SESSION['csrf_token'] ?? '';
        $temDashboard = Auth::can('dashboard.visualizar');

        ob_start();
        $viewPath = __DIR__ . '/../Views/pages/checklists/vinculados.php';
        extract([
            'csrf_token' => $csrfToken,
            'tem_dashboard' => $temDashboard,
        ]);
        include $viewPath;
        $html = ob_get_clean();

        Response::html($html);
    }

    /**
     * Renderiza a pagina standalone mobile de visualizacao de checklist
     *
     * GET /checklists/visualizar/{id}
     */
    public function viewVisualizar(Request $request, int $id): void
    {
        if (!Auth::can('checklists.criar')) {
            Response::html('<h1>Acesso negado</h1>', 403);
            return;
        }

        $chave = Auth::chave();
        $model = new Checklist();
        $checklist = $model->buscarPorId($id);

        if (!$checklist || $checklist['chave'] !== $chave) {
            Response::html('<h1>Checklist nao encontrado</h1>', 404);
            return;
        }

        // Decodificar dados
        $questoesSaida = json_decode($checklist['questoes_saida'] ?? '[]', true) ?: [];
        $questoesChegada = json_decode($checklist['questoes_entrada'] ?? '[]', true) ?: [];

        $vistoriaSaida = json_decode($checklist['vistoria_saida'] ?? '[]', true) ?: [];
        $vistoriaChegada = json_decode($checklist['vistoria_entrada'] ?? '[]', true) ?: [];

        // Resolver URLs das fotos
        foreach ($vistoriaSaida as &$item) {
            if (!empty($item['img'])) $item['img_url'] = FileHelper::url($item['img'], $chave);
        }
        foreach ($vistoriaChegada as &$item) {
            if (!empty($item['img'])) $item['img_url'] = FileHelper::url($item['img'], $chave);
        }

        $temDashboard = Auth::can('dashboard.visualizar');

        ob_start();
        $viewPath = __DIR__ . '/../Views/pages/checklists/visualizar.php';
        extract([
            'checklist' => $checklist,
            'isVinculado' => ($checklist['tipo'] ?? '') === 'V',
            'questoesSaida' => $questoesSaida,
            'questoesChegada' => $questoesChegada,
            'vistoriaSaida' => $vistoriaSaida,
            'vistoriaChegada' => $vistoriaChegada,
            'dataSaida' => $checklist['data_saida'] ?? null,
            'dataChegada' => $checklist['data_entrada'] ?? null,
            'tem_dashboard' => $temDashboard,
        ]);
        include $viewPath;
        $html = ob_get_clean();

        Response::html($html);
    }

    /**
     * Renderiza a pagina standalone mobile para criacao de checklist
     *
     * GET /checklists/novo
     */
    public function viewNovo(Request $request): void
    {
        if (!Auth::can('checklists.criar')) {
            Response::html('<h1>Acesso negado</h1>', 403);
            return;
        }

        if (!$this->planoPermitido()) {
            Response::html('<h1>Recurso nao disponivel para seu plano</h1>', 403);
            return;
        }

        $user = Auth::user();
        $csrfToken = $_SESSION['csrf_token'] ?? '';
        $retomarId = $request->query('retomar', '');
        $tipo = $request->query('tipo', '');
        $etapa = $request->query('etapa', '');
        $vinculo = $request->query('vinculo', '');
        $idVeiculo = $request->query('id_veiculo', '');
        $temDashboard = Auth::can('dashboard.visualizar');
        $vinculoResolvido = null;
        $vinculoErro = null;
        $retomarIdResolvido = $retomarId && ctype_digit((string) $retomarId) ? (int) $retomarId : null;

        if (!$retomarIdResolvido && trim((string) $retomarId) !== '') {
            $tipo = 'V';
            $vinculo = (string) $retomarId;
        }

        if ($tipo === 'V' && trim($vinculo) !== '') {
            try {
                $model = new Checklist();
                [$filialWhereLoc, $filialParamsLoc] = FilialHelper::whereFiliais('l.id_matriz_filial_retirada');
                [$filialWhereCt, $filialParamsCt] = FilialHelper::whereFiliais('ct.id_matriz_filial_retirada');
                $vinculoResolvido = $model->resolverVinculoPorCodigo(
                    $vinculo,
                    $filialWhereLoc,
                    $filialParamsLoc,
                    $filialWhereCt,
                    $filialParamsCt
                );
                if (!$vinculoResolvido) {
                    $vinculoErro = 'Locacao ou contrato nao encontrado';
                } elseif (!$retomarIdResolvido && trim((string) $retomarId) !== '') {
                    $idVeiculoBusca = $idVeiculo ? (int) $idVeiculo : (int) ($vinculoResolvido['id_veiculo'] ?? 0);
                    $checklistAberto = $model->buscarChecklistVinculadoAberto(
                        ($vinculoResolvido['tipo_vinculo'] ?? '') === 'L' ? (int) $vinculoResolvido['id_vinculo'] : null,
                        ($vinculoResolvido['tipo_vinculo'] ?? '') === 'C' ? (int) $vinculoResolvido['id_vinculo'] : null,
                        $idVeiculoBusca
                    );
                    if ($checklistAberto) {
                        if ($etapa === 'entrada') {
                            $model->iniciarEntrada((int) $checklistAberto['id']);
                        }
                        $retomarIdResolvido = (int) $checklistAberto['id'];
                    } else {
                        $vinculoErro = 'Checklist vinculado pendente nao encontrado';
                    }
                }
            } catch (\Exception $e) {
                $vinculoErro = $e->getMessage();
            }
        }

        ob_start();
        $viewPath = __DIR__ . '/../Views/pages/checklists/novo.php';
        extract([
            'csrf_token' => $csrfToken,
            'user_name' => $user['name'] ?? '',
            'plano' => $user['plano'] ?? 'G',
            'retomar_id' => $retomarIdResolvido,
            'tipo_inicial' => in_array($tipo, ['A', 'V'], true) ? $tipo : 'A',
            'etapa_inicial' => in_array($etapa, ['saida', 'entrada'], true) ? $etapa : 'saida',
            'vinculo_inicial' => $vinculo,
            'vinculo_resolvido' => $vinculoResolvido,
            'vinculo_erro' => $vinculoErro,
            'id_veiculo_inicial' => $idVeiculo ? (int) $idVeiculo : null,
            'tem_dashboard' => $temDashboard,
        ]);
        include $viewPath;
        $html = ob_get_clean();

        Response::html($html);
    }

    /**
     * Cria um novo checklist (aba Infor)
     *
     * POST /api/checklists/criar
     */
    public function criar(Request $request): void
    {
        try {
            if (!Auth::can('checklists.criar')) {
                Response::json(['success' => false, 'message' => 'Sem permissao'], 403);
                return;
            }

            if (!$this->planoPermitido()) {
                Response::json(['success' => false, 'message' => 'Recurso nao disponivel para seu plano'], 403);
                return;
            }

            $chave = Auth::chave();
            $user = Auth::user();

            $tipo = $request->input('tipo');
            $etapa = $request->input('etapa', 'saida');
            $idModelo = (int) $request->input('id_modelo');
            $idVeiculo = $request->input('id_veiculo') ? (int) $request->input('id_veiculo') : null;
            $idLocacao = $request->input('id_locacao') ? (int) $request->input('id_locacao') : null;
            $idContrato = $request->input('id_contrato') ? (int) $request->input('id_contrato') : null;
            $vinculoCodigo = trim((string) $request->input('vinculo_codigo', ''));
            $codigoVinculo = null;
            $obs = $request->input('obs', '');
            $model = new Checklist();
            $checklistVinculadoAberto = null;

            // Validacoes
            if (!in_array($tipo, ['V', 'A'], true)) {
                Response::json(['success' => false, 'message' => 'Tipo invalido'], 422);
                return;
            }

            if ($tipo === 'V') {
                if ($vinculoCodigo !== '') {
                    [$filialWhereLoc, $filialParamsLoc] = FilialHelper::whereFiliais('l.id_matriz_filial_retirada');
                    [$filialWhereCt, $filialParamsCt] = FilialHelper::whereFiliais('ct.id_matriz_filial_retirada');
                    $vinculoResolvido = $model->resolverVinculoPorCodigo(
                        $vinculoCodigo,
                        $filialWhereLoc,
                        $filialParamsLoc,
                        $filialWhereCt,
                        $filialParamsCt
                    );

                    if (!$vinculoResolvido) {
                        Response::json(['success' => false, 'message' => 'Locacao ou contrato nao encontrado'], 422);
                        return;
                    }

                    $codigoVinculo = (string) ($vinculoResolvido['codigo'] ?? $vinculoCodigo);
                    $idLocacao = null;
                    $idContrato = null;

                    if (($vinculoResolvido['tipo_vinculo'] ?? '') === 'L') {
                        $idLocacao = (int) $vinculoResolvido['id_vinculo'];
                    } else {
                        $idContrato = (int) $vinculoResolvido['id_vinculo'];
                    }

                    if (!$idVeiculo && !empty($vinculoResolvido['id_veiculo'])) {
                        $idVeiculo = (int) $vinculoResolvido['id_veiculo'];
                    }
                }

                if (!$idLocacao && !$idContrato) {
                    Response::json(['success' => false, 'message' => 'Selecione uma locacao ou contrato'], 422);
                    return;
                }
                if (!in_array($etapa, ['saida', 'entrada'], true)) {
                    Response::json(['success' => false, 'message' => 'Etapa invalida'], 422);
                    return;
                }
            } else {
                $etapa = 'saida';
                $idLocacao = null;
                $idContrato = null;
            }

            if (!$idVeiculo) {
                Response::json(['success' => false, 'message' => 'Selecione um veiculo'], 422);
                return;
            }

            if ($tipo === 'V') {
                $checklistVinculadoAberto = $model->buscarChecklistVinculadoAberto($idLocacao, $idContrato, $idVeiculo);
                if ($etapa === 'entrada' && !$idModelo && $checklistVinculadoAberto && !empty($checklistVinculadoAberto['id_modelo'])) {
                    $idModelo = (int) $checklistVinculadoAberto['id_modelo'];
                }
            }

            if (!$idModelo) {
                Response::json(['success' => false, 'message' => 'Selecione um modelo de checklist'], 422);
                return;
            }

            if ($tipo === 'V' && $codigoVinculo === null) {
                $codigoVinculo = $model->buscarCodigoVinculo($idLocacao, $idContrato);
                if (!$codigoVinculo) {
                    Response::json(['success' => false, 'message' => 'Codigo do vinculo nao encontrado'], 422);
                    return;
                }
            }

            if ($tipo === 'V') {
                $aberto = $checklistVinculadoAberto;
                if ($aberto) {
                    if ($etapa === 'entrada') {
                        $model->iniciarEntrada((int) $aberto['id']);
                    }

                    Response::json([
                        'success' => true,
                        'id' => (int) $aberto['id'],
                        'codigo' => $aberto['codigo'],
                        'retomar' => true,
                    ]);
                    return;
                }
            }

            $codigo = $tipo === 'V'
                ? $codigoVinculo
                : $model->gerarCodigo($chave);
            $statusInicial = $tipo === 'V'
                ? Checklist::STATUS_VINCULADO_SAIDA_INICIADO
                : Checklist::STATUS_AVULSO_INICIADO;

            $id = $model->criar([
                'chave' => $chave,
                'tipo' => $tipo,
                'codigo' => $codigo,
                'id_modelo' => $idModelo,
                'id_veiculo' => $idVeiculo,
                'id_locacao' => $idLocacao,
                'id_contrato' => $idContrato,
                'observacoes_' . $etapa => $obs,
                'id_funcionario' => $user['id'] ?? null,
                'status' => $statusInicial,
            ]);

            AuditLogService::registrar(
                ($user['name'] ?? 'Sistema') . ", criou checklist [{$codigo}]"
            );

            Response::json([
                'success' => true,
                'id' => $id,
                'codigo' => $codigo,
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao criar checklist: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Salva respostas do questionario (aba Questoes)
     *
     * POST /api/checklists/{id}/questoes
     */
    public function salvarQuestoes(Request $request, int $id): void
    {
        try {
            if (!Auth::can('checklists.criar')) {
                Response::json(['success' => false, 'message' => 'Sem permissao'], 403);
                return;
            }

            $etapa = $request->input('etapa', 'saida');
            $checklist = $this->validarChecklist($id, true, $etapa);
            if (!$checklist) return;

            $questoes = $request->input('questoes');
            if (!$questoes || !is_array($questoes)) {
                Response::json(['success' => false, 'message' => 'Dados de questoes invalidos'], 422);
                return;
            }

            $model = new Checklist();
            $etapaAtual = $model->etapaAtual($checklist, $etapa);
            $model->atualizarQuestoes($id, json_encode($questoes, JSON_UNESCAPED_UNICODE), $etapaAtual);

            Response::json(['success' => true]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao salvar questoes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload de foto da vistoria (aba Vistorias)
     *
     * POST /api/checklists/{id}/vistoria/upload
     */
    public function uploadVistoria(Request $request, int $id): void
    {
        try {
            if (!Auth::can('checklists.criar')) {
                Response::json(['success' => false, 'message' => 'Sem permissao'], 403);
                return;
            }

            $etapa = $request->input('etapa', 'saida');
            $checklist = $this->validarChecklist($id, true, $etapa);
            if (!$checklist) return;

            $itemId = $request->input('item_id');
            $foto = $request->input('foto');

            if (!$itemId || !$foto) {
                Response::json(['success' => false, 'message' => 'Dados incompletos'], 422);
                return;
            }

            // Salvar foto como WebP
            $chave = Auth::chave();
            $filename = ImageHelper::save($foto, 'vistoria', 'webp', 80, $chave);

            if (!$filename) {
                Response::json(['success' => false, 'message' => 'Erro ao salvar foto'], 500);
                return;
            }

            // Atualizar JSON da vistoria com o novo filename
            $model = new Checklist();
            $etapaAtual = $model->etapaAtual($checklist, $etapa);
            $vistoria = json_decode($checklist['vistoria_' . $etapaAtual] ?? '[]', true) ?: [];

            // Buscar template do modelo se vistoria esta vazia
            if (empty($vistoria)) {
                $vistoria = json_decode($checklist['modelo_vistoria'] ?? '[]', true) ?: [];
            }

            $found = false;
            foreach ($vistoria as &$item) {
                if ((string) ($item['id'] ?? '') === (string) $itemId) {
                    $item['img'] = $filename;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                // Item nao encontrado no template, adicionar
                $vistoria[] = ['id' => $itemId, 'img' => $filename];
            }

            $model->atualizarVistoria($id, json_encode($vistoria, JSON_UNESCAPED_UNICODE), $etapaAtual);

            Response::json([
                'success' => true,
                'filename' => $filename,
                'url' => FileHelper::url($filename, $chave),
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao enviar foto: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove foto da vistoria
     *
     * POST /api/checklists/{id}/vistoria/{itemId}/excluir
     */
    public function excluirVistoria(Request $request, int $id, string $itemId): void
    {
        try {
            if (!Auth::can('checklists.criar')) {
                Response::json(['success' => false, 'message' => 'Sem permissao'], 403);
                return;
            }

            $etapa = $request->input('etapa', 'saida');
            $checklist = $this->validarChecklist($id, true, $etapa);
            if (!$checklist) return;

            $chave = Auth::chave();
            $model = new Checklist();
            $etapaAtual = $model->etapaAtual($checklist, $etapa);
            $vistoria = json_decode($checklist['vistoria_' . $etapaAtual] ?? '[]', true) ?: [];

            foreach ($vistoria as &$item) {
                if ((string) ($item['id'] ?? '') === (string) $itemId && !empty($item['img'])) {
                    FileHelper::delete($item['img'], $chave);
                    $item['img'] = null;
                    break;
                }
            }

            $model->atualizarVistoria($id, json_encode($vistoria, JSON_UNESCAPED_UNICODE), $etapaAtual);

            Response::json(['success' => true]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao excluir foto: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Salva assinatura e finaliza o checklist (aba Assinatura)
     *
     * POST /api/checklists/{id}/assinar
     */
    public function assinar(Request $request, int $id): void
    {
        try {
            if (!Auth::can('checklists.criar')) {
                Response::json(['success' => false, 'message' => 'Sem permissao'], 403);
                return;
            }

            $etapa = $request->input('etapa', 'saida');
            $checklist = $this->validarChecklist($id, true, $etapa);
            if (!$checklist) return;

            $assinatura = $request->input('assinatura');
            if (!$assinatura) {
                Response::json(['success' => false, 'message' => 'Assinatura obrigatoria'], 422);
                return;
            }

            $chave = Auth::chave();
            $filename = ImageHelper::save($assinatura, 'assinatura_checklist', 'webp', 90, $chave);

            if (!$filename) {
                Response::json(['success' => false, 'message' => 'Erro ao salvar assinatura'], 500);
                return;
            }

            $model = new Checklist();
            $etapaAtual = $model->etapaAtual($checklist, $etapa);
            $model->salvarAssinatura($id, $filename, $etapaAtual);

            if (!empty($checklist['id_veiculo']) && $this->deveAtualizarVeiculoNoChecklist($checklist, $etapaAtual)) {
                $odometro = $this->normalizarOdometro($request->input('odometro'));
                $tanque = trim((string) $request->input('tanque', ''));
                (new Veiculo())->atualizarDadosChecklist((int) $checklist['id_veiculo'], $odometro, $tanque !== '' ? $tanque : null);
            }

            $user = Auth::user();
            AuditLogService::registrar(
                ($user['nome'] ?? $user['name'] ?? 'Sistema') . ", finalizou checklist [{$checklist['codigo']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Checklist finalizado com sucesso',
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao salvar assinatura: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retorna dados completos do checklist (para retomar preenchimento)
     *
     * GET /api/checklists/{id}
     */
    public function show(Request $request, int $id): void
    {
        try {
            if (!Auth::can('checklists.criar')) {
                Response::json(['success' => false, 'message' => 'Sem permissao'], 403);
                return;
            }

            $checklist = $this->validarChecklist($id, false);
            if (!$checklist) return;

            $chave = Auth::chave();
            $model = new Checklist();
            $etapa = $model->etapaAtual($checklist, $request->query('etapa', null));

            // Resolver URLs das fotos da vistoria
            $vistoria = json_decode($checklist['vistoria_' . $etapa] ?? '[]', true) ?: [];
            foreach ($vistoria as &$item) {
                if (!empty($item['img'])) {
                    $item['img_url'] = FileHelper::url($item['img'], $chave);
                }
            }

            // Resolver URL da assinatura
            $assinaturaUrl = null;
            if (!empty($checklist['assinatura_' . $etapa])) {
                $assinaturaUrl = FileHelper::url($checklist['assinatura_' . $etapa], $chave);
            }

            Response::json([
                'success' => true,
                'data' => [
                    'id' => (int) $checklist['id'],
                    'codigo' => $checklist['codigo'],
                    'tipo' => $checklist['tipo'],
                    'etapa' => $etapa,
                    'status' => $checklist['status'],
                    'id_modelo' => (int) $checklist['id_modelo'],
                    'modelo_nome' => $checklist['modelo_nome'],
                    'modelo_questoes' => json_decode($checklist['modelo_questoes'] ?? '[]', true),
                    'modelo_vistoria' => json_decode($checklist['modelo_vistoria'] ?? '[]', true),
                    'id_veiculo' => $checklist['id_veiculo'] ? (int) $checklist['id_veiculo'] : null,
                    'veiculo' => $checklist['placa'] ? ($checklist['placa'] . ' - ' . $checklist['marca'] . ' ' . $checklist['veiculo_modelo']) : null,
                    'tipo_combustivel' => $checklist['tipo_combustivel'] ?? 'GE',
                    'odometro' => $checklist['odometro'] !== null ? (int) $checklist['odometro'] : null,
                    'tanque_fracao' => $checklist['tanque_fracao'] ?? null,
                    'id_locacao' => $checklist['id_locacao'] ? (int) $checklist['id_locacao'] : null,
                    'locacao_codigo' => $checklist['locacao_codigo'],
                    'locacao_cliente' => $checklist['locacao_cliente'],
                    'id_contrato' => $checklist['id_contrato'] ? (int) $checklist['id_contrato'] : null,
                    'contrato_codigo' => $checklist['contrato_codigo'],
                    'contrato_cliente' => $checklist['contrato_cliente'],
                    'obs' => $checklist['observacoes_' . $etapa],
                    'questoes' => json_decode($checklist['questoes_' . $etapa] ?? '[]', true),
                    'vistoria' => $vistoria,
                    'assinatura_url' => $assinaturaUrl,
                ],
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar checklist: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Busca locacoes ativas para vincular
     *
     * GET /api/checklists/buscar-locacoes
     */
    public function buscarLocacoes(Request $request): void
    {
        try {
            $chave = Auth::chave();
            if (!$chave) { Response::json(['success' => false, 'message' => 'Sessao invalida'], 401); return; }
            $search = $request->query('q', '');
            [$filialWhere, $filialParams] = FilialHelper::whereFiliais('l.id_matriz_filial_retirada');

            $model = new Checklist();
            $locacoes = $model->buscarLocacoesAtivas($chave, $search, $filialWhere, $filialParams);

            $results = [];
            foreach ($locacoes as $loc) {
                $results[] = [
                    'id' => (int) $loc['id'],
                    'codigo' => $loc['codigo'],
                    'cliente' => $loc['cliente_nome'],
                    'id_veiculo' => $loc['id_veiculo'] ? (int) $loc['id_veiculo'] : null,
                    'veiculo' => $loc['placa'] ? ($loc['placa'] . ' - ' . $loc['marca'] . ' ' . $loc['veiculo_modelo']) : null,
                    'tipo_combustivel' => $loc['tipo_combustivel'] ?? 'GE',
                    'odometro' => $loc['odometro'] !== null ? (int) $loc['odometro'] : null,
                    'tanque_fracao' => $loc['tanque_fracao'] ?? null,
                    'text' => $loc['codigo'] . ' - ' . $loc['cliente_nome'],
                ];
            }

            Response::json(['success' => true, 'data' => $results]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Busca contratos ativos para vincular
     *
     * GET /api/checklists/buscar-contratos
     */
    public function buscarContratos(Request $request): void
    {
        try {
            $chave = Auth::chave();
            if (!$chave) { Response::json(['success' => false, 'message' => 'Sessao invalida'], 401); return; }
            $search = $request->query('q', '');
            [$filialWhere, $filialParams] = FilialHelper::whereFiliais('ct.id_matriz_filial_retirada');

            $model = new Checklist();
            $contratos = $model->buscarContratosAtivos($chave, $search, $filialWhere, $filialParams);

            $results = [];
            foreach ($contratos as $ct) {
                $results[] = [
                    'id' => (int) $ct['id'],
                    'codigo' => $ct['codigo'],
                    'cliente' => $ct['cliente_nome'],
                    'id_veiculo' => $ct['id_veiculo'] ? (int) $ct['id_veiculo'] : null,
                    'veiculo' => $ct['placa'] ? ($ct['placa'] . ' - ' . $ct['marca'] . ' ' . $ct['veiculo_modelo']) : null,
                    'tipo_combustivel' => $ct['tipo_combustivel'] ?? 'GE',
                    'odometro' => $ct['odometro'] !== null ? (int) $ct['odometro'] : null,
                    'tanque_fracao' => $ct['tanque_fracao'] ?? null,
                    'text' => $ct['codigo'] . ' - ' . ($ct['cliente_nome'] ?? ''),
                ];
            }

            Response::json(['success' => true, 'data' => $results]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Busca veiculos disponiveis para checklist avulso
     *
     * GET /api/checklists/buscar-veiculos
     */
    public function buscarVeiculos(Request $request): void
    {
        try {
            $chave = Auth::chave();
            if (!$chave) { Response::json(['success' => false, 'message' => 'Sessao invalida'], 401); return; }
            $search = $request->query('q', '');
            $id = (int) $request->query('id', 0);
            [$filialWhere, $filialParams] = FilialHelper::whereFiliais('v.id_matriz_filial');

            if ($id > 0) {
                $veiculo = (new Veiculo())->buscarPorId($id);
                $veiculos = $veiculo ? [$veiculo] : [];
            } else {
                $model = new Checklist();
                $veiculos = $model->buscarVeiculos($chave, $search, $filialWhere, $filialParams);
            }

            $results = [];
            foreach ($veiculos as $v) {
                $results[] = [
                    'id' => (int) $v['id'],
                    'placa' => $v['placa'],
                    'modelo' => $v['modelo'],
                    'marca' => $v['marca'],
                    'tipo_combustivel' => $v['tipo_combustivel'] ?? 'GE',
                    'odometro' => $v['odometro'] !== null ? (int) $v['odometro'] : null,
                    'tanque_fracao' => $v['tanque_fracao'] ?? null,
                    'text' => $v['placa'] . ' - ' . $v['marca'] . ' ' . $v['modelo'],
                ];
            }

            Response::json(['success' => true, 'data' => $results]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Busca locacoes e contratos ativos em um unico endpoint (para chosen-select)
     *
     * GET /api/checklists/buscar-vinculos
     */
    public function buscarVinculos(Request $request): void
    {
        try {
            $chave = Auth::chave();
            if (!$chave) { Response::json(['success' => false, 'message' => 'Sessao invalida'], 401); return; }
            $search = $request->query('q', '');
            $model = new Checklist();
            $results = [];

            // Buscar locacoes
            [$filialWhereLoc, $filialParamsLoc] = FilialHelper::whereFiliais('l.id_matriz_filial_retirada');
            $locacoes = $model->buscarLocacoesAtivas($chave, $search, $filialWhereLoc, $filialParamsLoc);

            foreach ($locacoes as $loc) {
                $results[] = [
                    'id' => $loc['codigo'],
                    'codigo' => $loc['codigo'],
                    'tipo_vinculo' => 'L',
                    'id_vinculo' => (int) $loc['id'],
                    'text' => '[Locação] ' . $loc['codigo'] . ' - ' . $loc['cliente_nome'],
                    'id_veiculo' => $loc['id_veiculo'] ? (int) $loc['id_veiculo'] : null,
                    'veiculo' => $loc['placa'] ? ($loc['placa'] . ' - ' . $loc['marca'] . ' ' . $loc['veiculo_modelo']) : null,
                    'tipo_combustivel' => $loc['tipo_combustivel'] ?? 'GE',
                    'odometro' => $loc['odometro'] !== null ? (int) $loc['odometro'] : null,
                    'tanque_fracao' => $loc['tanque_fracao'] ?? null,
                ];
            }

            // Buscar contratos
            [$filialWhereCt, $filialParamsCt] = FilialHelper::whereFiliais('ct.id_matriz_filial_retirada');
            $contratos = $model->buscarContratosAtivos($chave, $search, $filialWhereCt, $filialParamsCt);

            foreach ($contratos as $ct) {
                $results[] = [
                    'id' => $ct['codigo'],
                    'codigo' => $ct['codigo'],
                    'tipo_vinculo' => 'C',
                    'id_vinculo' => (int) $ct['id'],
                    'text' => '[Contrato] ' . $ct['codigo'] . ' - ' . ($ct['cliente_nome'] ?? ''),
                    'id_veiculo' => $ct['id_veiculo'] ? (int) $ct['id_veiculo'] : null,
                    'veiculo' => $ct['placa'] ? ($ct['placa'] . ' - ' . $ct['marca'] . ' ' . $ct['veiculo_modelo']) : null,
                    'tipo_combustivel' => $ct['tipo_combustivel'] ?? 'GE',
                    'odometro' => $ct['odometro'] !== null ? (int) $ct['odometro'] : null,
                    'tanque_fracao' => $ct['tanque_fracao'] ?? null,
                ];
            }

            Response::json(['success' => true, 'data' => $results]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Lista checklists/vinculos que ainda precisam de saida ou entrada.
     */
    public function vinculadosPendentes(Request $request): void
    {
        try {
            if (!Auth::can('checklists.criar')) {
                Response::json(['success' => false, 'message' => 'Sem permissao'], 403);
                return;
            }

            $search = $request->query('search', '');
            $status = $request->query('status', '');

            if ($status !== '' && !in_array($status, ['aguardando_saida', 'aguardando_chegada'], true)) {
                Response::json(['success' => false, 'message' => 'Status invalido'], 422);
                return;
            }

            $model = new Checklist();
            [$filialWhere, $filialParams] = FilialHelper::whereFiliais('v.id_matriz_filial');
            Response::json([
                'success' => true,
                'data' => $model->listarVinculadosPendentes($search, $status, $filialWhere, $filialParams),
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Retorna veiculos de uma locacao/contrato com status de checklist
     *
     * GET /api/checklists/veiculos-vinculo?tipo=L|C&id=123&etapa=saida|entrada
     */
    public function veiculosVinculo(Request $request): void
    {
        try {
            $chave = Auth::chave();
            if (!$chave) { Response::json(['success' => false, 'message' => 'Sessao invalida'], 401); return; }

            $tipo = $request->query('tipo', '');
            $id = (int) $request->query('id', 0);
            $etapa = $request->query('etapa', '');
            if ($etapa === '') {
                $etapa = $request->query('momento', 'S') === 'C' ? 'entrada' : 'saida';
            }

            if (!in_array($tipo, ['L', 'C'], true) || !$id || !in_array($etapa, ['saida', 'entrada'], true)) {
                Response::json(['success' => false, 'message' => 'Parametros invalidos'], 422);
                return;
            }

            $model = new Checklist();
            $veiculos = $model->buscarVeiculosDoVinculo($tipo, $id, $etapa, $chave);

            $results = [];
            foreach ($veiculos as $v) {
                $results[] = [
                    'id_veiculo' => (int) $v['id_veiculo'],
                    'placa' => $v['placa'],
                    'marca' => $v['marca'],
                    'modelo' => $v['modelo'],
                    'tipo_combustivel' => $v['tipo_combustivel'] ?? 'GE',
                    'odometro' => $v['odometro'] !== null ? (int) $v['odometro'] : null,
                    'tanque_fracao' => $v['tanque_fracao'] ?? null,
                    'checklist_feito' => $v['checklist_feito'],
                    'text' => $v['placa'] . ' - ' . $v['marca'] . ' ' . $v['modelo'],
                ];
            }

            Response::json(['success' => true, 'data' => $results]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function deveAtualizarVeiculoNoChecklist(array $checklist, string $etapa): bool
    {
        if (($checklist['tipo'] ?? '') === 'A') {
            return true;
        }

        return ($checklist['tipo'] ?? '') === 'V' && $etapa === 'entrada';
    }

    private function normalizarOdometro($valor): ?int
    {
        $digits = preg_replace('/\D+/', '', (string) $valor);
        if ($digits === '') {
            return null;
        }

        $odometro = (int) $digits;
        return $odometro > 0 ? $odometro : null;
    }
}
