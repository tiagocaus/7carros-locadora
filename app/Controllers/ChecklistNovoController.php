<?php

namespace App\Controllers;

use App\Config\Planos;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\Checklist;
use App\Models\ChecklistModelo;
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
    private function validarChecklist(int $id, bool $exigirPendente = true): ?array
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

        if ($exigirPendente && in_array($checklist['status'] ?? '', ['2', '4'])) {
            Response::json([
                'success' => false,
                'message' => 'Checklist ja finalizado'
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

        // Buscar par se vinculado
        $par = null;
        if (($checklist['tipo'] ?? '') === 'V') {
            $par = $model->buscarPar($checklist);
        }

        // Determinar saida e chegada
        $momento = $checklist['momento'] ?? 'S';
        if ($momento === 'C') {
            $regSaida = $par;
            $regChegada = $checklist;
        } else {
            $regSaida = $checklist;
            $regChegada = $par;
        }

        // Decodificar dados
        $questoesSaida = $regSaida ? (json_decode($regSaida['questoes'] ?? $regSaida['questoes_saida'] ?? '[]', true) ?: []) : [];
        $questoesChegada = $regChegada ? (json_decode($regChegada['questoes'] ?? $regChegada['questoes_saida'] ?? '[]', true) ?: []) : [];

        $vistoriaSaida = $regSaida ? (json_decode($regSaida['vistoria'] ?? $regSaida['vistoria_saida'] ?? '[]', true) ?: []) : [];
        $vistoriaChegada = $regChegada ? (json_decode($regChegada['vistoria'] ?? $regChegada['vistoria_saida'] ?? '[]', true) ?: []) : [];

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
            'dataSaida' => $regSaida['data_checklist'] ?? $regSaida['data_saida'] ?? null,
            'dataChegada' => $regChegada['data_checklist'] ?? $regChegada['data_saida'] ?? null,
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
        $temDashboard = Auth::can('dashboard.visualizar');

        ob_start();
        $viewPath = __DIR__ . '/../Views/pages/checklists/novo.php';
        extract([
            'csrf_token' => $csrfToken,
            'user_name' => $user['name'] ?? '',
            'plano' => $user['plano'] ?? 'G',
            'retomar_id' => $retomarId ? (int) $retomarId : null,
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
            $momento = $request->input('momento', 'N');
            $idModelo = (int) $request->input('id_modelo');
            $idVeiculo = $request->input('id_veiculo') ? (int) $request->input('id_veiculo') : null;
            $idLocacao = $request->input('id_locacao') ? (int) $request->input('id_locacao') : null;
            $idContrato = $request->input('id_contrato') ? (int) $request->input('id_contrato') : null;
            $tanque = $request->input('tanque', '');
            $odometro = $request->input('odometro') ? (int) str_replace(['.', ','], '', $request->input('odometro')) : null;
            $obs = $request->input('obs', '');

            // Validacoes
            if (!in_array($tipo, ['V', 'A'], true)) {
                Response::json(['success' => false, 'message' => 'Tipo invalido'], 422);
                return;
            }

            if ($tipo === 'V') {
                if (!$idLocacao && !$idContrato) {
                    Response::json(['success' => false, 'message' => 'Selecione uma locacao ou contrato'], 422);
                    return;
                }
                if (!in_array($momento, ['S', 'C'], true)) {
                    Response::json(['success' => false, 'message' => 'Selecione o momento (saida/chegada)'], 422);
                    return;
                }
            } else {
                $momento = 'N';
                $idLocacao = null;
                $idContrato = null;
            }

            if (!$idModelo) {
                Response::json(['success' => false, 'message' => 'Selecione um modelo de checklist'], 422);
                return;
            }

            if (!$idVeiculo) {
                Response::json(['success' => false, 'message' => 'Selecione um veiculo'], 422);
                return;
            }

            // Gerar codigo unico
            $codigo = 'CK' . strtoupper(bin2hex(random_bytes(6)));

            $model = new Checklist();
            $id = $model->criar([
                'chave' => $chave,
                'tipo' => $tipo,
                'momento' => $momento,
                'codigo' => $codigo,
                'id_modelo' => $idModelo,
                'id_veiculo' => $idVeiculo,
                'id_locacao' => $idLocacao,
                'id_contrato' => $idContrato,
                'tanque' => $tanque,
                'odometro' => $odometro,
                'obs_unica' => $obs,
                'id_funcionario' => $user['id'] ?? null,
                'status' => '1',
                'created_at' => date('Y-m-d H:i:s'),
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

            $checklist = $this->validarChecklist($id);
            if (!$checklist) return;

            $questoes = $request->input('questoes');
            if (!$questoes || !is_array($questoes)) {
                Response::json(['success' => false, 'message' => 'Dados de questoes invalidos'], 422);
                return;
            }

            $model = new Checklist();
            $model->atualizarQuestoes($id, json_encode($questoes, JSON_UNESCAPED_UNICODE));

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

            $checklist = $this->validarChecklist($id);
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
            $vistoria = json_decode($checklist['vistoria'] ?? '[]', true) ?: [];

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

            $model = new Checklist();
            $model->atualizarVistoria($id, json_encode($vistoria, JSON_UNESCAPED_UNICODE));

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

            $checklist = $this->validarChecklist($id);
            if (!$checklist) return;

            $chave = Auth::chave();
            $vistoria = json_decode($checklist['vistoria'] ?? '[]', true) ?: [];

            foreach ($vistoria as &$item) {
                if ((string) ($item['id'] ?? '') === (string) $itemId && !empty($item['img'])) {
                    FileHelper::delete($item['img'], $chave);
                    $item['img'] = null;
                    break;
                }
            }

            $model = new Checklist();
            $model->atualizarVistoria($id, json_encode($vistoria, JSON_UNESCAPED_UNICODE));

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

            $checklist = $this->validarChecklist($id);
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
            $model->salvarAssinatura($id, $filename);

            $user = Auth::user();
            AuditLogService::registrar(
                ($user['name'] ?? 'Sistema') . ", finalizou checklist [{$checklist['codigo']}]"
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

            // Resolver URLs das fotos da vistoria
            $vistoria = json_decode($checklist['vistoria'] ?? '[]', true) ?: [];
            foreach ($vistoria as &$item) {
                if (!empty($item['img'])) {
                    $item['img_url'] = FileHelper::url($item['img'], $chave);
                }
            }

            // Resolver URL da assinatura
            $assinaturaUrl = null;
            if (!empty($checklist['assinatura_unica'])) {
                $assinaturaUrl = FileHelper::url($checklist['assinatura_unica'], $chave);
            }

            Response::json([
                'success' => true,
                'data' => [
                    'id' => (int) $checklist['id'],
                    'codigo' => $checklist['codigo'],
                    'tipo' => $checklist['tipo'],
                    'momento' => $checklist['momento'],
                    'status' => $checklist['status'],
                    'id_modelo' => (int) $checklist['id_modelo'],
                    'modelo_nome' => $checklist['modelo_nome'],
                    'modelo_questoes' => json_decode($checklist['modelo_questoes'] ?? '[]', true),
                    'modelo_vistoria' => json_decode($checklist['modelo_vistoria'] ?? '[]', true),
                    'id_veiculo' => $checklist['id_veiculo'] ? (int) $checklist['id_veiculo'] : null,
                    'veiculo' => $checklist['placa'] ? ($checklist['placa'] . ' - ' . $checklist['marca'] . ' ' . $checklist['veiculo_modelo']) : null,
                    'id_locacao' => $checklist['id_locacao'] ? (int) $checklist['id_locacao'] : null,
                    'locacao_codigo' => $checklist['locacao_codigo'],
                    'locacao_cliente' => $checklist['locacao_cliente'],
                    'id_contrato' => $checklist['id_contrato'] ? (int) $checklist['id_contrato'] : null,
                    'contrato_codigo' => $checklist['contrato_codigo'],
                    'tanque' => $checklist['tanque'],
                    'odometro' => $checklist['odometro'] ? (int) $checklist['odometro'] : null,
                    'obs' => $checklist['obs_unica'],
                    'questoes' => json_decode($checklist['questoes'] ?? '[]', true),
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
            [$filialWhere, $filialParams] = FilialHelper::whereFiliais('v.id_matriz_filial');

            $model = new Checklist();
            $veiculos = $model->buscarVeiculos($chave, $search, $filialWhere, $filialParams);

            $results = [];
            foreach ($veiculos as $v) {
                $results[] = [
                    'id' => (int) $v['id'],
                    'placa' => $v['placa'],
                    'modelo' => $v['modelo'],
                    'marca' => $v['marca'],
                    'odometro' => $v['odometro'] ? (int) $v['odometro'] : null,
                    'tanque_fracao' => $v['tanque_fracao'],
                    'tipo_combustivel' => $v['tipo_combustivel'] ?? 'GE',
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
                    'id' => 'L-' . $loc['id'],
                    'text' => '[Locação] ' . $loc['codigo'] . ' - ' . $loc['cliente_nome'],
                    'id_veiculo' => $loc['id_veiculo'] ? (int) $loc['id_veiculo'] : null,
                    'veiculo' => $loc['placa'] ? ($loc['placa'] . ' - ' . $loc['marca'] . ' ' . $loc['veiculo_modelo']) : null,
                    'tipo_combustivel' => $loc['tipo_combustivel'] ?? 'GE',
                ];
            }

            // Buscar contratos
            [$filialWhereCt, $filialParamsCt] = FilialHelper::whereFiliais('ct.id_matriz_filial_retirada');
            $contratos = $model->buscarContratosAtivos($chave, $search, $filialWhereCt, $filialParamsCt);

            foreach ($contratos as $ct) {
                $results[] = [
                    'id' => 'C-' . $ct['id'],
                    'text' => '[Contrato] ' . $ct['codigo'] . ' - ' . ($ct['cliente_nome'] ?? ''),
                    'id_veiculo' => $ct['id_veiculo'] ? (int) $ct['id_veiculo'] : null,
                    'veiculo' => $ct['placa'] ? ($ct['placa'] . ' - ' . $ct['marca'] . ' ' . $ct['veiculo_modelo']) : null,
                    'tipo_combustivel' => $ct['tipo_combustivel'] ?? 'GE',
                ];
            }

            Response::json(['success' => true, 'data' => $results]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Retorna veiculos de uma locacao/contrato com status de checklist
     *
     * GET /api/checklists/veiculos-vinculo?tipo=L|C&id=123&momento=S|C
     */
    public function veiculosVinculo(Request $request): void
    {
        try {
            $chave = Auth::chave();
            if (!$chave) { Response::json(['success' => false, 'message' => 'Sessao invalida'], 401); return; }

            $tipo = $request->query('tipo', '');
            $id = (int) $request->query('id', 0);
            $momento = $request->query('momento', 'S');

            if (!in_array($tipo, ['L', 'C'], true) || !$id) {
                Response::json(['success' => false, 'message' => 'Parametros invalidos'], 422);
                return;
            }

            $model = new Checklist();
            $veiculos = $model->buscarVeiculosDoVinculo($tipo, $id, $momento, $chave);

            $results = [];
            foreach ($veiculos as $v) {
                $results[] = [
                    'id_veiculo' => (int) $v['id_veiculo'],
                    'placa' => $v['placa'],
                    'marca' => $v['marca'],
                    'modelo' => $v['modelo'],
                    'tipo_combustivel' => $v['tipo_combustivel'] ?? 'GE',
                    'odometro' => $v['odometro'] ? (int) $v['odometro'] : null,
                    'tanque_fracao' => $v['tanque_fracao'],
                    'checklist_feito' => $v['checklist_feito'],
                    'text' => $v['placa'] . ' - ' . $v['marca'] . ' ' . $v['modelo'],
                ];
            }

            Response::json(['success' => true, 'data' => $results]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
