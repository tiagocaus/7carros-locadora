<?php

namespace App\Services;

use App\Helpers\CurrencyHelper;
use App\Helpers\DateHelper;
use App\I18n\TemplateVariables;
use App\I18n\Translator;
use App\Models\Cliente;
use App\Models\Financeiro;
use App\Services\PagamentoLinkSyncService;

class InvoiceBatchNotificationService
{
    /**
     * Monta o payload de uma cobranca agrupada sem publica-la na fila.
     * Usado pelo CRON para manter o controle de envio por fatura.
     */
    public function buildBatchPayload(
        string $canal,
        array $faturas,
        array $cliente,
        int $filialId,
        array $options = []
    ): array {
        if (!in_array($canal, ['email', 'whatsapp', 'sms'], true)) {
            throw new \InvalidArgumentException('Canal de cobranca invalido');
        }

        if ($faturas === []) {
            throw new \InvalidArgumentException('Nenhuma fatura informada para o lote');
        }

        $email = trim((string) ($cliente['email'] ?? ''));
        $telefone = trim((string) ($cliente['telefone'] ?? $cliente['celular'] ?? ''));
        $erroDestino = $this->validarDestino($canal, $email, $telefone);
        if ($erroDestino !== null) {
            throw new \InvalidArgumentException($erroDestino);
        }

        $origemLabel = trim((string) ($options['origem_label'] ?? ''));

        return match ($canal) {
            'email' => [
                'to' => $email,
                'to_name' => $cliente['nome_rsocial'] ?? $cliente['nome'] ?? '',
                'subject' => $this->buildGroupedSubject($faturas, $origemLabel),
                'body' => $this->buildEmailBody($faturas, $cliente, $origemLabel),
                'body_text' => $this->buildTextMessage($faturas, $cliente, $origemLabel, false),
                'id_matriz_filial' => $filialId ?: null,
            ],
            'whatsapp' => [
                'to' => $telefone,
                'message' => $this->buildTextMessage($faturas, $cliente, $origemLabel, false),
                'id_matriz_filial' => $filialId ?: null,
            ],
            'sms' => [
                'to' => $telefone,
                'message' => $this->buildTextMessage($faturas, $cliente, $origemLabel, true),
                'id_matriz_filial' => $filialId ?: null,
            ],
        };
    }

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
                $enfileirado = $canal !== 'email' || $messageId > 0;
                $resultado[] = [
                    'parcela_id' => (int) $financeiro['id'],
                    'canal' => $canal,
                    'success' => $enfileirado,
                    'message_id' => $messageId,
                    'message' => $enfileirado ? "message_id={$messageId}" : 'Cliente sem email autorizado para envio',
                ];
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
                $payload = $this->buildBatchPayload($canal, $faturas, $cliente, $filialId, [
                    'origem_label' => $origemLabel,
                ]);

                if ($canal === 'email') {
                    $messageIds = queue_client_email(
                        (int) ($cliente['id'] ?? $faturas[0]['id_cliente'] ?? 0),
                        $payload,
                        $chave,
                        $batchId
                    );
                    $messageId = $messageIds[0] ?? 0;
                } else {
                    $messageId = queue_message($canal, $payload, $chave, $batchId);
                }
                $enfileirado = $canal !== 'email' || $messageId > 0;
                $resultado[] = [
                    'parcela_id' => null,
                    'canal' => $canal,
                    'success' => $enfileirado,
                    'message_id' => $messageId,
                    'message' => $enfileirado ? "message_id={$messageId}" : 'Cliente sem email autorizado para envio',
                    'total_faturas' => count($faturas),
                ];
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
                'id' => (int) ($cliente['id'] ?? $financeiro['id_cliente'] ?? 0),
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
                'parcela' => (int) ($financeiro['parcela'] ?? 0),
                'total_parcelas' => (int) ($financeiro['total_parcelas'] ?? 0),
                'status' => 'Pendente',
                'link_boleto' => $financeiro['link_pagamento'] ?? '',
            ],
        ];
    }

    private function buildGroupedSubject(array $faturas, string $origemLabel): string
    {
        $tipos = array_values(array_unique(array_filter(array_column($faturas, 'notification_type'))));
        $prefix = match ($tipos) {
            ['pre_due'] => count($faturas) . ' faturas proximas do vencimento',
            ['overdue'] => count($faturas) . ' faturas vencidas',
            default => count($faturas) . ' faturas para pagamento',
        };
        return $origemLabel !== '' ? "{$prefix} - {$origemLabel}" : $prefix;
    }

    private function buildEmailBody(array $faturas, array $cliente, string $origemLabel): string
    {
        $nome = htmlspecialchars((string) ($cliente['nome_rsocial'] ?? ''), ENT_QUOTES, 'UTF-8');
        $total = $this->formatValor($this->totalFaturas($faturas));
        $origem = $origemLabel !== '' ? '<p>Referente a <strong>' . htmlspecialchars($origemLabel, ENT_QUOTES, 'UTF-8') . '</strong>.</p>' : '';
        $sections = $this->groupByNotificationType($faturas);
        $tables = '';
        foreach ($sections as $tipo => $faturasSecao) {
            $titulo = match ($tipo) {
                'pre_due' => 'Proximas do vencimento',
                'overdue' => 'Vencidas',
                default => 'Faturas',
            };
            $tables .= '<h3>' . $titulo . '</h3>' . $this->buildInvoiceTable($faturasSecao, $cliente);
        }

        return '<p>Ola, ' . $nome . '!</p>'
            . $origem
            . '<p>Segue abaixo o resumo das faturas para pagamento.</p>'
            . $tables
            . '<p><strong>Total: ' . htmlspecialchars($total, ENT_QUOTES, 'UTF-8') . '</strong></p>';
    }

    private function buildInvoiceTable(array $faturas, array $cliente): string
    {
        $locale = (string) ($cliente['preferred_locale'] ?? 'pt_BR');
        $parcelaLabel = Translator::getInstance()->get('variables.fatura.parcela', [], $locale);
        $rows = '';
        foreach ($faturas as $fatura) {
            $link = (string) ($fatura['link_pagamento'] ?? '');
            $linkHtml = $link !== ''
                ? '<a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">Pagar</a>'
                : '-';
            $rows .= '<tr>'
                . '<td style="padding:8px;border:1px solid #e5e7eb;">' . htmlspecialchars($this->numeroFatura($fatura), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td style="padding:8px;border:1px solid #e5e7eb;text-align:center;">' . htmlspecialchars($this->descricaoParcela($fatura, $locale) ?: '-', ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td style="padding:8px;border:1px solid #e5e7eb;">' . htmlspecialchars((string) ($fatura['descricao'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td style="padding:8px;border:1px solid #e5e7eb;text-align:center;">' . htmlspecialchars($this->formatData($fatura['data_venci'] ?? null), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td style="padding:8px;border:1px solid #e5e7eb;text-align:right;">' . htmlspecialchars($this->formatValor((float) ($fatura['valor_total'] ?? 0)), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td style="padding:8px;border:1px solid #e5e7eb;text-align:center;">' . $linkHtml . '</td>'
                . '</tr>';
        }

        return '<table style="width:100%;border-collapse:collapse;border:1px solid #e5e7eb;">'
            . '<thead><tr style="background:#f8fafc;">'
            . '<th style="padding:8px;border:1px solid #e5e7eb;text-align:left;">Fatura</th>'
            . '<th style="padding:8px;border:1px solid #e5e7eb;text-align:center;">' . htmlspecialchars($parcelaLabel, ENT_QUOTES, 'UTF-8') . '</th>'
            . '<th style="padding:8px;border:1px solid #e5e7eb;text-align:left;">Descricao</th>'
            . '<th style="padding:8px;border:1px solid #e5e7eb;text-align:center;">Vencimento</th>'
            . '<th style="padding:8px;border:1px solid #e5e7eb;text-align:right;">Valor</th>'
            . '<th style="padding:8px;border:1px solid #e5e7eb;text-align:center;">Pagamento</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table>';
    }

    private function buildTextMessage(array $faturas, array $cliente, string $origemLabel, bool $sms): string
    {
        $total = $this->formatValor($this->totalFaturas($faturas));
        $prefix = $origemLabel !== '' ? "{$origemLabel}: " : '';
        $locale = (string) ($cliente['preferred_locale'] ?? 'pt_BR');
        $parcelas = array_values(array_unique(array_filter(array_map(
            fn(array $fatura): ?string => $this->descricaoParcela($fatura, $locale),
            $faturas
        ))));
        $parcelasResumo = $parcelas !== [] ? ' ' . implode(', ', $parcelas) . '.' : '';

        if ($sms) {
            $primeiroLink = (string) ($faturas[0]['link_pagamento'] ?? '');
            if ($primeiroLink !== '') {
                return $prefix . count($faturas) . " faturas geradas.{$parcelasResumo} Total {$total}. Primeiro link: {$primeiroLink}";
            }

            return $prefix . count($faturas) . " faturas geradas.{$parcelasResumo} Total {$total}. Entre em contato para os links de pagamento.";
        }

        $linhas = [
            $prefix . count($faturas) . ' faturas geradas para pagamento.',
            'Total: ' . $total,
        ];

        foreach ($this->groupByNotificationType($faturas) as $tipo => $faturasSecao) {
            $linhas[] = '';
            $linhas[] = match ($tipo) {
                'pre_due' => 'Proximas do vencimento:',
                'overdue' => 'Vencidas:',
                default => 'Faturas:',
            };
            foreach ($faturasSecao as $fatura) {
                $parcela = $this->descricaoParcela($fatura, $locale);
                $linhas[] = '- ' . $this->numeroFatura($fatura)
                    . ($parcela ? ' | ' . $parcela : '')
                    . ' | ' . $this->formatData($fatura['data_venci'] ?? null)
                    . ' | ' . $this->formatValor((float) ($fatura['valor_total'] ?? 0));
                if (!empty($fatura['link_pagamento'])) {
                    $linhas[] = '  ' . $fatura['link_pagamento'];
                }
            }
        }

        return implode("\n", $linhas);
    }

    private function descricaoParcela(array $fatura, string $locale): ?string
    {
        return TemplateVariables::formatInvoiceInstallment(
            (int) ($fatura['parcela'] ?? 0),
            (int) ($fatura['total_parcelas'] ?? 0),
            $locale
        );
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

    private function groupByNotificationType(array $faturas): array
    {
        $grupos = [];
        foreach ($faturas as $fatura) {
            $tipo = (string) ($fatura['notification_type'] ?? 'generic');
            $grupos[$tipo][] = $fatura;
        }

        $ordenados = [];
        foreach (['pre_due', 'overdue', 'generic'] as $tipo) {
            if (!empty($grupos[$tipo])) {
                $ordenados[$tipo] = $grupos[$tipo];
            }
        }

        return $ordenados + $grupos;
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
