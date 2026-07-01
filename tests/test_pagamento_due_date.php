#!/usr/bin/env php
<?php

/**
 * Regressao: vencimento DATE do financeiro nao pode voltar um dia por timezone.
 *
 * Uso: php tests/test_pagamento_due_date.php
 */

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/app/Helpers/helpers.php';

use App\Helpers\DateHelper;

date_default_timezone_set('Europe/Lisbon');

$cases = [
    ['due' => '2026-07-02', 'today' => '2026-07-01', 'expected' => '2026-07-02', 'label' => 'futura mantem vencimento real'],
    ['due' => '2026-07-01', 'today' => '2026-07-01', 'expected' => '2026-07-01', 'label' => 'hoje mantem hoje'],
    ['due' => '2026-06-30', 'today' => '2026-07-01', 'expected' => '2026-07-01', 'label' => 'vencida usa hoje'],
    ['due' => '', 'today' => '2026-07-01', 'expected' => '2026-07-01', 'label' => 'vazia usa hoje'],
    ['due' => 'invalida', 'today' => '2026-07-01', 'expected' => '2026-07-01', 'label' => 'invalida usa hoje'],
    ['due' => '2026-02-30', 'today' => '2026-07-01', 'expected' => '2026-07-01', 'label' => 'data impossivel usa hoje'],
    ['due' => '2026-07-02 00:00:00', 'today' => '2026-07-01', 'expected' => '2026-07-02', 'label' => 'datetime local preserva data civil'],
];

foreach ($cases as $case) {
    $actual = DateHelper::normalizeDueDateForGateway($case['due'], $case['today']);
    if ($actual !== $case['expected']) {
        fwrite(STDERR, "[FAIL] {$case['label']}: esperado {$case['expected']}, obtido {$actual}\n");
        exit(1);
    }
}

echo "[OK] normalizeDueDateForGateway preserva DATE de financeiro e aplica fallback correto.\n";
