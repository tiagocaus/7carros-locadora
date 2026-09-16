<?php
/** Migration específica, sem executar outras alterações pendentes. Padrão: diagnóstico. */
require dirname(__DIR__).'/vendor/autoload.php';
$ambiente='development';
foreach($argv as $arg) if(str_starts_with($arg,'--env=')) $ambiente=substr($arg,6);
if(!in_array($ambiente,['development','production'],true)) throw new InvalidArgumentException('Ambiente inválido.');
$_ENV['APP_ENV']=$ambiente;
use App\Core\Database;
if(Database::env('DB_HOST')!=='localhost') throw new RuntimeException('Execute no servidor do ambiente com DB_HOST=localhost.');
$db=Database::getConnection();
$nome='00430_create_contratos_km_cobrancas.php';
$consulta=$db->prepare('SELECT COUNT(*) FROM migrations WHERE migration=?');$consulta->execute([$nome]);
echo 'Ambiente: '.$ambiente."\nMigration registrada: ".($consulta->fetchColumn()?'sim':'não')."\n";
foreach(['contratos_odometros','contratos_km_ciclos','contratos_km_apuracoes'] as $t) {
    $q=$db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([$t]);
    echo $t.': '.($q->fetchColumn()?'presente':'ausente')."\n";
}
if(!in_array('--aplicar',$argv,true)) { echo "Diagnóstico concluído. Use --aplicar para executar somente esta migration.\n"; exit; }
$backup=dirname(__DIR__).'/storage/backups';
if(!is_dir($backup) && !mkdir($backup,0700,true)) throw new RuntimeException('Não foi possível criar diretório de backup.');
$schema=$db->query('SHOW CREATE TABLE contratos_odometros')->fetch(PDO::FETCH_NUM);
$arquivo=$backup.'/contratos-odometros-schema-'.date('Ymd-His').'-'.bin2hex(random_bytes(3)).'.sql';
if(file_put_contents($arquivo,$schema[1].";\n")===false) throw new RuntimeException('Backup do schema falhou.');
$migration=require dirname(__DIR__).'/app/Database/migrations/'.$nome;
$migration->up();
$q=$db->prepare('INSERT IGNORE INTO migrations (migration) VALUES (?)');$q->execute([$nome]);
echo "Migration aplicada. Nenhuma fatura histórica foi gerada.\n";
echo "Emissão disponível para todas as locadoras nos próximos registros de odômetro com km excedente apurado em contratos com Km Controlado.\n";
