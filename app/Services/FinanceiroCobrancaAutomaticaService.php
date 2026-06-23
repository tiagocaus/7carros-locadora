<?php

namespace App\Services;

use App\Classes\QueryBuilder;
use App\Models\Model;
use App\Models\Sms;
use App\Models\Whatsapp;

class FinanceiroCobrancaAutomaticaService
{
    private const BATCH_SIZE = 500;
    private const OVERDUE_INTERVAL_DAYS = 7;

    private QueryBuilder $qb;
    private \mysqli $mysqli;
    private PagamentoLinkSyncService $linkSyncService;

    public function __construct(?QueryBuilder $qb = null, ?PagamentoLinkSyncService $linkSyncService = null)
    {
        $this->mysqli = Model::sharedMysqli();
        $this->qb = $qb ?? new QueryBuilder($this->mysqli);
        $this->linkSyncService = $linkSyncService ?? new PagamentoLinkSyncService();
    }

    public function processar(): array
    {
        $stats = [
            'pre_due_candidates' => 0,
            'overdue_candidates' => 0,
            'queued' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        $preDue = $this->buscarFaturasPreVencimento();
        $overdue = $this->buscarFaturasVencidas();

        $stats['pre_due_candidates'] = count($preDue);
        $stats['overdue_candidates'] = count($overdue);

        foreach ($preDue as $fatura) {
            $this->processarFatura($fatura, 'pre_due', 'payment_reminder', $stats);
        }

        foreach ($overdue as $fatura) {
            $this->processarFatura($fatura, 'overdue', 'overdue_notice', $stats);
        }

        $this->limparContextoTenant();

        return $stats;
    }

    private function buscarFaturasPreVencimento(): array
    {
        return $this->baseQueryFaturas()
            ->whereRaw('f.data_venci = DATE_ADD(CURDATE(), INTERVAL 1 DAY)')
            ->orderBy('f.data_venci', 'ASC')
            ->orderBy('f.id', 'ASC')
            ->limit(self::BATCH_SIZE)
            ->get();
    }

    private function buscarFaturasVencidas(): array
    {
        return $this->baseQueryFaturas()
            ->whereRaw('f.data_venci < CURDATE()')
            ->whereRaw(
                "NOT EXISTS (
                    SELECT 1
                    FROM financeiro_cobrancas_notificacoes n
                    WHERE n.chave = f.chave
                        AND n.id_financeiro = f.id
                        AND n.tipo = 'overdue'
                        AND n.last_sent_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                )",
                [self::OVERDUE_INTERVAL_DAYS]
            )
            ->orderBy('f.data_venci', 'ASC')
            ->orderBy('f.id', 'ASC')
            ->limit(self::BATCH_SIZE)
            ->get();
    }

    private function baseQueryFaturas(): QueryBuilder
    {
        return $this->qb
            ->table('financeiro', 'f')
            ->withoutChave()
            ->select([
                'f.id',
                'f.chave',
                'f.codigo',
                'f.sequencia',
                'f.descricao',
                'f.data_venci',
                'f.valor_total',
                'f.id_cliente',
                'f.id_matriz_filial',
                'c.nome_rsocial AS cliente_nome',
                'c.email AS cliente_email',
                'c.telefone AS cliente_telefone',
                'c.celular AS cliente_celular',
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

    private function processarFatura(array $fatura, string $tipo, string $template, array &$stats): void
    {
        $chave = (string) ($fatura['chave'] ?? '');
        $idFinanceiro = (int) ($fatura['id'] ?? 0);
        $dataReferencia = (string) ($fatura['data_venci'] ?? date('Y-m-d'));

        if ($chave === '' || $idFinanceiro <= 0) {
            $stats['skipped']++;
            return;
        }

        try {
            $this->setContextoTenant($chave);
            $link = $this->linkSyncService->obterOuCriarLinkAtualizado($idFinanceiro, $chave);
            $context = $this->buildContext($fatura, $link['url'] ?? '');
            $canais = $this->canaisDisponiveis($fatura);

            if ($canais === []) {
                $stats['skipped']++;
                return;
            }

            foreach ($canais as $canal) {
                $this->enfileirarCanal($fatura, $tipo, $template, $canal, $context, $dataReferencia, $stats);
            }
        } catch (\Throwable $e) {
            $stats['failed']++;
            $stats['errors'][] = [
                'id_financeiro' => $idFinanceiro,
                'chave' => $chave,
                'tipo' => $tipo,
                'erro' => $e->getMessage(),
            ];
        }
    }

    private function enfileirarCanal(
        array $fatura,
        string $tipo,
        string $template,
        string $canal,
        array $context,
        string $dataReferencia,
        array &$stats
    ): void {
        $chave = (string) $fatura['chave'];
        $idFinanceiro = (int) $fatura['id'];

        if ($this->jaEnviado($chave, $idFinanceiro, $tipo, $canal, $dataReferencia)) {
            $stats['skipped']++;
            return;
        }

        try {
            $messageId = queue_template_message($template, $canal, $context, $chave);

            if ($messageId <= 0) {
                $this->registrarEnvio($chave, $idFinanceiro, $tipo, $canal, $dataReferencia, null, 'skipped', 'Destinatario ausente para o canal');
                $stats['skipped']++;
                return;
            }

            $this->registrarEnvio($chave, $idFinanceiro, $tipo, $canal, $dataReferencia, $messageId, 'queued', null);
            $stats['queued']++;
        } catch (\Throwable $e) {
            $this->registrarEnvio($chave, $idFinanceiro, $tipo, $canal, $dataReferencia, null, 'failed', $e->getMessage());
            $stats['failed']++;
            $stats['errors'][] = [
                'id_financeiro' => $idFinanceiro,
                'chave' => $chave,
                'tipo' => $tipo,
                'canal' => $canal,
                'erro' => $e->getMessage(),
            ];
        }
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
            $query->whereRaw('last_sent_at >= DATE_SUB(NOW(), INTERVAL ? DAY)', [self::OVERDUE_INTERVAL_DAYS]);
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
                (?, ?, ?, ?, ?, NOW(), ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                last_sent_at = VALUES(last_sent_at),
                message_id = VALUES(message_id),
                status = VALUES(status),
                error_message = VALUES(error_message),
                updated_at = NOW()
        ");

        if (!$stmt) {
            throw new \RuntimeException('Erro ao preparar registro de notificacao de cobranca: ' . $this->mysqli->error);
        }

        $stmt->bind_param(
            'sisssiss',
            $chave,
            $idFinanceiro,
            $tipo,
            $canal,
            $dataReferencia,
            $messageId,
            $status,
            $errorMessage
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
        $telefone = trim((string) ($fatura['cliente_telefone'] ?? $fatura['cliente_celular'] ?? ''));

        if ($email !== '') {
            $canais[] = 'email';
        }

        if ($filialId > 0 && $telefone !== '') {
            if ((new Whatsapp())->buscarConectadaPorFilial($filialId) !== null) {
                $canais[] = 'whatsapp';
            }

            if ((new Sms())->buscarValidadaPorFilial($filialId) !== null) {
                $canais[] = 'sms';
            }
        }

        return $canais;
    }

    private function buildContext(array $fatura, string $linkPagamento): array
    {
        $nome = (string) ($fatura['cliente_nome'] ?? '');
        $telefone = trim((string) ($fatura['cliente_telefone'] ?? $fatura['cliente_celular'] ?? ''));

        return [
            'cliente' => [
                'nome' => $nome,
                'primeiro_nome' => explode(' ', trim($nome))[0] ?? '',
                'email' => $fatura['cliente_email'] ?? '',
                'cpf_cnpj' => $fatura['cliente_cpf_cnpj'] ?? '',
                'telefone' => $telefone,
                'celular' => $telefone,
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

    private function diasAtraso(?string $dataVencimento): int
    {
        if (empty($dataVencimento) || $dataVencimento >= date('Y-m-d')) {
            return 0;
        }

        $vencimento = new \DateTimeImmutable($dataVencimento);
        $hoje = new \DateTimeImmutable(date('Y-m-d'));

        return (int) $vencimento->diff($hoje)->days;
    }

    private function setContextoTenant(string $chave): void
    {
        $_SESSION['chave'] = $chave;
        $_SESSION['user_id'] = 0;
        $_SESSION['user_name'] = 'Sistema';
    }

    private function limparContextoTenant(): void
    {
        unset($_SESSION['chave'], $_SESSION['user_id'], $_SESSION['user_name']);
    }
}
