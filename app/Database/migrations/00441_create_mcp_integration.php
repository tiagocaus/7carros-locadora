<?php

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Approved applications are global authentication configuration, not tenant data.
        if (!$this->tableExists('mcp_clients')) {
            $this->create('mcp_clients', function ($t) {
                $t->id();
                $t->string('client_id', 255);
                $t->string('name', 150);
                $t->text('redirect_uris');
                $t->string('secret_hash', 255)->nullable();
                $t->boolean('enabled')->default(0);
                $t->boolean('homologated')->default(0);
                $t->text('metadata')->nullable();
                $t->timestamps();
                $t->unique('client_id');
            });
        }
        if (!$this->tableExists('mcp_connections')) {
            $this->create('mcp_connections', function ($t) {
                $t->id();
                $t->string('chave', 45);
                $t->string('connection_id', 64);
                $t->string('client_id', 255);
                $t->integer('employee_id')->unsigned();
                $t->text('scopes');
                $t->datetime('expires_at');
                $t->datetime('revoked_at')->nullable();
                $t->datetime('last_used_at')->nullable();
                $t->timestamps();
                $t->unique('connection_id');
                $t->index(['chave', 'employee_id']);
                $t->index(['chave', 'client_id']);
            });
        }
        if (!$this->tableExists('mcp_credentials')) {
            $this->create('mcp_credentials', function ($t) {
                $t->id();
                $t->string('chave', 45);
                $t->string('connection_id', 64);
                $t->string('token_hash', 64);
                $t->string('kind', 10);
                $t->datetime('expires_at');
                $t->datetime('revoked_at')->nullable();
                $t->timestamps();
                $t->unique(['kind', 'token_hash']);
                $t->index(['chave', 'connection_id']);
            });
        }
        if (!$this->tableExists('mcp_audit')) {
            $this->create('mcp_audit', function ($t) {
                $t->id();
                $t->string('chave', 45);
                $t->integer('employee_id')->unsigned();
                $t->string('connection_id', 64);
                $t->string('client_id', 255);
                $t->string('request_id', 32);
                $t->string('event', 80);
                $t->string('result', 30);
                $t->integer('records')->unsigned()->default(0);
                $t->integer('duration_ms')->unsigned()->default(0);
                $t->timestamps();
                $t->index(['chave', 'created_at']);
            });
        }
        foreach (['conectar' => 'Conectar aplicativos de IA', 'administrar' => 'Administrar conexões de IA',
            'documentos' => 'Liberar documentos ao aplicativo de IA', 'contatos' => 'Liberar contatos ao aplicativo de IA'] as $key => $name) {
            if (!$this->db()->table('permissions')->where('`key`', '=', 'mcp.' . $key)->exists()) {
                $this->db()->table('permissions')->insert(['key' => 'mcp.' . $key, 'name' => $name, 'module' => 'mcp']);
            }
        }
        // Deliberately no automatic grants: administrators explicitly authorize roles.
    }

    public function down(): void
    {
        $ids = array_column($this->db()->table('permissions')->where('module', '=', 'mcp')->get(), 'id');
        if ($ids) {
            $this->db()->table('funcionarios_role_permissions')->whereIn('permission_id', $ids)->delete();
            $this->db()->table('permissions')->whereIn('id', $ids)->delete();
        }
        foreach (['mcp_audit', 'mcp_credentials', 'mcp_connections', 'mcp_clients'] as $table) {
            $this->drop($table);
        }
    }
};
