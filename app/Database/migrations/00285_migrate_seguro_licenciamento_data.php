<?php

/**
 * Migration 00285: Migrar dados de seguro e licenciamento para veiculos_encargos
 *
 * Migra os dados existentes das colunas fixas (companhia_seguro, tipo_seguro,
 * venc_seguro, venc_licenciamento) da tabela veiculos para a nova tabela
 * veiculos_encargos.
 *
 * Dados migrados:
 * - Seguro: companhia_seguro + tipo_seguro → descricao, venc_seguro → vencimento
 * - Licenciamento: venc_licenciamento → vencimento
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Migrar dados de Seguro
        $veiculosComSeguro = $this->db()
            ->table('veiculos')
            ->select(['id', 'chave', 'companhia_seguro', 'tipo_seguro', 'venc_seguro'])
            ->withoutChave()
            ->whereNotNull('companhia_seguro')
            ->whereRaw("companhia_seguro != ''")
            ->get();

        foreach ($veiculosComSeguro as $veiculo) {
            $descricao = trim($veiculo['companhia_seguro']);
            if (!empty($veiculo['tipo_seguro'])) {
                $descricao .= ' - ' . trim($veiculo['tipo_seguro']);
            }

            $vencimento = null;
            if (!empty($veiculo['venc_seguro']) && $veiculo['venc_seguro'] !== '0000-00-00') {
                $vencimento = $veiculo['venc_seguro'];
            }

            $this->db()->table('veiculos_encargos')->insert([
                'chave' => $veiculo['chave'],
                'id_veiculo' => $veiculo['id'],
                'nome' => 'Seguro',
                'descricao' => $descricao,
                'valor' => null,
                'vencimento' => $vencimento,
                'recorrencia' => 'anual',
                'dias_antecedencia' => 30,
                'ativo' => 1,
            ]);
        }

        // Migrar dados de Licenciamento
        $veiculosComLicenciamento = $this->db()
            ->table('veiculos')
            ->select(['id', 'chave', 'venc_licenciamento'])
            ->withoutChave()
            ->whereNotNull('venc_licenciamento')
            ->whereRaw("venc_licenciamento != '0000-00-00'")
            ->get();

        foreach ($veiculosComLicenciamento as $veiculo) {
            $this->db()->table('veiculos_encargos')->insert([
                'chave' => $veiculo['chave'],
                'id_veiculo' => $veiculo['id'],
                'nome' => 'Licenciamento',
                'descricao' => null,
                'valor' => null,
                'vencimento' => $veiculo['venc_licenciamento'],
                'recorrencia' => 'anual',
                'dias_antecedencia' => 30,
                'ativo' => 1,
            ]);
        }
    }

    public function down(): void
    {
        // Nao reverte automaticamente - dados originais permanecem na tabela veiculos
        // ate a migration 00286 dropar as colunas
    }
};
