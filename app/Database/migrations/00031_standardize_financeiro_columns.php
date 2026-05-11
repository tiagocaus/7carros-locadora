<?php

/**
 * Migration 00031: Padronizar Colunas da Tabela financeiro
 *
 * Renomeia 4 colunas para seguir o padrão de nomenclatura.
 * Esta é a maior tabela de dados do sistema (419k registros).
 *
 * Colunas renomeadas:
 * - matriz_filial → id_matriz_filial
 * - formaPagamento → id_forma_pagamento
 * - planoDeConta → id_plano_conta
 * - fornecedor → id_fornecedor
 *
 * NOTA: As colunas id_cliente, id_veiculo, id_multa, id_oficina,
 * id_funcionario, id_conta, id_promissoria já estão com nomes corretos,
 * mas precisarão ter o tipo alterado para int(100) unsigned em migration futura.
 */

use App\Database\Migration;

return new class extends Migration
{
    /**
     * Mapeamento DE → PARA das colunas
     */
    private array $columnRenames = [
        'matriz_filial'   => 'id_matriz_filial',
        'formaPagamento'  => 'id_forma_pagamento',
        'planoDeConta'    => 'id_plano_conta',
        'fornecedor'      => 'id_fornecedor',
    ];

    public function up(): void
    {
        foreach ($this->columnRenames as $oldName => $newName) {
            if ($this->columnExists('financeiro', $oldName) && !$this->columnExists('financeiro', $newName)) {
                $this->renameColumnPreservingType('financeiro', $oldName, $newName);
            }
        }
    }

    public function down(): void
    {
        // Reverte na ordem inversa (PARA → DE)
        $reverseRenames = array_flip($this->columnRenames);

        foreach ($reverseRenames as $newName => $oldName) {
            if ($this->columnExists('financeiro', $newName) && !$this->columnExists('financeiro', $oldName)) {
                $this->renameColumnPreservingType('financeiro', $newName, $oldName);
            }
        }
    }
};
