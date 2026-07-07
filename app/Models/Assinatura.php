<?php

namespace App\Models;

use App\Core\Auth;
use App\Helpers\FileHelper;
use App\Helpers\ImageHelper;

/**
 * Model Assinatura
 *
 * Gerencia assinaturas digitais de contratos, locacoes e promissorias.
 * Armazena assinaturas em arquivos (nao base64) para melhor performance.
 */
class Assinatura extends Model
{
    /**
     * Lista documentos ainda sem assinatura para consumo pelo app externo.
     *
     * @param string $chave Tenant autenticado
     * @param array $tipos Tipos permitidos: contrato, locacao, promissoria
     * @param string $search Termo de busca opcional
     * @param int $page Pagina atual
     * @param int $perPage Registros por pagina
     * @param array $filiaisPermitidas IDs de filiais permitidas; vazio significa acesso total
     * @return array{data: array, total: int}
     */
    public function listarPendentesParaApp(
        string $chave,
        array $tipos,
        string $search = '',
        int $page = 1,
        int $perPage = 20,
        array $filiaisPermitidas = []
    ): array {
        $tipos = array_values(array_intersect($tipos, ['contrato', 'locacao', 'promissoria']));
        $itens = [];

        if (in_array('contrato', $tipos, true)) {
            $itens = array_merge($itens, $this->listarContratosPendentes($chave, $search, $filiaisPermitidas));
        }

        if (in_array('locacao', $tipos, true)) {
            $itens = array_merge($itens, $this->listarLocacoesPendentes($chave, $search, $filiaisPermitidas));
        }

        if (in_array('promissoria', $tipos, true)) {
            $itens = array_merge($itens, $this->listarPromissoriasPendentes($chave, $search, $filiaisPermitidas));
        }

        usort($itens, static function (array $a, array $b): int {
            return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
        });

        $total = count($itens);
        $offset = max(0, ($page - 1) * $perPage);
        $data = array_slice($itens, $offset, $perPage);

        foreach ($data as &$item) {
            unset($item['created_at']);
        }

        return [
            'data' => $data,
            'total' => $total,
        ];
    }

    private function listarContratosPendentes(string $chave, string $search, array $filiaisPermitidas): array
    {
        $query = $this->qb
            ->table('contratos', 'c')
            ->withChave($chave)
            ->select([
                'c.id',
                'c.codigo',
                'c.id_cliente AS cliente_id',
                'COALESCE(cl.nome_rsocial, c.cliente_nome) AS cliente_nome',
                'cl.cpf_cnpj AS cliente_documento',
                'c.data_ini AS data_inicio',
                'c.data_fim AS data_fim',
                'c.total_pagar AS valor_total',
                'c.status',
                'c.created_at',
            ])
            ->selectRaw("'contrato' AS tipo")
            ->selectRaw('c.codigo AS codigo_assinatura')
            ->selectRaw("
                COALESCE(
                    (
                        SELECT CONCAT(v_ativo.placa, ' - ', v_ativo.modelo)
                        FROM contratos_veiculos cv_ativo
                        LEFT JOIN veiculos v_ativo
                            ON v_ativo.id = cv_ativo.id_veiculo
                            AND v_ativo.chave = cv_ativo.chave
                        WHERE cv_ativo.id_contrato = c.id
                          AND cv_ativo.chave = c.chave
                          AND cv_ativo.data_entrada IS NULL
                        ORDER BY cv_ativo.data_saida ASC, cv_ativo.id ASC
                        LIMIT 1
                    ),
                    (
                        SELECT CONCAT(v_resumo.placa, ' - ', v_resumo.modelo)
                        FROM contratos_veiculos cv_resumo
                        LEFT JOIN veiculos v_resumo
                            ON v_resumo.id = cv_resumo.id_veiculo
                            AND v_resumo.chave = cv_resumo.chave
                        WHERE cv_resumo.id_contrato = c.id
                          AND cv_resumo.chave = c.chave
                        ORDER BY cv_resumo.data_saida ASC, cv_resumo.id ASC
                        LIMIT 1
                    )
                ) AS veiculo_texto
            ")
            ->leftJoinRaw('clientes', 'cl', 'cl.id = c.id_cliente AND cl.chave = c.chave')
            ->leftJoinRaw('assinaturas', 'asn', 'asn.id_contrato = c.id AND asn.chave = c.chave')
            ->whereNull('asn.id');

        if (!empty($filiaisPermitidas)) {
            $query->whereIn('c.id_matriz_filial_retirada', $filiaisPermitidas);
        }

        $this->aplicarBuscaDocumentoPendente($query, $search, [
            'c.codigo',
            'cl.nome_rsocial',
            'c.cliente_nome',
            'cl.cpf_cnpj',
        ], "EXISTS (
            SELECT 1
            FROM contratos_veiculos cv_busca
            INNER JOIN veiculos v_busca
                ON v_busca.id = cv_busca.id_veiculo
                AND v_busca.chave = cv_busca.chave
            WHERE cv_busca.id_contrato = c.id
              AND cv_busca.chave = c.chave
              AND (
                  v_busca.placa LIKE ?
                  OR v_busca.modelo LIKE ?
                  OR v_busca.marca LIKE ?
              )
        )");

        return $query->orderByDesc('c.created_at')->get();
    }

    private function listarLocacoesPendentes(string $chave, string $search, array $filiaisPermitidas): array
    {
        $query = $this->qb
            ->table('locacoes', 'l')
            ->withChave($chave)
            ->select([
                'l.id',
                'l.codigo',
                'l.id_cliente AS cliente_id',
                'COALESCE(cl.nome_rsocial, l.cliente_nome) AS cliente_nome',
                'cl.cpf_cnpj AS cliente_documento',
                'l.data_saida AS data_inicio',
                'COALESCE(l.data_chegada, l.data_prevista) AS data_fim',
                'l.total_pagar AS valor_total',
                'l.status',
                'l.created_at',
            ])
            ->selectRaw("'locacao' AS tipo")
            ->selectRaw('l.codigo AS codigo_assinatura')
            ->selectRaw("
                (
                    SELECT CONCAT(v.placa, ' - ', v.modelo)
                    FROM locacoes_veiculos lv
                    LEFT JOIN veiculos v
                        ON v.id = lv.id_veiculo
                        AND v.chave = lv.chave
                    WHERE lv.id_locacao = l.id
                      AND lv.chave = l.chave
                      AND lv.id_veiculo IS NOT NULL
                    ORDER BY CASE WHEN lv.data_entrada IS NULL THEN 0 ELSE 1 END,
                             lv.data_saida DESC,
                             lv.id DESC
                    LIMIT 1
                ) AS veiculo_texto
            ")
            ->leftJoinRaw('clientes', 'cl', 'cl.id = l.id_cliente AND cl.chave = l.chave')
            ->leftJoinRaw('assinaturas', 'asn', 'asn.id_locacao = l.id AND asn.chave = l.chave')
            ->whereNull('asn.id');

        if (!empty($filiaisPermitidas)) {
            $placeholders = implode(',', array_fill(0, count($filiaisPermitidas), '?'));
            $query->whereRaw(
                "(l.id_matriz_filial_retirada IN ({$placeholders}) OR l.id_matriz_filial_devolucao IN ({$placeholders}))",
                array_merge($filiaisPermitidas, $filiaisPermitidas)
            );
        }

        $this->aplicarBuscaDocumentoPendente($query, $search, [
            'l.codigo',
            'cl.nome_rsocial',
            'l.cliente_nome',
            'cl.cpf_cnpj',
        ], "EXISTS (
            SELECT 1
            FROM locacoes_veiculos lv_busca
            INNER JOIN veiculos v_busca
                ON v_busca.id = lv_busca.id_veiculo
                AND v_busca.chave = lv_busca.chave
            WHERE lv_busca.id_locacao = l.id
              AND lv_busca.chave = l.chave
              AND (
                  v_busca.placa LIKE ?
                  OR v_busca.modelo LIKE ?
                  OR v_busca.marca LIKE ?
              )
        )");

        return $query->orderByDesc('l.created_at')->get();
    }

    private function listarPromissoriasPendentes(string $chave, string $search, array $filiaisPermitidas): array
    {
        $query = $this->qb
            ->table('promissorias', 'p')
            ->withChave($chave)
            ->select([
                'MIN(p.id) AS id',
                'p.codigo_base AS codigo',
                'p.codigo_base AS codigo_assinatura',
                'MAX(p.id_cliente) AS cliente_id',
                'MAX(c.nome_rsocial) AS cliente_nome',
                'MAX(c.cpf_cnpj) AS cliente_documento',
                'MIN(CASE WHEN p.pago = \'N\' THEN p.data_vencimento ELSE NULL END) AS data_inicio',
                'MAX(p.data_vencimento) AS data_fim',
                'SUM(p.valor_parcela) AS valor_total',
                'CASE WHEN SUM(CASE WHEN p.pago = \'N\' THEN 1 ELSE 0 END) = 0 THEN \'S\' ELSE \'N\' END AS status',
                'MAX(p.created_at) AS created_at',
            ])
            ->selectRaw("'promissoria' AS tipo")
            ->selectRaw('MAX(p.codigo_contrato_locacao) AS veiculo_texto')
            ->leftJoinRaw('clientes', 'c', 'c.id = p.id_cliente AND c.chave = p.chave')
            ->leftJoinRaw('assinaturas', 'asn', 'asn.codigo_promissoria = p.codigo_base AND asn.chave = p.chave')
            ->whereNotNull('p.codigo_base')
            ->whereNull('asn.id');

        if (!empty($filiaisPermitidas)) {
            $query->whereIn('p.id_matriz_filial', $filiaisPermitidas);
        }

        $this->aplicarBuscaDocumentoPendente($query, $search, [
            'p.codigo_base',
            'p.codigo_contrato_locacao',
            'c.nome_rsocial',
            'c.cpf_cnpj',
        ]);

        return $query
            ->groupBy('p.codigo_base')
            ->orderByRaw('MAX(p.created_at) DESC')
            ->get();
    }

    private function aplicarBuscaDocumentoPendente(
        \App\Classes\QueryBuilder $query,
        string $search,
        array $columns,
        ?string $extraExistsSql = null
    ): void {
        $search = trim($search);
        if ($search === '') {
            return;
        }

        $searchTerm = '%' . $search . '%';
        $parts = [];
        $params = [];

        foreach ($columns as $column) {
            $parts[] = "{$column} LIKE ?";
            $params[] = $searchTerm;
        }

        if ($extraExistsSql !== null) {
            $parts[] = $extraExistsSql;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $query->whereRaw('(' . implode(' OR ', $parts) . ')', $params);
    }

    /**
     * Busca assinatura por ID
     *
     * @param int $id ID da assinatura
     * @return array|null
     */
    public function buscarPorId(int $id): ?array
    {
        $assinatura = $this->qb
            ->table('assinaturas')
            ->where('id', '=', $id)
            ->first();

        if ($assinatura) {
            $assinatura['url'] = $this->getUrl($assinatura);
        }

        return $assinatura;
    }

    /**
     * Busca assinatura por contrato
     *
     * @param int $idContrato ID do contrato
     * @param string $tipo Tipo de assinatura (cliente, testemunha, fiador, avalista)
     * @return array|null
     */
    public function buscarPorContrato(int $idContrato, string $tipo = 'cliente', ?string $chave = null): ?array
    {
        $query = $this->qb
            ->table('assinaturas')
            ->where('id_contrato', '=', $idContrato)
            ->where('tipo', '=', $tipo)
            ->orderBy('created_at', 'DESC');

        if ($chave !== null) {
            $query->withChave($chave);
        }

        $assinatura = $query->first();

        if ($assinatura) {
            $assinatura['url'] = $this->getUrl($assinatura);
        }

        return $assinatura;
    }

    /**
     * Busca assinatura por locacao
     *
     * @param int $idLocacao ID da locacao
     * @param string $tipo Tipo de assinatura
     * @return array|null
     */
    public function buscarPorLocacao(int $idLocacao, string $tipo = 'cliente', ?string $chave = null): ?array
    {
        $query = $this->qb
            ->table('assinaturas')
            ->where('id_locacao', '=', $idLocacao)
            ->where('tipo', '=', $tipo)
            ->orderBy('created_at', 'DESC');

        if ($chave !== null) {
            $query->withChave($chave);
        }

        $assinatura = $query->first();

        if ($assinatura) {
            $assinatura['url'] = $this->getUrl($assinatura);
        }

        return $assinatura;
    }

    /**
     * Busca assinatura por promissoria agrupada pelo codigo base.
     */
    public function buscarPorPromissoria(string $codigoBase, string $tipo = 'cliente', ?string $chave = null): ?array
    {
        $query = $this->qb
            ->table('assinaturas')
            ->where('codigo_promissoria', '=', $codigoBase)
            ->where('tipo', '=', $tipo)
            ->orderBy('created_at', 'DESC');

        if ($chave !== null) {
            $query->withChave($chave);
        }

        $assinatura = $query->first();

        if ($assinatura) {
            $assinatura['url'] = $this->getUrl($assinatura);
        }

        return $assinatura;
    }

    /**
     * Lista todas assinaturas de um contrato
     *
     * @param int $idContrato ID do contrato
     * @return array
     */
    public function listarPorContrato(int $idContrato): array
    {
        $assinaturas = $this->qb
            ->table('assinaturas')
            ->where('id_contrato', '=', $idContrato)
            ->orderBy('created_at', 'ASC')
            ->get();

        foreach ($assinaturas as &$assinatura) {
            $assinatura['url'] = $this->getUrl($assinatura);
        }

        return $assinaturas;
    }

    /**
     * Lista todas assinaturas de uma locacao
     *
     * @param int $idLocacao ID da locacao
     * @return array
     */
    public function listarPorLocacao(int $idLocacao): array
    {
        $assinaturas = $this->qb
            ->table('assinaturas')
            ->where('id_locacao', '=', $idLocacao)
            ->orderBy('created_at', 'ASC')
            ->get();

        foreach ($assinaturas as &$assinatura) {
            $assinatura['url'] = $this->getUrl($assinatura);
        }

        return $assinaturas;
    }

    /**
     * Lista todas assinaturas de um cliente
     *
     * @param int $idCliente ID do cliente
     * @return array
     */
    public function listarPorCliente(int $idCliente): array
    {
        $assinaturas = $this->qb
            ->table('assinaturas')
            ->where('id_cliente', '=', $idCliente)
            ->orderBy('created_at', 'DESC')
            ->get();

        foreach ($assinaturas as &$assinatura) {
            $assinatura['url'] = $this->getUrl($assinatura);
        }

        return $assinaturas;
    }

    /**
     * Salva uma nova assinatura
     *
     * @param array $dados Dados da assinatura:
     *   - base64: string (obrigatorio) - Imagem em base64
     *   - id_contrato: int|null
     *   - id_locacao: int|null
     *   - codigo_promissoria: string|null
     *   - id_cliente: int|null
     *   - ip_address: string (obrigatorio)
     *   - user_agent: string|null
     *   - latitude: float|null
     *   - longitude: float|null
     *   - tipo: string (cliente|testemunha|fiador|avalista)
     *   - observacao: string|null
     *   - chave: string|null (usa Auth::chave() se nao fornecido)
     * @return int ID da assinatura criada
     * @throws \InvalidArgumentException Se dados obrigatorios faltarem
     */
    public function salvar(array $dados): int
    {
        // Validacoes
        if (empty($dados['base64'])) {
            throw new \InvalidArgumentException('Assinatura (base64) e obrigatoria');
        }

        if (
            empty($dados['id_contrato'])
            && empty($dados['id_locacao'])
            && empty($dados['codigo_promissoria'])
        ) {
            throw new \InvalidArgumentException('ID do contrato, locacao ou promissoria e obrigatorio');
        }

        $chave = $dados['chave'] ?? Auth::chave();
        if (!$chave) {
            throw new \InvalidArgumentException('Chave do tenant e obrigatoria');
        }

        // Salva arquivo usando ImageHelper (converte para WebP automaticamente)
        $filename = ImageHelper::save($dados['base64'], 'assinatura', chave: $chave);
        if (!$filename) {
            throw new \RuntimeException('Erro ao salvar arquivo de assinatura');
        }

        // Gera hash do arquivo para verificacao de integridade
        $filepath = FileHelper::getPath($filename, $chave);
        $hashArquivo = file_exists($filepath) ? hash_file('sha256', $filepath) : null;

        // Em rotas publicas pode nao existir sessao; use a chave do registro assinado.
        $query = $this->qb->table('assinaturas');
        if ($chave) {
            $query->withChave($chave);
        }

        return $query->insert([
            'id_contrato' => $dados['id_contrato'] ?? null,
            'id_locacao' => $dados['id_locacao'] ?? null,
            'codigo_promissoria' => $dados['codigo_promissoria'] ?? null,
            'id_cliente' => $dados['id_cliente'] ?? null,
            'arquivo' => $filename,
            'hash_arquivo' => $hashArquivo,
            'ip_address' => $dados['ip_address'] ?? '0.0.0.0',
            'user_agent' => $dados['user_agent'] ?? null,
            'latitude' => $dados['latitude'] ?? null,
            'longitude' => $dados['longitude'] ?? null,
            'tipo' => $dados['tipo'] ?? 'cliente',
            'observacao' => $dados['observacao'] ?? null,
            'created_at' => now(),
        ]);
    }

    /**
     * Exclui uma assinatura (registro e arquivo)
     *
     * @param int $id ID da assinatura
     * @return bool
     */
    public function excluir(int $id): bool
    {
        $assinatura = $this->buscarPorId($id);
        if (!$assinatura) {
            return false;
        }

        // Remove arquivo
        if (!empty($assinatura['arquivo'])) {
            FileHelper::delete($assinatura['arquivo'], $assinatura['chave']);
        }

        // Remove registro
        return $this->qb
            ->table('assinaturas')
            ->where('id', '=', $id)
            ->delete() > 0;
    }

    /**
     * Exclui assinaturas de um contrato
     *
     * @param int $idContrato ID do contrato
     * @return int Quantidade de registros excluidos
     */
    public function excluirPorContrato(int $idContrato): int
    {
        $assinaturas = $this->listarPorContrato($idContrato);
        $count = 0;

        foreach ($assinaturas as $assinatura) {
            if ($this->excluir($assinatura['id'])) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Exclui assinaturas de uma locacao
     *
     * @param int $idLocacao ID da locacao
     * @return int Quantidade de registros excluidos
     */
    public function excluirPorLocacao(int $idLocacao): int
    {
        $assinaturas = $this->listarPorLocacao($idLocacao);
        $count = 0;

        foreach ($assinaturas as $assinatura) {
            if ($this->excluir($assinatura['id'])) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Exclui assinaturas de uma promissoria agrupada pelo codigo base.
     */
    public function excluirPorPromissoria(string $codigoBase): int
    {
        $assinaturas = $this->qb
            ->table('assinaturas')
            ->where('codigo_promissoria', '=', $codigoBase)
            ->orderBy('created_at', 'ASC')
            ->get();

        $count = 0;

        foreach ($assinaturas as $assinatura) {
            if ($this->excluir((int) $assinatura['id'])) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Verifica integridade do arquivo de assinatura
     *
     * @param int $id ID da assinatura
     * @return bool True se arquivo integro
     */
    public function verificarIntegridade(int $id): bool
    {
        $assinatura = $this->qb
            ->table('assinaturas')
            ->where('id', '=', $id)
            ->first();

        if (!$assinatura || empty($assinatura['hash_arquivo'])) {
            return false;
        }

        $filepath = FileHelper::getPath($assinatura['arquivo'], $assinatura['chave']);
        if (!file_exists($filepath)) {
            return false;
        }

        $currentHash = hash_file('sha256', $filepath);
        return hash_equals($assinatura['hash_arquivo'], $currentHash);
    }

    /**
     * Gera URL publica para o arquivo de assinatura
     *
     * @param array $assinatura Dados da assinatura
     * @return string URL publica ou string vazia
     */
    public function getUrl(array $assinatura): string
    {
        if (empty($assinatura['arquivo']) || empty($assinatura['chave'])) {
            return '';
        }

        return FileHelper::url($assinatura['arquivo'], $assinatura['chave']);
    }

    /**
     * Verifica se contrato tem assinatura
     *
     * @param int $idContrato ID do contrato
     * @return bool
     */
    public function contratoTemAssinatura(int $idContrato, ?string $chave = null): bool
    {
        $query = $this->qb
            ->table('assinaturas')
            ->where('id_contrato', '=', $idContrato);

        if ($chave !== null) {
            $query->withChave($chave);
        }

        return $query->count() > 0;
    }

    /**
     * Verifica se locacao tem assinatura
     *
     * @param int $idLocacao ID da locacao
     * @return bool
     */
    public function locacaoTemAssinatura(int $idLocacao, ?string $chave = null): bool
    {
        $query = $this->qb
            ->table('assinaturas')
            ->where('id_locacao', '=', $idLocacao);

        if ($chave !== null) {
            $query->withChave($chave);
        }

        return $query->count() > 0;
    }

    /**
     * Verifica se promissoria tem assinatura.
     */
    public function promissoriaTemAssinatura(string $codigoBase, ?string $chave = null): bool
    {
        $query = $this->qb
            ->table('assinaturas')
            ->where('codigo_promissoria', '=', $codigoBase);

        if ($chave !== null) {
            $query->withChave($chave);
        }

        return $query->count() > 0;
    }

    /**
     * Registra verificacao de assinatura
     *
     * @param int $id ID da assinatura
     * @return bool
     */
    public function registrarVerificacao(int $id): bool
    {
        return $this->qb
            ->table('assinaturas')
            ->where('id', '=', $id)
            ->update([
                'verificado_em' => now()
            ]) > 0;
    }

    /**
     * Gera token de verificacao para assinatura
     *
     * @param int $id ID da assinatura
     * @return string|null Token gerado ou null se falhar
     */
    public function gerarTokenVerificacao(int $id): ?string
    {
        $token = bin2hex(random_bytes(32));

        $updated = $this->qb
            ->table('assinaturas')
            ->where('id', '=', $id)
            ->update([
                'token_verificacao' => $token
            ]);

        return $updated > 0 ? $token : null;
    }

    /**
     * Busca assinatura por token de verificacao
     *
     * @param string $token Token de verificacao
     * @return array|null
     */
    public function buscarPorToken(string $token): ?array
    {
        $assinatura = $this->qb
            ->table('assinaturas')
            ->where('token_verificacao', '=', $token)
            ->first();

        if ($assinatura) {
            $assinatura['url'] = $this->getUrl($assinatura);
        }

        return $assinatura;
    }
}
