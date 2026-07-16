<?php

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('APP_ROOT')) define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/app/Helpers/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_save_path(sys_get_temp_dir());
    session_start();
}

use App\Models\Model;
use App\Services\PromocaoAplicacaoService;

$db = Model::sharedMysqli();
$filial = $db->query("SELECT id, chave FROM matrizes_filiais WHERE chave <> '' ORDER BY id LIMIT 1")->fetch_assoc();
if (!$filial) {
    fwrite(STDERR, "SKIP: nenhuma filial disponivel.\n");
    exit(0);
}

$_SESSION['chave'] = $filial['chave'];
$codigo = 'TST' . strtoupper(substr(bin2hex(random_bytes(6)), 0, 10));
$falhas = 0;

function conferirPromo(string $rotulo, mixed $atual, mixed $esperado): void
{
    global $falhas;
    $ok = $atual === $esperado;
    echo ($ok ? 'PASS' : 'FAIL') . " {$rotulo}\n";
    if (!$ok) {
        echo '  esperado=' . var_export($esperado, true) . ' atual=' . var_export($atual, true) . "\n";
        $falhas++;
    }
}

$db->begin_transaction();
try {
    $stmt = $db->prepare("INSERT INTO promocoes (chave,codigo,nome,validade,dias,valor,tipo,onde_exibir,status) VALUES (?,?,?,NULL,2,10,'DPOR','SIS,SITE','A')");
    $nome = 'Promocao de teste';
    $stmt->bind_param('sss', $filial['chave'], $codigo, $nome);
    $stmt->execute();
    $promocaoId = $db->insert_id;

    $stmt = $db->prepare('INSERT INTO promocoes_filiais (id_promocao,id_matriz_filial,chave) VALUES (?,?,?)');
    $filialId = (int) $filial['id'];
    $stmt->bind_param('iis', $promocaoId, $filialId, $filial['chave']);
    $stmt->execute();

    $service = new PromocaoAplicacaoService();
    $percentual = $service->validarECalcular(strtolower($codigo), $filialId, 2, 100.00, 'SITE');
    conferirPromo('normaliza codigo', $percentual['codigo'], $codigo);
    conferirPromo('calcula percentual', $percentual['valor_desconto'], 10.0);
    conferirPromo('calcula total final', $percentual['total_final'], 90.0);

    try {
        $service->validarECalcular($codigo, $filialId, 1, 100.00, 'SITE');
        conferirPromo('bloqueia diarias insuficientes', false, true);
    } catch (InvalidArgumentException) {
        conferirPromo('bloqueia diarias insuficientes', true, true);
    }

    try {
        $service->validarECalcular($codigo, $filialId, 2, 100.00, 'APP');
        conferirPromo('bloqueia canal incorreto', false, true);
    } catch (InvalidArgumentException) {
        conferirPromo('bloqueia canal incorreto', true, true);
    }

    $stmt = $db->prepare('INSERT INTO grupos (chave,nome,descricao) VALUES (?, ?, ?)');
    $grupoPermitidoNome = 'Grupo promocao permitido';
    $grupoOutroNome = 'Grupo promocao bloqueado';
    $descricao = 'Fixture transacional';
    $stmt->bind_param('sss', $filial['chave'], $grupoPermitidoNome, $descricao);
    $stmt->execute();
    $grupoPermitidoId = $db->insert_id;
    $stmt->bind_param('sss', $filial['chave'], $grupoOutroNome, $descricao);
    $stmt->execute();
    $grupoOutroId = $db->insert_id;

    $db->query("UPDATE promocoes SET todos_grupos=0 WHERE id={$promocaoId}");
    $stmt = $db->prepare('INSERT INTO promocoes_grupos (chave,id_promocao,id_grupo) VALUES (?,?,?)');
    $stmt->bind_param('sii', $filial['chave'], $promocaoId, $grupoPermitidoId);
    $stmt->execute();

    $restrita = $service->validarECalcular($codigo, $filialId, 2, 100.00, 'SITE', $grupoPermitidoId);
    conferirPromo('aceita grupo vinculado', $restrita['valor_desconto'], 10.0);
    foreach ([0 => 'bloqueia contexto sem grupo', $grupoOutroId => 'bloqueia grupo nao vinculado'] as $grupoId => $rotulo) {
        try {
            $service->validarECalcular($codigo, $filialId, 2, 100.00, 'SITE', (int) $grupoId);
            conferirPromo($rotulo, false, true);
        } catch (InvalidArgumentException) {
            conferirPromo($rotulo, true, true);
        }
    }

    $grupoExternoChave = 'TENANT_EXTERNO_TESTE_PROMO';
    $grupoExternoNome = 'Grupo externo promocao';
    $stmt = $db->prepare('INSERT INTO grupos (chave,nome,descricao) VALUES (?, ?, ?)');
    $stmt->bind_param('sss', $grupoExternoChave, $grupoExternoNome, $descricao);
    $stmt->execute();
    $grupoExternoId = $db->insert_id;
    $stmt = $db->prepare('INSERT INTO promocoes_grupos (chave,id_promocao,id_grupo) VALUES (?,?,?)');
    $stmt->bind_param('sii', $filial['chave'], $promocaoId, $grupoExternoId);
    $stmt->execute();
    try {
        $service->validarECalcular($codigo, $filialId, 2, 100.00, 'SITE', $grupoExternoId);
        conferirPromo('bloqueia grupo de outro tenant', false, true);
    } catch (InvalidArgumentException) {
        conferirPromo('bloqueia grupo de outro tenant', true, true);
    }

    $db->query("UPDATE promocoes SET tipo='DFIX', valor=150 WHERE id={$promocaoId}");
    $stmt = $db->prepare('INSERT INTO promocoes_valores_filiais (chave,id_promocao,id_matriz_filial,valor) VALUES (?,?,?,150)');
    $stmt->bind_param('sii', $filial['chave'], $promocaoId, $filialId);
    $stmt->execute();
    $fixa = $service->validarECalcular($codigo, $filialId, 2, 100.00, 'SIS', $grupoPermitidoId);
    conferirPromo('limita desconto fixo ao total', $fixa['valor_desconto'], 100.0);

    $db->rollback();
} catch (Throwable $e) {
    $db->rollback();
    fwrite(STDERR, 'ERRO: ' . $e->getMessage() . "\n");
    exit(1);
}

exit($falhas > 0 ? 1 : 0);
