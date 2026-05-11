<?php

use App\Database\Migration;

/**
 * Migration: Migrar contatos de clientes para as novas tabelas
 *
 * Converte os campos diretos (email, tel_cel, tel_com, tel_residenc) para as
 * novas tabelas normalizadas (contatos_emails, contatos_telefones).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Buscar todos os clientes (sem filtro multi-tenant; carrega chave para propagar)
        $clientes = $this->db()->table('clientes')->withoutChave()->select(['id', 'chave', 'email', 'tel_cel', 'tel_com', 'tel_residenc'])->get();

        foreach ($clientes as $c) {
            // Migrar email (se existir)
            if (!empty(trim($c['email'] ?? ''))) {
                $this->db()->table('contatos_emails')->insert([
                    'chave' => $c['chave'],
                    'entidade_tipo' => 'cliente',
                    'entidade_id' => $c['id'],
                    'email' => trim($c['email']),
                    'descricao' => 'Principal',
                    'principal' => 'S',
                ]);
            }

            // Migrar celular (se existir) - prioridade como principal, assume WhatsApp
            $temCelular = !empty(trim($c['tel_cel'] ?? ''));
            $temComercial = !empty(trim($c['tel_com'] ?? ''));
            $temResidencial = !empty(trim($c['tel_residenc'] ?? ''));

            if ($temCelular) {
                $this->db()->table('contatos_telefones')->insert([
                    'chave' => $c['chave'],
                    'entidade_tipo' => 'cliente',
                    'entidade_id' => $c['id'],
                    'telefone' => trim($c['tel_cel']),
                    'descricao' => 'Celular',
                    'whatsapp' => 'S',
                    'telegram' => 'N',
                    'sms' => 'S',
                    'principal' => 'S',
                ]);
            }

            // Migrar telefone comercial (se existir)
            if ($temComercial) {
                $isPrincipal = !$temCelular ? 'S' : 'N';
                $this->db()->table('contatos_telefones')->insert([
                    'chave' => $c['chave'],
                    'entidade_tipo' => 'cliente',
                    'entidade_id' => $c['id'],
                    'telefone' => trim($c['tel_com']),
                    'descricao' => 'Comercial',
                    'whatsapp' => 'N',
                    'telegram' => 'N',
                    'sms' => 'N',
                    'principal' => $isPrincipal,
                ]);
            }

            // Migrar telefone residencial (se existir)
            if ($temResidencial) {
                $isPrincipal = (!$temCelular && !$temComercial) ? 'S' : 'N';
                $this->db()->table('contatos_telefones')->insert([
                    'chave' => $c['chave'],
                    'entidade_tipo' => 'cliente',
                    'entidade_id' => $c['id'],
                    'telefone' => trim($c['tel_residenc']),
                    'descricao' => 'Residencial',
                    'whatsapp' => 'N',
                    'telegram' => 'N',
                    'sms' => 'N',
                    'principal' => $isPrincipal,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Remover contatos migrados de clientes
        $this->execute("DELETE FROM contatos_emails WHERE entidade_tipo = 'cliente'");
        $this->execute("DELETE FROM contatos_telefones WHERE entidade_tipo = 'cliente'");
    }
};
