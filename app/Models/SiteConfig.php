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
            $dados['updated_at'] = now();
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
        $dados['updated_at'] = now();
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
            'ultimo_deploy_em' => now(),
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
            ->withChave($chave)
            ->first();
    }

    /**
     * Lista configuracoes para a publicacao administrativa do template.
     *
     * Sem filtro, esta e uma consulta cross-tenant exclusiva do comando CLI.
     * Com chave explicita, o isolamento normal do QueryBuilder e preservado.
     */
    public function listarParaAtualizacaoEmLote(?string $chave = null): array
    {
        $query = $this->qb
            ->table('site_config', 'sc')
            ->select([
                'sc.chave',
                'sc.status',
                'sc.versao',
                "(sc.api_token IS NOT NULL AND sc.api_token <> '') AS tem_api_token",
                'cr.id AS credencial_id',
                'cr.usuario AS ftp_usuario',
            ])
            ->leftJoinRaw('site_credenciais', 'cr', 'cr.chave = sc.chave')
            ->orderBy('sc.chave', 'ASC');

        if ($chave === null) {
            return $query
                ->withoutChave()
                ->get();
        }

        return $query
            ->withChave($chave)
            ->get();
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
