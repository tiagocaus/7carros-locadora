<?php

namespace App\Models;

use App\Helpers\CodigoHelper;
use App\Helpers\DateHelper;

/**
 * Gerencia checklists/vistorias realizados nos veiculos.
 */
class Checklist extends Model
{
    public const STATUS_AVULSO_INICIADO = '1';
    public const STATUS_AVULSO_CONCLUIDO = '2';
    public const STATUS_VINCULADO_SAIDA_INICIADO = '3';
    public const STATUS_VINCULADO_SAIDA_CONCLUIDO = '4';
    public const STATUS_VINCULADO_ENTRADA_INICIADO = '5';
    public const STATUS_VINCULADO_ENTRADA_CONCLUIDO = '6';

    private const ETAPAS = ['saida', 'entrada'];

    public function listarPaginado(
        string $chave,
        int $page,
        int $perPage,
        string $search = '',
        ?string $filialWhere = null,
        array $filialParams = []
    ): array {
        $query = $this->qb
            ->table('checklist', 'c')
            ->select([
                'c.id',
                'c.codigo',
                'c.tipo',
                'c.status',
                'c.created_at',
                'COALESCE(c.data_entrada, c.data_saida, c.created_at) AS data_checklist',
                'cm.nome AS modelo_nome',
                'v.placa',
                'v.modelo AS veiculo_modelo',
                'v.marca',
                'v.id_matriz_filial',
            ])
            ->leftJoin('checklist_modelos', 'cm', 'c.id_modelo', '=', 'cm.id')
            ->leftJoin('veiculos', 'v', 'c.id_veiculo', '=', 'v.id');

        $this->aplicarFiltrosListagem($query, $search, $filialWhere, $filialParams);

        return $query
            ->orderBy('c.created_at', 'DESC')
            ->paginate($page, $perPage)
            ->get();
    }

    public function contar(
        string $chave,
        string $search = '',
        ?string $filialWhere = null,
        array $filialParams = []
    ): int {
        $query = $this->qb
            ->table('checklist', 'c')
            ->leftJoin('checklist_modelos', 'cm', 'c.id_modelo', '=', 'cm.id')
            ->leftJoin('veiculos', 'v', 'c.id_veiculo', '=', 'v.id');

        $this->aplicarFiltrosListagem($query, $search, $filialWhere, $filialParams);

        return $query->count();
    }

    private function aplicarFiltrosListagem($query, string $search, ?string $filialWhere, array $filialParams): void
    {
        if (!empty($filialWhere)) {
            $query->whereRaw($filialWhere, $filialParams);
        }

        if ($search !== '') {
            $query->whereRaw(
                '(c.codigo LIKE ? OR cm.nome LIKE ? OR v.placa LIKE ? OR v.modelo LIKE ? OR v.marca LIKE ?)',
                array_fill(0, 5, '%' . $search . '%')
            );
        }
    }

    public function buscarPorId(int $id): ?array
    {
        return $this->baseCompleta()
            ->where('c.id', '=', $id)
            ->first();
    }

    public function buscarPorCodigo(string $codigo): ?array
    {
        return $this->qb
            ->table('checklist', 'c')
            ->withoutChave()
            ->select([
                'c.id',
                'c.chave',
                'c.codigo',
                'c.tipo',
                'c.status',
                'c.created_at',
                'COALESCE(c.data_entrada, c.data_saida, c.created_at) AS data_checklist',
                'cm.nome AS modelo_nome',
                'v.placa',
                'v.modelo AS veiculo_modelo',
                'v.marca',
                'v.id_matriz_filial',
            ])
            ->leftJoin('checklist_modelos', 'cm', 'c.id_modelo', '=', 'cm.id')
            ->leftJoin('veiculos', 'v', 'c.id_veiculo', '=', 'v.id')
            ->where('c.codigo', '=', $codigo)
            ->first();
    }

    public function buscarPorLocacaoFK(int $idLocacao, string $chave): ?array
    {
        return $this->qb
            ->table('checklist', 'c')
            ->select(['c.id', 'c.status'])
            ->where('c.id_locacao', '=', $idLocacao)
            ->where('c.tipo', '=', 'V')
            ->orderBy('c.created_at', 'DESC')
            ->first();
    }

    public function buscarPorContratoFK(int $idContrato, string $chave): ?array
    {
        return $this->qb
            ->table('checklist', 'c')
            ->select(['c.id', 'c.status'])
            ->where('c.id_contrato', '=', $idContrato)
            ->where('c.tipo', '=', 'V')
            ->orderBy('c.created_at', 'DESC')
            ->first();
    }

    public function listarFinalizadosPorLocacao(int $idLocacao): array
    {
        return $this->listarFinalizadosPorVinculo('id_locacao', $idLocacao);
    }

    public function listarFinalizadosPorContrato(int $idContrato): array
    {
        return $this->listarFinalizadosPorVinculo('id_contrato', $idContrato);
    }

    private function listarFinalizadosPorVinculo(string $campoVinculo, int $idVinculo): array
    {
        if (!in_array($campoVinculo, ['id_locacao', 'id_contrato'], true)) {
            throw new \InvalidArgumentException('Vinculo de checklist invalido');
        }

        return $this->qb
            ->table('checklist', 'c')
            ->select([
                'c.id',
                'c.codigo',
                'c.status',
                'c.data_saida',
                'c.data_entrada',
                'COALESCE(c.data_entrada, c.data_saida) AS data_checklist',
                'c.id_veiculo',
                'cm.nome AS modelo_nome',
                'v.placa',
                'v.modelo AS veiculo_modelo',
                'v.marca',
            ])
            ->leftJoin('checklist_modelos', 'cm', 'c.id_modelo', '=', 'cm.id')
            ->leftJoin('veiculos', 'v', 'c.id_veiculo', '=', 'v.id')
            ->where('c.' . $campoVinculo, '=', $idVinculo)
            ->where('c.tipo', '=', 'V')
            ->whereIn('c.status', [
                self::STATUS_VINCULADO_SAIDA_CONCLUIDO,
                self::STATUS_VINCULADO_ENTRADA_CONCLUIDO,
            ])
            ->orderBy('c.created_at', 'DESC')
            ->get();
    }

    public function excluir(int $id): int
    {
        return $this->qb
            ->table('checklist')
            ->where('id', '=', $id)
            ->delete();
    }

    public function buscarVeiculosDoVinculo(string $tipoVinculo, int $idVinculo, string $etapa, string $chave): array
    {
        if ($tipoVinculo === 'L') {
            $veiculos = $this->qb
                ->table('locacoes_veiculos', 'lv')
                ->select([
                    'lv.id_veiculo',
                    'v.placa',
                    'v.marca',
                    'v.modelo',
                    'v.tipo_combustivel',
                    'v.odometro',
                    'v.tanque_fracao',
                    'v.id_matriz_filial',
                ])
                ->leftJoin('veiculos', 'v', 'lv.id_veiculo', '=', 'v.id')
                ->where('lv.id_locacao', '=', $idVinculo)
                ->whereNotNull('lv.id_veiculo')
                ->get();

            $fkCol = 'id_locacao';
        } else {
            $veiculos = $this->qb
                ->table('contratos_veiculos', 'cv')
                ->select([
                    'cv.id_veiculo',
                    'v.placa',
                    'v.marca',
                    'v.modelo',
                    'v.tipo_combustivel',
                    'v.odometro',
                    'v.tanque_fracao',
                    'v.id_matriz_filial',
                ])
                ->leftJoin('veiculos', 'v', 'cv.id_veiculo', '=', 'v.id')
                ->where('cv.id_contrato', '=', $idVinculo)
                ->whereNotNull('cv.id_veiculo')
                ->get();

            $fkCol = 'id_contrato';
        }

        $statusFeitos = $etapa === 'entrada'
            ? [self::STATUS_VINCULADO_ENTRADA_CONCLUIDO]
            : [
                self::STATUS_VINCULADO_SAIDA_CONCLUIDO,
                self::STATUS_VINCULADO_ENTRADA_INICIADO,
                self::STATUS_VINCULADO_ENTRADA_CONCLUIDO,
            ];

        $checklistsFeitos = $this->qb
            ->table('checklist')
            ->select(['id_veiculo'])
            ->where($fkCol, '=', $idVinculo)
            ->where('tipo', '=', 'V')
            ->whereIn('status', $statusFeitos)
            ->get();

        $veiculosFeitos = array_column($checklistsFeitos, 'id_veiculo');
        $vistos = [];
        $resultado = [];

        foreach ($veiculos as $v) {
            $idV = (int) ($v['id_veiculo'] ?? 0);
            if ($idV <= 0 || isset($vistos[$idV])) {
                continue;
            }
            $vistos[$idV] = true;

            $v['id_veiculo'] = $idV;
            $v['checklist_feito'] = in_array($idV, $veiculosFeitos);
            $resultado[] = $v;
        }

        return $resultado;
    }

    public function excluirComArquivos(int $id, string $chave): int
    {
        $checklist = $this->buscarPorId($id);
        if (!$checklist || $checklist['chave'] !== $chave) {
            return 0;
        }

        foreach (['saida', 'entrada'] as $etapa) {
            $vistoria = json_decode($checklist['vistoria_' . $etapa] ?? '[]', true) ?: [];
            foreach ($vistoria as $item) {
                if (!empty($item['img'])) {
                    \App\Helpers\FileHelper::delete($item['img'], $chave);
                }
            }

            $assinatura = $checklist['assinatura_' . $etapa] ?? null;
            if (!empty($assinatura)) {
                \App\Helpers\FileHelper::delete($assinatura, $chave);
            }
        }

        return $this->excluir($id);
    }

    public function excluirPorLocacao(int $idLocacao, string $chave): void
    {
        $checklists = $this->qb
            ->table('checklist')
            ->select(['id'])
            ->where('id_locacao', '=', $idLocacao)
            ->get();

        foreach ($checklists as $ck) {
            $this->excluirComArquivos((int) $ck['id'], $chave);
        }
    }

    public function excluirPorContrato(int $idContrato, string $chave): void
    {
        $checklists = $this->qb
            ->table('checklist')
            ->select(['id'])
            ->where('id_contrato', '=', $idContrato)
            ->get();

        foreach ($checklists as $ck) {
            $this->excluirComArquivos((int) $ck['id'], $chave);
        }
    }

    public function buscarPar(array $checklist): ?array
    {
        return null;
    }

    public function criar(array $dados): int
    {
        $dados['created_at'] = $dados['created_at'] ?? $this->agora();

        return $this->qb
            ->table('checklist')
            ->insert($dados);
    }

    public function gerarCodigo(string $chave): string
    {
        for ($tentativa = 0; $tentativa < 20; $tentativa++) {
            $codigo = CodigoHelper::gerarComPrefixo('CK');

            $existente = $this->qb
                ->table('checklist')
                ->withChave($chave)
                ->where('codigo', '=', $codigo)
                ->first();

            if (!$existente) {
                return $codigo;
            }
        }

        throw new \RuntimeException('Nao foi possivel gerar um codigo de checklist unico');
    }

    public function buscarCodigoVinculo(?int $idLocacao, ?int $idContrato): ?string
    {
        if ($idLocacao) {
            $row = $this->qb
                ->table('locacoes')
                ->select(['codigo'])
                ->where('id', '=', $idLocacao)
                ->first();

            return $row['codigo'] ?? null;
        }

        if ($idContrato) {
            $row = $this->qb
                ->table('contratos')
                ->select(['codigo'])
                ->where('id', '=', $idContrato)
                ->first();

            return $row['codigo'] ?? null;
        }

        return null;
    }

    public function atualizarQuestoes(int $id, string $questoesJson, string $etapa = 'saida'): int
    {
        $etapa = $this->normalizarEtapa($etapa);

        return $this->qb
            ->table('checklist')
            ->where('id', '=', $id)
            ->update(['questoes_' . $etapa => $questoesJson]);
    }

    public function atualizarVistoria(int $id, string $vistoriaJson, string $etapa = 'saida'): int
    {
        $etapa = $this->normalizarEtapa($etapa);

        return $this->qb
            ->table('checklist')
            ->where('id', '=', $id)
            ->update(['vistoria_' . $etapa => $vistoriaJson]);
    }

    public function iniciarEntrada(int $id): int
    {
        return $this->qb
            ->table('checklist')
            ->where('id', '=', $id)
            ->where('tipo', '=', 'V')
            ->where('status', '=', self::STATUS_VINCULADO_SAIDA_CONCLUIDO)
            ->update(['status' => self::STATUS_VINCULADO_ENTRADA_INICIADO]);
    }

    public function salvarAssinatura(int $id, string $filename, string $etapa = 'saida'): int
    {
        $etapa = $this->normalizarEtapa($etapa);
        $status = $etapa === 'entrada'
            ? self::STATUS_VINCULADO_ENTRADA_CONCLUIDO
            : null;

        $checklist = $this->buscarPorIdCompleto($id, '');
        if (($checklist['tipo'] ?? '') === 'A') {
            $status = self::STATUS_AVULSO_CONCLUIDO;
            $etapa = 'saida';
        } elseif ($status === null) {
            $status = self::STATUS_VINCULADO_SAIDA_CONCLUIDO;
        }

        return $this->qb
            ->table('checklist')
            ->where('id', '=', $id)
            ->update([
                'assinatura_' . $etapa => $filename,
                'status' => $status,
                'data_' . $etapa => $this->agora(),
            ]);
    }

    public function buscarPorIdCompleto(int $id, string $chave): ?array
    {
        return $this->baseCompleta()
            ->where('c.id', '=', $id)
            ->first();
    }

    private function baseCompleta()
    {
        return $this->qb
            ->table('checklist', 'c')
            ->select([
                'c.*',
                'COALESCE(c.data_entrada, c.data_saida, c.created_at) AS data_checklist',
                'c.questoes_saida AS questoes',
                'c.vistoria_saida AS vistoria',
                'c.assinatura_saida AS assinatura',
                'c.observacoes_saida AS obs',
                'c.questoes_entrada AS questoes_chegada',
                'c.vistoria_entrada AS vistoria_chegada',
                'c.assinatura_entrada AS assinatura_chegada',
                'c.observacoes_entrada AS obs_chegada',
                'c.data_entrada AS data_chegada',
                'cm.nome AS modelo_nome',
                'cm.questoes AS modelo_questoes',
                'cm.vistoria AS modelo_vistoria',
                'cm.tipo AS modelo_tipo',
                'v.placa',
                'v.modelo AS veiculo_modelo',
                'v.marca',
                'v.renavam',
                'v.tipo_combustivel',
                'v.odometro',
                'v.tanque_fracao',
                'v.id_matriz_filial',
                'l.codigo AS locacao_codigo',
                'l.cliente_nome AS locacao_cliente',
                'ct.codigo AS contrato_codigo',
                'cl.nome_rsocial AS contrato_cliente',
            ])
            ->leftJoin('checklist_modelos', 'cm', 'c.id_modelo', '=', 'cm.id')
            ->leftJoin('veiculos', 'v', 'c.id_veiculo', '=', 'v.id')
            ->leftJoin('locacoes', 'l', 'c.id_locacao', '=', 'l.id')
            ->leftJoin('contratos', 'ct', 'c.id_contrato', '=', 'ct.id')
            ->leftJoin('clientes', 'cl', 'ct.id_cliente', '=', 'cl.id');
    }

    public function buscarChecklistVinculadoAberto(?int $idLocacao, ?int $idContrato, int $idVeiculo): ?array
    {
        $query = $this->qb
            ->table('checklist')
            ->where('tipo', '=', 'V')
            ->where('id_veiculo', '=', $idVeiculo)
            ->whereIn('status', [
                self::STATUS_VINCULADO_SAIDA_INICIADO,
                self::STATUS_VINCULADO_SAIDA_CONCLUIDO,
                self::STATUS_VINCULADO_ENTRADA_INICIADO,
            ]);

        if ($idLocacao) {
            $query->where('id_locacao', '=', $idLocacao);
        } elseif ($idContrato) {
            $query->where('id_contrato', '=', $idContrato);
        } else {
            return null;
        }

        return $query->orderBy('created_at', 'DESC')->first();
    }

    public function listarVinculadosPendentes(
        string $search = '',
        string $statusFiltro = '',
        ?string $filialWhere = null,
        array $filialParams = []
    ): array {
        $itens = [];

        if ($statusFiltro === '' || $statusFiltro === 'aguardando_saida') {
            $itens = array_merge($itens, $this->listarVinculosSemChecklist('L', $search, $filialWhere, $filialParams));
            $itens = array_merge($itens, $this->listarVinculosSemChecklist('C', $search, $filialWhere, $filialParams));
        }

        $query = $this->qb
            ->table('checklist', 'c')
            ->select([
                'c.id AS checklist_id',
                'c.codigo AS checklist_codigo',
                'c.status',
                'c.id_locacao',
                'c.id_contrato',
                'c.id_veiculo',
                'COALESCE(l.codigo, ct.codigo) AS vinculo_codigo',
                'COALESCE(l.cliente_nome, cl.nome_rsocial) AS cliente_nome',
                'v.placa',
                'v.marca',
                'v.modelo',
                'v.id_matriz_filial',
            ])
            ->leftJoin('locacoes', 'l', 'c.id_locacao', '=', 'l.id')
            ->leftJoin('contratos', 'ct', 'c.id_contrato', '=', 'ct.id')
            ->leftJoin('clientes', 'cl', 'ct.id_cliente', '=', 'cl.id')
            ->leftJoin('veiculos', 'v', 'c.id_veiculo', '=', 'v.id')
            ->where('c.tipo', '=', 'V')
            ->whereIn('c.status', [
                self::STATUS_VINCULADO_SAIDA_INICIADO,
                self::STATUS_VINCULADO_SAIDA_CONCLUIDO,
                self::STATUS_VINCULADO_ENTRADA_INICIADO,
            ]);

        if ($statusFiltro === 'aguardando_saida') {
            $query->where('c.status', '=', self::STATUS_VINCULADO_SAIDA_INICIADO);
        } elseif ($statusFiltro === 'aguardando_chegada') {
            $query->whereIn('c.status', [
                self::STATUS_VINCULADO_SAIDA_CONCLUIDO,
                self::STATUS_VINCULADO_ENTRADA_INICIADO,
            ]);
        }

        if ($search !== '') {
            $query->whereRaw(
                '(c.codigo LIKE ? OR l.codigo LIKE ? OR ct.codigo LIKE ? OR l.cliente_nome LIKE ? OR cl.nome_rsocial LIKE ? OR v.placa LIKE ? OR v.modelo LIKE ?)',
                array_fill(0, 7, '%' . $search . '%')
            );
        }

        if (!empty($filialWhere)) {
            $query->whereRaw($filialWhere, $filialParams);
        }

        foreach ($query->orderBy('c.created_at', 'DESC')->limit(100)->get() as $row) {
            $status = (string) ($row['status'] ?? '');
            $itens[] = [
                'checklist_id' => (int) $row['checklist_id'],
                'tipo_vinculo' => !empty($row['id_locacao']) ? 'L' : 'C',
                'id_vinculo' => (int) ($row['id_locacao'] ?: $row['id_contrato']),
                'id_veiculo' => (int) $row['id_veiculo'],
                'codigo' => $row['checklist_codigo'] ?: $row['vinculo_codigo'],
                'vinculo_codigo' => $row['vinculo_codigo'],
                'cliente' => $row['cliente_nome'] ?? '-',
                'veiculo' => trim(($row['placa'] ?? '') . ' - ' . ($row['marca'] ?? '') . ' ' . ($row['modelo'] ?? '')),
                'status' => $status,
                'etapa' => in_array($status, [self::STATUS_VINCULADO_SAIDA_CONCLUIDO, self::STATUS_VINCULADO_ENTRADA_INICIADO], true) ? 'entrada' : 'saida',
            ];
        }

        return array_slice($itens, 0, 100);
    }

    private function listarVinculosSemChecklist(
        string $tipo,
        string $search,
        ?string $filialWhere = null,
        array $filialParams = []
    ): array
    {
        if ($tipo === 'L') {
            $query = $this->qb
                ->table('locacoes', 'l')
                ->select([
                    'l.id AS id_vinculo',
                    'l.codigo AS vinculo_codigo',
                    'l.cliente_nome',
                    'lv.id_veiculo',
                    'v.placa',
                    'v.marca',
                    'v.modelo',
                    'v.odometro',
                    'v.tanque_fracao',
                ])
                ->leftJoin('locacoes_veiculos', 'lv', 'l.id', '=', 'lv.id_locacao')
                ->leftJoin('veiculos', 'v', 'lv.id_veiculo', '=', 'v.id')
                ->whereIn('l.status', ['A', 'R'])
                ->whereNotNull('lv.id_veiculo')
                ->whereRaw('NOT EXISTS (SELECT 1 FROM checklist c WHERE c.chave = l.chave AND c.tipo = ? AND c.id_locacao = l.id AND c.id_veiculo = lv.id_veiculo AND c.status IN (?, ?, ?, ?))', [
                    'V',
                    self::STATUS_VINCULADO_SAIDA_INICIADO,
                    self::STATUS_VINCULADO_SAIDA_CONCLUIDO,
                    self::STATUS_VINCULADO_ENTRADA_INICIADO,
                    self::STATUS_VINCULADO_ENTRADA_CONCLUIDO,
                ]);

            if ($search !== '') {
                $query->whereRaw('(l.codigo LIKE ? OR l.cliente_nome LIKE ? OR v.placa LIKE ? OR v.modelo LIKE ?)', array_fill(0, 4, '%' . $search . '%'));
            }
        } else {
            $query = $this->qb
                ->table('contratos', 'ct')
                ->select([
                    'ct.id AS id_vinculo',
                    'ct.codigo AS vinculo_codigo',
                    'cl.nome_rsocial AS cliente_nome',
                    'cv.id_veiculo',
                    'v.placa',
                    'v.marca',
                    'v.modelo',
                    'v.odometro',
                    'v.tanque_fracao',
                ])
                ->leftJoin('clientes', 'cl', 'ct.id_cliente', '=', 'cl.id')
                ->leftJoin('contratos_veiculos', 'cv', 'ct.id', '=', 'cv.id_contrato')
                ->leftJoin('veiculos', 'v', 'cv.id_veiculo', '=', 'v.id')
                ->whereIn('ct.status', ['A', 'R'])
                ->whereNotNull('cv.id_veiculo')
                ->whereRaw('NOT EXISTS (SELECT 1 FROM checklist c WHERE c.chave = ct.chave AND c.tipo = ? AND c.id_contrato = ct.id AND c.id_veiculo = cv.id_veiculo AND c.status IN (?, ?, ?, ?))', [
                    'V',
                    self::STATUS_VINCULADO_SAIDA_INICIADO,
                    self::STATUS_VINCULADO_SAIDA_CONCLUIDO,
                    self::STATUS_VINCULADO_ENTRADA_INICIADO,
                    self::STATUS_VINCULADO_ENTRADA_CONCLUIDO,
                ]);

            if ($search !== '') {
                $query->whereRaw('(ct.codigo LIKE ? OR cl.nome_rsocial LIKE ? OR v.placa LIKE ? OR v.modelo LIKE ?)', array_fill(0, 4, '%' . $search . '%'));
            }
        }

        if (!empty($filialWhere)) {
            $query->whereRaw($filialWhere, $filialParams);
        }

        $rows = $query->orderBy('vinculo_codigo', 'DESC')->limit(50)->get();
        $itens = [];
        $vistos = [];

        foreach ($rows as $row) {
            $key = $tipo . '-' . $row['id_vinculo'] . '-' . $row['id_veiculo'];
            if (isset($vistos[$key])) {
                continue;
            }
            $vistos[$key] = true;

            $itens[] = [
                'checklist_id' => null,
                'tipo_vinculo' => $tipo,
                'id_vinculo' => (int) $row['id_vinculo'],
                'id_veiculo' => (int) $row['id_veiculo'],
                'codigo' => $row['vinculo_codigo'],
                'vinculo_codigo' => $row['vinculo_codigo'],
                'cliente' => $row['cliente_nome'] ?? '-',
                'veiculo' => trim(($row['placa'] ?? '') . ' - ' . ($row['marca'] ?? '') . ' ' . ($row['modelo'] ?? '')),
                'status' => self::STATUS_VINCULADO_SAIDA_INICIADO,
                'etapa' => 'saida',
            ];
        }

        return $itens;
    }

    public function buscarLocacoesAtivas(
        string $chave,
        string $search = '',
        ?string $filialWhere = null,
        array $filialParams = []
    ): array {
        $query = $this->qb
            ->table('locacoes', 'l')
            ->select([
                'l.id',
                'l.codigo',
                'l.cliente_nome',
                'l.status',
                'lv.id_veiculo',
                'v.placa',
                'v.modelo AS veiculo_modelo',
                'v.marca',
                'v.tipo_combustivel',
                'v.odometro',
                'v.tanque_fracao',
            ])
            ->leftJoin('locacoes_veiculos', 'lv', 'l.id', '=', 'lv.id_locacao')
            ->leftJoin('veiculos', 'v', 'lv.id_veiculo', '=', 'v.id')
            ->whereIn('l.status', ['A', 'R']);

        if (!empty($filialWhere)) {
            $query->whereRaw($filialWhere, $filialParams);
        }

        if ($search !== '') {
            $query->whereRaw('(l.codigo LIKE ? OR l.cliente_nome LIKE ? OR v.placa LIKE ?)', array_fill(0, 3, '%' . $search . '%'));
        }

        return $query->orderBy('l.created_at', 'DESC')->limit(20)->get();
    }

    public function buscarContratosAtivos(
        string $chave,
        string $search = '',
        ?string $filialWhere = null,
        array $filialParams = []
    ): array {
        $query = $this->qb
            ->table('contratos', 'ct')
            ->select([
                'ct.id',
                'ct.codigo',
                'cl.nome_rsocial AS cliente_nome',
                'ct.data_ini',
                'ct.data_fim',
                'cv.id_veiculo',
                'v.placa',
                'v.modelo AS veiculo_modelo',
                'v.marca',
                'v.tipo_combustivel',
                'v.odometro',
                'v.tanque_fracao',
            ])
            ->leftJoin('clientes', 'cl', 'ct.id_cliente', '=', 'cl.id')
            ->leftJoin('contratos_veiculos', 'cv', 'ct.id', '=', 'cv.id_contrato')
            ->leftJoin('veiculos', 'v', 'cv.id_veiculo', '=', 'v.id')
            ->whereIn('ct.status', ['A', 'R']);

        if (!empty($filialWhere)) {
            $query->whereRaw($filialWhere, $filialParams);
        }

        if ($search !== '') {
            $query->whereRaw('(ct.codigo LIKE ? OR cl.nome_rsocial LIKE ? OR v.placa LIKE ?)', array_fill(0, 3, '%' . $search . '%'));
        }

        return $query->orderBy('ct.created_at', 'DESC')->limit(20)->get();
    }

    public function buscarVeiculos(
        string $chave,
        string $search = '',
        ?string $filialWhere = null,
        array $filialParams = []
    ): array {
        $query = $this->qb
            ->table('veiculos', 'v')
            ->select([
                'v.id',
                'v.placa',
                'v.modelo',
                'v.marca',
                'v.tipo_combustivel',
                'v.odometro',
                'v.tanque_fracao',
                'v.id_matriz_filial',
            ])
            ->whereNotIn('v.disponibilidade', ['V', 'RO', 'E']);

        if (!empty($filialWhere)) {
            $query->whereRaw($filialWhere, $filialParams);
        }

        if ($search !== '') {
            $query->whereRaw('(v.placa LIKE ? OR v.modelo LIKE ? OR v.marca LIKE ?)', array_fill(0, 3, '%' . $search . '%'));
        }

        return $query->orderBy('v.placa', 'ASC')->limit(20)->get();
    }

    public function resolverVinculoPorCodigo(
        string $codigo,
        ?string $filialWhereLoc = null,
        array $filialParamsLoc = [],
        ?string $filialWhereCt = null,
        array $filialParamsCt = []
    ): ?array {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }

        if (preg_match('/^(L|C)-(\d+)$/i', $codigo, $match)) {
            $tipo = strtoupper($match[1]);
            $id = (int) $match[2];
            return $tipo === 'L'
                ? $this->resolverLocacaoPorId($id, $filialWhereLoc, $filialParamsLoc)
                : $this->resolverContratoPorId($id, $filialWhereCt, $filialParamsCt);
        }

        $prefixo = strtoupper(substr($codigo, 0, 1));
        if ($prefixo === 'L') {
            return $this->resolverLocacaoPorCodigo($codigo, $filialWhereLoc, $filialParamsLoc);
        }
        if ($prefixo === 'C') {
            return $this->resolverContratoPorCodigo($codigo, $filialWhereCt, $filialParamsCt);
        }

        $resultados = array_filter([
            $this->resolverLocacaoPorCodigo($codigo, $filialWhereLoc, $filialParamsLoc),
            $this->resolverContratoPorCodigo($codigo, $filialWhereCt, $filialParamsCt),
        ]);

        if (count($resultados) > 1) {
            throw new \RuntimeException('Codigo de vinculo ambiguo');
        }

        return $resultados ? array_values($resultados)[0] : null;
    }

    private function resolverLocacaoPorCodigo(string $codigo, ?string $filialWhere, array $filialParams): ?array
    {
        return $this->resolverLocacaoBase($filialWhere, $filialParams)
            ->where('l.codigo', '=', $codigo)
            ->first();
    }

    private function resolverLocacaoPorId(int $id, ?string $filialWhere, array $filialParams): ?array
    {
        if ($id <= 0) {
            return null;
        }

        return $this->resolverLocacaoBase($filialWhere, $filialParams)
            ->where('l.id', '=', $id)
            ->first();
    }

    private function resolverContratoPorCodigo(string $codigo, ?string $filialWhere, array $filialParams): ?array
    {
        return $this->resolverContratoBase($filialWhere, $filialParams)
            ->where('ct.codigo', '=', $codigo)
            ->first();
    }

    private function resolverContratoPorId(int $id, ?string $filialWhere, array $filialParams): ?array
    {
        if ($id <= 0) {
            return null;
        }

        return $this->resolverContratoBase($filialWhere, $filialParams)
            ->where('ct.id', '=', $id)
            ->first();
    }

    private function resolverLocacaoBase(?string $filialWhere, array $filialParams)
    {
        $query = $this->qb
            ->table('locacoes', 'l')
            ->select([
                "'L' AS tipo_vinculo",
                'l.id AS id_vinculo',
                'l.codigo AS codigo',
                'l.cliente_nome AS cliente',
                'lv.id_veiculo',
                'v.placa',
                'v.marca',
                'v.modelo',
                'v.tipo_combustivel',
                'v.odometro',
                'v.tanque_fracao',
            ])
            ->leftJoin('locacoes_veiculos', 'lv', 'l.id', '=', 'lv.id_locacao')
            ->leftJoin('veiculos', 'v', 'lv.id_veiculo', '=', 'v.id')
            ->whereIn('l.status', ['A', 'R'])
            ->whereNotNull('lv.id_veiculo');

        if (!empty($filialWhere)) {
            $query->whereRaw($filialWhere, $filialParams);
        }

        return $query->limit(1);
    }

    private function resolverContratoBase(?string $filialWhere, array $filialParams)
    {
        $query = $this->qb
            ->table('contratos', 'ct')
            ->select([
                "'C' AS tipo_vinculo",
                'ct.id AS id_vinculo',
                'ct.codigo AS codigo',
                'cl.nome_rsocial AS cliente',
                'cv.id_veiculo',
                'v.placa',
                'v.marca',
                'v.modelo',
                'v.tipo_combustivel',
                'v.odometro',
                'v.tanque_fracao',
            ])
            ->leftJoin('clientes', 'cl', 'ct.id_cliente', '=', 'cl.id')
            ->leftJoin('contratos_veiculos', 'cv', 'ct.id', '=', 'cv.id_contrato')
            ->leftJoin('veiculos', 'v', 'cv.id_veiculo', '=', 'v.id')
            ->whereIn('ct.status', ['A', 'R'])
            ->whereNotNull('cv.id_veiculo');

        if (!empty($filialWhere)) {
            $query->whereRaw($filialWhere, $filialParams);
        }

        return $query->limit(1);
    }

    public function etapaAtual(array $checklist, ?string $etapaSolicitada = null): string
    {
        if ($etapaSolicitada !== null && in_array($etapaSolicitada, self::ETAPAS, true)) {
            return $etapaSolicitada;
        }

        if (($checklist['tipo'] ?? '') === 'A') {
            return 'saida';
        }

        return in_array((string) ($checklist['status'] ?? ''), [
            self::STATUS_VINCULADO_SAIDA_CONCLUIDO,
            self::STATUS_VINCULADO_ENTRADA_INICIADO,
        ], true) ? 'entrada' : 'saida';
    }

    public function etapaFinalizada(array $checklist, string $etapa): bool
    {
        $status = (string) ($checklist['status'] ?? '');

        if (($checklist['tipo'] ?? '') === 'A') {
            return $status === self::STATUS_AVULSO_CONCLUIDO;
        }

        if ($etapa === 'saida') {
            return in_array($status, [
                self::STATUS_VINCULADO_SAIDA_CONCLUIDO,
                self::STATUS_VINCULADO_ENTRADA_INICIADO,
                self::STATUS_VINCULADO_ENTRADA_CONCLUIDO,
            ], true);
        }

        return $status === self::STATUS_VINCULADO_ENTRADA_CONCLUIDO;
    }

    private function normalizarEtapa(string $etapa): string
    {
        if (!in_array($etapa, self::ETAPAS, true)) {
            throw new \InvalidArgumentException('Etapa de checklist invalida');
        }

        return $etapa;
    }

    private function agora(): string
    {
        return DateHelper::nowForDatabase();
    }
}
