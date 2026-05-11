<?php

namespace App\Models;

/**
 * Model ManutencaoPlano
 *
 * Gerencia planos de manutenção preventiva.
 * Cada plano define intervalos em km para diferentes itens de manutenção.
 */
class ManutencaoPlano extends Model
{
    /**
     * Lista todos os planos de manutenção do tenant
     *
     * @param string $chave Chave do tenant
     * @return array Lista de planos
     */
    public function listar(string $chave): array
    {
        return $this->qb
            ->table('manutencoes_plano')
            ->orderBy('nome', 'ASC')
            ->get();
    }

    /**
     * Lista planos do tenant com paginação e busca
     *
     * @param string $chave Chave do tenant
     * @param int $page Página atual
     * @param int $perPage Registros por página
     * @param string $search Termo de busca (opcional)
     * @return array Lista de planos
     */
    public function listarPaginado(string $chave, int $page, int $perPage, string $search = ''): array
    {
        $query = $this->qb
            ->table('manutencoes_plano')
            ->select(['id', 'chave', 'nome', 'tipo_veiculo', 'array', 'status']);

        if (!empty($search)) {
            $query->where('nome', 'LIKE', '%' . $search . '%');
        }

        $planos = $query
            ->orderBy('nome', 'ASC')
            ->paginate($page, $perPage)
            ->get();

        // Adicionar contagem de itens configurados
        foreach ($planos as &$plano) {
            $plano['itens_configurados'] = $this->contarItensConfigurados($plano['array']);
        }
        unset($plano);

        return $planos;
    }

    /**
     * Conta o total de planos do tenant
     *
     * @param string $chave Chave do tenant
     * @param string $search Termo de busca (opcional)
     * @return int Total de registros
     */
    public function contar(string $chave, string $search = ''): int
    {
        $query = $this->qb->table('manutencoes_plano');

        if (!empty($search)) {
            $query->where('nome', 'LIKE', '%' . $search . '%');
        }

        return $query->count();
    }

    /**
     * Busca um plano por ID
     *
     * @param int $id ID do plano
     * @return array|null Dados ou null
     */
    public function buscarPorId(int $id): ?array
    {
        $plano = $this->qb
            ->table('manutencoes_plano')
            ->where('id', '=', $id)
            ->first();

        if (!$plano) {
            return null;
        }

        $plano['itens_configurados'] = $this->contarItensConfigurados($plano['array']);

        return $plano;
    }

    /**
     * Cria um novo plano de manutenção
     *
     * @param array $dados Dados do plano
     * @return int ID criado
     */
    public function criar(array $dados): int
    {
        // Preparar JSON de intervalos
        $arrayIntervalos = $this->prepararArrayIntervalos($dados['intervalos'] ?? []);

        return $this->qb
            ->table('manutencoes_plano')
            ->insert([
                'chave' => $dados['chave'],
                'nome' => $dados['nome'],
                'tipo_veiculo' => $dados['tipo_veiculo'] ?? 'C',
                'array' => json_encode($arrayIntervalos, JSON_UNESCAPED_UNICODE),
                'status' => $dados['status'] ?? 'A',
            ]);
    }

    /**
     * Atualiza um plano existente
     *
     * @param int $id ID do plano
     * @param array $dados Dados para atualizar
     * @return int Linhas afetadas
     */
    public function atualizar(int $id, array $dados): int
    {
        $plano = $this->buscarPorId($id);
        if (!$plano) {
            throw new \InvalidArgumentException(t('modules.manutencao_plano.messages.not_found'));
        }

        $dadosUpdate = [];

        if (isset($dados['nome'])) {
            $dadosUpdate['nome'] = $dados['nome'];
        }

        if (isset($dados['status'])) {
            $dadosUpdate['status'] = $dados['status'];
        }

        if (array_key_exists('tipo_veiculo', $dados)) {
            $dadosUpdate['tipo_veiculo'] = $dados['tipo_veiculo'];
        }

        if (isset($dados['intervalos'])) {
            $arrayIntervalos = $this->prepararArrayIntervalos($dados['intervalos']);
            $dadosUpdate['array'] = json_encode($arrayIntervalos, JSON_UNESCAPED_UNICODE);
        }

        if (empty($dadosUpdate)) {
            return 0;
        }

        return $this->qb
            ->table('manutencoes_plano')
            ->where('id', '=', $id)
            ->update($dadosUpdate);
    }

    /**
     * Exclui um plano
     *
     * @param int $id ID do plano
     * @return int Linhas afetadas
     */
    public function excluir(int $id): int
    {
        $plano = $this->buscarPorId($id);
        if (!$plano) {
            throw new \InvalidArgumentException(t('modules.manutencao_plano.messages.not_found'));
        }

        // Verificar se há veículos vinculados
        $veiculosVinculados = $this->qb
            ->table('veiculos')
            ->where('id_plano_manutencao', '=', $id)
            ->count();

        if ($veiculosVinculados > 0) {
            throw new \InvalidArgumentException(t('modules.manutencao_plano.messages.has_vehicles'));
        }

        return $this->qb
            ->table('manutencoes_plano')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Conta quantos itens estão configurados (intervalo > 0) no JSON
     *
     * @param string|null $arrayJson JSON com intervalos
     * @return int Quantidade de itens configurados
     */
    private function contarItensConfigurados(?string $arrayJson): int
    {
        if (empty($arrayJson)) {
            return 0;
        }

        $intervalos = json_decode($arrayJson, true);
        if (!is_array($intervalos)) {
            return 0;
        }

        $count = 0;
        foreach ($intervalos as $valor) {
            $valorNumerico = $this->parseKm($valor);
            if ($valorNumerico > 0) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Prepara o array de intervalos para salvar no banco
     *
     * @param array $intervalos Array de intervalos do formulário
     * @return array Array formatado para JSON
     */
    private function prepararArrayIntervalos(array $intervalos): array
    {
        $resultado = [];

        foreach ($intervalos as $item => $valor) {
            // Converter para inteiro e formatar com ponto como separador de milhar
            $valorInt = $this->parseKm($valor);
            $resultado[$item] = $valorInt > 0 ? number_format($valorInt, 0, '', '.') : '0';
        }

        return $resultado;
    }

    /**
     * Converte string de km para inteiro
     * "10.000" -> 10000
     * "10000" -> 10000
     *
     * @param mixed $valor Valor a converter
     * @return int Valor em km
     */
    private function parseKm($valor): int
    {
        if (is_int($valor)) {
            return $valor;
        }

        if (is_string($valor)) {
            return (int) str_replace(['.', ','], '', $valor);
        }

        return (int) $valor;
    }

    /**
     * Lista planos ativos para select (usado em outros módulos)
     *
     * @param string $chave Chave do tenant
     * @return array Lista simplificada [id, nome]
     */
    public function listarParaSelect(string $chave): array
    {
        return $this->qb
            ->table('manutencoes_plano')
            ->select(['id', 'nome'])
            ->where('status', '=', 'A')
            ->orderBy('nome', 'ASC')
            ->get();
    }
}
