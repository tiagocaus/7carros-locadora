<?php

use App\Database\Migration;

/**
 * Migration: Migrar dados de taxas de locacoes para locacoes_taxaseservicos
 *
 * Migra ~35k registros do formato legado (opcoes + opcoes_texto) para a tabela normalizada.
 *
 * Formato legado:
 * - opcoes: IDs separados por vírgula ("1,2,80")
 * - opcoes_texto: "Nome|qtd|valor_unitario|valor_total" separados por vírgula
 *
 * Para registros com opcoes mas SEM opcoes_texto (17 registros):
 * busca nome da taxa na tabela taxaseservicos.
 */
return new class extends Migration
{
    public function up(): void
    {
        $batchSize = 2000;
        $offset = 0;
        $migrados = 0;
        $erros = 0;

        // Cache de taxas para lookup rápido
        $cacheTaxas = [];
        // Cache de IDs de taxa que existem (para evitar FK constraint)
        $taxaExiste = [];

        do {
            $locacoes = $this->db()
                ->table('locacoes')
                ->select(['id', 'chave', 'opcoes', 'opcoes_texto'])
                ->whereRaw("opcoes IS NOT NULL AND opcoes != ''")
                ->orderBy('id', 'ASC')
                ->limit($batchSize)
                ->offset($offset)
                ->get();

            foreach ($locacoes as $loc) {
                // Verificar se já migrou (idempotência)
                $existe = $this->db()
                    ->table('locacoes_taxaseservicos')
                    ->where('id_locacao', '=', $loc['id'])
                    ->first();

                if ($existe) {
                    continue;
                }

                $ids = array_filter(array_map('trim', explode(',', $loc['opcoes'])));
                $textos = [];

                // Parsear opcoes_texto se presente
                if (!empty($loc['opcoes_texto'])) {
                    $partes = explode(',', $loc['opcoes_texto']);
                    foreach ($partes as $parte) {
                        $campos = explode('|', $parte);
                        if (count($campos) >= 4) {
                            $qtd = $campos[1];
                            $valorUnit = $campos[2];
                            $valorTotal = $campos[3];

                            // Tratar "NaN" e valores inválidos
                            if (!is_numeric($qtd) || $qtd === 'NaN') {
                                $qtd = 1;
                            }
                            if (!is_numeric($valorUnit) || $valorUnit === 'NaN') {
                                $valorUnit = 0;
                            }
                            if (!is_numeric($valorTotal) || $valorTotal === 'NaN') {
                                $valorTotal = (float) $qtd * (float) $valorUnit;
                            }

                            $textos[] = [
                                'nome' => trim($campos[0]),
                                'quantidade' => max(1, (int) $qtd),
                                'valor_unitario' => (float) $valorUnit,
                                'valor_total' => (float) $valorTotal,
                            ];
                        }
                    }
                }

                // Inserir taxas
                foreach ($ids as $index => $idTaxa) {
                    $idTaxa = (int) trim($idTaxa);
                    if ($idTaxa <= 0) {
                        continue;
                    }

                    // Buscar dados da taxa original para base_calculo e tipo_valor
                    if (!isset($cacheTaxas[$idTaxa])) {
                        $cacheTaxas[$idTaxa] = $this->db()
                            ->table('taxaseservicos')
                            ->select(['id', 'nome', 'base_calculo', 'tipo_valor', 'valor'])
                            ->withoutChave()
                            ->where('id', '=', $idTaxa)
                            ->first();
                        $taxaExiste[$idTaxa] = !empty($cacheTaxas[$idTaxa]);
                    }
                    $taxaOriginal = $cacheTaxas[$idTaxa];

                    // id_taxa = NULL se a taxa foi deletada (evita FK constraint)
                    $idTaxaFK = $taxaExiste[$idTaxa] ? $idTaxa : null;

                    // Usar dados do texto parseado se disponível, senão fallback para taxa original
                    if (isset($textos[$index])) {
                        $dados = [
                            'chave' => $loc['chave'],
                            'id_locacao' => $loc['id'],
                            'id_taxa' => $idTaxaFK,
                            'base_calculo' => $taxaOriginal['base_calculo'] ?? 'FIX',
                            'tipo_valor' => $taxaOriginal['tipo_valor'] ?? 'MON',
                            'nome' => $textos[$index]['nome'],
                            'quantidade' => $textos[$index]['quantidade'],
                            'valor_unitario' => $textos[$index]['valor_unitario'],
                            'valor_total' => $textos[$index]['valor_total'],
                        ];
                    } elseif ($taxaOriginal) {
                        // Sem texto, usar dados da taxa original
                        $dados = [
                            'chave' => $loc['chave'],
                            'id_locacao' => $loc['id'],
                            'id_taxa' => $idTaxaFK,
                            'base_calculo' => $taxaOriginal['base_calculo'] ?? 'FIX',
                            'tipo_valor' => $taxaOriginal['tipo_valor'] ?? 'MON',
                            'nome' => $taxaOriginal['nome'] ?? 'Taxa #' . $idTaxa,
                            'quantidade' => 1,
                            'valor_unitario' => (float) ($taxaOriginal['valor'] ?? 0),
                            'valor_total' => (float) ($taxaOriginal['valor'] ?? 0),
                        ];
                    } else {
                        // Taxa deletada - salva com id_taxa NULL e nome do texto ou genérico
                        $dados = [
                            'chave' => $loc['chave'],
                            'id_locacao' => $loc['id'],
                            'id_taxa' => null,
                            'base_calculo' => 'FIX',
                            'tipo_valor' => 'MON',
                            'nome' => 'Taxa removida #' . $idTaxa,
                            'quantidade' => 1,
                            'valor_unitario' => 0,
                            'valor_total' => 0,
                        ];
                    }

                    try {
                        $this->db()->table('locacoes_taxaseservicos')->insert($dados);
                        $migrados++;
                    } catch (\Exception $e) {
                        $erros++;
                        error_log("Erro migração locação taxa {$loc['id']}: " . $e->getMessage());
                    }
                }
            }

            $offset += $batchSize;
            echo "Processados: {$offset} (taxas migradas: {$migrados})\n";

        } while (count($locacoes) === $batchSize);

        echo "Total taxas migradas para locacoes_taxaseservicos: {$migrados}\n";
        if ($erros > 0) {
            echo "Erros: {$erros}\n";
        }
    }

    public function down(): void
    {
        // Não reverter automaticamente - dados permanecem na tabela original
    }
};
