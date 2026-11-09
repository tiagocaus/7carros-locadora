<?php
/** Job com dependências em memória: sem rede, banco ou mensagens. */
define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/vendor/autoload.php';
require APP_ROOT . '/app/Helpers/helpers.php';

use App\Crons\Jobs\CoraPaymentReconcileJob;
use App\Models\FinanceiroTransacao;
use App\Services\CoraPaymentReconciliationService;
use App\Services\Gateways\CoraGateway;

function jobCoraCheck(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
class CoraJobTransactions extends FinanceiroTransacao {
    public array $rows = [];
    public function __construct() {}
    public function listarCoraPendentesParaConciliacao(int $aposId, int $limite = 50): array {
        return array_slice(array_values(array_filter($this->rows, fn($row) => $row['id'] > $aposId)), 0, $limite);
    }
}
class CoraJobReconciler extends CoraPaymentReconciliationService {
    public array $seen = [];
    public function criarGateway(array $transaction): CoraGateway { return new CoraGateway([]); }
    public function confirmar(array $transaction, CoraGateway $gateway, array $event): array {
        $this->seen[] = $transaction['id'];
        jobCoraCheck($event['event'] === 'invoice.cron', 'Origem do job incorreta');
        if ($transaction['id'] === 1) throw new RuntimeException('segredo-que-nao-pode-aparecer');
        return ['success'=>true, 'status'=>$transaction['id'] === 51 ? 'paid' : 'pending'];
    }
}
$path = tempnam(sys_get_temp_dir(), 'cora-job-test-');
$log = tempnam(sys_get_temp_dir(), 'cora-job-log-');
ini_set('error_log', $log);
$model = new CoraJobTransactions();
$service = new CoraJobReconciler();
for ($i=1; $i<=51; $i++) $model->rows[] = ['id'=>$i,'chave'=>'fixture','id_gateway'=>1,'id_financeiro'=>$i,'external_id'=>'inv_'.$i];
try {
    $job = new CoraPaymentReconcileJob($path, $model, $service);
    ob_start(); $first = $job->run(); ob_end_clean();
    jobCoraCheck($first['data'] === ['verificadas'=>50,'confirmadas'=>0,'erros'=>1], 'Falha bloqueou processamento do lote');
    jobCoraCheck(json_decode(file_get_contents($path),true)['last_id'] === 50, 'Não persistiu cursor');
    ob_start(); $second = (new CoraPaymentReconcileJob($path,$model,$service))->run(); ob_end_clean();
    jobCoraCheck($second['data']['verificadas'] === 1 && $second['data']['confirmadas'] === 1, 'Não retomou após cursor');
    jobCoraCheck(json_decode(file_get_contents($path),true)['last_id'] === 0, 'Não reiniciou ciclo');
    ob_start(); $job->run(); ob_end_clean();
    jobCoraCheck(count(array_filter($service->seen, fn($id) => $id === 1)) === 2, 'Falha antiga não foi tentada novamente');
    file_put_contents($path, '{"last_id":99999}');
    ob_start(); $wrapped = $job->run(); ob_end_clean();
    jobCoraCheck($wrapped['data']['verificadas'] === 50, 'Cursor além do fim impediu processamento');
    jobCoraCheck(!str_contains(file_get_contents($log),'segredo-que-nao-pode-aparecer'), 'Log expôs mensagem bruta');
    echo "[OK] Job Cora: lotes, cursor persistido, retomada, falha isolada, repetição e logs sanitizados.\n";
} finally {
    unlink($path); unlink($log);
}
