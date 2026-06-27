<?php

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $total = 0;
        $total += $this->normalizarTabela('checklist_modelos', ['questoes', 'vistoria']);
        $total += $this->normalizarTabela('checklist', ['questoes', 'vistoria']);

        echo "  JSONs de checklist padronizados para name: {$total}\n";
    }

    public function down(): void
    {
        // Migration de padronizacao de dados: nao revertemos para evitar
        // recriar campos legados removidos do formato canonico.
    }

    /**
     * @param array<int,string> $campos
     */
    private function normalizarTabela(string $tabela, array $campos): int
    {
        if (!$this->tableExists($tabela)) {
            echo "  [SKIP] Tabela {$tabela} ausente\n";
            return 0;
        }

        $colunas = ['id'];
        foreach ($campos as $campo) {
            if ($this->columnExists($tabela, $campo)) {
                $colunas[] = $campo;
            }
        }

        if (count($colunas) === 1) {
            return 0;
        }

        $rows = $this->db()
            ->table($tabela)
            ->withoutChave()
            ->select($colunas)
            ->get();

        $alterados = 0;
        foreach ($rows as $row) {
            $update = [];

            foreach ($campos as $campo) {
                if (!array_key_exists($campo, $row)) {
                    continue;
                }

                $json = $row[$campo] ?? null;
                if ($json === null || trim((string) $json) === '') {
                    continue;
                }

                $dados = json_decode((string) $json, true);
                if (!is_array($dados)) {
                    continue;
                }

                $normalizado = $this->normalizarItens($dados);
                if ($normalizado !== $dados) {
                    $update[$campo] = json_encode($normalizado, JSON_UNESCAPED_UNICODE);
                }
            }

            if (empty($update)) {
                continue;
            }

            $this->db()
                ->table($tabela)
                ->withoutChave()
                ->where('id', '=', (int) $row['id'])
                ->update($update);

            $alterados++;
        }

        return $alterados;
    }

    private function normalizarItens(array $itens): array
    {
        foreach ($itens as &$item) {
            if (!is_array($item)) {
                continue;
            }

            if (!isset($item['name']) || trim((string) $item['name']) === '') {
                foreach (['content', 'pergunta', 'label'] as $campoLegado) {
                    if (isset($item[$campoLegado]) && trim((string) $item[$campoLegado]) !== '') {
                        $item['name'] = trim((string) $item[$campoLegado]);
                        break;
                    }
                }
            }

            unset($item['content'], $item['pergunta'], $item['label']);

            if (isset($item['children']) && is_array($item['children'])) {
                $item['children'] = $this->normalizarItens($item['children']);
            }
        }
        unset($item);

        return $itens;
    }
};
