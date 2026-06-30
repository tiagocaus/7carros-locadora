<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\Grupo;
use App\Models\GrupoPrecoFilial;
use App\Models\GrupoPrecoDiaFilial;
use App\Models\MatrizFilial;
use App\Services\AuditLogService;

/**
 * Controller de Grupos
 *
 * Gerencia operacoes CRUD de grupos de veiculos para precificacao.
 */
class GruposController
{
    /**
     * Renderiza a pagina de grupos
     *
     * GET /pages/grupos
     */
    public function view(Request $request): void
    {
        $html = Template::render('pages.grupos.index');
        Response::html($html);
    }

    /**
     * Lista todos os grupos do tenant (com paginacao e busca)
     *
     * GET /api/grupos
     * Query params: page, perPage, search, q
     */
    public function index(Request $request): void
    {
        try {
            $grupoModel = new Grupo();

            // Se tem id_filial, retornar grupos com quantidade de veiculos disponiveis
            $filialId = $request->query('id_filial');
            if (!empty($filialId)) {
                $dataSaida = $this->normalizarDataHora($request->query('data_saida', ''));
                $dataPrevista = $this->normalizarDataHora($request->query('data_prevista', ''));
                $contexto = (string) $request->query('contexto', '');

                if ($contexto === 'reserva' && $dataSaida && $dataPrevista) {
                    $grupos = $grupoModel->listarComDisponibilidadePeriodo((int) $filialId, $dataSaida, $dataPrevista);
                } else {
                    $grupos = $grupoModel->listarComDisponibilidade((int) $filialId);
                }

                Response::json([
                    'success' => true,
                    'data' => $grupos
                ]);
                return;
            }

            // Parametros de paginacao
            $page = max(1, (int) $request->query('page', 1));
            $defaultPerPage = array_key_exists('q', $_GET) ? 50 : 10;
            $perPage = max(1, min(50, (int) $request->query('perPage', $defaultPerPage)));
            $search = $request->query('search', $request->query('q', ''));

            // Buscar grupos paginados
            $grupos = $grupoModel->listarPaginado($page, $perPage, $search);

            // Contar total de registros
            $total = $grupoModel->contar($search);

            // Calcular total de paginas
            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

            Response::json([
                'success' => true,
                'data' => $grupos,
                'pagination' => [
                    'page' => $page,
                    'perPage' => $perPage,
                    'total' => $total,
                    'totalPages' => $totalPages,
                    'hasNext' => $page < $totalPages,
                    'hasPrev' => $page > 1
                ]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar grupos: ' . $e->getMessage()
            ], 500);
        }
    }

    private function normalizarDataHora(string $valor): ?string
    {
        if ($valor === '') {
            return null;
        }

        $valor = str_replace('T', ' ', trim($valor));
        $timestamp = strtotime($valor);

        return $timestamp ? \App\Helpers\DateHelper::formatTimestamp($timestamp, 'Y-m-d H:i:s', false) : null;
    }

    /**
     * Exibe um grupo especifico
     *
     * GET /api/grupos/{id}
     */
    public function show(Request $request, int $id): void
    {
        try {
            $grupoModel = new Grupo();
            $grupo = $grupoModel->buscarPorId($id);

            if (!$grupo) {
                Response::json([
                    'success' => false,
                    'message' => 'Grupo nao encontrado'
                ], 404);
                return;
            }

            // Verificar se pertence ao tenant
            $chave = Auth::chave();
            if ($grupo['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Grupo nao encontrado'
                ], 404);
                return;
            }

            Response::json([
                'success' => true,
                'data' => $grupo
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar grupo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cria um novo grupo
     *
     * POST /grupos/salvar
     */
    public function store(Request $request): void
    {
        try {
            $dados = $request->all();
            $dados['chave'] = Auth::chave();

            // Validacao basica
            if (empty($dados['nome'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Nome e obrigatorio'
                ], 400);
                return;
            }

            $grupoModel = new Grupo();
            $id = $grupoModel->criar($dados);

            // Log de auditoria
            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", adicionou grupo [{$dados['nome']}]"
            );

            // Precos por dias agora sao salvos via /grupos/{id}/precos-filial/{idFilial}
            // (tabela grupos_precos_dias_filiais), nao mais aqui.

            Response::json([
                'success' => true,
                'message' => 'Grupo criado com sucesso',
                'data' => ['id' => $id]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao criar grupo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza um grupo
     *
     * POST /grupos/{id}/atualizar
     */
    public function update(Request $request, int $id): void
    {
        try {
            $grupoModel = new Grupo();
            $grupo = $grupoModel->buscarPorId($id);

            if (!$grupo) {
                Response::json([
                    'success' => false,
                    'message' => 'Grupo nao encontrado'
                ], 404);
                return;
            }

            // Verificar se pertence ao tenant
            $chave = Auth::chave();
            if ($grupo['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao pode editar este grupo'
                ], 403);
                return;
            }

            $dados = $request->all();
            $grupoModel->atualizar($id, $dados);

            // Log de auditoria
            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", atualizou grupo [{$grupo['nome']}]"
            );

            // Precos por dias agora sao salvos via /grupos/{id}/precos-filial/{idFilial}
            // (tabela grupos_precos_dias_filiais), nao mais aqui.

            Response::json([
                'success' => true,
                'message' => 'Grupo atualizado com sucesso'
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar grupo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exclui um grupo
     *
     * POST /grupos/{id}/excluir
     */
    public function destroy(Request $request, int $id): void
    {
        try {
            $grupoModel = new Grupo();
            $grupo = $grupoModel->buscarPorId($id);

            if (!$grupo) {
                Response::json([
                    'success' => false,
                    'message' => 'Grupo nao encontrado'
                ], 404);
                return;
            }

            // Verificar se pertence ao tenant
            $chave = Auth::chave();
            if ($grupo['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao pode excluir este grupo'
                ], 403);
                return;
            }

            $grupoModel->excluir($id);

            // Log de auditoria
            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", excluiu grupo [{$grupo['nome']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Grupo excluido com sucesso'
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao excluir grupo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retorna valores + escala por dias de um grupo em uma filial.
     * Se a entry nao existir, cria zerada on-the-fly via garantirEntriesParaGrupo.
     *
     * GET /api/grupos/{id}/precos-filial/{idFilial}
     */
    public function precosFilial(Request $request, int $id, int $idFilial): void
    {
        try {
            $chave = Auth::chave();

            $grupo = (new Grupo())->buscarPorId($id);
            if (!$grupo || $grupo['chave'] !== $chave) {
                Response::json(['success' => false, 'message' => 'Grupo nao encontrado'], 404);
                return;
            }

            $filial = (new MatrizFilial())->buscarPorId($idFilial);
            if (!$filial || $filial['chave'] !== $chave) {
                Response::json(['success' => false, 'message' => 'Filial nao encontrada'], 404);
                return;
            }

            $precosModel = new GrupoPrecoFilial();
            $valores = $precosModel->buscarPorGrupoFilial($id, $idFilial);
            if (!$valores) {
                // Cria entry zerada se ainda nao existe
                $precosModel->garantirEntriesParaGrupo($id);
                $valores = $precosModel->buscarPorGrupoFilial($id, $idFilial);
            }

            $faixas = (new GrupoPrecoDiaFilial())->listarPorGrupoFilial($id, $idFilial);

            Response::json([
                'success' => true,
                'data' => [
                    'valores'    => $valores,
                    'precos_dias' => $faixas,
                    'filial'     => [
                        'id'             => (int) $filial['id'],
                        'nome_fantasia'  => $filial['nome_fantasia'] ?? $filial['razao_social'] ?? '',
                        'currency_code'  => $filial['currency_code'] ?? 'BRL',
                        'locale'         => $filial['locale'] ?? 'pt_BR',
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar precos da filial: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Salva valores + escala por dias de um grupo em uma filial.
     *
     * POST /grupos/{id}/precos-filial/{idFilial}
     */
    public function salvarPrecosFilial(Request $request, int $id, int $idFilial): void
    {
        try {
            $chave = Auth::chave();

            $grupo = (new Grupo())->buscarPorId($id);
            if (!$grupo || $grupo['chave'] !== $chave) {
                Response::json(['success' => false, 'message' => 'Grupo nao encontrado'], 404);
                return;
            }

            $filial = (new MatrizFilial())->buscarPorId($idFilial);
            if (!$filial || $filial['chave'] !== $chave) {
                Response::json(['success' => false, 'message' => 'Filial nao encontrada'], 404);
                return;
            }

            $dados = $request->all();

            // 1) Valores (planos + seguros + tolerancia + km)
            $valoresFilial = $dados['valores'] ?? [];
            if (!empty($valoresFilial)) {
                $valoresFilial['chave'] = $chave;
                $valoresFilial['id_grupo'] = $id;
                $valoresFilial['id_matriz_filial'] = $idFilial;
                (new GrupoPrecoFilial())->upsert($valoresFilial);
            }

            // 2) Escala progressiva (precos_dias) por tipo_plano
            $precosDias = $dados['precos_dias'] ?? null;
            if (is_array($precosDias)) {
                (new GrupoPrecoDiaFilial())->salvarTodos($id, $idFilial, $chave, $precosDias);
            }

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') .
                ", atualizou valores do grupo [{$grupo['nome']}] para filial [#{$idFilial}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Valores salvos com sucesso',
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao salvar valores: ' . $e->getMessage()
            ], 500);
        }
    }
}
