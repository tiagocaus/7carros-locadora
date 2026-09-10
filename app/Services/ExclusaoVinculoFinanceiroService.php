<?php
namespace App\Services;

use App\Core\Auth;
use App\Helpers\FilialHelper;
use App\Helpers\FileHelper;
use App\Models\{Model, Contrato, Locacao, ExclusaoVinculoFinanceiro};

class ExclusaoVinculoFinanceiroService
{
    private ExclusaoVinculoFinanceiro $model;

    public function __construct()
    {
        $this->model = new ExclusaoVinculoFinanceiro();
    }

    private function validar(string $tipo, array $estado): void
    {
        $modulo = ExclusaoVinculoFinanceiro::tabela($tipo);
        if (!Auth::can($modulo . '.excluir') || !FilialHelper::temAcessoFilial($estado['entidade']['id_matriz_filial_retirada'] ?? null)) {
            throw new \DomainException(t('modules.exclusao_financeiro.forbidden'), 403);
        }
        if ($estado['financeiro'] && !Auth::can('financeiro.excluir')) {
            throw new \DomainException(t('modules.exclusao_financeiro.permission'), 403);
        }
        foreach ($estado['financeiro'] as $f) {
            if (!FilialHelper::temAcessoFilial($f['id_matriz_filial'] ?? null)) {
                throw new \DomainException(t('modules.exclusao_financeiro.forbidden'), 403);
            }
            // Um dependente associado a outra operacao nao pode ser apagado em cascata.
            $outraFk = $tipo === 'contrato' ? 'id_locacao' : 'id_contrato';
            if (!empty($f[$outraFk]) || (!empty($f['id_' . $tipo]) && (int) $f['id_' . $tipo] !== (int) $estado['entidade']['id'])) {
                throw new \DomainException(t('modules.exclusao_financeiro.shared'), 422);
            }
        }
        if ($estado['promissorias']) {
            throw new \DomainException(t('modules.exclusao_financeiro.promissory'), 422);
        }
    }

    public static function resumo(array $financeiro): array
    {
        $r = ['aberto' => 0, 'pago' => 0, 'total' => 0, 'quantidade' => count($financeiro), 'tipos' => []];
        foreach ($financeiro as $f) {
            $valor = (int) round((float) ($f['valor_total'] ?? 0) * 100);
            $status = $f['pago'] === 'S' ? 'pago' : 'aberto';
            $r[$status] += $valor;
            $r['total'] += $valor;
            $tipo = $f['tipo'];
            $r['tipos'][$tipo] ??= ['aberto' => 0, 'pago' => 0, 'total' => 0];
            $r['tipos'][$tipo][$status] += $valor;
            $r['tipos'][$tipo]['total'] += $valor;
        }
        return $r; // Valores em centavos, sem saldo liquido entre receitas e despesas.
    }

    private function referencia(string $tipo, array $estado): string
    {
        // Apenas os dados que o usuario esta autorizando; mudancas de holds nao alteram o resumo.
        return hash('sha256', json_encode([$tipo, $estado['entidade']['id'], $estado['entidade']['chave'], $estado['financeiro'], $estado['itens'], $estado['encerramentos']], JSON_THROW_ON_ERROR));
    }

    public function preview(string $tipo, int $id): array
    {
        $estado = $this->model->capturar($tipo, $id);
        $this->validar($tipo, $estado);
        $referencia = $this->referencia($tipo, $estado);
        $token = bin2hex(random_bytes(24));
        $_SESSION['exclusao_financeiro'][$tipo . ':' . $id] = ['token' => $token, 'referencia' => $referencia, 'expira' => time() + 900];
        return ['codigo' => $estado['entidade']['codigo'], 'resumo' => self::resumo($estado['financeiro']),
            'referencia' => $token, 'exige_motivo' => (bool) array_filter($estado['encerramentos'], fn($e) => !empty($e['id_financeiro_ajuste']))];
    }

    private function conferir(string $tipo, int $id, string $token, array $estado): void
    {
        $previa = $_SESSION['exclusao_financeiro'][$tipo . ':' . $id] ?? [];
        if (!$token || ($previa['expira'] ?? 0) < time() || !hash_equals($previa['token'] ?? '', $token)
            || !hash_equals($previa['referencia'] ?? '', $this->referencia($tipo, $estado))) {
            throw new \DomainException(t('modules.exclusao_financeiro.changed'), 409);
        }
    }

    protected function prepararGateways(string $tipo, int $id, array $estado): void
    {
        $chave = $estado['entidade']['chave'];
        $holds = new AuthorizationHoldReleaseService();
        if ($tipo === 'contrato') $holds->liberarDoContrato($id, $chave);
        else $holds->liberarDaLocacao($id, $chave);
        $sync = new PagamentoLinkSyncService();
        foreach ($estado['financeiro'] as $f) {
            $sync->prepararExclusao((int) $f['id'], $chave);
        }
    }

    protected function auditar(string $mensagem, array $campos): void
    {
        AuditLogService::registrarComCamposNaTransacao(Model::sharedMysqli(), $mensagem, $campos);
    }

    public function excluir(string $tipo, int $id, string $token, string $motivo = ''): void
    {
        $estado = $this->model->capturar($tipo, $id);
        $this->validar($tipo, $estado);
        $this->conferir($tipo, $id, $token, $estado);
        $temAjuste = (bool) array_filter($estado['encerramentos'], fn($e) => !empty($e['id_financeiro_ajuste']));
        $motivo = trim($motivo);
        if ($temAjuste && ($motivo === '' || mb_strlen($motivo) > 1000)) {
            throw new \DomainException(t('modules.exclusao_financeiro.reason_required'), 422);
        }
        // Efeitos externos nao sao revertidos pelo MySQL; persistir cancelamentos para retentativas.
        $this->prepararGateways($tipo, $id, $estado);
        $db = Model::sharedMysqli();
        $db->begin_transaction();
        try {
            $estado = $this->model->capturar($tipo, $id, true);
            $this->validar($tipo, $estado);
            $this->conferir($tipo, $id, $token, $estado);
            // Uma cobranca criada entre o cancelamento externo e o lock exige nova tentativa.
            foreach ($estado['transacoes'] as $transacao) {
                if ($transacao['type'] === 'charge' && !in_array($transacao['status'], ['paid', 'cancelled', 'refunded', 'failed', 'expired'], true)) {
                    throw new \DomainException(t('modules.exclusao_financeiro.changed'), 409);
                }
            }
            $codigo = $estado['entidade']['codigo'];
            $usuario = $_SESSION['user_name'] ?? 'Sistema';
            foreach ($estado['financeiro'] as $f) {
                $campos = [];
                foreach ($f as $campo => $valor) {
                    if ($campo !== 'chave' && $valor !== null) $campos[] = AuditLogService::campo($campo, $valor, null, 'Financeiro excluido');
                }
                $campos[] = AuditLogService::campo('Origem', "$tipo #$codigo (ID $id)", null);
                $campos[] = AuditLogService::campo('Itens', json_encode(array_values(array_filter($estado['itens'], fn($item) => (int) $item['id_financeiro'] === (int) $f['id'])), JSON_UNESCAPED_UNICODE), null);
                $campos[] = AuditLogService::campo('Referencias de pagamento', json_encode(array_values(array_filter($estado['transacoes'], fn($tr) => (int) $tr['id_financeiro'] === (int) $f['id'])), JSON_UNESCAPED_UNICODE), null);
                $campos[] = AuditLogService::campo('Links de pagamento', json_encode(array_values(array_filter($estado['links'], fn($link) => (int) $link['id_financeiro'] === (int) $f['id'])), JSON_UNESCAPED_UNICODE), null);
                if ($motivo !== '') $campos[] = AuditLogService::campo('Motivo', $motivo, null);
                $this->auditar(mb_substr("$usuario, excluiu o lancamento financeiro #{$f['id']} ao excluir $tipo [$codigo]", 0, 255), $campos);
            }
            // Filhos de taxas antes dos pais, para nao perder auditoria nem contar cascatas duas vezes.
            $pendentes = array_column($estado['financeiro'], null, 'id');
            while ($pendentes) {
                $pais = array_filter(array_column($pendentes, 'id_financeiro_taxa_origem'));
                $folhas = array_diff(array_keys($pendentes), $pais);
                if (!$folhas) throw new \RuntimeException('Ciclo de dependencias financeiras');
                foreach ($folhas as $fid) {
                    $this->model->desvincularAjuste((int) $fid);
                    $this->model->apagarFinanceiro((int) $fid);
                    unset($pendentes[$fid]);
                }
            }
            $arquivos = $this->model->removerChecklists($tipo, $id);
            $entidadeModel = $tipo === 'contrato' ? new Contrato() : new Locacao();
            if ($entidadeModel->deletar($id) !== 1) throw new \RuntimeException('Registro nao removido');
            $resumo = self::resumo($estado['financeiro']);
            $campos = [AuditLogService::campo('ID', $id, null)];
            foreach (['aberto', 'pago', 'total'] as $campo) $campos[] = AuditLogService::campo($campo, currency_format($resumo[$campo] / 100, true), null, 'Financeiro excluido');
            $campos[] = AuditLogService::campo('Quantidade', $resumo['quantidade'], null);
            if ($motivo !== '') $campos[] = AuditLogService::campo('Motivo', $motivo, null);
            $this->auditar(mb_substr("$usuario, excluiu $tipo [$codigo]", 0, 255), $campos);
            $db->commit();
            unset($_SESSION['exclusao_financeiro'][$tipo . ':' . $id]);
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
        try {
            \App\Core\Cache::forget('notification_counts', $estado['entidade']['chave']);
        } catch (\Throwable $e) {
            error_log('[ExclusaoVinculoFinanceiro] Falha ao invalidar cache de notificacoes');
        }
        foreach ($arquivos as $arquivo) {
            try {
                if (FileHelper::exists($arquivo, $estado['entidade']['chave']) && !FileHelper::delete($arquivo, $estado['entidade']['chave'])) throw new \RuntimeException('Falha de limpeza');
            } catch (\Throwable $e) {
                error_log('[ExclusaoVinculoFinanceiro] Limpeza pendente: ' . json_encode(['chave' => $estado['entidade']['chave'], 'arquivo' => $arquivo]));
            }
        }
    }
}
