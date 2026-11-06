<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\NFSe\NFSeContextoFiscal;
use App\Services\NFSe\Nacional\NFSeXMLNacional;
use App\Services\NFSe\Betha\NFSeXMLBetha;

function assertContextoFiscal(bool $condicao, string $mensagem): void
{
    if (!$condicao) {
        throw new RuntimeException($mensagem);
    }
}

function bloqueiaContextoFiscal(callable $operacao): void
{
    try {
        $operacao();
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException('Operação ambígua deveria ser bloqueada.');
}

assertContextoFiscal(!NFSeContextoFiscal::exigeNacional(1, '2026-10-31'), 'ME/EPP anterior ao corte.');
assertContextoFiscal(NFSeContextoFiscal::exigeNacional(1, '2026-11-01'), 'ME/EPP no corte.');
assertContextoFiscal(!NFSeContextoFiscal::exigeNacional(2, '2026-11-01'), 'Presumido preserva emissor.');
assertContextoFiscal(!NFSeContextoFiscal::exigeNacional(3, '2026-11-01'), 'Real preserva emissor.');
assertContextoFiscal(NFSeContextoFiscal::exigeNacional(4, '2026-10-31'), 'MEI já exige Nacional.');
assertContextoFiscal(NFSeContextoFiscal::competencia('2024-02-29') === '2024-02-29', 'Data bissexta válida.');
foreach (['2026-02-29', '2026-04-31', '01/11/2026', '', '2026-11-01x', '2099-01-01'] as $data) {
    bloqueiaContextoFiscal(fn () => NFSeContextoFiscal::competencia($data));
}

$dados = [
    'ambiente' => 2, 'serie' => '1', 'numero' => 12, 'municipio_codigo' => '2611606',
    'data_emissao' => '2026-10-01T10:00:00-03:00', 'data_competencia' => '2026-10-01',
    'prestador' => ['cnpj' => '11694532000192', 'regime_tributario' => 1, 'reg_apuracao_sn' => 2, 'enviar_im' => 'N'],
    'tomador' => ['tipo' => 'PF', 'cpf_cnpj' => '52998224725', 'nome' => 'Teste'],
    'servico' => ['codigo' => '1.1101.11', 'codigo_tributacao_nacional' => '990101', 'descricao' => 'Locação'],
    'valores' => ['servicos' => 100, 'trib_issqn' => 4, 'preencher_ibscbs' => 'N'],
];
$configAtual = ['perfil_id' => 14, 'regime_tributario' => 3, 'reg_apuracao_sn' => 3, 'codigo_municipio' => '3550308',
    'tipo_emissao' => 'nacional', 'ambiente' => 1, 'serie' => '999', 'codigo_servico' => '999999999',
    'codigo_tributacao_nacional' => '010101', 'trib_issqn' => 1, 'aliquota_iss' => 5, 'enviar_im' => 'S',
    'preencher_ibscbs' => 'S', 'c_ind_op_ibscbs' => '010101', 'cst_ibscbs' => '999', 'c_class_trib_ibscbs' => '999999',
    'certificado_arquivo' => 'renovado.pfx', 'certificado_senha' => 'segredo', '_privado' => 'nao persistir'];
$nota = ['tipo_emissao' => 'nacional', 'ambiente' => 2, 'serie' => '00001', 'numero' => 12,
    'prestador_cnpj' => '11.694.532/0001-92', 'data_competencia' => '2026-10-01', 'contexto_fiscal' => null,
    'xml_envio' => (new NFSeXMLNacional())->gerarXML($dados)];
$historico = NFSeContextoFiscal::historico($nota, $configAtual);
NFSeContextoFiscal::validarReenvio($historico);
assertContextoFiscal($historico['ambiente'] === 2 && $historico['codigo_municipio'] === '2611606', 'Ambiente e município originais prevalecem.');
assertContextoFiscal($historico['regime_tributario'] === 1 && $historico['reg_apuracao_sn'] === 2, 'Regime histórico extraído do XML.');
assertContextoFiscal($historico['codigo_servico'] === '111011100' && $historico['codigo_tributacao_nacional'] === '990101', 'NBS e cTribNac separados.');
assertContextoFiscal($historico['preencher_ibscbs'] === 'N' && $historico['c_ind_op_ibscbs'] === '', 'IBS/CBS atual não contamina histórico.');
assertContextoFiscal($historico['enviar_im'] === 'N', 'Ausência de IM histórica preservada.');
assertContextoFiscal($historico['certificado_arquivo'] === 'renovado.pfx', 'Credencial operacional pode ser renovada.');
$snapshot = NFSeContextoFiscal::snapshot($historico);
assertContextoFiscal($snapshot['perfil_id'] === 14, 'Snapshot preserva referência ao perfil.');
assertContextoFiscal(array_intersect_key($snapshot, array_flip(['certificado_senha', 'certificado_arquivo', '_privado', '_historico_reenvio_erro'])) === [], 'Snapshot não persiste segredos ou metadados internos.');

$comSnapshot = $nota;
$comSnapshot['contexto_fiscal'] = json_encode($snapshot);
$resolvido = NFSeContextoFiscal::historico($comSnapshot, array_replace($configAtual, ['perfil_id' => 90]));
assertContextoFiscal($resolvido['perfil_id'] === 14, 'Perfil original prevalece sobre configuração atual.');
NFSeContextoFiscal::validarReenvio($resolvido);

$betha = $nota;
$betha['tipo_emissao'] = 'betha';
$dados['valores'] = ['servicos' => 100, 'trib_issqn' => 1, 'aliquota_iss' => 2, 'preencher_ibscbs' => 'S',
    'c_ind_op_ibscbs' => '100301', 'cst_ibscbs' => '000', 'c_class_trib_ibscbs' => '000001'];
$dados['prestador']['enviar_im'] = 'S';
$dados['prestador']['inscricao_municipal'] = '12345';
$betha['xml_envio'] = (new NFSeXMLBetha())->gerarXML($dados);
$historicoBetha = NFSeContextoFiscal::historico($betha, $configAtual);
NFSeContextoFiscal::validarReenvio($historicoBetha);
assertContextoFiscal($historicoBetha['enviar_im'] === 'S' && (float) $historicoBetha['aliquota_iss'] === 2.0, 'IM e alíquota histórica Betha.');
assertContextoFiscal($historicoBetha['c_ind_op_ibscbs'] === '100301' && $historicoBetha['cst_ibscbs'] === '000'
    && $historicoBetha['c_class_trib_ibscbs'] === '000001', 'Classificações IBS/CBS históricas Betha.');

foreach (['ambiente' => 1, 'serie' => '2', 'numero' => 13, 'prestador_cnpj' => '99999999000199', 'data_competencia' => '2026-09-30'] as $campo => $divergente) {
    $alterada = array_replace($nota, [$campo => $divergente]);
    bloqueiaContextoFiscal(fn () => NFSeContextoFiscal::validarReenvio(NFSeContextoFiscal::historico($alterada, $configAtual)));
}
foreach (['', '<DPS>', str_replace('<opSimpNac>3</opSimpNac>', '<opSimpNac>1</opSimpNac>', $nota['xml_envio']),
    str_replace('<opSimpNac>3</opSimpNac>', '<opSimpNac>4</opSimpNac>', $nota['xml_envio']),
    str_replace('<regApTribSN>2</regApTribSN>', '', $nota['xml_envio']),
    str_replace('</infDPS>', '</infDPS><infDPS/>', $nota['xml_envio'])] as $xmlInseguro) {
    $insegura = array_replace($nota, ['xml_envio' => $xmlInseguro]);
    bloqueiaContextoFiscal(fn () => NFSeContextoFiscal::validarReenvio(NFSeContextoFiscal::historico($insegura, $configAtual)));
}
$semRegimeClaro = array_replace($nota, ['xml_envio' => str_replace('<opSimpNac>3</opSimpNac>', '<opSimpNac>1</opSimpNac>', $nota['xml_envio'])]);
$paraConsulta = NFSeContextoFiscal::historico($semRegimeClaro, $configAtual);
assertContextoFiscal($paraConsulta['ambiente'] === 2 && $paraConsulta['codigo_municipio'] === '2611606', 'Consulta mantém identidade mesmo com regime ambíguo.');
$semAmbiente = $nota;
unset($semAmbiente['ambiente']);
bloqueiaContextoFiscal(fn () => NFSeContextoFiscal::historico($semAmbiente, $configAtual));

echo "Teste de contexto fiscal passou: corte, competência, snapshots e recuperação histórica segura.\n";
