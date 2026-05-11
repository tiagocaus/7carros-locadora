<?php

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Verifica se a tabela antiga ainda existe e renomeia
        if ($this->tableExists('login_attempts')) {
            $this->renameTable('login_attempts', 'security_login_attempts');
        }
    }

    public function down(): void
    {
        // Verifica se a tabela nova existe e reverte
        if ($this->tableExists('security_login_attempts')) {
            $this->renameTable('security_login_attempts', 'login_attempts');
        }
    }
};
