#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

function assertSemContatoLegado(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$appRoot = dirname(__DIR__) . '/app';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($appRoot));
$violacoes = [];

foreach ($iterator as $arquivo) {
    if (!$arquivo->isFile() || $arquivo->getExtension() !== 'php') {
        continue;
    }

    $path = $arquivo->getPathname();
    if (str_contains($path, '/Database/migrations/') || str_ends_with($path, '/Models/MatrizFilial.php')) {
        continue;
    }

    $source = (string) file_get_contents($path);
    if (preg_match('/\$(?:empresa|matriz|filial|tenant|empresaMatriz|empresaRaw|f)\[[\'\"](?:fixo|celular)[\'\"]\]/', $source)) {
        $violacoes[] = $path;
    }
    if (preg_match('/\b(?:mf|matrizes_filiais)\.(?:email|fixo|celular)\b/', $source)) {
        $violacoes[] = $path;
    }
}

$controller = (string) file_get_contents($appRoot . '/Controllers/MatrizFilialController.php');
$migration = (string) file_get_contents(
    $appRoot . '/Database/migrations/00417_drop_legacy_contact_columns_from_matrizes_filiais.php'
);
assertSemContatoLegado(
    !str_contains($controller, "request->input('telefone_fixo')")
        && !str_contains($controller, "request->input('celular')")
        && !str_contains($controller, "request->input('email')"),
    'MatrizFilialController voltou a gravar colunas legadas de contato.'
);

assertSemContatoLegado(
    $violacoes === [],
    "Codigo de runtime ainda usa contato legado de matriz/filial:\n" . implode("\n", array_unique($violacoes))
);

foreach (['fixo', 'celular', 'email'] as $column) {
    assertSemContatoLegado(
        str_contains($migration, "dropColumnIfExists('matrizes_filiais', \$column)"),
        "A migration 00417 nao remove a coluna matrizes_filiais.{$column}."
    );
}
assertSemContatoLegado(
    str_contains($migration, '00416_backfill_legacy_matriz_filial_contacts.php')
        && str_contains($migration, 'validarContatosLegadosPreservados'),
    'A migration 00417 nao protege o backfill anterior contra perda de dados.'
);

echo "OK: codigo de runtime sem leituras ou escritas de contatos legados de matriz/filial.\n";
