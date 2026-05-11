<?php

/**
 * Migration 00030: Padronizar Colunas da Tabela locacoes
 *
 * Renomeia 50 colunas para seguir o padrão de nomenclatura:
 * - FKs: id_[tabela]_[contexto] (ex: id_cliente, id_matriz_filial_retirada)
 * - Colunas: snake_case (ex: data_saida, valor_desconto)
 *
 * IMPORTANTE: Esta é a tabela mais consultada do sistema (87k registros).
 * Após executar esta migration, atualizar:
 * - Model: Locacao.php ($fillable, $casts)
 * - Controller: LocacaoController.php
 * - Views: locacoes/*.blade.php
 * - JavaScript: locacoes.js
 */

use App\Database\Migration;

return new class extends Migration
{
    /**
     * Mapeamento DE → PARA das colunas
     */
    private array $columnRenames = [
        // FKs - Relacionamentos
        'clienteID'             => 'id_cliente',
        'grupo'                 => 'id_grupo',
        'veiculo'               => 'id_veiculo',
        'formaPagamento'        => 'id_forma_pagamento',
        'localRetirada'         => 'id_matriz_filial_retirada',
        'localDevolucao'        => 'id_matriz_filial_devolucao',
        'bloqueioConta'         => 'id_conta_bloqueio',
        'depositoFinanceiroID'  => 'id_financeiro_deposito',

        // Datas
        'dataSaida'             => 'data_saida',
        'dataPrevista'          => 'data_prevista',
        'dataChegada'           => 'data_chegada',

        // Cliente (desnormalizado)
        'clienteNome'           => 'cliente_nome',

        // Bloqueio
        'bloqueioTipo'          => 'bloqueio_tipo',
        'bloqueioValor'         => 'bloqueio_valor',
        'bloqueioPrazoDevolucao'=> 'bloqueio_prazo_devolucao',
        'bloqueioDataDevolucao' => 'bloqueio_data_devolucao',

        // Depósito
        'depositoTipo'          => 'deposito_tipo',
        'depositoValor'         => 'deposito_valor',

        // Odômetro
        'odometroIni'           => 'odometro_ini',
        'odometroFim'           => 'odometro_fim',
        'odometroUsado'         => 'odometro_usado',

        // Combustível
        'combustivelValor'      => 'combustivel_valor',
        'combustivelIni'        => 'combustivel_ini',
        'combustivelFim'        => 'combustivel_fim',
        'combustivelUsado'      => 'combustivel_usado',

        // Seguro Carro
        'seguroCarro'           => 'seguro_carro',
        'seguroCarroValor'      => 'seguro_carro_valor',
        'coberturaCarroValor'   => 'cobertura_carro_valor',

        // Seguro Terceiros
        'seguroTerceiros'       => 'seguro_terceiros',
        'seguroTerceirosValor'  => 'seguro_terceiros_valor',
        'coberturaTerceirosValor' => 'cobertura_terceiros_valor',

        // Promoção/Desconto
        'promocaoCodigo'        => 'promocao_codigo',
        'valorDesconto'         => 'valor_desconto',

        // Arrays/JSON
        'condutorAdicional'     => 'condutor_adicional',
        'arrayFiadores'         => 'array_fiadores',
        'arrayOutros'           => 'array_outros',
        'arrayAvaria'           => 'array_avaria',

        // KM
        'kmlExcedente'          => 'km_excedente',
        'kmlivreValor'          => 'km_livre_valor',
        'kmValor'               => 'km_valor',
        'kmcontroladoValor'     => 'km_controlado_valor',
        'kmcontroladoFranquia'  => 'km_controlado_franquia',

        // Entrega
        'entregueporNome'       => 'entregue_por_nome',
        'entregueporCNH'        => 'entregue_por_cnh',

        // Valores
        'diariaValor'           => 'diaria_valor',
        'minutoTolerancia'      => 'minuto_tolerancia',
        'valorTolerancia'       => 'valor_tolerancia',
        'totalFatura'           => 'total_fatura',
        'totalPagar'            => 'total_pagar',

        // Outros
        'opcoesTexto'           => 'opcoes_texto',
    ];

    public function up(): void
    {
        foreach ($this->columnRenames as $oldName => $newName) {
            if ($this->columnExists('locacoes', $oldName) && !$this->columnExists('locacoes', $newName)) {
                $this->renameColumnPreservingType('locacoes', $oldName, $newName);
            }
        }
    }

    public function down(): void
    {
        // Reverte na ordem inversa (PARA → DE)
        $reverseRenames = array_flip($this->columnRenames);

        foreach ($reverseRenames as $newName => $oldName) {
            if ($this->columnExists('locacoes', $newName) && !$this->columnExists('locacoes', $oldName)) {
                $this->renameColumnPreservingType('locacoes', $newName, $oldName);
            }
        }
    }
};
