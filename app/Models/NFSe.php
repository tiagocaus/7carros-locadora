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
        return $this->qb
            ->table('nfse')
            ->insert($dados);
    }

    /**
     * Busca NFS-e por ID com dados completos
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->qb
            ->table('nfse')
            ->where('id', '=', $id)
            ->first();
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
        $query = $this->qb
            ->table('nfse', 'n')
            ->select([
                'n.id',
                'n.numero',
                'n.serie',
                'n.tomador_nome',
                'n.valor_servicos',
                'n.ambiente',
                'n.status',
                'n.tipo_emissao',
                'n.data_emissao',
                'n.created_at',
                'mf.nome_fantasia AS filial_nome',
            ])
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
        $buildQuery = function (?string $status = null) use ($filialWhere, $filialParams, $dataInicio, $dataFim, $filialId) {
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

            return $q->count();
        };

        $autorizada = $buildQuery('autorizada');
        $rejeitada = $buildQuery('rejeitada');
        $cancelada = $buildQuery('cancelada');
        $pendente = $buildQuery('pendente');
        $processando = $buildQuery('processando');

        return [
            'total' => $buildQuery(),
            'autorizada' => $autorizada,
            'rejeitada' => $rejeitada,
            'cancelada' => $cancelada,
            'pendente' => $pendente,
            'processando' => $processando,
            'autorizadas' => $autorizada,
            'rejeitadas' => $rejeitada,
            'canceladas' => $cancelada,
            'pendentes' => $pendente + $processando,
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
                'data_emissao' => $dados['data_emissao'] ?? date('Y-m-d H:i:s'),
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
        return $this->qb
            ->table('nfse')
            ->where('id', '=', $id)
            ->update([
                'status' => 'cancelada',
                'data_cancelamento' => date('Y-m-d H:i:s'),
                'motivo_cancelamento' => $motivo,
            ]);
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
                'email_enviado' => date('Y-m-d H:i:s'),
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
        return $this->qb
            ->table('nfse')
            ->withoutChave()
            ->where('tipo_emissao', '=', 'betha')
            ->where('status', '=', 'processando')
            ->whereNotNull('protocolo')
            ->whereRaw('created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)')
            ->orderBy('created_at', 'ASC')
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
