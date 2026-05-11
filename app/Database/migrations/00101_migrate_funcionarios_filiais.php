<?php

use App\Database\Migration;

/**
 * Migration: Popular tabela funcionarios_filiais
 *
 * Migra dados existentes: cada funcionário que tem id_matriz_filial
 * definido recebe acesso a essa filial na tabela pivot.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Busca todos os funcionários que têm filial principal definida
        $funcionarios = $this->db()->withoutChave()->select(
            'funcionarios',
            ['id', 'chave', 'id_matriz_filial'],
            'id_matriz_filial IS NOT NULL AND deleted_at IS NULL'
        );

        $inserted = 0;

        foreach ($funcionarios as $funcionario) {
            // Verifica se já existe (evitar duplicatas em caso de re-run)
            $exists = $this->db()->withoutChave()->table('funcionarios_filiais')->select(['id'])->whereRaw('id_funcionario = ? AND id_matriz_filial = ?', [$funcionario['id'], $funcionario['id_matriz_filial']])->get();

            if (empty($exists)) {
                $this->db()->table('funcionarios_filiais')->withoutChave()->insert([
                    'id_funcionario' => $funcionario['id'],
                    'id_matriz_filial' => $funcionario['id_matriz_filial'],
                    'chave' => $funcionario['chave']
                ]);
                $inserted++;
            }
        }

        error_log("Migration funcionarios_filiais: {$inserted} registros inseridos");
    }

    public function down(): void
    {
        // Limpa todos os dados da tabela
        $this->db()->withoutChave()->table('funcionarios_filiais')->whereRaw('1=1')->delete();
    }
};
