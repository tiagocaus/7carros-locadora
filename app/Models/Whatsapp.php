<?php

namespace App\Models;

/**
 * Model Whatsapp
 *
 * Gerencia conexoes WhatsApp atraves de um provedor externo.
 * Relacionamento N:N com filiais via tabela whatsapp_filiais.
 */
class Whatsapp extends Model
{
    /**
     * Lista todas as conexoes do tenant
     *
     * @return array Lista de conexoes
     */
    public function listar(): array
    {
        return $this->qb
            ->table('whatsapp')
            ->orderBy('instanceName', 'ASC')
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
            ->table('whatsapp');

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('instanceName', 'LIKE', $searchTerm)
                  ->orWhere('remoteJid', 'LIKE', $searchTerm);
            });
        }

        return $query
            ->orderBy('instanceName', 'ASC')
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
            ->table('whatsapp');

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('instanceName', 'LIKE', $searchTerm)
                  ->orWhere('remoteJid', 'LIKE', $searchTerm);
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
            ->table('whatsapp')
            ->where('id', '=', $id)
            ->first();
    }

    /**
     * Busca conexao por instanceName
     *
     * @param string $instanceName Nome da instancia
     * @return array|null Dados ou null
     */
    public function buscarPorInstanceName(string $instanceName): ?array
    {
        return $this->qb
            ->table('whatsapp')
            ->where('instanceName', '=', $instanceName)
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
            ->table('whatsapp', 'w')
            ->select(['w.*'])
            ->innerJoin('whatsapp_filiais', 'wf', 'wf.id_whatsapp', '=', 'w.id')
            ->where('wf.id_matriz_filial', '=', $idMatrizFilial)
            ->orderBy('w.instanceName', 'ASC')
            ->get();
    }

    /**
     * Busca conexao conectada para uma filial
     *
     * @param int $idMatrizFilial ID da filial
     * @return array|null Conexao conectada ou null
     */
    public function buscarConectadaPorFilial(int $idMatrizFilial): ?array
    {
        return $this->qb
            ->table('whatsapp', 'w')
            ->select(['w.*'])
            ->innerJoin('whatsapp_filiais', 'wf', 'wf.id_whatsapp', '=', 'w.id')
            ->where('wf.id_matriz_filial', '=', $idMatrizFilial)
            ->where('w.status', '=', 'connected')
            ->first();
    }

    /**
     * Obtem filiais vinculadas a uma conexao
     *
     * @param int $id ID da conexao WhatsApp
     * @return array Lista de filiais vinculadas
     */
    public function getFiliais(int $id): array
    {
        return $this->qb
            ->table('whatsapp_filiais', 'wf')
            ->withoutChave() // Tabela pivot nao precisa de filtro de chave aqui
            ->select(['mf.id', 'mf.razao_social', 'mf.nome_fantasia'])
            ->innerJoin('matrizes_filiais', 'mf', 'mf.id', '=', 'wf.id_matriz_filial')
            ->where('wf.id_whatsapp', '=', $id)
            ->orderBy('mf.razao_social', 'ASC')
            ->get();
    }

    /**
     * Retorna IDs das filiais ja vinculadas a qualquer conexao WhatsApp do tenant
     *
     * @return array Lista de IDs de filiais ocupadas
     */
    public function getFiliaisOcupadas(): array
    {
        $resultado = $this->qb
            ->table('whatsapp_filiais')
            ->select(['id_matriz_filial'])
            ->distinct()
            ->get();

        return array_column($resultado, 'id_matriz_filial');
    }

    /**
     * Sincroniza filiais de uma conexao (delete + insert)
     *
     * @param int $id ID da conexao WhatsApp
     * @param array $filialIds Array de IDs de filiais
     * @param string $chave Chave do tenant
     * @return void
     */
    public function sincronizarFiliais(int $id, array $filialIds, string $chave): void
    {
        // Remover vinculos existentes
        $this->qb
            ->table('whatsapp_filiais')
            ->where('id_whatsapp', '=', $id)
            ->delete();

        // Inserir novos vinculos
        foreach ($filialIds as $filialId) {
            $this->qb
                ->table('whatsapp_filiais')
                ->insert([
                    'id_whatsapp' => $id,
                    'id_matriz_filial' => (int) $filialId,
                    'chave' => $chave,
                ]);
        }
    }

    /**
     * Gera instanceName unico no padrao locadora_{uuid}
     *
     * @return string Nome da instancia gerado
     */
    public static function gerarInstanceName(): string
    {
        // Gera UUID v4 simplificado (8 caracteres)
        $uuid = substr(bin2hex(random_bytes(4)), 0, 8);
        return 'locadora_' . $uuid;
    }

    /**
     * Cria uma nova conexao
     *
     * @param array $dados Dados da conexao
     * @return int ID criado
     */
    public function criar(array $dados): int
    {
        return $this->qb
            ->table('whatsapp')
            ->insert([
                'chave' => $dados['chave'],
                'instanceName' => $dados['instanceName'],
                'instanceId' => $dados['instanceId'] ?? '',
                'remoteJid' => $dados['remoteJid'] ?? null,
                'status' => $dados['status'] ?? 'disconnected',
                'connected_at' => $dados['connected_at'] ?? null,
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
            throw new \InvalidArgumentException('Conexao WhatsApp nao encontrada');
        }

        $dadosUpdate = [];

        if (isset($dados['instanceName'])) {
            $dadosUpdate['instanceName'] = $dados['instanceName'];
        }
        if (isset($dados['instanceId'])) {
            $dadosUpdate['instanceId'] = $dados['instanceId'];
        }
        if (array_key_exists('remoteJid', $dados)) {
            $dadosUpdate['remoteJid'] = $dados['remoteJid'] ?: null;
        }
        if (isset($dados['status'])) {
            $dadosUpdate['status'] = $dados['status'];
        }
        if (array_key_exists('connected_at', $dados)) {
            $dadosUpdate['connected_at'] = $dados['connected_at'] ?: null;
        }

        if (empty($dadosUpdate)) {
            return 0;
        }

        return $this->qb
            ->table('whatsapp')
            ->where('id', '=', $id)
            ->update($dadosUpdate);
    }

    /**
     * Atualiza apenas o status da conexao
     *
     * @param int $id ID da conexao
     * @param string $status Novo status
     * @param string|null $remoteJid Numero conectado (opcional)
     * @return int Linhas afetadas
     */
    public function atualizarStatus(int $id, string $status, ?string $remoteJid = null): int
    {
        $dados = ['status' => $status];

        if ($status === 'connected') {
            $dados['connected_at'] = date('Y-m-d H:i:s');
            if ($remoteJid) {
                $dados['remoteJid'] = $remoteJid;
            }
        } elseif ($status === 'disconnected') {
            $dados['connected_at'] = null;
        }

        return $this->qb
            ->table('whatsapp')
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
            throw new \InvalidArgumentException('Conexao WhatsApp nao encontrada');
        }

        return $this->qb
            ->table('whatsapp')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Verifica se instanceName ja existe
     *
     * @param string $instanceName Nome da instancia
     * @param int|null $excludeId ID a excluir da verificacao
     * @return bool
     */
    public function instanceNameExiste(string $instanceName, ?int $excludeId = null): bool
    {
        $query = $this->qb
            ->table('whatsapp')
            ->withoutChave()
            ->where('instanceName', '=', $instanceName);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->count() > 0;
    }
}
