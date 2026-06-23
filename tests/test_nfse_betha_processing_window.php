<?php

$source = file_get_contents(__DIR__ . '/../app/Models/NFSe.php');
if ($source === false) {
    throw new RuntimeException('Nao foi possivel ler app/Models/NFSe.php.');
}

$expectedExpression = "COALESCE(updated_at, created_at)";
$expectedWhere = '>= DATE_SUB(NOW(), INTERVAL 48 HOUR)';
$expectedOrderByRaw = 'orderByRaw';

if (!str_contains($source, $expectedExpression) || !str_contains($source, $expectedWhere)) {
    throw new RuntimeException('Busca Betha em processamento deve filtrar por updated_at/created_at recente.');
}

if (!str_contains($source, $expectedOrderByRaw)) {
    throw new RuntimeException('Busca Betha em processamento deve ordenar por updated_at/created_at.');
}

echo "Teste da janela de consulta Betha passou.\n";
