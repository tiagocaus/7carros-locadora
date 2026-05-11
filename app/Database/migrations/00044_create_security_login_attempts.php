<?php

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->create('security_login_attempts', function ($table) {
            $table->id();
            $table->string('usuario', 100);
            $table->string('ip_address', 45);
            $table->integer('tentativas')->default(1);
            $table->timestamp('bloqueado_ate')->nullable();
            $table->timestamps();

            $table->index(['usuario', 'ip_address']);
            $table->index('bloqueado_ate');
        });
    }

    public function down(): void
    {
        $this->drop('security_login_attempts');
    }
};
