<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Models\Localizar;
use App\Helpers\FilialHelper;

/**
 * Controller de Busca Global (Spotlight)
 *
 * Pesquisa em múltiplas entidades e retorna resultados agrupados
 */
class LocalizarController
{
    /**
     * Busca global em múltiplas entidades
     *
     * GET /api/localizar?q=termo
     */
    public function search(Request $request): void
    {
        try {
            $q = trim($request->query('q', ''));

            if (mb_strlen($q) < 2) {
                Response::json(['success' => true, 'data' => []]);
                return;
            }

            $searchTerm = '%' . $q . '%';
            [$filialWhere, $filialParams] = FilialHelper::whereFiliais('id_matriz_filial');
            [$locacaoFilialWhere, $locacaoFilialParams] = FilialHelper::whereLocacoes('l');
            [$contratoFilialWhere, $contratoFilialParams] = FilialHelper::whereContratos('c');

            $model = new Localizar();
            $results = [];

            // 1. Clientes
            if (Auth::can('clientes.visualizar')) {
                $rows = $model->buscarClientes($searchTerm, $filialWhere, $filialParams);
                if (!empty($rows)) {
                    $results[] = [
                        'type' => 'cliente',
                        'icon' => 'fas fa-users',
                        'label' => 'Clientes',
                        'items' => array_map(fn($row) => [
                            'id' => $row['id'],
                            'title' => $row['nome_rsocial'] ?? '',
                            'subtitle' => $row['cpf_cnpj'] ?? '',
                            'page' => '/pages/clientes/adicionar?id=' . $row['id'],
                            'tabName' => 'Clientes',
                            'tabIcon' => 'fas fa-users',
                        ], $rows),
                    ];
                }
            }

            // 2. Veículos
            if (Auth::can('veiculos.visualizar')) {
                $rows = $model->buscarVeiculos($searchTerm, $filialWhere, $filialParams);
                if (!empty($rows)) {
                    $results[] = [
                        'type' => 'veiculo',
                        'icon' => 'fas fa-car',
                        'label' => 'Veículos',
                        'items' => array_map(fn($row) => [
                            'id' => $row['id'],
                            'title' => trim(($row['placa'] ?? '') . ' - ' . ($row['marca'] ?? '') . ' ' . ($row['modelo'] ?? '')),
                            'subtitle' => 'Ano: ' . ($row['ano'] ?? ''),
                            'page' => '/pages/veiculos/adicionar?id=' . $row['id'],
                            'tabName' => 'Veículos',
                            'tabIcon' => 'fas fa-car',
                        ], $rows),
                    ];
                }
            }

            // 3. Locações
            if (Auth::can('locacoes.visualizar')) {
                $rows = $model->buscarLocacoes($searchTerm, $locacaoFilialWhere, $locacaoFilialParams);
                if (!empty($rows)) {
                    $results[] = [
                        'type' => 'locacao',
                        'icon' => 'fas fa-file-signature',
                        'label' => 'Locações',
                        'items' => array_map(fn($row) => [
                            'id' => $row['id'],
                            'title' => '#' . ($row['codigo'] ?? $row['id']),
                            'subtitle' => $row['cliente_nome'] ?? '',
                            'page' => '/pages/locacoes/adicionar?id=' . $row['id'],
                            'tabName' => 'Locações',
                            'tabIcon' => 'fas fa-file-signature',
                        ], $rows),
                    ];
                }
            }

            // 4. Contratos
            if (Auth::can('contratos.visualizar')) {
                $rows = $model->buscarContratos($searchTerm, $contratoFilialWhere, $contratoFilialParams);
                if (!empty($rows)) {
                    $results[] = [
                        'type' => 'contrato',
                        'icon' => 'fas fa-file-contract',
                        'label' => 'Contratos',
                        'items' => array_map(fn($row) => [
                            'id' => $row['id'],
                            'title' => '#' . ($row['codigo'] ?? $row['id']),
                            'subtitle' => $row['status'] ?? '',
                            'page' => '/pages/contratos/adicionar?id=' . $row['id'],
                            'tabName' => 'Contratos',
                            'tabIcon' => 'fas fa-file-contract',
                        ], $rows),
                    ];
                }
            }

            // 5. Funcionários
            if (Auth::can('funcionarios.visualizar')) {
                $rows = $model->buscarFuncionarios($searchTerm);
                if (!empty($rows)) {
                    $results[] = [
                        'type' => 'funcionario',
                        'icon' => 'fas fa-id-badge',
                        'label' => 'Funcionários',
                        'items' => array_map(fn($row) => [
                            'id' => $row['id'],
                            'title' => $row['nome'] ?? '',
                            'subtitle' => $row['funcao'] ?? $row['email'] ?? '',
                            'page' => '/pages/funcionarios/adicionar?id=' . $row['id'],
                            'tabName' => 'Funcionários',
                            'tabIcon' => 'fas fa-id-badge',
                        ], $rows),
                    ];
                }
            }

            Response::json(['success' => true, 'data' => $results]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao realizar busca'
            ], 500);
        }
    }
}
