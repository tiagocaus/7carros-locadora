<?php

/**
 * Audita e gera comissoes ausentes de recebimentos de contratos/locacoes.
 *
 * Padrao: somente previa.
 * Producao:  php scripts/reparar-comissoes-investidores.php --env=production
 * Contrato:  php scripts/reparar-comissoes-investidores.php --env=production --contract=CODIGO
 * Aplicacao: php scripts/reparar-comissoes-investidores.php --env=production --apply --confirm=GERAR_COMISSOES_INVESTIDORES
 */

$ambiente = 'development';
$tenantFiltro = null;
$contratoFiltro = null;
$dataInicio = null;
$dataFim = null;
$confirmacao = null;
$aplicar = in_array('--apply', $argv, true);

foreach ($argv as $argumento) {
    if (str_starts_with($argumento, '--env=')) {
        $ambiente = trim(substr($argumento, strlen('--env=')));
    } elseif (str_starts_with($argumento, '--tenant=')) {
        $tenantFiltro = trim(substr($argumento, strlen('--tenant=')));
    } elseif (str_starts_with($argumento, '--contract=')) {
        $contratoFiltro = trim(substr($argumento, strlen('--contract=')));
    } elseif (str_starts_with($argumento, '--since=')) {
        $dataInicio = trim(substr($argumento, strlen('--since=')));
    } elseif (str_starts_with($argumento, '--until=')) {
        $dataFim = trim(substr($argumento, strlen('--until=')));
    } elseif (str_starts_with($argumento, '--confirm=')) {
        $confirmacao = trim(substr($argumento, strlen('--confirm=')));
    }
}

if (!in_array($ambiente, ['development', 'production'], true)) {
    fwrite(STDERR, "Ambiente invalido: {$ambiente}. Use development ou production.\n");
    exit(1);
}

$validarData = static function (?string $data, string $opcao): void {
    if ($data === null || $data === '') {
        return;
    }

    $objeto = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
    if (!$objeto || $objeto->format('Y-m-d') !== $data) {
        fwrite(STDERR, "Data invalida em {$opcao}: {$data}. Use YYYY-MM-DD.\n");
        exit(1);
    }
};

$validarData($dataInicio, '--since');
$validarData($dataFim, '--until');
if ($dataInicio !== null && $dataFim !== null && $dataInicio > $dataFim) {
    fwrite(STDERR, "O periodo e invalido: --since nao pode ser posterior a --until.\n");
    exit(1);
}

if ($aplicar && $confirmacao !== 'GERAR_COMISSOES_INVESTIDORES') {
    fwrite(STDERR, "Confirmacao obrigatoria: --confirm=GERAR_COMISSOES_INVESTIDORES\n");
    exit(1);
}

$_ENV['APP_ENV'] = $ambiente;
putenv("APP_ENV={$ambiente}");

require dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}
require_once APP_ROOT . '/app/Helpers/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_save_path(sys_get_temp_dir());
    session_start();
}

use App\Core\Database;
use App\Models\Model;
use App\Services\ComissaoInvestidorService;

$mysqli = Model::sharedMysqli();
$nomeBanco = (string) Database::env('DB_DATABASE', 'nao informado');
$hostBanco = strtolower(trim((string) Database::env('DB_HOST', '')));

if ($ambiente === 'production' && !in_array($hostBanco, ['localhost', '127.0.0.1', '::1'], true)) {
    fwrite(STDERR, "Producao exige DB_HOST local. Host configurado: {$hostBanco}\n");
    Model::closeConnection();
    exit(1);
}

$sql = <<<'SQL'
SELECT
    f.id,
    f.chave,
    f.codigo,
    f.data_pago,
    f.valor_total,
    f.id_contrato,
    f.id_locacao
FROM financeiro f
WHERE f.tipo = 'R'
  AND f.pago = 'S'
  AND (f.id_contrato IS NOT NULL OR f.id_locacao IS NOT NULL)
  AND NOT EXISTS (
      SELECT 1
      FROM comissoes_investidores ci
      WHERE ci.chave = f.chave
        AND ci.id_financeiro_origem = f.id
  )
  AND (
      EXISTS (
          SELECT 1
          FROM contratos_veiculos cv
          JOIN veiculos v ON v.id = cv.id_veiculo AND v.chave = cv.chave
          JOIN fornecedores fo ON fo.id = v.id_fornecedor AND fo.chave = v.chave
          WHERE cv.id_contrato = f.id_contrato
            AND cv.chave = f.chave
            AND fo.investidor = 1
      )
      OR EXISTS (
          SELECT 1
          FROM locacoes_veiculos lv
          JOIN veiculos v ON v.id = lv.id_veiculo AND v.chave = lv.chave
          JOIN fornecedores fo ON fo.id = v.id_fornecedor AND fo.chave = v.chave
          WHERE lv.id_locacao = f.id_locacao
            AND lv.chave = f.chave
            AND fo.investidor = 1
      )
  )
SQL;

$tipos = '';
$parametros = [];
if ($tenantFiltro !== null && $tenantFiltro !== '') {
    $sql .= ' AND f.chave = ?';
    $tipos .= 's';
    $parametros[] = $tenantFiltro;
}
if ($contratoFiltro !== null && $contratoFiltro !== '') {
    $sql .= ' AND EXISTS (SELECT 1 FROM contratos c WHERE c.id = f.id_contrato AND c.chave = f.chave AND c.codigo = ?)';
    $tipos .= 's';
    $parametros[] = $contratoFiltro;
}
if ($dataInicio !== null && $dataInicio !== '') {
    $sql .= ' AND f.data_pago >= ?';
    $tipos .= 's';
    $parametros[] = $dataInicio;
}
if ($dataFim !== null && $dataFim !== '') {
    $sql .= ' AND f.data_pago <= ?';
    $tipos .= 's';
    $parametros[] = $dataFim;
}
$sql .= ' ORDER BY f.chave, f.id';

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    fwrite(STDERR, "Erro ao preparar auditoria: {$mysqli->error}\n");
    Model::closeConnection();
    exit(1);
}
if ($parametros) {
    $stmt->bind_param($tipos, ...$parametros);
}
$stmt->execute();
$candidatos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo "AMBIENTE | {$ambiente}\n";
echo "BANCO | {$nomeBanco}\n";
echo $aplicar ? "MODO APLICACAO\n" : "MODO PREVIA (nenhuma gravacao)\n";
if ($tenantFiltro) {
    echo "TENANT | {$tenantFiltro}\n";
}
if ($contratoFiltro) {
    echo "CONTRATO | {$contratoFiltro}\n";
}
if ($dataInicio || $dataFim) {
    echo 'PERIODO | ' . ($dataInicio ?: '-') . ' ate ' . ($dataFim ?: '-') . "\n";
}

$service = null;
$tenantAtual = null;
$elegiveis = 0;
$geradas = 0;
$ignoradas = 0;
$totalBase = 0.0;
$totalRepasse = 0.0;
$motivos = [];

foreach ($candidatos as $candidato) {
    $chave = (string) $candidato['chave'];
    if ($tenantAtual !== $chave) {
        $_SESSION['chave'] = $chave;
        $_SESSION['user_id'] = 0;
        $_SESSION['user_name'] = 'Reparo de comissoes investidores';
        $service = new ComissaoInvestidorService();
        $tenantAtual = $chave;
    }

    $idFinanceiro = (int) $candidato['id'];
    $analise = $service->analisarComissaoPorFinanceiro($idFinanceiro, false);
    if (!$analise['aplicavel']) {
        $motivo = (string) $analise['motivo'];
        $motivos[$motivo] = ($motivos[$motivo] ?? 0) + 1;
        $ignoradas++;
        printf("IGNORADO | tenant=%s | financeiro=%d | motivo=%s\n", $chave, $idFinanceiro, $motivo);
        continue;
    }

    $dados = $analise['dados_comissao'];
    $elegiveis++;
    $totalBase += (float) $dados['valor_base'];
    $totalRepasse += (float) $dados['valor_repasse_investidor'];
    printf(
        "ELEGIVEL | tenant=%s | financeiro=%d | origem=%s | contrato=%s | locacao=%s | data=%s | base=%.2f | repasse=%.2f\n",
        $chave,
        $idFinanceiro,
        $dados['tipo_origem'],
        $dados['id_contrato'] ?: '-',
        $dados['id_locacao'] ?: '-',
        $dados['data_referencia'],
        (float) $dados['valor_base'],
        (float) $dados['valor_repasse_investidor']
    );

    if (!$aplicar) {
        continue;
    }

    $revalidacao = $service->analisarComissaoPorFinanceiro($idFinanceiro, false);
    if (!$revalidacao['aplicavel']) {
        $motivo = (string) $revalidacao['motivo'];
        $motivos[$motivo] = ($motivos[$motivo] ?? 0) + 1;
        $ignoradas++;
        printf("NAO_APLICADO | tenant=%s | financeiro=%d | motivo=%s\n", $chave, $idFinanceiro, $motivo);
        continue;
    }

    $idComissao = $service->processarComissaoPorFinanceiro($idFinanceiro, false, false);
    if (!$idComissao) {
        $aposFalha = $service->analisarComissaoPorFinanceiro($idFinanceiro, false);
        if (($aposFalha['motivo'] ?? '') === 'comissao_ja_existente') {
            $ignoradas++;
            printf("NAO_APLICADO | tenant=%s | financeiro=%d | motivo=comissao_ja_existente\n", $chave, $idFinanceiro);
            continue;
        }

        fwrite(STDERR, "ERRO | tenant={$chave} | financeiro={$idFinanceiro} | comissao nao foi criada\n");
        Model::closeConnection();
        exit(1);
    }

    $geradas++;
    printf("APLICADO | tenant=%s | financeiro=%d | comissao=%d\n", $chave, $idFinanceiro, $idComissao);
}

printf(
    "TOTAL | candidatos=%d | elegiveis=%d | ignorados=%d | geradas=%d | base=%.2f | repasse=%.2f\n",
    count($candidatos),
    $elegiveis,
    $ignoradas,
    $geradas,
    $totalBase,
    $totalRepasse
);
foreach ($motivos as $motivo => $quantidade) {
    printf("MOTIVO | %s=%d\n", $motivo, $quantidade);
}

Model::closeConnection();
exit(0);
