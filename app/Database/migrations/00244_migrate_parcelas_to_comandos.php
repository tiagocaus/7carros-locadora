<?php

use App\Database\Migration;
use App\Models\ComandoParcela;

/**
 * Migration: Migra parcelas existentes da tabela clone para formas_pagamento_comandos
 *
 * Passo 1: Extrai valores distintos de parcelas da formas_pagamento_clone,
 *          normaliza e insere em formas_pagamento_comandos (per-tenant).
 * Passo 2: Popula contratos.id_comando_parcela baseado na forma de pagamento.
 */
return new class extends Migration
{
    /**
     * Mapa de normalização para parcelas "lixo"
     */
    private function normalizarParcelas(string $parcelas): ?string
    {
        $parcelas = trim($parcelas);

        // Valores que significam "a vista" → já existe como sistema (comando=0)
        $aVista = ['', '0', 'não', 'nao', 'a vista', 'A Vista', 'O', '0 - ', '0 -'];
        if (in_array($parcelas, $aVista, true)) {
            return '0';
        }

        // Trailing spaces
        $parcelas = trim($parcelas);

        // "30 dias" → "30"
        if (preg_match('/^(\d+)\s*dias?$/i', $parcelas, $m)) {
            return $m[1];
        }

        // "1X", "1x" → "1"
        if (preg_match('/^(\d+)[xX]$/', $parcelas, $m)) {
            return $m[1];
        }

        // Leading zeros em numero unico: "01" → "1"
        if (preg_match('/^0+(\d+)$/', $parcelas, $m)) {
            return $m[1];
        }

        // Dia da semana por extenso → abreviação
        $diasExtenso = [
            'segunda' => 'Seg', 'Segunda' => 'Seg',
            'terca'   => 'Ter', 'Terca'   => 'Ter', 'terça' => 'Ter', 'Terça' => 'Ter',
            'quarta'  => 'Qua', 'Quarta'  => 'Qua',
            'quinta'  => 'Qui', 'Quinta'  => 'Qui',
            'sexta'   => 'Sex', 'Sexta'   => 'Sex',
            'sabado'  => 'Sab', 'Sabado'  => 'Sab', 'sábado' => 'Sab', 'Sábado' => 'Sab',
            'domingo' => 'Dom', 'Domingo' => 'Dom',
        ];
        if (isset($diasExtenso[$parcelas])) {
            return $diasExtenso[$parcelas];
        }

        // Repetição de dia da semana: "Seg/Seg/Seg/Seg" → "w4-Seg"
        // Suporta: seg, SEG, Seg (case-insensitive)
        if (preg_match('/^([a-zA-Z]{3})(\/\1)+$/i', $parcelas)) {
            $partes = explode('/', $parcelas);
            $n = count($partes);
            $dia = ucfirst(strtolower($partes[0]));
            return "w{$n}-{$dia}";
        }

        // "QUI 07/14/21/28" → remover prefixo de dia e leading zeros
        if (preg_match('/^[A-Z]{3}\s+(.+)$/i', $parcelas, $m)) {
            $parcelas = $m[1];
        }

        // Prazos fixos com / separador
        if (preg_match('/^\d+(\/\d+)+$/', $parcelas)) {
            // Limpar leading zeros: "07/14/21/28" → "7/14/21/28"
            $partes = array_map(function ($p) {
                return ltrim($p, '0') ?: '0';
            }, explode('/', $parcelas));

            // Detectar sequências semanais longas (>6 entradas com intervalo constante de 7)
            // Ex: "7/14/21/.../1400" → "w200", "0/7/14/21/.../903" → "w129"
            if (count($partes) > 6) {
                $nums = array_map('intval', $partes);
                $inicio = $nums[0]; // 0 ou 7
                $intervaloConstante = true;
                for ($i = 1; $i < count($nums); $i++) {
                    if (($nums[$i] - $nums[$i - 1]) !== 7) {
                        $intervaloConstante = false;
                        break;
                    }
                }
                if ($intervaloConstante) {
                    return 'w' . count($nums);
                }
            }

            $resultado = implode('/', $partes);

            // Se ainda excede 255 chars, skip
            if (strlen($resultado) > 255) {
                return null;
            }

            return $resultado;
        }

        // "0/seg" e similares inválidos → skip
        if (preg_match('/^0\/[a-z]/i', $parcelas)) {
            return null;
        }

        // Dash separador em prazos: "2-9-16-23-30" (com 3+ partes) → "2/9/16/23/30"
        if (preg_match('/^\d+(-\d+){2,}$/', $parcelas)) {
            return str_replace('-', '/', $parcelas);
        }

        // Sequências com espaço no meio (ex: "847 854" em vez de "847/854")
        // Normalizar espaços para / e tentar novamente como prazos fixos
        if (preg_match('/^\d+[\s\/]+\d+([\s\/]+\d+)*$/', $parcelas)) {
            $limpo = preg_replace('/[\s]+/', '/', $parcelas);
            if (preg_match('/^\d+(\/\d+)+$/', $limpo)) {
                $partes = array_map(function ($p) {
                    return ltrim($p, '0') ?: '0';
                }, explode('/', $limpo));

                if (count($partes) > 6) {
                    $nums = array_map('intval', $partes);
                    $intervaloConstante = true;
                    for ($i = 1; $i < count($nums); $i++) {
                        if (($nums[$i] - $nums[$i - 1]) !== 7) {
                            $intervaloConstante = false;
                            break;
                        }
                    }
                    if ($intervaloConstante) {
                        return 'w' . count($nums);
                    }
                }

                $resultado = implode('/', $partes);
                if (strlen($resultado) <= 255) {
                    return $resultado;
                }
            }
        }

        // Proteção final: skip valores maiores que 255 chars
        if (strlen($parcelas) > 255) {
            return null;
        }

        return $parcelas;
    }

    public function up(): void
    {
        // Verifica se a tabela clone existe
        if (!$this->tableExists('formas_pagamento_clone')) {
            echo "  [SKIP] Tabela formas_pagamento_clone nao encontrada\n";
            return;
        }

        // ===================================================================
        // PASSO 1: Extrair e inserir comandos de parcelas per-tenant
        // ===================================================================

        // Buscar todos os (chave, parcelas) distintos do clone
        $stmt = $this->pdo->query("
            SELECT DISTINCT chave, parcelas
            FROM formas_pagamento_clone
            WHERE parcelas IS NOT NULL AND TRIM(parcelas) != ''
            ORDER BY chave, parcelas
        ");
        $registros = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Carregar comandos do sistema existentes (chave=0) para comparação
        $stmtSistema = $this->pdo->query("
            SELECT comando FROM formas_pagamento_comandos WHERE chave = '0'
        ");
        $comandosSistema = array_column($stmtSistema->fetchAll(\PDO::FETCH_ASSOC), 'comando');

        // Carregar comandos tenant existentes para evitar duplicatas
        $stmtTenant = $this->pdo->query("
            SELECT chave, comando FROM formas_pagamento_comandos WHERE chave != '0'
        ");
        $comandosTenant = [];
        foreach ($stmtTenant->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $comandosTenant[$row['chave'] . '|' . $row['comando']] = true;
        }

        // Preparar INSERT
        $insertStmt = $this->pdo->prepare("
            INSERT INTO formas_pagamento_comandos (chave, comando, descricao, status)
            VALUES (?, ?, ?, 'A')
        ");

        $inseridos = 0;
        $skipped = 0;

        // Agrupar por (chave, normalizado) para evitar duplicatas
        $paraInserir = [];
        foreach ($registros as $row) {
            $normalizado = $this->normalizarParcelas($row['parcelas']);

            // Skip se invalido
            if ($normalizado === null) {
                $skipped++;
                continue;
            }

            // Skip se é "a vista" (0) — já existe como sistema
            if ($normalizado === '0') {
                $skipped++;
                continue;
            }

            $key = $row['chave'] . '|' . $normalizado;
            if (!isset($paraInserir[$key])) {
                $paraInserir[$key] = [
                    'chave' => $row['chave'],
                    'comando' => $normalizado,
                ];
            }
        }

        foreach ($paraInserir as $item) {
            $chave = $item['chave'];
            $comando = $item['comando'];

            // Pula se já existe como comando do sistema
            if (in_array($comando, $comandosSistema, true)) {
                $skipped++;
                continue;
            }

            // Pula se já existe como comando do tenant
            $tenantKey = $chave . '|' . $comando;
            if (isset($comandosTenant[$tenantKey])) {
                $skipped++;
                continue;
            }

            // Gerar descrição automática
            $descricao = ComandoParcela::inferirLabel($comando);

            $insertStmt->execute([$chave, $comando, $descricao]);
            $comandosTenant[$tenantKey] = true;
            $inseridos++;
        }

        echo "  Comandos inseridos: {$inseridos}, Ignorados: {$skipped}\n";

        // ===================================================================
        // PASSO 2: Popular contratos.id_comando_parcela
        // ===================================================================

        if (!$this->columnExists('contratos', 'id_comando_parcela')) {
            echo "  [SKIP] Coluna contratos.id_comando_parcela nao existe\n";
            return;
        }

        // Indexar todos os comandos por (chave, comando) para lookup rápido
        $stmtCmds = $this->pdo->query("
            SELECT id, chave, comando FROM formas_pagamento_comandos ORDER BY chave DESC
        ");
        $todosComandos = $stmtCmds->fetchAll(\PDO::FETCH_ASSOC);

        // Mapa: chave|comando → id (tenant tem prioridade sobre sistema)
        $mapaComandos = [];
        // Primeiro insere sistema (chave=0), depois tenant sobrescreve
        foreach ($todosComandos as $cmd) {
            $mapaComandos[$cmd['chave'] . '|' . $cmd['comando']] = (int) $cmd['id'];
        }

        // Buscar contratos sem comando + parcelas do clone
        $stmtContratos = $this->pdo->query("
            SELECT c.id, c.chave, c.id_forma_pagamento, fpc.parcelas
            FROM contratos c
            INNER JOIN formas_pagamento_clone fpc ON fpc.id = c.id_forma_pagamento
            WHERE c.id_comando_parcela IS NULL
              AND c.id_forma_pagamento IS NOT NULL
              AND fpc.parcelas IS NOT NULL
        ");

        $updateStmt = $this->pdo->prepare("
            UPDATE contratos SET id_comando_parcela = ? WHERE id = ?
        ");

        $atualizados = 0;
        $naoEncontrados = 0;

        while ($contrato = $stmtContratos->fetch(\PDO::FETCH_ASSOC)) {
            $normalizado = $this->normalizarParcelas($contrato['parcelas']);

            if ($normalizado === null) {
                $naoEncontrados++;
                continue;
            }

            // Buscar comando: preferir tenant, fallback sistema
            $idComando = null;
            $tenantKey = $contrato['chave'] . '|' . $normalizado;
            $sistemaKey = '0|' . $normalizado;

            if (isset($mapaComandos[$tenantKey])) {
                $idComando = $mapaComandos[$tenantKey];
            } elseif (isset($mapaComandos[$sistemaKey])) {
                $idComando = $mapaComandos[$sistemaKey];
            }

            if ($idComando !== null) {
                $updateStmt->execute([$idComando, $contrato['id']]);
                $atualizados++;
            } else {
                $naoEncontrados++;
            }
        }

        echo "  Contratos atualizados: {$atualizados}, Sem match: {$naoEncontrados}\n";
    }

    public function down(): void
    {
        // Remove comandos de tenant (mantem os do sistema chave=0)
        $this->pdo->exec("
            DELETE FROM formas_pagamento_comandos WHERE chave != '0'
        ");

        // Limpa id_comando_parcela dos contratos
        if ($this->columnExists('contratos', 'id_comando_parcela')) {
            $this->pdo->exec("
                UPDATE contratos SET id_comando_parcela = NULL
            ");
        }
    }
};
