<?php

use App\Database\Migration;

/**
 * Permite preservar varias leituras de odometro do mesmo veiculo no mesmo dia.
 */
return new class extends Migration
{
    private const TABLE = 'contratos_odometros';
    private const UNIQUE_INDEX = 'idx_co_unique';
    private const HISTORY_INDEX = 'idx_co_veiculo_data';
    private const CONTRACT_INDEX = 'idx_co_contract_vehicle_data';

    public function up(): void
    {
        if (!$this->tableExists(self::TABLE)) {
            return;
        }

        $this->addIndexIfNotExists(
            self::TABLE,
            ['chave', 'id_contrato_veiculo', 'data', 'id'],
            self::HISTORY_INDEX
        );
        // Mantem um indice iniciado em id_contrato para sustentar a foreign key.
        $this->addIndexIfNotExists(
            self::TABLE,
            ['id_contrato', 'id_contrato_veiculo', 'data', 'id'],
            self::CONTRACT_INDEX
        );
        $this->dropIndexIfExists(self::TABLE, self::UNIQUE_INDEX);
    }

    public function down(): void
    {
        if (!$this->tableExists(self::TABLE)) {
            return;
        }

        $duplicidade = $this->db()
            ->table(self::TABLE)
            ->withoutChave()
            ->select(['id_contrato', 'id_contrato_veiculo', 'data'])
            ->selectRaw('COUNT(*) AS total')
            ->groupBy(['id_contrato', 'id_contrato_veiculo', 'data'])
            ->havingRaw('COUNT(*) > ?', [1])
            ->first();

        if ($duplicidade) {
            throw new \RuntimeException(
                'Rollback bloqueado: existem multiplas leituras de odometro no mesmo dia.'
            );
        }

        if (!$this->indexExists(self::TABLE, self::UNIQUE_INDEX)) {
            $this->table(self::TABLE, function ($table): void {
                $table->unique(
                    ['id_contrato', 'id_contrato_veiculo', 'data'],
                    self::UNIQUE_INDEX
                );
            });
        }

        $this->dropIndexIfExists(self::TABLE, self::CONTRACT_INDEX);
        $this->dropIndexIfExists(self::TABLE, self::HISTORY_INDEX);
    }
};
