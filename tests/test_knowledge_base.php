<?php

declare(strict_types=1);

require_once __DIR__ . '/../scripts/generate-customer-support-knowledge.php';

function kb_test_fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function kb_test_read_csv(string $path, array $expectedHeader): array
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        kb_test_fail("CSV ausente: {$path}");
    }
    $header = fgetcsv($handle);
    if ($header !== $expectedHeader) {
        kb_test_fail("Cabecalho invalido em {$path}");
    }
    $rows = [];
    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) !== count($expectedHeader)) {
            kb_test_fail("Quantidade de colunas invalida em {$path}");
        }
        $rows[] = $row;
    }
    fclose($handle);
    return $rows;
}

function kb_test_remove_directory(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($directory);
}

$root = dirname(__DIR__);
$temporaryDirectory = sys_get_temp_dir() . '/7carros-kb-test-' . bin2hex(random_bytes(6));

try {
    $result = kb_generate($root, false, $temporaryDirectory, false);
    $articles = kb_load_articles($root);
    $exportableArticles = kb_exportable_articles($articles);
    $exportableIds = array_fill_keys(array_column($exportableArticles, 'id'), true);
    $articlesById = [];

    $ids = [];
    $questions = [];
    $fingerprints = [];
    $allowedModules = ['contratos-locacoes', 'empresa', 'financeiro', 'publico', 'relatorios', 'sistema', 'veiculos', 'website'];
    foreach ($articles as $article) {
        $id = (string) $article['id'];
        $question = mb_strtolower(kb_polish_pt_br(trim((string) $article['pergunta'])), 'UTF-8');
        $fingerprint = hash('sha256', kb_polish_pt_br((string) $article['categoria']) . "\0" . $question . "\0" . kb_render_response($article));
        if (isset($ids[$id])) {
            kb_test_fail("ID duplicado: {$id}");
        }
        if (isset($questions[$question])) {
            kb_test_fail("Pergunta duplicada: {$article['pergunta']}");
        }
        if (isset($fingerprints[$fingerprint])) {
            kb_test_fail("Fingerprint duplicado: {$id}");
        }
        $ids[$id] = true;
        $articlesById[$id] = $article;
        $questions[$question] = true;
        $fingerprints[$fingerprint] = true;
        if (!in_array($article['modulo'], $allowedModules, true)) {
            kb_test_fail("Modulo nao normalizado em {$id}: {$article['modulo']}");
        }
        if ($article['tipo'] === 'navegacao'
            && ((string) $article['ultima_validacao'] !== '' || $article['validado_ui'] !== false)) {
            kb_test_fail("Navegacao nao validada recebeu data ou validacao de UI: {$id}");
        }
        if (trim($article['conflito']) !== '' && $article['status'] !== 'rascunho') {
            kb_test_fail("Conflito fora da quarentena editorial: {$id}");
        }
        if (isset($exportableIds[$id])
            && !preg_match('~`[^`]+(?: > [^`]+)+`~u', (string) $article['resposta'])) {
            kb_test_fail("Artigo exportavel sem caminho hierarquico: {$id}");
        }

        foreach (explode(';', (string) $article['fontes']) as $source) {
            $source = trim($source);
            if (!preg_match('~^(app/|docs/)~', $source)) {
                continue;
            }
            $sourcePath = preg_replace('~[#:].*$~', '', $source) ?? $source;
            if (!is_file($root . '/' . $sourcePath)) {
                kb_test_fail("Fonte inexistente em {$id}: {$sourcePath}");
            }
        }
    }

    $master = kb_test_read_csv($temporaryDirectory . '/base-conhecimento.csv', KB_PUBLIC_HEADER);
    if (count($master) !== $result['total_exportado'] || count($master) < 700) {
        kb_test_fail('Quantidade inesperada de artigos no CSV mestre.');
    }
    foreach ($master as $index => $row) {
        if (trim($row[0]) === '' || trim($row[1]) === '' || trim($row[2]) === '') {
            kb_test_fail('Campo vazio no CSV mestre, linha ' . ($index + 2));
        }
        $withoutCode = preg_replace('~`[^`]*`~u', '', $row[2]) ?? $row[2];
        if (str_contains($withoutCode, ' > ')) {
            kb_test_fail("Caminho fora de crases: {$row[1]}");
        }
        if (preg_match('~https?://|www\.~i', $row[2])) {
            kb_test_fail("URL direta encontrada: {$row[1]}");
        }
    }

    $approved = kb_test_read_csv($temporaryDirectory . '/base-conhecimento-aprovada.csv', KB_PUBLIC_HEADER);
    $expectedApproved = kb_public_rows(kb_exportable_articles($articles, true));
    if ($approved !== $expectedApproved) {
        kb_test_fail('O CSV aprovado nao corresponde ao filtro estrito de status e validacao visual.');
    }

    $masterKeys = [];
    foreach ($master as $row) {
        $masterKeys[hash('sha256', json_encode($row, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))] = true;
    }
    $lotKeys = [];
    foreach (KB_LOTS as $filename) {
        $rows = kb_test_read_csv($temporaryDirectory . '/lotes/' . $filename, KB_PUBLIC_HEADER);
        if ($rows === []) {
            kb_test_fail("Lote vazio: {$filename}");
        }
        foreach ($rows as $row) {
            $key = hash('sha256', json_encode($row, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            if (isset($lotKeys[$key])) {
                kb_test_fail("Artigo repetido entre lotes: {$row[1]}");
            }
            $lotKeys[$key] = true;
        }
    }
    ksort($masterKeys);
    ksort($lotKeys);
    if ($masterKeys !== $lotKeys) {
        kb_test_fail('A uniao dos lotes nao e identica ao CSV mestre.');
    }

    $auditHeader = [
        'id', 'modulo', 'lote', 'tipo', 'intencao', 'categoria', 'pergunta',
        'superficie', 'rota', 'rotas_cobertas', 'permissoes', 'planos', 'fontes',
        'ultima_validacao', 'validado_ui', 'status', 'conflito', 'catalogo',
    ];
    $audit = kb_test_read_csv($temporaryDirectory . '/catalogo-auditoria.csv', $auditHeader);
    if (count($audit) !== count($articles)) {
        kb_test_fail('Catalogo de auditoria nao contem todos os artigos.');
    }

    $divergencesPath = $temporaryDirectory . '/divergencias.md';
    $divergences = is_file($divergencesPath) ? file_get_contents($divergencesPath) : false;
    if ($divergences === false) {
        kb_test_fail('Relatorio de divergencias nao foi gerado.');
    }
    $conflictingArticles = array_values(array_filter(
        $articles,
        static fn (array $article): bool => trim((string) $article['conflito']) !== ''
    ));
    $divergenceIds = [];
    foreach ($conflictingArticles as $article) {
        $articleId = (string) $article['id'];
        $divergenceId = (string) ($article['divergencia_id'] ?? '');
        $divergenceIds[$divergenceId] = true;
        if (substr_count($divergences, "- `{$articleId}` —") !== 1) {
            kb_test_fail("Artigo conflitante ausente ou repetido em divergencias.md: {$articleId}");
        }
    }
    if (count($conflictingArticles) !== 18 || count($divergenceIds) !== 17) {
        kb_test_fail('Quantidade inesperada de artigos conflitantes ou divergencias agrupadas.');
    }
    foreach (array_keys($divergenceIds) as $divergenceId) {
        if (substr_count($divergences, "**Identificador:** `{$divergenceId}`") !== 1) {
            kb_test_fail("Divergencia ausente ou repetida no relatorio: {$divergenceId}");
        }
    }
    foreach ([
        '**Caminho:**',
        '**Perguntas e artigos afetados:**',
        '### Esperado / documentação',
        '### Encontrado / implementação',
        '### Risco',
        '### Correção sugerida',
        '### Fontes',
    ] as $requiredSection) {
        if (!str_contains($divergences, $requiredSection)) {
            kb_test_fail("Secao ausente em divergencias.md: {$requiredSection}");
        }
    }

    $inventoryHeader = [
        'superficie', 'modulo', 'rota', 'escopo', 'cobertura_navegacao',
        'cobertura_detalhada', 'artigos_navegacao', 'artigos_detalhados', 'motivo_excecao',
    ];
    $inventory = kb_test_read_csv($temporaryDirectory . '/inventario-cobertura.csv', $inventoryHeader);
    if (count($inventory) !== $result['rotas_inventariadas']) {
        kb_test_fail('Quantidade inesperada de rotas no inventario.');
    }
    foreach ($inventory as $row) {
        [$surface, $module, $route, $scope, $navigationStatus, $detailStatus, $navigationRefs, $detailRefs, $exception] = $row;
        if ($scope === 'principal' && !in_array($detailStatus, ['direta', 'explicita'], true)) {
            kb_test_fail("Rota principal sem cobertura detalhada: {$route}");
        }
        if ($scope !== 'principal' && trim($exception) === '') {
            kb_test_fail("Excecao sem justificativa: {$route}");
        }
        $routeDetailArticles = [];
        foreach (array_filter(explode('|', $detailRefs)) as $articleId) {
            if (str_starts_with($articleId, 'navegacao-')) {
                kb_test_fail("Navegacao usada como cobertura detalhada: {$route}");
            }
            $articleById = $articlesById[$articleId] ?? null;
            if ($articleById === null
                || !in_array($articleById['status'], ['fonte_revisada', 'aprovado'], true)
                || trim($articleById['conflito']) !== '') {
                kb_test_fail("Artigo nao exportavel usado na cobertura detalhada: {$route} -> {$articleId}");
            }
            $routeDetailArticles[] = $articleById;
        }
        if ($scope === 'principal') {
            $hasHierarchicalPath = array_filter(
                $routeDetailArticles,
                static fn (array $article): bool => preg_match(
                    '~`[^`]+(?: > [^`]+)+`~u',
                    (string) $article['resposta']
                ) === 1
            ) !== [];
            if (!$hasHierarchicalPath) {
                kb_test_fail("Rota principal sem orientacao por caminho: {$route}");
            }

            $actionPattern = match (true) {
                str_contains($route, '/adicionar') => '~adicion|cadastr|criar|novo~u',
                str_contains($route, '/editar') => '~edit|alter~u',
                str_contains($route, '/visualizar') => '~visualiz|consult|abrir~u',
                default => null,
            };
            if ($actionPattern !== null) {
                $actionText = mb_strtolower(implode("\n", array_map(
                    static fn (array $article): string => $article['pergunta'] . ' ' . $article['resposta'],
                    $routeDetailArticles
                )), 'UTF-8');
                if (preg_match($actionPattern, $actionText) !== 1) {
                    kb_test_fail("Rota de acao sem orientacao correspondente: {$route}");
                }
            }
        }
    }

    $expectedIntents = ['acesso', 'finalidade', 'filtros', 'campos_resultados', 'interpretacao', 'exportacao', 'problemas'];
    $visibleReports = [];
    $reportIntents = [];
    $reportPaths = [];
    $reportArticles = [];
    foreach ($articles as $article) {
        if (!str_starts_with((string) $article['rota'], '/pages/relatorios/')) {
            continue;
        }
        $reportId = (string) ($article['relatorio_id'] ?? '');
        if ($article['tipo'] === 'navegacao') {
            $visibleReports[$reportId] = true;
            if (!preg_match('~`([^`]+)`~u', (string) $article['resposta'], $pathMatch)) {
                kb_test_fail("Caminho ausente na navegacao do relatorio {$reportId}.");
            }
            $reportPaths[$reportId] = $pathMatch[1];
        }
        if (in_array(($article['intencao'] ?? ''), $expectedIntents, true)) {
            $reportIntents[$reportId][] = $article['intencao'];
            $reportArticles[$reportId][] = $article;
        }
    }
    if (count($visibleReports) !== 69) {
        kb_test_fail('Quantidade inesperada de relatorios visiveis no menu.');
    }
    sort($expectedIntents);
    foreach (array_keys($visibleReports) as $reportId) {
        $intents = $reportIntents[$reportId] ?? [];
        sort($intents);
        if ($intents !== $expectedIntents) {
            kb_test_fail("As sete intencoes nao foram encontradas para {$reportId}.");
        }
        $path = $reportPaths[$reportId] ?? '';
        foreach ($reportArticles[$reportId] ?? [] as $article) {
            if ($article['intencao'] === 'acesso') {
                continue;
            }
            if (!str_contains((string) $article['resposta'], "`{$path}`")) {
                kb_test_fail("Caminho detalhado diverge do menu em {$article['id']}.");
            }
        }
    }

    $maintenanceFilterId = 'relatorios-veicular-manutencoes-filtros';
    $maintenanceFilter = null;
    $grossMarginResults = null;
    $roiResults = null;
    $overdueInvoicesResults = null;
    foreach ($articles as $article) {
        if ($article['id'] === $maintenanceFilterId) {
            $maintenanceFilter = $article;
        }
        if ($article['id'] === 'relatorios-kpis-margem-bruta-campos-resultados') {
            $grossMarginResults = $article;
        }
        if ($article['id'] === 'relatorios-kpis-roi-veiculo-campos-resultados') {
            $roiResults = $article;
        }
        if ($article['id'] === 'relatorios-faturas-vencidas-a-vencer-campos-resultados') {
            $overdueInvoicesResults = $article;
        }
    }
    if ($maintenanceFilter === null || !str_contains(mb_strtolower($maintenanceFilter['resposta'], 'UTF-8'), 'veículo')) {
        kb_test_fail('O filtro de veiculo nao foi documentado no relatorio de manutencoes.');
    }
    if ($grossMarginResults === null
        || !str_contains($grossMarginResults['resposta'], 'cards de totalização')
        || str_contains($grossMarginResults['resposta'], 'tabela')) {
        kb_test_fail('Margem Bruta deve documentar somente os totalizadores existentes.');
    }
    if ($roiResults === null || !str_contains($roiResults['resposta'], 'Receita Total')) {
        kb_test_fail('ROI por Veiculo precisa documentar o card Receita Total.');
    }
    foreach (['Total Vencido', 'Qtd Vencidas', 'Total A Vencer', 'Qtd A Vencer'] as $expectedTotal) {
        if ($overdueInvoicesResults === null || !str_contains($overdueInvoicesResults['resposta'], $expectedTotal)) {
            kb_test_fail("Faturas Vencidas/A Vencer nao documenta o card {$expectedTotal}.");
        }
    }

    $reportCatalog = require $root . '/knowledge-base/articles-reports.php';
    if (count($reportCatalog) !== 417) {
        kb_test_fail('O catalogo de relatorios deve conter 414 intencoes, 2 acoes e 1 quarentena.');
    }

    kb_generate($root, false, $temporaryDirectory, true);
    fwrite(STDOUT, "Base valida: {$result['total_exportado']} perguntas; 69 relatorios com 7 intencoes; {$result['rotas_inventariadas']} rotas sem lacunas no escopo.\n");
} finally {
    kb_test_remove_directory($temporaryDirectory);
}
