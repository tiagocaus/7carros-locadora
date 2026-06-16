<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\NFSe;
use App\Models\NFSeConfiguracao;
use App\Models\NFSeEvento;
use App\Helpers\FilialHelper;
use App\Services\NFSe\NFSeService;
use App\Services\NFSe\NFSeCertificado;

/**
 * Controller de NFS-e (Nota Fiscal de Servico Eletronica)
 *
 * Gerencia emissao, cancelamento, consulta, configuracoes e certificados.
 *
 * Permissoes:
 * - nfse.visualizar
 * - nfse.criar
 * - nfse.excluir
 * - nfse.configurar
 */
class NFSeController
{
    // ==========================================
    // VIEWS (Paginas iframe)
    // ==========================================

    /**
     * Pagina de listagem de NFS-e
     *
     * GET /pages/nfse
     */
    public function view(Request $request): void
    {
        $html = Template::render('pages.nfse.index');
        Response::html($html);
    }

    /**
     * Pagina de emissao de NFS-e
     *
     * GET /pages/nfse/emitir
     */
    public function viewEmitir(Request $request): void
    {
        $html = Template::render('pages.nfse.emitir');
        Response::html($html);
    }

    /**
     * Pagina de visualizacao de NFS-e
     *
     * GET /pages/nfse/{id}/visualizar
     */
    public function viewVisualizar(Request $request, int $id): void
    {
        $html = Template::render('pages.nfse.visualizar');
        Response::html($html);
    }

    /**
     * Pagina de cancelamento de NFS-e
     *
     * GET /pages/nfse/{id}/cancelar
     */
    public function viewCancelar(Request $request, int $id): void
    {
        $html = Template::render('pages.nfse.cancelar');
        Response::html($html);
    }

    /**
     * Pagina de configuracoes de NFS-e
     *
     * GET /pages/nfse/configuracoes
     */
    public function viewConfiguracoes(Request $request): void
    {
        $html = Template::render('pages.nfse.configuracoes');
        Response::html($html);
    }

    // ==========================================
    // Validacao de acesso
    // ==========================================

    /**
     * Valida que NFS-e existe, pertence ao tenant e usuario tem acesso a filial
     *
     * @return array|null NFS-e ou null (ja envia response de erro)
     */
    private function validarAcessoNFSe(int $id): ?array
    {
        $nfseModel = new NFSe();
        $nfse = $nfseModel->buscarPorId($id);

        if (!$nfse || $nfse['chave'] !== Auth::chave()) {
            Response::json(['success' => false, 'message' => 'NFS-e nao encontrada'], 404);
            return null;
        }

        if (!FilialHelper::temAcessoFilial($nfse['id_matriz_filial'] ?? null)) {
            Response::json(['success' => false, 'message' => 'Voce nao tem permissao para acessar esta NFS-e'], 403);
            return null;
        }

        return $nfse;
    }

    // ==========================================
    // API - Leitura
    // ==========================================

    /**
     * Lista NFS-e com paginacao e filtros
     *
     * GET /api/nfse
     */
    public function index(Request $request): void
    {
        try {
            if (!Auth::can('nfse.visualizar')) {
                Response::json(['success' => false, 'message' => 'Voce nao tem permissao para visualizar NFS-e'], 403);
                return;
            }

            $chave = Auth::chave();
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('perPage', 10)));
            $search = $request->query('search', '');
            $filialId = $request->query('filial', '');
            $status = $request->query('status', '');
            $dataInicio = $request->query('data_inicio', '');
            $dataFim = $request->query('data_fim', '');
            $ambiente = $request->query('ambiente', '');

            // Validar acesso a filial selecionada
            if (!empty($filialId) && !FilialHelper::temAcessoFilial((int) $filialId)) {
                Response::json(['success' => false, 'message' => 'Voce nao tem permissao para acessar esta filial'], 403);
                return;
            }

            // Filtro de filial (permissoes do usuario)
            [$filialWhere, $filialParams] = FilialHelper::whereFiliais('id_matriz_filial');

            $nfseModel = new NFSe();

            $notas = $nfseModel->listarPaginado(
                $chave, $page, $perPage, $search,
                $filialWhere, $filialParams,
                $filialId, $status, $dataInicio, $dataFim, $ambiente
            );

            $total = $nfseModel->contar(
                $chave, $search,
                $filialWhere, $filialParams,
                $filialId, $status, $dataInicio, $dataFim, $ambiente
            );

            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

            Response::json([
                'success' => true,
                'data' => $notas,
                'pagination' => [
                    'page' => $page,
                    'perPage' => $perPage,
                    'total' => $total,
                    'totalPages' => $totalPages,
                    'hasNext' => $page < $totalPages,
                    'hasPrev' => $page > 1,
                ],
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro ao buscar NFS-e: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Exibe uma NFS-e com dados completos
     *
     * GET /api/nfse/{id}
     */
    public function show(Request $request, int $id): void
    {
        try {
            if (!Auth::can('nfse.visualizar')) {
                Response::json(['success' => false, 'message' => 'Voce nao tem permissao para visualizar NFS-e'], 403);
                return;
            }

            $nfse = $this->validarAcessoNFSe($id);
            if ($nfse === null) {
                return;
            }

            Response::json(['success' => true, 'data' => $nfse]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro ao buscar NFS-e: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Estatisticas de NFS-e (contadores por status)
     *
     * GET /api/nfse/estatisticas
     */
    public function estatisticas(Request $request): void
    {
        try {
            if (!Auth::can('nfse.visualizar')) {
                Response::json(['success' => false, 'message' => 'Sem permissao'], 403);
                return;
            }

            [$filialWhere, $filialParams] = FilialHelper::whereFiliais('id_matriz_filial');
            $dataInicio = $request->query('data_inicio', '');
            $dataFim = $request->query('data_fim', '');
            $filialId = $request->query('filial', '');

            $nfseModel = new NFSe();
            $stats = $nfseModel->estatisticas(Auth::chave(), $filialWhere, $filialParams, $dataInicio, $dataFim, $filialId);

            Response::json(['success' => true, 'data' => $stats]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro ao buscar estatisticas: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Lista eventos de uma NFS-e
     *
     * GET /api/nfse/{id}/eventos
     */
    public function eventos(Request $request, int $id): void
    {
        try {
            if (!Auth::can('nfse.visualizar')) {
                Response::json(['success' => false, 'message' => 'Sem permissao'], 403);
                return;
            }

            $nfse = $this->validarAcessoNFSe($id);
            if ($nfse === null) {
                return;
            }

            $eventoModel = new NFSeEvento();
            $eventos = $eventoModel->listarPorNfse($id);

            Response::json(['success' => true, 'data' => $eventos]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro ao buscar eventos: ' . $e->getMessage()], 500);
        }
    }

    // ==========================================
    // ACOES - Escrita
    // ==========================================

    /**
     * Emite NFS-e a partir de um financeiro
     *
     * POST /nfse/emitir
     */
    public function emitir(Request $request): void
    {
        try {
            if (!Auth::can('nfse.criar')) {
                Response::json(['success' => false, 'message' => 'Voce nao tem permissao para emitir NFS-e'], 403);
                return;
            }

            $dados = $request->all();
            $idFinanceiro = (int) ($dados['id_financeiro'] ?? 0);

            if ($idFinanceiro <= 0) {
                Response::json(['success' => false, 'message' => 'ID do financeiro e obrigatorio'], 400);
                return;
            }

            $chave = Auth::chave();
            $dadosExtras = [
                'valor_deducoes' => (float) ($dados['valor_deducoes'] ?? 0),
                'descricao_servico' => $dados['descricao_servico'] ?? '',
                'iss_retido' => $dados['iss_retido'] ?? 'N',
                'tomador_cpf_cnpj' => $dados['tomador_cpf_cnpj'] ?? '',
                'tomador_nome' => $dados['tomador_nome'] ?? '',
                'tomador_email' => $dados['tomador_email'] ?? '',
                'itens_nao_tributaveis' => $dados['itens_nao_tributaveis'] ?? [],
            ];

            $service = new NFSeService();
            $resultado = $service->emitir($idFinanceiro, $chave, $dadosExtras);

            $httpCode = ($resultado['sucesso'] ?? false) ? 200 : 422;
            Response::json([
                'success' => $resultado['sucesso'] ?? false,
                'message' => $resultado['mensagem'] ?? '',
                'data' => $resultado['dados'] ?? null,
                'erro' => $resultado['erro'] ?? null,
            ], $httpCode);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro ao emitir NFS-e: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Cancela NFS-e autorizada
     *
     * POST /nfse/{id}/cancelar
     */
    public function cancelar(Request $request, int $id): void
    {
        try {
            if (!Auth::can('nfse.excluir')) {
                Response::json(['success' => false, 'message' => 'Voce nao tem permissao para cancelar NFS-e'], 403);
                return;
            }

            $nfse = $this->validarAcessoNFSe($id);
            if ($nfse === null) {
                return;
            }

            $motivo = trim($request->input('motivo', ''));
            if (strlen($motivo) < 15) {
                Response::json(['success' => false, 'message' => 'Motivo do cancelamento deve ter no minimo 15 caracteres'], 400);
                return;
            }

            $service = new NFSeService();
            $resultado = $service->cancelar($id, $motivo, Auth::chave());

            $httpCode = ($resultado['sucesso'] ?? false) ? 200 : 422;
            Response::json([
                'success' => $resultado['sucesso'] ?? false,
                'message' => $resultado['mensagem'] ?? '',
                'erro' => $resultado['erro'] ?? null,
            ], $httpCode);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro ao cancelar NFS-e: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Consulta status de NFS-e na SEFIN/prefeitura
     *
     * POST /nfse/{id}/consultar
     */
    public function consultar(Request $request, int $id): void
    {
        try {
            if (!Auth::can('nfse.visualizar')) {
                Response::json(['success' => false, 'message' => 'Sem permissao'], 403);
                return;
            }

            $nfse = $this->validarAcessoNFSe($id);
            if ($nfse === null) {
                return;
            }

            $service = new NFSeService();
            $resultado = $service->consultar($id, Auth::chave());

            $httpCode = ($resultado['sucesso'] ?? false) ? 200 : 422;
            Response::json([
                'success' => $resultado['sucesso'] ?? false,
                'message' => $resultado['mensagem'] ?? '',
                'data' => $resultado['dados'] ?? null,
            ], $httpCode);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro ao consultar NFS-e: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Reenvia NFS-e rejeitada
     *
     * POST /nfse/{id}/reenviar
     */
    public function reenviar(Request $request, int $id): void
    {
        try {
            if (!Auth::can('nfse.criar')) {
                Response::json(['success' => false, 'message' => 'Voce nao tem permissao para reenviar NFS-e'], 403);
                return;
            }

            $nfse = $this->validarAcessoNFSe($id);
            if ($nfse === null) {
                return;
            }

            $service = new NFSeService();
            $resultado = $service->reenviar($id, Auth::chave());

            $httpCode = ($resultado['sucesso'] ?? false) ? 200 : 422;
            Response::json([
                'success' => $resultado['sucesso'] ?? false,
                'message' => $resultado['mensagem'] ?? '',
                'data' => $resultado['dados'] ?? null,
            ], $httpCode);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro ao reenviar NFS-e: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Envia NFS-e por email ao tomador
     *
     * POST /nfse/{id}/email
     */
    public function enviarEmail(Request $request, int $id): void
    {
        try {
            if (!Auth::can('nfse.visualizar')) {
                Response::json(['success' => false, 'message' => 'Sem permissao'], 403);
                return;
            }

            $nfse = $this->validarAcessoNFSe($id);
            if ($nfse === null) {
                return;
            }

            $service = new NFSeService();
            $resultado = $service->enviarPorEmail($id, Auth::chave());

            $httpCode = ($resultado['sucesso'] ?? false) ? 200 : 422;
            Response::json([
                'success' => $resultado['sucesso'] ?? false,
                'message' => $resultado['mensagem'] ?? '',
            ], $httpCode);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro ao enviar email: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Download do PDF da NFS-e
     *
     * GET /nfse/{id}/pdf
     */
    public function downloadPdf(Request $request, int $id): void
    {
        try {
            if (!Auth::can('nfse.visualizar')) {
                Response::json(['success' => false, 'message' => 'Sem permissao'], 403);
                return;
            }

            $nfse = $this->validarAcessoNFSe($id);
            if ($nfse === null) {
                return;
            }

            // Gerar PDF se nao existe
            if (empty($nfse['pdf_url'])) {
                $pdf = new \App\Services\NFSe\NFSePDF();
                $pdfResult = $pdf->gerar($nfse);
                if (!$pdfResult['sucesso']) {
                    Response::json(['success' => false, 'message' => 'Erro ao gerar PDF'], 500);
                    return;
                }
                $nfseModel->salvarPdfUrl($id, $pdfResult['caminho']);
                $nfse['pdf_url'] = $pdfResult['caminho'];
            }

            $pdf = new \App\Services\NFSe\NFSePDF();
            $caminhoCompleto = $pdf->getCaminhoCompleto($nfse['pdf_url']);

            if (!file_exists($caminhoCompleto)) {
                Response::json(['success' => false, 'message' => 'Arquivo PDF nao encontrado'], 404);
                return;
            }

            $nomeArquivo = 'nfse_' . ($nfse['numero'] ?? $id) . '.pdf';

            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $nomeArquivo . '"');
            header('Content-Length: ' . filesize($caminhoCompleto));
            readfile($caminhoCompleto);
            exit;
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro ao gerar PDF: ' . $e->getMessage()], 500);
        }
    }

    // ==========================================
    // CONFIGURACOES
    // ==========================================

    /**
     * Busca configuracao de NFS-e da filial
     *
     * GET /api/nfse/configuracoes
     */
    public function getConfiguracoes(Request $request): void
    {
        try {
            if (!Auth::can('nfse.configurar') && !Auth::can('nfse.criar')) {
                Response::json(['success' => false, 'message' => 'Sem permissao para consultar configuracoes de NFS-e'], 403);
                return;
            }

            $filialId = (int) $request->query('filial', 0);
            if ($filialId <= 0) {
                Response::json(['success' => false, 'message' => 'Filial nao informada'], 400);
                return;
            }

            if (!FilialHelper::temAcessoFilial($filialId)) {
                Response::json(['success' => false, 'message' => 'Sem acesso a esta filial'], 403);
                return;
            }

            $configModel = new NFSeConfiguracao();
            $config = $configModel->buscarPorMatrizFilial($filialId);

            // Adicionar info do certificado ANTES de mascarar senha
            if ($config && !empty($config['certificado_arquivo'])) {
                $cert = new NFSeCertificado();
                $config['certificado_dias_expiracao'] = $cert->diasParaExpirar(
                    Auth::chave(),
                    $config['certificado_arquivo'],
                    $config['certificado_senha'] ?? ''
                );
            }

            // Mascarar senha DEPOIS de usar
            if ($config && !empty($config['certificado_senha'])) {
                $config['certificado_senha'] = '***';
            }

            Response::json(['success' => true, 'data' => $config]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro ao buscar configuracoes: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Salva configuracoes gerais de NFS-e
     *
     * POST /nfse/configuracoes/salvar
     */
    public function salvarConfiguracoes(Request $request): void
    {
        try {
            if (!Auth::can('nfse.configurar')) {
                Response::json(['success' => false, 'message' => 'Sem permissao para configurar NFS-e'], 403);
                return;
            }

            $dados = $request->all();
            $filialId = (int) ($dados['id_matriz_filial'] ?? 0);

            if ($filialId <= 0) {
                Response::json(['success' => false, 'message' => 'Filial nao informada'], 400);
                return;
            }

            if (!FilialHelper::temAcessoFilial($filialId)) {
                Response::json(['success' => false, 'message' => 'Sem acesso a esta filial'], 403);
                return;
            }

            $configModel = new NFSeConfiguracao();
            $configModel->salvar($filialId, Auth::chave(), $dados);

            Response::json(['success' => true, 'message' => 'Configuracoes salvas com sucesso']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro ao salvar configuracoes: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Upload de certificado digital
     *
     * POST /nfse/configuracoes/certificado
     */
    public function uploadCertificado(Request $request): void
    {
        try {
            if (!Auth::can('nfse.configurar')) {
                Response::json(['success' => false, 'message' => 'Sem permissao para configurar NFS-e'], 403);
                return;
            }

            $filialId = (int) ($request->input('id_matriz_filial', 0));
            $senha = $request->input('certificado_senha', '');

            if ($filialId <= 0) {
                Response::json(['success' => false, 'message' => 'Filial nao informada'], 400);
                return;
            }

            if (!FilialHelper::temAcessoFilial($filialId)) {
                Response::json(['success' => false, 'message' => 'Sem acesso a esta filial'], 403);
                return;
            }

            if (empty($senha)) {
                Response::json(['success' => false, 'message' => 'Senha do certificado e obrigatoria'], 400);
                return;
            }

            if (!isset($_FILES['certificado']) || $_FILES['certificado']['error'] !== UPLOAD_ERR_OK) {
                Response::json(['success' => false, 'message' => 'Arquivo do certificado nao enviado'], 400);
                return;
            }

            $chave = Auth::chave();
            $cert = new NFSeCertificado();

            // Remover certificado anterior
            $configModel = new NFSeConfiguracao();
            $configAtual = $configModel->buscarPorMatrizFilial($filialId);
            if ($configAtual && !empty($configAtual['certificado_arquivo'])) {
                $cert->remover($chave, $configAtual['certificado_arquivo']);
            }

            // Upload novo
            $resultado = $cert->upload($_FILES['certificado'], $filialId, $chave, $senha);

            if (!$resultado['sucesso']) {
                Response::json(['success' => false, 'message' => $resultado['mensagem']], 422);
                return;
            }

            // Salvar no banco (precisa ter config criada antes)
            if (!$configAtual) {
                $configModel->salvar($filialId, $chave, []);
            }

            $configModel->atualizarCertificado($filialId, [
                'certificado_arquivo' => $resultado['arquivo'],
                'certificado_senha' => $resultado['senha_criptografada'],
                'certificado_validade' => $resultado['dados']['valido_ate'] ?? null,
            ]);

            Response::json([
                'success' => true,
                'message' => $resultado['mensagem'],
                'data' => $resultado['dados'] ?? null,
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro ao enviar certificado: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove certificado digital
     *
     * POST /nfse/configuracoes/certificado/remover
     */
    public function removerCertificado(Request $request): void
    {
        try {
            if (!Auth::can('nfse.configurar')) {
                Response::json(['success' => false, 'message' => 'Sem permissao para configurar NFS-e'], 403);
                return;
            }

            $filialId = (int) ($request->input('id_matriz_filial', 0));

            if ($filialId <= 0) {
                Response::json(['success' => false, 'message' => 'Filial nao informada'], 400);
                return;
            }

            if (!FilialHelper::temAcessoFilial($filialId)) {
                Response::json(['success' => false, 'message' => 'Sem acesso a esta filial'], 403);
                return;
            }

            $chave = Auth::chave();
            $configModel = new NFSeConfiguracao();
            $config = $configModel->buscarPorMatrizFilial($filialId);

            if ($config && !empty($config['certificado_arquivo'])) {
                $cert = new NFSeCertificado();
                $cert->remover($chave, $config['certificado_arquivo']);
            }

            $configModel->removerCertificado($filialId);

            Response::json(['success' => true, 'message' => 'Certificado removido com sucesso']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro ao remover certificado: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Testa conexao com SEFIN/prefeitura
     *
     * POST /nfse/configuracoes/testar-conexao
     */
    public function testarConexao(Request $request): void
    {
        try {
            if (!Auth::can('nfse.configurar')) {
                Response::json(['success' => false, 'message' => 'Sem permissao para configurar NFS-e'], 403);
                return;
            }

            $filialId = (int) ($request->input('id_matriz_filial', 0));

            if ($filialId <= 0) {
                Response::json(['success' => false, 'message' => 'Filial nao informada'], 400);
                return;
            }

            if (!FilialHelper::temAcessoFilial($filialId)) {
                Response::json(['success' => false, 'message' => 'Sem acesso a esta filial'], 403);
                return;
            }

            $chave = Auth::chave();
            $configModel = new NFSeConfiguracao();
            $config = $configModel->buscarPorMatrizFilial($filialId);

            if (!$config || empty($config['certificado_arquivo']) || empty($config['certificado_senha'])) {
                Response::json(['success' => false, 'message' => 'Certificado digital nao configurado'], 422);
                return;
            }

            $cert = new NFSeCertificado();
            $pem = $cert->extrairPEM($chave, $config['certificado_arquivo'], $config['certificado_senha']);

            try {
                $tipoEmissao = $config['tipo_emissao'] ?? 'nacional';
                $api = match ($tipoEmissao) {
                    'nacional' => new \App\Services\NFSe\Nacional\NFSeAPINacional(),
                    'betha' => new \App\Services\NFSe\Betha\NFSeAPIBetha(),
                    default => throw new \InvalidArgumentException('Tipo de emissão NFS-e não suportado: ' . $tipoEmissao),
                };

                $resultado = $api->testarConexao($pem['certPath'], $pem['keyPath'], (int) ($config['ambiente'] ?? 2));

                $httpCode = ($resultado['sucesso'] ?? false) ? 200 : 422;
                Response::json([
                    'success' => $resultado['sucesso'] ?? false,
                    'message' => $resultado['mensagem'] ?? '',
                ], $httpCode);
            } finally {
                $cert->limparPEM($pem['certPath'], $pem['keyPath']);
            }
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro ao testar conexao: ' . $e->getMessage()], 500);
        }
    }
}
