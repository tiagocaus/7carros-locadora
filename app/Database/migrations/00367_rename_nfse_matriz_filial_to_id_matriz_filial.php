<?php

/**
 * Migration 00367: Renomear nfse.matriz_filial para nfse.id_matriz_filial
 *
 * Alinha o schema da tabela nfse com o codigo, filtros de filial e
 * nfse_configuracoes, que usam id_matriz_filial como nome padrao.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (!$this->tableExists('nfse')) {
            return;
        }

        if ($this->columnExists('nfse', 'matriz_filial') && !$this->columnExists('nfse', 'id_matriz_filial')) {
            $this->renameColumnPreservingType('nfse', 'matriz_filial', 'id_matriz_filial');
            return;
        }

        if ($this->columnExists('nfse', 'matriz_filial') && $this->columnExists('nfse', 'id_matriz_filial')) {
            $this->execute("
                UPDATE nfse
                SET id_matriz_filial = matriz_filial
                WHERE (id_matriz_filial IS NULL OR id_matriz_filial = 0)
                  AND matriz_filial IS NOT NULL
            ");

            $this->dropColumnIfExists('nfse', 'matriz_filial');
        }
    }

    public function down(): void
    {
        if (!$this->tableExists('nfse')) {
            return;
        }

        if ($this->columnExists('nfse', 'id_matriz_filial') && !$this->columnExists('nfse', 'matriz_filial')) {
            $this->renameColumnPreservingType('nfse', 'id_matriz_filial', 'matriz_filial');
            return;
        }

        if ($this->columnExists('nfse', 'id_matriz_filial') && $this->columnExists('nfse', 'matriz_filial')) {
            $this->execute("
                UPDATE nfse
                SET matriz_filial = id_matriz_filial
                WHERE (matriz_filial IS NULL OR matriz_filial = 0)
                  AND id_matriz_filial IS NOT NULL
            ");

            $this->dropColumnIfExists('nfse', 'id_matriz_filial');
        }
    }
};
