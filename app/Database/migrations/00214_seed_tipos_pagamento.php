<?php

use App\Database\Migration;

/**
 * Migration: Seed de tipos de pagamento padrao
 *
 * Insere os tipos padrao para cada tenant existente no sistema.
 */
return new class extends Migration
{
    private array $tiposPadrao = [
        ['nome' => 'Dinheiro', 'icone' => 'fa-money-bill', 'ordem' => 1],
        ['nome' => 'PIX', 'icone' => 'fa-qrcode', 'ordem' => 2],
        ['nome' => 'Cartao de Debito', 'icone' => 'fa-credit-card', 'ordem' => 3],
        ['nome' => 'Cartao de Credito', 'icone' => 'fa-credit-card', 'ordem' => 4],
        ['nome' => 'Boleto', 'icone' => 'fa-barcode', 'ordem' => 5],
        ['nome' => 'Transferencia', 'icone' => 'fa-exchange-alt', 'ordem' => 6],
        ['nome' => 'Cheque', 'icone' => 'fa-money-check', 'ordem' => 7],
        ['nome' => 'Outros', 'icone' => 'fa-ellipsis-h', 'ordem' => 99],
    ];

    public function up(): void
    {
        // Buscar todas as chaves unicas de formas_pagamento
        $chaves = $this->db()
            ->withoutChave()
            ->table('formas_pagamento')
            ->selectRaw('DISTINCT chave')
            ->get();

        foreach ($chaves as $row) {
            $chave = $row['chave'];

            foreach ($this->tiposPadrao as $tipo) {
                // Verificar se ja existe
                $existe = $this->db()
                    ->withoutChave()
                    ->table('tipos_pagamento')
                    ->whereRaw('chave = ? AND nome = ?', [$chave, $tipo['nome']])
                    ->first();

                if (!$existe) {
                    $this->db()
                        ->withoutChave()
                        ->table('tipos_pagamento')
                        ->insert([
                            'chave' => $chave,
                            'nome' => $tipo['nome'],
                            'icone' => $tipo['icone'],
                            'ordem' => $tipo['ordem'],
                            'status' => 'A',
                        ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Remove todos os tipos padrao
        foreach ($this->tiposPadrao as $tipo) {
            $this->db()
                ->withoutChave()
                ->table('tipos_pagamento')
                ->whereRaw('nome = ?', [$tipo['nome']])
                ->delete();
        }
    }
};
