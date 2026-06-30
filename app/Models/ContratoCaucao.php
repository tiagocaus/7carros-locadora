<?php

namespace App\Models;

/**
 * Model ContratoCaucao
 *
 * Controla caucoes vinculadas a contratos e seus lancamentos financeiros.
 */
class ContratoCaucao extends Model
{
    private const PLANO_CONTA_ENTRADA = '1.1.6.01';
    private const PLANO_CONTA_DEVOLUCAO = '1.1.6.02';

    public function tabelaDisponivel(): bool
    {
        static $disponivel = null;

        if ($disponivel !== null) {
            return $disponivel;
        }

        $result = $this->getMysqli()->query("SHOW TABLES LIKE 'contratos_caucoes'");
        $disponivel = $result !== false && $result->num_rows > 0;
        if ($result instanceof \mysqli_result) {
            $result->free();
        }

        return $disponivel;
    }

    public function buscarAtivaPorContrato(int $contratoId): ?array
    {
        if (!$this->tabelaDisponivel()) {
            return null;
        }

        return $this->qb
            ->table('contratos_caucoes', 'cc')
            ->select([
                'cc.*',
                'cb.nome AS conta_descricao',
                'fp.nome AS forma_pagamento_descricao',
                'cart.bandeira AS cartao_bandeira',
                'cart.ultimos_digitos AS cartao_ultimos_digitos',
            ])
            ->leftJoin('contas_bancarias', 'cb', 'cc.id_conta', '=', 'cb.id')
            ->leftJoin('formas_pagamento', 'fp', 'cc.id_forma_pagamento', '=', 'fp.id')
            ->leftJoin('clientes_cartoes', 'cart', 'cc.id_cartao', '=', 'cart.id')
            ->where('cc.id_contrato', '=', $contratoId)
            ->where('cc.status', '=', 'ativa')
            ->orderByDesc('cc.id')
            ->first();
    }

    public function sincronizarAtiva(int $contratoId, array $dados, array $contrato): ?array
    {
        $valor = currency_parse($dados['caucao_valor'] ?? 0);
        $caucao = $this->buscarAtivaPorContrato($contratoId);

        if (!$this->tabelaDisponivel()) {
            if ($valor <= 0) {
                return null;
            }
            throw new \RuntimeException('Tabela de caucoes de contratos ainda nao foi criada');
        }

        if ($valor <= 0) {
            if ($caucao) {
                $this->cancelarCaucao($caucao);
            }
            return null;
        }

        $lancarFinanceiro = $this->booleanFromInput($dados['caucao_lancar_financeiro'] ?? null);
        $payload = [
            'chave' => $contrato['chave'],
            'id_contrato' => $contratoId,
            'id_cliente' => (int) ($contrato['id_cliente'] ?? $dados['id_cliente'] ?? 0),
            'id_conta' => !empty($dados['id_conta_caucao']) ? (int) $dados['id_conta_caucao'] : null,
            'id_cartao' => !empty($dados['id_cartao_caucao']) ? (int) $dados['id_cartao_caucao'] : null,
            'id_forma_pagamento' => !empty($dados['id_forma_pagamento_caucao']) ? (int) $dados['id_forma_pagamento_caucao'] : null,
            'valor' => $valor,
            'prazo_devolucao' => !empty($dados['caucao_prazo_devolucao']) ? (int) $dados['caucao_prazo_devolucao'] : null,
            'lancar_financeiro' => $lancarFinanceiro ? 1 : 0,
            'observacoes' => $dados['caucao_observacoes'] ?? null,
            'status' => 'ativa',
        ];

        if ($caucao && !$this->podeAlterarCaucaoComFinanceiro($caucao, $payload)) {
            throw new \RuntimeException('Nao e possivel alterar a caucao com lancamento financeiro ja pago');
        }

        if ($caucao) {
            $this->qb
                ->table('contratos_caucoes')
                ->where('id', '=', (int) $caucao['id'])
                ->update(array_merge($payload, ['updated_at' => now()]));
            $idCaucao = (int) $caucao['id'];
        } else {
            $idCaucao = $this->qb
                ->table('contratos_caucoes')
                ->insert($payload);
            $caucao = ['id' => $idCaucao];
        }

        $caucaoAtualizada = array_merge($caucao, $payload, ['id' => $idCaucao]);
        $this->sincronizarFinanceiroEntrada($caucaoAtualizada, $contrato);

        return $this->buscarAtivaPorContrato($contratoId);
    }

    public function devolver(int $contratoId, array $contrato, array $dados = []): void
    {
        $caucao = $this->buscarCaucaoAtualPorContrato($contratoId);
        if (!$caucao || (float) ($caucao['valor'] ?? 0) <= 0) {
            throw new \RuntimeException('Contrato sem caucao para devolver');
        }

        if (($caucao['status'] ?? '') === 'devolvida' || !empty($caucao['data_devolucao'])) {
            throw new \RuntimeException('Caucao ja foi devolvida');
        }

        $dataDevolucao = $this->normalizarDataDevolucao($dados['data_devolucao'] ?? null);
        $idFinanceiro = null;
        $entradaReal = $this->buscarEntradaFinanceiraReal($caucao);

        if ($entradaReal) {
            $idFinanceiro = $this->criarFinanceiroDevolucao($caucao, [
                'id_contrato' => $contratoId,
                'codigo' => $contrato['codigo'] ?? $contratoId,
                'id_matriz_filial' => $contrato['id_matriz_filial_retirada'] ?? null,
                'id_conta' => $dados['id_conta'] ?? $dados['id_conta_caucao'] ?? null,
                'id_forma_pagamento' => $dados['id_forma_pagamento'] ?? $dados['id_forma_pagamento_caucao'] ?? null,
                'data_devolucao' => $dataDevolucao,
            ]);
        }

        $this->qb
            ->table('contratos_caucoes')
            ->where('id', '=', (int) $caucao['id'])
            ->update([
                'id_financeiro_devolucao' => $idFinanceiro,
                'data_devolucao' => $dataDevolucao,
                'status' => 'devolvida',
                'updated_at' => now(),
            ]);
    }

    private function sincronizarFinanceiroEntrada(array $caucao, array $contrato): void
    {
        $idFinanceiro = !empty($caucao['id_financeiro_entrada']) ? (int) $caucao['id_financeiro_entrada'] : null;

        if (empty($caucao['lancar_financeiro'])) {
            if ($idFinanceiro) {
                $this->removerFinanceiroPendente($idFinanceiro);
                $this->qb
                    ->table('contratos_caucoes')
                    ->where('id', '=', (int) $caucao['id'])
                    ->update([
                        'id_financeiro_entrada' => null,
                        'updated_at' => now(),
                    ]);
            }
            return;
        }

        $plano = (new PlanoDeContas())->buscarPorHierarquia(self::PLANO_CONTA_ENTRADA);
        if (!$plano) {
            throw new \RuntimeException('Plano de conta da entrada de caucao nao encontrado');
        }

        $descricao = sprintf('Caucao - Contrato #%s', $contrato['codigo'] ?? $contrato['id'] ?? $caucao['id_contrato']);
        $dadosFinanceiro = [
            'chave' => $caucao['chave'],
            'codigo' => $contrato['codigo'] ?? null,
            'id_contrato' => (int) $caucao['id_contrato'],
            'id_cliente' => (int) $caucao['id_cliente'],
            'id_matriz_filial' => !empty($contrato['id_matriz_filial_retirada']) ? (int) $contrato['id_matriz_filial_retirada'] : null,
            'id_conta' => $caucao['id_conta'] ?? null,
            'id_forma_pagamento' => $caucao['id_forma_pagamento'] ?? null,
            'id_plano_de_conta' => (int) $plano['id'],
            'tipo' => 'R',
            'pago' => 'N',
            'parcela' => 1,
            'total_parcelas' => 1,
            'descricao' => $descricao,
            'data_venci' => today(),
            'valor_subtotal' => $caucao['valor'],
            'valor_total' => $caucao['valor'],
        ];

        $financeiroModel = new Financeiro();
        if ($idFinanceiro) {
            $lancamento = $financeiroModel->buscarPorId($idFinanceiro);
            if (!$lancamento || (int) ($lancamento['id_contrato'] ?? 0) !== (int) $caucao['id_contrato']) {
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
                $dadosFinanceiro['sequencia'] = \App\Helpers\SequenciaHelper::proximaSequencia(
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
            ->table('contratos_caucoes')
            ->where('id', '=', (int) $caucao['id'])
            ->update([
                'id_financeiro_entrada' => $idFinanceiro,
                'updated_at' => now(),
            ]);
    }

    private function buscarCaucaoAtualPorContrato(int $contratoId): ?array
    {
        if (!$this->tabelaDisponivel()) {
            return null;
        }

        return $this->qb
            ->table('contratos_caucoes')
            ->where('id_contrato', '=', $contratoId)
            ->where('status', '!=', 'cancelada')
            ->orderByDesc('id')
            ->first();
    }

    private function criarFinanceiroDevolucao(array $caucao, array $dados): int
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

        $descricao = sprintf('Devolucao Caucao - Contrato #%s', $dados['codigo']);
        $dadosFinanceiro = [
            'chave' => $caucao['chave'],
            'codigo' => $dados['codigo'] ?? null,
            'id_contrato' => (int) $dados['id_contrato'],
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
            $dadosFinanceiro['sequencia'] = \App\Helpers\SequenciaHelper::proximaSequencia(
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

        return today();
    }

    private function cancelarCaucao(array $caucao): void
    {
        if (!empty($caucao['id_financeiro_entrada'])) {
            $this->removerFinanceiroPendente((int) $caucao['id_financeiro_entrada']);
        }

        $this->qb
            ->table('contratos_caucoes')
            ->where('id', '=', (int) $caucao['id'])
            ->update([
                'status' => 'cancelada',
                'id_financeiro_entrada' => null,
                'updated_at' => now(),
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

        return (float) $caucaoAtual['valor'] === (float) $payload['valor']
            && (int) ($caucaoAtual['id_conta'] ?? 0) === (int) ($payload['id_conta'] ?? 0)
            && (int) ($caucaoAtual['id_forma_pagamento'] ?? 0) === (int) ($payload['id_forma_pagamento'] ?? 0)
            && (int) ($caucaoAtual['id_cliente'] ?? 0) === (int) ($payload['id_cliente'] ?? 0);
    }

    private function financeiroPagoEquivaleCaucao(array $lancamento, array $caucao): bool
    {
        return (float) ($lancamento['valor_total'] ?? 0) === (float) ($caucao['valor'] ?? 0)
            && (int) ($lancamento['id_conta'] ?? 0) === (int) ($caucao['id_conta'] ?? 0)
            && (int) ($lancamento['id_forma_pagamento'] ?? 0) === (int) ($caucao['id_forma_pagamento'] ?? 0)
            && (int) ($lancamento['id_cliente'] ?? 0) === (int) ($caucao['id_cliente'] ?? 0);
    }

    private function booleanFromInput(mixed $value): bool
    {
        return in_array($value, [1, '1', true, 'true', 'S', 'on', 'sim'], true);
    }
}
