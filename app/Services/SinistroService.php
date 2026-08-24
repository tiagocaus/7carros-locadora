<?php

namespace App\Services;

use App\Helpers\DateHelper;
use App\Models\Financeiro;
use App\Models\Model;
use App\Models\PlanoDeContas;
use App\Models\Sinistro;

class SinistroService
{
    public function criar(string $vinculo, array $parent, array $dados, string $chave, int $idFuncionario): int
    {
        $this->validarDados($vinculo, $parent, $dados);
        $mysqli = Model::sharedMysqli();
        $mysqli->begin_transaction();

        try {
            $sinistroModel = new Sinistro();
            $idVinculo = (int) $parent['id'];
            $id = $sinistroModel->criar([
                'chave' => $chave,
                'id_contrato' => $vinculo === 'contrato' ? $idVinculo : null,
                'id_locacao' => $vinculo === 'locacao' ? $idVinculo : null,
                'id_veiculo' => (int) $dados['id_veiculo'],
                'id_financeiro' => null,
                'id_funcionario' => $idFuncionario ?: null,
                'data_ocorrencia' => $this->normalizarData((string) $dados['data_ocorrencia']),
                'tipo' => (string) $dados['tipo'],
                'descricao' => trim((string) $dados['descricao']),
                'valor_estimado' => $this->valorOpcional($dados['valor_estimado'] ?? null),
                'observacoes' => $this->textoOpcional($dados['observacoes'] ?? null),
                'status' => Sinistro::STATUS_ABERTO,
            ]);

            if (!empty($dados['gerar_cobranca'])) {
                $idFinanceiro = $this->criarCobranca($vinculo, $parent, $id, $dados['cobranca'] ?? [], $chave, (int) $dados['id_veiculo']);
                $sinistroModel->atualizar($id, ['id_financeiro' => $idFinanceiro]);
            }

            $mysqli->commit();
            return $id;
        } catch (\Throwable $e) {
            $mysqli->rollback();
            throw $e;
        }
    }

    public function atualizar(int $id, string $vinculo, array $parent, array $dados): void
    {
        $this->validarDados($vinculo, $parent, $dados);
        $status = (string) ($dados['status'] ?? Sinistro::STATUS_ABERTO);
        if (!in_array($status, [Sinistro::STATUS_ABERTO, Sinistro::STATUS_CONCLUIDO], true)) {
            throw new \InvalidArgumentException('Status do sinistro invalido');
        }

        (new Sinistro())->atualizar($id, [
            'id_veiculo' => (int) $dados['id_veiculo'],
            'data_ocorrencia' => $this->normalizarData((string) $dados['data_ocorrencia']),
            'tipo' => (string) $dados['tipo'],
            'descricao' => trim((string) $dados['descricao']),
            'valor_estimado' => $this->valorOpcional($dados['valor_estimado'] ?? null),
            'observacoes' => $this->textoOpcional($dados['observacoes'] ?? null),
            'status' => $status,
        ]);
    }

    public function gerarCobranca(array $sinistro, string $vinculo, array $parent, array $cobranca, string $chave): int
    {
        if (!empty($sinistro['id_financeiro'])) {
            throw new \InvalidArgumentException('Este sinistro ja possui cobranca vinculada');
        }

        $mysqli = Model::sharedMysqli();
        $mysqli->begin_transaction();
        try {
            $idFinanceiro = $this->criarCobranca(
                $vinculo,
                $parent,
                (int) $sinistro['id'],
                $cobranca,
                $chave,
                (int) $sinistro['id_veiculo']
            );
            (new Sinistro())->atualizar((int) $sinistro['id'], ['id_financeiro' => $idFinanceiro]);
            $mysqli->commit();
            return $idFinanceiro;
        } catch (\Throwable $e) {
            $mysqli->rollback();
            throw $e;
        }
    }

    private function criarCobranca(string $vinculo, array $parent, int $idSinistro, array $dados, string $chave, int $idVeiculo): int
    {
        $valor = currency_parse($dados['valor'] ?? 0);
        if ($valor <= 0 || empty($dados['data_venci']) || empty($dados['id_conta']) || empty($dados['id_forma_pagamento'])) {
            throw new \InvalidArgumentException('Preencha valor, vencimento, conta e forma de pagamento da cobranca');
        }

        $plano = (new PlanoDeContas())->buscarPorHierarquia(Sinistro::PLANO_CONTA_SINISTROS);
        if (!$plano || ($plano['tipo'] ?? '') !== 'R') {
            throw new \RuntimeException('Plano de contas de sinistros nao encontrado');
        }

        return (new Financeiro())->criar([
            'chave' => $chave,
            'id_matriz_filial' => $parent['id_matriz_filial_retirada'] ?? null,
            'id_cliente' => $parent['id_cliente'] ?? null,
            'id_conta' => (int) $dados['id_conta'],
            'id_forma_pagamento' => (int) $dados['id_forma_pagamento'],
            'id_plano_de_conta' => (int) $plano['id'],
            'id_contrato' => $vinculo === 'contrato' ? (int) $parent['id'] : null,
            'id_locacao' => $vinculo === 'locacao' ? (int) $parent['id'] : null,
            'id_veiculo' => $idVeiculo,
            'tipo' => 'R',
            'pago' => 'N',
            'parcela' => 0,
            'total_parcelas' => 0,
            'descricao' => 'Sinistro #' . $idSinistro . ' - ' . ($parent['codigo'] ?? ''),
            'data_criada' => DateHelper::todayForDatabase(),
            'data_venci' => $this->normalizarDataCivil((string) $dados['data_venci']),
            'valor_subtotal' => $valor,
            'valor_total' => $valor,
        ]);
    }

    private function validarDados(string $vinculo, array $parent, array $dados): void
    {
        if (!in_array($vinculo, ['contrato', 'locacao'], true)) {
            throw new \InvalidArgumentException('Vinculo de sinistro invalido');
        }
        if (empty($dados['id_veiculo']) || empty($dados['data_ocorrencia']) || empty($dados['tipo']) || trim((string) ($dados['descricao'] ?? '')) === '') {
            throw new \InvalidArgumentException('Preencha data, veiculo, tipo e descricao do sinistro');
        }
        if (!in_array((string) $dados['tipo'], Sinistro::TIPOS, true)) {
            throw new \InvalidArgumentException('Tipo de sinistro invalido');
        }
        if (!(new Sinistro())->veiculoPertenceAoVinculo($vinculo, (int) $parent['id'], (int) $dados['id_veiculo'])) {
            throw new \InvalidArgumentException('O veiculo informado nao pertence a este contrato ou locacao');
        }
    }

    private function normalizarData(string $data): string
    {
        $data = str_replace('T', ' ', trim($data));
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2})(?::(\d{2}))?$/', $data, $partes)) {
            throw new \InvalidArgumentException('Data do sinistro invalida');
        }

        $ano = (int) $partes[1];
        $mes = (int) $partes[2];
        $dia = (int) $partes[3];
        $hora = (int) $partes[4];
        $minuto = (int) $partes[5];
        $segundo = isset($partes[6]) ? (int) $partes[6] : 0;
        if (!checkdate($mes, $dia, $ano) || $hora > 23 || $minuto > 59 || $segundo > 59) {
            throw new \InvalidArgumentException('Data do sinistro invalida');
        }

        return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $ano, $mes, $dia, $hora, $minuto, $segundo);
    }

    private function valorOpcional(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        $normalizado = currency_parse($valor);
        if ($normalizado < 0) {
            throw new \InvalidArgumentException('Valor estimado nao pode ser negativo');
        }
        return $normalizado;
    }

    private function normalizarDataCivil(string $data): string
    {
        $data = trim($data);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $data, $partes)
            || !checkdate((int) $partes[2], (int) $partes[3], (int) $partes[1])) {
            throw new \InvalidArgumentException('Data de vencimento da cobranca invalida');
        }
        return $data;
    }

    private function textoOpcional(mixed $valor): ?string
    {
        $texto = trim((string) ($valor ?? ''));
        return $texto === '' ? null : $texto;
    }
}
