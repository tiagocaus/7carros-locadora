<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\FilialHelper;
use App\Models\Contrato;
use App\Models\ContratoVeiculo;
use App\Models\Locacao;
use App\Models\LocacaoVeiculo;
use App\Models\Sinistro;
use App\Services\AuditLogService;
use App\Services\SinistroService;

class SinistrosController
{
    public function index(Request $request): void
    {
        try {
            $vinculo = strtolower((string) $request->query('vinculo', ''));
            $idVinculo = (int) $request->query('id_vinculo', 0);
            $parent = $this->autorizarVinculo($vinculo, $idVinculo, false);
            $veiculos = $vinculo === 'contrato'
                ? (new ContratoVeiculo())->listarPorContrato($idVinculo)
                : (new LocacaoVeiculo())->listarPorLocacao($idVinculo);

            Response::json([
                'success' => true,
                'data' => (new Sinistro())->listarPorVinculo($vinculo, $idVinculo),
                'veiculos' => array_values(array_filter(array_map(static fn(array $item): ?array => !empty($item['id_veiculo']) ? [
                    'id' => (int) $item['id_veiculo'],
                    'placa' => $item['veiculo_placa'] ?? '',
                    'marca' => $item['veiculo_marca'] ?? '',
                    'modelo' => $item['veiculo_modelo'] ?? '',
                ] : null, $veiculos))),
                'parent' => ['id' => (int) $parent['id'], 'codigo' => $parent['codigo'] ?? ''],
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            error_log('[Sinistros] Erro ao carregar: ' . $e->getMessage());
            Response::json(['success' => false, 'message' => 'Erro ao carregar sinistros'], 500);
        }
    }

    public function store(Request $request): void
    {
        try {
            $dados = $request->all();
            $vinculo = strtolower((string) ($dados['vinculo'] ?? ''));
            $parent = $this->autorizarVinculo($vinculo, (int) ($dados['id_vinculo'] ?? 0), true);
            if (!empty($dados['gerar_cobranca']) && !Auth::can('financeiro.criar')) {
                Response::json(['success' => false, 'message' => 'Sem permissao para gerar cobranca'], 403);
                return;
            }

            $id = (new SinistroService())->criar($vinculo, $parent, $dados, (string) Auth::chave(), (int) Auth::id());
            AuditLogService::registrar(($_SESSION['user_name'] ?? 'Sistema') . ', registrou sinistro [' . ($parent['codigo'] ?? $id) . ']');
            Response::json(['success' => true, 'message' => 'Sinistro registrado com sucesso', 'data' => ['id' => $id]], 201);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            error_log('[Sinistros] Erro ao registrar: ' . $e->getMessage());
            Response::json(['success' => false, 'message' => 'Erro ao registrar sinistro'], 500);
        }
    }

    public function update(Request $request, int $id): void
    {
        try {
            $sinistro = (new Sinistro())->buscarPorId($id);
            if (!$sinistro) {
                Response::json(['success' => false, 'message' => 'Sinistro nao encontrado'], 404);
                return;
            }
            [$vinculo, $idVinculo] = $this->vinculoDoSinistro($sinistro);
            $parent = $this->autorizarVinculo($vinculo, $idVinculo, true);
            (new SinistroService())->atualizar($id, $vinculo, $parent, $request->all());
            AuditLogService::registrar(($_SESSION['user_name'] ?? 'Sistema') . ', atualizou sinistro #' . $id);
            Response::json(['success' => true, 'message' => 'Sinistro atualizado com sucesso']);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            error_log('[Sinistros] Erro ao atualizar #' . $id . ': ' . $e->getMessage());
            Response::json(['success' => false, 'message' => 'Erro ao atualizar sinistro'], 500);
        }
    }

    public function gerarCobranca(Request $request, int $id): void
    {
        try {
            if (!Auth::can('financeiro.criar')) {
                Response::json(['success' => false, 'message' => 'Sem permissao para gerar cobranca'], 403);
                return;
            }
            $sinistro = (new Sinistro())->buscarPorId($id);
            if (!$sinistro) {
                Response::json(['success' => false, 'message' => 'Sinistro nao encontrado'], 404);
                return;
            }
            [$vinculo, $idVinculo] = $this->vinculoDoSinistro($sinistro);
            $parent = $this->autorizarVinculo($vinculo, $idVinculo, true);
            $idFinanceiro = (new SinistroService())->gerarCobranca($sinistro, $vinculo, $parent, $request->all(), (string) Auth::chave());
            AuditLogService::registrar(($_SESSION['user_name'] ?? 'Sistema') . ', gerou cobranca para sinistro #' . $id);
            Response::json(['success' => true, 'message' => 'Cobranca gerada com sucesso', 'data' => ['id_financeiro' => $idFinanceiro]]);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            error_log('[Sinistros] Erro ao gerar cobranca #' . $id . ': ' . $e->getMessage());
            Response::json(['success' => false, 'message' => 'Erro ao gerar cobranca'], 500);
        }
    }

    public function destroy(Request $request, int $id): void
    {
        try {
            $sinistro = (new Sinistro())->buscarPorId($id);
            if (!$sinistro) {
                Response::json(['success' => false, 'message' => 'Sinistro nao encontrado'], 404);
                return;
            }

            [$vinculo, $idVinculo] = $this->vinculoDoSinistro($sinistro);
            $parent = $this->autorizarVinculo($vinculo, $idVinculo, true);
            if (!empty($sinistro['id_financeiro']) && !Auth::can('financeiro.excluir')) {
                Response::json([
                    'success' => false,
                    'message' => 'Sem permissao para excluir a cobranca vinculada',
                ], 403);
                return;
            }

            $resultado = (new SinistroService())->excluir(
                $id,
                $vinculo,
                $parent,
                (string) ($_SESSION['user_name'] ?? 'Sistema'),
                Auth::can('financeiro.excluir')
            );
            Response::json([
                'success' => true,
                'message' => !empty($resultado['id_financeiro'])
                    ? 'Sinistro e cobranca vinculada excluidos com sucesso'
                    : 'Sinistro excluido com sucesso',
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            error_log('[Sinistros] Erro ao excluir #' . $id . ': ' . $e->getMessage());
            Response::json(['success' => false, 'message' => 'Erro ao excluir sinistro'], 500);
        }
    }

    private function autorizarVinculo(string $vinculo, int $id, bool $editar): array
    {
        if (!in_array($vinculo, ['contrato', 'locacao'], true) || $id <= 0) {
            throw new \InvalidArgumentException('Vinculo de sinistro invalido');
        }
        $permissao = $vinculo === 'contrato'
            ? 'contratos.' . ($editar ? 'editar' : 'visualizar')
            : 'locacoes.' . ($editar ? 'editar' : 'visualizar');
        if (!Auth::can($permissao)) {
            throw new \InvalidArgumentException('Acesso negado');
        }

        $parent = $vinculo === 'contrato'
            ? (new Contrato())->buscarPorId($id)
            : (new Locacao())->buscarPorId($id);
        if (!$parent) {
            throw new \InvalidArgumentException($vinculo === 'contrato' ? 'Contrato nao encontrado' : 'Locacao nao encontrada');
        }

        $filiais = array_filter([
            $parent['id_matriz_filial_retirada'] ?? null,
            $parent['id_matriz_filial_devolucao'] ?? null,
        ]);
        if ($filiais && !array_filter($filiais, static fn($filial): bool => FilialHelper::temAcessoFilial($filial))) {
            throw new \InvalidArgumentException('Acesso negado a filial');
        }
        return $parent;
    }

    private function vinculoDoSinistro(array $sinistro): array
    {
        return !empty($sinistro['id_contrato'])
            ? ['contrato', (int) $sinistro['id_contrato']]
            : ['locacao', (int) $sinistro['id_locacao']];
    }
}
