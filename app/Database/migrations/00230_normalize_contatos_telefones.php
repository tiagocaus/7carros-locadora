<?php

use App\Database\Migration;

/**
 * Migration: Normalizar telefones na tabela contatos_telefones
 *
 * - Para números com +: preserva código do país, remove só formatação
 * - Para números sem +: detecta se é brasileiro e adiciona +55 se necessário
 */
return new class extends Migration
{
    public function up(): void
    {
        $telefones = $this->db()
            ->withoutChave()
            ->table('contatos_telefones')
            ->select(['id', 'telefone'])
            ->whereRaw('telefone IS NOT NULL AND telefone != ?', [''])
            ->get();

        foreach ($telefones as $tel) {
            $novoTelefone = $this->normalizarTelefone($tel['telefone']);

            if ($novoTelefone !== $tel['telefone'] && $novoTelefone !== '') {
                $this->db()
                    ->withoutChave()
                    ->table('contatos_telefones')
                    ->whereRaw('id = ?', [$tel['id']])
                    ->update(['telefone' => $novoTelefone]);
            }
        }
    }

    public function down(): void
    {
        // Não é possível reverter - formatação original perdida
    }

    private function normalizarTelefone(string $telefone): string
    {
        $telefone = trim($telefone);

        if ($telefone === '') {
            return '';
        }

        // Se já começa com +, preservar o código do país
        if (str_starts_with($telefone, '+')) {
            // Remove tudo exceto números e mantém +
            $numeros = preg_replace('/[^0-9]/', '', $telefone);
            return '+' . $numeros;
        }

        // Não começa com + - remover tudo e detectar país
        $numeros = preg_replace('/[^0-9]/', '', $telefone);

        if ($numeros === '') {
            return '';
        }

        $tamanho = strlen($numeros);

        // 10 ou 11 dígitos = brasileiro sem código do país
        if ($tamanho === 10 || $tamanho === 11) {
            return '+55' . $numeros;
        }

        // 12 ou 13 dígitos começando com 55 = brasileiro com código
        if (($tamanho === 12 || $tamanho === 13) && str_starts_with($numeros, '55')) {
            return '+' . $numeros;
        }

        // Outros casos: assume que já tem código do país
        return '+' . $numeros;
    }
};
