<?php

namespace App\Services;

use App\Core\Database;
use App\Models\SiteConfig;
use App\Models\SiteAparencia;
use App\Models\SiteCredencial;
use App\Models\SiteDeployLog;
use App\Models\SiteIdioma;

class WebsiteBuilderService
{
    private string $templatePath;
    private string $tempBasePath;

    public function __construct()
    {
        $this->templatePath = APP_ROOT . '/storage/templates/website';
        $this->tempBasePath = APP_ROOT . '/storage/temp';
    }

    /**
     * Executa o build completo — gera output em diretorio temporario
     *
     * @return string Path do diretorio com os arquivos prontos
     */
    public function build(string $chave): string
    {
        $buildDir = $this->tempBasePath . '/website-build-' . $chave;

        // 1. Criar diretorio temporario
        $this->criarDiretorio($buildDir);
        $this->criarDiretorio($buildDir . '/includes');
        $this->criarDiretorio($buildDir . '/assets/css');
        $this->criarDiretorio($buildDir . '/assets/js');
        $this->criarDiretorio($buildDir . '/assets/img');
        $this->criarDiretorio($buildDir . '/lang');
        $this->criarDiretorio($buildDir . '/cache');

        // 2. Copiar arquivos PHP do template
        $this->copiarPaginas($buildDir);
        $this->copiarIncludes($buildDir);

        // 3. Copiar logo e favicon do tenant para assets/img (antes do config)
        $assetsTenant = $this->copiarLogoFavicon($chave, $buildDir);

        // 4. Gerar config.php com dados do tenant (usa paths locais dos assets)
        $this->gerarConfig($chave, $buildDir, $assetsTenant);

        // 5. Compilar CSS com cores do tenant
        $this->compilarCss($chave, $buildDir);

        // 6. Copiar + minificar JS
        $this->compilarJs($buildDir);

        // 7. Copiar idiomas habilitados
        $this->copiarIdiomas($chave, $buildDir);

        // 8. Gerar sitemap.xml e robots.txt
        $this->gerarSitemap($chave, $buildDir);
        $this->gerarRobots($chave, $buildDir);

        // 9. Copiar versao.json
        $this->copiarArquivo(
            $this->templatePath . '/versao.json',
            $buildDir . '/versao.json'
        );

        // 10. Criar cache/.htaccess
        file_put_contents($buildDir . '/cache/.htaccess', "Deny from all\n");

        // 11. Copiar imagens estaticas do CSS
        $this->copiarImagensCss($buildDir);

        // 12. Copiar libs JS/CSS pre-minificadas (chosen-select, etc)
        $this->copiarLibsAssets($buildDir);

        return $buildDir;
    }

    /**
     * Copia bibliotecas pre-minificadas do template (CSS e JS .min.*)
     * que nao precisam de processamento. Hoje: chosen-select.
     */
    private function copiarLibsAssets(string $buildDir): void
    {
        $libs = [
            'assets/css/chosen-select.min.css',
            'assets/js/chosen-select.min.js',
            // Helper CEP (ViaCEP + zippopotam) pre-minificado
            'assets/js/cep.min.js',
        ];
        foreach ($libs as $rel) {
            $origem = $this->templatePath . '/' . $rel;
            if (file_exists($origem)) {
                copy($origem, $buildDir . '/' . $rel);
            }
        }
    }

    /**
     * Copia logo e favicon do tenant de storage/uploads/{chave}/
     * para buildDir/assets/img/ com nome padronizado (logo.ext, favicon.ext).
     *
     * Retorna associando a chave {logo,favicon} => filename relativo
     * (sem path do buildDir) — usado pelo gerarConfig para montar logo_url.
     *
     * @return array{logo: string, favicon: string} paths relativos ou ''
     */
    private function copiarLogoFavicon(string $chave, string $buildDir): array
    {
        $result = ['logo' => '', 'favicon' => ''];

        // Precisa da sessao setada pros Models buscarem a aparencia
        $oldChave = $_SESSION['chave'] ?? null;
        $_SESSION['chave'] = $chave;

        try {
            $aparencia = (new SiteAparencia())->buscarPorChave();
        } finally {
            if ($oldChave !== null) {
                $_SESSION['chave'] = $oldChave;
            } else {
                unset($_SESSION['chave']);
            }
        }

        if ($aparencia) {
            foreach (['logo', 'favicon'] as $campo) {
                $filename = $aparencia[$campo] ?? null;
                if (empty($filename)) {
                    continue;
                }

                $origem = APP_ROOT . '/storage/uploads/' . $chave . '/' . $filename;
                if (!file_exists($origem)) {
                    continue;
                }

                $ext = pathinfo($filename, PATHINFO_EXTENSION) ?: 'webp';
                $destinoRelativo = 'assets/img/' . $campo . '.' . $ext;
                $destinoAbsoluto = $buildDir . '/' . $destinoRelativo;

                if (@copy($origem, $destinoAbsoluto)) {
                    $result[$campo] = $destinoRelativo;
                }
            }
        }

        if ($result['logo'] === '') {
            $logoPadraoOrigem = APP_ROOT . '/public/assets/img/logo_padrao.png';
            $logoPadraoRelativo = 'assets/img/logo_padrao.png';
            if (file_exists($logoPadraoOrigem) && @copy($logoPadraoOrigem, $buildDir . '/' . $logoPadraoRelativo)) {
                $result['logo'] = $logoPadraoRelativo;
            }
        }

        return $result;
    }

    /**
     * Build para preview (mesmo que build mas sem limpar depois)
     */
    public function preview(string $chave): string
    {
        return $this->build($chave);
    }

    /**
     * Le a versao do template a partir do versao.json
     */
    public function getVersaoArquivo(): string
    {
        $path = $this->templatePath . '/versao.json';
        if (!file_exists($path)) {
            return '0.0.0';
        }
        $json = json_decode(file_get_contents($path), true);
        return $json['versao'] ?? '0.0.0';
    }

    /**
     * Remove diretorio temporario de build
     */
    public function cleanup(string $buildPath): void
    {
        if (!is_dir($buildPath) || strpos($buildPath, 'website-build-') === false) {
            return;
        }
        $this->removerDiretorio($buildPath);
    }

    /**
     * Deploy completo: build + FTP upload + registrar no BD
     *
     * @return array{success: bool, message: string, detalhes: array}
     */
    public function deploy(string $chave, ?int $funcionarioId = null): array
    {
        $startTime = microtime(true);
        $versao = $this->getVersaoArquivo();
        $buildPath = null;

        // Setar chave na sessao para os Models funcionarem
        $hadChave = array_key_exists('chave', $_SESSION);
        $oldChave = $_SESSION['chave'] ?? null;
        $_SESSION['chave'] = $chave;

        // Registrar inicio do deploy
        $deployLogModel = new SiteDeployLog();
        $logId = $deployLogModel->registrar($versao, 'deploy', 'iniciado', null, $funcionarioId);

        try {
            // 1. Build
            $buildPath = $this->build($chave);

            // 2. Buscar credenciais FTP
            $credModel = new SiteCredencial();
            $cred = $credModel->getDecrypted();

            if (!$cred) {
                throw new \RuntimeException('Credenciais FTP nao configuradas');
            }

            // 3. Conectar via FTP/SFTP
            $ftpService = new FtpService();
            $connected = $ftpService->connect(
                $cred['host'],
                $cred['porta'],
                $cred['usuario'],
                $cred['senha'],
                $cred['tipo']
            );

            if (!$connected) {
                throw new \RuntimeException('Falha ao conectar ao servidor FTP');
            }

            $remoteDir = $cred['diretorio'] ?: '/public_html';

            // 3.5. Remover index.html padrão da hospedagem (cPanel etc).
            // Hospedagens recém-criadas trazem um index.html de boas-vindas que o Apache
            // serve no lugar do index.php. deleteRemoteFile() retorna silenciosamente se
            // o arquivo não existir.
            $ftpService->deleteRemoteFile(rtrim($remoteDir, '/') . '/index.html');

            // 4. Upload recursivo
            $uploadResult = $ftpService->uploadDirectory($buildPath, $remoteDir);

            // 5. Desconectar
            $ftpService->disconnect();

            if ($uploadResult['arquivos_enviados'] < 1) {
                throw new \RuntimeException('Nenhum arquivo foi enviado ao FTP');
            }

            if (!empty($uploadResult['erros'])) {
                $amostraErros = implode(', ', array_slice($uploadResult['erros'], 0, 5));
                $totalErros = count($uploadResult['erros']);
                throw new \RuntimeException(
                    "Falha ao enviar {$totalErros} arquivo(s) ao FTP: {$amostraErros}"
                );
            }

            // 6. Cleanup
            $this->cleanup($buildPath);
            $buildPath = null;

            $tempoSegundos = round(microtime(true) - $startTime, 2);
            $detalhes = [
                'arquivos_enviados' => $uploadResult['arquivos_enviados'],
                'tempo_segundos'    => $tempoSegundos,
                'erros'             => $uploadResult['erros'],
            ];

            // 7. Atualizar versao e timestamp
            $configModel = new SiteConfig();
            $configModel->registrarDeploy($versao);

            // 8. Registrar sucesso no log
            $deployLogModel->atualizarStatus($logId, 'sucesso', $detalhes);

            // Restaurar sessao
            if ($hadChave) {
                $_SESSION['chave'] = $oldChave;
            } else {
                unset($_SESSION['chave']);
            }

            return [
                'success'  => true,
                'message'  => "Deploy realizado com sucesso ({$uploadResult['arquivos_enviados']} arquivos em {$tempoSegundos}s)",
                'detalhes' => $detalhes,
            ];

        } catch (\Exception $e) {
            // Cleanup em caso de erro
            if ($buildPath && is_dir($buildPath)) {
                $this->cleanup($buildPath);
            }

            $tempoSegundos = round(microtime(true) - $startTime, 2);
            $detalhes = [
                'erro'            => $e->getMessage(),
                'tempo_segundos'  => $tempoSegundos,
            ];

            // Registrar falha
            $deployLogModel->atualizarStatus($logId, 'falha', $detalhes);

            // Restaurar sessao
            if ($hadChave) {
                $_SESSION['chave'] = $oldChave;
            } else {
                unset($_SESSION['chave']);
            }

            return [
                'success'  => false,
                'message'  => 'Erro no deploy: ' . $e->getMessage(),
                'detalhes' => $detalhes,
            ];
        }
    }

    // =========================================================================
    // PASSOS DO BUILD
    // =========================================================================

    private function copiarPaginas(string $buildDir): void
    {
        $paginas = [
            'index.php', 'sobre.php', 'veiculos.php', 'contato.php', 'reserva.php',
            // Proxies AJAX usados pelo JS do site para chamar /api/public/...
            'ajax-disponibilidade.php',
            'ajax-reserva.php',
            'ajax-cliente-por-documento.php',
            'ajax-cliente-existe.php',
            'ajax-cliente-login.php',
            'ajax-cliente-logout.php',
            'ajax-cliente-senha-reset.php',
        ];
        foreach ($paginas as $pagina) {
            $this->copiarArquivo(
                $this->templatePath . '/' . $pagina,
                $buildDir . '/' . $pagina
            );
        }
    }

    private function copiarIncludes(string $buildDir): void
    {
        $includes = ['header.php', 'footer.php', 'head.php', 'whatsapp-float.php',
                      'structured-data.php', 'manutencao.php', 'functions.php', 'api.php'];
        foreach ($includes as $file) {
            $this->copiarArquivo(
                $this->templatePath . '/includes/' . $file,
                $buildDir . '/includes/' . $file
            );
        }
    }

    private function gerarConfig(string $chave, string $buildDir, array $assetsTenant = []): void
    {
        $oldChave = $_SESSION['chave'] ?? null;
        $_SESSION['chave'] = $chave;

        $configModel = new SiteConfig();
        $config = $configModel->buscarPorChave();

        $aparenciaModel = new SiteAparencia();
        $aparencia = $aparenciaModel->buscarPorChave();

        if ($oldChave !== null) {
            $_SESSION['chave'] = $oldChave;
        }

        if (!$config) {
            throw new \RuntimeException("Configuracao do site nao encontrada para chave: {$chave}");
        }

        $apiToken = !empty($config['api_token']) ? decrypt($config['api_token']) : '';

        // Montar idiomas ativos
        $_SESSION['chave'] = $chave;
        $idiomaModel = new SiteIdioma();
        $idiomas = $idiomaModel->listarAtivos();
        $idiomasAtivos = array_map(fn($i) => $i['idioma'], $idiomas);
        if (empty($idiomasAtivos)) {
            $idiomasAtivos = [$config['idioma_padrao'] ?? 'pt_BR'];
        }
        if ($oldChave !== null) {
            $_SESSION['chave'] = $oldChave;
        }

        $appUrl = Database::env('APP_URL', 'https://locadora.7carros.com');

        // Paths relativos para os assets locais (copiados pelo build em /assets/img/)
        $logoUrl    = $assetsTenant['logo']    ?? '';
        $faviconUrl = $assetsTenant['favicon'] ?? '';

        // Buscar nome da empresa via MatrizFilial Model
        $mfModel = new \App\Models\MatrizFilial();
        $mf = $mfModel->buscarMatriz();
        $nomeEmpresa = $mf['nome_fantasia'] ?? $mf['razao_social'] ?? '';

        $configContent = "<?php\nreturn " . var_export([
            'chave'              => $chave,
            'api_url'            => $appUrl,
            'api_token'          => $apiToken,
            'nome_empresa'       => $nomeEmpresa,
            'dominio'            => $config['dominio'] ?? '',
            'idioma_padrao'      => $config['idioma_padrao'] ?? 'pt_BR',
            'idiomas_ativos'     => $idiomasAtivos,
            'whatsapp_numero'    => $config['whatsapp_numero'] ?? '',
            'whatsapp_mensagem'  => $config['whatsapp_mensagem'] ?? '',
            'whatsapp_flutuante' => (bool) $config['whatsapp_flutuante'],
            'reserva_online'     => (bool) $config['reserva_online'],
            'overbooking'        => (bool) $config['overbooking'],
            'pagamento_antecipado' => (bool) $config['pagamento_antecipado'],
            'manutencao'         => (bool) $config['manutencao'],
            'logo_url'           => $logoUrl,
            'favicon_url'        => $faviconUrl,
            'logo_fundo_branco'  => (bool) ($aparencia['logo_fundo_branco'] ?? true),
            'logo_alinhamento'   => $aparencia['logo_alinhamento'] ?? 'centro',
            'cache_ttl'          => 3600,
            'cache_dir'          => "__DIR__ . '/../cache/'",
            'versao'             => $this->getVersaoArquivo(),
            'deploy'             => bin2hex(random_bytes(4)),
        ], true) . ";\n";

        // Corrigir o cache_dir para que seja codigo PHP e nao string literal
        $configContent = str_replace(
            "'__DIR__ . \\'/../cache/\\''",
            "__DIR__ . '/../cache/'",
            $configContent
        );

        file_put_contents($buildDir . '/includes/config.php', $configContent);
    }

    private function compilarCss(string $chave, string $buildDir): void
    {
        $cssService = new WebsiteCssService();
        $css = $cssService->compilarMinificado($chave);
        file_put_contents($buildDir . '/assets/css/style.min.css', $css);
    }

    private function compilarJs(string $buildDir): void
    {
        $jsSource = $this->templatePath . '/assets/js/custom.js';
        if (!file_exists($jsSource)) {
            return;
        }

        $js = file_get_contents($jsSource);

        // Minificacao basica: remover comentarios de linha e multiplos espacos
        $js = preg_replace('#//.*$#m', '', $js);
        $js = preg_replace('/\s+/', ' ', $js);

        file_put_contents($buildDir . '/assets/js/custom.min.js', trim($js));
    }

    private function copiarIdiomas(string $chave, string $buildDir): void
    {
        $oldChave = $_SESSION['chave'] ?? null;
        $_SESSION['chave'] = $chave;

        $idiomaModel = new SiteIdioma();
        $idiomas = $idiomaModel->listarAtivos();

        if ($oldChave !== null) {
            $_SESSION['chave'] = $oldChave;
        }

        // Garantir pelo menos pt_BR
        $codigos = array_map(fn($i) => $i['idioma'], $idiomas);
        if (empty($codigos)) {
            $codigos = ['pt_BR'];
        }

        foreach ($codigos as $lang) {
            $source = $this->templatePath . '/lang/' . $lang . '.php';
            if (file_exists($source)) {
                copy($source, $buildDir . '/lang/' . $lang . '.php');
            }
        }
    }

    private function gerarSitemap(string $chave, string $buildDir): void
    {
        $oldChave = $_SESSION['chave'] ?? null;
        $_SESSION['chave'] = $chave;
        $configModel = new SiteConfig();
        $config = $configModel->buscarPorChave();
        if ($oldChave !== null) {
            $_SESSION['chave'] = $oldChave;
        }

        $dominio = $config['dominio'] ?? 'exemplo.com';
        $baseUrl = 'https://' . $dominio;
        $today = today();
        $paginas = ['index.php', 'sobre.php', 'veiculos.php', 'contato.php'];

        if (!empty($config['reserva_online'])) {
            $paginas[] = 'reserva.php';
        }

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

        foreach ($paginas as $pagina) {
            $loc = $pagina === 'index.php' ? '/' : '/' . $pagina;
            $priority = $pagina === 'index.php' ? '1.0' : '0.8';
            $xml .= "  <url>\n";
            $xml .= "    <loc>{$baseUrl}{$loc}</loc>\n";
            $xml .= "    <lastmod>{$today}</lastmod>\n";
            $xml .= "    <priority>{$priority}</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= "</urlset>\n";
        file_put_contents($buildDir . '/sitemap.xml', $xml);
    }

    private function gerarRobots(string $chave, string $buildDir): void
    {
        $oldChave = $_SESSION['chave'] ?? null;
        $_SESSION['chave'] = $chave;
        $configModel = new SiteConfig();
        $config = $configModel->buscarPorChave();
        if ($oldChave !== null) {
            $_SESSION['chave'] = $oldChave;
        }

        $dominio = $config['dominio'] ?? 'exemplo.com';
        $content = "User-agent: *\nAllow: /\n\nSitemap: https://{$dominio}/sitemap.xml\n";
        file_put_contents($buildDir . '/robots.txt', $content);
    }

    private function copiarImagensCss(string $buildDir): void
    {
        $cssDir = $this->templatePath . '/assets/css';
        $imageExts = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico'];

        if (!is_dir($cssDir)) {
            return;
        }

        foreach (scandir($cssDir) as $file) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, $imageExts, true)) {
                copy($cssDir . '/' . $file, $buildDir . '/assets/css/' . $file);
            }
        }
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function criarDiretorio(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    private function copiarArquivo(string $source, string $dest): void
    {
        if (file_exists($source)) {
            copy($source, $dest);
        }
    }

    private function removerDiretorio(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getRealPath());
            } else {
                unlink($item->getRealPath());
            }
        }

        rmdir($dir);
    }
}
