#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Helpers\DateHelper;
use App\Models\Model;
use App\Services\FinanceiroCobrancaAutomaticaService;

function assertOverdueBilling(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$chave = '1111111111111';
$filialId = 14;
$clienteId = 1;
$mysqli = Model::sharedMysqli();
$mysqli->begin_transaction();

try {
    $_SESSION['chave'] = $chave;

    $stmt = $mysqli->prepare(
        'UPDATE matrizes_filiais
         SET notificacao_cobranca_vencida = ?
         WHERE chave = ? AND id = ?'
    );
    $habilitada = 'S';
    $stmt->bind_param('ssi', $habilitada, $chave, $filialId);
    $stmt->execute();
    assertOverdueBilling($stmt->affected_rows >= 0, 'Nao foi possivel preparar a filial de teste.');
    $stmt->close();

    $hoje = DateHelper::todayForDatabase();
    $ontem = DateHelper::addDaysForDatabase(-1);
    $amanha = DateHelper::addDaysForDatabase(1);

    $insert = $mysqli->prepare(
        'INSERT INTO financeiro
            (chave, tipo, pago, data_criada, data_venci, id_cliente, id_matriz_filial, codigo)
         VALUES (?, "R", "N", ?, ?, ?, ?, ?)'
    );

    $codigoVencida = 'TOV-' . bin2hex(random_bytes(4));
    $insert->bind_param('sssiis', $chave, $hoje, $ontem, $clienteId, $filialId, $codigoVencida);
    $insert->execute();
    $idVencida = (int) $insert->insert_id;

    $codigoPreVencimento = 'TPD-' . bin2hex(random_bytes(4));
    $insert->bind_param('sssiis', $chave, $hoje, $amanha, $clienteId, $filialId, $codigoPreVencimento);
    $insert->execute();
    $idPreVencimento = (int) $insert->insert_id;
    $insert->close();

    $service = new FinanceiroCobrancaAutomaticaService();
    $buscarVencidas = new ReflectionMethod($service, 'buscarFaturasVencidas');
    $buscarVencidas->setAccessible(true);
    $buscarPreVencimento = new ReflectionMethod($service, 'buscarFaturasPreVencimento');
    $buscarPreVencimento->setAccessible(true);

    $limiteReenvio = (new DateTimeImmutable($hoje))->modify('-7 days')->format('Y-m-d');
    $vencidasAtivas = $buscarVencidas->invoke($service, $chave, $hoje, $limiteReenvio);
    $preVencimentoAtivas = $buscarPreVencimento->invoke($service, $chave, $amanha);

    assertOverdueBilling(
        in_array($idVencida, array_map('intval', array_column($vencidasAtivas, 'id')), true),
        'Fatura vencida deveria ser elegivel com a opcao ativa.'
    );
    assertOverdueBilling(
        in_array($idPreVencimento, array_map('intval', array_column($preVencimentoAtivas, 'id')), true),
        'Fatura pre-vencimento deveria ser elegivel.'
    );

    $stmt = $mysqli->prepare(
        'UPDATE matrizes_filiais
         SET notificacao_cobranca_vencida = ?
         WHERE chave = ? AND id = ?'
    );
    $desabilitada = 'N';
    $stmt->bind_param('ssi', $desabilitada, $chave, $filialId);
    $stmt->execute();
    $stmt->close();

    $vencidasDesativadas = $buscarVencidas->invoke($service, $chave, $hoje, $limiteReenvio);
    $preVencimentoDesativadas = $buscarPreVencimento->invoke($service, $chave, $amanha);

    assertOverdueBilling(
        !in_array($idVencida, array_map('intval', array_column($vencidasDesativadas, 'id')), true),
        'Fatura vencida nao pode ser elegivel com a opcao desativada.'
    );
    assertOverdueBilling(
        in_array($idPreVencimento, array_map('intval', array_column($preVencimentoDesativadas, 'id')), true),
        'A opcao nao pode bloquear lembretes pre-vencimento.'
    );

    echo "OK: cobranca vencida automatica respeita a filial sem afetar o pre-vencimento.\n";
} finally {
    $mysqli->rollback();
    unset($_SESSION['chave']);
}
