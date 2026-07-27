<?php

namespace App\Services;

use App\Classes\QueryBuilder;
use App\Models\Model;
use App\Models\MatrizFilial;
use App\Models\Sms;
use App\Models\Whatsapp;

class FinanceiroCobrancaAutomaticaService
{
    private const OVERDUE_INTERVAL_DAYS = 7;

    private QueryBuilder $qb;
    private \mysqli $mysqli;
    private PagamentoLinkSyncService $linkSyncService;
    private InvoiceBatchNotificationService $batchNotificationService;
    private MatrizFilial $matrizFilialModel;

    public function __construct(
        ?QueryBuilder $qb = null,
        ?PagamentoLinkSyncService $linkSyncService = null,
        ?MatrizFilial $matrizFilialModel = null
    ) {
        $this->mysqli = Model::sharedMysqli();
        $this->qb = $qb ?? new QueryBuilder($this->mysqli);
        $this->linkSyncService = $linkSyncService ?? new PagamentoLinkSyncService();
        $this->batchNotificationService = new InvoiceBatchNotificationService();
        $this->matrizFilialModel = $matrizFilialModel ?? new MatrizFilial();
    }

    public function processar(): array
    {
        $stats = [
            'pre_due_candidates' => 0,
            'overdue_candidates' => 0,
            'queued' => 0,
            'messages_queued' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        $chaves = $this->carregarChavesComFaturasPendentes();

        foreach ($chaves as $chave) {
            $this->setContextoTenant($chave);

            $hoje = today();
            $amanha = \App\Helpers\DateHelper::addDaysForDatabase(1);
            $limiteReenvioVencidas = $this->technicalDaysAgo(self::OVERDUE_INTERVAL_DAYS);

            $preDue = $this->buscarFaturasPreVencimento($chave, $amanha);
            $overdue = $this->buscarFaturasVencidas($chave, $hoje, $limiteReenvioVencidas);

            $stats['pre_due_candidates'] += count($preDue);
            $stats['overdue_candidates'] += count($overdue);

            $grupos = $this->agruparPorCliente($preDue, $overdue);
            foreach ($grupos as $faturasCliente) {
                $this->processarGrupoCliente($faturasCliente, $stats);
            }
        }

        $this->limparContextoTenant();

        return $stats;
    }

    private function carregarChavesComFaturasPendentes(): array
    {
        $rows = $this->qb
            ->table('financeiro', 'f')
            ->withoutChave()
            ->select(['f.chave'])
            ->distinct()
            ->innerJoin('clientes', 'c', 'f.id_cliente', '=', 'c.id')
            ->whereRaw('c.chave = f.chave')
            ->where('f.tipo', '=', 'R')
            ->where('f.pago', '=', 'N')
            ->whereRaw('f.data_venci IS NOT NULL')
            ->whereRaw("f.data_venci <> '0000-00-00'")
            ->whereRaw('f.id_cliente IS NOT NULL')
            ->whereRaw('f.id_cliente > 0')
            ->orderBy('f.chave', 'ASC')
            ->get();

        return array_values(array_filter(array_unique(array_map(static fn($row) => (string) ($row['chave'] ?? ''), $rows))));
    }

    private function buscarFaturasPreVencimento(string $chave, string $amanha): array
    {
        return $this->baseQueryFaturas($chave)
            ->where('f.data_venci', '=', $amanha)
            ->orderBy('f.data_venci', 'ASC')
            ->orderBy('f.id', 'ASC')
            ->get();
    }

    private function buscarFaturasVencidas(string $chave, string $hoje, string $limiteReenvio): array
    {
        $query = $this->baseQueryFaturas($chave);

        if ($this->matrizFilialModel->possuiConfiguracaoCobrancaVencida()) {
            $query
                ->innerJoin('matrizes_filiais', 'mf', 'f.id_matriz_filial', '=', 'mf.id')
                ->whereRaw('mf.chave = f.chave')
                ->where('mf.notificacao_cobranca_vencida', '=', 'S');
        }

        return $query
            ->where('f.data_venci', '<', $hoje)
            ->whereRaw(
                "NOT EXISTS (
                    SELECT 1
                    FROM financeiro_cobrancas_notificacoes n
                    WHERE n.chave = f.chave
                        AND n.id_financeiro = f.id
                        AND n.tipo = 'overdue'
                        AND n.status <> 'failed'
                        AND n.last_sent_at >= ?
                )",
                [$limiteReenvio]
            )
            ->orderBy('f.data_venci', 'ASC')
            ->orderBy('f.id', 'ASC')
            ->get();
    }

    private function baseQueryFaturas(string $chave): QueryBuilder
    {
        return $this->qb
            ->table('financeiro', 'f')
            ->withChave($chave)
            ->select([
                'f.id',
                'f.chave',
                'f.codigo',
                'f.sequencia',
                'f.descricao',
                'f.data_venci',
                'f.valor_total',
                'f.parcela',
                'f.total_parcelas',
                'f.id_cliente',
                'f.id_matriz_filial',
                'c.nome_rsocial AS cliente_nome',
                "(SELECT ce.email
                    FROM contatos_emails ce
                    WHERE ce.chave = f.chave
                        AND ce.entidade_tipo = 'cliente'
                        AND ce.entidade_id = c.id
                    ORDER BY ce.principal = 'S' DESC, ce.id ASC
                    LIMIT 1
                ) AS cliente_email",
                "(SELECT ct.telefone
                    FROM contatos_telefones ct
                    WHERE ct.chave = f.chave
                        AND ct.entidade_tipo = 'cliente'
                        AND ct.entidade_id = c.id
                        AND ct.whatsapp = 'S'
                    ORDER BY ct.principal = 'S' DESC, ct.id ASC
                    LIMIT 1
                ) AS cliente_whatsapp",
                "(SELECT ct.telefone
                    FROM contatos_telefones ct
                    WHERE ct.chave = f.chave
                        AND ct.entidade_tipo = 'cliente'
                        AND ct.entidade_id = c.id
                        AND ct.sms = 'S'
                    ORDER BY ct.principal = 'S' DESC, ct.id ASC
                    LIMIT 1
                ) AS cliente_sms",
                "(SELECT ct.telefone
                    FROM contatos_telefones ct
                    WHERE ct.chave = f.chave
                        AND ct.entidade_tipo = 'cliente'
                        AND ct.entidade_id = c.id
                    ORDER BY ct.principal = 'S' DESC, ct.id ASC
                    LIMIT 1
                ) AS cliente_telefone",
                'c.cpf_cnpj AS cliente_cpf_cnpj',
                'c.preferred_locale AS cliente_preferred_locale',
            ])
            ->innerJoin('clientes', 'c', 'f.id_cliente', '=', 'c.id')
            ->whereRaw('c.chave = f.chave')
            ->where('f.tipo', '=', 'R')
            ->where('f.pago', '=', 'N')
            ->whereRaw('f.data_venci IS NOT NULL')
            ->whereRaw("f.data_venci <> '0000-00-00'")
            ->whereRaw('f.id_cliente IS NOT NULL')
            ->whereRaw('f.id_cliente > 0');
    }

    private function agruparPorCliente(array $preDue, array $overdue): array
    {
        $grupos = [];
        foreach ([['tipo' => 'pre_due', 'faturas' => $preDue], ['tipo' => 'overdue', 'faturas' => $overdue]] as $grupoTipo) {
            foreach ($grupoTipo['faturas'] as $fatura) {
                $fatura['notification_type'] = $grupoTipo['tipo'];
                $clienteId = (int) ($fatura['id_cliente'] ?? 0);
                if ($clienteId > 0) {
                    $grupos[$clienteId][] = $fatura;
                }
            }
        }

        return $grupos;
    }

    private function processarGrupoCliente(array $faturas, array &$stats): void
    {
        $primeira = $faturas[0] ?? [];
        $chave = (string) ($primeira['chave'] ?? '');
        if ($chave === '') {
            $stats['skipped'] += count($faturas);
            return;
        }

        $remetentes = $this->resolverRemetentesPorCanal($faturas);
        if ($remetentes === []) {
            $stats['skipped'] += count($faturas);
            return;
        }

        $faturasComLink = [];
        foreach ($faturas as $fatura) {
            $idFinanceiro = (int) ($fatura['id'] ?? 0);
            if ($idFinanceiro <= 0) {
                $stats['skipped']++;
                continue;
            }

            try {
                $link = $this->linkSyncService->obterOuCriarLinkAtualizado($idFinanceiro, $chave);
                $fatura['link_pagamento'] = $link['url'] ?? '';
                $faturasComLink[] = $fatura;
            } catch (\Throwable $e) {
                $stats['failed']++;
                $stats['errors'][] = [
                    'id_financeiro' => $idFinanceiro,
                    'chave' => $chave,
                    'tipo' => $fatura['notification_type'] ?? '',
                    'erro' => $e->getMessage(),
                ];
            }
        }

        foreach ($remetentes as $canal => $remetente) {
            $elegiveis = array_values(array_filter($faturasComLink, function (array $fatura) use ($chave, $canal): bool {
                $tipo = (string) $fatura['notification_type'];
                $dataReferencia = (string) ($fatura['data_venci'] ?? today());
                $enviado = $this->jaEnviado($chave, (int) $fatura['id'], $tipo, $canal, $dataReferencia);
                return !$enviado;
            }));

            $stats['skipped'] += count($faturasComLink) - count($elegiveis);
            if ($elegiveis !== []) {
                $this->enfileirarGrupoCanal($elegiveis, $canal, $remetente, $stats);
            }
        }
    }

    private function enfileirarGrupoCanal(array $faturas, string $canal, array $remetente, array &$stats): void
    {
        $chave = (string) $faturas[0]['chave'];
        try {
            if (count($faturas) === 1) {
                $fatura = $faturas[0];
                $template = $fatura['notification_type'] === 'overdue' ? 'overdue_notice' : 'payment_reminder';
                $context = $this->buildContext($fatura, (string) ($fatura['link_pagamento'] ?? ''));
                $messageId = queue_template_message($template, $canal, $this->contextoParaCanal($context, $remetente, $canal), $chave);
            } else {
                $cliente = $this->clienteParaLote($remetente, $canal);
                $payload = $this->batchNotificationService->buildBatchPayload(
                    $canal,
                    $faturas,
                    $cliente,
                    (int) ($remetente['id_matriz_filial'] ?? 0)
                );
                $batchId = 'financeiro_cron_' . \App\Helpers\DateHelper::systemNow('YmdHis') . '_' . bin2hex(random_bytes(3));
                if ($canal === 'email') {
                    $messageIds = queue_client_email(
                        (int) ($faturas[0]['id_cliente'] ?? 0),
                        $payload,
                        $chave,
                        $batchId
                    );
                    $messageId = $messageIds[0] ?? 0;
                } else {
                    $messageIds = queue_client_phone(
                        $canal,
                        (int) ($faturas[0]['id_cliente'] ?? 0),
                        $payload,
                        $chave,
                        $batchId
                    );
                    $messageId = $messageIds[0] ?? 0;
                }
            }

            if ($messageId <= 0) {
                foreach ($faturas as $fatura) {
                    $this->registrarResultadoFatura($fatura, $canal, null, 'skipped', 'Destinatario ausente para o canal');
                }
                $stats['skipped'] += count($faturas);
                return;
            }

            foreach ($faturas as $fatura) {
                $this->registrarResultadoFatura($fatura, $canal, $messageId, 'queued', null);
            }
            $stats['queued'] += count($faturas);
            $stats['messages_queued']++;
        } catch (\Throwable $e) {
            foreach ($faturas as $fatura) {
                $this->registrarResultadoFatura($fatura, $canal, null, 'failed', $e->getMessage());
                $stats['errors'][] = [
                    'id_financeiro' => (int) $fatura['id'],
                    'chave' => $chave,
                    'tipo' => $fatura['notification_type'],
                    'canal' => $canal,
                    'erro' => $e->getMessage(),
                ];
            }
            $stats['failed'] += count($faturas);
        }
    }

    private function registrarResultadoFatura(array $fatura, string $canal, ?int $messageId, string $status, ?string $erro): void
    {
        $this->registrarEnvio(
            (string) $fatura['chave'],
            (int) $fatura['id'],
            (string) $fatura['notification_type'],
            $canal,
            (string) ($fatura['data_venci'] ?? today()),
            $messageId,
            $status,
            $erro
        );
    }

    private function jaEnviado(string $chave, int $idFinanceiro, string $tipo, string $canal, string $dataReferencia): bool
    {
        $query = $this->qb
            ->table('financeiro_cobrancas_notificacoes')
            ->withChave($chave)
            ->where('id_financeiro', '=', $idFinanceiro)
            ->where('tipo', '=', $tipo)
            ->where('canal', '=', $canal)
            ->where('status', '<>', 'failed');

        if ($tipo === 'overdue') {
            $query->where('last_sent_at', '>=', $this->technicalDaysAgo(self::OVERDUE_INTERVAL_DAYS));
        } else {
            $query->where('data_referencia', '=', $dataReferencia);
        }

        return $query->count() > 0;
    }

    private function registrarEnvio(
        string $chave,
        int $idFinanceiro,
        string $tipo,
        string $canal,
        string $dataReferencia,
        ?int $messageId,
        string $status,
        ?string $errorMessage
    ): void {
        $stmt = $this->mysqli->prepare("
            INSERT INTO financeiro_cobrancas_notificacoes
                (chave, id_financeiro, tipo, canal, data_referencia, last_sent_at, message_id, status, error_message, created_at, updated_at)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                last_sent_at = VALUES(last_sent_at),
                message_id = VALUES(message_id),
                status = VALUES(status),
                error_message = VALUES(error_message),
                updated_at = VALUES(updated_at)
        ");

        if (!$stmt) {
            throw new \RuntimeException('Erro ao preparar registro de notificacao de cobranca: ' . $this->mysqli->error);
        }

        $agora = \App\Helpers\DateHelper::systemNow();

        $stmt->bind_param(
            'sissssissss',
            $chave,
            $idFinanceiro,
            $tipo,
            $canal,
            $dataReferencia,
            $agora,
            $messageId,
            $status,
            $errorMessage,
            $agora,
            $agora
        );

        if (!$stmt->execute()) {
            $erro = $stmt->error;
            $stmt->close();
            throw new \RuntimeException('Erro ao registrar notificacao de cobranca: ' . $erro);
        }

        $stmt->close();
    }

    private function canaisDisponiveis(array $fatura): array
    {
        $canais = [];
        $filialId = (int) ($fatura['id_matriz_filial'] ?? 0);
        $email = trim((string) ($fatura['cliente_email'] ?? ''));
        $telefonePrincipal = trim((string) ($fatura['cliente_telefone'] ?? ''));
        $telefoneWhatsapp = trim((string) ($fatura['cliente_whatsapp'] ?? '')) ?: $telefonePrincipal;
        $telefoneSms = trim((string) ($fatura['cliente_sms'] ?? '')) ?: $telefonePrincipal;

        if ($this->isValidEmail($email)) {
            $canais[] = 'email';
        }

        $telefoneWhatsapp = $this->telefoneValido($telefoneWhatsapp) ?: $this->telefoneValido($telefonePrincipal);
        $telefoneSms = $this->telefoneValido($telefoneSms) ?: $this->telefoneValido($telefonePrincipal);

        if ($filialId > 0 && $telefoneWhatsapp !== '') {
            if ((new Whatsapp())->buscarConectadaPorFilial($filialId) !== null) {
                $canais[] = 'whatsapp';
            }
        }

        if ($filialId > 0 && $telefoneSms !== '') {
            if ((new Sms())->buscarValidadaPorFilial($filialId) !== null) {
                $canais[] = 'sms';
            }
        }

        return $canais;
    }

    /**
     * Seleciona uma filial/remetente deterministico por canal. Assim, faturas
     * do mesmo cliente em filiais diferentes continuam no mesmo lote.
     */
    private function resolverRemetentesPorCanal(array $faturas): array
    {
        $remetentes = [];
        foreach ($faturas as $fatura) {
            foreach ($this->canaisDisponiveis($fatura) as $canal) {
                if (!isset($remetentes[$canal])) {
                    $remetentes[$canal] = $fatura;
                }
            }
        }

        return $remetentes;
    }

    private function clienteParaLote(array $fatura, string $canal): array
    {
        $telefonePrincipal = trim((string) ($fatura['cliente_telefone'] ?? ''));
        $telefone = match ($canal) {
            'whatsapp' => $this->telefoneValido((string) ($fatura['cliente_whatsapp'] ?? ''))
                ?: $this->telefoneValido($telefonePrincipal),
            'sms' => $this->telefoneValido((string) ($fatura['cliente_sms'] ?? ''))
                ?: $this->telefoneValido($telefonePrincipal),
            default => $this->telefoneValido($telefonePrincipal),
        };

        return [
            'id' => (int) ($fatura['id_cliente'] ?? 0),
            'nome_rsocial' => $fatura['cliente_nome'] ?? '',
            'nome' => $fatura['cliente_nome'] ?? '',
            'email' => $fatura['cliente_email'] ?? '',
            'telefone' => $telefone,
            'celular' => $telefone,
            'cpf_cnpj' => $fatura['cliente_cpf_cnpj'] ?? '',
            'preferred_locale' => $fatura['cliente_preferred_locale'] ?? null,
        ];
    }

    private function contextoParaCanal(array $context, array $fatura, string $canal): array
    {
        $filialId = (int) ($fatura['id_matriz_filial'] ?? 0);
        $context['id_matriz_filial'] = $filialId ?: null;
        $context['empresa']['id'] = $filialId ?: null;

        if (!in_array($canal, ['whatsapp', 'sms'], true)) {
            return $context;
        }

        $telefonePrincipal = trim((string) ($fatura['cliente_telefone'] ?? ''));
        $telefone = $canal === 'whatsapp'
            ? ($this->telefoneValido((string) ($fatura['cliente_whatsapp'] ?? '')) ?: $this->telefoneValido($telefonePrincipal))
            : ($this->telefoneValido((string) ($fatura['cliente_sms'] ?? '')) ?: $this->telefoneValido($telefonePrincipal));

        $context['cliente']['telefone'] = $telefone;
        $context['cliente']['celular'] = $telefone;

        return $context;
    }

    private function buildContext(array $fatura, string $linkPagamento): array
    {
        $nome = (string) ($fatura['cliente_nome'] ?? '');
        $telefoneWhatsapp = $this->telefoneValido((string) ($fatura['cliente_whatsapp'] ?? ''));
        $telefoneSms = $this->telefoneValido((string) ($fatura['cliente_sms'] ?? ''));
        $telefone = $this->telefoneValido((string) ($fatura['cliente_telefone'] ?? ''));

        return [
            'cliente' => [
                'id' => (int) ($fatura['id_cliente'] ?? 0),
                'nome' => $nome,
                'primeiro_nome' => explode(' ', trim($nome))[0] ?? '',
                'email' => $fatura['cliente_email'] ?? '',
                'cpf_cnpj' => $fatura['cliente_cpf_cnpj'] ?? '',
                'telefone' => $telefoneWhatsapp ?: ($telefoneSms ?: $telefone),
                'celular' => $telefoneWhatsapp ?: ($telefoneSms ?: $telefone),
                'preferred_locale' => $fatura['cliente_preferred_locale'] ?? null,
            ],
            'empresa' => [
                'id' => (int) ($fatura['id_matriz_filial'] ?? 0) ?: null,
            ],
            'id_matriz_filial' => (int) ($fatura['id_matriz_filial'] ?? 0) ?: null,
            'fatura' => [
                'numero' => $this->numeroFatura($fatura),
                'valor' => (float) ($fatura['valor_total'] ?? 0),
                'data_vencimento' => $fatura['data_venci'] ?? '',
                'descricao' => $fatura['descricao'] ?? '',
                'parcela' => (int) ($fatura['parcela'] ?? 0),
                'total_parcelas' => (int) ($fatura['total_parcelas'] ?? 0),
                'status' => 'Pendente',
                'link_boleto' => $linkPagamento,
                'dias_atraso' => $this->diasAtraso($fatura['data_venci'] ?? null),
            ],
        ];
    }

    private function numeroFatura(array $fatura): string
    {
        if (!empty($fatura['codigo'])) {
            return (string) $fatura['codigo'];
        }

        if (!empty($fatura['sequencia'])) {
            return (string) $fatura['sequencia'];
        }

        return (string) ($fatura['id'] ?? '');
    }

    private function isValidEmail(string $email): bool
    {
        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function telefoneValido(string $telefone): string
    {
        $telefone = trim($telefone);
        if ($telefone === '') {
            return '';
        }

        $digits = preg_replace('/[^0-9]/', '', $telefone);
        $length = strlen($digits);

        if ($length === 0) {
            return '';
        }

        if (str_starts_with($digits, '55')) {
            return ($length === 12 || $length === 13) ? $telefone : '';
        }

        return ($length >= 8 && $length <= 15) ? $telefone : '';
    }

    private function diasAtraso(?string $dataVencimento): int
    {
        if (empty($dataVencimento) || $dataVencimento >= today()) {
            return 0;
        }

        $vencimento = new \DateTimeImmutable($dataVencimento);
        $hoje = new \DateTimeImmutable(today());

        return (int) $vencimento->diff($hoje)->days;
    }

    private function technicalDaysAgo(int $days): string
    {
        return \App\Helpers\DateHelper::formatTimestamp(
            \App\Helpers\DateHelper::timestamp() - ($days * 86400),
            'Y-m-d H:i:s',
            false
        );
    }

    private function setContextoTenant(string $chave): void
    {
        $_SESSION['chave'] = $chave;
        $_SESSION['user_id'] = 0;
        $_SESSION['user_name'] = 'Sistema';
        \App\Helpers\CurrencyHelper::clearCache();
    }

    private function limparContextoTenant(): void
    {
        unset($_SESSION['chave'], $_SESSION['user_id'], $_SESSION['user_name']);
        \App\Helpers\CurrencyHelper::clearCache();
    }
}
