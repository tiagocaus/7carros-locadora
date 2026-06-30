<?php

namespace App\Models;

/**
 * Model Feriado
 *
 * Gerencia feriados nacionais, estaduais e municipais por tenant.
 *
 * Feriados globais (chave='0') sao visiveis para todas as empresas.
 * Feriados especificos usam a chave do tenant.
 */
class Feriado extends Model
{
    /**
     * Tipos de feriado disponíveis
     */
    public const TIPOS = [
        'nacional' => 'Nacional',
        'estadual' => 'Estadual',
        'municipal' => 'Municipal',
        'ponto_facultativo' => 'Ponto Facultativo',
    ];

    /**
     * Lista todos os feriados do tenant (incluindo globais)
     *
     * @param string|null $estado Filtrar por UF (opcional)
     * @param string|null $cidade Filtrar por cidade (opcional)
     * @param string|null $pais Filtrar por pais (opcional)
     * @return array Lista de feriados
     */
    public function listar(?string $estado = null, ?string $cidade = null, ?string $pais = null): array
    {
        $query = $this->qb
            ->table('feriados')
            ->select(['id', 'nome', 'mes', 'dia', 'tipo', 'pais', 'estado', 'cidade', 'chave'])
            ->withGlobals();  // Inclui tenant + globais (chave='0')

        if ($pais !== null) {
            $query->where('pais', '=', $pais);
        }

        if ($estado !== null) {
            $query->whereNested(function ($q) use ($estado) {
                $q->whereNull('estado')
                  ->orWhere('estado', '=', $estado);
            });
        }

        if ($cidade !== null) {
            $query->whereNested(function ($q) use ($cidade) {
                $q->whereNull('cidade')
                  ->orWhere('cidade', '=', $cidade);
            });
        }

        return $query
            ->orderBy('mes', 'ASC')
            ->orderBy('dia', 'ASC')
            ->get();
    }

    /**
     * Lista feriados aplicaveis para uma matriz/filial especifica
     *
     * Inclui feriados globais (chave='0') e do tenant.
     *
     * @param string|null $estado UF da matriz/filial
     * @param string|null $cidade Cidade da matriz/filial
     * @param string|null $pais Pais da matriz/filial (codigo ISO 2 letras)
     * @return array Lista de feriados aplicaveis
     */
    public function listarAplicaveis(?string $estado = null, ?string $cidade = null, ?string $pais = null): array
    {
        $query = $this->qb
            ->table('feriados')
            ->select(['id', 'nome', 'mes', 'dia', 'tipo', 'pais', 'estado', 'cidade', 'chave'])
            ->withGlobals();  // Inclui tenant + globais (chave='0')

        // Filtrar por pais se informado
        if ($pais !== null) {
            $query->where('pais', '=', $pais);
        }

        // Construir filtro de localização
        $query->whereNested(function ($q) use ($estado, $cidade) {
            // Sempre incluir nacionais
            $q->where('tipo', '=', 'nacional');

            // Incluir estaduais do estado
            if ($estado) {
                $q->orWhereNested(function ($sub) use ($estado) {
                    $sub->where('tipo', '=', 'estadual')
                        ->where('estado', '=', $estado);
                });
            }

            // Incluir municipais da cidade
            if ($estado && $cidade) {
                $q->orWhereNested(function ($sub) use ($estado, $cidade) {
                    $sub->where('tipo', '=', 'municipal')
                        ->where('estado', '=', $estado)
                        ->where('cidade', '=', $cidade);
                });
            }
        });

        return $query
            ->orderBy('mes', 'ASC')
            ->orderBy('dia', 'ASC')
            ->get();
    }

    /**
     * Busca um feriado por ID
     *
     * @param int $id ID do feriado
     * @return array|null Dados ou null
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->qb
            ->table('feriados')
            ->withoutChave()
            ->where('id', '=', $id)
            ->first();
    }

    /**
     * Verifica se uma data e feriado
     *
     * Inclui feriados globais (chave='0') e do tenant.
     *
     * @param string $data Data no formato Y-m-d
     * @param string|null $estado UF (opcional)
     * @param string|null $cidade Cidade (opcional)
     * @param string|null $pais Pais (codigo ISO 2 letras, opcional)
     * @return array|null Dados do feriado ou null se nao for
     */
    public function isFeriado(string $data, ?string $estado = null, ?string $cidade = null, ?string $pais = null): ?array
    {
        $timestamp = strtotime($data);
        $mes = (int) \App\Helpers\DateHelper::formatTimestamp($timestamp, 'n');
        $dia = (int) \App\Helpers\DateHelper::formatTimestamp($timestamp, 'j');

        $query = $this->qb
            ->table('feriados')
            ->withGlobals()  // Inclui tenant + globais (chave='0')
            ->where('mes', '=', $mes)
            ->where('dia', '=', $dia);

        // Filtrar por pais se informado
        if ($pais !== null) {
            $query->where('pais', '=', $pais);
        }

        // Aplicar filtros de localizacao
        $query->whereNested(function ($q) use ($estado, $cidade) {
            // Nacionais
            $q->where('tipo', '=', 'nacional');

            // Estaduais
            if ($estado) {
                $q->orWhereNested(function ($sub) use ($estado) {
                    $sub->where('tipo', '=', 'estadual')
                        ->where('estado', '=', $estado);
                });
            }

            // Municipais
            if ($estado && $cidade) {
                $q->orWhereNested(function ($sub) use ($estado, $cidade) {
                    $sub->where('tipo', '=', 'municipal')
                        ->where('estado', '=', $estado)
                        ->where('cidade', '=', $cidade);
                });
            }
        });

        return $query->first();
    }

    /**
     * Cria um novo feriado
     *
     * @param array $dados Dados do feriado
     * @return int ID criado
     */
    public function criar(array $dados): int
    {
        return $this->qb
            ->table('feriados')
            ->insert([
                'chave' => $dados['chave'],
                'nome' => $dados['nome'],
                'mes' => (int) $dados['mes'],
                'dia' => (int) $dados['dia'],
                'tipo' => $dados['tipo'] ?? 'nacional',
                'estado' => $dados['estado'] ?? null,
                'cidade' => $dados['cidade'] ?? null,
            ]);
    }

    /**
     * Atualiza um feriado existente
     *
     * @param int $id ID do feriado
     * @param array $dados Dados para atualizar
     * @return int Linhas afetadas
     */
    public function atualizar(int $id, array $dados): int
    {
        $dadosUpdate = [];

        if (isset($dados['nome'])) {
            $dadosUpdate['nome'] = $dados['nome'];
        }
        if (isset($dados['mes'])) {
            $dadosUpdate['mes'] = (int) $dados['mes'];
        }
        if (isset($dados['dia'])) {
            $dadosUpdate['dia'] = (int) $dados['dia'];
        }
        if (isset($dados['tipo'])) {
            $dadosUpdate['tipo'] = $dados['tipo'];
        }
        if (array_key_exists('estado', $dados)) {
            $dadosUpdate['estado'] = $dados['estado'];
        }
        if (array_key_exists('cidade', $dados)) {
            $dadosUpdate['cidade'] = $dados['cidade'];
        }

        return $this->qb
            ->table('feriados')
            ->withoutChave()
            ->where('id', '=', $id)
            ->update($dadosUpdate);
    }

    /**
     * Exclui um feriado
     *
     * @param int $id ID do feriado
     * @return int Linhas afetadas
     */
    public function excluir(int $id): int
    {
        return $this->qb
            ->table('feriados')
            ->withoutChave()
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Lista feriados de um ano específico formatados com datas completas
     *
     * @param int $ano Ano
     * @param string|null $estado UF (opcional)
     * @param string|null $cidade Cidade (opcional)
     * @param string|null $pais Pais (codigo ISO 2 letras, opcional)
     * @return array Lista de feriados com data completa
     */
    public function listarPorAno(int $ano, ?string $estado = null, ?string $cidade = null, ?string $pais = null): array
    {
        $feriados = $this->listarAplicaveis($estado, $cidade, $pais);

        return array_map(function ($f) use ($ano) {
            $data = sprintf('%04d-%02d-%02d', $ano, $f['mes'], $f['dia']);
            return [
                'id' => $f['id'],
                'nome' => $f['nome'],
                'data' => $data,
                'data_formatada' => format_date($data),
                'tipo' => $f['tipo'],
                'tipo_nome' => self::TIPOS[$f['tipo']] ?? $f['tipo'],
            ];
        }, $feriados);
    }

    /**
     * Lista próximos feriados a partir de hoje
     *
     * Retorna feriados futuros ordenados por data, considerando
     * ano atual e próximo para completar o limite solicitado.
     *
     * @param int $limite Quantidade máxima de feriados (default 5)
     * @param string|null $estado UF (opcional)
     * @param string|null $cidade Cidade (opcional)
     * @param string|null $pais Pais (codigo ISO 2 letras, opcional)
     * @return array Lista de próximos feriados com data completa
     */
    public function listarProximos(int $limite = 5, ?string $estado = null, ?string $cidade = null, ?string $pais = null): array
    {
        $hoje = new \DateTime();
        $mesAtual = (int) $hoje->format('n');
        $diaAtual = (int) $hoje->format('j');
        $anoAtual = (int) $hoje->format('Y');

        $feriados = $this->listarAplicaveis($estado, $cidade, $pais);

        $resultado = [];

        // Primeiro: feriados restantes deste ano
        foreach ($feriados as $f) {
            if ($f['mes'] > $mesAtual || ($f['mes'] == $mesAtual && $f['dia'] >= $diaAtual)) {
                $data = sprintf('%04d-%02d-%02d', $anoAtual, $f['mes'], $f['dia']);
                $resultado[] = [
                    'id' => $f['id'],
                    'nome' => $f['nome'],
                    'data' => $data,
                    'data_formatada' => format_date($data),
                    'tipo' => $f['tipo'],
                    'tipo_nome' => self::TIPOS[$f['tipo']] ?? $f['tipo'],
                    'mes' => $f['mes'],
                    'dia' => $f['dia'],
                ];
            }
        }

        // Depois: feriados do próximo ano (se precisar completar limite)
        if (count($resultado) < $limite) {
            foreach ($feriados as $f) {
                if (count($resultado) >= $limite) {
                    break;
                }
                $data = sprintf('%04d-%02d-%02d', $anoAtual + 1, $f['mes'], $f['dia']);
                $resultado[] = [
                    'id' => $f['id'],
                    'nome' => $f['nome'],
                    'data' => $data,
                    'data_formatada' => format_date($data),
                    'tipo' => $f['tipo'],
                    'tipo_nome' => self::TIPOS[$f['tipo']] ?? $f['tipo'],
                    'mes' => $f['mes'],
                    'dia' => $f['dia'],
                ];
            }
        }

        return array_slice($resultado, 0, $limite);
    }
}
