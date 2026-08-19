<?php

/**
 * Regressao: taxas obrigatorias do website por filial e protecao server-side.
 *
 * Execute: php tests/test_website_taxas_obrigatorias.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}
require_once APP_ROOT . '/app/Helpers/helpers.php';

use App\Core\Database;
use App\Models\TaxaServico;
use App\Services\WebsiteReservaCalcService;

if (session_status() === PHP_SESSION_NONE) {
    session_save_path(sys_get_temp_dir());
    session_start();
}

$falhas = 0;
$sucessos = 0;

function checkTaxaWebsite(string $label, mixed $atual, mixed $esperado): void
{
    global $falhas, $sucessos;
    $ok = $atual === $esperado;
    echo '   ' . ($ok ? 'PASS' : 'FAIL') . " {$label} - esperado="
        . var_export($esperado, true) . ', atual=' . var_export($atual, true) . PHP_EOL;
    $ok ? $sucessos++ : $falhas++;
}

$cenario = Database::fetchOne(
    "SELECT sc.chave, gpf.id_matriz_filial, gpf.id_grupo,
            (SELECT mf.id
             FROM matrizes_filiais mf
             WHERE mf.chave = sc.chave
               AND mf.id <> gpf.id_matriz_filial
             ORDER BY mf.id
             LIMIT 1) AS outra_filial
     FROM site_config sc
     INNER JOIN grupos_precos_filiais gpf ON gpf.chave = sc.chave
     WHERE gpf.valor_plano_km_livre > 0
       AND (SELECT COUNT(*) FROM matrizes_filiais mf2 WHERE mf2.chave = sc.chave) > 1
       AND NOT EXISTS (
           SELECT 1
           FROM taxaseservicos t
           INNER JOIN taxaseservicos_filiais tsf
               ON tsf.id_taxaservico = t.id AND tsf.chave = t.chave
           WHERE t.chave = sc.chave
             AND tsf.id_matriz_filial = gpf.id_matriz_filial
             AND t.aplicar = 'S'
             AND FIND_IN_SET('SITE', t.onde_usar)
       )
     LIMIT 1"
);

if (!$cenario) {
    fwrite(STDERR, "Nao ha tenant local com duas filiais e preco de grupo para o teste.\n");
    exit(1);
}

$chave = (string) $cenario['chave'];
$filialId = (int) $cenario['id_matriz_filial'];
$outraFilialId = (int) $cenario['outra_filial'];
$grupoId = (int) $cenario['id_grupo'];
$_SESSION['chave'] = $chave;

$idsCriados = [];

try {
    $criarTaxa = static function (
        string $nome,
        string $tipo,
        string $base,
        float $valor,
        string $aplicar,
        string $ondeUsar,
        int $filial
    ) use ($chave, &$idsCriados): int {
        $id = (int) Database::insert(
            'INSERT INTO taxaseservicos
                (chave, nome, base_calculo, tipo_valor, valor, aplicar, onde_usar)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$chave, $nome, $base, $tipo, $valor, $aplicar, $ondeUsar]
        );
        $idsCriados[] = $id;
        Database::execute(
            'INSERT INTO taxaseservicos_filiais
                (id_taxaservico, id_matriz_filial, chave)
             VALUES (?, ?, ?)',
            [$id, $filial, $chave]
        );
        if ($tipo === 'MON') {
            Database::execute(
                'INSERT INTO taxaseservicos_valores_filiais
                    (chave, id_taxaservico, id_matriz_filial, valor)
                 VALUES (?, ?, ?, ?)',
                [$chave, $id, $filial, $valor]
            );
        }
        return $id;
    };

    $sufixo = bin2hex(random_bytes(4));
    $idObrigatoria = $criarTaxa("Teste obrigatoria {$sufixo}", 'POR', 'VLT', 10, 'S', 'SITE', $filialId);
    $idOpcional = $criarTaxa("Teste opcional {$sufixo}", 'MON', 'PER', 5, 'N', 'SITE', $filialId);
    $idOutraFilial = $criarTaxa("Teste outra filial {$sufixo}", 'POR', 'VLT', 50, 'S', 'SITE', $outraFilialId);
    $idForaSite = $criarTaxa("Teste fora site {$sufixo}", 'POR', 'VLT', 90, 'S', 'SIS', $filialId);

    $model = new TaxaServico();
    $publicadas = $model->listarParaWebsite();
    $publicadasMap = array_column($publicadas, null, 'id');
    checkTaxaWebsite('payload publica aplicar=S', $publicadasMap[$idObrigatoria]['aplicar'] ?? null, 'S');
    checkTaxaWebsite('payload publica filiais_ids', $publicadasMap[$idObrigatoria]['filiais_ids'] ?? null, [$filialId]);
    checkTaxaWebsite('taxa fora do canal SITE nao e publicada', isset($publicadasMap[$idForaSite]), false);

    $calc = (new WebsiteReservaCalcService())->calcular([
        'filial_id' => $filialId,
        'grupo_id' => $grupoId,
        'plano' => 'KML',
        'dias' => 2,
        'servicos' => [$idOpcional, $idOutraFilial, $idForaSite],
        'seguro_carro' => false,
        'seguro_terceiros' => false,
    ]);
    $detalhes = array_column($calc['breakdown']['servicos'] ?? [], null, 'id');
    $subtotalPlano = (float) ($calc['breakdown']['plano']['subtotal'] ?? 0);

    checkTaxaWebsite('backend inclui obrigatoria omitida do payload', isset($detalhes[$idObrigatoria]), true);
    checkTaxaWebsite('backend mantem opcional solicitada', isset($detalhes[$idOpcional]), true);
    checkTaxaWebsite('backend ignora taxa de outra filial', isset($detalhes[$idOutraFilial]), false);
    checkTaxaWebsite('backend ignora taxa fora do SITE', isset($detalhes[$idForaSite]), false);
    checkTaxaWebsite('breakdown identifica obrigatoriedade', $detalhes[$idObrigatoria]['obrigatorio'] ?? null, true);
    checkTaxaWebsite('taxa percentual usa subtotal do plano', (float) ($detalhes[$idObrigatoria]['total'] ?? 0), round($subtotalPlano * 0.10, 2));
    checkTaxaWebsite('taxa monetaria PER usa valor da filial por dia', (float) ($detalhes[$idOpcional]['total'] ?? 0), 10.0);

    $semOpcional = (new WebsiteReservaCalcService())->calcular([
        'filial_id' => $filialId,
        'grupo_id' => $grupoId,
        'plano' => 'KML',
        'dias' => 2,
        'servicos' => [],
    ]);
    $semOpcionalMap = array_column($semOpcional['breakdown']['servicos'] ?? [], null, 'id');
    checkTaxaWebsite('payload vazio continua incluindo obrigatoria', isset($semOpcionalMap[$idObrigatoria]), true);
    checkTaxaWebsite('payload vazio nao inclui opcional', isset($semOpcionalMap[$idOpcional]), false);
} finally {
    if ($idsCriados !== []) {
        $placeholders = implode(',', array_fill(0, count($idsCriados), '?'));
        Database::execute("DELETE FROM taxaseservicos WHERE id IN ({$placeholders}) AND chave = ?", [...$idsCriados, $chave]);
    }
}

$template = file_get_contents(APP_ROOT . '/storage/templates/website/reserva.php');
$javascript = file_get_contents(APP_ROOT . '/storage/templates/website/assets/js/custom.js');
$controller = file_get_contents(APP_ROOT . '/app/Controllers/PublicWebsiteController.php');
checkTaxaWebsite('template expoe politica e filiais do servico', str_contains($template, 'data-aplicar=') && str_contains($template, 'data-filiais='), true);
checkTaxaWebsite('javascript bloqueia servico obrigatorio', str_contains($javascript, '.servico-item input[data-obrigatorio="1"]'), true);
checkTaxaWebsite('reserva persiste servicos do breakdown autoritativo', str_contains($controller, "\$calc['breakdown']['servicos']"), true);
checkTaxaWebsite('snapshot persiste valor total calculado', str_contains($controller, "'valor_total'    => (float) (\$servicoCalculado['total']"), true);

echo "\nSucessos: {$sucessos}\nFalhas: {$falhas}\n";
exit($falhas > 0 ? 1 : 0);
