<?php

use App\Database\Migration;

/**
 * Estrutura de autenticacao, auditoria e indicacoes do portal publico.
 */
return new class extends Migration
{
    private const PERMISSION_KEY = 'notificacoes.alteracoes_portal';

    public function up(): void
    {
        if (!$this->columnExists('fornecedores', 'senha')) {
            $this->table('fornecedores', function ($table) {
                $table->string('senha', 255)->nullable()->after('email');
            });
        }

        if (!$this->tableExists('portal_sessions')) {
            $this->create('portal_sessions', function ($table) {
                $table->id();
                $table->string('chave', 45);
                $table->enum('perfil', ['cliente', 'investidor']);
                $table->bigInteger('entidade_id')->unsigned();
                $table->string('token_hash', 64);
                $table->timestamp('last_activity_at');
                $table->timestamp('expires_at');
                $table->timestamp('revoked_at')->nullable();
                $table->string('ip', 45)->nullable();
                $table->string('user_agent_hash', 64)->nullable();
                $table->timestamps();

                $table->unique('token_hash', 'uniq_portal_sessions_token');
                $table->index(['chave', 'perfil', 'entidade_id'], 'idx_portal_sessions_entidade');
                $table->index(['chave', 'expires_at'], 'idx_portal_sessions_expira');
            });
        }

        if (!$this->tableExists('fornecedor_password_resets')) {
            $this->create('fornecedor_password_resets', function ($table) {
                $table->id();
                $table->string('chave', 45);
                $table->bigInteger('id_fornecedor')->unsigned();
                $table->string('token_hash', 64);
                $table->timestamp('expires_at');
                $table->timestamp('used_at')->nullable();
                $table->string('request_ip', 45)->nullable();
                $table->timestamps();

                $table->unique('token_hash', 'uniq_fornecedor_reset_token');
                $table->index(['chave', 'id_fornecedor'], 'idx_fornecedor_reset_entidade');
                $table->index(['chave', 'expires_at'], 'idx_fornecedor_reset_expira');
            });
        }

        if (!$this->tableExists('portal_audit_logs')) {
            $this->create('portal_audit_logs', function ($table) {
                $table->id();
                $table->string('chave', 45);
                $table->enum('perfil', ['cliente', 'investidor']);
                $table->bigInteger('entidade_id')->unsigned();
                $table->string('acao', 100);
                $table->json('campos_alterados')->nullable();
                $table->string('ip', 45)->nullable();
                $table->string('user_agent_hash', 64)->nullable();
                $table->timestamp('created_at')->default('CURRENT_TIMESTAMP');

                $table->index(['chave', 'perfil', 'entidade_id'], 'idx_portal_audit_entidade');
                $table->index(['chave', 'created_at'], 'idx_portal_audit_data');
            });
        }

        if (!$this->tableExists('portal_indicacao_codigos')) {
            $this->create('portal_indicacao_codigos', function ($table) {
                $table->id();
                $table->string('chave', 45);
                $table->bigInteger('id_cliente')->unsigned();
                $table->string('codigo', 24);
                $table->timestamps();

                $table->unique(['chave', 'id_cliente'], 'uniq_portal_indicacao_cliente');
                $table->unique(['chave', 'codigo'], 'uniq_portal_indicacao_codigo');
            });
        }

        if (!$this->tableExists('portal_indicacao_eventos')) {
            $this->create('portal_indicacao_eventos', function ($table) {
                $table->id();
                $table->string('chave', 45);
                $table->bigInteger('id_codigo')->unsigned();
                $table->enum('tipo', ['clique', 'conversao']);
                $table->string('visitante_hash', 64)->nullable();
                $table->bigInteger('id_cliente_indicado')->unsigned()->nullable();
                $table->bigInteger('id_locacao')->unsigned()->nullable();
                $table->timestamp('created_at')->default('CURRENT_TIMESTAMP');

                $table->index(['chave', 'id_codigo', 'tipo'], 'idx_portal_indicacao_evento');
                $table->index(['chave', 'created_at'], 'idx_portal_indicacao_data');
            });
        }

        $this->addIndexIfNotExists(
            'fornecedores',
            ['chave', 'investidor', 'cpf_cnpj'],
            'idx_fornecedores_portal_documento'
        );
        $this->addIndexIfNotExists(
            'fornecedores',
            ['chave', 'investidor', 'email'],
            'idx_fornecedores_portal_email'
        );
        $this->addIndexIfNotExists(
            'veiculos',
            ['chave', 'id_fornecedor', 'disponibilidade'],
            'idx_veiculos_portal_investidor'
        );
        $this->addIndexIfNotExists(
            'contratos',
            ['chave', 'id_cliente', 'status'],
            'idx_contratos_portal_cliente'
        );
        $this->addIndexIfNotExists(
            'locacoes',
            ['chave', 'id_cliente', 'status'],
            'idx_locacoes_portal_cliente'
        );
        $this->addIndexIfNotExists(
            'multas',
            ['chave', 'id_cliente', 'pago'],
            'idx_multas_portal_cliente'
        );
        $this->addIndexIfNotExists(
            'manutencoes',
            ['chave', 'id_cliente', 'status'],
            'idx_manutencoes_portal_cliente'
        );
        $this->addIndexIfNotExists(
            'comissoes_investidores',
            ['chave', 'id_fornecedor', 'status', 'data_referencia'],
            'idx_comissoes_portal_investidor'
        );

        $permission = $this->db()
            ->table('permissions')
            ->select(['id'])
            ->whereRaw('`key` = ?', [self::PERMISSION_KEY])
            ->first();

        $permissionId = $permission
            ? (int) $permission['id']
            : $this->db()->table('permissions')->insert([
                'key' => self::PERMISSION_KEY,
                'name' => 'Alteracoes pelo Portal',
                'description' => 'Receber por e-mail alteracoes cadastrais feitas no portal',
                'module' => 'notificacoes',
            ]);

        $roles = $this->db()
            ->table('funcionarios_roles')
            ->select(['id'])
            ->whereIn('name', ['Proprietário', 'Gerente'])
            ->get();

        foreach ($roles as $role) {
            $roleId = (int) $role['id'];
            $exists = $this->db()
                ->table('funcionarios_role_permissions')
                ->whereRaw('role_id = ? AND permission_id = ?', [$roleId, $permissionId])
                ->exists();

            if (!$exists) {
                $this->db()->table('funcionarios_role_permissions')->insert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $permission = $this->db()
            ->table('permissions')
            ->select(['id'])
            ->whereRaw('`key` = ?', [self::PERMISSION_KEY])
            ->first();

        if ($permission) {
            $permissionId = (int) $permission['id'];
            $this->db()
                ->table('funcionarios_role_permissions')
                ->where('permission_id', '=', $permissionId)
                ->delete();
            $this->db()
                ->table('permissions')
                ->where('id', '=', $permissionId)
                ->delete();
        }

        $this->dropIndexIfExists('comissoes_investidores', 'idx_comissoes_portal_investidor');
        $this->dropIndexIfExists('manutencoes', 'idx_manutencoes_portal_cliente');
        $this->dropIndexIfExists('multas', 'idx_multas_portal_cliente');
        $this->dropIndexIfExists('locacoes', 'idx_locacoes_portal_cliente');
        $this->dropIndexIfExists('contratos', 'idx_contratos_portal_cliente');
        $this->dropIndexIfExists('veiculos', 'idx_veiculos_portal_investidor');
        $this->dropIndexIfExists('fornecedores', 'idx_fornecedores_portal_email');
        $this->dropIndexIfExists('fornecedores', 'idx_fornecedores_portal_documento');

        $this->drop('portal_indicacao_eventos');
        $this->drop('portal_indicacao_codigos');
        $this->drop('portal_audit_logs');
        $this->drop('fornecedor_password_resets');
        $this->drop('portal_sessions');
        $this->dropColumnIfExists('fornecedores', 'senha');
    }
};
