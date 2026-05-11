<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\Checklist;
use App\Models\MatrizFilial;
use App\Helpers\FileHelper;
use App\Helpers\FilialHelper;
use App\Helpers\PdfHelper;
use App\Services\AuditLogService;
use SimpleSoftwareIO\QrCode\Generator as QrCodeGenerator;

/**
 * Controller de Checklists
 *
 * Gerencia listagem, impressao, exclusao e verificacao publica de checklists.
 */
class ChecklistsController
{
    /**
     * Renderiza a pagina de listagem de checklists
     *
     * GET /pages/checklists
     */
    public function view(Request $request): void
    {
        $html = Template::render('pages.checklists.index');
        Response::html($html);
    }

    /**
     * Lista todos os checklists do tenant (com paginacao e busca)
     *
     * GET /api/checklists
     * Query params: page, perPage, search
     */
    public function index(Request $request): void
    {
        try {
            if (!Auth::can('checklists.visualizar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para visualizar checklists'
                ], 403);
                return;
            }

            $chave = Auth::chave();

            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('perPage', 10)));
            $search = $request->query('search', '');

            // Filtro de filial pelo veiculo (permite NULL para checklists sem veiculo vinculado)
            [$filialWhereBase, $filialParams] = FilialHelper::whereFiliais('v.id_matriz_filial');
            $filialWhere = $filialWhereBase === '1=1' ? '1=1' : '(' . $filialWhereBase . ' OR v.id_matriz_filial IS NULL)';

            $model = new Checklist();

            $checklists = $model->listarPaginado($chave, $page, $perPage, $search, $filialWhere, $filialParams);
            $total = $model->contar($chave, $search, $filialWhere, $filialParams);

            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

            Response::json([
                'success' => true,
                'data' => $checklists,
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
                'message' => 'Erro ao buscar checklists: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Gera PDF do checklist
     *
     * GET /checklists/{id}/imprimir
     */
    /**
     * Arquivos temporarios criados durante a geracao do PDF
     * @var string[]
     */
    private array $tmpFiles = [];

    public function imprimir(Request $request, int $id): void
    {
        try {
            if (!Auth::can('checklists.visualizar')) {
                Response::html('<h1>Acesso negado</h1>', 403);
                return;
            }

            $model = new Checklist();
            $checklist = $model->buscarPorId($id);

            if (!$checklist) {
                Response::html('<h1>Checklist nao encontrado</h1>', 404);
                return;
            }

            $chave = Auth::chave();
            if ($checklist['chave'] !== $chave) {
                Response::html('<h1>Acesso negado</h1>', 403);
                return;
            }

            if (!FilialHelper::temAcessoFilial($checklist['id_matriz_filial'] ?? null)) {
                Response::html('<h1>Acesso negado</h1>', 403);
                return;
            }

            // Buscar dados da empresa
            $matrizFilialModel = new MatrizFilial();
            $empresa = $matrizFilialModel->buscarDadosEmpresa($checklist['id_matriz_filial'] ?? null);

            // Logo da empresa (caminho absoluto para mPDF)
            $logoPath = PdfHelper::resolveImagePath($empresa['logo'] ?? null, $chave);

            // QR Code para verificacao publica (salvo em /tmp)
            $qrPath = $this->gerarQrCodePath($checklist['codigo'] ?? '');

            // Pareamento: se vinculado, buscar registro oposto (saida <-> chegada)
            $isVinculado = ($checklist['tipo'] ?? '') === 'V';
            $par = null;
            if ($isVinculado) {
                $par = $model->buscarPar($checklist);
            }

            // Determinar qual eh saida e qual eh chegada
            $momento = $checklist['momento'] ?? 'S';
            if ($momento === 'C') {
                $regSaida = $par;
                $regChegada = $checklist;
            } else {
                $regSaida = $checklist;
                $regChegada = $par;
            }

            // Decodificar JSON das questoes
            $questoesSaida = json_decode($regSaida['questoes'] ?? $regSaida['questoes_saida'] ?? '[]', true) ?: [];
            $questoesChegada = $regChegada ? (json_decode($regChegada['questoes'] ?? $regChegada['questoes_saida'] ?? '[]', true) ?: []) : [];

            // Resolver caminhos das assinaturas
            $assinaturaPath = PdfHelper::resolveImagePath($regSaida['assinatura_unica'] ?? $regSaida['assinatura'] ?? null, $chave);
            $assinaturaChegadaPath = $regChegada ? PdfHelper::resolveImagePath($regChegada['assinatura_unica'] ?? $regChegada['assinatura'] ?? null, $chave) : '';

            // Decodificar e carregar fotos da vistoria
            $vistoriaSaida = $this->carregarFotosVistoria(
                json_decode($regSaida['vistoria'] ?? $regSaida['vistoria_saida'] ?? '[]', true) ?: [],
                $chave
            );
            $vistoriaChegada = $regChegada ? $this->carregarFotosVistoria(
                json_decode($regChegada['vistoria'] ?? $regChegada['vistoria_saida'] ?? '[]', true) ?: [],
                $chave
            ) : [];

            // Montar dados de obs e datas para o template (compatibilidade)
            $checklist['obs'] = $regSaida['obs_unica'] ?? $regSaida['obs'] ?? '';
            $checklist['obs_chegada'] = $regChegada['obs_unica'] ?? $regChegada['obs'] ?? '';
            $checklist['data_saida'] = $regSaida['data_checklist'] ?? $regSaida['data_saida'] ?? null;
            $checklist['data_chegada'] = $regChegada['data_checklist'] ?? $regChegada['data_saida'] ?? null;

            // Selecionar template baseado no tipo e orientacao
            $orientacao = strtoupper($request->query('orientacao', ''));
            $usarVinculado = $isVinculado;
            $templateFilename = $usarVinculado ? 'template-vinculado.php' : 'template.php';

            // Capturar HTML via output buffering
            ob_start();
            $viewPath = __DIR__ . '/../Views/pages/checklists/imprimir/' . $templateFilename;
            extract([
                'checklist' => $checklist,
                'empresa' => $empresa,
                'logoPath' => $logoPath,
                'qrPath' => $qrPath,
                'questoesSaida' => $questoesSaida,
                'questoesChegada' => $questoesChegada,
                'vistoriaSaida' => $vistoriaSaida,
                'vistoriaChegada' => $vistoriaChegada,
                'assinaturaPath' => $assinaturaPath,
                'assinaturaChegadaPath' => $assinaturaChegadaPath,
                'orientacao' => $orientacao
            ]);
            include $viewPath;
            $html = ob_get_clean();

            // Gerar PDF (landscape para vinculado lado a lado, portrait para demais)
            $mpdf = PdfHelper::create([
                'format' => 'A4',
                'orientation' => $orientacao === 'L' ? 'L' : 'P',
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 5,
                'margin_bottom' => 30
            ]);

            $mpdf->WriteHTML($html);
            $mpdf->Output('checklist-' . ($checklist['codigo'] ?? $id) . '.pdf', 'I');

            $this->limparArquivosTemporarios();
            exit;
        } catch (\Exception $e) {
            $this->limparArquivosTemporarios();
            Response::html('<h1>Erro ao gerar PDF: ' . htmlspecialchars($e->getMessage()) . '</h1>', 500);
        }
    }

    /**
     * Pagina publica de verificacao do checklist (sem autenticacao)
     *
     * GET /verificar/checklist/{codigo}
     */
    public function verificarPublico(Request $request, string $codigo): void
    {
        $model = new Checklist();
        $checklist = $model->buscarPorCodigo($codigo);

        if (!$checklist) {
            $html = Template::render('public.verificar.erro', [
                'titulo' => 'Checklist nao encontrado',
                'mensagem' => 'O codigo informado nao foi encontrado ou o link esta incorreto.'
            ]);
            Response::html($html, 404);
            return;
        }

        // Buscar dados da empresa para logo
        $matrizFilialModel = new MatrizFilial();
        $empresa = $matrizFilialModel->buscarDadosEmpresa($checklist['id_matriz_filial'] ?? null);

        $html = Template::render('public.verificar.checklist', [
            'checklist' => $checklist,
            'empresa' => $empresa
        ]);
        Response::html($html);
    }

    /**
     * Exclui um checklist
     *
     * POST /checklists/{id}/excluir
     */
    public function destroy(Request $request, int $id): void
    {
        try {
            if (!Auth::can('checklists.excluir')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para excluir checklists'
                ], 403);
                return;
            }

            $model = new Checklist();
            $checklist = $model->buscarPorId($id);

            if (!$checklist) {
                Response::json([
                    'success' => false,
                    'message' => 'Checklist nao encontrado'
                ], 404);
                return;
            }

            $chave = Auth::chave();
            if ($checklist['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Checklist nao encontrado'
                ], 404);
                return;
            }

            if (!FilialHelper::temAcessoFilial($checklist['id_matriz_filial'] ?? null)) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem acesso a esta filial'
                ], 403);
                return;
            }

            $model->excluirComArquivos($id, $chave);

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", excluiu checklist [{$checklist['codigo']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Checklist excluido com sucesso'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao excluir checklist: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Gera QR code e salva em arquivo temporario para uso em mPDF
     */
    private function gerarQrCodePath(string $codigo): string
    {
        if (empty($codigo)) {
            return '';
        }

        try {
            $baseUrl = rtrim(Database::env('APP_URL', ''), '/');
            $url = $baseUrl . '/verificar/checklist/' . $codigo;

            $qrGenerator = new QrCodeGenerator();
            $qrImage = $qrGenerator->format('png')->size(120)->generate($url);

            $tmpPath = sys_get_temp_dir() . '/qr_checklist_' . $codigo . '.png';
            file_put_contents($tmpPath, $qrImage);
            $this->tmpFiles[] = $tmpPath;

            return $tmpPath;
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Resolve caminhos absolutos das fotos da vistoria para uso em mPDF.
     * PdfHelper::resolveImagePath converte WebP para JPEG temporario (performance) e
     * agenda cleanup automatico ao final do request.
     */
    private function carregarFotosVistoria(array $vistoria, string $chave): array
    {
        foreach ($vistoria as &$item) {
            $item['img_path'] = PdfHelper::resolveImagePath($item['img'] ?? null, $chave);
        }
        return $vistoria;
    }

    /**
     * Remove arquivos temporarios criados durante a geracao do PDF
     */
    private function limparArquivosTemporarios(): void
    {
        foreach ($this->tmpFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        $this->tmpFiles = [];
    }
}
