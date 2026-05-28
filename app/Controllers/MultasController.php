<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\Multa;
use App\Models\Veiculo;
use App\Models\Documento;
use App\Models\Cliente;
use App\Models\MatrizFilial;
use App\Helpers\FilialHelper;
use App\Helpers\FileHelper;
use App\Helpers\PdfHelper;
use App\Config\Planos;
use App\Services\AuditLogService;
use App\I18n\TemplateRenderer;
use SimpleSoftwareIO\QrCode\Generator as QrCodeGenerator;

/**
 * Controller de Multas de Transito
 *
 * Gerencia operacoes CRUD de multas vinculadas a contratos ou locacoes.
 * Integra com o financeiro para lancamento automatico de despesas.
 */
class MultasController
{
    /** @var array<int,string> Arquivos temporarios criados durante geracao de PDF (QR code etc) */
    private array $tmpFiles = [];

    /**
     * Renderiza a pagina de adicionar/editar multa
     *
     * GET /pages/multas/adicionar
     */
    public function viewAdicionar(Request $request): void
    {
        $html = Template::render('pages.multas.adicionar');
        Response::html($html);
    }

    /**
     * Exibe uma multa especifica
     *
     * GET /api/multas/{id}
     */
    public function show(Request $request, int $id): void
    {
        try {
            if (!Auth::can('multas.visualizar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para visualizar multas'
                ], 403);
                return;
            }

            $model = new Multa();
            $multa = $model->buscarPorId($id);

            if (!$multa) {
                Response::json([
                    'success' => false,
                    'message' => 'Multa nao encontrada'
                ], 404);
                return;
            }

            $chave = Auth::chave();
            if ($multa['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Multa nao encontrada'
                ], 404);
                return;
            }

            Response::json([
                'success' => true,
                'data' => $multa
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar multa: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Busca responsavel por multa (placa + data/hora)
     *
     * POST /api/multas/buscar-responsavel
     * Body: { placa, data_hora }
     */
    public function buscarResponsavel(Request $request): void
    {
        try {
            if (!Auth::can('multas.criar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para registrar multas'
                ], 403);
                return;
            }

            $dados = $request->all();
            $placa = trim($dados['placa'] ?? '');
            $dataHora = trim($dados['data_hora'] ?? '');

            if (empty($placa) || empty($dataHora)) {
                Response::json([
                    'success' => false,
                    'message' => 'Placa e data/hora sao obrigatorios'
                ], 400);
                return;
            }

            $chave = Auth::chave();

            // Buscar veiculo por placa
            $veiculoModel = new Veiculo();
            $veiculo = $veiculoModel->buscarPorPlaca($chave, strtoupper(str_replace(['-', ' '], '', $placa)));

            if (!$veiculo) {
                Response::json([
                    'success' => false,
                    'message' => 'Veiculo nao encontrado com esta placa'
                ], 404);
                return;
            }

            // Buscar responsavel
            $multaModel = new Multa();
            $responsavel = $multaModel->buscarResponsavel((int) $veiculo['id'], $dataHora);

            Response::json([
                'success' => true,
                'data' => [
                    'veiculo' => [
                        'id' => (int) $veiculo['id'],
                        'placa' => $veiculo['placa'],
                        'modelo' => $veiculo['modelo'] ?? '',
                        'marca' => $veiculo['marca'] ?? '',
                    ],
                    'responsavel' => $responsavel,
                ]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar responsavel: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cria uma nova multa
     *
     * POST /multas/salvar
     */
    public function store(Request $request): void
    {
        try {
            if (!Auth::can('multas.criar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para registrar multas'
                ], 403);
                return;
            }

            $dados = $request->all();
            $dados['chave'] = Auth::chave();

            // Validar campos obrigatorios
            $erros = [];
            if (empty($dados['data_hora'])) {
                $erros[] = '- Data e Hora';
            }
            if (empty($dados['id_veiculo'])) {
                $erros[] = '- Veiculo';
            }
            if (empty($dados['valor']) || $dados['valor'] === '0,00' || $dados['valor'] === '0.00') {
                $erros[] = '- Valor';
            }
            if (empty($dados['id_cliente'])) {
                $erros[] = '- Cliente';
            }

            if (!empty($erros)) {
                Response::json([
                    'success' => false,
                    'message' => 'Preencha os campos obrigatorios:\n\n' . implode('\n', $erros)
                ], 400);
                return;
            }

            // Processar upload de foto
            if (!empty($dados['foto_base64'])) {
                $filename = FileHelper::save($dados['foto_base64'], 'multa');
                if ($filename) {
                    $dados['foto'] = $filename;
                }
            }

            $model = new Multa();
            $id = $model->criar($dados);

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", registrou multa [#{$id}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Multa registrada com sucesso',
                'data' => ['id' => $id]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao registrar multa: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza uma multa existente
     *
     * POST /multas/{id}/atualizar
     */
    public function update(Request $request, int $id): void
    {
        try {
            if (!Auth::can('multas.editar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para editar multas'
                ], 403);
                return;
            }

            $model = new Multa();
            $multa = $model->buscarPorId($id);

            if (!$multa) {
                Response::json([
                    'success' => false,
                    'message' => 'Multa nao encontrada'
                ], 404);
                return;
            }

            $chave = Auth::chave();
            if ($multa['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao pode editar esta multa'
                ], 403);
                return;
            }

            if (!FilialHelper::temAcessoFilial($multa['id_matriz_filial'] ?? null)) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para editar esta multa'
                ], 403);
                return;
            }

            $dados = $request->all();

            // Processar upload de foto (nova ou substituicao)
            if (!empty($dados['foto_base64'])) {
                // Excluir foto anterior se existir
                if (!empty($multa['foto'])) {
                    FileHelper::delete($multa['foto'], $chave);
                }
                $filename = FileHelper::save($dados['foto_base64'], 'multa');
                if ($filename) {
                    $dados['foto'] = $filename;
                }
            }

            $model->atualizar($id, $dados);

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", atualizou multa [#{$id}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Multa atualizada com sucesso'
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar multa: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exclui uma multa
     *
     * POST /multas/{id}/excluir
     */
    public function destroy(Request $request, int $id): void
    {
        try {
            if (!Auth::can('multas.excluir')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para excluir multas'
                ], 403);
                return;
            }

            $model = new Multa();
            $multa = $model->buscarPorId($id);

            if (!$multa) {
                Response::json([
                    'success' => false,
                    'message' => 'Multa nao encontrada'
                ], 404);
                return;
            }

            $chave = Auth::chave();
            if ($multa['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao pode excluir esta multa'
                ], 403);
                return;
            }

            // Excluir foto se existir
            if (!empty($multa['foto'])) {
                FileHelper::delete($multa['foto'], $chave);
            }

            $model->excluir($id);

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", excluiu multa [#{$id}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Multa excluida com sucesso'
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao excluir multa: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marca multa como paga
     *
     * POST /multas/{id}/marcar-pago
     */
    public function marcarPago(Request $request, int $id): void
    {
        try {
            if (!Auth::can('multas.editar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para editar multas'
                ], 403);
                return;
            }

            $model = new Multa();
            $multa = $model->buscarPorId($id);

            if (!$multa) {
                Response::json([
                    'success' => false,
                    'message' => 'Multa nao encontrada'
                ], 404);
                return;
            }

            $chave = Auth::chave();
            if ($multa['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Multa nao encontrada'
                ], 404);
                return;
            }

            $model->marcarPago($id, date('Y-m-d'));

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", marcou multa [#{$id}] como paga"
            );

            Response::json([
                'success' => true,
                'message' => 'Multa marcada como paga'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao marcar multa como paga: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reverte pagamento da multa
     *
     * POST /multas/{id}/marcar-nao-pago
     */
    public function marcarNaoPago(Request $request, int $id): void
    {
        try {
            if (!Auth::can('multas.editar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Voce nao tem permissao para editar multas'
                ], 403);
                return;
            }

            $model = new Multa();
            $multa = $model->buscarPorId($id);

            if (!$multa) {
                Response::json([
                    'success' => false,
                    'message' => 'Multa nao encontrada'
                ], 404);
                return;
            }

            $chave = Auth::chave();
            if ($multa['chave'] !== $chave) {
                Response::json([
                    'success' => false,
                    'message' => 'Multa nao encontrada'
                ], 404);
                return;
            }

            $model->marcarNaoPago($id);

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", reverteu pagamento da multa [#{$id}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Pagamento revertido'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao reverter pagamento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Renderiza o offcanvas com opcoes de impressao da multa
     *
     * GET /pages/multas/offcanvas-impressao?id=X
     */
    public function offcanvasImpressao(Request $request): void
    {
        $id = (int) $request->query('id');

        $multaModel = new Multa();
        $multa = $multaModel->buscarPorId($id);

        if (!$multa) {
            Response::html('<p>Multa nao encontrada</p>', 404);
            return;
        }

        $chave = Auth::chave();
        if ($multa['chave'] !== $chave) {
            Response::html('<p>Acesso negado</p>', 403);
            return;
        }

        // Buscar documentos disponiveis (apenas tipo=3 Multa)
        $documentoModel = new Documento();
        $todosDocumentos = $documentoModel->listarParaSelect();
        $documentos = array_values(array_filter($todosDocumentos, fn($d) => (int) $d['tipo'] === 3));

        // Verificar plano do tenant para canais de mensageria
        $user = Auth::user();
        $planoCodigo = $user['plano'] ?? 'G';
        $planoInfo = Planos::getPlano($planoCodigo);
        $temEmail = ($planoInfo['smtp'] ?? 0) > 0;
        $temWhatsapp = ($planoInfo['whatsapp'] ?? 0) > 0;
        $temSms = ($planoInfo['sms'] ?? 0) > 0;

        $html = Template::render('pages.multas.offcanvas-impressao', [
            'multa' => $multa,
            'documentos' => $documentos,
            'temEmail' => $temEmail,
            'temWhatsapp' => $temWhatsapp,
            'temSms' => $temSms,
        ]);
        Response::html($html);
    }

    /**
     * Imprime a multa em PDF (notificacao, comprovante, termo de indicacao, documento)
     *
     * GET /multas/{id}/imprimir?tipo=X[&id_documento=Y]
     */
    public function imprimir(Request $request, int $id): void
    {
        try {
            $multaModel = new Multa();
            $multa = $multaModel->buscarPorId($id);

            if (!$multa) {
                Response::html('<h1>Multa nao encontrada</h1>', 404);
                return;
            }

            $chave = Auth::chave();
            if ($multa['chave'] !== $chave) {
                Response::html('<h1>Acesso negado</h1>', 403);
                return;
            }

            if (!FilialHelper::temAcessoFilial($multa['id_matriz_filial'] ?? null)) {
                Response::html('<h1>Acesso negado</h1>', 403);
                return;
            }

            $tipo = $request->query('tipo', 'notificacao');
            $tiposValidos = ['notificacao', 'comprovante', 'termo_indicacao', 'documento'];
            if (!in_array($tipo, $tiposValidos, true)) {
                $tipo = 'notificacao';
            }

            // Pre-requisitos por tipo
            if ($tipo === 'comprovante' && ($multa['pago'] ?? 'N') !== 'S') {
                Response::html('<h1>Comprovante disponivel apenas para multas pagas</h1>', 422);
                return;
            }
            if ($tipo === 'termo_indicacao' && empty($multa['numero_ait'])) {
                Response::html('<h1>Termo de indicacao requer numero AIT</h1>', 422);
                return;
            }

            // Empresa (matriz/filial) para header
            $empresa = $this->buscarDadosEmpresa($multa['id_matriz_filial'] ?? null) ?? [];

            // Logo: caminho LOCAL (mPDF nao consegue baixar URL com token HMAC).
            // Tambem converte WebP para JPEG (mPDF nao suporta WebP).
            $logoPath = PdfHelper::resolveImagePath($empresa['logo'] ?? null, $chave);

            // Cliente (para resolver variaveis no documento personalizado)
            $cliente = null;
            if (!empty($multa['id_cliente'])) {
                $cliente = (new Cliente())->buscarPorIdComContatos((int) $multa['id_cliente']);
            }

            // Documento customizado (apenas para tipo=documento) — aplica TemplateRenderer
            $documento = null;
            if ($tipo === 'documento') {
                $idDocumento = (int) $request->query('id_documento', 0);
                if ($idDocumento <= 0) {
                    Response::html('<h1>Documento nao informado</h1>', 422);
                    return;
                }
                $documentoModel = new Documento();
                $documento = $documentoModel->buscarPorId($idDocumento);
                if (!$documento || (int) ($documento['tipo'] ?? -1) !== 3) {
                    Response::html('<h1>Documento invalido</h1>', 422);
                    return;
                }
                if (!empty($documento['texto'])) {
                    $renderer = new TemplateRenderer();
                    $context = $this->buildDocumentoContextMulta($multa, $cliente, $empresa);
                    $documento['texto'] = $renderer->render($documento['texto'], $context);
                }
            }

            // QR de validacao apenas na notificacao ao cliente
            $qrPath = $tipo === 'notificacao' ? $this->gerarQrCodePath((int) $multa['id']) : '';

            // Output buffering
            ob_start();
            $viewPath = __DIR__ . '/../Views/pages/multas/imprimir/' . $tipo . '.php';
            include $viewPath;
            $html = ob_get_clean();

            $filename = 'multa-' . ($multa['n_infracao'] ?: $multa['id']) . '-' . $tipo . '.pdf';
            $pdfOptions = [
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 5,
                'margin_bottom' => 5,
            ];

            // Documento personalizado: header/footer aplicados via SetHTMLHeader/SetHTMLFooter
            // (Method 4 do mPDF). Os parciais usam estilos inline — mPDF nao processa
            // <style> blocks no contexto de header/footer. Ref:
            // https://mpdf.github.io/headers-footers/method-4.html
            if ($tipo === 'documento') {
                $partialsDir = __DIR__ . '/../Views/pages/multas/imprimir/_partials';

                ob_start();
                $_docTitulo = strtoupper($documento['titulo'] ?? t('modules.multas.pdf.document_title'));
                include $partialsDir . '/_header.php';
                $headerHtml = ob_get_clean();

                $footerHtml = '<div style="text-align:center; font-size:8pt; color:#999;">'
                    . t('modules.multas.pdf.page_label', ['page' => '{PAGENO}', 'total' => '{nbpg}'])
                    . '</div>';

                // Mesmo padrao contratos/locacoes: margens no construtor (orig_tMargin).
                $mpdf = PdfHelper::create(array_merge($pdfOptions, [
                    'margin_top' => PdfHelper::DOCUMENTO_HTML_HEADER_MARGIN_TOP_MM,
                    'margin_bottom' => PdfHelper::DOCUMENTO_MULTAS_HTML_FOOTER_MARGIN_BOTTOM_MM,
                ]));
                // SetHTMLHeader: 3o parametro = true forca aplicacao na pagina atual (1).
                // Sem isso, mPDF so aplica o header a partir da pagina 2.
                $mpdf->SetHTMLHeader($headerHtml, 'O', true);
                $mpdf->SetHTMLFooter($footerHtml, 'O');
                PdfHelper::writeHtml($mpdf, $html);
                $mpdf->Output($filename, 'I');
            } else {
                PdfHelper::outputInline($html, $filename, $pdfOptions);
            }

            $this->limparArquivosTemporarios();
            exit;

        } catch (\Throwable $e) {
            $logFile = APP_ROOT . '/storage/logs/multas-imprimir-error.log';
            @file_put_contents($logFile, date('Y-m-d H:i:s') . ' [' . get_class($e) . '] ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString() . "\n\n", FILE_APPEND);
            Response::html('<h1>Erro ao gerar impressao</h1><pre>' . htmlspecialchars(get_class($e) . ': ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine()) . '</pre>', 500);
        }
    }

    /**
     * Envia o PDF da multa por canal de mensageria (email, whatsapp, sms)
     *
     * POST /multas/{id}/enviar
     * Body JSON: { tipo, canal, id_documento }
     */
    public function enviarMulta(Request $request, int $id): void
    {
        try {
            $data = $request->all();
            $tipo = $data['tipo'] ?? 'notificacao';
            $canal = $data['canal'] ?? 'email';
            $idDocumento = (int) ($data['id_documento'] ?? 0);

            if (!in_array($canal, ['email', 'whatsapp', 'sms'], true)) {
                Response::json(['success' => false, 'message' => 'Canal invalido'], 422);
                return;
            }

            $tiposValidos = ['notificacao', 'comprovante', 'termo_indicacao', 'documento'];
            if (!in_array($tipo, $tiposValidos, true)) {
                Response::json(['success' => false, 'message' => 'Tipo invalido'], 422);
                return;
            }

            $multaModel = new Multa();
            $multa = $multaModel->buscarPorId($id);

            if (!$multa) {
                Response::json(['success' => false, 'message' => 'Multa nao encontrada'], 404);
                return;
            }

            $chave = Auth::chave();
            if ($multa['chave'] !== $chave) {
                Response::json(['success' => false, 'message' => 'Acesso negado'], 403);
                return;
            }

            // Pre-requisitos
            if ($tipo === 'comprovante' && ($multa['pago'] ?? 'N') !== 'S') {
                Response::json(['success' => false, 'message' => 'Comprovante disponivel apenas para multas pagas'], 422);
                return;
            }
            if ($tipo === 'termo_indicacao' && empty($multa['numero_ait'])) {
                Response::json(['success' => false, 'message' => 'Termo de indicacao requer numero AIT'], 422);
                return;
            }

            // Cliente para email/telefone (hidrata contatos_emails/contatos_telefones)
            $cliente = null;
            if (!empty($multa['id_cliente'])) {
                $clienteModel = new Cliente();
                $cliente = $clienteModel->buscarPorIdComContatos((int) $multa['id_cliente']);
            }

            $empresa = $this->buscarDadosEmpresa($multa['id_matriz_filial'] ?? null) ?? [];
            $logoPath = PdfHelper::resolveImagePath($empresa['logo'] ?? null, $chave);
            $nomeEmpresa = $empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? 'Locadora';
            $assuntoBase = t('modules.multas.print.notification') . ' - ' . ($multa['n_infracao'] ?: '#' . $multa['id']);
            $destinatario = $canal === 'email'
                ? ($cliente['email'] ?? '')
                : ($cliente['celular'] ?? $cliente['telefone'] ?? '');

            validate_queue_message($canal, [
                'to' => $destinatario,
                'id_matriz_filial' => $multa['id_matriz_filial'] ?? null,
            ]);

            // Documento customizado — aplica TemplateRenderer para resolver {{multa.X}} etc.
            $documento = null;
            if ($tipo === 'documento') {
                if ($idDocumento <= 0) {
                    Response::json(['success' => false, 'message' => 'Documento nao informado'], 422);
                    return;
                }
                $documentoModel = new Documento();
                $documento = $documentoModel->buscarPorId($idDocumento);
                if (!$documento || (int) ($documento['tipo'] ?? -1) !== 3) {
                    Response::json(['success' => false, 'message' => 'Documento invalido'], 422);
                    return;
                }
                if (!empty($documento['texto'])) {
                    $renderer = new TemplateRenderer();
                    $context = $this->buildDocumentoContextMulta($multa, $cliente, $empresa);
                    $documento['texto'] = $renderer->render($documento['texto'], $context);
                }
            }

            // QR de validacao apenas na notificacao ao cliente
            $qrPath = $tipo === 'notificacao' ? $this->gerarQrCodePath((int) $multa['id']) : '';

            // Gerar PDF como string
            ob_start();
            $viewPath = __DIR__ . '/../Views/pages/multas/imprimir/' . $tipo . '.php';
            include $viewPath;
            $html = ob_get_clean();

            $pdfOptionsEnvio = [
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 5,
                'margin_bottom' => 5,
            ];

            if ($tipo === 'documento') {
                $partialsDir = __DIR__ . '/../Views/pages/multas/imprimir/_partials';
                ob_start();
                $_docTitulo = strtoupper($documento['titulo'] ?? t('modules.multas.pdf.document_title'));
                include $partialsDir . '/_header.php';
                $headerHtml = ob_get_clean();
                $footerHtml = '<div style="text-align:center; font-size:8pt; color:#999;">'
                    . t('modules.multas.pdf.page_label', ['page' => '{PAGENO}', 'total' => '{nbpg}'])
                    . '</div>';
                $mpdf = PdfHelper::create(array_merge($pdfOptionsEnvio, [
                    'margin_top' => PdfHelper::DOCUMENTO_HTML_HEADER_MARGIN_TOP_MM,
                    'margin_bottom' => PdfHelper::DOCUMENTO_MULTAS_HTML_FOOTER_MARGIN_BOTTOM_MM,
                ]));
                $mpdf->SetHTMLHeader($headerHtml, 'O', true);
                $mpdf->SetHTMLFooter($footerHtml, 'O');
                PdfHelper::writeHtml($mpdf, $html);
                $pdfContent = $mpdf->Output('', 'S');
            } else {
                $pdfContent = PdfHelper::generateAsString($html, $pdfOptionsEnvio);
            }

            $filename = 'multa_' . ($multa['n_infracao'] ?: $multa['id']) . '_' . $tipo . '_' . time() . '.pdf';
            $tempDir = rtrim($_SERVER['DOCUMENT_ROOT'] ?? __DIR__ . '/../../public', '/') . '/storage/temp';
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            $tempPath = $tempDir . '/' . $filename;
            file_put_contents($tempPath, $pdfContent);

            if ($canal === 'email') {
                queue_message('email', [
                    'to' => $destinatario,
                    'to_name' => $cliente['nome_rsocial'] ?? '',
                    'subject' => $assuntoBase,
                    'body' => '<p>Segue em anexo o documento referente a multa <strong>' . htmlspecialchars($multa['n_infracao'] ?? '') . '</strong>.</p><p>Atenciosamente,<br>' . htmlspecialchars($nomeEmpresa) . '</p>',
                    'attachments' => [$tempPath],
                    'id_matriz_filial' => $multa['id_matriz_filial'] ?? null,
                ]);
            } elseif ($canal === 'whatsapp') {
                $publicUrl = rtrim(env('APP_URL', ''), '/') . '/storage/temp/' . $filename;
                queue_message('whatsapp', [
                    'to' => $destinatario,
                    'media_url' => $publicUrl,
                    'caption' => $assuntoBase . ' - ' . $nomeEmpresa,
                    'id_matriz_filial' => $multa['id_matriz_filial'] ?? null,
                ]);
            } elseif ($canal === 'sms') {
                queue_message('sms', [
                    'to' => $destinatario,
                    'message' => $assuntoBase . '. ' . $nomeEmpresa,
                    'id_matriz_filial' => $multa['id_matriz_filial'] ?? null,
                ]);
            }

            $this->limparArquivosTemporarios();
            Response::json(['success' => true, 'message' => 'Documento enviado com sucesso']);

        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro ao enviar: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Busca dados da empresa (matriz/filial) para impressao
     */
    private function buscarDadosEmpresa(?int $filialId): ?array
    {
        $matrizFilialModel = new MatrizFilial();
        return $matrizFilialModel->buscarDadosEmpresa($filialId);
    }

    /**
     * Verifica multa publicamente via token opaco (URL do QR code).
     *
     * GET /verificar/multa/{token}
     *
     * Token = base64url(encrypt(id)). Nao expoe id sequencial nem permite iterar.
     */
    public function verificarPublico(Request $request, string $token): void
    {
        $id = $this->decodificarTokenVerificacao($token);

        if ($id === null) {
            $html = Template::render('public.verificar.erro', [
                'titulo' => 'Multa nao encontrada',
                'mensagem' => 'O codigo informado nao foi encontrado ou o link esta incorreto.'
            ]);
            Response::html($html, 404);
            return;
        }

        $multa = (new Multa())->buscarPorId($id);

        if (!$multa) {
            $html = Template::render('public.verificar.erro', [
                'titulo' => 'Multa nao encontrada',
                'mensagem' => 'O codigo informado nao foi encontrado ou o link esta incorreto.'
            ]);
            Response::html($html, 404);
            return;
        }

        $empresa = (new MatrizFilial())->buscarDadosEmpresa($multa['id_matriz_filial'] ?? null) ?? [];

        $html = Template::render('public.verificar.multa', [
            'multa' => $multa,
            'empresa' => $empresa,
        ]);
        Response::html($html);
    }

    /**
     * Codifica id em token opaco URL-safe usando encrypt() global.
     */
    private function gerarTokenVerificacao(int $id): string
    {
        return strtr(rtrim(encrypt((string) $id), '='), '+/', '-_');
    }

    /**
     * Decodifica token opaco para id. Retorna null se invalido.
     */
    private function decodificarTokenVerificacao(string $token): ?int
    {
        $b64 = strtr($token, '-_', '+/');
        $pad = (4 - strlen($b64) % 4) % 4;
        if ($pad > 0) {
            $b64 .= str_repeat('=', $pad);
        }
        $decoded = decrypt($b64);
        return ($decoded !== null && ctype_digit($decoded)) ? (int) $decoded : null;
    }

    /**
     * Gera QR code apontando para a URL publica de verificacao da multa.
     * Salva PNG em arquivo temp e registra em $tmpFiles para cleanup.
     */
    private function gerarQrCodePath(int $id): string
    {
        try {
            $token = $this->gerarTokenVerificacao($id);
            $baseUrl = rtrim(env('APP_URL', ''), '/');
            $url = $baseUrl . '/verificar/multa/' . $token;

            $qrImage = (new QrCodeGenerator())->format('png')->size(120)->generate($url);

            $tmp = sys_get_temp_dir() . '/qr_multa_' . $id . '_' . uniqid() . '.png';
            file_put_contents($tmp, $qrImage);
            $this->tmpFiles[] = $tmp;

            return $tmp;
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Remove arquivos temporarios criados durante a geracao do PDF.
     */
    private function limparArquivosTemporarios(): void
    {
        foreach ($this->tmpFiles as $f) {
            if (file_exists($f)) {
                @unlink($f);
            }
        }
        $this->tmpFiles = [];
    }

    /**
     * Monta o contexto para o TemplateRenderer resolver variaveis {{multa.X}},
     * {{cliente.X}} e {{empresa.X}} dentro de documentos personalizados.
     * Chaves alinhadas com o registro em app/I18n/TemplateVariables.php.
     */
    private function buildDocumentoContextMulta(array $multa, ?array $cliente, array $empresa): array
    {
        return [
            'cliente' => [
                'nome' => $cliente['nome_rsocial'] ?? $multa['cliente_nome'] ?? '',
                'cpf_cnpj' => $cliente['cpf_cnpj'] ?? $multa['cliente_cpf_cnpj'] ?? '',
                'email' => $cliente['email'] ?? '',
                'telefone' => $cliente['telefone'] ?? '',
                'celular' => $cliente['celular'] ?? '',
                'nome_fantasia' => $cliente['nome_fantasia'] ?? '',
                'rg_ie' => $cliente['rg_ie'] ?? '',
                'rg' => $cliente['rg'] ?? '',
                'endereco' => $cliente['rua'] ?? '',
                'numero' => $cliente['numero'] ?? '',
                'complemento' => $cliente['complemento'] ?? '',
                'bairro' => $cliente['bairro'] ?? '',
                'cidade' => $cliente['cidade'] ?? '',
                'uf' => $cliente['estado'] ?? '',
                'cep' => $cliente['cep'] ?? '',
                'pais' => $cliente['pais'] ?? '',
                'cnh_numero' => $cliente['cnh_numero'] ?? '',
                'cnh_validade' => $cliente['cnh_validade'] ?? '',
                'cnh_categoria' => $cliente['cnh_categoria'] ?? '',
                'data_nascimento' => $cliente['nascimento'] ?? '',
                'profissao' => $cliente['profissao'] ?? '',
                'estado_civil' => $cliente['estado_civil'] ?? '',
            ],
            'empresa' => [
                'razao_social' => $empresa['razao_social'] ?? '',
                'nome_fantasia' => $empresa['nome_fantasia'] ?? '',
                'cnpj' => $empresa['cpf_cnpj'] ?? '',
                'email' => $empresa['email'] ?? '',
                'telefone' => $empresa['fixo'] ?? $empresa['celular'] ?? '',
                'whatsapp' => $empresa['celular'] ?? '',
                'endereco' => $empresa['rua'] ?? '',
                'numero' => $empresa['num'] ?? '',
                'bairro' => $empresa['bairro'] ?? '',
                'cidade' => $empresa['cidade'] ?? '',
                'uf' => $empresa['estado'] ?? '',
                'cep' => $empresa['cep'] ?? '',
                'pais' => $empresa['pais'] ?? '',
                'site' => $empresa['site'] ?? '',
                'ie' => $empresa['inscricao_estadual'] ?? '',
                'im' => $empresa['inscricao_municipal'] ?? '',
                'complemento' => $empresa['complemento'] ?? '',
            ],
            'multa' => [
                'local' => $multa['local'] ?? '',
                'cidade' => $multa['cidade'] ?? '',
                'estado' => $multa['estado'] ?? '',
                'data_hora' => $multa['data_hora'] ?? '',
                'data_vencimento' => $multa['data_vencimento'] ?? '',
                'valor' => $multa['valor'] ?? 0,
                'pago' => ($multa['pago'] ?? 'N') === 'S' ? 'Sim' : 'Nao',
                'descricao' => $multa['descri'] ?? '',
                'orgao_autuador' => $multa['orgao_autuador'] ?? '',
                'numero_infracao' => $multa['n_infracao'] ?? '',
            ],
        ];
    }
}
