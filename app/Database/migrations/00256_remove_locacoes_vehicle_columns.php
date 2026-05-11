<?php

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Dropar FKs antes de remover colunas
        $this->dropForeignKeyIfExists('locacoes', 'fk_locacoes_id_veiculo');
        $this->dropForeignKeyIfExists('locacoes', 'fk_locacoes_id_grupo');
        $this->dropIndexIfExists('locacoes', 'idx_locacoes_id_veiculo');

        $columns = [
            'id_veiculo',
            'id_grupo',
            'plano',
            'diaria_valor',
            'km_livre_valor',
            'km_valor',
            'km_controlado_valor',
            'km_controlado_franquia',
            'seguro_carro',
            'seguro_carro_valor',
            'cobertura_carro_valor',
            'seguro_terceiros',
            'seguro_terceiros_valor',
            'cobertura_terceiros_valor',
            'odometro_ini',
            'odometro_fim',
            'odometro_usado',
            'combustivel_ini',
            'combustivel_fim',
            'combustivel_usado',
            'combustivel_valor',
            'kmlExcedente',
            'minuto_tolerancia',
            'valor_tolerancia',
            'valor_km_retorno',
            'valor_condutor_adicional',
        ];

        foreach ($columns as $col) {
            $this->dropColumnIfExists('locacoes', $col);
        }

        echo "Removidas " . count($columns) . " colunas de veiculo de locacoes (migradas para locacoes_veiculos)\n";
    }

    public function down(): void
    {
        // Dados ja estao em locacoes_veiculos - restaurar estrutura apenas
        echo "Down: colunas de veiculo nao restauradas (dados em locacoes_veiculos)\n";
    }
};
