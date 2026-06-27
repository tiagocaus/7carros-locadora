<?php

namespace App\Models;

/**
 * Model Changelog
 *
 * Gerencia operações CRUD na tabela changelog.
 * Esta tabela é GLOBAL (não usa chave de tenant).
 */
class Changelog extends Model
{
    /**
     * Labels dos tipos de changelog
     */
    public const TIPOS = [
        'N' => 'Novo',
        'A' => 'Aprimorado',
        'C' => 'Correção',
    ];

    /**
     * Cores CSS dos tipos (Tailwind classes)
     */
    public const TIPO_CORES = [
        'N' => 'bg-green-100 text-green-800',
        'A' => 'bg-blue-100 text-blue-800',
        'C' => 'bg-orange-100 text-orange-800',
    ];

    /**
     * Retorna a versão mais recente do sistema
     *
     * @return string Versão mais recente ou '0.0.0' se não houver
     */
    public static function getUltimaVersao(): string
    {
        $instance = new self();
        $result = $instance->qb
            ->table('changelog')
            ->selectRaw('versao')
            ->withoutChave()
            ->orderByDesc('versao')
            ->limit(1)
            ->first();

        return $result['versao'] ?? '0.0.0';
    }

    /**
     * Lista todos os changelogs agrupados por versão
     *
     * @return array Lista de changelogs agrupados por versão
     */
    public function listarAgrupado(): array
    {
        $changelogs = $this->qb
            ->table('changelog')
            ->select(['id', 'versao', 'tipo', 'data', 'mensagem'])
            ->withoutChave()
            ->orderByDesc('versao')
            ->orderByDesc('data')
            ->orderByDesc('id')
            ->get();

        // Agrupar por versão
        $agrupado = [];
        foreach ($changelogs as $item) {
            $versao = $item['versao'];
            if (!isset($agrupado[$versao])) {
                $agrupado[$versao] = [];
            }
            $agrupado[$versao][] = $item;
        }

        return $agrupado;
    }

    /**
     * Busca um changelog por ID
     *
     * @param int $id ID do changelog
     * @return array|null Dados do changelog ou null se não encontrado
     */
    public function buscarPorId(int $id): ?array
    {
        $result = $this->qb
            ->table('changelog')
            ->select(['id', 'versao', 'tipo', 'data', 'mensagem'])
            ->withoutChave()
            ->where('id', '=', $id)
            ->first();

        return $result ?: null;
    }

    /**
     * Cria um novo changelog
     *
     * @param array $dados Dados do changelog
     * @return int|false ID do registro criado ou false em caso de erro
     */
    public function criar(array $dados): int|false
    {
        return $this->qb
            ->table('changelog')
            ->withoutChave()
            ->insert([
                'versao' => $dados['versao'],
                'tipo' => $dados['tipo'],
                'data' => $dados['data'],
                'mensagem' => $dados['mensagem'],
            ]);
    }

    /**
     * Atualiza um changelog existente
     *
     * @param int $id ID do changelog
     * @param array $dados Dados a atualizar
     * @return bool True se atualizado com sucesso
     */
    public function atualizar(int $id, array $dados): bool
    {
        $affected = $this->qb
            ->table('changelog')
            ->withoutChave()
            ->where('id', '=', $id)
            ->update([
                'versao' => $dados['versao'],
                'tipo' => $dados['tipo'],
                'data' => $dados['data'],
                'mensagem' => $dados['mensagem'],
            ]);

        return $affected > 0;
    }

    /**
     * Exclui um changelog
     *
     * @param int $id ID do changelog
     * @return bool True se excluído com sucesso
     */
    public function excluir(int $id): bool
    {
        $affected = $this->qb
            ->table('changelog')
            ->withoutChave()
            ->where('id', '=', $id)
            ->delete();

        return $affected > 0;
    }

    /**
     * Valida os dados do changelog
     *
     * @param array $dados Dados a validar
     * @return array Erros de validação (vazio se válido)
     */
    public function validar(array $dados): array
    {
        $erros = [];

        if (empty($dados['versao'])) {
            $erros['versao'] = 'A versão é obrigatória.';
        } elseif (strlen($dados['versao']) > 20) {
            $erros['versao'] = 'A versão deve ter no máximo 20 caracteres.';
        }

        if (empty($dados['tipo'])) {
            $erros['tipo'] = 'O tipo é obrigatório.';
        } elseif (!array_key_exists($dados['tipo'], self::TIPOS)) {
            $erros['tipo'] = 'Tipo inválido.';
        }

        if (empty($dados['data'])) {
            $erros['data'] = 'A data é obrigatória.';
        }

        if (empty($dados['mensagem'])) {
            $erros['mensagem'] = 'A mensagem é obrigatória.';
        } elseif (strlen($dados['mensagem']) > 255) {
            $erros['mensagem'] = 'A mensagem deve ter no máximo 255 caracteres.';
        }

        return $erros;
    }

    /**
     * Lista as últimas versões do changelog para exibição pública (tela de login)
     *
     * @param int $limite Número máximo de versões a retornar
     * @param int $offset Número de versões a pular (para paginação)
     * @return array Lista de versões com itens planos (tipo, mensagem, data)
     */
    public function listarUltimasVersoes(int $limite = 50, int $offset = 0): array
    {
        // Buscar versões distintas ordenadas
        $versoes = $this->qb
            ->table('changelog')
            ->selectRaw('DISTINCT versao, MAX(data) as data_versao')
            ->withoutChave()
            ->groupBy('versao')
            ->orderByDesc('versao')
            ->offset($offset)
            ->limit($limite)
            ->get();

        if (empty($versoes)) {
            return [];
        }

        $versoesLista = array_column($versoes, 'versao');

        // Buscar todos os itens das versões selecionadas
        $changelogs = $this->qb
            ->table('changelog')
            ->select(['id', 'versao', 'tipo', 'data', 'mensagem'])
            ->withoutChave()
            ->whereIn('versao', $versoesLista)
            ->orderByDesc('versao')
            ->orderByDesc('data')
            ->orderByDesc('id')
            ->get();

        $resultado = [];
        $versoesMap = [];

        foreach ($versoes as $v) {
            $versoesMap[$v['versao']] = $v['data_versao'];
        }

        foreach ($changelogs as $item) {
            $versao = $item['versao'];

            if (!isset($resultado[$versao])) {
                $resultado[$versao] = [
                    'versao' => $versao,
                    'data' => $versoesMap[$versao] ?? $item['data'],
                    'itens' => [],
                ];
            }

            $resultado[$versao]['itens'][] = [
                'tipo' => $item['tipo'],
                'mensagem' => $item['mensagem'],
                'data' => $item['data'],
            ];
        }

        return array_values($resultado);
    }
}
