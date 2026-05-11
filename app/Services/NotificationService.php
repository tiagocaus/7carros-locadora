<?php

namespace App\Services;

use App\Core\Cache;
use App\Models\Manutencao;
use App\Models\Financeiro;
use App\Models\VeiculoEncargo;
use App\Models\Cliente;

/**
 * Service para contagem de notificacoes do sistema
 *
 * Retorna os contadores exibidos no dropdown de notificacoes do navbar.
 * Usa cache Redis com TTL de 5 minutos para renderizacao server-side.
 */
class NotificationService
{
    private const CACHE_KEY = 'notification_counts';
    private const CACHE_TTL = 300; // 5 minutos

    /**
     * Retorna contadores de notificacoes
     *
     * @param bool $fresh Se true, ignora cache e busca direto no BD
     * @return array Contadores por categoria + total
     */
    public function getCounts(bool $fresh = false): array
    {
        if (!$fresh) {
            $cached = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
                return $this->fetchCounts();
            });

            if ($cached !== null) {
                return $cached;
            }
        }

        return $this->fetchCounts();
    }

    /**
     * Busca contadores diretamente no banco de dados
     */
    private function fetchCounts(): array
    {
        $manutencoes = (new Manutencao())->contarAbertas();
        $faturasVencidas = (new Financeiro())->contarVencidas();
        $licenciamento = (new VeiculoEncargo())->contarVencidosOuProximos();
        $cnhVencidas = (new Cliente())->contarCnhVencidas();

        $tarefas = 0;
        $problemas = 0;

        return [
            'manutencoes' => $manutencoes,
            'tarefas' => $tarefas,
            'faturas_vencidas' => $faturasVencidas,
            'licenciamento' => $licenciamento,
            'cnh_vencidas' => $cnhVencidas,
            'problemas' => $problemas,
            'total' => $manutencoes + $tarefas + $faturasVencidas + $licenciamento + $cnhVencidas + $problemas,
        ];
    }

    /**
     * Lista itens de notificacao por categoria, em formato unificado.
     *
     * Categorias suportadas: 'all', 'manutencao', 'fatura', 'licenciamento', 'cnh'.
     * Quando 'all', agrega as 4 categorias e ordena por data desc.
     *
     * @return array{items: array<int, array>, total: int, page: int, perPage: int, categoria: string}
     */
    public function getList(string $categoria = 'all', int $page = 1, int $perPage = 25): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $items = [];
        $total = 0;

        switch ($categoria) {
            case 'manutencao':
                $rows = (new Manutencao())->listarParaNotificacoes($perPage, $offset);
                $total = (new Manutencao())->contarAbertas();
                $items = array_map([$this, 'mapManutencao'], $rows);
                break;

            case 'fatura':
                $rows = (new Financeiro())->listarParaNotificacoes($perPage, $offset);
                $total = (new Financeiro())->contarVencidas();
                $items = array_map([$this, 'mapFatura'], $rows);
                break;

            case 'licenciamento':
                $rows = (new VeiculoEncargo())->listarParaNotificacoes($perPage, $offset);
                $total = (new VeiculoEncargo())->contarVencidosOuProximos();
                $items = array_map([$this, 'mapLicenciamento'], $rows);
                break;

            case 'cnh':
                $rows = (new Cliente())->listarCnhVencidasParaNotificacoes($perPage, $offset);
                $total = (new Cliente())->contarCnhVencidas();
                $items = array_map([$this, 'mapCnh'], $rows);
                break;

            case 'tarefa':
            case 'problema':
                // Categorias ainda nao implementadas no backend — retornam lista vazia.
                // A tela exibe estado "Nenhuma notificacao por aqui." sem quebrar.
                $items = [];
                $total = 0;
                break;

            case 'all':
            default:
                // Agrega TUDO em memoria, ordena por data desc, depois pagina.
                // O volume aqui é o total de notificacoes ativas — aceitavel para um app deste porte.
                $todos = array_merge(
                    array_map([$this, 'mapManutencao'], (new Manutencao())->listarParaNotificacoes(500, 0)),
                    array_map([$this, 'mapFatura'], (new Financeiro())->listarParaNotificacoes(500, 0)),
                    array_map([$this, 'mapLicenciamento'], (new VeiculoEncargo())->listarParaNotificacoes(500, 0)),
                    array_map([$this, 'mapCnh'], (new Cliente())->listarCnhVencidasParaNotificacoes(500, 0)),
                );
                usort($todos, fn($a, $b) => strcmp($b['data'] ?? '', $a['data'] ?? ''));
                $total = count($todos);
                $items = array_slice($todos, $offset, $perPage);
                break;
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'categoria' => $categoria,
        ];
    }

    private function mapManutencao(array $r): array
    {
        $veiculo = trim(($r['veiculo_placa'] ?? '') . ' — ' . ($r['veiculo_modelo'] ?? ''), ' —');
        return [
            'tipo' => 'manutencao',
            'id' => (int) $r['id'],
            'titulo' => 'OS ' . ($r['os'] ?? '#' . $r['id']),
            'detalhe' => $veiculo ?: '-',
            'extra' => ['status' => $r['status'] ?? null],
            'data' => $r['data_enviado'] ?? null,
            'link' => '/pages/manutencoes', // sem rota de edit dedicada
        ];
    }

    private function mapFatura(array $r): array
    {
        $tipo = $r['tipo'] ?? 'D';
        $contato = $tipo === 'R' ? ($r['cliente_nome'] ?? '-') : ($r['fornecedor_nome'] ?? '-');
        return [
            'tipo' => 'fatura',
            'id' => (int) $r['id'],
            'titulo' => '#' . ($r['codigo'] ?? $r['id']),
            'detalhe' => $contato,
            'extra' => [
                'sequencia' => $r['sequencia'] ?? null,
                'tipo_lancamento' => $tipo,
                'descricao' => $r['descricao'] ?? '',
                'valor' => (float) ($r['valor_total'] ?? 0),
            ],
            'data' => $r['data_venci'] ?? null,
            'link' => '/pages/financeiro/adicionar?id=' . (int) $r['id'],
        ];
    }

    private function mapLicenciamento(array $r): array
    {
        $veiculo = trim(($r['veiculo_placa'] ?? '') . ' — ' . ($r['veiculo_modelo'] ?? ''), ' —');
        return [
            'tipo' => 'licenciamento',
            'id' => (int) $r['id'],
            'titulo' => $r['nome'] ?? '-',
            'detalhe' => $veiculo ?: '-',
            'extra' => ['valor' => (float) ($r['valor'] ?? 0)],
            'data' => $r['vencimento'] ?? null,
            'link' => $r['id_veiculo'] ? ('/pages/veiculos/editar/' . (int) $r['id_veiculo']) : '/pages/veiculos',
        ];
    }

    private function mapCnh(array $r): array
    {
        return [
            'tipo' => 'cnh',
            'id' => (int) $r['id'],
            'titulo' => $r['nome_rsocial'] ?? '-',
            'detalhe' => 'CNH ' . ($r['cnh_numero'] ?? '-') . ' (' . ($r['cnh_categoria'] ?? '-') . ')',
            'extra' => ['cpf_cnpj' => $r['cpf_cnpj'] ?? ''],
            'data' => $r['cnh_validade'] ?? null,
            'link' => '/pages/clientes/adicionar?id=' . (int) $r['id'],
        ];
    }
}
