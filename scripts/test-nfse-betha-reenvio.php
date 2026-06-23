<?php

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Helpers/helpers.php';

use App\Core\Database;
use App\Models\NFSe;
use App\Services\NFSe\NFSeService;

$id = 0;
$polls = 4;
$sleep = 30;
$chave = '';
$envFile = '';

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--id=')) {
        $id = (int) substr($arg, 5);
    }
    if (str_starts_with($arg, '--chave=')) {
        $chave = trim(substr($arg, 8));
    }
    if (str_starts_with($arg, '--polls=')) {
        $polls = max(0, (int) substr($arg, 8));
    }
    if (str_starts_with($arg, '--sleep=')) {
        $sleep = max(1, (int) substr($arg, 8));
    }
    if (str_starts_with($arg, '--env-file=')) {
        $envFile = trim(substr($arg, 11));
    }
}

if ($id <= 0) {
    fwrite(STDERR, "Uso: php scripts/test-nfse-betha-reenvio.php --id=ID --chave=TENANT [--env-file=temp-bd.txt] [--polls=4] [--sleep=30]\n");
    exit(1);
}

if ($chave === '') {
    fwrite(STDERR, "Informe --chave para aplicar o filtro tenant antes da busca.\n");
    exit(1);
}

if ($envFile !== '') {
    $path = realpath($envFile);
    if ($path === false || !is_file($path)) {
        fwrite(STDERR, "Arquivo de ambiente nao encontrado: {$envFile}\n");
        exit(1);
    }

    $config = [];
    $baseEnv = realpath(__DIR__ . '/../.env.production');
    foreach (array_filter([$baseEnv, $path]) as $arquivoConfig) {
        foreach (file($arquivoConfig, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $config[trim($key)] = trim(trim($value), '"\'');
        }
    }

    $reflection = new ReflectionClass(Database::class);
    $property = $reflection->getProperty('config');
    $property->setAccessible(true);
    $property->setValue(null, $config);
}

$_SESSION['chave'] = $chave;

$nfseModel = new NFSe();
$nfse = $nfseModel->buscarPorId($id);
if (!$nfse) {
    fwrite(STDERR, "NFS-e {$id} nao encontrada.\n");
    exit(1);
}

$service = new NFSeService();

echo "Reenviando NFS-e id={$id}, numero_atual=" . ($nfse['numero'] ?? '-') . ", tipo=" . ($nfse['tipo_emissao'] ?? '-') . "\n";
$resultado = $service->reenviar($id, $nfse['chave'], true);
echo json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

for ($i = 1; $i <= $polls; $i++) {
    $atual = $nfseModel->buscarPorId($id);
    echo "Status apos reenvio #{$i}: " . ($atual['status'] ?? '-') . ", numero=" . ($atual['numero'] ?? '-') . ", protocolo=" . ($atual['protocolo'] ?? '-') . "\n";

    if (($atual['status'] ?? '') !== 'processando') {
        echo "Motivo: " . ($atual['motivo_rejeicao'] ?? '') . "\n";
        break;
    }

    sleep($sleep);
    $consulta = $service->consultar($id, $nfse['chave']);
    echo json_encode($consulta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
}
