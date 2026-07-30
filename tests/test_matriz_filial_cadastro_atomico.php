#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

if (session_status() === PHP_SESSION_NONE) {
    session_save_path(sys_get_temp_dir());
    session_start();
}

use App\Core\Database;
use App\Models\Model;
use App\Services\MatrizFilialCadastroService;

function assertMatrizFilialCadastro(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$controllerSource = file_get_contents(APP_ROOT . '/app/Controllers/MatrizFilialController.php');
$viewSource = file_get_contents(APP_ROOT . '/app/Views/pages/matrizes-filiais/adicionar.php');

assertMatrizFilialCadastro(
    !str_contains($controllerSource, "\$dados['chave']"),
    'O Controller voltou a depender de uma chave que o QueryBuilder injeta apenas no SQL.'
);
assertMatrizFilialCadastro(
    str_contains($viewSource, 'if (salvandoFormulario)')
        && str_contains($viewSource, 'btnSalvar.disabled = true'),
    'A trava contra submissoes concorrentes nao esta presente no formulario.'
);

$chave = '1111111111111';
$sufixo = bin2hex(random_bytes(6));
$nomeSucesso = "TESTE-MATRIZ-ATOMICA-{$sufixo}";
$nomeRollback = "TESTE-MATRIZ-ROLLBACK-{$sufixo}";
$idsCriados = [];

$_SESSION['chave'] = $chave;
$_SESSION['user_id'] = 1;
$_SESSION['user_name'] = 'Teste automatizado';

$dadosBase = [
    'tipo' => 'F',
    'status' => 'A',
    'razao_social' => $nomeSucesso,
    'nome_fantasia' => $nomeSucesso,
    'cpf_cnpj' => 'TESTE-' . $sufixo,
    'cidade' => 'Vitória',
    'estado' => 'ES',
    'pais' => 'BR',
    'locale' => 'pt_BR',
    'currency_code' => 'BRL',
    'date_format' => 'd/m/Y',
    'datetime_format' => 'd/m/Y H:i:s',
    'timezone' => 'America/Sao_Paulo',
];

try {
    $service = new MatrizFilialCadastroService();
    $id = $service->criar($dadosBase, [
        'horarios' => [
            [
                'dia_semana' => 1,
                'abertura' => '08:00',
                'fechamento' => '18:00',
                'periodo' => 1,
            ],
        ],
        'emails' => [
            [
                'email' => "teste-{$sufixo}@example.com",
                'principal' => 'S',
            ],
        ],
        'telefones' => [
            [
                'telefone' => '+55 (27) 99999-0000',
                'whatsapp' => 'S',
                'principal' => 'S',
            ],
        ],
        'locais' => [
            [
                'nome' => 'Local de teste',
                'bairro' => 'Centro',
                'cidade' => 'Vitória',
                'estado' => 'ES',
                'pais' => 'BR',
            ],
        ],
    ]);
    $idsCriados[] = $id;

    $principal = Database::fetchOne(
        'SELECT id, chave FROM matrizes_filiais WHERE id = ? AND chave = ?',
        [$id, $chave]
    );
    assertMatrizFilialCadastro(
        (int) ($principal['id'] ?? 0) === $id && ($principal['chave'] ?? '') === $chave,
        'O cadastro principal nao foi persistido no tenant de teste.'
    );

    $tabelasRelacionadas = [
        'horarios_funcionamento' => ['matriz_filial_id', 1],
        'contatos_emails' => ['entidade_id', 1],
        'contatos_telefones' => ['entidade_id', 1],
        'matrizes_filiais_locais' => ['id_matriz_filial', 1],
    ];

    foreach ($tabelasRelacionadas as $tabela => [$coluna, $esperado]) {
        $extra = str_starts_with($tabela, 'contatos_')
            ? " AND entidade_tipo = 'matriz_filial'"
            : '';
        $linha = Database::fetchOne(
            "SELECT COUNT(*) AS total, MIN(chave) AS chave
             FROM {$tabela}
             WHERE {$coluna} = ? AND chave = ?{$extra}",
            [$id, $chave]
        );
        assertMatrizFilialCadastro(
            (int) ($linha['total'] ?? 0) === $esperado && ($linha['chave'] ?? '') === $chave,
            "Falha ao persistir {$tabela} com isolamento por chave."
        );
    }

    $dadosRollback = $dadosBase;
    $dadosRollback['razao_social'] = $nomeRollback;
    $dadosRollback['nome_fantasia'] = $nomeRollback;
    $dadosRollback['cpf_cnpj'] = 'ROLLBACK-' . $sufixo;

    $falhouComoEsperado = false;
    try {
        $service->criar($dadosRollback, [
            'horarios' => [
                [
                    'dia_semana' => 999,
                    'abertura' => '08:00',
                    'fechamento' => '18:00',
                    'periodo' => 1,
                ],
            ],
        ]);
    } catch (Throwable) {
        $falhouComoEsperado = true;
    }

    assertMatrizFilialCadastro($falhouComoEsperado, 'A falha secundaria esperada nao ocorreu.');

    $aposRollback = Database::fetchOne(
        'SELECT COUNT(*) AS total FROM matrizes_filiais WHERE chave = ? AND razao_social = ?',
        [$chave, $nomeRollback]
    );
    assertMatrizFilialCadastro(
        (int) ($aposRollback['total'] ?? 0) === 0,
        'A matriz/filial principal permaneceu gravada apos rollback.'
    );

    echo "OK: cadastro agregado, chave automatica e rollback validados.\n";
} finally {
    foreach ($idsCriados as $idCriado) {
        Database::query(
            "DELETE FROM contatos_emails
             WHERE chave = ? AND entidade_tipo = 'matriz_filial' AND entidade_id = ?",
            [$chave, $idCriado]
        );
        Database::query(
            "DELETE FROM contatos_telefones
             WHERE chave = ? AND entidade_tipo = 'matriz_filial' AND entidade_id = ?",
            [$chave, $idCriado]
        );
        Database::query(
            'DELETE FROM matrizes_filiais WHERE chave = ? AND id = ?',
            [$chave, $idCriado]
        );
    }

    Model::closeConnection();
    Database::disconnect();
}
