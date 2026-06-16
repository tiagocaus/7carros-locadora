<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\DetectsCrossTenant;

/**
 * Model PlanoDeContas
 *
 * Gerencia operações CRUD na tabela planos_de_contas
 */
class PlanoDeContas extends Model
{
    use Auditable;
    use DetectsCrossTenant;

    /**
     * Retorna o nome da entidade para auditoria
     */
    protected function getEntidadeAuditoria(): string
    {
        return 'o plano de contas';
    }

    /**
     * Retorna o campo identificador para auditoria
     */
    protected function getCampoIdentificador(): string
    {
        return 'hierarquia';
    }

    /**
     * Obtém a descrição traduzida de um plano de contas
     *
     * @param array $plano Dados do plano de contas
     * @param string|null $locale Locale desejado (usa current_locale() se null)
     * @return string Descrição no idioma solicitado
     */
    public static function getDescricao(array $plano, ?string $locale = null): string
    {
        $locale = $locale ?? current_locale();

        if (!empty($plano['descricao_i18n'])) {
            $translations = is_array($plano['descricao_i18n'])
                ? $plano['descricao_i18n']
                : json_decode($plano['descricao_i18n'], true);

            if (isset($translations[$locale])) {
                return $translations[$locale];
            }

            // Fallback para pt_BR
            if (isset($translations['pt_BR'])) {
                return $translations['pt_BR'];
            }
        }

        return '';
    }

    /**
     * Lista todos os planos de contas do tenant atual
     *
     * @param string|null $where Condição WHERE adicional
     * @param array $params Parâmetros para prepared statement
     * @param string|null $orderBy Ordenação
     * @return array Lista de planos de contas
     */
    public function listar(?string $where = null, array $params = [], ?string $orderBy = 'hierarquia ASC'): array
    {
        $query = $this->qb
            ->table('planos_de_contas')
            ->withGlobals()
            ->select(['id', 'hierarquia', 'descricao_i18n', 'tipo']);

        if (!empty($where)) {
            $query->whereRaw($where, $params);
        }

        if (!empty($orderBy)) {
            $query->orderByRaw($orderBy);
        }

        return $query->get();
    }

    /**
     * Lista planos de contas com paginação
     *
     * @param int $page Página atual
     * @param int $perPage Itens por página
     * @param string|null $search Termo de busca
     * @param string|null $tipo Filtro por tipo (A, P, D, R)
     * @return array Lista de planos de contas
     */
    public function listarPaginado(int $page, int $perPage, ?string $search = null, ?string $tipo = null): array
    {
        $offset = ($page - 1) * $perPage;
        $locale = current_locale();

        $query = $this->qb
            ->table('planos_de_contas')
            ->withGlobals()
            ->select(['id', 'hierarquia', 'descricao_i18n', 'tipo', 'chave']);

        // Filtro por busca (busca no JSON de traduções)
        if (!empty($search)) {
            $searchTerm = "%{$search}%";
            $query->whereNested(function ($q) use ($searchTerm, $locale) {
                $q->where('hierarquia', 'LIKE', $searchTerm)
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(descricao_i18n, '$.{$locale}')) LIKE ?", [$searchTerm])
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(descricao_i18n, '$.pt_BR')) LIKE ?", [$searchTerm]);
            });
        }

        // Filtro por tipo
        if (!empty($tipo)) {
            $query->where('tipo', '=', $tipo);
        }

        return $query
            ->orderByRaw('hierarquia ASC')
            ->limit($perPage)
            ->offset($offset)
            ->get();
    }

    /**
     * Conta total de planos de contas
     *
     * @param string|null $search Termo de busca
     * @param string|null $tipo Filtro por tipo
     * @return int Total de registros
     */
    public function contar(?string $search = null, ?string $tipo = null): int
    {
        $locale = current_locale();

        $query = $this->qb
            ->table('planos_de_contas')
            ->withGlobals()
            ->select(['COUNT(*) as total']);

        if (!empty($search)) {
            $searchTerm = "%{$search}%";
            $query->whereNested(function ($q) use ($searchTerm, $locale) {
                $q->where('hierarquia', 'LIKE', $searchTerm)
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(descricao_i18n, '$.{$locale}')) LIKE ?", [$searchTerm])
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(descricao_i18n, '$.pt_BR')) LIKE ?", [$searchTerm]);
            });
        }

        if (!empty($tipo)) {
            $query->where('tipo', '=', $tipo);
        }

        $result = $query->first();
        return (int) ($result['total'] ?? 0);
    }

    /**
     * Busca um plano de contas por ID
     *
     * @param int $id ID do plano
     * @return array|null Dados do plano ou null se não encontrado
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->qb
            ->table('planos_de_contas')
            ->withGlobals()
            ->select(['id', 'hierarquia', 'descricao_i18n', 'tipo', 'chave'])
            ->where('id', '=', $id)
            ->first();
    }

    /**
     * Busca um plano de contas por hierarquia
     *
     * @param string $hierarquia Código hierárquico
     * @return array|null Dados do plano ou null se não encontrado
     */
    public function buscarPorHierarquia(string $hierarquia): ?array
    {
        return $this->qb
            ->table('planos_de_contas')
            ->withGlobals()
            ->select(['id', 'hierarquia', 'descricao_i18n', 'tipo', 'chave'])
            ->where('hierarquia', '=', $hierarquia)
            ->first();
    }

    /**
     * Busca planos de contas por termo
     *
     * @param string $termo Termo de busca
     * @param string|null $tipo Filtro por tipo
     * @param int $limit Limite de resultados
     * @return array Lista de planos encontrados
     */
    public function buscar(string $termo, ?string $tipo = null, int $limit = 20): array
    {
        $searchTerm = "%{$termo}%";
        $locale = current_locale();

        $query = $this->qb
            ->table('planos_de_contas')
            ->withGlobals()
            ->select(['id', 'hierarquia', 'descricao_i18n', 'tipo'])
            ->whereNested(function ($q) use ($searchTerm, $locale) {
                $q->where('hierarquia', 'LIKE', $searchTerm)
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(descricao_i18n, '$.{$locale}')) LIKE ?", [$searchTerm])
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(descricao_i18n, '$.pt_BR')) LIKE ?", [$searchTerm]);
            });

        if (!empty($tipo)) {
            $query->where('tipo', '=', $tipo);
        }

        return $query
            ->orderByRaw('hierarquia ASC')
            ->limit($limit)
            ->get();
    }

    /**
     * Cria um novo plano de contas
     *
     * @param array $dados Dados do plano
     * @return int ID do plano criado
     */
    public function criar(array $dados): int
    {
        return $this->qb
            ->table('planos_de_contas')
            ->insert($dados);
    }

    /**
     * Atualiza um plano de contas existente
     *
     * @param int $id ID do plano
     * @param array $dados Dados para atualizar
     * @return int Número de linhas afetadas
     */
    public function atualizar(int $id, array $dados): int
    {
        return $this->qb
            ->table('planos_de_contas')
            ->where('id', '=', $id)
            ->update($dados);
    }

    /**
     * Exclui um plano de contas
     *
     * @param int $id ID do plano
     * @return int Número de linhas afetadas
     */
    public function deletar(int $id): int
    {
        return $this->qb
            ->table('planos_de_contas')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Verifica se a hierarquia já existe (para validação)
     *
     * @param string $hierarquia Código hierárquico
     * @param int|null $excludeId ID a excluir da verificação (para edição)
     * @return bool True se já existe
     */
    public function hierarquiaExiste(string $hierarquia, ?int $excludeId = null): bool
    {
        $query = $this->qb
            ->table('planos_de_contas')
            ->withGlobals()
            ->select(['id'])
            ->where('hierarquia', '=', $hierarquia);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->first() !== null;
    }

    /**
     * Verifica se o plano possui lançamentos financeiros vinculados
     *
     * @param int $id ID do plano
     * @return bool True se possui vínculos
     */
    public function possuiLancamentos(int $id): bool
    {
        $result = $this->qb
            ->table('financeiro')
            ->select(['COUNT(*) as total'])
            ->where('id_plano_de_conta', '=', $id)
            ->first();

        return (int) ($result['total'] ?? 0) > 0;
    }

    /**
     * Lista tipos de plano de contas
     *
     * @return array Lista de tipos com labels traduzidos
     */
    public static function getTipos(): array
    {
        return [
            'A' => t('modules.planos_contas.fields.tipo_ativo'),
            'P' => t('modules.planos_contas.fields.tipo_passivo'),
            'D' => t('modules.planos_contas.fields.tipo_despesa'),
            'R' => t('modules.planos_contas.fields.tipo_receita'),
        ];
    }

    /**
     * Retorna o label do tipo
     *
     * @param string $tipo Código do tipo
     * @return string Label traduzido
     */
    public static function getTipoLabel(string $tipo): string
    {
        $tipos = self::getTipos();
        return $tipos[$tipo] ?? $tipo;
    }

    /**
     * Lista planos de contas por tipo (para select de conta pai)
     *
     * @param string $tipo Tipo de conta (A, P, D, R)
     * @param string|null $search Termo de busca opcional
     * @param int $limit Limite de resultados
     * @return array Lista de planos ordenados por hierarquia
     */
    public function listarPorTipo(string $tipo, ?string $search = null, int $limit = 50): array
    {
        $locale = current_locale();

        $query = $this->qb
            ->table('planos_de_contas')
            ->withGlobals()
            ->select(['id', 'hierarquia', 'descricao_i18n', 'tipo'])
            ->where('tipo', '=', $tipo);

        // Filtro por busca (busca no código e descrição)
        if (!empty($search)) {
            $searchTerm = "%{$search}%";
            $query->whereNested(function ($q) use ($searchTerm, $locale) {
                $q->where('hierarquia', 'LIKE', $searchTerm)
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(descricao_i18n, '$.{$locale}')) LIKE ?", [$searchTerm])
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(descricao_i18n, '$.pt_BR')) LIKE ?", [$searchTerm]);
            });
        }

        return $query
            ->orderByRaw('hierarquia ASC')
            ->limit($limit)
            ->get();
    }

    /**
     * Sugere o próximo código disponível
     *
     * @param string|null $hierarquiaPai Hierarquia do pai (null para conta raiz)
     * @param string|null $tipo Tipo da conta (necessário para conta raiz)
     * @return string Próximo código sugerido
     */
    public function sugerirProximoCodigo(?string $hierarquiaPai = null, ?string $tipo = null): string
    {
        if ($hierarquiaPai === null || $hierarquiaPai === '') {
            // Conta raiz - buscar próximo número inteiro do tipo
            return $this->sugerirProximoCodigoRaiz($tipo);
        }

        // Conta filha - buscar próximo código filho
        return $this->sugerirProximoCodigoFilho($hierarquiaPai);
    }

    /**
     * Sugere próximo código para conta raiz
     *
     * @param string|null $tipo Tipo da conta
     * @return string Próximo código
     */
    private function sugerirProximoCodigoRaiz(?string $tipo): string
    {
        // Buscar todas as contas raiz (sem ponto na hierarquia)
        $query = $this->qb
            ->table('planos_de_contas')
            ->withGlobals()
            ->select(['hierarquia'])
            ->whereRaw("hierarquia NOT LIKE '%.%'");

        if ($tipo) {
            $query->where('tipo', '=', $tipo);
        }

        $contas = $query->orderByRaw('CAST(hierarquia AS UNSIGNED) DESC')->limit(1)->get();

        if (empty($contas)) {
            // Primeira conta do tipo - usar padrão baseado no tipo
            $prefixos = ['A' => '1', 'P' => '2', 'D' => '3', 'R' => '4'];
            return $prefixos[$tipo] ?? '1';
        }

        $ultimoCodigo = (int) $contas[0]['hierarquia'];
        return (string) ($ultimoCodigo + 1);
    }

    /**
     * Sugere próximo código para conta filha
     *
     * @param string $hierarquiaPai Hierarquia do pai
     * @return string Próximo código filho
     */
    private function sugerirProximoCodigoFilho(string $hierarquiaPai): string
    {
        // Contar quantos pontos tem o pai para determinar o nível
        $nivelPai = substr_count($hierarquiaPai, '.');

        // Buscar filhos diretos do pai
        $pattern = $hierarquiaPai . '.%';
        $filhos = $this->qb
            ->table('planos_de_contas')
            ->withGlobals()
            ->select(['hierarquia'])
            ->whereRaw('hierarquia LIKE ?', [$pattern])
            ->get();

        // Filtrar apenas filhos diretos (mesmo nível)
        $filhosDirectos = [];
        foreach ($filhos as $filho) {
            $nivelFilho = substr_count($filho['hierarquia'], '.');
            // Filho direto tem exatamente 1 ponto a mais que o pai
            if ($nivelFilho === $nivelPai + 1) {
                $filhosDirectos[] = $filho['hierarquia'];
            }
        }

        if (empty($filhosDirectos)) {
            // Primeiro filho - usar .01 ou .1 dependendo do padrão
            return $hierarquiaPai . '.01';
        }

        // Encontrar o maior número filho
        $maiorNumero = 0;
        foreach ($filhosDirectos as $hierarquia) {
            // Extrair o último segmento
            $partes = explode('.', $hierarquia);
            $ultimaParte = end($partes);
            $numero = (int) $ultimaParte;
            if ($numero > $maiorNumero) {
                $maiorNumero = $numero;
            }
        }

        // Próximo número com padding
        $proximoNumero = $maiorNumero + 1;

        // Determinar o padding baseado no padrão existente
        $ultimoFilho = end($filhosDirectos);
        $partes = explode('.', $ultimoFilho);
        $ultimaParte = end($partes);
        $padding = strlen($ultimaParte);

        return $hierarquiaPai . '.' . str_pad($proximoNumero, $padding, '0', STR_PAD_LEFT);
    }
}
