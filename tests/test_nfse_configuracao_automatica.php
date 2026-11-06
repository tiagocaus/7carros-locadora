<?php

/** Integracao localhost: materializacao sob demanda, sem chamadas externas, sempre rollback. */
require_once __DIR__ . '/../vendor/autoload.php';

use App\Classes\QueryBuilder;
use App\Core\Database;
use App\Models\Model;
use App\Models\NFSeConfiguracao;
use App\Models\NFSePerfil;

$_ENV['APP_ENV'] = 'development';
if (Database::env('DB_HOST') !== 'localhost') {
    throw new RuntimeException('Teste exclusivo do .env.development com DB_HOST=localhost.');
}

function exigirAutomatico(bool $condicao, string $mensagem): void
{
    if (!$condicao) {
        throw new RuntimeException($mensagem);
    }
}

function bloquearAutomatico(callable $acao, string $mensagem): void
{
    try {
        $acao();
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException($mensagem);
}

$sessaoAnterior = $_SESSION ?? [];
$_SESSION['chave'] = '1111111111111';
$db = Model::sharedMysqli();
$qb = new QueryBuilder($db);
$model = new NFSeConfiguracao();
$perfis = new NFSePerfil();
exigirAutomatico($perfis->disponivel(), 'As migrations locais devem estar aplicadas antes do teste.');
$filiais = [];
$base = [
    'ativo' => 'S', 'tipo_emissao' => 'betha', 'ambiente' => 2, 'serie' => '7',
    'numero_atual' => 20, 'codigo_municipio' => '3550308',
    'codigo_servico' => '111011100', 'codigo_tributacao_nacional' => '990101',
    'descricao_servico' => 'Locacao de automovel sem condutor',
    'regime_tributario' => 1, 'reg_apuracao_sn' => 1, 'trib_issqn' => 4,
    'preencher_ibscbs' => 'N', 'emissao_auto' => 'N', 'enviar_email' => 'N',
    'certificado_arquivo' => 'teste-sem-acesso-externo.pfx',
    'certificado_senha' => 'segredo-fixture-nao-exibir',
];
$criar = function (array $mudancas = []) use ($qb, $base, &$filiais): int {
    $filial = $qb->table('matrizes_filiais')->insert([
        'chave' => $_SESSION['chave'], 'razao_social' => 'TESTE NFSE AUTOMATICA ROLLBACK',
        'cpf_cnpj' => '11222333000181', 'status' => 'A',
    ]);
    $filiais[] = $filial;
    $qb->table('nfse_configuracoes')->insert(array_replace($base, $mudancas, [
        'chave' => $_SESSION['chave'], 'id_matriz_filial' => $filial,
    ]));
    return $filial;
};

$db->begin_transaction();
try {
    $filial = $criar();
    $raiz = $model->buscarPorMatrizFilial($filial);
    $qb->table('nfse')->insert([
        'chave' => $_SESSION['chave'], 'id_matriz_filial' => $filial,
        'tipo_emissao' => 'nacional', 'ambiente' => 2, 'serie' => '0007',
        'numero' => 90, 'status' => 'rejeitada', 'data_competencia' => '2026-10-31',
    ]);
    $antesPerfis = count($perfis->listar($filial));
    $antesSequencias = $qb->table('nfse_sequencias')->where('id_matriz_filial', '=', $filial)->count();
    $preview = $model->resolverPorCompetencia($filial, '2026-11-01', false);
    exigirAutomatico($preview['tipo_emissao'] === 'nacional', 'Simples novembro deve resolver Nacional.');
    exigirAutomatico((int) $preview['numero_atual'] >= 90, 'Preview deve considerar numeracao Nacional historica equivalente.');
    $visao = $model->configuracaoAtual($filial, '2026-11-01');
    exigirAutomatico($visao['emissor_efetivo'] === 'nacional', 'Configuracao atual deve expor emissor efetivo Nacional.');
    exigirAutomatico($visao['data_competencia'] === '2026-11-01', 'Configuracao atual preserva competencia selecionada.');
    exigirAutomatico(is_array($visao['configuracao_atual']), 'Configuracao atual deve expor seus campos fiscais.');
    // Certificado fixture nao existe: a tela deve informar essa pendencia real,
    // sem transformar a ausencia de checkbox em pendencia fiscal adicional.
    foreach ($visao['pendencias'] as $pendencia) {
        exigirAutomatico(stripos($pendencia, 'certificado') !== false, 'Configuracao fiscal completa so deve apontar o certificado fixture ausente.');
    }
    exigirAutomatico(!array_key_exists('certificado_senha', $visao['configuracao_atual']), 'Leitura publica nao pode expor senha de certificado.');
    exigirAutomatico(!str_contains(json_encode($visao, JSON_THROW_ON_ERROR), $base['certificado_senha']), 'Nenhum nivel da resposta pode conter segredos.');
    exigirAutomatico(count($perfis->listar($filial)) === $antesPerfis, 'Preview/configuracaoAtual nao criam perfis.');
    exigirAutomatico($qb->table('nfse_sequencias')->where('id_matriz_filial', '=', $filial)->count() === $antesSequencias, 'Leitura nao cria nem consome sequencia.');

    $nacional = $model->resolverPorCompetencia($filial, '2026-11-01');
    exigirAutomatico(!empty($nacional['perfil_id']), 'Emissao materializa perfil Nacional valido sob demanda.');
    exigirAutomatico((int) $nacional['numero_atual'] >= 90, 'Materializacao nao reduz contador abaixo do historico.');
    exigirAutomatico($model->buscarPorMatrizFilial($filial) === $raiz, 'Preparacao automatica nao altera root municipal.');
    exigirAutomatico($model->resolverPorCompetencia($filial, '2026-10-31', false)['tipo_emissao'] === 'betha', 'Competencia antiga mantem Betha.');
    $qb->table('nfse_perfis_emissao')->where('id', '=', $nacional['perfil_id'])->update(['confirmado' => 'N']);
    exigirAutomatico($model->resolverPorCompetencia($filial, '2026-11-01')['tipo_emissao'] === 'nacional', 'Campo legado confirmado=N nao bloqueia emissao valida.');
    exigirAutomatico($perfis->proximo($nacional) >= 91, 'Primeiro numero deve ser maior que o historico; preparar nao reserva.');

    $incompleta = $criar(['codigo_tributacao_nacional' => null]);
    $pendente = $model->configuracaoAtual($incompleta, '2026-11-01');
    exigirAutomatico($pendente['pendencias'] !== [], 'Falta de cTribNac explicito deve aparecer como pendencia concreta.');
    foreach ($pendente['pendencias'] as $pendencia) {
        exigirAutomatico(is_string($pendencia) && trim($pendencia) !== '', 'Pendencias devem ser mensagens legiveis.');
    }
    bloquearAutomatico(fn () => $model->resolverPorCompetencia($incompleta, '2026-11-01'), 'Nao pode materializar Nacional com classificacao fiscal ausente.');
    exigirAutomatico($perfis->listar($incompleta) === [], 'Falha fiscal nao cria perfil parcial.');
    exigirAutomatico($qb->table('nfse_sequencias')->where('id_matriz_filial', '=', $incompleta)->count() === 0, 'Falha fiscal nao cria ou consome numeracao.');
    exigirAutomatico((int) $model->buscarPorMatrizFilial($incompleta)['numero_atual'] === 20, 'Falha fiscal preserva contador raiz.');

    $legado = $criar(['tipo_emissao' => 'abrasf']);
    exigirAutomatico($model->resolverPorCompetencia($legado, '2026-11-01')['tipo_emissao'] === 'nacional', 'Legado com dados compativeis pode usar Nacional obrigatorio.');
    exigirAutomatico($model->buscarPorMatrizFilial($legado)['tipo_emissao'] === 'abrasf', 'Legado abrasf nao pode ser reinterpretado ou sobrescrito.');
    exigirAutomatico($perfis->buscar($legado, 'issnet', 2) === null, 'Nao criar ISSNet a partir de abrasf.');
    foreach ([2, 3] as $regime) {
        $outra = $criar(['regime_tributario' => $regime]);
        exigirAutomatico($model->resolverPorCompetencia($outra, '2026-11-01', false)['tipo_emissao'] === 'betha', 'Regime fora do Simples conserva emissor configurado.');
        exigirAutomatico($perfis->buscar($outra, 'nacional', 2) === null, 'Leitura de outro regime nao cria Nacional.');
    }

    $camposAtuais = array_replace($base, [
        'data_competencia' => '2026-11-01', 'ativo' => 'N',
        'emissao_auto' => 'S', 'enviar_email' => 'S', 'descricao_servico' => 'Descricao fiscal atual Nacional',
        'numero_atual' => 1,
    ]);
    unset($camposAtuais['certificado_senha'], $camposAtuais['certificado_arquivo']);
    $model->salvarAtual($filial, $_SESSION['chave'], $camposAtuais);
    $depois = $model->buscarPorMatrizFilial($filial);
    foreach (['tipo_emissao', 'serie', 'numero_atual', 'descricao_servico', 'certificado_arquivo', 'certificado_senha'] as $campo) {
        exigirAutomatico($depois[$campo] === $raiz[$campo], 'Salvar configuracao atual preserva fiscal/contador municipal: ' . $campo);
    }
    exigirAutomatico($depois['ativo'] === 'N' && $depois['emissao_auto'] === 'S' && $depois['enviar_email'] === 'S', 'Salvar atual persiste flags gerais na raiz.');
    $atualizado = $perfis->buscar($filial, 'nacional', 2);
    exigirAutomatico($atualizado['descricao_servico'] === 'Descricao fiscal atual Nacional', 'Salvar atual atualiza os campos do emissor efetivo.');
    exigirAutomatico((int) $atualizado['numero_atual'] >= 90, 'Salvar atual nao regride contador Nacional.');

    // O formulario envia o tipo efetivo mostrado anteriormente. Sem escolha manual,
    // esse valor nao pode prender o Nacional depois da saida do Simples.
    $trocaRegime = $criar();
    $model->resolverPorCompetencia($trocaRegime, '2026-11-01');
    $lucro = array_replace($base, [
        'data_competencia' => '2026-11-01', 'regime_tributario' => 2,
        'tipo_emissao' => 'nacional', 'descricao_servico' => 'Fiscal municipal apos sair do Simples',
    ]);
    unset($lucro['certificado_arquivo'], $lucro['certificado_senha']);
    $model->salvarAtual($trocaRegime, $_SESSION['chave'], $lucro);
    $aposRegime = $model->buscarPorMatrizFilial($trocaRegime);
    exigirAutomatico($aposRegime['tipo_emissao'] === 'betha', 'Tipo Nacional efetivo antigo nao substitui preferencia Betha sem escolha manual.');
    exigirAutomatico((int) $aposRegime['regime_tributario'] === 2, 'Mudanca de regime deve ser persistida.');
    exigirAutomatico($aposRegime['descricao_servico'] === 'Fiscal municipal apos sair do Simples', 'Sair do Simples deve salvar campos no emissor municipal selecionado.');
    exigirAutomatico($model->resolverPorCompetencia($trocaRegime, '2026-11-01')['tipo_emissao'] === 'betha', 'Emissao subsequente de lucro presumido conserva Betha.');

    $escolha = array_replace($lucro, [
        'emissor_manual' => 'S', 'tipo_emissao' => 'nacional',
        'descricao_servico' => 'Escolha Nacional explicita no lucro presumido',
    ]);
    $model->salvarAtual($trocaRegime, $_SESSION['chave'], $escolha);
    exigirAutomatico($model->buscarPorMatrizFilial($trocaRegime)['tipo_emissao'] === 'nacional', 'Escolha manual permitida deve atualizar preferencia de emissor na raiz.');
    exigirAutomatico($model->resolverPorCompetencia($trocaRegime, '2026-11-01')['tipo_emissao'] === 'nacional', 'Escolha manual Nacional deve persistir nas emissoes seguintes.');

    // Preview de ambiente/regime selecionados nao usa dados fiscais de outro perfil.
    $previewFilial = $criar();
    $perfis->salvar($previewFilial, array_replace($base, [
        'tipo_emissao' => 'nacional', 'ambiente' => 2,
        'descricao_servico' => 'Fiscal Nacional homologacao', 'codigo_servico' => '111011100',
    ]), true);
    $perfis->salvar($previewFilial, array_replace($base, [
        'tipo_emissao' => 'nacional', 'ambiente' => 1, 'serie' => '9',
        'descricao_servico' => 'Fiscal Nacional producao', 'codigo_servico' => '111011200',
    ]), true);
    $rootPreview = $model->buscarPorMatrizFilial($previewFilial);
    $perfisPreview = $perfis->listar($previewFilial);
    $sequenciasPreview = $qb->table('nfse_sequencias')->where('id_matriz_filial', '=', $previewFilial)->get();
    $producao = $model->configuracaoAtual($previewFilial, '2026-11-01', ['ambiente' => 1, 'regime_tributario' => 1]);
    exigirAutomatico($producao['emissor_efetivo'] === 'nacional' && (int) $producao['configuracao_atual']['ambiente'] === 1, 'Preview deve considerar ambiente e regime selecionados.');
    exigirAutomatico($producao['configuracao_atual']['descricao_servico'] === 'Fiscal Nacional producao', 'Preview de producao deve carregar fiscal de producao.');
    exigirAutomatico($producao['configuracao_atual']['codigo_servico'] === '111011200', 'NBS de homologacao nao pode vazar para producao.');
    $homologacao = $model->configuracaoAtual($previewFilial, '2026-11-01', ['ambiente' => 2, 'regime_tributario' => 1]);
    exigirAutomatico($homologacao['configuracao_atual']['descricao_servico'] === 'Fiscal Nacional homologacao', 'Preview de homologacao deve carregar fiscal proprio.');
    $outroRegime = $model->configuracaoAtual($previewFilial, '2026-11-01', ['ambiente' => 2, 'regime_tributario' => 2]);
    exigirAutomatico($outroRegime['emissor_efetivo'] === 'betha', 'Alterar regime em preview deve resolver emissor municipal sem exigir save.');
    exigirAutomatico($model->buscarPorMatrizFilial($previewFilial) === $rootPreview, 'Preview com overrides nao grava regime ou ambiente na raiz.');
    exigirAutomatico($perfis->listar($previewFilial) === $perfisPreview, 'Preview com overrides nao altera perfis.');
    exigirAutomatico($qb->table('nfse_sequencias')->where('id_matriz_filial', '=', $previewFilial)->get() === $sequenciasPreview, 'Preview com overrides nao altera sequencias.');

    // Flag historica incompleta e tolerada na leitura ate 2026. Ativacao explicita
    // continua validada: compatibilidade nao pode ocultar erro do formulario atual.
    $ibsLegado = $criar(['preencher_ibscbs' => 'S', 'c_ind_op_ibscbs' => null, 'cst_ibscbs' => null, 'c_class_trib_ibscbs' => null]);
    $ibsPreview = $model->configuracaoAtual($ibsLegado, '2026-11-01');
    exigirAutomatico($ibsPreview['configuracao_atual']['preencher_ibscbs'] === 'N', 'Simples 2026 com flag legada incompleta deve mostrar IBS/CBS desativado.');
    foreach ($ibsPreview['pendencias'] as $pendencia) {
        exigirAutomatico(stripos($pendencia, 'IBS') === false && stripos($pendencia, 'CST') === false, 'Flag legada tolerada nao deve bloquear leitura de Simples 2026.');
    }
    exigirAutomatico($perfis->listar($ibsLegado) === [], 'Normalizacao de leitura da flag IBS/CBS nao cria perfil.');
    $ibsEfetivo = $model->resolverPorCompetencia($ibsLegado, '2026-11-01');
    exigirAutomatico($ibsEfetivo['preencher_ibscbs'] === 'N', 'Resolucao operacional deve aplicar a mesma compatibilidade da leitura.');
    exigirAutomatico($model->buscarPorMatrizFilial($ibsLegado)['preencher_ibscbs'] === 'S', 'Normalizacao operacional nao deve reescrever flag legada da origem.');
    $ibsAntes = $perfis->listar($ibsLegado);
    bloquearAutomatico(fn () => $model->salvarAtual($ibsLegado, $_SESSION['chave'], array_replace($base, [
        'data_competencia' => '2026-11-01', 'preencher_ibscbs' => 'S',
        'c_ind_op_ibscbs' => null, 'cst_ibscbs' => null, 'c_class_trib_ibscbs' => null,
    ])), 'Salvar explicitamente IBS/CBS ativo e incompleto deve falhar.');
    exigirAutomatico($perfis->listar($ibsLegado) === $ibsAntes, 'Falha de ativacao IBS/CBS nao deve gravar perfil parcial.');

    $nova = $qb->table('matrizes_filiais')->insert([
        'chave' => $_SESSION['chave'], 'razao_social' => 'TESTE PRIMEIRA CONFIGURACAO NFSE', 'status' => 'A',
    ]);
    $filiais[] = $nova;
    $primeiraVisao = $model->configuracaoAtual($nova, '2026-11-01');
    exigirAutomatico(is_array($primeiraVisao['configuracao_atual']) && is_array($primeiraVisao['pendencias']), 'Primeiro acesso sem configuracao deve retornar defaults e pendencias.');
    exigirAutomatico($model->buscarPorMatrizFilial($nova) === null && $perfis->listar($nova) === [], 'Primeiro acesso nao grava configuracao nem perfis.');
    $model->salvarAtual($nova, $_SESSION['chave'], [
        'data_competencia' => '2026-11-01', 'ativo' => 'N', 'ambiente' => 2,
        'regime_tributario' => 1, 'reg_apuracao_sn' => 1,
    ]);
    exigirAutomatico($model->buscarPorMatrizFilial($nova)['ativo'] === 'N', 'Primeiro cadastro inativo e incompleto deve ser permitido.');
    exigirAutomatico($qb->table('nfse_sequencias')->where('id_matriz_filial', '=', $nova)->count() === 0, 'Primeiro cadastro inativo incompleto nao cria sequencia.');

    $desativar = $criar(['serie' => 'ABCDEF', 'codigo_municipio' => '1', 'codigo_tributacao_nacional' => 'ruim']);
    $model->salvarAtual($desativar, $_SESSION['chave'], array_replace($base, [
        'data_competencia' => '2026-11-01', 'ativo' => 'N',
        'serie' => 'ABCDEF', 'codigo_municipio' => '1', 'codigo_tributacao_nacional' => 'ruim',
    ]));
    exigirAutomatico($model->buscarPorMatrizFilial($desativar)['ativo'] === 'N', 'Erros fiscais preexistentes nao podem impedir desativar emissao.');
    exigirAutomatico((int) $model->buscarPorMatrizFilial($desativar)['numero_atual'] === 20, 'Desativar preserva contador municipal.');
    foreach ($qb->table('nfse_sequencias')->where('id_matriz_filial', '=', $desativar)->get() as $sequencia) {
        exigirAutomatico((int) $sequencia['numero_atual'] <= 20, 'Desativacao de configuracao invalida nao pode consumir novo numero.');
    }

    $db->rollback();
    foreach ($filiais as $id) {
        exigirAutomatico($qb->table('matrizes_filiais')->where('id', '=', $id)->first() === null, 'Rollback deve remover todas as fixtures.');
        exigirAutomatico($perfis->listar($id) === [], 'Rollback deve remover perfis automaticos.');
        exigirAutomatico($qb->table('nfse_sequencias')->where('id_matriz_filial', '=', $id)->count() === 0, 'Rollback deve remover sequencias de teste.');
    }
    echo "Configuracao automatica NFS-e: cenarios locais passaram; rollback confirmado.\n";
} finally {
    $db->rollback();
    $_SESSION = $sessaoAnterior;
}
