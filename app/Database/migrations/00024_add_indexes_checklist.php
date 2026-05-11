<?php

/**
 * Migration 00024: Índices para tabela checklist
 *
 * Adiciona índices para acelerar buscas na tabela de checklists (14k registros).
 * Nota: Tabela tem 47MB por conter imagens base64.
 * Checklists são consultados ao abrir/fechar locações e em relatórios de vistoria.
 *
 * Índices criados:
 * - idx_checklist_chave: Filtro por tenant
 * - idx_checklist_chave_veiculo: Busca checklists do veículo
 * - idx_checklist_codigo: Busca por código da locação/contrato
 * - idx_checklist_chave_status: Filtro por status (preenchido, pendente)
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Índice para filtro por tenant
        $this->addIndexIfNotExists('checklist', 'chave', 'idx_checklist_chave');

        // Índice composto: busca checklists do veículo dentro do tenant
        $this->addIndexIfNotExists('checklist', ['chave', 'id_veiculo'], 'idx_checklist_chave_veiculo');

        // Índice para busca por código da locação/contrato
        $this->addIndexIfNotExists('checklist', 'codigo', 'idx_checklist_codigo');

        // Índice composto: filtro por status dentro do tenant
        $this->addIndexIfNotExists('checklist', ['chave', 'status'], 'idx_checklist_chave_status');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('checklist', 'idx_checklist_chave_status');
        $this->dropIndexIfExists('checklist', 'idx_checklist_codigo');
        $this->dropIndexIfExists('checklist', 'idx_checklist_chave_veiculo');
        $this->dropIndexIfExists('checklist', 'idx_checklist_chave');
    }
};
