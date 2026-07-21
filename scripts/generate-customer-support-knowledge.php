<?php

declare(strict_types=1);

const KB_PUBLIC_HEADER = ['categoria', 'pergunta', 'resposta'];
const KB_ARTICLE_TYPES = ['navegacao', 'procedimento', 'conceito', 'diagnostico'];
const KB_ARTICLE_STATUSES = ['rascunho', 'fonte_revisada', 'aprovado'];
const KB_LOTS = [
    'acesso-sistema' => '01-acesso-sistema.csv',
    'agenda-notificacoes' => '02-agenda-notificacoes.csv',
    'empresa-cadastros' => '03-empresa-cadastros.csv',
    'contratos-locacoes' => '04-contratos-locacoes.csv',
    'financeiro-pagamentos' => '05-financeiro-pagamentos.csv',
    'veiculos-operacao' => '06-veiculos-operacao.csv',
    'relatorios-indicadores-financeiro' => '07-relatorios-indicadores-financeiro.csv',
    'relatorios-frota-operacao' => '08-relatorios-frota-operacao.csv',
    'relatorios-relacionamento-comparativos' => '09-relatorios-relacionamento-comparativos.csv',
    'website-publico' => '10-website-publico.csv',
];

function kb_project_root(): string
{
    return dirname(__DIR__);
}

function kb_get_nested(array $data, array $parts): mixed
{
    $value = $data;
    foreach ($parts as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }
    return $value;
}

function kb_translation(string $key, string $root): string
{
    static $files = [];

    $parts = explode('.', $key);
    if (($parts[0] ?? '') === 'menu') {
        $fileKey = 'menu';
        $file = $root . '/app/Lang/pt_BR/menu.php';
        $path = array_slice($parts, 1);
    } elseif (($parts[0] ?? '') === 'modules' && isset($parts[1])) {
        $fileKey = 'modules.' . $parts[1];
        $file = $root . '/app/Lang/pt_BR/modules/' . $parts[1] . '.php';
        $path = array_slice($parts, 2);
    } else {
        return $key;
    }

    if (!is_file($file)) {
        return $key;
    }

    if (!isset($files[$fileKey])) {
        $loaded = require $file;
        $files[$fileKey] = is_array($loaded) ? $loaded : [];
    }

    $value = kb_get_nested($files[$fileKey], $path);
    return is_string($value) && $value !== '' ? $value : $key;
}

function kb_navigation_context(string $route, string $title, string $root): array
{
    $routePath = (string) (parse_url($route, PHP_URL_PATH) ?: $route);
    if ($routePath === '/pages/agenda') {
        return [
            'categoria' => 'Sistema / Agenda',
            'caminho' => 'Menu principal > Agenda',
        ];
    }
    if ($routePath === '/pages/notificacoes') {
        return [
            'categoria' => 'Sistema / Notificações',
            'caminho' => 'Menu principal > Notificações > ' . $title,
        ];
    }

    $system = [
        'programa-indicacao', 'feature-requests', 'logs', 'conceder-acesso',
        'configuracoes', 'changelog', 'gravacoes',
    ];
    $company = [
        'matrizes-filiais', 'clientes', 'mensageria', 'funcionarios', 'documentos',
        'taxas-e-servicos', 'oficinas', 'promocoes', 'central-multas',
        'contas-bancarias', 'formas-pagamento', 'gateways-pagamento',
        'planos-de-contas', 'fornecedores', 'estoque',
    ];
    $vehicles = [
        'veiculos', 'grupos', 'temporadas', 'veiculos-acessorios', 'manutencoes',
        'manutencoes-planos', 'checklists', 'checklist-modelos',
    ];
    $financial = ['financeiro', 'promissorias', 'comissoes-investidores', 'nfse'];

    $relative = trim((string) preg_replace('~^/pages/~', '', $route), '/');
    $segments = explode('/', $relative);
    $first = $segments[0] ?? '';

    if ($first === 'relatorios') {
        $groups = [
            'kpis' => 'kpis',
            'financeiro' => 'financial',
            'veicular' => 'vehicle',
            'clientes' => 'clients',
            'contratos' => 'contracts_rentals',
            'operacional' => 'operational',
            'faturas' => 'invoices',
            'comercial' => 'commercial',
            'fornecedores' => 'suppliers',
            'funcionarios' => 'employees',
            'comparativos' => 'comparisons',
        ];
        $groupKey = $groups[$segments[1] ?? ''] ?? null;
        $group = $groupKey
            ? kb_translation('menu.relatorios_menu.' . $groupKey, $root)
            : 'Outros';

        return [
            'categoria' => 'Relatórios / ' . $group,
            'caminho' => 'Relatórios > ' . $group . ' > ' . $title,
        ];
    }

    if (in_array($first, $system, true)) {
        return ['categoria' => 'Sistema / Navegacao', 'caminho' => 'Sistema > ' . $title];
    }
    if (in_array($first, ['locacoes', 'contratos'], true)) {
        return ['categoria' => 'Contratos e Locacoes / Navegacao', 'caminho' => 'Contrato/Locacoes > ' . $title];
    }
    if (in_array($first, $company, true)) {
        return ['categoria' => 'Empresa / Navegacao', 'caminho' => 'Empresa > ' . $title];
    }
    if (in_array($first, $vehicles, true)) {
        return ['categoria' => 'Veiculos / Navegacao', 'caminho' => 'Veiculos > ' . $title];
    }
    if (in_array($first, $financial, true)) {
        return ['categoria' => 'Financeiro / Navegacao', 'caminho' => 'Financeiro > ' . $title];
    }
    if ($first === 'website') {
        return ['categoria' => 'WebSite / Navegacao', 'caminho' => 'WebSite > ' . $title];
    }

    return ['categoria' => 'Navegacao geral', 'caminho' => $title];
}

function kb_collect_navigation_articles(string $root): array
{
    $navbar = $root . '/app/Views/partials/navbar.php';
    $lines = file($navbar, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        throw new RuntimeException('Nao foi possivel ler o menu principal.');
    }

    $articles = [];
    $seenRoutes = [];
    foreach ($lines as $lineNumber => $line) {
        if (!str_contains($line, 'openOrSwitchToTab(')) {
            continue;
        }
        if (!preg_match("~openOrSwitchToTab\\('([^']+)'~", $line, $routeMatch)) {
            continue;
        }
        $route = $routeMatch[1];
        if (!str_starts_with($route, '/pages/') || isset($seenRoutes[$route])) {
            continue;
        }
        if (!preg_match_all("~t\\('([^']+)'~", $line, $keyMatches) || empty($keyMatches[1])) {
            continue;
        }

        $key = end($keyMatches[1]);
        $title = kb_translation((string) $key, $root);
        if ($title === $key) {
            throw new RuntimeException("Traducao nao resolvida no menu: {$key}");
        }

        $context = kb_navigation_context($route, $title, $root);
        $slug = trim((string) preg_replace('~[^a-z0-9]+~', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $route) ?: $route)), '-');
        $questionArea = str_replace(' / ', ' - ', (string) $context['categoria']);
        $articles[] = [
            'id' => 'navegacao-' . $slug,
            'tipo' => 'navegacao',
            'intencao' => str_starts_with($route, '/pages/relatorios/') ? 'acesso' : 'navegacao',
            'categoria' => $context['categoria'],
            'pergunta' => 'Como acessar ' . $title . ' na area ' . $questionArea . ' do 7Carros?',
            'resposta' => "Acesse `{$context['caminho']}`.\n\nÉ necessário estar logado. Se o item não aparecer, confira se o seu perfil possui a permissão necessária para esse módulo.",
            'superficie' => 'Painel administrativo',
            'rota' => $route,
            'permissoes' => 'Autenticacao e permissao do modulo',
            'planos' => 'Conforme recursos do plano',
            'fontes' => 'app/Views/partials/navbar.php:' . ($lineNumber + 1) . '; ' . $key,
            'ultima_validacao' => '',
            'validado_ui' => false,
            'status' => 'fonte_revisada',
            'conflito' => '',
        ];
        $seenRoutes[$route] = true;
    }

    return $articles;
}

function kb_catalog_paths(string $root): array
{
    $catalogs = [
        $root . '/knowledge-base/articles.php',
        $root . '/knowledge-base/articles-modules.php',
    ];
    $additional = glob($root . '/knowledge-base/articles-*.php') ?: [];
    sort($additional, SORT_NATURAL | SORT_FLAG_CASE);
    foreach ($additional as $catalog) {
        if (!in_array($catalog, $catalogs, true)) {
            $catalogs[] = $catalog;
        }
    }
    return $catalogs;
}

function kb_infer_module(string $route, string $category = ''): string
{
    $path = (string) (parse_url($route, PHP_URL_PATH) ?: $route);
    $first = explode('/', trim((string) preg_replace('~^/pages/~', '', $path), '/'))[0] ?? '';

    return match (true) {
        str_starts_with($path, '/pages/relatorios/') => 'relatorios',
        str_starts_with($path, '/pages/website/'), str_starts_with($path, '/public/') => 'website',
        in_array($first, ['contratos', 'locacoes'], true) => 'contratos-locacoes',
        in_array($first, ['financeiro', 'promissorias', 'comissoes-investidores', 'nfse'], true) => 'financeiro',
        in_array($first, ['veiculos', 'veiculos-acessorios', 'grupos', 'temporadas', 'manutencoes', 'manutencoes-planos', 'checklists', 'checklist-modelos'], true),
        str_starts_with($path, '/checklists/') => 'veiculos',
        in_array($first, ['clientes', 'matrizes-filiais', 'mensageria', 'funcionarios', 'documentos', 'taxas-e-servicos', 'oficinas', 'promocoes', 'central-multas', 'contas-bancarias', 'formas-pagamento', 'gateways-pagamento', 'planos-de-contas', 'fornecedores', 'estoque'], true) => 'empresa',
        str_starts_with($path, '/assinar/'), str_starts_with($path, '/pagar/'), str_starts_with($path, '/verificar/') => 'publico',
        default => str_contains(mb_strtolower($category, 'UTF-8'), 'website') ? 'website' : 'sistema',
    };
}

function kb_infer_lot(string $module, string $route): string
{
    if ($module === 'relatorios') {
        $group = explode('/', trim((string) preg_replace('~^/pages/relatorios/~', '', $route), '/'))[0] ?? '';
        if (in_array($group, ['kpis', 'financeiro'], true)) {
            return 'relatorios-indicadores-financeiro';
        }
        if (in_array($group, ['veicular', 'contratos', 'operacional', 'faturas'], true)) {
            return 'relatorios-frota-operacao';
        }
        return 'relatorios-relacionamento-comparativos';
    }
    if (str_starts_with($route, '/pages/agenda') || str_starts_with($route, '/pages/notificacoes')) {
        return 'agenda-notificacoes';
    }
    return match ($module) {
        'website', 'publico' => 'website-publico',
        'contratos-locacoes' => 'contratos-locacoes',
        'financeiro' => 'financeiro-pagamentos',
        'veiculos' => 'veiculos-operacao',
        'empresa' => str_starts_with($route, '/pages/contas-bancarias')
            || str_starts_with($route, '/pages/formas-pagamento')
            || str_starts_with($route, '/pages/gateways-pagamento')
            || str_starts_with($route, '/pages/planos-de-contas')
                ? 'financeiro-pagamentos'
                : 'empresa-cadastros',
        default => 'acesso-sistema',
    };
}

function kb_infer_type(array $article): string
{
    if (str_starts_with((string) ($article['id'] ?? ''), 'navegacao-')) {
        return 'navegacao';
    }
    $haystack = mb_strtolower((string) (($article['categoria'] ?? '') . ' ' . ($article['pergunta'] ?? '')), 'UTF-8');
    if (preg_match('~problema|por que|não consigo|nao consigo|permiss|seguran|erro~u', $haystack)) {
        return 'diagnostico';
    }
    if (str_starts_with($haystack, 'como ') || str_contains($haystack, ' / cadastro') || str_contains($haystack, ' / emissão')) {
        return 'procedimento';
    }
    return 'conceito';
}

function kb_divergence_overrides(): array
{
    return [
        'locacoes-cancelar' => [
            'divergencia_id' => 'locacoes-permissao-cancelar',
            'documentado' => 'A documentação atribui o cancelamento e a exclusão à permissão locacoes.cancelar e separa essa ação das permissões de editar, devolver e substituir.',
            'encontrado' => 'O Controller atual diverge na aplicação das permissões gerais para cancelar ou excluir locações.',
            'risco' => 'O atendimento pode atribuir uma liberação ou um bloqueio a uma permissão que não é aplicada de forma consistente.',
            'correcao_sugerida' => 'Alinhar o Controller à permissão locacoes.cancelar e validar o fluxo com perfis que possuam e não possuam essa permissão.',
        ],
        'contratos-autorenovacao' => [
            'divergencia_id' => 'contratos-autorenovacao-fim',
            'documentado' => 'A documentação prevê a opção Fim para encerrar a renovação automática na data final do contrato.',
            'encontrado' => 'A opção Fim não existe no seletor atual da interface.',
            'risco' => 'O atendimento pode orientar uma opção que o cliente não consegue selecionar.',
            'correcao_sugerida' => 'Implementar e validar a opção Fim na interface ou retirar essa orientação da documentação, conforme a decisão de produto.',
        ],
        'financeiro-criar-lancamento' => [
            'divergencia_id' => 'financeiro-validacao-lancamento',
            'documentado' => 'A documentação exige cliente em receitas, fornecedor em despesas e pelo menos um item no lançamento.',
            'encontrado' => 'A interface e o Controller aceitam outros vínculos e permitem subtotal sem item.',
            'risco' => 'Podem ser criados lançamentos incompatíveis com as regras documentadas e com orientações de suporte.',
            'correcao_sugerida' => 'Aplicar as mesmas validações no formulário e no Controller ou revisar formalmente as regras documentadas.',
        ],
        'multas-online-iniciar-indicacao-conflito' => [
            'divergencia_id' => 'multas-indicacao-dados-condutor',
            'documentado' => 'A documentação exige CPF e CNH para indicar o real infrator.',
            'encontrado' => 'O formulário atual solicita somente CPF para real infrator; a CNH aparece apenas para principal condutor.',
            'risco' => 'Uma orientação baseada na documentação não corresponde aos campos exibidos e pode impedir ou comprometer o envio da indicação.',
            'correcao_sugerida' => 'Alinhar os campos e validações do formulário aos dados exigidos pela integração e validar os dois tipos de indicação.',
        ],
        'funcionarios-permissoes' => [
            'divergencia_id' => 'roles-menu-controllers',
            'documentado' => 'A documentação prevê ocultação dos itens sem permissão no menu e validação correspondente no servidor.',
            'encontrado' => 'O menu e alguns Controllers não aplicam essas verificações de forma consistente.',
            'risco' => 'Um funcionário pode visualizar uma opção sem conseguir usá-la ou alcançar uma ação sem a proteção documentada.',
            'correcao_sugerida' => 'Auditar o menu e os Controllers por permissão e executar testes de autorização com funções restritas.',
        ],
        'website-ativar' => [
            'divergencia_id' => 'website-ativacao-permissao',
            'documentado' => 'A documentação vincula a configuração do website à permissão website.configurar.',
            'encontrado' => 'O menu, a tela e a API de ativação não aplicam website.configurar.',
            'risco' => 'Usuários sem a autorização documentada podem alcançar o fluxo de ativação.',
            'correcao_sugerida' => 'Aplicar a permissão definida no menu, na tela e na API de ativação e testar perfis autorizados e restritos.',
        ],
        'relatorios-filtros-exportar' => [
            'divergencia_id' => 'relatorios-formatos-exportacao',
            'caminho' => 'Relatórios > Grupo do relatório > Relatório desejado',
            'documentado' => 'A documentação geral prevê exportação dos relatórios em PDF, Excel e CSV.',
            'encontrado' => 'As telas e rotas atuais oferecem somente exportação em PDF.',
            'risco' => 'O atendimento pode prometer formatos que não estão disponíveis na interface.',
            'correcao_sugerida' => 'Implementar os formatos previstos ou atualizar a especificação para refletir apenas o PDF atualmente disponível.',
        ],
        'relatorios-ticket-medio' => [
            'divergencia_id' => 'relatorios-ticket-medio-publicacao',
            'caminho' => 'Não disponível no menu.',
            'documentado' => 'A especificação funcional não possui uma seção própria para o relatório Ticket Médio.',
            'encontrado' => 'Existem view, API, PDF, rota e permissão para o relatório, mas não existe entrada no menu.',
            'risco' => 'O suporte pode orientar uma funcionalidade implementada tecnicamente, mas ainda não publicada como produto.',
            'correcao_sugerida' => 'Decidir se o relatório será publicado; em caso positivo, incluí-lo no menu e na especificação, ou remover a implementação não suportada.',
        ],
        'oficinas-problemas' => [
            'divergencia_id' => 'oficinas-permissao-especifica',
            'documentado' => 'O padrão de autorização do sistema prevê permissões por módulo e ação.',
            'encontrado' => 'O fluxo de Oficinas não valida uma chave de permissão específica.',
            'risco' => 'Não é possível orientar com segurança como restringir o cadastro de oficinas por função.',
            'correcao_sugerida' => 'Definir as permissões de Oficinas e aplicá-las nas telas e operações do Controller antes de publicar orientações de acesso.',
        ],
        'fornecedores-problemas' => [
            'divergencia_id' => 'fornecedores-gateways-split',
            'documentado' => 'O cadastro de fornecedor apresenta opções de conta para split em diferentes gateways.',
            'encontrado' => 'Somente o Asaas possui implementação concreta de split.',
            'risco' => 'O atendimento pode prometer divisão automática de valores por um gateway sem suporte efetivo.',
            'correcao_sugerida' => 'Limitar as opções da interface aos gateways implementados ou concluir e validar as demais integrações de split.',
        ],
        'comissoes-investidores-problemas' => [
            'divergencia_id' => 'comissoes-split-pendente',
            'documentado' => 'A documentação descreve o serviço de split para comissões de investidores.',
            'encontrado' => 'A própria documentação registra como pendentes a integração no fluxo de cobrança e o teste com conta Asaas em sandbox.',
            'risco' => 'O atendimento pode tratar como concluído um fluxo que ainda depende de integração e validação.',
            'correcao_sugerida' => 'Concluir a integração e os testes pendentes antes de orientar o split como funcionalidade disponível.',
        ],
        'website-aparencia-problemas' => [
            'divergencia_id' => 'website-preview-aparencia',
            'documentado' => 'A documentação prevê pré-visualização completa e em tempo real das alterações de aparência.',
            'encontrado' => 'O endpoint atual informa que a pré-visualização está indisponível.',
            'risco' => 'O cliente pode publicar alterações visuais sem conseguir conferi-las previamente como documentado.',
            'correcao_sugerida' => 'Implementar o preview previsto ou atualizar a documentação e a interface para deixar a indisponibilidade explícita.',
        ],
        'website-conteudos-problemas' => [
            'divergencia_id' => 'website-editor-conteudos',
            'documentado' => 'A documentação prevê editor WYSIWYG para os conteúdos do website.',
            'encontrado' => 'A interface atual usa inputs e textareas, sem editor WYSIWYG.',
            'risco' => 'A orientação pode induzir o cliente a procurar recursos de edição formatada inexistentes.',
            'correcao_sugerida' => 'Implementar o editor documentado ou ajustar a documentação à edição de texto disponível atualmente.',
        ],
        'website-banners-problemas' => [
            'divergencia_id' => 'website-reordenacao-banners',
            'documentado' => 'A documentação e o backend preveem reordenação dos banners.',
            'encontrado' => 'A interface atual não oferece controle para ordenar os banners.',
            'risco' => 'O cliente não consegue executar pela interface uma ação prevista no sistema.',
            'correcao_sugerida' => 'Adicionar o controle de reordenação na tela e validar sua integração com o endpoint existente.',
        ],
    ];
}

function kb_extract_descriptive_path(string $response): string
{
    if (!preg_match_all('~`([^`]*>[^`]*)`~u', $response, $matches)) {
        return '';
    }
    foreach ($matches[1] as $path) {
        $path = trim((string) $path);
        if ($path !== '') {
            return $path;
        }
    }
    return '';
}

function kb_divergence_metadata(array $article): array
{
    $id = (string) ($article['id'] ?? 'divergencia-sem-id');
    $asciiId = iconv('UTF-8', 'ASCII//TRANSLIT', $id) ?: $id;
    $fallbackId = trim((string) preg_replace('~[^a-z0-9]+~', '-', strtolower($asciiId)), '-');
    if ($fallbackId === '') {
        $fallbackId = substr(hash('sha256', $id), 0, 16);
    }
    $override = kb_divergence_overrides()[$id] ?? [];
    $pick = static function (string $field, string $fallback = '') use ($article, $override): string {
        $value = trim((string) ($article[$field] ?? ''));
        if ($value !== '') {
            return $value;
        }
        $value = trim((string) ($override[$field] ?? ''));
        return $value !== '' ? $value : $fallback;
    };

    $conflict = trim((string) ($article['conflito'] ?? $article['alertas_auditoria'] ?? ''));
    $path = $pick('caminho');
    if ($path === '') {
        $path = kb_extract_descriptive_path((string) ($article['resposta'] ?? ''));
    }

    return [
        'divergencia_id' => $pick('divergencia_id', 'artigo-' . $fallbackId),
        'caminho' => $path,
        'documentado' => $pick('documentado', 'O comportamento esperado não foi estruturado no artigo; consulte as fontes citadas.'),
        'encontrado' => $pick('encontrado', $conflict !== '' ? $conflict : 'O comportamento encontrado não foi estruturado no artigo.'),
        'risco' => $pick('risco', 'O artigo permanece bloqueado para atendimento enquanto a divergência não for validada.'),
        'correcao_sugerida' => $pick('correcao_sugerida', 'Validar as fontes e alinhar documentação e implementação antes de aprovar o artigo.'),
    ];
}

function kb_normalize_article(array $article, string $catalog): array
{
    $route = (string) ($article['rota'] ?? '');
    $module = (string) ($article['modulo'] ?? kb_infer_module($route, (string) ($article['categoria'] ?? '')));
    $moduleAliases = [
        'agenda' => 'sistema',
        'changelog' => 'sistema',
        'conceder acesso' => 'sistema',
        'configurações gerais' => 'sistema',
        'logs' => 'sistema',
        'notificações' => 'sistema',
        'pedidos de recurso' => 'sistema',
        'programa de indicação' => 'sistema',
    ];
    $module = $moduleAliases[mb_strtolower($module, 'UTF-8')] ?? $module;
    $lotAliases = [
        'sistema' => 'acesso-sistema',
    ];
    $typeAliases = [
        'problema' => 'diagnostico',
        'permissao' => 'diagnostico',
        'seguranca' => 'diagnostico',
    ];
    $lot = (string) ($article['lote'] ?? kb_infer_lot($module, $route));
    if ($lot === 'relatorios' || ($lot === 'acesso-sistema' && (str_starts_with($route, '/pages/agenda') || str_starts_with($route, '/pages/notificacoes')))) {
        $lot = kb_infer_lot($module, $route);
    }
    $type = (string) ($article['tipo'] ?? kb_infer_type($article));
    $coveredRoutes = $article['rotas_cobertas'] ?? $article['rotas'] ?? [];
    if (!is_array($coveredRoutes)) {
        $coveredRoutes = [];
    }
    $coveredRoutes = array_values(array_unique(array_filter(array_map('strval', $coveredRoutes))));

    $article['modulo'] = $module;
    $article['lote'] = $lotAliases[$lot] ?? $lot;
    $article['tipo'] = $typeAliases[$type] ?? $type;
    $article['rotas_cobertas'] = $coveredRoutes;
    $article['permissoes'] = (string) ($article['permissoes'] ?? 'Autenticação e permissões do módulo');
    $article['planos'] = (string) ($article['planos'] ?? 'Conforme recursos do plano');
    $article['validado_ui'] = ($article['validado_ui'] ?? false) === true;
    $article['conflito'] = (string) ($article['conflito'] ?? $article['alertas_auditoria'] ?? '');
    if (trim($article['conflito']) !== '') {
        $article['status'] = 'rascunho';
        $article['validado_ui'] = false;
        foreach (kb_divergence_metadata($article) as $field => $value) {
            $article[$field] = $value;
        }
    }
    $article['catalogo'] = $catalog;
    if (str_starts_with($route, '/pages/relatorios/')) {
        $article['relatorio_id'] = (string) ($article['relatorio_id'] ?? preg_replace('~^/pages/relatorios/~', '', $route));
        if ($article['tipo'] === 'navegacao') {
            $article['intencao'] = 'acesso';
        }
    }
    return $article;
}

function kb_load_articles(string $root): array
{
    $articles = [];
    foreach (kb_catalog_paths($root) as $catalog) {
        $loaded = require $catalog;
        if (!is_array($loaded)) {
            throw new RuntimeException("O catalogo {$catalog} precisa retornar um array.");
        }
        foreach ($loaded as $article) {
            if (!is_array($article)) {
                throw new RuntimeException("O catalogo {$catalog} contem um artigo invalido.");
            }
            $articles[] = kb_normalize_article($article, basename($catalog));
        }
    }
    foreach (kb_collect_navigation_articles($root) as $article) {
        $articles[] = kb_normalize_article($article, 'app/Views/partials/navbar.php');
    }
    return $articles;
}

function kb_polish_pt_br(string $text): string
{
    $phrases = [
        'Qual e' => 'Qual é',
        'qual e' => 'qual é',
        'Nao e' => 'Não é',
        'nao e' => 'não é',
        'Nao ha' => 'Não há',
        'nao ha' => 'não há',
        'Nao foi' => 'Não foi',
        'nao foi' => 'não foi',
        'Nao pode' => 'Não pode',
        'nao pode' => 'não pode',
    ];
    $text = strtr($text, $phrases);

    $words = [
        'acao' => 'ação', 'acoes' => 'ações', 'alem' => 'além', 'aplicacao' => 'aplicação', 'area' => 'área',
        'aplicaveis' => 'aplicáveis', 'autentico' => 'autêntico', 'bancaria' => 'bancária',
        'bancario' => 'bancário', 'botao' => 'botão', 'cartao' => 'cartão', 'cartoes' => 'cartões',
        'caucao' => 'caução', 'codigo' => 'código', 'codigos' => 'códigos', 'combustivel' => 'combustível',
        'composicao' => 'composição', 'concessao' => 'concessão', 'conexao' => 'conexão',
        'conexoes' => 'conexões', 'configuracao' => 'configuração', 'configuracoes' => 'configurações',
        'confirmacao' => 'confirmação', 'cobranca' => 'cobrança', 'cobrancas' => 'cobranças',
        'credito' => 'crédito', 'criacao' => 'criação',
        'devolucao' => 'devolução', 'descricao' => 'descrição', 'diferenca' => 'diferença',
        'disponivel' => 'disponível', 'disponiveis' => 'disponíveis', 'edicao' => 'edição',
        'eletronico' => 'eletrônico', 'eletronica' => 'eletrônica', 'emissao' => 'emissão',
        'endereco' => 'endereço', 'especifico' => 'específico', 'especifica' => 'específica',
        'fisica' => 'física', 'fisico' => 'físico', 'geracao' => 'geração', 'habilitacao' => 'habilitação',
        'historico' => 'histórico', 'impressao' => 'impressão', 'informacao' => 'informação',
        'informacoes' => 'informações', 'italico' => 'itálico', 'juridica' => 'jurídica',
        'lancado' => 'lançado', 'lancada' => 'lançada', 'lancamento' => 'lançamento',
        'lancamentos' => 'lançamentos', 'locacao' => 'locação', 'locacoes' => 'locações',
        'marcacao' => 'marcação', 'maximo' => 'máximo', 'metodo' => 'método', 'metodos' => 'métodos',
        'minimo' => 'mínimo', 'nao' => 'não', 'navegacao' => 'navegação',
        'necessario' => 'necessário', 'necessaria' => 'necessária',
        'numero' => 'número', 'numeros' => 'números', 'obrigatorio' => 'obrigatório',
        'obrigatoria' => 'obrigatória', 'obrigatorios' => 'obrigatórios', 'observacao' => 'observação',
        'observacoes' => 'observações', 'odometro' => 'odômetro', 'opcao' => 'opção', 'opcoes' => 'opções',
        'pagina' => 'página', 'paginas' => 'páginas', 'permissao' => 'permissão', 'permissoes' => 'permissões',
        'periodo' => 'período', 'preferencia' => 'preferência', 'publica' => 'pública', 'publicas' => 'públicas',
        'publico' => 'público', 'publicos' => 'públicos', 'questao' => 'questão', 'questoes' => 'questões',
        'razao' => 'razão', 'recomecar' => 'recomeçar', 'relatorio' => 'relatório', 'relatorios' => 'relatórios',
        'secao' => 'seção', 'secoes' => 'seções', 'sera' => 'será', 'sao' => 'são',
        'saida' => 'saída', 'situacao' => 'situação', 'substituicao' => 'substituição',
        'tambem' => 'também', 'transacao' => 'transação', 'transacoes' => 'transações',
        'usuario' => 'usuário', 'usuarios' => 'usuários', 'validacao' => 'validação',
        'veiculo' => 'veículo', 'veiculos' => 'veículos', 'verificacao' => 'verificação',
    ];

    $pattern = '~(?<![\p{L}\p{N}_])(' . implode('|', array_map('preg_quote', array_keys($words))) . ')(?![\p{L}\p{N}_])~iu';
    return preg_replace_callback($pattern, static function (array $match) use ($words): string {
        $original = $match[0];
        $replacement = $words[mb_strtolower($original, 'UTF-8')];
        if ($original === mb_strtoupper($original, 'UTF-8')) {
            return mb_strtoupper($replacement, 'UTF-8');
        }
        if (mb_substr($original, 0, 1, 'UTF-8') === mb_strtoupper(mb_substr($original, 0, 1, 'UTF-8'), 'UTF-8')) {
            return mb_strtoupper(mb_substr($replacement, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($replacement, 1, null, 'UTF-8');
        }
        return $replacement;
    }, $text) ?? $text;
}

function kb_render_response(array $article): string
{
    $response = trim((string) ($article['resposta'] ?? ''));
    $response = trim((string) preg_replace("~\R{3,}~", "\n\n", $response));
    return kb_polish_pt_br($response);
}

function kb_validate_articles(array $articles): array
{
    $errors = [];
    $ids = [];
    $questions = [];
    $fingerprints = [];
    $required = [
        'id', 'modulo', 'lote', 'tipo', 'categoria', 'pergunta', 'resposta',
        'superficie', 'rota', 'permissoes', 'planos', 'fontes', 'status', 'catalogo',
    ];

    foreach ($articles as $index => $article) {
        $label = (string) ($article['id'] ?? "linha-{$index}");
        foreach ($required as $field) {
            if (!isset($article[$field]) || trim((string) $article[$field]) === '') {
                $errors[] = "{$label}: campo obrigatorio ausente: {$field}";
            }
        }

        $id = (string) ($article['id'] ?? '');
        if (isset($ids[$id])) {
            $errors[] = "ID duplicado: {$id}";
        }
        $ids[$id] = true;

        $question = kb_polish_pt_br(trim((string) ($article['pergunta'] ?? '')));
        $normalizedQuestion = mb_strtolower($question, 'UTF-8');
        if (isset($questions[$normalizedQuestion])) {
            $errors[] = "Pergunta duplicada: {$question}";
        }
        $questions[$normalizedQuestion] = true;
        if (str_contains($question, "\n")) {
            $errors[] = "{$label}: a pergunta deve ocupar uma unica linha";
        }

        $response = kb_render_response($article);
        $fingerprint = hash('sha256', kb_polish_pt_br((string) ($article['categoria'] ?? '')) . "\0" . $question . "\0" . $response);
        if (isset($fingerprints[$fingerprint])) {
            $errors[] = "Conteudo duplicado: {$label} e {$fingerprints[$fingerprint]}";
        }
        $fingerprints[$fingerprint] = $label;

        if (!in_array((string) ($article['tipo'] ?? ''), KB_ARTICLE_TYPES, true)) {
            $errors[] = "{$label}: tipo de artigo invalido";
        }
        if (!array_key_exists((string) ($article['lote'] ?? ''), KB_LOTS)) {
            $errors[] = "{$label}: lote invalido";
        }
        if (!in_array((string) ($article['status'] ?? ''), KB_ARTICLE_STATUSES, true)) {
            $errors[] = "{$label}: status editorial invalido";
        }
        if (!is_bool($article['validado_ui'] ?? null)) {
            $errors[] = "{$label}: validado_ui precisa ser booleano";
        }
        if (!is_array($article['rotas_cobertas'] ?? null)) {
            $errors[] = "{$label}: rotas_cobertas precisa ser uma lista";
        }
        if (!is_string($article['conflito'] ?? null)) {
            $errors[] = "{$label}: conflito precisa ser texto";
        }
        if (trim((string) ($article['conflito'] ?? '')) !== '' && ($article['status'] ?? '') !== 'rascunho') {
            $errors[] = "{$label}: artigo com conflito precisa permanecer em rascunho";
        }
        if (trim((string) ($article['conflito'] ?? '')) !== '') {
            foreach (['divergencia_id', 'documentado', 'encontrado', 'risco', 'correcao_sugerida'] as $field) {
                if (trim((string) ($article[$field] ?? '')) === '') {
                    $errors[] = "{$label}: metadado de divergencia ausente: {$field}";
                }
            }
            if (!preg_match('~^[a-z0-9]+(?:-[a-z0-9]+)*$~', (string) ($article['divergencia_id'] ?? ''))) {
                $errors[] = "{$label}: divergencia_id invalido";
            }
        }
        if (($article['status'] ?? '') === 'aprovado' && ($article['validado_ui'] ?? false) !== true) {
            $errors[] = "{$label}: artigo aprovado precisa de validacao visual";
        }
        foreach (($article['rotas_cobertas'] ?? []) as $coveredRoute) {
            if (!is_string($coveredRoute) || trim($coveredRoute) === '') {
                $errors[] = "{$label}: rota coberta invalida";
            }
        }
        if (str_contains((string) ($article['resposta'] ?? ''), '{{url}}')) {
            $errors[] = "{$label}: placeholder de URL nao e permitido";
        }
        if (preg_match('~^\s*Link\s+(?:direto|para)\b~mi', (string) ($article['resposta'] ?? ''))) {
            $errors[] = "{$label}: instrucao de link direto nao e permitida";
        }
        $withoutCode = preg_replace('~`[^`]*`~u', '', $response) ?? $response;
        if (str_contains($withoutCode, ' > ')) {
            $errors[] = "{$label}: caminho com > fora de crases";
        }
        if (preg_match('~<\/?[a-z][^>]*>~i', $response)) {
            $errors[] = "{$label}: HTML nao e permitido";
        }
        if (preg_match('~\[[^\]]+\]\([^\)]+\)~', $response)) {
            $errors[] = "{$label}: link Markdown nao e permitido";
        }
        if (preg_match('~^#{1,6}\s~m', $response)) {
            $errors[] = "{$label}: titulos Markdown nao sao permitidos";
        }
        if (preg_match('~^\s*\|.*\|\s*$~m', $response)) {
            $errors[] = "{$label}: tabelas Markdown nao sao permitidas";
        }
        if (preg_match('~https?://|www\.~i', $response)) {
            $errors[] = "{$label}: URL direta nao e permitida";
        }
    }

    return $errors;
}

function kb_validate_report_intentions(array $articles): array
{
    $expected = ['acesso', 'finalidade', 'filtros', 'campos_resultados', 'interpretacao', 'exportacao', 'problemas'];
    $visible = [];
    $found = [];
    foreach ($articles as $article) {
        $route = (string) ($article['rota'] ?? '');
        if (!str_starts_with($route, '/pages/relatorios/')) {
            continue;
        }
        $reportId = (string) ($article['relatorio_id'] ?? preg_replace('~^/pages/relatorios/~', '', $route));
        if (($article['tipo'] ?? '') === 'navegacao') {
            $visible[$reportId] = $route;
        }
        $intent = (string) ($article['intencao'] ?? '');
        if (in_array($intent, $expected, true)) {
            $found[$reportId][$intent][] = (string) ($article['id'] ?? '');
        }
    }

    $errors = [];
    foreach ($visible as $reportId => $route) {
        foreach ($expected as $intent) {
            $count = count($found[$reportId][$intent] ?? []);
            if ($count !== 1) {
                $errors[] = "Relatorio {$route}: esperado 1 artigo para {$intent}, encontrado {$count}";
            }
        }
    }
    return $errors;
}

function kb_write_csv(string $path, array $header, array $rows): void
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException("Nao foi possivel criar {$directory}");
    }
    $handle = fopen($path, 'wb');
    if ($handle === false) {
        throw new RuntimeException("Nao foi possivel criar {$path}");
    }
    fputcsv($handle, $header, ',', '"', '');
    foreach ($rows as $row) {
        fputcsv($handle, $row, ',', '"', '');
    }
    fclose($handle);
}

function kb_render_csv(array $header, array $rows): string
{
    $handle = fopen('php://temp', 'w+b');
    if ($handle === false) {
        throw new RuntimeException('Nao foi possivel preparar o CSV em memoria.');
    }
    fputcsv($handle, $header, ',', '"', '');
    foreach ($rows as $row) {
        fputcsv($handle, $row, ',', '"', '');
    }
    rewind($handle);
    $contents = stream_get_contents($handle);
    fclose($handle);
    if ($contents === false) {
        throw new RuntimeException('Nao foi possivel renderizar o CSV.');
    }
    return $contents;
}

function kb_divergence_unique_values(array $values): array
{
    return array_values(array_unique(array_filter(array_map(
        static fn (mixed $value): string => trim((string) $value),
        $values
    ))));
}

function kb_render_divergence_statement(array $values): string
{
    $values = kb_divergence_unique_values($values);
    if (count($values) === 1) {
        return $values[0];
    }
    return implode("\n", array_map(static fn (string $value): string => '- ' . $value, $values));
}

function kb_render_divergences_markdown(array $articles): string
{
    $groups = [];
    foreach ($articles as $article) {
        if (trim((string) ($article['conflito'] ?? '')) === '') {
            continue;
        }

        $metadata = kb_divergence_metadata($article);
        $divergenceId = $metadata['divergencia_id'];
        if (!isset($groups[$divergenceId])) {
            $groups[$divergenceId] = [
                'titulo' => kb_polish_pt_br((string) ($article['categoria'] ?? $divergenceId)),
                'caminhos' => [],
                'artigos' => [],
                'documentado' => [],
                'encontrado' => [],
                'risco' => [],
                'correcao_sugerida' => [],
                'fontes' => [],
            ];
        }

        if ($metadata['caminho'] !== '') {
            $groups[$divergenceId]['caminhos'][] = $metadata['caminho'];
        }
        $groups[$divergenceId]['artigos'][] = [
            'id' => (string) ($article['id'] ?? ''),
            'pergunta' => kb_polish_pt_br((string) ($article['pergunta'] ?? '')),
        ];
        foreach (['documentado', 'encontrado', 'risco', 'correcao_sugerida'] as $field) {
            $groups[$divergenceId][$field][] = $metadata[$field];
        }
        foreach (explode(';', (string) ($article['fontes'] ?? '')) as $source) {
            $source = trim($source);
            if ($source !== '') {
                $groups[$divergenceId]['fontes'][] = $source;
            }
        }
    }

    ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);
    $articleCount = array_sum(array_map(
        static fn (array $group): int => count($group['artigos']),
        $groups
    ));
    $markdown = "# Divergências da base de atendimento\n\n";
    $markdown .= "Arquivo gerado automaticamente a partir dos metadados estruturados do catálogo. "
        . "Reúne " . count($groups) . " divergências que bloqueiam {$articleCount} artigos.\n\n";
    $markdown .= "Os artigos listados permanecem como rascunho e não são exportados para os CSVs de atendimento. "
        . "Corrija ou valide a divergência antes de remover o bloqueio editorial.\n";

    $number = 0;
    foreach ($groups as $divergenceId => $group) {
        $number++;
        $paths = kb_divergence_unique_values($group['caminhos']);
        $sources = kb_divergence_unique_values($group['fontes']);
        usort($group['artigos'], static fn (array $a, array $b): int => $a['id'] <=> $b['id']);

        $markdown .= "\n## {$number}. {$group['titulo']}\n\n";
        $markdown .= "**Identificador:** `{$divergenceId}`\n\n";
        if ($paths === []) {
            $markdown .= "**Caminho:** não há caminho descritivo confirmado no catálogo.\n\n";
        } else {
            $renderedPaths = array_map(static function (string $path): string {
                return str_contains($path, ' > ') ? "`{$path}`" : $path;
            }, $paths);
            $markdown .= '**Caminho:** ' . implode('; ', $renderedPaths) . "\n\n";
        }
        $markdown .= "**Perguntas e artigos afetados:**\n\n";
        foreach ($group['artigos'] as $affectedArticle) {
            $markdown .= "- `{$affectedArticle['id']}` — {$affectedArticle['pergunta']}\n";
        }
        $markdown .= "\n### Esperado / documentação\n\n";
        $markdown .= kb_render_divergence_statement($group['documentado']) . "\n";
        $markdown .= "\n### Encontrado / implementação\n\n";
        $markdown .= kb_render_divergence_statement($group['encontrado']) . "\n";
        $markdown .= "\n### Risco\n\n";
        $markdown .= kb_render_divergence_statement($group['risco']) . "\n";
        $markdown .= "\n### Correção sugerida\n\n";
        $markdown .= kb_render_divergence_statement($group['correcao_sugerida']) . "\n";
        $markdown .= "\n### Fontes\n\n";
        if ($sources === []) {
            $markdown .= "- Nenhuma fonte estruturada no artigo.\n";
        } else {
            foreach ($sources as $source) {
                $markdown .= "- `{$source}`\n";
            }
        }
    }

    return $markdown;
}

function kb_collect_page_routes(string $root): array
{
    $source = file_get_contents($root . '/app/Routes/web.php');
    if ($source === false) {
        throw new RuntimeException('Nao foi possivel ler app/Routes/web.php.');
    }
    $pattern = <<<'REGEX'
~\$router->get\('(/pages/[^']+)'~
REGEX;
    preg_match_all($pattern, $source, $matches);
    $routes = $matches[1] ?? [];

    $publicPatterns = [
        <<<'REGEX'
~\$router->get\('(/assinar/\{codigo\})'~
REGEX,
        <<<'REGEX'
~\$router->get\('(/pagar/\{codigo\})'~
REGEX,
        <<<'REGEX'
~\$router->get\('(/verificar/[^']+)'~
REGEX,
        <<<'REGEX'
~\$router->get\('(/public/redefinir-senha)'~
REGEX,
        <<<'REGEX'
~\$router->get\('(/checklists/(?:digital|vinculados|novo|visualizar/\{id\}))'~
REGEX,
    ];
    foreach ($publicPatterns as $publicPattern) {
        preg_match_all($publicPattern, $source, $publicMatches);
        $routes = array_merge($routes, $publicMatches[1] ?? []);
    }

    $routes = array_values(array_unique($routes));
    sort($routes, SORT_NATURAL | SORT_FLAG_CASE);
    return $routes;
}

function kb_route_surface(string $route): string
{
    return match (true) {
        str_starts_with($route, '/assinar/') => 'Página pública de assinatura',
        str_starts_with($route, '/pagar/') => 'Página pública de pagamento',
        str_starts_with($route, '/verificar/') => 'Página pública de verificação',
        str_starts_with($route, '/checklists/') => 'Checklist mobile',
        str_starts_with($route, '/public/') => 'Website público',
        default => 'Painel administrativo',
    };
}

function kb_auxiliary_parent_route(string $route): ?string
{
    if (!str_starts_with($route, '/pages/')) {
        return null;
    }

    $parts = explode('/', trim($route, '/'));
    if (count($parts) < 3) {
        return null;
    }

    $auxiliarySegments = [
        'adicionar', 'editar', 'visualizar', 'cancelar', 'devolver', 'substituir',
        'templates', 'testar', 'qrcode', 'emitir', 'detalhes',
    ];
    $tail = array_slice($parts, 2);
    $isAuxiliary = str_contains($route, '/offcanvas-')
        || array_intersect($tail, $auxiliarySegments) !== []
        || preg_match('~\{[^}]+\}~', $route) === 1;

    return $isAuxiliary ? '/pages/' . $parts[1] : null;
}

function kb_exportable_articles(array $articles, bool $approvedOnly = false): array
{
    $filtered = array_values(array_filter($articles, static function (array $article) use ($approvedOnly): bool {
        if (trim((string) ($article['conflito'] ?? '')) !== '') {
            return false;
        }
        if ($approvedOnly) {
            return ($article['status'] ?? '') === 'aprovado' && ($article['validado_ui'] ?? false) === true;
        }
        return in_array(($article['status'] ?? ''), ['fonte_revisada', 'aprovado'], true);
    }));
    $lotOrder = array_flip(array_keys(KB_LOTS));
    usort($filtered, static fn (array $a, array $b): int => [
        $lotOrder[$a['lote']] ?? 999,
        kb_polish_pt_br((string) $a['categoria']),
        kb_polish_pt_br((string) $a['pergunta']),
    ] <=> [
        $lotOrder[$b['lote']] ?? 999,
        kb_polish_pt_br((string) $b['categoria']),
        kb_polish_pt_br((string) $b['pergunta']),
    ]);
    return $filtered;
}

function kb_public_rows(array $articles): array
{
    return array_map(static fn (array $article): array => [
        kb_polish_pt_br((string) $article['categoria']),
        kb_polish_pt_br((string) $article['pergunta']),
        kb_render_response($article),
    ], $articles);
}

function kb_inventory_rows(string $root, array $articles): array
{
    $navigation = [];
    $detailDirect = [];
    $detailExplicit = [];
    foreach ($articles as $article) {
        $id = (string) $article['id'];
        $route = (string) $article['rota'];
        if (($article['tipo'] ?? '') === 'navegacao') {
            $navigation[$route][] = $id;
            continue;
        }
        if (!in_array(($article['status'] ?? ''), ['fonte_revisada', 'aprovado'], true)) {
            continue;
        }
        if (trim((string) ($article['conflito'] ?? '')) !== '') {
            continue;
        }
        $detailDirect[$route][] = $id;
        foreach ($article['rotas_cobertas'] as $coveredRoute) {
            $detailExplicit[(string) $coveredRoute][] = $id;
        }
    }

    $rows = [];
    foreach (kb_collect_page_routes($root) as $route) {
        $scope = 'principal';
        $exception = '';
        if ($route === '/checklists/novo') {
            $scope = 'externo_excluido';
            $exception = 'Fluxo de criação executado pelo aplicativo externo; artigos existentes são preservados.';
        } elseif ($route === '/pages/relatorios/kpis/ticket-medio') {
            $scope = 'conflito_produto';
            $exception = 'Implementado no código, mas ausente do menu e da especificação funcional.';
        }

        $navigationRefs = array_values(array_unique($navigation[$route] ?? []));
        $directRefs = array_values(array_unique($detailDirect[$route] ?? []));
        $explicitRefs = array_values(array_unique($detailExplicit[$route] ?? []));
        $detailRefs = array_values(array_unique(array_merge($directRefs, $explicitRefs)));
        if ($scope !== 'principal') {
            $detailStatus = 'fora_escopo';
        } elseif ($directRefs !== []) {
            $detailStatus = 'direta';
        } elseif ($explicitRefs !== []) {
            $detailStatus = 'explicita';
        } elseif ($navigationRefs !== []) {
            $detailStatus = 'somente_navegacao';
        } else {
            $detailStatus = 'pendente';
        }
        $navigationStatus = $navigationRefs !== []
            ? 'coberta'
            : ($detailRefs !== [] || !str_starts_with($route, '/pages/') ? 'nao_aplicavel' : 'pendente');
        $module = kb_infer_module($route);

        $rows[] = [
            kb_route_surface($route),
            $module,
            $route,
            $scope,
            $navigationStatus,
            $detailStatus,
            implode('|', $navigationRefs),
            implode('|', $detailRefs),
            $exception,
        ];
    }
    return $rows;
}

function kb_build_artifacts(string $root, array $articles, bool $onlyApproved = false): array
{
    $normal = kb_exportable_articles($articles);
    $approved = kb_exportable_articles($articles, true);
    if ($onlyApproved) {
        return [
            'base-conhecimento-aprovada.csv' => [KB_PUBLIC_HEADER, kb_public_rows($approved)],
        ];
    }

    $artifacts = [
        'base-conhecimento.csv' => [KB_PUBLIC_HEADER, kb_public_rows($normal)],
        'base-conhecimento-aprovada.csv' => [KB_PUBLIC_HEADER, kb_public_rows($approved)],
    ];
    foreach (KB_LOTS as $lot => $filename) {
        $lotArticles = array_values(array_filter($normal, static fn (array $article): bool => $article['lote'] === $lot));
        $artifacts['lotes/' . $filename] = [KB_PUBLIC_HEADER, kb_public_rows($lotArticles)];
    }

    $auditRows = [];
    foreach ($articles as $article) {
        $auditRows[] = [
            (string) $article['id'],
            (string) $article['modulo'],
            (string) $article['lote'],
            (string) $article['tipo'],
            (string) ($article['intencao'] ?? ''),
            kb_polish_pt_br((string) $article['categoria']),
            kb_polish_pt_br((string) $article['pergunta']),
            (string) $article['superficie'],
            (string) $article['rota'],
            implode('|', $article['rotas_cobertas']),
            (string) $article['permissoes'],
            (string) $article['planos'],
            (string) $article['fontes'],
            (string) ($article['ultima_validacao'] ?? ''),
            $article['validado_ui'] ? 'sim' : 'nao',
            (string) $article['status'],
            (string) $article['conflito'],
            (string) $article['catalogo'],
        ];
    }
    $artifacts['catalogo-auditoria.csv'] = [[
        'id', 'modulo', 'lote', 'tipo', 'intencao', 'categoria', 'pergunta',
        'superficie', 'rota', 'rotas_cobertas', 'permissoes', 'planos', 'fontes',
        'ultima_validacao', 'validado_ui', 'status', 'conflito', 'catalogo',
    ], $auditRows];
    $artifacts['inventario-cobertura.csv'] = [[
        'superficie', 'modulo', 'rota', 'escopo', 'cobertura_navegacao',
        'cobertura_detalhada', 'artigos_navegacao', 'artigos_detalhados', 'motivo_excecao',
    ], kb_inventory_rows($root, $articles)];
    $artifacts['divergencias.md'] = kb_render_divergences_markdown($articles);
    return $artifacts;
}

function kb_write_artifacts(string $outputDir, array $artifacts): void
{
    if (array_filter(array_keys($artifacts), static fn (string $path): bool => str_starts_with($path, 'lotes/')) !== []) {
        $obsoleteLots = [
            '02-empresa-cadastros.csv',
            '03-contratos-locacoes.csv',
            '04-financeiro-pagamentos.csv',
            '05-veiculos-operacao.csv',
            '06-relatorios.csv',
            '07-website-publico.csv',
        ];
        foreach ($obsoleteLots as $filename) {
            $obsoletePath = rtrim($outputDir, '/') . '/lotes/' . $filename;
            if (is_file($obsoletePath) && !unlink($obsoletePath)) {
                throw new RuntimeException("Nao foi possivel remover o lote obsoleto {$obsoletePath}");
            }
        }
    }
    foreach ($artifacts as $relativePath => $artifact) {
        $path = rtrim($outputDir, '/') . '/' . $relativePath;
        if (is_string($artifact)) {
            $directory = dirname($path);
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new RuntimeException("Nao foi possivel criar {$directory}");
            }
            if (file_put_contents($path, $artifact) === false) {
                throw new RuntimeException("Nao foi possivel criar {$path}");
            }
            continue;
        }
        [$header, $rows] = $artifact;
        kb_write_csv($path, $header, $rows);
    }
}

function kb_check_artifacts(string $outputDir, array $artifacts): void
{
    $differences = [];
    foreach ($artifacts as $relativePath => $artifact) {
        $path = rtrim($outputDir, '/') . '/' . $relativePath;
        $actual = is_file($path) ? file_get_contents($path) : false;
        if (is_string($artifact)) {
            $expected = $artifact;
        } else {
            [$header, $rows] = $artifact;
            $expected = kb_render_csv($header, $rows);
        }
        if ($actual === false) {
            $differences[] = "ausente: {$relativePath}";
        } elseif ($actual !== $expected) {
            $differences[] = "desatualizado: {$relativePath}";
        }
    }
    if ($differences !== []) {
        throw new RuntimeException("Artefatos divergentes:\n- " . implode("\n- ", $differences));
    }
}

function kb_generate(
    string $root,
    bool $onlyApproved = false,
    ?string $outputDir = null,
    bool $check = false
): array {
    $articles = kb_load_articles($root);
    $errors = array_merge(kb_validate_articles($articles), kb_validate_report_intentions($articles));
    if ($errors !== []) {
        throw new RuntimeException("Falha na validacao da base:\n- " . implode("\n- ", $errors));
    }

    $artifacts = kb_build_artifacts($root, $articles, $onlyApproved);
    $destination = $outputDir ?? $root . '/knowledge-base';
    if ($check) {
        kb_check_artifacts($destination, $artifacts);
    } else {
        kb_write_artifacts($destination, $artifacts);
    }

    $inventory = kb_inventory_rows($root, $articles);
    $normal = kb_exportable_articles($articles);
    $approved = kb_exportable_articles($articles, true);
    return [
        'total_catalogo' => count($articles),
        'total_exportado' => $onlyApproved ? count($approved) : count($normal),
        'total_aprovado' => count($approved),
        'rotas_inventariadas' => count($inventory),
        'rotas_cobertas_detalhadamente' => count(array_filter($inventory, static fn (array $row): bool => in_array($row[5], ['direta', 'explicita'], true))),
        'rotas_fora_escopo' => count(array_filter($inventory, static fn (array $row): bool => $row[5] === 'fora_escopo')),
        'rotas_pendentes' => count(array_filter($inventory, static fn (array $row): bool => in_array($row[5], ['somente_navegacao', 'pendente'], true))),
        'modo' => $check ? 'verificacao' : 'gravacao',
        'diretorio_saida' => $destination,
    ];
}

function kb_main(array $argv): int
{
    $onlyApproved = false;
    $check = false;
    $outputDir = null;
    foreach (array_slice($argv, 1) as $argument) {
        if ($argument === '--only-approved') {
            $onlyApproved = true;
        } elseif ($argument === '--check') {
            $check = true;
        } elseif (str_starts_with($argument, '--output-dir=')) {
            $outputDir = substr($argument, strlen('--output-dir='));
            if ($outputDir === '') {
                fwrite(STDERR, "--output-dir exige um caminho.\n");
                return 2;
            }
        } else {
            fwrite(STDERR, "Argumento desconhecido: {$argument}\n");
            return 2;
        }
    }

    try {
        $result = kb_generate(kb_project_root(), $onlyApproved, $outputDir, $check);
        fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);
        return 0;
    } catch (Throwable $error) {
        fwrite(STDERR, $error->getMessage() . PHP_EOL);
        return 1;
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(kb_main($argv));
}
