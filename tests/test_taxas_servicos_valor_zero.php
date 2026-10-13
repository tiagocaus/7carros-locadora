<?php

/**
 * Regressao do CRUD com valores zero. Executar com PHP CLI no localhost.
 * As duas conexoes usadas pelo fluxo sao revertidas ao final.
 */
namespace App\Core {
    // Captura as respostas sem encerrar o processo de teste.
    class Response
    {
        public static array $data = [];
        public static int $status = 200;

        public static function json(mixed $data, int $statusCode = 200): void
        {
            self::$data = $data;
            self::$status = $statusCode;
        }
    }
}

namespace {
    require __DIR__ . '/../vendor/autoload.php';
    define('APP_ROOT', dirname(__DIR__));
    require APP_ROOT . '/app/Helpers/helpers.php';

    use App\Controllers\TaxasServicosController;
    use App\Core\Database;
    use App\Core\Request;
    use App\Core\Response;
    use App\Models\Model;
    use App\Models\TaxaServico;

    $_ENV['APP_ENV'] = 'development';
    if (!is_file(APP_ROOT . '/.env.development') || Database::env('DB_HOST') !== 'localhost') {
        throw new RuntimeException('Este teste exige o banco localhost de .env.development.');
    }
    session_save_path(sys_get_temp_dir());
    session_start();
    $_SESSION = ['authenticated' => true, 'chave' => '1111111111111'];
    $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';

    function checkTaxaZero(bool $ok, string $message): void
    {
        if (!$ok) {
            throw new RuntimeException($message);
        }
    }

    $db = Model::sharedMysqli();
    $pdo = Database::getConnection();
    $db->begin_transaction();
    $pdo->beginTransaction();

    try {
        $filiais = $db->query(
            "SELECT id FROM matrizes_filiais WHERE chave = '1111111111111' ORDER BY id LIMIT 2"
        )->fetch_all(MYSQLI_ASSOC);
        checkTaxaZero(count($filiais) === 2, 'O tenant de teste precisa de duas filiais.');
        [$filialGratis, $filialPaga] = array_map('intval', array_column($filiais, 'id'));
        $controller = new TaxasServicosController();
        $model = new TaxaServico();
        $nome = 'TESTE-ZERO-' . bin2hex(random_bytes(6));
        $base = [
            'nome' => $nome,
            'tipo_valor' => 'MON',
            'base_calculo' => 'FIX',
            'filiais_ids' => json_encode([$filialGratis, $filialPaga]),
            'valores_filiais' => [$filialGratis => '0,00', $filialPaga => 0],
            'aplicar' => 'N',
            'onde_usar' => 'SIS',
        ];
        $enviar = static function (array $dados, ?int $id = null) use ($controller): array {
            $_POST = $dados;
            Response::$data = [];
            Response::$status = 200;
            $request = new Request();
            $id === null ? $controller->store($request) : $controller->update($request, $id);
            return Response::$data;
        };

        $resposta = $enviar($base);
        checkTaxaZero(($resposta['success'] ?? false) === true, 'Cadastro monetario zero falhou: ' . json_encode($resposta));
        $id = (int) $resposta['data']['id'];
        $taxa = $model->buscarPorId($id);
        checkTaxaZero((float) $taxa['valor'] === 0.0, 'O fallback deve ser zero quando todas as filiais forem gratuitas.');
        checkTaxaZero($taxa['valores_filiais'][$filialGratis] === 0.0, 'O cadastro deve guardar zero por filial.');

        $misto = $base;
        $misto['valores_filiais'][$filialPaga] = '25,50';
        checkTaxaZero($enviar($misto, $id)['success'] === true, 'Edicao com filial gratuita e paga falhou.');
        $taxa = $model->buscarPorId($id);
        checkTaxaZero((float) $taxa['valor'] === 25.5, 'O fallback deve usar o primeiro valor positivo.');
        checkTaxaZero($model->resolverValor($taxa, $filialGratis) === 0.0, 'Zero oficial nao pode usar o fallback pago.');
        checkTaxaZero($model->resolverValor($taxa, $filialPaga) === 25.5, 'O valor pago deve ser preservado.');
        $lista = $model->listarPaginado(1, 10, $nome, 'id_matriz_filial = ?', [$filialGratis]);
        checkTaxaZero(count($lista) === 1 && (float) $lista[0]['valor'] === 0.0, 'Listagem da filial gratuita deve mostrar zero.');
        $lista = $model->listarPaginado(1, 10, $nome);
        checkTaxaZero((float) $lista[0]['valor'] === 25.5, 'Listagem mista deve manter o primeiro valor positivo.');
        $select = array_column($model->listarParaSelect('1111111111111', $nome, $filialGratis), null, 'id');
        checkTaxaZero((float) $select[$id]['valor'] === 0.0, 'O select deve preservar a gratuidade da filial.');

        checkTaxaZero($enviar($base, $id)['success'] === true, 'Edicao de valor positivo para zero falhou.');
        checkTaxaZero((float) $model->buscarPorId($id)['valor'] === 0.0, 'Edicao para todas gratuitas deve zerar o fallback.');

        $percentual = $base;
        $percentual['nome'] .= '-POR';
        $percentual['tipo_valor'] = 'POR';
        $percentual['valor'] = '0,00';
        unset($percentual['valores_filiais']);
        $resposta = $enviar($percentual);
        checkTaxaZero($resposta['success'] === true, 'Cadastro percentual zero falhou.');
        $idPercentual = (int) $resposta['data']['id'];
        $percentual['valor'] = '2,50';
        checkTaxaZero($enviar($percentual, $idPercentual)['success'] === true, 'Edicao percentual positivo falhou.');
        $percentual['valor'] = 0;
        checkTaxaZero($enviar($percentual, $idPercentual)['success'] === true, 'Edicao percentual para zero falhou.');
        checkTaxaZero((float) $model->buscarPorId($idPercentual)['valor'] === 0.0, 'Percentual zero deve persistir.');

        foreach ([null, '', ' ', -1, '-0,01', 'abc', '1,2,3', [], false] as $invalido) {
            foreach ([null, $id] as $alvo) {
                $dados = $base;
                $dados['valores_filiais'][$filialGratis] = $invalido;
                $enviar($dados, $alvo);
                checkTaxaZero(Response::$status === 400, 'Valor monetario invalido deve ser rejeitado.');
            }
            foreach ([null, $idPercentual] as $alvo) {
                $dados = $percentual;
                $dados['valor'] = $invalido;
                $enviar($dados, $alvo);
                checkTaxaZero(Response::$status === 400, 'Percentual invalido deve ser rejeitado.');
            }
        }
        $semValor = $base;
        unset($semValor['valores_filiais'][$filialGratis]);
        $enviar($semValor);
        checkTaxaZero(Response::$status === 400, 'Valor de filial ausente deve ser rejeitado.');
        unset($percentual['valor']);
        $enviar($percentual, $idPercentual);
        checkTaxaZero(Response::$status === 400, 'Percentual ausente deve ser rejeitado.');

        echo "OK: cadastro, edicao, valores por filial, listagem e rejeicao de valores invalidos.\n";
    } finally {
        $db->rollback();
        $pdo->rollBack();
        session_destroy();
    }
}
