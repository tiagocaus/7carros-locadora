<?php

namespace App\Models;

/**
 * Model SMTP
 *
 * Gerencia conexoes SMTP com provedores de email (Gmail, Outlook, SendGrid, etc).
 * Relacionamento N:N com filiais via tabela smtp_filiais.
 */
class Smtp extends Model
{
    /**
     * Lista todas as conexoes SMTP do tenant
     *
     * @return array Lista de conexoes
     */
    public function listar(): array
    {
        return $this->qb
            ->table('smtp')
            ->orderBy('nome', 'ASC')
            ->get();
    }

    /**
     * Lista conexoes paginado
     *
     * @param int $page Pagina atual
     * @param int $perPage Registros por pagina
     * @param string $search Termo de busca (opcional)
     * @return array Lista de conexoes
     */
    public function listarPaginado(int $page, int $perPage, string $search = ''): array
    {
        $query = $this->qb
            ->table('smtp');

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('nome', 'LIKE', $searchTerm)
                  ->orWhere('from_email', 'LIKE', $searchTerm)
                  ->orWhere('from_name', 'LIKE', $searchTerm);
            });
        }

        return $query
            ->orderBy('nome', 'ASC')
            ->paginate($page, $perPage)
            ->get();
    }

    /**
     * Conta total de conexoes do tenant
     *
     * @param string $search Termo de busca (opcional)
     * @return int Total de registros
     */
    public function contar(string $search = ''): int
    {
        $query = $this->qb
            ->table('smtp');

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('nome', 'LIKE', $searchTerm)
                  ->orWhere('from_email', 'LIKE', $searchTerm)
                  ->orWhere('from_name', 'LIKE', $searchTerm);
            });
        }

        return $query->count();
    }

    /**
     * Busca uma conexao por ID
     *
     * @param int $id ID da conexao
     * @return array|null Dados ou null
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->qb
            ->table('smtp')
            ->where('id', '=', $id)
            ->first();
    }

    /**
     * Lista conexoes disponiveis para uma filial especifica
     *
     * @param int $idMatrizFilial ID da filial
     * @return array Lista de conexoes vinculadas a filial
     */
    public function listarPorFilial(int $idMatrizFilial): array
    {
        return $this->qb
            ->table('smtp', 's')
            ->select(['s.*'])
            ->innerJoin('smtp_filiais', 'sf', 'sf.id_smtp', '=', 's.id')
            ->where('sf.id_matriz_filial', '=', $idMatrizFilial)
            ->orderBy('s.nome', 'ASC')
            ->get();
    }

    /**
     * Busca conexao validada para uma filial
     *
     * @param int $idMatrizFilial ID da filial
     * @return array|null Conexao validada ou null
     */
    public function buscarValidadaPorFilial(int $idMatrizFilial): ?array
    {
        return $this->qb
            ->table('smtp', 's')
            ->select(['s.*'])
            ->innerJoin('smtp_filiais', 'sf', 'sf.id_smtp', '=', 's.id')
            ->where('sf.id_matriz_filial', '=', $idMatrizFilial)
            ->where('s.status', '=', 'validated')
            ->first();
    }

    /**
     * Obtem filiais vinculadas a uma conexao
     *
     * @param int $id ID da conexao SMTP
     * @return array Lista de filiais vinculadas
     */
    public function getFiliais(int $id): array
    {
        return $this->qb
            ->table('smtp_filiais', 'sf')
            ->withoutChave() // Tabela pivot nao precisa de filtro de chave aqui
            ->select(['mf.id', 'mf.razao_social', 'mf.nome_fantasia'])
            ->innerJoin('matrizes_filiais', 'mf', 'mf.id', '=', 'sf.id_matriz_filial')
            ->where('sf.id_smtp', '=', $id)
            ->orderBy('mf.razao_social', 'ASC')
            ->get();
    }

    /**
     * Retorna IDs das filiais ja vinculadas a qualquer conexao SMTP do tenant
     *
     * @return array Lista de IDs de filiais ocupadas
     */
    public function getFiliaisOcupadas(): array
    {
        $resultado = $this->qb
            ->table('smtp_filiais')
            ->select(['id_matriz_filial'])
            ->distinct()
            ->get();

        return array_column($resultado, 'id_matriz_filial');
    }

    /**
     * Sincroniza filiais de uma conexao (delete + insert)
     *
     * @param int $id ID da conexao SMTP
     * @param array $filialIds Array de IDs de filiais
     * @param string $chave Chave do tenant
     * @return void
     */
    public function sincronizarFiliais(int $id, array $filialIds, string $chave): void
    {
        // Remover vinculos existentes
        $this->qb
            ->table('smtp_filiais')
            ->where('id_smtp', '=', $id)
            ->delete();

        // Inserir novos vinculos
        foreach ($filialIds as $filialId) {
            $this->qb
                ->table('smtp_filiais')
                ->insert([
                    'id_smtp' => $id,
                    'id_matriz_filial' => (int) $filialId,
                    'chave' => $chave,
                ]);
        }
    }

    /**
     * Cria uma nova conexao SMTP
     *
     * @param array $dados Dados da conexao
     * @return int ID criado
     */
    public function criar(array $dados): int
    {
        return $this->qb
            ->table('smtp')
            ->insert([
                'chave' => $dados['chave'],
                'provider' => $dados['provider'] ?? 'smtp_custom',
                'nome' => $dados['nome'],
                'host' => $dados['host'],
                'port' => $dados['port'] ?? 587,
                'encryption' => $dados['encryption'] ?? 'tls',
                'username' => $dados['username'],
                'password' => $dados['password'], // Ja deve vir criptografado
                'from_email' => $dados['from_email'],
                'from_name' => $dados['from_name'],
                'reply_to_email' => $dados['reply_to_email'] ?? null,
                'reply_to_name' => $dados['reply_to_name'] ?? null,
                'daily_limit' => $dados['daily_limit'] ?? null,
                'status' => $dados['status'] ?? 'pending',
                'validated_at' => $dados['validated_at'] ?? null,
                'last_error' => $dados['last_error'] ?? null,
            ]);
    }

    /**
     * Atualiza uma conexao existente
     *
     * @param int $id ID da conexao
     * @param array $dados Dados para atualizar
     * @return int Linhas afetadas
     */
    public function atualizar(int $id, array $dados): int
    {
        $conexao = $this->buscarPorId($id);
        if (!$conexao) {
            throw new \InvalidArgumentException('Conexao SMTP nao encontrada');
        }

        $dadosUpdate = [];

        if (isset($dados['provider'])) {
            $dadosUpdate['provider'] = $dados['provider'];
        }
        if (isset($dados['nome'])) {
            $dadosUpdate['nome'] = $dados['nome'];
        }
        if (isset($dados['host'])) {
            $dadosUpdate['host'] = $dados['host'];
        }
        if (isset($dados['port'])) {
            $dadosUpdate['port'] = $dados['port'];
        }
        if (isset($dados['encryption'])) {
            $dadosUpdate['encryption'] = $dados['encryption'];
        }
        if (isset($dados['username'])) {
            $dadosUpdate['username'] = $dados['username'];
        }
        if (isset($dados['password'])) {
            $dadosUpdate['password'] = $dados['password'];
        }
        if (isset($dados['from_email'])) {
            $dadosUpdate['from_email'] = $dados['from_email'];
        }
        if (isset($dados['from_name'])) {
            $dadosUpdate['from_name'] = $dados['from_name'];
        }
        if (array_key_exists('reply_to_email', $dados)) {
            $dadosUpdate['reply_to_email'] = $dados['reply_to_email'] ?: null;
        }
        if (array_key_exists('reply_to_name', $dados)) {
            $dadosUpdate['reply_to_name'] = $dados['reply_to_name'] ?: null;
        }
        if (array_key_exists('daily_limit', $dados)) {
            $dadosUpdate['daily_limit'] = $dados['daily_limit'] ?: null;
        }
        if (isset($dados['status'])) {
            $dadosUpdate['status'] = $dados['status'];
        }
        if (array_key_exists('validated_at', $dados)) {
            $dadosUpdate['validated_at'] = $dados['validated_at'] ?: null;
        }
        if (array_key_exists('last_error', $dados)) {
            $dadosUpdate['last_error'] = $dados['last_error'] ?: null;
        }

        if (empty($dadosUpdate)) {
            return 0;
        }

        return $this->qb
            ->table('smtp')
            ->where('id', '=', $id)
            ->update($dadosUpdate);
    }

    /**
     * Atualiza status da conexao apos validacao
     *
     * @param int $id ID da conexao
     * @param string $status Novo status (pending, validated, invalid)
     * @param string|null $error Mensagem de erro (se invalid)
     * @return int Linhas afetadas
     */
    public function atualizarStatus(int $id, string $status, ?string $error = null): int
    {
        $dados = ['status' => $status];

        if ($status === 'validated') {
            $dados['validated_at'] = now();
            $dados['last_error'] = null;
        } elseif ($status === 'invalid') {
            $dados['last_error'] = $error;
        }

        return $this->qb
            ->table('smtp')
            ->where('id', '=', $id)
            ->update($dados);
    }

    /**
     * Exclui uma conexao
     *
     * @param int $id ID da conexao
     * @return int Linhas afetadas
     */
    public function excluir(int $id): int
    {
        $conexao = $this->buscarPorId($id);
        if (!$conexao) {
            throw new \InvalidArgumentException('Conexao SMTP nao encontrada');
        }

        return $this->qb
            ->table('smtp')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Verifica se nome ja existe no tenant
     *
     * @param string $nome Nome da conexao
     * @param int|null $excludeId ID a excluir da verificacao
     * @return bool
     */
    public function nomeExiste(string $nome, ?int $excludeId = null): bool
    {
        $query = $this->qb
            ->table('smtp')
            ->where('nome', '=', $nome);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->count() > 0;
    }
}
