<?php

namespace App\Models;

/**
 * Model ClienteCartao
 *
 * Gerencia tokens de cartão de crédito para cobranças futuras.
 * NUNCA armazena dados sensíveis (número, validade, CVV).
 * Apenas tokens/referências dos gateways de pagamento.
 */
class ClienteCartao extends Model
{
    /**
     * Lista cartões salvos de um cliente para um gateway específico
     *
     * @param int $idCliente ID do cliente
     * @param string|null $gateway Código do gateway (asaas, stripe, etc.) ou null para todos
     * @return array Lista de cartões
     */
    public function listarPorCliente(int $idCliente, ?string $gateway = null): array
    {
        $query = $this->qb
            ->table('clientes_cartoes')
            ->select(['id', 'bandeira', 'ultimos_digitos', 'gateway', 'padrao'])
            ->where('id_cliente', '=', $idCliente);

        if ($gateway !== null) {
            $query->where('gateway', '=', $gateway);
        }

        return $query->orderBy('padrao', 'DESC')->orderBy('id', 'DESC')->get();
    }

    /**
     * Busca um cartão por ID
     *
     * @param int $id ID do cartão
     * @return array|null Dados do cartão ou null se não encontrado
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->qb
            ->table('clientes_cartoes')
            ->where('id', '=', $id)
            ->first();
    }

    /**
     * Busca um cartão por token
     *
     * @param string $token Token do gateway
     * @param int $idCliente ID do cliente (para segurança)
     * @return array|null Dados do cartão ou null se não encontrado
     */
    public function buscarPorToken(string $token, int $idCliente): ?array
    {
        return $this->qb
            ->table('clientes_cartoes')
            ->where('token', '=', $token)
            ->where('id_cliente', '=', $idCliente)
                        ->first();
    }

    /**
     * Verifica se um token já existe para o cliente
     *
     * @param string $token Token do gateway
     * @param int $idCliente ID do cliente
     * @return bool
     */
    public function tokenExiste(string $token, int $idCliente): bool
    {
        $result = $this->qb
            ->table('clientes_cartoes')
            ->select(['id'])
            ->where('token', '=', $token)
            ->where('id_cliente', '=', $idCliente)
            ->first();

        return $result !== null;
    }

    /**
     * Cria um novo registro de cartão
     *
     * @param array{
     *     id_cliente: int,
     *     bandeira: string,
     *     ultimos_digitos: string,
     *     token: string,
     *     gateway: string,
     *     padrao?: int
     * } $dados Dados do cartão
     * @return int ID do cartão criado
     */
    public function criar(array $dados): int
    {
        // Verificar se o token já existe para este cliente
        if ($this->tokenExiste($dados['token'], $dados['id_cliente'])) {
            // Retorna o ID existente em vez de criar duplicado
            $existente = $this->buscarPorToken($dados['token'], $dados['id_cliente']);
            return $existente['id'];
        }

        // Se é o primeiro cartão do cliente para este gateway, marcar como padrão
        $cartoes = $this->listarPorCliente($dados['id_cliente'], $dados['gateway']);
        if (empty($cartoes)) {
            $dados['padrao'] = 1;
        } else {
            $dados['padrao'] = $dados['padrao'] ?? 0;
        }

        return $this->qb
            ->table('clientes_cartoes')
            ->insert($dados);
    }

    /**
     * Define um cartão como padrão (e remove padrão dos outros)
     *
     * @param int $id ID do cartão
     * @param int $idCliente ID do cliente
     * @param string $gateway Gateway do cartão
     * @return bool
     */
    public function definirComoPadrao(int $id, int $idCliente, string $gateway): bool
    {
        // Remover padrão de todos os cartões do cliente para este gateway
        $this->qb
            ->table('clientes_cartoes')
            ->where('id_cliente', '=', $idCliente)
            ->where('gateway', '=', $gateway)
            ->update(['padrao' => 0]);

        // Definir o cartão selecionado como padrão
        return $this->qb
            ->table('clientes_cartoes')
            ->where('id', '=', $id)
            ->update(['padrao' => 1]) > 0;
    }

    /**
     * Remove um cartão (hard delete)
     *
     * @param int $id ID do cartão
     * @param int $idCliente ID do cliente (para segurança)
     * @return bool
     */
    public function desativar(int $id, int $idCliente): bool
    {
        return $this->qb
            ->table('clientes_cartoes')
            ->where('id', '=', $id)
            ->where('id_cliente', '=', $idCliente)
            ->delete() > 0;
    }

    /**
     * Remove fisicamente um cartão (use com cuidado)
     *
     * @param int $id ID do cartão
     * @param int $idCliente ID do cliente (para segurança)
     * @return bool
     */
    public function remover(int $id, int $idCliente): bool
    {
        return $this->qb
            ->table('clientes_cartoes')
            ->where('id', '=', $id)
            ->where('id_cliente', '=', $idCliente)
            ->delete() > 0;
    }

    /**
     * Retorna o cartão padrão do cliente para um gateway
     *
     * @param int $idCliente ID do cliente
     * @param string $gateway Código do gateway
     * @return array|null Dados do cartão ou null se não encontrado
     */
    public function buscarPadrao(int $idCliente, string $gateway): ?array
    {
        return $this->qb
            ->table('clientes_cartoes')
            ->where('id_cliente', '=', $idCliente)
            ->where('gateway', '=', $gateway)
            ->where('padrao', '=', 1)
                        ->first();
    }

    /**
     * Atualiza o gateway_customer_id de um cartao
     *
     * @param int $id ID do cartao
     * @param string $customerId ID do customer no gateway (cus_xxx)
     * @return int Linhas afetadas
     */
    public function atualizarCustomerId(int $id, string $customerId): int
    {
        return $this->qb
            ->table('clientes_cartoes')
            ->where('id', '=', $id)
            ->update(['gateway_customer_id' => $customerId]);
    }
}
