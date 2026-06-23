<?php

namespace App\Crons\Jobs;

use App\Services\FinanceiroCobrancaAutomaticaService;

class SendFinanceiroCobrancasJob extends BaseJob
{
    protected string $name = 'SendFinanceiroCobrancas';
    protected string $description = 'Envia lembretes de cobranca pre-vencimento e avisos de faturas vencidas';

    protected function handle(): array
    {
        $this->log('Iniciando cobrancas automaticas do financeiro...');

        $stats = (new FinanceiroCobrancaAutomaticaService())->processar();

        $this->log(
            "Candidatas D-1: {$stats['pre_due_candidates']}; "
            . "vencidas: {$stats['overdue_candidates']}; "
            . "enfileiradas: {$stats['queued']}; "
            . "ignoradas: {$stats['skipped']}; "
            . "falhas: {$stats['failed']}"
        );

        return [
            'success' => $stats['failed'] === 0,
            'message' => "{$stats['queued']} cobranca(s) enfileirada(s)",
            'data' => $stats,
        ];
    }
}
