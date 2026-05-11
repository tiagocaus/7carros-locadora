<?php

namespace App\Models;

/**
 * Model FeatureRequest
 *
 * Gerencia pedidos de novos recursos/funcionalidades.
 * Sistema CROSS-TENANT: todos os tenants podem ver e votar em todos os pedidos.
 * Apenas admin 7Carros pode alterar status.
 */
class FeatureRequest extends Model
{
    /**
     * Status que indicam que o pedido está aberto
     */
    public const STATUS_ABERTOS = ['pendente', 'em_analise', 'em_desenvolvimento', 'aguardando_info'];

    /**
     * Status que indicam que o pedido está fechado
     */
    public const STATUS_FECHADOS = ['concluido', 'recusado'];

    /**
     * Labels para cada status
     */
    public const STATUS_LABELS = [
        'pendente' => 'Pendente',
        'em_analise' => 'Em Análise',
        'em_desenvolvimento' => 'Em Desenvolvimento',
        'concluido' => 'Concluído',
        'recusado' => 'Recusado',
        'aguardando_info' => 'Aguardando Informações',
    ];

    /**
     * Cores CSS para cada status (Tailwind)
     */
    public const STATUS_CORES = [
        'pendente' => 'bg-yellow-100 text-yellow-800',
        'em_analise' => 'bg-blue-100 text-blue-800',
        'em_desenvolvimento' => 'bg-purple-100 text-purple-800',
        'concluido' => 'bg-green-100 text-green-800',
        'recusado' => 'bg-red-100 text-red-800',
        'aguardando_info' => 'bg-orange-100 text-orange-800',
    ];

    /**
     * Labels para prioridades
     */
    public const PRIORIDADE_LABELS = [
        'baixa' => 'Baixa',
        'normal' => 'Normal',
        'alta' => 'Alta',
        'critica' => 'Crítica',
    ];

    /**
     * Lista pedidos com paginação e filtros (CROSS-TENANT)
     *
     * @param int $page Página atual
     * @param int $perPage Registros por página
     * @param array $filtros Filtros opcionais (status, modulo_id, search, ordenar)
     * @return array Lista de pedidos
     */
    public function listarPaginado(int $page, int $perPage, array $filtros = []): array
    {
        $query = $this->qb
            ->table('feature_requests fr')
            ->select([
                'fr.*',
                'm.nome as modulo_nome',
                'm.icone as modulo_icone',
            ])
            ->leftJoin('feature_request_modules', 'm', 'm.id', '=', 'fr.modulo_id');

        // Filtro por status
        if (!empty($filtros['status'])) {
            $query->where('fr.status', '=', $filtros['status']);
        }

        // Filtro por módulo
        if (!empty($filtros['modulo_id'])) {
            $query->where('fr.modulo_id', '=', (int) $filtros['modulo_id']);
        }

        // Filtro por meus pedidos (chave do tenant atual)
        if (!empty($filtros['meus_pedidos']) && !empty($filtros['chave'])) {
            $query->where('fr.chave', '=', $filtros['chave']);
        }

        // Busca por texto
        if (!empty($filtros['search'])) {
            $searchTerm = '%' . $filtros['search'] . '%';
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('fr.titulo', 'LIKE', $searchTerm)
                  ->orWhere('fr.descricao', 'LIKE', $searchTerm);
            });
        }

        // Ordenação
        $ordenar = $filtros['ordenar'] ?? 'recentes';
        switch ($ordenar) {
            case 'votos':
                $query->orderBy('fr.total_votos', 'DESC');
                break;
            case 'antigos':
                $query->orderBy('fr.created_at', 'ASC');
                break;
            case 'recentes':
            default:
                $query->orderBy('fr.created_at', 'DESC');
                break;
        }

        return $query
            ->paginate($page, $perPage)
            ->withoutChave()
            ->get();
    }

    /**
     * Conta total de pedidos (CROSS-TENANT)
     *
     * @param array $filtros Mesmos filtros de listarPaginado
     * @return int Total
     */
    public function contar(array $filtros = []): int
    {
        $query = $this->qb
            ->table('feature_requests fr');

        // Aplicar mesmos filtros
        if (!empty($filtros['status'])) {
            $query->where('fr.status', '=', $filtros['status']);
        }

        if (!empty($filtros['modulo_id'])) {
            $query->where('fr.modulo_id', '=', (int) $filtros['modulo_id']);
        }

        if (!empty($filtros['meus_pedidos']) && !empty($filtros['chave'])) {
            $query->where('fr.chave', '=', $filtros['chave']);
        }

        if (!empty($filtros['search'])) {
            $searchTerm = '%' . $filtros['search'] . '%';
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('fr.titulo', 'LIKE', $searchTerm)
                  ->orWhere('fr.descricao', 'LIKE', $searchTerm);
            });
        }

        return $query->withoutChave()->count();
    }

    /**
     * Busca um pedido por ID com dados relacionados
     *
     * @param int $id ID do pedido
     * @return array|null Dados completos ou null
     */
    public function buscarPorId(int $id): ?array
    {
        $pedido = $this->qb
            ->table('feature_requests fr')
            ->select([
                'fr.*',
                'm.nome as modulo_nome',
                'm.icone as modulo_icone',
            ])
            ->leftJoin('feature_request_modules', 'm', 'm.id', '=', 'fr.modulo_id')
            ->where('fr.id', '=', $id)
            ->withoutChave()
            ->first();

        if (!$pedido) {
            return null;
        }

        // Adicionar labels
        $pedido['status_label'] = self::STATUS_LABELS[$pedido['status']] ?? $pedido['status'];
        $pedido['status_cor'] = self::STATUS_CORES[$pedido['status']] ?? 'bg-gray-100 text-gray-800';
        $pedido['prioridade_label'] = self::PRIORIDADE_LABELS[$pedido['prioridade']] ?? $pedido['prioridade'];

        return $pedido;
    }

    /**
     * Busca pedidos similares usando FULLTEXT (para busca inteligente)
     *
     * @param string $termo Termo de busca
     * @param int $limite Máximo de resultados (default 5)
     * @param int|null $excluirId ID para excluir dos resultados
     * @return array Lista de pedidos similares
     */
    public function buscarSimilares(string $termo, int $limite = 5, ?int $excluirId = null): array
    {
        $termo = trim($termo);
        if (strlen($termo) < 3) {
            return [];
        }

        // Preparar termo para FULLTEXT (modo natural language)
        $termoEscapado = $this->getMysqli()->real_escape_string($termo);

        $sql = "
            SELECT
                fr.id, fr.titulo, fr.status, fr.total_votos, fr.created_at,
                m.nome as modulo_nome, m.icone as modulo_icone,
                MATCH(fr.titulo, fr.descricao) AGAINST ('{$termoEscapado}') as relevancia
            FROM feature_requests fr
            LEFT JOIN feature_request_modules m ON m.id = fr.modulo_id
            WHERE MATCH(fr.titulo, fr.descricao) AGAINST ('{$termoEscapado}' IN NATURAL LANGUAGE MODE)
        ";

        if ($excluirId) {
            $sql .= " AND fr.id != " . (int) $excluirId;
        }

        $sql .= " ORDER BY relevancia DESC LIMIT " . (int) $limite;

        $result = $this->getMysqli()->query($sql);

        if (!$result) {
            return [];
        }

        $pedidos = [];
        while ($row = $result->fetch_assoc()) {
            $row['status_label'] = self::STATUS_LABELS[$row['status']] ?? $row['status'];
            $row['status_cor'] = self::STATUS_CORES[$row['status']] ?? 'bg-gray-100 text-gray-800';
            $pedidos[] = $row;
        }

        return $pedidos;
    }

    /**
     * Cria um novo pedido
     *
     * @param array $dados Dados do pedido
     * @return int ID criado
     */
    public function criar(array $dados): int
    {
        return $this->qb
            ->table('feature_requests')
            ->withoutChave()
            ->insert([
                'chave' => $dados['chave'],
                'titulo' => $dados['titulo'],
                'descricao' => $dados['descricao'],
                'modulo_id' => $dados['modulo_id'] ?? null,
                'usuario_id' => $dados['usuario_id'] ?? null,
                'nome_solicitante' => $dados['nome_solicitante'] ?? null,
                'email_solicitante' => $dados['email_solicitante'],
                'telefone_solicitante' => $dados['telefone_solicitante'] ?? null,
                'status' => 'pendente',
                'prioridade' => $dados['prioridade'] ?? 'normal',
                'total_votos' => 0,
                'total_seguidores' => 0,
            ]);
    }

    /**
     * Atualiza um pedido existente
     *
     * @param int $id ID do pedido
     * @param array $dados Dados para atualizar
     * @return int Linhas afetadas
     */
    public function atualizar(int $id, array $dados): int
    {
        $dadosUpdate = [];

        if (isset($dados['titulo'])) {
            $dadosUpdate['titulo'] = $dados['titulo'];
        }
        if (isset($dados['descricao'])) {
            $dadosUpdate['descricao'] = $dados['descricao'];
        }
        if (isset($dados['modulo_id'])) {
            $dadosUpdate['modulo_id'] = $dados['modulo_id'] ?: null;
        }

        if (empty($dadosUpdate)) {
            return 0;
        }

        return $this->qb
            ->table('feature_requests')
            ->withoutChave()
            ->where('id', '=', $id)
            ->update($dadosUpdate);
    }

    /**
     * Atualiza status do pedido (apenas admin)
     *
     * @param int $id ID do pedido
     * @param string $status Novo status
     * @param string|null $resposta Resposta do admin (opcional)
     * @param int|null $respondidoPor ID do funcionário que respondeu
     * @param string|null $prioridade Nova prioridade (opcional)
     * @return int Linhas afetadas
     */
    public function atualizarStatus(int $id, string $status, ?string $resposta = null, ?int $respondidoPor = null, ?string $prioridade = null): int
    {
        $dadosUpdate = ['status' => $status];

        if ($prioridade !== null) {
            $dadosUpdate['prioridade'] = $prioridade;
        }

        if ($resposta !== null) {
            $dadosUpdate['resposta_admin'] = $resposta;
            $dadosUpdate['respondido_por'] = $respondidoPor;
            $dadosUpdate['respondido_em'] = date('Y-m-d H:i:s');
        }

        return $this->qb
            ->table('feature_requests')
            ->withoutChave()
            ->where('id', '=', $id)
            ->update($dadosUpdate);
    }

    /**
     * Atualiza prioridade do pedido (apenas admin)
     *
     * @param int $id ID do pedido
     * @param string $prioridade Nova prioridade
     * @return int Linhas afetadas
     */
    public function atualizarPrioridade(int $id, string $prioridade): int
    {
        return $this->qb
            ->table('feature_requests')
            ->withoutChave()
            ->where('id', '=', $id)
            ->update(['prioridade' => $prioridade]);
    }

    /**
     * Incrementa contador de votos
     *
     * @param int $id ID do pedido
     * @return int Linhas afetadas
     */
    public function incrementarVotos(int $id): int
    {
        $this->getMysqli()->query("UPDATE feature_requests SET total_votos = total_votos + 1 WHERE id = " . (int) $id);
        return $this->getMysqli()->affected_rows;
    }

    /**
     * Decrementa contador de votos
     *
     * @param int $id ID do pedido
     * @return int Linhas afetadas
     */
    public function decrementarVotos(int $id): int
    {
        $this->getMysqli()->query("UPDATE feature_requests SET total_votos = GREATEST(0, total_votos - 1) WHERE id = " . (int) $id);
        return $this->getMysqli()->affected_rows;
    }

    /**
     * Incrementa contador de seguidores
     *
     * @param int $id ID do pedido
     * @return int Linhas afetadas
     */
    public function incrementarSeguidores(int $id): int
    {
        $this->getMysqli()->query("UPDATE feature_requests SET total_seguidores = total_seguidores + 1 WHERE id = " . (int) $id);
        return $this->getMysqli()->affected_rows;
    }

    /**
     * Decrementa contador de seguidores
     *
     * @param int $id ID do pedido
     * @return int Linhas afetadas
     */
    public function decrementarSeguidores(int $id): int
    {
        $this->getMysqli()->query("UPDATE feature_requests SET total_seguidores = GREATEST(0, total_seguidores - 1) WHERE id = " . (int) $id);
        return $this->getMysqli()->affected_rows;
    }

    /**
     * Exclui um pedido
     *
     * @param int $id ID do pedido
     * @return int Linhas afetadas
     */
    public function excluir(int $id): int
    {
        return $this->qb
            ->table('feature_requests')
            ->withoutChave()
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Lista todos os módulos disponíveis
     *
     * @return array Lista de módulos com nomes traduzidos
     */
    public function listarModulos(): array
    {
        $modulos = $this->qb
            ->table('feature_request_modules')
            ->withoutChave()
            ->where('ativo', '=', 1)
            ->orderBy('ordem', 'ASC')
            ->get();

        // Traduzir nomes usando i18n
        foreach ($modulos as &$modulo) {
            if (!empty($modulo['translation_key'])) {
                $translated = t('modules.feature_requests.' . $modulo['translation_key']);
                // Usar tradução apenas se existir (não retornar a chave literal)
                if ($translated !== 'modules.feature_requests.' . $modulo['translation_key']) {
                    $modulo['nome'] = $translated;
                }
            }
        }

        return $modulos;
    }

    /**
     * Obtém estatísticas dos pedidos
     *
     * @param string|null $chave Filtrar por tenant (opcional)
     * @return array Estatísticas
     */
    public function estatisticas(?string $chave = null): array
    {
        $sql = "
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
                SUM(CASE WHEN status = 'em_analise' THEN 1 ELSE 0 END) as em_analise,
                SUM(CASE WHEN status = 'em_desenvolvimento' THEN 1 ELSE 0 END) as em_desenvolvimento,
                SUM(CASE WHEN status = 'concluido' THEN 1 ELSE 0 END) as concluidos,
                SUM(CASE WHEN status = 'recusado' THEN 1 ELSE 0 END) as recusados,
                SUM(CASE WHEN status = 'aguardando_info' THEN 1 ELSE 0 END) as aguardando_info
            FROM feature_requests
        ";

        if ($chave) {
            $chaveEscapada = $this->getMysqli()->real_escape_string($chave);
            $sql .= " WHERE chave = '{$chaveEscapada}'";
        }

        $result = $this->getMysqli()->query($sql);
        return $result ? $result->fetch_assoc() : [];
    }
}
