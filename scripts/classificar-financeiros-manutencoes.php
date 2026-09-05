<?php

/**
 * Classifica lancamentos financeiros historicos vinculados a manutencoes.
 *
 * Padrao: somente previa.
 * Previa global: php scripts/classificar-financeiros-manutencoes.php --env=production --all-tenants
 * Aplicacao global: adicione os planos, --apply e --confirm=CLASSIFICAR_FINANCEIROS_MANUTENCOES_TODOS
 * Previa individual: php scripts/classificar-financeiros-manutencoes.php --env=production --tenant=CHAVE
 *
 * O script nao altera planos_de_contas. Apenas usa planos existentes para
 * preencher classificacoes nulas em financeiro e financeiro_itens.
 */

$ambiente = 'development';
$tenant = '';
$hierarquiaDespesa = '';
$hierarquiaReceita = '';
$aplicar = in_array('--apply', $argv, true);
$todosTenants = in_array('--all-tenants', $argv, true);
$prefixoTenantTeste = '';
$confirmacao = '';

foreach ($argv as $argumento) {
    if (str_starts_with($argumento, '--env=')) {
        $ambiente = substr($argumento, strlen('--env='));
    } elseif (str_starts_with($argumento, '--tenant=')) {
        $tenant = trim(substr($argumento, strlen('--tenant=')));
    } elseif (str_starts_with($argumento, '--plano-despesa=')) {
        $hierarquiaDespesa = trim(substr($argumento, strlen('--plano-despesa=')));
    } elseif (str_starts_with($argumento, '--plano-receita=')) {
        $hierarquiaReceita = trim(substr($argumento, strlen('--plano-receita=')));
    } elseif (str_starts_with($argumento, '--confirm=')) {
        $confirmacao = trim(substr($argumento, strlen('--confirm=')));
    } elseif (str_starts_with($argumento, '--tenant-prefix=')) {
        $prefixoTenantTeste = trim(substr($argumento, strlen('--tenant-prefix=')));
    }
}

if (!in_array($ambiente, ['development', 'production'], true)) {
    fwrite(STDERR, "Ambiente invalido. Use development ou production.\n");
    exit(1);
}

if ($todosTenants && $tenant !== '') {
    fwrite(STDERR, "Use apenas uma opcao: --tenant=CHAVE ou --all-tenants.\n");
    exit(1);
}

if (!$todosTenants && $tenant === '') {
    fwrite(STDERR, "Informe --tenant=CHAVE ou use --all-tenants para a previa global.\n");
    exit(1);
}

if ($prefixoTenantTeste !== '' && ($ambiente !== 'development' || !$todosTenants)) {
    fwrite(STDERR, "--tenant-prefix e exclusivo para testes com --env=development --all-tenants.\n");
    exit(1);
}

if ($aplicar) {
    $confirmacaoEsperada = $todosTenants
        ? 'CLASSIFICAR_FINANCEIROS_MANUTENCOES_TODOS'
        : 'CLASSIFICAR_FINANCEIROS_MANUTENCOES';

    if ($confirmacao !== $confirmacaoEsperada) {
        fwrite(STDERR, "Para aplicar, informe --confirm={$confirmacaoEsperada}.\n");
        exit(1);
    }
}

$_ENV['APP_ENV'] = $ambiente;
putenv("APP_ENV={$ambiente}");

require dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}
require_once APP_ROOT . '/app/Helpers/helpers.php';

use App\Core\Database;
use App\Helpers\DateHelper;
use App\Models\Model;

$mysqli = Model::sharedMysqli();
$host = strtolower(trim((string) Database::env('DB_HOST', '')));
if ($ambiente === 'production' && !in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
    fwrite(STDERR, "Producao exige DB_HOST local. Host configurado: {$host}\n");
    Model::closeConnection();
    exit(1);
}

if ($todosTenants) {
    $filtroPrefixoManutencao = $prefixoTenantTeste !== '' ? ' AND m.chave LIKE ?' : '';
    $filtroPrefixoItem = $prefixoTenantTeste !== '' ? ' AND mi.chave LIKE ?' : '';
    $filtroPrefixoFinanceiro = $prefixoTenantTeste !== '' ? ' WHERE f.chave LIKE ?' : '';
    $sqlPreviaGlobal = "
        SELECT DISTINCT f.id, f.chave, f.tipo, f.valor_total, f.id_plano_de_conta
        FROM financeiro f
        INNER JOIN (
            SELECT m.chave, m.id_financeiro_principal AS id_financeiro
            FROM manutencoes m
            WHERE m.id_financeiro_principal IS NOT NULL{$filtroPrefixoManutencao}
            UNION
            SELECT mi.chave, mi.id_financeiro
            FROM manutencoes_itens mi
            WHERE mi.id_financeiro IS NOT NULL{$filtroPrefixoItem}
        ) origem ON origem.chave = f.chave
            AND (f.id = origem.id_financeiro OR f.id_financeiro_origem = origem.id_financeiro)
        {$filtroPrefixoFinanceiro}
        ORDER BY f.chave, f.id
    ";

    if ($prefixoTenantTeste !== '') {
        $padraoPrefixo = addcslashes($prefixoTenantTeste, '\\%_') . '%';
        $stmt = $mysqli->prepare($sqlPreviaGlobal);
        $stmt->bind_param('sss', $padraoPrefixo, $padraoPrefixo, $padraoPrefixo);
        $stmt->execute();
        $resultado = $stmt->get_result();
    } else {
        $resultado = $mysqli->query($sqlPreviaGlobal);
    }
    $financeirosVinculados = $resultado->fetch_all(MYSQLI_ASSOC);
    if (isset($stmt)) {
        $stmt->close();
    }
    $pendencias = array_values(array_filter(
        $financeirosVinculados,
        static fn (array $financeiro): bool => empty($financeiro['id_plano_de_conta'])
    ));
    $resumosPorTenant = [];
    $totalGeral = ['D' => ['quantidade' => 0, 'valor' => 0.0], 'R' => ['quantidade' => 0, 'valor' => 0.0]];

    foreach ($pendencias as $pendencia) {
        $chave = (string) $pendencia['chave'];
        $tipo = ($pendencia['tipo'] ?? '') === 'R' ? 'R' : 'D';
        $valor = (float) ($pendencia['valor_total'] ?? 0);

        if (!isset($resumosPorTenant[$chave])) {
            $resumosPorTenant[$chave] = [
                'D' => ['quantidade' => 0, 'valor' => 0.0],
                'R' => ['quantidade' => 0, 'valor' => 0.0],
            ];
        }

        $resumosPorTenant[$chave][$tipo]['quantidade']++;
        $resumosPorTenant[$chave][$tipo]['valor'] += $valor;
        $totalGeral[$tipo]['quantidade']++;
        $totalGeral[$tipo]['valor'] += $valor;
    }

    echo "AMBIENTE | {$ambiente}\n";
    echo $aplicar ? "MODO APLICACAO GLOBAL\n" : "MODO PREVIA GLOBAL (nenhuma gravacao)\n";

    if (empty($resumosPorTenant)) {
        echo "NENHUM_FINANCEIRO_DE_MANUTENCAO_SEM_PLANO\n";
    } else {
        foreach ($resumosPorTenant as $chave => $resumoTenant) {
            printf(
                "TENANT | %s | despesas_registros=%d | despesas_valor=%.2f | receitas_registros=%d | receitas_valor=%.2f\n",
                $chave,
                $resumoTenant['D']['quantidade'],
                $resumoTenant['D']['valor'],
                $resumoTenant['R']['quantidade'],
                $resumoTenant['R']['valor']
            );
        }
    }

    printf(
        "TOTAL | tenants=%d | despesas_registros=%d | despesas_valor=%.2f | receitas_registros=%d | receitas_valor=%.2f\n",
        count($resumosPorTenant),
        $totalGeral['D']['quantidade'],
        $totalGeral['D']['valor'],
        $totalGeral['R']['quantidade'],
        $totalGeral['R']['valor']
    );

    if (!$aplicar || empty($financeirosVinculados)) {
        Model::closeConnection();
        exit(0);
    }

    if ($hierarquiaDespesa === '' || $hierarquiaReceita === '') {
        fwrite(STDERR, "A aplicacao global exige --plano-despesa e --plano-receita.\n");
        Model::closeConnection();
        exit(1);
    }

    $buscarPlanoGlobal = static function (string $hierarquia, string $tipo) use ($mysqli): ?array {
        $stmt = $mysqli->prepare("
            SELECT id, chave, hierarquia, tipo
            FROM planos_de_contas
            WHERE hierarquia = ? AND tipo = ? AND chave = '0'
            LIMIT 1
        ");
        $stmt->bind_param('ss', $hierarquia, $tipo);
        $stmt->execute();
        $plano = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        return $plano;
    };

    $planoDespesa = $buscarPlanoGlobal($hierarquiaDespesa, 'D');
    $planoReceita = $buscarPlanoGlobal($hierarquiaReceita, 'R');

    if (!$planoDespesa || !$planoReceita) {
        fwrite(STDERR, "Os planos informados devem existir como planos globais e possuir os tipos D e R correspondentes.\n");
        Model::closeConnection();
        exit(1);
    }

    printf("PLANO_DESPESA | id=%d | hierarquia=%s\n", $planoDespesa['id'], $planoDespesa['hierarquia']);
    printf("PLANO_RECEITA | id=%d | hierarquia=%s\n", $planoReceita['id'], $planoReceita['hierarquia']);

    $atualizarFinanceiro = $mysqli->prepare("
        UPDATE financeiro
        SET id_plano_de_conta = ?, updated_at = ?
        WHERE id = ? AND chave = ? AND id_plano_de_conta IS NULL
    ");
    $atualizarItens = $mysqli->prepare("
        UPDATE financeiro_itens
        SET id_plano_de_conta = ?, updated_at = ?
        WHERE id_financeiro = ? AND chave = ? AND id_plano_de_conta IS NULL
    ");

    $atualizadosPorTenant = [];
    $financeirosAtualizados = 0;
    $itensAtualizados = 0;
    $mysqli->begin_transaction();

    try {
        foreach ($financeirosVinculados as $financeiro) {
            $chaveFinanceiro = (string) $financeiro['chave'];
            $tipo = ($financeiro['tipo'] ?? '') === 'R' ? 'R' : 'D';
            $idPlanoAtual = (int) ($financeiro['id_plano_de_conta'] ?? 0);
            $idPlano = $idPlanoAtual > 0
                ? $idPlanoAtual
                : (int) ($tipo === 'R' ? $planoReceita['id'] : $planoDespesa['id']);
            $idFinanceiro = (int) $financeiro['id'];
            $updatedAt = DateHelper::systemNow();

            if (!isset($atualizadosPorTenant[$chaveFinanceiro])) {
                $atualizadosPorTenant[$chaveFinanceiro] = ['financeiros' => 0, 'itens' => 0];
            }

            if ($idPlanoAtual <= 0) {
                $atualizarFinanceiro->bind_param('isis', $idPlano, $updatedAt, $idFinanceiro, $chaveFinanceiro);
                $atualizarFinanceiro->execute();
                $financeirosAtualizados += $atualizarFinanceiro->affected_rows;
                $atualizadosPorTenant[$chaveFinanceiro]['financeiros'] += $atualizarFinanceiro->affected_rows;
            }

            $atualizarItens->bind_param('isis', $idPlano, $updatedAt, $idFinanceiro, $chaveFinanceiro);
            $atualizarItens->execute();
            $itensAtualizados += $atualizarItens->affected_rows;
            $atualizadosPorTenant[$chaveFinanceiro]['itens'] += $atualizarItens->affected_rows;
        }

        $mysqli->commit();
    } catch (Throwable $e) {
        $mysqli->rollback();
        $atualizarFinanceiro->close();
        $atualizarItens->close();
        Model::closeConnection();
        fwrite(STDERR, "ERRO | operacao global revertida | {$e->getMessage()}\n");
        exit(1);
    }

    $atualizarFinanceiro->close();
    $atualizarItens->close();

    foreach ($atualizadosPorTenant as $chaveFinanceiro => $quantidades) {
        if ($quantidades['financeiros'] === 0 && $quantidades['itens'] === 0) {
            continue;
        }
        printf(
            "APLICADO_TENANT | %s | financeiros=%d | itens=%d\n",
            $chaveFinanceiro,
            $quantidades['financeiros'],
            $quantidades['itens']
        );
    }

    printf(
        "APLICADO_TOTAL | tenants=%d | financeiros=%d | itens=%d\n",
        count(array_filter(
            $atualizadosPorTenant,
            static fn (array $quantidades): bool => $quantidades['financeiros'] > 0 || $quantidades['itens'] > 0
        )),
        $financeirosAtualizados,
        $itensAtualizados
    );

    Model::closeConnection();
    exit(0);
}

$buscarPlano = static function (string $hierarquia, string $tipo) use ($mysqli, $tenant): ?array {
    if ($hierarquia === '') {
        return null;
    }

    $stmt = $mysqli->prepare("
        SELECT id, chave, hierarquia, tipo
        FROM planos_de_contas
        WHERE hierarquia = ?
          AND tipo = ?
          AND chave IN ('0', ?)
        ORDER BY (chave = ?) DESC
        LIMIT 1
    ");
    $stmt->bind_param('ssss', $hierarquia, $tipo, $tenant, $tenant);
    $stmt->execute();
    $plano = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $plano;
};

$sqlCandidatos = "
    SELECT DISTINCT f.id, f.tipo, f.valor_total, f.id_plano_de_conta
    FROM financeiro f
    INNER JOIN (
        SELECT m.chave, m.id_financeiro_principal AS id_financeiro
        FROM manutencoes m
        WHERE m.chave = ? AND m.id_financeiro_principal IS NOT NULL
        UNION
        SELECT mi.chave, mi.id_financeiro
        FROM manutencoes_itens mi
        WHERE mi.chave = ? AND mi.id_financeiro IS NOT NULL
    ) origem ON origem.chave = f.chave
        AND (f.id = origem.id_financeiro OR f.id_financeiro_origem = origem.id_financeiro)
    WHERE f.chave = ?
    ORDER BY f.id
";

$stmt = $mysqli->prepare($sqlCandidatos);
$stmt->bind_param('sss', $tenant, $tenant, $tenant);
$stmt->execute();
$financeirosVinculados = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$candidatos = array_values(array_filter(
    $financeirosVinculados,
    static fn (array $financeiro): bool => empty($financeiro['id_plano_de_conta'])
));

$resumo = ['D' => ['quantidade' => 0, 'valor' => 0.0], 'R' => ['quantidade' => 0, 'valor' => 0.0]];
foreach ($candidatos as $candidato) {
    $tipo = ($candidato['tipo'] ?? '') === 'R' ? 'R' : 'D';
    $resumo[$tipo]['quantidade']++;
    $resumo[$tipo]['valor'] += (float) ($candidato['valor_total'] ?? 0);
}

$planoDespesa = $buscarPlano($hierarquiaDespesa, 'D');
$planoReceita = $buscarPlano($hierarquiaReceita, 'R');

echo "AMBIENTE | {$ambiente}\n";
echo "TENANT | {$tenant}\n";
echo $aplicar ? "MODO APLICACAO\n" : "MODO PREVIA (nenhuma gravacao)\n";
printf("DESPESAS_SEM_PLANO | registros=%d | valor=%.2f | plano=%s\n", $resumo['D']['quantidade'], $resumo['D']['valor'], $planoDespesa['hierarquia'] ?? 'nao informado/encontrado');
printf("RECEITAS_SEM_PLANO | registros=%d | valor=%.2f | plano=%s\n", $resumo['R']['quantidade'], $resumo['R']['valor'], $planoReceita['hierarquia'] ?? 'nao informado/encontrado');

if (!$aplicar || (empty($candidatos) && empty($financeirosVinculados))) {
    Model::closeConnection();
    exit(0);
}

if (($resumo['D']['quantidade'] > 0 && !$planoDespesa) || ($resumo['R']['quantidade'] > 0 && !$planoReceita)) {
    fwrite(STDERR, "Informe planos existentes e compativeis para todos os tipos encontrados antes de aplicar.\n");
    Model::closeConnection();
    exit(1);
}

$atualizarFinanceiro = $mysqli->prepare("
    UPDATE financeiro
    SET id_plano_de_conta = ?, updated_at = ?
    WHERE id = ? AND chave = ? AND id_plano_de_conta IS NULL
");
$atualizarItens = $mysqli->prepare("
    UPDATE financeiro_itens
    SET id_plano_de_conta = ?, updated_at = ?
    WHERE id_financeiro = ? AND chave = ? AND id_plano_de_conta IS NULL
");

$financeirosAtualizados = 0;
$itensAtualizados = 0;
$mysqli->begin_transaction();

try {
    foreach ($financeirosVinculados as $financeiro) {
        $tipo = ($financeiro['tipo'] ?? '') === 'R' ? 'R' : 'D';
        $idPlanoAtual = (int) ($financeiro['id_plano_de_conta'] ?? 0);
        $idPlano = $idPlanoAtual > 0
            ? $idPlanoAtual
            : (int) ($tipo === 'R' ? $planoReceita['id'] : $planoDespesa['id']);
        $idFinanceiro = (int) $financeiro['id'];
        $updatedAt = DateHelper::systemNow();

        if ($idPlanoAtual <= 0) {
            $atualizarFinanceiro->bind_param('isis', $idPlano, $updatedAt, $idFinanceiro, $tenant);
            $atualizarFinanceiro->execute();
            $financeirosAtualizados += $atualizarFinanceiro->affected_rows;
        }

        $atualizarItens->bind_param('isis', $idPlano, $updatedAt, $idFinanceiro, $tenant);
        $atualizarItens->execute();
        $itensAtualizados += $atualizarItens->affected_rows;
    }

    $mysqli->commit();
} catch (Throwable $e) {
    $mysqli->rollback();
    $atualizarFinanceiro->close();
    $atualizarItens->close();
    Model::closeConnection();
    fwrite(STDERR, "ERRO | operacao revertida | {$e->getMessage()}\n");
    exit(1);
}

$atualizarFinanceiro->close();
$atualizarItens->close();
printf("APLICADO | financeiros=%d | itens=%d\n", $financeirosAtualizados, $itensAtualizados);
Model::closeConnection();
