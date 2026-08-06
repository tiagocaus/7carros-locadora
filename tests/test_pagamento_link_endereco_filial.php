#!/usr/bin/env php
<?php

/**
 * Regressão: matrizes_filiais não possui a coluna numero.
 *
 * O schema de matrizes_filiais armazena o número em `num`, enquanto o alias
 * normalizado consumido pelos gateways é `empresa_numero`.
 */

$source = file_get_contents(dirname(__DIR__) . '/app/Models/PagamentoLink.php');
if ($source === false) {
    fwrite(STDERR, "[FAIL] Não foi possível ler PagamentoLink.php.\n");
    exit(1);
}

if (str_contains($source, "'mf.numero AS empresa_numero'")) {
    fwrite(STDERR, "[FAIL] A consulta pública referencia matrizes_filiais.numero, coluna inexistente.\n");
    exit(1);
}

if (!str_contains($source, "'mf.num AS empresa_numero'")) {
    fwrite(STDERR, "[FAIL] O alias empresa_numero não usa matrizes_filiais.num.\n");
    exit(1);
}

echo "[OK] Consulta pública usa matrizes_filiais.num como empresa_numero.\n";
