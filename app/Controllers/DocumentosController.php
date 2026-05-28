<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\Documento;
use App\I18n\TemplateVariables;
use App\I18n\TemplateRenderer;
use App\Services\AuditLogService;
use App\Services\DocumentExtractor;

/**
 * Controller de Documentos
 *
 * Gerencia operacoes CRUD de modelos de documentos (clausulas, contratos, etc.)
 * com variaveis auto-preenchidas pelo sistema.
 */
class DocumentosController
{
    /**
     * Renderiza a pagina de listagem de documentos
     *
     * GET /pages/documentos
     */
    public function view(Request $request): void
    {
        if (!Auth::can('documentos.visualizar')) {
            Response::redirect('/');
            return;
        }

        $html = Template::render('pages.documentos.index');
        Response::html($html);
    }

    /**
     * Renderiza a pagina de adicionar/editar documento
     *
     * GET /pages/documentos/adicionar
     * GET /pages/documentos/{id}/editar
     */
    public function viewAdicionar(Request $request, ?int $id = null): void
    {
        $permissao = $id ? 'documentos.editar' : 'documentos.criar';

        if (!Auth::can($permissao)) {
            Response::redirect('/pages/documentos');
            return;
        }

        $documento = null;
        if ($id) {
            $documentoModel = new Documento();
            $documento = $documentoModel->buscarPorId($id);

            if (!$documento) {
                Response::redirect('/pages/documentos');
                return;
            }

            // Modelos globais (chave=0) podem ser abertos para gerar copia customizada.
            $chave = Auth::chave();
            if ($documento['chave'] !== $chave && $documento['chave'] !== '0') {
                Response::redirect('/pages/documentos');
                return;
            }
        }

        $html = Template::render('pages.documentos.adicionar', [
            'documento' => $documento,
            'tipos' => Documento::TIPOS,
        ]);
        Response::html($html);
    }

    /**
     * Lista todos os documentos do tenant (com paginacao e busca)
     *
     * GET /api/documentos
     * Query params: page, perPage, search, tipo, status
     */
    public function index(Request $request): void
    {
        if (!Auth::can('documentos.visualizar')) {
            Response::json([
                'success' => false,
                'message' => 'Sem permissao para visualizar documentos'
            ], 403);
            return;
        }

        try {
            $documentoModel = new Documento();

            // Parametros de paginacao
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('perPage', 10)));
            $search = $request->query('search', '');
            $tipo = $request->query('tipo');
            $status = $request->query('status');

            // Converter para int ou null
            $tipoInt = $tipo !== null && $tipo !== '' ? (int) $tipo : null;
            $statusInt = $status !== null && $status !== '' ? (int) $status : null;

            // Buscar documentos paginados
            $documentos = $documentoModel->listarPaginado($page, $perPage, $search, $tipoInt, $statusInt);

            // Adicionar nomes de tipo e status
            foreach ($documentos as &$doc) {
                $doc['tipo_nome'] = Documento::getNomeTipo((int) $doc['tipo']);
                $doc['status_nome'] = Documento::getNomeStatus((int) $doc['status']);
            }
            unset($doc);

            // Contar total de registros
            $total = $documentoModel->contar($search, $tipoInt, $statusInt);

            // Calcular total de paginas
            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

            Response::json([
                'success' => true,
                'data' => $documentos,
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
                'message' => 'Erro ao buscar documentos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exibe um documento especifico
     *
     * GET /api/documentos/{id}
     */
    public function show(Request $request, int $id): void
    {
        if (!Auth::can('documentos.visualizar')) {
            Response::json([
                'success' => false,
                'message' => 'Sem permissao para visualizar documentos'
            ], 403);
            return;
        }

        try {
            $documentoModel = new Documento();
            $documento = $documentoModel->buscarPorId($id);

            if (!$documento) {
                Response::json([
                    'success' => false,
                    'message' => 'Documento nao encontrado'
                ], 404);
                return;
            }

            // Verificar se pertence ao tenant ou se e modelo global do sistema
            $chave = Auth::chave();
            if ($documento['chave'] !== $chave && $documento['chave'] !== '0') {
                Response::json([
                    'success' => false,
                    'message' => 'Documento nao encontrado'
                ], 404);
                return;
            }

            // Adicionar nomes de tipo e status
            $documento['tipo_nome'] = Documento::getNomeTipo((int) $documento['tipo']);
            $documento['status_nome'] = Documento::getNomeStatus((int) $documento['status']);

            Response::json([
                'success' => true,
                'data' => $documento
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar documento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retorna variaveis disponiveis para uso no editor
     *
     * GET /api/documentos/variables
     */
    public function variables(Request $request): void
    {
        if (!Auth::can('documentos.visualizar')) {
            Response::json([
                'success' => false,
                'message' => 'Sem permissao'
            ], 403);
            return;
        }

        try {
            $locale = $request->query('locale', 'pt_BR');
            $variables = TemplateVariables::getForFrontend($locale);

            Response::json([
                'success' => true,
                'data' => $variables
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar variaveis: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cria um novo documento
     *
     * POST /documentos/salvar
     */
    public function store(Request $request): void
    {
        if (!Auth::can('documentos.criar')) {
            Response::json([
                'success' => false,
                'message' => 'Sem permissao para criar documentos'
            ], 403);
            return;
        }

        try {
            $dados = $request->all();
            $dados['chave'] = Auth::chave();

            // Validacao basica
            if (empty($dados['titulo'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Titulo e obrigatorio'
                ], 400);
                return;
            }

            $documentoModel = new Documento();
            $id = $documentoModel->criar($dados);

            // Log de auditoria
            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", adicionou documento [{$dados['titulo']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Documento criado com sucesso',
                'data' => ['id' => $id]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao criar documento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza um documento
     *
     * POST /documentos/{id}/atualizar
     */
    public function update(Request $request, int $id): void
    {
        if (!Auth::can('documentos.editar')) {
            Response::json([
                'success' => false,
                'message' => 'Sem permissao para editar documentos'
            ], 403);
            return;
        }

        try {
            $documentoModel = new Documento();
            $documento = $documentoModel->buscarPorId($id);

            if (!$documento) {
                Response::json([
                    'success' => false,
                    'message' => 'Documento nao encontrado'
                ], 404);
                return;
            }

            $chave = Auth::chave();
            if ($documento['chave'] === '0') {
                $dados = $request->all();
                $novoId = $documentoModel->criarCopiaTenant($documento, $chave, $dados);

                AuditLogService::registrar(
                    ($_SESSION['user_name'] ?? 'Sistema') . ", criou copia customizada do documento padrao [{$documento['titulo']}]"
                );

                Response::json([
                    'success' => true,
                    'message' => 'Documento padrao copiado e salvo com sucesso',
                    'data' => [
                        'id' => $novoId,
                        'copied_from_global' => true,
                    ],
                ]);
                return;
            }

            // Verificar se pertence ao tenant
            if ($documento['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao pode editar este documento'
                ], 403);
                return;
            }

            $dados = $request->all();
            $documentoModel->atualizar($id, $dados);

            // Log de auditoria
            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", atualizou documento [{$documento['titulo']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Documento atualizado com sucesso'
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar documento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exclui um documento
     *
     * POST /documentos/{id}/excluir
     */
    public function destroy(Request $request, int $id): void
    {
        if (!Auth::can('documentos.excluir')) {
            Response::json([
                'success' => false,
                'message' => 'Sem permissao para excluir documentos'
            ], 403);
            return;
        }

        try {
            $documentoModel = new Documento();
            $documento = $documentoModel->buscarPorId($id);

            if (!$documento) {
                Response::json([
                    'success' => false,
                    'message' => 'Documento nao encontrado'
                ], 404);
                return;
            }

            // Verificar se pertence ao tenant
            $chave = Auth::chave();
            if ($documento['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao pode excluir este documento'
                ], 403);
                return;
            }

            $documentoModel->excluir($id);

            // Log de auditoria
            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", excluiu documento [{$documento['titulo']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Documento excluido com sucesso'
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao excluir documento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lista documentos para select (busca server-side)
     *
     * GET /api/documentos/buscar
     * Query params: search, tipo
     */
    public function buscar(Request $request): void
    {
        if (!Auth::can('documentos.visualizar')) {
            Response::json([
                'success' => false,
                'message' => 'Sem permissao'
            ], 403);
            return;
        }

        try {
            $search = $request->query('search', '');
            $tipo = $request->query('tipo');

            $tipoInt = $tipo !== null && $tipo !== '' ? (int) $tipo : null;

            $documentoModel = new Documento();
            $documentos = $documentoModel->listarParaSelect($search, $tipoInt);

            Response::json([
                'success' => true,
                'data' => $documentos
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar documentos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Preview de documento com dados de exemplo
     *
     * POST /api/documentos/preview
     */
    public function preview(Request $request): void
    {
        if (!Auth::can('documentos.visualizar')) {
            Response::json([
                'success' => false,
                'message' => 'Sem permissao'
            ], 403);
            return;
        }

        try {
            $dados = $request->all();
            $conteudo = $dados['conteudo'] ?? '';
            $titulo = $dados['titulo'] ?? 'Documento';

            if (empty($conteudo)) {
                Response::json([
                    'success' => false,
                    'message' => 'Conteudo vazio'
                ], 400);
                return;
            }

            // Usar TemplateRenderer para substituir variaveis por exemplos
            $renderer = new TemplateRenderer();
            $conteudoRenderizado = $renderer->preview($conteudo);

            Response::json([
                'success' => true,
                'data' => [
                    'titulo' => $titulo,
                    'conteudo' => $conteudoRenderizado
                ]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao gerar preview: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Extrai texto de arquivo PDF ou DOCX
     *
     * POST /api/documentos/extrair-texto
     * Recebe arquivo via multipart/form-data
     * Retorna HTML extraido para inserir no editor
     */
    public function extrairTexto(Request $request): void
    {
        if (!Auth::can('documentos.criar')) {
            Response::json([
                'success' => false,
                'message' => 'Sem permissao para criar documentos'
            ], 403);
            return;
        }

        try {
            $file = $request->file('arquivo');

            if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
                Response::json([
                    'success' => false,
                    'message' => 'Arquivo nao enviado ou erro no upload'
                ], 400);
                return;
            }

            // Validar e extrair texto
            $extractor = new DocumentExtractor();
            $extractor->validate($file);
            $html = $extractor->extractToHtml($file['tmp_name'], $file['name']);

            // Arquivo temporario e deletado automaticamente pelo PHP,
            // mas garantimos aqui por seguranca
            if (file_exists($file['tmp_name'])) {
                @unlink($file['tmp_name']);
            }

            Response::json([
                'success' => true,
                'data' => [
                    'html' => $html
                ]
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            // Log do erro para debug
            error_log('DocumentExtractor error: ' . $e->getMessage());

            Response::json([
                'success' => false,
                'message' => 'Erro ao processar arquivo. Tente novamente.'
            ], 500);
        }
    }
}
