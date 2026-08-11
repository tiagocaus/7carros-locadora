<?php

use App\Database\Migration;

/**
 * Corrige colisao com o plano global de devolucao/reembolso de contrato.
 *
 * A migration 00419 considerava qualquer 3.4.1.23 como existente. Se um
 * tenant ja tivesse usado essa hierarquia, o plano global nao era criado.
 */
return new class extends Migration
{
    private const HIERARQUIA_GLOBAL = '3.4.1.23';
    private const HIERARQUIA_REALOCADA = '3.4.1.24';
    private const DESCRICAO_PT_BR = 'Devolução/Reembolso de contrato';

    public function up(): void
    {
        if (!$this->tableExists('planos_de_contas')) {
            throw new \RuntimeException('Tabela planos_de_contas nao encontrada');
        }

        $qb = $this->db();
        $qb->beginTransaction();

        try {
            $existente = $qb
                ->table('planos_de_contas')
                ->withoutChave()
                ->select(['id', 'chave', 'tipo', 'descricao_i18n'])
                ->where('hierarquia', '=', self::HIERARQUIA_GLOBAL)
                ->lockForUpdate()
                ->first();

            if ($existente && (string) $existente['chave'] === '0') {
                $this->validarPlanoGlobal($existente);
                $qb->commit();
                return;
            }

            if ($existente) {
                $destinoOcupado = $qb
                    ->table('planos_de_contas')
                    ->withoutChave()
                    ->select(['id'])
                    ->where('hierarquia', '=', self::HIERARQUIA_REALOCADA)
                    ->lockForUpdate()
                    ->first();

                if ($destinoOcupado) {
                    throw new \RuntimeException(
                        'Nao foi possivel realocar o plano conflitante: a hierarquia 3.4.1.24 ja esta em uso'
                    );
                }

                $afetados = $qb
                    ->table('planos_de_contas')
                    ->withoutChave()
                    ->where('id', '=', (int) $existente['id'])
                    ->where('hierarquia', '=', self::HIERARQUIA_GLOBAL)
                    ->update(['hierarquia' => self::HIERARQUIA_REALOCADA]);

                if ($afetados !== 1) {
                    throw new \RuntimeException('O plano conflitante nao foi realocado');
                }
            }

            $descricao = json_encode([
                'pt_BR' => self::DESCRICAO_PT_BR,
                'pt_PT' => 'Devolução/Reembolso de contrato',
                'en_US' => 'Contract refund',
                'es_ES' => 'Reembolso de contrato',
                'it_IT' => 'Rimborso contratto',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if ($descricao === false) {
                throw new \RuntimeException('Nao foi possivel serializar a descricao do plano global');
            }

            $qb->table('planos_de_contas')->withoutChave()->insert([
                'chave' => '0',
                'hierarquia' => self::HIERARQUIA_GLOBAL,
                'descricao_i18n' => $descricao,
                'tipo' => 'D',
            ]);

            $qb->commit();
        } catch (\Throwable $e) {
            $qb->rollback();
            throw $e;
        }
    }

    public function down(): void
    {
        // Reparacao de dados intencionalmente irreversivel: remover o plano
        // global ou desfazer a realocacao alteraria classificacoes financeiras.
    }

    private function validarPlanoGlobal(array $plano): void
    {
        $descricao = json_decode((string) ($plano['descricao_i18n'] ?? ''), true);
        $valido = (string) ($plano['tipo'] ?? '') === 'D'
            && is_array($descricao)
            && ($descricao['pt_BR'] ?? null) === self::DESCRICAO_PT_BR;

        if (!$valido) {
            throw new \RuntimeException(
                'A hierarquia global 3.4.1.23 existe, mas nao corresponde ao plano de devolucao de contrato'
            );
        }
    }
};
