<?php

/**
 * Migration 00119: Adicionar permissoes para modulo de comissoes de investidores
 *
 * Adiciona as permissoes necessarias para gerenciar comissoes:
 * - comissoes_investidores.visualizar: Ver lista de comissoes
 * - comissoes_investidores.pagar: Marcar comissoes como pagas
 * - comissoes_investidores.cancelar: Cancelar comissoes
 * - comissoes_investidores.exportar: Exportar relatorios
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Usar INSERT IGNORE para evitar erro se ja existir
        $this->execute("
            INSERT IGNORE INTO permissions (`key`, name, description, module) VALUES
            ('comissoes_investidores.visualizar', 'Visualizar Comissoes', 'Listar e visualizar comissoes de investidores', 'comissoes_investidores'),
            ('comissoes_investidores.pagar', 'Pagar Comissoes', 'Marcar comissoes como pagas (gera lancamento no financeiro)', 'comissoes_investidores'),
            ('comissoes_investidores.cancelar', 'Cancelar Comissoes', 'Cancelar comissoes pendentes', 'comissoes_investidores'),
            ('comissoes_investidores.exportar', 'Exportar Comissoes', 'Exportar relatorios de comissoes', 'comissoes_investidores')
        ");
    }

    public function down(): void
    {
        $this->execute("DELETE FROM permissions WHERE module = 'comissoes_investidores'");
    }
};
