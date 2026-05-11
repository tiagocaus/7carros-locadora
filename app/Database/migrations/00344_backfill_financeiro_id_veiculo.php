<?php

/**
 * Migration 00344: Backfill financeiro.id_veiculo a partir das tabelas-fonte.
 *
 * Antes do deploy de 2026, o campo financeiro.id_veiculo nao era preenchido
 * pelos Models Locacao/Contrato/Multa/Manutencao. Lancamentos pre-2026 ficaram
 * com id_veiculo = NULL, impedindo relatorios "por veiculo" de retornar dados
 * historicos. Esta migration faz o backfill via JOINs reversos.
 *
 * Estrategia (4 UPDATEs encadeados, conservadora):
 *   1) Locacoes com 1 unico veiculo  -> backfill via locacoes_veiculos
 *   2) Contratos com 1 unico veiculo -> backfill via contratos_veiculos
 *   3) Manutencoes -> backfill via manutencoes.id_financeiro_principal
 *   4) Multas -> backfill via multas.id_financeiro
 *
 * Para locacoes/contratos com >1 veiculo, NAO faz backfill (ambiguo); ficam NULL.
 * Operacao nao-destrutiva: down() fica vazio porque nao temos como saber quais
 * registros foram alterados pelo up().
 */

use App\Core\Cache;
use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Backfill via locacoes_veiculos (apenas locacoes com 1 veiculo)
        $this->execute("
            UPDATE financeiro f
            INNER JOIN (
                SELECT id_locacao, chave, MIN(id_veiculo) AS id_veiculo
                FROM locacoes_veiculos
                WHERE id_veiculo IS NOT NULL
                GROUP BY id_locacao, chave
                HAVING COUNT(DISTINCT id_veiculo) = 1
            ) lv ON lv.id_locacao = f.id_locacao AND lv.chave = f.chave
            SET f.id_veiculo = lv.id_veiculo
            WHERE f.id_veiculo IS NULL AND f.id_locacao IS NOT NULL
        ");

        // 2) Backfill via contratos_veiculos (apenas contratos com 1 veiculo)
        $this->execute("
            UPDATE financeiro f
            INNER JOIN (
                SELECT id_contrato, chave, MIN(id_veiculo) AS id_veiculo
                FROM contratos_veiculos
                WHERE id_veiculo IS NOT NULL
                GROUP BY id_contrato, chave
                HAVING COUNT(DISTINCT id_veiculo) = 1
            ) cv ON cv.id_contrato = f.id_contrato AND cv.chave = f.chave
            SET f.id_veiculo = cv.id_veiculo
            WHERE f.id_veiculo IS NULL AND f.id_contrato IS NOT NULL
        ");

        // 3) Backfill via manutencoes.id_financeiro_principal
        $this->execute("
            UPDATE financeiro f
            INNER JOIN manutencoes m ON m.id_financeiro_principal = f.id AND m.chave = f.chave
            SET f.id_veiculo = m.id_veiculo
            WHERE f.id_veiculo IS NULL AND m.id_veiculo IS NOT NULL
        ");

        // 4) Backfill via multas.id_financeiro
        $this->execute("
            UPDATE financeiro f
            INNER JOIN multas mt ON mt.id_financeiro = f.id AND mt.chave = f.chave
            SET f.id_veiculo = mt.id_veiculo
            WHERE f.id_veiculo IS NULL AND mt.id_veiculo IS NOT NULL
        ");

        try { Cache::flush(); } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        // Backfill nao-destrutivo: nao revertemos para evitar zerar registros legitimos.
    }
};
