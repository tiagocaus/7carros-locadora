<?php

use App\Database\Migration;

return new class extends Migration {
    public function up(): void
    {
        if ($this->tableExists('acessorios') && !$this->tableExists('veiculos_acessorios')) {
            $this->renameTable('acessorios', 'veiculos_acessorios');
        }
    }

    public function down(): void
    {
        if ($this->tableExists('veiculos_acessorios') && !$this->tableExists('acessorios')) {
            $this->renameTable('veiculos_acessorios', 'acessorios');
        }
    }
};
