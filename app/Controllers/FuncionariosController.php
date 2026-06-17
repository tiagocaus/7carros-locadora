<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\Funcionario;
use App\Helpers\FileHelper;
use App\Services\AuditLogService;

/**
 * Controller de Funcionários
 *
 * Gerencia operações CRUD de funcionários
 */
class FuncionariosController
{
    /**
     * Lista todos os funcionários (com paginação e busca)
     *
     * GET /api/funcionarios - Retorna JSON
     * Query params: page, perPage, search
     */
    public function index(Request $request): void
    {
        try {
            // Verificar permissão
            if (!Auth::can('funcionarios.visualizar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para visualizar funcionários'
                ], 403);
                return;
            }

            // Obter parâmetros de paginação e busca
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('perPage', 10))); // Máximo 100 por página
            $search = $request->query('search', '');

            $funcionarioModel = new Funcionario();

            // Buscar funcionários paginados
            $funcionarios = $funcionarioModel->listarPaginado($page, $perPage, $search);

            // Contar total de registros (com filtro de busca, se houver)
            $total = $funcionarioModel->contar($search);

            // Calcular total de páginas
            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

            // Adicionar foto_url para cada registro
            $chave = Auth::chave();
            foreach ($funcionarios as &$funcionario) {
                $funcionario['foto_url'] = !empty($funcionario['foto'])
                    ? FileHelper::url($funcionario['foto'], $chave)
                    : '';
            }
            unset($funcionario);

            // Retornar JSON com dados de paginação
            Response::json([
                'success' => true,
                'data' => $funcionarios,
                'pagination' => [
                    'page' => $page,
                    'perPage' => $perPage,
                    'total' => $total,
                    'totalPages' => $totalPages,
                    'hasNext' => $page < $totalPages,
                    'hasPrev' => $page > 1
                ]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar funcionários: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exibe um funcionário específico
     *
     * GET /api/funcionarios/{id}
     */
    public function show(Request $request, int $id): void
    {
        try {
            // Verificar permissão
            if (!Auth::can('funcionarios.visualizar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para visualizar funcionários'
                ], 403);
                return;
            }

            $funcionarioModel = new Funcionario();
            $funcionario = $funcionarioModel->buscarPorIdComFiliais($id);

            if (!$funcionario) {
                Response::json([
                    'success' => false,
                    'message' => 'Funcionário não encontrado'
                ], 404);
                return;
            }

            // Remover senha dos dados retornados
            unset($funcionario['senha']);

            // Adicionar foto_url
            $chave = Auth::chave();
            $funcionario['foto_url'] = !empty($funcionario['foto'])
                ? FileHelper::url($funcionario['foto'], $chave)
                : '';

            Response::json([
                'success' => true,
                'data' => $funcionario
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar funcionário: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cria um novo funcionário
     *
     * POST /funcionarios/salvar
     */
    public function store(Request $request): void
    {
        try {
            // Verificar permissão
            if (!Auth::can('funcionarios.criar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para criar funcionários'
                ], 403);
                return;
            }

            // Bloquear nome/usuário com termo "suporte"
            $nome = $request->input('nome', '');
            $usuario = $request->input('usuario', '');

            if (stripos($nome, 'suporte') !== false) {
                Response::json([
                    'success' => false,
                    'message' => 'O nome não pode conter o termo "suporte". Por favor, escolha outro.'
                ], 400);
                return;
            }

            if (stripos($usuario, 'suporte') !== false) {
                Response::json([
                    'success' => false,
                    'message' => 'O nome de usuário não pode conter o termo "suporte". Por favor, escolha outro.'
                ], 400);
                return;
            }

            // Mapear campos do formulário para campos do banco
            $dados = [
                'chave' => Auth::chave(),
                'nome' => $request->input('nome', ''),
                'usuario' => $request->input('usuario', ''),
                'email' => $request->input('email', ''),
                'senha' => $request->input('senha', ''),
                'status' => $request->input('status', 'A'),
                'foto' => $request->input('foto', ''),
                'id_role' => $request->input('id_role', null),
                'plano' => $request->input('plano', ''),
                'id_matriz_filial' => $request->input('matriz_filial', null),
                // Dados pessoais
                'cpf' => $request->input('cpf', ''),
                'nascionalidade' => $request->input('nascionalidade', ''),
                'sexo' => $request->input('sexo', ''),
                'e_civil' => $request->input('e_civil', ''),
                'cnh' => $request->input('cnh', ''),
                'registro_cnh' => $request->input('registro_cnh', ''),
                'validade_cnh' => $request->input('validade_cnh', null),
                // Dados trabalhistas
                'c_trabalho' => $request->input('c_trabalho', ''),
                'pis' => $request->input('pis', ''),
                'salario' => $request->input('salario') ? currency_parse($request->input('salario')) : '',
                'tipo_salario' => $request->input('tipo_salario', ''),
                'dia_pagamento' => $request->input('dia_pagamento', ''),
                // Endereço
                'cep' => $request->input('cep', ''),
                'rua' => $request->input('rua', ''),
                'num' => $request->input('num', ''),
                'comple' => $request->input('comple', ''),
                'bairro' => $request->input('bairro', ''),
                'cidade' => $request->input('cidade', ''),
                'uf' => $request->input('uf', ''),
                'pais' => $request->input('pais', 'Brasil'),
                // Contato
                'tel_fixo' => $request->input('tel_fixo', ''),
                'tel_cel' => $request->input('tel_cel', ''),
            ];

            // Processar upload de foto usando FileHelper
            $fotoBase64 = $request->input('foto_base64', '');
            if (!empty($fotoBase64)) {
                $filename = FileHelper::save($fotoBase64, 'foto_funcionario');
                if ($filename) {
                    $dados['foto'] = $filename;
                }
            }

            // Campos obrigatórios que não podem ser vazios
            $obrigatorios = ['chave', 'nome', 'usuario', 'email', 'senha', 'cpf'];

            // Campos que devem ser null quando vazios (foreign keys e datas)
            $nullableFields = ['validade_cnh', 'id_role', 'id_matriz_filial'];

            // Converter string vazia para null em campos nullable
            foreach ($nullableFields as $field) {
                if (isset($dados[$field]) && $dados[$field] === '') {
                    $dados[$field] = null;
                }
            }

            // Remover apenas campos obrigatórios que estão vazios (validação)
            // Campos opcionais são mantidos mesmo se vazios
            $dados = array_filter($dados, function ($value, $key) use ($obrigatorios) {
                if (in_array($key, $obrigatorios, true)) {
                    return $value !== '' && $value !== null;
                }
                return true;
            }, ARRAY_FILTER_USE_BOTH);

            $funcionarioModel = new Funcionario();
            $id = $funcionarioModel->criar($dados);

            // Log de auditoria
            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", adicionou funcionário [{$dados['nome']}]"
            );

            // Sincronizar filiais permitidas
            $filiaisPermitidas = $request->input('filiais_permitidas', '');
            if (!empty($filiaisPermitidas)) {
                if (is_string($filiaisPermitidas)) {
                    $filiaisPermitidas = json_decode($filiaisPermitidas, true) ?? [];
                }
                if (is_array($filiaisPermitidas) && !empty($filiaisPermitidas)) {
                    $funcionarioModel->sincronizarFiliais($id, $filiaisPermitidas);
                }
            }

            Response::json([
                'success' => true,
                'message' => 'Funcionário criado com sucesso',
                'data' => ['id' => $id]
            ], 201);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao criar funcionário: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza um funcionário existente
     *
     * PUT/POST /funcionarios/{id}/atualizar
     */
    public function update(Request $request, int $id): void
    {
        try {
            // Verificar permissão
            if (!Auth::can('funcionarios.editar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para editar funcionários'
                ], 403);
                return;
            }

            $funcionarioModel = new Funcionario();

            // Verificar se funcionário existe
            $funcionarioExistente = $funcionarioModel->buscarPorId($id);
            if (!$funcionarioExistente) {
                Response::json([
                    'success' => false,
                    'message' => 'Funcionário não encontrado'
                ], 404);
                return;
            }

            // Bloquear nome/usuário com termo "suporte"
            $nome = $request->input('nome', '');
            $usuario = $request->input('usuario', '');

            if (stripos($nome, 'suporte') !== false) {
                Response::json([
                    'success' => false,
                    'message' => 'O nome não pode conter o termo "suporte". Por favor, escolha outro.'
                ], 400);
                return;
            }

            if (stripos($usuario, 'suporte') !== false) {
                Response::json([
                    'success' => false,
                    'message' => 'O nome de usuário não pode conter o termo "suporte". Por favor, escolha outro.'
                ], 400);
                return;
            }

            // Mapear campos do formulário para campos do banco
            $dados = [
                'nome' => $request->input('nome'),
                'usuario' => $request->input('usuario'),
                'email' => $request->input('email'),
                'status' => $request->input('status'),
                'foto' => $request->input('foto'),
                'id_role' => $request->input('id_role'),
                'plano' => $request->input('plano'),
                'id_matriz_filial' => $request->input('matriz_filial'),
                // Dados pessoais
                'cpf' => $request->input('cpf'),
                'nascionalidade' => $request->input('nascionalidade'),
                'sexo' => $request->input('sexo'),
                'e_civil' => $request->input('e_civil'),
                'cnh' => $request->input('cnh'),
                'registro_cnh' => $request->input('registro_cnh'),
                'validade_cnh' => $request->input('validade_cnh'),
                // Dados trabalhistas
                'c_trabalho' => $request->input('c_trabalho'),
                'pis' => $request->input('pis'),
                'salario' => $request->input('salario') ? currency_parse($request->input('salario')) : '',
                'tipo_salario' => $request->input('tipo_salario'),
                'dia_pagamento' => $request->input('dia_pagamento'),
                // Endereço
                'cep' => $request->input('cep'),
                'rua' => $request->input('rua'),
                'num' => $request->input('num'),
                'comple' => $request->input('comple'),
                'bairro' => $request->input('bairro'),
                'cidade' => $request->input('cidade'),
                'uf' => $request->input('uf'),
                'pais' => $request->input('pais'),
                // Contato
                'tel_fixo' => $request->input('tel_fixo'),
                'tel_cel' => $request->input('tel_cel'),
            ];

            // Atualizar senha apenas se fornecida
            if ($request->input('senha')) {
                $dados['senha'] = $request->input('senha');
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

            // Na edição, permitir que campos sejam limpos (atualizados para vazio)
            // Apenas remover campos que são null (não foram fornecidos no request)
            $dados = array_filter($dados, function ($value) {
                return $value !== null;
            });

            // Campos que devem ser null quando vazios (foreign keys e datas)
            $nullableFields = ['validade_cnh', 'id_role', 'id_matriz_filial'];
            foreach ($nullableFields as $field) {
                if (isset($dados[$field]) && $dados[$field] === '') {
                    $dados[$field] = null;
                }
            }

            $funcionarioModel->atualizar($id, $dados);

            // Log de auditoria
            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", atualizou funcionário [{$funcionarioExistente['nome']}]"
            );

            // Sincronizar filiais permitidas
            $filiaisPermitidas = $request->input('filiais_permitidas', '');
            if (!empty($filiaisPermitidas)) {
                if (is_string($filiaisPermitidas)) {
                    $filiaisPermitidas = json_decode($filiaisPermitidas, true) ?? [];
                }
                if (is_array($filiaisPermitidas)) {
                    $funcionarioModel->sincronizarFiliais($id, $filiaisPermitidas);
                }
            }

            // Se está editando o próprio usuário, atualizar sessão
            if ($id === Auth::id()) {
                Auth::refreshFiliais();
            }

            Response::json([
                'success' => true,
                'message' => 'Funcionário atualizado com sucesso'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar funcionário: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exclui um funcionário
     *
     * DELETE /funcionarios/{id}
     */
    public function destroy(Request $request, int $id): void
    {
        try {
            // Verificar permissão de exclusão
            if (!Auth::can('funcionarios.excluir')) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para excluir funcionários'
                ], 403);
                return;
            }

            $funcionarioModel = new Funcionario();

            // Verificar se funcionário existe
            $funcionario = $funcionarioModel->buscarPorId($id);
            if (!$funcionario) {
                Response::json([
                    'success' => false,
                    'message' => 'Funcionário não encontrado'
                ], 404);
                return;
            }

            // Não permitir excluir o próprio usuário
            if ($funcionario['id'] == Auth::id()) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não pode excluir seu próprio usuário'
                ], 422);
                return;
            }

            // Verificar registros vinculados
            $vinculos = [];

            // Verificar contratos vinculados
            $contratosCount = $funcionarioModel->contarContratos($id);
            if ($contratosCount > 0) {
                $vinculos[] = $contratosCount . ' contrato(s)';
            }

            // Verificar locações vinculadas
            $locacoesCount = $funcionarioModel->contarLocacoes($id);
            if ($locacoesCount > 0) {
                $vinculos[] = $locacoesCount . ' locação(ões)';
            }

            // Verificar financeiro vinculado
            $financeiroCount = $funcionarioModel->contarFinanceiro($id);
            if ($financeiroCount > 0) {
                $vinculos[] = $financeiroCount . ' registro(s) financeiro(s)';
            }

            // Se houver vínculos, bloquear exclusão
            if (!empty($vinculos)) {
                Response::json([
                    'success' => false,
                    'message' => 'Não é possível excluir este funcionário pois existem registros vinculados: ' . implode(', ', $vinculos)
                ], 422);
                return;
            }

            // Apagar foto do funcionário usando FileHelper
            if (!empty($funcionario['foto'])) {
                FileHelper::delete($funcionario['foto'], Auth::chave());
            }

            // Excluir o funcionário (soft delete)
            $funcionarioModel->deletar($id);

            // Log de auditoria
            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", excluiu funcionário [{$funcionario['nome']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Funcionário excluído com sucesso'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao excluir funcionário: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Busca funcionários
     *
     * GET /api/funcionarios/buscar?termo=xxx
     */
    public function buscar(Request $request): void
    {
        try {
            // Verificar permissão
            if (!Auth::can('funcionarios.visualizar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para buscar funcionários'
                ], 403);
                return;
            }

            $termo = $request->query('termo', '');

            $funcionarioModel = new Funcionario();
            $funcionarios = $funcionarioModel->buscar($termo);

            Response::json([
                'success' => true,
                'data' => $funcionarios
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar funcionários: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lista todas as roles disponíveis
     *
     * GET /api/funcionarios/roles
     */
    public function roles(): void
    {
        try {
            // Verificar permissão
            if (!Auth::can('funcionarios.visualizar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para visualizar roles'
                ], 403);
                return;
            }

            $roleModel = new \App\Models\Role();
            $roles = $roleModel->listarParaSelect(Auth::chave());

            Response::json([
                'success' => true,
                'data' => $roles
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar roles: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verifica se o nome de usuário está disponível
     *
     * GET /api/funcionarios/check-usuario
     */
    public function checkUsuario(): void
    {
        try {
            // Verificar permissão
            if (!Auth::can('funcionarios.visualizar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para verificar usuários'
                ], 403);
                return;
            }

            $usuario = $_GET['usuario'] ?? '';
            $excludeId = isset($_GET['exclude_id']) ? (int) $_GET['exclude_id'] : null;

            if (empty($usuario)) {
                Response::json([
                    'success' => false,
                    'message' => 'Usuário não informado'
                ], 400);
                return;
            }

            // Bloquear usuários com termo "suporte"
            if (stripos($usuario, 'suporte') !== false) {
                Response::json([
                    'success' => true,
                    'disponivel' => false,
                    'message' => 'Nome de usuário não pode conter o termo "suporte". Por favor, escolha outro.'
                ]);
                return;
            }

            $funcionarioModel = new Funcionario();
            $disponivel = $funcionarioModel->usuarioDisponivel($usuario, $excludeId);

            Response::json([
                'success' => true,
                'disponivel' => $disponivel,
                'message' => $disponivel ? 'Usuário disponível' : 'Usuário já está em uso'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao verificar usuário'
            ], 500);
        }
    }
}
