<?php

use App\Database\Migration;

/**
 * Cria o plano global usado nos repasses de comissoes aos investidores.
 */
return new class extends Migration
{
    private const HIERARQUIA = '3.3.1.10';
    private const DESCRICAO_PT_BR = 'Comissões Investidores';

    public function up(): void
    {
        if (!$this->tableExists('planos_de_contas')) {
            throw new \RuntimeException('Tabela planos_de_contas nao encontrada');
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, chave, tipo, descricao_i18n
             FROM planos_de_contas
             WHERE hierarquia = ?
             LIMIT 1'
        );
        $stmt->execute([self::HIERARQUIA]);
        $existente = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($existente) {
            $descricao = json_decode((string) ($existente['descricao_i18n'] ?? ''), true);
            $valido = (string) $existente['chave'] === '0'
                && (string) $existente['tipo'] === 'D'
                && ($descricao['pt_BR'] ?? null) === self::DESCRICAO_PT_BR;

            if (!$valido) {
                throw new \RuntimeException(
                    'A hierarquia 3.3.1.10 ja esta ocupada por outro plano de contas'
                );
            }

            return;
        }

        $descricao = json_encode([
            'pt_BR' => self::DESCRICAO_PT_BR,
            'pt_PT' => 'Comissões de Investidores',
            'en_US' => 'Investor Commissions',
            'es_ES' => 'Comisiones de Inversores',
            'it_IT' => 'Commissioni degli Investitori',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $insert = $this->pdo->prepare(
            'INSERT INTO planos_de_contas (chave, hierarquia, descricao_i18n, tipo)
             VALUES (?, ?, ?, ?)'
        );
        $insert->execute(['0', self::HIERARQUIA, $descricao, 'D']);
    }

    public function down(): void
    {
        if (!$this->tableExists('planos_de_contas')) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'DELETE FROM planos_de_contas
             WHERE chave = ? AND hierarquia = ? AND tipo = ?'
        );
        $stmt->execute(['0', self::HIERARQUIA, 'D']);
    }
};
