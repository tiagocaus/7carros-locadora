<?php

namespace App\Services;

use App\Helpers\CurrencyHelper;
use App\Helpers\DateHelper;
use App\Models\Cliente;
use App\Models\Financeiro;
use App\Services\PagamentoLinkSyncService;

class InvoiceBatchNotificationService
{
    /**
     * Envia cobrancas de parcelas/faturas, agrupando em uma mensagem por canal
     * quando houver mais de uma fatura no lote.
     */
    public function sendInstallmentBatch(array $idsParcelas, array $options): array
    {
        $idsParcelas = array_values(array_unique(array_map('intval', $idsParcelas)));
        $canais = $options['canais'] ?? ['email' => true, 'whatsapp' => true, 'sms' => true];
        $chave = (string) ($options['chave'] ?? ($_SESSION['chave'] ?? ''));
        $filialId = (int) ($options['id_matriz_filial'] ?? 0);
        $clienteId = (int) ($options['id_cliente'] ?? 0);

        if ($idsParcelas === [] || !array_filter($canais)) {
            return [];
        }

        if ($chave === '') {
            throw new \RuntimeException('Chave do tenant nao definida para envio de cobranca');
        }

        $cliente = $clienteId > 0 ? (new Cliente())->buscarPorIdComContatos($clienteId) : null;
        if (!$cliente) {
            return [[
                'parcela_id' => null,
                'canal' => 'all',
                'success' => false,
                'message' => 'Cliente do contrato nao encontrado para envio de cobranca',
            ]];
        }

        $faturas = $this->carregarFaturas($idsParcelas, $chave);
        if ($faturas === []) {
            return [[
                'parcela_id' => null,
                'canal' => 'all',
                'success' => false,
                'message' => 'Nenhuma fatura encontrada para envio de cobranca',
            ]];
        }

        if (count($faturas) === 1) {
            return $this->sendSingleInvoice($faturas[0], $cliente, $canais, $chave, $filialId);
        }

        return $this->sendGroupedInvoices($faturas, $cliente, $canais, $chave, $filialId, $options);
    }

    private function carregarFaturas(array $idsParcelas, string $chave): array
    {
        $financeiroModel = new Financeiro();
        $faturas = [];

        foreach ($idsParcelas as $idParcela) {
            $financeiro = $financeiroModel->buscarPorId($idParcela);
            if (!$financeiro || ($financeiro['chave'] ?? '') !== $chave) {
                continue;
            }

            $link = (new PagamentoLinkSyncService())->obterOuCriarLinkAtualizado($idParcela, $chave);
            $financeiro['link_pagamento'] = $link['url'] ?? '';
            $faturas[] = $financeiro;
        }

        usort($faturas, static function (array $a, array $b): int {
            return [(int) ($a['parcela'] ?? 0), (int) ($a['id'] ?? 0)]
                <=> [(int) ($b['parcela'] ?? 0), (int) ($b['id'] ?? 0)];
        });

        return $faturas;
    }

    private function sendSingleInvoice(array $financeiro, array $cliente, array $canais, string $chave, int $filialId): array
    {
        $resultado = [];
        $email = trim((string) ($cliente['email'] ?? ''));
        $telefone = trim((string) ($cliente['telefone'] ?? $cliente['celular'] ?? ''));
        $context = $this->buildSingleContext($financeiro, $cliente, $filialId);

        foreach ($canais as $canal => $ativo) {
            if (!$ativo) {
                continue;
            }

            $erroDestino = $this->validarDestino($canal, $email, $telefone);
            if ($erroDestino !== null) {
                $resultado[] = ['parcela_id' => (int) $financeiro['id'], 'canal' => $canal, 'success' => false, 'message' => $erroDestino];
                continue;
            }

            try {
                $messageId = queue_template_message('payment_reminder', $canal, $context, $chave);
                $resultado[] = ['parcela_id' => (int) $financeiro['id'], 'canal' => $canal, 'success' => true, 'message_id' => $messageId, 'message' => "message_id={$messageId}"];
            } catch (\Throwable $e) {
                $resultado[] = ['parcela_id' => (int) $financeiro['id'], 'canal' => $canal, 'success' => false, 'message' => $e->getMessage()];
            }
        }

        return $resultado;
    }

    private function sendGroupedInvoices(array $faturas, array $cliente, array $canais, string $chave, int $filialId, array $options): array
    {
        $resultado = [];
        $email = trim((string) ($cliente['email'] ?? ''));
        $telefone = trim((string) ($cliente['telefone'] ?? $cliente['celular'] ?? ''));
        $batchId = 'invoice_batch_' . \App\Helpers\DateHelper::systemNow('YmdHis') . '_' . bin2hex(random_bytes(3));
        $origemLabel = trim((string) ($options['origem_label'] ?? ''));

        foreach ($canais as $canal => $ativo) {
            if (!$ativo) {
                continue;
            }

            $erroDestino = $this->validarDestino($canal, $email, $telefone);
            if ($erroDestino !== null) {
                $resultado[] = ['parcela_id' => null, 'canal' => $canal, 'success' => false, 'message' => $erroDestino];
                continue;
            }

            try {
                $payload = match ($canal) {
                    'email' => [
                        'to' => $email,
                        'to_name' => $cliente['nome_rsocial'] ?? '',
                        'subject' => $this->buildGroupedSubject($faturas, $origemLabel),
                        'body' => $this->buildEmailBody($faturas, $cliente, $origemLabel),
                        'body_text' => $this->buildTextMessage($faturas, $origemLabel, false),
                        'id_matriz_filial' => $filialId ?: null,
                    ],
                    'whatsapp' => [
                        'to' => $telefone,
                        'message' => $this->buildTextMessage($faturas, $origemLabel, false),
                        'id_matriz_filial' => $filialId ?: null,
                    ],
                    'sms' => [
                        'to' => $telefone,
                        'message' => $this->buildTextMessage($faturas, $origemLabel, true),
                        'id_matriz_filial' => $filialId ?: null,
                    ],
                };

                $messageId = queue_message($canal, $payload, $chave, $batchId);
                $resultado[] = ['parcela_id' => null, 'canal' => $canal, 'success' => true, 'message_id' => $messageId, 'message' => "message_id={$messageId}", 'total_faturas' => count($faturas)];
            } catch (\Throwable $e) {
                $resultado[] = ['parcela_id' => null, 'canal' => $canal, 'success' => false, 'message' => $e->getMessage()];
            }
        }

        return $resultado;
    }

    private function buildSingleContext(array $financeiro, array $cliente, int $filialId): array
    {
        $nome = (string) ($cliente['nome_rsocial'] ?? '');
        $telefone = trim((string) ($cliente['telefone'] ?? $cliente['celular'] ?? ''));

        return [
            'cliente' => [
                'nome' => $nome,
                'primeiro_nome' => explode(' ', trim($nome))[0] ?? '',
                'email' => $cliente['email'] ?? '',
                'cpf_cnpj' => $cliente['cpf_cnpj'] ?? '',
                'telefone' => $telefone,
                'celular' => $telefone,
                'preferred_locale' => $cliente['preferred_locale'] ?? null,
            ],
            'empresa' => [
                'id' => $filialId ?: null,
            ],
            'id_matriz_filial' => $filialId ?: null,
            'fatura' => [
                'numero' => $this->numeroFatura($financeiro),
                'valor' => $financeiro['valor_total'],
                'data_vencimento' => $financeiro['data_venci'],
                'descricao' => $financeiro['descricao'] ?? '',
                'status' => 'Pendente',
                'link_boleto' => $financeiro['link_pagamento'] ?? '',
            ],
        ];
    }

    private function buildGroupedSubject(array $faturas, string $origemLabel): string
    {
        $prefix = count($faturas) . ' faturas para pagamento';
        return $origemLabel !== '' ? "{$prefix} - {$origemLabel}" : $prefix;
    }

    private function buildEmailBody(array $faturas, array $cliente, string $origemLabel): string
    {
        $nome = htmlspecialchars((string) ($cliente['nome_rsocial'] ?? ''), ENT_QUOTES, 'UTF-8');
        $total = $this->formatValor($this->totalFaturas($faturas));
        $origem = $origemLabel !== '' ? '<p>Referente a <strong>' . htmlspecialchars($origemLabel, ENT_QUOTES, 'UTF-8') . '</strong>.</p>' : '';
        $rows = '';

        foreach ($faturas as $fatura) {
            $link = (string) ($fatura['link_pagamento'] ?? '');
            $linkHtml = $link !== ''
                ? '<a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">Pagar</a>'
                : '-';
            $rows .= '<tr>'
                . '<td style="padding:8px;border:1px solid #e5e7eb;">' . htmlspecialchars($this->numeroFatura($fatura), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td style="padding:8px;border:1px solid #e5e7eb;">' . htmlspecialchars((string) ($fatura['descricao'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td style="padding:8px;border:1px solid #e5e7eb;text-align:center;">' . htmlspecialchars($this->formatData($fatura['data_venci'] ?? null), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td style="padding:8px;border:1px solid #e5e7eb;text-align:right;">' . htmlspecialchars($this->formatValor((float) ($fatura['valor_total'] ?? 0)), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td style="padding:8px;border:1px solid #e5e7eb;text-align:center;">' . $linkHtml . '</td>'
                . '</tr>';
        }

        return '<p>Ola, ' . $nome . '!</p>'
            . $origem
            . '<p>Segue abaixo o resumo das faturas geradas para pagamento.</p>'
            . '<table style="width:100%;border-collapse:collapse;border:1px solid #e5e7eb;">'
            . '<thead><tr style="background:#f8fafc;">'
            . '<th style="padding:8px;border:1px solid #e5e7eb;text-align:left;">Fatura</th>'
            . '<th style="padding:8px;border:1px solid #e5e7eb;text-align:left;">Descricao</th>'
            . '<th style="padding:8px;border:1px solid #e5e7eb;text-align:center;">Vencimento</th>'
            . '<th style="padding:8px;border:1px solid #e5e7eb;text-align:right;">Valor</th>'
            . '<th style="padding:8px;border:1px solid #e5e7eb;text-align:center;">Pagamento</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table>'
            . '<p><strong>Total: ' . htmlspecialchars($total, ENT_QUOTES, 'UTF-8') . '</strong></p>';
    }

    private function buildTextMessage(array $faturas, string $origemLabel, bool $sms): string
    {
        $total = $this->formatValor($this->totalFaturas($faturas));
        $prefix = $origemLabel !== '' ? "{$origemLabel}: " : '';

        if ($sms) {
            $primeiroLink = (string) ($faturas[0]['link_pagamento'] ?? '');
            if ($primeiroLink !== '') {
                return $prefix . count($faturas) . " faturas geradas. Total {$total}. Primeiro link: {$primeiroLink}";
            }

            return $prefix . count($faturas) . " faturas geradas. Total {$total}. Entre em contato para os links de pagamento.";
        }

        $linhas = [
            $prefix . count($faturas) . ' faturas geradas para pagamento.',
            'Total: ' . $total,
            '',
        ];

        foreach ($faturas as $fatura) {
            $linhas[] = '- ' . $this->numeroFatura($fatura)
                . ' | ' . $this->formatData($fatura['data_venci'] ?? null)
                . ' | ' . $this->formatValor((float) ($fatura['valor_total'] ?? 0));
            if (!empty($fatura['link_pagamento'])) {
                $linhas[] = '  ' . $fatura['link_pagamento'];
            }
        }

        return implode("\n", $linhas);
    }

    private function validarDestino(string $canal, string $email, string $telefone): ?string
    {
        if ($canal === 'email' && $email === '') {
            return 'Cliente sem email';
        }

        if (in_array($canal, ['whatsapp', 'sms'], true) && $telefone === '') {
            return 'Cliente sem telefone';
        }

        return null;
    }

    private function numeroFatura(array $fatura): string
    {
        return (string) ($fatura['codigo'] ?? $fatura['sequencia'] ?? $fatura['id'] ?? '');
    }

    private function totalFaturas(array $faturas): float
    {
        return array_reduce($faturas, static fn(float $total, array $fatura): float => $total + (float) ($fatura['valor_total'] ?? 0), 0.0);
    }

    private function formatValor(float $valor): string
    {
        return CurrencyHelper::format($valor, true);
    }

    private function formatData(?string $data): string
    {
        return DateHelper::format($data);
    }

}
