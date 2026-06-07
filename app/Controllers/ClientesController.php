<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\Cliente;
use App\Models\ContatoEmail;
use App\Models\ContatoTelefone;
use App\Helpers\FileHelper;
use App\Helpers\FilialHelper;
use App\Models\ClienteCartao;
use App\Models\Financeiro;
use App\Models\GatewayPagamento;
use App\Services\AuditLogService;
use App\Services\Gateways\GatewayFactory;

/**
 * Controller de Clientes
 *
 * Gerencia operações CRUD de clientes
 */
class ClientesController
{
    /**
     * Normaliza o tipo de cliente para os códigos aceitos pela coluna clientes.tipo.
     */
    private function normalizarTipoCliente(mixed $tipo, ?string $padrao = null): ?string
    {
        if (is_array($tipo) || is_object($tipo)) {
            throw new \InvalidArgumentException('Tipo de cliente inválido');
        }

        $valor = strtolower(trim((string) $tipo));

        if ($valor === '') {
            return $padrao;
        }

        $tipos = [
            'pf' => 'PF',
            'pessoa_fisica' => 'PF',
            'f' => 'PF',
            'pj' => 'PJ',
            'pessoa_juridica' => 'PJ',
            'j' => 'PJ',
            'estrangeiro' => 'ES',
            'es' => 'ES',
            'foreigner' => 'ES',
        ];

        if (!isset($tipos[$valor])) {
            throw new \InvalidArgumentException('Tipo de cliente inválido');
        }

        return $tipos[$valor];
    }

    /**
     * Lista todos os clientes (com paginação e busca)
     *
     * GET /api/clientes - Retorna JSON
     * Query params: page, perPage, search
     */
    public function index(Request $request): void
    {
        try {
            // Verificar permissão
            if (!Auth::can('clientes.visualizar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para visualizar clientes'
                ], 403);
                return;
            }

            // Obter parâmetros de paginação e busca
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('perPage', 10))); // Máximo 100 por página
            $search = $request->query('search', '');

            // Obter filtro de filiais permitidas
            [$filialWhere, $filialParams] = FilialHelper::whereFiliais('id_matriz_filial');

            $clienteModel = new Cliente();

            // Buscar clientes paginados (com filtro de filiais)
            $clientes = $clienteModel->listarPaginado($page, $perPage, $search, $filialWhere, $filialParams);

            // Contar total de registros (com filtro de busca e filiais)
            $total = $clienteModel->contar($search, $filialWhere, $filialParams);

            // Calcular total de páginas
            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

            // Buscar IDs dos clientes para carregar contatos em lote
            $clienteIds = array_column($clientes, 'id');

            // Carregar emails e telefones em lote para melhor performance
            $emailModel = new ContatoEmail();
            $telefoneModel = new ContatoTelefone();

            $emailsPorCliente = [];
            $telefonesPorCliente = [];

            if (!empty($clienteIds)) {
                // Buscar todos os emails principais dos clientes
                foreach ($clienteIds as $cid) {
                    $emailPrincipal = $emailModel->getPrincipal('cliente', (int) $cid);
                    if ($emailPrincipal) {
                        $emailsPorCliente[$cid] = $emailPrincipal['email'];
                    }
                }

                // Buscar todos os telefones principais dos clientes
                foreach ($clienteIds as $cid) {
                    $telefonePrincipal = $telefoneModel->getPrincipal('cliente', (int) $cid);
                    if ($telefonePrincipal) {
                        $telefonesPorCliente[$cid] = $telefonePrincipal['telefone'];
                    }
                }
            }

            // Adicionar foto_url e contatos para cada registro
            $chave = Auth::chave();
            foreach ($clientes as &$cliente) {
                $cliente['foto_url'] = !empty($cliente['foto'])
                    ? FileHelper::url($cliente['foto'], $chave)
                    : '';

                // Adicionar email e telefone das tabelas normalizadas
                $cliente['email'] = $emailsPorCliente[$cliente['id']] ?? '';
                $cliente['tel_cel'] = $telefonesPorCliente[$cliente['id']] ?? '';
            }
            unset($cliente);

            // Retornar JSON com dados de paginação
            Response::json([
                'success' => true,
                'data' => $clientes,
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
                'message' => 'Erro ao buscar clientes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exibe um cliente específico
     *
     * GET /api/clientes/{id}
     */
    public function show(Request $request, int $id): void
    {
        try {
            // Verificar permissão
            if (!Auth::can('clientes.visualizar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para visualizar clientes'
                ], 403);
                return;
            }

            $clienteModel = new Cliente();
            $cliente = $clienteModel->buscarPorId($id);

            if (!$cliente) {
                Response::json([
                    'success' => false,
                    'message' => 'Cliente não encontrado'
                ], 404);
                return;
            }

            // Verificar acesso à filial do cliente
            if (!FilialHelper::temAcessoFilial($cliente['id_matriz_filial'] ?? null)) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para acessar este cliente'
                ], 403);
                return;
            }

            // Adicionar foto_url
            $chave = Auth::chave();
            $cliente['foto_url'] = !empty($cliente['foto'])
                ? FileHelper::url($cliente['foto'], $chave)
                : '';

            // Carregar emails de contato
            $emailModel = new ContatoEmail();
            $cliente['emails'] = $emailModel->listarPorEntidade('cliente', $id);

            // Carregar telefones de contato
            $telefoneModel = new ContatoTelefone();
            $cliente['telefones'] = $telefoneModel->listarPorEntidade('cliente', $id);

            // Extrair email principal como string (para compatibilidade com JS)
            $cliente['email'] = '';
            if (!empty($cliente['emails'])) {
                foreach ($cliente['emails'] as $e) {
                    if (($e['principal'] ?? 'N') === 'S') {
                        $cliente['email'] = $e['email'];
                        break;
                    }
                }
                if (empty($cliente['email'])) {
                    $cliente['email'] = $cliente['emails'][0]['email'] ?? '';
                }
            }

            // Extrair telefone principal como string (para compatibilidade com JS)
            $cliente['tel_cel'] = '';
            if (!empty($cliente['telefones'])) {
                foreach ($cliente['telefones'] as $t) {
                    if (($t['principal'] ?? 'N') === 'S') {
                        $cliente['tel_cel'] = $t['telefone'];
                        break;
                    }
                }
                if (empty($cliente['tel_cel'])) {
                    $cliente['tel_cel'] = $cliente['telefones'][0]['telefone'] ?? '';
                }
            }

            Response::json([
                'success' => true,
                'data' => $cliente
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar cliente: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cria um novo cliente
     *
     * POST /clientes/salvar
     */
    public function store(Request $request): void
    {
        try {
            // Verificar permissão
            if (!Auth::can('clientes.criar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para criar clientes'
                ], 403);
                return;
            }

            // Mapear campos do formulário para campos do banco
            $tipoCliente = $this->normalizarTipoCliente($request->input('tipo', 'PF'), 'PF');
            $dados = [
                'id_matriz_filial' => $request->input('id_matriz_filial', 0),
                'foto' => $request->input('foto', ''),
                'tipo' => $tipoCliente,
                'cpf_cnpj' => $request->input('cpf_cnpj', ''),
                'senha' => $request->input('senha') ? password_hash($request->input('senha'), PASSWORD_ARGON2ID) : null,
                'nome_rsocial' => $request->input('nome_rsocial', ''),
                'nome_fantasia' => $request->input('nome_fantasia', ''),
                'rg_ie' => $request->input('rg_ie', ''),
                'nascimento' => $request->input('nascimento', null),
                'sexo' => $request->input('sexo', ''),
                'estado_civil' => $request->input('estado_civil', ''),
                'profissao' => $request->input('profissao', ''),
                'cep' => $request->input('cep', ''),
                'rua' => $request->input('rua', ''),
                'numero' => $request->input('numero', ''),
                'complemento' => $request->input('complemento', ''),
                'pais' => $request->input('pais', 'Brasil'),
                'estado' => $request->input('estado', ''),
                'cidade' => $request->input('cidade', ''),
                'bairro' => $request->input('bairro', ''),
                'cnh_numero' => $request->input('cnh_numero', ''),
                'cnh_codigo_seguranca' => $request->input('cnh_codigo_seguranca', ''),
                'cnh_categoria' => $request->input('cnh_categoria', ''),
                'cnh_validade' => $request->input('cnh_validade', null),
                'situacao' => $request->input('situacao', 'A'),
                'obs' => $request->input('obs', ''),
            ];

            // Processar upload de foto usando FileHelper
            $fotoBase64 = $request->input('foto_base64', '');
            if (!empty($fotoBase64)) {
                $filename = FileHelper::save($fotoBase64, 'foto_cliente');
                if ($filename) {
                    $dados['foto'] = $filename;
                }
            }

            // Remover campos vazios ou nulos opcionais
            $dados = array_filter($dados, function($value) {
                return $value !== '' && $value !== null;
            });

            if ($tipoCliente === 'PJ') {
                $dados['cnh_numero'] = null;
                $dados['cnh_codigo_seguranca'] = null;
                $dados['cnh_categoria'] = null;
                $dados['cnh_validade'] = null;
            }

            $clienteModel = new Cliente();
            $id = $clienteModel->criarComAuditoria($dados);

            // Salvar emails de contato
            $emails = $request->input('emails', '');
            if (!empty($emails)) {
                $emailsArray = is_string($emails) ? json_decode($emails, true) : $emails;
                if (!empty($emailsArray) && is_array($emailsArray)) {
                    $emailModel = new ContatoEmail();
                    $emailModel->salvar('cliente', $id, $emailsArray);
                }
            }

            // Salvar telefones de contato
            $telefones = $request->input('telefones', '');
            if (!empty($telefones)) {
                $telefonesArray = is_string($telefones) ? json_decode($telefones, true) : $telefones;
                if (!empty($telefonesArray) && is_array($telefonesArray)) {
                    $telefoneModel = new ContatoTelefone();
                    $telefoneModel->salvar('cliente', $id, $telefonesArray);
                }
            }

            Response::json([
                'success' => true,
                'message' => 'Cliente criado com sucesso',
                'data' => ['id' => $id]
            ], 201);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao criar cliente: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza um cliente existente
     *
     * PUT/POST /clientes/{id}/atualizar
     */
    public function update(Request $request, int $id): void
    {
        try {
            // Verificar permissão
            if (!Auth::can('clientes.editar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para editar clientes'
                ], 403);
                return;
            }

            $clienteModel = new Cliente();

            // Verificar se cliente existe
            $clienteExistente = $clienteModel->buscarPorId($id);
            if (!$clienteExistente) {
                Response::json([
                    'success' => false,
                    'message' => 'Cliente não encontrado'
                ], 404);
                return;
            }

            // Verificar acesso à filial do cliente
            if (!FilialHelper::temAcessoFilial($clienteExistente['id_matriz_filial'] ?? null)) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para editar este cliente'
                ], 403);
                return;
            }

            // Mapear campos do formulário para campos do banco
            $tipoCliente = $this->normalizarTipoCliente($request->input('tipo'), null);
            $dados = [
                'id_matriz_filial' => $request->input('id_matriz_filial'),
                'tipo' => $tipoCliente,
                'cpf_cnpj' => $request->input('cpf_cnpj'),
                'nome_rsocial' => $request->input('nome_rsocial'),
                'rg_ie' => $request->input('rg_ie'),
                'nascimento' => $request->input('nascimento'),
                'sexo' => $request->input('sexo'),
                'estado_civil' => $request->input('estado_civil'),
                'profissao' => $request->input('profissao'),
                'cep' => $request->input('cep'),
                'rua' => $request->input('rua'),
                'numero' => $request->input('numero'),
                'complemento' => $request->input('complemento'),
                'pais' => $request->input('pais'),
                'estado' => $request->input('estado'),
                'cidade' => $request->input('cidade'),
                'bairro' => $request->input('bairro'),
                'cnh_numero' => $request->input('cnh_numero'),
                'cnh_codigo_seguranca' => $request->input('cnh_codigo_seguranca'),
                'cnh_categoria' => $request->input('cnh_categoria'),
                'cnh_validade' => $request->input('cnh_validade'),
                'situacao' => $request->input('situacao'),
            ];

            // Atualizar senha apenas se fornecida
            if ($request->input('senha')) {
                $dados['senha'] = password_hash($request->input('senha'), PASSWORD_ARGON2ID);
            }

            // Processar upload de foto usando FileHelper
            $fotoBase64 = $request->input('foto_base64', '');
            if (!empty($fotoBase64)) {
                // Apagar foto antiga
                if (!empty($clienteExistente['foto'])) {
                    FileHelper::delete($clienteExistente['foto'], Auth::chave());
                }
                $filename = FileHelper::save($fotoBase64, 'foto_cliente');
                if ($filename) {
                    $dados['foto'] = $filename;
                }
            }

            // Remover campos não fornecidos
            $dados = array_filter($dados, function($value) {
                return $value !== null && $value !== '';
            });

            $tipoEfetivo = $tipoCliente ?? ($clienteExistente['tipo'] ?? null);
            if ($tipoEfetivo === 'PJ') {
                $dados['cnh_numero'] = null;
                $dados['cnh_codigo_seguranca'] = null;
                $dados['cnh_categoria'] = null;
                $dados['cnh_validade'] = null;
            }

            $clienteModel->atualizarComAuditoria($id, $dados);

            // Atualizar emails de contato
            $emails = $request->input('emails', null);
            if ($emails !== null) {
                $emailsArray = is_string($emails) ? json_decode($emails, true) : $emails;
                if (is_array($emailsArray)) {
                    $emailModel = new ContatoEmail();
                    $emailModel->salvar('cliente', $id, $emailsArray);
                }
            }

            // Atualizar telefones de contato
            $telefones = $request->input('telefones', null);
            if ($telefones !== null) {
                $telefonesArray = is_string($telefones) ? json_decode($telefones, true) : $telefones;
                if (is_array($telefonesArray)) {
                    $telefoneModel = new ContatoTelefone();
                    $telefoneModel->salvar('cliente', $id, $telefonesArray);
                }
            }

            Response::json([
                'success' => true,
                'message' => 'Cliente atualizado com sucesso'
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar cliente: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exclui um cliente
     *
     * DELETE /clientes/{id}
     */
    public function destroy(Request $request, int $id): void
    {
        try {
            // Verificar permissão de exclusão
            if (!Auth::can('clientes.excluir')) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para excluir clientes'
                ], 403);
                return;
            }

            $clienteModel = new Cliente();

            // Verificar se cliente existe
            $cliente = $clienteModel->buscarPorId($id);
            if (!$cliente) {
                Response::json([
                    'success' => false,
                    'message' => 'Cliente não encontrado'
                ], 404);
                return;
            }

            // Verificar acesso à filial do cliente
            if (!FilialHelper::temAcessoFilial($cliente['id_matriz_filial'] ?? null)) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para excluir este cliente'
                ], 403);
                return;
            }

            // Verificar vínculos que impedem exclusão
            $verificacao = $this->verificarVinculos($id);

            if ($verificacao['temVinculos']) {
                Response::json([
                    'success' => false,
                    'message' => 'Não é possível excluir este cliente pois existem registros vinculados (contratos, locações ou financeiro em aberto). Considere desativar o cliente alterando o status para Inativo.',
                    'vinculos' => $verificacao['detalhes']
                ], 422);
                return;
            }

            // Apagar arquivos físicos do cliente
            $this->apagarArquivos($cliente, $id);

            // Apagar registros relacionados (clientes_arquivos, clientes_cartoes)
            $this->apagarRegistrosRelacionados($id);

            // Excluir o cliente
            $clienteModel->deletarComAuditoria($id);

            Response::json([
                'success' => true,
                'message' => 'Cliente excluído com sucesso'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao excluir cliente: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Busca clientes
     *
     * GET /api/clientes/buscar?termo=xxx
     */
    public function buscar(Request $request): void
    {
        try {
            // Verificar permissão
            if (!Auth::can('clientes.visualizar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para buscar clientes'
                ], 403);
                return;
            }

            $termo = $request->query('q', '');

            // Obter filtro de filiais permitidas
            [$filialWhere, $filialParams] = FilialHelper::whereFiliais('id_matriz_filial');

            $clienteModel = new Cliente();
            $clientes = $clienteModel->buscar($termo, $filialWhere, $filialParams);

            Response::json([
                'success' => true,
                'data' => $clientes
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar clientes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verifica se o cliente possui vínculos que impedem exclusão
     *
     * @param int $clienteId ID do cliente
     * @return array ['temVinculos' => bool, 'detalhes' => array]
     */
    private function verificarVinculos(int $clienteId): array
    {
        $clienteModel = new Cliente();
        return $clienteModel->verificarVinculos($clienteId);
    }

    /**
     * Apaga arquivos físicos do cliente usando FileHelper
     *
     * @param array $cliente Dados do cliente
     * @param int $clienteId ID do cliente
     * @return void
     */
    private function apagarArquivos(array $cliente, int $clienteId): void
    {
        $chave = Auth::chave();

        // Apagar foto principal
        if (!empty($cliente['foto'])) {
            FileHelper::delete($cliente['foto'], $chave);
        }

        // Apagar arquivos da tabela clientes_arquivos
        $clienteModel = new Cliente();
        $arquivos = $clienteModel->listarArquivos($clienteId);

        foreach ($arquivos as $arq) {
            if (!empty($arq['arquivo'])) {
                FileHelper::delete($arq['arquivo'], $chave);
            }
        }
    }

    /**
     * Apaga registros relacionados ao cliente (sem vínculos críticos)
     *
     * @param int $clienteId ID do cliente
     * @return void
     */
    private function apagarRegistrosRelacionados(int $clienteId): void
    {
        $clienteModel = new Cliente();

        // Apagar arquivos da tabela clientes_arquivos
        $clienteModel->excluirArquivos($clienteId);

        // Apagar cartões salvos
        $clienteModel->excluirCartoes($clienteId);
    }

    /**
     * Lista financeiro do cliente
     *
     * GET /api/clientes/{id}/financeiro
     */
    public function financeiro(Request $request, int $id): void
    {
        try {
            // Verificar permissão
            if (!Auth::can('clientes.visualizar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para visualizar financeiro'
                ], 403);
                return;
            }

            $clienteModel = new Cliente();
            $financeiro = $clienteModel->listarFinanceiro($id);

            Response::json([
                'success' => true,
                'data' => $financeiro
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar financeiro: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Envia cobrança de lançamento financeiro via WhatsApp
     *
     * POST /api/clientes/financeiro/{id}/cobranca
     */
    public function enviarCobrancaFinanceiro(Request $request, int $id): void
    {
        try {
            // Verificar permissão
            if (!Auth::can('financeiro.visualizar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para enviar cobranças'
                ], 403);
                return;
            }

            // Buscar lançamento financeiro
            $financeiroModel = new Financeiro();
            $financeiro = $financeiroModel->buscarPorId($id);

            if (!$financeiro) {
                Response::json([
                    'success' => false,
                    'message' => 'Lançamento financeiro não encontrado'
                ], 404);
                return;
            }

            // Verificar se já está pago
            if ($financeiro['pago'] === 'S') {
                Response::json([
                    'success' => false,
                    'message' => 'Este lançamento já está pago'
                ], 400);
                return;
            }

            // Verificar se é receita (tipo R)
            if ($financeiro['tipo'] !== 'R') {
                Response::json([
                    'success' => false,
                    'message' => 'Apenas lançamentos de receita podem ser cobrados'
                ], 400);
                return;
            }

            // Verificar se tem cliente vinculado
            if (empty($financeiro['id_cliente'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Lançamento não possui cliente vinculado'
                ], 400);
                return;
            }

            // Buscar dados do cliente
            $clienteModel = new Cliente();
            $cliente = $clienteModel->buscarPorId((int) $financeiro['id_cliente']);

            if (!$cliente) {
                Response::json([
                    'success' => false,
                    'message' => 'Cliente não encontrado'
                ], 404);
                return;
            }

            // Buscar telefone do cliente da tabela normalizada
            $telefoneModel = new ContatoTelefone();
            $telefonePrincipal = $telefoneModel->getPrincipal('cliente', (int) $cliente['id']);
            $telefone = $telefonePrincipal['telefone'] ?? null;

            // Se não tem principal, buscar qualquer telefone
            if (empty($telefone)) {
                $telefones = $telefoneModel->listarPorEntidade('cliente', (int) $cliente['id']);
                $telefone = $telefones[0]['telefone'] ?? null;
            }

            if (empty($telefone)) {
                Response::json([
                    'success' => false,
                    'message' => 'Cliente não possui telefone cadastrado'
                ], 400);
                return;
            }

            // Buscar email principal do cliente
            $emailModel = new ContatoEmail();
            $emailPrincipal = $emailModel->getPrincipal('cliente', (int) $cliente['id']);
            $email = $emailPrincipal['email'] ?? '';

            // Preparar contexto para o template (empresa.* vem do enrichment no service)
            $context = [
                'cliente' => [
                    'nome' => $cliente['nome_rsocial'],
                    'primeiro_nome' => explode(' ', $cliente['nome_rsocial'])[0],
                    'email' => $email,
                    'cpf_cnpj' => $cliente['cpf_cnpj'] ?? '',
                    'telefone' => $telefone,
                    'preferred_locale' => $cliente['preferred_locale'] ?? null,
                ],
                'fatura' => [
                    'numero' => $financeiro['codigo'] ?? $financeiro['sequencia'] ?? $id,
                    'valor' => $financeiro['valor_total'],
                    'data_vencimento' => $financeiro['data_venci'],
                    'descricao' => $financeiro['descricao'] ?? '',
                    'status' => 'Pendente',
                ],
            ];

            // Enviar mensagem via template
            $messageId = queue_template_message('payment_reminder', 'whatsapp', $context);

            Response::json([
                'success' => true,
                'message' => 'Cobrança enviada com sucesso via WhatsApp',
                'data' => [
                    'message_id' => $messageId,
                    'telefone' => $telefone
                ]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao enviar cobrança: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lista arquivos do cliente
     *
     * GET /api/clientes/{id}/arquivos
     */
    public function arquivos(Request $request, int $id): void
    {
        try {
            // Verificar permissão
            if (!Auth::can('clientes.visualizar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para visualizar arquivos de clientes'
                ], 403);
                return;
            }

            $clienteModel = new Cliente();

            // Verificar se cliente existe
            $cliente = $clienteModel->buscarPorId($id);
            if (!$cliente) {
                Response::json([
                    'success' => false,
                    'message' => 'Cliente não encontrado'
                ], 404);
                return;
            }

            // Verificar acesso à filial do cliente
            if (!FilialHelper::temAcessoFilial($cliente['id_matriz_filial'] ?? null)) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para acessar este cliente'
                ], 403);
                return;
            }

            // Buscar arquivos
            $arquivos = $clienteModel->listarArquivosCompleto($id);
            $chave = Auth::chave();

            // Processar cada arquivo para adicionar URL e nomes amigáveis
            foreach ($arquivos as &$arquivo) {
                $arquivo['arquivo_url'] = !empty($arquivo['arquivo'])
                    ? FileHelper::url($arquivo['arquivo'], $chave)
                    : '';
                $arquivo['tipo_nome'] = Cliente::TIPOS_ARQUIVO[$arquivo['tipo']] ?? 'Desconhecido';
                $arquivo['status_nome'] = Cliente::STATUS_ARQUIVO[$arquivo['status']] ?? 'Aguardando';
            }
            unset($arquivo);

            Response::json([
                'success' => true,
                'data' => $arquivos,
                'tipos' => Cliente::TIPOS_ARQUIVO
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar arquivos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Faz upload de um arquivo para o cliente
     *
     * POST /api/clientes/{id}/arquivos
     */
    public function uploadArquivo(Request $request, int $id): void
    {
        try {
            // Verificar permissão
            if (!Auth::can('clientes.editar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para enviar arquivos de clientes'
                ], 403);
                return;
            }

            $clienteModel = new Cliente();

            // Verificar se cliente existe
            $cliente = $clienteModel->buscarPorId($id);
            if (!$cliente) {
                Response::json([
                    'success' => false,
                    'message' => 'Cliente não encontrado'
                ], 404);
                return;
            }

            // Verificar acesso à filial do cliente
            if (!FilialHelper::temAcessoFilial($cliente['id_matriz_filial'] ?? null)) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para editar este cliente'
                ], 403);
                return;
            }

            // Validar dados recebidos
            $tipo = (int) $request->input('tipo');
            $arquivoBase64 = $request->input('arquivo_base64', '');
            $nome = $request->input('nome', '');

            if (!isset(Cliente::TIPOS_ARQUIVO[$tipo])) {
                Response::json([
                    'success' => false,
                    'message' => 'Tipo de documento inválido'
                ], 400);
                return;
            }

            if (empty($arquivoBase64)) {
                Response::json([
                    'success' => false,
                    'message' => 'Arquivo não informado'
                ], 400);
                return;
            }

            // Detectar tipo MIME do arquivo
            $mimeDetected = $this->detectMimeFromBase64($arquivoBase64);
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

            if (!in_array($mimeDetected, $allowedMimes, true)) {
                Response::json([
                    'success' => false,
                    'message' => 'Formato de arquivo não permitido. Apenas imagens (JPEG, PNG, WebP) e PDF são aceitos.'
                ], 400);
                return;
            }

            // Salvar arquivo usando FileHelper com prefixo específico
            $tipoNome = str_replace(['/', ' '], '', strtolower(Cliente::TIPOS_ARQUIVO[$tipo] ?? 'outros'));
            $filename = FileHelper::save($arquivoBase64, 'clientearquivo_' . $tipoNome);

            if (!$filename) {
                Response::json([
                    'success' => false,
                    'message' => 'Erro ao salvar arquivo'
                ], 500);
                return;
            }

            // Gerar nome amigável se não fornecido
            if (empty($nome)) {
                $extensao = pathinfo($filename, PATHINFO_EXTENSION);
                $nome = Cliente::TIPOS_ARQUIVO[$tipo] . '_' . date('Ymd_His') . '.' . $extensao;
            }

            // Truncar nome se muito longo (max 50 caracteres)
            if (strlen($nome) > 50) {
                $extensao = pathinfo($nome, PATHINFO_EXTENSION);
                $nomeBase = substr(pathinfo($nome, PATHINFO_FILENAME), 0, 45 - strlen($extensao));
                $nome = $nomeBase . '.' . $extensao;
            }

            // Inserir registro no banco
            $arquivoId = $clienteModel->inserirArquivo($id, [
                'nome' => $nome,
                'arquivo' => $filename,
                'tipo' => $tipo
            ]);

            Response::json([
                'success' => true,
                'message' => 'Arquivo enviado com sucesso',
                'data' => [
                    'id' => $arquivoId,
                    'nome' => $nome,
                    'arquivo_url' => FileHelper::url($filename, Auth::chave()),
                    'tipo' => $tipo,
                    'tipo_nome' => Cliente::TIPOS_ARQUIVO[$tipo]
                ]
            ], 201);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao enviar arquivo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exclui um arquivo específico do cliente
     *
     * POST /api/clientes/{id}/arquivos/{arquivoId}/excluir
     */
    public function excluirArquivo(Request $request, int $id, int $arquivoId): void
    {
        try {
            // Verificar permissão
            if (!Auth::can('clientes.editar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para excluir arquivos de clientes'
                ], 403);
                return;
            }

            $clienteModel = new Cliente();

            // Verificar se cliente existe
            $cliente = $clienteModel->buscarPorId($id);
            if (!$cliente) {
                Response::json([
                    'success' => false,
                    'message' => 'Cliente não encontrado'
                ], 404);
                return;
            }

            // Verificar acesso à filial do cliente
            if (!FilialHelper::temAcessoFilial($cliente['id_matriz_filial'] ?? null)) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para editar este cliente'
                ], 403);
                return;
            }

            // Buscar arquivo
            $arquivo = $clienteModel->buscarArquivo($arquivoId);
            if (!$arquivo) {
                Response::json([
                    'success' => false,
                    'message' => 'Arquivo não encontrado'
                ], 404);
                return;
            }

            // Verificar se o arquivo pertence ao cliente
            if ((int) $arquivo['id_cliente'] !== $id) {
                Response::json([
                    'success' => false,
                    'message' => 'Arquivo não pertence a este cliente'
                ], 403);
                return;
            }

            // Excluir arquivo físico
            if (!empty($arquivo['arquivo'])) {
                FileHelper::delete($arquivo['arquivo'], Auth::chave());
            }

            // Excluir registro do banco
            $clienteModel->excluirArquivoPorId($arquivoId);

            // Registrar log de auditoria
            $nomeUsuario = $_SESSION['user_name'] ?? 'Sistema';
            $tipoNome = Cliente::TIPOS_ARQUIVO[$arquivo['tipo']] ?? 'Documento';
            AuditLogService::registrar(
                "{$nomeUsuario}, excluiu o arquivo [{$arquivo['nome']}] ({$tipoNome}) do cliente [{$cliente['nome_rsocial']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Arquivo excluído com sucesso'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao excluir arquivo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lista cartões de crédito salvos do cliente
     *
     * GET /api/clientes/{id}/cartoes
     */
    public function cartoes(Request $request, int $id): void
    {
        try {
            if (!Auth::can('clientes.visualizar')) {
                Response::json(['success' => false, 'message' => 'Sem permissão'], 403);
                return;
            }

            $clienteModel = new Cliente();
            $cliente = $clienteModel->buscarPorId($id);
            if (!$cliente) {
                Response::json(['success' => false, 'message' => 'Cliente não encontrado'], 404);
                return;
            }

            if (!FilialHelper::temAcessoFilial($cliente['id_matriz_filial'] ?? null)) {
                Response::json(['success' => false, 'message' => 'Sem permissão para acessar este cliente'], 403);
                return;
            }

            $cartaoModel = new ClienteCartao();
            $cartoes = $cartaoModel->listarPorCliente($id);

            Response::json(['success' => true, 'data' => $cartoes]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro ao buscar cartões: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Lista gateways de pagamento que suportam cartão de crédito
     *
     * GET /api/clientes/{id}/gateways-cartao
     */
    public function gatewaysCartao(Request $request, int $id): void
    {
        try {
            if (!Auth::can('clientes.editar')) {
                Response::json(['success' => false, 'message' => 'Sem permissão'], 403);
                return;
            }

            $clienteModel = new Cliente();
            $cliente = $clienteModel->buscarPorId($id);
            if (!$cliente) {
                Response::json(['success' => false, 'message' => 'Cliente não encontrado'], 404);
                return;
            }

            if (!FilialHelper::temAcessoFilial($cliente['id_matriz_filial'] ?? null)) {
                Response::json(['success' => false, 'message' => 'Sem permissão para acessar este cliente'], 403);
                return;
            }

            $gatewayModel = new GatewayPagamento();
            $gateways = $gatewayModel->listarAtivos();

            $result = [];
            foreach ($gateways as $gw) {
                if ((int) ($gw['credit_card_enabled'] ?? 0) !== 1) {
                    continue;
                }

                $item = [
                    'id' => (int) $gw['id'],
                    'nome' => $gw['nome'],
                    'gateway_code' => $gw['gateway_code'],
                ];

                // Para Stripe, incluir publishable_key para o frontend
                if ($gw['gateway_code'] === 'stripe') {
                    $gwConfig = $gatewayModel->buscarPorIdComCredenciais((int) $gw['id']);
                    if ($gwConfig) {
                        try {
                            $gatewayInstance = GatewayFactory::create(
                                $gwConfig['gateway_code'],
                                $gwConfig['credentials'] ?? [],
                                $gwConfig['ambiente'] === 'sandbox',
                                (int) $gwConfig['id']
                            );
                            if (method_exists($gatewayInstance, 'getPublishableKey')) {
                                $item['publishable_key'] = $gatewayInstance->getPublishableKey();
                            }
                        } catch (\Exception $e) {
                            // Se falhar ao instanciar, continua sem publishable_key
                        }
                    }
                }

                $result[] = $item;
            }

            Response::json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro ao buscar gateways: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Tokeniza um cartão de crédito via gateway de pagamento
     *
     * POST /api/clientes/{id}/cartoes/tokenizar
     */
    public function tokenizarCartao(Request $request, int $id): void
    {
        try {
            if (!Auth::can('clientes.editar')) {
                Response::json(['success' => false, 'message' => 'Sem permissão'], 403);
                return;
            }

            $clienteModel = new Cliente();
            $cliente = $clienteModel->buscarPorId($id);
            if (!$cliente) {
                Response::json(['success' => false, 'message' => 'Cliente não encontrado'], 404);
                return;
            }

            if (!FilialHelper::temAcessoFilial($cliente['id_matriz_filial'] ?? null)) {
                Response::json(['success' => false, 'message' => 'Sem permissão para editar este cliente'], 403);
                return;
            }

            $dados = $request->all();
            $gatewayId = (int) ($dados['gateway_id'] ?? 0);

            if (empty($gatewayId)) {
                Response::json(['success' => false, 'message' => 'Gateway é obrigatório'], 400);
                return;
            }

            $gatewayModel = new GatewayPagamento();
            $gatewayConfig = $gatewayModel->buscarPorIdComCredenciais($gatewayId);

            if (!$gatewayConfig || $gatewayConfig['chave'] !== ($_SESSION['chave'] ?? '')) {
                Response::json(['success' => false, 'message' => 'Gateway inválido'], 400);
                return;
            }

            if ((int) ($gatewayConfig['credit_card_enabled'] ?? 0) !== 1) {
                Response::json(['success' => false, 'message' => 'Gateway não suporta cartão de crédito'], 400);
                return;
            }

            $gateway = GatewayFactory::create(
                $gatewayConfig['gateway_code'],
                $gatewayConfig['credentials'] ?? [],
                $gatewayConfig['ambiente'] === 'sandbox',
                $gatewayId
            );

            if (!$gateway->supportsTransparentCheckout()) {
                Response::json(['success' => false, 'message' => 'Este gateway não suporta checkout transparente'], 400);
                return;
            }

            // Preparar dados do cartão
            $cardData = [
                'holder' => $dados['holder'] ?? '',
                'number' => $dados['number'] ?? '',
                'expiry_month' => $dados['expiry_month'] ?? '',
                'expiry_year' => $dados['expiry_year'] ?? '',
                'cvv' => $dados['cvv'] ?? '',
                'cpf' => $cliente['cpf_cnpj'] ?? '',
                'email' => $dados['email'] ?? '',
                'phone' => $dados['phone'] ?? '',
                // Para Stripe que já recebe token do frontend
                'payment_method_id' => $dados['payment_method_id'] ?? null,
                'brand' => $dados['brand'] ?? null,
                'last_digits' => $dados['last_digits'] ?? null,
            ];

            $result = $gateway->tokenizeCard($cardData);

            if (!$result['success']) {
                Response::json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Erro ao tokenizar cartão'
                ], 400);
                return;
            }

            // Salvar cartão tokenizado
            $cartaoModel = new ClienteCartao();
            $dadosCartao = [
                'id_cliente' => $id,
                'bandeira' => strtoupper($result['brand'] ?? 'OUTROS'),
                'ultimos_digitos' => $result['last_digits'] ?? '****',
                'token' => $result['token'],
                'gateway' => $gatewayConfig['gateway_code'],
            ];
            if (!empty($result['gateway_customer_id'])) {
                $dadosCartao['gateway_customer_id'] = $result['gateway_customer_id'];
            }
            $cartaoId = $cartaoModel->criar($dadosCartao);

            Response::json([
                'success' => true,
                'message' => 'Cartão adicionado com sucesso',
                'data' => [
                    'id' => $cartaoId,
                    'brand' => strtoupper($result['brand'] ?? 'OUTROS'),
                    'last_digits' => $result['last_digits'] ?? '****',
                ]
            ], 201);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro ao processar cartão: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Desativa um cartão de crédito do cliente (soft delete)
     *
     * POST /api/clientes/{id}/cartoes/{cartaoId}/desativar
     */
    public function desativarCartao(Request $request, int $id, int $cartaoId): void
    {
        try {
            if (!Auth::can('clientes.editar')) {
                Response::json(['success' => false, 'message' => 'Sem permissão'], 403);
                return;
            }

            $clienteModel = new Cliente();
            $cliente = $clienteModel->buscarPorId($id);
            if (!$cliente) {
                Response::json(['success' => false, 'message' => 'Cliente não encontrado'], 404);
                return;
            }

            if (!FilialHelper::temAcessoFilial($cliente['id_matriz_filial'] ?? null)) {
                Response::json(['success' => false, 'message' => 'Sem permissão para editar este cliente'], 403);
                return;
            }

            $cartaoModel = new ClienteCartao();
            $cartao = $cartaoModel->buscarPorId($cartaoId);
            if (!$cartao || (int) $cartao['id_cliente'] !== $id) {
                Response::json(['success' => false, 'message' => 'Cartão não encontrado'], 404);
                return;
            }

            $cartaoModel->desativar($cartaoId, $id);

            Response::json(['success' => true, 'message' => 'Cartão desativado com sucesso']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro ao desativar cartão: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Define um cartão como padrão
     *
     * POST /api/clientes/{id}/cartoes/{cartaoId}/padrao
     */
    public function definirCartaoPadrao(Request $request, int $id, int $cartaoId): void
    {
        try {
            if (!Auth::can('clientes.editar')) {
                Response::json(['success' => false, 'message' => 'Sem permissão'], 403);
                return;
            }

            $clienteModel = new Cliente();
            $cliente = $clienteModel->buscarPorId($id);
            if (!$cliente) {
                Response::json(['success' => false, 'message' => 'Cliente não encontrado'], 404);
                return;
            }

            if (!FilialHelper::temAcessoFilial($cliente['id_matriz_filial'] ?? null)) {
                Response::json(['success' => false, 'message' => 'Sem permissão para editar este cliente'], 403);
                return;
            }

            $cartaoModel = new ClienteCartao();
            $cartao = $cartaoModel->buscarPorId($cartaoId);
            if (!$cartao || (int) $cartao['id_cliente'] !== $id) {
                Response::json(['success' => false, 'message' => 'Cartão não encontrado'], 404);
                return;
            }

            $cartaoModel->definirComoPadrao($cartaoId, $id, $cartao['gateway']);

            Response::json(['success' => true, 'message' => 'Cartão definido como padrão']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro ao definir cartão padrão: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Detecta o tipo MIME a partir de um base64
     *
     * @param string $base64 String base64 (pode conter data URI)
     * @return string|null Tipo MIME ou null se não detectado
     */
    private function detectMimeFromBase64(string $base64): ?string
    {
        // Se tem data URI, extrair o MIME diretamente
        if (preg_match('/^data:([a-zA-Z0-9\/+-]+);base64,/', $base64, $matches)) {
            return $matches[1];
        }

        // Remover possível prefixo data URI
        $data = preg_replace('/^data:[a-zA-Z0-9\/+-]+;base64,/', '', $base64);
        $decoded = base64_decode($data, true);

        if ($decoded === false) {
            return null;
        }

        // Detectar pelos magic bytes
        $signatures = [
            'image/jpeg' => ["\xFF\xD8\xFF"],
            'image/png' => ["\x89PNG\r\n\x1a\n"],
            'image/webp' => ["RIFF", "WEBP"],
            'application/pdf' => ["%PDF"],
        ];

        foreach ($signatures as $mime => $sigs) {
            foreach ($sigs as $sig) {
                if (str_starts_with($decoded, $sig)) {
                    return $mime;
                }
            }
        }

        // WebP precisa verificação especial (RIFF....WEBP)
        if (strlen($decoded) >= 12) {
            if (substr($decoded, 0, 4) === 'RIFF' && substr($decoded, 8, 4) === 'WEBP') {
                return 'image/webp';
            }
        }

        return null;
    }
}
