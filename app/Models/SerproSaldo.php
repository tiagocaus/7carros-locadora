<?php

namespace App\Models;

/**
 * Model SerproSaldo
 *
 * Gerencia saldo prepago de cada tenant para uso da API SERPRO eFrotas.
 * Inclui configuracao de auto-recarga via Stripe.
 *
 * IMPORTANTE: Operacoes de debito/credito usam SELECT ... FOR UPDATE
 * para evitar race conditions em acessos concorrentes.
 */
class SerproSaldo extends Model
{
    /**
     * Busca saldo do tenant atual
     */
    public function buscarPorChave(): ?array
    {
        return $this->qb
            ->table('serpro_saldo')
            ->first();
    }

    /**
     * Busca saldo de um tenant especifico (para CRON/webhook)
     */
    public function buscarPorChaveEspecifica(string $chave): ?array
    {
        return $this->qb
            ->table('serpro_saldo')
            ->withoutChave()
            ->where('chave', '=', $chave)
            ->first();
    }

    /**
     * Retorna saldo atual do tenant
     */
    public function getSaldo(): float
    {
        $registro = $this->buscarPorChave();
        return $registro ? (float) $registro['saldo'] : 0.00;
    }

    /**
     * Verifica se tenant tem saldo suficiente
     */
    public function temSaldoSuficiente(float $valor): bool
    {
        return $this->getSaldo() >= $valor;
    }

    /**
     * Cria registro de saldo para o tenant (se nao existir)
     */
    public function criarSeNaoExiste(): int
    {
        $existente = $this->buscarPorChave();
        if ($existente) {
            return (int) $existente['id'];
        }

        return $this->qb
            ->table('serpro_saldo')
            ->insert([
                'chave' => $_SESSION['chave'],
                'saldo' => 0.00,
                'auto_recarga_ativo' => 0,
                'auto_recarga_valor' => (float) env('SERPRO_AUTO_RECARGA_VALOR', 100.00),
                'auto_recarga_limite' => (float) env('SERPRO_AUTO_RECARGA_LIMITE', 10.00),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Debita valor do saldo com lock (FOR UPDATE)
     * Retorna saldo anterior e posterior
     *
     * @throws \RuntimeException Se saldo insuficiente
     */
    public function debitar(float $valor): array
    {
        $mysqli = $this->getMysqli();
        $chave = $_SESSION['chave'];

        // Lock do registro para evitar race condition
        $stmt = $mysqli->prepare(
            "SELECT id, saldo FROM serpro_saldo WHERE chave = ? FOR UPDATE"
        );
        $stmt->bind_param('s', $chave);
        $stmt->execute();
        $result = $stmt->get_result();
        $registro = $result->fetch_assoc();
        $stmt->close();

        if (!$registro) {
            throw new \RuntimeException('Saldo nao encontrado para o tenant');
        }

        $saldoAnterior = (float) $registro['saldo'];

        if ($saldoAnterior < $valor) {
            throw new \RuntimeException('Saldo insuficiente. Saldo atual: R$ ' . number_format($saldoAnterior, 2, ',', '.'));
        }

        $saldoPosterior = round($saldoAnterior - $valor, 2);

        $stmtUpdate = $mysqli->prepare(
            "UPDATE serpro_saldo SET saldo = ?, updated_at = NOW() WHERE id = ?"
        );
        $stmtUpdate->bind_param('di', $saldoPosterior, $registro['id']);
        $stmtUpdate->execute();
        $stmtUpdate->close();

        return [
            'saldo_anterior' => $saldoAnterior,
            'saldo_posterior' => $saldoPosterior,
        ];
    }

    /**
     * Credita valor no saldo com lock (FOR UPDATE)
     * Retorna saldo anterior e posterior
     */
    public function creditar(float $valor, ?string $chaveOverride = null): array
    {
        $mysqli = $this->getMysqli();
        $chave = $chaveOverride ?? $_SESSION['chave'];

        // Lock do registro
        $stmt = $mysqli->prepare(
            "SELECT id, saldo FROM serpro_saldo WHERE chave = ? FOR UPDATE"
        );
        $stmt->bind_param('s', $chave);
        $stmt->execute();
        $result = $stmt->get_result();
        $registro = $result->fetch_assoc();
        $stmt->close();

        if (!$registro) {
            // Cria registro se nao existe (caso webhook chegue antes do tenant configurar)
            $id = $this->qb
                ->table('serpro_saldo')
                ->withoutChave()
                ->insert([
                    'chave' => $chave,
                    'saldo' => $valor,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);

            return [
                'saldo_anterior' => 0.00,
                'saldo_posterior' => $valor,
            ];
        }

        $saldoAnterior = (float) $registro['saldo'];
        $saldoPosterior = round($saldoAnterior + $valor, 2);

        $stmtUpdate = $mysqli->prepare(
            "UPDATE serpro_saldo SET saldo = ?, updated_at = NOW() WHERE id = ?"
        );
        $stmtUpdate->bind_param('di', $saldoPosterior, $registro['id']);
        $stmtUpdate->execute();
        $stmtUpdate->close();

        return [
            'saldo_anterior' => $saldoAnterior,
            'saldo_posterior' => $saldoPosterior,
        ];
    }

    /**
     * Atualiza configuracao de auto-recarga
     */
    public function atualizarAutoRecarga(array $dados): int
    {
        $dadosUpdate = [];

        if (array_key_exists('auto_recarga_ativo', $dados)) {
            $dadosUpdate['auto_recarga_ativo'] = (int) $dados['auto_recarga_ativo'];
        }
        if (array_key_exists('auto_recarga_valor', $dados)) {
            $dadosUpdate['auto_recarga_valor'] = max(100.00, (float) $dados['auto_recarga_valor']);
        }
        if (array_key_exists('auto_recarga_limite', $dados)) {
            $dadosUpdate['auto_recarga_limite'] = max(1.00, (float) $dados['auto_recarga_limite']);
        }
        if (array_key_exists('stripe_customer_id', $dados)) {
            $dadosUpdate['stripe_customer_id'] = $dados['stripe_customer_id'];
        }
        if (array_key_exists('stripe_payment_method_id', $dados)) {
            $dadosUpdate['stripe_payment_method_id'] = $dados['stripe_payment_method_id'];
        }

        if (empty($dadosUpdate)) {
            return 0;
        }

        $dadosUpdate['updated_at'] = date('Y-m-d H:i:s');

        return $this->qb
            ->table('serpro_saldo')
            ->update($dadosUpdate);
    }

    /**
     * Verifica se auto-recarga deve ser disparada
     * Retorna dados da auto-recarga ou null se nao necessario
     */
    public function verificarAutoRecarga(): ?array
    {
        $registro = $this->buscarPorChave();

        if (!$registro) {
            return null;
        }

        if (
            (int) $registro['auto_recarga_ativo'] === 1
            && (float) $registro['saldo'] <= (float) $registro['auto_recarga_limite']
            && !empty($registro['stripe_customer_id'])
            && !empty($registro['stripe_payment_method_id'])
        ) {
            return [
                'valor' => (float) $registro['auto_recarga_valor'],
                'stripe_customer_id' => $registro['stripe_customer_id'],
                'stripe_payment_method_id' => $registro['stripe_payment_method_id'],
                'saldo_atual' => (float) $registro['saldo'],
            ];
        }

        return null;
    }
}
