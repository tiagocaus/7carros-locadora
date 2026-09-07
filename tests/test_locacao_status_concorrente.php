<?php
/** Duas conexoes reais no localhost: bloqueio e rejeicao da referencia antiga. */
require __DIR__ . '/../vendor/autoload.php';
$_ENV['APP_ENV']='development';
use App\Core\Database;
use App\Classes\QueryBuilder;
use App\Models\Model;
use App\Models\LocacaoEstadoOperacional as Estado;
if (Database::env('DB_HOST') !== 'localhost') throw new RuntimeException('Teste exige localhost');
$_SESSION=['chave'=>'1111111111111'];
$db=Model::sharedMysqli();
$qb=new QueryBuilder($db);
$id=$qb->table('locacoes')->insert(['codigo'=>'CONC'.bin2hex(random_bytes(4)), 'status'=>'R',
    'data_saida'=>'2026-09-01 10:00:00','data_prevista'=>'2026-09-02 10:00:00','dias'=>1,'cliente_nome'=>'Teste concorrencia']);
$segunda=new mysqli('localhost',Database::env('DB_USERNAME'),Database::env('DB_PASSWORD'),Database::env('DB_DATABASE'),(int)Database::env('DB_PORT',3306));
$segunda->query('SET SESSION innodb_lock_wait_timeout=1');
$falhas=0;
try {
    $db->begin_transaction();
    $estado=new Estado();
    $estado->bloquear($id);
    $segunda->begin_transaction();
    try {
        (new QueryBuilder($segunda))->table('locacoes')->where('id','=',$id)->lockForUpdate()->first();
        echo "FAIL segunda conexao passou pelo bloqueio\n"; $falhas++;
    } catch (Throwable $e) {
        if (!str_contains($e->getMessage(),'Lock wait timeout')) throw $e;
        echo "PASS segunda conexao aguarda lock da locacao\n";
    }
    $segunda->rollback();
    $qb->table('locacoes')->where('id','=',$id)->update(['status'=>'A']);
    $db->commit();
    $segunda->begin_transaction();
    $atual=(new QueryBuilder($segunda))->table('locacoes')->where('id','=',$id)->lockForUpdate()->first();
    try { Estado::validar($atual['status'],'R','R'); echo "FAIL referencia antiga aceita\n"; $falhas++; }
    catch (DomainException $e) {
        if ($e->getCode() !== 409) throw $e;
        echo "PASS segunda conexao rele A e rejeita referencia R\n";
    }
    $segunda->rollback();
} finally {
    $db->rollback(); $segunda->rollback(); $segunda->close();
    $qb->table('locacoes')->where('id','=',$id)->delete();
}
exit($falhas ? 1 : 0);
