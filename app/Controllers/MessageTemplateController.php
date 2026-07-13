<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Services\MessageTemplateService;
use App\I18n\TemplateVariables;
use App\I18n\Translator;
use App\Models\MessageTemplate;
use App\Services\AuditLogService;

/**
 * Controller de Templates de Mensagem
 *
 * Gerencia operações de visualização e edição de templates
 * de mensagem (email, WhatsApp, SMS) por empresa
 */
class MessageTemplateController
{
    private ?MessageTemplateService $service = null;
    private MessageTemplate $messageTemplateModel;

    public function __construct()
    {
        $this->messageTemplateModel = new MessageTemplate();
    }

    /**
     * Obtém instância do service
     */
    private function getService(): MessageTemplateService
    {
        if ($this->service === null) {
            $this->service = new MessageTemplateService(
                $this->messageTemplateModel->getMysqliConnection(),
                Auth::chave()
            );
        }
        return $this->service;
    }

    /**
     * Página de listagem de tipos de template
     *
     * GET /pages/configuracoes/templates
     */
    public function index(): void
    {
        if (!Auth::can('templates.visualizar')) {
            Response::html('<p>Acesso negado</p>', 403);
            return;
        }

        $html = Template::render('pages.configuracoes.templates.index');
        Response::html($html);
    }

    /**
     * Página de edição de template
     *
     * GET /pages/configuracoes/templates/{slug}
     */
    public function edit(Request $request, string $slug): void
    {
        if (!Auth::can('templates.visualizar')) {
            Response::html('<p>Acesso negado</p>', 403);
            return;
        }

        $type = $this->getService()->getTemplateType($slug);

        if (!$type) {
            Response::html('<p>Template não encontrado</p>', 404);
            return;
        }

        $html = Template::render('pages.configuracoes.templates.editar', [
            'slug' => $slug,
            'type' => $type,
        ]);
        Response::html($html);
    }

    /**
     * API: Lista todos os tipos de template
     *
     * GET /api/templates/types
     */
    public function getTypes(Request $request): void
    {
        try {
            if (!Auth::can('templates.visualizar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para visualizar templates'
                ], 403);
                return;
            }

            $category = $request->query('category');
            $types = $this->getService()->getTemplateTypes($category);

            // Adicionar info de customização para cada tipo
            foreach ($types as &$type) {
                $type['is_customized'] = $this->hasCustomTemplate($type['slug']);
            }

            Response::json([
                'success' => true,
                'data' => $types
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar tipos de template: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Busca template específico (customizado ou padrão)
     *
     * GET /api/templates/{slug}
     * Query: channel, locale
     */
    public function getTemplate(Request $request, string $slug): void
    {
        try {
            if (!Auth::can('templates.visualizar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para visualizar templates'
                ], 403);
                return;
            }

            $channel = $request->query('channel', 'email');
            $locale = $request->query('locale', Translator::getInstance()->getLocale());

            $template = $this->getService()->getTemplate($slug, $channel, $locale);

            if (!$template) {
                Response::json([
                    'success' => false,
                    'message' => 'Template não encontrado'
                ], 404);
                return;
            }

            Response::json([
                'success' => true,
                'data' => [
                    'slug' => $slug,
                    'channel' => $channel,
                    'locale' => $locale,
                    'subject' => $template['subject'] ?? '',
                    'content' => $template['content'] ?? '',
                    'content_plain' => $template['content_plain'] ?? '',
                    'is_custom' => $template['is_custom'] ?? false,
                    'type' => $template['type'] ?? null,
                ]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar template: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Salva template customizado
     *
     * POST /api/templates/{slug}
     * Body: channel, locale, subject, content, content_plain
     */
    public function saveTemplate(Request $request, string $slug): void
    {
        try {
            if (!Auth::can('templates.editar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para editar templates'
                ], 403);
                return;
            }

            $channel = $request->input('channel', 'email');
            $locale = $request->input('locale', 'pt_BR');
            $subject = $request->input('subject', '');
            $content = $request->input('content', '');
            $contentPlain = $request->input('content_plain', '');

            if (empty($content)) {
                Response::json([
                    'success' => false,
                    'message' => 'O conteúdo do template não pode ser vazio'
                ], 422);
                return;
            }

            // Validar variáveis usadas
            $errors = $this->getService()->validateTemplate($slug, $content);
            if (!empty($errors)) {
                Response::json([
                    'success' => false,
                    'message' => 'Template contém variáveis inválidas',
                    'errors' => $errors
                ], 422);
                return;
            }

            $id = $this->getService()->saveTemplate(
                $slug,
                $channel,
                $locale,
                [
                    'subject' => $subject,
                    'content' => $content,
                    'content_plain' => $contentPlain,
                ],
                Auth::id()
            );

            // Log de auditoria
            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", atualizou template de mensagem [{$slug}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Template salvo com sucesso',
                'data' => ['id' => $id]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao salvar template: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Restaura template para o padrão do sistema
     *
     * POST /api/templates/{slug}/restore
     * Body: channel, locale
     */
    public function restoreDefault(Request $request, string $slug): void
    {
        try {
            if (!Auth::can('templates.editar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para editar templates'
                ], 403);
                return;
            }

            $channel = $request->input('channel', 'email');
            $locale = $request->input('locale', 'pt_BR');

            $restored = $this->getService()->restoreDefault($slug, $channel, $locale);

            if ($restored) {
                // Log de auditoria
                AuditLogService::registrar(
                    ($_SESSION['user_name'] ?? 'Sistema') . ", restaurou template para padrão [{$slug}]"
                );

                Response::json([
                    'success' => true,
                    'message' => 'Template restaurado para o padrão'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Não foi possível restaurar o template (pode não haver customização)'
                ], 404);
            }
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao restaurar template: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Preview de template com dados de exemplo
     *
     * GET /api/templates/{slug}/preview
     * Query: channel, locale
     */
    public function preview(Request $request, string $slug): void
    {
        try {
            if (!Auth::can('templates.visualizar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para visualizar templates'
                ], 403);
                return;
            }

            $channel = $request->query('channel', 'email');
            $locale = $request->query('locale', Translator::getInstance()->getLocale());

            // Dados de exemplo para preview
            $exampleData = $this->getExampleData();

            $rendered = $this->getService()->render($slug, $channel, $exampleData, $locale);

            if (!$rendered) {
                Response::json([
                    'success' => false,
                    'message' => 'Template não encontrado'
                ], 404);
                return;
            }

            Response::json([
                'success' => true,
                'data' => $rendered
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao gerar preview: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Lista variáveis disponíveis para um tipo de template
     *
     * GET /api/templates/variables/{slug}
     */
    public function getVariables(Request $request, string $slug): void
    {
        try {
            if (!Auth::can('templates.visualizar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para visualizar templates'
                ], 403);
                return;
            }

            $locale = $request->query('locale', Translator::getInstance()->getLocale());
            $variables = $this->getService()->getAvailableVariables($slug, $locale);

            Response::json([
                'success' => true,
                'data' => $variables
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar variáveis: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verifica se existe template customizado para um tipo
     */
    private function hasCustomTemplate(string $slug): bool
    {
        return $this->messageTemplateModel->hasCustom($slug, Auth::chave());
    }

    /**
     * Retorna dados de exemplo para preview
     */
    private function getExampleData(): array
    {
        return [
            'cliente' => [
                'nome' => 'João da Silva',
                'cpf_cnpj' => '123.456.789-00',
                'email' => 'joao@exemplo.com',
                'telefone' => '(11) 98765-4321',
                'endereco' => 'Rua das Flores, 123',
                'bairro' => 'Centro',
                'cidade' => 'São Paulo',
                'uf' => 'SP',
                'cep' => '01234-567',
                'cnh_numero' => '12345678900',
                'cnh_validade' => '2025-12-31',
                'preferred_locale' => 'pt_BR',
            ],
            'empresa' => [
                'razao_social' => 'Locadora Exemplo LTDA',
                'nome_fantasia' => 'Locadora Exemplo',
                'cnpj' => '12.345.678/0001-00',
                'email' => 'contato@locadora.com',
                'telefone' => '(11) 3456-7890',
                'site' => 'www.locadora.com',
                'endereco' => 'Av. Principal, 1000',
                'bairro' => 'Centro',
                'cidade' => 'São Paulo',
                'uf' => 'SP',
                'cep' => '01000-000',
                'locale' => 'pt_BR',
            ],
            'locacao' => [
                'numero' => 'LOC-2024-001234',
                'data_retirada' => '2024-12-15 10:00:00',
                'data_devolucao' => '2024-12-20 10:00:00',
                'local_retirada' => 'Matriz - Centro',
                'local_devolucao' => 'Matriz - Centro',
                'valor_total' => 750.00,
                'valor_diaria' => 150.00,
            ],
            'contrato' => [
                'numero' => 'CTR-2024-005678',
                'data_inicio' => '2024-12-15',
                'data_fim' => '2024-12-20',
                'valor_total' => 750.00,
                'valor_diaria' => 150.00,
                'quantidade_dias' => 5,
                'status' => 'ativo',
            ],
            'veiculo' => [
                'placa' => 'ABC-1234',
                'modelo' => 'Civic',
                'marca' => 'Honda',
                'ano' => '2023',
                'cor' => 'Prata',
                'renavam' => '12345678901',
            ],
            'fatura' => [
                'numero' => 'FAT-2024-009876',
                'valor' => 750.00,
                'data_vencimento' => '2024-12-25',
                'data_pagamento' => null,
                'status' => 'pendente',
                'parcela' => 2,
                'total_parcelas' => 12,
                'link_boleto' => 'https://exemplo.com/boleto/123',
                'codigo_pix' => '00020126580014br.gov.bcb.pix...',
            ],
            'outros' => [
                'data_atual' => today(),
                'hora_atual' => \App\Helpers\DateHelper::todayForDatabase('H:i:s'),
                'link_portal_cliente' => 'https://exemplo.com/portal',
            ],
        ];
    }
}
