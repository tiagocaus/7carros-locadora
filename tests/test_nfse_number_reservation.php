<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\NFSeConfiguracao;
use App\Services\NFSe\NFSeAssinatura;
use App\Services\NFSe\NFSeService;

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . " Esperado: " . var_export($expected, true) . " Obtido: " . var_export($actual, true));
    }
}

function assertTrueValue(bool $value, string $message): void
{
    if (!$value) {
        throw new RuntimeException($message);
    }
}

class FakeNFSeConfiguracaoReserva extends NFSeConfiguracao
{
    public int $consultas = 0;
    public int $reservas = 0;

    public function __construct(
        private array $numeros,
        private array $resultadosReserva
    ) {
    }

    public function consultarProximoNumero(int $idMatrizFilial, ?string $chave = null): int
    {
        $this->consultas++;
        return array_shift($this->numeros);
    }

    public function reservarNumero(int $idMatrizFilial, int $numero, ?string $chave = null): bool
    {
        $this->reservas++;
        return array_shift($this->resultadosReserva);
    }
}

class FakeNFSeAssinaturaReserva extends NFSeAssinatura
{
    public int $assinaturas = 0;

    public function __construct(private bool $sucesso = true)
    {
    }

    public function assinar(
        string $xml,
        string $certPath,
        string $keyPath,
        string $tagToSign,
        string $algoritmo = 'sha256',
        string $idAttribute = 'Id'
    ): array {
        $this->assinaturas++;
        if (!$this->sucesso) {
            return ['sucesso' => false, 'xml' => '', 'mensagem' => 'Falha fake de assinatura.'];
        }

        return ['sucesso' => true, 'xml' => $xml . '<!-- assinado -->', 'mensagem' => ''];
    }
}

function invocarPrepararXML(FakeNFSeConfiguracaoReserva $configModel, FakeNFSeAssinaturaReserva $assinatura, array $dados): array
{
    $service = new NFSeService();

    $configProp = new ReflectionProperty(NFSeService::class, 'configModel');
    $configProp->setAccessible(true);
    $configProp->setValue($service, $configModel);

    $assinaturaProp = new ReflectionProperty(NFSeService::class, 'assinatura');
    $assinaturaProp->setAccessible(true);
    $assinaturaProp->setValue($service, $assinatura);

    $method = new ReflectionMethod(NFSeService::class, 'prepararXMLAssinado');
    $method->setAccessible(true);

    return $method->invokeArgs($service, [
        'betha',
        &$dados,
        ['id_matriz_filial' => 1428],
        'tenant-teste',
        ['certPath' => '/tmp/cert.pem', 'keyPath' => '/tmp/key.pem'],
    ]);
}

$dadosBase = [
    'ambiente' => 1,
    'serie' => '1',
    'data_emissao' => '2026-06-19T15:25:59-03:00',
    'data_competencia' => '2026-06-19',
    'municipio_codigo' => '4204558',
    'prestador' => [
        'cnpj' => '35706623000169',
        'inscricao_municipal' => '7138',
        'regime_tributario' => 1,
        'reg_apuracao_sn' => 1,
        'enviar_im' => 'S',
    ],
    'tomador' => [
        'cpf_cnpj' => '02656748000172',
        'nome' => 'Clyde Industries Brasil Ltda',
        'endereco' => [],
    ],
    'servico' => [
        'codigo' => '999999999',
        'descricao' => 'Locacao',
    ],
    'valores' => [
        'servicos' => 2490.00,
        'deducoes' => 0,
        'base_calculo' => 2490.00,
        'aliquota_iss' => 0,
        'valor_iss' => 0,
        'trib_issqn' => 4,
        'iss_retido' => 'N',
    ],
];

$dadosInvalidos = $dadosBase;
$dadosInvalidos['municipio_codigo'] = '123';
$config = new FakeNFSeConfiguracaoReserva([10], [true]);
$assinatura = new FakeNFSeAssinaturaReserva();
$resultado = invocarPrepararXML($config, $assinatura, $dadosInvalidos);
assertSameValue(false, $resultado['sucesso'], 'Validacao invalida deve falhar.');
assertSameValue(0, $config->reservas, 'Validacao invalida nao pode reservar numero.');
assertSameValue(0, $assinatura->assinaturas, 'Validacao invalida nao pode assinar XML.');

$config = new FakeNFSeConfiguracaoReserva([10], [true]);
$assinatura = new FakeNFSeAssinaturaReserva(false);
$resultado = invocarPrepararXML($config, $assinatura, $dadosBase);
assertSameValue(false, $resultado['sucesso'], 'Assinatura invalida deve falhar.');
assertSameValue(0, $config->reservas, 'Falha de assinatura nao pode reservar numero.');
assertSameValue(1, $assinatura->assinaturas, 'Assinatura deve ter sido tentada uma vez.');

$config = new FakeNFSeConfiguracaoReserva([10, 11], [false, true]);
$assinatura = new FakeNFSeAssinaturaReserva();
$resultado = invocarPrepararXML($config, $assinatura, $dadosBase);
assertSameValue(true, $resultado['sucesso'], 'Reserva deve suceder apos concorrencia.');
assertSameValue(2, $config->reservas, 'Concorrencia deve tentar reservar novamente.');
assertSameValue(2, $assinatura->assinaturas, 'XML deve ser refeito e assinado novamente apos concorrencia.');
assertSameValue(11, $resultado['numero'], 'Numero final deve ser o numero efetivamente reservado.');
assertTrueValue(str_contains($resultado['xml'], '000000000000011'), 'XML final deve conter o numero efetivamente reservado.');

echo "Teste de reserva de numero NFS-e passou.\n";
