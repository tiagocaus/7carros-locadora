<?php

use App\Core\Cache;
use App\Database\Migration;

/**
 * Orçamentos comerciais de locações de curta duração.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'orcamentos.visualizar' => ['Visualizar orçamentos', 'Visualizar orçamentos comerciais'],
        'orcamentos.criar' => ['Criar orçamentos', 'Criar novos orçamentos comerciais'],
        'orcamentos.editar' => ['Editar orçamentos', 'Editar e alterar o status de orçamentos'],
        'orcamentos.converter' => ['Converter orçamentos', 'Converter orçamentos em reservas'],
        'orcamentos.imprimir' => ['Imprimir orçamentos', 'Gerar o PDF de orçamentos'],
    ];

    public function up(): void
    {
        if (!$this->tableExists('orcamentos')) {
            $this->create('orcamentos', function ($table) {
                $table->id();
                $table->string('chave', 45);
                $table->string('codigo', 15);
                $table->addColumn("`status` CHAR(1) NOT NULL DEFAULT 'R'");
                $table->date('validade');
                $table->string('origem', 30)->nullable();
                $table->integer('id_cliente')->unsigned();
                $table->string('cliente_nome');
                $table->integer('id_matriz_filial_retirada')->unsigned();
                $table->integer('id_matriz_filial_devolucao')->unsigned();
                $table->integer('id_funcionario')->unsigned()->nullable();
                $table->datetime('data_saida');
                $table->datetime('data_prevista');
                $table->integer('dias')->unsigned();
                $table->integer('id_grupo')->unsigned();
                $table->string('grupo_nome', 100);
                $table->integer('id_veiculo')->unsigned()->nullable();
                $table->string('plano', 3);
                $table->decimal('diaria_valor', 10, 2)->default(0);
                $table->integer('km_franquia')->unsigned()->nullable();
                $table->decimal('valor_km_excedente', 10, 2)->default(0);
                $table->boolean('seguro_carro')->default(0);
                $table->decimal('valor_seguro_carro', 10, 2)->default(0);
                $table->boolean('seguro_terceiros')->default(0);
                $table->decimal('valor_seguro_terceiros', 10, 2)->default(0);
                $table->integer('id_conta')->unsigned()->nullable();
                $table->integer('id_forma_pagamento')->unsigned()->nullable();
                $table->string('condicao_pagamento', 150)->nullable();
                $table->string('promocao_codigo', 50)->nullable();
                $table->decimal('valor_desconto', 10, 2)->default(0);
                $table->addColumn('`taxas` MEDIUMTEXT NULL');
                $table->addColumn('`observacoes_cliente` MEDIUMTEXT NULL');
                $table->addColumn('`observacoes_internas` MEDIUMTEXT NULL');
                $table->decimal('subtotal_diarias', 10, 2)->default(0);
                $table->decimal('subtotal_adicionais', 10, 2)->default(0);
                $table->decimal('total_fatura', 10, 2)->default(0);
                $table->decimal('total_pagar', 10, 2)->default(0);
                $table->integer('id_locacao_convertida')->unsigned()->nullable();
                $table->datetime('enviado_at')->nullable();
                $table->datetime('convertido_at')->nullable();
                $table->timestamp('created_at')->default('CURRENT_TIMESTAMP');
                $table->addColumn('`updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP');

                $table->unique('codigo', 'uniq_orcamentos_codigo');
                $table->unique('id_locacao_convertida', 'uniq_orcamentos_locacao_convertida');
                $table->index(['chave', 'status'], 'idx_orcamentos_chave_status');
                $table->index(['chave', 'validade'], 'idx_orcamentos_chave_validade');
                $table->index(['chave', 'id_cliente'], 'idx_orcamentos_chave_cliente');
                $table->index(['chave', 'id_matriz_filial_retirada'], 'idx_orcamentos_chave_filial');

                $table->foreign('id_cliente')->references('id')->on('clientes')->restrictOnDelete();
                $table->foreign('id_matriz_filial_retirada')->references('id')->on('matrizes_filiais')->restrictOnDelete();
                $table->foreign('id_matriz_filial_devolucao')->references('id')->on('matrizes_filiais')->restrictOnDelete();
                $table->foreign('id_funcionario')->references('id')->on('funcionarios')->nullOnDelete();
                $table->foreign('id_grupo')->references('id')->on('grupos')->restrictOnDelete();
                $table->foreign('id_veiculo')->references('id')->on('veiculos')->nullOnDelete();
                $table->foreign('id_conta')->references('id')->on('contas_bancarias')->nullOnDelete();
                $table->foreign('id_forma_pagamento')->references('id')->on('formas_pagamento')->nullOnDelete();
                $table->foreign('id_locacao_convertida')->references('id')->on('locacoes')->nullOnDelete();
            });
        }

        foreach (self::PERMISSIONS as $key => [$name, $description]) {
            $permission = $this->db()->table('permissions')->select(['id'])->whereRaw('`key` = ?', [$key])->first();
            $permissionId = $permission
                ? (int) $permission['id']
                : $this->db()->table('permissions')->insert([
                    'key' => $key,
                    'name' => $name,
                    'description' => $description,
                    'module' => 'orcamentos',
                ]);

            $roles = $this->db()->table('funcionarios_roles')->select(['id'])->whereIn('name', ['Proprietário', 'Gerente'])->get();
            foreach ($roles as $role) {
                $exists = $this->db()->table('funcionarios_role_permissions')
                    ->whereRaw('role_id = ? AND permission_id = ?', [(int) $role['id'], $permissionId])
                    ->exists();
                if (!$exists) {
                    $this->db()->table('funcionarios_role_permissions')->insert([
                        'role_id' => (int) $role['id'],
                        'permission_id' => $permissionId,
                    ]);
                }
            }
        }

        try { Cache::flush(); } catch (\Throwable) {}
    }

    public function down(): void
    {
        foreach (array_keys(self::PERMISSIONS) as $key) {
            $permission = $this->db()->table('permissions')->select(['id'])->whereRaw('`key` = ?', [$key])->first();
            if ($permission) {
                $this->db()->table('funcionarios_role_permissions')->where('permission_id', '=', (int) $permission['id'])->delete();
                $this->db()->table('permissions')->where('id', '=', (int) $permission['id'])->delete();
            }
        }
        $this->drop('orcamentos');
        try { Cache::flush(); } catch (\Throwable) {}
    }
};
