<?php

namespace App\Services;

use App\Models\SiteAparencia;

class WebsiteCssService
{
    /**
     * Compila CSS para um tenant: substitui :root vars com cores do tenant + append css_customizado
     */
    public function compilar(string $chave): string
    {
        $cssPath = APP_ROOT . '/storage/templates/website/assets/css/style.css';

        if (!file_exists($cssPath)) {
            throw new \RuntimeException("Template CSS nao encontrado: {$cssPath}");
        }

        $css = file_get_contents($cssPath);

        // Resolver cores do tenant
        $websiteService = new WebsiteService();
        $aparenciaModel = new SiteAparencia();

        $oldChave = $_SESSION['chave'] ?? null;
        $_SESSION['chave'] = $chave;

        $aparencia = $aparenciaModel->buscarPorChave();
        $presetCor = $aparencia['preset_cor'] ?? 'azul';
        $coresCustomizadas = $aparencia['cores_customizadas'] ?? null;
        $cssCustomizado = $aparencia['css_customizado'] ?? '';

        if ($oldChave !== null) {
            $_SESSION['chave'] = $oldChave;
        }

        $cores = $websiteService->resolverCores($presetCor, $coresCustomizadas);

        // Substituir :root vars no CSS
        $css = $this->substituirCoresRoot($css, $cores);

        // Append CSS customizado
        if (!empty($cssCustomizado)) {
            $css .= "\n/* CSS customizado do tenant */\n" . $cssCustomizado;
        }

        return $css;
    }

    /**
     * Minifica CSS (remove comentarios, whitespace desnecessario)
     */
    public function minificar(string $css): string
    {
        // Remover comentarios
        $css = preg_replace('!/\*.*?\*/!s', '', $css);

        // Remover whitespace
        $css = preg_replace('/\s+/', ' ', $css);

        // Remover espacos ao redor de seletores/propriedades
        $css = preg_replace('/\s*([{}:;,>~+])\s*/', '$1', $css);

        // Remover ultimo ; antes de }
        $css = str_replace(';}', '}', $css);

        return trim($css);
    }

    /**
     * Compila e minifica em uma unica chamada
     */
    public function compilarMinificado(string $chave): string
    {
        return $this->minificar($this->compilar($chave));
    }

    /**
     * Substitui os valores das variaveis CSS :root com as cores do tenant
     */
    private function substituirCoresRoot(string $css, array $cores): string
    {
        // Encontra o bloco :root { ... } e substitui cada variavel
        $pattern = '/(:root\s*\{)(.*?)(\})/s';

        if (preg_match($pattern, $css, $matches)) {
            $rootContent = $matches[2];

            foreach ($cores as $varName => $valor) {
                // Substitui --cor-N: #xxx por --cor-N: #novo_valor
                $varPattern = '/(' . preg_quote($varName, '/') . '\s*:\s*)([^;]+)(;)/';
                $rootContent = preg_replace($varPattern, '${1}' . $valor . '${3}', $rootContent);
            }

            $css = preg_replace($pattern, '${1}' . $rootContent . '${3}', $css);
        }

        return $css;
    }
}
