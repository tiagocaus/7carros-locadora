<?php

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->create('funcionarios_remember_tokens', function ($table) {
            $table->id();
            $table->string('chave', 100);
            $table->bigInteger('usuario_id')->unsigned();
            $table->string('token', 64);
            $table->timestamp('expira_em');
            $table->timestamps();

            $table->index('token');
            $table->index('chave');
            $table->index('usuario_id');
        });
    }

    public function down(): void
    {
        $this->drop('funcionarios_remember_tokens');
    }
};
