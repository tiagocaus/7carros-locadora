<?php

/**
 * Migration: permite vincular um link de pagamento diretamente a uma locacao.
 *
 * Site publico: quando site_config.pagamento_antecipado=1, a reserva criada
 * precisa de um link de pagamento imediato, mas ainda nao ha financeiro gerado.
 * - `id_locacao`: nova coluna (nullable) para vincular o link a uma locacao.
 * - `id_financeiro`: passa a aceitar NULL, pra suportar links criados antes do financeiro.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->execute("
            ALTER TABLE pagamentos_links
                MODIFY COLUMN id_financeiro INT(10) UNSIGNED NULL COMMENT 'FK para tabela financeiro (opcional quando link vincula locacao diretamente)',
                ADD COLUMN id_locacao INT(10) UNSIGNED NULL COMMENT 'FK para tabela locacoes (link de pagamento de reserva antes do financeiro existir)' AFTER id_financeiro,
                ADD INDEX idx_pl_locacao (id_locacao)
        ");
    }

    public function down(): void
    {
        $this->execute("
            ALTER TABLE pagamentos_links
                DROP INDEX idx_pl_locacao,
                DROP COLUMN id_locacao,
                MODIFY COLUMN id_financeiro INT(10) UNSIGNED NOT NULL COMMENT 'FK para tabela financeiro'
        ");
    }
};
