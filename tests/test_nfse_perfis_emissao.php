<?php

/** Integracao local, sem rede externa e sem persistencia: php tests/test_nfse_perfis_emissao.php */
require_once __DIR__ . '/../vendor/autoload.php';

use App\Classes\QueryBuilder;
use App\Core\Database;
use App\Models\Model;
use App\Models\NFSeConfiguracao;
use App\Models\NFSePerfil;

$_ENV['APP_ENV'] = 'development';
if (Database::env('DB_HOST') !== 'localhost') {
    throw new RuntimeException('Este teste exige .env.development com DB_HOST=localhost.');
}

function conferirPerfil(mixed $esperado, mixed $obtido, string $mensagem): void
{
    if ($esperado !== $obtido) {
        throw new RuntimeException($mensagem . ' Esperado: ' . var_export($esperado, true) . '; obtido: ' . var_export($obtido, true));
    }
}

function rejeitarPerfil(callable $acao, string $mensagem): void
{
    try {
        $acao();
    } catch (InvalidArgumentException $e) {
        return;
    }
    throw new RuntimeException($mensagem);
}

$sessaoAnterior = $_SESSION ?? [];
$_SESSION['chave'] = '1111111111111';
$db = Model::sharedMysqli();
$qb = new QueryBuilder($db);
$config = new NFSeConfiguracao();
$perfis = new NFSePerfil();
if (!$perfis->disponivel()) {
    throw new RuntimeException('Execute as migrations locais pelo migrate.php antes deste teste.');
}

$db->begin_transaction();
try {
    $filial = $qb->table('matrizes_filiais')->insert([
        'chave' => $_SESSION['chave'], 'razao_social' => 'TESTE TRANSACIONAL PERFIS NFSE', 'status' => 'A',
    ]);
    $municipal = [
        'tipo_emissao' => 'betha', 'ambiente' => 2, 'ativo' => 'N', 'serie' => '7',
        'numero_atual' => 20, 'regime_tributario' => 1, 'reg_apuracao_sn' => 1,
        'codigo_municipio' => '3550308', 'codigo_servico' => '111011100',
        'codigo_tributacao_nacional' => '990101', 'descricao_servico' => 'Locacao teste',
        'preencher_ibscbs' => 'N', 'emissao_auto' => 'N', 'enviar_email' => 'N',
    ];
    $config->salvar($filial, $_SESSION['chave'], $municipal);
    $rootOriginal = $config->buscarPorMatrizFilial($filial);
    conferirPerfil('betha', $config->resolverPorCompetencia($filial, '2026-10-31')['tipo_emissao'], 'Outubro usa o emissor municipal.');
    $preview = $config->resolverPorCompetencia($filial, '2026-11-01', false);
    conferirPerfil('nacional', $preview['tipo_emissao'], 'Novembro resolve Nacional automaticamente.');
    conferirPerfil(null, $perfis->buscar($filial, 'nacional', 2), 'Preview nao deve materializar perfil Nacional.');

    $nacional = array_replace($municipal, [
        'tipo_emissao' => 'nacional', 'serie' => '001', 'numero_atual' => 30,
        'perfil_operacional' => 'S', 'nacional_confirmado' => 'N',
    ]);
    $config->salvar($filial, $_SESSION['chave'], $nacional);
    $qb->table('nfse_perfis_emissao')->where('id_matriz_filial', '=', $filial)->where('tipo_emissao', '=', 'nacional')->update(['confirmado' => 'N']);
    $rootAtual = $config->buscarPorMatrizFilial($filial);
    foreach (['tipo_emissao', 'ambiente', 'serie', 'numero_atual', 'codigo_servico', 'emissao_auto', 'enviar_email', 'certificado_arquivo', 'certificado_senha'] as $campo) {
        conferirPerfil($rootOriginal[$campo], $rootAtual[$campo], 'Salvar perfil Nacional preserva root municipal: ' . $campo);
    }
    $perfilNacional = $config->resolverPorCompetencia($filial, '2026-11-01');
    conferirPerfil('nacional', $perfilNacional['tipo_emissao'], 'Nacional completo funciona mesmo com confirmado=N legado.');
    conferirPerfil('betha', $config->resolverPorCompetencia($filial, '2026-10-31')['tipo_emissao'], 'Criar Nacional nao muda a rota retroativa.');

    foreach ([0, 5, 99] as $regime) {
        rejeitarPerfil(fn () => $config->salvar($filial, $_SESSION['chave'], array_replace($nacional, ['regime_tributario' => $regime])), 'Rejeitar regime fora do enum.');
    }
    foreach ([0, 4, 99] as $apuracao) {
        rejeitarPerfil(fn () => $config->salvar($filial, $_SESSION['chave'], array_replace($nacional, ['reg_apuracao_sn' => $apuracao])), 'Rejeitar apuracao fora do enum.');
    }

    conferirPerfil(31, $perfis->proximo($perfilNacional), 'Contador Nacional parte do valor configurado.');
    conferirPerfil(true, $perfis->reservar($perfilNacional, 31), 'Primeira reserva deve vencer.');
    conferirPerfil(false, $perfis->reservar($perfilNacional, 31), 'Reserva repetida deve perder.');
    conferirPerfil(32, $perfis->proximo(array_replace($perfilNacional, ['serie' => '1'])), 'Series 001 e 1 compartilham sequencia Nacional.');
    $config->salvar($filial, $_SESSION['chave'], array_replace($nacional, ['numero_atual' => 1]));
    conferirPerfil(32, $perfis->proximo($perfilNacional), 'Salvar formulario antigo nao reduz sequencia.');
    conferirPerfil(31, $perfis->buscar($filial, 'nacional', 2)['numero_atual'], 'Espelho do perfil nao pode regredir.');

    $perfilBetha = $perfis->buscar($filial, 'betha', 2);
    conferirPerfil(21, $perfis->proximo($perfilBetha), 'Provedor municipal possui contador independente.');
    $config->salvar($filial, $_SESSION['chave'], array_replace($nacional, ['ambiente' => 1, 'numero_atual' => 4]));
    conferirPerfil(5, $perfis->proximo($perfis->buscar($filial, 'nacional', 1)), 'Producao e homologacao possuem contadores independentes.');
    $config->salvar($filial, $_SESSION['chave'], array_replace($nacional, ['serie' => '2', 'numero_atual' => 5]));
    conferirPerfil(6, $perfis->proximo($perfis->buscar($filial, 'nacional', 2)), 'Serie nova usa seu proprio contador.');
    $config->salvar($filial, $_SESSION['chave'], array_replace($nacional, ['serie' => '1', 'numero_atual' => 0]));
    conferirPerfil(32, $perfis->proximo($perfis->buscar($filial, 'nacional', 2)), 'Voltar a uma serie usada preserva o maior numero.');

    // Sessao alheia apenas para leituras: nenhuma escrita fora do tenant de teste.
    $_SESSION['chave'] = 'teste-isolamento-sem-gravacao';
    conferirPerfil([], $perfis->listar($filial), 'Outro tenant nao pode listar perfis desta filial.');
    conferirPerfil(null, $perfis->buscar($filial, 'nacional', 2), 'Outro tenant nao pode buscar perfil por filial.');
    conferirPerfil(null, $config->buscarPorMatrizFilial($filial), 'Outro tenant nao pode ler configuracao root.');
    $_SESSION['chave'] = '1111111111111';

    // Verifica que salvar() respeitou a transacao externa antes do rollback final.
    $db->rollback();
    conferirPerfil(null, $qb->table('matrizes_filiais')->where('id', '=', $filial)->first(), 'Teste deve reverter a filial criada.');
    conferirPerfil([], $perfis->listar($filial), 'Teste deve reverter todos os perfis criados.');
    echo "Teste de integracao local dos perfis NFS-e passou; rollback confirmado.\n";
} finally {
    $db->rollback();
    $_SESSION = $sessaoAnterior;
}
