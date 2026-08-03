<?php

namespace App\Services;

use App\Core\Database;

/**
 * Service para logging de auditoria de operações CRUD
 *
 * Registra todas as alterações em entidades do sistema para
 * rastreabilidade e auditoria.
 *
 * Os dados de campos alterados são capturados pelo JavaScript (form-audit.js)
 * e enviados nos campos _audit_data (cadastro) ou _audit_changes (edição).
 */
class AuditLogService
{
    /**
     * Registra log usando dados de auditoria do frontend
     *
     * @param string $mensagem Descrição da ação realizada
     * @param string|null $auditData JSON do campo _audit_data (cadastro)
     * @param string|null $auditChanges JSON do campo _audit_changes (edição)
     * @return int ID do log criado
     */
    public static function registrarComAuditFrontend(
        string $mensagem,
        ?string $auditData = null,
        ?string $auditChanges = null
    ): int {
        $chave = $_SESSION['chave'] ?? null;
        $usuarioId = self::resolveFuncionarioId();
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        if (!$chave) {
            return 0;
        }

        $camposAlterados = null;

        // Prioridade: _audit_changes (edição) > _audit_data (cadastro)
        $jsonString = ($auditChanges !== null && $auditChanges !== '' && $auditChanges !== '[]')
            ? $auditChanges
            : $auditData;
        $isEditMode = ($auditChanges !== null && $auditChanges !== '' && $auditChanges !== '[]');

        if ($jsonString !== null && $jsonString !== '' && $jsonString !== '[]' && $jsonString !== '{}') {
            // Tentar decodificar diretamente
            $decoded = json_decode($jsonString, true);

            // Se falhou, pode ter escape duplo - tentar stripslashes
            if (json_last_error() !== JSON_ERROR_NONE) {
                $decoded = json_decode(stripslashes($jsonString), true);
            }

            // Se ainda falhou, tentar mais uma vez (triplo escape raro)
            if (json_last_error() !== JSON_ERROR_NONE && strpos($jsonString, '\\\\') !== false) {
                $decoded = json_decode(stripslashes(stripslashes($jsonString)), true);
            }

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && !empty($decoded)) {
                // Detectar formato: objeto agrupado por aba ou array plano
                // Se não tem índice 0 numérico, é objeto agrupado {"Aba": [...]}
                $isGroupedFormat = !isset($decoded[0]);

                if ($isGroupedFormat) {
                    // Novo formato: {"Aba": [{label, de, para}, ...]}
                    if ($isEditMode) {
                        // Para edição, usar direto
                        $camposAlterados = $decoded;
                    } else {
                        // Para cadastro, filtrar campos sem valor em cada aba
                        foreach ($decoded as $aba => $campos) {
                            if (!is_array($campos)) continue;
                            $decoded[$aba] = array_values(array_filter($campos, function ($item) {
                                if (!isset($item['para'])) return false;
                                if ($item['para'] === null || $item['para'] === '') return false;
                                if ($item['para'] === 0 || $item['para'] === '0') return true;
                                return !empty($item['para']);
                            }));
                            if (empty($decoded[$aba])) {
                                unset($decoded[$aba]);
                            }
                        }
                        $camposAlterados = !empty($decoded) ? $decoded : null;
                    }
                } else {
                    // Formato antigo: [{aba, label, de, para}, ...]
                    if ($isEditMode) {
                        // Para edição, usar direto
                        $camposAlterados = $decoded;
                    } else {
                        // Para cadastro, filtrar campos sem valor significativo
                        $camposAlterados = array_values(array_filter($decoded, function ($item) {
                            if (!isset($item['para'])) return false;
                            if ($item['para'] === null || $item['para'] === '') return false;
                            if ($item['para'] === 0 || $item['para'] === '0') return true;
                            return !empty($item['para']);
                        }));

                        if (empty($camposAlterados)) {
                            $camposAlterados = null;
                        }
                    }
                }
            }
        }

        return Database::insertGetId('logs', [
            'chave' => $chave,
            'id_funcionario' => $usuarioId,
            'data' => now(),
            'ip' => $ip,
            'mensagem' => $mensagem,
            'campos_alterados' => $camposAlterados !== null
                ? json_encode($camposAlterados, JSON_UNESCAPED_UNICODE)
                : null,
        ]);
    }

    /**
     * Registra log com campos já formatados (para processos sem frontend)
     * Usado em crons, renovações automáticas, processamentos batch, etc.
     *
     * @param string $mensagem Descrição da ação realizada
     * @param array $camposAlterados Array de campos no formato:
     *        [['aba' => 'Nome Aba', 'label' => 'Nome Campo', 'de' => 'valor_antigo', 'para' => 'valor_novo'], ...]
     * @return int ID do log criado
     */
    public static function registrarComCampos(
        string $mensagem,
        array $camposAlterados = []
    ): int {
        $chave = $_SESSION['chave'] ?? null;
        $usuarioId = self::resolveFuncionarioId();
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        if (!$chave) {
            return 0;
        }

        return Database::insertGetId('logs', [
            'chave' => $chave,
            'id_funcionario' => $usuarioId,
            'data' => now(),
            'ip' => $ip,
            'mensagem' => $mensagem,
            'campos_alterados' => !empty($camposAlterados)
                ? json_encode($camposAlterados, JSON_UNESCAPED_UNICODE)
                : null,
        ]);
    }

    /**
     * Registra log simples sem campos alterados
     * Útil para ações que não envolvem alteração de dados (login, logout, acesso, etc.)
     *
     * @param string $mensagem Descrição da ação realizada
     * @return int ID do log criado
     */
    public static function registrar(string $mensagem): int
    {
        $chave = $_SESSION['chave'] ?? null;
        $usuarioId = self::resolveFuncionarioId();
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        if (!$chave) {
            return 0;
        }

        return Database::insertGetId('logs', [
            'chave' => $chave,
            'id_funcionario' => $usuarioId,
            'data' => now(),
            'ip' => $ip,
            'mensagem' => $mensagem,
            'campos_alterados' => null,
        ]);
    }

    /**
     * Registra log de acesso ao sistema
     *
     * @return int ID do log criado
     */
    public static function registrarAcesso(): int
    {
        $nomeUsuario = $_SESSION['user_name'] ?? 'Sistema';
        $mensagem = "{$nomeUsuario}, Entrou no sistema []";

        return self::registrar($mensagem);
    }

    /**
     * Resolve o ator da auditoria.
     *
     * Webhooks, CRONs e outros processos automaticos nao possuem funcionario
     * em sessao. A tabela logs usa o identificador 0 para representar Sistema.
     */
    private static function resolveFuncionarioId(): int
    {
        $usuarioId = (int) ($_SESSION['user_id'] ?? 0);
        return $usuarioId > 0 ? $usuarioId : 0;
    }

    /**
     * Helper para criar array de campo alterado (formato padrão)
     *
     * @param string $label Nome legível do campo
     * @param mixed $valorAntigo Valor antes da alteração
     * @param mixed $valorNovo Valor após a alteração
     * @param string|null $aba Nome da aba (opcional)
     * @return array
     */
    public static function campo(string $label, $valorAntigo, $valorNovo, ?string $aba = null): array
    {
        return [
            'aba' => $aba,
            'label' => $label,
            'de' => $valorAntigo,
            'para' => $valorNovo,
        ];
    }
}
