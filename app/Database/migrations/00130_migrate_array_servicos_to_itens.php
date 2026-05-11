<?php

/**
 * Migration 00130: Migrar array_servicos para manutencoes_itens
 *
 * Migra os dados JSON da coluna array_servicos para a tabela normalizada.
 *
 * Formato do JSON em array_servicos:
 * [["ordem","descricao","qtd","valor_unit","valor_total"], ...]
 *
 * Exemplo:
 * [["1","PONTEIRA","1","40.00","40.00"],["2","MÃO DE OBRA","1","90.00","90.00"]]
 *
 * Dados legados ficam com id_estoque = NULL.
 * Itens de manutencoes fechadas (status='F') sao marcados como pagos.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Verificar se a tabela manutencoes_itens existe
        if (!$this->tableExists('manutencoes_itens')) {
            throw new \RuntimeException('Tabela manutencoes_itens nao existe. Execute a migration 00128 primeiro.');
        }

        // Verificar se ja existem itens (evita duplicacao em re-execucao)
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM manutencoes_itens");
        if ((int) $stmt->fetchColumn() > 0) {
            return; // Ja migrado
        }

        // Buscar todas as manutencoes com array_servicos preenchido
        $stmt = $this->pdo->query("
            SELECT id, chave, array_servicos, status
            FROM manutencoes
            WHERE array_servicos IS NOT NULL
              AND array_servicos != ''
              AND array_servicos != '[]'
        ");

        $inseridos = 0;
        $erros = 0;

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $itens = json_decode($row['array_servicos'], true);

            if (!is_array($itens) || empty($itens)) {
                continue;
            }

            // Manutencoes fechadas tem itens como pagos
            $pago = $row['status'] === 'F' ? 'S' : 'N';

            foreach ($itens as $item) {
                // Formato: [ordem, descricao, qtd, valor_unit, valor_total]
                if (!is_array($item) || count($item) < 5) {
                    $erros++;
                    continue;
                }

                $ordem = (int) ($item[0] ?? 1);
                $descricao = trim($item[1] ?? 'Item sem descricao');
                $quantidade = (float) str_replace(',', '.', $item[2] ?? '1');
                $valorUnitario = (float) str_replace(',', '.', $item[3] ?? '0');
                $valorTotal = (float) str_replace(',', '.', $item[4] ?? '0');

                // Inserir item na nova tabela
                $insertStmt = $this->pdo->prepare("
                    INSERT INTO manutencoes_itens
                    (chave, id_manutencao, id_estoque, descricao, quantidade, valor_unitario, valor_total, pago, ordem)
                    VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?)
                ");

                $insertStmt->execute([
                    $row['chave'],
                    $row['id'],
                    $descricao,
                    $quantidade,
                    $valorUnitario,
                    $valorTotal,
                    $pago,
                    $ordem
                ]);

                $inseridos++;
            }
        }

        // Log de resultado (apenas se executando via CLI)
        if (php_sapi_name() === 'cli') {
            echo "Migracao concluida: {$inseridos} itens inseridos, {$erros} erros.\n";
        }
    }

    public function down(): void
    {
        // Limpar todos os itens migrados
        // Nota: itens criados manualmente apos a migration serao perdidos
        $this->execute("TRUNCATE TABLE manutencoes_itens");
    }
};
