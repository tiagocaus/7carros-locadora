<?php

use App\Database\Migration;

/**
 * Migration: Consolidar formas de pagamento (1 por tipo por tenant)
 *
 * Para cada tenant, agrupa formas pelo tipo e mantém apenas a mais usada.
 * Atualiza todas as FKs (financeiro, contratos, locacoes) para apontar
 * para a forma vencedora. Apos consolidar, atualiza nome = tipo.nome.
 */
return new class extends Migration
{
    public function up(): void
    {
        $mysqli = $this->db()->getMysqli();

        // 1. Buscar todas as combinações chave + id_tipo_pagamento com duplicatas
        $sql = "
            SELECT fp.chave, fp.id_tipo_pagamento, fpt.nome as tipo_nome, COUNT(*) as cnt
            FROM formas_pagamento fp
            LEFT JOIN formas_pagamento_tipos fpt ON fp.id_tipo_pagamento = fpt.id
            WHERE fp.id_tipo_pagamento IS NOT NULL
            GROUP BY fp.chave, fp.id_tipo_pagamento
            ORDER BY fp.chave, fp.id_tipo_pagamento
        ";
        $result = $mysqli->query($sql);

        if (!$result) {
            throw new \RuntimeException("Erro ao buscar combinacoes: " . $mysqli->error);
        }

        $combinacoes = [];
        while ($row = $result->fetch_assoc()) {
            $combinacoes[] = $row;
        }
        $result->free();

        foreach ($combinacoes as $combo) {
            $chave = $combo['chave'];
            $tipoId = $combo['id_tipo_pagamento'];
            $tipoNome = $combo['tipo_nome'] ?? 'Outros';

            // 2. Buscar todas as formas deste combo, ordenadas por uso no financeiro
            $sql = "
                SELECT fp.id,
                       (SELECT COUNT(*) FROM financeiro f WHERE f.id_forma_pagamento = fp.id) as uso_financeiro
                FROM formas_pagamento fp
                WHERE fp.chave = ? AND fp.id_tipo_pagamento = ?
                ORDER BY uso_financeiro DESC, fp.id ASC
            ";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param('si', $chave, $tipoId);
            $stmt->execute();
            $formasResult = $stmt->get_result();

            $formas = [];
            while ($row = $formasResult->fetch_assoc()) {
                $formas[] = $row;
            }
            $stmt->close();

            if (count($formas) < 1) {
                continue;
            }

            // A vencedora = mais usada no financeiro (tiebreak: menor ID)
            $winnerId = $formas[0]['id'];

            // Coletar IDs dos losers (todas as formas EXCETO a vencedora)
            $loserIds = [];
            for ($i = 1; $i < count($formas); $i++) {
                $loserIds[] = $formas[$i]['id'];
            }

            // 3. Se há duplicatas, consolidar
            if (!empty($loserIds)) {
                $placeholders = implode(',', array_fill(0, count($loserIds), '?'));
                $types = str_repeat('i', count($loserIds));

                // 3a. Migrar financeiro
                $sql = "UPDATE financeiro SET id_forma_pagamento = ? WHERE id_forma_pagamento IN ($placeholders)";
                $stmt = $mysqli->prepare($sql);
                $params = array_merge([$winnerId], $loserIds);
                $stmt->bind_param('i' . $types, ...$params);
                $stmt->execute();
                $stmt->close();

                // 3b. Migrar contratos
                $sql = "UPDATE contratos SET id_forma_pagamento = ? WHERE id_forma_pagamento IN ($placeholders)";
                $stmt = $mysqli->prepare($sql);
                $stmt->bind_param('i' . $types, ...$params);
                $stmt->execute();
                $stmt->close();

                // 3c. Migrar locacoes
                $sql = "UPDATE locacoes SET id_forma_pagamento = ? WHERE id_forma_pagamento IN ($placeholders)";
                $stmt = $mysqli->prepare($sql);
                $stmt->bind_param('i' . $types, ...$params);
                $stmt->execute();
                $stmt->close();

                // 3d. Mesclar filiais (INSERT IGNORE para evitar duplicatas)
                foreach ($loserIds as $loserId) {
                    $sql = "
                        INSERT IGNORE INTO formas_pagamento_filiais (id_forma_pagamento, id_matriz_filial, chave)
                        SELECT ?, id_matriz_filial, chave
                        FROM formas_pagamento_filiais
                        WHERE id_forma_pagamento = ?
                    ";
                    $stmt = $mysqli->prepare($sql);
                    $stmt->bind_param('ii', $winnerId, $loserId);
                    $stmt->execute();
                    $stmt->close();
                }

                // 3e. Mesclar gateways (INSERT IGNORE para evitar duplicatas)
                foreach ($loserIds as $loserId) {
                    $sql = "
                        INSERT IGNORE INTO formas_pagamento_gateways (id_forma_pagamento, id_gateway, chave)
                        SELECT ?, id_gateway, chave
                        FROM formas_pagamento_gateways
                        WHERE id_forma_pagamento = ?
                    ";
                    $stmt = $mysqli->prepare($sql);
                    $stmt->bind_param('ii', $winnerId, $loserId);
                    $stmt->execute();
                    $stmt->close();
                }

                // 3f. Limpar filiais e gateways dos losers
                $sql = "DELETE FROM formas_pagamento_filiais WHERE id_forma_pagamento IN ($placeholders)";
                $stmt = $mysqli->prepare($sql);
                $stmt->bind_param($types, ...$loserIds);
                $stmt->execute();
                $stmt->close();

                $sql = "DELETE FROM formas_pagamento_gateways WHERE id_forma_pagamento IN ($placeholders)";
                $stmt = $mysqli->prepare($sql);
                $stmt->bind_param($types, ...$loserIds);
                $stmt->execute();
                $stmt->close();

                // 3g. Deletar formas perdedoras
                $sql = "DELETE FROM formas_pagamento WHERE id IN ($placeholders)";
                $stmt = $mysqli->prepare($sql);
                $stmt->bind_param($types, ...$loserIds);
                $stmt->execute();
                $stmt->close();
            }

            // 4. Atualizar nome da vencedora para o nome do tipo
            $sql = "UPDATE formas_pagamento SET nome = ? WHERE id = ?";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param('si', $tipoNome, $winnerId);
            $stmt->execute();
            $stmt->close();
        }
    }

    public function down(): void
    {
        // Consolidação não é reversível de forma automática.
        // Os dados originais já foram mesclados.
        // Para reverter, seria necessário restaurar backup.
    }
};
