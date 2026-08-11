<?php

namespace App\Models;

/**
 * Model ComandoParcela
 *
 * Gerencia comandos de parcelas (formas_pagamento_comandos).
 * Registros com chave=0 sao padrao do sistema e nao podem ser editados/excluidos.
 * Inclui logica de parsing dos comandos de parcelamento.
 */
class ComandoParcela extends Model
{
    /**
     * Lista todos os comandos de parcelas (sistema + tenant)
     *
     * @return array Lista de comandos
     */
    public function listar(): array
    {
        $chave = $_SESSION['chave'] ?? '';

        return $this->qb
            ->withoutChave()
            ->table('formas_pagamento_comandos')
            ->whereNested(function ($q) use ($chave) {
                $q->where('chave', '=', '0')
                  ->orWhere('chave', '=', $chave);
            })
            ->orderBy('chave', 'ASC')
            ->orderBy('comando', 'ASC')
            ->get();
    }

    /**
     * Lista comandos ativos para select (sistema + tenant)
     *
     * @param string $search Termo de busca (opcional)
     * @return array Lista com id, comando, descricao
     */
    public function listarParaSelect(string $search = ''): array
    {
        $chave = $_SESSION['chave'] ?? '';

        $query = $this->qb
            ->withoutChave()
            ->table('formas_pagamento_comandos')
            ->select(['id', 'comando', 'descricao', 'chave'])
            ->where('status', '=', 'A')
            ->whereNested(function ($q) use ($chave) {
                $q->where('chave', '=', '0')
                  ->orWhere('chave', '=', $chave);
            });

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('comando', 'LIKE', $searchTerm)
                  ->orWhere('descricao', 'LIKE', $searchTerm);
            });
        }

        return $query
            ->orderBy('chave', 'ASC')
            ->orderBy('comando', 'ASC')
            ->limit(50)
            ->get();
    }

    /**
     * Lista comandos com paginacao (sistema + tenant)
     *
     * @param int $page Pagina atual
     * @param int $perPage Registros por pagina
     * @param string $search Termo de busca (opcional)
     * @return array Lista de comandos
     */
    public function listarPaginado(int $page, int $perPage, string $search = ''): array
    {
        $chave = $_SESSION['chave'] ?? '';

        $query = $this->qb
            ->withoutChave()
            ->table('formas_pagamento_comandos')
            ->whereNested(function ($q) use ($chave) {
                $q->where('chave', '=', '0')
                  ->orWhere('chave', '=', $chave);
            });

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('comando', 'LIKE', $searchTerm)
                  ->orWhere('descricao', 'LIKE', $searchTerm);
            });
        }

        return $query
            ->orderBy('chave', 'ASC')
            ->orderBy('comando', 'ASC')
            ->paginate($page, $perPage)
            ->get();
    }

    /**
     * Conta o total de comandos (sistema + tenant)
     *
     * @param string $search Termo de busca (opcional)
     * @return int Total de registros
     */
    public function contar(string $search = ''): int
    {
        $chave = $_SESSION['chave'] ?? '';

        $query = $this->qb
            ->withoutChave()
            ->table('formas_pagamento_comandos')
            ->whereNested(function ($q) use ($chave) {
                $q->where('chave', '=', '0')
                  ->orWhere('chave', '=', $chave);
            });

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('comando', 'LIKE', $searchTerm)
                  ->orWhere('descricao', 'LIKE', $searchTerm);
            });
        }

        return $query->count();
    }

    /**
     * Busca um comando por ID
     *
     * @param int $id ID do comando
     * @return array|null Dados ou null
     */
    public function buscarPorId(int $id): ?array
    {
        $chave = $_SESSION['chave'] ?? '';

        return $this->qb
            ->withoutChave()
            ->table('formas_pagamento_comandos')
            ->where('id', '=', $id)
            ->whereNested(function ($q) use ($chave) {
                $q->where('chave', '=', '0')
                  ->orWhere('chave', '=', $chave);
            })
            ->first();
    }

    /**
     * Cria um novo comando de parcelas (somente tenant)
     *
     * @param array $dados Dados do comando
     * @return int ID criado
     */
    public function criar(array $dados): int
    {
        return $this->qb
            ->table('formas_pagamento_comandos')
            ->insert([
                'chave' => $dados['chave'],
                'comando' => $dados['comando'],
                'descricao' => $dados['descricao'] ?? null,
                'status' => $dados['status'] ?? 'A',
            ]);
    }

    /**
     * Atualiza um comando existente (somente tenant, nao permite editar chave=0)
     *
     * @param int $id ID do comando
     * @param array $dados Dados para atualizar
     * @return int Linhas afetadas
     */
    public function atualizar(int $id, array $dados): int
    {
        $comando = $this->buscarPorId($id);
        if (!$comando) {
            throw new \InvalidArgumentException('Comando de parcelas nao encontrado');
        }

        if ($comando['chave'] === '0') {
            throw new \InvalidArgumentException('Comandos padrao do sistema nao podem ser editados');
        }

        $dadosUpdate = [];

        if (isset($dados['comando'])) {
            $dadosUpdate['comando'] = $dados['comando'];
        }
        if (array_key_exists('descricao', $dados)) {
            $dadosUpdate['descricao'] = $dados['descricao'] ?: null;
        }
        if (isset($dados['status'])) {
            $dadosUpdate['status'] = $dados['status'];
        }

        if (empty($dadosUpdate)) {
            return 0;
        }

        return $this->qb
            ->table('formas_pagamento_comandos')
            ->where('id', '=', $id)
            ->update($dadosUpdate);
    }

    /**
     * Exclui um comando (somente tenant, nao permite excluir chave=0)
     *
     * @param int $id ID do comando
     * @return int Linhas afetadas
     */
    public function excluir(int $id): int
    {
        $comando = $this->buscarPorId($id);
        if (!$comando) {
            throw new \InvalidArgumentException('Comando de parcelas nao encontrado');
        }

        if ($comando['chave'] === '0') {
            throw new \InvalidArgumentException('Comandos padrao do sistema nao podem ser excluidos');
        }

        return $this->qb
            ->table('formas_pagamento_comandos')
            ->where('id', '=', $id)
            ->delete();
    }

    // ============================================================
    // LOGICA DE PARSING DE COMANDOS (movido de FormaPagamento)
    // ============================================================

    /**
     * Infere o label de exibicao a partir do comando de parcelas
     *
     * @param string $comando Comando de parcelas
     * @return string Label legivel
     */
    public static function inferirLabel(string $comando): string
    {
        $comando = trim($comando);

        if ($comando === '' || $comando === '0') {
            return 'a vista';
        }

        // Numero unico = X dias
        if (preg_match('/^\d+$/', $comando)) {
            return $comando . ' dias';
        }

        // Range X-Y = ate Yx
        if (preg_match('/^(\d+)-(\d+)$/', $comando, $matches)) {
            $min = (int) $matches[1];
            $max = (int) $matches[2];
            if ($min === 0 || $min === 1) {
                return 'ate ' . $max . 'x';
            }
            return $min . 'x a ' . $max . 'x';
        }

        // Prazos fixos 30/60/90 = "30/60/90 dias"
        if (preg_match('/^\d+(\/\d+)+$/', $comando)) {
            return $comando . ' dias';
        }

        // Semanal wX
        if (preg_match('/^w(\d+)$/', $comando, $matches)) {
            return $matches[1] . ' semanas';
        }

        // Semanal com dia wX-Dia
        if (preg_match('/^w(\d+)-(Dom|Seg|Ter|Qua|Qui|Sex|Sab)$/', $comando, $matches)) {
            return $matches[1] . ' semanas (' . $matches[2] . ')';
        }

        // Dia do mes dX
        if (preg_match('/^d(\d+)$/', $comando, $matches)) {
            return 'dia ' . $matches[1];
        }

        // Dia da semana unico
        $diasSemana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab'];
        if (in_array($comando, $diasSemana, true)) {
            return $comando;
        }

        return $comando;
    }

    /**
     * Analisa o comando de parcelas e retorna as opcoes disponiveis
     *
     * @param string $comando String do comando de parcelas
     * @return array Informacoes sobre as opcoes de parcelamento
     */
    public static function parseComando(string $comando): array
    {
        $comando = trim($comando);

        if (empty($comando) || $comando === '0') {
            return [
                'tipo' => 'avista',
                'opcoes' => [1],
                'min' => 1,
                'max' => 1,
                'intervalos' => [],
                'comando' => $comando,
            ];
        }

        // Prazo unico (numero inteiro)
        if (preg_match('/^\d+$/', $comando)) {
            $dias = (int) $comando;
            return [
                'tipo' => 'prazo_unico',
                'opcoes' => [1],
                'min' => 1,
                'max' => 1,
                'intervalos' => [$dias],
                'comando' => $comando,
            ];
        }

        // Parcelas mensais (X-Y)
        if (preg_match('/^(\d+)-(\d+)$/', $comando, $matches)) {
            $min = (int) $matches[1];
            $max = (int) $matches[2];
            $opcoes = range(max(1, $min), $max);
            return [
                'tipo' => 'mensal',
                'opcoes' => $opcoes,
                'min' => max(1, $min),
                'max' => $max,
                'intervalos' => [],
                'comando' => $comando,
            ];
        }

        // Prazos estabelecidos (30/60/90)
        if (preg_match('/^\d+(\/\d+)+$/', $comando)) {
            $intervalos = array_map('intval', explode('/', $comando));
            return [
                'tipo' => 'prazos_fixos',
                'opcoes' => [count($intervalos)],
                'min' => count($intervalos),
                'max' => count($intervalos),
                'intervalos' => $intervalos,
                'comando' => $comando,
            ];
        }

        // Parcelas semanais com dia (wX-Dia)
        if (preg_match('/^w(\d+)-(Dom|Seg|Ter|Qua|Qui|Sex|Sab)$/', $comando, $matches)) {
            $semanas = (int) $matches[1];
            $diaSemana = $matches[2];
            return [
                'tipo' => 'semanal_dia',
                'opcoes' => [$semanas],
                'min' => $semanas,
                'max' => $semanas,
                'intervalos' => [],
                'dia_semana' => $diaSemana,
                'comando' => $comando,
            ];
        }

        // Parcelas semanais (wX)
        if (preg_match('/^w(\d+)$/', $comando, $matches)) {
            $semanas = (int) $matches[1];
            return [
                'tipo' => 'semanal',
                'opcoes' => [$semanas],
                'min' => $semanas,
                'max' => $semanas,
                'intervalos' => [],
                'comando' => $comando,
            ];
        }

        // Dia do mes (dX)
        if (preg_match('/^d(\d+)$/', $comando, $matches)) {
            $diaMes = (int) $matches[1];
            return [
                'tipo' => 'dia_mes',
                'opcoes' => range(1, 12),
                'min' => 1,
                'max' => 12,
                'intervalos' => [],
                'dia_mes' => $diaMes,
                'comando' => $comando,
            ];
        }

        // Dia da semana unico (Seg, Ter, Qua, etc.)
        $diasSemana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab'];
        if (in_array($comando, $diasSemana, true)) {
            return [
                'tipo' => 'dias_semana',
                'opcoes' => [1],
                'min' => 1,
                'max' => 1,
                'intervalos' => [],
                'dias_semana' => [$comando],
                'dia_semana' => $comando,
                'comando' => $comando,
            ];
        }

        // Formato nao reconhecido
        return [
            'tipo' => 'desconhecido',
            'opcoes' => [1],
            'min' => 1,
            'max' => 1,
            'intervalos' => [],
            'comando' => $comando,
        ];
    }

    /**
     * Calcula as datas de vencimento baseado no comando de parcelas
     *
     * @param string $comando String do comando
     * @param string $dataBase Data base para calculo (Y-m-d)
     * @param int $numParcelas Numero de parcelas desejado
     * @return array Lista de datas no formato Y-m-d
     */
    public static function calcularDatasVencimento(string $comando, string $dataBase, int $numParcelas): array
    {
        $info = self::parseComando($comando);
        $datas = [];
        $base = new \DateTime($dataBase);

        switch ($info['tipo']) {
            case 'avista':
                $datas[] = $base->format('Y-m-d');
                break;

            case 'prazo_unico':
                $data = clone $base;
                $data->modify("+{$info['intervalos'][0]} days");
                $datas[] = $data->format('Y-m-d');
                break;

            case 'mensal':
                for ($i = 0; $i < $numParcelas; $i++) {
                    $data = clone $base;
                    $data->modify("+{$i} months");
                    $datas[] = $data->format('Y-m-d');
                }
                break;

            case 'prazos_fixos':
                foreach ($info['intervalos'] as $dias) {
                    $data = clone $base;
                    $data->modify("+{$dias} days");
                    $datas[] = $data->format('Y-m-d');
                }
                break;

            case 'semanal':
            case 'semanal_dia':
                for ($i = 0; $i < $numParcelas; $i++) {
                    $data = clone $base;
                    $data->modify("+{$i} weeks");
                    if (isset($info['dia_semana'])) {
                        self::ajustarParaDiaSemana($data, $info['dia_semana']);
                    }
                    $datas[] = $data->format('Y-m-d');
                }
                break;

            case 'dia_mes':
                $diaMes = $info['dia_mes'];
                for ($i = 0; $i < $numParcelas; $i++) {
                    $data = clone $base;
                    $data->modify("+{$i} months");
                    $ultimoDia = (int) $data->format('t');
                    $diaReal = min($diaMes, $ultimoDia);
                    $data->setDate((int) $data->format('Y'), (int) $data->format('m'), $diaReal);
                    $datas[] = $data->format('Y-m-d');
                }
                break;

            case 'dias_semana':
                $data = clone $base;
                self::ajustarParaDiaSemana($data, $info['dia_semana']);
                $datas[] = $data->format('Y-m-d');
                break;

            default:
                $datas[] = $base->format('Y-m-d');
                break;
        }

        return $datas;
    }

    /**
     * Calcula automaticamente o numero de parcelas baseado no comando e datas
     *
     * @param string $comando String do comando
     * @param string $dataInicio Data do primeiro vencimento (Y-m-d)
     * @param string $dataFim Data fim do contrato (Y-m-d)
     * @return int Numero de parcelas calculado
     */
    public static function calcularNumParcelasAutomatico(string $comando, string $dataInicio, string $dataFim): int
    {
        $info = self::parseComando($comando);
        $inicio = new \DateTime($dataInicio);
        $fim = new \DateTime($dataFim);

        switch ($info['tipo']) {
            case 'avista':
            case 'prazo_unico':
            case 'dias_semana':
                return 1;

            case 'prazos_fixos':
                return count($info['intervalos']);

            case 'semanal':
            case 'semanal_dia':
                return $info['max'];

            case 'mensal':
                $diff = $inicio->diff($fim);
                $meses = ($diff->y * 12) + $diff->m;
                if ($diff->d > 0) {
                    $meses++;
                }
                return max($info['min'], min($meses, $info['max']));

            case 'dia_mes':
                $count = 0;
                $data = clone $inicio;
                while ($data <= $fim) {
                    $count++;
                    $data->modify('+1 month');
                }
                return max(1, min($count, 12));

            default:
                return 1;
        }
    }

    /**
     * Ajusta a data para o proximo dia da semana especificado
     *
     * @param \DateTime $data Data a ajustar
     * @param string $diaSemana Dia da semana (Seg, Ter, etc.)
     */
    private static function ajustarParaDiaSemana(\DateTime $data, string $diaSemana): void
    {
        $diasMap = [
            'Dom' => 0, 'Seg' => 1, 'Ter' => 2, 'Qua' => 3,
            'Qui' => 4, 'Sex' => 5, 'Sab' => 6
        ];

        $diaAlvo = $diasMap[$diaSemana] ?? 1;
        $diaAtual = (int) $data->format('w');

        if ($diaAtual !== $diaAlvo) {
            $diff = $diaAlvo - $diaAtual;
            if ($diff < 0) {
                $diff += 7;
            }
            $data->modify("+{$diff} days");
        }
    }

}
