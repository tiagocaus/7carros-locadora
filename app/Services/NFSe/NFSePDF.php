<?php

namespace App\Services\NFSe;

use App\Helpers\PdfHelper;
use App\Models\MatrizFilial;
use SimpleSoftwareIO\QrCode\Generator as QrCodeGenerator;

/**
 * Geracao de DANFSE (Documento Auxiliar da NFS-e) em PDF
 *
 * Usa PdfHelper::saveToFile() para gerar e salvar o PDF.
 * Armazena em storage/uploads/{chave}/nfse/
 */
class NFSePDF
{
    private string $basePath;

    public function __construct()
    {
        $this->basePath = dirname(__DIR__, 3) . '/storage/uploads';
    }

    /**
     * Gera PDF da DANFSE e salva em arquivo
     *
     * @param array $dadosNFSe Dados completos da NFS-e
     * @return array ['sucesso' => bool, 'caminho' => string, 'mensagem' => string]
     */
    public function gerar(array $dadosNFSe): array
    {
        try {
            $chave = $dadosNFSe['chave'] ?? '';
            $numero = $dadosNFSe['numero'] ?? 0;
            $serie = $dadosNFSe['serie'] ?? 'DPS';

            // Criar diretorio
            $dir = $this->basePath . '/' . $chave . '/nfse';
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // Gerar HTML
            $html = $this->gerarHTML($dadosNFSe);

            // Nome do arquivo
            $nomeArquivo = "nfse_{$numero}_{$serie}_" . time() . '.pdf';
            $caminhoCompleto = $dir . '/' . $nomeArquivo;

            // Salvar PDF
            $sucesso = PdfHelper::saveToFile($html, $caminhoCompleto);

            if (!$sucesso) {
                return ['sucesso' => false, 'caminho' => '', 'mensagem' => 'Erro ao gerar PDF.'];
            }

            // Caminho relativo para salvar no BD
            $caminhoRelativo = $chave . '/nfse/' . $nomeArquivo;

            return [
                'sucesso' => true,
                'caminho' => $caminhoRelativo,
                'caminho_completo' => $caminhoCompleto,
                'mensagem' => 'PDF gerado com sucesso.',
            ];
        } catch (\Throwable $e) {
            return ['sucesso' => false, 'caminho' => '', 'mensagem' => 'Erro ao gerar PDF: ' . $e->getMessage()];
        }
    }

    /**
     * Retorna caminho completo de um PDF existente
     */
    public function getCaminhoCompleto(string $caminhoRelativo): string
    {
        return $this->basePath . '/' . $caminhoRelativo;
    }

    /**
     * Gera HTML da DANFSE
     */
    private function gerarHTML(array $d): string
    {
        $chave = (string) ($d['chave'] ?? '');
        $logoPath = $this->resolverLogoPath($d, $chave);
        $urlConsultaPublica = $this->gerarUrlConsultaPublica($d);
        $qrCodeDataUri = $this->gerarQRCodeDataUri($urlConsultaPublica);

        $statusLabel = match ($d['status'] ?? 'pendente') {
            'autorizada' => 'AUTORIZADA',
            'cancelada' => 'CANCELADA',
            'rejeitada' => 'REJEITADA',
            'processando' => 'PROCESSANDO',
            default => 'PENDENTE',
        };

        $statusColor = match ($d['status'] ?? 'pendente') {
            'autorizada' => '#28a745',
            'cancelada' => '#dc3545',
            'rejeitada' => '#ffc107',
            default => '#6c757d',
        };

        $ambienteLabel = ($d['ambiente'] ?? 2) == 1 ? '' : ' - HOMOLOGAÇÃO (SEM VALOR FISCAL)';

        $valorServicos = currency_format((float) ($d['valor_servicos'] ?? 0));
        $valorDeducoes = currency_format((float) ($d['valor_deducoes'] ?? 0));
        $baseCalculo = currency_format((float) ($d['base_calculo'] ?? 0));
        $valorISS = currency_format((float) ($d['valor_iss'] ?? 0));
        $issRetido = strtoupper((string) ($d['iss_retido'] ?? 'N')) === 'S';
        $valorISSRetidoRaw = (float) ($d['valor_iss_retido'] ?? 0);
        $valorISSRetido = currency_format($valorISSRetidoRaw);
        $valorLiquido = currency_format(max(0, (float) ($d['valor_servicos'] ?? 0) - (float) ($d['valor_deducoes'] ?? 0) - $valorISSRetidoRaw));
        $valorIBS = currency_format((float) ($d['valor_ibs'] ?? 0));
        $valorCBS = currency_format((float) ($d['valor_cbs'] ?? 0));
        $dataEmissao = $this->formatarDataHora($d['data_emissao'] ?? null);
        $dataCompetencia = $this->formatarData($d['data_competencia'] ?? null);
        $itensNaoTributaveis = [];
        if (!empty($d['itens_nao_tributaveis'])) {
            $decoded = is_string($d['itens_nao_tributaveis'])
                ? json_decode($d['itens_nao_tributaveis'], true)
                : $d['itens_nao_tributaveis'];
            $itensNaoTributaveis = is_array($decoded) ? $decoded : [];
        }

        $html = '
        <style>
            body { font-family: Arial, sans-serif; font-size: 9.5pt; color: #333; }
            .topbar { width: 100%; border: 1px solid #333; border-collapse: collapse; margin-bottom: 8px; }
            .topbar td { padding: 7px; vertical-align: middle; }
            .logo-cell { width: 22%; text-align: left; }
            .logo-img { max-width: 95px; max-height: 58px; }
            .logo-placeholder { width: 95px; height: 42px; border: 1px solid #ddd; color: #999; font-size: 8pt;
                                text-align: center; padding-top: 16px; }
            .title-cell { width: 53%; text-align: center; }
            .title-cell h1 { font-size: 15pt; margin: 0; letter-spacing: 0; }
            .title-cell h2 { font-size: 10.5pt; margin: 2px 0; color: #666; font-weight: normal; }
            .qr-cell { width: 25%; text-align: center; border-left: 1px solid #333; }
            .qr-img { width: 72px; height: 72px; }
            .qr-label { font-size: 7.5pt; color: #555; margin-top: 2px; }
            .status { display: inline-block; padding: 3px 10px; color: #fff; font-weight: bold; font-size: 10pt;
                      background: ' . $statusColor . '; border-radius: 2px; }
            .ambiente { color: #dc3545; font-size: 9pt; font-weight: bold; }
            .section { margin: 8px 0; }
            .section-title { background: #f0f0f0; padding: 4px 8px; font-weight: bold; font-size: 9.5pt;
                            border-left: 3px solid #333; margin-bottom: 4px; }
            table { width: 100%; border-collapse: collapse; }
            table td { padding: 3px 5px; vertical-align: top; }
            .label { font-weight: bold; color: #555; font-size: 8pt; }
            .value { font-size: 9.5pt; }
            .valores-table td { border: 1px solid #ddd; padding: 5px; }
            .valores-table .total { font-weight: bold; font-size: 11pt; background: #f8f8f8; }
            .footer { margin-top: 15px; text-align: center; font-size: 8pt; color: #000;
                      border-top: 1px solid #ddd; padding-top: 5px; }
        </style>';

        // Cabecalho
        $html .= '<table class="topbar"><tr>';
        $html .= '<td class="logo-cell">';
        if ($logoPath !== '') {
            $html .= '<img src="' . htmlspecialchars($logoPath) . '" class="logo-img" alt="Logo">';
        } else {
            $html .= '<div class="logo-placeholder">LOGO</div>';
        }
        $html .= '</td>';
        $html .= '<td class="title-cell">';
        $html .= '<h1>DANFSE</h1>';
        $html .= '<h2>Documento Auxiliar da NFS-e</h2>';
        $html .= '<h2>Nota Fiscal de Serviço Eletrônica</h2>';
        if (!empty($ambienteLabel)) {
            $html .= '<div class="ambiente">' . $ambienteLabel . '</div>';
        }
        $html .= '<div style="margin-top:5px"><span class="status">' . $statusLabel . '</span></div>';
        $html .= '</td>';
        $html .= '<td class="qr-cell">';
        if ($qrCodeDataUri !== '') {
            $html .= '<img src="' . $qrCodeDataUri . '" class="qr-img" alt="QR Code">';
            $html .= '<div class="qr-label">Consulte pela chave de acesso</div>';
        } else {
            $html .= '<div class="qr-label">QR Code indisponível</div>';
        }
        $html .= '</td>';
        $html .= '</tr></table>';

        // Identificacao
        $html .= '<div class="section">';
        $html .= '<div class="section-title">Identificação da NFS-e</div>';
        $html .= '<table><tr>';
        $html .= '<td width="25%"><span class="label">Número</span><br><span class="value">' . ($d['numero'] ?? '-') . '</span></td>';
        $html .= '<td width="15%"><span class="label">Série</span><br><span class="value">' . ($d['serie'] ?? '-') . '</span></td>';
        $html .= '<td width="30%"><span class="label">Data Emissão</span><br><span class="value">' . $dataEmissao . '</span></td>';
        $html .= '<td width="30%"><span class="label">Competência</span><br><span class="value">' . $dataCompetencia . '</span></td>';
        $html .= '</tr></table>';
        if (!empty($d['codigo_verificacao'])) {
            $html .= '<table><tr><td><span class="label">Código de Verificação</span><br><span class="value">' . $d['codigo_verificacao'] . '</span></td></tr></table>';
        }
        if (!empty($d['chave_acesso'])) {
            $html .= '<table><tr><td><span class="label">Chave de Acesso</span><br><span class="value" style="font-size:8pt">' . $d['chave_acesso'] . '</span></td></tr></table>';
        }
        $html .= '</div>';

        // Prestador
        $html .= '<div class="section">';
        $html .= '<div class="section-title">Prestador de Serviços</div>';
        $html .= '<table><tr>';
        $html .= '<td width="60%"><span class="label">Razão Social</span><br><span class="value">' . htmlspecialchars($d['prestador_razao_social'] ?? '') . '</span></td>';
        $html .= '<td width="40%"><span class="label">CNPJ</span><br><span class="value">' . ($d['prestador_cnpj'] ?? '') . '</span></td>';
        $html .= '</tr></table>';
        $html .= '</div>';

        // Tomador
        $html .= '<div class="section">';
        $html .= '<div class="section-title">Tomador de Serviços</div>';
        $html .= '<table><tr>';
        $html .= '<td width="60%"><span class="label">Nome / Razão Social</span><br><span class="value">' . htmlspecialchars($d['tomador_nome'] ?? '') . '</span></td>';
        $html .= '<td width="40%"><span class="label">CPF/CNPJ</span><br><span class="value">' . ($d['tomador_cpf_cnpj'] ?? '') . '</span></td>';
        $html .= '</tr></table>';
        if (!empty($d['tomador_email'])) {
            $html .= '<table><tr><td><span class="label">Email</span><br><span class="value">' . htmlspecialchars($d['tomador_email']) . '</span></td></tr></table>';
        }
        $html .= '</div>';

        // Servico
        $html .= '<div class="section">';
        $html .= '<div class="section-title">Serviço</div>';
        $html .= '<table><tr>';
        $html .= '<td width="30%"><span class="label">Código NBS</span><br><span class="value">' . ($d['codigo_servico'] ?? '') . '</span></td>';
        $html .= '<td width="70%"><span class="label">Descrição</span><br><span class="value">' . htmlspecialchars($d['descricao_servico'] ?? '') . '</span></td>';
        $html .= '</tr></table>';
        $html .= '</div>';

        // Valores
        $html .= '<div class="section">';
        $html .= '<div class="section-title">Valores</div>';
        $html .= '<table class="valores-table">';
        $html .= '<tr><td width="50%"><span class="label">Valor dos Serviços</span></td><td>' . $valorServicos . '</td></tr>';
        if ((float) ($d['valor_deducoes'] ?? 0) > 0) {
            $html .= '<tr><td><span class="label">(-) Deduções</span></td><td>' . $valorDeducoes . '</td></tr>';
            foreach ($itensNaoTributaveis as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $descricaoItem = htmlspecialchars((string) ($item['descricao'] ?? 'Item não tributável'));
                $valorItem = currency_format((float) ($item['valor'] ?? 0));
                $html .= '<tr><td style="padding-left:18px"><span class="label">Item não tributável</span><br>' . $descricaoItem . '</td><td>' . $valorItem . '</td></tr>';
            }
        }
        $html .= '<tr class="total"><td><span class="label">Base de Cálculo</span></td><td>' . $baseCalculo . '</td></tr>';
        $html .= '<tr><td><span class="label">Alíquota ISS</span></td><td>' . number_format((float) ($d['aliquota_iss'] ?? 0), 2, ',', '.') . '%</td></tr>';
        $html .= '<tr><td><span class="label">Valor ISS</span></td><td>' . $valorISS . '</td></tr>';
        $html .= '<tr><td><span class="label">ISS Retido</span></td><td>' . ($issRetido ? 'Sim' : 'Não') . '</td></tr>';
        $html .= '<tr><td><span class="label">Valor ISS Retido</span></td><td>' . $valorISSRetido . '</td></tr>';
        $html .= '<tr><td><span class="label">IBS (' . number_format((float) ($d['aliquota_ibs'] ?? 0.10), 2, ',', '.') . '%)</span></td><td>' . $valorIBS . '</td></tr>';
        $html .= '<tr><td><span class="label">CBS (' . number_format((float) ($d['aliquota_cbs'] ?? 0.90), 2, ',', '.') . '%)</span></td><td>' . $valorCBS . '</td></tr>';
        $html .= '<tr class="total"><td><span class="label">Valor Líquido</span></td><td>' . $valorLiquido . '</td></tr>';
        $html .= '</table>';
        $html .= '</div>';

        // Cancelamento
        if (($d['status'] ?? '') === 'cancelada') {
            $html .= '<div class="section">';
            $html .= '<div class="section-title" style="border-left-color:#dc3545">Cancelamento</div>';
            $html .= '<table><tr>';
            $html .= '<td width="30%"><span class="label">Data</span><br><span class="value">' . ($d['data_cancelamento'] ?? '-') . '</span></td>';
            $html .= '<td width="70%"><span class="label">Motivo</span><br><span class="value">' . htmlspecialchars($d['motivo_cancelamento'] ?? '') . '</span></td>';
            $html .= '</tr></table>';
            $html .= '</div>';
        }

        // Rodape
        $html .= '<div class="footer">';
        if ($urlConsultaPublica !== '') {
            $html .= '<div style="margin-bottom:4px">Consulta pública: ' . htmlspecialchars($urlConsultaPublica) . '</div>';
        }
        $html .= 'Documento gerado pelo Sistema 7Carros.com.br | ';
        $html .= 'Tipo Emissão: ' . strtoupper($d['tipo_emissao'] ?? 'nacional');
        $html .= '</div>';

        return $html;
    }

    private function resolverLogoPath(array $dadosNFSe, string $chave): string
    {
        $logo = (string) ($dadosNFSe['filial_logo'] ?? '');

        if ($logo === '' && !empty($dadosNFSe['id_matriz_filial'])) {
            try {
                $filial = (new MatrizFilial())->buscarPorId((int) $dadosNFSe['id_matriz_filial']);
                $logo = (string) ($filial['logo'] ?? '');
            } catch (\Throwable) {
                $logo = '';
            }
        }

        return PdfHelper::resolveImagePath($logo, $chave);
    }

    private function gerarUrlConsultaPublica(array $dadosNFSe): string
    {
        $chaveAcesso = preg_replace('/\D+/', '', (string) ($dadosNFSe['chave_acesso'] ?? '')) ?? '';
        if ($chaveAcesso === '') {
            return '';
        }

        return 'https://www.nfse.gov.br/ConsultaPublica/?tpc=1&chave=' . rawurlencode($chaveAcesso);
    }

    private function gerarQRCodeDataUri(string $conteudo): string
    {
        if ($conteudo === '') {
            return '';
        }

        try {
            $svg = (string) (new QrCodeGenerator())
                ->format('svg')
                ->size(130)
                ->margin(1)
                ->generate($conteudo);

            return 'data:image/svg+xml;base64,' . base64_encode($svg);
        } catch (\Throwable) {
            return '';
        }
    }

    private function formatarData(?string $data): string
    {
        if (empty($data)) {
            return '-';
        }

        return format_date(substr($data, 0, 10));
    }

    private function formatarDataHora(?string $dataHora): string
    {
        if (empty($dataHora)) {
            return '-';
        }

        return format_datetime($dataHora);
    }
}
