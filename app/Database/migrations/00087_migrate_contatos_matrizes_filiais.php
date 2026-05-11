<?php

use App\Database\Migration;

/**
 * Migration: Migrar contatos de matrizes_filiais para as novas tabelas
 *
 * Converte os campos diretos (email, fixo, celular) para as novas tabelas
 * normalizadas (contatos_emails, contatos_telefones).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Buscar todas as matrizes/filiais (sem filtro multi-tenant; carrega chave para propagar)
        $matrizes = $this->db()->table('matrizes_filiais')->withoutChave()->select(['id', 'chave', 'email', 'fixo', 'celular'])->get();

        foreach ($matrizes as $m) {
            // Migrar email (se existir)
            if (!empty(trim($m['email'] ?? ''))) {
                $this->db()->table('contatos_emails')->insert([
                    'chave' => $m['chave'],
                    'entidade_tipo' => 'matriz_filial',
                    'entidade_id' => $m['id'],
                    'email' => trim($m['email']),
                    'descricao' => 'Principal',
                    'principal' => 'S',
                ]);
            }

            // Migrar telefone fixo (se existir) - será o principal se não houver celular
            $temCelular = !empty(trim($m['celular'] ?? ''));
            if (!empty(trim($m['fixo'] ?? ''))) {
                $this->db()->table('contatos_telefones')->insert([
                    'chave' => $m['chave'],
                    'entidade_tipo' => 'matriz_filial',
                    'entidade_id' => $m['id'],
                    'telefone' => trim($m['fixo']),
                    'descricao' => 'Fixo',
                    'whatsapp' => 'N',
                    'telegram' => 'N',
                    'sms' => 'N',
                    'principal' => $temCelular ? 'N' : 'S',
                ]);
            }

            // Migrar celular (se existir) - será o principal e assumimos que tem WhatsApp
            if ($temCelular) {
                $this->db()->table('contatos_telefones')->insert([
                    'chave' => $m['chave'],
                    'entidade_tipo' => 'matriz_filial',
                    'entidade_id' => $m['id'],
                    'telefone' => trim($m['celular']),
                    'descricao' => 'Celular',
                    'whatsapp' => 'S',
                    'telegram' => 'N',
                    'sms' => 'S',
                    'principal' => 'S',
                ]);
            }
        }
    }

    public function down(): void
    {
        // Remover contatos migrados de matrizes_filiais
        $this->execute("DELETE FROM contatos_emails WHERE entidade_tipo = 'matriz_filial'");
        $this->execute("DELETE FROM contatos_telefones WHERE entidade_tipo = 'matriz_filial'");
    }
};
