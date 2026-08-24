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

    /**
     * Exclui o sinistro e, quando existir, sua cobranca pendente vinculada.
     * A auditoria participa da mesma transacao para impedir exclusoes sem log.
     */
    public function excluir(
        int $id,
        string $vinculo,
        array $parent,
        string $nomeUsuario,
        bool $podeExcluirFinanceiro
    ): array
    {
        $mysqli = Model::sharedMysqli();
        $mysqli->begin_transaction();

        try {
            $sinistroModel = new Sinistro();
            $sinistro = $sinistroModel->buscarPorIdParaAtualizacao($id);
            if (!$sinistro) {
                throw new \InvalidArgumentException('Sinistro nao encontrado');
            }

            $campoVinculo = $vinculo === 'contrato' ? 'id_contrato' : 'id_locacao';
            if ((int) ($sinistro[$campoVinculo] ?? 0) !== (int) ($parent['id'] ?? 0)) {
                throw new \RuntimeException('O sinistro nao pertence ao vinculo informado');
            }

            $financeiro = null;
            $idFinanceiro = (int) ($sinistro['id_financeiro'] ?? 0);
            if ($idFinanceiro > 0) {
                if (!$podeExcluirFinanceiro) {
                    throw new \InvalidArgumentException('Sem permissao para excluir a cobranca vinculada');
                }
                $financeiroModel = new Financeiro();
                $financeiroBloqueado = $financeiroModel->buscarPorIdParaAtualizacao($idFinanceiro);
                if (!$financeiroBloqueado) {
                    throw new \RuntimeException('Cobranca vinculada ao sinistro nao encontrada');
                }
                if (($financeiroBloqueado['pago'] ?? 'N') === 'S') {
                    throw new \InvalidArgumentException('Estorne a cobranca paga antes de excluir o sinistro');
                }
                if (($financeiroBloqueado['tipo'] ?? '') !== 'R'
                    || (int) ($financeiroBloqueado[$campoVinculo] ?? 0) !== (int) $parent['id']) {
                    throw new \RuntimeException('A cobranca nao pertence ao mesmo contrato ou locacao do sinistro');
                }

                $verificacao = $financeiroModel->verificarVinculos($idFinanceiro);
                if ($verificacao['temVinculos']) {
                    throw new \InvalidArgumentException(
                        'Nao e possivel excluir a cobranca vinculada: ' . implode(', ', $verificacao['detalhes'])
                    );
                }

                $financeiro = $financeiroModel->buscarPorId($idFinanceiro) ?? $financeiroBloqueado;
            }

            $mensagem = $nomeUsuario . ', excluiu o sinistro [#' . $id . ']';
            if ($financeiro) {
                $identificadorFinanceiro = $financeiro['codigo'] ?: ('#' . $idFinanceiro);
                $mensagem .= ' e a cobranca vinculada [' . $identificadorFinanceiro . ']';
            }
            AuditLogService::registrarComCamposNaTransacao(
                $mysqli,
                $mensagem,
                $this->camposAuditoriaExclusao($sinistro, $financeiro, $vinculo, $parent)
            );

            if ($financeiro && (new Financeiro())->deletar($idFinanceiro) !== 1) {
                throw new \RuntimeException('Nao foi possivel excluir a cobranca vinculada');
            }
            if ($sinistroModel->deletar($id) !== 1) {
                throw new \RuntimeException('Nao foi possivel excluir o sinistro');
            }

            $mysqli->commit();
            return ['id_financeiro' => $idFinanceiro > 0 ? $idFinanceiro : null];
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

    private function camposAuditoriaExclusao(
        array $sinistro,
        ?array $financeiro,
        string $vinculo,
        array $parent
    ): array {
        $tipos = [
            'colisao' => 'Colisao/acidente',
            'furto_roubo' => 'Furto/roubo',
            'incendio' => 'Incendio',
            'alagamento' => 'Alagamento',
            'danos_terceiros' => 'Danos a terceiros',
            'perda_total' => 'Perda total',
            'outros' => 'Outros',
        ];
        $campos = [
            AuditLogService::campo('ID', '#' . $sinistro['id'], '', 'Sinistro'),
            AuditLogService::campo(
                $vinculo === 'contrato' ? 'Contrato' : 'Locacao',
                $parent['codigo'] ?? ('#' . $parent['id']),
                '',
                'Sinistro'
            ),
            AuditLogService::campo('Veiculo (ID)', '#' . $sinistro['id_veiculo'], '', 'Sinistro'),
            AuditLogService::campo('Data da ocorrencia', $sinistro['data_ocorrencia'], '', 'Sinistro'),
            AuditLogService::campo('Tipo', $tipos[$sinistro['tipo']] ?? $sinistro['tipo'], '', 'Sinistro'),
            AuditLogService::campo('Descricao', $sinistro['descricao'], '', 'Sinistro'),
            AuditLogService::campo(
                'Status',
                ($sinistro['status'] ?? Sinistro::STATUS_ABERTO) === Sinistro::STATUS_CONCLUIDO ? 'Concluido' : 'Aberto',
                '',
                'Sinistro'
            ),
        ];

        if ($sinistro['valor_estimado'] !== null) {
            $campos[] = AuditLogService::campo(
                'Valor estimado',
                currency_format((float) $sinistro['valor_estimado'], true),
                '',
                'Sinistro'
            );
        }
        if (!empty($sinistro['observacoes'])) {
            $campos[] = AuditLogService::campo('Observacoes', $sinistro['observacoes'], '', 'Sinistro');
        }

        if (!$financeiro) {
            return $campos;
        }

        $mapeamentoFinanceiro = [
            'id' => 'ID',
            'codigo' => 'Codigo',
            'descricao' => 'Descricao',
            'data_criada' => 'Data de criacao',
            'data_venci' => 'Vencimento',
            'data_pago' => 'Pagamento',
            'valor_subtotal' => 'Subtotal',
            'juros' => 'Juros',
            'multa' => 'Multa',
            'desconto' => 'Desconto',
            'valor_total' => 'Valor total',
            'cliente_nome' => 'Cliente',
            'filial_nome' => 'Filial',
            'conta_descricao' => 'Conta bancaria',
            'forma_pagamento_descricao' => 'Forma de pagamento',
            'plano_conta_hierarquia' => 'Plano de contas',
        ];
        foreach ($mapeamentoFinanceiro as $campo => $label) {
            $valor = $financeiro[$campo] ?? null;
            if ($valor === null || $valor === '') {
                continue;
            }
            if ($campo === 'id') {
                $valor = '#' . $valor;
            } elseif (in_array($campo, ['valor_subtotal', 'juros', 'multa', 'desconto', 'valor_total'], true)) {
                $valor = currency_format((float) $valor, true);
            }
            $campos[] = AuditLogService::campo($label, $valor, '', 'Cobranca vinculada');
        }
        $campos[] = AuditLogService::campo('Situacao', 'Pendente', '', 'Cobranca vinculada');

        return $campos;
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
