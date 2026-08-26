<?php

use App\Database\Migration;

/**
 * Persiste o protocolo e o estado do cancelamento assincrono da Betha.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!$this->tableExists('nfse')) {
            return;
        }

        $this->addColumnIfNotExists('nfse', 'cancelamento_status', 'VARCHAR(20)', [
            'null' => true,
            'after' => 'motivo_cancelamento',
            'comment' => 'Estado do cancelamento assincrono: processando, erro ou concluido',
        ]);
        $this->addColumnIfNotExists('nfse', 'cancelamento_protocolo', 'VARCHAR(50)', [
            'null' => true,
            'after' => 'cancelamento_status',
            'comment' => 'Protocolo do pedido de cancelamento Betha',
        ]);
        $this->addColumnIfNotExists('nfse', 'cancelamento_solicitado_em', 'DATETIME', [
            'null' => true,
            'after' => 'cancelamento_protocolo',
            'comment' => 'Data de recepcao do pedido de cancelamento pelo emissor',
        ]);
        $this->addIndexIfNotExists(
            'nfse',
            ['tipo_emissao', 'status', 'cancelamento_status', 'cancelamento_solicitado_em'],
            'idx_nfse_cancelamento_betha'
        );
    }

    public function down(): void
    {
        if (!$this->tableExists('nfse')) {
            return;
        }

        $this->dropIndexIfExists('nfse', 'idx_nfse_cancelamento_betha');
        $this->dropColumnIfExists('nfse', 'cancelamento_solicitado_em');
        $this->dropColumnIfExists('nfse', 'cancelamento_protocolo');
        $this->dropColumnIfExists('nfse', 'cancelamento_status');
    }
};
