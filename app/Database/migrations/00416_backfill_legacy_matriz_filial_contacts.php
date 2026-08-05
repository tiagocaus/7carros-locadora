<?php

use App\Database\Migration;

/**
 * Preserva os poucos contatos que ainda existem somente nas colunas legadas
 * antes de o runtime deixar de consulta-las.
 */
return new class extends Migration
{
    private const DESCRICAO = 'Importado do legado 00416';

    public function up(): void
    {
        $filiais = $this->db()
            ->table('matrizes_filiais')
            ->withoutChave()
            ->select(['id', 'chave', 'email', 'fixo', 'celular'])
            ->get();

        foreach ($filiais as $filial) {
            $id = (int) $filial['id'];
            $chave = (string) $filial['chave'];

            $temEmailNormalizado = $this->db()
                ->table('contatos_emails')
                ->withChave($chave)
                ->select(['id'])
                ->where('entidade_tipo', '=', 'matriz_filial')
                ->where('entidade_id', '=', $id)
                ->first() !== null;

            $emailLegado = trim((string) ($filial['email'] ?? ''));
            if (!$temEmailNormalizado && $emailLegado !== '') {
                $this->db()
                    ->table('contatos_emails')
                    ->withoutChave()
                    ->insert([
                        'chave' => $chave,
                        'entidade_tipo' => 'matriz_filial',
                        'entidade_id' => $id,
                        'email' => $emailLegado,
                        'descricao' => self::DESCRICAO,
                        'principal' => 'S',
                        'recebe_email' => 'S',
                    ]);
            }

            $temTelefoneNormalizado = $this->db()
                ->table('contatos_telefones')
                ->withChave($chave)
                ->select(['id'])
                ->where('entidade_tipo', '=', 'matriz_filial')
                ->where('entidade_id', '=', $id)
                ->first() !== null;

            if ($temTelefoneNormalizado) {
                continue;
            }

            $fixoLegado = trim((string) ($filial['fixo'] ?? ''));
            $celularLegado = trim((string) ($filial['celular'] ?? ''));

            if ($fixoLegado !== '') {
                $this->db()
                    ->table('contatos_telefones')
                    ->withoutChave()
                    ->insert([
                        'chave' => $chave,
                        'entidade_tipo' => 'matriz_filial',
                        'entidade_id' => $id,
                        'telefone' => $fixoLegado,
                        'descricao' => self::DESCRICAO,
                        'whatsapp' => 'N',
                        'telegram' => 'N',
                        'sms' => 'N',
                        'principal' => $celularLegado === '' ? 'S' : 'N',
                    ]);
            }

            if ($celularLegado !== '') {
                $this->db()
                    ->table('contatos_telefones')
                    ->withoutChave()
                    ->insert([
                        'chave' => $chave,
                        'entidade_tipo' => 'matriz_filial',
                        'entidade_id' => $id,
                        'telefone' => $celularLegado,
                        'descricao' => self::DESCRICAO,
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
        $this->db()
            ->table('contatos_emails')
            ->withoutChave()
            ->where('entidade_tipo', '=', 'matriz_filial')
            ->where('descricao', '=', self::DESCRICAO)
            ->delete();

        $this->db()
            ->table('contatos_telefones')
            ->withoutChave()
            ->where('entidade_tipo', '=', 'matriz_filial')
            ->where('descricao', '=', self::DESCRICAO)
            ->delete();
    }
};
