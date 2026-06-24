<?php

use App\Database\Migration;

class Migration00389RenameVeiculoCombustivelDocumentVariable extends Migration
{
    public function up(): void
    {
        $this->execute("
            UPDATE documentos
            SET texto = REPLACE(texto, '{{veiculo.combustivel}}', '{{veiculo.combustivel_tipo}}')
            WHERE texto LIKE '%{{veiculo.combustivel}}%'
        ");

        $affected = $this->db()->getMysqli()->affected_rows;
        echo "  - Placeholders {{veiculo.combustivel}} migrados: {$affected}\n";
    }

    public function down(): void
    {
        $this->execute("
            UPDATE documentos
            SET texto = REPLACE(texto, '{{veiculo.combustivel_tipo}}', '{{veiculo.combustivel}}')
            WHERE texto LIKE '%{{veiculo.combustivel_tipo}}%'
        ");

        $affected = $this->db()->getMysqli()->affected_rows;
        echo "  - Placeholders {{veiculo.combustivel_tipo}} revertidos: {$affected}\n";
    }
}

return new Migration00389RenameVeiculoCombustivelDocumentVariable();
