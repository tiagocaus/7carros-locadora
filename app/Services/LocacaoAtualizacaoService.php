<?php

namespace App\Services;

use App\Models\Model;
use mysqli;

/**
 * Controla a transacao unica da atualizacao de uma locacao.
 *
 * Todos os Models compartilham a mesma conexao Singleton, portanto alteracoes
 * na locacao, veiculo, taxas, caucao e financeiro participam do mesmo commit.
 */
class LocacaoAtualizacaoService
{
    private mysqli $mysqli;
    private bool $transacaoAtiva = false;

    public function __construct(?mysqli $mysqli = null)
    {
        $this->mysqli = $mysqli ?? Model::sharedMysqli();
    }

    public function iniciar(): void
    {
        if ($this->transacaoAtiva) {
            throw new \LogicException('A transacao da atualizacao da locacao ja foi iniciada');
        }

        $this->mysqli->begin_transaction();
        $this->transacaoAtiva = true;
    }

    public function confirmar(): void
    {
        if (!$this->transacaoAtiva) {
            return;
        }

        $this->mysqli->commit();
        $this->transacaoAtiva = false;
    }

    public function reverter(): void
    {
        if (!$this->transacaoAtiva) {
            return;
        }

        $this->mysqli->rollback();
        $this->transacaoAtiva = false;
    }

    public function estaAtiva(): bool
    {
        return $this->transacaoAtiva;
    }

    public function __destruct()
    {
        if (!$this->transacaoAtiva) {
            return;
        }

        try {
            $this->mysqli->rollback();
        } catch (\Throwable $e) {
            error_log('[LocacaoAtualizacao] Falha ao reverter transacao pendente: ' . $e->getMessage());
        }
    }
}
