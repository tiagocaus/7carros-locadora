<?php

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Tabela de IPs bloqueados
        $this->create('security_blocked_ips', function ($table) {
            $table->id();
            $table->string('ip_address', 45);
            $table->string('reason', 255)->nullable();
            $table->timestamp('blocked_until')->nullable();
            $table->boolean('permanent')->default(0);
            $table->timestamps();

            $table->unique('ip_address');
            $table->index('blocked_until');
        });

        // Tabela de rate limiting
        $this->create('security_rate_limits', function ($table) {
            $table->id();
            $table->string('identifier', 255); // IP + user_id + endpoint
            $table->string('ip_address', 45);
            $table->bigInteger('user_id')->unsigned()->nullable();
            $table->string('endpoint', 255);
            $table->integer('hits')->default(1);
            $table->timestamp('window_start');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique('identifier');
            $table->index('ip_address');
            $table->index('expires_at');
            $table->index(['ip_address', 'endpoint']);
        });

        // Tabela de quotas de usuário
        $this->create('security_user_quotas', function ($table) {
            $table->id();
            $table->bigInteger('user_id')->unsigned();
            $table->string('chave', 45);
            $table->integer('records_accessed')->default(0);
            $table->integer('exports_count')->default(0);
            $table->date('quota_date');
            $table->timestamps();

            $table->unique(['user_id', 'quota_date']);
            $table->index(['chave', 'quota_date']);
        });

        // Tabela de logs de segurança
        $this->create('security_logs', function ($table) {
            $table->id();
            $table->enum('event_type', ['rate_limit', 'fingerprint', 'quota', 'honeypot', 'block', 'suspicious']);
            $table->string('ip_address', 45);
            $table->bigInteger('user_id')->unsigned()->nullable();
            $table->string('chave', 45)->nullable();
            $table->string('endpoint', 255);
            $table->json('details')->nullable();
            $table->integer('score')->default(0);
            $table->string('action_taken', 50)->nullable();
            $table->timestamps();

            $table->index('ip_address');
            $table->index(['event_type', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        // Tabela de fingerprints de requisição (para análise de timing)
        $this->create('security_request_fingerprints', function ($table) {
            $table->id();
            $table->string('ip_address', 45);
            $table->bigInteger('user_id')->unsigned()->nullable();
            $table->string('endpoint', 255);
            $table->integer('page_number')->nullable();
            $table->bigInteger('request_time_ms'); // timestamp em milissegundos
            $table->bigInteger('interval_ms')->nullable(); // intervalo desde última requisição
            $table->timestamps();

            $table->index(['ip_address', 'endpoint', 'created_at']);
            $table->index(['user_id', 'endpoint', 'created_at']);
        });
    }

    public function down(): void
    {
        $this->drop('security_request_fingerprints');
        $this->drop('security_logs');
        $this->drop('security_user_quotas');
        $this->drop('security_rate_limits');
        $this->drop('security_blocked_ips');
    }
};
