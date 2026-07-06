<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\Assinatura;
use App\Models\Promissoria;
use App\Models\MatrizFilial;
use App\Helpers\FilialHelper;
use App\Helpers\PdfHelper;
use App\Services\AuditLogService;
use App\Services\PromissoriaTemplateService;

/**
 * Controller de Promissorias
 *
 * Gerencia operacoes CRUD de promissorias com parcelas.
 * Cada promissoria e identificada pelo codigo_base (ex: PRO1010513).
 *
 * Permissoes:
 * - promissorias.visualizar
 * - promissorias.criar
 * - promissorias.editar
 * - promissorias.excluir
 */
class PromissoriasController
{
    /**
     * Renderiza a pagina de listagem
     *
     * GET /pages/promissorias
     */
    public function view(Request $request): void
    {
        $html = Template::render('pages.promissorias.index');
        Response::html($html);
    }

    /**
     * Renderiza a pagina de adicionar
     *
     * GET /pages/promissorias/adicionar
     */
    public function formView(Request $request): void
    {
        $html = Template::render('pages.promissorias.adicionar');
        Response::html($html);
    }

    /**
     * Renderiza a pagina de editar
     *
     * GET /pages/promissorias/editar/{codigo}
     */
    public function editView(Request $request, string $codigo): void
    {
        $html = Template::render('pages.promissorias.editar', ['codigo' => $codigo]);
        Response::html($html);
    }

    /**
     * Lista promissorias agrupadas com paginacao e busca
     *
     * GET /api/promissorias
     * Query params: page, perPage, search, status, filial
     */
    public function index(Request $request): void
    {
        try {
            if (!Auth::can('promissorias.visualizar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para visualizar promissorias'
                ], 403);
                return;
            }

            $chave = Auth::chave();

            // Parametros de paginacao
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('perPage', 10)));
            $search = $request->query('search', '');

            // Parametros de filtro
            $status = $request->query('status', '');

            // Filtro de filial (permissoes do usuario)
            [$filialWhere, $filialParams] = FilialHelper::whereFiliais('id_matriz_filial');

            $promissoriaModel = new Promissoria();

            // Buscar promissorias agrupadas
            $promissorias = $promissoriaModel->listarAgrupado(
                $chave,
                $page,
                $perPage,
                $search,
                $filialWhere,
                $filialParams,
                $status
            );

            // Contar total
            $total = $promissoriaModel->contarAgrupado($chave, $search, $filialWhere, $filialParams, $status);

            // Total de paginas
            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

            Response::json([
                'success' => true,
                'data' => $promissorias,
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
                'message' => 'Erro ao buscar promissorias: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exibe uma promissoria com todas suas parcelas pelo codigo base
     *
     * GET /api/promissorias/codigo/{codigo}
     */
    public function showByCodigo(Request $request, string $codigo): void
    {
        try {
            if (!Auth::can('promissorias.visualizar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para visualizar promissorias'
                ], 403);
                return;
            }

            $promissoriaModel = new Promissoria();
            $promissoria = $promissoriaModel->buscarResumoPorCodigoBase($codigo);

            if (!$promissoria) {
                Response::json([
                    'success' => false,
                    'message' => 'Promissoria nao encontrada'
                ], 404);
                return;
            }

            // Verificar tenant
            $chave = Auth::chave();
            if ($promissoria['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Promissoria nao encontrada'
                ], 404);
                return;
            }

            // Verificar acesso a filial
            if (!FilialHelper::temAcessoFilial($promissoria['id_matriz_filial'] ?? null)) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para acessar esta promissoria'
                ], 403);
                return;
            }

            Response::json([
                'success' => true,
                'data' => $promissoria
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar promissoria: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Busca assinatura de uma promissoria.
     *
     * GET /api/promissorias/{codigo}/assinatura
     */
    public function buscarAssinatura(Request $request, string $codigo): void
    {
        try {
            if (!Auth::can('promissorias.visualizar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para visualizar promissorias'
                ], 403);
                return;
            }

            $promissoriaModel = new Promissoria();
            $promissoria = $promissoriaModel->buscarResumoPorCodigoBase($codigo);

            if (!$promissoria) {
                Response::json([
                    'success' => false,
                    'message' => 'Promissoria nao encontrada'
                ], 404);
                return;
            }

            $chave = Auth::chave();
            if ($promissoria['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Acesso negado'
                ], 403);
                return;
            }

            if (!FilialHelper::temAcessoFilial($promissoria['id_matriz_filial'] ?? null)) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para acessar esta promissoria'
                ], 403);
                return;
            }

            $assinatura = (new Assinatura())->buscarPorPromissoria($codigo);

            if (!$assinatura) {
                Response::json([
                    'success' => false,
                    'message' => 'Assinatura nao encontrada'
                ], 404);
                return;
            }

            Response::json([
                'success' => true,
                'data' => [
                    'id' => $assinatura['id'],
                    'url' => $assinatura['url'] ?? '',
                    'data_assinatura' => !empty($assinatura['created_at'])
                        ? format_datetime($assinatura['created_at'])
                        : '-',
                    'ip' => $assinatura['ip_address'] ?? '-'
                ]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar assinatura: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Limpa assinatura de uma promissoria.
     *
     * POST /promissorias/{codigo}/limpar-assinatura
     */
    public function limparAssinatura(Request $request, string $codigo): void
    {
        try {
            if (!Auth::can('promissorias.editar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para editar promissorias'
                ], 403);
                return;
            }

            $promissoriaModel = new Promissoria();
            $promissoria = $promissoriaModel->buscarResumoPorCodigoBase($codigo);

            if (!$promissoria) {
                Response::json([
                    'success' => false,
                    'message' => 'Promissoria nao encontrada'
                ], 404);
                return;
            }

            $chave = Auth::chave();
            if ($promissoria['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Acesso negado'
                ], 403);
                return;
            }

            if (!FilialHelper::temAcessoFilial($promissoria['id_matriz_filial'] ?? null)) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para editar esta promissoria'
                ], 403);
                return;
            }

            (new Assinatura())->excluirPorPromissoria($codigo);

            $nomeUsuario = $_SESSION['user_name'] ?? 'Sistema';
            AuditLogService::registrar("{$nomeUsuario}, limpou assinatura da promissoria {$codigo}");

            Response::json([
                'success' => true,
                'message' => 'Assinatura removida com sucesso'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao limpar assinatura: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Envia link publico de assinatura da promissoria por WhatsApp.
     *
     * POST /promissorias/{codigo}/enviar-link-assinatura
     * Body JSON: { url }
     */
    public function enviarLinkAssinatura(Request $request, string $codigo): void
    {
        try {
            if (!Auth::can('promissorias.visualizar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para visualizar promissorias'
                ], 403);
                return;
            }

            $promissoriaModel = new Promissoria();
            $promissoria = $promissoriaModel->buscarResumoPorCodigoBase($codigo);

            if (!$promissoria) {
                Response::json([
                    'success' => false,
                    'message' => 'Promissoria nao encontrada'
                ], 404);
                return;
            }

            $chave = Auth::chave();
            if ($promissoria['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Acesso negado'
                ], 403);
                return;
            }

            if (!FilialHelper::temAcessoFilial($promissoria['id_matriz_filial'] ?? null)) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para acessar esta promissoria'
                ], 403);
                return;
            }

            $telefone = $promissoria['cliente_telefone'] ?? '';
            if (empty($telefone)) {
                Response::json([
                    'success' => false,
                    'message' => 'Cliente sem telefone cadastrado'
                ], 400);
                return;
            }

            $url = trim((string) ($request->input('url') ?? ''));
            if ($url === '') {
                $url = rtrim(env('APP_URL', ''), '/') . '/assinar/' . $promissoria['codigo_base'];
            }

            $filialId = (int) ($promissoria['id_matriz_filial'] ?? 0);
            $empresa = [];
            if ($filialId > 0) {
                $empresa = (new MatrizFilial())->buscarPorId($filialId) ?? [];
            }
            $empresa['id'] = $empresa['id'] ?? $filialId;

            queue_template_message('signature_request', 'whatsapp', [
                'cliente' => [
                    'nome' => $promissoria['cliente_nome'] ?? '',
                    'email' => $promissoria['cliente_email'] ?? '',
                    'telefone' => $telefone,
                    'celular' => $telefone,
                ],
                'empresa' => $empresa,
                'promissoria' => $promissoria,
                'outros' => [
                    'link_assinatura' => $url,
                ],
                'id_matriz_filial' => $filialId,
            ], $chave);

            Response::json([
                'success' => true,
                'message' => 'Link de assinatura enviado por WhatsApp'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao enviar link: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cria uma nova promissoria com parcelas
     *
     * POST /promissorias/salvar
     */
    public function store(Request $request): void
    {
        try {
            if (!Auth::can('promissorias.criar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para criar promissorias'
                ], 403);
                return;
            }

            $dados = $request->all();

            // Validacao basica
            if (empty($dados['id_cliente'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Cliente e obrigatorio'
                ], 400);
                return;
            }

            if (empty($dados['valor']) || floatval(str_replace(['.', ','], ['', '.'], $dados['valor'])) <= 0) {
                Response::json([
                    'success' => false,
                    'message' => 'Valor e obrigatorio e deve ser maior que zero'
                ], 400);
                return;
            }

            if (empty($dados['id_matriz_filial'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Filial e obrigatoria'
                ], 400);
                return;
            }

            if (empty($dados['primeiro_vencimento'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Data do primeiro vencimento e obrigatoria'
                ], 400);
                return;
            }

            // Verificar acesso a filial
            if (!FilialHelper::temAcessoFilial((int) $dados['id_matriz_filial'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para criar promissorias nesta filial'
                ], 403);
                return;
            }

            $promissoriaModel = new Promissoria();

            // Criar promissoria com parcelas
            $codigoBase = $promissoriaModel->criarComParcelas([
                'id_cliente' => $dados['id_cliente'],
                'id_matriz_filial' => $dados['id_matriz_filial'],
                'codigo_contrato_locacao' => $dados['codigo_contrato_locacao'] ?? null,
                'valor_total' => $dados['valor'],
                'primeiro_vencimento' => $dados['primeiro_vencimento'],
                'num_parcelas' => (int) ($dados['parcelas'] ?? 1),
                'intervalo_dias' => (int) ($dados['intervalo'] ?? 30),
                'obs' => $dados['obs'] ?? null,
            ]);

            $nomeUsuario = $_SESSION['user_name'] ?? 'Sistema';
            AuditLogService::registrar("{$nomeUsuario}, criou a promissoria {$codigoBase}");

            Response::json([
                'success' => true,
                'message' => 'Promissoria criada com sucesso',
                'data' => ['codigo' => $codigoBase]
            ], 201);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao criar promissoria: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza dados gerais de uma promissoria
     *
     * POST /promissorias/{codigo}/atualizar
     */
    public function update(Request $request, string $codigo): void
    {
        try {
            if (!Auth::can('promissorias.editar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para editar promissorias'
                ], 403);
                return;
            }

            $promissoriaModel = new Promissoria();
            $promissoria = $promissoriaModel->buscarResumoPorCodigoBase($codigo);

            if (!$promissoria) {
                Response::json([
                    'success' => false,
                    'message' => 'Promissoria nao encontrada'
                ], 404);
                return;
            }

            // Verificar tenant
            $chave = Auth::chave();
            if ($promissoria['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao pode editar esta promissoria'
                ], 403);
                return;
            }

            // Verificar acesso a filial
            if (!FilialHelper::temAcessoFilial($promissoria['id_matriz_filial'] ?? null)) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para editar esta promissoria'
                ], 403);
                return;
            }

            $dados = $request->all();

            // Atualizar dados gerais
            $promissoriaModel->atualizarDadosGerais($codigo, $dados);

            $nomeUsuario = $_SESSION['user_name'] ?? 'Sistema';
            AuditLogService::registrar("{$nomeUsuario}, atualizou a promissoria {$codigo}");

            Response::json([
                'success' => true,
                'message' => 'Promissoria atualizada com sucesso'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar promissoria: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exclui uma promissoria e todas suas parcelas
     *
     * POST /promissorias/{codigo}/excluir
     */
    public function destroy(Request $request, string $codigo): void
    {
        try {
            if (!Auth::can('promissorias.excluir')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para excluir promissorias'
                ], 403);
                return;
            }

            $promissoriaModel = new Promissoria();
            $promissoria = $promissoriaModel->buscarResumoPorCodigoBase($codigo);

            if (!$promissoria) {
                Response::json([
                    'success' => false,
                    'message' => 'Promissoria nao encontrada'
                ], 404);
                return;
            }

            // Verificar tenant
            $chave = Auth::chave();
            if ($promissoria['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao pode excluir esta promissoria'
                ], 403);
                return;
            }

            // Verificar acesso a filial
            if (!FilialHelper::temAcessoFilial($promissoria['id_matriz_filial'] ?? null)) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para excluir esta promissoria'
                ], 403);
                return;
            }

            $qtdParcelas = $promissoria['qtd_parcelas'];

            // Excluir todas as parcelas
            $promissoriaModel->excluirPorCodigoBase($codigo);

            $nomeUsuario = $_SESSION['user_name'] ?? 'Sistema';
            $mensagem = "{$nomeUsuario}, excluiu a promissoria {$codigo}";
            if ($qtdParcelas > 1) {
                $mensagem .= " ({$qtdParcelas} parcelas)";
            }
            AuditLogService::registrar($mensagem);

            Response::json([
                'success' => true,
                'message' => 'Promissoria excluida com sucesso'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao excluir promissoria: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marca todas as parcelas de uma promissoria como pagas
     *
     * POST /promissorias/{codigo}/marcar-pago
     */
    public function marcarPago(Request $request, string $codigo): void
    {
        try {
            if (!Auth::can('promissorias.editar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para marcar promissorias como pagas'
                ], 403);
                return;
            }

            $promissoriaModel = new Promissoria();
            $promissoria = $promissoriaModel->buscarResumoPorCodigoBase($codigo);

            if (!$promissoria) {
                Response::json([
                    'success' => false,
                    'message' => 'Promissoria nao encontrada'
                ], 404);
                return;
            }

            // Verificar tenant
            $chave = Auth::chave();
            if ($promissoria['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao pode modificar esta promissoria'
                ], 403);
                return;
            }

            // Verificar acesso a filial
            if (!FilialHelper::temAcessoFilial($promissoria['id_matriz_filial'] ?? null)) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para modificar esta promissoria'
                ], 403);
                return;
            }

            // Verificar se ja esta quitada
            if ($promissoria['quitado']) {
                Response::json([
                    'success' => false,
                    'message' => 'Esta promissoria ja esta quitada'
                ], 400);
                return;
            }

            $dataPago = today();

            // Marcar todas as parcelas como pagas
            $qtdAtualizadas = $promissoriaModel->marcarTodasPagas($codigo, $dataPago);

            $nomeUsuario = $_SESSION['user_name'] ?? 'Sistema';
            $mensagem = "{$nomeUsuario}, marcou a promissoria {$codigo} como paga";
            if ($qtdAtualizadas > 1) {
                $mensagem .= " ({$qtdAtualizadas} parcelas)";
            }
            AuditLogService::registrar($mensagem);

            Response::json([
                'success' => true,
                'message' => 'Promissoria marcada como paga'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao marcar como pago: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Adiciona nova parcela a uma promissoria
     *
     * POST /promissorias/{codigo}/parcelas/adicionar
     */
    public function addParcela(Request $request, string $codigo): void
    {
        try {
            if (!Auth::can('promissorias.editar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para adicionar parcelas'
                ], 403);
                return;
            }

            $promissoriaModel = new Promissoria();
            $promissoria = $promissoriaModel->buscarResumoPorCodigoBase($codigo);

            if (!$promissoria) {
                Response::json([
                    'success' => false,
                    'message' => 'Promissoria nao encontrada'
                ], 404);
                return;
            }

            // Verificar tenant
            $chave = Auth::chave();
            if ($promissoria['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao pode modificar esta promissoria'
                ], 403);
                return;
            }

            // Verificar acesso a filial
            if (!FilialHelper::temAcessoFilial($promissoria['id_matriz_filial'] ?? null)) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para modificar esta promissoria'
                ], 403);
                return;
            }

            $dados = $request->all();

            // Validacao
            if (empty($dados['valor_parcela']) || floatval(str_replace(['.', ','], ['', '.'], $dados['valor_parcela'])) <= 0) {
                Response::json([
                    'success' => false,
                    'message' => 'Valor da parcela e obrigatorio'
                ], 400);
                return;
            }

            if (empty($dados['data_vencimento'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Data de vencimento e obrigatoria'
                ], 400);
                return;
            }

            // Adicionar parcela
            $idParcela = $promissoriaModel->adicionarParcela($codigo, [
                'valor_parcela' => $dados['valor_parcela'],
                'data_vencimento' => $dados['data_vencimento']
            ]);

            $nomeUsuario = $_SESSION['user_name'] ?? 'Sistema';
            AuditLogService::registrar("{$nomeUsuario}, adicionou parcela na promissoria {$codigo}");

            Response::json([
                'success' => true,
                'message' => 'Parcela adicionada com sucesso',
                'data' => ['id' => $idParcela]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao adicionar parcela: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza uma parcela especifica
     *
     * POST /promissorias/{codigo}/parcelas/{id}/atualizar
     */
    public function updateParcela(Request $request, string $codigo, int $id): void
    {
        try {
            if (!Auth::can('promissorias.editar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para editar parcelas'
                ], 403);
                return;
            }

            $promissoriaModel = new Promissoria();

            // Buscar a parcela
            $parcela = $promissoriaModel->buscarPorId($id);

            if (!$parcela) {
                Response::json([
                    'success' => false,
                    'message' => 'Parcela nao encontrada'
                ], 404);
                return;
            }

            // Verificar se pertence a promissoria
            if ($parcela['codigo_base'] !== $codigo) {
                Response::json([
                    'success' => false,
                    'message' => 'Parcela nao pertence a esta promissoria'
                ], 400);
                return;
            }

            // Verificar tenant
            $chave = Auth::chave();
            if ($parcela['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao pode modificar esta parcela'
                ], 403);
                return;
            }

            // Verificar se ja foi paga
            if ($parcela['pago'] === 'S') {
                Response::json([
                    'success' => false,
                    'message' => 'Nao e possivel editar uma parcela ja paga'
                ], 400);
                return;
            }

            $dados = $request->all();

            // Atualizar parcela
            $promissoriaModel->atualizarParcela($id, [
                'valor_parcela' => $dados['valor_parcela'] ?? null,
                'data_vencimento' => $dados['data_vencimento'] ?? null
            ]);

            $nomeUsuario = $_SESSION['user_name'] ?? 'Sistema';
            AuditLogService::registrar("{$nomeUsuario}, atualizou parcela #{$parcela['numero_parcela']} da promissoria {$codigo}");

            Response::json([
                'success' => true,
                'message' => 'Parcela atualizada com sucesso'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar parcela: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exclui uma parcela especifica
     *
     * POST /promissorias/{codigo}/parcelas/{id}/excluir
     */
    public function destroyParcela(Request $request, string $codigo, int $id): void
    {
        try {
            if (!Auth::can('promissorias.excluir')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para excluir parcelas'
                ], 403);
                return;
            }

            $promissoriaModel = new Promissoria();

            // Buscar a parcela
            $parcela = $promissoriaModel->buscarPorId($id);

            if (!$parcela) {
                Response::json([
                    'success' => false,
                    'message' => 'Parcela nao encontrada'
                ], 404);
                return;
            }

            // Verificar se pertence a promissoria
            if ($parcela['codigo_base'] !== $codigo) {
                Response::json([
                    'success' => false,
                    'message' => 'Parcela nao pertence a esta promissoria'
                ], 400);
                return;
            }

            // Verificar tenant
            $chave = Auth::chave();
            if ($parcela['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao pode excluir esta parcela'
                ], 403);
                return;
            }

            // Verificar se ja foi paga
            if ($parcela['pago'] === 'S') {
                Response::json([
                    'success' => false,
                    'message' => 'Nao e possivel excluir uma parcela ja paga'
                ], 400);
                return;
            }

            // Verificar se e a unica parcela
            $promissoria = $promissoriaModel->buscarResumoPorCodigoBase($codigo);
            if ($promissoria && $promissoria['qtd_parcelas'] <= 1) {
                Response::json([
                    'success' => false,
                    'message' => 'Nao e possivel excluir a unica parcela. Exclua a promissoria inteira.'
                ], 400);
                return;
            }

            // Excluir parcela
            $promissoriaModel->excluirParcela($id);

            $nomeUsuario = $_SESSION['user_name'] ?? 'Sistema';
            AuditLogService::registrar("{$nomeUsuario}, excluiu parcela #{$parcela['numero_parcela']} da promissoria {$codigo}");

            Response::json([
                'success' => true,
                'message' => 'Parcela excluida com sucesso'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao excluir parcela: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marca uma parcela especifica como paga
     *
     * POST /promissorias/{codigo}/parcelas/{id}/pagar
     */
    public function marcarParcelaPaga(Request $request, string $codigo, int $id): void
    {
        try {
            if (!Auth::can('promissorias.editar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para marcar parcelas como pagas'
                ], 403);
                return;
            }

            $promissoriaModel = new Promissoria();

            // Buscar a parcela
            $parcela = $promissoriaModel->buscarPorId($id);

            if (!$parcela) {
                Response::json([
                    'success' => false,
                    'message' => 'Parcela nao encontrada'
                ], 404);
                return;
            }

            // Verificar se pertence a promissoria
            if ($parcela['codigo_base'] !== $codigo) {
                Response::json([
                    'success' => false,
                    'message' => 'Parcela nao pertence a esta promissoria'
                ], 400);
                return;
            }

            // Verificar tenant
            $chave = Auth::chave();
            if ($parcela['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao pode modificar esta parcela'
                ], 403);
                return;
            }

            // Verificar se ja foi paga
            if ($parcela['pago'] === 'S') {
                Response::json([
                    'success' => false,
                    'message' => 'Esta parcela ja esta paga'
                ], 400);
                return;
            }

            $dataPago = today();

            // Marcar parcela como paga
            $promissoriaModel->marcarParcelaPaga($id, $dataPago);

            $nomeUsuario = $_SESSION['user_name'] ?? 'Sistema';
            AuditLogService::registrar("{$nomeUsuario}, marcou parcela #{$parcela['numero_parcela']} da promissoria {$codigo} como paga");

            Response::json([
                'success' => true,
                'message' => 'Parcela marcada como paga'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao marcar parcela como paga: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Imprime uma promissoria
     *
     * GET /promissorias/{codigo}/imprimir
     */
    public function imprimir(Request $request, string $codigo): void
    {
        try {
            $promissoriaModel = new Promissoria();
            $promissoria = $promissoriaModel->buscarResumoPorCodigoBase($codigo);

            if (!$promissoria) {
                Response::html('<h1>Promissoria nao encontrada</h1>', 404);
                return;
            }

            // Verificar tenant
            $chave = Auth::chave();
            if ($promissoria['chave'] !== $chave) {
                Response::html('<h1>Acesso negado</h1>', 403);
                return;
            }

            // Verificar acesso a filial
            if (!FilialHelper::temAcessoFilial($promissoria['id_matriz_filial'] ?? null)) {
                Response::html('<h1>Voce nao tem permissao para acessar esta promissoria</h1>', 403);
                return;
            }

            // Buscar dados da empresa
            $empresa = null;
            if (!empty($promissoria['id_matriz_filial'])) {
                $matrizFilialModel = new MatrizFilial();
                $empresa = $matrizFilialModel->buscarPorId($promissoria['id_matriz_filial']);
            }

            // Buscar e renderizar templates de texto legal
            $templateService = new PromissoriaTemplateService($promissoriaModel->getMysqliConnection(), $chave);
            $todasPagas = (int)($promissoria['qtd_pagas'] ?? 0) === (int)($promissoria['qtd_parcelas'] ?? 0) && (int)($promissoria['qtd_parcelas'] ?? 0) > 0;
            $templateSlug = $todasPagas ? 'promissoria_texto_quitada' : 'promissoria_texto_pendente';

            // Montar contexto para renderizacao
            $context = $this->montarContextoPromissoria($promissoria, $empresa);
            $textoLegal = $templateService->render($templateSlug, $context);

            // Capturar HTML do template sem enviar para o navegador
            // (Template::render() faz echo+exit, nao podemos usar)
            ob_start();
            $viewPath = __DIR__ . '/../Views/pages/promissorias/imprimir/promissoria.php';
            extract(['promissoria' => $promissoria, 'empresa' => $empresa, 'textoLegal' => $textoLegal]);
            include $viewPath;
            $html = ob_get_clean();

            // Gerar PDF com PdfHelper (inclui watermark lateral automaticamente)
            $mpdf = PdfHelper::create([
                'margin_left' => 10,  // Espaço para watermark lateral
                'margin_right' => 10,
                'margin_top' => 5,
                'margin_bottom' => 5
            ]);

            PdfHelper::writeHtml($mpdf, $html);
            $mpdf->Output('promissoria-' . $codigo . '.pdf', 'I');
            exit;
        } catch (\Exception $e) {
            Response::html('<h1>Erro ao gerar impressao: ' . htmlspecialchars($e->getMessage()) . '</h1>', 500);
        }
    }

    /**
     * Imprime uma parcela individual da promissoria
     *
     * GET /promissorias/{codigo}/parcelas/{parcela}/imprimir
     */
    public function imprimirParcela(Request $request, string $codigo, int $parcela): void
    {
        try {
            $promissoriaModel = new Promissoria();

            // Buscar a parcela especifica com dados completos
            $parcelaData = $promissoriaModel->buscarParcelaCompleta($parcela);

            if (!$parcelaData) {
                Response::html('<h1>Parcela nao encontrada</h1>', 404);
                return;
            }

            // Verificar se pertence a promissoria informada
            if ($parcelaData['codigo_base'] !== $codigo) {
                Response::html('<h1>Parcela nao pertence a esta promissoria</h1>', 400);
                return;
            }

            // Verificar tenant
            $chave = Auth::chave();
            if ($parcelaData['chave'] !== $chave) {
                Response::html('<h1>Acesso negado</h1>', 403);
                return;
            }

            // Verificar acesso a filial
            if (!FilialHelper::temAcessoFilial($parcelaData['id_matriz_filial'] ?? null)) {
                Response::html('<h1>Voce nao tem permissao para acessar esta promissoria</h1>', 403);
                return;
            }

            // Buscar dados da empresa
            $empresa = null;
            if (!empty($parcelaData['id_matriz_filial'])) {
                $matrizFilialModel = new MatrizFilial();
                $empresa = $matrizFilialModel->buscarPorId($parcelaData['id_matriz_filial']);
            }

            // Buscar e renderizar templates de texto legal
            $templateService = new PromissoriaTemplateService($promissoriaModel->getMysqliConnection(), $chave);
            $isPaga = ($parcelaData['pago'] ?? 'N') === 'S';
            $templateSlug = $isPaga ? 'parcela_texto_paga' : 'parcela_texto_pendente';

            // Montar contexto para renderizacao
            $context = $this->montarContextoParcela($parcelaData, $empresa);
            $textoLegal = $templateService->render($templateSlug, $context);

            // Capturar HTML do template
            ob_start();
            $viewPath = __DIR__ . '/../Views/pages/promissorias/imprimir/parcela.php';
            extract(['parcela' => $parcelaData, 'empresa' => $empresa, 'textoLegal' => $textoLegal]);
            include $viewPath;
            $html = ob_get_clean();

            // Gerar PDF com PdfHelper
            $mpdf = PdfHelper::create([
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 5,
                'margin_bottom' => 5
            ]);

            PdfHelper::writeHtml($mpdf, $html);
            $mpdf->Output('parcela-' . $codigo . '-' . $parcelaData['numero_parcela'] . '.pdf', 'I');
            exit;
        } catch (\Exception $e) {
            Response::html('<h1>Erro ao gerar impressao: ' . htmlspecialchars($e->getMessage()) . '</h1>', 500);
        }
    }

    /**
     * Monta contexto para renderizacao de templates de promissoria
     */
    private function montarContextoPromissoria(array $promissoria, ?array $empresa): array
    {
        // Montar endereco completo do cliente
        $enderecoCompleto = $this->montarEnderecoCompleto(
            $promissoria['cliente_endereco'] ?? '',
            $promissoria['cliente_numero'] ?? '',
            $promissoria['cliente_bairro'] ?? '',
            $promissoria['cliente_cidade'] ?? '',
            $promissoria['cliente_estado'] ?? '',
            $promissoria['cliente_cep'] ?? ''
        );

        return [
            'cliente' => [
                'nome' => $promissoria['cliente_nome'] ?? '',
                'cpf_cnpj' => $promissoria['cliente_cpf_cnpj'] ?? '',
                'rg' => $promissoria['cliente_rg'] ?? '',
                'endereco_completo' => $enderecoCompleto,
            ],
            'empresa' => [
                'cidade' => $empresa['cidade'] ?? ($promissoria['cliente_cidade'] ?? ''),
            ],
            'promissoria' => [
                'codigo' => $promissoria['codigo_base'] ?? '',
                'valor_total' => currency_format((float)($promissoria['valor_total'] ?? 0)),
                'valor_extenso' => currency_format_extenso((float)($promissoria['valor_total'] ?? 0)),
                'qtd_parcelas' => (string)($promissoria['qtd_parcelas'] ?? 0),
                'qtd_pagas' => (string)($promissoria['qtd_pagas'] ?? 0),
                'codigo_contrato' => $promissoria['codigo_contrato_locacao'] ?? '',
                'status' => ((int)($promissoria['qtd_pagas'] ?? 0) === (int)($promissoria['qtd_parcelas'] ?? 0)) ? 'QUITADO' : 'PENDENTE',
            ],
        ];
    }

    /**
     * Monta contexto para renderizacao de templates de parcela
     */
    private function montarContextoParcela(array $parcela, ?array $empresa): array
    {
        // Montar endereco completo do cliente
        $enderecoCompleto = $this->montarEnderecoCompleto(
            $parcela['cliente_endereco'] ?? '',
            $parcela['cliente_numero'] ?? '',
            $parcela['cliente_bairro'] ?? '',
            $parcela['cliente_cidade'] ?? '',
            $parcela['cliente_estado'] ?? '',
            $parcela['cliente_cep'] ?? ''
        );

        return [
            'cliente' => [
                'nome' => $parcela['cliente_nome'] ?? '',
                'cpf_cnpj' => $parcela['cliente_cpf_cnpj'] ?? '',
                'rg' => $parcela['cliente_rg'] ?? '',
                'endereco_completo' => $enderecoCompleto,
            ],
            'empresa' => [
                'cidade' => $empresa['cidade'] ?? ($parcela['cliente_cidade'] ?? ''),
            ],
            'promissoria' => [
                'codigo' => $parcela['codigo_base'] ?? '',
                'codigo_contrato' => $parcela['codigo_contrato_locacao'] ?? '',
            ],
            'parcela' => [
                'numero' => (string)($parcela['numero_parcela'] ?? ''),
                'total' => (string)($parcela['total_parcelas'] ?? ''),
                'valor' => currency_format((float)($parcela['valor_parcela'] ?? 0)),
                'valor_extenso' => currency_format_extenso((float)($parcela['valor_parcela'] ?? 0)),
                'data_vencimento' => !empty($parcela['data_vencimento']) ? date_format(date_create($parcela['data_vencimento']), 'd/m/Y') : '',
                'data_pagamento' => !empty($parcela['data_pagamento']) ? date_format(date_create($parcela['data_pagamento']), 'd/m/Y') : '',
                'status' => ($parcela['pago'] ?? 'N') === 'S' ? 'PAGO' : 'PENDENTE',
            ],
        ];
    }

    /**
     * Monta endereco completo formatado
     */
    private function montarEnderecoCompleto(string $endereco, string $numero, string $bairro, string $cidade, string $estado, string $cep): string
    {
        $partes = [];

        if (!empty($endereco)) {
            $parte = $endereco;
            if (!empty($numero)) {
                $parte .= ', n. ' . $numero;
            }
            $partes[] = $parte;
        }

        if (!empty($bairro)) {
            $partes[] = $bairro;
        }

        if (!empty($cidade)) {
            $cidadeEstado = $cidade;
            if (!empty($estado)) {
                $cidadeEstado .= '/' . $estado;
            }
            $partes[] = $cidadeEstado;
        }

        if (!empty($cep)) {
            $partes[] = 'CEP ' . $cep;
        }

        return implode(', ', $partes);
    }
}
