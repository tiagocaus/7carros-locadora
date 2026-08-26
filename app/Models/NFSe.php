<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\DetectsCrossTenant;

/**
 * Model NFSe
 *
 * Gerencia registros de Notas Fiscais de Servico Eletronicas.
 *
 * Status:
 * - pendente -> registro criado, XML nao enviado
 * - processando -> XML enviado, aguardando resposta
 * - autorizada -> NFS-e emitida com sucesso
 * - rejeitada -> NFS-e rejeitada pela SEFIN/prefeitura
 * - cancelada -> NFS-e cancelada
 * - substituida -> NFS-e cancelada por substituicao
 */
class NFSe extends Model
{
    use Auditable;
    use DetectsCrossTenant;

    protected function getEntidadeAuditoria(): string
    {
        return 'a NFS-e';
    }

    protected function getCampoIdentificador(): string
    {
        return 'numero';
    }

    /**
     * Cria registro de NFS-e
     */
    public function criar(array $dados): int
    {
        // Mantem a emissao operacional durante o intervalo entre o upload do
        // codigo e a execucao da migration no servidor.
        foreach (['tomador_tipo', 'tomador_pais'] as $colunaNova) {
            if (array_key_exists($colunaNova, $dados) && !$this->colunaExiste($colunaNova)) {
                unset($dados[$colunaNova]);
            }
        }

        return $this->qb
            ->table('nfse')
            ->insert($dados);
    }

    /**
     * Busca NFS-e por ID com dados completos
     */
    public function buscarPorId(int $id): ?array
    {
        $nfse = $this->qb
            ->table('nfse')
            ->where('id', '=', $id)
            ->first();

        if (!$nfse) {
            return null;
        }

        $endereco = json_decode((string) ($nfse['tomador_endereco'] ?? ''), true);
        $paisEndereco = is_array($endereco) ? strtoupper(trim((string) ($endereco['pais'] ?? ''))) : '';
        if ($paisEndereco !== '' && $paisEndereco !== 'BR') {
            $nfse['tomador_tipo'] = $nfse['tomador_tipo'] ?? 'ES';
            $nfse['tomador_pais'] = $nfse['tomador_pais'] ?? $paisEndereco;
        }

        return $nfse;
    }

    private function colunaExiste(string $coluna): bool
    {
        static $cache = [];
        if (!array_key_exists($coluna, $cache)) {
            $tabela = 'nfse';
            $stmt = $this->getMysqli()->prepare(
                'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
            );
            $stmt->bind_param('ss', $tabela, $coluna);
            $stmt->execute();
            $cache[$coluna] = $stmt->get_result()->num_rows > 0;
            $stmt->close();
        }

        return $cache[$coluna];
    }

    /**
     * Busca NFS-e vinculada a um financeiro
     */
    public function buscarPorFinanceiro(int $idFinanceiro): ?array
    {
        return $this->qb
            ->table('nfse')
            ->where('id_financeiro', '=', $idFinanceiro)
            ->whereRaw("status != 'cancelada'")
            ->first();
    }

    /**
     * Busca outra NFS-e do tenant que ja utilize a mesma chave de acesso.
     */
    public function buscarPorChaveAcesso(string $chaveAcesso, ?int $ignorarId = null): ?array
    {
        $query = $this->qb
            ->table('nfse')
            ->where('chave_acesso', '=', $chaveAcesso);

        if ($ignorarId !== null) {
            $query->where('id', '!=', $ignorarId);
        }

        return $query->first();
    }

    /**
     * Lista NFS-e com paginacao e filtros
     */
    public function listarPaginado(
        int $page,
        int $perPage,
        string $search = '',
        string $filialWhere = '',
        array $filialParams = [],
        string $filialId = '',
        string $status = '',
        string $dataInicio = '',
        string $dataFim = '',
        string $ambiente = ''
    ): array {
        $campos = [
            'n.id',
            'n.numero',
            'n.serie',
            'n.tomador_nome',
            'n.valor_servicos',
            'n.ambiente',
            'n.status',
            'n.tipo_emissao',
            'n.protocolo',
            'n.data_emissao',
            'n.created_at',
            'n.updated_at',
            'mf.nome_fantasia AS filial_nome',
        ];
        if ($this->colunaExiste('cancelamento_status')) {
            $campos[] = 'n.cancelamento_status';
        }

        $query = $this->qb
            ->table('nfse', 'n')
            ->select($campos)
            ->leftJoin('matrizes_filiais', 'mf', 'n.id_matriz_filial', '=', 'mf.id');

        // Filtro de filial (permissoes do usuario)
        if (!empty($filialWhere)) {
            $filialWherePrefixed = str_replace('id_matriz_filial', 'n.id_matriz_filial', $filialWhere);
            $query->whereRaw($filialWherePrefixed, $filialParams);
        }

        if (!empty($status)) {
            $query->where('n.status', '=', $status);
        }

        if (!empty($dataInicio)) {
            $query->where('n.data_emissao', '>=', $dataInicio . ' 00:00:00');
        }

        if (!empty($dataFim)) {
            $query->where('n.data_emissao', '<=', $dataFim . ' 23:59:59');
        }

        if (!empty($filialId) && is_numeric($filialId)) {
            $query->where('n.id_matriz_filial', '=', (int) $filialId);
        }

        if (!empty($ambiente) && is_numeric($ambiente)) {
            $query->where('n.ambiente', '=', (int) $ambiente);
        }

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('n.tomador_nome', 'LIKE', $searchTerm)
                  ->orWhere('n.tomador_cpf_cnpj', 'LIKE', $searchTerm)
                  ->orWhere('n.numero', 'LIKE', $searchTerm)
                  ->orWhere('n.codigo_verificacao', 'LIKE', $searchTerm);
            });
        }

        return $query
            ->orderByDesc('n.data_emissao')
            ->orderByDesc('n.id')
            ->paginate($page, $perPage)
            ->get();
    }

    /**
     * Conta total de NFS-e com filtros
     */
    public function contar(
        string $search = '',
        string $filialWhere = '',
        array $filialParams = [],
        string $filialId = '',
        string $status = '',
        string $dataInicio = '',
        string $dataFim = '',
        string $ambiente = ''
    ): int {
        $query = $this->qb->table('nfse');

        // Filtro de filial (permissoes do usuario)
        if (!empty($filialWhere)) {
            $query->whereRaw($filialWhere, $filialParams);
        }

        if (!empty($status)) {
            $query->where('status', '=', $status);
        }

        if (!empty($dataInicio)) {
            $query->where('data_emissao', '>=', $dataInicio . ' 00:00:00');
        }

        if (!empty($dataFim)) {
            $query->where('data_emissao', '<=', $dataFim . ' 23:59:59');
        }

        if (!empty($filialId) && is_numeric($filialId)) {
            $query->where('id_matriz_filial', '=', (int) $filialId);
        }

        if (!empty($ambiente) && is_numeric($ambiente)) {
            $query->where('ambiente', '=', (int) $ambiente);
        }

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('tomador_nome', 'LIKE', $searchTerm)
                  ->orWhere('tomador_cpf_cnpj', 'LIKE', $searchTerm)
                  ->orWhere('numero', 'LIKE', $searchTerm);
            });
        }

        return $query->count();
    }

    /**
     * Estatisticas para dashboard
     */
    public function estatisticas(string $filialWhere = '', array $filialParams = [], string $dataInicio = '', string $dataFim = '', string $filialId = ''): array
    {
        $aplicarFiltros = function (?string $status = null, bool $usarMesAtualPadrao = false) use ($filialWhere, $filialParams, $dataInicio, $dataFim, $filialId) {
            $q = $this->qb
                ->table('nfse');

            if ($status !== null) {
                $q->where('status', '=', $status);
            }
            if (!empty($filialWhere)) {
                $q->whereRaw($filialWhere, $filialParams);
            }
            if (!empty($dataInicio)) {
                $q->where('data_emissao', '>=', $dataInicio . ' 00:00:00');
            }
            if (!empty($dataFim)) {
                $q->where('data_emissao', '<=', $dataFim . ' 23:59:59');
            }
            if (!empty($filialId) && is_numeric($filialId)) {
                $q->where('id_matriz_filial', '=', (int) $filialId);
            }

            if ($usarMesAtualPadrao && empty($dataInicio) && empty($dataFim)) {
                $q->where('data_emissao', '>=', \App\Helpers\DateHelper::todayForDatabase('Y-m-01') . ' 00:00:00')
                  ->where('data_emissao', '<=', \App\Helpers\DateHelper::todayForDatabase('Y-m-t') . ' 23:59:59');
            }

            return $q;
        };

        $buildQuery = function (?string $status = null) use ($aplicarFiltros) {
            $q = $aplicarFiltros($status);
            return $q->count();
        };

        $valorAutorizadas = $aplicarFiltros('autorizada', true)
            ->sum('valor_servicos');

        $autorizada = $buildQuery('autorizada');
        $rejeitada = $buildQuery('rejeitada');
        $cancelada = $buildQuery('cancelada');
        $substituida = $buildQuery('substituida');
        $pendente = $buildQuery('pendente');
        $processando = $buildQuery('processando');

        return [
            'total' => $buildQuery(),
            'autorizada' => $autorizada,
            'rejeitada' => $rejeitada,
            'cancelada' => $cancelada,
            'substituida' => $substituida,
            'pendente' => $pendente,
            'processando' => $processando,
            'autorizadas' => $autorizada,
            'rejeitadas' => $rejeitada,
            'canceladas' => $cancelada,
            'substituidas' => $substituida,
            'pendentes' => $pendente + $processando,
            'valor_autorizadas' => $valorAutorizadas,
        ];
    }

    /**
     * Atualiza status de uma NFS-e
     */
    public function atualizarStatus(int $id, string $status, ?string $motivo = null, ?string $codigo = null): int
    {
        $dados = ['status' => $status];

        if ($motivo !== null) {
            $dados['motivo_rejeicao'] = $motivo;
        }

        if ($codigo !== null) {
            $dados['codigo_rejeicao'] = $codigo;
        }

        return $this->qb
            ->table('nfse')
            ->where('id', '=', $id)
            ->update($dados);
    }

    /**
     * Remove somente dados de uma autorizacao conciliada incorretamente.
     * O XML enviado e os dados da tentativa local sao preservados para auditoria.
     */
    public function marcarConflitoDps(int $id, string $mensagem): int
    {
        return $this->qb
            ->table('nfse')
            ->where('id', '=', $id)
            ->update([
                'status' => 'rejeitada',
                'codigo_rejeicao' => 'DPS_CONFLITO',
                'motivo_rejeicao' => $mensagem,
                'chave_acesso' => null,
                'codigo_verificacao' => null,
                'xml_retorno' => null,
                'pdf_url' => null,
            ]);
    }

    /**
     * Atualiza NFS-e apos autorizacao
     */
    public function atualizarAutorizada(int $id, array $dados): int
    {
        return $this->qb
            ->table('nfse')
            ->where('id', '=', $id)
            ->update([
                'status' => 'autorizada',
                'numero' => $dados['numero'] ?? null,
                'codigo_verificacao' => $dados['codigo_verificacao'] ?? null,
                'chave_acesso' => $dados['chave_acesso'] ?? null,
                'xml_retorno' => $dados['xml_retorno'] ?? null,
                'data_emissao' => $dados['data_emissao'] ?? now(),
                'aliquota_ibs' => $dados['aliquota_ibs'] ?? 0,
                'valor_ibs' => $dados['valor_ibs'] ?? 0,
                'aliquota_cbs' => $dados['aliquota_cbs'] ?? 0,
                'valor_cbs' => $dados['valor_cbs'] ?? 0,
                'codigo_rejeicao' => null,
                'motivo_rejeicao' => null,
            ]);
    }

    /**
     * Atualiza NFS-e que foi recepcionada e segue em processamento.
     */
    public function atualizarProcessando(int $id, array $dados): int
    {
        return $this->qb
            ->table('nfse')
            ->where('id', '=', $id)
            ->update([
                'status' => 'processando',
                'protocolo' => $dados['protocolo'] ?? null,
                'xml_retorno' => $dados['xml_retorno'] ?? null,
            ]);
    }

    /**
     * Atualiza NFS-e apos cancelamento
     */
    public function atualizarCancelada(int $id, string $motivo): int
    {
        $dados = [
            'status' => 'cancelada',
            'data_cancelamento' => now(),
            'motivo_cancelamento' => $motivo,
        ];
        if ($this->colunaExiste('cancelamento_status')) {
            $dados['cancelamento_status'] = 'concluido';
        }

        return $this->qb
            ->table('nfse')
            ->where('id', '=', $id)
            ->update($dados);
    }

    /**
     * Atualiza a situacao fiscal confirmada pela API de eventos do ADN.
     */
    public function atualizarSituacaoFiscalExterna(
        int $id,
        string $situacao,
        ?string $dataEvento = null,
        ?string $motivo = null
    ): int {
        if (!$this->colunaExiste('situacao_fiscal') || !$this->colunaExiste('situacao_fiscal_consultada_em')) {
            return 0;
        }

        $status = $situacao === 'S' ? 'substituida' : 'cancelada';
        $motivoPadrao = $situacao === 'S'
            ? 'NFS-e cancelada por substituição registrada externamente.'
            : 'Cancelamento registrado externamente.';

        return $this->qb
            ->table('nfse')
            ->where('id', '=', $id)
            ->where('status', '=', 'autorizada')
            ->update([
                'status' => $status,
                'situacao_fiscal' => $situacao,
                'situacao_fiscal_consultada_em' => now(),
                'data_cancelamento' => $dataEvento ?: now(),
                'motivo_cancelamento' => $motivo ?: $motivoPadrao,
                'cancelamento_status' => 'concluido',
            ]);
    }

    /**
     * Registra uma consulta bem-sucedida em que a nota continua normal.
     */
    public function registrarSituacaoFiscalNormal(int $id): int
    {
        if (!$this->colunaExiste('situacao_fiscal') || !$this->colunaExiste('situacao_fiscal_consultada_em')) {
            return 0;
        }

        return $this->qb
            ->table('nfse')
            ->where('id', '=', $id)
            ->where('status', '=', 'autorizada')
            ->update([
                'situacao_fiscal' => 'N',
                'situacao_fiscal_consultada_em' => now(),
            ]);
    }

    /**
     * Registra a recepcao assincrona de um pedido de cancelamento Betha.
     */
    public function marcarCancelamentoProcessando(int $id, string $motivo, string $protocolo): int
    {
        return $this->qb
            ->table('nfse')
            ->where('id', '=', $id)
            ->where('status', '=', 'autorizada')
            ->update([
                'cancelamento_status' => 'processando',
                'cancelamento_protocolo' => $protocolo,
                'cancelamento_solicitado_em' => now(),
                'motivo_cancelamento' => $motivo,
            ]);
    }

    /**
     * Mantem a nota autorizada quando a Betha rejeita o cancelamento.
     */
    public function marcarErroCancelamento(int $id): int
    {
        return $this->qb
            ->table('nfse')
            ->where('id', '=', $id)
            ->where('status', '=', 'autorizada')
            ->update(['cancelamento_status' => 'erro']);
    }

    /**
     * Incrementa tentativas de envio
     */
    public function incrementarTentativas(int $id): int
    {
        $mysqli = $this->getMysqli();

        $stmt = $mysqli->prepare(
            "UPDATE nfse SET tentativas_envio = tentativas_envio + 1 WHERE id = ?"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        return $affected;
    }

    /**
     * Marca email como enviado
     */
    public function marcarEmailEnviado(int $id, string $email): int
    {
        return $this->qb
            ->table('nfse')
            ->where('id', '=', $id)
            ->update([
                'email_enviado' => now(),
                'email_destinatario' => $email,
            ]);
    }

    /**
     * Salva URL do PDF
     */
    public function salvarPdfUrl(int $id, string $pdfUrl): int
    {
        return $this->qb
            ->table('nfse')
            ->where('id', '=', $id)
            ->update(['pdf_url' => $pdfUrl]);
    }

    /**
     * Salva XML de envio
     */
    public function salvarXmlEnvio(int $id, string $xml): int
    {
        return $this->qb
            ->table('nfse')
            ->where('id', '=', $id)
            ->update(['xml_envio' => $xml]);
    }

    /**
     * Marca XML como pronto para envio ao provedor.
     */
    public function marcarProntaParaEnvio(int $id, string $xml): int
    {
        return $this->qb
            ->table('nfse')
            ->where('id', '=', $id)
            ->update([
                'xml_envio' => $xml,
                'status' => 'processando',
            ]);
    }

    /**
     * Atualiza dados principais antes de reenviar XML regenerado.
     */
    public function atualizarParaReenvio(int $id, array $dados): int
    {
        return $this->qb
            ->table('nfse')
            ->where('id', '=', $id)
            ->update([
                'numero' => $dados['numero'] ?? null,
                'serie' => $dados['serie'] ?? null,
                'xml_envio' => $dados['xml_envio'] ?? null,
                'status' => 'processando',
                'motivo_rejeicao' => null,
                'codigo_rejeicao' => null,
            ]);
    }

    // ==========================================
    // METODOS PARA CRON (cross-tenant)
    // ==========================================

    /**
     * Busca NFS-e autorizadas sem email enviado
     */
    public function buscarPendentesEmail(int $limite = 30): array
    {
        return $this->qb
            ->table('nfse', 'n')
            ->select(['n.*'])
            ->join('nfse_configuracoes', 'nc', 'n.id_matriz_filial', '=', 'nc.id_matriz_filial')
            ->withoutChave()
            ->where('n.status', '=', 'autorizada')
            ->whereNull('n.email_enviado')
            ->whereNotNull('n.tomador_email')
            ->where('nc.enviar_email', '=', 'S')
            ->orderBy('n.created_at', 'ASC')
            ->limit($limite)
            ->get();
    }

    /**
     * Busca NFS-e rejeitadas com erro recuperavel para reenvio
     */
    public function buscarRejeitadasRecuperaveis(int $limite = 20): array
    {
        $codigos = \App\Services\NFSe\NFSeErros::getCodigosRecuperaveis();

        return $this->qb
            ->table('nfse')
            ->withoutChave()
            ->where('status', '=', 'rejeitada')
            ->whereRaw('tentativas_envio < 3')
            ->whereIn('codigo_rejeicao', $codigos)
            ->orderBy('created_at', 'ASC')
            ->limit($limite)
            ->get();
    }

    /**
     * Busca NFS-e Betha em processamento para consulta de protocolo.
     */
    public function buscarBethaProcessando(int $limite = 20): array
    {
        $atividadeRecente = 'COALESCE(updated_at, created_at)';

        return $this->qb
            ->table('nfse')
            ->withoutChave()
            ->where('tipo_emissao', '=', 'betha')
            ->where('status', '=', 'processando')
            ->whereNotNull('protocolo')
            ->whereRaw("{$atividadeRecente} >= DATE_SUB(NOW(), INTERVAL 48 HOUR)")
            ->orderByRaw("{$atividadeRecente} ASC")
            ->limit($limite)
            ->get();
    }

    /**
     * Busca cancelamentos Betha aceitos e ainda nao confirmados pelo Ambiente Nacional.
     */
    public function buscarBethaCancelamentosProcessando(int $limite = 20): array
    {
        if (!$this->colunaExiste('cancelamento_status')
            || !$this->colunaExiste('cancelamento_protocolo')
            || !$this->colunaExiste('cancelamento_solicitado_em')) {
            return [];
        }

        return $this->qb
            ->table('nfse')
            ->withoutChave()
            ->where('tipo_emissao', '=', 'betha')
            ->where('status', '=', 'autorizada')
            ->where('cancelamento_status', '=', 'processando')
            ->whereNotNull('cancelamento_protocolo')
            ->whereRaw('cancelamento_solicitado_em >= DATE_SUB(NOW(), INTERVAL 48 HOUR)')
            ->orderBy('cancelamento_solicitado_em', 'ASC')
            ->limit($limite)
            ->get();
    }

    /**
     * Busca notas Betha autorizadas cuja situacao fiscal deve ser reconciliada no ADN.
     */
    public function buscarBethaSituacaoFiscalPendente(int $limite = 20): array
    {
        if (!$this->colunaExiste('situacao_fiscal') || !$this->colunaExiste('situacao_fiscal_consultada_em')) {
            return [];
        }

        return $this->qb
            ->table('nfse')
            ->withoutChave()
            ->where('tipo_emissao', '=', 'betha')
            ->where('status', '=', 'autorizada')
            ->whereNotNull('chave_acesso')
            ->whereRaw("(cancelamento_status IS NULL OR cancelamento_status != 'processando')")
            ->whereRaw('(situacao_fiscal_consultada_em IS NULL OR situacao_fiscal_consultada_em <= DATE_SUB(NOW(), INTERVAL 15 MINUTE))')
            ->orderByRaw('COALESCE(situacao_fiscal_consultada_em, data_emissao, created_at) ASC')
            ->limit($limite)
            ->get();
    }

    /**
     * Busca financeiros pagos sem NFS-e para emissao automatica
     */
    public function buscarFinanceirosParaEmissaoAuto(string $chave, int $idMatrizFilial, int $limite = 50): array
    {
        return $this->qb
            ->table('financeiro', 'f')
            ->select([
                'f.id',
                'f.id_cliente',
                'f.id_contrato',
                'f.id_locacao',
                'f.valor_total',
                'f.id_matriz_filial',
            ])
            ->withoutChave()
            ->where('f.chave', '=', $chave)
            ->where('f.id_matriz_filial', '=', $idMatrizFilial)
            ->where('f.pago', '=', 'S')
            ->where('f.tipo', '=', 'R')
            ->whereRaw('f.data_pago >= DATE_SUB(NOW(), INTERVAL 7 DAY)')
            ->whereRaw('NOT EXISTS (
                SELECT 1 FROM nfse n
                WHERE n.id_financeiro = f.id
                AND n.status != ?
            )', ['cancelada'])
            ->orderBy('f.data_pago', 'ASC')
            ->limit($limite)
            ->get();
    }
}
