<?php
/** Regressao operacional no MySQL LOCAL. Nao envia mensagens. */
require __DIR__ . '/../vendor/autoload.php';
$_ENV['APP_ENV'] = 'development';
define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/app/Helpers/helpers.php';

use App\Core\Database;
use App\Classes\QueryBuilder;
use App\Models\Model;
use App\Models\Locacao;
use App\Models\LocacaoEstadoOperacional as Estado;
use App\Services\AuditLogService;
use App\Services\LocacaoAtualizacaoService;

if (Database::env('DB_HOST') !== 'localhost') throw new RuntimeException('Teste exige localhost');
$_SESSION = ['chave' => '1111111111111', 'user_id' => 0, 'user_name' => 'Teste operacional'];
$falhas = 0;
function checkEstado(string $label, bool $ok): void {
    global $falhas;
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . "\n";
    if (!$ok) $falhas++;
}
$permitidos = ['PP', 'RR', 'RA', 'AA', 'AF', 'FF'];
foreach (['P', 'R', 'A', 'F'] as $de) foreach (['P', 'R', 'A', 'F'] as $para) {
    try { Estado::validar($de, $de, $para); $aceito = true; }
    catch (DomainException $e) { $aceito = false; checkEstado("codigo transicao $de/$para", $e->getCode() === 422); }
    checkEstado("matriz $de -> $para", $aceito === in_array($de.$para, $permitidos, true));
}
foreach ([null, '', 'R', ['A']] as $original) {
    try { Estado::validar('A', $original, 'A'); checkEstado('conflito rejeitado', false); }
    catch (DomainException $e) { checkEstado('aba antiga/referencia ausente', $e->getCode() === 409); }
}
Estado::validar('P', 'P', 'R', true);
checkEstado('confirmacao dedicada P/R', true);
checkEstado('snapshot sem mudanca normaliza numeros', Estado::diferencas(['km'=>12], ['km'=>'12']) === []);
$complementares = Estado::camposComplementares(json_encode(['Geral'=>[
    ['label'=>'Status', 'de'=>'R','para'=>'F'], ['label'=>'Grupo','de'=>'1','para'=>'2'],
    ['label'=>'Desconto','de'=>'0','para'=>'10'],
]]));
checkEstado('auditoria falsa nao substitui estado operacional', count($complementares) === 1 && $complementares[0]['label'] === 'Desconto');
$camposCombustivel = Estado::camposComplementares(json_encode([
    ['label'=>'Combustivel Saída','de'=>4,'para'=>8],
    ['label'=>'Valor combustível','de'=>0,'para'=>50],
]));
checkEstado('preserva campos financeiros de combustivel', count($camposCombustivel) === 1 && $camposCombustivel[0]['label'] === 'Valor combustível');

$db = Model::sharedMysqli();
$qb = new QueryBuilder($db);
$tx = new LocacaoAtualizacaoService();
$tx->iniciar();
try {
    $veiculo = $qb->table('veiculos')->insert(['placa'=>'TSTOPER', 'disponibilidade'=>'D', 'odometro'=>'100']);
    $id = $qb->table('locacoes')->insert(['codigo'=>'TEST'.bin2hex(random_bytes(4)), 'status'=>'R',
        'data_saida'=>'2026-09-01 10:00:00', 'data_prevista'=>'2026-09-02 10:00:00', 'dias'=>1, 'cliente_nome'=>'Teste']);
    $vinculo = $qb->table('locacoes_veiculos')->insert(['id_locacao'=>$id, 'id_veiculo'=>$veiculo,
        'data_saida'=>'2026-09-01 10:00:00', 'plano'=>'KL', 'odometro_saida'=>100, 'combustivel_saida'=>8]);
    $estado = new Estado();
    checkEstado('bloqueio le estado atual', $estado->bloquear($id)['status'] === 'R');
    $antes = $estado->capturar($id);
    $locacao = new Locacao();
    $locacao->registrarSaida($id, ['data_saida'=>'2026-09-01 10:00:00', 'odometro_ini'=>100, 'combustivel_ini'=>8]);
    $depois = $estado->capturar($id);
    checkEstado('saida abre locacao e ocupa veiculo', $depois['Status'] === 'A' && $depois["Veículo #$veiculo / disponibilidade"] === 'L');
    $campos = Estado::diferencas($antes, $depois);
    checkEstado('mudanca real sem audit frontend', count($campos) >= 2);
    $log = AuditLogService::registrarComCamposNaTransacao($db, 'Teste operacional', $campos);
    checkEstado('log na mesma transacao', $qb->table('logs')->where('id','=',$log)->exists());
    try { Estado::validar($estado->bloquear($id)['status'], 'R', 'R'); checkEstado('segunda aba bloqueada', false); }
    catch (DomainException $e) { checkEstado('segunda aba nao devolve para reserva', $e->getCode() === 409); }
    Estado::validar('A', 'A', 'A');
    checkEstado('segundo salvamento com referencia atual', true);
    $locacao->registrarDevolucao($id, ['data_chegada'=>'2026-09-02 10:00:00', 'odometro_fim'=>110, 'combustivel_fim'=>8]);
    $final = $estado->capturar($id);
    checkEstado('devolucao fecha e libera', $final['Status'] === 'F' && $final["Veículo #$veiculo / disponibilidade"] === 'D');
    checkEstado('devolucao encerra vinculo', $qb->table('locacoes_veiculos')->where('id','=',$vinculo)->value('data_entrada') !== null);
    $_SESSION['chave'] = 'outro-tenant-teste';
    checkEstado('isolamento de tenant no bloqueio', $estado->bloquear($id) === null);
    $_SESSION['chave'] = '1111111111111';
    // Ausencia de tenant provoca falha real da auditoria; a operacao inteira deve voltar.
    unset($_SESSION['chave']);
    try { AuditLogService::registrarComCamposNaTransacao($db, 'Deve falhar', []); checkEstado('falha auditoria', false); }
    catch (RuntimeException $e) { $tx->reverter(); checkEstado('falha auditoria permite rollback', true); }
    $_SESSION['chave'] = '1111111111111';
    checkEstado('rollback remove locacao', !$qb->table('locacoes')->where('id','=',$id)->exists());
    checkEstado('rollback remove veiculo', !$qb->table('veiculos')->where('id','=',$veiculo)->exists());
    checkEstado('rollback remove auditoria', !$qb->table('logs')->where('id','=',$log)->exists());
} finally { $tx->reverter(); }
exit($falhas ? 1 : 0);
