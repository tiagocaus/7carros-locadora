<?php

namespace App\Services\NFSe;

use App\Helpers\PdfHelper;

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

        $valorServicos = number_format((float) ($d['valor_servicos'] ?? 0), 2, ',', '.');
        $valorDeducoes = number_format((float) ($d['valor_deducoes'] ?? 0), 2, ',', '.');
        $baseCalculo = number_format((float) ($d['base_calculo'] ?? 0), 2, ',', '.');
        $valorISS = number_format((float) ($d['valor_iss'] ?? 0), 2, ',', '.');
        $valorIBS = number_format((float) ($d['valor_ibs'] ?? 0), 2, ',', '.');
        $valorCBS = number_format((float) ($d['valor_cbs'] ?? 0), 2, ',', '.');
        $itensNaoTributaveis = [];
        if (!empty($d['itens_nao_tributaveis'])) {
            $decoded = is_string($d['itens_nao_tributaveis'])
                ? json_decode($d['itens_nao_tributaveis'], true)
                : $d['itens_nao_tributaveis'];
            $itensNaoTributaveis = is_array($decoded) ? $decoded : [];
        }

        $html = '
        <style>
            body { font-family: Arial, sans-serif; font-size: 10pt; color: #333; }
            .header { text-align: center; margin-bottom: 10px; border-bottom: 2px solid #333; padding-bottom: 10px; }
            .header h1 { font-size: 16pt; margin: 0; }
            .header h2 { font-size: 12pt; margin: 2px 0; color: #666; }
            .status { display: inline-block; padding: 3px 10px; color: #fff; font-weight: bold; font-size: 11pt;
                      background: ' . $statusColor . '; border-radius: 3px; }
            .ambiente { color: #dc3545; font-size: 9pt; font-weight: bold; }
            .section { margin: 8px 0; }
            .section-title { background: #f0f0f0; padding: 4px 8px; font-weight: bold; font-size: 10pt;
                            border-left: 3px solid #333; margin-bottom: 5px; }
            table { width: 100%; border-collapse: collapse; }
            table td { padding: 3px 5px; vertical-align: top; }
            .label { font-weight: bold; color: #555; font-size: 8pt; }
            .value { font-size: 10pt; }
            .valores-table td { border: 1px solid #ddd; padding: 5px; }
            .valores-table .total { font-weight: bold; font-size: 11pt; background: #f8f8f8; }
            .footer { margin-top: 15px; text-align: center; font-size: 8pt; color: #999;
                      border-top: 1px solid #ddd; padding-top: 5px; }
        </style>';

        // Cabecalho
        $html .= '<div class="header">';
        $html .= '<h1>DANFSE - Documento Auxiliar da NFS-e</h1>';
        $html .= '<h2>Nota Fiscal de Serviço Eletrônica</h2>';
        if (!empty($ambienteLabel)) {
            $html .= '<div class="ambiente">' . $ambienteLabel . '</div>';
        }
        $html .= '<div style="margin-top:5px"><span class="status">' . $statusLabel . '</span></div>';
        $html .= '</div>';

        // Identificacao
        $html .= '<div class="section">';
        $html .= '<div class="section-title">Identificação da NFS-e</div>';
        $html .= '<table><tr>';
        $html .= '<td width="25%"><span class="label">Número</span><br><span class="value">' . ($d['numero'] ?? '-') . '</span></td>';
        $html .= '<td width="15%"><span class="label">Série</span><br><span class="value">' . ($d['serie'] ?? '-') . '</span></td>';
        $html .= '<td width="30%"><span class="label">Data Emissão</span><br><span class="value">' . ($d['data_emissao'] ?? '-') . '</span></td>';
        $html .= '<td width="30%"><span class="label">Competência</span><br><span class="value">' . ($d['data_competencia'] ?? '-') . '</span></td>';
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
        $html .= '<tr><td width="50%"><span class="label">Valor dos Serviços</span></td><td>R$ ' . $valorServicos . '</td></tr>';
        if ((float) ($d['valor_deducoes'] ?? 0) > 0) {
            $html .= '<tr><td><span class="label">(-) Deduções</span></td><td>R$ ' . $valorDeducoes . '</td></tr>';
            foreach ($itensNaoTributaveis as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $descricaoItem = htmlspecialchars((string) ($item['descricao'] ?? 'Item não tributável'));
                $valorItem = number_format((float) ($item['valor'] ?? 0), 2, ',', '.');
                $html .= '<tr><td style="padding-left:18px"><span class="label">Item não tributável</span><br>' . $descricaoItem . '</td><td>R$ ' . $valorItem . '</td></tr>';
            }
        }
        $html .= '<tr class="total"><td><span class="label">Base de Cálculo</span></td><td>R$ ' . $baseCalculo . '</td></tr>';
        if ((float) ($d['valor_iss'] ?? 0) > 0) {
            $html .= '<tr><td><span class="label">ISS (' . number_format((float) ($d['aliquota_iss'] ?? 0), 2, ',', '.') . '%)</span></td><td>R$ ' . $valorISS . '</td></tr>';
        }
        $html .= '<tr><td><span class="label">IBS (' . number_format((float) ($d['aliquota_ibs'] ?? 0.10), 2, ',', '.') . '%)</span></td><td>R$ ' . $valorIBS . '</td></tr>';
        $html .= '<tr><td><span class="label">CBS (' . number_format((float) ($d['aliquota_cbs'] ?? 0.90), 2, ',', '.') . '%)</span></td><td>R$ ' . $valorCBS . '</td></tr>';
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
        $html .= 'Documento gerado pelo Sistema 7Carros.com.br | ';
        $html .= 'Tipo Emissão: ' . strtoupper($d['tipo_emissao'] ?? 'nacional');
        $html .= '</div>';

        return $html;
    }
}
