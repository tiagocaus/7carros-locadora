<?php

/**
 * Migration 00113: Remover colunas legadas da tabela clientes
 *
 * Remove 20 colunas que foram migradas para tabelas normalizadas:
 *
 * Grupo 1: Campos de Arquivo (10 colunas)
 * - foto_cnh, foto_endereco, foto_rg, foto_ext_1, foto_ext_2
 * - arquivo_cnh, arquivo_endereco, arquivo_rg, arquivo_ext_1, arquivo_ext_2
 * Migrados para: clientes_arquivos
 *
 * Grupo 2: Campos de Cartao de Credito (4 colunas)
 * - cc_bandeira, cc_numero, cc_validade, cc_cv
 * Migrados para: clientes_cartoes
 *
 * Grupo 3: Campos de Contato (4 colunas)
 * - email, tel_cel, tel_com, tel_residenc
 * Migrados para: contatos_emails e contatos_telefones
 *
 * Grupo 4: Campos Obsoletos (2 colunas)
 * - nascion (typo/campo legado - ja existe 'nascimento')
 * - iban (nao utilizado no sistema)
 */

use App\Database\Migration;

return new class extends Migration
{
    /**
     * Colunas a remover organizadas por grupo
     */
    private array $colunas = [
        // Grupo 1: Campos de Arquivo
        'foto_cnh' => ['type' => 'VARCHAR', 'length' => 35, 'null' => true],
        'foto_endereco' => ['type' => 'VARCHAR', 'length' => 35, 'null' => true],
        'foto_rg' => ['type' => 'VARCHAR', 'length' => 35, 'null' => true],
        'foto_ext_1' => ['type' => 'VARCHAR', 'length' => 35, 'null' => true],
        'foto_ext_2' => ['type' => 'VARCHAR', 'length' => 35, 'null' => true],
        'arquivo_cnh' => ['type' => 'VARCHAR', 'length' => 35, 'null' => true],
        'arquivo_endereco' => ['type' => 'VARCHAR', 'length' => 35, 'null' => true],
        'arquivo_rg' => ['type' => 'VARCHAR', 'length' => 35, 'null' => true],
        'arquivo_ext_1' => ['type' => 'VARCHAR', 'length' => 35, 'null' => true],
        'arquivo_ext_2' => ['type' => 'VARCHAR', 'length' => 35, 'null' => true],

        // Grupo 2: Campos de Cartao de Credito
        'cc_bandeira' => ['type' => 'VARCHAR', 'length' => 20, 'null' => true],
        'cc_numero' => ['type' => 'VARCHAR', 'length' => 70, 'null' => true],
        'cc_validade' => ['type' => 'VARCHAR', 'length' => 70, 'null' => true],
        'cc_cv' => ['type' => 'VARCHAR', 'length' => 70, 'null' => true],

        // Grupo 3: Campos de Contato
        'email' => ['type' => 'VARCHAR', 'length' => 100, 'null' => true],
        'tel_cel' => ['type' => 'VARCHAR', 'length' => 20, 'null' => true],
        'tel_com' => ['type' => 'VARCHAR', 'length' => 20, 'null' => true],
        'tel_residenc' => ['type' => 'VARCHAR', 'length' => 20, 'null' => true],

        // Grupo 4: Campos Obsoletos
        'nascion' => ['type' => 'VARCHAR', 'length' => 100, 'null' => true],
        'iban' => ['type' => 'VARCHAR', 'length' => 50, 'null' => true],
    ];

    public function up(): void
    {
        foreach (array_keys($this->colunas) as $coluna) {
            $this->dropColumnIfExists('clientes', $coluna);
        }
    }

    public function down(): void
    {
        foreach ($this->colunas as $coluna => $config) {
            $this->addColumnIfNotExists('clientes', $coluna, $config['type'], [
                'length' => $config['length'] ?? null,
                'null' => $config['null'] ?? true
            ]);
        }
    }
};
