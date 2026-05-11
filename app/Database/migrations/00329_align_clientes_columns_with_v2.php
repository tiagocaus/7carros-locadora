<?php

/**
 * Migration 00329: Alinha colunas da tabela clientes com o schema canonico (v2)
 *
 * 6 renomeacoes que foram feitas manualmente no banco de desenvolvimento ao
 * longo do tempo e nunca viraram migration. Esta migration recupera o atraso.
 *
 * Mapeamento (legado -> canonico):
 *   nasci      -> nascimento
 *   num        -> numero
 *   comple     -> complemento
 *   cnh        -> cnh_numero
 *   codigo     -> cnh_codigo_seguranca
 *   categoria  -> cnh_categoria
 *
 * Idempotente: cada rename so executa se a coluna antiga existir e a nova nao.
 */

use App\Database\Migration;

return new class extends Migration
{
    private array $renames = [
        'nasci'     => 'nascimento',
        'num'       => 'numero',
        'comple'    => 'complemento',
        'cnh'       => 'cnh_numero',
        'codigo'    => 'cnh_codigo_seguranca',
        'categoria' => 'cnh_categoria',
    ];

    public function up(): void
    {
        foreach ($this->renames as $de => $para) {
            if ($this->columnExists('clientes', $de) && !$this->columnExists('clientes', $para)) {
                $this->renameColumnPreservingType('clientes', $de, $para);
            }
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->renames) as $de => $para) {
            if ($this->columnExists('clientes', $para) && !$this->columnExists('clientes', $de)) {
                $this->renameColumnPreservingType('clientes', $para, $de);
            }
        }
    }
};
