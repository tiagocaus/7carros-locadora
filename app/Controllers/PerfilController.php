<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Funcionario;
use App\Models\Role;
use App\Models\MatrizFilial;
use App\Helpers\FileHelper;
use App\Services\AuditLogService;

/**
 * Controller de Perfil do Usuário
 *
 * Permite que o usuário logado visualize e edite seus próprios dados
 * Campos administrativos (role, status, filiais) NÃO são editáveis
 */
class PerfilController
{
    /**
     * Exibe dados do perfil do usuário logado
     *
     * GET /api/perfil
     */
    public function show(Request $request): void
    {
        try {
            $userId = Auth::id();

            if (!$userId) {
                Response::json([
                    'success' => false,
                    'message' => 'Usuário não autenticado'
                ], 401);
                return;
            }

            $funcionarioModel = new Funcionario();
            $funcionario = $funcionarioModel->buscarPorIdComFiliais($userId);

            if (!$funcionario) {
                Response::json([
                    'success' => false,
                    'message' => 'Usuário não encontrado'
                ], 404);
                return;
            }

            // Remover campos sensíveis
            unset($funcionario['senha']);

            // Adicionar foto_url
            $funcionario['foto_url'] = !empty($funcionario['foto'])
                ? FileHelper::url($funcionario['foto'], Auth::chave())
                : '';

            // Buscar nome da role se existir
            if (!empty($funcionario['id_role'])) {
                $roleModel = new Role();
                $role = $roleModel->buscarPorId($funcionario['id_role'], Auth::chave());
                $funcionario['role_nome'] = $role['name'] ?? '';
            } else {
                $funcionario['role_nome'] = $funcionario['funcao'] ?? '';
            }

            // Buscar nome da filial se existir
            if (!empty($funcionario['id_matriz_filial'])) {
                $filialModel = new MatrizFilial();
                $filial = $filialModel->buscarPorId($funcionario['id_matriz_filial']);
                $funcionario['filial_nome'] = $filial['razao_social'] ?? $filial['nome_fantasia'] ?? '';
            } else {
                $funcionario['filial_nome'] = '';
            }

            Response::json([
                'success' => true,
                'data' => $funcionario
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao carregar perfil: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza dados do perfil do usuário logado
     *
     * POST /perfil/atualizar
     *
     * Campos editáveis: email, tel_fixo, tel_cel, foto
     * Campos NÃO editáveis: role, status, filiais, usuario, cpf, nome
     */
    public function update(Request $request): void
    {
        try {
            // IMPORTANTE: Sempre usar Auth::id() - nunca aceitar ID do request
            $userId = Auth::id();

            if (!$userId) {
                Response::json([
                    'success' => false,
                    'message' => 'Usuário não autenticado'
                ], 401);
                return;
            }

            $funcionarioModel = new Funcionario();
            $funcionarioExistente = $funcionarioModel->buscarPorId($userId);

            if (!$funcionarioExistente) {
                Response::json([
                    'success' => false,
                    'message' => 'Usuário não encontrado'
                ], 404);
                return;
            }

            // Lista branca de campos editáveis pelo perfil
            $dados = [
                'email' => $request->input('email'),
                'tel_fixo' => $request->input('tel_fixo'),
                'tel_cel' => $request->input('tel_cel'),
            ];

            // Validar email
            if (empty($dados['email'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Email é obrigatório'
                ], 400);
                return;
            }

            // Validar formato de email
            if (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
                Response::json([
                    'success' => false,
                    'message' => 'Email inválido'
                ], 400);
                return;
            }

            // Processar upload de foto usando FileHelper
            $fotoBase64 = $request->input('foto_base64', '');
            if (!empty($fotoBase64)) {
                // Apagar foto antiga
                if (!empty($funcionarioExistente['foto'])) {
                    FileHelper::delete($funcionarioExistente['foto'], Auth::chave());
                }
                $filename = FileHelper::save($fotoBase64, 'foto_funcionario');
                if ($filename) {
                    $dados['foto'] = $filename;
                }
            }

            // Remover campos null
            $dados = array_filter($dados, function ($value) {
                return $value !== null;
            });

            $funcionarioModel->atualizar($userId, $dados);

            // Atualizar dados na sessão
            Session::set('user_email', $dados['email']);
            if (isset($dados['foto'])) {
                Session::set('user_foto', $dados['foto']);
            }

            // Log de auditoria
            AuditLogService::registrar(
                ($funcionarioExistente['nome'] ?? 'Sistema') . " atualizou seu próprio perfil"
            );

            Response::json([
                'success' => true,
                'message' => 'Perfil atualizado com sucesso',
                'data' => [
                    'foto_url' => isset($dados['foto'])
                        ? FileHelper::url($dados['foto'], Auth::chave())
                        : null
                ]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar perfil: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Altera senha do usuário logado
     *
     * POST /perfil/alterar-senha
     *
     * Requer: senha_atual, nova_senha, confirmar_senha
     */
    public function alterarSenha(Request $request): void
    {
        try {
            // IMPORTANTE: Sempre usar Auth::id() - nunca aceitar ID do request
            $userId = Auth::id();

            if (!$userId) {
                Response::json([
                    'success' => false,
                    'message' => 'Usuário não autenticado'
                ], 401);
                return;
            }

            $senhaAtual = $request->input('senha_atual', '');
            $novaSenha = $request->input('nova_senha', '');
            $confirmarSenha = $request->input('confirmar_senha', '');

            // Validações
            if (empty($senhaAtual)) {
                Response::json([
                    'success' => false,
                    'message' => 'Senha atual é obrigatória'
                ], 400);
                return;
            }

            if (empty($novaSenha)) {
                Response::json([
                    'success' => false,
                    'message' => 'Nova senha é obrigatória'
                ], 400);
                return;
            }

            if (strlen($novaSenha) < 6) {
                Response::json([
                    'success' => false,
                    'message' => 'A nova senha deve ter no mínimo 6 caracteres'
                ], 400);
                return;
            }

            if ($novaSenha !== $confirmarSenha) {
                Response::json([
                    'success' => false,
                    'message' => 'As senhas não conferem'
                ], 400);
                return;
            }

            $funcionarioModel = new Funcionario();
            $funcionario = $funcionarioModel->buscarPorId($userId);

            if (!$funcionario) {
                Response::json([
                    'success' => false,
                    'message' => 'Usuário não encontrado'
                ], 404);
                return;
            }

            // Verificar senha atual
            if (!password_verify($senhaAtual, $funcionario['senha'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Senha atual incorreta'
                ], 400);
                return;
            }

            // Atualizar senha (o Model faz o hash automaticamente)
            $funcionarioModel->atualizar($userId, [
                'senha' => $novaSenha
            ]);

            // Log de auditoria
            AuditLogService::registrar(
                ($funcionario['nome'] ?? 'Sistema') . " alterou sua senha"
            );

            Response::json([
                'success' => true,
                'message' => 'Senha alterada com sucesso'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao alterar senha: ' . $e->getMessage()
            ], 500);
        }
    }
}
