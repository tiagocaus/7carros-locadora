<?php

namespace App\Models;

class SiteConfig extends Model
{
    /**
     * Busca configuracao do site pelo chave do tenant (sessao)
     */
    public function buscarPorChave(): ?array
    {
        return $this->qb
            ->table('site_config')
            ->select(['*'])
            ->first();
    }

    /**
     * Retorna o status do site para o tenant da sessao
     */
    public static function getStatus(): string
    {
        $model = new self();
        $config = $model->buscarPorChave();
        return $config['status'] ?? 'inativo';
    }

    /**
     * Cria ou atualiza a configuracao do site
     */
    public function criarOuAtualizar(array $dados): int
    {
        $existing = $this->buscarPorChave();

        if ($existing) {
            $dados['updated_at'] = date('Y-m-d H:i:s');
            return $this->qb
                ->table('site_config')
                ->where('id', '=', $existing['id'])
                ->update($dados);
        }

        $dados['chave'] = $_SESSION['chave'];
        return $this->qb
            ->table('site_config')
            ->insert($dados);
    }

    /**
     * Atualiza campos especificos
     */
    public function atualizar(array $dados): int
    {
        $dados['updated_at'] = date('Y-m-d H:i:s');
        return $this->qb
            ->table('site_config')
            ->update($dados);
    }

    /**
     * Atualiza o status do site
     */
    public function atualizarStatus(string $status): int
    {
        return $this->atualizar(['status' => $status]);
    }

    /**
     * Atualiza versao e timestamp do ultimo deploy
     */
    public function registrarDeploy(string $versao): int
    {
        return $this->atualizar([
            'versao'           => $versao,
            'ultimo_deploy_em' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Busca config por chave explicita (cross-tenant, sem depender da sessao)
     * Usado pela API publica para autenticacao por token
     */
    public function buscarPorChaveExplicita(string $chave): ?array
    {
        return $this->qb
            ->table('site_config')
            ->select(['*'])
            ->withoutChave()
            ->where('chave', '=', $chave)
            ->first();
    }

    /**
     * Retorna QueryBuilder apontando para outra tabela (reutiliza conexao singleton)
     * Permite queries em tabelas auxiliares sem criar novas conexoes
     */
    public function queryTable(string $table): \App\Classes\QueryBuilder
    {
        return $this->qb->table($table);
    }
}
