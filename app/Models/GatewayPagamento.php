<?php

namespace App\Models;

use App\Core\Auth;
use App\Core\Database;

/**
 * Model para gerenciamento de gateways de pagamento
 *
 * Gerencia configurações de gateways por tenant, incluindo
 * criptografia de credenciais e validação de configurações.
 */
class GatewayPagamento extends Model
{
    /**
     * Retorna chave de criptografia
     */
    private function getEncryptionKey(): string
    {
        return Database::env('APP_KEY', 'default-key-change-me-in-production');
    }

    /**
     * Criptografa credenciais usando AES-256-CBC
     *
     * @param array<string, mixed> $credentials
     * @return string Base64 encoded (IV + ciphertext)
     */
    private function encryptCredentials(array $credentials): string
    {
        $json = json_encode($credentials);
        $key = hash('sha256', $this->getEncryptionKey(), true);
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($json, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $encrypted);
    }

    /**
     * Descriptografa credenciais
     *
     * @param string $encrypted Base64 encoded
     * @return array<string, mixed>
     */
    private function decryptCredentials(string $encrypted): array
    {
        $data = base64_decode($encrypted);
        if ($data === false || strlen($data) < 17) {
            return [];
        }

        $iv = substr($data, 0, 16);
        $ciphertext = substr($data, 16);
        $key = hash('sha256', $this->getEncryptionKey(), true);
        $json = openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        if ($json === false) {
            return [];
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Lista todos os gateways do tenant
     *
     * @param string $search Termo de busca
     * @return array<int, array<string, mixed>>
     */
    public function listar(string $search = ''): array
    {
        $query = $this->qb
            ->table('gateways_pagamento', 'g')
            ->select([
                'g.id',
                'g.gateway_code',
                'g.currencies',
                'g.nome',
                'g.ambiente',
                'g.status',
                'g.pix_enabled',
                'g.boleto_enabled',
                'g.credit_card_enabled',
                'g.debit_card_enabled',
                'g.ordem',
                'g.created_at',
                'g.updated_at'
            ])
            ->orderBy('g.ordem', 'ASC')
            ->orderBy('g.nome', 'ASC');

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('g.nome', 'LIKE', "%{$search}%")
                  ->orWhere('g.gateway_code', 'LIKE', "%{$search}%");
            });
        }

        return $query->get();
    }

    /**
     * Lista com paginação
     *
     * @param int $page Página atual
     * @param int $perPage Itens por página
     * @param string $search Termo de busca
     * @return array<int, array<string, mixed>>
     */
    public function listarPaginado(int $page, int $perPage, string $search = ''): array
    {
        $offset = ($page - 1) * $perPage;

        $query = $this->qb
            ->table('gateways_pagamento', 'g')
            ->selectRaw('g.id, g.gateway_code, g.currencies, g.nome, g.ambiente, g.status, g.pix_enabled, g.boleto_enabled, g.credit_card_enabled, g.debit_card_enabled, g.ordem, g.created_at, GROUP_CONCAT(DISTINCT mf.nome_fantasia ORDER BY mf.nome_fantasia SEPARATOR ", ") as filiais_nomes')
            ->leftJoin('gateways_filiais', 'gf', 'g.id', '=', 'gf.id_gateway')
            ->leftJoin('matrizes_filiais', 'mf', 'gf.id_matriz_filial', '=', 'mf.id')
            ->groupBy('g.id')
            ->orderBy('g.ordem', 'ASC')
            ->orderBy('g.nome', 'ASC')
            ->limit($perPage)
            ->offset($offset);

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('g.nome', 'LIKE', "%{$search}%")
                  ->orWhere('g.gateway_code', 'LIKE', "%{$search}%");
            });
        }

        return $query->get();
    }

    /**
     * Lista gateways do tenant com nomes das filiais vinculadas.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listarComFiliais(): array
    {
        return $this->qb
            ->table('gateways_pagamento', 'g')
            ->selectRaw('g.id, g.gateway_code, g.currencies, g.nome, g.ambiente, g.status, g.pix_enabled, g.boleto_enabled, g.credit_card_enabled, g.debit_card_enabled, g.ordem, g.created_at, GROUP_CONCAT(DISTINCT mf.nome_fantasia ORDER BY mf.nome_fantasia SEPARATOR ", ") as filiais_nomes')
            ->leftJoin('gateways_filiais', 'gf', 'g.id', '=', 'gf.id_gateway')
            ->leftJoin('matrizes_filiais', 'mf', 'gf.id_matriz_filial', '=', 'mf.id')
            ->groupBy('g.id')
            ->orderBy('g.ordem', 'ASC')
            ->orderBy('g.nome', 'ASC')
            ->get();
    }

    /**
     * Conta registros
     *
     * @param string $search Termo de busca
     * @return int
     */
    public function contar(string $search = ''): int
    {
        $query = $this->qb
            ->table('gateways_pagamento', 'g')
            ->selectRaw('COUNT(DISTINCT g.id) as total');

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('g.nome', 'LIKE', "%{$search}%")
                  ->orWhere('g.gateway_code', 'LIKE', "%{$search}%");
            });
        }

        $result = $query->first();
        return (int) ($result['total'] ?? 0);
    }

    /**
     * Lista gateways ativos do tenant
     *
     * @return array<int, array<string, mixed>>
     */
    public function listarAtivos(): array
    {
        return $this->qb
            ->table('gateways_pagamento', 'g')
            ->select([
                'g.id',
                'g.gateway_code',
                'g.currencies',
                'g.nome',
                'g.ambiente',
                'g.pix_enabled',
                'g.boleto_enabled',
                'g.credit_card_enabled',
                'g.debit_card_enabled',
                'g.ordem'
            ])
            ->where('g.status', '=', 'A')
            ->orderBy('g.ordem', 'ASC')
            ->get();
    }

    /**
     * Lista gateways para página pública (sem credenciais)
     *
     * @param string $chave Chave do tenant
     * @return array<int, array<string, mixed>>
     */
    public function listarParaPagamentoPublico(string $chave): array
    {
        return $this->qb
            ->table('gateways_pagamento')
            ->select([
                'id',
                'gateway_code',
                'currencies',
                'nome',
                'pix_enabled',
                'boleto_enabled',
                'credit_card_enabled',
                'debit_card_enabled',
            ])
            ->withChave($chave)
            ->where('status', '=', 'A')
            ->orderBy('ordem', 'ASC')
            ->get();
    }

    /**
     * Lista gateways para pagamento público filtrados por IDs específicos
     *
     * @param string $chave Chave do tenant
     * @param array<int> $ids Array de IDs dos gateways
     * @return array<int, array<string, mixed>>
     */
    public function listarParaPagamentoPublicoPorIds(string $chave, array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return $this->qb
            ->table('gateways_pagamento')
            ->select([
                'id',
                'gateway_code',
                'currencies',
                'nome',
                'pix_enabled',
                'boleto_enabled',
                'credit_card_enabled',
                'debit_card_enabled',
            ])
            ->withChave($chave)
            ->where('status', '=', 'A')
            ->whereIn('id', $ids)
            ->orderBy('ordem', 'ASC')
            ->get();
    }

    /**
     * Busca gateway por ID (sem credenciais)
     *
     * @param int $id
     * @return array<string, mixed>|null
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->qb
            ->table('gateways_pagamento')
            ->where('id', '=', $id)
            ->first();
    }

    /**
     * Busca gateway por ID com credenciais descriptografadas
     *
     * @param int $id
     * @return array<string, mixed>|null
     */
    public function buscarPorIdComCredenciais(int $id): ?array
    {
        $gateway = $this->buscarPorId($id);

        if ($gateway && !empty($gateway['credentials'])) {
            $gateway['credentials'] = $this->decryptCredentials($gateway['credentials']);
        } else if ($gateway) {
            $gateway['credentials'] = [];
        }

        return $gateway;
    }

    /**
     * Busca gateway por ID dentro de uma chave explicita.
     *
     * Necessario em rotinas administrativas sem sessao, mantendo o isolamento
     * tenant-scoped sem desabilitar o filtro automatico de chave.
     */
    public function buscarPorIdComCredenciaisParaTenant(int $id, string $chave): ?array
    {
        $gateway = $this->qb
            ->table('gateways_pagamento')
            ->withChave($chave)
            ->where('id', '=', $id)
            ->first();

        if ($gateway && !empty($gateway['credentials'])) {
            $gateway['credentials'] = $this->decryptCredentials($gateway['credentials']);
        } elseif ($gateway) {
            $gateway['credentials'] = [];
        }

        return $gateway;
    }

    /**
     * Busca gateway por chave e código
     *
     * @param string $chave Chave do tenant
     * @param string $gatewayCode Código do gateway
     * @return array<string, mixed>|null
     */
    public function buscarPorChaveECodigo(string $chave, string $gatewayCode): ?array
    {
        $gateway = $this->qb
            ->table('gateways_pagamento')
            ->withChave($chave)
            ->where('gateway_code', '=', $gatewayCode)
            ->where('status', '=', 'A')
            ->first();

        if ($gateway && !empty($gateway['credentials'])) {
            $gateway['credentials'] = $this->decryptCredentials($gateway['credentials']);
        } else if ($gateway) {
            $gateway['credentials'] = [];
        }

        return $gateway;
    }

    /**
     * Cria novo gateway
     *
     * @param array<string, mixed> $dados
     * @return int ID do gateway criado
     */
    public function criar(array $dados): int
    {
        $credentials = $dados['credentials'] ?? [];
        $credentialsEncrypted = !empty($credentials) ? $this->encryptCredentials($credentials) : null;

        // Tratar currencies como JSON
        $currencies = $dados['currencies'] ?? ['BRL'];
        if (is_string($currencies)) {
            $currencies = json_decode($currencies, true) ?: ['BRL'];
        }
        $currenciesJson = json_encode(array_values($currencies));

        return $this->qb
            ->table('gateways_pagamento')
            ->insert([
                'chave' => $dados['chave'] ?? Auth::chave(),
                'gateway_code' => $dados['gateway_code'],
                'currencies' => $currenciesJson,
                'nome' => $dados['nome'],
                'credentials' => $credentialsEncrypted,
                'ambiente' => $dados['ambiente'] ?? 'sandbox',
                'status' => $dados['status'] ?? 'A',
                'pix_enabled' => (int) ($dados['pix_enabled'] ?? false),
                'boleto_enabled' => (int) ($dados['boleto_enabled'] ?? false),
                'credit_card_enabled' => (int) ($dados['credit_card_enabled'] ?? false),
                'debit_card_enabled' => (int) ($dados['debit_card_enabled'] ?? false),
                'webhook_url' => $dados['webhook_url'] ?? null,
                'webhook_secret' => $dados['webhook_secret'] ?? null,
                'ordem' => (int) ($dados['ordem'] ?? 0),
            ]);
    }

    /**
     * Atualiza gateway
     *
     * @param int $id
     * @param array<string, mixed> $dados
     * @return int Número de registros atualizados
     */
    public function atualizar(int $id, array $dados): int
    {
        $dadosUpdate = [];

        if (isset($dados['nome'])) {
            $dadosUpdate['nome'] = $dados['nome'];
        }
        if (isset($dados['credentials'])) {
            $dadosUpdate['credentials'] = $this->encryptCredentials($dados['credentials']);
        }
        if (isset($dados['ambiente'])) {
            $dadosUpdate['ambiente'] = $dados['ambiente'];
        }
        if (isset($dados['status'])) {
            $dadosUpdate['status'] = $dados['status'];
        }
        if (isset($dados['pix_enabled'])) {
            $dadosUpdate['pix_enabled'] = (int) $dados['pix_enabled'];
        }
        if (isset($dados['boleto_enabled'])) {
            $dadosUpdate['boleto_enabled'] = (int) $dados['boleto_enabled'];
        }
        if (isset($dados['credit_card_enabled'])) {
            $dadosUpdate['credit_card_enabled'] = (int) $dados['credit_card_enabled'];
        }
        if (isset($dados['debit_card_enabled'])) {
            $dadosUpdate['debit_card_enabled'] = (int) $dados['debit_card_enabled'];
        }
        if (isset($dados['webhook_url'])) {
            $dadosUpdate['webhook_url'] = $dados['webhook_url'];
        }
        if (isset($dados['webhook_secret'])) {
            $dadosUpdate['webhook_secret'] = $dados['webhook_secret'];
        }
        if (isset($dados['ordem'])) {
            $dadosUpdate['ordem'] = (int) $dados['ordem'];
        }
        if (isset($dados['currencies'])) {
            $currencies = $dados['currencies'];
            if (is_string($currencies)) {
                $currencies = json_decode($currencies, true) ?: ['BRL'];
            }
            $dadosUpdate['currencies'] = json_encode(array_values($currencies));
        }

        if (empty($dadosUpdate)) {
            return 0;
        }

        return $this->qb
            ->table('gateways_pagamento')
            ->where('id', '=', $id)
            ->update($dadosUpdate);
    }

    /**
     * Exclui gateway
     *
     * @param int $id
     * @return int Número de registros excluídos
     */
    public function excluir(int $id): int
    {
        return $this->qb
            ->table('gateways_pagamento')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Verifica se gateway existe e pertence ao tenant
     *
     * @param int $id
     * @return bool
     */
    public function existe(int $id): bool
    {
        return $this->qb
            ->table('gateways_pagamento')
            ->where('id', '=', $id)
            ->exists();
    }

    /**
     * Altera ordem de exibição
     *
     * @param int $id
     * @param int $novaOrdem
     * @return int
     */
    public function alterarOrdem(int $id, int $novaOrdem): int
    {
        return $this->qb
            ->table('gateways_pagamento')
            ->where('id', '=', $id)
            ->update(['ordem' => $novaOrdem]);
    }

    /**
     * Gera webhook URL única para o gateway
     *
     * @param int $id
     * @param string $gatewayCode
     * @return string
     */
    public function gerarWebhookUrl(int $id, string $gatewayCode): string
    {
        $baseUrl = Database::env('APP_URL', 'https://locadora.7carros.com');
        return "{$baseUrl}/webhook/{$gatewayCode}";
    }

    /**
     * Gera webhook secret
     *
     * @return string
     */
    public function gerarWebhookSecret(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Busca filiais vinculadas ao gateway
     *
     * @param int $gatewayId
     * @return array<int, array<string, mixed>>
     */
    public function buscarFiliais(int $gatewayId): array
    {
        return $this->qb
            ->table('gateways_filiais', 'gf')
            ->select([
                'gf.id_matriz_filial',
                'mf.nome_fantasia AS nome'
            ])
            ->leftJoin('matrizes_filiais', 'mf', 'gf.id_matriz_filial', '=', 'mf.id')
            ->where('gf.id_gateway', '=', $gatewayId)
            ->get();
    }

    /**
     * Sincroniza filiais do gateway
     *
     * @param int $gatewayId
     * @param array<int> $filiaisIds
     * @param string $chave
     * @return void
     */
    public function sincronizarFiliais(int $gatewayId, array $filiaisIds, string $chave): void
    {
        // Remover filiais antigas
        $this->qb
            ->table('gateways_filiais')
            ->where('id_gateway', '=', $gatewayId)
            ->delete();

        // Inserir novas filiais (chave eh adicionada automaticamente pelo QueryBuilder)
        foreach ($filiaisIds as $filialId) {
            if (!empty($filialId)) {
                $this->qb
                    ->table('gateways_filiais')
                    ->insert([
                        'id_gateway' => $gatewayId,
                        'id_matriz_filial' => (int) $filialId,
                    ]);
            }
        }
    }
}
