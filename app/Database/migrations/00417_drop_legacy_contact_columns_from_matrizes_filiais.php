<?php

use App\Database\Migration;

/**
 * Remove os campos de contato desnormalizados de matrizes_filiais.
 *
 * A migration 00416 preserva previamente os registros que ainda existiam
 * apenas nessas colunas. Depois desta migration, contatos_emails e
 * contatos_telefones sao as unicas fontes de verdade.
 */
return new class extends Migration
{
    private const BACKFILL_MIGRATION = '00416_backfill_legacy_matriz_filial_contacts.php';

    public function up(): void
    {
        $backfillExecutado = $this->db()
            ->table('migrations')
            ->withoutChave()
            ->where('migration', '=', self::BACKFILL_MIGRATION)
            ->exists();

        if (!$backfillExecutado) {
            throw new \RuntimeException('Execute a migration 00416 antes de remover os contatos legados de matrizes_filiais.');
        }

        $this->validarContatosLegadosPreservados();

        foreach (['fixo', 'celular', 'email'] as $column) {
            $this->dropColumnIfExists('matrizes_filiais', $column);
        }
    }

    public function down(): void
    {
        $this->addColumnIfNotExists('matrizes_filiais', 'fixo', 'VARCHAR(20)', [
            'null' => true,
            'after' => 'pais',
        ]);
        $this->addColumnIfNotExists('matrizes_filiais', 'celular', 'VARCHAR(20)', [
            'null' => true,
            'after' => 'fixo',
        ]);
        $this->addColumnIfNotExists('matrizes_filiais', 'email', 'VARCHAR(100)', [
            'null' => true,
            'after' => 'celular',
        ]);

        $filiais = $this->db()
            ->table('matrizes_filiais')
            ->withoutChave()
            ->select(['id', 'chave'])
            ->get();

        foreach ($filiais as $filial) {
            $id = (int) $filial['id'];
            $chave = (string) $filial['chave'];

            $email = $this->db()
                ->table('contatos_emails')
                ->withChave($chave)
                ->select(['email'])
                ->where('entidade_tipo', '=', 'matriz_filial')
                ->where('entidade_id', '=', $id)
                ->where('principal', '=', 'S')
                ->orderBy('id', 'ASC')
                ->first();

            $telefones = $this->db()
                ->table('contatos_telefones')
                ->withChave($chave)
                ->select(['telefone', 'principal', 'whatsapp'])
                ->where('entidade_tipo', '=', 'matriz_filial')
                ->where('entidade_id', '=', $id)
                ->orderByDesc('principal')
                ->orderBy('id', 'ASC')
                ->get();

            $celular = '';
            $fixo = '';
            $principal = '';
            foreach ($telefones as $telefone) {
                $numero = trim((string) ($telefone['telefone'] ?? ''));
                if ($numero === '') {
                    continue;
                }
                if ($principal === '' && ($telefone['principal'] ?? 'N') === 'S') {
                    $principal = $numero;
                }
                if ($celular === '' && ($telefone['whatsapp'] ?? 'N') === 'S') {
                    $celular = $numero;
                }
                if ($fixo === '' && ($telefone['whatsapp'] ?? 'N') !== 'S') {
                    $fixo = $numero;
                }
            }

            if ($fixo === '' && $principal !== '' && $principal !== $celular) {
                $fixo = $principal;
            }

            $this->db()
                ->table('matrizes_filiais')
                ->withChave($chave)
                ->where('id', '=', $id)
                ->update([
                    'fixo' => $fixo !== '' ? $fixo : null,
                    'celular' => $celular !== '' ? $celular : null,
                    'email' => !empty($email['email']) ? (string) $email['email'] : null,
                ]);
        }
    }

    private function validarContatosLegadosPreservados(): void
    {
        $filiais = $this->db()
            ->table('matrizes_filiais')
            ->withoutChave()
            ->select(['id', 'chave', 'email', 'fixo', 'celular'])
            ->get();

        foreach ($filiais as $filial) {
            $id = (int) $filial['id'];
            $chave = (string) $filial['chave'];
            $emailLegado = trim((string) ($filial['email'] ?? ''));
            $fixoLegado = trim((string) ($filial['fixo'] ?? ''));
            $celularLegado = trim((string) ($filial['celular'] ?? ''));

            if ($emailLegado !== '') {
                $emailPreservado = $this->db()
                    ->table('contatos_emails')
                    ->withChave($chave)
                    ->where('entidade_tipo', '=', 'matriz_filial')
                    ->where('entidade_id', '=', $id)
                    ->exists();

                if (!$emailPreservado) {
                    throw new \RuntimeException("A matriz/filial {$id} possui email legado ainda nao preservado.");
                }
            }

            if ($fixoLegado === '' && $celularLegado === '') {
                continue;
            }

            $telefonePreservado = $this->db()
                ->table('contatos_telefones')
                ->withChave($chave)
                ->where('entidade_tipo', '=', 'matriz_filial')
                ->where('entidade_id', '=', $id)
                ->exists();

            if (!$telefonePreservado) {
                throw new \RuntimeException("A matriz/filial {$id} possui telefone legado ainda nao preservado.");
            }
        }
    }
};
