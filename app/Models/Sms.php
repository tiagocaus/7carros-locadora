<?php

namespace App\Models;

/**
 * Model SMS
 *
 * Gerencia conexoes SMS com provedores externos (ClickSend, etc).
 * Relacionamento N:N com filiais via tabela sms_filiais.
 */
class Sms extends Model
{
    /**
     * Lista todas as conexoes SMS do tenant
     *
     * @return array Lista de conexoes
     */
    public function listar(): array
    {
        return $this->qb
            ->table('sms')
            ->orderBy('sender_id', 'ASC')
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
            ->table('sms');

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('sender_id', 'LIKE', $searchTerm)
                  ->orWhere('username', 'LIKE', $searchTerm);
            });
        }

        return $query
            ->orderBy('sender_id', 'ASC')
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
            ->table('sms');

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('sender_id', 'LIKE', $searchTerm)
                  ->orWhere('username', 'LIKE', $searchTerm);
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
            ->table('sms')
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
            ->table('sms', 's')
            ->select(['s.*'])
            ->innerJoin('sms_filiais', 'sf', 'sf.id_sms', '=', 's.id')
            ->where('sf.id_matriz_filial', '=', $idMatrizFilial)
            ->orderBy('s.sender_id', 'ASC')
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
            ->table('sms', 's')
            ->select(['s.*'])
            ->innerJoin('sms_filiais', 'sf', 'sf.id_sms', '=', 's.id')
            ->where('sf.id_matriz_filial', '=', $idMatrizFilial)
            ->where('s.status', '=', 'validated')
            ->first();
    }

    /**
     * Obtem filiais vinculadas a uma conexao
     *
     * @param int $id ID da conexao SMS
     * @return array Lista de filiais vinculadas
     */
    public function getFiliais(int $id): array
    {
        return $this->qb
            ->table('sms_filiais', 'sf')
            ->withoutChave() // Tabela pivot nao precisa de filtro de chave aqui
            ->select(['mf.id', 'mf.razao_social', 'mf.nome_fantasia'])
            ->innerJoin('matrizes_filiais', 'mf', 'mf.id', '=', 'sf.id_matriz_filial')
            ->where('sf.id_sms', '=', $id)
            ->orderBy('mf.razao_social', 'ASC')
            ->get();
    }

    /**
     * Retorna IDs das filiais ja vinculadas a qualquer conexao SMS do tenant
     *
     * @return array Lista de IDs de filiais ocupadas
     */
    public function getFiliaisOcupadas(): array
    {
        $resultado = $this->qb
            ->table('sms_filiais')
            ->select(['id_matriz_filial'])
            ->distinct()
            ->get();

        return array_column($resultado, 'id_matriz_filial');
    }

    /**
     * Sincroniza filiais de uma conexao (delete + insert)
     *
     * @param int $id ID da conexao SMS
     * @param array $filialIds Array de IDs de filiais
     * @param string $chave Chave do tenant
     * @return void
     */
    public function sincronizarFiliais(int $id, array $filialIds, string $chave): void
    {
        // Remover vinculos existentes
        $this->qb
            ->table('sms_filiais')
            ->where('id_sms', '=', $id)
            ->delete();

        // Inserir novos vinculos
        foreach ($filialIds as $filialId) {
            $this->qb
                ->table('sms_filiais')
                ->insert([
                    'id_sms' => $id,
                    'id_matriz_filial' => (int) $filialId,
                    'chave' => $chave,
                ]);
        }
    }

    /**
     * Cria uma nova conexao SMS
     *
     * @param array $dados Dados da conexao
     * @return int ID criado
     */
    public function criar(array $dados): int
    {
        return $this->qb
            ->table('sms')
            ->insert([
                'chave' => $dados['chave'],
                'provider' => $dados['provider'] ?? 'clicksend',
                'sender_id' => $dados['sender_id'],
                'username' => $dados['username'],
                'api_key' => $dados['api_key'], // Ja deve vir criptografado
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
            throw new \InvalidArgumentException('Conexao SMS nao encontrada');
        }

        $dadosUpdate = [];

        if (isset($dados['provider'])) {
            $dadosUpdate['provider'] = $dados['provider'];
        }
        if (isset($dados['sender_id'])) {
            $dadosUpdate['sender_id'] = $dados['sender_id'];
        }
        if (isset($dados['username'])) {
            $dadosUpdate['username'] = $dados['username'];
        }
        if (isset($dados['api_key'])) {
            $dadosUpdate['api_key'] = $dados['api_key'];
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
            ->table('sms')
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
            $dados['validated_at'] = date('Y-m-d H:i:s');
            $dados['last_error'] = null;
        } elseif ($status === 'invalid') {
            $dados['last_error'] = $error;
        }

        return $this->qb
            ->table('sms')
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
            throw new \InvalidArgumentException('Conexao SMS nao encontrada');
        }

        return $this->qb
            ->table('sms')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Verifica se sender_id ja existe no tenant
     *
     * @param string $senderId Sender ID
     * @param int|null $excludeId ID a excluir da verificacao
     * @return bool
     */
    public function senderIdExiste(string $senderId, ?int $excludeId = null): bool
    {
        $query = $this->qb
            ->table('sms')
            ->where('sender_id', '=', $senderId);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->count() > 0;
    }
}
