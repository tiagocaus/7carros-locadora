<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Config\Planos;
use App\Models\Veiculo;
use App\Services\TenantProvisioningService;

/**
 * Controller para webhooks do WHMCS
 *
 * Recebe chamadas do WHMCS para gerenciar o ciclo de vida dos tenants.
 * Autenticação via WhmcsAuthMiddleware (Bearer token).
 */
class WhmcsController
{
    private TenantProvisioningService $service;

    public function __construct()
    {
        $this->service = new TenantProvisioningService();
    }

    /**
     * Cria um novo tenant
     *
     * POST /webhook/whmcs/criar
     */
    public function criar(Request $request): void
    {
        $campos = $request->only(['chave', 'nomeCompleto', 'email', 'usuario', 'senha', 'plano', 'razao_social', 'nome_fantasia', 'cpf_cnpj']);

        // Validação de campos obrigatórios
        $obrigatorios = ['chave', 'nomeCompleto', 'email', 'usuario', 'senha', 'plano'];
        $faltantes = $this->validarObrigatorios($campos, $obrigatorios);
        if (!empty($faltantes)) {
            Response::error('Campos obrigatórios ausentes: ' . implode(', ', $faltantes), $faltantes, 400);
            return;
        }

        // Validação do plano
        if (!Planos::existe($campos['plano'])) {
            Response::error("Plano inválido: {$campos['plano']}. Válidos: " . implode(', ', array_keys(Planos::PLANOS)), null, 400);
            return;
        }

        try {
            $resultado = $this->service->criarTenant($campos);
            $statusCode = $resultado['already_existed'] ? 200 : 201;
            Response::json($resultado, $statusCode);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), null, 409);
        } catch (\Exception $e) {
            error_log('[WHMCS] Erro ao criar tenant: ' . $e->getMessage());
            Response::error('Erro interno ao criar tenant', null, 500);
        }
    }

    /**
     * Suspende um tenant
     *
     * POST /webhook/whmcs/suspender
     */
    public function suspender(Request $request): void
    {
        $chave = $request->input('chave', '');

        if (empty($chave)) {
            Response::error('Campo obrigatório ausente: chave', ['chave'], 400);
            return;
        }

        try {
            $resultado = $this->service->suspenderTenant($chave);
            Response::json($resultado);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), null, 404);
        } catch (\Exception $e) {
            error_log('[WHMCS] Erro ao suspender tenant: ' . $e->getMessage());
            Response::error('Erro interno ao suspender tenant', null, 500);
        }
    }

    /**
     * Reativa um tenant
     *
     * POST /webhook/whmcs/reativar
     */
    public function reativar(Request $request): void
    {
        $chave = $request->input('chave', '');

        if (empty($chave)) {
            Response::error('Campo obrigatório ausente: chave', ['chave'], 400);
            return;
        }

        try {
            $resultado = $this->service->reativarTenant($chave);
            Response::json($resultado);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), null, 404);
        } catch (\Exception $e) {
            error_log('[WHMCS] Erro ao reativar tenant: ' . $e->getMessage());
            Response::error('Erro interno ao reativar tenant', null, 500);
        }
    }

    /**
     * Muda o plano de um tenant
     *
     * POST /webhook/whmcs/mudar-pacote
     */
    public function mudarPacote(Request $request): void
    {
        $campos = $request->only(['chave', 'plano']);

        $faltantes = $this->validarObrigatorios($campos, ['chave', 'plano']);
        if (!empty($faltantes)) {
            Response::error('Campos obrigatórios ausentes: ' . implode(', ', $faltantes), $faltantes, 400);
            return;
        }

        if (!Planos::existe($campos['plano'])) {
            Response::error("Plano inválido: {$campos['plano']}. Válidos: " . implode(', ', array_keys(Planos::PLANOS)), null, 400);
            return;
        }

        try {
            $resultado = $this->service->mudarPacote($campos['chave'], $campos['plano']);
            Response::json($resultado);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), null, 404);
        } catch (\Exception $e) {
            error_log('[WHMCS] Erro ao mudar pacote: ' . $e->getMessage());
            Response::error('Erro interno ao mudar pacote', null, 500);
        }
    }

    /**
     * Atualiza a senha de um funcionário
     *
     * POST /webhook/whmcs/atualizar-senha
     */
    public function atualizarSenha(Request $request): void
    {
        $campos = $request->only(['chave', 'usuario', 'senha']);

        $faltantes = $this->validarObrigatorios($campos, ['chave', 'usuario', 'senha']);
        if (!empty($faltantes)) {
            Response::error('Campos obrigatórios ausentes: ' . implode(', ', $faltantes), $faltantes, 400);
            return;
        }

        try {
            $resultado = $this->service->atualizarSenha($campos['chave'], $campos['usuario'], $campos['senha']);
            Response::json($resultado);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), null, 404);
        } catch (\Exception $e) {
            error_log('[WHMCS] Erro ao atualizar senha: ' . $e->getMessage());
            Response::error('Erro interno ao atualizar senha', null, 500);
        }
    }

    /**
     * Termina/encerra um tenant
     *
     * POST /webhook/whmcs/terminar
     */
    public function terminar(Request $request): void
    {
        $chave = $request->input('chave', '');

        if (empty($chave)) {
            Response::error('Campo obrigatório ausente: chave', ['chave'], 400);
            return;
        }

        try {
            $resultado = $this->service->terminarTenant($chave);
            Response::json($resultado);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), null, 404);
        } catch (\Exception $e) {
            error_log('[WHMCS] Erro ao terminar tenant: ' . $e->getMessage());
            Response::error('Erro interno ao terminar tenant', null, 500);
        }
    }

    /**
     * Retorna totais de veiculos por disponibilidade para o WHMCS
     *
     * POST /webhook/whmcs/veiculos-disponibilidade
     */
    public function veiculosDisponibilidade(Request $request): void
    {
        $chave = trim((string) $request->input('chave', ''));

        if ($chave === '') {
            Response::error('Campo obrigatório ausente: chave', ['chave'], 400);
            return;
        }

        try {
            Response::json([
                'success' => true,
                'chave' => $chave,
                'data' => (new Veiculo())->resumoDisponibilidadeParaWhmcs($chave),
            ]);
        } catch (\Exception $e) {
            error_log('[WHMCS] Erro ao consultar disponibilidade de veiculos: ' . $e->getMessage());
            Response::error('Erro interno ao consultar disponibilidade de veiculos', null, 500);
        }
    }

    /**
     * Valida campos obrigatórios e retorna lista dos faltantes
     */
    private function validarObrigatorios(array $campos, array $obrigatorios): array
    {
        $faltantes = [];
        foreach ($obrigatorios as $campo) {
            if (empty($campos[$campo] ?? '')) {
                $faltantes[] = $campo;
            }
        }
        return $faltantes;
    }
}
