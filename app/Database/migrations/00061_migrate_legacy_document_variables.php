<?php

use App\Database\Migration;
use App\Services\DocumentMigrator;

/**
 * Migration: Converter variáveis legadas nos documentos
 *
 * Converte variáveis no formato $var para {{entidade.campo}}
 * Ex: $cRSocial → {{cliente.nome}}
 *
 * Também remove formatação legada (spans com background amarelo)
 */
return new class extends Migration
{
    public function up(): void
    {
        echo "  Migrando variáveis legadas nos documentos...\n";

        // Remove tabela auxiliar antiga (backup legado), se existir
        $this->execute('DROP TABLE IF EXISTS documentos_backup_legacy_vars');

        $migrator = new DocumentMigrator($this->db());

        $analysis = $migrator->analyze();
        echo "  - Análise: {$analysis['documents_with_legacy_vars']} documentos com variáveis legadas.\n";

        if ($analysis['documents_with_legacy_vars'] === 0) {
            echo "  - Nenhum documento para migrar.\n";
            return;
        }

        if (!empty($analysis['unknown_variables'])) {
            echo "  - AVISO: Variáveis não mapeadas encontradas:\n";
            foreach ($analysis['unknown_variables'] as $var) {
                echo "    • {$var}\n";
            }
        }

        $result = $migrator->migrateAll(false);

        echo "  - Migração concluída:\n";
        echo "    • Total: {$result['total']}\n";
        echo "    • Migrados: {$result['migrated']}\n";
        echo "    • Ignorados: {$result['skipped']}\n";
        echo "    • Erros: {$result['errors']}\n";

        if ($result['errors'] > 0) {
            echo "  - Documentos com erro:\n";
            foreach ($result['documents'] as $doc) {
                if ($doc['status'] === 'error') {
                    echo "    • ID {$doc['id']}: {$doc['error']}\n";
                }
            }
        }
    }

    public function down(): void
    {
        echo "  Revertendo registro da migration (conteúdos não são restaurados automaticamente).\n";
        $this->execute('DROP TABLE IF EXISTS documentos_backup_legacy_vars');
    }
};
