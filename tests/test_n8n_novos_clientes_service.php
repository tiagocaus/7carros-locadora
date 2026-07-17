<?php

/**
 * Testa a preparacao dos contatos do n8n sem enviar mensagens ou alterar o BD.
 *
 * Execute: php tests/test_n8n_novos_clientes_service.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\N8nCliente;
use App\Services\N8nNovosClientesService;

function assertN8n(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertN8nInvalid(callable $callback): void
{
    try {
        $callback();
    } catch (InvalidArgumentException) {
        return;
    }

    throw new RuntimeException('Era esperada uma InvalidArgumentException.');
}

$model = new class extends N8nCliente {
    public function __construct()
    {
    }

    public function listarProprietariosAtivosComContato(): array
    {
        return [
            [
                'id' => 10,
                'chave' => 'TENANT-A',
                'tel_cel' => '+55 (11) 99999-0001',
                'email' => ' proprietario1@example.com ',
                'empresa_created_at' => '2026-07-16 10:00:00',
            ],
            [
                'id' => 11,
                'chave' => 'TENANT-A',
                'tel_cel' => '+55 (11) 99999-0002',
                'email' => 'proprietario2@example.com',
                'empresa_created_at' => '2026-07-16 10:00:00',
            ],
            [
                'id' => 20,
                'chave' => 'TENANT-B',
                'tel_cel' => '(21) 98888-0000',
                'email' => 'fora-do-periodo@example.com',
                'empresa_created_at' => '2026-07-10 10:00:00',
            ],
        ];
    }
};

$resolver = static fn(int $dia): string => match ($dia) {
    1 => '2026-07-16',
    5 => '2026-07-12',
    default => '1900-01-01',
};

$service = new N8nNovosClientesService($model, $resolver);
$resultado = $service->listar('1, 5,1');

assertN8n(count($resultado) === 2, 'Deve retornar todos os Proprietarios do tenant no periodo.');
assertN8n($resultado[0] === [
    'id' => 10,
    'chave' => 'TENANT-A',
    'tel_cel' => '5511999990001',
    'email' => 'proprietario1@example.com',
], 'O primeiro contato nao foi normalizado corretamente.');
assertN8n($resultado[1]['id'] === 11, 'O segundo Proprietario do tenant deve ser mantido.');
assertN8n($service->normalizarDias('1,5,1') === [1, 5], 'Intervalos repetidos devem ser removidos.');

foreach ([null, '', '0', '-1', '1,abc', '1,,5', []] as $invalido) {
    assertN8nInvalid(fn() => $service->normalizarDias($invalido));
}

assertN8nInvalid(fn() => $service->normalizarDias(implode(',', range(1, 51))));
assertN8nInvalid(fn() => $service->normalizarDias('36501'));
assertN8n(!isset($_SESSION['chave']), 'O contexto de tenant deve ser restaurado ao terminar.');

echo "OK: n8n valida intervalos, filtra pela empresa e normaliza todos os Proprietarios.\n";
