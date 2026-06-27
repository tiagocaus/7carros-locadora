<?php

namespace App\Models;

use App\Core\Auth;

class CaucaoDeposito extends Model
{
    public function contarPendentesNotificacao(): int
    {
        return $this->contarPorSituacao('notificacao');
    }

    public function listarParaNotificacoes(int $limit = 25, int $offset = 0): array
    {
        $result = $this->buscar([
            'status' => 'notificacao',
            'page' => (int) floor($offset / max(1, $limit)) + 1,
            'perPage' => $limit,
        ]);

        return $result['details'];
    }

    public function buscar(array $filters = []): array
    {
        $chave = Auth::chave();
        if (empty($chave)) {
            return ['totals' => $this->emptyTotals(), 'details' => [], 'total' => 0];
        }

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($filters['perPage'] ?? 25)));
        $offset = ($page - 1) * $perPage;

        [$where, $params, $types] = $this->buildWhere($filters);
        $baseSql = $this->baseUnionSql();

        $countSql = "SELECT COUNT(*) AS total FROM ({$baseSql}) caucoes WHERE {$where}";
        $total = (int) ($this->fetchOne($countSql, $types, $params)['total'] ?? 0);

        $totalsSql = "
            SELECT
                COUNT(*) AS quantidade,
                COALESCE(SUM(valor), 0) AS valor_total,
                COALESCE(SUM(CASE WHEN status = 'ativa' THEN 1 ELSE 0 END), 0) AS ativas,
                COALESCE(SUM(CASE WHEN status = 'devolvida' THEN 1 ELSE 0 END), 0) AS devolvidas,
                COALESCE(SUM(CASE WHEN status = 'ativa' AND data_prevista_devolucao < CURDATE() THEN 1 ELSE 0 END), 0) AS vencidas,
                COALESCE(SUM(CASE WHEN status = 'ativa' AND data_prevista_devolucao BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END), 0) AS proximas
            FROM ({$baseSql}) caucoes
            WHERE {$where}
        ";
        $totals = $this->fetchOne($totalsSql, $types, $params) ?: $this->emptyTotals();

        $detailsSql = "
            SELECT *
            FROM ({$baseSql}) caucoes
            WHERE {$where}
            ORDER BY
                CASE
                    WHEN status = 'ativa' AND data_prevista_devolucao < CURDATE() THEN 0
                    WHEN status = 'ativa' AND data_prevista_devolucao <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1
                    WHEN status = 'ativa' THEN 2
                    ELSE 3
                END,
                data_prevista_devolucao ASC,
                id DESC
            LIMIT ? OFFSET ?
        ";
        $detailsParams = array_merge($params, [$perPage, $offset]);
        $detailsTypes = $types . 'ii';

        return [
            'totals' => [
                'quantidade' => (int) ($totals['quantidade'] ?? 0),
                'valor_total' => (float) ($totals['valor_total'] ?? 0),
                'ativas' => (int) ($totals['ativas'] ?? 0),
                'devolvidas' => (int) ($totals['devolvidas'] ?? 0),
                'vencidas' => (int) ($totals['vencidas'] ?? 0),
                'proximas' => (int) ($totals['proximas'] ?? 0),
            ],
            'details' => array_map([$this, 'normalizeRow'], $this->fetchAll($detailsSql, $detailsTypes, $detailsParams)),
            'total' => $total,
        ];
    }

    public function contarPorSituacao(string $status): int
    {
        $chave = Auth::chave();
        if (empty($chave)) {
            return 0;
        }

        [$where, $params, $types] = $this->buildWhere(['status' => $status]);
        $sql = "SELECT COUNT(*) AS total FROM ({$this->baseUnionSql()}) caucoes WHERE {$where}";

        return (int) ($this->fetchOne($sql, $types, $params)['total'] ?? 0);
    }

    private function buildWhere(array $filters): array
    {
        $where = ['chave = ?'];
        $params = [Auth::chave()];
        $types = 's';

        if (!empty($filters['origem'])) {
            $where[] = 'origem = ?';
            $params[] = (string) $filters['origem'];
            $types .= 's';
        }

        if (!empty($filters['status'])) {
            $status = (string) $filters['status'];
            if ($status === 'notificacao') {
                $where[] = "status = 'ativa' AND data_prevista_devolucao <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
            } elseif ($status === 'vencida') {
                $where[] = "status = 'ativa' AND data_prevista_devolucao < CURDATE()";
            } elseif ($status === 'proxima') {
                $where[] = "status = 'ativa' AND data_prevista_devolucao BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
            } elseif (in_array($status, ['ativa', 'devolvida', 'cancelada'], true)) {
                $where[] = 'status = ?';
                $params[] = $status;
                $types .= 's';
            }
        }

        if (!empty($filters['filial'])) {
            $where[] = '(id_filial = ? OR id_filial_devolucao = ?)';
            $params[] = (int) $filters['filial'];
            $params[] = (int) $filters['filial'];
            $types .= 'ii';
        } else {
            $filiaisPermitidas = Auth::filiaisPermitidas();
            if (!empty($filiaisPermitidas)) {
                $placeholders = implode(',', array_fill(0, count($filiaisPermitidas), '?'));
                $where[] = "(id_filial IN ({$placeholders}) OR id_filial_devolucao IN ({$placeholders}))";
                foreach ($filiaisPermitidas as $filialId) {
                    $params[] = (int) $filialId;
                    $types .= 'i';
                }
                foreach ($filiaisPermitidas as $filialId) {
                    $params[] = (int) $filialId;
                    $types .= 'i';
                }
            }
        }

        if (!empty($filters['data_inicio'])) {
            $where[] = 'data_base >= ?';
            $params[] = (string) $filters['data_inicio'];
            $types .= 's';
        }

        if (!empty($filters['data_fim'])) {
            $where[] = 'data_base <= ?';
            $params[] = (string) $filters['data_fim'];
            $types .= 's';
        }

        if (!empty($filters['cliente'])) {
            $where[] = 'id_cliente = ?';
            $params[] = (int) $filters['cliente'];
            $types .= 'i';
        }

        return [implode(' AND ', $where), $params, $types];
    }

    private function baseUnionSql(): string
    {
        return "
            SELECT
                lc.chave,
                'locacao' AS origem,
                lc.id,
                lc.id_locacao AS id_origem,
                l.codigo,
                l.status AS status_origem,
                lc.id_cliente,
                COALESCE(c.nome_rsocial, l.cliente_nome) AS cliente_nome,
                l.id_matriz_filial_retirada AS id_filial,
                l.id_matriz_filial_devolucao AS id_filial_devolucao,
                COALESCE(mf.nome_fantasia, mf.razao_social) AS filial_nome,
                DATE(COALESCE(l.data_chegada, l.data_prevista, l.data_saida, lc.created_at)) AS data_base,
                DATE_ADD(DATE(COALESCE(l.data_chegada, l.data_prevista, l.data_saida, lc.created_at)), INTERVAL COALESCE(lc.prazo_devolucao, 0) DAY) AS data_prevista_devolucao,
                lc.data_devolucao,
                lc.valor,
                lc.status,
                lc.lancar_financeiro,
                lc.id_financeiro_entrada,
                fe.pago AS entrada_paga,
                lc.id_financeiro_devolucao,
                lc.id_conta,
                cb.nome AS conta_nome,
                lc.id_forma_pagamento,
                fp.nome AS forma_pagamento_nome
            FROM locacoes_caucoes lc
            INNER JOIN locacoes l ON l.id = lc.id_locacao AND l.chave = lc.chave
            LEFT JOIN clientes c ON c.id = lc.id_cliente AND c.chave = lc.chave
            LEFT JOIN matrizes_filiais mf ON mf.id = l.id_matriz_filial_retirada AND mf.chave = lc.chave
            LEFT JOIN contas_bancarias cb ON cb.id = lc.id_conta AND cb.chave = lc.chave
            LEFT JOIN formas_pagamento fp ON fp.id = lc.id_forma_pagamento AND fp.chave = lc.chave
            LEFT JOIN financeiro fe ON fe.id = lc.id_financeiro_entrada AND fe.chave = lc.chave

            UNION ALL

            SELECT
                cc.chave,
                'contrato' AS origem,
                cc.id,
                cc.id_contrato AS id_origem,
                c.codigo,
                c.status AS status_origem,
                cc.id_cliente,
                cli.nome_rsocial AS cliente_nome,
                c.id_matriz_filial_retirada AS id_filial,
                NULL AS id_filial_devolucao,
                COALESCE(mf.nome_fantasia, mf.razao_social) AS filial_nome,
                DATE(COALESCE(c.data_fim, c.data_ini, cc.created_at)) AS data_base,
                DATE_ADD(DATE(COALESCE(c.data_fim, c.data_ini, cc.created_at)), INTERVAL COALESCE(cc.prazo_devolucao, 0) DAY) AS data_prevista_devolucao,
                cc.data_devolucao,
                cc.valor,
                cc.status,
                cc.lancar_financeiro,
                cc.id_financeiro_entrada,
                fe.pago AS entrada_paga,
                cc.id_financeiro_devolucao,
                cc.id_conta,
                cb.nome AS conta_nome,
                cc.id_forma_pagamento,
                fp.nome AS forma_pagamento_nome
            FROM contratos_caucoes cc
            INNER JOIN contratos c ON c.id = cc.id_contrato AND c.chave = cc.chave
            LEFT JOIN clientes cli ON cli.id = cc.id_cliente AND cli.chave = cc.chave
            LEFT JOIN matrizes_filiais mf ON mf.id = c.id_matriz_filial_retirada AND mf.chave = cc.chave
            LEFT JOIN contas_bancarias cb ON cb.id = cc.id_conta AND cb.chave = cc.chave
            LEFT JOIN formas_pagamento fp ON fp.id = cc.id_forma_pagamento AND fp.chave = cc.chave
            LEFT JOIN financeiro fe ON fe.id = cc.id_financeiro_entrada AND fe.chave = cc.chave
        ";
    }

    private function normalizeRow(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['id_origem'] = (int) $row['id_origem'];
        $row['id_cliente'] = !empty($row['id_cliente']) ? (int) $row['id_cliente'] : null;
        $row['valor'] = (float) $row['valor'];
        $row['lancar_financeiro'] = (int) $row['lancar_financeiro'];
        $row['id_financeiro_entrada'] = !empty($row['id_financeiro_entrada']) ? (int) $row['id_financeiro_entrada'] : null;
        $row['id_financeiro_devolucao'] = !empty($row['id_financeiro_devolucao']) ? (int) $row['id_financeiro_devolucao'] : null;
        $row['situacao'] = $this->situacao($row);
        return $row;
    }

    private function situacao(array $row): string
    {
        if (($row['status'] ?? '') !== 'ativa') {
            return (string) ($row['status'] ?? '');
        }

        $prevista = strtotime((string) ($row['data_prevista_devolucao'] ?? ''));
        if ($prevista === false) {
            return 'ativa';
        }

        $hoje = strtotime(date('Y-m-d'));
        if ($prevista < $hoje) {
            return 'vencida';
        }
        if ($prevista <= strtotime('+7 days', $hoje)) {
            return 'proxima';
        }

        return 'ativa';
    }

    private function emptyTotals(): array
    {
        return ['quantidade' => 0, 'valor_total' => 0.0, 'ativas' => 0, 'devolvidas' => 0, 'vencidas' => 0, 'proximas' => 0];
    }

    private function fetchOne(string $sql, string $types, array $params): ?array
    {
        $rows = $this->fetchAll($sql, $types, $params);
        return $rows[0] ?? null;
    }

    private function fetchAll(string $sql, string $types, array $params): array
    {
        $stmt = $this->getMysqli()->prepare($sql);
        if (!$stmt) {
            throw new \RuntimeException('Erro ao preparar consulta de caucao: ' . $this->getMysqli()->error);
        }

        if ($params) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        return $rows;
    }
}
