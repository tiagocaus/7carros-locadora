<?php

namespace App\Services;

use App\Models\SiteAparencia;
use App\Models\SiteBanner;
use App\Models\SiteConteudo;
use App\Models\SiteIdioma;
use App\Models\SiteLink;
use App\Models\SiteSeo;

/**
 * Popula conteúdo padrão do site público quando o tenant ativa pela primeira vez.
 *
 * Idempotente: só insere se a tabela do tenant estiver vazia. Nunca sobrescreve
 * edições feitas pelo cliente. Lê os arrays de /storage/templates/website/seed/.
 *
 * Imagens padrão de banners ficam em /storage/templates/website/defaults/ e são
 * copiadas no momento da ativação para storage/uploads/{chave}/ com sufixo único.
 * A logo padrão é asset fixo do sistema e só vira upload quando o cliente envia uma.
 */
class WebsiteSeedService
{
    /** Idiomas que tem arquivos de seed disponíveis */
    private const IDIOMAS_SUPORTADOS = ['pt_BR', 'en_US', 'es_ES', 'it_IT', 'pt_PT'];

    private string $seedPath;
    private string $defaultsPath;
    private string $uploadsBasePath;
    private string $suffix;

    public function __construct()
    {
        $this->seedPath        = APP_ROOT . '/storage/templates/website/seed';
        $this->defaultsPath    = APP_ROOT . '/storage/templates/website/defaults';
        $this->uploadsBasePath = APP_ROOT . '/storage/uploads';
        $this->suffix          = date('YmdHis');
    }

    /**
     * Popula todas as tabelas com defaults para um tenant.
     */
    public function seedTenant(string $chave): array
    {
        $oldChave = $_SESSION['chave'] ?? null;
        $_SESSION['chave'] = $chave;

        try {
            return [
                'conteudos' => $this->seedConteudos(),
                'banners'   => $this->seedBanners($chave),
                'aparencia' => $this->seedAparencia($chave),
                'links'     => $this->seedLinks(),
                'seo'       => $this->seedSeo(),
                'idiomas'   => $this->seedIdiomas(),
            ];
        } finally {
            if ($oldChave !== null) {
                $_SESSION['chave'] = $oldChave;
            }
        }
    }

    private function seedConteudos(): int
    {
        $model = new SiteConteudo();
        $total = 0;

        foreach (self::IDIOMAS_SUPORTADOS as $idioma) {
            $file = $this->seedPath . "/conteudos-default-{$idioma}.php";
            if (!file_exists($file)) {
                continue;
            }
            // Idempotência por idioma — só popula se não há nada naquele idioma
            if (!empty($model->listarPorIdioma($idioma))) {
                continue;
            }

            $defaults = require $file;
            foreach ($defaults as $pagina => $secoes) {
                foreach ($secoes as $secao => $conteudo) {
                    $model->salvar($pagina, $secao, $idioma, $conteudo);
                    $total++;
                }
            }
        }
        return $total;
    }

    private function seedBanners(string $chave): int
    {
        $model = new SiteBanner();
        if (!empty($model->listar())) {
            return 0;
        }

        $defaults = require $this->seedPath . '/banners-default.php';
        $count = 0;
        foreach ($defaults as $banner) {
            $source = $banner['source'] ?? null;
            if (!$source) continue;

            $destName = $this->copyDefaultAsset($source, $chave, 'banner_' . $this->suffix . '_' . $banner['ordem']);
            if (!$destName) continue;

            unset($banner['source']);
            $banner['foto'] = $destName;
            $model->criar($banner);
            $count++;
        }
        return $count;
    }

    private function seedAparencia(string $chave): int
    {
        $model = new SiteAparencia();
        $existing = $model->buscarPorChave();
        if ($existing) {
            return 0;
        }

        $model->criarOuAtualizar([
            'preset_cor'        => 'azul',
            'logo_fundo_branco' => 1,
            'logo_alinhamento'  => 'centro',
            'fonte_primaria'    => 'Titillium Web',
        ]);
        return 1;
    }

    private function seedLinks(): int
    {
        $model = new SiteLink();
        if (!empty($model->listar())) {
            return 0;
        }

        $defaults = require $this->seedPath . '/links-default.php';
        $model->salvarTodos($defaults);
        return count($defaults);
    }

    private function seedSeo(): int
    {
        $model = new SiteSeo();
        $total = 0;

        foreach (self::IDIOMAS_SUPORTADOS as $idioma) {
            $file = $this->seedPath . "/seo-default-{$idioma}.php";
            if (!file_exists($file)) {
                continue;
            }
            if (!empty($model->listarPorIdioma($idioma))) {
                continue;
            }

            $defaults = require $file;
            foreach ($defaults as $pagina => $dados) {
                $model->salvar($pagina, $idioma, $dados);
                $total++;
            }
        }
        return $total;
    }

    private function seedIdiomas(): int
    {
        $model = new SiteIdioma();
        if (!empty($model->listar())) {
            return 0;
        }

        $model->salvar([
            ['idioma' => 'pt_BR', 'ativo' => 1],
        ]);
        return 1;
    }

    /**
     * Copia um arquivo de /defaults/{source} para /uploads/{chave}[/{subDir}]/{newBasename}.{ext}
     * Retorna o nome final do arquivo (apenas basename) ou null em caso de falha.
     */
    private function copyDefaultAsset(string $source, string $chave, string $newBasename, ?string $subDir = null): ?string
    {
        $sourcePath = $this->defaultsPath . '/' . $source;
        if (!file_exists($sourcePath)) {
            return null;
        }

        $ext = pathinfo($source, PATHINFO_EXTENSION);
        $destName = $newBasename . '.' . $ext;

        $destDir = $this->uploadsBasePath . '/' . $chave;
        if ($subDir) {
            $destDir .= '/' . $subDir;
        }

        if (!is_dir($destDir)) {
            @mkdir($destDir, 0755, true);
        }

        if (!@copy($sourcePath, $destDir . '/' . $destName)) {
            return null;
        }

        return $destName;
    }
}
