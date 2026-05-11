<?php

/**
 * Migration 00093: Adicionar comentários às colunas da tabela grupos
 *
 * Restaura e atualiza os comentários das colunas que foram perdidos
 * durante migrations anteriores.
 */

use App\Database\Migration;

return new class extends Migration
{
    /**
     * Mapeamento: coluna => [tipo, comentário]
     * O tipo deve corresponder ao tipo atual da coluna
     */
    private array $columns = [
        'nome' => [
            'type' => 'VARCHAR(45)',
            'null' => true,
            'comment' => 'Nome do grupo de veículos',
        ],
        'descricao' => [
            'type' => 'VARCHAR(50)',
            'null' => true,
            'comment' => 'Descrição do grupo',
        ],
        'valor_plano_diaria' => [
            'type' => 'DECIMAL(10,2)',
            'null' => true,
            'default' => '0.00',
            'comment' => 'Valor da diária no plano Diária',
        ],
        'valor_plano_km_controlado' => [
            'type' => 'DECIMAL(10,2)',
            'null' => true,
            'default' => '0.00',
            'comment' => 'Valor da diária no plano Km Controlado',
        ],
        'valor_plano_km_livre' => [
            'type' => 'DECIMAL(10,2)',
            'null' => true,
            'default' => '0.00',
            'comment' => 'Valor da diária no plano Km Livre',
        ],
        'valor_km_excedente' => [
            'type' => 'DECIMAL(10,2)',
            'null' => true,
            'default' => '0.00',
            'comment' => 'Valor por km excedente da franquia',
        ],
        'km_franquia' => [
            'type' => 'INT(5)',
            'null' => false,
            'comment' => 'Km grátis por dia. Referente ao plano Km Controlado',
        ],
        'valor_seguro_carro' => [
            'type' => 'DECIMAL(12,2)',
            'null' => true,
            'default' => '0.00',
            'comment' => 'Valor do seguro do veículo',
        ],
        'valor_seguro_terceiros' => [
            'type' => 'DECIMAL(12,2)',
            'null' => true,
            'default' => '0.00',
            'comment' => 'Valor do seguro contra terceiros',
        ],
        'cobertura_carro' => [
            'type' => 'DECIMAL(12,2)',
            'null' => true,
            'default' => '0.00',
            'comment' => 'Indenização por custos operacionais do veículo',
        ],
        'cobertura_terceiros' => [
            'type' => 'DECIMAL(12,2)',
            'null' => true,
            'default' => '0.00',
            'comment' => 'Indenização por custos operacionais contra terceiros',
        ],
        'minutos_tolerancia' => [
            'type' => 'INT(5)',
            'null' => false,
            'comment' => 'Tempo tolerável no atraso (minutos)',
        ],
        'valor_tolerancia' => [
            'type' => 'DECIMAL(10,2)',
            'null' => true,
            'default' => '0.00',
            'comment' => 'Valor cobrado por minuto após ultrapassar tolerância',
        ],
        'valor_km_retorno' => [
            'type' => 'DECIMAL(10,2)',
            'null' => true,
            'default' => '0.00',
            'comment' => 'Valor por km caso veículo seja entregue em outro local',
        ],
        'valor_condutor_adicional' => [
            'type' => 'DECIMAL(10,2)',
            'null' => true,
            'default' => '0.00',
            'comment' => 'Valor cobrado por cada condutor adicional',
        ],
        'usar_tabela_diarias' => [
            'type' => 'TINYINT(1)',
            'null' => false,
            'default' => '0',
            'comment' => 'Usar tabela de preços progressiva para plano Diária',
        ],
        'tabela_diarias' => [
            'type' => 'MEDIUMTEXT',
            'null' => true,
            'comment' => 'JSON com tabela de preços progressiva (plano Diária)',
        ],
        'usar_tabela_km_controlado' => [
            'type' => 'TINYINT(1)',
            'null' => false,
            'default' => '0',
            'comment' => 'Usar tabela de preços progressiva para plano Km Controlado',
        ],
        'tabela_km_controlado' => [
            'type' => 'MEDIUMTEXT',
            'null' => true,
            'comment' => 'JSON com tabela de preços progressiva (plano Km Controlado)',
        ],
        'usar_tabela_km_livre' => [
            'type' => 'TINYINT(1)',
            'null' => false,
            'default' => '0',
            'comment' => 'Usar tabela de preços progressiva para plano Km Livre',
        ],
        'tabela_km_livre' => [
            'type' => 'MEDIUMTEXT',
            'null' => true,
            'comment' => 'JSON com tabela de preços progressiva (plano Km Livre)',
        ],
        'visivel_no_site' => [
            'type' => 'TINYINT(1)',
            'null' => false,
            'default' => '1',
            'comment' => 'Exibir grupo no site (1=Sim, 0=Não)',
        ],
    ];

    public function up(): void
    {
        foreach ($this->columns as $column => $config) {
            if (!$this->columnExists('grupos', $column)) {
                continue;
            }

            $this->addColumnComment('grupos', $column, $config);
        }
    }

    public function down(): void
    {
        // Remover comentários (definir como vazio)
        foreach ($this->columns as $column => $config) {
            if (!$this->columnExists('grupos', $column)) {
                continue;
            }

            $config['comment'] = '';
            $this->addColumnComment('grupos', $column, $config);
        }
    }

    /**
     * Adiciona comentário a uma coluna preservando seu tipo e propriedades
     */
    private function addColumnComment(string $table, string $column, array $config): void
    {
        $type = $config['type'];
        $null = ($config['null'] ?? true) ? 'NULL' : 'NOT NULL';
        $default = '';
        $comment = $config['comment'] ?? '';

        if (isset($config['default'])) {
            if (is_numeric($config['default'])) {
                $default = "DEFAULT {$config['default']}";
            } else {
                $default = "DEFAULT '{$config['default']}'";
            }
        }

        $commentSql = $comment ? "COMMENT '" . addslashes($comment) . "'" : '';

        $sql = "ALTER TABLE `{$table}` MODIFY COLUMN `{$column}` {$type} {$null} {$default} {$commentSql}";

        $this->execute(trim($sql));
    }
};
