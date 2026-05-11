<?php

use App\Database\Migration;

/**
 * Migration: Migrar taxas e serviços para contratos_taxaseservicos
 *
 * Migra ~4.011 registros de taxas/serviços.
 * Trata 12 IDs de taxas inexistentes (usar id_taxa = NULL).
 * Formato opcoes_texto: "nome|dias|valor_unitario|valor_total"
 */
return new class extends Migration
{
    public function up(): void
    {
        $contratos = $this->db()
            ->table('contratos')
            ->select(['id', 'chave', 'opcoes', 'opcoes_texto'])
            ->whereRaw("opcoes != ''")
            ->whereRaw('opcoes IS NOT NULL')
            ->get();

        $migrados = 0;
        $erros = 0;
        $taxas_nulas = 0;

        foreach ($contratos as $contrato) {
            $ids = array_filter(explode(',', $contrato['opcoes'] ?? ''));
            $textos = array_filter(explode(',', $contrato['opcoes_texto'] ?? ''));

            // Criar mapa de textos por posição
            // Formato: "nome|dias|valor_unitario|valor_total"
            $textos_parsed = [];
            foreach ($textos as $texto) {
                $partes = explode('|', $texto);
                if (count($partes) >= 4) {
                    $textos_parsed[] = [
                        'nome' => trim($partes[0]),
                        'quantidade' => (int) $partes[1],
                        'valor_unitario' => (float) str_replace(',', '.', $partes[2]),
                        'valor_total' => (float) str_replace(',', '.', $partes[3]),
                    ];
                }
            }

            foreach ($ids as $index => $id_taxa) {
                $id_taxa = (int) trim($id_taxa);
                if ($id_taxa <= 0) {
                    continue;
                }

                $texto = $textos_parsed[$index] ?? null;

                // Verificar se já existe
                $existe = $this->db()
                    ->table('contratos_taxaseservicos')
                    ->where('id_contrato', '=', $contrato['id'])
                    ->where('id_taxa', '=', $id_taxa)
                    ->first();

                if ($existe) {
                    continue;
                }

                // Verificar se a taxa existe no banco
                $taxaExiste = $this->db()
                    ->table('taxaseservicos')
                    ->select(['id', 'nome', 'calculo'])
                    ->where('id', '=', $id_taxa)
                    ->first();

                // Se não tiver texto, buscar nome da taxa
                $nome = $texto['nome'] ?? null;
                $calculo = null;

                if ($taxaExiste) {
                    if (!$nome) {
                        $nome = $taxaExiste['nome'];
                    }
                    $calculo = $taxaExiste['calculo'] ?? null;
                } else {
                    // Taxa não existe mais - usar id_taxa = NULL
                    $taxas_nulas++;
                    if (!$nome) {
                        $nome = 'Taxa/Serviço #' . $id_taxa . ' (removida)';
                    }
                }

                $dados = [
                    'chave' => $contrato['chave'],
                    'id_contrato' => $contrato['id'],
                    'id_taxa' => $taxaExiste ? $id_taxa : null, // NULL se taxa não existe
                    'nome' => $nome,
                    'calculo' => $calculo,
                    'quantidade' => $texto['quantidade'] ?? 1,
                    'valor_unitario' => $texto['valor_unitario'] ?? 0,
                    'valor_total' => $texto['valor_total'] ?? 0,
                ];

                try {
                    $this->db()->table('contratos_taxaseservicos')->insert($dados);
                    $migrados++;
                } catch (\Exception $e) {
                    $erros++;
                    error_log("Erro migração taxa contrato {$contrato['id']}, taxa {$id_taxa}: " . $e->getMessage());
                }
            }
        }

        echo "Taxas/Serviços migrados: {$migrados}\n";
        if ($taxas_nulas > 0) {
            echo "Taxas com id_taxa = NULL (removidas): {$taxas_nulas}\n";
        }
        if ($erros > 0) {
            echo "Erros: {$erros}\n";
        }
    }

    public function down(): void
    {
        // Não é possível reverter - dados já migrados
    }
};
