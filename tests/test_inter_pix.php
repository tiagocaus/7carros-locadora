#!/usr/bin/env php
<?php

/**
 * Teste Diagnóstico - PIX Banco Inter (Cobrança v3)
 *
 * Reproduz EXATAMENTE o fluxo de createPixCharge() do InterGateway
 * para identificar qual campo está causando o erro "Não foi possível converter o valor."
 *
 * Uso: php tests/test_inter_pix.php
 */

echo "\n========================================\n";
echo "  TESTE DIAGNOSTICO - PIX BANCO INTER\n";
echo "========================================\n\n";

// --- Bootstrap ---
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/app/Helpers/helpers.php';

// Carregar .env
foreach (['.env.development', '.env'] as $envFile) {
    $envPath = APP_ROOT . '/' . $envFile;
    if (file_exists($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                [$name, $value] = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value, '"\'');
                if (!empty($name)) {
                    putenv("$name=$value");
                    $_ENV[$name] = $value;
                }
            }
        }
        echo "[OK] Arquivo {$envFile} carregado\n";
        break;
    }
}

// --- Credenciais ---
$clientId = env('INTER_CLIENT_ID', '');
$clientSecret = env('INTER_CLIENT_SECRET', '');
$certPath = env('INTER_CERT_PATH', '');
$keyPath = env('INTER_KEY_PATH', '');
$pixKey = env('INTER_PIX_KEY', '');
$contaCorrente = env('INTER_CONTA_CORRENTE', '');
$ambiente = env('INTER_AMBIENTE', 'sandbox');
$baseUrl = env('INTER_BASE_URL', '') ?: 'https://cdpj.partners.bancointer.com.br';

// Resolver caminhos relativos
if ($certPath && !str_starts_with($certPath, '/')) {
    $certPath = APP_ROOT . '/' . $certPath;
}
if ($keyPath && !str_starts_with($keyPath, '/')) {
    $keyPath = APP_ROOT . '/' . $keyPath;
}

echo "\n--- CONFIGURACAO ---\n";
echo "Ambiente: {$ambiente}\n";
echo "Base URL: {$baseUrl}\n";
echo "Client ID: " . substr($clientId, 0, 8) . "...\n";
echo "Conta Corrente: {$contaCorrente}\n";
echo "Cert existe: " . (file_exists($certPath) ? 'SIM' : "NAO ({$certPath})") . "\n";
echo "Key existe: " . (file_exists($keyPath) ? 'SIM' : "NAO ({$keyPath})") . "\n";
echo "PIX Key: {$pixKey}\n";

if (!file_exists($certPath) || !file_exists($keyPath)) {
    echo "\n[ERRO] Certificados nao encontrados!\n";
    exit(1);
}

// --- Obter Token OAuth ---
echo "\n--- OBTENDO TOKEN ---\n";

$authUrl = $baseUrl . '/oauth/v2/token';
$authData = http_build_query([
    'grant_type' => 'client_credentials',
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'scope' => 'boleto-cobranca.write boleto-cobranca.read',
]);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $authUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $authData,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json',
    ],
    CURLOPT_SSLCERT => $certPath,
    CURLOPT_SSLKEY => $keyPath,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_TIMEOUT => 30,
]);

$authResponse = curl_exec($ch);
$authError = curl_error($ch);
$authHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($authError) {
    echo "[ERRO] cURL auth: {$authError}\n";
    exit(1);
}

$authDecoded = json_decode($authResponse, true);
echo "HTTP Code: {$authHttpCode}\n";

if (empty($authDecoded['access_token'])) {
    echo "[ERRO] Nao obteve token!\n";
    echo "Resposta: {$authResponse}\n";
    exit(1);
}

$token = $authDecoded['access_token'];
$scopes = $authDecoded['scope'] ?? '';
echo "[OK] Token obtido\n";
echo "Scopes: {$scopes}\n";

// --- Construir Payload EXATO do createPixCharge ---
echo "\n--- CONSTRUINDO PAYLOAD ---\n";

$testValor = 100.0; // Valor de teste: R$ 100,00
$testCpf = '00000000000'; // CPF fallback usado quando empresa nao tem
$testNome = 'Teste 7Carros';
$testEmail = 'teste@7carros.com';

$vencimento = date('Y-m-d', strtotime('+3 days'));

$payload = [
    'seuNumero' => substr(preg_replace('/[^a-zA-Z0-9]/', '', uniqid('pix', true)), 0, 15),
    'valorNominal' => (float) $testValor, // FLOAT - mesmo que o codigo atual
    'dataVencimento' => $vencimento,
    'numDiasAgenda' => 3,
];

$payload['pagador'] = [
    'cpfCnpj' => $testCpf,
    'nome' => $testNome,
    'tipoPessoa' => 'FISICA',
];
$payload['pagador']['email'] = $testEmail;

$payload['desconto'] = ['codigoDesconto' => 'NAOTEMDESCONTO'];
$payload['multa'] = ['codigoMulta' => 'NAOTEMMULTA'];
$payload['mora'] = ['codigoMora' => 'ISENTO'];

// Mostrar payload EXATAMENTE como será enviado via json_encode
$jsonPayload = json_encode($payload, JSON_PRESERVE_ZERO_FRACTION | JSON_PRETTY_PRINT);
echo "\nPayload JSON que sera enviado:\n";
echo $jsonPayload . "\n";

// Mostrar tipos PHP de cada campo
echo "\n--- TIPOS PHP ---\n";
echo "valorNominal: " . gettype($payload['valorNominal']) . " = " . var_export($payload['valorNominal'], true) . "\n";
echo "numDiasAgenda: " . gettype($payload['numDiasAgenda']) . " = " . var_export($payload['numDiasAgenda'], true) . "\n";
echo "seuNumero: " . gettype($payload['seuNumero']) . " = " . var_export($payload['seuNumero'], true) . "\n";
echo "dataVencimento: " . gettype($payload['dataVencimento']) . " = " . var_export($payload['dataVencimento'], true) . "\n";
echo "cpfCnpj: " . gettype($payload['pagador']['cpfCnpj']) . " = " . var_export($payload['pagador']['cpfCnpj'], true) . "\n";

// --- TESTE CORRIGIDO: sem desconto/multa/mora + pagador completo ---
echo "\n========================================\n";
echo "TESTE FINAL: Sem desconto/multa/mora + pagador com endereco\n";
echo "========================================\n";
$payloadFix = [
    'seuNumero' => substr(preg_replace('/[^a-zA-Z0-9]/', '', uniqid('pix', true)), 0, 15),
    'valorNominal' => (float) $testValor,
    'dataVencimento' => $vencimento,
    'numDiasAgenda' => 3,
    'pagador' => [
        'cpfCnpj' => '33683111000107',
        'nome' => 'Empresa Teste 7Carros',
        'tipoPessoa' => 'JURIDICA',
        'endereco' => 'Rua Teste 123',
        'cidade' => 'Vila Velha',
        'uf' => 'ES',
        'cep' => '29119021',
        'email' => $testEmail,
    ],
];
$resultFix = enviarCobranca($baseUrl, $payloadFix, $token, $certPath, $keyPath, $contaCorrente, JSON_PRESERVE_ZERO_FRACTION);

echo "\n========================================\n";
echo "RESULTADO: " . ($resultFix ? 'SUCESSO' : 'FALHA') . "\n";
echo "========================================\n\n";

/**
 * Envia cobrança e mostra resultado detalhado
 */
function enviarCobranca(string $baseUrl, array $payload, string $token, string $certPath, string $keyPath, string $contaCorrente, int $jsonFlags): bool
{
    $url = $baseUrl . '/cobranca/v3/cobrancas';
    $jsonPayload = json_encode($payload, $jsonFlags);

    echo "URL: {$url}\n";
    echo "JSON enviado: {$jsonPayload}\n";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $jsonPayload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            "Authorization: Bearer {$token}",
            "x-conta-corrente: {$contaCorrente}",
        ],
        CURLOPT_SSLCERT => $certPath,
        CURLOPT_SSLKEY => $keyPath,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "HTTP Code: {$httpCode}\n";

    if ($error) {
        echo "cURL Error: {$error}\n";
        return false;
    }

    $decoded = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300 && !empty($decoded['codigoSolicitacao'])) {
        echo "[SUCESSO] codigoSolicitacao: {$decoded['codigoSolicitacao']}\n";
        if (!empty($decoded['pixCopiaECola'])) {
            echo "PIX Code: " . substr($decoded['pixCopiaECola'], 0, 50) . "...\n";
        }
        return true;
    }

    echo "[FALHA] Resposta completa:\n";
    echo json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

    // Detalhar CADA violação com propriedade
    if (!empty($decoded['violacoes'])) {
        echo "\n--- VIOLACOES DETALHADAS ---\n";
        foreach ($decoded['violacoes'] as $i => $v) {
            echo "Violacao #{$i}:\n";
            echo "  propriedade: " . ($v['propriedade'] ?? 'N/A') . "\n";
            echo "  razao: " . ($v['razao'] ?? 'N/A') . "\n";
            echo "  motivo: " . ($v['motivo'] ?? 'N/A') . "\n";
            echo "  valor: " . ($v['valor'] ?? 'N/A') . "\n";
            // Mostrar TODOS os campos da violação
            foreach ($v as $key => $val) {
                if (!in_array($key, ['propriedade', 'razao', 'motivo', 'valor'])) {
                    echo "  {$key}: " . var_export($val, true) . "\n";
                }
            }
        }
    }

    return false;
}
