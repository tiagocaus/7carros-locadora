<?php

namespace App\Models;

/**
 * Model Checklist
 *
 * Gerencia checklists/vistorias realizados nos veiculos.
 */
class Checklist extends Model
{
    /**
     * Lista checklists com paginacao, busca e filtro de filial
     *
     * @param string $chave Chave do tenant
     * @param int $page Pagina atual
     * @param int $perPage Registros por pagina
     * @param string $search Termo de busca
     * @param string|null $filialWhere Clausula WHERE de filiais
     * @param array $filialParams Parametros da clausula de filiais
     * @return array Lista de checklists
     */
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
                'c.data_checklist',
                'c.status',
                'c.created_at',
                'cm.nome AS modelo_nome',
                'v.placa',
                'v.modelo AS veiculo_modelo',
                'v.marca',
                'v.id_matriz_filial'
            ])
            ->leftJoin('checklist_modelos', 'cm', 'c.id_modelo', '=', 'cm.id')
            ->leftJoin('veiculos', 'v', 'c.id_veiculo', '=', 'v.id');

        if (!empty($filialWhere)) {
            $query->whereRaw($filialWhere, $filialParams);
        }

        if (!empty($search)) {
            $query->whereRaw(
                '(c.codigo LIKE ? OR cm.nome LIKE ? OR v.placa LIKE ? OR v.modelo LIKE ? OR v.marca LIKE ?)',
                [
                    '%' . $search . '%',
                    '%' . $search . '%',
                    '%' . $search . '%',
                    '%' . $search . '%',
                    '%' . $search . '%'
                ]
            );
        }

        return $query
            ->orderBy('c.created_at', 'DESC')
            ->paginate($page, $perPage)
            ->get();
    }

    /**
     * Conta total de checklists com filtros
     *
     * @param string $chave Chave do tenant
     * @param string $search Termo de busca
     * @param string|null $filialWhere Clausula WHERE de filiais
     * @param array $filialParams Parametros da clausula de filiais
     * @return int Total de registros
     */
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

        if (!empty($filialWhere)) {
            $query->whereRaw($filialWhere, $filialParams);
        }

        if (!empty($search)) {
            $query->whereRaw(
                '(c.codigo LIKE ? OR cm.nome LIKE ? OR v.placa LIKE ? OR v.modelo LIKE ? OR v.marca LIKE ?)',
                [
                    '%' . $search . '%',
                    '%' . $search . '%',
                    '%' . $search . '%',
                    '%' . $search . '%',
                    '%' . $search . '%'
                ]
            );
        }

        return $query->count();
    }

    /**
     * Busca um checklist por ID com dados completos
     *
     * @param int $id ID do checklist
     * @return array|null Dados do checklist ou null
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->qb
            ->table('checklist', 'c')
            ->select([
                'c.*',
                // Aliases para compatibilidade com templates de impressao
                'c.questoes AS questoes_saida',
                'c.vistoria AS vistoria_saida',
                'c.assinatura_unica AS assinatura',
                'c.obs_unica AS obs',
                'c.data_checklist AS data_saida',
                'NULL AS questoes_chegada',
                'NULL AS vistoria_chegada',
                'NULL AS assinatura_chegada',
                'NULL AS obs_chegada',
                'NULL AS data_chegada',
                'cm.nome AS modelo_nome',
                'cm.tipo AS modelo_tipo',
                'v.placa',
                'v.modelo AS veiculo_modelo',
                'v.marca',
                'v.renavam',
                'v.id_matriz_filial'
            ])
            ->leftJoin('checklist_modelos', 'cm', 'c.id_modelo', '=', 'cm.id')
            ->leftJoin('veiculos', 'v', 'c.id_veiculo', '=', 'v.id')
            ->where('c.id', '=', $id)
            ->first();
    }

    /**
     * Busca um checklist por codigo (para rota publica, sem filtro de sessao)
     *
     * @param string $codigo Codigo do checklist
     * @return array|null Dados do checklist ou null
     */
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
                'c.momento',
                'c.data_checklist',
                'c.status',
                'c.created_at',
                'cm.nome AS modelo_nome',
                'v.placa',
                'v.modelo AS veiculo_modelo',
                'v.marca',
                'v.id_matriz_filial'
            ])
            ->leftJoin('checklist_modelos', 'cm', 'c.id_modelo', '=', 'cm.id')
            ->leftJoin('veiculos', 'v', 'c.id_veiculo', '=', 'v.id')
            ->where('c.codigo', '=', $codigo)
            ->first();
    }

    /**
     * Busca primeiro checklist vinculado a uma locacao (por FK)
     *
     * @param int $idLocacao ID da locacao
     * @param string $chave Chave do tenant
     * @return array|null
     */
    public function buscarPorLocacaoFK(int $idLocacao, string $chave): ?array
    {
        return $this->qb
            ->table('checklist', 'c')
            ->select(['c.id', 'c.momento'])
            ->where('c.id_locacao', '=', $idLocacao)
            ->orderBy('c.momento', 'ASC')
            ->first();
    }

    /**
     * Busca primeiro checklist vinculado a um contrato (por FK)
     *
     * @param int $idContrato ID do contrato
     * @param string $chave Chave do tenant
     * @return array|null
     */
    public function buscarPorContratoFK(int $idContrato, string $chave): ?array
    {
        return $this->qb
            ->table('checklist', 'c')
            ->select(['c.id', 'c.momento'])
            ->where('c.id_contrato', '=', $idContrato)
            ->orderBy('c.momento', 'ASC')
            ->first();
    }

    /**
     * Exclui um checklist
     *
     * @param int $id ID do checklist
     * @return int Linhas afetadas
     */
    public function excluir(int $id): int
    {
        return $this->qb
            ->table('checklist')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Busca veiculos de uma locacao ou contrato com status de checklist
     *
     * @param string $tipoVinculo 'L' (locacao) ou 'C' (contrato)
     * @param int $idVinculo ID da locacao ou contrato
     * @param string $momento 'S' ou 'C'
     * @param string $chave Chave do tenant
     * @return array Veiculos com campo checklist_feito
     */
    public function buscarVeiculosDoVinculo(string $tipoVinculo, int $idVinculo, string $momento, string $chave): array
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
                ])
                ->leftJoin('veiculos', 'v', 'lv.id_veiculo', '=', 'v.id')
                ->where('lv.id_locacao', '=', $idVinculo)
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
                ])
                ->leftJoin('veiculos', 'v', 'cv.id_veiculo', '=', 'v.id')
                ->where('cv.id_contrato', '=', $idVinculo)
                ->get();

            $fkCol = 'id_contrato';
        }

        // Verificar quais ja tem checklist feito para este momento
        $checklistsFeitos = $this->qb
            ->table('checklist')
            ->select(['id_veiculo'])
            ->where($fkCol, '=', $idVinculo)
            ->where('momento', '=', $momento)
            ->whereIn('status', ['2', '4'])
            ->get();

        $veiculosFeitos = array_column($checklistsFeitos, 'id_veiculo');

        // Deduplicar veiculos (contrato pode ter mesmo veiculo em periodos diferentes)
        $vistos = [];
        $resultado = [];
        foreach ($veiculos as $v) {
            $idV = (int) $v['id_veiculo'];
            if (isset($vistos[$idV])) continue;
            $vistos[$idV] = true;

            $v['id_veiculo'] = $idV;
            $v['checklist_feito'] = in_array($idV, $veiculosFeitos);
            $resultado[] = $v;
        }

        return $resultado;
    }

    /**
     * Exclui um checklist e seus arquivos (fotos de vistoria + assinatura)
     *
     * @param int $id ID do checklist
     * @param string $chave Chave do tenant
     * @return int Linhas afetadas
     */
    public function excluirComArquivos(int $id, string $chave): int
    {
        $checklist = $this->buscarPorId($id);
        if (!$checklist || $checklist['chave'] !== $chave) {
            return 0;
        }

        // Deletar fotos da vistoria
        $vistoria = json_decode($checklist['vistoria'] ?? $checklist['vistoria_saida'] ?? '[]', true) ?: [];
        foreach ($vistoria as $item) {
            if (!empty($item['img'])) {
                \App\Helpers\FileHelper::delete($item['img'], $chave);
            }
        }

        // Deletar assinatura
        $assinatura = $checklist['assinatura_unica'] ?? $checklist['assinatura'] ?? null;
        if (!empty($assinatura)) {
            \App\Helpers\FileHelper::delete($assinatura, $chave);
        }

        return $this->excluir($id);
    }

    /**
     * Exclui todos os checklists vinculados a uma locacao (com arquivos)
     *
     * @param int $idLocacao ID da locacao
     * @param string $chave Chave do tenant
     */
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

    /**
     * Exclui todos os checklists vinculados a um contrato (com arquivos)
     *
     * @param int $idContrato ID do contrato
     * @param string $chave Chave do tenant
     */
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

    /**
     * Busca o registro pareado de um checklist vinculado
     * (mesmo id_locacao/id_contrato + id_veiculo, momento oposto)
     *
     * @param array $checklist Dados do checklist atual (precisa ter momento, id_locacao, id_contrato, id_veiculo)
     * @return array|null Dados do par ou null se nao encontrar
     */
    public function buscarPar(array $checklist): ?array
    {
        $momento = $checklist['momento'] ?? null;
        if (!$momento || $momento === 'N') return null;

        $momentoOposto = $momento === 'S' ? 'C' : 'S';
        $idLocacao = $checklist['id_locacao'] ?? null;
        $idContrato = $checklist['id_contrato'] ?? null;
        $idVeiculo = $checklist['id_veiculo'] ?? null;

        if (!$idLocacao && !$idContrato) return null;

        $query = $this->qb
            ->table('checklist', 'c')
            ->select([
                'c.*',
                'c.questoes AS questoes_saida',
                'c.vistoria AS vistoria_saida',
                'c.assinatura_unica AS assinatura',
                'c.obs_unica AS obs',
                'c.data_checklist AS data_saida',
                'cm.nome AS modelo_nome',
                'v.placa',
                'v.modelo AS veiculo_modelo',
                'v.marca',
                'v.id_matriz_filial'
            ])
            ->leftJoin('checklist_modelos', 'cm', 'c.id_modelo', '=', 'cm.id')
            ->leftJoin('veiculos', 'v', 'c.id_veiculo', '=', 'v.id')
            ->where('c.momento', '=', $momentoOposto)
            ->where('c.tipo', '=', 'V');

        if ($idLocacao) {
            $query->where('c.id_locacao', '=', $idLocacao);
        } else {
            $query->where('c.id_contrato', '=', $idContrato);
        }

        if ($idVeiculo) {
            $query->where('c.id_veiculo', '=', $idVeiculo);
        }

        return $query->orderBy('c.created_at', 'DESC')->first();
    }

    /**
     * Cria um novo checklist normalizado
     * Filtro por chave automatico via sessao (campo chave incluido em $dados)
     *
     * @param array $dados Dados do checklist (deve conter 'chave')
     * @return int ID do checklist criado
     */
    public function criar(array $dados): int
    {
        return $this->qb
            ->table('checklist')
            ->insert($dados);
    }

    /**
     * Atualiza questoes de um checklist
     * Filtro por chave automatico via sessao
     *
     * @param int $id ID do checklist
     * @param string $questoesJson JSON das questoes
     * @return int Linhas afetadas
     */
    public function atualizarQuestoes(int $id, string $questoesJson): int
    {
        return $this->qb
            ->table('checklist')
            ->where('id', '=', $id)
            ->update(['questoes' => $questoesJson]);
    }

    /**
     * Atualiza vistoria de um checklist
     * Filtro por chave automatico via sessao
     *
     * @param int $id ID do checklist
     * @param string $vistoriaJson JSON da vistoria
     * @return int Linhas afetadas
     */
    public function atualizarVistoria(int $id, string $vistoriaJson): int
    {
        return $this->qb
            ->table('checklist')
            ->where('id', '=', $id)
            ->update(['vistoria' => $vistoriaJson]);
    }

    /**
     * Salva assinatura e finaliza o checklist
     * Filtro por chave automatico via sessao
     *
     * @param int $id ID do checklist
     * @param string $filename Nome do arquivo da assinatura
     * @return int Linhas afetadas
     */
    public function salvarAssinatura(int $id, string $filename): int
    {
        return $this->qb
            ->table('checklist')
            ->where('id', '=', $id)
            ->update([
                'assinatura_unica' => $filename,
                'status' => '2',
                'data_checklist' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Busca checklist por ID com dados completos (novo formato)
     *
     * @param int $id ID do checklist
     * @param string $chave Chave do tenant
     * @return array|null
     */
    public function buscarPorIdCompleto(int $id, string $chave): ?array
    {
        return $this->qb
            ->table('checklist', 'c')
            ->select([
                'c.*',
                'cm.nome AS modelo_nome',
                'cm.questoes AS modelo_questoes',
                'cm.vistoria AS modelo_vistoria',
                'v.placa',
                'v.modelo AS veiculo_modelo',
                'v.marca',
                'v.odometro AS veiculo_odometro',
                'v.tanque_fracao',
                'v.id_matriz_filial',
                'l.codigo AS locacao_codigo',
                'l.cliente_nome AS locacao_cliente',
                'ct.codigo AS contrato_codigo',
            ])
            ->leftJoin('checklist_modelos', 'cm', 'c.id_modelo', '=', 'cm.id')
            ->leftJoin('veiculos', 'v', 'c.id_veiculo', '=', 'v.id')
            ->leftJoin('locacoes', 'l', 'c.id_locacao', '=', 'l.id')
            ->leftJoin('contratos', 'ct', 'c.id_contrato', '=', 'ct.id')
            ->where('c.id', '=', $id)
            ->first();
    }

    /**
     * Busca locacoes ativas para vincular ao checklist
     *
     * @param string $chave Chave do tenant
     * @param string $search Termo de busca
     * @param string|null $filialWhere Clausula WHERE de filiais
     * @param array $filialParams Parametros da clausula de filiais
     * @return array
     */
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
            ])
            ->leftJoin('locacoes_veiculos', 'lv', 'l.id', '=', 'lv.id_locacao')
            ->leftJoin('veiculos', 'v', 'lv.id_veiculo', '=', 'v.id')
            ->whereIn('l.status', ['A', 'R']);

        if (!empty($filialWhere)) {
            $query->whereRaw($filialWhere, $filialParams);
        }

        if (!empty($search)) {
            $query->whereRaw(
                '(l.codigo LIKE ? OR l.cliente_nome LIKE ? OR v.placa LIKE ?)',
                ['%' . $search . '%', '%' . $search . '%', '%' . $search . '%']
            );
        }

        return $query
            ->orderBy('l.created_at', 'DESC')
            ->limit(20)
            ->get();
    }

    /**
     * Busca contratos ativos para vincular ao checklist
     *
     * @param string $chave Chave do tenant
     * @param string $search Termo de busca
     * @param string|null $filialWhere Clausula WHERE de filiais
     * @param array $filialParams Parametros da clausula de filiais
     * @return array
     */
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
            ])
            ->leftJoin('clientes', 'cl', 'ct.id_cliente', '=', 'cl.id')
            ->leftJoin('contratos_veiculos', 'cv', 'ct.id', '=', 'cv.id_contrato')
            ->leftJoin('veiculos', 'v', 'cv.id_veiculo', '=', 'v.id')
            ->whereIn('ct.status', ['A', 'R']);

        if (!empty($filialWhere)) {
            $query->whereRaw($filialWhere, $filialParams);
        }

        if (!empty($search)) {
            $query->whereRaw(
                '(ct.codigo LIKE ? OR cl.nome_rsocial LIKE ? OR v.placa LIKE ?)',
                ['%' . $search . '%', '%' . $search . '%', '%' . $search . '%']
            );
        }

        return $query
            ->orderBy('ct.created_at', 'DESC')
            ->limit(20)
            ->get();
    }

    /**
     * Busca veiculos disponiveis para checklist avulso
     *
     * @param string $chave Chave do tenant
     * @param string $search Termo de busca
     * @param string|null $filialWhere Clausula WHERE de filiais
     * @param array $filialParams Parametros da clausula de filiais
     * @return array
     */
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
                'v.odometro',
                'v.tanque_fracao',
                'v.tipo_combustivel',
                'v.id_matriz_filial',
            ])
            ->whereNotIn('v.disponibilidade', ['V', 'RO', 'E']);

        if (!empty($filialWhere)) {
            $query->whereRaw($filialWhere, $filialParams);
        }

        if (!empty($search)) {
            $query->whereRaw(
                '(v.placa LIKE ? OR v.modelo LIKE ? OR v.marca LIKE ?)',
                ['%' . $search . '%', '%' . $search . '%', '%' . $search . '%']
            );
        }

        return $query
            ->orderBy('v.placa', 'ASC')
            ->limit(20)
            ->get();
    }
}
