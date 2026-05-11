<?php

declare(strict_types=1);

namespace App\Services;

use App\Classes\QueryBuilder;
use App\I18n\TemplateRenderer;
use App\I18n\TemplateVariables;

/**
 * Migrador de documentos legados para o novo sistema de templates
 *
 * Responsabilidades:
 * - Converter variáveis legadas ($var) para novo formato ({{entidade.campo}})
 * - Remover formatação legada (spans com background amarelo)
 * - Migrar documentos da tabela `documentos` para `message_templates`
 *
 * @example Migrations tipicamente fazem `new DocumentMigrator($this->db());`
 */
class DocumentMigrator
{
    private QueryBuilder $db;
    private TemplateRenderer $renderer;
    private array $legacyMapping;
    private array $logs = [];

    /**
     * Padrão para encontrar spans legados com variáveis
     * Matches: <span style="background-color: #f1c40f;" contenteditable="false">$varName</span>
     */
    private const LEGACY_SPAN_PATTERN = '/<span[^>]*style="[^"]*background-color:\s*(?:#f1c40f|#ff0|yellow|rgb\(241,\s*196,\s*15\))[^"]*"[^>]*contenteditable="false"[^>]*>(\$[a-zA-Z]+)<\/span>/i';

    /**
     * Padrão alternativo: spans com qualquer background amarelo
     */
    private const LEGACY_SPAN_ALT_PATTERN = '/<span[^>]*style="[^"]*background-color:\s*(?:#f1c40f|#ff0|yellow|#ffff00|rgb\(255,\s*255,\s*0\)|rgb\(241,\s*196,\s*15\))[^"]*"[^>]*>(\$[a-zA-Z]+)<\/span>/i';

    /**
     * Padrão para variáveis simples (sem span)
     */
    private const SIMPLE_VAR_PATTERN = '/\$([a-zA-Z][a-zA-Z0-9]*)/';

    public function __construct(QueryBuilder|\mysqli $connection)
    {
        if ($connection instanceof QueryBuilder) {
            $this->db = $connection;
        } else {
            $this->db = new QueryBuilder($connection);
            $this->db->withoutChave();
        }
        $this->renderer = new TemplateRenderer();
        $this->legacyMapping = TemplateVariables::getLegacyMapping();
    }

    /**
     * Migra todos os documentos legados
     *
     * @param bool $dryRun Se true, não faz alterações no banco
     * @return array Resultado da migração
     */
    public function migrateAll(bool $dryRun = false): array
    {
        $this->logs = [];
        $result = [
            'total' => 0,
            'migrated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'documents' => [],
        ];

        // Carrega documentos via helper que sempre encerra com get() (array).
        $documents = $this->fetchActiveDocumentsOrdered();

        $result['total'] = count($documents);

        foreach ($documents as $doc) {
            $docResult = $this->migrateDocument($doc, $dryRun);
            $result['documents'][] = $docResult;

            if ($docResult['status'] === 'migrated') {
                $result['migrated']++;
            } elseif ($docResult['status'] === 'skipped') {
                $result['skipped']++;
            } else {
                $result['errors']++;
            }
        }

        return $result;
    }

    /**
     * Migra um documento específico
     *
     * @param array $doc Documento da tabela documentos
     * @param bool $dryRun Se true, não faz alterações
     * @return array Resultado
     */
    public function migrateDocument(array $doc, bool $dryRun = false): array
    {
        $result = [
            'id' => $doc['id'],
            'chave' => $doc['chave'],
            'titulo' => $doc['titulo'],
            'status' => 'pending',
            'original_text' => null,
            'converted_text' => null,
            'variables_found' => [],
            'variables_converted' => [],
            'variables_unknown' => [],
            'error' => null,
        ];

        try {
            $texto = $doc['texto'] ?? '';

            if (empty($texto)) {
                $result['status'] = 'skipped';
                $result['error'] = 'Texto vazio';
                return $result;
            }

            $result['original_text'] = $texto;

            // 1. Extrair e converter variáveis
            $conversion = $this->convertLegacyVariables($texto);
            $result['converted_text'] = $conversion['text'];
            $result['variables_found'] = $conversion['found'];
            $result['variables_converted'] = $conversion['converted'];
            $result['variables_unknown'] = $conversion['unknown'];

            // Se não há diferença, pular
            if ($texto === $conversion['text']) {
                $result['status'] = 'skipped';
                $result['error'] = 'Nenhuma variável legada encontrada';
                return $result;
            }

            // 2. Atualizar no banco (se não for dry run)
            if (!$dryRun) {
                $this->db->table('documentos')
                    ->withoutChave()
                    ->whereRaw('id = ?', [$doc['id']])
                    ->update([
                        'texto' => $conversion['text'],
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
            }

            $result['status'] = 'migrated';
            $this->log("Documento #{$doc['id']} migrado: " . count($conversion['converted']) . " variáveis convertidas");
        } catch (\Exception $e) {
            $result['status'] = 'error';
            $result['error'] = $e->getMessage();
            $this->log("Erro no documento #{$doc['id']}: " . $e->getMessage(), 'error');
        }

        return $result;
    }

    /**
     * Converte variáveis legadas em um texto
     *
     * @param string $text Texto com variáveis legadas
     * @return array ['text' => string, 'found' => [], 'converted' => [], 'unknown' => []]
     */
    public function convertLegacyVariables(string $text): array
    {
        $found = [];
        $converted = [];
        $unknown = [];

        // 1. Primeiro, remover spans de destaque e converter variáveis dentro deles
        $text = preg_replace_callback(
            self::LEGACY_SPAN_ALT_PATTERN,
            function ($matches) use (&$found, &$converted, &$unknown) {
                $legacyVar = $matches[1]; // Ex: $cRSocial
                $found[] = $legacyVar;

                $newVar = $this->legacyMapping[$legacyVar] ?? null;

                if ($newVar) {
                    $converted[$legacyVar] = $newVar;
                    return '{{' . $newVar . '}}';
                }

                $unknown[] = $legacyVar;
                return $legacyVar; // Mantém a variável original se não encontrar mapeamento
            },
            $text
        );

        // 2. Depois, converter variáveis simples que não estão em spans
        $text = preg_replace_callback(
            self::SIMPLE_VAR_PATTERN,
            function ($matches) use (&$found, &$converted, &$unknown) {
                $fullMatch = '$' . $matches[1]; // Ex: $cRSocial

                // Ignorar se já foi convertido (evitar duplicatas)
                if (in_array($fullMatch, $found)) {
                    return $matches[0];
                }

                // Verificar se parece uma variável de template (começa com c, e, l, v, f, o)
                $firstChar = strtolower($matches[1][0] ?? '');
                $isTemplateVar = in_array($firstChar, ['c', 'e', 'l', 'v', 'f', 'o']);

                if (!$isTemplateVar) {
                    return $matches[0]; // Não é uma variável de template
                }

                $found[] = $fullMatch;
                $newVar = $this->legacyMapping[$fullMatch] ?? null;

                if ($newVar) {
                    $converted[$fullMatch] = $newVar;
                    return '{{' . $newVar . '}}';
                }

                $unknown[] = $fullMatch;
                return $matches[0]; // Mantém a variável original
            },
            $text
        );

        // 3. Limpar outros spans de destaque vazios ou residuais
        $text = preg_replace(
            '/<span[^>]*style="[^"]*background-color:\s*(?:#f1c40f|#ff0|yellow|#ffff00)[^"]*"[^>]*>\s*<\/span>/i',
            '',
            $text
        );

        return [
            'text' => $text,
            'found' => array_unique($found),
            'converted' => $converted,
            'unknown' => array_unique($unknown),
        ];
    }

    /**
     * Analisa documentos sem fazer alterações
     *
     * @return array Análise de todos os documentos
     */
    public function analyze(): array
    {
        $result = [
            'total_documents' => 0,
            'documents_with_legacy_vars' => 0,
            'total_variables_found' => 0,
            'unique_variables' => [],
            'unknown_variables' => [],
            'documents' => [],
        ];

        $documents = $this->fetchActiveDocumentsOrdered(['id', 'chave', 'titulo', 'texto']);

        $result['total_documents'] = count($documents);
        $allVars = [];
        $allUnknown = [];

        foreach ($documents as $doc) {
            $texto = $doc['texto'] ?? '';

            if (empty($texto)) {
                continue;
            }

            $conversion = $this->convertLegacyVariables($texto);

            if (!empty($conversion['found'])) {
                $result['documents_with_legacy_vars']++;
                $result['documents'][] = [
                    'id' => $doc['id'],
                    'chave' => $doc['chave'],
                    'titulo' => $doc['titulo'],
                    'variables_found' => $conversion['found'],
                    'variables_converted' => $conversion['converted'],
                    'variables_unknown' => $conversion['unknown'],
                ];

                $allVars = array_merge($allVars, $conversion['found']);
                $allUnknown = array_merge($allUnknown, $conversion['unknown']);
            }
        }

        $result['unique_variables'] = array_unique($allVars);
        $result['unknown_variables'] = array_unique($allUnknown);
        $result['total_variables_found'] = count($result['unique_variables']);

        return $result;
    }

    /**
     * Obtém preview de como o documento ficará após a conversão
     *
     * @param int $documentId ID do documento
     * @return array|null Preview ou null se não encontrado
     */
    public function previewDocument(int $documentId): ?array
    {
        $doc = $this->db->table('documentos')
            ->withoutChave()
            ->select(['*'])
            ->whereRaw('id = ?', [$documentId])
            ->first();

        if (!$doc) {
            return null;
        }

        $conversion = $this->convertLegacyVariables($doc['texto'] ?? '');

        return [
            'id' => $doc['id'],
            'titulo' => $doc['titulo'],
            'original' => $doc['texto'],
            'converted' => $conversion['text'],
            'variables_found' => $conversion['found'],
            'variables_converted' => $conversion['converted'],
            'variables_unknown' => $conversion['unknown'],
            'has_changes' => $doc['texto'] !== $conversion['text'],
        ];
    }

    /**
     * Reverte um documento específico
     * (Requer que tenha sido feito backup antes)
     *
     * @param int $documentId ID do documento
     * @param string $originalText Texto original
     * @return bool Se reverteu com sucesso
     */
    public function revertDocument(int $documentId, string $originalText): bool
    {
        return $this->db->table('documentos')
            ->withoutChave()
            ->whereRaw('id = ?', [$documentId])
            ->update([
                'texto' => $originalText,
                'updated_at' => date('Y-m-d H:i:s'),
            ]) > 0;
    }

    /**
     * Adiciona log
     */
    private function log(string $message, string $level = 'info'): void
    {
        $this->logs[] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => $level,
            'message' => $message,
        ];
    }

    /**
     * Retorna os logs da última operação
     */
    public function getLogs(): array
    {
        return $this->logs;
    }

    /**
     * Retorna estatísticas do mapeamento
     */
    public function getMappingStats(): array
    {
        return [
            'total_mappings' => count($this->legacyMapping),
            'mappings' => $this->legacyMapping,
        ];
    }

    /**
     * Documentos ativos (status = 1) em ordem estável para migração/análise.
     *
     * @param  array<int, string>  $columns  Colunas do SELECT (padrão todas)
     * @return array<int, array<string, mixed>>
     */
    private function fetchActiveDocumentsOrdered(array $columns = ['*']): array
    {
        $rows = $this->db->table('documentos')
            ->withoutChave()
            ->select($columns)
            ->whereRaw('status = ?', [1])
            ->orderByRaw('chave, id')
            ->get();

        if (!is_array($rows)) {
            throw new \RuntimeException(
                'DocumentMigrator: era esperado array após get(), recebeu ' . get_debug_type($rows)
            );
        }

        return $rows;
    }
}
