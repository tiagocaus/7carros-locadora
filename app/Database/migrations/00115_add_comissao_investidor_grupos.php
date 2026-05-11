<?php

/**
 * Migration 00115: Adicionar campos de comissao investidor na tabela grupos
 *
 * Adiciona os campos para configurar o tipo e valor de comissao
 * que sera aplicada aos veiculos de investidores neste grupo.
 *
 * Tipos de comissao:
 * - percentual_locadora: Locadora retem X% de cada locacao
 * - fixo_locadora: Locadora retem R$ fixo de cada locacao
 * - fixo_locadora_mensal: Locadora cobra R$ fixo/mes (taxa de gestao)
 * - fixo_investidor_mensal: Locadora paga R$ fixo/mes (aluguel garantido)
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Verificar se a coluna ja existe
        if (!$this->columnExists('grupos', 'comissao_investidor_tipo')) {
            $this->execute("
                ALTER TABLE grupos
                ADD COLUMN comissao_investidor_tipo ENUM(
                    'percentual_locadora',
                    'fixo_locadora',
                    'fixo_locadora_mensal',
                    'fixo_investidor_mensal'
                ) DEFAULT NULL COMMENT 'Tipo de comissao para veiculos de investidores'
                AFTER valor_condutor_adicional
            ");
        }

        if (!$this->columnExists('grupos', 'comissao_investidor_valor')) {
            $this->execute("
                ALTER TABLE grupos
                ADD COLUMN comissao_investidor_valor DECIMAL(10,2) DEFAULT 0.00
                COMMENT 'Valor da comissao (percentual ou fixo conforme tipo)'
                AFTER comissao_investidor_tipo
            ");
        }
    }

    public function down(): void
    {
        if ($this->columnExists('grupos', 'comissao_investidor_valor')) {
            $this->execute("ALTER TABLE grupos DROP COLUMN comissao_investidor_valor");
        }

        if ($this->columnExists('grupos', 'comissao_investidor_tipo')) {
            $this->execute("ALTER TABLE grupos DROP COLUMN comissao_investidor_tipo");
        }
    }
};
