<?php

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->modifyColumn('formas_pagamento_comandos', 'comando', 'VARCHAR(500)', ['null' => false]);
    }

    public function down(): void
    {
        $comandoLongo = $this->db()
            ->withoutChave()
            ->table('formas_pagamento_comandos')
            ->select(['id'])
            ->whereRaw('CHAR_LENGTH(comando) > 255')
            ->first();

        if ($comandoLongo !== null) {
            throw new RuntimeException(
                'Rollback bloqueado: existem comandos com mais de 255 caracteres. Nenhum comando foi truncado.'
            );
        }

        $this->modifyColumn('formas_pagamento_comandos', 'comando', 'VARCHAR(255)', ['null' => false]);
    }
};
