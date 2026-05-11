<?php
/**
 * Teste: login de cliente + check existe + calculo server-side do total.
 *
 * Execute: php tests/test_cliente_login.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('APP_ROOT')) define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/app/Helpers/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();

use App\Core\Database;
use App\Models\Cliente;
use App\Services\WebsiteReservaCalcService;

$CHAVE_TESTE = '1111111111111';
$_SESSION['chave'] = $CHAVE_TESTE;

$sucessos = 0; $falhas = 0;
function check(string $label, $atual, $esperado): bool {
    global $falhas, $sucessos;
    $ok = ($atual === $esperado);
    echo "   " . ($ok ? '✓ PASS' : '✗ FAIL') . " {$label} — esperado=" . var_export($esperado, true) . ", atual=" . var_export($atual, true) . "\n";
    if ($ok) $sucessos++; else $falhas++;
    return $ok;
}

echo "=== Teste cliente login + calculo server-side ===\n\n";

// 1. Template cliente_nova_senha existe
echo "1. Template cliente_nova_senha\n";
$t = Database::fetchAll("SELECT id FROM message_template_types WHERE slug = 'cliente_nova_senha'");
check('tipo cadastrado', count($t), 1);
$tpl = Database::fetchAll("
    SELECT mt.channel FROM message_templates mt
    JOIN message_template_types mtt ON mtt.id = mt.template_type_id
    WHERE mtt.slug = 'cliente_nova_senha' AND mt.chave = '0' AND mt.locale = 'pt_BR' AND mt.channel = 'email'
");
check('template email pt_BR existe', count($tpl), 1);
echo "\n";

// 2. Cliente::buscarPorUsuarioParaLogin com CPF de cliente existente (qualquer um do tenant de teste)
echo "2. Cliente::buscarPorUsuarioParaLogin\n";
$alguemCliente = Database::fetchAll(
    "SELECT id, cpf_cnpj FROM clientes WHERE chave = ? AND cpf_cnpj IS NOT NULL AND cpf_cnpj != '' LIMIT 1",
    [$CHAVE_TESTE]
);
if (empty($alguemCliente)) {
    echo "   (pulando — nenhum cliente com cpf_cnpj no tenant de teste)\n";
} else {
    $cpf = $alguemCliente[0]['cpf_cnpj'];
    $cm = new Cliente();
    $row = $cm->buscarPorUsuarioParaLogin($cpf);
    check('retorna cliente com senha field', is_array($row) && array_key_exists('senha', $row), true);
    check('login com doc inexistente retorna null', $cm->buscarPorUsuarioParaLogin('00000000000'), null);
}
echo "\n";

// 3. Calculo de total via WebsiteReservaCalcService
echo "3. WebsiteReservaCalcService::calcular\n";
$svc = new WebsiteReservaCalcService();

// Lê os valores atuais do BD para assertar (evita flakiness)
$precoKML = (float) Database::fetchAll(
    "SELECT valor_plano_km_livre v FROM grupos_precos_filiais WHERE chave=? AND id_grupo=1 AND id_matriz_filial=14 LIMIT 1",
    [$CHAVE_TESTE]
)[0]['v'] ?? 0;
$valorCad = (float) Database::fetchAll(
    "SELECT valor v FROM taxaseservicos_valores_filiais WHERE chave=? AND id_taxaservico=2167 AND id_matriz_filial=14 LIMIT 1",
    [$CHAVE_TESTE]
)[0]['v'] ?? 0;

// Cenario 1: filial 14, grupo 1, plano KML, 2 dias → preco × 2
$res1 = $svc->calcular([
    'filial_id' => 14, 'grupo_id' => 1, 'plano' => 'KML', 'dias' => 2, 'servicos' => [],
]);
echo "   Cenario 1 (KML × 2 = {$precoKML}×2): " . json_encode($res1) . "\n";
check('plano × dias', $res1['total'], $precoKML * 2);

// Cenario 2: com Cadeirinha (tipo_valor=MON, base_calculo=VLT — fixo, nao × dias)
$res2 = $svc->calcular([
    'filial_id' => 14, 'grupo_id' => 1, 'plano' => 'KML', 'dias' => 2, 'servicos' => [2167],
]);
$esperado2 = ($precoKML * 2) + $valorCad; // VLT nao multiplica
echo "   Cenario 2 (com Cadeirinha VLT): " . json_encode($res2['total']) . " (esperado {$esperado2})\n";
check('plano + servico MON/VLT unico', $res2['total'], $esperado2);

// Cenario 3: grupo/filial inexistente
$res3 = $svc->calcular(['filial_id' => 99999, 'grupo_id' => 99999, 'plano' => 'KML', 'dias' => 2]);
check('filial/grupo inexistente → total 0', $res3['total'], 0.0);

echo "\n=== RESUMO ===\n";
echo "Sucessos: {$sucessos}\nFalhas:   {$falhas}\n";
exit($falhas > 0 ? 1 : 0);
