<?php

use App\Database\Migration;

/**
 * Migration: Popular templates i18n de mensagem
 *
 * Adiciona templates padrão (chave = '0') para idiomas adicionais:
 * - en_US (English)
 * - es_ES (Spanish)
 * - it_IT (Italian)
 * - pt_PT (Portuguese - Portugal)
 *
 * Templates pt_BR já foram populados na migração 00060.
 */
return new class extends Migration
{
    public function up(): void
    {
        $seedFile = __DIR__ . '/../seeds/message_templates_i18n.sql';

        if (!file_exists($seedFile)) {
            echo "  - AVISO: Arquivo de seeds i18n não encontrado: {$seedFile}\n";
            return;
        }

        $sql = file_get_contents($seedFile);

        // Divide o SQL em statements individuais
        // Remove comentários de linha única
        $sql = preg_replace('/^--.*$/m', '', $sql);

        // Divide por ponto e vírgula, mas ignora os que estão dentro de strings
        $statements = $this->splitSqlStatements($sql);

        $count = 0;
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (empty($statement)) {
                continue;
            }

            try {
                $this->execute($statement);
                $count++;
            } catch (\PDOException $e) {
                // Ignora erros de duplicidade (já existe o registro)
                if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                    throw $e;
                }
            }
        }

        echo "  - {$count} templates i18n populados.\n";
    }

    public function down(): void
    {
        // Remove apenas os templates padrão de idiomas adicionados por esta migração
        $locales = ['en_US', 'es_ES', 'it_IT', 'pt_PT'];

        foreach ($locales as $locale) {
            $this->execute(
                "DELETE FROM message_templates WHERE chave = '0' AND locale = ?",
                [$locale]
            );
        }

        echo "  - Templates i18n removidos.\n";
    }

    /**
     * Divide SQL em statements individuais, respeitando strings
     */
    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $current = '';
        $inString = false;
        $stringChar = '';
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];

            // Detecta início/fim de string
            if (($char === "'" || $char === '"') && ($i === 0 || $sql[$i - 1] !== '\\')) {
                if (!$inString) {
                    $inString = true;
                    $stringChar = $char;
                } elseif ($char === $stringChar) {
                    $inString = false;
                }
            }

            // Encontrou ponto e vírgula fora de string
            if ($char === ';' && !$inString) {
                $statements[] = $current;
                $current = '';
                continue;
            }

            $current .= $char;
        }

        // Adiciona o último statement se houver
        if (trim($current) !== '') {
            $statements[] = $current;
        }

        return $statements;
    }
};
