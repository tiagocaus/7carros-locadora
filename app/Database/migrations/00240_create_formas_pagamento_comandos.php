<?php

use App\Database\Migration;

/**
 * Migration: Criar tabela formas_pagamento_comandos
 *
 * Tabela independente para comandos de parcelas (antes embutido em formas_pagamento.parcelas).
 * Registros com chave=0 sao padrao do sistema (nao editaveis pelo tenant).
 */
return new class extends Migration
{
    private array $comandosPadrao = [
        ['comando' => '0', 'descricao' => 'Pagamento a vista, sem parcelamento'],
        ['comando' => '15', 'descricao' => 'Pagamento unico para daqui a 15 dias'],
        ['comando' => '1-12', 'descricao' => 'Parcelas mensais de 1 a 12 vezes'],
        ['comando' => '7/14/21/28', 'descricao' => '4 parcelas com prazos estabelecidos (7, 14, 21 e 28 dias)'],
        ['comando' => 'Seg', 'descricao' => 'Vencimento toda Segunda-feira'],
        ['comando' => 'd15', 'descricao' => 'Vencimento todo dia 15 de cada mes'],
        ['comando' => 'w36', 'descricao' => '36 parcelas semanais'],
        ['comando' => 'w36-Seg', 'descricao' => '36 parcelas semanais com vencimento toda Segunda-feira'],
    ];

    public function up(): void
    {
        if (!$this->tableExists('formas_pagamento_comandos')) {
            $this->create('formas_pagamento_comandos', function ($table) {
                $table->id();
                $table->string('chave', 45)->default('0');
                $table->string('comando', 255);
                $table->text('descricao')->nullable();
                $table->string('status', 1)->default('A');
                $table->timestamps();

                $table->index('chave', 'idx_fpc_chave');
                $table->index(['chave', 'status'], 'idx_fpc_chave_status');
            });
        }

        // Seed dos 8 comandos padrao do sistema (chave=0)
        foreach ($this->comandosPadrao as $cmd) {
            $existe = $this->db()
                ->withoutChave()
                ->table('formas_pagamento_comandos')
                ->whereRaw('chave = ? AND comando = ?', ['0', $cmd['comando']])
                ->first();

            if (!$existe) {
                $this->db()
                    ->withoutChave()
                    ->table('formas_pagamento_comandos')
                    ->insert([
                        'chave' => '0',
                        'comando' => $cmd['comando'],
                        'descricao' => $cmd['descricao'],
                        'status' => 'A',
                    ]);
            }
        }
    }

    public function down(): void
    {
        $this->drop('formas_pagamento_comandos');
    }
};
