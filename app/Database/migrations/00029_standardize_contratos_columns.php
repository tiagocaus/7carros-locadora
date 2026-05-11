<?php

/**
 * Migration 00029: Padronizar Colunas da Tabela contratos
 *
 * Renomeia 39 colunas para seguir o padrão de nomenclatura:
 * - FKs: id_[tabela]_[contexto] (ex: id_cliente, id_conta_bloqueio)
 * - Colunas: snake_case (ex: data_ini, valor_desconto)
 *
 * IMPORTANTE: Após executar esta migration, atualizar:
 * - Model: Contrato.php ($fillable, $casts)
 * - Controller: ContratoController.php
 * - Views: contratos/*.blade.php
 * - JavaScript: contratos.js
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
        'bloqueioConta'         => 'id_conta_bloqueio',
        'depositoConta'         => 'id_conta_deposito',
        'depositoFinanceiroID'  => 'id_financeiro_deposito',

        // Datas
        'dataIni'               => 'data_ini',
        'dataFim'               => 'data_fim',
        'dataRenovacao'         => 'data_renovacao',

        // Bloqueio
        'bloqueioTipo'          => 'bloqueio_tipo',
        'bloqueioValor'         => 'bloqueio_valor',
        'bloqueioPrazoDevolucao'=> 'bloqueio_prazo_devolucao',
        'bloqueioDataDevolucao' => 'bloqueio_data_devolucao',

        // Depósito
        'depositoTipo'          => 'deposito_tipo',
        'depositoValor'         => 'deposito_valor',

        // Valores
        'valorDesconto'         => 'valor_desconto',
        'totalFatura'           => 'total_fatura',
        'primeiroPagamento'     => 'primeiro_pagamento',
        'valorFaturasPaga'      => 'valor_faturas_paga',
        'totalPagar'            => 'total_pagar',

        // Seguro Carro
        'seguroCarro'           => 'seguro_carro',
        'seguroCarroValor'      => 'seguro_carro_valor',
        'coberturaCarroValor'   => 'cobertura_carro_valor',

        // Seguro Terceiros
        'seguroTerceiros'       => 'seguro_terceiros',
        'seguroTerceirosValor'  => 'seguro_terceiros_valor',
        'coberturaTerceirosValor' => 'cobertura_terceiros_valor',

        // Arrays/JSON
        'condutorAdicional'     => 'condutor_adicional',
        'arrayFiadores'         => 'array_fiadores',
        'arrayOutros'           => 'array_outros',

        // Odômetro
        'odometroIni'           => 'odometro_ini',
        'odometroFim'           => 'odometro_fim',
        'odometroArray'         => 'odometro_array',

        // Combustível
        'combustivelIni'        => 'combustivel_ini',
        'combustivelFim'        => 'combustivel_fim',

        // Outros
        'opcoesTexto'           => 'opcoes_texto',
        'autorenovacao'         => 'auto_renovacao',
    ];

    public function up(): void
    {
        foreach ($this->columnRenames as $oldName => $newName) {
            if ($this->columnExists('contratos', $oldName) && !$this->columnExists('contratos', $newName)) {
                $this->renameColumnPreservingType('contratos', $oldName, $newName);
            }
        }
    }

    public function down(): void
    {
        // Reverte na ordem inversa (PARA → DE)
        $reverseRenames = array_flip($this->columnRenames);

        foreach ($reverseRenames as $newName => $oldName) {
            if ($this->columnExists('contratos', $newName) && !$this->columnExists('contratos', $oldName)) {
                $this->renameColumnPreservingType('contratos', $newName, $oldName);
            }
        }
    }
};
