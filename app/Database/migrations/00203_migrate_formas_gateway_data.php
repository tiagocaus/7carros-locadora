<?php

/**
 * Migration 00203: Migrar dados de formas_gateway para gateways_pagamento
 *
 * Migra os 129 registros existentes da tabela legada formas_gateway
 * para a nova estrutura gateways_pagamento, convertendo:
 * - Asaas (98 registros) → asaas
 * - Gerencianet (23 registros) → efipay
 * - Stripe (4 registros) → stripe
 * - Square (4 registros) → square
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Verificar se as tabelas existem
        if (!$this->tableExists('formas_gateway') || !$this->tableExists('gateways_pagamento')) {
            return;
        }

        // Buscar todos os registros de formas_gateway
        $registros = $this->db()
            ->table('formas_gateway')
            ->withoutChave()
            ->get();

        if (empty($registros)) {
            return;
        }

        foreach ($registros as $reg) {
            // Decodificar JSON de configuração
            $config = json_decode($reg['array'] ?? '{}', true) ?? [];

            // Mapear gateway_code
            $gatewayCode = match (strtolower($reg['nome'] ?? '')) {
                'gerencianet' => 'efipay',
                'asaas' => 'asaas',
                'stripe' => 'stripe',
                'square' => 'square',
                default => strtolower($reg['nome'] ?? 'unknown')
            };

            // Mapear status (A=Ativo, D/outros=Inativo)
            $status = ($config['status'] ?? 'D') === 'A' ? 'A' : 'I';

            // Mapear ambiente
            $sandboxValue = $config['sandbox'] ?? $config['ambiente'] ?? 'sandbox';
            $ambiente = ($sandboxValue === 'producao' || $sandboxValue === 'production')
                ? 'production'
                : 'sandbox';

            // Mapear métodos de pagamento
            $pixEnabled = ($config['pix'] ?? 'false') === 'true' ? 1 : 0;
            $boletoEnabled = ($config['boleto'] ?? 'false') === 'true' ? 1 : 0;
            $creditCardEnabled = (
                ($config['cartaocredito'] ?? 'false') === 'true' ||
                ($config['cartao'] ?? 'false') === 'true'
            ) ? 1 : 0;

            // Extrair apenas credenciais (remover campos de configuração de métodos)
            $configKeys = ['status', 'sandbox', 'ambiente', 'pix', 'boleto', 'cartaocredito', 'cartao'];
            $credentials = array_diff_key($config, array_flip($configKeys));

            // Criptografar credenciais
            $encryptedCredentials = !empty($credentials) ? $this->encrypt(json_encode($credentials)) : null;

            // Verificar se já existe registro com mesma chave+gateway+filial
            $exists = $this->db()
                ->table('gateways_pagamento')
                ->withoutChave()
                ->where('chave', '=', $reg['chave'])
                ->where('gateway_code', '=', $gatewayCode)
                ->where('id_matriz_filial', $reg['id_matriz_filial'] ? '=' : 'IS', $reg['id_matriz_filial'])
                ->first();

            if ($exists) {
                // Pular se já existe
                continue;
            }

            // Inserir na nova tabela
            $this->db()
                ->table('gateways_pagamento')
                ->withoutChave()
                ->insert([
                    'chave' => $reg['chave'],
                    'id_matriz_filial' => $reg['id_matriz_filial'],
                    'gateway_code' => $gatewayCode,
                    'nome' => $reg['nome'],
                    'credentials' => $encryptedCredentials,
                    'ambiente' => $ambiente,
                    'status' => $status,
                    'pix_enabled' => $pixEnabled,
                    'boleto_enabled' => $boletoEnabled,
                    'credit_card_enabled' => $creditCardEnabled,
                    'debit_card_enabled' => 0,
                    'ordem' => 0,
                    'created_at' => $reg['created_at'] ?? date('Y-m-d H:i:s'),
                ]);
        }
    }

    public function down(): void
    {
        // Não é seguro reverter automaticamente
        // Os dados originais permanecem em formas_gateway
    }

    /**
     * Criptografa dados usando AES-256-CBC
     * Mesmo método usado em GatewayPagamento model
     */
    private function encrypt(string $data): string
    {
        $appKey = \App\Core\Database::env('APP_KEY', 'default-key-change-me');
        $key = hash('sha256', $appKey, true);
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $encrypted);
    }
};
