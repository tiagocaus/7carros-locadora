<?php
namespace App\Models;

/** Leituras bloqueaveis da exclusao de contrato/locacao, sempre no tenant atual. */
class ExclusaoVinculoFinanceiro extends Model
{
    public static function tabela(string $tipo): string
    {
        return match ($tipo) {
            'contrato' => 'contratos',
            'locacao' => 'locacoes',
            default => throw new \InvalidArgumentException('Tipo de vinculo invalido'),
        };
    }

    public function capturar(string $tipo, int $id, bool $bloquear = false): array
    {
        $tabela = self::tabela($tipo);
        $fk = 'id_' . $tipo;
        $q = $this->qb->table($tabela)->where('id', '=', $id);
        $entidade = ($bloquear ? $q->lockForUpdate() : $q)->first();
        if (!$entidade) {
            throw new \DomainException(t('modules.exclusao_financeiro.not_found'), 404);
        }
        $ler = function (string $table, string $col, array $ids) use ($bloquear): array {
            if (!$ids) return [];
            $q = $this->qb->table($table)->whereIn($col, $ids)->orderBy('id');
            if ($table === 'financeiro_transacoes') {
                $q->select(['id', 'id_financeiro', 'gateway', 'external_id', 'type', 'status', 'amount', 'fee', 'paid_at']);
            }
            if ($table === 'pagamentos_links') {
                $q->select(['id', 'id_financeiro', 'status', 'id_transacao_paga']);
            }
            return ($bloquear ? $q->lockForUpdate() : $q)->get();
        };
        $financeiro = $ler('financeiro', $fk, [$id]);
        $caucoes = $ler($tabela . '_caucoes', $fk, [$id]);
        $encerramentos = $tipo === 'contrato' ? $ler('contratos_encerramentos', $fk, [$id]) : [];
        $extras = [];
        foreach ($caucoes as $c) {
            $extras[] = $c['id_financeiro_entrada'];
            $extras[] = $c['id_financeiro_devolucao'];
        }
        foreach ($encerramentos as $e) $extras[] = $e['id_financeiro_ajuste'];
        $financeiro = array_merge($financeiro, $ler('financeiro', 'id', array_values(array_filter($extras))));
        $porId = [];
        foreach ($financeiro as $f) $porId[(int) $f['id']] = $f;
        // Parcelas filhas e despesas de taxas tambem fazem parte da exclusao.
        $novos = array_keys($porId);
        while ($novos) {
            $dependentes = array_merge($ler('financeiro', 'id_financeiro_origem', $novos), $ler('financeiro', 'id_financeiro_taxa_origem', $novos));
            $novos = [];
            foreach ($dependentes as $f) {
                if (!isset($porId[(int) $f['id']])) {
                    $porId[(int) $f['id']] = $f;
                    $novos[] = (int) $f['id'];
                }
            }
        }
        ksort($porId);
        $ids = array_keys($porId);
        return [
            'entidade' => $entidade, 'financeiro' => array_values($porId),
            'itens' => $ler('financeiro_itens', 'id_financeiro', $ids),
            'transacoes' => $ler('financeiro_transacoes', 'id_financeiro', $ids),
            'links' => $ler('pagamentos_links', 'id_financeiro', $ids),
            'promissorias' => $ler('promissorias', 'id_financeiro', $ids),
            'encerramentos' => $encerramentos,
        ];
    }

    /** Remove dados de checklist agora e retorna arquivos para limpeza apos commit. */
    public function removerChecklists(string $tipo, int $id): array
    {
        self::tabela($tipo);
        $rows = $this->qb->table('checklist')->where('id_' . $tipo, '=', $id)->lockForUpdate()->get();
        $arquivos = [];
        foreach ($rows as $row) {
            foreach (['saida', 'entrada'] as $etapa) {
                foreach (json_decode($row['vistoria_' . $etapa] ?? '[]', true) ?: [] as $item) {
                    if (!empty($item['img'])) $arquivos[] = $item['img'];
                }
                if (!empty($row['assinatura_' . $etapa])) $arquivos[] = $row['assinatura_' . $etapa];
            }
            (new Checklist())->excluir((int) $row['id']);
        }
        return array_values(array_unique($arquivos));
    }

    public function apagarFinanceiro(int $id): void
    {
        if ($this->qb->table('financeiro')->where('id', '=', $id)->delete() !== 1) {
            throw new \RuntimeException('Lancamento nao removido');
        }
    }

    public function desvincularAjuste(int $id): void
    {
        $this->qb->table('contratos_encerramentos')->where('id_financeiro_ajuste', '=', $id)
            ->update(['id_financeiro_ajuste' => null]);
    }
}
