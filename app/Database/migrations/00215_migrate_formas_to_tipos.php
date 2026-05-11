<?php

use App\Database\Migration;

/**
 * Migration: Migrar formas de pagamento para tipos
 *
 * Vincula as formas de pagamento existentes aos tipos corretos
 * baseado em keywords no campo nome.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Buscar todas as formas de pagamento sem tipo
        $formas = $this->db()
            ->withoutChave()
            ->table('formas_pagamento')
            ->whereRaw('id_tipo_pagamento IS NULL')
            ->get();

        foreach ($formas as $forma) {
            $nomeLower = mb_strtolower($forma['nome']);
            $chave = $forma['chave'];
            $tipoNome = $this->inferirTipo($nomeLower);

            // Buscar o id do tipo
            $tipo = $this->db()
                ->withoutChave()
                ->table('tipos_pagamento')
                ->whereRaw('chave = ? AND nome = ?', [$chave, $tipoNome])
                ->first();

            if ($tipo) {
                $this->db()
                    ->withoutChave()
                    ->table('formas_pagamento')
                    ->whereRaw('id = ?', [$forma['id']])
                    ->update(['id_tipo_pagamento' => $tipo['id']]);
            }
        }
    }

    public function down(): void
    {
        // Limpar a coluna id_tipo_pagamento
        $this->db()
            ->withoutChave()
            ->table('formas_pagamento')
            ->update(['id_tipo_pagamento' => null]);
    }

    /**
     * Infere o tipo de pagamento baseado no nome
     */
    private function inferirTipo(string $nomeLower): string
    {
        // Ordem importa: verificar mais especifico primeiro
        if (str_contains($nomeLower, 'pix')) {
            return 'PIX';
        }

        if (str_contains($nomeLower, 'boleto')) {
            return 'Boleto';
        }

        if (str_contains($nomeLower, 'credito') || str_contains($nomeLower, 'crédito')) {
            return 'Cartao de Credito';
        }

        if (str_contains($nomeLower, 'debito') || str_contains($nomeLower, 'débito')) {
            return 'Cartao de Debito';
        }

        if (str_contains($nomeLower, 'cartao') || str_contains($nomeLower, 'cartão')) {
            // Cartao generico = credito por padrao
            return 'Cartao de Credito';
        }

        if (str_contains($nomeLower, 'dinheiro') || str_contains($nomeLower, 'especie') || str_contains($nomeLower, 'espécie')) {
            return 'Dinheiro';
        }

        if (str_contains($nomeLower, 'transfer') || str_contains($nomeLower, 'ted') || str_contains($nomeLower, 'doc')) {
            return 'Transferencia';
        }

        if (str_contains($nomeLower, 'cheque')) {
            return 'Cheque';
        }

        return 'Outros';
    }
};
