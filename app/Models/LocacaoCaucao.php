<?php

namespace App\Models;

use App\Helpers\SequenciaHelper;

/**
 * Model LocacaoCaucao
 *
 * Controla caucoes vinculadas a locacoes e seus lancamentos financeiros.
 */
class LocacaoCaucao extends Model
{
    private const PLANO_CONTA_ENTRADA = '1.1.6.01';
    private const PLANO_CONTA_DEVOLUCAO = '1.1.6.02';

    public function tabelaDisponivel(): bool
    {
        static $disponivel = null;

        if ($disponivel !== null) {
            return $disponivel;
        }

        $result = $this->getMysqli()->query("SHOW TABLES LIKE 'locacoes_caucoes'");
        $disponivel = $result !== false && $result->num_rows > 0;
        if ($result instanceof \mysqli_result) {
            $result->free();
        }

        return $disponivel;
    }

    public function buscarAtualPorLocacao(int $locacaoId): ?array
    {
        if (!$this->tabelaDisponivel()) {
            return null;
        }

        return $this->qb
            ->table('locacoes_caucoes', 'lc')
            ->select([
                'lc.*',
                'cb.nome AS conta_descricao',
                'fp.nome AS forma_pagamento_descricao',
                'cart.bandeira AS cartao_bandeira',
                'cart.ultimos_digitos AS cartao_ultimos_digitos',
            ])
            ->leftJoin('contas_bancarias', 'cb', 'lc.id_conta', '=', 'cb.id')
            ->leftJoin('formas_pagamento', 'fp', 'lc.id_forma_pagamento', '=', 'fp.id')
            ->leftJoin('clientes_cartoes', 'cart', 'lc.id_cartao', '=', 'cart.id')
            ->where('lc.id_locacao', '=', $locacaoId)
            ->where('lc.status', '!=', 'cancelada')
            ->orderByDesc('lc.id')
            ->first();
    }

    public function sincronizarAtual(int $locacaoId, array $dados, array $locacao): ?array
    {
        $valor = currency_parse($dados['caucao_valor'] ?? 0);
        $caucao = $this->buscarAtualPorLocacao($locacaoId);

        if (!$this->tabelaDisponivel()) {
            if ($valor <= 0) {
                return null;
            }
            throw new \RuntimeException('Tabela de caucoes de locacoes ainda nao foi criada');
        }

        if ($valor <= 0) {
            if ($caucao && ($caucao['status'] ?? '') !== 'devolvida') {
                $this->cancelarCaucao($caucao);
            }
            return null;
        }

        $payload = [
            'chave' => $locacao['chave'],
            'id_locacao' => $locacaoId,
            'id_cliente' => !empty($locacao['id_cliente'] ?? $dados['id_cliente'] ?? null) ? (int) ($locacao['id_cliente'] ?? $dados['id_cliente']) : null,
            'id_conta' => !empty($dados['id_conta_caucao']) ? (int) $dados['id_conta_caucao'] : null,
            'id_cartao' => !empty($dados['id_cartao_caucao']) ? (int) $dados['id_cartao_caucao'] : null,
            'id_forma_pagamento' => !empty($dados['id_forma_pagamento_caucao']) ? (int) $dados['id_forma_pagamento_caucao'] : null,
            'valor' => $valor,
            'prazo_devolucao' => isset($dados['caucao_prazo_devolucao']) && $dados['caucao_prazo_devolucao'] !== '' ? (int) $dados['caucao_prazo_devolucao'] : null,
            'lancar_financeiro' => $this->booleanFromInput($dados['caucao_lancar_financeiro'] ?? null) ? 1 : 0,
            'observacoes' => $dados['caucao_observacoes'] ?? null,
        ];

        if ($caucao && ($caucao['status'] ?? '') === 'devolvida') {
            if (!$this->caucaoDevolvidaEquivalePayload($caucao, $payload)) {
                throw new \RuntimeException('Nao e possivel alterar a caucao ja devolvida');
            }
            return $caucao;
        }

        if ($caucao && !$this->podeAlterarCaucaoComFinanceiro($caucao, $payload)) {
            throw new \RuntimeException('Nao e possivel alterar a caucao com lancamento financeiro ja pago');
        }

        if ($caucao) {
            $this->qb
                ->table('locacoes_caucoes')
                ->where('id', '=', (int) $caucao['id'])
                ->update(array_merge($payload, [
                    'status' => 'ativa',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]));
            $idCaucao = (int) $caucao['id'];
        } else {
            $idCaucao = $this->qb
                ->table('locacoes_caucoes')
                ->insert(array_merge($payload, ['status' => 'ativa']));
            $caucao = ['id' => $idCaucao];
        }

        $caucaoAtualizada = array_merge($caucao, $payload, ['id' => $idCaucao, 'status' => 'ativa']);
        $this->sincronizarFinanceiroEntrada($caucaoAtualizada, $locacao);

        return $this->buscarAtualPorLocacao($locacaoId);
    }

    public function devolver(int $locacaoId, array $locacao, array $dados = []): void
    {
        $caucao = $this->buscarAtualPorLocacao($locacaoId);
        if (!$caucao || (float) ($caucao['valor'] ?? 0) <= 0) {
            throw new \RuntimeException('Locacao sem caucao para devolver');
        }

        if (($caucao['status'] ?? '') === 'devolvida' || !empty($caucao['data_devolucao'])) {
            throw new \RuntimeException('Caucao ja foi devolvida');
        }

        $dataDevolucao = $this->normalizarDataDevolucao($dados['data_devolucao'] ?? null);
        $idFinanceiro = null;
        $entradaReal = $this->buscarEntradaFinanceiraReal($caucao);

        if ($entradaReal) {
            $idFinanceiro = $this->criarFinanceiroDevolucao($caucao, $locacao, [
                'id_locacao' => $locacaoId,
                'codigo' => $locacao['codigo'] ?? $locacaoId,
                'id_matriz_filial' => $locacao['id_matriz_filial_retirada'] ?? null,
                'id_conta' => $dados['id_conta'] ?? $dados['id_conta_caucao'] ?? null,
                'id_forma_pagamento' => $dados['id_forma_pagamento'] ?? $dados['id_forma_pagamento_caucao'] ?? null,
                'data_devolucao' => $dataDevolucao,
            ]);
        }

        $this->qb
            ->table('locacoes_caucoes')
            ->where('id', '=', (int) $caucao['id'])
            ->update([
                'id_financeiro_devolucao' => $idFinanceiro,
                'data_devolucao' => $dataDevolucao,
                'status' => 'devolvida',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    private function criarFinanceiroDevolucao(array $caucao, array $locacao, array $dados): int
    {
        if (empty($dados['id_conta'])) {
            throw new \RuntimeException('Conta da devolucao da caucao e obrigatoria');
        }
        if (empty($dados['id_forma_pagamento'])) {
            throw new \RuntimeException('Forma de pagamento da devolucao da caucao e obrigatoria');
        }

        $plano = (new PlanoDeContas())->buscarPorHierarquia(self::PLANO_CONTA_DEVOLUCAO);
        if (!$plano) {
            throw new \RuntimeException('Plano de conta da devolucao de caucao nao encontrado');
        }

        $descricao = sprintf('Devolucao Caucao - Locacao %s', $dados['codigo']);
        $dadosFinanceiro = [
            'chave' => $caucao['chave'],
            'id_locacao' => (int) $dados['id_locacao'],
            'id_cliente' => $caucao['id_cliente'] ?? null,
            'id_matriz_filial' => !empty($dados['id_matriz_filial']) ? (int) $dados['id_matriz_filial'] : null,
            'id_conta' => (int) $dados['id_conta'],
            'id_forma_pagamento' => (int) $dados['id_forma_pagamento'],
            'id_plano_de_conta' => (int) $plano['id'],
            'tipo' => 'D',
            'pago' => 'S',
            'parcela' => 1,
            'total_parcelas' => 1,
            'descricao' => $descricao,
            'data_venci' => $dados['data_devolucao'],
            'data_pago' => $dados['data_devolucao'],
            'valor_subtotal' => $caucao['valor'],
            'valor_total' => $caucao['valor'],
        ];

        if (!empty($dadosFinanceiro['id_matriz_filial'])) {
            $dadosFinanceiro['sequencia'] = SequenciaHelper::proximaSequencia(
                (string) $caucao['chave'],
                (int) $dadosFinanceiro['id_matriz_filial'],
                'financeiro'
            );
        }

        $idFinanceiro = (new Financeiro())->criar($dadosFinanceiro);
        (new FinanceiroItem())->salvarTodos($idFinanceiro, (string) $caucao['chave'], [[
            'id_plano_de_conta' => (int) $plano['id'],
            'descricao' => $descricao,
            'valor' => $caucao['valor'],
        ]]);

        return $idFinanceiro;
    }

    private function buscarEntradaFinanceiraReal(array $caucao): ?array
    {
        if (empty($caucao['id_financeiro_entrada'])) {
            return null;
        }

        return (new Financeiro())->buscarPorId((int) $caucao['id_financeiro_entrada']);
    }

    private function normalizarDataDevolucao(mixed $value): string
    {
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        return date('Y-m-d');
    }

    private function sincronizarFinanceiroEntrada(array $caucao, array $locacao): void
    {
        $idFinanceiro = !empty($caucao['id_financeiro_entrada']) ? (int) $caucao['id_financeiro_entrada'] : null;

        if (empty($caucao['lancar_financeiro'])) {
            if ($idFinanceiro) {
                $this->removerFinanceiroPendente($idFinanceiro);
                $this->qb
                    ->table('locacoes_caucoes')
                    ->where('id', '=', (int) $caucao['id'])
                    ->update([
                        'id_financeiro_entrada' => null,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
            }
            return;
        }

        $plano = (new PlanoDeContas())->buscarPorHierarquia(self::PLANO_CONTA_ENTRADA);
        if (!$plano) {
            throw new \RuntimeException('Plano de conta da entrada de caucao nao encontrado');
        }

        $descricao = sprintf('Caucao - Locacao %s', $locacao['codigo'] ?? $locacao['id'] ?? $caucao['id_locacao']);
        $dadosFinanceiro = [
            'chave' => $caucao['chave'],
            'codigo' => $locacao['codigo'] ?? null,
            'id_locacao' => (int) $caucao['id_locacao'],
            'id_cliente' => $caucao['id_cliente'] ?? null,
            'id_matriz_filial' => !empty($locacao['id_matriz_filial_retirada']) ? (int) $locacao['id_matriz_filial_retirada'] : null,
            'id_conta' => $caucao['id_conta'] ?? null,
            'id_forma_pagamento' => $caucao['id_forma_pagamento'] ?? null,
            'id_plano_de_conta' => (int) $plano['id'],
            'tipo' => 'R',
            'pago' => 'N',
            'parcela' => 1,
            'total_parcelas' => 1,
            'descricao' => $descricao,
            'data_venci' => date('Y-m-d'),
            'valor_subtotal' => $caucao['valor'],
            'valor_total' => $caucao['valor'],
        ];

        $financeiroModel = new Financeiro();
        if ($idFinanceiro) {
            $lancamento = $financeiroModel->buscarPorId($idFinanceiro);
            if (!$lancamento || (int) ($lancamento['id_locacao'] ?? 0) !== (int) $caucao['id_locacao']) {
                $idFinanceiro = null;
            } elseif (($lancamento['pago'] ?? 'N') === 'S') {
                if ($this->financeiroPagoEquivaleCaucao($lancamento, $caucao)) {
                    return;
                }
                throw new \RuntimeException('Nao e possivel alterar a caucao com lancamento financeiro ja pago');
            } else {
                $financeiroModel->atualizar($idFinanceiro, $dadosFinanceiro);
                (new FinanceiroItem())->salvarTodos($idFinanceiro, (string) $caucao['chave'], [[
                    'id_plano_de_conta' => (int) $plano['id'],
                    'descricao' => $descricao,
                    'valor' => $caucao['valor'],
                ]]);
            }
        }

        if (!$idFinanceiro) {
            if (!empty($dadosFinanceiro['id_matriz_filial'])) {
                $dadosFinanceiro['sequencia'] = SequenciaHelper::proximaSequencia(
                    (string) $caucao['chave'],
                    (int) $dadosFinanceiro['id_matriz_filial'],
                    'financeiro'
                );
            }
            $idFinanceiro = $financeiroModel->criar($dadosFinanceiro);
            (new FinanceiroItem())->salvarTodos($idFinanceiro, (string) $caucao['chave'], [[
                'id_plano_de_conta' => (int) $plano['id'],
                'descricao' => $descricao,
                'valor' => $caucao['valor'],
            ]]);
        }

        $this->qb
            ->table('locacoes_caucoes')
            ->where('id', '=', (int) $caucao['id'])
            ->update([
                'id_financeiro_entrada' => $idFinanceiro,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    private function cancelarCaucao(array $caucao): void
    {
        if (!empty($caucao['id_financeiro_entrada'])) {
            $this->removerFinanceiroPendente((int) $caucao['id_financeiro_entrada']);
        }

        $this->qb
            ->table('locacoes_caucoes')
            ->where('id', '=', (int) $caucao['id'])
            ->update([
                'status' => 'cancelada',
                'id_financeiro_entrada' => null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    private function removerFinanceiroPendente(int $idFinanceiro): void
    {
        $financeiroModel = new Financeiro();
        $lancamento = $financeiroModel->buscarPorId($idFinanceiro);
        if (!$lancamento) {
            return;
        }

        if (($lancamento['pago'] ?? 'N') === 'S') {
            throw new \RuntimeException('Nao e possivel remover caucao com lancamento financeiro ja pago');
        }

        $financeiroModel->deletar($idFinanceiro);
    }

    private function podeAlterarCaucaoComFinanceiro(array $caucaoAtual, array $payload): bool
    {
        if (empty($caucaoAtual['id_financeiro_entrada'])) {
            return true;
        }

        $lancamento = (new Financeiro())->buscarPorId((int) $caucaoAtual['id_financeiro_entrada']);
        if (!$lancamento || ($lancamento['pago'] ?? 'N') !== 'S') {
            return true;
        }

        if (empty($payload['lancar_financeiro'])) {
            return false;
        }

        return $this->financeiroPagoEquivaleCaucao($lancamento, $payload);
    }

    private function financeiroPagoEquivaleCaucao(array $lancamento, array $caucao): bool
    {
        return (float) ($lancamento['valor_total'] ?? 0) === (float) ($caucao['valor'] ?? 0)
            && (int) ($lancamento['id_conta'] ?? 0) === (int) ($caucao['id_conta'] ?? 0)
            && (int) ($lancamento['id_forma_pagamento'] ?? 0) === (int) ($caucao['id_forma_pagamento'] ?? 0)
            && (int) ($lancamento['id_cliente'] ?? 0) === (int) ($caucao['id_cliente'] ?? 0);
    }

    private function caucaoDevolvidaEquivalePayload(array $caucao, array $payload): bool
    {
        return (float) ($caucao['valor'] ?? 0) === (float) ($payload['valor'] ?? 0)
            && (int) ($caucao['id_conta'] ?? 0) === (int) ($payload['id_conta'] ?? 0)
            && (int) ($caucao['id_forma_pagamento'] ?? 0) === (int) ($payload['id_forma_pagamento'] ?? 0)
            && (int) ($caucao['id_cliente'] ?? 0) === (int) ($payload['id_cliente'] ?? 0);
    }

    private function booleanFromInput(mixed $value): bool
    {
        return in_array($value, [1, '1', true, 'true', 'S', 'on', 'sim'], true);
    }
}
