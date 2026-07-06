<?php

/**
 * Definição de Rotas Web
 *
 * Define todas as rotas HTTP da aplicação
 */

use App\Controllers\AuthController;
use App\Controllers\ConfiguracoesController;
use App\Controllers\DashboardController;
use App\Controllers\NotificationController;
use App\Controllers\NotificacoesController;
use App\Controllers\LocalizarController;
use App\Controllers\ClientesController;
use App\Controllers\FuncionariosController;
use App\Controllers\RolesController;
use App\Controllers\MatrizFilialController;
use App\Controllers\FeriadoController;
use App\Controllers\FileController;
use App\Controllers\LocaleController;
use App\Controllers\MessageTemplateController;
use App\Controllers\LogsController;
use App\Controllers\TemporadasController;
use App\Controllers\GruposController;
use App\Controllers\VeiculosAcessoriosController;
use App\Controllers\VeiculosController;
use App\Controllers\ManutencoesPlanosController;
use App\Controllers\ManutencoesController;
use App\Controllers\FinanceiroController;
use App\Controllers\PromissoriasController;
use App\Controllers\PromissoriaTemplateController;
use App\Controllers\FornecedoresController;
use App\Controllers\OficinasController;
use App\Controllers\EstoqueController;
use App\Controllers\ComissoesInvestidoresController;
use App\Controllers\ProgramaIndicacaoController;
use App\Controllers\FeatureRequestsController;
use App\Controllers\ConcederAcessoController;
use App\Controllers\ChangelogController;
use App\Controllers\GravacoesController;
use App\Controllers\ContratosController;
use App\Controllers\CaucoesController;
use App\Controllers\LocacoesController;
use App\Controllers\AgendaController;
use App\Controllers\AssinaturaController;
use App\Controllers\ContasBancariasController;
use App\Controllers\FormasPagamentoController;
use App\Controllers\TaxasServicosController;
use App\Controllers\ChecklistModelosController;
use App\Controllers\ChecklistsController;
use App\Controllers\ChecklistNovoController;
use App\Controllers\WhatsappController;
use App\Controllers\SmsController;
use App\Controllers\SmtpController;
use App\Controllers\DocumentosController;
use App\Controllers\PromocoesController;
use App\Controllers\MultasController;
use App\Controllers\GatewaysPagamentoController;
use App\Controllers\PagamentoPublicoController;
use App\Controllers\PlanosDeContasController;
use App\Controllers\PerfilController;
use App\Controllers\PlanoController;
use App\Controllers\SerproSaldoController;
use App\Controllers\SerproConsultaController;
use App\Controllers\SerproIndicacaoController;
use App\Controllers\SerproWebhookController;
use App\Controllers\WhmcsController;
use App\Controllers\CentralMultasController;
use App\Controllers\NFSeController;
use App\Controllers\SessionController;
use App\Controllers\Relatorios\KpisController;
use App\Controllers\Relatorios\FinanceiroController as FinanceiroReportController;
use App\Controllers\WebsiteController;
use App\Controllers\PublicWebsiteController;

// Rota raiz - redireciona para login ou dashboard
$router->get('/', function ($request) {
    if (\App\Core\Auth::check()) {
        \App\Core\Response::redirect('/dashboard');
    } else {
        \App\Core\Response::redirect('/login');
    }
});

// Rotas de autenticação (apenas para visitantes)
$router->group(['middleware' => 'guest'], function ($router) {
    $router->get('/login', [AuthController::class, 'showLogin']);
    $router->post('/login', [AuthController::class, 'login'], ['csrf']);
    $router->get('/auth/redefinir-senha', [AuthController::class, 'showResetForm'], ['rate_limit']);
    $router->post('/auth/redefinir-senha', [AuthController::class, 'redefinirSenha'], ['csrf', 'rate_limit']);
    $router->post('/auth/redefinir-senha/definir', [AuthController::class, 'definirSenha'], ['csrf', 'rate_limit']);
});

// Rota de logout (requer autenticação, sem CSRF pois é ação que beneficia o usuário)
$router->post('/logout', [AuthController::class, 'logout'], ['auth']);

// Rota pública para servir arquivos via token (não requer autenticação)
$router->get('/files/{token}', [FileController::class, 'serve'], ['rate_limit']);

// Rota pública para assinatura de contratos (não requer autenticação)
$router->get('/assinar/{codigo}', [AssinaturaController::class, 'view'], ['rate_limit']);
$router->post('/assinar/{codigo}', [AssinaturaController::class, 'assinar'], ['rate_limit']);

// Rota pública para changelog na tela de login (não requer autenticação)
$router->get('/api/public/changelog', [ChangelogController::class, 'publicIndex'], ['rate_limit']);

// Rotas públicas para pagamento (não requer autenticação)
$router->get('/pagar/{codigo}', [PagamentoPublicoController::class, 'index'], ['rate_limit']);
$router->post('/pagar/{codigo}/processar', [PagamentoPublicoController::class, 'processar'], ['rate_limit']);
$router->get('/pagar/{codigo}/status', [PagamentoPublicoController::class, 'status'], ['rate_limit']);
$router->get('/pagar/{codigo}/gateway/{gatewayId}/capabilities', [PagamentoPublicoController::class, 'gatewayCapabilities'], ['rate_limit']);
$router->get('/pagar/{codigo}/cartoes', [PagamentoPublicoController::class, 'listarCartoes'], ['rate_limit']);
$router->post('/pagar/{codigo}/tokenizar', [PagamentoPublicoController::class, 'tokenizar'], ['rate_limit']);
$router->post('/pagar/{codigo}/salvar-cartao', [PagamentoPublicoController::class, 'salvarCartao'], ['rate_limit']);

// Rotas públicas para verificação (não requer autenticação)
$router->get('/verificar/checklist/{codigo}', [ChecklistsController::class, 'verificarPublico'], ['rate_limit']);
$router->get('/verificar/contrato/{codigo}', [ContratosController::class, 'verificarPublico'], ['rate_limit']);
$router->get('/verificar/fatura/{token}', [FinanceiroController::class, 'verificarPublico'], ['rate_limit']);
$router->get('/verificar/multa/{token}', [MultasController::class, 'verificarPublico'], ['rate_limit']);

// Webhooks de gateways de pagamento (não requer autenticação, sem CSRF)
$router->get('/webhook/asaas', [PagamentoPublicoController::class, 'webhookAsaasInfo'], ['rate_limit']);
$router->get('/webhook/stripe', [PagamentoPublicoController::class, 'webhookStripeInfo'], ['rate_limit']);
$router->get('/webhook/square', [PagamentoPublicoController::class, 'webhookSquareInfo'], ['rate_limit']);
$router->get('/webhook/cora', [PagamentoPublicoController::class, 'webhookCoraInfo'], ['rate_limit']);
$router->get('/webhook/efipay', [PagamentoPublicoController::class, 'webhookEfipayInfo'], ['rate_limit']);
$router->get('/webhook/inter', [PagamentoPublicoController::class, 'webhookInterInfo'], ['rate_limit']);
$router->get('/webhook/sicoob', [PagamentoPublicoController::class, 'webhookSicoobInfo'], ['rate_limit']);
$router->get('/webhook/bradesco', [PagamentoPublicoController::class, 'webhookBradescoInfo'], ['rate_limit']);
$router->get('/webhook/itau', [PagamentoPublicoController::class, 'webhookItauInfo'], ['rate_limit']);
$router->get('/webhook/bancard', [PagamentoPublicoController::class, 'webhookBancardInfo'], ['rate_limit']);
$router->get('/webhook/pagopar', [PagamentoPublicoController::class, 'webhookPagoparInfo'], ['rate_limit']);
$router->post('/webhook/asaas', [PagamentoPublicoController::class, 'webhookAsaas'], ['rate_limit']);
$router->post('/webhook/stripe', [PagamentoPublicoController::class, 'webhookStripe'], ['rate_limit']);
$router->post('/webhook/square', [PagamentoPublicoController::class, 'webhookSquare'], ['rate_limit']);
$router->post('/webhook/cora', [PagamentoPublicoController::class, 'webhookCora'], ['rate_limit']);
$router->post('/webhook/efipay', [PagamentoPublicoController::class, 'webhookEfipay'], ['rate_limit']);
$router->post('/webhook/inter', [PagamentoPublicoController::class, 'webhookInter'], ['rate_limit']);
$router->post('/webhook/sicoob', [PagamentoPublicoController::class, 'webhookSicoob'], ['rate_limit']);
$router->post('/webhook/bradesco', [PagamentoPublicoController::class, 'webhookBradesco'], ['rate_limit']);
$router->post('/webhook/itau', [PagamentoPublicoController::class, 'webhookItau'], ['rate_limit']);
$router->post('/webhook/bancard', [PagamentoPublicoController::class, 'webhookBancard'], ['rate_limit']);
$router->post('/webhook/pagopar', [PagamentoPublicoController::class, 'webhookPagopar'], ['rate_limit']);

// Webhooks Multas Online (não requer autenticação, sem CSRF)
$router->post('/webhook/multas-online/pix', [SerproWebhookController::class, 'webhookPix'], ['rate_limit']);
$router->post('/webhook/multas-online/stripe', [SerproWebhookController::class, 'webhookStripe'], ['rate_limit']);
$router->post('/webhook/multas-online/eventos', [SerproWebhookController::class, 'webhookEventos'], ['rate_limit']);

// Webhooks WHMCS - Provisionamento de tenants (autenticação via TENANT_ONBOARD_SECRET)
$router->post('/webhook/whmcs/criar', [WhmcsController::class, 'criar'], ['whmcs_auth', 'rate_limit']);
$router->post('/webhook/whmcs/suspender', [WhmcsController::class, 'suspender'], ['whmcs_auth', 'rate_limit']);
$router->post('/webhook/whmcs/reativar', [WhmcsController::class, 'reativar'], ['whmcs_auth', 'rate_limit']);
$router->post('/webhook/whmcs/mudar-pacote', [WhmcsController::class, 'mudarPacote'], ['whmcs_auth', 'rate_limit']);
$router->post('/webhook/whmcs/atualizar-senha', [WhmcsController::class, 'atualizarSenha'], ['whmcs_auth', 'rate_limit']);
$router->post('/webhook/whmcs/terminar', [WhmcsController::class, 'terminar'], ['whmcs_auth', 'rate_limit']);
$router->post('/webhook/whmcs/veiculos-disponibilidade', [WhmcsController::class, 'veiculosDisponibilidade'], ['whmcs_auth', 'rate_limit']);

// Webhook WHMCS - Ativação de Website (público, sem auth de sessão)
$router->get('/api/webhook/whmcs/site-ativacao', [WebsiteController::class, 'webhookWhmcsAtivacao'], ['rate_limit']);

// API pública do Website (sem auth de sessão, com rate limit)
$router->get('/api/public/dados-site', [PublicWebsiteController::class, 'dadosSite'], ['rate_limit']);
$router->get('/api/public/disponibilidade', [PublicWebsiteController::class, 'disponibilidade'], ['rate_limit']);
$router->get('/api/public/cliente-por-documento', [PublicWebsiteController::class, 'buscarClientePorDocumento'], ['rate_limit']);
$router->get('/api/public/cliente-existe', [PublicWebsiteController::class, 'clienteExiste'], ['rate_limit']);
$router->post('/api/public/cliente-login', [PublicWebsiteController::class, 'clienteLogin'], ['rate_limit']);
$router->post('/api/public/cliente-senha-reset', [PublicWebsiteController::class, 'clienteSenhaReset'], ['rate_limit']);
$router->get('/public/redefinir-senha', [PublicWebsiteController::class, 'exibirFormResetSenha'], ['rate_limit']);
$router->post('/api/public/cliente-senha-definir', [PublicWebsiteController::class, 'clienteSenhaDefinir'], ['rate_limit']);
$router->get('/api/public/conteudos', [PublicWebsiteController::class, 'conteudos'], ['rate_limit']);
$router->get('/api/public/status', [PublicWebsiteController::class, 'status'], ['rate_limit']);
$router->post('/api/public/reserva', [PublicWebsiteController::class, 'criarReserva'], ['rate_limit']);
$router->post('/api/public/contato', [PublicWebsiteController::class, 'contato'], ['rate_limit']);
$router->post('/api/public/limpar-cache', [PublicWebsiteController::class, 'limparCache'], ['rate_limit']);

// Rotas protegidas (requerem autenticação)
$router->group(['middleware' => 'auth'], function ($router) {
    // Dashboard
    $router->get('/dashboard', [DashboardController::class, 'index']);
    $router->get('/api/dashboard/stats', [DashboardController::class, 'stats'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/dashboard/subtabs/{tab}', [DashboardController::class, 'subtab'], ['api_csrf', 'rate_limit', 'throttle']);

    // Notifications
    $router->get('/api/notifications/counts', [NotificationController::class, 'counts'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/pages/notificacoes', [NotificacoesController::class, 'view']);
    $router->get('/api/notifications/list', [NotificacoesController::class, 'list'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/caucoes/{origem}/{id}/devolver', [CaucoesController::class, 'devolver'], ['api_csrf', 'rate_limit', 'throttle']);

    // Session refresh (sem api_csrf - usado quando token CSRF expira)
    $router->get('/api/session/refresh', [SessionController::class, 'refresh']);

    // Página de limite atingido (plano)
    $router->get('/pages/limite-atingido', [PlanoController::class, 'viewLimiteAtingido']);

    // API de verificação de limite do plano
    $router->get('/api/plano/verificar-limite', [PlanoController::class, 'verificarLimite'], ['api_csrf', 'rate_limit', 'throttle']);

    // Páginas iframe (módulos)
    $router->get('/pages/clientes', function ($request) {
        $html = \App\Views\Template::render('pages.clientes.index');
        \App\Core\Response::html($html);
    });

    $router->get('/pages/clientes/adicionar', function ($request) {
        $paisModel = new \App\Models\Pais();
        $html = \App\Views\Template::render('pages.clientes.adicionar', [
            'paises' => $paisModel->listarAtivos(),
        ]);
        \App\Core\Response::html($html);
    });

    // Páginas iframe - Funcionários
    $router->get('/pages/funcionarios', function ($request) {
        $html = \App\Views\Template::render('pages.funcionarios.index');
        \App\Core\Response::html($html);
    });

    $router->get('/pages/funcionarios/adicionar', function ($request) {
        $paisModel = new \App\Models\Pais();
        $html = \App\Views\Template::render('pages.funcionarios.adicionar', [
            'paises' => $paisModel->listarAtivos(),
        ]);
        \App\Core\Response::html($html);
    });

    // Rota de edição redireciona para adicionar com parâmetro id
    $router->get('/pages/funcionarios/editar/{id}', function ($request, $id) {
        $queryString = !empty($_SERVER['QUERY_STRING']) ? '&' . $_SERVER['QUERY_STRING'] : '';
        \App\Core\Response::redirect("/pages/funcionarios/adicionar?id={$id}{$queryString}");
    });

    // API Clientes (com proteção anti-scraping e CSRF)
    $router->get('/api/clientes', [ClientesController::class, 'index'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/clientes/buscar', [ClientesController::class, 'buscar'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/clientes/por-documento', [ClientesController::class, 'buscarPorDocumento'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/clientes/{id}/financeiro', [ClientesController::class, 'financeiro'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/clientes/financeiro/{id}/cobranca', [ClientesController::class, 'enviarCobrancaFinanceiro'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/clientes/{id}', [ClientesController::class, 'show'], ['api_csrf', 'rate_limit', 'throttle']);

    // API Arquivos de Clientes
    $router->get('/api/clientes/{id}/arquivos', [ClientesController::class, 'arquivos'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/clientes/{id}/arquivos', [ClientesController::class, 'uploadArquivo'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/clientes/{id}/arquivos/{arquivoId}/excluir', [ClientesController::class, 'excluirArquivo'], ['api_csrf', 'rate_limit', 'throttle']);

    // API Cartões de Crédito de Clientes
    $router->get('/api/clientes/{id}/cartoes', [ClientesController::class, 'cartoes'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/clientes/{id}/gateways-cartao', [ClientesController::class, 'gatewaysCartao'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/clientes/{id}/cartoes/tokenizar', [ClientesController::class, 'tokenizarCartao'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/clientes/{id}/cartoes/{cartaoId}/desativar', [ClientesController::class, 'desativarCartao'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/clientes/{id}/cartoes/{cartaoId}/padrao', [ClientesController::class, 'definirCartaoPadrao'], ['api_csrf', 'rate_limit', 'throttle']);

    // CRUD Clientes
    $router->post('/clientes/salvar', [ClientesController::class, 'store'], ['csrf', 'rate_limit']);
    $router->post('/clientes/{id}/atualizar', [ClientesController::class, 'update'], ['csrf', 'rate_limit']);
    $router->post('/clientes/{id}/excluir', [ClientesController::class, 'destroy'], ['csrf', 'rate_limit']);

    // API Funcionários (com proteção anti-scraping e CSRF)
    $router->get('/api/funcionarios', [FuncionariosController::class, 'index'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/funcionarios/buscar', [FuncionariosController::class, 'buscar'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/funcionarios/roles', [FuncionariosController::class, 'roles'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/funcionarios/check-usuario', [FuncionariosController::class, 'checkUsuario'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/funcionarios/{id}', [FuncionariosController::class, 'show'], ['api_csrf', 'rate_limit', 'throttle']);

    // CRUD Funcionários
    $router->post('/funcionarios/salvar', [FuncionariosController::class, 'store'], ['csrf', 'rate_limit']);
    $router->post('/funcionarios/{id}/atualizar', [FuncionariosController::class, 'update'], ['csrf', 'rate_limit']);
    $router->post('/funcionarios/{id}/excluir', [FuncionariosController::class, 'destroy'], ['csrf', 'rate_limit']);

    // Páginas iframe - Perfil do Usuário
    $router->get('/pages/perfil', function ($request) {
        $html = \App\Views\Template::render('pages.perfil.index');
        \App\Core\Response::html($html);
    });

    // API Perfil (com proteção CSRF)
    $router->get('/api/perfil', [PerfilController::class, 'show'], ['api_csrf', 'rate_limit', 'throttle']);

    // CRUD Perfil
    $router->post('/perfil/atualizar', [PerfilController::class, 'update'], ['csrf', 'rate_limit']);
    $router->post('/perfil/alterar-senha', [PerfilController::class, 'alterarSenha'], ['csrf', 'rate_limit']);

    // Páginas iframe - Roles (Funções)
    $router->get('/pages/roles/gerenciar', [RolesController::class, 'gerenciar']);
    $router->get('/pages/roles/adicionar', [RolesController::class, 'adicionar']);

    // Rota de edição redireciona para adicionar com parâmetro id
    $router->get('/pages/roles/editar/{id}', function ($request, $id) {
        $queryString = !empty($_SERVER['QUERY_STRING']) ? '&' . $_SERVER['QUERY_STRING'] : '';
        \App\Core\Response::redirect("/pages/roles/adicionar?id={$id}{$queryString}");
    });

    // API Roles (com proteção anti-scraping e CSRF)
    $router->get('/api/roles', [RolesController::class, 'index'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/roles/{id}/permissions', [RolesController::class, 'rolePermissions'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/permissions', [RolesController::class, 'permissions'], ['api_csrf', 'rate_limit', 'throttle']);

    // CRUD Roles
    $router->post('/roles/salvar', [RolesController::class, 'store'], ['csrf', 'rate_limit']);
    $router->post('/roles/{id}/atualizar', [RolesController::class, 'update'], ['csrf', 'rate_limit']);
    $router->post('/roles/{id}/excluir', [RolesController::class, 'destroy'], ['csrf', 'rate_limit']);
    $router->post('/roles/{id}/restaurar', [RolesController::class, 'restore'], ['csrf', 'rate_limit']);

    // Paginas iframe - Matrizes/Filiais
    $router->get('/pages/matrizes-filiais', function ($request) {
        $html = \App\Views\Template::render('pages.matrizes-filiais.index');
        \App\Core\Response::html($html);
    });

    $router->get('/pages/matrizes-filiais/adicionar', function ($request) {
        // Verificar limite do plano para criação
        $id = $_GET['id'] ?? null;
        if ($id === null) {
            $redirectUrl = \App\Helpers\PlanoLimiteHelper::getRedirectSeAtingido('matrizfilial');
            if ($redirectUrl) {
                \App\Core\Response::redirect($redirectUrl);
                return;
            }
        }
        $paisModel = new \App\Models\Pais();
        $html = \App\Views\Template::render('pages.matrizes-filiais.adicionar', [
            'paises' => $paisModel->listarAtivos(),
        ]);
        \App\Core\Response::html($html);
    });

    // API Matrizes/Filiais (com protecao anti-scraping e CSRF)
    $router->get('/api/matrizes-filiais', [MatrizFilialController::class, 'index'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/matrizes-filiais/buscar', [MatrizFilialController::class, 'buscar'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/matrizes-filiais/opcoes', [MatrizFilialController::class, 'opcoes'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/matrizes-filiais/distancia', [MatrizFilialController::class, 'calcularDistancia'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/matrizes-filiais/{id}', [MatrizFilialController::class, 'show'], ['api_csrf', 'rate_limit', 'throttle']);

    // API Feriados
    $router->get('/api/feriados/buscar', [FeriadoController::class, 'buscar'], ['api_csrf', 'rate_limit', 'throttle']);

    // CRUD Matrizes/Filiais
    $router->post('/matrizes-filiais/salvar', [MatrizFilialController::class, 'store'], ['csrf', 'rate_limit']);
    $router->post('/matrizes-filiais/{id}/atualizar', [MatrizFilialController::class, 'update'], ['csrf', 'rate_limit']);
    $router->post('/matrizes-filiais/{id}/excluir', [MatrizFilialController::class, 'destroy'], ['csrf', 'rate_limit']);
    $router->post('/matrizes-filiais/{id}/desativar', [MatrizFilialController::class, 'desativar'], ['csrf', 'rate_limit']);

    // API Locale (Internacionalização)
    $router->post('/api/locale/set', [LocaleController::class, 'set'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/locale/current', [LocaleController::class, 'current'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/locale/supported', [LocaleController::class, 'supported'], ['api_csrf', 'rate_limit', 'throttle']);

    // Páginas iframe - Configurações Gerais
    $router->get('/pages/configuracoes/gerais', function ($request) {
        $html = \App\Views\Template::render('pages.configuracoes.gerais');
        \App\Core\Response::html($html);
    });

    // API Configurações Gerais
    $router->get('/api/configuracoes/gerais', [ConfiguracoesController::class, 'show'], ['api_csrf', 'rate_limit', 'throttle']);

    // CRUD Configurações Gerais
    $router->post('/configuracoes/gerais/salvar', [ConfiguracoesController::class, 'update'], ['csrf', 'rate_limit']);

    // API Busca Global (Spotlight)
    $router->get('/api/localizar', [LocalizarController::class, 'search'], ['api_csrf', 'rate_limit', 'throttle']);

    // Páginas iframe - Templates de Mensagem
    $router->get('/pages/configuracoes/templates', [MessageTemplateController::class, 'index']);
    $router->get('/pages/configuracoes/templates/{slug}', [MessageTemplateController::class, 'edit']);

    // API Templates de Mensagem (com proteção anti-scraping e CSRF)
    $router->get('/api/templates/types', [MessageTemplateController::class, 'getTypes'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/templates/variables/{slug}', [MessageTemplateController::class, 'getVariables'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/templates/{slug}/preview', [MessageTemplateController::class, 'preview'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/templates/{slug}', [MessageTemplateController::class, 'getTemplate'], ['api_csrf', 'rate_limit', 'throttle']);

    // CRUD Templates de Mensagem
    $router->post('/api/templates/{slug}', [MessageTemplateController::class, 'saveTemplate'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/templates/{slug}/restore', [MessageTemplateController::class, 'restoreDefault'], ['api_csrf', 'rate_limit', 'throttle']);

    // Páginas iframe - Logs (Auditoria)
    $router->get('/pages/logs', function ($request) {
        $html = \App\Views\Template::render('pages.logs.index');
        \App\Core\Response::html($html);
    });

    // API Logs (com proteção anti-scraping e CSRF)
    $router->get('/api/logs', [LogsController::class, 'index'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/logs/envios', [LogsController::class, 'envios'], ['api_csrf', 'rate_limit', 'throttle']);

    // Paginas iframe - Temporadas
    $router->get('/pages/temporadas', [TemporadasController::class, 'view']);
    $router->get('/pages/temporadas/adicionar', function ($request) {
        $html = \App\Views\Template::render('pages.temporadas.adicionar');
        \App\Core\Response::html($html);
    });
    $router->get('/pages/temporadas/templates', function ($request) {
        $html = \App\Views\Template::render('pages.temporadas.templates');
        \App\Core\Response::html($html);
    });

    // API Temporadas (com protecao anti-scraping e CSRF)
    $router->get('/api/temporadas', [TemporadasController::class, 'index'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/temporadas/templates', [TemporadasController::class, 'templates'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/temporadas/grupos', [TemporadasController::class, 'grupos'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/temporadas/{id}', [TemporadasController::class, 'show'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/temporadas/{id}/ajustes', [TemporadasController::class, 'ajustes'], ['api_csrf', 'rate_limit', 'throttle']);

    // CRUD Temporadas
    $router->post('/temporadas/salvar', [TemporadasController::class, 'store'], ['csrf', 'rate_limit']);
    $router->post('/temporadas/{id}/atualizar', [TemporadasController::class, 'update'], ['csrf', 'rate_limit']);
    $router->post('/temporadas/{id}/excluir', [TemporadasController::class, 'destroy'], ['csrf', 'rate_limit']);
    $router->post('/temporadas/ativar-template', [TemporadasController::class, 'ativarTemplate'], ['csrf', 'rate_limit']);
    $router->post('/temporadas/{id}/ajustes', [TemporadasController::class, 'salvarAjustes'], ['csrf', 'rate_limit']);

    // Paginas iframe - Grupos
    $router->get('/pages/grupos', [GruposController::class, 'view']);
    $router->get('/pages/grupos/adicionar', function ($request) {
        $html = \App\Views\Template::render('pages.grupos.adicionar');
        \App\Core\Response::html($html);
    });

    // API Grupos (com protecao anti-scraping e CSRF)
    $router->get('/api/grupos', [GruposController::class, 'index'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/grupos/{id}', [GruposController::class, 'show'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/grupos/{id}/precos-filial/{idFilial}', [GruposController::class, 'precosFilial'], ['api_csrf', 'rate_limit', 'throttle']);

    // CRUD Grupos
    $router->post('/grupos/salvar', [GruposController::class, 'store'], ['csrf', 'rate_limit']);
    $router->post('/grupos/{id}/atualizar', [GruposController::class, 'update'], ['csrf', 'rate_limit']);
    $router->post('/grupos/{id}/excluir', [GruposController::class, 'destroy'], ['csrf', 'rate_limit']);
    $router->post('/grupos/{id}/precos-filial/{idFilial}', [GruposController::class, 'salvarPrecosFilial'], ['csrf', 'rate_limit']);

    // Paginas iframe - Contas Bancarias
    $router->get('/pages/contas-bancarias', [ContasBancariasController::class, 'view']);
    $router->get('/pages/contas-bancarias/adicionar', [ContasBancariasController::class, 'viewAdicionar']);

    // API Contas Bancarias (com protecao anti-scraping e CSRF)
    $router->get('/api/contas-bancarias', [ContasBancariasController::class, 'index'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/contas-bancarias/buscar', [ContasBancariasController::class, 'buscar'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/contas-bancarias/{id}', [ContasBancariasController::class, 'show'], ['api_csrf', 'rate_limit', 'throttle']);

    // CRUD Contas Bancarias
    $router->post('/contas-bancarias/salvar', [ContasBancariasController::class, 'store'], ['csrf', 'rate_limit']);
    $router->post('/contas-bancarias/{id}/atualizar', [ContasBancariasController::class, 'update'], ['csrf', 'rate_limit']);
    $router->post('/contas-bancarias/{id}/excluir', [ContasBancariasController::class, 'destroy'], ['csrf', 'rate_limit']);

    // Paginas iframe - Formas de Pagamento
    $router->get('/pages/formas-pagamento', [FormasPagamentoController::class, 'view']);
    $router->get('/pages/formas-pagamento/adicionar', [FormasPagamentoController::class, 'viewAdicionar']);

    // API Formas de Pagamento (com protecao anti-scraping e CSRF)
    $router->get('/api/formas-pagamento', [FormasPagamentoController::class, 'index'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/formas-pagamento/select', [FormasPagamentoController::class, 'indexSelect'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/formas-pagamento/{id}', [FormasPagamentoController::class, 'show'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/formas-pagamento/{id}/calcular-taxas', [FormasPagamentoController::class, 'calcularTaxas'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/formas-pagamento/{id}/calcular-desconto', [FormasPagamentoController::class, 'calcularDesconto'], ['api_csrf', 'rate_limit', 'throttle']);

    // CRUD Formas de Pagamento
    $router->post('/formas-pagamento/salvar', [FormasPagamentoController::class, 'store'], ['csrf', 'rate_limit']);
    $router->post('/formas-pagamento/{id}/atualizar', [FormasPagamentoController::class, 'update'], ['csrf', 'rate_limit']);
    $router->post('/formas-pagamento/{id}/excluir', [FormasPagamentoController::class, 'destroy'], ['csrf', 'rate_limit']);

    // Paginas iframe - Comandos de Parcelas
    $router->get('/pages/comandos-parcelas', [FormasPagamentoController::class, 'viewComandos']);

    // API Comandos de Parcelas (com protecao anti-scraping e CSRF)
    $router->get('/api/comandos-parcelas', [FormasPagamentoController::class, 'indexComandos'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/comandos-parcelas/select', [FormasPagamentoController::class, 'indexComandosParaSelect'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/comandos-parcelas/{id}', [FormasPagamentoController::class, 'showComando'], ['api_csrf', 'rate_limit', 'throttle']);

    // CRUD Comandos de Parcelas
    $router->post('/comandos-parcelas/salvar', [FormasPagamentoController::class, 'storeComando'], ['csrf', 'rate_limit']);
    $router->post('/comandos-parcelas/{id}/atualizar', [FormasPagamentoController::class, 'updateComando'], ['csrf', 'rate_limit']);
    $router->post('/comandos-parcelas/{id}/excluir', [FormasPagamentoController::class, 'destroyComando'], ['csrf', 'rate_limit']);

    // Paginas iframe - Gateways de Pagamento
    $router->get('/pages/gateways-pagamento', [GatewaysPagamentoController::class, 'view']);
    $router->get('/pages/gateways-pagamento/adicionar', [GatewaysPagamentoController::class, 'viewAdicionar']);

    // API Gateways de Pagamento (com protecao anti-scraping e CSRF)
    $router->get('/api/gateways-pagamento', [GatewaysPagamentoController::class, 'index'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/gateways-pagamento/disponiveis', [GatewaysPagamentoController::class, 'disponiveis'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/gateways-pagamento/{id}', [GatewaysPagamentoController::class, 'show'], ['api_csrf', 'rate_limit', 'throttle']);

    // CRUD Gateways de Pagamento
    $router->post('/gateways-pagamento/salvar', [GatewaysPagamentoController::class, 'store'], ['csrf', 'rate_limit']);
    $router->post('/gateways-pagamento/{id}/atualizar', [GatewaysPagamentoController::class, 'update'], ['csrf', 'rate_limit']);
    $router->post('/gateways-pagamento/{id}/excluir', [GatewaysPagamentoController::class, 'destroy'], ['csrf', 'rate_limit']);
    $router->post('/gateways-pagamento/{id}/status', [GatewaysPagamentoController::class, 'alterarStatus'], ['csrf', 'rate_limit']);
    $router->post('/gateways-pagamento/{id}/certificado', [GatewaysPagamentoController::class, 'uploadCertificado'], ['csrf', 'rate_limit']);
    $router->post('/gateways-pagamento/{id}/certificado/remover', [GatewaysPagamentoController::class, 'removerCertificado'], ['csrf', 'rate_limit']);
    $router->post('/api/gateways-pagamento/{id}/testar', [GatewaysPagamentoController::class, 'testar'], ['api_csrf', 'rate_limit', 'throttle']);

    // API Links de Pagamento
    $router->get('/api/pagamentos-links', [GatewaysPagamentoController::class, 'linksIndex'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/pagamentos-links/gerar', [GatewaysPagamentoController::class, 'gerarLink'], ['api_csrf', 'rate_limit', 'throttle']);

    // Paginas iframe - Planos de Contas
    $router->get('/pages/planos-de-contas', [PlanosDeContasController::class, 'view']);
    $router->get('/pages/planos-de-contas/adicionar', [PlanosDeContasController::class, 'viewAdicionar']);

    // API Planos de Contas (com protecao anti-scraping e CSRF)
    $router->get('/api/planos-de-contas', [PlanosDeContasController::class, 'index'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/planos-de-contas/buscar', [PlanosDeContasController::class, 'buscar'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/planos-de-contas/tipos', [PlanosDeContasController::class, 'tipos'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/planos-de-contas/por-tipo', [PlanosDeContasController::class, 'listarPorTipo'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/planos-de-contas/proximo-codigo', [PlanosDeContasController::class, 'proximoCodigo'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/planos-de-contas/validar-codigo', [PlanosDeContasController::class, 'validarCodigo'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/planos-de-contas/{id}', [PlanosDeContasController::class, 'show'], ['api_csrf', 'rate_limit', 'throttle']);

    // CRUD Planos de Contas
    $router->post('/planos-de-contas/salvar', [PlanosDeContasController::class, 'store'], ['csrf', 'rate_limit']);
    $router->post('/planos-de-contas/{id}/atualizar', [PlanosDeContasController::class, 'update'], ['csrf', 'rate_limit']);
    $router->post('/planos-de-contas/{id}/excluir', [PlanosDeContasController::class, 'destroy'], ['csrf', 'rate_limit']);

    // Paginas iframe - Taxas e Servicos
    $router->get('/pages/taxas-e-servicos', [TaxasServicosController::class, 'view']);
    $router->get('/pages/taxas-e-servicos/adicionar', [TaxasServicosController::class, 'viewAdicionar']);

    // API Taxas e Servicos (com protecao anti-scraping e CSRF)
    $router->get('/api/taxas-e-servicos', [TaxasServicosController::class, 'index'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/taxas-e-servicos/select', [TaxasServicosController::class, 'selectOptions'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/taxas-e-servicos/buscar', [TaxasServicosController::class, 'buscar'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/taxas-e-servicos/auto-aplicar', [TaxasServicosController::class, 'autoAplicar'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/taxas-e-servicos/{id}', [TaxasServicosController::class, 'show'], ['api_csrf', 'rate_limit', 'throttle']);

    // CRUD Taxas e Servicos
    $router->post('/taxas-e-servicos/salvar', [TaxasServicosController::class, 'store'], ['csrf', 'rate_limit']);
    $router->post('/taxas-e-servicos/{id}/atualizar', [TaxasServicosController::class, 'update'], ['csrf', 'rate_limit']);
    $router->post('/taxas-e-servicos/{id}/excluir', [TaxasServicosController::class, 'destroy'], ['csrf', 'rate_limit']);

    // Paginas iframe - Checklist Modelos
    $router->get('/pages/checklist-modelos', [ChecklistModelosController::class, 'view']);
    $router->get('/pages/checklist-modelos/adicionar', [ChecklistModelosController::class, 'viewAdicionar']);

    // API Checklist Modelos
    $router->get('/api/checklist-modelos', [ChecklistModelosController::class, 'index'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/checklist-modelos/buscar', [ChecklistModelosController::class, 'buscar'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/checklist-modelos/{id}', [ChecklistModelosController::class, 'show'], ['api_csrf', 'rate_limit', 'throttle']);

    // CRUD Checklist Modelos
    $router->post('/checklist-modelos/salvar', [ChecklistModelosController::class, 'store'], ['csrf', 'rate_limit']);
    $router->post('/checklist-modelos/{id}/atualizar', [ChecklistModelosController::class, 'update'], ['csrf', 'rate_limit']);
    $router->post('/checklist-modelos/{id}/excluir', [ChecklistModelosController::class, 'destroy'], ['csrf', 'rate_limit']);

    // Paginas iframe - Checklists
    $router->get('/pages/checklists', [ChecklistsController::class, 'view']);

    // API Checklists
    $router->get('/api/checklists', [ChecklistsController::class, 'index'], ['api_csrf', 'rate_limit', 'throttle']);

    // Checklist Digital - Paginas standalone mobile (devem ficar ANTES das rotas com {id})
    $router->get('/checklists/digital', [ChecklistNovoController::class, 'viewDigital']);
    $router->get('/checklists/vinculados', [ChecklistNovoController::class, 'viewVinculados']);
    $router->get('/checklists/novo', [ChecklistNovoController::class, 'viewNovo']);
    $router->get('/checklists/visualizar/{id}', [ChecklistNovoController::class, 'viewVisualizar']);

    // Impressao Checklist (PDF)
    $router->get('/checklists/{id}/imprimir', [ChecklistsController::class, 'imprimir']);

    // Exclusao Checklist
    $router->post('/checklists/{id}/excluir', [ChecklistsController::class, 'destroy'], ['csrf', 'rate_limit']);

    // API Checklist Novo - CRUD
    $router->post('/api/checklists/criar', [ChecklistNovoController::class, 'criar'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/checklists/{id}/questoes', [ChecklistNovoController::class, 'salvarQuestoes'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/checklists/{id}/vistoria/upload', [ChecklistNovoController::class, 'uploadVistoria'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/checklists/{id}/vistoria/{itemId}/excluir', [ChecklistNovoController::class, 'excluirVistoria'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/checklists/{id}/assinar', [ChecklistNovoController::class, 'assinar'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/checklists/novo/{id}', [ChecklistNovoController::class, 'show'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/checklists/buscar-locacoes', [ChecklistNovoController::class, 'buscarLocacoes'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/checklists/buscar-contratos', [ChecklistNovoController::class, 'buscarContratos'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/checklists/buscar-veiculos', [ChecklistNovoController::class, 'buscarVeiculos'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/checklists/buscar-vinculos', [ChecklistNovoController::class, 'buscarVinculos'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/checklists/vinculados', [ChecklistNovoController::class, 'vinculadosPendentes'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/checklists/veiculos-vinculo', [ChecklistNovoController::class, 'veiculosVinculo'], ['api_csrf', 'rate_limit', 'throttle']);

    // Paginas iframe - Promocoes
    $router->get('/pages/promocoes', [PromocoesController::class, 'view']);
    $router->get('/pages/promocoes/adicionar', [PromocoesController::class, 'viewAdicionar']);

    // API Promocoes (com protecao anti-scraping e CSRF)
    $router->get('/api/promocoes', [PromocoesController::class, 'index'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/promocoes/buscar', [PromocoesController::class, 'buscar'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/promocoes/{id}', [PromocoesController::class, 'show'], ['api_csrf', 'rate_limit', 'throttle']);

    // CRUD Promocoes
    $router->post('/promocoes/salvar', [PromocoesController::class, 'store'], ['csrf', 'rate_limit']);
    $router->post('/promocoes/{id}/atualizar', [PromocoesController::class, 'update'], ['csrf', 'rate_limit']);
    $router->post('/promocoes/{id}/excluir', [PromocoesController::class, 'destroy'], ['csrf', 'rate_limit']);

    // Paginas iframe - Multas
    $router->get('/pages/multas/adicionar', [MultasController::class, 'viewAdicionar']);

    // API Multas
    $router->get('/api/multas/{id}', [MultasController::class, 'show'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/multas/buscar-responsavel', [MultasController::class, 'buscarResponsavel'], ['api_csrf', 'rate_limit', 'throttle']);

    // CRUD Multas
    $router->post('/multas/salvar', [MultasController::class, 'store'], ['csrf', 'rate_limit']);
    $router->post('/multas/{id}/atualizar', [MultasController::class, 'update'], ['csrf', 'rate_limit']);
    $router->post('/multas/{id}/excluir', [MultasController::class, 'destroy'], ['csrf', 'rate_limit']);
    $router->post('/multas/{id}/marcar-pago', [MultasController::class, 'marcarPago'], ['csrf', 'rate_limit']);
    $router->post('/multas/{id}/marcar-nao-pago', [MultasController::class, 'marcarNaoPago'], ['csrf', 'rate_limit']);

    // Impressao de Multas
    $router->get('/pages/multas/offcanvas-impressao', [MultasController::class, 'offcanvasImpressao']);
    $router->get('/multas/{id}/imprimir', [MultasController::class, 'imprimir']);
    $router->post('/multas/{id}/enviar', [MultasController::class, 'enviarMulta'], ['csrf', 'rate_limit']);

    // Paginas iframe - Saldo Consultas
    $router->get('/pages/multas-online/saldo', [SerproSaldoController::class, 'view']);

    // API Saldo Consultas
    $router->get('/api/multas-online/saldo', [SerproSaldoController::class, 'index'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/multas-online/transacoes', [SerproSaldoController::class, 'transacoes'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/multas-online/transacoes/{id}/pix', [SerproSaldoController::class, 'pixRecarga'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/multas-online/transacoes/{id}/pix/status', [SerproSaldoController::class, 'statusPixRecarga'], ['api_csrf', 'rate_limit', 'throttle']);

    // Recargas Saldo
    $router->post('/multas-online/saldo/recarregar-pix', [SerproSaldoController::class, 'recarregarPix'], ['csrf', 'rate_limit']);
    $router->post('/multas-online/saldo/recarregar-stripe', [SerproSaldoController::class, 'recarregarStripe'], ['csrf', 'rate_limit']);
    $router->post('/multas-online/saldo/confirmar-stripe', [SerproSaldoController::class, 'confirmarStripe'], ['csrf', 'rate_limit']);
    $router->post('/multas-online/saldo/auto-recarga', [SerproSaldoController::class, 'atualizarAutoRecarga'], ['csrf', 'rate_limit']);

    // API Consultas Online
    $router->post('/api/multas-online/consultar-infracoes', [SerproConsultaController::class, 'consultarInfracoes'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/multas-online/consultar-lote', [SerproConsultaController::class, 'consultarLote'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/multas-online/pdf/{tipo}', [SerproConsultaController::class, 'downloadPdf'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/multas-online/veiculo/{placa}', [SerproConsultaController::class, 'dadosVeiculo'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/multas-online/crlv/{placa}', [SerproConsultaController::class, 'crlv'], ['api_csrf', 'rate_limit', 'throttle']);

    // API Configuracao Consultas
    $router->get('/api/multas-online/configuracao', [SerproConsultaController::class, 'getConfiguracao'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/multas-online/configuracao/salvar', [SerproConsultaController::class, 'salvarConfiguracao'], ['csrf', 'rate_limit']);
    $router->post('/multas-online/configuracao/toggle', [SerproConsultaController::class, 'toggleConfiguracao'], ['csrf', 'rate_limit']);

    // API Logs Consultas
    $router->get('/api/multas-online/logs', [SerproConsultaController::class, 'logs'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/multas-online/logs/{id}', [SerproConsultaController::class, 'logDetalhe'], ['api_csrf', 'rate_limit', 'throttle']);

    // Paginas iframe - Indicacoes de Condutor
    $router->get('/pages/multas-online/indicacoes', [SerproIndicacaoController::class, 'view']);

    // API Indicacoes de Condutor
    $router->get('/api/multas-online/indicacoes', [SerproIndicacaoController::class, 'index'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/multas-online/indicacoes/resumo', [SerproIndicacaoController::class, 'resumo'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/multas-online/indicacoes/{id}', [SerproIndicacaoController::class, 'show'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/multas-online/indicacoes/{id}/status', [SerproIndicacaoController::class, 'consultarStatus'], ['api_csrf', 'rate_limit', 'throttle']);

    // CRUD Indicacoes de Condutor
    $router->post('/multas-online/indicacoes/real-infrator', [SerproIndicacaoController::class, 'indicarRealInfrator'], ['csrf', 'rate_limit']);
    $router->post('/multas-online/indicacoes/principal-condutor', [SerproIndicacaoController::class, 'indicarPrincipalCondutor'], ['csrf', 'rate_limit']);
    $router->post('/multas-online/indicacoes/{id}/cancelar', [SerproIndicacaoController::class, 'cancelar'], ['csrf', 'rate_limit']);

    // Pagina iframe - Central de Multas
    $router->get('/pages/central-multas', [CentralMultasController::class, 'view']);

    // API Central de Multas
    $router->get('/api/central-multas/dashboard', [CentralMultasController::class, 'dashboard'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/central-multas/multas', [CentralMultasController::class, 'listarMultas'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/central-multas/ranking-veiculos', [CentralMultasController::class, 'rankingVeiculos'], ['api_csrf', 'rate_limit', 'throttle']);

    // Paginas iframe - Veiculos
    $router->get('/pages/veiculos', [VeiculosController::class, 'view']);
    $router->get('/pages/veiculos/adicionar', [VeiculosController::class, 'viewAdicionar']);
    $router->get('/pages/veiculos/{id}/editar', [VeiculosController::class, 'viewAdicionar']);

    // API Veiculos (com protecao anti-scraping e CSRF)
    $router->get('/api/veiculos', [VeiculosController::class, 'index'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/veiculos/buscar', [VeiculosController::class, 'buscar'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/veiculos/por-grupo', [VeiculosController::class, 'porGrupo'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/veiculos/{id}', [VeiculosController::class, 'show'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/veiculos/{id}/manutencoes', [VeiculosController::class, 'manutencoes'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/veiculos/{id}/faturas', [VeiculosController::class, 'faturas'], ['api_csrf', 'rate_limit', 'throttle']);

    // CRUD Veiculos
    $router->post('/veiculos/salvar', [VeiculosController::class, 'store'], ['csrf', 'rate_limit']);
    $router->post('/veiculos/{id}/atualizar', [VeiculosController::class, 'update'], ['csrf', 'rate_limit']);
    $router->post('/veiculos/{id}/excluir', [VeiculosController::class, 'destroy'], ['csrf', 'rate_limit']);
    $router->post('/veiculos/{id}/desativar', [VeiculosController::class, 'desativar'], ['csrf', 'rate_limit']);

    // API Encargos do Veiculo
    $router->get('/api/veiculos/{id}/encargos', [VeiculosController::class, 'listarEncargos'], ['api_csrf', 'rate_limit', 'throttle']);

    // CRUD Encargos do Veiculo
    $router->post('/veiculos/{id}/encargos/salvar', [VeiculosController::class, 'criarEncargo'], ['csrf', 'rate_limit']);
    $router->post('/veiculos/{id}/encargos/{encargoId}/atualizar', [VeiculosController::class, 'atualizarEncargo'], ['csrf', 'rate_limit']);
    $router->post('/veiculos/{id}/encargos/{encargoId}/excluir', [VeiculosController::class, 'excluirEncargo'], ['csrf', 'rate_limit']);

    // Paginas iframe - Veiculos Acessorios
    $router->get('/pages/veiculos-acessorios', function ($request) {
        $html = \App\Views\Template::render('pages.veiculos-acessorios.index');
        \App\Core\Response::html($html);
    });
    $router->get('/pages/veiculos-acessorios/adicionar', function ($request) {
        $html = \App\Views\Template::render('pages.veiculos-acessorios.adicionar');
        \App\Core\Response::html($html);
    });

    // API Veiculos Acessorios
    $router->get('/api/veiculos-acessorios', [VeiculosAcessoriosController::class, 'index'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/veiculos-acessorios/todos', [VeiculosAcessoriosController::class, 'todos'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/veiculos-acessorios/{id}', [VeiculosAcessoriosController::class, 'show'], ['api_csrf', 'rate_limit', 'throttle']);

    // CRUD Veiculos Acessorios
    $router->post('/veiculos-acessorios/salvar', [VeiculosAcessoriosController::class, 'store'], ['csrf', 'rate_limit']);
    $router->post('/veiculos-acessorios/{id}/atualizar', [VeiculosAcessoriosController::class, 'update'], ['csrf', 'rate_limit']);
    $router->post('/veiculos-acessorios/{id}/excluir', [VeiculosAcessoriosController::class, 'destroy'], ['csrf', 'rate_limit']);

    // Paginas iframe - Manutencoes Planos
    $router->get('/pages/manutencoes-planos', [ManutencoesPlanosController::class, 'view']);
    $router->get('/pages/manutencoes-planos/adicionar', function ($request) {
        $html = \App\Views\Template::render('pages.manutencoes-planos.adicionar');
        \App\Core\Response::html($html);
    });

    // API Manutencoes Planos
    $router->get('/api/manutencoes-planos', [ManutencoesPlanosController::class, 'index'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/manutencoes-planos/{id}', [ManutencoesPlanosController::class, 'show'], ['api_csrf', 'rate_limit', 'throttle']);

    // CRUD Manutencoes Planos
    $router->post('/manutencoes-planos/salvar', [ManutencoesPlanosController::class, 'store'], ['csrf', 'rate_limit']);
    $router->post('/manutencoes-planos/{id}/atualizar', [ManutencoesPlanosController::class, 'update'], ['csrf', 'rate_limit']);
    $router->post('/manutencoes-planos/{id}/excluir', [ManutencoesPlanosController::class, 'destroy'], ['csrf', 'rate_limit']);

    // Paginas iframe - Manutencoes (Ordens de Servico)
    $router->get('/pages/manutencoes', [ManutencoesController::class, 'view']);
    $router->get('/pages/manutencoes/adicionar', [ManutencoesController::class, 'viewAdicionar']);

    // API Manutencoes
    $router->get('/api/manutencoes', [ManutencoesController::class, 'index'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/manutencoes/{id}', [ManutencoesController::class, 'show'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/manutencoes/{id}/itens', [ManutencoesController::class, 'itens'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/manutencoes/{id}/itens/pendentes', [ManutencoesController::class, 'itensPendentes'], ['api_csrf', 'rate_limit', 'throttle']);

    // CRUD Manutencoes
    $router->post('/manutencoes/salvar', [ManutencoesController::class, 'store'], ['csrf', 'rate_limit']);
    $router->post('/manutencoes/{id}/atualizar', [ManutencoesController::class, 'update'], ['csrf', 'rate_limit']);
    $router->post('/manutencoes/{id}/excluir', [ManutencoesController::class, 'destroy'], ['csrf', 'rate_limit']);
    $router->post('/manutencoes/{id}/itens/salvar', [ManutencoesController::class, 'salvarItem'], ['csrf', 'rate_limit']);
    $router->post('/manutencoes/{id}/itens/{itemId}/excluir', [ManutencoesController::class, 'excluirItem'], ['csrf', 'rate_limit']);

    // Acoes de Manutencoes
    $router->post('/manutencoes/{id}/abrir', [ManutencoesController::class, 'abrir'], ['csrf', 'rate_limit']);
    $router->post('/manutencoes/{id}/fechar', [ManutencoesController::class, 'fechar'], ['csrf', 'rate_limit']);
    $router->post('/manutencoes/{id}/financeiro/criar', [ManutencoesController::class, 'criarFinanceiro'], ['csrf', 'rate_limit']);
    $router->post('/manutencoes/{id}/financeiro/parcial', [ManutencoesController::class, 'criarFinanceiroParcial'], ['csrf', 'rate_limit']);
    $router->get('/manutencoes/{id}/imprimir', [ManutencoesController::class, 'imprimir']);

    // Paginas iframe - Financeiro (Lancamentos)
    $router->get('/pages/financeiro', [FinanceiroController::class, 'view']);
    $router->get('/pages/financeiro/adicionar', [FinanceiroController::class, 'viewAdicionar']);

    // API Financeiro
    $router->get('/api/financeiro', [FinanceiroController::class, 'index'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/financeiro/planos-de-contas', [FinanceiroController::class, 'planosDeContas'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/financeiro/{id}', [FinanceiroController::class, 'show'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/financeiro/{id}/parcelas', [FinanceiroController::class, 'parcelas'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/financeiro/{id}/link-pagamento', [FinanceiroController::class, 'getLinkPagamento'], ['api_csrf', 'rate_limit', 'throttle']);

    // CRUD Financeiro
    $router->post('/financeiro/salvar', [FinanceiroController::class, 'store'], ['csrf', 'rate_limit']);
    $router->post('/financeiro/{id}/atualizar', [FinanceiroController::class, 'update'], ['csrf', 'rate_limit']);
    $router->post('/financeiro/{id}/baixa-parcial', [FinanceiroController::class, 'baixaParcial'], ['csrf', 'rate_limit']);
    $router->post('/financeiro/{id}/excluir', [FinanceiroController::class, 'destroy'], ['csrf', 'rate_limit']);

    // Parcelamento Financeiro
    $router->post('/financeiro/parcelas/atualizar-lote', [FinanceiroController::class, 'atualizarParcelasLote'], ['csrf', 'rate_limit']);
    $router->post('/financeiro/parcelas/excluir-lote', [FinanceiroController::class, 'excluirParcelasLote'], ['csrf', 'rate_limit']);

    // Impressao e envio de fatura
    $router->get('/pages/financeiro/offcanvas-impressao', [FinanceiroController::class, 'offcanvasImpressao']);
    $router->get('/financeiro/{id}/imprimir/fatura', [FinanceiroController::class, 'imprimirFatura'], ['rate_limit']);
    $router->post('/financeiro/{id}/enviar', [FinanceiroController::class, 'enviar'], ['csrf', 'rate_limit']);

    // Paginas iframe - Promissorias
    $router->get('/pages/promissorias', [PromissoriasController::class, 'view']);
    $router->get('/pages/promissorias/adicionar', [PromissoriasController::class, 'formView']);
    $router->get('/pages/promissorias/editar/{codigo}', [PromissoriasController::class, 'editView']);

    // API Promissorias (com protecao anti-scraping e CSRF)
    $router->get('/api/promissorias', [PromissoriasController::class, 'index'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/promissorias/codigo/{codigo}', [PromissoriasController::class, 'showByCodigo'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/promissorias/{codigo}/assinatura', [PromissoriasController::class, 'buscarAssinatura'], ['api_csrf', 'rate_limit', 'throttle']);

    // CRUD Promissorias
    $router->post('/promissorias/salvar', [PromissoriasController::class, 'store'], ['csrf', 'rate_limit']);
    $router->post('/promissorias/{codigo}/atualizar', [PromissoriasController::class, 'update'], ['csrf', 'rate_limit']);
    $router->post('/promissorias/{codigo}/excluir', [PromissoriasController::class, 'destroy'], ['csrf', 'rate_limit']);

    // Paginas iframe - NFS-e
    $router->get('/pages/nfse', [NFSeController::class, 'view']);
    $router->get('/pages/nfse/emitir', [NFSeController::class, 'viewEmitir']);
    $router->get('/pages/nfse/{id}/visualizar', [NFSeController::class, 'viewVisualizar']);
    $router->get('/pages/nfse/{id}/cancelar', [NFSeController::class, 'viewCancelar']);
    // Redirect: config NFS-e agora fica na aba da empresa
    $router->get('/pages/nfse/configuracoes', function () {
        \App\Core\Response::redirect('/pages/matrizes-filiais');
    });

    // API NFS-e (com protecao anti-scraping e CSRF)
    $router->get('/api/nfse', [NFSeController::class, 'index'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/nfse/estatisticas', [NFSeController::class, 'estatisticas'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/nfse/configuracoes', [NFSeController::class, 'getConfiguracoes'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/nfse/{id}', [NFSeController::class, 'show'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/nfse/{id}/eventos', [NFSeController::class, 'eventos'], ['api_csrf', 'rate_limit', 'throttle']);

    // Acoes NFS-e
    $router->post('/nfse/emitir', [NFSeController::class, 'emitir'], ['csrf', 'rate_limit']);
    $router->post('/nfse/{id}/cancelar', [NFSeController::class, 'cancelar'], ['csrf', 'rate_limit']);
    $router->post('/nfse/{id}/consultar', [NFSeController::class, 'consultar'], ['csrf', 'rate_limit']);
    $router->post('/nfse/{id}/reenviar', [NFSeController::class, 'reenviar'], ['csrf', 'rate_limit']);
    $router->post('/nfse/{id}/email', [NFSeController::class, 'enviarEmail'], ['csrf', 'rate_limit']);
    $router->get('/nfse/{id}/pdf', [NFSeController::class, 'downloadPdf'], ['api_csrf', 'rate_limit', 'throttle']);

    // Configuracoes NFS-e
    $router->post('/nfse/configuracoes/salvar', [NFSeController::class, 'salvarConfiguracoes'], ['csrf', 'rate_limit']);
    $router->post('/nfse/configuracoes/certificado', [NFSeController::class, 'uploadCertificado'], ['csrf', 'rate_limit']);
    $router->post('/nfse/configuracoes/certificado/remover', [NFSeController::class, 'removerCertificado'], ['csrf', 'rate_limit']);
    $router->post('/nfse/configuracoes/testar-conexao', [NFSeController::class, 'testarConexao'], ['csrf', 'rate_limit']);

    // Acoes Promissorias
    $router->post('/promissorias/{codigo}/limpar-assinatura', [PromissoriasController::class, 'limparAssinatura'], ['csrf', 'rate_limit']);
    $router->post('/promissorias/{codigo}/enviar-link-assinatura', [PromissoriasController::class, 'enviarLinkAssinatura'], ['csrf', 'rate_limit']);
    $router->post('/promissorias/{codigo}/marcar-pago', [PromissoriasController::class, 'marcarPago'], ['csrf', 'rate_limit']);

    // Operacoes de Parcelas
    $router->post('/promissorias/{codigo}/parcelas/adicionar', [PromissoriasController::class, 'addParcela'], ['csrf', 'rate_limit']);
    $router->post('/promissorias/{codigo}/parcelas/{parcela}/atualizar', [PromissoriasController::class, 'updateParcela'], ['csrf', 'rate_limit']);
    $router->post('/promissorias/{codigo}/parcelas/{parcela}/excluir', [PromissoriasController::class, 'destroyParcela'], ['csrf', 'rate_limit']);
    $router->post('/promissorias/{codigo}/parcelas/{parcela}/pagar', [PromissoriasController::class, 'marcarParcelaPaga'], ['csrf', 'rate_limit']);

    // Impressao Promissorias
    $router->get('/promissorias/{codigo}/imprimir', [PromissoriasController::class, 'imprimir']);
    $router->get('/promissorias/{codigo}/parcelas/{parcela}/imprimir', [PromissoriasController::class, 'imprimirParcela']);

    // Paginas iframe - Templates de Promissorias
    $router->get('/pages/promissorias/templates', [PromissoriaTemplateController::class, 'view']);

    // API Templates de Promissorias
    $router->get('/api/promissorias/templates/types', [PromissoriaTemplateController::class, 'listTypes'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/promissorias/templates', [PromissoriaTemplateController::class, 'listAll'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/promissorias/templates/{slug}', [PromissoriaTemplateController::class, 'show'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/promissorias/templates/{slug}/variables', [PromissoriaTemplateController::class, 'variables'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/promissorias/templates/preview', [PromissoriaTemplateController::class, 'preview'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/promissorias/templates/{slug}', [PromissoriaTemplateController::class, 'save'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/promissorias/templates/{slug}/restore', [PromissoriaTemplateController::class, 'restore'], ['api_csrf', 'rate_limit', 'throttle']);

    // Paginas iframe - Fornecedores
    $router->get('/pages/fornecedores', [FornecedoresController::class, 'view']);
    $router->get('/pages/fornecedores/adicionar', [FornecedoresController::class, 'viewAdicionar']);
    $router->get('/pages/fornecedores/{id}/editar', [FornecedoresController::class, 'viewAdicionar']);

    // API Fornecedores (com protecao anti-scraping e CSRF)
    $router->get('/api/fornecedores', [FornecedoresController::class, 'index'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/fornecedores/select', [FornecedoresController::class, 'fornecedoresSelect'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/fornecedores/investidores/select', [FornecedoresController::class, 'investidoresSelect'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/fornecedores/{id}', [FornecedoresController::class, 'show'], ['api_csrf', 'rate_limit', 'throttle']);

    // Paginas iframe - Oficinas
    $router->get('/pages/oficinas', [OficinasController::class, 'view']);
    $router->get('/pages/oficinas/adicionar', [OficinasController::class, 'viewAdicionar']);

    // API Oficinas (com protecao anti-scraping e CSRF)
    $router->get('/api/oficinas', [OficinasController::class, 'index'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/oficinas/buscar', [OficinasController::class, 'buscar'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/oficinas/{id}', [OficinasController::class, 'show'], ['api_csrf', 'rate_limit', 'throttle']);

    // CRUD Oficinas
    $router->post('/oficinas/salvar', [OficinasController::class, 'store'], ['csrf', 'rate_limit']);
    $router->post('/oficinas/{id}/atualizar', [OficinasController::class, 'update'], ['csrf', 'rate_limit']);
    $router->post('/oficinas/{id}/excluir', [OficinasController::class, 'destroy'], ['csrf', 'rate_limit']);

    // CRUD Fornecedores
    $router->post('/fornecedores/salvar', [FornecedoresController::class, 'store'], ['csrf', 'rate_limit']);
    $router->post('/fornecedores/{id}/atualizar', [FornecedoresController::class, 'update'], ['csrf', 'rate_limit']);
    $router->post('/fornecedores/{id}/excluir', [FornecedoresController::class, 'destroy'], ['csrf', 'rate_limit']);

    // Paginas iframe - Estoque
    $router->get('/pages/estoque', [EstoqueController::class, 'view']);
    $router->get('/pages/estoque/adicionar', [EstoqueController::class, 'viewAdicionar']);
    $router->get('/pages/estoque/{id}/editar', [EstoqueController::class, 'viewAdicionar']);

    // API Estoque (com protecao anti-scraping e CSRF)
    $router->get('/api/estoque', [EstoqueController::class, 'index'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/estoque/buscar', [EstoqueController::class, 'buscar'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/estoque/{id}', [EstoqueController::class, 'show'], ['api_csrf', 'rate_limit', 'throttle']);

    // CRUD Estoque
    $router->post('/estoque/salvar', [EstoqueController::class, 'store'], ['csrf', 'rate_limit']);
    $router->post('/estoque/{id}/atualizar', [EstoqueController::class, 'update'], ['csrf', 'rate_limit']);
    $router->post('/estoque/{id}/excluir', [EstoqueController::class, 'destroy'], ['csrf', 'rate_limit']);
    $router->post('/estoque/{id}/reativar', [EstoqueController::class, 'reativar'], ['csrf', 'rate_limit']);

    // Paginas iframe - Comissoes Investidores
    $router->get('/pages/comissoes-investidores', [ComissoesInvestidoresController::class, 'view']);

    // API Comissoes Investidores
    $router->get('/api/comissoes-investidores', [ComissoesInvestidoresController::class, 'index'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/comissoes-investidores/totais', [ComissoesInvestidoresController::class, 'totais'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/comissoes-investidores/resumo', [ComissoesInvestidoresController::class, 'resumo'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/comissoes-investidores/{id}', [ComissoesInvestidoresController::class, 'show'], ['api_csrf', 'rate_limit', 'throttle']);

    // CRUD Comissoes Investidores
    $router->post('/comissoes-investidores/{id}/pagar', [ComissoesInvestidoresController::class, 'pagar'], ['csrf', 'rate_limit']);
    $router->post('/comissoes-investidores/{id}/cancelar', [ComissoesInvestidoresController::class, 'cancelar'], ['csrf', 'rate_limit']);

    // Pagina iframe - Programa de Indicacao
    $router->get('/pages/programa-indicacao', [ProgramaIndicacaoController::class, 'view']);

    // API Programa de Indicacao
    $router->get('/api/programa-indicacao/codigo', [ProgramaIndicacaoController::class, 'getOrCreateCodigo'], ['api_csrf', 'rate_limit', 'throttle']);

    // Paginas iframe - Feature Requests (Pedidos de Recursos)
    $router->get('/pages/feature-requests', [FeatureRequestsController::class, 'view']);
    $router->get('/pages/feature-requests/adicionar', [FeatureRequestsController::class, 'viewAdicionar']);
    $router->get('/pages/feature-requests/detalhes', [FeatureRequestsController::class, 'viewDetalhes']);

    // API Feature Requests (com protecao anti-scraping e CSRF)
    $router->get('/api/feature-requests', [FeatureRequestsController::class, 'index'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/feature-requests/modulos', [FeatureRequestsController::class, 'modulos'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/feature-requests/similares', [FeatureRequestsController::class, 'similares'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/feature-requests/estatisticas', [FeatureRequestsController::class, 'estatisticas'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/feature-requests/meus-votos', [FeatureRequestsController::class, 'meusVotos'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/feature-requests/meus-seguidos', [FeatureRequestsController::class, 'meusSeguidos'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/feature-requests/{id}', [FeatureRequestsController::class, 'show'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/feature-requests/{id}/seguidores', [FeatureRequestsController::class, 'seguidores'], ['api_csrf', 'rate_limit', 'throttle']);

    // CRUD Feature Requests
    $router->post('/feature-requests/salvar', [FeatureRequestsController::class, 'store'], ['csrf', 'rate_limit']);
    $router->post('/feature-requests/{id}/atualizar', [FeatureRequestsController::class, 'update'], ['csrf', 'rate_limit']);
    $router->post('/feature-requests/{id}/excluir', [FeatureRequestsController::class, 'destroy'], ['csrf', 'rate_limit']);
    $router->post('/feature-requests/{id}/status', [FeatureRequestsController::class, 'atualizarStatus'], ['csrf', 'rate_limit']);
    $router->put('/feature-requests/{id}/status', [FeatureRequestsController::class, 'atualizarStatus'], ['csrf', 'rate_limit']);
    $router->post('/feature-requests/{id}/votar', [FeatureRequestsController::class, 'votar'], ['csrf', 'rate_limit']);
    $router->delete('/feature-requests/{id}/voto', [FeatureRequestsController::class, 'removerVoto'], ['csrf', 'rate_limit']);
    $router->post('/feature-requests/{id}/seguir', [FeatureRequestsController::class, 'seguir'], ['csrf', 'rate_limit']);
    $router->post('/feature-requests/{id}/deixar-de-seguir', [FeatureRequestsController::class, 'deixarDeSeguir'], ['csrf', 'rate_limit']);
    $router->delete('/feature-requests/{id}/seguir', [FeatureRequestsController::class, 'deixarDeSeguir'], ['csrf', 'rate_limit']);

    // Pagina iframe - Conceder Acesso (Suporte)
    $router->get('/pages/conceder-acesso', [ConcederAcessoController::class, 'view']);

    // API Conceder Acesso
    $router->get('/api/conceder-acesso/status', [ConcederAcessoController::class, 'status'], ['api_csrf', 'rate_limit', 'throttle']);

    // Acoes Conceder Acesso
    $router->post('/conceder-acesso/criar', [ConcederAcessoController::class, 'criar'], ['csrf', 'rate_limit']);
    $router->post('/conceder-acesso/excluir', [ConcederAcessoController::class, 'excluir'], ['csrf', 'rate_limit']);

    // Pagina iframe - Changelog
    $router->get('/pages/changelog', [ChangelogController::class, 'view']);

    // API Changelog
    $router->get('/api/changelog', [ChangelogController::class, 'index'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/changelog/{id}', [ChangelogController::class, 'show'], ['api_csrf', 'rate_limit', 'throttle']);

    // CRUD Changelog (apenas admin)
    $router->post('/api/changelog', [ChangelogController::class, 'store'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/changelog/{id}/atualizar', [ChangelogController::class, 'update'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/changelog/{id}/excluir', [ChangelogController::class, 'destroy'], ['api_csrf', 'rate_limit', 'throttle']);

    // Pagina iframe - Gravacoes de Tela
    $router->get('/pages/gravacoes', function ($request) {
        $html = \App\Views\Template::render('pages.gravacoes.index');
        \App\Core\Response::html($html);
    });

    // API Gravacoes (com protecao anti-scraping e CSRF)
    $router->get('/api/gravacoes', [GravacoesController::class, 'index'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/gravacoes/{id}', [GravacoesController::class, 'show'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/gravacoes', [GravacoesController::class, 'store'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->delete('/api/gravacoes/{id}', [GravacoesController::class, 'destroy'], ['api_csrf', 'rate_limit', 'throttle']);

    // Paginas iframe - Contratos
    $router->get('/pages/contratos', [ContratosController::class, 'view']);
    $router->get('/pages/contratos/adicionar', [ContratosController::class, 'formView']);
    $router->get('/pages/contratos/editar/{id}', [ContratosController::class, 'editView']);
    // Redirect de compatibilidade (URL antiga)
    $router->get('/pages/contratos/adicionar/{id}', [ContratosController::class, 'redirectToEdit']);
    $router->get('/pages/contratos/substituir/{id}', [ContratosController::class, 'substituirView']);
    $router->get('/pages/contratos/devolver/{id}', [ContratosController::class, 'devolverView']);
    $router->get('/pages/contratos/offcanvas-veiculo', [ContratosController::class, 'offcanvasVeiculo']);
    $router->get('/pages/contratos/offcanvas-impressao', [ContratosController::class, 'offcanvasImpressao']);
    $router->get('/pages/contratos/offcanvas-odometro', [ContratosController::class, 'offcanvasOdometro'], ['permission:contratos.editar']);

    // API Contratos (com protecao anti-scraping e CSRF)
    $router->get('/api/contratos', [ContratosController::class, 'index'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/contratos/buscar-select', [ContratosController::class, 'buscarSelect'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/contratos/{id}/regularizacao-renovacao', [ContratosController::class, 'previewRegularizacaoRenovacao'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/contratos/{id}/regularizar-renovacao', [ContratosController::class, 'regularizarRenovacao'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/contratos/{id}/odometros', [ContratosController::class, 'registrarOdometro'], ['permission:contratos.editar', 'api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/contratos/{id}', [ContratosController::class, 'show'], ['api_csrf', 'rate_limit', 'throttle']);

    // CRUD Contratos
    $router->post('/contratos/salvar', [ContratosController::class, 'store'], ['csrf', 'rate_limit']);
    $router->post('/contratos/{id}/atualizar', [ContratosController::class, 'update'], ['csrf', 'rate_limit']);
    $router->post('/contratos/{id}/excluir', [ContratosController::class, 'destroy'], ['csrf', 'rate_limit']);

    // Acoes de Contratos
    $router->post('/contratos/{id}/devolver', [ContratosController::class, 'devolver'], ['csrf', 'rate_limit']);
    $router->post('/contratos/{id}/substituir', [ContratosController::class, 'substituir'], ['csrf', 'rate_limit']);
    $router->post('/contratos/{id}/veiculos', [ContratosController::class, 'adicionarVeiculo'], ['csrf', 'rate_limit']);
    $router->post('/contratos/{id}/limpar-assinatura', [ContratosController::class, 'limparAssinatura'], ['csrf', 'rate_limit']);
    $router->post('/contratos/{id}/enviar-link-assinatura', [ContratosController::class, 'enviarLinkAssinatura'], ['csrf', 'rate_limit']);
    $router->get('/api/contratos/{id}/assinatura', [ContratosController::class, 'buscarAssinatura'], ['api_csrf', 'rate_limit', 'throttle']);

    // Impressao e Envio de Contratos
    $router->get('/contratos/{id}/imprimir', [ContratosController::class, 'imprimir']);
    $router->post('/contratos/{id}/enviar', [ContratosController::class, 'enviarContrato'], ['csrf', 'rate_limit']);

    // Financeiro do Contrato (parcelas)
    $router->get('/api/contratos/{id}/parcelas', [ContratosController::class, 'parcelas'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/contratos/opcoes-parcelamento/{id}', [ContratosController::class, 'opcoesParcelamento'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/contratos/preview-parcelas', [ContratosController::class, 'previewParcelasStateless'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/contratos/{id}/gerar-parcelas', [ContratosController::class, 'gerarParcelas'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/contratos/{id}/parcela-avulsa', [ContratosController::class, 'parcelaAvulsa'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/contratos/{id}/parcelas/{idParcela}/atualizar', [ContratosController::class, 'atualizarParcela'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/contratos/{id}/parcelas/{idParcela}/excluir', [ContratosController::class, 'removerParcela'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/contratos/{id}/parcelas/{idParcela}/marcar-pago', [ContratosController::class, 'marcarParcelaPaga'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/contratos/{id}/parcelas/{idParcela}/estornar', [ContratosController::class, 'estornarParcelaPagamento'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/contratos/{id}/recalcular-parcelas', [ContratosController::class, 'recalcularParcelas'], ['api_csrf', 'rate_limit', 'throttle']);

    // Bloqueio (Authorization Hold) - Contratos
    $router->post('/api/contratos/{id}/bloqueio/criar', [ContratosController::class, 'criarBloqueio'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/contratos/{id}/bloqueio/capturar', [ContratosController::class, 'capturarBloqueio'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/contratos/{id}/bloqueio/liberar', [ContratosController::class, 'liberarBloqueio'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/contratos/{id}/bloqueio/status', [ContratosController::class, 'statusBloqueio'], ['api_csrf', 'rate_limit', 'throttle']);

    // Pagina iframe - Agenda (timeline Gantt)
    $router->get('/pages/agenda', [AgendaController::class, 'view']);
    $router->get('/pages/agenda/adicionar', [AgendaController::class, 'formView']);
    $router->get('/pages/agenda/editar/{id}', [AgendaController::class, 'editView']);
    $router->get('/api/agenda', [AgendaController::class, 'index'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/agenda/salvar', [AgendaController::class, 'store'], ['csrf', 'rate_limit']);
    $router->post('/agenda/{id}/atualizar', [AgendaController::class, 'update'], ['csrf', 'rate_limit']);
    $router->post('/agenda/{id}/excluir', [AgendaController::class, 'destroy'], ['csrf', 'rate_limit']);

    // Paginas iframe - Locacoes/Reservas
    $router->get('/pages/locacoes', [LocacoesController::class, 'view']);
    $router->get('/pages/locacoes/adicionar', [LocacoesController::class, 'formView']);
    $router->get('/pages/locacoes/editar/{id}', [LocacoesController::class, 'editView']);
    $router->get('/pages/locacoes/substituir/{id}', [LocacoesController::class, 'substituirView']);

    // API Locacoes (com protecao anti-scraping e CSRF)
    $router->get('/api/locacoes', [LocacoesController::class, 'index'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/locacoes/{id}', [LocacoesController::class, 'show'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/locacoes/{id}/assinatura', [LocacoesController::class, 'buscarAssinatura'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/locacoes/{id}/veiculos', [LocacoesController::class, 'listarVeiculos'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/locacoes/{id}/taxas', [LocacoesController::class, 'listarTaxas'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/locacoes/{id}/parcelas', [LocacoesController::class, 'listarParcelas'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/locacoes/{id}/resumo-financeiro', [LocacoesController::class, 'resumoFinanceiro'], ['api_csrf', 'rate_limit', 'throttle']);

    // CRUD Locacoes
    $router->post('/locacoes/salvar', [LocacoesController::class, 'store'], ['csrf', 'rate_limit']);
    $router->post('/api/locacoes/{id}/confirmar-reserva', [LocacoesController::class, 'confirmarReserva'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/locacoes/{id}/atualizar', [LocacoesController::class, 'update'], ['csrf', 'rate_limit']);
    $router->post('/locacoes/{id}/excluir', [LocacoesController::class, 'destroy'], ['csrf', 'rate_limit']);

    // Acoes de Locacoes
    $router->post('/locacoes/{id}/substituir', [LocacoesController::class, 'substituir'], ['csrf', 'rate_limit']);
    $router->post('/locacoes/{id}/limpar-assinatura', [LocacoesController::class, 'limparAssinatura'], ['csrf', 'rate_limit']);
    $router->post('/locacoes/{id}/enviar-link-assinatura', [LocacoesController::class, 'enviarLinkAssinatura'], ['csrf', 'rate_limit']);

    // Parcelas de Locacoes
    $router->post('/api/locacoes/{id}/gerar-parcelas', [LocacoesController::class, 'gerarParcelas'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/locacoes/{id}/parcelas', [LocacoesController::class, 'adicionarParcela'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/locacoes/{id}/parcelas/{idParcela}/atualizar', [LocacoesController::class, 'atualizarParcela'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/locacoes/{id}/parcelas/{idParcela}/excluir', [LocacoesController::class, 'removerParcela'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/locacoes/{id}/parcelas/{idParcela}/marcar-pago', [LocacoesController::class, 'marcarParcelaPaga'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/locacoes/{id}/parcelas/{idParcela}/estornar', [LocacoesController::class, 'estornarParcelaPagamento'], ['api_csrf', 'rate_limit', 'throttle']);

    // Bloqueio (Authorization Hold)
    $router->post('/api/locacoes/{id}/bloqueio/criar', [LocacoesController::class, 'criarBloqueio'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/locacoes/{id}/bloqueio/capturar', [LocacoesController::class, 'capturarBloqueio'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/locacoes/{id}/bloqueio/liberar', [LocacoesController::class, 'liberarBloqueio'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/locacoes/{id}/bloqueio/status', [LocacoesController::class, 'statusBloqueio'], ['api_csrf', 'rate_limit', 'throttle']);

    // Caucao (Deposito de Garantia)
    $router->post('/api/locacoes/{id}/caucao/devolver', [LocacoesController::class, 'devolverCaucao'], ['api_csrf', 'rate_limit', 'throttle']);

    // Impressao Locacoes
    $router->get('/pages/locacoes/offcanvas-impressao', [LocacoesController::class, 'offcanvasImpressao']);
    $router->get('/locacoes/{id}/imprimir', [LocacoesController::class, 'imprimir']);
    $router->post('/locacoes/{id}/enviar', [LocacoesController::class, 'enviarLocacao'], ['csrf', 'rate_limit']);

    // Pagina iframe - Mensageria (WhatsApp, SMS e SMTP)
    $router->get('/pages/mensageria', [WhatsappController::class, 'view']);
    // Mantém rota antiga para compatibilidade
    $router->get('/pages/whatsapp', [WhatsappController::class, 'view']);

    // Formularios WhatsApp (offcanvas iframe)
    $router->get('/pages/mensageria/whatsapp/adicionar', [WhatsappController::class, 'viewWhatsappAdicionar']);
    $router->get('/pages/mensageria/whatsapp/editar', [WhatsappController::class, 'viewWhatsappEditar']);
    $router->get('/pages/mensageria/whatsapp/testar', [WhatsappController::class, 'viewWhatsappTestar']);
    $router->get('/pages/mensageria/whatsapp/qrcode', [WhatsappController::class, 'viewWhatsappQrcode']);

    // Formularios SMS (offcanvas iframe)
    $router->get('/pages/mensageria/sms/adicionar', [WhatsappController::class, 'viewSmsAdicionar']);
    $router->get('/pages/mensageria/sms/editar', [WhatsappController::class, 'viewSmsEditar']);
    $router->get('/pages/mensageria/sms/testar', [WhatsappController::class, 'viewSmsTestar']);

    // Formularios SMTP (offcanvas iframe)
    $router->get('/pages/mensageria/smtp/adicionar', [WhatsappController::class, 'viewSmtpAdicionar']);
    $router->get('/pages/mensageria/smtp/editar', [WhatsappController::class, 'viewSmtpEditar']);
    $router->get('/pages/mensageria/smtp/testar', [WhatsappController::class, 'viewSmtpTestar']);

    // API WhatsApp (com protecao anti-scraping e CSRF)
    $router->get('/api/whatsapp', [WhatsappController::class, 'index'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/whatsapp/filiais-ocupadas', [WhatsappController::class, 'filiaisOcupadas'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/whatsapp/check-number', [WhatsappController::class, 'checkNumber'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/whatsapp/{id}', [WhatsappController::class, 'show'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/whatsapp/{id}/status', [WhatsappController::class, 'status'], ['api_csrf', 'rate_limit', 'throttle']);

    // CRUD WhatsApp
    $router->post('/whatsapp/salvar', [WhatsappController::class, 'store'], ['csrf', 'rate_limit']);
    $router->post('/whatsapp/{id}/atualizar', [WhatsappController::class, 'update'], ['csrf', 'rate_limit']);
    $router->post('/whatsapp/{id}/excluir', [WhatsappController::class, 'destroy'], ['csrf', 'rate_limit']);
    $router->post('/whatsapp/{id}/connect', [WhatsappController::class, 'connect'], ['csrf', 'rate_limit']);
    $router->post('/whatsapp/{id}/disconnect', [WhatsappController::class, 'disconnect'], ['csrf', 'rate_limit']);
    $router->post('/whatsapp/{id}/restart', [WhatsappController::class, 'restart'], ['csrf', 'rate_limit']);
    $router->post('/whatsapp/{id}/recreate', [WhatsappController::class, 'recreate'], ['csrf', 'rate_limit']);

    // Testes WhatsApp
    $router->post('/whatsapp/test/text', [WhatsappController::class, 'testText'], ['csrf', 'rate_limit']);
    $router->post('/whatsapp/test/image', [WhatsappController::class, 'testImage'], ['csrf', 'rate_limit']);
    $router->post('/whatsapp/test/document', [WhatsappController::class, 'testDocument'], ['csrf', 'rate_limit']);

    // API SMS (com protecao anti-scraping e CSRF)
    $router->get('/api/sms', [SmsController::class, 'index'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/sms/providers', [SmsController::class, 'providers'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/sms/filiais-ocupadas', [SmsController::class, 'filiaisOcupadas'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/sms/{id}', [SmsController::class, 'show'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/sms/{id}/balance', [SmsController::class, 'balance'], ['api_csrf', 'rate_limit', 'throttle']);

    // CRUD SMS
    $router->post('/sms/salvar', [SmsController::class, 'store'], ['csrf', 'rate_limit']);
    $router->post('/sms/{id}/atualizar', [SmsController::class, 'update'], ['csrf', 'rate_limit']);
    $router->post('/sms/{id}/excluir', [SmsController::class, 'destroy'], ['csrf', 'rate_limit']);
    $router->post('/sms/{id}/validate', [SmsController::class, 'validate'], ['csrf', 'rate_limit']);

    // Teste SMS
    $router->post('/sms/test', [SmsController::class, 'testSend'], ['csrf', 'rate_limit']);

    // API SMTP (com protecao anti-scraping e CSRF)
    $router->get('/api/smtp', [SmtpController::class, 'index'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/smtp/providers', [SmtpController::class, 'providers'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/smtp/filiais-ocupadas', [SmtpController::class, 'filiaisOcupadas'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/smtp/{id}', [SmtpController::class, 'show'], ['api_csrf', 'rate_limit', 'throttle']);

    // CRUD SMTP
    $router->post('/smtp/salvar', [SmtpController::class, 'store'], ['csrf', 'rate_limit']);
    $router->post('/smtp/{id}/atualizar', [SmtpController::class, 'update'], ['csrf', 'rate_limit']);
    $router->post('/smtp/{id}/excluir', [SmtpController::class, 'destroy'], ['csrf', 'rate_limit']);
    $router->post('/smtp/{id}/validate', [SmtpController::class, 'validate'], ['csrf', 'rate_limit']);

    // Teste SMTP
    $router->post('/smtp/test', [SmtpController::class, 'testSend'], ['csrf', 'rate_limit']);

    // Paginas iframe - Documentos
    $router->get('/pages/documentos', [DocumentosController::class, 'view']);
    $router->get('/pages/documentos/adicionar', [DocumentosController::class, 'viewAdicionar']);
    $router->get('/pages/documentos/{id}/editar', [DocumentosController::class, 'viewAdicionar']);

    // API Documentos (com protecao anti-scraping e CSRF)
    $router->get('/api/documentos', [DocumentosController::class, 'index'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/documentos/variables', [DocumentosController::class, 'variables'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/documentos/buscar', [DocumentosController::class, 'buscar'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/documentos/{id}', [DocumentosController::class, 'show'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/documentos/preview', [DocumentosController::class, 'preview'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/documentos/extrair-texto', [DocumentosController::class, 'extrairTexto'], ['api_csrf', 'rate_limit', 'throttle']);

    // CRUD Documentos
    $router->post('/documentos/salvar', [DocumentosController::class, 'store'], ['csrf', 'rate_limit']);
    $router->post('/documentos/{id}/atualizar', [DocumentosController::class, 'update'], ['csrf', 'rate_limit']);
    $router->post('/documentos/{id}/excluir', [DocumentosController::class, 'destroy'], ['csrf', 'rate_limit']);

    // ============================================================
    // RELATORIOS - KPIs
    // ============================================================

    // Taxa de Ocupação
    $router->get('/pages/relatorios/kpis/taxa-ocupacao', [KpisController::class, 'viewTaxaOcupacao']);
    $router->get('/api/relatorios/kpis/taxa-ocupacao', [KpisController::class, 'taxaOcupacao'], ['api_csrf', 'rate_limit', 'throttle']);

    // RevPAR
    $router->get('/pages/relatorios/kpis/revpar', [KpisController::class, 'viewRevpar']);
    $router->get('/api/relatorios/kpis/revpar', [KpisController::class, 'revpar'], ['api_csrf', 'rate_limit', 'throttle']);

    // ADR (Diária Média)
    $router->get('/pages/relatorios/kpis/adr', [KpisController::class, 'viewAdr']);
    $router->get('/api/relatorios/kpis/adr', [KpisController::class, 'adr'], ['api_csrf', 'rate_limit', 'throttle']);

    // Ticket Médio
    $router->get('/pages/relatorios/kpis/ticket-medio', [KpisController::class, 'viewTicketMedio']);
    $router->get('/api/relatorios/kpis/ticket-medio', [KpisController::class, 'ticketMedio'], ['api_csrf', 'rate_limit', 'throttle']);

    // Tempo Médio de Locação
    $router->get('/pages/relatorios/kpis/tempo-medio', [KpisController::class, 'viewTempoMedio']);
    $router->get('/api/relatorios/kpis/tempo-medio', [KpisController::class, 'tempoMedio'], ['api_csrf', 'rate_limit', 'throttle']);

    // % Receitas Adicionais
    $router->get('/pages/relatorios/kpis/receitas-adicionais', [KpisController::class, 'viewReceitasAdicionais']);
    $router->get('/api/relatorios/kpis/receitas-adicionais', [KpisController::class, 'receitasAdicionais'], ['api_csrf', 'rate_limit', 'throttle']);

    // Receita por Veículo
    $router->get('/pages/relatorios/kpis/receita-veiculo', [KpisController::class, 'viewReceitaVeiculo']);
    $router->get('/api/relatorios/kpis/receita-veiculo', [KpisController::class, 'receitaVeiculo'], ['api_csrf', 'rate_limit', 'throttle']);

    // Margem Bruta por Dia
    $router->get('/pages/relatorios/kpis/margem-bruta', [KpisController::class, 'viewMargemBruta']);
    $router->get('/api/relatorios/kpis/margem-bruta', [KpisController::class, 'margemBruta'], ['api_csrf', 'rate_limit', 'throttle']);

    // ROI por Veículo
    $router->get('/pages/relatorios/kpis/roi-veiculo', [KpisController::class, 'viewRoiVeiculo']);
    $router->get('/api/relatorios/kpis/roi-veiculo', [KpisController::class, 'roiVeiculo'], ['api_csrf', 'rate_limit', 'throttle']);

    // PDF Exports - KPIs
    $router->get('/relatorios/kpis/taxa-ocupacao/pdf', [KpisController::class, 'taxaOcupacaoPdf']);
    $router->get('/relatorios/kpis/revpar/pdf', [KpisController::class, 'revparPdf']);
    $router->get('/relatorios/kpis/adr/pdf', [KpisController::class, 'adrPdf']);
    $router->get('/relatorios/kpis/ticket-medio/pdf', [KpisController::class, 'ticketMedioPdf']);
    $router->get('/relatorios/kpis/tempo-medio/pdf', [KpisController::class, 'tempoMedioPdf']);
    $router->get('/relatorios/kpis/receitas-adicionais/pdf', [KpisController::class, 'receitasAdicionaisPdf']);
    $router->get('/relatorios/kpis/receita-veiculo/pdf', [KpisController::class, 'receitaVeiculoPdf']);
    $router->get('/relatorios/kpis/margem-bruta/pdf', [KpisController::class, 'margemBrutaPdf']);
    $router->get('/relatorios/kpis/roi-veiculo/pdf', [KpisController::class, 'roiVeiculoPdf']);

    // =====================================================
    // RELATÓRIOS FINANCEIROS
    // =====================================================

    // Movimentações Financeiras
    $router->get('/pages/relatorios/financeiro/movimentacoes', [FinanceiroReportController::class, 'viewMovimentacoes']);
    $router->get('/api/relatorios/financeiro/movimentacoes', [FinanceiroReportController::class, 'movimentacoes'], ['api_csrf', 'rate_limit', 'throttle']);

    // Caucoes
    $router->get('/pages/relatorios/financeiro/caucoes', [FinanceiroReportController::class, 'viewCaucoes']);
    $router->get('/api/relatorios/financeiro/caucoes', [FinanceiroReportController::class, 'caucoes'], ['api_csrf', 'rate_limit', 'throttle']);

    // Faturamento
    $router->get('/pages/relatorios/financeiro/faturamento', [FinanceiroReportController::class, 'viewFaturamento']);
    $router->get('/api/relatorios/financeiro/faturamento', [FinanceiroReportController::class, 'faturamento'], ['api_csrf', 'rate_limit', 'throttle']);

    // DRE
    $router->get('/pages/relatorios/financeiro/dre', [FinanceiroReportController::class, 'viewDre']);
    $router->get('/api/relatorios/financeiro/dre', [FinanceiroReportController::class, 'dre'], ['api_csrf', 'rate_limit', 'throttle']);

    // Livro de Caixa
    $router->get('/pages/relatorios/financeiro/livro-caixa', [FinanceiroReportController::class, 'viewLivroCaixa']);
    $router->get('/api/relatorios/financeiro/livro-caixa', [FinanceiroReportController::class, 'livroCaixa'], ['api_csrf', 'rate_limit', 'throttle']);

    // Contas Bancárias
    $router->get('/pages/relatorios/financeiro/contas-bancarias', [FinanceiroReportController::class, 'viewContasBancarias']);
    $router->get('/api/relatorios/financeiro/contas-bancarias', [FinanceiroReportController::class, 'contasBancarias'], ['api_csrf', 'rate_limit', 'throttle']);

    // Plano de Contas
    $router->get('/pages/relatorios/financeiro/plano-contas', [FinanceiroReportController::class, 'viewPlanoContas']);
    $router->get('/api/relatorios/financeiro/plano-contas', [FinanceiroReportController::class, 'planoContas'], ['api_csrf', 'rate_limit', 'throttle']);

    // Projeção de Receitas
    $router->get('/pages/relatorios/financeiro/projecao-receitas', [FinanceiroReportController::class, 'viewProjecaoReceitas']);
    $router->get('/api/relatorios/financeiro/projecao-receitas', [FinanceiroReportController::class, 'projecaoReceitas'], ['api_csrf', 'rate_limit', 'throttle']);

    // Rentabilidade
    $router->get('/pages/relatorios/financeiro/rentabilidade', [FinanceiroReportController::class, 'viewRentabilidade']);
    $router->get('/api/relatorios/financeiro/rentabilidade', [FinanceiroReportController::class, 'rentabilidade'], ['api_csrf', 'rate_limit', 'throttle']);

    // Inadimplência
    $router->get('/pages/relatorios/financeiro/inadimplencia', [FinanceiroReportController::class, 'viewInadimplencia']);
    $router->get('/api/relatorios/financeiro/inadimplencia', [FinanceiroReportController::class, 'inadimplencia'], ['api_csrf', 'rate_limit', 'throttle']);

    // Taxas e Serviços
    $router->get('/pages/relatorios/financeiro/taxas-servicos', [FinanceiroReportController::class, 'viewTaxasServicos']);
    $router->get('/api/relatorios/financeiro/taxas-servicos', [FinanceiroReportController::class, 'taxasServicos'], ['api_csrf', 'rate_limit', 'throttle']);

    // PDF Exports - Financeiro
    $router->get('/relatorios/financeiro/movimentacoes/pdf', [FinanceiroReportController::class, 'movimentacoesPdf']);
    $router->get('/relatorios/financeiro/faturamento/pdf', [FinanceiroReportController::class, 'faturamentoPdf']);
    $router->get('/relatorios/financeiro/dre/pdf', [FinanceiroReportController::class, 'drePdf']);
    $router->get('/relatorios/financeiro/livro-caixa/pdf', [FinanceiroReportController::class, 'livroCaixaPdf']);
    $router->get('/relatorios/financeiro/contas-bancarias/pdf', [FinanceiroReportController::class, 'contasBancariasPdf']);
    $router->get('/relatorios/financeiro/plano-contas/pdf', [FinanceiroReportController::class, 'planoContasPdf']);
    $router->get('/relatorios/financeiro/projecao-receitas/pdf', [FinanceiroReportController::class, 'projecaoReceitasPdf']);
    $router->get('/relatorios/financeiro/rentabilidade/pdf', [FinanceiroReportController::class, 'rentabilidadePdf']);
    $router->get('/relatorios/financeiro/inadimplencia/pdf', [FinanceiroReportController::class, 'inadimplenciaPdf']);
    $router->get('/relatorios/financeiro/taxas-servicos/pdf', [FinanceiroReportController::class, 'taxasServicosPdf']);

    // =========================================================================
    // RELATORIOS - FATURAS
    // =========================================================================

    // Vencidas / A Vencer
    $router->get('/pages/relatorios/faturas/vencidas-a-vencer', [\App\Controllers\Relatorios\FaturasController::class, 'viewVencidasAVencer']);
    $router->get('/api/relatorios/faturas/vencidas-a-vencer', [\App\Controllers\Relatorios\FaturasController::class, 'vencidasAVencer'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/faturas/vencidas-a-vencer/pdf', [\App\Controllers\Relatorios\FaturasController::class, 'vencidasAVencerPdf']);

    // Por Veículo
    $router->get('/pages/relatorios/faturas/por-veiculo', [\App\Controllers\Relatorios\FaturasController::class, 'viewFaturasPorVeiculo']);
    $router->get('/api/relatorios/faturas/por-veiculo', [\App\Controllers\Relatorios\FaturasController::class, 'faturasPorVeiculo'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/faturas/por-veiculo/pdf', [\App\Controllers\Relatorios\FaturasController::class, 'faturasPorVeiculoPdf']);

    // Pagar / Receber
    $router->get('/pages/relatorios/faturas/pagar-receber', [\App\Controllers\Relatorios\FaturasController::class, 'viewPagarReceber']);
    $router->get('/api/relatorios/faturas/pagar-receber', [\App\Controllers\Relatorios\FaturasController::class, 'pagarReceber'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/faturas/pagar-receber/pdf', [\App\Controllers\Relatorios\FaturasController::class, 'pagarReceberPdf']);

    // =========================================================================
    // RELATORIOS - CONTRATOS / LOCACOES
    // =========================================================================

    // 5.1 Visao Geral
    $router->get('/pages/relatorios/contratos/visao-geral', [\App\Controllers\Relatorios\ContratosController::class, 'viewVisaoGeral']);
    $router->get('/api/relatorios/contratos/visao-geral', [\App\Controllers\Relatorios\ContratosController::class, 'visaoGeral'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/contratos/visao-geral/pdf', [\App\Controllers\Relatorios\ContratosController::class, 'visaoGeralPdf']);

    // 5.2 Por Periodo
    $router->get('/pages/relatorios/contratos/por-periodo', [\App\Controllers\Relatorios\ContratosController::class, 'viewPorPeriodo']);
    $router->get('/api/relatorios/contratos/por-periodo', [\App\Controllers\Relatorios\ContratosController::class, 'porPeriodo'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/contratos/por-periodo/pdf', [\App\Controllers\Relatorios\ContratosController::class, 'porPeriodoPdf']);

    // 5.3 Por Forma de Pagamento
    $router->get('/pages/relatorios/contratos/por-forma-pagamento', [\App\Controllers\Relatorios\ContratosController::class, 'viewPorFormaPagamento']);
    $router->get('/api/relatorios/contratos/por-forma-pagamento', [\App\Controllers\Relatorios\ContratosController::class, 'porFormaPagamento'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/contratos/por-forma-pagamento/pdf', [\App\Controllers\Relatorios\ContratosController::class, 'porFormaPagamentoPdf']);

    // 5.4 Extensoes
    $router->get('/pages/relatorios/contratos/extensoes', [\App\Controllers\Relatorios\ContratosController::class, 'viewExtensoes']);
    $router->get('/api/relatorios/contratos/extensoes', [\App\Controllers\Relatorios\ContratosController::class, 'extensoes'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/contratos/extensoes/pdf', [\App\Controllers\Relatorios\ContratosController::class, 'extensoesPdf']);

    // 5.5 Trocas de Veiculo
    $router->get('/pages/relatorios/contratos/trocas-veiculo', [\App\Controllers\Relatorios\ContratosController::class, 'viewTrocasVeiculo']);
    $router->get('/api/relatorios/contratos/trocas-veiculo', [\App\Controllers\Relatorios\ContratosController::class, 'trocasVeiculo'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/contratos/trocas-veiculo/pdf', [\App\Controllers\Relatorios\ContratosController::class, 'trocasVeiculoPdf']);

    // ========================================================================
    // RELATORIOS - 4. CLIENTES
    // ========================================================================

    // 4.1 Por Cliente
    $router->get('/pages/relatorios/clientes/por-cliente', [\App\Controllers\Relatorios\ClientesController::class, 'viewPorCliente']);
    $router->get('/api/relatorios/clientes/por-cliente', [\App\Controllers\Relatorios\ClientesController::class, 'porCliente'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/clientes/por-cliente/pdf', [\App\Controllers\Relatorios\ClientesController::class, 'porClientePdf']);

    // 4.2 Aniversariantes
    $router->get('/pages/relatorios/clientes/aniversariantes', [\App\Controllers\Relatorios\ClientesController::class, 'viewAniversariantes']);
    $router->get('/api/relatorios/clientes/aniversariantes', [\App\Controllers\Relatorios\ClientesController::class, 'aniversariantes'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/clientes/aniversariantes/pdf', [\App\Controllers\Relatorios\ClientesController::class, 'aniversariantesPdf']);

    // 4.3 CNH Vencidas
    $router->get('/pages/relatorios/clientes/cnh-vencidas', [\App\Controllers\Relatorios\ClientesController::class, 'viewCnhVencidas']);
    $router->get('/api/relatorios/clientes/cnh-vencidas', [\App\Controllers\Relatorios\ClientesController::class, 'cnhVencidas'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/clientes/cnh-vencidas/pdf', [\App\Controllers\Relatorios\ClientesController::class, 'cnhVencidasPdf']);

    // 4.4 Top Clientes
    $router->get('/pages/relatorios/clientes/top-clientes', [\App\Controllers\Relatorios\ClientesController::class, 'viewTopClientes']);
    $router->get('/api/relatorios/clientes/top-clientes', [\App\Controllers\Relatorios\ClientesController::class, 'topClientes'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/clientes/top-clientes/pdf', [\App\Controllers\Relatorios\ClientesController::class, 'topClientesPdf']);

    // 4.5 Frequencia
    $router->get('/pages/relatorios/clientes/frequencia', [\App\Controllers\Relatorios\ClientesController::class, 'viewFrequencia']);
    $router->get('/api/relatorios/clientes/frequencia', [\App\Controllers\Relatorios\ClientesController::class, 'frequencia'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/clientes/frequencia/pdf', [\App\Controllers\Relatorios\ClientesController::class, 'frequenciaPdf']);

    // 4.6 Tempo de Relacionamento
    $router->get('/pages/relatorios/clientes/tempo-relacionamento', [\App\Controllers\Relatorios\ClientesController::class, 'viewTempoRelacionamento']);
    $router->get('/api/relatorios/clientes/tempo-relacionamento', [\App\Controllers\Relatorios\ClientesController::class, 'tempoRelacionamento'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/clientes/tempo-relacionamento/pdf', [\App\Controllers\Relatorios\ClientesController::class, 'tempoRelacionamentoPdf']);

    // 4.7 Ocorrencias
    $router->get('/pages/relatorios/clientes/ocorrencias', [\App\Controllers\Relatorios\ClientesController::class, 'viewOcorrencias']);
    $router->get('/api/relatorios/clientes/ocorrencias', [\App\Controllers\Relatorios\ClientesController::class, 'ocorrencias'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/clientes/ocorrencias/pdf', [\App\Controllers\Relatorios\ClientesController::class, 'ocorrenciasPdf']);

    // 4.8 Inativos
    $router->get('/pages/relatorios/clientes/inativos', [\App\Controllers\Relatorios\ClientesController::class, 'viewInativos']);
    $router->get('/api/relatorios/clientes/inativos', [\App\Controllers\Relatorios\ClientesController::class, 'inativos'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/clientes/inativos/pdf', [\App\Controllers\Relatorios\ClientesController::class, 'inativosPdf']);

    // ========================================================================
    // RELATORIOS - 6. OPERACIONAL
    // ========================================================================

    // 6.1 Checklists Realizados
    $router->get('/pages/relatorios/operacional/checklists-realizados', [\App\Controllers\Relatorios\OperacionalController::class, 'viewChecklistsRealizados']);
    $router->get('/api/relatorios/operacional/checklists-realizados', [\App\Controllers\Relatorios\OperacionalController::class, 'checklistsRealizados'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/operacional/checklists-realizados/pdf', [\App\Controllers\Relatorios\OperacionalController::class, 'checklistsRealizadosPdf']);

    // 6.2 Avarias e Sinistros
    $router->get('/pages/relatorios/operacional/avarias-sinistros', [\App\Controllers\Relatorios\OperacionalController::class, 'viewAvariasSinistros']);
    $router->get('/api/relatorios/operacional/avarias-sinistros', [\App\Controllers\Relatorios\OperacionalController::class, 'avariasSinistros'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/operacional/avarias-sinistros/pdf', [\App\Controllers\Relatorios\OperacionalController::class, 'avariasSinistrosPdf']);

    // 6.3 Multas de Transito
    $router->get('/pages/relatorios/operacional/multas-transito', [\App\Controllers\Relatorios\OperacionalController::class, 'viewMultasTransito']);
    $router->get('/api/relatorios/operacional/multas-transito', [\App\Controllers\Relatorios\OperacionalController::class, 'multasTransito'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/operacional/multas-transito/pdf', [\App\Controllers\Relatorios\OperacionalController::class, 'multasTransitoPdf']);

    // 6.4 Devolucoes Antecipadas
    $router->get('/pages/relatorios/operacional/devolucoes-antecipadas', [\App\Controllers\Relatorios\OperacionalController::class, 'viewDevolucoesAntecipadas']);
    $router->get('/api/relatorios/operacional/devolucoes-antecipadas', [\App\Controllers\Relatorios\OperacionalController::class, 'devolucoesAntecipadas'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/operacional/devolucoes-antecipadas/pdf', [\App\Controllers\Relatorios\OperacionalController::class, 'devolucoesAntecipadasPdf']);

    // 6.5 Devolucoes Atrasadas
    $router->get('/pages/relatorios/operacional/devolucoes-atrasadas', [\App\Controllers\Relatorios\OperacionalController::class, 'viewDevolucoesAtrasadas']);
    $router->get('/api/relatorios/operacional/devolucoes-atrasadas', [\App\Controllers\Relatorios\OperacionalController::class, 'devolucoesAtrasadas'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/operacional/devolucoes-atrasadas/pdf', [\App\Controllers\Relatorios\OperacionalController::class, 'devolucoesAtrasadasPdf']);

    // 6.6 Reservas Canceladas
    $router->get('/pages/relatorios/operacional/reservas-canceladas', [\App\Controllers\Relatorios\OperacionalController::class, 'viewReservasCanceladas']);
    $router->get('/api/relatorios/operacional/reservas-canceladas', [\App\Controllers\Relatorios\OperacionalController::class, 'reservasCanceladas'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/operacional/reservas-canceladas/pdf', [\App\Controllers\Relatorios\OperacionalController::class, 'reservasCanceladasPdf']);

    // 6.7 Turnaround
    $router->get('/pages/relatorios/operacional/turnaround', [\App\Controllers\Relatorios\OperacionalController::class, 'viewTurnaround']);
    $router->get('/api/relatorios/operacional/turnaround', [\App\Controllers\Relatorios\OperacionalController::class, 'turnaround'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/operacional/turnaround/pdf', [\App\Controllers\Relatorios\OperacionalController::class, 'turnaroundPdf']);

    // 6.8 Combustivel
    $router->get('/pages/relatorios/operacional/combustivel', [\App\Controllers\Relatorios\OperacionalController::class, 'viewCombustivel']);
    $router->get('/api/relatorios/operacional/combustivel', [\App\Controllers\Relatorios\OperacionalController::class, 'combustivel'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/operacional/combustivel/pdf', [\App\Controllers\Relatorios\OperacionalController::class, 'combustivelPdf']);

    // ============================================================
    // RELATORIOS - VEICULAR
    // ============================================================

    // 3.1 Manutencoes
    $router->get('/pages/relatorios/veicular/manutencoes', [\App\Controllers\Relatorios\VeicularController::class, 'viewManutencoes']);
    $router->get('/api/relatorios/veicular/manutencoes', [\App\Controllers\Relatorios\VeicularController::class, 'manutencoes'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/veicular/manutencoes/pdf', [\App\Controllers\Relatorios\VeicularController::class, 'manutencoesPdf']);

    // 3.2 Lucro por Veiculo
    $router->get('/pages/relatorios/veicular/lucro-veiculo', [\App\Controllers\Relatorios\VeicularController::class, 'viewLucroVeiculo']);
    $router->get('/api/relatorios/veicular/lucro-veiculo', [\App\Controllers\Relatorios\VeicularController::class, 'lucroVeiculo'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/veicular/lucro-veiculo/pdf', [\App\Controllers\Relatorios\VeicularController::class, 'lucroVeiculoPdf']);

    // 3.3 Despesas Veicular
    $router->get('/pages/relatorios/veicular/despesas', [\App\Controllers\Relatorios\VeicularController::class, 'viewDespesas']);
    $router->get('/api/relatorios/veicular/despesas', [\App\Controllers\Relatorios\VeicularController::class, 'despesas'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/veicular/despesas/pdf', [\App\Controllers\Relatorios\VeicularController::class, 'despesasPdf']);

    // 3.4 Veiculo/Cliente
    $router->get('/pages/relatorios/veicular/veiculo-cliente', [\App\Controllers\Relatorios\VeicularController::class, 'viewVeiculoCliente']);
    $router->get('/api/relatorios/veicular/veiculo-cliente', [\App\Controllers\Relatorios\VeicularController::class, 'veiculoCliente'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/veicular/veiculo-cliente/pdf', [\App\Controllers\Relatorios\VeicularController::class, 'veiculoClientePdf']);

    // 3.5 Licenciamento
    $router->get('/pages/relatorios/veicular/licenciamento', [\App\Controllers\Relatorios\VeicularController::class, 'viewLicenciamento']);
    $router->get('/api/relatorios/veicular/licenciamento', [\App\Controllers\Relatorios\VeicularController::class, 'licenciamento'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/veicular/licenciamento/pdf', [\App\Controllers\Relatorios\VeicularController::class, 'licenciamentoPdf']);

    // 3.6 Disponibilidade
    $router->get('/pages/relatorios/veicular/disponibilidade', [\App\Controllers\Relatorios\VeicularController::class, 'viewDisponibilidade']);
    $router->get('/api/relatorios/veicular/disponibilidade', [\App\Controllers\Relatorios\VeicularController::class, 'disponibilidade'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/veicular/disponibilidade/pdf', [\App\Controllers\Relatorios\VeicularController::class, 'disponibilidadePdf']);

    // 3.7 Taxa de Ocupacao por Grupo
    $router->get('/pages/relatorios/veicular/ocupacao-grupo', [\App\Controllers\Relatorios\VeicularController::class, 'viewOcupacaoGrupo']);
    $router->get('/api/relatorios/veicular/ocupacao-grupo', [\App\Controllers\Relatorios\VeicularController::class, 'ocupacaoGrupo'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/veicular/ocupacao-grupo/pdf', [\App\Controllers\Relatorios\VeicularController::class, 'ocupacaoGrupoPdf']);

    // 3.8 Depreciacao
    $router->get('/pages/relatorios/veicular/depreciacao', [\App\Controllers\Relatorios\VeicularController::class, 'viewDepreciacao']);
    $router->get('/api/relatorios/veicular/depreciacao', [\App\Controllers\Relatorios\VeicularController::class, 'depreciacao'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/veicular/depreciacao/pdf', [\App\Controllers\Relatorios\VeicularController::class, 'depreciacaoPdf']);

    // 3.9 Tempo Medio Parado
    $router->get('/pages/relatorios/veicular/tempo-parado', [\App\Controllers\Relatorios\VeicularController::class, 'viewTempoParado']);
    $router->get('/api/relatorios/veicular/tempo-parado', [\App\Controllers\Relatorios\VeicularController::class, 'tempoParado'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/veicular/tempo-parado/pdf', [\App\Controllers\Relatorios\VeicularController::class, 'tempoParadoPdf']);

    // 3.10 Quilometragem Media
    $router->get('/pages/relatorios/veicular/quilometragem-media', [\App\Controllers\Relatorios\VeicularController::class, 'viewQuilometragemMedia']);
    $router->get('/api/relatorios/veicular/quilometragem-media', [\App\Controllers\Relatorios\VeicularController::class, 'quilometragemMedia'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/veicular/quilometragem-media/pdf', [\App\Controllers\Relatorios\VeicularController::class, 'quilometragemMediaPdf']);

    // 3.11 TCO
    $router->get('/pages/relatorios/veicular/tco', [\App\Controllers\Relatorios\VeicularController::class, 'viewTco']);
    $router->get('/api/relatorios/veicular/tco', [\App\Controllers\Relatorios\VeicularController::class, 'tco'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/veicular/tco/pdf', [\App\Controllers\Relatorios\VeicularController::class, 'tcoPdf']);

    // ============================================================
    // RELATORIOS - COMPARATIVOS
    // ============================================================

    // 11.1 Mensal/Anual
    $router->get('/pages/relatorios/comparativos/mensal-anual', [\App\Controllers\Relatorios\ComparativosController::class, 'viewMensalAnual']);
    $router->get('/api/relatorios/comparativos/mensal-anual', [\App\Controllers\Relatorios\ComparativosController::class, 'mensalAnual'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/comparativos/mensal-anual/pdf', [\App\Controllers\Relatorios\ComparativosController::class, 'mensalAnualPdf']);

    // 11.2 Entre Filiais
    $router->get('/pages/relatorios/comparativos/filiais', [\App\Controllers\Relatorios\ComparativosController::class, 'viewFiliais']);
    $router->get('/api/relatorios/comparativos/filiais', [\App\Controllers\Relatorios\ComparativosController::class, 'filiais'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/comparativos/filiais/pdf', [\App\Controllers\Relatorios\ComparativosController::class, 'filiaisPdf']);

    // 11.3 Ranking de Veiculos
    $router->get('/pages/relatorios/comparativos/ranking-veiculos', [\App\Controllers\Relatorios\ComparativosController::class, 'viewRankingVeiculos']);
    $router->get('/api/relatorios/comparativos/ranking-veiculos', [\App\Controllers\Relatorios\ComparativosController::class, 'rankingVeiculos'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/comparativos/ranking-veiculos/pdf', [\App\Controllers\Relatorios\ComparativosController::class, 'rankingVeiculosPdf']);

    // 11.4 Analise de Tendencias
    $router->get('/pages/relatorios/comparativos/tendencias', [\App\Controllers\Relatorios\ComparativosController::class, 'viewTendencias']);
    $router->get('/api/relatorios/comparativos/tendencias', [\App\Controllers\Relatorios\ComparativosController::class, 'tendencias'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/comparativos/tendencias/pdf', [\App\Controllers\Relatorios\ComparativosController::class, 'tendenciasPdf']);

    // ============================================================
    // RELATORIOS - FORNECEDORES
    // ============================================================

    // 9.1 Compras e Pagamentos
    $router->get('/pages/relatorios/fornecedores/compras', [\App\Controllers\Relatorios\FornecedoresController::class, 'viewCompras']);
    $router->get('/api/relatorios/fornecedores/compras', [\App\Controllers\Relatorios\FornecedoresController::class, 'compras'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/fornecedores/compras/pdf', [\App\Controllers\Relatorios\FornecedoresController::class, 'comprasPdf']);

    // 9.2 Fornecedor Investidor
    $router->get('/pages/relatorios/fornecedores/investidor', [\App\Controllers\Relatorios\FornecedoresController::class, 'viewInvestidor']);
    $router->get('/api/relatorios/fornecedores/investidor', [\App\Controllers\Relatorios\FornecedoresController::class, 'investidor'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/fornecedores/investidor/pdf', [\App\Controllers\Relatorios\FornecedoresController::class, 'investidorPdf']);

    // ============================================================
    // RELATORIOS - COMERCIAL
    // ============================================================

    // 8.1 Taxa de Conversao
    $router->get('/pages/relatorios/comercial/taxa-conversao', [\App\Controllers\Relatorios\ComercialController::class, 'viewTaxaConversao']);
    $router->get('/api/relatorios/comercial/taxa-conversao', [\App\Controllers\Relatorios\ComercialController::class, 'taxaConversao'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/comercial/taxa-conversao/pdf', [\App\Controllers\Relatorios\ComercialController::class, 'taxaConversaoPdf']);

    // 8.2 Origem das Locacoes
    $router->get('/pages/relatorios/comercial/origem-locacoes', [\App\Controllers\Relatorios\ComercialController::class, 'viewOrigemLocacoes']);
    $router->get('/api/relatorios/comercial/origem-locacoes', [\App\Controllers\Relatorios\ComercialController::class, 'origemLocacoes'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/comercial/origem-locacoes/pdf', [\App\Controllers\Relatorios\ComercialController::class, 'origemLocacoesPdf']);

    // 8.3 Promocoes Utilizadas
    $router->get('/pages/relatorios/comercial/promocoes', [\App\Controllers\Relatorios\ComercialController::class, 'viewPromocoes']);
    $router->get('/api/relatorios/comercial/promocoes', [\App\Controllers\Relatorios\ComercialController::class, 'promocoes'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/comercial/promocoes/pdf', [\App\Controllers\Relatorios\ComercialController::class, 'promocoesPdf']);

    // 8.4 Descontos Concedidos
    $router->get('/pages/relatorios/comercial/descontos', [\App\Controllers\Relatorios\ComercialController::class, 'viewDescontos']);
    $router->get('/api/relatorios/comercial/descontos', [\App\Controllers\Relatorios\ComercialController::class, 'descontos'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/comercial/descontos/pdf', [\App\Controllers\Relatorios\ComercialController::class, 'descontosPdf']);

    // 8.5 Analise de Temporada
    $router->get('/pages/relatorios/comercial/temporada', [\App\Controllers\Relatorios\ComercialController::class, 'viewTemporada']);
    $router->get('/api/relatorios/comercial/temporada', [\App\Controllers\Relatorios\ComercialController::class, 'temporada'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/comercial/temporada/pdf', [\App\Controllers\Relatorios\ComercialController::class, 'temporadaPdf']);

    // ============================================================
    // RELATORIOS - FUNCIONARIOS
    // ============================================================

    // 10.1 Vendas
    $router->get('/pages/relatorios/funcionarios/vendas', [\App\Controllers\Relatorios\FuncionariosController::class, 'viewVendas']);
    $router->get('/api/relatorios/funcionarios/vendas', [\App\Controllers\Relatorios\FuncionariosController::class, 'vendas'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/funcionarios/vendas/pdf', [\App\Controllers\Relatorios\FuncionariosController::class, 'vendasPdf']);

    // 10.2 Comissoes
    $router->get('/pages/relatorios/funcionarios/comissoes', [\App\Controllers\Relatorios\FuncionariosController::class, 'viewComissoes']);
    $router->get('/api/relatorios/funcionarios/comissoes', [\App\Controllers\Relatorios\FuncionariosController::class, 'comissoes'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/funcionarios/comissoes/pdf', [\App\Controllers\Relatorios\FuncionariosController::class, 'comissoesPdf']);

    // 10.3 Produtividade
    $router->get('/pages/relatorios/funcionarios/produtividade', [\App\Controllers\Relatorios\FuncionariosController::class, 'viewProdutividade']);
    $router->get('/api/relatorios/funcionarios/produtividade', [\App\Controllers\Relatorios\FuncionariosController::class, 'produtividade'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/funcionarios/produtividade/pdf', [\App\Controllers\Relatorios\FuncionariosController::class, 'produtividadePdf']);

    // 10.4 Metas vs Realizado
    $router->get('/pages/relatorios/funcionarios/metas', [\App\Controllers\Relatorios\FuncionariosController::class, 'viewMetas']);
    $router->get('/api/relatorios/funcionarios/metas', [\App\Controllers\Relatorios\FuncionariosController::class, 'metas'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/relatorios/funcionarios/metas/pdf', [\App\Controllers\Relatorios\FuncionariosController::class, 'metasPdf']);

    // =========================================================================
    // WEBSITE
    // =========================================================================

    // Views
    $router->get('/pages/website/configuracoes', [WebsiteController::class, 'configuracoes']);
    $router->get('/pages/website/banners', [WebsiteController::class, 'banners']);
    $router->get('/pages/website/integracoes', [WebsiteController::class, 'integracoes']);
    $router->get('/pages/website/aparencia', [WebsiteController::class, 'aparencia']);
    $router->get('/pages/website/conteudos', [WebsiteController::class, 'conteudos']);
    $router->get('/pages/website/seo', [WebsiteController::class, 'seo']);
    $router->get('/pages/website/publicar', [WebsiteController::class, 'deploy']);
    $router->get('/pages/website/ativar', [WebsiteController::class, 'ativar']);

    // API - Configuração
    $router->get('/api/website/config', [WebsiteController::class, 'getConfig'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/website/config', [WebsiteController::class, 'updateConfig'], ['api_csrf', 'rate_limit', 'throttle']);

    // API - Aparência
    $router->get('/api/website/aparencia', [WebsiteController::class, 'getAparencia'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/website/aparencia', [WebsiteController::class, 'updateAparencia'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/website/aparencia/reset', [WebsiteController::class, 'resetAparencia'], ['api_csrf', 'rate_limit', 'throttle']);

    // API - Conteúdos
    $router->get('/api/website/conteudos/{pagina}', [WebsiteController::class, 'getConteudos'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/website/conteudos/{pagina}', [WebsiteController::class, 'updateConteudos'], ['api_csrf', 'rate_limit', 'throttle']);

    // API - SEO
    $router->get('/api/website/seo/{pagina}', [WebsiteController::class, 'getSeo'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/website/seo/{pagina}', [WebsiteController::class, 'updateSeo'], ['api_csrf', 'rate_limit', 'throttle']);

    // API - Integrações
    $router->get('/api/website/integracoes', [WebsiteController::class, 'getIntegracoes'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/website/integracoes', [WebsiteController::class, 'saveIntegracao'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/website/integracoes/{id}/excluir', [WebsiteController::class, 'deleteIntegracao'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->delete('/api/website/integracoes/{id}', [WebsiteController::class, 'deleteIntegracao'], ['api_csrf', 'rate_limit', 'throttle']);

    // API - Banners
    $router->get('/api/website/banners', [WebsiteController::class, 'getBanners'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/website/banners', [WebsiteController::class, 'saveBanner'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->put('/api/website/banners/{id}', [WebsiteController::class, 'updateBanner'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/website/banners/{id}/excluir', [WebsiteController::class, 'deleteBanner'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->delete('/api/website/banners/{id}', [WebsiteController::class, 'deleteBanner'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/website/banners/reordenar', [WebsiteController::class, 'reordenarBanners'], ['api_csrf', 'rate_limit', 'throttle']);

    // API - Links
    $router->get('/api/website/links', [WebsiteController::class, 'getLinks'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/website/links', [WebsiteController::class, 'saveLinks'], ['api_csrf', 'rate_limit', 'throttle']);

    // API - Idiomas
    $router->get('/api/website/idiomas', [WebsiteController::class, 'getIdiomas'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/website/idiomas', [WebsiteController::class, 'saveIdiomas'], ['api_csrf', 'rate_limit', 'throttle']);

    // API - Ativação
    $router->post('/api/website/ativar', [WebsiteController::class, 'submitAtivacao'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/website/verificar-dominio', [WebsiteController::class, 'verificarDominio'], ['api_csrf', 'rate_limit', 'throttle']);

    // API - Deploy
    $router->post('/api/website/deploy', [WebsiteController::class, 'executarDeploy'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/website/deploy/status', [WebsiteController::class, 'deployStatus'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->get('/api/website/deploy/log', [WebsiteController::class, 'deployLog'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/website/preview', [WebsiteController::class, 'preview'], ['api_csrf', 'rate_limit', 'throttle']);

    // API - Presets
    $router->post('/api/website/presets', [WebsiteController::class, 'savePreset'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->post('/api/website/presets/{id}/excluir', [WebsiteController::class, 'deletePreset'], ['api_csrf', 'rate_limit', 'throttle']);
    $router->delete('/api/website/presets/{id}', [WebsiteController::class, 'deletePreset'], ['api_csrf', 'rate_limit', 'throttle']);
});
